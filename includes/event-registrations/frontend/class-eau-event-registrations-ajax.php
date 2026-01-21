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

        // Compra de acesso à gravação de evento passado (v1.68.10)
        add_action('wp_ajax_eau_purchase_recording_access', array(__CLASS__, 'purchase_recording_access'));

        // Buscar membros da instituição para registro múltiplo (v1.69.0)
        add_action('wp_ajax_eau_get_institution_members_for_event', array(__CLASS__, 'get_institution_members_for_event'));

        // Registro múltiplo para membros da instituição (v1.69.0)
        add_action('wp_ajax_eau_register_multiple_for_event', array(__CLASS__, 'register_multiple_for_event'));
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

            // Verificar se membership está ativo (v1.51.46)
            // Membros com membership cancelado/expirado/suspenso não podem se inscrever
            if (\EauSystem\Eau_User_Institution_Helper::is_membership_inactive()) {
                $status = \EauSystem\Eau_User_Institution_Helper::get_membership_status();
                $messages = array(
                    'cancelled' => __('Your membership has been cancelled. You cannot register for events.', 'eau-system'),
                    'expired' => __('Your membership has expired. Please renew to register for events.', 'eau-system'),
                    'suspended' => __('Your membership is suspended. Please contact support.', 'eau-system'),
                );
                $message = isset($messages[$status]) ? $messages[$status] : __('You need an active membership to register for events.', 'eau-system');
                wp_send_json_error(array('message' => $message));
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

        // Verificar se pode se inscrever baseado na visibilidade (v1.68.0)
        $can_register = \EauSystem\Events\Frontend\Eau_Events_Helper::can_user_register($event_id);
        if (!$can_register['can_register']) {
            wp_send_json_error(array('message' => $can_register['message']));
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

        // Determinar preço correto baseado na visibilidade e tipo de usuário (v1.68.0)
        $user_price = \EauSystem\Events\Frontend\Eau_Events_Helper::get_user_price($event_id);
        $event_price = $user_price['amount'];
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
        update_post_meta($post_id, $prefix . 'price_paid', $event_price);
        update_post_meta($post_id, $prefix . 'price_type', $user_price['is_member'] ? 'member' : 'non_member');
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

        // Processar cupom se fornecido (v1.69.0)
        $coupon_code = isset($_POST['coupon_code']) ? sanitize_text_field($_POST['coupon_code']) : '';
        if (!empty($coupon_code) && !$is_free_event) {
            $coupon_result = self::apply_coupon_to_registration($post_id, $coupon_code, $event_id, $event_price, get_current_user_id());
            if ($coupon_result['success']) {
                // Atualizar preço pago com desconto
                update_post_meta($post_id, $prefix . 'price_paid', $coupon_result['final_price']);
                update_post_meta($post_id, $prefix . 'coupon_id', $coupon_result['coupon_id']);
                update_post_meta($post_id, $prefix . 'coupon_code', $coupon_result['coupon_code']);
                update_post_meta($post_id, $prefix . 'discount_applied', $coupon_result['discount_amount']);
                update_post_meta($post_id, $prefix . 'original_price', $event_price);

                // Atualizar payment_status se ficou gratuito
                if ($coupon_result['final_price'] <= 0) {
                    update_post_meta($post_id, $prefix . 'payment_status', 'free');
                }
            }
        }

        // Envia email de confirmação
        \EauSystem\Email\Email_Events::send_registration_confirmation($post_id);

        // Determinar se precisa redirecionar para checkout (v1.71.0)
        // Recalcular preço final considerando cupom
        $final_price = (float) get_post_meta($post_id, Config\META_PREFIX . 'price_paid', true);
        $final_payment_status = get_post_meta($post_id, Config\META_PREFIX . 'payment_status', true);
        $requires_payment = ($final_payment_status === 'pending' && $final_price > 0);

        $response = array(
            'message'          => __('Registration successful! You will receive a confirmation email shortly.', 'eau-system'),
            'registration_id'  => $post_id,
            'requires_payment' => $requires_payment,
        );

        // Se evento é pago, adicionar URL de checkout
        if ($requires_payment) {
            $checkout_page_id = \EauSystem\Eau_Pages::get_page_id('checkout');
            $checkout_url = $checkout_page_id ? get_permalink($checkout_page_id) : home_url('/checkout/');
            $response['checkout_url'] = add_query_arg(array(
                'type'   => 'event',
                'reg_id' => $post_id,
            ), $checkout_url);
            $response['message'] = __('Registration created! Redirecting to payment...', 'eau-system');
        }

        wp_send_json_success($response);
    }

    /**
     * Registra múltiplos membros para um evento (v1.69.0)
     *
     * @since  1.69.0
     * @return void
     */
    public static function register_multiple_for_event() {
        check_ajax_referer('eau_event_registration', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in.', 'eau-system')));
        }

        $event_id = isset($_POST['event_id']) ? absint($_POST['event_id']) : 0;
        $member_ids = isset($_POST['member_ids']) ? array_map('absint', (array) $_POST['member_ids']) : array();
        $coupon_code = isset($_POST['coupon_code']) ? sanitize_text_field($_POST['coupon_code']) : '';

        if (!$event_id) {
            wp_send_json_error(array('message' => __('Invalid event.', 'eau-system')));
        }

        if (empty($member_ids)) {
            wp_send_json_error(array('message' => __('Please select at least one member.', 'eau-system')));
        }

        // Verificar se evento existe
        $event = get_post($event_id);
        if (!$event || $event->post_type !== 'eau_event') {
            wp_send_json_error(array('message' => __('Event not found.', 'eau-system')));
        }

        // Verificar se usuário pode registrar outros (deve ser da mesma instituição)
        $current_user_id = get_current_user_id();
        $user_institution = get_user_meta($current_user_id, 'mem_membercompanyname', true);

        if (empty($user_institution)) {
            wp_send_json_error(array('message' => __('You must belong to an institution to register others.', 'eau-system')));
        }

        // Verificar capacidade
        $capacity = get_post_meta($event_id, 'evt_capacity', true);
        if ($capacity && intval($capacity) > 0) {
            $current_registrations = self::count_registrations($event_id);
            if (($current_registrations + count($member_ids)) > intval($capacity)) {
                wp_send_json_error(array('message' => __('Not enough spots available for all selected members.', 'eau-system')));
            }
        }

        // Obter preço do evento
        $user_price = \EauSystem\Events\Frontend\Eau_Events_Helper::get_user_price($event_id);
        $event_price = $user_price['amount'];
        $total_price = $event_price * count($member_ids);
        $is_free_event = ($event_price <= 0);

        // Validar cupom se fornecido
        $coupon_data = null;
        $discount_per_person = 0;
        if (!empty($coupon_code) && !$is_free_event) {
            $validation = \EauSystem\Coupons\Eau_Coupons_Validator::validate_for_multiple(
                $coupon_code,
                $event_id,
                $event_price,
                count($member_ids),
                $current_user_id
            );

            if (!$validation['valid']) {
                wp_send_json_error(array('message' => $validation['error']));
            }

            $coupon_data = $validation['coupon'];
            $total_discount = $validation['discount']['amount'];
            $discount_per_person = $total_discount / count($member_ids);
        }

        $prefix = Config\META_PREFIX;
        $registered_ids = array();
        $failed_members = array();

        // Registrar cada membro
        foreach ($member_ids as $member_user_id) {
            // Verificar se membro pertence à mesma instituição
            $member_institution = get_user_meta($member_user_id, 'mem_membercompanyname', true);
            if ($member_institution !== $user_institution) {
                $failed_members[] = $member_user_id;
                continue;
            }

            // Obter dados do membro
            $member_user = get_userdata($member_user_id);
            if (!$member_user) {
                $failed_members[] = $member_user_id;
                continue;
            }

            // Verificar se já está registrado
            $existing = self::get_existing_registration($event_id, $member_user->user_email);
            if ($existing) {
                $failed_members[] = $member_user_id;
                continue;
            }

            // Criar registro
            $post_title = $member_user->display_name . ' - ' . $event->post_title;

            $post_id = wp_insert_post(array(
                'post_type'   => Config\POST_TYPE,
                'post_title'  => $post_title,
                'post_status' => 'publish',
            ));

            if (is_wp_error($post_id)) {
                $failed_members[] = $member_user_id;
                continue;
            }

            // Determinar tipo de membro
            $member_type = 'member'; // Assumimos que todos são membros se estão na instituição

            // Calcular preço final com desconto
            $final_price = $event_price - $discount_per_person;
            $payment_status = $is_free_event || $final_price <= 0 ? 'free' : 'pending';

            // Salvar meta dados
            update_post_meta($post_id, $prefix . 'attendee_name', $member_user->display_name);
            update_post_meta($post_id, $prefix . 'attendee_email', $member_user->user_email);
            update_post_meta($post_id, $prefix . 'event_id', $event_id);
            update_post_meta($post_id, $prefix . 'registration_date', current_time('Y-m-d\TH:i'));
            update_post_meta($post_id, $prefix . 'member_type', $member_type);
            update_post_meta($post_id, $prefix . 'status', Config\DEFAULT_STATUS);
            update_post_meta($post_id, $prefix . 'payment_status', $payment_status);
            update_post_meta($post_id, $prefix . 'price_paid', $final_price);
            update_post_meta($post_id, $prefix . 'price_type', 'member');
            update_post_meta($post_id, $prefix . 'attended', '0');
            update_post_meta($post_id, $prefix . 'activity_created', '0');
            update_post_meta($post_id, $prefix . 'user_id', $member_user_id);
            update_post_meta($post_id, $prefix . 'registered_by_user_id', $current_user_id);
            update_post_meta($post_id, $prefix . 'registration_type', 'by_institution_member');

            // Salvar mem_userid
            $mem_userid = get_user_meta($member_user_id, 'mem_userid', true);
            if (!empty($mem_userid)) {
                update_post_meta($post_id, $prefix . 'mem_userid', $mem_userid);
            }

            // Salvar dados do cupom se aplicado
            if ($coupon_data && $discount_per_person > 0) {
                update_post_meta($post_id, $prefix . 'coupon_id', $coupon_data['id']);
                update_post_meta($post_id, $prefix . 'coupon_code', $coupon_data['code']);
                update_post_meta($post_id, $prefix . 'discount_applied', $discount_per_person);
                update_post_meta($post_id, $prefix . 'original_price', $event_price);
            }

            // Envia email de confirmação
            \EauSystem\Email\Email_Events::send_registration_confirmation($post_id);

            $registered_ids[] = $post_id;
        }

        // Registrar uso do cupom (uma vez para todos os registros)
        if ($coupon_data && !empty($registered_ids)) {
            \EauSystem\Coupons\Eau_Coupons_Model::record_usage(array(
                'coupon_id' => $coupon_data['id'],
                'user_id' => $current_user_id,
                'registration_id' => $registered_ids[0], // Primeiro registro
                'event_id' => $event_id,
                'discount_applied' => $coupon_data['discount']['amount'],
                'original_price' => $total_price,
                'final_price' => $total_price - $coupon_data['discount']['amount'],
            ));
        }

        $total_registered = count($registered_ids);
        $total_failed = count($failed_members);

        if ($total_registered === 0) {
            wp_send_json_error(array('message' => __('Failed to register any members. They may already be registered.', 'eau-system')));
        }

        $message = sprintf(
            __('%d member(s) registered successfully!', 'eau-system'),
            $total_registered
        );

        if ($total_failed > 0) {
            $message .= ' ' . sprintf(
                __('%d member(s) could not be registered (already registered or different institution).', 'eau-system'),
                $total_failed
            );
        }

        // Calcular valor total a pagar (v1.71.0)
        $total_pending_amount = 0;
        $pending_registration_ids = array();
        foreach ($registered_ids as $reg_id) {
            $reg_status = get_post_meta($reg_id, Config\META_PREFIX . 'payment_status', true);
            $reg_price = (float) get_post_meta($reg_id, Config\META_PREFIX . 'price_paid', true);
            if ($reg_status === 'pending' && $reg_price > 0) {
                $total_pending_amount += $reg_price;
                $pending_registration_ids[] = $reg_id;
            }
        }

        $requires_payment = !empty($pending_registration_ids);

        $response = array(
            'message' => $message,
            'registered_count' => $total_registered,
            'failed_count' => $total_failed,
            'registration_ids' => $registered_ids,
            'requires_payment' => $requires_payment,
        );

        // Se tem pagamentos pendentes, adicionar URL de checkout (v1.71.0)
        if ($requires_payment) {
            $checkout_page_id = \EauSystem\Eau_Pages::get_page_id('checkout');
            $checkout_url = $checkout_page_id ? get_permalink($checkout_page_id) : home_url('/checkout/');

            // Para múltiplos registros, usar o primeiro como referência
            // O webhook processará todos os registros do mesmo evento/usuário
            $response['checkout_url'] = add_query_arg(array(
                'type'    => 'event',
                'reg_id'  => $pending_registration_ids[0],
                'batch'   => implode(',', $pending_registration_ids),
            ), $checkout_url);
            $response['total_amount'] = $total_pending_amount;
            $response['message'] = $message . ' ' . __('Redirecting to payment...', 'eau-system');
        }

        wp_send_json_success($response);
    }

    /**
     * Aplica cupom a um registro (v1.69.0)
     *
     * @param int    $registration_id ID do registro
     * @param string $coupon_code     Código do cupom
     * @param int    $event_id        ID do evento
     * @param float  $original_price  Preço original
     * @param int    $user_id         ID do usuário
     * @return array
     */
    private static function apply_coupon_to_registration($registration_id, $coupon_code, $event_id, $original_price, $user_id) {
        $validation = \EauSystem\Coupons\Eau_Coupons_Validator::validate(
            $coupon_code,
            $event_id,
            $original_price,
            $user_id
        );

        if (!$validation['valid']) {
            return array('success' => false, 'error' => $validation['error']);
        }

        $coupon = $validation['coupon'];
        $discount = $validation['discount'];

        // Registrar uso do cupom
        \EauSystem\Coupons\Eau_Coupons_Model::record_usage(array(
            'coupon_id' => $coupon['id'],
            'user_id' => $user_id,
            'registration_id' => $registration_id,
            'event_id' => $event_id,
            'discount_applied' => $discount['amount'],
            'original_price' => $original_price,
            'final_price' => $discount['final_total'],
        ));

        return array(
            'success' => true,
            'coupon_id' => $coupon['id'],
            'coupon_code' => $coupon['code'],
            'discount_amount' => $discount['amount'],
            'final_price' => $discount['final_total'],
        );
    }

    /**
     * Busca membros da instituição para registro múltiplo (v1.69.0)
     *
     * @since  1.69.0
     * @return void
     */
    public static function get_institution_members_for_event() {
        check_ajax_referer('eau_event_registration', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in.', 'eau-system')));
        }

        $event_id = isset($_POST['event_id']) ? absint($_POST['event_id']) : 0;

        if (!$event_id) {
            wp_send_json_error(array('message' => __('Invalid event.', 'eau-system')));
        }

        // Obter instituição do usuário atual
        $current_user_id = get_current_user_id();
        $user_institution = get_user_meta($current_user_id, 'mem_membercompanyname', true);

        if (empty($user_institution)) {
            wp_send_json_error(array('message' => __('You must belong to an institution.', 'eau-system')));
        }

        // Resolver nome da instituição (pode ser ID de post ou título)
        $institution_name = $user_institution;
        if (is_numeric($user_institution)) {
            // Se for um ID, buscar o título do post
            $institution_post = get_post(intval($user_institution));
            if ($institution_post) {
                $institution_name = $institution_post->post_title;
            }
        }

        // Buscar logo da instituição
        $institution_logo = '';
        if (is_numeric($user_institution)) {
            $institution_logo = get_post_meta(intval($user_institution), 'ins_logo', true);
        }

        // Buscar todos os membros da mesma instituição
        $args = array(
            'meta_query' => array(
                array(
                    'key' => 'mem_membercompanyname',
                    'value' => $user_institution,
                    'compare' => '=',
                ),
            ),
            'orderby' => 'display_name',
            'order' => 'ASC',
            'number' => 100, // Limitar a 100 membros
        );

        $users_query = new \WP_User_Query($args);
        $users = $users_query->get_results();

        // Obter preço do evento
        $user_price = \EauSystem\Events\Frontend\Eau_Events_Helper::get_user_price($event_id);

        // Preparar lista de membros (inclui o usuário logado)
        $members = array();
        foreach ($users as $user) {
            // Verificar se já está registrado
            $already_registered = self::get_existing_registration($event_id, $user->user_email) !== null;

            // Marcar se é o usuário atual (para UI destacar)
            $is_current_user = ($user->ID === $current_user_id);

            $members[] = array(
                'user_id' => $user->ID,
                'name' => $user->display_name,
                'email' => $user->user_email,
                'already_registered' => $already_registered,
                'is_current_user' => $is_current_user,
            );
        }

        wp_send_json_success(array(
            'members' => $members,
            'institution_name' => $institution_name,
            'institution_id' => is_numeric($user_institution) ? intval($user_institution) : 0,
            'institution_logo' => $institution_logo,
            'event_price' => $user_price['amount'],
            'price_display' => $user_price['display'],
            'current_user_id' => $current_user_id,
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

    /**
     * Processa compra de acesso à gravação de evento passado
     *
     * @since  1.68.10
     * @return void
     */
    public static function purchase_recording_access() {
        check_ajax_referer('eau_event_registration', 'nonce');

        // Precisa estar logado
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in to purchase access.', 'eau-system')));
        }

        $event_id = isset($_POST['event_id']) ? absint($_POST['event_id']) : 0;

        if (!$event_id) {
            wp_send_json_error(array('message' => __('Invalid event.', 'eau-system')));
        }

        // Verificar se evento existe
        $event = get_post($event_id);
        if (!$event || $event->post_type !== 'eau_event') {
            wp_send_json_error(array('message' => __('Event not found.', 'eau-system')));
        }

        // Verificar se tem gravação disponível
        $recording_url = get_post_meta($event_id, 'evt_recording_url', true);
        $materials = get_post_meta($event_id, 'evt_materials', true);
        if (empty($recording_url) && empty($materials)) {
            wp_send_json_error(array('message' => __('No recording or materials available for this event.', 'eau-system')));
        }

        // Verificar se usuário já tem acesso (já registrado)
        $current_user = wp_get_current_user();
        $existing = self::get_user_registration($event_id, $current_user->ID);
        if ($existing) {
            wp_send_json_error(array('message' => __('You already have access to this event.', 'eau-system')));
        }

        // Verificar se pode comprar acesso (visibilidade compatível com tipo de usuário)
        $can_register = \EauSystem\Events\Frontend\Eau_Events_Helper::can_user_register($event_id);
        if (!$can_register['can_register']) {
            wp_send_json_error(array('message' => $can_register['message']));
        }

        // Determinar preço e tipo de membro
        $user_price = \EauSystem\Events\Frontend\Eau_Events_Helper::get_user_price($event_id);
        $price = $user_price['amount'];
        $is_free = ($price <= 0);

        // Determinar tipo de membro
        $member_type = 'non_member';
        $mem_type = get_user_meta($current_user->ID, 'mem_type', true);
        if (in_array($mem_type, array('member', 'Admin', 'superAdmin', 'institutionAdmin'))) {
            $member_type = 'member';
        }

        // Se é gratuito, criar registro direto
        if ($is_free) {
            $registration_id = self::create_recording_access_registration($event_id, $current_user, $member_type, 0, 'free');

            if (is_wp_error($registration_id)) {
                wp_send_json_error(array('message' => __('Error creating access. Please try again.', 'eau-system')));
            }

            wp_send_json_success(array(
                'message'         => __('Access granted! You can now view the recording and materials.', 'eau-system'),
                'registration_id' => $registration_id,
                'redirect'        => false,
            ));
        }

        // Se é pago, criar registro com status pending e redirecionar para pagamento
        $registration_id = self::create_recording_access_registration($event_id, $current_user, $member_type, $price, 'pending');

        if (is_wp_error($registration_id)) {
            wp_send_json_error(array('message' => __('Error creating access. Please try again.', 'eau-system')));
        }

        // Redirecionar para página de checkout (v1.71.0)
        $checkout_page_id = \EauSystem\Eau_Pages::get_page_id('checkout');
        $checkout_url = $checkout_page_id ? get_permalink($checkout_page_id) : home_url('/checkout/');

        wp_send_json_success(array(
            'message'         => __('Redirecting to payment...', 'eau-system'),
            'registration_id' => $registration_id,
            'price'           => $price,
            'redirect'        => true,
            'checkout_url'    => add_query_arg(array(
                'type'   => 'event',
                'reg_id' => $registration_id,
            ), $checkout_url),
        ));
    }

    /**
     * Cria registro de acesso à gravação
     *
     * @since  1.68.10
     * @param  int      $event_id    Event ID
     * @param  \WP_User $user        User object
     * @param  string   $member_type Member type (member/non_member)
     * @param  float    $price       Price paid
     * @param  string   $status      Payment status (free/pending/paid)
     * @return int|\WP_Error
     */
    private static function create_recording_access_registration($event_id, $user, $member_type, $price, $status) {
        $event = get_post($event_id);
        $prefix = Config\META_PREFIX;

        $post_title = $user->display_name . ' - ' . $event->post_title . ' (Recording Access)';

        $post_id = wp_insert_post(array(
            'post_type'   => Config\POST_TYPE,
            'post_title'  => $post_title,
            'post_status' => 'publish',
        ));

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        // Salvar meta dados
        update_post_meta($post_id, $prefix . 'attendee_name', $user->display_name);
        update_post_meta($post_id, $prefix . 'attendee_email', $user->user_email);
        update_post_meta($post_id, $prefix . 'event_id', $event_id);
        update_post_meta($post_id, $prefix . 'registration_date', current_time('Y-m-d\TH:i'));
        update_post_meta($post_id, $prefix . 'member_type', $member_type);
        update_post_meta($post_id, $prefix . 'status', $status);
        update_post_meta($post_id, $prefix . 'payment_status', $status);
        update_post_meta($post_id, $prefix . 'price_paid', $price);
        update_post_meta($post_id, $prefix . 'price_type', $member_type);
        update_post_meta($post_id, $prefix . 'attended', '0');
        update_post_meta($post_id, $prefix . 'activity_created', '0');
        update_post_meta($post_id, $prefix . 'user_id', $user->ID);
        update_post_meta($post_id, $prefix . 'access_type', 'recording'); // Marca como acesso a gravação

        // Salvar mem_userid
        $mem_userid = get_user_meta($user->ID, 'mem_userid', true);
        if (!empty($mem_userid)) {
            update_post_meta($post_id, $prefix . 'mem_userid', $mem_userid);
        }

        return $post_id;
    }

    /**
     * Gera URL de checkout para acesso à gravação
     *
     * @since  1.68.10
     * @param  int   $registration_id Registration ID
     * @param  int   $event_id        Event ID
     * @param  float $price           Price
     * @return string
     */
    private static function get_recording_checkout_url($registration_id, $event_id, $price) {
        // Verifica se WooCommerce está ativo
        if (!function_exists('wc_get_checkout_url')) {
            // Fallback: retorna URL de pagamento genérica ou página do evento
            return add_query_arg(array(
                'recording_payment' => $registration_id,
                'event_id'          => $event_id,
            ), home_url('/dashboard/payments/'));
        }

        // Cria sessão de pagamento para processamento posterior
        // O produto será adicionado ao carrinho via hook
        WC()->session->set('eau_recording_access', array(
            'registration_id' => $registration_id,
            'event_id'        => $event_id,
            'price'           => $price,
        ));

        return add_query_arg(array(
            'eau_recording_access' => $registration_id,
        ), wc_get_checkout_url());
    }

    /**
     * Verifica se usuário tem acesso à gravação de um evento
     *
     * @since  1.68.10
     * @param  int $event_id Event ID
     * @param  int $user_id  User ID (opcional)
     * @return bool
     */
    public static function has_recording_access($event_id, $user_id = null) {
        if ($user_id === null) {
            if (!is_user_logged_in()) {
                return false;
            }
            $user_id = get_current_user_id();
        }

        $registration = self::get_user_registration($event_id, $user_id);
        if (!$registration) {
            return false;
        }

        $prefix = Config\META_PREFIX;
        $status = get_post_meta($registration->ID, $prefix . 'status', true);

        // Acesso concedido se status é paid ou free
        return in_array($status, array('paid', 'free'));
    }
}
