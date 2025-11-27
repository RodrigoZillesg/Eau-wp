<?php
namespace EauSystem;

/**
 * Database helper for Institution Link Requests
 *
 * Manages the wp_eau_institution_requests table for tracking
 * member requests to join institutions.
 *
 * @since 1.44.0
 */
class Eau_Institution_Requests_Database {

    /**
     * Table name (without prefix)
     */
    const TABLE_NAME = 'eau_institution_requests';

    /**
     * Request statuses
     */
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * Get the full table name with prefix
     *
     * @return string
     */
    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_NAME;
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
     * Create the requests table
     */
    public static function create_table() {
        global $wpdb;

        $table_name = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            request_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            institution_id BIGINT UNSIGNED NOT NULL,
            status VARCHAR(20) DEFAULT 'pending',
            request_date DATETIME DEFAULT CURRENT_TIMESTAMP,
            response_date DATETIME NULL,
            responded_by BIGINT UNSIGNED NULL,
            notes TEXT NULL,
            previous_institution_id BIGINT UNSIGNED NULL,
            INDEX idx_user_id (user_id),
            INDEX idx_institution_id (institution_id),
            INDEX idx_status (status),
            INDEX idx_user_status (user_id, status),
            INDEX idx_institution_status (institution_id, status)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Create a new request
     *
     * @param int $user_id User ID
     * @param int $institution_id Institution post ID
     * @return int|false Request ID or false on failure
     */
    public static function create_request($user_id, $institution_id) {
        global $wpdb;

        $result = $wpdb->insert(
            self::get_table_name(),
            array(
                'user_id' => $user_id,
                'institution_id' => $institution_id,
                'status' => self::STATUS_PENDING,
                'request_date' => current_time('mysql'),
            ),
            array('%d', '%d', '%s', '%s')
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Get a request by ID
     *
     * @param int $request_id Request ID
     * @return object|null Request object or null
     */
    public static function get_request($request_id) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . self::get_table_name() . " WHERE request_id = %d",
            $request_id
        ));
    }

    /**
     * Check if user has pending request for institution
     *
     * @param int $user_id User ID
     * @param int $institution_id Institution post ID
     * @return bool
     */
    public static function has_pending_request($user_id, $institution_id) {
        global $wpdb;

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM " . self::get_table_name() . "
            WHERE user_id = %d
            AND institution_id = %d
            AND status = %s",
            $user_id,
            $institution_id,
            self::STATUS_PENDING
        ));

        return $count > 0;
    }

    /**
     * Get all pending requests for a user
     *
     * @param int $user_id User ID
     * @return array Array of request objects
     */
    public static function get_user_pending_requests($user_id) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT r.*, p.post_title as institution_name
            FROM " . self::get_table_name() . " r
            LEFT JOIN {$wpdb->posts} p ON r.institution_id = p.ID
            WHERE r.user_id = %d
            AND r.status = %s
            ORDER BY r.request_date DESC",
            $user_id,
            self::STATUS_PENDING
        ));
    }

    /**
     * Get all requests for a user (all statuses)
     *
     * @param int $user_id User ID
     * @param array $statuses Optional array of statuses to filter
     * @return array Array of request objects
     */
    public static function get_user_requests($user_id, $statuses = array()) {
        global $wpdb;

        $sql = "SELECT r.*, p.post_title as institution_name
                FROM " . self::get_table_name() . " r
                LEFT JOIN {$wpdb->posts} p ON r.institution_id = p.ID
                WHERE r.user_id = %d";

        $params = array($user_id);

        if (!empty($statuses)) {
            $placeholders = implode(',', array_fill(0, count($statuses), '%s'));
            $sql .= " AND r.status IN ($placeholders)";
            $params = array_merge($params, $statuses);
        }

        $sql .= " ORDER BY r.request_date DESC";

        return $wpdb->get_results($wpdb->prepare($sql, ...$params));
    }

    /**
     * Get incoming requests for institutions (for institutionAdmin)
     *
     * @param array $institution_ids Array of institution post IDs
     * @param string $status Status filter (default: pending)
     * @param int $limit Limit
     * @param int $offset Offset
     * @return array Array with 'requests' and 'total'
     */
    public static function get_incoming_requests($institution_ids, $status = 'pending', $limit = 20, $offset = 0) {
        global $wpdb;

        if (empty($institution_ids)) {
            return array('requests' => array(), 'total' => 0);
        }

        $placeholders = implode(',', array_fill(0, count($institution_ids), '%d'));

        // Count total
        $count_sql = "SELECT COUNT(*) FROM " . self::get_table_name() . "
                      WHERE institution_id IN ($placeholders) AND status = %s";
        $count_params = array_merge($institution_ids, array($status));
        $total = $wpdb->get_var($wpdb->prepare($count_sql, ...$count_params));

        // Get requests with user and institution data
        $sql = "SELECT r.*,
                       p.post_title as institution_name,
                       u.display_name as user_name,
                       u.user_email as user_email
                FROM " . self::get_table_name() . " r
                LEFT JOIN {$wpdb->posts} p ON r.institution_id = p.ID
                LEFT JOIN {$wpdb->users} u ON r.user_id = u.ID
                WHERE r.institution_id IN ($placeholders)
                AND r.status = %s
                ORDER BY r.request_date DESC
                LIMIT %d OFFSET %d";

        $params = array_merge($institution_ids, array($status, $limit, $offset));
        $requests = $wpdb->get_results($wpdb->prepare($sql, ...$params));

        return array(
            'requests' => $requests,
            'total' => (int) $total
        );
    }

    /**
     * Update request status
     *
     * @param int $request_id Request ID
     * @param string $status New status
     * @param int|null $responded_by User ID who responded
     * @param string|null $notes Optional notes
     * @return bool Success
     */
    public static function update_status($request_id, $status, $responded_by = null, $notes = null) {
        global $wpdb;

        $data = array(
            'status' => $status,
            'response_date' => current_time('mysql'),
        );
        $format = array('%s', '%s');

        if ($responded_by) {
            $data['responded_by'] = $responded_by;
            $format[] = '%d';
        }

        if ($notes !== null) {
            $data['notes'] = $notes;
            $format[] = '%s';
        }

        return $wpdb->update(
            self::get_table_name(),
            $data,
            array('request_id' => $request_id),
            $format,
            array('%d')
        ) !== false;
    }

    /**
     * Cancel a request (by user)
     *
     * @param int $request_id Request ID
     * @param int $user_id User ID (for verification)
     * @return bool Success
     */
    public static function cancel_request($request_id, $user_id) {
        global $wpdb;

        return $wpdb->update(
            self::get_table_name(),
            array(
                'status' => self::STATUS_CANCELLED,
                'response_date' => current_time('mysql'),
            ),
            array(
                'request_id' => $request_id,
                'user_id' => $user_id,
                'status' => self::STATUS_PENDING,
            ),
            array('%s', '%s'),
            array('%d', '%d', '%s')
        ) !== false;
    }

    /**
     * Save previous institution ID when approving
     *
     * @param int $request_id Request ID
     * @param int $previous_institution_id Previous institution post ID
     * @return bool Success
     */
    public static function save_previous_institution($request_id, $previous_institution_id) {
        global $wpdb;

        return $wpdb->update(
            self::get_table_name(),
            array('previous_institution_id' => $previous_institution_id),
            array('request_id' => $request_id),
            array('%d'),
            array('%d')
        ) !== false;
    }

    /**
     * Get request history for a user (all completed requests)
     *
     * @param int $user_id User ID
     * @param int $limit Limit
     * @param int $offset Offset
     * @return array Array with 'requests' and 'total'
     */
    public static function get_user_history($user_id, $limit = 20, $offset = 0) {
        global $wpdb;

        // Count total
        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM " . self::get_table_name() . "
            WHERE user_id = %d
            AND status IN (%s, %s, %s)",
            $user_id,
            self::STATUS_APPROVED,
            self::STATUS_REJECTED,
            self::STATUS_CANCELLED
        ));

        // Get requests
        $requests = $wpdb->get_results($wpdb->prepare(
            "SELECT r.*,
                    p.post_title as institution_name,
                    ru.display_name as responded_by_name
            FROM " . self::get_table_name() . " r
            LEFT JOIN {$wpdb->posts} p ON r.institution_id = p.ID
            LEFT JOIN {$wpdb->users} ru ON r.responded_by = ru.ID
            WHERE r.user_id = %d
            AND r.status IN (%s, %s, %s)
            ORDER BY COALESCE(r.response_date, r.request_date) DESC
            LIMIT %d OFFSET %d",
            $user_id,
            self::STATUS_APPROVED,
            self::STATUS_REJECTED,
            self::STATUS_CANCELLED,
            $limit,
            $offset
        ));

        return array(
            'requests' => $requests,
            'total' => (int) $total
        );
    }

    /**
     * Get request history for institutions (for institutionAdmin)
     *
     * @param array $institution_ids Array of institution post IDs
     * @param int $limit Limit
     * @param int $offset Offset
     * @return array Array with 'requests' and 'total'
     */
    public static function get_institution_history($institution_ids, $limit = 20, $offset = 0) {
        global $wpdb;

        if (empty($institution_ids)) {
            return array('requests' => array(), 'total' => 0);
        }

        $placeholders = implode(',', array_fill(0, count($institution_ids), '%d'));

        // Count total
        $count_sql = "SELECT COUNT(*) FROM " . self::get_table_name() . "
                      WHERE institution_id IN ($placeholders)
                      AND status IN (%s, %s)";
        $count_params = array_merge($institution_ids, array(self::STATUS_APPROVED, self::STATUS_REJECTED));
        $total = $wpdb->get_var($wpdb->prepare($count_sql, ...$count_params));

        // Get requests with user and institution data
        $sql = "SELECT r.*,
                       p.post_title as institution_name,
                       u.display_name as user_name,
                       u.user_email as user_email,
                       ru.display_name as responded_by_name
                FROM " . self::get_table_name() . " r
                LEFT JOIN {$wpdb->posts} p ON r.institution_id = p.ID
                LEFT JOIN {$wpdb->users} u ON r.user_id = u.ID
                LEFT JOIN {$wpdb->users} ru ON r.responded_by = ru.ID
                WHERE r.institution_id IN ($placeholders)
                AND r.status IN (%s, %s)
                ORDER BY r.response_date DESC
                LIMIT %d OFFSET %d";

        $params = array_merge($institution_ids, array(self::STATUS_APPROVED, self::STATUS_REJECTED, $limit, $offset));
        $requests = $wpdb->get_results($wpdb->prepare($sql, ...$params));

        return array(
            'requests' => $requests,
            'total' => (int) $total
        );
    }

    /**
     * Count pending requests for institutions
     *
     * @param array $institution_ids Array of institution post IDs
     * @return int Count
     */
    public static function count_pending_for_institutions($institution_ids) {
        global $wpdb;

        if (empty($institution_ids)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($institution_ids), '%d'));

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM " . self::get_table_name() . "
            WHERE institution_id IN ($placeholders) AND status = %s",
            ...array_merge($institution_ids, array(self::STATUS_PENDING))
        ));
    }

    /**
     * Delete old cancelled/rejected requests (cleanup)
     *
     * @param int $days_old Delete requests older than X days
     * @return int Number of deleted rows
     */
    public static function cleanup_old_requests($days_old = 90) {
        global $wpdb;

        return $wpdb->query($wpdb->prepare(
            "DELETE FROM " . self::get_table_name() . "
            WHERE status IN (%s, %s)
            AND response_date < DATE_SUB(NOW(), INTERVAL %d DAY)",
            self::STATUS_CANCELLED,
            self::STATUS_REJECTED,
            $days_old
        ));
    }
}
