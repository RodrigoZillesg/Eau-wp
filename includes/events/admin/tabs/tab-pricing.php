<?php
/**
 * Events Admin - Tab: Capacity & Pricing
 *
 * Renderiza a aba de capacidade e preços do evento.
 * Inclui campos para capacidade, preços e configurações de guests.
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
 * Renderiza o conteúdo da tab Pricing
 *
 * Campos incluídos:
 * - Capacity (number, 0 = unlimited)
 * - Member Price (number, step 0.01)
 * - Non-Member Price (number, step 0.01)
 * - Early Bird Price (number, step 0.01)
 * - Early Bird End Date (datetime-local)
 * - Allow Guests (checkbox)
 * - Max Guests (number, 1-10)
 *
 * @since  1.28.0
 * @param  array $meta Array com valores dos meta fields
 * @return void
 */
function render_pricing($meta) {
    $p = Config\META_PREFIX;
    ?>
    <div class="eau-tab-panel" id="tab-pricing">
        <div class="eau-form-grid">
            <!-- Capacity -->
            <div class="eau-form-field eau-form-field-span-2">
                <label class="eau-form-label"><?php _e('Event Capacity', 'eau-system'); ?></label>
                <input type="number" name="<?php echo $p; ?>capacity" class="eau-form-input"
                       value="<?php echo esc_attr($meta['capacity']); ?>" min="0">
                <p class="eau-form-hint"><?php _e('Leave empty for unlimited', 'eau-system'); ?></p>
            </div>

            <!-- Member Price -->
            <div class="eau-form-field">
                <label class="eau-form-label"><?php _e('Member Price ($)', 'eau-system'); ?></label>
                <input type="number" name="<?php echo $p; ?>member_price" class="eau-form-input"
                       value="<?php echo esc_attr($meta['member_price']); ?>" min="0" step="0.01">
            </div>

            <!-- Non-Member Price -->
            <div class="eau-form-field">
                <label class="eau-form-label"><?php _e('Non-Member Price ($)', 'eau-system'); ?></label>
                <input type="number" name="<?php echo $p; ?>non_member_price" class="eau-form-input"
                       value="<?php echo esc_attr($meta['non_member_price']); ?>" min="0" step="0.01">
            </div>

            <!-- Early Bird Section -->
            <div class="eau-form-field eau-form-field-span-2">
                <h4 class="eau-form-section-title"><?php _e('Early Bird (Optional)', 'eau-system'); ?></h4>
            </div>

            <!-- Early Bird Price -->
            <div class="eau-form-field">
                <label class="eau-form-label"><?php _e('Early Bird Price ($)', 'eau-system'); ?></label>
                <input type="number" name="<?php echo $p; ?>early_bird_price" id="<?php echo $p; ?>early_bird_price"
                       class="eau-form-input" value="<?php echo esc_attr($meta['early_bird_price']); ?>" min="0" step="0.01">
            </div>

            <!-- Early Bird End -->
            <div class="eau-form-field">
                <label class="eau-form-label"><?php _e('Early Bird End Date', 'eau-system'); ?></label>
                <input type="datetime-local" name="<?php echo $p; ?>early_bird_end_date" id="<?php echo $p; ?>early_bird_end_date"
                       class="eau-form-input" value="<?php echo esc_attr($meta['early_bird_end_date']); ?>">
            </div>

            <!-- Guests Section -->
            <div class="eau-form-field eau-form-field-span-2">
                <h4 class="eau-form-section-title"><?php _e('Guest Settings', 'eau-system'); ?></h4>
            </div>

            <!-- Allow Guests -->
            <div class="eau-form-field">
                <label class="eau-checkbox-label">
                    <input type="checkbox" name="<?php echo $p; ?>allow_guests" value="1"
                           <?php checked($meta['allow_guests'], '1'); ?>>
                    <?php _e('Allow guests', 'eau-system'); ?>
                </label>
            </div>

            <!-- Max Guests -->
            <div class="eau-form-field">
                <label class="eau-form-label"><?php _e('Max Guests', 'eau-system'); ?></label>
                <input type="number" name="<?php echo $p; ?>max_guests" class="eau-form-input"
                       value="<?php echo esc_attr($meta['max_guests']); ?>" min="1" max="10">
            </div>
        </div>
    </div>
    <?php
}
