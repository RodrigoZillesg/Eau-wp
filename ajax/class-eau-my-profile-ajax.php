<?php
namespace EauSystem\Ajax;

use EauSystem\Eau_My_Profile;
use EauSystem\Eau_User_Institution_Helper;

/**
 * AJAX Handlers para My Profile
 *
 * @since 1.40.0
 */
class Eau_My_Profile_Ajax {

    /**
     * Registra os handlers AJAX
     */
    public static function register_handlers() {
        // Get profile data
        add_action('wp_ajax_eau_get_my_profile', array(__CLASS__, 'get_my_profile'));

        // Update personal info
        add_action('wp_ajax_eau_update_my_profile', array(__CLASS__, 'update_my_profile'));

        // Upload profile photo file
        add_action('wp_ajax_eau_upload_profile_photo', array(__CLASS__, 'upload_profile_photo'));

        // Update profile photo
        add_action('wp_ajax_eau_update_profile_photo', array(__CLASS__, 'update_profile_photo'));

        // Change password
        add_action('wp_ajax_eau_change_password', array(__CLASS__, 'change_password'));
    }

    /**
     * AJAX: Get My Profile
     * Retorna todos os dados do perfil do usuário logado
     */
    public static function get_my_profile() {
        // Verifica nonce
        check_ajax_referer('eau_my_profile_nonce', 'nonce');

        // Verifica se está logado
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'You must be logged in.'));
        }

        $user_id = get_current_user_id();
        $user = get_userdata($user_id);

        if (!$user) {
            wp_send_json_error(array('message' => 'User not found.'));
        }

        // Pega meta fields
        $mem_phone = get_user_meta($user_id, 'mem_phone', true);
        $mem_address = get_user_meta($user_id, 'mem_address', true);
        $mem_city = get_user_meta($user_id, 'mem_city', true);
        $mem_postcode = get_user_meta($user_id, 'mem_postcode', true);
        $mem_type = get_user_meta($user_id, 'mem_type', true);
        $mem_status = get_user_meta($user_id, 'mem_status', true);
        $mem_userid = get_user_meta($user_id, 'mem_userid', true);
        $mem_profile_photo = get_user_meta($user_id, Eau_My_Profile::PROFILE_PHOTO_META_KEY, true);

        // Pega nome da instituição
        $institution_name = Eau_User_Institution_Helper::get_user_institution_name($user_id);

        // Pega URL da foto de perfil
        $profile_photo_url = Eau_My_Profile::get_profile_photo_url($user_id, 150);

        // Formata data de registro
        $registered_date = date_i18n('F Y', strtotime($user->user_registered));

        // Monta resposta
        $data = array(
            // Core fields
            'ID' => $user->ID,
            'user_login' => $user->user_login,
            'user_email' => $user->user_email,
            'display_name' => $user->display_name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'description' => $user->description,
            'user_registered' => $registered_date,

            // Meta fields
            'mem_phone' => $mem_phone,
            'mem_address' => $mem_address,
            'mem_city' => $mem_city,
            'mem_postcode' => $mem_postcode,
            'mem_type' => $mem_type,
            'mem_type_label' => Eau_My_Profile::get_mem_type_label($mem_type),
            'mem_status' => $mem_status,
            'mem_userid' => $mem_userid,
            'mem_profile_photo' => $mem_profile_photo,

            // Computed fields
            'institution_name' => $institution_name,
            'profile_photo_url' => $profile_photo_url,
        );

        wp_send_json_success($data);
    }

    /**
     * AJAX: Update My Profile
     * Atualiza dados pessoais e endereço do usuário
     */
    public static function update_my_profile() {
        // Verifica nonce
        check_ajax_referer('eau_my_profile_nonce', 'nonce');

        // Verifica se está logado
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'You must be logged in.'));
        }

        $user_id = get_current_user_id();

        // Campos permitidos para atualização
        $allowed_core_fields = array('display_name', 'first_name', 'last_name', 'user_email', 'description');
        $allowed_meta_fields = array('mem_phone', 'mem_address', 'mem_city', 'mem_postcode');

        // Pega campos enviados
        $fields = isset($_POST['fields']) ? $_POST['fields'] : array();

        if (empty($fields)) {
            wp_send_json_error(array('message' => 'No fields to update.'));
        }

        // Prepara dados para wp_update_user
        $userdata = array('ID' => $user_id);
        $errors = array();

        foreach ($fields as $key => $value) {
            $key = sanitize_key($key);
            $value = sanitize_text_field($value);

            if (in_array($key, $allowed_core_fields)) {
                // Validação especial para email
                if ($key === 'user_email') {
                    if (empty($value)) {
                        $errors[] = 'Email is required.';
                        continue;
                    }

                    if (!is_email($value)) {
                        $errors[] = 'Invalid email format.';
                        continue;
                    }

                    // Verifica se email já existe (exceto o próprio usuário)
                    $existing_user = get_user_by('email', $value);
                    if ($existing_user && $existing_user->ID !== $user_id) {
                        $errors[] = 'This email is already in use.';
                        continue;
                    }
                }

                // Validação para display_name
                if ($key === 'display_name' && empty($value)) {
                    $errors[] = 'Display name is required.';
                    continue;
                }

                $userdata[$key] = $value;

            } elseif (in_array($key, $allowed_meta_fields)) {
                // Atualiza meta field
                update_user_meta($user_id, $key, $value);
            }
            // Campos não permitidos são silenciosamente ignorados
        }

        // Se houver erros, retorna
        if (!empty($errors)) {
            wp_send_json_error(array('message' => implode(' ', $errors)));
        }

        // Atualiza dados core se houver
        if (count($userdata) > 1) {
            $updated = wp_update_user($userdata);

            if (is_wp_error($updated)) {
                wp_send_json_error(array('message' => $updated->get_error_message()));
            }
        }

        wp_send_json_success(array('message' => 'Profile updated successfully.'));
    }

    /**
     * AJAX: Upload Profile Photo File
     * Faz upload do arquivo de imagem para a Media Library
     */
    public static function upload_profile_photo() {
        // Verifica nonce
        check_ajax_referer('eau_my_profile_nonce', 'nonce');

        // Verifica se está logado
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'You must be logged in.'));
        }

        // Verifica se há arquivo
        if (empty($_FILES['file'])) {
            wp_send_json_error(array('message' => 'No file uploaded.'));
        }

        $file = $_FILES['file'];

        // Verifica erros de upload
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error_messages = array(
                UPLOAD_ERR_INI_SIZE => 'File exceeds server limit.',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds form limit.',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded.',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
                UPLOAD_ERR_EXTENSION => 'Upload stopped by extension.',
            );
            $message = isset($error_messages[$file['error']]) ? $error_messages[$file['error']] : 'Upload error.';
            wp_send_json_error(array('message' => $message));
        }

        // Verifica tipo MIME
        $allowed_types = array('image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp');
        $file_type = wp_check_filetype($file['name']);
        $mime_type = $file_type['type'];

        // Verificação adicional usando getimagesize
        $image_info = @getimagesize($file['tmp_name']);
        if ($image_info === false) {
            wp_send_json_error(array('message' => 'File is not a valid image.'));
        }

        if (!in_array($image_info['mime'], $allowed_types)) {
            wp_send_json_error(array('message' => 'File type not allowed. Allowed: JPG, PNG, GIF, WEBP'));
        }

        // Verifica tamanho (5MB máximo)
        $max_size = 5 * 1024 * 1024; // 5MB
        if ($file['size'] > $max_size) {
            wp_send_json_error(array('message' => 'File is too large. Maximum size is 5 MB.'));
        }

        // Prepara para upload no WordPress
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        // Configura upload
        $overrides = array(
            'test_form' => false,
            'mimes' => array(
                'jpg|jpeg|jpe' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'webp' => 'image/webp',
            ),
        );

        // Faz upload
        $uploaded = wp_handle_upload($file, $overrides);

        if (isset($uploaded['error'])) {
            wp_send_json_error(array('message' => $uploaded['error']));
        }

        // Cria attachment no WordPress
        $attachment = array(
            'post_mime_type' => $uploaded['type'],
            'post_title' => preg_replace('/\.[^.]+$/', '', basename($uploaded['file'])),
            'post_content' => '',
            'post_status' => 'inherit',
            'post_author' => get_current_user_id(),
        );

        $attachment_id = wp_insert_attachment($attachment, $uploaded['file']);

        if (is_wp_error($attachment_id)) {
            wp_send_json_error(array('message' => 'Failed to create attachment.'));
        }

        // Gera metadados da imagem
        $attachment_data = wp_generate_attachment_metadata($attachment_id, $uploaded['file']);
        wp_update_attachment_metadata($attachment_id, $attachment_data);

        // Retorna sucesso com dados do arquivo
        wp_send_json_success(array(
            'id' => $attachment_id,
            'url' => $uploaded['url'],
            'filename' => basename($uploaded['file']),
        ));
    }

    /**
     * AJAX: Update Profile Photo
     * Atualiza a foto de perfil do usuário
     */
    public static function update_profile_photo() {
        // Verifica nonce
        check_ajax_referer('eau_my_profile_nonce', 'nonce');

        // Verifica se está logado
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'You must be logged in.'));
        }

        $user_id = get_current_user_id();
        $attachment_id = isset($_POST['attachment_id']) ? absint($_POST['attachment_id']) : 0;

        // Se attachment_id = 0, remove a foto
        if ($attachment_id === 0) {
            delete_user_meta($user_id, Eau_My_Profile::PROFILE_PHOTO_META_KEY);

            wp_send_json_success(array(
                'message' => 'Profile photo removed.',
                'profile_photo_url' => Eau_My_Profile::get_profile_photo_url($user_id, 150),
            ));
        }

        // Verifica se o attachment existe e é uma imagem
        $attachment = get_post($attachment_id);
        if (!$attachment || $attachment->post_type !== 'attachment') {
            wp_send_json_error(array('message' => 'Invalid attachment.'));
        }

        // Verifica se é uma imagem
        if (!wp_attachment_is_image($attachment_id)) {
            wp_send_json_error(array('message' => 'File must be an image.'));
        }

        // Salva o attachment ID
        update_user_meta($user_id, Eau_My_Profile::PROFILE_PHOTO_META_KEY, $attachment_id);

        // Retorna a nova URL
        $profile_photo_url = Eau_My_Profile::get_profile_photo_url($user_id, 150);

        wp_send_json_success(array(
            'message' => 'Profile photo updated.',
            'profile_photo_url' => $profile_photo_url,
        ));
    }

    /**
     * AJAX: Change Password
     * Altera a senha do usuário
     */
    public static function change_password() {
        // Verifica nonce
        check_ajax_referer('eau_my_profile_nonce', 'nonce');

        // Verifica se está logado
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'You must be logged in.'));
        }

        $user_id = get_current_user_id();
        $user = get_userdata($user_id);

        // Pega senhas enviadas
        $current_password = isset($_POST['current_password']) ? $_POST['current_password'] : '';
        $new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
        $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

        // Validações
        if (empty($current_password)) {
            wp_send_json_error(array('message' => 'Current password is required.'));
        }

        if (empty($new_password)) {
            wp_send_json_error(array('message' => 'New password is required.'));
        }

        if (strlen($new_password) < 8) {
            wp_send_json_error(array('message' => 'New password must be at least 8 characters.'));
        }

        if ($new_password !== $confirm_password) {
            wp_send_json_error(array('message' => 'Passwords do not match.'));
        }

        // Verifica se a senha atual está correta
        if (!wp_check_password($current_password, $user->user_pass, $user_id)) {
            wp_send_json_error(array('message' => 'Current password is incorrect.'));
        }

        // Atualiza a senha
        wp_set_password($new_password, $user_id);

        // Re-autentica o usuário para manter a sessão
        wp_set_auth_cookie($user_id, true);

        wp_send_json_success(array('message' => 'Password changed successfully.'));
    }
}
