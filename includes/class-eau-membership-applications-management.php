<?php
namespace EauSystem;

use EauSystem\Components\Eau_Stats_Cards;
use EauSystem\Components\Eau_Data_Table;
use EauSystem\Components\Eau_Pagination;
use EauSystem\Components\Eau_Filters;
use EauSystem\Components\Eau_Modal;
use EauSystem\Components\Eau_Access_Denied;

/**
 * Membership Applications Management Page
 *
 * Shortcode: [eau_membership_applications]
 *
 * Allows admins to view, approve, and reject membership applications.
 *
 * @since 1.49.0
 */
class Eau_Membership_Applications_Management {

    /**
     * Register shortcode
     */
    public static function register_shortcode() {
        add_shortcode('eau_membership_applications', array(__CLASS__, 'render_shortcode'));
    }

    /**
     * Render the membership applications management page
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public static function render_shortcode($atts) {
        // Check if user is logged in
        if (!is_user_logged_in()) {
            return Eau_Access_Denied::not_logged_in();
        }

        // Only superAdmin and Admin can access
        if (!Eau_User_Institution_Helper::has_admin_access()) {
            return Eau_Access_Denied::no_permission();
        }

        // Enqueue assets
        self::enqueue_assets();

        // Get stats
        $stats = self::get_stats();

        ob_start();
        ?>
        <div class="eau-membership-applications-container">

            <!-- Stats Cards -->
            <?php echo self::render_stats_cards($stats); ?>

            <!-- Page Header -->
            <div class="eau-page-header">
                <div class="eau-page-header-title">
                    <h1>Membership Applications</h1>
                    <p class="eau-page-header-subtitle">Review and manage membership applications</p>
                </div>
                <div class="eau-page-header-actions">
                    <button class="eau-btn eau-btn-secondary" id="eau-export-applications-csv">
                        <i data-lucide="download"></i>
                        Export CSV
                    </button>
                </div>
            </div>

            <!-- Search and Filters -->
            <div class="eau-search-filters-bar">
                <div class="eau-search-wrapper">
                    <i data-lucide="search"></i>
                    <input
                        type="text"
                        class="eau-search-input"
                        placeholder="Search by name, email, or company..."
                        id="eau-applications-search"
                    >
                </div>
                <button class="eau-btn eau-btn-secondary" id="eau-filters-toggle">
                    <i data-lucide="filter"></i>
                    Filters
                </button>
            </div>

            <!-- Filters Panel -->
            <?php echo self::render_filters(); ?>

            <!-- Applications Table -->
            <?php echo self::render_applications_table(); ?>

            <!-- Pagination -->
            <div id="eau-pagination-container">
                <?php echo self::render_pagination(); ?>
            </div>

            <!-- Modals -->
            <?php echo self::render_modals(); ?>

        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Enqueue required assets
     */
    private static function enqueue_assets() {
        // Ensure eau-components is registered and enqueued
        if (!wp_style_is('eau-components', 'registered')) {
            wp_register_style(
                'eau-components',
                EAU_SYSTEM_PLUGIN_URL . 'assets/css/eau-components.css',
                array(),
                EAU_SYSTEM_VERSION
            );
        }
        wp_enqueue_style('eau-components');

        // Dashboard CSS (for cards styling)
        wp_enqueue_style(
            'eau-dashboard',
            EAU_SYSTEM_PLUGIN_URL . 'assets/css/eau-dashboard.css',
            array(),
            EAU_SYSTEM_VERSION
        );

        // CSS
        wp_enqueue_style(
            'eau-membership-applications',
            EAU_SYSTEM_PLUGIN_URL . 'assets/css/eau-membership-applications.css',
            array('eau-components', 'eau-dashboard'),
            EAU_SYSTEM_VERSION
        );

        // JavaScript
        wp_enqueue_script(
            'eau-membership-applications',
            EAU_SYSTEM_PLUGIN_URL . 'assets/js/eau-membership-applications.js',
            array('jquery'),
            EAU_SYSTEM_VERSION,
            true
        );

        // Get current user mem_type
        $current_user_id = get_current_user_id();
        $mem_type = get_user_meta($current_user_id, 'mem_type', true);

        // Localize script
        wp_localize_script('eau-membership-applications', 'eauMembershipApplications', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('eau_membership_applications'),
            'membership_types' => Eau_Membership_Types::get_all(),
            'mem_type' => $mem_type,
            'membersUrl' => '/dashboard/members/',
        ));
    }

    /**
     * Get applications statistics
     *
     * @return array
     */
    private static function get_stats() {
        global $wpdb;

        $table_name = Eau_Membership_Database::get_table_name(Eau_Membership_Database::TABLE_APPLICATIONS);

        // Total applications
        $total = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");

        // By status
        $pending = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE status = %s",
            'pending'
        ));

        $under_review = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE status = %s",
            'under_review'
        ));

        $approved = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE status = %s",
            'approved'
        ));

        $rejected = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE status = %s",
            'rejected'
        ));

        // This month
        $this_month = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE submitted_at >= %s",
            date('Y-m-01 00:00:00')
        ));

        return array(
            'total' => (int) $total,
            'pending' => (int) $pending,
            'under_review' => (int) $under_review,
            'approved' => (int) $approved,
            'rejected' => (int) $rejected,
            'this_month' => (int) $this_month,
        );
    }

    /**
     * Render stats cards - Following Dashboard pattern (icon on right)
     *
     * @param array $stats Statistics data
     * @return string HTML
     */
    private static function render_stats_cards($stats) {
        ob_start();
        ?>
        <div class="eau-dashboard-cards">
            <!-- Total Applications -->
            <div class="eau-dashboard-card eau-card-blue">
                <div class="eau-card-content">
                    <h3 class="eau-card-title">Total Applications</h3>
                    <div class="eau-card-stats">
                        <span class="eau-card-number"><?php echo number_format($stats['total']); ?></span>
                    </div>
                </div>
                <div class="eau-card-icon">
                    <i data-lucide="file-text"></i>
                </div>
            </div>

            <!-- Pending Review -->
            <div class="eau-dashboard-card eau-card-yellow">
                <div class="eau-card-content">
                    <h3 class="eau-card-title">Pending Review</h3>
                    <div class="eau-card-stats">
                        <span class="eau-card-number"><?php echo number_format($stats['pending'] + $stats['under_review']); ?></span>
                        <span class="eau-card-pending"><?php echo $stats['pending']; ?> new, <?php echo $stats['under_review']; ?> in review</span>
                    </div>
                </div>
                <div class="eau-card-icon">
                    <i data-lucide="clock"></i>
                </div>
            </div>

            <!-- Approved -->
            <div class="eau-dashboard-card eau-card-green">
                <div class="eau-card-content">
                    <h3 class="eau-card-title">Approved</h3>
                    <div class="eau-card-stats">
                        <span class="eau-card-number"><?php echo number_format($stats['approved']); ?></span>
                    </div>
                </div>
                <div class="eau-card-icon">
                    <i data-lucide="check-circle"></i>
                </div>
            </div>

            <!-- Rejected -->
            <div class="eau-dashboard-card eau-card-red">
                <div class="eau-card-content">
                    <h3 class="eau-card-title">Rejected</h3>
                    <div class="eau-card-stats">
                        <span class="eau-card-number"><?php echo number_format($stats['rejected']); ?></span>
                    </div>
                </div>
                <div class="eau-card-icon">
                    <i data-lucide="x-circle"></i>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render filters panel
     *
     * @return string HTML
     */
    private static function render_filters() {
        // Get membership types for filter
        $membership_types = Eau_Membership_Types::get_all();
        $type_options = array('' => 'All Types');
        foreach ($membership_types as $type) {
            $type_options[$type->type_key] = $type->type_label;
        }

        $filters_config = array(
            'id' => 'eau-applications-filters',
            'collapsible' => true,
            'show_clear' => true,
            'filters' => array(
                array(
                    'key' => 'status',
                    'label' => 'Status',
                    'type' => 'select',
                    'options' => array(
                        '' => 'All Statuses',
                        'pending' => 'Pending',
                        'under_review' => 'Under Review',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                        'cancelled' => 'Cancelled',
                    ),
                    'placeholder' => 'All Statuses',
                ),
                array(
                    'key' => 'membership_type',
                    'label' => 'Membership Type',
                    'type' => 'select',
                    'options' => $type_options,
                    'placeholder' => 'All Types',
                ),
                array(
                    'key' => 'date_from',
                    'label' => 'Date From',
                    'type' => 'date',
                ),
                array(
                    'key' => 'date_to',
                    'label' => 'Date To',
                    'type' => 'date',
                ),
            ),
        );

        $filters = new Eau_Filters($filters_config);
        return $filters->render();
    }

    /**
     * Render applications table
     *
     * @return string HTML
     */
    private static function render_applications_table() {
        $table_config = array(
            'id' => 'eau-applications-table',
            'columns' => array(
                array(
                    'key' => 'application_id',
                    'label' => 'ID',
                    'sortable' => true,
                ),
                array(
                    'key' => 'applicant',
                    'label' => 'APPLICANT',
                    'sortable' => true,
                ),
                array(
                    'key' => 'membership_type',
                    'label' => 'MEMBERSHIP TYPE',
                    'sortable' => true,
                ),
                array(
                    'key' => 'company_name',
                    'label' => 'ORGANIZATION',
                    'sortable' => true,
                ),
                array(
                    'key' => 'status',
                    'label' => 'STATUS',
                    'sortable' => true,
                ),
                array(
                    'key' => 'submitted_at',
                    'label' => 'SUBMITTED',
                    'sortable' => true,
                ),
            ),
            'actions' => array('view'),
            'selectable' => false,
            'ajax_endpoint' => 'eau_get_membership_applications',
            'empty_message' => 'No applications found',
            'loading_message' => 'Loading applications...',
        );

        $table = new Eau_Data_Table($table_config);
        return $table->render();
    }

    /**
     * Render pagination
     *
     * @return string HTML
     */
    private static function render_pagination() {
        $pagination_config = array(
            'id' => 'applications-pagination',
            'total_items' => 0,
            'per_page' => 20,
            'current_page' => 1,
            'max_pages_shown' => 5,
        );

        $pagination = new Eau_Pagination($pagination_config);
        return $pagination->render();
    }

    /**
     * Render modals
     *
     * @return string HTML
     */
    private static function render_modals() {
        $html = '';

        // Modal: View Application
        $view_modal_config = array(
            'id' => 'eau-view-application-modal',
            'title' => 'Application Details',
            'size' => 'large',
            'content' => self::render_view_modal_content(),
            'show_footer' => true,
            'footer_buttons' => array(
                array(
                    'id' => 'eau-close-view-modal',
                    'label' => 'Close',
                    'class' => 'eau-btn-secondary',
                    'action' => 'close',
                ),
            ),
        );
        $view_modal = new Eau_Modal($view_modal_config);
        $html .= $view_modal->render();

        // Additional footer buttons for view modal (will be shown/hidden via JS based on status)
        $html .= '
        <div id="eau-application-action-buttons" style="display:none;">
            <button type="button" class="eau-btn eau-btn-warning" id="eau-mark-under-review">
                <i data-lucide="eye"></i> Mark Under Review
            </button>
            <button type="button" class="eau-btn eau-btn-danger" id="eau-reject-application">
                <i data-lucide="x"></i> Reject
            </button>
            <button type="button" class="eau-btn eau-btn-success" id="eau-approve-application">
                <i data-lucide="check"></i> Approve
            </button>
            <button type="button" class="eau-btn eau-btn-danger" id="eau-cancel-application" style="display:none;">
                <i data-lucide="ban"></i> Cancel Approval
            </button>
        </div>
        ';

        // Modal: Approve Confirmation
        $approve_modal_config = array(
            'id' => 'eau-approve-modal',
            'title' => 'Approve Application',
            'size' => 'medium',
            'content' => self::render_approve_modal_content(),
            'show_footer' => true,
            'footer_buttons' => array(
                array(
                    'label' => 'Cancel',
                    'class' => 'eau-btn-secondary',
                    'action' => 'close',
                ),
                array(
                    'id' => 'eau-confirm-approve',
                    'label' => '<i data-lucide="check"></i> Confirm Approval',
                    'class' => 'eau-btn-success',
                    'action' => 'custom',
                ),
            ),
        );
        $approve_modal = new Eau_Modal($approve_modal_config);
        $html .= $approve_modal->render();

        // Modal: Reject Confirmation
        $reject_modal_config = array(
            'id' => 'eau-reject-modal',
            'title' => 'Reject Application',
            'size' => 'medium',
            'content' => self::render_reject_modal_content(),
            'show_footer' => true,
            'footer_buttons' => array(
                array(
                    'label' => 'Cancel',
                    'class' => 'eau-btn-secondary',
                    'action' => 'close',
                ),
                array(
                    'id' => 'eau-confirm-reject',
                    'label' => '<i data-lucide="x"></i> Confirm Rejection',
                    'class' => 'eau-btn-danger',
                    'action' => 'custom',
                ),
            ),
        );
        $reject_modal = new Eau_Modal($reject_modal_config);
        $html .= $reject_modal->render();

        // Modal: Cancel Confirmation (for approved applications)
        $cancel_modal_config = array(
            'id' => 'eau-cancel-modal',
            'title' => 'Cancel Approved Application',
            'size' => 'medium',
            'content' => self::render_cancel_modal_content(),
            'show_footer' => true,
            'footer_buttons' => array(
                array(
                    'label' => 'Close',
                    'class' => 'eau-btn-secondary',
                    'action' => 'close',
                ),
                array(
                    'id' => 'eau-confirm-cancel',
                    'label' => '<i data-lucide="ban"></i> Confirm Cancellation',
                    'class' => 'eau-btn-danger',
                    'action' => 'custom',
                ),
            ),
        );
        $cancel_modal = new Eau_Modal($cancel_modal_config);
        $html .= $cancel_modal->render();

        return $html;
    }

    /**
     * Render view modal content
     *
     * @return string HTML
     */
    private static function render_view_modal_content() {
        ob_start();
        ?>
        <div class="eau-application-details">
            <!-- Will be populated via JavaScript -->
            <div class="eau-skeleton-loader">
                <div class="eau-skeleton eau-skeleton-text"></div>
                <div class="eau-skeleton eau-skeleton-text"></div>
                <div class="eau-skeleton eau-skeleton-text"></div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render approve modal content
     *
     * @return string HTML
     */
    private static function render_approve_modal_content() {
        ob_start();
        ?>
        <div class="eau-approve-form">
            <div class="eau-alert eau-alert-info">
                <i data-lucide="info"></i>
                <div>
                    <strong>Approving this application will:</strong>
                    <ul>
                        <li>Update the user's membership type</li>
                        <li>Set their membership status to active</li>
                        <li>Create an institution (for Full Provider applications)</li>
                        <li>Send a confirmation email to the applicant</li>
                    </ul>
                </div>
            </div>

            <div class="eau-form-group">
                <label for="approve-admin-notes">Admin Notes (optional)</label>
                <textarea id="approve-admin-notes" class="eau-textarea" rows="3"
                    placeholder="Add any notes about this approval..."></textarea>
            </div>

            <div class="eau-form-group">
                <label>
                    <input type="checkbox" id="approve-send-email" checked>
                    Send confirmation email to applicant
                </label>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render reject modal content
     *
     * @return string HTML
     */
    private static function render_reject_modal_content() {
        ob_start();
        ?>
        <div class="eau-reject-form">
            <div class="eau-alert eau-alert-warning">
                <i data-lucide="alert-triangle"></i>
                <div>
                    <strong>This action cannot be undone.</strong>
                    <p>The applicant will be notified of the rejection.</p>
                </div>
            </div>

            <div class="eau-form-group">
                <label for="reject-reason">Rejection Reason <span class="required">*</span></label>
                <textarea id="reject-reason" class="eau-textarea" rows="3" required
                    placeholder="Please provide a reason for the rejection..."></textarea>
            </div>

            <div class="eau-form-group">
                <label>
                    <input type="checkbox" id="reject-send-email" checked>
                    Send rejection email to applicant
                </label>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render cancel modal content
     *
     * @since 1.51.46
     * @return string HTML
     */
    private static function render_cancel_modal_content() {
        ob_start();
        ?>
        <div class="eau-cancel-form">
            <div class="eau-alert eau-alert-danger">
                <i data-lucide="alert-triangle"></i>
                <div>
                    <strong>Warning: This will cancel the approved membership.</strong>
                    <ul>
                        <li>The user's membership status will be set to "cancelled"</li>
                        <li>This application will show as "Cancelled" in Payments Management</li>
                        <li>The member will be notified by email (optional)</li>
                    </ul>
                </div>
            </div>

            <div class="eau-form-group">
                <label for="cancel-reason">Cancellation Reason <span class="required">*</span></label>
                <textarea id="cancel-reason" class="eau-textarea" rows="3" required
                    placeholder="Please provide a reason for the cancellation..."></textarea>
            </div>

            <div class="eau-form-group">
                <label>
                    <input type="checkbox" id="cancel-send-email" checked>
                    Send cancellation notification to member
                </label>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
