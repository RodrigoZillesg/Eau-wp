<?php
namespace EauSystem;

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
            return '<p>Você precisa estar logado para ver o dashboard.</p>';
        }

        // Por enquanto, qualquer usuário logado pode ver
        // TODO: Adicionar verificação de mem_type depois

        // Coleta estatísticas
        $stats = self::get_dashboard_stats();

        // Pega o nome do usuário logado
        $current_user = wp_get_current_user();
        $display_name = $current_user->display_name;

        // Renderiza HTML
        ob_start();
        ?>
        <div class="eau-dashboard-container">

            <!-- Welcome Section -->
            <div class="eau-welcome-section">
                <h1 class="eau-welcome-title">Welcome, <?php echo esc_html($display_name); ?></h1>
                <p class="eau-welcome-description">Here's what's happening with your membership today.</p>
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

                <!-- CPD Activities -->
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
        return array(
            'total_members' => self::get_total_members(),
            'active_members' => self::get_active_members(),
            'cpd_activities' => self::get_cpd_activities(),
            'pending_approval' => self::get_pending_approval(),
            'active_events' => self::get_active_events(),
            'points_awarded' => self::get_points_awarded(),
            'pending_payments' => self::get_pending_payments(),
        );
    }

    /**
     * Total de membros (todos os usuários)
     */
    private static function get_total_members() {
        $users = count_users();
        return $users['total_users'];
    }

    /**
     * Membros ativos (mem_status = Active)
     */
    private static function get_active_members() {
        global $wpdb;

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT user_id)
                FROM {$wpdb->usermeta}
                WHERE meta_key = %s
                AND meta_value = %s",
                'mem_status',
                'Active'
            )
        );

        return intval($count);
    }

    /**
     * Total de CPD Activities publicadas
     */
    private static function get_cpd_activities() {
        $count = wp_count_posts('activitie');
        return isset($count->publish) ? $count->publish : 0;
    }

    /**
     * Activities pendentes de aprovação (act_verified != 1)
     */
    private static function get_pending_approval() {
        global $wpdb;

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
     */
    private static function get_points_awarded() {
        global $wpdb;

        $total = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(CAST(pm.meta_value AS DECIMAL(10,2)))
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_type = %s
                AND p.post_status = %s
                AND pm.meta_key = %s",
                'activitie',
                'publish',
                'act_hours_of_pd_anything_below_60_minutes_can_be_entered_as_a_decimal_e_g_30_mins_0_5'
            )
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
}
