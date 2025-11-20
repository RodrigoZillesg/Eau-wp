<?php
namespace EauSystem;

/**
 * Classe principal do plugin Eau System
 */
class Eau_System {

    protected $loader;
    protected $plugin_name;
    protected $version;

    public function __construct() {
        $this->version = EAU_SYSTEM_VERSION;
        $this->plugin_name = 'eau-system';

        $this->load_dependencies();
        $this->define_admin_hooks();
    }

    private function load_dependencies() {
        require_once EAU_SYSTEM_PLUGIN_DIR . 'includes/class-eau-admin.php';
        require_once EAU_SYSTEM_PLUGIN_DIR . 'includes/class-eau-csv-handler.php';
        require_once EAU_SYSTEM_PLUGIN_DIR . 'includes/class-eau-post-type-creator.php';
        require_once EAU_SYSTEM_PLUGIN_DIR . 'includes/class-eau-woocommerce-compat.php';
    }

    private function define_admin_hooks() {
        $admin = new Eau_Admin($this->get_plugin_name(), $this->get_version());

        add_action('admin_menu', array($admin, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($admin, 'enqueue_styles'));
        add_action('admin_enqueue_scripts', array($admin, 'enqueue_scripts'));
    }

    public function run() {
        // Plugin está rodando
    }

    public function get_plugin_name() {
        return $this->plugin_name;
    }

    public function get_version() {
        return $this->version;
    }
}
