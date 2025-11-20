<?php
namespace EauSystem;

/**
 * Classe para importar usuários do CSV
 */
class Eau_User_Importer {

    private $column_mapping;
    private $conditions;
    private $user_role;
    private $send_email;
    private $default_password;

    /**
     * Importa usuários do CSV em lote
     */
    public function import_batch($csv_filepath, $column_mapping, $offset = 0, $limit = 25, $conditions = array(), $user_role = 'subscriber', $send_email = false, $default_password = '') {
        $this->column_mapping = $column_mapping;
        $this->conditions = $conditions;
        $this->user_role = $user_role;
        $this->send_email = $send_email;
        $this->default_password = $default_password;

        // Lê dados do CSV
        $csv_handler = new Eau_CSV_Handler();
        $csv_data = $csv_handler->get_csv_data($csv_filepath);

        if (is_wp_error($csv_data)) {
            return $csv_data;
        }

        $total_rows = count($csv_data);
        $batch_data = array_slice($csv_data, $offset, $limit);

        $imported = 0;
        $skipped = 0;
        $errors = array();

        foreach ($batch_data as $row_index => $row) {
            $actual_row_number = $offset + $row_index + 1;
            $result = $this->import_single_user($row, $actual_row_number);

            if (is_wp_error($result)) {
                $skipped++;
                $errors[] = array(
                    'row' => $actual_row_number,
                    'message' => $result->get_error_message()
                );
            } else {
                $imported++;
            }
        }

        return array(
            'success' => true,
            'imported' => $imported,
            'skipped' => $skipped,
            'total' => $total_rows,
            'processed' => $offset + count($batch_data),
            'has_more' => ($offset + count($batch_data)) < $total_rows,
            'errors' => $errors
        );
    }

    /**
     * Importa um único usuário do CSV
     */
    private function import_single_user($row, $row_number) {
        // Valida condicionais antes de importar
        if (!empty($this->conditions)) {
            $passes_conditions = $this->validate_conditions($row);
            if (!$passes_conditions) {
                return new \WP_Error('condition_not_met', 'Linha ' . $row_number . ': Não atende às condições');
            }
        }

        // Campos obrigatórios
        $email = $this->get_mapped_value($row, 'user_email');
        $username = $this->get_mapped_value($row, 'user_login');

        // Validações básicas
        if (empty($email) || !is_email($email)) {
            return new \WP_Error('invalid_email', 'Linha ' . $row_number . ': Email inválido ou vazio');
        }

        // Se não tem username, usa parte do email
        if (empty($username)) {
            $username = sanitize_user(current(explode('@', $email)));
        }

        // Verifica se usuário já existe
        if (email_exists($email)) {
            return new \WP_Error('email_exists', 'Linha ' . $row_number . ': Email já cadastrado - ' . $email);
        }

        if (username_exists($username)) {
            // Se username existe, adiciona número
            $base_username = $username;
            $counter = 1;
            while (username_exists($username)) {
                $username = $base_username . $counter;
                $counter++;
            }
        }

        // Define senha
        $password = $this->get_mapped_value($row, 'user_pass');
        if (empty($password)) {
            $password = !empty($this->default_password) ? $this->default_password : wp_generate_password(12, false);
        }

        // Prepara dados do usuário
        $user_data = array(
            'user_login' => $username,
            'user_email' => sanitize_email($email),
            'user_pass' => $password,
            'role' => $this->user_role,
            'user_registered' => current_time('mysql')
        );

        // Campos opcionais do usuário
        $optional_fields = array('first_name', 'last_name', 'display_name', 'user_url', 'description');
        foreach ($optional_fields as $field) {
            $value = $this->get_mapped_value($row, $field);
            if (!empty($value)) {
                $user_data[$field] = sanitize_text_field($value);
            }
        }

        // Se não tem display_name, tenta montar com first_name + last_name
        if (empty($user_data['display_name'])) {
            $display_parts = array();
            if (!empty($user_data['first_name'])) {
                $display_parts[] = $user_data['first_name'];
            }
            if (!empty($user_data['last_name'])) {
                $display_parts[] = $user_data['last_name'];
            }
            if (!empty($display_parts)) {
                $user_data['display_name'] = implode(' ', $display_parts);
            } else {
                $user_data['display_name'] = $username;
            }
        }

        // Desativa notificação padrão do WP se send_email for false
        if (!$this->send_email) {
            add_filter('wp_new_user_notification_email_admin', '__return_false');
            add_filter('wp_new_user_notification_email', '__return_false');
        }

        // Cria o usuário
        $user_id = wp_insert_user($user_data);

        // Remove os filtros
        if (!$this->send_email) {
            remove_filter('wp_new_user_notification_email_admin', '__return_false');
            remove_filter('wp_new_user_notification_email', '__return_false');
        }

        if (is_wp_error($user_id)) {
            return new \WP_Error('insert_failed', 'Linha ' . $row_number . ': ' . $user_id->get_error_message());
        }

        // Adiciona user meta fields customizados
        $this->add_user_meta_fields($user_id, $row);

        // Envia email de boas-vindas se solicitado
        if ($this->send_email) {
            wp_new_user_notification($user_id, null, 'both');
        }

        return $user_id;
    }

    /**
     * Adiciona user meta fields customizados
     */
    private function add_user_meta_fields($user_id, $row) {
        if (!isset($this->column_mapping['meta_fields']) || !is_array($this->column_mapping['meta_fields'])) {
            return;
        }

        foreach ($this->column_mapping['meta_fields'] as $meta_key => $csv_column) {
            if (!empty($csv_column) && isset($row[$csv_column])) {
                $value = sanitize_text_field($row[$csv_column]);
                update_user_meta($user_id, $meta_key, $value);
            }
        }
    }

    /**
     * Busca valor mapeado de uma coluna
     */
    private function get_mapped_value($row, $field_name) {
        // Verifica mapeamento direto
        if (isset($this->column_mapping[$field_name])) {
            $column = $this->column_mapping[$field_name];
            return isset($row[$column]) ? $row[$column] : '';
        }

        return '';
    }

    /**
     * Valida se a linha atende às condições definidas
     */
    private function validate_conditions($row) {
        if (empty($this->conditions) || !is_array($this->conditions)) {
            return true;
        }

        foreach ($this->conditions as $condition) {
            $column = $condition['column'];
            $operator = $condition['operator'];
            $value = $condition['value'];

            $row_value = isset($row[$column]) ? $row[$column] : '';
            $condition_met = $this->check_condition($row_value, $operator, $value);

            if (!$condition_met) {
                return false;
            }
        }

        return true;
    }

    /**
     * Verifica uma condição específica
     */
    private function check_condition($row_value, $operator, $expected_value) {
        switch ($operator) {
            case 'not_empty':
                return !empty($row_value);

            case 'empty':
                return empty($row_value);

            case 'equals':
                return $row_value == $expected_value;

            case 'not_equals':
                return $row_value != $expected_value;

            case 'greater_than':
                return is_numeric($row_value) && floatval($row_value) > floatval($expected_value);

            case 'greater_than_or_equal':
                return is_numeric($row_value) && floatval($row_value) >= floatval($expected_value);

            case 'less_than':
                return is_numeric($row_value) && floatval($row_value) < floatval($expected_value);

            case 'less_than_or_equal':
                return is_numeric($row_value) && floatval($row_value) <= floatval($expected_value);

            case 'contains':
                return stripos($row_value, $expected_value) !== false;

            case 'not_contains':
                return stripos($row_value, $expected_value) === false;

            case 'starts_with':
                return stripos($row_value, $expected_value) === 0;

            case 'ends_with':
                $length = strlen($expected_value);
                return substr($row_value, -$length) === $expected_value;

            default:
                return true;
        }
    }
}
