<?php
namespace EauSystem\Components;

/**
 * Componente reutilizável de Filters Panel
 *
 * Uso:
 * $filters = new Eau_Filters($config);
 * echo $filters->render();
 */
class Eau_Filters {

    private $config;

    /**
     * Constructor
     *
     * @param array $config Configuração dos filtros:
     *  [
     *    'id' => 'members-filters',
     *    'filters' => [
     *        [
     *            'key' => 'status',
     *            'label' => 'Status',
     *            'type' => 'select',
     *            'options' => [ ... ],
     *            'placeholder' => 'All Status'
     *        ],
     *        ...
     *    ]
     *  ]
     */
    public function __construct($config = array()) {
        $defaults = array(
            'id' => 'eau-filters',
            'filters' => array(),
            'collapsible' => true,
            'show_clear' => true,
        );

        $this->config = wp_parse_args($config, $defaults);
    }

    /**
     * Renderiza o painel de filtros
     *
     * @return string HTML do painel de filtros
     */
    public function render() {
        ob_start();
        ?>
        <div class="eau-filters-panel" id="<?php echo esc_attr($this->config['id']); ?>" style="display: none;">
            <div class="eau-filters-grid">
                <?php foreach ($this->config['filters'] as $filter) : ?>
                    <?php echo $this->render_filter($filter); ?>
                <?php endforeach; ?>
            </div>

            <?php if ($this->config['show_clear']) : ?>
                <div class="eau-filters-actions">
                    <button class="eau-btn eau-btn-secondary eau-filters-clear" type="button">
                        <i data-lucide="x"></i>
                        Clear Filters
                    </button>
                    <button class="eau-btn eau-btn-primary eau-filters-apply" type="button">
                        <i data-lucide="check"></i>
                        Apply Filters
                    </button>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renderiza um filtro individual
     *
     * @param array $filter Configuração do filtro
     * @return string HTML do filtro
     */
    private function render_filter($filter) {
        $type = isset($filter['type']) ? $filter['type'] : 'select';

        switch ($type) {
            case 'select':
                return $this->render_select_filter($filter);
            case 'date':
                return $this->render_date_filter($filter);
            case 'date_range':
                return $this->render_date_range_filter($filter);
            case 'text':
                return $this->render_text_filter($filter);
            default:
                return '';
        }
    }

    /**
     * Renderiza um filtro do tipo select
     *
     * @param array $filter Configuração do filtro
     * @return string HTML do select
     */
    private function render_select_filter($filter) {
        $key = isset($filter['key']) ? $filter['key'] : '';
        $label = isset($filter['label']) ? $filter['label'] : '';
        $options = isset($filter['options']) ? $filter['options'] : array();
        $placeholder = isset($filter['placeholder']) ? $filter['placeholder'] : 'Select...';

        ob_start();
        ?>
        <div class="eau-filter-item">
            <label class="eau-filter-label" for="eau-filter-<?php echo esc_attr($key); ?>">
                <?php echo esc_html($label); ?>
            </label>
            <select
                class="eau-filter-select"
                id="eau-filter-<?php echo esc_attr($key); ?>"
                name="<?php echo esc_attr($key); ?>"
                data-filter="<?php echo esc_attr($key); ?>"
            >
                <option value=""><?php echo esc_html($placeholder); ?></option>
                <?php foreach ($options as $value => $text) : ?>
                    <option value="<?php echo esc_attr($value); ?>">
                        <?php echo esc_html($text); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renderiza um filtro do tipo date
     *
     * @param array $filter Configuração do filtro
     * @return string HTML do date input
     */
    private function render_date_filter($filter) {
        $key = isset($filter['key']) ? $filter['key'] : '';
        $label = isset($filter['label']) ? $filter['label'] : '';
        $placeholder = isset($filter['placeholder']) ? $filter['placeholder'] : 'Select date...';

        ob_start();
        ?>
        <div class="eau-filter-item">
            <label class="eau-filter-label" for="eau-filter-<?php echo esc_attr($key); ?>">
                <?php echo esc_html($label); ?>
            </label>
            <input
                type="date"
                class="eau-filter-date"
                id="eau-filter-<?php echo esc_attr($key); ?>"
                name="<?php echo esc_attr($key); ?>"
                data-filter="<?php echo esc_attr($key); ?>"
                placeholder="<?php echo esc_attr($placeholder); ?>"
            >
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renderiza um filtro do tipo date range (2 inputs)
     *
     * @param array $filter Configuração do filtro
     * @return string HTML do date range
     */
    private function render_date_range_filter($filter) {
        $key = isset($filter['key']) ? $filter['key'] : '';
        $label = isset($filter['label']) ? $filter['label'] : '';
        $key_from = $key . '_from';
        $key_to = $key . '_to';

        ob_start();
        ?>
        <div class="eau-filter-item eau-filter-date-range">
            <label class="eau-filter-label">
                <?php echo esc_html($label); ?>
            </label>
            <div class="eau-filter-date-range-inputs">
                <input
                    type="date"
                    class="eau-filter-date"
                    id="eau-filter-<?php echo esc_attr($key_from); ?>"
                    name="<?php echo esc_attr($key_from); ?>"
                    data-filter="<?php echo esc_attr($key_from); ?>"
                    placeholder="From"
                >
                <span class="eau-filter-date-separator">—</span>
                <input
                    type="date"
                    class="eau-filter-date"
                    id="eau-filter-<?php echo esc_attr($key_to); ?>"
                    name="<?php echo esc_attr($key_to); ?>"
                    data-filter="<?php echo esc_attr($key_to); ?>"
                    placeholder="To"
                >
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renderiza um filtro do tipo text
     *
     * @param array $filter Configuração do filtro
     * @return string HTML do text input
     */
    private function render_text_filter($filter) {
        $key = isset($filter['key']) ? $filter['key'] : '';
        $label = isset($filter['label']) ? $filter['label'] : '';
        $placeholder = isset($filter['placeholder']) ? $filter['placeholder'] : '';

        ob_start();
        ?>
        <div class="eau-filter-item">
            <label class="eau-filter-label" for="eau-filter-<?php echo esc_attr($key); ?>">
                <?php echo esc_html($label); ?>
            </label>
            <input
                type="text"
                class="eau-filter-text"
                id="eau-filter-<?php echo esc_attr($key); ?>"
                name="<?php echo esc_attr($key); ?>"
                data-filter="<?php echo esc_attr($key); ?>"
                placeholder="<?php echo esc_attr($placeholder); ?>"
            >
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Obtém as opções para o filtro de Status (membership status)
     *
     * @return array Array de opções [value => label]
     */
    public static function get_status_options() {
        return array(
            'active' => 'Active',
            'pending' => 'Pending Approval',
            'expired' => 'Expired',
            'suspended' => 'Suspended',
            'cancelled' => 'Cancelled',
        );
    }

    /**
     * Obtém as opções para o filtro de User Type (mem_type do JetEngine)
     *
     * Filtra opções baseado no tipo de usuário logado:
     * - superAdmin: vê todas as opções
     * - Admin: vê Admin, institutionAdmin e member
     * - institutionAdmin: vê institutionAdmin e member
     *
     * @return array Array de opções [value => label]
     * @since 1.72.5 - Usa helpers para suportar múltiplos tipos com priorização
     */
    public static function get_user_type_options() {
        $current_user_id = get_current_user_id();
        $current_user = get_userdata($current_user_id);

        // Todas as opções disponíveis
        $all_options = array(
            'superAdmin' => 'Super Admin',
            'Admin' => 'Admin',
            'institutionAdmin' => 'Institution Admin',
            'Member' => 'Member',
        );

        // v1.72.5: Usa helpers para verificar permissões (suporta múltiplos tipos)
        // Super Admin ou WP Administrator: vê TODAS as opções
        if (in_array('administrator', $current_user->roles) || \EauSystem\Eau_User_Institution_Helper::is_super_admin($current_user_id)) {
            return $all_options;
        }

        // Admin: vê Admin, institutionAdmin e member
        if (\EauSystem\Eau_User_Institution_Helper::is_admin($current_user_id)) {
            return array(
                'Admin' => 'Admin',
                'institutionAdmin' => 'Institution Admin',
                'Member' => 'Member',
            );
        }

        // Institution Admin: vê institutionAdmin e member
        if (\EauSystem\Eau_User_Institution_Helper::is_institution_admin($current_user_id)) {
            return array(
                'institutionAdmin' => 'Institution Admin',
                'Member' => 'Member',
            );
        }

        // Fallback: apenas Member (caso seja um member comum acessando)
        return array(
            'Member' => 'Member',
        );
    }

    /**
     * Obtém as opções para o filtro de Institution
     *
     * CORRETO: Institution Admin vê TODAS as suas instituições
     *
     * @return array Array de opções [ID => Nome]
     */
    public static function get_institution_options() {
        $is_institution_admin = \EauSystem\Eau_User_Institution_Helper::is_institution_admin();

        // Institution Admin: retorna TODAS as instituições que gerencia
        if ($is_institution_admin) {
            $user_institutions = \EauSystem\Eau_User_Institution_Helper::get_user_managed_institutions(get_current_user_id());

            $options = array();
            foreach ($user_institutions as $institution) {
                $options[$institution->ID] = $institution->post_title;
            }

            return $options;
        }

        // Admin/Super Admin: retorna todas
        $args = array(
            'post_type' => 'institutions',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        );

        $institutions = get_posts($args);

        $options = array();
        foreach ($institutions as $institution) {
            $options[$institution->ID] = $institution->post_title;
        }

        return $options;
    }

    /**
     * Get membership type options for filter dropdown
     *
     * @since 1.49.6
     * @return array Associative array of membership_type => label
     */
    public static function get_membership_type_options() {
        $types = \EauSystem\Eau_Membership_Types::get_all();

        $options = array();
        if (!empty($types)) {
            foreach ($types as $type) {
                $options[$type->type_key] = $type->type_label;
            }
        }

        // Add option for users without membership
        $options['none'] = 'No Membership';

        return $options;
    }

    /**
     * Get membership status options for filter dropdown
     *
     * @since 1.49.6
     * @return array Associative array of status => label
     */
    public static function get_membership_status_options() {
        return array(
            'active' => 'Active',
            'pending' => 'Pending',
            'expired' => 'Expired',
            'suspended' => 'Suspended',
            'none' => 'No Membership',
        );
    }

    /**
     * Get member tags options for filter dropdown
     *
     * @since 1.51.75
     * @return array Associative array of tag_slug => tag_name
     */
    public static function get_member_tag_options() {
        $tags = \EauSystem\Eau_Settings::get_member_tags();

        $options = array();
        foreach ($tags as $tag) {
            $options[$tag['slug']] = $tag['name'];
        }

        return $options;
    }

    /**
     * Get member position options for filters
     *
     * @since 1.58.0
     * @return array Associative array of position_value => position_label
     */
    public static function get_position_options() {
        return array(
            'teacher' => 'Teacher',
            'academic_manager' => 'Academic Manager',
            'director_of_studies' => 'Director of Studies',
            'principal' => 'Principal',
            'general_manager_ceo' => 'General Manager/CEO',
            'marketing_sales' => 'Marketing/Sales',
            'admissions' => 'Admissions',
            'student_services' => 'Student Services',
            'other' => 'Other',
        );
    }

    /**
     * Get email migration status options for filters
     *
     * @since 1.62.0
     * @return array Associative array of status_value => status_label
     */
    public static function get_email_migration_status_options() {
        return array(
            'pending' => 'Pending',
            'awaiting_verification' => 'Awaiting Verification',
            'verified' => 'Verified',
            'skipped' => 'Skipped',
            'not_set' => 'Not Set (Needs Migration)',
        );
    }

    /**
     * Get Lifetime Member flag options for filters
     *
     * @since 1.72.3
     * @return array Associative array of value => label
     */
    public static function get_lifetime_member_options() {
        return array(
            'yes' => 'Yes - Lifetime Member',
            'no' => 'No - Not Lifetime',
        );
    }

    /**
     * Get Full Individual Member flag options for filters
     *
     * @since 1.72.3
     * @return array Associative array of value => label
     */
    public static function get_full_individual_member_options() {
        return array(
            'yes' => 'Yes - Full Individual',
            'no' => 'No - Not Full Individual',
        );
    }
}
