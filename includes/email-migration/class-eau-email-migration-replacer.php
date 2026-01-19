<?php
namespace EauSystem\EmailMigration;

/**
 * Email Replacement Handler
 *
 * Handles global replacement of old email with new email across all tables
 * Uses SQL transactions for safety
 *
 * @since 1.62.0
 */
class Eau_Email_Migration_Replacer {

    /**
     * Replace email in all relevant tables
     *
     * @param int $user_id User ID
     * @param string $old_email Old email address
     * @param string $new_email New email address
     * @return array|\WP_Error Array of changes made or WP_Error on failure
     */
    public static function replace_all($user_id, $old_email, $new_email) {
        global $wpdb;

        $changes = array();

        try {
            // Start transaction
            $wpdb->query('START TRANSACTION');

            // 1. Update wp_users
            $result = self::update_users_table($user_id, $new_email);
            $changes['wp_users'] = $result;

            // 2. Update wp_comments
            $result = self::update_comments_table($user_id, $old_email, $new_email);
            $changes['wp_comments'] = $result;

            // 3. Update wp_eau_membership_applications
            $result = self::update_membership_applications($old_email, $new_email);
            $changes['wp_eau_membership_applications'] = $result;

            // 4. Update wp_postmeta (event registrations)
            $result = self::update_event_registrations($old_email, $new_email);
            $changes['wp_postmeta_events'] = $result;

            // 5. Update wp_usermeta (except secondary email)
            $result = self::update_usermeta($user_id, $old_email, $new_email);
            $changes['wp_usermeta'] = $result;

            // 6. Ensure secondary email has the old value
            update_user_meta($user_id, Eau_Email_Migration_Database::META_SECONDARY_EMAIL, $old_email);

            // Commit transaction
            $wpdb->query('COMMIT');

            // Log the migration
            Eau_Email_Migration_Database::log_migration(
                $user_id,
                $old_email,
                $new_email,
                $changes,
                Eau_Email_Migration_Database::STATUS_COMPLETED,
                get_current_user_id() !== $user_id ? get_current_user_id() : null
            );

            return $changes;

        } catch (\Exception $e) {
            // Rollback on error
            $wpdb->query('ROLLBACK');

            // Log failed migration
            Eau_Email_Migration_Database::log_migration(
                $user_id,
                $old_email,
                $new_email,
                array('error' => $e->getMessage()),
                Eau_Email_Migration_Database::STATUS_FAILED,
                get_current_user_id() !== $user_id ? get_current_user_id() : null
            );

            return new \WP_Error('replace_failed', $e->getMessage());
        }
    }

    /**
     * Update wp_users table
     *
     * @param int $user_id User ID
     * @param string $new_email New email
     * @return int Number of rows affected
     */
    private static function update_users_table($user_id, $new_email) {
        global $wpdb;

        $result = $wpdb->update(
            $wpdb->users,
            array('user_email' => $new_email),
            array('ID' => $user_id),
            array('%s'),
            array('%d')
        );

        if ($result === false) {
            throw new \Exception('Failed to update wp_users table: ' . $wpdb->last_error);
        }

        // Also update user cache
        clean_user_cache($user_id);

        return $result;
    }

    /**
     * Update wp_comments table
     *
     * @param int $user_id User ID
     * @param string $old_email Old email
     * @param string $new_email New email
     * @return int Number of rows affected
     */
    private static function update_comments_table($user_id, $old_email, $new_email) {
        global $wpdb;

        $total = 0;

        // Update by user_id
        $result = $wpdb->update(
            $wpdb->comments,
            array('comment_author_email' => $new_email),
            array('user_id' => $user_id),
            array('%s'),
            array('%d')
        );

        if ($result !== false) {
            $total += $result;
        }

        // Update by email (for comments made before user was logged in)
        $result = $wpdb->update(
            $wpdb->comments,
            array('comment_author_email' => $new_email),
            array('comment_author_email' => $old_email),
            array('%s'),
            array('%s')
        );

        if ($result !== false) {
            $total += $result;
        }

        return $total;
    }

    /**
     * Update wp_eau_membership_applications table
     *
     * @param string $old_email Old email
     * @param string $new_email New email
     * @return int Number of rows affected
     */
    private static function update_membership_applications($old_email, $new_email) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'eau_membership_applications';

        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
            return 0;
        }

        $result = $wpdb->update(
            $table_name,
            array('email' => $new_email),
            array('email' => $old_email),
            array('%s'),
            array('%s')
        );

        return $result !== false ? $result : 0;
    }

    /**
     * Update wp_postmeta for event registrations
     *
     * @param string $old_email Old email
     * @param string $new_email New email
     * @return int Number of rows affected
     */
    private static function update_event_registrations($old_email, $new_email) {
        global $wpdb;

        $total = 0;

        // Email meta keys used in event registrations
        $email_meta_keys = array(
            'attendee_email',
            'reg_attendee_email',
            'eau_reg_attendee_email',
        );

        foreach ($email_meta_keys as $meta_key) {
            $result = $wpdb->update(
                $wpdb->postmeta,
                array('meta_value' => $new_email),
                array(
                    'meta_key' => $meta_key,
                    'meta_value' => $old_email,
                ),
                array('%s'),
                array('%s', '%s')
            );

            if ($result !== false) {
                $total += $result;
            }
        }

        return $total;
    }

    /**
     * Update wp_usermeta (except secondary email field)
     *
     * @param int $user_id User ID
     * @param string $old_email Old email
     * @param string $new_email New email
     * @return int Number of rows affected
     */
    private static function update_usermeta($user_id, $old_email, $new_email) {
        global $wpdb;

        // Find all usermeta entries with the old email value
        // Exclude our secondary email field
        $meta_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT umeta_id FROM {$wpdb->usermeta}
             WHERE user_id = %d
             AND meta_value = %s
             AND meta_key != %s",
            $user_id,
            $old_email,
            Eau_Email_Migration_Database::META_SECONDARY_EMAIL
        ));

        if (empty($meta_ids)) {
            return 0;
        }

        // Update each found entry
        $placeholders = implode(',', array_fill(0, count($meta_ids), '%d'));
        $query_params = array_merge(array($new_email), $meta_ids);

        $result = $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->usermeta}
             SET meta_value = %s
             WHERE umeta_id IN ($placeholders)",
            $query_params
        ));

        return $result !== false ? $result : 0;
    }

    /**
     * Rollback a migration using the log
     *
     * @param int $log_id Log ID
     * @param int $admin_user_id Admin performing the rollback
     * @return true|\WP_Error
     */
    public static function rollback_migration($log_id, $admin_user_id) {
        // Check admin permission
        $admin_mem_type = get_user_meta($admin_user_id, 'mem_type', true);
        if (!in_array($admin_mem_type, array('superAdmin', 'Admin'))) {
            return new \WP_Error('permission_denied', __('Admin access required.', 'eau-system'));
        }

        // Get log entry
        $log = Eau_Email_Migration_Database::get_log($log_id);
        if (!$log) {
            return new \WP_Error('log_not_found', __('Migration log not found.', 'eau-system'));
        }

        // Check if already reverted
        if ($log['status'] === Eau_Email_Migration_Database::STATUS_REVERTED) {
            return new \WP_Error('already_reverted', __('This migration has already been reverted.', 'eau-system'));
        }

        // Perform rollback (swap old and new emails)
        $result = self::replace_all(
            $log['user_id'],
            $log['new_email'],  // Current email (was new)
            $log['old_email']   // Revert to this
        );

        if (is_wp_error($result)) {
            return $result;
        }

        // Update original log status
        Eau_Email_Migration_Database::update_status($log_id, Eau_Email_Migration_Database::STATUS_REVERTED);

        // Reset user migration status
        update_user_meta(
            $log['user_id'],
            Eau_Email_Migration_Database::META_MIGRATION_STATUS,
            Eau_Email_Migration_Database::USER_STATUS_PENDING
        );

        return true;
    }

    /**
     * Scan for all occurrences of an email in the database
     * Useful for debugging and verification
     *
     * @param string $email Email to search for
     * @return array Array of occurrences by table
     */
    public static function scan_email_occurrences($email) {
        global $wpdb;

        $occurrences = array();

        // wp_users
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->users} WHERE user_email = %s",
            $email
        ));
        if ($count > 0) {
            $occurrences['wp_users'] = (int) $count;
        }

        // wp_comments
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_author_email = %s",
            $email
        ));
        if ($count > 0) {
            $occurrences['wp_comments'] = (int) $count;
        }

        // wp_usermeta
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_value = %s",
            $email
        ));
        if ($count > 0) {
            $occurrences['wp_usermeta'] = (int) $count;
        }

        // wp_postmeta
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_value = %s",
            $email
        ));
        if ($count > 0) {
            $occurrences['wp_postmeta'] = (int) $count;
        }

        // wp_eau_membership_applications
        $table_name = $wpdb->prefix . 'eau_membership_applications';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name) {
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE email = %s",
                $email
            ));
            if ($count > 0) {
                $occurrences['wp_eau_membership_applications'] = (int) $count;
            }
        }

        return $occurrences;
    }
}
