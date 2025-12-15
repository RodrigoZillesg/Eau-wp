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

        // Member Tags CRUD
        add_action('wp_ajax_eau_get_member_tags', array(__CLASS__, 'get_member_tags'));
        add_action('wp_ajax_eau_add_member_tag', array(__CLASS__, 'add_member_tag'));
        add_action('wp_ajax_eau_update_member_tag', array(__CLASS__, 'update_member_tag'));
        add_action('wp_ajax_eau_delete_member_tag', array(__CLASS__, 'delete_member_tag'));
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

    /**
     * AJAX: Get Member Tags
     */
    public static function get_member_tags() {
        // Verifica nonce
        check_ajax_referer('eau_settings_nonce', 'nonce');

        // Verifica permissão
        if (!Eau_Settings::can_access_settings()) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        $tags = Eau_Settings::get_member_tags();

        wp_send_json_success(array(
            'tags' => $tags,
        ));
    }

    /**
     * AJAX: Add Member Tag
     */
    public static function add_member_tag() {
        // Verifica nonce
        check_ajax_referer('eau_settings_nonce', 'nonce');

        // Verifica permissão
        if (!Eau_Settings::can_access_settings()) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $color = isset($_POST['color']) ? sanitize_hex_color($_POST['color']) : null;
        $description = isset($_POST['description']) ? sanitize_text_field($_POST['description']) : '';

        if (empty($name)) {
            wp_send_json_error(array('message' => 'Tag name is required.'));
        }

        $result = Eau_Settings::add_member_tag($name, $color, $description);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array(
            'message' => 'Tag created successfully.',
            'tag' => $result,
        ));
    }

    /**
     * AJAX: Update Member Tag
     */
    public static function update_member_tag() {
        // Verifica nonce
        check_ajax_referer('eau_settings_nonce', 'nonce');

        // Verifica permissão
        if (!Eau_Settings::can_access_settings()) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        $id = isset($_POST['id']) ? sanitize_text_field($_POST['id']) : '';
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $color = isset($_POST['color']) ? sanitize_hex_color($_POST['color']) : '';
        $description = isset($_POST['description']) ? sanitize_text_field($_POST['description']) : null;

        if (empty($id)) {
            wp_send_json_error(array('message' => 'Tag ID is required.'));
        }

        if (empty($name)) {
            wp_send_json_error(array('message' => 'Tag name is required.'));
        }

        $result = Eau_Settings::update_member_tag($id, $name, $color, $description);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array(
            'message' => 'Tag updated successfully.',
            'tag' => $result,
        ));
    }

    /**
     * AJAX: Delete Member Tag
     */
    public static function delete_member_tag() {
        // Verifica nonce
        check_ajax_referer('eau_settings_nonce', 'nonce');

        // Verifica permissão
        if (!Eau_Settings::can_access_settings()) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        $id = isset($_POST['id']) ? sanitize_text_field($_POST['id']) : '';

        if (empty($id)) {
            wp_send_json_error(array('message' => 'Tag ID is required.'));
        }

        $result = Eau_Settings::delete_member_tag($id);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array(
            'message' => 'Tag deleted successfully.',
        ));
    }
}
