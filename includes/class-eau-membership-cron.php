<?php
/**
 * Membership Cron Jobs
 *
 * Handles automated tasks for membership system:
 * - Daily check for expiring memberships
 * - Send expiration reminders (60, 30, 7 days before, and on expiration)
 * - Update membership status to expired
 *
 * @package EauSystem
 * @since   1.50.0
 */

namespace EauSystem;

use EauSystem\Email\Email_Membership;

if (!defined('WPINC')) {
    die;
}

class Eau_Membership_Cron {

    /**
     * Cron hook name
     */
    const CRON_HOOK = 'eau_membership_expiry_check';

    /**
     * Reminder intervals (days before expiration)
     */
    const REMINDER_INTERVALS = array(
        '60_days' => 60,
        '30_days' => 30,
        '7_days'  => 7,
        'expired' => 0,
    );

    /**
     * Register hooks
     */
    public static function register_hooks() {
        // Register the cron action
        add_action(self::CRON_HOOK, array(__CLASS__, 'run_expiry_check'));
    }

    /**
     * Setup cron schedule (called on plugin activation)
     */
    public static function setup_cron() {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            // Schedule daily at 9 AM server time
            $timestamp = strtotime('tomorrow 09:00:00');
            wp_schedule_event($timestamp, 'daily', self::CRON_HOOK);
        }
    }

    /**
     * Clear cron schedule (called on plugin deactivation)
     */
    public static function clear_cron() {
        $timestamp = wp_next_scheduled(self::CRON_HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::CRON_HOOK);
        }
    }

    /**
     * Main cron job - check for expiring memberships
     */
    public static function run_expiry_check() {
        // Log start
        error_log('[EAU Membership Cron] Starting expiry check at ' . current_time('mysql'));

        // Get all users with active membership
        $users_with_membership = self::get_users_with_active_membership();

        if (empty($users_with_membership)) {
            error_log('[EAU Membership Cron] No users with active membership found');
            return;
        }

        $today = current_time('Y-m-d');
        $reminders_sent = 0;
        $memberships_expired = 0;

        foreach ($users_with_membership as $user_id) {
            $expiry_date = get_user_meta($user_id, 'mem_membership_expiry_date', true);

            if (empty($expiry_date)) {
                continue;
            }

            $days_until_expiry = self::calculate_days_until_expiry($expiry_date);

            // Check each reminder interval
            foreach (self::REMINDER_INTERVALS as $reminder_type => $days_before) {
                if ($days_until_expiry === $days_before) {
                    // Check if reminder was already sent
                    if (!Email_Membership::was_reminder_sent($user_id, $reminder_type)) {
                        // Send reminder
                        $sent = Email_Membership::send_expiry_reminder($user_id, $reminder_type);

                        if ($sent) {
                            $reminders_sent++;
                            error_log("[EAU Membership Cron] Sent {$reminder_type} reminder to user #{$user_id}");
                        }
                    }
                }
            }

            // If membership has expired, update status
            if ($days_until_expiry < 0) {
                $current_status = get_user_meta($user_id, 'mem_membership_status', true);

                if ($current_status === 'active') {
                    update_user_meta($user_id, 'mem_membership_status', 'expired');
                    $memberships_expired++;
                    error_log("[EAU Membership Cron] Marked user #{$user_id} membership as expired");
                }
            }
        }

        error_log("[EAU Membership Cron] Completed: {$reminders_sent} reminders sent, {$memberships_expired} memberships expired");
    }

    /**
     * Get users with active membership status
     *
     * @return array User IDs
     */
    private static function get_users_with_active_membership() {
        $users = get_users(array(
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key'     => 'mem_membership_status',
                    'value'   => 'active',
                    'compare' => '=',
                ),
                array(
                    'key'     => 'mem_membership_expiry_date',
                    'compare' => 'EXISTS',
                ),
            ),
            'fields' => 'ID',
        ));

        return $users;
    }

    /**
     * Calculate days until expiration
     *
     * @param string $expiry_date Expiry date (Y-m-d)
     * @return int Days until expiry (negative if already expired)
     */
    private static function calculate_days_until_expiry($expiry_date) {
        $today = new \DateTime(current_time('Y-m-d'));
        $expiry = new \DateTime($expiry_date);
        $interval = $today->diff($expiry);

        // Return positive if in future, negative if in past
        return $interval->invert ? -$interval->days : $interval->days;
    }

    /**
     * Get statistics for dashboard widget
     *
     * @return array
     */
    public static function get_expiry_stats() {
        global $wpdb;

        $today = current_time('Y-m-d');
        $in_7_days = date('Y-m-d', strtotime('+7 days'));
        $in_30_days = date('Y-m-d', strtotime('+30 days'));
        $in_60_days = date('Y-m-d', strtotime('+60 days'));

        // Count expiring in each period
        $expiring_7_days = count(get_users(array(
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key'     => 'mem_membership_status',
                    'value'   => 'active',
                    'compare' => '=',
                ),
                array(
                    'key'     => 'mem_membership_expiry_date',
                    'value'   => array($today, $in_7_days),
                    'compare' => 'BETWEEN',
                    'type'    => 'DATE',
                ),
            ),
            'fields' => 'ID',
        )));

        $expiring_30_days = count(get_users(array(
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key'     => 'mem_membership_status',
                    'value'   => 'active',
                    'compare' => '=',
                ),
                array(
                    'key'     => 'mem_membership_expiry_date',
                    'value'   => array($today, $in_30_days),
                    'compare' => 'BETWEEN',
                    'type'    => 'DATE',
                ),
            ),
            'fields' => 'ID',
        )));

        // Count already expired
        $expired = count(get_users(array(
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key'     => 'mem_membership_status',
                    'value'   => 'expired',
                    'compare' => '=',
                ),
            ),
            'fields' => 'ID',
        )));

        return array(
            'expiring_7_days'  => $expiring_7_days,
            'expiring_30_days' => $expiring_30_days,
            'expired'          => $expired,
        );
    }

    /**
     * Get users expiring soon (for admin display)
     *
     * @param int $days Number of days to check
     * @param int $limit Max users to return
     * @return array Users with expiry info
     */
    public static function get_users_expiring_soon($days = 30, $limit = 10) {
        $today = current_time('Y-m-d');
        $target_date = date('Y-m-d', strtotime("+{$days} days"));

        $users = get_users(array(
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key'     => 'mem_membership_status',
                    'value'   => 'active',
                    'compare' => '=',
                ),
                array(
                    'key'     => 'mem_membership_expiry_date',
                    'value'   => array($today, $target_date),
                    'compare' => 'BETWEEN',
                    'type'    => 'DATE',
                ),
            ),
            'number' => $limit,
            'orderby' => 'meta_value',
            'meta_key' => 'mem_membership_expiry_date',
            'order' => 'ASC',
        ));

        $result = array();
        foreach ($users as $user) {
            $expiry_date = get_user_meta($user->ID, 'mem_membership_expiry_date', true);
            $membership_type = get_user_meta($user->ID, 'mem_membership_type', true);

            $result[] = array(
                'user_id'         => $user->ID,
                'name'            => $user->display_name,
                'email'           => $user->user_email,
                'membership_type' => $membership_type,
                'expiry_date'     => $expiry_date,
                'days_remaining'  => self::calculate_days_until_expiry($expiry_date),
            );
        }

        return $result;
    }

    /**
     * Manually trigger expiry check (for testing or admin action)
     *
     * @return array Results of the check
     */
    public static function manual_run() {
        ob_start();
        self::run_expiry_check();
        $log = ob_get_clean();

        return array(
            'success' => true,
            'message' => 'Expiry check completed',
            'log'     => $log,
            'stats'   => self::get_expiry_stats(),
        );
    }

    /**
     * Clear old reminder records (older than 2 years)
     * Called periodically to keep table size manageable
     */
    public static function cleanup_old_reminders() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'eau_membership_reminders';
        $two_years_ago = date('Y-m-d H:i:s', strtotime('-2 years'));

        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table_name} WHERE sent_at < %s",
            $two_years_ago
        ));

        if ($deleted > 0) {
            error_log("[EAU Membership Cron] Cleaned up {$deleted} old reminder records");
        }

        return $deleted;
    }
}
