<?php
namespace EauSystem\Ajax;

use EauSystem\Eau_Membership_Database;
use EauSystem\Eau_Membership_Types;
use EauSystem\Email\Email_Membership;

/**
 * AJAX handlers for Membership Selection
 *
 * Handles:
 * - Application submission
 * - Document uploads
 * - Fee calculations
 *
 * @since 1.49.0
 */
class Eau_Membership_Selection_Ajax {

    /**
     * Register AJAX handlers
     */
    public static function register_handlers() {
        // Logged in users only
        add_action('wp_ajax_eau_submit_membership_application', array(__CLASS__, 'submit_application'));
        add_action('wp_ajax_eau_calculate_membership_fee', array(__CLASS__, 'calculate_fee'));
    }

    /**
     * Submit membership application
     */
    public static function submit_application() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'eau_membership_selection')) {
            wp_send_json_error(array('message' => 'Security check failed. Please refresh and try again.'));
        }

        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'You must be logged in to submit an application.'));
        }

        $user_id = get_current_user_id();
        $user = get_userdata($user_id);

        // Check for existing pending application
        $existing = self::get_pending_application($user_id, $user->user_email);
        if ($existing) {
            wp_send_json_error(array('message' => 'You already have a pending application. Please wait for it to be reviewed.'));
        }

        // Validate membership type
        $membership_type = isset($_POST['membership_type']) ? sanitize_text_field($_POST['membership_type']) : '';
        if (!Eau_Membership_Types::is_valid_type($membership_type)) {
            wp_send_json_error(array('message' => 'Invalid membership type selected.'));
        }

        // Validate required fields
        $required_fields = array('first_name', 'last_name', 'position', 'company_name', 'state', 'country');
        $errors = array();

        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
            }
        }

        // Validate CRICOS for Full Provider and Associate
        $cricos_types = array('full_provider', 'associate_access');
        if (in_array($membership_type, $cricos_types)) {
            if (empty($_POST['cricos_number'])) {
                $errors[] = 'CRICOS Provider Number is required';
            } else {
                // Validate CRICOS format
                $cricos = sanitize_text_field($_POST['cricos_number']);
                if (!preg_match('/^[0-9]{5}[A-Z]$/i', $cricos)) {
                    $errors[] = 'Invalid CRICOS Provider Number format';
                }
            }
        }

        if (!empty($errors)) {
            wp_send_json_error(array('message' => implode('. ', $errors)));
        }

        // Sanitize inputs
        $first_name = sanitize_text_field($_POST['first_name']);
        $last_name = sanitize_text_field($_POST['last_name']);
        $email = $user->user_email; // Use logged in user's email
        $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
        $position = sanitize_text_field($_POST['position']);
        $company_name = sanitize_text_field($_POST['company_name']);
        $state = sanitize_text_field($_POST['state']);
        $country = sanitize_text_field($_POST['country']);
        $website = isset($_POST['website']) ? esc_url_raw($_POST['website']) : '';
        $additional_info = isset($_POST['additional_info']) ? sanitize_textarea_field($_POST['additional_info']) : '';
        $cricos_number = isset($_POST['cricos_number']) ? sanitize_text_field($_POST['cricos_number']) : '';
        $cricos_sites = isset($_POST['cricos_sites']) ? intval($_POST['cricos_sites']) : 1;

        // Prepare membership data JSON
        $membership_data = array(
            'website' => $website,
            'additional_info' => $additional_info,
            'cricos_number' => $cricos_number,
            'cricos_sites' => $cricos_sites,
        );

        // Handle document uploads
        $documents = array();
        $upload_dir = wp_upload_dir();

        // Handle required documents
        if (!empty($_FILES['required_docs'])) {
            foreach ($_FILES['required_docs']['name'] as $key => $name) {
                if (!empty($name)) {
                    $file = array(
                        'name' => $_FILES['required_docs']['name'][$key],
                        'type' => $_FILES['required_docs']['type'][$key],
                        'tmp_name' => $_FILES['required_docs']['tmp_name'][$key],
                        'error' => $_FILES['required_docs']['error'][$key],
                        'size' => $_FILES['required_docs']['size'][$key],
                    );

                    $uploaded = self::handle_file_upload($file);
                    if ($uploaded) {
                        $documents['required'][] = $uploaded;
                    }
                }
            }
        }

        // Handle supporting documents
        foreach ($_FILES as $key => $file) {
            if (strpos($key, 'supporting_docs_') === 0 && !empty($file['name'])) {
                $uploaded = self::handle_file_upload($file);
                if ($uploaded) {
                    $documents['supporting'][] = $uploaded;
                }
            }
        }

        // Insert application into database
        global $wpdb;
        $table_name = Eau_Membership_Database::get_table_name(Eau_Membership_Database::TABLE_APPLICATIONS);

        $result = $wpdb->insert(
            $table_name,
            array(
                'user_id' => $user_id,
                'email' => $email,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'phone' => $phone,
                'position' => $position,
                'company_name' => $company_name,
                'state' => $state,
                'country' => $country,
                'membership_type' => $membership_type,
                'membership_data' => json_encode($membership_data),
                'documents' => json_encode($documents),
                'status' => 'pending',
                'submitted_at' => current_time('mysql'),
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );

        if ($result === false) {
            wp_send_json_error(array('message' => 'Failed to submit application. Please try again.'));
        }

        $application_id = $wpdb->insert_id;

        // Send notification email to admin (uses Email_Membership for dev/prod handling)
        Email_Membership::send_application_notification_to_admin($application_id);

        // Send confirmation email to user (uses Email_Membership for dev/prod handling)
        Email_Membership::send_application_received($application_id);

        wp_send_json_success(array(
            'message' => 'Application submitted successfully!',
            'application_id' => $application_id,
        ));
    }

    /**
     * Calculate membership fee
     */
    public static function calculate_fee() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'eau_membership_selection')) {
            wp_send_json_error(array('message' => 'Security check failed.'));
        }

        $membership_type = isset($_POST['membership_type']) ? sanitize_text_field($_POST['membership_type']) : '';
        $cricos_sites = isset($_POST['cricos_sites']) ? intval($_POST['cricos_sites']) : 1;

        $type_data = Eau_Membership_Types::get_by_key($membership_type);

        if (!$type_data) {
            wp_send_json_error(array('message' => 'Invalid membership type.'));
        }

        if ($type_data->fee_is_variable) {
            $fee = Eau_Membership_Types::calculate_full_provider_fee($cricos_sites);
        } else {
            $fee = $type_data->fee_amount;
        }

        wp_send_json_success(array(
            'fee' => $fee,
            'formatted_fee' => '$' . number_format($fee, 2) . ' ' . $type_data->fee_currency,
            'includes_gst' => $type_data->fee_includes_gst,
        ));
    }

    /**
     * Get pending application for user
     *
     * @param int $user_id User ID
     * @param string $email User email
     * @return object|null
     */
    private static function get_pending_application($user_id, $email) {
        global $wpdb;

        $table_name = Eau_Membership_Database::get_table_name(Eau_Membership_Database::TABLE_APPLICATIONS);

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name
            WHERE (user_id = %d OR email = %s)
            AND status IN ('pending', 'under_review')
            ORDER BY submitted_at DESC
            LIMIT 1",
            $user_id,
            $email
        ));
    }

    /**
     * Handle file upload
     *
     * @param array $file File array from $_FILES
     * @return array|false Upload result or false on failure
     */
    private static function handle_file_upload($file) {
        // Validate file
        $allowed_types = array(
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'image/jpeg',
            'image/png',
        );

        if (!in_array($file['type'], $allowed_types)) {
            return false;
        }

        // Max 10MB
        if ($file['size'] > 10 * 1024 * 1024) {
            return false;
        }

        // Use WordPress upload handler
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        $upload_overrides = array(
            'test_form' => false,
            'mimes' => array(
                'pdf' => 'application/pdf',
                'doc' => 'application/msword',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'jpg|jpeg' => 'image/jpeg',
                'png' => 'image/png',
            ),
        );

        $uploaded = wp_handle_upload($file, $upload_overrides);

        if (isset($uploaded['error'])) {
            return false;
        }

        return array(
            'name' => $file['name'],
            'url' => $uploaded['url'],
            'path' => $uploaded['file'],
            'type' => $uploaded['type'],
        );
    }

}
