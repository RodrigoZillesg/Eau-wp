<?php
namespace EauSystem;

use EauSystem\Email\Email_Service;

/**
 * AJAX handlers for Public Registration
 *
 * Handles:
 * - Institution search (public)
 * - Email availability check
 * - Registration processing
 * - reCAPTCHA verification
 *
 * @since 1.49.0
 */
class Eau_Public_Registration_Ajax {

    /**
     * Register AJAX handlers
     */
    public static function register_handlers() {
        // Public handlers (no priv)
        add_action('wp_ajax_nopriv_eau_search_institutions_public', array(__CLASS__, 'search_institutions'));
        add_action('wp_ajax_nopriv_eau_check_email_exists', array(__CLASS__, 'check_email_exists'));
        add_action('wp_ajax_nopriv_eau_process_registration', array(__CLASS__, 'process_registration'));

        // Also for logged in users (in case of testing)
        add_action('wp_ajax_eau_search_institutions_public', array(__CLASS__, 'search_institutions'));
        add_action('wp_ajax_eau_check_email_exists', array(__CLASS__, 'check_email_exists'));
        add_action('wp_ajax_eau_process_registration', array(__CLASS__, 'process_registration'));
    }

    /**
     * Search institutions (public)
     */
    public static function search_institutions() {
        // Verify nonce
        check_ajax_referer('eau_public_registration', 'nonce');

        $query = isset($_POST['query']) ? sanitize_text_field($_POST['query']) : '';

        if (strlen($query) < 2) {
            wp_send_json_success(array('institutions' => array()));
        }

        global $wpdb;

        // Search in institutions CPT
        $institutions = $wpdb->get_results($wpdb->prepare(
            "SELECT ID as id, post_title as name
            FROM {$wpdb->posts}
            WHERE post_type = 'institutions'
            AND post_status = 'publish'
            AND post_title LIKE %s
            ORDER BY post_title ASC
            LIMIT 20",
            '%' . $wpdb->esc_like($query) . '%'
        ));

        wp_send_json_success(array('institutions' => $institutions));
    }

    /**
     * Check if email already exists
     */
    public static function check_email_exists() {
        // Verify nonce
        check_ajax_referer('eau_public_registration', 'nonce');

        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';

        if (empty($email)) {
            wp_send_json_error(array('message' => 'Email is required'));
        }

        $exists = email_exists($email);

        wp_send_json_success(array('exists' => (bool) $exists));
    }

    /**
     * Process registration
     */
    public static function process_registration() {
        // Verify nonce
        if (!isset($_POST['eau_registration_nonce']) || !wp_verify_nonce($_POST['eau_registration_nonce'], 'eau_public_registration')) {
            wp_send_json_error(array('message' => 'Security check failed. Please refresh and try again.'));
        }

        // Rate limiting (simple implementation)
        $ip = $_SERVER['REMOTE_ADDR'];
        $rate_key = 'eau_reg_rate_' . md5($ip);
        $attempts = get_transient($rate_key);

        if ($attempts && $attempts > 5) {
            wp_send_json_error(array('message' => 'Too many attempts. Please try again in a few minutes.'));
        }

        set_transient($rate_key, ($attempts ? $attempts + 1 : 1), 300);

        // Verify reCAPTCHA if configured
        $recaptcha_secret = get_option(Eau_Public_Registration::RECAPTCHA_SECRET_KEY_OPTION, '');
        if (!empty($recaptcha_secret)) {
            $recaptcha_token = isset($_POST['recaptcha_token']) ? sanitize_text_field($_POST['recaptcha_token']) : '';

            if (empty($recaptcha_token)) {
                wp_send_json_error(array('message' => 'reCAPTCHA verification failed. Please try again.'));
            }

            $recaptcha_response = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', array(
                'body' => array(
                    'secret' => $recaptcha_secret,
                    'response' => $recaptcha_token,
                    'remoteip' => $ip,
                ),
            ));

            if (is_wp_error($recaptcha_response)) {
                wp_send_json_error(array('message' => 'reCAPTCHA verification failed. Please try again.'));
            }

            $recaptcha_data = json_decode(wp_remote_retrieve_body($recaptcha_response), true);

            if (!isset($recaptcha_data['success']) || !$recaptcha_data['success'] || $recaptcha_data['score'] < 0.5) {
                wp_send_json_error(array('message' => 'reCAPTCHA verification failed. Please try again.'));
            }
        }

        // Validate required fields (company_name removed in v1.51.23)
        $required_fields = array('first_name', 'last_name', 'email', 'position', 'state', 'country', 'password', 'password_confirm');
        $errors = array();

        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
            }
        }

        if (!empty($errors)) {
            wp_send_json_error(array('message' => implode('. ', $errors)));
        }

        // Sanitize inputs
        $first_name = sanitize_text_field($_POST['first_name']);
        $last_name = sanitize_text_field($_POST['last_name']);
        $email = sanitize_email($_POST['email']);
        $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
        $position = sanitize_text_field($_POST['position']);
        $state = sanitize_text_field($_POST['state']);
        $country = sanitize_text_field($_POST['country']);
        $password = $_POST['password'];
        $password_confirm = $_POST['password_confirm'];
        $newsletters = isset($_POST['newsletters']) ? array_map('sanitize_text_field', (array) $_POST['newsletters']) : array();
        $terms = isset($_POST['terms']) && $_POST['terms'];

        // Validate email format
        if (!is_email($email)) {
            wp_send_json_error(array('message' => 'Please enter a valid email address.'));
        }

        // Check if email exists
        if (email_exists($email)) {
            wp_send_json_error(array('message' => 'This email is already registered. Please log in or use a different email.'));
        }

        // Validate password
        if (strlen($password) < 8) {
            wp_send_json_error(array('message' => 'Password must be at least 8 characters.'));
        }

        // Validate password match
        if ($password !== $password_confirm) {
            wp_send_json_error(array('message' => 'Passwords do not match.'));
        }

        // Validate terms
        if (!$terms) {
            wp_send_json_error(array('message' => 'You must accept the terms and conditions.'));
        }

        // Create user
        $username = self::generate_username($email);

        $user_id = wp_create_user($username, $password, $email);

        if (is_wp_error($user_id)) {
            wp_send_json_error(array('message' => 'Failed to create account: ' . $user_id->get_error_message()));
        }

        // Update WordPress native user data
        wp_update_user(array(
            'ID' => $user_id,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'display_name' => $first_name . ' ' . $last_name,
            'nickname' => $first_name,
            'role' => 'subscriber',
        ));

        // Add user meta (following existing pattern with mem_* prefix)
        update_user_meta($user_id, 'mem_type', 'Member'); // Note: "Member" with capital M
        update_user_meta($user_id, 'mem_status', 'pending'); // Pending until membership approved
        update_user_meta($user_id, 'mem_membership_status', 'pending'); // Membership status pending (v1.51.52)
        update_user_meta($user_id, 'mem_firstname', $first_name);
        update_user_meta($user_id, 'mem_lastname', $last_name);
        update_user_meta($user_id, 'mem_phone', $phone); // Standard phone field used in profile
        update_user_meta($user_id, 'mem_position', $position);
        update_user_meta($user_id, 'mem_state', $state);
        update_user_meta($user_id, 'mem_country', $country);
        update_user_meta($user_id, 'mem_userid', $user_id);

        // Note: Company/Institution relationship removed in v1.51.23
        // Users will select institution when applying for membership

        // Store newsletter subscriptions
        if (!empty($newsletters)) {
            update_user_meta($user_id, 'mem_newsletter_subscriptions', json_encode($newsletters));
        }

        // Store registration date
        update_user_meta($user_id, 'mem_registration_date', current_time('mysql'));
        update_user_meta($user_id, 'mem_registration_method', 'public_registration');

        // Log the user in
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);

        // Clear rate limit
        delete_transient($rate_key);

        // Send welcome email
        self::send_welcome_email($user_id, $first_name, $email);

        wp_send_json_success(array(
            'message' => 'Account created successfully!',
            'user_id' => $user_id,
            'dashboard_url' => home_url('/dashboard/'),
            'membership_url' => home_url('/membership-selection/'),
        ));
    }

    /**
     * Generate username from email
     *
     * @param string $email Email address
     * @return string Unique username
     */
    private static function generate_username($email) {
        // Use part before @ as base
        $base = sanitize_user(explode('@', $email)[0], true);

        // Ensure it's not empty
        if (empty($base)) {
            $base = 'user';
        }

        // Check if exists and add number if needed
        $username = $base;
        $counter = 1;

        while (username_exists($username)) {
            $username = $base . $counter;
            $counter++;
        }

        return $username;
    }

    /**
     * Send welcome email
     *
     * Uses Email_Service to respect dev/prod environment settings
     *
     * @param int $user_id User ID
     * @param string $first_name First name
     * @param string $email Email address
     */
    private static function send_welcome_email($user_id, $first_name, $email) {
        $subject = 'Welcome to English Australia';

        // HTML content for Email_Service
        $content = sprintf(
            '<h1>Welcome to English Australia!</h1>
            <p>Dear %s,</p>
            <p>Thank you for creating your account with English Australia!</p>
            <p>Your account has been created successfully. You can now log in to access our member resources.</p>
            <h3>Next Steps:</h3>
            <ol>
                <li>Visit the Member Centre to apply for a membership type</li>
                <li>Complete your membership application</li>
                <li>Once approved, you\'ll have full access to member benefits</li>
            </ol>
            <p>If you have any questions, please don\'t hesitate to contact us.</p>
            <p>Best regards,<br>The English Australia Team</p>',
            esc_html($first_name)
        );

        // Use Email_Service to respect dev/prod settings
        Email_Service::send($email, $subject, $content);
    }
}
