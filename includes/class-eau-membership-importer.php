<?php
/**
 * Membership Importer
 *
 * Imports membership data from legacy CSV, updating existing users
 * or creating new ones when email is not found.
 *
 * @package    EauSystem
 * @subpackage Includes
 * @since      1.55.0
 */

namespace EauSystem;

if (!defined('WPINC')) {
    die;
}

/**
 * Class Eau_Membership_Importer
 *
 * Handles batch import of membership data from CSV.
 *
 * @since 1.55.0
 */
class Eau_Membership_Importer {

    /**
     * Mapping of CSV membership types to system types
     *
     * @var array
     */
    private static $type_mapping = array(
        'member college - full'      => 'full_provider',
        'member college - associate' => 'associate_access',
        'corporate affiliate'        => 'corporate_affiliate',
        'professional affiliate'     => 'professional_affiliate',
    );

    /**
     * Mapping of CSV status to system status
     *
     * @var array
     */
    private static $status_mapping = array(
        'active'    => 'active',
        'pending'   => 'pending',
        'expired'   => 'expired',
        'suspended' => 'suspended',
        'cancelled' => 'cancelled',
    );

    /**
     * Get preview of CSV data without importing
     *
     * @since  1.55.0
     * @param  string $csv_filepath Path to CSV file
     * @param  int    $limit        Number of rows to preview
     * @return array|WP_Error
     */
    public static function get_preview($csv_filepath, $limit = 10) {
        if (!file_exists($csv_filepath)) {
            return new \WP_Error('file_not_found', __('CSV file not found.', 'eau-system'));
        }

        $csv_handler = new Eau_CSV_Handler();
        $csv_data = $csv_handler->get_csv_data($csv_filepath);

        if (is_wp_error($csv_data)) {
            return $csv_data;
        }

        $total_rows = count($csv_data);
        $preview_rows = array_slice($csv_data, 0, $limit);
        $will_update = 0;
        $will_create = 0;
        $preview = array();

        // Analyze all rows for counts
        foreach ($csv_data as $row) {
            $email = self::get_csv_value($row, 'Member Email Address');
            if (!empty($email) && is_email($email)) {
                $user = get_user_by('email', $email);
                if ($user) {
                    $will_update++;
                } else {
                    $will_create++;
                }
            }
        }

        // Build preview data
        foreach ($preview_rows as $row) {
            $email = self::get_csv_value($row, 'Member Email Address');
            $type = self::get_csv_value($row, 'Type');
            $status = self::get_csv_value($row, 'Status');
            $expiry = self::get_csv_value($row, 'Expiry Date');
            $first_name = self::get_csv_value($row, 'Member First Name');
            $last_name = self::get_csv_value($row, 'Member Last Name');

            $user = !empty($email) && is_email($email) ? get_user_by('email', $email) : null;

            $preview[] = array(
                'email'      => $email,
                'name'       => trim($first_name . ' ' . $last_name),
                'type'       => $type,
                'type_mapped'=> self::map_membership_type($type),
                'status'     => $status,
                'expiry'     => $expiry,
                'action'     => $user ? 'update' : 'create',
                'user_id'    => $user ? $user->ID : null,
            );
        }

        return array(
            'total_rows'  => $total_rows,
            'will_update' => $will_update,
            'will_create' => $will_create,
            'preview'     => $preview,
        );
    }

    /**
     * Import a batch of rows from CSV
     *
     * @since  1.55.0
     * @param  string $csv_filepath Path to CSV file
     * @param  int    $offset       Starting offset
     * @param  int    $limit        Batch size
     * @return array|WP_Error
     */
    public static function import_batch($csv_filepath, $offset = 0, $limit = 25) {
        if (!file_exists($csv_filepath)) {
            return new \WP_Error('file_not_found', __('CSV file not found.', 'eau-system'));
        }

        $csv_handler = new Eau_CSV_Handler();
        $csv_data = $csv_handler->get_csv_data($csv_filepath);

        if (is_wp_error($csv_data)) {
            return $csv_data;
        }

        $total_rows = count($csv_data);
        $batch_data = array_slice($csv_data, $offset, $limit);

        $updated = 0;
        $created = 0;
        $skipped = 0;
        $errors = array();

        // Block emails during import
        add_filter('wp_new_user_notification_email_admin', '__return_false');
        add_filter('wp_new_user_notification_email', '__return_false');
        add_filter('pre_wp_mail', '__return_false', 999);
        add_filter('woocommerce_email_enabled_customer_new_account', '__return_false', 999);

        foreach ($batch_data as $index => $row) {
            $row_number = $offset + $index + 2; // +2 for header row and 1-based index
            $result = self::process_row($row, $row_number);

            if (is_wp_error($result)) {
                $skipped++;
                $errors[] = array(
                    'row'     => $row_number,
                    'message' => $result->get_error_message(),
                );
            } elseif ($result === 'created') {
                $created++;
            } elseif ($result === 'updated') {
                $updated++;
            } else {
                $skipped++;
            }
        }

        // Remove email filters
        remove_filter('wp_new_user_notification_email_admin', '__return_false');
        remove_filter('wp_new_user_notification_email', '__return_false');
        remove_filter('pre_wp_mail', '__return_false', 999);
        remove_filter('woocommerce_email_enabled_customer_new_account', '__return_false', 999);

        $processed = $offset + count($batch_data);

        return array(
            'success'   => true,
            'updated'   => $updated,
            'created'   => $created,
            'skipped'   => $skipped,
            'total'     => $total_rows,
            'processed' => $processed,
            'has_more'  => $processed < $total_rows,
            'errors'    => $errors,
        );
    }

    /**
     * Process a single row from CSV
     *
     * @since  1.55.0
     * @param  array $row        CSV row data
     * @param  int   $row_number Row number for error reporting
     * @return string|WP_Error   'created', 'updated', or WP_Error
     */
    private static function process_row($row, $row_number) {
        $email = self::get_csv_value($row, 'Member Email Address');

        // Validate email
        if (empty($email)) {
            return new \WP_Error('empty_email', sprintf(__('Row %d: Empty email', 'eau-system'), $row_number));
        }

        if (!is_email($email)) {
            return new \WP_Error('invalid_email', sprintf(__('Row %d: Invalid email - %s', 'eau-system'), $row_number, $email));
        }

        // Try to find existing user
        $user = get_user_by('email', $email);

        if ($user) {
            // UPDATE existing user
            $result = self::update_user_membership($user->ID, $row);
            if (is_wp_error($result)) {
                return $result;
            }
            return 'updated';
        } else {
            // CREATE new user
            $result = self::create_user_with_membership($row, $row_number);
            if (is_wp_error($result)) {
                return $result;
            }
            return 'created';
        }
    }

    /**
     * Update existing user with membership data
     *
     * @since  1.55.0
     * @param  int   $user_id WordPress user ID
     * @param  array $row     CSV row data
     * @return bool|WP_Error
     */
    private static function update_user_membership($user_id, $row) {
        // Get values from CSV
        $type = self::map_membership_type(self::get_csv_value($row, 'Type'));
        $status = self::map_membership_status(self::get_csv_value($row, 'Status'));
        $start_date = self::parse_date(self::get_csv_value($row, 'Start Date'));
        $expiry_date = self::parse_date(self::get_csv_value($row, 'Expiry Date'));
        $company_id = self::get_csv_value($row, 'Member Company Name');
        $position = self::get_csv_value($row, 'Member Position');
        $first_name = self::get_csv_value($row, 'Member First Name');
        $last_name = self::get_csv_value($row, 'Member Last Name');

        // Update membership meta fields
        if (!empty($type)) {
            update_user_meta($user_id, 'mem_membership_type', $type);
        }
        if (!empty($status)) {
            update_user_meta($user_id, 'mem_membership_status', $status);
        }
        if (!empty($start_date)) {
            update_user_meta($user_id, 'mem_membership_start_date', $start_date);
        }
        if (!empty($expiry_date)) {
            update_user_meta($user_id, 'mem_membership_expiry_date', $expiry_date);
        }
        if (!empty($company_id)) {
            update_user_meta($user_id, 'mem_membercompanyname', $company_id);
        }
        if (!empty($position)) {
            update_user_meta($user_id, 'mem_position', $position);
        }

        // Update first/last name if provided and current is empty
        $current_first = get_user_meta($user_id, 'first_name', true);
        $current_last = get_user_meta($user_id, 'last_name', true);

        if (empty($current_first) && !empty($first_name)) {
            update_user_meta($user_id, 'first_name', sanitize_text_field(trim($first_name)));
        }
        if (empty($current_last) && !empty($last_name)) {
            update_user_meta($user_id, 'last_name', sanitize_text_field(trim($last_name)));
        }

        return true;
    }

    /**
     * Create new user with membership data
     *
     * @since  1.55.0
     * @param  array $row        CSV row data
     * @param  int   $row_number Row number for error reporting
     * @return int|WP_Error      User ID or error
     */
    private static function create_user_with_membership($row, $row_number) {
        $email = sanitize_email(self::get_csv_value($row, 'Member Email Address'));
        $first_name = sanitize_text_field(trim(self::get_csv_value($row, 'Member First Name')));
        $last_name = sanitize_text_field(trim(self::get_csv_value($row, 'Member Last Name')));

        // Generate username from email
        $username = sanitize_user(current(explode('@', $email)));

        // Handle username collision
        if (username_exists($username)) {
            $base_username = $username;
            $counter = 1;
            while (username_exists($username)) {
                $username = $base_username . $counter;
                $counter++;
            }
        }

        // Generate random password
        $password = wp_generate_password(12, false);

        // Build display name
        $display_name = trim($first_name . ' ' . $last_name);
        if (empty($display_name)) {
            $display_name = $username;
        }

        // Create user
        $user_data = array(
            'user_login'    => $username,
            'user_email'    => $email,
            'user_pass'     => $password,
            'first_name'    => $first_name,
            'last_name'     => $last_name,
            'display_name'  => $display_name,
            'role'          => 'eau_member',
        );

        $user_id = wp_insert_user($user_data);

        if (is_wp_error($user_id)) {
            return new \WP_Error(
                'user_creation_failed',
                sprintf(__('Row %d: Failed to create user - %s', 'eau-system'), $row_number, $user_id->get_error_message())
            );
        }

        // Set mem_type
        update_user_meta($user_id, 'mem_type', 'member');

        // Generate mem_userid
        $mem_userid = 'MEM' . str_pad($user_id, 6, '0', STR_PAD_LEFT);
        update_user_meta($user_id, 'mem_userid', $mem_userid);

        // Set mem_status
        update_user_meta($user_id, 'mem_status', 'active');

        // Now add membership data
        self::update_user_membership($user_id, $row);

        return $user_id;
    }

    /**
     * Map CSV membership type to system type
     *
     * @since  1.55.0
     * @param  string $csv_type Type from CSV
     * @return string|null      Mapped type or null
     */
    public static function map_membership_type($csv_type) {
        if (empty($csv_type)) {
            return null;
        }

        $normalized = strtolower(trim($csv_type));

        if (isset(self::$type_mapping[$normalized])) {
            return self::$type_mapping[$normalized];
        }

        // Try partial match
        foreach (self::$type_mapping as $key => $value) {
            if (strpos($normalized, $key) !== false) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Map CSV status to system status
     *
     * @since  1.55.0
     * @param  string $csv_status Status from CSV
     * @return string|null        Mapped status or null
     */
    public static function map_membership_status($csv_status) {
        if (empty($csv_status)) {
            return null;
        }

        $normalized = strtolower(trim($csv_status));

        if (isset(self::$status_mapping[$normalized])) {
            return self::$status_mapping[$normalized];
        }

        return null;
    }

    /**
     * Parse date string to Y-m-d format
     *
     * @since  1.55.0
     * @param  string $date_string Date string from CSV
     * @return string|null         Formatted date or null
     */
    public static function parse_date($date_string) {
        if (empty($date_string)) {
            return null;
        }

        // Handle "0000-00-00" or similar
        if (strpos($date_string, '0000') === 0) {
            return null;
        }

        // Try to parse the date
        $timestamp = strtotime($date_string);
        if ($timestamp === false || $timestamp <= 0) {
            return null;
        }

        return date('Y-m-d', $timestamp);
    }

    /**
     * Get value from CSV row by column name (case-insensitive)
     *
     * @since  1.55.0
     * @param  array  $row    CSV row
     * @param  string $column Column name
     * @return string         Value or empty string
     */
    private static function get_csv_value($row, $column) {
        // Direct match
        if (isset($row[$column])) {
            return trim($row[$column]);
        }

        // Case-insensitive search
        $column_lower = strtolower($column);
        foreach ($row as $key => $value) {
            if (strtolower($key) === $column_lower) {
                return trim($value);
            }
        }

        return '';
    }
}
