<?php
/**
 * Event Registrations - CPT Registration
 *
 * Registra o Custom Post Type (fallback quando JetEngine não gerencia).
 *
 * @package    EauSystem
 * @subpackage Events\Registrations
 * @since      1.29.0
 */

namespace EauSystem\EventRegistrations;

use EauSystem\EventRegistrations\Config;

if (!defined('WPINC')) {
    die;
}

/**
 * Class Eau_Event_Registrations_CPT
 *
 * Registra o Custom Post Type 'eau_event_reg'.
 *
 * @since 1.29.0
 */
class Eau_Event_Registrations_CPT {

    /**
     * Instância singleton
     *
     * @var Eau_Event_Registrations_CPT|null
     */
    private static $instance = null;

    /**
     * Retorna a instância singleton
     *
     * @since  1.29.0
     * @return Eau_Event_Registrations_CPT
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
        // Prioridade 5 para registrar antes do JetEngine (que usa 10)
        add_action('init', array($this, 'register_post_type'), 5);
    }

    /**
     * Registra o Post Type
     *
     * @since  1.29.0
     * @return void
     */
    public function register_post_type() {
        register_post_type(Config\POST_TYPE, array(
            'labels'             => $this->get_labels(),
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => 'edit.php?post_type=eau_event', // Submenu de Events
            'query_var'          => true,
            'rewrite'            => false,
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => null,
            'supports'           => array('title'),
            'show_in_rest'       => true,
        ));
    }

    /**
     * Retorna labels do Post Type
     *
     * @since  1.29.0
     * @return array
     */
    private function get_labels() {
        return array(
            'name'               => __('Event Registrations', 'eau-system'),
            'singular_name'      => __('Registration', 'eau-system'),
            'add_new'            => __('Add New', 'eau-system'),
            'add_new_item'       => __('Add New Registration', 'eau-system'),
            'edit_item'          => __('Edit Registration', 'eau-system'),
            'new_item'           => __('New Registration', 'eau-system'),
            'view_item'          => __('View Registration', 'eau-system'),
            'all_items'          => __('Registrations', 'eau-system'),
            'search_items'       => __('Search Registrations', 'eau-system'),
            'not_found'          => __('No registrations found.', 'eau-system'),
            'not_found_in_trash' => __('No registrations found in Trash.', 'eau-system'),
            'menu_name'          => __('Event Registrations', 'eau-system'),
        );
    }

}
