<?php
namespace EauSystem\Ajax;

use EauSystem\Eau_Settings;

/**
 * AJAX Handlers para Settings
 *
 * @since 1.39.0
 */
class Eau_Settings_Ajax {

    /**
     * Registra os handlers AJAX
     */
    public static function register_handlers() {
        // Get settings
        add_action('wp_ajax_eau_get_settings', array(__CLASS__, 'get_settings'));

        // Save settings
        add_action('wp_ajax_eau_save_settings', array(__CLASS__, 'save_settings'));
    }

    /**
     * AJAX: Get Settings
     */
    public static function get_settings() {
        // Verifica nonce
        check_ajax_referer('eau_settings_nonce', 'nonce');

        // Verifica permissão
        if (!Eau_Settings::can_access_settings()) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        // Retorna configurações
        wp_send_json_success(array(
            'activity_approval_mode' => Eau_Settings::get_activity_approval_mode(),
        ));
    }

    /**
     * AJAX: Save Settings
     */
    public static function save_settings() {
        // Verifica nonce
        check_ajax_referer('eau_settings_nonce', 'nonce');

        // Verifica permissão
        if (!Eau_Settings::can_access_settings()) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        // Pega dados
        $settings = isset($_POST['settings']) ? $_POST['settings'] : array();

        // Salva Activity Approval Mode
        if (isset($settings['activity_approval_mode'])) {
            $approval_mode = sanitize_text_field($settings['activity_approval_mode']);

            // Valida valor
            if (in_array($approval_mode, array(Eau_Settings::APPROVAL_AUTO, Eau_Settings::APPROVAL_MANUAL))) {
                update_option(Eau_Settings::OPTION_ACTIVITY_APPROVAL, $approval_mode);
            }
        }

        wp_send_json_success(array(
            'message' => 'Settings saved successfully.',
        ));
    }
}
