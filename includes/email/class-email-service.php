<?php
/**
 * Email Service - Serviço de envio de emails
 *
 * @package EauSystem
 * @since   1.44.0
 */

namespace EauSystem\Email;

if (!defined('WPINC')) {
    die;
}

class Email_Service {

    /**
     * Registra preview endpoint
     */
    public static function register() {
        add_action('admin_init', [__CLASS__, 'handle_preview']);
    }

    /**
     * Preview: /wp-admin/?eau_email_preview=1
     */
    public static function handle_preview() {
        if (!isset($_GET['eau_email_preview']) || !current_user_can('manage_options')) {
            return;
        }

        $template = $_GET['template'] ?? 'event_registration';

        $data = [
            'name'           => $_GET['name'] ?? 'John Smith',
            'event_title'    => $_GET['event_title'] ?? 'TEST 2 - Members Only FIXED',
            'event_date'     => $_GET['event_date'] ?? 'Saturday, December 20, 2025',
            'event_location' => $_GET['event_location'] ?? 'TBA',
            'cpd_points'     => $_GET['cpd_points'] ?? '5',
            'cpd_category'   => $_GET['cpd_category'] ?? 'Professional Development',
            'certificate_url'=> $_GET['certificate_url'] ?? '#',
            'join_url'       => $_GET['join_url'] ?? '#',
        ];

        $content = self::get_template($template, $data);

        $options = [
            'cancel_text' => 'If you need to cancel your registration, please <a href="' . home_url('/dashboard/') . '">log in to your member portal</a>.',
        ];

        $html = Email_Template::render('Email Preview', $content, $options);

        echo $html;
        exit;
    }

    /**
     * Envia um email usando o template padrão
     *
     * @param string       $to      Email do destinatário
     * @param string       $subject Assunto
     * @param string       $content Conteúdo HTML (será inserido no template)
     * @param array        $options Opções adicionais
     * @return bool
     */
    public static function send($to, $subject, $content, $options = []) {
        // Renderiza o template completo
        $html = Email_Template::render($subject, $content, $options);

        // Headers
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . Email_Config::get_from_name() . ' <' . Email_Config::get_from_email() . '>',
        ];

        // Reply-To opcional
        if (!empty($options['reply_to'])) {
            $headers[] = 'Reply-To: ' . $options['reply_to'];
        }

        // CC opcional
        if (!empty($options['cc'])) {
            $cc = is_array($options['cc']) ? implode(',', $options['cc']) : $options['cc'];
            $headers[] = 'Cc: ' . $cc;
        }

        // BCC opcional
        if (!empty($options['bcc'])) {
            $bcc = is_array($options['bcc']) ? implode(',', $options['bcc']) : $options['bcc'];
            $headers[] = 'Bcc: ' . $bcc;
        }

        // Attachments opcional
        $attachments = $options['attachments'] ?? [];

        // Envia usando wp_mail
        $sent = wp_mail($to, $subject, $html, $headers, $attachments);

        // Log opcional
        if (!empty($options['log']) && $options['log'] === true) {
            self::log_email($to, $subject, $sent);
        }

        return $sent;
    }

    /**
     * Envia email para múltiplos destinatários
     *
     * @param array  $recipients Lista de emails
     * @param string $subject    Assunto
     * @param string $content    Conteúdo HTML
     * @param array  $options    Opções adicionais
     * @return array Resultados [email => bool]
     */
    public static function send_bulk($recipients, $subject, $content, $options = []) {
        $results = [];
        foreach ($recipients as $email) {
            $results[$email] = self::send($email, $subject, $content, $options);
        }
        return $results;
    }

    /**
     * Envia email com dados personalizados por destinatário
     *
     * @param array  $recipients Lista de [email => [name, ...data]]
     * @param string $subject    Assunto (pode conter {placeholders})
     * @param string $content    Conteúdo HTML (pode conter {placeholders})
     * @param array  $options    Opções adicionais
     * @return array Resultados [email => bool]
     */
    public static function send_personalized($recipients, $subject, $content, $options = []) {
        $results = [];

        foreach ($recipients as $email => $data) {
            // Substitui placeholders no assunto e conteúdo
            $personalized_subject = self::replace_placeholders($subject, $data);
            $personalized_content = self::replace_placeholders($content, $data);

            $results[$email] = self::send($email, $personalized_subject, $personalized_content, $options);
        }

        return $results;
    }

    /**
     * Substitui placeholders {key} pelos valores do array
     */
    private static function replace_placeholders($text, $data) {
        foreach ($data as $key => $value) {
            $text = str_replace('{' . $key . '}', $value, $text);
        }
        return $text;
    }

    /**
     * Log de email enviado
     */
    private static function log_email($to, $subject, $success) {
        $log = get_option('eau_email_log', []);
        $log[] = [
            'to'      => $to,
            'subject' => $subject,
            'success' => $success,
            'date'    => current_time('mysql'),
        ];
        // Mantém apenas últimos 100 registros
        $log = array_slice($log, -100);
        update_option('eau_email_log', $log);
    }

    /**
     * Templates pré-definidos
     */
    public static function get_template($template_name, $data = []) {
        switch ($template_name) {
            case 'welcome':
                return self::template_welcome($data);

            case 'certificate':
                return self::template_certificate($data);

            case 'event_registration':
                return self::template_event_registration($data);

            case 'event_reminder':
                return self::template_event_reminder($data);

            default:
                return '';
        }
    }

    /**
     * Template: Boas-vindas
     */
    private static function template_welcome($data) {
        $name = $data['name'] ?? 'Member';

        return "
            <h1>Welcome to English Australia!</h1>
            <p>Dear {$name},</p>
            <p>Thank you for joining English Australia. We're excited to have you as part of our community.</p>
            <p>Your account has been successfully created. You can now access all member benefits and resources.</p>
            " . Email_Template::button('Access Dashboard', home_url('/dashboard/')) . "
            <p>If you have any questions, please don't hesitate to contact us.</p>
        ";
    }

    /**
     * Template: Certificado
     */
    private static function template_certificate($data) {
        $name = $data['name'] ?? 'Participant';
        $event_title = $data['event_title'] ?? 'Event';
        $cpd_points = $data['cpd_points'] ?? '0';
        $cpd_category = $data['cpd_category'] ?? '';
        $certificate_url = $data['certificate_url'] ?? '#';

        return "
            <h1>Your Certificate is Ready!</h1>
            <p>Dear {$name},</p>
            <p>Congratulations on completing <strong>{$event_title}</strong>!</p>
            " . Email_Template::info_box($event_title, [
                'CPD Points' => $cpd_points,
                'Category' => $cpd_category,
            ]) . "
            <p>Your certificate of attendance has been generated and is ready for download.</p>
            " . Email_Template::button('Download Certificate', $certificate_url) . "
            <p>This activity has been recorded in your CPD history.</p>
        ";
    }

    /**
     * Template: Registro em evento
     */
    private static function template_event_registration($data) {
        $name = $data['name'] ?? 'Participant';
        $event_title = $data['event_title'] ?? 'Event';
        $event_date = $data['event_date'] ?? '';
        $event_location = $data['event_location'] ?? 'TBA';

        return "
            <h1>Registration Confirmed!</h1>
            <p>Dear {$name},</p>
            <p>Your registration for the following event has been confirmed:</p>
            " . Email_Template::info_box($event_title, [
                'Date' => $event_date,
                'Location' => $event_location,
            ]) . "
            <p>We look forward to seeing you at the event!</p>
        ";
    }

    /**
     * Template: Lembrete de evento
     */
    private static function template_event_reminder($data) {
        $name = $data['name'] ?? 'Participant';
        $event_title = $data['event_title'] ?? 'Event';
        $event_date = $data['event_date'] ?? '';
        $event_location = $data['event_location'] ?? 'TBA';
        $join_url = $data['join_url'] ?? '';

        $content = "
            <h1>Event Reminder</h1>
            <p>Dear {$name},</p>
            <p>This is a friendly reminder that your event is coming up!</p>
            " . Email_Template::info_box($event_title, [
                'Date' => $event_date,
                'Location' => $event_location,
            ]) . "
            <p>We look forward to seeing you!</p>
        ";

        if (!empty($join_url)) {
            $content .= Email_Template::button('Join Event', $join_url);
        }

        return $content;
    }
}
