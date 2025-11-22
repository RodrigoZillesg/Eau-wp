/**
 * Eau Members Settings Admin Page
 */

(function($) {
    'use strict';

    const EauMembersSettings = {

        /**
         * Initialize
         */
        init: function() {
            this.initSortable();
            this.bindEvents();
        },

        /**
         * Initialize sortable (drag & drop)
         */
        initSortable: function() {
            $('.eau-sortable-tbody').sortable({
                handle: '.drag-handle',
                placeholder: 'ui-sortable-placeholder',
                cursor: 'move',
                opacity: 0.8,
                helper: function(e, tr) {
                    const $originals = tr.children();
                    const $helper = tr.clone();
                    $helper.children().each(function(index) {
                        $(this).width($originals.eq(index).width());
                    });
                    return $helper;
                },
                update: function(event, ui) {
                    EauMembersSettings.updateOrder();
                }
            });
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            // Toggle enabled checkbox
            $(document).on('change', '.field-enabled input[type="checkbox"]', function() {
                const $row = $(this).closest('.eau-field-row');
                const isEnabled = $(this).is(':checked');

                // Enable/disable required and readonly checkboxes
                $row.find('.field-required input[type="checkbox"]').prop('disabled', !isEnabled);
                $row.find('.field-readonly input[type="checkbox"]').prop('disabled', !isEnabled);

                // If disabled, uncheck required and readonly
                if (!isEnabled) {
                    $row.find('.field-required input[type="checkbox"]').prop('checked', false);
                    $row.find('.field-readonly input[type="checkbox"]').prop('checked', false);
                }
            });

            // Confirm before leaving if changes are unsaved
            let formChanged = false;

            $('form').on('change', 'input, select, textarea', function() {
                formChanged = true;
            });

            $('form').on('submit', function() {
                formChanged = false;
            });

            $(window).on('beforeunload', function() {
                if (formChanged) {
                    return 'You have unsaved changes. Are you sure you want to leave?';
                }
            });
        },

        /**
         * Update order after drag & drop
         */
        updateOrder: function() {
            $('.eau-sortable-tbody').each(function() {
                let order = 1;
                $(this).find('.eau-field-row').each(function() {
                    $(this).find('.field-order-input').val(order);
                    order++;
                });
            });
        }
    };

    /**
     * Init on document ready
     */
    $(document).ready(function() {
        EauMembersSettings.init();
    });

})(jQuery);
