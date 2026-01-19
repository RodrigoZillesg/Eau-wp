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

        // Handler AJAX para Sincronização de User Types
        add_action('wp_ajax_eau_sync_user_types', array($this, 'handle_sync_user_types'));

        // Handlers AJAX para Membership Import (v1.55.0)
        add_action('wp_ajax_eau_import_membership_analyze_csv', array($this, 'handle_import_membership_analyze_csv'));
        add_action('wp_ajax_eau_import_membership_preview', array($this, 'handle_import_membership_preview'));
        add_action('wp_ajax_eau_import_membership_batch', array($this, 'handle_import_membership_batch'));

        // Handlers AJAX para Institution Import (v1.54.0)
        add_action('wp_ajax_eau_import_institution_analyze_csv', array($this, 'handle_import_institution_analyze_csv'));
        add_action('wp_ajax_eau_import_institution_preview', array($this, 'handle_import_institution_preview'));
        add_action('wp_ajax_eau_import_institution_batch', array($this, 'handle_import_institution_batch'));
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

        // Lucide Icons
        wp_enqueue_script(
            'lucide-icons',
            'https://unpkg.com/lucide@latest/dist/umd/lucide.min.js',
            array(),
            null,
            true
        );

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
            array('jquery', 'lucide-icons', $this->plugin_name . '-toast'),
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
            ),
            'knownMetaFields' => array(
                array('name' => 'mem_status', 'title' => 'Member Status'),
                array('name' => 'mem_membercompanyname', 'title' => 'Member Company Name'),
                array('name' => 'mem_phone', 'title' => 'Phone'),
                array('name' => 'mem_address', 'title' => 'Address'),
                array('name' => 'mem_city', 'title' => 'City'),
                array('name' => 'mem_state', 'title' => 'State'),
                array('name' => 'mem_postcode', 'title' => 'Postcode'),
                array('name' => 'mem_country', 'title' => 'Country'),
                array('name' => 'mem_memberposition', 'title' => 'Member Position'),
                array('name' => 'mem_type', 'title' => 'Member Type'),
                array('name' => 'mem_tags', 'title' => 'Tags'),
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

        // Auto-sync categories se estamos importando atividades
        if ($post_type_slug === 'activitie' && $result['imported'] > 0) {
            $sync_stats = Eau_Categories_Database::sync_categories_from_activities();
            $result['categories_sync'] = $sync_stats;
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
        $import_limit = sanitize_text_field($_POST['import_limit'] ?? 'all');
        $total_imported = isset($_POST['total_imported']) ? intval($_POST['total_imported']) : 0;
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
        $result = $importer->import_batch($csv_filepath, $column_mapping, $offset, $batch_size, $conditions, $user_role, $send_email, $default_password, $import_limit, $total_imported);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success($result);
    }

    /**
     * Handler AJAX para sincronização de user types
     */
    public function handle_sync_user_types() {
        check_ajax_referer('eau_system_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permissão negada.'));
        }

        $syncer = new Eau_User_Type_Sync();
        $result = $syncer->sync_all_user_types();

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success($result);
    }

    /**
     * Verifica permissão para importação de membership
     * Aceita superAdmin e Admin (baseado em mem_type)
     *
     * @since 1.55.0
     * @return bool
     */
    private function can_import_membership() {
        if (!is_user_logged_in()) {
            return false;
        }

        $user_id = get_current_user_id();
        $mem_type = get_user_meta($user_id, 'mem_type', true);

        return in_array($mem_type, array('superAdmin', 'Admin'));
    }

    /**
     * Handler AJAX para análise de CSV de membership
     *
     * @since 1.55.0
     */
    public function handle_import_membership_analyze_csv() {
        // Aceita nonce do settings (frontend) ou do system (admin)
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'eau_settings_nonce') &&
            !wp_verify_nonce($_POST['nonce'] ?? '', 'eau_system_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed.'));
        }

        if (!$this->can_import_membership()) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        if (!isset($_FILES['csv_file']) && !isset($_FILES['membership_csv_file'])) {
            wp_send_json_error(array('message' => 'No file uploaded.'));
        }

        // Aceita ambos os nomes de campo (frontend e admin)
        $file = isset($_FILES['csv_file']) ? $_FILES['csv_file'] : $_FILES['membership_csv_file'];

        $csv_handler = new Eau_CSV_Handler();
        $result = $csv_handler->process_upload($file);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        // Adiciona preview na resposta
        $upload_dir = wp_upload_dir();
        $csv_filepath = $upload_dir['basedir'] . '/eau-system-csv/' . $result['filename'];

        if (file_exists($csv_filepath)) {
            $preview = Eau_Membership_Importer::get_preview($csv_filepath, 10);
            if (!is_wp_error($preview)) {
                $result['preview'] = $preview['preview'];
                $result['existing_count'] = $preview['will_update'];
                $result['new_count'] = $preview['will_create'];
            }
        }

        wp_send_json_success($result);
    }

    /**
     * Handler AJAX para preview de importação de membership
     *
     * @since 1.55.0
     */
    public function handle_import_membership_preview() {
        // Aceita nonce do settings (frontend) ou do system (admin)
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'eau_settings_nonce') &&
            !wp_verify_nonce($_POST['nonce'] ?? '', 'eau_system_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed.'));
        }

        if (!$this->can_import_membership()) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        $csv_filename = sanitize_text_field($_POST['csv_filename'] ?? $_POST['filename'] ?? '');

        if (empty($csv_filename)) {
            wp_send_json_error(array('message' => 'Filename not provided.'));
        }

        $upload_dir = wp_upload_dir();
        $csv_filepath = $upload_dir['basedir'] . '/eau-system-csv/' . $csv_filename;

        if (!file_exists($csv_filepath)) {
            wp_send_json_error(array('message' => 'CSV file not found.'));
        }

        $result = Eau_Membership_Importer::get_preview($csv_filepath, 10);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success($result);
    }

    /**
     * Handler AJAX para importação de membership em lote
     *
     * @since 1.55.0
     */
    public function handle_import_membership_batch() {
        // Aceita nonce do settings (frontend) ou do system (admin)
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'eau_settings_nonce') &&
            !wp_verify_nonce($_POST['nonce'] ?? '', 'eau_system_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed.'));
        }

        if (!$this->can_import_membership()) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        $csv_filename = sanitize_text_field($_POST['csv_filename'] ?? $_POST['filename'] ?? '');
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $batch_size = isset($_POST['limit']) ? intval($_POST['limit']) : (isset($_POST['batch_size']) ? intval($_POST['batch_size']) : 25);

        if (empty($csv_filename)) {
            wp_send_json_error(array('message' => 'Filename not provided.'));
        }

        $upload_dir = wp_upload_dir();
        $csv_filepath = $upload_dir['basedir'] . '/eau-system-csv/' . $csv_filename;

        if (!file_exists($csv_filepath)) {
            wp_send_json_error(array('message' => 'CSV file not found.'));
        }

        $result = Eau_Membership_Importer::import_batch($csv_filepath, $offset, $batch_size);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success($result);
    }

    /**
     * Handler AJAX para análise de CSV de instituições
     *
     * @since 1.54.0
     */
    public function handle_import_institution_analyze_csv() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'eau_settings_nonce') &&
            !wp_verify_nonce($_POST['nonce'] ?? '', 'eau_system_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed.'));
        }

        if (!$this->can_import_membership()) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        if (!isset($_FILES['csv_file']) && !isset($_FILES['institution_csv_file'])) {
            wp_send_json_error(array('message' => 'No file uploaded.'));
        }

        $file = isset($_FILES['csv_file']) ? $_FILES['csv_file'] : $_FILES['institution_csv_file'];

        $csv_handler = new Eau_CSV_Handler();
        $result = $csv_handler->process_upload($file);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        // Adiciona preview na resposta
        $upload_dir = wp_upload_dir();
        $csv_filepath = $upload_dir['basedir'] . '/eau-system-csv/' . $result['filename'];

        if (file_exists($csv_filepath)) {
            $preview = Eau_Institution_Importer::get_preview($csv_filepath, 10);
            if (!is_wp_error($preview)) {
                $result['preview'] = $preview['preview'];
                $result['total_institutions'] = $preview['total_institutions'];
                $result['existing_count'] = $preview['will_update'];
                $result['new_count'] = $preview['will_create'];
            }
        }

        wp_send_json_success($result);
    }

    /**
     * Handler AJAX para preview de importação de instituições
     *
     * @since 1.54.0
     */
    public function handle_import_institution_preview() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'eau_settings_nonce') &&
            !wp_verify_nonce($_POST['nonce'] ?? '', 'eau_system_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed.'));
        }

        if (!$this->can_import_membership()) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        $csv_filename = sanitize_text_field($_POST['csv_filename'] ?? $_POST['filename'] ?? '');

        if (empty($csv_filename)) {
            wp_send_json_error(array('message' => 'Filename not provided.'));
        }

        $upload_dir = wp_upload_dir();
        $csv_filepath = $upload_dir['basedir'] . '/eau-system-csv/' . $csv_filename;

        if (!file_exists($csv_filepath)) {
            wp_send_json_error(array('message' => 'CSV file not found.'));
        }

        $result = Eau_Institution_Importer::get_preview($csv_filepath, 10);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success($result);
    }

    /**
     * Handler AJAX para importação de instituições em lote
     *
     * @since 1.54.0
     */
    public function handle_import_institution_batch() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'eau_settings_nonce') &&
            !wp_verify_nonce($_POST['nonce'] ?? '', 'eau_system_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed.'));
        }

        if (!$this->can_import_membership()) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        $csv_filename = sanitize_text_field($_POST['csv_filename'] ?? $_POST['filename'] ?? '');
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $batch_size = isset($_POST['limit']) ? intval($_POST['limit']) : (isset($_POST['batch_size']) ? intval($_POST['batch_size']) : 25);
        $sync_delete = isset($_POST['sync_delete']) ? filter_var($_POST['sync_delete'], FILTER_VALIDATE_BOOLEAN) : false;

        if (empty($csv_filename)) {
            wp_send_json_error(array('message' => 'Filename not provided.'));
        }

        $upload_dir = wp_upload_dir();
        $csv_filepath = $upload_dir['basedir'] . '/eau-system-csv/' . $csv_filename;

        if (!file_exists($csv_filepath)) {
            wp_send_json_error(array('message' => 'CSV file not found.'));
        }

        $result = Eau_Institution_Importer::import_batch($csv_filepath, $offset, $batch_size, $sync_delete);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success($result);
    }
}
