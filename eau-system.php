<?php
/**
 * Plugin Name: Eau System
 * Plugin URI: https://platty.com.br
 * Description: Sistema para importação de CSV e criação dinâmica de Post Types e Usuários compatível com JetEngine e WooCommerce
 * Version: 1.63.8
 * Author: Platty / Rodrigo Zillesg
 * Author URI: https://platty.com.br
 * Text Domain: eau-system
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Se este arquivo foi chamado diretamente, aborta.
if (!defined('WPINC')) {
    die;
}

// Define constantes do plugin
define('EAU_SYSTEM_VERSION', '1.63.8');
define('EAU_SYSTEM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('EAU_SYSTEM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('EAU_SYSTEM_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Autoloader simples para as classes do plugin
spl_autoload_register(function ($class) {
    $prefix = 'EauSystem\\';
    $base_dir = EAU_SYSTEM_PLUGIN_DIR . 'includes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);

    // Suporta namespaces com subpastas (e.g., EauSystem\Components\Eau_Skeleton)
    // Converte namespace separators para directory separators
    $relative_class = str_replace('\\', DIRECTORY_SEPARATOR, $relative_class);

    // Converte CamelCase/Snake_Case para kebab-case (class-eau-*)
    $parts = explode(DIRECTORY_SEPARATOR, $relative_class);
    $class_name = array_pop($parts);
    $subdirs = !empty($parts) ? implode(DIRECTORY_SEPARATOR, array_map('strtolower', $parts)) . DIRECTORY_SEPARATOR : '';

    $file_name = 'class-' . strtolower(str_replace('_', '-', $class_name)) . '.php';
    $file = $base_dir . $subdirs . $file_name;

    if (file_exists($file)) {
        require $file;
    }
});

// Inclui arquivos principais
require_once EAU_SYSTEM_PLUGIN_DIR . 'includes/class-eau-system.php';

/**
 * Inicia o plugin
 */
function run_eau_system() {
    $plugin = new EauSystem\Eau_System();
    $plugin->run();
}

// Hook de ativação
register_activation_hook(__FILE__, function() {
    // Registra roles customizados
    \EauSystem\Eau_Roles::register_custom_roles();

    // Cria tabelas de duplicatas
    \EauSystem\Eau_Duplicate_Database::create_tables();

    // Cria tabelas do OpenLearning
    \EauSystem\Eau_OpenLearning_Database::create_tables();

    // Cria tabelas do Membership System
    \EauSystem\Eau_Membership_Database::create_tables();

    // Cria tabela de histórico de Member-Institution (v1.53.0)
    \EauSystem\Eau_Member_Institution_Database::create_table();

    // Cria tabela de Event Categories (v1.61.0)
    \EauSystem\Eau_Event_Categories_Database::create_table();

    // Cria tabela de log de Email Migration (v1.62.0)
    \EauSystem\EmailMigration\Eau_Email_Migration_Database::create_table();

    // Cria Post Type OpenLearning no JetEngine
    \EauSystem\Eau_OpenLearning_Post_Type::save_to_jet_engine();
    \EauSystem\Eau_OpenLearning_Post_Type::save_backup();

    // Setup cache cron para activities stats
    \EauSystem\Eau_Activities_Stats_Cache::setup_cron();

    // Setup cron para processar eventos finalizados e criar Activities
    \EauSystem\EventRegistrations\Eau_Event_Activity_Creator::setup_cron();
    // Setup cron para sincronização OpenLearning
    \EauSystem\Eau_OpenLearning_Service::setup_cron();

    // Setup cron para verificação de expiração de membership (v1.50.0)
    \EauSystem\Eau_Membership_Cron::setup_cron();

    // Cria páginas do sistema automaticamente (v1.57.0)
    \EauSystem\Eau_Pages::create_pages();

    // Cria tabelas necessárias se precisar
    flush_rewrite_rules();
});

// Hook de desativação
register_deactivation_hook(__FILE__, function() {
    // Remove cache cron
    \EauSystem\Eau_Activities_Stats_Cache::clear_cron();

    // Remove cron de eventos finalizados
    \EauSystem\EventRegistrations\Eau_Event_Activity_Creator::clear_cron();
    // Remove OpenLearning sync cron
    \EauSystem\Eau_OpenLearning_Service::clear_cron();

    // Remove cron de expiração de membership (v1.50.0)
    \EauSystem\Eau_Membership_Cron::clear_cron();

    flush_rewrite_rules();
});

// Registra hook do WP Cron para regeneração de cache
add_action('eau_regenerate_activities_stats_cache', array(
    'EauSystem\\Eau_Activities_Stats_Cache',
    'regenerate_all_caches'
));

// Inicia o plugin
run_eau_system();
