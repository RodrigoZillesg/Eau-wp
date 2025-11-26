/**
 * Eau Event Registrations Page - JavaScript
 *
 * @package EauSystem
 * @since 1.29.3
 */

(function($) {
    'use strict';

    const EauEventRegistrationsPage = {
        // State
        currentPage: 1,
        perPage: 20,
        searchTerm: '',
        statusFilter: '',
        orderBy: 'registration_date',
        order: 'DESC',
        eventId: null,

        /**
         * Initialize
         */
        init: function() {
            this.eventId = $('.eau-event-registrations-container').data('event-id');
            if (!this.eventId) {
                console.error('Event ID not found');
                return;
            }

            this.bindEvents();
            this.loadRegistrations();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            const self = this;

            // Search
            let searchTimeout;
            $('#eau-registrations-search').on('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(function() {
                    self.searchTerm = $('#eau-registrations-search').val();
                    self.currentPage = 1;
                    self.loadRegistrations();
                }, 300);
            });

            // Status filter
            $('#eau-registrations-status-filter').on('change', function() {
                self.statusFilter = $(this).val();
                self.currentPage = 1;
                self.loadRegistrations();
            });

            // Sortable columns
            $(document).on('click', '.eau-sortable', function() {
                const sort = $(this).data('sort');

                if (self.orderBy === sort) {
                    self.order = self.order === 'ASC' ? 'DESC' : 'ASC';
                } else {
                    self.orderBy = sort;
                    self.order = 'ASC';
                }

                self.loadRegistrations();
            });

            // Dropdown toggle
            $(document).on('click', '.eau-action-more', function(e) {
                e.stopPropagation();
                const $menu = $(this).siblings('.eau-dropdown-menu');
                $('.eau-dropdown-menu').not($menu).removeClass('active');
                $menu.toggleClass('active');
            });

            // Close dropdown on click outside
            $(document).on('click', function() {
                $('.eau-dropdown-menu').removeClass('active');
            });

            // Status change actions
            $(document).on('click', '.eau-reg-status-btn', function(e) {
                e.stopPropagation();
                const regId = $(this).data('id');
                const newStatus = $(this).data('status');
                $('.eau-dropdown-menu').removeClass('active');
                self.updateRegistrationStatus(regId, newStatus);
            });

            // Pagination
            $(document).on('click', '.eau-pagination-btn:not(.disabled)', function() {
                const page = $(this).data('page');
                if (page) {
                    self.currentPage = page;
                    self.loadRegistrations();
                }
            });

            // Export CSV
            $('#eau-export-csv').on('click', function() {
                self.exportCSV();
            });
        },

        /**
         * Load registrations via AJAX
         */
        loadRegistrations: function() {
            const self = this;
            const $tbody = $('#eau-registrations-tbody');

            // Show skeleton loading
            $tbody.html('<tr><td colspan="5" class="eau-loading-cell"><div class="eau-skeleton-row"></div></td></tr>');

            $.ajax({
                url: eauEventRegistrations.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_get_event_registrations',
                    nonce: eauEventRegistrations.nonce,
                    event_id: this.eventId,
                    page: this.currentPage,
                    per_page: this.perPage,
                    search: this.searchTerm,
                    status: this.statusFilter,
                    orderby: this.orderBy,
                    order: this.order
                },
                success: function(response) {
                    if (response.success) {
                        self.renderTable(response.data.rows);
                        self.renderPagination(response.data);
                        self.updateStats(response.data.stats);
                    } else {
                        $tbody.html('<tr><td colspan="5" class="eau-empty-cell">Error loading registrations</td></tr>');
                    }
                    lucide.createIcons();
                },
                error: function() {
                    $tbody.html('<tr><td colspan="5" class="eau-empty-cell">Error loading registrations</td></tr>');
                }
            });
        },

        /**
         * Render table rows
         */
        renderTable: function(rows) {
            const self = this;
            const $tbody = $('#eau-registrations-tbody');

            if (!rows || rows.length === 0) {
                $tbody.html('<tr><td colspan="5" class="eau-empty-cell">No registrations found</td></tr>');
                return;
            }

            let html = '';
            rows.forEach(function(row) {
                const statusBadgeClass = 'eau-badge-' + row.status_class;

                html += '<tr>';
                html += '<td class="eau-member-cell">';
                html += '<span class="eau-member-name">' + self.escapeHtml(row.attendee_name) + '</span>';
                html += '</td>';
                html += '<td class="eau-contact-cell">';
                html += '<a href="mailto:' + self.escapeHtml(row.attendee_email) + '">' + self.escapeHtml(row.attendee_email) + '</a>';
                html += '</td>';
                html += '<td>' + row.registration_date + '</td>';
                html += '<td>';
                html += '<span class="eau-badge ' + statusBadgeClass + '">' + row.status_label + '</span>';
                html += '</td>';
                html += '<td class="eau-table-actions-col">';
                html += '<div class="eau-table-actions">';
                html += self.renderStatusActions(row);
                html += '</div>';
                html += '</td>';
                html += '</tr>';
            });

            $tbody.html(html);
        },

        /**
         * Render status action buttons (dropdown with payment status options)
         */
        renderStatusActions: function(row) {
            let actions = '';

            // Dropdown for payment status
            actions += '<div class="eau-dropdown" data-id="' + row.id + '">';
            actions += '<button class="eau-action-btn eau-action-more" title="Change Payment Status">';
            actions += '<i data-lucide="more-vertical"></i>';
            actions += '</button>';
            actions += '<div class="eau-dropdown-menu">';

            if (row.status !== 'paid') {
                actions += '<button class="eau-dropdown-item eau-reg-status-btn" data-id="' + row.id + '" data-status="paid">';
                actions += '<i data-lucide="check-circle"></i> Mark as Paid';
                actions += '</button>';
            }

            if (row.status !== 'pending') {
                actions += '<button class="eau-dropdown-item eau-reg-status-btn" data-id="' + row.id + '" data-status="pending">';
                actions += '<i data-lucide="clock"></i> Mark as Pending';
                actions += '</button>';
            }

            if (row.status !== 'failed') {
                actions += '<button class="eau-dropdown-item eau-dropdown-item-danger eau-reg-status-btn" data-id="' + row.id + '" data-status="failed">';
                actions += '<i data-lucide="x-circle"></i> Mark as Failed';
                actions += '</button>';
            }

            if (row.status !== 'refunded') {
                actions += '<button class="eau-dropdown-item eau-reg-status-btn" data-id="' + row.id + '" data-status="refunded">';
                actions += '<i data-lucide="rotate-ccw"></i> Mark as Refunded';
                actions += '</button>';
            }

            actions += '</div>';
            actions += '</div>';

            return actions;
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
            html += '<button class="eau-pagination-btn ' + (data.page <= 1 ? 'disabled' : '') + '" data-page="' + (data.page - 1) + '">';
            html += '<i data-lucide="chevron-left"></i>';
            html += '</button>';

            // Page numbers
            for (let i = 1; i <= data.total_pages; i++) {
                if (i === 1 || i === data.total_pages || (i >= data.page - 2 && i <= data.page + 2)) {
                    html += '<button class="eau-pagination-btn ' + (i === data.page ? 'active' : '') + '" data-page="' + i + '">' + i + '</button>';
                } else if (i === data.page - 3 || i === data.page + 3) {
                    html += '<span class="eau-pagination-ellipsis">...</span>';
                }
            }

            // Next button
            html += '<button class="eau-pagination-btn ' + (data.page >= data.total_pages ? 'disabled' : '') + '" data-page="' + (data.page + 1) + '">';
            html += '<i data-lucide="chevron-right"></i>';
            html += '</button>';

            html += '</div>';

            $container.html(html);
            lucide.createIcons();
        },

        /**
         * Update stats cards
         */
        updateStats: function(stats) {
            // Update stats values (if elements exist)
            $('.eau-stats-card-value').each(function() {
                const $parent = $(this).closest('.eau-stats-card');
                const label = $parent.find('.eau-stats-card-label').text().toLowerCase();

                if (stats[label] !== undefined) {
                    $(this).text(stats[label]);
                }
            });
        },

        /**
         * Update registration status
         */
        updateRegistrationStatus: function(regId, newStatus) {
            const self = this;

            $.ajax({
                url: eauEventRegistrations.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_update_registration_status',
                    nonce: eauEventRegistrations.nonce,
                    registration_id: regId,
                    status: newStatus
                },
                success: function(response) {
                    if (response.success) {
                        self.showToast(response.data.message, 'success');
                        self.loadRegistrations();
                    } else {
                        self.showToast(response.data.message || 'Error updating status', 'error');
                    }
                },
                error: function() {
                    self.showToast('Error updating status', 'error');
                }
            });
        },

        /**
         * Export CSV
         */
        exportCSV: function() {
            const self = this;

            $.ajax({
                url: eauEventRegistrations.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_export_event_registrations',
                    nonce: eauEventRegistrations.nonce,
                    event_id: this.eventId,
                    search: this.searchTerm,
                    status: this.statusFilter
                },
                success: function(response) {
                    if (response.success && response.data.csv) {
                        // Create download
                        const blob = new Blob([response.data.csv], { type: 'text/csv' });
                        const url = window.URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = response.data.filename || 'registrations.csv';
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                        window.URL.revokeObjectURL(url);

                        self.showToast('CSV exported successfully', 'success');
                    } else {
                        self.showToast(response.data.message || 'Error exporting CSV', 'error');
                    }
                },
                error: function() {
                    self.showToast('Error exporting CSV', 'error');
                }
            });
        },

        /**
         * Show toast notification
         */
        showToast: function(message, type) {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: type || 'info',
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
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        if ($('.eau-event-registrations-container').length) {
            EauEventRegistrationsPage.init();
        }
    });

})(jQuery);
