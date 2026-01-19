<?php
namespace EauSystem\EmailMigration;

/**
 * Database helper for Email Migration System
 *
 * Creates and manages table for migration logs
 *
 * @since 1.62.0
 */
class Eau_Email_Migration_Database {

    /**
     * Table name (without prefix)
     */
    const TABLE_LOG = 'eau_email_migration_log';

    /**
     * Migration statuses
     */
    const STATUS_COMPLETED = 'completed';
    const STATUS_REVERTED = 'reverted';
    const STATUS_FAILED = 'failed';

    /**
     * User migration statuses
     */
    const USER_STATUS_PENDING = 'pending';
    const USER_STATUS_AWAITING = 'awaiting_verification';
    const USER_STATUS_VERIFIED = 'verified';
    const USER_STATUS_SKIPPED = 'skipped';

    /**
     * Meta keys used by the system
     */
    const META_SECONDARY_EMAIL = 'mem_secondary_email';
    const META_MIGRATION_STATUS = 'mem_email_migration_status';
    const META_MIGRATION_DATE = 'mem_email_migration_date';
    const META_MIGRATION_OLD = 'mem_email_migration_old';
    const META_VERIFICATION_CODE = 'mem_email_verification_code';
    const META_VERIFICATION_EXPIRY = 'mem_email_verification_expiry';
    const META_PENDING_NEW_EMAIL = 'mem_email_pending_new';

    /**
     * Verification code expiry in seconds (15 minutes)
     */
    const CODE_EXPIRY_SECONDS = 900;

    /**
     * Get full table name with prefix
     *
     * @param string $table Table constant
     * @return string
     */
    public static function get_table_name($table = null) {
        global $wpdb;
        return $wpdb->prefix . ($table ?: self::TABLE_LOG);
    }

    /**
     * Check if table exists
     *
     * @return bool
     */
    public static function table_exists() {
        global $wpdb;
        $table_name = self::get_table_name();
        return $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
    }

    /**
     * Create migration log table
     */
    public static function create_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = self::get_table_name();

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $sql = "CREATE TABLE $table_name (
            log_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            old_email VARCHAR(255) NOT NULL,
            new_email VARCHAR(255) NOT NULL,
            tables_updated LONGTEXT,
            status VARCHAR(20) DEFAULT 'completed',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            created_by BIGINT UNSIGNED DEFAULT NULL,
            PRIMARY KEY (log_id),
            KEY idx_user_id (user_id),
            KEY idx_old_email (old_email),
            KEY idx_created_at (created_at)
        ) $charset_collate;";

        dbDelta($sql);
    }

    /**
     * Log a migration
     *
     * @param int $user_id User ID
     * @param string $old_email Old email
     * @param string $new_email New email
     * @param array $tables_updated Array of updated tables
     * @param string $status Status of migration
     * @param int|null $created_by Admin user who performed (null if self)
     * @return int|false Insert ID or false on failure
     */
    public static function log_migration($user_id, $old_email, $new_email, $tables_updated, $status = 'completed', $created_by = null) {
        global $wpdb;
        $table_name = self::get_table_name();

        $result = $wpdb->insert(
            $table_name,
            array(
                'user_id' => $user_id,
                'old_email' => $old_email,
                'new_email' => $new_email,
                'tables_updated' => json_encode($tables_updated),
                'status' => $status,
                'created_by' => $created_by,
            ),
            array('%d', '%s', '%s', '%s', '%s', '%d')
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Get migration log for a user
     *
     * @param int $user_id User ID
     * @return array
     */
    public static function get_user_migrations($user_id) {
        global $wpdb;
        $table_name = self::get_table_name();

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %d ORDER BY created_at DESC",
            $user_id
        ), ARRAY_A);
    }

    /**
     * Get all migration logs with pagination
     *
     * @param array $args Query args (page, per_page, search, status)
     * @return array
     */
    public static function get_migrations($args = array()) {
        global $wpdb;
        $table_name = self::get_table_name();

        $defaults = array(
            'page' => 1,
            'per_page' => 20,
            'search' => '',
            'status' => '',
            'orderby' => 'created_at',
            'order' => 'DESC',
        );

        $args = wp_parse_args($args, $defaults);
        $offset = ($args['page'] - 1) * $args['per_page'];

        $where = array('1=1');
        $params = array();

        if (!empty($args['search'])) {
            $where[] = "(old_email LIKE %s OR new_email LIKE %s)";
            $params[] = '%' . $wpdb->esc_like($args['search']) . '%';
            $params[] = '%' . $wpdb->esc_like($args['search']) . '%';
        }

        if (!empty($args['status'])) {
            $where[] = "status = %s";
            $params[] = $args['status'];
        }

        $where_clause = implode(' AND ', $where);
        $order_clause = sprintf('%s %s', esc_sql($args['orderby']), esc_sql($args['order']));

        // Count total
        $count_sql = "SELECT COUNT(*) FROM $table_name WHERE $where_clause";
        $total = empty($params)
            ? $wpdb->get_var($count_sql)
            : $wpdb->get_var($wpdb->prepare($count_sql, $params));

        // Get results
        $sql = "SELECT l.*, u.display_name as user_name
                FROM $table_name l
                LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID
                WHERE $where_clause
                ORDER BY $order_clause
                LIMIT %d OFFSET %d";

        $params[] = $args['per_page'];
        $params[] = $offset;

        $results = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);

        return array(
            'logs' => $results,
            'total' => (int) $total,
            'page' => $args['page'],
            'per_page' => $args['per_page'],
            'total_pages' => ceil($total / $args['per_page']),
        );
    }

    /**
     * Update migration status
     *
     * @param int $log_id Log ID
     * @param string $status New status
     * @return bool
     */
    public static function update_status($log_id, $status) {
        global $wpdb;
        $table_name = self::get_table_name();

        return $wpdb->update(
            $table_name,
            array('status' => $status),
            array('log_id' => $log_id),
            array('%s'),
            array('%d')
        ) !== false;
    }

    /**
     * Get a single log entry
     *
     * @param int $log_id Log ID
     * @return array|null
     */
    public static function get_log($log_id) {
        global $wpdb;
        $table_name = self::get_table_name();

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE log_id = %d",
            $log_id
        ), ARRAY_A);
    }

    /**
     * Get migration statistics
     *
     * @return array
     */
    public static function get_stats() {
        global $wpdb;
        $table_name = self::get_table_name();

        $stats = array(
            'total_migrations' => 0,
            'completed' => 0,
            'reverted' => 0,
            'pending_users' => 0,
            'verified_users' => 0,
            'skipped_users' => 0,
        );

        // Migration log stats
        $log_stats = $wpdb->get_results(
            "SELECT status, COUNT(*) as count FROM $table_name GROUP BY status",
            ARRAY_A
        );

        foreach ($log_stats as $stat) {
            if ($stat['status'] === self::STATUS_COMPLETED) {
                $stats['completed'] = (int) $stat['count'];
            } elseif ($stat['status'] === self::STATUS_REVERTED) {
                $stats['reverted'] = (int) $stat['count'];
            }
            $stats['total_migrations'] += (int) $stat['count'];
        }

        // User migration status stats
        $stats['pending_users'] = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta}
             WHERE meta_key = %s AND meta_value = %s",
            self::META_MIGRATION_STATUS,
            self::USER_STATUS_PENDING
        ));

        $stats['verified_users'] = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta}
             WHERE meta_key = %s AND meta_value = %s",
            self::META_MIGRATION_STATUS,
            self::USER_STATUS_VERIFIED
        ));

        $stats['skipped_users'] = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta}
             WHERE meta_key = %s AND meta_value = %s",
            self::META_MIGRATION_STATUS,
            self::USER_STATUS_SKIPPED
        ));

        return $stats;
    }
}
