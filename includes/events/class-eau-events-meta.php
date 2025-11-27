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
        add_action('admin_init', array($this, 'handle_force_sync_request'));

        // One-time reset to force resync with new args (v1.31.5)
        $this->maybe_reset_jet_version();
    }

    /**
     * Reset jet version option to force resync (one-time fix)
     *
     * @since 1.31.5
     */
    private function maybe_reset_jet_version() {
        // v1317 - force complete resync
        $reset_key = 'eau_events_jet_args_reset_v1317';
        if (!get_option($reset_key)) {
            global $wpdb;
            $table = $wpdb->prefix . 'jet_post_types';

            // Delete existing entry completely
            if ($wpdb->get_var("SHOW TABLES LIKE '$table'") == $table) {
                $wpdb->delete($table, array('slug' => Config\POST_TYPE), array('%s'));
            }

            delete_option('eau_events_jet_version');
            update_option($reset_key, '1');
        }
    }

    /**
     * Handle force sync request via URL parameter
     *
     * Access: /wp-admin/?eau_force_jet_sync=1
     * Debug:  /wp-admin/?eau_jet_debug=1
     *
     * @since  1.28.5
     * @return void
     */
    public function handle_force_sync_request() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Debug endpoint
        if (isset($_GET['eau_jet_debug'])) {
            $this->show_jet_debug();
            return;
        }

        if (!isset($_GET['eau_force_jet_sync'])) {
            return;
        }

        $result = self::force_jet_engine_sync();

        add_action('admin_notices', function() use ($result) {
            $class = strpos($result, 'failed') !== false ? 'notice-error' : 'notice-success';
            echo '<div class="notice ' . $class . ' is-dismissible"><p><strong>Eau Events JetEngine Sync:</strong> ' . esc_html($result) . '</p></div>';
        });
    }

    /**
     * Show debug info for JetEngine integration
     *
     * @since 1.28.6
     */
    private function show_jet_debug() {
        global $wpdb;
        $table = $wpdb->prefix . 'jet_post_types';

        // Check table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") == $table;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE slug = %s",
            Config\POST_TYPE
        ), ARRAY_A);

        // Try to insert now and capture error
        $test_result = null;
        $test_error = null;
        if (!$row) {
            $test_data = array(
                'slug'        => Config\POST_TYPE,
                'status'      => 'built-in',
                'labels'      => maybe_serialize($this->get_labels()),
                'args'        => maybe_serialize($this->get_args()),
                'meta_fields' => maybe_serialize($this->get_jet_meta_fields()),
            );
            $test_result = $wpdb->insert($table, $test_data, array('%s', '%s', '%s', '%s', '%s'));
            $test_error = $wpdb->last_error;
            $insert_id = $wpdb->insert_id;

            // Re-fetch after insert
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table WHERE slug = %s",
                Config\POST_TYPE
            ), ARRAY_A);
        }

        add_action('admin_notices', function() use ($row, $table, $table_exists, $test_result, $test_error, $insert_id) {
            echo '<div class="notice notice-info"><pre style="max-height:500px;overflow:auto;">';
            echo '<strong>Eau Events JetEngine Debug:</strong><br><br>';

            echo 'Table: ' . esc_html($table) . '<br>';
            echo 'Table exists: ' . ($table_exists ? 'YES' : 'NO') . '<br>';
            echo 'JetEngine Version: ' . (defined('JET_ENGINE_VERSION') ? JET_ENGINE_VERSION : 'NOT DEFINED') . '<br><br>';

            if ($test_result !== null) {
                echo '<strong>Insert attempted:</strong><br>';
                echo 'Result: ' . ($test_result ? 'SUCCESS (ID: ' . $insert_id . ')' : 'FAILED') . '<br>';
                if ($test_error) {
                    echo 'Error: ' . esc_html($test_error) . '<br>';
                }
                echo '<br>';
            }

            if (!$row) {
                echo 'No entry found in wp_jet_post_types for eau_event';
            } else {
                echo 'ID: ' . esc_html($row['id']) . '<br>';
                echo 'Slug: ' . esc_html($row['slug']) . '<br>';
                echo 'Status: ' . esc_html($row['status']) . '<br><br>';

                echo '<strong>Labels:</strong><br>';
                print_r(maybe_unserialize($row['labels']));

                echo '<br><br><strong>Args:</strong><br>';
                print_r(maybe_unserialize($row['args']));

                echo '<br><br><strong>Meta Fields (' . count(maybe_unserialize($row['meta_fields'])) . '):</strong><br>';
                $fields = maybe_unserialize($row['meta_fields']);
                foreach ($fields as $i => $field) {
                    echo "[$i] " . ($field['title'] ?? 'no title') . ' - ' . ($field['name'] ?? 'no name') . ' (' . ($field['type'] ?? $field['object_type'] ?? 'unknown') . ')<br>';
                }
            }
            echo '</pre></div>';
        });
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
        // Check if JetEngine table exists (more reliable than checking constant)
        global $wpdb;
        $table = $wpdb->prefix . 'jet_post_types';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            return;
        }

        // Check if needs update based on version
        $version_key = 'eau_events_jet_version';
        $current_version = \EauSystem\Events\Eau_Events::VERSION;
        $saved_version = get_option($version_key);

        if ($this->exists_in_jet_engine()) {
            // Update if version changed - delete and recreate for clean update
            if ($saved_version !== $current_version) {
                // Delete old entry and recreate to ensure all args are correct
                $wpdb->delete($table, array('slug' => Config\POST_TYPE), array('%s'));
                $this->save_to_jet_engine();
                update_option($version_key, $current_version);
            }
            return;
        }

        $this->save_to_jet_engine();
        update_option($version_key, $current_version);
    }

    /**
     * Força registro/atualização no JetEngine (para debug/admin)
     *
     * Pode ser chamado via: add_action('admin_init', ['EauSystem\Events\Eau_Events_Meta', 'force_jet_engine_sync']);
     * Ou acessando: /wp-admin/?eau_force_jet_sync=1
     *
     * @since  1.28.4
     * @return bool|string True se sucesso, mensagem de erro caso contrário
     */
    public static function force_jet_engine_sync() {
        if (!defined('JET_ENGINE_VERSION')) {
            return 'JetEngine not installed';
        }

        global $wpdb;
        $table = $wpdb->prefix . 'jet_post_types';

        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            return 'JetEngine table not found: ' . $table;
        }

        $instance = self::get_instance();

        // Delete existing entry and recreate to ensure clean meta_fields
        if ($instance->exists_in_jet_engine()) {
            $wpdb->delete($table, array('slug' => Config\POST_TYPE), array('%s'));
        }

        $result = $instance->save_to_jet_engine();
        if ($result === false) {
            return 'Insert failed: ' . $wpdb->last_error;
        }

        // Clear version option to prevent immediate re-sync
        delete_option('eau_events_jet_version');

        return 'Synced successfully (ID: ' . $wpdb->insert_id . ')';
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

        // Use 'publish' status so JetEngine manages the CPT completely
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
     * Atualiza configuração do CPT na tabela JetEngine
     *
     * @since  1.28.2
     * @return int|false Número de linhas atualizadas ou false em caso de erro
     */
    private function update_in_jet_engine() {
        global $wpdb;
        $table = $wpdb->prefix . 'jet_post_types';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            return false;
        }

        $data = array(
            'labels'      => maybe_serialize($this->get_labels()),
            'args'        => maybe_serialize($this->get_args()),
            'meta_fields' => maybe_serialize($this->get_jet_meta_fields()),
        );

        return $wpdb->update(
            $table,
            $data,
            array('slug' => Config\POST_TYPE),
            array('%s', '%s', '%s'),
            array('%s')
        );
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
            'public'              => true,
            'publicly_queryable'  => true,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'show_in_admin_bar'   => true,
            'show_in_nav_menus'   => true,
            'query_var'           => true,
            'has_archive'         => true,
            'hierarchical'        => false,
            'show_in_rest'        => false, // Disable Gutenberg - use classic editor for our metabox
            'menu_position'       => 25,
            'menu_icon'           => 'dashicons-calendar-alt',
            'capability_type'     => 'post',
            'map_meta_cap'        => true,
            'supports'            => array('title', 'thumbnail'),
            'rewrite'             => true,
            'rewrite_slug'        => 'events',
            'with_front'          => false,
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

        // Base ID for fields - using fixed IDs to avoid regeneration on each save
        $base_id = 90000;

        // Simplified format without tabs to avoid JetEngine UI issues
        $fields = array(
            // Basic Info fields
            array('title' => 'Short Description', 'name' => $p.'short_description', 'object_type' => 'field', 'type' => 'text', 'width' => '100%', 'id' => $base_id++),
            array('title' => 'Full Description', 'name' => $p.'full_description', 'object_type' => 'field', 'type' => 'wysiwyg', 'width' => '100%', 'id' => $base_id++),
            array('title' => 'Start Date & Time', 'name' => $p.'start_datetime', 'object_type' => 'field', 'type' => 'datetime-local', 'width' => '50%', 'is_required' => true, 'id' => $base_id++),
            array('title' => 'End Date & Time', 'name' => $p.'end_datetime', 'object_type' => 'field', 'type' => 'datetime-local', 'width' => '50%', 'is_required' => true, 'id' => $base_id++),
            array('title' => 'Timezone', 'name' => $p.'timezone', 'object_type' => 'field', 'type' => 'select', 'options' => $tz, 'default_value' => Config\DEFAULT_TIMEZONE, 'id' => $base_id++),
            array('title' => 'Event Image', 'name' => $p.'image_id', 'object_type' => 'field', 'type' => 'media', 'value_format' => 'id', 'id' => $base_id++),

            // Location fields
            array('title' => 'Event Type', 'name' => $p.'event_type', 'object_type' => 'field', 'type' => 'radio', 'options' => $et, 'default_value' => Config\DEFAULT_EVENT_TYPE, 'id' => $base_id++),
            array('title' => 'Venue Name', 'name' => $p.'venue_name', 'object_type' => 'field', 'type' => 'text', 'width' => '100%', 'id' => $base_id++),
            array('title' => 'Address', 'name' => $p.'address', 'object_type' => 'field', 'type' => 'text', 'width' => '100%', 'id' => $base_id++),
            array('title' => 'City', 'name' => $p.'city', 'object_type' => 'field', 'type' => 'text', 'width' => '25%', 'id' => $base_id++),
            array('title' => 'State', 'name' => $p.'state', 'object_type' => 'field', 'type' => 'text', 'width' => '25%', 'id' => $base_id++),
            array('title' => 'Postal Code', 'name' => $p.'postal_code', 'object_type' => 'field', 'type' => 'text', 'width' => '25%', 'id' => $base_id++),
            array('title' => 'Country', 'name' => $p.'country', 'object_type' => 'field', 'type' => 'select', 'width' => '25%', 'options' => $ct, 'default_value' => Config\DEFAULT_COUNTRY, 'id' => $base_id++),
            array('title' => 'Virtual URL', 'name' => $p.'virtual_url', 'object_type' => 'field', 'type' => 'text', 'width' => '100%', 'id' => $base_id++),

            // Pricing fields
            array('title' => 'Capacity', 'name' => $p.'capacity', 'object_type' => 'field', 'type' => 'number', 'min_value' => 0, 'width' => '100%', 'id' => $base_id++),
            array('title' => 'Price ($)', 'name' => $p.'member_price', 'object_type' => 'field', 'type' => 'number', 'width' => '50%', 'min_value' => 0, 'step_value' => 0.01, 'id' => $base_id++),
            array('title' => 'Early Bird Price ($)', 'name' => $p.'early_bird_price', 'object_type' => 'field', 'type' => 'number', 'width' => '50%', 'min_value' => 0, 'step_value' => 0.01, 'id' => $base_id++),
            array('title' => 'Early Bird End Date', 'name' => $p.'early_bird_end_date', 'object_type' => 'field', 'type' => 'datetime-local', 'width' => '50%', 'id' => $base_id++),

            // Settings fields
            array('title' => 'CPD Points', 'name' => $p.'cpd_points', 'object_type' => 'field', 'type' => 'number', 'width' => '50%', 'min_value' => 0, 'step_value' => 0.5, 'id' => $base_id++),
            array('title' => 'CPD Category ID', 'name' => $p.'cpd_category', 'object_type' => 'field', 'type' => 'number', 'width' => '50%', 'min_value' => 0, 'description' => 'ID from eau_activity_categories table', 'id' => $base_id++),
            array('title' => 'Visibility', 'name' => $p.'visibility', 'object_type' => 'field', 'type' => 'select', 'options' => $vs, 'default_value' => Config\DEFAULT_VISIBILITY, 'id' => $base_id++),
            array('title' => 'Require Approval', 'name' => $p.'require_approval', 'object_type' => 'field', 'type' => 'switcher', 'width' => '100%', 'id' => $base_id++),
        );

        return $fields;
    }
}
