<?php
/**
 * My Payments AJAX Handlers
 *
 * Handles AJAX requests for the personal payments history page.
 * Returns only payments belonging to the current logged-in user.
 *
 * @package    EauSystem
 * @subpackage Ajax
 * @since      1.53.8
 */

namespace EauSystem\Ajax;

use EauSystem\Payments\Payments_Post_Type;
use EauSystem\Eau_Membership_Types;
use EauSystem\Checkout\Eau_Course_Purchases;

if (!defined('WPINC')) {
    die;
}

/**
 * Class Eau_My_Payments_Ajax
 *
 * AJAX handlers for personal payments history.
 *
 * @since 1.53.8
 */
class Eau_My_Payments_Ajax {

    /**
     * Registers AJAX handlers
     *
     * @since  1.53.8
     * @return void
     */
    public static function register_handlers() {
        // Get user payments list
        add_action('wp_ajax_eau_get_my_payments', array(__CLASS__, 'get_my_payments'));

        // Get user payments stats
        add_action('wp_ajax_eau_get_my_payments_stats', array(__CLASS__, 'get_my_payments_stats'));

        // Get single payment details
        add_action('wp_ajax_eau_get_my_payment_detail', array(__CLASS__, 'get_payment_detail'));

        // Export user payments CSV
        add_action('wp_ajax_eau_export_my_payments_csv', array(__CLASS__, 'export_csv'));
    }

    /**
     * Gets the current user's payments
     *
     * @since  1.53.8
     * @return void
     */
    public static function get_my_payments() {
        check_ajax_referer('eau_my_payments_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in', 'eau-system')));
        }

        $user_id = get_current_user_id();

        $page = isset($_POST['page']) ? absint($_POST['page']) : 1;
        $per_page = isset($_POST['per_page']) ? absint($_POST['per_page']) : 20;
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $payment_type = isset($_POST['payment_type']) ? sanitize_text_field($_POST['payment_type']) : '';
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
        $year = isset($_POST['year']) ? sanitize_text_field($_POST['year']) : '';
        $order_by = isset($_POST['order_by']) ? sanitize_text_field($_POST['order_by']) : 'date';
        $order = isset($_POST['order']) ? strtoupper(sanitize_text_field($_POST['order'])) : 'DESC';

        $payments = array();

        // Get Event Registration payments (if not filtered to membership/course only)
        if (empty($payment_type) || $payment_type === 'event') {
            $event_payments = self::get_event_payments($user_id, $search, $year, $status);
            $payments = array_merge($payments, $event_payments);

            // Also get pending event registrations (v1.71.0)
            if (empty($status) || $status === 'pending') {
                $pending_events = self::get_pending_event_registrations($user_id, $search, $year);
                $payments = array_merge($payments, $pending_events);
            }
        }

        // Get Course payments (v1.71.0)
        if (empty($payment_type) || $payment_type === 'course') {
            $course_payments = self::get_course_payments($user_id, $search, $year, $status);
            $payments = array_merge($payments, $course_payments);
        }

        // Get Membership Application payments (if not filtered to event/course only)
        if (empty($payment_type) || $payment_type === 'membership') {
            $membership_payments = self::get_membership_payments($user_id, $search, $year);
            $payments = array_merge($payments, $membership_payments);
        }

        // Get imported/legacy payments (skip if filtering by status since they don't have status)
        if (empty($status)) {
            $imported_payments = self::get_imported_payments($user_id, $search, $year, $payment_type);
            $payments = array_merge($payments, $imported_payments);
        }

        // Filter by status if specified (for non-pending items)
        if (!empty($status)) {
            $payments = array_filter($payments, function($p) use ($status) {
                return isset($p['payment_status']) && $p['payment_status'] === $status;
            });
            $payments = array_values($payments);
        }

        // Sort payments
        usort($payments, function($a, $b) use ($order_by, $order) {
            $field = $order_by === 'date' ? 'date_raw' : $order_by;
            $aVal = isset($a[$field]) ? $a[$field] : '';
            $bVal = isset($b[$field]) ? $b[$field] : '';

            if ($order === 'ASC') {
                return $aVal <=> $bVal;
            }
            return $bVal <=> $aVal;
        });

        // Paginate
        $total = count($payments);
        $total_pages = ceil($total / $per_page);
        $offset = ($page - 1) * $per_page;
        $payments = array_slice($payments, $offset, $per_page);

        wp_send_json_success(array(
            'rows'        => $payments,
            'total'       => $total,
            'total_pages' => $total_pages,
            'page'        => $page,
            'per_page'    => $per_page,
        ));
    }

    /**
     * Gets event registration payments for a user
     *
     * @since  1.53.8
     * @updated 1.71.0 - Added status filter and payment_status field
     * @param  int    $user_id User ID
     * @param  string $search  Search term
     * @param  string $year    Year filter
     * @param  string $status  Payment status filter
     * @return array
     */
    private static function get_event_payments($user_id, $search = '', $year = '', $status = '') {
        $payments = array();
        $prefix = Payments_Post_Type::META_PREFIX;

        // Get user's event registrations
        $reg_args = array(
            'post_type'      => 'eau_event_reg',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => array(
                array(
                    'key'     => 'reg_user_id',
                    'value'   => $user_id,
                    'compare' => '=',
                ),
            ),
        );

        $registrations = get_posts($reg_args);

        foreach ($registrations as $reg) {
            // Get payments for this registration
            $reg_payments = Payments_Post_Type::get_payments_by_registration($reg->ID);

            foreach ($reg_payments as $payment) {
                // Apply year filter
                if (!empty($year) && !empty($payment['payment_date'])) {
                    $payment_year = date('Y', strtotime($payment['payment_date']));
                    if ($payment_year !== $year) {
                        continue;
                    }
                }

                // Get event info for reference
                $event_id = get_post_meta($reg->ID, 'reg_event_id', true);
                $event = get_post($event_id);
                $reference = $event ? $event->post_title : __('Event Registration', 'eau-system');

                // Apply search filter
                if (!empty($search)) {
                    $search_lower = strtolower($search);
                    if (
                        strpos(strtolower($reference), $search_lower) === false &&
                        strpos(strtolower($payment['notes'] ?? ''), $search_lower) === false
                    ) {
                        continue;
                    }
                }

                $method_labels = Payments_Post_Type::get_payment_methods();
                $method = isset($payment['payment_method']) ? $payment['payment_method'] : '';
                $method_label = isset($method_labels[$method]) ? $method_labels[$method] : ucfirst($method);

                $payments[] = array(
                    'id'              => $payment['id'],
                    'payment_type'    => 'event',
                    'type_label'      => __('Event', 'eau-system'),
                    'type_class'      => 'eau-badge-info',
                    'reference'       => esc_html($reference),
                    'reference_id'    => $event_id,
                    'amount'          => floatval($payment['amount']),
                    'amount_fmt'      => '$' . number_format($payment['amount'], 2),
                    'date'            => !empty($payment['payment_date']) ? date('M j, Y', strtotime($payment['payment_date'])) : '-',
                    'date_raw'        => $payment['payment_date'] ?? '',
                    'method'          => $method_label,
                    'method_key'      => $method,
                    'status'          => __('Completed', 'eau-system'),
                    'status_class'    => 'eau-badge-success',
                    'payment_status'  => 'completed',
                    'item_id'         => $reg->ID,
                    'notes'           => $payment['notes'] ?? '',
                    'transaction_id'  => $payment['transaction_id'] ?? '',
                    'receipt_url'     => $payment['receipt_url'] ?? '',
                    'registration_id' => $reg->ID,
                );
            }
        }

        return $payments;
    }

    /**
     * Gets membership payments for a user
     *
     * @since  1.53.8
     * @param  int    $user_id User ID
     * @param  string $search  Search term
     * @param  string $year    Year filter
     * @return array
     */
    private static function get_membership_payments($user_id, $search = '', $year = '') {
        global $wpdb;
        $payments = array();
        $prefix = Payments_Post_Type::META_PREFIX;
        $table = $wpdb->prefix . 'eau_membership_applications';

        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return $payments;
        }

        // Get user's membership applications
        $applications = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE created_user_id = %d",
            $user_id
        ));

        foreach ($applications as $app) {
            // Get payments for this application
            $app_payments = Payments_Post_Type::get_payments_by_application($app->application_id);

            // Get membership type info for reference
            $type = Eau_Membership_Types::get_by_key($app->membership_type);
            $reference = $type ? $type->type_label . ' Membership' : $app->membership_type . ' Membership';

            foreach ($app_payments as $payment) {
                // Apply year filter
                if (!empty($year) && !empty($payment['payment_date'])) {
                    $payment_year = date('Y', strtotime($payment['payment_date']));
                    if ($payment_year !== $year) {
                        continue;
                    }
                }

                // Apply search filter
                if (!empty($search)) {
                    $search_lower = strtolower($search);
                    if (
                        strpos(strtolower($reference), $search_lower) === false &&
                        strpos(strtolower($payment['notes'] ?? ''), $search_lower) === false
                    ) {
                        continue;
                    }
                }

                $method_labels = Payments_Post_Type::get_payment_methods();
                $method = isset($payment['payment_method']) ? $payment['payment_method'] : '';
                $method_label = isset($method_labels[$method]) ? $method_labels[$method] : ucfirst($method);

                $payments[] = array(
                    'id'            => $payment['id'],
                    'payment_type'  => 'membership',
                    'type_label'    => __('Membership', 'eau-system'),
                    'type_class'    => 'eau-badge-purple',
                    'reference'     => esc_html($reference),
                    'reference_id'  => $app->application_id,
                    'amount'        => floatval($payment['amount']),
                    'amount_fmt'    => '$' . number_format($payment['amount'], 2),
                    'date'          => !empty($payment['payment_date']) ? date('M j, Y', strtotime($payment['payment_date'])) : '-',
                    'date_raw'      => $payment['payment_date'] ?? '',
                    'method'        => $method_label,
                    'method_key'    => $method,
                    'status'        => __('Confirmed', 'eau-system'),
                    'status_class'  => 'eau-badge-success',
                    'notes'         => $payment['notes'] ?? '',
                    'transaction_id' => $payment['transaction_id'] ?? '',
                    'receipt_url'   => $payment['receipt_url'] ?? '',
                    'application_id' => $app->application_id,
                );
            }
        }

        return $payments;
    }

    /**
     * Gets imported/legacy payments for a user
     *
     * @since  1.53.8
     * @param  int    $user_id      User ID
     * @param  string $search       Search term
     * @param  string $year         Year filter
     * @param  string $type_filter  Payment type filter
     * @return array
     */
    private static function get_imported_payments($user_id, $search = '', $year = '', $type_filter = '') {
        $payments = array();
        $prefix = Payments_Post_Type::META_PREFIX;

        // Get user's email for matching legacy payments
        $user = get_userdata($user_id);
        $user_email = $user ? $user->user_email : '';

        // Query for imported payments - by user_id or payer_email
        $meta_query = array(
            'relation' => 'AND',
            array(
                'key'     => $prefix . 'legacy_order_no',
                'value'   => '',
                'compare' => '!=',
            ),
            array(
                'relation' => 'OR',
                array(
                    'key'     => $prefix . 'user_id',
                    'value'   => $user_id,
                    'compare' => '=',
                ),
                array(
                    'key'     => $prefix . 'payer_email',
                    'value'   => $user_email,
                    'compare' => '=',
                ),
            ),
        );

        // Add type filter if specified
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

        $imported = get_posts($args);

        foreach ($imported as $payment_post) {
            $payment_id = $payment_post->ID;
            $amount = floatval(get_post_meta($payment_id, $prefix . 'amount', true));
            $payment_date = get_post_meta($payment_id, $prefix . 'payment_date', true);
            $payment_method = get_post_meta($payment_id, $prefix . 'payment_method', true);
            $transaction_id = get_post_meta($payment_id, $prefix . 'transaction_id', true);
            $notes = get_post_meta($payment_id, $prefix . 'notes', true);
            $legacy_description = get_post_meta($payment_id, $prefix . 'legacy_description', true);
            $legacy_order_no = get_post_meta($payment_id, $prefix . 'legacy_order_no', true);
            $payment_type = get_post_meta($payment_id, $prefix . 'payment_type', true) ?: 'membership';

            // Apply year filter
            if (!empty($year) && !empty($payment_date)) {
                $payment_year = date('Y', strtotime($payment_date));
                if ($payment_year !== $year) {
                    continue;
                }
            }

            // Reference is from notes or description
            $reference = !empty($notes) ? $notes : (!empty($legacy_description) ? explode("\n", $legacy_description)[0] : 'Order #' . $legacy_order_no);
            if (strlen($reference) > 60) {
                $reference = substr($reference, 0, 57) . '...';
            }

            // Apply search filter
            if (!empty($search)) {
                $search_lower = strtolower($search);
                if (
                    strpos(strtolower($reference), $search_lower) === false &&
                    strpos(strtolower($legacy_order_no), $search_lower) === false &&
                    strpos(strtolower($notes), $search_lower) === false
                ) {
                    continue;
                }
            }

            // Determine type label
            if ($payment_type === 'event') {
                $type_label = __('Event', 'eau-system');
                $type_class = 'eau-badge-info';
            } else {
                $type_label = __('Membership', 'eau-system');
                $type_class = 'eau-badge-purple';
            }

            $method_labels = Payments_Post_Type::get_payment_methods();
            $method_label = isset($method_labels[$payment_method]) ? $method_labels[$payment_method] : ucfirst($payment_method);

            $payments[] = array(
                'id'            => $payment_id,
                'payment_type'  => $payment_type,
                'type_label'    => $type_label,
                'type_class'    => $type_class,
                'reference'     => esc_html($reference),
                'reference_id'  => $legacy_order_no,
                'amount'        => $amount,
                'amount_fmt'    => '$' . number_format($amount, 2),
                'date'          => !empty($payment_date) ? date('M j, Y', strtotime($payment_date)) : '-',
                'date_raw'      => $payment_date ?: $payment_post->post_date,
                'method'        => $method_label,
                'method_key'    => $payment_method,
                'status'        => __('Confirmed', 'eau-system'),
                'status_class'  => 'eau-badge-success',
                'notes'         => $notes,
                'transaction_id' => $transaction_id,
                'receipt_url'   => '',
                'is_imported'   => true,
                'legacy_order_no' => $legacy_order_no,
            );
        }

        return $payments;
    }

    /**
     * Gets pending event registrations for a user (v1.71.0)
     *
     * Returns event registrations that have been created but not yet paid.
     *
     * @since  1.71.0
     * @param  int    $user_id User ID
     * @param  string $search  Search term
     * @param  string $year    Year filter
     * @return array
     */
    private static function get_pending_event_registrations($user_id, $search = '', $year = '') {
        $payments = array();

        // Get user's pending event registrations
        $reg_args = array(
            'post_type'      => 'eau_event_reg',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => array(
                'relation' => 'AND',
                array(
                    'key'     => 'reg_user_id',
                    'value'   => $user_id,
                    'compare' => '=',
                ),
                array(
                    'key'     => 'reg_payment_status',
                    'value'   => 'pending',
                    'compare' => '=',
                ),
            ),
        );

        $registrations = get_posts($reg_args);

        foreach ($registrations as $reg) {
            $reg_id = $reg->ID;

            // Get event info
            $event_id = get_post_meta($reg_id, 'reg_event_id', true);
            $event = get_post($event_id);
            $reference = $event ? $event->post_title : __('Event Registration', 'eau-system');

            // Get price
            $amount = (float) get_post_meta($reg_id, 'reg_price_paid', true);
            if ($amount <= 0) {
                continue; // Skip free registrations
            }

            // Get date
            $date = get_post_meta($reg_id, 'reg_date', true) ?: $reg->post_date;

            // Apply year filter
            if (!empty($year)) {
                $reg_year = date('Y', strtotime($date));
                if ($reg_year !== $year) {
                    continue;
                }
            }

            // Apply search filter
            if (!empty($search)) {
                $search_lower = strtolower($search);
                if (strpos(strtolower($reference), $search_lower) === false) {
                    continue;
                }
            }

            $payments[] = array(
                'id'              => 'pending_event_' . $reg_id,
                'payment_type'    => 'event',
                'type_label'      => __('Event', 'eau-system'),
                'type_class'      => 'eau-badge-info',
                'reference'       => esc_html($reference),
                'reference_id'    => $event_id,
                'amount'          => $amount,
                'amount_fmt'      => '$' . number_format($amount, 2),
                'date'            => date('M j, Y', strtotime($date)),
                'date_raw'        => $date,
                'method'          => '-',
                'method_key'      => '',
                'status'          => __('Pending', 'eau-system'),
                'status_class'    => 'eau-badge-warning',
                'payment_status'  => 'pending',
                'item_id'         => $reg_id,
                'registration_id' => $reg_id,
            );
        }

        return $payments;
    }

    /**
     * Gets course payments for a user (v1.71.0)
     *
     * @since  1.71.0
     * @param  int    $user_id User ID
     * @param  string $search  Search term
     * @param  string $year    Year filter
     * @param  string $status  Filter by status (pending, paid, etc)
     * @return array
     */
    private static function get_course_payments($user_id, $search = '', $year = '', $status = '') {
        global $wpdb;
        $payments = array();

        $table_name = $wpdb->prefix . 'eau_course_purchases';

        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        if (!$table_exists) {
            return $payments;
        }

        // Build query
        $sql = "SELECT p.*, c.post_title as course_title
                FROM $table_name p
                LEFT JOIN {$wpdb->posts} c ON p.course_id = c.ID
                WHERE p.user_id = %d";

        $params = array($user_id);

        if (!empty($status)) {
            $sql .= " AND p.status = %s";
            $params[] = $status;
        }

        $sql .= " ORDER BY p.created_at DESC";

        $purchases = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);

        foreach ($purchases as $purchase) {
            $reference = !empty($purchase['course_title']) ? $purchase['course_title'] : __('Course Purchase', 'eau-system');
            $amount = (float) $purchase['amount'];
            $date = $purchase['paid_at'] ?: $purchase['created_at'];

            // Apply year filter
            if (!empty($year)) {
                $purchase_year = date('Y', strtotime($date));
                if ($purchase_year !== $year) {
                    continue;
                }
            }

            // Apply search filter
            if (!empty($search)) {
                $search_lower = strtolower($search);
                if (strpos(strtolower($reference), $search_lower) === false) {
                    continue;
                }
            }

            // Determine status display
            $status_label = __('Pending', 'eau-system');
            $status_class = 'eau-badge-warning';
            $payment_status = $purchase['status'];

            if ($purchase['status'] === 'paid') {
                $status_label = __('Completed', 'eau-system');
                $status_class = 'eau-badge-success';
                $payment_status = 'completed';
            } elseif ($purchase['status'] === 'failed') {
                $status_label = __('Failed', 'eau-system');
                $status_class = 'eau-badge-danger';
                $payment_status = 'failed';
            }

            $payments[] = array(
                'id'              => 'course_' . $purchase['id'],
                'payment_type'    => 'course',
                'type_label'      => __('Course', 'eau-system'),
                'type_class'      => 'eau-badge-primary',
                'reference'       => esc_html($reference),
                'reference_id'    => $purchase['course_id'],
                'amount'          => $amount,
                'amount_fmt'      => '$' . number_format($amount, 2),
                'date'            => date('M j, Y', strtotime($date)),
                'date_raw'        => $date,
                'method'          => $purchase['status'] === 'paid' ? 'Credit Card' : '-',
                'method_key'      => 'card',
                'status'          => $status_label,
                'status_class'    => $status_class,
                'payment_status'  => $payment_status,
                'item_id'         => $purchase['id'],
                'transaction_id'  => $purchase['transaction_id'] ?? '',
            );
        }

        return $payments;
    }

    /**
     * Gets payment stats for the current user
     *
     * @since  1.53.8
     * @updated 1.71.0 - Added pending payments and course payments
     * @return void
     */
    public static function get_my_payments_stats() {
        check_ajax_referer('eau_my_payments_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in', 'eau-system')));
        }

        $user_id = get_current_user_id();

        // Get all completed payments
        $event_payments = self::get_event_payments($user_id);
        $membership_payments = self::get_membership_payments($user_id);
        $imported_payments = self::get_imported_payments($user_id);
        $course_payments = self::get_course_payments($user_id, '', '', 'paid');

        // Get pending items (v1.71.0)
        $pending_events = self::get_pending_event_registrations($user_id);
        $pending_courses = self::get_course_payments($user_id, '', '', 'pending');

        $all_completed = array_merge($event_payments, $membership_payments, $imported_payments, $course_payments);

        // Calculate stats
        $total_paid = 0;
        $event_count = 0;
        $course_count = count($course_payments);

        foreach ($all_completed as $payment) {
            $total_paid += $payment['amount'];
            if ($payment['payment_type'] === 'event') {
                $event_count++;
            }
        }

        // Calculate pending amount
        $pending_amount = 0;
        foreach ($pending_events as $p) {
            $pending_amount += $p['amount'];
        }
        foreach ($pending_courses as $p) {
            $pending_amount += $p['amount'];
        }

        wp_send_json_success(array(
            'pending_payments'     => count($pending_events) + count($pending_courses),
            'pending_payments_fmt' => '$' . number_format($pending_amount, 2),
            'total_paid'           => $total_paid,
            'total_paid_fmt'       => '$' . number_format($total_paid, 2),
            'event_payments'       => $event_count,
            'course_payments'      => $course_count,
        ));
    }

    /**
     * Gets payment detail for the modal
     *
     * @since  1.53.8
     * @updated 1.71.3 - Support for different payment types (event, course, pending)
     * @return void
     */
    public static function get_payment_detail() {
        check_ajax_referer('eau_my_payments_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in', 'eau-system')));
        }

        $raw_payment_id = isset($_POST['payment_id']) ? sanitize_text_field($_POST['payment_id']) : '';
        $payment_type = isset($_POST['payment_type']) ? sanitize_text_field($_POST['payment_type']) : '';

        if (empty($raw_payment_id)) {
            wp_send_json_error(array('message' => __('Invalid payment ID', 'eau-system')));
        }

        $user_id = get_current_user_id();

        // Handle different payment types
        if (strpos($raw_payment_id, 'pending_event_') === 0) {
            // Pending event registration
            $reg_id = intval(str_replace('pending_event_', '', $raw_payment_id));
            self::get_pending_event_detail($reg_id, $user_id);
            return;
        }

        if (strpos($raw_payment_id, 'course_') === 0) {
            // Course payment
            $purchase_id = intval(str_replace('course_', '', $raw_payment_id));
            self::get_course_payment_detail($purchase_id, $user_id);
            return;
        }

        if ($payment_type === 'event' && is_numeric($raw_payment_id)) {
            // Completed event payment (from eau_payment post)
            self::get_event_payment_detail(intval($raw_payment_id), $user_id);
            return;
        }

        // Default: imported/legacy payment (eau_payment post)
        self::get_imported_payment_detail(intval($raw_payment_id), $user_id);
    }

    /**
     * Gets pending event registration detail
     *
     * @since 1.71.3
     */
    private static function get_pending_event_detail($reg_id, $user_id) {
        $reg = get_post($reg_id);
        if (!$reg || $reg->post_type !== 'eau_event_reg') {
            wp_send_json_error(array('message' => __('Registration not found', 'eau-system')));
        }

        // Verify ownership
        $reg_user_id = get_post_meta($reg_id, 'reg_user_id', true);
        if ($reg_user_id != $user_id) {
            wp_send_json_error(array('message' => __('You do not have permission to view this', 'eau-system')));
        }

        // Get event info
        $event_id = get_post_meta($reg_id, 'reg_event_id', true);
        $event = get_post($event_id);
        $reference = $event ? $event->post_title : __('Event Registration', 'eau-system');

        $amount = (float) get_post_meta($reg_id, 'reg_price_paid', true);
        $date = get_post_meta($reg_id, 'reg_date', true) ?: $reg->post_date;

        wp_send_json_success(array(
            'id'             => 'pending_event_' . $reg_id,
            'payment_type'   => 'event',
            'type_label'     => __('Event', 'eau-system'),
            'type_class'     => 'eau-badge-info',
            'amount'         => $amount,
            'amount_fmt'     => '$' . number_format($amount, 2),
            'date'           => date('F j, Y', strtotime($date)),
            'method'         => '-',
            'reference'      => $reference,
            'transaction_id' => '',
            'notes'          => __('Payment pending', 'eau-system'),
            'receipt_url'    => '',
            'is_pending'     => true,
            'status'         => 'pending',
            'status_label'   => __('Pending', 'eau-system'),
            'status_class'   => 'eau-badge-warning',
        ));
    }

    /**
     * Gets course payment detail
     *
     * @since 1.71.3
     */
    private static function get_course_payment_detail($purchase_id, $user_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'eau_course_purchases';

        $purchase = $wpdb->get_row($wpdb->prepare(
            "SELECT p.*, c.post_title as course_title
             FROM $table_name p
             LEFT JOIN {$wpdb->posts} c ON p.course_id = c.ID
             WHERE p.id = %d",
            $purchase_id
        ), ARRAY_A);

        if (!$purchase) {
            wp_send_json_error(array('message' => __('Purchase not found', 'eau-system')));
        }

        // Verify ownership
        if ($purchase['user_id'] != $user_id) {
            wp_send_json_error(array('message' => __('You do not have permission to view this', 'eau-system')));
        }

        $reference = !empty($purchase['course_title']) ? $purchase['course_title'] : __('Course Purchase', 'eau-system');
        $amount = (float) $purchase['amount'];
        $date = $purchase['paid_at'] ?: $purchase['created_at'];

        // Status
        $status_label = __('Pending', 'eau-system');
        $status_class = 'eau-badge-warning';
        if ($purchase['status'] === 'paid') {
            $status_label = __('Completed', 'eau-system');
            $status_class = 'eau-badge-success';
        } elseif ($purchase['status'] === 'failed') {
            $status_label = __('Failed', 'eau-system');
            $status_class = 'eau-badge-danger';
        }

        wp_send_json_success(array(
            'id'             => 'course_' . $purchase_id,
            'payment_type'   => 'course',
            'type_label'     => __('Course', 'eau-system'),
            'type_class'     => 'eau-badge-primary',
            'amount'         => $amount,
            'amount_fmt'     => '$' . number_format($amount, 2),
            'date'           => date('F j, Y', strtotime($date)),
            'method'         => $purchase['status'] === 'paid' ? 'Credit Card' : '-',
            'reference'      => $reference,
            'transaction_id' => $purchase['transaction_id'] ?? '',
            'notes'          => '',
            'receipt_url'    => '',
            'status'         => $purchase['status'],
            'status_label'   => $status_label,
            'status_class'   => $status_class,
        ));
    }

    /**
     * Gets event payment detail (completed payment from eau_payment post)
     *
     * @since 1.71.3
     */
    private static function get_event_payment_detail($payment_id, $user_id) {
        $prefix = Payments_Post_Type::META_PREFIX;

        $payment = get_post($payment_id);
        if (!$payment || $payment->post_type !== 'eau_payment') {
            wp_send_json_error(array('message' => __('Payment not found', 'eau-system')));
        }

        // Verify ownership
        $payment_user_id = get_post_meta($payment_id, $prefix . 'user_id', true);
        $payer_email = get_post_meta($payment_id, $prefix . 'payer_email', true);
        $user = get_userdata($user_id);

        if ($payment_user_id != $user_id && $payer_email !== $user->user_email) {
            wp_send_json_error(array('message' => __('You do not have permission to view this payment', 'eau-system')));
        }

        // Get payment details
        $amount = floatval(get_post_meta($payment_id, $prefix . 'amount', true));
        $payment_date = get_post_meta($payment_id, $prefix . 'payment_date', true);
        $payment_method = get_post_meta($payment_id, $prefix . 'payment_method', true);
        $transaction_id = get_post_meta($payment_id, $prefix . 'transaction_id', true);
        $notes = get_post_meta($payment_id, $prefix . 'notes', true);
        $receipt_url = get_post_meta($payment_id, $prefix . 'receipt_url', true);

        // Get reference from registration
        $registration_id = get_post_meta($payment_id, $prefix . 'registration_id', true);
        $reference = $notes ?: __('Event Payment', 'eau-system');
        if ($registration_id) {
            $event_id = get_post_meta($registration_id, 'reg_event_id', true);
            $event = get_post($event_id);
            if ($event) {
                $reference = $event->post_title;
            }
        }

        $method_labels = Payments_Post_Type::get_payment_methods();
        $method_label = isset($method_labels[$payment_method]) ? $method_labels[$payment_method] : ucfirst($payment_method);

        wp_send_json_success(array(
            'id'             => $payment_id,
            'payment_type'   => 'event',
            'type_label'     => __('Event', 'eau-system'),
            'type_class'     => 'eau-badge-info',
            'amount'         => $amount,
            'amount_fmt'     => '$' . number_format($amount, 2),
            'date'           => !empty($payment_date) ? date('F j, Y', strtotime($payment_date)) : '-',
            'method'         => $method_label,
            'reference'      => $reference,
            'transaction_id' => $transaction_id,
            'notes'          => $notes,
            'receipt_url'    => $receipt_url,
            'status'         => 'completed',
            'status_label'   => __('Completed', 'eau-system'),
            'status_class'   => 'eau-badge-success',
        ));
    }

    /**
     * Gets imported/legacy payment detail
     *
     * @since 1.71.3
     */
    private static function get_imported_payment_detail($payment_id, $user_id) {
        $prefix = Payments_Post_Type::META_PREFIX;

        $payment = get_post($payment_id);
        if (!$payment || $payment->post_type !== 'eau_payment') {
            wp_send_json_error(array('message' => __('Payment not found', 'eau-system')));
        }

        // Verify ownership
        $payment_user_id = get_post_meta($payment_id, $prefix . 'user_id', true);
        $payer_email = get_post_meta($payment_id, $prefix . 'payer_email', true);
        $user = get_userdata($user_id);

        if ($payment_user_id != $user_id && $payer_email !== $user->user_email) {
            wp_send_json_error(array('message' => __('You do not have permission to view this payment', 'eau-system')));
        }

        // Get payment details
        $amount = floatval(get_post_meta($payment_id, $prefix . 'amount', true));
        $payment_date = get_post_meta($payment_id, $prefix . 'payment_date', true);
        $payment_method = get_post_meta($payment_id, $prefix . 'payment_method', true);
        $transaction_id = get_post_meta($payment_id, $prefix . 'transaction_id', true);
        $notes = get_post_meta($payment_id, $prefix . 'notes', true);
        $receipt_url = get_post_meta($payment_id, $prefix . 'receipt_url', true);
        $payment_type = get_post_meta($payment_id, $prefix . 'payment_type', true) ?: 'membership';
        $legacy_description = get_post_meta($payment_id, $prefix . 'legacy_description', true);
        $legacy_order_no = get_post_meta($payment_id, $prefix . 'legacy_order_no', true);

        // Reference
        $reference = !empty($notes) ? $notes : (!empty($legacy_description) ? $legacy_description : 'Payment');

        // Type labels
        if ($payment_type === 'event') {
            $type_label = __('Event', 'eau-system');
            $type_class = 'eau-badge-info';
        } else {
            $type_label = __('Membership', 'eau-system');
            $type_class = 'eau-badge-purple';
        }

        $method_labels = Payments_Post_Type::get_payment_methods();
        $method_label = isset($method_labels[$payment_method]) ? $method_labels[$payment_method] : ucfirst($payment_method);

        wp_send_json_success(array(
            'id'             => $payment_id,
            'payment_type'   => $payment_type,
            'type_label'     => $type_label,
            'type_class'     => $type_class,
            'amount'         => $amount,
            'amount_fmt'     => '$' . number_format($amount, 2),
            'date'           => !empty($payment_date) ? date('F j, Y', strtotime($payment_date)) : '-',
            'method'         => $method_label,
            'reference'      => $reference,
            'transaction_id' => $transaction_id,
            'notes'          => $notes,
            'receipt_url'    => $receipt_url,
            'is_imported'    => !empty($legacy_order_no),
            'status'         => 'completed',
            'status_label'   => __('Confirmed', 'eau-system'),
            'status_class'   => 'eau-badge-success',
        ));
    }

    /**
     * Exports user payments to CSV
     *
     * @since  1.53.8
     * @return void
     */
    public static function export_csv() {
        check_ajax_referer('eau_my_payments_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in', 'eau-system')));
        }

        $user_id = get_current_user_id();
        $payment_type = isset($_POST['payment_type']) ? sanitize_text_field($_POST['payment_type']) : '';
        $year = isset($_POST['year']) ? sanitize_text_field($_POST['year']) : '';

        $payments = array();

        if (empty($payment_type) || $payment_type === 'event') {
            $payments = array_merge($payments, self::get_event_payments($user_id, '', $year));
        }
        if (empty($payment_type) || $payment_type === 'membership') {
            $payments = array_merge($payments, self::get_membership_payments($user_id, '', $year));
        }
        $payments = array_merge($payments, self::get_imported_payments($user_id, '', $year, $payment_type));

        // Sort by date descending
        usort($payments, function($a, $b) {
            return ($b['date_raw'] ?? '') <=> ($a['date_raw'] ?? '');
        });

        // Build CSV
        $csv_lines = array();
        $csv_lines[] = array('Date', 'Type', 'Reference', 'Amount', 'Method', 'Transaction ID');

        foreach ($payments as $payment) {
            $csv_lines[] = array(
                $payment['date'],
                $payment['type_label'],
                $payment['reference'],
                number_format($payment['amount'], 2),
                $payment['method'],
                $payment['transaction_id'] ?? '',
            );
        }

        $csv = '';
        foreach ($csv_lines as $line) {
            $csv .= '"' . implode('","', array_map('addslashes', $line)) . '"' . "\n";
        }

        wp_send_json_success(array(
            'csv'      => $csv,
            'filename' => 'my-payments-' . date('Y-m-d-His') . '.csv',
        ));
    }
}
