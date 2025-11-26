/**
 * EAU System - Categories Management JS
 * Version: 1.31.0
 */

(function($) {
    'use strict';

    /**
     * Categories Management Controller
     */
    const EauCategoriesManagement = {

        // State
        currentPage: 1,
        perPage: 20,
        searchTerm: '',
        selectedIds: [],
        orderBy: 'category_name',
        order: 'ASC',

        /**
         * Inicializa
         */
        init: function() {
            this.bindEvents();
            this.loadCategories();
        },

        /**
         * Vincula eventos
         */
        bindEvents: function() {
            const self = this;

            // Search
            $('#eau-categories-search').on('input', this.debounce(function(e) {
                self.searchTerm = $(e.target).val();
                self.currentPage = 1;
                self.loadCategories();
            }, 300));

            // Add Category
            $('#eau-add-category').on('click', this.handleAddCategory.bind(this));

            // Refresh Categories
            $('#eau-refresh-categories').on('click', this.handleRefreshCategories.bind(this));

            // Table actions
            $(document).on('click', '.eau-action-view', this.handleViewCategory.bind(this));
            $(document).on('click', '.eau-action-edit', this.handleEditCategory.bind(this));
            $(document).on('click', '.eau-action-delete', this.handleDeleteCategory.bind(this));

            // Modal close
            $(document).on('click', '.eau-modal-close, [data-action="close"]', this.handleCloseModal.bind(this));

            // Modal save
            $(document).on('click', '[data-action="save"]', this.handleSaveCategory.bind(this));

            // Sortable columns
            $(document).on('click', '.eau-table-th.eau-sortable', this.handleSort.bind(this));

            // Select all
            $(document).on('change', '#categories-table-select-all', function() {
                const checked = $(this).is(':checked');
                $('.eau-row-checkbox').prop('checked', checked);
                self.updateSelectedIds();
            });

            // Select row
            $(document).on('change', '.eau-row-checkbox', function() {
                self.updateSelectedIds();
            });
        },

        /**
         * Update selected IDs
         */
        updateSelectedIds: function() {
            this.selectedIds = [];
            $('.eau-row-checkbox:checked').each((i, el) => {
                this.selectedIds.push($(el).val());
            });
        },

        /**
         * Handle sort
         */
        handleSort: function(e) {
            const $th = $(e.currentTarget);
            const column = $th.data('key');

            if (this.orderBy === column) {
                this.order = this.order === 'ASC' ? 'DESC' : 'ASC';
            } else {
                this.orderBy = column;
                this.order = 'ASC';
            }

            this.loadCategories();
        },

        /**
         * Load categories
         */
        loadCategories: function() {
            const self = this;

            // Show skeleton
            this.showSkeleton();

            $.ajax({
                url: eauCategoriesData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_get_categories',
                    nonce: eauCategoriesData.nonce,
                    page: this.currentPage,
                    per_page: this.perPage,
                    search: this.searchTerm,
                    orderby: this.orderBy,
                    order: this.order
                },
                success: function(response) {
                    if (response.success) {
                        self.renderCategories(response.data.categories, response.data.pagination);
                        self.renderPagination(response.data.pagination);
                    } else {
                        EauNotifications.error('Error', response.data.message || 'Failed to load categories');
                    }
                },
                error: function() {
                    EauNotifications.error('Error', 'Failed to load categories');
                },
                complete: function() {
                    self.hideSkeleton();
                    lucide.createIcons();
                }
            });
        },

        /**
         * Load statistics
         */
        loadStats: function() {
            const self = this;

            $.ajax({
                url: eauCategoriesData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_get_categories_stats',
                    nonce: eauCategoriesData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.updateStatsCards(response.data);
                    }
                }
            });
        },

        /**
         * Update stats cards
         */
        updateStatsCards: function(stats) {
            // Update Total Categories
            $('.eau-stat-card').eq(0).find('.eau-stat-number').text(stats.total);

            // Update Configured
            $('.eau-stat-card').eq(1).find('.eau-stat-number').text(stats.configured);

            // Update Not Configured
            $('.eau-stat-card').eq(2).find('.eau-stat-number').text(stats.not_configured);

            // Update Avg Points/Hour
            $('.eau-stat-card').eq(3).find('.eau-stat-number').text(stats.avg_points);
        },

        /**
         * Render categories
         */
        renderCategories: function(categories, pagination) {
            const tbody = $('#categories-table tbody');
            tbody.empty();

            if (categories.length === 0) {
                tbody.html(`
                    <tr class="eau-table-empty">
                        <td colspan="6" class="eau-table-td" style="text-align: center;">
                            <div class="eau-empty-state">
                                <i data-lucide="inbox"></i>
                                <p>No categories found</p>
                            </div>
                        </td>
                    </tr>
                `);
                $('.eau-table-info').text('0 total items');
                return;
            }

            categories.forEach(category => {
                const pointsBadgeClass = category.points_per_hour_raw > 0 ? 'eau-points-badge' : 'eau-points-badge eau-points-badge-zero';

                const row = `
                    <tr class="eau-table-tr">
                        <td class="eau-table-td">
                            <input type="checkbox" class="eau-row-checkbox" value="${category.id}">
                        </td>
                        <td class="eau-table-td">
                            <span class="eau-category-serial">${category.category_serial}</span>
                        </td>
                        <td class="eau-table-td">${category.category_name}</td>
                        <td class="eau-table-td">
                            <span class="${pointsBadgeClass}">${category.points_per_hour}</span>
                        </td>
                        <td class="eau-table-td">${category.updated_at}</td>
                        <td class="eau-table-td">
                            <div class="eau-table-actions">
                                <button class="eau-action-btn eau-action-view" data-id="${category.id}" title="View">
                                    <i data-lucide="eye"></i>
                                </button>
                                <button class="eau-action-btn eau-action-edit" data-id="${category.id}" title="Edit">
                                    <i data-lucide="edit"></i>
                                </button>
                                <button class="eau-action-btn eau-action-delete" data-id="${category.id}" title="Delete">
                                    <i data-lucide="trash-2"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
                tbody.append(row);
            });

            $('.eau-table-info').text(`${pagination.total} total items`);
        },

        /**
         * Render pagination
         */
        renderPagination: function(pagination) {
            const totalPages = pagination.total_pages || 1;

            if (totalPages <= 1) {
                $('#eau-categories-pagination').html('');
                return;
            }

            const startItem = ((pagination.page - 1) * pagination.per_page) + 1;
            const endItem = Math.min(pagination.page * pagination.per_page, pagination.total);

            const html = this.buildPaginationHTML(pagination.page, totalPages, startItem, endItem, pagination.total);
            $('#eau-categories-pagination').html(html);

            // Bind pagination click events
            const self = this;
            $(document).off('click', '.eau-pagination-btn').on('click', '.eau-pagination-btn', function(e) {
                e.preventDefault();
                if ($(this).is(':disabled') || $(this).hasClass('eau-pagination-active')) {
                    return;
                }

                const page = parseInt($(this).data('page'));
                self.currentPage = page;
                self.loadCategories();

                // Scroll to top of table
                $('html, body').animate({
                    scrollTop: $('.eau-categories-management-container').offset().top - 100
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

            let html = '<div class="eau-pagination-wrapper" id="categories-pagination">';

            // Info
            html += '<div class="eau-pagination-info">';
            html += `Showing ${startItem.toLocaleString()} to ${endItem.toLocaleString()} of ${total.toLocaleString()} categories`;
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
         * Get pages to show
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
         * Handle add category
         */
        handleAddCategory: function(e) {
            e.preventDefault();
            this.openModal('eau-modal-add');
            this.loadAddForm();
        },

        /**
         * Handle refresh categories
         */
        handleRefreshCategories: function(e) {
            e.preventDefault();
            const self = this;

            $.ajax({
                url: eauCategoriesData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_sync_categories',
                    nonce: eauCategoriesData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        const data = response.data;
                        let message = `Found ${data.total_found} categories. `;
                        message += `Added ${data.added} new, skipped ${data.skipped} existing.`;

                        EauNotifications.success('Sync Complete', message);
                        self.loadCategories();
                        self.loadStats();
                    } else {
                        EauNotifications.error('Error', response.data.message || 'Failed to sync categories');
                    }
                },
                error: function() {
                    EauNotifications.error('Error', 'Failed to sync categories');
                }
            });
        },

        /**
         * Handle view category
         */
        handleViewCategory: function(e) {
            e.preventDefault();
            const categoryId = $(e.currentTarget).data('id');
            this.openModal('eau-modal-view');
            this.loadCategoryDetails(categoryId, 'view');
        },

        /**
         * Handle edit category
         */
        handleEditCategory: function(e) {
            e.preventDefault();
            const categoryId = $(e.currentTarget).data('id');
            this.openModal('eau-modal-edit');
            this.loadCategoryDetails(categoryId, 'edit');
        },

        /**
         * Handle delete category
         */
        handleDeleteCategory: function(e) {
            e.preventDefault();
            const categoryId = $(e.currentTarget).data('id');
            const self = this;

            EauNotifications.confirm({
                title: 'Delete Category?',
                message: 'Are you sure you want to delete this category? This action cannot be undone.',
                type: 'danger',
                confirmText: 'Delete',
                cancelText: 'Cancel',
                onConfirm: function() {
                    $.ajax({
                        url: eauCategoriesData.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'eau_delete_category',
                            nonce: eauCategoriesData.nonce,
                            id: categoryId
                        },
                        success: function(response) {
                            if (response.success) {
                                EauNotifications.success('Success', 'Category deleted successfully');
                                self.loadCategories();
                                self.loadStats();
                            } else {
                                EauNotifications.error('Error', response.data.message || 'Failed to delete category');
                            }
                        },
                        error: function() {
                            EauNotifications.error('Error', 'Failed to delete category');
                        }
                    });
                }
            });
        },

        /**
         * Load category details
         */
        loadCategoryDetails: function(categoryId, mode) {
            const self = this;
            const modalId = mode === 'view' ? 'eau-modal-view' : 'eau-modal-edit';

            $.ajax({
                url: eauCategoriesData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_get_category',
                    nonce: eauCategoriesData.nonce,
                    id: categoryId
                },
                success: function(response) {
                    if (response.success) {
                        if (mode === 'view') {
                            self.renderViewForm(response.data);
                        } else {
                            self.renderEditForm(response.data);
                        }
                    } else {
                        EauNotifications.error('Error', response.data.message || 'Failed to load category');
                        self.closeModal(modalId);
                    }
                },
                error: function() {
                    EauNotifications.error('Error', 'Failed to load category');
                    self.closeModal(modalId);
                }
            });
        },

        /**
         * Render view form
         */
        renderViewForm: function(category) {
            const html = `
                <form class="eau-modal-form">
                    <div class="eau-form-grid">
                        <div class="eau-form-field">
                            <label class="eau-form-label">Category ID</label>
                            <input type="text" class="eau-form-input" value="${category.category_serial}" readonly>
                        </div>
                        <div class="eau-form-field">
                            <label class="eau-form-label">Category Name</label>
                            <input type="text" class="eau-form-input" value="${category.category_name}" readonly>
                        </div>
                        <div class="eau-form-field">
                            <label class="eau-form-label">Points per Hour</label>
                            <input type="text" class="eau-form-input" value="${category.points_per_hour}" readonly>
                        </div>
                        <div class="eau-form-field">
                            <label class="eau-form-label">Created At</label>
                            <input type="text" class="eau-form-input" value="${category.created_at || 'N/A'}" readonly>
                        </div>
                        <div class="eau-form-field">
                            <label class="eau-form-label">Last Updated</label>
                            <input type="text" class="eau-form-input" value="${category.updated_at || 'N/A'}" readonly>
                        </div>
                    </div>
                </form>
            `;

            $('#eau-modal-view-body').html(html);
        },

        /**
         * Render edit form
         */
        renderEditForm: function(category) {
            const html = `
                <form class="eau-modal-form" id="eau-category-edit-form">
                    <input type="hidden" id="edit-category-id" value="${category.id}">
                    <input type="hidden" id="edit-category-serial" value="${category.category_serial}">
                    <div class="eau-form-grid">
                        <div class="eau-form-field">
                            <label class="eau-form-label" for="edit-category-serial-display">Category ID</label>
                            <input type="text" id="edit-category-serial-display" class="eau-form-input" value="${category.category_serial}" readonly>
                        </div>
                        <div class="eau-form-field">
                            <label class="eau-form-label" for="edit-category-name">Category Name <span class="eau-form-required">*</span></label>
                            <input type="text" id="edit-category-name" class="eau-form-input" value="${category.category_name}" required>
                        </div>
                        <div class="eau-form-field">
                            <label class="eau-form-label" for="edit-points-per-hour">Points per Hour <span class="eau-form-required">*</span></label>
                            <input type="number" step="0.10" min="0" id="edit-points-per-hour" class="eau-form-input" value="${category.points_per_hour}" required>
                        </div>
                    </div>
                </form>
            `;

            $('#eau-modal-edit-body').html(html);
        },

        /**
         * Load add form
         */
        loadAddForm: function() {
            const self = this;

            // Generate category serial via AJAX
            $.ajax({
                url: eauCategoriesData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_generate_category_serial',
                    nonce: eauCategoriesData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        const categorySerial = response.data.category_serial;

                        const html = `
                            <form class="eau-modal-form" id="eau-category-add-form">
                                <div class="eau-form-grid">
                                    <div class="eau-form-field">
                                        <label class="eau-form-label" for="add-category-serial">Category ID</label>
                                        <input type="text" id="add-category-serial" class="eau-form-input" value="${categorySerial}" readonly>
                                    </div>
                                    <div class="eau-form-field">
                                        <label class="eau-form-label" for="add-category-name">Category Name <span class="eau-form-required">*</span></label>
                                        <input type="text" id="add-category-name" class="eau-form-input" placeholder="Enter category name" required>
                                    </div>
                                    <div class="eau-form-field">
                                        <label class="eau-form-label" for="add-points-per-hour">Points per Hour <span class="eau-form-required">*</span></label>
                                        <input type="number" step="0.10" min="0" id="add-points-per-hour" class="eau-form-input" value="1.00" required>
                                    </div>
                                </div>
                            </form>
                        `;

                        $('#eau-modal-add-body').html(html);
                    } else {
                        EauNotifications.error('Error', 'Failed to generate Category ID');
                    }
                },
                error: function() {
                    EauNotifications.error('Error', 'Failed to generate Category ID');
                }
            });
        },

        /**
         * Handle save category
         */
        handleSaveCategory: function(e) {
            e.preventDefault();
            const $modal = $(e.currentTarget).closest('.eau-modal');
            const modalId = $modal.attr('id');

            let data = {
                action: 'eau_save_category',
                nonce: eauCategoriesData.nonce
            };

            if (modalId === 'eau-modal-edit') {
                data.id = $('#edit-category-id').val();
                data.category_serial = $('#edit-category-serial').val().trim();
                data.category_name = $('#edit-category-name').val().trim();
                data.points_per_hour = $('#edit-points-per-hour').val();
            } else if (modalId === 'eau-modal-add') {
                data.category_serial = $('#add-category-serial').val().trim();
                data.category_name = $('#add-category-name').val().trim();
                data.points_per_hour = $('#add-points-per-hour').val();
            }

            // Validation
            if (!data.category_serial || !data.category_name) {
                EauNotifications.error('Validation Error', 'Please fill in all required fields');
                return;
            }

            const self = this;

            $.ajax({
                url: eauCategoriesData.ajaxUrl,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        EauNotifications.success('Success', response.data.message || 'Category saved successfully');
                        self.closeModal(modalId);
                        self.loadCategories();
                        self.loadStats();
                    } else {
                        EauNotifications.error('Error', response.data.message || 'Failed to save category');
                    }
                },
                error: function() {
                    EauNotifications.error('Error', 'Failed to save category');
                }
            });
        },

        /**
         * Open modal
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
            $overlay.find('[data-modal-action="save"]').off('click').on('click', function(e) {
                e.preventDefault();
                self.handleSaveCategory(e);
            });

            $overlay.find('[data-modal-action="create"]').off('click').on('click', function(e) {
                e.preventDefault();
                self.handleSaveCategory(e);
            });

            // Re-initialize Lucide icons
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        },

        /**
         * Close modal
         */
        closeModal: function(modalId) {
            if (modalId) {
                $('#' + modalId + '-overlay').fadeOut(200);
            } else {
                $('.eau-modal-overlay').fadeOut(200);
            }
        },

        /**
         * Handle close modal
         */
        handleCloseModal: function(e) {
            if (e) {
                e.preventDefault();
            }
            this.closeModal();
        },

        /**
         * Show skeleton
         */
        showSkeleton: function() {
            $('#categories-table-loading').show();
        },

        /**
         * Hide skeleton
         */
        hideSkeleton: function() {
            $('#categories-table-loading').hide();
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
        if ($('.eau-categories-management-container').length) {
            EauCategoriesManagement.init();
        }
    });

})(jQuery);
