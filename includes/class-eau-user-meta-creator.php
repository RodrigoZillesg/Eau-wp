<?php
namespace EauSystem;

/**
 * Classe para criar meta boxes de usuários dinamicamente
 */
class Eau_User_Meta_Creator {

    /**
     * Cria meta box de usuário
     */
    public function create_user_meta_box($meta_box_name, $selected_columns, $meta_key_prefix = '') {
        global $wpdb;

        // Valida entrada
        if (empty($meta_box_name) || empty($selected_columns)) {
            return new \WP_Error('invalid_input', 'Nome do meta box ou colunas não fornecidos.');
        }

        // Sanitiza dados
        $meta_box_name = sanitize_text_field($meta_box_name);
        $slug = sanitize_title($meta_box_name);
        $meta_key_prefix = $this->sanitize_prefix($meta_key_prefix);

        // Prepara meta fields
        $meta_fields = $this->prepare_meta_fields($selected_columns, $meta_key_prefix);

        // Monta estrutura para salvar
        $meta_box_data = array(
            'slug' => $slug,
            'name' => $meta_box_name,
            'status' => 'publish',
            'meta_fields' => $meta_fields,
            'meta_key_prefix' => $meta_key_prefix,
            'created_at' => current_time('mysql')
        );

        // Salva no banco de dados
        $result = $this->save_to_database($meta_box_data);

        if (is_wp_error($result)) {
            return $result;
        }

        return array(
            'success' => true,
            'message' => 'Meta Box de usuário criado com sucesso!',
            'slug' => $slug,
            'name' => $meta_box_name,
            'fields_count' => count($meta_fields)
        );
    }

    /**
     * Salva meta box no banco de dados
     */
    private function save_to_database($data) {
        $meta_boxes = get_option('eau_user_meta_boxes', array());
        $meta_boxes[$data['slug']] = $data;

        $updated = update_option('eau_user_meta_boxes', $meta_boxes);

        if (!$updated && !isset($meta_boxes[$data['slug']])) {
            return new \WP_Error('save_failed', 'Erro ao salvar meta box no banco de dados.');
        }

        return true;
    }

    /**
     * Prepara meta fields baseado nas colunas selecionadas
     */
    private function prepare_meta_fields($columns, $prefix = '') {
        $meta_fields = array();

        foreach ($columns as $column) {
            $field_name = sanitize_key($column);

            // Adiciona prefixo se fornecido
            if (!empty($prefix)) {
                $field_name = $prefix . '_' . $field_name;
            }

            $meta_fields[] = array(
                'name' => $field_name,
                'title' => $column,
                'type' => $this->detect_field_type($column),
                'original_column' => $column
            );
        }

        return $meta_fields;
    }

    /**
     * Detecta tipo de campo baseado no nome
     */
    private function detect_field_type($column_name) {
        $column_lower = strtolower($column_name);

        // Tipos de campo
        if (preg_match('/(email|e-mail)/', $column_lower)) {
            return 'email';
        }

        if (preg_match('/(phone|telefone|celular|tel)/', $column_lower)) {
            return 'text';
        }

        if (preg_match('/(cpf|cnpj|rg|document)/', $column_lower)) {
            return 'text';
        }

        if (preg_match('/(date|data|nascimento|birth)/', $column_lower)) {
            return 'date';
        }

        if (preg_match('/(number|numero|idade|age|quantity)/', $column_lower)) {
            return 'number';
        }

        if (preg_match('/(url|site|website|link)/', $column_lower)) {
            return 'url';
        }

        if (preg_match('/(description|descricao|bio|about)/', $column_lower)) {
            return 'textarea';
        }

        return 'text';
    }

    /**
     * Sanitiza prefixo do meta key
     */
    private function sanitize_prefix($prefix) {
        if (empty($prefix)) {
            return '';
        }

        // Remove caracteres não permitidos
        $prefix = preg_replace('/[^a-z0-9_]/i', '', $prefix);
        $prefix = strtolower($prefix);

        return $prefix;
    }

    /**
     * Retorna meta boxes registrados
     */
    public static function get_registered_meta_boxes() {
        return get_option('eau_user_meta_boxes', array());
    }

    /**
     * Deleta um meta box
     */
    public function delete_meta_box($slug) {
        if (empty($slug)) {
            return new \WP_Error('invalid_slug', 'Slug do meta box não fornecido.');
        }

        $meta_boxes = get_option('eau_user_meta_boxes', array());

        if (!isset($meta_boxes[$slug])) {
            return new \WP_Error('not_found', 'Meta box não encontrado.');
        }

        unset($meta_boxes[$slug]);
        update_option('eau_user_meta_boxes', $meta_boxes);

        return array(
            'success' => true,
            'message' => 'Meta box deletado com sucesso.'
        );
    }

    /**
     * Retorna campos de um meta box específico
     */
    public static function get_meta_box_fields($slug) {
        $meta_boxes = self::get_registered_meta_boxes();

        if (isset($meta_boxes[$slug]) && isset($meta_boxes[$slug]['meta_fields'])) {
            return $meta_boxes[$slug]['meta_fields'];
        }

        return array();
    }
}
