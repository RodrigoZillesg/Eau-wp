/**
 * Event Registrations Admin JavaScript
 *
 * @package EauSystem
 * @since 1.29.0
 */

(function($) {
    'use strict';

    const EauEventRegAdmin = {

        // State
        currentPage: 1,
        perPage: 20,
        searchTerm: '',
        statusFilter: '',
        eventFilter: 0,
        orderBy: 'date',
        order: 'DESC',
        searchTimeout: null,

        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.loadRegistrations();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            const self = this;

            // Stats card filter click
            $(document).on('click', '.eau-admin-stat-card', function() {
                const filter = $(this).data('filter');
                self.statusFilter = filter;
                self.currentPage = 1;

                $('.eau-admin-stat-card').removeClass('active');
                $(this).addClass('active');
                $('#eau-status-filter').val(filter);

                self.loadRegistrations();
            });

            // Search input
            $('#eau-registration-search').on('input', function() {
                clearTimeout(self.searchTimeout);
                self.searchTimeout = setTimeout(function() {
                    self.searchTerm = $('#eau-registration-search').val();
                    self.currentPage = 1;
                    self.loadRegistrations();
                }, 300);
            });

            // Status filter change
            $('#eau-status-filter').on('change', function() {
                self.statusFilter = $(this).val();
                self.currentPage = 1;

                // Update active stat card
                $('.eau-admin-stat-card').removeClass('active');
                $('.eau-admin-stat-card[data-filter="' + self.statusFilter + '"]').addClass('active');

                self.loadRegistrations();
            });

            // Event filter change
            $('#eau-event-filter').on('change', function() {
                self.eventFilter = $(this).val();
                self.currentPage = 1;
                self.loadRegistrations();
            });

            // Table sorting
            $(document).on('click', '.eau-admin-table th.sortable', function() {
                const column = $(this).data('column');
                if (self.orderBy === column) {
                    self.order = self.order === 'ASC' ? 'DESC' : 'ASC';
                } else {
                    self.orderBy = column;
                    self.order = 'ASC';
                }
                self.loadRegistrations();
            });

            // Pagination
            $(document).on('click', '.eau-pagination-btn', function() {
                if ($(this).is(':disabled')) return;
                self.currentPage = $(this).data('page');
                self.loadRegistrations();
            });

            // Status dropdown change
            $(document).on('change', '.eau-status-select', function() {
                const $select = $(this);
                const postId = $select.data('id');
                const status = $select.val();
                self.updateStatus(postId, status);
            });

            // Delete button
            $(document).on('click', '.eau-action-delete', function() {
                const postId = $(this).data('id');
                if (confirm(eauEventRegAdmin.i18n.confirmDelete)) {
                    self.deleteRegistration(postId);
                }
            });
        },

        /**
         * Load registrations via AJAX
         */
        loadRegistrations: function() {
            const self = this;

            // Show skeleton loading
            $('#eau-registrations-table').html(
                '<div class="eau-loading-skeleton">' +
                '<div class="eau-skeleton-row"></div>'.repeat(5) +
                '</div>'
            );

            $.ajax({
                url: eauEventRegAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_get_registrations',
                    nonce: eauEventRegAdmin.nonce,
                    page: this.currentPage,
                    per_page: this.perPage,
                    search: this.searchTerm,
                    status: this.statusFilter,
                    event_id: this.eventFilter,
                    orderby: this.orderBy,
                    order: this.order
                },
                success: function(response) {
                    if (response.success) {
                        self.renderTable(response.data.registrations);
                        self.renderPagination(response.data);
                    } else {
                        self.showError('Error loading registrations');
                    }
                },
                error: function() {
                    self.showError('Connection error');
                }
            });
        },

        /**
         * Render table
         */
        renderTable: function(registrations) {
            if (!registrations || registrations.length === 0) {
                $('#eau-registrations-table').html(
                    '<div class="eau-empty-state">' +
                    '<span class="dashicons dashicons-tickets"></span>' +
                    '<h3>No registrations found</h3>' +
                    '<p>Try adjusting your filters or add a new registration.</p>' +
                    '</div>'
                );
                return;
            }

            const statusOptions = {
                'confirmed': 'Confirmed',
                'pending': 'Pending',
                'cancelled': 'Cancelled'
            };

            let html = '<table class="eau-admin-table">';
            html += '<thead><tr>';
            html += '<th class="column-attendee">Attendee</th>';
            html += '<th class="column-event">Event</th>';
            html += '<th class="column-date sortable" data-column="date">Date <span class="sort-icon dashicons dashicons-arrow-down"></span></th>';
            html += '<th class="column-type">Type</th>';
            html += '<th class="column-status">Status</th>';
            html += '<th class="column-actions">Actions</th>';
            html += '</tr></thead><tbody>';

            registrations.forEach(function(reg) {
                const initials = reg.attendee_name ? reg.attendee_name.substring(0, 2).toUpperCase() : '??';
                const memberTypeClass = reg.member_type === 'member' ? 'primary' : 'secondary';
                const memberTypeLabel = reg.member_type === 'member' ? 'Member' : 'Non-Member';
                const formattedDate = reg.registration_date ? new Date(reg.registration_date).toLocaleDateString('en-AU', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric'
                }) : '-';

                html += '<tr data-id="' + reg.id + '">';

                // Attendee
                html += '<td class="column-attendee">';
                html += '<div class="eau-attendee-info">';
                html += '<div class="eau-attendee-avatar">' + initials + '</div>';
                html += '<div class="eau-attendee-details">';
                html += '<strong>' + (reg.attendee_name || '-') + '</strong>';
                html += '<span class="eau-attendee-email">' + (reg.attendee_email || '-') + '</span>';
                html += '</div></div></td>';

                // Event
                html += '<td class="column-event">';
                html += '<span class="eau-event-name">' + reg.event_name + '</span>';
                html += '</td>';

                // Date
                html += '<td class="column-date">' + formattedDate + '</td>';

                // Type
                html += '<td class="column-type">';
                html += '<span class="eau-badge eau-badge-' + memberTypeClass + '">' + memberTypeLabel + '</span>';
                html += '</td>';

                // Status
                html += '<td class="column-status">';
                html += '<select class="eau-status-select" data-id="' + reg.id + '">';
                Object.keys(statusOptions).forEach(function(key) {
                    const selected = reg.status === key ? ' selected' : '';
                    html += '<option value="' + key + '"' + selected + '>' + statusOptions[key] + '</option>';
                });
                html += '</select>';
                html += '</td>';

                // Actions
                html += '<td class="column-actions">';
                html += '<div class="eau-admin-actions">';
                html += '<a href="' + reg.edit_url + '" class="eau-action-btn eau-action-edit" title="Edit">';
                html += '<span class="dashicons dashicons-edit"></span>';
                html += '</a>';
                html += '<button type="button" class="eau-action-btn eau-action-delete" data-id="' + reg.id + '" title="Delete">';
                html += '<span class="dashicons dashicons-trash"></span>';
                html += '</button>';
                html += '</div></td>';

                html += '</tr>';
            });

            html += '</tbody></table>';
            $('#eau-registrations-table').html(html);
        },

        /**
         * Render pagination
         */
        renderPagination: function(data) {
            const start = ((data.current_page - 1) * data.per_page) + 1;
            const end = Math.min(data.current_page * data.per_page, data.total);

            let html = '<div class="eau-pagination-info">';
            html += 'Showing ' + start + '-' + end + ' of ' + data.total + ' registrations';
            html += '</div>';

            html += '<div class="eau-pagination-buttons">';
            html += '<button type="button" class="button eau-pagination-btn" data-page="' + (data.current_page - 1) + '"' + (data.current_page <= 1 ? ' disabled' : '') + '>';
            html += '&laquo; Previous</button>';
            html += '<span class="eau-pagination-current">' + data.current_page + ' / ' + data.total_pages + '</span>';
            html += '<button type="button" class="button eau-pagination-btn" data-page="' + (data.current_page + 1) + '"' + (data.current_page >= data.total_pages ? ' disabled' : '') + '>';
            html += 'Next &raquo;</button>';
            html += '</div>';

            $('#eau-pagination').html(html);
        },

        /**
         * Update registration status
         */
        updateStatus: function(postId, status) {
            const self = this;

            $.ajax({
                url: eauEventRegAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_update_registration_status',
                    nonce: eauEventRegAdmin.nonce,
                    post_id: postId,
                    status: status
                },
                success: function(response) {
                    if (response.success) {
                        if (typeof EauNotifications !== 'undefined') {
                            EauNotifications.success(eauEventRegAdmin.i18n.statusUpdated);
                        }
                        // Reload to update stats
                        self.loadRegistrations();
                        self.reloadStats();
                    } else {
                        if (typeof EauNotifications !== 'undefined') {
                            EauNotifications.error(eauEventRegAdmin.i18n.statusError);
                        }
                    }
                },
                error: function() {
                    if (typeof EauNotifications !== 'undefined') {
                        EauNotifications.error(eauEventRegAdmin.i18n.statusError);
                    }
                }
            });
        },

        /**
         * Delete registration
         */
        deleteRegistration: function(postId) {
            const self = this;

            $.ajax({
                url: eauEventRegAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_delete_registration',
                    nonce: eauEventRegAdmin.nonce,
                    post_id: postId
                },
                success: function(response) {
                    if (response.success) {
                        if (typeof EauNotifications !== 'undefined') {
                            EauNotifications.success(eauEventRegAdmin.i18n.deleteSuccess);
                        }
                        self.loadRegistrations();
                        self.reloadStats();
                    } else {
                        if (typeof EauNotifications !== 'undefined') {
                            EauNotifications.error(eauEventRegAdmin.i18n.deleteError);
                        }
                    }
                },
                error: function() {
                    if (typeof EauNotifications !== 'undefined') {
                        EauNotifications.error(eauEventRegAdmin.i18n.deleteError);
                    }
                }
            });
        },

        /**
         * Reload stats (page refresh for now)
         */
        reloadStats: function() {
            // In a real implementation, we'd update stats via AJAX
            // For now, just reload the page to update stats
            // location.reload();
        },

        /**
         * Show error message
         */
        showError: function(message) {
            $('#eau-registrations-table').html(
                '<div class="eau-empty-state">' +
                '<span class="dashicons dashicons-warning"></span>' +
                '<h3>Error</h3>' +
                '<p>' + message + '</p>' +
                '</div>'
            );
        }
    };

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        EauEventRegAdmin.init();
    });

})(jQuery);
