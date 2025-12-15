<?php
/**
 * Events Frontend - Helper Functions
 *
 * Funções auxiliares reutilizáveis para templates de eventos.
 *
 * @package    EauSystem
 * @subpackage Events\Frontend
 * @since      1.28.2
 */

namespace EauSystem\Events\Frontend;

use EauSystem\Events\Config;

if (!defined('WPINC')) {
    die;
}

/**
 * Class Eau_Events_Helper
 *
 * @since 1.28.2
 */
class Eau_Events_Helper {

    /**
     * Obtém todos os meta dados de um evento
     *
     * @since  1.28.2
     * @param  int $post_id ID do post
     * @return array Meta dados formatados
     */
    public static function get_event_data($post_id) {
        $meta = Config\get_event_meta($post_id);
        $post = get_post($post_id);

        // Image - Prioriza Event Image do modal, fallback para Featured Image
        $thumbnail = '';
        if (!empty($meta['image_id'])) {
            $thumbnail = wp_get_attachment_image_url($meta['image_id'], 'large');
        }
        if (!$thumbnail) {
            $thumbnail = get_the_post_thumbnail_url($post_id, 'large');
        }

        // Dates
        $start_obj = self::parse_datetime($meta['start_datetime']);
        $end_obj = self::parse_datetime($meta['end_datetime']);

        // Location
        $location = self::build_location($meta);

        // Price
        $price = self::format_price($meta['member_price']);

        // Time checks
        $now = current_time('timestamp');
        $start_ts = $start_obj ? $start_obj->getTimestamp() : 0;
        $end_ts = $end_obj ? $end_obj->getTimestamp() : 0;

        $is_past = $end_ts ? $end_ts < $now : ($start_ts && $start_ts < $now);
        $is_live = $start_ts && $end_ts && $start_ts <= $now && $end_ts >= $now;

        return array(
            'id' => $post_id,
            'title' => $post->post_title,
            'slug' => $post->post_name,
            'thumbnail' => $thumbnail,
            'meta' => $meta,
            'start_obj' => $start_obj,
            'end_obj' => $end_obj,
            'location' => $location,
            'price' => $price,
            'is_past' => $is_past,
            'is_live' => $is_live,
        );
    }

    /**
     * Parse datetime string para DateTime object
     *
     * @since  1.28.2
     * @param  string $datetime
     * @return \DateTime|null
     */
    public static function parse_datetime($datetime) {
        if (empty($datetime)) return null;

        $obj = \DateTime::createFromFormat('Y-m-d\TH:i', $datetime);
        if (!$obj) {
            $obj = \DateTime::createFromFormat('Y-m-d H:i:s', $datetime);
        }
        return $obj ?: null;
    }

    /**
     * Formata data para exibição
     *
     * @since  1.28.2
     * @param  \DateTime|null $date_obj
     * @param  string         $format
     * @return string
     */
    public static function format_date($date_obj, $format = 'M j, Y') {
        return $date_obj ? $date_obj->format($format) : '';
    }

    /**
     * Formata hora para exibição
     *
     * @since  1.28.2
     * @param  \DateTime|null $date_obj
     * @return string
     */
    public static function format_time($date_obj) {
        return $date_obj ? $date_obj->format('g:i A') : '';
    }

    /**
     * Constrói string de localização
     *
     * @since  1.28.2
     * @param  array $meta
     * @return array
     */
    public static function build_location($meta) {
        if ($meta['event_type'] === 'virtual') {
            return array(
                'short' => __('Online', 'eau-system'),
                'full' => __('Virtual Event', 'eau-system'),
            );
        }

        $parts = array_filter(array(
            $meta['venue_name'],
            $meta['address'],
            $meta['city'],
            $meta['state'],
            $meta['postal_code'],
            $meta['country']
        ));

        $short = $meta['venue_name'] ?: ($meta['city'] ?: __('Location TBA', 'eau-system'));
        $full = $parts ? implode(', ', $parts) : __('Location TBA', 'eau-system');

        return array('short' => $short, 'full' => $full);
    }

    /**
     * Formata preço para exibição
     *
     * @since  1.28.2
     * @param  mixed $price
     * @return array
     */
    public static function format_price($price) {
        $value = floatval($price);
        return array(
            'value' => $value,
            'display' => $value > 0 ? '$' . number_format($value, 2) : __('Free', 'eau-system'),
            'is_free' => $value === 0.0,
        );
    }

    /**
     * Verifica se usuário atual é admin
     *
     * @since  1.28.2
     * @return bool
     */
    public static function is_admin() {
        if (!is_user_logged_in()) return false;
        $mem_type = get_user_meta(get_current_user_id(), 'mem_type', true);
        return in_array($mem_type, array('superAdmin', 'Admin'));
    }

    /**
     * Renderiza ícone SVG
     *
     * @since  1.28.2
     * @param  string $name Nome do ícone
     * @param  int    $size Tamanho
     * @return string SVG HTML
     */
    public static function icon($name, $size = 16) {
        $icons = array(
            'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line>',
            'clock' => '<circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline>',
            'map-pin' => '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle>',
            'dollar' => '<line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>',
            'graduation' => '<path d="M22 10v6M2 10l10-5 10 5-10 5z"></path><path d="M6 12v5c3 3 9 3 12 0v-5"></path>',
            'users' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path>',
            'check-circle' => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline>',
            'share' => '<circle cx="18" cy="5" r="3"></circle><circle cx="6" cy="12" r="3"></circle><circle cx="18" cy="19" r="3"></circle><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line>',
            'heart' => '<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>',
            'chevron-left' => '<polyline points="15 18 9 12 15 6"></polyline>',
            'settings' => '<circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>',
            'building' => '<rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="3" y1="9" x2="21" y2="9"></line><line x1="9" y1="21" x2="9" y2="9"></line>',
            'alert-circle' => '<circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line>',
            'video' => '<polygon points="23 7 16 12 23 17 23 7"></polygon><rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect>',
        );

        $path = $icons[$name] ?? '';
        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">%s</svg>',
            $size, $size, $path
        );
    }

    /**
     * Renderiza card de evento
     *
     * @since  1.28.2
     * @param  int    $post_id
     * @param  string $type 'upcoming' ou 'past'
     * @return string HTML
     */
    public static function render_card($post_id, $type = 'upcoming') {
        $data = self::get_event_data($post_id);
        $permalink = get_permalink($post_id);
        $date = self::format_date($data['start_obj']);
        $time = self::format_time($data['start_obj']);
        $cpd = $data['meta']['cpd_points'] ? intval($data['meta']['cpd_points']) . ' CPD' : '';
        $is_admin = self::is_admin();
        $edit_url = site_url('dashboard/events/?edit=' . $post_id);

        ob_start();
        ?>
        <article class="eau-event-card">
            <?php if ($data['thumbnail']) : ?>
                <div class="eau-event-card-image">
                    <a href="<?php echo esc_url($permalink); ?>">
                        <img src="<?php echo esc_url($data['thumbnail']); ?>" alt="<?php echo esc_attr($data['title']); ?>">
                    </a>
                </div>
            <?php endif; ?>

            <div class="eau-event-card-content">
                <h3 class="eau-event-card-title">
                    <a href="<?php echo esc_url($permalink); ?>"><?php echo esc_html($data['title']); ?></a>
                </h3>

                <div class="eau-event-card-meta">
                    <?php if ($date) : ?>
                        <div class="eau-event-card-meta-item">
                            <?php echo self::icon('calendar', 14); ?>
                            <span><?php echo esc_html($date); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($time) : ?>
                        <div class="eau-event-card-meta-item">
                            <?php echo self::icon('clock', 14); ?>
                            <span><?php echo esc_html($time); ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="eau-event-card-location">
                    <?php echo self::icon('map-pin', 14); ?>
                    <span><?php echo esc_html($data['location']['short']); ?></span>
                </div>

                <div class="eau-event-card-footer">
                    <div class="eau-event-card-info">
                        <span class="eau-event-card-price">
                            <?php echo self::icon('dollar', 14); ?>
                            <?php echo esc_html($data['price']['display']); ?>
                        </span>
                        <?php if ($cpd) : ?>
                            <span class="eau-event-card-cpd">
                                <?php echo self::icon('graduation', 14); ?>
                                <?php echo esc_html($cpd); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="eau-event-card-actions">
                        <?php if ($is_admin && $type === 'upcoming') : ?>
                            <a href="<?php echo esc_url($edit_url); ?>" class="eau-event-card-link eau-event-card-edit">
                                <?php _e('Edit', 'eau-system'); ?>
                            </a>
                        <?php endif; ?>
                        <a href="<?php echo esc_url($permalink); ?>" class="eau-event-card-link">
                            <?php _e('Open', 'eau-system'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </article>
        <?php
        return ob_get_clean();
    }
}
