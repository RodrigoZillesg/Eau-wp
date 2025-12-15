<?php
namespace EauSystem;

/**
 * Membership Selection Page
 *
 * Displays membership types as cards and allows users to apply
 * for a specific membership type via dynamic forms.
 *
 * @since 1.49.0
 */
class Eau_Membership_Selection {

    /**
     * Register shortcode
     */
    public static function register_shortcode() {
        add_shortcode('eau_membership_selection', array(__CLASS__, 'render_shortcode'));
    }

    /**
     * Enqueue assets
     */
    public static function enqueue_assets() {
        // intl-tel-input CSS for DDI selector
        wp_enqueue_style(
            'intl-tel-input',
            'https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/css/intlTelInput.css',
            array(),
            '18.2.1'
        );

        wp_enqueue_style(
            'eau-membership-selection',
            EAU_SYSTEM_PLUGIN_URL . 'assets/css/eau-membership-selection.css',
            array('intl-tel-input'),
            EAU_SYSTEM_VERSION
        );

        // intl-tel-input JS for DDI selector
        wp_enqueue_script(
            'intl-tel-input',
            'https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/intlTelInput.min.js',
            array(),
            '18.2.1',
            true
        );

        wp_enqueue_script(
            'eau-membership-selection',
            EAU_SYSTEM_PLUGIN_URL . 'assets/js/eau-membership-selection.js',
            array('jquery', 'intl-tel-input'),
            EAU_SYSTEM_VERSION,
            true
        );

        // Localize script
        wp_localize_script('eau-membership-selection', 'eauMembershipSelection', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('eau_membership_selection'),
            'strings' => array(
                'submitting' => __('Submitting...', 'eau-system'),
                'errorGeneric' => __('An error occurred. Please try again.', 'eau-system'),
                'successApplication' => __('Your application has been submitted successfully!', 'eau-system'),
                'confirmSubmit' => __('Are you sure you want to submit this application?', 'eau-system'),
                'validationRequired' => __('This field is required', 'eau-system'),
                'uploadError' => __('Error uploading file. Please try again.', 'eau-system'),
            ),
        ));
    }

    /**
     * Render shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string
     */
    public static function render_shortcode($atts = array()) {
        // Always enqueue assets (for consistent styling including hiding page title)
        self::enqueue_assets();

        // Check if user is logged in
        if (!is_user_logged_in()) {
            return self::render_login_required();
        }

        // Get current user
        $user_id = get_current_user_id();
        $current_membership = get_user_meta($user_id, 'mem_membership_type', true);
        $membership_status = get_user_meta($user_id, 'mem_membership_status', true);

        // Check if user already has an active membership
        if ($current_membership && $membership_status === 'active') {
            return self::render_already_member($current_membership);
        }

        // Check if user has a pending application
        $pending_application = self::get_pending_application($user_id);
        if ($pending_application) {
            return self::render_pending_application($pending_application);
        }

        // Get all membership types
        $membership_types = Eau_Membership_Types::get_all();

        ob_start();
        ?>
        <div class="eau-membership-selection">
            <?php echo self::render_header(); ?>

            <div class="eau-membership-cards">
                <?php foreach ($membership_types as $type) : ?>
                    <?php echo self::render_membership_card($type); ?>
                <?php endforeach; ?>
            </div>

            <?php echo self::render_application_modal(); ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render login required message
     *
     * @return string
     */
    private static function render_login_required() {
        ob_start();
        ?>
        <div class="eau-membership-selection eau-not-logged-in">
            <div class="eau-message-box eau-message-info">
                <i data-lucide="log-in"></i>
                <h2><?php _e('Login Required', 'eau-system'); ?></h2>
                <p><?php _e('Please log in to view membership options and apply for a membership.', 'eau-system'); ?></p>
                <div class="eau-message-actions">
                    <a href="<?php echo esc_url(home_url('/login/')); ?>" class="eau-btn eau-btn-primary">
                        <i data-lucide="log-in"></i>
                        <?php _e('Log In', 'eau-system'); ?>
                    </a>
                    <a href="<?php echo esc_url(home_url('/register/')); ?>" class="eau-btn eau-btn-secondary">
                        <i data-lucide="user-plus"></i>
                        <?php _e('Create Account', 'eau-system'); ?>
                    </a>
                </div>
            </div>
        </div>
        <?php
        // Initialize Lucide icons
        ?>
        <script>if (typeof lucide !== 'undefined') { lucide.createIcons(); }</script>
        <?php
        return ob_get_clean();
    }

    /**
     * Render already member message
     *
     * @param string $membership_type Current membership type
     * @return string
     */
    private static function render_already_member($membership_type) {
        $type_label = Eau_Membership_Types::get_label($membership_type);
        $user_id = get_current_user_id();
        $expiry_date = get_user_meta($user_id, 'mem_membership_expiry_date', true);

        ob_start();
        ?>
        <div class="eau-membership-selection eau-already-member">
            <div class="eau-message-box eau-message-success">
                <i data-lucide="badge-check"></i>
                <h2><?php _e('Active Membership', 'eau-system'); ?></h2>
                <p><?php printf(__('You are currently a %s member.', 'eau-system'), '<strong>' . esc_html($type_label) . '</strong>'); ?></p>
                <?php if ($expiry_date) : ?>
                    <p class="eau-expiry-info">
                        <i data-lucide="calendar"></i>
                        <?php printf(__('Your membership expires on %s', 'eau-system'), '<strong>' . date('F j, Y', strtotime($expiry_date)) . '</strong>'); ?>
                    </p>
                <?php endif; ?>
                <div class="eau-message-actions">
                    <a href="<?php echo esc_url(home_url('/dashboard/')); ?>" class="eau-btn eau-btn-primary">
                        <i data-lucide="layout-dashboard"></i>
                        <?php _e('Go to Dashboard', 'eau-system'); ?>
                    </a>
                    <a href="<?php echo esc_url(home_url('/dashboard/profile/')); ?>" class="eau-btn eau-btn-secondary">
                        <i data-lucide="user"></i>
                        <?php _e('View Profile', 'eau-system'); ?>
                    </a>
                </div>
            </div>
        </div>
        <?php
        ?>
        <script>if (typeof lucide !== 'undefined') { lucide.createIcons(); }</script>
        <?php
        return ob_get_clean();
    }

    /**
     * Render pending application message
     *
     * @param object $application Pending application
     * @return string
     */
    private static function render_pending_application($application) {
        $type_label = Eau_Membership_Types::get_label($application->membership_type);
        $submitted_date = date('F j, Y', strtotime($application->submitted_at));

        ob_start();
        ?>
        <div class="eau-membership-selection eau-pending-application">
            <div class="eau-message-box eau-message-warning">
                <i data-lucide="clock"></i>
                <h2><?php _e('Application Pending', 'eau-system'); ?></h2>
                <p><?php printf(__('Your application for %s membership is currently being reviewed.', 'eau-system'), '<strong>' . esc_html($type_label) . '</strong>'); ?></p>
                <p class="eau-submitted-info">
                    <i data-lucide="calendar"></i>
                    <?php printf(__('Submitted on %s', 'eau-system'), '<strong>' . esc_html($submitted_date) . '</strong>'); ?>
                </p>
                <div class="eau-application-status">
                    <span class="eau-status-badge eau-status-<?php echo esc_attr($application->status); ?>">
                        <?php echo esc_html(ucfirst($application->status)); ?>
                    </span>
                </div>
                <p class="eau-note"><?php _e('You will receive an email notification once your application has been reviewed.', 'eau-system'); ?></p>
                <div class="eau-message-actions">
                    <a href="<?php echo esc_url(home_url('/dashboard/')); ?>" class="eau-btn eau-btn-primary">
                        <i data-lucide="layout-dashboard"></i>
                        <?php _e('Go to Dashboard', 'eau-system'); ?>
                    </a>
                </div>
            </div>
        </div>
        <?php
        ?>
        <script>if (typeof lucide !== 'undefined') { lucide.createIcons(); }</script>
        <?php
        return ob_get_clean();
    }

    /**
     * Render page header
     *
     * @return string
     */
    private static function render_header() {
        ob_start();
        ?>
        <div class="eau-membership-header">
            <div class="eau-membership-header-content">
                <h1>
                    <i data-lucide="award"></i>
                    <?php _e('Membership Options', 'eau-system'); ?>
                </h1>
                <p><?php _e('Choose the membership type that best suits your organization. Each membership offers different benefits and access levels.', 'eau-system'); ?></p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render membership card
     *
     * @param object $type Membership type object
     * @return string
     */
    private static function render_membership_card($type) {
        $icon = self::get_type_icon($type->type_key);
        $fee_display = self::get_fee_display($type);
        $benefits = !empty($type->benefits) ? $type->benefits : array();
        $requirements = !empty($type->requirements) ? $type->requirements : array();

        ob_start();
        ?>
        <div class="eau-membership-card" data-type="<?php echo esc_attr($type->type_key); ?>">
            <div class="eau-card-header">
                <div class="eau-card-icon">
                    <i data-lucide="<?php echo esc_attr($icon); ?>"></i>
                </div>
                <h2 class="eau-card-title"><?php echo esc_html($type->type_label); ?></h2>
            </div>

            <div class="eau-card-price">
                <?php echo $fee_display; ?>
            </div>

            <?php if (!empty($type->type_description)) : ?>
                <p class="eau-card-description"><?php echo esc_html($type->type_description); ?></p>
            <?php endif; ?>

            <?php if (!empty($benefits)) : ?>
                <div class="eau-card-benefits">
                    <h4><?php _e('Benefits', 'eau-system'); ?></h4>
                    <ul>
                        <?php foreach (array_slice($benefits, 0, 5) as $benefit) : ?>
                            <li>
                                <i data-lucide="check"></i>
                                <?php echo esc_html($benefit); ?>
                            </li>
                        <?php endforeach; ?>
                        <?php if (count($benefits) > 5) : ?>
                            <li class="eau-more-benefits">
                                <i data-lucide="plus"></i>
                                <?php printf(__('And %d more benefits...', 'eau-system'), count($benefits) - 5); ?>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (!empty($requirements)) : ?>
                <div class="eau-card-requirements">
                    <h4><?php _e('Requirements', 'eau-system'); ?></h4>
                    <ul>
                        <?php foreach ($requirements as $requirement) : ?>
                            <li>
                                <i data-lucide="alert-circle"></i>
                                <?php echo esc_html($requirement); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="eau-card-actions">
                <button type="button" class="eau-btn eau-btn-primary eau-apply-btn" data-type="<?php echo esc_attr($type->type_key); ?>">
                    <i data-lucide="file-text"></i>
                    <?php _e('Apply Now', 'eau-system'); ?>
                </button>
                <button type="button" class="eau-btn eau-btn-ghost eau-details-btn" data-type="<?php echo esc_attr($type->type_key); ?>">
                    <i data-lucide="info"></i>
                    <?php _e('More Details', 'eau-system'); ?>
                </button>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render application modal
     *
     * @return string
     */
    private static function render_application_modal() {
        $user = wp_get_current_user();
        $user_id = $user->ID;

        // Get user data for pre-filling
        $first_name = get_user_meta($user_id, 'mem_firstname', true) ?: $user->first_name;
        $last_name = get_user_meta($user_id, 'mem_lastname', true) ?: $user->last_name;
        $email = $user->user_email;
        // Try mem_phone first (new field), fallback to mem_mobile (legacy)
        $phone = get_user_meta($user_id, 'mem_phone', true) ?: get_user_meta($user_id, 'mem_mobile', true);
        $position = get_user_meta($user_id, 'mem_position', true);
        $company_id = get_user_meta($user_id, 'mem_membercompanyname', true);
        $company_name = '';

        // Get company name if exists
        if ($company_id) {
            $company_post = get_post($company_id);
            if ($company_post) {
                $company_name = $company_post->post_title;
            }
        }

        ob_start();
        ?>
        <div class="eau-modal" id="application-modal" style="display: none;">
            <div class="eau-modal-overlay"></div>
            <div class="eau-modal-container eau-modal-lg">
                <div class="eau-modal-header">
                    <h2 class="eau-modal-title">
                        <i data-lucide="file-text"></i>
                        <span id="modal-title"><?php _e('Membership Application', 'eau-system'); ?></span>
                    </h2>
                    <button type="button" class="eau-modal-close" aria-label="<?php _e('Close', 'eau-system'); ?>">
                        <i data-lucide="x"></i>
                    </button>
                </div>
                <div class="eau-modal-body">
                    <form id="membership-application-form" class="eau-form">
                        <input type="hidden" name="membership_type" id="application-type" value="">
                        <?php wp_nonce_field('eau_membership_application', 'eau_application_nonce'); ?>

                        <!-- Step Indicator -->
                        <div class="eau-application-steps">
                            <div class="eau-app-step active" data-step="1">
                                <span class="eau-step-number">1</span>
                                <span class="eau-step-label"><?php _e('Personal Info', 'eau-system'); ?></span>
                            </div>
                            <div class="eau-app-step" data-step="2">
                                <span class="eau-step-number">2</span>
                                <span class="eau-step-label"><?php _e('Organization', 'eau-system'); ?></span>
                            </div>
                            <div class="eau-app-step" data-step="3">
                                <span class="eau-step-number">3</span>
                                <span class="eau-step-label"><?php _e('Documents', 'eau-system'); ?></span>
                            </div>
                            <div class="eau-app-step" data-step="4">
                                <span class="eau-step-number">4</span>
                                <span class="eau-step-label"><?php _e('Review', 'eau-system'); ?></span>
                            </div>
                        </div>

                        <!-- Step 1: Personal Information -->
                        <div class="eau-application-step" id="app-step-1">
                            <h3><?php _e('Personal Information', 'eau-system'); ?></h3>
                            <p class="eau-step-description"><?php _e('Please verify your contact details.', 'eau-system'); ?></p>

                            <div class="eau-form-grid">
                                <div class="eau-form-field">
                                    <label for="app-first-name"><?php _e('First Name', 'eau-system'); ?> <span class="required">*</span></label>
                                    <input type="text" id="app-first-name" name="first_name" class="eau-form-input" value="<?php echo esc_attr($first_name); ?>" required>
                                    <span class="eau-form-error"></span>
                                </div>

                                <div class="eau-form-field">
                                    <label for="app-last-name"><?php _e('Last Name', 'eau-system'); ?> <span class="required">*</span></label>
                                    <input type="text" id="app-last-name" name="last_name" class="eau-form-input" value="<?php echo esc_attr($last_name); ?>" required>
                                    <span class="eau-form-error"></span>
                                </div>

                                <div class="eau-form-field">
                                    <label for="app-email"><?php _e('Email', 'eau-system'); ?> <span class="required">*</span></label>
                                    <input type="email" id="app-email" name="email" class="eau-form-input" value="<?php echo esc_attr($email); ?>" readonly>
                                    <span class="eau-form-hint"><?php _e('Email cannot be changed', 'eau-system'); ?></span>
                                </div>

                                <div class="eau-form-field">
                                    <label for="app-phone"><?php _e('Phone', 'eau-system'); ?></label>
                                    <div class="eau-phone-input-wrapper">
                                        <input type="tel" id="app-phone" class="eau-form-input eau-phone-input" autocomplete="tel" placeholder="<?php esc_attr_e('Enter phone number', 'eau-system'); ?>">
                                        <input type="hidden" name="phone" id="app-phone-full" value="<?php echo esc_attr($phone); ?>">
                                    </div>
                                </div>

                                <div class="eau-form-field">
                                    <label for="app-position"><?php _e('Position', 'eau-system'); ?> <span class="required">*</span></label>
                                    <select id="app-position" name="position" class="eau-form-select" required>
                                        <option value=""><?php _e('Select your position', 'eau-system'); ?></option>
                                        <?php foreach (Eau_Membership_Types::get_positions() as $key => $label) : ?>
                                            <option value="<?php echo esc_attr($key); ?>" <?php selected($position, $key); ?>><?php echo esc_html($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <span class="eau-form-error"></span>
                                </div>
                            </div>
                        </div>

                        <!-- Step 2: Organization Information -->
                        <div class="eau-application-step" id="app-step-2" style="display: none;">
                            <h3><?php _e('Organization Information', 'eau-system'); ?></h3>
                            <p class="eau-step-description"><?php _e('Tell us about your organization.', 'eau-system'); ?></p>

                            <div class="eau-form-grid">
                                <div class="eau-form-field eau-form-field-full">
                                    <label for="app-company-name"><?php _e('Organization Name', 'eau-system'); ?> <span class="required">*</span></label>
                                    <input type="text" id="app-company-name" name="company_name" class="eau-form-input" value="<?php echo esc_attr($company_name); ?>" required>
                                    <span class="eau-form-error"></span>
                                </div>

                                <!-- CRICOS fields (shown for Full Provider and Associate) -->
                                <div class="eau-form-field eau-cricos-field" style="display: none;">
                                    <label for="app-cricos-number"><?php _e('CRICOS Provider Number', 'eau-system'); ?> <span class="required">*</span></label>
                                    <input type="text" id="app-cricos-number" name="cricos_number" class="eau-form-input" placeholder="e.g., 01234K">
                                    <span class="eau-form-error"></span>
                                </div>

                                <div class="eau-form-field eau-cricos-field" style="display: none;">
                                    <label for="app-cricos-sites"><?php _e('Number of CRICOS Sites', 'eau-system'); ?></label>
                                    <input type="number" id="app-cricos-sites" name="cricos_sites" class="eau-form-input" min="1" value="1">
                                    <span class="eau-form-hint"><?php _e('For Full Provider fee calculation', 'eau-system'); ?></span>
                                </div>

                                <div class="eau-form-field">
                                    <label for="app-state"><?php _e('State', 'eau-system'); ?> <span class="required">*</span></label>
                                    <select id="app-state" name="state" class="eau-form-select" required>
                                        <option value=""><?php _e('Select state', 'eau-system'); ?></option>
                                        <?php foreach (Eau_Membership_Types::get_australian_states() as $key => $label) : ?>
                                            <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <span class="eau-form-error"></span>
                                </div>

                                <div class="eau-form-field">
                                    <label for="app-country"><?php _e('Country', 'eau-system'); ?> <span class="required">*</span></label>
                                    <select id="app-country" name="country" class="eau-form-select" required>
                                        <option value="Australia" selected><?php _e('Australia', 'eau-system'); ?></option>
                                        <option value="New Zealand"><?php _e('New Zealand', 'eau-system'); ?></option>
                                        <option value="Other"><?php _e('Other', 'eau-system'); ?></option>
                                    </select>
                                </div>

                                <div class="eau-form-field eau-form-field-full">
                                    <label for="app-website"><?php _e('Organization Website', 'eau-system'); ?></label>
                                    <input type="url" id="app-website" name="website" class="eau-form-input" placeholder="https://">
                                </div>

                                <div class="eau-form-field eau-form-field-full">
                                    <label for="app-additional-info"><?php _e('Additional Information', 'eau-system'); ?></label>
                                    <textarea id="app-additional-info" name="additional_info" class="eau-form-textarea" rows="3" placeholder="<?php _e('Any additional information about your organization...', 'eau-system'); ?>"></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Step 3: Documents -->
                        <div class="eau-application-step" id="app-step-3" style="display: none;">
                            <h3><?php _e('Required Documents', 'eau-system'); ?></h3>
                            <p class="eau-step-description" id="documents-description"><?php _e('Please upload any required documents for your application.', 'eau-system'); ?></p>

                            <div id="required-documents-list" class="eau-documents-list">
                                <!-- Dynamic document upload fields will be inserted here -->
                            </div>

                            <div class="eau-form-field">
                                <label for="app-supporting-docs"><?php _e('Additional Supporting Documents', 'eau-system'); ?></label>
                                <div id="supporting-docs-upload" class="eau-upload-area">
                                    <input type="file" id="app-supporting-docs" name="supporting_docs[]" multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                    <div class="eau-upload-placeholder">
                                        <i data-lucide="upload"></i>
                                        <p><?php _e('Drag files here or click to upload', 'eau-system'); ?></p>
                                        <span><?php _e('PDF, DOC, DOCX, JPG, PNG (max 10MB each)', 'eau-system'); ?></span>
                                    </div>
                                </div>
                                <div id="uploaded-files-list" class="eau-uploaded-files"></div>
                            </div>
                        </div>

                        <!-- Step 4: Review -->
                        <div class="eau-application-step" id="app-step-4" style="display: none;">
                            <h3><?php _e('Review Your Application', 'eau-system'); ?></h3>
                            <p class="eau-step-description"><?php _e('Please review your application details before submitting.', 'eau-system'); ?></p>

                            <div class="eau-review-section">
                                <div class="eau-review-card">
                                    <h4><i data-lucide="award"></i> <?php _e('Membership Type', 'eau-system'); ?></h4>
                                    <div id="review-membership-type" class="eau-review-value"></div>
                                    <div id="review-membership-fee" class="eau-review-fee"></div>
                                </div>

                                <div class="eau-review-card">
                                    <h4><i data-lucide="user"></i> <?php _e('Personal Information', 'eau-system'); ?></h4>
                                    <div id="review-personal" class="eau-review-details"></div>
                                </div>

                                <div class="eau-review-card">
                                    <h4><i data-lucide="building-2"></i> <?php _e('Organization', 'eau-system'); ?></h4>
                                    <div id="review-organization" class="eau-review-details"></div>
                                </div>

                                <div class="eau-review-card">
                                    <h4><i data-lucide="file-text"></i> <?php _e('Documents', 'eau-system'); ?></h4>
                                    <div id="review-documents" class="eau-review-details"></div>
                                </div>
                            </div>

                            <div class="eau-form-field">
                                <label class="eau-checkbox-label">
                                    <input type="checkbox" id="app-confirm" name="confirm" required>
                                    <span class="eau-checkbox-custom"><i data-lucide="check"></i></span>
                                    <span><?php _e('I confirm that all information provided is accurate and complete.', 'eau-system'); ?> <span class="required">*</span></span>
                                </label>
                                <span class="eau-form-error" id="confirm-error"></span>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="eau-modal-footer">
                    <button type="button" class="eau-btn eau-btn-secondary eau-prev-step" style="display: none;">
                        <i data-lucide="arrow-left"></i>
                        <?php _e('Previous', 'eau-system'); ?>
                    </button>
                    <button type="button" class="eau-btn eau-btn-primary eau-next-step">
                        <?php _e('Next', 'eau-system'); ?>
                        <i data-lucide="arrow-right"></i>
                    </button>
                    <button type="button" class="eau-btn eau-btn-primary eau-submit-application" style="display: none;">
                        <i data-lucide="send"></i>
                        <?php _e('Submit Application', 'eau-system'); ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Details Modal -->
        <div class="eau-modal" id="details-modal" style="display: none;">
            <div class="eau-modal-overlay"></div>
            <div class="eau-modal-container">
                <div class="eau-modal-header">
                    <h2 class="eau-modal-title">
                        <i data-lucide="info"></i>
                        <span id="details-modal-title"><?php _e('Membership Details', 'eau-system'); ?></span>
                    </h2>
                    <button type="button" class="eau-modal-close" aria-label="<?php _e('Close', 'eau-system'); ?>">
                        <i data-lucide="x"></i>
                    </button>
                </div>
                <div class="eau-modal-body" id="details-modal-content">
                    <!-- Dynamic content -->
                </div>
                <div class="eau-modal-footer">
                    <button type="button" class="eau-btn eau-btn-secondary eau-modal-close-btn">
                        <?php _e('Close', 'eau-system'); ?>
                    </button>
                    <button type="button" class="eau-btn eau-btn-primary eau-apply-from-details">
                        <i data-lucide="file-text"></i>
                        <?php _e('Apply for this Membership', 'eau-system'); ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Membership Types Data for JavaScript -->
        <script type="application/json" id="membership-types-data">
            <?php echo json_encode(self::get_membership_types_for_js()); ?>
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * Get fee display HTML
     *
     * @param object $type Membership type
     * @return string
     */
    private static function get_fee_display($type) {
        if ($type->fee_is_variable) {
            return '<div class="eau-fee-variable">
                <span class="eau-fee-label">' . __('Fee', 'eau-system') . '</span>
                <span class="eau-fee-amount">' . __('Variable', 'eau-system') . '</span>
                <span class="eau-fee-note">' . __('Calculated based on CRICOS sites', 'eau-system') . '</span>
            </div>';
        }

        $amount = number_format($type->fee_amount, 0);
        $gst = $type->fee_includes_gst ? __('inc. GST', 'eau-system') : __('ex. GST', 'eau-system');

        $html = '<div class="eau-fee-fixed">
            <span class="eau-fee-currency">$</span>
            <span class="eau-fee-amount">' . $amount . '</span>
            <span class="eau-fee-period">/' . __('year', 'eau-system') . '</span>
            <span class="eau-fee-gst">' . $gst . '</span>
        </div>';

        // Show member college rate if different
        if ($type->fee_member_college && $type->fee_member_college != $type->fee_amount) {
            $member_amount = number_format($type->fee_member_college, 0);
            $html .= '<div class="eau-fee-member-rate">
                <span>' . __('Member College Rate:', 'eau-system') . '</span>
                <strong>$' . $member_amount . '</strong>
            </div>';
        }

        return $html;
    }

    /**
     * Get icon for membership type
     *
     * @param string $type_key Type key
     * @return string Lucide icon name
     */
    private static function get_type_icon($type_key) {
        $icons = array(
            'full_provider' => 'building-2',
            'associate_access' => 'building',
            'corporate_affiliate' => 'briefcase',
            'professional_affiliate' => 'user-check',
        );

        return isset($icons[$type_key]) ? $icons[$type_key] : 'award';
    }

    /**
     * Get pending application for user
     *
     * @param int $user_id User ID
     * @return object|null
     */
    private static function get_pending_application($user_id) {
        global $wpdb;

        $user = get_userdata($user_id);
        if (!$user) {
            return null;
        }

        $table_name = Eau_Membership_Database::get_table_name(Eau_Membership_Database::TABLE_APPLICATIONS);

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name
            WHERE (user_id = %d OR email = %s)
            AND status IN ('pending', 'under_review')
            ORDER BY submitted_at DESC
            LIMIT 1",
            $user_id,
            $user->user_email
        ));
    }

    /**
     * Get membership types data for JavaScript
     *
     * @return array
     */
    private static function get_membership_types_for_js() {
        $types = Eau_Membership_Types::get_all();
        $data = array();

        foreach ($types as $type) {
            // Get is_institution_type and show_cricos_fields from database (with fallbacks for backwards compatibility)
            $is_institution_type = isset($type->is_institution_type) ? (bool) $type->is_institution_type : true;
            $show_cricos_fields = isset($type->show_cricos_fields) ? (bool) $type->show_cricos_fields : in_array($type->type_key, array('full_provider', 'associate_access'));

            $data[$type->type_key] = array(
                'type_id' => $type->type_id,
                'type_key' => $type->type_key,
                'type_label' => $type->type_label,
                'type_description' => $type->type_description,
                'fee_amount' => $type->fee_amount,
                'fee_member_college' => $type->fee_member_college,
                'fee_currency' => $type->fee_currency,
                'fee_includes_gst' => $type->fee_includes_gst,
                'fee_is_variable' => $type->fee_is_variable,
                'requirements' => $type->requirements,
                'required_documents' => $type->required_documents,
                'benefits' => $type->benefits,
                'newsletter_access' => $type->newsletter_access,
                'max_duration_months' => $type->max_duration_months,
                'requires_approval' => $type->requires_approval,
                'requires_email_submission' => $type->requires_email_submission,
                'is_institution_type' => $is_institution_type,
                'show_cricos_fields' => $show_cricos_fields,
            );
        }

        return $data;
    }
}
