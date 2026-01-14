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
        // Shared helpers (must load first)
        require_once EAU_SYSTEM_PLUGIN_DIR . 'includes/shared/helpers.php';

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

        // Institutions Management (v1.28.0)
        require_once EAU_SYSTEM_PLUGIN_DIR . 'includes/class-eau-institutions-management.php';
        require_once EAU_SYSTEM_PLUGIN_DIR . 'ajax/class-eau-institutions-ajax.php';

        // Activities Management (v1.29.0)
        require_once EAU_SYSTEM_PLUGIN_DIR . 'includes/class-eau-activities-management.php';
        require_once EAU_SYSTEM_PLUGIN_DIR . 'ajax/class-eau-activities-ajax.php';

        // Categories Management (v1.31.0)
        require_once EAU_SYSTEM_PLUGIN_DIR . 'includes/class-eau-categories-database.php';
        require_once EAU_SYSTEM_PLUGIN_DIR . 'includes/class-eau-categories-management.php';
        require_once EAU_SYSTEM_PLUGIN_DIR . 'includes/class-eau-categories-ajax.php';

        // Event Categories Management (v1.61.0)
        require_once EAU_SYSTEM_PLUGIN_DIR . 'includes/class-eau-event-categories-database.php';
        require_once EAU_SYSTEM_PLUGIN_DIR . 'includes/class-eau-event-categories-ajax.php';

        // My CPDs (v1.37.0)
        require_once EAU_SYSTEM_PLUGIN_DIR . 'includes/class-eau-my-cpds.php';
        require_once EAU_SYSTEM_PLUGIN_DIR . 'ajax/class-eau-my-cpds-ajax.php';

        // Settings (v1.39.0)
        require_once EAU_SYSTEM_PLUGIN_DIR . 'includes/class-eau-settings.php';
        require_once EAU_SYSTEM_PLUGIN_DIR . 'ajax/class-eau-settings-ajax.php';

        // My Profile (v1.40.0)
        require_once EAU_SYSTEM_PLUGIN_DIR . 'includes/class-eau-my-profile.php';
        require_once EAU_SYSTEM_PLUGIN_DIR . 'ajax/class-eau-my-profile-ajax.php';

        // Duplicate Manager (v1.18.0)
        require_once EAU_SYSTEM_PLUGIN_DIR . 'includes/class-eau-duplicate-database.php';
        require_once EAU_SYSTEM_PLUGIN_DIR . 'includes/class-eau-duplicate-scanner.php';
        require_once EAU_SYSTEM_PLUGIN_DIR . 'includes/class-eau-duplicate-manager.php';
        require_once EAU_SYSTEM_PLUGIN_DIR . 'ajax/class-eau-duplicate-ajax.php';

        // Documentation (v1.18.1)
        require_once EAU_SYSTEM_PLUGIN_DIR . 'includes/class-eau-documentation.php';

        // Events Module (v1.28.0)
        require_once EAU_SYSTEM_PLUGIN_DIR . 'includes/events/class-eau-events.php';

        // Event Registrations CPT (v1.29.0)
        require_once EAU_SYSTEM_PLUGIN_DIR . 'includes/event-registrations/class-eau-event-registrations.php';

        // OpenLearning Integration (v1.41.0)
        require_once EAU_SYSTEM_PLUGIN_DIR . 'includes/class-eau-openlearning-database.php';
        require_once EAU_SYSTEM_PLUGIN_DIR . 'includes/class-eau-openlearning-post-type.php';
        require_once EAU_SYSTEM_PLUGIN_DIR . 'includes/class-eau-openlearning-service.php';
        require_once EAU_SYSTEM_PLUGIN_DIR . 'includes/class-eau-openlearning-courses.php';
        require_once EAU_SYSTEM_PLUGIN_DIR . 'ajax/class-eau-openlearning-ajax.php';

        // OpenLearning Management (v1.43.0)
        require_once EAU_SYSTEM_PLUGIN_DIR . 'includes/class-eau-openlearning-management.php';
        require_once EAU_SYSTEM_PLUGIN_DIR . 'ajax/class-eau-openlearning-management-ajax.php';

        // Email Service (v1.44.0)
        require_once EAU_SYSTEM_PLUGIN_DIR . 'includes/email/class-email-config.php';
        require_once EAU_SYSTEM_PLUGIN_DIR . 'includes/email/class-email-template.php';
        require_once EAU_SYSTEM_PLUGIN_DIR . 'includes/email/class-email-settings.php';
        require_once EAU_SYSTEM_PLUGIN_DIR . 'includes/email/class-email-service.php';
        require_once EAU_SYSTEM_PLUGIN_DIR . 'includes/email/class-email-events.php';
        require_once EAU_SYSTEM_PLUGIN_DIR . 'includes/email/class-email-membership.php';
        // My Institution (v1.44.0)
        require_once EAU_SYSTEM_PLUGIN_DIR . 'includes/class-eau-institution-requests-database.php';
        require_once EAU_SYSTEM_PLUGIN_DIR . 'includes/class-eau-my-institution.php';
        require_once EAU_SYSTEM_PLUGIN_DIR . 'ajax/class-eau-my-institution-ajax.php';

        // Payments System (v1.45.0)
        require_once EAU_SYSTEM_PLUGIN_DIR . 'includes/payments/class-payments-post-type.php';
        require_once EAU_SYSTEM_PLUGIN_DIR . 'includes/payments/class-payments-ajax.php';

        // Membership System (v1.49.0)
        require_once EAU_SYSTEM_PLUGIN_DIR . 'includes/class-eau-membership-database.php';
        require_once EAU_SYSTEM_PLUGIN_DIR . 'includes/class-eau-membership-types.php';
        require_once EAU_SYSTEM_PLUGIN_DIR . 'includes/class-eau-newsletters.php';
        require_once EAU_SYSTEM_PLUGIN_DIR . 'includes/class-eau-public-registration.php';
        require_once EAU_SYSTEM_PLUGIN_DIR . 'ajax/class-eau-public-registration-ajax.php';
        require_once EAU_SYSTEM_PLUGIN_DIR . 'includes/class-eau-membership-selection.php';
        require_once EAU_SYSTEM_PLUGIN_DIR . 'ajax/class-eau-membership-selection-ajax.php';
        require_once EAU_SYSTEM_PLUGIN_DIR . 'includes/class-eau-membership-applications-management.php';
        require_once EAU_SYSTEM_PLUGIN_DIR . 'ajax/class-eau-membership-applications-ajax.php';

        // Membership Cron Jobs (v1.50.0)
        require_once EAU_SYSTEM_PLUGIN_DIR . 'includes/class-eau-membership-cron.php';

        // Payments Management (v1.50.1)
        require_once EAU_SYSTEM_PLUGIN_DIR . 'includes/class-eau-payments-management.php';
        require_once EAU_SYSTEM_PLUGIN_DIR . 'ajax/class-eau-payments-management-ajax.php';

        // Payment Receipt Generator (v1.53.6)
        require_once EAU_SYSTEM_PLUGIN_DIR . 'includes/payments/class-payment-receipt-generator.php';

        // My Payments (v1.53.8)
        require_once EAU_SYSTEM_PLUGIN_DIR . 'includes/class-eau-my-payments.php';
        require_once EAU_SYSTEM_PLUGIN_DIR . 'ajax/class-eau-my-payments-ajax.php';

        // System Presentation - Public (v1.51.65)
        require_once EAU_SYSTEM_PLUGIN_DIR . 'includes/class-eau-system-presentation.php';

        // Sidebar Menu (v1.56.0)
        require_once EAU_SYSTEM_PLUGIN_DIR . 'includes/class-eau-sidebar-menu.php';

        // Pages Manager (v1.57.0)
        require_once EAU_SYSTEM_PLUGIN_DIR . 'includes/class-eau-pages.php';

        // Custom Header (v1.57.5)
        require_once EAU_SYSTEM_PLUGIN_DIR . 'includes/class-eau-header.php';

        // Ensure membership tables exist (for updates without reactivation)
        if (!Eau_Membership_Database::tables_exist()) {
            Eau_Membership_Database::create_tables();
        }

        // Upgrade existing tables if needed
        Eau_Membership_Database::maybe_upgrade_tables();
    }

    private function define_admin_hooks() {
        $admin = new Eau_Admin($this->get_plugin_name(), $this->get_version());

        add_action('admin_menu', array($admin, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($admin, 'enqueue_styles'));
        add_action('admin_enqueue_scripts', array($admin, 'enqueue_scripts'));

        // Documentation pages
        add_action('admin_menu', array('EauSystem\Eau_Documentation', 'register_admin_pages'));
        add_action('admin_enqueue_scripts', array('EauSystem\Eau_Documentation', 'enqueue_admin_assets'));

        // Email settings page
        Email\Email_Settings::register();

        // Email preview
        Email\Email_Service::register();

        // Email events cron
        Email\Email_Events::register();
    }

    private function define_frontend_hooks() {
        // Enfileira assets do dashboard no frontend
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));

        // Adiciona CSS crítico inline para evitar FOUC (Flash of Unstyled Content)
        add_action('wp_head', array($this, 'add_critical_css'), 1);

        // Adiciona script para remover loading quando CSS carregar
        add_action('wp_footer', array($this, 'add_fouc_prevention_script'), 999);
    }

    /**
     * Adiciona CSS crítico inline no <head> para evitar FOUC
     * Prioridade 1 para carregar antes de qualquer outro CSS
     */
    public function add_critical_css() {
        ?>
        <style id="eau-critical-css">
            /* Overlay de loading - esconde conteúdo até CSS carregar */
            .eau-loading-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: #f9fafb;
                z-index: 999999;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: opacity 0.3s ease-out;
            }
            .eau-loading-overlay.eau-fade-out {
                opacity: 0;
                pointer-events: none;
            }
            .eau-loading-spinner {
                width: 40px;
                height: 40px;
                border: 3px solid #e5e7eb;
                border-top-color: #2563eb;
                border-radius: 50%;
                animation: eau-spin 0.8s linear infinite;
            }
            @keyframes eau-spin {
                to { transform: rotate(360deg); }
            }

            /* Esconde conteúdo Eau inicialmente até CSS carregar */
            body:not(.eau-styles-loaded) .eau-dashboard-container,
            body:not(.eau-styles-loaded) .eau-members-management,
            body:not(.eau-styles-loaded) .eau-institutions-management,
            body:not(.eau-styles-loaded) .eau-activities-management,
            body:not(.eau-styles-loaded) .eau-categories-management,
            body:not(.eau-styles-loaded) .eau-my-cpds-container,
            body:not(.eau-styles-loaded) .eau-my-profile-container,
            body:not(.eau-styles-loaded) .eau-my-institution-container,
            body:not(.eau-styles-loaded) .eau-settings-container,
            body:not(.eau-styles-loaded) .eau-payments-container,
            body:not(.eau-styles-loaded) .eau-duplicate-manager,
            body:not(.eau-styles-loaded) .eau-openlearning-container,
            body:not(.eau-styles-loaded) .eau-events-management,
            body:not(.eau-styles-loaded) .eau-registration-form,
            body:not(.eau-styles-loaded) .eau-membership-selection,
            body:not(.eau-styles-loaded) .eau-membership-applications {
                opacity: 0;
            }

            /* Transição suave quando estilos carregarem */
            body.eau-styles-loaded .eau-dashboard-container,
            body.eau-styles-loaded .eau-members-management,
            body.eau-styles-loaded .eau-institutions-management,
            body.eau-styles-loaded .eau-activities-management,
            body.eau-styles-loaded .eau-categories-management,
            body.eau-styles-loaded .eau-my-cpds-container,
            body.eau-styles-loaded .eau-my-profile-container,
            body.eau-styles-loaded .eau-my-institution-container,
            body.eau-styles-loaded .eau-settings-container,
            body.eau-styles-loaded .eau-payments-container,
            body.eau-styles-loaded .eau-duplicate-manager,
            body.eau-styles-loaded .eau-openlearning-container,
            body.eau-styles-loaded .eau-events-management,
            body.eau-styles-loaded .eau-registration-form,
            body.eau-styles-loaded .eau-membership-selection,
            body.eau-styles-loaded .eau-membership-applications {
                opacity: 1;
                transition: opacity 0.2s ease-in;
            }
        </style>
        <script>
            // Adiciona overlay de loading imediatamente
            (function() {
                var overlay = document.createElement('div');
                overlay.className = 'eau-loading-overlay';
                overlay.id = 'eau-loading-overlay';
                overlay.innerHTML = '<div class="eau-loading-spinner"></div>';
                document.documentElement.appendChild(overlay);
            })();
        </script>
        <?php
    }

    /**
     * Adiciona script no footer para remover overlay quando CSS carregar
     */
    public function add_fouc_prevention_script() {
        ?>
        <script>
            (function() {
                function removeLoadingOverlay() {
                    var overlay = document.getElementById('eau-loading-overlay');
                    if (overlay) {
                        overlay.classList.add('eau-fade-out');
                        setTimeout(function() {
                            overlay.remove();
                        }, 300);
                    }
                    document.body.classList.add('eau-styles-loaded');
                }

                // Verifica se todas as stylesheets foram carregadas
                function checkStylesLoaded() {
                    var stylesheets = document.querySelectorAll('link[rel="stylesheet"]');
                    var allLoaded = true;

                    stylesheets.forEach(function(link) {
                        if (!link.sheet) {
                            allLoaded = false;
                        }
                    });

                    return allLoaded;
                }

                // Aguarda DOM ready e estilos carregados
                if (document.readyState === 'complete') {
                    removeLoadingOverlay();
                } else {
                    window.addEventListener('load', function() {
                        // Pequeno delay para garantir que estilos foram aplicados
                        setTimeout(removeLoadingOverlay, 50);
                    });
                }

                // Fallback: remove após 3 segundos caso algo dê errado
                setTimeout(function() {
                    if (document.getElementById('eau-loading-overlay')) {
                        removeLoadingOverlay();
                    }
                }, 3000);
            })();
        </script>
        <?php
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

        // Version console.log for debugging
        wp_add_inline_script(
            'lucide-icons',
            'console.log("%c Eau System v' . EAU_SYSTEM_VERSION . ' ", "background: #005EB8; color: white; padding: 4px 8px; border-radius: 4px; font-weight: bold;");',
            'after'
        );
    }

    public function run() {
        // Registra shortcodes
        Eau_Dashboard::register_shortcode();
        Eau_Members_Management::register_shortcode();
        Eau_Institutions_Management::register_shortcode();
        Eau_Activities_Management::register_shortcode();
        Eau_Categories_Management::register_shortcode();
        Eau_My_Cpds::register_shortcode();
        Eau_Settings::register_shortcode();
        Eau_My_Profile::register_shortcode();
        Eau_Duplicate_Manager::register_shortcode();
        Eau_OpenLearning_Courses::register_shortcode();
        Eau_OpenLearning_Management::register_shortcode();
        Eau_My_Institution::register_shortcode();
        Eau_Public_Registration::register_shortcode();
        Eau_Membership_Selection::register_shortcode();
        Eau_Membership_Applications_Management::register_shortcode();
        Eau_System_Presentation::register_shortcode();
        Eau_Payments_Management::init();
        Eau_My_Payments::register_shortcode();
        Eau_Sidebar_Menu::register_shortcode();

        // Registra AJAX handlers
        \EauSystem\Ajax\Eau_Members_Ajax::register_handlers();
        \EauSystem\Ajax\Eau_Institutions_Ajax::register_handlers();
        \EauSystem\Ajax\Eau_Activities_Ajax::register_handlers();
        Eau_Categories_Ajax::register_ajax_handlers();
        Eau_Event_Categories_Ajax::register_ajax_handlers();
        \EauSystem\Ajax\Eau_My_Cpds_Ajax::register_handlers();
        \EauSystem\Ajax\Eau_Settings_Ajax::register_handlers();
        \EauSystem\Ajax\Eau_My_Profile_Ajax::register_handlers();
        Eau_Duplicate_Ajax::register_endpoints();
        \EauSystem\Ajax\Eau_OpenLearning_Ajax::register_handlers();
        \EauSystem\Ajax\Eau_OpenLearning_Management_Ajax::register_handlers();
        \EauSystem\Ajax\Eau_My_Institution_Ajax::register_handlers();
        Eau_Public_Registration_Ajax::register_handlers();
        \EauSystem\Ajax\Eau_Membership_Selection_Ajax::register_handlers();
        \EauSystem\Ajax\Eau_Membership_Applications_Ajax::register_handlers();
        \EauSystem\Ajax\Eau_Payments_Management_Ajax::init();
        \EauSystem\Ajax\Eau_My_Payments_Ajax::register_handlers();

        // Registra hooks do Duplicate Scanner (WP Cron)
        Eau_Duplicate_Scanner::register_hooks();

        // Inicializa Members Settings
        Eau_Members_Settings::init();

        // Garante que tabelas de duplicatas existem
        if (!Eau_Duplicate_Database::tables_exist()) {
            Eau_Duplicate_Database::create_tables();
        }

        // Garante que tabela de categorias existe
        Eau_Categories_Database::create_table();

        // Initialize Events Module
        \EauSystem\Events\Eau_Events::get_instance();

        // Initialize Event Registrations CPT
        \EauSystem\EventRegistrations\Eau_Event_Registrations::get_instance();

        // Initialize Payments System
        \EauSystem\Payments\Payments_Post_Type::init();
        \EauSystem\Payments\Payments_Ajax::register_handlers();

        // Initialize Payment Receipt Generator (v1.53.6)
        \EauSystem\Payments\Payment_Receipt_Generator::register();

        // Garante que tabelas do OpenLearning existem
        if (!Eau_OpenLearning_Database::tables_exist()) {
            Eau_OpenLearning_Database::create_tables();
        }

        // Registra OpenLearning Post Type
        Eau_OpenLearning_Post_Type::register_hooks();

        // Registra WP Cron para sincronização de cursos OpenLearning
        add_action('eau_openlearning_sync_courses', array('EauSystem\\Eau_OpenLearning_Service', 'cron_sync_handler'));

        // Garante que tabela de requests de instituição existe
        if (!Eau_Institution_Requests_Database::table_exists()) {
            Eau_Institution_Requests_Database::create_table();
        }

        // Registra hooks do Membership Cron (v1.50.0)
        Eau_Membership_Cron::register_hooks();

        // Verifica se precisa criar páginas (para updates sem reativação) (v1.57.0)
        if (Eau_Pages::needs_page_creation()) {
            Eau_Pages::create_pages();
        }

        // Initialize custom header for system pages (v1.57.5)
        Eau_Header::init();
    }

    public function get_plugin_name() {
        return $this->plugin_name;
    }

    public function get_version() {
        return $this->version;
    }
}
