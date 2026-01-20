/**
 * Eau Sidebar Menu JavaScript
 *
 * Controla a abertura/fechamento do menu lateral
 * Usa Event Delegation para funcionar mesmo com elementos carregados dinamicamente
 *
 * @since 1.56.0
 * @updated 1.68.13
 */

(function() {
    'use strict';

    var EauSidebarMenu = {
        // State
        isOpen: false,
        initialized: false,
        retryCount: 0,
        maxRetries: 20,

        /**
         * Get the first visible element matching selector
         */
        getVisibleElement: function(selector) {
            var elements = document.querySelectorAll(selector);
            for (var i = 0; i < elements.length; i++) {
                var el = elements[i];
                var rect = el.getBoundingClientRect();
                var style = window.getComputedStyle(el);
                if (rect.width > 0 && rect.height > 0 && style.display !== 'none' && style.visibility !== 'hidden') {
                    return el;
                }
            }
            // Fallback to first element if none are "visible"
            return elements.length > 0 ? elements[0] : null;
        },

        /**
         * Get hamburger button
         */
        getHamburger: function() {
            return this.getVisibleElement('#eau-hamburger-btn');
        },

        /**
         * Get sidebar
         */
        getSidebar: function() {
            return this.getVisibleElement('#eau-sidebar');
        },

        /**
         * Get overlay
         */
        getOverlay: function() {
            return this.getVisibleElement('#eau-sidebar-overlay');
        },

        /**
         * Get close button
         */
        getCloseBtn: function() {
            return this.getVisibleElement('#eau-sidebar-close');
        },

        /**
         * Initialize the sidebar menu
         */
        init: function() {
            // Prevent double initialization
            if (this.initialized) {
                return true;
            }

            var hamburger = this.getHamburger();
            var sidebar = this.getSidebar();

            // Check if essential elements exist
            if (!hamburger || !sidebar) {
                return false;
            }

            this.bindEvents();
            this.initialized = true;
            console.log('EauSidebarMenu initialized successfully');
            return true;
        },

        /**
         * Bind event handlers using Event Delegation
         */
        bindEvents: function() {
            var self = this;

            // Use event delegation on document for all clicks
            // This ensures it works even if elements are added dynamically
            document.addEventListener('click', function(e) {
                // Check if clicked on hamburger button or its children
                var hamburger = e.target.closest('#eau-hamburger-btn');
                if (hamburger) {
                    e.preventDefault();
                    e.stopPropagation();
                    self.toggle();
                    return;
                }

                // Check if clicked on close button
                var closeBtn = e.target.closest('#eau-sidebar-close');
                if (closeBtn) {
                    e.preventDefault();
                    self.close();
                    return;
                }

                // Check if clicked on overlay
                var overlay = e.target.closest('#eau-sidebar-overlay');
                if (overlay && e.target === overlay) {
                    e.preventDefault();
                    self.close();
                    return;
                }

                // Check if clicked on a sidebar link
                var sidebarLink = e.target.closest('.eau-sidebar-link');
                if (sidebarLink && self.isOpen) {
                    // Small delay to allow navigation
                    setTimeout(function() {
                        self.close();
                    }, 100);
                }
            }, true); // Use capture phase to ensure we get the event first

            // Close on Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && self.isOpen) {
                    self.close();
                }
            }, false);
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
            var sidebar = this.getSidebar();
            var overlay = this.getOverlay();
            var hamburger = this.getHamburger();

            this.isOpen = true;

            // Add active classes to ALL matching elements (in case of duplicates)
            document.querySelectorAll('#eau-sidebar').forEach(function(el) {
                el.classList.add('active');
            });
            document.querySelectorAll('#eau-sidebar-overlay').forEach(function(el) {
                el.classList.add('active');
            });
            document.body.classList.add('eau-sidebar-open');

            // Update ARIA on all hamburger buttons
            document.querySelectorAll('#eau-hamburger-btn').forEach(function(el) {
                el.setAttribute('aria-expanded', 'true');
            });

            // Focus management - focus first link after opening
            var self = this;
            setTimeout(function() {
                var firstLink = sidebar ? sidebar.querySelector('.eau-sidebar-link') : null;
                if (firstLink) firstLink.focus();
            }, 300);
        },

        /**
         * Close the sidebar
         */
        close: function() {
            var hamburger = this.getHamburger();

            this.isOpen = false;

            // Remove active classes from ALL matching elements (in case of duplicates)
            document.querySelectorAll('#eau-sidebar').forEach(function(el) {
                el.classList.remove('active');
            });
            document.querySelectorAll('#eau-sidebar-overlay').forEach(function(el) {
                el.classList.remove('active');
            });
            document.body.classList.remove('eau-sidebar-open');

            // Update ARIA on all hamburger buttons
            document.querySelectorAll('#eau-hamburger-btn').forEach(function(el) {
                el.setAttribute('aria-expanded', 'false');
            });

            // Return focus to hamburger button
            if (hamburger) hamburger.focus();
        }
    };

    // Initialize function with retry mechanism
    function initSidebarMenu() {
        try {
            var success = EauSidebarMenu.init();
            if (!success && EauSidebarMenu.retryCount < EauSidebarMenu.maxRetries) {
                EauSidebarMenu.retryCount++;
                // Exponential backoff: 100ms, 200ms, 400ms, etc. (max 2 seconds)
                var delay = Math.min(100 * Math.pow(1.5, EauSidebarMenu.retryCount), 2000);
                setTimeout(initSidebarMenu, delay);
            }
        } catch (e) {
            console.error('EauSidebarMenu init error:', e);
        }
    }

    // Initialize on DOMContentLoaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSidebarMenu);
    } else {
        // DOM already loaded, init immediately
        initSidebarMenu();
    }

    // Expose to global scope for external access
    window.EauSidebarMenu = EauSidebarMenu;

})();
