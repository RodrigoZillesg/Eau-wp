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
                        Limpar Filtros
                    </button>
                    <button class="eau-btn eau-btn-primary eau-filters-apply" type="button">
                        <i data-lucide="check"></i>
                        Aplicar Filtros
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
     * Obtém as opções para o filtro de Status
     *
     * @return array Array de opções [value => label]
     */
    public static function get_status_options() {
        return array(
            'active' => 'Active',
            'inactive' => 'Inactive',
        );
    }

    /**
     * Obtém as opções para o filtro de User Type (mem_type do JetEngine)
     *
     * @return array Array de opções [value => label]
     */
    public static function get_user_type_options() {
        // Opções do glossário "User types" do JetEngine
        return array(
            'superAdmin' => 'Super Admin',
            'Admin' => 'Admin',
            'institutionAdmin' => 'Institution Admin',
            'Member' => 'Member',
        );
    }

    /**
     * Obtém as opções para o filtro de Membership Type
     *
     * @return array Array de opções [value => label]
     */
    public static function get_membership_type_options() {
        global $wpdb;

        // Busca todos os valores únicos de ins_type
        $results = $wpdb->get_col("
            SELECT DISTINCT pm.meta_value
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
            WHERE pm.meta_key = 'ins_type'
            AND p.post_type = 'institutions'
            AND p.post_status = 'publish'
            AND pm.meta_value != ''
            ORDER BY pm.meta_value ASC
        ");

        $options = array();
        foreach ($results as $type) {
            $options[$type] = $type;
        }

        return $options;
    }

    /**
     * Obtém as opções para o filtro de Institution
     *
     * @return array Array de opções [ID => Nome]
     */
    public static function get_institution_options() {
        $is_institution_admin = \EauSystem\Eau_User_Institution_Helper::is_institution_admin();

        // Institution Admin: retorna apenas sua instituição
        if ($is_institution_admin) {
            $user_institution = \EauSystem\Eau_User_Institution_Helper::get_user_institution(get_current_user_id());

            if ($user_institution) {
                return array(
                    $user_institution->ID => $user_institution->post_title
                );
            }

            return array();
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
}
