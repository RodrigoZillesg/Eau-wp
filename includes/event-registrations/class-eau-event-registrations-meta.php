<?php
/**
 * Event Registrations - Meta Fields Registration
 *
 * Registra meta fields para REST API e integração com JetEngine.
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
 * Class Eau_Event_Registrations_Meta
 *
 * Gerencia registro de meta fields e integração com JetEngine.
 *
 * @since 1.29.0
 */
class Eau_Event_Registrations_Meta {

    /**
     * Instância singleton
     *
     * @since 1.29.0
     * @var   Eau_Event_Registrations_Meta|null
     */
    private static $instance = null;

    /**
     * Retorna a instância singleton
     *
     * @since  1.29.0
     * @return Eau_Event_Registrations_Meta
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
     * @since 1.29.0
     */
    private function __construct() {
        add_action('init', array($this, 'register_meta'), 10);
        add_action('init', array($this, 'register_to_jet_engine'), 5);
        add_action('admin_init', array($this, 'handle_debug_request'));
    }

    /**
     * Handle debug/sync request via URL parameter
     *
     * @since  1.29.0
     * @return void
     */
    public function handle_debug_request() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Debug endpoint: /wp-admin/?eau_reg_debug=1
        if (isset($_GET['eau_reg_debug'])) {
            $this->show_debug();
            return;
        }

        // Force sync: /wp-admin/?eau_reg_sync=1
        if (isset($_GET['eau_reg_sync'])) {
            $result = $this->force_sync();
            add_action('admin_notices', function() use ($result) {
                $class = strpos($result, 'failed') !== false ? 'notice-error' : 'notice-success';
                echo '<div class="notice ' . $class . ' is-dismissible"><p><strong>Event Registrations JetEngine Sync:</strong> ' . esc_html($result) . '</p></div>';
            });
        }
    }

    /**
     * Show debug info
     *
     * @since 1.29.0
     */
    private function show_debug() {
        global $wpdb;
        $table = $wpdb->prefix . 'jet_post_types';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") == $table;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE slug = %s",
            Config\POST_TYPE
        ), ARRAY_A);

        add_action('admin_notices', function() use ($row, $table, $table_exists) {
            echo '<div class="notice notice-info"><pre style="max-height:500px;overflow:auto;">';
            echo '<strong>Event Registrations JetEngine Debug:</strong><br><br>';
            echo 'Table: ' . esc_html($table) . '<br>';
            echo 'Table exists: ' . ($table_exists ? 'YES' : 'NO') . '<br><br>';

            if (!$row) {
                echo 'No entry found for ' . Config\POST_TYPE . '<br>';
                echo '<a href="' . admin_url('?eau_reg_sync=1') . '" class="button">Force Sync Now</a>';
            } else {
                echo 'ID: ' . esc_html($row['id']) . '<br>';
                echo 'Slug: ' . esc_html($row['slug']) . '<br>';
                echo 'Status: ' . esc_html($row['status']) . '<br><br>';

                echo '<strong>Meta Fields (' . count(maybe_unserialize($row['meta_fields'])) . '):</strong><br>';
                $fields = maybe_unserialize($row['meta_fields']);
                foreach ($fields as $i => $field) {
                    echo "[$i] " . ($field['title'] ?? 'no title') . ' - ' . ($field['name'] ?? 'no name') . ' (' . ($field['type'] ?? 'unknown') . ')<br>';
                }
            }
            echo '</pre></div>';
        });
    }

    /**
     * Force sync to JetEngine
     *
     * @since  1.29.0
     * @return string Result message
     */
    private function force_sync() {
        global $wpdb;
        $table = $wpdb->prefix . 'jet_post_types';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            return 'JetEngine table not found';
        }

        // Delete existing and recreate
        if ($this->exists_in_jet_engine()) {
            $wpdb->delete($table, array('slug' => Config\POST_TYPE), array('%s'));
        }

        $result = $this->save_to_jet_engine();
        if ($result === false) {
            return 'Insert failed: ' . $wpdb->last_error;
        }

        delete_option('eau_event_reg_jet_version');
        return 'Synced successfully (ID: ' . $wpdb->insert_id . ')';
    }

    /**
     * Registra meta fields para REST API
     *
     * @since  1.29.0
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
     * Registra/atualiza CPT no JetEngine
     *
     * @since  1.29.0
     * @return void
     */
    public function register_to_jet_engine() {
        global $wpdb;
        $table = $wpdb->prefix . 'jet_post_types';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            return;
        }

        $version_key = 'eau_event_reg_jet_version';
        $current_version = Eau_Event_Registrations::VERSION;
        $saved_version = get_option($version_key);

        if ($this->exists_in_jet_engine()) {
            if ($saved_version !== $current_version) {
                $this->update_in_jet_engine();
                update_option($version_key, $current_version);
            }
            return;
        }

        $this->save_to_jet_engine();
        update_option($version_key, $current_version);
    }

    /**
     * Verifica se CPT existe na tabela JetEngine
     *
     * @since  1.29.0
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
     * @since  1.29.0
     * @return int|false
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
     * Atualiza configuração do CPT na tabela JetEngine
     *
     * @since  1.29.0
     * @return int|false
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
     * Remove CPT da tabela JetEngine
     *
     * @since  1.29.0
     * @return int|false
     */
    public static function remove_from_jet_engine() {
        global $wpdb;
        $table = $wpdb->prefix . 'jet_post_types';
        return $wpdb->delete($table, array('slug' => Config\POST_TYPE), array('%s'));
    }

    /**
     * Retorna labels para JetEngine
     *
     * @since  1.29.0
     * @return array
     */
    private function get_labels() {
        return array(
            'name'          => 'Event Registrations',
            'singular_name' => 'Registration',
            'menu_name'     => 'Registrations',
        );
    }

    /**
     * Retorna argumentos do CPT para JetEngine
     *
     * @since  1.29.0
     * @return array
     */
    private function get_args() {
        return array(
            'public'        => false,
            'has_archive'   => false,
            'show_in_rest'  => true,
            'show_in_menu'  => true,
            'menu_position' => 26,
            'menu_icon'     => 'dashicons-tickets-alt',
            'supports'      => array('title'),
            'rewrite'       => false,
            'rewrite_slug'  => '',
        );
    }

    /**
     * Retorna configuração de meta fields para JetEngine
     *
     * @since  1.29.0
     * @return array
     */
    private function get_jet_meta_fields() {
        $p = Config\META_PREFIX;
        $status_options = Config\to_jet_format(Config\get_status_options());
        $member_type_options = Config\to_jet_format(Config\get_member_type_options());

        // Base ID único para este CPT
        $base_id = 91000;

        return array(
            array(
                'title'       => 'Attendee Name',
                'name'        => $p . 'attendee_name',
                'object_type' => 'field',
                'type'        => 'text',
                'width'       => '50%',
                'is_required' => true,
                'id'          => $base_id++,
            ),
            array(
                'title'       => 'Attendee Email',
                'name'        => $p . 'attendee_email',
                'object_type' => 'field',
                'type'        => 'text',
                'width'       => '50%',
                'is_required' => true,
                'id'          => $base_id++,
            ),
            array(
                'title'       => 'Event',
                'name'        => $p . 'event_id',
                'object_type' => 'field',
                'type'        => 'posts',
                'post_type'   => 'eau_event',
                'is_multiple' => false,
                'is_required' => true,
                'width'       => '100%',
                'id'          => $base_id++,
            ),
            array(
                'title'         => 'Registration Date',
                'name'          => $p . 'registration_date',
                'object_type'   => 'field',
                'type'          => 'datetime-local',
                'width'         => '50%',
                'id'            => $base_id++,
            ),
            array(
                'title'       => 'Member Type',
                'name'        => $p . 'member_type',
                'object_type' => 'field',
                'type'        => 'select',
                'options'     => $member_type_options,
                'width'       => '50%',
                'id'          => $base_id++,
            ),
            array(
                'title'         => 'Status',
                'name'          => $p . 'status',
                'object_type'   => 'field',
                'type'          => 'select',
                'options'       => $status_options,
                'default_value' => Config\DEFAULT_STATUS,
                'width'         => '50%',
                'id'            => $base_id++,
            ),
        );
    }
}
