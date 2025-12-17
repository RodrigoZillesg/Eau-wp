<?php
/**
 * Payment Receipt Generator
 *
 * Generates professional payment receipts in HTML format
 * that can be printed or saved as PDF.
 *
 * @package    EauSystem
 * @subpackage Payments
 * @since      1.53.5
 */

namespace EauSystem\Payments;

use EauSystem\Eau_User_Institution_Helper;
use EauSystem\Eau_Membership_Types;

if (!defined('WPINC')) {
    die;
}

class Payment_Receipt_Generator {

    /**
     * Logo path relative to plugin
     */
    const LOGO_PATH = 'includes/event-registrations/certificate/english-australia-logo-500.png';

    /**
     * Registers the AJAX endpoint
     *
     * @since 1.53.5
     */
    public static function register() {
        add_action('wp_ajax_eau_generate_payment_receipt', array(__CLASS__, 'generate_receipt'));
    }

    /**
     * Generates and outputs the payment receipt
     *
     * Supports both admin access (from Payments Management) and user access
     * (from My Payments - users can download their own receipts).
     *
     * @since 1.53.5
     * @since 1.53.8 Added support for user's own receipts via My Payments
     */
    public static function generate_receipt() {
        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_die(__('You must be logged in', 'eau-system'));
        }

        // Verify nonce - accept either admin nonce or my payments nonce
        $nonce = isset($_GET['nonce']) ? $_GET['nonce'] : '';
        $is_admin_request = wp_verify_nonce($nonce, 'eau_payments_management_nonce');
        $is_user_request = wp_verify_nonce($nonce, 'eau_my_payments_nonce');

        if (!$is_admin_request && !$is_user_request) {
            wp_die(__('Security check failed', 'eau-system'));
        }

        $invoice_id = isset($_GET['invoice_id']) ? intval($_GET['invoice_id']) : 0;
        $invoice_type = isset($_GET['invoice_type']) ? sanitize_text_field($_GET['invoice_type']) : '';

        if (!$invoice_id || !$invoice_type) {
            wp_die(__('Invalid invoice', 'eau-system'));
        }

        // Check permissions
        if ($is_admin_request) {
            // Admin request - must have admin access
            if (!Eau_User_Institution_Helper::has_admin_access()) {
                wp_die(__('Permission denied', 'eau-system'));
            }
        } else {
            // User request - must own the payment
            if (!self::user_owns_payment($invoice_id)) {
                wp_die(__('You do not have permission to view this receipt', 'eau-system'));
            }
        }

        // Get receipt data
        $data = self::get_receipt_data($invoice_id, $invoice_type);

        if (!$data) {
            wp_die(__('Receipt data not found', 'eau-system'));
        }

        // Output HTML receipt
        self::render_receipt($data);
        exit;
    }

    /**
     * Checks if the current user owns a payment
     *
     * @since 1.53.8
     * @param int $invoice_id Payment/Invoice ID
     * @return bool
     */
    private static function user_owns_payment($invoice_id) {
        $user_id = get_current_user_id();
        $user = get_userdata($user_id);
        $user_email = $user ? $user->user_email : '';
        $prefix = Payments_Post_Type::META_PREFIX;

        // Check if it's a payment post
        $post = get_post($invoice_id);
        if ($post && $post->post_type === 'eau_payment') {
            $payment_user_id = get_post_meta($invoice_id, $prefix . 'user_id', true);
            $payer_email = get_post_meta($invoice_id, $prefix . 'payer_email', true);

            if ($payment_user_id == $user_id || $payer_email === $user_email) {
                return true;
            }
        }

        // Check if it's an event registration
        if ($post && $post->post_type === 'eau_event_reg') {
            $reg_user_id = get_post_meta($invoice_id, 'reg_user_id', true);
            if ($reg_user_id == $user_id) {
                return true;
            }
        }

        // Check if it's a membership application
        global $wpdb;
        $table = $wpdb->prefix . 'eau_membership_applications';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
            $app = $wpdb->get_row($wpdb->prepare(
                "SELECT created_user_id FROM $table WHERE application_id = %d",
                $invoice_id
            ));
            if ($app && $app->created_user_id == $user_id) {
                return true;
            }
        }

        return false;
    }

    /**
     * Gets receipt data based on invoice type
     *
     * Handles three scenarios:
     * 1. eau_payment post with legacy_order_no (imported payment)
     * 2. eau_payment post without legacy_order_no (standard payment linked to registration/application)
     * 3. eau_event_reg or application ID (invoice ID directly)
     *
     * @since 1.53.5
     * @since 1.53.8 Added support for standard eau_payment posts (non-imported)
     * @param int    $invoice_id   Invoice or Payment ID
     * @param string $invoice_type Invoice type (event, membership)
     * @return array|null
     */
    private static function get_receipt_data($invoice_id, $invoice_type) {
        $prefix = Payments_Post_Type::META_PREFIX;

        // Check if this is a payment post (eau_payment)
        $post = get_post($invoice_id);
        if ($post && $post->post_type === 'eau_payment') {
            $legacy_order_no = get_post_meta($invoice_id, $prefix . 'legacy_order_no', true);

            if (!empty($legacy_order_no)) {
                // Imported payment - get data directly from payment post
                return self::get_imported_payment_data($invoice_id);
            }

            // Standard payment - get linked registration/application and payment details
            return self::get_standard_payment_data($invoice_id, $invoice_type);
        }

        // Direct registration/application ID flow
        if ($invoice_type === 'event') {
            return self::get_event_registration_data($invoice_id);
        } else {
            return self::get_membership_application_data($invoice_id);
        }
    }

    /**
     * Gets data for a standard payment (non-imported, linked to registration/application)
     *
     * @since 1.53.8
     * @param int    $payment_id   Payment post ID
     * @param string $invoice_type Invoice type (event, membership)
     * @return array|null
     */
    private static function get_standard_payment_data($payment_id, $invoice_type) {
        $payment = get_post($payment_id);
        if (!$payment || $payment->post_type !== 'eau_payment') {
            return null;
        }

        $prefix = Payments_Post_Type::META_PREFIX;

        // Get payment details
        $amount = floatval(get_post_meta($payment_id, $prefix . 'amount', true));
        $payment_date = get_post_meta($payment_id, $prefix . 'payment_date', true);
        $payment_method = get_post_meta($payment_id, $prefix . 'payment_method', true);
        $transaction_id = get_post_meta($payment_id, $prefix . 'transaction_id', true);
        $notes = get_post_meta($payment_id, $prefix . 'notes', true);

        // Get linked registration or application
        $registration_id = get_post_meta($payment_id, $prefix . 'registration_id', true);
        $application_id = get_post_meta($payment_id, $prefix . 'application_id', true);

        // Get payer info
        $payer_name = '';
        $payer_email = '';
        $description = '';

        if ($invoice_type === 'event' && !empty($registration_id)) {
            $attendee_name = get_post_meta($registration_id, 'reg_attendee_name', true);
            $attendee_email = get_post_meta($registration_id, 'reg_attendee_email', true);
            $event_id = get_post_meta($registration_id, 'reg_event_id', true);
            $event = get_post($event_id);

            $payer_name = $attendee_name;
            $payer_email = $attendee_email;
            $description = $event ? $event->post_title : 'Event Registration';
        } elseif (!empty($application_id)) {
            global $wpdb;
            $table = $wpdb->prefix . 'eau_membership_applications';

            $app = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table WHERE application_id = %d",
                $application_id
            ));

            if ($app) {
                $user = get_userdata($app->created_user_id);
                $payer_name = $user ? $user->display_name : 'Member';
                $payer_email = $user ? $user->user_email : '';

                $type = \EauSystem\Eau_Membership_Types::get_by_key($app->membership_type);
                $description = $type ? $type->type_label . ' Membership' : $app->membership_type . ' Membership';
            }
        }

        // Fallback payer info from payment post
        if (empty($payer_name)) {
            $payer_name = get_post_meta($payment_id, $prefix . 'payer_name', true) ?: 'Member';
        }
        if (empty($payer_email)) {
            $payer_email = get_post_meta($payment_id, $prefix . 'payer_email', true) ?: '';
        }

        // Payment method label
        $method_labels = Payments_Post_Type::get_payment_methods();
        $method_label = isset($method_labels[$payment_method]) ? $method_labels[$payment_method] : ucfirst($payment_method);

        return array(
            'receipt_number'   => 'EA-' . str_pad($payment_id, 6, '0', STR_PAD_LEFT),
            'receipt_date'     => !empty($payment_date) ? date('F j, Y', strtotime($payment_date)) : date('F j, Y'),
            'payer_name'       => $payer_name,
            'payer_email'      => $payer_email,
            'description'      => !empty($notes) ? $notes : $description,
            'payment_type'     => $invoice_type === 'event' ? 'Event Registration' : 'Membership Fee',
            'subtotal'         => $amount,
            'tax'              => 0,
            'total'            => $amount,
            'payment_method'   => $method_label,
            'transaction_id'   => $transaction_id,
            'card_type'        => '',
            'order_number'     => '',
            'status'           => 'Paid',
            'is_imported'      => false,
        );
    }

    /**
     * Gets data for an imported payment
     *
     * @since 1.53.5
     * @param int $payment_id Payment post ID
     * @return array|null
     */
    private static function get_imported_payment_data($payment_id) {
        $payment = get_post($payment_id);
        if (!$payment || $payment->post_type !== 'eau_payment') {
            return null;
        }

        $prefix = Payments_Post_Type::META_PREFIX;

        $payer_name = get_post_meta($payment_id, $prefix . 'payer_name', true);
        $payer_email = get_post_meta($payment_id, $prefix . 'payer_email', true);
        $amount = floatval(get_post_meta($payment_id, $prefix . 'amount', true));
        $payment_date = get_post_meta($payment_id, $prefix . 'payment_date', true);
        $payment_method = get_post_meta($payment_id, $prefix . 'payment_method', true);
        $transaction_id = get_post_meta($payment_id, $prefix . 'transaction_id', true);
        $notes = get_post_meta($payment_id, $prefix . 'notes', true);
        $payment_type = get_post_meta($payment_id, $prefix . 'payment_type', true) ?: 'membership';

        // Legacy specific
        $legacy_order_no = get_post_meta($payment_id, $prefix . 'legacy_order_no', true);
        $legacy_description = get_post_meta($payment_id, $prefix . 'legacy_description', true);
        $card_type = get_post_meta($payment_id, $prefix . 'card_type', true);
        $tax_amount = floatval(get_post_meta($payment_id, $prefix . 'tax_amount', true));
        $subtotal_amount = floatval(get_post_meta($payment_id, $prefix . 'subtotal_amount', true));

        // Payment method label
        $method_labels = Payments_Post_Type::get_payment_methods();
        $method_label = isset($method_labels[$payment_method]) ? $method_labels[$payment_method] : ucfirst($payment_method);

        return array(
            'receipt_number'   => 'EA-' . str_pad($payment_id, 6, '0', STR_PAD_LEFT),
            'receipt_date'     => !empty($payment_date) ? date('F j, Y', strtotime($payment_date)) : date('F j, Y'),
            'payer_name'       => $payer_name,
            'payer_email'      => $payer_email,
            'description'      => !empty($notes) ? $notes : (!empty($legacy_description) ? str_replace("\n", '<br>', $legacy_description) : 'Payment'),
            'payment_type'     => $payment_type === 'event' ? 'Event Registration' : 'Membership Fee',
            'subtotal'         => $subtotal_amount > 0 ? $subtotal_amount : $amount,
            'tax'              => $tax_amount,
            'total'            => $amount,
            'payment_method'   => $method_label,
            'transaction_id'   => $transaction_id,
            'card_type'        => $card_type,
            'order_number'     => $legacy_order_no,
            'status'           => 'Paid',
            'is_imported'      => true,
        );
    }

    /**
     * Gets data for an event registration payment
     *
     * @since 1.53.5
     * @param int $registration_id Registration ID
     * @return array|null
     */
    private static function get_event_registration_data($registration_id) {
        $reg = get_post($registration_id);
        if (!$reg || $reg->post_type !== 'eau_event_reg') {
            return null;
        }

        $attendee_name = get_post_meta($registration_id, 'reg_attendee_name', true);
        $attendee_email = get_post_meta($registration_id, 'reg_attendee_email', true);
        $event_id = get_post_meta($registration_id, 'reg_event_id', true);
        $reg_date = get_post_meta($registration_id, 'reg_registration_date', true) ?: $reg->post_date;

        // Event info
        $event = get_post($event_id);
        $event_title = $event ? $event->post_title : 'Event Registration';
        $event_price = floatval(get_post_meta($event_id, 'evt_member_price', true) ?: 0);

        // Get payments
        $payments = Payments_Post_Type::get_payments_by_registration($registration_id);
        $total_paid = Payments_Post_Type::get_total_paid($registration_id);

        // Get latest payment info
        $payment_method = '';
        $transaction_id = '';
        $payment_date = $reg_date;
        if (!empty($payments)) {
            $latest = end($payments);
            $payment_method = $latest['payment_method'] ?? '';
            $transaction_id = $latest['transaction_id'] ?? '';
            $payment_date = $latest['payment_date'] ?? $reg_date;
        }

        $method_labels = Payments_Post_Type::get_payment_methods();
        $method_label = isset($method_labels[$payment_method]) ? $method_labels[$payment_method] : ucfirst($payment_method);

        return array(
            'receipt_number'   => 'EA-REG-' . str_pad($registration_id, 6, '0', STR_PAD_LEFT),
            'receipt_date'     => date('F j, Y', strtotime($payment_date)),
            'payer_name'       => $attendee_name,
            'payer_email'      => $attendee_email,
            'description'      => $event_title,
            'payment_type'     => 'Event Registration',
            'subtotal'         => $event_price,
            'tax'              => 0,
            'total'            => $total_paid,
            'payment_method'   => $method_label,
            'transaction_id'   => $transaction_id,
            'card_type'        => '',
            'order_number'     => $registration_id,
            'status'           => $total_paid >= $event_price ? 'Paid' : 'Partial',
            'is_imported'      => false,
        );
    }

    /**
     * Gets data for a membership application payment
     *
     * @since 1.53.5
     * @param int $application_id Application ID
     * @return array|null
     */
    private static function get_membership_application_data($application_id) {
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

        // Get payments
        $payments = Payments_Post_Type::get_payments_by_application($application_id);
        $total_paid = Payments_Post_Type::get_membership_total_paid_by_application($application_id);

        // Get latest payment info
        $payment_method = '';
        $transaction_id = '';
        $payment_date = $app->submitted_at;
        if (!empty($payments)) {
            $latest = end($payments);
            $payment_method = $latest['payment_method'] ?? '';
            $transaction_id = $latest['transaction_id'] ?? '';
            $payment_date = $latest['payment_date'] ?? $app->submitted_at;
        }

        $method_labels = Payments_Post_Type::get_payment_methods();
        $method_label = isset($method_labels[$payment_method]) ? $method_labels[$payment_method] : ucfirst($payment_method);

        return array(
            'receipt_number'   => 'EA-MEM-' . str_pad($application_id, 6, '0', STR_PAD_LEFT),
            'receipt_date'     => date('F j, Y', strtotime($payment_date)),
            'payer_name'       => $app->first_name . ' ' . $app->last_name,
            'payer_email'      => $app->email,
            'description'      => $type_label . ' Membership',
            'payment_type'     => 'Membership Fee',
            'subtotal'         => $fee,
            'tax'              => 0,
            'total'            => $total_paid,
            'payment_method'   => $method_label,
            'transaction_id'   => $transaction_id,
            'card_type'        => '',
            'order_number'     => $application_id,
            'status'           => $total_paid >= $fee ? 'Paid' : 'Partial',
            'is_imported'      => false,
        );
    }

    /**
     * Renders the receipt HTML
     *
     * @since 1.53.5
     * @param array $data Receipt data
     */
    private static function render_receipt($data) {
        $logo_url = EAU_SYSTEM_PLUGIN_URL . self::LOGO_PATH;

        // Get organization info (could be from settings in future)
        $org_name = 'English Australia';
        $org_address = 'PO Box 1437';
        $org_city = 'Darlinghurst NSW 1300';
        $org_country = 'Australia';
        $org_abn = 'ABN: 86 003 959 218';
        $org_phone = '+61 2 9264 3615';
        $org_email = 'info@englishaustralia.com.au';
        $org_website = 'www.englishaustralia.com.au';

        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Receipt - <?php echo esc_html($data['receipt_number']); ?></title>
    <style>
        /* Reset and Base */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            font-size: 14px;
            line-height: 1.5;
            color: #1e293b;
            background: #f8fafc;
            padding: 20px;
        }

        /* Receipt Container */
        .receipt-container {
            max-width: 800px;
            margin: 0 auto;
            background: #ffffff;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            border-radius: 8px;
            overflow: hidden;
        }

        /* Header */
        .receipt-header {
            background: #ffffff;
            border-top: 5px solid #1e40af;
            padding: 30px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #e2e8f0;
        }

        .receipt-logo img {
            max-height: 80px;
            width: auto;
        }

        .receipt-title {
            text-align: right;
        }

        .receipt-title h1 {
            font-size: 26px;
            font-weight: 700;
            margin-bottom: 5px;
            letter-spacing: 1px;
            color: #1e40af;
        }

        .receipt-number {
            font-size: 14px;
            color: #64748b;
            font-weight: 500;
        }

        /* Body */
        .receipt-body {
            padding: 40px;
        }

        /* Info Grid */
        .receipt-info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .receipt-info-section h3 {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .receipt-info-section p {
            margin: 3px 0;
            color: #334155;
        }

        .receipt-info-section .name {
            font-weight: 600;
            font-size: 16px;
            color: #1e293b;
        }

        /* Details Table */
        .receipt-details {
            margin: 30px 0;
        }

        .receipt-details table {
            width: 100%;
            border-collapse: collapse;
        }

        .receipt-details th {
            background: #f1f5f9;
            padding: 12px 15px;
            text-align: left;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            font-weight: 600;
            border-bottom: 2px solid #e2e8f0;
        }

        .receipt-details th:last-child {
            text-align: right;
        }

        .receipt-details td {
            padding: 15px;
            border-bottom: 1px solid #e2e8f0;
        }

        .receipt-details td:last-child {
            text-align: right;
            font-family: 'SFMono-Regular', Consolas, monospace;
        }

        .receipt-details .description {
            font-weight: 500;
        }

        .receipt-details .type-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
            background: #dbeafe;
            color: #1e40af;
            margin-top: 5px;
        }

        /* Totals */
        .receipt-totals {
            margin-left: auto;
            width: 300px;
        }

        .receipt-totals table {
            width: 100%;
        }

        .receipt-totals td {
            padding: 8px 15px;
        }

        .receipt-totals td:last-child {
            text-align: right;
            font-family: 'SFMono-Regular', Consolas, monospace;
        }

        .receipt-totals .total-row {
            border-top: 2px solid #1e40af;
            font-weight: 700;
            font-size: 18px;
            color: #1e40af;
        }

        .receipt-totals .total-row td {
            padding-top: 15px;
        }

        /* Payment Info */
        .receipt-payment-info {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 8px;
            padding: 20px;
            margin-top: 30px;
        }

        .receipt-payment-info h3 {
            font-size: 14px;
            font-weight: 600;
            color: #166534;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .receipt-payment-info h3::before {
            content: '✓';
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 20px;
            height: 20px;
            background: #16a34a;
            color: white;
            border-radius: 50%;
            font-size: 12px;
        }

        .receipt-payment-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
        }

        .receipt-payment-detail {
            display: flex;
            flex-direction: column;
        }

        .receipt-payment-detail .label {
            font-size: 11px;
            text-transform: uppercase;
            color: #64748b;
            margin-bottom: 3px;
        }

        .receipt-payment-detail .value {
            font-weight: 500;
            color: #1e293b;
        }

        /* Footer */
        .receipt-footer {
            background: #f8fafc;
            padding: 25px 40px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            color: #64748b;
        }

        .receipt-footer-org {
            line-height: 1.8;
        }

        .receipt-footer-note {
            text-align: right;
            font-style: italic;
        }

        /* Actions Bar */
        .receipt-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            padding: 20px;
            background: #f8fafc;
        }

        .receipt-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
        }

        .receipt-btn-primary {
            background: #2563eb;
            color: white;
        }

        .receipt-btn-primary:hover {
            background: #1d4ed8;
        }

        .receipt-btn-secondary {
            background: white;
            color: #374151;
            border: 1px solid #d1d5db;
        }

        .receipt-btn-secondary:hover {
            background: #f9fafb;
        }

        /* Print Styles */
        @media print {
            body {
                background: white;
                padding: 0;
            }

            .receipt-container {
                box-shadow: none;
                max-width: 100%;
                border-radius: 0;
            }

            .receipt-actions {
                display: none;
            }

            .receipt-header {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                border-top: 5px solid #1e40af !important;
            }

            .receipt-title h1 {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                color: #1e40af !important;
            }

            .receipt-payment-info {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .receipt-details .type-badge {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>
    <div class="receipt-actions">
        <button class="receipt-btn receipt-btn-secondary" onclick="window.close()">
            Close
        </button>
        <button class="receipt-btn receipt-btn-primary" onclick="window.print()">
            Print / Save as PDF
        </button>
    </div>

    <div class="receipt-container">
        <!-- Header -->
        <div class="receipt-header">
            <div class="receipt-logo">
                <img src="<?php echo esc_url($logo_url); ?>" alt="English Australia">
            </div>
            <div class="receipt-title">
                <h1>PAYMENT RECEIPT</h1>
                <div class="receipt-number"><?php echo esc_html($data['receipt_number']); ?></div>
            </div>
        </div>

        <!-- Body -->
        <div class="receipt-body">
            <!-- Info Grid -->
            <div class="receipt-info-grid">
                <div class="receipt-info-section">
                    <h3>Receipt Issued To</h3>
                    <p class="name"><?php echo esc_html($data['payer_name']); ?></p>
                    <p><?php echo esc_html($data['payer_email']); ?></p>
                </div>
                <div class="receipt-info-section" style="text-align: right;">
                    <h3>Receipt Date</h3>
                    <p class="name"><?php echo esc_html($data['receipt_date']); ?></p>
                    <?php if (!empty($data['order_number'])): ?>
                    <p style="margin-top: 10px;">
                        <span style="color: #64748b;">Order #:</span>
                        <strong><?php echo esc_html($data['order_number']); ?></strong>
                    </p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Details Table -->
            <div class="receipt-details">
                <table>
                    <thead>
                        <tr>
                            <th>Description</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <div class="description"><?php echo $data['description']; ?></div>
                                <span class="type-badge"><?php echo esc_html($data['payment_type']); ?></span>
                            </td>
                            <td>$<?php echo number_format($data['subtotal'], 2); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Totals -->
            <div class="receipt-totals">
                <table>
                    <tr>
                        <td>Subtotal</td>
                        <td>$<?php echo number_format($data['subtotal'], 2); ?></td>
                    </tr>
                    <?php if ($data['tax'] > 0): ?>
                    <tr>
                        <td>Tax (GST)</td>
                        <td>$<?php echo number_format($data['tax'], 2); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr class="total-row">
                        <td>Total Paid</td>
                        <td>$<?php echo number_format($data['total'], 2); ?></td>
                    </tr>
                </table>
            </div>

            <!-- Payment Info -->
            <div class="receipt-payment-info">
                <h3>Payment Confirmed</h3>
                <div class="receipt-payment-details">
                    <?php if (!empty($data['payment_method'])): ?>
                    <div class="receipt-payment-detail">
                        <span class="label">Payment Method</span>
                        <span class="value"><?php echo esc_html($data['payment_method']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($data['card_type'])): ?>
                    <div class="receipt-payment-detail">
                        <span class="label">Card Type</span>
                        <span class="value"><?php echo esc_html($data['card_type']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($data['transaction_id'])): ?>
                    <div class="receipt-payment-detail">
                        <span class="label">Transaction ID</span>
                        <span class="value"><?php echo esc_html($data['transaction_id']); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="receipt-payment-detail">
                        <span class="label">Status</span>
                        <span class="value"><?php echo esc_html($data['status']); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="receipt-footer">
            <div class="receipt-footer-org">
                <strong><?php echo esc_html($org_name); ?></strong><br>
                <?php echo esc_html($org_address); ?><br>
                <?php echo esc_html($org_city); ?>, <?php echo esc_html($org_country); ?><br>
                <?php echo esc_html($org_abn); ?>
            </div>
            <div class="receipt-footer-note">
                Thank you for your payment.<br>
                This receipt was generated electronically<br>
                and is valid without signature.
            </div>
        </div>
    </div>

    <script>
        // Auto-focus print button
        document.querySelector('.receipt-btn-primary').focus();
    </script>
</body>
</html>
        <?php
    }
}
