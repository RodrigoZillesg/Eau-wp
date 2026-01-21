<?php
namespace EauSystem\Coupons;

/**
 * Gerencia as tabelas de cupons de desconto
 *
 * @since 1.69.0
 */
class Eau_Coupons_Database {

    /**
     * Nomes das tabelas
     */
    const TABLE_COUPONS = 'eau_coupons';
    const TABLE_COUPON_EVENTS = 'eau_coupon_events';
    const TABLE_COUPON_USAGE = 'eau_coupon_usage';

    /**
     * Cria todas as tabelas de cupons
     */
    public static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Tabela principal de cupons
        self::create_coupons_table($charset_collate);

        // Tabela de relação cupom-eventos (N:N)
        self::create_coupon_events_table($charset_collate);

        // Tabela de uso de cupons (tracking)
        self::create_coupon_usage_table($charset_collate);
    }

    /**
     * Cria a tabela principal de cupons
     *
     * @param string $charset_collate Charset do banco
     */
    private static function create_coupons_table($charset_collate) {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE_COUPONS;

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            code varchar(50) NOT NULL,
            description varchar(255) DEFAULT '',
            discount_type enum('percentage', 'fixed') NOT NULL DEFAULT 'percentage',
            discount_value decimal(10,2) NOT NULL DEFAULT 0.00,
            min_order_value decimal(10,2) DEFAULT NULL,
            max_discount decimal(10,2) DEFAULT NULL,
            valid_from datetime DEFAULT NULL,
            valid_until datetime DEFAULT NULL,
            usage_limit int(11) DEFAULT NULL,
            usage_limit_per_user int(11) DEFAULT 1,
            usage_count int(11) NOT NULL DEFAULT 0,
            status enum('active', 'inactive', 'expired') NOT NULL DEFAULT 'active',
            event_scope enum('all', 'specific') NOT NULL DEFAULT 'all',
            created_by bigint(20) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY code (code),
            KEY status (status),
            KEY valid_from (valid_from),
            KEY valid_until (valid_until)
        ) $charset_collate;";

        dbDelta($sql);
    }

    /**
     * Cria a tabela de relação cupom-eventos
     *
     * @param string $charset_collate Charset do banco
     */
    private static function create_coupon_events_table($charset_collate) {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE_COUPON_EVENTS;

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            coupon_id bigint(20) NOT NULL,
            event_id bigint(20) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY coupon_event (coupon_id, event_id),
            KEY coupon_id (coupon_id),
            KEY event_id (event_id)
        ) $charset_collate;";

        dbDelta($sql);
    }

    /**
     * Cria a tabela de uso de cupons
     *
     * @param string $charset_collate Charset do banco
     */
    private static function create_coupon_usage_table($charset_collate) {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE_COUPON_USAGE;

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            coupon_id bigint(20) NOT NULL,
            user_id bigint(20) NOT NULL,
            registration_id bigint(20) DEFAULT NULL,
            event_id bigint(20) DEFAULT NULL,
            discount_applied decimal(10,2) NOT NULL DEFAULT 0.00,
            original_price decimal(10,2) NOT NULL DEFAULT 0.00,
            final_price decimal(10,2) NOT NULL DEFAULT 0.00,
            used_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY coupon_id (coupon_id),
            KEY user_id (user_id),
            KEY registration_id (registration_id),
            KEY event_id (event_id)
        ) $charset_collate;";

        dbDelta($sql);
    }

    /**
     * Verifica se as tabelas existem
     *
     * @return bool
     */
    public static function tables_exist() {
        global $wpdb;

        $coupons_table = $wpdb->prefix . self::TABLE_COUPONS;
        $events_table = $wpdb->prefix . self::TABLE_COUPON_EVENTS;
        $usage_table = $wpdb->prefix . self::TABLE_COUPON_USAGE;

        $coupons_exists = $wpdb->get_var("SHOW TABLES LIKE '$coupons_table'");
        $events_exists = $wpdb->get_var("SHOW TABLES LIKE '$events_table'");
        $usage_exists = $wpdb->get_var("SHOW TABLES LIKE '$usage_table'");

        return $coupons_exists && $events_exists && $usage_exists;
    }

    /**
     * Remove todas as tabelas (para desinstalação)
     */
    public static function drop_tables() {
        global $wpdb;

        $wpdb->query("DROP TABLE IF EXISTS " . $wpdb->prefix . self::TABLE_COUPON_USAGE);
        $wpdb->query("DROP TABLE IF EXISTS " . $wpdb->prefix . self::TABLE_COUPON_EVENTS);
        $wpdb->query("DROP TABLE IF EXISTS " . $wpdb->prefix . self::TABLE_COUPONS);
    }
}
