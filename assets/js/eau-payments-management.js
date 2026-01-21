/**
 * Payments Management JavaScript
 *
 * Refatorado para mostrar FATURAS (registrations/applications) ao invés de pagamentos.
 * Segue o padrão de Event Registrations.
 *
 * @package    EauSystem
 * @since      1.50.1
 * @updated    1.51.0 - Refatorado para faturas
 */
(function($) {
    'use strict';

    const EauPaymentsController = {
        // State
        currentPage: 1,
        perPage: 20,
        searchTerm: '',
        filters: {},
        orderBy: 'date',
        order: 'DESC',
        currentInvoice: null,

        /**
         * Inicializa o controller
         */
        init: function() {
            this.bindEvents();
            this.checkUrlParams(); // Check for URL parameters BEFORE loading invoices
            this.loadInvoices();
            this.loadStats();
        },

        /**
         * Check URL parameters for filters
         */
        checkUrlParams: function() {
            const urlParams = new URLSearchParams(window.location.search);
            const paymentStatusFilter = urlParams.get('payment_status');

            // Apply payment_status filter from URL
            if (paymentStatusFilter) {
                // Set the filter value
                this.filters.payment_status = paymentStatusFilter;

                // Update the select element
                $('select[name="payment_status"]').val(paymentStatusFilter);

                // Show the filters panel
                $('#eau-invoices-filters').show();

                // Remove the parameter from URL to avoid re-applying on refresh
                const url = new URL(window.location);
                url.searchParams.delete('payment_status');
                window.history.replaceState({}, document.title, url);

                // Reset to first page
                this.currentPage = 1;
            }
        },

        /**
         * Vincula eventos
         */
        bindEvents: function() {
            const self = this;

            // Search
            let searchTimeout;
            $('#eau-invoices-search').on('input', function() {
                clearTimeout(searchTimeout);
                const term = $(this).val();
                searchTimeout = setTimeout(function() {
                    self.searchTerm = term;
                    self.currentPage = 1;
                    self.loadInvoices();
                }, 300);
            });

            // Filters toggle
            $('#eau-filters-toggle').on('click', function() {
                $('#eau-invoices-filters').slideToggle(200);
            });

            // Filter changes
            $(document).on('change', '#eau-invoices-filters select', function() {
                const key = $(this).attr('name');
                const value = $(this).val();
                self.filters[key] = value;
                self.currentPage = 1;
                self.loadInvoices();
            });

            // Sorting
            $(document).on('click', '.eau-data-table th.eau-sortable', function() {
                const column = $(this).data('sort');
                if (self.orderBy === column) {
                    self.order = self.order === 'ASC' ? 'DESC' : 'ASC';
                } else {
                    self.orderBy = column;
                    self.order = 'ASC';
                }
                self.loadInvoices();
            });

            // Pagination
            $(document).on('click', '#eau-pagination-container .eau-pagination-btn', function(e) {
                e.preventDefault();
                if ($(this).is(':disabled') || $(this).hasClass('eau-pagination-active')) {
                    return;
                }

                const page = parseInt($(this).data('page'));
                if (page) {
                    self.currentPage = page;
                    self.loadInvoices();

                    // Scroll to top of table
                    $('html, body').animate({
                        scrollTop: $('.eau-payments-management-container').offset().top - 100
                    }, 300);
                }
            });

            // View invoice (manage payments)
            $(document).on('click', '.eau-action-view', function(e) {
                e.preventDefault();
                const row = $(this).closest('tr');
                const invoiceId = row.data('id');
                const invoiceType = row.data('type');
                self.openPaymentModal(invoiceId, invoiceType);
            });

            // Download receipt
            $(document).on('click', '.eau-action-receipt', function(e) {
                e.preventDefault();
                const invoiceId = $(this).data('id');
                const invoiceType = $(this).data('type');
                self.downloadReceipt(invoiceId, invoiceType);
            });

            // Copy payment link
            $(document).on('click', '.eau-action-copy-link', function(e) {
                e.preventDefault();
                const btn = $(this);
                const invoiceId = btn.data('id');
                const invoiceType = btn.data('type');
                self.copyPaymentLink(invoiceId, invoiceType, btn);
            });

            // Modal close
            $(document).on('click', '[data-modal-action="close"], .eau-modal-overlay', function(e) {
                if (e.target === this) {
                    self.closePaymentModal();
                }
            });

            // Prevent modal close when clicking inside modal
            $(document).on('click', '.eau-modal', function(e) {
                e.stopPropagation();
            });

            // Add payment form
            $('#eau-add-payment-form').on('submit', function(e) {
                e.preventDefault();
                self.addPayment();
            });

            // Delete payment
            $(document).on('click', '.eau-delete-payment-btn', function(e) {
                e.preventDefault();
                const paymentId = $(this).data('payment-id');
                if (confirm(eauPaymentsManagement.i18n.confirmDelete)) {
                    self.deletePayment(paymentId);
                }
            });

            // Export CSV
            $('#eau-export-csv-btn').on('click', function() {
                self.exportCSV();
            });

            // ========== CSV Import Event Handlers ==========

            // Open import modal
            $('#eau-import-csv-btn').on('click', function() {
                self.openImportModal();
            });

            // Import modal close
            $('#eau-import-modal-overlay').on('click', '[data-modal-action="close"]', function() {
                self.closeImportModal();
            });

            // Import browse button
            $('#eau-import-browse-btn').on('click', function() {
                $('#eau-import-file-input').click();
            });

            // Import file input change
            $('#eau-import-file-input').on('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    self.handleImportFile(file);
                }
            });

            // Import dropzone drag and drop
            const dropzone = $('#eau-import-dropzone');
            dropzone.on('dragover', function(e) {
                e.preventDefault();
                $(this).addClass('dragover');
            });
            dropzone.on('dragleave', function() {
                $(this).removeClass('dragover');
            });
            dropzone.on('drop', function(e) {
                e.preventDefault();
                $(this).removeClass('dragover');
                const file = e.originalEvent.dataTransfer.files[0];
                if (file) {
                    self.handleImportFile(file);
                }
            });

            // Import remove file button
            $('#eau-import-remove-file').on('click', function() {
                self.resetImport();
            });

            // Import preview button
            $('#eau-import-preview-btn').on('click', function() {
                self.previewImport();
            });

            // Import start button
            $('#eau-import-start-btn').on('click', function() {
                self.executeImport();
            });

            // Import done button
            $('#eau-import-done-btn').on('click', function() {
                self.closeImportModal();
                self.loadInvoices(); // Reload data
                self.loadStats();
            });

            // ========== Media Upload Event Handlers ==========

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

            // Media upload - Browse button
            $(document).on('click', '.eau-media-upload-browse-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const wrapper = $(this).closest('.eau-media-upload-wrapper');
                const fileInput = wrapper.find('.eau-media-upload-file-input')[0];
                if (fileInput) {
                    fileInput.click();
                }
            });

            // Media upload - Click on dropzone
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
         * Carrega faturas via AJAX
         */
        loadInvoices: function() {
            const self = this;

            // Show skeleton loading
            this.showTableSkeleton();

            $.ajax({
                url: eauPaymentsManagement.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_get_invoices',
                    nonce: eauPaymentsManagement.nonce,
                    page: this.currentPage,
                    per_page: this.perPage,
                    search: this.searchTerm,
                    invoice_type: this.filters.invoice_type || '',
                    payment_status: this.filters.payment_status || '',
                    order_by: this.orderBy,
                    order: this.order,
                },
                success: function(response) {
                    if (response.success) {
                        self.renderTable(response.data.rows);
                        self.renderPagination(response.data);
                        $('#eau-invoices-table-count .eau-count-number').text(response.data.total || 0);
                    } else {
                        self.showError(response.data.message || eauPaymentsManagement.i18n.error);
                    }
                },
                error: function() {
                    self.showError(eauPaymentsManagement.i18n.error);
                }
            });
        },

        /**
         * Carrega estatísticas
         */
        loadStats: function() {
            const self = this;

            // Show skeleton loading in stats cards
            self.showStatsSkeleton();

            $.ajax({
                url: eauPaymentsManagement.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_get_invoice_stats',
                    nonce: eauPaymentsManagement.nonce,
                },
                success: function(response) {
                    if (response.success) {
                        const stats = response.data;
                        self.updateStatsCard('stat-total-due', '$' + parseFloat(stats.total_due).toLocaleString('en-US', {minimumFractionDigits: 2}));
                        self.updateStatsCard('stat-total-paid', '$' + parseFloat(stats.total_paid).toLocaleString('en-US', {minimumFractionDigits: 2}));
                        self.updateStatsCard('stat-pending', stats.pending_count);
                        self.updateStatsCard('stat-paid', stats.paid_count);
                    }
                },
                error: function() {
                    // Remove skeleton on error, show dashes
                    self.updateStatsCard('stat-total-due', '-');
                    self.updateStatsCard('stat-total-paid', '-');
                    self.updateStatsCard('stat-pending', '-');
                    self.updateStatsCard('stat-paid', '-');
                }
            });
        },

        /**
         * Mostra skeleton nos stats cards
         */
        showStatsSkeleton: function() {
            $('.eau-stat-card-number').each(function() {
                $(this).html('<div class="eau-skeleton eau-skeleton-text-short" style="height: 2rem; width: 80%;"></div>');
            });
        },

        /**
         * Atualiza um stats card removendo skeleton
         */
        updateStatsCard: function(cardId, value) {
            $('#' + cardId + ' .eau-stat-card-number').text(value);
        },

        /**
         * Exibe skeleton loading na tabela
         */
        showTableSkeleton: function() {
            const skeletonRows = this.getSkeletonRows(5);
            $('#eau-invoices-table tbody').html(skeletonRows);
        },

        /**
         * Gera linhas de skeleton
         */
        getSkeletonRows: function(count) {
            let html = '';
            for (let i = 0; i < count; i++) {
                html += `
                <tr class="eau-table-row">
                    <td class="eau-table-td"><div class="eau-skeleton eau-skeleton-text"></div></td>
                    <td class="eau-table-td"><div class="eau-skeleton eau-skeleton-badge"></div></td>
                    <td class="eau-table-td"><div class="eau-skeleton eau-skeleton-text"></div></td>
                    <td class="eau-table-td"><div class="eau-skeleton eau-skeleton-text-short"></div></td>
                    <td class="eau-table-td"><div class="eau-skeleton eau-skeleton-text-short"></div></td>
                    <td class="eau-table-td"><div class="eau-skeleton eau-skeleton-text-short"></div></td>
                    <td class="eau-table-td"><div class="eau-skeleton eau-skeleton-badge"></div></td>
                    <td class="eau-table-td eau-table-td-actions"><div class="eau-skeleton eau-skeleton-icon"></div></td>
                </tr>`;
            }
            return html;
        },

        /**
         * Renderiza tabela de faturas
         */
        renderTable: function(rows) {
            const tbody = $('#eau-invoices-table tbody');
            tbody.empty();

            if (!rows || rows.length === 0) {
                tbody.html(`
                    <tr class="eau-table-row">
                        <td class="eau-table-td" colspan="8" style="text-align: center; padding: 2rem;">
                            ${eauPaymentsManagement.i18n.noResults}
                        </td>
                    </tr>
                `);
                return;
            }

            rows.forEach(function(row) {
                const tr = $(`
                    <tr class="eau-table-row" data-id="${row.id}" data-type="${row.invoice_type}">
                        <td class="eau-table-td" data-label="MEMBER">
                            <div class="eau-member-cell">
                                <span class="eau-member-name">${row.member_name}</span>
                                <span class="eau-member-email">${row.member_email}</span>
                            </div>
                        </td>
                        <td class="eau-table-td" data-label="TYPE">
                            <span class="eau-badge ${row.type_class}">${row.type_label}</span>
                        </td>
                        <td class="eau-table-td" data-label="REFERENCE">${row.reference}</td>
                        <td class="eau-table-td" data-label="DUE">${row.amount_due_fmt}</td>
                        <td class="eau-table-td eau-amount-paid" data-label="PAID">${row.amount_paid_fmt}</td>
                        <td class="eau-table-td ${row.balance > 0 ? 'eau-amount-balance' : ''}" data-label="BALANCE">${row.balance_fmt}</td>
                        <td class="eau-table-td" data-label="STATUS">
                            <span class="eau-badge ${row.status_class}">${row.status_label}</span>
                        </td>
                        <td class="eau-table-td eau-table-td-actions">
                            <div class="eau-table-actions">
                                <button type="button" class="eau-action-btn eau-action-view" title="Manage Payments">
                                    <i data-lucide="credit-card"></i>
                                </button>
                                ${row.payment_status !== 'paid' ? `
                                <button type="button" class="eau-action-btn eau-action-copy-link" title="Copy Payment Link" data-id="${row.id}" data-type="${row.invoice_type}">
                                    <i data-lucide="link"></i>
                                </button>
                                ` : `
                                <button type="button" class="eau-action-btn eau-action-receipt" title="Download Receipt" data-id="${row.id}" data-type="${row.invoice_type}">
                                    <i data-lucide="file-text"></i>
                                </button>
                                `}
                            </div>
                        </td>
                    </tr>
                `);
                tbody.append(tr);
            });

            // Re-initialize Lucide icons
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        },

        /**
         * Renderiza paginação (padrão do componente Eau_Pagination)
         */
        renderPagination: function(data) {
            const container = $('#eau-pagination-container');
            container.empty();

            const totalPages = data.total_pages || 1;

            if (totalPages <= 1) {
                return;
            }

            const currentPage = data.page;
            const perPage = this.perPage;
            const startItem = ((currentPage - 1) * perPage) + 1;
            const endItem = Math.min(currentPage * perPage, data.total);

            const html = this.buildPaginationHTML(currentPage, totalPages, startItem, endItem, data.total);
            container.html(html);

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        },

        /**
         * Constrói HTML da paginação (seguindo padrão Eau_Pagination)
         */
        buildPaginationHTML: function(currentPage, totalPages, startItem, endItem, total) {
            const pagesToShow = this.getPagesToShow(currentPage, totalPages);

            let html = '<div class="eau-pagination-wrapper">';

            // Info
            html += '<div class="eau-pagination-info">';
            html += `Showing ${startItem.toLocaleString()} to ${endItem.toLocaleString()} of ${total.toLocaleString()} items`;
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
         * Calcula quais páginas mostrar na paginação
         */
        getPagesToShow: function(currentPage, totalPages) {
            const pages = [];
            const delta = 2; // Páginas antes e depois da atual

            // Sempre mostrar primeira página
            pages.push(1);

            // Calcular range ao redor da página atual
            const rangeStart = Math.max(2, currentPage - delta);
            const rangeEnd = Math.min(totalPages - 1, currentPage + delta);

            // Adicionar ellipsis se necessário antes do range
            if (rangeStart > 2) {
                pages.push('...');
            }

            // Adicionar páginas no range
            for (let i = rangeStart; i <= rangeEnd; i++) {
                if (!pages.includes(i)) {
                    pages.push(i);
                }
            }

            // Adicionar ellipsis se necessário depois do range
            if (rangeEnd < totalPages - 1) {
                pages.push('...');
            }

            // Sempre mostrar última página (se maior que 1)
            if (totalPages > 1 && !pages.includes(totalPages)) {
                pages.push(totalPages);
            }

            return pages;
        },

        /**
         * Abre modal de pagamento
         */
        openPaymentModal: function(invoiceId, invoiceType) {
            const self = this;

            // Store current invoice
            this.currentInvoice = { id: invoiceId, type: invoiceType };

            // Set hidden fields
            $('#eau-invoice-id').val(invoiceId);
            $('#eau-invoice-type').val(invoiceType);

            // Set default date to today
            $('#eau-payment-date').val(new Date().toISOString().split('T')[0]);

            // Reset form
            $('#eau-add-payment-form')[0].reset();
            $('#eau-invoice-id').val(invoiceId);
            $('#eau-invoice-type').val(invoiceType);
            $('#eau-payment-date').val(new Date().toISOString().split('T')[0]);

            // Show modal
            $('#eau-payment-modal-overlay').fadeIn(200);

            // Load invoice details
            this.loadInvoiceDetails(invoiceId, invoiceType);
        },

        /**
         * Fecha modal de pagamento
         */
        closePaymentModal: function() {
            $('#eau-payment-modal-overlay').fadeOut(200);
            this.currentInvoice = null;
            // Refresh table
            this.loadInvoices();
            this.loadStats();
        },

        /**
         * Carrega detalhes da fatura
         */
        loadInvoiceDetails: function(invoiceId, invoiceType) {
            const self = this;

            $.ajax({
                url: eauPaymentsManagement.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_get_invoice_details',
                    nonce: eauPaymentsManagement.nonce,
                    invoice_id: invoiceId,
                    invoice_type: invoiceType,
                },
                success: function(response) {
                    if (response.success) {
                        self.populateModal(response.data);
                    } else {
                        self.showError(response.data.message || eauPaymentsManagement.i18n.error);
                    }
                },
                error: function() {
                    self.showError(eauPaymentsManagement.i18n.error);
                }
            });
        },

        /**
         * Popula modal com dados da fatura
         */
        populateModal: function(data) {
            const isImported = data.is_imported === true;

            // Member info (new structure)
            $('#eau-payment-member-name').text(data.member_name || '-');
            $('#eau-payment-member-email').text(data.member_email || '-');

            // Update badge based on type
            const badgeHtml = data.invoice_type === 'event'
                ? '<span class="eau-badge eau-badge-info">Event</span>'
                : '<span class="eau-badge eau-badge-purple">Membership</span>';
            $('#eau-payment-status-badge').html(badgeHtml);

            // Reference
            $('#eau-payment-reference').text(data.reference || '-');
            $('#eau-payment-type').val(data.invoice_type);

            // Payment summary
            $('#eau-payment-amount-due').text(data.amount_due_fmt || '$0.00');
            $('#eau-payment-total-paid').text(data.total_paid_fmt || '$0.00');
            $('#eau-payment-balance').text(data.balance_fmt || '$0.00');

            // Handle imported payment extra info
            if (isImported) {
                // Show imported payment details section
                this.showImportedPaymentDetails(data);
                // Hide add payment form (imported payments are already fully paid)
                $('#eau-add-payment-form').hide();
                // Show imported notice
                if ($('#eau-imported-notice').length === 0) {
                    $('#eau-add-payment-form').before(`
                        <div id="eau-imported-notice" class="eau-imported-notice">
                            <i data-lucide="info"></i>
                            <span>This is a historical payment imported from the legacy system. It cannot be modified.</span>
                        </div>
                    `);
                }
            } else {
                // Standard invoice - show add payment form
                $('#eau-add-payment-form').show();
                $('#eau-imported-notice').remove();
                $('#eau-imported-details').remove();
            }

            // Payments list
            this.renderPaymentsList(data.payments || [], isImported);

            // Pre-fill amount with balance if there's a balance
            if (data.balance > 0) {
                $('#eau-payment-amount').val(data.balance.toFixed(2));
            } else {
                $('#eau-payment-amount').val('');
            }

            // Re-init Lucide
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        },

        /**
         * Mostra detalhes adicionais para pagamentos importados
         */
        showImportedPaymentDetails: function(data) {
            // Remove existing details
            $('#eau-imported-details').remove();

            let detailsHtml = '<div id="eau-imported-details" class="eau-imported-details">';
            detailsHtml += '<h4><i data-lucide="file-text"></i> Legacy System Details</h4>';
            detailsHtml += '<div class="eau-imported-details-grid">';

            if (data.legacy_order_no) {
                detailsHtml += `<div class="eau-imported-detail"><span class="label">Order #</span><span class="value">${data.legacy_order_no}</span></div>`;
            }
            if (data.transaction_id) {
                detailsHtml += `<div class="eau-imported-detail"><span class="label">Transaction ID</span><span class="value">${data.transaction_id}</span></div>`;
            }
            if (data.card_type) {
                detailsHtml += `<div class="eau-imported-detail"><span class="label">Card Type</span><span class="value">${data.card_type}</span></div>`;
            }
            if (data.subtotal_amount > 0) {
                detailsHtml += `<div class="eau-imported-detail"><span class="label">Subtotal</span><span class="value">${data.subtotal_amount_fmt}</span></div>`;
            }
            if (data.tax_amount > 0) {
                detailsHtml += `<div class="eau-imported-detail"><span class="label">Tax</span><span class="value">${data.tax_amount_fmt}</span></div>`;
            }

            detailsHtml += '</div></div>';

            // Insert after the summary
            $('.eau-pm-summary').after(detailsHtml);
        },

        /**
         * Renderiza lista de pagamentos no modal
         */
        renderPaymentsList: function(payments, isImported) {
            const container = $('#eau-payments-list');
            container.empty();

            if (!payments || payments.length === 0) {
                container.html(`
                    <div class="eau-pm-empty-state">
                        <i data-lucide="inbox"></i>
                        <p>No payments recorded yet</p>
                    </div>
                `);
                return;
            }

            payments.forEach(function(payment) {
                const receiptHtml = payment.has_receipt
                    ? `<a href="${payment.receipt_url}" target="_blank" class="eau-view-receipt">View Receipt</a>`
                    : '';

                // For imported payments, show transaction ID and card type instead of delete button
                let extraInfo = '';
                let actionHtml = '';

                if (payment.is_imported || isImported) {
                    // Show additional imported info
                    if (payment.transaction_id) {
                        extraInfo += ` &bull; <span class="eau-payment-txn">TXN: ${payment.transaction_id}</span>`;
                    }
                    if (payment.card_type) {
                        extraInfo += ` &bull; <span class="eau-payment-card">${payment.card_type}</span>`;
                    }
                    // No delete button for imported payments
                    actionHtml = `<span class="eau-payment-imported-badge"><i data-lucide="check-circle"></i></span>`;
                } else {
                    // Standard payment - show delete button
                    actionHtml = `
                        <button type="button" class="eau-delete-payment-btn" data-payment-id="${payment.id}" title="Delete payment">
                            <i data-lucide="trash-2"></i>
                        </button>
                    `;
                }

                const html = `
                    <div class="eau-payment-item ${payment.is_imported || isImported ? 'eau-payment-item-imported' : ''}">
                        <div class="eau-payment-item-info">
                            <div class="eau-payment-item-amount">${payment.amount}</div>
                            <div class="eau-payment-item-details">
                                ${payment.date} &bull; ${payment.method}
                                ${payment.notes ? ` &bull; <em>${payment.notes}</em>` : ''}
                                ${extraInfo}
                                ${receiptHtml}
                            </div>
                        </div>
                        ${actionHtml}
                    </div>
                `;
                container.append(html);
            });
        },

        /**
         * Adiciona pagamento
         */
        addPayment: function() {
            const self = this;
            const form = $('#eau-add-payment-form');
            const btn = $('#eau-add-payment-btn');

            // Disable button
            btn.prop('disabled', true).html('<i data-lucide="loader-2" class="eau-spin"></i> Adding...');

            // Get form data
            const formData = {
                action: 'eau_add_invoice_payment',
                nonce: eauPaymentsManagement.nonce,
                invoice_id: $('#eau-invoice-id').val(),
                invoice_type: $('#eau-invoice-type').val(),
                amount: $('#eau-payment-amount').val(),
                payment_date: $('#eau-payment-date').val(),
                payment_method: $('#eau-payment-method').val(),
                notes: $('#eau-payment-notes').val(),
                receipt_id: $('[name="receipt_id"]').val() || '',
            };

            $.ajax({
                url: eauPaymentsManagement.ajaxUrl,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        // Update modal with new data
                        if (response.data.details) {
                            self.populateModal(response.data.details);
                        }
                        // Reset form fields (but keep invoice IDs)
                        $('#eau-payment-amount').val('');
                        $('#eau-payment-method').val('');
                        $('#eau-payment-notes').val('');
                        // Show success message
                        self.showSuccess(response.data.message || 'Payment added successfully');
                    } else {
                        self.showError(response.data.message || eauPaymentsManagement.i18n.error);
                    }
                },
                error: function() {
                    self.showError(eauPaymentsManagement.i18n.error);
                },
                complete: function() {
                    btn.prop('disabled', false).html('<i data-lucide="plus"></i> Add Payment');
                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                }
            });
        },

        /**
         * Deleta pagamento
         */
        deletePayment: function(paymentId) {
            const self = this;

            $.ajax({
                url: eauPaymentsManagement.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_delete_invoice_payment',
                    nonce: eauPaymentsManagement.nonce,
                    payment_id: paymentId,
                    invoice_id: this.currentInvoice.id,
                    invoice_type: this.currentInvoice.type,
                },
                success: function(response) {
                    if (response.success) {
                        // Update modal with new data
                        if (response.data.details) {
                            self.populateModal(response.data.details);
                        }
                        self.showSuccess(response.data.message || 'Payment deleted');
                    } else {
                        self.showError(response.data.message || eauPaymentsManagement.i18n.error);
                    }
                },
                error: function() {
                    self.showError(eauPaymentsManagement.i18n.error);
                }
            });
        },

        /**
         * Exporta CSV
         */
        exportCSV: function() {
            const self = this;

            $.ajax({
                url: eauPaymentsManagement.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_export_invoices_csv',
                    nonce: eauPaymentsManagement.nonce,
                    search: this.searchTerm,
                    invoice_type: this.filters.invoice_type || '',
                    payment_status: this.filters.payment_status || '',
                },
                success: function(response) {
                    if (response.success) {
                        // Download CSV
                        const blob = new Blob([response.data.csv], { type: 'text/csv' });
                        const url = window.URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = response.data.filename;
                        document.body.appendChild(a);
                        a.click();
                        window.URL.revokeObjectURL(url);
                        document.body.removeChild(a);
                    } else {
                        self.showError(response.data.message || 'Export failed');
                    }
                },
                error: function() {
                    self.showError('Export failed');
                }
            });
        },

        /**
         * Abre o receipt em nova aba para download/impressão
         */
        downloadReceipt: function(invoiceId, invoiceType) {
            // Build receipt URL with nonce
            const receiptUrl = eauPaymentsManagement.ajaxUrl +
                '?action=eau_generate_payment_receipt' +
                '&invoice_id=' + invoiceId +
                '&invoice_type=' + invoiceType +
                '&nonce=' + eauPaymentsManagement.nonce;

            // Open in new tab
            window.open(receiptUrl, '_blank');
        },

        /**
         * Copia o link de pagamento para a área de transferência
         */
        copyPaymentLink: function(invoiceId, invoiceType, btn) {
            const self = this;

            // Build checkout URL based on type
            let checkoutUrl = eauPaymentsManagement.checkoutUrl;

            // Add query parameters
            if (invoiceType === 'event') {
                checkoutUrl += (checkoutUrl.includes('?') ? '&' : '?') + 'type=event&reg_id=' + invoiceId;
            } else if (invoiceType === 'course') {
                checkoutUrl += (checkoutUrl.includes('?') ? '&' : '?') + 'type=course&purchase_id=' + invoiceId;
            }

            // Copy to clipboard
            if (navigator.clipboard && window.isSecureContext) {
                // Modern API
                navigator.clipboard.writeText(checkoutUrl).then(function() {
                    self.showCopySuccess(btn);
                }).catch(function() {
                    self.fallbackCopyToClipboard(checkoutUrl, btn);
                });
            } else {
                // Fallback for older browsers
                self.fallbackCopyToClipboard(checkoutUrl, btn);
            }
        },

        /**
         * Mostra feedback visual de sucesso no botão de copiar
         */
        showCopySuccess: function(btn) {
            const self = this;

            // Store original icon
            const originalIcon = btn.html();

            // Change to checkmark icon with success color
            btn.addClass('eau-copy-success');
            btn.html('<i data-lucide="check"></i>');

            // Re-initialize Lucide icons
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }

            // Show toast notification
            self.showSuccess(eauPaymentsManagement.i18n.linkCopied);

            // Revert after 2 seconds
            setTimeout(function() {
                btn.removeClass('eau-copy-success');
                btn.html(originalIcon);

                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
            }, 2000);
        },

        /**
         * Fallback para copiar texto em navegadores mais antigos
         */
        fallbackCopyToClipboard: function(text, btn) {
            const self = this;
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-999999px';
            textArea.style.top = '-999999px';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();

            try {
                document.execCommand('copy');
                self.showCopySuccess(btn);
            } catch (err) {
                self.showError(eauPaymentsManagement.i18n.copyLinkError);
            }

            document.body.removeChild(textArea);
        },

        /**
         * Exibe mensagem de erro
         */
        showError: function(message) {
            if (typeof EauNotifications !== 'undefined') {
                EauNotifications.error(message);
            } else {
                console.error(message);
            }
        },

        /**
         * Exibe mensagem de sucesso
         */
        showSuccess: function(message) {
            if (typeof EauNotifications !== 'undefined') {
                EauNotifications.success(message);
            } else {
                console.log(message);
            }
        },

        // ========== Media Upload Helper Functions ==========

        /**
         * Upload file to server
         */
        uploadFile: function(wrapper, file) {
            const self = this;
            const maxSize = parseInt(wrapper.data('max-file-size')) || 10485760; // 10MB
            const allowedExtensions = wrapper.data('allowed-extensions') || '';

            // Validate file size
            if (file.size > maxSize) {
                this.showError('File is too large. Maximum size is ' + this.formatFileSize(maxSize));
                return;
            }

            // Validate extension
            if (allowedExtensions) {
                const allowed = allowedExtensions.toLowerCase().split(',');
                const ext = file.name.split('.').pop().toLowerCase();

                if (!allowed.includes(ext)) {
                    this.showError('File type not allowed. Allowed: ' + allowed.map(e => e.toUpperCase()).join(', '));
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
            formData.append('nonce', eauPaymentsManagement.nonce);
            formData.append('file', file);
            formData.append('max_size', maxSize);
            formData.append('allowed_extensions', allowedExtensions);

            // Upload via AJAX
            $.ajax({
                url: eauPaymentsManagement.ajaxUrl,
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
                        self.showSuccess('File uploaded successfully');
                    } else {
                        self.showError(response.data.message || 'Upload failed');
                    }
                },
                error: function() {
                    self.showError('Upload failed. Please try again.');
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
                url: eauPaymentsManagement.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_get_user_files',
                    nonce: eauPaymentsManagement.nonce,
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

                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
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

        // ========== CSV Import Methods ==========

        /**
         * Import state
         */
        importKey: null,
        importFile: null,

        /**
         * Open import modal
         */
        openImportModal: function() {
            this.resetImport();
            $('#eau-import-modal-overlay').css('display', 'flex').hide().fadeIn(200);
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        },

        /**
         * Close import modal
         */
        closeImportModal: function() {
            $('#eau-import-modal-overlay').fadeOut(200);
            this.resetImport();
        },

        /**
         * Reset import state
         */
        resetImport: function() {
            this.importKey = null;
            this.importFile = null;

            // Reset file input
            $('#eau-import-file-input').val('');

            // Show upload step, hide others
            $('#eau-import-step-upload').show();
            $('#eau-import-step-preview').hide();
            $('#eau-import-step-progress').hide();
            $('#eau-import-step-result').hide();

            // Show/hide buttons
            $('#eau-import-cancel-btn').show();
            $('#eau-import-preview-btn').hide();
            $('#eau-import-start-btn').hide();
            $('#eau-import-done-btn').hide();

            // Reset dropzone and file info
            $('#eau-import-dropzone').show();
            $('#eau-import-file-info').hide();

            // Reset progress
            $('#eau-import-progress-fill').css('width', '0%');

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        },

        /**
         * Handle import file selection
         */
        handleImportFile: function(file) {
            const self = this;

            // Validate file type
            if (!file.name.toLowerCase().endsWith('.csv')) {
                EauNotifications.error('Invalid File', 'Please select a CSV file');
                return;
            }

            // Show file info
            $('#eau-import-file-name').text(file.name);
            $('#eau-import-file-size').text(this.formatFileSize(file.size));
            $('#eau-import-dropzone').hide();
            $('#eau-import-file-info').show();

            // Store file reference
            this.importFile = file;

            // Upload file
            const formData = new FormData();
            formData.append('action', 'eau_upload_import_csv');
            formData.append('nonce', eauPaymentsManagement.nonce);
            formData.append('csv_file', file);

            $.ajax({
                url: eauPaymentsManagement.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        self.importKey = response.data.import_key;
                        $('#eau-import-preview-btn').show();
                        EauNotifications.success('File Uploaded', 'Click Preview to see import details');
                    } else {
                        EauNotifications.error('Upload Failed', response.data.message);
                        self.resetImport();
                    }
                },
                error: function() {
                    EauNotifications.error('Upload Failed', 'Network error, please try again');
                    self.resetImport();
                }
            });

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        },

        /**
         * Preview import data
         */
        previewImport: function() {
            const self = this;

            if (!this.importKey) {
                EauNotifications.error('Error', 'Please upload a file first');
                return;
            }

            // Show loading state
            $('#eau-import-preview-btn').prop('disabled', true).html('<i data-lucide="loader-2" class="eau-spin"></i> Loading...');

            $.ajax({
                url: eauPaymentsManagement.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_preview_import_csv',
                    nonce: eauPaymentsManagement.nonce,
                    import_key: this.importKey
                },
                success: function(response) {
                    if (response.success) {
                        self.showPreview(response.data);
                    } else {
                        EauNotifications.error('Preview Failed', response.data.message);
                    }
                },
                error: function() {
                    EauNotifications.error('Preview Failed', 'Network error, please try again');
                },
                complete: function() {
                    $('#eau-import-preview-btn').prop('disabled', false).html('<i data-lucide="eye"></i> Preview');
                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                }
            });
        },

        /**
         * Show preview data
         */
        showPreview: function(data) {
            // Update stats
            $('#eau-import-total-rows').text(data.total_rows);
            $('#eau-import-total-orders').text(data.total_orders);
            $('#eau-import-duplicates').text(data.duplicates);

            // Build preview table
            const tbody = $('#eau-import-preview-body');
            tbody.empty();

            data.preview.forEach(function(order) {
                const statusBadge = order.is_duplicate
                    ? '<span class="eau-badge eau-badge-warning">Duplicate</span>'
                    : '<span class="eau-badge eau-badge-success">New</span>';

                const userBadge = order.user_found
                    ? '<span class="eau-badge eau-badge-success">Found</span>'
                    : '<span class="eau-badge eau-badge-warning">Not Found</span>';

                // Get first item description or summary
                const description = order.items && order.items.length > 0
                    ? order.items[0].description
                    : '-';

                const row = `
                    <tr>
                        <td>${statusBadge}</td>
                        <td>${order.order_no || '-'}</td>
                        <td>
                            <div>${order.full_name || order.first_name + ' ' + order.last_name || '-'}</div>
                            <small style="color: #6b7280;">${order.email || '-'}</small>
                        </td>
                        <td>${order.date || '-'}</td>
                        <td title="${description}">${description.substring(0, 40)}${description.length > 40 ? '...' : ''}</td>
                        <td>$${parseFloat(order.total || 0).toFixed(2)}</td>
                        <td>${userBadge}</td>
                    </tr>
                `;
                tbody.append(row);
            });

            // Show preview step
            $('#eau-import-step-upload').hide();
            $('#eau-import-step-preview').show();

            // Update buttons
            $('#eau-import-preview-btn').hide();
            $('#eau-import-start-btn').show();

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        },

        /**
         * Execute import
         */
        executeImport: function() {
            const self = this;

            if (!this.importKey) {
                EauNotifications.error('Error', 'Import key not found');
                return;
            }

            // Show progress step
            $('#eau-import-step-preview').hide();
            $('#eau-import-step-progress').show();
            $('#eau-import-start-btn').hide();
            $('#eau-import-cancel-btn').hide();

            // Animate progress bar
            let progress = 0;
            const progressInterval = setInterval(function() {
                progress += Math.random() * 10;
                if (progress > 90) progress = 90;
                $('#eau-import-progress-fill').css('width', progress + '%');
            }, 200);

            $.ajax({
                url: eauPaymentsManagement.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_execute_import_csv',
                    nonce: eauPaymentsManagement.nonce,
                    import_key: this.importKey
                },
                success: function(response) {
                    clearInterval(progressInterval);
                    $('#eau-import-progress-fill').css('width', '100%');

                    setTimeout(function() {
                        if (response.success) {
                            self.showImportResult(response.data);
                        } else {
                            EauNotifications.error('Import Failed', response.data.message);
                            self.resetImport();
                        }
                    }, 500);
                },
                error: function() {
                    clearInterval(progressInterval);
                    EauNotifications.error('Import Failed', 'Network error, please try again');
                    self.resetImport();
                }
            });

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        },

        /**
         * Show import result
         */
        showImportResult: function(data) {
            // Update result stats
            $('#eau-import-result-imported').text(data.imported);
            $('#eau-import-result-skipped').text(data.duplicates_skipped);
            $('#eau-import-result-errors').text(data.errors.length);

            // Show errors if any
            if (data.errors && data.errors.length > 0) {
                const errorsList = $('#eau-import-errors-ul');
                errorsList.empty();
                data.errors.forEach(function(error) {
                    errorsList.append(`<li>Order #${error.order_no}: ${error.reason}</li>`);
                });
                $('#eau-import-result-errors-list').show();
            } else {
                $('#eau-import-result-errors-list').hide();
            }

            // Show result step
            $('#eau-import-step-progress').hide();
            $('#eau-import-step-result').show();
            $('#eau-import-done-btn').show();

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }

            // Show success notification
            EauNotifications.success(
                'Import Complete',
                `${data.imported} payments imported, ${data.duplicates_skipped} duplicates skipped`
            );
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        EauPaymentsController.init();
    });

})(jQuery);
