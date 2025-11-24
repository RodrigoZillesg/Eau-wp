/**
 * Activities Management JavaScript
 */
(function($) {
    'use strict';

    const ActivitiesManagement = {
        currentPage: 1,
        perPage: 20,
        orderBy: 'post_date',
        order: 'DESC',
        search: '',
        filters: {},
        selectedIds: [],

        /**
         * Inicializa o módulo
         */
        init: function() {
            this.bindEvents();
            this.loadActivities();
            this.loadStats();
        },

        /**
         * Bind eventos
         */
        bindEvents: function() {
            const self = this;

            // Search
            $('#eau-activities-search').on('keyup', this.debounce(function() {
                self.search = $(this).val();
                self.currentPage = 1;
                self.loadActivities();
            }, 500));

            // Filters toggle
            $('#eau-filters-toggle').on('click', function(e) {
                e.preventDefault();
                $('#activities-filters').slideToggle(300);
            });

            // Apply filters
            $(document).on('click', '.eau-filters-apply', function(e) {
                self.handleApplyFilters(e);
            });

            // Clear filters
            $(document).on('click', '.eau-filters-clear', function(e) {
                self.handleClearFilters(e);
            });

            // Sort
            $(document).on('click', '.eau-sortable', function() {
                const columnKey = $(this).data('key');
                self.handleSort(columnKey);
            });

            // Select all
            $(document).on('change', '#eau-select-all-activities', function() {
                const checked = $(this).is(':checked');
                $('.eau-row-checkbox').prop('checked', checked);
                self.updateSelectedIds();
            });

            // Select row
            $(document).on('change', '.eau-row-checkbox', function() {
                self.updateSelectedIds();
            });

            // View activity
            $(document).on('click', '.eau-action-view', function(e) {
                e.preventDefault();
                const activityId = $(this).data('id');
                self.viewActivity(activityId);
            });

            // Edit activity
            $(document).on('click', '.eau-action-edit', function(e) {
                e.preventDefault();
                const activityId = $(this).data('id');
                self.editActivity(activityId);
            });

            // Delete activity
            $(document).on('click', '.eau-action-delete', function(e) {
                e.preventDefault();
                const activityId = $(this).data('id');
                self.deleteActivity(activityId);
            });

            // Bulk Delete (apenas para super admin)
            if (eauActivitiesData.isSuperAdmin) {
                $('#eau-bulk-delete-activities').on('click', this.handleBulkDelete.bind(this));
            }

            // Add activity
            $('#eau-add-activity').on('click', function(e) {
                self.handleAddActivity(e);
            });

            // Export CSV
            $('#eau-export-activities-csv').on('click', function(e) {
                self.handleExportCSV(e);
            });

            // Save activity
            $(document).on('click', '[data-action="save"]', function(e) {
                e.preventDefault();
                const modalId = $(this).closest('.eau-modal').attr('id');
                self.saveActivity(modalId);
            });
        },

        /**
         * Load activities
         */
        loadActivities: function() {
            const self = this;

            // Mostra loading
            this.showLoading();

            $.ajax({
                url: eauActivitiesData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_get_activities',
                    nonce: eauActivitiesData.nonce,
                    page: this.currentPage,
                    per_page: this.perPage,
                    search: this.search,
                    orderby: this.orderBy,
                    order: this.order,
                    ...this.filters
                },
                success: function(response) {
                    if (response.success) {
                        self.renderActivities(response.data);
                        self.renderPagination(response.data);
                        self.updateCounter(response.data.total);
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
         * Load stats cards
         */
        loadStats: function() {
            const self = this;

            $.ajax({
                url: eauActivitiesData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_get_activities_stats',
                    nonce: eauActivitiesData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.updateStatsCards(response.data);
                    }
                },
                error: function() {
                    // Silently fail - stats are not critical
                    console.error('Failed to load stats');
                }
            });
        },

        /**
         * Update stats cards with new values
         */
        updateStatsCards: function(stats) {
            // Update Total Activities
            $('.eau-stat-card').eq(0).find('.eau-stat-number').text(stats.total);

            // Update Verified Activities
            $('.eau-stat-card').eq(1).find('.eau-stat-number').text(stats.verified);

            // Update Pending Verification
            $('.eau-stat-card').eq(2).find('.eau-stat-number').text(stats.pending);

            // Update Total Points
            $('.eau-stat-card').eq(3).find('.eau-stat-number').text(stats.total_points);
        },

        /**
         * Renderiza activities na tabela
         */
        renderActivities: function(data) {
            const tbody = $('#activities-table-tbody');

            if (data.rows.length === 0) {
                tbody.html(this.getEmptyState());
                return;
            }

            let html = '';
            data.rows.forEach(function(row) {
                html += `
                    <tr class="eau-table-tr">
                        <td class="eau-table-td eau-table-td-checkbox">
                            <input type="checkbox" class="eau-row-checkbox" value="${row._id}">
                        </td>
                        <td class="eau-table-td">${row.activity}</td>
                        <td class="eau-table-td">${row.member}</td>
                        <td class="eau-table-td">${row.institution}</td>
                        <td class="eau-table-td">${row.category}</td>
                        <td class="eau-table-td">${row.hours}</td>
                        <td class="eau-table-td">${row.points}</td>
                        <td class="eau-table-td">${row.status}</td>
                        <td class="eau-table-td">${row.date}</td>
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

            // Re-initialize Lucide icons
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
                    <td colspan="10" style="text-align: center; padding: 3rem;">
                        <i data-lucide="inbox" style="width: 3rem; height: 3rem; color: #d1d5db; margin-bottom: 1rem;"></i>
                        <p style="color: #6b7280; margin: 0;">No activities found</p>
                    </td>
                </tr>
            `;
        },

        /**
         * Renderiza paginação
         */
        renderPagination: function(data) {
            const self = this;
            const container = $('#eau-activities-pagination');
            const totalPages = data.total_pages || 1;

            if (totalPages <= 1) {
                container.html('');
                return;
            }

            const startItem = ((data.page - 1) * data.per_page) + 1;
            const endItem = Math.min(data.page * data.per_page, data.total);

            const html = this.buildPaginationHTML(data.page, totalPages, startItem, endItem, data.total);
            container.html(html);

            // Bind pagination click events
            $(document).off('click', '.eau-pagination-btn').on('click', '.eau-pagination-btn', function(e) {
                e.preventDefault();
                if ($(this).is(':disabled') || $(this).hasClass('eau-pagination-active')) {
                    return;
                }

                const page = parseInt($(this).data('page'));
                self.currentPage = page;
                self.loadActivities();

                // Scroll to top of table
                $('html, body').animate({
                    scrollTop: $('.eau-activities-management-container').offset().top - 100
                }, 300);
            });

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

            let html = '<div class="eau-pagination-wrapper" id="activities-pagination">';

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
            const delta = 2; // Páginas ao redor da atual
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
         * Update counter
         */
        updateCounter: function(total) {
            $('#activities-table-count .eau-count-number').text(total);
        },

        /**
         * Handle sort by column
         */
        handleSort: function(columnKey) {
            // Mapeamento de colunas para campos de ordenação
            const columnMap = {
                'activity': 'post_title',
                'member': 'member_name',
                'institution': 'institution_name',
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

            // Recarrega activities
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
         * Update selected IDs
         */
        updateSelectedIds: function() {
            const self = this;
            this.selectedIds = [];

            $('.eau-row-checkbox:checked').each(function() {
                self.selectedIds.push($(this).val());
            });

            // Mostrar/ocultar botão de deleção em massa (apenas para super admin)
            if (eauActivitiesData.isSuperAdmin) {
                if (this.selectedIds.length > 0) {
                    $('#eau-bulk-delete-activities').show();
                } else {
                    $('#eau-bulk-delete-activities').hide();
                }
            }
        },

        /**
         * Show loading overlay
         */
        showLoading: function() {
            $('#activities-table-loading').show();
        },

        /**
         * Hide loading overlay
         */
        hideLoading: function() {
            $('#activities-table-loading').hide();
        },

        /**
         * View activity
         */
        viewActivity: function(activityId) {
            const self = this;

            // Abre modal e carrega dados
            this.openModal('eau-modal-view');
            this.loadActivityDetails(activityId, 'view');
        },

        /**
         * Edit activity
         */
        editActivity: function(activityId) {
            const self = this;

            // Abre modal e carrega dados
            this.openModal('eau-modal-edit');
            this.loadActivityDetails(activityId, 'edit');
        },

        /**
         * Delete activity
         */
        deleteActivity: function(activityId) {
            const self = this;

            // Show confirm modal
            EauNotifications.confirm({
                title: 'Delete Activity?',
                message: 'Are you sure you want to delete this activity? This action cannot be undone.',
                type: 'danger',
                confirmText: 'Delete',
                cancelText: 'Cancel',
                onConfirm: function() {
                    $.ajax({
                        url: eauActivitiesData.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'eau_delete_activity',
                            nonce: eauActivitiesData.nonce,
                            activity_id: activityId
                        },
                        success: function(response) {
                            if (response.success) {
                                EauNotifications.success('Success', 'Activity deleted successfully');
                                self.loadActivities(); // Reload table
                                self.loadStats(); // Reload stats cards
                            } else {
                                EauNotifications.error('Error', response.data.message || 'Failed to delete activity');
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
                action: eauActivitiesData.ajaxUrl
            });

            form.append($('<input>', { type: 'hidden', name: 'action', value: 'eau_export_activities_csv' }));
            form.append($('<input>', { type: 'hidden', name: 'nonce', value: eauActivitiesData.nonce }));
            form.append($('<input>', { type: 'hidden', name: 'export_type', value: exportType }));

            if (exportType === 'selected') {
                this.selectedIds.forEach(function(id) {
                    form.append($('<input>', { type: 'hidden', name: 'selected_ids[]', value: id }));
                });
            }

            form.appendTo('body').submit().remove();
        },

        /**
         * Handle add activity
         */
        handleAddActivity: function(e) {
            e.preventDefault();

            // Abre modal vazio para adicionar
            this.openModal('eau-modal-add');
            this.loadAddActivityForm();
        },

        /**
         * Handle apply filters
         */
        handleApplyFilters: function(e) {
            if (e) e.preventDefault();

            const self = this;
            this.filters = {};

            // Coleta valores de todos os filtros
            $('#activities-filters [data-filter]').each(function() {
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
            this.loadActivities();
        },

        /**
         * Handle clear filters
         */
        handleClearFilters: function(e) {
            e.preventDefault();

            // Limpa todos os inputs de filtro
            $('#activities-filters [data-filter]').val('');

            // Limpa o objeto de filtros
            this.filters = {};

            // Mostra skeleton durante limpeza
            this.showLoading();

            // Reset para primeira página e recarrega
            this.currentPage = 1;
            this.loadActivities();
        },

        /**
         * Open modal
         */
        openModal: function(modalId) {
            const $overlay = $('#' + modalId + '-overlay');
            $overlay.css('display', 'flex').hide().fadeIn(200);
            $('body').addClass('eau-modal-open');

            // Bind close button
            const self = this;
            $overlay.find('[data-modal-action="close"]').off('click').on('click', function() {
                self.closeModal(modalId);
            });

            // Close on overlay click (outside modal)
            $overlay.off('click').on('click', function(e) {
                if ($(e.target).hasClass('eau-modal-overlay')) {
                    self.closeModal(modalId);
                }
            });
        },

        /**
         * Close modal
         */
        closeModal: function(modalId) {
            const $overlay = $('#' + modalId + '-overlay');
            $overlay.fadeOut(200, function() {
                $(this).css('display', 'none');
            });
            $('body').removeClass('eau-modal-open');
        },

        /**
         * Load activity details
         */
        loadActivityDetails: function(activityId, mode) {
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
                </div>
            `);

            $.ajax({
                url: eauActivitiesData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_get_activity_details',
                    nonce: eauActivitiesData.nonce,
                    activity_id: activityId
                },
                success: function(response) {
                    if (response.success) {
                        self.renderActivityForm(modalId, response.data, mode);
                    } else {
                        alert('Failed to load activity details');
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
         * Load Add Activity Form
         */
        loadAddActivityForm: function() {
            const emptyData = {
                _ID: '',
                post_title: '',
                post_content: '',
                act_hours: '',
                act_verified: '0'
            };

            this.renderActivityForm('eau-modal-add', emptyData, 'add');
        },

        /**
         * Render activity form
         */
        renderActivityForm: function(modalId, data, mode) {
            const isView = mode === 'view';
            const isAdd = mode === 'add';
            const readonly = isView ? 'readonly' : '';
            const requiredAttr = !isView ? 'required' : '';

            let html = '<form class="eau-modal-form" id="eau-activity-form">';
            html += '<div class="eau-form-grid">';

            // Hidden activity ID
            if (data._ID) {
                html += `<input type="hidden" name="activity_id" value="${data._ID}">`;
            }

            // Activity Title
            html += `
                <div class="eau-form-field eau-form-field-span-2">
                    <label class="eau-form-label">Activity Title ${!isView ? '<span class="eau-form-required">*</span>' : ''}</label>
                    <input type="text" class="eau-form-input" name="post_title" value="${data.post_title || ''}" ${readonly} ${requiredAttr}>
                </div>
            `;

            // Hours
            html += `
                <div class="eau-form-field">
                    <label class="eau-form-label">Hours ${!isView ? '<span class="eau-form-required">*</span>' : ''}</label>
                    <input type="number" step="0.01" class="eau-form-input" name="act_hours" value="${data.act_hours || ''}" ${readonly} ${requiredAttr}>
                </div>
            `;

            // Verified Status
            html += `
                <div class="eau-form-field">
                    <label class="eau-form-label">Verification Status</label>
            `;
            if (!isView) {
                html += `
                    <select class="eau-form-select" name="act_verified">
                        <option value="0" ${data.act_verified !== '1' ? 'selected' : ''}>Pending</option>
                        <option value="1" ${data.act_verified === '1' ? 'selected' : ''}>Verified</option>
                    </select>
                `;
            } else {
                html += `<input type="text" class="eau-form-input" value="${data.act_verified === '1' ? 'Verified' : 'Pending'}" readonly>`;
            }
            html += `</div>`;

            // Member info (view only)
            if (mode === 'view' || mode === 'edit') {
                html += `
                    <div class="eau-form-field">
                        <label class="eau-form-label">Member</label>
                        <input type="text" class="eau-form-input" value="${data.member_name || ''}" readonly>
                    </div>
                    <div class="eau-form-field">
                        <label class="eau-form-label">Email</label>
                        <input type="text" class="eau-form-input" value="${data.member_email || ''}" readonly>
                    </div>
                `;
            }

            // Description
            html += `
                <div class="eau-form-field eau-form-field-span-2">
                    <label class="eau-form-label">Description</label>
                    <textarea class="eau-form-textarea" name="post_content" rows="4" ${readonly}>${data.post_content || ''}</textarea>
                </div>
            `;

            html += '</div></form>';

            $('#' + modalId + '-body').html(html);

            // Re-initialize Lucide icons
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        },

        /**
         * Save Activity (Edit or Add)
         */
        saveActivity: function(modalId) {
            const self = this;
            const $form = $('#eau-activity-form');

            // Valida form
            if (!$form[0].checkValidity()) {
                $form[0].reportValidity();
                return;
            }

            const formData = $form.serializeArray();
            const fields = {};

            // Converte para objeto
            formData.forEach(function(item) {
                if (item.name !== 'activity_id') {
                    fields[item.name] = item.value;
                }
            });

            const activityId = $form.find('input[name="activity_id"]').val();
            const action = activityId ? 'eau_update_activity' : 'eau_create_activity';

            const ajaxData = {
                action: action,
                nonce: eauActivitiesData.nonce
            };

            if (activityId) {
                ajaxData.activity_id = activityId;
                ajaxData.fields = fields;
            } else {
                Object.assign(ajaxData, fields);
            }

            $.ajax({
                url: eauActivitiesData.ajaxUrl,
                type: 'POST',
                data: ajaxData,
                success: function(response) {
                    if (response.success) {
                        EauNotifications.success('Success', response.data.message);
                        self.closeModal(modalId);
                        self.loadActivities(); // Reload table
                        self.loadStats(); // Reload stats cards
                    } else {
                        EauNotifications.error('Error', response.data.message || 'Failed to save activity');
                    }
                },
                error: function() {
                    EauNotifications.error('Network Error', 'Please try again.');
                }
            });
        },

        /**
         * Handle bulk delete
         */
        handleBulkDelete: function() {
            const self = this;

            if (this.selectedIds.length === 0) {
                EauNotifications.warning('No Selection', 'Please select activities to delete.');
                return;
            }

            const count = this.selectedIds.length;
            EauNotifications.confirm({
                title: 'Delete Activities?',
                message: `Are you sure you want to delete ${count} activity(ies)? This action cannot be undone.`,
                type: 'danger',
                confirmText: 'Delete',
                cancelText: 'Cancel',
                onConfirm: function() {
                    $.ajax({
                        url: eauActivitiesData.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'eau_bulk_delete_activities',
                            nonce: eauActivitiesData.nonce,
                            ids: self.selectedIds
                        },
                        success: function(response) {
                            if (response.success) {
                                EauNotifications.success('Deleted!', response.data.message);
                                self.selectedIds = [];
                                self.loadActivities();
                                $('#eau-bulk-delete-activities').hide();
                            } else {
                                EauNotifications.error('Error', response.data.message || 'Failed to delete activities');
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

    // Initialize when document is ready
    $(document).ready(function() {
        if ($('.eau-activities-management-container').length) {
            ActivitiesManagement.init();
        }
    });

})(jQuery);
