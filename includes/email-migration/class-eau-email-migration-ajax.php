<?php
namespace EauSystem\EmailMigration;

/**
 * AJAX Handlers for Email Migration
 *
 * @since 1.62.0
 */
class Eau_Email_Migration_Ajax {

    /**
     * Register AJAX handlers
     */
    public static function register_ajax_handlers() {
        // User choice (new email or keep current)
        add_action('wp_ajax_eau_email_update_choice', array(__CLASS__, 'handle_choice'));

        // Verify code
        add_action('wp_ajax_eau_email_verify_code', array(__CLASS__, 'handle_verify_code'));

        // Resend code
        add_action('wp_ajax_eau_email_resend_code', array(__CLASS__, 'handle_resend_code'));

        // Cancel verification (go back)
        add_action('wp_ajax_eau_email_cancel_verification', array(__CLASS__, 'handle_cancel_verification'));

        // Admin: Run initial migration
        add_action('wp_ajax_eau_email_run_initial_migration', array(__CLASS__, 'handle_run_initial_migration'));

        // Admin: Get migration stats
        add_action('wp_ajax_eau_email_get_migration_stats', array(__CLASS__, 'handle_get_migration_stats'));

        // Admin: Reset user migration
        add_action('wp_ajax_eau_email_reset_user_migration', array(__CLASS__, 'handle_reset_user_migration'));

        // Admin: Rollback migration
        add_action('wp_ajax_eau_email_rollback_migration', array(__CLASS__, 'handle_rollback_migration'));
    }

    /**
     * Handle user choice (step 1)
     */
    public static function handle_choice() {
        check_ajax_referer('eau_email_migration_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in.', 'eau-system')), 401);
        }

        $user_id = get_current_user_id();
        $option = isset($_POST['email_option']) ? sanitize_text_field($_POST['email_option']) : '';

        if (!in_array($option, array('different', 'same'))) {
            wp_send_json_error(array('message' => __('Invalid option selected.', 'eau-system')), 400);
        }

        // Option: Keep current email
        if ($option === 'same') {
            $result = Eau_Email_Migration::skip_migration($user_id);

            if ($result === true) {
                wp_send_json_success(array(
                    'message' => __('Email confirmed successfully!', 'eau-system'),
                    'redirect' => home_url('/dashboard/'),
                ));
            } else {
                wp_send_json_error(array('message' => __('An error occurred. Please try again.', 'eau-system')), 500);
            }
        }

        // Option: Different email
        $new_email = isset($_POST['new_email']) ? sanitize_email($_POST['new_email']) : '';
        $confirm_email = isset($_POST['confirm_email']) ? sanitize_email($_POST['confirm_email']) : '';

        // Validate emails
        if (empty($new_email)) {
            wp_send_json_error(array('message' => __('Please enter your new email address.', 'eau-system')), 400);
        }

        if ($new_email !== $confirm_email) {
            wp_send_json_error(array('message' => __('Email addresses do not match.', 'eau-system')), 400);
        }

        // Check if same as current
        $user = get_userdata($user_id);
        if ($new_email === $user->user_email) {
            wp_send_json_error(array('message' => __('New email must be different from your current email. If you want to keep your current email, select the second option.', 'eau-system')), 400);
        }

        // Check rate limit
        if (!Eau_Email_Migration::check_rate_limit($user_id)) {
            wp_send_json_error(array('message' => __('Too many attempts. Please try again in an hour.', 'eau-system')), 429);
        }

        // Send verification code
        $result = Eau_Email_Migration::send_verification_code($user_id, $new_email);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), 400);
        }

        Eau_Email_Migration::increment_rate_limit($user_id);

        wp_send_json_success(array(
            'message' => __('Verification code sent! Please check your email.', 'eau-system'),
            'step' => 'verification',
            'email' => $new_email,
        ));
    }

    /**
     * Handle code verification (step 2)
     */
    public static function handle_verify_code() {
        check_ajax_referer('eau_email_migration_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in.', 'eau-system')), 401);
        }

        $user_id = get_current_user_id();
        $code = isset($_POST['code']) ? sanitize_text_field($_POST['code']) : '';

        // Validate code format
        if (empty($code) || !preg_match('/^[0-9]{6}$/', $code)) {
            wp_send_json_error(array('message' => __('Please enter a valid 6-digit code.', 'eau-system')), 400);
        }

        // Verify and migrate
        $result = Eau_Email_Migration::verify_code_and_migrate($user_id, $code);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), 400);
        }

        wp_send_json_success(array(
            'message' => __('Email updated successfully!', 'eau-system'),
            'redirect' => home_url('/dashboard/'),
        ));
    }

    /**
     * Handle resend code request
     */
    public static function handle_resend_code() {
        check_ajax_referer('eau_email_migration_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in.', 'eau-system')), 401);
        }

        $user_id = get_current_user_id();

        // Check rate limit
        if (!Eau_Email_Migration::check_rate_limit($user_id)) {
            wp_send_json_error(array('message' => __('Too many attempts. Please try again in an hour.', 'eau-system')), 429);
        }

        // Get pending email
        $pending_email = get_user_meta($user_id, Eau_Email_Migration_Database::META_PENDING_NEW_EMAIL, true);

        if (empty($pending_email)) {
            wp_send_json_error(array('message' => __('No pending verification found. Please start over.', 'eau-system')), 400);
        }

        // Send new code
        $result = Eau_Email_Migration::send_verification_code($user_id, $pending_email);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), 400);
        }

        Eau_Email_Migration::increment_rate_limit($user_id);

        wp_send_json_success(array(
            'message' => __('New verification code sent!', 'eau-system'),
        ));
    }

    /**
     * Handle cancel verification (go back to step 1)
     */
    public static function handle_cancel_verification() {
        check_ajax_referer('eau_email_migration_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in.', 'eau-system')), 401);
        }

        $user_id = get_current_user_id();

        // Reset status to pending
        update_user_meta($user_id, Eau_Email_Migration_Database::META_MIGRATION_STATUS, Eau_Email_Migration_Database::USER_STATUS_PENDING);

        // Clean up verification data
        delete_user_meta($user_id, Eau_Email_Migration_Database::META_VERIFICATION_CODE);
        delete_user_meta($user_id, Eau_Email_Migration_Database::META_VERIFICATION_EXPIRY);
        delete_user_meta($user_id, Eau_Email_Migration_Database::META_PENDING_NEW_EMAIL);

        wp_send_json_success(array(
            'message' => __('Verification cancelled.', 'eau-system'),
            'reload' => true,
        ));
    }

    /**
     * Admin: Run initial migration
     */
    public static function handle_run_initial_migration() {
        check_ajax_referer('eau_email_migration_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'eau-system')), 403);
        }

        $dry_run = isset($_POST['dry_run']) ? filter_var($_POST['dry_run'], FILTER_VALIDATE_BOOLEAN) : false;

        $stats = Eau_Email_Migration::run_initial_migration($dry_run);

        wp_send_json_success(array(
            'message' => $dry_run
                ? __('Dry run completed.', 'eau-system')
                : __('Initial migration completed.', 'eau-system'),
            'stats' => $stats,
            'dry_run' => $dry_run,
        ));
    }

    /**
     * Admin: Get migration statistics
     */
    public static function handle_get_migration_stats() {
        check_ajax_referer('eau_email_migration_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'eau-system')), 403);
        }

        $stats = Eau_Email_Migration_Database::get_stats();

        wp_send_json_success(array(
            'stats' => $stats,
        ));
    }

    /**
     * Admin: Reset user migration status
     */
    public static function handle_reset_user_migration() {
        check_ajax_referer('eau_email_migration_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'eau-system')), 403);
        }

        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

        if (!$user_id) {
            wp_send_json_error(array('message' => __('Invalid user ID.', 'eau-system')), 400);
        }

        $result = Eau_Email_Migration::reset_user_migration($user_id);

        if ($result === true) {
            wp_send_json_success(array(
                'message' => __('User migration status reset successfully.', 'eau-system'),
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to reset user migration status.', 'eau-system')), 500);
        }
    }

    /**
     * Admin: Rollback a migration
     */
    public static function handle_rollback_migration() {
        check_ajax_referer('eau_email_migration_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'eau-system')), 403);
        }

        $log_id = isset($_POST['log_id']) ? intval($_POST['log_id']) : 0;

        if (!$log_id) {
            wp_send_json_error(array('message' => __('Invalid log ID.', 'eau-system')), 400);
        }

        $result = Eau_Email_Migration_Replacer::rollback_migration($log_id, get_current_user_id());

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), 400);
        }

        wp_send_json_success(array(
            'message' => __('Migration rolled back successfully.', 'eau-system'),
        ));
    }
}
