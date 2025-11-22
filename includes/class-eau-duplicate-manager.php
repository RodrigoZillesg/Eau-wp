<?php
namespace EauSystem;

use EauSystem\Components\Eau_Access_Denied;

/**
 * Gerenciador de Duplicatas - Interface e Shortcode
 */
class Eau_Duplicate_Manager {

    /**
     * Registra o shortcode
     */
    public static function register_shortcode() {
        add_shortcode('eau_duplicate_manager', array(__CLASS__, 'render'));
    }

    /**
     * Renderiza a página de gerenciamento de duplicatas
     */
    public static function render($atts) {
        // Verifica se usuário está logado
        if (!is_user_logged_in()) {
            return Eau_Access_Denied::not_logged_in();
        }

        // Verifica se usuário é Admin ou Super Admin
        if (!current_user_can('manage_options')) {
            return Eau_Access_Denied::no_permission();
        }

        // Garante que as tabelas existem e atualiza estrutura se necessário
        if (!Eau_Duplicate_Database::tables_exist()) {
            Eau_Duplicate_Database::create_tables();
        } else {
            // Atualiza estrutura das tabelas (adiciona colunas novas se necessário)
            Eau_Duplicate_Database::create_tables();
        }

        // Enfileira assets
        self::enqueue_assets();

        // Busca informações do último scan
        $last_scan = Eau_Duplicate_Scanner::get_last_scan();

        ob_start();
        ?>
        <div class="eau-duplicate-manager-container">

            <!-- Header -->
            <div class="eau-welcome-section">
                <h1 class="eau-welcome-title">Duplicate Manager</h1>
                <p class="eau-welcome-description">Find and merge duplicate members using intelligent matching</p>
            </div>

            <!-- Scan Section -->
            <div class="eau-scan-section">
                <div class="eau-scan-controls">
                    <button id="eau-start-scan-btn" class="eau-btn eau-btn-primary">
                        <i data-lucide="scan"></i>
                        <span>Start New Scan</span>
                    </button>

                    <?php if ($last_scan): ?>
                        <div class="eau-scan-info">
                            <span class="eau-scan-info-label">Last scan:</span>
                            <span class="eau-scan-info-value">
                                <?php echo human_time_diff(strtotime($last_scan->scan_date), current_time('timestamp')) . ' ago'; ?>
                            </span>
                            <?php if ($last_scan->scan_status === 'completed'): ?>
                                <span class="eau-scan-info-label">Found:</span>
                                <span class="eau-scan-info-value">
                                    <?php echo $last_scan->duplicates_found; ?> potential duplicates
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Progress Bar (hidden initially) -->
                <div id="eau-scan-progress" class="eau-scan-progress" style="display: none;">
                    <div class="eau-progress-header">
                        <i data-lucide="loader-2" class="eau-spin"></i>
                        <span id="eau-progress-text">Starting scan...</span>
                    </div>
                    <div class="eau-progress-bar">
                        <div id="eau-progress-fill" class="eau-progress-fill" style="width: 0%;"></div>
                    </div>
                    <div id="eau-progress-stats" class="eau-progress-stats"></div>
                </div>
            </div>

            <!-- Filters and Sort -->
            <div class="eau-duplicate-filters">
                <div class="eau-filter-group">
                    <label class="eau-filter-label">Filter by match:</label>
                    <div class="eau-filter-buttons">
                        <button class="eau-filter-btn active" data-filter="all">All</button>
                        <button class="eau-filter-btn" data-filter="high">High (≥80%)</button>
                        <button class="eau-filter-btn" data-filter="medium">Medium (50-79%)</button>
                    </div>
                </div>

                <div class="eau-sort-group">
                    <label class="eau-filter-label">Sort by:</label>
                    <select id="eau-sort-select" class="eau-form-select">
                        <option value="score_desc">Similarity (High to Low)</option>
                        <option value="score_asc">Similarity (Low to High)</option>
                        <option value="date_desc">Date (Newest First)</option>
                    </select>
                </div>
            </div>

            <!-- Results Summary -->
            <div id="eau-results-summary" class="eau-results-summary" style="display: none;">
                <i data-lucide="alert-circle"></i>
                <span id="eau-results-text">Showing 0 of 0 potential duplicates</span>
            </div>

            <!-- Duplicate Pairs List -->
            <div id="eau-duplicates-container" class="eau-duplicates-container">
                <div class="eau-duplicates-empty">
                    <i data-lucide="users-2"></i>
                    <p>No duplicate pairs found. Click "Start New Scan" to analyze your members.</p>
                </div>
            </div>

            <!-- Pagination -->
            <div id="eau-pagination" class="eau-pagination" style="display: none;">
                <button id="eau-prev-page" class="eau-btn eau-btn-secondary" disabled>
                    <i data-lucide="chevron-left"></i>
                    Previous
                </button>
                <span id="eau-page-info" class="eau-page-info">Page 1 of 1</span>
                <button id="eau-next-page" class="eau-btn eau-btn-secondary" disabled>
                    Next
                    <i data-lucide="chevron-right"></i>
                </button>
            </div>

        </div>

        <!-- Modal de Merge -->
        <div id="eau-merge-modal-overlay" class="eau-modal-overlay" style="display: none;">
            <div class="eau-modal eau-merge-modal">
                <div class="eau-modal-header">
                    <h2 class="eau-modal-title">Merge Members</h2>
                    <button class="eau-modal-close" data-action="close-merge">
                        <i data-lucide="x"></i>
                    </button>
                </div>
                <div id="eau-merge-modal-body" class="eau-modal-body">
                    <!-- Conteúdo dinâmico -->
                </div>
                <div class="eau-modal-footer">
                    <button class="eau-btn eau-btn-secondary" data-action="close-merge">Cancel</button>
                    <button id="eau-confirm-merge-btn" class="eau-btn eau-btn-danger">
                        <i data-lucide="merge"></i>
                        Confirm Merge
                    </button>
                </div>
            </div>
        </div>

        <script>
            // Inicializa ícones Lucide
            (function() {
                function initLucideIcons() {
                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    } else {
                        setTimeout(initLucideIcons, 100);
                    }
                }

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
     * Enfileira CSS e JavaScript
     */
    private static function enqueue_assets() {
        // CSS
        wp_enqueue_style(
            'eau-components',
            EAU_SYSTEM_PLUGIN_URL . 'assets/css/eau-components.css',
            array(),
            EAU_SYSTEM_VERSION
        );

        wp_enqueue_style(
            'eau-duplicate-manager',
            EAU_SYSTEM_PLUGIN_URL . 'assets/css/eau-duplicate-manager.css',
            array('eau-components'),
            EAU_SYSTEM_VERSION
        );

        // JavaScript - Lucide Icons
        wp_enqueue_script(
            'lucide-icons',
            'https://unpkg.com/lucide@latest/dist/umd/lucide.min.js',
            array(),
            null,
            true
        );

        // JavaScript - jQuery
        wp_enqueue_script('jquery');

        // JavaScript - Notifications
        wp_enqueue_script(
            'eau-notifications',
            EAU_SYSTEM_PLUGIN_URL . 'assets/js/eau-notifications.js',
            array('jquery', 'lucide-icons'),
            EAU_SYSTEM_VERSION,
            true
        );

        // JavaScript - Duplicate Manager
        wp_enqueue_script(
            'eau-duplicate-manager',
            EAU_SYSTEM_PLUGIN_URL . 'assets/js/eau-duplicate-manager.js',
            array('jquery', 'eau-notifications'),
            EAU_SYSTEM_VERSION,
            true
        );

        // Localiza script
        wp_localize_script('eau-duplicate-manager', 'eauDuplicateData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('eau_duplicate_nonce'),
        ));
    }
}
