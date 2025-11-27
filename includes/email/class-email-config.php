<?php
/**
 * Email Configuration
 *
 * @package EauSystem
 * @since   1.44.0
 */

namespace EauSystem\Email;

if (!defined('WPINC')) {
    die;
}

class Email_Config {

    // Configurações padrão
    const FROM_NAME = 'English Australia';
    const FROM_EMAIL = 'noreply@englishaustralia.com.au';

    // Cores do template
    const COLOR_PRIMARY = '#005EB8';
    const COLOR_SECONDARY = '#6495ED';
    const COLOR_TEXT = '#333333';
    const COLOR_TEXT_LIGHT = '#666666';
    const COLOR_BACKGROUND = '#f4f4f4';
    const COLOR_WHITE = '#ffffff';

    // Logo
    const LOGO_URL = '';  // Será preenchido dinamicamente

    /**
     * Retorna configurações do email
     */
    public static function get_from_name() {
        return get_option('eau_email_from_name', self::FROM_NAME);
    }

    public static function get_from_email() {
        return get_option('eau_email_from_email', self::FROM_EMAIL);
    }

    public static function get_logo_url() {
        $custom_logo = get_option('eau_email_logo_url', '');
        if (!empty($custom_logo)) {
            return $custom_logo;
        }
        // Fallback para logo do plugin
        return EAU_SYSTEM_PLUGIN_URL . 'includes/event-registrations/certificate/english-australia-logo-500.png';
    }

    public static function get_footer_text() {
        return get_option('eau_email_footer_text', '© ' . date('Y') . ' English Australia. All rights reserved.');
    }
}
