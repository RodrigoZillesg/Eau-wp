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
        $left = 350;

        // Logo no topo
        $r->draw_logo($left, 100, 154);

        // Título
        $r->draw_text_left('CERTIFICATE OF', 38, 'blue', $left, 320, 'bold');
        $r->draw_text_left('ATTENDANCE', 38, 'blue', $left, 370, 'bold');

        // Texto introdutório
        $r->draw_text_left('This certificate is awarded to', 18, 'gray', $left, 440);

        // Nome do participante
        $full_name = trim($data['first_name'] . ' ' . ($data['last_name'] ?? ''));
        $r->draw_text_left($full_name, 36, 'dark', $left, 500, 'bold');

        // Evento
        $r->draw_text_left('For attendance at', 16, 'gray', $left, 570);
        $r->draw_text_left($data['event_title'], 20, 'blue', $left, 605, 'bold');

        // Data e CPD
        $y = 670;
        if (!empty($data['event_date'])) {
            $r->draw_text_left('Date', 14, 'gray', $left, $y);
            $r->draw_text_left($data['event_date'], 16, 'dark', $left, $y + 25);
        }

        if (!empty($data['cpd_points'])) {
            $r->draw_text_left('CPD Points: ' . $data['cpd_points'], 16, 'dark', $left + 300, $y + 25);
            if (!empty($data['cpd_category'])) {
                $r->draw_text_left('(' . $data['cpd_category'] . ')', 14, 'gray', $left + 300, $y + 50);
            }
        }

        // Assinatura
        $sig_y = 800;
        $r->draw_signature($left, $sig_y);
        $r->draw_text_left(Certificate_Config::SIGNER_NAME, 14, 'dark', $left, $sig_y + 30);
        $r->draw_text_left(Certificate_Config::SIGNER_TITLE, 12, 'gray', $left, $sig_y + 50);

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
