<?php
/**
 * Events Admin - Tab: Capacity & Pricing
 *
 * Renderiza a aba de capacidade e preços do evento.
 * Inclui campos para capacidade, preços e configurações de visibilidade.
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
 * - Visibility (select: public, members, private)
 * - Member Price (number, step 0.01)
 * - Non-Member Price (number, step 0.01) - only for public events
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
            <div class="eau-form-field">
                <label class="eau-form-label"><?php _e('Event Capacity', 'eau-system'); ?></label>
                <input type="number" name="<?php echo $p; ?>capacity" class="eau-form-input"
                       value="<?php echo esc_attr($meta['capacity']); ?>" min="0">
                <p class="eau-form-hint"><?php _e('Leave empty for unlimited', 'eau-system'); ?></p>
            </div>

            <!-- Visibility -->
            <div class="eau-form-field">
                <label class="eau-form-label"><?php _e('Event Visibility', 'eau-system'); ?></label>
                <select name="<?php echo $p; ?>visibility" id="eau-edit-visibility" class="eau-form-select">
                    <?php foreach (Config\get_visibility_options() as $key => $label) : ?>
                        <option value="<?php echo esc_attr($key); ?>" <?php selected($meta['visibility'], $key); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="eau-form-hint"><?php _e('Who can see and register for this event', 'eau-system'); ?></p>
            </div>

            <!-- Pricing Section -->
            <div class="eau-form-field eau-form-field-span-2">
                <h4 class="eau-form-section-title"><?php _e('Pricing', 'eau-system'); ?></h4>
            </div>

            <!-- Member Price -->
            <div class="eau-form-field">
                <label class="eau-form-label"><?php _e('Member Price ($)', 'eau-system'); ?></label>
                <input type="number" name="<?php echo $p; ?>member_price" id="eau-edit-member_price" class="eau-form-input"
                       value="<?php echo esc_attr($meta['member_price']); ?>" min="0" step="0.01">
                <p class="eau-form-hint"><?php _e('Price for members. Leave 0 for free.', 'eau-system'); ?></p>
            </div>

            <!-- Non-Member Price (only for public events) -->
            <div class="eau-form-field eau-non-member-price-field">
                <label class="eau-form-label"><?php _e('Non-Member Price ($)', 'eau-system'); ?></label>
                <input type="number" name="<?php echo $p; ?>non_member_price" id="eau-edit-non_member_price" class="eau-form-input"
                       value="<?php echo esc_attr($meta['non_member_price']); ?>" min="0" step="0.01">
                <p class="eau-form-hint"><?php _e('Price for non-members. Only applies to public events.', 'eau-system'); ?></p>
            </div>
        </div>
    </div>
    <?php
}
