<?php
namespace EauSystem\Ajax;

use EauSystem\Eau_User_Institution_Helper;
use EauSystem\Eau_Member_Institution_Service;

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

        // Bulk delete members (apenas super admin)
        add_action('wp_ajax_eau_bulk_delete_members', array(__CLASS__, 'bulk_delete_members'));

        // Get filtered member IDs (apenas super admin)
        add_action('wp_ajax_eau_get_filtered_member_ids', array(__CLASS__, 'get_filtered_member_ids'));

        // Bulk delete members em lotes (apenas super admin)
        add_action('wp_ajax_eau_bulk_delete_members_batch', array(__CLASS__, 'bulk_delete_members_batch'));

        // Update member
        add_action('wp_ajax_eau_update_member', array(__CLASS__, 'update_member'));

        // Get member details
        add_action('wp_ajax_eau_get_member_details', array(__CLASS__, 'get_member_details'));

        // Create member
        add_action('wp_ajax_eau_create_member', array(__CLASS__, 'create_member'));

        // Get editable fields configuration
        add_action('wp_ajax_eau_get_editable_fields', array(__CLASS__, 'get_editable_fields'));

        // Get institutions for select dropdown
        add_action('wp_ajax_eau_get_institutions_list', array(__CLASS__, 'get_institutions'));

        // Export CSV
        add_action('wp_ajax_eau_export_members_csv', array(__CLASS__, 'export_members_csv'));

        // Get member tags (para uso no Members Management)
        add_action('wp_ajax_eau_get_member_tags', array(__CLASS__, 'get_member_tags'));

        // Add member tag inline (para uso no Members Management)
        add_action('wp_ajax_eau_add_member_tag', array(__CLASS__, 'add_member_tag'));

        // Update member tags (quick edit from table)
        add_action('wp_ajax_eau_update_member_tags', array(__CLASS__, 'update_member_tags'));

        // Bulk add tags to members
        add_action('wp_ajax_eau_bulk_add_tags', array(__CLASS__, 'bulk_add_tags'));

        // Bulk remove tags from members
        add_action('wp_ajax_eau_bulk_remove_tags', array(__CLASS__, 'bulk_remove_tags'));

        // Bulk remove ALL tags from members
        add_action('wp_ajax_eau_bulk_remove_all_tags', array(__CLASS__, 'bulk_remove_all_tags'));

        // Reset email migration status (v1.62.0)
        add_action('wp_ajax_eau_reset_email_migration', array(__CLASS__, 'reset_email_migration'));

        // Bulk set member flag (Lifetime Member / Full Individual Member) (v1.72.4)
        add_action('wp_ajax_eau_bulk_set_member_flag', array(__CLASS__, 'bulk_set_member_flag'));
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
        $tag = isset($_POST['tag']) ? sanitize_text_field($_POST['tag']) : '';
        $position = isset($_POST['position']) ? sanitize_text_field($_POST['position']) : '';
        $registered_date_from = isset($_POST['registered_date_from']) ? sanitize_text_field($_POST['registered_date_from']) : '';
        $registered_date_to = isset($_POST['registered_date_to']) ? sanitize_text_field($_POST['registered_date_to']) : '';
        $email_migration = isset($_POST['email_migration']) ? sanitize_text_field($_POST['email_migration']) : '';
        $lifetime_member = isset($_POST['lifetime_member']) ? sanitize_text_field($_POST['lifetime_member']) : '';
        $full_individual_member = isset($_POST['full_individual_member']) ? sanitize_text_field($_POST['full_individual_member']) : '';
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
            'tag' => $tag,
            'position' => $position,
            'registered_date_from' => $registered_date_from,
            'registered_date_to' => $registered_date_to,
            'email_migration' => $email_migration,
            'lifetime_member' => $lifetime_member,
            'full_individual_member' => $full_individual_member,
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

        // Busca tags do membro
        $member_tags = get_user_meta($user['ID'], 'mem_tags', true);
        $member_tags = is_array($member_tags) ? $member_tags : array();

        // Gera HTML das tags como badges
        $tags_badges_html = '';
        if (!empty($member_tags)) {
            $tags_badges_html = '<div class="eau-member-tags-badges">';
            foreach ($member_tags as $tag_slug) {
                $tag = \EauSystem\Eau_Settings::get_tag_by_slug($tag_slug);
                if ($tag) {
                    $tags_badges_html .= sprintf(
                        '<span class="eau-member-tag-badge" style="background-color: %s">%s</span>',
                        esc_attr($tag['color']),
                        esc_html($tag['name'])
                    );
                }
            }
            $tags_badges_html .= '</div>';
        }

        // Flags: Lifetime Member e Full Individual Member (v1.72.3)
        $lifetime_member = get_user_meta($user['ID'], 'mem_lifetime_member', true);
        $full_individual_member = get_user_meta($user['ID'], 'mem_full_individual_member', true);

        $flags_badges_html = '';
        if ($lifetime_member === '1' || $full_individual_member === '1') {
            $flags_badges_html = '<div class="eau-member-flags-badges">';
            if ($lifetime_member === '1') {
                $flags_badges_html .= '<span class="eau-member-flag-badge eau-flag-lifetime"><i data-lucide="infinity"></i> Lifetime</span>';
            }
            if ($full_individual_member === '1') {
                $flags_badges_html .= '<span class="eau-member-flag-badge eau-flag-full-individual"><i data-lucide="user-check"></i> Full Individual</span>';
            }
            $flags_badges_html .= '</div>';
        }

        $member_html = sprintf(
            '<div class="eau-member-cell"><strong>%s</strong>%s%s</div>',
            esc_html($full_name),
            $flags_badges_html,
            $tags_badges_html
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

        // Position (mem_memberposition)
        $position_value = get_user_meta($user['ID'], 'mem_memberposition', true);
        $position_label = self::get_position_label($position_value);
        // Se não encontrar label no mapeamento, usa o valor original do banco
        $position_display = !empty($position_label) ? $position_label : $position_value;
        $position_html = !empty($position_display) ? esc_html($position_display) : '<span class="eau-text-muted">-</span>';

        // Status (case-insensitive comparison)
        $status_lower = strtolower($user['status']);
        $status_class = $status_lower === 'active' ? 'eau-status-badge-active' : 'eau-status-badge-inactive';
        $status_html = sprintf(
            '<span class="eau-status-badge %s">%s</span>',
            esc_attr($status_class),
            esc_html(ucfirst($user['status']))
        );

        // Email Migration Status (v1.62.0)
        $email_migration_status = get_user_meta($user['ID'], \EauSystem\EmailMigration\Eau_Email_Migration_Database::META_MIGRATION_STATUS, true);
        $email_status_html = self::format_email_migration_status($user['ID'], $email_migration_status);

        // Actions - os botões serão renderizados pelo JavaScript
        $actions_html = ''; // Vazio - o JS renderiza os botões com data-id

        return array(
            'ID' => $user['ID'],
            'member' => $member_html,
            'contact' => $contact_html,
            'membership' => $membership_html,
            'user_type' => $user_type_html,
            'position' => $position_html,
            'status' => $status_html,
            'email_status' => $email_status_html,
            'actions' => $actions_html,
            'member_tags' => implode(',', $member_tags), // Slugs das tags separadas por vírgula
            'email_migration_status' => $email_migration_status, // Raw status for JS
            'lifetime_member' => $lifetime_member === '1' ? '1' : '0', // Lifetime Member flag (v1.72.3)
            'full_individual_member' => $full_individual_member === '1' ? '1' : '0', // Full Individual Member flag (v1.72.3)
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
     * @since 1.66.2 - Suporte a múltiplos tipos
     * @param string|array $mem_type mem_type do usuário (string ou array)
     * @return string Label amigável (múltiplos tipos separados por vírgula)
     */
    private static function get_mem_type_label($mem_type) {
        $labels = array(
            'superAdmin' => 'Super Admin',
            'Admin' => 'Admin',
            'institutionAdmin' => 'Inst. Admin',
            'member' => 'Member',
            'non-member' => 'Non-Member',
            'Member' => 'Member', // Legado
        );

        // Normaliza para array
        if (is_array($mem_type)) {
            $types = $mem_type;
        } elseif (!empty($mem_type)) {
            $types = array($mem_type);
        } else {
            return 'Member';
        }

        // Mapeia para labels
        $type_labels = array();
        foreach ($types as $type) {
            if (isset($labels[$type])) {
                $type_labels[] = $labels[$type];
            } elseif (!empty($type)) {
                $type_labels[] = $type;
            }
        }

        return !empty($type_labels) ? implode(', ', $type_labels) : 'Member';
    }

    /**
     * Pega a classe CSS do mem_type
     *
     * @since 1.66.2 - Suporte a múltiplos tipos
     * @param string|array $mem_type mem_type do usuário (string ou array)
     * @return string Classe CSS
     */
    private static function get_mem_type_class($mem_type) {
        $admin_types = array('superAdmin', 'Admin', 'institutionAdmin');

        // Normaliza para array
        if (is_array($mem_type)) {
            $types = $mem_type;
        } elseif (!empty($mem_type)) {
            $types = array($mem_type);
        } else {
            return 'eau-user-type-member';
        }

        // Se qualquer tipo é admin, retorna classe admin
        foreach ($types as $type) {
            if (in_array($type, $admin_types)) {
                return 'eau-user-type-admin';
            }
        }

        return 'eau-user-type-member';
    }

    /**
     * Pega o label amigável da position
     *
     * @param string $position Position value (pode ser slug ou label)
     * @return string Label amigável ou string vazia se não encontrar
     */
    private static function get_position_label($position) {
        if (empty($position)) {
            return '';
        }

        // Mapeamento de slugs para labels
        $labels = array(
            'teacher' => 'Teacher',
            'academic_manager' => 'Academic Manager',
            'director_of_studies' => 'Director of Studies',
            'principal' => 'Principal',
            'general_manager_ceo' => 'General Manager/CEO',
            'marketing_sales' => 'Marketing/Sales',
            'admissions' => 'Admissions',
            'student_services' => 'Student Services',
            'other' => 'Other',
        );

        // Tenta encontrar pelo slug (case-insensitive)
        $position_lower = strtolower($position);
        if (isset($labels[$position_lower])) {
            return $labels[$position_lower];
        }

        // Tenta encontrar pelo slug com underscores (ex: "Director of Studies" -> "director_of_studies")
        $position_slug = strtolower(str_replace(' ', '_', $position));
        $position_slug = preg_replace('/[^a-z0-9_]/', '', $position_slug);
        if (isset($labels[$position_slug])) {
            return $labels[$position_slug];
        }

        // Se o valor já é um label válido (case-insensitive), retorna o label padronizado
        $labels_lower = array_map('strtolower', $labels);
        $search_key = array_search($position_lower, $labels_lower);
        if ($search_key !== false) {
            return $labels[$search_key];
        }

        // Não encontrou - retorna vazio para usar o valor original
        return '';
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

        // Institution Admin: verifica se pode acessar este usuário
        $current_user_id = get_current_user_id();
        if (!Eau_User_Institution_Helper::can_user_access_user($current_user_id, $user_id)) {
            wp_send_json_error(array('message' => 'You do not have permission to delete this user.'));
        }

        // CASCADING DELETE: Deleta todas as atividades deste membro primeiro
        global $wpdb;

        // Pega o mem_userid do membro
        $mem_userid = get_user_meta($user_id, 'mem_userid', true);

        if (!empty($mem_userid)) {
            // Busca todas as atividades com esse act_user_id
            $activity_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT p.ID
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_type = 'activitie'
                AND p.post_status = 'publish'
                AND pm.meta_key = 'act_user_id'
                AND pm.meta_value = %s",
                $mem_userid
            ));

            // Deleta cada atividade (force delete, não vai para trash)
            foreach ($activity_ids as $activity_id) {
                wp_delete_post($activity_id, true);
            }

            // Invalida cache de estatísticas
            \EauSystem\Eau_Activities_Stats_Cache::invalidate_all();
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

        // Institution Admin: verifica se pode acessar este usuário
        $current_user_id = get_current_user_id();
        if (!Eau_User_Institution_Helper::can_user_access_user($current_user_id, $user_id)) {
            wp_send_json_error(array('message' => 'Access denied.'));
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

            // get_user_meta sem key retorna array de arrays
            // maybe_unserialize para suportar campos que armazenam arrays (ex: mem_type)
            $raw_value = is_array($value) ? $value[0] : $value;
            $data['meta'][$key] = maybe_unserialize($raw_value);
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

        // Institution Admin: verifica se pode acessar este usuário
        $current_user_id = get_current_user_id();
        if (!Eau_User_Institution_Helper::can_user_access_user($current_user_id, $user_id)) {
            wp_send_json_error(array('message' => 'You do not have permission to edit this user.'));
        }

        // Atualiza campos do usuário
        $userdata = array('ID' => $user_id);

        $allowed_fields = array('user_email', 'display_name', 'first_name', 'last_name', 'role');

        foreach ($fields as $key => $value) {
            if (in_array($key, $allowed_fields)) {
                $userdata[$key] = sanitize_text_field($value);
            } else {
                // É um meta field
                $sanitized_key = sanitize_key($key);

                // Tratamento especial para mem_type (v1.66.0, v1.66.2 - suporte a múltiplos tipos)
                if ($sanitized_key === 'mem_type') {
                    // Converte para array se necessário
                    $new_types = is_array($value) ? $value : array($value);
                    $new_types = array_filter(array_map('sanitize_text_field', $new_types));

                    // Pega tipos atuais como array
                    $current_types = Eau_User_Institution_Helper::get_user_types($user_id);

                    // Valida permissões para cada tipo
                    $allowed_types = \EauSystem\Eau_Members_Settings::get_allowed_user_types();
                    foreach ($new_types as $type) {
                        if (!isset($allowed_types[$type])) {
                            wp_send_json_error(array('message' => "You are not allowed to assign the type: $type"));
                        }
                    }

                    // Se está adicionando institutionAdmin, também adiciona ao novo sistema
                    if (in_array('institutionAdmin', $new_types)) {
                        // Verifica se o usuário está linkado a uma instituição
                        $company_id = get_user_meta($user_id, 'mem_membercompanyname', true);
                        if (empty($company_id)) {
                            wp_send_json_error(array('message' => 'Cannot set as Institution Admin: user is not linked to any institution.'));
                        }

                        // Busca a instituição pelo company_id
                        $institution = Eau_Member_Institution_Service::get_institution_by_company_id($company_id);
                        if ($institution) {
                            // Usa o novo sistema para adicionar à lista de managed institutions
                            Eau_User_Institution_Helper::add_managed_institution($user_id, $institution->ID);
                        }
                    } else {
                        // Se removeu institutionAdmin, limpa managed institutions
                        if (in_array('institutionAdmin', $current_types)) {
                            delete_user_meta($user_id, 'mem_managed_institutions');
                        }
                    }

                    // Atualiza o mem_type como array
                    update_user_meta($user_id, 'mem_type', $new_types);

                    continue; // Não processa mais este campo
                }

                // Tratamento especial para mem_tags (array de slugs)
                if ($sanitized_key === 'mem_tags') {
                    // Converte string separada por vírgulas em array
                    $tags_array = array_filter(array_map('trim', explode(',', $value)));
                    $tags_array = array_map('sanitize_text_field', $tags_array);
                    update_user_meta($user_id, $sanitized_key, $tags_array);
                } else {
                    update_user_meta($user_id, $sanitized_key, sanitize_text_field($value));
                }
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

        // Institution Admin: vincular automaticamente à instituição usando serviço centralizado
        // TODO: Frontend deveria permitir ESCOLHER a instituição ao criar membro
        $current_user_id = get_current_user_id();
        if (Eau_User_Institution_Helper::is_institution_admin($current_user_id)) {
            $company_ids = Eau_User_Institution_Helper::get_user_managed_company_ids($current_user_id);
            if (!empty($company_ids)) {
                // Por enquanto, usa a PRIMEIRA instituição
                // TODO: Adicionar seletor de instituição no frontend
                $company_id = $company_ids[0];

                // Busca o post ID da instituição pelo company_id
                $institution = Eau_Member_Institution_Service::get_institution_by_company_id($company_id);
                if ($institution) {
                    // Usa o serviço centralizado para vincular o membro
                    Eau_Member_Institution_Service::link_member($user_id, $institution->ID, array(
                        'member_type' => 'member',
                        'reason' => 'Member created by institution admin',
                    ));
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

    /**
     * AJAX: Bulk Delete Members (apenas super admin)
     */
    public static function bulk_delete_members() {
        // Verifica nonce
        check_ajax_referer('eau_members_nonce', 'nonce');

        // Verifica se é super admin
        if (!Eau_User_Institution_Helper::is_super_admin()) {
            wp_send_json_error(array(
                'message' => 'Permission denied. Only super admins can perform bulk deletions.'
            ));
        }

        // Pega IDs dos membros
        $member_ids = isset($_POST['ids']) ? array_map('absint', $_POST['ids']) : array();

        if (empty($member_ids)) {
            wp_send_json_error(array(
                'message' => 'No members selected for deletion.'
            ));
        }

        $deleted_count = 0;
        $failed_count = 0;
        $failed_members = array();

        foreach ($member_ids as $user_id) {
            // Não permite deletar o próprio usuário
            if ($user_id === get_current_user_id()) {
                $failed_count++;
                $user = get_userdata($user_id);
                $failed_members[] = $user ? $user->display_name : "ID: $user_id";
                continue;
            }

            // Tenta deletar o usuário
            $deleted = wp_delete_user($user_id);

            if ($deleted) {
                $deleted_count++;
            } else {
                $failed_count++;
                $user = get_userdata($user_id);
                $failed_members[] = $user ? $user->display_name : "ID: $user_id";
            }
        }

        // Prepara mensagem de resposta
        $message = sprintf(
            '%d member(s) deleted successfully.',
            $deleted_count
        );

        if ($failed_count > 0) {
            $message .= sprintf(
                ' %d member(s) could not be deleted: %s',
                $failed_count,
                implode(', ', $failed_members)
            );
        }

        wp_send_json_success(array(
            'message' => $message,
            'deleted_count' => $deleted_count,
            'failed_count' => $failed_count,
        ));
    }

    /**
     * AJAX: Get Filtered Member IDs (apenas super admin)
     * Retorna todos os IDs de membros que correspondem aos filtros atuais
     */
    public static function get_filtered_member_ids() {
        // Verifica nonce
        check_ajax_referer('eau_members_nonce', 'nonce');

        // Verifica se é super admin
        if (!Eau_User_Institution_Helper::is_super_admin()) {
            wp_send_json_error(array(
                'message' => 'Permission denied. Only super admins can perform this action.'
            ));
        }

        // Pega filtros
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
        $role = isset($_POST['role']) ? sanitize_text_field($_POST['role']) : '';
        $institution_id = isset($_POST['institution_id']) ? absint($_POST['institution_id']) : 0;
        $membership_type = isset($_POST['membership_type']) ? sanitize_text_field($_POST['membership_type']) : '';
        $registered_date_from = isset($_POST['registered_date_from']) ? sanitize_text_field($_POST['registered_date_from']) : '';
        $registered_date_to = isset($_POST['registered_date_to']) ? sanitize_text_field($_POST['registered_date_to']) : '';

        // Busca usuários com filtros (sem paginação)
        $result = Eau_User_Institution_Helper::get_users_with_institutions(array(
            'number' => -1, // Sem limite
            'offset' => 0,
            'search' => $search,
            'role' => $role,
            'status' => $status,
            'institution_id' => $institution_id,
            'membership_type' => $membership_type,
            'registered_date_from' => $registered_date_from,
            'registered_date_to' => $registered_date_to,
        ));

        $member_ids = array();
        foreach ($result['users'] as $user) {
            $member_ids[] = $user['ID'];
        }

        wp_send_json_success(array(
            'ids' => $member_ids,
            'total' => count($member_ids),
        ));
    }

    /**
     * AJAX: Bulk Delete Members Batch (apenas super admin)
     * Deleta um lote de membros por vez (max 50)
     */
    public static function bulk_delete_members_batch() {
        // Verifica nonce
        check_ajax_referer('eau_members_nonce', 'nonce');

        // Verifica se é super admin
        if (!Eau_User_Institution_Helper::is_super_admin()) {
            wp_send_json_error(array(
                'message' => 'Permission denied. Only super admins can perform bulk deletions.'
            ));
        }

        // Pega IDs do lote (máximo 50)
        $member_ids = isset($_POST['ids']) ? array_map('absint', $_POST['ids']) : array();

        // Limita a 50 por lote para evitar timeout
        $member_ids = array_slice($member_ids, 0, 50);

        if (empty($member_ids)) {
            wp_send_json_error(array(
                'message' => 'No members provided for deletion.'
            ));
        }

        $deleted_count = 0;
        $failed_count = 0;
        $failed_members = array();
        $current_user_id = get_current_user_id();

        global $wpdb;

        foreach ($member_ids as $user_id) {
            // Não permite deletar o próprio usuário
            if ($user_id === $current_user_id) {
                $failed_count++;
                $user = get_userdata($user_id);
                $failed_members[] = $user ? $user->display_name : "ID: $user_id";
                continue;
            }

            // CASCADING DELETE: Deleta todas as atividades deste membro primeiro
            $mem_userid = get_user_meta($user_id, 'mem_userid', true);

            if (!empty($mem_userid)) {
                // Busca todas as atividades com esse act_user_id
                $activity_ids = $wpdb->get_col($wpdb->prepare(
                    "SELECT p.ID
                    FROM {$wpdb->posts} p
                    INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                    WHERE p.post_type = 'activitie'
                    AND p.post_status = 'publish'
                    AND pm.meta_key = 'act_user_id'
                    AND pm.meta_value = %s",
                    $mem_userid
                ));

                // Deleta cada atividade (force delete, não vai para trash)
                foreach ($activity_ids as $activity_id) {
                    wp_delete_post($activity_id, true);
                }
            }

            // Tenta deletar o usuário
            $deleted = wp_delete_user($user_id);

            if ($deleted) {
                $deleted_count++;
            } else {
                $failed_count++;
                $user = get_userdata($user_id);
                $failed_members[] = $user ? $user->display_name : "ID: $user_id";
            }
        }

        // Invalida cache de estatísticas (bulk delete afeta múltiplos usuários)
        \EauSystem\Eau_Activities_Stats_Cache::invalidate_all();

        wp_send_json_success(array(
            'deleted_count' => $deleted_count,
            'failed_count' => $failed_count,
            'failed_members' => $failed_members,
        ));
    }

    /**
     * AJAX: Get Member Tags
     * Retorna todas as tags disponíveis para membros
     */
    public static function get_member_tags() {
        // Aceita ambos os nonces (settings e members)
        $valid_nonce = false;

        if (isset($_POST['nonce'])) {
            if (wp_verify_nonce($_POST['nonce'], 'eau_members_nonce')) {
                $valid_nonce = true;
            } elseif (wp_verify_nonce($_POST['nonce'], 'eau_settings_nonce')) {
                $valid_nonce = true;
            }
        }

        if (!$valid_nonce) {
            wp_send_json_error(array('message' => 'Invalid nonce.'));
        }

        $tags = \EauSystem\Eau_Settings::get_member_tags();

        wp_send_json_success(array(
            'tags' => $tags,
        ));
    }

    /**
     * AJAX: Add Member Tag
     * Cria uma nova tag (para uso inline no Members Management)
     */
    public static function add_member_tag() {
        // Aceita ambos os nonces (settings e members)
        $valid_nonce = false;

        if (isset($_POST['nonce'])) {
            if (wp_verify_nonce($_POST['nonce'], 'eau_members_nonce')) {
                $valid_nonce = true;
            } elseif (wp_verify_nonce($_POST['nonce'], 'eau_settings_nonce')) {
                $valid_nonce = true;
            }
        }

        if (!$valid_nonce) {
            wp_send_json_error(array('message' => 'Invalid nonce.'));
        }

        // Verifica permissão (superAdmin ou Admin)
        if (!Eau_User_Institution_Helper::is_super_admin() && !Eau_User_Institution_Helper::is_admin()) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $color = isset($_POST['color']) ? sanitize_hex_color($_POST['color']) : null;

        if (empty($name)) {
            wp_send_json_error(array('message' => 'Tag name is required.'));
        }

        $result = \EauSystem\Eau_Settings::add_member_tag($name, $color);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array(
            'message' => 'Tag created successfully.',
            'tag' => $result,
        ));
    }

    /**
     * AJAX: Update Member Tags (quick edit from table)
     * Permite atualizar tags de um membro diretamente da tabela
     */
    public static function update_member_tags() {
        // Verifica nonce
        check_ajax_referer('eau_members_nonce', 'nonce');

        // Verifica permissão (Admin ou superAdmin)
        $current_user_id = get_current_user_id();
        if (!Eau_User_Institution_Helper::is_super_admin($current_user_id) &&
            !Eau_User_Institution_Helper::is_admin($current_user_id)) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        // Pega parâmetros
        $user_id = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;
        $tags = isset($_POST['tags']) ? $_POST['tags'] : array();

        if (!$user_id) {
            wp_send_json_error(array('message' => 'User ID is required.'));
        }

        // Verifica se usuário existe
        $user = get_userdata($user_id);
        if (!$user) {
            wp_send_json_error(array('message' => 'User not found.'));
        }

        // Sanitiza tags (array de slugs)
        if (is_string($tags)) {
            $tags = array_filter(array_map('trim', explode(',', $tags)));
        }
        $tags = array_map('sanitize_text_field', $tags);

        // Atualiza meta do usuário
        update_user_meta($user_id, 'mem_tags', $tags);

        // Retorna tags atualizadas com informações completas
        $tags_data = array();
        foreach ($tags as $slug) {
            $tag = \EauSystem\Eau_Settings::get_tag_by_slug($slug);
            if ($tag) {
                $tags_data[] = $tag;
            }
        }

        wp_send_json_success(array(
            'message' => 'Tags updated successfully.',
            'tags' => $tags_data,
        ));
    }

    /**
     * AJAX: Bulk Add Tags to Members
     * Adiciona tags a múltiplos membros de uma vez
     */
    public static function bulk_add_tags() {
        // Verifica nonce
        check_ajax_referer('eau_members_nonce', 'nonce');

        // Verifica permissão (Admin ou superAdmin)
        $current_user_id = get_current_user_id();
        if (!Eau_User_Institution_Helper::is_super_admin($current_user_id) &&
            !Eau_User_Institution_Helper::is_admin($current_user_id)) {
            wp_send_json_error(array('message' => 'Permission denied. Only admins can perform bulk tag operations.'));
        }

        // Pega parâmetros
        $member_ids = isset($_POST['member_ids']) ? array_map('absint', $_POST['member_ids']) : array();
        $tags_to_add = isset($_POST['tags']) ? $_POST['tags'] : array();

        if (empty($member_ids)) {
            wp_send_json_error(array('message' => 'No members selected.'));
        }

        if (empty($tags_to_add)) {
            wp_send_json_error(array('message' => 'No tags selected.'));
        }

        // Sanitiza tags
        if (is_string($tags_to_add)) {
            $tags_to_add = array_filter(array_map('trim', explode(',', $tags_to_add)));
        }
        $tags_to_add = array_map('sanitize_text_field', $tags_to_add);

        // Processa em lotes de 50
        $batch_size = 50;
        $member_ids = array_slice($member_ids, 0, $batch_size);

        $updated_count = 0;
        $failed_count = 0;

        foreach ($member_ids as $user_id) {
            // Pega tags atuais do usuário
            $current_tags = get_user_meta($user_id, 'mem_tags', true);
            if (!is_array($current_tags)) {
                $current_tags = array();
            }

            // Merge com novas tags (sem duplicatas)
            $new_tags = array_unique(array_merge($current_tags, $tags_to_add));

            // Atualiza
            $result = update_user_meta($user_id, 'mem_tags', $new_tags);
            if ($result !== false) {
                $updated_count++;
            } else {
                $failed_count++;
            }
        }

        wp_send_json_success(array(
            'message' => sprintf('%d member(s) updated successfully.', $updated_count),
            'updated_count' => $updated_count,
            'failed_count' => $failed_count,
        ));
    }

    /**
     * AJAX: Bulk Remove Tags from Members
     * Remove tags de múltiplos membros de uma vez
     */
    public static function bulk_remove_tags() {
        // Verifica nonce
        check_ajax_referer('eau_members_nonce', 'nonce');

        // Verifica permissão (Admin ou superAdmin)
        $current_user_id = get_current_user_id();
        if (!Eau_User_Institution_Helper::is_super_admin($current_user_id) &&
            !Eau_User_Institution_Helper::is_admin($current_user_id)) {
            wp_send_json_error(array('message' => 'Permission denied. Only admins can perform bulk tag operations.'));
        }

        // Pega parâmetros
        $member_ids = isset($_POST['member_ids']) ? array_map('absint', $_POST['member_ids']) : array();
        $tags_to_remove = isset($_POST['tags']) ? $_POST['tags'] : array();

        if (empty($member_ids)) {
            wp_send_json_error(array('message' => 'No members selected.'));
        }

        if (empty($tags_to_remove)) {
            wp_send_json_error(array('message' => 'No tags selected.'));
        }

        // Sanitiza tags
        if (is_string($tags_to_remove)) {
            $tags_to_remove = array_filter(array_map('trim', explode(',', $tags_to_remove)));
        }
        $tags_to_remove = array_map('sanitize_text_field', $tags_to_remove);

        // Processa em lotes de 50
        $batch_size = 50;
        $member_ids = array_slice($member_ids, 0, $batch_size);

        $updated_count = 0;
        $failed_count = 0;

        foreach ($member_ids as $user_id) {
            // Pega tags atuais do usuário
            $current_tags = get_user_meta($user_id, 'mem_tags', true);
            if (!is_array($current_tags)) {
                $current_tags = array();
            }

            // Remove as tags especificadas
            $new_tags = array_values(array_diff($current_tags, $tags_to_remove));

            // Atualiza
            $result = update_user_meta($user_id, 'mem_tags', $new_tags);
            if ($result !== false) {
                $updated_count++;
            } else {
                $failed_count++;
            }
        }

        wp_send_json_success(array(
            'message' => sprintf('%d member(s) updated successfully.', $updated_count),
            'updated_count' => $updated_count,
            'failed_count' => $failed_count,
        ));
    }

    /**
     * AJAX: Bulk Remove ALL Tags from Members
     * Remove TODAS as tags de múltiplos membros de uma vez
     */
    public static function bulk_remove_all_tags() {
        // Verifica nonce
        check_ajax_referer('eau_members_nonce', 'nonce');

        // Verifica permissão (Admin ou superAdmin)
        $current_user_id = get_current_user_id();
        if (!Eau_User_Institution_Helper::is_super_admin($current_user_id) &&
            !Eau_User_Institution_Helper::is_admin($current_user_id)) {
            wp_send_json_error(array('message' => 'Permission denied. Only admins can perform bulk tag operations.'));
        }

        // Pega parâmetros
        $member_ids = isset($_POST['member_ids']) ? array_map('absint', $_POST['member_ids']) : array();

        if (empty($member_ids)) {
            wp_send_json_error(array('message' => 'No members selected.'));
        }

        // Processa em lotes de 50
        $batch_size = 50;
        $member_ids = array_slice($member_ids, 0, $batch_size);

        $updated_count = 0;
        $failed_count = 0;

        foreach ($member_ids as $user_id) {
            // Remove todas as tags (seta como array vazio)
            $result = update_user_meta($user_id, 'mem_tags', array());
            if ($result !== false) {
                $updated_count++;
            } else {
                // update_user_meta retorna false quando o valor não mudou
                // Verifica se já estava vazio
                $current = get_user_meta($user_id, 'mem_tags', true);
                if (empty($current) || $current === array()) {
                    $updated_count++; // Já estava vazio, considera sucesso
                } else {
                    $failed_count++;
                }
            }
        }

        wp_send_json_success(array(
            'message' => sprintf('All tags removed from %d member(s).', $updated_count),
            'updated_count' => $updated_count,
            'failed_count' => $failed_count,
        ));
    }

    /**
     * Format email migration status for display in table
     *
     * @since 1.62.0
     * @param int $user_id User ID
     * @param string $status Migration status
     * @return string HTML
     */
    private static function format_email_migration_status($user_id, $status) {
        // Check if user is exempt (superAdmin or Admin)
        $mem_type = get_user_meta($user_id, 'mem_type', true);
        if (in_array($mem_type, array('superAdmin', 'Admin'))) {
            return '<span class="eau-email-status-badge eau-email-status-exempt">Exempt</span>';
        }

        // Status mapping
        $status_config = array(
            'pending' => array(
                'label' => 'Pending',
                'class' => 'eau-email-status-pending',
                'show_reset' => false,
            ),
            'awaiting_verification' => array(
                'label' => 'Awaiting',
                'class' => 'eau-email-status-awaiting',
                'show_reset' => true,
            ),
            'verified' => array(
                'label' => 'Verified',
                'class' => 'eau-email-status-verified',
                'show_reset' => true,
            ),
            'skipped' => array(
                'label' => 'Skipped',
                'class' => 'eau-email-status-skipped',
                'show_reset' => true,
            ),
        );

        // Default for empty/not set
        if (empty($status) || !isset($status_config[$status])) {
            $html = '<span class="eau-email-status-badge eau-email-status-not-set">Not Set</span>';
            return $html;
        }

        $config = $status_config[$status];
        $html = sprintf(
            '<span class="eau-email-status-badge %s">%s</span>',
            esc_attr($config['class']),
            esc_html($config['label'])
        );

        // Add reset button if applicable
        if ($config['show_reset'] && (Eau_User_Institution_Helper::is_super_admin() || Eau_User_Institution_Helper::is_admin())) {
            $html .= sprintf(
                ' <button type="button" class="eau-btn-icon eau-btn-reset-email" data-user-id="%d" title="Reset email migration status">
                    <i data-lucide="rotate-ccw"></i>
                </button>',
                esc_attr($user_id)
            );
        }

        return $html;
    }

    /**
     * AJAX: Reset email migration status
     *
     * @since 1.62.0
     */
    public static function reset_email_migration() {
        check_ajax_referer('eau_members_nonce', 'nonce');

        // Verify permission (superAdmin or Admin only)
        if (!Eau_User_Institution_Helper::is_super_admin() && !Eau_User_Institution_Helper::is_admin()) {
            wp_send_json_error(array('message' => 'You do not have permission to reset email migration status.'));
        }

        $user_id = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;

        if (!$user_id) {
            wp_send_json_error(array('message' => 'Invalid user ID.'));
        }

        // Check if user exists
        $user = get_userdata($user_id);
        if (!$user) {
            wp_send_json_error(array('message' => 'User not found.'));
        }

        // Reset using the Email Migration class
        $result = \EauSystem\EmailMigration\Eau_Email_Migration::reset_user_migration($user_id);

        if ($result === true) {
            wp_send_json_success(array(
                'message' => sprintf('Email migration status reset for %s.', $user->display_name),
            ));
        } else {
            wp_send_json_error(array('message' => 'Failed to reset email migration status.'));
        }
    }

    /**
     * AJAX: Bulk set member flag (Lifetime Member / Full Individual Member)
     *
     * @since 1.72.4
     */
    public static function bulk_set_member_flag() {
        check_ajax_referer('eau_members_nonce', 'nonce');

        // Verify permission (superAdmin or Admin only)
        if (!Eau_User_Institution_Helper::is_super_admin() && !Eau_User_Institution_Helper::is_admin()) {
            wp_send_json_error(array('message' => 'You do not have permission to update member flags.'));
        }

        $ids = isset($_POST['ids']) ? array_map('absint', (array) $_POST['ids']) : array();
        $flag_name = isset($_POST['flag_name']) ? sanitize_text_field($_POST['flag_name']) : '';
        $flag_value = isset($_POST['flag_value']) ? sanitize_text_field($_POST['flag_value']) : '';

        // Validate flag name
        $allowed_flags = array('mem_lifetime_member', 'mem_full_individual_member');
        if (!in_array($flag_name, $allowed_flags)) {
            wp_send_json_error(array('message' => 'Invalid flag name.'));
        }

        // Validate flag value
        if (!in_array($flag_value, array('0', '1'))) {
            wp_send_json_error(array('message' => 'Invalid flag value.'));
        }

        if (empty($ids)) {
            wp_send_json_error(array('message' => 'No members selected.'));
        }

        $updated = 0;
        $failed = 0;

        foreach ($ids as $user_id) {
            // Check if user exists
            $user = get_userdata($user_id);
            if (!$user) {
                $failed++;
                continue;
            }

            // Update user meta
            $result = update_user_meta($user_id, $flag_name, $flag_value);

            // update_user_meta returns false if the value is the same, so we check if the meta now has the right value
            $current_value = get_user_meta($user_id, $flag_name, true);
            if ($current_value === $flag_value) {
                $updated++;
            } else {
                $failed++;
            }
        }

        $flag_label = $flag_name === 'mem_lifetime_member' ? 'Lifetime Member' : 'Full Individual Member';
        $value_label = $flag_value === '1' ? 'Yes' : 'No';

        if ($failed === 0) {
            wp_send_json_success(array(
                'message' => sprintf('%d member(s) updated: %s set to %s.', $updated, $flag_label, $value_label),
                'updated' => $updated,
            ));
        } else {
            wp_send_json_success(array(
                'message' => sprintf('%d member(s) updated, %d failed: %s set to %s.', $updated, $failed, $flag_label, $value_label),
                'updated' => $updated,
                'failed' => $failed,
            ));
        }
    }
}
