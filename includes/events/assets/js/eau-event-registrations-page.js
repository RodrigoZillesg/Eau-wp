/**
 * Eau Event Registrations Page - JavaScript
 *
 * @package EauSystem
 * @since 1.29.3
 */

(function($) {
    'use strict';

    const EauEventRegistrationsPage = {
        // State
        currentPage: 1,
        perPage: 20,
        searchTerm: '',
        statusFilter: '',
        paymentFilter: '',
        orderBy: 'registration_date',
        order: 'DESC',
        eventId: null,

        /**
         * Initialize
         */
        init: function() {
            this.eventId = $('.eau-event-registrations-container').data('event-id');
            if (!this.eventId) {
                console.error('Event ID not found');
                return;
            }

            this.bindEvents();
            this.loadRegistrations();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            const self = this;

            // Search
            let searchTimeout;
            $('#eau-registrations-search').on('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(function() {
                    self.searchTerm = $('#eau-registrations-search').val();
                    self.currentPage = 1;
                    self.loadRegistrations();
                }, 300);
            });

            // Status filter
            $('#eau-registrations-status-filter').on('change', function() {
                self.statusFilter = $(this).val();
                self.currentPage = 1;
                self.loadRegistrations();
            });

            // Payment filter
            $('#eau-registrations-payment-filter').on('change', function() {
                self.paymentFilter = $(this).val();
                self.currentPage = 1;
                self.loadRegistrations();
            });

            // Sortable columns
            $(document).on('click', '.eau-sortable', function() {
                const sort = $(this).data('sort');

                if (self.orderBy === sort) {
                    self.order = self.order === 'ASC' ? 'DESC' : 'ASC';
                } else {
                    self.orderBy = sort;
                    self.order = 'ASC';
                }

                self.loadRegistrations();
            });

            // Dropdown toggle
            $(document).on('click', '.eau-action-more', function(e) {
                e.stopPropagation();
                const $menu = $(this).siblings('.eau-dropdown-menu');
                $('.eau-dropdown-menu').not($menu).removeClass('active');
                $menu.toggleClass('active');
            });

            // Close dropdown on click outside
            $(document).on('click', function() {
                $('.eau-dropdown-menu').removeClass('active');
            });

            // Manage Payments button - opens modal
            $(document).on('click', '.eau-manage-payments-btn', function(e) {
                e.stopPropagation();
                const regId = $(this).data('id');
                $('.eau-dropdown-menu').removeClass('active');
                self.openPaymentModal(regId);
            });

            // Status change actions (for quick status changes)
            $(document).on('click', '.eau-reg-status-btn', function(e) {
                e.stopPropagation();
                const regId = $(this).data('id');
                const newStatus = $(this).data('status');
                $('.eau-dropdown-menu').removeClass('active');
                self.updateRegistrationStatus(regId, newStatus);
            });

            // Pagination
            $(document).on('click', '.eau-pagination-btn:not(.disabled)', function() {
                const page = $(this).data('page');
                if (page) {
                    self.currentPage = page;
                    self.loadRegistrations();
                }
            });

            // Export CSV
            $('#eau-export-csv').on('click', function() {
                self.exportCSV();
            });

            // Payment Modal Events
            this.bindPaymentModalEvents();
        },

        /**
         * Bind payment modal events
         */
        bindPaymentModalEvents: function() {
            const self = this;

            // Close modal
            $(document).on('click', '#eau-payment-modal-close, #eau-payment-modal-done, #eau-payment-modal .eau-modal-overlay', function() {
                self.closePaymentModal();
            });

            // Add payment form submit
            $(document).on('submit', '#eau-add-payment-form', function(e) {
                e.preventDefault();
                self.addPayment();
            });

            // Delete payment
            $(document).on('click', '.eau-delete-payment-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const paymentId = $(this).data('id');
                if (paymentId) {
                    self.deletePayment(paymentId);
                }
            });

            // Receipt link - prevent modal interference
            $(document).on('click', '.eau-receipt-link', function(e) {
                e.stopPropagation();
            });

            // Media Upload Component Events for Receipt
            this.bindMediaUploadEvents();
        },

        /**
         * Bind Media Upload Component Events
         */
        bindMediaUploadEvents: function() {
            const self = this;

            // Media upload tabs
            $(document).on('click', '#eau-payment-modal .eau-media-upload-tab', function() {
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

            // Media upload - Browse button
            $(document).on('click', '#eau-payment-modal .eau-media-upload-browse-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const wrapper = $(this).closest('.eau-media-upload-wrapper');
                const fileInput = wrapper.find('.eau-media-upload-file-input')[0];
                if (fileInput) {
                    fileInput.click();
                }
            });

            // Media upload - Click on dropzone
            $(document).on('click', '#eau-payment-modal .eau-media-upload-dropzone', function(e) {
                if ($(e.target).closest('.eau-media-upload-browse-btn').length || $(e.target).is('input[type="file"]')) {
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
            $(document).on('change', '#eau-payment-modal .eau-media-upload-file-input', function() {
                const wrapper = $(this).closest('.eau-media-upload-wrapper');
                const file = this.files[0];
                if (file) {
                    self.uploadFile(wrapper, file);
                }
                $(this).val('');
            });

            // Media upload - Remove button
            $(document).on('click', '#eau-payment-modal .eau-media-upload-remove', function() {
                const wrapper = $(this).closest('.eau-media-upload-wrapper');
                self.clearMediaUpload(wrapper);
            });
        },

        /**
         * Upload file via AJAX
         */
        uploadFile: function(wrapper, file) {
            const self = this;
            const maxSize = parseInt(wrapper.data('max-file-size')) || 10485760;
            const allowedExtensions = (wrapper.data('allowed-extensions') || '').split(',').filter(e => e);

            // Validate file size
            if (file.size > maxSize) {
                if (typeof EauNotifications !== 'undefined') {
                    EauNotifications.error('Error', 'File is too large. Max size: ' + (maxSize / 1024 / 1024) + 'MB');
                }
                return;
            }

            // Validate extension
            if (allowedExtensions.length > 0) {
                const ext = file.name.split('.').pop().toLowerCase();
                if (!allowedExtensions.includes(ext)) {
                    if (typeof EauNotifications !== 'undefined') {
                        EauNotifications.error('Error', 'File type not allowed. Allowed: ' + allowedExtensions.join(', '));
                    }
                    return;
                }
            }

            // Show progress
            const $progress = wrapper.find('.eau-media-upload-progress');
            const $progressFill = wrapper.find('.eau-media-upload-progress-fill');
            const $progressText = wrapper.find('.eau-media-upload-progress-text');
            const $dropzone = wrapper.find('.eau-media-upload-dropzone-content');

            $dropzone.hide();
            $progress.show();

            const formData = new FormData();
            formData.append('action', 'eau_upload_media');
            formData.append('nonce', eauEventRegistrations.nonce);
            formData.append('file', file);

            $.ajax({
                url: eauEventRegistrations.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                xhr: function() {
                    const xhr = new window.XMLHttpRequest();
                    xhr.upload.addEventListener('progress', function(e) {
                        if (e.lengthComputable) {
                            const percent = Math.round((e.loaded / e.total) * 100);
                            $progressFill.css('width', percent + '%');
                            $progressText.text('Uploading... ' + percent + '%');
                        }
                    }, false);
                    return xhr;
                },
                success: function(response) {
                    $progress.hide();
                    $dropzone.show();
                    $progressFill.css('width', '0%');

                    if (response.success) {
                        self.setMediaValue(wrapper, response.data.id, 'media', response.data.filename, response.data.url);
                    } else {
                        if (typeof EauNotifications !== 'undefined') {
                            let errorMsg = response.data.message || 'Upload failed';
                            // Debug info
                            if (response.data.debug) {
                                console.log('Upload Debug:', response.data.debug);
                                errorMsg += ' (Check console for debug info)';
                            }
                            EauNotifications.error('Error', errorMsg);
                        }
                    }
                },
                error: function(xhr, status, error) {
                    $progress.hide();
                    $dropzone.show();
                    $progressFill.css('width', '0%');
                    if (typeof EauNotifications !== 'undefined') {
                        let errorMsg = 'Upload failed: ' + (xhr.status || 'Unknown error');
                        // Try to get response data
                        try {
                            const resp = JSON.parse(xhr.responseText);
                            if (resp.data && resp.data.message) {
                                errorMsg = resp.data.message;
                            }
                            if (resp.data && resp.data.debug) {
                                console.log('Upload Debug:', resp.data.debug);
                            }
                        } catch(e) {}
                        EauNotifications.error('Error', errorMsg);
                    }
                }
            });
        },

        /**
         * Load user files for My Files tab
         */
        loadUserFiles: function(wrapper) {
            const self = this;
            const $list = wrapper.find('.eau-media-upload-myfiles-list');

            $list.html('<div class="eau-media-upload-myfiles-loading"><i data-lucide="loader-2" class="eau-spin"></i><span>Loading files...</span></div>');
            lucide.createIcons();

            $.ajax({
                url: eauEventRegistrations.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_get_user_files',
                    nonce: eauEventRegistrations.nonce
                },
                success: function(response) {
                    if (response.success && response.data.files) {
                        self.renderUserFiles(wrapper, response.data.files);
                    } else {
                        $list.html('<div class="eau-media-upload-myfiles-empty">No files found</div>');
                    }
                },
                error: function() {
                    $list.html('<div class="eau-media-upload-myfiles-empty">Error loading files</div>');
                }
            });
        },

        /**
         * Render user files grid
         */
        renderUserFiles: function(wrapper, files) {
            const self = this;
            const $list = wrapper.find('.eau-media-upload-myfiles-list');

            if (!files || files.length === 0) {
                $list.html('<div class="eau-media-upload-myfiles-empty">No files found</div>');
                return;
            }

            let html = '<div class="eau-media-upload-myfiles-grid">';
            files.forEach(function(file) {
                const isImage = file.type && file.type.startsWith('image/');
                html += '<div class="eau-media-upload-myfile-item" data-id="' + file.id + '" data-url="' + file.url + '" data-filename="' + file.filename + '">';
                if (isImage && file.thumbnail) {
                    html += '<img src="' + file.thumbnail + '" alt="' + file.filename + '">';
                } else {
                    html += '<div class="eau-media-upload-myfile-icon"><i data-lucide="file"></i></div>';
                }
                html += '<span class="eau-media-upload-myfile-name">' + file.filename + '</span>';
                html += '</div>';
            });
            html += '</div>';

            $list.html(html);
            lucide.createIcons();

            // Bind click on file items
            $list.find('.eau-media-upload-myfile-item').on('click', function() {
                const id = $(this).data('id');
                const url = $(this).data('url');
                const filename = $(this).data('filename');
                self.setMediaValue(wrapper, id, 'media', filename, url);
            });
        },

        /**
         * Set media value
         */
        setMediaValue: function(wrapper, value, type, filename, url) {
            wrapper.find('.eau-media-upload-value').val(value);
            wrapper.find('.eau-media-upload-type').val(type);

            // Show preview
            const $preview = wrapper.find('.eau-media-upload-preview');
            const $previewName = wrapper.find('[id$="-preview-name"]');
            const $previewLink = wrapper.find('[id$="-preview-link"]');
            const $previewImage = wrapper.find('.eau-media-upload-preview-image');
            const $previewIcon = wrapper.find('.eau-media-upload-preview-icon');

            $previewName.text(filename || value);
            if (url) {
                $previewLink.attr('href', url).attr('target', '_blank').show();
            }

            // Check if image
            const isImage = url && /\.(jpg|jpeg|png|gif|webp)$/i.test(url);
            if (isImage) {
                $previewImage.attr('src', url).show();
                $previewIcon.hide();
            } else {
                $previewImage.hide();
                $previewIcon.show();
            }

            $preview.show();
            lucide.createIcons();
        },

        /**
         * Clear media upload
         */
        clearMediaUpload: function(wrapper) {
            wrapper.find('.eau-media-upload-value').val('');
            wrapper.find('.eau-media-upload-type').val('');
            wrapper.find('.eau-media-upload-url-input').val('');
            wrapper.find('.eau-media-upload-preview').hide();
            wrapper.find('.eau-media-upload-preview-image').attr('src', '').hide();
            wrapper.find('.eau-media-upload-preview-icon').show();
        },

        /**
         * Load registrations via AJAX
         */
        loadRegistrations: function() {
            const self = this;
            const $tbody = $('#eau-registrations-tbody');

            // Show skeleton loading
            $tbody.html('<tr><td colspan="6" class="eau-loading-cell"><div class="eau-skeleton-row"></div></td></tr>');

            $.ajax({
                url: eauEventRegistrations.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_get_event_registrations',
                    nonce: eauEventRegistrations.nonce,
                    event_id: this.eventId,
                    page: this.currentPage,
                    per_page: this.perPage,
                    search: this.searchTerm,
                    status: this.paymentFilter, // Use payment filter for status
                    orderby: this.orderBy,
                    order: this.order
                },
                success: function(response) {
                    if (response.success) {
                        self.renderTable(response.data.rows);
                        self.renderPagination(response.data);
                        self.updateStats(response.data.stats);
                    } else {
                        $tbody.html('<tr><td colspan="6" class="eau-empty-cell">Error loading registrations</td></tr>');
                    }
                    lucide.createIcons();
                },
                error: function() {
                    $tbody.html('<tr><td colspan="6" class="eau-empty-cell">Error loading registrations</td></tr>');
                }
            });
        },

        /**
         * Render table rows
         */
        renderTable: function(rows) {
            const self = this;
            const $tbody = $('#eau-registrations-tbody');

            if (!rows || rows.length === 0) {
                $tbody.html('<tr><td colspan="6" class="eau-empty-cell">No registrations found</td></tr>');
                return;
            }

            let html = '';
            rows.forEach(function(row) {
                const statusBadgeClass = 'eau-badge-' + row.status_class;

                html += '<tr>';
                html += '<td class="eau-member-cell">';
                html += '<span class="eau-member-name">' + self.escapeHtml(row.attendee_name) + '</span>';
                html += '</td>';
                html += '<td class="eau-contact-cell">';
                html += '<span>' + self.escapeHtml(row.attendee_email) + '</span>';
                html += '</td>';
                html += '<td>' + row.registration_date + '</td>';
                html += '<td>';
                html += '<span class="eau-badge ' + statusBadgeClass + '">' + row.status_label + '</span>';
                html += '</td>';
                html += '<td class="eau-attended-cell">';
                if (row.attended) {
                    html += '<span class="eau-attended-badge eau-attended-yes" title="Clicked Join Online">';
                    html += '<i data-lucide="check-circle"></i>';
                    html += '</span>';
                    if (row.activity_created) {
                        html += '<span class="eau-cpd-badge" title="CPD Activity Created">';
                        html += '<i data-lucide="award"></i>';
                        html += '</span>';
                    }
                } else {
                    html += '<span class="eau-attended-badge eau-attended-no" title="Not attended yet">';
                    html += '<i data-lucide="minus"></i>';
                    html += '</span>';
                }
                html += '</td>';
                html += '<td class="eau-table-actions-col">';
                html += '<div class="eau-table-actions">';
                html += self.renderStatusActions(row);
                html += '</div>';
                html += '</td>';
                html += '</tr>';
            });

            $tbody.html(html);
        },

        /**
         * Render status action buttons (dropdown with payment options)
         */
        renderStatusActions: function(row) {
            let actions = '';

            // Dropdown for payment status
            actions += '<div class="eau-dropdown" data-id="' + row.id + '">';
            actions += '<button class="eau-action-btn eau-action-more" title="Actions">';
            actions += '<i data-lucide="more-vertical"></i>';
            actions += '</button>';
            actions += '<div class="eau-dropdown-menu">';

            // Manage Payments button (opens modal)
            actions += '<button class="eau-dropdown-item eau-manage-payments-btn" data-id="' + row.id + '">';
            actions += '<i data-lucide="credit-card"></i> Manage Payments';
            actions += '</button>';

            actions += '<div class="eau-dropdown-divider"></div>';

            // Quick status change options
            if (row.status !== 'paid') {
                actions += '<button class="eau-dropdown-item eau-reg-status-btn" data-id="' + row.id + '" data-status="paid">';
                actions += '<i data-lucide="check-circle"></i> Mark as Paid';
                actions += '</button>';
            }

            if (row.status !== 'pending') {
                actions += '<button class="eau-dropdown-item eau-reg-status-btn" data-id="' + row.id + '" data-status="pending">';
                actions += '<i data-lucide="clock"></i> Mark as Pending';
                actions += '</button>';
            }

            if (row.status !== 'failed') {
                actions += '<button class="eau-dropdown-item eau-dropdown-item-danger eau-reg-status-btn" data-id="' + row.id + '" data-status="failed">';
                actions += '<i data-lucide="x-circle"></i> Mark as Failed';
                actions += '</button>';
            }

            if (row.status !== 'refunded') {
                actions += '<button class="eau-dropdown-item eau-reg-status-btn" data-id="' + row.id + '" data-status="refunded">';
                actions += '<i data-lucide="rotate-ccw"></i> Mark as Refunded';
                actions += '</button>';
            }

            actions += '</div>';
            actions += '</div>';

            return actions;
        },

        /**
         * Open payment modal
         */
        openPaymentModal: function(registrationId) {
            const self = this;
            const $modal = $('#eau-payment-modal');

            // Store registration ID
            $('#eau-payment-registration-id').val(registrationId);

            // Reset form
            $('#eau-add-payment-form')[0].reset();
            $('#eau-payment-date').val(new Date().toISOString().split('T')[0]);

            // Reset media upload component
            const $receiptWrapper = $('#eau-payment-receipt-id-wrapper');
            this.clearMediaUpload($receiptWrapper);

            // Show modal with loading state
            $modal.addClass('active');
            $('#eau-payments-list').html('<div class="eau-skeleton-row"></div>');

            // Load payment info
            $.ajax({
                url: eauEventRegistrations.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_get_registration_payment_info',
                    nonce: eauEventRegistrations.nonce,
                    registration_id: registrationId
                },
                success: function(response) {
                    if (response.success) {
                        self.populatePaymentModal(response.data);
                    } else {
                        self.showToast(response.data.message || 'Error loading payment info', 'error');
                        self.closePaymentModal();
                    }
                    lucide.createIcons();
                },
                error: function() {
                    self.showToast('Error loading payment info', 'error');
                    self.closePaymentModal();
                }
            });
        },

        /**
         * Populate payment modal with data
         */
        populatePaymentModal: function(data) {
            // Registration info
            $('#eau-payment-attendee-name').text(data.registration.attendee_name || '-');
            $('#eau-payment-attendee-email').text(data.registration.attendee_email || '-');
            $('#eau-payment-event-price').text('$' + parseFloat(data.event.price || 0).toFixed(2));

            // Summary
            $('#eau-payment-total-paid').text('$' + parseFloat(data.total_paid || 0).toFixed(2));
            $('#eau-payment-balance').text('$' + parseFloat(data.balance || 0).toFixed(2));

            // Set suggested amount to balance
            if (data.balance > 0) {
                $('#eau-payment-amount').val(parseFloat(data.balance).toFixed(2));
            }

            // Payments list
            this.renderPaymentsList(data.payments);
        },

        /**
         * Render payments list
         */
        renderPaymentsList: function(payments) {
            const self = this;
            const $list = $('#eau-payments-list');

            if (!payments || payments.length === 0) {
                $list.html('<div class="eau-empty-payments"><i data-lucide="receipt"></i><br>No payments recorded yet.</div>');
                lucide.createIcons();
                return;
            }

            const methodLabels = {
                'credit_card': 'Credit Card',
                'debit_card': 'Debit Card',
                'bank_transfer': 'Bank Transfer',
                'pix': 'PIX',
                'cash': 'Cash',
                'invoice': 'Invoice',
                'other': 'Other'
            };

            let html = '';

            payments.forEach(function(payment) {
                html += '<div class="eau-payment-card">';

                // Amount
                html += '<div class="eau-payment-card-amount">$' + parseFloat(payment.amount).toFixed(2) + '</div>';

                // Details
                html += '<div class="eau-payment-card-details">';
                html += '<div class="eau-payment-card-method">' + (methodLabels[payment.payment_method] || payment.payment_method) + '</div>';
                html += '<div class="eau-payment-card-meta">';
                html += '<span class="eau-payment-card-date"><i data-lucide="calendar"></i> ' + self.escapeHtml(payment.payment_date) + '</span>';
                html += '</div>';

                // Notes if any
                if (payment.notes) {
                    html += '<div class="eau-payment-card-notes">' + self.escapeHtml(payment.notes) + '</div>';
                }
                html += '</div>';

                // Actions
                html += '<div class="eau-payment-card-actions">';
                if (payment.receipt_url) {
                    html += '<a href="' + payment.receipt_url + '" target="_blank" class="eau-receipt-link">';
                    html += '<i data-lucide="file-text"></i> Receipt';
                    html += '</a>';
                }
                html += '<button type="button" class="eau-payment-delete-btn eau-delete-payment-btn" data-id="' + payment.id + '" title="Delete payment">';
                html += '<i data-lucide="trash-2"></i>';
                html += '</button>';
                html += '</div>';

                html += '</div>';
            });

            $list.html(html);
            lucide.createIcons();
        },

        /**
         * Close payment modal
         */
        closePaymentModal: function() {
            $('#eau-payment-modal').removeClass('active');
            this.loadRegistrations(); // Refresh table
        },

        /**
         * Add payment
         */
        addPayment: function() {
            const self = this;
            const $btn = $('#eau-add-payment-btn');
            const originalText = $btn.html();

            // Disable button
            $btn.prop('disabled', true).html('<i data-lucide="loader"></i> Adding...');

            $.ajax({
                url: eauEventRegistrations.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_add_payment',
                    nonce: eauEventRegistrations.nonce,
                    registration_id: $('#eau-payment-registration-id').val(),
                    amount: $('#eau-payment-amount').val(),
                    payment_date: $('#eau-payment-date').val(),
                    payment_method: $('#eau-payment-method').val(),
                    receipt_id: $('#eau-payment-receipt-id').val(),
                    notes: $('#eau-payment-notes').val()
                },
                success: function(response) {
                    $btn.prop('disabled', false).html(originalText);
                    lucide.createIcons();

                    if (response.success) {
                        self.showToast(response.data.message, 'success');

                        // Update modal data
                        $('#eau-payment-total-paid').text('$' + parseFloat(response.data.total_paid || 0).toFixed(2));
                        $('#eau-payment-balance').text('$' + parseFloat(response.data.balance || 0).toFixed(2));
                        self.renderPaymentsList(response.data.payments);

                        // Reset form
                        $('#eau-add-payment-form')[0].reset();
                        $('#eau-payment-date').val(new Date().toISOString().split('T')[0]);

                        // Reset media upload component
                        const $receiptWrapper = $('#eau-payment-receipt-id-wrapper');
                        self.clearMediaUpload($receiptWrapper);

                        // Update suggested amount
                        if (response.data.balance > 0) {
                            $('#eau-payment-amount').val(parseFloat(response.data.balance).toFixed(2));
                        }
                    } else {
                        self.showToast(response.data.message || 'Error adding payment', 'error');
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).html(originalText);
                    lucide.createIcons();
                    self.showToast('Error adding payment', 'error');
                }
            });
        },

        /**
         * Delete payment
         */
        deletePayment: function(paymentId) {
            const self = this;

            if (typeof EauNotifications !== 'undefined') {
                EauNotifications.confirm({
                    title: 'Delete Payment?',
                    message: 'Are you sure you want to delete this payment? This action cannot be undone.',
                    type: 'danger',
                    confirmText: 'Delete',
                    onConfirm: function() {
                        self.doDeletePayment(paymentId);
                    }
                });
            } else if (confirm('Are you sure you want to delete this payment?')) {
                self.doDeletePayment(paymentId);
            }
        },

        /**
         * Execute delete payment
         */
        doDeletePayment: function(paymentId) {
            const self = this;

            $.ajax({
                url: eauEventRegistrations.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_delete_payment',
                    nonce: eauEventRegistrations.nonce,
                    payment_id: paymentId
                },
                success: function(response) {
                    if (response.success) {
                        self.showToast(response.data.message, 'success');

                        // Update modal data
                        $('#eau-payment-total-paid').text('$' + parseFloat(response.data.total_paid || 0).toFixed(2));
                        $('#eau-payment-balance').text('$' + parseFloat(response.data.balance || 0).toFixed(2));
                        self.renderPaymentsList(response.data.payments);

                        // Update suggested amount
                        if (response.data.balance > 0) {
                            $('#eau-payment-amount').val(parseFloat(response.data.balance).toFixed(2));
                        }
                    } else {
                        self.showToast(response.data.message || 'Error deleting payment', 'error');
                    }
                },
                error: function() {
                    self.showToast('Error deleting payment', 'error');
                }
            });
        },

        /**
         * Render pagination
         */
        renderPagination: function(data) {
            const $container = $('#eau-pagination-container');

            if (data.total_pages <= 1) {
                $container.html('');
                return;
            }

            let html = '<div class="eau-pagination">';

            // Prev button
            html += '<button class="eau-pagination-btn ' + (data.page <= 1 ? 'disabled' : '') + '" data-page="' + (data.page - 1) + '">';
            html += '<i data-lucide="chevron-left"></i>';
            html += '</button>';

            // Page numbers
            for (let i = 1; i <= data.total_pages; i++) {
                if (i === 1 || i === data.total_pages || (i >= data.page - 2 && i <= data.page + 2)) {
                    html += '<button class="eau-pagination-btn ' + (i === data.page ? 'active' : '') + '" data-page="' + i + '">' + i + '</button>';
                } else if (i === data.page - 3 || i === data.page + 3) {
                    html += '<span class="eau-pagination-ellipsis">...</span>';
                }
            }

            // Next button
            html += '<button class="eau-pagination-btn ' + (data.page >= data.total_pages ? 'disabled' : '') + '" data-page="' + (data.page + 1) + '">';
            html += '<i data-lucide="chevron-right"></i>';
            html += '</button>';

            html += '</div>';

            $container.html(html);
            lucide.createIcons();
        },

        /**
         * Update stats cards
         */
        updateStats: function(stats) {
            // Update stats values (if elements exist)
            $('.eau-stats-card-value').each(function() {
                const $parent = $(this).closest('.eau-stats-card');
                const label = $parent.find('.eau-stats-card-label').text().toLowerCase();

                if (stats[label] !== undefined) {
                    $(this).text(stats[label]);
                }
            });
        },

        /**
         * Update registration status
         */
        updateRegistrationStatus: function(regId, newStatus) {
            const self = this;

            $.ajax({
                url: eauEventRegistrations.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_update_registration_status',
                    nonce: eauEventRegistrations.nonce,
                    registration_id: regId,
                    status: newStatus
                },
                success: function(response) {
                    if (response.success) {
                        self.showToast(response.data.message, 'success');
                        self.loadRegistrations();
                    } else {
                        self.showToast(response.data.message || 'Error updating status', 'error');
                    }
                },
                error: function() {
                    self.showToast('Error updating status', 'error');
                }
            });
        },

        /**
         * Export CSV
         */
        exportCSV: function() {
            const self = this;

            $.ajax({
                url: eauEventRegistrations.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_export_event_registrations',
                    nonce: eauEventRegistrations.nonce,
                    event_id: this.eventId,
                    search: this.searchTerm,
                    status: this.paymentFilter
                },
                success: function(response) {
                    if (response.success && response.data.csv) {
                        // Create download
                        const blob = new Blob([response.data.csv], { type: 'text/csv' });
                        const url = window.URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = response.data.filename || 'registrations.csv';
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                        window.URL.revokeObjectURL(url);

                        self.showToast('CSV exported successfully', 'success');
                    } else {
                        self.showToast(response.data.message || 'Error exporting CSV', 'error');
                    }
                },
                error: function() {
                    self.showToast('Error exporting CSV', 'error');
                }
            });
        },

        /**
         * Show toast notification
         */
        showToast: function(message, type) {
            if (typeof EauNotifications !== 'undefined') {
                switch (type) {
                    case 'success':
                        EauNotifications.success('Success', message);
                        break;
                    case 'error':
                        EauNotifications.error('Error', message);
                        break;
                    case 'warning':
                        EauNotifications.warning('Warning', message);
                        break;
                    default:
                        EauNotifications.info('Info', message);
                }
            } else {
                console.log(type + ': ' + message);
            }
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
        if ($('.eau-event-registrations-container').length) {
            EauEventRegistrationsPage.init();
        }
    });

})(jQuery);
