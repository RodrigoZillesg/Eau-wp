<?php
/**
 * Course Purchases Database
 *
 * Gerencia a tabela de compras de cursos Open Learning.
 *
 * @package EauSystem
 * @subpackage Checkout
 * @since 1.70.0
 */

namespace EauSystem\Checkout;

// Se este arquivo foi chamado diretamente, aborta.
if (!defined('WPINC')) {
    die;
}

class Eau_Course_Purchases {

    /**
     * Table name (without prefix)
     */
    const TABLE_NAME = 'eau_course_purchases';

    /**
     * Purchase statuses
     */
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_PAID = 'paid';
    const STATUS_FAILED = 'failed';
    const STATUS_REFUNDED = 'refunded';

    /**
     * Create the table
     */
    public static function create_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE_NAME;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            course_id BIGINT UNSIGNED NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            currency VARCHAR(3) DEFAULT 'AUD',
            status VARCHAR(20) DEFAULT 'pending',
            payment_id BIGINT UNSIGNED NULL,
            transaction_id VARCHAR(100) NULL,
            reference VARCHAR(100) NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            paid_at DATETIME NULL,
            access_granted_at DATETIME NULL,
            refunded_at DATETIME NULL,
            notes TEXT NULL,
            INDEX idx_user (user_id),
            INDEX idx_course (course_id),
            INDEX idx_status (status),
            INDEX idx_reference (reference),
            UNIQUE KEY unique_user_course_pending (user_id, course_id, status)
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
     * Get a purchase by ID
     *
     * @param int $id Purchase ID
     * @return array|null
     */
    public static function get_purchase($id) {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE_NAME;

        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id),
            ARRAY_A
        );
    }

    /**
     * Get purchase by reference
     *
     * @param string $reference Payment reference
     * @return array|null
     */
    public static function get_purchase_by_reference($reference) {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE_NAME;

        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table_name WHERE reference = %s", $reference),
            ARRAY_A
        );
    }

    /**
     * Get user purchases
     *
     * @param int    $user_id User ID
     * @param string $status  Filter by status (optional)
     * @return array
     */
    public static function get_user_purchases($user_id, $status = null) {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE_NAME;

        $sql = "SELECT p.*, c.post_title as course_title
                FROM $table_name p
                LEFT JOIN {$wpdb->posts} c ON p.course_id = c.ID
                WHERE p.user_id = %d";

        $params = array($user_id);

        if ($status) {
            $sql .= " AND p.status = %s";
            $params[] = $status;
        }

        $sql .= " ORDER BY p.created_at DESC";

        return $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
    }

    /**
     * Create a new purchase
     *
     * @param array $data Purchase data
     * @return int|false Purchase ID or false on failure
     */
    public static function create_purchase($data) {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE_NAME;

        // Check if user already has a pending/paid purchase for this course
        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, status FROM $table_name
                 WHERE user_id = %d AND course_id = %d AND status IN ('pending', 'processing', 'paid')
                 ORDER BY created_at DESC LIMIT 1",
                $data['user_id'],
                $data['course_id']
            ),
            ARRAY_A
        );

        if ($existing) {
            if ($existing['status'] === 'paid') {
                return new \WP_Error('already_purchased', 'User has already purchased this course');
            }

            // Return existing pending purchase
            return (int) $existing['id'];
        }

        $result = $wpdb->insert(
            $table_name,
            array(
                'user_id'    => absint($data['user_id']),
                'course_id'  => absint($data['course_id']),
                'amount'     => floatval($data['amount']),
                'currency'   => sanitize_text_field($data['currency'] ?? 'AUD'),
                'status'     => self::STATUS_PENDING,
                'reference'  => sanitize_text_field($data['reference'] ?? ''),
                'created_at' => current_time('mysql'),
            ),
            array('%d', '%d', '%f', '%s', '%s', '%s', '%s')
        );

        if ($result) {
            return $wpdb->insert_id;
        }

        return false;
    }

    /**
     * Update purchase status
     *
     * @param int    $id     Purchase ID
     * @param string $status New status
     * @param array  $extra  Extra fields to update
     * @return bool
     */
    public static function update_status($id, $status, $extra = array()) {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE_NAME;

        $update_data = array('status' => $status);
        $update_format = array('%s');

        // Set timestamps based on status
        switch ($status) {
            case self::STATUS_PAID:
                $update_data['paid_at'] = current_time('mysql');
                $update_format[] = '%s';
                break;

            case self::STATUS_REFUNDED:
                $update_data['refunded_at'] = current_time('mysql');
                $update_format[] = '%s';
                break;
        }

        // Merge extra fields
        foreach ($extra as $key => $value) {
            $update_data[$key] = $value;
            $update_format[] = is_numeric($value) ? '%d' : '%s';
        }

        return $wpdb->update(
            $table_name,
            $update_data,
            array('id' => $id),
            $update_format,
            array('%d')
        ) !== false;
    }

    /**
     * Mark purchase as paid
     *
     * @param int    $id             Purchase ID
     * @param string $transaction_id Transaction ID
     * @param int    $payment_id     Payment post ID
     * @return bool
     */
    public static function mark_paid($id, $transaction_id = '', $payment_id = null) {
        return self::update_status($id, self::STATUS_PAID, array(
            'transaction_id' => $transaction_id,
            'payment_id'     => $payment_id,
        ));
    }

    /**
     * Mark purchase as failed
     *
     * @param int    $id    Purchase ID
     * @param string $notes Failure notes
     * @return bool
     */
    public static function mark_failed($id, $notes = '') {
        return self::update_status($id, self::STATUS_FAILED, array(
            'notes' => $notes,
        ));
    }

    /**
     * Check if user has access to a course
     *
     * @param int $user_id   User ID
     * @param int $course_id Course ID
     * @return bool
     */
    public static function user_has_access($user_id, $course_id) {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE_NAME;

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name
                 WHERE user_id = %d AND course_id = %d AND status = 'paid'",
                $user_id,
                $course_id
            )
        );

        return $count > 0;
    }

    /**
     * Grant course access
     *
     * @param int $id Purchase ID
     * @return bool
     */
    public static function grant_access($id) {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE_NAME;

        return $wpdb->update(
            $table_name,
            array('access_granted_at' => current_time('mysql')),
            array('id' => $id),
            array('%s'),
            array('%d')
        ) !== false;
    }

    /**
     * Get pending purchases (for reminders)
     *
     * @param int $days_old Minimum age in days
     * @return array
     */
    public static function get_pending_purchases($days_old = 3) {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE_NAME;
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-$days_old days"));

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT p.*, u.user_email, u.display_name, c.post_title as course_title
                 FROM $table_name p
                 LEFT JOIN {$wpdb->users} u ON p.user_id = u.ID
                 LEFT JOIN {$wpdb->posts} c ON p.course_id = c.ID
                 WHERE p.status = 'pending' AND p.created_at < %s
                 ORDER BY p.created_at ASC",
                $cutoff_date
            ),
            ARRAY_A
        );
    }

    /**
     * Get purchase statistics
     *
     * @return array
     */
    public static function get_stats() {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE_NAME;

        if (!self::table_exists()) {
            return array(
                'total'     => 0,
                'pending'   => 0,
                'paid'      => 0,
                'failed'    => 0,
                'refunded'  => 0,
                'revenue'   => 0,
            );
        }

        $stats = $wpdb->get_results(
            "SELECT status, COUNT(*) as count, SUM(amount) as total
             FROM $table_name
             GROUP BY status",
            ARRAY_A
        );

        $result = array(
            'total'     => 0,
            'pending'   => 0,
            'processing' => 0,
            'paid'      => 0,
            'failed'    => 0,
            'refunded'  => 0,
            'revenue'   => 0,
        );

        foreach ($stats as $stat) {
            $result[$stat['status']] = (int) $stat['count'];
            $result['total'] += (int) $stat['count'];

            if ($stat['status'] === 'paid') {
                $result['revenue'] = (float) $stat['total'];
            }
        }

        return $result;
    }

    /**
     * Delete old failed/pending purchases
     *
     * @param int $days_old Minimum age in days
     * @return int Number of deleted records
     */
    public static function cleanup($days_old = 30) {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE_NAME;
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-$days_old days"));

        return $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $table_name
                 WHERE status IN ('pending', 'failed') AND created_at < %s",
                $cutoff_date
            )
        );
    }
}
