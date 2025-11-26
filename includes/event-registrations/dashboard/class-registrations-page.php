<?php
/**
 * Event Registrations Dashboard Page
 *
 * Shortcode: [eau_event_registrations]
 * URL: /dashboard/events/{slug}/registrations/
 *
 * @package    EauSystem
 * @subpackage EventRegistrations\Dashboard
 * @since      1.30.0
 */

namespace EauSystem\EventRegistrations\Dashboard;

use EauSystem\Components\Eau_Access_Denied;
use EauSystem\Events\Config;

if (!defined('WPINC')) {
    die;
}

/**
 * Class Registrations_Page
 *
 * Controller principal da página de registrations.
 *
 * @since 1.30.0
 */
class Registrations_Page {

    /**
     * Registra o shortcode e rewrite rules
     *
     * @since  1.30.0
     * @return void
     */
    public static function register() {
        add_shortcode('eau_event_registrations', array(__CLASS__, 'render'));
        add_action('init', array(__CLASS__, 'add_rewrite_rules'));
        add_filter('query_vars', array(__CLASS__, 'add_query_vars'));
        add_action('template_redirect', array(__CLASS__, 'handle_rewrite'));
    }

    /**
     * Adiciona rewrite rules para pretty URL
     *
     * @since  1.30.0
     * @return void
     */
    public static function add_rewrite_rules() {
        add_rewrite_rule(
            '^dashboard/events/([^/]+)/registrations/?$',
            'index.php?pagename=dashboard&eau_event_slug=$matches[1]&eau_registrations=1',
            'top'
        );
    }

    /**
     * Adiciona query vars
     *
     * @since  1.30.0
     * @param  array $vars Query vars
     * @return array
     */
    public static function add_query_vars($vars) {
        $vars[] = 'eau_event_slug';
        $vars[] = 'eau_registrations';
        return $vars;
    }

    /**
     * Handle rewrite - injeta shortcode na página
     *
     * @since  1.30.0
     * @return void
     */
    public static function handle_rewrite() {
        $event_slug = get_query_var('eau_event_slug');
        $is_registrations = get_query_var('eau_registrations');

        if ($event_slug && $is_registrations) {
            set_query_var('eau_event_registrations_slug', $event_slug);
            add_filter('the_content', array(__CLASS__, 'inject_shortcode'), 999);
        }
    }

    /**
     * Injeta o shortcode no conteúdo
     *
     * @since  1.30.0
     * @param  string $content Content
     * @return string
     */
    public static function inject_shortcode($content) {
        if (is_main_query() && in_the_loop()) {
            remove_filter('the_content', array(__CLASS__, 'inject_shortcode'), 999);
            return do_shortcode('[eau_event_registrations]');
        }
        return $content;
    }

    /**
     * Renderiza a página de Event Registrations
     *
     * @since  1.30.0
     * @param  array $atts Atributos do shortcode
     * @return string HTML da página
     */
    public static function render($atts = array()) {
        // Verifica acesso
        $access_check = self::check_access();
        if ($access_check !== true) {
            return $access_check;
        }

        // Obtém dados do evento
        $event_data = self::get_event_data();
        if (is_string($event_data)) {
            return $event_data; // Error message
        }

        // Carrega assets
        Registrations_Assets::enqueue();

        // Renderiza template
        return Registrations_Template::render($event_data);
    }

    /**
     * Verifica permissões de acesso
     *
     * @since  1.30.0
     * @return true|string True se permitido, HTML de erro se não
     */
    private static function check_access() {
        if (!is_user_logged_in()) {
            return Eau_Access_Denied::not_logged_in();
        }

        $user_id = get_current_user_id();
        $mem_type = get_user_meta($user_id, 'mem_type', true);

        if (!in_array($mem_type, array('superAdmin', 'Admin'))) {
            return Eau_Access_Denied::no_permission();
        }

        return true;
    }

    /**
     * Obtém dados do evento a partir da URL
     *
     * @since  1.30.0
     * @return array|string Array com dados ou mensagem de erro
     */
    private static function get_event_data() {
        $event_slug = get_query_var('eau_event_registrations_slug');
        if (!$event_slug) {
            $event_slug = get_query_var('eau_event_slug');
        }
        if (!$event_slug && isset($_GET['event'])) {
            $event_slug = sanitize_text_field($_GET['event']);
        }

        if (!$event_slug) {
            return '<div class="eau-error-message">Event not specified</div>';
        }

        $event = get_page_by_path($event_slug, OBJECT, Config\POST_TYPE);

        if (!$event) {
            return '<div class="eau-error-message">Event not found</div>';
        }

        $event_id = $event->ID;
        $start_datetime = get_post_meta($event_id, 'evt_start_datetime', true);
        $member_price = floatval(get_post_meta($event_id, 'evt_member_price', true) ?: 0);

        return array(
            'id'           => $event_id,
            'title'        => $event->post_title,
            'slug'         => $event_slug,
            'date'         => self::format_date($start_datetime),
            'type'         => get_post_meta($event_id, 'evt_event_type', true) ?: 'in-person',
            'price'        => $member_price,
            'stats'        => Registrations_Stats::get($event_id, $member_price),
        );
    }

    /**
     * Formata data do evento
     *
     * @since  1.30.0
     * @param  string $datetime Datetime string
     * @return string Data formatada
     */
    private static function format_date($datetime) {
        if (!$datetime) {
            return '';
        }

        $date_obj = \DateTime::createFromFormat('Y-m-d\TH:i', $datetime);
        if (!$date_obj) {
            $date_obj = \DateTime::createFromFormat('Y-m-d H:i:s', $datetime);
        }

        return $date_obj ? $date_obj->format('F j, Y') : '';
    }
}
