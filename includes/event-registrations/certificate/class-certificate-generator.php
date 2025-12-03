<?php
/**
 * Certificate Generator - Classe principal
 *
 * @package EauSystem
 * @since   1.43.9
 */

namespace EauSystem\EventRegistrations\Certificate;

if (!defined('WPINC')) {
    die;
}

class Certificate_Generator {

    /**
     * Registra preview endpoint
     */
    public static function register() {
        add_action('admin_init', [__CLASS__, 'handle_preview']);
    }

    /**
     * Preview: /wp-admin/?eau_certificate_preview=1
     */
    public static function handle_preview() {
        if (!isset($_GET['eau_certificate_preview']) || !current_user_can('manage_options')) {
            return;
        }

        $data = [
            'first_name'   => $_GET['first_name'] ?? 'John',
            'last_name'    => $_GET['last_name'] ?? 'Smith',
            'event_title'  => $_GET['event_title'] ?? 'Professional Development Workshop',
            'event_date'   => $_GET['event_date'] ?? 'November 28, 2025',
            'cpd_points'   => $_GET['cpd_points'] ?? '5',
            'cpd_category' => $_GET['cpd_category'] ?? 'Professional Development',
        ];

        $image = self::create_image($data);

        header('Content-Type: image/png');
        imagepng($image);
        imagedestroy($image);
        exit;
    }

    /**
     * Gera certificado e salva na mídia do WP
     *
     * @param array $data Dados do certificado
     * @return int|false ID da mídia ou false
     */
    public static function generate($data) {
        if (!extension_loaded('gd')) {
            return false;
        }

        $image = self::create_image($data);
        if (!$image) {
            return false;
        }

        // Salva arquivo
        $upload_dir = wp_upload_dir();
        $filename = 'certificate-' . sanitize_title($data['first_name'] . '-' . $data['last_name']) . '-' . time() . '.png';
        $filepath = $upload_dir['path'] . '/' . $filename;

        imagepng($image, $filepath, 9);
        imagedestroy($image);

        // Adiciona à mídia
        return self::add_to_media($filepath, $filename, $data);
    }

    /**
     * Cria a imagem do certificado
     */
    private static function create_image($data) {
        $w = Certificate_Config::WIDTH;
        $h = Certificate_Config::HEIGHT;

        $image = imagecreatetruecolor($w, $h);
        imageantialias($image, true);

        $r = new Certificate_Renderer($image);

        // Background e formas geométricas
        $r->fill_background();
        $r->draw_corner_shapes();

        // Margem esquerda para conteúdo
        $left = 320;

        // Logo no topo
        $r->draw_logo($left, 80, 180);

        // Título - fontes maiores
        $r->draw_text_left('CERTIFICATE OF', 52, 'blue', $left, 310, 'bold');
        $r->draw_text_left('ATTENDANCE', 52, 'blue', $left, 375, 'bold');

        // Texto introdutório
        $r->draw_text_left('This certificate is awarded to', 22, 'gray', $left, 460);

        // Nome do participante - fonte maior
        $full_name = trim($data['first_name'] . ' ' . ($data['last_name'] ?? ''));
        $r->draw_text_left($full_name, 48, 'dark', $left, 530, 'bold');

        // Evento
        $r->draw_text_left('For attendance at', 20, 'gray', $left, 610);
        $r->draw_text_left($data['event_title'], 28, 'blue', $left, 655, 'bold');

        // Data e CPD
        $y = 730;
        if (!empty($data['event_date'])) {
            $r->draw_text_left('Date', 18, 'gray', $left, $y);
            $r->draw_text_left($data['event_date'], 22, 'dark', $left, $y + 30);
        }

        if (!empty($data['cpd_points'])) {
            $r->draw_text_left('CPD Points: ' . $data['cpd_points'], 22, 'dark', $left + 350, $y + 30);
            if (!empty($data['cpd_category'])) {
                $r->draw_text_left('(' . $data['cpd_category'] . ')', 18, 'gray', $left + 350, $y + 60);
            }
        }

        // Assinatura
        $sig_y = 870;
        $r->draw_signature($left, $sig_y, 140);
        $r->draw_text_left(Certificate_Config::SIGNER_NAME, 18, 'dark', $left, $sig_y + 35);
        $r->draw_text_left(Certificate_Config::SIGNER_TITLE, 16, 'gray', $left, $sig_y + 58);

        return $image;
    }

    /**
     * Adiciona à biblioteca de mídia
     */
    private static function add_to_media($filepath, $filename, $data) {
        $filetype = wp_check_filetype($filename);

        $attachment_id = wp_insert_attachment([
            'guid'           => wp_upload_dir()['url'] . '/' . $filename,
            'post_mime_type' => $filetype['type'],
            'post_title'     => 'Certificate - ' . $data['first_name'] . ' ' . ($data['last_name'] ?? ''),
            'post_status'    => 'inherit',
        ], $filepath);

        if (is_wp_error($attachment_id)) {
            return false;
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        wp_update_attachment_metadata($attachment_id, wp_generate_attachment_metadata($attachment_id, $filepath));

        return $attachment_id;
    }
}
