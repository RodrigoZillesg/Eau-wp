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
            echo self::render_stats($data['stats']);
            echo self::render_filters();
            echo self::render_table();
            echo self::render_pagination();
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
     * @param  array $stats Estatísticas
     * @return string HTML
     */
    private static function render_stats($stats) {
        $cards = array(
            array(
                'label' => 'Total',
                'value' => $stats['total'],
                'icon'  => 'users',
                'class' => 'eau-icon-primary',
            ),
            array(
                'label' => 'Paid',
                'value' => $stats['paid'],
                'icon'  => 'check-circle',
                'class' => 'eau-icon-success',
                'text'  => 'eau-text-success',
            ),
            array(
                'label' => 'Pending',
                'value' => $stats['pending'],
                'icon'  => 'clock',
                'class' => 'eau-icon-warning',
                'text'  => 'eau-text-warning',
            ),
            array(
                'label' => 'Failed',
                'value' => $stats['failed'],
                'icon'  => 'x-circle',
                'class' => 'eau-icon-danger',
                'text'  => 'eau-text-danger',
            ),
            array(
                'label' => 'Revenue',
                'value' => '$' . number_format($stats['revenue'], 2),
                'icon'  => 'dollar-sign',
                'class' => 'eau-icon-revenue',
                'text'  => 'eau-text-revenue',
            ),
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
}
