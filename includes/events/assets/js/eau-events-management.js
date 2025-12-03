/**
 * Eau Events Management - Frontend JavaScript
 *
 * @package EauSystem
 * @since 1.28.1
 */

(function($) {
    'use strict';

    const EauEventsManagement = {
        // State
        currentPage: 1,
        perPage: 20,
        searchTerm: '',
        statusFilter: '',
        orderBy: 'start_datetime',
        order: 'ASC',
        quillEditor: null,

        /**
         * Initialize
         */
        init: function() {
            this.initQuillEditor();
            this.bindEvents();
            this.loadEvents();
        },

        /**
         * Initialize Quill Editor
         */
        initQuillEditor: function() {
            if (typeof Quill === 'undefined') {
                console.warn('Quill not loaded');
                return;
            }

            this.quillEditor = new Quill('#eau-quill-editor', {
                theme: 'snow',
                placeholder: 'Enter full event description...',
                modules: {
                    toolbar: [
                        [{ 'header': [1, 2, 3, false] }],
                        ['bold', 'italic', 'underline', 'strike'],
                        [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                        [{ 'indent': '-1'}, { 'indent': '+1' }],
                        ['link'],
                        ['clean']
                    ]
                }
            });

            // Sync Quill content to hidden input on text change
            this.quillEditor.on('text-change', () => {
                const html = this.quillEditor.root.innerHTML;
                $('#eau-edit-full_description').val(html === '<p><br></p>' ? '' : html);
            });
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            // Search
            let searchTimeout;
            $('#eau-events-search').on('input', (e) => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    this.searchTerm = e.target.value;
                    this.currentPage = 1;
                    this.loadEvents();
                }, 300);
            });

            // Status filter
            $('#eau-events-status-filter').on('change', (e) => {
                this.statusFilter = e.target.value;
                this.currentPage = 1;
                this.loadEvents();
            });

            // Sortable columns
            $(document).on('click', '.eau-sortable', (e) => {
                const $th = $(e.currentTarget);
                const sort = $th.data('sort');

                if (this.orderBy === sort) {
                    this.order = this.order === 'ASC' ? 'DESC' : 'ASC';
                } else {
                    this.orderBy = sort;
                    this.order = 'ASC';
                }

                this.loadEvents();
            });

            // Action buttons
            $(document).on('click', '.eau-action-view', (e) => {
                const url = $(e.currentTarget).data('url');
                window.open(url, '_blank');
            });

            $(document).on('click', '.eau-action-edit', (e) => {
                const eventId = $(e.currentTarget).data('id');
                this.openEditModal(eventId);
            });

            // 3 dots menu
            $(document).on('click', '.eau-action-more', (e) => {
                e.stopPropagation();
                const $menu = $(e.currentTarget).siblings('.eau-dropdown-menu');
                $('.eau-dropdown-menu').not($menu).removeClass('active');
                $menu.toggleClass('active');
            });

            // Close dropdown on click outside
            $(document).on('click', () => {
                $('.eau-dropdown-menu').removeClass('active');
            });

            // Dropdown actions
            $(document).on('click', '.eau-dropdown-item', (e) => {
                e.stopPropagation();
                const $item = $(e.currentTarget);
                const action = $item.data('action');
                const eventId = $item.closest('.eau-dropdown').data('id');

                $('.eau-dropdown-menu').removeClass('active');

                switch (action) {
                    case 'unpublish':
                        this.toggleStatus(eventId);
                        break;
                    case 'duplicate':
                        this.duplicateEvent(eventId);
                        break;
                    case 'registrations':
                        this.viewRegistrations(eventId);
                        break;
                    case 'delete':
                        this.deleteEvent(eventId);
                        break;
                }
            });

            // Create Event button
            $('#eau-create-event-btn').on('click', () => {
                this.openCreateModal();
            });

            // Modal close buttons
            $(document).on('click', '#eau-modal-close, #eau-modal-cancel', () => {
                this.closeModal();
            });

            // Modal overlay click - only close if clicking directly on overlay, not children
            $(document).on('click', '.eau-modal-overlay', (e) => {
                if ($(e.target).hasClass('eau-modal-overlay')) {
                    this.closeModal();
                }
            });

            $(document).on('click', '#eau-modal-save', () => {
                this.saveEvent();
            });

            // Modal tabs
            $(document).on('click', '.eau-modal-tab-btn', (e) => {
                const tab = $(e.currentTarget).data('tab');
                $('.eau-modal-tab-btn').removeClass('active');
                $(e.currentTarget).addClass('active');
                $('.eau-modal-tab-content').removeClass('active');
                $(`.eau-modal-tab-content[data-tab="${tab}"]`).addClass('active');
            });

            // Auto-fill CPD points when category is selected
            $('#eau-edit-cpd_category').on('change', (e) => {
                const $selected = $(e.target).find('option:selected');
                const points = $selected.data('points');
                if (points) {
                    $('#eau-edit-cpd_points').val(points);
                }
            });

            // Event type radio - show/hide location fields
            $('input[name="event_type"]').on('change', (e) => {
                this.toggleLocationFields(e.target.value);
            });

            // Media Upload Component Events
            this.bindMediaUploadEvents();

            // Force links with eau-no-lightbox class to open in new tab (bypass any lightbox plugins)
            $(document).on('click', '.eau-no-lightbox', function(e) {
                e.stopPropagation();
                const href = $(this).attr('href');
                if (href && href !== '#') {
                    window.open(href, '_blank');
                    e.preventDefault();
                }
            });

            // Pagination
            $(document).on('click', '.eau-pagination-btn:not(.disabled)', (e) => {
                const page = $(e.currentTarget).data('page');
                if (page) {
                    this.currentPage = page;
                    this.loadEvents();
                }
            });
        },

        /**
         * Load events via AJAX
         */
        loadEvents: function() {
            const $tbody = $('#eau-events-tbody');
            $tbody.html('<tr><td colspan="6" class="eau-loading-cell"><div class="eau-skeleton-row"></div></td></tr>');

            $.ajax({
                url: eauEventsManagement.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_get_events',
                    nonce: eauEventsManagement.nonce,
                    page: this.currentPage,
                    per_page: this.perPage,
                    search: this.searchTerm,
                    status: this.statusFilter,
                    orderby: this.orderBy,
                    order: this.order
                },
                success: (response) => {
                    if (response.success) {
                        this.renderTable(response.data.rows);
                        this.renderPagination(response.data);
                    } else {
                        $tbody.html('<tr><td colspan="6" class="eau-empty-cell">Error loading events</td></tr>');
                    }
                    lucide.createIcons();
                },
                error: () => {
                    $tbody.html('<tr><td colspan="6" class="eau-empty-cell">Error loading events</td></tr>');
                }
            });
        },

        /**
         * Render table rows
         */
        renderTable: function(rows) {
            const $tbody = $('#eau-events-tbody');

            if (!rows || rows.length === 0) {
                $tbody.html('<tr><td colspan="6" class="eau-empty-cell">No events found</td></tr>');
                return;
            }

            let html = '';
            rows.forEach(row => {
                const statusClass = row.status_class === 'success' ? 'eau-badge-success' : 'eau-badge-warning';
                const toggleText = row.status === 'Published' ? 'Unpublish' : 'Publish';

                html += `
                    <tr data-slug="${this.escapeHtml(row.slug)}">
                        <td class="eau-event-title-cell">
                            <span class="eau-event-title">${this.escapeHtml(row.title)}</span>
                        </td>
                        <td class="eau-event-date-cell">
                            <span class="eau-date-main">${row.date}</span>
                            <span class="eau-date-time">${row.time}</span>
                        </td>
                        <td>${row.location}</td>
                        <td>${row.capacity}</td>
                        <td>
                            <span class="eau-badge ${statusClass}">${row.status}</span>
                        </td>
                        <td class="eau-table-actions-col">
                            <div class="eau-table-actions">
                                <button class="eau-action-btn eau-action-view" data-url="${row.view_url}" title="View">
                                    <i data-lucide="eye"></i>
                                </button>
                                <button class="eau-action-btn eau-action-edit" data-id="${row.id}" title="Edit">
                                    <i data-lucide="edit"></i>
                                </button>
                                <div class="eau-dropdown" data-id="${row.id}">
                                    <button class="eau-action-btn eau-action-more" title="More">
                                        <i data-lucide="more-vertical"></i>
                                    </button>
                                    <div class="eau-dropdown-menu">
                                        <button class="eau-dropdown-item" data-action="unpublish">
                                            <i data-lucide="eye-off"></i> ${toggleText}
                                        </button>
                                        <button class="eau-dropdown-item" data-action="duplicate">
                                            <i data-lucide="copy"></i> Duplicate
                                        </button>
                                        <button class="eau-dropdown-item" data-action="registrations">
                                            <i data-lucide="users"></i> View Registrations
                                        </button>
                                        <button class="eau-dropdown-item eau-dropdown-item-danger" data-action="delete">
                                            <i data-lucide="trash-2"></i> Delete
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                `;
            });

            $tbody.html(html);
        },

        /**
         * Render pagination
         */
        renderPagination: function(data) {
            const $container = $('#eau-pagination-container');

            if (data.total_pages <= 1) {
                $container.html('');
                return;
            }

            let html = '<div class="eau-pagination">';

            // Prev button
            html += `<button class="eau-pagination-btn ${data.page <= 1 ? 'disabled' : ''}" data-page="${data.page - 1}">
                <i data-lucide="chevron-left"></i>
            </button>`;

            // Page numbers
            for (let i = 1; i <= data.total_pages; i++) {
                if (i === 1 || i === data.total_pages || (i >= data.page - 2 && i <= data.page + 2)) {
                    html += `<button class="eau-pagination-btn ${i === data.page ? 'active' : ''}" data-page="${i}">${i}</button>`;
                } else if (i === data.page - 3 || i === data.page + 3) {
                    html += '<span class="eau-pagination-ellipsis">...</span>';
                }
            }

            // Next button
            html += `<button class="eau-pagination-btn ${data.page >= data.total_pages ? 'disabled' : ''}" data-page="${data.page + 1}">
                <i data-lucide="chevron-right"></i>
            </button>`;

            html += '</div>';

            $container.html(html);
            lucide.createIcons();
        },

        /**
         * Open create modal
         */
        openCreateModal: function() {
            const $modal = $('#eau-event-edit-modal');
            const $modalBody = $modal.find('.eau-modal-body');
            const $modalFooter = $modal.find('.eau-modal-footer');

            // Reset form and show first tab
            $('#eau-event-edit-form')[0].reset();
            $('.eau-modal-tab-btn').removeClass('active').first().addClass('active');
            $('.eau-modal-tab-content').removeClass('active').first().addClass('active');

            // Set mode to create
            $('#eau-edit-mode').val('create');
            $('#eau-edit-event-id').val('');
            $('#eau-modal-title').text('Create Event');
            $('#eau-modal-save').html('<i data-lucide="save"></i> Create Event');

            // Set default values
            $('#eau-edit-timezone').val('Australia/Sydney');
            $('input[name="event_type"][value="in-person"]').prop('checked', true);
            $('#eau-edit-country').val('Australia');
            $('#eau-edit-visibility').val('public');

            // Clear image - using Eau Media Upload component
            this.clearMediaUpload($('#eau-edit-image_id-wrapper'));

            // Clear Quill editor
            if (this.quillEditor) {
                this.quillEditor.setContents([]);
                $('#eau-edit-full_description').val('');
            }

            // Show correct location fields for default type
            this.toggleLocationFields('in-person');

            // Show modal with loading state
            $modalBody.addClass('eau-modal-loading');
            $modalFooter.addClass('eau-modal-loading');
            $modal.addClass('active');

            // Remove loading after 1 second
            setTimeout(() => {
                $modalBody.removeClass('eau-modal-loading');
                $modalFooter.removeClass('eau-modal-loading');
                lucide.createIcons();
            }, 1000);
        },

        /**
         * Open edit modal
         */
        openEditModal: function(eventId) {
            const $modal = $('#eau-event-edit-modal');
            const $modalBody = $modal.find('.eau-modal-body');
            const $modalFooter = $modal.find('.eau-modal-footer');

            // Reset form and show first tab
            $('#eau-event-edit-form')[0].reset();
            $('.eau-modal-tab-btn').removeClass('active').first().addClass('active');
            $('.eau-modal-tab-content').removeClass('active').first().addClass('active');

            // Set mode to edit
            $('#eau-edit-mode').val('edit');
            $('#eau-modal-title').text('Edit Event');
            $('#eau-modal-save').html('<i data-lucide="save"></i> Save Changes');

            // Show modal with loading state
            $modalBody.addClass('eau-modal-loading');
            $modalFooter.addClass('eau-modal-loading');
            $modal.addClass('active');

            // Load event data after 1 second delay
            setTimeout(() => {
                $.ajax({
                    url: eauEventsManagement.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'eau_get_event',
                        nonce: eauEventsManagement.nonce,
                        event_id: eventId
                    },
                    success: (response) => {
                        // Remove loading state
                        $modalBody.removeClass('eau-modal-loading');
                        $modalFooter.removeClass('eau-modal-loading');

                        if (response.success) {
                            this.populateModal(response.data.event, eventId);
                        } else {
                            this.showToast('Error loading event', 'error');
                            this.closeModal();
                        }
                        lucide.createIcons();
                    },
                    error: () => {
                        $modalBody.removeClass('eau-modal-loading');
                        $modalFooter.removeClass('eau-modal-loading');
                        this.showToast('Error loading event', 'error');
                        this.closeModal();
                    }
                });
            }, 1000);
        },

        /**
         * Populate modal with event data
         */
        populateModal: function(event, eventId) {
            $('#eau-edit-event-id').val(eventId);
            $('#eau-edit-title').val(event.title || '');
            $('#eau-edit-short_description').val(event.short_description || '');

            // Load Full Description into Quill
            if (this.quillEditor) {
                const fullDescription = event.full_description || '';
                if (fullDescription) {
                    this.quillEditor.root.innerHTML = fullDescription;
                } else {
                    this.quillEditor.setContents([]);
                }
                $('#eau-edit-full_description').val(fullDescription);
            }
            $('#eau-edit-start_datetime').val(event.start_datetime || '');
            $('#eau-edit-end_datetime').val(event.end_datetime || '');
            $('#eau-edit-timezone').val(event.timezone || 'Australia/Sydney');
            $('#eau-edit-venue_name').val(event.venue_name || '');
            $('#eau-edit-address').val(event.address || '');
            $('#eau-edit-city').val(event.city || '');
            $('#eau-edit-state').val(event.state || '');
            $('#eau-edit-postal_code').val(event.postal_code || '');
            $('#eau-edit-country').val(event.country || 'Australia');
            $('#eau-edit-virtual_url').val(event.virtual_url || '');
            $('#eau-edit-capacity').val(event.capacity || '');
            $('#eau-edit-member_price').val(event.member_price || '');
            $('#eau-edit-early_bird_price').val(event.early_bird_price || '');
            $('#eau-edit-early_bird_end_date').val(event.early_bird_end_date || '');
            $('#eau-edit-cpd_points').val(event.cpd_points || '');
            $('#eau-edit-cpd_category').val(event.cpd_category || '');
            $('#eau-edit-visibility').val(event.visibility || 'public');

            // Radio buttons
            const eventType = event.event_type || 'in-person';
            $(`input[name="event_type"][value="${eventType}"]`).prop('checked', true);

            // Toggle location fields based on event type
            this.toggleLocationFields(eventType);

            // Checkboxes
            $('#eau-edit-require_approval').prop('checked', event.require_approval === '1');

            // Image - using Eau Media Upload component
            const $imageWrapper = $('#eau-edit-image_id-wrapper');
            if (event.image_id && event.image_url) {
                this.setMediaValue($imageWrapper, event.image_id, 'media', event.image_url.split('/').pop(), event.image_url);
            } else {
                this.clearMediaUpload($imageWrapper);
            }
        },

        /**
         * Close modal
         */
        closeModal: function() {
            $('#eau-event-edit-modal').removeClass('active');
            // Reset save button state
            const $saveBtn = $('#eau-modal-save');
            $saveBtn.prop('disabled', false).html('<i data-lucide="check"></i> Save Event');
            lucide.createIcons();
        },

        /**
         * Toggle location fields based on event type
         */
        toggleLocationFields: function(eventType) {
            const $virtualFields = $('.eau-location-virtual');
            const $inPersonFields = $('.eau-location-in-person');

            switch (eventType) {
                case 'virtual':
                    $virtualFields.show();
                    $inPersonFields.hide();
                    break;
                case 'in-person':
                    $virtualFields.hide();
                    $inPersonFields.show();
                    break;
                case 'hybrid':
                    $virtualFields.show();
                    $inPersonFields.show();
                    break;
                default:
                    $virtualFields.hide();
                    $inPersonFields.show();
            }
        },

        /**
         * Bind Media Upload Component Events
         */
        bindMediaUploadEvents: function() {
            const self = this;

            // Media upload tabs
            $(document).on('click', '.eau-media-upload-tab', function() {
                const tab = $(this).data('tab');
                const wrapper = $(this).closest('.eau-media-upload-wrapper');

                wrapper.find('.eau-media-upload-tab').removeClass('eau-media-upload-tab-active');
                $(this).addClass('eau-media-upload-tab-active');

                wrapper.find('.eau-media-upload-panel').removeClass('active');
                if (tab === 'url') {
                    wrapper.find('.eau-media-upload-url-panel').addClass('active');
                } else if (tab === 'upload') {
                    wrapper.find('.eau-media-upload-file-panel').addClass('active');
                } else if (tab === 'myfiles') {
                    wrapper.find('.eau-media-upload-myfiles-panel').addClass('active');
                    self.loadUserFiles(wrapper);
                }

                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
            });

            // Media upload - URL input
            $(document).on('input blur', '.eau-media-upload-url-input', function() {
                const wrapper = $(this).closest('.eau-media-upload-wrapper');
                const url = $(this).val().trim();

                if (url) {
                    self.setMediaValue(wrapper, url, 'url', url.split('/').pop() || url);
                } else {
                    self.clearMediaUpload(wrapper);
                }
            });

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

            // Media upload - File input change
            $(document).on('change', '.eau-media-upload-file-input', function() {
                const wrapper = $(this).closest('.eau-media-upload-wrapper');
                const file = this.files[0];

                if (file) {
                    self.uploadFile(wrapper, file);
                }

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

            // My Files - Search input
            $(document).on('input', '.eau-media-upload-myfiles-search-input', function() {
                const wrapper = $(this).closest('.eau-media-upload-wrapper');
                clearTimeout(wrapper.data('search-timeout'));

                const timeout = setTimeout(function() {
                    self.loadUserFiles(wrapper);
                }, 300);

                wrapper.data('search-timeout', timeout);
            });

            // My Files - Select file
            $(document).on('click', '.eau-media-upload-file-item', function() {
                const wrapper = $(this).closest('.eau-media-upload-wrapper');
                const fileId = $(this).data('id');
                const fileUrl = $(this).data('url');
                const fileName = $(this).data('filename');

                wrapper.find('.eau-media-upload-file-item').removeClass('selected');
                $(this).addClass('selected');

                self.setMediaValue(wrapper, fileId, 'media', fileName, fileUrl);
            });
        },

        /**
         * Upload file via AJAX
         */
        uploadFile: function(wrapper, file) {
            const self = this;
            const maxSize = parseInt(wrapper.data('max-file-size')) || 5242880; // 5MB
            const allowedExtensions = wrapper.data('allowed-extensions') || '';

            // Validate file size
            if (file.size > maxSize) {
                this.showToast('File is too large. Maximum size is ' + this.formatFileSize(maxSize), 'error');
                return;
            }

            // Validate extension
            if (allowedExtensions) {
                const allowed = allowedExtensions.toLowerCase().split(',');
                const ext = file.name.split('.').pop().toLowerCase();

                if (!allowed.includes(ext)) {
                    this.showToast('File type not allowed. Allowed: ' + allowed.map(e => e.toUpperCase()).join(', '), 'error');
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
            formData.append('action', 'eau_upload_file');
            formData.append('nonce', eauEventsManagement.nonce);
            formData.append('file', file);
            formData.append('max_size', maxSize);
            formData.append('allowed_extensions', allowedExtensions);

            // Upload via AJAX
            $.ajax({
                url: eauEventsManagement.ajaxUrl,
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
                        self.setMediaValue(wrapper, response.data.id, 'media', response.data.filename, response.data.url);
                        self.showToast('Image uploaded successfully', 'success');
                    } else {
                        self.showToast(response.data.message || 'Upload failed', 'error');
                    }
                },
                error: function() {
                    self.showToast('Upload failed. Please try again.', 'error');
                },
                complete: function() {
                    progressContainer.hide();
                    dropzoneContent.show();
                }
            });
        },

        /**
         * Load user's files for My Files panel
         */
        loadUserFiles: function(wrapper) {
            const self = this;
            const list = wrapper.find('.eau-media-upload-myfiles-list');
            const search = wrapper.find('.eau-media-upload-myfiles-search-input').val() || '';

            list.html(
                '<div class="eau-media-upload-myfiles-loading">' +
                '<i data-lucide="loader-2" class="eau-spin"></i>' +
                '<span>Loading files...</span>' +
                '</div>'
            );

            $.ajax({
                url: eauEventsManagement.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_get_user_media',
                    nonce: eauEventsManagement.nonce,
                    search: search,
                    type: 'image'
                },
                success: function(response) {
                    if (response.success && response.data.files.length > 0) {
                        self.renderUserFiles(wrapper, response.data.files);
                    } else {
                        list.html('<div class="eau-media-upload-myfiles-empty">No images found</div>');
                    }
                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                },
                error: function() {
                    list.html('<div class="eau-media-upload-myfiles-empty">Error loading files</div>');
                }
            });
        },

        /**
         * Render user files in My Files panel
         */
        renderUserFiles: function(wrapper, files) {
            const list = wrapper.find('.eau-media-upload-myfiles-list');
            let html = '<div class="eau-media-upload-myfiles-grid">';

            files.forEach(function(file) {
                const isImage = ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(file.ext.toLowerCase());
                html += '<div class="eau-media-upload-file-item" data-id="' + file.id + '" data-url="' + file.url + '" data-filename="' + file.filename + '">';
                if (isImage) {
                    html += '<div class="eau-media-upload-file-thumb"><img src="' + file.url + '" alt="' + file.filename + '"></div>';
                } else {
                    html += '<div class="eau-media-upload-file-thumb"><i data-lucide="file"></i></div>';
                }
                html += '<span class="eau-media-upload-file-name">' + file.filename + '</span>';
                html += '</div>';
            });

            html += '</div>';
            list.html(html);
        },

        /**
         * Set media value in component
         */
        setMediaValue: function(wrapper, value, type, filename, url) {
            const valueInput = wrapper.find('.eau-media-upload-value');
            const typeInput = wrapper.find('.eau-media-upload-type');
            const preview = wrapper.find('.eau-media-upload-preview');
            const previewName = wrapper.find('.eau-media-upload-preview-name');
            const previewLink = wrapper.find('.eau-media-upload-preview-link');
            const thumbnail = wrapper.find('.eau-media-upload-preview-thumbnail');
            const thumbnailImage = thumbnail.find('.eau-media-upload-preview-image');

            valueInput.val(value);
            typeInput.val(type);
            previewName.text(filename);

            const fileUrl = url || value;
            previewLink.attr('href', fileUrl).attr('target', '_blank');
            thumbnail.attr('href', fileUrl).attr('target', '_blank');

            // Check if file is an image and show preview
            const isImage = this.isImageFile(filename) || this.isImageUrl(fileUrl);

            if (isImage && fileUrl) {
                thumbnailImage.attr('src', fileUrl).show();
                thumbnail.addClass('has-image');
            } else {
                thumbnailImage.attr('src', '').hide();
                thumbnail.removeClass('has-image');
            }

            preview.show();

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        },

        /**
         * Check if filename is an image
         */
        isImageFile: function(filename) {
            if (!filename) return false;
            const ext = filename.split('.').pop().toLowerCase();
            return ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'].includes(ext);
        },

        /**
         * Check if URL is an image
         */
        isImageUrl: function(url) {
            if (!url) return false;
            return /\.(jpg|jpeg|png|gif|webp|bmp|svg)(\?.*)?$/i.test(url);
        },

        /**
         * Clear media upload component
         */
        clearMediaUpload: function(wrapper) {
            wrapper.find('.eau-media-upload-value').val('');
            wrapper.find('.eau-media-upload-type').val('');
            wrapper.find('.eau-media-upload-url-input').val('');
            wrapper.find('.eau-media-upload-preview').hide();
            wrapper.find('.eau-media-upload-file-item').removeClass('selected');

            const thumbnail = wrapper.find('.eau-media-upload-preview-thumbnail');
            thumbnail.removeClass('has-image');
            thumbnail.find('.eau-media-upload-preview-image').attr('src', '').hide();
        },

        /**
         * Format file size
         */
        formatFileSize: function(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        },

        /**
         * Save event (create or update)
         */
        saveEvent: function() {
            // Sync Quill content to hidden input before saving
            if (this.quillEditor) {
                const html = this.quillEditor.root.innerHTML;
                const cleanHtml = html === '<p><br></p>' ? '' : html;
                $('#eau-edit-full_description').val(cleanHtml);
                console.log('Quill HTML being saved:', cleanHtml);
            }

            const $form = $('#eau-event-edit-form');
            const mode = $('#eau-edit-mode').val();
            const isCreate = mode === 'create';

            // Build data object from form
            const data = {
                action: isCreate ? 'eau_create_event' : 'eau_update_event',
                nonce: eauEventsManagement.nonce
            };

            // Add form fields
            $form.find('input, select, textarea').each(function() {
                const $field = $(this);
                const name = $field.attr('name');
                if (!name) return;

                if ($field.is(':checkbox')) {
                    data[name] = $field.is(':checked') ? '1' : '';
                } else if ($field.is(':radio')) {
                    if ($field.is(':checked')) {
                        data[name] = $field.val();
                    }
                } else {
                    data[name] = $field.val();
                }
            });

            console.log('Data being sent:', data);
            console.log('full_description value:', data.full_description);

            // Disable save button
            const $saveBtn = $('#eau-modal-save');
            const originalText = $saveBtn.html();
            $saveBtn.prop('disabled', true).html('<i data-lucide="loader-2" class="eau-spin"></i> Saving...');
            lucide.createIcons();

            $.ajax({
                url: eauEventsManagement.ajaxUrl,
                type: 'POST',
                data: data,
                success: (response) => {
                    if (response.success) {
                        this.showToast(isCreate ? 'Event created successfully' : 'Event updated successfully', 'success');
                        this.closeModal();
                        this.loadEvents();
                    } else {
                        this.showToast(response.data.message || 'Error saving event', 'error');
                        $saveBtn.prop('disabled', false).html(originalText);
                        lucide.createIcons();
                    }
                },
                error: () => {
                    this.showToast('Error saving event', 'error');
                    $saveBtn.prop('disabled', false).html(originalText);
                    lucide.createIcons();
                }
            });
        },

        /**
         * Toggle event status
         */
        toggleStatus: function(eventId) {
            $.ajax({
                url: eauEventsManagement.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_toggle_event_status',
                    nonce: eauEventsManagement.nonce,
                    event_id: eventId
                },
                success: (response) => {
                    if (response.success) {
                        this.showToast(response.data.message, 'success');
                        this.loadEvents();
                    } else {
                        this.showToast(response.data.message || 'Error', 'error');
                    }
                }
            });
        },

        /**
         * Duplicate event
         */
        duplicateEvent: function(eventId) {
            $.ajax({
                url: eauEventsManagement.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_duplicate_event',
                    nonce: eauEventsManagement.nonce,
                    event_id: eventId
                },
                success: (response) => {
                    if (response.success) {
                        this.showToast(response.data.message, 'success');
                        this.loadEvents();
                    } else {
                        this.showToast(response.data.message || 'Error', 'error');
                    }
                }
            });
        },

        /**
         * Delete event
         */
        deleteEvent: function(eventId) {
            const self = this;

            EauNotifications.confirm({
                title: 'Delete Event?',
                message: 'Are you sure you want to delete this event? This action cannot be undone.',
                type: 'danger',
                confirmText: 'Delete',
                cancelText: 'Cancel',
                onConfirm: function() {
                    $.ajax({
                        url: eauEventsManagement.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'eau_delete_event',
                            nonce: eauEventsManagement.nonce,
                            event_id: eventId
                        },
                        success: (response) => {
                            if (response.success) {
                                self.showToast(response.data.message, 'success');
                                self.loadEvents();
                            } else {
                                self.showToast(response.data.message || 'Error', 'error');
                            }
                        }
                    });
                }
            });
        },

        /**
         * Show toast notification
         */
        showToast: function(message, type) {
            if (typeof EauNotifications !== 'undefined') {
                switch (type) {
                    case 'success':
                        EauNotifications.success('Success', message);
                        break;
                    case 'error':
                        EauNotifications.error('Error', message);
                        break;
                    case 'warning':
                        EauNotifications.warning('Warning', message);
                        break;
                    default:
                        EauNotifications.info('Info', message);
                }
            } else {
                console.log(type + ': ' + message);
            }
        },

        /**
         * View Registrations - redirect to registrations page
         */
        viewRegistrations: function(eventId) {
            // Find event slug from table row
            const $row = $(`.eau-dropdown[data-id="${eventId}"]`).closest('tr');
            const eventSlug = $row.data('slug');

            if (eventSlug) {
                window.location.href = eauEventsManagement.registrationsUrl.replace('{slug}', eventSlug);
            } else {
                // Fallback: fetch slug via AJAX
                $.ajax({
                    url: eauEventsManagement.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'eau_get_event',
                        nonce: eauEventsManagement.nonce,
                        event_id: eventId
                    },
                    success: (response) => {
                        if (response.success && response.data.event.slug) {
                            window.location.href = eauEventsManagement.registrationsUrl.replace('{slug}', response.data.event.slug);
                        } else {
                            this.showToast('Could not load event', 'error');
                        }
                    },
                    error: () => {
                        this.showToast('Error loading event', 'error');
                    }
                });
            }
        },

        /**
         * Escape HTML
         */
        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        if ($('.eau-events-management-container').length) {
            EauEventsManagement.init();
        }
    });

})(jQuery);
