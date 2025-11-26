<?php
/**
 * Events Admin - Tab: Location
 *
 * Renderiza a aba de localização do evento.
 * Inclui campos para tipo de evento, venue e endereço.
 *
 * @package    EauSystem
 * @subpackage Events\Admin\Tabs
 * @since      1.28.0
 */

namespace EauSystem\Events\Admin\Tabs;

use EauSystem\Events\Config;

if (!defined('WPINC')) {
    die;
}

/**
 * Renderiza o conteúdo da tab Location
 *
 * Campos incluídos:
 * - Event Type (radio: in-person, virtual, hybrid)
 * - Venue Name (text)
 * - Address (text)
 * - City (text)
 * - State (text)
 * - Postal Code (text)
 * - Country (select)
 * - Virtual URL (url, para eventos virtual/hybrid)
 *
 * @since  1.28.0
 * @param  array $meta Array com valores dos meta fields
 * @return void
 */
function render_location($meta) {
    $p = Config\META_PREFIX;
    ?>
    <div class="eau-tab-panel" id="tab-location">
        <div class="eau-form-grid">
            <!-- Event Type -->
            <div class="eau-form-field eau-form-field-span-2">
                <label class="eau-form-label"><?php _e('Event Type', 'eau-system'); ?></label>
                <div class="eau-radio-group">
                    <?php foreach (Config\get_event_types() as $key => $label) : ?>
                        <label class="eau-radio-label">
                            <input type="radio" name="<?php echo $p; ?>event_type" value="<?php echo esc_attr($key); ?>"
                                   <?php checked($meta['event_type'], $key); ?>>
                            <?php echo esc_html($label); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Venue Name -->
            <div class="eau-form-field eau-form-field-span-2 eau-location-field">
                <label class="eau-form-label"><?php _e('Venue Name', 'eau-system'); ?></label>
                <input type="text" name="<?php echo $p; ?>venue_name" class="eau-form-input"
                       value="<?php echo esc_attr($meta['venue_name']); ?>"
                       placeholder="<?php _e('e.g., Sydney Convention Centre', 'eau-system'); ?>">
            </div>

            <!-- Address -->
            <div class="eau-form-field eau-form-field-span-2 eau-location-field">
                <label class="eau-form-label"><?php _e('Address', 'eau-system'); ?></label>
                <input type="text" name="<?php echo $p; ?>address" class="eau-form-input"
                       value="<?php echo esc_attr($meta['address']); ?>">
            </div>

            <!-- City -->
            <div class="eau-form-field eau-location-field">
                <label class="eau-form-label"><?php _e('City', 'eau-system'); ?></label>
                <input type="text" name="<?php echo $p; ?>city" class="eau-form-input"
                       value="<?php echo esc_attr($meta['city']); ?>">
            </div>

            <!-- State -->
            <div class="eau-form-field eau-location-field">
                <label class="eau-form-label"><?php _e('State', 'eau-system'); ?></label>
                <input type="text" name="<?php echo $p; ?>state" class="eau-form-input"
                       value="<?php echo esc_attr($meta['state']); ?>">
            </div>

            <!-- Postal Code -->
            <div class="eau-form-field eau-location-field">
                <label class="eau-form-label"><?php _e('Postal Code', 'eau-system'); ?></label>
                <input type="text" name="<?php echo $p; ?>postal_code" class="eau-form-input"
                       value="<?php echo esc_attr($meta['postal_code']); ?>">
            </div>

            <!-- Country -->
            <div class="eau-form-field eau-location-field">
                <label class="eau-form-label"><?php _e('Country', 'eau-system'); ?></label>
                <select name="<?php echo $p; ?>country" class="eau-form-select">
                    <option value=""><?php _e('Select Country', 'eau-system'); ?></option>
                    <?php foreach (Config\get_countries() as $key => $label) : ?>
                        <option value="<?php echo esc_attr($key); ?>" <?php selected($meta['country'], $key); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Virtual URL -->
            <div class="eau-form-field eau-form-field-span-2 eau-virtual-field">
                <label class="eau-form-label"><?php _e('Virtual Event URL', 'eau-system'); ?></label>
                <input type="url" name="<?php echo $p; ?>virtual_url" class="eau-form-input"
                       value="<?php echo esc_url($meta['virtual_url']); ?>"
                       placeholder="https://zoom.us/j/...">
                <p class="eau-form-hint"><?php _e('Zoom, Teams, etc.', 'eau-system'); ?></p>
            </div>
        </div>
    </div>
    <?php
}
