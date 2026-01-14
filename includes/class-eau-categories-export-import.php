<?php
/**
 * Categories Export/Import
 *
 * Handles export and import of activity categories.
 *
 * @package    EauSystem
 * @subpackage Includes
 * @since      1.55.5
 */

namespace EauSystem;

if (!defined('WPINC')) {
    die;
}

/**
 * Class Eau_Categories_Export_Import
 *
 * Handles export and import of activity categories.
 *
 * @since 1.55.5
 */
class Eau_Categories_Export_Import {

    /**
     * Export all categories to JSON format
     *
     * @since  1.55.5
     * @return array Export data with categories
     */
    public static function export_categories() {
        global $wpdb;

        $table_name = $wpdb->prefix . Eau_Categories_Database::TABLE_NAME;

        $categories = $wpdb->get_results(
            "SELECT category_serial, category_name, points_per_hour
             FROM $table_name
             ORDER BY category_name ASC",
            ARRAY_A
        );

        return array(
            'export_version' => '1.0',
            'export_date' => current_time('mysql'),
            'plugin_version' => EAU_SYSTEM_VERSION,
            'total_categories' => count($categories),
            'categories' => $categories,
        );
    }

    /**
     * Export categories as downloadable JSON file
     *
     * @since 1.55.5
     */
    public static function download_export() {
        $data = self::export_categories();
        $filename = 'eau-categories-export-' . date('Y-m-d-His') . '.json';

        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Validate import data structure
     *
     * @since  1.55.5
     * @param  array $data Import data to validate
     * @return true|\WP_Error True if valid, WP_Error otherwise
     */
    public static function validate_import_data($data) {
        if (!is_array($data)) {
            return new \WP_Error('invalid_format', __('Invalid JSON format.', 'eau-system'));
        }

        if (!isset($data['categories']) || !is_array($data['categories'])) {
            return new \WP_Error('missing_categories', __('No categories found in import file.', 'eau-system'));
        }

        if (empty($data['categories'])) {
            return new \WP_Error('empty_categories', __('Categories array is empty.', 'eau-system'));
        }

        // Validate first category structure
        $first = $data['categories'][0];
        $required_fields = array('category_serial', 'category_name', 'points_per_hour');

        foreach ($required_fields as $field) {
            if (!isset($first[$field])) {
                return new \WP_Error(
                    'missing_field',
                    sprintf(__('Required field "%s" is missing from categories.', 'eau-system'), $field)
                );
            }
        }

        return true;
    }

    /**
     * Get preview of import data
     *
     * @since  1.55.5
     * @param  array $data Import data
     * @param  int   $limit Preview limit
     * @return array Preview data with statistics
     */
    public static function get_import_preview($data, $limit = 10) {
        global $wpdb;

        $table_name = $wpdb->prefix . Eau_Categories_Database::TABLE_NAME;
        $categories = $data['categories'];

        $will_create = 0;
        $will_update = 0;
        $preview = array();

        foreach ($categories as $index => $category) {
            $serial = sanitize_text_field($category['category_serial']);

            // Check if exists
            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT id, category_name, points_per_hour FROM $table_name WHERE category_serial = %s",
                $serial
            ), ARRAY_A);

            $action = $existing ? 'update' : 'create';

            if ($existing) {
                $will_update++;
            } else {
                $will_create++;
            }

            // Only include in preview up to limit
            if ($index < $limit) {
                $preview[] = array(
                    'category_serial' => $serial,
                    'category_name' => sanitize_text_field($category['category_name']),
                    'points_per_hour' => floatval($category['points_per_hour']),
                    'action' => $action,
                    'existing_id' => $existing ? $existing['id'] : null,
                    'existing_name' => $existing ? $existing['category_name'] : null,
                );
            }
        }

        return array(
            'total_categories' => count($categories),
            'will_create' => $will_create,
            'will_update' => $will_update,
            'preview' => $preview,
        );
    }

    /**
     * Import categories from data
     *
     * @since  1.55.5
     * @param  array $data       Import data
     * @param  bool  $skip_existing Whether to skip existing categories (false = update)
     * @return array Import results
     */
    public static function import_categories($data, $skip_existing = false) {
        global $wpdb;

        $table_name = $wpdb->prefix . Eau_Categories_Database::TABLE_NAME;
        $categories = $data['categories'];

        $results = array(
            'total' => count($categories),
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => array(),
        );

        foreach ($categories as $index => $category) {
            $serial = sanitize_text_field($category['category_serial']);
            $name = sanitize_text_field($category['category_name']);
            $points = floatval($category['points_per_hour']);

            if (empty($serial) || empty($name)) {
                $results['skipped']++;
                $results['errors'][] = array(
                    'row' => $index + 1,
                    'message' => __('Missing required fields (serial or name).', 'eau-system'),
                );
                continue;
            }

            // Check if exists
            $existing_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table_name WHERE category_serial = %s",
                $serial
            ));

            if ($existing_id) {
                if ($skip_existing) {
                    $results['skipped']++;
                    continue;
                }

                // Update existing
                $updated = $wpdb->update(
                    $table_name,
                    array(
                        'category_name' => $name,
                        'points_per_hour' => $points,
                    ),
                    array('id' => $existing_id),
                    array('%s', '%f'),
                    array('%d')
                );

                if ($updated !== false) {
                    $results['updated']++;
                } else {
                    $results['errors'][] = array(
                        'row' => $index + 1,
                        'message' => sprintf(__('Failed to update category: %s', 'eau-system'), $name),
                    );
                }
            } else {
                // Create new
                $inserted = $wpdb->insert(
                    $table_name,
                    array(
                        'category_serial' => $serial,
                        'category_name' => $name,
                        'points_per_hour' => $points,
                    ),
                    array('%s', '%s', '%f')
                );

                if ($inserted) {
                    $results['created']++;
                } else {
                    $results['errors'][] = array(
                        'row' => $index + 1,
                        'message' => sprintf(__('Failed to create category: %s', 'eau-system'), $name),
                    );
                }
            }
        }

        return $results;
    }

    /**
     * Parse uploaded JSON file
     *
     * @since  1.55.5
     * @param  array $file $_FILES array element
     * @return array|\WP_Error Parsed data or error
     */
    public static function parse_uploaded_file($file) {
        // Validate file upload
        if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
            return new \WP_Error('no_file', __('No file uploaded.', 'eau-system'));
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return new \WP_Error('upload_error', __('File upload failed.', 'eau-system'));
        }

        // Check file extension
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'json') {
            return new \WP_Error('invalid_extension', __('Only JSON files are allowed.', 'eau-system'));
        }

        // Check file size (max 5MB)
        if ($file['size'] > 5 * 1024 * 1024) {
            return new \WP_Error('file_too_large', __('File size exceeds 5MB limit.', 'eau-system'));
        }

        // Read and parse JSON
        $content = file_get_contents($file['tmp_name']);
        if ($content === false) {
            return new \WP_Error('read_error', __('Failed to read uploaded file.', 'eau-system'));
        }

        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new \WP_Error('json_error', __('Invalid JSON: ', 'eau-system') . json_last_error_msg());
        }

        return $data;
    }

    /**
     * Store uploaded file temporarily for batch processing
     *
     * @since  1.55.5
     * @param  array $file $_FILES array element
     * @return string|\WP_Error Temporary filename or error
     */
    public static function store_temp_file($file) {
        $parsed = self::parse_uploaded_file($file);

        if (is_wp_error($parsed)) {
            return $parsed;
        }

        // Validate structure
        $validation = self::validate_import_data($parsed);
        if (is_wp_error($validation)) {
            return $validation;
        }

        // Store in uploads directory
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/eau-temp';

        if (!file_exists($temp_dir)) {
            wp_mkdir_p($temp_dir);
        }

        $temp_filename = 'categories-import-' . uniqid() . '.json';
        $temp_path = $temp_dir . '/' . $temp_filename;

        if (file_put_contents($temp_path, json_encode($parsed)) === false) {
            return new \WP_Error('save_error', __('Failed to save temporary file.', 'eau-system'));
        }

        return $temp_filename;
    }

    /**
     * Get data from temporary file
     *
     * @since  1.55.5
     * @param  string $filename Temporary filename
     * @return array|\WP_Error Data or error
     */
    public static function get_temp_file_data($filename) {
        $upload_dir = wp_upload_dir();
        $temp_path = $upload_dir['basedir'] . '/eau-temp/' . basename($filename);

        if (!file_exists($temp_path)) {
            return new \WP_Error('file_not_found', __('Temporary file not found.', 'eau-system'));
        }

        $content = file_get_contents($temp_path);
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new \WP_Error('json_error', __('Invalid JSON in temporary file.', 'eau-system'));
        }

        return $data;
    }

    /**
     * Delete temporary file
     *
     * @since  1.55.5
     * @param  string $filename Temporary filename
     */
    public static function delete_temp_file($filename) {
        $upload_dir = wp_upload_dir();
        $temp_path = $upload_dir['basedir'] . '/eau-temp/' . basename($filename);

        if (file_exists($temp_path)) {
            unlink($temp_path);
        }
    }
}
