<?php
/**
 * Event Registrations - Metabox
 *
 * Metabox para edição de registros de eventos no admin.
 *
 * @package    EauSystem
 * @subpackage EventRegistrations\Admin
 * @since      1.29.2
 */

namespace EauSystem\EventRegistrations\Admin;

use EauSystem\EventRegistrations\Config;

if (!defined('WPINC')) {
    die;
}

/**
 * Class Eau_Event_Registrations_Metabox
 *
 * Gerencia metaboxes para o CPT Event Registration.
 *
 * @since 1.29.2
 */
class Eau_Event_Registrations_Metabox {

    /**
     * Instância singleton
     *
     * @var Eau_Event_Registrations_Metabox|null
     */
    private static $instance = null;

    /**
     * Retorna a instância singleton
     *
     * @since  1.29.2
     * @return Eau_Event_Registrations_Metabox
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     *
     * @since 1.29.2
     */
    private function __construct() {
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post_' . Config\POST_TYPE, array($this, 'save_meta'), 10, 2);
    }

    /**
     * Adiciona metaboxes
     *
     * @since  1.29.2
     * @return void
     */
    public function add_meta_boxes() {
        add_meta_box(
            'eau_event_registration_details',
            __('Registration Details', 'eau-system'),
            array($this, 'render_metabox'),
            Config\POST_TYPE,
            'normal',
            'high'
        );
    }

    /**
     * Renderiza o metabox
     *
     * @since  1.29.2
     * @param  \WP_Post $post Post atual
     * @return void
     */
    public function render_metabox($post) {
        wp_nonce_field('eau_event_reg_metabox', 'eau_event_reg_nonce');

        $prefix = Config\META_PREFIX;

        // Get current values
        $attendee_name = get_post_meta($post->ID, $prefix . 'attendee_name', true);
        $attendee_email = get_post_meta($post->ID, $prefix . 'attendee_email', true);
        $event_id = get_post_meta($post->ID, $prefix . 'event_id', true);
        $registration_date = get_post_meta($post->ID, $prefix . 'registration_date', true);
        $member_type = get_post_meta($post->ID, $prefix . 'member_type', true);
        $status = get_post_meta($post->ID, $prefix . 'status', true) ?: Config\DEFAULT_STATUS;

        // Get events for dropdown
        $events = get_posts(array(
            'post_type'      => 'eau_event',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ));

        // Get options
        $status_options = Config\get_status_options();
        $member_type_options = Config\get_member_type_options();
        ?>
        <style>
            .eau-metabox-grid {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 20px;
            }
            .eau-metabox-field {
                display: flex;
                flex-direction: column;
                gap: 5px;
            }
            .eau-metabox-field.full-width {
                grid-column: 1 / -1;
            }
            .eau-metabox-field label {
                font-weight: 600;
                color: #1e1e1e;
            }
            .eau-metabox-field input,
            .eau-metabox-field select {
                padding: 8px 12px;
                border: 1px solid #8c8f94;
                border-radius: 4px;
                font-size: 14px;
            }
            .eau-metabox-field input:focus,
            .eau-metabox-field select:focus {
                border-color: #2271b1;
                box-shadow: 0 0 0 1px #2271b1;
                outline: none;
            }
            .eau-metabox-field .description {
                font-size: 12px;
                color: #646970;
                margin-top: 2px;
            }
            .eau-metabox-field select {
                max-width: 100%;
            }
        </style>
        <div class="eau-metabox-grid">
            <!-- Attendee Name -->
            <div class="eau-metabox-field">
                <label for="eau_attendee_name"><?php esc_html_e('Attendee Name', 'eau-system'); ?> <span style="color: #dc2626;">*</span></label>
                <input type="text" id="eau_attendee_name" name="eau_attendee_name" value="<?php echo esc_attr($attendee_name); ?>" required>
            </div>

            <!-- Attendee Email -->
            <div class="eau-metabox-field">
                <label for="eau_attendee_email"><?php esc_html_e('Attendee Email', 'eau-system'); ?> <span style="color: #dc2626;">*</span></label>
                <input type="email" id="eau_attendee_email" name="eau_attendee_email" value="<?php echo esc_attr($attendee_email); ?>" required>
            </div>

            <!-- Event -->
            <div class="eau-metabox-field">
                <label for="eau_event_id"><?php esc_html_e('Event', 'eau-system'); ?> <span style="color: #dc2626;">*</span></label>
                <select id="eau_event_id" name="eau_event_id" required>
                    <option value=""><?php esc_html_e('Select an event...', 'eau-system'); ?></option>
                    <?php foreach ($events as $event) : ?>
                        <option value="<?php echo esc_attr($event->ID); ?>" <?php selected($event_id, $event->ID); ?>>
                            <?php echo esc_html($event->post_title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Registration Date -->
            <div class="eau-metabox-field">
                <label for="eau_registration_date"><?php esc_html_e('Registration Date', 'eau-system'); ?></label>
                <input type="datetime-local" id="eau_registration_date" name="eau_registration_date" value="<?php echo esc_attr($registration_date); ?>">
                <span class="description"><?php esc_html_e('Leave empty to use current date/time on save.', 'eau-system'); ?></span>
            </div>

            <!-- Member Type -->
            <div class="eau-metabox-field">
                <label for="eau_member_type"><?php esc_html_e('Member Type', 'eau-system'); ?></label>
                <select id="eau_member_type" name="eau_member_type">
                    <option value=""><?php esc_html_e('Select...', 'eau-system'); ?></option>
                    <?php foreach ($member_type_options as $key => $label) : ?>
                        <option value="<?php echo esc_attr($key); ?>" <?php selected($member_type, $key); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Status -->
            <div class="eau-metabox-field">
                <label for="eau_status"><?php esc_html_e('Status', 'eau-system'); ?></label>
                <select id="eau_status" name="eau_status">
                    <?php foreach ($status_options as $key => $label) : ?>
                        <option value="<?php echo esc_attr($key); ?>" <?php selected($status, $key); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <?php
    }

    /**
     * Salva os meta dados
     *
     * @since  1.29.2
     * @param  int      $post_id ID do post
     * @param  \WP_Post $post    Objeto do post
     * @return void
     */
    public function save_meta($post_id, $post) {
        // Verify nonce
        if (!isset($_POST['eau_event_reg_nonce']) || !wp_verify_nonce($_POST['eau_event_reg_nonce'], 'eau_event_reg_metabox')) {
            return;
        }

        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $prefix = Config\META_PREFIX;

        // Save attendee name
        if (isset($_POST['eau_attendee_name'])) {
            update_post_meta($post_id, $prefix . 'attendee_name', sanitize_text_field($_POST['eau_attendee_name']));
        }

        // Save attendee email
        if (isset($_POST['eau_attendee_email'])) {
            update_post_meta($post_id, $prefix . 'attendee_email', sanitize_email($_POST['eau_attendee_email']));
        }

        // Save event ID
        if (isset($_POST['eau_event_id'])) {
            update_post_meta($post_id, $prefix . 'event_id', absint($_POST['eau_event_id']));
        }

        // Save registration date (or set current if empty)
        if (!empty($_POST['eau_registration_date'])) {
            update_post_meta($post_id, $prefix . 'registration_date', sanitize_text_field($_POST['eau_registration_date']));
        } else {
            $current_date = get_post_meta($post_id, $prefix . 'registration_date', true);
            if (empty($current_date)) {
                update_post_meta($post_id, $prefix . 'registration_date', current_time('Y-m-d\TH:i'));
            }
        }

        // Save member type
        if (isset($_POST['eau_member_type'])) {
            update_post_meta($post_id, $prefix . 'member_type', sanitize_key($_POST['eau_member_type']));
        }

        // Save status
        if (isset($_POST['eau_status'])) {
            $valid_statuses = array_keys(Config\get_status_options());
            $status = sanitize_key($_POST['eau_status']);
            if (in_array($status, $valid_statuses)) {
                update_post_meta($post_id, $prefix . 'status', $status);
            }
        }

        // Update post title based on attendee name and event
        $attendee_name = get_post_meta($post_id, $prefix . 'attendee_name', true);
        $event_id = get_post_meta($post_id, $prefix . 'event_id', true);
        $event_title = $event_id ? get_the_title($event_id) : '';

        if ($attendee_name) {
            $new_title = $attendee_name;
            if ($event_title) {
                $new_title .= ' - ' . $event_title;
            }

            // Remove action to prevent infinite loop
            remove_action('save_post_' . Config\POST_TYPE, array($this, 'save_meta'), 10);

            wp_update_post(array(
                'ID'         => $post_id,
                'post_title' => $new_title,
            ));

            // Re-add action
            add_action('save_post_' . Config\POST_TYPE, array($this, 'save_meta'), 10, 2);
        }
    }
}
