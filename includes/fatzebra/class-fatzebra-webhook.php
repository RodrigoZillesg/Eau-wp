<?php
/**
 * Fat Zebra Webhook Handler
 *
 * Processa webhooks recebidos do Fat Zebra.
 * Atualiza status de pagamentos e dispara ações correspondentes.
 *
 * @package EauSystem
 * @subpackage FatZebra
 * @since 1.70.0
 */

namespace EauSystem\FatZebra;

// Se este arquivo foi chamado diretamente, aborta.
if (!defined('WPINC')) {
    die;
}

class FatZebra_Webhook {

    /**
     * Webhook event types
     */
    const EVENT_PURCHASE_SUCCESS = 'purchase.successful';
    const EVENT_PURCHASE_FAILED = 'purchase.failed';
    const EVENT_REFUND_SUCCESS = 'refund.successful';
    const EVENT_REFUND_FAILED = 'refund.failed';

    /**
     * Register REST API endpoint for webhooks
     *
     * @return void
     */
    public static function register_endpoint() {
        add_action('rest_api_init', function () {
            register_rest_route('eau-system/v1', '/fatzebra-webhook', array(
                'methods'             => 'POST',
                'callback'            => array(__CLASS__, 'handle_webhook'),
                'permission_callback' => '__return_true', // Webhook é público, validação por signature
            ));
        });
    }

    /**
     * Handle incoming webhook
     *
     * @param \WP_REST_Request $request REST request object
     * @return \WP_REST_Response|\WP_Error
     */
    public static function handle_webhook($request) {
        // Get raw body for signature verification
        $raw_body = $request->get_body();
        $signature = $request->get_header('X-Signature') ?? $request->get_header('X-FatZebra-Signature');

        FatZebra_Logger::log('info', 'Webhook received', array(
            'has_signature' => !empty($signature),
            'content_type'  => $request->get_content_type(),
        ));

        // Validate signature (if configured)
        $webhook_secret = FatZebra_Settings::get_webhook_secret();
        if (!empty($webhook_secret)) {
            if (empty($signature)) {
                FatZebra_Logger::log('warning', 'Webhook rejected: missing signature');
                return new \WP_Error(
                    'missing_signature',
                    'Webhook signature is required',
                    array('status' => 401)
                );
            }

            $gateway = FatZebra_Gateway::get_instance();
            if (!$gateway->validate_webhook($raw_body, $signature)) {
                FatZebra_Logger::log('warning', 'Webhook rejected: invalid signature');
                return new \WP_Error(
                    'invalid_signature',
                    'Invalid webhook signature',
                    array('status' => 401)
                );
            }
        }

        // Parse payload
        $payload = json_decode($raw_body, true);

        if (empty($payload)) {
            FatZebra_Logger::log('error', 'Webhook rejected: invalid JSON payload');
            return new \WP_Error(
                'invalid_payload',
                'Invalid JSON payload',
                array('status' => 400)
            );
        }

        // Get event type
        $event_type = $payload['event'] ?? $payload['type'] ?? null;

        if (empty($event_type)) {
            FatZebra_Logger::log('error', 'Webhook rejected: missing event type');
            return new \WP_Error(
                'missing_event',
                'Event type is required',
                array('status' => 400)
            );
        }

        FatZebra_Logger::log('info', 'Processing webhook event', array(
            'event'     => $event_type,
            'reference' => $payload['response']['reference'] ?? 'N/A',
        ));

        // Process event
        $result = self::process_event($event_type, $payload);

        if (is_wp_error($result)) {
            FatZebra_Logger::log('error', 'Webhook processing failed', array(
                'event' => $event_type,
                'error' => $result->get_error_message(),
            ));

            return new \WP_REST_Response(array(
                'success' => false,
                'error'   => $result->get_error_message(),
            ), 500);
        }

        FatZebra_Logger::log('info', 'Webhook processed successfully', array(
            'event' => $event_type,
        ));

        return new \WP_REST_Response(array(
            'success' => true,
            'message' => 'Webhook processed',
        ), 200);
    }

    /**
     * Process webhook event
     *
     * @param string $event_type Event type
     * @param array  $payload    Full payload
     * @return bool|WP_Error
     */
    private static function process_event($event_type, $payload) {
        $response = $payload['response'] ?? array();

        switch ($event_type) {
            case self::EVENT_PURCHASE_SUCCESS:
                return self::handle_purchase_success($response);

            case self::EVENT_PURCHASE_FAILED:
                return self::handle_purchase_failed($response);

            case self::EVENT_REFUND_SUCCESS:
                return self::handle_refund_success($response);

            case self::EVENT_REFUND_FAILED:
                return self::handle_refund_failed($response);

            default:
                FatZebra_Logger::log('info', 'Unhandled webhook event type', array(
                    'event' => $event_type,
                ));
                return true; // Retorna sucesso para eventos não tratados
        }
    }

    /**
     * Handle successful purchase
     *
     * @param array $data Response data
     * @return bool|WP_Error
     */
    private static function handle_purchase_success($data) {
        $reference = $data['reference'] ?? '';
        $transaction_id = $data['id'] ?? '';
        $amount = $data['amount'] ?? 0;

        if (empty($reference)) {
            return new \WP_Error('missing_reference', 'Reference is required');
        }

        FatZebra_Logger::log('info', 'Processing successful purchase', array(
            'reference'      => $reference,
            'transaction_id' => $transaction_id,
            'amount'         => $amount,
        ));

        // Parse reference to determine type
        $parsed = FatZebra_Gateway::parse_reference($reference);

        if (!$parsed) {
            FatZebra_Logger::log('warning', 'Could not parse reference', array(
                'reference' => $reference,
            ));
            return new \WP_Error('invalid_reference', 'Could not parse reference');
        }

        switch ($parsed['type']) {
            case 'event':
                return self::process_event_payment($parsed['item_id'], $data, 'paid');

            case 'course':
                return self::process_course_payment($parsed['item_id'], $data, 'paid');

            default:
                return new \WP_Error('unknown_type', 'Unknown payment type: ' . $parsed['type']);
        }
    }

    /**
     * Handle failed purchase
     *
     * @param array $data Response data
     * @return bool|WP_Error
     */
    private static function handle_purchase_failed($data) {
        $reference = $data['reference'] ?? '';
        $transaction_id = $data['id'] ?? '';
        $error_message = $data['message'] ?? 'Payment failed';

        if (empty($reference)) {
            return new \WP_Error('missing_reference', 'Reference is required');
        }

        FatZebra_Logger::log('info', 'Processing failed purchase', array(
            'reference'      => $reference,
            'transaction_id' => $transaction_id,
            'error'          => $error_message,
        ));

        // Parse reference
        $parsed = FatZebra_Gateway::parse_reference($reference);

        if (!$parsed) {
            return new \WP_Error('invalid_reference', 'Could not parse reference');
        }

        switch ($parsed['type']) {
            case 'event':
                return self::process_event_payment($parsed['item_id'], $data, 'failed');

            case 'course':
                return self::process_course_payment($parsed['item_id'], $data, 'failed');

            default:
                return new \WP_Error('unknown_type', 'Unknown payment type');
        }
    }

    /**
     * Handle successful refund
     *
     * @param array $data Response data
     * @return bool|WP_Error
     */
    private static function handle_refund_success($data) {
        $original_transaction_id = $data['original_transaction_id'] ?? '';
        $refund_id = $data['id'] ?? '';
        $amount = $data['amount'] ?? 0;

        FatZebra_Logger::log('info', 'Processing successful refund', array(
            'original_transaction_id' => $original_transaction_id,
            'refund_id'               => $refund_id,
            'amount'                  => $amount,
        ));

        // Find payment by transaction ID and update status
        $payment = self::find_payment_by_transaction_id($original_transaction_id);

        if (!$payment) {
            FatZebra_Logger::log('warning', 'Payment not found for refund', array(
                'transaction_id' => $original_transaction_id,
            ));
            return true; // Não é erro, pode ser transação antiga
        }

        // Update payment status
        update_post_meta($payment->ID, 'pay_status', 'refunded');
        update_post_meta($payment->ID, 'pay_refund_id', $refund_id);
        update_post_meta($payment->ID, 'pay_refund_date', current_time('mysql'));
        update_post_meta($payment->ID, 'pay_refund_amount', $amount);

        // Fire action for other modules
        do_action('eau_payment_refunded', $payment->ID, $data);

        // Send refund email
        self::send_refund_email($payment->ID);

        return true;
    }

    /**
     * Handle failed refund
     *
     * @param array $data Response data
     * @return bool|WP_Error
     */
    private static function handle_refund_failed($data) {
        FatZebra_Logger::log('warning', 'Refund failed', array(
            'data' => $data,
        ));

        // Log the failure but don't change payment status
        return true;
    }

    /**
     * Process event registration payment
     *
     * @param int    $registration_id Registration ID
     * @param array  $data            Transaction data
     * @param string $status          Payment status (paid, failed)
     * @return bool|WP_Error
     */
    private static function process_event_payment($registration_id, $data, $status) {
        // Busca o post de registration
        $registration = get_post($registration_id);

        if (!$registration || $registration->post_type !== 'eau_event_reg') {
            FatZebra_Logger::log('warning', 'Event registration not found', array(
                'registration_id' => $registration_id,
            ));
            return new \WP_Error('not_found', 'Registration not found');
        }

        // Atualiza status de pagamento da registration
        update_post_meta($registration_id, 'reg_payment_status', $status);
        update_post_meta($registration_id, 'reg_transaction_id', $data['id'] ?? '');
        update_post_meta($registration_id, 'reg_payment_date', current_time('mysql'));

        if ($status === 'paid') {
            // Atualiza status da registration para confirmed
            update_post_meta($registration_id, 'reg_status', 'confirmed');

            // Cria registro de pagamento
            $payment_id = self::create_payment_record(array(
                'type'           => 'event',
                'item_id'        => $registration_id,
                'user_id'        => get_post_meta($registration_id, 'reg_user_id', true),
                'amount'         => $data['amount'] ?? 0,
                'transaction_id' => $data['id'] ?? '',
                'status'         => 'completed',
                'data'           => $data,
            ));

            // Dispara ação para enviar email
            do_action('eau_event_payment_completed', $registration_id, $payment_id, $data);

            // Envia email de confirmação
            self::send_payment_confirmation_email('event', $registration_id, $data);
        } else {
            // Pagamento falhou - envia email de falha
            do_action('eau_event_payment_failed', $registration_id, $data);
            self::send_payment_failed_email('event', $registration_id, $data);
        }

        return true;
    }

    /**
     * Process course payment
     *
     * @param int    $purchase_id Course purchase ID
     * @param array  $data        Transaction data
     * @param string $status      Payment status (paid, failed)
     * @return bool|WP_Error
     */
    private static function process_course_payment($purchase_id, $data, $status) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'eau_course_purchases';

        // Verifica se a tabela existe
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");

        if (!$table_exists) {
            FatZebra_Logger::log('error', 'Course purchases table not found');
            return new \WP_Error('table_not_found', 'Course purchases table not found');
        }

        // Busca o purchase
        $purchase = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $purchase_id),
            ARRAY_A
        );

        if (!$purchase) {
            FatZebra_Logger::log('warning', 'Course purchase not found', array(
                'purchase_id' => $purchase_id,
            ));
            return new \WP_Error('not_found', 'Course purchase not found');
        }

        // Atualiza status
        $update_data = array(
            'status'         => $status,
            'transaction_id' => $data['id'] ?? '',
        );

        if ($status === 'paid') {
            $update_data['paid_at'] = current_time('mysql');
        }

        $wpdb->update(
            $table_name,
            $update_data,
            array('id' => $purchase_id),
            array('%s', '%s', '%s'),
            array('%d')
        );

        if ($status === 'paid') {
            // Cria registro de pagamento
            $payment_id = self::create_payment_record(array(
                'type'           => 'course',
                'item_id'        => $purchase_id,
                'user_id'        => $purchase['user_id'],
                'amount'         => $data['amount'] ?? 0,
                'transaction_id' => $data['id'] ?? '',
                'status'         => 'completed',
                'data'           => $data,
            ));

            // Auto-provisioning no OpenLearning
            self::provision_course_access($purchase['user_id'], $purchase['course_id']);

            // Atualiza access_granted_at
            $wpdb->update(
                $table_name,
                array(
                    'access_granted_at' => current_time('mysql'),
                    'payment_id'        => $payment_id,
                ),
                array('id' => $purchase_id),
                array('%s', '%d'),
                array('%d')
            );

            // Dispara ação
            do_action('eau_course_payment_completed', $purchase_id, $payment_id, $data);

            // Envia email
            self::send_payment_confirmation_email('course', $purchase_id, $data);
        } else {
            do_action('eau_course_payment_failed', $purchase_id, $data);
            self::send_payment_failed_email('course', $purchase_id, $data);
        }

        return true;
    }

    /**
     * Create payment record
     *
     * @param array $data Payment data
     * @return int|false Payment ID or false on failure
     */
    private static function create_payment_record($data) {
        // Cria post de pagamento
        $payment_data = array(
            'post_type'   => 'eau_payment',
            'post_status' => 'publish',
            'post_title'  => sprintf(
                'Payment - %s #%d',
                ucfirst($data['type']),
                $data['item_id']
            ),
            'post_author' => $data['user_id'],
        );

        $payment_id = wp_insert_post($payment_data);

        if (is_wp_error($payment_id)) {
            FatZebra_Logger::log('error', 'Failed to create payment record', array(
                'error' => $payment_id->get_error_message(),
            ));
            return false;
        }

        // Salva meta fields
        update_post_meta($payment_id, 'pay_type', $data['type']);
        update_post_meta($payment_id, 'pay_item_id', $data['item_id']);
        update_post_meta($payment_id, 'pay_user_id', $data['user_id']);
        update_post_meta($payment_id, 'pay_amount', FatZebra_Gateway::cents_to_dollars($data['amount']));
        update_post_meta($payment_id, 'pay_amount_cents', $data['amount']);
        update_post_meta($payment_id, 'pay_transaction_id', $data['transaction_id']);
        update_post_meta($payment_id, 'pay_status', $data['status']);
        update_post_meta($payment_id, 'pay_gateway', 'fatzebra');
        update_post_meta($payment_id, 'pay_date', current_time('mysql'));
        update_post_meta($payment_id, 'pay_gateway_response', wp_json_encode($data['data']));

        FatZebra_Logger::log('info', 'Payment record created', array(
            'payment_id'     => $payment_id,
            'type'           => $data['type'],
            'item_id'        => $data['item_id'],
            'transaction_id' => $data['transaction_id'],
        ));

        return $payment_id;
    }

    /**
     * Find payment by transaction ID
     *
     * @param string $transaction_id Fat Zebra transaction ID
     * @return WP_Post|null
     */
    private static function find_payment_by_transaction_id($transaction_id) {
        $payments = get_posts(array(
            'post_type'      => 'eau_payment',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'meta_query'     => array(
                array(
                    'key'   => 'pay_transaction_id',
                    'value' => $transaction_id,
                ),
            ),
        ));

        return !empty($payments) ? $payments[0] : null;
    }

    /**
     * Provision course access via OpenLearning
     *
     * @param int $user_id   WordPress user ID
     * @param int $course_id Course ID
     * @return bool
     */
    private static function provision_course_access($user_id, $course_id) {
        // Verifica se a classe existe
        if (!class_exists('EauSystem\Eau_OpenLearning_Service')) {
            FatZebra_Logger::log('warning', 'OpenLearning service not available');
            return false;
        }

        try {
            $result = \EauSystem\Eau_OpenLearning_Service::provision_user($user_id, $course_id);

            FatZebra_Logger::log('info', 'Course access provisioned', array(
                'user_id'   => $user_id,
                'course_id' => $course_id,
                'success'   => $result,
            ));

            return $result;
        } catch (\Exception $e) {
            FatZebra_Logger::log('error', 'Failed to provision course access', array(
                'user_id'   => $user_id,
                'course_id' => $course_id,
                'error'     => $e->getMessage(),
            ));
            return false;
        }
    }

    /**
     * Send payment confirmation email
     *
     * @param string $type    Payment type (event, course)
     * @param int    $item_id Item ID
     * @param array  $data    Transaction data
     */
    private static function send_payment_confirmation_email($type, $item_id, $data) {
        // Será implementado junto com Email_Payments
        do_action('eau_send_payment_confirmation', $type, $item_id, $data);
    }

    /**
     * Send payment failed email
     *
     * @param string $type    Payment type (event, course)
     * @param int    $item_id Item ID
     * @param array  $data    Transaction data
     */
    private static function send_payment_failed_email($type, $item_id, $data) {
        // Será implementado junto com Email_Payments
        do_action('eau_send_payment_failed', $type, $item_id, $data);
    }

    /**
     * Send refund confirmation email
     *
     * @param int $payment_id Payment ID
     */
    private static function send_refund_email($payment_id) {
        // Será implementado junto com Email_Payments
        do_action('eau_send_refund_confirmation', $payment_id);
    }
}
