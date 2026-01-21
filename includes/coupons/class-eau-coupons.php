<?php
namespace EauSystem\Coupons;

/**
 * Bootstrap do módulo de cupons
 *
 * @since 1.69.0
 */
class Eau_Coupons {

    /**
     * Inicializa o módulo de cupons
     */
    public static function init() {
        // Registra handlers AJAX
        add_action('init', array(__CLASS__, 'register_ajax_handlers'));

        // Cria tabelas na ativação (já chamado no hook de ativação do plugin principal)
        // Mas vamos garantir que existam
        add_action('admin_init', array(__CLASS__, 'maybe_create_tables'));

        // Atualiza cupons expirados diariamente
        add_action('init', array(__CLASS__, 'setup_cron'));
        add_action('eau_update_expired_coupons', array(__CLASS__, 'update_expired_coupons'));
    }

    /**
     * Registra handlers AJAX
     */
    public static function register_ajax_handlers() {
        require_once EAU_SYSTEM_PLUGIN_DIR . 'ajax/class-eau-coupons-ajax.php';
        \EauSystem\Ajax\Eau_Coupons_Ajax::register();
    }

    /**
     * Cria tabelas se não existirem
     */
    public static function maybe_create_tables() {
        if (!Eau_Coupons_Database::tables_exist()) {
            Eau_Coupons_Database::create_tables();
        }
    }

    /**
     * Configura cron para atualização de cupons expirados
     */
    public static function setup_cron() {
        if (!wp_next_scheduled('eau_update_expired_coupons')) {
            wp_schedule_event(time(), 'daily', 'eau_update_expired_coupons');
        }
    }

    /**
     * Remove cron na desativação
     */
    public static function clear_cron() {
        $timestamp = wp_next_scheduled('eau_update_expired_coupons');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'eau_update_expired_coupons');
        }
    }

    /**
     * Atualiza status de cupons expirados
     */
    public static function update_expired_coupons() {
        Eau_Coupons_Model::update_expired_coupons();
    }
}
