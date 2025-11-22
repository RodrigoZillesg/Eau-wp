<?php
namespace EauSystem;

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
            return self::render_access_denied_message(
                'Authentication Required',
                'You need to be logged in to access this page.',
                'login'
            );
        }

        // Verifica se usuário é Admin ou Super Admin
        if (!current_user_can('manage_options')) {
            return self::render_access_denied_message(
                'Access Denied',
                'You do not have sufficient permissions to access this page. Only Administrators and Super Administrators can manage duplicate members.',
                'dashboard'
            );
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
     * Renderiza mensagem visual de acesso negado
     */
    private static function render_access_denied_message($title, $message, $action = 'dashboard') {
        // Enfileira Lucide Icons
        wp_enqueue_script(
            'lucide-icons',
            'https://unpkg.com/lucide@latest/dist/umd/lucide.min.js',
            array(),
            null,
            true
        );

        // Define ícone baseado na ação
        $icon = ($action === 'login') ? 'log-in' : 'shield-alert';

        // Sempre direciona para login
        $button_text = 'Go to Login';
        $button_url = home_url('/login/');

        ob_start();
        ?>
        <style>
            .eau-access-denied-container {
                max-width: 600px !important;
                margin: 4rem auto !important;
                padding: 2rem !important;
                text-align: center !important;
            }

            .eau-access-denied-card {
                background: #ffffff !important;
                border: 1px solid #e5e7eb !important;
                border-radius: 16px !important;
                padding: 3rem 2rem !important;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1) !important;
            }

            .eau-access-denied-icon {
                width: 64px !important;
                height: 64px !important;
                margin: 0 auto 1.5rem !important;
                color: #dc2626 !important;
            }

            .eau-access-denied-title {
                font-size: 1.5rem !important;
                font-weight: 600 !important;
                color: #111827 !important;
                margin: 0 0 1rem 0 !important;
                line-height: 1.2 !important;
            }

            .eau-access-denied-message {
                font-size: 1rem !important;
                color: #6b7280 !important;
                line-height: 1.6 !important;
                margin: 0 0 2rem 0 !important;
            }

            .eau-access-denied-button {
                display: inline-flex !important;
                align-items: center !important;
                gap: 0.5rem !important;
                padding: 0.875rem 2rem !important;
                background: #2563eb !important;
                color: #ffffff !important;
                border: none !important;
                border-radius: 8px !important;
                font-size: 1rem !important;
                font-weight: 500 !important;
                text-decoration: none !important;
                cursor: pointer !important;
                transition: all 0.2s ease !important;
            }

            .eau-access-denied-button:hover {
                background: #1d4ed8 !important;
                color: #ffffff !important;
                text-decoration: none !important;
                transform: translateY(-1px) !important;
                box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3) !important;
            }

            .eau-access-denied-button i {
                width: 20px !important;
                height: 20px !important;
            }

            @media (max-width: 768px) {
                .eau-access-denied-container {
                    margin: 2rem auto !important;
                    padding: 1rem !important;
                }

                .eau-access-denied-card {
                    padding: 2rem 1.5rem !important;
                }

                .eau-access-denied-title {
                    font-size: 1.25rem !important;
                }

                .eau-access-denied-message {
                    font-size: 0.9375rem !important;
                }
            }
        </style>

        <div class="eau-access-denied-container">
            <div class="eau-access-denied-card">
                <i data-lucide="<?php echo esc_attr($icon); ?>" class="eau-access-denied-icon"></i>

                <h1 class="eau-access-denied-title"><?php echo esc_html($title); ?></h1>

                <p class="eau-access-denied-message"><?php echo esc_html($message); ?></p>

                <a href="<?php echo esc_url($button_url); ?>" class="eau-access-denied-button">
                    <i data-lucide="arrow-right"></i>
                    <?php echo esc_html($button_text); ?>
                </a>
            </div>
        </div>

        <script>
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
