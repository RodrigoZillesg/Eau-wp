<?php
namespace EauSystem;

use EauSystem\Components\Eau_Access_Denied;
use EauSystem\Components\Eau_Modal;
use EauSystem\Components\Eau_Skeleton;
use EauSystem\Components\Eau_Pagination;

/**
 * My Institution Page
 *
 * Allows members to view their institution, search for others,
 * and request to link to a new institution.
 *
 * Shortcode: [eau_my_institution]
 *
 * @since 1.44.0
 */
class Eau_My_Institution {

    /**
     * Register the shortcode
     */
    public static function register_shortcode() {
        add_shortcode('eau_my_institution', array(__CLASS__, 'render_page'));
    }

    /**
     * Render the My Institution page
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public static function render_page($atts) {
        // Check if user is logged in
        if (!is_user_logged_in()) {
            return Eau_Access_Denied::not_logged_in();
        }

        // Enqueue assets
        self::enqueue_assets();

        $user_id = get_current_user_id();
        $mem_type = get_user_meta($user_id, 'mem_type', true);
        $is_institution_admin = ($mem_type === 'institutionAdmin');

        ob_start();
        ?>
        <div class="eau-my-institution-container" id="eau-my-institution-container">

            <!-- Page Header -->
            <div class="eau-page-header">
                <div class="eau-page-header-title">
                    <h2>My Institution</h2>
                    <p class="eau-page-header-subtitle">
                        <?php if ($is_institution_admin): ?>
                            Manage your institution connections and member requests
                        <?php else: ?>
                            View your institution and request to join a new one
                        <?php endif; ?>
                    </p>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="eau-my-institution-stats" id="eau-my-institution-stats">
                <?php echo Eau_Skeleton::stats_cards(2); ?>
            </div>

            <!-- Current Institution Section -->
            <div class="eau-section" id="eau-current-institution-section">
                <div class="eau-section-header">
                    <h2 class="eau-section-title">
                        <i data-lucide="home"></i>
                        <?php echo $is_institution_admin ? 'My Institutions' : 'Current Institution'; ?>
                    </h2>
                </div>
                <div class="eau-section-body" id="eau-current-institution-body">
                    <?php echo Eau_Skeleton::card(); ?>
                </div>
            </div>

            <!-- Incoming Requests Section (only for institutionAdmin) -->
            <?php if ($is_institution_admin): ?>
            <div class="eau-section" id="eau-incoming-requests-section">
                <div class="eau-section-header">
                    <h2 class="eau-section-title">
                        <i data-lucide="inbox"></i>
                        Incoming Requests
                        <span class="eau-badge eau-badge-primary" id="eau-incoming-count" style="display: none;">0</span>
                    </h2>
                </div>
                <div class="eau-section-body" id="eau-incoming-requests-body">
                    <?php echo Eau_Skeleton::text(3); ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Search Institutions Section -->
            <div class="eau-section" id="eau-search-section">
                <div class="eau-section-header">
                    <h2 class="eau-section-title">
                        <i data-lucide="search"></i>
                        Find an Institution
                    </h2>
                </div>
                <div class="eau-section-body">
                    <!-- Search Bar -->
                    <div class="eau-search-wrapper eau-mb-4">
                        <i data-lucide="search"></i>
                        <input type="text"
                               class="eau-search-input"
                               id="eau-institution-search"
                               placeholder="Search by institution name, city, or state...">
                    </div>

                    <!-- Search Results -->
                    <div id="eau-search-results">
                        <p class="eau-text-muted eau-text-center">Enter a search term to find institutions</p>
                    </div>

                    <!-- Search Pagination -->
                    <div id="eau-search-pagination" style="display: none;"></div>
                </div>
            </div>

            <!-- Pending Requests Section -->
            <div class="eau-section" id="eau-pending-requests-section">
                <div class="eau-section-header">
                    <h2 class="eau-section-title">
                        <i data-lucide="clock"></i>
                        My Pending Requests
                        <span class="eau-badge eau-badge-warning" id="eau-pending-count" style="display: none;">0</span>
                    </h2>
                </div>
                <div class="eau-section-body" id="eau-pending-requests-body">
                    <?php echo Eau_Skeleton::text(2); ?>
                </div>
            </div>

            <!-- Request History Section (for institutionAdmin) -->
            <?php if ($is_institution_admin): ?>
            <div class="eau-section" id="eau-institution-history-section">
                <div class="eau-section-header">
                    <h2 class="eau-section-title">
                        <i data-lucide="history"></i>
                        Request History
                    </h2>
                </div>
                <div class="eau-section-body" id="eau-institution-history-body">
                    <?php echo Eau_Skeleton::text(3); ?>
                </div>
                <div id="eau-institution-history-pagination" class="eau-pagination-container" style="display: none;"></div>
            </div>
            <?php endif; ?>

            <!-- My Request History Section -->
            <div class="eau-section" id="eau-my-history-section">
                <div class="eau-section-header">
                    <h2 class="eau-section-title">
                        <i data-lucide="file-clock"></i>
                        My Request History
                    </h2>
                </div>
                <div class="eau-section-body" id="eau-my-history-body">
                    <?php echo Eau_Skeleton::text(3); ?>
                </div>
                <div id="eau-my-history-pagination" class="eau-pagination-container" style="display: none;"></div>
            </div>

            <!-- Modals -->
            <?php echo self::render_request_modal(); ?>
            <?php echo self::render_respond_modal(); ?>
            <?php echo self::render_leave_modal(); ?>
            <?php echo self::render_view_institution_modal(); ?>
            <?php if ($is_institution_admin): ?>
                <?php echo self::render_edit_institution_modal(); ?>
            <?php endif; ?>

        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render the request confirmation modal
     *
     * @return string HTML
     */
    private static function render_request_modal() {
        ob_start();
        ?>
        <div class="eau-modal-overlay" id="eau-request-modal-overlay" style="display: none;">
            <div class="eau-modal eau-modal-small" id="eau-request-modal">
                <div class="eau-modal-header">
                    <h2 class="eau-modal-title">
                        <i data-lucide="send"></i>
                        Request to Join
                    </h2>
                    <button class="eau-modal-close" type="button" data-action="close">
                        <i data-lucide="x"></i>
                    </button>
                </div>
                <div class="eau-modal-body">
                    <p>You are about to request to join:</p>
                    <div class="eau-institution-preview">
                        <strong id="eau-request-institution-name"></strong>
                    </div>
                    <div id="eau-request-warning" class="eau-alert eau-alert-warning" style="display: none;">
                        <i data-lucide="alert-triangle"></i>
                        <span>If approved, you will leave your current institution.</span>
                    </div>
                    <p class="eau-text-muted">The institution administrator will review your request.</p>
                </div>
                <div class="eau-modal-footer">
                    <button type="button" class="eau-btn eau-btn-secondary" data-action="close">
                        Cancel
                    </button>
                    <button type="button" class="eau-btn eau-btn-primary" id="eau-confirm-request-btn">
                        <i data-lucide="send"></i>
                        Send Request
                    </button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render the respond to request modal
     *
     * @return string HTML
     */
    private static function render_respond_modal() {
        ob_start();
        ?>
        <div class="eau-modal-overlay" id="eau-respond-modal-overlay" style="display: none;">
            <div class="eau-modal eau-modal-medium" id="eau-respond-modal">
                <div class="eau-modal-header">
                    <h2 class="eau-modal-title" id="eau-respond-modal-title">
                        <i data-lucide="user-check"></i>
                        Review Request
                    </h2>
                    <button class="eau-modal-close" type="button" data-action="close">
                        <i data-lucide="x"></i>
                    </button>
                </div>
                <div class="eau-modal-body">
                    <div class="eau-request-details">
                        <div class="eau-form-grid">
                            <div class="eau-form-field">
                                <label class="eau-form-label">Member Name</label>
                                <p class="eau-form-static" id="eau-respond-user-name"></p>
                            </div>
                            <div class="eau-form-field">
                                <label class="eau-form-label">Email</label>
                                <p class="eau-form-static" id="eau-respond-user-email"></p>
                            </div>
                            <div class="eau-form-field">
                                <label class="eau-form-label">Current Institution</label>
                                <p class="eau-form-static" id="eau-respond-current-institution"></p>
                            </div>
                            <div class="eau-form-field">
                                <label class="eau-form-label">Requested</label>
                                <p class="eau-form-static" id="eau-respond-request-date"></p>
                            </div>
                            <div class="eau-form-field eau-form-field-span-2">
                                <label class="eau-form-label">Requesting to Join</label>
                                <p class="eau-form-static eau-text-primary" id="eau-respond-institution-name"></p>
                            </div>
                        </div>
                    </div>

                    <div class="eau-form-field eau-mt-4">
                        <label class="eau-form-label" for="eau-respond-notes">Notes (optional)</label>
                        <textarea class="eau-form-input" id="eau-respond-notes" rows="3"
                                  placeholder="Add a note for the member (will be visible to them)..."></textarea>
                    </div>
                </div>
                <div class="eau-modal-footer">
                    <button type="button" class="eau-btn eau-btn-secondary" data-action="close">
                        Cancel
                    </button>
                    <button type="button" class="eau-btn eau-btn-danger" id="eau-reject-request-btn">
                        <i data-lucide="x"></i>
                        Reject
                    </button>
                    <button type="button" class="eau-btn eau-btn-success" id="eau-approve-request-btn">
                        <i data-lucide="check"></i>
                        Approve
                    </button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render the leave institution modal
     *
     * @return string HTML
     */
    private static function render_leave_modal() {
        ob_start();
        ?>
        <div class="eau-modal-overlay" id="eau-leave-modal-overlay" style="display: none;">
            <div class="eau-modal eau-modal-small" id="eau-leave-modal">
                <div class="eau-modal-header">
                    <h2 class="eau-modal-title">
                        <i data-lucide="log-out"></i>
                        Leave Institution
                    </h2>
                    <button class="eau-modal-close" type="button" data-action="close">
                        <i data-lucide="x"></i>
                    </button>
                </div>
                <div class="eau-modal-body">
                    <p>Are you sure you want to leave:</p>
                    <div class="eau-institution-preview">
                        <strong id="eau-leave-institution-name"></strong>
                    </div>
                    <div class="eau-alert eau-alert-warning">
                        <i data-lucide="alert-triangle"></i>
                        <span>You will need to request access to join another institution.</span>
                    </div>
                </div>
                <div class="eau-modal-footer">
                    <button type="button" class="eau-btn eau-btn-secondary" data-action="close">
                        Cancel
                    </button>
                    <button type="button" class="eau-btn eau-btn-danger" id="eau-confirm-leave-btn">
                        <i data-lucide="log-out"></i>
                        Leave Institution
                    </button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render the view institution details modal
     *
     * @return string HTML
     */
    private static function render_view_institution_modal() {
        ob_start();
        ?>
        <div class="eau-modal-overlay" id="eau-view-institution-modal-overlay" style="display: none;">
            <div class="eau-modal eau-modal-medium" id="eau-view-institution-modal">
                <div class="eau-modal-header">
                    <h2 class="eau-modal-title">
                        <i data-lucide="building-2"></i>
                        <span id="eau-view-institution-title">Institution Details</span>
                    </h2>
                    <button class="eau-modal-close" type="button" data-action="close">
                        <i data-lucide="x"></i>
                    </button>
                </div>
                <div class="eau-modal-body" id="eau-view-institution-body">
                    <?php echo Eau_Skeleton::form(6); ?>
                </div>
                <div class="eau-modal-footer">
                    <button type="button" class="eau-btn eau-btn-secondary" data-action="close">
                        Close
                    </button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render the edit institution modal (for institutionAdmin)
     *
     * @return string HTML
     */
    private static function render_edit_institution_modal() {
        // Get countries list for select
        $countries = \EauSystem\Helpers\Eau_Location_Data::get_countries();

        ob_start();
        ?>
        <div class="eau-modal-overlay" id="eau-edit-institution-modal-overlay" style="display: none;">
            <div class="eau-modal eau-modal-large" id="eau-edit-institution-modal">
                <div class="eau-modal-header">
                    <h2 class="eau-modal-title">
                        <i data-lucide="edit"></i>
                        <span id="eau-edit-institution-title">Edit Institution</span>
                    </h2>
                    <button class="eau-modal-close" type="button" data-action="close">
                        <i data-lucide="x"></i>
                    </button>
                </div>
                <div class="eau-modal-body" id="eau-edit-institution-body">
                    <form id="eau-edit-institution-form">
                        <input type="hidden" name="institution_id" id="eau-edit-institution-id">

                        <div class="eau-form-grid">
                            <!-- Institution Logo -->
                            <div class="eau-form-field eau-form-field-span-2">
                                <label class="eau-form-label">Institution Logo</label>
                                <?php
                                echo \EauSystem\Components\Eau_Media_Upload::field(
                                    'eau-edit-ins_company_logo',
                                    'ins_company_logo',
                                    '',
                                    array(
                                        'type' => 'media',
                                        'allowed_types' => 'image/*',
                                        'allowed_extensions' => 'jpg,jpeg,png,gif,webp,svg',
                                        'max_file_size' => 5 * 1024 * 1024, // 5MB
                                    )
                                );
                                ?>
                            </div>

                            <!-- Institution Name -->
                            <div class="eau-form-field eau-form-field-span-2">
                                <label class="eau-form-label" for="eau-edit-ins_company_name">
                                    Institution Name <span class="eau-required">*</span>
                                </label>
                                <input type="text" class="eau-form-input" id="eau-edit-ins_company_name"
                                       name="ins_company_name" required>
                            </div>

                            <!-- Company ID (readonly) -->
                            <div class="eau-form-field">
                                <label class="eau-form-label" for="eau-edit-ins_company_id">Company ID</label>
                                <input type="text" class="eau-form-input" id="eau-edit-ins_company_id"
                                       name="ins_company_id" readonly>
                            </div>

                            <!-- Status -->
                            <div class="eau-form-field">
                                <label class="eau-form-label" for="eau-edit-ins_status">Status</label>
                                <select class="eau-form-select" id="eau-edit-ins_status" name="ins_status">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>

                            <!-- Email -->
                            <div class="eau-form-field">
                                <label class="eau-form-label" for="eau-edit-ins_company_email">Email</label>
                                <input type="email" class="eau-form-input" id="eau-edit-ins_company_email"
                                       name="ins_company_email">
                            </div>

                            <!-- Phone (intl-tel-input) -->
                            <div class="eau-form-field">
                                <label class="eau-form-label" for="eau-edit-ins_company_company_phone">Phone</label>
                                <input type="tel" class="eau-form-input" id="eau-edit-ins_company_company_phone"
                                       name="ins_company_company_phone">
                            </div>

                            <!-- Address -->
                            <div class="eau-form-field eau-form-field-span-2">
                                <label class="eau-form-label" for="eau-edit-ins_company_company_address_line_1">Address</label>
                                <input type="text" class="eau-form-input" id="eau-edit-ins_company_company_address_line_1"
                                       name="ins_company_company_address_line_1">
                            </div>

                            <!-- Country -->
                            <div class="eau-form-field">
                                <label class="eau-form-label" for="eau-edit-ins_company_company_country">Country</label>
                                <select class="eau-form-select" id="eau-edit-ins_company_company_country" name="ins_company_company_country">
                                    <option value="">Select country...</option>
                                    <?php foreach ($countries as $code => $name): ?>
                                        <option value="<?php echo esc_attr($code); ?>"><?php echo esc_html($name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- State -->
                            <div class="eau-form-field">
                                <label class="eau-form-label" for="eau-edit-ins_company_company_state">State</label>
                                <select class="eau-form-select" id="eau-edit-ins_company_company_state" name="ins_company_company_state">
                                    <option value="">Select state...</option>
                                </select>
                                <input type="text" class="eau-form-input" id="eau-edit-ins_company_company_state_text"
                                       name="ins_company_company_state_text" style="display: none;" placeholder="Enter state...">
                            </div>

                            <!-- City -->
                            <div class="eau-form-field">
                                <label class="eau-form-label" for="eau-edit-ins_company_company_suburb">City</label>
                                <select class="eau-form-select" id="eau-edit-ins_company_company_suburb" name="ins_company_company_suburb">
                                    <option value="">Select city...</option>
                                </select>
                                <input type="text" class="eau-form-input" id="eau-edit-ins_company_company_suburb_text"
                                       name="ins_company_company_suburb_text" style="display: none;" placeholder="Enter city...">
                            </div>

                            <!-- Postcode -->
                            <div class="eau-form-field">
                                <label class="eau-form-label" for="eau-edit-ins_company_company_postcode">Postcode</label>
                                <input type="text" class="eau-form-input" id="eau-edit-ins_company_company_postcode"
                                       name="ins_company_company_postcode">
                            </div>
                        </div>
                    </form>
                </div>
                <div class="eau-modal-footer">
                    <button type="button" class="eau-btn eau-btn-secondary" data-action="close">
                        Cancel
                    </button>
                    <button type="button" class="eau-btn eau-btn-primary" id="eau-save-institution-btn">
                        <i data-lucide="save"></i>
                        Save Changes
                    </button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Enqueue page assets
     */
    public static function enqueue_assets() {
        $version = defined('EAU_SYSTEM_VERSION') ? EAU_SYSTEM_VERSION : '1.46.0';
        $plugin_url = defined('EAU_SYSTEM_PLUGIN_URL') ? EAU_SYSTEM_PLUGIN_URL : plugin_dir_url(dirname(__FILE__));

        // CSS
        wp_enqueue_style(
            'eau-components',
            $plugin_url . 'assets/css/eau-components.css',
            array(),
            $version
        );

        // intl-tel-input CSS
        wp_enqueue_style(
            'intl-tel-input',
            'https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/css/intlTelInput.css',
            array(),
            '18.2.1'
        );

        wp_enqueue_style(
            'eau-my-institution',
            $plugin_url . 'assets/css/eau-my-institution.css',
            array('eau-components', 'intl-tel-input'),
            $version
        );

        // Notifications JS
        wp_enqueue_script(
            'eau-notifications',
            $plugin_url . 'assets/js/eau-notifications.js',
            array('jquery'),
            $version,
            true
        );

        // intl-tel-input JS
        wp_enqueue_script(
            'intl-tel-input',
            'https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/intlTelInput.min.js',
            array(),
            '18.2.1',
            true
        );

        // Main JS
        wp_enqueue_script(
            'eau-my-institution',
            $plugin_url . 'assets/js/eau-my-institution.js',
            array('jquery', 'eau-notifications', 'intl-tel-input'),
            $version,
            true
        );

        // Localize script
        $user_id = get_current_user_id();
        $mem_type = get_user_meta($user_id, 'mem_type', true);

        wp_localize_script('eau-my-institution', 'eauMyInstitutionData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('eau_my_institution_nonce'),
            'userType' => $mem_type,
            'isInstitutionAdmin' => ($mem_type === 'institutionAdmin'),
            'strings' => array(
                'loading' => 'Loading...',
                'noInstitution' => 'You are not currently linked to any institution.',
                'noResults' => 'No institutions found. Try a different search term.',
                'noPendingRequests' => 'You have no pending requests.',
                'noIncomingRequests' => 'No pending requests to review.',
                'noHistory' => 'No request history yet.',
                'noInstitutionHistory' => 'No processed requests yet.',
                'confirmCancel' => 'Are you sure you want to cancel this request?',
                'requestSent' => 'Request sent successfully!',
                'requestCancelled' => 'Request cancelled.',
                'requestApproved' => 'Request approved!',
                'requestRejected' => 'Request rejected.',
                'leftInstitution' => 'You have left the institution.',
                'error' => 'An error occurred. Please try again.',
                'institutionUpdated' => 'Institution updated successfully!',
                'loadingInstitution' => 'Loading institution data...',
                'selectState' => 'Select state...',
                'selectCity' => 'Select city...',
                'loadingStates' => 'Loading states...',
                'loadingCities' => 'Loading cities...',
            ),
        ));

        // Lucide Icons
        wp_enqueue_script(
            'lucide-icons',
            'https://unpkg.com/lucide@latest/dist/umd/lucide.min.js',
            array(),
            null,
            true
        );

        // Media Upload Component Assets
        \EauSystem\Components\Eau_Media_Upload::enqueue_assets();
    }
}
