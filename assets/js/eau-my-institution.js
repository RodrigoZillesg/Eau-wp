(function($) {
    'use strict';

    const MyInstitutionController = {

        // === STATE ===
        config: {},
        currentInstitutions: [],
        selectedInstitution: null,
        selectedRequest: null,
        searchPage: 1,
        searchPerPage: 10,
        incomingPage: 1,
        incomingPerPage: 10,
        myHistoryPage: 1,
        myHistoryPerPage: 10,
        institutionHistoryPage: 1,
        institutionHistoryPerPage: 10,
        searchTimeout: null,
        phoneIti: null, // intl-tel-input instance
        currentCountryHasDetailedData: false,

        // === STATE for Tabs ===
        searchDataLoaded: false,
        requestsDataLoaded: false,
        currentInstitutionId: 0,
        allInstitutions: [], // All institutions for select dropdown

        // === INIT ===
        init: function() {
            this.config = window.eauMyInstitutionData || {};
            this.currentInstitutionId = this.config.defaultInstitutionId || 0;
            this.bindEvents();
            this.bindTabEvents();
            this.bindInstitutionSelectorEvents();
            this.bindLocationEvents();
            this.bindMediaUploadEvents();
            this.loadInitialData();
        },

        // === BIND EVENTS ===
        bindEvents: function() {
            const self = this;

            // Institution select change - show preview
            $('#eau-institution-select').on('change', function() {
                const institutionId = $(this).val();
                if (institutionId) {
                    self.showInstitutionPreview(institutionId);
                } else {
                    self.hideInstitutionPreview();
                }
            });

            // Institution filter input - filter select options
            $('#eau-institution-filter').on('input', function() {
                const filterTerm = $(this).val().toLowerCase().trim();
                self.filterInstitutionSelect(filterTerm);
            });

            // Clear filter when select changes
            $('#eau-institution-select').on('change', function() {
                if ($(this).val()) {
                    $('#eau-institution-filter').val('');
                }
            });

            // Request to join button (from preview card)
            $('#eau-request-join-btn').on('click', function(e) {
                e.preventDefault();
                const institutionId = $(this).data('institution-id');
                const institutionName = $(this).data('institution-name');
                if (institutionId) {
                    self.openRequestModal(institutionId, institutionName);
                }
            });

            // Request to join button (legacy - from search results)
            $(document).on('click', '.eau-request-join-btn', function(e) {
                e.preventDefault();
                const $btn = $(this);
                const institutionId = $btn.data('institution-id');
                const institutionName = $btn.data('institution-name');
                self.openRequestModal(institutionId, institutionName);
            });

            // Confirm request
            $('#eau-confirm-request-btn').on('click', function() {
                self.submitRequest();
            });

            // Cancel pending request
            $(document).on('click', '.eau-cancel-request-btn', function(e) {
                e.preventDefault();
                const requestId = $(this).data('request-id');
                self.cancelRequest(requestId);
            });

            // View institution details
            $(document).on('click', '.eau-view-institution-btn', function(e) {
                e.preventDefault();
                const institutionId = $(this).data('institution-id');
                self.viewInstitutionDetails(institutionId);
            });

            // Edit institution (for institutionAdmin)
            $(document).on('click', '.eau-edit-institution-btn', function(e) {
                e.preventDefault();
                const institutionId = $(this).data('institution-id');
                self.openEditInstitutionModal(institutionId);
            });

            // Save institution changes
            $('#eau-save-institution-btn').on('click', function() {
                self.saveInstitution();
            });

            // Close edit modal
            $('#eau-edit-institution-modal-overlay').on('click', '[data-action="close"]', function() {
                self.closeEditModal();
            });
            $('#eau-edit-institution-modal-overlay').on('click', function(e) {
                if ($(e.target).is('#eau-edit-institution-modal-overlay')) {
                    self.closeEditModal();
                }
            });

            // Leave institution button
            $(document).on('click', '.eau-leave-institution-btn', function(e) {
                e.preventDefault();
                const $btn = $(this);
                const institutionId = $btn.data('institution-id');
                const institutionName = $btn.data('institution-name');
                self.openLeaveModal(institutionId, institutionName);
            });

            // Confirm leave
            $('#eau-confirm-leave-btn').on('click', function() {
                self.leaveInstitution();
            });

            // Review incoming request
            $(document).on('click', '.eau-review-request-btn', function(e) {
                e.preventDefault();
                const requestData = $(this).data('request');
                self.openRespondModal(requestData);
            });

            // Approve request
            $('#eau-approve-request-btn').on('click', function() {
                self.respondToRequest('approve');
            });

            // Reject request
            $('#eau-reject-request-btn').on('click', function() {
                self.respondToRequest('reject');
            });

            // Modal close buttons
            $(document).on('click', '[data-action="close"]', function() {
                $(this).closest('.eau-modal-overlay').fadeOut(200);
            });

            // Close modal on overlay click
            $('.eau-modal-overlay').on('click', function(e) {
                if ($(e.target).hasClass('eau-modal-overlay')) {
                    $(this).fadeOut(200);
                }
            });

            // Incoming requests pagination
            $(document).on('click', '#eau-incoming-pagination .eau-pagination-btn', function(e) {
                e.preventDefault();
                const page = $(this).data('page');
                if (page) {
                    self.incomingPage = page;
                    self.loadIncomingRequests();
                }
            });

            // My history pagination
            $(document).on('click', '#eau-my-history-pagination .eau-pagination-btn', function(e) {
                e.preventDefault();
                const page = $(this).data('page');
                if (page) {
                    self.myHistoryPage = page;
                    self.loadMyHistory();
                }
            });

            // Institution history pagination
            $(document).on('click', '#eau-institution-history-pagination .eau-pagination-btn', function(e) {
                e.preventDefault();
                const page = $(this).data('page');
                if (page) {
                    self.institutionHistoryPage = page;
                    self.loadInstitutionHistory();
                }
            });
        },

        // === BIND TAB EVENTS ===
        bindTabEvents: function() {
            const self = this;

            // Tab switching
            $(document).on('click', '.eau-tabs .eau-tab', function(e) {
                e.preventDefault();
                const tab = $(this).data('tab');

                // Don't do anything if already active
                if ($(this).hasClass('active')) {
                    return;
                }

                // Update tab buttons
                $('.eau-tabs .eau-tab').removeClass('active');
                $(this).addClass('active');

                // Update tab contents
                $('.eau-tab-content').removeClass('active');
                $(`.eau-tab-content[data-tab="${tab}"]`).addClass('active');

                // Load data for specific tabs if needed (lazy loading)
                if (tab === 'search' && !self.searchDataLoaded) {
                    self.loadInstitutionsForSelect();
                    self.loadPendingRequests();
                    self.searchDataLoaded = true;
                }
                if (tab === 'requests' && !self.requestsDataLoaded) {
                    self.loadIncomingRequests();
                    self.loadInstitutionHistory();
                    self.requestsDataLoaded = true;
                }

                // Re-initialize Lucide icons
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
            });
        },

        // === BIND INSTITUTION SELECTOR EVENTS ===
        // Note: Binding is now done dynamically in renderInstitutionDetailsTab()
        bindInstitutionSelectorEvents: function() {
            // Empty - selector binding happens after AJAX load in renderInstitutionDetailsTab
        },

        // Note: loadInstitutionDetailsEmbedded removed - now using loadInstitutionEmbedded

        // === BIND LOCATION EVENTS (Country -> State -> City cascade) ===
        bindLocationEvents: function() {
            const self = this;

            // Country change - load states
            $('#eau-edit-ins_company_company_country').on('change', function() {
                const countryCode = $(this).val();
                self.loadStatesForCountry(countryCode);
            });

            // State change - load cities
            $('#eau-edit-ins_company_company_state').on('change', function() {
                const countryCode = $('#eau-edit-ins_company_company_country').val();
                const stateCode = $(this).val();
                self.loadCitiesForState(countryCode, stateCode);
            });
        },

        // === BIND MEDIA UPLOAD EVENTS ===
        bindMediaUploadEvents: function() {
            const self = this;

            // Media upload - Browse button (trigger file input)
            $(document).on('click', '.eau-media-upload-browse-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const wrapper = $(this).closest('.eau-media-upload-wrapper');
                const fileInput = wrapper.find('.eau-media-upload-file-input')[0];
                if (fileInput) {
                    fileInput.click();
                }
            });

            // Media upload - Click on dropzone to trigger file input
            $(document).on('click', '.eau-media-upload-dropzone', function(e) {
                // Don't trigger if clicking on the browse button or file input itself
                if ($(e.target).closest('.eau-media-upload-browse-btn').length ||
                    $(e.target).is('input[type="file"]')) {
                    return;
                }
                e.preventDefault();
                e.stopPropagation();
                const wrapper = $(this).closest('.eau-media-upload-wrapper');
                const fileInput = wrapper.find('.eau-media-upload-file-input')[0];
                if (fileInput) {
                    fileInput.click();
                }
            });

            // Media upload - File input change (handle file selection)
            $(document).on('change', '.eau-media-upload-file-input', function() {
                const wrapper = $(this).closest('.eau-media-upload-wrapper');
                const file = this.files[0];

                if (file) {
                    self.uploadFile(wrapper, file);
                }

                // Clear the input so the same file can be selected again
                $(this).val('');
            });

            // Media upload - Drag and drop
            $(document).on('dragover dragenter', '.eau-media-upload-dropzone', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).addClass('drag-active');
            });

            $(document).on('dragleave dragend drop', '.eau-media-upload-dropzone', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('drag-active');
            });

            $(document).on('drop', '.eau-media-upload-dropzone', function(e) {
                e.preventDefault();
                const wrapper = $(this).closest('.eau-media-upload-wrapper');
                const files = e.originalEvent.dataTransfer.files;

                if (files.length > 0) {
                    self.uploadFile(wrapper, files[0]);
                }
            });

            // Media upload - Remove button
            $(document).on('click', '.eau-media-upload-remove', function() {
                const wrapper = $(this).closest('.eau-media-upload-wrapper');
                self.clearMediaUpload(wrapper);
            });
        },

        // === UPLOAD FILE ===
        uploadFile: function(wrapper, file) {
            const self = this;
            const maxSize = parseInt(wrapper.data('max-file-size')) || 10485760; // 10MB
            const allowedExtensions = wrapper.data('allowed-extensions') || '';

            // Validate file size
            if (file.size > maxSize) {
                EauNotifications.error('Error', 'File is too large. Maximum size is ' + this.formatFileSize(maxSize));
                return;
            }

            // Validate extension
            if (allowedExtensions) {
                const allowed = allowedExtensions.toLowerCase().split(',');
                const ext = file.name.split('.').pop().toLowerCase();

                if (!allowed.includes(ext)) {
                    EauNotifications.error('Error', 'File type not allowed. Allowed: ' + allowed.map(e => e.toUpperCase()).join(', '));
                    return;
                }
            }

            // Show progress
            const dropzone = wrapper.find('.eau-media-upload-dropzone');
            const progressContainer = dropzone.find('.eau-media-upload-progress');
            const progressFill = progressContainer.find('.eau-media-upload-progress-fill');
            const progressText = progressContainer.find('.eau-media-upload-progress-text');
            const dropzoneContent = dropzone.find('.eau-media-upload-dropzone-content');

            dropzoneContent.hide();
            progressContainer.show();
            progressFill.css('width', '0%');
            progressText.text('Uploading... 0%');

            // Create FormData
            const formData = new FormData();
            formData.append('action', 'eau_upload_institution_file');
            formData.append('nonce', this.config.nonce);
            formData.append('file', file);
            formData.append('max_size', maxSize);
            formData.append('allowed_extensions', allowedExtensions);

            // Upload via AJAX
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                xhr: function() {
                    const xhr = new window.XMLHttpRequest();
                    xhr.upload.addEventListener('progress', function(e) {
                        if (e.lengthComputable) {
                            const percent = Math.round((e.loaded / e.total) * 100);
                            progressFill.css('width', percent + '%');
                            progressText.text('Uploading... ' + percent + '%');
                        }
                    }, false);
                    return xhr;
                },
                success: function(response) {
                    if (response.success) {
                        self.setMediaValueFromUpload(wrapper, response.data.id, response.data.filename, response.data.url);
                        EauNotifications.success('Success', 'File uploaded successfully');
                    } else {
                        EauNotifications.error('Error', response.data.message || 'Upload failed');
                    }
                },
                error: function() {
                    EauNotifications.error('Error', 'Upload failed. Please try again.');
                },
                complete: function() {
                    progressContainer.hide();
                    dropzoneContent.show();
                }
            });
        },

        // === SET MEDIA VALUE FROM UPLOAD ===
        setMediaValueFromUpload: function(wrapper, value, filename, url) {
            const valueInput = wrapper.find('.eau-media-upload-value');
            const typeInput = wrapper.find('.eau-media-upload-type');
            const preview = wrapper.find('.eau-media-upload-preview');
            const previewName = wrapper.find('.eau-media-upload-preview-name');
            const previewLink = wrapper.find('.eau-media-upload-preview-link');
            const thumbnail = wrapper.find('.eau-media-upload-preview-thumbnail');
            const thumbnailImage = thumbnail.find('.eau-media-upload-preview-image');

            valueInput.val(value);
            typeInput.val('media');
            previewName.text(filename);
            previewLink.attr('href', url);

            // Check if file is an image and show preview
            const isImage = this.isImageFile(filename);

            if (isImage && url) {
                thumbnailImage.attr('src', url).show();
                thumbnail.addClass('has-image');
            } else {
                thumbnailImage.attr('src', '').hide();
                thumbnail.removeClass('has-image');
            }

            // Hide dropzone and show preview
            wrapper.find('.eau-media-upload-panel').hide();
            preview.show();

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        },

        // === CLEAR MEDIA UPLOAD ===
        clearMediaUpload: function(wrapper) {
            wrapper.find('.eau-media-upload-value').val('');
            wrapper.find('.eau-media-upload-type').val('');
            wrapper.find('.eau-media-upload-preview').hide();

            // Show dropzone again
            wrapper.find('.eau-media-upload-file-panel').show();

            // Clear image preview
            const thumbnail = wrapper.find('.eau-media-upload-preview-thumbnail');
            thumbnail.removeClass('has-image');
            thumbnail.find('.eau-media-upload-preview-image').attr('src', '').hide();
        },

        // === CHECK IF IMAGE FILE ===
        isImageFile: function(filename) {
            if (!filename) return false;
            const ext = filename.split('.').pop().toLowerCase();
            return ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'].includes(ext);
        },

        // === FORMAT FILE SIZE ===
        formatFileSize: function(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        },

        // === LOAD STATES FOR COUNTRY ===
        loadStatesForCountry: function(countryCode, selectedState, selectedCity) {
            const self = this;
            const $stateSelect = $('#eau-edit-ins_company_company_state');
            const $stateText = $('#eau-edit-ins_company_company_state_text');
            const $citySelect = $('#eau-edit-ins_company_company_suburb');
            const $cityText = $('#eau-edit-ins_company_company_suburb_text');

            // Reset fields
            $stateSelect.html('<option value="">' + (self.config.strings.loadingStates || 'Loading...') + '</option>');
            $citySelect.html('<option value="">' + (self.config.strings.selectCity || 'Select city...') + '</option>');

            if (!countryCode) {
                $stateSelect.html('<option value="">' + (self.config.strings.selectState || 'Select state...') + '</option>');
                $stateSelect.show();
                $stateText.hide();
                $citySelect.show();
                $cityText.hide();
                return;
            }

            $.ajax({
                url: self.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_get_states',
                    nonce: self.config.nonce,
                    country: countryCode
                },
                success: function(response) {
                    if (response.success) {
                        const states = response.data.states;
                        self.currentCountryHasDetailedData = response.data.has_detailed_data;

                        if (Object.keys(states).length > 0) {
                            // Has states - show select
                            let html = '<option value="">' + (self.config.strings.selectState || 'Select state...') + '</option>';
                            for (const code in states) {
                                const selected = (selectedState && selectedState === code) ? ' selected' : '';
                                html += '<option value="' + code + '"' + selected + '>' + self.escapeHtml(states[code]) + '</option>';
                            }
                            $stateSelect.html(html).show();
                            $stateText.hide().val('');

                            // If state was pre-selected, load cities with selectedCity
                            if (selectedState && states[selectedState]) {
                                self.loadCitiesForState(countryCode, selectedState, selectedCity);
                            }
                        } else {
                            // No states - show text input
                            $stateSelect.hide();
                            $stateText.show().val(selectedState || '');
                            $citySelect.hide();
                            $cityText.show().val(selectedCity || '');
                        }
                    }
                },
                error: function() {
                    $stateSelect.html('<option value="">' + (self.config.strings.selectState || 'Select state...') + '</option>');
                }
            });
        },

        // === LOAD CITIES FOR STATE ===
        loadCitiesForState: function(countryCode, stateCode, selectedCity) {
            const self = this;
            const $citySelect = $('#eau-edit-ins_company_company_suburb');
            const $cityText = $('#eau-edit-ins_company_company_suburb_text');

            // Reset city
            $citySelect.html('<option value="">' + (self.config.strings.loadingCities || 'Loading...') + '</option>');

            if (!countryCode || !stateCode) {
                $citySelect.html('<option value="">' + (self.config.strings.selectCity || 'Select city...') + '</option>');
                $citySelect.show();
                $cityText.hide();
                return;
            }

            $.ajax({
                url: self.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_get_cities',
                    nonce: self.config.nonce,
                    country: countryCode,
                    state: stateCode
                },
                success: function(response) {
                    if (response.success) {
                        const cities = response.data.cities;

                        if (cities.length > 0) {
                            // Has cities - show select
                            let html = '<option value="">' + (self.config.strings.selectCity || 'Select city...') + '</option>';
                            cities.forEach(function(city) {
                                const selected = (selectedCity && selectedCity === city) ? ' selected' : '';
                                html += '<option value="' + self.escapeHtml(city) + '"' + selected + '>' + self.escapeHtml(city) + '</option>';
                            });
                            $citySelect.html(html).show();
                            $cityText.hide().val('');
                        } else {
                            // No cities - show text input
                            $citySelect.hide();
                            $cityText.show().val(selectedCity || '');
                        }
                    }
                },
                error: function() {
                    $citySelect.html('<option value="">' + (self.config.strings.selectCity || 'Select city...') + '</option>');
                }
            });
        },

        // === INITIALIZE PHONE INPUT ===
        initPhoneInput: function() {
            const phoneInput = document.querySelector('#eau-edit-ins_company_company_phone');
            if (phoneInput && typeof intlTelInput !== 'undefined') {
                // Destroy existing instance if any
                if (this.phoneIti) {
                    this.phoneIti.destroy();
                }

                this.phoneIti = intlTelInput(phoneInput, {
                    initialCountry: 'au',
                    preferredCountries: ['au', 'nz', 'gb', 'us'],
                    separateDialCode: true,
                    utilsScript: 'https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/utils.js',
                    formatOnDisplay: true,
                    nationalMode: false,
                    autoPlaceholder: 'aggressive'
                });
            }
        },

        // === CLOSE EDIT MODAL ===
        closeEditModal: function() {
            $('#eau-edit-institution-modal-overlay').fadeOut(200);
            // Destroy phone input instance
            if (this.phoneIti) {
                this.phoneIti.destroy();
                this.phoneIti = null;
            }
        },

        // === LOAD INITIAL DATA ===
        loadInitialData: function() {
            // Stats (institutionAdmin only - shown above tabs)
            if (this.config.isInstitutionAdmin) {
                this.loadStats();
            }

            // My Request History (always visible below tabs)
            this.loadMyHistory();

            // Load Institution Details (first tab - always visible initially)
            this.loadInstitutionDetailsTab();

            // Initialize Lucide icons for the initially rendered content
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }

            // Note: Other data is loaded lazily when tabs are clicked
            // - Search tab: loadPendingRequests() is called when tab is activated
            // - Requests tab: loadIncomingRequests() and loadInstitutionHistory() when tab is activated
        },

        // === LOAD INSTITUTION DETAILS TAB ===
        loadInstitutionDetailsTab: function() {
            const self = this;

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_get_my_institution',
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.currentInstitutions = response.data.institutions || [];
                        self.renderInstitutionDetailsTab();
                    } else {
                        self.renderNoInstitutionState();
                    }
                },
                error: function() {
                    self.renderNoInstitutionState();
                }
            });
        },

        renderInstitutionDetailsTab: function() {
            const self = this;
            const $content = $('#eau-institution-details-content');
            const $selectorContainer = $('#eau-institution-selector-container');
            const $leaveContainer = $('#eau-leave-section-container');

            if (this.currentInstitutions.length === 0) {
                this.renderNoInstitutionState();
                return;
            }

            // If multiple institutions, show selector
            if (this.currentInstitutions.length > 1) {
                let selectorHtml = `
                    <div class="eau-institution-selector">
                        <label for="eau-institution-selector">
                            <i data-lucide="building-2"></i>
                            Select Institution:
                        </label>
                        <select id="eau-institution-selector" class="eau-form-select">
                `;
                this.currentInstitutions.forEach(function(inst) {
                    selectorHtml += `<option value="${inst.id}">${self.escapeHtml(inst.name)}</option>`;
                });
                selectorHtml += `
                        </select>
                    </div>
                `;
                $selectorContainer.html(selectorHtml).show();

                // Bind change event
                $('#eau-institution-selector').on('change', function() {
                    const institutionId = $(this).val();
                    self.loadInstitutionEmbedded(institutionId);
                });
            }

            // Load the first institution's embedded content
            const firstInstitutionId = this.currentInstitutions[0].id;
            this.currentInstitutionId = firstInstitutionId;
            this.loadInstitutionEmbedded(firstInstitutionId);

            // Show leave button for regular members (not institutionAdmin) with single institution
            if (!this.config.isInstitutionAdmin && this.currentInstitutions.length === 1) {
                const inst = this.currentInstitutions[0];
                $leaveContainer.html(`
                    <div class="eau-leave-section">
                        <button type="button" class="eau-btn eau-btn-outline-danger eau-leave-institution-btn"
                                data-institution-id="${inst.id}"
                                data-institution-name="${self.escapeHtml(inst.name)}">
                            <i data-lucide="log-out"></i>
                            Leave Institution
                        </button>
                    </div>
                `).show();

                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
            }
        },

        renderNoInstitutionState: function() {
            const $content = $('#eau-institution-details-content');
            $content.html(`
                <div class="eau-empty-state">
                    <i data-lucide="building-2"></i>
                    <h3>No Institution</h3>
                    <p>You are not currently linked to any institution.</p>
                    <p class="eau-text-muted">Use the "Search & Join" tab to find and request to join an institution.</p>
                </div>
            `);

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        },

        loadInstitutionEmbedded: function(institutionId) {
            const self = this;
            const $content = $('#eau-institution-details-content');

            // Show loading skeleton
            $content.html('<div class="eau-loading-state"><div class="eau-spinner"></div><p>Loading institution details...</p></div>');

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_get_institution_embedded',
                    nonce: this.config.nonce,
                    institution_id: institutionId
                },
                success: function(response) {
                    if (response.success) {
                        $content.html(response.data.html);
                        self.currentInstitutionId = institutionId;

                        // Initialize Lucide icons first
                        if (typeof lucide !== 'undefined') {
                            lucide.createIcons();
                        }

                        // Initialize the single page controller with script data from AJAX
                        if (response.data.scriptData) {
                            // Re-initialize single page controller with new data
                            if (typeof EauInstitutionSingleController !== 'undefined') {
                                // Use the reinit method which properly initializes and loads members
                                EauInstitutionSingleController.reinit(institutionId, response.data.scriptData);
                            }
                        }
                    } else {
                        $content.html('<div class="eau-empty-state"><p>' + (response.data.message || 'Error loading institution') + '</p></div>');
                    }
                },
                error: function() {
                    $content.html('<div class="eau-empty-state"><p>Error loading institution. Please try again.</p></div>');
                }
            });
        },

        // === LOAD STATS ===
        loadStats: function() {
            const self = this;

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_get_my_institution_stats',
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.renderStats(response.data);
                    }
                }
            });
        },

        renderStats: function(stats) {
            let html = '<div class="eau-stats-grid eau-stats-grid-2">';

            html += `
                <div class="eau-stat-card eau-stat-card-blue">
                    <div class="eau-stat-card-content">
                        <h3 class="eau-stat-card-title">Pending Requests</h3>
                        <p class="eau-stat-card-number">${stats.pending_requests}</p>
                    </div>
                    <div class="eau-stat-card-icon">
                        <i data-lucide="clock"></i>
                    </div>
                </div>
            `;

            if (this.config.isInstitutionAdmin) {
                html += `
                    <div class="eau-stat-card eau-stat-card-purple">
                        <div class="eau-stat-card-content">
                            <h3 class="eau-stat-card-title">Requests to Review</h3>
                            <p class="eau-stat-card-number">${stats.incoming_requests}</p>
                        </div>
                        <div class="eau-stat-card-icon">
                            <i data-lucide="inbox"></i>
                        </div>
                    </div>
                `;
            } else {
                // Placeholder for non-admin
                html += `
                    <div class="eau-stat-card eau-stat-card-green">
                        <div class="eau-stat-card-content">
                            <h3 class="eau-stat-card-title">Institution Status</h3>
                            <p class="eau-stat-card-number">${this.currentInstitutions.length > 0 ? 'Active' : 'None'}</p>
                        </div>
                        <div class="eau-stat-card-icon">
                            <i data-lucide="building-2"></i>
                        </div>
                    </div>
                `;
            }

            html += '</div>';

            $('#eau-my-institution-stats').html(html);

            // Update badges
            if (stats.pending_requests > 0) {
                $('#eau-pending-count').text(stats.pending_requests).show();
            } else {
                $('#eau-pending-count').hide();
            }

            if (stats.incoming_requests > 0) {
                $('#eau-incoming-count').text(stats.incoming_requests).show();
                // v1.66.6 - Também atualiza o badge na aba Requests
                $('#eau-requests-badge').text(stats.incoming_requests).show();
            } else {
                $('#eau-incoming-count').hide();
                $('#eau-requests-badge').hide();
            }

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        },

        // === LOAD CURRENT INSTITUTION ===
        loadCurrentInstitution: function() {
            const self = this;

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_get_my_institution',
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.currentInstitutions = response.data.institutions || [];
                        self.renderCurrentInstitutions();
                    } else {
                        self.renderNoInstitution();
                    }
                },
                error: function() {
                    self.renderNoInstitution();
                }
            });
        },

        renderCurrentInstitutions: function() {
            const self = this;

            if (this.currentInstitutions.length === 0) {
                this.renderNoInstitution();
                return;
            }

            let html = '<div class="eau-institution-cards">';

            this.currentInstitutions.forEach(function(inst) {
                const roleLabel = inst.role === 'admin' ? 'Administrator' : 'Member';
                const roleBadge = inst.role === 'admin'
                    ? '<span class="eau-badge eau-badge-purple">Admin</span>'
                    : '<span class="eau-badge eau-badge-blue">Member</span>';

                const statusBadge = inst.status === 'active'
                    ? '<span class="eau-badge eau-badge-success">Active</span>'
                    : '<span class="eau-badge eau-badge-secondary">Inactive</span>';

                html += `
                    <div class="eau-institution-card">
                        <div class="eau-institution-card-header">
                            <div class="eau-institution-card-logo">
                                ${inst.logo ? `<img src="${inst.logo}" alt="${inst.name}">` : '<i data-lucide="building-2"></i>'}
                            </div>
                            <div class="eau-institution-card-info">
                                <h3 class="eau-institution-card-name">${self.escapeHtml(inst.name)}</h3>
                                <div class="eau-institution-card-badges">
                                    ${roleBadge}
                                    ${statusBadge}
                                    ${inst.type ? `<span class="eau-badge eau-badge-outline">${self.escapeHtml(inst.type)}</span>` : ''}
                                </div>
                            </div>
                        </div>
                        <div class="eau-institution-card-body">
                            <div class="eau-institution-card-details">
                                ${inst.email ? `<p><i data-lucide="mail"></i> ${self.escapeHtml(inst.email)}</p>` : ''}
                                ${inst.phone ? `<p><i data-lucide="phone"></i> ${self.escapeHtml(inst.phone)}</p>` : ''}
                                ${inst.website ? `<p><i data-lucide="globe"></i> <a href="${self.escapeHtml(inst.website)}" target="_blank">${self.escapeHtml(inst.website)}</a></p>` : ''}
                                ${inst.city || inst.state ? `<p><i data-lucide="map-pin"></i> ${self.escapeHtml([inst.city, inst.state].filter(Boolean).join(', '))}</p>` : ''}
                            </div>
                        </div>
                        <div class="eau-institution-card-footer">
                            <button type="button" class="eau-btn eau-btn-secondary eau-btn-sm eau-view-institution-btn"
                                    data-institution-id="${inst.id}">
                                <i data-lucide="eye"></i>
                                View Details
                            </button>
                            ${inst.role === 'admin' ? `
                                <button type="button" class="eau-btn eau-btn-primary eau-btn-sm eau-edit-institution-btn"
                                        data-institution-id="${inst.id}">
                                    <i data-lucide="edit"></i>
                                    Edit
                                </button>
                            ` : ''}
                            ${inst.role === 'member' ? `
                                <button type="button" class="eau-btn eau-btn-outline-danger eau-btn-sm eau-leave-institution-btn"
                                        data-institution-id="${inst.id}"
                                        data-institution-name="${self.escapeHtml(inst.name)}">
                                    <i data-lucide="log-out"></i>
                                    Leave
                                </button>
                            ` : ''}
                        </div>
                    </div>
                `;
            });

            html += '</div>';

            $('#eau-current-institution-body').html(html);

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        },

        renderNoInstitution: function() {
            const html = `
                <div class="eau-empty-state">
                    <i data-lucide="building-2"></i>
                    <p>${this.config.strings.noInstitution}</p>
                    <p class="eau-text-muted">Use the search below to find and request to join an institution.</p>
                </div>
            `;

            $('#eau-current-institution-body').html(html);

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        },

        // === SEARCH INSTITUTIONS ===
        searchInstitutions: function(term) {
            const self = this;

            $('#eau-search-results').html('<div class="eau-loading-inline"><i data-lucide="loader-2" class="eau-spin"></i> Searching...</div>');
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_search_institutions_public',
                    nonce: this.config.nonce,
                    search: term,
                    page: this.searchPage,
                    per_page: this.searchPerPage
                },
                success: function(response) {
                    if (response.success) {
                        self.renderSearchResults(response.data);
                    } else {
                        self.showSearchError();
                    }
                },
                error: function() {
                    self.showSearchError();
                }
            });
        },

        renderSearchResults: function(data) {
            const self = this;
            const institutions = data.institutions || [];

            if (institutions.length === 0) {
                $('#eau-search-results').html(`
                    <div class="eau-empty-state eau-empty-state-sm">
                        <i data-lucide="search-x"></i>
                        <p>${this.config.strings.noResults}</p>
                    </div>
                `);
                $('#eau-search-pagination').hide();
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
                return;
            }

            let html = '<div class="eau-search-results-list">';

            institutions.forEach(function(inst) {
                const location = [inst.city, inst.state].filter(Boolean).join(', ');
                let actionBtn = '';

                if (inst.is_current) {
                    actionBtn = '<span class="eau-badge eau-badge-success"><i data-lucide="check"></i> Current</span>';
                } else if (inst.has_pending_request) {
                    actionBtn = '<span class="eau-badge eau-badge-warning"><i data-lucide="clock"></i> Pending</span>';
                } else {
                    actionBtn = `
                        <button type="button" class="eau-btn eau-btn-primary eau-btn-sm eau-request-join-btn"
                                data-institution-id="${inst.id}"
                                data-institution-name="${self.escapeHtml(inst.name)}">
                            <i data-lucide="plus"></i>
                            Request
                        </button>
                    `;
                }

                html += `
                    <div class="eau-search-result-item">
                        <div class="eau-search-result-info">
                            <h4 class="eau-search-result-name">${self.escapeHtml(inst.name)}</h4>
                            <div class="eau-search-result-meta">
                                ${location ? `<span><i data-lucide="map-pin"></i> ${self.escapeHtml(location)}</span>` : ''}
                                ${inst.type ? `<span><i data-lucide="tag"></i> ${self.escapeHtml(inst.type)}</span>` : ''}
                            </div>
                        </div>
                        <div class="eau-search-result-action">
                            ${actionBtn}
                        </div>
                    </div>
                `;
            });

            html += '</div>';

            $('#eau-search-results').html(html);

            // Render pagination
            if (data.total_pages > 1) {
                this.renderSearchPagination(data);
            } else {
                $('#eau-search-pagination').hide();
            }

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        },

        renderSearchPagination: function(data) {
            let html = '<div class="eau-pagination">';

            // Previous
            if (data.page > 1) {
                html += `<button class="eau-pagination-btn" data-page="${data.page - 1}"><i data-lucide="chevron-left"></i></button>`;
            }

            // Pages
            for (let i = 1; i <= data.total_pages; i++) {
                if (i === data.page) {
                    html += `<button class="eau-pagination-btn eau-pagination-btn-active">${i}</button>`;
                } else if (i <= 2 || i > data.total_pages - 2 || Math.abs(i - data.page) <= 1) {
                    html += `<button class="eau-pagination-btn" data-page="${i}">${i}</button>`;
                } else if (i === 3 && data.page > 4) {
                    html += '<span class="eau-pagination-ellipsis">...</span>';
                } else if (i === data.total_pages - 2 && data.page < data.total_pages - 3) {
                    html += '<span class="eau-pagination-ellipsis">...</span>';
                }
            }

            // Next
            if (data.page < data.total_pages) {
                html += `<button class="eau-pagination-btn" data-page="${data.page + 1}"><i data-lucide="chevron-right"></i></button>`;
            }

            html += '</div>';

            $('#eau-search-pagination').html(html).show();

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        },

        clearSearchResults: function() {
            $('#eau-search-results').html('<p class="eau-text-muted eau-text-center">Enter a search term to find institutions</p>');
            $('#eau-search-pagination').hide();
        },

        showSearchError: function() {
            $('#eau-search-results').html(`
                <div class="eau-alert eau-alert-danger">
                    <i data-lucide="alert-circle"></i>
                    <span>${this.config.strings.error}</span>
                </div>
            `);
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        },

        // === INSTITUTION SELECT (NEW) ===
        loadInstitutionsForSelect: function() {
            const self = this;
            const $select = $('#eau-institution-select');
            const $hint = $('#eau-institution-select-hint');

            $hint.text('Loading institutions...');
            $select.prop('disabled', true);

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_get_all_institutions_for_select',
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success && response.data.institutions) {
                        self.allInstitutions = response.data.institutions;
                        self.populateInstitutionSelect(response.data.institutions);
                        $hint.text(response.data.institutions.length + ' institutions available');
                    } else {
                        $hint.text('No institutions available');
                    }
                    $select.prop('disabled', false);
                },
                error: function() {
                    $hint.text('Error loading institutions. Please refresh the page.');
                    $select.prop('disabled', false);
                }
            });
        },

        populateInstitutionSelect: function(institutions) {
            const self = this;
            const $select = $('#eau-institution-select');

            // Clear existing options except the first one
            $select.find('option:not(:first)').remove();

            // Add options for each institution
            institutions.forEach(function(inst) {
                const location = [inst.city, inst.state].filter(Boolean).join(', ');
                let optionText = inst.name;
                if (location) {
                    optionText += ' - ' + location;
                }

                // Add status indicators
                let suffix = '';
                if (inst.is_current) {
                    suffix = ' (Current)';
                } else if (inst.has_pending_request) {
                    suffix = ' (Pending Request)';
                }

                const $option = $('<option></option>')
                    .val(inst.id)
                    .text(optionText + suffix)
                    .data('institution', inst);

                if (inst.is_current || inst.has_pending_request) {
                    $option.prop('disabled', true);
                }

                $select.append($option);
            });
        },

        filterInstitutionSelect: function(filterTerm) {
            const $select = $('#eau-institution-select');
            const $options = $select.find('option');
            let visibleCount = 0;

            $options.each(function() {
                const $option = $(this);
                const value = $option.val();

                // Always show the placeholder option
                if (!value) {
                    $option.show();
                    return;
                }

                const text = $option.text().toLowerCase();
                const inst = $option.data('institution');

                // Check if matches filter term
                let matches = text.includes(filterTerm);

                // Also check city, state, country if available
                if (!matches && inst) {
                    const city = (inst.city || '').toLowerCase();
                    const state = (inst.state || '').toLowerCase();
                    const country = (inst.country || '').toLowerCase();
                    matches = city.includes(filterTerm) ||
                              state.includes(filterTerm) ||
                              country.includes(filterTerm);
                }

                if (matches || !filterTerm) {
                    $option.show();
                    visibleCount++;
                } else {
                    $option.hide();
                }
            });

            // Update hint with filtered count
            const $hint = $('#eau-institution-select-hint');
            if (filterTerm) {
                $hint.text(visibleCount + ' institutions matching "' + filterTerm + '"');
            } else {
                $hint.text(this.allInstitutions.length + ' institutions available');
            }

            // If only one visible option (besides placeholder), auto-select it
            if (visibleCount === 1 && filterTerm) {
                const $visibleOption = $select.find('option:visible').not(':first');
                if ($visibleOption.length === 1 && !$visibleOption.prop('disabled')) {
                    $select.val($visibleOption.val()).trigger('change');
                }
            }
        },

        showInstitutionPreview: function(institutionId) {
            const self = this;
            const $preview = $('#eau-selected-institution-preview');

            // Find institution from cached data
            const inst = this.allInstitutions.find(i => i.id == institutionId);

            if (!inst) {
                this.hideInstitutionPreview();
                return;
            }

            // Update preview content
            if (inst.logo) {
                $('#eau-preview-logo').html(`<img src="${inst.logo}" alt="${self.escapeHtml(inst.name)}">`);
            } else {
                $('#eau-preview-logo').html('<i data-lucide="building-2"></i>');
            }

            $('#eau-preview-name').text(inst.name);

            const location = [inst.city, inst.state, inst.country].filter(Boolean).join(', ');
            $('#eau-preview-location').text(location || 'Location not specified');

            // Type
            if (inst.type) {
                $('#eau-preview-type').text(inst.type).removeClass().addClass('eau-badge eau-badge-info');
                $('#eau-preview-type-container').show();
            } else {
                $('#eau-preview-type-container').hide();
            }

            // Email
            if (inst.email) {
                $('#eau-preview-email').text(inst.email);
                $('#eau-preview-email-container').show();
            } else {
                $('#eau-preview-email-container').hide();
            }

            // Phone
            if (inst.phone) {
                $('#eau-preview-phone').text(inst.phone);
                $('#eau-preview-phone-container').show();
            } else {
                $('#eau-preview-phone-container').hide();
            }

            // Update request button
            const $btn = $('#eau-request-join-btn');
            $btn.data('institution-id', inst.id);
            $btn.data('institution-name', inst.name);

            // Disable button if already current or pending
            if (inst.is_current) {
                $btn.prop('disabled', true).html('<i data-lucide="check"></i> Already a Member');
            } else if (inst.has_pending_request) {
                $btn.prop('disabled', true).html('<i data-lucide="clock"></i> Request Pending');
            } else {
                $btn.prop('disabled', false).html('<i data-lucide="user-plus"></i> Request to Join');
            }

            // Show preview
            $preview.slideDown(200);

            // Re-init Lucide icons
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        },

        hideInstitutionPreview: function() {
            $('#eau-selected-institution-preview').slideUp(200);
        },

        // === REQUEST MODAL ===
        openRequestModal: function(institutionId, institutionName) {
            this.selectedInstitution = {
                id: institutionId,
                name: institutionName
            };

            $('#eau-request-institution-name').text(institutionName);

            // Show warning if user already has an institution (for regular members)
            if (!this.config.isInstitutionAdmin && this.currentInstitutions.length > 0) {
                $('#eau-request-warning').show();
            } else {
                $('#eau-request-warning').hide();
            }

            $('#eau-request-modal-overlay').css('display', 'flex').hide().fadeIn(200);

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        },

        submitRequest: function() {
            const self = this;

            if (!this.selectedInstitution) {
                return;
            }

            const $btn = $('#eau-confirm-request-btn');
            $btn.prop('disabled', true).addClass('eau-loading');

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_request_institution_link',
                    nonce: this.config.nonce,
                    institution_id: this.selectedInstitution.id
                },
                success: function(response) {
                    if (response.success) {
                        EauNotifications.success('Success', self.config.strings.requestSent);
                        $('#eau-request-modal-overlay').fadeOut(200);

                        // Reload data
                        self.loadInitialData();

                        // Reload institutions for select to update status
                        if (self.searchDataLoaded) {
                            self.loadInstitutionsForSelect();
                            self.loadPendingRequests();
                        }

                        // Hide preview card
                        self.hideInstitutionPreview();

                        // Reset select
                        $('#eau-institution-select').val('');
                    } else {
                        EauNotifications.error('Error', response.data.message || self.config.strings.error);
                    }
                },
                error: function() {
                    EauNotifications.error('Error', self.config.strings.error);
                },
                complete: function() {
                    $btn.prop('disabled', false).removeClass('eau-loading');
                }
            });
        },

        // === PENDING REQUESTS ===
        loadPendingRequests: function() {
            const self = this;

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_get_my_pending_requests',
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.renderPendingRequests(response.data.requests || []);
                    }
                }
            });
        },

        renderPendingRequests: function(requests) {
            const self = this;

            if (requests.length === 0) {
                $('#eau-pending-requests-body').html(`
                    <div class="eau-empty-state eau-empty-state-sm">
                        <i data-lucide="check-circle"></i>
                        <p>${this.config.strings.noPendingRequests}</p>
                    </div>
                `);
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
                return;
            }

            let html = '<div class="eau-requests-list">';

            requests.forEach(function(req) {
                html += `
                    <div class="eau-request-item">
                        <div class="eau-request-info">
                            <h4 class="eau-request-institution">${self.escapeHtml(req.institution_name)}</h4>
                            <p class="eau-request-date">
                                <i data-lucide="calendar"></i>
                                Requested: ${req.request_date_formatted}
                            </p>
                        </div>
                        <div class="eau-request-actions">
                            <span class="eau-badge eau-badge-warning">
                                <i data-lucide="clock"></i>
                                Pending
                            </span>
                            <button type="button" class="eau-btn eau-btn-outline-danger eau-btn-sm eau-cancel-request-btn"
                                    data-request-id="${req.request_id}">
                                <i data-lucide="x"></i>
                                Cancel
                            </button>
                        </div>
                    </div>
                `;
            });

            html += '</div>';

            $('#eau-pending-requests-body').html(html);

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        },

        cancelRequest: function(requestId) {
            const self = this;

            EauNotifications.confirm({
                title: 'Cancel Request?',
                message: this.config.strings.confirmCancel,
                type: 'warning',
                confirmText: 'Yes, Cancel',
                cancelText: 'No, Keep It',
                onConfirm: function() {
                    $.ajax({
                        url: self.config.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'eau_cancel_institution_request',
                            nonce: self.config.nonce,
                            request_id: requestId
                        },
                        success: function(response) {
                            if (response.success) {
                                EauNotifications.success('Cancelled', self.config.strings.requestCancelled);
                                self.loadInitialData();

                                // Reload institutions for select to update status
                                if (self.searchDataLoaded) {
                                    self.loadInstitutionsForSelect();
                                    self.loadPendingRequests();
                                }
                            } else {
                                EauNotifications.error('Error', response.data.message || self.config.strings.error);
                            }
                        },
                        error: function() {
                            EauNotifications.error('Error', self.config.strings.error);
                        }
                    });
                }
            });
        },

        // === INCOMING REQUESTS (for institutionAdmin) ===
        loadIncomingRequests: function() {
            const self = this;

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_get_incoming_institution_requests',
                    nonce: this.config.nonce,
                    page: this.incomingPage,
                    per_page: this.incomingPerPage
                },
                success: function(response) {
                    if (response.success) {
                        self.renderIncomingRequests(response.data);
                    }
                }
            });
        },

        renderIncomingRequests: function(data) {
            const self = this;
            const requests = data.requests || [];

            if (requests.length === 0) {
                $('#eau-incoming-requests-body').html(`
                    <div class="eau-empty-state eau-empty-state-sm">
                        <i data-lucide="inbox"></i>
                        <p>${this.config.strings.noIncomingRequests}</p>
                    </div>
                `);
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
                return;
            }

            let html = '<div class="eau-incoming-requests-list">';

            requests.forEach(function(req) {
                html += `
                    <div class="eau-incoming-request-item">
                        <div class="eau-incoming-request-info">
                            <div class="eau-incoming-request-user">
                                <strong>${self.escapeHtml(req.user_name)}</strong>
                                <span class="eau-text-muted">${self.escapeHtml(req.user_email)}</span>
                            </div>
                            <div class="eau-incoming-request-meta">
                                <span><i data-lucide="building-2"></i> Wants to join: <strong>${self.escapeHtml(req.institution_name)}</strong></span>
                                ${req.current_institution ? `<span><i data-lucide="map-pin"></i> Currently at: ${self.escapeHtml(req.current_institution)}</span>` : ''}
                                <span><i data-lucide="calendar"></i> ${req.request_date_formatted}</span>
                            </div>
                        </div>
                        <div class="eau-incoming-request-actions">
                            <button type="button" class="eau-btn eau-btn-primary eau-btn-sm eau-review-request-btn"
                                    data-request='${JSON.stringify(req).replace(/'/g, "&#39;")}'>
                                <i data-lucide="eye"></i>
                                Review
                            </button>
                        </div>
                    </div>
                `;
            });

            html += '</div>';

            // Add pagination if needed
            if (data.total_pages > 1) {
                html += '<div id="eau-incoming-pagination" class="eau-pagination">';
                // Similar pagination as search
                html += '</div>';
            }

            $('#eau-incoming-requests-body').html(html);

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        },

        // === RESPOND MODAL ===
        openRespondModal: function(requestData) {
            this.selectedRequest = requestData;

            $('#eau-respond-user-name').text(requestData.user_name);
            $('#eau-respond-user-email').text(requestData.user_email);
            $('#eau-respond-current-institution').text(requestData.current_institution || 'None');
            $('#eau-respond-request-date').text(requestData.request_date_formatted);
            $('#eau-respond-institution-name').text(requestData.institution_name);
            $('#eau-respond-notes').val('');

            $('#eau-respond-modal-overlay').css('display', 'flex').hide().fadeIn(200);

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        },

        respondToRequest: function(action) {
            const self = this;

            if (!this.selectedRequest) {
                return;
            }

            const notes = $('#eau-respond-notes').val().trim();
            const $approveBtn = $('#eau-approve-request-btn');
            const $rejectBtn = $('#eau-reject-request-btn');

            $approveBtn.prop('disabled', true);
            $rejectBtn.prop('disabled', true);

            if (action === 'approve') {
                $approveBtn.addClass('eau-loading');
            } else {
                $rejectBtn.addClass('eau-loading');
            }

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_respond_institution_request',
                    nonce: this.config.nonce,
                    request_id: this.selectedRequest.request_id,
                    response_action: action,
                    notes: notes
                },
                success: function(response) {
                    if (response.success) {
                        const message = action === 'approve'
                            ? self.config.strings.requestApproved
                            : self.config.strings.requestRejected;
                        EauNotifications.success('Success', message);
                        $('#eau-respond-modal-overlay').fadeOut(200);

                        // v1.66.7 - Recarrega dados da aba Requests além do loadInitialData
                        self.loadStats(); // Atualiza contadores
                        self.loadIncomingRequests(); // Atualiza lista de incoming requests
                        self.loadInstitutionHistory(); // Atualiza histórico da instituição
                    } else {
                        EauNotifications.error('Error', response.data.message || self.config.strings.error);
                    }
                },
                error: function() {
                    EauNotifications.error('Error', self.config.strings.error);
                },
                complete: function() {
                    $approveBtn.prop('disabled', false).removeClass('eau-loading');
                    $rejectBtn.prop('disabled', false).removeClass('eau-loading');
                }
            });
        },

        // === VIEW INSTITUTION DETAILS ===
        viewInstitutionDetails: function(institutionId) {
            const self = this;

            // Find in current institutions first
            const inst = this.currentInstitutions.find(i => i.id == institutionId);

            if (inst) {
                this.renderInstitutionDetails(inst);
            } else {
                // Would need to fetch from server
                $('#eau-view-institution-body').html('<p class="eau-text-muted">Loading...</p>');
                $('#eau-view-institution-modal-overlay').css('display', 'flex').hide().fadeIn(200);
            }
        },

        renderInstitutionDetails: function(inst) {
            const self = this;

            $('#eau-view-institution-title').text(inst.name);

            let html = '<div class="eau-form-grid">';

            const fields = [
                { label: 'Company ID', value: inst.company_id },
                { label: 'Type', value: inst.type },
                { label: 'Status', value: inst.status },
                { label: 'Email', value: inst.email },
                { label: 'Phone', value: inst.phone },
                { label: 'Website', value: inst.website, isLink: true },
                { label: 'Address', value: inst.address, span: 2 },
                { label: 'City', value: inst.city },
                { label: 'State', value: inst.state },
                { label: 'Postcode', value: inst.postcode },
                { label: 'Country', value: inst.country },
            ];

            fields.forEach(function(field) {
                if (field.value) {
                    const spanClass = field.span === 2 ? 'eau-form-field-span-2' : '';
                    let displayValue = self.escapeHtml(field.value);

                    if (field.isLink) {
                        displayValue = `<a href="${displayValue}" target="_blank">${displayValue}</a>`;
                    }

                    html += `
                        <div class="eau-form-field ${spanClass}">
                            <label class="eau-form-label">${field.label}</label>
                            <p class="eau-form-static">${displayValue}</p>
                        </div>
                    `;
                }
            });

            html += '</div>';

            $('#eau-view-institution-body').html(html);
            $('#eau-view-institution-modal-overlay').css('display', 'flex').hide().fadeIn(200);

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        },

        // === LEAVE INSTITUTION ===
        openLeaveModal: function(institutionId, institutionName) {
            this.selectedInstitution = {
                id: institutionId,
                name: institutionName
            };

            $('#eau-leave-institution-name').text(institutionName);
            $('#eau-leave-modal-overlay').css('display', 'flex').hide().fadeIn(200);

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        },

        leaveInstitution: function() {
            const self = this;

            if (!this.selectedInstitution) {
                return;
            }

            const $btn = $('#eau-confirm-leave-btn');
            $btn.prop('disabled', true).addClass('eau-loading');

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_leave_institution',
                    nonce: this.config.nonce,
                    institution_id: this.selectedInstitution.id
                },
                success: function(response) {
                    if (response.success) {
                        EauNotifications.success('Success', self.config.strings.leftInstitution);
                        $('#eau-leave-modal-overlay').fadeOut(200);
                        self.loadInitialData();
                    } else {
                        EauNotifications.error('Error', response.data.message || self.config.strings.error);
                    }
                },
                error: function() {
                    EauNotifications.error('Error', self.config.strings.error);
                },
                complete: function() {
                    $btn.prop('disabled', false).removeClass('eau-loading');
                }
            });
        },

        // === MY REQUEST HISTORY ===
        loadMyHistory: function() {
            const self = this;

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_get_my_request_history',
                    nonce: this.config.nonce,
                    page: this.myHistoryPage,
                    per_page: this.myHistoryPerPage
                },
                success: function(response) {
                    if (response.success) {
                        self.renderMyHistory(response.data);
                    }
                }
            });
        },

        renderMyHistory: function(data) {
            const self = this;
            const requests = data.requests || [];

            if (requests.length === 0) {
                $('#eau-my-history-body').html(`
                    <div class="eau-empty-state eau-empty-state-sm">
                        <i data-lucide="file-clock"></i>
                        <p>${this.config.strings.noHistory}</p>
                    </div>
                `);
                $('#eau-my-history-pagination').hide();
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
                return;
            }

            let html = '<div class="eau-history-list">';

            requests.forEach(function(req) {
                const statusIcon = self.getStatusIcon(req.status);

                html += `
                    <div class="eau-history-item eau-history-item-${req.status}">
                        <div class="eau-history-item-header">
                            <div class="eau-history-item-institution">
                                <i data-lucide="building-2"></i>
                                <strong>${self.escapeHtml(req.institution_name)}</strong>
                            </div>
                            <span class="eau-badge eau-badge-${req.status_class}">
                                ${statusIcon}
                                ${req.status_label}
                            </span>
                        </div>
                        <div class="eau-history-item-details">
                            <div class="eau-history-item-dates">
                                <span><i data-lucide="calendar"></i> Requested: ${req.request_date_formatted}</span>
                                ${req.response_date_formatted ? `<span><i data-lucide="calendar-check"></i> Responded: ${req.response_date_formatted}</span>` : ''}
                                ${req.responded_by_name ? `<span><i data-lucide="user"></i> By: ${self.escapeHtml(req.responded_by_name)}</span>` : ''}
                            </div>
                            ${req.notes ? `
                                <div class="eau-history-item-notes">
                                    <i data-lucide="message-square"></i>
                                    <span>${self.escapeHtml(req.notes)}</span>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                `;
            });

            html += '</div>';

            $('#eau-my-history-body').html(html);

            // Render pagination
            if (data.total_pages > 1) {
                this.renderHistoryPagination(data, '#eau-my-history-pagination');
            } else {
                $('#eau-my-history-pagination').hide();
            }

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        },

        // === INSTITUTION REQUEST HISTORY (for institutionAdmin) ===
        loadInstitutionHistory: function() {
            const self = this;

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_get_institution_request_history',
                    nonce: this.config.nonce,
                    page: this.institutionHistoryPage,
                    per_page: this.institutionHistoryPerPage
                },
                success: function(response) {
                    if (response.success) {
                        self.renderInstitutionHistory(response.data);
                    }
                }
            });
        },

        renderInstitutionHistory: function(data) {
            const self = this;
            const requests = data.requests || [];

            if (requests.length === 0) {
                $('#eau-institution-history-body').html(`
                    <div class="eau-empty-state eau-empty-state-sm">
                        <i data-lucide="history"></i>
                        <p>${this.config.strings.noInstitutionHistory}</p>
                    </div>
                `);
                $('#eau-institution-history-pagination').hide();
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
                return;
            }

            let html = '<div class="eau-history-list">';

            requests.forEach(function(req) {
                const statusIcon = self.getStatusIcon(req.status);

                html += `
                    <div class="eau-history-item eau-history-item-${req.status}">
                        <div class="eau-history-item-header">
                            <div class="eau-history-item-user">
                                <i data-lucide="user"></i>
                                <div>
                                    <strong>${self.escapeHtml(req.user_name)}</strong>
                                    <span class="eau-text-muted">${self.escapeHtml(req.user_email)}</span>
                                </div>
                            </div>
                            <span class="eau-badge eau-badge-${req.status_class}">
                                ${statusIcon}
                                ${req.status_label}
                            </span>
                        </div>
                        <div class="eau-history-item-details">
                            <div class="eau-history-item-meta">
                                <span><i data-lucide="building-2"></i> ${self.escapeHtml(req.institution_name)}</span>
                            </div>
                            <div class="eau-history-item-dates">
                                <span><i data-lucide="calendar"></i> Requested: ${req.request_date_formatted}</span>
                                ${req.response_date_formatted ? `<span><i data-lucide="calendar-check"></i> Responded: ${req.response_date_formatted}</span>` : ''}
                                ${req.responded_by_name ? `<span><i data-lucide="user-check"></i> By: ${self.escapeHtml(req.responded_by_name)}</span>` : ''}
                            </div>
                            ${req.notes ? `
                                <div class="eau-history-item-notes">
                                    <i data-lucide="message-square"></i>
                                    <span>${self.escapeHtml(req.notes)}</span>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                `;
            });

            html += '</div>';

            $('#eau-institution-history-body').html(html);

            // Render pagination
            if (data.total_pages > 1) {
                this.renderHistoryPagination(data, '#eau-institution-history-pagination');
            } else {
                $('#eau-institution-history-pagination').hide();
            }

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        },

        renderHistoryPagination: function(data, container) {
            let html = '<div class="eau-pagination">';

            // Previous
            if (data.page > 1) {
                html += `<button class="eau-pagination-btn" data-page="${data.page - 1}"><i data-lucide="chevron-left"></i></button>`;
            }

            // Pages
            for (let i = 1; i <= data.total_pages; i++) {
                if (i === data.page) {
                    html += `<button class="eau-pagination-btn eau-pagination-btn-active">${i}</button>`;
                } else if (i <= 2 || i > data.total_pages - 2 || Math.abs(i - data.page) <= 1) {
                    html += `<button class="eau-pagination-btn" data-page="${i}">${i}</button>`;
                } else if (i === 3 && data.page > 4) {
                    html += '<span class="eau-pagination-ellipsis">...</span>';
                } else if (i === data.total_pages - 2 && data.page < data.total_pages - 3) {
                    html += '<span class="eau-pagination-ellipsis">...</span>';
                }
            }

            // Next
            if (data.page < data.total_pages) {
                html += `<button class="eau-pagination-btn" data-page="${data.page + 1}"><i data-lucide="chevron-right"></i></button>`;
            }

            html += '</div>';

            $(container).html(html).show();

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        },

        getStatusIcon: function(status) {
            const icons = {
                'approved': '<i data-lucide="check-circle"></i>',
                'rejected': '<i data-lucide="x-circle"></i>',
                'cancelled': '<i data-lucide="minus-circle"></i>',
                'pending': '<i data-lucide="clock"></i>',
                // v1.67.1 - Member-institution history statuses
                'linked': '<i data-lucide="user-plus"></i>',
                'unlinked': '<i data-lucide="user-minus"></i>',
                'transferred': '<i data-lucide="arrow-right-left"></i>'
            };
            return icons[status] || '';
        },

        // === EDIT INSTITUTION (for institutionAdmin) ===
        openEditInstitutionModal: function(institutionId) {
            const self = this;

            // Show modal with loading state
            $('#eau-edit-institution-title').text('Edit Institution');
            $('#eau-edit-institution-form')[0].reset();
            $('#eau-edit-institution-modal-overlay').css('display', 'flex').hide().fadeIn(200);

            // Initialize phone input
            setTimeout(function() {
                self.initPhoneInput();
            }, 100);

            // Load institution data
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_get_institution_for_edit',
                    nonce: this.config.nonce,
                    institution_id: institutionId
                },
                success: function(response) {
                    if (response.success) {
                        self.populateEditForm(response.data);
                    } else {
                        EauNotifications.error('Error', response.data.message || self.config.strings.error);
                        self.closeEditModal();
                    }
                },
                error: function() {
                    EauNotifications.error('Error', self.config.strings.error);
                    self.closeEditModal();
                }
            });

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        },

        populateEditForm: function(data) {
            const self = this;

            $('#eau-edit-institution-id').val(data.id);
            $('#eau-edit-institution-title').text('Edit: ' + data.ins_company_name);
            $('#eau-edit-ins_company_id').val(data.ins_company_id);
            $('#eau-edit-ins_company_name').val(data.ins_company_name);
            $('#eau-edit-ins_company_email').val(data.ins_company_email);
            $('#eau-edit-ins_company_company_address_line_1').val(data.ins_company_company_address_line_1);
            $('#eau-edit-ins_company_company_postcode').val(data.ins_company_company_postcode);
            $('#eau-edit-ins_status').val(data.ins_status || 'active');

            // Set phone with intl-tel-input
            if (this.phoneIti && data.ins_company_company_phone) {
                this.phoneIti.setNumber(data.ins_company_company_phone);
            } else {
                $('#eau-edit-ins_company_company_phone').val(data.ins_company_company_phone || '');
            }

            // Set country and trigger cascade
            const countryCode = data.ins_company_company_country || '';
            $('#eau-edit-ins_company_company_country').val(countryCode);

            // Load states if country is set (pass city for cascade)
            if (countryCode) {
                this.loadStatesForCountry(countryCode, data.ins_company_company_state, data.ins_company_company_suburb);
            } else {
                // No country - show text inputs
                $('#eau-edit-ins_company_company_state').hide();
                $('#eau-edit-ins_company_company_state_text').show().val(data.ins_company_company_state || '');
                $('#eau-edit-ins_company_company_suburb').hide();
                $('#eau-edit-ins_company_company_suburb_text').show().val(data.ins_company_company_suburb || '');
            }

            // Set logo if exists
            if (data.ins_company_logo) {
                this.setMediaUploadValue(data.ins_company_logo, data.ins_company_logo_url);
            }

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        },

        // Set media upload value
        setMediaUploadValue: function(value, url) {
            const $wrapper = $('#eau-edit-ins_company_logo').closest('.eau-media-upload-wrapper');
            if ($wrapper.length && value) {
                // Update hidden input
                $wrapper.find('input[name="ins_company_logo"]').val(value);
                $wrapper.find('input[name="ins_company_logo_type"]').val('media');

                // Show preview if it's an image URL
                if (url) {
                    const $preview = $wrapper.find('.eau-media-upload-preview');
                    const $thumbnail = $preview.find('.eau-media-upload-preview-thumbnail');
                    const filename = url.split('/').pop();

                    if (this.isImageUrl(url)) {
                        $thumbnail.addClass('has-image').html('<img src="' + url + '" alt="Logo">');
                    } else {
                        $thumbnail.removeClass('has-image').html('<i data-lucide="file"></i>');
                    }

                    $preview.find('.eau-media-upload-preview-filename').text(filename);
                    $preview.find('.eau-media-upload-preview-link').attr('href', url);
                    $preview.show();
                    $wrapper.find('.eau-media-upload-tabs, .eau-media-upload-panel').hide();

                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                }
            }
        },

        // Check if URL is an image
        isImageUrl: function(url) {
            if (!url) return false;
            const imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp'];
            const ext = url.split('.').pop().toLowerCase().split('?')[0];
            return imageExtensions.includes(ext);
        },

        saveInstitution: function() {
            const self = this;
            const $form = $('#eau-edit-institution-form');

            // Validate form
            if (!$form[0].checkValidity()) {
                $form[0].reportValidity();
                return;
            }

            const institutionId = $('#eau-edit-institution-id').val();
            const fields = {};

            // Basic fields
            fields['ins_company_name'] = $('#eau-edit-ins_company_name').val();
            fields['ins_company_email'] = $('#eau-edit-ins_company_email').val();
            fields['ins_company_company_address_line_1'] = $('#eau-edit-ins_company_company_address_line_1').val();
            fields['ins_company_company_postcode'] = $('#eau-edit-ins_company_company_postcode').val();
            fields['ins_status'] = $('#eau-edit-ins_status').val();
            fields['ins_company_company_country'] = $('#eau-edit-ins_company_company_country').val();

            // Phone with intl-tel-input
            if (this.phoneIti) {
                fields['ins_company_company_phone'] = this.phoneIti.getNumber();
            } else {
                fields['ins_company_company_phone'] = $('#eau-edit-ins_company_company_phone').val();
            }

            // State - check if select or text
            const $stateSelect = $('#eau-edit-ins_company_company_state');
            const $stateText = $('#eau-edit-ins_company_company_state_text');
            if ($stateSelect.is(':visible')) {
                fields['ins_company_company_state'] = $stateSelect.val();
            } else {
                fields['ins_company_company_state'] = $stateText.val();
            }

            // City - check if select or text
            const $citySelect = $('#eau-edit-ins_company_company_suburb');
            const $cityText = $('#eau-edit-ins_company_company_suburb_text');
            if ($citySelect.is(':visible')) {
                fields['ins_company_company_suburb'] = $citySelect.val();
            } else {
                fields['ins_company_company_suburb'] = $cityText.val();
            }

            // Logo
            const $logoWrapper = $('#eau-edit-ins_company_logo').closest('.eau-media-upload-wrapper');
            const logoValue = $logoWrapper.find('input[name="ins_company_logo"]').val();
            if (logoValue) {
                fields['ins_company_logo'] = logoValue;
            }

            const $btn = $('#eau-save-institution-btn');
            $btn.prop('disabled', true).addClass('eau-loading');

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_update_my_institution',
                    nonce: this.config.nonce,
                    institution_id: institutionId,
                    fields: fields
                },
                success: function(response) {
                    if (response.success) {
                        EauNotifications.success('Success', self.config.strings.institutionUpdated);
                        self.closeEditModal();
                        // Reload institution details tab
                        self.loadInstitutionDetailsTab();
                    } else {
                        EauNotifications.error('Error', response.data.message || self.config.strings.error);
                    }
                },
                error: function() {
                    EauNotifications.error('Error', self.config.strings.error);
                },
                complete: function() {
                    $btn.prop('disabled', false).removeClass('eau-loading');
                }
            });
        },

        // === UTILITIES ===
        escapeHtml: function(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        if ($('#eau-my-institution-container').length) {
            MyInstitutionController.init();
        }
    });

})(jQuery);
