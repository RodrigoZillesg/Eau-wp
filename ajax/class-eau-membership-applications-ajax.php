<?php
namespace EauSystem\Ajax;

use EauSystem\Eau_Membership_Database;
use EauSystem\Eau_Membership_Types;
use EauSystem\Eau_User_Institution_Helper;
use EauSystem\Email\Email_Membership;

/**
 * AJAX handlers for Membership Applications Management
 *
 * @since 1.49.0
 */
class Eau_Membership_Applications_Ajax {

    /**
     * Register AJAX handlers
     */
    public static function register_handlers() {
        // Admin only handlers
        add_action('wp_ajax_eau_get_membership_applications', array(__CLASS__, 'get_applications'));
        add_action('wp_ajax_eau_get_application_details', array(__CLASS__, 'get_application_details'));
        add_action('wp_ajax_eau_approve_application', array(__CLASS__, 'approve_application'));
        add_action('wp_ajax_eau_reject_application', array(__CLASS__, 'reject_application'));
        add_action('wp_ajax_eau_mark_application_under_review', array(__CLASS__, 'mark_under_review'));
        add_action('wp_ajax_eau_cancel_application', array(__CLASS__, 'cancel_application'));
        add_action('wp_ajax_eau_export_applications_csv', array(__CLASS__, 'export_csv'));
    }

    /**
     * Get applications list with pagination and filters
     */
    public static function get_applications() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'eau_membership_applications')) {
            wp_send_json_error(array('message' => 'Security check failed.'));
        }

        // Check permissions
        if (!Eau_User_Institution_Helper::has_admin_access()) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        global $wpdb;
        $table_name = Eau_Membership_Database::get_table_name(Eau_Membership_Database::TABLE_APPLICATIONS);

        // Pagination
        $page = isset($_POST['page']) ? max(1, intval($_POST['page'])) : 1;
        $per_page = isset($_POST['per_page']) ? max(1, min(100, intval($_POST['per_page']))) : 20;
        $offset = ($page - 1) * $per_page;

        // Search
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';

        // Filters
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
        $membership_type = isset($_POST['membership_type']) ? sanitize_text_field($_POST['membership_type']) : '';
        $date_from = isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : '';
        $date_to = isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : '';

        // Sorting
        $order_by = isset($_POST['order_by']) ? sanitize_text_field($_POST['order_by']) : 'submitted_at';
        $order = isset($_POST['order']) && strtoupper($_POST['order']) === 'ASC' ? 'ASC' : 'DESC';

        // Allowed columns for sorting
        $allowed_order_by = array('application_id', 'first_name', 'last_name', 'email', 'membership_type', 'company_name', 'status', 'submitted_at');
        if (!in_array($order_by, $allowed_order_by)) {
            $order_by = 'submitted_at';
        }

        // Build WHERE clause
        $where = array('1=1');
        $params = array();

        if (!empty($search)) {
            $where[] = "(first_name LIKE %s OR last_name LIKE %s OR email LIKE %s OR company_name LIKE %s)";
            $search_like = '%' . $wpdb->esc_like($search) . '%';
            $params[] = $search_like;
            $params[] = $search_like;
            $params[] = $search_like;
            $params[] = $search_like;
        }

        if (!empty($status)) {
            $where[] = "status = %s";
            $params[] = $status;
        }

        if (!empty($membership_type)) {
            $where[] = "membership_type = %s";
            $params[] = $membership_type;
        }

        if (!empty($date_from)) {
            $where[] = "DATE(submitted_at) >= %s";
            $params[] = $date_from;
        }

        if (!empty($date_to)) {
            $where[] = "DATE(submitted_at) <= %s";
            $params[] = $date_to;
        }

        $where_sql = implode(' AND ', $where);

        // Get total count
        $count_sql = "SELECT COUNT(*) FROM $table_name WHERE $where_sql";
        if (!empty($params)) {
            $count_sql = $wpdb->prepare($count_sql, $params);
        }
        $total = $wpdb->get_var($count_sql);

        // Get applications
        $sql = "SELECT * FROM $table_name WHERE $where_sql ORDER BY $order_by $order LIMIT %d OFFSET %d";
        $params[] = $per_page;
        $params[] = $offset;

        $applications = $wpdb->get_results($wpdb->prepare($sql, $params));

        // Format applications for response
        $formatted = array();
        foreach ($applications as $app) {
            $type_label = Eau_Membership_Types::get_label($app->membership_type);

            $formatted[] = array(
                'application_id' => $app->application_id,
                'applicant' => array(
                    'name' => $app->first_name . ' ' . $app->last_name,
                    'email' => $app->email,
                    'phone' => $app->phone,
                ),
                'membership_type' => array(
                    'key' => $app->membership_type,
                    'label' => $type_label,
                ),
                'company_name' => $app->company_name,
                'state' => $app->state,
                'country' => $app->country,
                'status' => $app->status,
                'submitted_at' => $app->submitted_at,
                'submitted_at_formatted' => date('M j, Y', strtotime($app->submitted_at)),
                'reviewed_at' => $app->reviewed_at,
                'has_documents' => !empty($app->documents) && $app->documents !== '[]',
            );
        }

        wp_send_json_success(array(
            'applications' => $formatted,
            'total' => (int) $total,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil($total / $per_page),
        ));
    }

    /**
     * Get single application details
     */
    public static function get_application_details() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'eau_membership_applications')) {
            wp_send_json_error(array('message' => 'Security check failed.'));
        }

        // Check permissions
        if (!Eau_User_Institution_Helper::has_admin_access()) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        $application_id = isset($_POST['application_id']) ? intval($_POST['application_id']) : 0;
        if ($application_id <= 0) {
            wp_send_json_error(array('message' => 'Invalid application ID.'));
        }

        global $wpdb;
        $table_name = Eau_Membership_Database::get_table_name(Eau_Membership_Database::TABLE_APPLICATIONS);

        $application = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE application_id = %d",
            $application_id
        ));

        if (!$application) {
            wp_send_json_error(array('message' => 'Application not found.'));
        }

        // Get membership type details
        $type_data = Eau_Membership_Types::get_by_key($application->membership_type);

        // Get reviewer info if reviewed
        $reviewer = null;
        if ($application->reviewed_by) {
            $reviewer_user = get_userdata($application->reviewed_by);
            if ($reviewer_user) {
                $reviewer = array(
                    'name' => $reviewer_user->display_name,
                    'email' => $reviewer_user->user_email,
                );
            }
        }

        // Get linked user info if exists
        $linked_user = null;
        if ($application->user_id) {
            $user = get_userdata($application->user_id);
            if ($user) {
                $linked_user = array(
                    'id' => $user->ID,
                    'name' => $user->display_name,
                    'email' => $user->user_email,
                );
            }
        }

        // Parse JSON fields
        $membership_data = json_decode($application->membership_data, true) ?: array();
        $documents = json_decode($application->documents, true) ?: array();
        $newsletters = json_decode($application->newsletter_subscriptions, true) ?: array();

        wp_send_json_success(array(
            'application' => array(
                'application_id' => $application->application_id,
                'user_id' => $application->user_id,
                'linked_user' => $linked_user,
                'email' => $application->email,
                'first_name' => $application->first_name,
                'last_name' => $application->last_name,
                'phone' => $application->phone,
                'position' => $application->position,
                'company_name' => $application->company_name,
                'state' => $application->state,
                'country' => $application->country,
                'membership_type' => array(
                    'key' => $application->membership_type,
                    'label' => $type_data ? $type_data->type_label : $application->membership_type,
                    'fee' => $type_data ? $type_data->fee_amount : 0,
                    'fee_currency' => $type_data ? $type_data->fee_currency : 'AUD',
                ),
                'membership_data' => $membership_data,
                'documents' => $documents,
                'newsletter_subscriptions' => $newsletters,
                'status' => $application->status,
                'submitted_at' => $application->submitted_at,
                'submitted_at_formatted' => date('F j, Y \a\t g:i A', strtotime($application->submitted_at)),
                'reviewed_at' => $application->reviewed_at,
                'reviewed_at_formatted' => $application->reviewed_at ? date('F j, Y \a\t g:i A', strtotime($application->reviewed_at)) : null,
                'reviewer' => $reviewer,
                'admin_notes' => $application->admin_notes,
                'rejection_reason' => $application->rejection_reason,
                'created_user_id' => $application->created_user_id,
                'created_institution_id' => $application->created_institution_id,
            ),
        ));
    }

    /**
     * Approve an application
     */
    public static function approve_application() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'eau_membership_applications')) {
            wp_send_json_error(array('message' => 'Security check failed.'));
        }

        // Check permissions
        if (!Eau_User_Institution_Helper::has_admin_access()) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        $application_id = isset($_POST['application_id']) ? intval($_POST['application_id']) : 0;
        $admin_notes = isset($_POST['admin_notes']) ? sanitize_textarea_field($_POST['admin_notes']) : '';
        // Fix: Use filter_var to correctly convert string "false" to boolean false
        $send_email = isset($_POST['send_email']) ? filter_var($_POST['send_email'], FILTER_VALIDATE_BOOLEAN) : true;

        if ($application_id <= 0) {
            wp_send_json_error(array('message' => 'Invalid application ID.'));
        }

        global $wpdb;
        $table_name = Eau_Membership_Database::get_table_name(Eau_Membership_Database::TABLE_APPLICATIONS);

        // Get application
        $application = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE application_id = %d",
            $application_id
        ));

        if (!$application) {
            wp_send_json_error(array('message' => 'Application not found.'));
        }

        if ($application->status === 'approved') {
            wp_send_json_error(array('message' => 'This application has already been approved.'));
        }

        if ($application->status === 'rejected') {
            wp_send_json_error(array('message' => 'Cannot approve a rejected application.'));
        }

        // Start transaction
        $wpdb->query('START TRANSACTION');

        try {
            $current_user_id = get_current_user_id();
            $membership_data = json_decode($application->membership_data, true) ?: array();

            // Get or create user
            $user_id = $application->user_id;
            if (!$user_id) {
                // Check if user exists by email
                $existing_user = get_user_by('email', $application->email);
                if ($existing_user) {
                    $user_id = $existing_user->ID;
                } else {
                    // Create new user
                    $user_id = wp_create_user(
                        $application->email,
                        wp_generate_password(),
                        $application->email
                    );

                    if (is_wp_error($user_id)) {
                        throw new \Exception('Failed to create user: ' . $user_id->get_error_message());
                    }

                    // Set basic user meta
                    update_user_meta($user_id, 'first_name', $application->first_name);
                    update_user_meta($user_id, 'last_name', $application->last_name);
                }
            }

            // Update user membership meta
            update_user_meta($user_id, 'mem_membership_type', $application->membership_type);
            update_user_meta($user_id, 'mem_membership_status', 'active');
            update_user_meta($user_id, 'mem_membership_start_date', current_time('Y-m-d'));
            update_user_meta($user_id, 'mem_membership_application_id', $application_id);
            update_user_meta($user_id, 'mem_status', 'active');

            // Calculate expiry date (1 year from now)
            $expiry_date = date('Y-m-d', strtotime('+1 year'));
            update_user_meta($user_id, 'mem_membership_expiry_date', $expiry_date);

            // Update user meta for contact info
            update_user_meta($user_id, 'mem_firstname', $application->first_name);
            update_user_meta($user_id, 'mem_lastname', $application->last_name);
            update_user_meta($user_id, 'mem_phone', $application->phone);
            update_user_meta($user_id, 'mem_position', $application->position);

            // Handle institution creation for provider types
            $institution_id = null;
            $provider_types = array('full_provider', 'associate_access');

            if (in_array($application->membership_type, $provider_types)) {
                // Check if institution already exists by CRICOS
                $cricos_number = isset($membership_data['cricos_number']) ? $membership_data['cricos_number'] : '';

                if (!empty($cricos_number)) {
                    // Search for existing institution
                    $existing_institution = get_posts(array(
                        'post_type' => 'institutions',
                        'meta_query' => array(
                            array(
                                'key' => 'ins_cricos_number',
                                'value' => $cricos_number,
                            ),
                        ),
                        'posts_per_page' => 1,
                    ));

                    if (!empty($existing_institution)) {
                        $institution_id = $existing_institution[0]->ID;
                    }
                }

                // Create new institution if not found
                if (!$institution_id) {
                    $institution_id = wp_insert_post(array(
                        'post_type' => 'institutions',
                        'post_title' => $application->company_name,
                        'post_status' => 'publish',
                    ));

                    if (is_wp_error($institution_id)) {
                        throw new \Exception('Failed to create institution.');
                    }

                    // Set institution meta
                    update_post_meta($institution_id, 'ins_company_name', $application->company_name);
                    update_post_meta($institution_id, 'ins_type', $application->membership_type);
                    update_post_meta($institution_id, 'ins_state', $application->state);
                    update_post_meta($institution_id, 'ins_country', $application->country);
                    update_post_meta($institution_id, 'ins_company_primary_contact', $user_id);
                    update_post_meta($institution_id, 'ins_membership_start_date', current_time('Y-m-d'));
                    update_post_meta($institution_id, 'ins_membership_expiry_date', $expiry_date);

                    if (!empty($cricos_number)) {
                        update_post_meta($institution_id, 'ins_cricos_number', $cricos_number);
                    }

                    if (isset($membership_data['cricos_sites'])) {
                        update_post_meta($institution_id, 'ins_cricos_sites', $membership_data['cricos_sites']);
                    }

                    // Generate institution ID
                    $ins_company_id = 'INS' . str_pad($institution_id, 6, '0', STR_PAD_LEFT);
                    update_post_meta($institution_id, 'ins_company_id', $ins_company_id);
                }

                // Link user to institution
                update_user_meta($user_id, 'mem_membercompanyname', get_post_meta($institution_id, 'ins_company_id', true));
                update_user_meta($user_id, 'mem_type', 'institutionAdmin');
            } else {
                // For non-provider types, just set as member
                update_user_meta($user_id, 'mem_type', 'member');
                update_user_meta($user_id, 'mem_membercompanyname', $application->company_name);
            }

            // Update application status
            $wpdb->update(
                $table_name,
                array(
                    'status' => 'approved',
                    'reviewed_at' => current_time('mysql'),
                    'reviewed_by' => $current_user_id,
                    'admin_notes' => $admin_notes,
                    'created_user_id' => $user_id,
                    'created_institution_id' => $institution_id,
                ),
                array('application_id' => $application_id),
                array('%s', '%s', '%d', '%s', '%d', '%d'),
                array('%d')
            );

            $wpdb->query('COMMIT');

            // Send approval email (uses Email_Membership for dev/prod handling)
            if ($send_email) {
                Email_Membership::send_application_approved($application_id, $admin_notes);
            }

            wp_send_json_success(array(
                'message' => 'Application approved successfully!',
                'user_id' => $user_id,
                'institution_id' => $institution_id,
            ));

        } catch (\Exception $e) {
            $wpdb->query('ROLLBACK');
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * Reject an application
     */
    public static function reject_application() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'eau_membership_applications')) {
            wp_send_json_error(array('message' => 'Security check failed.'));
        }

        // Check permissions
        if (!Eau_User_Institution_Helper::has_admin_access()) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        $application_id = isset($_POST['application_id']) ? intval($_POST['application_id']) : 0;
        $rejection_reason = isset($_POST['rejection_reason']) ? sanitize_textarea_field($_POST['rejection_reason']) : '';
        // Fix: Use filter_var to correctly convert string "false" to boolean false
        $send_email = isset($_POST['send_email']) ? filter_var($_POST['send_email'], FILTER_VALIDATE_BOOLEAN) : true;

        if ($application_id <= 0) {
            wp_send_json_error(array('message' => 'Invalid application ID.'));
        }

        if (empty($rejection_reason)) {
            wp_send_json_error(array('message' => 'Please provide a rejection reason.'));
        }

        global $wpdb;
        $table_name = Eau_Membership_Database::get_table_name(Eau_Membership_Database::TABLE_APPLICATIONS);

        // Get application
        $application = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE application_id = %d",
            $application_id
        ));

        if (!$application) {
            wp_send_json_error(array('message' => 'Application not found.'));
        }

        if ($application->status === 'approved') {
            wp_send_json_error(array('message' => 'Cannot reject an approved application.'));
        }

        if ($application->status === 'rejected') {
            wp_send_json_error(array('message' => 'This application has already been rejected.'));
        }

        // Update application
        $result = $wpdb->update(
            $table_name,
            array(
                'status' => 'rejected',
                'reviewed_at' => current_time('mysql'),
                'reviewed_by' => get_current_user_id(),
                'rejection_reason' => $rejection_reason,
            ),
            array('application_id' => $application_id),
            array('%s', '%s', '%d', '%s'),
            array('%d')
        );

        if ($result === false) {
            wp_send_json_error(array('message' => 'Failed to update application.'));
        }

        // Send rejection email (uses Email_Membership for dev/prod handling)
        if ($send_email) {
            Email_Membership::send_application_rejected($application_id, $rejection_reason);
        }

        wp_send_json_success(array('message' => 'Application rejected.'));
    }

    /**
     * Mark application as under review
     */
    public static function mark_under_review() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'eau_membership_applications')) {
            wp_send_json_error(array('message' => 'Security check failed.'));
        }

        // Check permissions
        if (!Eau_User_Institution_Helper::has_admin_access()) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        $application_id = isset($_POST['application_id']) ? intval($_POST['application_id']) : 0;

        if ($application_id <= 0) {
            wp_send_json_error(array('message' => 'Invalid application ID.'));
        }

        global $wpdb;
        $table_name = Eau_Membership_Database::get_table_name(Eau_Membership_Database::TABLE_APPLICATIONS);

        $result = $wpdb->update(
            $table_name,
            array('status' => 'under_review'),
            array('application_id' => $application_id),
            array('%s'),
            array('%d')
        );

        if ($result === false) {
            wp_send_json_error(array('message' => 'Failed to update application status.'));
        }

        wp_send_json_success(array('message' => 'Application marked as under review.'));
    }

    /**
     * Cancel an approved application
     *
     * This allows admins to cancel an application that was already approved.
     * When cancelled:
     * - Application status changes to 'cancelled'
     * - User's membership status is set to 'cancelled'
     * - The application will show as "Cancelled" in Payments Management
     *
     * @since 1.51.46
     */
    public static function cancel_application() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'eau_membership_applications')) {
            wp_send_json_error(array('message' => 'Security check failed.'));
        }

        // Check permissions
        if (!Eau_User_Institution_Helper::has_admin_access()) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        $application_id = isset($_POST['application_id']) ? intval($_POST['application_id']) : 0;
        $cancellation_reason = isset($_POST['cancellation_reason']) ? sanitize_textarea_field($_POST['cancellation_reason']) : '';
        $send_email = isset($_POST['send_email']) ? filter_var($_POST['send_email'], FILTER_VALIDATE_BOOLEAN) : true;

        if ($application_id <= 0) {
            wp_send_json_error(array('message' => 'Invalid application ID.'));
        }

        if (empty($cancellation_reason)) {
            wp_send_json_error(array('message' => 'Please provide a cancellation reason.'));
        }

        global $wpdb;
        $table_name = Eau_Membership_Database::get_table_name(Eau_Membership_Database::TABLE_APPLICATIONS);

        // Get application
        $application = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE application_id = %d",
            $application_id
        ));

        if (!$application) {
            wp_send_json_error(array('message' => 'Application not found.'));
        }

        // Only approved applications can be cancelled
        if ($application->status !== 'approved') {
            wp_send_json_error(array('message' => 'Only approved applications can be cancelled.'));
        }

        // Update application status to cancelled
        $result = $wpdb->update(
            $table_name,
            array(
                'status' => 'cancelled',
                'reviewed_at' => current_time('mysql'),
                'reviewed_by' => get_current_user_id(),
                'admin_notes' => $cancellation_reason,
            ),
            array('application_id' => $application_id),
            array('%s', '%s', '%d', '%s'),
            array('%d')
        );

        if ($result === false) {
            wp_send_json_error(array('message' => 'Failed to cancel application.'));
        }

        // Update user's membership status if user was created
        if (!empty($application->created_user_id)) {
            update_user_meta($application->created_user_id, 'mem_membership_status', 'cancelled');
        }

        // Send cancellation email
        if ($send_email) {
            Email_Membership::send_application_cancelled($application_id, $cancellation_reason);
        }

        wp_send_json_success(array('message' => 'Application cancelled successfully.'));
    }

    /**
     * Export applications to CSV
     */
    public static function export_csv() {
        // Verify nonce
        if (!isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'eau_membership_applications')) {
            wp_die('Security check failed.');
        }

        // Check permissions
        if (!Eau_User_Institution_Helper::has_admin_access()) {
            wp_die('Permission denied.');
        }

        global $wpdb;
        $table_name = Eau_Membership_Database::get_table_name(Eau_Membership_Database::TABLE_APPLICATIONS);

        // Get all applications
        $applications = $wpdb->get_results("SELECT * FROM $table_name ORDER BY submitted_at DESC");

        // Set headers for download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=membership-applications-' . date('Y-m-d') . '.csv');

        // Open output stream
        $output = fopen('php://output', 'w');

        // Add BOM for Excel
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        // Header row
        fputcsv($output, array(
            'Application ID',
            'First Name',
            'Last Name',
            'Email',
            'Phone',
            'Position',
            'Company Name',
            'State',
            'Country',
            'Membership Type',
            'Status',
            'Submitted At',
            'Reviewed At',
            'Admin Notes',
            'Rejection Reason',
        ));

        // Data rows
        foreach ($applications as $app) {
            $type_label = Eau_Membership_Types::get_label($app->membership_type);

            fputcsv($output, array(
                $app->application_id,
                $app->first_name,
                $app->last_name,
                $app->email,
                $app->phone,
                $app->position,
                $app->company_name,
                $app->state,
                $app->country,
                $type_label,
                ucfirst(str_replace('_', ' ', $app->status)),
                $app->submitted_at,
                $app->reviewed_at,
                $app->admin_notes,
                $app->rejection_reason,
            ));
        }

        fclose($output);
        exit;
    }

}
