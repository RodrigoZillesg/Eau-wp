/**
 * EAU System - Members Management JS
 * Versão: 1.9.2
 */

(function($) {
    'use strict';

    /**
     * Members Management Controller
     */
    const EauMembersManagement = {

        // State
        currentPage: 1,
        perPage: 20,
        searchTerm: '',
        filters: {},
        selectedIds: [],
        editableFields: {}, // Campos configurados no settings
        institutions: [], // Lista de instituições para select
        orderBy: 'display_name', // Campo de ordenação
        order: 'ASC', // Direção: ASC ou DESC
        phoneIti: null, // intl-tel-input instance

        /**
         * Inicializa
         */
        init: function() {
            this.loadEditableFields(); // Carrega campos configurados
            this.loadInstitutions(); // Carrega instituições
            this.bindEvents();
            this.checkUrlParams(); // Check for URL parameters BEFORE loading members
            this.loadMembers();
        },

        /**
         * Check URL parameters for direct actions
         */
        checkUrlParams: function() {
            const urlParams = new URLSearchParams(window.location.search);
            const editId = urlParams.get('edit');
            const statusFilter = urlParams.get('status');

            // Apply status filter from URL
            if (statusFilter) {
                // Set the filter value
                this.filters.status = statusFilter;

                // Update the select element
                $('#eau-filter-status').val(statusFilter);

                // Show the filters panel
                $('#eau-filters-panel').addClass('active');

                // Remove the parameter from URL to avoid re-applying on refresh
                const url = new URL(window.location);
                url.searchParams.delete('status');
                window.history.replaceState({}, document.title, url);

                // Reset to first page
                this.currentPage = 1;
            }

            if (editId) {
                // Remove the parameter from URL to avoid reopening on refresh
                const url = new URL(window.location);
                url.searchParams.delete('edit');
                window.history.replaceState({}, document.title, url);

                // Open edit modal for the specified user
                this.editMember(parseInt(editId, 10));
            }
        },

        /**
         * Carrega campos editáveis configurados
         */
        loadEditableFields: function() {
            const self = this;

            $.ajax({
                url: eauMembersData.ajaxUrl,
                type: 'POST',
                async: false, // Sincrono para garantir que carregue antes de renderizar forms
                data: {
                    action: 'eau_get_editable_fields',
                    nonce: eauMembersData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.editableFields = response.data;
                    }
                },
                error: function() {
                    console.error('Failed to load editable fields');
                }
            });
        },

        /**
         * Carrega lista de instituições
         */
        loadInstitutions: function() {
            const self = this;

            $.ajax({
                url: eauMembersData.ajaxUrl,
                type: 'POST',
                async: false, // Sincrono para garantir que carregue antes de renderizar forms
                data: {
                    action: 'eau_get_institutions_list',
                    nonce: eauMembersData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.institutions = response.data;
                    }
                },
                error: function() {
                    console.error('Failed to load institutions');
                }
            });
        },

        /**
         * Vincula eventos
         */
        bindEvents: function() {
            const self = this;

            // Filters toggle
            $('#eau-filters-toggle').on('click', this.toggleFilters.bind(this));

            // Search
            $('#eau-members-search').on('input', this.debounce(function(e) {
                self.searchTerm = $(e.target).val();
                self.currentPage = 1;
                self.loadMembers();
            }, 300));

            // Export CSV
            $('#eau-export-csv').on('click', this.handleExportCSV.bind(this));

            // Add Member
            $('#eau-add-member').on('click', this.handleAddMember.bind(this));

            // Select All (header)
            $(document).on('change', '#members-table-select-all', function() {
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
                const userId = $(this).data('id');
                self.viewMember(userId);
            });

            $(document).on('click', '.eau-action-edit', function(e) {
                e.preventDefault();
                const userId = $(this).data('id');
                self.editMember(userId);
            });

            $(document).on('click', '.eau-action-delete', function(e) {
                e.preventDefault();
                const userId = $(this).data('id');
                self.deleteMember(userId);
            });

            // Table Sorting
            $(document).on('click', '.eau-sortable', function() {
                const columnKey = $(this).data('key');
                self.handleSort(columnKey);
            });

            // Filters
            $('.eau-filters-apply').on('click', this.handleApplyFilters.bind(this));
            $('.eau-filters-clear').on('click', this.handleClearFilters.bind(this));

            // Auto-apply on filter change (opcional - pode ser removido se quiser aplicar apenas no botão)
            $(document).on('change', '.eau-filter-select, .eau-filter-date', function() {
                // Aplicar filtros automaticamente ao mudar (comentar essa linha se preferir apenas com o botão Apply)
                // self.handleApplyFilters();
            });

            // Bulk Delete (apenas para super admin)
            if (eauMembersData.isSuperAdmin) {
                $('#eau-bulk-delete-members').on('click', this.handleBulkDelete.bind(this));
                $('#eau-delete-all-filtered-members').on('click', this.handleDeleteAllFiltered.bind(this));
            }
        },

        /**
         * Load members via AJAX
         */
        loadMembers: function() {
            const self = this;

            // Show loading
            this.showLoading();

            $.ajax({
                url: eauMembersData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_get_members',
                    nonce: eauMembersData.nonce,
                    page: this.currentPage,
                    per_page: this.perPage,
                    search: this.searchTerm,
                    orderby: this.orderBy,
                    order: this.order,
                    ...this.filters
                },
                success: function(response) {
                    if (response.success) {
                        self.renderMembers(response.data);
                        self.updateCounter(response.data.total);
                        self.renderPagination(response.data);
                    } else {
                        self.showError('Failed to load members');
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
         * Render members in table
         */
        renderMembers: function(data) {
            const tbody = $('#members-table-tbody');

            if (!data.rows || data.rows.length === 0) {
                tbody.html(this.getEmptyState());
                return;
            }

            let html = '';
            data.rows.forEach(function(row) {
                html += `
                    <tr class="eau-table-row" data-id="${row.ID}">
                        <td class="eau-table-td-checkbox">
                            <input type="checkbox" class="eau-row-checkbox" value="${row.ID}">
                        </td>
                        <td class="eau-table-td" data-label="MEMBER">${row.member}</td>
                        <td class="eau-table-td" data-label="CONTACT">${row.contact}</td>
                        <td class="eau-table-td" data-label="MEMBERSHIP">${row.membership}</td>
                        <td class="eau-table-td" data-label="USER TYPE">${row.user_type}</td>
                        <td class="eau-table-td" data-label="STATUS">${row.status}</td>
                        <td class="eau-table-td eau-table-td-actions">
                            <div class="eau-table-actions">
                                <button class="eau-action-btn eau-action-view" data-action="view" data-id="${row.ID}" title="View">
                                    <i data-lucide="eye"></i>
                                </button>
                                <button class="eau-action-btn eau-action-edit" data-action="edit" data-id="${row.ID}" title="Edit">
                                    <i data-lucide="pencil"></i>
                                </button>
                                <button class="eau-action-btn eau-action-delete" data-action="delete" data-id="${row.ID}" title="Delete">
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
                    <td colspan="7" style="text-align: center; padding: 3rem;">
                        <i data-lucide="inbox" style="width: 3rem; height: 3rem; color: #d1d5db; margin-bottom: 1rem;"></i>
                        <p style="color: #6b7280; margin: 0;">No members found</p>
                    </td>
                </tr>
            `;
        },

        /**
         * Update counter
         */
        updateCounter: function(total) {
            $('#members-table-count .eau-count-number').text(total);
        },

        /**
         * Handle sort by column
         */
        handleSort: function(columnKey) {
            // Mapeamento de colunas para campos de ordenação
            const columnMap = {
                'member': 'display_name',
                'contact': 'user_email'
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

            // Recarrega membros
            this.loadMembers();
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
            if (eauMembersData.isSuperAdmin) {
                if (this.selectedIds.length > 0) {
                    $('#eau-bulk-delete-members').show();
                } else {
                    $('#eau-bulk-delete-members').hide();
                }
            }
        },

        /**
         * Show loading overlay
         */
        showLoading: function() {
            $('#members-table-loading').show();
        },

        /**
         * Hide loading overlay
         */
        hideLoading: function() {
            $('#members-table-loading').hide();
        },

        /**
         * Show error
         */
        showError: function(message) {
            alert(message); // TODO: Substituir por toast/notification elegante
        },

        /**
         * Toggle filtros
         */
        toggleFilters: function(e) {
            e.preventDefault();
            $('#eau-filters-panel').slideToggle(200);
        },

        /**
         * View Member
         */
        viewMember: function(userId) {
            const self = this;

            // Abre modal e carrega dados
            this.openModal('eau-modal-view');
            this.loadMemberDetails(userId, 'view');
        },

        /**
         * Edit Member
         */
        editMember: function(userId) {
            const self = this;

            // Abre modal e carrega dados
            this.openModal('eau-modal-edit');
            this.loadMemberDetails(userId, 'edit');
        },

        /**
         * Delete Member
         */
        deleteMember: function(userId) {
            const self = this;

            // Show confirm modal
            EauNotifications.confirm({
                title: 'Delete Member?',
                message: 'Are you sure you want to delete this member? This action cannot be undone.',
                type: 'danger',
                confirmText: 'Delete',
                cancelText: 'Cancel',
                onConfirm: function() {
                    $.ajax({
                        url: eauMembersData.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'eau_delete_member',
                            nonce: eauMembersData.nonce,
                            user_id: userId
                        },
                        success: function(response) {
                            if (response.success) {
                                EauNotifications.success('Success', 'Member deleted successfully');
                                self.loadMembers(); // Reload table
                            } else {
                                EauNotifications.error('Error', response.data.message || 'Failed to delete member');
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

            if (exportType === 'selected' && this.selectedIds.length === 0) {
                alert('Please select at least one member to export.');
                return;
            }

            // Create form and submit
            const form = $('<form>', {
                method: 'POST',
                action: eauMembersData.ajaxUrl
            });

            form.append($('<input>', { type: 'hidden', name: 'action', value: 'eau_export_members_csv' }));
            form.append($('<input>', { type: 'hidden', name: 'nonce', value: eauMembersData.nonce }));
            form.append($('<input>', { type: 'hidden', name: 'export_type', value: exportType }));

            if (exportType === 'selected') {
                this.selectedIds.forEach(function(id) {
                    form.append($('<input>', { type: 'hidden', name: 'selected_ids[]', value: id }));
                });
            }

            form.appendTo('body').submit().remove();
        },

        /**
         * Handle add member
         */
        handleAddMember: function(e) {
            e.preventDefault();

            // Abre modal vazio para adicionar
            this.openModal('eau-modal-add');
            this.loadAddMemberForm();
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
                self.loadMembers();

                // Scroll to top of table
                $('html, body').animate({
                    scrollTop: $('.eau-members-table-wrapper').offset().top - 100
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

            let html = '<div class="eau-pagination-wrapper" id="members-pagination">';

            // Info
            html += '<div class="eau-pagination-info">';
            html += `Showing ${startItem.toLocaleString()} to ${endItem.toLocaleString()} of ${total.toLocaleString()} members`;
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
            this.loadMembers();

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
            this.loadMembers();

            // Atualiza badge de filtros ativos
            this.updateFiltersCount();

        },

        /**
         * Update filters count badge (opcional)
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
                self.saveMember(modalId);
            });

            $overlay.find('[data-modal-action="create"]').off('click').on('click', function() {
                self.createMember();
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
         * Load Member Details
         */
        loadMemberDetails: function(userId, mode) {
            const self = this;
            const modalId = mode === 'view' ? 'eau-modal-view' : 'eau-modal-edit';

            // Show skeleton loading
            $('#' + modalId + '-body').html(`
                <div class="eau-form-grid">
                    <div class="eau-form-field">
                        <div class="eau-skeleton eau-skeleton-text" style="width: 30%; margin-bottom: 0.5rem;"></div>
                        <div class="eau-skeleton eau-skeleton-row"></div>
                    </div>
                    <div class="eau-form-field">
                        <div class="eau-skeleton eau-skeleton-text" style="width: 30%; margin-bottom: 0.5rem;"></div>
                        <div class="eau-skeleton eau-skeleton-row"></div>
                    </div>
                    <div class="eau-form-field">
                        <div class="eau-skeleton eau-skeleton-text" style="width: 30%; margin-bottom: 0.5rem;"></div>
                        <div class="eau-skeleton eau-skeleton-row"></div>
                    </div>
                    <div class="eau-form-field">
                        <div class="eau-skeleton eau-skeleton-text" style="width: 30%; margin-bottom: 0.5rem;"></div>
                        <div class="eau-skeleton eau-skeleton-row"></div>
                    </div>
                </div>
            `);

            $.ajax({
                url: eauMembersData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_get_member_details',
                    nonce: eauMembersData.nonce,
                    user_id: userId
                },
                success: function(response) {
                    if (response.success) {
                        self.renderMemberForm(modalId, response.data, mode);
                    } else {
                        alert('Failed to load member details');
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
         * Load Add Member Form
         */
        loadAddMemberForm: function() {
            const emptyData = {
                ID: '',
                display_name: '',
                user_email: '',
                user_login: '',
                first_name: '',
                last_name: '',
                role: 'subscriber'
            };

            this.renderMemberForm('eau-modal-add', emptyData, 'add');
        },

        /**
         * Render Member Form (Dinâmico baseado em configurações)
         */
        renderMemberForm: function(modalId, userData, mode) {
            const self = this;
            const isView = mode === 'view';
            const isAdd = mode === 'add';

            let html = '<form class="eau-modal-form" id="eau-member-form">';
            html += '<div class="eau-form-grid">';

            // Hidden user ID
            if (userData.ID) {
                html += `<input type="hidden" name="user_id" value="${userData.ID}">`;
            }

            // Renderiza campos configurados
            for (const fieldKey in this.editableFields) {
                const field = this.editableFields[fieldKey];
                html += this.renderField(fieldKey, field, userData, mode);
            }

            html += '</div></form>';

            $('#' + modalId + '-body').html(html);

            // Re-initialize Lucide icons
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }

            // Initialize phone input with intl-tel-input (only for edit/add modes)
            if (!isView) {
                this.initPhoneInput(userData);
            }
        },

        /**
         * Initialize intl-tel-input for phone field
         */
        initPhoneInput: function(userData) {
            const self = this;
            const phoneInput = document.querySelector('#eau-member-phone');

            if (phoneInput && typeof intlTelInput !== 'undefined') {
                // Destroy previous instance if exists
                if (this.phoneIti) {
                    this.phoneIti.destroy();
                    this.phoneIti = null;
                }

                // Initialize intl-tel-input
                this.phoneIti = intlTelInput(phoneInput, {
                    initialCountry: 'au',
                    preferredCountries: ['au', 'nz', 'gb', 'us'],
                    separateDialCode: true,
                    utilsScript: 'https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/utils.js'
                });

                // Set initial value if exists
                const phoneValue = userData && userData.meta ? (userData.meta.mem_phone || userData.meta.mem_mobile || '') : '';
                if (phoneValue) {
                    this.phoneIti.setNumber(phoneValue);
                }

                // Update hidden field when phone changes
                phoneInput.addEventListener('change', function() {
                    if (self.phoneIti) {
                        $('#eau-member-phone-full').val(self.phoneIti.getNumber());
                    }
                });

                // Also update on blur
                phoneInput.addEventListener('blur', function() {
                    if (self.phoneIti) {
                        $('#eau-member-phone-full').val(self.phoneIti.getNumber());
                    }
                });

                // Also update on countrychange
                phoneInput.addEventListener('countrychange', function() {
                    if (self.phoneIti) {
                        $('#eau-member-phone-full').val(self.phoneIti.getNumber());
                    }
                });
            }
        },

        /**
         * Renderiza um campo individual
         */
        renderField: function(fieldKey, fieldConfig, userData, mode) {
            const isView = mode === 'view';
            const isAdd = mode === 'add';

            // Determina readonly
            let readonly = '';
            if (isView || fieldConfig.readonly) {
                readonly = 'readonly';
            }
            // user_login é sempre readonly no edit
            if (fieldKey === 'user_login' && mode === 'edit') {
                readonly = 'readonly';
            }

            // Determina required
            const isRequired = !isView && fieldConfig.required;
            const requiredAttr = isRequired ? 'required' : '';
            const requiredLabel = isRequired ? '<span class="eau-form-required">*</span>' : '';

            // Pega valor do campo
            let value = '';
            if (fieldConfig.type === 'core') {
                value = userData[fieldKey] || '';
            } else if (fieldConfig.type === 'meta') {
                value = userData.meta && userData.meta[fieldConfig.meta_key] ? userData.meta[fieldConfig.meta_key] : '';
            }

            // Determina o name do campo
            const fieldName = fieldConfig.type === 'meta' ? fieldConfig.meta_key : fieldKey;

            // HTML do campo
            let fieldHTML = '';

            // Tipo de input
            const inputType = fieldConfig.field_type || 'text';

            if (inputType === 'select') {
                // Campo SELECT (role, status, etc)
                fieldHTML += `<div class="eau-form-field">`;
                fieldHTML += `<label class="eau-form-label">${fieldConfig.label} ${requiredLabel}</label>`;

                if (fieldKey === 'role') {
                    // Role dropdown
                    if (!isView) {
                        const userRole = userData.roles && userData.roles[0] ? userData.roles[0] : 'subscriber';
                        fieldHTML += `<select class="eau-form-select" name="${fieldName}" ${requiredAttr} ${readonly}>`;
                        fieldHTML += `<option value="subscriber" ${userRole === 'subscriber' ? 'selected' : ''}>Subscriber</option>`;
                        fieldHTML += `<option value="contributor" ${userRole === 'contributor' ? 'selected' : ''}>Contributor</option>`;
                        fieldHTML += `<option value="author" ${userRole === 'author' ? 'selected' : ''}>Author</option>`;
                        fieldHTML += `<option value="editor" ${userRole === 'editor' ? 'selected' : ''}>Editor</option>`;
                        fieldHTML += `<option value="administrator" ${userRole === 'administrator' ? 'selected' : ''}>Administrator</option>`;
                        fieldHTML += `</select>`;
                    } else {
                        const userRole = userData.roles && userData.roles[0] ? userData.roles[0] : 'subscriber';
                        const roleLabel = this.getRoleLabel(userRole);
                        fieldHTML += `<input type="text" class="eau-form-input" value="${roleLabel}" readonly>`;
                    }
                } else if (fieldKey === 'mem_status' || fieldConfig.meta_key === 'mem_status') {
                    // Status dropdown
                    fieldHTML += `<select class="eau-form-select" name="${fieldName}" ${requiredAttr} ${readonly}>`;
                    fieldHTML += `<option value="">Select Status</option>`;
                    fieldHTML += `<option value="Active" ${value === 'Active' ? 'selected' : ''}>Active</option>`;
                    fieldHTML += `<option value="Inactive" ${value === 'Inactive' ? 'selected' : ''}>Inactive</option>`;
                    fieldHTML += `</select>`;
                } else if (fieldKey === 'mem_membercompanyname' || fieldConfig.meta_key === 'mem_membercompanyname') {
                    // Institution dropdown (Member Company Name)
                    if (!isView && !readonly) {
                        fieldHTML += `<select class="eau-form-select" name="${fieldName}" ${requiredAttr}>`;
                        fieldHTML += `<option value="">Select Institution</option>`;

                        // Renderiza options das instituições
                        this.institutions.forEach(function(institution) {
                            const selected = value === institution.value ? 'selected' : '';
                            fieldHTML += `<option value="${institution.value}" ${selected}>${institution.label}</option>`;
                        });

                        fieldHTML += `</select>`;
                    } else {
                        // Modo view ou readonly - mostra como texto
                        // Busca o label da instituição pelo value
                        let displayValue = value;
                        const institution = this.institutions.find(inst => inst.value === value);
                        if (institution) {
                            displayValue = institution.label;
                        }
                        fieldHTML += `<input type="text" class="eau-form-input" value="${displayValue}" readonly>`;
                    }
                } else {
                    // Generic select - apenas texto readonly por enquanto
                    fieldHTML += `<input type="text" class="eau-form-input" name="${fieldName}" value="${value}" ${readonly} ${requiredAttr}>`;
                }

                fieldHTML += `</div>`;
            } else if (inputType === 'textarea') {
                // Textarea
                fieldHTML += `<div class="eau-form-field eau-form-field-span-2">`;
                fieldHTML += `<label class="eau-form-label">${fieldConfig.label} ${requiredLabel}</label>`;
                fieldHTML += `<textarea class="eau-form-input" name="${fieldName}" rows="3" ${readonly} ${requiredAttr}>${value}</textarea>`;
                fieldHTML += `</div>`;
            } else if (inputType === 'tel' || fieldName === 'mem_phone' || fieldName === 'mem_mobile') {
                // Phone input with intl-tel-input DDI selector
                fieldHTML += `<div class="eau-form-field">`;
                fieldHTML += `<label class="eau-form-label">${fieldConfig.label} ${requiredLabel}</label>`;
                if (!isView) {
                    fieldHTML += `<div class="eau-phone-input-wrapper">`;
                    fieldHTML += `<input type="tel" class="eau-form-input eau-phone-input" id="eau-member-phone" autocomplete="tel" placeholder="Enter phone number" ${requiredAttr}>`;
                    fieldHTML += `<input type="hidden" name="${fieldName}" id="eau-member-phone-full" value="${value}">`;
                    fieldHTML += `</div>`;
                } else {
                    fieldHTML += `<input type="text" class="eau-form-input" value="${value}" readonly>`;
                }
                fieldHTML += `</div>`;
            } else {
                // Input text, email, etc
                fieldHTML += `<div class="eau-form-field">`;
                fieldHTML += `<label class="eau-form-label">${fieldConfig.label} ${requiredLabel}</label>`;
                fieldHTML += `<input type="${inputType}" class="eau-form-input" name="${fieldName}" value="${value}" ${readonly} ${requiredAttr}>`;
                fieldHTML += `</div>`;
            }

            return fieldHTML;
        },

        /**
         * Helper para pegar label da role
         */
        getRoleLabel: function(role) {
            const labels = {
                'administrator': 'Administrator',
                'editor': 'Editor',
                'author': 'Author',
                'contributor': 'Contributor',
                'subscriber': 'Subscriber'
            };
            return labels[role] || role;
        },

        /**
         * Save Member (Edit)
         */
        saveMember: function(modalId) {
            const self = this;
            const $form = $('#eau-member-form');

            // Update phone hidden field with full number before saving
            if (this.phoneIti) {
                $('#eau-member-phone-full').val(this.phoneIti.getNumber());
            }

            // Valida form
            if (!$form[0].checkValidity()) {
                $form[0].reportValidity();
                return;
            }

            const formData = $form.serializeArray();
            const fields = {};

            // Converte para objeto
            formData.forEach(function(item) {
                if (item.name !== 'user_id') {
                    fields[item.name] = item.value;
                }
            });

            const userId = $form.find('input[name="user_id"]').val();

            $.ajax({
                url: eauMembersData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_update_member',
                    nonce: eauMembersData.nonce,
                    user_id: userId,
                    fields: fields
                },
                success: function(response) {
                    if (response.success) {
                        EauNotifications.success('Saved!', 'Member updated successfully');
                        self.closeModal(modalId);
                        self.loadMembers();
                    } else {
                        EauNotifications.error('Error', response.data.message || 'Failed to update member');
                    }
                },
                error: function() {
                    EauNotifications.error('Network Error', 'Please try again');
                }
            });
        },

        /**
         * Create Member (Add)
         */
        createMember: function() {
            const self = this;
            const $form = $('#eau-member-form');

            // Update phone hidden field with full number before saving
            if (this.phoneIti) {
                $('#eau-member-phone-full').val(this.phoneIti.getNumber());
            }

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

            // Se user_login não estiver presente, gera automaticamente do email
            if (!dataObj.user_login && dataObj.user_email) {
                // Gera username a partir do email (parte antes do @)
                dataObj.user_login = dataObj.user_email.split('@')[0];
                // Remove caracteres especiais e substitui por underscore
                dataObj.user_login = dataObj.user_login.replace(/[^a-zA-Z0-9]/g, '_').toLowerCase();
            }

            $.ajax({
                url: eauMembersData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_create_member',
                    nonce: eauMembersData.nonce,
                    ...dataObj
                },
                success: function(response) {
                    if (response.success) {
                        EauNotifications.success('Success!', 'Member created successfully');
                        self.closeModal('eau-modal-add');
                        self.loadMembers();
                    } else {
                        EauNotifications.error('Error', response.data.message || 'Failed to create member');
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
                EauNotifications.warning('No Selection', 'Please select members to delete.');
                return;
            }

            const count = this.selectedIds.length;
            EauNotifications.confirm({
                title: 'Delete Members?',
                message: `Are you sure you want to delete ${count} member(s)? This action cannot be undone.`,
                type: 'danger',
                confirmText: 'Delete',
                cancelText: 'Cancel',
                onConfirm: function() {
                    $.ajax({
                        url: eauMembersData.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'eau_bulk_delete_members',
                            nonce: eauMembersData.nonce,
                            ids: self.selectedIds
                        },
                        success: function(response) {
                            if (response.success) {
                                EauNotifications.success('Deleted!', response.data.message);
                                self.selectedIds = [];
                                self.loadMembers();
                                $('#eau-bulk-delete-members').hide();
                            } else {
                                EauNotifications.error('Error', response.data.message || 'Failed to delete members');
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
         * Handle delete all filtered members (em lotes)
         */
        handleDeleteAllFiltered: function() {
            const self = this;

            // Primeiro, busca todos os IDs filtrados
            $.ajax({
                url: eauMembersData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_get_filtered_member_ids',
                    nonce: eauMembersData.nonce,
                    search: self.searchTerm,
                    ...self.filters
                },
                success: function(response) {
                    if (response.success) {
                        const totalIds = response.data.ids;
                        const totalCount = response.data.total;

                        if (totalCount === 0) {
                            EauNotifications.warning('No Members', 'No members found with current filters.');
                            return;
                        }

                        // Confirma com o usuário
                        EauNotifications.confirm({
                            title: 'Delete All Filtered Members?',
                            message: `Are you sure you want to delete ${totalCount} member(s)? This action cannot be undone and will be processed in batches.`,
                            type: 'danger',
                            confirmText: 'Delete All',
                            cancelText: 'Cancel',
                            onConfirm: function() {
                                self.processBatchDeletion(totalIds, 'members');
                            }
                        });
                    } else {
                        EauNotifications.error('Error', response.data.message || 'Failed to fetch filtered members');
                    }
                },
                error: function() {
                    EauNotifications.error('Network Error', 'Please try again');
                }
            });
        },

        /**
         * Processa deleção em lotes com barra de progresso
         */
        processBatchDeletion: function(allIds, type) {
            const self = this;
            const batchSize = 50;
            const totalCount = allIds.length;
            let processedCount = 0;
            let deletedCount = 0;
            let failedCount = 0;

            // Cria notificação de progresso
            const progressNotification = EauNotifications.info(
                'Deleting...',
                `Processing 0 of ${totalCount} ${type}...`,
                { duration: 0 } // Não fecha automaticamente
            );

            // Função para processar próximo lote
            function processNextBatch() {
                if (processedCount >= totalCount) {
                    // Finalizado
                    EauNotifications.close(progressNotification);

                    let message = `Successfully deleted ${deletedCount} ${type}.`;
                    if (failedCount > 0) {
                        message += ` ${failedCount} ${type} could not be deleted.`;
                    }

                    EauNotifications.success('Completed!', message);
                    self.loadMembers();
                    return;
                }

                // Pega próximo lote
                const batch = allIds.slice(processedCount, processedCount + batchSize);

                $.ajax({
                    url: eauMembersData.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'eau_bulk_delete_members_batch',
                        nonce: eauMembersData.nonce,
                        ids: batch
                    },
                    success: function(response) {
                        if (response.success) {
                            deletedCount += response.data.deleted_count;
                            failedCount += response.data.failed_count;
                        } else {
                            failedCount += batch.length;
                        }

                        processedCount += batch.length;

                        // Atualiza progresso
                        const percentage = Math.round((processedCount / totalCount) * 100);
                        EauNotifications.update(progressNotification, {
                            message: `Processing ${processedCount} of ${totalCount} ${type}... (${percentage}%)`
                        });

                        // Processa próximo lote
                        processNextBatch();
                    },
                    error: function() {
                        failedCount += batch.length;
                        processedCount += batch.length;
                        processNextBatch();
                    }
                });
            }

            // Inicia processamento
            processNextBatch();
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
        if ($('.eau-members-management-container').length) {
            EauMembersManagement.init();
        }
    });

})(jQuery);
