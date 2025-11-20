<?php
namespace EauSystem;

/**
 * Classe para criar Post Types no formato compatível com JetEngine
 */
class Eau_Post_Type_Creator {

    /**
     * Cria um novo Post Type com os campos selecionados
     */
    public function create_post_type($post_type_name, $selected_columns, $meta_key_prefix = '') {
        global $wpdb;

        // Sanitiza e valida o nome do post type
        $post_type_slug = $this->sanitize_post_type_slug($post_type_name);

        if (empty($post_type_slug)) {
            return new \WP_Error('invalid_post_type', 'Nome do Post Type inválido.');
        }

        // Verifica se o post type já existe na tabela do JetEngine
        if ($this->post_type_exists_in_jet_engine($post_type_slug)) {
            return new \WP_Error('post_type_exists', 'Este Post Type já existe no JetEngine.');
        }

        // Verifica se o post type já está registrado no WordPress
        if (post_type_exists($post_type_slug)) {
            return new \WP_Error('post_type_exists', 'Este Post Type já existe no WordPress.');
        }

        // Sanitiza o prefixo se fornecido
        $meta_key_prefix = $this->sanitize_prefix($meta_key_prefix);

        // Prepara os campos no formato JetEngine
        $meta_fields = $this->prepare_meta_fields($selected_columns, $meta_key_prefix);

        // Prepara dados para salvar na tabela do JetEngine
        $jet_engine_data = array(
            'slug' => $post_type_slug,
            'status' => 'publish',
            'labels' => $this->generate_labels($post_type_name),
            'args' => $this->get_default_post_type_args(),
            'meta_fields' => $meta_fields,
        );

        // Salva diretamente na tabela wp_jet_post_types
        $inserted_id = $this->save_to_jet_engine_table($jet_engine_data);

        if (!$inserted_id) {
            return new \WP_Error('save_failed', 'Falha ao salvar o Post Type no banco de dados.');
        }

        // Também mantém backup na opção do Eau System
        $this->save_eau_system_backup($post_type_slug, $post_type_name, $jet_engine_data);

        // Registra o post type imediatamente
        $this->register_custom_post_type($jet_engine_data);

        // Flush rewrite rules
        flush_rewrite_rules();

        return array(
            'success' => true,
            'id' => $inserted_id,
            'post_type_slug' => $post_type_slug,
            'post_type_name' => $post_type_name,
            'meta_fields' => $meta_fields
        );
    }

    /**
     * Verifica se o post type existe na tabela do JetEngine
     */
    private function post_type_exists_in_jet_engine($slug) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'jet_post_types';

        // Verifica se a tabela existe
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return false;
        }

        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE slug = %s",
            $slug
        ));

        return !empty($exists);
    }

    /**
     * Salva o Post Type na tabela do JetEngine
     */
    private function save_to_jet_engine_table($data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'jet_post_types';

        // Verifica se a tabela existe, se não, cria
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            $this->create_jet_engine_table();
        }

        // Prepara dados serializados
        $insert_data = array(
            'slug' => $data['slug'],
            'status' => $data['status'],
            'labels' => maybe_serialize($data['labels']),
            'args' => maybe_serialize($data['args']),
            'meta_fields' => maybe_serialize($data['meta_fields']),
        );

        // Insere na tabela
        $inserted = $wpdb->insert(
            $table_name,
            $insert_data,
            array('%s', '%s', '%s', '%s', '%s')
        );

        if ($inserted) {
            return $wpdb->insert_id;
        }

        return false;
    }

    /**
     * Cria a tabela do JetEngine se não existir
     */
    private function create_jet_engine_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'jet_post_types';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            slug text,
            status text,
            labels longtext,
            args longtext,
            meta_fields longtext,
            PRIMARY KEY (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Salva backup na opção do Eau System
     */
    private function save_eau_system_backup($slug, $name, $data) {
        $post_types = get_option('eau_system_post_types', array());
        $post_types[$slug] = array(
            'slug' => $slug,
            'name' => $name,
            'labels' => $data['labels'],
            'args' => $data['args'],
            'meta_fields' => $data['meta_fields'],
            'created_at' => current_time('mysql'),
        );
        update_option('eau_system_post_types', $post_types);
    }

    /**
     * Exclui um Post Type
     */
    public function delete_post_type($slug) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'jet_post_types';

        // Remove da tabela do JetEngine
        $deleted = $wpdb->delete(
            $table_name,
            array('slug' => $slug),
            array('%s')
        );

        if ($deleted === false) {
            return new \WP_Error('delete_failed', 'Falha ao excluir o Post Type do banco de dados.');
        }

        // Remove do backup do Eau System
        $post_types = get_option('eau_system_post_types', array());
        if (isset($post_types[$slug])) {
            unset($post_types[$slug]);
            update_option('eau_system_post_types', $post_types);
        }

        // Flush rewrite rules
        flush_rewrite_rules();

        return array(
            'success' => true,
            'message' => 'Post Type excluído com sucesso.'
        );
    }

    /**
     * Sanitiza o slug do post type
     */
    private function sanitize_post_type_slug($name) {
        $slug = sanitize_title($name);
        $slug = str_replace('-', '_', $slug);

        // Remove caracteres inválidos
        $slug = preg_replace('/[^a-z0-9_]/', '', $slug);

        // Garante que não ultrapasse 20 caracteres
        $slug = substr($slug, 0, 20);

        return $slug;
    }

    /**
     * Sanitiza o prefixo dos meta keys
     */
    private function sanitize_prefix($prefix) {
        if (empty($prefix)) {
            return '';
        }

        // Converte para minúsculas
        $prefix = strtolower($prefix);

        // Remove caracteres inválidos (mantém apenas letras, números e underscore)
        $prefix = preg_replace('/[^a-z0-9_]/', '', $prefix);

        // Remove underscores do início e fim
        $prefix = trim($prefix, '_');

        // Garante que não ultrapasse 20 caracteres
        $prefix = substr($prefix, 0, 20);

        return $prefix;
    }

    /**
     * Prepara os meta fields no formato JetEngine
     */
    private function prepare_meta_fields($columns, $prefix = '') {
        $meta_fields = array();

        foreach ($columns as $column) {
            $field_name = $this->sanitize_field_name($column, $prefix);
            $field_type = $this->detect_field_type($column);

            $meta_fields[] = array(
                'name' => $field_name,
                'title' => $column,
                'type' => $field_type,
                'object_type' => 'field',
                'width' => '100%',
                'is_required' => false,
                'is_multiple' => false,
                'default_value' => '',
                'conditional_logic' => array(),
            );
        }

        return $meta_fields;
    }

    /**
     * Sanitiza o nome do campo
     */
    private function sanitize_field_name($name, $prefix = '') {
        $field_name = sanitize_title($name);
        $field_name = str_replace('-', '_', $field_name);
        $field_name = preg_replace('/[^a-z0-9_]/', '', $field_name);

        // Adiciona prefixo se fornecido
        if (!empty($prefix)) {
            $field_name = $prefix . '_' . $field_name;
        }

        return $field_name;
    }

    /**
     * Detecta o tipo de campo baseado no nome da coluna
     */
    private function detect_field_type($column_name) {
        $column_lower = strtolower($column_name);

        // Detecção inteligente de tipos
        if (strpos($column_lower, 'email') !== false) {
            return 'text';
        } elseif (strpos($column_lower, 'url') !== false || strpos($column_lower, 'link') !== false) {
            return 'text';
        } elseif (strpos($column_lower, 'phone') !== false || strpos($column_lower, 'telefone') !== false) {
            return 'text';
        } elseif (strpos($column_lower, 'date') !== false || strpos($column_lower, 'data') !== false) {
            return 'date';
        } elseif (strpos($column_lower, 'price') !== false || strpos($column_lower, 'preco') !== false || strpos($column_lower, 'valor') !== false) {
            return 'number';
        } elseif (strpos($column_lower, 'image') !== false || strpos($column_lower, 'imagem') !== false || strpos($column_lower, 'foto') !== false) {
            return 'media';
        } elseif (strpos($column_lower, 'description') !== false || strpos($column_lower, 'descricao') !== false) {
            return 'textarea';
        } else {
            return 'text';
        }
    }

    /**
     * Gera labels para o post type
     */
    private function generate_labels($name) {
        $plural = $name . 's';

        return array(
            'name' => $plural,
            'singular_name' => $name,
            'add_new' => 'Adicionar Novo',
            'add_new_item' => 'Adicionar Novo ' . $name,
            'edit_item' => 'Editar ' . $name,
            'new_item' => 'Novo ' . $name,
            'view_item' => 'Ver ' . $name,
            'search_items' => 'Buscar ' . $plural,
            'not_found' => 'Nenhum ' . $name . ' encontrado',
            'not_found_in_trash' => 'Nenhum ' . $name . ' encontrado na lixeira',
            'parent_item_colon' => '',
            'all_items' => 'Todos ' . $plural,
            'archives' => 'Arquivos de ' . $plural,
            'menu_name' => $plural,
        );
    }

    /**
     * Retorna argumentos padrão para o post type
     */
    private function get_default_post_type_args() {
        return array(
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_rest' => true,
            'query_var' => true,
            'capability_type' => 'post',
            'has_archive' => true,
            'hierarchical' => false,
            'menu_position' => 5,
            'menu_icon' => 'dashicons-admin-post',
            'supports' => array('title', 'editor', 'thumbnail', 'custom-fields'),
            'rewrite' => array('with_front' => false),
        );
    }

    /**
     * Registra o post type customizado
     */
    private function register_custom_post_type($config) {
        $args = array_merge($config['args'], array(
            'labels' => $config['labels']
        ));

        register_post_type($config['slug'], $args);

        // Registra os meta fields
        foreach ($config['meta_fields'] as $field) {
            register_post_meta($config['slug'], $field['name'], array(
                'type' => $this->get_meta_type($field['type']),
                'single' => true,
                'show_in_rest' => true,
            ));
        }
    }

    /**
     * Converte tipo de campo JetEngine para tipo de meta
     */
    private function get_meta_type($field_type) {
        $type_map = array(
            'text' => 'string',
            'textarea' => 'string',
            'number' => 'number',
            'date' => 'string',
            'media' => 'integer',
            'checkbox' => 'boolean',
        );

        return isset($type_map[$field_type]) ? $type_map[$field_type] : 'string';
    }

    /**
     * Obtém todos os post types criados pelo plugin da tabela JetEngine
     */
    public static function get_registered_post_types() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'jet_post_types';

        // Verifica se a tabela existe
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return array();
        }

        // Busca apenas os post types criados pelo Eau System
        $eau_post_types = get_option('eau_system_post_types', array());
        $eau_slugs = array_keys($eau_post_types);

        if (empty($eau_slugs)) {
            return array();
        }

        $slugs_placeholder = implode("','", array_map('esc_sql', $eau_slugs));
        $results = $wpdb->get_results(
            "SELECT * FROM $table_name WHERE slug IN ('$slugs_placeholder') AND status = 'publish'",
            ARRAY_A
        );

        $post_types = array();
        foreach ($results as $row) {
            $post_types[$row['slug']] = array(
                'id' => $row['id'],
                'slug' => $row['slug'],
                'name' => isset($eau_post_types[$row['slug']]['name']) ? $eau_post_types[$row['slug']]['name'] : $row['slug'],
                'status' => $row['status'],
                'labels' => maybe_unserialize($row['labels']),
                'args' => maybe_unserialize($row['args']),
                'meta_fields' => maybe_unserialize($row['meta_fields']),
                'created_at' => isset($eau_post_types[$row['slug']]['created_at']) ? $eau_post_types[$row['slug']]['created_at'] : '',
            );
        }

        return $post_types;
    }

    /**
     * Registra todos os post types salvos
     */
    public static function register_all_post_types() {
        $post_types = self::get_registered_post_types();

        foreach ($post_types as $config) {
            $creator = new self();
            $creator->register_custom_post_type($config);
        }
    }
}

// Hook para registrar os post types no init
add_action('init', array('EauSystem\Eau_Post_Type_Creator', 'register_all_post_types'), 10);
