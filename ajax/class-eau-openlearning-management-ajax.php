<?php
namespace EauSystem\Ajax;

use EauSystem\Eau_OpenLearning_Service;
use EauSystem\Eau_OpenLearning_Post_Type;
use EauSystem\Eau_User_Institution_Helper;

/**
 * Handlers AJAX para OpenLearning Management (página de configuração de cursos)
 *
 * @since 1.43.0
 */
class Eau_OpenLearning_Management_Ajax {

    /**
     * Registra os handlers AJAX
     */
    public static function register_handlers() {
        // Get courses para listagem (com paginação, filtros, ordenação)
        add_action('wp_ajax_eau_openlearning_mgmt_get_courses', array(__CLASS__, 'handle_get_courses'));

        // Get course details
        add_action('wp_ajax_eau_openlearning_mgmt_get_course', array(__CLASS__, 'handle_get_course'));

        // Update course settings (visibility, featured, order)
        add_action('wp_ajax_eau_openlearning_mgmt_update_course', array(__CLASS__, 'handle_update_course'));

        // Bulk update visibility
        add_action('wp_ajax_eau_openlearning_mgmt_bulk_visibility', array(__CLASS__, 'handle_bulk_visibility'));

        // Bulk update featured (v1.72.5)
        add_action('wp_ajax_eau_openlearning_mgmt_bulk_featured', array(__CLASS__, 'handle_bulk_featured'));

        // Get stats for the management page
        add_action('wp_ajax_eau_openlearning_mgmt_get_stats', array(__CLASS__, 'handle_get_stats'));

        // Sync courses from OpenLearning API
        add_action('wp_ajax_eau_openlearning_mgmt_sync', array(__CLASS__, 'handle_sync'));
    }

    /**
     * Retorna lista de cursos com paginação, filtros e ordenação
     */
    public static function handle_get_courses() {
        // Verifica nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'eau_openlearning_management_nonce')) {
            wp_send_json_error(array('message' => 'Invalid security token'));
        }

        // Verifica permissão (apenas superAdmin e Admin)
        if (!Eau_User_Institution_Helper::has_admin_access()) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        // Parâmetros
        $page = isset($_POST['page']) ? absint($_POST['page']) : 1;
        $per_page = isset($_POST['per_page']) ? absint($_POST['per_page']) : 20;
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $orderby = isset($_POST['orderby']) ? sanitize_text_field($_POST['orderby']) : 'title';
        $order = isset($_POST['order']) ? strtoupper(sanitize_text_field($_POST['order'])) : 'ASC';

        // Filtros
        $visibility = isset($_POST['visibility']) ? sanitize_text_field($_POST['visibility']) : '';
        $featured = isset($_POST['featured']) ? sanitize_text_field($_POST['featured']) : '';
        $price_filter = isset($_POST['price']) ? sanitize_text_field($_POST['price']) : '';

        // Garante que order seja válido
        $order = in_array($order, array('ASC', 'DESC')) ? $order : 'ASC';

        // Calcula offset
        $offset = ($page - 1) * $per_page;

        // Monta query
        $post_type = Eau_OpenLearning_Post_Type::POST_TYPE;
        $meta_prefix = Eau_OpenLearning_Post_Type::META_PREFIX;

        $args = array(
            'post_type' => $post_type,
            'post_status' => 'publish',
            'posts_per_page' => $per_page,
            'offset' => $offset,
            's' => $search,
        );

        // Meta query para filtros
        $meta_query = array('relation' => 'AND');

        // Filtro de visibilidade
        if ($visibility === 'visible') {
            $meta_query[] = array(
                'relation' => 'OR',
                array(
                    'key' => $meta_prefix . 'is_visible',
                    'value' => 'true',
                    'compare' => '=',
                ),
                array(
                    'key' => $meta_prefix . 'is_visible',
                    'value' => '1',
                    'compare' => '=',
                ),
            );
        } elseif ($visibility === 'hidden') {
            $meta_query[] = array(
                'relation' => 'OR',
                array(
                    'key' => $meta_prefix . 'is_visible',
                    'value' => '',
                    'compare' => '=',
                ),
                array(
                    'key' => $meta_prefix . 'is_visible',
                    'value' => 'false',
                    'compare' => '=',
                ),
                array(
                    'key' => $meta_prefix . 'is_visible',
                    'compare' => 'NOT EXISTS',
                ),
            );
        }

        // Filtro de destaque
        if ($featured === 'featured') {
            $meta_query[] = array(
                'relation' => 'OR',
                array(
                    'key' => $meta_prefix . 'is_featured',
                    'value' => 'true',
                    'compare' => '=',
                ),
                array(
                    'key' => $meta_prefix . 'is_featured',
                    'value' => '1',
                    'compare' => '=',
                ),
            );
        } elseif ($featured === 'not_featured') {
            $meta_query[] = array(
                'relation' => 'OR',
                array(
                    'key' => $meta_prefix . 'is_featured',
                    'value' => '',
                    'compare' => '=',
                ),
                array(
                    'key' => $meta_prefix . 'is_featured',
                    'value' => 'false',
                    'compare' => '=',
                ),
                array(
                    'key' => $meta_prefix . 'is_featured',
                    'compare' => 'NOT EXISTS',
                ),
            );
        }

        // Filtro de preço
        if ($price_filter === 'free') {
            $meta_query[] = array(
                'relation' => 'OR',
                array(
                    'key' => $meta_prefix . 'price',
                    'value' => '0',
                    'compare' => '=',
                ),
                array(
                    'key' => $meta_prefix . 'price',
                    'value' => '',
                    'compare' => '=',
                ),
                array(
                    'key' => $meta_prefix . 'price',
                    'compare' => 'NOT EXISTS',
                ),
            );
        } elseif ($price_filter === 'paid') {
            $meta_query[] = array(
                'key' => $meta_prefix . 'price',
                'value' => '0',
                'compare' => '>',
                'type' => 'NUMERIC',
            );
        }

        if (count($meta_query) > 1) {
            $args['meta_query'] = $meta_query;
        }

        // Ordenação
        $orderby_map = array(
            'course' => 'title',
            'title' => 'title',
            'price' => $meta_prefix . 'price',
            'visibility' => $meta_prefix . 'is_visible',
            'featured' => $meta_prefix . 'is_featured',
            'order' => $meta_prefix . 'display_order',
            'synced' => $meta_prefix . 'last_synced',
        );

        $actual_orderby = isset($orderby_map[$orderby]) ? $orderby_map[$orderby] : 'title';

        if ($actual_orderby === 'title') {
            $args['orderby'] = 'title';
            $args['order'] = $order;
        } else {
            $args['meta_key'] = $actual_orderby;
            $args['orderby'] = 'meta_value';
            $args['order'] = $order;

            // Para campos numéricos
            if (in_array($actual_orderby, array($meta_prefix . 'price', $meta_prefix . 'display_order'))) {
                $args['orderby'] = 'meta_value_num';
            }
        }

        $query = new \WP_Query($args);

        // Formata os dados
        $rows = array();
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $rows[] = self::format_course_row(get_the_ID());
            }
            wp_reset_postdata();
        }

        // Total para paginação
        $args_count = $args;
        $args_count['posts_per_page'] = -1;
        $args_count['fields'] = 'ids';
        unset($args_count['offset']);
        $count_query = new \WP_Query($args_count);
        $total = $count_query->found_posts;

        wp_send_json_success(array(
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil($total / $per_page),
        ));
    }

    /**
     * Formata dados de um curso para a tabela
     */
    private static function format_course_row($post_id) {
        $meta_prefix = Eau_OpenLearning_Post_Type::META_PREFIX;

        $is_visible = get_post_meta($post_id, $meta_prefix . 'is_visible', true);
        $is_featured = get_post_meta($post_id, $meta_prefix . 'is_featured', true);
        $price = get_post_meta($post_id, $meta_prefix . 'price', true);
        $display_order = get_post_meta($post_id, $meta_prefix . 'display_order', true);
        $last_synced = get_post_meta($post_id, $meta_prefix . 'last_synced', true);
        $image_url = get_post_meta($post_id, $meta_prefix . 'image_url', true);

        // Normaliza valores booleanos do JetEngine switcher
        $is_visible_bool = ($is_visible === 'true' || $is_visible === '1');
        $is_featured_bool = ($is_featured === 'true' || $is_featured === '1');

        return array(
            'ID' => $post_id,
            'title' => get_the_title($post_id),
            'image_url' => $image_url,
            'price' => floatval($price),
            'is_visible' => $is_visible_bool,
            'is_featured' => $is_featured_bool,
            'display_order' => intval($display_order),
            'last_synced' => $last_synced,
            'course_id' => get_post_meta($post_id, $meta_prefix . 'course_id', true),
        );
    }

    /**
     * Retorna detalhes de um curso específico
     */
    public static function handle_get_course() {
        // Verifica nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'eau_openlearning_management_nonce')) {
            wp_send_json_error(array('message' => 'Invalid security token'));
        }

        // Verifica permissão
        if (!Eau_User_Institution_Helper::has_admin_access()) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $course_id = isset($_POST['course_id']) ? absint($_POST['course_id']) : 0;

        if (!$course_id) {
            wp_send_json_error(array('message' => 'Invalid course ID'));
        }

        $post = get_post($course_id);
        if (!$post || $post->post_type !== Eau_OpenLearning_Post_Type::POST_TYPE) {
            wp_send_json_error(array('message' => 'Course not found'));
        }

        $meta_prefix = Eau_OpenLearning_Post_Type::META_PREFIX;

        $is_visible = get_post_meta($course_id, $meta_prefix . 'is_visible', true);
        $is_featured = get_post_meta($course_id, $meta_prefix . 'is_featured', true);

        $course = array(
            'ID' => $post->ID,
            'title' => $post->post_title,
            'description' => $post->post_content,
            'image_url' => get_post_meta($course_id, $meta_prefix . 'image_url', true),
            'course_url' => get_post_meta($course_id, $meta_prefix . 'course_url', true),
            'course_id' => get_post_meta($course_id, $meta_prefix . 'course_id', true),
            'price' => floatval(get_post_meta($course_id, $meta_prefix . 'price', true)),
            'self_paced' => get_post_meta($course_id, $meta_prefix . 'self_paced', true) === 'true',
            'is_visible' => ($is_visible === 'true' || $is_visible === '1'),
            'is_featured' => ($is_featured === 'true' || $is_featured === '1'),
            'display_order' => intval(get_post_meta($course_id, $meta_prefix . 'display_order', true)),
            'last_synced' => get_post_meta($course_id, $meta_prefix . 'last_synced', true),
        );

        wp_send_json_success($course);
    }

    /**
     * Atualiza configurações de um curso (visibility, featured, order)
     */
    public static function handle_update_course() {
        // Verifica nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'eau_openlearning_management_nonce')) {
            wp_send_json_error(array('message' => 'Invalid security token'));
        }

        // Verifica permissão
        if (!Eau_User_Institution_Helper::has_admin_access()) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $course_id = isset($_POST['course_id']) ? absint($_POST['course_id']) : 0;

        if (!$course_id) {
            wp_send_json_error(array('message' => 'Invalid course ID'));
        }

        $post = get_post($course_id);
        if (!$post || $post->post_type !== Eau_OpenLearning_Post_Type::POST_TYPE) {
            wp_send_json_error(array('message' => 'Course not found'));
        }

        $meta_prefix = Eau_OpenLearning_Post_Type::META_PREFIX;

        // Atualiza campos permitidos
        $updated = array();

        // Visibility
        if (isset($_POST['is_visible'])) {
            $is_visible = filter_var($_POST['is_visible'], FILTER_VALIDATE_BOOLEAN);
            update_post_meta($course_id, $meta_prefix . 'is_visible', $is_visible ? 'true' : '');
            $updated['is_visible'] = $is_visible;
        }

        // Featured
        if (isset($_POST['is_featured'])) {
            $is_featured = filter_var($_POST['is_featured'], FILTER_VALIDATE_BOOLEAN);
            update_post_meta($course_id, $meta_prefix . 'is_featured', $is_featured ? 'true' : '');
            $updated['is_featured'] = $is_featured;
        }

        // Display Order
        if (isset($_POST['display_order'])) {
            $display_order = absint($_POST['display_order']);
            update_post_meta($course_id, $meta_prefix . 'display_order', $display_order);
            $updated['display_order'] = $display_order;
        }

        wp_send_json_success(array(
            'message' => 'Course updated successfully',
            'updated' => $updated,
        ));
    }

    /**
     * Atualiza visibilidade em massa
     */
    public static function handle_bulk_visibility() {
        // Verifica nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'eau_openlearning_management_nonce')) {
            wp_send_json_error(array('message' => 'Invalid security token'));
        }

        // Verifica permissão
        if (!Eau_User_Institution_Helper::has_admin_access()) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $course_ids = isset($_POST['course_ids']) ? array_map('absint', (array) $_POST['course_ids']) : array();
        $visibility = isset($_POST['visibility']) ? filter_var($_POST['visibility'], FILTER_VALIDATE_BOOLEAN) : false;

        if (empty($course_ids)) {
            wp_send_json_error(array('message' => 'No courses selected'));
        }

        $meta_prefix = Eau_OpenLearning_Post_Type::META_PREFIX;
        $updated_count = 0;

        foreach ($course_ids as $course_id) {
            $post = get_post($course_id);
            if ($post && $post->post_type === Eau_OpenLearning_Post_Type::POST_TYPE) {
                update_post_meta($course_id, $meta_prefix . 'is_visible', $visibility ? 'true' : '');
                $updated_count++;
            }
        }

        wp_send_json_success(array(
            'message' => $updated_count . ' courses updated successfully',
            'updated_count' => $updated_count,
        ));
    }

    /**
     * Bulk set featured status (v1.72.5)
     */
    public static function handle_bulk_featured() {
        // Verifica nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'eau_openlearning_management_nonce')) {
            wp_send_json_error(array('message' => 'Invalid security token'));
        }

        // Verifica permissão
        if (!Eau_User_Institution_Helper::has_admin_access()) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $course_ids = isset($_POST['course_ids']) ? array_map('absint', (array) $_POST['course_ids']) : array();
        $featured = isset($_POST['featured']) ? filter_var($_POST['featured'], FILTER_VALIDATE_BOOLEAN) : false;

        if (empty($course_ids)) {
            wp_send_json_error(array('message' => 'No courses selected'));
        }

        $meta_prefix = Eau_OpenLearning_Post_Type::META_PREFIX;
        $updated_count = 0;

        foreach ($course_ids as $course_id) {
            $post = get_post($course_id);
            if ($post && $post->post_type === Eau_OpenLearning_Post_Type::POST_TYPE) {
                update_post_meta($course_id, $meta_prefix . 'is_featured', $featured ? 'true' : '');
                $updated_count++;
            }
        }

        $status_text = $featured ? 'featured' : 'removed from featured';
        wp_send_json_success(array(
            'message' => $updated_count . ' courses ' . $status_text . ' successfully',
            'updated_count' => $updated_count,
        ));
    }

    /**
     * Retorna estatísticas para a página de gerenciamento
     */
    public static function handle_get_stats() {
        // Verifica nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'eau_openlearning_management_nonce')) {
            wp_send_json_error(array('message' => 'Invalid security token'));
        }

        // Verifica permissão
        if (!Eau_User_Institution_Helper::has_admin_access()) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        global $wpdb;

        $post_type = Eau_OpenLearning_Post_Type::POST_TYPE;
        $meta_prefix = Eau_OpenLearning_Post_Type::META_PREFIX;

        // Total de cursos
        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'",
            $post_type
        ));

        // Cursos visíveis
        $visible = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = %s
            AND p.post_status = 'publish'
            AND pm.meta_key = %s
            AND (pm.meta_value = 'true' OR pm.meta_value = '1')",
            $post_type,
            $meta_prefix . 'is_visible'
        ));

        // Cursos em destaque
        $featured = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = %s
            AND p.post_status = 'publish'
            AND pm.meta_key = %s
            AND (pm.meta_value = 'true' OR pm.meta_value = '1')",
            $post_type,
            $meta_prefix . 'is_featured'
        ));

        // Cursos gratuitos
        $free = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = %s
            AND p.post_status = 'publish'
            AND pm.meta_key = %s
            AND (pm.meta_value = '0' OR pm.meta_value = '' OR pm.meta_value IS NULL)",
            $post_type,
            $meta_prefix . 'price'
        ));

        wp_send_json_success(array(
            'total' => intval($total),
            'visible' => intval($visible),
            'featured' => intval($featured),
            'free' => intval($free),
        ));
    }

    /**
     * Sincroniza cursos da API OpenLearning
     */
    public static function handle_sync() {
        // Verifica nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'eau_openlearning_management_nonce')) {
            wp_send_json_error(array('message' => 'Invalid security token'));
        }

        // Verifica permissão
        if (!Eau_User_Institution_Helper::has_admin_access()) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        // Executa sincronização
        $result = Eau_OpenLearning_Service::sync_course_catalog(false);

        if ($result['success']) {
            wp_send_json_success(array(
                'message' => 'Courses synchronized successfully',
                'stats' => $result['stats'],
            ));
        } else {
            wp_send_json_error(array(
                'message' => $result['error'],
            ));
        }
    }
}
