<?php
/**
 * Event Registrations Templates
 *
 * @package    EauSystem
 * @subpackage EventRegistrations\Dashboard
 * @since      1.30.0
 */

namespace EauSystem\EventRegistrations\Dashboard;

if (!defined('WPINC')) {
    die;
}

/**
 * Class Registrations_Template
 *
 * Renderiza os templates HTML da página de registrations.
 *
 * @since 1.30.0
 */
class Registrations_Template {

    /**
     * Renderiza a página completa
     *
     * @since  1.30.0
     * @param  array $data Dados do evento
     * @return string HTML
     */
    public static function render($data) {
        ob_start();
        ?>
        <div class="eau-events-management-container eau-event-registrations-container" data-event-id="<?php echo esc_attr($data['id']); ?>">
            <?php
            echo self::render_back_link();
            echo self::render_header($data);
            echo self::render_stats($data['stats'], $data['is_free'] ?? false);
            echo self::render_filters();
            echo self::render_table();
            echo self::render_pagination();
            echo self::render_payment_modal();
            ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renderiza link de voltar
     *
     * @since  1.30.0
     * @return string HTML
     */
    private static function render_back_link() {
        ob_start();
        ?>
        <div class="eau-back-link">
            <a href="<?php echo esc_url(home_url('/dashboard/events/')); ?>">
                <i data-lucide="chevron-left"></i>
                Back to Events
            </a>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renderiza header da página
     *
     * @since  1.30.0
     * @param  array $data Dados do evento
     * @return string HTML
     */
    private static function render_header($data) {
        ob_start();
        ?>
        <div class="eau-page-header">
            <div class="eau-page-header-title">
                <h1><?php echo esc_html($data['title']); ?> - Registrations</h1>
                <p class="eau-page-header-subtitle">
                    <?php echo esc_html($data['date']); ?>
                    <?php if ($data['type']) : ?>
                        &bull; <?php echo esc_html(ucfirst($data['type'])); ?>
                    <?php endif; ?>
                </p>
            </div>
            <div class="eau-page-header-actions">
                <button type="button" class="eau-btn eau-btn-secondary" id="eau-export-csv">
                    <i data-lucide="download"></i>
                    Export CSV
                </button>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renderiza cards de estatísticas
     *
     * @since  1.30.0
     * @param  array $stats   Estatísticas
     * @param  bool  $is_free Se o evento é gratuito
     * @return string HTML
     */
    private static function render_stats($stats, $is_free = false) {
        // Valor do Revenue: "Free" se gratuito, senão valor formatado
        $revenue_value = $is_free ? 'Free' : '$' . number_format($stats['revenue'], 2);

        $cards = array(
            array(
                'label' => 'Total',
                'value' => $stats['total'],
                'icon'  => 'users',
                'class' => 'eau-icon-primary',
            ),
        );

        // Se evento é free, mostra card Free; senão mostra Paid
        if ($is_free) {
            $cards[] = array(
                'label' => 'Free',
                'value' => $stats['free'] ?? $stats['total'],
                'icon'  => 'gift',
                'class' => 'eau-icon-success',
                'text'  => 'eau-text-success',
            );
        } else {
            $cards[] = array(
                'label' => 'Paid',
                'value' => $stats['paid'],
                'icon'  => 'check-circle',
                'class' => 'eau-icon-success',
                'text'  => 'eau-text-success',
            );
        }

        $cards[] = array(
            'label' => 'Pending',
            'value' => $stats['pending'],
            'icon'  => 'clock',
            'class' => 'eau-icon-warning',
            'text'  => 'eau-text-warning',
        );
        $cards[] = array(
            'label' => 'Failed',
            'value' => $stats['failed'],
            'icon'  => 'x-circle',
            'class' => 'eau-icon-danger',
            'text'  => 'eau-text-danger',
        );
        $cards[] = array(
            'label' => 'Revenue',
            'value' => $revenue_value,
            'icon'  => 'dollar-sign',
            'class' => 'eau-icon-revenue',
            'text'  => 'eau-text-revenue',
        );

        ob_start();
        ?>
        <div class="eau-stats-cards-event">
            <?php foreach ($cards as $card) : ?>
                <?php echo self::render_stat_card($card); ?>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renderiza um card de estatística
     *
     * @since  1.30.0
     * @param  array $card Dados do card
     * @return string HTML
     */
    private static function render_stat_card($card) {
        $text_class = isset($card['text']) ? $card['text'] : '';
        ob_start();
        ?>
        <div class="eau-stats-card">
            <div class="eau-stats-card-inner">
                <div class="eau-stats-card-content">
                    <p class="eau-stats-card-label"><?php echo esc_html($card['label']); ?></p>
                    <p class="eau-stats-card-value <?php echo esc_attr($text_class); ?>">
                        <?php echo esc_html($card['value']); ?>
                    </p>
                </div>
                <div class="eau-stats-card-icon <?php echo esc_attr($card['class']); ?>">
                    <i data-lucide="<?php echo esc_attr($card['icon']); ?>"></i>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renderiza barra de filtros
     *
     * @since  1.30.0
     * @return string HTML
     */
    private static function render_filters() {
        ob_start();
        ?>
        <div class="eau-search-filters-bar">
            <div class="eau-search-wrapper">
                <i data-lucide="search"></i>
                <input
                    type="text"
                    class="eau-search-input"
                    placeholder="Search by name or email..."
                    id="eau-registrations-search"
                >
            </div>
            <div class="eau-filter-select-wrapper">
                <select id="eau-registrations-payment-filter" class="eau-filter-select">
                    <option value="">All Payments</option>
                    <option value="paid">Paid</option>
                    <option value="pending">Pending</option>
                    <option value="failed">Failed</option>
                    <option value="refunded">Refunded</option>
                </select>
            </div>
            <div class="eau-filter-select-wrapper">
                <select id="eau-registrations-status-filter" class="eau-filter-select">
                    <option value="">Status</option>
                    <option value="confirmed">Confirmed</option>
                    <option value="canceled">Canceled</option>
                    <option value="waitlist">Waitlist</option>
                </select>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renderiza estrutura da tabela
     *
     * @since  1.30.0
     * @return string HTML
     */
    private static function render_table() {
        ob_start();
        ?>
        <div class="eau-data-table-wrapper">
            <table class="eau-data-table" id="eau-registrations-table">
                <thead>
                    <tr>
                        <th class="eau-sortable" data-sort="attendee_name">
                            Member
                            <i data-lucide="chevrons-up-down"></i>
                        </th>
                        <th>Contact</th>
                        <th class="eau-sortable" data-sort="registration_date">
                            Registration Date
                            <i data-lucide="chevrons-up-down"></i>
                        </th>
                        <th>Payment</th>
                        <th>Attended</th>
                        <th class="eau-table-actions-col">Actions</th>
                    </tr>
                </thead>
                <tbody id="eau-registrations-tbody">
                    <!-- Filled by JavaScript -->
                </tbody>
            </table>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renderiza container de paginação
     *
     * @since  1.30.0
     * @return string HTML
     */
    private static function render_pagination() {
        return '<div id="eau-pagination-container"></div>';
    }

    /**
     * Renderiza modal de pagamento
     *
     * @since  1.45.0
     * @return string HTML
     */
    private static function render_payment_modal() {
        ob_start();
        ?>
        <!-- Payment Modal -->
        <div class="eau-modal" id="eau-payment-modal">
            <div class="eau-modal-overlay"></div>
            <div class="eau-modal-container eau-modal-lg">
                <div class="eau-modal-header">
                    <h2 class="eau-modal-title">
                        <i data-lucide="credit-card"></i>
                        <?php esc_html_e('Manage Payments', 'eau-system'); ?>
                    </h2>
                    <button type="button" class="eau-modal-close" id="eau-payment-modal-close">&times;</button>
                </div>
                <div class="eau-modal-body">
                    <!-- Registration Info -->
                    <div class="eau-payment-info-section">
                        <div class="eau-payment-info-row">
                            <span class="eau-payment-info-label"><?php esc_html_e('Attendee', 'eau-system'); ?>:</span>
                            <span class="eau-payment-info-value" id="eau-payment-attendee-name">-</span>
                        </div>
                        <div class="eau-payment-info-row">
                            <span class="eau-payment-info-label"><?php esc_html_e('Email', 'eau-system'); ?>:</span>
                            <span class="eau-payment-info-value" id="eau-payment-attendee-email">-</span>
                        </div>
                        <div class="eau-payment-info-row">
                            <span class="eau-payment-info-label"><?php esc_html_e('Event Price', 'eau-system'); ?>:</span>
                            <span class="eau-payment-info-value" id="eau-payment-event-price">$0.00</span>
                        </div>
                    </div>

                    <!-- Payment Summary -->
                    <div class="eau-payment-summary">
                        <div class="eau-payment-summary-item">
                            <span class="eau-payment-summary-label"><?php esc_html_e('Total Paid', 'eau-system'); ?></span>
                            <span class="eau-payment-summary-value eau-text-success" id="eau-payment-total-paid">$0.00</span>
                        </div>
                        <div class="eau-payment-summary-item">
                            <span class="eau-payment-summary-label"><?php esc_html_e('Balance Due', 'eau-system'); ?></span>
                            <span class="eau-payment-summary-value eau-text-warning" id="eau-payment-balance">$0.00</span>
                        </div>
                    </div>

                    <!-- Payments List -->
                    <div class="eau-payments-list-section">
                        <h3 class="eau-section-title"><?php esc_html_e('Payment History', 'eau-system'); ?></h3>
                        <div class="eau-payments-list" id="eau-payments-list">
                            <!-- Filled by JavaScript -->
                        </div>
                    </div>

                    <!-- Add Payment Form -->
                    <div class="eau-add-payment-section">
                        <h3 class="eau-section-title"><?php esc_html_e('Add New Payment', 'eau-system'); ?></h3>
                        <form id="eau-add-payment-form" class="eau-form">
                            <input type="hidden" id="eau-payment-registration-id" value="">

                            <div class="eau-form-grid eau-form-grid-2">
                                <div class="eau-form-field">
                                    <label for="eau-payment-amount"><?php esc_html_e('Amount', 'eau-system'); ?> *</label>
                                    <div class="eau-input-group">
                                        <span class="eau-input-prefix">$</span>
                                        <input type="number" id="eau-payment-amount" name="amount" step="0.01" min="0.01" required>
                                    </div>
                                </div>
                                <div class="eau-form-field">
                                    <label for="eau-payment-date"><?php esc_html_e('Payment Date', 'eau-system'); ?> *</label>
                                    <input type="date" id="eau-payment-date" name="payment_date" required>
                                </div>
                            </div>

                            <div class="eau-form-grid eau-form-grid-2">
                                <div class="eau-form-field">
                                    <label for="eau-payment-method"><?php esc_html_e('Payment Method', 'eau-system'); ?> *</label>
                                    <select id="eau-payment-method" name="payment_method" required>
                                        <option value=""><?php esc_html_e('Select method...', 'eau-system'); ?></option>
                                        <option value="credit_card"><?php esc_html_e('Credit Card', 'eau-system'); ?></option>
                                        <option value="debit_card"><?php esc_html_e('Debit Card', 'eau-system'); ?></option>
                                        <option value="bank_transfer"><?php esc_html_e('Bank Transfer', 'eau-system'); ?></option>
                                        <option value="pix"><?php esc_html_e('PIX', 'eau-system'); ?></option>
                                        <option value="cash"><?php esc_html_e('Cash', 'eau-system'); ?></option>
                                        <option value="invoice"><?php esc_html_e('Invoice', 'eau-system'); ?></option>
                                        <option value="other"><?php esc_html_e('Other', 'eau-system'); ?></option>
                                    </select>
                                </div>
                                <div class="eau-form-field">
                                    <label for="eau-payment-receipt"><?php esc_html_e('Receipt/Proof', 'eau-system'); ?></label>
                                    <div class="eau-file-upload-wrapper" id="eau-payment-receipt-wrapper">
                                        <input type="hidden" id="eau-payment-receipt-id" name="receipt_id" value="">
                                        <button type="button" class="eau-btn eau-btn-outline eau-btn-sm" id="eau-payment-upload-btn">
                                            <i data-lucide="upload"></i>
                                            <?php esc_html_e('Upload', 'eau-system'); ?>
                                        </button>
                                        <span class="eau-file-name" id="eau-payment-file-name"></span>
                                        <button type="button" class="eau-btn-icon eau-btn-remove-file" id="eau-payment-remove-file" style="display:none;">
                                            <i data-lucide="x"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="eau-form-field">
                                <label for="eau-payment-notes"><?php esc_html_e('Notes', 'eau-system'); ?></label>
                                <textarea id="eau-payment-notes" name="notes" rows="2" placeholder="<?php esc_attr_e('Optional notes about this payment...', 'eau-system'); ?>"></textarea>
                            </div>

                            <div class="eau-form-actions">
                                <button type="submit" class="eau-btn eau-btn-primary" id="eau-add-payment-btn">
                                    <i data-lucide="plus"></i>
                                    <?php esc_html_e('Add Payment', 'eau-system'); ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="eau-modal-footer">
                    <button type="button" class="eau-btn eau-btn-secondary" id="eau-payment-modal-done">
                        <?php esc_html_e('Done', 'eau-system'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
