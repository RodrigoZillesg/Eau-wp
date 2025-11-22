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
        $this->define_frontend_hooks();
    }

    private function load_dependencies() {
        require_once EAU_SYSTEM_PLUGIN_DIR . 'includes/class-eau-admin.php';
        require_once EAU_SYSTEM_PLUGIN_DIR . 'includes/class-eau-csv-handler.php';
        require_once EAU_SYSTEM_PLUGIN_DIR . 'includes/class-eau-post-type-creator.php';
        require_once EAU_SYSTEM_PLUGIN_DIR . 'includes/class-eau-importer.php';
        require_once EAU_SYSTEM_PLUGIN_DIR . 'includes/class-eau-user-importer.php';
        require_once EAU_SYSTEM_PLUGIN_DIR . 'includes/class-eau-user-meta-creator.php';
        require_once EAU_SYSTEM_PLUGIN_DIR . 'includes/class-eau-roles.php';
        require_once EAU_SYSTEM_PLUGIN_DIR . 'includes/class-eau-woocommerce-compat.php';
        require_once EAU_SYSTEM_PLUGIN_DIR . 'includes/class-eau-dashboard.php';

        // Members Management (v1.9.0)
        require_once EAU_SYSTEM_PLUGIN_DIR . 'includes/class-eau-user-institution-helper.php';
        require_once EAU_SYSTEM_PLUGIN_DIR . 'includes/components/class-eau-stats-cards.php';
        require_once EAU_SYSTEM_PLUGIN_DIR . 'includes/components/class-eau-data-table.php';
        require_once EAU_SYSTEM_PLUGIN_DIR . 'includes/components/class-eau-pagination.php';
        require_once EAU_SYSTEM_PLUGIN_DIR . 'includes/components/class-eau-filters.php';
        require_once EAU_SYSTEM_PLUGIN_DIR . 'includes/components/class-eau-modal.php';
        require_once EAU_SYSTEM_PLUGIN_DIR . 'includes/class-eau-members-management.php';
        require_once EAU_SYSTEM_PLUGIN_DIR . 'includes/class-eau-members-settings.php';
        require_once EAU_SYSTEM_PLUGIN_DIR . 'ajax/class-eau-members-ajax.php';

        // Duplicate Manager (v1.18.0)
        require_once EAU_SYSTEM_PLUGIN_DIR . 'includes/class-eau-duplicate-database.php';
        require_once EAU_SYSTEM_PLUGIN_DIR . 'includes/class-eau-duplicate-scanner.php';
        require_once EAU_SYSTEM_PLUGIN_DIR . 'includes/class-eau-duplicate-manager.php';
        require_once EAU_SYSTEM_PLUGIN_DIR . 'ajax/class-eau-duplicate-ajax.php';

        // Documentation (v1.18.1)
        require_once EAU_SYSTEM_PLUGIN_DIR . 'includes/class-eau-documentation.php';
    }

    private function define_admin_hooks() {
        $admin = new Eau_Admin($this->get_plugin_name(), $this->get_version());

        add_action('admin_menu', array($admin, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($admin, 'enqueue_styles'));
        add_action('admin_enqueue_scripts', array($admin, 'enqueue_scripts'));

        // Documentation pages
        add_action('admin_menu', array('EauSystem\Eau_Documentation', 'register_admin_pages'));
        add_action('admin_enqueue_scripts', array('EauSystem\Eau_Documentation', 'enqueue_admin_assets'));
    }

    private function define_frontend_hooks() {
        // Enfileira assets do dashboard no frontend
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
    }

    public function enqueue_frontend_assets() {
        // CSS do Dashboard
        wp_enqueue_style(
            'eau-dashboard',
            EAU_SYSTEM_PLUGIN_URL . 'assets/css/eau-dashboard.css',
            array(),
            $this->version,
            'all'
        );

        // Lucide Icons (leve, apenas ~50KB)
        wp_enqueue_script(
            'lucide-icons',
            'https://unpkg.com/lucide@latest/dist/umd/lucide.min.js',
            array(),
            null,
            true
        );
    }

    public function run() {
        // Registra shortcodes
        Eau_Dashboard::register_shortcode();
        Eau_Members_Management::register_shortcode();
        Eau_Duplicate_Manager::register_shortcode();

        // Registra AJAX handlers
        \EauSystem\Ajax\Eau_Members_Ajax::register_handlers();
        Eau_Duplicate_Ajax::register_endpoints();

        // Registra hooks do Duplicate Scanner (WP Cron)
        Eau_Duplicate_Scanner::register_hooks();

        // Inicializa Members Settings
        Eau_Members_Settings::init();

        // Garante que tabelas de duplicatas existem
        if (!Eau_Duplicate_Database::tables_exist()) {
            Eau_Duplicate_Database::create_tables();
        }
    }

    public function get_plugin_name() {
        return $this->plugin_name;
    }

    public function get_version() {
        return $this->version;
    }
}
