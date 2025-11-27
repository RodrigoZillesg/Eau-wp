<?php
/**
 * Email Events - Envio de emails para eventos
 *
 * @package EauSystem
 * @since   1.44.0
 */

namespace EauSystem\Email;

use EauSystem\EventRegistrations\Config;

if (!defined('WPINC')) {
    die;
}

class Email_Events {

    /**
     * Registra hooks e crons
     */
    public static function register() {
        // Cron para lembretes
        add_action('eau_email_reminders_cron', [__CLASS__, 'process_reminders']);

        // Agendar cron se não existir
        if (!wp_next_scheduled('eau_email_reminders_cron')) {
            wp_schedule_event(time(), 'hourly', 'eau_email_reminders_cron');
        }
    }

    /**
     * Busca todos os registros de um evento
     *
     * @param int   $event_id ID do evento
     * @param array $statuses Status permitidos (default: confirmed, pending)
     * @return array Lista de registros
     */
    public static function get_event_registrations($event_id, $statuses = ['confirmed', 'pending']) {
        $prefix = Config\META_PREFIX;

        $meta_query = [
            [
                'key'   => $prefix . 'event_id',
                'value' => $event_id,
            ],
        ];

        if (!empty($statuses)) {
            $meta_query[] = [
                'key'     => $prefix . 'status',
                'value'   => $statuses,
                'compare' => 'IN',
            ];
        }

        $query = new \WP_Query([
            'post_type'      => Config\POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => $meta_query,
        ]);

        return $query->posts;
    }

    /**
     * Obtém dados do evento formatados
     */
    public static function get_event_data($event_id) {
        $event = get_post($event_id);
        if (!$event) {
            return null;
        }

        $start = get_post_meta($event_id, 'evt_start_datetime', true);
        $end = get_post_meta($event_id, 'evt_end_datetime', true);
        $location = get_post_meta($event_id, 'evt_location', true);
        $online_url = get_post_meta($event_id, 'evt_online_url', true);

        return [
            'id'           => $event_id,
            'title'        => $event->post_title,
            'start'        => $start,
            'end'          => $end,
            'start_formatted' => $start ? date_i18n('l, F j, Y', strtotime($start)) : '',
            'time_formatted'  => $start ? date_i18n('g:i A', strtotime($start)) : '',
            'location'     => $location ?: 'TBA',
            'online_url'   => $online_url,
            'url'          => get_permalink($event_id),
        ];
    }

    /**
     * Envia email de confirmação de registro
     *
     * @param int $registration_id ID do registro
     * @return bool
     */
    public static function send_registration_confirmation($registration_id) {
        $prefix = Config\META_PREFIX;

        $attendee_name = get_post_meta($registration_id, $prefix . 'attendee_name', true);
        $attendee_email = get_post_meta($registration_id, $prefix . 'attendee_email', true);
        $event_id = get_post_meta($registration_id, $prefix . 'event_id', true);

        if (!$attendee_email || !$event_id) {
            return false;
        }

        $event = self::get_event_data($event_id);
        if (!$event) {
            return false;
        }

        $content = Email_Service::get_template('event_registration', [
            'name'           => $attendee_name,
            'event_title'    => $event['title'],
            'event_date'     => $event['start_formatted'],
            'event_location' => $event['location'],
        ]);

        $options = [
            'cancel_text' => 'If you need to cancel your registration, please <a href="' . home_url('/dashboard/') . '">log in to your member portal</a>.',
        ];

        return Email_Service::send(
            $attendee_email,
            'Registration Confirmed - ' . $event['title'],
            $content,
            $options
        );
    }

    /**
     * Envia lembrete de evento
     *
     * @param int    $event_id ID do evento
     * @param string $reminder_type Tipo: 7_days, 3_days, 1_day, 1_hour, starting
     * @return array Resultados por email
     */
    public static function send_event_reminder($event_id, $reminder_type = '1_day') {
        $event = self::get_event_data($event_id);
        if (!$event) {
            return [];
        }

        $registrations = self::get_event_registrations($event_id);
        $results = [];
        $prefix = Config\META_PREFIX;

        // Título baseado no tipo de lembrete
        $subjects = [
            '7_days'   => 'Event Reminder: 1 Week to Go!',
            '3_days'   => 'Event Reminder: 3 Days to Go!',
            '1_day'    => 'Event Reminder: Tomorrow!',
            '1_hour'   => 'Event Starting Soon!',
            'starting' => 'Event is Starting Now!',
        ];

        $subject = ($subjects[$reminder_type] ?? 'Event Reminder') . ' - ' . $event['title'];

        foreach ($registrations as $registration) {
            $attendee_name = get_post_meta($registration->ID, $prefix . 'attendee_name', true);
            $attendee_email = get_post_meta($registration->ID, $prefix . 'attendee_email', true);

            if (!$attendee_email) {
                continue;
            }

            $content = Email_Service::get_template('event_reminder', [
                'name'           => $attendee_name,
                'event_title'    => $event['title'],
                'event_date'     => $event['start_formatted'] . ' at ' . $event['time_formatted'],
                'event_location' => $event['location'],
                'join_url'       => $event['online_url'] ?: $event['url'],
            ]);

            $results[$attendee_email] = Email_Service::send($attendee_email, $subject, $content);
        }

        // Marca que o lembrete foi enviado
        update_post_meta($event_id, 'evt_reminder_sent_' . $reminder_type, current_time('mysql'));

        return $results;
    }

    /**
     * Envia email de certificado
     *
     * @param int    $registration_id ID do registro
     * @param string $certificate_url URL do certificado
     * @return bool
     */
    public static function send_certificate_email($registration_id, $certificate_url) {
        $prefix = Config\META_PREFIX;

        $attendee_name = get_post_meta($registration_id, $prefix . 'attendee_name', true);
        $attendee_email = get_post_meta($registration_id, $prefix . 'attendee_email', true);
        $event_id = get_post_meta($registration_id, $prefix . 'event_id', true);

        if (!$attendee_email || !$event_id) {
            return false;
        }

        $event = self::get_event_data($event_id);
        if (!$event) {
            return false;
        }

        $cpd_points = get_post_meta($event_id, 'evt_cpd_points', true) ?: '0';
        $cpd_category = get_post_meta($event_id, 'evt_cpd_category', true) ?: '';

        $content = Email_Service::get_template('certificate', [
            'name'            => $attendee_name,
            'event_title'     => $event['title'],
            'cpd_points'      => $cpd_points,
            'cpd_category'    => $cpd_category,
            'certificate_url' => $certificate_url,
        ]);

        return Email_Service::send(
            $attendee_email,
            'Your Certificate is Ready - ' . $event['title'],
            $content
        );
    }

    /**
     * Envia email para todos os registrados de um evento
     *
     * @param int    $event_id ID do evento
     * @param string $subject  Assunto
     * @param string $content  Conteúdo HTML
     * @param array  $options  Opções extras
     * @return array Resultados
     */
    public static function send_to_event_registrations($event_id, $subject, $content, $options = []) {
        $registrations = self::get_event_registrations($event_id);
        $results = [];
        $prefix = Config\META_PREFIX;

        foreach ($registrations as $registration) {
            $attendee_name = get_post_meta($registration->ID, $prefix . 'attendee_name', true);
            $attendee_email = get_post_meta($registration->ID, $prefix . 'attendee_email', true);

            if (!$attendee_email) {
                continue;
            }

            // Substitui placeholders
            $personalized_content = str_replace('{name}', $attendee_name, $content);
            $personalized_subject = str_replace('{name}', $attendee_name, $subject);

            $results[$attendee_email] = Email_Service::send(
                $attendee_email,
                $personalized_subject,
                $personalized_content,
                $options
            );
        }

        return $results;
    }

    /**
     * Processa lembretes agendados (executado via cron)
     */
    public static function process_reminders() {
        $now = current_time('timestamp');

        // Busca eventos futuros
        $events = get_posts([
            'post_type'      => 'eau_event',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => [
                [
                    'key'     => 'evt_start_datetime',
                    'value'   => current_time('Y-m-d\TH:i'),
                    'compare' => '>=',
                    'type'    => 'DATETIME',
                ],
            ],
        ]);

        foreach ($events as $event) {
            $start = get_post_meta($event->ID, 'evt_start_datetime', true);
            if (!$start) {
                continue;
            }

            $start_timestamp = strtotime($start);
            $diff_hours = ($start_timestamp - $now) / 3600;

            // 7 dias antes (168 horas)
            if ($diff_hours <= 168 && $diff_hours > 167) {
                if (!get_post_meta($event->ID, 'evt_reminder_sent_7_days', true)) {
                    self::send_event_reminder($event->ID, '7_days');
                }
            }

            // 3 dias antes (72 horas)
            if ($diff_hours <= 72 && $diff_hours > 71) {
                if (!get_post_meta($event->ID, 'evt_reminder_sent_3_days', true)) {
                    self::send_event_reminder($event->ID, '3_days');
                }
            }

            // 1 dia antes (24 horas)
            if ($diff_hours <= 24 && $diff_hours > 23) {
                if (!get_post_meta($event->ID, 'evt_reminder_sent_1_day', true)) {
                    self::send_event_reminder($event->ID, '1_day');
                }
            }

            // 1 hora antes
            if ($diff_hours <= 1 && $diff_hours > 0) {
                if (!get_post_meta($event->ID, 'evt_reminder_sent_1_hour', true)) {
                    self::send_event_reminder($event->ID, '1_hour');
                }
            }

            // Evento começando (0 a -1 hora)
            if ($diff_hours <= 0 && $diff_hours > -1) {
                if (!get_post_meta($event->ID, 'evt_reminder_sent_starting', true)) {
                    self::send_event_reminder($event->ID, 'starting');
                }
            }
        }
    }

    /**
     * Limpa flags de lembretes (útil para reenviar)
     */
    public static function reset_reminder_flags($event_id) {
        delete_post_meta($event_id, 'evt_reminder_sent_7_days');
        delete_post_meta($event_id, 'evt_reminder_sent_3_days');
        delete_post_meta($event_id, 'evt_reminder_sent_1_day');
        delete_post_meta($event_id, 'evt_reminder_sent_1_hour');
        delete_post_meta($event_id, 'evt_reminder_sent_starting');
    }
}
