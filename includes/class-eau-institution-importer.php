<?php
/**
 * Institution Importer
 *
 * Imports institution data from CSV, supporting two formats:
 * - Legacy format: Detailed company data with many fields
 * - MembershipDetails format: Membership data with start/expiry dates
 *
 * @package    EauSystem
 * @subpackage Includes
 * @since      1.54.0
 */

namespace EauSystem;

if (!defined('WPINC')) {
    die;
}

/**
 * Class Eau_Institution_Importer
 *
 * Handles batch import of institution data from CSV.
 *
 * @since 1.54.0
 */
class Eau_Institution_Importer {

    /**
     * CSV Format constants
     */
    const FORMAT_LEGACY = 'legacy';
    const FORMAT_MEMBERSHIP = 'membership';

    /**
     * Mapping of CSV company types to system types
     *
     * @var array
     */
    private static $type_mapping = array(
        'elicos college'  => 'elicos_college',
        'agents'          => 'agent',
        'media'           => 'media',
        'other'           => 'other',
    );

    /**
     * Detect CSV format based on headers
     *
     * @since  1.63.9
     * @param  array $headers CSV headers
     * @return string         FORMAT_LEGACY or FORMAT_MEMBERSHIP
     */
    public static function detect_format($headers) {
        // Normalize headers to lowercase for comparison
        $headers_lower = array_map('strtolower', array_map('trim', $headers));

        // MembershipDetails format has these specific headers
        $membership_headers = array('start date', 'expiry date', 'userid', 'total staff', 'membership type', 'member name', 'campus name');

        $matches = 0;
        foreach ($membership_headers as $header) {
            if (in_array($header, $headers_lower)) {
                $matches++;
            }
        }

        // If 4+ of the membership headers match, it's the membership format
        if ($matches >= 4) {
            return self::FORMAT_MEMBERSHIP;
        }

        return self::FORMAT_LEGACY;
    }

    /**
     * Get format name for display
     *
     * @since  1.63.9
     * @param  string $format Format constant
     * @return string         Human readable format name
     */
    public static function get_format_name($format) {
        if ($format === self::FORMAT_MEMBERSHIP) {
            return 'MembershipDetails (Membership Data)';
        }
        return 'Legacy (Company Details)';
    }

    /**
     * Get preview of CSV data without importing
     *
     * @since  1.54.0
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

        if (empty($csv_data)) {
            return new \WP_Error('empty_csv', __('CSV file is empty.', 'eau-system'));
        }

        // Detect format from first row keys
        $first_row = reset($csv_data);
        $headers = array_keys($first_row);
        $format = self::detect_format($headers);

        // Use format-specific extraction
        if ($format === self::FORMAT_MEMBERSHIP) {
            return self::get_preview_membership($csv_data, $limit, $format);
        }

        // Legacy format
        $unique_institutions = self::extract_unique_institutions($csv_data);

        $total_institutions = count($unique_institutions);
        $preview_institutions = array_slice($unique_institutions, 0, $limit);
        $will_update = 0;
        $will_create = 0;
        $preview = array();

        // Analyze all institutions for counts
        foreach ($unique_institutions as $company_id => $institution_data) {
            $existing = self::find_existing_institution($company_id, $institution_data['company_name']);
            if ($existing) {
                $will_update++;
            } else {
                $will_create++;
            }
        }

        // Build preview data
        foreach ($preview_institutions as $company_id => $institution_data) {
            $existing = self::find_existing_institution($company_id, $institution_data['company_name']);

            $preview[] = array(
                'company_id'   => $company_id,
                'company_name' => $institution_data['company_name'],
                'email'        => isset($institution_data['email']) ? $institution_data['email'] : '',
                'type'         => isset($institution_data['type']) ? $institution_data['type'] : '',
                'cricos'       => isset($institution_data['cricos']) ? $institution_data['cricos'] : '',
                'state'        => isset($institution_data['state']) ? $institution_data['state'] : '',
                'country'      => isset($institution_data['country']) ? $institution_data['country'] : '',
                'action'       => $existing ? 'update' : 'create',
                'post_id'      => $existing ? $existing->ID : null,
            );
        }

        // Count institutions that will be deleted (not in CSV)
        $will_delete = self::count_institutions_not_in_csv($unique_institutions);

        return array(
            'format'             => $format,
            'format_name'        => self::get_format_name($format),
            'total_rows'         => count($csv_data),
            'total_institutions' => $total_institutions,
            'will_update'        => $will_update,
            'will_create'        => $will_create,
            'will_delete'        => $will_delete,
            'preview'            => $preview,
        );
    }

    /**
     * Get preview for MembershipDetails format
     *
     * @since  1.63.9
     * @param  array  $csv_data CSV data
     * @param  int    $limit    Number of rows to preview
     * @param  string $format   Format constant
     * @return array
     */
    private static function get_preview_membership($csv_data, $limit, $format) {
        $unique_institutions = self::extract_membership_institutions($csv_data);

        $total_institutions = count($unique_institutions);
        $preview_institutions = array_slice($unique_institutions, 0, $limit, true);
        $will_update = 0;
        $will_create = 0;
        $preview = array();

        // Analyze all institutions for counts
        foreach ($unique_institutions as $company_id => $institution_data) {
            $existing = self::find_existing_institution($company_id, $institution_data['company_name']);
            if ($existing) {
                $will_update++;
            } else {
                $will_create++;
            }
        }

        // Build preview data
        foreach ($preview_institutions as $company_id => $institution_data) {
            $existing = self::find_existing_institution($company_id, $institution_data['company_name']);

            $preview[] = array(
                'company_id'      => $company_id,
                'company_name'    => $institution_data['company_name'],
                'membership_type' => $institution_data['membership_type'],
                'start_date'      => $institution_data['start_date'],
                'expiry_date'     => $institution_data['expiry_date'],
                'total_staff'     => $institution_data['total_staff'],
                'action'          => $existing ? 'update' : 'create',
                'post_id'         => $existing ? $existing->ID : null,
            );
        }

        // Count institutions that will be deleted (not in CSV)
        $will_delete = self::count_institutions_not_in_csv($unique_institutions);

        return array(
            'format'             => $format,
            'format_name'        => self::get_format_name($format),
            'total_rows'         => count($csv_data),
            'total_institutions' => $total_institutions,
            'will_update'        => $will_update,
            'will_create'        => $will_create,
            'will_delete'        => $will_delete,
            'preview'            => $preview,
        );
    }

    /**
     * Extract institutions from MembershipDetails format CSV
     *
     * @since  1.63.9
     * @param  array $csv_data CSV data
     * @return array           Unique institutions keyed by Company ID
     */
    private static function extract_membership_institutions($csv_data) {
        $institutions = array();

        foreach ($csv_data as $row) {
            $company_id = self::get_csv_value($row, 'Company ID');

            if (empty($company_id)) {
                continue;
            }

            if (isset($institutions[$company_id])) {
                continue;
            }

            // Use Campus Name if available, otherwise Member Name
            $campus_name = self::get_csv_value($row, 'Campus Name');
            $member_name = self::get_csv_value($row, 'Member Name');
            $company_name = !empty($campus_name) ? $campus_name : $member_name;

            // Convert dates from DD/MM/YYYY to YYYY-MM-DD
            $start_date = self::convert_date_format(self::get_csv_value($row, 'Start Date'));
            $expiry_date = self::convert_date_format(self::get_csv_value($row, 'Expiry Date'));

            $institutions[$company_id] = array(
                'company_id'      => $company_id,
                'company_name'    => $company_name,
                'member_name'     => $member_name,
                'campus_name'     => $campus_name,
                'user_id'         => self::get_csv_value($row, 'UserId'),
                'total_staff'     => intval(self::get_csv_value($row, 'Total Staff')),
                'membership_type' => self::get_csv_value($row, 'Membership Type'),
                'start_date'      => $start_date,
                'expiry_date'     => $expiry_date,
            );
        }

        return $institutions;
    }

    /**
     * Convert date from DD/MM/YYYY to YYYY-MM-DD
     *
     * @since  1.63.9
     * @param  string $date Date in DD/MM/YYYY format
     * @return string       Date in YYYY-MM-DD format or empty string
     */
    private static function convert_date_format($date) {
        if (empty($date)) {
            return '';
        }

        $parts = explode('/', $date);
        if (count($parts) !== 3) {
            return $date; // Return as-is if not in expected format
        }

        return $parts[2] . '-' . str_pad($parts[1], 2, '0', STR_PAD_LEFT) . '-' . str_pad($parts[0], 2, '0', STR_PAD_LEFT);
    }

    /**
     * Count institutions in database that are not in the CSV
     *
     * @since  1.63.9
     * @param  array $csv_institutions Institutions from CSV keyed by company ID
     * @return int                     Number of institutions not in CSV
     */
    private static function count_institutions_not_in_csv($csv_institutions) {
        $query = new \WP_Query(array(
            'post_type'      => 'institutions',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ));

        $count = 0;
        foreach ($query->posts as $post_id) {
            $company_id = get_post_meta($post_id, 'ins_company_id', true);
            if (!empty($company_id) && !isset($csv_institutions[$company_id])) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Extract unique institutions from CSV data
     *
     * @since  1.54.0
     * @param  array $csv_data All CSV rows
     * @return array           Unique institutions keyed by Company ID
     */
    private static function extract_unique_institutions($csv_data) {
        $institutions = array();

        foreach ($csv_data as $row) {
            $company_id = self::get_csv_value($row, 'Company ID');

            // Skip rows without Company ID
            if (empty($company_id)) {
                continue;
            }

            // Skip if already processed
            if (isset($institutions[$company_id])) {
                continue;
            }

            $institutions[$company_id] = array(
                'company_id'      => $company_id,
                'company_name'    => self::get_csv_value($row, 'Company Company Name') ?: self::get_csv_value($row, 'Company Name'),
                'email'           => self::get_csv_value($row, 'Company Company Email Address') ?: self::get_csv_value($row, 'Company Email'),
                'type'            => self::get_csv_value($row, 'Company Company Type'),
                'cricos'          => self::get_csv_value($row, 'Company CRICOS Code'),
                'abn'             => self::get_csv_value($row, 'Company ABN'),
                'address_line_1'  => self::get_csv_value($row, 'Company Company Address Line 1'),
                'address_line_2'  => self::get_csv_value($row, 'Company Company Address Line 2'),
                'address_line_3'  => self::get_csv_value($row, 'Company Company Address Line 3'),
                'suburb'          => self::get_csv_value($row, 'Company Company Suburb'),
                'postcode'        => self::get_csv_value($row, 'Company Company Postcode'),
                'state'           => self::get_csv_value($row, 'Company Company State'),
                'country'         => self::get_csv_value($row, 'Company Company Country'),
                'phone'           => self::get_csv_value($row, 'Company Company Phone'),
                'primary_contact' => self::get_csv_value($row, 'Company Primary Contact'),
                'courses_offered' => self::get_csv_value($row, 'Company Courses Offered'),
                'logo'            => self::get_csv_value($row, 'Company Logo'),
                'website'         => self::get_csv_value($row, 'Company Website'),
                'parent_company'  => self::get_csv_value($row, 'Company Parent Company'),
                'member_since'    => self::get_csv_value($row, 'Company Member Since: (Date)'),
            );
        }

        return $institutions;
    }

    /**
     * Import a batch of institutions from CSV
     *
     * @since  1.54.0
     * @param  string $csv_filepath Path to CSV file
     * @param  int    $offset       Starting offset
     * @param  int    $limit        Batch size
     * @param  bool   $sync_delete  If true, delete institutions not in CSV (for membership format)
     * @return array|WP_Error
     */
    public static function import_batch($csv_filepath, $offset = 0, $limit = 25, $sync_delete = false) {
        if (!file_exists($csv_filepath)) {
            return new \WP_Error('file_not_found', __('CSV file not found.', 'eau-system'));
        }

        $csv_handler = new Eau_CSV_Handler();
        $csv_data = $csv_handler->get_csv_data($csv_filepath);

        if (is_wp_error($csv_data)) {
            return $csv_data;
        }

        if (empty($csv_data)) {
            return new \WP_Error('empty_csv', __('CSV file is empty.', 'eau-system'));
        }

        // Detect format
        $first_row = reset($csv_data);
        $headers = array_keys($first_row);
        $format = self::detect_format($headers);

        // Extract institutions based on format
        if ($format === self::FORMAT_MEMBERSHIP) {
            $unique_institutions = self::extract_membership_institutions($csv_data);
        } else {
            $unique_institutions = self::extract_unique_institutions($csv_data);
        }

        $institutions_array = array_values($unique_institutions);
        $total_institutions = count($institutions_array);
        $batch_data = array_slice($institutions_array, $offset, $limit);

        $updated = 0;
        $created = 0;
        $skipped = 0;
        $deleted = 0;
        $errors = array();

        foreach ($batch_data as $index => $institution_data) {
            $row_number = $offset + $index + 1;

            // Use format-specific processing
            if ($format === self::FORMAT_MEMBERSHIP) {
                $result = self::process_membership_institution($institution_data, $row_number);
            } else {
                $result = self::process_institution($institution_data, $row_number);
            }

            if (is_wp_error($result)) {
                $skipped++;
                $errors[] = array(
                    'row'     => $row_number,
                    'company' => $institution_data['company_name'],
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

        $processed = $offset + count($batch_data);
        $has_more = $processed < $total_institutions;

        // If this is the last batch and sync_delete is true, delete institutions not in CSV
        if (!$has_more && $sync_delete) {
            $deleted = self::delete_institutions_not_in_csv($unique_institutions);
        }

        return array(
            'success'   => true,
            'format'    => $format,
            'updated'   => $updated,
            'created'   => $created,
            'skipped'   => $skipped,
            'deleted'   => $deleted,
            'total'     => $total_institutions,
            'processed' => $processed,
            'has_more'  => $has_more,
            'errors'    => $errors,
        );
    }

    /**
     * Process a single institution from MembershipDetails format
     *
     * @since  1.63.9
     * @param  array $institution_data Institution data from CSV
     * @param  int   $row_number       Row number for error reporting
     * @return string|WP_Error         'created', 'updated', or WP_Error
     */
    private static function process_membership_institution($institution_data, $row_number) {
        $company_id = $institution_data['company_id'];
        $company_name = $institution_data['company_name'];

        if (empty($company_id)) {
            return new \WP_Error('empty_company_id', sprintf(__('Row %d: No Company ID', 'eau-system'), $row_number));
        }

        $existing = self::find_existing_institution($company_id, $company_name);

        if ($existing) {
            // UPDATE existing institution
            $result = self::update_membership_institution($existing->ID, $institution_data);
            if (is_wp_error($result)) {
                return $result;
            }
            return 'updated';
        } else {
            // CREATE new institution
            $result = self::create_membership_institution($institution_data, $row_number);
            if (is_wp_error($result)) {
                return $result;
            }
            return 'created';
        }
    }

    /**
     * Update institution with MembershipDetails data
     *
     * @since  1.63.9
     * @param  int   $post_id          Institution post ID
     * @param  array $institution_data Institution data
     * @return bool|WP_Error
     */
    private static function update_membership_institution($post_id, $institution_data) {
        // Update post title
        if (!empty($institution_data['company_name'])) {
            wp_update_post(array(
                'ID'         => $post_id,
                'post_title' => sanitize_text_field($institution_data['company_name']),
            ));
        }

        // Update meta fields
        update_post_meta($post_id, 'ins_company_id', sanitize_text_field($institution_data['company_id']));
        update_post_meta($post_id, 'ins_company_name', sanitize_text_field($institution_data['company_name']));
        update_post_meta($post_id, 'ins_member_start_date', sanitize_text_field($institution_data['start_date']));
        update_post_meta($post_id, 'ins_member_expire_date', sanitize_text_field($institution_data['expiry_date']));
        update_post_meta($post_id, 'ins_total_staff', intval($institution_data['total_staff']));
        update_post_meta($post_id, 'ins_membership_type', sanitize_text_field($institution_data['membership_type']));
        update_post_meta($post_id, 'ins_company_primary_contact', sanitize_text_field($institution_data['user_id']));
        update_post_meta($post_id, 'ins_status', 'active');

        // Determine institution type based on membership_type
        $membership_type = $institution_data['membership_type'];
        if (strpos($membership_type, 'College') !== false) {
            update_post_meta($post_id, 'ins_type', 'College');
        } elseif (strpos($membership_type, 'Corporate') !== false || strpos($membership_type, 'Affiliate') !== false) {
            update_post_meta($post_id, 'ins_type', 'Corporate affiliate');
        }

        return true;
    }

    /**
     * Create institution from MembershipDetails data
     *
     * @since  1.63.9
     * @param  array $institution_data Institution data
     * @param  int   $row_number       Row number for error reporting
     * @return int|WP_Error            Post ID or error
     */
    private static function create_membership_institution($institution_data, $row_number) {
        $company_name = !empty($institution_data['company_name'])
            ? sanitize_text_field($institution_data['company_name'])
            : 'Institution ' . $institution_data['company_id'];

        $post_id = wp_insert_post(array(
            'post_type'   => 'institutions',
            'post_title'  => $company_name,
            'post_status' => 'publish',
            'post_author' => get_current_user_id() ?: 1,
        ));

        if (is_wp_error($post_id)) {
            return new \WP_Error(
                'institution_creation_failed',
                sprintf(__('Row %d: Failed to create institution - %s', 'eau-system'), $row_number, $post_id->get_error_message())
            );
        }

        // Add meta fields
        self::update_membership_institution($post_id, $institution_data);

        return $post_id;
    }

    /**
     * Delete institutions that are not in the CSV
     *
     * @since  1.63.9
     * @param  array $csv_institutions Institutions from CSV keyed by company ID
     * @return int                     Number of deleted institutions
     */
    private static function delete_institutions_not_in_csv($csv_institutions) {
        $query = new \WP_Query(array(
            'post_type'      => 'institutions',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ));

        $deleted = 0;
        foreach ($query->posts as $post_id) {
            $company_id = get_post_meta($post_id, 'ins_company_id', true);
            if (!empty($company_id) && !isset($csv_institutions[$company_id])) {
                $result = wp_delete_post($post_id, true);
                if ($result) {
                    $deleted++;
                }
            }
        }

        return $deleted;
    }

    /**
     * Process a single institution
     *
     * @since  1.54.0
     * @param  array $institution_data Institution data from CSV
     * @param  int   $row_number       Row number for error reporting
     * @return string|WP_Error         'created', 'updated', or WP_Error
     */
    private static function process_institution($institution_data, $row_number) {
        $company_id = $institution_data['company_id'];
        $company_name = $institution_data['company_name'];

        // Validate required fields
        if (empty($company_id) && empty($company_name)) {
            return new \WP_Error('empty_identifier', sprintf(__('Row %d: No Company ID or Name', 'eau-system'), $row_number));
        }

        // Try to find existing institution
        $existing = self::find_existing_institution($company_id, $company_name);

        if ($existing) {
            // UPDATE existing institution
            $result = self::update_institution($existing->ID, $institution_data);
            if (is_wp_error($result)) {
                return $result;
            }
            return 'updated';
        } else {
            // CREATE new institution
            $result = self::create_institution($institution_data, $row_number);
            if (is_wp_error($result)) {
                return $result;
            }
            return 'created';
        }
    }

    /**
     * Find existing institution by Company ID or name
     *
     * @since  1.54.0
     * @param  string $company_id   Company ID
     * @param  string $company_name Company name
     * @return WP_Post|null         Found post or null
     */
    private static function find_existing_institution($company_id, $company_name) {
        global $wpdb;

        // First, try to find by Company ID (most reliable)
        if (!empty($company_id)) {
            $post_id = $wpdb->get_var($wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta}
                WHERE meta_key = 'ins_company_id' AND meta_value = %s
                LIMIT 1",
                $company_id
            ));

            if ($post_id) {
                $post = get_post($post_id);
                if ($post && $post->post_type === 'institutions') {
                    return $post;
                }
            }
        }

        // Fallback: try to find by name
        if (!empty($company_name)) {
            $args = array(
                'post_type'      => 'institutions',
                'post_status'    => array('publish', 'draft', 'private'),
                'posts_per_page' => 1,
                'meta_query'     => array(
                    array(
                        'key'     => 'ins_company_name',
                        'value'   => $company_name,
                        'compare' => '=',
                    ),
                ),
            );

            $query = new \WP_Query($args);

            if ($query->have_posts()) {
                return $query->posts[0];
            }

            // Also try by post title
            $args = array(
                'post_type'      => 'institutions',
                'post_status'    => array('publish', 'draft', 'private'),
                'posts_per_page' => 1,
                'title'          => $company_name,
            );

            $query = new \WP_Query($args);

            if ($query->have_posts()) {
                return $query->posts[0];
            }
        }

        return null;
    }

    /**
     * Update existing institution
     *
     * @since  1.54.0
     * @param  int   $post_id          Institution post ID
     * @param  array $institution_data Institution data from CSV
     * @return bool|WP_Error
     */
    private static function update_institution($post_id, $institution_data) {
        // Update post title if name changed
        if (!empty($institution_data['company_name'])) {
            wp_update_post(array(
                'ID'         => $post_id,
                'post_title' => sanitize_text_field($institution_data['company_name']),
            ));
        }

        // Update meta fields
        self::update_institution_meta($post_id, $institution_data);

        return true;
    }

    /**
     * Create new institution
     *
     * @since  1.54.0
     * @param  array $institution_data Institution data from CSV
     * @param  int   $row_number       Row number for error reporting
     * @return int|WP_Error            Post ID or error
     */
    private static function create_institution($institution_data, $row_number) {
        $company_name = !empty($institution_data['company_name'])
            ? sanitize_text_field($institution_data['company_name'])
            : 'Institution ' . $institution_data['company_id'];

        // Create the post
        $post_data = array(
            'post_type'   => 'institutions',
            'post_title'  => $company_name,
            'post_status' => 'publish',
            'post_author' => get_current_user_id(),
        );

        $post_id = wp_insert_post($post_data);

        if (is_wp_error($post_id)) {
            return new \WP_Error(
                'institution_creation_failed',
                sprintf(__('Row %d: Failed to create institution - %s', 'eau-system'), $row_number, $post_id->get_error_message())
            );
        }

        // Update meta fields
        self::update_institution_meta($post_id, $institution_data);

        return $post_id;
    }

    /**
     * Update institution meta fields
     *
     * @since  1.54.0
     * @param  int   $post_id          Institution post ID
     * @param  array $institution_data Institution data from CSV
     */
    private static function update_institution_meta($post_id, $institution_data) {
        // Map CSV fields to meta keys
        $meta_mapping = array(
            'company_id'      => 'ins_company_id',
            'company_name'    => 'ins_company_name',
            'email'           => 'ins_company_email',
            'type'            => 'ins_company_type',
            'cricos'          => 'ins_cricos_number',
            'abn'             => 'ins_abn',
            'address_line_1'  => 'ins_company_company_address_line_1',
            'suburb'          => 'ins_company_company_suburb',
            'postcode'        => 'ins_company_company_postcode',
            'state'           => 'ins_company_company_state',
            'country'         => 'ins_company_company_country',
            'phone'           => 'ins_company_company_phone',
            'primary_contact' => 'ins_company_primary_contact',
            'courses_offered' => 'ins_courses_offered',
            'logo'            => 'ins_company_logo',
            'website'         => 'ins_website',
            'parent_company'  => 'ins_parent_company',
            'member_since'    => 'ins_member_since',
        );

        foreach ($meta_mapping as $data_key => $meta_key) {
            if (isset($institution_data[$data_key]) && $institution_data[$data_key] !== '') {
                $value = $institution_data[$data_key];

                // Sanitize based on field type
                if (in_array($meta_key, array('ins_company_email'))) {
                    $value = sanitize_email($value);
                } elseif (in_array($meta_key, array('ins_website'))) {
                    $value = esc_url_raw($value);
                } else {
                    $value = sanitize_text_field($value);
                }

                update_post_meta($post_id, $meta_key, $value);
            }
        }

        // Set default status if not set
        $current_status = get_post_meta($post_id, 'ins_status', true);
        if (empty($current_status)) {
            update_post_meta($post_id, 'ins_status', 'active');
        }
    }

    /**
     * Get value from CSV row by column name (case-insensitive)
     *
     * @since  1.54.0
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

    /**
     * Get total unique institutions count from CSV
     *
     * @since  1.54.0
     * @param  string $csv_filepath Path to CSV file
     * @return int|WP_Error
     */
    public static function get_total_institutions($csv_filepath) {
        if (!file_exists($csv_filepath)) {
            return new \WP_Error('file_not_found', __('CSV file not found.', 'eau-system'));
        }

        $csv_handler = new Eau_CSV_Handler();
        $csv_data = $csv_handler->get_csv_data($csv_filepath);

        if (is_wp_error($csv_data)) {
            return $csv_data;
        }

        $unique_institutions = self::extract_unique_institutions($csv_data);

        return count($unique_institutions);
    }
}
