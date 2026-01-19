/**
 * EAU System - Members Management JS
 * Versão: 1.10.4
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
        availableTags: [], // Lista de tags disponíveis para membros
        orderBy: 'display_name', // Campo de ordenação
        order: 'ASC', // Direção: ASC ou DESC
        phoneIti: null, // intl-tel-input instance

        // States/Provinces by country
        statesByCountry: {
            'AU': {
                'ACT': 'Australian Capital Territory',
                'NSW': 'New South Wales',
                'NT': 'Northern Territory',
                'QLD': 'Queensland',
                'SA': 'South Australia',
                'TAS': 'Tasmania',
                'VIC': 'Victoria',
                'WA': 'Western Australia'
            },
            'NZ': {
                'AUK': 'Auckland',
                'BOP': 'Bay of Plenty',
                'CAN': 'Canterbury',
                'GIS': 'Gisborne',
                'HKB': 'Hawke\'s Bay',
                'MWT': 'Manawatu-Wanganui',
                'MBH': 'Marlborough',
                'NSN': 'Nelson',
                'NTL': 'Northland',
                'OTA': 'Otago',
                'STL': 'Southland',
                'TKI': 'Taranaki',
                'TAS': 'Tasman',
                'WKO': 'Waikato',
                'WGN': 'Wellington',
                'WTC': 'West Coast'
            },
            'GB': {
                'ENG': 'England',
                'SCT': 'Scotland',
                'WLS': 'Wales',
                'NIR': 'Northern Ireland'
            },
            'US': {
                'AL': 'Alabama', 'AK': 'Alaska', 'AZ': 'Arizona', 'AR': 'Arkansas',
                'CA': 'California', 'CO': 'Colorado', 'CT': 'Connecticut', 'DE': 'Delaware',
                'FL': 'Florida', 'GA': 'Georgia', 'HI': 'Hawaii', 'ID': 'Idaho',
                'IL': 'Illinois', 'IN': 'Indiana', 'IA': 'Iowa', 'KS': 'Kansas',
                'KY': 'Kentucky', 'LA': 'Louisiana', 'ME': 'Maine', 'MD': 'Maryland',
                'MA': 'Massachusetts', 'MI': 'Michigan', 'MN': 'Minnesota', 'MS': 'Mississippi',
                'MO': 'Missouri', 'MT': 'Montana', 'NE': 'Nebraska', 'NV': 'Nevada',
                'NH': 'New Hampshire', 'NJ': 'New Jersey', 'NM': 'New Mexico', 'NY': 'New York',
                'NC': 'North Carolina', 'ND': 'North Dakota', 'OH': 'Ohio', 'OK': 'Oklahoma',
                'OR': 'Oregon', 'PA': 'Pennsylvania', 'RI': 'Rhode Island', 'SC': 'South Carolina',
                'SD': 'South Dakota', 'TN': 'Tennessee', 'TX': 'Texas', 'UT': 'Utah',
                'VT': 'Vermont', 'VA': 'Virginia', 'WA': 'Washington', 'WV': 'West Virginia',
                'WI': 'Wisconsin', 'WY': 'Wyoming', 'DC': 'District of Columbia'
            },
            'CA': {
                'AB': 'Alberta', 'BC': 'British Columbia', 'MB': 'Manitoba',
                'NB': 'New Brunswick', 'NL': 'Newfoundland and Labrador',
                'NS': 'Nova Scotia', 'NT': 'Northwest Territories', 'NU': 'Nunavut',
                'ON': 'Ontario', 'PE': 'Prince Edward Island', 'QC': 'Quebec',
                'SK': 'Saskatchewan', 'YT': 'Yukon'
            },
            'BR': {
                'AC': 'Acre', 'AL': 'Alagoas', 'AP': 'Amapá', 'AM': 'Amazonas',
                'BA': 'Bahia', 'CE': 'Ceará', 'DF': 'Distrito Federal', 'ES': 'Espírito Santo',
                'GO': 'Goiás', 'MA': 'Maranhão', 'MT': 'Mato Grosso', 'MS': 'Mato Grosso do Sul',
                'MG': 'Minas Gerais', 'PA': 'Pará', 'PB': 'Paraíba', 'PR': 'Paraná',
                'PE': 'Pernambuco', 'PI': 'Piauí', 'RJ': 'Rio de Janeiro', 'RN': 'Rio Grande do Norte',
                'RS': 'Rio Grande do Sul', 'RO': 'Rondônia', 'RR': 'Roraima',
                'SC': 'Santa Catarina', 'SP': 'São Paulo', 'SE': 'Sergipe', 'TO': 'Tocantins'
            }
        },

        /**
         * Inicializa
         */
        init: function() {
            this.hideBulkActionButtons(); // Esconde botões de ação em massa inicialmente
            this.loadEditableFields(); // Carrega campos configurados
            this.loadInstitutions(); // Carrega instituições
            this.loadAvailableTags(); // Carrega tags disponíveis
            this.bindEvents();
            this.checkUrlParams(); // Check for URL parameters BEFORE loading members
            this.loadMembers();
        },

        /**
         * Esconde os botões de ação em massa (Delete Selected, Manage Tags)
         * Chamado no init e quando não há itens selecionados
         * Usa css() com !important para sobrescrever o CSS do .eau-btn
         */
        hideBulkActionButtons: function() {
            $('#eau-bulk-delete-members').css('display', 'none');
            $('#eau-bulk-manage-tags').css('display', 'none');
            // Força o !important via cssText para sobrescrever o CSS
            document.getElementById('eau-bulk-delete-members')?.style.setProperty('display', 'none', 'important');
            document.getElementById('eau-bulk-manage-tags')?.style.setProperty('display', 'none', 'important');
        },

        /**
         * Mostra os botões de ação em massa
         */
        showBulkActionButtons: function() {
            // Remove o style inline para deixar o CSS padrão (.eau-btn) funcionar
            $('#eau-bulk-delete-members').css('display', '');
            $('#eau-bulk-manage-tags').css('display', '');
        },

        /**
         * Check URL parameters for direct actions
         */
        checkUrlParams: function() {
            const urlParams = new URLSearchParams(window.location.search);
            const editId = urlParams.get('edit');
            const statusFilter = urlParams.get('status');
            const institutionFilter = urlParams.get('institution');

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

            // Apply institution filter from URL (used when clicking members count from /manage-institutions/)
            if (institutionFilter) {
                // Set the filter value (ensure it's a string for consistency)
                this.filters.institution = institutionFilter;

                // Update the select element with the institution ID
                const $institutionSelect = $('#eau-filter-institution');
                if ($institutionSelect.length > 0) {
                    $institutionSelect.val(institutionFilter);

                    // If the value wasn't found in the select, try with different formats
                    if ($institutionSelect.val() !== institutionFilter) {
                        // Try as integer
                        $institutionSelect.val(parseInt(institutionFilter, 10));
                    }
                }

                // Show the filters panel
                $('#eau-filters-panel').addClass('active');

                // Remove the parameter from URL to avoid re-applying on refresh
                const url = new URL(window.location);
                url.searchParams.delete('institution');
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
         * Carrega lista de tags disponíveis
         */
        loadAvailableTags: function() {
            const self = this;

            $.ajax({
                url: eauMembersData.ajaxUrl,
                type: 'POST',
                async: false, // Sincrono para garantir que carregue antes de renderizar forms
                data: {
                    action: 'eau_get_member_tags',
                    nonce: eauMembersData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.availableTags = response.data.tags || [];
                    }
                },
                error: function() {
                    console.error('Failed to load available tags');
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

            // Tags Quick Edit
            $(document).on('click', '.eau-action-tags', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const userId = $(this).data('id');
                const currentTags = $(this).data('tags') || '';
                self.openTagsPopover($(this), userId, currentTags);
            });

            // Close tags popover when clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.eau-tags-popover, .eau-action-tags').length) {
                    self.closeTagsPopover();
                }
            });

            // Tags popover events (delegated)
            $(document).on('click', '.eau-popover-tag-remove', function(e) {
                e.preventDefault();
                const slug = $(this).data('slug');
                const userId = $(this).closest('.eau-tags-popover').data('user-id');
                self.removeTagFromMember(userId, slug);
            });

            $(document).on('click', '.eau-popover-tag-add', function(e) {
                e.preventDefault();
                const slug = $(this).data('slug');
                const userId = $(this).closest('.eau-tags-popover').data('user-id');
                self.addTagToMember(userId, slug);
            });

            $(document).on('input', '.eau-popover-tag-search', function() {
                self.filterPopoverTags($(this).val());
            });

            // Reset Email Migration Status (v1.62.0)
            $(document).on('click', '.eau-btn-reset-email', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const userId = $(this).data('user-id');
                self.resetEmailMigration(userId);
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

            // Bulk Manage Tags (Admin ou superAdmin)
            if (eauMembersData.canManageTags) {
                $('#eau-bulk-manage-tags').on('click', this.openBulkTagsModal.bind(this));
            }

            // Popover: Criar nova tag inline
            $(document).on('click', '.eau-popover-create-btn', function(e) {
                e.preventDefault();
                const tagName = $('.eau-popover-tag-search').val().trim();
                const userId = $('.eau-tags-popover').data('user-id');
                if (tagName && userId) {
                    self.createTagInPopover(tagName, userId);
                }
            });
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
                        <td class="eau-table-td" data-label="POSITION">${row.position || '<span class="eau-text-muted">-</span>'}</td>
                        <td class="eau-table-td" data-label="STATUS">${row.status}</td>
                        <td class="eau-table-td" data-label="EMAIL">${row.email_status || '<span class="eau-text-muted">-</span>'}</td>
                        <td class="eau-table-td eau-table-td-actions">
                            <div class="eau-table-actions">
                                ${eauMembersData.canManageTags ? `
                                <button class="eau-action-btn eau-action-tags" data-action="tags" data-id="${row.ID}" data-tags="${row.member_tags || ''}" title="Manage Tags">
                                    <i data-lucide="tags"></i>
                                </button>
                                ` : ''}
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
                    <td colspan="8" style="text-align: center; padding: 3rem;">
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

            // Mostrar/ocultar botões de ação em massa baseado na seleção
            if (this.selectedIds.length > 0) {
                // Mostra os botões removendo o style inline (deixa o CSS padrão funcionar)
                if (eauMembersData.isSuperAdmin) {
                    const deleteBtn = document.getElementById('eau-bulk-delete-members');
                    if (deleteBtn) deleteBtn.style.removeProperty('display');
                }
                if (eauMembersData.canManageTags) {
                    const tagsBtn = document.getElementById('eau-bulk-manage-tags');
                    if (tagsBtn) tagsBtn.style.removeProperty('display');
                }
            } else {
                // Esconde os botões usando !important para sobrescrever o CSS
                this.hideBulkActionButtons();
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
         * Reset email migration status (v1.62.0)
         */
        resetEmailMigration: function(userId) {
            const self = this;

            EauNotifications.confirm({
                title: 'Reset Email Migration?',
                message: 'This will reset the email migration status to "pending". The user will need to update their email again on next login.',
                type: 'warning',
                confirmText: 'Reset',
                cancelText: 'Cancel',
                onConfirm: function() {
                    $.ajax({
                        url: eauMembersData.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'eau_reset_email_migration',
                            nonce: eauMembersData.nonce,
                            user_id: userId
                        },
                        success: function(response) {
                            if (response.success) {
                                EauNotifications.success('Success', response.data.message);
                                self.loadMembers(); // Reload table
                            } else {
                                EauNotifications.error('Error', response.data.message || 'Failed to reset email migration status');
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
                this.initTagsField();
                this.initCountryStateFields();
            }
        },

        /**
         * Initialize country/state dynamic dropdowns
         */
        initCountryStateFields: function() {
            const self = this;
            const countrySelect = document.querySelector('#eau-member-country');
            const stateField = document.querySelector('#eau-member-state');

            if (countrySelect && stateField) {
                countrySelect.addEventListener('change', function() {
                    self.updateStateOptions(this.value, '');
                });
            }
        },

        /**
         * Update state options based on selected country
         */
        updateStateOptions: function(countryCode, currentValue) {
            const stateContainer = document.querySelector('#eau-member-state');
            if (!stateContainer) return;

            const states = this.statesByCountry[countryCode] || {};
            const hasStates = Object.keys(states).length > 0;
            const parentDiv = stateContainer.parentElement;

            if (hasStates) {
                // Replace with select
                let html = `<select class="eau-form-select" name="mem_state" id="eau-member-state">`;
                html += `<option value="">Select State/Province</option>`;
                Object.entries(states).forEach(([stateCode, stateName]) => {
                    const selected = currentValue === stateCode ? 'selected' : '';
                    html += `<option value="${stateCode}" ${selected}>${stateName}</option>`;
                });
                html += `</select>`;

                // Replace the existing element
                const oldElement = document.querySelector('#eau-member-state');
                const newElement = document.createElement('div');
                newElement.innerHTML = html;
                oldElement.replaceWith(newElement.firstChild);
            } else {
                // Replace with text input
                const html = `<input type="text" class="eau-form-input" name="mem_state" id="eau-member-state" value="${currentValue}" placeholder="Enter state/province">`;

                const oldElement = document.querySelector('#eau-member-state');
                const newElement = document.createElement('div');
                newElement.innerHTML = html;
                oldElement.replaceWith(newElement.firstChild);
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
                } else if (fieldConfig.options) {
                    // Generic select with options from config
                    if (!isView && !readonly) {
                        fieldHTML += `<select class="eau-form-select" name="${fieldName}" ${requiredAttr}>`;
                        Object.entries(fieldConfig.options).forEach(([optValue, optLabel]) => {
                            const selected = value === optValue ? 'selected' : '';
                            fieldHTML += `<option value="${optValue}" ${selected}>${optLabel}</option>`;
                        });
                        fieldHTML += `</select>`;
                    } else {
                        // View mode - show label instead of value
                        const displayValue = fieldConfig.options[value] || value || '';
                        fieldHTML += `<input type="text" class="eau-form-input" value="${displayValue}" readonly>`;
                    }
                } else {
                    // Generic select without options - show as text input
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
            } else if (inputType === 'tags') {
                // Tags field - multi-select with chips and inline creation
                const memberTags = Array.isArray(value) ? value : (value ? value.split(',') : []);

                fieldHTML += `<div class="eau-form-field eau-form-field-span-2">`;
                fieldHTML += `<label class="eau-form-label">${fieldConfig.label} ${requiredLabel}</label>`;
                fieldHTML += `<div class="eau-tags-field" id="eau-member-tags-field">`;

                // Selected tags as chips
                fieldHTML += `<div class="eau-selected-tags" id="eau-member-selected-tags">`;
                memberTags.forEach(tagSlug => {
                    const tag = this.availableTags.find(t => t.slug === tagSlug);
                    if (tag) {
                        if (isView) {
                            fieldHTML += `<span class="eau-tag-chip" style="background-color: ${tag.color}; color: white;">${this.escapeHtml(tag.name)}</span>`;
                        } else {
                            fieldHTML += `<span class="eau-tag-chip" style="background-color: ${tag.color}; color: white;" data-slug="${tag.slug}">
                                ${this.escapeHtml(tag.name)}
                                <button type="button" class="eau-tag-remove" data-slug="${tag.slug}">&times;</button>
                            </span>`;
                        }
                    }
                });
                fieldHTML += `</div>`;

                if (!isView) {
                    // Tag input for adding
                    fieldHTML += `<div class="eau-tags-input-wrapper">`;
                    fieldHTML += `<div class="eau-tags-dropdown-container">`;
                    fieldHTML += `<input type="text" class="eau-form-input eau-tag-search" id="eau-tag-search" placeholder="Type to search or add tags..." autocomplete="off">`;
                    fieldHTML += `<div class="eau-tags-dropdown" id="eau-tags-dropdown" style="display: none;">`;
                    fieldHTML += `<div class="eau-tags-dropdown-list" id="eau-tags-dropdown-list"></div>`;
                    fieldHTML += `<div class="eau-tags-dropdown-create" id="eau-tags-dropdown-create" style="display: none;">`;
                    fieldHTML += `<button type="button" class="eau-btn eau-btn-sm eau-btn-secondary" id="eau-create-tag-inline">`;
                    fieldHTML += `<i data-lucide="plus"></i> Create "<span id="eau-new-tag-name"></span>"`;
                    fieldHTML += `</button>`;
                    fieldHTML += `</div>`;
                    fieldHTML += `</div>`;
                    fieldHTML += `</div>`;
                    fieldHTML += `</div>`;

                    // Hidden field to store selected tag slugs
                    fieldHTML += `<input type="hidden" name="${fieldName}" id="eau-member-tags-hidden" value="${memberTags.join(',')}">`;
                }

                fieldHTML += `</div>`;
                fieldHTML += `</div>`;
            } else if (inputType === 'country') {
                // Country dropdown
                fieldHTML += `<div class="eau-form-field">`;
                fieldHTML += `<label class="eau-form-label">${fieldConfig.label} ${requiredLabel}</label>`;

                if (!isView && !readonly) {
                    fieldHTML += `<select class="eau-form-select" name="${fieldName}" id="eau-member-country" ${requiredAttr}>`;
                    if (fieldConfig.options) {
                        Object.entries(fieldConfig.options).forEach(([optValue, optLabel]) => {
                            const selected = value === optValue ? 'selected' : '';
                            fieldHTML += `<option value="${optValue}" ${selected}>${optLabel}</option>`;
                        });
                    }
                    fieldHTML += `</select>`;
                } else {
                    // View mode
                    const displayValue = fieldConfig.options && fieldConfig.options[value] ? fieldConfig.options[value] : value;
                    fieldHTML += `<input type="text" class="eau-form-input" value="${displayValue || ''}" readonly>`;
                }

                fieldHTML += `</div>`;
            } else if (inputType === 'state') {
                // State dropdown (dynamic based on country)
                fieldHTML += `<div class="eau-form-field">`;
                fieldHTML += `<label class="eau-form-label">${fieldConfig.label} ${requiredLabel}</label>`;

                if (!isView && !readonly) {
                    // Get country value to populate states
                    const countryValue = userData.meta && userData.meta.mem_country ? userData.meta.mem_country : 'AU';
                    const states = this.statesByCountry[countryValue] || {};
                    const hasStates = Object.keys(states).length > 0;

                    if (hasStates) {
                        fieldHTML += `<select class="eau-form-select" name="${fieldName}" id="eau-member-state" ${requiredAttr}>`;
                        fieldHTML += `<option value="">Select State/Province</option>`;
                        Object.entries(states).forEach(([stateCode, stateName]) => {
                            const selected = value === stateCode ? 'selected' : '';
                            fieldHTML += `<option value="${stateCode}" ${selected}>${stateName}</option>`;
                        });
                        fieldHTML += `</select>`;
                    } else {
                        // No predefined states - show text input
                        fieldHTML += `<input type="text" class="eau-form-input" name="${fieldName}" id="eau-member-state" value="${value || ''}" placeholder="Enter state/province" ${requiredAttr}>`;
                    }
                } else {
                    // View mode - try to get state label
                    const countryValue = userData.meta && userData.meta.mem_country ? userData.meta.mem_country : '';
                    const states = this.statesByCountry[countryValue] || {};
                    const displayValue = states[value] || value;
                    fieldHTML += `<input type="text" class="eau-form-input" value="${displayValue || ''}" readonly>`;
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
        },

        /**
         * Escape HTML helper
         */
        escapeHtml: function(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        /**
         * Initialize tags field events
         */
        initTagsField: function() {
            const self = this;

            // Tag search input
            $(document).off('focus', '#eau-tag-search').on('focus', '#eau-tag-search', function() {
                self.showTagsDropdown();
            });

            $(document).off('input', '#eau-tag-search').on('input', '#eau-tag-search', function() {
                self.filterTagsDropdown($(this).val());
            });

            // Close dropdown when clicking outside
            $(document).off('click.tagsdropdown').on('click.tagsdropdown', function(e) {
                if (!$(e.target).closest('.eau-tags-dropdown-container').length) {
                    $('#eau-tags-dropdown').hide();
                }
            });

            // Remove tag
            $(document).off('click', '.eau-tag-remove').on('click', '.eau-tag-remove', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const slug = $(this).data('slug');
                self.removeMemberTag(slug);
            });

            // Add tag from dropdown
            $(document).off('click', '.eau-tag-dropdown-item').on('click', '.eau-tag-dropdown-item', function(e) {
                e.preventDefault();
                const slug = $(this).data('slug');
                self.addMemberTag(slug);
                $('#eau-tag-search').val('');
                $('#eau-tags-dropdown').hide();
            });

            // Create tag inline
            $(document).off('click', '#eau-create-tag-inline').on('click', '#eau-create-tag-inline', function(e) {
                e.preventDefault();
                const tagName = $('#eau-tag-search').val().trim();
                if (tagName) {
                    self.createTagInline(tagName);
                }
            });
        },

        /**
         * Show tags dropdown with available tags
         */
        showTagsDropdown: function() {
            this.filterTagsDropdown($('#eau-tag-search').val());
            $('#eau-tags-dropdown').show();
        },

        /**
         * Filter tags dropdown based on search
         */
        filterTagsDropdown: function(search) {
            const self = this;
            const $list = $('#eau-tags-dropdown-list');
            const $createSection = $('#eau-tags-dropdown-create');
            const selectedSlugs = this.getSelectedTagSlugs();

            search = (search || '').toLowerCase().trim();

            // Filter available tags
            let filteredTags = this.availableTags.filter(tag => {
                // Exclude already selected tags
                if (selectedSlugs.includes(tag.slug)) return false;
                // Filter by search
                if (search && !tag.name.toLowerCase().includes(search)) return false;
                return true;
            });

            // Render filtered tags
            let html = '';
            filteredTags.forEach(tag => {
                html += `<div class="eau-tag-dropdown-item" data-slug="${tag.slug}">
                    <span class="eau-tag-color" style="background-color: ${tag.color}"></span>
                    <span class="eau-tag-name">${this.escapeHtml(tag.name)}</span>
                </div>`;
            });

            if (filteredTags.length === 0 && !search) {
                html = '<div class="eau-tags-dropdown-empty">No tags available</div>';
            } else if (filteredTags.length === 0 && search) {
                html = '<div class="eau-tags-dropdown-empty">No matching tags</div>';
            }

            $list.html(html);

            // Show create option if search doesn't match any tag exactly
            const exactMatch = this.availableTags.some(tag => tag.name.toLowerCase() === search);
            if (search && !exactMatch) {
                $('#eau-new-tag-name').text(search);
                $createSection.show();
            } else {
                $createSection.hide();
            }
        },

        /**
         * Get selected tag slugs from hidden input
         */
        getSelectedTagSlugs: function() {
            const value = $('#eau-member-tags-hidden').val();
            return value ? value.split(',').filter(s => s) : [];
        },

        /**
         * Add tag to member
         */
        addMemberTag: function(slug) {
            const tag = this.availableTags.find(t => t.slug === slug);
            if (!tag) return;

            const slugs = this.getSelectedTagSlugs();
            if (slugs.includes(slug)) return;

            // Add to hidden input
            slugs.push(slug);
            $('#eau-member-tags-hidden').val(slugs.join(','));

            // Add chip
            const chip = `<span class="eau-tag-chip" style="background-color: ${tag.color}; color: white;" data-slug="${tag.slug}">
                ${this.escapeHtml(tag.name)}
                <button type="button" class="eau-tag-remove" data-slug="${tag.slug}">&times;</button>
            </span>`;
            $('#eau-member-selected-tags').append(chip);
        },

        /**
         * Remove tag from member
         */
        removeMemberTag: function(slug) {
            let slugs = this.getSelectedTagSlugs();
            slugs = slugs.filter(s => s !== slug);
            $('#eau-member-tags-hidden').val(slugs.join(','));

            // Remove chip
            $(`.eau-tag-chip[data-slug="${slug}"]`).remove();
        },

        /**
         * Create tag inline and add to member
         */
        createTagInline: function(tagName) {
            const self = this;

            $.ajax({
                url: eauMembersData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_add_member_tag',
                    nonce: eauMembersData.nonce,
                    name: tagName
                },
                success: function(response) {
                    if (response.success) {
                        const newTag = response.data.tag;
                        self.availableTags.push(newTag);
                        self.addMemberTag(newTag.slug);
                        $('#eau-tag-search').val('');
                        $('#eau-tags-dropdown').hide();
                        EauNotifications.success('Tag Created', `Tag "${newTag.name}" created and added.`);
                    } else {
                        EauNotifications.error('Error', response.data.message || 'Failed to create tag.');
                    }
                },
                error: function() {
                    EauNotifications.error('Network Error', 'Failed to create tag.');
                }
            });
        },

        // ======================================
        // QUICK TAGS POPOVER (Inline na Tabela)
        // ======================================

        /**
         * Abre o popover de tags para um membro específico
         */
        openTagsPopover: function($button, userId, currentTagsStr) {
            const self = this;

            // Fecha qualquer popover aberto
            this.closeTagsPopover();

            // Parse das tags atuais
            const currentTags = currentTagsStr ? currentTagsStr.split(',').filter(s => s.trim()) : [];

            // Cria HTML do popover
            let html = `
                <div class="eau-tags-popover" data-user-id="${userId}">
                    <div class="eau-tags-popover-header">
                        <span class="eau-tags-popover-title">Quick Tags</span>
                        <button type="button" class="eau-tags-popover-close">
                            <i data-lucide="x"></i>
                        </button>
                    </div>
                    <div class="eau-tags-popover-body">
                        <div class="eau-popover-current-tags">
                            ${this.renderPopoverCurrentTags(currentTags)}
                        </div>
                        <div class="eau-popover-divider"></div>
                        <div class="eau-popover-search">
                            <input type="text" class="eau-popover-tag-search" placeholder="Search or create tags...">
                        </div>
                        <div class="eau-popover-available-tags">
                            ${this.renderPopoverAvailableTags(currentTags)}
                        </div>
                        <div class="eau-popover-create-tag" id="eau-popover-create-tag" style="display: none;">
                            <button type="button" class="eau-btn eau-btn-sm eau-btn-secondary eau-popover-create-btn">
                                <i data-lucide="plus"></i>
                                <span>Create "<span class="eau-popover-new-tag-name"></span>"</span>
                            </button>
                        </div>
                    </div>
                </div>
            `;

            // Adiciona popover ao body
            $('body').append(html);

            // Posiciona popover próximo ao botão usando getBoundingClientRect para posição relativa à viewport
            const $popover = $('.eau-tags-popover');
            const buttonRect = $button[0].getBoundingClientRect();
            const popoverWidth = 280;
            const popoverHeight = 380;

            // Posição inicial: abaixo do botão, relativo à viewport
            let top = buttonRect.bottom + 5;
            let left = buttonRect.left - (popoverWidth / 2) + (buttonRect.width / 2);

            // Ajusta se ultrapassar a tela horizontalmente
            const windowWidth = $(window).width();
            if (left + popoverWidth > windowWidth - 10) {
                left = windowWidth - popoverWidth - 10;
            }
            if (left < 10) {
                left = 10;
            }

            // Ajusta se ultrapassar a tela verticalmente (abre acima se não couber abaixo)
            const windowHeight = $(window).height();
            if (top + popoverHeight > windowHeight - 10) {
                // Tenta abrir acima do botão
                top = buttonRect.top - popoverHeight - 5;
                // Se ainda não couber acima, abre abaixo mesmo e deixa rolar
                if (top < 10) {
                    top = Math.max(10, windowHeight - popoverHeight - 10);
                }
            }

            $popover.css({
                position: 'fixed',
                top: top + 'px',
                left: left + 'px'
            });

            // Bind evento de fechar
            $popover.find('.eau-tags-popover-close').on('click', function() {
                self.closeTagsPopover();
            });

            // Re-inicializa Lucide
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        },

        /**
         * Renderiza as tags atuais do membro no popover
         */
        renderPopoverCurrentTags: function(currentTags) {
            if (currentTags.length === 0) {
                return '<span class="eau-popover-no-tags">No tags assigned</span>';
            }

            let html = '';
            currentTags.forEach(slug => {
                const tag = this.availableTags.find(t => t.slug === slug);
                if (tag) {
                    html += `
                        <span class="eau-popover-tag-chip" style="background-color: ${tag.color}; color: white;">
                            ${this.escapeHtml(tag.name)}
                            <button type="button" class="eau-popover-tag-remove" data-slug="${slug}">&times;</button>
                        </span>
                    `;
                }
            });
            return html;
        },

        /**
         * Renderiza as tags disponíveis para adicionar no popover
         */
        renderPopoverAvailableTags: function(currentTags, filter = '') {
            let availableTags = this.availableTags.filter(tag => {
                // Exclui tags já selecionadas
                if (currentTags.includes(tag.slug)) return false;
                // Filtra por busca
                if (filter && !tag.name.toLowerCase().includes(filter.toLowerCase())) return false;
                return true;
            });

            if (availableTags.length === 0) {
                return '<span class="eau-popover-no-tags">No tags available</span>';
            }

            let html = '';
            availableTags.forEach(tag => {
                html += `
                    <div class="eau-popover-tag-add" data-slug="${tag.slug}">
                        <span class="eau-popover-tag-color" style="background-color: ${tag.color}"></span>
                        <span class="eau-popover-tag-name">${this.escapeHtml(tag.name)}</span>
                        <i data-lucide="plus" class="eau-popover-tag-plus"></i>
                    </div>
                `;
            });
            return html;
        },

        /**
         * Fecha o popover de tags
         */
        closeTagsPopover: function() {
            $('.eau-tags-popover').remove();
        },

        /**
         * Atualiza os badges de tags na célula MEMBER da tabela
         */
        updateMemberTagsBadges: function(userId, tagSlugs) {
            const self = this;
            const $row = $(`.eau-table-row[data-id="${userId}"]`);
            const $memberCell = $row.find('td[data-label="MEMBER"] .eau-member-cell');

            if (!$memberCell.length) return;

            // Remove badges antigos
            $memberCell.find('.eau-member-tags-badges').remove();

            // Se não tem tags, não adiciona nada
            if (!tagSlugs || tagSlugs.length === 0) return;

            // Gera HTML dos novos badges
            let badgesHtml = '<div class="eau-member-tags-badges">';
            tagSlugs.forEach(slug => {
                const tag = self.availableTags.find(t => t.slug === slug);
                if (tag) {
                    badgesHtml += `<span class="eau-member-tag-badge" style="background-color: ${tag.color}">${self.escapeHtml(tag.name)}</span>`;
                }
            });
            badgesHtml += '</div>';

            // Adiciona após o nome
            $memberCell.append(badgesHtml);
        },

        /**
         * Filtra as tags no popover
         */
        filterPopoverTags: function(searchTerm) {
            const $popover = $('.eau-tags-popover');
            if (!$popover.length) return;

            const userId = $popover.data('user-id');

            // Pega tags atuais da row
            const $row = $(`.eau-table-row[data-id="${userId}"]`);
            const currentTagsStr = $row.find('.eau-action-tags').data('tags') || '';
            const currentTags = currentTagsStr ? currentTagsStr.toString().split(',').filter(s => s.trim()) : [];

            // Re-renderiza tags disponíveis com filtro
            const availableHtml = this.renderPopoverAvailableTags(currentTags, searchTerm);
            $popover.find('.eau-popover-available-tags').html(availableHtml);

            // Mostra/oculta botão de criar nova tag
            const $createSection = $popover.find('#eau-popover-create-tag');
            const trimmedSearch = (searchTerm || '').trim();

            if (trimmedSearch) {
                // Verifica se existe tag com esse nome exato
                const exactMatch = this.availableTags.some(tag =>
                    tag.name.toLowerCase() === trimmedSearch.toLowerCase()
                );

                if (!exactMatch) {
                    $popover.find('.eau-popover-new-tag-name').text(trimmedSearch);
                    $createSection.show();
                } else {
                    $createSection.hide();
                }
            } else {
                $createSection.hide();
            }

            // Re-inicializa Lucide
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        },

        /**
         * Adiciona uma tag a um membro (via AJAX)
         */
        addTagToMember: function(userId, slug) {
            const self = this;
            const $popover = $('.eau-tags-popover');
            const $row = $(`.eau-table-row[data-id="${userId}"]`);
            const $tagsBtn = $row.find('.eau-action-tags');

            // Pega tags atuais
            const currentTagsStr = $tagsBtn.data('tags') || '';
            let currentTags = currentTagsStr ? currentTagsStr.toString().split(',').filter(s => s.trim()) : [];

            // Adiciona nova tag
            if (!currentTags.includes(slug)) {
                currentTags.push(slug);
            }

            // Atualiza via AJAX
            $.ajax({
                url: eauMembersData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_update_member_tags',
                    nonce: eauMembersData.nonce,
                    user_id: userId,
                    tags: currentTags
                },
                success: function(response) {
                    if (response.success) {
                        // Atualiza data-tags no botão
                        $tagsBtn.data('tags', currentTags.join(','));

                        // Atualiza badges na tabela
                        self.updateMemberTagsBadges(userId, currentTags);

                        // Atualiza popover
                        $popover.find('.eau-popover-current-tags').html(
                            self.renderPopoverCurrentTags(currentTags)
                        );
                        $popover.find('.eau-popover-available-tags').html(
                            self.renderPopoverAvailableTags(currentTags, $popover.find('.eau-popover-tag-search').val())
                        );

                        // Re-inicializa Lucide
                        if (typeof lucide !== 'undefined') {
                            lucide.createIcons();
                        }

                        EauNotifications.success('Tag Added', 'Tag added to member.');
                    } else {
                        EauNotifications.error('Error', response.data.message || 'Failed to add tag.');
                    }
                },
                error: function() {
                    EauNotifications.error('Network Error', 'Failed to add tag.');
                }
            });
        },

        /**
         * Cria uma nova tag no popover e adiciona ao membro
         */
        createTagInPopover: function(tagName, userId) {
            const self = this;
            const $popover = $('.eau-tags-popover');
            const $row = $(`.eau-table-row[data-id="${userId}"]`);
            const $tagsBtn = $row.find('.eau-action-tags');

            // Desabilita o botão enquanto processa
            $popover.find('.eau-popover-create-btn').prop('disabled', true);

            $.ajax({
                url: eauMembersData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_add_member_tag',
                    nonce: eauMembersData.nonce,
                    name: tagName
                },
                success: function(response) {
                    if (response.success) {
                        const newTag = response.data.tag;

                        // Adiciona à lista de tags disponíveis (para uso futuro)
                        self.availableTags.push(newTag);

                        // Pega tags atuais do membro
                        const currentTagsStr = $tagsBtn.data('tags') || '';
                        let currentTags = currentTagsStr ? currentTagsStr.toString().split(',').filter(s => s.trim()) : [];

                        // Adiciona a nova tag à lista do membro
                        if (!currentTags.includes(newTag.slug)) {
                            currentTags.push(newTag.slug);
                        }

                        // Atualiza via AJAX
                        $.ajax({
                            url: eauMembersData.ajaxUrl,
                            type: 'POST',
                            data: {
                                action: 'eau_update_member_tags',
                                nonce: eauMembersData.nonce,
                                user_id: userId,
                                tags: currentTags
                            },
                            success: function(updateResponse) {
                                if (updateResponse.success) {
                                    // Atualiza data-tags no botão
                                    $tagsBtn.data('tags', currentTags.join(','));

                                    // Atualiza badges na tabela
                                    self.updateMemberTagsBadges(userId, currentTags);

                                    // Limpa o campo de busca
                                    $popover.find('.eau-popover-tag-search').val('');
                                    $popover.find('#eau-popover-create-tag').hide();

                                    // Atualiza popover - current tags e available tags
                                    $popover.find('.eau-popover-current-tags').html(
                                        self.renderPopoverCurrentTags(currentTags)
                                    );
                                    $popover.find('.eau-popover-available-tags').html(
                                        self.renderPopoverAvailableTags(currentTags, '')
                                    );

                                    // Re-inicializa Lucide
                                    if (typeof lucide !== 'undefined') {
                                        lucide.createIcons();
                                    }

                                    EauNotifications.success('Tag Created', `Tag "${newTag.name}" created and added.`);
                                }
                            }
                        });
                    } else {
                        EauNotifications.error('Error', response.data.message || 'Failed to create tag.');
                    }
                },
                error: function() {
                    EauNotifications.error('Network Error', 'Failed to create tag.');
                },
                complete: function() {
                    $popover.find('.eau-popover-create-btn').prop('disabled', false);
                }
            });
        },

        /**
         * Remove uma tag de um membro (via AJAX)
         */
        removeTagFromMember: function(userId, slug) {
            const self = this;
            const $popover = $('.eau-tags-popover');
            const $row = $(`.eau-table-row[data-id="${userId}"]`);
            const $tagsBtn = $row.find('.eau-action-tags');

            // Pega tags atuais
            const currentTagsStr = $tagsBtn.data('tags') || '';
            let currentTags = currentTagsStr ? currentTagsStr.toString().split(',').filter(s => s.trim()) : [];

            // Remove a tag
            currentTags = currentTags.filter(t => t !== slug);

            // Atualiza via AJAX
            $.ajax({
                url: eauMembersData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_update_member_tags',
                    nonce: eauMembersData.nonce,
                    user_id: userId,
                    tags: currentTags
                },
                success: function(response) {
                    if (response.success) {
                        // Atualiza data-tags no botão
                        $tagsBtn.data('tags', currentTags.join(','));

                        // Atualiza badges na tabela
                        self.updateMemberTagsBadges(userId, currentTags);

                        // Atualiza popover
                        $popover.find('.eau-popover-current-tags').html(
                            self.renderPopoverCurrentTags(currentTags)
                        );
                        $popover.find('.eau-popover-available-tags').html(
                            self.renderPopoverAvailableTags(currentTags, $popover.find('.eau-popover-tag-search').val())
                        );

                        // Re-inicializa Lucide
                        if (typeof lucide !== 'undefined') {
                            lucide.createIcons();
                        }

                        EauNotifications.success('Tag Removed', 'Tag removed from member.');
                    } else {
                        EauNotifications.error('Error', response.data.message || 'Failed to remove tag.');
                    }
                },
                error: function() {
                    EauNotifications.error('Network Error', 'Failed to remove tag.');
                }
            });
        },

        // ======================================
        // BULK TAGS MODAL
        // ======================================

        /**
         * Abre o modal de gerenciamento de tags em massa
         */
        openBulkTagsModal: function() {
            const self = this;
            const count = this.selectedIds.length;

            if (count === 0) {
                EauNotifications.warning('No Selection', 'Please select members first.');
                return;
            }

            // Remove modal existente se houver
            $('#eau-bulk-tags-modal-overlay').remove();

            // Cria HTML do modal
            let html = `
                <div class="eau-bulk-tags-modal-overlay" id="eau-bulk-tags-modal-overlay">
                    <div class="eau-bulk-tags-modal">
                        <div class="eau-bulk-tags-modal-header">
                            <h3>Manage Tags for ${count} Member${count > 1 ? 's' : ''}</h3>
                            <button type="button" class="eau-bulk-tags-modal-close" id="eau-bulk-tags-close-btn">
                                <i data-lucide="x"></i>
                            </button>
                        </div>
                        <div class="eau-bulk-tags-modal-body">
                            <div class="eau-bulk-tags-tabs">
                                <button type="button" class="eau-bulk-tags-tab active" data-tab="add">
                                    <i data-lucide="plus-circle"></i> Add Tags
                                </button>
                                <button type="button" class="eau-bulk-tags-tab" data-tab="remove">
                                    <i data-lucide="minus-circle"></i> Remove Tags
                                </button>
                            </div>
                            <div class="eau-bulk-tags-tab-content" id="eau-bulk-tags-tab-add">
                                <p class="eau-bulk-tags-description">Select tags to add to all selected members:</p>
                                <div class="eau-bulk-tags-list">
                                    ${this.renderBulkTagsCheckboxes()}
                                </div>
                            </div>
                            <div class="eau-bulk-tags-tab-content" id="eau-bulk-tags-tab-remove" style="display: none;">
                                <p class="eau-bulk-tags-description">Select tags to remove from all selected members:</p>
                                <div class="eau-bulk-tags-remove-all-option">
                                    <label class="eau-bulk-tag-checkbox eau-bulk-tag-remove-all">
                                        <input type="checkbox" id="eau-bulk-remove-all-tags">
                                        <span class="eau-bulk-tag-color" style="background-color: #dc2626"></span>
                                        <span class="eau-bulk-tag-name"><strong>Remove ALL tags</strong></span>
                                    </label>
                                </div>
                                <div class="eau-bulk-tags-divider"></div>
                                <div class="eau-bulk-tags-list">
                                    ${this.renderBulkTagsCheckboxes()}
                                </div>
                            </div>
                        </div>
                        <div class="eau-bulk-tags-modal-footer">
                            <button type="button" class="eau-btn eau-btn-secondary" id="eau-bulk-tags-cancel-btn">
                                Cancel
                            </button>
                            <button type="button" class="eau-btn eau-btn-primary" id="eau-bulk-tags-apply-btn">
                                <i data-lucide="check"></i>
                                Apply Changes
                            </button>
                        </div>
                    </div>
                </div>
            `;

            // Adiciona modal ao body
            $('body').append(html);

            // Referência ao modal
            const $overlay = $('#eau-bulk-tags-modal-overlay');
            const $modal = $overlay.find('.eau-bulk-tags-modal');

            // Bind eventos diretamente nos elementos do modal
            // Tabs
            $modal.find('.eau-bulk-tags-tab').on('click', function() {
                const tab = $(this).data('tab');
                $modal.find('.eau-bulk-tags-tab').removeClass('active');
                $(this).addClass('active');
                $modal.find('.eau-bulk-tags-tab-content').hide();
                $modal.find(`#eau-bulk-tags-tab-${tab}`).show();
            });

            // Botão Apply
            $('#eau-bulk-tags-apply-btn').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                self.applyBulkTags();
            });

            // Botão Cancel
            $('#eau-bulk-tags-cancel-btn').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                self.closeBulkTagsModal();
            });

            // Botão Close (X)
            $('#eau-bulk-tags-close-btn').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                self.closeBulkTagsModal();
            });

            // Fechar ao clicar no overlay
            $overlay.on('click', function(e) {
                if ($(e.target).is($overlay)) {
                    self.closeBulkTagsModal();
                }
            });

            // "Remove All" checkbox - seleciona/deseleciona todas as tags
            $('#eau-bulk-remove-all-tags').on('change', function() {
                const isChecked = $(this).is(':checked');
                $('#eau-bulk-tags-tab-remove .eau-bulk-tags-list input[type="checkbox"]').prop('checked', isChecked);
            });

            // Anima entrada
            setTimeout(function() {
                $overlay.addClass('active');
            }, 10);

            // Re-inicializa Lucide
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        },

        /**
         * Renderiza checkboxes de tags para bulk modal
         */
        renderBulkTagsCheckboxes: function() {
            if (this.availableTags.length === 0) {
                return '<span class="eau-bulk-tags-empty">No tags available</span>';
            }

            let html = '';
            this.availableTags.forEach(tag => {
                html += `
                    <label class="eau-bulk-tag-checkbox">
                        <input type="checkbox" name="bulk_tags[]" value="${tag.slug}">
                        <span class="eau-bulk-tag-color" style="background-color: ${tag.color}"></span>
                        <span class="eau-bulk-tag-name">${this.escapeHtml(tag.name)}</span>
                    </label>
                `;
            });
            return html;
        },

        /**
         * Fecha o modal de bulk tags
         */
        closeBulkTagsModal: function() {
            const $overlay = $('#eau-bulk-tags-modal-overlay');
            $overlay.removeClass('active');
            setTimeout(function() {
                $overlay.remove();
            }, 200);
        },

        /**
         * Aplica as tags em massa
         */
        applyBulkTags: function() {
            const self = this;
            const $modal = $('#eau-bulk-tags-modal-overlay .eau-bulk-tags-modal');

            // Determina se é Add ou Remove baseado na tab ativa
            const isRemove = $modal.find('.eau-bulk-tags-tab[data-tab="remove"]').hasClass('active');

            // Verifica se "Remove All" está marcado
            const removeAll = isRemove && $('#eau-bulk-remove-all-tags').is(':checked');

            // Pega tags selecionadas da tab ativa
            const activeTabId = isRemove ? '#eau-bulk-tags-tab-remove' : '#eau-bulk-tags-tab-add';
            const selectedTags = [];
            $modal.find(`${activeTabId} .eau-bulk-tags-list input[name="bulk_tags[]"]:checked`).each(function() {
                selectedTags.push($(this).val());
            });

            // Se não é removeAll e não tem tags selecionadas, mostra warning
            if (!removeAll && selectedTags.length === 0) {
                EauNotifications.warning('No Tags Selected', 'Please select at least one tag.');
                return;
            }

            // Define ação baseado em removeAll ou isRemove
            const action = removeAll ? 'eau_bulk_remove_all_tags' : (isRemove ? 'eau_bulk_remove_tags' : 'eau_bulk_add_tags');

            const memberIds = this.selectedIds;
            const totalCount = memberIds.length;
            const batchSize = 50;
            let processedCount = 0;
            let successCount = 0;
            let failedCount = 0;

            // Fecha modal
            this.closeBulkTagsModal();

            // Cria notificação de progresso
            const actionText = isRemove ? 'Removing tags' : 'Adding tags';
            const progressNotification = EauNotifications.info(
                `${actionText}...`,
                `Processing 0 of ${totalCount} members...`,
                { duration: 0 }
            );

            // Função para processar próximo lote
            function processNextBatch() {
                if (processedCount >= totalCount) {
                    // Finalizado
                    EauNotifications.close(progressNotification);

                    const doneText = isRemove ? 'removed from' : 'added to';
                    let message = `Tags ${doneText} ${successCount} member${successCount !== 1 ? 's' : ''}.`;
                    if (failedCount > 0) {
                        message += ` ${failedCount} failed.`;
                    }

                    EauNotifications.success('Completed!', message);

                    // Limpa seleção e recarrega tabela
                    self.selectedIds = [];
                    $('#members-table-select-all').prop('checked', false);
                    self.loadMembers();
                    return;
                }

                // Pega próximo lote
                const batch = memberIds.slice(processedCount, processedCount + batchSize);

                $.ajax({
                    url: eauMembersData.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: action,
                        nonce: eauMembersData.nonce,
                        member_ids: batch,
                        tags: selectedTags
                    },
                    success: function(response) {
                        if (response.success) {
                            successCount += response.data.updated_count || batch.length;
                            failedCount += response.data.failed_count || 0;
                        } else {
                            failedCount += batch.length;
                            // Log error for debugging
                            console.error('Bulk tags error:', response.data ? response.data.message : 'Unknown error');
                        }

                        processedCount += batch.length;

                        // Atualiza progresso
                        const percentage = Math.round((processedCount / totalCount) * 100);
                        EauNotifications.update(progressNotification, {
                            message: `Processing ${processedCount} of ${totalCount} members... (${percentage}%)`
                        });

                        // Processa próximo lote
                        processNextBatch();
                    },
                    error: function(xhr, status, error) {
                        failedCount += batch.length;
                        processedCount += batch.length;
                        console.error('AJAX error:', status, error);
                        processNextBatch();
                    }
                });
            }

            // Inicia processamento
            processNextBatch();
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
