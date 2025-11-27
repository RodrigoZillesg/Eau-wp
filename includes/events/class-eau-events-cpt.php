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

        // Force classic editor for eau_event
        add_filter('use_block_editor_for_post_type', array($this, 'disable_gutenberg'), 10, 2);
    }

    /**
     * Disable Gutenberg for eau_event post type
     *
     * @since 1.31.8
     * @param bool   $use_block_editor
     * @param string $post_type
     * @return bool
     */
    public function disable_gutenberg($use_block_editor, $post_type) {
        if ($post_type === Config\POST_TYPE) {
            return false;
        }
        return $use_block_editor;
    }

    /**
     * Registra o Post Type
     *
     * @since  1.28.0
     * @return void
     */
    public function register_post_type() {
        // Se JetEngine gerencia este CPT, não registrar aqui
        if ($this->is_managed_by_jet_engine()) {
            return;
        }

        // Fallback: registrar se JetEngine não está gerenciando
        register_post_type(Config\POST_TYPE, array(
            'labels'             => $this->get_labels(),
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => array('slug' => 'events', 'with_front' => false),
            'capability_type'    => 'post',
            'map_meta_cap'       => true,
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => 25,
            'menu_icon'          => 'dashicons-calendar-alt',
            'supports'           => array('title', 'thumbnail'),
            'show_in_rest'       => false, // Use classic editor for our metabox
        ));
    }

    /**
     * Verifica se JetEngine gerencia o CPT
     *
     * Retorna true apenas se:
     * 1. JetEngine está ativo (constante definida)
     * 2. CPT existe na tabela jet_post_types com status='publish'
     *
     * @since  1.31.4
     * @return bool
     */
    private function is_managed_by_jet_engine() {
        // JetEngine must be active
        if (!defined('JET_ENGINE_VERSION')) {
            return false;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'jet_post_types';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            return false;
        }

        // Verifica se existe com status='publish' (JetEngine gerencia)
        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE slug = %s AND status = 'publish'",
            Config\POST_TYPE
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
}
