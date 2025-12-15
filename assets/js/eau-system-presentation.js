/**
 * EAU System Presentation - Public Presentation Page
 * Shortcode: [eau_system_presentation]
 */
(function($) {
    'use strict';

    const EauPresentation = {
        currentLang: 'en', // English as default

        init: function() {
            this.bindEvents();
            this.initLucideIcons();
            this.setInitialLanguage();
        },

        bindEvents: function() {
            const self = this;

            // Language tab switching
            $(document).on('click', '.eau-pres-lang-tab', function(e) {
                e.preventDefault();
                const lang = $(this).data('lang');
                self.switchLanguage(lang);
            });

            // Lightbox - open
            $(document).on('click', '.eau-pres-page-image-wrapper', function(e) {
                e.preventDefault();
                const $img = $(this).find('.eau-pres-page-image');
                const src = $img.attr('src');
                const alt = $img.attr('alt') || '';
                self.openLightbox(src, alt);
            });

            // Lightbox - close on overlay click (not on content)
            $(document).on('click', '.eau-pres-lightbox-overlay', function(e) {
                self.closeLightbox();
            });

            // Lightbox - close button
            $(document).on('click', '.eau-pres-lightbox-close', function(e) {
                e.preventDefault();
                e.stopPropagation();
                self.closeLightbox();
            });

            // Keyboard - close lightbox with Escape
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') {
                    self.closeLightbox();
                }
            });

            // Smooth scroll for TOC links
            $(document).on('click', '.eau-pres-toc-link', function(e) {
                e.preventDefault();
                const target = $(this).attr('href');
                if (target && $(target).length) {
                    $('html, body').animate({
                        scrollTop: $(target).offset().top - 100
                    }, 500);
                }
            });

            // Scroll to top button - visibility
            $(window).on('scroll', function() {
                self.toggleScrollTopButton();
            });

            // Scroll to top button - click
            $(document).on('click', '.eau-pres-scroll-top', function(e) {
                e.preventDefault();
                $('html, body').animate({ scrollTop: 0 }, 500);
            });
        },

        toggleScrollTopButton: function() {
            const $btn = $('.eau-pres-scroll-top');
            if ($(window).scrollTop() > 400) {
                $btn.addClass('visible');
            } else {
                $btn.removeClass('visible');
            }
        },

        setInitialLanguage: function() {
            // Check URL parameter first
            const urlParams = new URLSearchParams(window.location.search);
            const langParam = urlParams.get('lang');

            if (langParam && (langParam === 'pt' || langParam === 'en')) {
                this.switchLanguage(langParam);
            } else {
                // Default to English
                this.switchLanguage('en');
            }
        },

        switchLanguage: function(lang) {
            this.currentLang = lang;

            // Update tabs
            $('.eau-pres-lang-tab').removeClass('active');
            $(`.eau-pres-lang-tab[data-lang="${lang}"]`).addClass('active');

            // Update content visibility
            $('.eau-pres-content').removeClass('active');
            $(`.eau-pres-content[data-lang="${lang}"]`).addClass('active');

            // Update URL without reload
            const url = new URL(window.location.href);
            url.searchParams.set('lang', lang);
            window.history.replaceState({}, '', url);

            // Reinit Lucide icons for the new content
            this.initLucideIcons();
        },

        openLightbox: function(src, caption) {
            // Create lightbox if it doesn't exist
            if (!$('.eau-pres-lightbox').length) {
                const lightboxHtml = `
                    <div class="eau-pres-lightbox">
                        <div class="eau-pres-lightbox-overlay"></div>
                        <div class="eau-pres-lightbox-container">
                            <button class="eau-pres-lightbox-close" aria-label="Close">
                                <i data-lucide="x"></i>
                            </button>
                            <div class="eau-pres-lightbox-scroll">
                                <img class="eau-pres-lightbox-image" src="" alt="">
                            </div>
                            <div class="eau-pres-lightbox-caption"></div>
                        </div>
                    </div>
                `;
                $('body').append(lightboxHtml);
                this.initLucideIcons();
            }

            // Set image and caption
            $('.eau-pres-lightbox-image').attr('src', src).attr('alt', caption);
            $('.eau-pres-lightbox-caption').text(caption);

            // Show lightbox
            $('.eau-pres-lightbox').addClass('active');

            // Prevent body scroll
            $('body').css('overflow', 'hidden');
        },

        closeLightbox: function() {
            $('.eau-pres-lightbox').removeClass('active');
            $('body').css('overflow', '');
        },

        initLucideIcons: function() {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        if ($('.eau-presentation').length) {
            EauPresentation.init();
            // Ensure icons are rendered after all content is loaded
            $(window).on('load', function() {
                EauPresentation.initLucideIcons();
            });
        }
    });

})(jQuery);
