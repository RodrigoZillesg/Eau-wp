/**
 * EAU Notifications System
 *
 * Provides Toast notifications and Confirm modals
 */

(function($) {
    'use strict';

    window.EauNotifications = {

        /**
         * Initialize toast container
         */
        init: function() {
            if (!$('.eau-toast-container').length) {
                $('body').append('<div class="eau-toast-container"></div>');
            }
        },

        /**
         * Show Toast notification
         *
         * @param {string} type - success, error, warning, info
         * @param {string} title - Toast title
         * @param {string} message - Toast message
         * @param {object|number} options - Options object { duration: ms } or duration in ms (default: 5000)
         * @returns {jQuery} Toast element (for update/close)
         */
        toast: function(type, title, message, options) {
            this.init();

            // Support both old signature (duration as number) and new (options object)
            let duration = 5000;
            if (typeof options === 'number') {
                duration = options;
            } else if (typeof options === 'object' && options !== null) {
                duration = options.duration !== undefined ? options.duration : 5000;
            }

            const icons = {
                success: 'check-circle',
                error: 'alert-circle',
                warning: 'alert-triangle',
                info: 'info'
            };

            const icon = icons[type] || icons.info;
            const toastId = 'eau-toast-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);

            const $toast = $(`
                <div class="eau-toast eau-toast-${type}" id="${toastId}">
                    <div class="eau-toast-icon">
                        <i data-lucide="${icon}"></i>
                    </div>
                    <div class="eau-toast-content">
                        <div class="eau-toast-title">${title}</div>
                        ${message ? `<div class="eau-toast-message">${message}</div>` : ''}
                    </div>
                    <button class="eau-toast-close" aria-label="Close">
                        <i data-lucide="x"></i>
                    </button>
                </div>
            `);

            $('.eau-toast-container').append($toast);

            // Initialize Lucide icons
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }

            // Close button
            $toast.find('.eau-toast-close').on('click', function() {
                EauNotifications.closeToast($toast);
            });

            // Auto close (only if duration > 0)
            if (duration > 0) {
                $toast.data('autoCloseTimer', setTimeout(function() {
                    EauNotifications.closeToast($toast);
                }, duration));
            }

            // Return toast element for update/close
            return $toast;
        },

        /**
         * Update an existing toast
         *
         * @param {jQuery} $toast - Toast element returned from toast()
         * @param {object} options - { title, message }
         */
        update: function($toast, options) {
            if (!$toast || !$toast.length) return;

            if (options.title) {
                $toast.find('.eau-toast-title').text(options.title);
            }
            if (options.message) {
                $toast.find('.eau-toast-message').text(options.message);
            }
        },

        /**
         * Close a specific toast
         *
         * @param {jQuery} $toast - Toast element to close
         */
        close: function($toast) {
            if (!$toast || !$toast.length) return;

            // Clear auto-close timer if exists
            const timer = $toast.data('autoCloseTimer');
            if (timer) {
                clearTimeout(timer);
            }

            this.closeToast($toast);
        },

        /**
         * Close toast
         */
        closeToast: function($toast) {
            $toast.addClass('eau-toast-hiding');
            setTimeout(function() {
                $toast.remove();
            }, 300);
        },

        /**
         * Show success toast
         * @returns {jQuery} Toast element
         */
        success: function(title, message, options) {
            return this.toast('success', title, message, options);
        },

        /**
         * Show error toast
         * @returns {jQuery} Toast element
         */
        error: function(title, message, options) {
            return this.toast('error', title, message, options);
        },

        /**
         * Show warning toast
         * @returns {jQuery} Toast element
         */
        warning: function(title, message, options) {
            return this.toast('warning', title, message, options);
        },

        /**
         * Show info toast
         * @returns {jQuery} Toast element
         */
        info: function(title, message, options) {
            return this.toast('info', title, message, options);
        },

        /**
         * Show confirm modal
         *
         * @param {object} options - { title, message, type, confirmText, cancelText, onConfirm, onCancel }
         */
        confirm: function(options) {
            const defaults = {
                title: 'Are you sure?',
                message: 'This action cannot be undone.',
                type: 'danger', // danger, warning, info
                confirmText: 'Confirm',
                cancelText: 'Cancel',
                onConfirm: function() {},
                onCancel: function() {}
            };

            options = $.extend({}, defaults, options);

            const icons = {
                danger: 'alert-triangle',
                warning: 'alert-circle',
                info: 'info'
            };

            const icon = icons[options.type] || icons.danger;

            // Create modal with very high z-index to appear above any other modal
            // NOTE: Do NOT use eau-modal class here as it has z-index !important that conflicts
            const modalId = 'eau-confirm-modal-' + Date.now();
            const $modal = $(`
                <div class="eau-confirm-overlay" id="${modalId}-overlay" style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:99999999;display:flex;align-items:center;justify-content:center;">
                    <div class="eau-confirm-modal" style="position:relative;z-index:1;background:#fff;border-radius:8px;box-shadow:0 20px 25px -5px rgba(0,0,0,0.1),0 10px 10px -5px rgba(0,0,0,0.04);max-width:400px;width:90%;">
                        <div class="eau-confirm-body">
                            <div class="eau-confirm-icon eau-confirm-icon-${options.type}">
                                <i data-lucide="${icon}"></i>
                            </div>
                            <h3 class="eau-confirm-title">${options.title}</h3>
                            <p class="eau-confirm-message">${options.message}</p>
                            <div class="eau-confirm-actions">
                                <button class="eau-btn eau-btn-secondary" data-action="cancel">
                                    ${options.cancelText}
                                </button>
                                <button class="eau-btn eau-btn-danger" data-action="confirm">
                                    ${options.confirmText}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `);

            $('body').append($modal);

            // Initialize Lucide icons
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }

            // Confirm button
            $modal.find('[data-action="confirm"]').on('click', function() {
                options.onConfirm();
                EauNotifications.closeConfirm($modal);
            });

            // Cancel button
            $modal.find('[data-action="cancel"]').on('click', function() {
                options.onCancel();
                EauNotifications.closeConfirm($modal);
            });

            // Close on overlay click
            $modal.on('click', function(e) {
                if ($(e.target).hasClass('eau-confirm-overlay')) {
                    options.onCancel();
                    EauNotifications.closeConfirm($modal);
                }
            });
        },

        /**
         * Close confirm modal
         */
        closeConfirm: function($modal) {
            $modal.fadeOut(200, function() {
                $modal.remove();
            });
        }
    };

})(jQuery);
