<?php
namespace EauSystem\Ajax;

use EauSystem\Eau_User_Institution_Helper;
use EauSystem\Eau_Institution_Requests_Database;
use EauSystem\Helpers\Eau_Location_Data;

/**
 * AJAX Handlers for My Institution page
 *
 * @since 1.44.0
 */
class Eau_My_Institution_Ajax {

    /**
     * Register AJAX handlers
     */
    public static function register_handlers() {
        // Get current user's institution(s)
        add_action('wp_ajax_eau_get_my_institution', array(__CLASS__, 'get_my_institution'));

        // Search institutions
        add_action('wp_ajax_eau_search_institutions_public', array(__CLASS__, 'search_institutions'));

        // Request to join institution
        add_action('wp_ajax_eau_request_institution_link', array(__CLASS__, 'request_institution_link'));

        // Cancel pending request
        add_action('wp_ajax_eau_cancel_institution_request', array(__CLASS__, 'cancel_institution_request'));

        // Get user's pending requests
        add_action('wp_ajax_eau_get_my_pending_requests', array(__CLASS__, 'get_my_pending_requests'));

        // Get incoming requests (for institutionAdmin)
        add_action('wp_ajax_eau_get_incoming_institution_requests', array(__CLASS__, 'get_incoming_requests'));

        // Respond to request (approve/reject)
        add_action('wp_ajax_eau_respond_institution_request', array(__CLASS__, 'respond_institution_request'));

        // Leave institution
        add_action('wp_ajax_eau_leave_institution', array(__CLASS__, 'leave_institution'));

        // Get stats for My Institution page
        add_action('wp_ajax_eau_get_my_institution_stats', array(__CLASS__, 'get_my_institution_stats'));

        // Get user's request history
        add_action('wp_ajax_eau_get_my_request_history', array(__CLASS__, 'get_my_request_history'));

        // Get institution request history (for institutionAdmin)
        add_action('wp_ajax_eau_get_institution_request_history', array(__CLASS__, 'get_institution_request_history'));

        // Get full institution details for editing (for institutionAdmin)
        add_action('wp_ajax_eau_get_institution_for_edit', array(__CLASS__, 'get_institution_for_edit'));

        // Update institution (for institutionAdmin)
        add_action('wp_ajax_eau_update_my_institution', array(__CLASS__, 'update_my_institution'));

        // Location data endpoints
        add_action('wp_ajax_eau_get_countries', array(__CLASS__, 'get_countries'));
        add_action('wp_ajax_eau_get_states', array(__CLASS__, 'get_states'));
        add_action('wp_ajax_eau_get_cities', array(__CLASS__, 'get_cities'));
    }

    /**
     * AJAX: Get current user's institution(s)
     */
    public static function get_my_institution() {
        check_ajax_referer('eau_my_institution_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not logged in'));
        }

        $user_id = get_current_user_id();
        $mem_type = get_user_meta($user_id, 'mem_type', true);

        $institutions = array();

        // For institutionAdmin - get all managed institutions
        if ($mem_type === 'institutionAdmin') {
            $managed = Eau_User_Institution_Helper::get_user_managed_institutions($user_id);
            foreach ($managed as $inst) {
                $institutions[] = self::format_institution_data($inst->ID, 'admin');
            }
        }

        // For all users - get member institution (via mem_membercompanyname)
        $member_institution = Eau_User_Institution_Helper::get_user_institution($user_id);
        if ($member_institution) {
            // Check if not already in list (for institutionAdmin who is also a member)
            $already_added = false;
            foreach ($institutions as $inst) {
                if ($inst['id'] === $member_institution->ID) {
                    $already_added = true;
                    break;
                }
            }
            if (!$already_added) {
                $institutions[] = self::format_institution_data($member_institution->ID, 'member');
            }
        }

        wp_send_json_success(array(
            'institutions' => $institutions,
            'user_type' => $mem_type,
            'can_have_multiple' => ($mem_type === 'institutionAdmin'),
        ));
    }

    /**
     * Format institution data for response
     *
     * @param int $institution_id Institution post ID
     * @param string $role User's role in this institution (admin/member)
     * @return array
     */
    private static function format_institution_data($institution_id, $role = 'member') {
        $post = get_post($institution_id);
        if (!$post) {
            return null;
        }

        return array(
            'id' => $institution_id,
            'name' => $post->post_title,
            'company_id' => get_post_meta($institution_id, 'ins_company_id', true),
            'type' => get_post_meta($institution_id, 'ins_type', true),
            'status' => get_post_meta($institution_id, 'ins_status', true) ?: 'active',
            'email' => get_post_meta($institution_id, 'ins_company_email', true),
            'phone' => get_post_meta($institution_id, 'ins_company_phone', true),
            'website' => get_post_meta($institution_id, 'ins_company_website', true),
            'address' => get_post_meta($institution_id, 'ins_company_address', true),
            'city' => get_post_meta($institution_id, 'ins_company_city', true),
            'state' => get_post_meta($institution_id, 'ins_company_state', true),
            'postcode' => get_post_meta($institution_id, 'ins_company_postcode', true),
            'country' => get_post_meta($institution_id, 'ins_company_country', true),
            'logo' => get_post_meta($institution_id, 'ins_company_logo', true),
            'role' => $role,
        );
    }

    /**
     * AJAX: Search institutions
     */
    public static function search_institutions() {
        check_ajax_referer('eau_my_institution_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not logged in'));
        }

        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $page = isset($_POST['page']) ? absint($_POST['page']) : 1;
        $per_page = isset($_POST['per_page']) ? absint($_POST['per_page']) : 10;

        $user_id = get_current_user_id();

        // Get current user's institution IDs
        $current_institution_ids = self::get_user_institution_ids($user_id);

        // Get pending request institution IDs
        $pending_requests = Eau_Institution_Requests_Database::get_user_pending_requests($user_id);
        $pending_institution_ids = array_map(function($r) { return $r->institution_id; }, $pending_requests);

        // Query institutions
        $args = array(
            'post_type' => 'institutions',
            'post_status' => 'publish',
            'posts_per_page' => $per_page,
            'paged' => $page,
            'orderby' => 'title',
            'order' => 'ASC',
        );

        if (!empty($search)) {
            $args['s'] = $search;
        }

        // Only active institutions
        $args['meta_query'] = array(
            'relation' => 'OR',
            array(
                'key' => 'ins_status',
                'compare' => 'NOT EXISTS',
            ),
            array(
                'key' => 'ins_status',
                'value' => 'active',
            ),
        );

        $query = new \WP_Query($args);

        $results = array();
        foreach ($query->posts as $post) {
            $is_current = in_array($post->ID, $current_institution_ids);
            $has_pending = in_array($post->ID, $pending_institution_ids);

            $results[] = array(
                'id' => $post->ID,
                'name' => $post->post_title,
                'type' => get_post_meta($post->ID, 'ins_type', true),
                'city' => get_post_meta($post->ID, 'ins_company_city', true),
                'state' => get_post_meta($post->ID, 'ins_company_state', true),
                'is_current' => $is_current,
                'has_pending_request' => $has_pending,
            );
        }

        wp_send_json_success(array(
            'institutions' => $results,
            'total' => $query->found_posts,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => $query->max_num_pages,
        ));
    }

    /**
     * Get all institution IDs user is linked to
     *
     * @param int $user_id User ID
     * @return array Array of institution post IDs
     */
    private static function get_user_institution_ids($user_id) {
        $ids = array();

        $mem_type = get_user_meta($user_id, 'mem_type', true);

        // For institutionAdmin - managed institutions
        if ($mem_type === 'institutionAdmin') {
            $managed = Eau_User_Institution_Helper::get_user_managed_institutions($user_id);
            foreach ($managed as $inst) {
                $ids[] = $inst->ID;
            }
        }

        // For all - member institution
        $member_institution = Eau_User_Institution_Helper::get_user_institution($user_id);
        if ($member_institution && !in_array($member_institution->ID, $ids)) {
            $ids[] = $member_institution->ID;
        }

        return $ids;
    }

    /**
     * AJAX: Request to join institution
     */
    public static function request_institution_link() {
        check_ajax_referer('eau_my_institution_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not logged in'));
        }

        $institution_id = isset($_POST['institution_id']) ? absint($_POST['institution_id']) : 0;

        if (!$institution_id) {
            wp_send_json_error(array('message' => 'Invalid institution'));
        }

        // Verify institution exists
        $institution = get_post($institution_id);
        if (!$institution || $institution->post_type !== 'institutions') {
            wp_send_json_error(array('message' => 'Institution not found'));
        }

        $user_id = get_current_user_id();
        $mem_type = get_user_meta($user_id, 'mem_type', true);

        // Check if already linked to this institution
        $current_ids = self::get_user_institution_ids($user_id);
        if (in_array($institution_id, $current_ids)) {
            wp_send_json_error(array('message' => 'You are already linked to this institution'));
        }

        // Check for existing pending request
        if (Eau_Institution_Requests_Database::has_pending_request($user_id, $institution_id)) {
            wp_send_json_error(array('message' => 'You already have a pending request for this institution'));
        }

        // For regular members - check if they already have an institution
        $will_replace = false;
        if ($mem_type === 'member' && !empty($current_ids)) {
            $will_replace = true;
        }

        // Create the request
        $request_id = Eau_Institution_Requests_Database::create_request($user_id, $institution_id);

        if (!$request_id) {
            wp_send_json_error(array('message' => 'Failed to create request'));
        }

        wp_send_json_success(array(
            'message' => 'Request submitted successfully',
            'request_id' => $request_id,
            'will_replace_current' => $will_replace,
        ));
    }

    /**
     * AJAX: Cancel pending request
     */
    public static function cancel_institution_request() {
        check_ajax_referer('eau_my_institution_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not logged in'));
        }

        $request_id = isset($_POST['request_id']) ? absint($_POST['request_id']) : 0;

        if (!$request_id) {
            wp_send_json_error(array('message' => 'Invalid request'));
        }

        $user_id = get_current_user_id();

        // Verify request belongs to user and is pending
        $request = Eau_Institution_Requests_Database::get_request($request_id);
        if (!$request || $request->user_id != $user_id) {
            wp_send_json_error(array('message' => 'Request not found'));
        }

        if ($request->status !== Eau_Institution_Requests_Database::STATUS_PENDING) {
            wp_send_json_error(array('message' => 'Only pending requests can be cancelled'));
        }

        $result = Eau_Institution_Requests_Database::cancel_request($request_id, $user_id);

        if ($result) {
            wp_send_json_success(array('message' => 'Request cancelled successfully'));
        } else {
            wp_send_json_error(array('message' => 'Failed to cancel request'));
        }
    }

    /**
     * AJAX: Get user's pending requests
     */
    public static function get_my_pending_requests() {
        check_ajax_referer('eau_my_institution_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not logged in'));
        }

        $user_id = get_current_user_id();
        $requests = Eau_Institution_Requests_Database::get_user_pending_requests($user_id);

        $formatted = array();
        foreach ($requests as $request) {
            $formatted[] = array(
                'request_id' => $request->request_id,
                'institution_id' => $request->institution_id,
                'institution_name' => $request->institution_name,
                'request_date' => $request->request_date,
                'request_date_formatted' => date_i18n('M j, Y', strtotime($request->request_date)),
            );
        }

        wp_send_json_success(array('requests' => $formatted));
    }

    /**
     * AJAX: Get incoming requests (for institutionAdmin)
     */
    public static function get_incoming_requests() {
        check_ajax_referer('eau_my_institution_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not logged in'));
        }

        $user_id = get_current_user_id();
        $mem_type = get_user_meta($user_id, 'mem_type', true);

        // Only institutionAdmin can see incoming requests
        if ($mem_type !== 'institutionAdmin') {
            wp_send_json_success(array('requests' => array(), 'total' => 0));
        }

        $page = isset($_POST['page']) ? absint($_POST['page']) : 1;
        $per_page = isset($_POST['per_page']) ? absint($_POST['per_page']) : 20;
        $offset = ($page - 1) * $per_page;

        // Get managed institution IDs
        $managed = Eau_User_Institution_Helper::get_user_managed_institutions($user_id);
        $institution_ids = array_map(function($inst) { return $inst->ID; }, $managed);

        if (empty($institution_ids)) {
            wp_send_json_success(array('requests' => array(), 'total' => 0));
        }

        $result = Eau_Institution_Requests_Database::get_incoming_requests(
            $institution_ids,
            Eau_Institution_Requests_Database::STATUS_PENDING,
            $per_page,
            $offset
        );

        $formatted = array();
        foreach ($result['requests'] as $request) {
            // Get additional user meta
            $user_meta = array(
                'phone' => get_user_meta($request->user_id, 'mem_phone', true),
                'current_institution' => Eau_User_Institution_Helper::get_user_institution_name($request->user_id),
            );

            $formatted[] = array(
                'request_id' => $request->request_id,
                'user_id' => $request->user_id,
                'user_name' => $request->user_name,
                'user_email' => $request->user_email,
                'user_phone' => $user_meta['phone'],
                'current_institution' => $user_meta['current_institution'],
                'institution_id' => $request->institution_id,
                'institution_name' => $request->institution_name,
                'request_date' => $request->request_date,
                'request_date_formatted' => date_i18n('M j, Y g:i A', strtotime($request->request_date)),
            );
        }

        wp_send_json_success(array(
            'requests' => $formatted,
            'total' => $result['total'],
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil($result['total'] / $per_page),
        ));
    }

    /**
     * AJAX: Respond to institution request (approve/reject)
     */
    public static function respond_institution_request() {
        check_ajax_referer('eau_my_institution_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not logged in'));
        }

        $request_id = isset($_POST['request_id']) ? absint($_POST['request_id']) : 0;
        $action = isset($_POST['response_action']) ? sanitize_text_field($_POST['response_action']) : '';
        $notes = isset($_POST['notes']) ? sanitize_textarea_field($_POST['notes']) : '';

        if (!$request_id || !in_array($action, array('approve', 'reject'))) {
            wp_send_json_error(array('message' => 'Invalid parameters'));
        }

        $current_user_id = get_current_user_id();
        $mem_type = get_user_meta($current_user_id, 'mem_type', true);

        // Only institutionAdmin can respond
        if ($mem_type !== 'institutionAdmin') {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        // Get request
        $request = Eau_Institution_Requests_Database::get_request($request_id);
        if (!$request || $request->status !== Eau_Institution_Requests_Database::STATUS_PENDING) {
            wp_send_json_error(array('message' => 'Request not found or already processed'));
        }

        // Verify current user manages this institution
        $managed = Eau_User_Institution_Helper::get_user_managed_institutions($current_user_id);
        $managed_ids = array_map(function($inst) { return $inst->ID; }, $managed);

        if (!in_array($request->institution_id, $managed_ids)) {
            wp_send_json_error(array('message' => 'You do not manage this institution'));
        }

        if ($action === 'approve') {
            // Process approval
            $result = self::process_approval($request, $current_user_id, $notes);
        } else {
            // Process rejection
            $result = Eau_Institution_Requests_Database::update_status(
                $request_id,
                Eau_Institution_Requests_Database::STATUS_REJECTED,
                $current_user_id,
                $notes
            );
        }

        if ($result) {
            $message = $action === 'approve' ? 'Request approved successfully' : 'Request rejected';
            wp_send_json_success(array('message' => $message));
        } else {
            wp_send_json_error(array('message' => 'Failed to process request'));
        }
    }

    /**
     * Process approval of institution link request
     *
     * @param object $request Request object
     * @param int $responded_by User ID who approved
     * @param string $notes Optional notes
     * @return bool Success
     */
    private static function process_approval($request, $responded_by, $notes = '') {
        $user_id = $request->user_id;
        $institution_id = $request->institution_id;

        // Get institution's company_id
        $new_company_id = get_post_meta($institution_id, 'ins_company_id', true);
        if (empty($new_company_id)) {
            return false;
        }

        $user_mem_type = get_user_meta($user_id, 'mem_type', true);

        // Get current institution for history
        $current_institution = Eau_User_Institution_Helper::get_user_institution($user_id);
        $previous_institution_id = $current_institution ? $current_institution->ID : null;

        // Start transaction-like operations
        $success = true;

        if ($user_mem_type === 'institutionAdmin') {
            // For institutionAdmin: add ins_company_primary_contact to new institution
            $mem_userid = get_user_meta($user_id, 'mem_userid', true);
            if (!empty($mem_userid)) {
                // Add as primary contact (or secondary - depends on business rules)
                // For now, we'll update/add the primary contact
                update_post_meta($institution_id, 'ins_company_primary_contact', $mem_userid);
            }
        } else {
            // For regular member: update mem_membercompanyname
            update_user_meta($user_id, 'mem_membercompanyname', $new_company_id);
        }

        // Save previous institution in request record
        if ($previous_institution_id) {
            Eau_Institution_Requests_Database::save_previous_institution($request->request_id, $previous_institution_id);
        }

        // Update request status
        $success = Eau_Institution_Requests_Database::update_status(
            $request->request_id,
            Eau_Institution_Requests_Database::STATUS_APPROVED,
            $responded_by,
            $notes
        );

        return $success;
    }

    /**
     * AJAX: Leave current institution
     */
    public static function leave_institution() {
        check_ajax_referer('eau_my_institution_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not logged in'));
        }

        $institution_id = isset($_POST['institution_id']) ? absint($_POST['institution_id']) : 0;

        if (!$institution_id) {
            wp_send_json_error(array('message' => 'Invalid institution'));
        }

        $user_id = get_current_user_id();
        $mem_type = get_user_meta($user_id, 'mem_type', true);

        // institutionAdmin cannot leave via this method (needs to be removed as admin)
        if ($mem_type === 'institutionAdmin') {
            // Check if this is their member institution, not admin
            $current_institution = Eau_User_Institution_Helper::get_user_institution($user_id);
            if (!$current_institution || $current_institution->ID != $institution_id) {
                wp_send_json_error(array('message' => 'Institution admins must be removed by a super admin'));
            }
        }

        // Get current institution
        $current_institution = Eau_User_Institution_Helper::get_user_institution($user_id);
        if (!$current_institution || $current_institution->ID != $institution_id) {
            wp_send_json_error(array('message' => 'You are not a member of this institution'));
        }

        // Clear the mem_membercompanyname
        delete_user_meta($user_id, 'mem_membercompanyname');

        wp_send_json_success(array('message' => 'You have left the institution'));
    }

    /**
     * AJAX: Get stats for My Institution page
     */
    public static function get_my_institution_stats() {
        check_ajax_referer('eau_my_institution_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not logged in'));
        }

        $user_id = get_current_user_id();
        $mem_type = get_user_meta($user_id, 'mem_type', true);

        $stats = array(
            'pending_requests' => 0,
            'incoming_requests' => 0,
        );

        // User's pending requests
        $pending = Eau_Institution_Requests_Database::get_user_pending_requests($user_id);
        $stats['pending_requests'] = count($pending);

        // Incoming requests (for institutionAdmin)
        if ($mem_type === 'institutionAdmin') {
            $managed = Eau_User_Institution_Helper::get_user_managed_institutions($user_id);
            $institution_ids = array_map(function($inst) { return $inst->ID; }, $managed);

            if (!empty($institution_ids)) {
                $stats['incoming_requests'] = Eau_Institution_Requests_Database::count_pending_for_institutions($institution_ids);
            }
        }

        wp_send_json_success($stats);
    }

    /**
     * AJAX: Get user's request history
     */
    public static function get_my_request_history() {
        check_ajax_referer('eau_my_institution_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not logged in'));
        }

        $user_id = get_current_user_id();
        $page = isset($_POST['page']) ? absint($_POST['page']) : 1;
        $per_page = isset($_POST['per_page']) ? absint($_POST['per_page']) : 10;
        $offset = ($page - 1) * $per_page;

        $result = Eau_Institution_Requests_Database::get_user_history($user_id, $per_page, $offset);

        $formatted = array();
        foreach ($result['requests'] as $request) {
            $formatted[] = array(
                'request_id' => $request->request_id,
                'institution_id' => $request->institution_id,
                'institution_name' => $request->institution_name,
                'status' => $request->status,
                'status_label' => self::get_status_label($request->status),
                'status_class' => self::get_status_class($request->status),
                'request_date' => $request->request_date,
                'request_date_formatted' => date_i18n('M j, Y', strtotime($request->request_date)),
                'response_date' => $request->response_date,
                'response_date_formatted' => $request->response_date ? date_i18n('M j, Y', strtotime($request->response_date)) : null,
                'responded_by_name' => $request->responded_by_name,
                'notes' => $request->notes,
            );
        }

        wp_send_json_success(array(
            'requests' => $formatted,
            'total' => $result['total'],
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil($result['total'] / $per_page),
        ));
    }

    /**
     * AJAX: Get institution request history (for institutionAdmin)
     */
    public static function get_institution_request_history() {
        check_ajax_referer('eau_my_institution_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not logged in'));
        }

        $user_id = get_current_user_id();
        $mem_type = get_user_meta($user_id, 'mem_type', true);

        // Only institutionAdmin can see history
        if ($mem_type !== 'institutionAdmin') {
            wp_send_json_success(array('requests' => array(), 'total' => 0));
        }

        $page = isset($_POST['page']) ? absint($_POST['page']) : 1;
        $per_page = isset($_POST['per_page']) ? absint($_POST['per_page']) : 10;
        $offset = ($page - 1) * $per_page;

        // Get managed institution IDs
        $managed = Eau_User_Institution_Helper::get_user_managed_institutions($user_id);
        $institution_ids = array_map(function($inst) { return $inst->ID; }, $managed);

        if (empty($institution_ids)) {
            wp_send_json_success(array('requests' => array(), 'total' => 0));
        }

        $result = Eau_Institution_Requests_Database::get_institution_history($institution_ids, $per_page, $offset);

        $formatted = array();
        foreach ($result['requests'] as $request) {
            $formatted[] = array(
                'request_id' => $request->request_id,
                'user_id' => $request->user_id,
                'user_name' => $request->user_name,
                'user_email' => $request->user_email,
                'institution_id' => $request->institution_id,
                'institution_name' => $request->institution_name,
                'status' => $request->status,
                'status_label' => self::get_status_label($request->status),
                'status_class' => self::get_status_class($request->status),
                'request_date' => $request->request_date,
                'request_date_formatted' => date_i18n('M j, Y', strtotime($request->request_date)),
                'response_date' => $request->response_date,
                'response_date_formatted' => $request->response_date ? date_i18n('M j, Y', strtotime($request->response_date)) : null,
                'responded_by_name' => $request->responded_by_name,
                'notes' => $request->notes,
            );
        }

        wp_send_json_success(array(
            'requests' => $formatted,
            'total' => $result['total'],
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil($result['total'] / $per_page),
        ));
    }

    /**
     * Get human-readable status label
     *
     * @param string $status Status code
     * @return string Label
     */
    private static function get_status_label($status) {
        $labels = array(
            'pending' => 'Pending',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'cancelled' => 'Cancelled',
        );
        return isset($labels[$status]) ? $labels[$status] : ucfirst($status);
    }

    /**
     * Get CSS class for status badge
     *
     * @param string $status Status code
     * @return string CSS class
     */
    private static function get_status_class($status) {
        $classes = array(
            'pending' => 'warning',
            'approved' => 'success',
            'rejected' => 'danger',
            'cancelled' => 'secondary',
        );
        return isset($classes[$status]) ? $classes[$status] : 'secondary';
    }

    /**
     * AJAX: Get institution details for editing (institutionAdmin only)
     */
    public static function get_institution_for_edit() {
        check_ajax_referer('eau_my_institution_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not logged in'));
        }

        $institution_id = isset($_POST['institution_id']) ? absint($_POST['institution_id']) : 0;

        if (!$institution_id) {
            wp_send_json_error(array('message' => 'Invalid institution ID'));
        }

        $user_id = get_current_user_id();
        $mem_type = get_user_meta($user_id, 'mem_type', true);

        // Only institutionAdmin can edit
        if ($mem_type !== 'institutionAdmin') {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        // Verify user manages this institution
        $managed = Eau_User_Institution_Helper::get_user_managed_institutions($user_id);
        $managed_ids = array_map(function($inst) { return $inst->ID; }, $managed);

        if (!in_array($institution_id, $managed_ids)) {
            wp_send_json_error(array('message' => 'You do not manage this institution'));
        }

        // Get institution data
        $post = get_post($institution_id);
        if (!$post || $post->post_type !== 'institutions') {
            wp_send_json_error(array('message' => 'Institution not found'));
        }

        $logo_id = get_post_meta($institution_id, 'ins_company_logo', true);
        $logo_url = $logo_id ? wp_get_attachment_url($logo_id) : '';

        $institution = array(
            'id' => $institution_id,
            'post_title' => $post->post_title,
            'ins_company_id' => get_post_meta($institution_id, 'ins_company_id', true),
            'ins_company_name' => get_post_meta($institution_id, 'ins_company_name', true) ?: $post->post_title,
            'ins_company_email' => get_post_meta($institution_id, 'ins_company_email', true),
            'ins_company_company_phone' => get_post_meta($institution_id, 'ins_company_company_phone', true),
            'ins_company_company_address_line_1' => get_post_meta($institution_id, 'ins_company_company_address_line_1', true),
            'ins_company_company_suburb' => get_post_meta($institution_id, 'ins_company_company_suburb', true),
            'ins_company_company_state' => get_post_meta($institution_id, 'ins_company_company_state', true),
            'ins_company_company_postcode' => get_post_meta($institution_id, 'ins_company_company_postcode', true),
            'ins_company_company_country' => get_post_meta($institution_id, 'ins_company_company_country', true),
            'ins_status' => get_post_meta($institution_id, 'ins_status', true) ?: 'active',
            'ins_company_logo' => $logo_id,
            'ins_company_logo_url' => $logo_url,
        );

        wp_send_json_success($institution);
    }

    /**
     * AJAX: Update institution (institutionAdmin only)
     */
    public static function update_my_institution() {
        check_ajax_referer('eau_my_institution_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not logged in'));
        }

        $institution_id = isset($_POST['institution_id']) ? absint($_POST['institution_id']) : 0;
        $fields = isset($_POST['fields']) ? $_POST['fields'] : array();

        if (!$institution_id) {
            wp_send_json_error(array('message' => 'Invalid institution ID'));
        }

        $user_id = get_current_user_id();
        $mem_type = get_user_meta($user_id, 'mem_type', true);

        // Only institutionAdmin can update
        if ($mem_type !== 'institutionAdmin') {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        // Verify user manages this institution
        $managed = Eau_User_Institution_Helper::get_user_managed_institutions($user_id);
        $managed_ids = array_map(function($inst) { return $inst->ID; }, $managed);

        if (!in_array($institution_id, $managed_ids)) {
            wp_send_json_error(array('message' => 'You do not manage this institution'));
        }

        // Get institution
        $post = get_post($institution_id);
        if (!$post || $post->post_type !== 'institutions') {
            wp_send_json_error(array('message' => 'Institution not found'));
        }

        // Allowed fields for institutionAdmin to update
        $allowed_fields = array(
            'ins_company_name',
            'ins_company_email',
            'ins_company_company_phone',
            'ins_company_company_address_line_1',
            'ins_company_company_suburb',
            'ins_company_company_state',
            'ins_company_company_postcode',
            'ins_company_company_country',
            'ins_company_logo',
            'ins_status',
        );

        $updated_count = 0;
        foreach ($fields as $key => $value) {
            if (in_array($key, $allowed_fields)) {
                if ($key === 'ins_company_email') {
                    $value = sanitize_email($value);
                } elseif ($key === 'ins_company_logo') {
                    $value = absint($value);
                } else {
                    $value = sanitize_text_field($value);
                }
                update_post_meta($institution_id, $key, $value);
                $updated_count++;
            }
        }

        // Update post title if company_name was changed
        if (isset($fields['ins_company_name']) && !empty($fields['ins_company_name'])) {
            wp_update_post(array(
                'ID' => $institution_id,
                'post_title' => sanitize_text_field($fields['ins_company_name']),
            ));
        }

        if ($updated_count > 0) {
            wp_send_json_success(array('message' => 'Institution updated successfully'));
        } else {
            wp_send_json_error(array('message' => 'No valid fields to update'));
        }
    }

    /**
     * AJAX: Get countries list
     */
    public static function get_countries() {
        check_ajax_referer('eau_my_institution_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not logged in'));
        }

        $countries = Eau_Location_Data::get_countries();

        wp_send_json_success(array('countries' => $countries));
    }

    /**
     * AJAX: Get states for a country
     */
    public static function get_states() {
        check_ajax_referer('eau_my_institution_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not logged in'));
        }

        $country_code = isset($_POST['country']) ? sanitize_text_field($_POST['country']) : '';

        if (empty($country_code)) {
            wp_send_json_success(array('states' => array()));
        }

        $states = Eau_Location_Data::get_states($country_code);

        wp_send_json_success(array(
            'states' => $states,
            'has_detailed_data' => Eau_Location_Data::has_detailed_data($country_code),
        ));
    }

    /**
     * AJAX: Get cities for a state
     */
    public static function get_cities() {
        check_ajax_referer('eau_my_institution_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not logged in'));
        }

        $country_code = isset($_POST['country']) ? sanitize_text_field($_POST['country']) : '';
        $state_code = isset($_POST['state']) ? sanitize_text_field($_POST['state']) : '';

        if (empty($country_code) || empty($state_code)) {
            wp_send_json_success(array('cities' => array()));
        }

        $cities = Eau_Location_Data::get_cities($country_code, $state_code);

        wp_send_json_success(array('cities' => $cities));
    }
}
