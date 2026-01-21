<?php
/**
 * Checkout AJAX Handler
 *
 * Processa requisições AJAX para o checkout.
 *
 * @package EauSystem
 * @subpackage Ajax
 * @since 1.70.0
 */

namespace EauSystem\Ajax;

use EauSystem\FatZebra\FatZebra_Gateway;
use EauSystem\FatZebra\FatZebra_Settings;
use EauSystem\FatZebra\FatZebra_Logger;

// Se este arquivo foi chamado diretamente, aborta.
if (!defined('WPINC')) {
    die;
}

class Eau_Checkout_Ajax {

    /**
     * Register AJAX handlers
     */
    public static function register_handlers() {
        add_action('wp_ajax_eau_initiate_payment', array(__CLASS__, 'initiate_payment'));
        add_action('wp_ajax_eau_check_payment_status', array(__CLASS__, 'check_payment_status'));
    }

    /**
     * Initiate payment - create hosted payment session
     */
    public static function initiate_payment() {
        // Verify nonce
        if (!check_ajax_referer('eau_checkout_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => 'Invalid security token'));
        }

        // Check if logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'You must be logged in to make a payment'));
        }

        // Check if gateway is configured
        if (!FatZebra_Settings::is_configured()) {
            wp_send_json_error(array('message' => 'Payment gateway is not configured'));
        }

        // Get parameters
        $type = sanitize_text_field($_POST['type'] ?? '');
        $item_id = absint($_POST['item_id'] ?? 0);
        $amount = absint($_POST['amount'] ?? 0); // In cents

        if (empty($type) || empty($item_id) || empty($amount)) {
            wp_send_json_error(array('message' => 'Missing required parameters'));
        }

        // Validate ownership and get details
        $user = wp_get_current_user();
        $description = '';
        $reference_item_id = $item_id;

        switch ($type) {
            case 'event':
                $validation = self::validate_event_payment($item_id, $user->ID);
                if (is_wp_error($validation)) {
                    wp_send_json_error(array('message' => $validation->get_error_message()));
                }
                $description = $validation['description'];
                break;

            case 'course':
                $validation = self::validate_course_payment($item_id, $user->ID);
                if (is_wp_error($validation)) {
                    wp_send_json_error(array('message' => $validation->get_error_message()));
                }
                $description = $validation['description'];
                break;

            default:
                wp_send_json_error(array('message' => 'Invalid payment type'));
        }

        // Generate reference
        $reference = FatZebra_Gateway::generate_reference($type, $reference_item_id);

        // Get return URLs
        $return_url = add_query_arg(array(
            'type' => $type,
            'status' => 'success',
        ), home_url('/checkout/'));

        $cancel_url = add_query_arg(array(
            'type' => $type,
            'status' => 'cancelled',
        ), home_url('/checkout/'));

        // Create hosted payment session
        $gateway = FatZebra_Gateway::get_instance();

        $payment_data = array(
            'reference'      => $reference,
            'amount'         => $amount,
            'currency'       => 'AUD',
            'customer_email' => $user->user_email,
            'customer_name'  => $user->display_name,
            'description'    => $description,
            'return_url'     => $return_url,
            'cancel_url'     => $cancel_url,
        );

        FatZebra_Logger::log('info', 'Initiating payment', array(
            'type'      => $type,
            'item_id'   => $item_id,
            'reference' => $reference,
            'amount'    => $amount,
            'user_id'   => $user->ID,
        ));

        $result = $gateway->create_hosted_payment($payment_data);

        if (is_wp_error($result)) {
            FatZebra_Logger::log('error', 'Failed to initiate payment', array(
                'error' => $result->get_error_message(),
            ));
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        // Update item with pending payment info
        self::mark_payment_pending($type, $item_id, $reference, $result['session_id'] ?? '');

        FatZebra_Logger::log('info', 'Payment initiated successfully', array(
            'reference'    => $reference,
            'redirect_url' => substr($result['redirect_url'], 0, 50) . '...',
        ));

        wp_send_json_success(array(
            'redirect_url' => $result['redirect_url'],
            'session_id'   => $result['session_id'] ?? null,
            'reference'    => $reference,
        ));
    }

    /**
     * Check payment status
     */
    public static function check_payment_status() {
        // Verify nonce
        if (!check_ajax_referer('eau_checkout_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => 'Invalid security token'));
        }

        $type = sanitize_text_field($_POST['type'] ?? '');
        $item_id = absint($_POST['item_id'] ?? 0);

        if (empty($type) || empty($item_id)) {
            wp_send_json_error(array('message' => 'Missing required parameters'));
        }

        $status = 'unknown';

        switch ($type) {
            case 'event':
                $status = get_post_meta($item_id, 'reg_payment_status', true) ?: 'pending';
                break;

            case 'course':
                global $wpdb;
                $table_name = $wpdb->prefix . 'eau_course_purchases';
                $status = $wpdb->get_var($wpdb->prepare(
                    "SELECT status FROM $table_name WHERE id = %d",
                    $item_id
                )) ?: 'pending';
                break;
        }

        wp_send_json_success(array(
            'status' => $status,
        ));
    }

    /**
     * Validate event payment
     *
     * @param int $reg_id  Registration ID
     * @param int $user_id User ID
     * @return array|WP_Error
     */
    private static function validate_event_payment($reg_id, $user_id) {
        $registration = get_post($reg_id);

        if (!$registration || $registration->post_type !== 'eau_event_reg') {
            return new \WP_Error('not_found', 'Registration not found');
        }

        // Check ownership
        $reg_user_id = get_post_meta($reg_id, 'reg_user_id', true);
        if ((int) $reg_user_id !== $user_id) {
            return new \WP_Error('permission_denied', 'You do not have permission to pay for this registration');
        }

        // Check if already paid
        $payment_status = get_post_meta($reg_id, 'reg_payment_status', true);
        if ($payment_status === 'paid') {
            return new \WP_Error('already_paid', 'This registration has already been paid');
        }

        // Get event for description
        $event_id = get_post_meta($reg_id, 'reg_event_id', true);
        $event = get_post($event_id);

        return array(
            'valid' => true,
            'description' => $event ? 'Event Registration: ' . $event->post_title : 'Event Registration',
        );
    }

    /**
     * Validate course payment
     *
     * @param int $purchase_id Purchase ID
     * @param int $user_id     User ID
     * @return array|WP_Error
     */
    private static function validate_course_payment($purchase_id, $user_id) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'eau_course_purchases';

        $purchase = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $purchase_id),
            ARRAY_A
        );

        if (!$purchase) {
            return new \WP_Error('not_found', 'Purchase not found');
        }

        // Check ownership
        if ((int) $purchase['user_id'] !== $user_id) {
            return new \WP_Error('permission_denied', 'You do not have permission to pay for this purchase');
        }

        // Check if already paid
        if ($purchase['status'] === 'paid') {
            return new \WP_Error('already_paid', 'This course has already been paid');
        }

        // Get course for description
        $course = get_post($purchase['course_id']);

        return array(
            'valid' => true,
            'description' => $course ? 'Course: ' . $course->post_title : 'Course Enrollment',
        );
    }

    /**
     * Mark payment as pending
     *
     * @param string $type       Payment type
     * @param int    $item_id    Item ID
     * @param string $reference  Payment reference
     * @param string $session_id Fat Zebra session ID
     */
    private static function mark_payment_pending($type, $item_id, $reference, $session_id) {
        switch ($type) {
            case 'event':
                update_post_meta($item_id, 'reg_payment_status', 'processing');
                update_post_meta($item_id, 'reg_payment_reference', $reference);
                update_post_meta($item_id, 'reg_payment_session_id', $session_id);
                update_post_meta($item_id, 'reg_payment_initiated_at', current_time('mysql'));
                break;

            case 'course':
                global $wpdb;
                $table_name = $wpdb->prefix . 'eau_course_purchases';

                $wpdb->update(
                    $table_name,
                    array(
                        'status' => 'processing',
                    ),
                    array('id' => $item_id),
                    array('%s'),
                    array('%d')
                );
                break;
        }
    }
}
