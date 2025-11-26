<?php
/**
 * Event Registrations Page (Frontend Dashboard)
 *
 * Shortcode: [eau_event_registrations]
 * URL: /dashboard/events/{slug}/registrations/
 *
 * @package    EauSystem
 * @subpackage Events\Admin
 * @since      1.29.3
 */

namespace EauSystem\Events\Admin;

use EauSystem\Components\Eau_Access_Denied;
use EauSystem\Events\Config;

if (!defined('WPINC')) {
    die;
}

/**
 * Class Eau_Event_Registrations_Page
 *
 * Renderiza página de registrations de um evento no dashboard.
 *
 * @since 1.29.3
 */
class Eau_Event_Registrations_Page {

    /**
     * Registra o shortcode e rewrite rules
     *
     * @since  1.29.3
     * @return void
     */
    public static function register() {
        add_shortcode('eau_event_registrations', array(__CLASS__, 'render'));
        add_action('init', array(__CLASS__, 'add_rewrite_rules'));
        add_filter('query_vars', array(__CLASS__, 'add_query_vars'));
        add_action('template_redirect', array(__CLASS__, 'handle_rewrite'));
    }

    /**
     * Adiciona rewrite rules para pretty URL
     *
     * @since  1.29.3
     * @return void
     */
    public static function add_rewrite_rules() {
        add_rewrite_rule(
            '^dashboard/events/([^/]+)/registrations/?$',
            'index.php?pagename=dashboard&eau_event_slug=$matches[1]&eau_registrations=1',
            'top'
        );
    }

    /**
     * Adiciona query vars
     *
     * @since  1.29.3
     * @param  array $vars Query vars
     * @return array
     */
    public static function add_query_vars($vars) {
        $vars[] = 'eau_event_slug';
        $vars[] = 'eau_registrations';
        return $vars;
    }

    /**
     * Handle rewrite - injeta shortcode na página
     *
     * @since  1.29.3
     * @return void
     */
    public static function handle_rewrite() {
        $event_slug = get_query_var('eau_event_slug');
        $is_registrations = get_query_var('eau_registrations');

        if ($event_slug && $is_registrations) {
            // Store for later use
            set_query_var('eau_event_registrations_slug', $event_slug);

            // Add filter to inject content
            add_filter('the_content', array(__CLASS__, 'inject_shortcode'), 999);
        }
    }

    /**
     * Injeta o shortcode no conteúdo
     *
     * @since  1.29.3
     * @param  string $content Content
     * @return string
     */
    public static function inject_shortcode($content) {
        if (is_main_query() && in_the_loop()) {
            remove_filter('the_content', array(__CLASS__, 'inject_shortcode'), 999);
            return do_shortcode('[eau_event_registrations]');
        }
        return $content;
    }

    /**
     * Renderiza a página de Event Registrations
     *
     * @since  1.29.3
     * @param  array $atts Atributos do shortcode
     * @return string HTML da página
     */
    public static function render($atts = array()) {
        // Verifica se usuário está logado
        if (!is_user_logged_in()) {
            return Eau_Access_Denied::not_logged_in();
        }

        // Verifica permissão (Admin ou Super Admin)
        $user_id = get_current_user_id();
        $mem_type = get_user_meta($user_id, 'mem_type', true);

        if (!in_array($mem_type, array('superAdmin', 'Admin'))) {
            return Eau_Access_Denied::no_permission();
        }

        // Get event slug from URL
        $event_slug = get_query_var('eau_event_registrations_slug');
        if (!$event_slug) {
            $event_slug = get_query_var('eau_event_slug');
        }
        if (!$event_slug && isset($_GET['event'])) {
            $event_slug = sanitize_text_field($_GET['event']);
        }

        if (!$event_slug) {
            return '<div class="eau-error-message">Event not specified</div>';
        }

        // Get event by slug
        $event = get_page_by_path($event_slug, OBJECT, Config\POST_TYPE);

        if (!$event) {
            return '<div class="eau-error-message">Event not found</div>';
        }

        // Get event data
        $event_id = $event->ID;
        $event_title = $event->post_title;
        $start_datetime = get_post_meta($event_id, 'evt_start_datetime', true);
        $event_type = get_post_meta($event_id, 'evt_event_type', true) ?: 'in-person';

        // Format date
        $date_formatted = '';
        if ($start_datetime) {
            $date_obj = \DateTime::createFromFormat('Y-m-d\TH:i', $start_datetime);
            if (!$date_obj) {
                $date_obj = \DateTime::createFromFormat('Y-m-d H:i:s', $start_datetime);
            }
            if ($date_obj) {
                $date_formatted = $date_obj->format('F j, Y');
            }
        }

        // Get event price for revenue calculation
        $member_price = get_post_meta($event_id, 'evt_member_price', true);
        $member_price = $member_price ? floatval($member_price) : 0;

        // Get stats
        $stats = self::get_stats($event_id, $member_price);

        // Carrega assets
        self::enqueue_assets();

        ob_start();
        ?>
        <div class="eau-events-management-container eau-event-registrations-container" data-event-id="<?php echo esc_attr($event_id); ?>">

            <!-- Back Link -->
            <div class="eau-back-link">
                <a href="<?php echo esc_url(home_url('/dashboard/manage-events/')); ?>">
                    <i data-lucide="chevron-left"></i>
                    Back to Events
                </a>
            </div>

            <!-- Page Header -->
            <div class="eau-page-header">
                <div class="eau-page-header-title">
                    <h1><?php echo esc_html($event_title); ?> - Registrations</h1>
                    <p class="eau-page-header-subtitle">
                        <?php echo esc_html($date_formatted); ?>
                        <?php if ($event_type) : ?>
                            &bull; <?php echo esc_html(ucfirst($event_type)); ?>
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

            <!-- Stats Cards -->
            <?php echo self::render_stats_cards($stats); ?>

            <!-- Search and Filters -->
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
                    <select id="eau-registrations-status-filter" class="eau-filter-select">
                        <option value="">All Status</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="pending">Pending</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
            </div>

            <!-- Registrations Table -->
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
                            <th>Status</th>
                            <th class="eau-table-actions-col">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="eau-registrations-tbody">
                        <!-- Filled by JavaScript -->
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div id="eau-pagination-container"></div>

        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renderiza os cards de estatísticas
     *
     * @since  1.29.3
     * @param  array $stats Estatísticas
     * @return string HTML dos cards
     */
    private static function render_stats_cards($stats) {
        $revenue_formatted = '$' . number_format($stats['revenue'], 2);
        ob_start();
        ?>
        <div class="eau-stats-cards">
            <div class="eau-stats-card">
                <div class="eau-stats-card-inner">
                    <div class="eau-stats-card-content">
                        <p class="eau-stats-card-label">Total</p>
                        <p class="eau-stats-card-value"><?php echo esc_html($stats['total']); ?></p>
                    </div>
                    <div class="eau-stats-card-icon eau-icon-primary">
                        <i data-lucide="users"></i>
                    </div>
                </div>
            </div>
            <div class="eau-stats-card">
                <div class="eau-stats-card-inner">
                    <div class="eau-stats-card-content">
                        <p class="eau-stats-card-label">Confirmed</p>
                        <p class="eau-stats-card-value eau-text-success"><?php echo esc_html($stats['confirmed']); ?></p>
                    </div>
                    <div class="eau-stats-card-icon eau-icon-success">
                        <i data-lucide="check-circle"></i>
                    </div>
                </div>
            </div>
            <div class="eau-stats-card">
                <div class="eau-stats-card-inner">
                    <div class="eau-stats-card-content">
                        <p class="eau-stats-card-label">Pending</p>
                        <p class="eau-stats-card-value eau-text-warning"><?php echo esc_html($stats['pending']); ?></p>
                    </div>
                    <div class="eau-stats-card-icon eau-icon-warning">
                        <i data-lucide="clock"></i>
                    </div>
                </div>
            </div>
            <div class="eau-stats-card">
                <div class="eau-stats-card-inner">
                    <div class="eau-stats-card-content">
                        <p class="eau-stats-card-label">Cancelled</p>
                        <p class="eau-stats-card-value eau-text-danger"><?php echo esc_html($stats['cancelled']); ?></p>
                    </div>
                    <div class="eau-stats-card-icon eau-icon-danger">
                        <i data-lucide="x-circle"></i>
                    </div>
                </div>
            </div>
            <div class="eau-stats-card">
                <div class="eau-stats-card-inner">
                    <div class="eau-stats-card-content">
                        <p class="eau-stats-card-label">Revenue</p>
                        <p class="eau-stats-card-value eau-text-revenue"><?php echo esc_html($revenue_formatted); ?></p>
                    </div>
                    <div class="eau-stats-card-icon eau-icon-revenue">
                        <i data-lucide="dollar-sign"></i>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Obtém estatísticas das registrations
     *
     * @since  1.29.3
     * @param  int   $event_id Event ID
     * @param  float $price    Event price
     * @return array Estatísticas
     */
    private static function get_stats($event_id, $price = 0) {
        $stats = array(
            'total'     => 0,
            'confirmed' => 0,
            'pending'   => 0,
            'cancelled' => 0,
            'revenue'   => 0,
        );

        $registrations = get_posts(array(
            'post_type'      => 'eau_event_reg',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'fields'         => 'ids',
            'meta_query'     => array(
                array(
                    'key'   => 'reg_event_id',
                    'value' => $event_id,
                    'compare' => '=',
                ),
            ),
        ));

        $stats['total'] = count($registrations);

        foreach ($registrations as $reg_id) {
            $status = get_post_meta($reg_id, 'reg_status', true) ?: 'pending';
            if (isset($stats[$status])) {
                $stats[$status]++;
            }
        }

        // Calculate revenue (confirmed registrations * price)
        $stats['revenue'] = $stats['confirmed'] * $price;

        return $stats;
    }

    /**
     * Enfileira assets da página
     *
     * @since  1.29.3
     * @return void
     */
    private static function enqueue_assets() {
        // Garante que eau-components está registrado
        if (!wp_style_is('eau-components', 'registered')) {
            wp_register_style(
                'eau-components',
                EAU_SYSTEM_PLUGIN_URL . 'assets/css/eau-components.css',
                array(),
                EAU_SYSTEM_VERSION
            );
        }
        wp_enqueue_style('eau-components');

        // CSS - Use same CSS as events management for consistency
        wp_enqueue_style(
            'eau-events-management',
            EAU_SYSTEM_PLUGIN_URL . 'includes/events/assets/css/eau-events-management.css',
            array('eau-components'),
            EAU_SYSTEM_VERSION
        );

        // Lucide Icons
        wp_enqueue_script(
            'lucide',
            'https://unpkg.com/lucide@latest/dist/umd/lucide.min.js',
            array(),
            null,
            true
        );

        // SweetAlert2
        wp_enqueue_style(
            'sweetalert2',
            'https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css',
            array(),
            '11'
        );
        wp_enqueue_script(
            'sweetalert2',
            'https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js',
            array(),
            '11',
            true
        );

        // JavaScript
        wp_enqueue_script(
            'eau-event-registrations-page',
            EAU_SYSTEM_PLUGIN_URL . 'includes/events/assets/js/eau-event-registrations-page.js',
            array('jquery', 'lucide'),
            EAU_SYSTEM_VERSION,
            true
        );

        // Localize script
        wp_localize_script('eau-event-registrations-page', 'eauEventRegistrations', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('eau_events_management_nonce'),
        ));

        // Inicializa Lucide icons
        wp_add_inline_script('lucide', 'document.addEventListener("DOMContentLoaded", function() { lucide.createIcons(); });');
    }
}
