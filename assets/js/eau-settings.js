/**
 * Settings Page Controller
 *
 * @since 1.39.0
 */
(function($) {
    'use strict';

    const EauSettingsController = {

        // State
        settings: {
            activity_approval_mode: 'manual'
        },

        /**
         * Initialize controller
         */
        init: function() {
            this.bindEvents();

            // Initialize Lucide icons
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            const self = this;

            // Radio option click - update visual state
            $('.eau-radio-option').on('click', function() {
                const $group = $(this).closest('.eau-radio-group');
                $group.find('.eau-radio-option').removeClass('selected');
                $(this).addClass('selected');
            });

            // Save settings button
            $('#eau-save-settings-btn').on('click', function() {
                self.saveSettings();
            });
        },

        /**
         * Get current settings from form
         */
        getFormSettings: function() {
            return {
                activity_approval_mode: $('input[name="approval_mode"]:checked').val() || 'manual'
            };
        },

        /**
         * Save settings via AJAX
         */
        saveSettings: function() {
            const self = this;
            const $btn = $('#eau-save-settings-btn');
            const originalHtml = $btn.html();

            // Disable button and show loading
            $btn.prop('disabled', true).html('<i data-lucide="loader-2" class="eau-spin"></i> Saving...');

            // Re-init icons for loading spinner
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }

            const settings = this.getFormSettings();

            $.ajax({
                url: eauSettingsData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_save_settings',
                    nonce: eauSettingsData.nonce,
                    settings: settings
                },
                success: function(response) {
                    if (response.success) {
                        EauNotifications.success('Settings Saved', response.data.message || 'Your settings have been updated.');
                        self.settings = settings;
                    } else {
                        EauNotifications.error('Error', response.data.message || 'Failed to save settings.');
                    }
                },
                error: function() {
                    EauNotifications.error('Network Error', 'Please check your connection and try again.');
                },
                complete: function() {
                    // Re-enable button
                    $btn.prop('disabled', false).html(originalHtml);

                    // Re-init icons
                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                }
            });
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        EauSettingsController.init();
    });

})(jQuery);
