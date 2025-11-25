<?php
/**
 * Events Admin - Tab: CPD & Settings
 *
 * Renderiza a aba de configurações CPD e visibilidade do evento.
 * Inclui campos para pontos CPD, categoria e controle de acesso.
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
 * Renderiza o conteúdo da tab Settings
 *
 * Campos incluídos:
 * - CPD Points (number, step 0.5)
 * - CPD Category (taxonomy dropdown)
 * - Visibility (select: public, members, private)
 * - Require Approval (checkbox)
 * - Members Only (checkbox)
 *
 * @since  1.28.0
 * @param  array $meta Array com valores dos meta fields
 * @return void
 */
function render_settings($meta) {
    $p = Config\META_PREFIX;
    ?>
    <div class="eau-tab-panel" id="tab-settings">
        <div class="eau-form-grid">
            <!-- CPD Section -->
            <div class="eau-form-field eau-form-field-span-2">
                <h4 class="eau-form-section-title"><?php _e('CPD Settings', 'eau-system'); ?></h4>
            </div>

            <!-- CPD Points -->
            <div class="eau-form-field">
                <label class="eau-form-label"><?php _e('CPD Points', 'eau-system'); ?></label>
                <input type="number" name="<?php echo $p; ?>cpd_points" class="eau-form-input"
                       value="<?php echo esc_attr($meta['cpd_points']); ?>" min="0" step="0.5">
            </div>

            <!-- CPD Category -->
            <div class="eau-form-field">
                <label class="eau-form-label"><?php _e('CPD Category', 'eau-system'); ?></label>
                <?php wp_dropdown_categories(array(
                    'taxonomy'          => Config\TAXONOMY,
                    'name'              => $p . 'cpd_category',
                    'class'             => 'eau-form-select',
                    'show_option_none'  => __('Select category', 'eau-system'),
                    'option_none_value' => '',
                    'selected'          => $meta['cpd_category'],
                    'hide_empty'        => false,
                )); ?>
            </div>

            <!-- Visibility Section -->
            <div class="eau-form-field eau-form-field-span-2">
                <h4 class="eau-form-section-title"><?php _e('Visibility', 'eau-system'); ?></h4>
            </div>

            <!-- Visibility -->
            <div class="eau-form-field eau-form-field-span-2">
                <label class="eau-form-label"><?php _e('Event Visibility', 'eau-system'); ?></label>
                <select name="<?php echo $p; ?>visibility" class="eau-form-select">
                    <?php foreach (Config\get_visibility_options() as $key => $label) : ?>
                        <option value="<?php echo esc_attr($key); ?>" <?php selected($meta['visibility'], $key); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Require Approval -->
            <div class="eau-form-field">
                <label class="eau-checkbox-label">
                    <input type="checkbox" name="<?php echo $p; ?>require_approval" value="1"
                           <?php checked($meta['require_approval'], '1'); ?>>
                    <?php _e('Require approval', 'eau-system'); ?>
                </label>
            </div>

            <!-- Members Only -->
            <div class="eau-form-field">
                <label class="eau-checkbox-label">
                    <input type="checkbox" name="<?php echo $p; ?>members_only" value="1"
                           <?php checked($meta['members_only'], '1'); ?>>
                    <?php _e('Members only registration', 'eau-system'); ?>
                </label>
            </div>
        </div>
    </div>
    <?php
}
