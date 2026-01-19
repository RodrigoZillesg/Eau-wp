/**
 * EAU System - Institutions Management JS
 * Versão: 1.0.0
 */

(function($) {
    'use strict';

    /**
     * Institutions Management Controller
     */
    const EauInstitutionsManagement = {

        // State
        currentPage: 1,
        perPage: 20,
        searchTerm: '',
        filters: {},
        selectedIds: [],
        orderBy: 'ins_member_company_name', // Campo de ordenação
        order: 'ASC', // Direção: ASC ou DESC

        /**
         * Inicializa
         */
        init: function() {
            this.bindEvents();
            this.loadInstitutions();
        },

        /**
         * Vincula eventos
         */
        bindEvents: function() {
            const self = this;

            // Filters toggle
            $('#eau-filters-toggle').on('click', this.toggleFilters.bind(this));

            // Search
            $('#eau-institutions-search').on('input', this.debounce(function(e) {
                self.searchTerm = $(e.target).val();
                self.currentPage = 1;
                self.loadInstitutions();
            }, 300));

            // Export CSV
            $('#eau-export-institutions-csv').on('click', this.handleExportCSV.bind(this));

            // Add Institution
            $('#eau-add-institution').on('click', this.handleAddInstitution.bind(this));

            // Select All (header)
            $(document).on('change', '#institutions-table-select-all', function() {
                const isChecked = $(this).is(':checked');
                $('.eau-row-checkbox').prop('checked', isChecked);
                self.updateSelectedIds();
            });

            // Row checkbox
            $(document).on('change', '.eau-row-checkbox', function() {
                self.updateSelectedIds();
            });

            // Table Actions
            $(document).on('click', '.eau-action-view', function(e) {
                e.preventDefault();
                const institutionId = $(this).data('id');
                self.viewInstitution(institutionId);
            });

            $(document).on('click', '.eau-action-edit', function(e) {
                e.preventDefault();
                const institutionId = $(this).data('id');
                self.editInstitution(institutionId);
            });

            $(document).on('click', '.eau-action-delete', function(e) {
                e.preventDefault();
                const institutionId = $(this).data('id');
                self.deleteInstitution(institutionId);
            });

            // Table Sorting
            $(document).on('click', '.eau-sortable', function() {
                const columnKey = $(this).data('key');
                self.handleSort(columnKey);
            });

            // Filters
            $('.eau-filters-apply').on('click', this.handleApplyFilters.bind(this));
            $('.eau-filters-clear').on('click', this.handleClearFilters.bind(this));

            // Bulk Delete (apenas para super admin)
            if (eauInstitutionsData.isSuperAdmin) {
                $('#eau-bulk-delete-institutions').on('click', this.handleBulkDelete.bind(this));
            }
        },

        /**
         * Load institutions via AJAX
         */
        loadInstitutions: function() {
            const self = this;

            // Show loading
            this.showLoading();

            $.ajax({
                url: eauInstitutionsData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_get_institutions',
                    nonce: eauInstitutionsData.nonce,
                    page: this.currentPage,
                    per_page: this.perPage,
                    search: this.searchTerm,
                    orderby: this.orderBy,
                    order: this.order,
                    ...this.filters
                },
                success: function(response) {
                    if (response.success) {
                        self.renderInstitutions(response.data);
                        self.updateCounter(response.data.total);
                        self.renderPagination(response.data);
                    } else {
                        self.showError('Failed to load institutions');
                    }
                },
                error: function() {
                    self.showError('Network error. Please try again.');
                },
                complete: function() {
                    self.hideLoading();
                    // Re-initialize Lucide icons
                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                }
            });
        },

        /**
         * Render institutions in table
         */
        renderInstitutions: function(data) {
            const tbody = $('#institutions-table-tbody');

            if (!data.rows || data.rows.length === 0) {
                tbody.html(this.getEmptyState());
                return;
            }

            let html = '';
            data.rows.forEach(function(row) {
                html += `
                    <tr class="eau-table-row" data-id="${row._id}">
                        <td class="eau-table-td-checkbox">
                            <input type="checkbox" class="eau-row-checkbox" value="${row._id}">
                        </td>
                        <td class="eau-table-td" data-label="INSTITUTION">${row.institution}</td>
                        <td class="eau-table-td" data-label="CAMPUS">${row.campus || ''}</td>
                        <td class="eau-table-td" data-label="CODE">${row.code}</td>
                        <td class="eau-table-td" data-label="TYPE">${row.type}</td>
                        <td class="eau-table-td" data-label="CONTACT">${row.contact}</td>
                        <td class="eau-table-td" data-label="MEMBERS">${row.members}</td>
                        <td class="eau-table-td" data-label="START DATE">${row.start_date}</td>
                        <td class="eau-table-td" data-label="EXPIRE DATE">${row.expire_date}</td>
                        <td class="eau-table-td" data-label="STATUS">${row.status}</td>
                        <td class="eau-table-td eau-table-td-actions">
                            <div class="eau-table-actions">
                                <button class="eau-action-btn eau-action-view" data-action="view" data-id="${row._id}" title="View">
                                    <i data-lucide="eye"></i>
                                </button>
                                <button class="eau-action-btn eau-action-edit" data-action="edit" data-id="${row._id}" title="Edit">
                                    <i data-lucide="pencil"></i>
                                </button>
                                <button class="eau-action-btn eau-action-delete" data-action="delete" data-id="${row._id}" title="Delete">
                                    <i data-lucide="trash-2"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            });

            tbody.html(html);

            // Re-initialize Lucide icons after rendering
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        },

        /**
         * Get empty state HTML
         */
        getEmptyState: function() {
            return `
                <tr class="eau-table-empty">
                    <td colspan="11" style="text-align: center; padding: 3rem;">
                        <i data-lucide="inbox" style="width: 3rem; height: 3rem; color: #d1d5db; margin-bottom: 1rem;"></i>
                        <p style="color: #6b7280; margin: 0;">No institutions found</p>
                    </td>
                </tr>
            `;
        },

        /**
         * Update counter
         */
        updateCounter: function(total) {
            $('#institutions-table-count .eau-count-number').text(total);
        },

        /**
         * Handle sort by column
         */
        handleSort: function(columnKey) {
            // Mapeamento de colunas para campos de ordenação
            const columnMap = {
                'institution': 'ins_company_name',
                'campus': 'ins_company_name',
                'code': 'ins_company_id',
                'type': 'ins_type',
                'contact': 'ins_company_email',
                'members': 'members_count',
                'start_date': 'ins_member_start_date',
                'expire_date': 'ins_member_expire_date',
                'status': 'ins_status'
            };

            const sortField = columnMap[columnKey] || columnKey;

            // Se já está ordenando por este campo, inverte a direção
            if (this.orderBy === sortField) {
                this.order = this.order === 'ASC' ? 'DESC' : 'ASC';
            } else {
                // Novo campo, começa com ASC
                this.orderBy = sortField;
                this.order = 'ASC';
            }

            // Atualiza ícones
            this.updateSortIcons(columnKey);

            // Volta para primeira página
            this.currentPage = 1;

            // Recarrega instituições
            this.loadInstitutions();
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
         * Update selected IDs
         */
        updateSelectedIds: function() {
            const self = this;
            this.selectedIds = [];

            $('.eau-row-checkbox:checked').each(function() {
                self.selectedIds.push($(this).val());
            });

            // Mostrar/ocultar botão de deleção em massa (apenas para super admin)
            if (eauInstitutionsData.isSuperAdmin) {
                if (this.selectedIds.length > 0) {
                    $('#eau-bulk-delete-institutions').addClass('eau-visible');
                } else {
                    $('#eau-bulk-delete-institutions').removeClass('eau-visible');
                }
            }
        },

        /**
         * Show loading overlay
         */
        showLoading: function() {
            $('#institutions-table-loading').show();
        },

        /**
         * Hide loading overlay
         */
        hideLoading: function() {
            $('#institutions-table-loading').hide();
        },

        /**
         * Show error
         */
        showError: function(message) {
            if (typeof EauNotifications !== 'undefined') {
                EauNotifications.error('Error', message);
            } else {
                alert(message);
            }
        },

        /**
         * Toggle filtros
         */
        toggleFilters: function(e) {
            e.preventDefault();
            $('#eau-filters-panel').slideToggle(200);
        },

        /**
         * View Institution
         * Opens the dedicated Institution Single Page in a new tab
         */
        viewInstitution: function(institutionId) {
            // Open the institution single page in a new tab
            window.open('/institution/' + institutionId + '/', '_blank');
        },

        /**
         * Edit Institution
         */
        editInstitution: function(institutionId) {
            const self = this;

            // Abre modal e carrega dados
            this.openModal('eau-modal-edit');
            this.loadInstitutionDetails(institutionId, 'edit');
        },

        /**
         * Delete Institution
         */
        deleteInstitution: function(institutionId) {
            const self = this;

            // Show confirm modal
            EauNotifications.confirm({
                title: 'Delete Institution?',
                message: 'Are you sure you want to delete this institution? This action cannot be undone.',
                type: 'danger',
                confirmText: 'Delete',
                cancelText: 'Cancel',
                onConfirm: function() {
                    $.ajax({
                        url: eauInstitutionsData.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'eau_delete_institution',
                            nonce: eauInstitutionsData.nonce,
                            institution_id: institutionId
                        },
                        success: function(response) {
                            if (response.success) {
                                EauNotifications.success('Success', 'Institution deleted successfully');
                                self.loadInstitutions(); // Reload table
                            } else {
                                EauNotifications.error('Error', response.data.message || 'Failed to delete institution');
                            }
                        },
                        error: function() {
                            EauNotifications.error('Network Error', 'Please try again.');
                        }
                    });
                }
            });
        },

        /**
         * Handle export CSV
         */
        handleExportCSV: function(e) {
            e.preventDefault();

            const exportType = this.selectedIds.length > 0 ? 'selected' : 'all';

            // Create form and submit
            const form = $('<form>', {
                method: 'POST',
                action: eauInstitutionsData.ajaxUrl
            });

            form.append($('<input>', { type: 'hidden', name: 'action', value: 'eau_export_institutions_csv' }));
            form.append($('<input>', { type: 'hidden', name: 'nonce', value: eauInstitutionsData.nonce }));
            form.append($('<input>', { type: 'hidden', name: 'export_type', value: exportType }));

            if (exportType === 'selected') {
                this.selectedIds.forEach(function(id) {
                    form.append($('<input>', { type: 'hidden', name: 'selected_ids[]', value: id }));
                });
            }

            form.appendTo('body').submit().remove();
        },

        /**
         * Handle add institution
         */
        handleAddInstitution: function(e) {
            e.preventDefault();

            // Abre modal vazio para adicionar
            this.openModal('eau-modal-add');
            this.loadAddInstitutionForm();
        },

        /**
         * Render pagination
         */
        renderPagination: function(data) {
            const self = this;
            const totalPages = data.total_pages || 1;

            if (totalPages <= 1) {
                $('#eau-pagination-container').html('');
                return;
            }

            const startItem = ((data.page - 1) * data.per_page) + 1;
            const endItem = Math.min(data.page * data.per_page, data.total);

            const html = this.buildPaginationHTML(data.page, totalPages, startItem, endItem, data.total);
            $('#eau-pagination-container').html(html);

            // Bind pagination click events
            $(document).off('click', '.eau-pagination-btn').on('click', '.eau-pagination-btn', function(e) {
                e.preventDefault();
                if ($(this).is(':disabled') || $(this).hasClass('eau-pagination-active')) {
                    return;
                }

                const page = parseInt($(this).data('page'));
                self.currentPage = page;
                self.loadInstitutions();

                // Scroll to top of table
                $('html, body').animate({
                    scrollTop: $('.eau-institutions-table-wrapper').offset().top - 100
                }, 300);
            });

            // Re-initialize Lucide icons for pagination
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        },

        /**
         * Build pagination HTML
         */
        buildPaginationHTML: function(currentPage, totalPages, startItem, endItem, total) {
            const pagesToShow = this.getPagesToShow(currentPage, totalPages);

            let html = '<div class="eau-pagination-wrapper" id="institutions-pagination">';

            // Info
            html += '<div class="eau-pagination-info">';
            html += `Showing ${startItem.toLocaleString()} to ${endItem.toLocaleString()} of ${total.toLocaleString()} institutions`;
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
            const maxShown = 5;
            const pages = [];

            if (totalPages <= maxShown + 2) {
                for (let i = 1; i <= totalPages; i++) {
                    pages.push(i);
                }
                return pages;
            }

            pages.push(1);

            let start = Math.max(2, currentPage - Math.floor(maxShown / 2));
            let end = Math.min(totalPages - 1, currentPage + Math.floor(maxShown / 2));

            if (currentPage <= Math.ceil(maxShown / 2) + 1) {
                end = Math.min(totalPages - 1, maxShown);
            }

            if (currentPage >= totalPages - Math.ceil(maxShown / 2)) {
                start = Math.max(2, totalPages - maxShown);
            }

            if (start > 2) {
                pages.push('...');
            }

            for (let i = start; i <= end; i++) {
                pages.push(i);
            }

            if (end < totalPages - 1) {
                pages.push('...');
            }

            if (totalPages > 1) {
                pages.push(totalPages);
            }

            return pages;
        },

        /**
         * Handle apply filters
         */
        handleApplyFilters: function(e) {
            if (e) e.preventDefault();

            const self = this;
            this.filters = {};

            // Coleta valores de todos os filtros
            $('#eau-filters-panel [data-filter]').each(function() {
                const key = $(this).data('filter');
                const value = $(this).val();

                if (value && value !== '') {
                    self.filters[key] = value;
                }
            });

            // Mostra skeleton durante filtragem
            this.showLoading();

            // Reset para primeira página e recarrega
            this.currentPage = 1;
            this.loadInstitutions();

            // Atualiza badge de filtros ativos (opcional)
            this.updateFiltersCount();
        },

        /**
         * Handle clear filters
         */
        handleClearFilters: function(e) {
            e.preventDefault();

            // Limpa todos os inputs de filtro
            $('#eau-filters-panel [data-filter]').val('');

            // Limpa o objeto de filtros
            this.filters = {};

            // Mostra skeleton durante limpeza de filtros
            this.showLoading();

            // Reset para primeira página e recarrega
            this.currentPage = 1;
            this.loadInstitutions();

            // Atualiza badge de filtros ativos
            this.updateFiltersCount();
        },

        /**
         * Update filters count badge
         */
        updateFiltersCount: function() {
            const count = Object.keys(this.filters).length;
            const $toggle = $('#eau-filters-toggle');

            // Remove badge anterior
            $toggle.find('.eau-filters-badge').remove();

            // Adiciona badge se houver filtros ativos
            if (count > 0) {
                $toggle.append(`<span class="eau-filters-badge">${count}</span>`);
            }
        },

        /**
         * Open Modal
         */
        openModal: function(modalId) {
            const $overlay = $('#' + modalId + '-overlay');
            $overlay.css('display', 'flex').hide().fadeIn(200);

            // Bind close events
            const self = this;
            $overlay.find('[data-modal-action="close"]').off('click').on('click', function() {
                self.closeModal(modalId);
            });

            // Close on overlay click
            $overlay.off('click').on('click', function(e) {
                if ($(e.target).hasClass('eau-modal-overlay')) {
                    self.closeModal(modalId);
                }
            });

            // Bind action buttons
            $overlay.find('[data-modal-action="save"]').off('click').on('click', function() {
                self.saveInstitution(modalId);
            });

            $overlay.find('[data-modal-action="create"]').off('click').on('click', function() {
                self.createInstitution();
            });

            // Re-initialize Lucide icons
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        },

        /**
         * Close Modal
         */
        closeModal: function(modalId) {
            $('#' + modalId + '-overlay').fadeOut(200);
        },

        /**
         * Load Institution Details
         */
        loadInstitutionDetails: function(institutionId, mode) {
            const self = this;
            const modalId = mode === 'view' ? 'eau-modal-view' : 'eau-modal-edit';

            // Show skeleton loading
            $('#' + modalId + '-body').html(`
                <div class="eau-form-grid">
                    <div class="eau-form-field">
                        <div class="eau-skeleton eau-skeleton-text" style="width: 30%; margin-bottom: 0.5rem;">
                            <div class="eau-skeleton-shimmer"></div>
                        </div>
                        <div class="eau-skeleton eau-skeleton-row">
                            <div class="eau-skeleton-shimmer"></div>
                        </div>
                    </div>
                    <div class="eau-form-field">
                        <div class="eau-skeleton eau-skeleton-text" style="width: 30%; margin-bottom: 0.5rem;">
                            <div class="eau-skeleton-shimmer"></div>
                        </div>
                        <div class="eau-skeleton eau-skeleton-row">
                            <div class="eau-skeleton-shimmer"></div>
                        </div>
                    </div>
                    <div class="eau-form-field">
                        <div class="eau-skeleton eau-skeleton-text" style="width: 30%; margin-bottom: 0.5rem;">
                            <div class="eau-skeleton-shimmer"></div>
                        </div>
                        <div class="eau-skeleton eau-skeleton-row">
                            <div class="eau-skeleton-shimmer"></div>
                        </div>
                    </div>
                    <div class="eau-form-field">
                        <div class="eau-skeleton eau-skeleton-text" style="width: 30%; margin-bottom: 0.5rem;">
                            <div class="eau-skeleton-shimmer"></div>
                        </div>
                        <div class="eau-skeleton eau-skeleton-row">
                            <div class="eau-skeleton-shimmer"></div>
                        </div>
                    </div>
                </div>
            `);

            $.ajax({
                url: eauInstitutionsData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_get_institution_details',
                    nonce: eauInstitutionsData.nonce,
                    institution_id: institutionId
                },
                success: function(response) {
                    if (response.success) {
                        self.renderInstitutionForm(modalId, response.data, mode);
                    } else {
                        alert('Failed to load institution details');
                        self.closeModal(modalId);
                    }
                },
                error: function() {
                    alert('Network error. Please try again.');
                    self.closeModal(modalId);
                }
            });
        },

        /**
         * Load Add Institution Form
         */
        loadAddInstitutionForm: function() {
            const emptyData = {
                _ID: '',
                ins_company_name: '',
                ins_company_id: '',
                ins_type: '',
                ins_company_email: '',
                ins_company_company_phone: '',
                ins_company_company_address_line_1: '',
                ins_company_company_suburb: '',
                ins_company_company_state: '',
                ins_company_company_postcode: '',
                ins_company_company_country: '',
                ins_status: 'active'
            };

            this.renderInstitutionForm('eau-modal-add', emptyData, 'add');
        },

        /**
         * Render Institution Form
         */
        renderInstitutionForm: function(modalId, data, mode) {
            const self = this;
            const isView = mode === 'view';
            const isAdd = mode === 'add';
            const readonly = isView ? 'readonly' : '';
            const requiredAttr = !isView ? 'required' : '';

            let html = '<form class="eau-modal-form" id="eau-institution-form">';
            html += '<div class="eau-form-grid">';

            // Hidden institution ID
            if (data._ID) {
                html += `<input type="hidden" name="institution_id" value="${data._ID}">`;
            }

            // Logo - View mode (display only) - show if logo exists
            if (isView) {
                const logoUrl = data.ins_company_logo_url || '';
                if (logoUrl && logoUrl.length > 0) {
                    html += `
                        <div class="eau-form-field eau-form-field-span-2">
                            <label class="eau-form-label">Institution Logo</label>
                            <div class="eau-institution-logo-preview">
                                <img src="${logoUrl}" alt="Institution Logo" style="max-width: 200px; max-height: 100px; border-radius: 8px; border: 1px solid #e5e7eb;">
                            </div>
                        </div>
                    `;
                }
            }

            // Logo - Edit mode (upload component)
            if (!isView) {
                const logoUrl = data.ins_company_logo_url || '';
                const logoId = data.ins_company_logo || '';
                const logoFilename = logoUrl ? logoUrl.split('/').pop() : '';
                const hasLogo = logoUrl && logoUrl.length > 0;

                html += `
                    <div class="eau-form-field eau-form-field-span-2">
                        <label class="eau-form-label">Institution Logo</label>
                        <div class="eau-media-upload-wrapper" id="ins-logo-upload-wrapper"
                            data-type="media"
                            data-allowed-types="image/*"
                            data-allowed-extensions="jpg,jpeg,png,gif,webp"
                            data-max-file-size="5242880">

                            <!-- Upload Panel -->
                            <div class="eau-media-upload-panel eau-media-upload-file-panel active">
                                <div class="eau-media-upload-dropzone" id="ins-logo-dropzone">
                                    <input type="file" class="eau-media-upload-file-input" id="ins-logo-file-input" accept="image/*" style="display: none;">
                                    <div class="eau-media-upload-dropzone-content">
                                        <i data-lucide="upload-cloud"></i>
                                        <span class="eau-media-upload-dropzone-text">
                                            Drag & drop or <button type="button" class="eau-media-upload-browse-btn">Browse</button>
                                        </span>
                                        <span class="eau-media-upload-dropzone-hint">
                                            Allowed: JPG, PNG, GIF, WEBP<br>Max size: 5 MB
                                        </span>
                                    </div>
                                    <!-- Upload progress -->
                                    <div class="eau-media-upload-progress" style="display: none;">
                                        <div class="eau-media-upload-progress-bar">
                                            <div class="eau-media-upload-progress-fill"></div>
                                        </div>
                                        <span class="eau-media-upload-progress-text">Uploading... 0%</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Preview -->
                            <div class="eau-media-upload-preview" id="ins-logo-preview" style="${hasLogo ? '' : 'display:none;'}">
                                <div class="eau-media-upload-preview-content">
                                    <div class="eau-media-upload-preview-thumbnail" id="ins-logo-thumbnail">
                                        <img src="${logoUrl}" alt="Preview" class="eau-media-upload-preview-image" style="${hasLogo ? '' : 'display: none;'}">
                                        <i data-lucide="image" class="eau-media-upload-preview-icon" style="${hasLogo ? 'display: none;' : ''}"></i>
                                    </div>
                                    <div class="eau-media-upload-preview-info">
                                        <span class="eau-media-upload-preview-name" id="ins-logo-preview-name">${logoFilename}</span>
                                    </div>
                                    <div class="eau-media-upload-preview-actions">
                                        <a href="${logoUrl}" target="_blank" class="eau-media-upload-preview-link" id="ins-logo-preview-link" title="Open file" ${hasLogo ? '' : 'style="display:none;"'}>
                                            <i data-lucide="external-link"></i>
                                        </a>
                                        <button type="button" class="eau-media-upload-remove" id="ins-logo-remove" title="Remove file">
                                            <i data-lucide="x"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Hidden inputs -->
                            <input type="hidden" id="ins-logo-value" name="ins_company_logo" value="${logoId}" class="eau-media-upload-value">
                        </div>
                    </div>
                `;
            }

            // Institution Name
            html += `
                <div class="eau-form-field">
                    <label class="eau-form-label">Institution Name ${!isView ? '<span class="eau-form-required">*</span>' : ''}</label>
                    <input type="text" class="eau-form-input" name="ins_company_name" value="${data.ins_company_name || data.post_title || ''}" ${readonly} ${requiredAttr}>
                </div>
            `;

            // Code (sempre readonly)
            html += `
                <div class="eau-form-field">
                    <label class="eau-form-label">Code</label>
                    <input type="text" class="eau-form-input" name="ins_company_id" value="${data.ins_company_id || ''}" readonly>
                </div>
            `;

            // Institution Type
            html += `
                <div class="eau-form-field">
                    <label class="eau-form-label">Institution Type</label>
            `;
            if (!isView) {
                html += `
                    <select class="eau-form-select" name="ins_type">
                        <option value="">Select Type</option>
                        <option value="College" ${data.ins_type === 'College' ? 'selected' : ''}>College</option>
                        <option value="Corporate affiliate" ${data.ins_type === 'Corporate affiliate' ? 'selected' : ''}>Corporate Affiliate</option>
                    </select>
                `;
            } else {
                html += `<input type="text" class="eau-form-input" value="${data.ins_type || '-'}" readonly>`;
            }
            html += `</div>`;

            // Email
            html += `
                <div class="eau-form-field">
                    <label class="eau-form-label">Email</label>
                    <input type="email" class="eau-form-input" name="ins_company_email" value="${data.ins_company_email || ''}" ${readonly}>
                </div>
            `;

            // Phone
            html += `
                <div class="eau-form-field">
                    <label class="eau-form-label">Phone</label>
                    <input type="tel" class="eau-form-input" name="ins_company_company_phone" value="${data.ins_company_company_phone || ''}" ${readonly}>
                </div>
            `;

            // Address
            html += `
                <div class="eau-form-field eau-form-field-span-2">
                    <label class="eau-form-label">Address</label>
                    <input type="text" class="eau-form-input" name="ins_company_company_address_line_1" value="${data.ins_company_company_address_line_1 || ''}" ${readonly}>
                </div>
            `;

            // City
            html += `
                <div class="eau-form-field">
                    <label class="eau-form-label">City</label>
                    <input type="text" class="eau-form-input" name="ins_company_company_suburb" value="${data.ins_company_company_suburb || ''}" ${readonly}>
                </div>
            `;

            // State
            html += `
                <div class="eau-form-field">
                    <label class="eau-form-label">State</label>
                    <input type="text" class="eau-form-input" name="ins_company_company_state" value="${data.ins_company_company_state || ''}" ${readonly}>
                </div>
            `;

            // ZIP
            html += `
                <div class="eau-form-field">
                    <label class="eau-form-label">ZIP Code</label>
                    <input type="text" class="eau-form-input" name="ins_company_company_postcode" value="${data.ins_company_company_postcode || ''}" ${readonly}>
                </div>
            `;

            // Country
            html += `
                <div class="eau-form-field">
                    <label class="eau-form-label">Country</label>
                    <input type="text" class="eau-form-input" name="ins_company_company_country" value="${data.ins_company_company_country || ''}" ${readonly}>
                </div>
            `;

            // Status
            html += `
                <div class="eau-form-field">
                    <label class="eau-form-label">Status</label>
            `;
            if (!isView) {
                html += `
                    <select class="eau-form-select" name="ins_status" ${requiredAttr}>
                        <option value="active" ${data.ins_status === 'active' || !data.ins_status ? 'selected' : ''}>Active</option>
                        <option value="inactive" ${data.ins_status === 'inactive' ? 'selected' : ''}>Inactive</option>
                    </select>
                `;
            } else {
                html += `<input type="text" class="eau-form-input" value="${data.ins_status || 'active'}" readonly>`;
            }
            html += `</div>`;

            // Members Count (view only)
            if (mode === 'view' || mode === 'edit') {
                html += `
                    <div class="eau-form-field">
                        <label class="eau-form-label">Total Members</label>
                        <input type="text" class="eau-form-input" value="${data.members_count || 0}" readonly>
                    </div>
                `;
            }

            html += '</div></form>';

            // Add members list for View mode
            if (isView && data.members && data.members.length > 0) {
                html += this.renderMembersList(data.members, data._ID);
            }

            $('#' + modalId + '-body').html(html);

            // Re-initialize Lucide icons
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }

            // Bind media upload events for edit/add modes
            if (!isView) {
                this.bindLogoUploadEvents();
            }
        },

        /**
         * Render members list for View Institution modal
         */
        renderMembersList: function(members, institutionId) {
            const self = this;

            let html = `
                <div class="eau-members-section">
                    <div class="eau-members-section-header">
                        <h3 class="eau-members-section-title">
                            <i data-lucide="users"></i>
                            Institution Members (${members.length}${members.length >= 50 ? '+' : ''})
                        </h3>
                        <a href="/dashboard/manage-members/?institution=${institutionId}" target="_blank" class="eau-btn eau-btn-sm eau-btn-secondary">
                            <i data-lucide="external-link"></i>
                            View All
                        </a>
                    </div>
                    <div class="eau-members-list">
                        <table class="eau-members-mini-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
            `;

            members.forEach(function(member) {
                const statusClass = member.status === 'active' ? 'eau-status-badge-active' : 'eau-status-badge-inactive';
                const typeLabel = member.type || '-';

                html += `
                    <tr>
                        <td>
                            <a href="/dashboard/manage-members/?edit=${member.id}" target="_blank" class="eau-member-link">
                                ${self.escapeHtml(member.name)}
                            </a>
                        </td>
                        <td>${self.escapeHtml(member.email)}</td>
                        <td>${self.escapeHtml(typeLabel)}</td>
                        <td><span class="eau-status-badge ${statusClass}">${self.escapeHtml(member.status)}</span></td>
                    </tr>
                `;
            });

            html += `
                            </tbody>
                        </table>
                    </div>
                </div>
            `;

            return html;
        },

        /**
         * Escape HTML special characters
         */
        escapeHtml: function(text) {
            if (!text) return '';
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
        },

        /**
         * Bind logo upload events
         */
        bindLogoUploadEvents: function() {
            const self = this;
            const wrapper = $('#ins-logo-upload-wrapper');

            if (!wrapper.length) return;

            const dropzone = wrapper.find('.eau-media-upload-dropzone');
            const fileInput = wrapper.find('.eau-media-upload-file-input');
            const browseBtn = wrapper.find('.eau-media-upload-browse-btn');
            const removeBtn = wrapper.find('.eau-media-upload-remove');

            // Browse button click
            browseBtn.off('click').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                fileInput[0].click(); // Use native click instead of jQuery trigger
            });

            // Dropzone click (outside browse button and file input)
            dropzone.off('click').on('click', function(e) {
                // Prevent triggering on browse button or file input itself
                if ($(e.target).hasClass('eau-media-upload-browse-btn') ||
                    $(e.target).hasClass('eau-media-upload-file-input') ||
                    $(e.target).closest('.eau-media-upload-browse-btn').length) {
                    return;
                }
                e.preventDefault();
                e.stopPropagation();
                fileInput[0].click(); // Use native click instead of jQuery trigger
            });

            // File input change
            fileInput.off('change').on('change', function(e) {
                const files = e.target.files;
                if (files && files.length > 0) {
                    self.uploadLogoFile(wrapper, files[0]);
                }
            });

            // Drag and drop
            dropzone.off('dragover dragleave drop');
            dropzone.on('dragover', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).addClass('eau-media-upload-dragover');
            });

            dropzone.on('dragleave', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('eau-media-upload-dragover');
            });

            dropzone.on('drop', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('eau-media-upload-dragover');

                const files = e.originalEvent.dataTransfer.files;
                if (files && files.length > 0) {
                    self.uploadLogoFile(wrapper, files[0]);
                }
            });

            // Remove button
            removeBtn.off('click').on('click', function(e) {
                e.preventDefault();
                self.clearLogoUpload(wrapper);
            });
        },

        /**
         * Upload logo file
         */
        uploadLogoFile: function(wrapper, file) {
            const self = this;

            // Validate file type
            const allowedExtensions = wrapper.data('allowed-extensions') || 'jpg,jpeg,png,gif,webp';
            const maxSize = wrapper.data('max-file-size') || 5242880;

            const ext = file.name.split('.').pop().toLowerCase();
            const allowedArr = allowedExtensions.split(',').map(e => e.trim().toLowerCase());

            if (!allowedArr.includes(ext)) {
                EauNotifications.error('Invalid File', 'Allowed file types: ' + allowedExtensions.toUpperCase());
                return;
            }

            if (file.size > maxSize) {
                EauNotifications.error('File Too Large', 'Maximum file size is ' + this.formatFileSize(maxSize));
                return;
            }

            // Show progress
            const progressContainer = wrapper.find('.eau-media-upload-progress');
            const progressFill = wrapper.find('.eau-media-upload-progress-fill');
            const progressText = wrapper.find('.eau-media-upload-progress-text');
            const dropzoneContent = wrapper.find('.eau-media-upload-dropzone-content');

            dropzoneContent.hide();
            progressContainer.show();
            progressFill.css('width', '0%');
            progressText.text('Uploading... 0%');

            // Create FormData
            const formData = new FormData();
            formData.append('action', 'eau_upload_institution_logo');
            formData.append('nonce', eauInstitutionsData.nonce);
            formData.append('file', file);
            formData.append('max_size', maxSize);
            formData.append('allowed_extensions', allowedExtensions);

            // Upload via AJAX with progress
            const xhr = new XMLHttpRequest();

            xhr.upload.addEventListener('progress', function(e) {
                if (e.lengthComputable) {
                    const percent = Math.round((e.loaded / e.total) * 100);
                    progressFill.css('width', percent + '%');
                    progressText.text('Uploading... ' + percent + '%');
                }
            });

            xhr.addEventListener('load', function() {
                progressContainer.hide();
                dropzoneContent.show();

                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            self.setLogoValueFromUpload(wrapper, response.data);
                            EauNotifications.success('Uploaded', 'Logo uploaded successfully');
                        } else {
                            EauNotifications.error('Upload Failed', response.data.message || 'Failed to upload logo');
                        }
                    } catch (e) {
                        EauNotifications.error('Upload Failed', 'Invalid server response');
                    }
                } else {
                    EauNotifications.error('Upload Failed', 'Server error: ' + xhr.status);
                }
            });

            xhr.addEventListener('error', function() {
                progressContainer.hide();
                dropzoneContent.show();
                EauNotifications.error('Upload Failed', 'Network error occurred');
            });

            xhr.open('POST', eauInstitutionsData.ajaxUrl);
            xhr.send(formData);
        },

        /**
         * Set logo value from upload response
         */
        setLogoValueFromUpload: function(wrapper, data) {
            // Update hidden input with attachment ID
            wrapper.find('.eau-media-upload-value').val(data.id);

            // Update preview
            const preview = wrapper.find('.eau-media-upload-preview');
            const previewImg = preview.find('.eau-media-upload-preview-image');
            const previewIcon = preview.find('.eau-media-upload-preview-icon');
            const previewName = preview.find('.eau-media-upload-preview-name');
            const previewLink = preview.find('.eau-media-upload-preview-link');

            // Show image preview
            previewImg.attr('src', data.url).show();
            previewIcon.hide();
            previewName.text(data.filename);
            previewLink.attr('href', data.url).show();

            // Show preview, hide dropzone
            preview.show();
            wrapper.find('.eau-media-upload-file-panel').hide();

            // Re-initialize Lucide icons
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        },

        /**
         * Clear logo upload
         */
        clearLogoUpload: function(wrapper) {
            // Clear hidden input
            wrapper.find('.eau-media-upload-value').val('');

            // Clear file input
            wrapper.find('.eau-media-upload-file-input').val('');

            // Hide preview, show dropzone
            wrapper.find('.eau-media-upload-preview').hide();
            wrapper.find('.eau-media-upload-file-panel').show();

            // Reset preview
            const preview = wrapper.find('.eau-media-upload-preview');
            preview.find('.eau-media-upload-preview-image').attr('src', '').hide();
            preview.find('.eau-media-upload-preview-icon').show();
            preview.find('.eau-media-upload-preview-name').text('');
            preview.find('.eau-media-upload-preview-link').attr('href', '#').hide();
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
         * Save Institution (Edit)
         */
        saveInstitution: function(modalId) {
            const self = this;
            const $form = $('#eau-institution-form');

            // Valida form
            if (!$form[0].checkValidity()) {
                $form[0].reportValidity();
                return;
            }

            const formData = $form.serializeArray();
            const fields = {};

            // Converte para objeto
            formData.forEach(function(item) {
                if (item.name !== 'institution_id') {
                    fields[item.name] = item.value;
                }
            });

            const institutionId = $form.find('input[name="institution_id"]').val();

            $.ajax({
                url: eauInstitutionsData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_update_institution',
                    nonce: eauInstitutionsData.nonce,
                    institution_id: institutionId,
                    fields: fields
                },
                success: function(response) {
                    if (response.success) {
                        EauNotifications.success('Saved!', 'Institution updated successfully');
                        self.closeModal(modalId);
                        self.loadInstitutions();
                    } else {
                        EauNotifications.error('Error', response.data.message || 'Failed to update institution');
                    }
                },
                error: function() {
                    EauNotifications.error('Network Error', 'Please try again');
                }
            });
        },

        /**
         * Create Institution (Add)
         */
        createInstitution: function() {
            const self = this;
            const $form = $('#eau-institution-form');

            // Valida form
            if (!$form[0].checkValidity()) {
                $form[0].reportValidity();
                return;
            }

            const formData = $form.serializeArray();
            const dataObj = {};

            // Converte para objeto
            formData.forEach(function(item) {
                dataObj[item.name] = item.value;
            });

            $.ajax({
                url: eauInstitutionsData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_create_institution',
                    nonce: eauInstitutionsData.nonce,
                    ...dataObj
                },
                success: function(response) {
                    if (response.success) {
                        EauNotifications.success('Success!', 'Institution created successfully');
                        self.closeModal('eau-modal-add');
                        self.loadInstitutions();
                    } else {
                        EauNotifications.error('Error', response.data.message || 'Failed to create institution');
                    }
                },
                error: function() {
                    EauNotifications.error('Network Error', 'Please try again');
                }
            });
        },

        /**
         * Handle bulk delete
         */
        handleBulkDelete: function() {
            const self = this;

            if (this.selectedIds.length === 0) {
                EauNotifications.warning('No Selection', 'Please select institutions to delete.');
                return;
            }

            const count = this.selectedIds.length;
            EauNotifications.confirm({
                title: 'Delete Institutions?',
                message: `Are you sure you want to delete ${count} institution(s)? This action cannot be undone.`,
                type: 'danger',
                confirmText: 'Delete',
                cancelText: 'Cancel',
                onConfirm: function() {
                    $.ajax({
                        url: eauInstitutionsData.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'eau_bulk_delete_institutions',
                            nonce: eauInstitutionsData.nonce,
                            ids: self.selectedIds
                        },
                        success: function(response) {
                            if (response.success) {
                                EauNotifications.success('Deleted!', response.data.message);
                                self.selectedIds = [];
                                self.loadInstitutions();
                                $('#eau-bulk-delete-institutions').removeClass('eau-visible');
                            } else {
                                EauNotifications.error('Error', response.data.message || 'Failed to delete institutions');
                            }
                        },
                        error: function() {
                            EauNotifications.error('Network Error', 'Please try again');
                        }
                    });
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
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
    };

    /**
     * Init on document ready
     */
    $(document).ready(function() {
        if ($('.eau-institutions-management-container').length) {
            EauInstitutionsManagement.init();
        }
    });

})(jQuery);
