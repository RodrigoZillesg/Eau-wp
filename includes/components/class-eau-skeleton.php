<?php
namespace EauSystem\Components;

/**
 * Componente reutilizável de Skeleton Loading
 *
 * Este componente fornece métodos estáticos para gerar skeleton loaders
 * consistentes em todo o plugin. Skeleton loaders são placeholders animados
 * que indicam que o conteúdo está carregando.
 *
 * IMPORTANTE: Este é o padrão oficial para todos os loadings do plugin.
 * Use sempre skeleton loaders ao invés de spinners ou texto "Loading...".
 *
 * Uso:
 * echo Eau_Skeleton::table();
 * echo Eau_Skeleton::stats_cards();
 * echo Eau_Skeleton::text();
 */
class Eau_Skeleton {

    /**
     * Renderiza skeleton para uma tabela completa (Members)
     *
     * @param int $rows Número de linhas skeleton (padrão: 5)
     * @return string HTML do skeleton table
     */
    public static function table($rows = 5) {
        ob_start();
        ?>
        <div class="eau-skeleton-table">
            <!-- Header -->
            <div class="eau-skeleton-table-header">
                <div class="eau-skeleton eau-skeleton-circle"></div>
                <div class="eau-skeleton"></div>
                <div class="eau-skeleton"></div>
                <div class="eau-skeleton"></div>
                <div class="eau-skeleton"></div>
                <div class="eau-skeleton"></div>
                <div class="eau-skeleton"></div>
            </div>

            <!-- Body -->
            <div class="eau-skeleton-table-body">
                <?php for ($i = 0; $i < $rows; $i++) : ?>
                    <div class="eau-skeleton-table-row">
                        <div class="eau-skeleton eau-skeleton-circle"></div>
                        <div class="eau-skeleton"></div>
                        <div class="eau-skeleton"></div>
                        <div class="eau-skeleton"></div>
                        <div class="eau-skeleton"></div>
                        <div class="eau-skeleton"></div>
                        <div class="eau-skeleton"></div>
                    </div>
                <?php endfor; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renderiza skeleton para cards de estatísticas (Dashboard/Members)
     *
     * @param int $count Número de cards (padrão: 4)
     * @return string HTML do skeleton stats cards
     */
    public static function stats_cards($count = 4) {
        ob_start();
        ?>
        <div class="eau-skeleton-stats-grid">
            <?php for ($i = 0; $i < $count; $i++) : ?>
                <div class="eau-skeleton-stat-card">
                    <div class="eau-skeleton eau-skeleton-circle"></div>
                    <div class="eau-skeleton-stat-content">
                        <div class="eau-skeleton eau-skeleton-title"></div>
                        <div class="eau-skeleton eau-skeleton-text"></div>
                    </div>
                </div>
            <?php endfor; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renderiza skeleton para texto simples
     *
     * @param int $lines Número de linhas (padrão: 3)
     * @return string HTML do skeleton text
     */
    public static function text($lines = 3) {
        ob_start();
        ?>
        <div class="eau-skeleton-text-block">
            <?php for ($i = 0; $i < $lines; $i++) : ?>
                <div class="eau-skeleton eau-skeleton-text"></div>
            <?php endfor; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renderiza skeleton para um card genérico
     *
     * @return string HTML do skeleton card
     */
    public static function card() {
        ob_start();
        ?>
        <div class="eau-skeleton eau-skeleton-card"></div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renderiza skeleton para uma linha genérica
     *
     * @return string HTML do skeleton row
     */
    public static function row() {
        ob_start();
        ?>
        <div class="eau-skeleton eau-skeleton-row"></div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renderiza skeleton personalizado
     *
     * Permite criar skeletons customizados com HTML próprio
     *
     * @param string $html HTML customizado
     * @return string HTML do skeleton
     */
    public static function custom($html) {
        return $html;
    }
}
