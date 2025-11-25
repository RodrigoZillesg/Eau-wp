<?php
/**
 * Event Registrations - Bootstrap
 *
 * Carrega e inicializa o módulo de Event Registrations.
 *
 * @package    EauSystem
 * @subpackage EventRegistrations
 * @since      1.29.0
 */

namespace EauSystem\EventRegistrations;

if (!defined('WPINC')) {
    die;
}

/**
 * Class Eau_Event_Registrations
 *
 * Classe principal do módulo Event Registrations.
 *
 * @since 1.29.0
 */
class Eau_Event_Registrations {

    /**
     * Versão do módulo
     *
     * @var string
     */
    const VERSION = '1.29.2';

    /**
     * Instância singleton
     *
     * @var Eau_Event_Registrations|null
     */
    private static $instance = null;

    /**
     * Retorna a instância singleton
     *
     * @since  1.29.0
     * @return Eau_Event_Registrations
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
        $this->load_dependencies();
        $this->init();
    }

    /**
     * Carrega arquivos de dependência
     *
     * @since  1.29.0
     * @return void
     */
    private function load_dependencies() {
        $base = EAU_SYSTEM_PLUGIN_DIR . 'includes/event-registrations/';

        // Config
        require_once $base . 'config/constants.php';
        require_once $base . 'config/meta-fields.php';
        require_once $base . 'config/options.php';

        // Core
        require_once $base . 'class-eau-event-registrations-cpt.php';

        // Admin
        require_once $base . 'admin/class-eau-event-registrations-metabox.php';

        // Frontend
        require_once $base . 'frontend/class-eau-event-registrations-ajax.php';
    }

    /**
     * Inicializa os componentes do módulo
     *
     * @since  1.29.0
     * @return void
     */
    private function init() {
        // Remove do JetEngine se existir (evita conflito)
        $this->remove_from_jet_engine();

        Eau_Event_Registrations_CPT::get_instance();

        // Apenas registra meta fields para REST API, não sincroniza com JetEngine
        add_action('init', array($this, 'register_meta_fields'), 10);

        // Admin
        Admin\Eau_Event_Registrations_Metabox::get_instance();

        // Frontend AJAX
        Frontend\Eau_Event_Registrations_Ajax::register_handlers();

        // Flush rewrite rules se versão mudou
        $this->maybe_flush_rewrite_rules();
    }

    /**
     * Remove CPT do JetEngine para evitar conflito
     *
     * @since  1.29.3
     * @return void
     */
    private function remove_from_jet_engine() {
        global $wpdb;
        $table = $wpdb->prefix . 'jet_post_types';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            return;
        }

        // Verifica se existe e deleta
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE slug = %s",
            Config\POST_TYPE
        ));

        if ($exists) {
            $wpdb->delete($table, array('slug' => Config\POST_TYPE), array('%s'));
        }
    }

    /**
     * Registra meta fields para REST API
     *
     * @since  1.29.3
     * @return void
     */
    public function register_meta_fields() {
        $prefix = Config\META_PREFIX;
        $fields = Config\get_meta_fields();

        foreach ($fields as $field => $type) {
            register_post_meta(Config\POST_TYPE, $prefix . $field, array(
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
     * Flush rewrite rules se versão mudou
     *
     * @since  1.29.0
     * @return void
     */
    private function maybe_flush_rewrite_rules() {
        $option_key = 'eau_event_reg_version';
        $saved_version = get_option($option_key);

        if ($saved_version !== self::VERSION) {
            add_action('init', function() {
                flush_rewrite_rules();
            }, 99);
            update_option($option_key, self::VERSION);
        }
    }

    /**
     * Executa na desinstalação
     *
     * @since  1.29.0
     * @return void
     */
    public static function uninstall() {
        // Limpa dados se necessário
        global $wpdb;
        $table = $wpdb->prefix . 'jet_post_types';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") == $table) {
            $wpdb->delete($table, array('slug' => Config\POST_TYPE), array('%s'));
        }
    }
}
