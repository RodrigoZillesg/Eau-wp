<?php
namespace EauSystem;

use EauSystem\Email\Email_Service;

/**
 * Cron para verificar e notificar expiração de membership de instituições
 *
 * Envia emails para superAdmin e Admin quando o membership de uma instituição
 * está próximo de expirar.
 *
 * @since 1.63.2
 */
class Eau_Institutions_Expiration_Cron {

    /**
     * Nome do evento cron
     */
    const CRON_HOOK = 'eau_check_institutions_expiration';

    /**
     * Opção para rastrear notificações enviadas
     */
    const NOTIFICATIONS_OPTION = 'eau_institutions_expiration_notifications';

    /**
     * Dias antes da expiração para enviar notificações
     */
    const NOTIFICATION_DAYS = array(90, 60, 30, 14, 7, 1, 0);

    /**
     * Inicializa o cron
     */
    public static function init() {
        // Registra o hook do cron
        add_action(self::CRON_HOOK, array(__CLASS__, 'check_expirations'));

        // Agenda o cron se não estiver agendado
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time(), 'daily', self::CRON_HOOK);
        }
    }

    /**
     * Remove o agendamento do cron (ao desativar plugin)
     */
    public static function unschedule() {
        $timestamp = wp_next_scheduled(self::CRON_HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::CRON_HOOK);
        }
    }

    /**
     * Verifica expirações e envia notificações
     */
    public static function check_expirations() {
        // Busca todas as instituições com data de expiração
        $institutions = get_posts(array(
            'post_type'      => 'institutions',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_query'     => array(
                array(
                    'key'     => 'ins_member_expire_date',
                    'value'   => '',
                    'compare' => '!=',
                ),
            ),
        ));

        if (empty($institutions)) {
            return;
        }

        $notifications_sent = get_option(self::NOTIFICATIONS_OPTION, array());
        $today = date('Y-m-d');

        foreach ($institutions as $institution) {
            $expire_date = get_post_meta($institution->ID, 'ins_member_expire_date', true);
            if (empty($expire_date)) {
                continue;
            }

            $days_until = self::calculate_days_until($expire_date);

            // Se expirou, muda o status para pending automaticamente
            if ($days_until < 0) {
                self::update_expired_institution_status($institution->ID);
            }

            // Verifica se deve enviar notificação
            foreach (self::NOTIFICATION_DAYS as $threshold) {
                if ($days_until <= $threshold) {
                    $notification_key = $institution->ID . '_' . $threshold;

                    // Verifica se já enviou esta notificação
                    if (isset($notifications_sent[$notification_key])) {
                        continue;
                    }

                    // Envia notificação
                    self::send_expiration_notification($institution, $days_until, $threshold);

                    // Marca como enviada
                    $notifications_sent[$notification_key] = $today;
                    break; // Envia apenas uma notificação por instituição por dia
                }
            }
        }

        // Salva notificações enviadas
        update_option(self::NOTIFICATIONS_OPTION, $notifications_sent);

        // Limpa notificações antigas (mais de 1 ano)
        self::cleanup_old_notifications();
    }

    /**
     * Calcula dias até a expiração
     */
    private static function calculate_days_until($expire_date) {
        $expire_timestamp = strtotime($expire_date);
        $today_timestamp = strtotime('today');
        return floor(($expire_timestamp - $today_timestamp) / (60 * 60 * 24));
    }

    /**
     * Atualiza o status de uma instituição expirada para "pending"
     *
     * @param int $institution_id ID da instituição
     */
    private static function update_expired_institution_status($institution_id) {
        $current_status = get_post_meta($institution_id, 'ins_status', true);

        // Só atualiza se o status atual for "active"
        if ($current_status === 'active') {
            update_post_meta($institution_id, 'ins_status', 'pending');

            // Log para debug
            $company_name = get_post_meta($institution_id, 'ins_company_name', true);
            error_log("Eau System: Institution '{$company_name}' (ID: {$institution_id}) status changed from 'active' to 'pending' due to expired membership.");
        }
    }

    /**
     * Envia notificação de expiração
     */
    private static function send_expiration_notification($institution, $days_until, $threshold) {
        $company_name = get_post_meta($institution->ID, 'ins_company_name', true) ?: $institution->post_title;
        $expire_date = get_post_meta($institution->ID, 'ins_member_expire_date', true);
        $expire_formatted = date('F j, Y', strtotime($expire_date));

        // Busca admins para notificar
        $admins = self::get_admin_users();

        if (empty($admins)) {
            return;
        }

        // Define urgência baseada nos dias
        $urgency = self::get_urgency_level($days_until);

        // Monta o assunto
        $subject = self::get_email_subject($company_name, $days_until, $urgency);

        // Monta o conteúdo HTML
        $html_content = self::get_email_content($institution, $company_name, $expire_formatted, $days_until, $urgency);

        // Envia para cada admin
        foreach ($admins as $admin) {
            Email_Service::send($admin->user_email, $subject, $html_content);
        }

        // Log para debug
        error_log("Eau System: Sent expiration notification for institution '{$company_name}' ({$days_until} days) to " . count($admins) . " admins");
    }

    /**
     * Busca usuários admin (superAdmin e Admin)
     */
    private static function get_admin_users() {
        $users = get_users(array(
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key'     => 'mem_type',
                    'value'   => 'superAdmin',
                    'compare' => '=',
                ),
                array(
                    'key'     => 'mem_type',
                    'value'   => 'Admin',
                    'compare' => '=',
                ),
            ),
        ));

        return $users;
    }

    /**
     * Define nível de urgência
     */
    private static function get_urgency_level($days_until) {
        if ($days_until <= 0) {
            return 'expired';
        }
        if ($days_until <= 7) {
            return 'critical';
        }
        if ($days_until <= 30) {
            return 'warning';
        }
        return 'info';
    }

    /**
     * Gera assunto do email
     */
    private static function get_email_subject($company_name, $days_until, $urgency) {
        $emoji = '';
        $prefix = '';

        switch ($urgency) {
            case 'expired':
                $emoji = '🚨';
                $prefix = '[EXPIRED]';
                break;
            case 'critical':
                $emoji = '⚠️';
                $prefix = '[URGENT]';
                break;
            case 'warning':
                $emoji = '⏰';
                $prefix = '[REMINDER]';
                break;
            default:
                $emoji = '📅';
                $prefix = '[NOTICE]';
        }

        if ($days_until <= 0) {
            return "{$emoji} {$prefix} Institution Membership Expired: {$company_name}";
        }

        if ($days_until === 1) {
            return "{$emoji} {$prefix} Institution Membership Expires Tomorrow: {$company_name}";
        }

        return "{$emoji} {$prefix} Institution Membership Expires in {$days_until} Days: {$company_name}";
    }

    /**
     * Gera conteúdo HTML do email
     */
    private static function get_email_content($institution, $company_name, $expire_formatted, $days_until, $urgency) {
        $institution_id = $institution->ID;
        $company_code = get_post_meta($institution_id, 'ins_company_id', true);
        $institution_type = get_post_meta($institution_id, 'ins_type', true);
        $email = get_post_meta($institution_id, 'ins_company_email', true);
        $campus = get_post_meta($institution_id, 'ins_member_company_campus', true);

        // Cores baseadas na urgência
        $colors = array(
            'expired'  => array('bg' => '#fef2f2', 'border' => '#dc2626', 'text' => '#991b1b'),
            'critical' => array('bg' => '#fef2f2', 'border' => '#dc2626', 'text' => '#991b1b'),
            'warning'  => array('bg' => '#fffbeb', 'border' => '#f59e0b', 'text' => '#92400e'),
            'info'     => array('bg' => '#eff6ff', 'border' => '#3b82f6', 'text' => '#1e40af'),
        );

        $color = $colors[$urgency];

        // URL para a página da instituição
        $institution_url = home_url('/institution/' . $institution_id . '/');
        $manage_url = home_url('/dashboard/manage-institutions/');

        // Mensagem principal
        $main_message = '';
        if ($days_until <= 0) {
            $main_message = "The membership for <strong>{$company_name}</strong> has <strong>expired</strong> on {$expire_formatted}.";
        } elseif ($days_until === 1) {
            $main_message = "The membership for <strong>{$company_name}</strong> will expire <strong>tomorrow</strong> ({$expire_formatted}).";
        } else {
            $main_message = "The membership for <strong>{$company_name}</strong> will expire in <strong>{$days_until} days</strong> ({$expire_formatted}).";
        }

        $html = <<<HTML
<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; max-width: 600px; margin: 0 auto;">

    <!-- Alert Box -->
    <div style="background: {$color['bg']}; border-left: 4px solid {$color['border']}; padding: 16px 20px; margin-bottom: 24px; border-radius: 0 8px 8px 0;">
        <p style="margin: 0; color: {$color['text']}; font-size: 15px; line-height: 1.6;">
            {$main_message}
        </p>
    </div>

    <!-- Institution Details -->
    <div style="background: #f9fafb; border-radius: 8px; padding: 20px; margin-bottom: 24px;">
        <h3 style="margin: 0 0 16px 0; font-size: 14px; text-transform: uppercase; color: #6b7280; letter-spacing: 0.05em;">
            Institution Details
        </h3>

        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-size: 14px; width: 140px;">Institution Name:</td>
                <td style="padding: 8px 0; color: #111827; font-size: 14px; font-weight: 500;">{$company_name}</td>
            </tr>
HTML;

        if (!empty($company_code)) {
            $html .= <<<HTML
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-size: 14px;">Code:</td>
                <td style="padding: 8px 0; color: #111827; font-size: 14px;">{$company_code}</td>
            </tr>
HTML;
        }

        if (!empty($institution_type)) {
            $html .= <<<HTML
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-size: 14px;">Type:</td>
                <td style="padding: 8px 0; color: #111827; font-size: 14px;">{$institution_type}</td>
            </tr>
HTML;
        }

        if (!empty($campus)) {
            $html .= <<<HTML
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-size: 14px;">Campus:</td>
                <td style="padding: 8px 0; color: #111827; font-size: 14px;">{$campus}</td>
            </tr>
HTML;
        }

        if (!empty($email)) {
            $html .= <<<HTML
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-size: 14px;">Contact Email:</td>
                <td style="padding: 8px 0; color: #111827; font-size: 14px;">
                    <a href="mailto:{$email}" style="color: #2563eb; text-decoration: none;">{$email}</a>
                </td>
            </tr>
HTML;
        }

        $html .= <<<HTML
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-size: 14px;">Expiration Date:</td>
                <td style="padding: 8px 0; color: {$color['text']}; font-size: 14px; font-weight: 600;">{$expire_formatted}</td>
            </tr>
        </table>
    </div>

    <!-- Action Buttons -->
    <div style="text-align: center; margin-bottom: 24px;">
        <a href="{$institution_url}" style="display: inline-block; background: #2563eb; color: #ffffff; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-size: 14px; font-weight: 500; margin-right: 12px;">
            View Institution
        </a>
        <a href="{$manage_url}" style="display: inline-block; background: #f3f4f6; color: #374151; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-size: 14px; font-weight: 500;">
            Manage Institutions
        </a>
    </div>

    <!-- Footer -->
    <div style="border-top: 1px solid #e5e7eb; padding-top: 16px; text-align: center;">
        <p style="margin: 0; color: #9ca3af; font-size: 12px;">
            This is an automated notification from Eau System.
        </p>
    </div>

</div>
HTML;

        return $html;
    }

    /**
     * Limpa notificações antigas (mais de 1 ano)
     */
    private static function cleanup_old_notifications() {
        $notifications = get_option(self::NOTIFICATIONS_OPTION, array());
        $one_year_ago = date('Y-m-d', strtotime('-1 year'));

        $cleaned = array();
        foreach ($notifications as $key => $date) {
            if ($date > $one_year_ago) {
                $cleaned[$key] = $date;
            }
        }

        if (count($cleaned) !== count($notifications)) {
            update_option(self::NOTIFICATIONS_OPTION, $cleaned);
        }
    }

    /**
     * Força verificação manual (para debug/teste)
     */
    public static function force_check() {
        self::check_expirations();
    }

    /**
     * Reseta notificações enviadas (para debug/teste)
     */
    public static function reset_notifications() {
        delete_option(self::NOTIFICATIONS_OPTION);
    }
}
