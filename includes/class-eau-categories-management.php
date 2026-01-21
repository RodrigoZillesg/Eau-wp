<?php
namespace EauSystem;

use EauSystem\Components\Eau_Stats_Cards;
use EauSystem\Components\Eau_Data_Table;
use EauSystem\Components\Eau_Pagination;
use EauSystem\Components\Eau_Modal;
use EauSystem\Components\Eau_Access_Denied;

/**
 * Categories Management Page
 *
 * Shortcode: [eau_categories_management]
 */
class Eau_Categories_Management {

    /**
     * Registra o shortcode
     */
    public static function register_shortcode() {
        add_shortcode('eau_categories_management', array(__CLASS__, 'render_categories_management'));
    }

    /**
     * Renderiza a página de Categories Management
     *
     * @param array $atts Atributos do shortcode
     * @return string HTML da página
     */
    public static function render_categories_management($atts) {
        // Verifica se usuário está logado
        if (!is_user_logged_in()) {
            return Eau_Access_Denied::not_logged_in();
        }

        // Verifica se usuário é Admin ou Super Admin
        if (!current_user_can('manage_options')) {
            return Eau_Access_Denied::no_permission();
        }

        // Carrega assets
        self::enqueue_assets();

        // Pega estatísticas
        $stats = self::get_categories_stats();

        ob_start();
        ?>
        <div class="eau-categories-management-container">

            <!-- Stats Cards -->
            <?php echo self::render_stats_cards($stats); ?>

            <!-- Page Header -->
            <div class="eau-page-header">
                <div class="eau-page-header-title">
                    <h1>Activity Categories Management</h1>
                    <p class="eau-page-header-subtitle">Configure categories and points per hour for CPD activities</p>
                </div>
                <div class="eau-page-header-actions">
                    <button class="eau-btn eau-btn-ghost" id="eau-import-categories" title="Import Categories">
                        <i data-lucide="upload"></i>
                        Import
                    </button>
                    <button class="eau-btn eau-btn-ghost" id="eau-export-categories" title="Export Categories">
                        <i data-lucide="download"></i>
                        Export
                    </button>
                    <button class="eau-btn eau-btn-secondary" id="eau-refresh-categories">
                        <i data-lucide="refresh-cw"></i>
                        Refresh from Activities
                    </button>
                    <button class="eau-btn eau-btn-primary" id="eau-add-category">
                        <i data-lucide="plus"></i>
                        Add Category
                    </button>
                </div>
            </div>

            <!-- Search Bar -->
            <div class="eau-search-filters-bar">
                <div class="eau-search-wrapper">
                    <i data-lucide="search"></i>
                    <input
                        type="text"
                        class="eau-search-input"
                        placeholder="Search by category name or ID..."
                        id="eau-categories-search"
                    >
                </div>
            </div>

            <!-- Data Table -->
            <?php echo self::render_data_table(); ?>

            <!-- Pagination -->
            <div id="eau-categories-pagination"></div>

            <!-- Modals -->
            <?php echo self::render_modals(); ?>

            <!-- Bulk Actions Bar (v1.72.5) - Floating bar that appears when items are selected -->
            <div class="eau-bulk-actions-bar" id="eau-bulk-actions-bar">
                <div class="eau-bulk-actions-info">
                    <span class="eau-bulk-actions-count" id="eau-bulk-actions-count">0</span>
                    <span class="eau-bulk-actions-label" id="eau-bulk-actions-label">categories selected</span>
                </div>
                <div class="eau-bulk-actions-buttons">
                    <!-- Export Selected -->
                    <button class="eau-btn eau-btn-sm eau-btn-secondary" id="eau-bulk-export-selected">
                        <i data-lucide="download"></i>
                        Export Selected
                    </button>

                    <!-- Delete Selected -->
                    <button class="eau-btn eau-btn-sm eau-btn-danger" id="eau-bulk-delete-categories">
                        <i data-lucide="trash-2"></i>
                        Delete Selected
                    </button>
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
     * Pega estatísticas das categorias
     *
     * @return array Estatísticas
     */
    private static function get_categories_stats() {
        global $wpdb;

        $table_name = $wpdb->prefix . Eau_Categories_Database::TABLE_NAME;

        // Total de categorias
        $total = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");

        // Categorias com pontos configurados
        $configured = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE points_per_hour > 0");

        // Categorias sem pontos
        $not_configured = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE points_per_hour = 0");

        // Média de pontos por hora
        $avg_points = $wpdb->get_var("SELECT AVG(points_per_hour) FROM $table_name WHERE points_per_hour > 0");

        return array(
            'total' => (int) $total,
            'configured' => (int) $configured,
            'not_configured' => (int) $not_configured,
            'avg_points' => (float) $avg_points,
        );
    }

    /**
     * Renderiza os cards de estatísticas
     *
     * @param array $stats Estatísticas das categorias
     * @return string HTML dos cards
     */
    private static function render_stats_cards($stats) {
        $cards_data = array(
            array(
                'title' => 'Total Categories',
                'number' => $stats['total'],
                'icon' => 'folder',
                'color' => 'blue',
            ),
            array(
                'title' => 'Configured',
                'number' => $stats['configured'],
                'icon' => 'check-circle',
                'color' => 'green',
            ),
            array(
                'title' => 'Not Configured',
                'number' => $stats['not_configured'],
                'icon' => 'alert-circle',
                'color' => 'orange',
            ),
            array(
                'title' => 'Avg Points/Hour',
                'number' => $stats['avg_points'] ? number_format_i18n($stats['avg_points'], 2) : '0.00',
                'icon' => 'trending-up',
                'color' => 'purple',
            ),
        );

        $stats_cards = new Eau_Stats_Cards($cards_data);
        return $stats_cards->render();
    }

    /**
     * Renderiza a tabela de dados
     *
     * @return string HTML da tabela
     */
    private static function render_data_table() {
        $columns = array(
            array(
                'key' => 'category_serial',
                'label' => 'Category ID',
                'sortable' => true,
            ),
            array(
                'key' => 'category_name',
                'label' => 'Category Name',
                'sortable' => true,
            ),
            array(
                'key' => 'points_per_hour',
                'label' => 'Points/Hour',
                'sortable' => true,
            ),
            array(
                'key' => 'updated_at',
                'label' => 'Last Updated',
                'sortable' => true,
            ),
        );

        $table = new Eau_Data_Table(array(
            'id' => 'categories-table',
            'columns' => $columns,
            'loading_rows' => 20,
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
            'title' => 'View Category',
            'size' => 'large',
        ));
        $html .= $view_modal->render();

        // Modal Edit
        $edit_modal = new Eau_Modal(array(
            'id' => 'eau-modal-edit',
            'title' => 'Edit Category',
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
        ));
        $html .= $edit_modal->render();

        // Modal Add
        $add_modal = new Eau_Modal(array(
            'id' => 'eau-modal-add',
            'title' => 'Add Category',
            'size' => 'large',
            'show_footer' => true,
            'footer_buttons' => array(
                array(
                    'label' => 'Cancel',
                    'class' => 'eau-btn-secondary',
                    'action' => 'close',
                ),
                array(
                    'label' => 'Create Category',
                    'class' => 'eau-btn-primary',
                    'action' => 'create',
                ),
            ),
        ));
        $html .= $add_modal->render();

        // Modal Import (v1.55.5)
        $html .= self::render_import_modal();

        return $html;
    }

    /**
     * Renderiza o modal de importação
     *
     * @since 1.55.5
     * @return string HTML do modal
     */
    private static function render_import_modal() {
        ob_start();
        ?>
        <div class="eau-modal-overlay" id="eau-modal-import-overlay" style="display: none;">
            <div class="eau-modal eau-modal-lg">
                <div class="eau-modal-header">
                    <h2 class="eau-modal-title">
                        <i data-lucide="upload"></i>
                        Import Categories
                    </h2>
                    <button type="button" class="eau-modal-close" data-modal-close>&times;</button>
                </div>
                <div class="eau-modal-body">
                    <!-- Step 1: Upload -->
                    <div id="import-step-upload" class="import-step">
                        <p class="eau-description">
                            Upload a JSON file previously exported from this system.
                            Categories will be matched by Category ID (serial).
                        </p>

                        <div class="eau-info-box" style="background: #f0f9ff; border: 1px solid #0ea5e9; border-radius: 8px; padding: 12px; margin-bottom: 20px;">
                            <div style="display: flex; align-items: flex-start; gap: 10px;">
                                <i data-lucide="info" style="width: 20px; height: 20px; color: #0ea5e9; flex-shrink: 0;"></i>
                                <div>
                                    <strong>How it works:</strong>
                                    <ul style="margin: 8px 0 0 0; padding-left: 20px; font-size: 13px; color: #666;">
                                        <li>Existing categories (same ID) will be <strong>updated</strong></li>
                                        <li>New categories will be <strong>created</strong></li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <form id="import-categories-form" enctype="multipart/form-data">
                            <div class="eau-form-group">
                                <label class="eau-form-label">JSON File</label>
                                <input type="file" name="json_file" id="import-json-file" accept=".json" required class="eau-form-input">
                                <p class="eau-form-hint">Maximum file size: 5MB</p>
                            </div>

                            <div class="eau-form-group">
                                <label class="eau-checkbox-label">
                                    <input type="checkbox" name="skip_existing" id="import-skip-existing">
                                    Skip existing categories (only add new ones)
                                </label>
                            </div>
                        </form>
                    </div>

                    <!-- Step 2: Preview -->
                    <div id="import-step-preview" class="import-step" style="display: none;">
                        <div id="import-preview-stats" class="eau-preview-stats" style="display: flex; gap: 15px; margin-bottom: 20px;">
                            <!-- Stats loaded via JS -->
                        </div>

                        <h4>Preview (first 10 categories):</h4>
                        <div class="eau-table-wrapper" style="max-height: 300px; overflow-y: auto; border: 1px solid #e5e7eb; border-radius: 8px;">
                            <table class="eau-data-table" id="import-preview-table">
                                <thead>
                                    <tr>
                                        <th>Category ID</th>
                                        <th>Category Name</th>
                                        <th>Points/Hour</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Preview rows loaded via JS -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Step 3: Result -->
                    <div id="import-step-result" class="import-step" style="display: none;">
                        <div id="import-result-content">
                            <!-- Result loaded via JS -->
                        </div>
                    </div>
                </div>
                <div class="eau-modal-footer">
                    <button type="button" class="eau-btn eau-btn-secondary" id="import-btn-cancel">Cancel</button>
                    <button type="button" class="eau-btn eau-btn-secondary" id="import-btn-back" style="display: none;">Back</button>
                    <button type="button" class="eau-btn eau-btn-primary" id="import-btn-analyze">
                        <i data-lucide="search"></i>
                        Analyze File
                    </button>
                    <button type="button" class="eau-btn eau-btn-primary" id="import-btn-execute" style="display: none;">
                        <i data-lucide="upload"></i>
                        Import Categories
                    </button>
                    <button type="button" class="eau-btn eau-btn-primary" id="import-btn-close" style="display: none;">
                        <i data-lucide="check"></i>
                        Close
                    </button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
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
            'eau-categories-management',
            EAU_SYSTEM_PLUGIN_URL . 'assets/css/eau-categories-management.css',
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

        // JS - Categories Management
        wp_enqueue_script(
            'eau-categories-management',
            EAU_SYSTEM_PLUGIN_URL . 'assets/js/eau-categories-management.js',
            array('jquery', 'eau-notifications', 'lucide-icons'),
            EAU_SYSTEM_VERSION,
            true
        );

        // Localiza script
        wp_localize_script('eau-categories-management', 'eauCategoriesData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('eau_categories_nonce'),
        ));
    }
}
