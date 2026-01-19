/**
 * Eau Sidebar Menu JavaScript
 *
 * Controla a abertura/fechamento do menu lateral
 *
 * @since 1.56.0
 * @updated 1.68.11
 */

(function($) {
    'use strict';

    const EauSidebarMenu = {
        // Elements
        $hamburger: null,
        $sidebar: null,
        $overlay: null,
        $closeBtn: null,
        $body: null,

        // State
        isOpen: false,
        initialized: false,

        /**
         * Initialize the sidebar menu
         */
        init: function() {
            // Prevent double initialization
            if (this.initialized) {
                return;
            }

            this.cacheElements();

            // Only proceed if elements exist
            if (this.$hamburger.length === 0) {
                return;
            }

            this.bindEvents();
            this.initialized = true;
        },

        /**
         * Cache DOM elements
         */
        cacheElements: function() {
            this.$hamburger = $('#eau-hamburger-btn');
            this.$sidebar = $('#eau-sidebar');
            this.$overlay = $('#eau-sidebar-overlay');
            this.$closeBtn = $('#eau-sidebar-close');
            this.$body = $('body');
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            const self = this;

            // Hamburger button click
            this.$hamburger.on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                self.toggle();
            });

            // Close button click
            this.$closeBtn.on('click', function(e) {
                e.preventDefault();
                self.close();
            });

            // Overlay click
            this.$overlay.on('click', function(e) {
                e.preventDefault();
                self.close();
            });

            // Close on Escape key
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && self.isOpen) {
                    self.close();
                }
            });

            // Prevent scroll on sidebar when at limits
            this.$sidebar.on('touchmove', function(e) {
                e.stopPropagation();
            });

            // Close when clicking a link (for SPA-like behavior)
            this.$sidebar.on('click', '.eau-sidebar-link', function() {
                // Small delay to allow navigation
                setTimeout(function() {
                    self.close();
                }, 100);
            });

            // Handle window resize
            $(window).on('resize', this.debounce(function() {
                // Optional: close sidebar on resize to desktop
                // if (window.innerWidth > 1024 && self.isOpen) {
                //     self.close();
                // }
            }, 250));
        },

        /**
         * Toggle sidebar open/close
         */
        toggle: function() {
            if (this.isOpen) {
                this.close();
            } else {
                this.open();
            }
        },

        /**
         * Open the sidebar
         */
        open: function() {
            this.isOpen = true;

            // Add active classes
            this.$sidebar.addClass('active');
            this.$overlay.addClass('active');
            this.$body.addClass('eau-sidebar-open');

            // Update ARIA
            this.$hamburger.attr('aria-expanded', 'true');

            // Focus management - focus first link after opening
            setTimeout(() => {
                this.$sidebar.find('.eau-sidebar-link').first().focus();
            }, 300);

            // Trap focus inside sidebar
            this.trapFocus();
        },

        /**
         * Close the sidebar
         */
        close: function() {
            this.isOpen = false;

            // Remove active classes
            this.$sidebar.removeClass('active');
            this.$overlay.removeClass('active');
            this.$body.removeClass('eau-sidebar-open');

            // Update ARIA
            this.$hamburger.attr('aria-expanded', 'false');

            // Return focus to hamburger button
            this.$hamburger.focus();

            // Release focus trap
            this.releaseFocusTrap();
        },

        /**
         * Trap focus inside sidebar when open
         */
        trapFocus: function() {
            const self = this;
            const $sidebar = this.$sidebar;
            const $focusableElements = $sidebar.find('a, button, input, select, textarea, [tabindex]:not([tabindex="-1"])');
            const $firstFocusable = $focusableElements.first();
            const $lastFocusable = $focusableElements.last();

            $sidebar.on('keydown.focusTrap', function(e) {
                if (e.key === 'Tab') {
                    if (e.shiftKey) {
                        // Shift + Tab
                        if (document.activeElement === $firstFocusable[0]) {
                            e.preventDefault();
                            $lastFocusable.focus();
                        }
                    } else {
                        // Tab
                        if (document.activeElement === $lastFocusable[0]) {
                            e.preventDefault();
                            $firstFocusable.focus();
                        }
                    }
                }
            });
        },

        /**
         * Release focus trap
         */
        releaseFocusTrap: function() {
            this.$sidebar.off('keydown.focusTrap');
        },

        /**
         * Debounce utility function
         *
         * @param {Function} func Function to debounce
         * @param {number} wait Wait time in ms
         * @returns {Function} Debounced function
         */
        debounce: function(func, wait) {
            let timeout;
            return function(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func.apply(this, args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        EauSidebarMenu.init();
    });

    // Also initialize if document is already complete (for late-loaded scripts)
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        setTimeout(function() {
            EauSidebarMenu.init();
        }, 1);
    }

    // Expose to global scope for external access
    window.EauSidebarMenu = EauSidebarMenu;

})(jQuery);
