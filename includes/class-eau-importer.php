<?php
namespace EauSystem;

/**
 * Classe para importar dados do CSV para posts
 */
class Eau_Importer {

    private $post_type_slug;
    private $meta_fields;
    private $column_mapping;

    /**
     * Importa dados do CSV para posts
     */
    public function import_csv_data($csv_filepath, $post_type_slug, $column_mapping) {
        $this->post_type_slug = $post_type_slug;
        $this->column_mapping = $column_mapping;

        // Busca os meta fields do post type
        $post_types = Eau_Post_Type_Creator::get_registered_post_types();

        if (!isset($post_types[$post_type_slug])) {
            return new \WP_Error('invalid_post_type', 'Post Type não encontrado.');
        }

        $this->meta_fields = $post_types[$post_type_slug]['meta_fields'];

        // Lê dados do CSV
        $csv_handler = new Eau_CSV_Handler();
        $csv_data = $csv_handler->get_csv_data($csv_filepath);

        if (is_wp_error($csv_data)) {
            return $csv_data;
        }

        // Importa os dados
        $results = $this->process_import($csv_data);

        return $results;
    }

    /**
     * Processa a importação dos dados
     */
    private function process_import($csv_data) {
        $imported = 0;
        $skipped = 0;
        $errors = array();

        foreach ($csv_data as $row_index => $row) {
            $result = $this->import_single_row($row, $row_index + 1);

            if (is_wp_error($result)) {
                $skipped++;
                $errors[] = array(
                    'row' => $row_index + 1,
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
            'total' => count($csv_data),
            'errors' => $errors
        );
    }

    /**
     * Importa uma única linha do CSV como post
     */
    private function import_single_row($row, $row_number) {
        // Determina o título do post
        $post_title = $this->get_post_title($row);

        if (empty($post_title)) {
            return new \WP_Error('empty_title', 'Linha ' . $row_number . ': Título vazio');
        }

        // Cria o post
        $post_data = array(
            'post_title' => sanitize_text_field($post_title),
            'post_type' => $this->post_type_slug,
            'post_status' => 'publish',
            'post_author' => get_current_user_id(),
        );

        // Verifica se tem coluna mapeada para conteúdo
        if (isset($this->column_mapping['post_content'])) {
            $content_column = $this->column_mapping['post_content'];
            if (isset($row[$content_column])) {
                $post_data['post_content'] = wp_kses_post($row[$content_column]);
            }
        }

        $post_id = wp_insert_post($post_data, true);

        if (is_wp_error($post_id)) {
            return new \WP_Error('insert_failed', 'Linha ' . $row_number . ': ' . $post_id->get_error_message());
        }

        // Adiciona os meta fields
        $this->add_meta_fields($post_id, $row);

        return $post_id;
    }

    /**
     * Determina o título do post baseado no mapeamento ou primeira coluna
     */
    private function get_post_title($row) {
        // Se tem mapeamento específico para título
        if (isset($this->column_mapping['post_title'])) {
            $title_column = $this->column_mapping['post_title'];
            return isset($row[$title_column]) ? $row[$title_column] : '';
        }

        // Senão, usa a primeira coluna não vazia
        foreach ($row as $value) {
            if (!empty($value)) {
                return $value;
            }
        }

        return '';
    }

    /**
     * Adiciona meta fields ao post
     */
    private function add_meta_fields($post_id, $row) {
        foreach ($this->meta_fields as $field) {
            $field_name = $field['name'];

            // Busca a coluna mapeada para este campo
            $csv_column = $this->get_mapped_column($field_name);

            if ($csv_column && isset($row[$csv_column])) {
                $value = $this->sanitize_field_value($row[$csv_column], $field['type']);
                update_post_meta($post_id, $field_name, $value);
            }
        }
    }

    /**
     * Busca a coluna CSV mapeada para um campo
     */
    private function get_mapped_column($field_name) {
        // Verifica no mapeamento personalizado
        if (isset($this->column_mapping['fields'][$field_name])) {
            return $this->column_mapping['fields'][$field_name];
        }

        // Tenta encontrar por correspondência de nome
        foreach ($this->column_mapping['available_columns'] as $column) {
            $column_normalized = $this->normalize_string($column);
            $field_normalized = $this->normalize_string($field_name);

            if ($column_normalized === $field_normalized) {
                return $column;
            }
        }

        return null;
    }

    /**
     * Normaliza string para comparação
     */
    private function normalize_string($string) {
        $string = strtolower($string);
        $string = str_replace(array('-', ' ', '_'), '', $string);
        return $string;
    }

    /**
     * Sanitiza valor do campo baseado no tipo
     */
    private function sanitize_field_value($value, $type) {
        switch ($type) {
            case 'number':
                return is_numeric($value) ? floatval($value) : 0;

            case 'date':
                return sanitize_text_field($value);

            case 'textarea':
                return sanitize_textarea_field($value);

            case 'media':
                // Para mídia, pode ser um ID ou URL
                return is_numeric($value) ? absint($value) : esc_url_raw($value);

            case 'checkbox':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);

            default:
                return sanitize_text_field($value);
        }
    }

    /**
     * Importa em lote (batch) para performance
     */
    public function import_batch($csv_filepath, $post_type_slug, $column_mapping, $offset = 0, $limit = 50) {
        $this->post_type_slug = $post_type_slug;
        $this->column_mapping = $column_mapping;

        // Busca os meta fields do post type
        $post_types = Eau_Post_Type_Creator::get_registered_post_types();

        if (!isset($post_types[$post_type_slug])) {
            return new \WP_Error('invalid_post_type', 'Post Type não encontrado.');
        }

        $this->meta_fields = $post_types[$post_type_slug]['meta_fields'];

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
            $result = $this->import_single_row($row, $actual_row_number);

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
}
