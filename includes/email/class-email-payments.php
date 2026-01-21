<?php
/**
 * Email Payments
 *
 * Handles sending payment-related emails.
 *
 * @package EauSystem
 * @subpackage Email
 * @since 1.71.0
 */

namespace EauSystem\Email;

// Se este arquivo foi chamado diretamente, aborta.
if (!defined('WPINC')) {
    die;
}

class Email_Payments {

    /**
     * Send payment confirmation email
     *
     * @param string $type    Payment type (event, course)
     * @param int    $item_id Item ID (registration or purchase ID)
     * @param array  $data    Transaction data
     * @return bool
     */
    public static function send_payment_confirmation($type, $item_id, $data = array()) {
        $user_id = 0;
        $email = '';
        $subject = '';
        $item_name = '';
        $amount = 0;

        if ($type === 'event') {
            $user_id = get_post_meta($item_id, 'reg_user_id', true);
            $event_id = get_post_meta($item_id, 'reg_event_id', true);
            $event = get_post($event_id);
            $item_name = $event ? $event->post_title : 'Event Registration';
            $amount = (float) get_post_meta($item_id, 'reg_price_paid', true);
            $subject = sprintf(__('Payment Confirmed: %s', 'eau-system'), $item_name);
        } elseif ($type === 'course') {
            global $wpdb;
            $table_name = $wpdb->prefix . 'eau_course_purchases';
            $purchase = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name WHERE id = %d",
                $item_id
            ), ARRAY_A);

            if ($purchase) {
                $user_id = $purchase['user_id'];
                $course = get_post($purchase['course_id']);
                $item_name = $course ? $course->post_title : 'Course Enrollment';
                $amount = (float) $purchase['amount'];
            }
            $subject = sprintf(__('Course Enrollment Confirmed: %s', 'eau-system'), $item_name);
        }

        if (!$user_id) {
            return false;
        }

        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }

        $email = $user->user_email;
        $transaction_id = $data['id'] ?? '';

        // Build email content
        $content = self::build_confirmation_email($type, array(
            'user_name'      => $user->display_name,
            'item_name'      => $item_name,
            'amount'         => $amount,
            'transaction_id' => $transaction_id,
            'date'           => current_time('F j, Y'),
        ));

        return Email_Service::send($email, $subject, $content);
    }

    /**
     * Send payment failed email
     *
     * @param string $type    Payment type (event, course)
     * @param int    $item_id Item ID
     * @param array  $data    Transaction data
     * @return bool
     */
    public static function send_payment_failed($type, $item_id, $data = array()) {
        $user_id = 0;
        $email = '';
        $item_name = '';
        $amount = 0;

        if ($type === 'event') {
            $user_id = get_post_meta($item_id, 'reg_user_id', true);
            $event_id = get_post_meta($item_id, 'reg_event_id', true);
            $event = get_post($event_id);
            $item_name = $event ? $event->post_title : 'Event Registration';
            $amount = (float) get_post_meta($item_id, 'reg_price_paid', true);
        } elseif ($type === 'course') {
            global $wpdb;
            $table_name = $wpdb->prefix . 'eau_course_purchases';
            $purchase = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name WHERE id = %d",
                $item_id
            ), ARRAY_A);

            if ($purchase) {
                $user_id = $purchase['user_id'];
                $course = get_post($purchase['course_id']);
                $item_name = $course ? $course->post_title : 'Course Enrollment';
                $amount = (float) $purchase['amount'];
            }
        }

        if (!$user_id) {
            return false;
        }

        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }

        $email = $user->user_email;
        $subject = __('Payment Failed - Action Required', 'eau-system');
        $error_message = $data['message'] ?? __('There was a problem processing your payment.', 'eau-system');

        // Get checkout URL
        $checkout_url = self::get_checkout_url($type, $item_id);

        // Build email content
        $content = self::build_failed_email(array(
            'user_name'     => $user->display_name,
            'item_name'     => $item_name,
            'amount'        => $amount,
            'error_message' => $error_message,
            'checkout_url'  => $checkout_url,
        ));

        return Email_Service::send($email, $subject, $content);
    }

    /**
     * Send payment reminder email
     *
     * @param string $type    Payment type (event, course)
     * @param int    $item_id Item ID
     * @param bool   $urgent  Whether this is an urgent reminder
     * @return bool
     */
    public static function send_payment_reminder($type, $item_id, $urgent = false) {
        $user_id = 0;
        $item_name = '';
        $amount = 0;

        if ($type === 'event') {
            $user_id = get_post_meta($item_id, 'reg_user_id', true);
            $event_id = get_post_meta($item_id, 'reg_event_id', true);
            $event = get_post($event_id);
            $item_name = $event ? $event->post_title : 'Event Registration';
            $amount = (float) get_post_meta($item_id, 'reg_price_paid', true);
        } elseif ($type === 'course') {
            global $wpdb;
            $table_name = $wpdb->prefix . 'eau_course_purchases';
            $purchase = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name WHERE id = %d",
                $item_id
            ), ARRAY_A);

            if ($purchase) {
                $user_id = $purchase['user_id'];
                $course = get_post($purchase['course_id']);
                $item_name = $course ? $course->post_title : 'Course Enrollment';
                $amount = (float) $purchase['amount'];
            }
        }

        if (!$user_id) {
            return false;
        }

        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }

        $email = $user->user_email;
        $subject = $urgent
            ? __('Urgent: Complete Your Payment', 'eau-system')
            : __('Reminder: Complete Your Payment', 'eau-system');

        $checkout_url = self::get_checkout_url($type, $item_id);

        // Build email content
        $content = self::build_reminder_email(array(
            'user_name'    => $user->display_name,
            'item_name'    => $item_name,
            'amount'       => $amount,
            'checkout_url' => $checkout_url,
            'urgent'       => $urgent,
        ));

        return Email_Service::send($email, $subject, $content);
    }

    /**
     * Send refund confirmation email
     *
     * @param int $payment_id Payment post ID
     * @return bool
     */
    public static function send_refund_confirmation($payment_id) {
        $user_id = get_post_meta($payment_id, 'pay_user_id', true);

        if (!$user_id) {
            return false;
        }

        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }

        $amount = get_post_meta($payment_id, 'pay_refund_amount', true);
        $type = get_post_meta($payment_id, 'pay_type', true);
        $item_id = get_post_meta($payment_id, 'pay_item_id', true);

        // Get item name
        $item_name = 'Payment';
        if ($type === 'event') {
            $event_id = get_post_meta($item_id, 'reg_event_id', true);
            $event = get_post($event_id);
            $item_name = $event ? $event->post_title : 'Event';
        } elseif ($type === 'course') {
            global $wpdb;
            $table_name = $wpdb->prefix . 'eau_course_purchases';
            $purchase = $wpdb->get_row($wpdb->prepare(
                "SELECT course_id FROM $table_name WHERE id = %d",
                $item_id
            ), ARRAY_A);

            if ($purchase) {
                $course = get_post($purchase['course_id']);
                $item_name = $course ? $course->post_title : 'Course';
            }
        }

        $email = $user->user_email;
        $subject = __('Refund Processed', 'eau-system');

        // Build email content
        $content = self::build_refund_email(array(
            'user_name' => $user->display_name,
            'item_name' => $item_name,
            'amount'    => $amount,
            'date'      => current_time('F j, Y'),
        ));

        return Email_Service::send($email, $subject, $content);
    }

    /**
     * Get checkout URL for an item
     *
     * @param string $type    Payment type
     * @param int    $item_id Item ID
     * @return string
     */
    private static function get_checkout_url($type, $item_id) {
        $checkout_page_id = \EauSystem\Eau_Pages::get_page_id('checkout');
        $base_url = $checkout_page_id ? get_permalink($checkout_page_id) : home_url('/checkout/');

        if ($type === 'event') {
            return add_query_arg(array('type' => 'event', 'reg_id' => $item_id), $base_url);
        } elseif ($type === 'course') {
            return add_query_arg(array('type' => 'course', 'purchase_id' => $item_id), $base_url);
        }

        return $base_url;
    }

    /**
     * Build payment confirmation email HTML
     *
     * @param string $type Payment type
     * @param array  $data Email data
     * @return string
     */
    private static function build_confirmation_email($type, $data) {
        $item_type = $type === 'event' ? 'Event Registration' : 'Course Enrollment';

        $html = '<h2>Payment Confirmed</h2>';
        $html .= '<p>Dear ' . esc_html($data['user_name']) . ',</p>';
        $html .= '<p>Thank you! Your payment has been successfully processed.</p>';

        $html .= Email_Template::info_box(array(
            array('label' => $item_type, 'value' => $data['item_name']),
            array('label' => 'Amount', 'value' => '$' . number_format($data['amount'], 2)),
            array('label' => 'Date', 'value' => $data['date']),
        ));

        if (!empty($data['transaction_id'])) {
            $html .= '<p style="color: #6b7280; font-size: 0.875rem;">Transaction ID: ' . esc_html($data['transaction_id']) . '</p>';
        }

        if ($type === 'event') {
            $html .= '<p>You will receive a separate email with event details and any additional information.</p>';
        } else {
            $html .= '<p>You can now access your course through your dashboard.</p>';
            $html .= Email_Template::button(home_url('/dashboard/courses/'), 'Access Your Courses');
        }

        $html .= '<p>Thank you for your payment.<br>English Australia</p>';

        return $html;
    }

    /**
     * Build payment failed email HTML
     *
     * @param array $data Email data
     * @return string
     */
    private static function build_failed_email($data) {
        $html = '<h2>Payment Failed</h2>';
        $html .= '<p>Dear ' . esc_html($data['user_name']) . ',</p>';
        $html .= '<p>Unfortunately, we were unable to process your payment.</p>';

        $html .= Email_Template::info_box(array(
            array('label' => 'Item', 'value' => $data['item_name']),
            array('label' => 'Amount', 'value' => '$' . number_format($data['amount'], 2)),
            array('label' => 'Error', 'value' => $data['error_message']),
        ));

        $html .= '<p>Please try again using the button below:</p>';
        $html .= Email_Template::button($data['checkout_url'], 'Retry Payment');

        $html .= '<p>If you continue to experience issues, please contact our support team.</p>';
        $html .= '<p>English Australia</p>';

        return $html;
    }

    /**
     * Build payment reminder email HTML
     *
     * @param array $data Email data
     * @return string
     */
    private static function build_reminder_email($data) {
        $html = $data['urgent']
            ? '<h2 style="color: #dc2626;">Urgent: Payment Required</h2>'
            : '<h2>Payment Reminder</h2>';

        $html .= '<p>Dear ' . esc_html($data['user_name']) . ',</p>';

        if ($data['urgent']) {
            $html .= '<p><strong>This is a final reminder.</strong> Your registration requires payment to be confirmed.</p>';
        } else {
            $html .= '<p>This is a friendly reminder that your registration payment is still pending.</p>';
        }

        $html .= Email_Template::info_box(array(
            array('label' => 'Item', 'value' => $data['item_name']),
            array('label' => 'Amount Due', 'value' => '$' . number_format($data['amount'], 2)),
        ));

        $html .= '<p>Please complete your payment using the button below:</p>';
        $html .= Email_Template::button($data['checkout_url'], 'Complete Payment');

        if ($data['urgent']) {
            $html .= '<p style="color: #dc2626;"><strong>Note:</strong> Unpaid registrations may be cancelled.</p>';
        }

        $html .= '<p>If you have any questions, please contact us.</p>';
        $html .= '<p>English Australia</p>';

        return $html;
    }

    /**
     * Build refund confirmation email HTML
     *
     * @param array $data Email data
     * @return string
     */
    private static function build_refund_email($data) {
        $html = '<h2>Refund Processed</h2>';
        $html .= '<p>Dear ' . esc_html($data['user_name']) . ',</p>';
        $html .= '<p>Your refund has been processed successfully.</p>';

        $html .= Email_Template::info_box(array(
            array('label' => 'Item', 'value' => $data['item_name']),
            array('label' => 'Refund Amount', 'value' => '$' . number_format($data['amount'], 2)),
            array('label' => 'Date', 'value' => $data['date']),
        ));

        $html .= '<p>The refund will be credited to your original payment method within 5-10 business days, depending on your bank.</p>';
        $html .= '<p>If you have any questions, please don\'t hesitate to contact us.</p>';
        $html .= '<p>English Australia</p>';

        return $html;
    }

    /**
     * Register hooks for payment emails
     */
    public static function register_hooks() {
        // Hook into webhook events
        add_action('eau_send_payment_confirmation', array(__CLASS__, 'send_payment_confirmation'), 10, 3);
        add_action('eau_send_payment_failed', array(__CLASS__, 'send_payment_failed'), 10, 3);
        add_action('eau_send_refund_confirmation', array(__CLASS__, 'send_refund_confirmation'), 10, 1);
    }
}
