<?php
namespace EauSystem;

/**
 * Helper class para relacionamento entre User e Institution
 *
 * Relacionamento:
 * User (usermeta: mem_membercompanyname) = Institution (meta: ins_company_id)
 */
class Eau_User_Institution_Helper {

    /**
     * Pega a instituição relacionada ao usuário
     *
     * @param int $user_id ID do usuário
     * @return \WP_Post|null Post da instituição ou null se não encontrar
     */
    public static function get_user_institution($user_id) {
        // Pega o company ID do usuário
        $mem_company_id = get_user_meta($user_id, 'mem_membercompanyname', true);

        if (empty($mem_company_id)) {
            return null;
        }

        // Busca a instituição com esse company ID
        global $wpdb;

        $institution_id = $wpdb->get_var($wpdb->prepare(
            "SELECT p.ID
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'institutions'
            AND p.post_status = 'publish'
            AND pm.meta_key = 'ins_company_id'
            AND pm.meta_value = %s
            LIMIT 1",
            $mem_company_id
        ));

        if (!$institution_id) {
            return null;
        }

        return get_post($institution_id);
    }

    /**
     * Pega o tipo de membership do usuário (ins_type da instituição)
     *
     * @param int $user_id ID do usuário
     * @return string|null Tipo de membership ou null
     */
    public static function get_user_membership_type($user_id) {
        $institution = self::get_user_institution($user_id);

        if (!$institution) {
            return null;
        }

        $ins_type = get_post_meta($institution->ID, 'ins_type', true);

        return !empty($ins_type) ? $ins_type : null;
    }

    /**
     * Pega o nome da instituição do usuário
     *
     * @param int $user_id ID do usuário
     * @return string|null Nome da instituição ou null
     */
    public static function get_user_institution_name($user_id) {
        $institution = self::get_user_institution($user_id);

        if (!$institution) {
            return null;
        }

        return $institution->post_title;
    }

    /**
     * Pega o status do usuário (do metadado mem_status)
     *
     * @param int $user_id ID do usuário
     * @return string Status do usuário (active, inactive, etc)
     */
    public static function get_user_status($user_id) {
        $status = get_user_meta($user_id, 'mem_status', true);

        return !empty($status) ? $status : 'unknown';
    }

    /**
     * Pega todos os usuários com suas instituições (para listagem)
     *
     * @param array $args Argumentos de busca (paged, search, filters, etc)
     * @return array Array com 'users' e 'total'
     */
    public static function get_users_with_institutions($args = array()) {
        global $wpdb;

        $defaults = array(
            'number' => 20,
            'offset' => 0,
            'search' => '',
            'role' => '',
            'status' => '',
            'institution_id' => '',
            'membership_type' => '',
            'registered_date_from' => '',
            'registered_date_to' => '',
            'orderby' => 'display_name',
            'order' => 'ASC',
        );

        $args = wp_parse_args($args, $defaults);

        // Filtro automático para Institution Admin
        $current_user_id = get_current_user_id();
        $is_institution_admin = self::is_institution_admin($current_user_id);

        if ($is_institution_admin && empty($args['institution_id'])) {
            // Força filtro pela instituição do usuário logado
            $user_institution = self::get_user_institution($current_user_id);
            if ($user_institution) {
                $args['institution_id'] = $user_institution->ID;
            } else {
                // Sem instituição: retorna vazio
                return array(
                    'users' => array(),
                    'total' => 0
                );
            }
        }

        // Monta a query base
        $where = array("1=1");
        $join = array();

        // Busca por nome, email ou phone
        if (!empty($args['search'])) {
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $where[] = $wpdb->prepare(
                "(u.user_login LIKE %s OR u.user_email LIKE %s OR u.display_name LIKE %s)",
                $search, $search, $search
            );
        }

        // Filtro por status (mem_status)
        if (!empty($args['status'])) {
            $join[] = "INNER JOIN {$wpdb->usermeta} um_status ON u.ID = um_status.user_id AND um_status.meta_key = 'mem_status'";
            $where[] = $wpdb->prepare("um_status.meta_value = %s", $args['status']);
        }

        // Filtro por user type (mem_type do JetEngine)
        if (!empty($args['role'])) {
            $join[] = "INNER JOIN {$wpdb->usermeta} um_type ON u.ID = um_type.user_id AND um_type.meta_key = 'mem_type'";
            $where[] = $wpdb->prepare("um_type.meta_value = %s", $args['role']);
        }

        // Filtro por instituição
        if (!empty($args['institution_id'])) {
            // Primeiro pega o ins_company_id da instituição
            $company_id = get_post_meta($args['institution_id'], 'ins_company_id', true);
            if (!empty($company_id)) {
                $join[] = "INNER JOIN {$wpdb->usermeta} um_company ON u.ID = um_company.user_id AND um_company.meta_key = 'mem_membercompanyname'";
                $where[] = $wpdb->prepare("um_company.meta_value = %s", $company_id);
            }
        }

        // Filtro por membership type (ins_type)
        if (!empty($args['membership_type'])) {
            // Precisa fazer JOIN com posts (institutions) via postmeta
            // 1. JOIN usermeta para pegar mem_membercompanyname
            // 2. JOIN postmeta para pegar ins_company_id
            // 3. JOIN postmeta para filtrar por ins_type
            $join[] = "INNER JOIN {$wpdb->usermeta} um_mtype_comp ON u.ID = um_mtype_comp.user_id AND um_mtype_comp.meta_key = 'mem_membercompanyname'";
            $join[] = "INNER JOIN {$wpdb->postmeta} pm_comp_id ON pm_comp_id.meta_key = 'ins_company_id' AND pm_comp_id.meta_value = um_mtype_comp.meta_value";
            $join[] = "INNER JOIN {$wpdb->postmeta} pm_ins_type ON pm_ins_type.post_id = pm_comp_id.post_id AND pm_ins_type.meta_key = 'ins_type'";
            $where[] = $wpdb->prepare("pm_ins_type.meta_value = %s", $args['membership_type']);
        }

        // Filtro por data de registro (from)
        if (!empty($args['registered_date_from'])) {
            $where[] = $wpdb->prepare("u.user_registered >= %s", $args['registered_date_from'] . ' 00:00:00');
        }

        // Filtro por data de registro (to)
        if (!empty($args['registered_date_to'])) {
            $where[] = $wpdb->prepare("u.user_registered <= %s", $args['registered_date_to'] . ' 23:59:59');
        }

        // Monta query de contagem
        $join_sql = !empty($join) ? implode(' ', array_unique($join)) : '';
        $where_sql = implode(' AND ', $where);

        $count_query = "SELECT COUNT(DISTINCT u.ID)
                       FROM {$wpdb->users} u
                       {$join_sql}
                       WHERE {$where_sql}";

        $total = $wpdb->get_var($count_query);

        // Monta query de dados
        $orderby = sanitize_sql_orderby("{$args['orderby']} {$args['order']}");

        $data_query = "SELECT DISTINCT u.ID, u.user_login, u.user_email, u.display_name, u.user_registered
                      FROM {$wpdb->users} u
                      {$join_sql}
                      WHERE {$where_sql}
                      ORDER BY {$orderby}
                      LIMIT %d OFFSET %d";

        $user_ids = $wpdb->get_results($wpdb->prepare(
            $data_query,
            $args['number'],
            $args['offset']
        ));

        // Pega dados completos dos usuários
        $users = array();
        foreach ($user_ids as $user_data) {
            $user = get_userdata($user_data->ID);
            if ($user) {
                $users[] = array(
                    'ID' => $user->ID,
                    'display_name' => $user->display_name,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'user_email' => $user->user_email,
                    'user_login' => $user->user_login,
                    'roles' => $user->roles,
                    'mem_type' => get_user_meta($user->ID, 'mem_type', true),
                    'status' => self::get_user_status($user->ID),
                    'institution_name' => self::get_user_institution_name($user->ID),
                    'membership_type' => self::get_user_membership_type($user->ID),
                );
            }
        }

        return array(
            'users' => $users,
            'total' => (int) $total
        );
    }

    /**
     * Verifica se usuário é Institution Admin
     *
     * @param int|null $user_id ID do usuário (null = usuário atual)
     * @return bool True se for institution admin
     */
    public static function is_institution_admin($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        $mem_type = get_user_meta($user_id, 'mem_type', true);
        return ($mem_type === 'institutionAdmin');
    }

    /**
     * Pega o company_id (ins_company_id) da instituição do usuário
     *
     * @param int|null $user_id ID do usuário (null = usuário atual)
     * @return string|null Company ID ou null
     */
    public static function get_user_company_id($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        return get_user_meta($user_id, 'mem_membercompanyname', true);
    }

    /**
     * Verifica se usuário tem acesso a outro usuário
     *
     * @param int $accessor_id ID do usuário que está tentando acessar
     * @param int $target_user_id ID do usuário alvo
     * @return bool True se tiver acesso
     */
    public static function can_user_access_user($accessor_id, $target_user_id) {
        // Super Admin ou Admin: acesso total
        $accessor = get_userdata($accessor_id);
        if ($accessor && (in_array('administrator', $accessor->roles) || current_user_can('manage_options'))) {
            return true;
        }

        // Não é institution admin: sem acesso
        if (!self::is_institution_admin($accessor_id)) {
            return false;
        }

        // Institution Admin: verifica se é da mesma instituição
        $accessor_company = self::get_user_company_id($accessor_id);
        $target_company = self::get_user_company_id($target_user_id);

        return ($accessor_company === $target_company && !empty($accessor_company));
    }

    /**
     * Pega estatísticas dos usuários
     *
     * @return array Array com total, active, inactive, new_this_month
     */
    public static function get_users_stats() {
        global $wpdb;

        $current_user_id = get_current_user_id();
        $is_institution_admin = self::is_institution_admin($current_user_id);

        // Filtro por company_id para Institution Admins
        $company_filter = '';
        $company_id = '';

        if ($is_institution_admin) {
            $company_id = self::get_user_company_id($current_user_id);
            if (!empty($company_id)) {
                $company_filter = $wpdb->prepare(
                    " AND user_id IN (
                        SELECT user_id FROM {$wpdb->usermeta}
                        WHERE meta_key = 'mem_membercompanyname'
                        AND meta_value = %s
                    )",
                    $company_id
                );
            }
        }

        // Total de usuários
        if ($is_institution_admin && !empty($company_id)) {
            $total_users = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT user_id)
                FROM {$wpdb->usermeta}
                WHERE meta_key = 'mem_membercompanyname'
                AND meta_value = %s",
                $company_id
            ));
        } else {
            $total = count_users();
            $total_users = $total['total_users'];
        }

        // Active members (mem_status = 'active')
        $active = $wpdb->get_var(
            "SELECT COUNT(DISTINCT user_id)
            FROM {$wpdb->usermeta}
            WHERE meta_key = 'mem_status'
            AND meta_value = 'active'
            {$company_filter}"
        );

        // Inactive members (mem_status = 'inactive')
        $inactive = $wpdb->get_var(
            "SELECT COUNT(DISTINCT user_id)
            FROM {$wpdb->usermeta}
            WHERE meta_key = 'mem_status'
            AND meta_value = 'inactive'
            {$company_filter}"
        );

        // New this month
        $start_of_month = date('Y-m-01 00:00:00');

        if ($is_institution_admin && !empty($company_id)) {
            $new_this_month = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT u.ID)
                FROM {$wpdb->users} u
                INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
                WHERE u.user_registered >= %s
                AND um.meta_key = 'mem_membercompanyname'
                AND um.meta_value = %s",
                $start_of_month,
                $company_id
            ));
        } else {
            $new_this_month = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*)
                FROM {$wpdb->users}
                WHERE user_registered >= %s",
                $start_of_month
            ));
        }

        return array(
            'total' => (int) $total_users,
            'active' => (int) $active,
            'inactive' => (int) $inactive,
            'new_this_month' => (int) $new_this_month,
        );
    }
}
