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

use EauSystem\Components\Eau_Stats_Cards;
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
                    <a href="<?php echo esc_url(admin_url('post-new.php?post_type=eau_event')); ?>" class="eau-btn eau-btn-primary">
                        <i data-lucide="plus"></i>
                        Create Event
                    </a>
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

        // Get CPD categories
        $cpd_categories = get_terms(array(
            'taxonomy' => 'cpd_category',
            'hide_empty' => false,
        ));

        ob_start();
        ?>
        <div class="eau-modal" id="eau-event-edit-modal">
            <div class="eau-modal-overlay"></div>
            <div class="eau-modal-container eau-modal-large">
                <div class="eau-modal-header">
                    <h2 class="eau-modal-title">Edit Event</h2>
                    <button type="button" class="eau-modal-close" id="eau-modal-close">
                        <i data-lucide="x"></i>
                    </button>
                </div>
                <div class="eau-modal-body">
                    <form id="eau-event-edit-form">
                        <input type="hidden" name="event_id" id="eau-edit-event-id">

                        <!-- Tabs Navigation -->
                        <div class="eau-modal-tabs-nav">
                            <button type="button" class="eau-modal-tab-btn active" data-tab="basic-info">
                                <i data-lucide="edit-3"></i> Basic Info
                            </button>
                            <button type="button" class="eau-modal-tab-btn" data-tab="location">
                                <i data-lucide="map-pin"></i> Location
                            </button>
                            <button type="button" class="eau-modal-tab-btn" data-tab="pricing">
                                <i data-lucide="ticket"></i> Pricing
                            </button>
                            <button type="button" class="eau-modal-tab-btn" data-tab="settings">
                                <i data-lucide="settings"></i> Settings
                            </button>
                        </div>

                        <!-- Tab: Basic Info -->
                        <div class="eau-modal-tab-content active" data-tab="basic-info">
                            <div class="eau-form-grid">
                                <div class="eau-form-field eau-form-field-span-2">
                                    <label class="eau-form-label">Event Title <span class="required">*</span></label>
                                    <input type="text" name="title" id="eau-edit-title" class="eau-form-input" required>
                                </div>
                                <div class="eau-form-field eau-form-field-span-2">
                                    <label class="eau-form-label">Short Description</label>
                                    <textarea name="short_description" id="eau-edit-short_description" class="eau-form-textarea" rows="2" maxlength="500"></textarea>
                                </div>
                                <div class="eau-form-field">
                                    <label class="eau-form-label">Start Date & Time <span class="required">*</span></label>
                                    <input type="datetime-local" name="start_datetime" id="eau-edit-start_datetime" class="eau-form-input" required>
                                </div>
                                <div class="eau-form-field">
                                    <label class="eau-form-label">End Date & Time <span class="required">*</span></label>
                                    <input type="datetime-local" name="end_datetime" id="eau-edit-end_datetime" class="eau-form-input" required>
                                </div>
                                <div class="eau-form-field">
                                    <label class="eau-form-label">Timezone</label>
                                    <select name="timezone" id="eau-edit-timezone" class="eau-form-select">
                                        <?php foreach ($timezones as $key => $label) : ?>
                                            <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
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
                                                <input type="radio" name="event_type" value="<?php echo esc_attr($key); ?>">
                                                <?php echo esc_html($label); ?>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="eau-form-field">
                                    <label class="eau-form-label">Venue Name</label>
                                    <input type="text" name="venue_name" id="eau-edit-venue_name" class="eau-form-input">
                                </div>
                                <div class="eau-form-field">
                                    <label class="eau-form-label">Address</label>
                                    <input type="text" name="address" id="eau-edit-address" class="eau-form-input">
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
                                <div class="eau-form-field eau-form-field-span-2">
                                    <label class="eau-form-label">Virtual URL (for virtual/hybrid events)</label>
                                    <input type="url" name="virtual_url" id="eau-edit-virtual_url" class="eau-form-input" placeholder="https://">
                                </div>
                            </div>
                        </div>

                        <!-- Tab: Pricing -->
                        <div class="eau-modal-tab-content" data-tab="pricing">
                            <div class="eau-form-grid">
                                <div class="eau-form-field">
                                    <label class="eau-form-label">Capacity</label>
                                    <input type="number" name="capacity" id="eau-edit-capacity" class="eau-form-input" min="0" placeholder="0 = unlimited">
                                </div>
                                <div class="eau-form-field">
                                    <label class="eau-form-label">Member Price ($)</label>
                                    <input type="number" name="member_price" id="eau-edit-member_price" class="eau-form-input" min="0" step="0.01">
                                </div>
                                <div class="eau-form-field">
                                    <label class="eau-form-label">Non-Member Price ($)</label>
                                    <input type="number" name="non_member_price" id="eau-edit-non_member_price" class="eau-form-input" min="0" step="0.01">
                                </div>
                                <div class="eau-form-field">
                                    <label class="eau-form-label">Early Bird Price ($)</label>
                                    <input type="number" name="early_bird_price" id="eau-edit-early_bird_price" class="eau-form-input" min="0" step="0.01">
                                </div>
                                <div class="eau-form-field">
                                    <label class="eau-form-label">Early Bird End Date</label>
                                    <input type="datetime-local" name="early_bird_end_date" id="eau-edit-early_bird_end_date" class="eau-form-input">
                                </div>
                                <div class="eau-form-field">
                                    <label class="eau-form-label">Max Guests</label>
                                    <input type="number" name="max_guests" id="eau-edit-max_guests" class="eau-form-input" min="0" max="10">
                                </div>
                                <div class="eau-form-field eau-form-field-span-2">
                                    <label class="eau-checkbox-label">
                                        <input type="checkbox" name="allow_guests" id="eau-edit-allow_guests" value="1">
                                        Allow attendees to bring guests
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Tab: Settings -->
                        <div class="eau-modal-tab-content" data-tab="settings">
                            <div class="eau-form-grid">
                                <div class="eau-form-field">
                                    <label class="eau-form-label">CPD Points</label>
                                    <input type="number" name="cpd_points" id="eau-edit-cpd_points" class="eau-form-input" min="0" step="0.5">
                                </div>
                                <div class="eau-form-field">
                                    <label class="eau-form-label">CPD Category</label>
                                    <select name="cpd_category" id="eau-edit-cpd_category" class="eau-form-select">
                                        <option value="">Select Category</option>
                                        <?php if (!is_wp_error($cpd_categories)) : ?>
                                            <?php foreach ($cpd_categories as $cat) : ?>
                                                <option value="<?php echo esc_attr($cat->term_id); ?>"><?php echo esc_html($cat->name); ?></option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                <div class="eau-form-field">
                                    <label class="eau-form-label">Visibility</label>
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
                                <div class="eau-form-field eau-form-field-span-2">
                                    <label class="eau-checkbox-label">
                                        <input type="checkbox" name="members_only" id="eau-edit-members_only" value="1">
                                        Members only event
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
        $cards_data = array(
            array(
                'title' => 'Total Events',
                'number' => $stats['total'],
                'icon' => 'calendar',
                'color' => 'blue',
            ),
            array(
                'title' => 'Published',
                'number' => $stats['published'],
                'icon' => 'eye',
                'color' => 'green',
            ),
            array(
                'title' => 'Draft',
                'number' => $stats['draft'],
                'icon' => 'file-edit',
                'color' => 'gray',
            ),
            array(
                'title' => 'Upcoming',
                'number' => $stats['upcoming'],
                'icon' => 'calendar-check',
                'color' => 'purple',
            ),
        );

        $stats_cards = new Eau_Stats_Cards($cards_data);
        return $stats_cards->render();
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
        // CSS
        wp_enqueue_style(
            'eau-events-management',
            EAU_SYSTEM_PLUGIN_URL . 'includes/events/assets/css/eau-events-management.css',
            array('eau-components'),
            EAU_SYSTEM_VERSION
        );

        // JavaScript
        wp_enqueue_script(
            'eau-events-management',
            EAU_SYSTEM_PLUGIN_URL . 'includes/events/assets/js/eau-events-management.js',
            array('jquery'),
            EAU_SYSTEM_VERSION,
            true
        );

        // Localize script
        wp_localize_script('eau-events-management', 'eauEventsManagement', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('eau_events_management_nonce'),
            'editUrl' => admin_url('post.php?post={id}&action=edit'),
            'viewUrl' => home_url('/events/{slug}/'),
        ));
    }
}
