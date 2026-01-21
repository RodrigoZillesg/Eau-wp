<?php
/**
 * My Payments Page
 *
 * Personal payments history page where any logged-in user can view
 * all their payments made to English Australia.
 *
 * @package    EauSystem
 * @subpackage Includes
 * @since      1.53.8
 */

namespace EauSystem;

use EauSystem\Components\Eau_Stats_Cards;
use EauSystem\Components\Eau_Data_Table;
use EauSystem\Components\Eau_Pagination;
use EauSystem\Components\Eau_Filters;
use EauSystem\Components\Eau_Access_Denied;

if (!defined('WPINC')) {
    die;
}

/**
 * Class Eau_My_Payments
 *
 * Renders the personal payments history page.
 *
 * @since 1.53.8
 */
class Eau_My_Payments {

    /**
     * Registers the shortcode
     *
     * @since  1.53.8
     * @return void
     */
    public static function register_shortcode() {
        add_shortcode('eau_my_payments', array(__CLASS__, 'render_shortcode'));
    }

    /**
     * Renders the shortcode
     *
     * @since  1.53.8
     * @return string HTML
     */
    public static function render_shortcode() {
        // Check if user is logged in
        if (!is_user_logged_in()) {
            return Eau_Access_Denied::not_logged_in();
        }

        // Enqueue assets
        self::enqueue_assets();

        ob_start();
        ?>
        <div class="eau-my-payments-container">
            <?php
            echo self::render_stats_cards();
            echo self::render_page_header();
            echo self::render_search_and_filters();
            echo self::render_filters_panel();
            echo self::render_data_table();
            echo self::render_pagination();
            echo self::render_payment_detail_modal();
            ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Enqueues assets
     *
     * @since  1.53.8
     * @return void
     */
    private static function enqueue_assets() {
        // Ensure eau-components is loaded
        if (!wp_style_is('eau-components', 'registered')) {
            wp_register_style(
                'eau-components',
                EAU_SYSTEM_PLUGIN_URL . 'assets/css/eau-components.css',
                array(),
                EAU_SYSTEM_VERSION
            );
        }
        wp_enqueue_style('eau-components');

        // Main CSS
        wp_enqueue_style(
            'eau-my-payments',
            EAU_SYSTEM_PLUGIN_URL . 'assets/css/eau-my-payments.css',
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

        // Main JS
        wp_enqueue_script(
            'eau-my-payments',
            EAU_SYSTEM_PLUGIN_URL . 'assets/js/eau-my-payments.js',
            array('jquery', 'eau-notifications'),
            EAU_SYSTEM_VERSION,
            true
        );

        // Get checkout URL (v1.71.0)
        $checkout_page_id = Eau_Pages::get_page_id('checkout');
        $checkout_url = $checkout_page_id ? get_permalink($checkout_page_id) : home_url('/checkout/');

        // Localize script
        wp_localize_script('eau-my-payments', 'eauMyPayments', array(
            'ajaxUrl'     => admin_url('admin-ajax.php'),
            'nonce'       => wp_create_nonce('eau_my_payments_nonce'),
            'checkoutUrl' => $checkout_url,
            'i18n'        => array(
                'loading'      => __('Loading...', 'eau-system'),
                'noResults'    => __('No payments found', 'eau-system'),
                'error'        => __('An error occurred', 'eau-system'),
                'payNow'       => __('Pay Now', 'eau-system'),
                'viewDetails'  => __('View Details', 'eau-system'),
                'retryPayment' => __('Retry Payment', 'eau-system'),
            ),
        ));
    }

    /**
     * Renders stats cards
     *
     * @since  1.53.8
     * @updated 1.71.0 - Added Pending Payments and Course Payments cards
     * @return string HTML
     */
    private static function render_stats_cards() {
        // Stats will be loaded via AJAX
        $cards = array(
            array(
                'icon'   => 'clock',
                'title'  => __('Pending Payments', 'eau-system'),
                'number' => '$0.00',
                'color'  => 'orange',
                'id'     => 'stat-pending-payments',
            ),
            array(
                'icon'   => 'dollar-sign',
                'title'  => __('Total Paid', 'eau-system'),
                'number' => '$0.00',
                'color'  => 'green',
                'id'     => 'stat-total-paid',
            ),
            array(
                'icon'   => 'calendar',
                'title'  => __('Event Payments', 'eau-system'),
                'number' => '0',
                'color'  => 'purple',
                'id'     => 'stat-event-payments',
            ),
            array(
                'icon'   => 'graduation-cap',
                'title'  => __('Course Payments', 'eau-system'),
                'number' => '0',
                'color'  => 'blue',
                'id'     => 'stat-course-payments',
            ),
        );

        $stats_cards = new Eau_Stats_Cards($cards);
        return $stats_cards->render();
    }

    /**
     * Renders page header
     *
     * @since  1.53.8
     * @return string HTML
     */
    private static function render_page_header() {
        ob_start();
        ?>
        <div class="eau-page-header">
            <div class="eau-page-header-title">
                <h2><?php esc_html_e('My Payments', 'eau-system'); ?></h2>
                <p class="eau-page-header-subtitle"><?php esc_html_e('View your payment history with English Australia', 'eau-system'); ?></p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renders search and filters bar
     *
     * @since  1.53.8
     * @return string HTML
     */
    private static function render_search_and_filters() {
        ob_start();
        ?>
        <div class="eau-search-filters-bar">
            <div class="eau-search-wrapper">
                <i data-lucide="search"></i>
                <input
                    type="text"
                    class="eau-search-input"
                    placeholder="<?php esc_attr_e('Search payments...', 'eau-system'); ?>"
                    id="eau-my-payments-search"
                >
            </div>
            <button type="button" class="eau-btn eau-btn-secondary eau-filters-toggle" id="eau-filters-toggle">
                <i data-lucide="sliders-horizontal"></i>
                <?php esc_html_e('Filters', 'eau-system'); ?>
            </button>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renders filters panel
     *
     * @since  1.53.8
     * @updated 1.71.0 - Added course type and status filter
     * @return string HTML
     */
    private static function render_filters_panel() {
        $current_year = date('Y');
        $year_options = array('' => __('All Years', 'eau-system'));
        for ($y = $current_year; $y >= $current_year - 5; $y--) {
            $year_options[$y] = $y;
        }

        $filters_config = array(
            'id'      => 'eau-my-payments-filters',
            'filters' => array(
                array(
                    'key'     => 'payment_type',
                    'label'   => __('Type', 'eau-system'),
                    'type'    => 'select',
                    'options' => array(
                        ''           => __('All Types', 'eau-system'),
                        'event'      => __('Event', 'eau-system'),
                        'course'     => __('Course', 'eau-system'),
                        'membership' => __('Membership', 'eau-system'),
                    ),
                ),
                array(
                    'key'     => 'status',
                    'label'   => __('Status', 'eau-system'),
                    'type'    => 'select',
                    'options' => array(
                        ''          => __('All Statuses', 'eau-system'),
                        'pending'   => __('Pending', 'eau-system'),
                        'completed' => __('Completed', 'eau-system'),
                        'failed'    => __('Failed', 'eau-system'),
                    ),
                ),
                array(
                    'key'     => 'year',
                    'label'   => __('Year', 'eau-system'),
                    'type'    => 'select',
                    'options' => $year_options,
                ),
            ),
        );

        $filters = new Eau_Filters($filters_config);
        return $filters->render();
    }

    /**
     * Renders data table
     *
     * @since  1.53.8
     * @updated 1.71.0 - Added pay action for pending payments
     * @return string HTML
     */
    private static function render_data_table() {
        $columns = array(
            array(
                'key'      => 'date',
                'label'    => __('DATE', 'eau-system'),
                'sortable' => true,
            ),
            array(
                'key'      => 'type',
                'label'    => __('TYPE', 'eau-system'),
                'sortable' => true,
            ),
            array(
                'key'      => 'reference',
                'label'    => __('REFERENCE', 'eau-system'),
                'sortable' => true,
            ),
            array(
                'key'      => 'amount',
                'label'    => __('AMOUNT', 'eau-system'),
                'sortable' => true,
            ),
            array(
                'key'      => 'status',
                'label'    => __('STATUS', 'eau-system'),
                'sortable' => true,
            ),
            array(
                'key'      => 'actions_custom',
                'label'    => __('ACTIONS', 'eau-system'),
                'sortable' => false,
            ),
        );

        $table = new Eau_Data_Table(array(
            'id'            => 'eau-my-payments-table',
            'columns'       => $columns,
            'actions'       => array(), // Custom actions rendered in column
            'selectable'    => false,
            'empty_message' => __('No payments found', 'eau-system'),
        ));
        return $table->render();
    }

    /**
     * Renders pagination container
     *
     * @since  1.53.8
     * @return string HTML
     */
    private static function render_pagination() {
        return '<div id="eau-pagination-container"></div>';
    }

    /**
     * Renders payment detail modal
     *
     * @since  1.53.8
     * @return string HTML
     */
    private static function render_payment_detail_modal() {
        ob_start();
        ?>
        <!-- Payment Detail Modal -->
        <div class="eau-modal-overlay" id="eau-payment-detail-modal-overlay" style="display: none;">
            <div class="eau-modal eau-modal-md" id="eau-payment-detail-modal">
                <div class="eau-modal-header">
                    <h2 class="eau-modal-title">
                        <i data-lucide="receipt"></i>
                        <?php esc_html_e('Payment Details', 'eau-system'); ?>
                    </h2>
                    <button type="button" class="eau-modal-close" data-modal-action="close">&times;</button>
                </div>
                <div class="eau-modal-body">
                    <!-- Payment Header -->
                    <div class="eau-mp-payment-header">
                        <div class="eau-mp-payment-type-badge" id="eau-mp-type-badge">
                            <span class="eau-badge eau-badge-info">Event</span>
                        </div>
                        <div class="eau-mp-payment-amount" id="eau-mp-amount">$0.00</div>
                        <div class="eau-mp-payment-status" id="eau-mp-status">
                            <span class="eau-badge eau-badge-success"><?php esc_html_e('Confirmed', 'eau-system'); ?></span>
                        </div>
                    </div>

                    <!-- Payment Details Grid -->
                    <div class="eau-mp-details-grid">
                        <div class="eau-mp-detail-item">
                            <div class="eau-mp-detail-icon">
                                <i data-lucide="calendar"></i>
                            </div>
                            <div class="eau-mp-detail-content">
                                <span class="eau-mp-detail-label"><?php esc_html_e('Date', 'eau-system'); ?></span>
                                <span class="eau-mp-detail-value" id="eau-mp-date">-</span>
                            </div>
                        </div>

                        <div class="eau-mp-detail-item">
                            <div class="eau-mp-detail-icon">
                                <i data-lucide="credit-card"></i>
                            </div>
                            <div class="eau-mp-detail-content">
                                <span class="eau-mp-detail-label"><?php esc_html_e('Payment Method', 'eau-system'); ?></span>
                                <span class="eau-mp-detail-value" id="eau-mp-method">-</span>
                            </div>
                        </div>

                        <div class="eau-mp-detail-item">
                            <div class="eau-mp-detail-icon">
                                <i data-lucide="file-text"></i>
                            </div>
                            <div class="eau-mp-detail-content">
                                <span class="eau-mp-detail-label"><?php esc_html_e('Reference', 'eau-system'); ?></span>
                                <span class="eau-mp-detail-value" id="eau-mp-reference">-</span>
                            </div>
                        </div>

                        <div class="eau-mp-detail-item" id="eau-mp-transaction-container" style="display: none;">
                            <div class="eau-mp-detail-icon">
                                <i data-lucide="hash"></i>
                            </div>
                            <div class="eau-mp-detail-content">
                                <span class="eau-mp-detail-label"><?php esc_html_e('Transaction ID', 'eau-system'); ?></span>
                                <span class="eau-mp-detail-value eau-mp-monospace" id="eau-mp-transaction">-</span>
                            </div>
                        </div>
                    </div>

                    <!-- Notes Section -->
                    <div class="eau-mp-notes-section" id="eau-mp-notes-section" style="display: none;">
                        <h4 class="eau-mp-notes-title">
                            <i data-lucide="message-square"></i>
                            <?php esc_html_e('Notes', 'eau-system'); ?>
                        </h4>
                        <div class="eau-mp-notes-content" id="eau-mp-notes">-</div>
                    </div>

                    <!-- Receipt Link -->
                    <div class="eau-mp-receipt-section" id="eau-mp-receipt-section" style="display: none;">
                        <a href="#" target="_blank" class="eau-btn eau-btn-secondary" id="eau-mp-receipt-link">
                            <i data-lucide="file-text"></i>
                            <?php esc_html_e('View Receipt', 'eau-system'); ?>
                        </a>
                    </div>
                </div>
                <div class="eau-modal-footer">
                    <button type="button" class="eau-btn eau-btn-secondary" id="eau-mp-download-receipt-btn">
                        <i data-lucide="download"></i>
                        <?php esc_html_e('Download Receipt', 'eau-system'); ?>
                    </button>
                    <button type="button" class="eau-btn eau-btn-primary" data-modal-action="close">
                        <?php esc_html_e('Close', 'eau-system'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
