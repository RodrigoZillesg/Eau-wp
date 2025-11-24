<?php
/**
 * Events Admin - Tab: Basic Info
 *
 * Renderiza a aba de informações básicas do evento.
 * Inclui campos de descrição, datas, timezone e imagem.
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
 * Renderiza o conteúdo da tab Basic Info
 *
 * Campos incluídos:
 * - Short Description (text, max 500 chars)
 * - Full Description (WYSIWYG)
 * - Start Date & Time (datetime-local, required)
 * - End Date & Time (datetime-local, required)
 * - Timezone (select)
 * - Event Image (media upload)
 *
 * @since  1.28.0
 * @param  array $meta Array com valores dos meta fields
 * @return void
 */
function render_basic_info($meta) {
    $p = Config\META_PREFIX;
    ?>
    <div class="eau-tab-panel active" id="tab-basic-info">
        <div class="eau-form-grid">
            <!-- Short Description -->
            <div class="eau-form-field eau-form-field-span-2">
                <label class="eau-form-label"><?php _e('Short Description', 'eau-system'); ?></label>
                <input type="text" name="<?php echo $p; ?>short_description" class="eau-form-input"
                       value="<?php echo esc_attr($meta['short_description']); ?>" maxlength="500">
                <p class="eau-form-hint"><?php _e('Max 500 characters', 'eau-system'); ?></p>
            </div>

            <!-- Full Description -->
            <div class="eau-form-field eau-form-field-span-2">
                <label class="eau-form-label"><?php _e('Full Description', 'eau-system'); ?></label>
                <?php wp_editor($meta['full_description'], $p . 'full_description', array(
                    'textarea_name' => $p . 'full_description',
                    'textarea_rows' => 8,
                    'media_buttons' => true,
                )); ?>
            </div>

            <!-- Start Date -->
            <div class="eau-form-field">
                <label class="eau-form-label"><?php _e('Start Date & Time', 'eau-system'); ?> <span class="required">*</span></label>
                <input type="datetime-local" name="<?php echo $p; ?>start_datetime" id="<?php echo $p; ?>start_datetime"
                       class="eau-form-input" value="<?php echo esc_attr($meta['start_datetime']); ?>" required>
            </div>

            <!-- End Date -->
            <div class="eau-form-field">
                <label class="eau-form-label"><?php _e('End Date & Time', 'eau-system'); ?> <span class="required">*</span></label>
                <input type="datetime-local" name="<?php echo $p; ?>end_datetime" id="<?php echo $p; ?>end_datetime"
                       class="eau-form-input" value="<?php echo esc_attr($meta['end_datetime']); ?>" required>
            </div>

            <!-- Timezone -->
            <div class="eau-form-field eau-form-field-span-2">
                <label class="eau-form-label"><?php _e('Timezone', 'eau-system'); ?></label>
                <select name="<?php echo $p; ?>timezone" class="eau-form-select">
                    <?php foreach (Config\get_timezones() as $key => $label) : ?>
                        <option value="<?php echo esc_attr($key); ?>" <?php selected($meta['timezone'], $key); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Image -->
            <div class="eau-form-field eau-form-field-span-2">
                <label class="eau-form-label"><?php _e('Event Image', 'eau-system'); ?></label>
                <div class="eau-image-upload-wrapper">
                    <div class="eau-image-preview" id="evt_image_preview">
                        <?php if (!empty($meta['image_id'])) : ?>
                            <?php echo wp_get_attachment_image($meta['image_id'], 'medium'); ?>
                        <?php else : ?>
                            <div class="eau-image-placeholder">
                                <span class="dashicons dashicons-format-image"></span>
                                <p><?php _e('Click to upload', 'eau-system'); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <input type="hidden" name="<?php echo $p; ?>image_id" id="<?php echo $p; ?>image_id"
                           value="<?php echo esc_attr($meta['image_id']); ?>">
                    <div class="eau-image-actions">
                        <button type="button" class="button eau-upload-image-btn"><?php _e('Choose', 'eau-system'); ?></button>
                        <button type="button" class="button eau-remove-image-btn" <?php echo empty($meta['image_id']) ? 'style="display:none;"' : ''; ?>>
                            <?php _e('Remove', 'eau-system'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}
