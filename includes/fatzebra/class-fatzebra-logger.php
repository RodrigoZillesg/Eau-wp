<?php
/**
 * Fat Zebra Logger
 *
 * Sistema de logging para o gateway Fat Zebra.
 * Armazena logs em tabela customizada para auditoria.
 *
 * @package EauSystem
 * @subpackage FatZebra
 * @since 1.70.0
 */

namespace EauSystem\FatZebra;

// Se este arquivo foi chamado diretamente, aborta.
if (!defined('WPINC')) {
    die;
}

class FatZebra_Logger {

    /**
     * Table name (without prefix)
     */
    const TABLE_NAME = 'eau_fatzebra_logs';

    /**
     * Log levels
     */
    const LEVEL_DEBUG   = 'debug';
    const LEVEL_INFO    = 'info';
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR   = 'error';

    /**
     * Max logs to keep (auto-cleanup)
     */
    const MAX_LOGS = 10000;

    /**
     * Log retention days
     */
    const RETENTION_DAYS = 90;

    /**
     * Create logs table
     *
     * @return void
     */
    public static function create_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE_NAME;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            level VARCHAR(20) NOT NULL DEFAULT 'info',
            message TEXT NOT NULL,
            context LONGTEXT NULL,
            user_id BIGINT UNSIGNED NULL,
            ip_address VARCHAR(45) NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_level (level),
            INDEX idx_created_at (created_at),
            INDEX idx_user_id (user_id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Check if table exists
     *
     * @return bool
     */
    public static function table_exists() {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;
        return $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
    }

    /**
     * Log a message
     *
     * @param string $level   Log level (debug, info, warning, error)
     * @param string $message Log message
     * @param array  $context Additional context data
     * @return bool
     */
    public static function log($level, $message, $context = array()) {
        global $wpdb;

        // Garante que tabela existe
        if (!self::table_exists()) {
            self::create_table();
        }

        // Valida level
        $valid_levels = array(self::LEVEL_DEBUG, self::LEVEL_INFO, self::LEVEL_WARNING, self::LEVEL_ERROR);
        if (!in_array($level, $valid_levels)) {
            $level = self::LEVEL_INFO;
        }

        // Prepara context
        $context_json = null;
        if (!empty($context)) {
            // Remove dados sensíveis
            $context = self::sanitize_context($context);
            $context_json = wp_json_encode($context);
        }

        // User ID
        $user_id = get_current_user_id();
        if ($user_id === 0) {
            $user_id = null;
        }

        // IP Address
        $ip = self::get_ip();

        // Insere log
        $result = $wpdb->insert(
            $wpdb->prefix . self::TABLE_NAME,
            array(
                'level'      => $level,
                'message'    => sanitize_text_field($message),
                'context'    => $context_json,
                'user_id'    => $user_id,
                'ip_address' => $ip,
                'created_at' => current_time('mysql'),
            ),
            array('%s', '%s', '%s', '%d', '%s', '%s')
        );

        // Auto-cleanup (1% chance to run)
        if (wp_rand(1, 100) === 1) {
            self::cleanup();
        }

        return $result !== false;
    }

    /**
     * Log debug message
     *
     * @param string $message Message
     * @param array  $context Context
     */
    public static function debug($message, $context = array()) {
        self::log(self::LEVEL_DEBUG, $message, $context);
    }

    /**
     * Log info message
     *
     * @param string $message Message
     * @param array  $context Context
     */
    public static function info($message, $context = array()) {
        self::log(self::LEVEL_INFO, $message, $context);
    }

    /**
     * Log warning message
     *
     * @param string $message Message
     * @param array  $context Context
     */
    public static function warning($message, $context = array()) {
        self::log(self::LEVEL_WARNING, $message, $context);
    }

    /**
     * Log error message
     *
     * @param string $message Message
     * @param array  $context Context
     */
    public static function error($message, $context = array()) {
        self::log(self::LEVEL_ERROR, $message, $context);
    }

    /**
     * Get logs with pagination
     *
     * @param array $args Query arguments
     * @return array
     */
    public static function get_logs($args = array()) {
        global $wpdb;

        $defaults = array(
            'level'    => '',
            'search'   => '',
            'user_id'  => null,
            'per_page' => 50,
            'page'     => 1,
            'order'    => 'DESC',
        );

        $args = wp_parse_args($args, $defaults);
        $table_name = $wpdb->prefix . self::TABLE_NAME;

        // Build WHERE clause
        $where = array('1=1');
        $prepare_args = array();

        if (!empty($args['level'])) {
            $where[] = 'level = %s';
            $prepare_args[] = $args['level'];
        }

        if (!empty($args['search'])) {
            $where[] = '(message LIKE %s OR context LIKE %s)';
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $prepare_args[] = $search;
            $prepare_args[] = $search;
        }

        if (!empty($args['user_id'])) {
            $where[] = 'user_id = %d';
            $prepare_args[] = $args['user_id'];
        }

        $where_sql = implode(' AND ', $where);

        // Order
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

        // Pagination
        $per_page = absint($args['per_page']);
        $page = max(1, absint($args['page']));
        $offset = ($page - 1) * $per_page;

        // Get total count
        $count_sql = "SELECT COUNT(*) FROM $table_name WHERE $where_sql";
        if (!empty($prepare_args)) {
            $count_sql = $wpdb->prepare($count_sql, $prepare_args);
        }
        $total = (int) $wpdb->get_var($count_sql);

        // Get logs
        $sql = "SELECT * FROM $table_name WHERE $where_sql ORDER BY created_at $order LIMIT %d OFFSET %d";
        $prepare_args[] = $per_page;
        $prepare_args[] = $offset;

        $logs = $wpdb->get_results($wpdb->prepare($sql, $prepare_args), ARRAY_A);

        // Parse context JSON
        foreach ($logs as &$log) {
            if (!empty($log['context'])) {
                $log['context'] = json_decode($log['context'], true);
            }
        }

        return array(
            'logs'       => $logs,
            'total'      => $total,
            'total_pages' => ceil($total / $per_page),
            'page'       => $page,
            'per_page'   => $per_page,
        );
    }

    /**
     * Get log statistics
     *
     * @return array
     */
    public static function get_stats() {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE_NAME;

        if (!self::table_exists()) {
            return array(
                'total'   => 0,
                'debug'   => 0,
                'info'    => 0,
                'warning' => 0,
                'error'   => 0,
            );
        }

        $stats = $wpdb->get_results(
            "SELECT level, COUNT(*) as count FROM $table_name GROUP BY level",
            ARRAY_A
        );

        $result = array(
            'total'   => 0,
            'debug'   => 0,
            'info'    => 0,
            'warning' => 0,
            'error'   => 0,
        );

        foreach ($stats as $stat) {
            $result[$stat['level']] = (int) $stat['count'];
            $result['total'] += (int) $stat['count'];
        }

        return $result;
    }

    /**
     * Cleanup old logs
     *
     * @return int Number of deleted logs
     */
    public static function cleanup() {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE_NAME;

        if (!self::table_exists()) {
            return 0;
        }

        // Delete logs older than retention period
        $cutoff_date = date('Y-m-d H:i:s', strtotime('-' . self::RETENTION_DAYS . ' days'));

        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $table_name WHERE created_at < %s",
                $cutoff_date
            )
        );

        // Also enforce max logs limit
        $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_name");

        if ($count > self::MAX_LOGS) {
            $to_delete = $count - self::MAX_LOGS;
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM $table_name ORDER BY created_at ASC LIMIT %d",
                    $to_delete
                )
            );
            $deleted += $to_delete;
        }

        if ($deleted > 0) {
            self::log('info', "Cleaned up $deleted old log entries");
        }

        return $deleted;
    }

    /**
     * Clear all logs
     *
     * @return bool
     */
    public static function clear_all() {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE_NAME;

        if (!self::table_exists()) {
            return true;
        }

        $result = $wpdb->query("TRUNCATE TABLE $table_name");

        return $result !== false;
    }

    /**
     * Export logs to CSV
     *
     * @param array $args Filter arguments
     * @return string CSV content
     */
    public static function export_csv($args = array()) {
        $args['per_page'] = 10000; // Max export
        $args['page'] = 1;

        $result = self::get_logs($args);
        $logs = $result['logs'];

        $output = fopen('php://temp', 'r+');

        // Header
        fputcsv($output, array('ID', 'Level', 'Message', 'Context', 'User ID', 'IP Address', 'Created At'));

        // Data
        foreach ($logs as $log) {
            fputcsv($output, array(
                $log['id'],
                $log['level'],
                $log['message'],
                is_array($log['context']) ? wp_json_encode($log['context']) : $log['context'],
                $log['user_id'] ?? '',
                $log['ip_address'] ?? '',
                $log['created_at'],
            ));
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    /**
     * Remove sensitive data from context
     *
     * @param array $context Context array
     * @return array Sanitized context
     */
    private static function sanitize_context($context) {
        $sensitive_keys = array(
            'card_number',
            'cvv',
            'cvc',
            'password',
            'token',
            'secret',
            'auth',
            'authorization',
        );

        foreach ($context as $key => $value) {
            // Check if key contains sensitive word
            foreach ($sensitive_keys as $sensitive) {
                if (stripos($key, $sensitive) !== false) {
                    $context[$key] = '[REDACTED]';
                    break;
                }
            }

            // Recursively sanitize nested arrays
            if (is_array($value)) {
                $context[$key] = self::sanitize_context($value);
            }
        }

        return $context;
    }

    /**
     * Get client IP address
     *
     * @return string
     */
    private static function get_ip() {
        $ip = '';

        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
        } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $ip = $_SERVER['HTTP_X_REAL_IP'];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            $ip = '';
        }

        return $ip;
    }
}
