<?php
namespace EauSystem;

use EauSystem\Components\Eau_Data_Table;
use EauSystem\Components\Eau_Pagination;
use EauSystem\Components\Eau_Access_Denied;
use EauSystem\Components\Eau_Modal;
use EauSystem\Components\Eau_Wysiwyg;
use EauSystem\Components\Eau_Media_Upload;

/**
 * My CPDs Page
 *
 * Página para membros visualizarem suas próprias atividades CPD
 * e seu progresso anual em relação à meta de 20 pontos.
 *
 * Shortcode: [eau_my_cpds]
 *
 * @since 1.37.0
 * @since 1.38.0 Adicionado formulário de Add Activity
 */
class Eau_My_Cpds {

    /**
     * Meta de pontos CPD anual
     */
    const CPD_ANNUAL_GOAL = 20;

    /**
     * Registra o shortcode
     */
    public static function register_shortcode() {
        add_shortcode('eau_my_cpds', array(__CLASS__, 'render_my_cpds'));
    }

    /**
     * Renderiza a página My CPDs
     *
     * @param array $atts Atributos do shortcode
     * @return string HTML da página
     */
    public static function render_my_cpds($atts) {
        // Verifica se usuário está logado
        if (!is_user_logged_in()) {
            return Eau_Access_Denied::not_logged_in();
        }

        // Verifica se membership está ativo (v1.51.46)
        if (!Eau_User_Institution_Helper::is_membership_active()) {
            return Eau_Access_Denied::membership_inactive();
        }

        // Carrega assets
        self::enqueue_assets();

        // Pega dados do usuário
        $user_id = get_current_user_id();
        $current_year = date('Y');

        // Pega progresso CPD do ano atual
        $progress = self::get_user_cpd_progress($user_id, $current_year);

        // Anos disponíveis para filtro
        $available_years = self::get_available_years($user_id);

        ob_start();
        ?>
        <div class="eau-my-cpds-container">

            <!-- CPD Progress Section -->
            <?php echo self::render_progress_section($progress, $current_year); ?>

            <!-- Page Header -->
            <div class="eau-page-header">
                <div class="eau-page-header-title">
                    <h2>My CPD Activities</h2>
                    <p class="eau-page-header-subtitle">View all your CPD activities and points earned</p>
                </div>
                <div class="eau-page-header-actions">
                    <button type="button" class="eau-btn eau-btn-primary" id="eau-add-activity-btn">
                        <i data-lucide="plus"></i>
                        Add Activity
                    </button>
                </div>
            </div>

            <!-- Search and Filters -->
            <?php $categories = self::get_categories(); ?>
            <div class="eau-search-filters-bar">
                <div class="eau-search-wrapper">
                    <i data-lucide="search"></i>
                    <input
                        type="text"
                        class="eau-search-input"
                        placeholder="Search activities..."
                        id="eau-my-cpds-search"
                    >
                </div>
                <div class="eau-filters-group">
                    <select id="eau-category-select" class="eau-form-select">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo esc_attr($cat['category_serial']); ?>">
                                <?php echo esc_html($cat['category_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select id="eau-year-select" class="eau-form-select">
                        <?php foreach ($available_years as $year): ?>
                            <option value="<?php echo esc_attr($year); ?>" <?php selected($year, $current_year); ?>>
                                <?php echo esc_html($year); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Data Table -->
            <?php echo self::render_data_table(); ?>

            <!-- Pagination -->
            <div id="eau-my-cpds-pagination"></div>

            <!-- Add Activity Modal -->
            <?php echo self::render_add_activity_modal(); ?>

            <!-- Edit Activity Modal -->
            <?php echo self::render_edit_activity_modal(); ?>

        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renderiza o modal de Add Activity
     *
     * @return string HTML do modal
     */
    private static function render_add_activity_modal() {
        // Pega categorias disponíveis
        $categories = self::get_categories();

        ob_start();
        ?>
        <div class="eau-modal-overlay" id="add-activity-modal-overlay" style="display: none;">
            <div class="eau-modal eau-modal-large" id="add-activity-modal">

                <!-- Modal Header -->
                <div class="eau-modal-header">
                    <h2 class="eau-modal-title">
                        <i data-lucide="plus-circle"></i>
                        Add New CPD Activity
                    </h2>
                    <button class="eau-modal-close" type="button" data-modal-action="close">
                        <i data-lucide="x"></i>
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="eau-modal-body">
                    <form id="eau-add-activity-form" class="eau-modal-form">
                        <div class="eau-form-grid">

                            <!-- Activity Name -->
                            <div class="eau-form-field eau-form-field-span-2">
                                <label class="eau-form-label" for="eau-activity-name">
                                    Activity Name <span class="eau-form-required">*</span>
                                </label>
                                <input
                                    type="text"
                                    class="eau-form-input"
                                    id="eau-activity-name"
                                    name="activity_name"
                                    placeholder="Enter activity name"
                                    required
                                >
                            </div>

                            <!-- Category -->
                            <div class="eau-form-field">
                                <label class="eau-form-label" for="eau-activity-category">
                                    Category <span class="eau-form-required">*</span>
                                </label>
                                <select
                                    class="eau-form-select"
                                    id="eau-activity-category"
                                    name="category_serial"
                                    required
                                >
                                    <option value="">Select category...</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option
                                            value="<?php echo esc_attr($cat['category_serial']); ?>"
                                            data-name="<?php echo esc_attr($cat['category_name']); ?>"
                                            data-points="<?php echo esc_attr($cat['points_per_hour']); ?>"
                                        >
                                            <?php echo esc_html($cat['category_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Hours -->
                            <div class="eau-form-field">
                                <label class="eau-form-label" for="eau-activity-hours">
                                    Hours <span class="eau-form-required">*</span>
                                </label>
                                <input
                                    type="number"
                                    class="eau-form-input"
                                    id="eau-activity-hours"
                                    name="hours"
                                    placeholder="0.0"
                                    step="0.5"
                                    min="0.5"
                                    required
                                >
                                <p class="eau-form-hint">Enter hours spent (e.g., 1.5 for 1h30min)</p>
                            </div>

                            <!-- Completion Date -->
                            <div class="eau-form-field">
                                <label class="eau-form-label" for="eau-activity-date">
                                    Completion Date <span class="eau-form-required">*</span>
                                </label>
                                <div class="eau-datepicker-wrapper">
                                    <input
                                        type="text"
                                        class="eau-form-input eau-datepicker-input"
                                        id="eau-activity-date"
                                        name="completed_date"
                                        placeholder="Select date"
                                        required
                                        readonly
                                    >
                                    <span class="eau-datepicker-icon">
                                        <i data-lucide="calendar"></i>
                                    </span>
                                </div>
                            </div>

                            <!-- Points Preview -->
                            <div class="eau-form-field">
                                <label class="eau-form-label">Estimated Points</label>
                                <div class="eau-points-preview">
                                    <span class="eau-points-preview-value" id="eau-points-preview">0.00</span>
                                    <span class="eau-points-preview-label">points</span>
                                </div>
                            </div>

                            <!-- Description -->
                            <div class="eau-form-field eau-form-field-span-2">
                                <label class="eau-form-label" for="eau-activity-description">
                                    Description
                                </label>
                                <?php echo Eau_Wysiwyg::field(
                                    'eau-activity-description',
                                    'description',
                                    '',
                                    array(
                                        'placeholder' => 'Describe your CPD activity...',
                                        'height' => 150,
                                        'toolbar' => 'basic'
                                    )
                                ); ?>
                            </div>

                            <!-- Proof/Evidence -->
                            <div class="eau-form-field eau-form-field-span-2">
                                <label class="eau-form-label" for="eau-activity-proof">
                                    Proof/Evidence (Optional)
                                </label>
                                <p class="eau-form-hint" style="margin-bottom: 0.5rem;">
                                    Upload a certificate, attendance statement, or provide a link to the event website
                                </p>
                                <?php echo Eau_Media_Upload::field(
                                    'eau-activity-proof',
                                    'proof',
                                    '',
                                    array(
                                        'type' => 'both',
                                        'placeholder' => 'Upload file or enter URL',
                                        'url_placeholder' => 'https://example.com/event',
                                        'allowed_types' => 'image/*,.pdf,application/pdf',
                                        'allowed_extensions' => 'jpg,jpeg,png,gif,pdf'
                                    )
                                ); ?>
                            </div>

                        </div>
                    </form>
                </div>

                <!-- Modal Footer -->
                <div class="eau-modal-footer">
                    <button type="button" class="eau-btn eau-btn-secondary" data-modal-action="close">
                        Cancel
                    </button>
                    <button type="button" class="eau-btn eau-btn-primary" id="eau-save-activity-btn">
                        <i data-lucide="save"></i>
                        Save Activity
                    </button>
                </div>

            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renderiza o modal de Edit Activity (somente categoria e prova)
     *
     * @return string HTML do modal
     */
    private static function render_edit_activity_modal() {
        // Pega categorias disponíveis
        $categories = self::get_categories();

        ob_start();
        ?>
        <div class="eau-modal-overlay" id="edit-activity-modal-overlay" style="display: none;">
            <div class="eau-modal eau-modal-medium" id="edit-activity-modal">

                <!-- Modal Header -->
                <div class="eau-modal-header">
                    <h2 class="eau-modal-title">
                        <i data-lucide="pencil"></i>
                        Edit Activity
                    </h2>
                    <button class="eau-modal-close" type="button" data-modal-action="close">
                        <i data-lucide="x"></i>
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="eau-modal-body">
                    <form id="eau-edit-activity-form" class="eau-modal-form">
                        <input type="hidden" id="eau-edit-activity-id" name="activity_id" value="">

                        <!-- Activity Title (readonly) -->
                        <div class="eau-form-field">
                            <label class="eau-form-label">Activity</label>
                            <div class="eau-form-readonly" id="eau-edit-activity-title"></div>
                        </div>

                        <!-- Category -->
                        <div class="eau-form-field">
                            <label class="eau-form-label" for="eau-edit-category">
                                Category <span class="eau-form-required">*</span>
                            </label>
                            <select
                                class="eau-form-select"
                                id="eau-edit-category"
                                name="category_serial"
                                required
                            >
                                <option value="">Select category...</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option
                                        value="<?php echo esc_attr($cat['category_serial']); ?>"
                                        data-name="<?php echo esc_attr($cat['category_name']); ?>"
                                        data-points="<?php echo esc_attr($cat['points_per_hour']); ?>"
                                    >
                                        <?php echo esc_html($cat['category_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Proof/Evidence -->
                        <div class="eau-form-field">
                            <label class="eau-form-label" for="eau-edit-proof">
                                Proof/Evidence (Optional)
                            </label>
                            <p class="eau-form-hint" style="margin-bottom: 0.5rem;">
                                Upload a certificate, attendance statement, or provide a link to the event website
                            </p>
                            <?php echo Eau_Media_Upload::field(
                                'eau-edit-proof',
                                'proof',
                                '',
                                array(
                                    'type' => 'both',
                                    'placeholder' => 'Upload file or enter URL',
                                    'url_placeholder' => 'https://example.com/event',
                                    'allowed_types' => 'image/*,.pdf,application/pdf',
                                    'allowed_extensions' => 'jpg,jpeg,png,gif,pdf'
                                )
                            ); ?>
                        </div>

                    </form>
                </div>

                <!-- Modal Footer -->
                <div class="eau-modal-footer">
                    <button type="button" class="eau-btn eau-btn-secondary" data-modal-action="close">
                        Cancel
                    </button>
                    <button type="button" class="eau-btn eau-btn-primary" id="eau-update-activity-btn">
                        <i data-lucide="save"></i>
                        Save Changes
                    </button>
                </div>

            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Pega categorias disponíveis do banco
     *
     * @return array Lista de categorias
     */
    private static function get_categories() {
        global $wpdb;

        $table = $wpdb->prefix . 'eau_activity_categories';

        $categories = $wpdb->get_results(
            "SELECT category_serial, category_name, points_per_hour
            FROM {$table}
            ORDER BY category_name ASC",
            ARRAY_A
        );

        return $categories ?: array();
    }

    /**
     * Renderiza a seção de progresso CPD
     *
     * @param array $progress Dados do progresso
     * @param int $year Ano
     * @return string HTML
     */
    private static function render_progress_section($progress, $year) {
        $percentage = min(100, ($progress['total_points'] / self::CPD_ANNUAL_GOAL) * 100);
        $status_class = $percentage >= 100 ? 'eau-progress-complete' : ($percentage >= 50 ? 'eau-progress-halfway' : 'eau-progress-starting');

        ob_start();
        ?>
        <div class="eau-cpd-progress-section">
            <div class="eau-cpd-progress-header">
                <div class="eau-cpd-progress-title">
                    <i data-lucide="trending-up"></i>
                    <h2>My CPD Progress</h2>
                </div>
                <div class="eau-cpd-progress-year" id="eau-progress-year-display">
                    <i data-lucide="calendar"></i>
                    <span><?php echo esc_html($year); ?></span>
                </div>
            </div>

            <div class="eau-cpd-progress-card">
                <div class="eau-cpd-progress-bar-container">
                    <div class="eau-cpd-progress-bar <?php echo esc_attr($status_class); ?>" id="eau-progress-bar" style="width: <?php echo esc_attr($percentage); ?>%;">
                        <span class="eau-cpd-progress-bar-text" id="eau-progress-bar-text">
                            <?php echo esc_html(number_format($percentage, 1)); ?>%
                        </span>
                    </div>
                </div>

                <div class="eau-cpd-progress-stats">
                    <div class="eau-cpd-stat">
                        <i data-lucide="star" class="eau-cpd-stat-icon"></i>
                        <span class="eau-cpd-stat-value" id="eau-points-earned"><?php echo esc_html(number_format($progress['total_points'], 2)); ?></span>
                        <span class="eau-cpd-stat-label">Points Earned</span>
                    </div>
                    <div class="eau-cpd-stat-divider"></div>
                    <div class="eau-cpd-stat">
                        <i data-lucide="target" class="eau-cpd-stat-icon"></i>
                        <span class="eau-cpd-stat-value"><?php echo esc_html(self::CPD_ANNUAL_GOAL); ?></span>
                        <span class="eau-cpd-stat-label">Annual Goal</span>
                    </div>
                    <div class="eau-cpd-stat-divider"></div>
                    <div class="eau-cpd-stat">
                        <i data-lucide="hourglass" class="eau-cpd-stat-icon"></i>
                        <span class="eau-cpd-stat-value" id="eau-points-remaining"><?php echo esc_html(number_format(max(0, self::CPD_ANNUAL_GOAL - $progress['total_points']), 2)); ?></span>
                        <span class="eau-cpd-stat-label">Points Remaining</span>
                    </div>
                    <div class="eau-cpd-stat-divider"></div>
                    <div class="eau-cpd-stat">
                        <i data-lucide="clipboard-list" class="eau-cpd-stat-icon"></i>
                        <span class="eau-cpd-stat-value" id="eau-activities-count"><?php echo esc_html($progress['activities_count']); ?></span>
                        <span class="eau-cpd-stat-label">Activities</span>
                    </div>
                </div>
            </div>

            <?php if ($percentage >= 100): ?>
                <div class="eau-cpd-goal-reached">
                    <i data-lucide="award"></i>
                    <span>Congratulations! You've reached your annual CPD goal!</span>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
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
            array(
                'key' => 'action',
                'label' => '',
                'sortable' => false,
                'width' => '50px',
            ),
        );

        $table = new Eau_Data_Table(array(
            'id' => 'my-cpds-table',
            'columns' => $columns,
            'loading_rows' => 10,
            'selectable' => false,
            'actions' => array(),
        ));

        return $table->render();
    }

    /**
     * Pega o progresso CPD do usuário para um ano específico
     *
     * @param int $user_id ID do usuário
     * @param int $year Ano
     * @return array Dados do progresso
     */
    public static function get_user_cpd_progress($user_id, $year) {
        global $wpdb;

        // Pega o mem_userid do usuário
        $mem_userid = get_user_meta($user_id, 'mem_userid', true);

        if (empty($mem_userid)) {
            return array(
                'total_points' => 0,
                'activities_count' => 0,
                'verified_count' => 0,
                'pending_count' => 0,
            );
        }

        $table_categories = $wpdb->prefix . 'eau_activity_categories';
        $start_date = $year . '-01-01 00:00:00';
        $end_date = $year . '-12-31 23:59:59';

        // Busca total de pontos (todas as atividades - verificadas e pendentes)
        $total_points = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(
                CAST(pm_hours.meta_value AS DECIMAL(10,2)) *
                COALESCE(cat.points_per_hour, 1)
            )
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm_user ON p.ID = pm_user.post_id
            INNER JOIN {$wpdb->postmeta} pm_hours ON p.ID = pm_hours.post_id
            LEFT JOIN {$wpdb->postmeta} pm_cat ON p.ID = pm_cat.post_id AND pm_cat.meta_key = 'act_category_serial'
            LEFT JOIN {$table_categories} cat ON cat.category_serial = pm_cat.meta_value
            WHERE p.post_type = 'activitie'
            AND p.post_status = 'publish'
            AND pm_user.meta_key = 'act_user_id'
            AND pm_user.meta_value = %s
            AND pm_hours.meta_key = 'act_hours_of_pd_anything_below_60_minutes_can_be_entered_as_a_decimal_e_g_30_mins_0_5'
            AND p.post_date >= %s
            AND p.post_date <= %s",
            $mem_userid,
            $start_date,
            $end_date
        ));

        // Conta atividades verificadas
        $verified_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT p.ID)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm_user ON p.ID = pm_user.post_id
            INNER JOIN {$wpdb->postmeta} pm_verified ON p.ID = pm_verified.post_id
            WHERE p.post_type = 'activitie'
            AND p.post_status = 'publish'
            AND pm_user.meta_key = 'act_user_id'
            AND pm_user.meta_value = %s
            AND pm_verified.meta_key = 'act_verified'
            AND pm_verified.meta_value = '1'
            AND p.post_date >= %s
            AND p.post_date <= %s",
            $mem_userid,
            $start_date,
            $end_date
        ));

        // Conta atividades pendentes
        $pending_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT p.ID)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm_user ON p.ID = pm_user.post_id
            LEFT JOIN {$wpdb->postmeta} pm_verified ON p.ID = pm_verified.post_id AND pm_verified.meta_key = 'act_verified'
            WHERE p.post_type = 'activitie'
            AND p.post_status = 'publish'
            AND pm_user.meta_key = 'act_user_id'
            AND pm_user.meta_value = %s
            AND (pm_verified.meta_value IS NULL OR pm_verified.meta_value != '1')
            AND p.post_date >= %s
            AND p.post_date <= %s",
            $mem_userid,
            $start_date,
            $end_date
        ));

        return array(
            'total_points' => (float) ($total_points ?: 0),
            'activities_count' => (int) $verified_count + (int) $pending_count,
            'verified_count' => (int) $verified_count,
            'pending_count' => (int) $pending_count,
        );
    }

    /**
     * Pega os anos disponíveis com atividades para o usuário
     *
     * @param int $user_id ID do usuário
     * @return array Anos disponíveis
     */
    private static function get_available_years($user_id) {
        global $wpdb;

        $mem_userid = get_user_meta($user_id, 'mem_userid', true);

        if (empty($mem_userid)) {
            return array(date('Y'));
        }

        $years = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT YEAR(p.post_date) as year
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'activitie'
            AND p.post_status = 'publish'
            AND pm.meta_key = 'act_user_id'
            AND pm.meta_value = %s
            ORDER BY year DESC",
            $mem_userid
        ));

        // Garante que o ano atual está sempre disponível
        $current_year = date('Y');
        if (!in_array($current_year, $years)) {
            array_unshift($years, $current_year);
        }

        return $years;
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
            'eau-my-cpds',
            EAU_SYSTEM_PLUGIN_URL . 'assets/css/eau-my-cpds.css',
            array('eau-components'),
            EAU_SYSTEM_VERSION
        );

        // Quill.js CSS (Wysiwyg)
        wp_enqueue_style(
            'quill-snow',
            'https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css',
            array(),
            '2.0.3'
        );

        // Flatpickr CSS (Datepicker)
        wp_enqueue_style(
            'flatpickr',
            'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css',
            array(),
            '4.6.13'
        );

        // JS - Notifications library
        wp_enqueue_script(
            'eau-notifications',
            EAU_SYSTEM_PLUGIN_URL . 'assets/js/eau-notifications.js',
            array('jquery', 'lucide-icons'),
            EAU_SYSTEM_VERSION,
            true
        );

        // Quill.js (Wysiwyg)
        wp_enqueue_script(
            'quill',
            'https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js',
            array(),
            '2.0.3',
            true
        );

        // Flatpickr (Datepicker)
        wp_enqueue_script(
            'flatpickr',
            'https://cdn.jsdelivr.net/npm/flatpickr',
            array(),
            '4.6.13',
            true
        );

        // JS - My CPDs
        wp_enqueue_script(
            'eau-my-cpds',
            EAU_SYSTEM_PLUGIN_URL . 'assets/js/eau-my-cpds.js',
            array('jquery', 'eau-notifications', 'lucide-icons', 'quill', 'flatpickr'),
            EAU_SYSTEM_VERSION,
            true
        );

        // Localiza script
        wp_localize_script('eau-my-cpds', 'eauMyCpdsData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('eau_my_cpds_nonce'),
            'currentYear' => date('Y'),
            'annualGoal' => self::CPD_ANNUAL_GOAL,
        ));
    }
}
