<?php
/**
 * Email Membership - Envio de emails para membership
 *
 * @package EauSystem
 * @since   1.49.8
 */

namespace EauSystem\Email;

if (!defined('WPINC')) {
    die;
}

class Email_Membership {

    /**
     * Registra hooks
     */
    public static function register() {
        // Hooks podem ser adicionados aqui se necessário
    }

    /**
     * Envia email de confirmação de aplicação recebida (para o usuário)
     *
     * @param int $application_id ID da aplicação
     * @return bool
     */
    public static function send_application_received($application_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'eau_membership_applications';

        $application = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE application_id = %d",
            $application_id
        ));

        if (!$application) {
            return false;
        }

        // Busca o label do tipo de membership
        $membership_type_label = self::get_membership_type_label($application->membership_type);

        $content = self::template_application_received([
            'name'            => $application->first_name,
            'full_name'       => $application->first_name . ' ' . $application->last_name,
            'membership_type' => $membership_type_label,
            'submitted_at'    => date_i18n('F j, Y \a\t g:i A', strtotime($application->submitted_at)),
        ]);

        return Email_Service::send(
            $application->email,
            'Membership Application Received - English Australia',
            $content
        );
    }

    /**
     * Envia notificação de nova aplicação para admin
     *
     * @param int $application_id ID da aplicação
     * @return bool
     */
    public static function send_application_notification_to_admin($application_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'eau_membership_applications';

        $application = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE application_id = %d",
            $application_id
        ));

        if (!$application) {
            return false;
        }

        // Busca o label do tipo de membership
        $membership_type_label = self::get_membership_type_label($application->membership_type);

        // Email do admin para notificações de membership
        $admin_email = get_option('eau_membership_admin_email', get_option('admin_email'));

        $content = self::template_application_notification_admin([
            'applicant_name'  => $application->first_name . ' ' . $application->last_name,
            'applicant_email' => $application->email,
            'membership_type' => $membership_type_label,
            'company_name'    => $application->company_name,
            'submitted_at'    => date_i18n('F j, Y \a\t g:i A', strtotime($application->submitted_at)),
            'review_url'      => home_url('/dashboard/membership-applications/'),
        ]);

        return Email_Service::send(
            $admin_email,
            'New Membership Application - ' . $application->first_name . ' ' . $application->last_name,
            $content
        );
    }

    /**
     * Envia email de aplicação aprovada
     *
     * @param int    $application_id ID da aplicação
     * @param string $admin_message  Mensagem opcional do admin
     * @return bool
     */
    public static function send_application_approved($application_id, $admin_message = '') {
        global $wpdb;
        $table = $wpdb->prefix . 'eau_membership_applications';

        $application = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE application_id = %d",
            $application_id
        ));

        if (!$application) {
            return false;
        }

        // Busca o label do tipo de membership
        $membership_type_label = self::get_membership_type_label($application->membership_type);

        $content = self::template_application_approved([
            'name'            => $application->first_name,
            'full_name'       => $application->first_name . ' ' . $application->last_name,
            'membership_type' => $membership_type_label,
            'admin_message'   => $admin_message,
            'dashboard_url'   => home_url('/dashboard/'),
        ]);

        return Email_Service::send(
            $application->email,
            'Congratulations! Your Membership Application Has Been Approved',
            $content
        );
    }

    /**
     * Envia email de aplicação rejeitada
     *
     * @param int    $application_id   ID da aplicação
     * @param string $rejection_reason Motivo da rejeição
     * @return bool
     */
    public static function send_application_rejected($application_id, $rejection_reason = '') {
        global $wpdb;
        $table = $wpdb->prefix . 'eau_membership_applications';

        $application = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE application_id = %d",
            $application_id
        ));

        if (!$application) {
            return false;
        }

        // Busca o label do tipo de membership
        $membership_type_label = self::get_membership_type_label($application->membership_type);

        $content = self::template_application_rejected([
            'name'              => $application->first_name,
            'full_name'         => $application->first_name . ' ' . $application->last_name,
            'membership_type'   => $membership_type_label,
            'rejection_reason'  => $rejection_reason,
            'contact_email'     => Email_Config::get_from_email(),
        ]);

        return Email_Service::send(
            $application->email,
            'Update on Your Membership Application - English Australia',
            $content
        );
    }

    /**
     * Envia email de aplicação cancelada
     *
     * Enviado quando um admin cancela uma aplicação que já havia sido aprovada.
     *
     * @since 1.51.46
     * @param int    $application_id      ID da aplicação
     * @param string $cancellation_reason Motivo do cancelamento
     * @return bool
     */
    public static function send_application_cancelled($application_id, $cancellation_reason = '') {
        global $wpdb;
        $table = $wpdb->prefix . 'eau_membership_applications';

        $application = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE application_id = %d",
            $application_id
        ));

        if (!$application) {
            return false;
        }

        // Busca o label do tipo de membership
        $membership_type_label = self::get_membership_type_label($application->membership_type);

        $content = self::template_application_cancelled([
            'name'                 => $application->first_name,
            'full_name'            => $application->first_name . ' ' . $application->last_name,
            'membership_type'      => $membership_type_label,
            'cancellation_reason'  => $cancellation_reason,
            'contact_email'        => Email_Config::get_from_email(),
        ]);

        return Email_Service::send(
            $application->email,
            'Membership Status Update - English Australia',
            $content
        );
    }

    /**
     * Envia email de lembrete de expiração de membership
     *
     * @param int    $user_id      ID do usuário
     * @param string $reminder_type Tipo: 60_days, 30_days, 7_days, expired
     * @return bool
     */
    public static function send_expiry_reminder($user_id, $reminder_type = '30_days') {
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }

        $membership_type = get_user_meta($user_id, 'mem_membership_type', true);
        $expiry_date = get_user_meta($user_id, 'mem_membership_expiry_date', true);

        if (!$membership_type || !$expiry_date) {
            return false;
        }

        $membership_type_label = self::get_membership_type_label($membership_type);
        $first_name = get_user_meta($user_id, 'first_name', true) ?: $user->display_name;

        // Títulos baseados no tipo de lembrete
        $subjects = [
            '60_days' => 'Membership Renewal Reminder - 60 Days',
            '30_days' => 'Membership Renewal Reminder - 30 Days',
            '7_days'  => 'Urgent: Membership Expiring in 7 Days',
            'expired' => 'Your Membership Has Expired',
        ];

        $subject = $subjects[$reminder_type] ?? 'Membership Renewal Reminder';

        $content = self::template_expiry_reminder([
            'name'            => $first_name,
            'membership_type' => $membership_type_label,
            'expiry_date'     => date_i18n('F j, Y', strtotime($expiry_date)),
            'reminder_type'   => $reminder_type,
            'renewal_url'     => home_url('/membership-selection/'),
            'contact_email'   => Email_Config::get_from_email(),
        ]);

        // Log do lembrete enviado
        self::log_reminder_sent($user_id, $reminder_type);

        return Email_Service::send(
            $user->user_email,
            $subject,
            $content
        );
    }

    /**
     * Envia email notificando o membro que foi removido de uma instituição
     *
     * @since 1.67.4
     * @param int    $user_id        ID do usuário removido
     * @param int    $institution_id ID da instituição
     * @param string $reason         Motivo da remoção (opcional)
     * @return bool
     */
    public static function send_member_removed_from_institution($user_id, $institution_id, $reason = '') {
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }

        $institution = get_post($institution_id);
        if (!$institution) {
            return false;
        }

        $institution_name = get_post_meta($institution_id, 'ins_company_name', true) ?: $institution->post_title;
        $first_name = get_user_meta($user_id, 'first_name', true) ?: $user->display_name;

        $content = self::template_member_removed_from_institution([
            'name'             => $first_name,
            'institution_name' => $institution_name,
            'reason'           => $reason,
            'contact_email'    => Email_Config::get_from_email(),
        ]);

        return Email_Service::send(
            $user->user_email,
            'Institution Membership Update - English Australia',
            $content
        );
    }

    /**
     * Obtém o label do tipo de membership
     *
     * @param string $type_key Chave do tipo
     * @return string Label do tipo
     */
    private static function get_membership_type_label($type_key) {
        $type = \EauSystem\Eau_Membership_Types::get_by_key($type_key);
        return $type ? $type->type_label : ucwords(str_replace('_', ' ', $type_key));
    }

    /**
     * Registra que um lembrete foi enviado
     *
     * @param int    $user_id       ID do usuário
     * @param string $reminder_type Tipo de lembrete
     */
    private static function log_reminder_sent($user_id, $reminder_type) {
        global $wpdb;
        $table = $wpdb->prefix . 'eau_membership_reminders';

        // Verifica se a tabela existe
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            return;
        }

        $wpdb->insert($table, [
            'user_id'       => $user_id,
            'membership_type' => get_user_meta($user_id, 'mem_membership_type', true),
            'reminder_type' => $reminder_type,
            'sent_at'       => current_time('mysql'),
        ]);
    }

    /**
     * Verifica se um lembrete já foi enviado
     *
     * @param int    $user_id       ID do usuário
     * @param string $reminder_type Tipo de lembrete
     * @return bool
     */
    public static function was_reminder_sent($user_id, $reminder_type) {
        global $wpdb;
        $table = $wpdb->prefix . 'eau_membership_reminders';

        // Verifica se a tabela existe
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            return false;
        }

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND reminder_type = %s",
            $user_id,
            $reminder_type
        ));

        return $count > 0;
    }

    // =========================================================================
    // TEMPLATES DE EMAIL
    // =========================================================================

    /**
     * Template: Aplicação recebida (para usuário)
     */
    private static function template_application_received($data) {
        $name = $data['name'] ?? 'Member';
        $membership_type = $data['membership_type'] ?? 'Membership';
        $submitted_at = $data['submitted_at'] ?? '';

        return "
            <h1>Application Received</h1>
            <p>Dear {$name},</p>
            <p>Thank you for submitting your membership application to English Australia.</p>
            " . Email_Template::info_box('Application Details', [
                'Membership Type' => $membership_type,
                'Submitted'       => $submitted_at,
                'Status'          => 'Under Review',
            ]) . "
            <p>Our team will review your application and get back to you within 5-7 business days.</p>
            <p>If you have any questions in the meantime, please don't hesitate to contact us.</p>
            <p>Best regards,<br>The English Australia Team</p>
        ";
    }

    /**
     * Template: Notificação de nova aplicação (para admin)
     */
    private static function template_application_notification_admin($data) {
        $applicant_name = $data['applicant_name'] ?? 'Unknown';
        $applicant_email = $data['applicant_email'] ?? '';
        $membership_type = $data['membership_type'] ?? 'Membership';
        $company_name = $data['company_name'] ?? '-';
        $submitted_at = $data['submitted_at'] ?? '';
        $review_url = $data['review_url'] ?? home_url('/dashboard/membership-applications/');

        return "
            <h1>New Membership Application</h1>
            <p>A new membership application has been submitted and requires review.</p>
            " . Email_Template::info_box('Applicant Details', [
                'Name'            => $applicant_name,
                'Email'           => $applicant_email,
                'Company'         => $company_name,
                'Membership Type' => $membership_type,
                'Submitted'       => $submitted_at,
            ]) . "
            " . Email_Template::button('Review Application', $review_url) . "
            <p>Please review and process this application at your earliest convenience.</p>
        ";
    }

    /**
     * Template: Aplicação aprovada
     */
    private static function template_application_approved($data) {
        $name = $data['name'] ?? 'Member';
        $membership_type = $data['membership_type'] ?? 'Membership';
        $admin_message = $data['admin_message'] ?? '';
        $dashboard_url = $data['dashboard_url'] ?? home_url('/dashboard/');

        $content = "
            <h1>Welcome to English Australia!</h1>
            <p>Dear {$name},</p>
            <p>We are delighted to inform you that your membership application has been <strong>approved</strong>!</p>
            " . Email_Template::info_box('Your Membership', [
                'Membership Type' => $membership_type,
                'Status'          => 'Active',
            ]) . "
        ";

        if (!empty($admin_message)) {
            $content .= "
                <p><strong>Message from our team:</strong></p>
                <p><em>" . esc_html($admin_message) . "</em></p>
            ";
        }

        $content .= "
            <p>You now have full access to all member benefits and resources. Log in to your dashboard to explore everything available to you.</p>
            " . Email_Template::button('Access Your Dashboard', $dashboard_url) . "
            <p>Thank you for joining English Australia. We look forward to supporting you on your professional journey.</p>
            <p>Best regards,<br>The English Australia Team</p>
        ";

        return $content;
    }

    /**
     * Template: Aplicação rejeitada
     */
    private static function template_application_rejected($data) {
        $name = $data['name'] ?? 'Member';
        $membership_type = $data['membership_type'] ?? 'Membership';
        $rejection_reason = $data['rejection_reason'] ?? '';
        $contact_email = $data['contact_email'] ?? 'info@englishaustralia.com.au';

        $content = "
            <h1>Membership Application Update</h1>
            <p>Dear {$name},</p>
            <p>Thank you for your interest in becoming a member of English Australia.</p>
            <p>After careful review, we regret to inform you that we are unable to approve your application for <strong>{$membership_type}</strong> at this time.</p>
        ";

        if (!empty($rejection_reason)) {
            $content .= Email_Template::info_box_html("
                <h3>Reason</h3>
                <p>" . esc_html($rejection_reason) . "</p>
            ");
        }

        $content .= "
            <p>If you believe this decision was made in error or would like to discuss your application further, please don't hesitate to contact us at <a href=\"mailto:{$contact_email}\">{$contact_email}</a>.</p>
            <p>We appreciate your understanding and wish you all the best in your future endeavors.</p>
            <p>Kind regards,<br>The English Australia Team</p>
        ";

        return $content;
    }

    /**
     * Template: Aplicação cancelada
     *
     * @since 1.51.46
     */
    private static function template_application_cancelled($data) {
        $name = $data['name'] ?? 'Member';
        $membership_type = $data['membership_type'] ?? 'Membership';
        $cancellation_reason = $data['cancellation_reason'] ?? '';
        $contact_email = $data['contact_email'] ?? 'info@englishaustralia.com.au';

        $content = "
            <h1>Membership Status Update</h1>
            <p>Dear {$name},</p>
            <p>We are writing to inform you that your <strong>{$membership_type}</strong> membership has been cancelled.</p>
        ";

        if (!empty($cancellation_reason)) {
            $content .= Email_Template::info_box_html("
                <h3>Reason for Cancellation</h3>
                <p>" . esc_html($cancellation_reason) . "</p>
            ");
        }

        $content .= "
            <p>If you believe this cancellation was made in error or would like to discuss your membership status, please don't hesitate to contact us at <a href=\"mailto:{$contact_email}\">{$contact_email}</a>.</p>
            <p>We appreciate your past membership and hope to welcome you back in the future.</p>
            <p>Kind regards,<br>The English Australia Team</p>
        ";

        return $content;
    }

    /**
     * Template: Lembrete de expiração
     */
    private static function template_expiry_reminder($data) {
        $name = $data['name'] ?? 'Member';
        $membership_type = $data['membership_type'] ?? 'Membership';
        $expiry_date = $data['expiry_date'] ?? '';
        $reminder_type = $data['reminder_type'] ?? '30_days';
        $renewal_url = $data['renewal_url'] ?? home_url('/membership-selection/');
        $contact_email = $data['contact_email'] ?? 'info@englishaustralia.com.au';

        // Mensagens específicas por tipo de lembrete
        $messages = [
            '60_days' => "Your membership will expire in approximately <strong>60 days</strong>. This is a friendly reminder to consider renewing your membership to continue enjoying all the benefits.",
            '30_days' => "Your membership will expire in <strong>30 days</strong>. We encourage you to renew soon to avoid any interruption to your member benefits.",
            '7_days'  => "Your membership will expire in just <strong>7 days</strong>. Please renew as soon as possible to maintain your access to all member resources and benefits.",
            'expired' => "Your membership has <strong>expired</strong>. To continue accessing member benefits and resources, please renew your membership today.",
        ];

        $urgency_message = $messages[$reminder_type] ?? $messages['30_days'];

        $title = $reminder_type === 'expired' ? 'Membership Expired' : 'Membership Renewal Reminder';

        $content = "
            <h1>{$title}</h1>
            <p>Dear {$name},</p>
            <p>{$urgency_message}</p>
            " . Email_Template::info_box('Your Membership', [
                'Membership Type' => $membership_type,
                'Expiry Date'     => $expiry_date,
            ]) . "
            " . Email_Template::button('Renew Membership', $renewal_url) . "
            <p>If you have any questions about renewing your membership or need assistance, please contact us at <a href=\"mailto:{$contact_email}\">{$contact_email}</a>.</p>
            <p>Thank you for being a valued member of English Australia.</p>
            <p>Best regards,<br>The English Australia Team</p>
        ";

        return $content;
    }

    /**
     * Template: Membro removido de instituição
     *
     * @since 1.67.4
     */
    private static function template_member_removed_from_institution($data) {
        $name = $data['name'] ?? 'Member';
        $institution_name = $data['institution_name'] ?? 'the institution';
        $reason = $data['reason'] ?? '';
        $contact_email = $data['contact_email'] ?? 'info@englishaustralia.com.au';

        $content = "
            <h1>Institution Membership Update</h1>
            <p>Dear {$name},</p>
            <p>We are writing to inform you that your membership with <strong>{$institution_name}</strong> has been ended by an institution administrator.</p>
        ";

        if (!empty($reason)) {
            $content .= Email_Template::info_box_html("
                <h3>Reason Provided</h3>
                <p>" . esc_html($reason) . "</p>
            ");
        }

        $content .= "
            <p>As a result, your account has been updated to reflect this change. You may join another institution by submitting a new membership request.</p>
            <p>If you believe this was done in error or have any questions, please contact us at <a href=\"mailto:{$contact_email}\">{$contact_email}</a> or reach out to the institution directly.</p>
            <p>We appreciate your understanding.</p>
            <p>Kind regards,<br>The English Australia Team</p>
        ";

        return $content;
    }
}
