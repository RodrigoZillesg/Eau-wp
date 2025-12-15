<?php
/**
 * Events Shortcodes
 *
 * Shortcodes para renderizar single e archive de eventos
 *
 * @package    EauSystem
 * @subpackage Events\Frontend
 * @since      1.48.2
 */

namespace EauSystem\Events\Frontend;

use EauSystem\Events\Frontend\Eau_Events_Helper as Helper;
use EauSystem\EventRegistrations\Frontend\Eau_Event_Registrations_Ajax;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Eau_Events_Shortcodes
 *
 * Registra e renderiza shortcodes de eventos
 *
 * @since 1.48.2
 */
class Eau_Events_Shortcodes {

    /**
     * Registra os shortcodes
     *
     * @since  1.48.2
     * @return void
     */
    public static function register() {
        add_shortcode('eau_event_single', array(__CLASS__, 'render_single'));
        add_shortcode('eau_events_archive', array(__CLASS__, 'render_archive'));
    }

    /**
     * Enfileira assets necessários para os shortcodes
     *
     * @since  1.48.2
     * @return void
     */
    private static function enqueue_assets() {
        // CSS
        wp_enqueue_style(
            'eau-events-frontend',
            EAU_SYSTEM_PLUGIN_URL . 'includes/events/assets/css/eau-events-frontend.css',
            array(),
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

        // JS
        wp_enqueue_script(
            'eau-events-frontend',
            EAU_SYSTEM_PLUGIN_URL . 'includes/events/assets/js/eau-events-frontend.js',
            array('jquery', 'lucide'),
            EAU_SYSTEM_VERSION,
            true
        );

        // Localize
        wp_localize_script('eau-events-frontend', 'eauEventsFrontend', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('eau_event_registration'),
        ));

        // Inline script para inicializar Lucide
        wp_add_inline_script('lucide', 'document.addEventListener("DOMContentLoaded", function() { if(typeof lucide !== "undefined") lucide.createIcons(); });');
    }

    /**
     * Shortcode: [eau_event_single]
     *
     * Renderiza a página de um evento individual
     *
     * Atributos:
     * - id: ID do evento (se não fornecido, tenta pegar do contexto atual)
     *
     * @since  1.48.2
     * @param  array $atts Atributos do shortcode
     * @return string HTML renderizado
     */
    public static function render_single($atts) {
        $atts = shortcode_atts(array(
            'id' => 0,
        ), $atts, 'eau_event_single');

        // Tenta pegar o ID do evento
        $event_id = absint($atts['id']);

        // Se não foi passado ID, tenta pegar do contexto atual (query var ou post atual)
        if (!$event_id) {
            global $post;
            if ($post && $post->post_type === 'eau_event') {
                $event_id = $post->ID;
            }
        }

        // Se ainda não tem ID, verifica se tem query var
        if (!$event_id && get_query_var('eau_event_id')) {
            $event_id = absint(get_query_var('eau_event_id'));
        }

        if (!$event_id) {
            return '<div class="eau-event-error">' . __('Event not found. Please provide a valid event ID.', 'eau-system') . '</div>';
        }

        // Verifica se o post existe e é do tipo correto
        $event_post = get_post($event_id);
        if (!$event_post || $event_post->post_type !== 'eau_event') {
            return '<div class="eau-event-error">' . __('Event not found.', 'eau-system') . '</div>';
        }

        // Enfileira assets
        self::enqueue_assets();

        // Renderiza
        ob_start();
        self::render_single_content($event_id);
        return ob_get_clean();
    }

    /**
     * Renderiza o conteúdo da single de evento
     *
     * @since  1.48.2
     * @param  int $event_id ID do evento
     * @return void
     */
    private static function render_single_content($event_id) {
        $data = Helper::get_event_data($event_id);
        $meta = $data['meta'];

        // Date formatting
        $date_display = Helper::format_date($data['start_obj'], 'l, F j, Y');
        $time_display = Helper::format_time($data['start_obj']);
        if ($data['end_obj']) $time_display .= ' - ' . Helper::format_time($data['end_obj']);
        $full_date = Helper::format_date($data['start_obj'], 'l j F Y \a\t h:i a');
        $iso_date = $data['start_obj'] ? $data['start_obj']->format('c') : '';

        // Registration check
        $is_registered = Eau_Event_Registrations_Ajax::is_user_registered($event_id);
        $current_registrations = Eau_Event_Registrations_Ajax::count_registrations($event_id);
        $capacity = intval($meta['capacity']);
        $spots_left = $capacity > 0 ? max(0, $capacity - $current_registrations) : null;

        // User data for pre-fill
        $user_name = '';
        $user_email = '';
        if (is_user_logged_in()) {
            $current_user = wp_get_current_user();
            $user_name = $current_user->display_name;
            $user_email = $current_user->user_email;
        }
        ?>

        <div class="eau-event-single">
            <div class="eau-event-container">
                <!-- Back Link -->
                <a href="<?php echo esc_url(get_post_type_archive_link('eau_event')); ?>" class="eau-event-back-link">
                    <?php echo Helper::icon('chevron-left', 16); ?>
                    <?php _e('Back to Events', 'eau-system'); ?>
                </a>

                <div class="eau-event-layout">
                    <!-- Main Content -->
                    <div class="eau-event-main">
                        <?php if ($data['thumbnail']) : ?>
                            <div class="eau-event-image">
                                <img src="<?php echo esc_url($data['thumbnail']); ?>" alt="<?php echo esc_attr($data['title']); ?>">
                            </div>
                        <?php endif; ?>

                        <h1 class="eau-event-title"><?php echo esc_html($data['title']); ?></h1>

                        <!-- Date & Location Info -->
                        <div class="eau-event-info-grid">
                            <div class="eau-event-info-item">
                                <div class="eau-event-info-icon"><?php echo Helper::icon('calendar', 20); ?></div>
                                <div class="eau-event-info-content">
                                    <span class="eau-event-info-label"><?php _e('Date & Time', 'eau-system'); ?></span>
                                    <span class="eau-event-info-value"><?php echo esc_html($date_display); ?></span>
                                    <span class="eau-event-info-subvalue"><?php echo esc_html($time_display); ?></span>
                                </div>
                            </div>
                            <div class="eau-event-info-item">
                                <div class="eau-event-info-icon"><?php echo Helper::icon('map-pin', 20); ?></div>
                                <div class="eau-event-info-content">
                                    <span class="eau-event-info-label"><?php _e('Location', 'eau-system'); ?></span>
                                    <span class="eau-event-info-value"><?php echo esc_html($data['location']['full']); ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- About Section -->
                        <div class="eau-event-section">
                            <h2 class="eau-event-section-title"><?php _e('About This Event', 'eau-system'); ?></h2>
                            <div class="eau-event-description">
                                <?php if ($meta['full_description']) : ?>
                                    <?php echo wp_kses_post($meta['full_description']); ?>
                                <?php elseif ($meta['short_description']) : ?>
                                    <p><?php echo esc_html($meta['short_description']); ?></p>
                                <?php else : ?>
                                    <p class="eau-event-no-description"><?php _e('No description available', 'eau-system'); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- CPD Points Section -->
                        <?php if ($meta['cpd_points'] && floatval($meta['cpd_points']) > 0) :
                            // Get CPD category name from eau_activity_categories table
                            $cpd_category_name = '';
                            if (!empty($meta['cpd_category'])) {
                                global $wpdb;
                                $table_name = $wpdb->prefix . 'eau_activity_categories';
                                $cpd_category_name = $wpdb->get_var($wpdb->prepare(
                                    "SELECT category_name FROM $table_name WHERE id = %d",
                                    intval($meta['cpd_category'])
                                ));
                            }
                        ?>
                            <div class="eau-event-section eau-event-cpd-section">
                                <div class="eau-event-cpd-box">
                                    <div class="eau-event-cpd-icon"><?php echo Helper::icon('graduation', 24); ?></div>
                                    <div class="eau-event-cpd-content">
                                        <span class="eau-event-cpd-title"><?php _e('CPD Points', 'eau-system'); ?></span>
                                        <span class="eau-event-cpd-text">
                                            <?php printf(__('Earn %s CPD points by attending this event.', 'eau-system'), '<strong>' . esc_html($meta['cpd_points']) . '</strong>'); ?>
                                        </span>
                                        <?php if ($cpd_category_name) : ?>
                                            <span class="eau-event-cpd-category">
                                                <?php printf(__('Category: %s', 'eau-system'), '<strong>' . esc_html($cpd_category_name) . '</strong>'); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Sidebar -->
                    <aside class="eau-event-sidebar">
                        <div class="eau-event-price-card">
                            <div class="eau-event-price-header">
                                <span class="eau-event-price-label"><?php _e('Member Price', 'eau-system'); ?></span>
                                <?php if ($data['is_live']) : ?>
                                    <div class="eau-event-live-badge">
                                        <span class="eau-live-dot"></span>
                                        <span class="eau-live-text"><?php _e('LIVE', 'eau-system'); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <span class="eau-event-price-value <?php echo $data['price']['is_free'] ? 'eau-event-price-free' : ''; ?>">
                                <?php echo esc_html($data['price']['display']); ?>
                            </span>

                            <?php if ($capacity > 0) : ?>
                                <div class="eau-event-capacity">
                                    <?php echo Helper::icon('users', 16); ?>
                                    <span><?php _e('Spots Left', 'eau-system'); ?></span>
                                    <span class="eau-event-capacity-value <?php echo $spots_left === 0 ? 'eau-event-full' : ''; ?>">
                                        <?php echo $spots_left === 0 ? __('Full', 'eau-system') : sprintf('%d / %d', $spots_left, $capacity); ?>
                                    </span>
                                </div>
                            <?php endif; ?>

                            <?php if ($data['is_live']) : ?>
                                <?php
                                $event_type = $meta['event_type'] ?: 'in-person';
                                $show_location = in_array($event_type, array('in-person', 'hybrid'));
                                $show_virtual = in_array($event_type, array('virtual', 'hybrid'));
                                ?>

                                <?php if ($show_location && !empty($data['location']['full'])) : ?>
                                    <div class="eau-event-live-location">
                                        <?php echo Helper::icon('map-pin', 16); ?>
                                        <span><?php echo esc_html($data['location']['full']); ?></span>
                                    </div>
                                <?php endif; ?>

                                <?php if ($is_registered) : ?>
                                    <?php if ($show_virtual && !empty($meta['virtual_url'])) : ?>
                                        <a href="<?php echo esc_url($meta['virtual_url']); ?>" target="_blank" class="eau-btn eau-btn-primary eau-btn-full eau-event-join-btn" data-event-id="<?php echo esc_attr($data['id']); ?>">
                                            <?php echo Helper::icon('video', 18); ?>
                                            <?php _e('Join Online', 'eau-system'); ?>
                                        </a>
                                    <?php endif; ?>
                                <?php else : ?>
                                    <div class="eau-event-not-registered-badge">
                                        <?php echo Helper::icon('alert-circle', 18); ?>
                                        <?php _e('You must be registered to join this event', 'eau-system'); ?>
                                    </div>
                                <?php endif; ?>

                            <?php elseif (!$data['is_past']) : ?>
                                <?php if ($is_registered) : ?>
                                    <div class="eau-event-registered-badge">
                                        <?php echo Helper::icon('check-circle', 20); ?>
                                        <?php _e("You're registered!", 'eau-system'); ?>
                                    </div>
                                <?php elseif ($spots_left === 0) : ?>
                                    <button class="eau-btn eau-btn-secondary eau-btn-full" disabled>
                                        <?php _e('Event Full', 'eau-system'); ?>
                                    </button>
                                <?php else : ?>
                                    <button class="eau-btn eau-btn-primary eau-btn-full eau-event-register-btn" data-event-id="<?php echo esc_attr($data['id']); ?>">
                                        <?php _e('Register Now', 'eau-system'); ?>
                                    </button>
                                <?php endif; ?>

                                <!-- Countdown -->
                                <div class="eau-event-countdown" data-start="<?php echo esc_attr($iso_date); ?>">
                                    <div class="eau-countdown-header">
                                        <?php echo Helper::icon('clock', 16); ?>
                                        <span><?php _e('Event Starts In', 'eau-system'); ?></span>
                                    </div>
                                    <div class="eau-countdown-title"><?php echo esc_html($data['title']); ?></div>
                                    <div class="eau-countdown-timer">
                                        <div class="eau-countdown-item"><span class="eau-countdown-value" data-days>--</span><span class="eau-countdown-label"><?php _e('DAYS', 'eau-system'); ?></span></div>
                                        <div class="eau-countdown-item"><span class="eau-countdown-value" data-hours>--</span><span class="eau-countdown-label"><?php _e('HOURS', 'eau-system'); ?></span></div>
                                        <div class="eau-countdown-item"><span class="eau-countdown-value" data-minutes>--</span><span class="eau-countdown-label"><?php _e('MINUTES', 'eau-system'); ?></span></div>
                                        <div class="eau-countdown-item"><span class="eau-countdown-value" data-seconds>--</span><span class="eau-countdown-label"><?php _e('SECONDS', 'eau-system'); ?></span></div>
                                    </div>
                                    <div class="eau-countdown-date"><?php echo esc_html($full_date); ?></div>
                                </div>
                            <?php else : ?>
                                <div class="eau-event-past-badge"><?php _e('This event has ended', 'eau-system'); ?></div>
                            <?php endif; ?>

                            <!-- CPD Link - only show after event ended for eligible users -->
                            <?php if ($data['is_past'] && $meta['cpd_points'] && floatval($meta['cpd_points']) > 0 && Eau_Event_Registrations_Ajax::can_view_cpd($event_id)) : ?>
                                <a href="<?php echo esc_url(home_url('/dashboard/my-activities/')); ?>" class="eau-event-cpd-link">
                                    <?php _e('View in My CPD', 'eau-system'); ?>
                                </a>
                            <?php endif; ?>

                            <!-- Share & Save -->
                            <div class="eau-event-actions">
                                <button class="eau-btn eau-btn-outline eau-event-share-btn" data-url="<?php echo esc_url(get_permalink($event_id)); ?>" data-title="<?php echo esc_attr($data['title']); ?>">
                                    <?php echo Helper::icon('share', 16); ?>
                                    <?php _e('Share', 'eau-system'); ?>
                                </button>
                                <button class="eau-btn eau-btn-outline eau-event-save-btn" data-event-id="<?php echo esc_attr($data['id']); ?>">
                                    <?php echo Helper::icon('heart', 16); ?>
                                    <?php _e('Save', 'eau-system'); ?>
                                </button>
                            </div>
                        </div>

                        <!-- Organizer Card -->
                        <div class="eau-event-organizer-card">
                            <span class="eau-event-organizer-label"><?php _e('Organized by', 'eau-system'); ?></span>
                            <div class="eau-event-organizer-info">
                                <div class="eau-event-organizer-logo"><?php echo Helper::icon('building', 24); ?></div>
                                <div class="eau-event-organizer-details">
                                    <span class="eau-event-organizer-name"><?php echo get_bloginfo('name'); ?></span>
                                    <span class="eau-event-organizer-role"><?php _e('Professional Development Team', 'eau-system'); ?></span>
                                </div>
                            </div>
                        </div>
                    </aside>
                </div>
            </div>
        </div>

        <!-- Registration Modal -->
        <?php self::render_registration_modal($data, $date_display, $time_display, $user_name, $user_email); ?>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                if (typeof lucide !== 'undefined') lucide.createIcons();
            });
        </script>
        <?php
    }

    /**
     * Renderiza o modal de registro
     *
     * @since  1.48.2
     * @param  array  $data          Dados do evento
     * @param  string $date_display  Data formatada
     * @param  string $time_display  Horário formatado
     * @param  string $user_name     Nome do usuário
     * @param  string $user_email    Email do usuário
     * @return void
     */
    private static function render_registration_modal($data, $date_display, $time_display, $user_name, $user_email) {
        ?>
        <div class="eau-reg-modal" id="eau-registration-modal">
            <div class="eau-reg-modal-overlay"></div>
            <div class="eau-reg-modal-container">
                <div class="eau-reg-modal-header">
                    <h2 class="eau-reg-modal-title"><?php _e('Confirm Registration', 'eau-system'); ?></h2>
                    <button type="button" class="eau-reg-modal-close" aria-label="Close">&times;</button>
                </div>
                <div class="eau-reg-modal-body">
                    <div class="eau-reg-event-info">
                        <strong><?php echo esc_html($data['title']); ?></strong>
                        <span><?php echo esc_html($date_display); ?> &bull; <?php echo esc_html($time_display); ?></span>
                    </div>

                    <div class="eau-reg-user-info">
                        <p class="eau-reg-info-label"><?php _e('You will be registered as:', 'eau-system'); ?></p>
                        <div class="eau-reg-info-row">
                            <span class="eau-reg-info-icon">👤</span>
                            <span class="eau-reg-info-value"><?php echo esc_html($user_name); ?></span>
                        </div>
                        <div class="eau-reg-info-row">
                            <span class="eau-reg-info-icon">✉️</span>
                            <span class="eau-reg-info-value"><?php echo esc_html($user_email); ?></span>
                        </div>
                    </div>

                    <!-- Additional Information -->
                    <div class="eau-reg-additional-info">
                        <p class="eau-reg-section-title"><?php _e('Additional Information (Optional)', 'eau-system'); ?></p>

                        <div class="eau-reg-field">
                            <label for="eau-reg-dietary"><?php _e('Dietary Requirements', 'eau-system'); ?></label>
                            <input type="text" id="eau-reg-dietary" name="dietary_requirements" placeholder="<?php esc_attr_e('e.g., Vegetarian, Gluten-free, Halal', 'eau-system'); ?>">
                        </div>

                        <div class="eau-reg-field">
                            <label for="eau-reg-accessibility"><?php _e('Accessibility Requirements', 'eau-system'); ?></label>
                            <input type="text" id="eau-reg-accessibility" name="accessibility_requirements" placeholder="<?php esc_attr_e('e.g., Wheelchair access, Hearing loop', 'eau-system'); ?>">
                        </div>

                        <div class="eau-reg-field">
                            <label for="eau-reg-notes"><?php _e('Additional Notes', 'eau-system'); ?></label>
                            <textarea id="eau-reg-notes" name="additional_notes" rows="3" placeholder="<?php esc_attr_e('Any other information we should know?', 'eau-system'); ?>"></textarea>
                        </div>
                    </div>

                    <!-- Terms and Conditions -->
                    <div class="eau-reg-terms">
                        <label class="eau-reg-checkbox-label">
                            <input type="checkbox" id="eau-reg-terms" name="agree_terms" required>
                            <span class="eau-reg-checkbox-text">
                                <?php _e('I agree to the terms and conditions and understand that:', 'eau-system'); ?>
                            </span>
                        </label>
                        <ul class="eau-reg-terms-list">
                            <li><?php _e('Registration is subject to availability', 'eau-system'); ?></li>
                            <li><?php _e('Cancellations must be made 48 hours in advance', 'eau-system'); ?></li>
                            <li><?php _e('CPD points will be awarded upon attendance', 'eau-system'); ?></li>
                            <li><?php _e('I will receive email reminders about this event', 'eau-system'); ?></li>
                        </ul>
                    </div>

                    <div class="eau-reg-message" id="eau-registration-message"></div>
                </div>
                <div class="eau-reg-modal-footer">
                    <button type="button" class="eau-reg-btn-cancel" id="eau-cancel-registration"><?php _e('Cancel', 'eau-system'); ?></button>
                    <button type="button" class="eau-reg-btn-confirm" id="eau-confirm-registration" data-event-id="<?php echo esc_attr($data['id']); ?>">
                        <span class="btn-text"><?php _e('Confirm', 'eau-system'); ?></span>
                        <span class="btn-loading" style="display:none;"><?php _e('Registering...', 'eau-system'); ?></span>
                    </button>
                </div>
                <input type="hidden" id="eau-reg-nonce" value="<?php echo wp_create_nonce('eau_event_registration'); ?>">
            </div>
        </div>
        <?php
    }

    /**
     * Shortcode: [eau_events_archive]
     *
     * Renderiza a listagem de eventos (archive)
     *
     * Atributos:
     * - show_filters: true/false - mostrar filtros (default: true)
     * - show_past: true/false - mostrar eventos passados (default: true)
     * - limit: número de eventos por seção (default: -1 = todos)
     *
     * @since  1.48.2
     * @param  array $atts Atributos do shortcode
     * @return string HTML renderizado
     */
    public static function render_archive($atts) {
        // Se usuário está logado, verifica se membership está ativo (v1.51.53)
        if (is_user_logged_in() && !\EauSystem\Eau_User_Institution_Helper::is_membership_active()) {
            return \EauSystem\Components\Eau_Access_Denied::membership_inactive();
        }

        $atts = shortcode_atts(array(
            'show_filters' => 'true',
            'show_past' => 'true',
            'limit' => -1,
        ), $atts, 'eau_events_archive');

        $show_filters = filter_var($atts['show_filters'], FILTER_VALIDATE_BOOLEAN);
        $show_past = filter_var($atts['show_past'], FILTER_VALIDATE_BOOLEAN);
        $limit = intval($atts['limit']);

        // Enfileira assets
        self::enqueue_assets();

        ob_start();
        self::render_archive_content($show_filters, $show_past, $limit);
        return ob_get_clean();
    }

    /**
     * Renderiza o conteúdo do archive de eventos
     *
     * @since  1.48.2
     * @param  bool $show_filters Mostrar filtros
     * @param  bool $show_past    Mostrar eventos passados
     * @param  int  $limit        Limite de eventos
     * @return void
     */
    private static function render_archive_content($show_filters, $show_past, $limit) {
        // Filters
        $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
        $category = isset($_GET['category']) ? absint($_GET['category']) : 0;
        $event_type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : '';

        // CPD categories from database
        $cpd_categories = \EauSystem\Shared\get_cpd_categories();

        // Base query args
        $base_args = array(
            'post_type' => 'eau_event',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'meta_key' => 'evt_start_datetime',
            'orderby' => 'meta_value',
        );

        if (!empty($search)) $base_args['s'] = $search;

        // Upcoming events: end_datetime >= now
        $upcoming_meta = array(
            'relation' => 'AND',
            array('key' => 'evt_end_datetime', 'value' => current_time('Y-m-d\TH:i'), 'compare' => '>=', 'type' => 'DATETIME'),
        );
        if ($category > 0) {
            $upcoming_meta[] = array('key' => 'evt_cpd_category', 'value' => (string) $category, 'compare' => '=', 'type' => 'NUMERIC');
        }
        if (!empty($event_type)) {
            $upcoming_meta[] = array('key' => 'evt_event_type', 'value' => $event_type, 'compare' => '=');
        }

        $upcoming_args = array_merge($base_args, array(
            'order' => 'ASC',
            'meta_query' => $upcoming_meta,
        ));

        // Past events: end_datetime < now
        $past_meta = array(
            'relation' => 'AND',
            array('key' => 'evt_end_datetime', 'value' => current_time('Y-m-d\TH:i'), 'compare' => '<', 'type' => 'DATETIME'),
        );
        if ($category > 0) {
            $past_meta[] = array('key' => 'evt_cpd_category', 'value' => (string) $category, 'compare' => '=', 'type' => 'NUMERIC');
        }
        if (!empty($event_type)) {
            $past_meta[] = array('key' => 'evt_event_type', 'value' => $event_type, 'compare' => '=');
        }

        $past_args = array_merge($base_args, array(
            'order' => 'DESC',
            'meta_query' => $past_meta,
        ));

        $upcoming = new \WP_Query($upcoming_args);
        $past = $show_past ? new \WP_Query($past_args) : null;
        ?>

        <div class="eau-events-archive">
            <div class="eau-events-container">
                <!-- Header -->
                <div class="eau-events-header">
                    <div class="eau-events-header-content">
                        <h1 class="eau-events-title"><?php _e('Events', 'eau-system'); ?></h1>
                        <p class="eau-events-subtitle"><?php _e('Discover and register for upcoming events', 'eau-system'); ?></p>
                    </div>
                    <?php if (Helper::is_admin()) : ?>
                        <a href="<?php echo site_url('dashboard/events'); ?>" class="eau-btn eau-btn-primary">
                            <?php echo Helper::icon('settings', 16); ?>
                            <?php _e('Manage Events', 'eau-system'); ?>
                        </a>
                    <?php endif; ?>
                </div>

                <?php if ($show_filters) : ?>
                <!-- Filters -->
                <div class="eau-events-filters">
                    <form method="get" class="eau-events-filter-form">
                        <div class="eau-filter-search">
                            <input type="text" name="search" class="eau-filter-input" placeholder="<?php _e('Search events...', 'eau-system'); ?>" value="<?php echo esc_attr($search); ?>">
                        </div>
                        <div class="eau-filter-select-wrapper">
                            <select name="category" class="eau-filter-select">
                                <option value=""><?php _e('All Categories', 'eau-system'); ?></option>
                                <?php foreach ($cpd_categories as $cat) : ?>
                                    <option value="<?php echo esc_attr($cat['id']); ?>" <?php selected($category, $cat['id']); ?>><?php echo esc_html($cat['category_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="eau-filter-select-wrapper">
                            <select name="type" class="eau-filter-select">
                                <option value=""><?php _e('All Types', 'eau-system'); ?></option>
                                <option value="in-person" <?php selected($event_type, 'in-person'); ?>><?php _e('In-Person', 'eau-system'); ?></option>
                                <option value="virtual" <?php selected($event_type, 'virtual'); ?>><?php _e('Virtual', 'eau-system'); ?></option>
                                <option value="hybrid" <?php selected($event_type, 'hybrid'); ?>><?php _e('Hybrid', 'eau-system'); ?></option>
                            </select>
                        </div>
                    </form>
                </div>
                <?php endif; ?>

                <!-- Upcoming Events -->
                <?php if ($upcoming->have_posts()) : ?>
                    <section class="eau-events-section">
                        <h2 class="eau-events-section-title"><?php _e('Upcoming Events', 'eau-system'); ?></h2>
                        <div class="eau-events-grid eau-events-grid-upcoming">
                            <?php while ($upcoming->have_posts()) : $upcoming->the_post(); ?>
                                <?php echo Helper::render_card(get_the_ID(), 'upcoming'); ?>
                            <?php endwhile; wp_reset_postdata(); ?>
                        </div>
                    </section>
                <?php endif; ?>

                <!-- Past Events -->
                <?php if ($show_past && $past && $past->have_posts()) : ?>
                    <section class="eau-events-section">
                        <h2 class="eau-events-section-title"><?php _e('Past Events', 'eau-system'); ?></h2>
                        <div class="eau-events-grid eau-events-grid-past">
                            <?php while ($past->have_posts()) : $past->the_post(); ?>
                                <?php echo Helper::render_card(get_the_ID(), 'past'); ?>
                            <?php endwhile; wp_reset_postdata(); ?>
                        </div>
                    </section>
                <?php endif; ?>

                <!-- No Events -->
                <?php if (!$upcoming->have_posts() && (!$show_past || !$past || !$past->have_posts())) : ?>
                    <div class="eau-events-empty">
                        <?php echo Helper::icon('calendar', 48); ?>
                        <h3><?php _e('No events found', 'eau-system'); ?></h3>
                        <p><?php _e('Check back later for upcoming events.', 'eau-system'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                if (typeof lucide !== 'undefined') lucide.createIcons();
            });
        </script>
        <?php
    }
}
