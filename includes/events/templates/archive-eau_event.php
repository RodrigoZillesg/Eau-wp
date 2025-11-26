<?php
/**
 * Archive Template for Events CPT
 *
 * @package EauSystem
 * @since 1.28.2
 */

use EauSystem\Events\Frontend\Eau_Events_Helper as Helper;

if (!defined('ABSPATH')) exit;

get_header();

// Filters
$search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
$category = isset($_GET['category']) ? absint($_GET['category']) : 0;
$event_type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : '';

// CPD categories
$cpd_categories = get_terms(array('taxonomy' => 'cpd_category', 'hide_empty' => false));

// Base query args
$base_args = array(
    'post_type' => 'eau_event',
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'meta_key' => 'evt_start_datetime',
    'orderby' => 'meta_value',
);

if (!empty($search)) $base_args['s'] = $search;
if ($category > 0) {
    $base_args['tax_query'] = array(array('taxonomy' => 'cpd_category', 'field' => 'term_id', 'terms' => $category));
}

// Upcoming events
$upcoming_args = array_merge($base_args, array(
    'order' => 'ASC',
    'meta_query' => array(array('key' => 'evt_start_datetime', 'value' => current_time('Y-m-d H:i:s'), 'compare' => '>=', 'type' => 'DATETIME')),
));
if (!empty($event_type)) $upcoming_args['meta_query'][] = array('key' => 'evt_event_type', 'value' => $event_type);

// Past events
$past_args = array_merge($base_args, array(
    'order' => 'DESC',
    'meta_query' => array(array('key' => 'evt_start_datetime', 'value' => current_time('Y-m-d H:i:s'), 'compare' => '<', 'type' => 'DATETIME')),
));
if (!empty($event_type)) $past_args['meta_query'][] = array('key' => 'evt_event_type', 'value' => $event_type);

$upcoming = new WP_Query($upcoming_args);
$past = new WP_Query($past_args);
?>

<div class="eau-events-archive">
    <div class="eau-events-container">
        <!-- Header -->
        <div class="eau-events-header">
            <div class="eau-events-header-content">
                <h1 class="eau-events-title"><?php _e('Events', 'eau-system'); ?></h1>
                <p class="eau-events-subtitle"><?php _e('Discover and register for upcoming events', 'eau-system'); ?></p>
            </div>
            <?php if (Helper::is_admin()) : ?>
                <a href="<?php echo admin_url('edit.php?post_type=eau_event'); ?>" class="eau-btn eau-btn-primary">
                    <?php echo Helper::icon('settings', 16); ?>
                    <?php _e('Manage Events', 'eau-system'); ?>
                </a>
            <?php endif; ?>
        </div>

        <!-- Filters -->
        <div class="eau-events-filters">
            <form method="get" action="<?php echo get_post_type_archive_link('eau_event'); ?>" class="eau-events-filter-form">
                <div class="eau-filter-search">
                    <input type="text" name="search" class="eau-filter-input" placeholder="<?php _e('Search events...', 'eau-system'); ?>" value="<?php echo esc_attr($search); ?>">
                </div>
                <div class="eau-filter-select-wrapper">
                    <select name="category" class="eau-filter-select">
                        <option value=""><?php _e('All Categories', 'eau-system'); ?></option>
                        <?php foreach ($cpd_categories as $cat) : ?>
                            <option value="<?php echo esc_attr($cat->term_id); ?>" <?php selected($category, $cat->term_id); ?>><?php echo esc_html($cat->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="eau-filter-select-wrapper">
                    <select name="type" class="eau-filter-select">
                        <option value=""><?php _e('All Types', 'eau-system'); ?></option>
                        <option value="in-person" <?php selected($event_type, 'in-person'); ?>><?php _e('In-Person', 'eau-system'); ?></option>
                        <option value="virtual" <?php selected($event_type, 'virtual'); ?>><?php _e('Virtual', 'eau-system'); ?></option>
                        <option value="hybrid" <?php selected($event_type, 'hybrid'); ?>><?php _e('Hybrid', 'eau-system'); ?></option>
                    </select>
                </div>
            </form>
        </div>

        <!-- Upcoming Events -->
        <?php if ($upcoming->have_posts()) : ?>
            <section class="eau-events-section">
                <h2 class="eau-events-section-title"><?php _e('Upcoming Events', 'eau-system'); ?></h2>
                <div class="eau-events-grid eau-events-grid-upcoming">
                    <?php while ($upcoming->have_posts()) : $upcoming->the_post(); ?>
                        <?php echo Helper::render_card(get_the_ID(), 'upcoming'); ?>
                    <?php endwhile; wp_reset_postdata(); ?>
                </div>
            </section>
        <?php endif; ?>

        <!-- Past Events -->
        <?php if ($past->have_posts()) : ?>
            <section class="eau-events-section">
                <h2 class="eau-events-section-title"><?php _e('Past Events', 'eau-system'); ?></h2>
                <div class="eau-events-grid eau-events-grid-past">
                    <?php while ($past->have_posts()) : $past->the_post(); ?>
                        <?php echo Helper::render_card(get_the_ID(), 'past'); ?>
                    <?php endwhile; wp_reset_postdata(); ?>
                </div>
            </section>
        <?php endif; ?>

        <!-- No Events -->
        <?php if (!$upcoming->have_posts() && !$past->have_posts()) : ?>
            <div class="eau-events-empty">
                <?php echo Helper::icon('calendar', 48); ?>
                <h3><?php _e('No events found', 'eau-system'); ?></h3>
                <p><?php _e('Check back later for upcoming events.', 'eau-system'); ?></p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php get_footer();
