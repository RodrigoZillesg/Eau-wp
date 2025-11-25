<?php
/**
 * Event Registrations AJAX Handlers
 *
 * @package    EauSystem
 * @subpackage Events\Registrations\Admin
 * @since      1.29.0
 */

namespace EauSystem\EventRegistrations\Admin;

use EauSystem\EventRegistrations\Config;

if (!defined('WPINC')) {
    die;
}

/**
 * Class Eau_Event_Registrations_Ajax
 *
 * Handlers AJAX para a página de registrations.
 *
 * @since 1.29.0
 */
class Eau_Event_Registrations_Ajax {

    /**
     * Registra handlers AJAX
     *
     * @since  1.29.0
     * @return void
     */
    public static function register_handlers() {
        add_action('wp_ajax_eau_get_registrations', array(__CLASS__, 'get_registrations'));
        add_action('wp_ajax_eau_update_registration_status', array(__CLASS__, 'update_status'));
        add_action('wp_ajax_eau_delete_registration', array(__CLASS__, 'delete_registration'));
    }

    /**
     * Retorna lista de registrations
     *
     * @since  1.29.0
     * @return void
     */
    public static function get_registrations() {
        check_ajax_referer('eau_event_reg_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $page = isset($_POST['page']) ? absint($_POST['page']) : 1;
        $per_page = isset($_POST['per_page']) ? absint($_POST['per_page']) : 20;
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $status = isset($_POST['status']) ? sanitize_key($_POST['status']) : '';
        $event_id = isset($_POST['event_id']) ? absint($_POST['event_id']) : 0;
        $orderby = isset($_POST['orderby']) ? sanitize_key($_POST['orderby']) : 'date';
        $order = isset($_POST['order']) ? strtoupper(sanitize_key($_POST['order'])) : 'DESC';

        $prefix = Config\META_PREFIX;

        // Build query args
        $args = array(
            'post_type'      => Config\POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => $per_page,
            'paged'          => $page,
            'orderby'        => $orderby === 'date' ? 'date' : 'meta_value',
            'order'          => $order,
        );

        // Meta query for filters
        $meta_query = array('relation' => 'AND');

        if (!empty($status)) {
            $meta_query[] = array(
                'key'   => $prefix . 'status',
                'value' => $status,
            );
        }

        if (!empty($event_id)) {
            $meta_query[] = array(
                'key'   => $prefix . 'event_id',
                'value' => $event_id,
            );
        }

        // Search
        if (!empty($search)) {
            $meta_query[] = array(
                'relation' => 'OR',
                array(
                    'key'     => $prefix . 'attendee_name',
                    'value'   => $search,
                    'compare' => 'LIKE',
                ),
                array(
                    'key'     => $prefix . 'attendee_email',
                    'value'   => $search,
                    'compare' => 'LIKE',
                ),
            );
        }

        if (count($meta_query) > 1) {
            $args['meta_query'] = $meta_query;
        }

        $query = new \WP_Query($args);
        $registrations = array();

        foreach ($query->posts as $post) {
            $event_id = get_post_meta($post->ID, $prefix . 'event_id', true);
            $event = $event_id ? get_post($event_id) : null;

            $registrations[] = array(
                'id'                => $post->ID,
                'attendee_name'     => get_post_meta($post->ID, $prefix . 'attendee_name', true),
                'attendee_email'    => get_post_meta($post->ID, $prefix . 'attendee_email', true),
                'event_id'          => $event_id,
                'event_name'        => $event ? $event->post_title : __('(No event)', 'eau-system'),
                'registration_date' => get_post_meta($post->ID, $prefix . 'registration_date', true),
                'member_type'       => get_post_meta($post->ID, $prefix . 'member_type', true),
                'status'            => get_post_meta($post->ID, $prefix . 'status', true) ?: 'pending',
                'edit_url'          => get_edit_post_link($post->ID, 'raw'),
            );
        }

        wp_send_json_success(array(
            'registrations' => $registrations,
            'total'         => $query->found_posts,
            'total_pages'   => $query->max_num_pages,
            'current_page'  => $page,
            'per_page'      => $per_page,
        ));
    }

    /**
     * Atualiza status de uma registration
     *
     * @since  1.29.0
     * @return void
     */
    public static function update_status() {
        check_ajax_referer('eau_event_reg_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        $status = isset($_POST['status']) ? sanitize_key($_POST['status']) : '';

        if (!$post_id || !$status) {
            wp_send_json_error(array('message' => 'Invalid data'));
        }

        $valid_statuses = array_keys(Config\get_status_options());
        if (!in_array($status, $valid_statuses)) {
            wp_send_json_error(array('message' => 'Invalid status'));
        }

        update_post_meta($post_id, Config\META_PREFIX . 'status', $status);

        wp_send_json_success(array('message' => 'Status updated'));
    }

    /**
     * Deleta uma registration
     *
     * @since  1.29.0
     * @return void
     */
    public static function delete_registration() {
        check_ajax_referer('eau_event_reg_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;

        if (!$post_id) {
            wp_send_json_error(array('message' => 'Invalid ID'));
        }

        $post = get_post($post_id);
        if (!$post || $post->post_type !== Config\POST_TYPE) {
            wp_send_json_error(array('message' => 'Invalid registration'));
        }

        $result = wp_delete_post($post_id, true);

        if ($result) {
            wp_send_json_success(array('message' => 'Registration deleted'));
        } else {
            wp_send_json_error(array('message' => 'Error deleting registration'));
        }
    }
}
