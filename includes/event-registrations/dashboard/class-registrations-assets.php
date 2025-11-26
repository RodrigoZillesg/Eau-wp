<?php
/**
 * Event Registrations Assets
 *
 * @package    EauSystem
 * @subpackage EventRegistrations\Dashboard
 * @since      1.30.0
 */

namespace EauSystem\EventRegistrations\Dashboard;

if (!defined('WPINC')) {
    die;
}

/**
 * Class Registrations_Assets
 *
 * Gerencia CSS e JavaScript da página de registrations.
 *
 * @since 1.30.0
 */
class Registrations_Assets {

    /**
     * Enfileira todos os assets necessários
     *
     * @since  1.30.0
     * @return void
     */
    public static function enqueue() {
        self::enqueue_styles();
        self::enqueue_scripts();
        self::localize_scripts();
    }

    /**
     * Enfileira estilos CSS
     *
     * @since  1.30.0
     * @return void
     */
    private static function enqueue_styles() {
        // eau-components base
        if (!wp_style_is('eau-components', 'registered')) {
            wp_register_style(
                'eau-components',
                EAU_SYSTEM_PLUGIN_URL . 'assets/css/eau-components.css',
                array(),
                EAU_SYSTEM_VERSION
            );
        }
        wp_enqueue_style('eau-components');

        // Events management CSS (shared styles)
        wp_enqueue_style(
            'eau-events-management',
            EAU_SYSTEM_PLUGIN_URL . 'includes/events/assets/css/eau-events-management.css',
            array('eau-components'),
            EAU_SYSTEM_VERSION
        );

        // SweetAlert2
        wp_enqueue_style(
            'sweetalert2',
            'https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css',
            array(),
            '11'
        );
    }

    /**
     * Enfileira scripts JavaScript
     *
     * @since  1.30.0
     * @return void
     */
    private static function enqueue_scripts() {
        // Lucide Icons
        wp_enqueue_script(
            'lucide',
            'https://unpkg.com/lucide@latest/dist/umd/lucide.min.js',
            array(),
            null,
            true
        );

        // SweetAlert2
        wp_enqueue_script(
            'sweetalert2',
            'https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js',
            array(),
            '11',
            true
        );

        // Main JavaScript
        wp_enqueue_script(
            'eau-event-registrations-page',
            EAU_SYSTEM_PLUGIN_URL . 'includes/events/assets/js/eau-event-registrations-page.js',
            array('jquery', 'lucide'),
            EAU_SYSTEM_VERSION,
            true
        );

        // Initialize Lucide icons
        wp_add_inline_script(
            'lucide',
            'document.addEventListener("DOMContentLoaded", function() { lucide.createIcons(); });'
        );
    }

    /**
     * Localiza scripts com dados do PHP
     *
     * @since  1.30.0
     * @return void
     */
    private static function localize_scripts() {
        wp_localize_script('eau-event-registrations-page', 'eauEventRegistrations', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('eau_events_management_nonce'),
        ));
    }
}
