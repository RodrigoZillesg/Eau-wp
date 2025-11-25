<?php
namespace EauSystem;

/**
 * Sistema de Cache para Estatísticas de Atividades
 *
 * Gerencia cache com WordPress Transients para melhorar performance
 * das queries de estatísticas que são executadas frequentemente.
 *
 * Cache duration: 1 hora
 * Invalidação: Automática ao criar/editar/deletar atividades
 * Regeneração: WP Cron a cada hora (fallback)
 */
class Eau_Activities_Stats_Cache {

    /**
     * Prefixo para as chaves de cache
     */
    const CACHE_PREFIX = 'eau_activities_stats_';

    /**
     * Duração do cache (1 hora)
     */
    const CACHE_DURATION = HOUR_IN_SECONDS;

    /**
     * Nome do cron job
     */
    const CRON_HOOK = 'eau_regenerate_activities_stats_cache';

    /**
     * Obtém estatísticas (com cache)
     *
     * @param int|null $user_id ID do usuário (null = current user)
     * @return array Estatísticas
     */
    public static function get_stats($user_id = null) {
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }

        // Gera chave de cache específica para o usuário/contexto
        $cache_key = self::generate_cache_key($user_id);

        // Tenta buscar do cache
        $cached_stats = get_transient($cache_key);

        if ($cached_stats !== false) {
            // Cache hit: retorna dados cacheados
            return $cached_stats;
        }

        // Cache miss: calcula e armazena
        $stats = self::calculate_stats($user_id);
        set_transient($cache_key, $stats, self::CACHE_DURATION);

        return $stats;
    }

    /**
     * Invalida cache para um usuário específico ou todos
     *
     * @param int|null $user_id ID do usuário (null = invalidate all)
     */
    public static function invalidate_cache($user_id = null) {
        if ($user_id !== null) {
            // Invalida cache específico do usuário
            $cache_key = self::generate_cache_key($user_id);
            delete_transient($cache_key);
        } else {
            // Invalida todos os caches de activities stats
            self::invalidate_all();
        }
    }

    /**
     * Invalida TODOS os caches de estatísticas
     * Usado em bulk operations ou quando não sabemos quem foi afetado
     */
    public static function invalidate_all() {
        global $wpdb;

        // Deleta todos os transients que começam com nosso prefixo
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options}
                WHERE option_name LIKE %s
                OR option_name LIKE %s",
                '_transient_' . self::CACHE_PREFIX . '%',
                '_transient_timeout_' . self::CACHE_PREFIX . '%'
            )
        );
    }

    /**
     * Regenera todos os caches (usado pelo WP Cron)
     * Percorre os tipos de usuários e pré-gera os caches
     */
    public static function regenerate_all_caches() {
        // Invalida caches antigos primeiro
        self::invalidate_all();

        // Pré-gera cache para super admin (visão global)
        $super_admins = get_users(array(
            'meta_key' => 'mem_type',
            'meta_value' => 'superAdmin',
            'number' => 1,
        ));

        if (!empty($super_admins)) {
            self::get_stats($super_admins[0]->ID);
        }

        // Pré-gera cache para institution admins
        $institution_admins = get_users(array(
            'meta_key' => 'mem_type',
            'meta_value' => 'institutionAdmin',
            'number' => 20, // Limita para evitar sobrecarga
        ));

        foreach ($institution_admins as $admin) {
            self::get_stats($admin->ID);
        }
    }

    /**
     * Setup do WP Cron para regeneração automática
     */
    public static function setup_cron() {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time(), 'hourly', self::CRON_HOOK);
        }
    }

    /**
     * Remove o WP Cron (usado na desativação do plugin)
     */
    public static function clear_cron() {
        $timestamp = wp_next_scheduled(self::CRON_HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::CRON_HOOK);
        }
    }

    /**
     * Gera chave de cache única baseada no contexto do usuário
     *
     * @param int $user_id ID do usuário
     * @return string Chave de cache
     */
    private static function generate_cache_key($user_id) {
        $mem_type = get_user_meta($user_id, 'mem_type', true);

        if ($mem_type === 'superAdmin' || Eau_User_Institution_Helper::has_admin_access($user_id)) {
            // Super admin e admin vêem tudo: cache global
            return self::CACHE_PREFIX . 'global_admin';
        }

        if ($mem_type === 'institutionAdmin') {
            // Institution admin: cache por conjunto de instituições
            $company_ids = Eau_User_Institution_Helper::get_user_managed_company_ids($user_id);
            $companies_hash = md5(implode('_', $company_ids));
            return self::CACHE_PREFIX . 'institution_' . $companies_hash;
        }

        // Fallback: cache por usuário
        return self::CACHE_PREFIX . 'user_' . $user_id;
    }

    /**
     * Calcula estatísticas (query real no banco)
     * IMPORTANTE: Filtra apenas atividades COM membros associados
     *
     * @param int $user_id ID do usuário
     * @return array Estatísticas
     */
    private static function calculate_stats($user_id) {
        global $wpdb;

        $is_institution_admin = Eau_User_Institution_Helper::is_institution_admin($user_id);

        if ($is_institution_admin) {
            // Institution Admin: filtra por suas instituições
            $company_ids = Eau_User_Institution_Helper::get_user_managed_company_ids($user_id);

            if (empty($company_ids)) {
                return array(
                    'total' => 0,
                    'verified' => 0,
                    'pending' => 0,
                    'total_hours' => 0,
                );
            }

            // Busca act_user_id dos membros dessas instituições
            $user_ids = get_users(array(
                'fields' => 'ID',
                'meta_query' => array(
                    array(
                        'key' => 'mem_membercompanyname',
                        'value' => $company_ids,
                        'compare' => 'IN',
                    ),
                ),
            ));

            if (empty($user_ids)) {
                return array(
                    'total' => 0,
                    'verified' => 0,
                    'pending' => 0,
                    'total_hours' => 0,
                );
            }

            // Pega os mem_userid desses usuários
            $act_user_ids = array();
            foreach ($user_ids as $uid) {
                $mem_userid = get_user_meta($uid, 'mem_userid', true);
                if (!empty($mem_userid)) {
                    $act_user_ids[] = $mem_userid;
                }
            }

            if (empty($act_user_ids)) {
                return array(
                    'total' => 0,
                    'verified' => 0,
                    'pending' => 0,
                    'total_hours' => 0,
                );
            }

            $placeholders = implode(',', array_fill(0, count($act_user_ids), '%s'));

            // Total - FILTRO: act_user_id não vazio
            $total = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT p.ID)
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm_user ON p.ID = pm_user.post_id
                WHERE p.post_type = 'activitie'
                AND p.post_status = 'publish'
                AND pm_user.meta_key = 'act_user_id'
                AND pm_user.meta_value IN ($placeholders)
                AND pm_user.meta_value != ''
                AND pm_user.meta_value IS NOT NULL",
                ...$act_user_ids
            ));

            // Verified - FILTRO: act_user_id não vazio
            $verified = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT p.ID)
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm_user ON p.ID = pm_user.post_id
                INNER JOIN {$wpdb->postmeta} pm_verified ON p.ID = pm_verified.post_id
                WHERE p.post_type = 'activitie'
                AND p.post_status = 'publish'
                AND pm_user.meta_key = 'act_user_id'
                AND pm_user.meta_value IN ($placeholders)
                AND pm_user.meta_value != ''
                AND pm_user.meta_value IS NOT NULL
                AND pm_verified.meta_key = 'act_verified'
                AND pm_verified.meta_value = '1'",
                ...$act_user_ids
            ));

            // Pending - FILTRO: act_user_id não vazio
            $pending = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT p.ID)
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm_user ON p.ID = pm_user.post_id
                LEFT JOIN {$wpdb->postmeta} pm_verified ON p.ID = pm_verified.post_id AND pm_verified.meta_key = 'act_verified'
                WHERE p.post_type = 'activitie'
                AND p.post_status = 'publish'
                AND pm_user.meta_key = 'act_user_id'
                AND pm_user.meta_value IN ($placeholders)
                AND pm_user.meta_value != ''
                AND pm_user.meta_value IS NOT NULL
                AND (pm_verified.meta_value IS NULL OR pm_verified.meta_value != '1')",
                ...$act_user_ids
            ));

            // Total points (horas × pontos_per_hour da categoria) - FILTRO: act_user_id não vazio
            $table_categories = $wpdb->prefix . 'eau_activity_categories';
            $total_hours = $wpdb->get_var($wpdb->prepare(
                "SELECT SUM(
                    CAST(pm_hours.meta_value AS DECIMAL(10,2)) *
                    COALESCE(cat.points_per_hour, 0)
                )
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm_user ON p.ID = pm_user.post_id
                INNER JOIN {$wpdb->postmeta} pm_hours ON p.ID = pm_hours.post_id
                LEFT JOIN {$wpdb->postmeta} pm_cat ON p.ID = pm_cat.post_id AND pm_cat.meta_key = 'act_category_serial'
                LEFT JOIN {$table_categories} cat ON cat.category_serial = pm_cat.meta_value
                WHERE p.post_type = 'activitie'
                AND p.post_status = 'publish'
                AND pm_user.meta_key = 'act_user_id'
                AND pm_user.meta_value IN ($placeholders)
                AND pm_user.meta_value != ''
                AND pm_user.meta_value IS NOT NULL
                AND pm_hours.meta_key = 'act_hours_of_pd_anything_below_60_minutes_can_be_entered_as_a_decimal_e_g_30_mins_0_5'",
                ...$act_user_ids
            ));
        } else {
            // Admin/Super Admin: vê tudo (mas filtra atividades sem membros)

            // Total - FILTRO: act_user_id não vazio
            $total = $wpdb->get_var(
                "SELECT COUNT(DISTINCT p.ID)
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm_user ON p.ID = pm_user.post_id
                WHERE p.post_type = 'activitie'
                AND p.post_status = 'publish'
                AND pm_user.meta_key = 'act_user_id'
                AND pm_user.meta_value != ''
                AND pm_user.meta_value IS NOT NULL"
            );

            // Verified - FILTRO: act_user_id não vazio
            $verified = $wpdb->get_var(
                "SELECT COUNT(DISTINCT p.ID)
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm_user ON p.ID = pm_user.post_id
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_type = 'activitie'
                AND p.post_status = 'publish'
                AND pm_user.meta_key = 'act_user_id'
                AND pm_user.meta_value != ''
                AND pm_user.meta_value IS NOT NULL
                AND pm.meta_key = 'act_verified'
                AND pm.meta_value = '1'"
            );

            // Pending - FILTRO: act_user_id não vazio
            $pending = $wpdb->get_var(
                "SELECT COUNT(DISTINCT p.ID)
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm_user ON p.ID = pm_user.post_id
                LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'act_verified'
                WHERE p.post_type = 'activitie'
                AND p.post_status = 'publish'
                AND pm_user.meta_key = 'act_user_id'
                AND pm_user.meta_value != ''
                AND pm_user.meta_value IS NOT NULL
                AND (pm.meta_value IS NULL OR pm.meta_value != '1')"
            );

            // Total points (horas × pontos_per_hour da categoria) - FILTRO: act_user_id não vazio
            $table_categories = $wpdb->prefix . 'eau_activity_categories';
            $total_hours = $wpdb->get_var(
                "SELECT SUM(
                    CAST(pm_hours.meta_value AS DECIMAL(10,2)) *
                    COALESCE(cat.points_per_hour, 0)
                )
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm_user ON p.ID = pm_user.post_id
                INNER JOIN {$wpdb->postmeta} pm_hours ON p.ID = pm_hours.post_id
                    AND pm_hours.meta_key = 'act_hours_of_pd_anything_below_60_minutes_can_be_entered_as_a_decimal_e_g_30_mins_0_5'
                LEFT JOIN {$wpdb->postmeta} pm_cat ON p.ID = pm_cat.post_id
                    AND pm_cat.meta_key = 'act_category_serial'
                LEFT JOIN {$table_categories} cat ON cat.category_serial = pm_cat.meta_value
                WHERE p.post_type = 'activitie'
                AND p.post_status = 'publish'
                AND pm_user.meta_key = 'act_user_id'
                AND pm_user.meta_value != ''
                AND pm_user.meta_value IS NOT NULL"
            );
        }

        return array(
            'total' => (int) $total,
            'verified' => (int) $verified,
            'pending' => (int) $pending,
            'total_hours' => (float) $total_hours,
        );
    }
}
