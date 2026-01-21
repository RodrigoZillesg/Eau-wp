<?php
/**
 * Fat Zebra Module Bootstrap
 *
 * Inicializa o módulo Fat Zebra e suas dependências.
 *
 * @package EauSystem
 * @subpackage FatZebra
 * @since 1.70.0
 */

namespace EauSystem\FatZebra;

// Se este arquivo foi chamado diretamente, aborta.
if (!defined('WPINC')) {
    die;
}

class FatZebra {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Module version
     */
    const VERSION = '1.0.0';

    /**
     * Get singleton instance
     *
     * @return self
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init();
    }

    /**
     * Load dependencies
     */
    private function load_dependencies() {
        $base = EAU_SYSTEM_PLUGIN_DIR . 'includes/fatzebra/';

        require_once $base . 'class-fatzebra-logger.php';
        require_once $base . 'class-fatzebra-settings.php';
        require_once $base . 'class-fatzebra-gateway.php';
        require_once $base . 'class-fatzebra-webhook.php';
    }

    /**
     * Initialize module
     */
    private function init() {
        // Register webhook endpoint
        FatZebra_Webhook::register_endpoint();

        // Register AJAX handlers for settings
        add_action('wp_ajax_eau_fatzebra_test_connection', array($this, 'ajax_test_connection'));
        add_action('wp_ajax_eau_fatzebra_save_settings', array($this, 'ajax_save_settings'));
        add_action('wp_ajax_eau_fatzebra_get_settings', array($this, 'ajax_get_settings'));
        add_action('wp_ajax_eau_fatzebra_get_logs', array($this, 'ajax_get_logs'));
        add_action('wp_ajax_eau_fatzebra_clear_logs', array($this, 'ajax_clear_logs'));
    }

    /**
     * Activation hook - create tables
     */
    public static function activate() {
        FatZebra_Logger::create_table();
    }

    /**
     * AJAX: Test connection
     */
    public function ajax_test_connection() {
        // Verify nonce
        if (!check_ajax_referer('eau_settings_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => 'Invalid security token'));
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $gateway = FatZebra_Gateway::get_instance();
        $result = $gateway->test_connection();

        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'message' => $result->get_error_message(),
            ));
        }

        wp_send_json_success($result);
    }

    /**
     * AJAX: Save settings
     */
    public function ajax_save_settings() {
        // Verify nonce
        if (!check_ajax_referer('eau_settings_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => 'Invalid security token'));
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        // Get settings from POST
        $settings = array(
            'sandbox_mode'        => filter_var($_POST['sandbox_mode'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'sandbox_username'    => sanitize_text_field($_POST['sandbox_username'] ?? 'TEST'),
            'sandbox_token'       => sanitize_text_field($_POST['sandbox_token'] ?? ''),
            'production_username' => sanitize_text_field($_POST['production_username'] ?? ''),
            'production_token'    => sanitize_text_field($_POST['production_token'] ?? ''),
            'webhook_secret'      => sanitize_text_field($_POST['webhook_secret'] ?? ''),
        );

        $result = FatZebra_Settings::save_all($settings);

        if ($result) {
            wp_send_json_success(array(
                'message'  => __('Settings saved successfully', 'eau-system'),
                'settings' => FatZebra_Settings::get_for_display(),
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Failed to save settings', 'eau-system'),
            ));
        }
    }

    /**
     * AJAX: Get settings
     */
    public function ajax_get_settings() {
        // Verify nonce
        if (!check_ajax_referer('eau_settings_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => 'Invalid security token'));
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        wp_send_json_success(FatZebra_Settings::get_for_display());
    }

    /**
     * AJAX: Get logs
     */
    public function ajax_get_logs() {
        // Verify nonce
        if (!check_ajax_referer('eau_settings_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => 'Invalid security token'));
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $args = array(
            'level'    => sanitize_text_field($_POST['level'] ?? ''),
            'search'   => sanitize_text_field($_POST['search'] ?? ''),
            'per_page' => absint($_POST['per_page'] ?? 50),
            'page'     => absint($_POST['page'] ?? 1),
            'order'    => sanitize_text_field($_POST['order'] ?? 'DESC'),
        );

        $result = FatZebra_Logger::get_logs($args);
        $stats = FatZebra_Logger::get_stats();

        wp_send_json_success(array(
            'logs'  => $result,
            'stats' => $stats,
        ));
    }

    /**
     * AJAX: Clear logs
     */
    public function ajax_clear_logs() {
        // Verify nonce
        if (!check_ajax_referer('eau_settings_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => 'Invalid security token'));
        }

        // Check permissions - only superAdmin
        $mem_type = get_user_meta(get_current_user_id(), 'mem_type', true);
        if ($mem_type !== 'superAdmin') {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $result = FatZebra_Logger::clear_all();

        if ($result) {
            wp_send_json_success(array(
                'message' => __('Logs cleared successfully', 'eau-system'),
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Failed to clear logs', 'eau-system'),
            ));
        }
    }

    /**
     * Check if Fat Zebra is configured and ready
     *
     * @return bool
     */
    public static function is_ready() {
        return FatZebra_Settings::is_configured();
    }

    /**
     * Get gateway instance
     *
     * @return FatZebra_Gateway
     */
    public static function gateway() {
        return FatZebra_Gateway::get_instance();
    }
}
