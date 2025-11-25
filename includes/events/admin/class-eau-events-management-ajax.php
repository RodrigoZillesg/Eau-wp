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
        $orderby = isset($_POST['orderby']) ? sanitize_text_field($_POST['orderby']) : 'start_datetime';
        $order = isset($_POST['order']) ? strtoupper(sanitize_text_field($_POST['order'])) : 'ASC';

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

        // Order
        if ($orderby === 'start_datetime') {
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

        // Create the post
        $event_id = wp_insert_post(array(
            'post_title' => $title,
            'post_type' => Config\POST_TYPE,
            'post_status' => 'draft',
        ));

        if (is_wp_error($event_id)) {
            wp_send_json_error(array('message' => 'Failed to create event'));
        }

        $prefix = Config\META_PREFIX;

        // Text fields
        $text_fields = array('short_description', 'start_datetime', 'end_datetime', 'timezone', 'event_type', 'venue_name', 'address', 'city', 'state', 'postal_code', 'country', 'early_bird_end_date', 'visibility');
        foreach ($text_fields as $f) {
            if (isset($_POST[$f]) && $_POST[$f] !== '') {
                update_post_meta($event_id, $prefix . $f, sanitize_text_field($_POST[$f]));
            }
        }

        // Numbers
        $numbers = array('image_id', 'capacity', 'member_price', 'non_member_price', 'early_bird_price', 'max_guests', 'cpd_points', 'cpd_category');
        foreach ($numbers as $f) {
            if (isset($_POST[$f]) && $_POST[$f] !== '') {
                update_post_meta($event_id, $prefix . $f, floatval($_POST[$f]));
            }
        }

        // Checkboxes
        $checks = array('allow_guests', 'require_approval', 'members_only');
        foreach ($checks as $f) {
            update_post_meta($event_id, $prefix . $f, isset($_POST[$f]) && $_POST[$f] ? '1' : '');
        }

        // URL
        if (isset($_POST['virtual_url']) && $_POST['virtual_url'] !== '') {
            update_post_meta($event_id, $prefix . 'virtual_url', esc_url_raw($_POST['virtual_url']));
        }

        // WYSIWYG
        if (isset($_POST['full_description']) && $_POST['full_description'] !== '') {
            update_post_meta($event_id, $prefix . 'full_description', wp_kses_post($_POST['full_description']));
        }

        wp_send_json_success(array(
            'message' => 'Event created successfully',
            'event_id' => $event_id,
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
        $text_fields = array('short_description', 'start_datetime', 'end_datetime', 'timezone', 'event_type', 'venue_name', 'address', 'city', 'state', 'postal_code', 'country', 'early_bird_end_date', 'visibility');
        foreach ($text_fields as $f) {
            if (isset($_POST[$f])) {
                update_post_meta($event_id, $prefix . $f, sanitize_text_field($_POST[$f]));
            }
        }

        // Numbers
        $numbers = array('image_id', 'capacity', 'member_price', 'non_member_price', 'early_bird_price', 'max_guests', 'cpd_points', 'cpd_category');
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
        $checks = array('allow_guests', 'require_approval', 'members_only');
        foreach ($checks as $f) {
            update_post_meta($event_id, $prefix . $f, isset($_POST[$f]) && $_POST[$f] ? '1' : '');
        }

        // URL
        if (isset($_POST['virtual_url'])) {
            update_post_meta($event_id, $prefix . 'virtual_url', esc_url_raw($_POST['virtual_url']));
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
