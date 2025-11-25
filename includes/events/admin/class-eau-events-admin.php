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

use EauSystem\Events\Config;

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
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    /**
     * Enfileira assets do admin
     *
     * @since  1.28.0
     * @param  string $hook Hook da página atual
     * @return void
     */
    public function enqueue_assets($hook) {
        global $post_type;

        if ($post_type !== Config\POST_TYPE) {
            return;
        }

        if (!in_array($hook, array('post.php', 'post-new.php'))) {
            return;
        }

        wp_enqueue_media();

        wp_enqueue_style(
            'eau-events-admin',
            EAU_SYSTEM_PLUGIN_URL . 'includes/events/assets/css/eau-events-admin.css',
            array(),
            EAU_SYSTEM_VERSION
        );

        wp_enqueue_script(
            'eau-events-admin',
            EAU_SYSTEM_PLUGIN_URL . 'includes/events/assets/js/eau-events-admin.js',
            array('jquery'),
            EAU_SYSTEM_VERSION,
            true
        );

        wp_localize_script('eau-events-admin', 'eauEventsAdmin', array(
            'mediaTitle'  => __('Select Event Image', 'eau-system'),
            'mediaButton' => __('Use this image', 'eau-system'),
        ));
    }
}
