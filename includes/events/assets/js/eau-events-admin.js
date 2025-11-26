/**
 * Eau Events CPT - Admin JavaScript
 *
 * @package EauSystem
 * @since 1.28.1
 */

(function($) {
    'use strict';

    console.log('Eau Events Admin JS loaded');

    // Fallback for localized data
    var eauEventsAdminData = window.eauEventsAdmin || {
        mediaTitle: 'Select Event Image',
        mediaButton: 'Use this image'
    };

    /**
     * Events Admin Controller
     */
    const EauEventsAdmin = {

        /**
         * Media frame instance
         */
        mediaFrame: null,

        /**
         * Initialize
         */
        init: function() {
            this.bindTabEvents();
            this.bindImageUpload();
            this.bindEventTypeChange();
            this.initConditionalFields();
        },

        /**
         * Bind tab navigation events
         */
        bindTabEvents: function() {
            const self = this;

            $(document).on('click', '.eau-tab-btn', function(e) {
                e.preventDefault();

                const $btn = $(this);
                const tabId = $btn.data('tab');

                // Update active states
                $('.eau-tab-btn').removeClass('active');
                $btn.addClass('active');

                // Show/hide panels
                $('.eau-tab-panel').removeClass('active');
                $('#tab-' + tabId).addClass('active');

                // Save active tab to session storage
                if (typeof sessionStorage !== 'undefined') {
                    sessionStorage.setItem('eau_event_active_tab', tabId);
                }
            });

            // Restore active tab from session storage
            if (typeof sessionStorage !== 'undefined') {
                const savedTab = sessionStorage.getItem('eau_event_active_tab');
                if (savedTab && $('.eau-tab-btn[data-tab="' + savedTab + '"]').length) {
                    $('.eau-tab-btn[data-tab="' + savedTab + '"]').trigger('click');
                }
            }
        },

        /**
         * Bind image upload functionality
         */
        bindImageUpload: function() {
            const self = this;

            // Click on preview or upload button
            $(document).on('click', '.eau-image-preview, .eau-upload-image-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                self.openMediaFrame();
            });

            // Remove image
            $(document).on('click', '.eau-remove-image-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                self.removeImage();
            });
        },

        /**
         * Open WordPress media frame
         */
        openMediaFrame: function() {
            const self = this;

            // Check if wp.media is available
            if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
                alert('Media library not available. Please refresh the page.');
                return;
            }

            // If frame exists, open it
            if (this.mediaFrame) {
                this.mediaFrame.open();
                return;
            }

            // Create new frame
            this.mediaFrame = wp.media({
                title: eauEventsAdminData.mediaTitle,
                button: {
                    text: eauEventsAdminData.mediaButton
                },
                multiple: false,
                library: {
                    type: 'image'
                }
            });

            // Handle selection
            this.mediaFrame.on('select', function() {
                const attachment = self.mediaFrame.state().get('selection').first().toJSON();
                self.setImage(attachment);
            });

            this.mediaFrame.open();
        },

        /**
         * Set image preview
         */
        setImage: function(attachment) {
            const imageUrl = attachment.sizes && attachment.sizes.medium
                ? attachment.sizes.medium.url
                : attachment.url;

            $('#evt_image_id').val(attachment.id);
            $('#evt_image_preview').html('<img src="' + imageUrl + '" alt="">');
            $('.eau-remove-image-btn').show();
        },

        /**
         * Remove image
         */
        removeImage: function() {
            $('#evt_image_id').val('');
            $('#evt_image_preview').html(
                '<div class="eau-image-placeholder">' +
                '<span class="dashicons dashicons-format-image"></span>' +
                '<p>Click to upload event image</p>' +
                '<small>PNG, JPG, WebP up to 5MB</small>' +
                '</div>'
            );
            $('.eau-remove-image-btn').hide();
        },

        /**
         * Bind event type change
         */
        bindEventTypeChange: function() {
            const self = this;

            $(document).on('change', 'input[name="evt_event_type"]', function() {
                self.updateLocationFields();
            });
        },

        /**
         * Update location fields based on event type
         */
        updateLocationFields: function() {
            const eventType = $('input[name="evt_event_type"]:checked').val();

            // Location fields
            const $locationFields = $('.eau-location-field');
            const $virtualFields = $('.eau-virtual-field');

            switch (eventType) {
                case 'virtual':
                    $locationFields.addClass('hidden');
                    $virtualFields.removeClass('hidden');
                    break;

                case 'in-person':
                    $locationFields.removeClass('hidden');
                    $virtualFields.addClass('hidden');
                    break;

                case 'hybrid':
                default:
                    $locationFields.removeClass('hidden');
                    $virtualFields.removeClass('hidden');
                    break;
            }
        },

        /**
         * Initialize conditional fields on page load
         */
        initConditionalFields: function() {
            this.updateLocationFields();
        },

        /**
         * Validate form before submit
         */
        validateForm: function() {
            let isValid = true;
            const errors = [];

            // Check required fields
            const startDate = $('#evt_start_datetime').val();
            const endDate = $('#evt_end_datetime').val();

            if (!startDate) {
                errors.push('Start Date & Time is required');
                isValid = false;
            }

            if (!endDate) {
                errors.push('End Date & Time is required');
                isValid = false;
            }

            // Validate end date is after start date
            if (startDate && endDate) {
                const start = new Date(startDate);
                const end = new Date(endDate);

                if (end <= start) {
                    errors.push('End Date must be after Start Date');
                    isValid = false;
                }
            }

            // Validate early bird date
            const earlyBirdDate = $('#evt_early_bird_end_date').val();
            const earlyBirdPrice = $('#evt_early_bird_price').val();

            if (earlyBirdPrice && earlyBirdPrice > 0 && !earlyBirdDate) {
                errors.push('Early Bird End Date is required when Early Bird Price is set');
                isValid = false;
            }

            if (earlyBirdDate && startDate) {
                const earlyBird = new Date(earlyBirdDate);
                const start = new Date(startDate);

                if (earlyBird >= start) {
                    errors.push('Early Bird End Date must be before Event Start Date');
                    isValid = false;
                }
            }

            // Display errors if any
            if (!isValid) {
                alert(errors.join('\n'));
            }

            return isValid;
        }
    };

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        console.log('Document ready - checking for .eau-event-metabox');
        console.log('Found elements:', $('.eau-event-metabox').length);

        // Only initialize if we're on an event edit page
        if ($('.eau-event-metabox').length > 0) {
            console.log('Initializing EauEventsAdmin');
            EauEventsAdmin.init();

            // Bind form validation
            $('form#post').on('submit', function(e) {
                if (!EauEventsAdmin.validateForm()) {
                    e.preventDefault();
                    return false;
                }
            });
        }
    });

})(jQuery);
