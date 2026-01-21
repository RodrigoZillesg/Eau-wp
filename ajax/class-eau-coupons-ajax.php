<?php
namespace EauSystem\Ajax;

use EauSystem\Coupons\Eau_Coupons_Model;
use EauSystem\Coupons\Eau_Coupons_Validator;

/**
 * Handlers AJAX para cupons de desconto
 *
 * @since 1.69.0
 */
class Eau_Coupons_Ajax {

    /**
     * Registra os handlers AJAX
     */
    public static function register() {
        // Admin endpoints (Settings)
        add_action('wp_ajax_eau_get_coupons', array(__CLASS__, 'get_coupons'));
        add_action('wp_ajax_eau_get_coupon', array(__CLASS__, 'get_coupon'));
        add_action('wp_ajax_eau_create_coupon', array(__CLASS__, 'create_coupon'));
        add_action('wp_ajax_eau_update_coupon', array(__CLASS__, 'update_coupon'));
        add_action('wp_ajax_eau_delete_coupon', array(__CLASS__, 'delete_coupon'));
        add_action('wp_ajax_eau_get_coupons_stats', array(__CLASS__, 'get_stats'));

        // Frontend endpoints (Event registration)
        add_action('wp_ajax_eau_validate_coupon', array(__CLASS__, 'validate_coupon'));
        add_action('wp_ajax_nopriv_eau_validate_coupon', array(__CLASS__, 'validate_coupon_nopriv'));

        // Utility
        add_action('wp_ajax_eau_get_events_for_coupon', array(__CLASS__, 'get_events_for_coupon'));
    }

    /**
     * Verifica se usuário é admin
     *
     * @return bool
     */
    private static function is_admin_user() {
        if (!is_user_logged_in()) {
            return false;
        }

        $user_id = get_current_user_id();
        $mem_type = get_user_meta($user_id, 'mem_type', true);

        return in_array($mem_type, array('superAdmin', 'Admin'));
    }

    /**
     * Lista cupons com paginação e filtros
     */
    public static function get_coupons() {
        check_ajax_referer('eau_coupons_nonce', 'nonce');

        if (!self::is_admin_user()) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        $args = array(
            'page' => isset($_POST['page']) ? intval($_POST['page']) : 1,
            'per_page' => isset($_POST['per_page']) ? intval($_POST['per_page']) : 20,
            'search' => isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '',
            'status' => isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '',
            'orderby' => isset($_POST['orderby']) ? sanitize_text_field($_POST['orderby']) : 'created_at',
            'order' => isset($_POST['order']) ? sanitize_text_field($_POST['order']) : 'DESC',
        );

        $result = Eau_Coupons_Model::get_coupons($args);

        // Formata os dados para exibição
        foreach ($result['coupons'] as &$coupon) {
            $coupon['formatted_discount'] = Eau_Coupons_Validator::format_discount($coupon);
            $coupon['validity_description'] = Eau_Coupons_Validator::get_validity_description($coupon);
            $coupon['usage_description'] = Eau_Coupons_Validator::get_usage_description($coupon);
            $coupon['created_at_formatted'] = date_i18n('M j, Y', strtotime($coupon['created_at']));
        }

        wp_send_json_success($result);
    }

    /**
     * Busca detalhes de um cupom
     */
    public static function get_coupon() {
        check_ajax_referer('eau_coupons_nonce', 'nonce');

        if (!self::is_admin_user()) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

        if (!$id) {
            wp_send_json_error(array('message' => 'Invalid coupon ID.'));
        }

        $coupon = Eau_Coupons_Model::get_coupon($id);

        if (!$coupon) {
            wp_send_json_error(array('message' => 'Coupon not found.'));
        }

        // Adiciona informações formatadas
        $coupon['formatted_discount'] = Eau_Coupons_Validator::format_discount($coupon);
        $coupon['validity_description'] = Eau_Coupons_Validator::get_validity_description($coupon);
        $coupon['usage_description'] = Eau_Coupons_Validator::get_usage_description($coupon);

        // Busca nomes dos eventos se scope é específico
        if ($coupon['event_scope'] === 'specific' && !empty($coupon['events'])) {
            $coupon['event_names'] = array();
            foreach ($coupon['events'] as $event_id) {
                $event = get_post($event_id);
                if ($event) {
                    $coupon['event_names'][] = array(
                        'id' => $event_id,
                        'title' => $event->post_title,
                    );
                }
            }
        }

        wp_send_json_success(array('coupon' => $coupon));
    }

    /**
     * Cria um novo cupom
     */
    public static function create_coupon() {
        check_ajax_referer('eau_coupons_nonce', 'nonce');

        if (!self::is_admin_user()) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        // Validações
        $code = isset($_POST['code']) ? sanitize_text_field($_POST['code']) : '';
        if (empty($code)) {
            wp_send_json_error(array('message' => 'Coupon code is required.'));
        }

        $discount_value = isset($_POST['discount_value']) ? floatval($_POST['discount_value']) : 0;
        if ($discount_value <= 0) {
            wp_send_json_error(array('message' => 'Discount value must be greater than 0.'));
        }

        $discount_type = isset($_POST['discount_type']) ? sanitize_text_field($_POST['discount_type']) : 'percentage';
        if ($discount_type === 'percentage' && $discount_value > 100) {
            wp_send_json_error(array('message' => 'Percentage discount cannot exceed 100%.'));
        }

        $data = array(
            'code' => $code,
            'description' => isset($_POST['description']) ? sanitize_text_field($_POST['description']) : '',
            'discount_type' => $discount_type,
            'discount_value' => $discount_value,
            'min_order_value' => isset($_POST['min_order_value']) && $_POST['min_order_value'] !== '' ? floatval($_POST['min_order_value']) : null,
            'max_discount' => isset($_POST['max_discount']) && $_POST['max_discount'] !== '' ? floatval($_POST['max_discount']) : null,
            'valid_from' => isset($_POST['valid_from']) && $_POST['valid_from'] !== '' ? sanitize_text_field($_POST['valid_from']) : null,
            'valid_until' => isset($_POST['valid_until']) && $_POST['valid_until'] !== '' ? sanitize_text_field($_POST['valid_until']) : null,
            'usage_limit' => isset($_POST['usage_limit']) && $_POST['usage_limit'] !== '' ? intval($_POST['usage_limit']) : null,
            'usage_limit_per_user' => isset($_POST['usage_limit_per_user']) && $_POST['usage_limit_per_user'] !== '' ? intval($_POST['usage_limit_per_user']) : 1,
            'status' => isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'active',
            'event_scope' => isset($_POST['event_scope']) ? sanitize_text_field($_POST['event_scope']) : 'all',
            'event_ids' => isset($_POST['event_ids']) ? array_map('intval', (array) $_POST['event_ids']) : array(),
        );

        $coupon_id = Eau_Coupons_Model::create_coupon($data);

        if (!$coupon_id) {
            wp_send_json_error(array('message' => 'Failed to create coupon. The code may already exist.'));
        }

        $coupon = Eau_Coupons_Model::get_coupon($coupon_id);

        wp_send_json_success(array(
            'message' => 'Coupon created successfully.',
            'coupon' => $coupon,
        ));
    }

    /**
     * Atualiza um cupom existente
     */
    public static function update_coupon() {
        check_ajax_referer('eau_coupons_nonce', 'nonce');

        if (!self::is_admin_user()) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

        if (!$id) {
            wp_send_json_error(array('message' => 'Invalid coupon ID.'));
        }

        $data = array();

        // Só adiciona campos que foram enviados
        if (isset($_POST['code'])) {
            $data['code'] = sanitize_text_field($_POST['code']);
        }
        if (isset($_POST['description'])) {
            $data['description'] = sanitize_text_field($_POST['description']);
        }
        if (isset($_POST['discount_type'])) {
            $data['discount_type'] = sanitize_text_field($_POST['discount_type']);
        }
        if (isset($_POST['discount_value'])) {
            $data['discount_value'] = floatval($_POST['discount_value']);
        }
        if (isset($_POST['min_order_value'])) {
            $data['min_order_value'] = $_POST['min_order_value'] !== '' ? floatval($_POST['min_order_value']) : null;
        }
        if (isset($_POST['max_discount'])) {
            $data['max_discount'] = $_POST['max_discount'] !== '' ? floatval($_POST['max_discount']) : null;
        }
        if (isset($_POST['valid_from'])) {
            $data['valid_from'] = $_POST['valid_from'] !== '' ? sanitize_text_field($_POST['valid_from']) : null;
        }
        if (isset($_POST['valid_until'])) {
            $data['valid_until'] = $_POST['valid_until'] !== '' ? sanitize_text_field($_POST['valid_until']) : null;
        }
        if (isset($_POST['usage_limit'])) {
            $data['usage_limit'] = $_POST['usage_limit'] !== '' ? intval($_POST['usage_limit']) : null;
        }
        if (isset($_POST['usage_limit_per_user'])) {
            $data['usage_limit_per_user'] = $_POST['usage_limit_per_user'] !== '' ? intval($_POST['usage_limit_per_user']) : 1;
        }
        if (isset($_POST['status'])) {
            $data['status'] = sanitize_text_field($_POST['status']);
        }
        if (isset($_POST['event_scope'])) {
            $data['event_scope'] = sanitize_text_field($_POST['event_scope']);
            $data['event_ids'] = isset($_POST['event_ids']) ? array_map('intval', (array) $_POST['event_ids']) : array();
        }

        $result = Eau_Coupons_Model::update_coupon($id, $data);

        if (!$result) {
            wp_send_json_error(array('message' => 'Failed to update coupon.'));
        }

        $coupon = Eau_Coupons_Model::get_coupon($id);

        wp_send_json_success(array(
            'message' => 'Coupon updated successfully.',
            'coupon' => $coupon,
        ));
    }

    /**
     * Deleta um cupom
     */
    public static function delete_coupon() {
        check_ajax_referer('eau_coupons_nonce', 'nonce');

        if (!self::is_admin_user()) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

        if (!$id) {
            wp_send_json_error(array('message' => 'Invalid coupon ID.'));
        }

        $result = Eau_Coupons_Model::delete_coupon($id);

        if (!$result) {
            wp_send_json_error(array('message' => 'Failed to delete coupon.'));
        }

        wp_send_json_success(array('message' => 'Coupon deleted successfully.'));
    }

    /**
     * Retorna estatísticas de cupons
     */
    public static function get_stats() {
        check_ajax_referer('eau_coupons_nonce', 'nonce');

        if (!self::is_admin_user()) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        $stats = Eau_Coupons_Model::get_stats();

        wp_send_json_success(array('stats' => $stats));
    }

    /**
     * Valida cupom no frontend (usuário logado)
     */
    public static function validate_coupon() {
        check_ajax_referer('eau_event_registration', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array(
                'message' => 'You must be logged in to use a coupon.',
                'error_code' => 'not_logged_in',
            ));
        }

        $code = isset($_POST['code']) ? sanitize_text_field($_POST['code']) : '';
        $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : null;
        $order_total = isset($_POST['order_total']) ? floatval($_POST['order_total']) : null;
        $member_count = isset($_POST['member_count']) ? intval($_POST['member_count']) : 1;

        if (empty($code)) {
            wp_send_json_error(array(
                'message' => 'Please enter a coupon code.',
                'error_code' => 'empty_code',
            ));
        }

        // Se order_total não foi passado, busca o preço do evento
        if ($order_total === null && $event_id) {
            $price = get_post_meta($event_id, 'event_price', true);
            $order_total = floatval($price) * $member_count;
        }

        // Valida cupom
        if ($member_count > 1) {
            $price_per_person = $order_total / $member_count;
            $result = Eau_Coupons_Validator::validate_for_multiple($code, $event_id, $price_per_person, $member_count);
        } else {
            $result = Eau_Coupons_Validator::validate($code, $event_id, $order_total);
        }

        if (!$result['valid']) {
            wp_send_json_error(array(
                'message' => $result['error'],
                'error_code' => $result['error_code'],
            ));
        }

        // Formata resposta
        $coupon = $result['coupon'];
        $discount = $result['discount'];

        wp_send_json_success(array(
            'valid' => true,
            'coupon_id' => $coupon['id'],
            'coupon_code' => $coupon['code'],
            'discount_type' => $coupon['discount_type'],
            'discount_value' => $coupon['discount_value'],
            'discount_amount' => $discount['amount'],
            'original_total' => $discount['original_total'],
            'final_total' => $discount['final_total'],
            'formatted_discount' => Eau_Coupons_Validator::format_discount($coupon),
            'message' => 'Coupon applied successfully!',
        ));
    }

    /**
     * Valida cupom no frontend (usuário não logado)
     */
    public static function validate_coupon_nopriv() {
        wp_send_json_error(array(
            'message' => 'You must be logged in to use a coupon.',
            'error_code' => 'not_logged_in',
        ));
    }

    /**
     * Busca eventos para seleção no modal de cupom
     */
    public static function get_events_for_coupon() {
        check_ajax_referer('eau_coupons_nonce', 'nonce');

        if (!self::is_admin_user()) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';

        $args = array(
            'post_type' => 'eau_event',
            'post_status' => array('publish', 'draft'),
            'posts_per_page' => 50,
            'orderby' => 'date',
            'order' => 'DESC',
        );

        if (!empty($search)) {
            $args['s'] = $search;
        }

        $events = get_posts($args);

        $result = array();
        foreach ($events as $event) {
            $start_date = get_post_meta($event->ID, 'evt_start_date', true);

            // Get prices - check both member and non-member prices
            $member_price = get_post_meta($event->ID, 'evt_member_price', true);
            $non_member_price = get_post_meta($event->ID, 'evt_non_member_price', true);

            // Determine display price (use member price if available, otherwise non-member)
            $price = '';
            if (!empty($member_price) && floatval($member_price) > 0) {
                $price = '$' . number_format(floatval($member_price), 2);
                if (!empty($non_member_price) && floatval($non_member_price) > 0 && $non_member_price !== $member_price) {
                    $price .= ' / $' . number_format(floatval($non_member_price), 2);
                }
            } elseif (!empty($non_member_price) && floatval($non_member_price) > 0) {
                $price = '$' . number_format(floatval($non_member_price), 2);
            } else {
                $price = 'Free';
            }

            $result[] = array(
                'id' => $event->ID,
                'title' => $event->post_title,
                'date' => $start_date ? date_i18n('M j, Y', strtotime($start_date)) : '',
                'price' => $price,
                'status' => $event->post_status,
            );
        }

        wp_send_json_success(array('events' => $result));
    }
}
