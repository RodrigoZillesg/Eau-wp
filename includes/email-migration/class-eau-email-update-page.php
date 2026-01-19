<?php
namespace EauSystem\EmailMigration;

/**
 * Email Update Page
 *
 * Renders the email update form with two steps:
 * 1. Choose between new email or keep current
 * 2. Verify new email with code (if new email chosen)
 *
 * @since 1.62.0
 */
class Eau_Email_Update_Page {

    /**
     * Common personal email domains
     * Users with these domains can choose to keep their email
     */
    const PERSONAL_EMAIL_DOMAINS = array(
        // Google
        'gmail.com', 'googlemail.com',
        // Microsoft
        'outlook.com', 'hotmail.com', 'live.com', 'msn.com', 'hotmail.co.uk', 'hotmail.fr', 'outlook.co.uk',
        // Yahoo
        'yahoo.com', 'yahoo.co.uk', 'yahoo.com.br', 'yahoo.fr', 'ymail.com', 'rocketmail.com',
        // Apple
        'icloud.com', 'me.com', 'mac.com',
        // Other popular
        'aol.com', 'protonmail.com', 'proton.me', 'zoho.com', 'mail.com', 'gmx.com', 'gmx.net',
        'fastmail.com', 'tutanota.com', 'hey.com', 'pm.me',
        // Regional
        'uol.com.br', 'bol.com.br', 'terra.com.br', 'ig.com.br',
        'btinternet.com', 'virginmedia.com', 'sky.com',
        'orange.fr', 'wanadoo.fr', 'free.fr', 'laposte.net',
        'web.de', 't-online.de',
    );

    /**
     * Check if email appears to be a personal email
     *
     * @param string $email Email address
     * @return bool True if appears to be personal
     */
    public static function is_personal_email($email) {
        $domain = strtolower(substr(strrchr($email, '@'), 1));
        return in_array($domain, self::PERSONAL_EMAIL_DOMAINS);
    }

    /**
     * Render the email update page
     *
     * @param array $atts Shortcode attributes
     * @return string HTML content
     */
    public static function render($atts = array()) {
        // Check if user is logged in
        if (!is_user_logged_in()) {
            return self::render_not_logged_in();
        }

        // Check if user needs to update
        if (!Eau_Email_Migration::needs_email_update()) {
            wp_safe_redirect(home_url('/dashboard/'));
            exit;
        }

        self::enqueue_assets();

        // Add body class to hide navigation
        add_filter('body_class', function($classes) {
            $classes[] = 'eau-email-update-locked';
            return $classes;
        });

        $user = wp_get_current_user();
        $status = get_user_meta($user->ID, Eau_Email_Migration_Database::META_MIGRATION_STATUS, true);
        $pending_email = get_user_meta($user->ID, Eau_Email_Migration_Database::META_PENDING_NEW_EMAIL, true);

        ob_start();
        ?>
        <div class="eau-email-update-container">
            <div class="eau-email-update-card">
                <!-- Header -->
                <div class="eau-email-update-header">
                    <div class="eau-email-update-icon">
                        <i data-lucide="mail"></i>
                    </div>
                    <h1 class="eau-email-update-title">Update Your Email</h1>
                    <p class="eau-email-update-subtitle">
                        We're updating our system to use personal email addresses.
                        This ensures you maintain access even if you change institutions.
                    </p>
                </div>

                <!-- Body -->
                <div class="eau-email-update-body">
                    <?php if ($status === Eau_Email_Migration_Database::USER_STATUS_AWAITING && !empty($pending_email)): ?>
                        <!-- Step 2: Verification -->
                        <?php echo self::render_verification_step($pending_email); ?>
                    <?php else: ?>
                        <!-- Step 1: Choose option -->
                        <?php echo self::render_choice_step($user); ?>
                    <?php endif; ?>
                </div>

                <!-- Footer -->
                <div class="eau-email-update-footer">
                    <p class="eau-text-muted">
                        <i data-lucide="info"></i>
                        Your institutional email will be saved as a secondary email for reference.
                    </p>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render the choice step (step 1)
     *
     * @param \WP_User $user Current user
     * @return string HTML
     */
    private static function render_choice_step($user) {
        $is_personal = self::is_personal_email($user->user_email);
        $email_domain = strtolower(substr(strrchr($user->user_email, '@'), 1));

        ob_start();
        ?>
        <div class="eau-email-update-current">
            <span class="eau-email-update-label">Current Email:</span>
            <span class="eau-email-update-value" id="current-email">
                <?php echo esc_html($user->user_email); ?>
            </span>
            <?php if (!$is_personal): ?>
                <span class="eau-email-institutional-warning">
                    <i data-lucide="alert-triangle"></i>
                    This appears to be an institutional email
                </span>
            <?php endif; ?>
        </div>

        <form id="eau-email-update-form" class="eau-email-update-form">
            <input type="hidden" name="action" value="eau_email_update_choice">
            <?php wp_nonce_field('eau_email_migration_nonce', 'nonce'); ?>

            <!-- Option 1: Different email -->
            <label class="eau-radio-card" id="option-different">
                <input type="radio" name="email_option" value="different" required <?php echo !$is_personal ? 'checked' : ''; ?>>
                <div class="eau-radio-card-content">
                    <div class="eau-radio-card-header">
                        <span class="eau-radio-indicator"></span>
                        <span class="eau-radio-title">
                            <?php if ($is_personal): ?>
                                I want to use a different personal email
                            <?php else: ?>
                                Enter your personal email address
                            <?php endif; ?>
                        </span>
                    </div>
                    <?php if (!$is_personal): ?>
                        <p class="eau-radio-card-description">
                            Please provide a personal email (e.g., Gmail, Outlook, Yahoo) that you'll keep even if you change institutions.
                        </p>
                    <?php endif; ?>
                    <div class="eau-radio-card-body eau-new-email-fields" style="<?php echo !$is_personal ? 'display: block;' : 'display: none;'; ?>">
                        <div class="eau-form-field">
                            <label class="eau-form-label">New Personal Email</label>
                            <input type="email"
                                   class="eau-form-input"
                                   id="new-email"
                                   name="new_email"
                                   placeholder="your.personal@gmail.com"
                                   autocomplete="email"
                                   <?php echo !$is_personal ? 'required' : ''; ?>>
                        </div>
                        <div class="eau-form-field">
                            <label class="eau-form-label">Confirm Email</label>
                            <input type="email"
                                   class="eau-form-input"
                                   id="confirm-email"
                                   name="confirm_email"
                                   placeholder="your.personal@gmail.com"
                                   autocomplete="email"
                                   <?php echo !$is_personal ? 'required' : ''; ?>>
                        </div>
                    </div>
                </div>
            </label>

            <?php if ($is_personal): ?>
                <!-- Option 2: Keep current email - ONLY shown for personal emails -->
                <label class="eau-radio-card" id="option-same">
                    <input type="radio" name="email_option" value="same" required>
                    <div class="eau-radio-card-content">
                        <div class="eau-radio-card-header">
                            <span class="eau-radio-indicator"></span>
                            <span class="eau-radio-title">
                                My current email is already my personal email
                            </span>
                        </div>
                        <p class="eau-radio-card-description">
                            The email shown above is my personal email and I want to keep using it.
                        </p>
                    </div>
                </label>
            <?php endif; ?>

            <button type="submit"
                    class="eau-btn eau-btn-primary eau-btn-lg eau-btn-full"
                    id="submit-btn"
                    <?php echo $is_personal ? 'disabled' : ''; ?>>
                <span class="btn-text">Continue</span>
                <span class="btn-loading" style="display: none;">
                    <i data-lucide="loader-2" class="spin"></i>
                    Processing...
                </span>
            </button>
        </form>
        <?php
        return ob_get_clean();
    }

    /**
     * Render the verification step (step 2)
     *
     * @param string $pending_email Email awaiting verification
     * @return string HTML
     */
    private static function render_verification_step($pending_email) {
        ob_start();
        ?>
        <div class="eau-verification-step">
            <div class="eau-verification-icon">
                <i data-lucide="mail-check"></i>
            </div>

            <h2 class="eau-verification-title">Verify Your New Email</h2>

            <p class="eau-verification-subtitle">
                We sent a 6-digit verification code to:
            </p>

            <p class="eau-verification-email">
                <?php echo esc_html($pending_email); ?>
            </p>

            <form id="eau-verification-form" class="eau-verification-form">
                <input type="hidden" name="action" value="eau_email_verify_code">
                <?php wp_nonce_field('eau_email_migration_nonce', 'nonce'); ?>

                <div class="eau-code-input-container">
                    <input type="text"
                           class="eau-code-input"
                           id="verification-code"
                           name="code"
                           maxlength="6"
                           pattern="[0-9]{6}"
                           placeholder="000000"
                           autocomplete="one-time-code"
                           inputmode="numeric"
                           required>
                </div>

                <button type="submit"
                        class="eau-btn eau-btn-primary eau-btn-lg eau-btn-full"
                        id="verify-btn">
                    <span class="btn-text">Verify</span>
                    <span class="btn-loading" style="display: none;">
                        <i data-lucide="loader-2" class="spin"></i>
                        Verifying...
                    </span>
                </button>
            </form>

            <div class="eau-resend-container">
                <p class="eau-text-muted">Didn't receive the code?</p>
                <button type="button"
                        class="eau-btn eau-btn-link"
                        id="resend-code-btn"
                        disabled>
                    Resend Code (<span id="resend-timer">60</span>s)
                </button>
            </div>

            <div class="eau-back-container">
                <button type="button"
                        class="eau-btn eau-btn-secondary"
                        id="back-btn">
                    <i data-lucide="arrow-left"></i>
                    Use a different email
                </button>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render not logged in message
     *
     * @return string HTML
     */
    private static function render_not_logged_in() {
        ob_start();
        ?>
        <div class="eau-email-update-container">
            <div class="eau-email-update-card">
                <div class="eau-email-update-header">
                    <div class="eau-email-update-icon eau-icon-warning">
                        <i data-lucide="alert-triangle"></i>
                    </div>
                    <h1 class="eau-email-update-title">Login Required</h1>
                    <p class="eau-email-update-subtitle">
                        You need to be logged in to update your email address.
                    </p>
                </div>
                <div class="eau-email-update-body">
                    <a href="<?php echo esc_url(home_url('/login/')); ?>"
                       class="eau-btn eau-btn-primary eau-btn-lg eau-btn-full">
                        Go to Login
                    </a>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Enqueue page assets
     */
    private static function enqueue_assets() {
        // Lucide Icons
        wp_enqueue_script(
            'lucide-icons',
            'https://unpkg.com/lucide@latest/dist/umd/lucide.min.js',
            array(),
            null,
            true
        );

        // Components CSS
        wp_enqueue_style(
            'eau-components',
            EAU_SYSTEM_PLUGIN_URL . 'assets/css/eau-components.css',
            array(),
            EAU_SYSTEM_VERSION
        );

        // Page CSS
        wp_enqueue_style(
            'eau-email-update',
            EAU_SYSTEM_PLUGIN_URL . 'assets/css/eau-email-update.css',
            array('eau-components'),
            EAU_SYSTEM_VERSION
        );

        // Notifications JS
        wp_enqueue_script(
            'eau-notifications',
            EAU_SYSTEM_PLUGIN_URL . 'assets/js/eau-notifications.js',
            array('jquery'),
            EAU_SYSTEM_VERSION,
            true
        );

        // Page JS
        wp_enqueue_script(
            'eau-email-update',
            EAU_SYSTEM_PLUGIN_URL . 'assets/js/eau-email-update.js',
            array('jquery', 'eau-notifications'),
            EAU_SYSTEM_VERSION,
            true
        );

        wp_localize_script('eau-email-update', 'eau_email_update_vars', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('eau_email_migration_nonce'),
            'redirect_url' => home_url('/dashboard/'),
            'i18n' => array(
                'email_required' => __('Please enter your new email address.', 'eau-system'),
                'email_mismatch' => __('Email addresses do not match.', 'eau-system'),
                'email_same' => __('New email must be different from current email.', 'eau-system'),
                'code_required' => __('Please enter the verification code.', 'eau-system'),
                'code_invalid' => __('Verification code must be 6 digits.', 'eau-system'),
                'sending' => __('Sending...', 'eau-system'),
                'verifying' => __('Verifying...', 'eau-system'),
                'resend_available' => __('Resend Code', 'eau-system'),
            ),
        ));
    }
}
