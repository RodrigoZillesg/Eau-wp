<?php
/**
 * Database class for Member-Institution relationship history
 *
 * @package EauSystem
 * @since 1.53.0
 */

namespace EauSystem;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Eau_Member_Institution_Database
 *
 * Handles database operations for member-institution relationship history
 */
class Eau_Member_Institution_Database {

    /**
     * Table name (without prefix)
     */
    const TABLE_NAME = 'eau_member_institution_history';

    /**
     * Get full table name with prefix
     *
     * @return string
     */
    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_NAME;
    }

    /**
     * Create the history table
     *
     * @return bool
     */
    public static function create_table() {
        global $wpdb;

        $table_name = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            institution_id BIGINT UNSIGNED DEFAULT NULL,
            ins_company_id VARCHAR(50) DEFAULT NULL,
            action ENUM('linked', 'unlinked', 'transferred') NOT NULL,
            previous_institution_id BIGINT UNSIGNED DEFAULT NULL,
            previous_ins_company_id VARCHAR(50) DEFAULT NULL,
            new_member_type VARCHAR(50) DEFAULT NULL,
            previous_member_type VARCHAR(50) DEFAULT NULL,
            performed_by BIGINT UNSIGNED DEFAULT NULL,
            reason TEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_user_id (user_id),
            INDEX idx_institution_id (institution_id),
            INDEX idx_ins_company_id (ins_company_id),
            INDEX idx_action (action),
            INDEX idx_created_at (created_at)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Verify table was created
        $table_exists = $wpdb->get_var(
            $wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $table_name
            )
        ) === $table_name;

        return $table_exists;
    }

    /**
     * Drop the history table (use with caution)
     *
     * @return bool
     */
    public static function drop_table() {
        global $wpdb;

        $table_name = self::get_table_name();
        $wpdb->query("DROP TABLE IF EXISTS {$table_name}");

        return true;
    }

    /**
     * Check if table exists
     *
     * @return bool
     */
    public static function table_exists() {
        global $wpdb;

        $table_name = self::get_table_name();
        return $wpdb->get_var(
            $wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $table_name
            )
        ) === $table_name;
    }

    /**
     * Insert a history record
     *
     * @param array $data Record data
     * @return int|false Insert ID or false on failure
     */
    public static function insert($data) {
        global $wpdb;

        $defaults = array(
            'user_id' => 0,
            'institution_id' => null,
            'ins_company_id' => null,
            'action' => 'linked',
            'previous_institution_id' => null,
            'previous_ins_company_id' => null,
            'new_member_type' => null,
            'previous_member_type' => null,
            'performed_by' => get_current_user_id(),
            'reason' => null,
            'created_at' => current_time('mysql'),
        );

        $data = wp_parse_args($data, $defaults);

        $result = $wpdb->insert(
            self::get_table_name(),
            $data,
            array(
                '%d', // user_id
                '%d', // institution_id
                '%s', // ins_company_id
                '%s', // action
                '%d', // previous_institution_id
                '%s', // previous_ins_company_id
                '%s', // new_member_type
                '%s', // previous_member_type
                '%d', // performed_by
                '%s', // reason
                '%s', // created_at
            )
        );

        if ($result === false) {
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * Get history records for a user
     *
     * @param int $user_id User ID
     * @param array $args Query arguments
     * @return array
     */
    public static function get_user_history($user_id, $args = array()) {
        global $wpdb;

        $defaults = array(
            'limit' => 50,
            'offset' => 0,
            'order' => 'DESC',
            'action' => null,
        );

        $args = wp_parse_args($args, $defaults);
        $table_name = self::get_table_name();

        $where = $wpdb->prepare("WHERE user_id = %d", $user_id);

        if (!empty($args['action'])) {
            $where .= $wpdb->prepare(" AND action = %s", $args['action']);
        }

        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        $limit = absint($args['limit']);
        $offset = absint($args['offset']);

        $sql = "SELECT * FROM {$table_name} {$where} ORDER BY created_at {$order} LIMIT {$limit} OFFSET {$offset}";

        return $wpdb->get_results($sql);
    }

    /**
     * Get history records for an institution
     *
     * @param int $institution_id Institution post ID
     * @param array $args Query arguments
     * @return array
     */
    public static function get_institution_history($institution_id, $args = array()) {
        global $wpdb;

        $defaults = array(
            'limit' => 50,
            'offset' => 0,
            'order' => 'DESC',
            'action' => null,
        );

        $args = wp_parse_args($args, $defaults);
        $table_name = self::get_table_name();

        $where = $wpdb->prepare(
            "WHERE institution_id = %d OR previous_institution_id = %d",
            $institution_id,
            $institution_id
        );

        if (!empty($args['action'])) {
            $where .= $wpdb->prepare(" AND action = %s", $args['action']);
        }

        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        $limit = absint($args['limit']);
        $offset = absint($args['offset']);

        $sql = "SELECT * FROM {$table_name} {$where} ORDER BY created_at {$order} LIMIT {$limit} OFFSET {$offset}";

        return $wpdb->get_results($sql);
    }

    /**
     * Get the last action for a user
     *
     * @param int $user_id User ID
     * @return object|null
     */
    public static function get_last_action($user_id) {
        global $wpdb;

        $table_name = self::get_table_name();

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE user_id = %d ORDER BY created_at DESC LIMIT 1",
                $user_id
            )
        );
    }

    /**
     * Count total records for a user
     *
     * @param int $user_id User ID
     * @return int
     */
    public static function count_user_records($user_id) {
        global $wpdb;

        $table_name = self::get_table_name();

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} WHERE user_id = %d",
                $user_id
            )
        );
    }
}
