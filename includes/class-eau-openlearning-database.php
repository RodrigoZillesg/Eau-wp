<?php
namespace EauSystem;

/**
 * Gerencia as tabelas de banco de dados para OpenLearning
 *
 * @since 1.41.0
 */
class Eau_OpenLearning_Database {

    /**
     * Nome da tabela de catálogo de cursos
     */
    public static function get_courses_table() {
        global $wpdb;
        return $wpdb->prefix . 'eau_openlearning_courses';
    }

    /**
     * Nome da tabela de log de sincronização
     */
    public static function get_sync_log_table() {
        global $wpdb;
        return $wpdb->prefix . 'eau_openlearning_sync_log';
    }

    /**
     * Nome da tabela de usuários provisionados
     */
    public static function get_users_table() {
        global $wpdb;
        return $wpdb->prefix . 'eau_openlearning_users';
    }

    /**
     * Cria todas as tabelas necessárias
     */
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Tabela de catálogo de cursos (cache)
        $courses_table = self::get_courses_table();
        $sql_courses = "CREATE TABLE IF NOT EXISTS {$courses_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            course_id VARCHAR(255) NOT NULL,
            title TEXT NOT NULL,
            description TEXT,
            image_url TEXT,
            course_url TEXT NOT NULL,
            price DECIMAL(10,2) DEFAULT 0,
            self_paced TINYINT(1) DEFAULT 1,
            slug VARCHAR(255),
            is_available TINYINT(1) DEFAULT 1,
            is_manually_disabled TINYINT(1) DEFAULT 0,
            disabled_at DATETIME DEFAULT NULL,
            disabled_by BIGINT UNSIGNED DEFAULT NULL,
            last_checked DATETIME DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY course_id (course_id),
            KEY is_available (is_available),
            KEY is_manually_disabled (is_manually_disabled)
        ) {$charset_collate};";

        dbDelta($sql_courses);

        // Tabela de log de sincronização
        $sync_log_table = self::get_sync_log_table();
        $sql_sync_log = "CREATE TABLE IF NOT EXISTS {$sync_log_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            sync_started_at DATETIME NOT NULL,
            sync_completed_at DATETIME DEFAULT NULL,
            total_courses INT DEFAULT 0,
            available_courses INT DEFAULT 0,
            unavailable_courses INT DEFAULT 0,
            new_courses INT DEFAULT 0,
            updated_courses INT DEFAULT 0,
            status VARCHAR(50) DEFAULT 'running',
            error_message TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status (status),
            KEY sync_started_at (sync_started_at)
        ) {$charset_collate};";

        dbDelta($sql_sync_log);

        // Tabela de usuários provisionados no OpenLearning
        $users_table = self::get_users_table();
        $sql_users = "CREATE TABLE IF NOT EXISTS {$users_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            wp_user_id BIGINT UNSIGNED NOT NULL,
            openlearning_user_id VARCHAR(255) NOT NULL,
            external_institution_id VARCHAR(255),
            provisioned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_sso_at DATETIME DEFAULT NULL,
            sso_count INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY wp_user_id (wp_user_id),
            UNIQUE KEY openlearning_user_id (openlearning_user_id),
            KEY external_institution_id (external_institution_id)
        ) {$charset_collate};";

        dbDelta($sql_users);
    }

    /**
     * Verifica se as tabelas existem
     */
    public static function tables_exist() {
        global $wpdb;

        $courses_table = self::get_courses_table();
        $result = $wpdb->get_var("SHOW TABLES LIKE '{$courses_table}'");

        return $result === $courses_table;
    }

    /**
     * Remove todas as tabelas (para desinstalação)
     */
    public static function drop_tables() {
        global $wpdb;

        $wpdb->query("DROP TABLE IF EXISTS " . self::get_courses_table());
        $wpdb->query("DROP TABLE IF EXISTS " . self::get_sync_log_table());
        $wpdb->query("DROP TABLE IF EXISTS " . self::get_users_table());
    }
}
