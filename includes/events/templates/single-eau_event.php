<?php
/**
 * Single Template for Events CPT
 *
 * @package EauSystem
 * @since 1.28.2
 */

use EauSystem\Events\Frontend\Eau_Events_Helper as Helper;
use EauSystem\EventRegistrations\Frontend\Eau_Event_Registrations_Ajax;

if (!defined('ABSPATH')) exit;

get_header();

while (have_posts()) : the_post();
    $data = Helper::get_event_data(get_the_ID());
    $meta = $data['meta'];

    // Date formatting
    $date_display = Helper::format_date($data['start_obj'], 'l, F j, Y');
    $time_display = Helper::format_time($data['start_obj']);
    if ($data['end_obj']) $time_display .= ' - ' . Helper::format_time($data['end_obj']);
    $full_date = Helper::format_date($data['start_obj'], 'l j F Y \a\t h:i a');
    $iso_date = $data['start_obj'] ? $data['start_obj']->format('c') : '';

    // Timezone formatting
    $timezone_value = $meta['timezone'] ?: 'Australia/Sydney';
    $timezone_label = '';
    $timezone_abbr = '';
    try {
        $tz = new \DateTimeZone($timezone_value);
        $timezones_list = \EauSystem\Events\Config\get_timezones();
        $timezone_label = isset($timezones_list[$timezone_value]) ? $timezones_list[$timezone_value] : $timezone_value;
        if ($data['start_obj']) {
            $timezone_abbr = $data['start_obj']->format('T'); // e.g., AEDT, AEST
        }
    } catch (\Exception $e) {
        $timezone_label = $timezone_value;
    }

    // Registration check
    $is_registered = Eau_Event_Registrations_Ajax::is_user_registered(get_the_ID());
    $current_registrations = Eau_Event_Registrations_Ajax::count_registrations(get_the_ID());
    $capacity = intval($meta['capacity']);
    $spots_left = $capacity > 0 ? max(0, $capacity - $current_registrations) : null;

    // Visibility and pricing
    $visibility = $meta['visibility'] ?: 'public';
    $user_price = Helper::get_user_price(get_the_ID());
    $can_register = Helper::can_user_register(get_the_ID());

    // User data for pre-fill
    $user_name = '';
    $user_email = '';
    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();
        $user_name = $current_user->display_name;
        $user_email = $current_user->user_email;
    }

    // Get event category name (v1.68.9)
    $event_category_name = '';
    if (!empty($meta['event_category'])) {
        global $wpdb;
        $cat_table = $wpdb->prefix . 'eau_event_categories';
        $event_category_name = $wpdb->get_var($wpdb->prepare(
            "SELECT category_name FROM $cat_table WHERE id = %d",
            intval($meta['event_category'])
        ));
    }
?>

<div class="eau-event-single">
    <div class="eau-event-container">
        <!-- Back Link -->
        <a href="<?php echo get_post_type_archive_link('eau_event'); ?>" class="eau-event-back-link">
            <?php echo Helper::icon('chevron-left', 16); ?>
            <?php _e('Back to Events', 'eau-system'); ?>
        </a>

        <div class="eau-event-layout">
            <!-- Main Content -->
            <div class="eau-event-main">
                <?php if ($data['thumbnail']) : ?>
                    <div class="eau-event-image">
                        <img src="<?php echo esc_url($data['thumbnail']); ?>" alt="<?php echo esc_attr($data['title']); ?>">
                    </div>
                <?php endif; ?>

                <h1 class="eau-event-title"><?php echo esc_html($data['title']); ?></h1>

                <?php if ($event_category_name) : ?>
                    <div class="eau-event-category-badge">
                        <?php echo Helper::icon('tag', 14); ?>
                        <span><?php echo esc_html($event_category_name); ?></span>
                    </div>
                <?php endif; ?>

                <!-- Date & Location Info -->
                <div class="eau-event-info-grid">
                    <div class="eau-event-info-item">
                        <div class="eau-event-info-icon"><?php echo Helper::icon('calendar', 20); ?></div>
                        <div class="eau-event-info-content">
                            <span class="eau-event-info-label"><?php _e('Date & Time', 'eau-system'); ?></span>
                            <span class="eau-event-info-value"><?php echo esc_html($date_display); ?></span>
                            <span class="eau-event-info-subvalue">
                                <?php echo esc_html($time_display); ?>
                                <?php if ($timezone_abbr || $timezone_label) : ?>
                                    <span class="eau-event-timezone">
                                        (<?php echo esc_html($timezone_abbr ?: $timezone_label); ?>)
                                    </span>
                                <?php endif; ?>
                            </span>
                            <?php if ($timezone_label && $timezone_abbr) : ?>
                                <span class="eau-event-info-timezone-full"><?php echo esc_html($timezone_label); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="eau-event-info-item">
                        <div class="eau-event-info-icon"><?php echo Helper::icon('map-pin', 20); ?></div>
                        <div class="eau-event-info-content">
                            <span class="eau-event-info-label"><?php _e('Location', 'eau-system'); ?></span>
                            <span class="eau-event-info-value"><?php echo esc_html($data['location']['full']); ?></span>
                        </div>
                    </div>
                </div>

                <!-- About Section -->
                <div class="eau-event-section">
                    <h2 class="eau-event-section-title"><?php _e('About This Event', 'eau-system'); ?></h2>
                    <div class="eau-event-description">
                        <?php if ($meta['full_description']) : ?>
                            <?php echo wp_kses_post($meta['full_description']); ?>
                        <?php elseif ($meta['short_description']) : ?>
                            <p><?php echo esc_html($meta['short_description']); ?></p>
                        <?php else : ?>
                            <p class="eau-event-no-description"><?php _e('No description available', 'eau-system'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- CPD Points Section -->
                <?php if ($meta['cpd_points'] && floatval($meta['cpd_points']) > 0) :
                    // Get CPD category name from eau_activity_categories table
                    $cpd_category_name = '';
                    if (!empty($meta['cpd_category'])) {
                        global $wpdb;
                        $table_name = $wpdb->prefix . 'eau_activity_categories';
                        $cpd_category_name = $wpdb->get_var($wpdb->prepare(
                            "SELECT category_name FROM $table_name WHERE id = %d",
                            intval($meta['cpd_category'])
                        ));
                    }
                ?>
                    <div class="eau-event-section eau-event-cpd-section">
                        <div class="eau-event-cpd-box">
                            <div class="eau-event-cpd-icon"><?php echo Helper::icon('graduation', 24); ?></div>
                            <div class="eau-event-cpd-content">
                                <span class="eau-event-cpd-title"><?php _e('CPD Points', 'eau-system'); ?></span>
                                <span class="eau-event-cpd-text">
                                    <?php printf(__('Earn %s CPD points by attending this event.', 'eau-system'), '<strong>' . esc_html($meta['cpd_points']) . '</strong>'); ?>
                                </span>
                                <?php if ($cpd_category_name) : ?>
                                    <span class="eau-event-cpd-category">
                                        <?php printf(__('Category: %s', 'eau-system'), '<strong>' . esc_html($cpd_category_name) . '</strong>'); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Sidebar -->
            <aside class="eau-event-sidebar">
                <div class="eau-event-price-card">
                    <div class="eau-event-price-header">
                        <?php if ($visibility === 'members') : ?>
                            <span class="eau-event-visibility-badge eau-visibility-members">
                                <?php echo Helper::icon('lock', 14); ?>
                                <?php _e('Members Only', 'eau-system'); ?>
                            </span>
                        <?php endif; ?>
                        <span class="eau-event-price-label"><?php echo esc_html($user_price['label']); ?></span>
                        <?php if ($data['is_live']) : ?>
                            <div class="eau-event-live-badge">
                                <span class="eau-live-dot"></span>
                                <span class="eau-live-text"><?php _e('LIVE', 'eau-system'); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <span class="eau-event-price-value <?php echo $user_price['amount'] == 0 ? 'eau-event-price-free' : ''; ?>">
                        <?php echo esc_html($user_price['display']); ?>
                    </span>

                    <?php
                    // Show member discount info for non-members on public events
                    $member_price = floatval(get_post_meta(get_the_ID(), 'evt_member_price', true));
                    if ($visibility === 'public' && !$user_price['is_member'] && $member_price < $user_price['amount']) :
                    ?>
                        <p class="eau-event-member-discount">
                            <?php printf(__('Members pay only $%s', 'eau-system'), number_format($member_price, 2)); ?>
                            <a href="<?php echo esc_url(home_url('/dashboard/my-institution/')); ?>"><?php _e('Join an Institution', 'eau-system'); ?></a>
                        </p>
                    <?php endif; ?>

                    <?php if ($capacity > 0) : ?>
                        <div class="eau-event-capacity">
                            <?php echo Helper::icon('users', 16); ?>
                            <span><?php _e('Spots Left', 'eau-system'); ?></span>
                            <span class="eau-event-capacity-value <?php echo $spots_left === 0 ? 'eau-event-full' : ''; ?>">
                                <?php echo $spots_left === 0 ? __('Full', 'eau-system') : sprintf('%d / %d', $spots_left, $capacity); ?>
                            </span>
                        </div>
                    <?php endif; ?>

                    <?php if ($data['is_live']) : ?>
                        <?php
                        $event_type = $meta['event_type'] ?: 'in-person';
                        $show_location = in_array($event_type, array('in-person', 'hybrid'));
                        $show_virtual = in_array($event_type, array('virtual', 'hybrid'));
                        ?>

                        <?php if ($show_location && !empty($data['location']['full'])) : ?>
                            <div class="eau-event-live-location">
                                <?php echo Helper::icon('map-pin', 16); ?>
                                <span><?php echo esc_html($data['location']['full']); ?></span>
                            </div>
                        <?php endif; ?>

                        <?php if ($is_registered) : ?>
                            <?php if ($show_virtual && !empty($meta['virtual_url'])) : ?>
                                <a href="<?php echo esc_url($meta['virtual_url']); ?>" target="_blank" class="eau-btn eau-btn-primary eau-btn-full eau-event-join-btn" data-event-id="<?php echo esc_attr($data['id']); ?>">
                                    <?php echo Helper::icon('video', 18); ?>
                                    <?php _e('Join Online', 'eau-system'); ?>
                                </a>
                            <?php endif; ?>
                        <?php else : ?>
                            <div class="eau-event-not-registered-badge">
                                <?php echo Helper::icon('alert-circle', 18); ?>
                                <?php _e('You must be registered to join this event', 'eau-system'); ?>
                            </div>
                        <?php endif; ?>

                    <?php elseif (!$data['is_past']) : ?>
                        <?php if ($is_registered) : ?>
                            <div class="eau-event-registered-badge">
                                <?php echo Helper::icon('check-circle', 20); ?>
                                <?php _e("You're registered!", 'eau-system'); ?>
                            </div>
                        <?php elseif (!$can_register['can_register']) : ?>
                            <div class="eau-event-cannot-register">
                                <p class="eau-event-cannot-register-message"><?php echo esc_html($can_register['message']); ?></p>
                                <?php if ($can_register['reason'] === 'login_required') : ?>
                                    <a href="<?php echo esc_url(wp_login_url(get_permalink())); ?>" class="eau-btn eau-btn-primary eau-btn-full">
                                        <?php _e('Log In to Register', 'eau-system'); ?>
                                    </a>
                                <?php elseif ($can_register['reason'] === 'members_only') : ?>
                                    <a href="<?php echo esc_url(home_url('/membership-selection/')); ?>" class="eau-btn eau-btn-secondary eau-btn-full">
                                        <?php _e('Become a Member', 'eau-system'); ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php elseif ($spots_left === 0) : ?>
                            <button class="eau-btn eau-btn-secondary eau-btn-full" disabled>
                                <?php _e('Event Full', 'eau-system'); ?>
                            </button>
                        <?php else : ?>
                            <button class="eau-btn eau-btn-primary eau-btn-full eau-event-register-btn" data-event-id="<?php echo esc_attr($data['id']); ?>">
                                <?php _e('Register Now', 'eau-system'); ?>
                            </button>
                        <?php endif; ?>

                        <!-- Countdown -->
                        <div class="eau-event-countdown" data-start="<?php echo esc_attr($iso_date); ?>">
                            <div class="eau-countdown-header">
                                <?php echo Helper::icon('clock', 16); ?>
                                <span><?php _e('Event Starts In', 'eau-system'); ?></span>
                            </div>
                            <div class="eau-countdown-title"><?php echo esc_html($data['title']); ?></div>
                            <div class="eau-countdown-timer">
                                <div class="eau-countdown-item"><span class="eau-countdown-value" data-days>--</span><span class="eau-countdown-label"><?php _e('DAYS', 'eau-system'); ?></span></div>
                                <div class="eau-countdown-item"><span class="eau-countdown-value" data-hours>--</span><span class="eau-countdown-label"><?php _e('HOURS', 'eau-system'); ?></span></div>
                                <div class="eau-countdown-item"><span class="eau-countdown-value" data-minutes>--</span><span class="eau-countdown-label"><?php _e('MINUTES', 'eau-system'); ?></span></div>
                                <div class="eau-countdown-item"><span class="eau-countdown-value" data-seconds>--</span><span class="eau-countdown-label"><?php _e('SECONDS', 'eau-system'); ?></span></div>
                            </div>
                            <div class="eau-countdown-date"><?php echo esc_html($full_date); ?></div>
                        </div>
                    <?php else : ?>
                        <div class="eau-event-past-badge"><?php _e('This event has ended', 'eau-system'); ?></div>

                        <?php
                        // Recording and Materials Section (v1.68.10)
                        // Allow purchase of access for users who didn't register
                        $has_recording = !empty($meta['recording_url']);
                        $has_materials = !empty($meta['materials']);

                        if ($has_recording || $has_materials) :
                            // Determine if user can purchase/access resources
                            $can_access_resources = false;
                            $can_purchase_access = false;
                            $purchase_price = 0;
                            $access_reason = '';

                            if (!is_user_logged_in()) {
                                $access_reason = 'login_required';
                            } elseif ($is_registered) {
                                // Already registered = full access
                                $can_access_resources = true;
                            } else {
                                // Not registered - check if can purchase access
                                $can_register_check = Helper::can_user_register(get_the_ID());

                                if ($can_register_check['can_register']) {
                                    // User type is compatible with event visibility
                                    $can_purchase_access = true;
                                    $purchase_price = $user_price['amount'];
                                } else {
                                    // Can't access (e.g., members only event for non-member)
                                    $access_reason = $can_register_check['reason'];
                                }
                            }
                        ?>
                            <div class="eau-event-resources-section">
                                <?php if (!is_user_logged_in()) : ?>
                                    <!-- Not logged in message -->
                                    <div class="eau-event-resources-locked">
                                        <?php echo Helper::icon('lock', 18); ?>
                                        <span><?php _e('Log in to access event resources', 'eau-system'); ?></span>
                                        <a href="<?php echo esc_url(wp_login_url(get_permalink())); ?>" class="eau-btn eau-btn-sm eau-btn-primary">
                                            <?php _e('Log In', 'eau-system'); ?>
                                        </a>
                                    </div>
                                <?php elseif ($can_access_resources) : ?>
                                    <!-- Show resources for registered attendees -->
                                    <h4 class="eau-event-resources-title">
                                        <?php echo Helper::icon('folder-open', 18); ?>
                                        <?php _e('Event Resources', 'eau-system'); ?>
                                    </h4>

                                    <?php if ($has_recording) : ?>
                                        <div class="eau-event-resource-item">
                                            <a href="<?php echo esc_url($meta['recording_url']); ?>" target="_blank" class="eau-event-recording-link">
                                                <?php echo Helper::icon('play-circle', 20); ?>
                                                <span><?php _e('Watch Recording', 'eau-system'); ?></span>
                                                <?php echo Helper::icon('external-link', 14); ?>
                                            </a>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($has_materials) :
                                        // Parse materials - can be JSON array or newline-separated URLs
                                        $materials_list = array();
                                        $materials_data = $meta['materials'];

                                        // Try to decode as JSON first
                                        $decoded = json_decode($materials_data, true);
                                        if (is_array($decoded)) {
                                            $materials_list = $decoded;
                                        } else {
                                            // Legacy format: newline-separated URLs
                                            $urls = array_filter(array_map('trim', explode("\n", $materials_data)));
                                            foreach ($urls as $url) {
                                                $materials_list[] = array(
                                                    'type' => 'url',
                                                    'url' => $url,
                                                    'name' => basename(parse_url($url, PHP_URL_PATH)) ?: $url,
                                                );
                                            }
                                        }

                                        if (!empty($materials_list)) :
                                    ?>
                                        <div class="eau-event-materials-list">
                                            <span class="eau-event-materials-label"><?php _e('Supplementary Materials:', 'eau-system'); ?></span>
                                            <?php foreach ($materials_list as $material) :
                                                $material_url = isset($material['url']) ? $material['url'] : '';
                                                $material_name = isset($material['name']) ? $material['name'] : basename($material_url);
                                                $material_type = isset($material['type']) ? $material['type'] : 'url';

                                                // Determine icon based on file extension
                                                $ext = strtolower(pathinfo($material_name, PATHINFO_EXTENSION));
                                                $icon = 'file';
                                                if (in_array($ext, array('pdf'))) $icon = 'file-text';
                                                elseif (in_array($ext, array('doc', 'docx'))) $icon = 'file-text';
                                                elseif (in_array($ext, array('xls', 'xlsx'))) $icon = 'file-spreadsheet';
                                                elseif (in_array($ext, array('ppt', 'pptx'))) $icon = 'file-presentation';
                                                elseif (in_array($ext, array('zip', 'rar'))) $icon = 'file-archive';
                                                elseif (in_array($ext, array('jpg', 'jpeg', 'png', 'gif'))) $icon = 'image';
                                                elseif ($material_type === 'url') $icon = 'link';
                                            ?>
                                                <a href="<?php echo esc_url($material_url); ?>" target="_blank" class="eau-event-material-link">
                                                    <?php echo Helper::icon($icon, 16); ?>
                                                    <span><?php echo esc_html($material_name); ?></span>
                                                    <?php echo Helper::icon('download', 14); ?>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                <?php elseif ($can_purchase_access) : ?>
                                    <!-- Can purchase access to recording/materials -->
                                    <div class="eau-event-purchase-access">
                                        <h4 class="eau-event-resources-title">
                                            <?php echo Helper::icon('video', 18); ?>
                                            <?php _e('Recording Available', 'eau-system'); ?>
                                        </h4>
                                        <p class="eau-event-purchase-desc">
                                            <?php _e('Get access to the event recording and supplementary materials.', 'eau-system'); ?>
                                        </p>
                                        <div class="eau-event-purchase-price">
                                            <span class="eau-purchase-price-label"><?php echo esc_html($user_price['label']); ?>:</span>
                                            <span class="eau-purchase-price-value"><?php echo esc_html($user_price['display']); ?></span>
                                        </div>
                                        <?php if ($purchase_price <= 0) : ?>
                                            <!-- Free access -->
                                            <button class="eau-btn eau-btn-primary eau-btn-full eau-event-get-access-btn" data-event-id="<?php echo esc_attr($data['id']); ?>">
                                                <?php echo Helper::icon('unlock', 18); ?>
                                                <?php _e('Get Free Access', 'eau-system'); ?>
                                            </button>
                                        <?php else : ?>
                                            <!-- Paid access -->
                                            <button class="eau-btn eau-btn-primary eau-btn-full eau-event-purchase-access-btn" data-event-id="<?php echo esc_attr($data['id']); ?>" data-price="<?php echo esc_attr($purchase_price); ?>">
                                                <?php echo Helper::icon('credit-card', 18); ?>
                                                <?php printf(__('Purchase Access - %s', 'eau-system'), esc_html($user_price['display'])); ?>
                                            </button>
                                        <?php endif; ?>

                                        <?php
                                        // Show member discount info for non-members
                                        $member_price = floatval(get_post_meta(get_the_ID(), 'evt_member_price', true));
                                        if ($visibility === 'public' && !$user_price['is_member'] && $member_price < $purchase_price) :
                                        ?>
                                            <p class="eau-event-member-discount-small">
                                                <?php printf(__('Members pay only %s', 'eau-system'), '$' . number_format($member_price, 2)); ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>

                                <?php else : ?>
                                    <!-- Cannot access (e.g., members only event) -->
                                    <div class="eau-event-resources-locked">
                                        <?php echo Helper::icon('lock', 18); ?>
                                        <?php if ($access_reason === 'members_only') : ?>
                                            <span><?php _e('This recording is available for members only', 'eau-system'); ?></span>
                                            <a href="<?php echo esc_url(home_url('/dashboard/my-institution/')); ?>" class="eau-btn eau-btn-sm eau-btn-secondary">
                                                <?php _e('Join an Institution', 'eau-system'); ?>
                                            </a>
                                        <?php else : ?>
                                            <span><?php _e('You do not have access to this content', 'eau-system'); ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                    <?php endif; ?>

                    <!-- CPD Link - only show after event ended for eligible users -->
                    <?php if ($data['is_past'] && $meta['cpd_points'] && floatval($meta['cpd_points']) > 0 && Eau_Event_Registrations_Ajax::can_view_cpd(get_the_ID())) : ?>
                        <a href="<?php echo esc_url(home_url('/dashboard/my-activities/')); ?>" class="eau-event-cpd-link">
                            <?php _e('View in My CPD', 'eau-system'); ?>
                        </a>
                    <?php endif; ?>

                </div>

                <!-- Organizer Card -->
                <div class="eau-event-organizer-card">
                    <span class="eau-event-organizer-label"><?php _e('Organized by', 'eau-system'); ?></span>
                    <div class="eau-event-organizer-info">
                        <div class="eau-event-organizer-logo"><?php echo Helper::icon('building', 24); ?></div>
                        <div class="eau-event-organizer-details">
                            <span class="eau-event-organizer-name"><?php echo get_bloginfo('name'); ?></span>
                            <span class="eau-event-organizer-role"><?php _e('Professional Development Team', 'eau-system'); ?></span>
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</div>

<!-- Registration Modal -->
<div class="eau-reg-modal" id="eau-registration-modal">
    <div class="eau-reg-modal-overlay"></div>
    <div class="eau-reg-modal-container">
        <div class="eau-reg-modal-header">
            <h2 class="eau-reg-modal-title"><?php _e('Confirm Registration', 'eau-system'); ?></h2>
            <button type="button" class="eau-reg-modal-close" aria-label="Close">&times;</button>
        </div>
        <div class="eau-reg-modal-body">
            <div class="eau-reg-event-info">
                <strong><?php echo esc_html($data['title']); ?></strong>
                <span><?php echo esc_html($date_display); ?> &bull; <?php echo esc_html($time_display); ?></span>
            </div>

            <div class="eau-reg-user-info">
                <p class="eau-reg-info-label"><?php _e('You will be registered as:', 'eau-system'); ?></p>
                <div class="eau-reg-info-row">
                    <span class="eau-reg-info-icon">👤</span>
                    <span class="eau-reg-info-value"><?php echo esc_html($user_name); ?></span>
                </div>
                <div class="eau-reg-info-row">
                    <span class="eau-reg-info-icon">✉️</span>
                    <span class="eau-reg-info-value"><?php echo esc_html($user_email); ?></span>
                </div>
            </div>

            <!-- Additional Information -->
            <div class="eau-reg-additional-info">
                <p class="eau-reg-section-title"><?php _e('Additional Information (Optional)', 'eau-system'); ?></p>

                <div class="eau-reg-field">
                    <label for="eau-reg-dietary"><?php _e('Dietary Requirements', 'eau-system'); ?></label>
                    <input type="text" id="eau-reg-dietary" name="dietary_requirements" placeholder="<?php esc_attr_e('e.g., Vegetarian, Gluten-free, Halal', 'eau-system'); ?>">
                </div>

                <div class="eau-reg-field">
                    <label for="eau-reg-accessibility"><?php _e('Accessibility Requirements', 'eau-system'); ?></label>
                    <input type="text" id="eau-reg-accessibility" name="accessibility_requirements" placeholder="<?php esc_attr_e('e.g., Wheelchair access, Hearing loop', 'eau-system'); ?>">
                </div>

                <div class="eau-reg-field">
                    <label for="eau-reg-notes"><?php _e('Additional Notes', 'eau-system'); ?></label>
                    <textarea id="eau-reg-notes" name="additional_notes" rows="3" placeholder="<?php esc_attr_e('Any other information we should know?', 'eau-system'); ?>"></textarea>
                </div>
            </div>

            <!-- Terms and Conditions -->
            <div class="eau-reg-terms">
                <label class="eau-reg-checkbox-label">
                    <input type="checkbox" id="eau-reg-terms" name="agree_terms" required>
                    <span class="eau-reg-checkbox-text">
                        <?php _e('I agree to the terms and conditions and understand that:', 'eau-system'); ?>
                    </span>
                </label>
                <ul class="eau-reg-terms-list">
                    <li><?php _e('Registration is subject to availability', 'eau-system'); ?></li>
                    <li><?php _e('Cancellations must be made 48 hours in advance', 'eau-system'); ?></li>
                    <li><?php _e('CPD points will be awarded upon attendance', 'eau-system'); ?></li>
                    <li><?php _e('I will receive email reminders about this event', 'eau-system'); ?></li>
                </ul>
            </div>

            <div class="eau-reg-message" id="eau-registration-message"></div>
        </div>
        <div class="eau-reg-modal-footer">
            <button type="button" class="eau-reg-btn-cancel" id="eau-cancel-registration"><?php _e('Cancel', 'eau-system'); ?></button>
            <button type="button" class="eau-reg-btn-confirm" id="eau-confirm-registration" data-event-id="<?php echo esc_attr($data['id']); ?>">
                <span class="btn-text"><?php _e('Confirm', 'eau-system'); ?></span>
                <span class="btn-loading" style="display:none;"><?php _e('Registering...', 'eau-system'); ?></span>
            </button>
        </div>
        <input type="hidden" id="eau-reg-nonce" value="<?php echo wp_create_nonce('eau_event_registration'); ?>">
    </div>
</div>

<?php endwhile;

get_footer();
