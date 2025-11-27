<?php
/**
 * Events Frontend - Assets
 *
 * Enfileira CSS e JavaScript para páginas de eventos no frontend.
 * Assets são carregados apenas em archive e single de eventos.
 *
 * @package    EauSystem
 * @subpackage Events\Frontend
 * @since      1.28.0
 */

namespace EauSystem\Events\Frontend;

use EauSystem\Events\Config;

if (!defined('WPINC')) {
    die;
}

/**
 * Class Eau_Events_Assets
 *
 * Gerencia carregamento de assets no frontend.
 *
 * @since 1.28.0
 */
class Eau_Events_Assets {

    /**
     * Instância singleton
     *
     * @since 1.28.0
     * @var   Eau_Events_Assets|null
     */
    private static $instance = null;

    /**
     * Retorna a instância singleton
     *
     * @since  1.28.0
     * @return Eau_Events_Assets
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor privado (singleton)
     *
     * @since 1.28.0
     */
    private function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue'));
    }

    /**
     * Enfileira assets de eventos no frontend
     *
     * Carrega CSS e JS apenas em páginas de archive ou single
     * do post type eau_event.
     *
     * @since  1.28.0
     * @return void
     */
    public function enqueue() {
        $post_type = Config\POST_TYPE;

        if (!is_post_type_archive($post_type) && !is_singular($post_type)) {
            return;
        }

        wp_enqueue_style(
            'eau-events-frontend',
            EAU_SYSTEM_PLUGIN_URL . 'includes/events/assets/css/eau-events-frontend.css',
            array(),
            EAU_SYSTEM_VERSION
        );

        wp_enqueue_script(
            'eau-events-frontend',
            EAU_SYSTEM_PLUGIN_URL . 'includes/events/assets/js/eau-events-frontend.js',
            array(),
            EAU_SYSTEM_VERSION,
            true
        );

        // Pass AJAX data to script
        wp_localize_script('eau-events-frontend', 'eauEventsFrontendData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('eau_event_registration'),
        ));
    }
}
