<?php
/**
 * Payments AJAX Handlers
 *
 * @package    EauSystem
 * @subpackage Payments
 * @since      1.45.0
 */

namespace EauSystem\Payments;

if (!defined('WPINC')) {
    die;
}

/**
 * Class Payments_Ajax
 *
 * AJAX handlers para gerenciamento de pagamentos.
 *
 * @since 1.45.0
 */
class Payments_Ajax {

    /**
     * Registra handlers AJAX
     *
     * @since  1.45.0
     * @return void
     */
    public static function register_handlers() {
        add_action('wp_ajax_eau_add_payment', array(__CLASS__, 'add_payment'));
        add_action('wp_ajax_eau_get_payments', array(__CLASS__, 'get_payments'));
        add_action('wp_ajax_eau_delete_payment', array(__CLASS__, 'delete_payment'));
        add_action('wp_ajax_eau_get_registration_payment_info', array(__CLASS__, 'get_registration_payment_info'));
    }

    /**
     * Verifica permissão de gerenciar eventos
     *
     * @since  1.45.0
     * @return bool
     */
    private static function can_manage_events() {
        if (!is_user_logged_in()) {
            return false;
        }

        $user_id = get_current_user_id();
        $mem_type = get_user_meta($user_id, 'mem_type', true);

        return in_array($mem_type, array('superAdmin', 'Admin'));
    }

    /**
     * AJAX: Adiciona um novo pagamento
     *
     * @since  1.45.0
     * @return void
     */
    public static function add_payment() {
        check_ajax_referer('eau_events_management_nonce', 'nonce');

        if (!self::can_manage_events()) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $registration_id = isset($_POST['registration_id']) ? absint($_POST['registration_id']) : 0;
        $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
        $payment_date = isset($_POST['payment_date']) ? sanitize_text_field($_POST['payment_date']) : '';
        $payment_method = isset($_POST['payment_method']) ? sanitize_key($_POST['payment_method']) : '';
        $notes = isset($_POST['notes']) ? sanitize_textarea_field($_POST['notes']) : '';
        $receipt_id = isset($_POST['receipt_id']) ? absint($_POST['receipt_id']) : 0;

        // Validações
        if (!$registration_id) {
            wp_send_json_error(array('message' => 'Invalid registration ID'));
        }

        if ($amount <= 0) {
            wp_send_json_error(array('message' => 'Amount must be greater than zero'));
        }

        if (empty($payment_date)) {
            wp_send_json_error(array('message' => 'Payment date is required'));
        }

        if (empty($payment_method)) {
            wp_send_json_error(array('message' => 'Payment method is required'));
        }

        // Verifica se registration existe
        $registration = get_post($registration_id);
        if (!$registration || $registration->post_type !== 'eau_event_reg') {
            wp_send_json_error(array('message' => 'Registration not found'));
        }

        // Obtém dados da registration
        $event_id = get_post_meta($registration_id, 'reg_event_id', true);
        $user_id = get_post_meta($registration_id, 'reg_user_id', true);

        // Prepara URL do comprovante
        $receipt_url = '';
        if ($receipt_id) {
            $receipt_url = wp_get_attachment_url($receipt_id);
        }

        // Cria o pagamento
        $payment_id = Payments_Post_Type::create_payment(array(
            'registration_id' => $registration_id,
            'event_id'        => intval($event_id),
            'user_id'         => intval($user_id),
            'amount'          => $amount,
            'payment_date'    => $payment_date,
            'payment_method'  => $payment_method,
            'receipt_url'     => $receipt_url,
            'receipt_id'      => $receipt_id,
            'notes'           => $notes,
            'created_by'      => get_current_user_id(),
            'status'          => 'confirmed',
        ));

        if (is_wp_error($payment_id)) {
            wp_send_json_error(array('message' => 'Failed to create payment'));
        }

        // Atualiza status de pagamento da registration
        self::update_registration_payment_status($registration_id);

        // Retorna pagamentos atualizados
        $payments = Payments_Post_Type::get_payments_by_registration($registration_id);
        $total_paid = Payments_Post_Type::get_total_paid($registration_id);
        $event_price = floatval(get_post_meta($event_id, 'evt_member_price', true) ?: 0);

        wp_send_json_success(array(
            'message'    => 'Payment added successfully',
            'payment_id' => $payment_id,
            'payments'   => $payments,
            'total_paid' => $total_paid,
            'balance'    => max(0, $event_price - $total_paid),
        ));
    }

    /**
     * AJAX: Lista pagamentos de uma registration
     *
     * @since  1.45.0
     * @return void
     */
    public static function get_payments() {
        check_ajax_referer('eau_events_management_nonce', 'nonce');

        if (!self::can_manage_events()) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $registration_id = isset($_POST['registration_id']) ? absint($_POST['registration_id']) : 0;

        if (!$registration_id) {
            wp_send_json_error(array('message' => 'Invalid registration ID'));
        }

        $payments = Payments_Post_Type::get_payments_by_registration($registration_id);
        $total_paid = Payments_Post_Type::get_total_paid($registration_id);

        // Obtém preço do evento
        $event_id = get_post_meta($registration_id, 'reg_event_id', true);
        $event_price = floatval(get_post_meta($event_id, 'evt_member_price', true) ?: 0);

        wp_send_json_success(array(
            'payments'   => $payments,
            'total_paid' => $total_paid,
            'balance'    => max(0, $event_price - $total_paid),
        ));
    }

    /**
     * AJAX: Deleta um pagamento
     *
     * @since  1.45.0
     * @return void
     */
    public static function delete_payment() {
        check_ajax_referer('eau_events_management_nonce', 'nonce');

        if (!self::can_manage_events()) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $payment_id = isset($_POST['payment_id']) ? absint($_POST['payment_id']) : 0;

        if (!$payment_id) {
            wp_send_json_error(array('message' => 'Invalid payment ID'));
        }

        $payment = get_post($payment_id);
        if (!$payment || $payment->post_type !== Payments_Post_Type::POST_TYPE) {
            wp_send_json_error(array('message' => 'Payment not found'));
        }

        // Obtém registration_id antes de deletar
        $registration_id = get_post_meta($payment_id, Payments_Post_Type::META_PREFIX . 'registration_id', true);

        // Deleta o pagamento
        wp_trash_post($payment_id);

        // Atualiza status de pagamento da registration
        if ($registration_id) {
            self::update_registration_payment_status($registration_id);
        }

        // Retorna pagamentos atualizados
        $payments = Payments_Post_Type::get_payments_by_registration($registration_id);
        $total_paid = Payments_Post_Type::get_total_paid($registration_id);
        $event_id = get_post_meta($registration_id, 'reg_event_id', true);
        $event_price = floatval(get_post_meta($event_id, 'evt_member_price', true) ?: 0);

        wp_send_json_success(array(
            'message'    => 'Payment deleted successfully',
            'payments'   => $payments,
            'total_paid' => $total_paid,
            'balance'    => max(0, $event_price - $total_paid),
        ));
    }

    /**
     * AJAX: Obtém informações de pagamento de uma registration
     *
     * @since  1.45.0
     * @return void
     */
    public static function get_registration_payment_info() {
        check_ajax_referer('eau_events_management_nonce', 'nonce');

        if (!self::can_manage_events()) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $registration_id = isset($_POST['registration_id']) ? absint($_POST['registration_id']) : 0;

        if (!$registration_id) {
            wp_send_json_error(array('message' => 'Invalid registration ID'));
        }

        // Dados da registration
        $registration = get_post($registration_id);
        if (!$registration || $registration->post_type !== 'eau_event_reg') {
            wp_send_json_error(array('message' => 'Registration not found'));
        }

        $attendee_name = get_post_meta($registration_id, 'reg_attendee_name', true);
        $attendee_email = get_post_meta($registration_id, 'reg_attendee_email', true);
        $event_id = get_post_meta($registration_id, 'reg_event_id', true);
        $payment_status = get_post_meta($registration_id, 'reg_status', true) ?: 'pending';

        // Dados do evento
        $event = get_post($event_id);
        $event_title = $event ? $event->post_title : 'Unknown Event';
        $event_price = floatval(get_post_meta($event_id, 'evt_member_price', true) ?: 0);

        // Pagamentos
        $payments = Payments_Post_Type::get_payments_by_registration($registration_id);
        $total_paid = Payments_Post_Type::get_total_paid($registration_id);

        // Métodos de pagamento disponíveis
        $payment_methods = Payments_Post_Type::get_payment_methods();

        wp_send_json_success(array(
            'registration' => array(
                'id'             => $registration_id,
                'attendee_name'  => $attendee_name,
                'attendee_email' => $attendee_email,
                'payment_status' => $payment_status,
            ),
            'event' => array(
                'id'    => $event_id,
                'title' => $event_title,
                'price' => $event_price,
            ),
            'payments'        => $payments,
            'total_paid'      => $total_paid,
            'balance'         => max(0, $event_price - $total_paid),
            'payment_methods' => $payment_methods,
        ));
    }

    /**
     * Atualiza status de pagamento da registration baseado nos pagamentos
     *
     * @since  1.45.0
     * @param  int $registration_id ID da registration
     * @return void
     */
    private static function update_registration_payment_status($registration_id) {
        $event_id = get_post_meta($registration_id, 'reg_event_id', true);
        $event_price = floatval(get_post_meta($event_id, 'evt_member_price', true) ?: 0);
        $total_paid = Payments_Post_Type::get_total_paid($registration_id);

        // Determina novo status
        if ($event_price <= 0) {
            $new_status = 'free';
        } elseif ($total_paid >= $event_price) {
            $new_status = 'paid';
        } elseif ($total_paid > 0) {
            $new_status = 'partial';
        } else {
            $new_status = 'pending';
        }

        update_post_meta($registration_id, 'reg_status', $new_status);
    }
}
