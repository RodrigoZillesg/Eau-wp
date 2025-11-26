<?php
/**
 * Events Admin - Main Controller
 *
 * Gerencia assets do admin para eventos.
 *
 * @package    EauSystem
 * @subpackage Events\Admin
 * @since      1.28.0
 */

namespace EauSystem\Events\Admin;

if (!defined('WPINC')) {
    die;
}

/**
 * Class Eau_Events_Admin
 *
 * Controla o carregamento de assets no admin.
 *
 * @since 1.28.0
 */
class Eau_Events_Admin {

    /**
     * Instância singleton
     *
     * @var Eau_Events_Admin|null
     */
    private static $instance = null;

    /**
     * Retorna a instância singleton
     *
     * @since  1.28.0
     * @return Eau_Events_Admin
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
     * @since 1.28.0
     */
    private function __construct() {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'), 20);
    }

    /**
     * Enfileira assets do admin
     *
     * @since  1.28.0
     * @param  string $hook Hook da página atual
     * @return void
     */
    public function enqueue_assets($hook) {
        // Only on edit/new post screens
        if (!in_array($hook, array('post.php', 'post-new.php'))) {
            return;
        }

        // Get post type - try multiple methods for compatibility
        global $post_type, $post;

        $current_post_type = $post_type;

        // Fallback: get from post object
        if (empty($current_post_type) && !empty($post)) {
            $current_post_type = $post->post_type;
        }

        // Fallback: get from query string (for post.php)
        if (empty($current_post_type) && isset($_GET['post'])) {
            $current_post_type = get_post_type(absint($_GET['post']));
        }

        // Fallback: get from post_type query string (for post-new.php)
        if (empty($current_post_type) && isset($_GET['post_type'])) {
            $current_post_type = sanitize_key($_GET['post_type']);
        }

        if ($current_post_type !== 'eau_event') {
            return;
        }

        wp_enqueue_media();

        wp_enqueue_style(
            'eau-events-admin',
            EAU_SYSTEM_PLUGIN_URL . 'includes/events/assets/css/eau-events-admin.css',
            array(),
            EAU_SYSTEM_VERSION
        );

        // Localize script data BEFORE enqueueing the script
        wp_register_script(
            'eau-events-admin',
            EAU_SYSTEM_PLUGIN_URL . 'includes/events/assets/js/eau-events-admin.js',
            array('jquery', 'media-upload', 'thickbox'),
            EAU_SYSTEM_VERSION,
            true
        );

        wp_localize_script('eau-events-admin', 'eauEventsAdmin', array(
            'mediaTitle'  => __('Select Event Image', 'eau-system'),
            'mediaButton' => __('Use this image', 'eau-system'),
        ));

        wp_enqueue_script('eau-events-admin');

        // Fallback inline script in case localize doesn't work
        wp_add_inline_script('eau-events-admin',
            'window.eauEventsAdmin = window.eauEventsAdmin || {
                mediaTitle: "' . esc_js(__('Select Event Image', 'eau-system')) . '",
                mediaButton: "' . esc_js(__('Use this image', 'eau-system')) . '"
            };',
            'before'
        );
    }
}
