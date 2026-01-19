<?php
/**
 * Events Admin - Tab: CPD & Settings
 *
 * Renderiza a aba de configurações CPD, categorias e materiais do evento.
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
 * @since  1.28.0
 * @param  array $meta Array com valores dos meta fields
 * @return void
 */
function render_settings($meta) {
    $p = Config\META_PREFIX;
    ?>
    <div class="eau-tab-panel" id="tab-settings">
        <div class="eau-form-grid">
            <!-- Event Category Section -->
            <div class="eau-form-field eau-form-field-span-2">
                <h4 class="eau-form-section-title"><?php _e('Event Category', 'eau-system'); ?></h4>
            </div>

            <!-- Event Category -->
            <div class="eau-form-field eau-form-field-span-2">
                <label class="eau-form-label"><?php _e('Event Category', 'eau-system'); ?></label>
                <select name="<?php echo $p; ?>event_category" id="eau-edit-event_category" class="eau-form-select">
                    <option value=""><?php _e('Select category', 'eau-system'); ?></option>
                    <?php foreach (Config\get_event_categories_from_db() as $cat) : ?>
                        <option value="<?php echo esc_attr($cat['id']); ?>" <?php selected($meta['event_category'], $cat['id']); ?>>
                            <?php echo esc_html($cat['category_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="eau-form-hint"><?php _e('Categorize this event for filtering and organization', 'eau-system'); ?></p>
            </div>

            <!-- CPD Section -->
            <div class="eau-form-field eau-form-field-span-2">
                <h4 class="eau-form-section-title"><?php _e('CPD Settings', 'eau-system'); ?></h4>
            </div>

            <!-- CPD Points -->
            <div class="eau-form-field">
                <label class="eau-form-label"><?php _e('CPD Points', 'eau-system'); ?></label>
                <input type="number" name="<?php echo $p; ?>cpd_points" id="eau-cpd-points" class="eau-form-input"
                       value="<?php echo esc_attr($meta['cpd_points']); ?>" min="0" step="0.5">
            </div>

            <!-- CPD Category -->
            <div class="eau-form-field">
                <label class="eau-form-label"><?php _e('CPD Category', 'eau-system'); ?></label>
                <select name="<?php echo $p; ?>cpd_category" id="eau-cpd-category" class="eau-form-select">
                    <option value="" data-points="0"><?php _e('Select category', 'eau-system'); ?></option>
                    <?php foreach (Config\get_cpd_categories_from_db() as $cat) : ?>
                        <option value="<?php echo esc_attr($cat['id']); ?>" data-points="<?php echo esc_attr($cat['points_per_hour']); ?>" <?php selected($meta['cpd_category'], $cat['id']); ?>>
                            <?php echo esc_html($cat['category_name']); ?> (<?php echo esc_html($cat['points_per_hour']); ?> pts/hr)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Materials & Recording Section -->
            <div class="eau-form-field eau-form-field-span-2">
                <h4 class="eau-form-section-title"><?php _e('Materials & Recording', 'eau-system'); ?></h4>
                <p class="eau-form-hint"><?php _e('Add supplementary materials and recording link for registered attendees', 'eau-system'); ?></p>
            </div>

            <!-- Recording URL -->
            <div class="eau-form-field eau-form-field-span-2">
                <label class="eau-form-label"><?php _e('Recording URL', 'eau-system'); ?></label>
                <input type="url" name="<?php echo $p; ?>recording_url" id="eau-edit-recording_url" class="eau-form-input"
                       value="<?php echo esc_attr($meta['recording_url']); ?>" placeholder="https://...">
                <p class="eau-form-hint"><?php _e('Link to the event recording (YouTube, Vimeo, etc.). Only visible to registered attendees.', 'eau-system'); ?></p>
            </div>

            <!-- Supplementary Materials -->
            <div class="eau-form-field eau-form-field-span-2">
                <label class="eau-form-label"><?php _e('Supplementary Materials', 'eau-system'); ?></label>
                <?php
                echo \EauSystem\Components\Eau_Media_Upload::field(
                    'eau-edit-materials',
                    $p . 'materials',
                    $meta['materials'],
                    array(
                        'type' => 'both',
                        'allowed_types' => 'application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation,application/zip',
                        'allowed_extensions' => 'pdf,doc,docx,ppt,pptx,zip',
                        'placeholder' => __('Upload or enter URL for materials...', 'eau-system'),
                    )
                );
                ?>
                <p class="eau-form-hint"><?php _e('Upload documents or enter a link to supplementary materials (PDF, DOC, PPT, ZIP). Only visible to registered attendees.', 'eau-system'); ?></p>
            </div>

            <!-- Other Settings Section -->
            <div class="eau-form-field eau-form-field-span-2">
                <h4 class="eau-form-section-title"><?php _e('Other Settings', 'eau-system'); ?></h4>
            </div>

            <!-- Require Approval -->
            <div class="eau-form-field eau-form-field-span-2">
                <label class="eau-checkbox-label">
                    <input type="checkbox" name="<?php echo $p; ?>require_approval" value="1"
                           <?php checked($meta['require_approval'], '1'); ?>>
                    <?php _e('Require approval for registrations', 'eau-system'); ?>
                </label>
                <p class="eau-form-hint"><?php _e('If enabled, registrations will need manual approval before confirmation', 'eau-system'); ?></p>
            </div>
        </div>
    </div>
    <?php
}
