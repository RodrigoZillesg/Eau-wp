<?php
namespace EauSystem\Components;

/**
 * Componente reutilizável de Paginação
 *
 * Uso:
 * $pagination = new Eau_Pagination($config);
 * echo $pagination->render();
 */
class Eau_Pagination {

    private $config;

    /**
     * Constructor
     *
     * @param array $config Configuração da paginação:
     *  [
     *    'id' => 'members-pagination',
     *    'total_items' => 6056,
     *    'per_page' => 20,
     *    'current_page' => 1,
     *    'max_pages_shown' => 5,  // Número máximo de botões de página exibidos
     *  ]
     */
    public function __construct($config = array()) {
        $defaults = array(
            'id' => 'eau-pagination',
            'total_items' => 0,
            'per_page' => 20,
            'current_page' => 1,
            'max_pages_shown' => 5,
        );

        $this->config = wp_parse_args($config, $defaults);
    }

    /**
     * Renderiza a paginação
     *
     * @return string HTML da paginação
     */
    public function render() {
        $total_pages = $this->get_total_pages();

        if ($total_pages <= 1) {
            return ''; // Não mostra paginação se só tem 1 página
        }

        $current_page = $this->config['current_page'];
        $start_item = (($current_page - 1) * $this->config['per_page']) + 1;
        $end_item = min($current_page * $this->config['per_page'], $this->config['total_items']);

        ob_start();
        ?>
        <div class="eau-pagination-wrapper" id="<?php echo esc_attr($this->config['id']); ?>">

            <!-- Info: Showing X to Y of Z -->
            <div class="eau-pagination-info">
                Showing <?php echo number_format($start_item); ?> to <?php echo number_format($end_item); ?>
                of <?php echo number_format($this->config['total_items']); ?> members
            </div>

            <!-- Navigation Buttons -->
            <div class="eau-pagination-nav">
                <?php echo $this->render_nav_buttons($current_page, $total_pages); ?>
            </div>

        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renderiza os botões de navegação
     *
     * @param int $current_page Página atual
     * @param int $total_pages Total de páginas
     * @return string HTML dos botões
     */
    private function render_nav_buttons($current_page, $total_pages) {
        ob_start();

        // Previous button
        $prev_disabled = $current_page <= 1 ? 'disabled' : '';
        ?>
        <button
            class="eau-pagination-btn eau-pagination-prev <?php echo $prev_disabled; ?>"
            data-page="<?php echo max(1, $current_page - 1); ?>"
            <?php echo $prev_disabled ? 'disabled' : ''; ?>
        >
            <i data-lucide="chevron-left"></i>
        </button>

        <?php
        // Page number buttons
        $pages_to_show = $this->get_pages_to_show($current_page, $total_pages);

        foreach ($pages_to_show as $page) {
            if ($page === '...') {
                echo '<span class="eau-pagination-ellipsis">...</span>';
            } else {
                $active_class = $page === $current_page ? 'eau-pagination-active' : '';
                ?>
                <button
                    class="eau-pagination-btn eau-pagination-number <?php echo $active_class; ?>"
                    data-page="<?php echo $page; ?>"
                >
                    <?php echo $page; ?>
                </button>
                <?php
            }
        }

        // Next button
        $next_disabled = $current_page >= $total_pages ? 'disabled' : '';
        ?>
        <button
            class="eau-pagination-btn eau-pagination-next <?php echo $next_disabled; ?>"
            data-page="<?php echo min($total_pages, $current_page + 1); ?>"
            <?php echo $next_disabled ? 'disabled' : ''; ?>
        >
            <i data-lucide="chevron-right"></i>
        </button>

        <?php
        return ob_get_clean();
    }

    /**
     * Calcula quais páginas devem ser exibidas
     *
     * Exemplo: Se estamos na página 5 de 10:
     * [1] ... [3] [4] [5] [6] [7] ... [10]
     *
     * @param int $current_page Página atual
     * @param int $total_pages Total de páginas
     * @return array Array de páginas a exibir (pode conter '...' para ellipsis)
     */
    private function get_pages_to_show($current_page, $total_pages) {
        $max_shown = $this->config['max_pages_shown'];
        $pages = array();

        // Se tem poucas páginas, mostra todas
        if ($total_pages <= $max_shown + 2) {
            return range(1, $total_pages);
        }

        // Sempre mostra a primeira página
        $pages[] = 1;

        // Calcula o range ao redor da página atual
        $start = max(2, $current_page - floor($max_shown / 2));
        $end = min($total_pages - 1, $current_page + floor($max_shown / 2));

        // Ajusta se estiver no início
        if ($current_page <= ceil($max_shown / 2) + 1) {
            $end = min($total_pages - 1, $max_shown);
        }

        // Ajusta se estiver no final
        if ($current_page >= $total_pages - ceil($max_shown / 2)) {
            $start = max(2, $total_pages - $max_shown);
        }

        // Adiciona ellipsis se necessário (início)
        if ($start > 2) {
            $pages[] = '...';
        }

        // Adiciona páginas do meio
        for ($i = $start; $i <= $end; $i++) {
            $pages[] = $i;
        }

        // Adiciona ellipsis se necessário (fim)
        if ($end < $total_pages - 1) {
            $pages[] = '...';
        }

        // Sempre mostra a última página
        if ($total_pages > 1) {
            $pages[] = $total_pages;
        }

        return $pages;
    }

    /**
     * Calcula o total de páginas
     *
     * @return int Total de páginas
     */
    private function get_total_pages() {
        if ($this->config['per_page'] <= 0) {
            return 1;
        }

        return (int) ceil($this->config['total_items'] / $this->config['per_page']);
    }
}
