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
 * For institutionAdmin, shows the full single page view with
 * all editing capabilities via integrated tabs.
 *
 * Shortcode: [eau_my_institution]
 *
 * @since 1.44.0
 * @since 1.65.0 Reestruturado com sistema de tabs e integração com single page
 */
class Eau_My_Institution {

    /**
     * Register the shortcode
     */
    public static function register_shortcode() {
        add_shortcode('eau_my_institution', array(__CLASS__, 'render_page'));
    }

    /**
     * Get all institutions the user is linked to
     *
     * @since 1.66.4 - Atualizado para usar o novo sistema de separação de papéis
     * @param int $user_id User ID
     * @return array Array of WP_Post objects
     */
    private static function get_user_all_institutions($user_id) {
        $institutions = array();

        // For institutionAdmin - get all managed institutions (novo sistema)
        if (Eau_User_Institution_Helper::is_institution_admin($user_id)) {
            $managed = Eau_User_Institution_Helper::get_user_managed_institutions($user_id);
            foreach ($managed as $inst) {
                $institutions[$inst->ID] = $inst;
            }
        }

        // For all users - get member institution (via mem_membercompanyname)
        $member_institution = Eau_User_Institution_Helper::get_user_institution($user_id);
        if ($member_institution && !isset($institutions[$member_institution->ID])) {
            $institutions[$member_institution->ID] = $member_institution;
        }

        return array_values($institutions);
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

        // v1.67.1 - Permite acesso mais amplo à página My Institution
        // Acesso permitido para:
        // - non-member (para solicitar afiliação)
        // - usuários vinculados a uma instituição (mem_membercompanyname definido)
        // - usuários com membership ativo
        // - admins e institutionAdmins
        $user_id = get_current_user_id();
        // v1.72.5: Usa helper para verificar non-member (suporta múltiplos tipos)
        $is_non_member = Eau_User_Institution_Helper::is_non_member($user_id);
        $is_linked_to_institution = !empty(Eau_User_Institution_Helper::get_user_institution($user_id));

        // Permite acesso se:
        // 1. É non-member (pode solicitar afiliação)
        // 2. Está vinculado a uma instituição
        // 3. Tem membership ativo
        if (!$is_non_member && !$is_linked_to_institution && !Eau_User_Institution_Helper::is_membership_active()) {
            return Eau_Access_Denied::membership_inactive();
        }

        // Enqueue assets
        self::enqueue_assets();

        // v1.66.4 - Usa o helper para verificar se é institution admin (novo sistema)
        // Nota: $user_id já foi declarado acima no access check
        $is_institution_admin = Eau_User_Institution_Helper::is_institution_admin($user_id);

        // Get user's institutions
        $user_institutions = self::get_user_all_institutions($user_id);
        $has_institution = !empty($user_institutions);
        $default_institution_id = $has_institution ? $user_institutions[0]->ID : 0;

        ob_start();
        ?>
        <div class="eau-my-institution-container" id="eau-my-institution-container"
             data-default-institution="<?php echo esc_attr($default_institution_id); ?>"
             data-has-institution="<?php echo $has_institution ? '1' : '0'; ?>">

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

            <!-- Stats Cards (institutionAdmin only) -->
            <?php if ($is_institution_admin): ?>
            <div class="eau-my-institution-stats" id="eau-my-institution-stats">
                <?php echo Eau_Skeleton::stats_cards(2); ?>
            </div>
            <?php endif; ?>

            <!-- TABS -->
            <div class="eau-tabs-wrapper">
                <div class="eau-tabs">
                    <button class="eau-tab active" data-tab="details">
                        <i data-lucide="building-2"></i>
                        <span class="eau-tab-text">Institution Details</span>
                    </button>
                    <button class="eau-tab" data-tab="search">
                        <i data-lucide="search"></i>
                        <span class="eau-tab-text">Search & Join</span>
                    </button>
                    <?php if ($is_institution_admin): ?>
                    <button class="eau-tab" data-tab="requests">
                        <i data-lucide="inbox"></i>
                        <span class="eau-tab-text">Requests</span>
                        <span class="eau-tab-badge" id="eau-requests-badge"></span>
                    </button>
                    <?php endif; ?>
                </div>

                <!-- TAB CONTENTS -->
                <div class="eau-tab-contents">
                    <!-- Tab 1: Institution Details -->
                    <div class="eau-tab-content active" data-tab="details">
                        <?php echo self::render_institution_details_tab($user_institutions, $is_institution_admin); ?>
                    </div>

                    <!-- Tab 2: Search & Join -->
                    <div class="eau-tab-content" data-tab="search">
                        <?php echo self::render_search_tab(); ?>
                    </div>

                    <!-- Tab 3: Requests (institutionAdmin) -->
                    <?php if ($is_institution_admin): ?>
                    <div class="eau-tab-content" data-tab="requests">
                        <?php echo self::render_requests_tab(); ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- My Request History (sempre visível abaixo das tabs) -->
            <?php echo self::render_my_history_section(); ?>

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
     * Render the Institution Details tab content
     *
     * Shows skeleton loading initially, content is loaded via AJAX
     * to ensure consistency with the working AJAX implementation.
     *
     * @param array $institutions Array of WP_Post objects (not used, kept for compatibility)
     * @param bool $is_institution_admin Whether user is institutionAdmin
     * @return string HTML
     */
    private static function render_institution_details_tab($institutions, $is_institution_admin) {
        ob_start();
        ?>
        <!-- Institution selector placeholder (will be populated via AJAX if needed) -->
        <div id="eau-institution-selector-container" style="display: none;"></div>

        <!-- Conteúdo da Single Page (embedded) - loaded via AJAX -->
        <div id="eau-institution-details-content">
            <?php echo Eau_Skeleton::card(); ?>
        </div>

        <!-- Leave section placeholder (for regular members) -->
        <div id="eau-leave-section-container" style="display: none;"></div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render the Search & Join tab content
     *
     * @return string HTML
     */
    private static function render_search_tab() {
        ob_start();
        ?>
        <!-- Search Section -->
        <div class="eau-search-section-content">
            <h3 class="eau-section-subtitle">
                <i data-lucide="building-2"></i>
                Find an Institution
            </h3>
            <p class="eau-section-description">Select an institution from the list below to view details and request to join.</p>

            <!-- Institution Search & Select -->
            <div class="eau-form-group eau-mb-4">
                <label for="eau-institution-filter" class="eau-form-label">
                    <i data-lucide="search"></i>
                    Search Institution
                </label>
                <div class="eau-institution-search-wrapper">
                    <input type="text"
                           id="eau-institution-filter"
                           class="eau-form-input eau-institution-filter-input"
                           placeholder="Type to filter institutions...">
                    <select id="eau-institution-select" class="eau-form-select eau-institution-select-large">
                        <option value="">-- Select an institution --</option>
                    </select>
                </div>
                <p class="eau-form-hint" id="eau-institution-select-hint">Loading institutions...</p>
            </div>

            <!-- Selected Institution Preview -->
            <div id="eau-selected-institution-preview" style="display: none;">
                <div class="eau-institution-preview-card">
                    <div class="eau-institution-preview-header">
                        <div class="eau-institution-preview-logo" id="eau-preview-logo">
                            <i data-lucide="building-2"></i>
                        </div>
                        <div class="eau-institution-preview-info">
                            <h4 id="eau-preview-name"></h4>
                            <p id="eau-preview-location" class="eau-text-muted"></p>
                        </div>
                    </div>
                    <div class="eau-institution-preview-details">
                        <div class="eau-preview-detail" id="eau-preview-type-container">
                            <span class="eau-preview-label">Type:</span>
                            <span id="eau-preview-type" class="eau-badge"></span>
                        </div>
                        <div class="eau-preview-detail" id="eau-preview-email-container">
                            <span class="eau-preview-label">Email:</span>
                            <span id="eau-preview-email"></span>
                        </div>
                        <div class="eau-preview-detail" id="eau-preview-phone-container">
                            <span class="eau-preview-label">Phone:</span>
                            <span id="eau-preview-phone"></span>
                        </div>
                    </div>
                    <div class="eau-institution-preview-actions">
                        <button type="button" class="eau-btn eau-btn-primary" id="eau-request-join-btn">
                            <i data-lucide="user-plus"></i>
                            Request to Join
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Divider -->
        <hr class="eau-section-divider">

        <!-- My Pending Requests Section -->
        <div class="eau-pending-section-content">
            <h3 class="eau-section-subtitle">
                <i data-lucide="clock"></i>
                My Pending Requests
                <span class="eau-badge eau-badge-warning" id="eau-pending-count" style="display: none;">0</span>
            </h3>
            <div id="eau-pending-requests-body">
                <?php echo Eau_Skeleton::text(2); ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render the Requests tab content (institutionAdmin only)
     *
     * @return string HTML
     */
    private static function render_requests_tab() {
        ob_start();
        ?>
        <!-- Incoming Requests Section -->
        <div class="eau-incoming-section-content">
            <h3 class="eau-section-subtitle">
                <i data-lucide="inbox"></i>
                Incoming Requests
                <span class="eau-badge eau-badge-primary" id="eau-incoming-count" style="display: none;">0</span>
            </h3>
            <div id="eau-incoming-requests-body">
                <?php echo Eau_Skeleton::text(3); ?>
            </div>
        </div>

        <!-- Divider -->
        <hr class="eau-section-divider">

        <!-- Institution Request History Section -->
        <div class="eau-institution-history-content">
            <h3 class="eau-section-subtitle">
                <i data-lucide="history"></i>
                Institution Request History
            </h3>
            <div id="eau-institution-history-body">
                <?php echo Eau_Skeleton::text(3); ?>
            </div>
            <div id="eau-institution-history-pagination" class="eau-pagination-container" style="display: none;"></div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render the My Request History section (always visible)
     *
     * @return string HTML
     */
    private static function render_my_history_section() {
        ob_start();
        ?>
        <div class="eau-section eau-my-history-section" id="eau-my-history-section">
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
        $version = defined('EAU_SYSTEM_VERSION') ? EAU_SYSTEM_VERSION : '1.65.0';
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

        // Institution Single CSS (para a embedded page)
        wp_enqueue_style(
            'eau-institution-single',
            $plugin_url . 'assets/css/eau-institution-single.css',
            array('eau-components'),
            $version
        );

        wp_enqueue_style(
            'eau-my-institution',
            $plugin_url . 'assets/css/eau-my-institution.css',
            array('eau-components', 'intl-tel-input', 'eau-institution-single'),
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

        // Lucide Icons
        wp_enqueue_script(
            'lucide-icons',
            'https://unpkg.com/lucide@latest/dist/umd/lucide.min.js',
            array(),
            null,
            true
        );

        // Institution Single JS (para as funcionalidades da embedded page)
        wp_enqueue_script(
            'eau-institution-single',
            $plugin_url . 'assets/js/eau-institution-single.js',
            array('jquery', 'lucide-icons', 'eau-notifications'),
            $version,
            true
        );

        // Main JS
        wp_enqueue_script(
            'eau-my-institution',
            $plugin_url . 'assets/js/eau-my-institution.js',
            array('jquery', 'eau-notifications', 'intl-tel-input', 'lucide-icons', 'eau-institution-single'),
            $version,
            true
        );

        // Localize Institution Single script
        $user_id = get_current_user_id();
        $user_institutions = self::get_user_all_institutions($user_id);
        $default_institution_id = !empty($user_institutions) ? $user_institutions[0]->ID : 0;

        if ($default_institution_id) {
            $permissions = Eau_Institution_Single::get_user_permissions($default_institution_id);
            $can_edit_members = Eau_Institution_Single::user_can_edit_members($default_institution_id);

            wp_localize_script('eau-institution-single', 'eauInstitutionSingleData', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('eau_institution_single_nonce'),
                'canEdit' => $permissions['can_edit'],
                'canEditRestricted' => $permissions['can_edit_restricted'],
                'canEditMembers' => $can_edit_members,
                'canManageAdmins' => $permissions['can_manage_admins'],
            ));
        }

        // Localize My Institution script
        // v1.66.4 - Usa o helper para verificar se é institution admin (novo sistema)
        $is_inst_admin = Eau_User_Institution_Helper::is_institution_admin($user_id);

        wp_localize_script('eau-my-institution', 'eauMyInstitutionData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('eau_my_institution_nonce'),
            'userType' => get_user_meta($user_id, 'mem_type', true),
            'isInstitutionAdmin' => $is_inst_admin,
            'defaultInstitutionId' => $default_institution_id,
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
                'loadingInstitutionDetails' => 'Loading institution details...',
            ),
        ));

        // Media Upload Component Assets
        \EauSystem\Components\Eau_Media_Upload::enqueue_assets();
    }
}
