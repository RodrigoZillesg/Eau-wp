<?php
/**
 * Events Management Page (Frontend Dashboard)
 *
 * Shortcode: [eau_events_management]
 *
 * @package    EauSystem
 * @subpackage Events\Admin
 * @since      1.28.1
 */

namespace EauSystem\Events\Admin;

use EauSystem\Components\Eau_Access_Denied;
use EauSystem\Events\Config;

if (!defined('WPINC')) {
    die;
}

/**
 * Class Eau_Events_Management
 *
 * Renderiza página de gerenciamento de eventos no dashboard.
 *
 * @since 1.28.1
 */
class Eau_Events_Management {

    /**
     * Registra o shortcode
     *
     * @since  1.28.1
     * @return void
     */
    public static function register_shortcode() {
        add_shortcode('eau_events_management', array(__CLASS__, 'render'));
    }

    /**
     * Renderiza a página de Events Management
     *
     * @since  1.28.1
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

        // Carrega assets
        self::enqueue_assets();

        // Pega estatísticas
        $stats = self::get_stats();

        ob_start();
        ?>
        <div class="eau-events-management-container">

            <!-- Page Header -->
            <div class="eau-page-header">
                <div class="eau-page-header-title">
                    <h1>Event Management</h1>
                    <p class="eau-page-header-subtitle">Create and manage events</p>
                </div>
                <div class="eau-page-header-actions">
                    <button type="button" class="eau-btn eau-btn-primary" id="eau-create-event-btn">
                        <i data-lucide="plus"></i>
                        Create Event
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
                        placeholder="Search events..."
                        id="eau-events-search"
                    >
                </div>
                <div class="eau-filter-select-wrapper">
                    <select id="eau-events-status-filter" class="eau-filter-select">
                        <option value="">All Status</option>
                        <option value="publish">Published</option>
                        <option value="draft">Draft</option>
                    </select>
                </div>
            </div>

            <!-- Events Table -->
            <div class="eau-data-table-wrapper">
                <table class="eau-data-table" id="eau-events-table">
                    <thead>
                        <tr>
                            <th class="eau-sortable" data-sort="title">
                                Event
                                <i data-lucide="chevrons-up-down"></i>
                            </th>
                            <th class="eau-sortable" data-sort="start_datetime">
                                Date
                                <i data-lucide="chevrons-up-down"></i>
                            </th>
                            <th>Location</th>
                            <th class="eau-sortable" data-sort="capacity">
                                Capacity
                                <i data-lucide="chevrons-up-down"></i>
                            </th>
                            <th>Status</th>
                            <th class="eau-table-actions-col">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="eau-events-tbody">
                        <!-- Filled by JavaScript -->
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div id="eau-pagination-container"></div>

            <!-- Edit Modal -->
            <?php echo self::render_edit_modal(); ?>

        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renderiza o modal de edição
     *
     * @since  1.28.1
     * @return string HTML do modal
     */
    private static function render_edit_modal() {
        // Get options for selects
        $timezones = Config\get_timezones();
        $countries = Config\get_countries();
        $event_types = Config\get_event_types();
        $visibility_options = Config\get_visibility_options();

        // Get CPD categories from eau_activity_categories table
        $cpd_categories = self::get_cpd_categories();

        ob_start();
        ?>
        <div class="eau-modal" id="eau-event-edit-modal">
            <div class="eau-modal-overlay"></div>
            <div class="eau-modal-container eau-modal-large">
                <div class="eau-modal-header">
                    <h2 class="eau-modal-title" id="eau-modal-title">Edit Event</h2>
                    <button type="button" class="eau-modal-close" id="eau-modal-close">
                        <i data-lucide="x"></i>
                    </button>
                </div>
                <div class="eau-modal-body">
                    <form id="eau-event-edit-form">
                        <input type="hidden" name="event_id" id="eau-edit-event-id">
                        <input type="hidden" name="mode" id="eau-edit-mode" value="edit">

                        <!-- Tabs Navigation -->
                        <div class="eau-modal-tabs-nav">
                            <button type="button" class="eau-modal-tab-btn active" data-tab="basic-info">
                                <i data-lucide="file-text"></i> Basic Info
                            </button>
                            <button type="button" class="eau-modal-tab-btn" data-tab="location">
                                <i data-lucide="map-pin"></i> Location
                            </button>
                            <button type="button" class="eau-modal-tab-btn" data-tab="pricing">
                                <i data-lucide="ticket"></i> Capacity & Pricing
                            </button>
                            <button type="button" class="eau-modal-tab-btn" data-tab="settings">
                                <i data-lucide="settings"></i> CPD & Settings
                            </button>
                        </div>

                        <!-- Tab: Basic Info -->
                        <div class="eau-modal-tab-content active" data-tab="basic-info">
                            <div class="eau-form-grid">
                                <div class="eau-form-field eau-form-field-span-2">
                                    <label class="eau-form-label">Event Title <span class="required">*</span></label>
                                    <input type="text" name="title" id="eau-edit-title" class="eau-form-input" placeholder="Enter event title" required>
                                </div>
                                <div class="eau-form-field eau-form-field-span-2">
                                    <label class="eau-form-label">Short Description</label>
                                    <textarea name="short_description" id="eau-edit-short_description" class="eau-form-textarea" rows="2" maxlength="500" placeholder="Brief description for event cards (max 500 characters)"></textarea>
                                </div>
                                <div class="eau-form-field">
                                    <label class="eau-form-label">Start Date & Time <span class="required">*</span></label>
                                    <input type="datetime-local" name="start_datetime" id="eau-edit-start_datetime" class="eau-form-input" required>
                                </div>
                                <div class="eau-form-field">
                                    <label class="eau-form-label">End Date & Time <span class="required">*</span></label>
                                    <input type="datetime-local" name="end_datetime" id="eau-edit-end_datetime" class="eau-form-input" required>
                                </div>
                                <div class="eau-form-field eau-form-field-span-2">
                                    <label class="eau-form-label">Timezone</label>
                                    <select name="timezone" id="eau-edit-timezone" class="eau-form-select">
                                        <?php foreach ($timezones as $key => $label) : ?>
                                            <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="eau-form-field eau-form-field-span-2">
                                    <label class="eau-form-label">Event Image</label>
                                    <div class="eau-image-upload-wrapper">
                                        <div class="eau-image-preview" id="eau-image-preview">
                                            <div class="eau-image-placeholder" id="eau-image-placeholder">
                                                <span class="dashicons dashicons-format-image"></span>
                                                <p><?php _e('Click to upload', 'eau-system'); ?></p>
                                            </div>
                                        </div>
                                        <input type="hidden" name="image_id" id="eau-edit-image_id" value="">
                                        <div class="eau-image-actions">
                                            <button type="button" class="button eau-upload-image-btn" id="eau-select-image"><?php _e('Choose', 'eau-system'); ?></button>
                                            <button type="button" class="button eau-remove-image-btn" id="eau-remove-image" style="display: none;"><?php _e('Remove', 'eau-system'); ?></button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tab: Location -->
                        <div class="eau-modal-tab-content" data-tab="location">
                            <div class="eau-form-grid">
                                <div class="eau-form-field eau-form-field-span-2">
                                    <label class="eau-form-label">Event Type</label>
                                    <div class="eau-radio-group">
                                        <?php foreach ($event_types as $key => $label) : ?>
                                            <label class="eau-radio-label">
                                                <input type="radio" name="event_type" value="<?php echo esc_attr($key); ?>" id="eau-event-type-<?php echo esc_attr($key); ?>">
                                                <?php echo esc_html($label); ?>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <!-- Virtual Event Fields -->
                                <div class="eau-location-fields eau-location-virtual" style="display: none;">
                                    <div class="eau-form-grid">
                                        <div class="eau-form-field eau-form-field-span-2">
                                            <label class="eau-form-label">Virtual Meeting URL <span class="required">*</span></label>
                                            <input type="url" name="virtual_url" id="eau-edit-virtual_url" class="eau-form-input" placeholder="https://zoom.us/j/...">
                                        </div>
                                    </div>
                                </div>

                                <!-- In-Person Event Fields -->
                                <div class="eau-location-fields eau-location-in-person">
                                    <div class="eau-form-grid">
                                        <div class="eau-form-field eau-form-field-span-2">
                                            <label class="eau-form-label">Venue Name</label>
                                            <input type="text" name="venue_name" id="eau-edit-venue_name" class="eau-form-input" placeholder="e.g., Sydney Convention Centre">
                                        </div>
                                        <div class="eau-form-field eau-form-field-span-2">
                                            <label class="eau-form-label">Address</label>
                                            <input type="text" name="address" id="eau-edit-address" class="eau-form-input" placeholder="Street address">
                                        </div>
                                        <div class="eau-form-field">
                                            <label class="eau-form-label">City</label>
                                            <input type="text" name="city" id="eau-edit-city" class="eau-form-input">
                                        </div>
                                        <div class="eau-form-field">
                                            <label class="eau-form-label">State</label>
                                            <input type="text" name="state" id="eau-edit-state" class="eau-form-input">
                                        </div>
                                        <div class="eau-form-field">
                                            <label class="eau-form-label">Postal Code</label>
                                            <input type="text" name="postal_code" id="eau-edit-postal_code" class="eau-form-input">
                                        </div>
                                        <div class="eau-form-field">
                                            <label class="eau-form-label">Country</label>
                                            <select name="country" id="eau-edit-country" class="eau-form-select">
                                                <?php foreach ($countries as $key => $label) : ?>
                                                    <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tab: Capacity & Pricing -->
                        <div class="eau-modal-tab-content" data-tab="pricing">
                            <div class="eau-form-grid">
                                <div class="eau-form-field eau-form-field-span-2">
                                    <label class="eau-form-label">Event Capacity</label>
                                    <input type="number" name="capacity" id="eau-edit-capacity" class="eau-form-input" min="0" placeholder="50">
                                    <p class="eau-form-hint">Leave empty for unlimited</p>
                                </div>
                                <div class="eau-form-field eau-form-field-span-2">
                                    <label class="eau-form-label">Price ($)</label>
                                    <input type="number" name="member_price" id="eau-edit-member_price" class="eau-form-input" min="0" step="0.01" placeholder="0">
                                    <p class="eau-form-hint">Leave 0 for free events</p>
                                </div>

                                <div class="eau-form-field eau-form-field-span-2">
                                    <p class="eau-form-section-title">Early Bird Pricing (Optional)</p>
                                </div>
                                <div class="eau-form-field">
                                    <label class="eau-form-label">Early Bird Price ($)</label>
                                    <input type="number" name="early_bird_price" id="eau-edit-early_bird_price" class="eau-form-input" min="0" step="0.01">
                                </div>
                                <div class="eau-form-field">
                                    <label class="eau-form-label">Early Bird End Date</label>
                                    <input type="datetime-local" name="early_bird_end_date" id="eau-edit-early_bird_end_date" class="eau-form-input">
                                </div>
                            </div>
                        </div>

                        <!-- Tab: CPD & Settings -->
                        <div class="eau-modal-tab-content" data-tab="settings">
                            <div class="eau-form-grid">
                                <div class="eau-form-field eau-form-field-span-2">
                                    <p class="eau-form-section-title">CPD Settings</p>
                                </div>
                                <div class="eau-form-field">
                                    <label class="eau-form-label">CPD Points</label>
                                    <input type="number" name="cpd_points" id="eau-edit-cpd_points" class="eau-form-input" min="0" step="0.5" placeholder="1">
                                </div>
                                <div class="eau-form-field">
                                    <label class="eau-form-label">CPD Category</label>
                                    <select name="cpd_category" id="eau-edit-cpd_category" class="eau-form-select">
                                        <option value="">Select CPD category</option>
                                        <?php foreach ($cpd_categories as $cat) : ?>
                                            <option value="<?php echo esc_attr($cat['id']); ?>" data-points="<?php echo esc_attr($cat['points_per_hour']); ?>">
                                                <?php echo esc_html($cat['category_name']); ?> (<?php echo esc_html($cat['points_per_hour']); ?> pts/hr)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="eau-form-field eau-form-field-span-2">
                                    <p class="eau-form-section-title">Visibility Settings</p>
                                </div>
                                <div class="eau-form-field eau-form-field-span-2">
                                    <label class="eau-form-label">Event Visibility</label>
                                    <select name="visibility" id="eau-edit-visibility" class="eau-form-select">
                                        <?php foreach ($visibility_options as $key => $label) : ?>
                                            <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="eau-form-field eau-form-field-span-2">
                                    <label class="eau-checkbox-label">
                                        <input type="checkbox" name="require_approval" id="eau-edit-require_approval" value="1">
                                        Require approval for registrations
                                    </label>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="eau-modal-footer">
                    <button type="button" class="eau-btn eau-btn-secondary" id="eau-modal-cancel">Cancel</button>
                    <button type="button" class="eau-btn eau-btn-primary" id="eau-modal-save">
                        <i data-lucide="save"></i> Save Changes
                    </button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renderiza os cards de estatísticas
     *
     * @since  1.28.1
     * @param  array $stats Estatísticas dos eventos
     * @return string HTML dos cards
     */
    private static function render_stats_cards($stats) {
        ob_start();
        ?>
        <div class="eau-stats-cards">
            <div class="eau-stats-card">
                <div class="eau-stats-card-inner">
                    <div class="eau-stats-card-content">
                        <p class="eau-stats-card-label">Total Events</p>
                        <p class="eau-stats-card-value"><?php echo esc_html($stats['total']); ?></p>
                    </div>
                    <div class="eau-stats-card-icon">
                        <i data-lucide="calendar"></i>
                    </div>
                </div>
            </div>
            <div class="eau-stats-card">
                <div class="eau-stats-card-inner">
                    <div class="eau-stats-card-content">
                        <p class="eau-stats-card-label">Published</p>
                        <p class="eau-stats-card-value"><?php echo esc_html($stats['published']); ?></p>
                    </div>
                    <div class="eau-stats-card-icon">
                        <i data-lucide="eye"></i>
                    </div>
                </div>
            </div>
            <div class="eau-stats-card">
                <div class="eau-stats-card-inner">
                    <div class="eau-stats-card-content">
                        <p class="eau-stats-card-label">Draft</p>
                        <p class="eau-stats-card-value"><?php echo esc_html($stats['draft']); ?></p>
                    </div>
                    <div class="eau-stats-card-icon">
                        <i data-lucide="square-pen"></i>
                    </div>
                </div>
            </div>
            <div class="eau-stats-card">
                <div class="eau-stats-card-inner">
                    <div class="eau-stats-card-content">
                        <p class="eau-stats-card-label">Upcoming</p>
                        <p class="eau-stats-card-value"><?php echo esc_html($stats['upcoming']); ?></p>
                    </div>
                    <div class="eau-stats-card-icon">
                        <i data-lucide="calendar"></i>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Obtém estatísticas dos eventos
     *
     * @since  1.28.1
     * @return array Estatísticas
     */
    private static function get_stats() {
        $post_type = Config\POST_TYPE;
        $current_date = current_time('Y-m-d\TH:i');

        // Total
        $total = wp_count_posts($post_type);

        // Upcoming
        $upcoming_query = new \WP_Query(array(
            'post_type' => $post_type,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => array(
                array(
                    'key' => 'evt_start_datetime',
                    'value' => $current_date,
                    'compare' => '>=',
                    'type' => 'DATETIME',
                ),
            ),
        ));

        return array(
            'total' => isset($total->publish) ? $total->publish + (isset($total->draft) ? $total->draft : 0) : 0,
            'published' => isset($total->publish) ? $total->publish : 0,
            'draft' => isset($total->draft) ? $total->draft : 0,
            'upcoming' => $upcoming_query->found_posts,
        );
    }

    /**
     * Enfileira assets da página
     *
     * @since  1.28.1
     * @return void
     */
    private static function enqueue_assets() {
        // WordPress Media Library
        wp_enqueue_media();

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

        // CSS
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
            'eau-events-management',
            EAU_SYSTEM_PLUGIN_URL . 'includes/events/assets/js/eau-events-management.js',
            array('jquery', 'lucide'),
            EAU_SYSTEM_VERSION,
            true
        );

        // Localize script
        wp_localize_script('eau-events-management', 'eauEventsManagement', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('eau_events_management_nonce'),
            'editUrl' => admin_url('post.php?post={id}&action=edit'),
            'viewUrl' => home_url('/events/{slug}/'),
            'registrationsUrl' => home_url('/dashboard/events/{slug}/registrations/'),
        ));

        // Inicializa Lucide icons
        wp_add_inline_script('lucide', 'document.addEventListener("DOMContentLoaded", function() { lucide.createIcons(); });');
    }

    /**
     * Obtém categorias CPD da tabela eau_activity_categories
     *
     * @since  1.28.1
     * @return array Lista de categorias
     */
    private static function get_cpd_categories() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'eau_activity_categories';

        // Verifica se a tabela existe
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        if (!$table_exists) {
            return array();
        }

        $results = $wpdb->get_results(
            "SELECT id, category_serial, category_name, points_per_hour
             FROM $table_name
             ORDER BY category_name ASC",
            ARRAY_A
        );

        return $results ? $results : array();
    }
}
