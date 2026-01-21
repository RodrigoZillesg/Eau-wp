<?php
namespace EauSystem;

use EauSystem\Components\Eau_Stats_Cards;
use EauSystem\Components\Eau_Data_Table;
use EauSystem\Components\Eau_Pagination;
use EauSystem\Components\Eau_Filters;
use EauSystem\Components\Eau_Modal;
use EauSystem\Components\Eau_Access_Denied;

/**
 * OpenLearning Courses Management Page
 *
 * Permite que superAdmin e Admin configurem quais cursos estão visíveis
 * e quais são destaques (featured).
 *
 * Shortcode: [eau_openlearning_management]
 *
 * @since 1.43.0
 */
class Eau_OpenLearning_Management {

    /**
     * Registra o shortcode
     */
    public static function register_shortcode() {
        add_shortcode('eau_openlearning_management', array(__CLASS__, 'render_management_page'));
    }

    /**
     * Renderiza a página de gerenciamento
     *
     * @param array $atts Atributos do shortcode
     * @return string HTML da página
     */
    public static function render_management_page($atts) {
        // Verifica se usuário está logado
        if (!is_user_logged_in()) {
            return Eau_Access_Denied::not_logged_in();
        }

        // Verifica se usuário tem permissão (apenas superAdmin e Admin)
        if (!Eau_User_Institution_Helper::has_admin_access()) {
            return Eau_Access_Denied::no_permission();
        }

        // Carrega assets
        self::enqueue_assets();

        // Pega estatísticas
        $stats = self::get_courses_stats();

        ob_start();
        ?>
        <div class="eau-openlearning-management-container">

            <!-- Stats Cards -->
            <?php echo self::render_stats_cards($stats); ?>

            <!-- Page Header -->
            <div class="eau-page-header">
                <div class="eau-page-header-title">
                    <h1>OpenLearning Course Management</h1>
                    <p class="eau-page-header-subtitle">Configure which courses are visible to members and which are featured</p>
                </div>
                <div class="eau-page-header-actions">
                    <button class="eau-btn eau-btn-secondary" id="eau-sync-courses">
                        <i data-lucide="refresh-cw"></i>
                        Sync Courses
                    </button>
                    <button class="eau-btn eau-btn-secondary" id="eau-bulk-visible" style="display: none;">
                        <i data-lucide="eye"></i>
                        Set Visible
                    </button>
                    <button class="eau-btn eau-btn-secondary" id="eau-bulk-hidden" style="display: none;">
                        <i data-lucide="eye-off"></i>
                        Set Hidden
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
                        placeholder="Search by course title..."
                        id="eau-courses-search"
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
            <div id="eau-courses-pagination"></div>

            <!-- Modals -->
            <?php echo self::render_modals(); ?>

            <!-- Bulk Actions Bar (v1.72.5) - Floating bar that appears when items are selected -->
            <div class="eau-bulk-actions-bar" id="eau-bulk-actions-bar">
                <div class="eau-bulk-actions-info">
                    <span class="eau-bulk-actions-count" id="eau-bulk-actions-count">0</span>
                    <span class="eau-bulk-actions-label" id="eau-bulk-actions-label">courses selected</span>
                </div>
                <div class="eau-bulk-actions-buttons">
                    <!-- Visibility Toggle -->
                    <div class="eau-bulk-action-group">
                        <label class="eau-bulk-action-label">Visibility:</label>
                        <div class="eau-bulk-toggle-buttons">
                            <button class="eau-btn eau-btn-sm eau-btn-toggle" id="eau-bulk-set-visible" data-action="visibility" data-value="visible">
                                <i data-lucide="eye"></i> Visible
                            </button>
                            <button class="eau-btn eau-btn-sm eau-btn-toggle-off" id="eau-bulk-set-hidden" data-action="visibility" data-value="hidden">
                                <i data-lucide="eye-off"></i> Hidden
                            </button>
                        </div>
                    </div>

                    <!-- Featured Toggle -->
                    <div class="eau-bulk-action-group">
                        <label class="eau-bulk-action-label">Featured:</label>
                        <div class="eau-bulk-toggle-buttons">
                            <button class="eau-btn eau-btn-sm eau-btn-toggle" id="eau-bulk-set-featured" data-action="featured" data-value="1">
                                <i data-lucide="star"></i> Yes
                            </button>
                            <button class="eau-btn eau-btn-sm eau-btn-toggle-off" id="eau-bulk-set-not-featured" data-action="featured" data-value="0">
                                <i data-lucide="star-off"></i> No
                            </button>
                        </div>
                    </div>
                </div>
                <button class="eau-bulk-actions-close" id="eau-bulk-actions-close" title="Clear selection">
                    <i data-lucide="x"></i>
                </button>
            </div>

        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Pega estatísticas dos cursos
     *
     * @return array Estatísticas
     */
    private static function get_courses_stats() {
        global $wpdb;

        $post_type = Eau_OpenLearning_Post_Type::POST_TYPE;
        $meta_prefix = Eau_OpenLearning_Post_Type::META_PREFIX;

        // Total de cursos
        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'",
            $post_type
        ));

        // Cursos visíveis
        $visible = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = %s
            AND p.post_status = 'publish'
            AND pm.meta_key = %s
            AND (pm.meta_value = 'true' OR pm.meta_value = '1')",
            $post_type,
            $meta_prefix . 'is_visible'
        ));

        // Cursos em destaque
        $featured = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = %s
            AND p.post_status = 'publish'
            AND pm.meta_key = %s
            AND (pm.meta_value = 'true' OR pm.meta_value = '1')",
            $post_type,
            $meta_prefix . 'is_featured'
        ));

        // Cursos gratuitos
        $free = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = %s
            AND p.post_status = 'publish'
            AND pm.meta_key = %s
            AND (pm.meta_value = '0' OR pm.meta_value = '' OR pm.meta_value IS NULL)",
            $post_type,
            $meta_prefix . 'price'
        ));

        return array(
            'total' => intval($total),
            'visible' => intval($visible),
            'featured' => intval($featured),
            'free' => intval($free),
        );
    }

    /**
     * Renderiza os cards de estatísticas
     *
     * @param array $stats Estatísticas dos cursos
     * @return string HTML dos cards
     */
    private static function render_stats_cards($stats) {
        $cards_data = array(
            array(
                'title' => 'Total Courses',
                'number' => $stats['total'],
                'icon' => 'book-open',
                'color' => 'blue',
            ),
            array(
                'title' => 'Visible Courses',
                'number' => $stats['visible'],
                'icon' => 'eye',
                'color' => 'green',
            ),
            array(
                'title' => 'Featured Courses',
                'number' => $stats['featured'],
                'icon' => 'star',
                'color' => 'orange',
            ),
            array(
                'title' => 'Free Courses',
                'number' => $stats['free'],
                'icon' => 'gift',
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
            'id' => 'courses-filters',
            'filters' => array(
                array(
                    'key' => 'visibility',
                    'label' => 'Visibility',
                    'type' => 'select',
                    'options' => array(
                        'visible' => 'Visible',
                        'hidden' => 'Hidden',
                    ),
                    'placeholder' => 'All'
                ),
                array(
                    'key' => 'featured',
                    'label' => 'Featured Status',
                    'type' => 'select',
                    'options' => array(
                        'featured' => 'Featured',
                        'not_featured' => 'Not Featured',
                    ),
                    'placeholder' => 'All'
                ),
                array(
                    'key' => 'price',
                    'label' => 'Price',
                    'type' => 'select',
                    'options' => array(
                        'free' => 'Free',
                        'paid' => 'Paid',
                    ),
                    'placeholder' => 'All'
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
                'key' => 'course',
                'label' => 'Course',
                'sortable' => true,
            ),
            array(
                'key' => 'price',
                'label' => 'Price',
                'sortable' => true,
            ),
            array(
                'key' => 'visibility',
                'label' => 'Visibility',
                'sortable' => true,
            ),
            array(
                'key' => 'featured',
                'label' => 'Featured',
                'sortable' => true,
            ),
            array(
                'key' => 'synced',
                'label' => 'Last Synced',
                'sortable' => true,
            ),
        );

        $table = new Eau_Data_Table(array(
            'id' => 'courses-table',
            'columns' => $columns,
            'loading_rows' => 20,
        ));

        return $table->render();
    }

    /**
     * Renderiza os modais
     *
     * @return string HTML dos modais
     */
    private static function render_modals() {
        $html = '';

        // Modal Sync Progress (único modal necessário)
        $sync_modal = new Eau_Modal(array(
            'id' => 'eau-modal-sync',
            'title' => 'Syncing Courses',
            'size' => 'small',
            'show_footer' => false,
        ));
        $html .= $sync_modal->render();

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
            'eau-openlearning-management',
            EAU_SYSTEM_PLUGIN_URL . 'assets/css/eau-openlearning-management.css',
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

        // JS - OpenLearning Management
        wp_enqueue_script(
            'eau-openlearning-management',
            EAU_SYSTEM_PLUGIN_URL . 'assets/js/eau-openlearning-management.js',
            array('jquery', 'eau-notifications', 'lucide-icons'),
            EAU_SYSTEM_VERSION,
            true
        );

        // JS - OpenLearning SSO (for course access buttons)
        wp_enqueue_script(
            'eau-openlearning',
            EAU_SYSTEM_PLUGIN_URL . 'assets/js/eau-openlearning.js',
            array('jquery'),
            EAU_SYSTEM_VERSION,
            true
        );

        // Localiza script para management
        wp_localize_script('eau-openlearning-management', 'eauOpenLearningMgmtData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('eau_openlearning_management_nonce'),
            'isSuperAdmin' => Eau_User_Institution_Helper::is_super_admin(),
        ));

        // Localiza script para SSO
        wp_localize_script('eau-openlearning', 'eauOpenLearning', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('eau_openlearning_nonce'),
            'i18n' => array(
                'launching' => __('Opening OpenLearning...', 'eau-system'),
                'launchError' => __('Failed to open course', 'eau-system'),
            ),
        ));
    }
}
