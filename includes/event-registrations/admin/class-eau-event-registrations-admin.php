<?php
/**
 * Event Registrations Admin Page
 *
 * Página de administração para gerenciar registros de eventos.
 *
 * @package    EauSystem
 * @subpackage Events\Registrations\Admin
 * @since      1.29.0
 */

namespace EauSystem\EventRegistrations\Admin;

use EauSystem\EventRegistrations\Config;

if (!defined('WPINC')) {
    die;
}

/**
 * Class Eau_Event_Registrations_Admin
 *
 * Página de administração com stats cards, filtros e listagem.
 *
 * @since 1.29.0
 */
class Eau_Event_Registrations_Admin {

    /**
     * Instância singleton
     *
     * @var Eau_Event_Registrations_Admin|null
     */
    private static $instance = null;

    /**
     * Retorna a instância singleton
     *
     * @since  1.29.0
     * @return Eau_Event_Registrations_Admin
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     *
     * @since 1.29.0
     */
    private function __construct() {
        add_action('admin_menu', array($this, 'add_submenu_page'), 20);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    /**
     * Adiciona submenu page
     *
     * @since  1.29.0
     * @return void
     */
    public function add_submenu_page() {
        add_submenu_page(
            'edit.php?post_type=eau_event',
            __('Event Registrations', 'eau-system'),
            __('Registrations', 'eau-system'),
            'manage_options',
            'eau-event-registrations',
            array($this, 'render_page')
        );
    }

    /**
     * Enfileira assets
     *
     * @since  1.29.0
     * @param  string $hook Hook da página
     * @return void
     */
    public function enqueue_assets($hook) {
        if ($hook !== 'eau_event_page_eau-event-registrations') {
            return;
        }

        // Components CSS
        wp_enqueue_style(
            'eau-components',
            EAU_SYSTEM_PLUGIN_URL . 'assets/css/eau-components.css',
            array(),
            EAU_SYSTEM_VERSION
        );

        // Page specific CSS
        wp_enqueue_style(
            'eau-event-registrations-admin',
            EAU_SYSTEM_PLUGIN_URL . 'includes/event-registrations/assets/css/eau-event-registrations-admin.css',
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

        // Page specific JS
        wp_enqueue_script(
            'eau-event-registrations-admin',
            EAU_SYSTEM_PLUGIN_URL . 'includes/event-registrations/assets/js/eau-event-registrations-admin.js',
            array('jquery', 'eau-notifications'),
            EAU_SYSTEM_VERSION,
            true
        );

        wp_localize_script('eau-event-registrations-admin', 'eauEventRegAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('eau_event_reg_nonce'),
            'i18n'    => array(
                'confirmDelete'  => __('Are you sure you want to delete this registration?', 'eau-system'),
                'deleteSuccess'  => __('Registration deleted successfully.', 'eau-system'),
                'deleteError'    => __('Error deleting registration.', 'eau-system'),
                'statusUpdated'  => __('Status updated successfully.', 'eau-system'),
                'statusError'    => __('Error updating status.', 'eau-system'),
            ),
        ));
    }

    /**
     * Renderiza a página
     *
     * @since  1.29.0
     * @return void
     */
    public function render_page() {
        $stats = $this->get_stats();
        $events = $this->get_events_for_filter();
        $current_filter = isset($_GET['status_filter']) ? sanitize_key($_GET['status_filter']) : '';
        $event_filter = isset($_GET['event_filter']) ? absint($_GET['event_filter']) : 0;
        ?>
        <div class="wrap eau-registrations-wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('Event Registrations', 'eau-system'); ?></h1>
            <a href="<?php echo admin_url('post-new.php?post_type=' . Config\POST_TYPE); ?>" class="page-title-action">
                <?php esc_html_e('Add New', 'eau-system'); ?>
            </a>

            <!-- Stats Cards -->
            <div class="eau-admin-stats-cards" id="eau-stats-cards">
                <div class="eau-admin-stat-card <?php echo $current_filter === '' ? 'active' : ''; ?>" data-filter="">
                    <div class="eau-admin-stat-icon eau-admin-stat-icon-primary">
                        <span class="dashicons dashicons-groups"></span>
                    </div>
                    <div class="eau-admin-stat-content">
                        <span class="eau-admin-stat-value"><?php echo esc_html($stats['total']); ?></span>
                        <span class="eau-admin-stat-label"><?php esc_html_e('Total', 'eau-system'); ?></span>
                    </div>
                </div>
                <div class="eau-admin-stat-card <?php echo $current_filter === 'confirmed' ? 'active' : ''; ?>" data-filter="confirmed">
                    <div class="eau-admin-stat-icon eau-admin-stat-icon-success">
                        <span class="dashicons dashicons-yes-alt"></span>
                    </div>
                    <div class="eau-admin-stat-content">
                        <span class="eau-admin-stat-value"><?php echo esc_html($stats['confirmed']); ?></span>
                        <span class="eau-admin-stat-label"><?php esc_html_e('Confirmed', 'eau-system'); ?></span>
                    </div>
                </div>
                <div class="eau-admin-stat-card <?php echo $current_filter === 'pending' ? 'active' : ''; ?>" data-filter="pending">
                    <div class="eau-admin-stat-icon eau-admin-stat-icon-warning">
                        <span class="dashicons dashicons-clock"></span>
                    </div>
                    <div class="eau-admin-stat-content">
                        <span class="eau-admin-stat-value"><?php echo esc_html($stats['pending']); ?></span>
                        <span class="eau-admin-stat-label"><?php esc_html_e('Pending', 'eau-system'); ?></span>
                    </div>
                </div>
                <div class="eau-admin-stat-card <?php echo $current_filter === 'cancelled' ? 'active' : ''; ?>" data-filter="cancelled">
                    <div class="eau-admin-stat-icon eau-admin-stat-icon-danger">
                        <span class="dashicons dashicons-dismiss"></span>
                    </div>
                    <div class="eau-admin-stat-content">
                        <span class="eau-admin-stat-value"><?php echo esc_html($stats['cancelled']); ?></span>
                        <span class="eau-admin-stat-label"><?php esc_html_e('Cancelled', 'eau-system'); ?></span>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="eau-admin-filters">
                <div class="eau-admin-search-box">
                    <input type="text" id="eau-registration-search" placeholder="<?php esc_attr_e('Search by name or email...', 'eau-system'); ?>">
                </div>
                <div class="eau-admin-filter-group">
                    <select id="eau-event-filter">
                        <option value=""><?php esc_html_e('All Events', 'eau-system'); ?></option>
                        <?php foreach ($events as $event) : ?>
                            <option value="<?php echo esc_attr($event->ID); ?>" <?php selected($event_filter, $event->ID); ?>>
                                <?php echo esc_html($event->post_title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select id="eau-status-filter">
                        <option value=""><?php esc_html_e('All Status', 'eau-system'); ?></option>
                        <?php foreach (Config\get_status_options() as $key => $label) : ?>
                            <option value="<?php echo esc_attr($key); ?>" <?php selected($current_filter, $key); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Table Container -->
            <div class="eau-admin-table-wrapper" id="eau-registrations-table">
                <!-- Table will be loaded via AJAX -->
                <div class="eau-loading-skeleton">
                    <div class="eau-skeleton-row"></div>
                    <div class="eau-skeleton-row"></div>
                    <div class="eau-skeleton-row"></div>
                    <div class="eau-skeleton-row"></div>
                    <div class="eau-skeleton-row"></div>
                </div>
            </div>

            <!-- Pagination -->
            <div class="eau-admin-pagination" id="eau-pagination">
                <!-- Pagination will be loaded via AJAX -->
            </div>
        </div>
        <?php
    }

    /**
     * Calcula estatísticas
     *
     * @since  1.29.0
     * @return array
     */
    private function get_stats() {
        global $wpdb;

        $stats = array(
            'total'     => 0,
            'confirmed' => 0,
            'pending'   => 0,
            'cancelled' => 0,
        );

        // Count all registrations
        $stats['total'] = wp_count_posts(Config\POST_TYPE)->publish ?? 0;

        // Count by status
        $prefix = Config\META_PREFIX;
        $status_counts = $wpdb->get_results($wpdb->prepare(
            "SELECT pm.meta_value as status, COUNT(*) as count
             FROM {$wpdb->posts} p
             JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_type = %s
             AND p.post_status = 'publish'
             AND pm.meta_key = %s
             GROUP BY pm.meta_value",
            Config\POST_TYPE,
            $prefix . 'status'
        ));

        foreach ($status_counts as $row) {
            if (isset($stats[$row->status])) {
                $stats[$row->status] = (int) $row->count;
            }
        }

        // Total might not match sum if some posts don't have status meta
        $stats['total'] = max($stats['total'], $stats['confirmed'] + $stats['pending'] + $stats['cancelled']);

        return $stats;
    }

    /**
     * Retorna lista de eventos para o filtro
     *
     * @since  1.29.0
     * @return array
     */
    private function get_events_for_filter() {
        return get_posts(array(
            'post_type'      => 'eau_event',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ));
    }
}
