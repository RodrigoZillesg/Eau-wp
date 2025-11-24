<?php
namespace EauSystem\Components;

/**
 * Componente reutilizável de Cards Estatísticos
 *
 * Uso:
 * $cards = new Eau_Stats_Cards($cards_data);
 * echo $cards->render();
 */
class Eau_Stats_Cards {

    private $cards;

    /**
     * Constructor
     *
     * @param array $cards Array de cards com estrutura:
     *  [
     *    [
     *      'title' => 'Total Members',
     *      'number' => 6056,
     *      'subtitle' => '',  // opcional
     *      'icon' => 'users',
     *      'color' => 'blue'  // blue, green, purple, orange, red
     *    ]
     *  ]
     */
    public function __construct($cards = array()) {
        $this->cards = $cards;
    }

    /**
     * Renderiza os cards
     *
     * @return string HTML dos cards
     */
    public function render() {
        if (empty($this->cards)) {
            return '';
        }

        ob_start();
        ?>
        <div class="eau-stats-cards">
            <?php foreach ($this->cards as $card): ?>
                <?php echo $this->render_single_card($card); ?>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renderiza um card individual
     *
     * @param array $card Dados do card
     * @return string HTML do card
     */
    private function render_single_card($card) {
        $defaults = array(
            'title' => '',
            'number' => 0,
            'subtitle' => '',
            'icon' => 'info',
            'color' => 'blue',
        );

        $card = wp_parse_args($card, $defaults);

        $color_class = 'eau-stat-card-' . sanitize_html_class($card['color']);

        ob_start();
        ?>
        <div class="eau-stat-card <?php echo esc_attr($color_class); ?>">
            <div class="eau-stat-card-icon">
                <i data-lucide="<?php echo esc_attr($card['icon']); ?>"></i>
            </div>
            <div class="eau-stat-card-content">
                <h3 class="eau-stat-card-title"><?php echo esc_html($card['title']); ?></h3>
                <div class="eau-stat-card-stats">
                    <span class="eau-stat-card-number"><?php
                        // Se já é string formatada, usa direto; caso contrário, formata como número
                        if (is_numeric($card['number'])) {
                            echo esc_html(number_format((float)$card['number']));
                        } else {
                            echo esc_html($card['number']);
                        }
                    ?></span>
                    <?php if (!empty($card['subtitle'])): ?>
                        <span class="eau-stat-card-subtitle"><?php echo esc_html($card['subtitle']); ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Enfileira os assets necessários
     */
    public static function enqueue_assets() {
        // CSS já está no eau-components.css
        // Lucide já está carregado globalmente
    }
}
