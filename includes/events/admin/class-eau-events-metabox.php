<?php
/**
 * Events Admin - Metabox Controller
 *
 * Gerencia o metabox com tabs no admin.
 *
 * @package    EauSystem
 * @subpackage Events\Admin
 * @since      1.28.0
 */

namespace EauSystem\Events\Admin;

use EauSystem\Events\Config;
use EauSystem\Events\Admin\Tabs;

if (!defined('WPINC')) {
    die;
}

/**
 * Class Eau_Events_Metabox
 *
 * Renderiza e salva o metabox de eventos.
 *
 * @since 1.28.0
 */
class Eau_Events_Metabox {

    /**
     * Inicializa hooks do metabox
     *
     * @since  1.28.0
     * @return void
     */
    public static function init() {
        add_action('add_meta_boxes', array(__CLASS__, 'add_metabox'), 5);
        add_action('save_post_' . Config\POST_TYPE, array(__CLASS__, 'save'), 10, 2);
    }

    /**
     * Adiciona o metabox
     *
     * @since  1.28.0
     * @return void
     */
    public static function add_metabox() {
        add_meta_box(
            'eau_event_details',
            __('Event Details', 'eau-system'),
            array(__CLASS__, 'render'),
            Config\POST_TYPE,
            'normal',
            'high'
        );
    }

    /**
     * Renderiza o metabox com tabs
     *
     * @since  1.28.0
     * @param  \WP_Post $post Post atual
     * @return void
     */
    public static function render($post) {
        wp_nonce_field('eau_event_save', 'eau_event_nonce');
        $meta = Config\get_event_meta($post->ID);
        ?>
        <div class="eau-event-metabox">
            <div class="eau-tabs-nav">
                <button type="button" class="eau-tab-btn active" data-tab="basic-info">
                    <span class="dashicons dashicons-edit"></span> <?php _e('Basic Info', 'eau-system'); ?>
                </button>
                <button type="button" class="eau-tab-btn" data-tab="location">
                    <span class="dashicons dashicons-location"></span> <?php _e('Location', 'eau-system'); ?>
                </button>
                <button type="button" class="eau-tab-btn" data-tab="pricing">
                    <span class="dashicons dashicons-tickets-alt"></span> <?php _e('Pricing', 'eau-system'); ?>
                </button>
                <button type="button" class="eau-tab-btn" data-tab="settings">
                    <span class="dashicons dashicons-admin-settings"></span> <?php _e('Settings', 'eau-system'); ?>
                </button>
            </div>
            <div class="eau-tabs-content">
                <?php
                Tabs\render_basic_info($meta);
                Tabs\render_location($meta);
                Tabs\render_pricing($meta);
                Tabs\render_settings($meta);
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Salva os dados do metabox
     *
     * @since  1.28.0
     * @param  int      $post_id ID do post
     * @param  \WP_Post $post    Objeto do post
     * @return void
     */
    public static function save($post_id, $post) {
        if (!isset($_POST['eau_event_nonce']) || !wp_verify_nonce($_POST['eau_event_nonce'], 'eau_event_save')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $prefix = Config\META_PREFIX;

        // Text fields
        $text = array('short_description', 'start_datetime', 'end_datetime', 'timezone', 'event_type', 'venue_name', 'address', 'city', 'state', 'postal_code', 'country', 'early_bird_end_date', 'visibility');
        foreach ($text as $f) {
            if (isset($_POST[$prefix . $f])) {
                update_post_meta($post_id, $prefix . $f, sanitize_text_field($_POST[$prefix . $f]));
            }
        }

        // Numbers
        $numbers = array('image_id', 'capacity', 'member_price', 'early_bird_price', 'cpd_points', 'cpd_category');
        foreach ($numbers as $f) {
            if (isset($_POST[$prefix . $f])) {
                $val = $_POST[$prefix . $f];
                if ($val === '') {
                    delete_post_meta($post_id, $prefix . $f);
                } else {
                    update_post_meta($post_id, $prefix . $f, floatval($val));
                }
            }
        }

        // Checkboxes
        $checks = array('require_approval');
        foreach ($checks as $f) {
            update_post_meta($post_id, $prefix . $f, isset($_POST[$prefix . $f]) ? '1' : '');
        }

        // URL
        if (isset($_POST[$prefix . 'virtual_url'])) {
            update_post_meta($post_id, $prefix . 'virtual_url', esc_url_raw($_POST[$prefix . 'virtual_url']));
        }

        // WYSIWYG
        if (isset($_POST[$prefix . 'full_description'])) {
            update_post_meta($post_id, $prefix . 'full_description', wp_kses_post($_POST[$prefix . 'full_description']));
        }
    }
}

// Initialize
Eau_Events_Metabox::init();
