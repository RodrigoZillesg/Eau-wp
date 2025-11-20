<?php
namespace EauSystem;

/**
 * Classe para sincronizar user types baseado em posts de membership
 */
class Eau_User_Type_Sync {

    /**
     * Sincroniza mem_type de todos os usuários
     */
    public function sync_all_user_types() {
        // Aumenta limite de memória temporariamente
        @ini_set('memory_limit', '512M');
        set_time_limit(300); // 5 minutos

        // Busca todos os posts do tipo membership
        $memberships = get_posts(array(
            'post_type' => 'membership',
            'posts_per_page' => -1,
            'post_status' => 'any'
        ));

        if (empty($memberships)) {
            return new \WP_Error('no_memberships', 'Nenhum post de membership encontrado.');
        }

        // Array para armazenar emails de administradores de instituição
        $institution_admin_emails = array();

        // Percorre cada membership e coleta os emails dos admins
        foreach ($memberships as $membership) {
            $primary_email = get_post_meta($membership->ID, 'primary_contacts_email', true);

            if (!empty($primary_email) && is_email($primary_email)) {
                $institution_admin_emails[] = strtolower(trim($primary_email));
            }
        }

        // Remove duplicatas
        $institution_admin_emails = array_unique($institution_admin_emails);

        // Busca TODOS os usuários (apenas ID e email para economizar memória)
        $all_users = get_users(array(
            'fields' => array('ID', 'user_email'),
            'number' => -1
        ));

        $stats = array(
            'total_users' => count($all_users),
            'institution_admins' => 0,
            'members' => 0,
            'errors' => array()
        );

        // Percorre todos os usuários e define mem_type
        foreach ($all_users as $user) {
            $user_email = strtolower(trim($user->user_email));

            // Verifica se é admin de instituição
            if (in_array($user_email, $institution_admin_emails)) {
                update_user_meta($user->ID, 'mem_type', 'institutionAdmin');
                $stats['institution_admins']++;
            } else {
                // Todos os outros são Members
                update_user_meta($user->ID, 'mem_type', 'Member');
                $stats['members']++;
            }

            // Libera memória a cada 100 usuários
            if ($stats['institution_admins'] + $stats['members'] % 100 === 0) {
                wp_cache_flush();
            }
        }

        return array(
            'success' => true,
            'message' => 'Sincronização concluída com sucesso!',
            'stats' => $stats,
            'admin_emails_found' => count($institution_admin_emails),
            'memberships_processed' => count($memberships)
        );
    }

    /**
     * Sincroniza um único usuário baseado em seu email
     */
    public function sync_single_user($user_id) {
        $user = get_user_by('ID', $user_id);

        if (!$user) {
            return new \WP_Error('user_not_found', 'Usuário não encontrado.');
        }

        // Verifica se o email do usuário está em algum membership
        $user_email = strtolower(trim($user->user_email));

        $memberships = get_posts(array(
            'post_type' => 'membership',
            'posts_per_page' => -1,
            'post_status' => 'any',
            'meta_query' => array(
                array(
                    'key' => 'primary_contacts_email',
                    'value' => $user_email,
                    'compare' => '='
                )
            )
        ));

        if (!empty($memberships)) {
            update_user_meta($user_id, 'mem_type', 'institutionAdmin');
            return array(
                'success' => true,
                'mem_type' => 'institutionAdmin'
            );
        } else {
            update_user_meta($user_id, 'mem_type', 'Member');
            return array(
                'success' => true,
                'mem_type' => 'Member'
            );
        }
    }

    /**
     * Retorna estatísticas atuais de mem_type
     */
    public static function get_current_stats() {
        global $wpdb;

        $stats = array(
            'superAdmin' => 0,
            'Admin' => 0,
            'institutionAdmin' => 0,
            'Member' => 0,
            'undefined' => 0,
            'total' => 0
        );

        // Total de usuários
        $stats['total'] = intval($wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->users}"));

        // Conta todos os tipos em uma query
        $results = $wpdb->get_results(
            "SELECT meta_value, COUNT(DISTINCT user_id) as count
            FROM {$wpdb->usermeta}
            WHERE meta_key = 'mem_type'
            GROUP BY meta_value",
            ARRAY_A
        );

        $total_with_type = 0;
        if ($results) {
            foreach ($results as $row) {
                $type = $row['meta_value'];
                $count = intval($row['count']);
                $total_with_type += $count;

                if (isset($stats[$type])) {
                    $stats[$type] = $count;
                }
            }
        }

        // Usuários sem mem_type = total - usuários com mem_type
        $stats['undefined'] = $stats['total'] - $total_with_type;

        return $stats;
    }
}
