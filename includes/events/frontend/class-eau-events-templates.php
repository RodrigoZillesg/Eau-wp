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

        // Page templates from plugin
        add_filter('theme_page_templates', array($this, 'register_page_templates'), 10, 3);
        add_filter('template_include', array($this, 'load_page_template'), 99);

        // Prevent redirect when archive is empty
        self::prevent_empty_archive_redirect();
    }

    /**
     * Registra templates de página customizados
     *
     * @since  1.28.3
     * @param  array    $templates Templates existentes
     * @param  WP_Theme $theme     Tema atual
     * @param  WP_Post  $post      Post atual
     * @return array Templates com os novos adicionados
     */
    public function register_page_templates($templates, $theme = null, $post = null) {
        $templates['page-events-management.php'] = __('Events Management', 'eau-system');
        return $templates;
    }

    /**
     * Carrega template de página customizado
     *
     * @since  1.28.3
     * @param  string $template Caminho do template
     * @return string Caminho do template customizado ou original
     */
    public function load_page_template($template) {
        global $post;

        if (!$post || !is_page()) {
            return $template;
        }

        $page_template = get_post_meta($post->ID, '_wp_page_template', true);

        if ($page_template === 'page-events-management.php') {
            $custom = EAU_SYSTEM_PLUGIN_DIR . 'includes/events/templates/page-events-management.php';
            if (file_exists($custom)) {
                return $custom;
            }
        }

        return $template;
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

    /**
     * Previne redirect para home quando archive está vazio
     *
     * @since  1.28.3
     * @return void
     */
    public static function prevent_empty_archive_redirect() {
        add_action('template_redirect', function() {
            if (is_post_type_archive(Config\POST_TYPE) && !have_posts()) {
                global $wp_query;
                $wp_query->is_404 = false;
                status_header(200);
            }
        }, 1);
    }
}
