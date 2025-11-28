<?php
/**
 * Certificate Configuration
 *
 * @package EauSystem
 * @since   1.43.9
 */

namespace EauSystem\EventRegistrations\Certificate;

if (!defined('WPINC')) {
    die;
}

class Certificate_Config {

    // Dimensões 16:9 paisagem
    const WIDTH = 1920;
    const HEIGHT = 1080;

    // Cores (RGB)
    const COLOR_BLUE = [0, 94, 184];       // #005EB8
    const COLOR_GOLD = [212, 175, 55];     // Dourado
    const COLOR_DARK = [51, 51, 51];       // Texto escuro
    const COLOR_GRAY = [128, 128, 128];    // Texto cinza
    const COLOR_WHITE = [255, 255, 255];   // Fundo

    // Organização
    const ORG_NAME = 'English Australia';
    const SIGNER_NAME = 'Ian Aird';
    const SIGNER_TITLE = 'Chief Executive Officer';

    /**
     * Retorna fonte do sistema
     */
    public static function get_font($type = 'regular') {
        // Primeiro tenta fontes no plugin
        $plugin_fonts = [
            'regular' => EAU_SYSTEM_PLUGIN_DIR . 'assets/fonts/OpenSans-Regular.ttf',
            'bold'    => EAU_SYSTEM_PLUGIN_DIR . 'assets/fonts/OpenSans-Bold.ttf',
            'italic'  => EAU_SYSTEM_PLUGIN_DIR . 'assets/fonts/OpenSans-Italic.ttf',
        ];

        $plugin_font = $plugin_fonts[$type] ?? $plugin_fonts['regular'];
        if (file_exists($plugin_font)) {
            return $plugin_font;
        }

        // Fallback: fontes Linux comuns
        $linux_fonts = [
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
            '/usr/share/fonts/truetype/freefont/FreeSans.ttf',
        ];

        foreach ($linux_fonts as $font) {
            if (file_exists($font)) {
                return $font;
            }
        }

        // Fallback: fontes Windows
        $windows_fonts = [
            'regular' => 'C:/Windows/Fonts/arial.ttf',
            'bold'    => 'C:/Windows/Fonts/arialbd.ttf',
            'italic'  => 'C:/Windows/Fonts/ariali.ttf',
        ];

        $win_font = $windows_fonts[$type] ?? $windows_fonts['regular'];
        if (file_exists($win_font)) {
            return $win_font;
        }

        // Último fallback - GD built-in (retorna número da fonte)
        return 5;
    }
}
