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
         * Open edit modal
         */
        openEditModal: function(eventId) {
            const $modal = $('#eau-event-edit-modal');

            // Reset form and show first tab
            $('#eau-event-edit-form')[0].reset();
            $('.eau-modal-tab-btn').removeClass('active').first().addClass('active');
            $('.eau-modal-tab-content').removeClass('active').first().addClass('active');

            $modal.addClass('active');

            // Load event data
            $.ajax({
                url: eauEventsManagement.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_get_event',
                    nonce: eauEventsManagement.nonce,
                    event_id: eventId
                },
                success: (response) => {
                    if (response.success) {
                        this.populateModal(response.data.event, eventId);
                    } else {
                        this.showToast('Error loading event', 'error');
                        this.closeModal();
                    }
                    lucide.createIcons();
                }
            });
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
            $(`input[name="event_type"][value="${event.event_type || 'in-person'}"]`).prop('checked', true);

            // Checkboxes
            $('#eau-edit-allow_guests').prop('checked', event.allow_guests === '1');
            $('#eau-edit-require_approval').prop('checked', event.require_approval === '1');
            $('#eau-edit-members_only').prop('checked', event.members_only === '1');
        },

        /**
         * Close modal
         */
        closeModal: function() {
            $('#eau-event-edit-modal').removeClass('active');
        },

        /**
         * Save event
         */
        saveEvent: function() {
            const $form = $('#eau-event-edit-form');
            const formData = new FormData($form[0]);

            formData.append('action', 'eau_update_event');
            formData.append('nonce', eauEventsManagement.nonce);

            $.ajax({
                url: eauEventsManagement.ajaxUrl,
                type: 'POST',
                data: Object.fromEntries(formData),
                success: (response) => {
                    if (response.success) {
                        this.showToast('Event updated successfully', 'success');
                        this.closeModal();
                        this.loadEvents();
                    } else {
                        this.showToast(response.data.message || 'Error updating event', 'error');
                    }
                },
                error: () => {
                    this.showToast('Error updating event', 'error');
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
            if (!confirm('Are you sure you want to delete this event?')) {
                return;
            }

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
        },

        /**
         * Show toast notification
         */
        showToast: function(message, type) {
            if (typeof EauNotifications !== 'undefined') {
                EauNotifications.show(message, type);
            } else {
                alert(message);
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
