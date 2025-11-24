<?php
namespace EauSystem;

use EauSystem\Components\Eau_Stats_Cards;
use EauSystem\Components\Eau_Data_Table;
use EauSystem\Components\Eau_Pagination;
use EauSystem\Components\Eau_Filters;
use EauSystem\Components\Eau_Modal;
use EauSystem\Components\Eau_Access_Denied;

/**
 * Activities Management Page
 *
 * Shortcode: [eau_activities_management]
 */
class Eau_Activities_Management {

    /**
     * Registra o shortcode
     */
    public static function register_shortcode() {
        add_shortcode('eau_activities_management', array(__CLASS__, 'render_activities_management'));
    }

    /**
     * Renderiza a página de Activities Management
     *
     * @param array $atts Atributos do shortcode
     * @return string HTML da página
     */
    public static function render_activities_management($atts) {
        // Verifica se usuário está logado
        if (!is_user_logged_in()) {
            return Eau_Access_Denied::not_logged_in();
        }

        // Verifica se usuário tem permissão (superAdmin, Admin ou institutionAdmin)
        if (!Eau_User_Institution_Helper::has_admin_access() &&
            !Eau_User_Institution_Helper::is_institution_admin()) {
            return Eau_Access_Denied::no_permission();
        }

        // Carrega assets
        self::enqueue_assets();

        // Pega estatísticas
        $stats = self::get_activities_stats();

        ob_start();
        ?>
        <div class="eau-activities-management-container">

            <!-- Stats Cards -->
            <?php echo self::render_stats_cards($stats); ?>

            <!-- Page Header -->
            <div class="eau-page-header">
                <div class="eau-page-header-title">
                    <h1>CPD Activities Management</h1>
                    <p class="eau-page-header-subtitle">Managing all CPD activities and their verification status</p>
                </div>
                <div class="eau-page-header-actions">
                    <?php if (Eau_User_Institution_Helper::is_super_admin()): ?>
                        <button class="eau-btn eau-btn-danger" id="eau-bulk-delete-activities" style="display: none;">
                            <i data-lucide="trash-2"></i>
                            Delete Selected
                        </button>
                        <button class="eau-btn eau-btn-danger" id="eau-delete-all-filtered-activities">
                            <i data-lucide="trash-2"></i>
                            Delete All Filtered
                        </button>
                    <?php endif; ?>
                    <button class="eau-btn eau-btn-secondary" id="eau-export-activities-csv">
                        <i data-lucide="download"></i>
                        Export CSV
                    </button>
                    <button class="eau-btn eau-btn-primary" id="eau-add-activity">
                        <i data-lucide="plus"></i>
                        Add Activity
                    </button>
                </div>
            </div>

            <!-- Search and Filters -->
            <div class="eau-search-filters-bar">
                <div class="eau-search-wrapper">
                    <i data-lucide="search"></i>
                    <input
                        type="text"
                        class="eau-search-input"
                        placeholder="Search by activity title or member..."
                        id="eau-activities-search"
                    >
                </div>
                <button class="eau-btn eau-btn-secondary eau-filters-toggle" id="eau-filters-toggle">
                    <i data-lucide="filter"></i>
                    Filters
                </button>
            </div>

            <!-- Filters Panel -->
            <?php echo self::render_filters(); ?>

            <!-- Data Table -->
            <?php echo self::render_data_table(); ?>

            <!-- Pagination -->
            <div id="eau-activities-pagination"></div>

            <!-- Modals -->
            <?php echo self::render_modals(); ?>

        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Pega estatísticas das atividades
     *
     * @return array Estatísticas
     */
    private static function get_activities_stats() {
        global $wpdb;

        $is_institution_admin = Eau_User_Institution_Helper::is_institution_admin();

        if ($is_institution_admin) {
            // Institution Admin: filtra por suas instituições
            $company_ids = Eau_User_Institution_Helper::get_user_managed_company_ids(get_current_user_id());

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
            foreach ($user_ids as $user_id) {
                $mem_userid = get_user_meta($user_id, 'mem_userid', true);
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

            // Total
            $total = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT p.ID)
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm_user ON p.ID = pm_user.post_id
                WHERE p.post_type = 'activitie'
                AND p.post_status = 'publish'
                AND pm_user.meta_key = 'act_user_id'
                AND pm_user.meta_value IN ($placeholders)",
                ...$act_user_ids
            ));

            // Verified
            $verified = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT p.ID)
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm_user ON p.ID = pm_user.post_id
                INNER JOIN {$wpdb->postmeta} pm_verified ON p.ID = pm_verified.post_id
                WHERE p.post_type = 'activitie'
                AND p.post_status = 'publish'
                AND pm_user.meta_key = 'act_user_id'
                AND pm_user.meta_value IN ($placeholders)
                AND pm_verified.meta_key = 'act_verified'
                AND pm_verified.meta_value = '1'",
                ...$act_user_ids
            ));

            // Pending
            $pending = $wpdb->get_var($wpdb->prepare(
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

            // Total points (horas × pontos_per_hour da categoria)
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
                AND pm_hours.meta_key = 'act_hours_of_pd_anything_below_60_minutes_can_be_entered_as_a_decimal_e_g_30_mins_0_5'",
                ...$act_user_ids
            ));
        } else {
            // Admin/Super Admin: vê tudo
            $counts = wp_count_posts('activitie');
            $total = isset($counts->publish) ? (int) $counts->publish : 0;

            // Verified
            $verified = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT p.ID)
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_type = %s
                AND p.post_status = %s
                AND pm.meta_key = %s
                AND pm.meta_value = %s",
                'activitie',
                'publish',
                'act_verified',
                '1'
            ));

            // Pending
            $pending = $wpdb->get_var($wpdb->prepare(
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
            ));

            // Total points (horas × pontos_per_hour da categoria)
            $table_categories = $wpdb->prefix . 'eau_activity_categories';
            $total_hours = $wpdb->get_var(
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
        }

        return array(
            'total' => (int) $total,
            'verified' => (int) $verified,
            'pending' => (int) $pending,
            'total_hours' => (float) $total_hours,
        );
    }

    /**
     * Renderiza os cards de estatísticas
     *
     * @param array $stats Estatísticas das atividades
     * @return string HTML dos cards
     */
    private static function render_stats_cards($stats) {
        $cards_data = array(
            array(
                'title' => 'Total Activities',
                'number' => $stats['total'],
                'icon' => 'book-open',
                'color' => 'blue',
            ),
            array(
                'title' => 'Verified Activities',
                'number' => $stats['verified'],
                'icon' => 'check-circle',
                'color' => 'green',
            ),
            array(
                'title' => 'Pending Verification',
                'number' => $stats['pending'],
                'icon' => 'clock',
                'color' => 'orange',
            ),
            array(
                'title' => 'Total Points',
                'number' => number_format_i18n($stats['total_hours'], 2),
                'icon' => 'award',
                'color' => 'purple',
            ),
        );

        $stats_cards = new Eau_Stats_Cards($cards_data);
        return $stats_cards->render();
    }

    /**
     * Renderiza os filtros
     *
     * @return string HTML dos filtros
     */
    private static function render_filters() {
        $filters_config = array(
            'id' => 'activities-filters',
            'filters' => array(
                array(
                    'key' => 'verified',
                    'label' => 'Verification Status',
                    'type' => 'select',
                    'options' => array(
                        '1' => 'Verified',
                        '0' => 'Pending',
                    ),
                    'placeholder' => 'All Status'
                ),
                array(
                    'key' => 'institution',
                    'label' => 'Institution',
                    'type' => 'select',
                    'options' => Eau_Filters::get_institution_options(),
                    'placeholder' => 'All Institutions'
                ),
                array(
                    'key' => 'date',
                    'label' => 'Date Range',
                    'type' => 'date_range'
                ),
            )
        );

        $filters = new Eau_Filters($filters_config);
        return $filters->render();
    }

    /**
     * Renderiza a tabela de dados
     *
     * @return string HTML da tabela
     */
    private static function render_data_table() {
        $columns = array(
            array(
                'key' => 'activity',
                'label' => 'Activity',
                'sortable' => true,
            ),
            array(
                'key' => 'member',
                'label' => 'Member',
                'sortable' => true,
            ),
            array(
                'key' => 'institution',
                'label' => 'Institution',
                'sortable' => true,
            ),
            array(
                'key' => 'category',
                'label' => 'Category',
                'sortable' => true,
            ),
            array(
                'key' => 'hours',
                'label' => 'Hours',
                'sortable' => true,
            ),
            array(
                'key' => 'points',
                'label' => 'Points',
                'sortable' => true,
            ),
            array(
                'key' => 'status',
                'label' => 'Status',
                'sortable' => false,
            ),
            array(
                'key' => 'date',
                'label' => 'Date',
                'sortable' => true,
            ),
        );

        $table = new Eau_Data_Table(array(
            'id' => 'activities-table',
            'columns' => $columns,
            'loading_rows' => 30,
        ));

        return $table->render();
    }

    /**
     * Renderiza os modals
     *
     * @return string HTML dos modals
     */
    private static function render_modals() {
        $html = '';

        // Modal View
        $view_modal = new Eau_Modal(array(
            'id' => 'eau-modal-view',
            'title' => 'View Activity',
            'size' => 'large',
        ));
        $html .= $view_modal->render();

        // Modal Edit
        $edit_modal = new Eau_Modal(array(
            'id' => 'eau-modal-edit',
            'title' => 'Edit Activity',
            'size' => 'large',
            'buttons' => array(
                array(
                    'text' => 'Cancel',
                    'class' => 'eau-btn-secondary',
                    'action' => 'close',
                ),
                array(
                    'text' => 'Save Changes',
                    'class' => 'eau-btn-primary',
                    'action' => 'save',
                ),
            ),
        ));
        $html .= $edit_modal->render();

        // Modal Add
        $add_modal = new Eau_Modal(array(
            'id' => 'eau-modal-add',
            'title' => 'Add Activity',
            'size' => 'large',
            'buttons' => array(
                array(
                    'text' => 'Cancel',
                    'class' => 'eau-btn-secondary',
                    'action' => 'close',
                ),
                array(
                    'text' => 'Create Activity',
                    'class' => 'eau-btn-primary',
                    'action' => 'save',
                ),
            ),
        ));
        $html .= $add_modal->render();

        return $html;
    }

    /**
     * Carrega assets (CSS e JS)
     */
    private static function enqueue_assets() {
        // CSS dos componentes
        wp_enqueue_style(
            'eau-components',
            EAU_SYSTEM_PLUGIN_URL . 'assets/css/eau-components.css',
            array(),
            EAU_SYSTEM_VERSION
        );

        // CSS específico da página
        wp_enqueue_style(
            'eau-activities-management',
            EAU_SYSTEM_PLUGIN_URL . 'assets/css/eau-activities-management.css',
            array('eau-components'),
            EAU_SYSTEM_VERSION
        );

        // JS - Notifications library
        wp_enqueue_script(
            'eau-notifications',
            EAU_SYSTEM_PLUGIN_URL . 'assets/js/eau-notifications.js',
            array('jquery', 'lucide-icons'),
            EAU_SYSTEM_VERSION,
            true
        );

        // JS - Activities Management
        wp_enqueue_script(
            'eau-activities-management',
            EAU_SYSTEM_PLUGIN_URL . 'assets/js/eau-activities-management.js',
            array('jquery', 'eau-notifications', 'lucide-icons'),
            EAU_SYSTEM_VERSION,
            true
        );

        // Localiza script
        wp_localize_script('eau-activities-management', 'eauActivitiesData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('eau_activities_nonce'),
            'isSuperAdmin' => Eau_User_Institution_Helper::is_super_admin(),
        ));
    }
}
