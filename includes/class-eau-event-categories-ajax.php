<?php
namespace EauSystem;

/**
 * AJAX handlers para Event Categories Management
 *
 * @since 1.61.0
 */
class Eau_Event_Categories_Ajax {

    /**
     * Registra os handlers AJAX
     */
    public static function register_ajax_handlers() {
        // Get event categories list
        add_action('wp_ajax_eau_get_event_categories', array(__CLASS__, 'get_categories'));

        // Get single event category
        add_action('wp_ajax_eau_get_event_category', array(__CLASS__, 'get_category'));

        // Save event category (create or update)
        add_action('wp_ajax_eau_save_event_category', array(__CLASS__, 'save_category'));

        // Delete event category
        add_action('wp_ajax_eau_delete_event_category', array(__CLASS__, 'delete_category'));

        // Sync event categories from events
        add_action('wp_ajax_eau_sync_event_categories', array(__CLASS__, 'sync_categories'));

        // Generate unique event category serial
        add_action('wp_ajax_eau_generate_event_category_serial', array(__CLASS__, 'generate_category_serial'));

        // Get stats
        add_action('wp_ajax_eau_get_event_categories_stats', array(__CLASS__, 'get_categories_stats'));
    }

    /**
     * Get event categories list com paginação, busca e ordenação
     */
    public static function get_categories() {
        check_ajax_referer('eau_event_categories_nonce', 'nonce');

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

        $result = Eau_Event_Categories_Database::get_categories($args);

        // Formata os dados para exibição
        $categories = array_map(function($category) {
            return array(
                'id' => $category['id'],
                'category_serial' => $category['category_serial'],
                'category_name' => $category['category_name'],
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
     * Get single event category
     */
    public static function get_category() {
        check_ajax_referer('eau_event_categories_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permissão negada.'), 403);
        }

        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

        if (!$id) {
            wp_send_json_error(array('message' => 'ID inválido.'), 400);
        }

        $category = Eau_Event_Categories_Database::get_category($id);

        if (!$category) {
            wp_send_json_error(array('message' => 'Categoria não encontrada.'), 404);
        }

        wp_send_json_success($category);
    }

    /**
     * Save event category (create or update)
     */
    public static function save_category() {
        check_ajax_referer('eau_event_categories_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permissão negada.'), 403);
        }

        // Validação
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $category_serial = isset($_POST['category_serial']) ? sanitize_text_field($_POST['category_serial']) : '';
        $category_name = isset($_POST['category_name']) ? sanitize_text_field($_POST['category_name']) : '';

        if (empty($category_serial)) {
            wp_send_json_error(array('message' => 'Category ID é obrigatório.'), 400);
        }

        if (empty($category_name)) {
            wp_send_json_error(array('message' => 'Nome da categoria é obrigatório.'), 400);
        }

        $data = array(
            'category_serial' => $category_serial,
            'category_name' => $category_name,
        );

        if ($id > 0) {
            $data['id'] = $id;
        }

        $result = Eau_Event_Categories_Database::save_category($data);

        if ($result === false) {
            wp_send_json_error(array('message' => 'Erro ao salvar categoria.'), 500);
        }

        wp_send_json_success(array(
            'message' => $id > 0 ? 'Categoria atualizada com sucesso.' : 'Categoria criada com sucesso.',
            'id' => $result,
        ));
    }

    /**
     * Delete event category
     */
    public static function delete_category() {
        check_ajax_referer('eau_event_categories_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permissão negada.'), 403);
        }

        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

        if (!$id) {
            wp_send_json_error(array('message' => 'ID inválido.'), 400);
        }

        $result = Eau_Event_Categories_Database::delete_category($id);

        if (!$result) {
            wp_send_json_error(array('message' => 'Erro ao deletar categoria.'), 500);
        }

        wp_send_json_success(array('message' => 'Categoria deletada com sucesso.'));
    }

    /**
     * Sync event categories from events
     */
    public static function sync_categories() {
        check_ajax_referer('eau_event_categories_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permissão negada.'), 403);
        }

        $stats = Eau_Event_Categories_Database::sync_categories_from_events();

        wp_send_json_success(array(
            'message' => 'Sincronização concluída.',
            'total_found' => $stats['total_found'],
            'added' => $stats['added'],
            'skipped' => $stats['skipped'],
        ));
    }

    /**
     * Generate unique event category serial
     */
    public static function generate_category_serial() {
        check_ajax_referer('eau_event_categories_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permissão negada.'), 403);
        }

        global $wpdb;
        $table_name = $wpdb->prefix . Eau_Event_Categories_Database::TABLE_NAME;

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
     * Get event categories statistics
     */
    public static function get_categories_stats() {
        check_ajax_referer('eau_event_categories_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permissão negada.'), 403);
        }

        global $wpdb;
        $table_name = $wpdb->prefix . Eau_Event_Categories_Database::TABLE_NAME;

        // Total de categorias
        $total = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");

        wp_send_json_success(array(
            'total' => (int) $total,
        ));
    }
}
