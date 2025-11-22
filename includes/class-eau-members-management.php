<?php
namespace EauSystem;

use EauSystem\Components\Eau_Stats_Cards;
use EauSystem\Components\Eau_Data_Table;
use EauSystem\Components\Eau_Pagination;
use EauSystem\Components\Eau_Filters;
use EauSystem\Components\Eau_Modal;
use EauSystem\Components\Eau_Access_Denied;

/**
 * Members Management Page
 *
 * Shortcode: [eau_members_management]
 */
class Eau_Members_Management {

    /**
     * Registra o shortcode
     */
    public static function register_shortcode() {
        add_shortcode('eau_members_management', array(__CLASS__, 'render_members_management'));
    }

    /**
     * Renderiza a página de Members Management
     *
     * @param array $atts Atributos do shortcode
     * @return string HTML da página
     */
    public static function render_members_management($atts) {
        // Verifica se usuário está logado
        if (!is_user_logged_in()) {
            return Eau_Access_Denied::not_logged_in();
        }

        // Verifica se usuário é Admin, Super Admin ou Institution Admin
        if (!current_user_can('manage_options') &&
            !Eau_User_Institution_Helper::is_institution_admin()) {
            return Eau_Access_Denied::no_permission();
        }

        // Carrega assets
        self::enqueue_assets();

        // Pega estatísticas
        $stats = Eau_User_Institution_Helper::get_users_stats();

        ob_start();
        ?>
        <div class="eau-members-management-container">

            <!-- Stats Cards -->
            <?php echo self::render_stats_cards($stats); ?>

            <!-- Page Header -->
            <div class="eau-page-header">
                <div class="eau-page-header-title">
                    <h1>Members Management</h1>
                    <p class="eau-page-header-subtitle">Managing all members across all institutions</p>
                </div>
                <div class="eau-page-header-actions">
                    <button class="eau-btn eau-btn-secondary" id="eau-export-csv">
                        <i data-lucide="download"></i>
                        Export CSV
                    </button>
                    <a href="/dashboard/merge-members/" class="eau-btn eau-btn-secondary">
                        <i data-lucide="users-2"></i>
                        Merge Duplicates
                    </a>
                    <button class="eau-btn eau-btn-primary" id="eau-add-member">
                        <i data-lucide="user-plus"></i>
                        Add Member
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
                        placeholder="Search by name, email, or phone..."
                        id="eau-members-search"
                    >
                </div>
                <button class="eau-btn eau-btn-secondary" id="eau-filters-toggle">
                    <i data-lucide="filter"></i>
                    Filters
                </button>
            </div>

            <!-- Filters Panel (hidden by default) -->
            <?php echo self::render_filters(); ?>

            <!-- Members Table -->
            <?php echo self::render_members_table(); ?>

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
     * Renderiza os cards de estatísticas
     *
     * @param array $stats Estatísticas dos usuários
     * @return string HTML dos cards
     */
    private static function render_stats_cards($stats) {
        $cards_data = array(
            array(
                'title' => 'Total Members',
                'number' => $stats['total'],
                'icon' => 'users',
                'color' => 'blue',
            ),
            array(
                'title' => 'Active Members',
                'number' => $stats['active'],
                'icon' => 'user-check',
                'color' => 'green',
            ),
            array(
                'title' => 'New This Month',
                'number' => $stats['new_this_month'],
                'icon' => 'user-plus',
                'color' => 'purple',
            ),
            array(
                'title' => 'Inactive Members',
                'number' => $stats['inactive'],
                'icon' => 'user-x',
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
            EAU_SYSTEM_VERSION
        );

        // CSS específico da página
        wp_enqueue_style(
            'eau-members-management',
            EAU_SYSTEM_PLUGIN_URL . 'assets/css/eau-members-management.css',
            array('eau-components'),
            EAU_SYSTEM_VERSION
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
            'eau-members-management',
            EAU_SYSTEM_PLUGIN_URL . 'assets/js/eau-members-management.js',
            array('jquery', 'eau-components', 'eau-notifications', 'lucide-icons'),
            EAU_SYSTEM_VERSION,
            true
        );

        // Localiza script com dados do AJAX
        wp_localize_script('eau-members-management', 'eauMembersData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('eau_members_nonce'),
        ));
    }

    /**
     * Renderiza a tabela de membros
     *
     * @return string HTML da tabela
     */
    private static function render_members_table() {
        $table_config = array(
            'id' => 'members-table',
            'columns' => array(
                array(
                    'key' => 'member',
                    'label' => 'MEMBER',
                    'sortable' => true,
                ),
                array(
                    'key' => 'contact',
                    'label' => 'CONTACT',
                    'sortable' => true,
                ),
                array(
                    'key' => 'membership',
                    'label' => 'MEMBERSHIP',
                ),
                array(
                    'key' => 'user_type',
                    'label' => 'USER TYPE',
                ),
                array(
                    'key' => 'status',
                    'label' => 'STATUS',
                ),
            ),
            'actions' => array('view', 'edit', 'delete'),
            'selectable' => true,
            'ajax_endpoint' => 'eau_get_members',
            'empty_message' => 'No members found',
            'loading_message' => 'Loading members...',
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
            'id' => 'members-pagination',
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
                    'key' => 'role',
                    'label' => 'User Type',
                    'type' => 'select',
                    'options' => Eau_Filters::get_user_type_options(),
                    'placeholder' => 'All User Types',
                ),
                array(
                    'key' => 'institution',
                    'label' => 'Institution',
                    'type' => 'select',
                    'options' => Eau_Filters::get_institution_options(),
                    'placeholder' => 'All Institutions',
                ),
                array(
                    'key' => 'registered_date',
                    'label' => 'Registration Date',
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

        // Modal: View Member
        $view_modal_config = array(
            'id' => 'eau-modal-view',
            'title' => 'View Member',
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

        // Modal: Edit Member
        $edit_modal_config = array(
            'id' => 'eau-modal-edit',
            'title' => 'Edit Member',
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

        // Modal: Add Member
        $add_modal_config = array(
            'id' => 'eau-modal-add',
            'title' => 'Add New Member',
            'size' => 'large',
            'show_footer' => true,
            'footer_buttons' => array(
                array(
                    'label' => 'Cancel',
                    'class' => 'eau-btn-secondary',
                    'action' => 'close',
                ),
                array(
                    'label' => 'Create Member',
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
