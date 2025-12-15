<?php
/**
 * Payments Management AJAX Handlers
 *
 * Refatorado para mostrar FATURAS (registrations/applications) ao invés de pagamentos.
 * Segue o padrão de Event Registrations onde a tabela mostra inscrições/applications
 * pendentes de pagamento, e o admin pode adicionar pagamentos a cada fatura.
 *
 * @package    EauSystem
 * @subpackage Ajax
 * @since      1.50.1
 * @updated    1.51.0 - Refatorado para mostrar faturas
 */

namespace EauSystem\Ajax;

use EauSystem\Eau_User_Institution_Helper;
use EauSystem\Eau_Membership_Types;
use EauSystem\Payments\Payments_Post_Type;

if (!defined('WPINC')) {
    die;
}

/**
 * Class Eau_Payments_Management_Ajax
 *
 * AJAX handlers for unified invoices management (events + membership).
 *
 * @since 1.50.1
 */
class Eau_Payments_Management_Ajax {

    /**
     * Inicializa os handlers AJAX
     *
     * @since  1.50.1
     * @return void
     */
    public static function init() {
        // List invoices (registrations + applications)
        add_action('wp_ajax_eau_get_invoices', array(__CLASS__, 'get_invoices'));

        // Get invoice details (registration or application with payments)
        add_action('wp_ajax_eau_get_invoice_details', array(__CLASS__, 'get_invoice_details'));

        // Add payment to invoice
        add_action('wp_ajax_eau_add_invoice_payment', array(__CLASS__, 'add_payment'));

        // Delete payment
        add_action('wp_ajax_eau_delete_invoice_payment', array(__CLASS__, 'delete_payment'));

        // Get invoice stats
        add_action('wp_ajax_eau_get_invoice_stats', array(__CLASS__, 'get_stats'));

        // Export invoices CSV
        add_action('wp_ajax_eau_export_invoices_csv', array(__CLASS__, 'export_csv'));

        // Search events for dropdown
        add_action('wp_ajax_eau_search_events_for_payment', array(__CLASS__, 'search_events'));
    }

    /**
     * Lista todas as faturas (registrations + applications)
     *
     * @since  1.51.0
     * @return void
     */
    public static function get_invoices() {
        check_ajax_referer('eau_payments_management_nonce', 'nonce');

        if (!Eau_User_Institution_Helper::has_admin_access()) {
            wp_send_json_error(array('message' => __('Permission denied', 'eau-system')));
        }

        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $per_page = isset($_POST['per_page']) ? intval($_POST['per_page']) : 20;
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $invoice_type = isset($_POST['invoice_type']) ? sanitize_text_field($_POST['invoice_type']) : '';
        $payment_status = isset($_POST['payment_status']) ? sanitize_text_field($_POST['payment_status']) : '';
        $order_by = isset($_POST['order_by']) ? sanitize_text_field($_POST['order_by']) : 'date';
        $order = isset($_POST['order']) ? strtoupper(sanitize_text_field($_POST['order'])) : 'DESC';

        $invoices = array();

        // Get Event Registrations (if not filtered to membership only)
        if (empty($invoice_type) || $invoice_type === 'event') {
            $event_invoices = self::get_event_registrations($search, $payment_status);
            $invoices = array_merge($invoices, $event_invoices);
        }

        // Get Membership Applications (if not filtered to event only)
        if (empty($invoice_type) || $invoice_type === 'membership') {
            $membership_invoices = self::get_membership_applications($search, $payment_status);
            $invoices = array_merge($invoices, $membership_invoices);
        }

        // Sort invoices
        usort($invoices, function($a, $b) use ($order_by, $order) {
            $field = $order_by === 'date' ? 'date_raw' : $order_by;
            $aVal = isset($a[$field]) ? $a[$field] : '';
            $bVal = isset($b[$field]) ? $b[$field] : '';

            if ($order === 'ASC') {
                return $aVal <=> $bVal;
            }
            return $bVal <=> $aVal;
        });

        // Paginate
        $total = count($invoices);
        $total_pages = ceil($total / $per_page);
        $offset = ($page - 1) * $per_page;
        $invoices = array_slice($invoices, $offset, $per_page);

        wp_send_json_success(array(
            'rows'        => $invoices,
            'total'       => $total,
            'total_pages' => $total_pages,
            'page'        => $page,
        ));
    }

    /**
     * Busca Event Registrations como faturas
     *
     * @since  1.51.0
     * @param  string $search Search term
     * @param  string $payment_status Filter by payment status
     * @return array
     */
    private static function get_event_registrations($search = '', $payment_status = '') {
        global $wpdb;

        $invoices = array();

        // Query event registrations
        // Note: payment_status filter is applied after calculating based on amounts
        $args = array(
            'post_type'      => 'eau_event_reg',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
        );

        $registrations = get_posts($args);

        foreach ($registrations as $reg) {
            $reg_id = $reg->ID;
            $attendee_name = get_post_meta($reg_id, 'reg_attendee_name', true);
            $attendee_email = get_post_meta($reg_id, 'reg_attendee_email', true);
            $event_id = get_post_meta($reg_id, 'reg_event_id', true);
            $user_id = get_post_meta($reg_id, 'reg_user_id', true);
            $reg_status = get_post_meta($reg_id, 'reg_status', true) ?: 'pending';
            $reg_date = get_post_meta($reg_id, 'reg_registration_date', true) ?: $reg->post_date;

            // Apply search filter
            if (!empty($search)) {
                $search_lower = strtolower($search);
                if (
                    strpos(strtolower($attendee_name), $search_lower) === false &&
                    strpos(strtolower($attendee_email), $search_lower) === false
                ) {
                    continue;
                }
            }

            // Get event info
            $event = get_post($event_id);
            $event_title = $event ? $event->post_title : __('Unknown Event', 'eau-system');
            $event_price = floatval(get_post_meta($event_id, 'evt_member_price', true) ?: 0);

            // Get total paid
            $total_paid = Payments_Post_Type::get_total_paid($reg_id);
            $balance = max(0, $event_price - $total_paid);

            // Determine payment status based on amounts (not registration status)
            if ($event_price <= 0) {
                $pay_status = 'free';
            } elseif ($total_paid >= $event_price) {
                $pay_status = 'paid';
            } elseif ($total_paid > 0) {
                $pay_status = 'partial';
            } else {
                $pay_status = 'pending';
            }

            // Filter by payment status if specified
            if (!empty($payment_status) && $pay_status !== $payment_status) {
                continue;
            }

            $status_label = self::get_payment_status_label($pay_status);
            $status_class = self::get_payment_status_class($pay_status);

            $invoices[] = array(
                'id'              => $reg_id,
                'invoice_type'    => 'event',
                'type_label'      => __('Event', 'eau-system'),
                'type_class'      => 'eau-badge-info',
                'member_name'     => esc_html($attendee_name),
                'member_email'    => esc_html($attendee_email),
                'user_id'         => $user_id,
                'reference'       => esc_html($event_title),
                'reference_id'    => $event_id,
                'amount_due'      => $event_price,
                'amount_due_fmt'  => '$' . number_format($event_price, 2),
                'amount_paid'     => $total_paid,
                'amount_paid_fmt' => '$' . number_format($total_paid, 2),
                'balance'         => $balance,
                'balance_fmt'     => '$' . number_format($balance, 2),
                'payment_status'  => $pay_status,
                'status_label'    => $status_label,
                'status_class'    => $status_class,
                'date'            => date('M j, Y', strtotime($reg_date)),
                'date_raw'        => $reg_date,
            );
        }

        return $invoices;
    }

    /**
     * Busca Membership Applications como faturas
     *
     * @since  1.51.0
     * @param  string $search Search term
     * @param  string $payment_status Filter by payment status
     * @return array
     */
    private static function get_membership_applications($search = '', $payment_status = '') {
        global $wpdb;

        $invoices = array();
        $table = $wpdb->prefix . 'eau_membership_applications';

        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return $invoices;
        }

        // Get approved and cancelled applications (those that need payment tracking)
        // Cancelled applications show as "Cancelled" status in Payments
        $where = "WHERE status IN ('approved', 'cancelled')";

        if (!empty($search)) {
            $search_like = '%' . $wpdb->esc_like($search) . '%';
            $where .= $wpdb->prepare(
                " AND (first_name LIKE %s OR last_name LIKE %s OR email LIKE %s)",
                $search_like, $search_like, $search_like
            );
        }

        $applications = $wpdb->get_results("SELECT * FROM $table $where ORDER BY submitted_at DESC");

        foreach ($applications as $app) {
            // Get membership type info
            $type = Eau_Membership_Types::get_by_key($app->membership_type);
            $type_label = $type ? $type->type_label : $app->membership_type;

            // Calculate fee
            $fee = 0;
            if ($type) {
                if ($type->fee_is_variable) {
                    // For variable fees, get from membership_data
                    $data = json_decode($app->membership_data, true);
                    $fee = isset($data['calculated_fee']) ? floatval($data['calculated_fee']) : floatval($type->fee_amount);
                } else {
                    $fee = floatval($type->fee_amount);
                }
            }

            // Get total paid for this application
            $total_paid = Payments_Post_Type::get_membership_total_paid_by_application($app->application_id);
            $balance = max(0, $fee - $total_paid);

            // Determine payment status
            // If application is cancelled, show as "Cancelled" regardless of payment
            if ($app->status === 'cancelled') {
                $pay_status = 'cancelled';
            } elseif ($fee <= 0) {
                $pay_status = 'free';
            } elseif ($total_paid >= $fee) {
                $pay_status = 'paid';
            } elseif ($total_paid > 0) {
                $pay_status = 'partial';
            } else {
                $pay_status = 'pending';
            }

            // Filter by payment status if specified
            if (!empty($payment_status) && $pay_status !== $payment_status) {
                continue;
            }

            $status_label = self::get_payment_status_label($pay_status);
            $status_class = self::get_payment_status_class($pay_status);

            $invoices[] = array(
                'id'              => $app->application_id,
                'invoice_type'    => 'membership',
                'type_label'      => __('Membership', 'eau-system'),
                'type_class'      => 'eau-badge-purple',
                'member_name'     => esc_html($app->first_name . ' ' . $app->last_name),
                'member_email'    => esc_html($app->email),
                'user_id'         => $app->created_user_id,
                'reference'       => esc_html($type_label),
                'reference_id'    => $app->membership_type,
                'amount_due'      => $fee,
                'amount_due_fmt'  => '$' . number_format($fee, 2),
                'amount_paid'     => $total_paid,
                'amount_paid_fmt' => '$' . number_format($total_paid, 2),
                'balance'         => $balance,
                'balance_fmt'     => '$' . number_format($balance, 2),
                'payment_status'  => $pay_status,
                'status_label'    => $status_label,
                'status_class'    => $status_class,
                'date'            => date('M j, Y', strtotime($app->submitted_at)),
                'date_raw'        => $app->submitted_at,
                'app_status'      => $app->status, // Include application status for reference
            );
        }

        return $invoices;
    }

    /**
     * Obtém label do status de pagamento
     *
     * @since  1.51.0
     * @param  string $status Status
     * @return string
     */
    private static function get_payment_status_label($status) {
        $labels = array(
            'free'      => __('Free', 'eau-system'),
            'pending'   => __('Pending', 'eau-system'),
            'partial'   => __('Partial', 'eau-system'),
            'paid'      => __('Paid', 'eau-system'),
            'cancelled' => __('Cancelled', 'eau-system'),
        );
        return isset($labels[$status]) ? $labels[$status] : ucfirst($status);
    }

    /**
     * Obtém classe CSS do status de pagamento
     *
     * @since  1.51.0
     * @param  string $status Status
     * @return string
     */
    private static function get_payment_status_class($status) {
        $classes = array(
            'free'      => 'eau-badge-secondary',
            'pending'   => 'eau-badge-warning',
            'partial'   => 'eau-badge-info',
            'paid'      => 'eau-badge-success',
            'cancelled' => 'eau-badge-danger',
        );
        return isset($classes[$status]) ? $classes[$status] : 'eau-badge-secondary';
    }

    /**
     * Obtém detalhes de uma fatura (registration ou application)
     *
     * @since  1.51.0
     * @return void
     */
    public static function get_invoice_details() {
        check_ajax_referer('eau_payments_management_nonce', 'nonce');

        if (!Eau_User_Institution_Helper::has_admin_access()) {
            wp_send_json_error(array('message' => __('Permission denied', 'eau-system')));
        }

        $invoice_id = isset($_POST['invoice_id']) ? intval($_POST['invoice_id']) : 0;
        $invoice_type = isset($_POST['invoice_type']) ? sanitize_text_field($_POST['invoice_type']) : '';

        if (!$invoice_id || !$invoice_type) {
            wp_send_json_error(array('message' => __('Invalid invoice', 'eau-system')));
        }

        if ($invoice_type === 'event') {
            $details = self::get_event_registration_details($invoice_id);
        } else {
            $details = self::get_membership_application_details($invoice_id);
        }

        if (!$details) {
            wp_send_json_error(array('message' => __('Invoice not found', 'eau-system')));
        }

        wp_send_json_success($details);
    }

    /**
     * Obtém detalhes de uma Event Registration
     *
     * @since  1.51.0
     * @param  int $registration_id ID da registration
     * @return array|null
     */
    private static function get_event_registration_details($registration_id) {
        $reg = get_post($registration_id);
        if (!$reg || $reg->post_type !== 'eau_event_reg') {
            return null;
        }

        $attendee_name = get_post_meta($registration_id, 'reg_attendee_name', true);
        $attendee_email = get_post_meta($registration_id, 'reg_attendee_email', true);
        $event_id = get_post_meta($registration_id, 'reg_event_id', true);
        $user_id = get_post_meta($registration_id, 'reg_user_id', true);
        $reg_status = get_post_meta($registration_id, 'reg_status', true) ?: 'pending';
        $reg_date = get_post_meta($registration_id, 'reg_registration_date', true) ?: $reg->post_date;

        // Event info
        $event = get_post($event_id);
        $event_title = $event ? $event->post_title : __('Unknown Event', 'eau-system');
        $event_price = floatval(get_post_meta($event_id, 'evt_member_price', true) ?: 0);

        // Payments
        $payments = Payments_Post_Type::get_payments_by_registration($registration_id);
        $total_paid = Payments_Post_Type::get_total_paid($registration_id);
        $balance = max(0, $event_price - $total_paid);

        // Format payments for display
        $formatted_payments = array();
        foreach ($payments as $payment) {
            $formatted_payments[] = self::format_payment_for_list($payment);
        }

        // Payment methods
        $payment_methods = Payments_Post_Type::get_payment_methods();

        return array(
            'invoice_id'      => $registration_id,
            'invoice_type'    => 'event',
            'member_name'     => $attendee_name,
            'member_email'    => $attendee_email,
            'user_id'         => $user_id,
            'reference'       => $event_title,
            'reference_id'    => $event_id,
            'amount_due'      => $event_price,
            'amount_due_fmt'  => '$' . number_format($event_price, 2),
            'total_paid'      => $total_paid,
            'total_paid_fmt'  => '$' . number_format($total_paid, 2),
            'balance'         => $balance,
            'balance_fmt'     => '$' . number_format($balance, 2),
            'payment_status'  => $reg_status,
            'status_label'    => self::get_payment_status_label($reg_status),
            'date'            => date('M j, Y', strtotime($reg_date)),
            'payments'        => $formatted_payments,
            'payment_methods' => $payment_methods,
        );
    }

    /**
     * Obtém detalhes de uma Membership Application
     *
     * @since  1.51.0
     * @param  int $application_id ID da application
     * @return array|null
     */
    private static function get_membership_application_details($application_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'eau_membership_applications';

        $app = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE application_id = %d", $application_id));
        if (!$app) {
            return null;
        }

        // Membership type info
        $type = Eau_Membership_Types::get_by_key($app->membership_type);
        $type_label = $type ? $type->type_label : $app->membership_type;

        // Calculate fee
        $fee = 0;
        if ($type) {
            if ($type->fee_is_variable) {
                $data = json_decode($app->membership_data, true);
                $fee = isset($data['calculated_fee']) ? floatval($data['calculated_fee']) : floatval($type->fee_amount);
            } else {
                $fee = floatval($type->fee_amount);
            }
        }

        // Payments
        $payments = Payments_Post_Type::get_payments_by_application($application_id);
        $total_paid = Payments_Post_Type::get_membership_total_paid_by_application($application_id);
        $balance = max(0, $fee - $total_paid);

        // Determine status
        if ($fee <= 0) {
            $pay_status = 'free';
        } elseif ($total_paid >= $fee) {
            $pay_status = 'paid';
        } elseif ($total_paid > 0) {
            $pay_status = 'partial';
        } else {
            $pay_status = 'pending';
        }

        // Format payments for display
        $formatted_payments = array();
        foreach ($payments as $payment) {
            $formatted_payments[] = self::format_payment_for_list($payment);
        }

        // Payment methods
        $payment_methods = Payments_Post_Type::get_payment_methods();

        return array(
            'invoice_id'      => $application_id,
            'invoice_type'    => 'membership',
            'member_name'     => $app->first_name . ' ' . $app->last_name,
            'member_email'    => $app->email,
            'user_id'         => $app->created_user_id,
            'reference'       => $type_label,
            'reference_id'    => $app->membership_type,
            'amount_due'      => $fee,
            'amount_due_fmt'  => '$' . number_format($fee, 2),
            'total_paid'      => $total_paid,
            'total_paid_fmt'  => '$' . number_format($total_paid, 2),
            'balance'         => $balance,
            'balance_fmt'     => '$' . number_format($balance, 2),
            'payment_status'  => $pay_status,
            'status_label'    => self::get_payment_status_label($pay_status),
            'date'            => date('M j, Y', strtotime($app->submitted_at)),
            'payments'        => $formatted_payments,
            'payment_methods' => $payment_methods,
        );
    }

    /**
     * Formata pagamento para exibição na lista
     *
     * @since  1.51.0
     * @param  array $payment Dados do pagamento
     * @return array
     */
    private static function format_payment_for_list($payment) {
        $method_labels = Payments_Post_Type::get_payment_methods();
        $method = isset($payment['payment_method']) ? $payment['payment_method'] : '';

        return array(
            'id'           => $payment['id'],
            'amount'       => '$' . number_format($payment['amount'], 2),
            'amount_raw'   => $payment['amount'],
            'date'         => !empty($payment['payment_date']) ? date('M j, Y', strtotime($payment['payment_date'])) : '-',
            'method'       => isset($method_labels[$method]) ? $method_labels[$method] : $method,
            'status'       => isset($payment['status']) ? ucfirst($payment['status']) : 'Confirmed',
            'notes'        => isset($payment['notes']) ? $payment['notes'] : '',
            'receipt_url'  => isset($payment['receipt_url']) ? $payment['receipt_url'] : '',
            'has_receipt'  => !empty($payment['receipt_url']) || !empty($payment['receipt_id']),
        );
    }

    /**
     * Adiciona um pagamento a uma fatura
     *
     * @since  1.51.0
     * @return void
     */
    public static function add_payment() {
        check_ajax_referer('eau_payments_management_nonce', 'nonce');

        if (!Eau_User_Institution_Helper::has_admin_access()) {
            wp_send_json_error(array('message' => __('Permission denied', 'eau-system')));
        }

        $invoice_id = isset($_POST['invoice_id']) ? intval($_POST['invoice_id']) : 0;
        $invoice_type = isset($_POST['invoice_type']) ? sanitize_text_field($_POST['invoice_type']) : '';
        $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
        $payment_date = isset($_POST['payment_date']) ? sanitize_text_field($_POST['payment_date']) : '';
        $payment_method = isset($_POST['payment_method']) ? sanitize_text_field($_POST['payment_method']) : '';

        // Validate
        if (!$invoice_id || !$invoice_type) {
            wp_send_json_error(array('message' => __('Invalid invoice', 'eau-system')));
        }

        if ($amount <= 0) {
            wp_send_json_error(array('message' => __('Amount must be greater than zero', 'eau-system')));
        }

        if (empty($payment_date)) {
            wp_send_json_error(array('message' => __('Payment date is required', 'eau-system')));
        }

        if (empty($payment_method)) {
            wp_send_json_error(array('message' => __('Payment method is required', 'eau-system')));
        }

        $notes = isset($_POST['notes']) ? sanitize_textarea_field($_POST['notes']) : '';
        $receipt_id = isset($_POST['receipt_id']) ? intval($_POST['receipt_id']) : 0;
        $receipt_url = $receipt_id ? wp_get_attachment_url($receipt_id) : '';

        if ($invoice_type === 'event') {
            // Add payment to event registration
            $reg = get_post($invoice_id);
            if (!$reg || $reg->post_type !== 'eau_event_reg') {
                wp_send_json_error(array('message' => __('Registration not found', 'eau-system')));
            }

            $event_id = get_post_meta($invoice_id, 'reg_event_id', true);
            $user_id = get_post_meta($invoice_id, 'reg_user_id', true);

            $payment_id = Payments_Post_Type::create_payment(array(
                'payment_type'   => 'event',
                'registration_id' => $invoice_id,
                'event_id'       => intval($event_id),
                'user_id'        => intval($user_id),
                'amount'         => $amount,
                'payment_date'   => $payment_date,
                'payment_method' => $payment_method,
                'receipt_url'    => $receipt_url,
                'receipt_id'     => $receipt_id,
                'notes'          => $notes,
                'created_by'     => get_current_user_id(),
                'status'         => 'confirmed',
            ));

            if (is_wp_error($payment_id)) {
                wp_send_json_error(array('message' => $payment_id->get_error_message()));
            }

            // Update registration payment status
            self::update_event_registration_status($invoice_id);

            // Get updated details
            $details = self::get_event_registration_details($invoice_id);

        } else {
            // Add payment to membership application
            global $wpdb;
            $table = $wpdb->prefix . 'eau_membership_applications';
            $app = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE application_id = %d", $invoice_id));

            if (!$app) {
                wp_send_json_error(array('message' => __('Application not found', 'eau-system')));
            }

            $payment_id = Payments_Post_Type::create_membership_payment(array(
                'user_id'                   => $app->created_user_id,
                'amount'                    => $amount,
                'payment_date'              => $payment_date,
                'payment_method'            => $payment_method,
                'receipt_url'               => $receipt_url,
                'receipt_id'                => $receipt_id,
                'notes'                     => $notes,
                'created_by'                => get_current_user_id(),
                'status'                    => 'confirmed',
                'membership_application_id' => $invoice_id,
                'membership_type'           => $app->membership_type,
            ));

            if (is_wp_error($payment_id)) {
                wp_send_json_error(array('message' => $payment_id->get_error_message()));
            }

            // Update user membership status if fully paid
            $details = self::get_membership_application_details($invoice_id);
            if ($details && $details['balance'] <= 0 && $app->created_user_id) {
                update_user_meta($app->created_user_id, 'mem_membership_status', 'active');
            }
        }

        wp_send_json_success(array(
            'message'    => __('Payment added successfully', 'eau-system'),
            'payment_id' => $payment_id,
            'details'    => $details,
        ));
    }

    /**
     * Atualiza status de pagamento de uma Event Registration
     *
     * @since  1.51.0
     * @param  int $registration_id
     * @return void
     */
    private static function update_event_registration_status($registration_id) {
        $event_id = get_post_meta($registration_id, 'reg_event_id', true);
        $event_price = floatval(get_post_meta($event_id, 'evt_member_price', true) ?: 0);
        $total_paid = Payments_Post_Type::get_total_paid($registration_id);

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

    /**
     * Exclui um pagamento
     *
     * @since  1.51.0
     * @return void
     */
    public static function delete_payment() {
        check_ajax_referer('eau_payments_management_nonce', 'nonce');

        if (!Eau_User_Institution_Helper::has_admin_access()) {
            wp_send_json_error(array('message' => __('Permission denied', 'eau-system')));
        }

        $payment_id = isset($_POST['payment_id']) ? intval($_POST['payment_id']) : 0;
        $invoice_id = isset($_POST['invoice_id']) ? intval($_POST['invoice_id']) : 0;
        $invoice_type = isset($_POST['invoice_type']) ? sanitize_text_field($_POST['invoice_type']) : '';

        if (!$payment_id) {
            wp_send_json_error(array('message' => __('Invalid payment ID', 'eau-system')));
        }

        $payment = get_post($payment_id);
        if (!$payment || $payment->post_type !== 'eau_payment') {
            wp_send_json_error(array('message' => __('Payment not found', 'eau-system')));
        }

        // Delete payment
        wp_trash_post($payment_id);

        // Update invoice status
        if ($invoice_type === 'event' && $invoice_id) {
            self::update_event_registration_status($invoice_id);
            $details = self::get_event_registration_details($invoice_id);
        } else if ($invoice_type === 'membership' && $invoice_id) {
            $details = self::get_membership_application_details($invoice_id);
        } else {
            $details = null;
        }

        wp_send_json_success(array(
            'message' => __('Payment deleted successfully', 'eau-system'),
            'details' => $details,
        ));
    }

    /**
     * Obtém estatísticas das faturas
     *
     * @since  1.51.0
     * @return void
     */
    public static function get_stats() {
        check_ajax_referer('eau_payments_management_nonce', 'nonce');

        if (!Eau_User_Institution_Helper::has_admin_access()) {
            wp_send_json_error(array('message' => __('Permission denied', 'eau-system')));
        }

        // Get all invoices to calculate stats
        $event_invoices = self::get_event_registrations('', '');
        $membership_invoices = self::get_membership_applications('', '');

        $total_due = 0;
        $total_paid = 0;
        $pending_count = 0;
        $paid_count = 0;

        $all_invoices = array_merge($event_invoices, $membership_invoices);

        foreach ($all_invoices as $invoice) {
            $total_due += $invoice['amount_due'];
            $total_paid += $invoice['amount_paid'];

            if ($invoice['payment_status'] === 'pending' || $invoice['payment_status'] === 'partial') {
                $pending_count++;
            } elseif ($invoice['payment_status'] === 'paid') {
                $paid_count++;
            }
        }

        wp_send_json_success(array(
            'total_due'       => $total_due,
            'total_paid'      => $total_paid,
            'total_balance'   => $total_due - $total_paid,
            'event_count'     => count($event_invoices),
            'membership_count' => count($membership_invoices),
            'pending_count'   => $pending_count,
            'paid_count'      => $paid_count,
        ));
    }

    /**
     * Exporta faturas para CSV
     *
     * @since  1.51.0
     * @return void
     */
    public static function export_csv() {
        check_ajax_referer('eau_payments_management_nonce', 'nonce');

        if (!Eau_User_Institution_Helper::has_admin_access()) {
            wp_send_json_error(array('message' => __('Permission denied', 'eau-system')));
        }

        $invoice_type = isset($_POST['invoice_type']) ? sanitize_text_field($_POST['invoice_type']) : '';
        $payment_status = isset($_POST['payment_status']) ? sanitize_text_field($_POST['payment_status']) : '';
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';

        $invoices = array();

        if (empty($invoice_type) || $invoice_type === 'event') {
            $invoices = array_merge($invoices, self::get_event_registrations($search, $payment_status));
        }
        if (empty($invoice_type) || $invoice_type === 'membership') {
            $invoices = array_merge($invoices, self::get_membership_applications($search, $payment_status));
        }

        // Build CSV
        $csv_lines = array();
        $csv_lines[] = array('Type', 'Member Name', 'Email', 'Reference', 'Amount Due', 'Amount Paid', 'Balance', 'Status', 'Date');

        foreach ($invoices as $inv) {
            $csv_lines[] = array(
                $inv['type_label'],
                $inv['member_name'],
                $inv['member_email'],
                $inv['reference'],
                number_format($inv['amount_due'], 2),
                number_format($inv['amount_paid'], 2),
                number_format($inv['balance'], 2),
                $inv['status_label'],
                $inv['date'],
            );
        }

        $csv = '';
        foreach ($csv_lines as $line) {
            $csv .= '"' . implode('","', array_map('addslashes', $line)) . '"' . "\n";
        }

        wp_send_json_success(array(
            'csv'      => $csv,
            'filename' => 'invoices-export-' . date('Y-m-d-His') . '.csv',
        ));
    }

    /**
     * Busca eventos para Select2
     *
     * @since  1.51.0
     * @return void
     */
    public static function search_events() {
        check_ajax_referer('eau_payments_management_nonce', 'nonce');

        if (!Eau_User_Institution_Helper::has_admin_access()) {
            wp_send_json_error(array('message' => __('Permission denied', 'eau-system')));
        }

        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';

        $args = array(
            'post_type'      => 'eau_event',
            'posts_per_page' => 20,
            'post_status'    => 'publish',
            's'              => $search,
            'orderby'        => 'date',
            'order'          => 'DESC',
        );

        $events = get_posts($args);

        $results = array();
        foreach ($events as $event) {
            $event_date = get_post_meta($event->ID, 'evt_date', true);
            $date_str = $event_date ? ' - ' . date('M j, Y', strtotime($event_date)) : '';
            $results[] = array(
                'id'   => $event->ID,
                'text' => $event->post_title . $date_str,
            );
        }

        wp_send_json_success(array('results' => $results));
    }
}
