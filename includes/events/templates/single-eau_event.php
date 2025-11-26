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
                    <span class="eau-event-price-label"><?php _e('Member Price', 'eau-system'); ?></span>
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
                        <!-- LIVE NOW -->
                        <div class="eau-event-live-section">
                            <div class="eau-event-live-badge">
                                <span class="eau-live-dot"></span>
                                <span class="eau-live-text"><?php _e('LIVE NOW', 'eau-system'); ?></span>
                            </div>
                            <div class="eau-event-live-title"><?php echo esc_html($data['title']); ?></div>

                            <?php
                            $event_type = $meta['event_type'] ?: 'in-person';
                            $show_location = in_array($event_type, array('in-person', 'hybrid'));
                            $show_virtual = in_array($event_type, array('virtual', 'hybrid'));
                            ?>

                            <?php if ($show_location && !empty($data['location']['full'])) : ?>
                                <div class="eau-event-live-location">
                                    <?php echo Helper::icon('map-pin', 18); ?>
                                    <span><?php echo esc_html($data['location']['full']); ?></span>
                                </div>
                            <?php endif; ?>

                            <?php if ($show_virtual && !empty($meta['virtual_url'])) : ?>
                                <a href="<?php echo esc_url($meta['virtual_url']); ?>" target="_blank" class="eau-btn eau-btn-primary eau-btn-full eau-event-join-btn">
                                    <?php echo Helper::icon('video', 18); ?>
                                    <?php _e('Join Online', 'eau-system'); ?>
                                </a>
                            <?php endif; ?>
                        </div>

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

<!-- Registration Modal -->
<div class="eau-modal" id="eau-registration-modal">
    <div class="eau-modal-backdrop"></div>
    <div class="eau-modal-content">
        <div class="eau-modal-header">
            <h3 class="eau-modal-title"><?php _e('Register for Event', 'eau-system'); ?></h3>
            <button class="eau-modal-close" type="button">&times;</button>
        </div>
        <div class="eau-modal-body">
            <div class="eau-registration-event-info">
                <strong><?php echo esc_html($data['title']); ?></strong>
                <span><?php echo esc_html($date_display); ?> &bull; <?php echo esc_html($time_display); ?></span>
            </div>
            <form id="eau-registration-form" class="eau-registration-form">
                <input type="hidden" name="event_id" value="<?php echo esc_attr($data['id']); ?>">
                <?php wp_nonce_field('eau_event_registration', 'eau_reg_nonce'); ?>

                <div class="eau-form-group">
                    <label for="eau-attendee-name"><?php _e('Full Name', 'eau-system'); ?> <span class="required">*</span></label>
                    <input type="text" id="eau-attendee-name" name="attendee_name" value="<?php echo esc_attr($user_name); ?>" required>
                </div>

                <div class="eau-form-group">
                    <label for="eau-attendee-email"><?php _e('Email Address', 'eau-system'); ?> <span class="required">*</span></label>
                    <input type="email" id="eau-attendee-email" name="attendee_email" value="<?php echo esc_attr($user_email); ?>" required>
                </div>

                <div class="eau-form-message" id="eau-registration-message"></div>

                <div class="eau-form-actions">
                    <button type="button" class="eau-btn eau-btn-outline eau-modal-cancel"><?php _e('Cancel', 'eau-system'); ?></button>
                    <button type="submit" class="eau-btn eau-btn-primary" id="eau-submit-registration">
                        <span class="btn-text"><?php _e('Complete Registration', 'eau-system'); ?></span>
                        <span class="btn-loading" style="display:none;"><?php _e('Registering...', 'eau-system'); ?></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function() {
    var modal = document.getElementById('eau-registration-modal');
    var form = document.getElementById('eau-registration-form');
    var registerBtn = document.querySelector('.eau-event-register-btn');
    var closeBtn = modal ? modal.querySelector('.eau-modal-close') : null;
    var cancelBtn = modal ? modal.querySelector('.eau-modal-cancel') : null;
    var backdrop = modal ? modal.querySelector('.eau-modal-backdrop') : null;
    var messageEl = document.getElementById('eau-registration-message');
    var submitBtn = document.getElementById('eau-submit-registration');

    function openModal() {
        if (modal) {
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    }

    function closeModal() {
        if (modal) {
            modal.classList.remove('active');
            document.body.style.overflow = '';
            if (messageEl) {
                messageEl.innerHTML = '';
                messageEl.className = 'eau-form-message';
            }
        }
    }

    if (registerBtn) {
        registerBtn.addEventListener('click', openModal);
    }

    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
    if (backdrop) backdrop.addEventListener('click', closeModal);

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeModal();
    });

    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();

            var btnText = submitBtn.querySelector('.btn-text');
            var btnLoading = submitBtn.querySelector('.btn-loading');

            submitBtn.disabled = true;
            btnText.style.display = 'none';
            btnLoading.style.display = 'inline';

            var formData = new FormData(form);
            formData.append('action', 'eau_register_for_event');
            formData.append('nonce', formData.get('eau_reg_nonce'));

            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                body: formData
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                submitBtn.disabled = false;
                btnText.style.display = 'inline';
                btnLoading.style.display = 'none';

                if (data.success) {
                    messageEl.className = 'eau-form-message eau-form-message-success';
                    messageEl.innerHTML = data.data.message;
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    messageEl.className = 'eau-form-message eau-form-message-error';
                    messageEl.innerHTML = data.data.message;
                }
            })
            .catch(function(error) {
                submitBtn.disabled = false;
                btnText.style.display = 'inline';
                btnLoading.style.display = 'none';
                messageEl.className = 'eau-form-message eau-form-message-error';
                messageEl.innerHTML = '<?php _e('An error occurred. Please try again.', 'eau-system'); ?>';
            });
        });
    }
})();
</script>

<?php endwhile;

get_footer();
