<?php
namespace EauSystem\EmailMigration;

use EauSystem\Eau_User_Institution_Helper;
use EauSystem\Email\Email_Service;

/**
 * Main orchestrator for Email Migration System
 *
 * Handles:
 * - Initial migration (copy emails to secondary field)
 * - Login interception (redirect to update page)
 * - Verification code generation and validation
 * - Status management
 *
 * @since 1.62.0
 */
class Eau_Email_Migration {

    /**
     * Page slug for email update
     */
    const UPDATE_PAGE_SLUG = 'update-email';

    /**
     * Register hooks
     */
    public static function register() {
        // Hook after login to check if user needs to update email
        add_action('template_redirect', array(__CLASS__, 'maybe_redirect_to_update'), 1);

        // Register shortcode
        add_shortcode('eau_email_update', array('EauSystem\\EmailMigration\\Eau_Email_Update_Page', 'render'));

        // Register AJAX handlers
        Eau_Email_Migration_Ajax::register_ajax_handlers();
    }

    /**
     * Check if user needs to update email and redirect if necessary
     */
    public static function maybe_redirect_to_update() {
        // Not logged in - continue normally
        if (!is_user_logged_in()) {
            return;
        }

        $user_id = get_current_user_id();

        // Check if user is admin - they don't need to migrate
        if (self::is_exempt_user($user_id)) {
            return;
        }

        // Check migration status
        $status = get_user_meta($user_id, Eau_Email_Migration_Database::META_MIGRATION_STATUS, true);

        // If already verified or skipped, continue normally
        if (in_array($status, array(
            Eau_Email_Migration_Database::USER_STATUS_VERIFIED,
            Eau_Email_Migration_Database::USER_STATUS_SKIPPED
        ))) {
            return;
        }

        // Check if already on update page to avoid redirect loop
        global $post;
        if ($post && $post->post_name === self::UPDATE_PAGE_SLUG) {
            return;
        }

        // Also check by URL in case post object is not available
        $current_url = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($current_url, '/' . self::UPDATE_PAGE_SLUG) !== false) {
            return;
        }

        // Allow AJAX requests to pass through
        if (wp_doing_ajax()) {
            return;
        }

        // Allow WP Admin for authorized users
        if (is_admin()) {
            return;
        }

        // Redirect to update page
        wp_safe_redirect(home_url('/' . self::UPDATE_PAGE_SLUG . '/'));
        exit;
    }

    /**
     * Check if user is exempt from migration (superAdmin, Admin, institutionAdmin, or in exempt list)
     *
     * @since 1.66.8 - Uses centralized helper to support multi-type mem_type
     * @since 1.66.8 - Added support for exempt email list from settings
     * @param int $user_id User ID
     * @return bool
     */
    public static function is_exempt_user($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        // v1.66.8 - Check if user's email is in the exempt list (settings)
        $user = get_userdata($user_id);
        if ($user && \EauSystem\Eau_Settings::is_email_exempt_from_migration($user->user_email)) {
            return true;
        }

        // v1.66.8 - Verificação direta do mem_type (suporta string e array)
        $mem_type = get_user_meta($user_id, 'mem_type', true);
        $admin_types = array('superAdmin', 'Admin', 'institutionAdmin');

        if (is_array($mem_type)) {
            if (!empty(array_intersect($mem_type, $admin_types))) {
                return true;
            }
        } else {
            if (in_array($mem_type, $admin_types)) {
                return true;
            }
        }

        // Fallback: Use centralized helper which supports multi-type mem_type
        if (Eau_User_Institution_Helper::has_admin_access($user_id)) {
            return true;
        }

        // Also exempt institution admins (via mem_managed_institutions or primary contact)
        if (Eau_User_Institution_Helper::is_institution_admin($user_id)) {
            return true;
        }

        return false;
    }

    /**
     * Check if user needs email update
     *
     * @param int $user_id User ID
     * @return bool
     */
    public static function needs_email_update($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        // Exempt users don't need update
        if (self::is_exempt_user($user_id)) {
            return false;
        }

        $status = get_user_meta($user_id, Eau_Email_Migration_Database::META_MIGRATION_STATUS, true);

        // Needs update if status is not verified or skipped
        return !in_array($status, array(
            Eau_Email_Migration_Database::USER_STATUS_VERIFIED,
            Eau_Email_Migration_Database::USER_STATUS_SKIPPED
        ));
    }

    /**
     * Run initial migration for all users
     * Copies current email to secondary email field and sets status to pending
     *
     * @param bool $dry_run If true, only count users without making changes
     * @return array Stats about the migration
     */
    public static function run_initial_migration($dry_run = false) {
        global $wpdb;

        $stats = array(
            'total_users' => 0,
            'migrated' => 0,
            'skipped_exempt' => 0,
            'skipped_already_done' => 0,
            'errors' => array(),
        );

        // Get all users
        $users = get_users(array(
            'fields' => array('ID', 'user_email'),
        ));

        $stats['total_users'] = count($users);

        foreach ($users as $user) {
            // Skip exempt users (superAdmin, Admin)
            if (self::is_exempt_user($user->ID)) {
                $stats['skipped_exempt']++;
                continue;
            }

            // Check if already has migration status
            $existing_status = get_user_meta($user->ID, Eau_Email_Migration_Database::META_MIGRATION_STATUS, true);
            if (!empty($existing_status)) {
                $stats['skipped_already_done']++;
                continue;
            }

            if (!$dry_run) {
                // Copy email to secondary field
                update_user_meta($user->ID, Eau_Email_Migration_Database::META_SECONDARY_EMAIL, $user->user_email);

                // Set status to pending
                update_user_meta($user->ID, Eau_Email_Migration_Database::META_MIGRATION_STATUS, Eau_Email_Migration_Database::USER_STATUS_PENDING);

                // Set migration date
                update_user_meta($user->ID, Eau_Email_Migration_Database::META_MIGRATION_DATE, current_time('mysql'));
            }

            $stats['migrated']++;
        }

        return $stats;
    }

    /**
     * Generate verification code and send to new email
     *
     * @param int $user_id User ID
     * @param string $new_email New email address
     * @return true|\WP_Error
     */
    public static function send_verification_code($user_id, $new_email) {
        // Validate email format
        if (!is_email($new_email)) {
            return new \WP_Error('invalid_email', __('Invalid email format.', 'eau-system'));
        }

        // Check if email already exists
        $existing_user = get_user_by('email', $new_email);
        if ($existing_user && $existing_user->ID !== $user_id) {
            return new \WP_Error('email_exists', __('This email is already in use by another account.', 'eau-system'));
        }

        // Generate 6-digit code
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Calculate expiry time (15 minutes)
        $expiry = date('Y-m-d H:i:s', time() + Eau_Email_Migration_Database::CODE_EXPIRY_SECONDS);

        // Save code and pending email
        update_user_meta($user_id, Eau_Email_Migration_Database::META_VERIFICATION_CODE, $code);
        update_user_meta($user_id, Eau_Email_Migration_Database::META_VERIFICATION_EXPIRY, $expiry);
        update_user_meta($user_id, Eau_Email_Migration_Database::META_PENDING_NEW_EMAIL, $new_email);
        update_user_meta($user_id, Eau_Email_Migration_Database::META_MIGRATION_STATUS, Eau_Email_Migration_Database::USER_STATUS_AWAITING);

        // Send email with code
        $user = get_userdata($user_id);
        $first_name = $user->first_name ?: $user->display_name;

        $subject = sprintf(__('Your Verification Code - %s', 'eau-system'), $code);

        $content = sprintf(
            '<p>Hi %s,</p>
            <p>You requested to update your email address on our platform.</p>
            <p style="font-size: 24px; font-weight: bold; text-align: center; padding: 20px; background: #f5f5f5; border-radius: 8px; letter-spacing: 4px;">%s</p>
            <p>This code expires in 15 minutes.</p>
            <p>If you didn\'t request this change, please ignore this email.</p>',
            esc_html($first_name),
            $code
        );

        $sent = Email_Service::send($new_email, $subject, $content);

        if (!$sent) {
            return new \WP_Error('email_failed', __('Failed to send verification email. Please try again.', 'eau-system'));
        }

        return true;
    }

    /**
     * Verify code and complete email migration
     *
     * @param int $user_id User ID
     * @param string $code Verification code
     * @return true|\WP_Error
     */
    public static function verify_code_and_migrate($user_id, $code) {
        // Get stored code and expiry
        $stored_code = get_user_meta($user_id, Eau_Email_Migration_Database::META_VERIFICATION_CODE, true);
        $expiry = get_user_meta($user_id, Eau_Email_Migration_Database::META_VERIFICATION_EXPIRY, true);
        $new_email = get_user_meta($user_id, Eau_Email_Migration_Database::META_PENDING_NEW_EMAIL, true);

        // Check if code exists
        if (empty($stored_code) || empty($new_email)) {
            return new \WP_Error('no_pending', __('No pending verification found. Please request a new code.', 'eau-system'));
        }

        // Check if code has expired
        if (strtotime($expiry) < time()) {
            // Clean up expired data
            delete_user_meta($user_id, Eau_Email_Migration_Database::META_VERIFICATION_CODE);
            delete_user_meta($user_id, Eau_Email_Migration_Database::META_VERIFICATION_EXPIRY);

            return new \WP_Error('code_expired', __('Verification code has expired. Please request a new code.', 'eau-system'));
        }

        // Verify code
        if ($code !== $stored_code) {
            return new \WP_Error('invalid_code', __('Invalid verification code. Please try again.', 'eau-system'));
        }

        // Get current email for replacement
        $user = get_userdata($user_id);
        $old_email = $user->user_email;

        // Execute global replacement
        $result = Eau_Email_Migration_Replacer::replace_all($user_id, $old_email, $new_email);

        if (is_wp_error($result)) {
            return $result;
        }

        // Update migration status
        update_user_meta($user_id, Eau_Email_Migration_Database::META_MIGRATION_STATUS, Eau_Email_Migration_Database::USER_STATUS_VERIFIED);
        update_user_meta($user_id, Eau_Email_Migration_Database::META_MIGRATION_DATE, current_time('mysql'));
        update_user_meta($user_id, Eau_Email_Migration_Database::META_MIGRATION_OLD, $old_email);

        // Clean up verification data
        delete_user_meta($user_id, Eau_Email_Migration_Database::META_VERIFICATION_CODE);
        delete_user_meta($user_id, Eau_Email_Migration_Database::META_VERIFICATION_EXPIRY);
        delete_user_meta($user_id, Eau_Email_Migration_Database::META_PENDING_NEW_EMAIL);

        return true;
    }

    /**
     * Mark user as skipped (their current email is already personal)
     *
     * @param int $user_id User ID
     * @return true
     */
    public static function skip_migration($user_id) {
        $user = get_userdata($user_id);

        // Copy current email to secondary field if not already set
        $secondary = get_user_meta($user_id, Eau_Email_Migration_Database::META_SECONDARY_EMAIL, true);
        if (empty($secondary)) {
            update_user_meta($user_id, Eau_Email_Migration_Database::META_SECONDARY_EMAIL, $user->user_email);
        }

        // Update status to skipped
        update_user_meta($user_id, Eau_Email_Migration_Database::META_MIGRATION_STATUS, Eau_Email_Migration_Database::USER_STATUS_SKIPPED);
        update_user_meta($user_id, Eau_Email_Migration_Database::META_MIGRATION_DATE, current_time('mysql'));

        return true;
    }

    /**
     * Reset user migration status (admin function)
     *
     * @param int $user_id User ID
     * @return true
     */
    public static function reset_user_migration($user_id) {
        // Set status back to pending
        update_user_meta($user_id, Eau_Email_Migration_Database::META_MIGRATION_STATUS, Eau_Email_Migration_Database::USER_STATUS_PENDING);

        // Clean up any verification data
        delete_user_meta($user_id, Eau_Email_Migration_Database::META_VERIFICATION_CODE);
        delete_user_meta($user_id, Eau_Email_Migration_Database::META_VERIFICATION_EXPIRY);
        delete_user_meta($user_id, Eau_Email_Migration_Database::META_PENDING_NEW_EMAIL);

        return true;
    }

    /**
     * Check rate limit for verification code requests
     *
     * @param int $user_id User ID
     * @return bool True if allowed, false if rate limited
     */
    public static function check_rate_limit($user_id) {
        $key = 'eau_email_verify_attempts_' . $user_id;
        $attempts = get_transient($key);

        if ($attempts >= 5) {
            return false;
        }

        return true;
    }

    /**
     * Increment rate limit counter
     *
     * @param int $user_id User ID
     */
    public static function increment_rate_limit($user_id) {
        $key = 'eau_email_verify_attempts_' . $user_id;
        $attempts = get_transient($key) ?: 0;
        set_transient($key, $attempts + 1, HOUR_IN_SECONDS);
    }
}
