<?php
/**
 * Events Frontend - Templates
 *
 * Carrega templates customizados para archive e single de eventos.
 * Permite sobrescrever templates padrão do tema.
 *
 * @package    EauSystem
 * @subpackage Events\Frontend
 * @since      1.28.0
 */

namespace EauSystem\Events\Frontend;

use EauSystem\Events\Config;

if (!defined('WPINC')) {
    die;
}

/**
 * Class Eau_Events_Templates
 *
 * Gerencia carregamento de templates customizados.
 *
 * @since 1.28.0
 */
class Eau_Events_Templates {

    /**
     * Instância singleton
     *
     * @since 1.28.0
     * @var   Eau_Events_Templates|null
     */
    private static $instance = null;

    /**
     * Retorna a instância singleton
     *
     * @since  1.28.0
     * @return Eau_Events_Templates
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor privado (singleton)
     *
     * @since 1.28.0
     */
    private function __construct() {
        add_filter('template_include', array($this, 'load_template'));
    }

    /**
     * Carrega template customizado para eventos
     *
     * Verifica se é archive ou single de eventos e carrega
     * template da pasta includes/events/templates/.
     *
     * @since  1.28.0
     * @param  string $template Caminho do template padrão
     * @return string Caminho do template (customizado ou padrão)
     */
    public function load_template($template) {
        $post_type = Config\POST_TYPE;

        if (is_post_type_archive($post_type)) {
            $custom = EAU_SYSTEM_PLUGIN_DIR . 'includes/events/templates/archive-eau_event.php';
            if (file_exists($custom)) {
                return $custom;
            }
        }

        if (is_singular($post_type)) {
            $custom = EAU_SYSTEM_PLUGIN_DIR . 'includes/events/templates/single-eau_event.php';
            if (file_exists($custom)) {
                return $custom;
            }
        }

        return $template;
    }
}
