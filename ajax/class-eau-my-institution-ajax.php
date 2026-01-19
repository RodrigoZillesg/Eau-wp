<?php
namespace EauSystem\Ajax;

use EauSystem\Eau_User_Institution_Helper;
use EauSystem\Eau_Institution_Requests_Database;
use EauSystem\Eau_Member_Institution_Service;
use EauSystem\Eau_Member_Institution_Database;
use EauSystem\Helpers\Eau_Location_Data;
use EauSystem\Email\Email_Service;
use EauSystem\Email\Email_Template;

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

        // Upload file for institution logo
        add_action('wp_ajax_eau_upload_institution_file', array(__CLASS__, 'upload_institution_file'));

        // Get institution embedded HTML (for My Institution tabs)
        add_action('wp_ajax_eau_get_institution_embedded', array(__CLASS__, 'get_institution_embedded'));

        // Get all institutions for select dropdown (v1.65.2)
        add_action('wp_ajax_eau_get_all_institutions_for_select', array(__CLASS__, 'get_all_institutions_for_select'));
    }

    /**
     * AJAX: Get current user's institution(s)
     *
     * @since 1.66.5 - Atualizado para usar o novo sistema de separação de papéis
     */
    public static function get_my_institution() {
        check_ajax_referer('eau_my_institution_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not logged in'));
        }

        $user_id = get_current_user_id();
        $is_inst_admin = Eau_User_Institution_Helper::is_institution_admin($user_id);

        $institutions = array();

        // For institutionAdmin - get all managed institutions (v1.66.5 - usa helper)
        if ($is_inst_admin) {
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
            'user_type' => get_user_meta($user_id, 'mem_type', true),
            'can_have_multiple' => $is_inst_admin,
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

        // Get logo - convert attachment ID to URL
        $logo_id = get_post_meta($institution_id, 'ins_company_logo', true);
        $logo_url = '';
        if (!empty($logo_id) && is_numeric($logo_id)) {
            $logo_url = wp_get_attachment_url($logo_id);
        } elseif (!empty($logo_id) && filter_var($logo_id, FILTER_VALIDATE_URL)) {
            // If it's already a URL, use it directly
            $logo_url = $logo_id;
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
            'logo' => $logo_url,
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
     * AJAX: Get all institutions for select dropdown
     *
     * Returns all active institutions with status flags for the current user.
     * Used by the "Find an Institution" select dropdown.
     *
     * @since 1.65.2
     */
    public static function get_all_institutions_for_select() {
        check_ajax_referer('eau_my_institution_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not logged in'));
        }

        $user_id = get_current_user_id();

        // Get current user's institution IDs
        $current_institution_ids = self::get_user_institution_ids($user_id);

        // Get pending request institution IDs
        $pending_requests = Eau_Institution_Requests_Database::get_user_pending_requests($user_id);
        $pending_institution_ids = array_map(function($r) { return $r->institution_id; }, $pending_requests);

        // Query ALL active institutions
        $args = array(
            'post_type' => 'institutions',
            'post_status' => 'publish',
            'posts_per_page' => -1, // Get all
            'orderby' => 'title',
            'order' => 'ASC',
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => 'ins_status',
                    'compare' => 'NOT EXISTS',
                ),
                array(
                    'key' => 'ins_status',
                    'value' => 'active',
                ),
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
                'country' => get_post_meta($post->ID, 'ins_company_country', true),
                'email' => get_post_meta($post->ID, 'ins_company_email', true),
                'phone' => get_post_meta($post->ID, 'ins_company_phone', true),
                'logo' => get_post_meta($post->ID, 'ins_company_logo', true),
                'is_current' => $is_current,
                'has_pending_request' => $has_pending,
            );
        }

        wp_send_json_success(array(
            'institutions' => $results,
            'total' => count($results),
        ));
    }

    /**
     * Get all institution IDs user is linked to
     *
     * @since 1.66.5 - Atualizado para usar o novo sistema de separação de papéis
     * @param int $user_id User ID
     * @return array Array of institution post IDs
     */
    private static function get_user_institution_ids($user_id) {
        $ids = array();

        // For institutionAdmin - managed institutions (v1.66.5 - usa helper)
        if (Eau_User_Institution_Helper::is_institution_admin($user_id)) {
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
     *
     * @since 1.66.5 - Atualizado para usar o novo sistema de separação de papéis
     */
    public static function get_incoming_requests() {
        check_ajax_referer('eau_my_institution_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not logged in'));
        }

        $user_id = get_current_user_id();

        // Only institutionAdmin can see incoming requests (v1.66.5 - usa helper)
        if (!Eau_User_Institution_Helper::is_institution_admin($user_id)) {
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

        // Only institutionAdmin can respond (v1.66.6 - usa helper)
        if (!Eau_User_Institution_Helper::is_institution_admin($current_user_id)) {
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
            // v1.66.7 - Envia email de notificação para o membro
            self::send_request_response_email($request, $action, $notes, $current_user_id);

            $message = $action === 'approve' ? 'Request approved successfully' : 'Request rejected';
            wp_send_json_success(array('message' => $message));
        } else {
            wp_send_json_error(array('message' => 'Failed to process request'));
        }
    }

    /**
     * Envia email de notificação para o membro sobre a resposta da solicitação
     *
     * @since 1.66.7
     * @param object $request Request object
     * @param string $action 'approve' ou 'reject'
     * @param string $notes Notas opcionais
     * @param int $responded_by User ID de quem respondeu
     */
    private static function send_request_response_email($request, $action, $notes, $responded_by) {
        $user = get_userdata($request->user_id);
        if (!$user) {
            return;
        }

        $institution = get_post($request->institution_id);
        $institution_name = $institution ? $institution->post_title : 'Unknown';

        $responded_by_user = get_userdata($responded_by);
        $responded_by_name = $responded_by_user ? $responded_by_user->display_name : 'Administrator';

        $member_name = trim($user->first_name . ' ' . $user->last_name);
        if (empty($member_name)) {
            $member_name = $user->display_name;
        }

        if ($action === 'approve') {
            $subject = 'Your request to join ' . $institution_name . ' has been approved';
            $status_text = 'approved';
            $status_color = '#10b981'; // green
            $additional_info = 'You are now a member of <strong>' . esc_html($institution_name) . '</strong>. You can access your institution details from your member portal.';
        } else {
            $subject = 'Your request to join ' . $institution_name . ' has been declined';
            $status_text = 'declined';
            $status_color = '#ef4444'; // red
            $additional_info = 'Unfortunately, your request to join <strong>' . esc_html($institution_name) . '</strong> was not approved at this time.';
        }

        // Build email content
        $content = '<p>Dear ' . esc_html($member_name) . ',</p>';

        $content .= Email_Template::info_box(
            'Request Status: <span style="color: ' . $status_color . '; text-transform: uppercase;">' . $status_text . '</span>',
            '<strong>Institution:</strong> ' . esc_html($institution_name) . '<br>' .
            '<strong>Responded by:</strong> ' . esc_html($responded_by_name) . '<br>' .
            '<strong>Date:</strong> ' . date_i18n('F j, Y \a\t g:i A')
        );

        $content .= '<p>' . $additional_info . '</p>';

        if (!empty($notes)) {
            $content .= '<p><strong>Administrator Notes:</strong></p>';
            $content .= '<blockquote style="margin: 1rem 0; padding: 1rem; background: #f9fafb; border-left: 4px solid #d1d5db; color: #374151;">' . nl2br(esc_html($notes)) . '</blockquote>';
        }

        $content .= Email_Template::button('Go to Member Portal', home_url('/dashboard/my-institution/'));

        // Send email
        Email_Service::send($user->user_email, $subject, $content);
    }

    /**
     * Process approval of institution link request
     *
     * Uses centralized Eau_Member_Institution_Service for proper linking/transfer
     * with history tracking.
     *
     * @param object $request Request object
     * @param int $responded_by User ID who approved
     * @param string $notes Optional notes
     * @return bool Success
     *
     * @since 1.44.0
     * @since 1.65.4 Uses centralized service with history tracking
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

        // Get new institution name for reason
        $new_institution = get_post($institution_id);
        $new_institution_name = $new_institution ? $new_institution->post_title : 'Unknown';

        // Build reason for history
        $reason = sprintf('Approved to join institution "%s"', $new_institution_name);
        if (!empty($notes)) {
            $reason .= '. Notes: ' . $notes;
        }

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

            // Also link via mem_membercompanyname if they have a previous one
            if ($previous_institution_id) {
                // Transfer to new institution using centralized service
                $result = Eau_Member_Institution_Service::transfer_member($user_id, $institution_id, array(
                    'member_type' => 'institutionAdmin',
                    'set_as_primary_contact' => true,
                    'performed_by' => $responded_by,
                    'reason' => $reason,
                    'force' => true, // Allow transfer even if only admin
                ));

                if (is_wp_error($result)) {
                    // Log error but continue - the primary contact was already set
                    error_log('Eau System: Failed to transfer institutionAdmin: ' . $result->get_error_message());
                }
            } else {
                // Link to new institution using centralized service
                $result = Eau_Member_Institution_Service::link_member($user_id, $institution_id, array(
                    'member_type' => 'institutionAdmin',
                    'set_as_primary_contact' => true,
                    'performed_by' => $responded_by,
                    'reason' => $reason,
                ));

                if (is_wp_error($result)) {
                    error_log('Eau System: Failed to link institutionAdmin: ' . $result->get_error_message());
                }
            }
        } else {
            // For regular member: use centralized service
            if ($previous_institution_id) {
                // Transfer to new institution (this handles unlink from old + link to new + history)
                $result = Eau_Member_Institution_Service::transfer_member($user_id, $institution_id, array(
                    'member_type' => 'member',
                    'performed_by' => $responded_by,
                    'reason' => $reason,
                ));

                if (is_wp_error($result)) {
                    // Fallback to direct update if transfer fails
                    error_log('Eau System: Failed to transfer member: ' . $result->get_error_message());
                    update_user_meta($user_id, 'mem_membercompanyname', $new_company_id);
                }
            } else {
                // Link to new institution using centralized service
                $result = Eau_Member_Institution_Service::link_member($user_id, $institution_id, array(
                    'member_type' => 'member',
                    'performed_by' => $responded_by,
                    'reason' => $reason,
                ));

                if (is_wp_error($result)) {
                    // Fallback to direct update if link fails
                    error_log('Eau System: Failed to link member: ' . $result->get_error_message());
                    update_user_meta($user_id, 'mem_membercompanyname', $new_company_id);
                }
            }
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
     *
     * Uses centralized Eau_Member_Institution_Service for proper unlinking
     * with validation and history tracking.
     *
     * @since 1.44.0
     * @since 1.53.0 Uses centralized service with validation
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

        // Get current institution
        $current_institution = Eau_User_Institution_Helper::get_user_institution($user_id);
        if (!$current_institution || $current_institution->ID != $institution_id) {
            wp_send_json_error(array('message' => 'You are not a member of this institution'));
        }

        // Check if member can be unlinked (validates if only admin)
        $can_unlink = Eau_Member_Institution_Service::can_unlink_member($user_id);
        if (is_wp_error($can_unlink)) {
            wp_send_json_error(array(
                'message' => $can_unlink->get_error_message(),
                'error_code' => $can_unlink->get_error_code(),
            ));
        }

        // Use centralized service to unlink member
        $result = Eau_Member_Institution_Service::unlink_member($user_id, array(
            'reason' => 'Member left institution voluntarily',
        ));

        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'message' => $result->get_error_message(),
                'error_code' => $result->get_error_code(),
            ));
        }

        wp_send_json_success(array('message' => 'You have left the institution'));
    }

    /**
     * AJAX: Get stats for My Institution page
     *
     * @since 1.66.5 - Atualizado para usar o novo sistema de separação de papéis
     */
    public static function get_my_institution_stats() {
        check_ajax_referer('eau_my_institution_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not logged in'));
        }

        $user_id = get_current_user_id();

        $stats = array(
            'pending_requests' => 0,
            'incoming_requests' => 0,
        );

        // User's pending requests
        $pending = Eau_Institution_Requests_Database::get_user_pending_requests($user_id);
        $stats['pending_requests'] = count($pending);

        // Incoming requests (for institutionAdmin - v1.66.5 usa helper)
        if (Eau_User_Institution_Helper::is_institution_admin($user_id)) {
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

        // v1.67.1 - Combina histórico de requests E histórico de member-institution
        $all_history = array();

        // 1. Get request history (join requests)
        $requests_result = Eau_Institution_Requests_Database::get_user_history($user_id, 100, 0);
        foreach ($requests_result['requests'] as $request) {
            $all_history[] = array(
                'type' => 'request',
                'request_id' => $request->request_id,
                'institution_id' => $request->institution_id,
                'institution_name' => $request->institution_name,
                'status' => $request->status,
                'status_label' => self::get_status_label($request->status),
                'status_class' => self::get_status_class($request->status),
                'date' => $request->response_date ?: $request->request_date,
                'date_formatted' => date_i18n('M j, Y', strtotime($request->response_date ?: $request->request_date)),
                'request_date' => $request->request_date,
                'request_date_formatted' => date_i18n('M j, Y', strtotime($request->request_date)),
                'response_date' => $request->response_date,
                'response_date_formatted' => $request->response_date ? date_i18n('M j, Y', strtotime($request->response_date)) : null,
                'responded_by_name' => $request->responded_by_name,
                'notes' => $request->notes,
                'sort_date' => strtotime($request->response_date ?: $request->request_date),
            );
        }

        // 2. Get member-institution history (linked/unlinked/transferred)
        $member_history = Eau_Member_Institution_Database::get_user_history($user_id, array('limit' => 100));
        foreach ($member_history as $history) {
            // Get institution name
            $institution_name = '';
            if ($history->institution_id) {
                $institution = get_post($history->institution_id);
                $institution_name = $institution ? $institution->post_title : '';
            }
            // For unlinked, use previous institution
            if (empty($institution_name) && $history->action === 'unlinked' && $history->previous_institution_id) {
                $institution = get_post($history->previous_institution_id);
                $institution_name = $institution ? $institution->post_title : '';
            }

            // Get who performed the action
            $performed_by_name = '';
            if ($history->performed_by) {
                $performer = get_user_by('ID', $history->performed_by);
                $performed_by_name = $performer ? $performer->display_name : '';
            }

            $all_history[] = array(
                'type' => 'membership',
                'history_id' => $history->id,
                'institution_id' => $history->institution_id ?: $history->previous_institution_id,
                'institution_name' => $institution_name,
                'status' => $history->action,
                'status_label' => self::get_status_label($history->action),
                'status_class' => self::get_status_class($history->action),
                'date' => $history->created_at,
                'date_formatted' => date_i18n('M j, Y', strtotime($history->created_at)),
                'request_date' => $history->created_at,
                'request_date_formatted' => date_i18n('M j, Y', strtotime($history->created_at)),
                'response_date' => null,
                'response_date_formatted' => null,
                'responded_by_name' => $performed_by_name,
                'notes' => $history->reason,
                'sort_date' => strtotime($history->created_at),
            );
        }

        // Sort by date descending
        usort($all_history, function($a, $b) {
            return $b['sort_date'] - $a['sort_date'];
        });

        // Remove sort_date from results and apply pagination
        $total = count($all_history);
        $offset = ($page - 1) * $per_page;
        $paginated = array_slice($all_history, $offset, $per_page);

        $formatted = array();
        foreach ($paginated as $item) {
            unset($item['sort_date']);
            $formatted[] = $item;
        }

        wp_send_json_success(array(
            'requests' => $formatted,
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil($total / $per_page),
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

        // Only institutionAdmin can see history (v1.66.6 - usa helper)
        if (!Eau_User_Institution_Helper::is_institution_admin($user_id)) {
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
            // v1.67.1 - Member-institution history statuses
            'linked' => 'Joined',
            'unlinked' => 'Left',
            'transferred' => 'Transferred',
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
            // v1.67.1 - Member-institution history statuses
            'linked' => 'success',
            'unlinked' => 'danger',
            'transferred' => 'info',
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

        // Only institutionAdmin can edit (v1.66.6 - usa helper)
        if (!Eau_User_Institution_Helper::is_institution_admin($user_id)) {
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

        // Only institutionAdmin can update (v1.66.6 - usa helper)
        if (!Eau_User_Institution_Helper::is_institution_admin($user_id)) {
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

    /**
     * AJAX: Upload file for institution logo
     *
     * @since 1.46.3
     */
    public static function upload_institution_file() {
        // Verifica nonce específico da página My Institution
        check_ajax_referer('eau_my_institution_nonce', 'nonce');

        // Verifica se está logado
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'You must be logged in to upload files.'));
        }

        // Verifica se é institutionAdmin (v1.66.6 - usa helper)
        $user_id = get_current_user_id();
        if (!Eau_User_Institution_Helper::is_institution_admin($user_id)) {
            wp_send_json_error(array('message' => 'Permission denied. Only institution administrators can upload files.'));
        }

        // Verifica se há arquivo
        if (empty($_FILES['file'])) {
            wp_send_json_error(array('message' => 'No file uploaded.'));
        }

        // Inclui funções necessárias para upload
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        $file = $_FILES['file'];

        // Validação de tamanho (10MB max por padrão)
        $max_size = isset($_POST['max_size']) ? intval($_POST['max_size']) : 10 * 1024 * 1024;
        if ($file['size'] > $max_size) {
            wp_send_json_error(array(
                'message' => 'File is too large. Maximum size is ' . size_format($max_size) . '.'
            ));
        }

        // Validação de extensão (se fornecida)
        if (!empty($_POST['allowed_extensions'])) {
            $allowed = array_map('trim', explode(',', strtolower($_POST['allowed_extensions'])));
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if (!in_array($ext, $allowed)) {
                wp_send_json_error(array(
                    'message' => 'File type not allowed. Allowed types: ' . implode(', ', array_map('strtoupper', $allowed))
                ));
            }
        }

        // Upload do arquivo
        $upload = wp_handle_upload($file, array('test_form' => false));

        if (isset($upload['error'])) {
            wp_send_json_error(array('message' => $upload['error']));
        }

        // Cria attachment na Media Library
        $attachment = array(
            'post_mime_type' => $upload['type'],
            'post_title'     => sanitize_file_name(pathinfo($file['name'], PATHINFO_FILENAME)),
            'post_content'   => '',
            'post_status'    => 'inherit',
            'post_author'    => get_current_user_id(),
        );

        $attachment_id = wp_insert_attachment($attachment, $upload['file']);

        if (is_wp_error($attachment_id)) {
            wp_send_json_error(array('message' => 'Failed to create attachment.'));
        }

        // Gera metadata para o attachment
        $attachment_data = wp_generate_attachment_metadata($attachment_id, $upload['file']);
        wp_update_attachment_metadata($attachment_id, $attachment_data);

        // Retorna dados do arquivo
        wp_send_json_success(array(
            'id' => $attachment_id,
            'url' => $upload['url'],
            'filename' => basename($upload['file']),
            'type' => $upload['type'],
            'size' => $file['size'],
            'size_formatted' => size_format($file['size']),
        ));
    }

    /**
     * AJAX: Get institution embedded HTML for My Institution tabs
     *
     * Returns the rendered single page for an institution to be embedded
     * in the My Institution page tabs.
     *
     * @since 1.65.0
     */
    public static function get_institution_embedded() {
        check_ajax_referer('eau_my_institution_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not logged in'));
        }

        $institution_id = isset($_POST['institution_id']) ? absint($_POST['institution_id']) : 0;

        if (!$institution_id) {
            wp_send_json_error(array('message' => 'Invalid institution ID'));
        }

        // Verifica se a instituição existe
        $institution = get_post($institution_id);
        if (!$institution || $institution->post_type !== 'institutions') {
            wp_send_json_error(array('message' => 'Institution not found'));
        }

        // Verifica se usuário tem permissão para ver esta instituição
        $user_id = get_current_user_id();
        $can_view = false;

        // Check if user is linked to this institution
        $current_ids = self::get_user_institution_ids($user_id);
        if (in_array($institution_id, $current_ids)) {
            $can_view = true;
        }

        // Check if user is superAdmin or Admin
        if (!$can_view && Eau_User_Institution_Helper::has_admin_access($user_id)) {
            $can_view = true;
        }

        if (!$can_view) {
            wp_send_json_error(array('message' => 'You do not have permission to view this institution'));
        }

        // Load the Eau_Institution_Single class if not already loaded
        if (!class_exists('\EauSystem\Eau_Institution_Single')) {
            require_once EAU_SYSTEM_PLUGIN_DIR . 'includes/class-eau-institution-single.php';
        }

        // Get user permissions for this institution
        $permissions = \EauSystem\Eau_Institution_Single::get_user_permissions($institution_id);
        $can_edit_members = \EauSystem\Eau_Institution_Single::user_can_edit_members($institution_id);

        // Render the embedded version
        $html = \EauSystem\Eau_Institution_Single::render_embedded($institution_id, array(
            'show_back_link' => false,
            'container_class' => 'eau-institution-embedded',
        ));

        // Add "Manage Institution" button for admins
        if ($permissions['can_edit'] || $can_edit_members) {
            $single_url = home_url('/institution/' . $institution_id . '/');
            $manage_button = '<div class="eau-manage-institution-link"><a href="' . esc_url($single_url) . '" class="eau-btn eau-btn-primary"><i data-lucide="settings"></i> Manage Institution</a></div>';

            // Insert button after the opening container div
            // Look for the container div and insert button right after it
            if (preg_match('/(<div[^>]*class="[^"]*eau-institution-single-container[^"]*"[^>]*>)/s', $html, $matches)) {
                $html = str_replace($matches[1], $matches[1] . $manage_button, $html);
            }
        }

        // Get can_manage_admins permission
        $can_manage_admins = \EauSystem\Eau_Institution_Single::user_can_manage_institution_admins($institution_id);

        wp_send_json_success(array(
            'html' => $html,
            'institution_id' => $institution_id,
            'institution_name' => $institution->post_title,
            // Include script data for JavaScript initialization
            'scriptData' => array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('eau_institution_single_nonce'),
                'canEdit' => $permissions['can_edit'],
                'canEditRestricted' => $permissions['can_edit_restricted'],
                'canEditMembers' => $can_edit_members,
                'canManageAdmins' => $can_manage_admins,
                'institutionId' => $institution_id,
            ),
        ));
    }
}
