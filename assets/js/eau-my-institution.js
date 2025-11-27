(function($) {
    'use strict';

    const MyInstitutionController = {

        // === STATE ===
        config: {},
        currentInstitutions: [],
        selectedInstitution: null,
        selectedRequest: null,
        searchPage: 1,
        searchPerPage: 10,
        incomingPage: 1,
        incomingPerPage: 10,
        searchTimeout: null,

        // === INIT ===
        init: function() {
            this.config = window.eauMyInstitutionData || {};
            this.bindEvents();
            this.loadInitialData();
        },

        // === BIND EVENTS ===
        bindEvents: function() {
            const self = this;

            // Search input with debounce
            $('#eau-institution-search').on('input', function() {
                clearTimeout(self.searchTimeout);
                const term = $(this).val().trim();

                if (term.length < 2) {
                    self.clearSearchResults();
                    return;
                }

                self.searchTimeout = setTimeout(function() {
                    self.searchPage = 1;
                    self.searchInstitutions(term);
                }, 300);
            });

            // Search pagination
            $(document).on('click', '#eau-search-pagination .eau-pagination-btn', function(e) {
                e.preventDefault();
                const page = $(this).data('page');
                if (page) {
                    self.searchPage = page;
                    self.searchInstitutions($('#eau-institution-search').val().trim());
                }
            });

            // Request to join button
            $(document).on('click', '.eau-request-join-btn', function(e) {
                e.preventDefault();
                const $btn = $(this);
                const institutionId = $btn.data('institution-id');
                const institutionName = $btn.data('institution-name');
                self.openRequestModal(institutionId, institutionName);
            });

            // Confirm request
            $('#eau-confirm-request-btn').on('click', function() {
                self.submitRequest();
            });

            // Cancel pending request
            $(document).on('click', '.eau-cancel-request-btn', function(e) {
                e.preventDefault();
                const requestId = $(this).data('request-id');
                self.cancelRequest(requestId);
            });

            // View institution details
            $(document).on('click', '.eau-view-institution-btn', function(e) {
                e.preventDefault();
                const institutionId = $(this).data('institution-id');
                self.viewInstitutionDetails(institutionId);
            });

            // Leave institution button
            $(document).on('click', '.eau-leave-institution-btn', function(e) {
                e.preventDefault();
                const $btn = $(this);
                const institutionId = $btn.data('institution-id');
                const institutionName = $btn.data('institution-name');
                self.openLeaveModal(institutionId, institutionName);
            });

            // Confirm leave
            $('#eau-confirm-leave-btn').on('click', function() {
                self.leaveInstitution();
            });

            // Review incoming request
            $(document).on('click', '.eau-review-request-btn', function(e) {
                e.preventDefault();
                const requestData = $(this).data('request');
                self.openRespondModal(requestData);
            });

            // Approve request
            $('#eau-approve-request-btn').on('click', function() {
                self.respondToRequest('approve');
            });

            // Reject request
            $('#eau-reject-request-btn').on('click', function() {
                self.respondToRequest('reject');
            });

            // Modal close buttons
            $(document).on('click', '[data-action="close"]', function() {
                $(this).closest('.eau-modal-overlay').fadeOut(200);
            });

            // Close modal on overlay click
            $('.eau-modal-overlay').on('click', function(e) {
                if ($(e.target).hasClass('eau-modal-overlay')) {
                    $(this).fadeOut(200);
                }
            });

            // Incoming requests pagination
            $(document).on('click', '#eau-incoming-pagination .eau-pagination-btn', function(e) {
                e.preventDefault();
                const page = $(this).data('page');
                if (page) {
                    self.incomingPage = page;
                    self.loadIncomingRequests();
                }
            });
        },

        // === LOAD INITIAL DATA ===
        loadInitialData: function() {
            this.loadStats();
            this.loadCurrentInstitution();
            this.loadPendingRequests();

            if (this.config.isInstitutionAdmin) {
                this.loadIncomingRequests();
            }
        },

        // === LOAD STATS ===
        loadStats: function() {
            const self = this;

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_get_my_institution_stats',
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.renderStats(response.data);
                    }
                }
            });
        },

        renderStats: function(stats) {
            let html = '<div class="eau-stats-grid eau-stats-grid-2">';

            html += `
                <div class="eau-stat-card eau-stat-card-blue">
                    <div class="eau-stat-card-content">
                        <h3 class="eau-stat-card-title">Pending Requests</h3>
                        <p class="eau-stat-card-number">${stats.pending_requests}</p>
                    </div>
                    <div class="eau-stat-card-icon">
                        <i data-lucide="clock"></i>
                    </div>
                </div>
            `;

            if (this.config.isInstitutionAdmin) {
                html += `
                    <div class="eau-stat-card eau-stat-card-purple">
                        <div class="eau-stat-card-content">
                            <h3 class="eau-stat-card-title">Requests to Review</h3>
                            <p class="eau-stat-card-number">${stats.incoming_requests}</p>
                        </div>
                        <div class="eau-stat-card-icon">
                            <i data-lucide="inbox"></i>
                        </div>
                    </div>
                `;
            } else {
                // Placeholder for non-admin
                html += `
                    <div class="eau-stat-card eau-stat-card-green">
                        <div class="eau-stat-card-content">
                            <h3 class="eau-stat-card-title">Institution Status</h3>
                            <p class="eau-stat-card-number">${this.currentInstitutions.length > 0 ? 'Active' : 'None'}</p>
                        </div>
                        <div class="eau-stat-card-icon">
                            <i data-lucide="building-2"></i>
                        </div>
                    </div>
                `;
            }

            html += '</div>';

            $('#eau-my-institution-stats').html(html);

            // Update badges
            if (stats.pending_requests > 0) {
                $('#eau-pending-count').text(stats.pending_requests).show();
            } else {
                $('#eau-pending-count').hide();
            }

            if (stats.incoming_requests > 0) {
                $('#eau-incoming-count').text(stats.incoming_requests).show();
            } else {
                $('#eau-incoming-count').hide();
            }

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        },

        // === LOAD CURRENT INSTITUTION ===
        loadCurrentInstitution: function() {
            const self = this;

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_get_my_institution',
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.currentInstitutions = response.data.institutions || [];
                        self.renderCurrentInstitutions();
                    } else {
                        self.renderNoInstitution();
                    }
                },
                error: function() {
                    self.renderNoInstitution();
                }
            });
        },

        renderCurrentInstitutions: function() {
            const self = this;

            if (this.currentInstitutions.length === 0) {
                this.renderNoInstitution();
                return;
            }

            let html = '<div class="eau-institution-cards">';

            this.currentInstitutions.forEach(function(inst) {
                const roleLabel = inst.role === 'admin' ? 'Administrator' : 'Member';
                const roleBadge = inst.role === 'admin'
                    ? '<span class="eau-badge eau-badge-purple">Admin</span>'
                    : '<span class="eau-badge eau-badge-blue">Member</span>';

                const statusBadge = inst.status === 'active'
                    ? '<span class="eau-badge eau-badge-success">Active</span>'
                    : '<span class="eau-badge eau-badge-secondary">Inactive</span>';

                html += `
                    <div class="eau-institution-card">
                        <div class="eau-institution-card-header">
                            <div class="eau-institution-card-logo">
                                ${inst.logo ? `<img src="${inst.logo}" alt="${inst.name}">` : '<i data-lucide="building-2"></i>'}
                            </div>
                            <div class="eau-institution-card-info">
                                <h3 class="eau-institution-card-name">${self.escapeHtml(inst.name)}</h3>
                                <div class="eau-institution-card-badges">
                                    ${roleBadge}
                                    ${statusBadge}
                                    ${inst.type ? `<span class="eau-badge eau-badge-outline">${self.escapeHtml(inst.type)}</span>` : ''}
                                </div>
                            </div>
                        </div>
                        <div class="eau-institution-card-body">
                            <div class="eau-institution-card-details">
                                ${inst.email ? `<p><i data-lucide="mail"></i> ${self.escapeHtml(inst.email)}</p>` : ''}
                                ${inst.phone ? `<p><i data-lucide="phone"></i> ${self.escapeHtml(inst.phone)}</p>` : ''}
                                ${inst.website ? `<p><i data-lucide="globe"></i> <a href="${self.escapeHtml(inst.website)}" target="_blank">${self.escapeHtml(inst.website)}</a></p>` : ''}
                                ${inst.city || inst.state ? `<p><i data-lucide="map-pin"></i> ${self.escapeHtml([inst.city, inst.state].filter(Boolean).join(', '))}</p>` : ''}
                            </div>
                        </div>
                        <div class="eau-institution-card-footer">
                            <button type="button" class="eau-btn eau-btn-secondary eau-btn-sm eau-view-institution-btn"
                                    data-institution-id="${inst.id}">
                                <i data-lucide="eye"></i>
                                View Details
                            </button>
                            ${inst.role === 'member' ? `
                                <button type="button" class="eau-btn eau-btn-outline-danger eau-btn-sm eau-leave-institution-btn"
                                        data-institution-id="${inst.id}"
                                        data-institution-name="${self.escapeHtml(inst.name)}">
                                    <i data-lucide="log-out"></i>
                                    Leave
                                </button>
                            ` : ''}
                        </div>
                    </div>
                `;
            });

            html += '</div>';

            $('#eau-current-institution-body').html(html);

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        },

        renderNoInstitution: function() {
            const html = `
                <div class="eau-empty-state">
                    <i data-lucide="building-2"></i>
                    <p>${this.config.strings.noInstitution}</p>
                    <p class="eau-text-muted">Use the search below to find and request to join an institution.</p>
                </div>
            `;

            $('#eau-current-institution-body').html(html);

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        },

        // === SEARCH INSTITUTIONS ===
        searchInstitutions: function(term) {
            const self = this;

            $('#eau-search-results').html('<div class="eau-loading-inline"><i data-lucide="loader-2" class="eau-spin"></i> Searching...</div>');
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_search_institutions_public',
                    nonce: this.config.nonce,
                    search: term,
                    page: this.searchPage,
                    per_page: this.searchPerPage
                },
                success: function(response) {
                    if (response.success) {
                        self.renderSearchResults(response.data);
                    } else {
                        self.showSearchError();
                    }
                },
                error: function() {
                    self.showSearchError();
                }
            });
        },

        renderSearchResults: function(data) {
            const self = this;
            const institutions = data.institutions || [];

            if (institutions.length === 0) {
                $('#eau-search-results').html(`
                    <div class="eau-empty-state eau-empty-state-sm">
                        <i data-lucide="search-x"></i>
                        <p>${this.config.strings.noResults}</p>
                    </div>
                `);
                $('#eau-search-pagination').hide();
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
                return;
            }

            let html = '<div class="eau-search-results-list">';

            institutions.forEach(function(inst) {
                const location = [inst.city, inst.state].filter(Boolean).join(', ');
                let actionBtn = '';

                if (inst.is_current) {
                    actionBtn = '<span class="eau-badge eau-badge-success"><i data-lucide="check"></i> Current</span>';
                } else if (inst.has_pending_request) {
                    actionBtn = '<span class="eau-badge eau-badge-warning"><i data-lucide="clock"></i> Pending</span>';
                } else {
                    actionBtn = `
                        <button type="button" class="eau-btn eau-btn-primary eau-btn-sm eau-request-join-btn"
                                data-institution-id="${inst.id}"
                                data-institution-name="${self.escapeHtml(inst.name)}">
                            <i data-lucide="plus"></i>
                            Request
                        </button>
                    `;
                }

                html += `
                    <div class="eau-search-result-item">
                        <div class="eau-search-result-info">
                            <h4 class="eau-search-result-name">${self.escapeHtml(inst.name)}</h4>
                            <div class="eau-search-result-meta">
                                ${location ? `<span><i data-lucide="map-pin"></i> ${self.escapeHtml(location)}</span>` : ''}
                                ${inst.type ? `<span><i data-lucide="tag"></i> ${self.escapeHtml(inst.type)}</span>` : ''}
                            </div>
                        </div>
                        <div class="eau-search-result-action">
                            ${actionBtn}
                        </div>
                    </div>
                `;
            });

            html += '</div>';

            $('#eau-search-results').html(html);

            // Render pagination
            if (data.total_pages > 1) {
                this.renderSearchPagination(data);
            } else {
                $('#eau-search-pagination').hide();
            }

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        },

        renderSearchPagination: function(data) {
            let html = '<div class="eau-pagination">';

            // Previous
            if (data.page > 1) {
                html += `<button class="eau-pagination-btn" data-page="${data.page - 1}"><i data-lucide="chevron-left"></i></button>`;
            }

            // Pages
            for (let i = 1; i <= data.total_pages; i++) {
                if (i === data.page) {
                    html += `<button class="eau-pagination-btn eau-pagination-btn-active">${i}</button>`;
                } else if (i <= 2 || i > data.total_pages - 2 || Math.abs(i - data.page) <= 1) {
                    html += `<button class="eau-pagination-btn" data-page="${i}">${i}</button>`;
                } else if (i === 3 && data.page > 4) {
                    html += '<span class="eau-pagination-ellipsis">...</span>';
                } else if (i === data.total_pages - 2 && data.page < data.total_pages - 3) {
                    html += '<span class="eau-pagination-ellipsis">...</span>';
                }
            }

            // Next
            if (data.page < data.total_pages) {
                html += `<button class="eau-pagination-btn" data-page="${data.page + 1}"><i data-lucide="chevron-right"></i></button>`;
            }

            html += '</div>';

            $('#eau-search-pagination').html(html).show();

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        },

        clearSearchResults: function() {
            $('#eau-search-results').html('<p class="eau-text-muted eau-text-center">Enter a search term to find institutions</p>');
            $('#eau-search-pagination').hide();
        },

        showSearchError: function() {
            $('#eau-search-results').html(`
                <div class="eau-alert eau-alert-danger">
                    <i data-lucide="alert-circle"></i>
                    <span>${this.config.strings.error}</span>
                </div>
            `);
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        },

        // === REQUEST MODAL ===
        openRequestModal: function(institutionId, institutionName) {
            this.selectedInstitution = {
                id: institutionId,
                name: institutionName
            };

            $('#eau-request-institution-name').text(institutionName);

            // Show warning if user already has an institution (for regular members)
            if (!this.config.isInstitutionAdmin && this.currentInstitutions.length > 0) {
                $('#eau-request-warning').show();
            } else {
                $('#eau-request-warning').hide();
            }

            $('#eau-request-modal-overlay').css('display', 'flex').hide().fadeIn(200);

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        },

        submitRequest: function() {
            const self = this;

            if (!this.selectedInstitution) {
                return;
            }

            const $btn = $('#eau-confirm-request-btn');
            $btn.prop('disabled', true).addClass('eau-loading');

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_request_institution_link',
                    nonce: this.config.nonce,
                    institution_id: this.selectedInstitution.id
                },
                success: function(response) {
                    if (response.success) {
                        EauNotifications.success('Success', self.config.strings.requestSent);
                        $('#eau-request-modal-overlay').fadeOut(200);
                        self.loadInitialData();
                    } else {
                        EauNotifications.error('Error', response.data.message || self.config.strings.error);
                    }
                },
                error: function() {
                    EauNotifications.error('Error', self.config.strings.error);
                },
                complete: function() {
                    $btn.prop('disabled', false).removeClass('eau-loading');
                }
            });
        },

        // === PENDING REQUESTS ===
        loadPendingRequests: function() {
            const self = this;

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_get_my_pending_requests',
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.renderPendingRequests(response.data.requests || []);
                    }
                }
            });
        },

        renderPendingRequests: function(requests) {
            const self = this;

            if (requests.length === 0) {
                $('#eau-pending-requests-body').html(`
                    <div class="eau-empty-state eau-empty-state-sm">
                        <i data-lucide="check-circle"></i>
                        <p>${this.config.strings.noPendingRequests}</p>
                    </div>
                `);
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
                return;
            }

            let html = '<div class="eau-requests-list">';

            requests.forEach(function(req) {
                html += `
                    <div class="eau-request-item">
                        <div class="eau-request-info">
                            <h4 class="eau-request-institution">${self.escapeHtml(req.institution_name)}</h4>
                            <p class="eau-request-date">
                                <i data-lucide="calendar"></i>
                                Requested: ${req.request_date_formatted}
                            </p>
                        </div>
                        <div class="eau-request-actions">
                            <span class="eau-badge eau-badge-warning">
                                <i data-lucide="clock"></i>
                                Pending
                            </span>
                            <button type="button" class="eau-btn eau-btn-outline-danger eau-btn-sm eau-cancel-request-btn"
                                    data-request-id="${req.request_id}">
                                <i data-lucide="x"></i>
                                Cancel
                            </button>
                        </div>
                    </div>
                `;
            });

            html += '</div>';

            $('#eau-pending-requests-body').html(html);

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        },

        cancelRequest: function(requestId) {
            const self = this;

            EauNotifications.confirm({
                title: 'Cancel Request?',
                message: this.config.strings.confirmCancel,
                type: 'warning',
                confirmText: 'Yes, Cancel',
                cancelText: 'No, Keep It',
                onConfirm: function() {
                    $.ajax({
                        url: self.config.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'eau_cancel_institution_request',
                            nonce: self.config.nonce,
                            request_id: requestId
                        },
                        success: function(response) {
                            if (response.success) {
                                EauNotifications.success('Cancelled', self.config.strings.requestCancelled);
                                self.loadInitialData();
                            } else {
                                EauNotifications.error('Error', response.data.message || self.config.strings.error);
                            }
                        },
                        error: function() {
                            EauNotifications.error('Error', self.config.strings.error);
                        }
                    });
                }
            });
        },

        // === INCOMING REQUESTS (for institutionAdmin) ===
        loadIncomingRequests: function() {
            const self = this;

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_get_incoming_institution_requests',
                    nonce: this.config.nonce,
                    page: this.incomingPage,
                    per_page: this.incomingPerPage
                },
                success: function(response) {
                    if (response.success) {
                        self.renderIncomingRequests(response.data);
                    }
                }
            });
        },

        renderIncomingRequests: function(data) {
            const self = this;
            const requests = data.requests || [];

            if (requests.length === 0) {
                $('#eau-incoming-requests-body').html(`
                    <div class="eau-empty-state eau-empty-state-sm">
                        <i data-lucide="inbox"></i>
                        <p>${this.config.strings.noIncomingRequests}</p>
                    </div>
                `);
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
                return;
            }

            let html = '<div class="eau-incoming-requests-list">';

            requests.forEach(function(req) {
                html += `
                    <div class="eau-incoming-request-item">
                        <div class="eau-incoming-request-info">
                            <div class="eau-incoming-request-user">
                                <strong>${self.escapeHtml(req.user_name)}</strong>
                                <span class="eau-text-muted">${self.escapeHtml(req.user_email)}</span>
                            </div>
                            <div class="eau-incoming-request-meta">
                                <span><i data-lucide="building-2"></i> Wants to join: <strong>${self.escapeHtml(req.institution_name)}</strong></span>
                                ${req.current_institution ? `<span><i data-lucide="map-pin"></i> Currently at: ${self.escapeHtml(req.current_institution)}</span>` : ''}
                                <span><i data-lucide="calendar"></i> ${req.request_date_formatted}</span>
                            </div>
                        </div>
                        <div class="eau-incoming-request-actions">
                            <button type="button" class="eau-btn eau-btn-primary eau-btn-sm eau-review-request-btn"
                                    data-request='${JSON.stringify(req).replace(/'/g, "&#39;")}'>
                                <i data-lucide="eye"></i>
                                Review
                            </button>
                        </div>
                    </div>
                `;
            });

            html += '</div>';

            // Add pagination if needed
            if (data.total_pages > 1) {
                html += '<div id="eau-incoming-pagination" class="eau-pagination">';
                // Similar pagination as search
                html += '</div>';
            }

            $('#eau-incoming-requests-body').html(html);

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        },

        // === RESPOND MODAL ===
        openRespondModal: function(requestData) {
            this.selectedRequest = requestData;

            $('#eau-respond-user-name').text(requestData.user_name);
            $('#eau-respond-user-email').text(requestData.user_email);
            $('#eau-respond-current-institution').text(requestData.current_institution || 'None');
            $('#eau-respond-request-date').text(requestData.request_date_formatted);
            $('#eau-respond-institution-name').text(requestData.institution_name);
            $('#eau-respond-notes').val('');

            $('#eau-respond-modal-overlay').css('display', 'flex').hide().fadeIn(200);

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        },

        respondToRequest: function(action) {
            const self = this;

            if (!this.selectedRequest) {
                return;
            }

            const notes = $('#eau-respond-notes').val().trim();
            const $approveBtn = $('#eau-approve-request-btn');
            const $rejectBtn = $('#eau-reject-request-btn');

            $approveBtn.prop('disabled', true);
            $rejectBtn.prop('disabled', true);

            if (action === 'approve') {
                $approveBtn.addClass('eau-loading');
            } else {
                $rejectBtn.addClass('eau-loading');
            }

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_respond_institution_request',
                    nonce: this.config.nonce,
                    request_id: this.selectedRequest.request_id,
                    response_action: action,
                    notes: notes
                },
                success: function(response) {
                    if (response.success) {
                        const message = action === 'approve'
                            ? self.config.strings.requestApproved
                            : self.config.strings.requestRejected;
                        EauNotifications.success('Success', message);
                        $('#eau-respond-modal-overlay').fadeOut(200);
                        self.loadInitialData();
                    } else {
                        EauNotifications.error('Error', response.data.message || self.config.strings.error);
                    }
                },
                error: function() {
                    EauNotifications.error('Error', self.config.strings.error);
                },
                complete: function() {
                    $approveBtn.prop('disabled', false).removeClass('eau-loading');
                    $rejectBtn.prop('disabled', false).removeClass('eau-loading');
                }
            });
        },

        // === VIEW INSTITUTION DETAILS ===
        viewInstitutionDetails: function(institutionId) {
            const self = this;

            // Find in current institutions first
            const inst = this.currentInstitutions.find(i => i.id == institutionId);

            if (inst) {
                this.renderInstitutionDetails(inst);
            } else {
                // Would need to fetch from server
                $('#eau-view-institution-body').html('<p class="eau-text-muted">Loading...</p>');
                $('#eau-view-institution-modal-overlay').css('display', 'flex').hide().fadeIn(200);
            }
        },

        renderInstitutionDetails: function(inst) {
            const self = this;

            $('#eau-view-institution-title').text(inst.name);

            let html = '<div class="eau-form-grid">';

            const fields = [
                { label: 'Company ID', value: inst.company_id },
                { label: 'Type', value: inst.type },
                { label: 'Status', value: inst.status },
                { label: 'Email', value: inst.email },
                { label: 'Phone', value: inst.phone },
                { label: 'Website', value: inst.website, isLink: true },
                { label: 'Address', value: inst.address, span: 2 },
                { label: 'City', value: inst.city },
                { label: 'State', value: inst.state },
                { label: 'Postcode', value: inst.postcode },
                { label: 'Country', value: inst.country },
            ];

            fields.forEach(function(field) {
                if (field.value) {
                    const spanClass = field.span === 2 ? 'eau-form-field-span-2' : '';
                    let displayValue = self.escapeHtml(field.value);

                    if (field.isLink) {
                        displayValue = `<a href="${displayValue}" target="_blank">${displayValue}</a>`;
                    }

                    html += `
                        <div class="eau-form-field ${spanClass}">
                            <label class="eau-form-label">${field.label}</label>
                            <p class="eau-form-static">${displayValue}</p>
                        </div>
                    `;
                }
            });

            html += '</div>';

            $('#eau-view-institution-body').html(html);
            $('#eau-view-institution-modal-overlay').css('display', 'flex').hide().fadeIn(200);

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        },

        // === LEAVE INSTITUTION ===
        openLeaveModal: function(institutionId, institutionName) {
            this.selectedInstitution = {
                id: institutionId,
                name: institutionName
            };

            $('#eau-leave-institution-name').text(institutionName);
            $('#eau-leave-modal-overlay').css('display', 'flex').hide().fadeIn(200);

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        },

        leaveInstitution: function() {
            const self = this;

            if (!this.selectedInstitution) {
                return;
            }

            const $btn = $('#eau-confirm-leave-btn');
            $btn.prop('disabled', true).addClass('eau-loading');

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_leave_institution',
                    nonce: this.config.nonce,
                    institution_id: this.selectedInstitution.id
                },
                success: function(response) {
                    if (response.success) {
                        EauNotifications.success('Success', self.config.strings.leftInstitution);
                        $('#eau-leave-modal-overlay').fadeOut(200);
                        self.loadInitialData();
                    } else {
                        EauNotifications.error('Error', response.data.message || self.config.strings.error);
                    }
                },
                error: function() {
                    EauNotifications.error('Error', self.config.strings.error);
                },
                complete: function() {
                    $btn.prop('disabled', false).removeClass('eau-loading');
                }
            });
        },

        // === UTILITIES ===
        escapeHtml: function(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        if ($('#eau-my-institution-container').length) {
            MyInstitutionController.init();
        }
    });

})(jQuery);
