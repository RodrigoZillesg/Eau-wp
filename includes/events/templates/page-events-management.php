<?php
/**
 * Template Name: Events Management
 * Template Post Type: page
 *
 * Admin page for managing events.
 *
 * @package EauSystem
 * @since 1.28.3
 */

use EauSystem\Events\Admin\Eau_Events_Management;

if (!defined('ABSPATH')) exit;

get_header();

// Render the events management content
echo Eau_Events_Management::render();

get_footer();
