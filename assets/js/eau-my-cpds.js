/**
 * My CPDs JavaScript Controller
 *
 * @since 1.37.0
 * @since 1.38.0 Added Add Activity functionality
 */
(function($) {
    'use strict';

    const MyCpdsController = {
        // State
        currentPage: 1,
        perPage: 20,
        orderBy: 'post_date',
        order: 'DESC',
        search: '',
        year: null,
        category: '',

        // Quill editor instance
        quillEditor: null,

        // Flatpickr instance
        datepicker: null,

        /**
         * Inicializa o controller
         */
        init: function() {
            // Set initial year from data or current year
            this.year = eauMyCpdsData.currentYear || new Date().getFullYear();

            // Initialize Lucide icons on page load
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }

            this.bindEvents();
            this.loadActivities();
            this.initAddActivityForm();
        },

        /**
         * Bind de eventos
         */
        bindEvents: function() {
            const self = this;

            // Search
            $('#eau-my-cpds-search').on('keyup', this.debounce(function() {
                self.search = $(this).val();
                self.currentPage = 1;
                self.loadActivities();
            }, 500));

            // Year filter
            $('#eau-year-select').on('change', function() {
                self.year = $(this).val();
                self.currentPage = 1;
                self.loadActivities();
                self.loadProgress();
            });

            // Category filter
            $('#eau-category-select').on('change', function() {
                self.category = $(this).val();
                self.currentPage = 1;
                self.loadActivities();
            });

            // Sort
            $(document).on('click', '.eau-sortable', function() {
                const columnKey = $(this).data('key');
                self.handleSort(columnKey);
            });

            // Pagination
            $(document).on('click', '.eau-pagination-btn', function(e) {
                e.preventDefault();
                if ($(this).is(':disabled') || $(this).hasClass('eau-pagination-active')) {
                    return;
                }

                const page = parseInt($(this).data('page'));
                self.currentPage = page;
                self.loadActivities();

                // Scroll to table
                $('html, body').animate({
                    scrollTop: $('.eau-page-header').offset().top - 50
                }, 300);
            });

            // Add Activity button
            $('#eau-add-activity-btn').on('click', function() {
                self.openAddActivityModal();
            });

            // Modal close buttons
            $(document).on('click', '#add-activity-modal-overlay [data-modal-action="close"]', function() {
                self.closeAddActivityModal();
            });

            // Close modal on overlay click
            $('#add-activity-modal-overlay').on('click', function(e) {
                if (e.target === this) {
                    self.closeAddActivityModal();
                }
            });

            // Save activity button
            $('#eau-save-activity-btn').on('click', function() {
                self.saveActivity();
            });

            // Points preview calculation
            $('#eau-activity-category, #eau-activity-hours').on('change input', function() {
                self.updatePointsPreview();
            });

            // Edit Activity button click
            $(document).on('click', '.eau-action-edit', function() {
                const activityId = $(this).data('id');
                self.openEditActivityModal(activityId);
            });

            // Edit modal close buttons
            $(document).on('click', '#edit-activity-modal-overlay [data-modal-action="close"]', function() {
                self.closeEditActivityModal();
            });

            // Close edit modal on overlay click
            $('#edit-activity-modal-overlay').on('click', function(e) {
                if (e.target === this) {
                    self.closeEditActivityModal();
                }
            });

            // Update activity button
            $('#eau-update-activity-btn').on('click', function() {
                self.updateActivity();
            });

            // Delete Activity button click
            $(document).on('click', '.eau-action-delete', function() {
                const activityId = $(this).data('id');
                self.confirmDeleteActivity(activityId);
            });

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

                // Re-initialize Lucide icons for the panel
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
                    fileInput.click(); // Use native click to avoid jQuery event loop
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
                    fileInput.click(); // Use native click to avoid jQuery event loop
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

                // Update selection
                wrapper.find('.eau-media-upload-file-item').removeClass('selected');
                $(this).addClass('selected');

                // Set value
                self.setMediaValue(wrapper, fileId, 'media', fileName, fileUrl);
            });
        },

        /**
         * Upload file to server
         */
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
            formData.append('action', 'eau_upload_file');
            formData.append('nonce', eauMyCpdsData.nonce);
            formData.append('file', file);
            formData.append('max_size', maxSize);
            formData.append('allowed_extensions', allowedExtensions);

            // Upload via AJAX
            $.ajax({
                url: eauMyCpdsData.ajaxUrl,
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

        /**
         * Load user's files for My Files panel
         */
        loadUserFiles: function(wrapper) {
            const self = this;
            const listContainer = wrapper.find('.eau-media-upload-myfiles-list');
            const searchInput = wrapper.find('.eau-media-upload-myfiles-search-input');
            const search = searchInput.val().trim();

            // Show loading
            listContainer.html(`
                <div class="eau-media-upload-myfiles-loading">
                    <i data-lucide="loader-2" class="eau-spin"></i>
                    <span>Loading files...</span>
                </div>
            `);

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }

            $.ajax({
                url: eauMyCpdsData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_get_user_files',
                    nonce: eauMyCpdsData.nonce,
                    search: search,
                    per_page: 50
                },
                success: function(response) {
                    if (response.success) {
                        self.renderUserFiles(wrapper, response.data.files);
                    } else {
                        listContainer.html(`
                            <div class="eau-media-upload-myfiles-empty">
                                <i data-lucide="alert-circle"></i>
                                <span>Failed to load files</span>
                            </div>
                        `);
                    }

                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                },
                error: function() {
                    listContainer.html(`
                        <div class="eau-media-upload-myfiles-empty">
                            <i data-lucide="alert-circle"></i>
                            <span>Failed to load files</span>
                        </div>
                    `);

                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                }
            });
        },

        /**
         * Render user files list
         */
        renderUserFiles: function(wrapper, files) {
            const listContainer = wrapper.find('.eau-media-upload-myfiles-list');
            const currentValue = wrapper.find('.eau-media-upload-value').val();

            if (files.length === 0) {
                listContainer.html(`
                    <div class="eau-media-upload-myfiles-empty">
                        <i data-lucide="folder-open"></i>
                        <span>No files found</span>
                    </div>
                `);
                return;
            }

            let html = '';
            files.forEach(function(file) {
                const isSelected = currentValue == file.id;
                const iconHtml = file.is_image && file.thumbnail
                    ? `<img src="${file.thumbnail}" alt="">`
                    : `<i data-lucide="file"></i>`;
                const iconClass = file.is_image && file.thumbnail ? 'is-image' : '';

                html += `
                    <div class="eau-media-upload-file-item ${isSelected ? 'selected' : ''}"
                         data-id="${file.id}"
                         data-url="${file.url}"
                         data-filename="${file.filename}">
                        <div class="eau-media-upload-file-item-icon ${iconClass}">
                            ${iconHtml}
                        </div>
                        <div class="eau-media-upload-file-item-info">
                            <span class="eau-media-upload-file-item-name">${file.filename}</span>
                            <span class="eau-media-upload-file-item-meta">${file.size_formatted} • ${file.date}</span>
                        </div>
                        <div class="eau-media-upload-file-item-select"></div>
                    </div>
                `;
            });

            listContainer.html(html);

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        },

        /**
         * Set media value and update preview
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
            previewLink.attr('href', url || value);

            // Check if file is an image and show preview
            const fileUrl = url || value;
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
         * Check if URL points to an image
         */
        isImageUrl: function(url) {
            if (!url) return false;
            // Check common image extensions in URL
            return /\.(jpg|jpeg|png|gif|webp|bmp|svg)(\?.*)?$/i.test(url);
        },

        /**
         * Clear media upload values
         */
        clearMediaUpload: function(wrapper) {
            wrapper.find('.eau-media-upload-value').val('');
            wrapper.find('.eau-media-upload-type').val('');
            wrapper.find('.eau-media-upload-url-input').val('');
            wrapper.find('.eau-media-upload-preview').hide();
            wrapper.find('.eau-media-upload-file-item').removeClass('selected');

            // Clear image preview
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
         * Initialize Add Activity form components
         */
        initAddActivityForm: function() {
            const self = this;

            // Initialize Quill editor
            if (typeof Quill !== 'undefined' && $('#eau-activity-description-editor').length) {
                this.quillEditor = new Quill('#eau-activity-description-editor', {
                    theme: 'snow',
                    placeholder: 'Describe your CPD activity...',
                    modules: {
                        toolbar: [
                            ['bold', 'italic', 'underline'],
                            [{ 'list': 'ordered' }, { 'list': 'bullet' }],
                            ['clean']
                        ]
                    }
                });

                // Sync with hidden input
                this.quillEditor.on('text-change', function() {
                    $('#eau-activity-description').val(self.quillEditor.root.innerHTML);
                });
            }

            // Initialize Flatpickr datepicker
            if (typeof flatpickr !== 'undefined' && $('#eau-activity-date').length) {
                this.datepicker = flatpickr('#eau-activity-date', {
                    dateFormat: 'Y-m-d',
                    altInput: true,
                    altFormat: 'F j, Y',
                    maxDate: 'today',
                    disableMobile: true
                });
            }
        },

        /**
         * Load activities
         */
        loadActivities: function() {
            const self = this;

            // Show loading
            this.showLoading();

            $.ajax({
                url: eauMyCpdsData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_get_my_cpds',
                    nonce: eauMyCpdsData.nonce,
                    page: this.currentPage,
                    per_page: this.perPage,
                    search: this.search,
                    year: this.year,
                    category: this.category,
                    orderby: this.orderBy,
                    order: this.order
                },
                success: function(response) {
                    if (response.success) {
                        self.renderActivities(response.data);
                        self.renderPagination(response.data);
                    } else {
                        EauNotifications.error('Error', response.data.message || 'Failed to load activities');
                    }
                },
                error: function() {
                    EauNotifications.error('Network Error', 'Please try again.');
                },
                complete: function() {
                    self.hideLoading();
                }
            });
        },

        /**
         * Load CPD progress
         */
        loadProgress: function() {
            const self = this;

            $.ajax({
                url: eauMyCpdsData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_get_my_cpd_progress',
                    nonce: eauMyCpdsData.nonce,
                    year: this.year
                },
                success: function(response) {
                    if (response.success) {
                        self.updateProgressUI(response.data);
                    }
                },
                error: function() {
                    console.error('Failed to load progress');
                }
            });
        },

        /**
         * Update progress UI
         */
        updateProgressUI: function(data) {
            // Update year display
            $('#eau-progress-year-display span').text(this.year);

            // Update progress bar
            const $progressBar = $('#eau-progress-bar');
            const percentage = parseFloat(data.percentage);

            $progressBar
                .css('width', percentage + '%')
                .removeClass('eau-progress-complete eau-progress-halfway eau-progress-starting');

            if (percentage >= 100) {
                $progressBar.addClass('eau-progress-complete');
            } else if (percentage >= 50) {
                $progressBar.addClass('eau-progress-halfway');
            } else {
                $progressBar.addClass('eau-progress-starting');
            }

            $('#eau-progress-bar-text').text(percentage + '%');

            // Update stats
            $('#eau-points-earned').text(data.total_points);
            $('#eau-points-remaining').text(data.points_remaining);
            $('#eau-activities-count').text(data.activities_count);

            // Show/hide goal reached message
            if (data.goal_reached) {
                if ($('.eau-cpd-goal-reached').length === 0) {
                    const goalHtml = `
                        <div class="eau-cpd-goal-reached">
                            <i data-lucide="award"></i>
                            <span>Congratulations! You've reached your annual CPD goal!</span>
                        </div>
                    `;
                    $('.eau-cpd-progress-card').after(goalHtml);
                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                }
            } else {
                $('.eau-cpd-goal-reached').remove();
            }
        },

        /**
         * Render activities na tabela
         */
        renderActivities: function(data) {
            const tbody = $('#my-cpds-table-tbody');

            if (data.rows.length === 0) {
                tbody.html(this.getEmptyState());
                return;
            }

            let html = '';
            data.rows.forEach(function(row) {
                html += `
                    <tr class="eau-table-tr">
                        <td class="eau-table-td">${row.activity}</td>
                        <td class="eau-table-td">${row.category}</td>
                        <td class="eau-table-td">${row.hours}</td>
                        <td class="eau-table-td">${row.points}</td>
                        <td class="eau-table-td">${row.status}</td>
                        <td class="eau-table-td">${row.date}</td>
                        <td class="eau-table-td">${row.action || ''}</td>
                    </tr>
                `;
            });

            tbody.html(html);

            // Re-initialize Lucide icons after DOM update
            // Using setTimeout to ensure DOM is fully updated
            setTimeout(function() {
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
            }, 10);
        },

        /**
         * Get empty state HTML
         */
        getEmptyState: function() {
            return `
                <tr class="eau-table-empty">
                    <td colspan="7" style="text-align: center; padding: 3rem;">
                        <i data-lucide="inbox" style="width: 3rem; height: 3rem; color: #d1d5db; margin-bottom: 1rem;"></i>
                        <p style="color: #6b7280; margin: 0;">No CPD activities found for ${this.year}</p>
                    </td>
                </tr>
            `;
        },

        /**
         * Render pagination
         */
        renderPagination: function(data) {
            const self = this;
            const container = $('#eau-my-cpds-pagination');
            const totalPages = data.total_pages || 1;

            if (totalPages <= 1) {
                container.html('');
                return;
            }

            const startItem = ((data.page - 1) * data.per_page) + 1;
            const endItem = Math.min(data.page * data.per_page, data.total);

            const html = this.buildPaginationHTML(data.page, totalPages, startItem, endItem, data.total);
            container.html(html);

            // Re-initialize Lucide icons
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        },

        /**
         * Build pagination HTML
         */
        buildPaginationHTML: function(currentPage, totalPages, startItem, endItem, total) {
            const pagesToShow = this.getPagesToShow(currentPage, totalPages);

            let html = '<div class="eau-pagination-wrapper" id="my-cpds-pagination">';

            // Info
            html += '<div class="eau-pagination-info">';
            html += `Showing ${startItem.toLocaleString()} to ${endItem.toLocaleString()} of ${total.toLocaleString()} activities`;
            html += '</div>';

            // Navigation
            html += '<div class="eau-pagination-nav">';

            // Previous button
            const prevDisabled = currentPage <= 1 ? 'disabled' : '';
            html += `<button class="eau-pagination-btn eau-pagination-prev ${prevDisabled}" data-page="${Math.max(1, currentPage - 1)}" ${prevDisabled ? 'disabled' : ''}>`;
            html += '<i data-lucide="chevron-left"></i>';
            html += '</button>';

            // Page numbers
            pagesToShow.forEach(function(page) {
                if (page === '...') {
                    html += '<span class="eau-pagination-ellipsis">...</span>';
                } else {
                    const activeClass = page === currentPage ? 'eau-pagination-active' : '';
                    html += `<button class="eau-pagination-btn eau-pagination-number ${activeClass}" data-page="${page}">${page}</button>`;
                }
            });

            // Next button
            const nextDisabled = currentPage >= totalPages ? 'disabled' : '';
            html += `<button class="eau-pagination-btn eau-pagination-next ${nextDisabled}" data-page="${Math.min(totalPages, currentPage + 1)}" ${nextDisabled ? 'disabled' : ''}>`;
            html += '<i data-lucide="chevron-right"></i>';
            html += '</button>';

            html += '</div></div>';

            return html;
        },

        /**
         * Get pages to show in pagination
         */
        getPagesToShow: function(currentPage, totalPages) {
            const delta = 2;
            const range = [];
            const rangeWithDots = [];
            let l;

            for (let i = 1; i <= totalPages; i++) {
                if (i === 1 || i === totalPages || (i >= currentPage - delta && i <= currentPage + delta)) {
                    range.push(i);
                }
            }

            range.forEach(function(i) {
                if (l) {
                    if (i - l === 2) {
                        rangeWithDots.push(l + 1);
                    } else if (i - l !== 1) {
                        rangeWithDots.push('...');
                    }
                }
                rangeWithDots.push(i);
                l = i;
            });

            return rangeWithDots;
        },

        /**
         * Handle sort
         */
        handleSort: function(columnKey) {
            // Mapeamento de colunas para campos de ordenação
            const columnMap = {
                'activity': 'post_title',
                'category': 'category_name',
                'hours': 'hours',
                'points': 'points',
                'date': 'post_date'
            };

            const sortField = columnMap[columnKey] || columnKey;

            // Se já está ordenando por este campo, inverte a direção
            if (this.orderBy === sortField) {
                this.order = this.order === 'ASC' ? 'DESC' : 'ASC';
            } else {
                // Novo campo, começa com ASC (exceto data que começa DESC)
                this.orderBy = sortField;
                this.order = sortField === 'post_date' ? 'DESC' : 'ASC';
            }

            // Atualiza ícones
            this.updateSortIcons(columnKey);

            // Volta para primeira página
            this.currentPage = 1;

            // Recarrega
            this.loadActivities();
        },

        /**
         * Update sort icons
         */
        updateSortIcons: function(activeColumn) {
            // Remove todas as classes de ordenação
            $('.eau-sortable').removeClass('eau-sorted-asc eau-sorted-desc');

            // Adiciona classe no cabeçalho ativo
            const $activeHeader = $(`.eau-sortable[data-key="${activeColumn}"]`);
            if (this.order === 'ASC') {
                $activeHeader.addClass('eau-sorted-asc');
            } else {
                $activeHeader.addClass('eau-sorted-desc');
            }
        },

        /**
         * Show loading
         */
        showLoading: function() {
            $('#my-cpds-table-loading').show();
        },

        /**
         * Hide loading
         */
        hideLoading: function() {
            $('#my-cpds-table-loading').hide();
        },

        /**
         * Open Add Activity modal
         */
        openAddActivityModal: function() {
            const $modal = $('#add-activity-modal-overlay');
            $modal.css('display', 'flex').hide().fadeIn(200);
            $('body').addClass('eau-modal-open');

            // Re-initialize Lucide icons
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        },

        /**
         * Close Add Activity modal
         */
        closeAddActivityModal: function() {
            $('#add-activity-modal-overlay').fadeOut(200, function() {
                $(this).css('display', 'none');
            });
            $('body').removeClass('eau-modal-open');
        },

        /**
         * Reset Add Activity form
         */
        resetAddActivityForm: function() {
            $('#eau-add-activity-form')[0].reset();

            // Reset Quill editor
            if (this.quillEditor) {
                this.quillEditor.setText('');
            }

            // Reset datepicker
            if (this.datepicker) {
                this.datepicker.clear();
            }

            // Reset media upload
            const wrapper = $('#eau-activity-proof-wrapper');
            this.clearMediaUpload(wrapper);

            // Reset points preview
            $('#eau-points-preview').text('0.00');
        },

        /**
         * Update points preview based on category and hours
         */
        updatePointsPreview: function() {
            const $category = $('#eau-activity-category');
            const hours = parseFloat($('#eau-activity-hours').val()) || 0;
            const selectedOption = $category.find('option:selected');
            const pointsPerHour = parseFloat(selectedOption.data('points')) || 0;

            const estimatedPoints = hours * pointsPerHour;
            $('#eau-points-preview').text(estimatedPoints.toFixed(2));
        },

        /**
         * Save new activity
         */
        saveActivity: function() {
            const self = this;

            // Get form values
            const activityName = $('#eau-activity-name').val().trim();
            const categorySerial = $('#eau-activity-category').val();
            const hours = $('#eau-activity-hours').val();
            const completedDate = $('#eau-activity-date').val();
            const description = $('#eau-activity-description').val() || '';
            const proof = $('#eau-activity-proof').val() || '';
            const proofType = $('#eau-activity-proof-type').val() || '';

            // Validation
            if (!activityName) {
                EauNotifications.error('Validation Error', 'Activity name is required.');
                $('#eau-activity-name').focus();
                return;
            }

            if (!categorySerial) {
                EauNotifications.error('Validation Error', 'Please select a category.');
                $('#eau-activity-category').focus();
                return;
            }

            if (!hours || parseFloat(hours) <= 0) {
                EauNotifications.error('Validation Error', 'Hours must be greater than 0.');
                $('#eau-activity-hours').focus();
                return;
            }

            if (!completedDate) {
                EauNotifications.error('Validation Error', 'Please select a completion date.');
                $('#eau-activity-date').focus();
                return;
            }

            // Disable save button
            const $saveBtn = $('#eau-save-activity-btn');
            $saveBtn.prop('disabled', true).html('<i data-lucide="loader-2" class="eau-spin"></i> Saving...');

            $.ajax({
                url: eauMyCpdsData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_create_my_activity',
                    nonce: eauMyCpdsData.nonce,
                    activity_name: activityName,
                    category_serial: categorySerial,
                    hours: hours,
                    completed_date: completedDate,
                    description: description,
                    proof: proof,
                    proof_type: proofType
                },
                success: function(response) {
                    if (response.success) {
                        EauNotifications.success('Success', response.data.message);

                        // Close modal
                        self.closeAddActivityModal();

                        // Reset form
                        self.resetAddActivityForm();

                        // Reload activities and progress
                        self.loadActivities();
                        self.loadProgress();
                    } else {
                        EauNotifications.error('Error', response.data.message || 'Failed to create activity.');
                    }
                },
                error: function() {
                    EauNotifications.error('Network Error', 'Please try again.');
                },
                complete: function() {
                    // Re-enable save button
                    $saveBtn.prop('disabled', false).html('<i data-lucide="save"></i> Save Activity');

                    // Re-initialize Lucide icons
                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                }
            });
        },

        /**
         * Open Edit Activity modal
         */
        openEditActivityModal: function(activityId) {
            const self = this;
            const $modal = $('#edit-activity-modal-overlay');

            // Show modal with loading state
            $modal.css('display', 'flex').hide().fadeIn(200);
            $('body').addClass('eau-modal-open');

            // Load activity data
            $.ajax({
                url: eauMyCpdsData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_get_my_activity',
                    nonce: eauMyCpdsData.nonce,
                    activity_id: activityId
                },
                success: function(response) {
                    if (response.success) {
                        const data = response.data;

                        // Set form values
                        $('#eau-edit-activity-id').val(data.id);
                        $('#eau-edit-activity-title').text(data.title);
                        $('#eau-edit-category').val(data.category_serial);

                        // Set proof value in media upload component
                        const wrapper = $('#eau-edit-proof-wrapper');
                        if (data.proof_value) {
                            self.setMediaValue(wrapper, data.proof_value, data.proof_type, data.proof_filename, data.proof_url);

                            // Also set URL input if it's a URL type
                            if (data.proof_type === 'url') {
                                wrapper.find('.eau-media-upload-url-input').val(data.proof_value);
                            }
                        } else {
                            self.clearMediaUpload(wrapper);
                        }
                    } else {
                        EauNotifications.error('Error', response.data.message || 'Failed to load activity.');
                        self.closeEditActivityModal();
                    }
                },
                error: function() {
                    EauNotifications.error('Network Error', 'Please try again.');
                    self.closeEditActivityModal();
                },
                complete: function() {
                    // Re-initialize Lucide icons
                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                }
            });
        },

        /**
         * Close Edit Activity modal
         */
        closeEditActivityModal: function() {
            $('#edit-activity-modal-overlay').fadeOut(200, function() {
                $(this).css('display', 'none');
            });
            $('body').removeClass('eau-modal-open');

            // Reset form
            this.resetEditActivityForm();
        },

        /**
         * Reset Edit Activity form
         */
        resetEditActivityForm: function() {
            $('#eau-edit-activity-form')[0].reset();
            $('#eau-edit-activity-id').val('');
            $('#eau-edit-activity-title').text('');

            // Reset media upload
            const wrapper = $('#eau-edit-proof-wrapper');
            this.clearMediaUpload(wrapper);
        },

        /**
         * Update activity
         */
        updateActivity: function() {
            const self = this;
            const activityId = $('#eau-edit-activity-id').val();
            const categorySerial = $('#eau-edit-category').val();
            const proof = $('#eau-edit-proof').val() || '';

            // Validation
            if (!categorySerial) {
                EauNotifications.error('Validation Error', 'Please select a category.');
                $('#eau-edit-category').focus();
                return;
            }

            // Disable save button
            const $saveBtn = $('#eau-update-activity-btn');
            $saveBtn.prop('disabled', true).html('<i data-lucide="loader-2" class="eau-spin"></i> Saving...');

            $.ajax({
                url: eauMyCpdsData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_update_my_activity',
                    nonce: eauMyCpdsData.nonce,
                    activity_id: activityId,
                    category_serial: categorySerial,
                    proof: proof
                },
                success: function(response) {
                    if (response.success) {
                        EauNotifications.success('Success', response.data.message);

                        // Close modal
                        self.closeEditActivityModal();

                        // Reload activities and progress
                        self.loadActivities();
                        self.loadProgress();
                    } else {
                        EauNotifications.error('Error', response.data.message || 'Failed to update activity.');
                    }
                },
                error: function() {
                    EauNotifications.error('Network Error', 'Please try again.');
                },
                complete: function() {
                    // Re-enable save button
                    $saveBtn.prop('disabled', false).html('<i data-lucide="save"></i> Save Changes');

                    // Re-initialize Lucide icons
                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                }
            });
        },

        /**
         * Confirm and delete activity
         */
        confirmDeleteActivity: function(activityId) {
            const self = this;

            EauNotifications.confirm({
                title: 'Delete Activity',
                message: 'Are you sure you want to delete this activity? This action cannot be undone.',
                type: 'danger',
                confirmText: 'Delete',
                cancelText: 'Cancel',
                onConfirm: function() {
                    self.deleteActivity(activityId);
                }
            });
        },

        /**
         * Delete activity
         */
        deleteActivity: function(activityId) {
            const self = this;

            $.ajax({
                url: eauMyCpdsData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_delete_my_activity',
                    nonce: eauMyCpdsData.nonce,
                    activity_id: activityId
                },
                success: function(response) {
                    if (response.success) {
                        EauNotifications.success('Success', response.data.message);

                        // Reload activities and progress
                        self.loadActivities();
                        self.loadProgress();
                    } else {
                        EauNotifications.error('Error', response.data.message || 'Failed to delete activity.');
                    }
                },
                error: function() {
                    EauNotifications.error('Network Error', 'Please try again.');
                }
            });
        },

        /**
         * Debounce helper
         */
        debounce: function(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func.apply(this, args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        if ($('.eau-my-cpds-container').length) {
            MyCpdsController.init();
        }
    });

})(jQuery);
