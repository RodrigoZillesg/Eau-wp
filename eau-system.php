<?php
/**
 * Plugin Name: Eau System
 * Plugin URI: https://platty.com.br
 * Description: Sistema para importação de CSV e criação dinâmica de Post Types e Usuários compatível com JetEngine e WooCommerce
 * Version: 1.30.4
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
define('EAU_SYSTEM_VERSION', '1.30.4');
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

    // Cria tabelas necessárias se precisar
    flush_rewrite_rules();
});

// Hook de desativação
register_deactivation_hook(__FILE__, function() {
    flush_rewrite_rules();
});

// Inicia o plugin
run_eau_system();
