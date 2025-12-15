<?php
/**
 * Payments Management Page
 *
 * Unified invoices management page for events and membership.
 * Shows registrations and applications (invoices) with payment management.
 *
 * Refatorado para seguir o padrão de Event Registrations:
 * - Tabela mostra FATURAS (registrations/applications), não pagamentos
 * - Modal permite adicionar pagamentos a uma fatura existente
 *
 * @package    EauSystem
 * @subpackage Includes
 * @since      1.50.1
 * @updated    1.51.0 - Refatorado para mostrar faturas
 */

namespace EauSystem;

use EauSystem\Components\Eau_Stats_Cards;
use EauSystem\Components\Eau_Data_Table;
use EauSystem\Components\Eau_Pagination;
use EauSystem\Components\Eau_Filters;
use EauSystem\Components\Eau_Modal;
use EauSystem\Components\Eau_Media_Upload;
use EauSystem\Components\Eau_Access_Denied;
use EauSystem\Payments\Payments_Post_Type;

if (!defined('WPINC')) {
    die;
}

/**
 * Class Eau_Payments_Management
 *
 * Renderiza a página de gestão de faturas (eventos + membership).
 *
 * @since 1.50.1
 */
class Eau_Payments_Management {

    /**
     * Inicializa a classe
     *
     * @since  1.50.1
     * @return void
     */
    public static function init() {
        add_shortcode('eau_payments_management', array(__CLASS__, 'render_shortcode'));
    }

    /**
     * Renderiza o shortcode
     *
     * @since  1.50.1
     * @return string HTML da página
     */
    public static function render_shortcode() {
        // Check if user is logged in
        if (!is_user_logged_in()) {
            return Eau_Access_Denied::not_logged_in();
        }

        // Check if user has admin access
        if (!Eau_User_Institution_Helper::has_admin_access()) {
            return Eau_Access_Denied::no_permission();
        }

        // Enqueue assets
        self::enqueue_assets();

        ob_start();
        ?>
        <div class="eau-payments-management-container">
            <?php
            echo self::render_stats_cards();
            echo self::render_page_header();
            echo self::render_search_and_filters();
            echo self::render_filters_panel();
            echo self::render_data_table();
            echo self::render_pagination();
            echo self::render_payment_modal();
            ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Enfileira assets
     *
     * @since  1.50.1
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
            'eau-payments-management',
            EAU_SYSTEM_PLUGIN_URL . 'assets/css/eau-payments-management.css',
            array('eau-components'),
            EAU_SYSTEM_VERSION
        );

        // Main JS
        wp_enqueue_script(
            'eau-payments-management',
            EAU_SYSTEM_PLUGIN_URL . 'assets/js/eau-payments-management.js',
            array('jquery'),
            EAU_SYSTEM_VERSION,
            true
        );

        // Localize script
        wp_localize_script('eau-payments-management', 'eauPaymentsManagement', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('eau_payments_management_nonce'),
            'i18n'    => array(
                'confirmDelete' => __('Are you sure you want to delete this payment?', 'eau-system'),
                'loading'       => __('Loading...', 'eau-system'),
                'noResults'     => __('No invoices found', 'eau-system'),
                'error'         => __('An error occurred', 'eau-system'),
            ),
        ));
    }

    /**
     * Renderiza cards de estatísticas
     *
     * @since  1.51.0
     * @return string HTML
     */
    private static function render_stats_cards() {
        // Stats will be loaded via AJAX
        $cards = array(
            array(
                'icon'   => 'file-text',
                'title'  => __('Total Due', 'eau-system'),
                'number' => '$0.00',
                'color'  => 'blue',
                'id'     => 'stat-total-due',
            ),
            array(
                'icon'   => 'dollar-sign',
                'title'  => __('Total Paid', 'eau-system'),
                'number' => '$0.00',
                'color'  => 'green',
                'id'     => 'stat-total-paid',
            ),
            array(
                'icon'   => 'clock',
                'title'  => __('Pending Payment', 'eau-system'),
                'number' => '0',
                'color'  => 'orange',
                'id'     => 'stat-pending',
            ),
            array(
                'icon'   => 'check-circle',
                'title'  => __('Fully Paid', 'eau-system'),
                'number' => '0',
                'color'  => 'purple',
                'id'     => 'stat-paid',
            ),
        );

        $stats_cards = new Eau_Stats_Cards($cards);
        return $stats_cards->render();
    }

    /**
     * Renderiza header da página
     *
     * @since  1.50.1
     * @return string HTML
     */
    private static function render_page_header() {
        ob_start();
        ?>
        <div class="eau-page-header">
            <div class="eau-page-header-title">
                <h1><?php esc_html_e('Payments Management', 'eau-system'); ?></h1>
                <p class="eau-page-header-subtitle"><?php esc_html_e('Manage event registrations and membership payments', 'eau-system'); ?></p>
            </div>
            <div class="eau-page-header-actions">
                <button type="button" class="eau-btn eau-btn-secondary" id="eau-export-csv-btn">
                    <i data-lucide="download"></i>
                    <?php esc_html_e('Export CSV', 'eau-system'); ?>
                </button>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renderiza barra de busca e toggle de filtros
     *
     * @since  1.50.1
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
                    placeholder="<?php esc_attr_e('Search by member name or email...', 'eau-system'); ?>"
                    id="eau-invoices-search"
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
     * Renderiza painel de filtros
     *
     * @since  1.50.1
     * @return string HTML
     */
    private static function render_filters_panel() {
        $filters_config = array(
            'id'      => 'eau-invoices-filters',
            'filters' => array(
                array(
                    'key'     => 'invoice_type',
                    'label'   => __('Type', 'eau-system'),
                    'type'    => 'select',
                    'options' => array(
                        ''           => __('All Types', 'eau-system'),
                        'event'      => __('Event', 'eau-system'),
                        'membership' => __('Membership', 'eau-system'),
                    ),
                ),
                array(
                    'key'     => 'payment_status',
                    'label'   => __('Payment Status', 'eau-system'),
                    'type'    => 'select',
                    'options' => array(
                        ''        => __('All Statuses', 'eau-system'),
                        'pending' => __('Pending', 'eau-system'),
                        'partial' => __('Partial', 'eau-system'),
                        'paid'    => __('Paid', 'eau-system'),
                        'free'    => __('Free', 'eau-system'),
                    ),
                ),
            ),
        );

        $filters = new Eau_Filters($filters_config);
        return $filters->render();
    }

    /**
     * Renderiza tabela de dados
     *
     * @since  1.51.0
     * @return string HTML
     */
    private static function render_data_table() {
        $columns = array(
            array(
                'key'      => 'member_name',
                'label'    => 'MEMBER',
                'sortable' => true,
            ),
            array(
                'key'      => 'invoice_type',
                'label'    => 'TYPE',
                'sortable' => true,
            ),
            array(
                'key'      => 'reference',
                'label'    => 'REFERENCE',
                'sortable' => true,
            ),
            array(
                'key'      => 'amount_due',
                'label'    => 'DUE',
                'sortable' => true,
            ),
            array(
                'key'      => 'amount_paid',
                'label'    => 'PAID',
                'sortable' => true,
            ),
            array(
                'key'      => 'balance',
                'label'    => 'BALANCE',
                'sortable' => true,
            ),
            array(
                'key'      => 'payment_status',
                'label'    => 'STATUS',
                'sortable' => true,
            ),
        );

        $table = new Eau_Data_Table(array(
            'id'            => 'eau-invoices-table',
            'columns'       => $columns,
            'actions'       => array('view'),
            'selectable'    => false,
            'empty_message' => __('No invoices found', 'eau-system'),
        ));
        return $table->render();
    }

    /**
     * Renderiza container de paginação
     *
     * @since  1.50.1
     * @return string HTML
     */
    private static function render_pagination() {
        return '<div id="eau-pagination-container"></div>';
    }

    /**
     * Renderiza modal de pagamento - Design profissional UI/UX
     *
     * @since  1.51.0
     * @updated 1.51.4 - Redesign completo UI/UX
     * @return string HTML
     */
    private static function render_payment_modal() {
        ob_start();
        ?>
        <!-- Payment Modal -->
        <div class="eau-modal-overlay" id="eau-payment-modal-overlay" style="display: none;">
            <div class="eau-modal eau-modal-lg" id="eau-payment-modal">
                <div class="eau-modal-header">
                    <h2 class="eau-modal-title">
                        <i data-lucide="receipt"></i>
                        <?php esc_html_e('Payment Details', 'eau-system'); ?>
                    </h2>
                    <button type="button" class="eau-modal-close" data-modal-action="close">&times;</button>
                </div>
                <div class="eau-modal-body">

                    <!-- Member Header Card -->
                    <div class="eau-pm-member-card">
                        <div class="eau-pm-member-avatar">
                            <i data-lucide="user"></i>
                        </div>
                        <div class="eau-pm-member-info">
                            <h3 class="eau-pm-member-name" id="eau-payment-member-name">-</h3>
                            <p class="eau-pm-member-email" id="eau-payment-member-email">-</p>
                        </div>
                        <div class="eau-pm-member-badge" id="eau-payment-status-badge">
                            <span class="eau-badge eau-badge-info">Event</span>
                        </div>
                    </div>

                    <!-- Reference Info -->
                    <div class="eau-pm-reference-card">
                        <div class="eau-pm-reference-icon">
                            <i data-lucide="file-text"></i>
                        </div>
                        <div class="eau-pm-reference-content">
                            <span class="eau-pm-reference-label"><?php esc_html_e('Reference', 'eau-system'); ?></span>
                            <span class="eau-pm-reference-value" id="eau-payment-reference">-</span>
                        </div>
                        <input type="hidden" id="eau-payment-type" value="">
                    </div>

                    <!-- Payment Summary Cards -->
                    <div class="eau-pm-summary-grid">
                        <div class="eau-pm-summary-card eau-pm-summary-due">
                            <div class="eau-pm-summary-icon">
                                <i data-lucide="file-text"></i>
                            </div>
                            <div class="eau-pm-summary-content">
                                <span class="eau-pm-summary-label"><?php esc_html_e('Amount Due', 'eau-system'); ?></span>
                                <span class="eau-pm-summary-value" id="eau-payment-amount-due">$0.00</span>
                            </div>
                        </div>
                        <div class="eau-pm-summary-card eau-pm-summary-paid">
                            <div class="eau-pm-summary-icon">
                                <i data-lucide="check-circle"></i>
                            </div>
                            <div class="eau-pm-summary-content">
                                <span class="eau-pm-summary-label"><?php esc_html_e('Total Paid', 'eau-system'); ?></span>
                                <span class="eau-pm-summary-value" id="eau-payment-total-paid">$0.00</span>
                            </div>
                        </div>
                        <div class="eau-pm-summary-card eau-pm-summary-balance">
                            <div class="eau-pm-summary-icon">
                                <i data-lucide="wallet"></i>
                            </div>
                            <div class="eau-pm-summary-content">
                                <span class="eau-pm-summary-label"><?php esc_html_e('Balance', 'eau-system'); ?></span>
                                <span class="eau-pm-summary-value" id="eau-payment-balance">$0.00</span>
                            </div>
                        </div>
                    </div>

                    <!-- Payment History Section -->
                    <div class="eau-pm-section">
                        <div class="eau-pm-section-header">
                            <h3 class="eau-pm-section-title">
                                <i data-lucide="history"></i>
                                <?php esc_html_e('Payment History', 'eau-system'); ?>
                            </h3>
                        </div>
                        <div class="eau-pm-payments-list" id="eau-payments-list">
                            <div class="eau-pm-empty-state">
                                <i data-lucide="inbox"></i>
                                <p><?php esc_html_e('No payments recorded yet', 'eau-system'); ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Add Payment Section -->
                    <div class="eau-pm-section eau-pm-add-section">
                        <div class="eau-pm-section-header">
                            <h3 class="eau-pm-section-title">
                                <i data-lucide="plus-circle"></i>
                                <?php esc_html_e('Record Payment', 'eau-system'); ?>
                            </h3>
                        </div>
                        <form id="eau-add-payment-form" class="eau-pm-form">
                            <input type="hidden" id="eau-invoice-id" name="invoice_id" value="">
                            <input type="hidden" id="eau-invoice-type" name="invoice_type" value="">

                            <div class="eau-pm-form-grid">
                                <div class="eau-pm-form-group">
                                    <label for="eau-payment-amount">
                                        <i data-lucide="dollar-sign"></i>
                                        <?php esc_html_e('Amount', 'eau-system'); ?> <span class="required">*</span>
                                    </label>
                                    <div class="eau-pm-input-with-prefix">
                                        <span class="eau-pm-input-prefix">$</span>
                                        <input type="number" id="eau-payment-amount" name="amount" step="0.01" min="0.01" placeholder="0.00" required>
                                    </div>
                                </div>

                                <div class="eau-pm-form-group">
                                    <label for="eau-payment-date">
                                        <i data-lucide="calendar"></i>
                                        <?php esc_html_e('Date', 'eau-system'); ?> <span class="required">*</span>
                                    </label>
                                    <input type="date" id="eau-payment-date" name="payment_date" required>
                                </div>

                                <div class="eau-pm-form-group">
                                    <label for="eau-payment-method">
                                        <i data-lucide="credit-card"></i>
                                        <?php esc_html_e('Method', 'eau-system'); ?> <span class="required">*</span>
                                    </label>
                                    <select id="eau-payment-method" name="payment_method" required>
                                        <option value=""><?php esc_html_e('Select...', 'eau-system'); ?></option>
                                        <option value="credit_card"><?php esc_html_e('Credit Card', 'eau-system'); ?></option>
                                        <option value="debit_card"><?php esc_html_e('Debit Card', 'eau-system'); ?></option>
                                        <option value="bank_transfer"><?php esc_html_e('Bank Transfer', 'eau-system'); ?></option>
                                        <option value="cash"><?php esc_html_e('Cash', 'eau-system'); ?></option>
                                        <option value="invoice"><?php esc_html_e('Invoice', 'eau-system'); ?></option>
                                        <option value="other"><?php esc_html_e('Other', 'eau-system'); ?></option>
                                    </select>
                                </div>

                                <div class="eau-pm-form-group">
                                    <label>
                                        <i data-lucide="paperclip"></i>
                                        <?php esc_html_e('Receipt', 'eau-system'); ?>
                                    </label>
                                    <?php
                                    echo Eau_Media_Upload::field(
                                        'eau-payment-receipt-id',
                                        'receipt_id',
                                        '',
                                        array(
                                            'type'               => 'media',
                                            'allowed_types'      => 'image/*,application/pdf',
                                            'allowed_extensions' => 'jpg,jpeg,png,gif,pdf',
                                            'max_file_size'      => 10 * 1024 * 1024,
                                        )
                                    );
                                    ?>
                                </div>
                            </div>

                            <div class="eau-pm-form-group eau-pm-form-full">
                                <label for="eau-payment-notes">
                                    <i data-lucide="message-square"></i>
                                    <?php esc_html_e('Notes', 'eau-system'); ?>
                                </label>
                                <textarea id="eau-payment-notes" name="notes" rows="2" placeholder="<?php esc_attr_e('Optional notes...', 'eau-system'); ?>"></textarea>
                            </div>

                            <div class="eau-pm-form-actions">
                                <button type="submit" class="eau-btn eau-btn-success" id="eau-add-payment-btn">
                                    <i data-lucide="plus"></i>
                                    <?php esc_html_e('Add Payment', 'eau-system'); ?>
                                </button>
                            </div>
                        </form>
                    </div>

                </div>
                <div class="eau-modal-footer">
                    <button type="button" class="eau-btn eau-btn-secondary" data-modal-action="close">
                        <?php esc_html_e('Close', 'eau-system'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
