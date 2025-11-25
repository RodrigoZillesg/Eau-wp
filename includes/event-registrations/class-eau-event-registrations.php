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
    const VERSION = '1.29.0';

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
        require_once $base . 'class-eau-event-registrations-meta.php';

        // Admin
        require_once $base . 'admin/class-eau-event-registrations-admin.php';
        require_once $base . 'admin/class-eau-event-registrations-ajax.php';
    }

    /**
     * Inicializa os componentes do módulo
     *
     * @since  1.29.0
     * @return void
     */
    private function init() {
        Eau_Event_Registrations_CPT::get_instance();
        Eau_Event_Registrations_Meta::get_instance();

        // Admin
        Admin\Eau_Event_Registrations_Admin::get_instance();
        Admin\Eau_Event_Registrations_Ajax::register_handlers();

        // Flush rewrite rules se versão mudou
        $this->maybe_flush_rewrite_rules();
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
        Eau_Event_Registrations_Meta::remove_from_jet_engine();
    }
}
