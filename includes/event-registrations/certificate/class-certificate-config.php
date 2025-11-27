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
        $fonts = [
            'regular' => 'C:/Windows/Fonts/arial.ttf',
            'bold'    => 'C:/Windows/Fonts/arialbd.ttf',
            'italic'  => 'C:/Windows/Fonts/ariali.ttf',
        ];

        $font = $fonts[$type] ?? $fonts['regular'];

        // Fallback Linux
        if (!file_exists($font)) {
            return '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';
        }

        return $font;
    }
}
