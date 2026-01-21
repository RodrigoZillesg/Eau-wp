<?php
/**
 * Checkout Page
 *
 * Página unificada de checkout para eventos e cursos.
 * Shortcode: [eau_checkout]
 *
 * @package EauSystem
 * @subpackage Checkout
 * @since 1.70.0
 */

namespace EauSystem\Checkout;

use EauSystem\Components\Eau_Access_Denied;
use EauSystem\FatZebra\FatZebra;
use EauSystem\FatZebra\FatZebra_Gateway;
use EauSystem\FatZebra\FatZebra_Settings;
use EauSystem\FatZebra\FatZebra_Logger;

// Se este arquivo foi chamado diretamente, aborta.
if (!defined('WPINC')) {
    die;
}

class Eau_Checkout_Page {

    /**
     * Register shortcode
     */
    public static function register_shortcode() {
        add_shortcode('eau_checkout', array(__CLASS__, 'render'));
    }

    /**
     * Render checkout page
     *
     * @param array $atts Shortcode attributes
     * @return string
     */
    public static function render($atts = array()) {
        // Verifica se usuário está logado
        if (!is_user_logged_in()) {
            return Eau_Access_Denied::not_logged_in();
        }

        // Get parameters
        $type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : '';
        $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';

        // Handle return from Fat Zebra
        if (!empty($status)) {
            return self::render_status_page($status, $type);
        }

        // Handle checkout based on type
        switch ($type) {
            case 'event':
                return self::render_event_checkout();

            case 'course':
                return self::render_course_checkout();

            default:
                return self::render_error('Invalid checkout type');
        }
    }

    /**
     * Render event checkout
     *
     * @return string
     */
    private static function render_event_checkout() {
        $reg_id = isset($_GET['reg_id']) ? absint($_GET['reg_id']) : 0;

        if (!$reg_id) {
            return self::render_error('Registration ID is required');
        }

        // Get registration
        $registration = get_post($reg_id);

        if (!$registration || $registration->post_type !== 'eau_event_reg') {
            return self::render_error('Registration not found');
        }

        // Check ownership
        $user_id = get_post_meta($reg_id, 'reg_user_id', true);
        if ((int) $user_id !== get_current_user_id()) {
            return Eau_Access_Denied::render(
                'Access Denied',
                'You do not have permission to access this checkout.'
            );
        }

        // Check payment status
        $payment_status = get_post_meta($reg_id, 'reg_payment_status', true);
        if ($payment_status === 'paid') {
            return self::render_already_paid('event', $reg_id);
        }

        // Get event details
        $event_id = get_post_meta($reg_id, 'reg_event_id', true);
        $event = get_post($event_id);

        if (!$event) {
            return self::render_error('Event not found');
        }

        // Get pricing from registration (may include coupon discount)
        $price = (float) get_post_meta($reg_id, 'reg_price_paid', true);
        $original_price = (float) get_post_meta($reg_id, 'reg_original_price', true);
        $discount = (float) get_post_meta($reg_id, 'reg_discount_applied', true);
        $coupon_code = get_post_meta($reg_id, 'reg_coupon_code', true);

        // If no price recorded, this shouldn't require payment
        if ($price <= 0) {
            return self::render_error('This registration does not require payment');
        }

        // Enqueue assets
        self::enqueue_assets();

        // Build checkout data
        $checkout_data = array(
            'type' => 'event',
            'item_id' => $reg_id,
            'title' => $event->post_title,
            'subtitle' => self::format_event_date($event_id),
            'location' => get_post_meta($event_id, 'evt_location', true),
            'price' => $price,
            'original_price' => $original_price > 0 ? $original_price : $price,
            'discount' => $discount,
            'coupon_code' => $coupon_code,
            'currency' => 'AUD',
            'event' => $event,
            'registration' => $registration,
        );

        return self::render_checkout_form($checkout_data);
    }

    /**
     * Render course checkout
     *
     * @return string
     */
    private static function render_course_checkout() {
        $purchase_id = isset($_GET['purchase_id']) ? absint($_GET['purchase_id']) : 0;

        if (!$purchase_id) {
            return self::render_error('Purchase ID is required');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'eau_course_purchases';

        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");

        if (!$table_exists) {
            return self::render_error('Course purchases system not configured');
        }

        // Get purchase
        $purchase = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $purchase_id),
            ARRAY_A
        );

        if (!$purchase) {
            return self::render_error('Purchase not found');
        }

        // Check ownership
        if ((int) $purchase['user_id'] !== get_current_user_id()) {
            return Eau_Access_Denied::render(
                'Access Denied',
                'You do not have permission to access this checkout.'
            );
        }

        // Check payment status
        if ($purchase['status'] === 'paid') {
            return self::render_already_paid('course', $purchase_id);
        }

        // Get course details
        $course = get_post($purchase['course_id']);

        if (!$course) {
            return self::render_error('Course not found');
        }

        // Enqueue assets
        self::enqueue_assets();

        // Build checkout data
        $checkout_data = array(
            'type' => 'course',
            'item_id' => $purchase_id,
            'title' => $course->post_title,
            'subtitle' => 'Open Learning Course',
            'price' => (float) $purchase['amount'],
            'currency' => $purchase['currency'] ?? 'AUD',
            'course' => $course,
            'purchase' => $purchase,
        );

        return self::render_checkout_form($checkout_data);
    }

    /**
     * Render checkout form
     *
     * @param array $data Checkout data
     * @return string
     */
    private static function render_checkout_form($data) {
        $is_configured = FatZebra_Settings::is_configured();
        $is_sandbox = FatZebra_Settings::is_sandbox_mode();

        $user = wp_get_current_user();

        ob_start();
        ?>
        <div class="eau-checkout-container">

            <!-- Checkout Header -->
            <div class="eau-checkout-header">
                <h1 class="eau-checkout-title">
                    <i data-lucide="credit-card"></i>
                    Checkout
                </h1>
                <?php if ($is_sandbox) : ?>
                <div class="eau-checkout-sandbox-badge">
                    <i data-lucide="flask-conical"></i>
                    Test Mode
                </div>
                <?php endif; ?>
            </div>

            <div class="eau-checkout-content">

                <!-- Item Details -->
                <div class="eau-checkout-item">
                    <div class="eau-checkout-item-icon">
                        <i data-lucide="<?php echo $data['type'] === 'event' ? 'calendar' : 'book-open'; ?>"></i>
                    </div>
                    <div class="eau-checkout-item-details">
                        <span class="eau-checkout-item-type">
                            <?php echo $data['type'] === 'event' ? 'Event Registration' : 'Course Enrollment'; ?>
                        </span>
                        <h2 class="eau-checkout-item-title"><?php echo esc_html($data['title']); ?></h2>
                        <p class="eau-checkout-item-subtitle"><?php echo esc_html($data['subtitle']); ?></p>
                        <?php if (!empty($data['location'])) : ?>
                        <p class="eau-checkout-item-location">
                            <i data-lucide="map-pin"></i>
                            <?php echo esc_html($data['location']); ?>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Order Summary -->
                <div class="eau-checkout-summary">
                    <h3 class="eau-checkout-summary-title">Order Summary</h3>

                    <?php
                    $has_discount = !empty($data['discount']) && $data['discount'] > 0;
                    $original_price = $has_discount ? $data['original_price'] : $data['price'];
                    ?>

                    <div class="eau-checkout-line">
                        <span><?php echo $data['type'] === 'event' ? 'Registration Fee' : 'Course Fee'; ?></span>
                        <span>$<?php echo number_format($original_price, 2); ?></span>
                    </div>

                    <?php if ($has_discount) : ?>
                    <div class="eau-checkout-line" style="color: #10b981;">
                        <span>
                            Discount
                            <?php if (!empty($data['coupon_code'])) : ?>
                            <span style="font-size: 0.75rem; color: #6b7280;">(<?php echo esc_html($data['coupon_code']); ?>)</span>
                            <?php endif; ?>
                        </span>
                        <span>-$<?php echo number_format($data['discount'], 2); ?></span>
                    </div>
                    <?php endif; ?>

                    <div class="eau-checkout-line eau-checkout-line-total">
                        <span>Total</span>
                        <span class="eau-checkout-total">$<?php echo number_format($data['price'], 2); ?></span>
                    </div>
                </div>

                <!-- Customer Info (Read Only) -->
                <div class="eau-checkout-customer">
                    <h3 class="eau-checkout-summary-title">Your Details</h3>
                    <div class="eau-checkout-customer-info">
                        <p><strong><?php echo esc_html($user->display_name); ?></strong></p>
                        <p><?php echo esc_html($user->user_email); ?></p>
                    </div>
                </div>

                <?php if ($is_configured) : ?>
                <!-- Pay Button -->
                <div class="eau-checkout-actions">
                    <button
                        type="button"
                        class="eau-btn eau-btn-primary eau-btn-lg eau-checkout-pay-btn"
                        id="eau-pay-now-btn"
                        data-type="<?php echo esc_attr($data['type']); ?>"
                        data-item-id="<?php echo esc_attr($data['item_id']); ?>"
                        data-amount="<?php echo esc_attr(FatZebra_Gateway::dollars_to_cents($data['price'])); ?>"
                    >
                        <i data-lucide="lock"></i>
                        Pay Now - $<?php echo number_format($data['price'], 2); ?>
                    </button>

                    <p class="eau-checkout-secure-notice">
                        <i data-lucide="shield-check"></i>
                        Secure payment powered by Fat Zebra
                    </p>

                    <div class="eau-checkout-cards">
                        <span class="eau-checkout-card-icon" title="Visa">
                            <i data-lucide="credit-card"></i>
                            Visa
                        </span>
                        <span class="eau-checkout-card-icon" title="Mastercard">
                            <i data-lucide="credit-card"></i>
                            Mastercard
                        </span>
                        <span class="eau-checkout-card-icon" title="American Express">
                            <i data-lucide="credit-card"></i>
                            Amex
                        </span>
                    </div>
                </div>

                <?php if ($is_sandbox) : ?>
                <!-- Test Card Info -->
                <div class="eau-checkout-test-info">
                    <div class="eau-checkout-test-badge">
                        <i data-lucide="info"></i>
                        <strong>Test Mode:</strong> Use card number <code>4005 5500 0000 0001</code> with any CVV and future expiry date.
                    </div>
                </div>
                <?php endif; ?>

                <?php else : ?>
                <!-- Gateway Not Configured -->
                <div class="eau-checkout-error">
                    <i data-lucide="alert-circle"></i>
                    <h3>Payment Gateway Not Configured</h3>
                    <p>The payment gateway has not been configured. Please contact the administrator.</p>
                </div>
                <?php endif; ?>

            </div>

        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
            });
        </script>
        <?php

        return ob_get_clean();
    }

    /**
     * Render status page after payment
     *
     * @param string $status Payment status
     * @param string $type   Payment type
     * @return string
     */
    private static function render_status_page($status, $type) {
        self::enqueue_assets();

        $icon = 'check-circle';
        $title = 'Payment Successful';
        $message = 'Your payment has been processed successfully.';
        $class = 'success';
        $action_url = '';
        $action_text = '';

        switch ($status) {
            case 'success':
                $icon = 'check-circle';
                $title = 'Payment Successful!';
                $message = $type === 'event'
                    ? 'Your event registration has been confirmed. You will receive a confirmation email shortly.'
                    : 'Your course enrollment has been confirmed. You can now access the course.';
                $class = 'success';
                $action_url = $type === 'event' ? home_url('/dashboard/my-cpds/') : home_url('/dashboard/courses/');
                $action_text = $type === 'event' ? 'View My Registrations' : 'Access Course';
                break;

            case 'cancelled':
                $icon = 'x-circle';
                $title = 'Payment Cancelled';
                $message = 'Your payment was cancelled. No charges have been made.';
                $class = 'warning';
                $action_url = home_url('/dashboard/');
                $action_text = 'Return to Dashboard';
                break;

            case 'failed':
                $icon = 'alert-circle';
                $title = 'Payment Failed';
                $message = 'There was a problem processing your payment. Please try again or contact support.';
                $class = 'error';
                $action_url = home_url('/dashboard/my-payments/');
                $action_text = 'View My Payments';
                break;

            default:
                $icon = 'info';
                $title = 'Payment Status';
                $message = 'Your payment is being processed.';
                $class = 'info';
                break;
        }

        ob_start();
        ?>
        <div class="eau-checkout-container">
            <div class="eau-checkout-status eau-checkout-status-<?php echo esc_attr($class); ?>">
                <div class="eau-checkout-status-icon">
                    <i data-lucide="<?php echo esc_attr($icon); ?>"></i>
                </div>
                <h1 class="eau-checkout-status-title"><?php echo esc_html($title); ?></h1>
                <p class="eau-checkout-status-message"><?php echo esc_html($message); ?></p>

                <?php if ($action_url) : ?>
                <div class="eau-checkout-status-actions">
                    <a href="<?php echo esc_url($action_url); ?>" class="eau-btn eau-btn-primary">
                        <?php echo esc_html($action_text); ?>
                    </a>
                    <a href="<?php echo esc_url(home_url('/dashboard/')); ?>" class="eau-btn eau-btn-secondary">
                        Return to Dashboard
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php

        return ob_get_clean();
    }

    /**
     * Render already paid message
     *
     * @param string $type    Payment type
     * @param int    $item_id Item ID
     * @return string
     */
    private static function render_already_paid($type, $item_id) {
        self::enqueue_assets();

        ob_start();
        ?>
        <div class="eau-checkout-container">
            <div class="eau-checkout-status eau-checkout-status-info">
                <div class="eau-checkout-status-icon">
                    <i data-lucide="check-circle"></i>
                </div>
                <h1 class="eau-checkout-status-title">Already Paid</h1>
                <p class="eau-checkout-status-message">
                    This <?php echo $type === 'event' ? 'registration' : 'course'; ?> has already been paid for.
                </p>
                <div class="eau-checkout-status-actions">
                    <a href="<?php echo esc_url(home_url('/dashboard/my-payments/')); ?>" class="eau-btn eau-btn-primary">
                        View My Payments
                    </a>
                    <a href="<?php echo esc_url(home_url('/dashboard/')); ?>" class="eau-btn eau-btn-secondary">
                        Return to Dashboard
                    </a>
                </div>
            </div>
        </div>
        <?php

        return ob_get_clean();
    }

    /**
     * Render error message
     *
     * @param string $message Error message
     * @return string
     */
    private static function render_error($message) {
        self::enqueue_assets();

        ob_start();
        ?>
        <div class="eau-checkout-container">
            <div class="eau-checkout-status eau-checkout-status-error">
                <div class="eau-checkout-status-icon">
                    <i data-lucide="alert-circle"></i>
                </div>
                <h1 class="eau-checkout-status-title">Error</h1>
                <p class="eau-checkout-status-message"><?php echo esc_html($message); ?></p>
                <div class="eau-checkout-status-actions">
                    <a href="<?php echo esc_url(home_url('/dashboard/')); ?>" class="eau-btn eau-btn-primary">
                        Return to Dashboard
                    </a>
                </div>
            </div>
        </div>
        <?php

        return ob_get_clean();
    }

    /**
     * Format event date
     *
     * @param int $event_id Event ID
     * @return string
     */
    private static function format_event_date($event_id) {
        $start_date = get_post_meta($event_id, 'evt_start_date', true);
        $end_date = get_post_meta($event_id, 'evt_end_date', true);

        if (empty($start_date)) {
            return '';
        }

        $start = strtotime($start_date);
        $formatted = date('F j, Y', $start);

        if (!empty($end_date) && $end_date !== $start_date) {
            $end = strtotime($end_date);
            $formatted .= ' - ' . date('F j, Y', $end);
        }

        return $formatted;
    }

    /**
     * Enqueue assets
     */
    private static function enqueue_assets() {
        // Components CSS
        wp_enqueue_style(
            'eau-components',
            EAU_SYSTEM_PLUGIN_URL . 'assets/css/eau-components.css',
            array(),
            EAU_SYSTEM_VERSION
        );

        // Checkout CSS
        wp_enqueue_style(
            'eau-checkout',
            EAU_SYSTEM_PLUGIN_URL . 'assets/css/eau-checkout.css',
            array('eau-components'),
            EAU_SYSTEM_VERSION
        );

        // Notifications JS
        wp_enqueue_script(
            'eau-notifications',
            EAU_SYSTEM_PLUGIN_URL . 'assets/js/eau-notifications.js',
            array('jquery', 'lucide-icons'),
            EAU_SYSTEM_VERSION,
            true
        );

        // Checkout JS
        wp_enqueue_script(
            'eau-checkout',
            EAU_SYSTEM_PLUGIN_URL . 'assets/js/eau-checkout.js',
            array('jquery', 'eau-notifications', 'lucide-icons'),
            EAU_SYSTEM_VERSION,
            true
        );

        // Localize script
        wp_localize_script('eau-checkout', 'eauCheckoutData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('eau_checkout_nonce'),
            'returnUrl' => add_query_arg('status', 'success', home_url('/checkout/')),
            'cancelUrl' => add_query_arg('status', 'cancelled', home_url('/checkout/')),
        ));
    }
}
