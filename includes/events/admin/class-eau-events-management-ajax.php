<?php
/**
 * Events Management AJAX Handlers
 *
 * @package    EauSystem
 * @subpackage Events\Admin
 * @since      1.28.1
 */

namespace EauSystem\Events\Admin;

use EauSystem\Events\Config;

if (!defined('WPINC')) {
    die;
}

/**
 * Class Eau_Events_Management_Ajax
 *
 * Handlers AJAX para gerenciamento de eventos.
 *
 * @since 1.28.1
 */
class Eau_Events_Management_Ajax {

    /**
     * Registra os handlers AJAX
     *
     * @since  1.28.1
     * @return void
     */
    public static function register_handlers() {
        add_action('wp_ajax_eau_get_events', array(__CLASS__, 'get_events'));
        add_action('wp_ajax_eau_get_event', array(__CLASS__, 'get_event'));
        add_action('wp_ajax_eau_create_event', array(__CLASS__, 'create_event'));
        add_action('wp_ajax_eau_update_event', array(__CLASS__, 'update_event'));
        add_action('wp_ajax_eau_delete_event', array(__CLASS__, 'delete_event'));
        add_action('wp_ajax_eau_duplicate_event', array(__CLASS__, 'duplicate_event'));
        add_action('wp_ajax_eau_toggle_event_status', array(__CLASS__, 'toggle_event_status'));
        add_action('wp_ajax_eau_get_event_registrations', array(__CLASS__, 'get_event_registrations'));
        add_action('wp_ajax_eau_update_registration_status', array(__CLASS__, 'update_registration_status'));
        add_action('wp_ajax_eau_export_event_registrations', array(__CLASS__, 'export_event_registrations'));
        add_action('wp_ajax_eau_upload_material_file', array(__CLASS__, 'upload_material_file'));
    }

    /**
     * AJAX: Lista eventos com filtros e paginação
     *
     * @since  1.28.1
     * @return void
     */
    public static function get_events() {
        check_ajax_referer('eau_events_management_nonce', 'nonce');

        // Verifica permissão
        if (!self::can_manage_events()) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        // Parâmetros
        $page = isset($_POST['page']) ? absint($_POST['page']) : 1;
        $per_page = isset($_POST['per_page']) ? absint($_POST['per_page']) : 20;
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
        $date_filter = isset($_POST['date_filter']) ? sanitize_text_field($_POST['date_filter']) : '';
        $event_category = isset($_POST['event_category']) ? absint($_POST['event_category']) : 0;
        $cpd_category = isset($_POST['cpd_category']) ? absint($_POST['cpd_category']) : 0;
        $location = isset($_POST['location']) ? sanitize_text_field($_POST['location']) : '';
        $orderby = isset($_POST['orderby']) ? sanitize_text_field($_POST['orderby']) : 'id';
        $order = isset($_POST['order']) ? strtoupper(sanitize_text_field($_POST['order'])) : 'DESC';

        // Query args
        $args = array(
            'post_type' => Config\POST_TYPE,
            'posts_per_page' => $per_page,
            'paged' => $page,
            'post_status' => $status ? $status : array('publish', 'draft'),
        );

        // Search
        if (!empty($search)) {
            $args['s'] = $search;
        }

        // Meta query array
        $meta_query = array();

        // Date filter
        if (!empty($date_filter)) {
            $current_datetime = current_time('Y-m-d\TH:i');

            switch ($date_filter) {
                case 'upcoming':
                    $meta_query[] = array(
                        'key'     => 'evt_start_datetime',
                        'value'   => $current_datetime,
                        'compare' => '>=',
                        'type'    => 'DATETIME',
                    );
                    break;
                case 'past':
                    $meta_query[] = array(
                        'key'     => 'evt_start_datetime',
                        'value'   => $current_datetime,
                        'compare' => '<',
                        'type'    => 'DATETIME',
                    );
                    break;
                case 'this_week':
                    $week_start = date('Y-m-d\T00:00', strtotime('monday this week'));
                    $week_end = date('Y-m-d\T23:59', strtotime('sunday this week'));
                    $meta_query[] = array(
                        'key'     => 'evt_start_datetime',
                        'value'   => array($week_start, $week_end),
                        'compare' => 'BETWEEN',
                        'type'    => 'DATETIME',
                    );
                    break;
                case 'this_month':
                    $month_start = date('Y-m-01\T00:00');
                    $month_end = date('Y-m-t\T23:59');
                    $meta_query[] = array(
                        'key'     => 'evt_start_datetime',
                        'value'   => array($month_start, $month_end),
                        'compare' => 'BETWEEN',
                        'type'    => 'DATETIME',
                    );
                    break;
            }
        }

        // Event category filter
        if ($event_category > 0) {
            $meta_query[] = array(
                'key'     => 'evt_event_category',
                'value'   => $event_category,
                'compare' => '=',
            );
        }

        // CPD category filter
        if ($cpd_category > 0) {
            $meta_query[] = array(
                'key'     => 'evt_cpd_category',
                'value'   => $cpd_category,
                'compare' => '=',
            );
        }

        // Location (city) filter
        if (!empty($location)) {
            $meta_query[] = array(
                'key'     => 'evt_city',
                'value'   => $location,
                'compare' => '=',
            );
        }

        // Apply meta query
        if (!empty($meta_query)) {
            $args['meta_query'] = $meta_query;
        }

        // Order
        if ($orderby === 'id') {
            $args['orderby'] = 'ID';
            $args['order'] = $order;
        } elseif ($orderby === 'start_datetime') {
            $args['meta_key'] = 'evt_start_datetime';
            $args['orderby'] = 'meta_value';
            $args['order'] = $order;
        } elseif ($orderby === 'capacity') {
            $args['meta_key'] = 'evt_capacity';
            $args['orderby'] = 'meta_value_num';
            $args['order'] = $order;
        } else {
            $args['orderby'] = $orderby;
            $args['order'] = $order;
        }

        $query = new \WP_Query($args);

        // Formata resultados
        $rows = array();
        foreach ($query->posts as $post) {
            $rows[] = self::format_event_row($post);
        }

        wp_send_json_success(array(
            'rows' => $rows,
            'total' => $query->found_posts,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => $query->max_num_pages,
        ));
    }

    /**
     * AJAX: Get single event data
     *
     * @since  1.28.1
     * @return void
     */
    public static function get_event() {
        check_ajax_referer('eau_events_management_nonce', 'nonce');

        if (!self::can_manage_events()) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $event_id = isset($_POST['event_id']) ? absint($_POST['event_id']) : 0;

        if (!$event_id) {
            wp_send_json_error(array('message' => 'Invalid event ID'));
        }

        $post = get_post($event_id);
        if (!$post || $post->post_type !== Config\POST_TYPE) {
            wp_send_json_error(array('message' => 'Event not found'));
        }

        // Get all meta
        $meta = Config\get_event_meta($event_id);
        $meta['title'] = $post->post_title;
        $meta['status'] = $post->post_status;

        // Get image URL if image_id exists
        if (!empty($meta['image_id'])) {
            $image_url = wp_get_attachment_image_url($meta['image_id'], 'medium');
            $meta['image_url'] = $image_url ? $image_url : '';
        }

        wp_send_json_success(array('event' => $meta));
    }

    /**
     * AJAX: Create new event
     *
     * @since  1.28.1
     * @return void
     */
    public static function create_event() {
        check_ajax_referer('eau_events_management_nonce', 'nonce');

        if (!self::can_manage_events()) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        // Validate required fields
        $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
        $start_datetime = isset($_POST['start_datetime']) ? sanitize_text_field($_POST['start_datetime']) : '';
        $end_datetime = isset($_POST['end_datetime']) ? sanitize_text_field($_POST['end_datetime']) : '';

        if (empty($title)) {
            wp_send_json_error(array('message' => 'Event title is required'));
        }

        if (empty($start_datetime)) {
            wp_send_json_error(array('message' => 'Start date and time is required'));
        }

        if (empty($end_datetime)) {
            wp_send_json_error(array('message' => 'End date and time is required'));
        }

        // Check if should publish immediately
        $publish_immediately = isset($_POST['publish_immediately']) && $_POST['publish_immediately'] === '1';
        $post_status = $publish_immediately ? 'publish' : 'draft';

        // Create the post
        $event_id = wp_insert_post(array(
            'post_title' => $title,
            'post_type' => Config\POST_TYPE,
            'post_status' => $post_status,
        ));

        if (is_wp_error($event_id)) {
            wp_send_json_error(array('message' => 'Failed to create event'));
        }

        $prefix = Config\META_PREFIX;

        // Text fields
        $text_fields = array('short_description', 'start_datetime', 'end_datetime', 'timezone', 'event_type', 'venue_name', 'address', 'city', 'state', 'postal_code', 'country', 'visibility', 'materials');
        foreach ($text_fields as $f) {
            if (isset($_POST[$f]) && $_POST[$f] !== '') {
                update_post_meta($event_id, $prefix . $f, sanitize_text_field($_POST[$f]));
            }
        }

        // Numbers (allow empty values to delete meta)
        $numbers = array('image_id', 'capacity', 'member_price', 'non_member_price', 'cpd_points', 'cpd_category', 'event_category');
        foreach ($numbers as $f) {
            if (isset($_POST[$f])) {
                if ($_POST[$f] !== '' && $_POST[$f] !== null) {
                    update_post_meta($event_id, $prefix . $f, floatval($_POST[$f]));
                } else {
                    delete_post_meta($event_id, $prefix . $f);
                }
            }
        }

        // Checkboxes
        $checks = array('require_approval');
        foreach ($checks as $f) {
            update_post_meta($event_id, $prefix . $f, isset($_POST[$f]) && $_POST[$f] ? '1' : '');
        }

        // URLs
        if (isset($_POST['virtual_url']) && $_POST['virtual_url'] !== '') {
            update_post_meta($event_id, $prefix . 'virtual_url', esc_url_raw($_POST['virtual_url']));
        }
        if (isset($_POST['recording_url']) && $_POST['recording_url'] !== '') {
            update_post_meta($event_id, $prefix . 'recording_url', esc_url_raw($_POST['recording_url']));
        }

        // WYSIWYG
        if (isset($_POST['full_description']) && $_POST['full_description'] !== '') {
            update_post_meta($event_id, $prefix . 'full_description', wp_kses_post($_POST['full_description']));
        }

        $message = $publish_immediately
            ? 'Event created and published successfully'
            : 'Event created as draft successfully';

        wp_send_json_success(array(
            'message' => $message,
            'event_id' => $event_id,
            'status' => $post_status,
        ));
    }

    /**
     * AJAX: Update event
     *
     * @since  1.28.1
     * @return void
     */
    public static function update_event() {
        check_ajax_referer('eau_events_management_nonce', 'nonce');

        if (!self::can_manage_events()) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $event_id = isset($_POST['event_id']) ? absint($_POST['event_id']) : 0;

        if (!$event_id) {
            wp_send_json_error(array('message' => 'Invalid event ID'));
        }

        // Update post title
        if (isset($_POST['title'])) {
            wp_update_post(array(
                'ID' => $event_id,
                'post_title' => sanitize_text_field($_POST['title']),
            ));
        }

        $prefix = Config\META_PREFIX;

        // Text fields
        $text_fields = array('short_description', 'start_datetime', 'end_datetime', 'timezone', 'event_type', 'venue_name', 'address', 'city', 'state', 'postal_code', 'country', 'visibility', 'materials');
        foreach ($text_fields as $f) {
            if (isset($_POST[$f])) {
                update_post_meta($event_id, $prefix . $f, sanitize_text_field($_POST[$f]));
            }
        }

        // Numbers
        $numbers = array('image_id', 'capacity', 'member_price', 'non_member_price', 'cpd_points', 'cpd_category', 'event_category');
        foreach ($numbers as $f) {
            if (isset($_POST[$f])) {
                $val = $_POST[$f];
                if ($val === '') {
                    delete_post_meta($event_id, $prefix . $f);
                } else {
                    update_post_meta($event_id, $prefix . $f, floatval($val));
                }
            }
        }

        // Checkboxes
        $checks = array('require_approval');
        foreach ($checks as $f) {
            update_post_meta($event_id, $prefix . $f, isset($_POST[$f]) && $_POST[$f] ? '1' : '');
        }

        // URLs
        if (isset($_POST['virtual_url'])) {
            update_post_meta($event_id, $prefix . 'virtual_url', esc_url_raw($_POST['virtual_url']));
        }
        if (isset($_POST['recording_url'])) {
            update_post_meta($event_id, $prefix . 'recording_url', esc_url_raw($_POST['recording_url']));
        }

        // WYSIWYG
        if (isset($_POST['full_description'])) {
            update_post_meta($event_id, $prefix . 'full_description', wp_kses_post($_POST['full_description']));
        }

        wp_send_json_success(array('message' => 'Event updated successfully'));
    }

    /**
     * AJAX: Delete evento
     *
     * @since  1.28.1
     * @return void
     */
    public static function delete_event() {
        check_ajax_referer('eau_events_management_nonce', 'nonce');

        if (!self::can_manage_events()) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $event_id = isset($_POST['event_id']) ? absint($_POST['event_id']) : 0;

        if (!$event_id) {
            wp_send_json_error(array('message' => 'Invalid event ID'));
        }

        $result = wp_trash_post($event_id);

        if ($result) {
            wp_send_json_success(array('message' => 'Event deleted successfully'));
        } else {
            wp_send_json_error(array('message' => 'Failed to delete event'));
        }
    }

    /**
     * AJAX: Duplicate evento
     *
     * @since  1.28.1
     * @return void
     */
    public static function duplicate_event() {
        check_ajax_referer('eau_events_management_nonce', 'nonce');

        if (!self::can_manage_events()) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $event_id = isset($_POST['event_id']) ? absint($_POST['event_id']) : 0;

        if (!$event_id) {
            wp_send_json_error(array('message' => 'Invalid event ID'));
        }

        $post = get_post($event_id);
        if (!$post) {
            wp_send_json_error(array('message' => 'Event not found'));
        }

        // Create duplicate
        $new_post_id = wp_insert_post(array(
            'post_title' => $post->post_title . ' (Copy)',
            'post_type' => Config\POST_TYPE,
            'post_status' => 'draft',
        ));

        if (is_wp_error($new_post_id)) {
            wp_send_json_error(array('message' => 'Failed to duplicate event'));
        }

        // Copy all meta
        $meta = get_post_meta($event_id);
        foreach ($meta as $key => $values) {
            if (strpos($key, 'evt_') === 0) {
                foreach ($values as $value) {
                    add_post_meta($new_post_id, $key, maybe_unserialize($value));
                }
            }
        }

        wp_send_json_success(array('message' => 'Event duplicated successfully', 'new_id' => $new_post_id));
    }

    /**
     * AJAX: Toggle event status (publish/draft)
     *
     * @since  1.28.1
     * @return void
     */
    public static function toggle_event_status() {
        check_ajax_referer('eau_events_management_nonce', 'nonce');

        if (!self::can_manage_events()) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $event_id = isset($_POST['event_id']) ? absint($_POST['event_id']) : 0;

        if (!$event_id) {
            wp_send_json_error(array('message' => 'Invalid event ID'));
        }

        $post = get_post($event_id);
        if (!$post) {
            wp_send_json_error(array('message' => 'Event not found'));
        }

        $new_status = $post->post_status === 'publish' ? 'draft' : 'publish';

        wp_update_post(array(
            'ID' => $event_id,
            'post_status' => $new_status,
        ));

        $status_label = $new_status === 'publish' ? 'published' : 'unpublished';
        wp_send_json_success(array('message' => 'Event ' . $status_label . ' successfully', 'new_status' => $new_status));
    }

    /**
     * Formata dados do evento para a tabela
     *
     * @since  1.28.1
     * @param  \WP_Post $post Post do evento
     * @return array Dados formatados
     */
    private static function format_event_row($post) {
        $post_id = $post->ID;

        // Meta
        $start_datetime = get_post_meta($post_id, 'evt_start_datetime', true);
        $event_type = get_post_meta($post_id, 'evt_event_type', true);
        $venue_name = get_post_meta($post_id, 'evt_venue_name', true);
        $capacity = get_post_meta($post_id, 'evt_capacity', true);

        // Format date
        $date_formatted = '';
        $time_formatted = '';
        if ($start_datetime) {
            $date_obj = \DateTime::createFromFormat('Y-m-d\TH:i', $start_datetime);
            if (!$date_obj) {
                $date_obj = \DateTime::createFromFormat('Y-m-d H:i:s', $start_datetime);
            }
            if ($date_obj) {
                $date_formatted = $date_obj->format('M j, Y');
                $time_formatted = $date_obj->format('g:i A');
            }
        }

        // Location
        $location = 'Location TBA';
        if ($event_type === 'virtual') {
            $location = 'Online';
        } elseif ($venue_name) {
            $location = $venue_name;
        }

        // Status
        $status_label = $post->post_status === 'publish' ? 'Published' : 'Draft';
        $status_class = $post->post_status === 'publish' ? 'success' : 'warning';

        return array(
            'id' => $post_id,
            'title' => $post->post_title,
            'slug' => $post->post_name,
            'date' => $date_formatted,
            'time' => $time_formatted,
            'location' => $location,
            'capacity' => $capacity ? $capacity : '—',
            'status' => $status_label,
            'status_class' => $status_class,
            'edit_url' => admin_url('post.php?post=' . $post_id . '&action=edit'),
            'view_url' => get_permalink($post_id),
        );
    }

    /**
     * AJAX: Get registrations for a specific event
     *
     * @since  1.29.3
     * @return void
     */
    public static function get_event_registrations() {
        check_ajax_referer('eau_events_management_nonce', 'nonce');

        if (!self::can_manage_events()) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $event_id = isset($_POST['event_id']) ? absint($_POST['event_id']) : 0;

        if (!$event_id) {
            wp_send_json_error(array('message' => 'Invalid event ID'));
        }

        // Parameters
        $page = isset($_POST['page']) ? absint($_POST['page']) : 1;
        $per_page = isset($_POST['per_page']) ? absint($_POST['per_page']) : 20;
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
        $orderby = isset($_POST['orderby']) ? sanitize_text_field($_POST['orderby']) : 'registration_date';
        $order = isset($_POST['order']) ? strtoupper(sanitize_text_field($_POST['order'])) : 'DESC';

        // Query args
        $args = array(
            'post_type'      => 'eau_event_reg',
            'posts_per_page' => $per_page,
            'paged'          => $page,
            'post_status'    => 'publish',
            'meta_query'     => array(
                array(
                    'key'   => 'reg_event_id',
                    'value' => $event_id,
                    'compare' => '=',
                ),
            ),
        );

        // Add status filter
        if (!empty($status)) {
            $args['meta_query'][] = array(
                'key'   => 'reg_status',
                'value' => $status,
                'compare' => '=',
            );
        }

        // Add search
        if (!empty($search)) {
            $args['meta_query'][] = array(
                'relation' => 'OR',
                array(
                    'key'     => 'reg_attendee_name',
                    'value'   => $search,
                    'compare' => 'LIKE',
                ),
                array(
                    'key'     => 'reg_attendee_email',
                    'value'   => $search,
                    'compare' => 'LIKE',
                ),
            );
        }

        // Order
        if ($orderby === 'registration_date') {
            $args['meta_key'] = 'reg_registration_date';
            $args['orderby'] = 'meta_value';
            $args['order'] = $order;
        } elseif ($orderby === 'attendee_name') {
            $args['meta_key'] = 'reg_attendee_name';
            $args['orderby'] = 'meta_value';
            $args['order'] = $order;
        } else {
            $args['orderby'] = 'date';
            $args['order'] = $order;
        }

        $query = new \WP_Query($args);

        // Format results
        $rows = array();
        foreach ($query->posts as $post) {
            $rows[] = self::format_registration_row($post);
        }

        // Get stats
        $stats = self::get_registration_stats($event_id);

        // Get event title
        $event_title = get_the_title($event_id);

        wp_send_json_success(array(
            'rows'        => $rows,
            'total'       => $query->found_posts,
            'page'        => $page,
            'per_page'    => $per_page,
            'total_pages' => $query->max_num_pages,
            'stats'       => $stats,
            'event_title' => $event_title,
        ));
    }

    /**
     * AJAX: Update registration status
     *
     * @since  1.29.3
     * @return void
     */
    public static function update_registration_status() {
        check_ajax_referer('eau_events_management_nonce', 'nonce');

        if (!self::can_manage_events()) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $registration_id = isset($_POST['registration_id']) ? absint($_POST['registration_id']) : 0;
        $new_status = isset($_POST['status']) ? sanitize_key($_POST['status']) : '';

        if (!$registration_id) {
            wp_send_json_error(array('message' => 'Invalid registration ID'));
        }

        $valid_statuses = array('paid', 'pending', 'failed', 'refunded');
        if (!in_array($new_status, $valid_statuses)) {
            wp_send_json_error(array('message' => 'Invalid status'));
        }

        update_post_meta($registration_id, 'reg_status', $new_status);

        wp_send_json_success(array(
            'message' => 'Payment status updated successfully',
            'new_status' => $new_status,
        ));
    }

    /**
     * Format registration row for table
     *
     * @since  1.29.3
     * @param  \WP_Post $post Registration post
     * @return array Formatted data
     */
    private static function format_registration_row($post) {
        $post_id = $post->ID;

        $attendee_name = get_post_meta($post_id, 'reg_attendee_name', true);
        $attendee_email = get_post_meta($post_id, 'reg_attendee_email', true);
        $registration_date = get_post_meta($post_id, 'reg_registration_date', true);
        $status = get_post_meta($post_id, 'reg_status', true) ?: 'pending';
        $attended = get_post_meta($post_id, 'reg_attended', true);
        $activity_created = get_post_meta($post_id, 'reg_activity_created', true);

        // Format date
        $date_formatted = '';
        if ($registration_date) {
            $date_obj = \DateTime::createFromFormat('Y-m-d\TH:i', $registration_date);
            if (!$date_obj) {
                $date_obj = \DateTime::createFromFormat('Y-m-d H:i:s', $registration_date);
            }
            if ($date_obj) {
                $date_formatted = $date_obj->format('M j, Y g:i A');
            }
        }

        // Status class
        $status_classes = array(
            'paid'     => 'success',
            'pending'  => 'warning',
            'failed'   => 'danger',
            'refunded' => 'refunded',
        );
        $status_class = isset($status_classes[$status]) ? $status_classes[$status] : 'secondary';

        // Status label
        $status_labels = array(
            'paid'     => 'Paid',
            'pending'  => 'Pending',
            'failed'   => 'Failed',
            'refunded' => 'Refunded',
        );
        $status_label = isset($status_labels[$status]) ? $status_labels[$status] : ucfirst($status);

        return array(
            'id'               => $post_id,
            'attendee_name'    => $attendee_name ?: '—',
            'attendee_email'   => $attendee_email ?: '—',
            'registration_date' => $date_formatted ?: '—',
            'status'           => $status,
            'status_label'     => $status_label,
            'status_class'     => $status_class,
            'attended'         => $attended === '1',
            'activity_created' => $activity_created === '1',
        );
    }

    /**
     * Get registration stats for an event
     *
     * @since  1.29.3
     * @param  int $event_id Event ID
     * @return array Stats
     */
    private static function get_registration_stats($event_id) {
        global $wpdb;

        $stats = array(
            'total'    => 0,
            'paid'     => 0,
            'pending'  => 0,
            'failed'   => 0,
            'refunded' => 0,
        );

        // Get all registrations for this event
        $registrations = get_posts(array(
            'post_type'      => 'eau_event_reg',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'fields'         => 'ids',
            'meta_query'     => array(
                array(
                    'key'   => 'reg_event_id',
                    'value' => $event_id,
                    'compare' => '=',
                ),
            ),
        ));

        $stats['total'] = count($registrations);

        foreach ($registrations as $reg_id) {
            $status = get_post_meta($reg_id, 'reg_status', true) ?: 'pending';
            if (isset($stats[$status])) {
                $stats[$status]++;
            }
        }

        return $stats;
    }

    /**
     * AJAX: Export event registrations as CSV
     *
     * @since  1.29.3
     * @return void
     */
    public static function export_event_registrations() {
        check_ajax_referer('eau_events_management_nonce', 'nonce');

        if (!self::can_manage_events()) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $event_id = isset($_POST['event_id']) ? absint($_POST['event_id']) : 0;

        if (!$event_id) {
            wp_send_json_error(array('message' => 'Invalid event ID'));
        }

        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';

        // Query args
        $args = array(
            'post_type'      => 'eau_event_reg',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_query'     => array(
                array(
                    'key'   => 'reg_event_id',
                    'value' => $event_id,
                    'compare' => '=',
                ),
            ),
        );

        // Add status filter
        if (!empty($status)) {
            $args['meta_query'][] = array(
                'key'   => 'reg_status',
                'value' => $status,
                'compare' => '=',
            );
        }

        // Add search
        if (!empty($search)) {
            $args['meta_query'][] = array(
                'relation' => 'OR',
                array(
                    'key'     => 'reg_attendee_name',
                    'value'   => $search,
                    'compare' => 'LIKE',
                ),
                array(
                    'key'     => 'reg_attendee_email',
                    'value'   => $search,
                    'compare' => 'LIKE',
                ),
            );
        }

        $query = new \WP_Query($args);

        // Build CSV
        $csv_lines = array();
        $csv_lines[] = '"Name","Email","Registration Date","Status"';

        foreach ($query->posts as $post) {
            $name = get_post_meta($post->ID, 'reg_attendee_name', true);
            $email = get_post_meta($post->ID, 'reg_attendee_email', true);
            $date = get_post_meta($post->ID, 'reg_registration_date', true);
            $reg_status = get_post_meta($post->ID, 'reg_status', true) ?: 'pending';

            // Format date
            $date_formatted = '';
            if ($date) {
                $date_obj = \DateTime::createFromFormat('Y-m-d\TH:i', $date);
                if (!$date_obj) {
                    $date_obj = \DateTime::createFromFormat('Y-m-d H:i:s', $date);
                }
                if ($date_obj) {
                    $date_formatted = $date_obj->format('Y-m-d H:i');
                }
            }

            $csv_lines[] = sprintf(
                '"%s","%s","%s","%s"',
                str_replace('"', '""', $name),
                str_replace('"', '""', $email),
                $date_formatted,
                ucfirst($reg_status)
            );
        }

        $csv = implode("\n", $csv_lines);
        $event_title = get_the_title($event_id);
        $filename = sanitize_file_name($event_title . '-registrations-' . date('Y-m-d')) . '.csv';

        wp_send_json_success(array(
            'csv' => $csv,
            'filename' => $filename,
        ));
    }

    /**
     * AJAX: Upload de arquivo de material suplementar
     *
     * @since  1.68.5
     * @return void
     */
    public static function upload_material_file() {
        check_ajax_referer('eau_events_management_nonce', 'nonce');

        // Verifica permissão
        if (!self::can_manage_events()) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        // Verifica se arquivo foi enviado
        if (empty($_FILES['file'])) {
            wp_send_json_error(array('message' => 'No file uploaded'));
        }

        $file = $_FILES['file'];

        // Verifica erro de upload
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error_messages = array(
                UPLOAD_ERR_INI_SIZE => 'File is too large',
                UPLOAD_ERR_FORM_SIZE => 'File is too large',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            );
            $message = isset($error_messages[$file['error']]) ? $error_messages[$file['error']] : 'Upload error';
            wp_send_json_error(array('message' => $message));
        }

        // Verifica tamanho (10MB máximo)
        $max_size = 10 * 1024 * 1024;
        if ($file['size'] > $max_size) {
            wp_send_json_error(array('message' => 'File is too large. Maximum size is 10MB.'));
        }

        // Verifica extensão permitida
        $allowed_extensions = array('pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip', 'rar', 'jpg', 'jpeg', 'png', 'gif');
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed_extensions)) {
            wp_send_json_error(array('message' => 'File type not allowed. Allowed types: ' . implode(', ', $allowed_extensions)));
        }

        // Faz upload para a Media Library
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        // Usa wp_handle_upload diretamente
        $upload_overrides = array(
            'test_form' => false,
            'mimes' => array(
                'pdf' => 'application/pdf',
                'doc' => 'application/msword',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'xls' => 'application/vnd.ms-excel',
                'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'ppt' => 'application/vnd.ms-powerpoint',
                'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'zip' => 'application/zip',
                'rar' => 'application/x-rar-compressed',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
            ),
        );

        $movefile = wp_handle_upload($file, $upload_overrides);

        if (isset($movefile['error'])) {
            wp_send_json_error(array('message' => $movefile['error']));
        }

        // Cria attachment na Media Library
        $attachment = array(
            'guid' => $movefile['url'],
            'post_mime_type' => $movefile['type'],
            'post_title' => preg_replace('/\.[^.]+$/', '', basename($file['name'])),
            'post_content' => '',
            'post_status' => 'inherit',
        );

        $attach_id = wp_insert_attachment($attachment, $movefile['file']);

        if (is_wp_error($attach_id)) {
            wp_send_json_error(array('message' => 'Failed to save attachment'));
        }

        // Gera metadata para o attachment
        $attach_data = wp_generate_attachment_metadata($attach_id, $movefile['file']);
        wp_update_attachment_metadata($attach_id, $attach_data);

        wp_send_json_success(array(
            'url' => $movefile['url'],
            'filename' => basename($file['name']),
            'attachment_id' => $attach_id,
        ));
    }

    /**
     * Verifica se usuário pode gerenciar eventos
     *
     * @since  1.28.1
     * @return bool
     */
    private static function can_manage_events() {
        if (!is_user_logged_in()) {
            return false;
        }

        $user_id = get_current_user_id();
        $mem_type = get_user_meta($user_id, 'mem_type', true);

        return in_array($mem_type, array('superAdmin', 'Admin'));
    }
}
