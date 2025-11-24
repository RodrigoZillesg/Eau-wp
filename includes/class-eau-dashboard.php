<?php
namespace EauSystem;

use EauSystem\Components\Eau_Access_Denied;

/**
 * Classe para gerenciar dashboards customizados
 */
class Eau_Dashboard {

    /**
     * Registra o shortcode
     */
    public static function register_shortcode() {
        add_shortcode('eau_admin_dashboard', array(__CLASS__, 'render_admin_dashboard'));
    }

    /**
     * Renderiza o dashboard do Super Admin
     */
    public static function render_admin_dashboard($atts) {
        // Verifica se usuário está logado
        if (!is_user_logged_in()) {
            return Eau_Access_Denied::not_logged_in();
        }

        // Por enquanto, qualquer usuário logado pode ver
        // TODO: Adicionar verificação de mem_type depois

        // Coleta estatísticas
        $stats = self::get_dashboard_stats();

        // Pega o nome do usuário logado
        $current_user = wp_get_current_user();
        $display_name = $current_user->display_name;

        // Identifica tipo de usuário
        $user_role_info = self::get_user_role_info($current_user->ID);

        // Renderiza HTML
        ob_start();
        ?>
        <div class="eau-dashboard-container">

            <!-- Welcome Section -->
            <div class="eau-welcome-section">
                <h1 class="eau-welcome-title">Welcome, <?php echo esc_html($display_name); ?></h1>
                <p class="eau-welcome-description">
                    <?php echo esc_html($user_role_info['description']); ?>
                    <?php if (!empty($user_role_info['institutions'])): ?>
                        <?php foreach ($user_role_info['institutions'] as $institution_name): ?>
                            <span class="eau-institution-badge"><?php echo esc_html($institution_name); ?></span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </p>
            </div>

            <div class="eau-dashboard-cards">

                <!-- Total Members -->
                <a href="/members" class="eau-dashboard-card-link">
                    <div class="eau-dashboard-card eau-card-blue">
                        <div class="eau-card-content">
                            <h3 class="eau-card-title">Total Members</h3>
                            <div class="eau-card-stats">
                                <span class="eau-card-number"><?php echo number_format($stats['total_members']); ?></span>
                                <span class="eau-card-active"><?php echo number_format($stats['active_members']); ?> Active</span>
                            </div>
                        </div>
                        <div class="eau-card-icon">
                            <i data-lucide="users"></i>
                        </div>
                    </div>
                </a>

                <!-- Total Institutions (apenas para superAdmin e Admin) -->
                <?php if (Eau_User_Institution_Helper::has_admin_access()): ?>
                <a href="/dashboard/manage-institutions/" class="eau-dashboard-card-link">
                    <div class="eau-dashboard-card eau-card-indigo">
                        <div class="eau-card-content">
                            <h3 class="eau-card-title">Total Institutions</h3>
                            <div class="eau-card-stats">
                                <span class="eau-card-number"><?php echo number_format($stats['total_institutions']); ?></span>
                            </div>
                        </div>
                        <div class="eau-card-icon">
                            <i data-lucide="building-2"></i>
                        </div>
                    </div>
                </a>
                <?php endif; ?>

                <!-- CPD Activities -->
                <a href="/dashboard/manage-activities/" class="eau-dashboard-card-link">
                    <div class="eau-dashboard-card eau-card-green">
                        <div class="eau-card-content">
                            <h3 class="eau-card-title">CPD Activities</h3>
                            <div class="eau-card-stats">
                                <span class="eau-card-number"><?php echo number_format($stats['cpd_activities']); ?></span>
                                <span class="eau-card-pending"><?php echo number_format($stats['pending_approval']); ?> Pending Approval</span>
                            </div>
                        </div>
                        <div class="eau-card-icon">
                            <i data-lucide="book-open"></i>
                        </div>
                    </div>
                </a>

                <!-- Active Events -->
                <div class="eau-dashboard-card eau-card-purple">
                    <div class="eau-card-content">
                        <h3 class="eau-card-title">Active Events</h3>
                        <div class="eau-card-stats">
                            <span class="eau-card-number"><?php echo number_format($stats['active_events']); ?></span>
                        </div>
                    </div>
                    <div class="eau-card-icon">
                        <i data-lucide="calendar"></i>
                    </div>
                </div>

                <!-- Points Awarded -->
                <div class="eau-dashboard-card eau-card-orange">
                    <div class="eau-card-content">
                        <h3 class="eau-card-title">Points Awarded</h3>
                        <div class="eau-card-stats">
                            <span class="eau-card-number"><?php echo number_format($stats['points_awarded'], 1); ?></span>
                        </div>
                    </div>
                    <div class="eau-card-icon">
                        <i data-lucide="award"></i>
                    </div>
                </div>

                <!-- Pending Payments -->
                <div class="eau-dashboard-card eau-card-red">
                    <div class="eau-card-content">
                        <h3 class="eau-card-title">Pending Payments</h3>
                        <div class="eau-card-stats">
                            <span class="eau-card-number"><?php echo number_format($stats['pending_payments']); ?></span>
                        </div>
                    </div>
                    <div class="eau-card-icon">
                        <i data-lucide="credit-card"></i>
                    </div>
                </div>

            </div>
        </div>

        <script>
            // Re-inicializa ícones Lucide quando o shortcode é renderizado
            (function() {
                function initLucideIcons() {
                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                        console.log('EAU Dashboard: Lucide icons initialized');
                    } else {
                        console.warn('EAU Dashboard: Lucide not loaded yet, retrying...');
                        setTimeout(initLucideIcons, 100);
                    }
                }

                // Tenta inicializar imediatamente
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', initLucideIcons);
                } else {
                    initLucideIcons();
                }
            })();
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * Coleta todas as estatísticas do dashboard
     */
    private static function get_dashboard_stats() {
        // Usa o método filtrado do helper que já respeita institutionAdmin
        $user_stats = Eau_User_Institution_Helper::get_users_stats();

        return array(
            'total_members' => $user_stats['total'],
            'active_members' => $user_stats['active'],
            'total_institutions' => self::get_total_institutions(),
            'cpd_activities' => self::get_cpd_activities(),
            'pending_approval' => self::get_pending_approval(),
            'active_events' => self::get_active_events(),
            'points_awarded' => self::get_points_awarded(),
            'pending_payments' => self::get_pending_payments(),
        );
    }

    /**
     * Total de Institutions
     */
    private static function get_total_institutions() {
        $count = wp_count_posts('institutions');
        return isset($count->publish) ? $count->publish : 0;
    }

    /**
     * Total de CPD Activities publicadas
     *
     * CORRETO: Filtra por TODAS as instituições que o admin gerencia
     * Usa act_user_id (não post_author) para relacionamento
     */
    private static function get_cpd_activities() {
        global $wpdb;

        $is_institution_admin = Eau_User_Institution_Helper::is_institution_admin();

        if ($is_institution_admin) {
            $company_ids = Eau_User_Institution_Helper::get_user_managed_company_ids();

            if (empty($company_ids)) {
                return 0;
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
                return 0;
            }

            // Pega os mem_userid desses usuários
            $act_user_ids = array();
            foreach ($user_ids as $user_id) {
                $mem_userid = get_user_meta($user_id, 'mem_userid', true);
                if (!empty($mem_userid)) {
                    $act_user_ids[] = $mem_userid;
                }
            }

            if (empty($act_user_ids)) {
                return 0;
            }

            // Busca activities via act_user_id (relacionamento correto)
            $placeholders = implode(',', array_fill(0, count($act_user_ids), '%s'));
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT p.ID)
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_type = 'activitie'
                AND p.post_status = 'publish'
                AND pm.meta_key = 'act_user_id'
                AND pm.meta_value IN ($placeholders)",
                ...$act_user_ids
            ));

            return intval($count);
        }

        // Admin/Super Admin: vê tudo
        $count = wp_count_posts('activitie');
        return isset($count->publish) ? $count->publish : 0;
    }

    /**
     * Activities pendentes de aprovação (act_verified != 1)
     *
     * CORRETO: Filtra por TODAS as instituições que o admin gerencia
     * Usa act_user_id (não post_author) para relacionamento
     */
    private static function get_pending_approval() {
        global $wpdb;

        $is_institution_admin = Eau_User_Institution_Helper::is_institution_admin();

        if ($is_institution_admin) {
            $company_ids = Eau_User_Institution_Helper::get_user_managed_company_ids();

            if (empty($company_ids)) {
                return 0;
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
                return 0;
            }

            // Pega os mem_userid desses usuários
            $act_user_ids = array();
            foreach ($user_ids as $user_id) {
                $mem_userid = get_user_meta($user_id, 'mem_userid', true);
                if (!empty($mem_userid)) {
                    $act_user_ids[] = $mem_userid;
                }
            }

            if (empty($act_user_ids)) {
                return 0;
            }

            // Activities pendentes via act_user_id (relacionamento correto)
            $placeholders = implode(',', array_fill(0, count($act_user_ids), '%s'));
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT p.ID)
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm_user ON p.ID = pm_user.post_id
                LEFT JOIN {$wpdb->postmeta} pm_verified ON p.ID = pm_verified.post_id AND pm_verified.meta_key = 'act_verified'
                WHERE p.post_type = 'activitie'
                AND p.post_status = 'publish'
                AND pm_user.meta_key = 'act_user_id'
                AND pm_user.meta_value IN ($placeholders)
                AND (pm_verified.meta_value IS NULL OR pm_verified.meta_value != '1')",
                ...$act_user_ids
            ));

            return intval($count);
        }

        // Admin/Super Admin: vê tudo
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT p.ID)
                FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = %s
                WHERE p.post_type = %s
                AND p.post_status = %s
                AND (pm.meta_value IS NULL OR pm.meta_value != %s)",
                'act_verified',
                'activitie',
                'publish',
                '1'
            )
        );

        return intval($count);
    }

    /**
     * Eventos ativos (publicados com event_date >= hoje)
     */
    private static function get_active_events() {
        global $wpdb;
        $today = current_time('Y-m-d');

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT p.ID)
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_type = %s
                AND p.post_status = %s
                AND pm.meta_key = %s
                AND pm.meta_value >= %s",
                'events',
                'publish',
                'event_date',
                $today
            )
        );

        return intval($count);
    }

    /**
     * Soma de pontos (hours) de todas as activities publicadas
     *
     * CORRETO: Filtra por TODAS as instituições que o admin gerencia
     */
    private static function get_points_awarded() {
        global $wpdb;

        $table_categories = $wpdb->prefix . 'eau_activity_categories';
        $is_institution_admin = Eau_User_Institution_Helper::is_institution_admin();

        if ($is_institution_admin) {
            $company_ids = Eau_User_Institution_Helper::get_user_managed_company_ids();

            if (empty($company_ids)) {
                return 0;
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
                return 0;
            }

            // Pega os mem_userid desses usuários
            $act_user_ids = array();
            foreach ($user_ids as $user_id) {
                $mem_userid = get_user_meta($user_id, 'mem_userid', true);
                if (!empty($mem_userid)) {
                    $act_user_ids[] = $mem_userid;
                }
            }

            if (empty($act_user_ids)) {
                return 0;
            }

            // Calcula pontos: horas × pontos_per_hour da categoria
            $placeholders = implode(',', array_fill(0, count($act_user_ids), '%s'));
            $total = $wpdb->get_var($wpdb->prepare(
                "SELECT SUM(
                    CAST(pm_hours.meta_value AS DECIMAL(10,2)) *
                    COALESCE(cat.points_per_hour, 0)
                )
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm_user ON p.ID = pm_user.post_id AND pm_user.meta_key = 'act_user_id'
                INNER JOIN {$wpdb->postmeta} pm_hours ON p.ID = pm_hours.post_id AND pm_hours.meta_key = 'act_hours_of_pd_anything_below_60_minutes_can_be_entered_as_a_decimal_e_g_30_mins_0_5'
                LEFT JOIN {$wpdb->postmeta} pm_cat ON p.ID = pm_cat.post_id AND pm_cat.meta_key = 'act_category_serial'
                LEFT JOIN {$table_categories} cat ON cat.category_serial = pm_cat.meta_value
                WHERE p.post_type = 'activitie'
                AND p.post_status = 'publish'
                AND pm_user.meta_value IN ($placeholders)",
                ...$act_user_ids
            ));

            return floatval($total);
        }

        // Admin/Super Admin: vê tudo - calcula pontos: horas × pontos_per_hour
        $total = $wpdb->get_var(
            "SELECT SUM(
                CAST(pm_hours.meta_value AS DECIMAL(10,2)) *
                COALESCE(cat.points_per_hour, 0)
            )
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm_hours ON p.ID = pm_hours.post_id
                AND pm_hours.meta_key = 'act_hours_of_pd_anything_below_60_minutes_can_be_entered_as_a_decimal_e_g_30_mins_0_5'
            LEFT JOIN {$wpdb->postmeta} pm_cat ON p.ID = pm_cat.post_id
                AND pm_cat.meta_key = 'act_category_serial'
            LEFT JOIN {$table_categories} cat ON cat.category_serial = pm_cat.meta_value
            WHERE p.post_type = 'activitie'
            AND p.post_status = 'publish'"
        );

        return floatval($total);
    }

    /**
     * Pending Payments (fake data por enquanto)
     */
    private static function get_pending_payments() {
        // TODO: Implementar quando o post type de payments for criado
        return 12; // Fake data
    }

    /**
     * Retorna informações sobre a role do usuário para exibição
     *
     * @param int $user_id ID do usuário
     * @return array Array com 'description' e 'institutions' (array de nomes)
     */
    private static function get_user_role_info($user_id) {
        $user = get_userdata($user_id);

        // Verifica se é Super Admin ou Admin (manage_options)
        if (in_array('administrator', $user->roles) || current_user_can('manage_options')) {
            return array(
                'description' => 'System Administrator - Full access to all institutions and data',
                'institutions' => array(),
            );
        }

        // Verifica se é Institution Admin - CORRETO: pega TODAS as instituições
        if (Eau_User_Institution_Helper::is_institution_admin($user_id)) {
            $institution_names = Eau_User_Institution_Helper::get_user_managed_institution_names($user_id);

            if (empty($institution_names)) {
                return array(
                    'description' => 'Institution Administrator for',
                    'institutions' => array('Unknown Institution'),
                );
            }

            return array(
                'description' => 'Institution Administrator for',
                'institutions' => $institution_names,
            );
        }

        // Membro comum
        return array(
            'description' => 'Here\'s what\'s happening with your membership today.',
            'institutions' => array(),
        );
    }
}
