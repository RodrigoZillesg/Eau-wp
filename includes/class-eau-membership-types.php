<?php
namespace EauSystem;

/**
 * Membership Types Manager
 *
 * Handles CRUD operations for membership types and provides
 * helper methods for retrieving type information.
 *
 * @since 1.49.0
 */
class Eau_Membership_Types {

    /**
     * Membership type keys (constants for reference)
     */
    const TYPE_FULL_PROVIDER = 'full_provider';
    const TYPE_ASSOCIATE_ACCESS = 'associate_access';
    const TYPE_CORPORATE_AFFILIATE = 'corporate_affiliate';
    const TYPE_PROFESSIONAL_AFFILIATE = 'professional_affiliate';

    /**
     * Get all active membership types
     *
     * @param bool $include_inactive Include inactive types
     * @return array
     */
    public static function get_all($include_inactive = false) {
        global $wpdb;

        $table_name = Eau_Membership_Database::get_table_name(Eau_Membership_Database::TABLE_TYPES);

        $sql = "SELECT * FROM $table_name";
        if (!$include_inactive) {
            $sql .= " WHERE is_active = 1";
        }
        $sql .= " ORDER BY display_order ASC";

        $results = $wpdb->get_results($sql);

        // Decode JSON fields
        return array_map(array(__CLASS__, 'decode_json_fields'), $results);
    }

    /**
     * Get membership type by key
     *
     * @param string $type_key Type key (e.g., 'full_provider')
     * @return object|null
     */
    public static function get_by_key($type_key) {
        global $wpdb;

        $table_name = Eau_Membership_Database::get_table_name(Eau_Membership_Database::TABLE_TYPES);

        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE type_key = %s",
            $type_key
        ));

        return $result ? self::decode_json_fields($result) : null;
    }

    /**
     * Get membership type by ID
     *
     * @param int $type_id Type ID
     * @return object|null
     */
    public static function get_by_id($type_id) {
        global $wpdb;

        $table_name = Eau_Membership_Database::get_table_name(Eau_Membership_Database::TABLE_TYPES);

        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE type_id = %d",
            $type_id
        ));

        return $result ? self::decode_json_fields($result) : null;
    }

    /**
     * Get membership type label by key
     *
     * @param string $type_key Type key
     * @return string
     */
    public static function get_label($type_key) {
        $type = self::get_by_key($type_key);
        return $type ? $type->type_label : '';
    }

    /**
     * Get formatted fee for display
     *
     * @param string $type_key Type key
     * @param bool $for_member_college Whether to get member college rate
     * @return string
     */
    public static function get_formatted_fee($type_key, $for_member_college = false) {
        $type = self::get_by_key($type_key);

        if (!$type) {
            return '';
        }

        if ($type->fee_is_variable) {
            return 'Variable (calculated based on sites)';
        }

        $amount = $for_member_college && $type->fee_member_college
            ? $type->fee_member_college
            : $type->fee_amount;

        $formatted = '$' . number_format($amount, 2) . ' ' . $type->fee_currency;

        if ($type->fee_includes_gst) {
            $formatted .= ' (inc. GST)';
        }

        return $formatted;
    }

    /**
     * Get all types as key => label array (for dropdowns)
     *
     * @return array
     */
    public static function get_options() {
        $types = self::get_all();
        $options = array();

        foreach ($types as $type) {
            $options[$type->type_key] = $type->type_label;
        }

        return $options;
    }

    /**
     * Check if a type key is valid
     *
     * @param string $type_key Type key to check
     * @return bool
     */
    public static function is_valid_type($type_key) {
        $type = self::get_by_key($type_key);
        return $type !== null && $type->is_active;
    }

    /**
     * Get newsletter access for a membership type
     *
     * @param string $type_key Type key
     * @return array Newsletter keys the type has access to
     */
    public static function get_newsletter_access($type_key) {
        $type = self::get_by_key($type_key);

        if (!$type || empty($type->newsletter_access)) {
            return array();
        }

        // If 'all' is in the array, return all newsletter keys
        if (in_array('all', $type->newsletter_access)) {
            return array('all');
        }

        return $type->newsletter_access;
    }

    /**
     * Check if type has access to a specific newsletter
     *
     * @param string $type_key Membership type key
     * @param string $newsletter_key Newsletter key
     * @return bool
     */
    public static function has_newsletter_access($type_key, $newsletter_key) {
        $access = self::get_newsletter_access($type_key);

        if (in_array('all', $access)) {
            return true;
        }

        return in_array($newsletter_key, $access);
    }

    /**
     * Get required documents for a membership type
     *
     * @param string $type_key Type key
     * @return array
     */
    public static function get_required_documents($type_key) {
        $type = self::get_by_key($type_key);
        return $type && !empty($type->required_documents) ? $type->required_documents : array();
    }

    /**
     * Get requirements for a membership type
     *
     * @param string $type_key Type key
     * @return array
     */
    public static function get_requirements($type_key) {
        $type = self::get_by_key($type_key);
        return $type && !empty($type->requirements) ? $type->requirements : array();
    }

    /**
     * Get benefits for a membership type
     *
     * @param string $type_key Type key
     * @return array
     */
    public static function get_benefits($type_key) {
        $type = self::get_by_key($type_key);
        return $type && !empty($type->benefits) ? $type->benefits : array();
    }

    /**
     * Check if type requires email submission (Corporate Affiliate)
     *
     * @param string $type_key Type key
     * @return string|false Email address or false
     */
    public static function get_email_submission_required($type_key) {
        $type = self::get_by_key($type_key);

        if ($type && !empty($type->requires_email_submission)) {
            return $type->requires_email_submission;
        }

        return false;
    }

    /**
     * Calculate Full Provider fee based on number of sites
     *
     * Fee structure (example - adjust as needed):
     * - Base fee: $3,000
     * - Per additional site: $500
     *
     * @param int $num_sites Number of CRICOS sites
     * @return float
     */
    public static function calculate_full_provider_fee($num_sites) {
        $base_fee = 3000.00;
        $per_site_fee = 500.00;

        // First site is included in base
        $additional_sites = max(0, $num_sites - 1);

        return $base_fee + ($additional_sites * $per_site_fee);
    }

    /**
     * Update a membership type
     *
     * @param int $type_id Type ID
     * @param array $data Data to update
     * @return bool
     */
    public static function update($type_id, $data) {
        global $wpdb;

        $table_name = Eau_Membership_Database::get_table_name(Eau_Membership_Database::TABLE_TYPES);

        // Encode JSON fields
        $json_fields = array('requirements', 'required_documents', 'benefits', 'newsletter_access', 'form_fields');
        foreach ($json_fields as $field) {
            if (isset($data[$field]) && is_array($data[$field])) {
                $data[$field] = json_encode($data[$field]);
            }
        }

        $data['updated_at'] = current_time('mysql');

        return $wpdb->update(
            $table_name,
            $data,
            array('type_id' => $type_id)
        ) !== false;
    }

    /**
     * Decode JSON fields in a type object
     *
     * @param object $type Type object
     * @return object
     */
    private static function decode_json_fields($type) {
        $json_fields = array('requirements', 'required_documents', 'benefits', 'newsletter_access', 'form_fields');

        foreach ($json_fields as $field) {
            if (isset($type->$field) && !empty($type->$field)) {
                $decoded = json_decode($type->$field, true);
                $type->$field = is_array($decoded) ? $decoded : array();
            } else {
                $type->$field = array();
            }
        }

        // Cast numeric fields
        $type->type_id = (int) $type->type_id;
        $type->fee_amount = (float) $type->fee_amount;
        $type->fee_member_college = $type->fee_member_college ? (float) $type->fee_member_college : null;
        $type->fee_includes_gst = (bool) $type->fee_includes_gst;
        $type->fee_is_variable = (bool) $type->fee_is_variable;
        $type->max_duration_months = (int) $type->max_duration_months;
        $type->requires_approval = (bool) $type->requires_approval;
        $type->is_active = (bool) $type->is_active;
        $type->display_order = (int) $type->display_order;

        return $type;
    }

    /**
     * Get positions list (hardcoded from PRD)
     *
     * @return array
     */
    public static function get_positions() {
        return array(
            'teacher' => 'Teacher',
            'senior_teacher' => 'Senior Teacher',
            'academic_manager' => 'Academic Manager',
            'director_of_studies' => 'Director of Studies',
            'principal' => 'Principal',
            'general_manager' => 'General Manager',
            'ceo' => 'CEO',
            'marketing_officer' => 'Marketing Officer/Manager',
            'admissions_officer' => 'Admissions Officer/Manager',
            'student_services_officer' => 'Student Services Officer/Manager',
            'business_development_manager' => 'Business Development Manager',
            'other' => 'Other',
        );
    }

    /**
     * Get Australian states list
     *
     * @return array
     */
    public static function get_australian_states() {
        return array(
            'NSW' => 'New South Wales',
            'VIC' => 'Victoria',
            'QLD' => 'Queensland',
            'TAS' => 'Tasmania',
            'ACT' => 'Australian Capital Territory',
            'SA' => 'South Australia',
            'NT' => 'Northern Territory',
            'WA' => 'Western Australia',
            'Other' => 'Other',
        );
    }
}
