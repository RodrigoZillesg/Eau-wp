<?php
namespace EauSystem;

/**
 * Helper class para relacionamento entre User e Institution
 *
 * RELACIONAMENTO CORRETO (v1.26.0):
 * ================================================================================
 * Institution Admin: Institution (ins_company_primary_contact) = User (mem_userid)
 *   - Um admin pode gerenciar MÚLTIPLAS instituições
 *   - Relacionamento via ins_company_primary_contact = mem_userid
 *
 * Member Regular: User (mem_membercompanyname) = Institution (ins_company_id)
 *   - Um membro pertence a UMA instituição
 *   - Relacionamento via mem_membercompanyname = ins_company_id
 *
 * Ver RELATIONSHIPS.md para detalhes completos.
 * ================================================================================
 */
class Eau_User_Institution_Helper {

    /**
     * Pega o mem_userid do usuário (usado para relacionamentos)
     *
     * @param int|null $user_id ID do usuário (null = usuário atual)
     * @return string|null mem_userid ou null
     */
    public static function get_user_mem_userid($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        return get_user_meta($user_id, 'mem_userid', true);
    }

    /**
     * Pega os tipos do usuário como array normalizado
     *
     * @since 1.66.2 - Suporte a múltiplos tipos
     * @param int|null $user_id ID do usuário (null = usuário atual)
     * @return array Array de tipos do usuário
     */
    public static function get_user_types($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        $mem_type = get_user_meta($user_id, 'mem_type', true);

        // Se já é array, retorna
        if (is_array($mem_type)) {
            return array_filter($mem_type);
        }

        // Se é string, converte para array
        if (!empty($mem_type)) {
            return array($mem_type);
        }

        // Sem tipo definido, assume 'member'
        return array('member');
    }

    /**
     * Verifica se o usuário tem um tipo específico
     *
     * @since 1.66.2 - Suporte a múltiplos tipos
     * @param string $type Tipo a verificar
     * @param int|null $user_id ID do usuário (null = usuário atual)
     * @return bool True se o usuário tem o tipo
     */
    public static function user_has_type($type, $user_id = null) {
        $types = self::get_user_types($user_id);
        return in_array($type, $types);
    }

    /**
     * Verifica se usuário é Institution Admin
     *
     * Um usuário é considerado institutionAdmin se:
     * 1. Novo sistema: mem_managed_institutions contém pelo menos um ID
     * 2. OU: mem_type contém 'institutionAdmin' (string ou array)
     * 3. OU: É ins_company_primary_contact de alguma instituição
     *
     * @since 1.66.0 - Novo sistema de separação de papéis
     * @since 1.66.2 - Suporte a múltiplos tipos
     * @param int|null $user_id ID do usuário (null = usuário atual)
     * @return bool True se for institution admin
     */
    public static function is_institution_admin($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        // 1. Verifica novo sistema (mem_managed_institutions)
        $managed = get_user_meta($user_id, 'mem_managed_institutions', true);
        if (!empty($managed) && is_array($managed) && count($managed) > 0) {
            return true;
        }

        // 2. Verifica se mem_type contém 'institutionAdmin'
        if (self::user_has_type('institutionAdmin', $user_id)) {
            return true;
        }

        // 3. Verifica se é Primary Contact de alguma instituição
        $mem_userid = self::get_user_mem_userid($user_id);
        if (!empty($mem_userid)) {
            global $wpdb;
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->postmeta} pm
                INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                WHERE p.post_type = 'institutions'
                AND p.post_status = 'publish'
                AND pm.meta_key = 'ins_company_primary_contact'
                AND pm.meta_value = %s",
                $mem_userid
            ));
            if ($count > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Verifica se usuário é Super Admin
     *
     * @since 1.66.2 - Suporte a múltiplos tipos
     * @param int|null $user_id ID do usuário (null = usuário atual)
     * @return bool True se for super admin
     */
    public static function is_super_admin($user_id = null) {
        return self::user_has_type('superAdmin', $user_id);
    }

    /**
     * Verifica se usuário é Admin
     *
     * @since 1.66.2 - Suporte a múltiplos tipos
     * @param int|null $user_id ID do usuário (null = usuário atual)
     * @return bool True se for admin
     */
    public static function is_admin($user_id = null) {
        return self::user_has_type('Admin', $user_id);
    }

    /**
     * Verifica se usuário tem permissões de administração (superAdmin OU Admin)
     *
     * @param int|null $user_id ID do usuário (null = usuário atual)
     * @return bool True se for superAdmin ou Admin
     */
    public static function has_admin_access($user_id = null) {
        return self::is_super_admin($user_id) || self::is_admin($user_id);
    }

    /**
     * Retorna o tipo de maior autoridade do usuário
     *
     * Hierarquia (do maior para o menor):
     * 1. superAdmin
     * 2. Admin
     * 3. institutionAdmin
     * 4. member
     * 5. non-member
     *
     * @since 1.72.5 - Priorização correta de permissões
     * @param int|null $user_id ID do usuário (null = usuário atual)
     * @return string O tipo de maior autoridade
     */
    public static function get_highest_permission_type($user_id = null) {
        // Hierarquia de permissões (ordem decrescente de autoridade)
        $hierarchy = array('superAdmin', 'Admin', 'institutionAdmin', 'member', 'non-member');

        $types = self::get_user_types($user_id);

        // Verifica também se é institution admin por outras vias
        if (self::is_institution_admin($user_id) && !in_array('institutionAdmin', $types)) {
            $types[] = 'institutionAdmin';
        }

        // Retorna o primeiro tipo encontrado na hierarquia
        foreach ($hierarchy as $type) {
            if (in_array($type, $types)) {
                return $type;
            }
        }

        // Fallback
        return 'member';
    }

    /**
     * Verifica se usuário é non-member
     *
     * @since 1.72.5
     * @param int|null $user_id ID do usuário (null = usuário atual)
     * @return bool True se for non-member
     */
    public static function is_non_member($user_id = null) {
        return self::user_has_type('non-member', $user_id);
    }

    /**
     * Verifica se usuário é member regular (não admin, não institutionAdmin)
     *
     * @since 1.72.5
     * @param int|null $user_id ID do usuário (null = usuário atual)
     * @return bool True se for apenas member
     */
    public static function is_member_only($user_id = null) {
        $highest = self::get_highest_permission_type($user_id);
        return $highest === 'member';
    }

    // =========================================================================
    // MANAGED INSTITUTIONS HELPERS (v1.66.0)
    // =========================================================================

    /**
     * Adiciona uma instituição à lista de instituições administradas pelo usuário
     *
     * Este método NÃO altera o mem_type do usuário, preservando seu papel original
     * (superAdmin, Admin, member, etc.)
     *
     * @since 1.66.0 - Novo sistema de separação de papéis
     * @param int $user_id ID do usuário WordPress
     * @param int $institution_id ID da instituição (post ID)
     * @return bool True em caso de sucesso
     */
    public static function add_managed_institution($user_id, $institution_id) {
        $user_id = absint($user_id);
        $institution_id = absint($institution_id);

        if (!$user_id || !$institution_id) {
            return false;
        }

        // Verifica se a instituição existe
        $institution = get_post($institution_id);
        if (!$institution || $institution->post_type !== 'institutions') {
            return false;
        }

        // Pega lista atual
        $managed = get_user_meta($user_id, 'mem_managed_institutions', true);
        if (!is_array($managed)) {
            $managed = array();
        }

        // Adiciona se não existir
        if (!in_array($institution_id, $managed)) {
            $managed[] = $institution_id;
            update_user_meta($user_id, 'mem_managed_institutions', array_values($managed));
        }

        return true;
    }

    /**
     * Remove uma instituição da lista de instituições administradas pelo usuário
     *
     * Este método NÃO altera o mem_type do usuário, preservando seu papel original.
     * Se o usuário tinha mem_type = 'institutionAdmin' (legado) e não administrar mais
     * nenhuma instituição, o método opcionalmente pode atualizar para 'member'.
     *
     * @since 1.66.0 - Novo sistema de separação de papéis
     * @param int $user_id ID do usuário WordPress
     * @param int $institution_id ID da instituição (post ID)
     * @param bool $downgrade_legacy Se true, altera mem_type de 'institutionAdmin' para 'member'
     *                               quando não administrar mais nenhuma instituição
     * @return bool True em caso de sucesso
     */
    public static function remove_managed_institution($user_id, $institution_id, $downgrade_legacy = false) {
        $user_id = absint($user_id);
        $institution_id = absint($institution_id);

        if (!$user_id || !$institution_id) {
            return false;
        }

        // Pega lista atual
        $managed = get_user_meta($user_id, 'mem_managed_institutions', true);
        if (!is_array($managed)) {
            $managed = array();
        }

        // Remove a instituição da lista
        $managed = array_filter($managed, function($id) use ($institution_id) {
            return absint($id) !== $institution_id;
        });

        // Atualiza o meta
        update_user_meta($user_id, 'mem_managed_institutions', array_values($managed));

        // Se downgrade_legacy está ativo e não administra mais nenhuma instituição
        if ($downgrade_legacy) {
            // Verifica se ainda administra alguma instituição (via novo sistema ou legado)
            $still_manages = self::get_user_managed_institutions($user_id);
            if (empty($still_manages)) {
                // Se o mem_type era 'institutionAdmin' (legado), muda para 'member'
                $mem_type = get_user_meta($user_id, 'mem_type', true);
                if ($mem_type === 'institutionAdmin') {
                    update_user_meta($user_id, 'mem_type', 'member');
                }
            }
        }

        return true;
    }

    /**
     * Verifica se um usuário administra uma instituição específica
     *
     * @since 1.66.0
     * @param int $user_id ID do usuário WordPress
     * @param int $institution_id ID da instituição (post ID)
     * @return bool True se o usuário administra essa instituição
     */
    public static function user_manages_institution($user_id, $institution_id) {
        $user_id = absint($user_id);
        $institution_id = absint($institution_id);

        if (!$user_id || !$institution_id) {
            return false;
        }

        $managed = self::get_user_managed_institutions($user_id);
        foreach ($managed as $inst) {
            if ($inst->ID === $institution_id) {
                return true;
            }
        }

        return false;
    }

    /**
     * Obtém todos os user IDs que administram uma instituição específica
     *
     * Combina:
     * 1. Usuários com a instituição em mem_managed_institutions (novo sistema)
     * 2. Usuários com mem_type = institutionAdmin e mem_membercompanyname = company_id (legado)
     * 3. Usuário que é ins_company_primary_contact da instituição
     *
     * @since 1.66.0
     * @param int $institution_id ID da instituição (post ID)
     * @return array Array de user IDs (inteiros)
     */
    public static function get_institution_admin_user_ids($institution_id) {
        $institution_id = absint($institution_id);
        if (!$institution_id) {
            return array();
        }

        global $wpdb;
        $user_ids = array();

        // 1. Busca no novo sistema (mem_managed_institutions contém o institution_id)
        // Nota: mem_managed_institutions é um array serializado
        $all_users_with_managed = $wpdb->get_results(
            "SELECT user_id, meta_value FROM {$wpdb->usermeta}
            WHERE meta_key = 'mem_managed_institutions'
            AND meta_value != '' AND meta_value IS NOT NULL"
        );

        foreach ($all_users_with_managed as $row) {
            $managed = maybe_unserialize($row->meta_value);
            if (is_array($managed) && in_array($institution_id, array_map('absint', $managed))) {
                $user_ids[] = absint($row->user_id);
            }
        }

        // 2. Busca usuários com mem_type = institutionAdmin E mem_membercompanyname = company_id (legado)
        $company_id = get_post_meta($institution_id, 'ins_company_id', true);
        if (!empty($company_id)) {
            $legacy_admins = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT u1.user_id
                FROM {$wpdb->usermeta} u1
                INNER JOIN {$wpdb->usermeta} u2 ON u1.user_id = u2.user_id
                WHERE u1.meta_key = 'mem_type' AND u1.meta_value = 'institutionAdmin'
                AND u2.meta_key = 'mem_membercompanyname' AND u2.meta_value = %s",
                $company_id
            ));
            $user_ids = array_merge($user_ids, array_map('absint', $legacy_admins));
        }

        // 3. Busca o primary contact da instituição
        $primary_contact_mem_userid = get_post_meta($institution_id, 'ins_company_primary_contact', true);
        if (!empty($primary_contact_mem_userid)) {
            // Converte mem_userid para user_id
            $primary_user_id = $wpdb->get_var($wpdb->prepare(
                "SELECT user_id FROM {$wpdb->usermeta}
                WHERE meta_key = 'mem_userid' AND meta_value = %s
                LIMIT 1",
                $primary_contact_mem_userid
            ));
            if ($primary_user_id) {
                $user_ids[] = absint($primary_user_id);
            }
        }

        // Remove duplicatas e zeros
        $user_ids = array_unique(array_filter($user_ids));

        return array_values($user_ids);
    }

    // =========================================================================
    // MEMBERSHIP STATUS HELPERS (v1.51.46)
    // =========================================================================

    /**
     * Verifica se o membership do usuário está ativo
     *
     * Retorna true APENAS se mem_membership_status === 'active'
     * Admins (superAdmin, Admin) e institutionAdmin sempre têm acesso, independente do status
     *
     * @since 1.51.46
     * @param int|null $user_id ID do usuário (null = usuário atual)
     * @return bool True se membership ativo ou se for admin/institutionAdmin
     */
    public static function is_membership_active($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        // Admins e institutionAdmin sempre têm acesso
        if (self::has_admin_access($user_id) || self::is_institution_admin($user_id)) {
            return true;
        }

        $membership_status = get_user_meta($user_id, 'mem_membership_status', true);

        // v1.67.2 - Considera ativo se:
        // 1. Status é explicitamente 'active'
        // 2. OU usuário está vinculado a uma instituição E status NÃO é explicitamente inativo
        if ($membership_status === 'active') {
            return true;
        }

        // Se vinculado a uma instituição, considera ativo exceto se explicitamente inativo
        $user_institution = self::get_user_institution($user_id);
        if ($user_institution) {
            $inactive_statuses = array('cancelled', 'expired', 'suspended');
            return !in_array($membership_status, $inactive_statuses);
        }

        return false;
    }

    /**
     * Verifica se o membership está cancelado, expirado ou suspenso
     *
     * @since 1.51.46
     * @param int|null $user_id ID do usuário (null = usuário atual)
     * @return bool True se membership cancelado, expirado ou suspenso
     */
    public static function is_membership_inactive($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        // Admins e institutionAdmin nunca são considerados inativos
        if (self::has_admin_access($user_id) || self::is_institution_admin($user_id)) {
            return false;
        }

        $membership_status = get_user_meta($user_id, 'mem_membership_status', true);
        $inactive_statuses = array('cancelled', 'expired', 'suspended');

        return in_array($membership_status, $inactive_statuses, true);
    }

    /**
     * Obtém o status de membership do usuário
     *
     * @since 1.51.46
     * @param int|null $user_id ID do usuário (null = usuário atual)
     * @return string Status: active, pending, expired, suspended, cancelled, ou vazio
     */
    public static function get_membership_status($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        return get_user_meta($user_id, 'mem_membership_status', true);
    }

    /**
     * Obtém o label amigável do status de membership
     *
     * @since 1.51.46
     * @param int|null $user_id ID do usuário (null = usuário atual)
     * @return array Array com 'label' e 'class' para exibição
     */
    public static function get_membership_status_display($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        $status = self::get_membership_status($user_id);

        $displays = array(
            'active' => array(
                'label' => 'Active',
                'class' => 'eau-badge-success',
            ),
            'pending' => array(
                'label' => 'Pending Approval',
                'class' => 'eau-badge-warning',
            ),
            'expired' => array(
                'label' => 'Expired',
                'class' => 'eau-badge-danger',
            ),
            'suspended' => array(
                'label' => 'Suspended',
                'class' => 'eau-badge-danger',
            ),
            'cancelled' => array(
                'label' => 'Cancelled',
                'class' => 'eau-badge-danger',
            ),
        );

        if (isset($displays[$status])) {
            return $displays[$status];
        }

        // Default para status vazio ou desconhecido
        return array(
            'label' => 'No Membership',
            'class' => 'eau-badge-secondary',
        );
    }

    /**
     * Verifica se o usuário pode acessar funcionalidades de membro
     *
     * Combina verificação de login + membership ativo
     *
     * @since 1.51.46
     * @param int|null $user_id ID do usuário (null = usuário atual)
     * @return bool True se pode acessar
     */
    public static function can_access_member_features($user_id = null) {
        if (!is_user_logged_in()) {
            return false;
        }

        return self::is_membership_active($user_id);
    }

    /**
     * Pega TODAS as instituições que o usuário administra
     *
     * Combina:
     * 1. Novo sistema: mem_managed_institutions (array de IDs de instituição)
     * 2. Legado: ins_company_primary_contact = mem_userid
     *
     * Um admin pode gerenciar MÚLTIPLAS instituições!
     *
     * @since 1.66.0 - Novo sistema de separação de papéis
     * @param int|null $user_id ID do usuário (null = usuário atual)
     * @return array Array de WP_Post objects (instituições)
     */
    public static function get_user_managed_institutions($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        $institution_ids = array();

        // 1. Do novo sistema (mem_managed_institutions)
        $managed = get_user_meta($user_id, 'mem_managed_institutions', true);
        if (is_array($managed) && !empty($managed)) {
            $institution_ids = array_merge($institution_ids, $managed);
        }

        // 2. Instituições onde é Primary Contact (legado)
        $mem_userid = self::get_user_mem_userid($user_id);
        if (!empty($mem_userid)) {
            global $wpdb;
            $as_primary = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT p.ID
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_type = 'institutions'
                AND p.post_status = 'publish'
                AND pm.meta_key = 'ins_company_primary_contact'
                AND pm.meta_value = %s",
                $mem_userid
            ));
            if (!empty($as_primary)) {
                $institution_ids = array_merge($institution_ids, $as_primary);
            }
        }

        // Remove duplicatas e converte para inteiros
        $institution_ids = array_unique(array_map('absint', $institution_ids));
        $institution_ids = array_filter($institution_ids); // Remove zeros

        if (empty($institution_ids)) {
            return array();
        }

        // Retorna os posts das instituições
        $institutions = get_posts(array(
            'post_type' => 'institutions',
            'posts_per_page' => -1,
            'post__in' => $institution_ids,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC',
        ));

        return $institutions;
    }

    /**
     * Pega os company_ids de TODAS as instituições que o admin gerencia
     *
     * @param int|null $user_id ID do usuário (null = usuário atual)
     * @return array Array de company_ids (ins_company_id)
     */
    public static function get_user_managed_company_ids($user_id = null) {
        $institutions = self::get_user_managed_institutions($user_id);

        if (empty($institutions)) {
            return array();
        }

        $company_ids = array();
        foreach ($institutions as $institution) {
            $company_id = get_post_meta($institution->ID, 'ins_company_id', true);
            if (!empty($company_id)) {
                $company_ids[] = $company_id;
            }
        }

        return array_unique($company_ids);
    }

    /**
     * Pega os nomes de TODAS as instituições que o admin gerencia
     *
     * @param int|null $user_id ID do usuário (null = usuário atual)
     * @return array Array de nomes (post_title)
     */
    public static function get_user_managed_institution_names($user_id = null) {
        $institutions = self::get_user_managed_institutions($user_id);

        if (empty($institutions)) {
            return array();
        }

        $names = array();
        foreach ($institutions as $institution) {
            $names[] = $institution->post_title;
        }

        return $names;
    }

    /**
     * Pega a instituição relacionada ao usuário (PARA MEMBROS REGULARES)
     *
     * DEPRECATED para institutionAdmin (use get_user_managed_institutions)
     * Usado apenas para membros regulares
     *
     * @param int $user_id ID do usuário
     * @return \WP_Post|null Post da instituição ou null se não encontrar
     */
    public static function get_user_institution($user_id) {
        // Pega o company ID do usuário (membro regular)
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
     * Pega o tipo de membership do usuário
     *
     * Primeiro busca no user meta (mem_membership_type) para o novo sistema.
     * Se não encontrar, faz fallback para ins_type da instituição (legado).
     *
     * @since 1.49.6 - Adicionado suporte a mem_membership_type
     * @param int $user_id ID do usuário
     * @return string|null Tipo de membership ou null
     */
    public static function get_user_membership_type($user_id) {
        // Primeiro, busca no user meta (novo sistema)
        $mem_membership_type = get_user_meta($user_id, 'mem_membership_type', true);
        if (!empty($mem_membership_type)) {
            // Retorna o label formatado
            $type_data = \EauSystem\Eau_Membership_Types::get_by_key($mem_membership_type);
            return $type_data ? $type_data->type_label : ucwords(str_replace('_', ' ', $mem_membership_type));
        }

        // Fallback: busca ins_type da instituição (sistema legado)
        $institution = self::get_user_institution($user_id);

        if (!$institution) {
            return null;
        }

        $ins_type = get_post_meta($institution->ID, 'ins_type', true);

        if (!empty($ins_type)) {
            // Tenta buscar label no novo sistema
            $type_data = \EauSystem\Eau_Membership_Types::get_by_key($ins_type);
            return $type_data ? $type_data->type_label : ucwords(str_replace('_', ' ', $ins_type));
        }

        return null;
    }

    /**
     * Pega o nome da instituição do usuário (PARA MEMBROS REGULARES)
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
     * Pega o status do usuário (do metadado mem_membership_status)
     *
     * @param int $user_id ID do usuário
     * @return string Status do usuário (active, pending, expired, suspended, cancelled)
     */
    public static function get_user_status($user_id) {
        // Primeiro tenta mem_membership_status (novo campo)
        $status = get_user_meta($user_id, 'mem_membership_status', true);

        // Fallback para mem_status se mem_membership_status estiver vazio
        if (empty($status)) {
            $status = get_user_meta($user_id, 'mem_status', true);
        }

        return !empty($status) ? $status : 'unknown';
    }

    /**
     * Pega todos os usuários com suas instituições (para listagem)
     *
     * CORRETO: Filtra por TODAS as instituições que o admin gerencia
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
            'tag' => '',
            'position' => '',
            'registered_date_from' => '',
            'registered_date_to' => '',
            'email_migration' => '',
            'lifetime_member' => '',
            'full_individual_member' => '',
            'orderby' => 'display_name',
            'order' => 'ASC',
        );

        $args = wp_parse_args($args, $defaults);

        // Filtro automático para Institution Admin
        $current_user_id = get_current_user_id();
        $is_institution_admin = self::is_institution_admin($current_user_id);

        if ($is_institution_admin && empty($args['institution_id'])) {
            // Pega TODOS os company_ids das instituições que o admin gerencia
            $company_ids = self::get_user_managed_company_ids($current_user_id);

            if (empty($company_ids)) {
                // Sem instituições: retorna vazio
                return array(
                    'users' => array(),
                    'total' => 0
                );
            }

            // Adiciona filtro IN para múltiplos company_ids
            $args['_internal_company_ids'] = $company_ids;
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

        // Filtro por status (mem_membership_status) - v1.51.52
        if (!empty($args['status'])) {
            $join[] = "INNER JOIN {$wpdb->usermeta} um_status ON u.ID = um_status.user_id AND um_status.meta_key = 'mem_membership_status'";
            $where[] = $wpdb->prepare("um_status.meta_value = %s", $args['status']);
        }

        // Filtro por user type (mem_type do JetEngine)
        if (!empty($args['role'])) {
            $join[] = "INNER JOIN {$wpdb->usermeta} um_type ON u.ID = um_type.user_id AND um_type.meta_key = 'mem_type'";
            $where[] = $wpdb->prepare("um_type.meta_value = %s", $args['role']);
        }

        // Filtro por instituição (CORRETO: usa company_ids)
        if (!empty($args['institution_id'])) {
            // Filtro explícito por uma instituição específica
            $company_id = get_post_meta($args['institution_id'], 'ins_company_id', true);
            if (!empty($company_id)) {
                $join[] = "INNER JOIN {$wpdb->usermeta} um_company ON u.ID = um_company.user_id AND um_company.meta_key = 'mem_membercompanyname'";
                $where[] = $wpdb->prepare("um_company.meta_value = %s", $company_id);
            }
        } elseif (!empty($args['_internal_company_ids'])) {
            // Filtro automático para Institution Admin (múltiplas instituições)
            $company_ids = $args['_internal_company_ids'];
            $placeholders = implode(',', array_fill(0, count($company_ids), '%s'));
            $join[] = "INNER JOIN {$wpdb->usermeta} um_company ON u.ID = um_company.user_id AND um_company.meta_key = 'mem_membercompanyname'";
            $where[] = $wpdb->prepare("um_company.meta_value IN ($placeholders)", ...$company_ids);
        }

        // Filtro por membership type (novo sistema: mem_membership_type OU legado: ins_type)
        if (!empty($args['membership_type'])) {
            if ($args['membership_type'] === 'none') {
                // Usuários SEM membership - não tem mem_membership_type E não tem ins_type via instituição
                $join[] = "LEFT JOIN {$wpdb->usermeta} um_mtype ON u.ID = um_mtype.user_id AND um_mtype.meta_key = 'mem_membership_type'";
                $join[] = "LEFT JOIN {$wpdb->usermeta} um_mtype_comp ON u.ID = um_mtype_comp.user_id AND um_mtype_comp.meta_key = 'mem_membercompanyname'";
                $join[] = "LEFT JOIN {$wpdb->postmeta} pm_comp_id ON pm_comp_id.meta_key = 'ins_company_id' AND pm_comp_id.meta_value = um_mtype_comp.meta_value";
                $join[] = "LEFT JOIN {$wpdb->postmeta} pm_ins_type ON pm_ins_type.post_id = pm_comp_id.post_id AND pm_ins_type.meta_key = 'ins_type'";
                $where[] = "(um_mtype.meta_value IS NULL OR um_mtype.meta_value = '') AND (pm_ins_type.meta_value IS NULL OR pm_ins_type.meta_value = '')";
            } else {
                // Busca por tipo específico - primeiro no user meta (novo sistema), depois no ins_type (legado)
                $join[] = "LEFT JOIN {$wpdb->usermeta} um_mtype ON u.ID = um_mtype.user_id AND um_mtype.meta_key = 'mem_membership_type'";
                $join[] = "LEFT JOIN {$wpdb->usermeta} um_mtype_comp ON u.ID = um_mtype_comp.user_id AND um_mtype_comp.meta_key = 'mem_membercompanyname'";
                $join[] = "LEFT JOIN {$wpdb->postmeta} pm_comp_id ON pm_comp_id.meta_key = 'ins_company_id' AND pm_comp_id.meta_value = um_mtype_comp.meta_value";
                $join[] = "LEFT JOIN {$wpdb->postmeta} pm_ins_type ON pm_ins_type.post_id = pm_comp_id.post_id AND pm_ins_type.meta_key = 'ins_type'";
                $where[] = $wpdb->prepare("(um_mtype.meta_value = %s OR pm_ins_type.meta_value = %s)", $args['membership_type'], $args['membership_type']);
            }
        }

        // Filtro por data de registro (from)
        if (!empty($args['registered_date_from'])) {
            $where[] = $wpdb->prepare("u.user_registered >= %s", $args['registered_date_from'] . ' 00:00:00');
        }

        // Filtro por data de registro (to)
        if (!empty($args['registered_date_to'])) {
            $where[] = $wpdb->prepare("u.user_registered <= %s", $args['registered_date_to'] . ' 23:59:59');
        }

        // Filtro por tag (mem_tags é um array serializado)
        if (!empty($args['tag'])) {
            // mem_tags é armazenado como array serializado, então usamos LIKE para buscar o slug
            $join[] = "INNER JOIN {$wpdb->usermeta} um_tags ON u.ID = um_tags.user_id AND um_tags.meta_key = 'mem_tags'";
            // O valor é serializado como a:N:{i:0;s:X:"slug";...}, então buscamos pela string do slug
            $where[] = $wpdb->prepare("um_tags.meta_value LIKE %s", '%"' . $wpdb->esc_like($args['tag']) . '"%');
        }

        // Filtro por position (mem_memberposition)
        if (!empty($args['position'])) {
            $join[] = "INNER JOIN {$wpdb->usermeta} um_position ON u.ID = um_position.user_id AND um_position.meta_key = 'mem_memberposition'";
            $where[] = $wpdb->prepare("um_position.meta_value = %s", $args['position']);
        }

        // Filtro por email migration status (v1.62.0)
        if (!empty($args['email_migration'])) {
            if ($args['email_migration'] === 'not_set') {
                // Users without email migration status set
                $join[] = "LEFT JOIN {$wpdb->usermeta} um_email_mig ON u.ID = um_email_mig.user_id AND um_email_mig.meta_key = 'mem_email_migration_status'";
                $where[] = "(um_email_mig.meta_value IS NULL OR um_email_mig.meta_value = '')";
            } else {
                // Specific status
                $join[] = "INNER JOIN {$wpdb->usermeta} um_email_mig ON u.ID = um_email_mig.user_id AND um_email_mig.meta_key = 'mem_email_migration_status'";
                $where[] = $wpdb->prepare("um_email_mig.meta_value = %s", $args['email_migration']);
            }
        }

        // Filtro por Lifetime Member (v1.72.3)
        if (!empty($args['lifetime_member'])) {
            if ($args['lifetime_member'] === 'yes') {
                $join[] = "INNER JOIN {$wpdb->usermeta} um_lifetime ON u.ID = um_lifetime.user_id AND um_lifetime.meta_key = 'mem_lifetime_member'";
                $where[] = "um_lifetime.meta_value = '1'";
            } elseif ($args['lifetime_member'] === 'no') {
                $join[] = "LEFT JOIN {$wpdb->usermeta} um_lifetime ON u.ID = um_lifetime.user_id AND um_lifetime.meta_key = 'mem_lifetime_member'";
                $where[] = "(um_lifetime.meta_value IS NULL OR um_lifetime.meta_value = '' OR um_lifetime.meta_value = '0')";
            }
        }

        // Filtro por Full Individual Member (v1.72.3)
        if (!empty($args['full_individual_member'])) {
            if ($args['full_individual_member'] === 'yes') {
                $join[] = "INNER JOIN {$wpdb->usermeta} um_fim ON u.ID = um_fim.user_id AND um_fim.meta_key = 'mem_full_individual_member'";
                $where[] = "um_fim.meta_value = '1'";
            } elseif ($args['full_individual_member'] === 'no') {
                $join[] = "LEFT JOIN {$wpdb->usermeta} um_fim ON u.ID = um_fim.user_id AND um_fim.meta_key = 'mem_full_individual_member'";
                $where[] = "(um_fim.meta_value IS NULL OR um_fim.meta_value = '' OR um_fim.meta_value = '0')";
            }
        }

        // Monta query de contagem
        $join_sql = !empty($join) ? implode(' ', array_unique($join)) : '';
        $where_sql = implode(' AND ', $where);

        $count_query = "SELECT COUNT(DISTINCT u.ID)
                       FROM {$wpdb->users} u
                       {$join_sql}
                       WHERE {$where_sql}";

        $total = $wpdb->get_var($count_query);

        // Campos que exigem ordenação manual (meta fields ou nomes compostos)
        $manual_sort_fields = array('first_name', 'last_name', 'mem_type', 'institution_name', 'membership_type', 'status');
        $needs_manual_sort = in_array($args['orderby'], $manual_sort_fields);

        // Monta query de dados
        if ($needs_manual_sort) {
            // Busca TODOS os registros sem limit/offset para ordenar manualmente
            $data_query = "SELECT DISTINCT u.ID, u.user_login, u.user_email, u.display_name, u.user_registered
                          FROM {$wpdb->users} u
                          {$join_sql}
                          WHERE {$where_sql}";

            $user_ids = $wpdb->get_results($data_query);
        } else {
            // Ordenação por campos nativos pode usar SQL
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
        }

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

        // Se precisa ordenação manual, ordena e pagina manualmente
        if ($needs_manual_sort) {
            // Ordena o array baseado no campo solicitado
            usort($users, function($a, $b) use ($args) {
                $field = $args['orderby'];
                $val_a = isset($a[$field]) ? $a[$field] : '';
                $val_b = isset($b[$field]) ? $b[$field] : '';

                // Ordenação alfabética case-insensitive
                $comparison = strcasecmp($val_a, $val_b);

                return $args['order'] === 'DESC' ? -$comparison : $comparison;
            });

            // Paginação manual
            $users = array_slice($users, $args['offset'], $args['number']);
        }

        return array(
            'users' => $users,
            'total' => (int) $total
        );
    }

    /**
     * Verifica se usuário tem acesso a outro usuário
     *
     * CORRETO: Institution Admin tem acesso a membros de TODAS suas instituições
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

        // Institution Admin: verifica se target está em alguma das suas instituições
        $accessor_company_ids = self::get_user_managed_company_ids($accessor_id);
        $target_company = get_user_meta($target_user_id, 'mem_membercompanyname', true);

        return in_array($target_company, $accessor_company_ids) && !empty($target_company);
    }

    /**
     * Pega estatísticas dos usuários
     *
     * CORRETO: Filtra por TODAS as instituições que o admin gerencia
     *
     * @since 1.72.6 - Prioriza has_admin_access() sobre is_institution_admin()
     * @return array Array com total, active, inactive, new_this_month
     */
    public static function get_users_stats() {
        global $wpdb;

        $current_user_id = get_current_user_id();

        // v1.72.6: Admins veem tudo, não filtra
        $is_admin = self::has_admin_access($current_user_id);
        $is_institution_admin = !$is_admin && self::is_institution_admin($current_user_id);

        // Filtro por company_ids para Institution Admins (NÃO para admins)
        $company_filter = '';
        $company_ids = array();

        if ($is_institution_admin) {
            $company_ids = self::get_user_managed_company_ids($current_user_id);
            if (!empty($company_ids)) {
                $placeholders = implode(',', array_fill(0, count($company_ids), '%s'));
                $company_filter = $wpdb->prepare(
                    " AND user_id IN (
                        SELECT user_id FROM {$wpdb->usermeta}
                        WHERE meta_key = 'mem_membercompanyname'
                        AND meta_value IN ($placeholders)
                    )",
                    ...$company_ids
                );
            }
        }

        // Total de usuários
        if ($is_institution_admin && !empty($company_ids)) {
            $placeholders = implode(',', array_fill(0, count($company_ids), '%s'));
            $total_users = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT user_id)
                FROM {$wpdb->usermeta}
                WHERE meta_key = 'mem_membercompanyname'
                AND meta_value IN ($placeholders)",
                ...$company_ids
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

        if ($is_institution_admin && !empty($company_ids)) {
            $placeholders = implode(',', array_fill(0, count($company_ids), '%s'));
            $params = array_merge(array($start_of_month), $company_ids);
            $new_this_month = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT u.ID)
                FROM {$wpdb->users} u
                INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
                WHERE u.user_registered >= %s
                AND um.meta_key = 'mem_membercompanyname'
                AND um.meta_value IN ($placeholders)",
                ...$params
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
