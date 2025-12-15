/**
 * Eau Public Registration JavaScript
 *
 * Handles multi-step form, validation, institution search, and registration submission.
 *
 * @since 1.49.0
 */

(function($) {
    'use strict';

    const EauPublicRegistration = {
        // Current step
        currentStep: 1,
        totalSteps: 3,

        // Search timeout for debouncing
        searchTimeout: null,

        // Selected institution
        selectedInstitution: null,

        // Form data cache
        formData: {},

        // Phone input instance (intl-tel-input)
        phoneIti: null,

        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.initLucideIcons();
            this.initPhoneInput();
        },

        /**
         * Initialize international phone input with DDI selector
         */
        initPhoneInput: function() {
            const phoneInput = document.querySelector('#reg-phone');
            if (phoneInput && typeof intlTelInput !== 'undefined') {
                this.phoneIti = intlTelInput(phoneInput, {
                    initialCountry: 'au',
                    preferredCountries: ['au', 'nz', 'gb', 'us'],
                    separateDialCode: true,
                    utilsScript: 'https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/utils.js',
                    formatOnDisplay: true,
                    nationalMode: false,
                    autoPlaceholder: 'aggressive'
                });

                // Update hidden field on input change
                phoneInput.addEventListener('input', () => {
                    this.updatePhoneHiddenField();
                });

                // Also update on country change
                phoneInput.addEventListener('countrychange', () => {
                    this.updatePhoneHiddenField();
                });
            }
        },

        /**
         * Update hidden phone field with full international number
         */
        updatePhoneHiddenField: function() {
            if (this.phoneIti) {
                const fullNumber = this.phoneIti.getNumber();
                $('#reg-phone-full').val(fullNumber);
            }
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            const self = this;

            // Step navigation
            $(document).on('click', '.eau-next-step', function() {
                const nextStep = $(this).data('next');
                self.goToStep(nextStep);
            });

            $(document).on('click', '.eau-prev-step', function() {
                const prevStep = $(this).data('prev');
                self.goToStep(prevStep);
            });

            // Form submission
            $('#eau-registration-form').on('submit', function(e) {
                e.preventDefault();
                self.submitRegistration();
            });

            // Institution search
            $('#reg-company-search').on('input', function() {
                self.handleInstitutionSearch($(this).val());
            });

            $('#reg-company-search').on('focus', function() {
                if ($(this).val().length >= 2) {
                    $('#reg-company-dropdown').addClass('active');
                }
            });

            // Close dropdown on outside click
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.eau-select-search-wrapper').length) {
                    $('#reg-company-dropdown').removeClass('active');
                }
            });

            // Institution dropdown item click
            $(document).on('click', '.eau-institution-dropdown-item', function() {
                self.selectInstitution($(this));
            });

            // Password toggle
            $('.eau-password-toggle').on('click', function() {
                self.togglePasswordVisibility($(this));
            });

            // Password strength
            $('#reg-password').on('input', function() {
                self.checkPasswordStrength($(this).val());
            });

            // Real-time email validation
            $('#reg-email').on('blur', function() {
                self.validateEmail($(this).val());
            });

            // Real-time password match
            $('#reg-password-confirm').on('input', function() {
                self.validatePasswordMatch();
            });

            // Clear field errors on input
            $('.eau-form-input, .eau-form-select').on('input change', function() {
                self.clearFieldError($(this));
            });
        },

        /**
         * Initialize Lucide icons
         */
        initLucideIcons: function() {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        },

        /**
         * Go to step
         *
         * @param {number} step Step number
         */
        goToStep: function(step) {
            // Validate current step before proceeding
            if (step > this.currentStep && !this.validateStep(this.currentStep)) {
                return;
            }

            // Hide all steps
            $('.eau-registration-step').hide();

            // Show target step
            $('#step-' + step).show();

            // Update step indicators
            this.updateStepIndicators(step);

            // Update current step
            this.currentStep = step;

            // Scroll to top of form
            $('html, body').animate({
                scrollTop: $('.eau-registration-form').offset().top - 100
            }, 300);

            // Re-init icons
            this.initLucideIcons();
        },

        /**
         * Update step indicators
         *
         * @param {number} activeStep Active step number
         */
        updateStepIndicators: function(activeStep) {
            $('.eau-step').each(function() {
                const stepNum = $(this).data('step');

                $(this).removeClass('active completed');

                if (stepNum < activeStep) {
                    $(this).addClass('completed');
                } else if (stepNum === activeStep) {
                    $(this).addClass('active');
                }
            });
        },

        /**
         * Validate step
         *
         * @param {number} step Step number
         * @return {boolean} Is valid
         */
        validateStep: function(step) {
            let isValid = true;
            const self = this;
            const $step = $('#step-' + step);

            // Clear previous errors
            $step.find('.eau-form-error').removeClass('visible').text('');
            $step.find('.eau-form-input, .eau-form-select').removeClass('error');

            // Validate required fields
            $step.find('[required]').each(function() {
                const $field = $(this);
                const value = $field.val().trim();

                if (!value) {
                    self.showFieldError($field, eauPublicRegistration.strings.validationRequired);
                    isValid = false;
                }
            });

            // Step-specific validation
            if (step === 1) {
                // Email validation
                const email = $('#reg-email').val().trim();
                if (email && !self.isValidEmail(email)) {
                    self.showFieldError($('#reg-email'), eauPublicRegistration.strings.validationEmail);
                    isValid = false;
                }
            }

            // Step 2 validation - Position, State, Country are validated by required attribute

            if (step === 3) {
                // Password validation
                const password = $('#reg-password').val();
                if (password.length < 8) {
                    self.showFieldError($('#reg-password'), eauPublicRegistration.strings.validationPasswordMin);
                    isValid = false;
                }

                // Password match
                const passwordConfirm = $('#reg-password-confirm').val();
                if (password !== passwordConfirm) {
                    self.showFieldError($('#reg-password-confirm'), eauPublicRegistration.strings.validationPasswordMatch);
                    isValid = false;
                }

                // Terms acceptance
                if (!$('#reg-terms').is(':checked')) {
                    $('#terms-error').addClass('visible').text(eauPublicRegistration.strings.validationTerms);
                    isValid = false;
                }
            }

            return isValid;
        },

        /**
         * Show field error
         *
         * @param {jQuery} $field Field element
         * @param {string} message Error message
         */
        showFieldError: function($field, message) {
            $field.addClass('error');
            $field.closest('.eau-form-field').find('.eau-form-error').addClass('visible').text(message);
        },

        /**
         * Clear field error
         *
         * @param {jQuery} $field Field element
         */
        clearFieldError: function($field) {
            $field.removeClass('error');
            $field.closest('.eau-form-field').find('.eau-form-error').removeClass('visible').text('');
        },

        /**
         * Handle institution search
         *
         * @param {string} query Search query
         */
        handleInstitutionSearch: function(query) {
            const self = this;
            const $dropdown = $('#reg-company-dropdown');

            // Clear timeout
            if (this.searchTimeout) {
                clearTimeout(this.searchTimeout);
            }

            // Minimum 2 characters
            if (query.length < 2) {
                $dropdown.removeClass('active').html('');
                return;
            }

            // Show loading
            $dropdown.addClass('active').html(
                '<div class="eau-institution-dropdown-loading">Searching...</div>'
            );

            // Debounce search
            this.searchTimeout = setTimeout(function() {
                self.searchInstitutions(query);
            }, 300);
        },

        /**
         * Search institutions via AJAX
         *
         * @param {string} query Search query
         */
        searchInstitutions: function(query) {
            const self = this;
            const $dropdown = $('#reg-company-dropdown');

            $.ajax({
                url: eauPublicRegistration.ajaxurl,
                type: 'POST',
                data: {
                    action: 'eau_search_institutions_public',
                    nonce: eauPublicRegistration.nonce,
                    query: query
                },
                success: function(response) {
                    if (response.success && response.data.institutions) {
                        self.renderInstitutionDropdown(response.data.institutions, query);
                    } else {
                        self.renderInstitutionDropdown([], query);
                    }
                },
                error: function() {
                    $dropdown.html(
                        '<div class="eau-institution-dropdown-empty">Error searching. Please try again.</div>'
                    );
                }
            });
        },

        /**
         * Render institution dropdown
         *
         * @param {array} institutions Institutions array
         * @param {string} query Original query
         */
        renderInstitutionDropdown: function(institutions, query) {
            const $dropdown = $('#reg-company-dropdown');
            let html = '';

            if (institutions.length > 0) {
                institutions.forEach(function(inst) {
                    html += '<div class="eau-institution-dropdown-item" data-id="' + inst.id + '" data-name="' + self.escapeHtml(inst.name) + '">';
                    html += self.escapeHtml(inst.name);
                    html += '</div>';
                });
            }

            // Always show "Add new" option
            html += '<div class="eau-institution-dropdown-item add-new" data-id="new" data-name="' + this.escapeHtml(query) + '">';
            html += '<i data-lucide="plus-circle"></i>';
            html += 'Add "' + this.escapeHtml(query) + '" as new institution';
            html += '</div>';

            $dropdown.html(html);
            this.initLucideIcons();
        },

        /**
         * Select institution
         *
         * @param {jQuery} $item Dropdown item
         */
        selectInstitution: function($item) {
            const id = $item.data('id');
            const name = $item.data('name');

            // Update fields
            $('#reg-company-search').val(name);
            $('#reg-company-id').val(id === 'new' ? '' : id);
            $('#reg-company-name').val(name);

            // Store selection
            this.selectedInstitution = {
                id: id,
                name: name
            };

            // Hide dropdown
            $('#reg-company-dropdown').removeClass('active');

            // Clear error
            this.clearFieldError($('#reg-company-search'));
        },

        /**
         * Toggle password visibility
         *
         * @param {jQuery} $button Toggle button
         */
        togglePasswordVisibility: function($button) {
            const $input = $button.siblings('input');
            const type = $input.attr('type') === 'password' ? 'text' : 'password';
            $input.attr('type', type);

            // Update icon
            const iconName = type === 'password' ? 'eye' : 'eye-off';
            $button.html('<i data-lucide="' + iconName + '"></i>');
            this.initLucideIcons();
        },

        /**
         * Check password strength
         *
         * @param {string} password Password
         */
        checkPasswordStrength: function(password) {
            const $strength = $('#password-strength');
            let strength = 0;

            if (password.length >= 8) strength++;
            if (password.length >= 12) strength++;
            if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
            if (/\d/.test(password)) strength++;
            if (/[^a-zA-Z0-9]/.test(password)) strength++;

            $strength.removeClass('weak fair good strong');

            if (password.length === 0) {
                $strength.find('.eau-strength-text').text('');
            } else if (strength <= 1) {
                $strength.addClass('weak');
                $strength.find('.eau-strength-text').text(eauPublicRegistration.strings.strengthWeak);
            } else if (strength === 2) {
                $strength.addClass('fair');
                $strength.find('.eau-strength-text').text(eauPublicRegistration.strings.strengthFair);
            } else if (strength === 3) {
                $strength.addClass('good');
                $strength.find('.eau-strength-text').text(eauPublicRegistration.strings.strengthGood);
            } else {
                $strength.addClass('strong');
                $strength.find('.eau-strength-text').text(eauPublicRegistration.strings.strengthStrong);
            }
        },

        /**
         * Validate email format
         *
         * @param {string} email Email
         * @return {boolean} Is valid
         */
        isValidEmail: function(email) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        },

        /**
         * Validate email (format + availability)
         *
         * @param {string} email Email
         */
        validateEmail: function(email) {
            const self = this;
            const $field = $('#reg-email');

            if (!email || !self.isValidEmail(email)) {
                return;
            }

            // Check if email exists
            $.ajax({
                url: eauPublicRegistration.ajaxurl,
                type: 'POST',
                data: {
                    action: 'eau_check_email_exists',
                    nonce: eauPublicRegistration.nonce,
                    email: email
                },
                success: function(response) {
                    if (response.success && response.data.exists) {
                        self.showFieldError($field, eauPublicRegistration.strings.validationEmailExists);
                    }
                }
            });
        },

        /**
         * Validate password match
         */
        validatePasswordMatch: function() {
            const password = $('#reg-password').val();
            const confirm = $('#reg-password-confirm').val();

            if (confirm && password !== confirm) {
                this.showFieldError($('#reg-password-confirm'), eauPublicRegistration.strings.validationPasswordMatch);
            } else {
                this.clearFieldError($('#reg-password-confirm'));
            }
        },

        /**
         * Submit registration
         */
        submitRegistration: function() {
            const self = this;

            // Validate final step
            if (!this.validateStep(3)) {
                return;
            }

            // Update phone hidden field with full international number before submit
            this.updatePhoneHiddenField();

            // Get form data
            const formData = new FormData($('#eau-registration-form')[0]);
            formData.append('action', 'eau_process_registration');

            // Get reCAPTCHA token if configured
            if (eauPublicRegistration.recaptchaSiteKey && typeof grecaptcha !== 'undefined') {
                grecaptcha.ready(function() {
                    grecaptcha.execute(eauPublicRegistration.recaptchaSiteKey, {action: 'register'}).then(function(token) {
                        formData.append('recaptcha_token', token);
                        self.sendRegistration(formData);
                    });
                });
            } else {
                this.sendRegistration(formData);
            }
        },

        /**
         * Send registration AJAX request
         *
         * @param {FormData} formData Form data
         */
        sendRegistration: function(formData) {
            const self = this;
            const $submitBtn = $('#submit-registration');
            const originalText = $submitBtn.html();

            // Disable button
            $submitBtn.prop('disabled', true).html(
                '<i data-lucide="loader-2" class="eau-spin"></i> ' + eauPublicRegistration.strings.submitting
            );
            this.initLucideIcons();

            $.ajax({
                url: eauPublicRegistration.ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        // Show success message
                        self.showSuccess(response.data);
                    } else {
                        // Show error
                        self.showError(response.data.message || eauPublicRegistration.strings.errorGeneric);
                        $submitBtn.prop('disabled', false).html(originalText);
                        self.initLucideIcons();
                    }
                },
                error: function() {
                    self.showError(eauPublicRegistration.strings.errorGeneric);
                    $submitBtn.prop('disabled', false).html(originalText);
                    self.initLucideIcons();
                }
            });
        },

        /**
         * Show success message
         *
         * @param {object} data Response data
         */
        showSuccess: function(data) {
            // Hide form steps
            $('.eau-registration-step').hide();
            $('.eau-registration-steps').hide();

            // Show success
            $('#registration-success').show();

            // Update links if provided
            if (data.dashboard_url) {
                $('.eau-success-actions a[href*="dashboard"]').attr('href', data.dashboard_url);
            }

            this.initLucideIcons();

            // Scroll to success
            $('html, body').animate({
                scrollTop: $('#registration-success').offset().top - 100
            }, 300);
        },

        /**
         * Show error message
         *
         * @param {string} message Error message
         */
        showError: function(message) {
            // Use EauNotifications if available
            if (typeof EauNotifications !== 'undefined') {
                EauNotifications.error(message);
            } else {
                alert(message);
            }
        },

        /**
         * Escape HTML
         *
         * @param {string} str String to escape
         * @return {string} Escaped string
         */
        escapeHtml: function(str) {
            if (!str) return '';
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }
    };

    // Expose escapeHtml for renderInstitutionDropdown
    const self = EauPublicRegistration;

    // Initialize on document ready
    $(document).ready(function() {
        EauPublicRegistration.init();
    });

    // Expose globally
    window.EauPublicRegistration = EauPublicRegistration;

})(jQuery);

// Add spin animation for loader
const style = document.createElement('style');
style.textContent = `
    .eau-spin {
        animation: eau-spin 1s linear infinite;
    }
    @keyframes eau-spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
`;
document.head.appendChild(style);
