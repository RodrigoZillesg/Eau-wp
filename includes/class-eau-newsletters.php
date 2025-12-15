<?php
namespace EauSystem;

/**
 * Newsletters Manager
 *
 * Handles CRUD operations for newsletters and provides
 * helper methods for subscription management.
 *
 * @since 1.49.0
 */
class Eau_Newsletters {

    /**
     * Newsletter keys (constants for reference)
     */
    const NL_PD_UPDATES = 'pd_updates';
    const NL_INDUSTRY_NEWS = 'industry_news';
    const NL_SIG_DIRECT_ENTRY = 'sig_direct_entry';
    const NL_SIG_WELLBEING = 'sig_wellbeing';
    const NL_SIG_ASSESSMENT = 'sig_assessment';
    const NL_SIG_ED_TECH = 'sig_ed_tech';
    const NL_SIG_POST_ENTRY = 'sig_post_entry';
    const NL_SIG_ONLINE_LEARNING = 'sig_online_learning';
    const NL_SIG_ACADEMIC_MANAGERS = 'sig_academic_managers';

    /**
     * Get all active newsletters
     *
     * @param bool $include_inactive Include inactive newsletters
     * @return array
     */
    public static function get_all($include_inactive = false) {
        global $wpdb;

        $table_name = Eau_Membership_Database::get_table_name(Eau_Membership_Database::TABLE_NEWSLETTERS);

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
     * Get newsletter by key
     *
     * @param string $newsletter_key Newsletter key
     * @return object|null
     */
    public static function get_by_key($newsletter_key) {
        global $wpdb;

        $table_name = Eau_Membership_Database::get_table_name(Eau_Membership_Database::TABLE_NEWSLETTERS);

        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE newsletter_key = %s",
            $newsletter_key
        ));

        return $result ? self::decode_json_fields($result) : null;
    }

    /**
     * Get newsletter by ID
     *
     * @param int $newsletter_id Newsletter ID
     * @return object|null
     */
    public static function get_by_id($newsletter_id) {
        global $wpdb;

        $table_name = Eau_Membership_Database::get_table_name(Eau_Membership_Database::TABLE_NEWSLETTERS);

        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE newsletter_id = %d",
            $newsletter_id
        ));

        return $result ? self::decode_json_fields($result) : null;
    }

    /**
     * Get all public newsletters
     *
     * @return array
     */
    public static function get_public() {
        global $wpdb;

        $table_name = Eau_Membership_Database::get_table_name(Eau_Membership_Database::TABLE_NEWSLETTERS);

        $results = $wpdb->get_results(
            "SELECT * FROM $table_name WHERE is_public = 1 AND is_active = 1 ORDER BY display_order ASC"
        );

        return array_map(array(__CLASS__, 'decode_json_fields'), $results);
    }

    /**
     * Get member-only newsletters
     *
     * @return array
     */
    public static function get_members_only() {
        global $wpdb;

        $table_name = Eau_Membership_Database::get_table_name(Eau_Membership_Database::TABLE_NEWSLETTERS);

        $results = $wpdb->get_results(
            "SELECT * FROM $table_name WHERE is_public = 0 AND is_active = 1 ORDER BY display_order ASC"
        );

        return array_map(array(__CLASS__, 'decode_json_fields'), $results);
    }

    /**
     * Get newsletters available to a specific membership type
     *
     * @param string $membership_type Membership type key
     * @return array
     */
    public static function get_for_membership_type($membership_type) {
        $all_newsletters = self::get_all();
        $available = array();

        foreach ($all_newsletters as $newsletter) {
            // Public newsletters are available to everyone
            if ($newsletter->is_public) {
                $available[] = $newsletter;
                continue;
            }

            // Check if membership type has access
            if (empty($newsletter->allowed_membership_types)) {
                // No restrictions, available to all members
                $available[] = $newsletter;
            } elseif (in_array($membership_type, $newsletter->allowed_membership_types)) {
                $available[] = $newsletter;
            }
        }

        return $available;
    }

    /**
     * Check if a user can access a specific newsletter
     *
     * @param int $user_id User ID
     * @param string $newsletter_key Newsletter key
     * @return bool
     */
    public static function user_can_access($user_id, $newsletter_key) {
        $newsletter = self::get_by_key($newsletter_key);

        if (!$newsletter || !$newsletter->is_active) {
            return false;
        }

        // Public newsletters are accessible to everyone
        if ($newsletter->is_public) {
            return true;
        }

        // Get user's membership type
        $membership_type = get_user_meta($user_id, 'mem_membership_type', true);

        if (empty($membership_type)) {
            return false;
        }

        // Check if membership is active
        $membership_status = get_user_meta($user_id, 'mem_membership_status', true);
        if ($membership_status !== 'active') {
            return false;
        }

        // Check type access
        if (empty($newsletter->allowed_membership_types)) {
            // No restrictions, available to all members
            return true;
        }

        return in_array($membership_type, $newsletter->allowed_membership_types);
    }

    /**
     * Get all newsletters as key => name array (for forms)
     *
     * @param bool $public_only Only return public newsletters
     * @return array
     */
    public static function get_options($public_only = false) {
        $newsletters = $public_only ? self::get_public() : self::get_all();
        $options = array();

        foreach ($newsletters as $newsletter) {
            $options[$newsletter->newsletter_key] = $newsletter->newsletter_name;
        }

        return $options;
    }

    /**
     * Get user's newsletter subscriptions
     *
     * @param int $user_id User ID
     * @return array Newsletter keys the user is subscribed to
     */
    public static function get_user_subscriptions($user_id) {
        $subscriptions = get_user_meta($user_id, 'mem_newsletter_subscriptions', true);

        if (empty($subscriptions)) {
            return array();
        }

        // Handle both JSON string and array
        if (is_string($subscriptions)) {
            $decoded = json_decode($subscriptions, true);
            return is_array($decoded) ? $decoded : array();
        }

        return is_array($subscriptions) ? $subscriptions : array();
    }

    /**
     * Update user's newsletter subscriptions
     *
     * @param int $user_id User ID
     * @param array $newsletter_keys Array of newsletter keys
     * @return bool
     */
    public static function update_user_subscriptions($user_id, $newsletter_keys) {
        // Validate that user can access all requested newsletters
        $valid_keys = array();
        foreach ($newsletter_keys as $key) {
            if (self::user_can_access($user_id, $key)) {
                $valid_keys[] = $key;
            }
        }

        // Store as JSON
        return update_user_meta($user_id, 'mem_newsletter_subscriptions', json_encode($valid_keys));
    }

    /**
     * Subscribe user to a newsletter
     *
     * @param int $user_id User ID
     * @param string $newsletter_key Newsletter key
     * @return bool
     */
    public static function subscribe_user($user_id, $newsletter_key) {
        if (!self::user_can_access($user_id, $newsletter_key)) {
            return false;
        }

        $subscriptions = self::get_user_subscriptions($user_id);

        if (!in_array($newsletter_key, $subscriptions)) {
            $subscriptions[] = $newsletter_key;
            return update_user_meta($user_id, 'mem_newsletter_subscriptions', json_encode($subscriptions));
        }

        return true;
    }

    /**
     * Unsubscribe user from a newsletter
     *
     * @param int $user_id User ID
     * @param string $newsletter_key Newsletter key
     * @return bool
     */
    public static function unsubscribe_user($user_id, $newsletter_key) {
        $subscriptions = self::get_user_subscriptions($user_id);

        $key = array_search($newsletter_key, $subscriptions);
        if ($key !== false) {
            unset($subscriptions[$key]);
            $subscriptions = array_values($subscriptions); // Reindex
            return update_user_meta($user_id, 'mem_newsletter_subscriptions', json_encode($subscriptions));
        }

        return true;
    }

    /**
     * Check if user is subscribed to a newsletter
     *
     * @param int $user_id User ID
     * @param string $newsletter_key Newsletter key
     * @return bool
     */
    public static function is_user_subscribed($user_id, $newsletter_key) {
        $subscriptions = self::get_user_subscriptions($user_id);
        return in_array($newsletter_key, $subscriptions);
    }

    /**
     * Get subscribers count for a newsletter
     *
     * @param string $newsletter_key Newsletter key
     * @return int
     */
    public static function get_subscriber_count($newsletter_key) {
        global $wpdb;

        // Search in user meta for subscriptions containing this key
        $results = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta}
            WHERE meta_key = 'mem_newsletter_subscriptions'
            AND meta_value LIKE %s",
            '%"' . $wpdb->esc_like($newsletter_key) . '"%'
        ));

        return (int) $results;
    }

    /**
     * Update a newsletter
     *
     * @param int $newsletter_id Newsletter ID
     * @param array $data Data to update
     * @return bool
     */
    public static function update($newsletter_id, $data) {
        global $wpdb;

        $table_name = Eau_Membership_Database::get_table_name(Eau_Membership_Database::TABLE_NEWSLETTERS);

        // Encode JSON fields
        if (isset($data['allowed_membership_types']) && is_array($data['allowed_membership_types'])) {
            $data['allowed_membership_types'] = json_encode($data['allowed_membership_types']);
        }

        return $wpdb->update(
            $table_name,
            $data,
            array('newsletter_id' => $newsletter_id)
        ) !== false;
    }

    /**
     * Decode JSON fields in a newsletter object
     *
     * @param object $newsletter Newsletter object
     * @return object
     */
    private static function decode_json_fields($newsletter) {
        // Decode allowed_membership_types
        if (isset($newsletter->allowed_membership_types) && !empty($newsletter->allowed_membership_types)) {
            $decoded = json_decode($newsletter->allowed_membership_types, true);
            $newsletter->allowed_membership_types = is_array($decoded) ? $decoded : array();
        } else {
            $newsletter->allowed_membership_types = array();
        }

        // Cast numeric/boolean fields
        $newsletter->newsletter_id = (int) $newsletter->newsletter_id;
        $newsletter->is_public = (bool) $newsletter->is_public;
        $newsletter->is_active = (bool) $newsletter->is_active;
        $newsletter->display_order = (int) $newsletter->display_order;

        return $newsletter;
    }

    /**
     * Get newsletters grouped by access type (for forms)
     *
     * @return array With 'public' and 'members_only' keys
     */
    public static function get_grouped() {
        return array(
            'public' => self::get_public(),
            'members_only' => self::get_members_only(),
        );
    }
}
