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

        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.loadEvents();
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
                        // TODO: Implement registrations view
                        this.showToast('Registrations feature coming soon', 'info');
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

            // Modal
            $('#eau-modal-close, #eau-modal-cancel, .eau-modal-overlay').on('click', () => {
                this.closeModal();
            });

            $('#eau-modal-save').on('click', () => {
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

            // Image upload
            $('#eau-select-image').on('click', () => {
                this.openMediaLibrary();
            });

            $('#eau-remove-image').on('click', () => {
                this.removeImage();
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
                    <tr>
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

            // Clear image
            this.removeImage();

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
            $('#eau-edit-non_member_price').val(event.non_member_price || '');
            $('#eau-edit-early_bird_price').val(event.early_bird_price || '');
            $('#eau-edit-early_bird_end_date').val(event.early_bird_end_date || '');
            $('#eau-edit-max_guests').val(event.max_guests || '');
            $('#eau-edit-cpd_points').val(event.cpd_points || '');
            $('#eau-edit-cpd_category').val(event.cpd_category || '');
            $('#eau-edit-visibility').val(event.visibility || 'public');

            // Radio buttons
            const eventType = event.event_type || 'in-person';
            $(`input[name="event_type"][value="${eventType}"]`).prop('checked', true);

            // Toggle location fields based on event type
            this.toggleLocationFields(eventType);

            // Checkboxes
            $('#eau-edit-allow_guests').prop('checked', event.allow_guests === '1');
            $('#eau-edit-require_approval').prop('checked', event.require_approval === '1');
            $('#eau-edit-members_only').prop('checked', event.members_only === '1');

            // Image
            if (event.image_id && event.image_url) {
                this.setImage(event.image_id, event.image_url);
            } else {
                this.removeImage();
            }
        },

        /**
         * Close modal
         */
        closeModal: function() {
            $('#eau-event-edit-modal').removeClass('active');
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
         * Open WordPress Media Library
         */
        openMediaLibrary: function() {
            // Create media frame if not exists
            if (!this.mediaFrame) {
                this.mediaFrame = wp.media({
                    title: 'Select Event Image',
                    button: {
                        text: 'Use this image'
                    },
                    multiple: false
                });

                // When image is selected
                this.mediaFrame.on('select', () => {
                    const attachment = this.mediaFrame.state().get('selection').first().toJSON();
                    this.setImage(attachment.id, attachment.url);
                });
            }

            this.mediaFrame.open();
        },

        /**
         * Set image preview
         */
        setImage: function(imageId, imageUrl) {
            $('#eau-edit-image_id').val(imageId);
            $('#eau-image-preview-img').attr('src', imageUrl).show();
            $('#eau-image-placeholder').hide();
            $('#eau-remove-image').show();
            $('#eau-image-preview').css('border-style', 'solid');
        },

        /**
         * Remove image
         */
        removeImage: function() {
            $('#eau-edit-image_id').val('');
            $('#eau-image-preview-img').attr('src', '').hide();
            $('#eau-image-placeholder').show();
            $('#eau-remove-image').hide();
            $('#eau-image-preview').css('border-style', 'dashed');
            lucide.createIcons();
        },

        /**
         * Save event (create or update)
         */
        saveEvent: function() {
            const $form = $('#eau-event-edit-form');
            const formData = new FormData($form[0]);
            const mode = $('#eau-edit-mode').val();
            const isCreate = mode === 'create';

            formData.append('action', isCreate ? 'eau_create_event' : 'eau_update_event');
            formData.append('nonce', eauEventsManagement.nonce);

            // Disable save button
            const $saveBtn = $('#eau-modal-save');
            const originalText = $saveBtn.html();
            $saveBtn.prop('disabled', true).html('<i data-lucide="loader-2" class="eau-spin"></i> Saving...');
            lucide.createIcons();

            $.ajax({
                url: eauEventsManagement.ajaxUrl,
                type: 'POST',
                data: Object.fromEntries(formData),
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
            Swal.fire({
                title: 'Delete Event?',
                text: 'Are you sure you want to delete this event? This action cannot be undone.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, delete it',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
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
                                this.showToast(response.data.message, 'success');
                                this.loadEvents();
                            } else {
                                this.showToast(response.data.message || 'Error', 'error');
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
            const iconMap = {
                success: 'success',
                error: 'error',
                warning: 'warning',
                info: 'info'
            };

            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: iconMap[type] || 'info',
                title: message,
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true
            });
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
