<?php
/**
 * Single Template for Events CPT
 *
 * @package EauSystem
 * @since 1.28.2
 */

use EauSystem\Events\Frontend\Eau_Events_Helper as Helper;

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

    // Registration check (placeholder)
    $is_registered = false;
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
                <?php if ($meta['cpd_points'] && floatval($meta['cpd_points']) > 0) : ?>
                    <div class="eau-event-section eau-event-cpd-section">
                        <div class="eau-event-cpd-box">
                            <div class="eau-event-cpd-icon"><?php echo Helper::icon('graduation', 24); ?></div>
                            <div class="eau-event-cpd-content">
                                <span class="eau-event-cpd-title"><?php _e('CPD Points', 'eau-system'); ?></span>
                                <span class="eau-event-cpd-text">
                                    <?php printf(__('Earn %s CPD points by attending this event.', 'eau-system'), '<strong>' . esc_html($meta['cpd_points']) . '</strong>'); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Sidebar -->
            <aside class="eau-event-sidebar">
                <div class="eau-event-price-card">
                    <span class="eau-event-price-label"><?php _e('Member Price', 'eau-system'); ?></span>
                    <span class="eau-event-price-value <?php echo $data['price']['is_free'] ? 'eau-event-price-free' : ''; ?>">
                        <?php echo esc_html($data['price']['display']); ?>
                    </span>

                    <?php if ($meta['capacity']) : ?>
                        <div class="eau-event-capacity">
                            <?php echo Helper::icon('users', 16); ?>
                            <span><?php _e('Capacity', 'eau-system'); ?></span>
                            <span class="eau-event-capacity-value"><?php echo esc_html($meta['capacity']); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if (!$data['is_past']) : ?>
                        <?php if ($is_registered) : ?>
                            <div class="eau-event-registered-badge">
                                <?php echo Helper::icon('check-circle', 20); ?>
                                <?php _e("You're registered!", 'eau-system'); ?>
                            </div>
                        <?php else : ?>
                            <button class="eau-btn eau-btn-primary eau-btn-full eau-event-register-btn">
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

                    <!-- CPD Link -->
                    <?php if ($meta['cpd_points'] && floatval($meta['cpd_points']) > 0) : ?>
                        <a href="<?php echo is_user_logged_in() ? esc_url(home_url('/dashboard/my-cpd/')) : esc_url(wp_login_url(get_permalink())); ?>" class="eau-event-cpd-link">
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

<?php endwhile;

get_footer();
