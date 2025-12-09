<?php
/**
 * Eau Events - Bootstrap
 *
 * Carrega e inicializa o módulo de Events.
 *
 * @package    EauSystem
 * @subpackage Events
 * @since      1.28.0
 */

namespace EauSystem\Events;

if (!defined('WPINC')) {
    die;
}

/**
 * Class Eau_Events
 *
 * Classe principal do módulo Events.
 * Responsável por carregar dependências e inicializar componentes.
 *
 * @since 1.28.0
 */
class Eau_Events {

    /**
     * Versão do módulo
     *
     * @var string
     */
    const VERSION = '1.31.8';

    /**
     * Instância singleton
     *
     * @var Eau_Events|null
     */
    private static $instance = null;

    /**
     * Retorna a instância singleton
     *
     * @since  1.28.0
     * @return Eau_Events
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
        $this->load_dependencies();
        $this->init();
    }

    /**
     * Carrega arquivos de dependência
     *
     * @since  1.28.0
     * @return void
     */
    private function load_dependencies() {
        $base = EAU_SYSTEM_PLUGIN_DIR . 'includes/events/';

        // Config
        require_once $base . 'config/constants.php';
        require_once $base . 'config/meta-fields.php';
        require_once $base . 'config/options.php';

        // Core
        require_once $base . 'class-eau-events-cpt.php';
        require_once $base . 'class-eau-events-meta.php';

        // Admin metaboxes - always load (JetEngine meta fields may not work for built-in CPTs)
        require_once $base . 'admin/class-eau-events-admin.php';
        require_once $base . 'admin/class-eau-events-metabox.php';
        require_once $base . 'admin/tabs/tab-basic-info.php';
        require_once $base . 'admin/tabs/tab-location.php';
        require_once $base . 'admin/tabs/tab-pricing.php';
        require_once $base . 'admin/tabs/tab-settings.php';

        // Frontend
        require_once $base . 'frontend/class-eau-events-helper.php';
        require_once $base . 'frontend/class-eau-events-templates.php';
        require_once $base . 'frontend/class-eau-events-assets.php';

        // Events Management (Dashboard)
        require_once $base . 'admin/class-eau-events-management.php';
        require_once $base . 'admin/class-eau-events-management-ajax.php';
    }

    /**
     * Inicializa os componentes do módulo
     *
     * @since  1.28.0
     * @return void
     */
    private function init() {
        Eau_Events_CPT::get_instance();
        Eau_Events_Meta::get_instance();

        // Always init admin metaboxes
        Admin\Eau_Events_Admin::get_instance();

        Frontend\Eau_Events_Templates::get_instance();
        Frontend\Eau_Events_Assets::get_instance();

        // Events Management (shortcode e AJAX)
        Admin\Eau_Events_Management::register_shortcode();
        Admin\Eau_Events_Management_Ajax::register_handlers();

        // Frontend Shortcodes (single e archive)
        Frontend\Eau_Events_Shortcodes::register();

        // Flush rewrite rules se versão mudou
        $this->maybe_flush_rewrite_rules();
    }

    /**
     * Flush rewrite rules se versão do módulo mudou
     *
     * @since  1.28.1
     * @return void
     */
    private function maybe_flush_rewrite_rules() {
        $option_key = 'eau_events_version';
        $saved_version = get_option($option_key);

        if ($saved_version !== self::VERSION) {
            add_action('init', function() {
                flush_rewrite_rules();
            }, 99);
            update_option($option_key, self::VERSION);
        }
    }

    /**
     * Executa na ativação do plugin
     *
     * @since  1.28.0
     * @return void
     */
    public static function activate() {
        Eau_Events_CPT::get_instance()->register_post_type();
        Eau_Events_Meta::get_instance()->register_to_jet_engine();
        flush_rewrite_rules();
    }

    /**
     * Executa na desinstalação do plugin
     *
     * @since  1.28.0
     * @return void
     */
    public static function uninstall() {
        Eau_Events_Meta::remove_from_jet_engine();
    }
}
