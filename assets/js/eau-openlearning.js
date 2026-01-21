/**
 * OpenLearning Integration - SSO Handler
 *
 * Handles:
 * - SSO launch via LTI form submission
 * - Course access buttons
 *
 * @since 1.41.0
 * @updated 1.42.0 - Removed AJAX course loading (now server-side rendered)
 */
(function($) {
    'use strict';

    const EauOpenLearning = {
        // Configuration
        config: null,

        /**
         * Initialize the module
         */
        init: function() {
            // Check if config is available
            if (typeof eauOpenLearning === 'undefined') {
                console.warn('EauOpenLearning: Configuration not found');
                return;
            }

            this.config = eauOpenLearning;

            // Bind events
            this.bindEvents();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            const self = this;

            // Course access buttons (delegated)
            $(document).on('click', '.eau-course-access-btn', function(e) {
                e.preventDefault();
                const courseId = $(this).data('course-id');
                self.launchSSO(courseId, $(this));
            });

            // Course enroll buttons - redirect to checkout (v1.71.0)
            $(document).on('click', '.eau-course-enroll-btn', function(e) {
                e.preventDefault();
                const courseId = $(this).data('course-id');
                self.enrollCourse(courseId, $(this));
            });
        },

        /**
         * Enroll in a paid course (v1.71.0)
         *
         * Creates a purchase record and redirects to checkout
         *
         * @param {string} courseId - Course ID
         * @param {jQuery} $button - Button element
         */
        enrollCourse: function(courseId, $button) {
            const self = this;

            // Set loading state
            $button.addClass('eau-loading').prop('disabled', true);
            const originalHtml = $button.html();
            $button.html('<i data-lucide="loader-2" class="eau-spin"></i> Processing...');

            // Recreate icons
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_openlearning_enroll_course',
                    nonce: this.config.nonce,
                    course_id: courseId
                },
                success: function(response) {
                    if (response.success) {
                        if (response.data.requires_payment && response.data.checkout_url) {
                            // Redirect to checkout
                            self.showNotification(response.data.message || 'Redirecting to checkout...', 'info');
                            setTimeout(function() {
                                window.location.href = response.data.checkout_url;
                            }, 500);
                        } else if (response.data.already_purchased) {
                            // Already purchased - reload to show access button
                            self.showNotification(response.data.message || 'You already have access to this course.', 'info');
                            $button.removeClass('eau-loading').prop('disabled', false);
                            $button.html(originalHtml);
                            if (typeof lucide !== 'undefined') {
                                lucide.createIcons();
                            }
                            location.reload();
                        } else {
                            // Free course - message to user
                            self.showNotification(response.data.message || 'This course is free!', 'success');
                            $button.removeClass('eau-loading').prop('disabled', false);
                            $button.html(originalHtml);
                            if (typeof lucide !== 'undefined') {
                                lucide.createIcons();
                            }
                        }
                    } else {
                        // Error
                        $button.removeClass('eau-loading').prop('disabled', false);
                        $button.html(originalHtml);
                        if (typeof lucide !== 'undefined') {
                            lucide.createIcons();
                        }
                        self.showNotification(response.data.message || 'Failed to process enrollment', 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('EauOpenLearning: Enrollment failed', error);
                    $button.removeClass('eau-loading').prop('disabled', false);
                    $button.html(originalHtml);
                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                    self.showNotification('Failed to connect to server. Please try again.', 'error');
                }
            });
        },

        /**
         * Launch SSO to OpenLearning
         *
         * Strategy: SSO authenticates on institution homepage, then redirects to course_url
         * The context_id doesn't work correctly with MongoDB ObjectIds
         *
         * @param {string|null} courseId - Specific course ID or null for homepage
         * @param {jQuery|null} $button - Button element (for loading state)
         */
        launchSSO: function(courseId, $button) {
            const self = this;

            // Set loading state on button - keep it until process completes
            if ($button) {
                $button.addClass('eau-loading').prop('disabled', true);
            }

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_openlearning_sso_launch',
                    nonce: this.config.nonce,
                    course_id: courseId || ''
                },
                success: function(response) {
                    if (response.success && response.data.launch_data) {
                        // Store redirect URL if provided (for course-specific access)
                        const redirectUrl = response.data.redirect_url || null;
                        // Pass button to submitLTIForm so it can clear loading when done
                        self.submitLTIForm(response.data.launch_data, redirectUrl, $button);
                    } else {
                        // Error - clear loading state
                        if ($button) {
                            $button.removeClass('eau-loading').prop('disabled', false);
                        }

                        const errorMsg = response.data && response.data.message
                            ? response.data.message
                            : self.config.i18n.launchError;

                        self.showNotification(errorMsg, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    // Error - clear loading state
                    if ($button) {
                        $button.removeClass('eau-loading').prop('disabled', false);
                    }

                    console.error('EauOpenLearning: SSO launch failed', error);
                    self.showNotification(self.config.i18n.launchError, 'error');
                }
            });
        },

        /**
         * Submit LTI form to OpenLearning
         *
         * Creates a hidden form with OAuth/LTI parameters and submits it
         * to open OpenLearning in a new tab with auto-login.
         * If redirectUrl is provided, navigates to the course URL after SSO authentication.
         *
         * @param {object} launchData - Contains url, method, and params
         * @param {string|null} redirectUrl - URL to redirect after SSO (for course access)
         * @param {jQuery|null} $button - Button element to clear loading state when done
         */
        submitLTIForm: function(launchData, redirectUrl, $button) {
            const self = this;

            // Remove any existing form
            $('#eau-openlearning-sso-form').remove();

            // Open a new window first (must be done synchronously due to popup blockers)
            const targetWindow = window.open('about:blank', 'openlearning_sso');

            if (!targetWindow) {
                // Popup blocked - clear loading state and show error
                if ($button) {
                    $button.removeClass('eau-loading').prop('disabled', false);
                }
                this.showNotification('Please allow popups to access OpenLearning courses', 'error');
                return;
            }

            // Create form targeting the new window
            const $form = $('<form>', {
                id: 'eau-openlearning-sso-form',
                method: launchData.method || 'POST',
                action: launchData.url,
                target: 'openlearning_sso'
            });

            // Add hidden fields for all LTI parameters
            if (launchData.params) {
                Object.keys(launchData.params).forEach(function(key) {
                    $form.append($('<input>', {
                        type: 'hidden',
                        name: key,
                        value: launchData.params[key]
                    }));
                });
            }

            // Append to body and submit
            $('body').append($form);
            $form.submit();

            // If we have a redirect URL, navigate to it after SSO completes
            if (redirectUrl) {
                setTimeout(function() {
                    try {
                        // Navigate the window to the course URL
                        targetWindow.location.href = redirectUrl;
                    } catch (e) {
                        // Cross-origin error - window already navigated away
                        console.log('EauOpenLearning: SSO completed, course should be loading');
                    }

                    // Clear loading state after redirect
                    if ($button) {
                        $button.removeClass('eau-loading').prop('disabled', false);
                    }
                }, 2000); // Wait 2 seconds for SSO to complete
            } else {
                // No redirect - clear loading state after a short delay
                setTimeout(function() {
                    if ($button) {
                        $button.removeClass('eau-loading').prop('disabled', false);
                    }
                }, 1000);
            }

            // Clean up form
            setTimeout(function() {
                $form.remove();
            }, 1000);
        },

        /**
         * Show notification to user
         *
         * @param {string} message
         * @param {string} type - 'success', 'error', 'warning', 'info'
         */
        showNotification: function(message, type) {
            // Use EauNotifications if available
            if (typeof EauNotifications !== 'undefined') {
                EauNotifications.show(message, type);
                return;
            }

            // Fallback to alert for errors
            if (type === 'error') {
                alert(message);
            }
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        EauOpenLearning.init();
    });

})(jQuery);
