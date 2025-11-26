<?php
namespace EauSystem\Components;

/**
 * Componente reutilizável de Data Table
 *
 * Tabela genérica com suporte a:
 * - Seleção múltipla (checkboxes)
 * - Ações por linha (view, edit, delete)
 * - Loading states
 * - Empty states
 * - Responsividade
 *
 * Uso:
 * $table = new Eau_Data_Table($config);
 * echo $table->render();
 */
class Eau_Data_Table {

    private $config;
    private $data;

    /**
     * Constructor
     *
     * @param array $config Configuração da tabela:
     *  [
     *    'id' => 'members-table',
     *    'columns' => [
     *      ['key' => 'member', 'label' => 'MEMBER', 'sortable' => true],
     *      ['key' => 'contact', 'label' => 'CONTACT'],
     *      ...
     *    ],
     *    'actions' => ['view', 'edit', 'delete'],
     *    'selectable' => true,
     *    'ajax_endpoint' => 'eau_get_members',
     *    'empty_message' => 'No members found',
     *  ]
     */
    public function __construct($config = array()) {
        $defaults = array(
            'id' => 'eau-table',
            'columns' => array(),
            'actions' => array('view', 'edit', 'delete'),
            'selectable' => true,
            'ajax_endpoint' => '',
            'empty_message' => 'No items found',
            'loading_message' => 'Loading...',
        );

        $this->config = wp_parse_args($config, $defaults);
        $this->data = array();
    }

    /**
     * Define os dados da tabela (para uso sem AJAX)
     *
     * @param array $data Array de dados
     */
    public function set_data($data) {
        $this->data = $data;
    }

    /**
     * Renderiza a tabela
     *
     * @return string HTML da tabela
     */
    public function render() {
        $table_id = esc_attr($this->config['id']);

        ob_start();
        ?>
        <div class="eau-data-table-wrapper" id="<?php echo $table_id; ?>-wrapper">

            <!-- Table Header (Select All + Count) -->
            <div class="eau-table-header">
                <?php if ($this->config['selectable']): ?>
                    <label class="eau-checkbox-wrapper">
                        <input type="checkbox" id="<?php echo $table_id; ?>-select-all" class="eau-select-all">
                        <span>Select all</span>
                    </label>
                <?php endif; ?>
                <span class="eau-table-count" id="<?php echo $table_id; ?>-count">
                    <span class="eau-count-number">0</span> total items
                </span>
            </div>

            <!-- Table -->
            <div class="eau-table-container">
                <table class="eau-table" id="<?php echo $table_id; ?>">
                    <thead>
                        <tr>
                            <?php if ($this->config['selectable']): ?>
                                <th class="eau-table-th-checkbox">
                                    <input type="checkbox" class="eau-table-select-all-header">
                                </th>
                            <?php endif; ?>

                            <?php foreach ($this->config['columns'] as $column): ?>
                                <?php
                                $sortable = isset($column['sortable']) && $column['sortable'];
                                $sort_class = $sortable ? 'eau-sortable' : '';
                                ?>
                                <th class="eau-table-th <?php echo esc_attr($sort_class); ?>"
                                    data-key="<?php echo esc_attr($column['key']); ?>">
                                    <?php echo esc_html($column['label']); ?>
                                    <?php if ($sortable): ?>
                                        <i data-lucide="chevrons-up-down" class="eau-sort-icon"></i>
                                    <?php endif; ?>
                                </th>
                            <?php endforeach; ?>

                            <?php if (!empty($this->config['actions'])): ?>
                                <th class="eau-table-th eau-table-th-actions">ACTIONS</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody id="<?php echo $table_id; ?>-tbody">
                        <?php if (!empty($this->data)): ?>
                            <?php echo $this->render_rows($this->data); ?>
                        <?php else: ?>
                            <?php echo $this->render_loading_state(); ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Loading Overlay - Skeleton -->
            <div class="eau-table-loading-overlay" id="<?php echo $table_id; ?>-loading" style="display: none;">
                <?php echo $this->render_skeleton_overlay(); ?>
            </div>

        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renderiza as linhas da tabela
     *
     * @param array $data Dados das linhas
     * @return string HTML das linhas
     */
    public function render_rows($data) {
        if (empty($data)) {
            return $this->render_empty_state();
        }

        ob_start();
        foreach ($data as $row) {
            echo $this->render_single_row($row);
        }
        return ob_get_clean();
    }

    /**
     * Renderiza uma linha individual
     *
     * @param array $row Dados da linha
     * @return string HTML da linha
     */
    private function render_single_row($row) {
        $row_id = isset($row['ID']) ? $row['ID'] : '';

        ob_start();
        ?>
        <tr class="eau-table-row" data-id="<?php echo esc_attr($row_id); ?>">

            <?php if ($this->config['selectable']): ?>
                <td class="eau-table-td-checkbox">
                    <input type="checkbox" class="eau-row-checkbox" value="<?php echo esc_attr($row_id); ?>">
                </td>
            <?php endif; ?>

            <?php foreach ($this->config['columns'] as $column): ?>
                <td class="eau-table-td" data-label="<?php echo esc_attr($column['label']); ?>">
                    <?php echo $this->render_cell_content($row, $column); ?>
                </td>
            <?php endforeach; ?>

            <?php if (!empty($this->config['actions'])): ?>
                <td class="eau-table-td eau-table-td-actions">
                    <div class="eau-table-actions">
                        <?php echo $this->render_actions($row_id); ?>
                    </div>
                </td>
            <?php endif; ?>

        </tr>
        <?php
        return ob_get_clean();
    }

    /**
     * Renderiza o conteúdo de uma célula
     *
     * @param array $row Dados da linha
     * @param array $column Configuração da coluna
     * @return string HTML da célula
     */
    private function render_cell_content($row, $column) {
        $key = $column['key'];
        $value = isset($row[$key]) ? $row[$key] : '';

        // Se tem render customizado
        if (isset($column['render']) && is_callable($column['render'])) {
            return call_user_func($column['render'], $value, $row);
        }

        // Renderização padrão
        return esc_html($value);
    }

    /**
     * Renderiza os botões de ação
     *
     * @param int $row_id ID da linha
     * @return string HTML das ações
     */
    private function render_actions($row_id) {
        $actions_html = '';

        foreach ($this->config['actions'] as $action) {
            $icon = $this->get_action_icon($action);
            $title = ucfirst($action);

            $actions_html .= sprintf(
                '<button class="eau-action-btn eau-action-%s" data-action="%s" data-id="%s" title="%s">
                    <i data-lucide="%s"></i>
                </button>',
                esc_attr($action),
                esc_attr($action),
                esc_attr($row_id),
                esc_attr($title),
                esc_attr($icon)
            );
        }

        return $actions_html;
    }

    /**
     * Pega o ícone para cada ação
     *
     * @param string $action Nome da ação
     * @return string Nome do ícone Lucide
     */
    private function get_action_icon($action) {
        $icons = array(
            'view' => 'eye',
            'edit' => 'pencil',
            'delete' => 'trash-2',
        );

        return isset($icons[$action]) ? $icons[$action] : 'circle';
    }

    /**
     * Renderiza estado de loading com skeleton
     *
     * @return string HTML do loading state
     */
    private function render_loading_state() {
        $colspan = count($this->config['columns']);
        if ($this->config['selectable']) $colspan++;
        if (!empty($this->config['actions'])) $colspan++;

        ob_start();
        ?>
        <tr class="eau-table-loading">
            <td colspan="<?php echo $colspan; ?>" style="padding: 0;">
                <?php echo Eau_Skeleton::table(10); ?>
            </td>
        </tr>
        <?php
        return ob_get_clean();
    }

    /**
     * Renderiza estado vazio (sem dados)
     *
     * @return string HTML do empty state
     */
    private function render_empty_state() {
        $colspan = count($this->config['columns']);
        if ($this->config['selectable']) $colspan++;
        if (!empty($this->config['actions'])) $colspan++;

        ob_start();
        ?>
        <tr class="eau-table-empty">
            <td colspan="<?php echo $colspan; ?>" style="text-align: center; padding: 3rem;">
                <i data-lucide="inbox" style="width: 3rem; height: 3rem; color: #d1d5db; margin-bottom: 1rem;"></i>
                <p style="color: #6b7280; margin: 0;"><?php echo esc_html($this->config['empty_message']); ?></p>
            </td>
        </tr>
        <?php
        return ob_get_clean();
    }

    /**
     * Renderiza skeleton overlay para loading
     *
     * @return string HTML do skeleton overlay
     */
    private function render_skeleton_overlay() {
        $colspan = count($this->config['columns']);
        if ($this->config['selectable']) $colspan++;
        if (!empty($this->config['actions'])) $colspan++;

        ob_start();
        ?>
        <table class="eau-table">
            <thead>
                <tr>
                    <?php if ($this->config['selectable']): ?>
                        <th class="eau-table-th-checkbox"></th>
                    <?php endif; ?>

                    <?php foreach ($this->config['columns'] as $column): ?>
                        <th class="eau-table-th"><?php echo esc_html($column['label']); ?></th>
                    <?php endforeach; ?>

                    <?php if (!empty($this->config['actions'])): ?>
                        <th class="eau-table-th eau-table-th-actions">ACTIONS</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td colspan="<?php echo $colspan; ?>" style="padding: 0;">
                        <?php echo Eau_Skeleton::table(10); ?>
                    </td>
                </tr>
            </tbody>
        </table>
        <?php
        return ob_get_clean();
    }
}
