<?php
namespace EauSystem;

use EauSystem\Components\Eau_Stats_Cards;
use EauSystem\Components\Eau_Data_Table;
use EauSystem\Components\Eau_Pagination;
use EauSystem\Components\Eau_Filters;
use EauSystem\Components\Eau_Modal;
use EauSystem\Components\Eau_Access_Denied;

/**
 * Institutions Management Page
 *
 * Shortcode: [eau_institutions_management]
 */
class Eau_Institutions_Management {

    /**
     * Registra o shortcode
     */
    public static function register_shortcode() {
        add_shortcode('eau_institutions_management', array(__CLASS__, 'render_institutions_management'));
    }

    /**
     * Renderiza a página de Institutions Management
     *
     * @param array $atts Atributos do shortcode
     * @return string HTML da página
     */
    public static function render_institutions_management($atts) {
        // Verifica se usuário está logado
        if (!is_user_logged_in()) {
            return Eau_Access_Denied::not_logged_in();
        }

        // Verifica se usuário tem permissão (superAdmin ou Admin)
        if (!Eau_User_Institution_Helper::has_admin_access()) {
            return Eau_Access_Denied::no_permission();
        }

        // Carrega assets
        self::enqueue_assets();

        // Pega estatísticas
        $stats = self::get_institutions_stats();

        ob_start();
        ?>
        <div class="eau-institutions-management-container">

            <!-- Stats Cards -->
            <?php echo self::render_stats_cards($stats); ?>

            <!-- Page Header -->
            <div class="eau-page-header">
                <div class="eau-page-header-title">
                    <h1>Institutions Management</h1>
                    <p class="eau-page-header-subtitle">Managing all institutions and their settings</p>
                </div>
                <div class="eau-page-header-actions">
                    <?php if (Eau_User_Institution_Helper::is_super_admin()): ?>
                        <button class="eau-btn eau-btn-danger" id="eau-bulk-delete-institutions" style="display: none;">
                            <i data-lucide="trash-2"></i>
                            Delete Selected
                        </button>
                        <button class="eau-btn eau-btn-danger" id="eau-delete-all-filtered-institutions">
                            <i data-lucide="trash-2"></i>
                            Delete All Filtered
                        </button>
                    <?php endif; ?>
                    <button class="eau-btn eau-btn-secondary" id="eau-export-institutions-csv">
                        <i data-lucide="download"></i>
                        Export CSV
                    </button>
                    <button class="eau-btn eau-btn-primary" id="eau-add-institution">
                        <i data-lucide="building-2"></i>
                        Add Institution
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
                        placeholder="Search by institution name or code..."
                        id="eau-institutions-search"
                    >
                </div>
                <button class="eau-btn eau-btn-secondary" id="eau-filters-toggle">
                    <i data-lucide="filter"></i>
                    Filters
                </button>
            </div>

            <!-- Filters Panel (hidden by default) -->
            <?php echo self::render_filters(); ?>

            <!-- Institutions Table -->
            <?php echo self::render_institutions_table(); ?>

            <!-- Pagination -->
            <div id="eau-pagination-container">
                <?php echo self::render_pagination(); ?>
            </div>

            <!-- Modals -->
            <?php echo self::render_modals(); ?>

        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Pega estatísticas das instituições
     *
     * @return array Estatísticas
     */
    private static function get_institutions_stats() {
        // Total de instituições (usando wp_count_posts)
        $counts = wp_count_posts('institutions');
        $total = isset($counts->publish) ? (int) $counts->publish : 0;

        // Para active/inactive, precisamos contar via meta_query
        // Active institutions (sem status definido ou status = 'active')
        $active_query = new \WP_Query(array(
            'post_type' => 'institutions',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => 'ins_status',
                    'compare' => 'NOT EXISTS',
                ),
                array(
                    'key' => 'ins_status',
                    'value' => 'active',
                    'compare' => '=',
                ),
            ),
        ));
        $active = $active_query->found_posts;

        // Inactive institutions
        $inactive_query = new \WP_Query(array(
            'post_type' => 'institutions',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => array(
                array(
                    'key' => 'ins_status',
                    'value' => 'inactive',
                    'compare' => '=',
                ),
            ),
        ));
        $inactive = $inactive_query->found_posts;

        // Novas este mês
        $current_month = date('m');
        $current_year = date('Y');
        $new_this_month_query = new \WP_Query(array(
            'post_type' => 'institutions',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'date_query' => array(
                array(
                    'year' => $current_year,
                    'month' => $current_month,
                ),
            ),
        ));
        $new_this_month = $new_this_month_query->found_posts;

        return array(
            'total' => $total,
            'active' => $active,
            'inactive' => $inactive,
            'new_this_month' => $new_this_month,
        );
    }

    /**
     * Renderiza os cards de estatísticas
     *
     * @param array $stats Estatísticas das instituições
     * @return string HTML dos cards
     */
    private static function render_stats_cards($stats) {
        $cards_data = array(
            array(
                'title' => 'Total Institutions',
                'number' => $stats['total'],
                'icon' => 'building-2',
                'color' => 'blue',
            ),
            array(
                'title' => 'Active Institutions',
                'number' => $stats['active'],
                'icon' => 'check-circle',
                'color' => 'green',
            ),
            array(
                'title' => 'New This Month',
                'number' => $stats['new_this_month'],
                'icon' => 'plus-circle',
                'color' => 'purple',
            ),
            array(
                'title' => 'Inactive Institutions',
                'number' => $stats['inactive'],
                'icon' => 'x-circle',
                'color' => 'red',
            ),
        );

        $stats_cards = new Eau_Stats_Cards($cards_data);
        return $stats_cards->render();
    }

    /**
     * Enfileira CSS e JS
     */
    private static function enqueue_assets() {
        // CSS dos componentes
        wp_enqueue_style(
            'eau-components',
            EAU_SYSTEM_PLUGIN_URL . 'assets/css/eau-components.css',
            array(),
            EAU_SYSTEM_VERSION . '-' . time()
        );

        // CSS específico da página
        wp_enqueue_style(
            'eau-institutions-management',
            EAU_SYSTEM_PLUGIN_URL . 'assets/css/eau-institutions-management.css',
            array('eau-components'),
            EAU_SYSTEM_VERSION . '-' . time()
        );

        // Lucide Icons
        wp_enqueue_script(
            'lucide-icons',
            'https://unpkg.com/lucide@latest/dist/umd/lucide.min.js',
            array(),
            null,
            true
        );

        // JS dos componentes
        wp_enqueue_script(
            'eau-components',
            EAU_SYSTEM_PLUGIN_URL . 'assets/js/eau-components.js',
            array('jquery', 'lucide-icons'),
            EAU_SYSTEM_VERSION,
            true
        );

        // JS de notificações (Toast e Confirm)
        wp_enqueue_script(
            'eau-notifications',
            EAU_SYSTEM_PLUGIN_URL . 'assets/js/eau-notifications.js',
            array('jquery', 'lucide-icons'),
            EAU_SYSTEM_VERSION,
            true
        );

        // JS específico da página
        wp_enqueue_script(
            'eau-institutions-management',
            EAU_SYSTEM_PLUGIN_URL . 'assets/js/eau-institutions-management.js',
            array('jquery', 'eau-components', 'eau-notifications', 'lucide-icons'),
            EAU_SYSTEM_VERSION,
            true
        );

        // Localiza script com dados do AJAX
        wp_localize_script('eau-institutions-management', 'eauInstitutionsData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('eau_institutions_nonce'),
            'isSuperAdmin' => Eau_User_Institution_Helper::is_super_admin(),
        ));
    }

    /**
     * Renderiza a tabela de instituições
     *
     * @return string HTML da tabela
     */
    private static function render_institutions_table() {
        $table_config = array(
            'id' => 'institutions-table',
            'columns' => array(
                array(
                    'key' => 'institution',
                    'label' => 'INSTITUTION',
                    'sortable' => true,
                ),
                array(
                    'key' => 'campus',
                    'label' => 'CAMPUS',
                    'sortable' => true,
                ),
                array(
                    'key' => 'code',
                    'label' => 'CODE',
                    'sortable' => true,
                ),
                array(
                    'key' => 'type',
                    'label' => 'TYPE',
                    'sortable' => true,
                ),
                array(
                    'key' => 'contact',
                    'label' => 'CONTACT',
                    'sortable' => true,
                ),
                array(
                    'key' => 'members',
                    'label' => 'MEMBERS',
                    'sortable' => true,
                ),
                array(
                    'key' => 'start_date',
                    'label' => 'START DATE',
                    'sortable' => true,
                ),
                array(
                    'key' => 'expire_date',
                    'label' => 'EXPIRE DATE',
                    'sortable' => true,
                ),
                array(
                    'key' => 'status',
                    'label' => 'STATUS',
                    'sortable' => true,
                ),
            ),
            'actions' => array('view', 'edit', 'delete'),
            'selectable' => true,
            'ajax_endpoint' => 'eau_get_institutions',
            'empty_message' => 'No institutions found',
            'loading_message' => 'Loading institutions...',
        );

        $table = new Eau_Data_Table($table_config);
        return $table->render();
    }

    /**
     * Renderiza a paginação (será atualizada via AJAX)
     *
     * @return string HTML da paginação
     */
    private static function render_pagination() {
        // Paginação inicial vazia - será preenchida via AJAX
        $pagination_config = array(
            'id' => 'institutions-pagination',
            'total_items' => 0,
            'per_page' => 20,
            'current_page' => 1,
            'max_pages_shown' => 5,
        );

        $pagination = new Eau_Pagination($pagination_config);
        return $pagination->render();
    }

    /**
     * Renderiza o painel de filtros
     *
     * @return string HTML dos filtros
     */
    private static function render_filters() {
        $filters_config = array(
            'id' => 'eau-filters-panel',
            'collapsible' => true,
            'show_clear' => true,
            'filters' => array(
                array(
                    'key' => 'status',
                    'label' => 'Status',
                    'type' => 'select',
                    'options' => Eau_Filters::get_status_options(),
                    'placeholder' => 'All Status',
                ),
                array(
                    'key' => 'institution_type',
                    'label' => 'Institution Type',
                    'type' => 'select',
                    'options' => array(
                        'College' => 'College',
                        'Corporate affiliate' => 'Corporate Affiliate',
                        'not_defined' => 'Not Defined',
                    ),
                    'placeholder' => 'All Types',
                ),
                array(
                    'key' => 'campus',
                    'label' => 'Campus',
                    'type' => 'text',
                    'placeholder' => 'Search by campus name...',
                ),
                array(
                    'key' => 'start_date',
                    'label' => 'Start Date',
                    'type' => 'date_range',
                ),
                array(
                    'key' => 'expire_date',
                    'label' => 'Expire Date',
                    'type' => 'date_range',
                ),
            ),
        );

        $filters = new Eau_Filters($filters_config);
        return $filters->render();
    }

    /**
     * Renderiza os modais (View, Edit, Add)
     *
     * @return string HTML dos modais
     */
    private static function render_modals() {
        $html = '';

        // Modal: View Institution
        $view_modal_config = array(
            'id' => 'eau-modal-view',
            'title' => 'View Institution',
            'size' => 'large',
            'show_footer' => true,
            'footer_buttons' => array(
                array(
                    'label' => 'Close',
                    'class' => 'eau-btn-secondary',
                    'action' => 'close',
                ),
            ),
        );
        $view_modal = new Eau_Modal($view_modal_config);
        $html .= $view_modal->render();

        // Modal: Edit Institution
        $edit_modal_config = array(
            'id' => 'eau-modal-edit',
            'title' => 'Edit Institution',
            'size' => 'large',
            'show_footer' => true,
            'footer_buttons' => array(
                array(
                    'label' => 'Cancel',
                    'class' => 'eau-btn-secondary',
                    'action' => 'close',
                ),
                array(
                    'label' => 'Save Changes',
                    'class' => 'eau-btn-primary',
                    'action' => 'save',
                ),
            ),
        );
        $edit_modal = new Eau_Modal($edit_modal_config);
        $html .= $edit_modal->render();

        // Modal: Add Institution
        $add_modal_config = array(
            'id' => 'eau-modal-add',
            'title' => 'Add New Institution',
            'size' => 'large',
            'show_footer' => true,
            'footer_buttons' => array(
                array(
                    'label' => 'Cancel',
                    'class' => 'eau-btn-secondary',
                    'action' => 'close',
                ),
                array(
                    'label' => 'Create Institution',
                    'class' => 'eau-btn-primary',
                    'action' => 'create',
                ),
            ),
        );
        $add_modal = new Eau_Modal($add_modal_config);
        $html .= $add_modal->render();

        return $html;
    }
}
