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

    // Registration check
    $is_registered = Eau_Event_Registrations_Ajax::is_user_registered(get_the_ID());
    $current_registrations = Eau_Event_Registrations_Ajax::count_registrations(get_the_ID());
    $capacity = intval($meta['capacity']);
    $spots_left = $capacity > 0 ? max(0, $capacity - $current_registrations) : null;

    // User data for pre-fill
    $user_name = '';
    $user_email = '';
    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();
        $user_name = $current_user->display_name;
        $user_email = $current_user->user_email;
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

                <!-- Date & Location Info -->
                <div class="eau-event-info-grid">
                    <div class="eau-event-info-item">
                        <div class="eau-event-info-icon"><?php echo Helper::icon('calendar', 20); ?></div>
                        <div class="eau-event-info-content">
                            <span class="eau-event-info-label"><?php _e('Date & Time', 'eau-system'); ?></span>
                            <span class="eau-event-info-value"><?php echo esc_html($date_display); ?></span>
                            <span class="eau-event-info-subvalue"><?php echo esc_html($time_display); ?></span>
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
                        <span class="eau-event-price-label"><?php _e('Member Price', 'eau-system'); ?></span>
                        <?php if ($data['is_live']) : ?>
                            <div class="eau-event-live-badge">
                                <span class="eau-live-dot"></span>
                                <span class="eau-live-text"><?php _e('LIVE', 'eau-system'); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <span class="eau-event-price-value <?php echo $data['price']['is_free'] ? 'eau-event-price-free' : ''; ?>">
                        <?php echo esc_html($data['price']['display']); ?>
                    </span>

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
                    <?php endif; ?>

                    <!-- CPD Link - only show after event ended for eligible users -->
                    <?php if ($data['is_past'] && $meta['cpd_points'] && floatval($meta['cpd_points']) > 0 && Eau_Event_Registrations_Ajax::can_view_cpd(get_the_ID())) : ?>
                        <a href="<?php echo esc_url(home_url('/dashboard/my-activities/')); ?>" class="eau-event-cpd-link">
                            <?php _e('View in My CPD', 'eau-system'); ?>
                        </a>
                    <?php endif; ?>

                    <!-- Share & Save -->
                    <div class="eau-event-actions">
                        <button class="eau-btn eau-btn-outline eau-event-share-btn" data-url="<?php echo esc_url(get_permalink()); ?>" data-title="<?php echo esc_attr($data['title']); ?>">
                            <?php echo Helper::icon('share', 16); ?>
                            <?php _e('Share', 'eau-system'); ?>
                        </button>
                        <button class="eau-btn eau-btn-outline eau-event-save-btn" data-event-id="<?php echo esc_attr($data['id']); ?>">
                            <?php echo Helper::icon('heart', 16); ?>
                            <?php _e('Save', 'eau-system'); ?>
                        </button>
                    </div>
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
