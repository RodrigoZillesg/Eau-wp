/**
 * Membership Applications Management JavaScript
 *
 * @since 1.49.0
 */
(function($) {
    'use strict';

    const EauMembershipApplicationsController = {
        // State
        currentPage: 1,
        perPage: 20,
        searchTerm: '',
        filters: {},
        orderBy: 'submitted_at',
        order: 'DESC',
        currentApplicationId: null,
        currentApplicationData: null,

        /**
         * Initialize controller
         */
        init: function() {
            this.bindEvents();
            this.loadApplications();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            const self = this;

            // Search
            let searchTimeout;
            $('#eau-applications-search').on('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(function() {
                    self.searchTerm = $('#eau-applications-search').val();
                    self.currentPage = 1;
                    self.loadApplications();
                }, 300);
            });

            // Filters toggle
            $('#eau-filters-toggle').on('click', function() {
                $('#eau-applications-filters').slideToggle(200);
            });

            // Filter changes
            $(document).on('change', '#eau-applications-filters select, #eau-applications-filters input', function() {
                self.collectFilters();
                self.currentPage = 1;
                self.loadApplications();
            });

            // Clear filters
            $(document).on('click', '.eau-filters-clear', function() {
                $('#eau-applications-filters select').val('');
                $('#eau-applications-filters input').val('');
                self.filters = {};
                self.currentPage = 1;
                self.loadApplications();
            });

            // Table sorting
            $(document).on('click', '.eau-data-table th[data-sortable="true"]', function() {
                const column = $(this).data('column');
                if (self.orderBy === column) {
                    self.order = self.order === 'ASC' ? 'DESC' : 'ASC';
                } else {
                    self.orderBy = column;
                    self.order = 'ASC';
                }
                self.loadApplications();
            });

            // Pagination
            $(document).on('click', '.eau-pagination-btn:not(.disabled)', function() {
                const page = $(this).data('page');
                if (page) {
                    self.currentPage = parseInt(page);
                    self.loadApplications();
                }
            });

            $(document).on('change', '.eau-pagination-select', function() {
                self.perPage = parseInt($(this).val());
                self.currentPage = 1;
                self.loadApplications();
            });

            // View application
            $(document).on('click', '.eau-view-application', function() {
                const applicationId = $(this).data('id');
                self.viewApplication(applicationId);
            });

            // Quick approve/reject buttons
            $(document).on('click', '.eau-quick-approve', function(e) {
                e.stopPropagation();
                const applicationId = $(this).data('id');
                self.currentApplicationId = applicationId;
                self.openApproveModal();
            });

            $(document).on('click', '.eau-quick-reject', function(e) {
                e.stopPropagation();
                const applicationId = $(this).data('id');
                self.currentApplicationId = applicationId;
                self.openRejectModal();
            });

            // Modal action buttons
            $('#eau-approve-application').on('click', function() {
                self.openApproveModal();
            });

            $('#eau-reject-application').on('click', function() {
                self.openRejectModal();
            });

            $('#eau-mark-under-review').on('click', function() {
                self.markUnderReview();
            });

            // Cancel approved application
            $('#eau-cancel-application').on('click', function() {
                self.openCancelModal();
            });

            // Confirm approve
            $('#eau-confirm-approve').on('click', function() {
                self.approveApplication();
            });

            // Confirm reject
            $('#eau-confirm-reject').on('click', function() {
                self.rejectApplication();
            });

            // Confirm cancel
            $('#eau-confirm-cancel').on('click', function() {
                self.cancelApplication();
            });

            // Export CSV
            $('#eau-export-applications-csv').on('click', function() {
                self.exportCSV();
            });

            // Close modals - handle both modal overlay click and close buttons
            $(document).on('click', '[data-modal-action="close"]', function() {
                $(this).closest('.eau-modal-overlay').fadeOut(200);
            });

            // Close on overlay background click
            $(document).on('click', '.eau-modal-overlay', function(e) {
                if ($(e.target).hasClass('eau-modal-overlay')) {
                    $(this).fadeOut(200);
                }
            });
        },

        /**
         * Collect filter values
         */
        collectFilters: function() {
            this.filters = {
                status: $('#eau-applications-filters [name="status"]').val(),
                membership_type: $('#eau-applications-filters [name="membership_type"]').val(),
                date_from: $('#eau-applications-filters [name="date_from"]').val(),
                date_to: $('#eau-applications-filters [name="date_to"]').val(),
            };
        },

        /**
         * Load applications via AJAX
         */
        loadApplications: function() {
            const self = this;

            // Show skeleton loading
            this.showTableSkeleton();

            $.ajax({
                url: eauMembershipApplications.ajaxurl,
                type: 'POST',
                data: {
                    action: 'eau_get_membership_applications',
                    nonce: eauMembershipApplications.nonce,
                    page: this.currentPage,
                    per_page: this.perPage,
                    search: this.searchTerm,
                    order_by: this.orderBy,
                    order: this.order,
                    ...this.filters
                },
                success: function(response) {
                    if (response.success) {
                        self.renderTable(response.data.applications);
                        self.renderPagination(response.data);
                    } else {
                        EauNotifications.error(response.data.message || 'Failed to load applications');
                    }
                },
                error: function() {
                    EauNotifications.error('An error occurred while loading applications');
                }
            });
        },

        /**
         * Show table skeleton loading
         */
        showTableSkeleton: function() {
            const tbody = $('#eau-applications-table tbody');
            let skeletonRows = '';

            for (let i = 0; i < 5; i++) {
                skeletonRows += `
                    <tr>
                        <td><div class="eau-skeleton eau-skeleton-text" style="width: 40px;"></div></td>
                        <td>
                            <div class="eau-skeleton eau-skeleton-text" style="width: 120px;"></div>
                            <div class="eau-skeleton eau-skeleton-text" style="width: 160px; margin-top: 4px;"></div>
                        </td>
                        <td><div class="eau-skeleton eau-skeleton-text" style="width: 150px;"></div></td>
                        <td><div class="eau-skeleton eau-skeleton-text" style="width: 100px;"></div></td>
                        <td><div class="eau-skeleton eau-skeleton-text" style="width: 80px;"></div></td>
                        <td><div class="eau-skeleton eau-skeleton-text" style="width: 100px;"></div></td>
                        <td><div class="eau-skeleton eau-skeleton-text" style="width: 100px;"></div></td>
                    </tr>
                `;
            }

            tbody.html(skeletonRows);
        },

        /**
         * Render applications table
         */
        renderTable: function(applications) {
            const tbody = $('#eau-applications-table tbody');

            if (applications.length === 0) {
                tbody.html(`
                    <tr>
                        <td colspan="7" class="eau-no-results">
                            <i data-lucide="inbox"></i>
                            <p>No applications found</p>
                        </td>
                    </tr>
                `);
                lucide.createIcons();
                return;
            }

            let rows = '';
            applications.forEach(function(app) {
                const statusBadge = this.getStatusBadge(app.status);
                const canApprove = ['pending', 'under_review'].includes(app.status);

                rows += `
                    <tr data-id="${app.application_id}" class="eau-clickable-row">
                        <td>#${app.application_id}</td>
                        <td>
                            <div class="eau-applicant-cell">
                                <span class="eau-applicant-name">${this.escapeHtml(app.applicant.name)}</span>
                                <span class="eau-applicant-email">${this.escapeHtml(app.applicant.email)}</span>
                                ${app.has_documents ? '<span class="eau-has-documents"><i data-lucide="paperclip"></i> Has documents</span>' : ''}
                            </div>
                        </td>
                        <td>
                            <div class="eau-membership-type-cell">
                                <span class="eau-membership-type-label">${this.escapeHtml(app.membership_type.label)}</span>
                            </div>
                        </td>
                        <td>${this.escapeHtml(app.company_name || '-')}</td>
                        <td>${statusBadge}</td>
                        <td>${app.submitted_at_formatted}</td>
                        <td>
                            <div class="eau-action-buttons">
                                <button class="eau-action-btn eau-action-btn--view eau-view-application"
                                    data-id="${app.application_id}" title="View Details">
                                    <i data-lucide="eye"></i>
                                </button>
                                ${canApprove ? `
                                    <button class="eau-action-btn eau-action-btn--approve eau-quick-approve"
                                        data-id="${app.application_id}" title="Approve">
                                        <i data-lucide="check"></i>
                                    </button>
                                    <button class="eau-action-btn eau-action-btn--reject eau-quick-reject"
                                        data-id="${app.application_id}" title="Reject">
                                        <i data-lucide="x"></i>
                                    </button>
                                ` : ''}
                            </div>
                        </td>
                    </tr>
                `;
            }.bind(this));

            tbody.html(rows);
            lucide.createIcons();

            // Clickable rows
            $('.eau-clickable-row').on('click', function(e) {
                if (!$(e.target).closest('.eau-action-btn').length) {
                    const id = $(this).data('id');
                    EauMembershipApplicationsController.viewApplication(id);
                }
            });
        },

        /**
         * Get status badge HTML
         */
        getStatusBadge: function(status) {
            const icons = {
                pending: 'clock',
                under_review: 'eye',
                approved: 'check-circle',
                rejected: 'x-circle',
                cancelled: 'ban'
            };

            const labels = {
                pending: 'Pending',
                under_review: 'Under Review',
                approved: 'Approved',
                rejected: 'Rejected',
                cancelled: 'Cancelled'
            };

            return `
                <span class="eau-status-badge eau-status-badge--${status}">
                    <i data-lucide="${icons[status] || 'circle'}"></i>
                    ${labels[status] || status}
                </span>
            `;
        },

        /**
         * Render pagination - Following Members page pattern
         */
        renderPagination: function(data) {
            const container = $('#eau-pagination-container');

            if (data.total === 0) {
                container.html('');
                return;
            }

            const totalPages = data.total_pages;
            const currentPage = data.page;

            let html = '<div class="eau-pagination-wrapper">';

            // Info - "Showing X to Y of Z applications"
            const start = (currentPage - 1) * data.per_page + 1;
            const end = Math.min(currentPage * data.per_page, data.total);
            html += `<div class="eau-pagination-info">Showing ${start.toLocaleString()} to ${end.toLocaleString()} of ${data.total.toLocaleString()} applications</div>`;

            // Navigation buttons
            html += '<div class="eau-pagination-nav">';

            // Previous
            html += `
                <button class="eau-pagination-btn eau-pagination-prev ${currentPage === 1 ? 'disabled' : ''}"
                    data-page="${currentPage - 1}" ${currentPage === 1 ? 'disabled' : ''}>
                    <i data-lucide="chevron-left"></i>
                </button>
            `;

            // Page numbers
            const maxButtons = 5;
            let startPage = Math.max(1, currentPage - Math.floor(maxButtons / 2));
            let endPage = Math.min(totalPages, startPage + maxButtons - 1);

            if (endPage - startPage < maxButtons - 1) {
                startPage = Math.max(1, endPage - maxButtons + 1);
            }

            if (startPage > 1) {
                html += `<button class="eau-pagination-btn eau-pagination-number" data-page="1">1</button>`;
                if (startPage > 2) {
                    html += `<span class="eau-pagination-ellipsis">...</span>`;
                }
            }

            for (let i = startPage; i <= endPage; i++) {
                html += `
                    <button class="eau-pagination-btn eau-pagination-number ${i === currentPage ? 'eau-pagination-active' : ''}" data-page="${i}">
                        ${i}
                    </button>
                `;
            }

            if (endPage < totalPages) {
                if (endPage < totalPages - 1) {
                    html += `<span class="eau-pagination-ellipsis">...</span>`;
                }
                html += `<button class="eau-pagination-btn eau-pagination-number" data-page="${totalPages}">${totalPages}</button>`;
            }

            // Next
            html += `
                <button class="eau-pagination-btn eau-pagination-next ${currentPage === totalPages ? 'disabled' : ''}"
                    data-page="${currentPage + 1}" ${currentPage === totalPages ? 'disabled' : ''}>
                    <i data-lucide="chevron-right"></i>
                </button>
            `;

            html += '</div></div>';

            container.html(html);
            lucide.createIcons();
        },

        /**
         * View application details
         */
        viewApplication: function(applicationId) {
            const self = this;
            this.currentApplicationId = applicationId;

            // Show modal with loading (use overlay ID)
            $('#eau-view-application-modal-overlay').fadeIn(200);
            $('.eau-application-details').html(`
                <div class="eau-skeleton-loader">
                    <div class="eau-skeleton eau-skeleton-text" style="width: 100%; height: 100px;"></div>
                    <div class="eau-skeleton eau-skeleton-text" style="width: 100%; height: 150px; margin-top: 16px;"></div>
                    <div class="eau-skeleton eau-skeleton-text" style="width: 100%; height: 100px; margin-top: 16px;"></div>
                </div>
            `);

            $.ajax({
                url: eauMembershipApplications.ajaxurl,
                type: 'POST',
                data: {
                    action: 'eau_get_application_details',
                    nonce: eauMembershipApplications.nonce,
                    application_id: applicationId
                },
                success: function(response) {
                    if (response.success) {
                        self.currentApplicationData = response.data.application;
                        self.renderApplicationDetails(response.data.application);
                    } else {
                        EauNotifications.error(response.data.message || 'Failed to load application');
                        $('#eau-view-application-modal-overlay').fadeOut(200);
                    }
                },
                error: function() {
                    EauNotifications.error('An error occurred');
                    $('#eau-view-application-modal-overlay').fadeOut(200);
                }
            });
        },

        /**
         * Render application details in modal
         */
        renderApplicationDetails: function(app) {
            const canAction = ['pending', 'under_review'].includes(app.status);
            const canCancel = app.status === 'approved';

            // Show/hide action buttons
            $('#eau-mark-under-review').toggle(app.status === 'pending');
            $('#eau-approve-application, #eau-reject-application').toggle(canAction);
            $('#eau-cancel-application').toggle(canCancel);

            let html = '';

            // Status Section
            html += `
                <div class="eau-application-section">
                    <div class="eau-application-section-header">
                        <i data-lucide="info"></i>
                        <h4>Application Status</h4>
                    </div>
                    <div class="eau-application-section-content">
                        <div class="eau-info-grid">
                            <div class="eau-info-item">
                                <span class="eau-info-label">Application ID</span>
                                <span class="eau-info-value">#${app.application_id}</span>
                            </div>
                            <div class="eau-info-item">
                                <span class="eau-info-label">Status</span>
                                <span class="eau-info-value">${this.getStatusBadge(app.status)}</span>
                            </div>
                            <div class="eau-info-item">
                                <span class="eau-info-label">Submitted</span>
                                <span class="eau-info-value">${app.submitted_at_formatted}</span>
                            </div>
                            ${app.reviewed_at ? `
                                <div class="eau-info-item">
                                    <span class="eau-info-label">Reviewed</span>
                                    <span class="eau-info-value">${app.reviewed_at_formatted}</span>
                                </div>
                            ` : ''}
                        </div>
                        ${app.linked_user ? `
                            <div class="eau-linked-user">
                                <i data-lucide="user-check"></i>
                                <span>Linked to existing user: ${this.canViewMemberDetails() ? `<a href="${eauMembershipApplications.membersUrl}?edit=${app.linked_user.id}" target="_blank">${this.escapeHtml(app.linked_user.name)}</a>` : this.escapeHtml(app.linked_user.name)}</span>
                            </div>
                        ` : ''}
                    </div>
                </div>
            `;

            // Membership Type Section
            html += `
                <div class="eau-application-section">
                    <div class="eau-application-section-header">
                        <i data-lucide="award"></i>
                        <h4>Membership Type</h4>
                    </div>
                    <div class="eau-application-section-content">
                        <div class="eau-info-grid">
                            <div class="eau-info-item">
                                <span class="eau-info-label">Type</span>
                                <span class="eau-info-value eau-info-value--large">${this.escapeHtml(app.membership_type.label)}</span>
                            </div>
                            <div class="eau-info-item">
                                <span class="eau-info-label">Fee</span>
                                <span class="eau-info-value">
                                    <div class="eau-fee-display">
                                        <span class="eau-fee-amount">$${parseFloat(app.membership_type.fee).toLocaleString()}</span>
                                        <span class="eau-fee-currency">${app.membership_type.fee_currency}</span>
                                        <span class="eau-fee-note">(inc. GST)</span>
                                    </div>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            // Personal Info Section
            html += `
                <div class="eau-application-section">
                    <div class="eau-application-section-header">
                        <i data-lucide="user"></i>
                        <h4>Personal Information</h4>
                    </div>
                    <div class="eau-application-section-content">
                        <div class="eau-info-grid">
                            <div class="eau-info-item">
                                <span class="eau-info-label">Full Name</span>
                                <span class="eau-info-value">${this.escapeHtml(app.first_name)} ${this.escapeHtml(app.last_name)}</span>
                            </div>
                            <div class="eau-info-item">
                                <span class="eau-info-label">Email</span>
                                <span class="eau-info-value"><a href="mailto:${app.email}">${this.escapeHtml(app.email)}</a></span>
                            </div>
                            <div class="eau-info-item">
                                <span class="eau-info-label">Phone</span>
                                <span class="eau-info-value">${this.escapeHtml(app.phone || 'Not provided')}</span>
                            </div>
                            <div class="eau-info-item">
                                <span class="eau-info-label">Position</span>
                                <span class="eau-info-value">${this.escapeHtml(app.position || 'Not provided')}</span>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            // Organization Section
            html += `
                <div class="eau-application-section">
                    <div class="eau-application-section-header">
                        <i data-lucide="building-2"></i>
                        <h4>Organization</h4>
                    </div>
                    <div class="eau-application-section-content">
                        <div class="eau-info-grid">
                            <div class="eau-info-item">
                                <span class="eau-info-label">Company Name</span>
                                <span class="eau-info-value">${this.escapeHtml(app.company_name)}</span>
                            </div>
                            <div class="eau-info-item">
                                <span class="eau-info-label">Location</span>
                                <span class="eau-info-value">${this.escapeHtml(app.state)}, ${this.escapeHtml(app.country)}</span>
                            </div>
                            ${app.membership_data.cricos_number ? `
                                <div class="eau-info-item">
                                    <span class="eau-info-label">CRICOS Number</span>
                                    <span class="eau-info-value">${this.escapeHtml(app.membership_data.cricos_number)}</span>
                                </div>
                            ` : ''}
                            ${app.membership_data.cricos_sites ? `
                                <div class="eau-info-item">
                                    <span class="eau-info-label">CRICOS Sites</span>
                                    <span class="eau-info-value">${app.membership_data.cricos_sites}</span>
                                </div>
                            ` : ''}
                            ${app.membership_data.website ? `
                                <div class="eau-info-item">
                                    <span class="eau-info-label">Website</span>
                                    <span class="eau-info-value"><a href="${app.membership_data.website}" target="_blank">${this.escapeHtml(app.membership_data.website)}</a></span>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                </div>
            `;

            // Documents Section
            html += `
                <div class="eau-application-section">
                    <div class="eau-application-section-header">
                        <i data-lucide="paperclip"></i>
                        <h4>Documents</h4>
                    </div>
                    <div class="eau-application-section-content">
            `;

            if (app.documents && (app.documents.required?.length || app.documents.supporting?.length)) {
                html += '<div class="eau-documents-list">';

                if (app.documents.required) {
                    app.documents.required.forEach(function(doc) {
                        html += this.renderDocumentItem(doc, 'Required');
                    }.bind(this));
                }

                if (app.documents.supporting) {
                    app.documents.supporting.forEach(function(doc) {
                        html += this.renderDocumentItem(doc, 'Supporting');
                    }.bind(this));
                }

                html += '</div>';
            } else {
                html += '<p class="eau-no-documents">No documents uploaded</p>';
            }

            html += '</div></div>';

            // Admin Notes / Rejection Reason
            if (app.admin_notes) {
                html += `
                    <div class="eau-admin-notes-box">
                        <h5><i data-lucide="sticky-note"></i> Admin Notes</h5>
                        <p>${this.escapeHtml(app.admin_notes)}</p>
                    </div>
                `;
            }

            if (app.rejection_reason) {
                html += `
                    <div class="eau-rejection-box">
                        <h5><i data-lucide="alert-circle"></i> Rejection Reason</h5>
                        <p>${this.escapeHtml(app.rejection_reason)}</p>
                    </div>
                `;
            }

            $('.eau-application-details').html(html);
            lucide.createIcons();
        },

        /**
         * Render document item
         */
        renderDocumentItem: function(doc, type) {
            const icon = this.getDocumentIcon(doc.type);
            return `
                <a href="${doc.url}" target="_blank" class="eau-document-item">
                    <div class="eau-document-icon">
                        <i data-lucide="${icon}"></i>
                    </div>
                    <div class="eau-document-info">
                        <div class="eau-document-name">${this.escapeHtml(doc.name)}</div>
                        <div class="eau-document-type">${type} Document</div>
                    </div>
                    <div class="eau-document-action">
                        <i data-lucide="external-link"></i>
                    </div>
                </a>
            `;
        },

        /**
         * Get document icon based on MIME type
         */
        getDocumentIcon: function(mimeType) {
            if (mimeType.includes('pdf')) return 'file-text';
            if (mimeType.includes('image')) return 'image';
            if (mimeType.includes('word')) return 'file-text';
            return 'file';
        },

        /**
         * Open approve confirmation modal
         */
        openApproveModal: function() {
            $('#eau-view-application-modal-overlay').fadeOut(200);
            $('#eau-approve-modal-overlay').fadeIn(200);
            $('#approve-admin-notes').val('');
            $('#approve-send-email').prop('checked', true);
            lucide.createIcons();
        },

        /**
         * Open reject confirmation modal
         */
        openRejectModal: function() {
            $('#eau-view-application-modal-overlay').fadeOut(200);
            $('#eau-reject-modal-overlay').fadeIn(200);
            $('#reject-reason').val('');
            $('#reject-send-email').prop('checked', true);
            lucide.createIcons();
        },

        /**
         * Approve application
         */
        approveApplication: function() {
            const self = this;
            const adminNotes = $('#approve-admin-notes').val();
            const sendEmail = $('#approve-send-email').is(':checked');

            $('#eau-confirm-approve').prop('disabled', true).html('<i data-lucide="loader-2" class="spin"></i> Processing...');

            $.ajax({
                url: eauMembershipApplications.ajaxurl,
                type: 'POST',
                data: {
                    action: 'eau_approve_application',
                    nonce: eauMembershipApplications.nonce,
                    application_id: this.currentApplicationId,
                    admin_notes: adminNotes,
                    send_email: sendEmail
                },
                success: function(response) {
                    if (response.success) {
                        EauNotifications.success(response.data.message);
                        $('#eau-approve-modal-overlay').fadeOut(200);
                        self.loadApplications();
                    } else {
                        EauNotifications.error(response.data.message || 'Failed to approve application');
                    }
                },
                error: function() {
                    EauNotifications.error('An error occurred');
                },
                complete: function() {
                    $('#eau-confirm-approve').prop('disabled', false).html('<i data-lucide="check"></i> Confirm Approval');
                    lucide.createIcons();
                }
            });
        },

        /**
         * Reject application
         */
        rejectApplication: function() {
            const self = this;
            const rejectionReason = $('#reject-reason').val();
            const sendEmail = $('#reject-send-email').is(':checked');

            if (!rejectionReason.trim()) {
                EauNotifications.error('Please provide a rejection reason');
                return;
            }

            $('#eau-confirm-reject').prop('disabled', true).html('<i data-lucide="loader-2" class="spin"></i> Processing...');

            $.ajax({
                url: eauMembershipApplications.ajaxurl,
                type: 'POST',
                data: {
                    action: 'eau_reject_application',
                    nonce: eauMembershipApplications.nonce,
                    application_id: this.currentApplicationId,
                    rejection_reason: rejectionReason,
                    send_email: sendEmail
                },
                success: function(response) {
                    if (response.success) {
                        EauNotifications.success(response.data.message);
                        $('#eau-reject-modal-overlay').fadeOut(200);
                        self.loadApplications();
                    } else {
                        EauNotifications.error(response.data.message || 'Failed to reject application');
                    }
                },
                error: function() {
                    EauNotifications.error('An error occurred');
                },
                complete: function() {
                    $('#eau-confirm-reject').prop('disabled', false).html('<i data-lucide="x"></i> Confirm Rejection');
                    lucide.createIcons();
                }
            });
        },

        /**
         * Open cancel confirmation modal
         */
        openCancelModal: function() {
            $('#eau-view-application-modal-overlay').fadeOut(200);
            $('#eau-cancel-modal-overlay').fadeIn(200);
            $('#cancel-reason').val('');
            $('#cancel-send-email').prop('checked', true);
            lucide.createIcons();
        },

        /**
         * Cancel approved application
         */
        cancelApplication: function() {
            const self = this;
            const cancellationReason = $('#cancel-reason').val();
            const sendEmail = $('#cancel-send-email').is(':checked');

            if (!cancellationReason.trim()) {
                EauNotifications.error('Please provide a cancellation reason');
                return;
            }

            $('#eau-confirm-cancel').prop('disabled', true).html('<i data-lucide="loader-2" class="spin"></i> Processing...');

            $.ajax({
                url: eauMembershipApplications.ajaxurl,
                type: 'POST',
                data: {
                    action: 'eau_cancel_application',
                    nonce: eauMembershipApplications.nonce,
                    application_id: this.currentApplicationId,
                    cancellation_reason: cancellationReason,
                    send_email: sendEmail
                },
                success: function(response) {
                    if (response.success) {
                        EauNotifications.success(response.data.message);
                        $('#eau-cancel-modal-overlay').fadeOut(200);
                        self.loadApplications();
                    } else {
                        EauNotifications.error(response.data.message || 'Failed to cancel application');
                    }
                },
                error: function() {
                    EauNotifications.error('An error occurred');
                },
                complete: function() {
                    $('#eau-confirm-cancel').prop('disabled', false).html('<i data-lucide="ban"></i> Confirm Cancellation');
                    lucide.createIcons();
                }
            });
        },

        /**
         * Mark application as under review
         */
        markUnderReview: function() {
            const self = this;

            $.ajax({
                url: eauMembershipApplications.ajaxurl,
                type: 'POST',
                data: {
                    action: 'eau_mark_application_under_review',
                    nonce: eauMembershipApplications.nonce,
                    application_id: this.currentApplicationId
                },
                success: function(response) {
                    if (response.success) {
                        EauNotifications.success(response.data.message);
                        $('#eau-view-application-modal-overlay').fadeOut(200);
                        self.loadApplications();
                    } else {
                        EauNotifications.error(response.data.message || 'Failed to update status');
                    }
                },
                error: function() {
                    EauNotifications.error('An error occurred');
                }
            });
        },

        /**
         * Export to CSV
         */
        exportCSV: function() {
            const url = eauMembershipApplications.ajaxurl +
                '?action=eau_export_applications_csv&nonce=' + eauMembershipApplications.nonce;
            window.location.href = url;
        },

        /**
         * Check if current user can view member details (Admin or superAdmin only)
         */
        canViewMemberDetails: function() {
            const memType = eauMembershipApplications.mem_type || '';
            return memType === 'Admin' || memType === 'superAdmin';
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
        if ($('.eau-membership-applications-container').length) {
            EauMembershipApplicationsController.init();
        }
    });

})(jQuery);
