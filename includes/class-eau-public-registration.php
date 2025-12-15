<?php
namespace EauSystem;

/**
 * Public Registration Page
 *
 * Allows visitors to register as members.
 * After registration, users can log in and apply for membership.
 *
 * Shortcode: [eau_public_registration]
 *
 * Features:
 * - 10 required fields (First Name, Last Name, Email, Company, Position, State, Country, Password, Verify Password, reCAPTCHA)
 * - Newsletter subscription checkboxes (public newsletters)
 * - Company dropdown with AJAX search (existing institutions)
 * - reCAPTCHA v3 integration
 * - Email verification
 * - Automatic login after registration
 *
 * @since 1.49.0
 */
class Eau_Public_Registration {

    /**
     * reCAPTCHA site key (configurable via settings)
     */
    const RECAPTCHA_SITE_KEY_OPTION = 'eau_recaptcha_site_key';
    const RECAPTCHA_SECRET_KEY_OPTION = 'eau_recaptcha_secret_key';

    /**
     * Register the shortcode
     */
    public static function register_shortcode() {
        add_shortcode('eau_public_registration', array(__CLASS__, 'render_registration'));
    }

    /**
     * Render the registration page
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public static function render_registration($atts) {
        // If already logged in, show message
        if (is_user_logged_in()) {
            return self::render_already_logged_in();
        }

        // Load assets
        self::enqueue_assets();

        // Get data for dropdowns
        $positions = Eau_Membership_Types::get_positions();
        $states = Eau_Membership_Types::get_australian_states();
        $public_newsletters = Eau_Newsletters::get_public();
        $recaptcha_site_key = get_option(self::RECAPTCHA_SITE_KEY_OPTION, '');

        ob_start();
        ?>
        <div class="eau-public-registration-container" id="eau-public-registration">

            <!-- Registration Header -->
            <div class="eau-registration-header">
                <div class="eau-registration-header-content">
                    <h1 class="eau-registration-title">
                        <i data-lucide="user-plus"></i>
                        Create Your Account
                    </h1>
                    <p class="eau-registration-subtitle">
                        Join English Australia to access member benefits, professional development resources, and industry networking opportunities.
                    </p>
                </div>
            </div>

            <!-- Registration Form -->
            <form id="eau-registration-form" class="eau-registration-form" novalidate>
                <?php wp_nonce_field('eau_public_registration', 'eau_registration_nonce'); ?>

                <!-- Step Indicator -->
                <div class="eau-registration-steps">
                    <div class="eau-step active" data-step="1">
                        <span class="eau-step-number">1</span>
                        <span class="eau-step-label">Personal Info</span>
                    </div>
                    <div class="eau-step-connector"></div>
                    <div class="eau-step" data-step="2">
                        <span class="eau-step-number">2</span>
                        <span class="eau-step-label">Professional Info</span>
                    </div>
                    <div class="eau-step-connector"></div>
                    <div class="eau-step" data-step="3">
                        <span class="eau-step-number">3</span>
                        <span class="eau-step-label">Account Setup</span>
                    </div>
                </div>

                <!-- Step 1: Personal Information -->
                <div class="eau-registration-step" id="step-1">
                    <h2 class="eau-step-title">
                        <i data-lucide="user"></i>
                        Personal Information
                    </h2>
                    <p class="eau-step-description">Please provide your personal details.</p>

                    <div class="eau-form-grid">
                        <!-- First Name -->
                        <div class="eau-form-field">
                            <label class="eau-form-label">
                                First Name
                                <span class="eau-form-required">*</span>
                            </label>
                            <input type="text"
                                   class="eau-form-input"
                                   name="first_name"
                                   id="reg-first-name"
                                   required
                                   autocomplete="given-name"
                                   placeholder="Enter your first name">
                            <span class="eau-form-error"></span>
                        </div>

                        <!-- Last Name -->
                        <div class="eau-form-field">
                            <label class="eau-form-label">
                                Last Name
                                <span class="eau-form-required">*</span>
                            </label>
                            <input type="text"
                                   class="eau-form-input"
                                   name="last_name"
                                   id="reg-last-name"
                                   required
                                   autocomplete="family-name"
                                   placeholder="Enter your last name">
                            <span class="eau-form-error"></span>
                        </div>

                        <!-- Email -->
                        <div class="eau-form-field eau-form-field-span-2">
                            <label class="eau-form-label">
                                Email Address
                                <span class="eau-form-required">*</span>
                            </label>
                            <input type="email"
                                   class="eau-form-input"
                                   name="email"
                                   id="reg-email"
                                   required
                                   autocomplete="email"
                                   placeholder="Enter your email address">
                            <span class="eau-form-hint">This will be your login username</span>
                            <span class="eau-form-error"></span>
                        </div>

                        <!-- Phone (optional) with DDI selector -->
                        <div class="eau-form-field eau-form-field-span-2">
                            <label class="eau-form-label">
                                Phone Number
                            </label>
                            <div class="eau-phone-input-wrapper">
                                <input type="tel"
                                       class="eau-form-input eau-phone-input"
                                       id="reg-phone"
                                       autocomplete="tel"
                                       placeholder="Enter your phone number (optional)">
                                <input type="hidden" name="phone" id="reg-phone-full">
                            </div>
                            <span class="eau-form-error"></span>
                        </div>
                    </div>

                    <div class="eau-registration-nav">
                        <div></div>
                        <button type="button" class="eau-btn eau-btn-primary eau-next-step" data-next="2">
                            Continue
                            <i data-lucide="arrow-right"></i>
                        </button>
                    </div>
                </div>

                <!-- Step 2: Professional Information -->
                <div class="eau-registration-step" id="step-2" style="display: none;">
                    <h2 class="eau-step-title">
                        <i data-lucide="briefcase"></i>
                        Professional Information
                    </h2>
                    <p class="eau-step-description">Tell us about your professional background.</p>

                    <div class="eau-form-grid">
                        <!-- Position -->
                        <div class="eau-form-field eau-form-field-span-2">
                            <label class="eau-form-label">
                                Position
                                <span class="eau-form-required">*</span>
                            </label>
                            <select class="eau-form-select" name="position" id="reg-position" required>
                                <option value="">Select your position</option>
                                <?php foreach ($positions as $key => $label): ?>
                                    <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <span class="eau-form-error"></span>
                        </div>

                        <!-- State -->
                        <div class="eau-form-field">
                            <label class="eau-form-label">
                                State
                                <span class="eau-form-required">*</span>
                            </label>
                            <select class="eau-form-select" name="state" id="reg-state" required>
                                <option value="">Select state</option>
                                <?php foreach ($states as $abbr => $name): ?>
                                    <option value="<?php echo esc_attr($abbr); ?>"><?php echo esc_html($name); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <span class="eau-form-error"></span>
                        </div>

                        <!-- Country -->
                        <div class="eau-form-field">
                            <label class="eau-form-label">
                                Country
                                <span class="eau-form-required">*</span>
                            </label>
                            <select class="eau-form-select" name="country" id="reg-country" required>
                                <option value="">Select country</option>
                                <option value="Australia" selected>Australia</option>
                                <option value="New Zealand">New Zealand</option>
                                <option value="Other">Other</option>
                            </select>
                            <span class="eau-form-error"></span>
                        </div>
                    </div>

                    <div class="eau-registration-nav">
                        <button type="button" class="eau-btn eau-btn-secondary eau-prev-step" data-prev="1">
                            <i data-lucide="arrow-left"></i>
                            Back
                        </button>
                        <button type="button" class="eau-btn eau-btn-primary eau-next-step" data-next="3">
                            Continue
                            <i data-lucide="arrow-right"></i>
                        </button>
                    </div>
                </div>

                <!-- Step 3: Account Setup -->
                <div class="eau-registration-step" id="step-3" style="display: none;">
                    <h2 class="eau-step-title">
                        <i data-lucide="lock"></i>
                        Account Setup
                    </h2>
                    <p class="eau-step-description">Create your login credentials and select newsletter preferences.</p>

                    <div class="eau-form-grid">
                        <!-- Password -->
                        <div class="eau-form-field">
                            <label class="eau-form-label">
                                Password
                                <span class="eau-form-required">*</span>
                            </label>
                            <div class="eau-password-wrapper">
                                <input type="password"
                                       class="eau-form-input"
                                       name="password"
                                       id="reg-password"
                                       required
                                       autocomplete="new-password"
                                       placeholder="Create a password">
                                <button type="button" class="eau-password-toggle" tabindex="-1">
                                    <i data-lucide="eye"></i>
                                </button>
                            </div>
                            <div class="eau-password-strength" id="password-strength">
                                <div class="eau-strength-bar"></div>
                                <span class="eau-strength-text"></span>
                            </div>
                            <span class="eau-form-hint">Minimum 8 characters with at least one number</span>
                            <span class="eau-form-error"></span>
                        </div>

                        <!-- Verify Password -->
                        <div class="eau-form-field">
                            <label class="eau-form-label">
                                Verify Password
                                <span class="eau-form-required">*</span>
                            </label>
                            <div class="eau-password-wrapper">
                                <input type="password"
                                       class="eau-form-input"
                                       name="password_confirm"
                                       id="reg-password-confirm"
                                       required
                                       autocomplete="new-password"
                                       placeholder="Confirm your password">
                                <button type="button" class="eau-password-toggle" tabindex="-1">
                                    <i data-lucide="eye"></i>
                                </button>
                            </div>
                            <span class="eau-form-error"></span>
                        </div>
                    </div>

                    <!-- Newsletter Subscriptions -->
                    <div class="eau-newsletters-section">
                        <h3 class="eau-newsletters-title">
                            <i data-lucide="mail"></i>
                            Newsletter Subscriptions
                        </h3>
                        <p class="eau-newsletters-description">
                            Stay informed with our newsletters. You can update your preferences anytime.
                        </p>

                        <div class="eau-newsletters-grid">
                            <?php foreach ($public_newsletters as $newsletter): ?>
                                <label class="eau-newsletter-option">
                                    <input type="checkbox"
                                           name="newsletters[]"
                                           value="<?php echo esc_attr($newsletter->newsletter_key); ?>"
                                           checked>
                                    <span class="eau-newsletter-checkbox">
                                        <i data-lucide="check"></i>
                                    </span>
                                    <span class="eau-newsletter-info">
                                        <span class="eau-newsletter-name"><?php echo esc_html($newsletter->newsletter_name); ?></span>
                                        <?php if (!empty($newsletter->newsletter_description)): ?>
                                            <span class="eau-newsletter-desc"><?php echo esc_html($newsletter->newsletter_description); ?></span>
                                        <?php endif; ?>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>

                        <p class="eau-newsletters-note">
                            <i data-lucide="info"></i>
                            Additional newsletters will become available once you are approved as a member.
                        </p>
                    </div>

                    <!-- Terms and Conditions -->
                    <div class="eau-terms-section">
                        <label class="eau-terms-checkbox">
                            <input type="checkbox" name="terms" id="reg-terms" required>
                            <span class="eau-checkbox-custom">
                                <i data-lucide="check"></i>
                            </span>
                            <span class="eau-terms-text">
                                I agree to the <a href="/terms-of-use/" target="_blank">Terms of Use</a>
                                and <a href="/privacy-policy/" target="_blank">Privacy Policy</a>
                                <span class="eau-form-required">*</span>
                            </span>
                        </label>
                        <span class="eau-form-error" id="terms-error"></span>
                    </div>

                    <?php if (!empty($recaptcha_site_key)): ?>
                    <!-- reCAPTCHA -->
                    <input type="hidden" name="recaptcha_token" id="recaptcha-token">
                    <?php endif; ?>

                    <div class="eau-registration-nav">
                        <button type="button" class="eau-btn eau-btn-secondary eau-prev-step" data-prev="2">
                            <i data-lucide="arrow-left"></i>
                            Back
                        </button>
                        <button type="submit" class="eau-btn eau-btn-primary eau-btn-lg" id="submit-registration">
                            <i data-lucide="user-check"></i>
                            Create Account
                        </button>
                    </div>
                </div>

                <!-- Success Message (hidden by default) -->
                <div class="eau-registration-success" id="registration-success" style="display: none;">
                    <div class="eau-success-icon">
                        <i data-lucide="check-circle"></i>
                    </div>
                    <h2 class="eau-success-title">Account Created Successfully!</h2>
                    <p class="eau-success-message">
                        Welcome to English Australia! Your account has been created and you are now logged in.
                    </p>
                    <p class="eau-success-next">
                        <strong>Next Step:</strong> Visit the Member Centre to apply for a membership type that suits your organization.
                    </p>
                    <div class="eau-success-actions">
                        <a href="/dashboard/" class="eau-btn eau-btn-primary eau-btn-lg">
                            <i data-lucide="layout-dashboard"></i>
                            Go to Dashboard
                        </a>
                        <a href="/membership-selection/" class="eau-btn eau-btn-secondary eau-btn-lg">
                            <i data-lucide="id-card"></i>
                            Apply for Membership
                        </a>
                    </div>
                </div>

            </form>

            <!-- Already have account -->
            <div class="eau-registration-footer">
                <p>Already have an account? <a href="/login/">Log in here</a></p>
            </div>

        </div>
        <?php

        return ob_get_clean();
    }

    /**
     * Render message for already logged in users
     *
     * @return string HTML
     */
    private static function render_already_logged_in() {
        ob_start();
        ?>
        <div class="eau-already-logged-in">
            <div class="eau-already-logged-in-icon">
                <i data-lucide="user-check"></i>
            </div>
            <h2>You're Already Logged In</h2>
            <p>You already have an account and are currently logged in.</p>
            <div class="eau-already-logged-in-actions">
                <a href="/dashboard/" class="eau-btn eau-btn-primary">
                    <i data-lucide="layout-dashboard"></i>
                    Go to Dashboard
                </a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Enqueue page assets
     */
    public static function enqueue_assets() {
        // intl-tel-input CSS
        wp_enqueue_style(
            'intl-tel-input',
            'https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/css/intlTelInput.css',
            array(),
            '18.2.1'
        );

        // CSS
        wp_enqueue_style(
            'eau-public-registration',
            EAU_SYSTEM_PLUGIN_URL . 'assets/css/eau-public-registration.css',
            array('intl-tel-input'),
            EAU_SYSTEM_VERSION
        );

        // intl-tel-input JS
        wp_enqueue_script(
            'intl-tel-input',
            'https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/intlTelInput.min.js',
            array(),
            '18.2.1',
            true
        );

        // JS
        wp_enqueue_script(
            'eau-public-registration',
            EAU_SYSTEM_PLUGIN_URL . 'assets/js/eau-public-registration.js',
            array('jquery', 'intl-tel-input'),
            EAU_SYSTEM_VERSION,
            true
        );

        // Localize script
        wp_localize_script('eau-public-registration', 'eauPublicRegistration', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('eau_public_registration'),
            'recaptchaSiteKey' => get_option(self::RECAPTCHA_SITE_KEY_OPTION, ''),
            'strings' => array(
                'validationRequired' => 'This field is required',
                'validationEmail' => 'Please enter a valid email address',
                'validationEmailExists' => 'This email is already registered',
                'validationPasswordMin' => 'Password must be at least 8 characters',
                'validationPasswordMatch' => 'Passwords do not match',
                'validationPasswordWeak' => 'Password is too weak',
                'validationTerms' => 'You must accept the terms and conditions',
                'validationCompany' => 'Please select or enter a company',
                'strengthWeak' => 'Weak',
                'strengthFair' => 'Fair',
                'strengthGood' => 'Good',
                'strengthStrong' => 'Strong',
                'submitting' => 'Creating account...',
                'errorGeneric' => 'An error occurred. Please try again.',
            ),
        ));

        // reCAPTCHA v3 (if configured)
        $recaptcha_site_key = get_option(self::RECAPTCHA_SITE_KEY_OPTION, '');
        if (!empty($recaptcha_site_key)) {
            wp_enqueue_script(
                'recaptcha-v3',
                'https://www.google.com/recaptcha/api.js?render=' . esc_attr($recaptcha_site_key),
                array(),
                null,
                true
            );
        }
    }

    /**
     * Get reCAPTCHA site key
     *
     * @return string
     */
    public static function get_recaptcha_site_key() {
        return get_option(self::RECAPTCHA_SITE_KEY_OPTION, '');
    }

    /**
     * Get reCAPTCHA secret key
     *
     * @return string
     */
    public static function get_recaptcha_secret_key() {
        return get_option(self::RECAPTCHA_SECRET_KEY_OPTION, '');
    }
}
