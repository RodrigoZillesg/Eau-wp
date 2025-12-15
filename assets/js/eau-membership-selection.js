/**
 * Eau Membership Selection JavaScript
 *
 * Handles membership cards, application modal, form validation,
 * and submission.
 *
 * @since 1.49.0
 */

(function($) {
    'use strict';

    const EauMembershipSelection = {
        // Current application step
        currentStep: 1,
        totalSteps: 4,

        // Selected membership type
        selectedType: null,

        // Membership types data
        membershipTypes: {},

        // Uploaded files
        uploadedFiles: [],

        // Phone input instance
        phoneIti: null,

        // Is this an institution type membership?
        isInstitutionType: true,

        /**
         * Initialize
         */
        init: function() {
            this.loadMembershipTypes();
            this.bindEvents();
            this.initLucideIcons();
            // Note: Phone input is initialized when application modal opens (not on page load)
            // because intl-tel-input doesn't work well with hidden elements
        },

        /**
         * Load membership types from embedded JSON
         */
        loadMembershipTypes: function() {
            const dataEl = document.getElementById('membership-types-data');
            if (dataEl) {
                try {
                    this.membershipTypes = JSON.parse(dataEl.textContent);
                } catch (e) {
                    console.error('Failed to parse membership types data:', e);
                }
            }
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
         * Initialize international phone input with DDI selector
         */
        initPhoneInput: function() {
            const phoneInput = document.querySelector('#app-phone');
            if (!phoneInput || typeof intlTelInput === 'undefined') {
                return;
            }

            // Destroy existing instance if any
            if (this.phoneIti) {
                this.phoneIti.destroy();
                this.phoneIti = null;
            }

            // Create new instance
            this.phoneIti = intlTelInput(phoneInput, {
                initialCountry: 'au',
                preferredCountries: ['au', 'nz', 'gb', 'us'],
                separateDialCode: true,
                utilsScript: 'https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/utils.js',
                formatOnDisplay: true,
                nationalMode: false,
                autoPlaceholder: 'aggressive'
            });

            const self = this;

            // Update hidden field on input change
            phoneInput.addEventListener('input', function() {
                self.updatePhoneHiddenField();
            });

            // Also update on country change
            phoneInput.addEventListener('countrychange', function() {
                self.updatePhoneHiddenField();
            });

            // Set initial value from hidden field if exists
            const hiddenPhone = document.querySelector('#app-phone-full');
            if (hiddenPhone && hiddenPhone.value && hiddenPhone.value.trim() !== '') {
                this.phoneIti.setNumber(hiddenPhone.value);
                // Sync hidden field after setting number
                this.updatePhoneHiddenField();
            }
        },

        /**
         * Update hidden phone field with full international number
         */
        updatePhoneHiddenField: function() {
            if (this.phoneIti) {
                const fullNumber = this.phoneIti.getNumber();
                const hiddenField = document.querySelector('#app-phone-full');
                if (hiddenField) {
                    hiddenField.value = fullNumber;
                }
            }
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            const self = this;

            // Apply button click
            $(document).on('click', '.eau-apply-btn', function() {
                const type = $(this).data('type');
                self.openApplicationModal(type);
            });

            // Details button click
            $(document).on('click', '.eau-details-btn', function() {
                const type = $(this).data('type');
                self.openDetailsModal(type);
            });

            // Apply from details modal
            $(document).on('click', '.eau-apply-from-details', function() {
                self.closeModal('#details-modal');
                setTimeout(function() {
                    self.openApplicationModal(self.selectedType);
                }, 300);
            });

            // Modal close
            $(document).on('click', '.eau-modal-close, .eau-modal-close-btn, .eau-modal-overlay', function() {
                const modal = $(this).closest('.eau-modal');
                self.closeModal('#' + modal.attr('id'));
            });

            // Next step
            $(document).on('click', '.eau-next-step', function() {
                self.nextStep();
            });

            // Previous step
            $(document).on('click', '.eau-prev-step', function() {
                self.prevStep();
            });

            // Submit application
            $(document).on('click', '.eau-submit-application', function() {
                self.submitApplication();
            });

            // File upload change (supporting docs)
            $(document).on('change', '#app-supporting-docs', function(e) {
                self.handleFileUpload(e.target.files);
            });

            // Required document file upload change
            $(document).on('change', '#required-documents-list input[type="file"]', function(e) {
                const $input = $(this);
                const index = $input.attr('id').replace('required-doc-', '');
                const $preview = $('#preview-required-' + index);
                const $container = $input.closest('.eau-document-item');

                if (this.files && this.files.length > 0) {
                    const file = this.files[0];
                    const maxSize = 10 * 1024 * 1024; // 10MB

                    if (file.size > maxSize) {
                        self.showError('File "' + file.name + '" is too large. Maximum size is 10MB.');
                        $input.val('');
                        return;
                    }

                    // Show preview
                    $preview.html(`
                        <div class="eau-file-preview">
                            <i data-lucide="file-check"></i>
                            <span class="eau-file-name">${self.escapeHtml(file.name)}</span>
                            <span class="eau-file-size">(${self.formatFileSize(file.size)})</span>
                            <button type="button" class="eau-remove-required-file" data-index="${index}">
                                <i data-lucide="x"></i>
                            </button>
                        </div>
                    `);

                    // Mark as valid
                    $container.css('border-color', '#10b981');
                    $input.closest('.eau-upload-area').find('.eau-upload-placeholder').hide();

                    self.initLucideIcons();
                }
            });

            // Remove required document file
            $(document).on('click', '.eau-remove-required-file', function() {
                const index = $(this).data('index');
                const $input = $('#required-doc-' + index);
                const $preview = $('#preview-required-' + index);
                const $container = $input.closest('.eau-document-item');

                $input.val('');
                $preview.empty();
                $container.css('border-color', '');
                $input.closest('.eau-upload-area').find('.eau-upload-placeholder').show();
            });

            // Remove uploaded file
            $(document).on('click', '.eau-remove-file', function() {
                const index = $(this).data('index');
                self.removeUploadedFile(index);
            });

            // CRICOS sites change (for fee calculation)
            $(document).on('change', '#app-cricos-sites', function() {
                self.updateFeeCalculation();
            });

            // Clear errors on input
            $(document).on('input change', '.eau-form-input, .eau-form-select, .eau-form-textarea', function() {
                self.clearFieldError($(this));
            });

            // Escape key to close modal
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') {
                    self.closeModal('#application-modal');
                    self.closeModal('#details-modal');
                }
            });
        },

        /**
         * Open application modal
         *
         * @param {string} type Membership type key
         */
        openApplicationModal: function(type) {
            if (!this.membershipTypes[type]) {
                console.error('Unknown membership type:', type);
                return;
            }

            this.selectedType = type;
            const typeData = this.membershipTypes[type];

            // Check if this is an institution type
            this.isInstitutionType = typeData.is_institution_type !== false;

            // Set total steps (3 for individual, 4 for institution)
            this.totalSteps = this.isInstitutionType ? 4 : 3;

            // Reset form and steps
            this.resetApplicationForm();
            this.currentStep = 1;

            // Show/hide Organization step indicator based on type
            if (this.isInstitutionType) {
                $('.eau-app-step[data-step="2"]').show();
                // Reset step numbers for institution types
                this.updateStepNumbers(4);
            } else {
                $('.eau-app-step[data-step="2"]').hide();
                // Update step numbers for individual types (1, 2, 3 instead of 1, 3, 4)
                this.updateStepNumbers(3);
            }

            this.updateStepDisplay();

            // Set type
            $('#application-type').val(type);
            $('#modal-title').text(typeData.type_label + ' Application');

            // Show/hide CRICOS fields (only for institution types)
            if (this.isInstitutionType && typeData.show_cricos_fields) {
                $('.eau-cricos-field').show();
                $('#app-cricos-number').prop('required', true);
            } else {
                $('.eau-cricos-field').hide();
                $('#app-cricos-number').prop('required', false);
            }

            // Remove required from organization fields for individual members
            if (!this.isInstitutionType) {
                $('#app-company-name').prop('required', false);
                $('#app-state').prop('required', false);
            } else {
                $('#app-company-name').prop('required', true);
                $('#app-state').prop('required', true);
            }

            // Setup required documents
            this.setupRequiredDocuments(typeData.required_documents || []);

            // Show modal with flex display for centering
            this.showModal('#application-modal');

            // Initialize phone input after modal is visible (intl-tel-input needs visible element)
            setTimeout(() => {
                this.initPhoneInput();
            }, 300);

            this.initLucideIcons();
        },

        /**
         * Update step numbers for display
         *
         * @param {number} totalSteps Total number of steps
         */
        updateStepNumbers: function(totalSteps) {
            if (totalSteps === 3) {
                // Individual member: hide step 2, renumber 3->2, 4->3
                $('.eau-app-step[data-step="3"] .eau-step-number').text('2');
                $('.eau-app-step[data-step="4"] .eau-step-number').text('3');
            } else {
                // Institution: reset to normal 1, 2, 3, 4
                $('.eau-app-step[data-step="2"] .eau-step-number').text('2');
                $('.eau-app-step[data-step="3"] .eau-step-number').text('3');
                $('.eau-app-step[data-step="4"] .eau-step-number').text('4');
            }
        },

        /**
         * Open details modal
         *
         * @param {string} type Membership type key
         */
        openDetailsModal: function(type) {
            if (!this.membershipTypes[type]) {
                console.error('Unknown membership type:', type);
                return;
            }

            this.selectedType = type;
            const typeData = this.membershipTypes[type];

            // Update title
            $('#details-modal-title').text(typeData.type_label);

            // Build content
            let content = '';

            // Description
            if (typeData.type_description) {
                content += '<div class="eau-details-section">';
                content += '<p>' + this.escapeHtml(typeData.type_description) + '</p>';
                content += '</div>';
            }

            // Fee
            content += '<div class="eau-details-section">';
            content += '<h4><i data-lucide="dollar-sign"></i> Fee</h4>';
            if (typeData.fee_is_variable) {
                content += '<p>Variable - calculated based on number of CRICOS sites.</p>';
                content += '<p><strong>Base fee:</strong> $3,000 + $500 per additional site</p>';
            } else {
                content += '<p><strong>$' + parseFloat(typeData.fee_amount).toLocaleString() + '</strong> ' + typeData.fee_currency;
                content += typeData.fee_includes_gst ? ' (inc. GST)' : ' (ex. GST)';
                content += ' per year</p>';

                if (typeData.fee_member_college && typeData.fee_member_college != typeData.fee_amount) {
                    content += '<p><strong>Member College Rate:</strong> $' + parseFloat(typeData.fee_member_college).toLocaleString() + '</p>';
                }
            }
            content += '</div>';

            // Benefits
            if (typeData.benefits && typeData.benefits.length > 0) {
                content += '<div class="eau-details-section eau-details-benefits">';
                content += '<h4><i data-lucide="check-circle"></i> Benefits</h4>';
                content += '<ul>';
                typeData.benefits.forEach(function(benefit) {
                    content += '<li><i data-lucide="check"></i> ' + this.escapeHtml(benefit) + '</li>';
                }, this);
                content += '</ul>';
                content += '</div>';
            }

            // Requirements
            if (typeData.requirements && typeData.requirements.length > 0) {
                content += '<div class="eau-details-section eau-details-requirements">';
                content += '<h4><i data-lucide="alert-circle"></i> Requirements</h4>';
                content += '<ul>';
                typeData.requirements.forEach(function(req) {
                    content += '<li><i data-lucide="alert-circle"></i> ' + this.escapeHtml(req) + '</li>';
                }, this);
                content += '</ul>';
                content += '</div>';
            }

            // Required Documents
            if (typeData.required_documents && typeData.required_documents.length > 0) {
                content += '<div class="eau-details-section eau-details-documents">';
                content += '<h4><i data-lucide="file-text"></i> Required Documents</h4>';
                content += '<ul>';
                typeData.required_documents.forEach(function(doc) {
                    content += '<li><i data-lucide="file"></i> ' + this.escapeHtml(doc) + '</li>';
                }, this);
                content += '</ul>';
                content += '</div>';
            }

            // Duration
            content += '<div class="eau-details-section">';
            content += '<h4><i data-lucide="calendar"></i> Duration</h4>';
            content += '<p>Membership is valid for ' + typeData.max_duration_months + ' months from approval date.</p>';
            content += '</div>';

            $('#details-modal-content').html(content);

            // Show modal with flex display for centering
            this.showModal('#details-modal');

            this.initLucideIcons();
        },

        /**
         * Show modal with proper flex display for centering
         *
         * @param {string} selector Modal selector
         */
        showModal: function(selector) {
            const $modal = $(selector);
            $modal.css({
                'display': 'flex',
                'opacity': '0'
            }).animate({
                'opacity': '1'
            }, 200);
            $('body').css('overflow', 'hidden');
        },

        /**
         * Hide modal
         *
         * @param {string} selector Modal selector
         */
        hideModal: function(selector) {
            const $modal = $(selector);
            $modal.animate({
                'opacity': '0'
            }, 200, function() {
                $modal.css('display', 'none');
            });
            $('body').css('overflow', '');
        },

        /**
         * Close modal
         *
         * @param {string} selector Modal selector
         */
        closeModal: function(selector) {
            this.hideModal(selector);
        },

        /**
         * Reset application form
         */
        resetApplicationForm: function() {
            $('#membership-application-form')[0].reset();
            this.uploadedFiles = [];
            $('#uploaded-files-list').empty();
            $('.eau-form-error').removeClass('visible').text('');
            $('.eau-form-input, .eau-form-select').removeClass('error');

            // Re-populate read-only fields
            const $emailField = $('#app-email');
            if ($emailField.data('original')) {
                $emailField.val($emailField.data('original'));
            }
        },

        /**
         * Setup required documents fields
         *
         * @param {array} documents Required documents list
         */
        setupRequiredDocuments: function(documents) {
            const $container = $('#required-documents-list');
            $container.empty();

            if (!documents || documents.length === 0) {
                $('#documents-description').text('No specific documents are required for this membership type. You may upload supporting documents if needed.');
                return;
            }

            $('#documents-description').text('Please upload the following required documents:');

            documents.forEach(function(doc, index) {
                const html = `
                    <div class="eau-document-item">
                        <label for="required-doc-${index}">
                            ${this.escapeHtml(doc)}
                            <span class="eau-required-badge">Required</span>
                        </label>
                        <div class="eau-upload-area">
                            <input type="file" id="required-doc-${index}" name="required_docs[${index}]"
                                   data-doc-name="${this.escapeHtml(doc)}"
                                   accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required>
                            <div class="eau-upload-placeholder">
                                <i data-lucide="upload"></i>
                                <p>Click to upload ${this.escapeHtml(doc)}</p>
                                <span>PDF, DOC, DOCX, JPG, PNG (max 10MB)</span>
                            </div>
                        </div>
                        <div class="eau-uploaded-file-preview" id="preview-required-${index}"></div>
                    </div>
                `;
                $container.append(html);
            }, this);

            this.initLucideIcons();
        },

        /**
         * Next step
         */
        nextStep: function() {
            if (!this.validateStep(this.currentStep)) {
                return;
            }

            if (this.currentStep < this.totalSteps) {
                this.currentStep++;
                this.updateStepDisplay();

                // Populate review on last step
                if (this.currentStep === this.totalSteps) {
                    this.populateReview();
                }
            }
        },

        /**
         * Previous step
         */
        prevStep: function() {
            if (this.currentStep > 1) {
                this.currentStep--;
                this.updateStepDisplay();
            }
        },

        /**
         * Get actual step content ID from visual step number
         * For individuals: visual 1->1, 2->3, 3->4 (skips step 2)
         * For institutions: visual 1->1, 2->2, 3->3, 4->4
         *
         * @param {number} visualStep Visual step number
         * @return {number} Actual step content ID
         */
        getActualStepId: function(visualStep) {
            if (this.isInstitutionType) {
                return visualStep;
            }
            // Individual member: skip step 2 (Organization)
            // Visual step 1 = Content step 1 (Personal Info)
            // Visual step 2 = Content step 3 (Documents)
            // Visual step 3 = Content step 4 (Review)
            const mapping = { 1: 1, 2: 3, 3: 4 };
            return mapping[visualStep] || visualStep;
        },

        /**
         * Get step indicator data-step from visual step number
         * For individuals: only show steps 1, 3, 4 indicators
         *
         * @param {number} visualStep Visual step number
         * @return {number} Step indicator data-step value
         */
        getIndicatorStepNum: function(visualStep) {
            if (this.isInstitutionType) {
                return visualStep;
            }
            // Individual: map to visible indicators (1, 3, 4)
            const mapping = { 1: 1, 2: 3, 3: 4 };
            return mapping[visualStep] || visualStep;
        },

        /**
         * Update step display
         */
        updateStepDisplay: function() {
            const self = this;

            // Hide all steps
            $('.eau-application-step').hide();

            // Show current step content (mapped for individual members)
            const actualStepId = this.getActualStepId(this.currentStep);
            $('#app-step-' + actualStepId).show();

            // Update step indicators
            $('.eau-app-step:visible').each(function(index) {
                const $step = $(this);
                const visualIndex = index + 1; // 1-based index

                $step.removeClass('active completed');

                if (visualIndex < self.currentStep) {
                    $step.addClass('completed');
                } else if (visualIndex === self.currentStep) {
                    $step.addClass('active');
                }
            });

            // Update buttons
            if (this.currentStep === 1) {
                $('.eau-prev-step').hide();
            } else {
                $('.eau-prev-step').show();
            }

            if (this.currentStep === this.totalSteps) {
                $('.eau-next-step').hide();
                $('.eau-submit-application').show();
            } else {
                $('.eau-next-step').show();
                $('.eau-submit-application').hide();
            }

            // Scroll modal to top
            $('.eau-modal-body').scrollTop(0);

            this.initLucideIcons();
        },

        /**
         * Validate step
         *
         * @param {number} step Visual step number
         * @return {boolean}
         */
        validateStep: function(step) {
            let isValid = true;
            const self = this;
            // Convert visual step to actual step content ID
            const actualStepId = this.getActualStepId(step);
            const $step = $('#app-step-' + actualStepId);

            // Clear previous errors
            $step.find('.eau-form-error').removeClass('visible').text('');
            $step.find('.eau-form-input, .eau-form-select').removeClass('error');

            // Validate required fields
            $step.find('[required]:visible').each(function() {
                const $field = $(this);
                const value = $field.val();

                if (!value || value.trim() === '') {
                    self.showFieldError($field, eauMembershipSelection.strings.validationRequired);
                    isValid = false;
                }
            });

            // Step-specific validation
            if (step === 2) {
                // Validate CRICOS number format if visible
                const $cricosNumber = $('#app-cricos-number');
                if ($cricosNumber.is(':visible') && $cricosNumber.val()) {
                    const cricosPattern = /^[0-9]{5}[A-Z]$/i;
                    if (!cricosPattern.test($cricosNumber.val())) {
                        self.showFieldError($cricosNumber, 'Please enter a valid CRICOS number (e.g., 01234K)');
                        isValid = false;
                    }
                }
            }

            if (step === 3) {
                // Validate required document uploads
                $step.find('input[type="file"][required]').each(function() {
                    if (!this.files || this.files.length === 0) {
                        const $container = $(this).closest('.eau-document-item');
                        $container.css('border-color', '#dc2626');
                        isValid = false;
                    }
                });
            }

            if (step === 4) {
                // Validate confirmation checkbox
                if (!$('#app-confirm').is(':checked')) {
                    $('#confirm-error').addClass('visible').text('Please confirm that your information is accurate.');
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
         * Populate review section
         */
        populateReview: function() {
            const typeData = this.membershipTypes[this.selectedType];

            // Membership type
            $('#review-membership-type').text(typeData.type_label);

            // Fee
            let feeText = '';
            if (typeData.fee_is_variable) {
                const sites = parseInt($('#app-cricos-sites').val()) || 1;
                const fee = 3000 + ((sites - 1) * 500);
                feeText = '$' + fee.toLocaleString() + ' AUD (for ' + sites + ' site' + (sites > 1 ? 's' : '') + ')';
            } else {
                feeText = '$' + parseFloat(typeData.fee_amount).toLocaleString() + ' ' + typeData.fee_currency;
                feeText += typeData.fee_includes_gst ? ' (inc. GST)' : '';
            }
            $('#review-membership-fee').text(feeText);

            // Personal info
            let personalHtml = '';
            personalHtml += '<p><strong>Name:</strong> ' + this.escapeHtml($('#app-first-name').val() + ' ' + $('#app-last-name').val()) + '</p>';
            personalHtml += '<p><strong>Email:</strong> ' + this.escapeHtml($('#app-email').val()) + '</p>';
            if ($('#app-phone').val()) {
                personalHtml += '<p><strong>Phone:</strong> ' + this.escapeHtml($('#app-phone').val()) + '</p>';
            }
            personalHtml += '<p><strong>Position:</strong> ' + this.escapeHtml($('#app-position option:selected').text()) + '</p>';
            $('#review-personal').html(personalHtml);

            // Organization
            let orgHtml = '';
            orgHtml += '<p><strong>Organization:</strong> ' + this.escapeHtml($('#app-company-name').val()) + '</p>';
            if ($('#app-cricos-number').is(':visible') && $('#app-cricos-number').val()) {
                orgHtml += '<p><strong>CRICOS Number:</strong> ' + this.escapeHtml($('#app-cricos-number').val()) + '</p>';
                orgHtml += '<p><strong>CRICOS Sites:</strong> ' + this.escapeHtml($('#app-cricos-sites').val()) + '</p>';
            }
            orgHtml += '<p><strong>Location:</strong> ' + this.escapeHtml($('#app-state option:selected').text()) + ', ' + this.escapeHtml($('#app-country option:selected').text()) + '</p>';
            if ($('#app-website').val()) {
                orgHtml += '<p><strong>Website:</strong> ' + this.escapeHtml($('#app-website').val()) + '</p>';
            }
            $('#review-organization').html(orgHtml);

            // Documents
            let docsHtml = '';
            const requiredDocs = $('#required-documents-list input[type="file"]');
            if (requiredDocs.length > 0) {
                requiredDocs.each(function() {
                    if (this.files && this.files.length > 0) {
                        docsHtml += '<p><i data-lucide="file"></i> ' + this.files[0].name + '</p>';
                    }
                });
            }
            if (this.uploadedFiles.length > 0) {
                this.uploadedFiles.forEach(function(file) {
                    docsHtml += '<p><i data-lucide="file"></i> ' + this.escapeHtml(file.name) + '</p>';
                }, this);
            }
            if (!docsHtml) {
                docsHtml = '<p>No documents uploaded</p>';
            }
            $('#review-documents').html(docsHtml);

            this.initLucideIcons();
        },

        /**
         * Handle file upload
         *
         * @param {FileList} files Files to upload
         */
        handleFileUpload: function(files) {
            const self = this;
            const maxSize = 10 * 1024 * 1024; // 10MB

            Array.from(files).forEach(function(file) {
                if (file.size > maxSize) {
                    self.showError('File "' + file.name + '" is too large. Maximum size is 10MB.');
                    return;
                }

                self.uploadedFiles.push(file);
            });

            this.renderUploadedFiles();
        },

        /**
         * Render uploaded files list
         */
        renderUploadedFiles: function() {
            const $container = $('#uploaded-files-list');
            $container.empty();

            this.uploadedFiles.forEach(function(file, index) {
                const sizeKB = Math.round(file.size / 1024);
                const html = `
                    <div class="eau-uploaded-file">
                        <div class="eau-uploaded-file-info">
                            <i data-lucide="file"></i>
                            <span class="eau-uploaded-file-name">${this.escapeHtml(file.name)}</span>
                            <span class="eau-uploaded-file-size">(${sizeKB} KB)</span>
                        </div>
                        <button type="button" class="eau-remove-file" data-index="${index}">
                            <i data-lucide="x"></i>
                        </button>
                    </div>
                `;
                $container.append(html);
            }, this);

            this.initLucideIcons();
        },

        /**
         * Remove uploaded file
         *
         * @param {number} index File index
         */
        removeUploadedFile: function(index) {
            this.uploadedFiles.splice(index, 1);
            this.renderUploadedFiles();
        },

        /**
         * Update fee calculation for Full Provider
         */
        updateFeeCalculation: function() {
            // This could update a fee display in real-time
            // For now, it will be reflected in the review step
        },

        /**
         * Submit application
         */
        submitApplication: function() {
            const self = this;

            if (!this.validateStep(this.totalSteps)) {
                return;
            }

            const $submitBtn = $('.eau-submit-application');
            const originalText = $submitBtn.html();

            // Disable button
            $submitBtn.prop('disabled', true).html(
                '<i data-lucide="loader-2" class="eau-spin"></i> ' + eauMembershipSelection.strings.submitting
            );
            this.initLucideIcons();

            // Prepare form data
            const formData = new FormData($('#membership-application-form')[0]);
            formData.append('action', 'eau_submit_membership_application');
            formData.append('nonce', eauMembershipSelection.nonce);

            // Add additional files
            this.uploadedFiles.forEach(function(file, index) {
                formData.append('supporting_docs_' + index, file);
            });

            $.ajax({
                url: eauMembershipSelection.ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        self.showSuccess(response.data);
                    } else {
                        self.showError(response.data.message || eauMembershipSelection.strings.errorGeneric);
                        $submitBtn.prop('disabled', false).html(originalText);
                        self.initLucideIcons();
                    }
                },
                error: function() {
                    self.showError(eauMembershipSelection.strings.errorGeneric);
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
            // Replace modal content with success message
            $('.eau-modal-body').html(`
                <div class="eau-application-success">
                    <div style="text-align: center; padding: 2rem;">
                        <i data-lucide="check-circle" style="width: 64px; height: 64px; color: #10b981; margin-bottom: 1rem;"></i>
                        <h2 style="margin: 0 0 1rem; color: #111827;">${eauMembershipSelection.strings.successApplication}</h2>
                        <p style="color: #6b7280; margin: 0 0 1.5rem;">
                            Your application has been submitted and is now pending review.
                            You will receive an email notification once it has been processed.
                        </p>
                        <p style="color: #6b7280;">
                            <strong>Application Reference:</strong> #${data.application_id || 'N/A'}
                        </p>
                    </div>
                </div>
            `);

            // Update footer
            $('.eau-modal-footer').html(`
                <a href="/dashboard/" class="eau-btn eau-btn-primary">
                    <i data-lucide="layout-dashboard"></i>
                    Go to Dashboard
                </a>
            `);

            this.initLucideIcons();
        },

        /**
         * Show error message
         *
         * @param {string} message Error message
         */
        showError: function(message) {
            if (typeof EauNotifications !== 'undefined') {
                EauNotifications.error(message);
            } else {
                alert(message);
            }
        },

        /**
         * Format file size to human readable
         *
         * @param {number} bytes File size in bytes
         * @return {string}
         */
        formatFileSize: function(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        },

        /**
         * Escape HTML
         *
         * @param {string} str String to escape
         * @return {string}
         */
        escapeHtml: function(str) {
            if (!str) return '';
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        EauMembershipSelection.init();
    });

    // Expose globally
    window.EauMembershipSelection = EauMembershipSelection;

})(jQuery);
