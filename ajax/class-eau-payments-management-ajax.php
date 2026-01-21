<?php
/**
 * Payments Management AJAX Handlers
 *
 * Refatorado para mostrar FATURAS (registrations/purchases) ao invés de pagamentos.
 * Segue o padrão de Event Registrations onde a tabela mostra inscrições/purchases
 * pendentes de pagamento, e o admin pode adicionar pagamentos a cada fatura.
 *
 * @package    EauSystem
 * @subpackage Ajax
 * @since      1.50.1
 * @updated    1.51.0 - Refatorado para mostrar faturas
 * @updated    1.71.0 - Adicionado suporte para cursos, removido membership UI
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
     * @since  1.53.0 Adicionados handlers para importação de CSV
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

        // CSV Import handlers (v1.53.0)
        add_action('wp_ajax_eau_upload_import_csv', array(__CLASS__, 'upload_import_csv'));
        add_action('wp_ajax_eau_preview_import_csv', array(__CLASS__, 'preview_import_csv'));
        add_action('wp_ajax_eau_execute_import_csv', array(__CLASS__, 'execute_import_csv'));
    }

    /**
     * Lista todas as faturas (registrations + applications + legacy payments)
     *
     * @since  1.51.0
     * @since  1.53.1 Adicionado suporte para pagamentos legacy importados
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

        // Get Event Registrations (if not filtered to course only)
        if (empty($invoice_type) || $invoice_type === 'event') {
            $event_invoices = self::get_event_registrations($search, $payment_status);
            $invoices = array_merge($invoices, $event_invoices);

            // Also get imported event payments (v1.53.2)
            $imported_events = self::get_imported_payments($search, $payment_status, 'event');
            $invoices = array_merge($invoices, $imported_events);
        }

        // Get Course Purchases (v1.71.0)
        if (empty($invoice_type) || $invoice_type === 'course') {
            $course_invoices = self::get_course_purchases($search, $payment_status);
            $invoices = array_merge($invoices, $course_invoices);
        }

        // Get Membership Applications (historical data, kept for backwards compatibility)
        // Only show if no filter or if specifically filtering for membership (legacy)
        if (empty($invoice_type)) {
            $membership_invoices = self::get_membership_applications($search, $payment_status);
            $invoices = array_merge($invoices, $membership_invoices);

            // Also get imported membership payments (v1.53.2)
            $imported_membership = self::get_imported_payments($search, $payment_status, 'membership');
            $invoices = array_merge($invoices, $imported_membership);

            // Also get payments with 'legacy' type (didn't match event/membership patterns)
            $imported_legacy = self::get_imported_payments($search, $payment_status, 'legacy');
            $invoices = array_merge($invoices, $imported_legacy);
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
     * Busca Course Purchases como faturas (v1.71.0)
     *
     * @since  1.71.0
     * @param  string $search Search term
     * @param  string $payment_status Filter by payment status
     * @return array
     */
    private static function get_course_purchases($search = '', $payment_status = '') {
        global $wpdb;

        $invoices = array();
        $table = $wpdb->prefix . 'eau_course_purchases';

        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return $invoices;
        }

        $where = "WHERE 1=1";

        if (!empty($search)) {
            $search_like = '%' . $wpdb->esc_like($search) . '%';
            // We'll filter by user info after getting results
        }

        $purchases = $wpdb->get_results("SELECT * FROM $table $where ORDER BY created_at DESC", ARRAY_A);

        foreach ($purchases as $purchase) {
            $user = get_userdata($purchase['user_id']);
            $user_name = $user ? $user->display_name : __('Unknown User', 'eau-system');
            $user_email = $user ? $user->user_email : '';

            // Apply search filter
            if (!empty($search)) {
                $search_lower = strtolower($search);
                if (
                    strpos(strtolower($user_name), $search_lower) === false &&
                    strpos(strtolower($user_email), $search_lower) === false
                ) {
                    continue;
                }
            }

            // Get course info
            $course = get_post($purchase['course_id']);
            $course_title = $course ? $course->post_title : __('Unknown Course', 'eau-system');
            $amount = floatval($purchase['amount']);
            $status = $purchase['status'];

            // Map status to payment status
            $pay_status = $status;
            if ($status === 'paid') {
                $pay_status = 'paid';
                $total_paid = $amount;
            } elseif ($status === 'pending' || $status === 'processing') {
                $pay_status = 'pending';
                $total_paid = 0;
            } elseif ($status === 'failed') {
                $pay_status = 'pending'; // Show as pending so they can retry
                $total_paid = 0;
            } else {
                $total_paid = 0;
            }

            $balance = max(0, $amount - $total_paid);

            // Filter by payment status if specified
            if (!empty($payment_status) && $pay_status !== $payment_status) {
                continue;
            }

            $status_label = self::get_payment_status_label($pay_status);
            $status_class = self::get_payment_status_class($pay_status);

            $invoices[] = array(
                'id'              => $purchase['id'],
                'invoice_type'    => 'course',
                'type_label'      => __('Course', 'eau-system'),
                'type_class'      => 'eau-badge-blue',
                'member_name'     => esc_html($user_name),
                'member_email'    => esc_html($user_email),
                'user_id'         => $purchase['user_id'],
                'reference'       => esc_html($course_title),
                'reference_id'    => $purchase['course_id'],
                'amount_due'      => $amount,
                'amount_due_fmt'  => '$' . number_format($amount, 2),
                'amount_paid'     => $total_paid,
                'amount_paid_fmt' => '$' . number_format($total_paid, 2),
                'balance'         => $balance,
                'balance_fmt'     => '$' . number_format($balance, 2),
                'payment_status'  => $pay_status,
                'status_label'    => $status_label,
                'status_class'    => $status_class,
                'date'            => date('M j, Y', strtotime($purchase['created_at'])),
                'date_raw'        => $purchase['created_at'],
                'transaction_id'  => $purchase['transaction_id'],
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
     * Busca pagamentos importados de CSV (identificados por legacy_order_no)
     *
     * Pagamentos importados aparecem como seus tipos detectados (Event/Membership),
     * não como um tipo separado "Legacy".
     *
     * @since  1.53.1
     * @since  1.53.2 Corrigido para identificar por legacy_order_no e mostrar tipo correto
     * @param  string $search Search term
     * @param  string $payment_status Filter by payment status (sempre 'paid' para importados)
     * @param  string $type_filter Filter by type ('event', 'membership', or empty for all)
     * @return array
     */
    private static function get_imported_payments($search = '', $payment_status = '', $type_filter = '') {
        $invoices = array();

        // Imported payments are always 'paid' since they're historical records
        // If filtering for other statuses, return empty
        if (!empty($payment_status) && $payment_status !== 'paid') {
            return $invoices;
        }

        $prefix = Payments_Post_Type::META_PREFIX;

        // Query for imported payments - identified by having legacy_order_no
        $meta_query = array(
            array(
                'key'     => $prefix . 'legacy_order_no',
                'value'   => '',
                'compare' => '!=',
            ),
        );

        // Filter by payment_type if specified
        if (!empty($type_filter)) {
            $meta_query[] = array(
                'key'     => $prefix . 'payment_type',
                'value'   => $type_filter,
                'compare' => '=',
            );
        }

        $args = array(
            'post_type'      => 'eau_payment',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => $meta_query,
        );

        $payments = get_posts($args);

        foreach ($payments as $payment) {
            $payment_id = $payment->ID;
            $payer_name = get_post_meta($payment_id, $prefix . 'payer_name', true);
            $payer_email = get_post_meta($payment_id, $prefix . 'payer_email', true);
            $amount = floatval(get_post_meta($payment_id, $prefix . 'amount', true));
            $payment_date = get_post_meta($payment_id, $prefix . 'payment_date', true);
            $user_id = get_post_meta($payment_id, $prefix . 'user_id', true);
            $legacy_order_no = get_post_meta($payment_id, $prefix . 'legacy_order_no', true);
            $legacy_description = get_post_meta($payment_id, $prefix . 'legacy_description', true);
            $notes = get_post_meta($payment_id, $prefix . 'notes', true);
            $payment_type = get_post_meta($payment_id, $prefix . 'payment_type', true) ?: 'membership';

            // Apply search filter
            if (!empty($search)) {
                $search_lower = strtolower($search);
                if (
                    strpos(strtolower($payer_name), $search_lower) === false &&
                    strpos(strtolower($payer_email), $search_lower) === false &&
                    strpos(strtolower($legacy_order_no), $search_lower) === false &&
                    strpos(strtolower($legacy_description), $search_lower) === false &&
                    strpos(strtolower($notes), $search_lower) === false
                ) {
                    continue;
                }
            }

            // Reference is the first line of description or notes
            $reference = !empty($notes) ? $notes : (!empty($legacy_description) ? explode("\n", $legacy_description)[0] : 'Order #' . $legacy_order_no);

            // Truncate reference if too long
            if (strlen($reference) > 60) {
                $reference = substr($reference, 0, 57) . '...';
            }

            // Determine type label and class based on payment_type (not hardcoded as Legacy)
            if ($payment_type === 'event') {
                $type_label = __('Event', 'eau-system');
                $type_class = 'eau-badge-info';
                $invoice_type = 'event';
            } else {
                // Default to Membership for membership and legacy types
                $type_label = __('Membership', 'eau-system');
                $type_class = 'eau-badge-purple';
                $invoice_type = 'membership';
            }

            $invoices[] = array(
                'id'              => $payment_id,
                'invoice_type'    => $invoice_type,
                'type_label'      => $type_label,
                'type_class'      => $type_class,
                'member_name'     => esc_html($payer_name),
                'member_email'    => esc_html($payer_email),
                'user_id'         => intval($user_id),
                'reference'       => esc_html($reference),
                'reference_id'    => $legacy_order_no,
                'amount_due'      => $amount,
                'amount_due_fmt'  => '$' . number_format($amount, 2),
                'amount_paid'     => $amount, // Imported payments are fully paid
                'amount_paid_fmt' => '$' . number_format($amount, 2),
                'balance'         => 0,
                'balance_fmt'     => '$0.00',
                'payment_status'  => 'paid',
                'status_label'    => __('Paid', 'eau-system'),
                'status_class'    => 'eau-badge-success',
                'date'            => !empty($payment_date) ? date('M j, Y', strtotime($payment_date)) : date('M j, Y', strtotime($payment->post_date)),
                'date_raw'        => !empty($payment_date) ? $payment_date : $payment->post_date,
                'is_imported'     => true, // Flag to identify imported payments if needed
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
     * @since  1.53.4 Adicionado suporte para pagamentos importados
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

        // Check if this is an imported payment (eau_payment post with legacy_order_no)
        $post = get_post($invoice_id);
        if ($post && $post->post_type === 'eau_payment') {
            $legacy_order_no = get_post_meta($invoice_id, Payments_Post_Type::META_PREFIX . 'legacy_order_no', true);
            if (!empty($legacy_order_no)) {
                $details = self::get_imported_payment_details($invoice_id);
                if ($details) {
                    wp_send_json_success($details);
                }
            }
        }

        // Standard flow for event registrations and membership applications
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
     * Obtém detalhes de um pagamento importado (legacy)
     *
     * Pagamentos importados são registros históricos completos - não há "balance"
     * porque já foram pagos no sistema legado.
     *
     * @since  1.53.4
     * @param  int $payment_id ID do post eau_payment
     * @return array|null
     */
    private static function get_imported_payment_details($payment_id) {
        $payment = get_post($payment_id);
        if (!$payment || $payment->post_type !== 'eau_payment') {
            return null;
        }

        $prefix = Payments_Post_Type::META_PREFIX;

        // Get all payment meta
        $payer_name = get_post_meta($payment_id, $prefix . 'payer_name', true);
        $payer_email = get_post_meta($payment_id, $prefix . 'payer_email', true);
        $amount = floatval(get_post_meta($payment_id, $prefix . 'amount', true));
        $payment_date = get_post_meta($payment_id, $prefix . 'payment_date', true);
        $payment_method = get_post_meta($payment_id, $prefix . 'payment_method', true);
        $transaction_id = get_post_meta($payment_id, $prefix . 'transaction_id', true);
        $user_id = get_post_meta($payment_id, $prefix . 'user_id', true);
        $notes = get_post_meta($payment_id, $prefix . 'notes', true);
        $payment_type = get_post_meta($payment_id, $prefix . 'payment_type', true) ?: 'membership';

        // Legacy specific fields
        $legacy_order_no = get_post_meta($payment_id, $prefix . 'legacy_order_no', true);
        $legacy_reference = get_post_meta($payment_id, $prefix . 'legacy_reference', true);
        $legacy_description = get_post_meta($payment_id, $prefix . 'legacy_description', true);
        $card_type = get_post_meta($payment_id, $prefix . 'card_type', true);
        $tax_amount = floatval(get_post_meta($payment_id, $prefix . 'tax_amount', true));
        $subtotal_amount = floatval(get_post_meta($payment_id, $prefix . 'subtotal_amount', true));

        // Build reference string
        $reference = !empty($notes) ? $notes : (!empty($legacy_description) ? $legacy_description : 'Order #' . $legacy_order_no);

        // Determine invoice type label
        if ($payment_type === 'event') {
            $type_label = __('Event', 'eau-system');
        } else {
            $type_label = __('Membership', 'eau-system');
        }

        // Format the single payment as the "payment history"
        // For imported payments, this IS the payment - it's already been paid
        $method_labels = Payments_Post_Type::get_payment_methods();
        $formatted_payments = array(
            array(
                'id'           => $payment_id,
                'amount'       => '$' . number_format($amount, 2),
                'amount_raw'   => $amount,
                'date'         => !empty($payment_date) ? date('M j, Y', strtotime($payment_date)) : '-',
                'method'       => isset($method_labels[$payment_method]) ? $method_labels[$payment_method] : ucfirst($payment_method),
                'status'       => __('Confirmed', 'eau-system'),
                'notes'        => $notes,
                'receipt_url'  => '',
                'has_receipt'  => false,
                'is_imported'  => true,
                'transaction_id' => $transaction_id,
                'card_type'    => $card_type,
            ),
        );

        // Payment methods for the form
        $payment_methods = Payments_Post_Type::get_payment_methods();

        return array(
            'invoice_id'         => $payment_id,
            'invoice_type'       => $payment_type,
            'is_imported'        => true, // Flag para o frontend saber que é importado
            'member_name'        => $payer_name,
            'member_email'       => $payer_email,
            'user_id'            => intval($user_id),
            'reference'          => $reference,
            'reference_id'       => $legacy_order_no,
            'amount_due'         => $amount,
            'amount_due_fmt'     => '$' . number_format($amount, 2),
            'total_paid'         => $amount, // Imported = already fully paid
            'total_paid_fmt'     => '$' . number_format($amount, 2),
            'balance'            => 0,
            'balance_fmt'        => '$0.00',
            'payment_status'     => 'paid',
            'status_label'       => __('Paid', 'eau-system'),
            'date'               => !empty($payment_date) ? date('M j, Y', strtotime($payment_date)) : date('M j, Y', strtotime($payment->post_date)),
            'payments'           => $formatted_payments,
            'payment_methods'    => $payment_methods,
            // Extra imported-specific data
            'legacy_order_no'    => $legacy_order_no,
            'legacy_reference'   => $legacy_reference,
            'legacy_description' => $legacy_description,
            'transaction_id'     => $transaction_id,
            'card_type'          => $card_type,
            'tax_amount'         => $tax_amount,
            'tax_amount_fmt'     => '$' . number_format($tax_amount, 2),
            'subtotal_amount'    => $subtotal_amount,
            'subtotal_amount_fmt' => '$' . number_format($subtotal_amount, 2),
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
     * @since  1.53.1 Adicionado suporte para pagamentos legacy
     * @return void
     */
    public static function get_stats() {
        check_ajax_referer('eau_payments_management_nonce', 'nonce');

        if (!Eau_User_Institution_Helper::has_admin_access()) {
            wp_send_json_error(array('message' => __('Permission denied', 'eau-system')));
        }

        // Get all invoices to calculate stats (including imported payments)
        $event_invoices = self::get_event_registrations('', '');
        $course_invoices = self::get_course_purchases('', '');
        $membership_invoices = self::get_membership_applications('', '');

        // Get imported payments (v1.53.2)
        $imported_payments = self::get_imported_payments('', '', '');

        $total_due = 0;
        $total_paid = 0;
        $pending_count = 0;
        $paid_count = 0;

        $all_invoices = array_merge($event_invoices, $course_invoices, $membership_invoices, $imported_payments);

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
            'total_due'        => $total_due,
            'total_paid'       => $total_paid,
            'total_balance'    => $total_due - $total_paid,
            'event_count'      => count($event_invoices),
            'course_count'     => count($course_invoices),
            'membership_count' => count($membership_invoices),
            'imported_count'   => count($imported_payments),
            'pending_count'    => $pending_count,
            'paid_count'       => $paid_count,
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
            $invoices = array_merge($invoices, self::get_imported_payments($search, $payment_status, 'event'));
        }
        if (empty($invoice_type) || $invoice_type === 'membership') {
            $invoices = array_merge($invoices, self::get_membership_applications($search, $payment_status));
            $invoices = array_merge($invoices, self::get_imported_payments($search, $payment_status, 'membership'));
            $invoices = array_merge($invoices, self::get_imported_payments($search, $payment_status, 'legacy'));
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

    /**
     * Upload CSV file for import
     *
     * @since  1.53.0
     * @return void
     */
    public static function upload_import_csv() {
        check_ajax_referer('eau_payments_management_nonce', 'nonce');

        if (!Eau_User_Institution_Helper::has_admin_access()) {
            wp_send_json_error(array('message' => __('Permission denied', 'eau-system')));
        }

        if (empty($_FILES['csv_file'])) {
            wp_send_json_error(array('message' => __('No file uploaded', 'eau-system')));
        }

        $file = $_FILES['csv_file'];

        // Validate file type
        $allowed_types = array('text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel');
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $file_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        // Also check extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($extension !== 'csv') {
            wp_send_json_error(array('message' => __('Only CSV files are allowed', 'eau-system')));
        }

        // Move to upload directory
        $upload_dir = wp_upload_dir();
        $import_dir = $upload_dir['basedir'] . '/eau-imports/';

        if (!file_exists($import_dir)) {
            wp_mkdir_p($import_dir);
        }

        // Create unique filename
        $filename = 'import_' . time() . '_' . sanitize_file_name($file['name']);
        $filepath = $import_dir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            wp_send_json_error(array('message' => __('Failed to save uploaded file', 'eau-system')));
        }

        // Store filepath in transient for later use
        $import_key = 'eau_import_' . get_current_user_id() . '_' . time();
        set_transient($import_key, $filepath, HOUR_IN_SECONDS);

        wp_send_json_success(array(
            'message'    => __('File uploaded successfully', 'eau-system'),
            'import_key' => $import_key,
            'filename'   => $file['name'],
            'size'       => size_format($file['size']),
        ));
    }

    /**
     * Preview CSV import data
     *
     * @since  1.53.0
     * @return void
     */
    public static function preview_import_csv() {
        check_ajax_referer('eau_payments_management_nonce', 'nonce');

        if (!Eau_User_Institution_Helper::has_admin_access()) {
            wp_send_json_error(array('message' => __('Permission denied', 'eau-system')));
        }

        $import_key = isset($_POST['import_key']) ? sanitize_text_field($_POST['import_key']) : '';

        if (empty($import_key)) {
            wp_send_json_error(array('message' => __('Invalid import key', 'eau-system')));
        }

        $filepath = get_transient($import_key);

        if (!$filepath || !file_exists($filepath)) {
            wp_send_json_error(array('message' => __('Import file not found. Please upload again.', 'eau-system')));
        }

        // Use the importer class
        require_once EAU_SYSTEM_PLUGIN_DIR . 'includes/payments/class-payments-csv-importer.php';

        $preview = \EauSystem\Payments\Payments_CSV_Importer::get_preview($filepath, 20);

        if (is_wp_error($preview)) {
            wp_send_json_error(array('message' => $preview->get_error_message()));
        }

        // Count duplicates
        $duplicates = 0;
        foreach ($preview['preview'] as $order) {
            if (!empty($order['is_duplicate'])) {
                $duplicates++;
            }
        }

        wp_send_json_success(array(
            'total_rows'   => $preview['total_rows'],
            'total_orders' => $preview['total_orders'],
            'duplicates'   => $duplicates,
            'preview'      => $preview['preview'],
        ));
    }

    /**
     * Execute CSV import
     *
     * @since  1.53.0
     * @return void
     */
    public static function execute_import_csv() {
        check_ajax_referer('eau_payments_management_nonce', 'nonce');

        if (!Eau_User_Institution_Helper::has_admin_access()) {
            wp_send_json_error(array('message' => __('Permission denied', 'eau-system')));
        }

        $import_key = isset($_POST['import_key']) ? sanitize_text_field($_POST['import_key']) : '';

        if (empty($import_key)) {
            wp_send_json_error(array('message' => __('Invalid import key', 'eau-system')));
        }

        $filepath = get_transient($import_key);

        if (!$filepath || !file_exists($filepath)) {
            wp_send_json_error(array('message' => __('Import file not found. Please upload again.', 'eau-system')));
        }

        // Use the importer class
        require_once EAU_SYSTEM_PLUGIN_DIR . 'includes/payments/class-payments-csv-importer.php';

        $importer = new \EauSystem\Payments\Payments_CSV_Importer();
        $result = $importer->import_from_csv($filepath);

        // Delete transient and file after import
        delete_transient($import_key);

        // Optionally keep the file for audit, or delete it
        // unlink($filepath);

        wp_send_json_success(array(
            'message'            => __('Import completed', 'eau-system'),
            'total_rows'         => $result['total_rows'],
            'total_orders'       => $result['total_orders'],
            'imported'           => $result['imported'],
            'duplicates_skipped' => $result['duplicates_skipped'],
            'errors'             => $result['errors'],
            'matched_users'      => $result['matched_users'],
            'unmatched_users'    => $result['unmatched_users'],
        ));
    }
}
