<?php
/**
 * Certificate Renderer - Desenha elementos no certificado
 *
 * @package EauSystem
 * @since   1.43.9
 */

namespace EauSystem\EventRegistrations\Certificate;

if (!defined('WPINC')) {
    die;
}

class Certificate_Renderer {

    private $image;
    private $colors = [];

    public function __construct($image) {
        $this->image = $image;
        $this->init_colors();
    }

    private function init_colors() {
        $this->colors['blue'] = imagecolorallocate($this->image, ...Certificate_Config::COLOR_BLUE);
        $this->colors['blue_light'] = imagecolorallocate($this->image, 100, 149, 237); // Azul claro
        $this->colors['gold'] = imagecolorallocate($this->image, ...Certificate_Config::COLOR_GOLD);
        $this->colors['dark'] = imagecolorallocate($this->image, ...Certificate_Config::COLOR_DARK);
        $this->colors['gray'] = imagecolorallocate($this->image, ...Certificate_Config::COLOR_GRAY);
        $this->colors['white'] = imagecolorallocate($this->image, ...Certificate_Config::COLOR_WHITE);
    }

    public function fill_background() {
        imagefill($this->image, 0, 0, $this->colors['white']);
    }

    public function draw_corner_shapes() {
        $w = Certificate_Config::WIDTH;
        $h = Certificate_Config::HEIGHT;

        // Triângulo superior esquerdo (azul escuro)
        $points_tl = [0, 0, 300, 0, 0, 300];
        imagefilledpolygon($this->image, $points_tl, $this->colors['blue']);

        // Triângulo menor superior esquerdo (azul claro)
        $points_tl2 = [0, 150, 150, 0, 0, 0];
        imagefilledpolygon($this->image, $points_tl2, $this->colors['blue_light']);

        // Triângulo inferior direito (azul escuro)
        $points_br = [$w, $h, $w - 300, $h, $w, $h - 300];
        imagefilledpolygon($this->image, $points_br, $this->colors['blue']);

        // Triângulo menor inferior direito (azul claro)
        $points_br2 = [$w, $h - 150, $w - 150, $h, $w, $h];
        imagefilledpolygon($this->image, $points_br2, $this->colors['blue_light']);
    }

    public function draw_text_centered($text, $size, $color_key, $y, $font_type = 'regular') {
        $font = Certificate_Config::get_font($font_type);
        $color = $this->colors[$color_key];

        $bbox = imagettfbbox($size, 0, $font, $text);
        $x = (Certificate_Config::WIDTH - abs($bbox[4] - $bbox[0])) / 2;

        imagettftext($this->image, $size, 0, $x, $y, $color, $font, $text);
    }

    public function draw_text_left($text, $size, $color_key, $x, $y, $font_type = 'regular') {
        $font = Certificate_Config::get_font($font_type);
        imagettftext($this->image, $size, 0, $x, $y, $this->colors[$color_key], $font, $text);
    }

    public function draw_signature($x, $y, $max_width = 120) {
        $sig_path = EAU_SYSTEM_PLUGIN_DIR . 'includes/event-registrations/certificate/rubrica.png';

        if (!file_exists($sig_path)) {
            return;
        }

        $sig = imagecreatefrompng($sig_path);
        if (!$sig) {
            return;
        }

        // Preserva transparência
        imagealphablending($this->image, true);
        imagesavealpha($this->image, true);

        $orig_w = imagesx($sig);
        $orig_h = imagesy($sig);

        $ratio = $max_width / $orig_w;
        $new_w = $max_width;
        $new_h = (int)($orig_h * $ratio);

        imagecopyresampled($this->image, $sig, $x, $y - $new_h, 0, 0, $new_w, $new_h, $orig_w, $orig_h);
        imagedestroy($sig);
    }

    public function draw_logo($x, $y, $max_width = 200) {
        $logo_path = EAU_SYSTEM_PLUGIN_DIR . 'includes/event-registrations/certificate/english-australia-logo-500.png';

        if (!file_exists($logo_path)) {
            return;
        }

        $logo = imagecreatefrompng($logo_path);
        if (!$logo) {
            return;
        }

        // Preserva transparência
        imagealphablending($this->image, true);
        imagesavealpha($this->image, true);

        $orig_w = imagesx($logo);
        $orig_h = imagesy($logo);

        $ratio = $max_width / $orig_w;
        $new_w = $max_width;
        $new_h = (int)($orig_h * $ratio);

        imagecopyresampled($this->image, $logo, $x, $y, 0, 0, $new_w, $new_h, $orig_w, $orig_h);
        imagedestroy($logo);
    }
}
