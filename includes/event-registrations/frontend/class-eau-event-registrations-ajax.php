<?php
/**
 * Event Registrations Frontend AJAX
 *
 * Handlers AJAX para registro de eventos no frontend.
 *
 * @package    EauSystem
 * @subpackage EventRegistrations\Frontend
 * @since      1.29.4
 */

namespace EauSystem\EventRegistrations\Frontend;

use EauSystem\EventRegistrations\Config;

if (!defined('WPINC')) {
    die;
}

/**
 * Class Eau_Event_Registrations_Ajax
 *
 * Handlers AJAX para registro de eventos.
 *
 * @since 1.29.4
 */
class Eau_Event_Registrations_Ajax {

    /**
     * Registra handlers AJAX
     *
     * @since  1.29.4
     * @return void
     */
    public static function register_handlers() {
        // Registro de evento (usuários logados e não logados)
        add_action('wp_ajax_eau_register_for_event', array(__CLASS__, 'register_for_event'));
        add_action('wp_ajax_nopriv_eau_register_for_event', array(__CLASS__, 'register_for_event'));

        // Verificar se já está registrado
        add_action('wp_ajax_eau_check_registration', array(__CLASS__, 'check_registration'));
        add_action('wp_ajax_nopriv_eau_check_registration', array(__CLASS__, 'check_registration'));

        // Cancelar registro
        add_action('wp_ajax_eau_cancel_registration', array(__CLASS__, 'cancel_registration'));
    }

    /**
     * Registra usuário para um evento
     *
     * @since  1.29.4
     * @return void
     */
    public static function register_for_event() {
        check_ajax_referer('eau_event_registration', 'nonce');

        $event_id = isset($_POST['event_id']) ? absint($_POST['event_id']) : 0;
        $attendee_name = isset($_POST['attendee_name']) ? sanitize_text_field($_POST['attendee_name']) : '';
        $attendee_email = isset($_POST['attendee_email']) ? sanitize_email($_POST['attendee_email']) : '';

        // Se usuário está logado e não enviou nome/email, usa dados do usuário
        if (is_user_logged_in()) {
            $current_user = wp_get_current_user();
            if (empty($attendee_name)) {
                $attendee_name = $current_user->display_name;
            }
            if (empty($attendee_email)) {
                $attendee_email = $current_user->user_email;
            }
        }

        // Validações
        if (!$event_id) {
            wp_send_json_error(array('message' => __('Invalid event.', 'eau-system')));
        }

        if (empty($attendee_name)) {
            wp_send_json_error(array('message' => __('Name is required.', 'eau-system')));
        }

        if (empty($attendee_email) || !is_email($attendee_email)) {
            wp_send_json_error(array('message' => __('Valid email is required.', 'eau-system')));
        }

        // Verificar se evento existe
        $event = get_post($event_id);
        if (!$event || $event->post_type !== 'eau_event') {
            wp_send_json_error(array('message' => __('Event not found.', 'eau-system')));
        }

        // Verificar se já está registrado (mesmo email para mesmo evento)
        $existing = self::get_existing_registration($event_id, $attendee_email);
        if ($existing) {
            wp_send_json_error(array('message' => __('You are already registered for this event.', 'eau-system')));
        }

        // Verificar capacidade (evt_ é o prefixo do módulo Events)
        $capacity = get_post_meta($event_id, 'evt_capacity', true);
        if ($capacity && intval($capacity) > 0) {
            $current_registrations = self::count_registrations($event_id);
            if ($current_registrations >= intval($capacity)) {
                wp_send_json_error(array('message' => __('This event is at full capacity.', 'eau-system')));
            }
        }

        // Determinar tipo de membro
        $member_type = 'non_member';
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            $mem_type = get_user_meta($user->ID, 'mem_type', true);
            if (in_array($mem_type, array('member', 'Admin', 'superAdmin', 'institutionAdmin'))) {
                $member_type = 'member';
            }
        }

        // Criar registro
        $prefix = Config\META_PREFIX;
        $post_title = $attendee_name . ' - ' . $event->post_title;

        $post_id = wp_insert_post(array(
            'post_type'   => Config\POST_TYPE,
            'post_title'  => $post_title,
            'post_status' => 'publish',
        ));

        if (is_wp_error($post_id)) {
            wp_send_json_error(array('message' => __('Error creating registration.', 'eau-system')));
        }

        // Verificar se evento é gratuito
        $event_price = floatval(get_post_meta($event_id, 'evt_member_price', true) ?: 0);
        $is_free_event = ($event_price <= 0);
        $payment_status = $is_free_event ? 'free' : 'pending';

        // Salvar meta dados
        update_post_meta($post_id, $prefix . 'attendee_name', $attendee_name);
        update_post_meta($post_id, $prefix . 'attendee_email', $attendee_email);
        update_post_meta($post_id, $prefix . 'event_id', $event_id);
        update_post_meta($post_id, $prefix . 'registration_date', current_time('Y-m-d\TH:i'));
        update_post_meta($post_id, $prefix . 'member_type', $member_type);
        update_post_meta($post_id, $prefix . 'status', Config\DEFAULT_STATUS);
        update_post_meta($post_id, $prefix . 'payment_status', $payment_status);
        update_post_meta($post_id, $prefix . 'attended', '0');
        update_post_meta($post_id, $prefix . 'activity_created', '0');

        // Salvar user_id e mem_userid se logado
        if (is_user_logged_in()) {
            $wp_user_id = get_current_user_id();
            update_post_meta($post_id, $prefix . 'user_id', $wp_user_id);

            // Salvar mem_userid do usuário
            $mem_userid = get_user_meta($wp_user_id, 'mem_userid', true);
            if (!empty($mem_userid)) {
                update_post_meta($post_id, $prefix . 'mem_userid', $mem_userid);
            }
        }

        // Salvar campos adicionais (v1.45.0)
        $dietary = isset($_POST['dietary_requirements']) ? sanitize_text_field($_POST['dietary_requirements']) : '';
        $accessibility = isset($_POST['accessibility_requirements']) ? sanitize_text_field($_POST['accessibility_requirements']) : '';
        $notes = isset($_POST['additional_notes']) ? sanitize_textarea_field($_POST['additional_notes']) : '';

        if (!empty($dietary)) {
            update_post_meta($post_id, $prefix . 'dietary_requirements', $dietary);
        }
        if (!empty($accessibility)) {
            update_post_meta($post_id, $prefix . 'accessibility_requirements', $accessibility);
        }
        if (!empty($notes)) {
            update_post_meta($post_id, $prefix . 'additional_notes', $notes);
        }

        // Envia email de confirmação
        \EauSystem\Email\Email_Events::send_registration_confirmation($post_id);

        wp_send_json_success(array(
            'message'         => __('Registration successful! You will receive a confirmation email shortly.', 'eau-system'),
            'registration_id' => $post_id,
        ));
    }

    /**
     * Verifica se usuário já está registrado
     *
     * @since  1.29.4
     * @return void
     */
    public static function check_registration() {
        $event_id = isset($_POST['event_id']) ? absint($_POST['event_id']) : 0;

        if (!$event_id) {
            wp_send_json_error(array('registered' => false));
        }

        $is_registered = false;
        $registration = null;

        // Verificar por user_id se logado
        if (is_user_logged_in()) {
            $registration = self::get_user_registration($event_id, get_current_user_id());
            $is_registered = !empty($registration);
        }

        wp_send_json_success(array(
            'registered'      => $is_registered,
            'registration_id' => $registration ? $registration->ID : null,
            'status'          => $registration ? get_post_meta($registration->ID, Config\META_PREFIX . 'status', true) : null,
        ));
    }

    /**
     * Cancela registro de evento
     *
     * @since  1.29.4
     * @return void
     */
    public static function cancel_registration() {
        check_ajax_referer('eau_event_registration', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in to cancel.', 'eau-system')));
        }

        $event_id = isset($_POST['event_id']) ? absint($_POST['event_id']) : 0;

        if (!$event_id) {
            wp_send_json_error(array('message' => __('Invalid event.', 'eau-system')));
        }

        $registration = self::get_user_registration($event_id, get_current_user_id());

        if (!$registration) {
            wp_send_json_error(array('message' => __('Registration not found.', 'eau-system')));
        }

        // Atualizar status para cancelled
        update_post_meta($registration->ID, Config\META_PREFIX . 'status', 'cancelled');

        wp_send_json_success(array('message' => __('Registration cancelled successfully.', 'eau-system')));
    }

    /**
     * Busca registro existente por email
     *
     * @since  1.29.4
     * @param  int    $event_id Event ID
     * @param  string $email    Email
     * @return \WP_Post|null
     */
    public static function get_existing_registration($event_id, $email) {
        $prefix = Config\META_PREFIX;

        $query = new \WP_Query(array(
            'post_type'      => Config\POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'meta_query'     => array(
                'relation' => 'AND',
                array(
                    'key'   => $prefix . 'event_id',
                    'value' => $event_id,
                ),
                array(
                    'key'   => $prefix . 'attendee_email',
                    'value' => $email,
                ),
                array(
                    'key'     => $prefix . 'status',
                    'value'   => 'cancelled',
                    'compare' => '!=',
                ),
            ),
        ));

        return $query->have_posts() ? $query->posts[0] : null;
    }

    /**
     * Busca registro por user_id
     *
     * @since  1.29.4
     * @param  int $event_id Event ID
     * @param  int $user_id  User ID
     * @return \WP_Post|null
     */
    public static function get_user_registration($event_id, $user_id) {
        $prefix = Config\META_PREFIX;

        $query = new \WP_Query(array(
            'post_type'      => Config\POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'meta_query'     => array(
                'relation' => 'AND',
                array(
                    'key'   => $prefix . 'event_id',
                    'value' => $event_id,
                ),
                array(
                    'key'   => $prefix . 'user_id',
                    'value' => $user_id,
                ),
                array(
                    'key'     => $prefix . 'status',
                    'value'   => 'cancelled',
                    'compare' => '!=',
                ),
            ),
        ));

        return $query->have_posts() ? $query->posts[0] : null;
    }

    /**
     * Conta registros de um evento
     *
     * @since  1.29.4
     * @param  int $event_id Event ID
     * @return int
     */
    public static function count_registrations($event_id) {
        $prefix = Config\META_PREFIX;

        $query = new \WP_Query(array(
            'post_type'      => Config\POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => array(
                'relation' => 'AND',
                array(
                    'key'   => $prefix . 'event_id',
                    'value' => $event_id,
                ),
                array(
                    'key'     => $prefix . 'status',
                    'value'   => 'cancelled',
                    'compare' => '!=',
                ),
            ),
        ));

        return $query->found_posts;
    }

    /**
     * Verifica se usuário está registrado em um evento (helper estático)
     *
     * @since  1.29.4
     * @param  int $event_id Event ID
     * @param  int $user_id  User ID (opcional, usa current user)
     * @return bool
     */
    public static function is_user_registered($event_id, $user_id = null) {
        if ($user_id === null) {
            if (!is_user_logged_in()) {
                return false;
            }
            $user_id = get_current_user_id();
        }

        return !empty(self::get_user_registration($event_id, $user_id));
    }

    /**
     * Verifica se usuário pode ver o link "View my CPD" para um evento
     *
     * Condições:
     * 1. Usuário está logado
     * 2. Usuário está registrado no evento
     * 3. Status do registro é 'paid' ou 'free'
     * 4. Se evento for online (virtual/hybrid), usuário deve ter 'attended' = true
     *
     * @since  1.47.4
     * @param  int $event_id Event ID
     * @param  int $user_id  User ID (opcional, usa current user)
     * @return bool
     */
    public static function can_view_cpd($event_id, $user_id = null) {
        if ($user_id === null) {
            if (!is_user_logged_in()) {
                return false;
            }
            $user_id = get_current_user_id();
        }

        // Busca registro do usuário
        $registration = self::get_user_registration($event_id, $user_id);
        if (!$registration) {
            return false;
        }

        $prefix = Config\META_PREFIX;

        // Verifica status de pagamento
        $status = get_post_meta($registration->ID, $prefix . 'status', true);
        if (!in_array($status, array('paid', 'free'))) {
            return false;
        }

        // Verifica tipo do evento
        $event_type = get_post_meta($event_id, 'evt_event_type', true) ?: 'in-person';

        // Se evento é online (virtual ou hybrid), verifica se participou (attended)
        if (in_array($event_type, array('virtual', 'hybrid'))) {
            $attended = get_post_meta($registration->ID, $prefix . 'attended', true);
            if (!$attended) {
                return false;
            }
        }

        return true;
    }
}
