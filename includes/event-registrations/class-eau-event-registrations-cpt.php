<?php
/**
 * Event Registrations - CPT Registration
 *
 * Registra o Custom Post Type e sincroniza com JetEngine.
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
     * Versão para controle de sincronização com JetEngine
     */
    const VERSION = '1.46.8';

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
        add_action('init', array($this, 'register_to_jet_engine'), 5);
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

    /**
     * Registra CPT na tabela JetEngine
     *
     * @since  1.46.8
     * @return void
     */
    public function register_to_jet_engine() {
        global $wpdb;
        $table = $wpdb->prefix . 'jet_post_types';

        // Verifica se tabela JetEngine existe
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            return;
        }

        // Verifica se precisa atualizar baseado na versão
        $version_key = 'eau_event_reg_jet_version';
        $saved_version = get_option($version_key);

        if ($this->exists_in_jet_engine()) {
            // Atualiza se versão mudou
            if ($saved_version !== self::VERSION) {
                $wpdb->delete($table, array('slug' => Config\POST_TYPE), array('%s'));
                $this->save_to_jet_engine();
                update_option($version_key, self::VERSION);
            }
            return;
        }

        $this->save_to_jet_engine();
        update_option($version_key, self::VERSION);
    }

    /**
     * Verifica se CPT existe na tabela JetEngine
     *
     * @since  1.46.8
     * @return bool
     */
    private function exists_in_jet_engine() {
        global $wpdb;
        $table = $wpdb->prefix . 'jet_post_types';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            return false;
        }

        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE slug = %s",
            Config\POST_TYPE
        ));
    }

    /**
     * Salva configuração do CPT na tabela JetEngine
     *
     * @since  1.46.8
     * @return int|false
     */
    private function save_to_jet_engine() {
        global $wpdb;
        $table = $wpdb->prefix . 'jet_post_types';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            return false;
        }

        $labels = array(
            'name'          => 'Event Registrations',
            'singular_name' => 'Registration',
            'menu_name'     => 'Registrations',
        );

        $args = array(
            'public'              => false,
            'publicly_queryable'  => false,
            'show_ui'             => true,
            'show_in_menu'        => 'edit.php?post_type=eau_event',
            'show_in_admin_bar'   => false,
            'show_in_nav_menus'   => false,
            'query_var'           => true,
            'has_archive'         => false,
            'hierarchical'        => false,
            'show_in_rest'        => true,
            'capability_type'     => 'post',
            'map_meta_cap'        => true,
            'supports'            => array('title'),
            'rewrite'             => false,
        );

        $meta_fields = $this->get_jet_meta_fields();

        $data = array(
            'slug'        => Config\POST_TYPE,
            'status'      => 'publish',
            'labels'      => maybe_serialize($labels),
            'args'        => maybe_serialize($args),
            'meta_fields' => maybe_serialize($meta_fields),
        );

        return $wpdb->insert($table, $data, array('%s', '%s', '%s', '%s', '%s'));
    }

    /**
     * Retorna configuração de meta fields para JetEngine
     *
     * @since  1.46.8
     * @return array
     */
    private function get_jet_meta_fields() {
        $p = Config\META_PREFIX;
        $base_id = 96000;

        $status_options = array(
            array('key' => 'paid', 'value' => 'Paid'),
            array('key' => 'partial', 'value' => 'Partial'),
            array('key' => 'pending', 'value' => 'Pending'),
            array('key' => 'free', 'value' => 'Free'),
            array('key' => 'failed', 'value' => 'Failed'),
            array('key' => 'refunded', 'value' => 'Refunded'),
        );

        return array(
            array('title' => 'Event ID', 'name' => $p.'event_id', 'object_type' => 'field', 'type' => 'text', 'width' => '50%', 'id' => $base_id++),
            array('title' => 'User ID', 'name' => $p.'user_id', 'object_type' => 'field', 'type' => 'text', 'width' => '50%', 'id' => $base_id++),
            array('title' => 'Attendee Name', 'name' => $p.'attendee_name', 'object_type' => 'field', 'type' => 'text', 'width' => '50%', 'id' => $base_id++),
            array('title' => 'Attendee Email', 'name' => $p.'attendee_email', 'object_type' => 'field', 'type' => 'text', 'width' => '50%', 'id' => $base_id++),
            array('title' => 'Registration Date', 'name' => $p.'registration_date', 'object_type' => 'field', 'type' => 'datetime-local', 'width' => '50%', 'id' => $base_id++),
            array('title' => 'Payment Status', 'name' => $p.'status', 'object_type' => 'field', 'type' => 'select', 'width' => '50%', 'id' => $base_id++, 'options' => $status_options),
            array('title' => 'Attended', 'name' => $p.'attended', 'object_type' => 'field', 'type' => 'switcher', 'width' => '50%', 'id' => $base_id++),
            array('title' => 'Notes', 'name' => $p.'notes', 'object_type' => 'field', 'type' => 'textarea', 'width' => '100%', 'id' => $base_id++),
        );
    }

}
