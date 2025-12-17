/**
 * My Payments JavaScript Controller
 *
 * Handles the personal payments history page functionality.
 *
 * @package    EauSystem
 * @since      1.53.8
 */

(function($) {
    'use strict';

    const MyPaymentsController = {
        // State
        currentPage: 1,
        perPage: 20,
        totalPages: 1,
        total: 0,
        orderBy: 'date',
        order: 'DESC',
        search: '',
        paymentType: '',
        year: '',

        // Table selectors (based on Eau_Data_Table component)
        tableId: 'eau-my-payments-table',

        /**
         * Initializes the controller
         */
        init: function() {
            this.bindEvents();
            this.loadStats();
            this.loadPayments();

            // Initialize Lucide icons
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        },

        /**
         * Binds event handlers
         */
        bindEvents: function() {
            const self = this;

            // Search with debounce
            $('#eau-my-payments-search').on('input', this.debounce(function() {
                self.search = $(this).val();
                self.currentPage = 1;
                self.loadPayments();
            }, 500));

            // Filters toggle
            $('#eau-filters-toggle').on('click', function() {
                $('#eau-my-payments-filters').slideToggle(200);
                $(this).toggleClass('active');
            });

            // Payment type filter
            $('#eau-my-payments-filters').on('change', 'select[name="payment_type"]', function() {
                self.paymentType = $(this).val();
                self.currentPage = 1;
                self.loadPayments();
            });

            // Year filter
            $('#eau-my-payments-filters').on('change', 'select[name="year"]', function() {
                self.year = $(this).val();
                self.currentPage = 1;
                self.loadPayments();
            });

            // Sort columns
            $(document).on('click', '#' + this.tableId + ' th.eau-sortable', function() {
                const column = $(this).data('key');
                if (column) {
                    self.handleSort(column);
                }
            });

            // Pagination
            $(document).on('click', '#eau-pagination-container .eau-pagination-btn:not(.disabled)', function() {
                const page = $(this).data('page');
                if (page && page !== self.currentPage) {
                    self.currentPage = page;
                    self.loadPayments();
                }
            });

            // View payment details
            $(document).on('click', '.eau-action-view', function() {
                const paymentId = $(this).data('id');
                self.openPaymentDetail(paymentId);
            });

            // Download receipt from table
            $(document).on('click', '.eau-action-receipt', function() {
                const paymentId = $(this).data('id');
                const paymentType = $(this).data('type');
                self.downloadReceiptById(paymentId, paymentType);
            });

            // Close modal
            $(document).on('click', '[data-modal-action="close"]', function() {
                self.closeModal();
            });

            // Close modal on overlay click
            $(document).on('click', '.eau-modal-overlay', function(e) {
                if ($(e.target).hasClass('eau-modal-overlay')) {
                    self.closeModal();
                }
            });

            // Close modal on ESC key
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') {
                    self.closeModal();
                }
            });

            // Download receipt from modal
            $('#eau-mp-download-receipt-btn').on('click', function() {
                self.downloadReceipt();
            });
        },

        /**
         * Loads payments stats
         */
        loadStats: function() {
            const self = this;

            // Show skeleton loading
            this.showStatsSkeleton();

            $.ajax({
                url: eauMyPayments.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_get_my_payments_stats',
                    nonce: eauMyPayments.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.renderStats(response.data);
                    }
                },
                error: function() {
                    console.error('Failed to load stats');
                }
            });
        },

        /**
         * Shows skeleton loading for stats
         */
        showStatsSkeleton: function() {
            $('.eau-stat-card-number').each(function() {
                $(this).html('<div class="eau-skeleton eau-skeleton-text-short" style="height: 2rem; width: 80%;"></div>');
            });
        },

        /**
         * Renders stats cards
         */
        renderStats: function(data) {
            $('#stat-total-payments .eau-stat-card-number').text(data.total_payments);
            $('#stat-total-paid .eau-stat-card-number').text(data.total_paid_fmt);
            $('#stat-event-payments .eau-stat-card-number').text(data.event_payments);
            $('#stat-membership-payments .eau-stat-card-number').text(data.membership_payments);
        },

        /**
         * Loads payments from server
         */
        loadPayments: function() {
            const self = this;

            // Show skeleton loading
            this.showTableSkeleton();

            $.ajax({
                url: eauMyPayments.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_get_my_payments',
                    nonce: eauMyPayments.nonce,
                    page: this.currentPage,
                    per_page: this.perPage,
                    search: this.search,
                    payment_type: this.paymentType,
                    year: this.year,
                    order_by: this.orderBy,
                    order: this.order
                },
                success: function(response) {
                    if (response.success) {
                        self.total = response.data.total;
                        self.totalPages = response.data.total_pages;
                        self.renderPayments(response.data.rows);
                        self.renderPagination();
                        self.updateCount();
                    } else {
                        self.renderEmptyState();
                        self.updateCount();
                    }
                },
                error: function() {
                    EauNotifications.error(eauMyPayments.i18n.error);
                    self.renderEmptyState();
                }
            });
        },

        /**
         * Shows skeleton loading for table
         */
        showTableSkeleton: function() {
            const tbody = $('#' + this.tableId + '-tbody');
            const colspan = 7; // 6 columns + actions

            let skeletonHtml = '';
            for (let i = 0; i < 5; i++) {
                skeletonHtml += '<tr class="eau-table-row eau-skeleton-row">';
                for (let j = 0; j < colspan; j++) {
                    skeletonHtml += '<td class="eau-table-td"><div class="eau-skeleton eau-skeleton-text" style="height: 16px; width: ' + (60 + Math.random() * 30) + '%;"></div></td>';
                }
                skeletonHtml += '</tr>';
            }

            tbody.html(skeletonHtml);
        },

        /**
         * Updates the item count display
         */
        updateCount: function() {
            $('#' + this.tableId + '-count .eau-count-number').text(this.total);
        },

        /**
         * Renders payments table
         */
        renderPayments: function(rows) {
            const self = this;
            const tbody = $('#' + this.tableId + '-tbody');

            if (!rows || rows.length === 0) {
                this.renderEmptyState();
                return;
            }

            let html = '';

            rows.forEach(function(row) {
                html += '<tr class="eau-table-row" data-id="' + row.id + '">';
                html += '<td class="eau-table-td" data-label="DATE">' + row.date + '</td>';
                html += '<td class="eau-table-td" data-label="TYPE"><span class="eau-badge ' + row.type_class + '">' + row.type_label + '</span></td>';
                html += '<td class="eau-table-td eau-truncate" data-label="REFERENCE" title="' + self.escapeHtml(row.reference) + '">' + row.reference + '</td>';
                html += '<td class="eau-table-td eau-amount" data-label="AMOUNT"><strong>' + row.amount_fmt + '</strong></td>';
                html += '<td class="eau-table-td" data-label="METHOD">' + row.method + '</td>';
                html += '<td class="eau-table-td" data-label="STATUS"><span class="eau-badge ' + row.status_class + '">' + row.status + '</span></td>';
                html += '<td class="eau-table-td eau-table-td-actions">';
                html += '<div class="eau-table-actions">';
                html += '<button type="button" class="eau-action-btn eau-action-view" data-id="' + row.id + '" title="View Details">';
                html += '<i data-lucide="eye"></i>';
                html += '</button>';
                html += '<button type="button" class="eau-action-btn eau-action-receipt" data-id="' + row.id + '" data-type="' + row.payment_type + '" title="Download Receipt">';
                html += '<i data-lucide="file-text"></i>';
                html += '</button>';
                html += '</div>';
                html += '</td>';
                html += '</tr>';
            });

            tbody.html(html);

            // Re-initialize Lucide icons
            setTimeout(function() {
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
            }, 10);
        },

        /**
         * Renders empty state
         */
        renderEmptyState: function() {
            const tbody = $('#' + this.tableId + '-tbody');
            const colspan = 7;

            tbody.html(
                '<tr class="eau-table-empty">' +
                '<td colspan="' + colspan + '" style="text-align: center; padding: 3rem;">' +
                '<i data-lucide="inbox" style="width: 3rem; height: 3rem; color: #d1d5db; margin-bottom: 1rem; display: block; margin-left: auto; margin-right: auto;"></i>' +
                '<p style="color: #6b7280; margin: 0;">' + eauMyPayments.i18n.noResults + '</p>' +
                '</td>' +
                '</tr>'
            );

            // Re-initialize Lucide icons
            setTimeout(function() {
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
            }, 10);
        },

        /**
         * Renders pagination
         */
        renderPagination: function() {
            const container = $('#eau-pagination-container');

            if (this.total <= this.perPage) {
                container.html('');
                return;
            }

            const start = ((this.currentPage - 1) * this.perPage) + 1;
            const end = Math.min(this.currentPage * this.perPage, this.total);

            let html = '<div class="eau-pagination">';

            // Info text
            html += '<div class="eau-pagination-info">';
            html += 'Showing <strong>' + start + '</strong> to <strong>' + end + '</strong> of <strong>' + this.total + '</strong> payments';
            html += '</div>';

            // Navigation
            html += '<div class="eau-pagination-nav">';

            // Previous button
            html += '<button type="button" class="eau-pagination-btn' + (this.currentPage <= 1 ? ' disabled' : '') + '" data-page="' + (this.currentPage - 1) + '"' + (this.currentPage <= 1 ? ' disabled' : '') + '>';
            html += '<i data-lucide="chevron-left"></i>';
            html += '</button>';

            // Page numbers
            const pages = this.getPagesToShow();
            pages.forEach(function(page) {
                if (page === '...') {
                    html += '<span class="eau-pagination-ellipsis">...</span>';
                } else {
                    html += '<button type="button" class="eau-pagination-btn' + (page === this.currentPage ? ' active' : '') + '" data-page="' + page + '">' + page + '</button>';
                }
            }, this);

            // Next button
            html += '<button type="button" class="eau-pagination-btn' + (this.currentPage >= this.totalPages ? ' disabled' : '') + '" data-page="' + (this.currentPage + 1) + '"' + (this.currentPage >= this.totalPages ? ' disabled' : '') + '>';
            html += '<i data-lucide="chevron-right"></i>';
            html += '</button>';

            html += '</div>';
            html += '</div>';

            container.html(html);

            // Re-initialize Lucide icons
            setTimeout(function() {
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
            }, 10);
        },

        /**
         * Gets pages to show in pagination
         */
        getPagesToShow: function() {
            const pages = [];
            const current = this.currentPage;
            const total = this.totalPages;

            if (total <= 7) {
                for (let i = 1; i <= total; i++) {
                    pages.push(i);
                }
            } else {
                pages.push(1);

                if (current > 3) {
                    pages.push('...');
                }

                const start = Math.max(2, current - 1);
                const end = Math.min(total - 1, current + 1);

                for (let i = start; i <= end; i++) {
                    pages.push(i);
                }

                if (current < total - 2) {
                    pages.push('...');
                }

                pages.push(total);
            }

            return pages;
        },

        /**
         * Handles column sorting
         */
        handleSort: function(column) {
            if (this.orderBy === column) {
                this.order = this.order === 'ASC' ? 'DESC' : 'ASC';
            } else {
                this.orderBy = column;
                this.order = 'DESC';
            }

            // Update sort indicators
            $('#' + this.tableId + ' th').removeClass('sorted-asc sorted-desc');
            $('#' + this.tableId + ' th[data-key="' + column + '"]').addClass('sorted-' + this.order.toLowerCase());

            this.currentPage = 1;
            this.loadPayments();
        },

        /**
         * Opens payment detail modal
         */
        openPaymentDetail: function(paymentId) {
            const self = this;
            const modal = $('#eau-payment-detail-modal-overlay');

            // Store current payment ID for receipt download
            modal.data('current-payment-id', paymentId);

            // Show modal with loading state
            modal.fadeIn(200);

            $.ajax({
                url: eauMyPayments.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_get_my_payment_detail',
                    nonce: eauMyPayments.nonce,
                    payment_id: paymentId
                },
                success: function(response) {
                    if (response.success) {
                        self.populatePaymentModal(response.data);
                    } else {
                        EauNotifications.error(response.data.message || eauMyPayments.i18n.error);
                        self.closeModal();
                    }
                },
                error: function() {
                    EauNotifications.error(eauMyPayments.i18n.error);
                    self.closeModal();
                }
            });
        },

        /**
         * Populates payment detail modal
         */
        populatePaymentModal: function(data) {
            // Type badge
            $('#eau-mp-type-badge').html('<span class="eau-badge ' + data.type_class + '">' + data.type_label + '</span>');

            // Amount
            $('#eau-mp-amount').text(data.amount_fmt);

            // Status
            $('#eau-mp-status').html('<span class="eau-badge eau-badge-success">Confirmed</span>');

            // Date
            $('#eau-mp-date').text(data.date);

            // Method
            $('#eau-mp-method').text(data.method);

            // Reference
            $('#eau-mp-reference').text(data.reference);

            // Transaction ID
            if (data.transaction_id) {
                $('#eau-mp-transaction').text(data.transaction_id);
                $('#eau-mp-transaction-container').show();
            } else {
                $('#eau-mp-transaction-container').hide();
            }

            // Notes
            if (data.notes) {
                $('#eau-mp-notes').text(data.notes);
                $('#eau-mp-notes-section').show();
            } else {
                $('#eau-mp-notes-section').hide();
            }

            // Receipt link
            if (data.receipt_url) {
                $('#eau-mp-receipt-link').attr('href', data.receipt_url);
                $('#eau-mp-receipt-section').show();
            } else {
                $('#eau-mp-receipt-section').hide();
            }

            // Store data for receipt download
            $('#eau-payment-detail-modal-overlay').data('payment-type', data.payment_type);

            // Re-initialize Lucide icons
            setTimeout(function() {
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
            }, 10);
        },

        /**
         * Closes modal
         */
        closeModal: function() {
            $('.eau-modal-overlay').fadeOut(200);
        },

        /**
         * Downloads receipt for payment in modal
         */
        downloadReceipt: function() {
            const modal = $('#eau-payment-detail-modal-overlay');
            const paymentId = modal.data('current-payment-id');
            const paymentType = modal.data('payment-type') || 'payment';

            this.downloadReceiptById(paymentId, paymentType);
        },

        /**
         * Downloads receipt by payment ID
         */
        downloadReceiptById: function(paymentId, paymentType) {
            // For imported payments (eau_payment posts), use 'imported' type
            const receiptUrl = eauMyPayments.ajaxUrl +
                '?action=eau_generate_payment_receipt' +
                '&invoice_id=' + paymentId +
                '&invoice_type=' + paymentType +
                '&nonce=' + eauMyPayments.nonce;

            window.open(receiptUrl, '_blank');
        },

        /**
         * Escapes HTML characters
         */
        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        /**
         * Debounce utility
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
        if ($('.eau-my-payments-container').length) {
            MyPaymentsController.init();
        }
    });

})(jQuery);
