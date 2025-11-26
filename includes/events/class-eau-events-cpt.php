<?php
/**
 * Eau Events - CPT Registration
 *
 * Registra o Custom Post Type e Taxonomy.
 *
 * @package    EauSystem
 * @subpackage Events
 * @since      1.28.0
 */

namespace EauSystem\Events;

use EauSystem\Events\Config;

if (!defined('WPINC')) {
    die;
}

/**
 * Class Eau_Events_CPT
 *
 * Registra o Custom Post Type 'eau_event'.
 *
 * @since 1.28.0
 */
class Eau_Events_CPT {

    /**
     * Instância singleton
     *
     * @var Eau_Events_CPT|null
     */
    private static $instance = null;

    /**
     * Retorna a instância singleton
     *
     * @since  1.28.0
     * @return Eau_Events_CPT
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
        add_action('init', array($this, 'register_post_type'), 10);
    }

    /**
     * Registra o Post Type
     *
     * @since  1.28.0
     * @return void
     */
    public function register_post_type() {
        // Remove from JetEngine if exists to avoid conflicts
        $this->remove_from_jet_engine();

        register_post_type(Config\POST_TYPE, array(
            'labels'             => $this->get_labels(),
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => array('slug' => 'events', 'with_front' => false),
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => 25,
            'menu_icon'          => 'dashicons-calendar-alt',
            'supports'           => array('title', 'thumbnail'),
            'show_in_rest'       => true,
        ));
    }

    /**
     * Retorna labels do Post Type
     *
     * @since  1.28.0
     * @return array
     */
    private function get_labels() {
        return array(
            'name'               => __('Events', 'eau-system'),
            'singular_name'      => __('Event', 'eau-system'),
            'add_new'            => __('Add New', 'eau-system'),
            'add_new_item'       => __('Add New Event', 'eau-system'),
            'edit_item'          => __('Edit Event', 'eau-system'),
            'new_item'           => __('New Event', 'eau-system'),
            'view_item'          => __('View Event', 'eau-system'),
            'all_items'          => __('All Events', 'eau-system'),
            'search_items'       => __('Search Events', 'eau-system'),
            'not_found'          => __('No events found.', 'eau-system'),
            'not_found_in_trash' => __('No events found in Trash.', 'eau-system'),
            'menu_name'          => __('Events', 'eau-system'),
        );
    }

    /**
     * Remove CPT da tabela JetEngine para evitar conflitos
     *
     * @since  1.31.3
     * @return void
     */
    private function remove_from_jet_engine() {
        global $wpdb;
        $table = $wpdb->prefix . 'jet_post_types';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            return;
        }

        // Delete from JetEngine table
        $wpdb->delete($table, array('slug' => Config\POST_TYPE), array('%s'));
    }
}
