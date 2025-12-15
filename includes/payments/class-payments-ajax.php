<?php
/**
 * Payments AJAX Handlers
 *
 * @package    EauSystem
 * @subpackage Payments
 * @since      1.45.0
 */

namespace EauSystem\Payments;

if (!defined('WPINC')) {
    die;
}

/**
 * Class Payments_Ajax
 *
 * AJAX handlers para gerenciamento de pagamentos.
 *
 * @since 1.45.0
 */
class Payments_Ajax {

    /**
     * Registra handlers AJAX
     *
     * @since  1.45.0
     * @since  1.49.9 Adicionados handlers para membership payments
     * @return void
     */
    public static function register_handlers() {
        // Event payments
        add_action('wp_ajax_eau_add_payment', array(__CLASS__, 'add_payment'));
        add_action('wp_ajax_eau_get_payments', array(__CLASS__, 'get_payments'));
        add_action('wp_ajax_eau_delete_payment', array(__CLASS__, 'delete_payment'));
        add_action('wp_ajax_eau_get_registration_payment_info', array(__CLASS__, 'get_registration_payment_info'));

        // Generic media upload handlers
        add_action('wp_ajax_eau_upload_media', array(__CLASS__, 'upload_media'));
        add_action('wp_ajax_eau_get_user_files', array(__CLASS__, 'get_user_files'));

        // Membership payments (v1.49.9)
        add_action('wp_ajax_eau_get_membership_payments', array(__CLASS__, 'get_membership_payments'));
        add_action('wp_ajax_eau_add_membership_payment', array(__CLASS__, 'add_membership_payment'));
        add_action('wp_ajax_eau_delete_membership_payment', array(__CLASS__, 'delete_membership_payment'));
        add_action('wp_ajax_eau_get_membership_payment_stats', array(__CLASS__, 'get_membership_payment_stats'));
    }

    /**
     * Verifica permissão de gerenciar eventos
     *
     * @since  1.45.0
     * @return bool
     */
    private static function can_manage_events() {
        if (!is_user_logged_in()) {
            return false;
        }

        $user_id = get_current_user_id();
        $mem_type = get_user_meta($user_id, 'mem_type', true);

        return in_array($mem_type, array('superAdmin', 'Admin'));
    }

    /**
     * AJAX: Adiciona um novo pagamento
     *
     * @since  1.45.0
     * @return void
     */
    public static function add_payment() {
        check_ajax_referer('eau_events_management_nonce', 'nonce');

        if (!self::can_manage_events()) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $registration_id = isset($_POST['registration_id']) ? absint($_POST['registration_id']) : 0;
        $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
        $payment_date = isset($_POST['payment_date']) ? sanitize_text_field($_POST['payment_date']) : '';
        $payment_method = isset($_POST['payment_method']) ? sanitize_key($_POST['payment_method']) : '';
        $notes = isset($_POST['notes']) ? sanitize_textarea_field($_POST['notes']) : '';
        $receipt_id = isset($_POST['receipt_id']) ? absint($_POST['receipt_id']) : 0;

        // Validações
        if (!$registration_id) {
            wp_send_json_error(array('message' => 'Invalid registration ID'));
        }

        if ($amount <= 0) {
            wp_send_json_error(array('message' => 'Amount must be greater than zero'));
        }

        if (empty($payment_date)) {
            wp_send_json_error(array('message' => 'Payment date is required'));
        }

        if (empty($payment_method)) {
            wp_send_json_error(array('message' => 'Payment method is required'));
        }

        // Verifica se registration existe
        $registration = get_post($registration_id);
        if (!$registration || $registration->post_type !== 'eau_event_reg') {
            wp_send_json_error(array('message' => 'Registration not found'));
        }

        // Obtém dados da registration
        $event_id = get_post_meta($registration_id, 'reg_event_id', true);
        $user_id = get_post_meta($registration_id, 'reg_user_id', true);

        // Prepara URL do comprovante
        $receipt_url = '';
        if ($receipt_id) {
            $receipt_url = wp_get_attachment_url($receipt_id);
        }

        // Cria o pagamento
        $payment_id = Payments_Post_Type::create_payment(array(
            'registration_id' => $registration_id,
            'event_id'        => intval($event_id),
            'user_id'         => intval($user_id),
            'amount'          => $amount,
            'payment_date'    => $payment_date,
            'payment_method'  => $payment_method,
            'receipt_url'     => $receipt_url,
            'receipt_id'      => $receipt_id,
            'notes'           => $notes,
            'created_by'      => get_current_user_id(),
            'status'          => 'confirmed',
        ));

        if (is_wp_error($payment_id)) {
            wp_send_json_error(array('message' => 'Failed to create payment'));
        }

        // Atualiza status de pagamento da registration
        self::update_registration_payment_status($registration_id);

        // Retorna pagamentos atualizados
        $payments = Payments_Post_Type::get_payments_by_registration($registration_id);
        $total_paid = Payments_Post_Type::get_total_paid($registration_id);
        $event_price = floatval(get_post_meta($event_id, 'evt_member_price', true) ?: 0);

        wp_send_json_success(array(
            'message'    => 'Payment added successfully',
            'payment_id' => $payment_id,
            'payments'   => $payments,
            'total_paid' => $total_paid,
            'balance'    => max(0, $event_price - $total_paid),
        ));
    }

    /**
     * AJAX: Lista pagamentos de uma registration
     *
     * @since  1.45.0
     * @return void
     */
    public static function get_payments() {
        check_ajax_referer('eau_events_management_nonce', 'nonce');

        if (!self::can_manage_events()) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $registration_id = isset($_POST['registration_id']) ? absint($_POST['registration_id']) : 0;

        if (!$registration_id) {
            wp_send_json_error(array('message' => 'Invalid registration ID'));
        }

        $payments = Payments_Post_Type::get_payments_by_registration($registration_id);
        $total_paid = Payments_Post_Type::get_total_paid($registration_id);

        // Obtém preço do evento
        $event_id = get_post_meta($registration_id, 'reg_event_id', true);
        $event_price = floatval(get_post_meta($event_id, 'evt_member_price', true) ?: 0);

        wp_send_json_success(array(
            'payments'   => $payments,
            'total_paid' => $total_paid,
            'balance'    => max(0, $event_price - $total_paid),
        ));
    }

    /**
     * AJAX: Deleta um pagamento
     *
     * @since  1.45.0
     * @return void
     */
    public static function delete_payment() {
        check_ajax_referer('eau_events_management_nonce', 'nonce');

        if (!self::can_manage_events()) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $payment_id = isset($_POST['payment_id']) ? absint($_POST['payment_id']) : 0;

        if (!$payment_id) {
            wp_send_json_error(array('message' => 'Invalid payment ID'));
        }

        $payment = get_post($payment_id);
        if (!$payment || $payment->post_type !== Payments_Post_Type::POST_TYPE) {
            wp_send_json_error(array('message' => 'Payment not found'));
        }

        // Obtém registration_id antes de deletar
        $registration_id = get_post_meta($payment_id, Payments_Post_Type::META_PREFIX . 'registration_id', true);

        // Deleta o pagamento
        wp_trash_post($payment_id);

        // Atualiza status de pagamento da registration
        if ($registration_id) {
            self::update_registration_payment_status($registration_id);
        }

        // Retorna pagamentos atualizados
        $payments = Payments_Post_Type::get_payments_by_registration($registration_id);
        $total_paid = Payments_Post_Type::get_total_paid($registration_id);
        $event_id = get_post_meta($registration_id, 'reg_event_id', true);
        $event_price = floatval(get_post_meta($event_id, 'evt_member_price', true) ?: 0);

        wp_send_json_success(array(
            'message'    => 'Payment deleted successfully',
            'payments'   => $payments,
            'total_paid' => $total_paid,
            'balance'    => max(0, $event_price - $total_paid),
        ));
    }

    /**
     * AJAX: Obtém informações de pagamento de uma registration
     *
     * @since  1.45.0
     * @return void
     */
    public static function get_registration_payment_info() {
        check_ajax_referer('eau_events_management_nonce', 'nonce');

        if (!self::can_manage_events()) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $registration_id = isset($_POST['registration_id']) ? absint($_POST['registration_id']) : 0;

        if (!$registration_id) {
            wp_send_json_error(array('message' => 'Invalid registration ID'));
        }

        // Dados da registration
        $registration = get_post($registration_id);
        if (!$registration || $registration->post_type !== 'eau_event_reg') {
            wp_send_json_error(array('message' => 'Registration not found'));
        }

        $attendee_name = get_post_meta($registration_id, 'reg_attendee_name', true);
        $attendee_email = get_post_meta($registration_id, 'reg_attendee_email', true);
        $event_id = get_post_meta($registration_id, 'reg_event_id', true);
        $payment_status = get_post_meta($registration_id, 'reg_status', true) ?: 'pending';

        // Dados do evento
        $event = get_post($event_id);
        $event_title = $event ? $event->post_title : 'Unknown Event';
        $event_price = floatval(get_post_meta($event_id, 'evt_member_price', true) ?: 0);

        // Pagamentos
        $payments = Payments_Post_Type::get_payments_by_registration($registration_id);
        $total_paid = Payments_Post_Type::get_total_paid($registration_id);

        // Métodos de pagamento disponíveis
        $payment_methods = Payments_Post_Type::get_payment_methods();

        wp_send_json_success(array(
            'registration' => array(
                'id'             => $registration_id,
                'attendee_name'  => $attendee_name,
                'attendee_email' => $attendee_email,
                'payment_status' => $payment_status,
            ),
            'event' => array(
                'id'    => $event_id,
                'title' => $event_title,
                'price' => $event_price,
            ),
            'payments'        => $payments,
            'total_paid'      => $total_paid,
            'balance'         => max(0, $event_price - $total_paid),
            'payment_methods' => $payment_methods,
        ));
    }

    /**
     * Atualiza status de pagamento da registration baseado nos pagamentos
     *
     * @since  1.45.0
     * @param  int $registration_id ID da registration
     * @return void
     */
    private static function update_registration_payment_status($registration_id) {
        $event_id = get_post_meta($registration_id, 'reg_event_id', true);
        $event_price = floatval(get_post_meta($event_id, 'evt_member_price', true) ?: 0);
        $total_paid = Payments_Post_Type::get_total_paid($registration_id);

        // Determina novo status
        if ($event_price <= 0) {
            $new_status = 'free';
        } elseif ($total_paid >= $event_price) {
            $new_status = 'paid';
        } elseif ($total_paid > 0) {
            $new_status = 'partial';
        } else {
            $new_status = 'pending';
        }

        update_post_meta($registration_id, 'reg_status', $new_status);
    }

    /**
     * AJAX: Upload de arquivo para Media Library
     *
     * @since  1.45.8
     * @return void
     */
    public static function upload_media() {
        // Verifica login primeiro
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'You must be logged in to upload files'), 401);
            return;
        }

        // Aceita qualquer nonce válido do sistema
        $nonce_valid = false;
        $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';

        // Se nonce vazio, tenta REQUEST
        if (empty($nonce)) {
            $nonce = isset($_REQUEST['nonce']) ? sanitize_text_field($_REQUEST['nonce']) : '';
        }

        $nonces_to_check = array(
            'eau_events_management_nonce',
            'eau_event_registrations_nonce',
            'eau_my_cpds_nonce',
        );

        $nonce_results = array();
        foreach ($nonces_to_check as $nonce_action) {
            $result = wp_verify_nonce($nonce, $nonce_action);
            $nonce_results[$nonce_action] = $result;
            if ($result) {
                $nonce_valid = true;
                break;
            }
        }

        if (!$nonce_valid) {
            // Log para debug
            error_log('EAU Upload Media - Nonce Failed: ' . print_r(array(
                'nonce' => substr($nonce, 0, 10) . '...',
                'user_id' => get_current_user_id(),
                'results' => $nonce_results,
            ), true));

            wp_send_json_error(array(
                'message' => 'Security check failed. Please refresh the page and try again.',
                'debug' => array(
                    'nonce_received' => !empty($nonce),
                    'nonce_length' => strlen($nonce),
                    'user_id' => get_current_user_id(),
                    'results' => $nonce_results,
                )
            ), 403);
            return;
        }

        // Permite upload para qualquer usuário logado do sistema
        $user_id = get_current_user_id();

        if (empty($_FILES['file'])) {
            wp_send_json_error(array('message' => 'No file uploaded'), 400);
            return;
        }

        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        $file = $_FILES['file'];

        // Verifica erro de upload
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error_messages = array(
                UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload limit',
                UPLOAD_ERR_FORM_SIZE  => 'File exceeds form upload limit',
                UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded',
                UPLOAD_ERR_NO_FILE    => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION  => 'File upload stopped by extension',
            );
            $error_msg = isset($error_messages[$file['error']]) ? $error_messages[$file['error']] : 'Unknown upload error';
            wp_send_json_error(array('message' => $error_msg), 400);
            return;
        }

        // Validação de tamanho (10MB max por padrão)
        $max_size = isset($_POST['max_size']) ? intval($_POST['max_size']) : 10 * 1024 * 1024;
        if ($file['size'] > $max_size) {
            wp_send_json_error(array(
                'message' => 'File is too large. Maximum size is ' . size_format($max_size)
            ), 400);
            return;
        }

        // Validação de extensão
        if (!empty($_POST['allowed_extensions'])) {
            $allowed = array_map('trim', explode(',', strtolower($_POST['allowed_extensions'])));
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if (!in_array($ext, $allowed)) {
                wp_send_json_error(array(
                    'message' => 'File type not allowed. Allowed: ' . implode(', ', array_map('strtoupper', $allowed))
                ), 400);
                return;
            }
        }

        // Verifica tipo MIME
        $filetype = wp_check_filetype($file['name']);
        if (!$filetype['type']) {
            wp_send_json_error(array('message' => 'Invalid file type'), 400);
            return;
        }

        // Upload do arquivo
        $upload = wp_handle_upload($file, array(
            'test_form' => false,
            'mimes'     => null, // Usa mimes padrão do WordPress
        ));

        if (isset($upload['error'])) {
            wp_send_json_error(array('message' => $upload['error']), 400);
            return;
        }

        // Cria attachment
        $attachment = array(
            'post_mime_type' => $upload['type'],
            'post_title'     => sanitize_file_name(pathinfo($file['name'], PATHINFO_FILENAME)),
            'post_content'   => '',
            'post_status'    => 'inherit',
            'post_author'    => $user_id,
        );

        $attachment_id = wp_insert_attachment($attachment, $upload['file']);

        if (is_wp_error($attachment_id)) {
            wp_send_json_error(array('message' => 'Failed to create attachment: ' . $attachment_id->get_error_message()), 500);
            return;
        }

        // Gera metadata
        $attachment_data = wp_generate_attachment_metadata($attachment_id, $upload['file']);
        wp_update_attachment_metadata($attachment_id, $attachment_data);

        wp_send_json_success(array(
            'id'       => $attachment_id,
            'url'      => $upload['url'],
            'filename' => basename($upload['file']),
            'type'     => $upload['type'],
            'size'     => $file['size'],
        ));
    }

    /**
     * AJAX: Lista arquivos do usuário
     *
     * @since  1.45.8
     * @return void
     */
    public static function get_user_files() {
        // Aceita qualquer nonce válido do sistema
        $nonce_valid = false;
        $nonces_to_check = array(
            'eau_events_management_nonce',
            'eau_event_registrations_nonce',
            'eau_my_cpds_nonce',
        );

        foreach ($nonces_to_check as $nonce_action) {
            if (isset($_POST['nonce']) && wp_verify_nonce($_POST['nonce'], $nonce_action)) {
                $nonce_valid = true;
                break;
            }
        }

        if (!$nonce_valid) {
            wp_send_json_error(array('message' => 'Security check failed'));
        }

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'You must be logged in'));
        }

        $user_id = get_current_user_id();

        $args = array(
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'author'         => $user_id,
            'posts_per_page' => 50,
            'orderby'        => 'date',
            'order'          => 'DESC',
        );

        $attachments = get_posts($args);
        $files = array();

        foreach ($attachments as $attachment) {
            $url = wp_get_attachment_url($attachment->ID);
            $thumbnail = wp_get_attachment_image_src($attachment->ID, 'thumbnail');

            $files[] = array(
                'id'        => $attachment->ID,
                'filename'  => basename(get_attached_file($attachment->ID)),
                'url'       => $url,
                'thumbnail' => $thumbnail ? $thumbnail[0] : null,
                'type'      => $attachment->post_mime_type,
            );
        }

        wp_send_json_success(array('files' => $files));
    }

    // =========================================================================
    // MEMBERSHIP PAYMENT AJAX HANDLERS (v1.49.9)
    // =========================================================================

    /**
     * AJAX: Lista pagamentos de membership
     *
     * @since  1.49.9
     * @return void
     */
    public static function get_membership_payments() {
        check_ajax_referer('eau_membership_payments_nonce', 'nonce');

        if (!self::can_manage_events()) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $args = array(
            'page'            => isset($_POST['page']) ? absint($_POST['page']) : 1,
            'per_page'        => isset($_POST['per_page']) ? absint($_POST['per_page']) : 20,
            'search'          => isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '',
            'status'          => isset($_POST['status']) ? sanitize_key($_POST['status']) : '',
            'membership_type' => isset($_POST['membership_type']) ? sanitize_key($_POST['membership_type']) : '',
            'order_by'        => isset($_POST['order_by']) ? sanitize_key($_POST['order_by']) : 'date',
            'order'           => isset($_POST['order']) ? strtoupper(sanitize_key($_POST['order'])) : 'DESC',
        );

        $result = Payments_Post_Type::get_all_membership_payments($args);

        wp_send_json_success($result);
    }

    /**
     * AJAX: Adiciona um pagamento de membership
     *
     * @since  1.49.9
     * @return void
     */
    public static function add_membership_payment() {
        check_ajax_referer('eau_membership_payments_nonce', 'nonce');

        if (!self::can_manage_events()) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        // Validações
        $user_id = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;
        $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
        $payment_date = isset($_POST['payment_date']) ? sanitize_text_field($_POST['payment_date']) : '';
        $payment_method = isset($_POST['payment_method']) ? sanitize_key($_POST['payment_method']) : '';
        $membership_type = isset($_POST['membership_type']) ? sanitize_key($_POST['membership_type']) : '';

        if (!$user_id) {
            wp_send_json_error(array('message' => 'User ID is required'));
        }

        if ($amount <= 0) {
            wp_send_json_error(array('message' => 'Amount must be greater than zero'));
        }

        if (empty($payment_date)) {
            wp_send_json_error(array('message' => 'Payment date is required'));
        }

        if (empty($payment_method)) {
            wp_send_json_error(array('message' => 'Payment method is required'));
        }

        // Verifica se usuário existe
        $user = get_userdata($user_id);
        if (!$user) {
            wp_send_json_error(array('message' => 'User not found'));
        }

        // Dados do pagamento
        $notes = isset($_POST['notes']) ? sanitize_textarea_field($_POST['notes']) : '';
        $receipt_id = isset($_POST['receipt_id']) ? absint($_POST['receipt_id']) : 0;
        $transaction_id = isset($_POST['transaction_id']) ? sanitize_text_field($_POST['transaction_id']) : '';
        $application_id = isset($_POST['application_id']) ? absint($_POST['application_id']) : 0;
        $period_start = isset($_POST['period_start']) ? sanitize_text_field($_POST['period_start']) : '';
        $period_end = isset($_POST['period_end']) ? sanitize_text_field($_POST['period_end']) : '';
        $status = isset($_POST['status']) ? sanitize_key($_POST['status']) : 'confirmed';

        // Receipt URL
        $receipt_url = '';
        if ($receipt_id) {
            $receipt_url = wp_get_attachment_url($receipt_id);
        }

        // Se não tiver tipo de membership, pega do usuário
        if (empty($membership_type)) {
            $membership_type = get_user_meta($user_id, 'mem_membership_type', true);
        }

        // Cria o pagamento
        $payment_id = Payments_Post_Type::create_membership_payment(array(
            'user_id'                   => $user_id,
            'amount'                    => $amount,
            'payment_date'              => $payment_date,
            'payment_method'            => $payment_method,
            'transaction_id'            => $transaction_id,
            'receipt_url'               => $receipt_url,
            'receipt_id'                => $receipt_id,
            'notes'                     => $notes,
            'created_by'                => get_current_user_id(),
            'status'                    => $status,
            'membership_application_id' => $application_id,
            'membership_type'           => $membership_type,
            'membership_period_start'   => $period_start,
            'membership_period_end'     => $period_end,
        ));

        if (is_wp_error($payment_id)) {
            wp_send_json_error(array('message' => 'Failed to create payment: ' . $payment_id->get_error_message()));
        }

        // Se pagamento confirmado, atualiza status do membership do usuário
        if ($status === 'confirmed') {
            update_user_meta($user_id, 'mem_membership_status', 'active');

            // Atualiza datas se fornecidas
            if (!empty($period_start)) {
                update_user_meta($user_id, 'mem_membership_start_date', $period_start);
            }
            if (!empty($period_end)) {
                update_user_meta($user_id, 'mem_membership_expiry_date', $period_end);
            }
        }

        wp_send_json_success(array(
            'message'    => 'Payment added successfully',
            'payment_id' => $payment_id,
        ));
    }

    /**
     * AJAX: Deleta um pagamento de membership
     *
     * @since  1.49.9
     * @return void
     */
    public static function delete_membership_payment() {
        check_ajax_referer('eau_membership_payments_nonce', 'nonce');

        if (!self::can_manage_events()) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $payment_id = isset($_POST['payment_id']) ? absint($_POST['payment_id']) : 0;

        if (!$payment_id) {
            wp_send_json_error(array('message' => 'Invalid payment ID'));
        }

        $payment = get_post($payment_id);
        if (!$payment || $payment->post_type !== Payments_Post_Type::POST_TYPE) {
            wp_send_json_error(array('message' => 'Payment not found'));
        }

        // Verifica se é pagamento de membership
        $payment_type = get_post_meta($payment_id, Payments_Post_Type::META_PREFIX . 'payment_type', true);
        if ($payment_type !== 'membership') {
            wp_send_json_error(array('message' => 'This is not a membership payment'));
        }

        // Deleta o pagamento
        wp_trash_post($payment_id);

        wp_send_json_success(array('message' => 'Payment deleted successfully'));
    }

    /**
     * AJAX: Obtém estatísticas de pagamentos de membership
     *
     * @since  1.49.9
     * @return void
     */
    public static function get_membership_payment_stats() {
        check_ajax_referer('eau_membership_payments_nonce', 'nonce');

        if (!self::can_manage_events()) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $stats = Payments_Post_Type::get_membership_payment_stats();

        wp_send_json_success($stats);
    }
}
