<?php
namespace EauSystem;

/**
 * Classe responsável pela interface administrativa
 */
class Eau_Admin {

    private $plugin_name;
    private $version;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;

        // Handlers AJAX
        add_action('wp_ajax_eau_upload_csv', array($this, 'handle_csv_upload'));
        add_action('wp_ajax_eau_create_post_type', array($this, 'handle_create_post_type'));
        add_action('wp_ajax_eau_delete_post_type', array($this, 'handle_delete_post_type'));
        add_action('wp_ajax_eau_import_analyze_csv', array($this, 'handle_import_analyze_csv'));
        add_action('wp_ajax_eau_import_batch', array($this, 'handle_import_batch'));

        // Handlers AJAX para User Meta Boxes
        add_action('wp_ajax_eau_create_user_meta_box', array($this, 'handle_create_user_meta_box'));
        add_action('wp_ajax_eau_delete_user_meta_box', array($this, 'handle_delete_user_meta_box'));
        add_action('wp_ajax_eau_import_users_analyze_csv', array($this, 'handle_import_users_analyze_csv'));
        add_action('wp_ajax_eau_import_users_batch', array($this, 'handle_import_users_batch'));
    }

    /**
     * Adiciona menu no admin do WordPress
     */
    public function add_admin_menu() {
        add_menu_page(
            'Eau System',
            'Eau System',
            'manage_options',
            'eau-system',
            array($this, 'display_admin_page'),
            'dashicons-database-import',
            30
        );
    }

    /**
     * Enfileira estilos CSS
     */
    public function enqueue_styles($hook) {
        if ('toplevel_page_eau-system' !== $hook) {
            return;
        }

        wp_enqueue_style(
            $this->plugin_name,
            EAU_SYSTEM_PLUGIN_URL . 'assets/css/eau-admin.css',
            array(),
            $this->version,
            'all'
        );

        wp_enqueue_style(
            $this->plugin_name . '-toast',
            EAU_SYSTEM_PLUGIN_URL . 'assets/css/eau-toast.css',
            array(),
            $this->version,
            'all'
        );
    }

    /**
     * Enfileira scripts JavaScript
     */
    public function enqueue_scripts($hook) {
        if ('toplevel_page_eau-system' !== $hook) {
            return;
        }

        // Toast system (carrega primeiro)
        wp_enqueue_script(
            $this->plugin_name . '-toast',
            EAU_SYSTEM_PLUGIN_URL . 'assets/js/eau-toast.js',
            array(),
            $this->version,
            true
        );

        wp_enqueue_script(
            $this->plugin_name,
            EAU_SYSTEM_PLUGIN_URL . 'assets/js/eau-admin.js',
            array('jquery', $this->plugin_name . '-toast'),
            $this->version,
            true
        );

        wp_localize_script($this->plugin_name, 'eauSystem', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('eau_system_nonce'),
            'strings' => array(
                'uploadError' => __('Erro ao fazer upload do arquivo.', 'eau-system'),
                'createError' => __('Erro ao criar o Post Type.', 'eau-system'),
                'selectColumns' => __('Selecione pelo menos uma coluna.', 'eau-system'),
                'postTypeName' => __('Digite o nome do Post Type.', 'eau-system'),
            )
        ));
    }

    /**
     * Exibe a página administrativa
     */
    public function display_admin_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        include EAU_SYSTEM_PLUGIN_DIR . 'includes/admin-page.php';
    }

    /**
     * Handler AJAX para upload de CSV
     */
    public function handle_csv_upload() {
        check_ajax_referer('eau_system_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permissão negada.'));
        }

        if (!isset($_FILES['csv_file'])) {
            wp_send_json_error(array('message' => 'Nenhum arquivo enviado.'));
        }

        $csv_handler = new Eau_CSV_Handler();
        $result = $csv_handler->process_upload($_FILES['csv_file']);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success($result);
    }

    /**
     * Handler AJAX para criação de Post Type
     */
    public function handle_create_post_type() {
        check_ajax_referer('eau_system_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permissão negada.'));
        }

        $post_type_name = sanitize_text_field($_POST['post_type_name'] ?? '');
        $selected_columns = isset($_POST['selected_columns']) ? array_map('sanitize_text_field', $_POST['selected_columns']) : array();
        $meta_key_prefix = sanitize_text_field($_POST['meta_key_prefix'] ?? '');

        if (empty($post_type_name) || empty($selected_columns)) {
            wp_send_json_error(array('message' => 'Dados incompletos.'));
        }

        $post_type_creator = new Eau_Post_Type_Creator();
        $result = $post_type_creator->create_post_type($post_type_name, $selected_columns, $meta_key_prefix);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success($result);
    }

    /**
     * Handler AJAX para exclusão de Post Type
     */
    public function handle_delete_post_type() {
        check_ajax_referer('eau_system_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permissão negada.'));
        }

        $slug = sanitize_text_field($_POST['slug'] ?? '');

        if (empty($slug)) {
            wp_send_json_error(array('message' => 'Slug do Post Type não fornecido.'));
        }

        $post_type_creator = new Eau_Post_Type_Creator();
        $result = $post_type_creator->delete_post_type($slug);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success($result);
    }

    /**
     * Handler AJAX para analisar CSV para importação
     */
    public function handle_import_analyze_csv() {
        check_ajax_referer('eau_system_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permissão negada.'));
        }

        if (!isset($_FILES['import_csv_file'])) {
            wp_send_json_error(array('message' => 'Nenhum arquivo enviado.'));
        }

        $post_type_slug = sanitize_text_field($_POST['post_type_slug'] ?? '');

        if (empty($post_type_slug)) {
            wp_send_json_error(array('message' => 'Post Type não especificado.'));
        }

        $csv_handler = new Eau_CSV_Handler();
        $result = $csv_handler->process_upload($_FILES['import_csv_file']);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        // Adiciona informações do post type
        $result['post_type_slug'] = $post_type_slug;

        wp_send_json_success($result);
    }

    /**
     * Handler AJAX para importação em lote (batch)
     */
    public function handle_import_batch() {
        check_ajax_referer('eau_system_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permissão negada.'));
        }

        $csv_filename = sanitize_text_field($_POST['csv_filename'] ?? '');
        $post_type_slug = sanitize_text_field($_POST['post_type_slug'] ?? '');
        $column_mapping = isset($_POST['column_mapping']) ? json_decode(stripslashes($_POST['column_mapping']), true) : array();
        $conditions = isset($_POST['conditions']) ? json_decode(stripslashes($_POST['conditions']), true) : array();
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $batch_size = isset($_POST['batch_size']) ? intval($_POST['batch_size']) : 25;

        if (empty($csv_filename) || empty($post_type_slug)) {
            wp_send_json_error(array('message' => 'Dados incompletos.'));
        }

        // Monta caminho do arquivo
        $upload_dir = wp_upload_dir();
        $csv_filepath = $upload_dir['basedir'] . '/eau-system-csv/' . $csv_filename;

        if (!file_exists($csv_filepath)) {
            wp_send_json_error(array('message' => 'Arquivo CSV não encontrado.'));
        }

        $importer = new Eau_Importer();
        $result = $importer->import_batch($csv_filepath, $post_type_slug, $column_mapping, $offset, $batch_size, $conditions);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success($result);
    }

    /**
     * Handler AJAX para criar meta box de usuário
     */
    public function handle_create_user_meta_box() {
        check_ajax_referer('eau_system_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permissão negada.'));
        }

        $meta_box_name = sanitize_text_field($_POST['meta_box_name'] ?? '');
        $selected_columns = isset($_POST['selected_columns']) ? array_map('sanitize_text_field', $_POST['selected_columns']) : array();
        $meta_key_prefix = sanitize_text_field($_POST['meta_key_prefix'] ?? '');

        if (empty($meta_box_name) || empty($selected_columns)) {
            wp_send_json_error(array('message' => 'Dados incompletos.'));
        }

        $meta_creator = new Eau_User_Meta_Creator();
        $result = $meta_creator->create_user_meta_box($meta_box_name, $selected_columns, $meta_key_prefix);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success($result);
    }

    /**
     * Handler AJAX para deletar meta box de usuário
     */
    public function handle_delete_user_meta_box() {
        check_ajax_referer('eau_system_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permissão negada.'));
        }

        $slug = sanitize_text_field($_POST['slug'] ?? '');

        if (empty($slug)) {
            wp_send_json_error(array('message' => 'Slug do meta box não fornecido.'));
        }

        $meta_creator = new Eau_User_Meta_Creator();
        $result = $meta_creator->delete_meta_box($slug);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success($result);
    }

    /**
     * Handler AJAX para analisar CSV para importação de usuários
     */
    public function handle_import_users_analyze_csv() {
        check_ajax_referer('eau_system_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permissão negada.'));
        }

        if (!isset($_FILES['import_users_csv_file'])) {
            wp_send_json_error(array('message' => 'Nenhum arquivo enviado.'));
        }

        $meta_box_slug = sanitize_text_field($_POST['meta_box_slug'] ?? '');

        $csv_handler = new Eau_CSV_Handler();
        $result = $csv_handler->process_upload($_FILES['import_users_csv_file']);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        // Adiciona informações do meta box se fornecido
        if (!empty($meta_box_slug)) {
            $result['meta_box_slug'] = $meta_box_slug;
            $result['meta_box_fields'] = Eau_User_Meta_Creator::get_meta_box_fields($meta_box_slug);
        }

        wp_send_json_success($result);
    }

    /**
     * Handler AJAX para importação de usuários em lote
     */
    public function handle_import_users_batch() {
        check_ajax_referer('eau_system_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permissão negada.'));
        }

        $csv_filename = sanitize_text_field($_POST['csv_filename'] ?? '');
        $column_mapping = isset($_POST['column_mapping']) ? json_decode(stripslashes($_POST['column_mapping']), true) : array();
        $conditions = isset($_POST['conditions']) ? json_decode(stripslashes($_POST['conditions']), true) : array();
        $user_role = sanitize_text_field($_POST['user_role'] ?? 'subscriber');
        $send_email = isset($_POST['send_email']) && $_POST['send_email'] === 'true';
        $default_password = sanitize_text_field($_POST['default_password'] ?? '');
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $batch_size = isset($_POST['batch_size']) ? intval($_POST['batch_size']) : 25;

        if (empty($csv_filename)) {
            wp_send_json_error(array('message' => 'Dados incompletos.'));
        }

        // Monta caminho do arquivo
        $upload_dir = wp_upload_dir();
        $csv_filepath = $upload_dir['basedir'] . '/eau-system-csv/' . $csv_filename;

        if (!file_exists($csv_filepath)) {
            wp_send_json_error(array('message' => 'Arquivo CSV não encontrado.'));
        }

        $importer = new Eau_User_Importer();
        $result = $importer->import_batch($csv_filepath, $column_mapping, $offset, $batch_size, $conditions, $user_role, $send_email, $default_password);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success($result);
    }
}
