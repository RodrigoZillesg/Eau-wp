/**
 * Email Update Page JavaScript
 *
 * Handles the email update form with two steps:
 * 1. Choose between new email or keep current
 * 2. Verify new email with code
 *
 * @since 1.62.0
 */
(function($) {
    'use strict';

    const EauEmailUpdate = {
        // Elements
        $choiceForm: null,
        $verificationForm: null,
        $submitBtn: null,
        $verifyBtn: null,
        $resendBtn: null,
        $backBtn: null,

        // State
        resendTimer: null,
        resendCountdown: 60,

        /**
         * Initialize
         */
        init: function() {
            this.cacheElements();
            this.bindEvents();
            this.initLucide();
            this.startResendTimer();
        },

        /**
         * Cache DOM elements
         */
        cacheElements: function() {
            this.$choiceForm = $('#eau-email-update-form');
            this.$verificationForm = $('#eau-verification-form');
            this.$submitBtn = $('#submit-btn');
            this.$verifyBtn = $('#verify-btn');
            this.$resendBtn = $('#resend-code-btn');
            this.$backBtn = $('#back-btn');
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            const self = this;

            // Radio option change
            $(document).on('change', 'input[name="email_option"]', function() {
                self.handleOptionChange($(this).val());
            });

            // Choice form submit
            this.$choiceForm.on('submit', function(e) {
                e.preventDefault();
                self.handleChoiceSubmit();
            });

            // Verification form submit
            this.$verificationForm.on('submit', function(e) {
                e.preventDefault();
                self.handleVerifySubmit();
            });

            // Resend code
            this.$resendBtn.on('click', function() {
                self.handleResendCode();
            });

            // Back button
            this.$backBtn.on('click', function() {
                self.handleBack();
            });

            // Auto-format code input
            $(document).on('input', '#verification-code', function() {
                let value = $(this).val().replace(/\D/g, '');
                if (value.length > 6) {
                    value = value.substring(0, 6);
                }
                $(this).val(value);

                // Auto-submit when 6 digits entered
                if (value.length === 6) {
                    self.$verificationForm.submit();
                }
            });
        },

        /**
         * Initialize Lucide icons
         */
        initLucide: function() {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        },

        /**
         * Handle option change
         */
        handleOptionChange: function(option) {
            const $newEmailFields = $('.eau-new-email-fields');

            if (option === 'different') {
                $newEmailFields.slideDown(200);
                $('#new-email').prop('required', true);
                $('#confirm-email').prop('required', true);
            } else {
                $newEmailFields.slideUp(200);
                $('#new-email').prop('required', false).val('');
                $('#confirm-email').prop('required', false).val('');
            }

            this.$submitBtn.prop('disabled', false);
        },

        /**
         * Handle choice form submit
         */
        handleChoiceSubmit: function() {
            const self = this;
            const option = $('input[name="email_option"]:checked').val();

            if (!option) {
                EauNotifications.error(eau_email_update_vars.i18n.email_required);
                return;
            }

            // Validate if different email
            if (option === 'different') {
                const newEmail = $('#new-email').val().trim();
                const confirmEmail = $('#confirm-email').val().trim();
                const currentEmail = $('#current-email').text().trim();

                if (!newEmail) {
                    EauNotifications.error(eau_email_update_vars.i18n.email_required);
                    $('#new-email').focus();
                    return;
                }

                if (newEmail !== confirmEmail) {
                    EauNotifications.error(eau_email_update_vars.i18n.email_mismatch);
                    $('#confirm-email').focus();
                    return;
                }

                if (newEmail.toLowerCase() === currentEmail.toLowerCase()) {
                    EauNotifications.error(eau_email_update_vars.i18n.email_same);
                    $('#new-email').focus();
                    return;
                }
            }

            // Show loading
            self.setButtonLoading(self.$submitBtn, true);

            // Send request
            $.ajax({
                url: eau_email_update_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'eau_email_update_choice',
                    nonce: eau_email_update_vars.nonce,
                    email_option: option,
                    new_email: $('#new-email').val(),
                    confirm_email: $('#confirm-email').val(),
                },
                success: function(response) {
                    if (response.success) {
                        if (response.data.redirect) {
                            // Skip selected or verification not needed
                            EauNotifications.success(response.data.message);
                            setTimeout(function() {
                                window.location.href = response.data.redirect;
                            }, 1000);
                        } else if (response.data.step === 'verification') {
                            // Show verification step
                            EauNotifications.success(response.data.message);
                            setTimeout(function() {
                                window.location.reload();
                            }, 1000);
                        }
                    } else {
                        EauNotifications.error(response.data.message);
                        self.setButtonLoading(self.$submitBtn, false);
                    }
                },
                error: function() {
                    EauNotifications.error('An error occurred. Please try again.');
                    self.setButtonLoading(self.$submitBtn, false);
                }
            });
        },

        /**
         * Handle verify form submit
         */
        handleVerifySubmit: function() {
            const self = this;
            const code = $('#verification-code').val().trim();

            if (!code) {
                EauNotifications.error(eau_email_update_vars.i18n.code_required);
                $('#verification-code').focus();
                return;
            }

            if (!/^[0-9]{6}$/.test(code)) {
                EauNotifications.error(eau_email_update_vars.i18n.code_invalid);
                $('#verification-code').focus();
                return;
            }

            // Show loading
            self.setButtonLoading(self.$verifyBtn, true);

            // Send request
            $.ajax({
                url: eau_email_update_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'eau_email_verify_code',
                    nonce: eau_email_update_vars.nonce,
                    code: code,
                },
                success: function(response) {
                    if (response.success) {
                        EauNotifications.success(response.data.message);
                        setTimeout(function() {
                            window.location.href = response.data.redirect;
                        }, 1000);
                    } else {
                        EauNotifications.error(response.data.message);
                        self.setButtonLoading(self.$verifyBtn, false);
                        $('#verification-code').val('').focus();
                    }
                },
                error: function() {
                    EauNotifications.error('An error occurred. Please try again.');
                    self.setButtonLoading(self.$verifyBtn, false);
                }
            });
        },

        /**
         * Handle resend code
         */
        handleResendCode: function() {
            const self = this;

            if (self.$resendBtn.prop('disabled')) {
                return;
            }

            self.$resendBtn.prop('disabled', true);

            $.ajax({
                url: eau_email_update_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'eau_email_resend_code',
                    nonce: eau_email_update_vars.nonce,
                },
                success: function(response) {
                    if (response.success) {
                        EauNotifications.success(response.data.message);
                        self.resendCountdown = 60;
                        self.startResendTimer();
                    } else {
                        EauNotifications.error(response.data.message);
                        self.$resendBtn.prop('disabled', false);
                    }
                },
                error: function() {
                    EauNotifications.error('An error occurred. Please try again.');
                    self.$resendBtn.prop('disabled', false);
                }
            });
        },

        /**
         * Handle back button
         */
        handleBack: function() {
            const self = this;

            $.ajax({
                url: eau_email_update_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'eau_email_cancel_verification',
                    nonce: eau_email_update_vars.nonce,
                },
                success: function(response) {
                    if (response.success) {
                        window.location.reload();
                    } else {
                        EauNotifications.error(response.data.message);
                    }
                },
                error: function() {
                    EauNotifications.error('An error occurred. Please try again.');
                }
            });
        },

        /**
         * Start resend timer
         */
        startResendTimer: function() {
            const self = this;

            if (!self.$resendBtn.length) {
                return;
            }

            // Clear existing timer
            if (self.resendTimer) {
                clearInterval(self.resendTimer);
            }

            // Update button text
            self.updateResendButton();

            self.resendTimer = setInterval(function() {
                self.resendCountdown--;
                self.updateResendButton();

                if (self.resendCountdown <= 0) {
                    clearInterval(self.resendTimer);
                    self.$resendBtn.prop('disabled', false);
                    self.$resendBtn.text(eau_email_update_vars.i18n.resend_available);
                }
            }, 1000);
        },

        /**
         * Update resend button text
         */
        updateResendButton: function() {
            $('#resend-timer').text(this.resendCountdown);
        },

        /**
         * Set button loading state
         */
        setButtonLoading: function($btn, loading) {
            if (loading) {
                $btn.prop('disabled', true);
                $btn.addClass('is-loading');
                this.initLucide();
            } else {
                $btn.prop('disabled', false);
                $btn.removeClass('is-loading');
            }
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        EauEmailUpdate.init();
    });

})(jQuery);
