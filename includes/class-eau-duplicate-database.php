<?php
namespace EauSystem;

/**
 * Gerencia tabelas do banco de dados para o sistema de duplicatas
 */
class Eau_Duplicate_Database {

    /**
     * Cria todas as tabelas necessárias
     */
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Adiciona coluna total_users se não existir (para atualizações)
        $table_scans = $wpdb->prefix . 'eau_duplicate_scans';

        // Verifica se a tabela já existe antes de tentar adicionar coluna
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_scans'");

        if ($table_exists) {
            $column_exists = $wpdb->get_results(
                "SHOW COLUMNS FROM $table_scans LIKE 'total_users'"
            );

            if (empty($column_exists)) {
                $wpdb->query(
                    "ALTER TABLE $table_scans
                    ADD COLUMN total_users int(11) DEFAULT 0 AFTER scan_date"
                );
            }
        }

        // Tabela de scans
        $sql_scans = "CREATE TABLE IF NOT EXISTS $table_scans (
            scan_id bigint(20) NOT NULL AUTO_INCREMENT,
            scan_date datetime NOT NULL,
            total_users int(11) DEFAULT 0,
            total_users_analyzed int(11) DEFAULT 0,
            duplicates_found int(11) DEFAULT 0,
            scan_status varchar(20) NOT NULL DEFAULT 'in_progress',
            scan_by_user_id bigint(20) NOT NULL,
            PRIMARY KEY (scan_id),
            KEY scan_date (scan_date),
            KEY scan_status (scan_status)
        ) $charset_collate;";

        // Tabela de pares duplicados
        $table_pairs = $wpdb->prefix . 'eau_duplicate_pairs';
        $sql_pairs = "CREATE TABLE IF NOT EXISTS $table_pairs (
            pair_id bigint(20) NOT NULL AUTO_INCREMENT,
            scan_id bigint(20) NOT NULL,
            user_id_1 bigint(20) NOT NULL,
            user_id_2 bigint(20) NOT NULL,
            similarity_score decimal(5,2) NOT NULL,
            match_details longtext,
            pair_status varchar(20) NOT NULL DEFAULT 'pending',
            reviewed_by_user_id bigint(20) DEFAULT NULL,
            reviewed_date datetime DEFAULT NULL,
            merged_into_user_id bigint(20) DEFAULT NULL,
            PRIMARY KEY (pair_id),
            KEY scan_id (scan_id),
            KEY user_id_1 (user_id_1),
            KEY user_id_2 (user_id_2),
            KEY pair_status (pair_status),
            KEY similarity_score (similarity_score)
        ) $charset_collate;";

        // Tabela de exclusões
        $table_exclusions = $wpdb->prefix . 'eau_duplicate_exclusions';
        $sql_exclusions = "CREATE TABLE IF NOT EXISTS $table_exclusions (
            exclusion_id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id_1 bigint(20) NOT NULL,
            user_id_2 bigint(20) NOT NULL,
            exclusion_type varchar(20) NOT NULL,
            created_by_user_id bigint(20) NOT NULL,
            created_date datetime NOT NULL,
            PRIMARY KEY (exclusion_id),
            UNIQUE KEY user_pair (user_id_1, user_id_2),
            KEY exclusion_type (exclusion_type)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_scans);
        dbDelta($sql_pairs);
        dbDelta($sql_exclusions);
    }

    /**
     * Remove todas as tabelas (usado na desinstalação)
     */
    public static function drop_tables() {
        global $wpdb;

        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}eau_duplicate_scans");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}eau_duplicate_pairs");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}eau_duplicate_exclusions");
    }

    /**
     * Verifica se as tabelas existem
     */
    public static function tables_exist() {
        global $wpdb;

        $table_scans = $wpdb->prefix . 'eau_duplicate_scans';
        $table_pairs = $wpdb->prefix . 'eau_duplicate_pairs';
        $table_exclusions = $wpdb->prefix . 'eau_duplicate_exclusions';

        $scans_exist = $wpdb->get_var("SHOW TABLES LIKE '$table_scans'") === $table_scans;
        $pairs_exist = $wpdb->get_var("SHOW TABLES LIKE '$table_pairs'") === $table_pairs;
        $exclusions_exist = $wpdb->get_var("SHOW TABLES LIKE '$table_exclusions'") === $table_exclusions;

        return $scans_exist && $pairs_exist && $exclusions_exist;
    }
}
