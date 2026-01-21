/**
 * Checkout Page Controller
 *
 * @since 1.70.0
 */
(function($) {
    'use strict';

    const EauCheckoutController = {

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

            // Pay Now button
            $('#eau-pay-now-btn').on('click', function() {
                self.initiatePayment($(this));
            });
        },

        /**
         * Initiate payment
         *
         * @param {jQuery} $btn Button element
         */
        initiatePayment: function($btn) {
            const self = this;
            const type = $btn.data('type');
            const itemId = $btn.data('item-id');
            const amount = $btn.data('amount');

            // Validate
            if (!type || !itemId || !amount) {
                EauNotifications.error('Invalid checkout data');
                return;
            }

            // Disable button and show loading
            $btn.prop('disabled', true);
            const originalHtml = $btn.html();
            $btn.html('<i data-lucide="loader-2" class="eau-spin"></i> Processing...');

            // Re-create icons for the loading spinner
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }

            // Call AJAX to create hosted payment session
            $.ajax({
                url: eauCheckoutData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_initiate_payment',
                    nonce: eauCheckoutData.nonce,
                    type: type,
                    item_id: itemId,
                    amount: amount
                },
                success: function(response) {
                    if (response.success && response.data.redirect_url) {
                        // Redirect to Fat Zebra hosted payment page
                        EauNotifications.success('Redirecting to payment...');
                        window.location.href = response.data.redirect_url;
                    } else {
                        EauNotifications.error(response.data.message || 'Failed to initiate payment');
                        self.resetButton($btn, originalHtml);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Payment initiation failed:', error);
                    EauNotifications.error('Failed to connect to payment server. Please try again.');
                    self.resetButton($btn, originalHtml);
                }
            });
        },

        /**
         * Reset button to original state
         *
         * @param {jQuery} $btn Button element
         * @param {string} originalHtml Original button HTML
         */
        resetButton: function($btn, originalHtml) {
            $btn.prop('disabled', false);
            $btn.html(originalHtml);

            // Re-create icons
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        },

        /**
         * Check payment status
         *
         * @param {string} type    Payment type
         * @param {number} itemId  Item ID
         * @param {function} callback Callback function
         */
        checkPaymentStatus: function(type, itemId, callback) {
            $.ajax({
                url: eauCheckoutData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_check_payment_status',
                    nonce: eauCheckoutData.nonce,
                    type: type,
                    item_id: itemId
                },
                success: function(response) {
                    if (response.success) {
                        callback(null, response.data.status);
                    } else {
                        callback(response.data.message || 'Failed to check status');
                    }
                },
                error: function() {
                    callback('Failed to check payment status');
                }
            });
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        if ($('.eau-checkout-container').length > 0) {
            EauCheckoutController.init();
        }
    });

    // Make controller globally available
    window.EauCheckoutController = EauCheckoutController;

})(jQuery);
