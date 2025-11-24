<?php
/**
 * Events - Meta Fields Registration
 *
 * Registra meta fields para REST API e integração com JetEngine.
 * Salva configuração na tabela wp_jet_post_types quando JetEngine está ativo.
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
 * Class Eau_Events_Meta
 *
 * Gerencia registro de meta fields e integração com JetEngine.
 *
 * @since 1.28.0
 */
class Eau_Events_Meta {

    /**
     * Instância singleton
     *
     * @since 1.28.0
     * @var   Eau_Events_Meta|null
     */
    private static $instance = null;

    /**
     * Retorna a instância singleton
     *
     * @since  1.28.0
     * @return Eau_Events_Meta
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
        add_action('init', array($this, 'register_meta'), 10);
        add_action('init', array($this, 'register_to_jet_engine'), 5);
    }

    /**
     * Registra meta fields para REST API
     *
     * @since  1.28.0
     * @return void
     */
    public function register_meta() {
        $fields = Config\get_meta_fields();

        foreach ($fields as $field => $type) {
            register_post_meta(Config\POST_TYPE, Config\META_PREFIX . $field, array(
                'type'              => $type,
                'single'            => true,
                'show_in_rest'      => true,
                'sanitize_callback' => Config\get_sanitize_callback($type),
                'auth_callback'     => function() {
                    return current_user_can('edit_posts');
                },
            ));
        }
    }

    /**
     * Registra CPT no JetEngine se disponível
     *
     * Verifica se JetEngine está ativo e se o CPT ainda não existe
     * na tabela wp_jet_post_types antes de salvar.
     *
     * @since  1.28.0
     * @return void
     */
    public function register_to_jet_engine() {
        if (!defined('JET_ENGINE_VERSION')) {
            return;
        }

        if ($this->exists_in_jet_engine()) {
            return;
        }

        $this->save_to_jet_engine();
    }

    /**
     * Verifica se CPT existe na tabela JetEngine
     *
     * @since  1.28.0
     * @return bool True se existe, false caso contrário
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
     * @since  1.28.0
     * @return int|false Número de linhas inseridas ou false em caso de erro
     */
    private function save_to_jet_engine() {
        global $wpdb;
        $table = $wpdb->prefix . 'jet_post_types';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            return false;
        }

        $data = array(
            'slug'        => Config\POST_TYPE,
            'status'      => 'publish',
            'labels'      => maybe_serialize($this->get_labels()),
            'args'        => maybe_serialize($this->get_args()),
            'meta_fields' => maybe_serialize($this->get_jet_meta_fields()),
        );

        return $wpdb->insert($table, $data, array('%s', '%s', '%s', '%s', '%s'));
    }

    /**
     * Remove CPT da tabela JetEngine (usado na desinstalação)
     *
     * @since  1.28.0
     * @return int|false Número de linhas deletadas ou false em caso de erro
     */
    public static function remove_from_jet_engine() {
        global $wpdb;
        $table = $wpdb->prefix . 'jet_post_types';
        $wpdb->delete($table, array('slug' => Config\POST_TYPE), array('%s'));
    }

    /**
     * Retorna labels para JetEngine
     *
     * @since  1.28.0
     * @return array Labels do CPT
     */
    private function get_labels() {
        return array(
            'name'          => 'Events',
            'singular_name' => 'Event',
            'menu_name'     => 'Events',
        );
    }

    /**
     * Retorna argumentos do CPT para JetEngine
     *
     * @since  1.28.0
     * @return array Argumentos de registro do CPT
     */
    private function get_args() {
        return array(
            'public'       => true,
            'has_archive'  => true,
            'show_in_rest' => true,
            'menu_icon'    => 'dashicons-calendar-alt',
            'supports'     => array('title', 'thumbnail'),
            'rewrite'      => array('slug' => 'events'),
        );
    }

    /**
     * Retorna configuração de meta fields para JetEngine
     *
     * Inclui tabs e campos organizados em 4 seções:
     * - Basic Info: descrição, datas, timezone, imagem
     * - Location: tipo, venue, endereço, URL virtual
     * - Pricing: capacidade, preços, early bird, guests
     * - Settings: CPD, visibilidade, aprovação
     *
     * @since  1.28.0
     * @return array Configuração de meta fields no formato JetEngine
     */
    private function get_jet_meta_fields() {
        $p = Config\META_PREFIX;
        $tz = Config\to_jet_format(Config\get_timezones());
        $ct = Config\to_jet_format(Config\get_countries());
        $et = Config\to_jet_format(Config\get_event_types());
        $vs = Config\to_jet_format(Config\get_visibility_options());

        return array(
            // Tab: Basic Info
            array('title' => 'Basic Info', 'name' => 'tab_basic', 'object_type' => 'tab'),
            array('title' => 'Short Description', 'name' => $p.'short_description', 'object_type' => 'field', 'type' => 'text', 'width' => '100%'),
            array('title' => 'Full Description', 'name' => $p.'full_description', 'object_type' => 'field', 'type' => 'wysiwyg', 'width' => '100%'),
            array('title' => 'Start Date & Time', 'name' => $p.'start_datetime', 'object_type' => 'field', 'type' => 'datetime-local', 'width' => '50%', 'is_required' => true),
            array('title' => 'End Date & Time', 'name' => $p.'end_datetime', 'object_type' => 'field', 'type' => 'datetime-local', 'width' => '50%', 'is_required' => true),
            array('title' => 'Timezone', 'name' => $p.'timezone', 'object_type' => 'field', 'type' => 'select', 'options' => $tz, 'default_value' => Config\DEFAULT_TIMEZONE),
            array('title' => 'Event Image', 'name' => $p.'image_id', 'object_type' => 'field', 'type' => 'media', 'value_format' => 'id'),

            // Tab: Location
            array('title' => 'Location', 'name' => 'tab_location', 'object_type' => 'tab'),
            array('title' => 'Event Type', 'name' => $p.'event_type', 'object_type' => 'field', 'type' => 'radio', 'options' => $et, 'default_value' => Config\DEFAULT_EVENT_TYPE),
            array('title' => 'Venue Name', 'name' => $p.'venue_name', 'object_type' => 'field', 'type' => 'text', 'width' => '100%'),
            array('title' => 'Address', 'name' => $p.'address', 'object_type' => 'field', 'type' => 'text', 'width' => '100%'),
            array('title' => 'City', 'name' => $p.'city', 'object_type' => 'field', 'type' => 'text', 'width' => '25%'),
            array('title' => 'State', 'name' => $p.'state', 'object_type' => 'field', 'type' => 'text', 'width' => '25%'),
            array('title' => 'Postal Code', 'name' => $p.'postal_code', 'object_type' => 'field', 'type' => 'text', 'width' => '25%'),
            array('title' => 'Country', 'name' => $p.'country', 'object_type' => 'field', 'type' => 'select', 'width' => '25%', 'options' => $ct, 'default_value' => Config\DEFAULT_COUNTRY),
            array('title' => 'Virtual URL', 'name' => $p.'virtual_url', 'object_type' => 'field', 'type' => 'text', 'width' => '100%'),

            // Tab: Pricing
            array('title' => 'Capacity & Pricing', 'name' => 'tab_pricing', 'object_type' => 'tab'),
            array('title' => 'Capacity', 'name' => $p.'capacity', 'object_type' => 'field', 'type' => 'number', 'min_value' => 0),
            array('title' => 'Member Price ($)', 'name' => $p.'member_price', 'object_type' => 'field', 'type' => 'number', 'width' => '50%', 'min_value' => 0, 'step_value' => 0.01),
            array('title' => 'Non-Member Price ($)', 'name' => $p.'non_member_price', 'object_type' => 'field', 'type' => 'number', 'width' => '50%', 'min_value' => 0, 'step_value' => 0.01),
            array('title' => 'Early Bird Price ($)', 'name' => $p.'early_bird_price', 'object_type' => 'field', 'type' => 'number', 'width' => '50%', 'min_value' => 0, 'step_value' => 0.01),
            array('title' => 'Early Bird End Date', 'name' => $p.'early_bird_end_date', 'object_type' => 'field', 'type' => 'datetime-local', 'width' => '50%'),
            array('title' => 'Allow Guests', 'name' => $p.'allow_guests', 'object_type' => 'field', 'type' => 'switcher', 'width' => '50%'),
            array('title' => 'Max Guests', 'name' => $p.'max_guests', 'object_type' => 'field', 'type' => 'number', 'width' => '50%', 'min_value' => 1, 'max_value' => 10),

            // Tab: Settings
            array('title' => 'CPD & Settings', 'name' => 'tab_settings', 'object_type' => 'tab'),
            array('title' => 'CPD Points', 'name' => $p.'cpd_points', 'object_type' => 'field', 'type' => 'number', 'width' => '50%', 'min_value' => 0, 'step_value' => 0.5),
            array('title' => 'CPD Category', 'name' => $p.'cpd_category', 'object_type' => 'field', 'type' => 'select', 'width' => '50%', 'options_from' => 'terms', 'options_tax' => Config\TAXONOMY),
            array('title' => 'Visibility', 'name' => $p.'visibility', 'object_type' => 'field', 'type' => 'select', 'options' => $vs, 'default_value' => Config\DEFAULT_VISIBILITY),
            array('title' => 'Require Approval', 'name' => $p.'require_approval', 'object_type' => 'field', 'type' => 'switcher'),
            array('title' => 'Members Only', 'name' => $p.'members_only', 'object_type' => 'field', 'type' => 'switcher', 'default_value' => true),
        );
    }
}
