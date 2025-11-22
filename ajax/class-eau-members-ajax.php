<?php
namespace EauSystem\Ajax;

use EauSystem\Eau_User_Institution_Helper;

/**
 * AJAX Handlers para Members Management
 */
class Eau_Members_Ajax {

    /**
     * Registra os handlers AJAX
     */
    public static function register_handlers() {
        // Lista membros
        add_action('wp_ajax_eau_get_members', array(__CLASS__, 'get_members'));

        // Delete member
        add_action('wp_ajax_eau_delete_member', array(__CLASS__, 'delete_member'));

        // Update member
        add_action('wp_ajax_eau_update_member', array(__CLASS__, 'update_member'));

        // Get member details
        add_action('wp_ajax_eau_get_member_details', array(__CLASS__, 'get_member_details'));

        // Create member
        add_action('wp_ajax_eau_create_member', array(__CLASS__, 'create_member'));

        // Get editable fields configuration
        add_action('wp_ajax_eau_get_editable_fields', array(__CLASS__, 'get_editable_fields'));

        // Get institutions for select dropdown
        add_action('wp_ajax_eau_get_institutions', array(__CLASS__, 'get_institutions'));

        // Export CSV
        add_action('wp_ajax_eau_export_members_csv', array(__CLASS__, 'export_members_csv'));
    }

    /**
     * AJAX: Get Members (lista paginada com filtros)
     */
    public static function get_members() {
        // Verifica nonce
        check_ajax_referer('eau_members_nonce', 'nonce');

        // Pega parâmetros
        $page = isset($_POST['page']) ? absint($_POST['page']) : 1;
        $per_page = isset($_POST['per_page']) ? absint($_POST['per_page']) : 20;
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $role = isset($_POST['role']) ? sanitize_text_field($_POST['role']) : '';
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
        $institution_id = isset($_POST['institution']) ? absint($_POST['institution']) : '';
        $membership_type = isset($_POST['membership_type']) ? sanitize_text_field($_POST['membership_type']) : '';
        $registered_date_from = isset($_POST['registered_date_from']) ? sanitize_text_field($_POST['registered_date_from']) : '';
        $registered_date_to = isset($_POST['registered_date_to']) ? sanitize_text_field($_POST['registered_date_to']) : '';
        $orderby = isset($_POST['orderby']) ? sanitize_text_field($_POST['orderby']) : 'display_name';
        $order = isset($_POST['order']) ? sanitize_text_field($_POST['order']) : 'ASC';

        // Calcula offset
        $offset = ($page - 1) * $per_page;

        // Busca usuários
        $result = Eau_User_Institution_Helper::get_users_with_institutions(array(
            'number' => $per_page,
            'offset' => $offset,
            'search' => $search,
            'role' => $role,
            'status' => $status,
            'institution_id' => $institution_id,
            'membership_type' => $membership_type,
            'registered_date_from' => $registered_date_from,
            'registered_date_to' => $registered_date_to,
            'orderby' => $orderby,
            'order' => $order,
        ));

        // Formata dados para a tabela
        $rows = array();
        foreach ($result['users'] as $user) {
            $rows[] = self::format_user_row($user);
        }

        wp_send_json_success(array(
            'rows' => $rows,
            'total' => $result['total'],
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil($result['total'] / $per_page),
        ));
    }

    /**
     * Formata os dados do usuário para a tabela
     *
     * @param array $user Dados do usuário
     * @return array Dados formatados
     */
    private static function format_user_row($user) {
        // Full name (first_name + last_name)
        $full_name = trim($user['first_name'] . ' ' . $user['last_name']);
        // Se não tiver first/last name, usa display_name
        if (empty($full_name)) {
            $full_name = $user['display_name'];
        }

        $member_html = sprintf(
            '<div class="eau-member-cell"><strong>%s</strong></div>',
            esc_html($full_name)
        );

        // Contact (email)
        $contact_html = sprintf(
            '<a href="mailto:%s">%s</a>',
            esc_attr($user['user_email']),
            esc_html($user['user_email'])
        );

        // Membership Type
        $membership = $user['membership_type'] ? $user['membership_type'] : '-';
        $membership_html = sprintf(
            '<div class="eau-membership-cell">
                <div>%s</div>
                <div class="eau-membership-subtitle">%s</div>
            </div>',
            esc_html($user['institution_name'] ? $user['institution_name'] : '-'),
            esc_html($membership)
        );

        // User Type (mem_type do JetEngine)
        $mem_type = !empty($user['mem_type']) ? $user['mem_type'] : '';
        $user_type_label = self::get_mem_type_label($mem_type);
        $user_type_class = self::get_mem_type_class($mem_type);

        $user_type_html = sprintf(
            '<span class="eau-user-type-badge %s">%s</span>',
            esc_attr($user_type_class),
            esc_html($user_type_label)
        );

        // Status (case-insensitive comparison)
        $status_lower = strtolower($user['status']);
        $status_class = $status_lower === 'active' ? 'eau-status-badge-active' : 'eau-status-badge-inactive';
        $status_html = sprintf(
            '<span class="eau-status-badge %s">%s</span>',
            esc_attr($status_class),
            esc_html(ucfirst($user['status']))
        );

        // Actions - os botões serão renderizados pelo JavaScript
        $actions_html = ''; // Vazio - o JS renderiza os botões com data-id

        return array(
            'ID' => $user['ID'],
            'member' => $member_html,
            'contact' => $contact_html,
            'membership' => $membership_html,
            'user_type' => $user_type_html,
            'status' => $status_html,
            'actions' => $actions_html,
        );
    }

    /**
     * Pega o label amigável da role
     *
     * @param string $role Role do WordPress
     * @return string Label amigável
     */
    private static function get_role_label($role) {
        $labels = array(
            'administrator' => 'Super Admin',
            'editor' => 'Editor',
            'author' => 'Author',
            'contributor' => 'Contributor',
            'subscriber' => 'Member',
        );

        return isset($labels[$role]) ? $labels[$role] : ucfirst($role);
    }

    /**
     * Pega a classe CSS da role
     *
     * @param string $role Role do WordPress
     * @return string Classe CSS
     */
    private static function get_role_class($role) {
        if ($role === 'administrator') {
            return 'eau-user-type-admin';
        }

        return 'eau-user-type-member';
    }

    /**
     * Pega o label amigável do mem_type (JetEngine)
     *
     * @param string $mem_type mem_type do usuário
     * @return string Label amigável
     */
    private static function get_mem_type_label($mem_type) {
        $labels = array(
            'superAdmin' => 'Super Admin',
            'Admin' => 'Admin',
            'institutionAdmin' => 'Institution Admin',
            'Member' => 'Member',
        );

        return isset($labels[$mem_type]) ? $labels[$mem_type] : (!empty($mem_type) ? $mem_type : 'Member');
    }

    /**
     * Pega a classe CSS do mem_type
     *
     * @param string $mem_type mem_type do usuário
     * @return string Classe CSS
     */
    private static function get_mem_type_class($mem_type) {
        if (in_array($mem_type, array('superAdmin', 'Admin', 'institutionAdmin'))) {
            return 'eau-user-type-admin';
        }

        return 'eau-user-type-member';
    }

    /**
     * AJAX: Delete Member
     */
    public static function delete_member() {
        check_ajax_referer('eau_members_nonce', 'nonce');

        // Verifica permissão
        if (!current_user_can('delete_users')) {
            wp_send_json_error(array('message' => 'You do not have permission to delete users.'));
        }

        $user_id = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;

        if (!$user_id) {
            wp_send_json_error(array('message' => 'Invalid user ID.'));
        }

        // Não pode deletar a si mesmo
        if ($user_id === get_current_user_id()) {
            wp_send_json_error(array('message' => 'You cannot delete yourself.'));
        }

        // Deleta usuário
        require_once(ABSPATH . 'wp-admin/includes/user.php');
        $deleted = wp_delete_user($user_id);

        if ($deleted) {
            wp_send_json_success(array('message' => 'User deleted successfully.'));
        } else {
            wp_send_json_error(array('message' => 'Failed to delete user.'));
        }
    }

    /**
     * AJAX: Get Member Details
     */
    public static function get_member_details() {
        check_ajax_referer('eau_members_nonce', 'nonce');

        $user_id = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;

        if (!$user_id) {
            wp_send_json_error(array('message' => 'Invalid user ID.'));
        }

        $user = get_userdata($user_id);

        if (!$user) {
            wp_send_json_error(array('message' => 'User not found.'));
        }

        // Pega todos os meta fields
        $user_meta = get_user_meta($user_id);

        // Formata dados
        $data = array(
            'ID' => $user->ID,
            'user_login' => $user->user_login,
            'user_email' => $user->user_email,
            'display_name' => $user->display_name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'roles' => $user->roles,
            'institution_name' => Eau_User_Institution_Helper::get_user_institution_name($user_id),
            'membership_type' => Eau_User_Institution_Helper::get_user_membership_type($user_id),
            'status' => Eau_User_Institution_Helper::get_user_status($user_id),
            'meta' => array(),
        );

        // Adiciona meta fields (remove arrays e valores internos do WP)
        foreach ($user_meta as $key => $value) {
            // Pula meta fields internos do WP
            if (strpos($key, 'wp_') === 0 || strpos($key, '_') === 0) {
                continue;
            }

            $data['meta'][$key] = is_array($value) ? $value[0] : $value;
        }

        wp_send_json_success($data);
    }

    /**
     * AJAX: Update Member
     */
    public static function update_member() {
        check_ajax_referer('eau_members_nonce', 'nonce');

        // Verifica permissão
        if (!current_user_can('edit_users')) {
            wp_send_json_error(array('message' => 'You do not have permission to edit users.'));
        }

        $user_id = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;
        $fields = isset($_POST['fields']) ? $_POST['fields'] : array();

        if (!$user_id) {
            wp_send_json_error(array('message' => 'Invalid user ID.'));
        }

        // Atualiza campos do usuário
        $userdata = array('ID' => $user_id);

        $allowed_fields = array('user_email', 'display_name', 'first_name', 'last_name', 'role');

        foreach ($fields as $key => $value) {
            if (in_array($key, $allowed_fields)) {
                $userdata[$key] = sanitize_text_field($value);
            } else {
                // É um meta field
                update_user_meta($user_id, sanitize_key($key), sanitize_text_field($value));
            }
        }

        // Atualiza user data se houver campos
        if (count($userdata) > 1) {
            $updated = wp_update_user($userdata);

            if (is_wp_error($updated)) {
                wp_send_json_error(array('message' => $updated->get_error_message()));
            }
        }

        wp_send_json_success(array('message' => 'User updated successfully.'));
    }

    /**
     * AJAX: Export Members CSV
     */
    public static function export_members_csv() {
        check_ajax_referer('eau_members_nonce', 'nonce');

        // Verifica permissão
        if (!current_user_can('list_users')) {
            wp_die('You do not have permission to export users.');
        }

        // Pega parâmetros (mesmos filtros da listagem)
        $export_type = isset($_POST['export_type']) ? sanitize_text_field($_POST['export_type']) : 'all';
        $selected_ids = isset($_POST['selected_ids']) ? array_map('absint', $_POST['selected_ids']) : array();

        // Busca usuários
        if ($export_type === 'selected' && !empty($selected_ids)) {
            // Exporta apenas selecionados
            $users = array();
            foreach ($selected_ids as $user_id) {
                $user = get_userdata($user_id);
                if ($user) {
                    $users[] = array(
                        'ID' => $user->ID,
                        'display_name' => $user->display_name,
                        'user_email' => $user->user_email,
                        'roles' => $user->roles,
                        'status' => Eau_User_Institution_Helper::get_user_status($user->ID),
                        'institution_name' => Eau_User_Institution_Helper::get_user_institution_name($user->ID),
                        'membership_type' => Eau_User_Institution_Helper::get_user_membership_type($user->ID),
                    );
                }
            }
        } else {
            // Exporta todos (com filtros aplicados)
            $result = Eau_User_Institution_Helper::get_users_with_institutions(array(
                'number' => -1, // Todos
                'offset' => 0,
            ));
            $users = $result['users'];
        }

        // Gera CSV
        $filename = 'members-export-' . date('Y-m-d-H-i-s') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        $output = fopen('php://output', 'w');

        // Header
        fputcsv($output, array('ID', 'Name', 'Email', 'Status', 'Institution', 'Membership Type', 'User Type'));

        // Rows
        foreach ($users as $user) {
            $role = !empty($user['roles']) ? $user['roles'][0] : 'subscriber';

            fputcsv($output, array(
                $user['ID'],
                $user['display_name'],
                $user['user_email'],
                $user['status'],
                $user['institution_name'] ? $user['institution_name'] : '-',
                $user['membership_type'] ? $user['membership_type'] : '-',
                self::get_role_label($role),
            ));
        }

        fclose($output);
        exit;
    }

    /**
     * AJAX: Create Member
     */
    public static function create_member() {
        // Verifica nonce
        check_ajax_referer('eau_members_nonce', 'nonce');

        // Verifica permissões
        if (!current_user_can('create_users')) {
            wp_send_json_error(array('message' => 'You do not have permission to create users'));
        }

        // Pega dados
        $username = isset($_POST['user_login']) ? sanitize_user($_POST['user_login']) : '';
        $email = isset($_POST['user_email']) ? sanitize_email($_POST['user_email']) : '';
        $display_name = isset($_POST['display_name']) ? sanitize_text_field($_POST['display_name']) : '';
        $first_name = isset($_POST['first_name']) ? sanitize_text_field($_POST['first_name']) : '';
        $last_name = isset($_POST['last_name']) ? sanitize_text_field($_POST['last_name']) : '';
        $role = isset($_POST['role']) ? sanitize_text_field($_POST['role']) : 'subscriber';

        // Validação de email
        if (empty($email)) {
            wp_send_json_error(array('message' => 'Email is required'));
        }

        if (email_exists($email)) {
            wp_send_json_error(array('message' => 'Email already exists'));
        }

        // Se username estiver vazio, gera automaticamente do email
        if (empty($username)) {
            $username = sanitize_user(explode('@', $email)[0]);
            $username = preg_replace('/[^a-zA-Z0-9]/', '_', $username);
            $username = strtolower($username);
        }

        // Se username já existe, adiciona número ao final
        $original_username = $username;
        $counter = 1;
        while (username_exists($username)) {
            $username = $original_username . '_' . $counter;
            $counter++;

            // Previne loop infinito
            if ($counter > 1000) {
                wp_send_json_error(array('message' => 'Could not generate unique username'));
            }
        }

        // Cria usuário
        $user_id = wp_create_user($username, wp_generate_password(12, true), $email);

        if (is_wp_error($user_id)) {
            wp_send_json_error(array('message' => $user_id->get_error_message()));
        }

        // Atualiza dados adicionais
        wp_update_user(array(
            'ID' => $user_id,
            'display_name' => $display_name,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'role' => $role,
        ));

        // Salva campos personalizados (meta fields)
        $editable_fields = \EauSystem\Eau_Members_Settings::get_editable_fields();
        foreach ($editable_fields as $field_key => $field_config) {
            if ($field_config['type'] === 'meta' && isset($field_config['meta_key'])) {
                $meta_key = $field_config['meta_key'];
                if (isset($_POST[$meta_key])) {
                    update_user_meta($user_id, $meta_key, sanitize_text_field($_POST[$meta_key]));
                }
            }
        }

        wp_send_json_success(array(
            'message' => 'Member created successfully',
            'user_id' => $user_id,
        ));
    }

    /**
     * AJAX: Get Editable Fields Configuration
     */
    public static function get_editable_fields() {
        // Verifica nonce
        check_ajax_referer('eau_members_nonce', 'nonce');

        // Pega campos configurados
        $fields = \EauSystem\Eau_Members_Settings::get_editable_fields();

        wp_send_json_success($fields);
    }

    /**
     * AJAX: Get Institutions (para select dropdown)
     */
    public static function get_institutions() {
        // Verifica nonce
        check_ajax_referer('eau_members_nonce', 'nonce');

        // Busca posts do tipo "institutions"
        $args = array(
            'post_type' => 'institutions',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC',
        );

        $institutions_query = new \WP_Query($args);
        $institutions = array();

        if ($institutions_query->have_posts()) {
            while ($institutions_query->have_posts()) {
                $institutions_query->the_post();
                $post_id = get_the_ID();

                // Pega o campo ins_member_company_name (meta field)
                $company_name = get_post_meta($post_id, 'ins_member_company_name', true);

                $institutions[] = array(
                    'value' => $company_name,
                    'label' => get_the_title(),
                );
            }
            wp_reset_postdata();
        }

        wp_send_json_success($institutions);
    }
}
