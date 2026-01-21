<?php
/**
 * Fat Zebra Settings
 *
 * Gerencia as configurações do gateway Fat Zebra.
 * Armazena credenciais de forma segura usando encryption.
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

class FatZebra_Settings {

    /**
     * Option key for storing settings
     */
    const OPTION_KEY = 'eau_fatzebra_settings';

    /**
     * Encryption cipher
     */
    const CIPHER = 'AES-256-CBC';

    /**
     * Get encryption key (uses WordPress AUTH_KEY)
     *
     * @return string
     */
    private static function get_encryption_key() {
        if (defined('AUTH_KEY') && !empty(AUTH_KEY)) {
            return hash('sha256', AUTH_KEY);
        }
        return hash('sha256', 'eau-system-fatzebra-default-key');
    }

    /**
     * Encrypt a value
     *
     * @param string $value Value to encrypt
     * @return string Encrypted value
     */
    private static function encrypt($value) {
        if (empty($value)) {
            return '';
        }

        $key = self::get_encryption_key();
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(self::CIPHER));
        $encrypted = openssl_encrypt($value, self::CIPHER, $key, 0, $iv);

        return base64_encode($encrypted . '::' . $iv);
    }

    /**
     * Decrypt a value
     *
     * @param string $value Encrypted value
     * @return string Decrypted value
     */
    private static function decrypt($value) {
        if (empty($value)) {
            return '';
        }

        $key = self::get_encryption_key();
        $parts = explode('::', base64_decode($value), 2);

        if (count($parts) !== 2) {
            return '';
        }

        list($encrypted, $iv) = $parts;

        return openssl_decrypt($encrypted, self::CIPHER, $key, 0, $iv);
    }

    /**
     * Get all settings
     *
     * @return array
     */
    public static function get_all() {
        $defaults = array(
            'sandbox_mode'        => true,
            'sandbox_username'    => 'TEST',
            'sandbox_token'       => 'TEST',
            'production_username' => '',
            'production_token'    => '',
            'webhook_secret'      => '',
        );

        $settings = get_option(self::OPTION_KEY, array());

        return wp_parse_args($settings, $defaults);
    }

    /**
     * Save all settings
     *
     * @param array $settings Settings to save
     * @return bool
     */
    public static function save_all($settings) {
        $current = self::get_all();

        // Campos que devem ser encriptados
        $encrypted_fields = array('sandbox_token', 'production_token', 'webhook_secret');

        // Processa cada campo
        foreach ($settings as $key => $value) {
            if (in_array($key, $encrypted_fields)) {
                // Se o valor está vazio, não atualiza (mantém o atual)
                if (empty($value) || $value === '********') {
                    $settings[$key] = $current[$key] ?? '';
                } else {
                    $settings[$key] = self::encrypt($value);
                }
            }
        }

        // Valida sandbox_mode
        $settings['sandbox_mode'] = filter_var($settings['sandbox_mode'] ?? true, FILTER_VALIDATE_BOOLEAN);

        // Salva
        $result = update_option(self::OPTION_KEY, $settings);

        if ($result) {
            FatZebra_Logger::log('info', 'Settings saved');
        }

        return $result;
    }

    /**
     * Check if sandbox mode is enabled
     *
     * @return bool
     */
    public static function is_sandbox_mode() {
        $settings = self::get_all();
        return (bool) ($settings['sandbox_mode'] ?? true);
    }

    /**
     * Get current username (based on mode)
     *
     * @return string
     */
    public static function get_username() {
        $settings = self::get_all();

        if (self::is_sandbox_mode()) {
            return $settings['sandbox_username'] ?? 'TEST';
        }

        return $settings['production_username'] ?? '';
    }

    /**
     * Get current token (based on mode)
     *
     * @return string
     */
    public static function get_token() {
        $settings = self::get_all();

        if (self::is_sandbox_mode()) {
            $encrypted = $settings['sandbox_token'] ?? '';
        } else {
            $encrypted = $settings['production_token'] ?? '';
        }

        $decrypted = self::decrypt($encrypted);

        // Se não conseguiu decriptar, pode ser valor legacy não encriptado
        if (empty($decrypted) && !empty($encrypted)) {
            return $encrypted;
        }

        return $decrypted;
    }

    /**
     * Get webhook secret
     *
     * @return string
     */
    public static function get_webhook_secret() {
        $settings = self::get_all();
        $encrypted = $settings['webhook_secret'] ?? '';

        $decrypted = self::decrypt($encrypted);

        // Se não conseguiu decriptar, pode ser valor legacy não encriptado
        if (empty($decrypted) && !empty($encrypted)) {
            return $encrypted;
        }

        return $decrypted;
    }

    /**
     * Get webhook URL for Fat Zebra configuration
     *
     * @return string
     */
    public static function get_webhook_url() {
        return rest_url('eau-system/v1/fatzebra-webhook');
    }

    /**
     * Check if gateway is configured
     *
     * @return bool
     */
    public static function is_configured() {
        $username = self::get_username();
        $token = self::get_token();

        return !empty($username) && !empty($token);
    }

    /**
     * Get mode label
     *
     * @return string
     */
    public static function get_mode_label() {
        return self::is_sandbox_mode() ? __('Sandbox', 'eau-system') : __('Production', 'eau-system');
    }

    /**
     * Get settings for display (masking sensitive data)
     *
     * @return array
     */
    public static function get_for_display() {
        $settings = self::get_all();

        // Mascara tokens
        $masked_fields = array('sandbox_token', 'production_token', 'webhook_secret');

        foreach ($masked_fields as $field) {
            if (!empty($settings[$field])) {
                $settings[$field] = '********';
            }
        }

        // Adiciona informações extras
        $settings['mode_label'] = self::get_mode_label();
        $settings['is_configured'] = self::is_configured();
        $settings['webhook_url'] = self::get_webhook_url();

        return $settings;
    }

    /**
     * Reset settings to defaults
     *
     * @return bool
     */
    public static function reset() {
        FatZebra_Logger::log('warning', 'Settings reset to defaults');
        return delete_option(self::OPTION_KEY);
    }

    /**
     * Set sandbox mode
     *
     * @param bool $sandbox True for sandbox, false for production
     * @return bool
     */
    public static function set_sandbox_mode($sandbox) {
        $settings = self::get_all();
        $settings['sandbox_mode'] = (bool) $sandbox;

        FatZebra_Logger::log('info', 'Mode changed', array(
            'mode' => $sandbox ? 'sandbox' : 'production',
        ));

        return update_option(self::OPTION_KEY, $settings);
    }

    /**
     * Set sandbox credentials
     *
     * @param string $username Username
     * @param string $token    Token
     * @return bool
     */
    public static function set_sandbox_credentials($username, $token) {
        $settings = self::get_all();
        $settings['sandbox_username'] = sanitize_text_field($username);

        if (!empty($token) && $token !== '********') {
            $settings['sandbox_token'] = self::encrypt($token);
        }

        return update_option(self::OPTION_KEY, $settings);
    }

    /**
     * Set production credentials
     *
     * @param string $username Username
     * @param string $token    Token
     * @return bool
     */
    public static function set_production_credentials($username, $token) {
        $settings = self::get_all();
        $settings['production_username'] = sanitize_text_field($username);

        if (!empty($token) && $token !== '********') {
            $settings['production_token'] = self::encrypt($token);
        }

        return update_option(self::OPTION_KEY, $settings);
    }

    /**
     * Set webhook secret
     *
     * @param string $secret Webhook secret
     * @return bool
     */
    public static function set_webhook_secret($secret) {
        $settings = self::get_all();

        if (!empty($secret) && $secret !== '********') {
            $settings['webhook_secret'] = self::encrypt($secret);
        }

        return update_option(self::OPTION_KEY, $settings);
    }

    /**
     * Generate a random webhook secret
     *
     * @return string
     */
    public static function generate_webhook_secret() {
        return wp_generate_password(32, false);
    }
}
