<?php
namespace EauSystem;

/**
 * AJAX handlers para Categories Management
 */
class Eau_Categories_Ajax {

    /**
     * Registra os handlers AJAX
     */
    public static function register_ajax_handlers() {
        // Get categories list
        add_action('wp_ajax_eau_get_categories', array(__CLASS__, 'get_categories'));

        // Get single category
        add_action('wp_ajax_eau_get_category', array(__CLASS__, 'get_category'));

        // Save category (create or update)
        add_action('wp_ajax_eau_save_category', array(__CLASS__, 'save_category'));

        // Delete category
        add_action('wp_ajax_eau_delete_category', array(__CLASS__, 'delete_category'));

        // Sync categories from activities
        add_action('wp_ajax_eau_sync_categories', array(__CLASS__, 'sync_categories'));

        // Generate unique category serial
        add_action('wp_ajax_eau_generate_category_serial', array(__CLASS__, 'generate_category_serial'));

        // Get stats
        add_action('wp_ajax_eau_get_categories_stats', array(__CLASS__, 'get_categories_stats'));

        // Export categories (v1.55.5)
        add_action('wp_ajax_eau_export_categories', array(__CLASS__, 'export_categories'));

        // Import categories - analyze (v1.55.5)
        add_action('wp_ajax_eau_import_categories_analyze', array(__CLASS__, 'import_categories_analyze'));

        // Import categories - execute (v1.55.5)
        add_action('wp_ajax_eau_import_categories_execute', array(__CLASS__, 'import_categories_execute'));
    }

    /**
     * Get categories list com paginação, busca e ordenação
     */
    public static function get_categories() {
        check_ajax_referer('eau_categories_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permissão negada.'), 403);
        }

        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $per_page = isset($_POST['per_page']) ? intval($_POST['per_page']) : 20;
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $orderby = isset($_POST['orderby']) ? sanitize_text_field($_POST['orderby']) : 'category_name';
        $order = isset($_POST['order']) ? sanitize_text_field($_POST['order']) : 'ASC';

        $args = array(
            'page' => $page,
            'per_page' => $per_page,
            'search' => $search,
            'orderby' => $orderby,
            'order' => $order,
        );

        $result = Eau_Categories_Database::get_categories($args);

        // Formata os dados para exibição
        $categories = array_map(function($category) {
            return array(
                'id' => $category['id'],
                'category_serial' => $category['category_serial'],
                'category_name' => $category['category_name'],
                'points_per_hour' => number_format_i18n((float) $category['points_per_hour'], 2),
                'points_per_hour_raw' => (float) $category['points_per_hour'],
                'updated_at' => mysql2date('d/m/Y H:i', $category['updated_at']),
                'updated_at_raw' => $category['updated_at'],
            );
        }, $result['categories']);

        wp_send_json_success(array(
            'categories' => $categories,
            'pagination' => array(
                'total' => $result['total'],
                'page' => $result['page'],
                'per_page' => $result['per_page'],
                'total_pages' => $result['total_pages'],
            ),
        ));
    }

    /**
     * Get single category
     */
    public static function get_category() {
        check_ajax_referer('eau_categories_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permissão negada.'), 403);
        }

        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

        if (!$id) {
            wp_send_json_error(array('message' => 'ID inválido.'), 400);
        }

        $category = Eau_Categories_Database::get_category($id);

        if (!$category) {
            wp_send_json_error(array('message' => 'Categoria não encontrada.'), 404);
        }

        wp_send_json_success($category);
    }

    /**
     * Save category (create or update)
     */
    public static function save_category() {
        check_ajax_referer('eau_categories_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permissão negada.'), 403);
        }

        // Validação
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $category_serial = isset($_POST['category_serial']) ? sanitize_text_field($_POST['category_serial']) : '';
        $category_name = isset($_POST['category_name']) ? sanitize_text_field($_POST['category_name']) : '';
        $points_per_hour = isset($_POST['points_per_hour']) ? floatval($_POST['points_per_hour']) : 0;

        if (empty($category_serial)) {
            wp_send_json_error(array('message' => 'Category ID é obrigatório.'), 400);
        }

        if (empty($category_name)) {
            wp_send_json_error(array('message' => 'Nome da categoria é obrigatório.'), 400);
        }

        if ($points_per_hour < 0) {
            wp_send_json_error(array('message' => 'Pontos por hora não pode ser negativo.'), 400);
        }

        $data = array(
            'category_serial' => $category_serial,
            'category_name' => $category_name,
            'points_per_hour' => $points_per_hour,
        );

        if ($id > 0) {
            $data['id'] = $id;
        }

        $result = Eau_Categories_Database::save_category($data);

        if ($result === false) {
            wp_send_json_error(array('message' => 'Erro ao salvar categoria.'), 500);
        }

        wp_send_json_success(array(
            'message' => $id > 0 ? 'Categoria atualizada com sucesso.' : 'Categoria criada com sucesso.',
            'id' => $result,
        ));
    }

    /**
     * Delete category
     */
    public static function delete_category() {
        check_ajax_referer('eau_categories_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permissão negada.'), 403);
        }

        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

        if (!$id) {
            wp_send_json_error(array('message' => 'ID inválido.'), 400);
        }

        $result = Eau_Categories_Database::delete_category($id);

        if (!$result) {
            wp_send_json_error(array('message' => 'Erro ao deletar categoria.'), 500);
        }

        wp_send_json_success(array('message' => 'Categoria deletada com sucesso.'));
    }

    /**
     * Sync categories from activities
     */
    public static function sync_categories() {
        check_ajax_referer('eau_categories_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permissão negada.'), 403);
        }

        $stats = Eau_Categories_Database::sync_categories_from_activities();

        wp_send_json_success(array(
            'message' => 'Sincronização concluída.',
            'total_found' => $stats['total_found'],
            'added' => $stats['added'],
            'skipped' => $stats['skipped'],
        ));
    }

    /**
     * Generate unique category serial
     */
    public static function generate_category_serial() {
        check_ajax_referer('eau_categories_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permissão negada.'), 403);
        }

        global $wpdb;
        $table_name = $wpdb->prefix . Eau_Categories_Database::TABLE_NAME;

        // Busca o maior ID numérico existente
        $max_serial = $wpdb->get_var("
            SELECT MAX(CAST(category_serial AS UNSIGNED))
            FROM $table_name
            WHERE category_serial REGEXP '^[0-9]+$'
        ");

        // Se não houver nenhum, começa do 1, senão incrementa
        $next_serial = $max_serial ? (int)$max_serial + 1 : 1;

        // Formata com padding de zeros (ex: 001, 002, etc)
        $category_serial = str_pad($next_serial, 3, '0', STR_PAD_LEFT);

        wp_send_json_success(array(
            'category_serial' => $category_serial,
        ));
    }

    /**
     * Get categories statistics
     */
    public static function get_categories_stats() {
        check_ajax_referer('eau_categories_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permissão negada.'), 403);
        }

        global $wpdb;
        $table_name = $wpdb->prefix . Eau_Categories_Database::TABLE_NAME;

        // Total de categorias
        $total = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");

        // Categorias com pontos configurados
        $configured = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE points_per_hour > 0");

        // Categorias sem pontos
        $not_configured = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE points_per_hour = 0");

        // Média de pontos por hora
        $avg_points = $wpdb->get_var("SELECT AVG(points_per_hour) FROM $table_name WHERE points_per_hour > 0");

        wp_send_json_success(array(
            'total' => (int) $total,
            'configured' => (int) $configured,
            'not_configured' => (int) $not_configured,
            'avg_points' => $avg_points ? number_format_i18n((float) $avg_points, 2) : '0.00',
        ));
    }

    /**
     * Export categories to JSON
     *
     * @since 1.55.5
     */
    public static function export_categories() {
        check_ajax_referer('eau_categories_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permissão negada.'), 403);
        }

        $data = Eau_Categories_Export_Import::export_categories();

        wp_send_json_success($data);
    }

    /**
     * Analyze import file and show preview
     *
     * @since 1.55.5
     */
    public static function import_categories_analyze() {
        check_ajax_referer('eau_categories_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permissão negada.'), 403);
        }

        if (!isset($_FILES['json_file'])) {
            wp_send_json_error(array('message' => 'Nenhum arquivo enviado.'), 400);
        }

        // Parse uploaded file
        $data = Eau_Categories_Export_Import::parse_uploaded_file($_FILES['json_file']);

        if (is_wp_error($data)) {
            wp_send_json_error(array('message' => $data->get_error_message()), 400);
        }

        // Validate structure
        $validation = Eau_Categories_Export_Import::validate_import_data($data);

        if (is_wp_error($validation)) {
            wp_send_json_error(array('message' => $validation->get_error_message()), 400);
        }

        // Store temp file for later import
        $temp_filename = Eau_Categories_Export_Import::store_temp_file($_FILES['json_file']);

        if (is_wp_error($temp_filename)) {
            wp_send_json_error(array('message' => $temp_filename->get_error_message()), 500);
        }

        // Get preview
        $preview = Eau_Categories_Export_Import::get_import_preview($data, 10);

        wp_send_json_success(array(
            'filename' => $temp_filename,
            'total_categories' => $preview['total_categories'],
            'will_create' => $preview['will_create'],
            'will_update' => $preview['will_update'],
            'preview' => $preview['preview'],
            'export_date' => isset($data['export_date']) ? $data['export_date'] : null,
            'plugin_version' => isset($data['plugin_version']) ? $data['plugin_version'] : null,
        ));
    }

    /**
     * Execute import
     *
     * @since 1.55.5
     */
    public static function import_categories_execute() {
        check_ajax_referer('eau_categories_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permissão negada.'), 403);
        }

        $filename = isset($_POST['filename']) ? sanitize_file_name($_POST['filename']) : '';
        $skip_existing = isset($_POST['skip_existing']) ? filter_var($_POST['skip_existing'], FILTER_VALIDATE_BOOLEAN) : false;

        if (empty($filename)) {
            wp_send_json_error(array('message' => 'Arquivo não especificado.'), 400);
        }

        // Get data from temp file
        $data = Eau_Categories_Export_Import::get_temp_file_data($filename);

        if (is_wp_error($data)) {
            wp_send_json_error(array('message' => $data->get_error_message()), 400);
        }

        // Execute import
        $results = Eau_Categories_Export_Import::import_categories($data, $skip_existing);

        // Clean up temp file
        Eau_Categories_Export_Import::delete_temp_file($filename);

        wp_send_json_success(array(
            'message' => 'Importação concluída.',
            'total' => $results['total'],
            'created' => $results['created'],
            'updated' => $results['updated'],
            'skipped' => $results['skipped'],
            'errors' => $results['errors'],
        ));
    }
}
