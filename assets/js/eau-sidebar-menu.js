/**
 * Eau Sidebar Menu JavaScript
 *
 * Controla a abertura/fechamento do menu lateral
 * Usa JavaScript nativo para evitar conflitos com outros plugins
 *
 * @since 1.56.0
 * @updated 1.68.12
 */

(function() {
    'use strict';

    var EauSidebarMenu = {
        // Elements
        hamburger: null,
        sidebar: null,
        overlay: null,
        closeBtn: null,

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
            if (!this.hamburger) {
                return;
            }

            this.bindEvents();
            this.initialized = true;
            console.log('EauSidebarMenu initialized successfully');
        },

        /**
         * Cache DOM elements
         */
        cacheElements: function() {
            this.hamburger = document.getElementById('eau-hamburger-btn');
            this.sidebar = document.getElementById('eau-sidebar');
            this.overlay = document.getElementById('eau-sidebar-overlay');
            this.closeBtn = document.getElementById('eau-sidebar-close');
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            var self = this;

            // Hamburger button click - use native addEventListener
            if (this.hamburger) {
                this.hamburger.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    self.toggle();
                }, false);
            }

            // Close button click
            if (this.closeBtn) {
                this.closeBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    self.close();
                }, false);
            }

            // Overlay click
            if (this.overlay) {
                this.overlay.addEventListener('click', function(e) {
                    e.preventDefault();
                    self.close();
                }, false);
            }

            // Close on Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && self.isOpen) {
                    self.close();
                }
            }, false);

            // Close when clicking a link (for SPA-like behavior)
            if (this.sidebar) {
                this.sidebar.addEventListener('click', function(e) {
                    var target = e.target.closest('.eau-sidebar-link');
                    if (target) {
                        // Small delay to allow navigation
                        setTimeout(function() {
                            self.close();
                        }, 100);
                    }
                }, false);
            }
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
            if (this.sidebar) this.sidebar.classList.add('active');
            if (this.overlay) this.overlay.classList.add('active');
            document.body.classList.add('eau-sidebar-open');

            // Update ARIA
            if (this.hamburger) this.hamburger.setAttribute('aria-expanded', 'true');

            // Focus management - focus first link after opening
            var self = this;
            setTimeout(function() {
                var firstLink = self.sidebar ? self.sidebar.querySelector('.eau-sidebar-link') : null;
                if (firstLink) firstLink.focus();
            }, 300);
        },

        /**
         * Close the sidebar
         */
        close: function() {
            this.isOpen = false;

            // Remove active classes
            if (this.sidebar) this.sidebar.classList.remove('active');
            if (this.overlay) this.overlay.classList.remove('active');
            document.body.classList.remove('eau-sidebar-open');

            // Update ARIA
            if (this.hamburger) this.hamburger.setAttribute('aria-expanded', 'false');

            // Return focus to hamburger button
            if (this.hamburger) this.hamburger.focus();
        }
    };

    // Initialize function that can be called multiple times safely
    function initSidebarMenu() {
        try {
            EauSidebarMenu.init();
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

    // Also try to initialize after a small delay (backup for edge cases)
    setTimeout(initSidebarMenu, 100);

    // Expose to global scope for external access
    window.EauSidebarMenu = EauSidebarMenu;

})();
