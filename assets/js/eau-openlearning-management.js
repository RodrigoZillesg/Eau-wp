/**
 * OpenLearning Course Management JavaScript
 *
 * @since 1.43.0
 */
(function($) {
    'use strict';

    const OpenLearningManagement = {
        currentPage: 1,
        perPage: 20,
        orderBy: 'title',
        order: 'ASC',
        search: '',
        filters: {},
        selectedIds: [],

        /**
         * Inicializa o módulo
         */
        init: function() {
            this.bindEvents();
            this.loadCourses();

            // Hide bulk action buttons initially (CSS handles this with !important)
        },

        /**
         * Bind eventos
         */
        bindEvents: function() {
            const self = this;

            // Search
            $('#eau-courses-search').on('keyup', this.debounce(function() {
                self.search = $(this).val();
                self.currentPage = 1;
                self.loadCourses();
            }, 500));

            // Filters toggle
            $('#eau-filters-toggle').on('click', function(e) {
                e.preventDefault();
                $('#courses-filters').slideToggle(300);
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
            $(document).on('change', '#courses-table-select-all, .eau-table-select-all-header', function() {
                const checked = $(this).is(':checked');
                $('.eau-row-checkbox').prop('checked', checked);
                $('#courses-table-select-all, .eau-table-select-all-header').prop('checked', checked);
                self.updateSelectedIds();
            });

            // Select row
            $(document).on('change', '.eau-row-checkbox', function() {
                self.updateSelectedIds();
            });

            // Toggle visibility inline
            $(document).on('click', '.eau-toggle-visibility', function(e) {
                e.preventDefault();
                const courseId = $(this).data('id');
                const currentState = $(this).data('visible') === true || $(this).data('visible') === 'true';
                self.toggleVisibility(courseId, !currentState, $(this));
            });

            // Toggle featured inline
            $(document).on('click', '.eau-toggle-featured', function(e) {
                e.preventDefault();
                const courseId = $(this).data('id');
                const currentState = $(this).data('featured') === true || $(this).data('featured') === 'true';
                self.toggleFeatured(courseId, !currentState, $(this));
            });

            // Sync courses
            $('#eau-sync-courses').on('click', function(e) {
                e.preventDefault();
                self.syncCourses();
            });

            // Bulk visibility buttons (old header buttons - keeping for backwards compatibility)
            $('#eau-bulk-visible').on('click', function(e) {
                e.preventDefault();
                self.bulkSetVisibility(true);
            });

            $('#eau-bulk-hidden').on('click', function(e) {
                e.preventDefault();
                self.bulkSetVisibility(false);
            });

            // Bulk actions bar - Close button
            $('#eau-bulk-actions-close').on('click', this.clearSelection.bind(this));

            // Bulk actions bar - Visibility buttons
            $('#eau-bulk-set-visible').on('click', function(e) {
                e.preventDefault();
                self.bulkSetVisibility(true);
            });

            $('#eau-bulk-set-hidden').on('click', function(e) {
                e.preventDefault();
                self.bulkSetVisibility(false);
            });

            // Bulk actions bar - Featured buttons
            $('#eau-bulk-set-featured').on('click', function(e) {
                e.preventDefault();
                self.bulkSetFeatured(true);
            });

            $('#eau-bulk-set-not-featured').on('click', function(e) {
                e.preventDefault();
                self.bulkSetFeatured(false);
            });
        },

        /**
         * Load courses
         */
        loadCourses: function() {
            const self = this;

            this.showLoading();

            $.ajax({
                url: eauOpenLearningMgmtData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_openlearning_mgmt_get_courses',
                    nonce: eauOpenLearningMgmtData.nonce,
                    page: this.currentPage,
                    per_page: this.perPage,
                    search: this.search,
                    orderby: this.orderBy,
                    order: this.order,
                    ...this.filters
                },
                success: function(response) {
                    if (response.success) {
                        self.renderCourses(response.data);
                        self.renderPagination(response.data);
                        self.updateCounter(response.data.total);
                        self.loadStats();
                    } else {
                        EauNotifications.error('Error', response.data.message || 'Failed to load courses');
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
         * Load stats
         */
        loadStats: function() {
            const self = this;

            $.ajax({
                url: eauOpenLearningMgmtData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_openlearning_mgmt_get_stats',
                    nonce: eauOpenLearningMgmtData.nonce
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
            $('.eau-stat-card').eq(0).find('.eau-stat-number').text(stats.total);
            $('.eau-stat-card').eq(1).find('.eau-stat-number').text(stats.visible);
            $('.eau-stat-card').eq(2).find('.eau-stat-number').text(stats.featured);
            $('.eau-stat-card').eq(3).find('.eau-stat-number').text(stats.free);
        },

        /**
         * Render courses in table
         */
        renderCourses: function(data) {
            const tbody = $('#courses-table-tbody');

            if (data.rows.length === 0) {
                tbody.html(this.getEmptyState());
                return;
            }

            let html = '';
            data.rows.forEach(function(row) {
                const priceLabel = row.price > 0 ? '$' + row.price.toFixed(2) : '<span class="eau-price-free">Free</span>';
                const visibilityIcon = row.is_visible
                    ? '<i data-lucide="eye" class="eau-icon-visible"></i> Visible'
                    : '<i data-lucide="eye-off" class="eau-icon-hidden"></i> Hidden';
                const visibilityClass = row.is_visible ? 'eau-status-visible' : 'eau-status-hidden';
                const featuredIcon = row.is_featured
                    ? '<i data-lucide="star" class="eau-icon-featured"></i> Featured'
                    : '<i data-lucide="star-off"></i> Not Featured';
                const featuredClass = row.is_featured ? 'eau-status-featured' : 'eau-status-not-featured';

                // Image thumbnail
                const imageHtml = row.image_url
                    ? `<img src="${row.image_url}" alt="" class="eau-course-thumb">`
                    : `<div class="eau-course-thumb-placeholder"><i data-lucide="book-open"></i></div>`;

                html += `
                    <tr class="eau-table-tr">
                        <td class="eau-table-td eau-table-td-checkbox">
                            <input type="checkbox" class="eau-row-checkbox" value="${row.ID}">
                        </td>
                        <td class="eau-table-td eau-td-course">
                            <div class="eau-course-cell">
                                ${imageHtml}
                                <div class="eau-course-info">
                                    <span class="eau-course-title">${row.title}</span>
                                    <span class="eau-course-id">ID: ${row.course_id || 'N/A'}</span>
                                </div>
                            </div>
                        </td>
                        <td class="eau-table-td eau-td-price">${priceLabel}</td>
                        <td class="eau-table-td eau-td-visibility">
                            <button class="eau-toggle-visibility ${visibilityClass}" data-id="${row.ID}" data-visible="${row.is_visible}">
                                ${visibilityIcon}
                            </button>
                        </td>
                        <td class="eau-table-td eau-td-featured">
                            <button class="eau-toggle-featured ${featuredClass}" data-id="${row.ID}" data-featured="${row.is_featured}">
                                ${featuredIcon}
                            </button>
                        </td>
                        <td class="eau-table-td eau-td-synced">
                            <span class="eau-last-synced">${row.last_synced || 'Never'}</span>
                        </td>
                        <td class="eau-table-td eau-table-td-actions">
                            <div class="eau-table-actions">
                                <button class="eau-action-btn eau-course-access-btn" data-course-id="${row.course_id}" title="Open in OpenLearning">
                                    <i data-lucide="external-link"></i>
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
                    <td colspan="8" style="text-align: center; padding: 3rem;">
                        <i data-lucide="book-x" style="width: 3rem; height: 3rem; color: #d1d5db; margin-bottom: 1rem;"></i>
                        <p style="color: #6b7280; margin: 0;">No courses found</p>
                        <p style="color: #9ca3af; margin: 0.5rem 0 0 0; font-size: 0.875rem;">Try syncing courses from OpenLearning</p>
                    </td>
                </tr>
            `;
        },

        /**
         * Render pagination
         */
        renderPagination: function(data) {
            const self = this;
            const container = $('#eau-courses-pagination');
            const totalPages = data.total_pages || 1;

            if (totalPages <= 1) {
                container.html('');
                return;
            }

            const startItem = ((data.page - 1) * data.per_page) + 1;
            const endItem = Math.min(data.page * data.per_page, data.total);

            const html = this.buildPaginationHTML(data.page, totalPages, startItem, endItem, data.total);
            container.html(html);

            // Bind pagination events
            container.find('.eau-pagination-btn').on('click', function(e) {
                e.preventDefault();
                if ($(this).is(':disabled') || $(this).hasClass('eau-pagination-active')) {
                    return;
                }

                const page = parseInt($(this).data('page'));
                self.currentPage = page;
                self.loadCourses();

                $('html, body').animate({
                    scrollTop: $('.eau-openlearning-management-container').offset().top - 100
                }, 300);
            });

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        },

        /**
         * Build pagination HTML - Using standard eau-pagination-wrapper classes
         */
        buildPaginationHTML: function(currentPage, totalPages, startItem, endItem, total) {
            let html = '<div class="eau-pagination-wrapper">';
            html += `<span class="eau-pagination-info">Showing ${startItem}-${endItem} of ${total}</span>`;
            html += '<div class="eau-pagination-nav">';

            // First page
            html += `<button class="eau-pagination-btn" data-page="1" ${currentPage === 1 ? 'disabled' : ''}>
                <i data-lucide="chevrons-left"></i>
            </button>`;

            // Previous
            html += `<button class="eau-pagination-btn" data-page="${currentPage - 1}" ${currentPage === 1 ? 'disabled' : ''}>
                <i data-lucide="chevron-left"></i>
            </button>`;

            // Page numbers
            const startPage = Math.max(1, currentPage - 2);
            const endPage = Math.min(totalPages, currentPage + 2);

            if (startPage > 1) {
                html += '<span class="eau-pagination-ellipsis">...</span>';
            }

            for (let i = startPage; i <= endPage; i++) {
                html += `<button class="eau-pagination-btn ${i === currentPage ? 'eau-pagination-active' : ''}" data-page="${i}">${i}</button>`;
            }

            if (endPage < totalPages) {
                html += '<span class="eau-pagination-ellipsis">...</span>';
            }

            // Next
            html += `<button class="eau-pagination-btn" data-page="${currentPage + 1}" ${currentPage === totalPages ? 'disabled' : ''}>
                <i data-lucide="chevron-right"></i>
            </button>`;

            // Last page
            html += `<button class="eau-pagination-btn" data-page="${totalPages}" ${currentPage === totalPages ? 'disabled' : ''}>
                <i data-lucide="chevrons-right"></i>
            </button>`;

            html += '</div></div>';
            return html;
        },

        /**
         * Handle sort
         */
        handleSort: function(columnKey) {
            const columnMap = {
                'course': 'title',
                'price': 'price',
                'visibility': 'visibility',
                'featured': 'featured',
                'synced': 'synced'
            };

            const sortField = columnMap[columnKey] || columnKey;

            if (this.orderBy === sortField) {
                this.order = this.order === 'ASC' ? 'DESC' : 'ASC';
            } else {
                this.orderBy = sortField;
                this.order = 'ASC';
            }

            this.updateSortIcons(columnKey);
            this.currentPage = 1;
            this.loadCourses();
        },

        /**
         * Update sort icons
         */
        updateSortIcons: function(activeColumn) {
            $('.eau-sortable').removeClass('eau-sorted-asc eau-sorted-desc');
            const $activeHeader = $(`.eau-sortable[data-key="${activeColumn}"]`);
            if (this.order === 'ASC') {
                $activeHeader.addClass('eau-sorted-asc');
            } else {
                $activeHeader.addClass('eau-sorted-desc');
            }
        },

        /**
         * Handle apply filters
         */
        handleApplyFilters: function(e) {
            e.preventDefault();

            this.filters = {
                visibility: $('#filter-visibility').val(),
                featured: $('#filter-featured').val(),
                price: $('#filter-price').val()
            };

            this.currentPage = 1;
            this.loadCourses();
        },

        /**
         * Handle clear filters
         */
        handleClearFilters: function(e) {
            e.preventDefault();

            $('#filter-visibility').val('');
            $('#filter-featured').val('');
            $('#filter-price').val('');

            this.filters = {};
            this.currentPage = 1;
            this.loadCourses();
        },

        /**
         * Update selected IDs and show/hide bulk actions bar
         */
        updateSelectedIds: function() {
            this.selectedIds = [];
            $('.eau-row-checkbox:checked').each(function() {
                OpenLearningManagement.selectedIds.push($(this).val());
            });

            // Update bulk actions bar
            const count = this.selectedIds.length;
            $('#eau-bulk-actions-count').text(count);
            $('#eau-bulk-actions-label').text(count === 1 ? 'course selected' : 'courses selected');

            if (count > 0) {
                $('#eau-bulk-actions-bar').addClass('eau-visible');
            } else {
                $('#eau-bulk-actions-bar').removeClass('eau-visible');
            }

            // Re-initialize Lucide icons for the bar
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        },

        /**
         * Clear selection
         */
        clearSelection: function() {
            $('.eau-row-checkbox').prop('checked', false);
            $('#courses-table-select-all, .eau-table-select-all-header').prop('checked', false);
            this.selectedIds = [];
            $('#eau-bulk-actions-bar').removeClass('eau-visible');
        },

        /**
         * Toggle visibility for a course
         */
        toggleVisibility: function(courseId, newState, $button) {
            const self = this;

            $.ajax({
                url: eauOpenLearningMgmtData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_openlearning_mgmt_update_course',
                    nonce: eauOpenLearningMgmtData.nonce,
                    course_id: courseId,
                    is_visible: newState
                },
                success: function(response) {
                    if (response.success) {
                        // Update button state
                        $button.data('visible', newState);
                        if (newState) {
                            $button.removeClass('eau-status-hidden').addClass('eau-status-visible');
                            $button.html('<i data-lucide="eye" class="eau-icon-visible"></i> Visible');
                        } else {
                            $button.removeClass('eau-status-visible').addClass('eau-status-hidden');
                            $button.html('<i data-lucide="eye-off" class="eau-icon-hidden"></i> Hidden');
                        }

                        if (typeof lucide !== 'undefined') {
                            lucide.createIcons();
                        }

                        EauNotifications.success('Updated', 'Course visibility updated');
                        self.loadStats();
                    } else {
                        EauNotifications.error('Error', response.data.message);
                    }
                },
                error: function() {
                    EauNotifications.error('Network Error', 'Please try again');
                }
            });
        },

        /**
         * Toggle featured for a course
         */
        toggleFeatured: function(courseId, newState, $button) {
            const self = this;

            $.ajax({
                url: eauOpenLearningMgmtData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_openlearning_mgmt_update_course',
                    nonce: eauOpenLearningMgmtData.nonce,
                    course_id: courseId,
                    is_featured: newState
                },
                success: function(response) {
                    if (response.success) {
                        // Update button state
                        $button.data('featured', newState);
                        if (newState) {
                            $button.removeClass('eau-status-not-featured').addClass('eau-status-featured');
                            $button.html('<i data-lucide="star" class="eau-icon-featured"></i> Featured');
                        } else {
                            $button.removeClass('eau-status-featured').addClass('eau-status-not-featured');
                            $button.html('<i data-lucide="star-off"></i> Not Featured');
                        }

                        if (typeof lucide !== 'undefined') {
                            lucide.createIcons();
                        }

                        EauNotifications.success('Updated', 'Course featured status updated');
                        self.loadStats();
                    } else {
                        EauNotifications.error('Error', response.data.message);
                    }
                },
                error: function() {
                    EauNotifications.error('Network Error', 'Please try again');
                }
            });
        },

        /**
         * Bulk set visibility
         */
        bulkSetVisibility: function(visible) {
            const self = this;

            if (this.selectedIds.length === 0) {
                EauNotifications.warning('Warning', 'Please select courses first');
                return;
            }

            const action = visible ? 'set as visible' : 'hide';
            EauNotifications.confirm({
                title: 'Bulk Update',
                message: `Are you sure you want to ${action} ${this.selectedIds.length} selected course(s)?`,
                type: 'info',
                confirmText: 'Yes, update',
                cancelText: 'Cancel',
                onConfirm: function() {
                    $.ajax({
                        url: eauOpenLearningMgmtData.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'eau_openlearning_mgmt_bulk_visibility',
                            nonce: eauOpenLearningMgmtData.nonce,
                            course_ids: self.selectedIds,
                            visibility: visible
                        },
                        success: function(response) {
                            if (response.success) {
                                EauNotifications.success('Updated', response.data.message);
                                self.clearSelection();
                                self.loadCourses();
                            } else {
                                EauNotifications.error('Error', response.data.message);
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
         * Bulk set featured (v1.72.5)
         */
        bulkSetFeatured: function(featured) {
            const self = this;

            if (this.selectedIds.length === 0) {
                EauNotifications.warning('Warning', 'Please select courses first');
                return;
            }

            const action = featured ? 'set as featured' : 'remove from featured';
            EauNotifications.confirm({
                title: 'Bulk Update',
                message: `Are you sure you want to ${action} ${this.selectedIds.length} selected course(s)?`,
                type: 'info',
                confirmText: 'Yes, update',
                cancelText: 'Cancel',
                onConfirm: function() {
                    $.ajax({
                        url: eauOpenLearningMgmtData.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'eau_openlearning_mgmt_bulk_featured',
                            nonce: eauOpenLearningMgmtData.nonce,
                            course_ids: self.selectedIds,
                            featured: featured
                        },
                        success: function(response) {
                            if (response.success) {
                                EauNotifications.success('Updated', response.data.message);
                                self.clearSelection();
                                self.loadCourses();
                            } else {
                                EauNotifications.error('Error', response.data.message);
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
         * Sync courses from OpenLearning
         */
        syncCourses: function() {
            const self = this;

            this.openModal('eau-modal-sync');
            $('#eau-modal-sync-body').html(`
                <div class="eau-sync-progress">
                    <div class="eau-sync-spinner"></div>
                    <p>Synchronizing courses from OpenLearning...</p>
                    <p class="eau-sync-hint">This may take a few moments.</p>
                </div>
            `);

            $.ajax({
                url: eauOpenLearningMgmtData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_openlearning_mgmt_sync',
                    nonce: eauOpenLearningMgmtData.nonce
                },
                success: function(response) {
                    self.closeModal();

                    if (response.success) {
                        const stats = response.data.stats || {};
                        EauNotifications.success('Sync Complete',
                            `Created: ${stats.created || 0}, Updated: ${stats.updated || 0}`);
                        self.loadCourses();
                    } else {
                        EauNotifications.error('Sync Failed', response.data.message);
                    }
                },
                error: function() {
                    self.closeModal();
                    EauNotifications.error('Network Error', 'Please try again');
                }
            });
        },

        /**
         * Open modal
         */
        openModal: function(modalId) {
            $('#' + modalId + '-overlay').css('display', 'flex').hide().fadeIn(200);
        },

        /**
         * Close modal
         */
        closeModal: function() {
            $('.eau-modal-overlay').fadeOut(200);
        },

        /**
         * Get modal loading state
         */
        getModalLoading: function() {
            return `
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
            `;
        },

        /**
         * Show loading state
         */
        showLoading: function() {
            $('#courses-table-loading').show();
        },

        /**
         * Hide loading state
         */
        hideLoading: function() {
            $('#courses-table-loading').hide();
        },

        /**
         * Update counter display
         */
        updateCounter: function(total) {
            $('#courses-table-count .eau-count-number').text(total);
        },

        /**
         * Debounce function
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

    // Initialize on document ready
    $(document).ready(function() {
        OpenLearningManagement.init();
    });

})(jQuery);
