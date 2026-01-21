/**
 * Settings Page Controller
 *
 * @since 1.39.0
 * @since 1.60.0 Adicionado sistema de abas e Categories Management
 */
(function($) {
    'use strict';

    const EauSettingsController = {

        // State
        settings: {
            activity_approval_mode: 'manual'
        },
        tags: [],
        editingTagId: null,
        activeTab: 'general',
        categoriesLoaded: false,
        eventCategoriesLoaded: false,
        couponsLoaded: false,
        paymentGatewayLoaded: false,

        /**
         * Initialize controller
         */
        init: function() {
            this.bindEvents();
            this.bindTabEvents();
            this.loadTags();
            this.restoreTab();

            // Initialize Lucide icons
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        },

        /**
         * Bind tab events (v1.60.0)
         */
        bindTabEvents: function() {
            const self = this;

            // Tab click
            $('.eau-settings-tab').on('click', function(e) {
                e.preventDefault();
                const tab = $(this).data('tab');
                self.switchTab(tab);
            });
        },

        /**
         * Switch tab (v1.60.0)
         */
        switchTab: function(tab) {
            // Update buttons
            $('.eau-settings-tab').removeClass('active');
            $(`.eau-settings-tab[data-tab="${tab}"]`).addClass('active');

            // Update panels
            $('.eau-settings-tab-panel').removeClass('active');
            $(`.eau-settings-tab-panel[data-tab-content="${tab}"]`).addClass('active');

            this.activeTab = tab;

            // Store active tab in localStorage
            localStorage.setItem('eau_settings_active_tab', tab);

            // Lazy load CPD Categories when tab is activated
            if (tab === 'cpd-categories' && !this.categoriesLoaded) {
                this.categoriesLoaded = true;
                if (typeof EauCategoriesController !== 'undefined') {
                    EauCategoriesController.init();
                }
            }

            // Lazy load Event Categories when tab is activated
            if (tab === 'event-categories' && !this.eventCategoriesLoaded) {
                this.eventCategoriesLoaded = true;
                if (typeof EauEventCategoriesController !== 'undefined') {
                    EauEventCategoriesController.init();
                }
            }

            // Lazy load Coupons when tab is activated (v1.69.0)
            if (tab === 'coupons' && !this.couponsLoaded) {
                this.couponsLoaded = true;
                if (typeof EauCouponsController !== 'undefined') {
                    EauCouponsController.init();
                }
            }

            // Lazy load Payment Gateway when tab is activated (v1.70.0)
            if (tab === 'payment-gateway' && !this.paymentGatewayLoaded) {
                this.paymentGatewayLoaded = true;
                if (typeof EauPaymentGatewayController !== 'undefined') {
                    EauPaymentGatewayController.init();
                }
            }

            // Re-init Lucide icons
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        },

        /**
         * Restore tab from localStorage (v1.60.0)
         */
        restoreTab: function() {
            const validTabs = ['general', 'cpd-categories', 'event-categories', 'tags', 'coupons', 'import', 'system'];

            // Check URL hash first
            const hash = window.location.hash.substring(1);
            if (hash && validTabs.includes(hash)) {
                this.switchTab(hash);
                return;
            }

            // Otherwise check localStorage
            const savedTab = localStorage.getItem('eau_settings_active_tab');
            if (savedTab && validTabs.includes(savedTab)) {
                this.switchTab(savedTab);
            }
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            const self = this;

            // Radio option click - update visual state
            $('.eau-radio-option').on('click', function() {
                const $group = $(this).closest('.eau-radio-group');
                $group.find('.eau-radio-option').removeClass('selected');
                $(this).addClass('selected');
            });

            // Save settings button
            $('#eau-save-settings-btn').on('click', function() {
                self.saveSettings();
            });

            // Recreate missing pages button (v1.57.0)
            $('#eau-recreate-pages-btn').on('click', function() {
                self.recreateMissingPages();
            });

            // Save email exemptions button (v1.66.8)
            $('#eau-save-email-exempt-btn').on('click', function() {
                self.saveEmailExemptions();
            });

            // Add tag button
            $('#eau-add-tag-btn').on('click', function() {
                self.addTag();
            });

            // Add tag on Enter key
            $('#eau-new-tag-name').on('keypress', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    self.addTag();
                }
            });

            // Tag actions (delegated)
            $(document).on('click', '.eau-tag-action-btn.edit', function() {
                const tagId = $(this).closest('.eau-tag-item').data('tag-id');
                self.startEditTag(tagId);
            });

            $(document).on('click', '.eau-tag-action-btn.delete', function() {
                const tagId = $(this).closest('.eau-tag-item').data('tag-id');
                self.deleteTag(tagId);
            });

            $(document).on('click', '.eau-tag-action-btn.save', function() {
                const tagId = $(this).closest('.eau-tag-item').data('tag-id');
                self.saveEditTag(tagId);
            });

            $(document).on('click', '.eau-tag-action-btn.cancel', function() {
                self.cancelEditTag();
            });

            // Save edit on Enter key
            $(document).on('keypress', '.eau-tag-edit-name', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    const tagId = $(this).closest('.eau-tag-item').data('tag-id');
                    self.saveEditTag(tagId);
                }
            });

            // Cancel edit on Escape key
            $(document).on('keydown', '.eau-tag-edit-name', function(e) {
                if (e.which === 27) {
                    e.preventDefault();
                    self.cancelEditTag();
                }
            });
        },

        /**
         * Load tags from server
         */
        loadTags: function() {
            const self = this;

            $.ajax({
                url: eauSettingsData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_get_member_tags',
                    nonce: eauSettingsData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.tags = response.data.tags || [];
                        self.renderTags();
                    } else {
                        EauNotifications.error('Error', response.data.message || 'Failed to load tags.');
                    }
                },
                error: function() {
                    EauNotifications.error('Network Error', 'Failed to load tags.');
                }
            });
        },

        /**
         * Render tags list
         */
        renderTags: function() {
            const $list = $('#eau-tags-list');

            if (this.tags.length === 0) {
                $list.html(`
                    <div class="eau-tags-empty">
                        <i data-lucide="tags"></i>
                        <p>No tags created yet</p>
                    </div>
                `);
            } else {
                let html = '';
                this.tags.forEach(tag => {
                    html += this.renderTagItem(tag);
                });
                $list.html(html);
            }

            // Re-init Lucide icons
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        },

        /**
         * Render single tag item
         */
        renderTagItem: function(tag) {
            const isEditing = this.editingTagId === tag.id;
            const description = tag.description || '';

            if (isEditing) {
                return `
                    <div class="eau-tag-item editing" data-tag-id="${tag.id}">
                        <div class="eau-tag-edit-inputs">
                            <div class="eau-tag-edit-row">
                                <input type="text" class="eau-form-input eau-tag-edit-name" value="${this.escapeHtml(tag.name)}" placeholder="Tag name" maxlength="50">
                                <input type="color" class="eau-color-picker eau-tag-edit-color" value="${tag.color}" title="Tag color">
                            </div>
                            <input type="text" class="eau-form-input eau-tag-edit-description" value="${this.escapeHtml(description)}" placeholder="Description (optional)" maxlength="200">
                        </div>
                        <div class="eau-tag-actions">
                            <button type="button" class="eau-btn eau-btn-sm eau-btn-icon eau-btn-primary-icon eau-tag-action-btn save" title="Save">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                            </button>
                            <button type="button" class="eau-btn eau-btn-sm eau-btn-icon eau-tag-action-btn cancel" title="Cancel">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                            </button>
                        </div>
                    </div>
                `;
            }

            const descriptionHtml = description ? `<span class="eau-tag-description">${this.escapeHtml(description)}</span>` : '';

            return `
                <div class="eau-tag-item" data-tag-id="${tag.id}">
                    <div class="eau-tag-color" style="background-color: ${tag.color}"></div>
                    <div class="eau-tag-info">
                        <span class="eau-tag-name">${this.escapeHtml(tag.name)}</span>
                        ${descriptionHtml}
                        <span class="eau-tag-slug">${tag.slug}</span>
                    </div>
                    <div class="eau-tag-actions">
                        <button type="button" class="eau-btn eau-btn-sm eau-btn-icon eau-tag-action-btn edit" title="Edit">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path></svg>
                        </button>
                        <button type="button" class="eau-btn eau-btn-sm eau-btn-icon eau-btn-danger-ghost eau-tag-action-btn delete" title="Delete">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                        </button>
                    </div>
                </div>
            `;
        },

        /**
         * Add new tag
         */
        addTag: function() {
            const self = this;
            const $nameInput = $('#eau-new-tag-name');
            const $colorInput = $('#eau-new-tag-color');
            const $descriptionInput = $('#eau-new-tag-description');
            const $btn = $('#eau-add-tag-btn');

            const name = $nameInput.val().trim();
            const color = $colorInput.val();
            const description = $descriptionInput.val().trim();

            if (!name) {
                EauNotifications.warning('Warning', 'Please enter a tag name.');
                $nameInput.focus();
                return;
            }

            const originalHtml = $btn.html();
            $btn.prop('disabled', true).html('<i data-lucide="loader-2" class="eau-spin"></i>');

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }

            $.ajax({
                url: eauSettingsData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_add_member_tag',
                    nonce: eauSettingsData.nonce,
                    name: name,
                    color: color,
                    description: description
                },
                success: function(response) {
                    if (response.success) {
                        self.tags.push(response.data.tag);
                        self.renderTags();
                        $nameInput.val('');
                        $descriptionInput.val('');
                        EauNotifications.success('Success', 'Tag created successfully.');
                    } else {
                        EauNotifications.error('Error', response.data.message || 'Failed to create tag.');
                    }
                },
                error: function() {
                    EauNotifications.error('Network Error', 'Failed to create tag.');
                },
                complete: function() {
                    $btn.prop('disabled', false).html(originalHtml);
                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                }
            });
        },

        /**
         * Start editing a tag
         */
        startEditTag: function(tagId) {
            this.editingTagId = tagId;
            this.renderTags();

            // Focus on the name input
            setTimeout(() => {
                $('.eau-tag-edit-name').focus().select();
            }, 50);
        },

        /**
         * Save tag edit
         */
        saveEditTag: function(tagId) {
            const self = this;
            const $item = $(`.eau-tag-item[data-tag-id="${tagId}"]`);
            const name = $item.find('.eau-tag-edit-name').val().trim();
            const color = $item.find('.eau-tag-edit-color').val();
            const description = $item.find('.eau-tag-edit-description').val().trim();

            if (!name) {
                EauNotifications.warning('Warning', 'Tag name cannot be empty.');
                return;
            }

            $.ajax({
                url: eauSettingsData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_update_member_tag',
                    nonce: eauSettingsData.nonce,
                    id: tagId,
                    name: name,
                    color: color,
                    description: description
                },
                success: function(response) {
                    if (response.success) {
                        // Update tag in local array
                        const index = self.tags.findIndex(t => t.id === tagId);
                        if (index !== -1) {
                            self.tags[index] = response.data.tag;
                        }
                        self.editingTagId = null;
                        self.renderTags();
                        EauNotifications.success('Success', 'Tag updated successfully.');
                    } else {
                        EauNotifications.error('Error', response.data.message || 'Failed to update tag.');
                    }
                },
                error: function() {
                    EauNotifications.error('Network Error', 'Failed to update tag.');
                }
            });
        },

        /**
         * Cancel tag edit
         */
        cancelEditTag: function() {
            this.editingTagId = null;
            this.renderTags();
        },

        /**
         * Delete tag
         */
        deleteTag: function(tagId) {
            const self = this;
            const tag = this.tags.find(t => t.id === tagId);

            if (!tag) return;

            EauNotifications.confirm({
                title: 'Delete Tag?',
                message: `Are you sure you want to delete the tag "${tag.name}"? This will remove it from all members.`,
                type: 'danger',
                confirmText: 'Delete',
                cancelText: 'Cancel',
                onConfirm: function() {
                    $.ajax({
                        url: eauSettingsData.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'eau_delete_member_tag',
                            nonce: eauSettingsData.nonce,
                            id: tagId
                        },
                        success: function(response) {
                            if (response.success) {
                                self.tags = self.tags.filter(t => t.id !== tagId);
                                self.renderTags();
                                EauNotifications.success('Success', 'Tag deleted successfully.');
                            } else {
                                EauNotifications.error('Error', response.data.message || 'Failed to delete tag.');
                            }
                        },
                        error: function() {
                            EauNotifications.error('Network Error', 'Failed to delete tag.');
                        }
                    });
                }
            });
        },

        /**
         * Escape HTML
         */
        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        /**
         * Get current settings from form
         */
        getFormSettings: function() {
            return {
                activity_approval_mode: $('input[name="approval_mode"]:checked').val() || 'manual'
            };
        },

        /**
         * Save settings via AJAX
         */
        saveSettings: function() {
            const self = this;
            const $btn = $('#eau-save-settings-btn');
            const originalHtml = $btn.html();

            // Disable button and show loading
            $btn.prop('disabled', true).html('<i data-lucide="loader-2" class="eau-spin"></i> Saving...');

            // Re-init icons for loading spinner
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }

            const settings = this.getFormSettings();

            $.ajax({
                url: eauSettingsData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_save_settings',
                    nonce: eauSettingsData.nonce,
                    settings: settings
                },
                success: function(response) {
                    if (response.success) {
                        EauNotifications.success('Settings Saved', response.data.message || 'Your settings have been updated.');
                        self.settings = settings;
                    } else {
                        EauNotifications.error('Error', response.data.message || 'Failed to save settings.');
                    }
                },
                error: function() {
                    EauNotifications.error('Network Error', 'Please check your connection and try again.');
                },
                complete: function() {
                    // Re-enable button
                    $btn.prop('disabled', false).html(originalHtml);

                    // Re-init icons
                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                }
            });
        },

        /**
         * Recreate all pages (v1.57.4)
         * Deletes all existing pages and recreates them
         */
        recreateMissingPages: function() {
            const self = this;

            // Confirmation dialog
            EauNotifications.confirm({
                title: 'Recreate All Pages?',
                message: 'This will delete all existing system pages and create new ones. Any manual changes to these pages will be lost.',
                type: 'warning',
                confirmText: 'Recreate All',
                cancelText: 'Cancel',
                onConfirm: function() {
                    self.executeRecreatePages();
                }
            });
        },

        /**
         * Execute the recreate pages action
         */
        executeRecreatePages: function() {
            const $btn = $('#eau-recreate-pages-btn');
            const originalHtml = $btn.html();

            // Disable button and show loading
            $btn.prop('disabled', true).html('<i data-lucide="loader-2" class="eau-spin"></i> Recreating...');

            // Re-init icons for loading spinner
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }

            $.ajax({
                url: eauSettingsData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_recreate_missing_pages',
                    nonce: eauSettingsData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        const data = response.data;
                        EauNotifications.success('Pages Recreated', `Successfully created ${data.created} page(s).`);
                        // Reload page to show updated list
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        EauNotifications.error('Error', response.data.message || 'Failed to recreate pages.');
                    }
                },
                error: function() {
                    EauNotifications.error('Network Error', 'Please check your connection and try again.');
                },
                complete: function() {
                    // Re-enable button
                    $btn.prop('disabled', false).html(originalHtml);

                    // Re-init icons
                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                }
            });
        },

        /**
         * Save email exemptions (v1.66.8)
         */
        saveEmailExemptions: function() {
            const $btn = $('#eau-save-email-exempt-btn');
            const $textarea = $('#eau-email-migration-exempt');
            const originalHtml = $btn.html();
            const emails = $textarea.val().trim();

            // Disable button and show loading
            $btn.prop('disabled', true).html('<i data-lucide="loader-2" class="eau-spin"></i> Saving...');

            // Re-init icons for loading spinner
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }

            $.ajax({
                url: eauSettingsData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_save_email_exempt',
                    nonce: eauSettingsData.nonce,
                    emails: emails
                },
                success: function(response) {
                    if (response.success) {
                        const count = response.data.count || 0;
                        EauNotifications.success(
                            'Exemptions Saved',
                            count > 0
                                ? `${count} email(s) added to exemption list.`
                                : 'Exemption list cleared.'
                        );
                    } else {
                        EauNotifications.error('Error', response.data.message || 'Failed to save exemptions.');
                    }
                },
                error: function() {
                    EauNotifications.error('Network Error', 'Please check your connection and try again.');
                },
                complete: function() {
                    // Re-enable button
                    $btn.prop('disabled', false).html(originalHtml);

                    // Re-init icons
                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                }
            });
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        EauSettingsController.init();
    });

    // ============================================
    // Membership Import Controller (v1.55.0)
    // ============================================

    const EauMembershipImportController = {
        // State
        csvFilename: '',
        rowCount: 0,
        totalUpdated: 0,
        totalCreated: 0,
        totalSkipped: 0,

        /**
         * Initialize controller
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            const self = this;

            // Open modal button
            $('#eau-import-membership-btn').on('click', function() {
                self.openModal();
            });

            // Close modal
            $(document).on('click', '.eau-modal-close-membership, #eau-membership-close-modal', function() {
                self.closeModal();
            });

            // Close on overlay click
            $(document).on('click', '#eau-import-membership-modal .eau-modal-overlay', function() {
                self.closeModal();
            });

            // Upload form submit
            $('#eau-import-membership-upload-form').on('submit', function(e) {
                e.preventDefault();
                self.uploadCSV();
            });

            // Back to step 1
            $('#eau-membership-back-to-step1').on('click', function() {
                self.showStep(1);
            });

            // Start import
            $('#eau-membership-start-import').on('click', function() {
                self.startImport();
            });
        },

        /**
         * Open modal
         */
        openModal: function() {
            this.resetState();
            $('#eau-import-membership-modal').addClass('active');
            this.showStep(1);

            // Initialize Lucide icons in modal
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        },

        /**
         * Close modal
         */
        closeModal: function() {
            $('#eau-import-membership-modal').removeClass('active');
            this.resetState();
        },

        /**
         * Reset state
         */
        resetState: function() {
            this.csvFilename = '';
            this.rowCount = 0;
            this.totalUpdated = 0;
            this.totalCreated = 0;
            this.totalSkipped = 0;

            // Reset form
            $('#eau-import-membership-upload-form')[0].reset();

            // Clear preview
            $('#eau-membership-preview-stats').html('');
            $('#eau-membership-preview-table tbody').html('');

            // Clear log
            $('#eau-membership-import-log').html('');

            // Reset progress
            $('#eau-membership-progress-fill').css('width', '0%');
            $('#eau-membership-progress-text').text('Preparing...');

            // Clear summary
            $('#eau-membership-import-summary').html('');
        },

        /**
         * Show specific step
         */
        showStep: function(step) {
            $('.eau-import-step').hide();
            $(`#eau-import-membership-step-${step}`).show();
        },

        /**
         * Upload CSV for analysis
         */
        uploadCSV: function() {
            const self = this;
            const fileInput = $('#membership_csv_file')[0];

            if (!fileInput.files || fileInput.files.length === 0) {
                EauNotifications.warning('Warning', 'Please select a CSV file.');
                return;
            }

            const file = fileInput.files[0];

            // Validate file type
            if (!file.name.toLowerCase().endsWith('.csv')) {
                EauNotifications.error('Invalid File', 'Please select a CSV file.');
                return;
            }

            // Validate file size (10MB max)
            if (file.size > 10 * 1024 * 1024) {
                EauNotifications.error('File Too Large', 'Maximum file size is 10MB.');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'eau_import_membership_analyze_csv');
            formData.append('nonce', eauSettingsData.nonce);
            formData.append('csv_file', file);

            const $btn = $('#eau-import-membership-upload-form button[type="submit"]');
            const originalHtml = $btn.html();
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Analyzing...');

            $.ajax({
                url: eauSettingsData.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        self.csvFilename = response.data.filename;
                        self.rowCount = response.data.row_count;
                        self.showPreview(response.data);
                    } else {
                        EauNotifications.error('Error', response.data.message || 'Failed to analyze CSV.');
                    }
                },
                error: function() {
                    EauNotifications.error('Network Error', 'Failed to upload CSV file.');
                },
                complete: function() {
                    $btn.prop('disabled', false).html(originalHtml);
                }
            });
        },

        /**
         * Show preview (Step 2)
         */
        showPreview: function(data) {
            const self = this;

            // Build stats HTML
            const statsHtml = `
                <div class="eau-stat-box total">
                    <div class="eau-stat-number">${data.row_count}</div>
                    <div class="eau-stat-label">Total Rows</div>
                </div>
                <div class="eau-stat-box update">
                    <div class="eau-stat-number">${data.existing_count}</div>
                    <div class="eau-stat-label">Will Update</div>
                </div>
                <div class="eau-stat-box create">
                    <div class="eau-stat-number">${data.new_count}</div>
                    <div class="eau-stat-label">Will Create</div>
                </div>
            `;
            $('#eau-membership-preview-stats').html(statsHtml);

            // Build preview table
            let tableHtml = '';
            if (data.preview && data.preview.length > 0) {
                data.preview.forEach(function(row) {
                    const actionClass = row.action === 'update' ? 'update' : 'create';
                    const actionLabel = row.action === 'update' ? 'UPDATE' : 'CREATE';
                    tableHtml += `
                        <tr>
                            <td>${self.escapeHtml(row.email)}</td>
                            <td>${self.escapeHtml(row.name)}</td>
                            <td>${self.escapeHtml(row.type)}</td>
                            <td>${self.escapeHtml(row.status)}</td>
                            <td>${self.escapeHtml(row.expiry)}</td>
                            <td><span class="eau-action-badge ${actionClass}">${actionLabel}</span></td>
                        </tr>
                    `;
                });
            }
            $('#eau-membership-preview-table tbody').html(tableHtml);

            this.showStep(2);

            // Re-initialize Lucide icons
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        },

        /**
         * Start import process
         */
        startImport: function() {
            this.showStep(3);
            this.totalUpdated = 0;
            this.totalCreated = 0;
            this.totalSkipped = 0;
            $('#eau-membership-import-log').html('');

            this.logMessage('info', 'Starting import...');
            this.processBatch(0);
        },

        /**
         * Process batch
         */
        processBatch: function(offset, retryCount) {
            const self = this;
            retryCount = retryCount || 0;
            const maxRetries = 3;

            $.ajax({
                url: eauSettingsData.ajaxUrl,
                type: 'POST',
                timeout: 60000, // 60 seconds timeout
                data: {
                    action: 'eau_import_membership_batch',
                    nonce: eauSettingsData.nonce,
                    filename: this.csvFilename,
                    offset: offset,
                    limit: 10 // Reduced batch size to avoid timeout
                },
                success: function(response) {
                    if (response.success) {
                        const data = response.data;

                        self.totalUpdated += data.updated || 0;
                        self.totalCreated += data.created || 0;
                        self.totalSkipped += data.skipped || 0;

                        // Update progress
                        const progress = Math.round((data.processed / data.total) * 100);
                        $('#eau-membership-progress-fill').css('width', progress + '%');
                        $('#eau-membership-progress-text').text(
                            `Processed ${data.processed} of ${data.total} (${progress}%)`
                        );

                        // Log results
                        if (data.updated > 0) {
                            self.logMessage('success', `Batch: ${data.updated} updated`);
                        }
                        if (data.created > 0) {
                            self.logMessage('warning', `Batch: ${data.created} created`);
                        }
                        if (data.skipped > 0) {
                            self.logMessage('error', `Batch: ${data.skipped} skipped`);
                        }

                        // Log errors
                        if (data.errors && data.errors.length > 0) {
                            data.errors.forEach(function(error) {
                                self.logMessage('error', error);
                            });
                        }

                        // Continue or finish
                        if (data.has_more) {
                            self.processBatch(data.processed);
                        } else {
                            self.showComplete(data);
                        }
                    } else {
                        EauNotifications.error('Error', response.data.message || 'Import failed.');
                        self.logMessage('error', response.data.message || 'Import failed.');
                    }
                },
                error: function(xhr, status, error) {
                    if (retryCount < maxRetries) {
                        self.logMessage('warning', `Network error. Retrying (${retryCount + 1}/${maxRetries})...`);
                        // Wait 2 seconds before retry
                        setTimeout(function() {
                            self.processBatch(offset, retryCount + 1);
                        }, 2000);
                    } else {
                        EauNotifications.error('Network Error', 'Import interrupted after multiple retries.');
                        self.logMessage('error', 'Network error - import interrupted after ' + maxRetries + ' retries.');
                        self.logMessage('info', 'You can try again from the beginning.');
                    }
                }
            });
        },

        /**
         * Show import complete (Step 4)
         */
        showComplete: function(data) {
            this.logMessage('success', 'Import complete!');

            const summaryHtml = `
                <div class="eau-summary-grid">
                    <div class="eau-summary-item">
                        <div class="eau-summary-number updated">${this.totalUpdated}</div>
                        <div class="eau-summary-label">Members Updated</div>
                    </div>
                    <div class="eau-summary-item">
                        <div class="eau-summary-number created">${this.totalCreated}</div>
                        <div class="eau-summary-label">Members Created</div>
                    </div>
                    <div class="eau-summary-item">
                        <div class="eau-summary-number skipped">${this.totalSkipped}</div>
                        <div class="eau-summary-label">Rows Skipped</div>
                    </div>
                </div>
                <p style="text-align: center; margin-top: 15px;">
                    <strong>Total processed:</strong> ${data.total} rows
                </p>
            `;
            $('#eau-membership-import-summary').html(summaryHtml);

            this.showStep(4);
            EauNotifications.success('Import Complete', `Successfully processed ${data.total} rows.`);
        },

        /**
         * Log message
         */
        logMessage: function(type, message) {
            const $log = $('#eau-membership-import-log');
            const timestamp = new Date().toLocaleTimeString();
            $log.append(`<p class="eau-log-${type}">[${timestamp}] ${this.escapeHtml(message)}</p>`);
            $log.scrollTop($log[0].scrollHeight);
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

    // Initialize Membership Import Controller
    $(document).ready(function() {
        // Only initialize if the import button exists
        if ($('#eau-import-membership-btn').length > 0) {
            EauMembershipImportController.init();
        }
    });

    // ============================================
    // Institution Import Controller (v1.54.0)
    // ============================================

    const EauInstitutionImportController = {
        // State
        csvFilename: '',
        totalInstitutions: 0,
        totalUpdated: 0,
        totalCreated: 0,
        totalSkipped: 0,

        /**
         * Initialize controller
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            const self = this;

            // Open modal button
            $('#eau-import-institution-btn').on('click', function() {
                self.openModal();
            });

            // Close modal
            $(document).on('click', '.eau-modal-close-institution, #eau-institution-close-modal', function() {
                self.closeModal();
            });

            // Close on overlay click
            $(document).on('click', '#eau-import-institution-modal .eau-modal-overlay', function() {
                self.closeModal();
            });

            // Upload form submit
            $('#eau-import-institution-upload-form').on('submit', function(e) {
                e.preventDefault();
                self.uploadCSV();
            });

            // Back to step 1
            $('#eau-institution-back-to-step1').on('click', function() {
                self.showStep(1);
            });

            // Start import
            $('#eau-institution-start-import').on('click', function() {
                self.startImport();
            });
        },

        /**
         * Open modal
         */
        openModal: function() {
            this.resetState();
            $('#eau-import-institution-modal').addClass('active');
            this.showStep(1);

            // Initialize Lucide icons in modal
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        },

        /**
         * Close modal
         */
        closeModal: function() {
            $('#eau-import-institution-modal').removeClass('active');
            this.resetState();
        },

        /**
         * Reset state
         */
        resetState: function() {
            this.csvFilename = '';
            this.totalInstitutions = 0;
            this.totalUpdated = 0;
            this.totalCreated = 0;
            this.totalSkipped = 0;

            // Reset form
            $('#eau-import-institution-upload-form')[0].reset();

            // Clear preview
            $('#eau-institution-preview-stats').html('');
            $('#eau-institution-preview-table tbody').html('');

            // Clear log
            $('#eau-institution-import-log').html('');

            // Reset progress
            $('#eau-institution-progress-fill').css('width', '0%');
            $('#eau-institution-progress-text').text('Preparing...');

            // Clear summary
            $('#eau-institution-import-summary').html('');
        },

        /**
         * Show specific step
         */
        showStep: function(step) {
            $('#eau-import-institution-modal .eau-import-step').hide();
            $(`#eau-import-institution-step-${step}`).show();
        },

        /**
         * Upload CSV for analysis
         */
        uploadCSV: function() {
            const self = this;
            const fileInput = $('#institution_csv_file')[0];

            if (!fileInput.files || fileInput.files.length === 0) {
                EauNotifications.warning('Warning', 'Please select a CSV file.');
                return;
            }

            const file = fileInput.files[0];

            // Validate file type
            if (!file.name.toLowerCase().endsWith('.csv')) {
                EauNotifications.error('Invalid File', 'Please select a CSV file.');
                return;
            }

            // Validate file size (10MB max)
            if (file.size > 10 * 1024 * 1024) {
                EauNotifications.error('File Too Large', 'Maximum file size is 10MB.');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'eau_import_institution_analyze_csv');
            formData.append('nonce', eauSettingsData.nonce);
            formData.append('csv_file', file);

            const $btn = $('#eau-import-institution-upload-form button[type="submit"]');
            const originalHtml = $btn.html();
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Analyzing...');

            $.ajax({
                url: eauSettingsData.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        self.csvFilename = response.data.filename;
                        self.totalInstitutions = response.data.total_institutions;
                        self.showPreview(response.data);
                    } else {
                        EauNotifications.error('Error', response.data.message || 'Failed to analyze CSV.');
                    }
                },
                error: function() {
                    EauNotifications.error('Network Error', 'Failed to upload CSV file.');
                },
                complete: function() {
                    $btn.prop('disabled', false).html(originalHtml);
                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                }
            });
        },

        /**
         * Show preview (Step 2)
         */
        showPreview: function(data) {
            const self = this;

            // Store format for later use
            this.csvFormat = data.format || 'legacy';

            // Show format info badge
            const formatClass = this.csvFormat === 'membership' ? 'membership' : 'legacy';
            const formatLabel = data.format_name || (this.csvFormat === 'membership' ? 'MembershipDetails' : 'Legacy');
            const formatHtml = `
                <span class="eau-format-badge ${formatClass}">
                    <i data-lucide="${this.csvFormat === 'membership' ? 'calendar-check' : 'building-2'}"></i>
                    ${formatLabel}
                </span>
                <span style="color: #666; font-size: 13px;">Format detected automatically</span>
            `;
            $('#eau-institution-format-info').html(formatHtml);

            // Build stats HTML
            let statsHtml = `
                <div class="eau-stat-box total">
                    <div class="eau-stat-number">${data.total_institutions}</div>
                    <div class="eau-stat-label">Unique Institutions</div>
                </div>
                <div class="eau-stat-box update">
                    <div class="eau-stat-number">${data.will_update}</div>
                    <div class="eau-stat-label">Will Update</div>
                </div>
                <div class="eau-stat-box create">
                    <div class="eau-stat-number">${data.will_create}</div>
                    <div class="eau-stat-label">Will Create</div>
                </div>
            `;

            // Show delete stat if there are institutions to delete
            if (data.will_delete && data.will_delete > 0) {
                statsHtml += `
                    <div class="eau-stat-box delete">
                        <div class="eau-stat-number">${data.will_delete}</div>
                        <div class="eau-stat-label">Not in CSV</div>
                    </div>
                `;
            }
            $('#eau-institution-preview-stats').html(statsHtml);

            // Show sync option for membership format
            if (this.csvFormat === 'membership' && data.will_delete > 0) {
                $('#eau-institution-sync-option').show();
                $('#eau-institution-delete-count').text('(' + data.will_delete + ' institutions)');
            } else {
                $('#eau-institution-sync-option').hide();
            }

            // Update table headers based on format
            let theadHtml = '';
            if (this.csvFormat === 'membership') {
                theadHtml = `
                    <tr>
                        <th>Company ID</th>
                        <th>Company Name</th>
                        <th>Membership Type</th>
                        <th>Start Date</th>
                        <th>Expiry Date</th>
                        <th>Action</th>
                    </tr>
                `;
            } else {
                theadHtml = `
                    <tr>
                        <th>Company ID</th>
                        <th>Company Name</th>
                        <th>Email</th>
                        <th>Type</th>
                        <th>State</th>
                        <th>Action</th>
                    </tr>
                `;
            }
            $('#eau-institution-preview-thead').html(theadHtml);

            // Build preview table rows
            let tableHtml = '';
            if (data.preview && data.preview.length > 0) {
                data.preview.forEach(function(row) {
                    const actionClass = row.action === 'update' ? 'update' : 'create';
                    const actionLabel = row.action === 'update' ? 'UPDATE' : 'CREATE';

                    if (self.csvFormat === 'membership') {
                        tableHtml += `
                            <tr>
                                <td>${self.escapeHtml(row.company_id)}</td>
                                <td>${self.escapeHtml(row.company_name)}</td>
                                <td>${self.escapeHtml(row.membership_type || '')}</td>
                                <td>${self.escapeHtml(row.start_date || '')}</td>
                                <td>${self.escapeHtml(row.expiry_date || '')}</td>
                                <td><span class="eau-action-badge ${actionClass}">${actionLabel}</span></td>
                            </tr>
                        `;
                    } else {
                        tableHtml += `
                            <tr>
                                <td>${self.escapeHtml(row.company_id)}</td>
                                <td>${self.escapeHtml(row.company_name)}</td>
                                <td>${self.escapeHtml(row.email || '')}</td>
                                <td>${self.escapeHtml(row.type || '')}</td>
                                <td>${self.escapeHtml(row.state || '')}</td>
                                <td><span class="eau-action-badge ${actionClass}">${actionLabel}</span></td>
                            </tr>
                        `;
                    }
                });
            }
            $('#eau-institution-preview-table tbody').html(tableHtml);

            this.showStep(2);

            // Re-initialize Lucide icons
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        },

        /**
         * Start import process
         */
        startImport: function() {
            this.showStep(3);
            this.totalUpdated = 0;
            this.totalCreated = 0;
            this.totalSkipped = 0;
            this.totalDeleted = 0;
            this.syncDelete = $('#eau-institution-sync-delete').is(':checked');
            $('#eau-institution-import-log').html('');

            this.logMessage('info', 'Starting institution import...');
            if (this.syncDelete) {
                this.logMessage('warning', 'Full sync enabled - institutions not in CSV will be deleted.');
            }
            this.processBatch(0);
        },

        /**
         * Process batch
         */
        processBatch: function(offset, retryCount) {
            const self = this;
            retryCount = retryCount || 0;
            const maxRetries = 3;

            $.ajax({
                url: eauSettingsData.ajaxUrl,
                type: 'POST',
                timeout: 60000, // 60 seconds timeout
                data: {
                    action: 'eau_import_institution_batch',
                    nonce: eauSettingsData.nonce,
                    filename: this.csvFilename,
                    offset: offset,
                    limit: 25,
                    sync_delete: this.syncDelete ? 1 : 0
                },
                success: function(response) {
                    if (response.success) {
                        const data = response.data;

                        self.totalUpdated += data.updated || 0;
                        self.totalCreated += data.created || 0;
                        self.totalSkipped += data.skipped || 0;
                        self.totalDeleted += data.deleted || 0;

                        // Update progress
                        const progress = Math.round((data.processed / data.total) * 100);
                        $('#eau-institution-progress-fill').css('width', progress + '%');
                        $('#eau-institution-progress-text').text(
                            `Processed ${data.processed} of ${data.total} institutions (${progress}%)`
                        );

                        // Log results
                        if (data.updated > 0) {
                            self.logMessage('success', `Batch: ${data.updated} institutions updated`);
                        }
                        if (data.created > 0) {
                            self.logMessage('warning', `Batch: ${data.created} institutions created`);
                        }
                        if (data.skipped > 0) {
                            self.logMessage('error', `Batch: ${data.skipped} institutions skipped`);
                        }
                        if (data.deleted > 0) {
                            self.logMessage('info', `Sync: ${data.deleted} institutions deleted (not in CSV)`);
                        }

                        // Log errors
                        if (data.errors && data.errors.length > 0) {
                            data.errors.forEach(function(error) {
                                self.logMessage('error', `Row ${error.row}: ${error.message}`);
                            });
                        }

                        // Continue or finish
                        if (data.has_more) {
                            self.processBatch(data.processed);
                        } else {
                            self.showComplete(data);
                        }
                    } else {
                        EauNotifications.error('Error', response.data.message || 'Import failed.');
                        self.logMessage('error', response.data.message || 'Import failed.');
                    }
                },
                error: function(xhr, status, error) {
                    if (retryCount < maxRetries) {
                        self.logMessage('warning', `Network error. Retrying (${retryCount + 1}/${maxRetries})...`);
                        // Wait 2 seconds before retry
                        setTimeout(function() {
                            self.processBatch(offset, retryCount + 1);
                        }, 2000);
                    } else {
                        EauNotifications.error('Network Error', 'Import interrupted after multiple retries.');
                        self.logMessage('error', 'Network error - import interrupted after ' + maxRetries + ' retries.');
                        self.logMessage('info', 'You can try again from the beginning.');
                    }
                }
            });
        },

        /**
         * Show import complete (Step 4)
         */
        showComplete: function(data) {
            this.logMessage('success', 'Institution import complete!');

            // Build deleted item HTML conditionally
            const deletedItemHtml = this.totalDeleted > 0 ? `
                    <div class="eau-summary-item">
                        <div class="eau-summary-number deleted">${this.totalDeleted}</div>
                        <div class="eau-summary-label">Institutions Deleted</div>
                    </div>` : '';

            const summaryHtml = `
                <div class="eau-summary-grid${this.totalDeleted > 0 ? ' eau-summary-grid-4' : ''}">
                    <div class="eau-summary-item">
                        <div class="eau-summary-number updated">${this.totalUpdated}</div>
                        <div class="eau-summary-label">Institutions Updated</div>
                    </div>
                    <div class="eau-summary-item">
                        <div class="eau-summary-number created">${this.totalCreated}</div>
                        <div class="eau-summary-label">Institutions Created</div>
                    </div>
                    <div class="eau-summary-item">
                        <div class="eau-summary-number skipped">${this.totalSkipped}</div>
                        <div class="eau-summary-label">Institutions Skipped</div>
                    </div>
                    ${deletedItemHtml}
                </div>
                <p style="text-align: center; margin-top: 15px;">
                    <strong>Total processed:</strong> ${data.total} unique institutions
                </p>
            `;
            $('#eau-institution-import-summary').html(summaryHtml);

            this.showStep(4);
            EauNotifications.success('Import Complete', `Successfully processed ${data.total} institutions.`);
        },

        /**
         * Log message
         */
        logMessage: function(type, message) {
            const $log = $('#eau-institution-import-log');
            const timestamp = new Date().toLocaleTimeString();
            $log.append(`<p class="eau-log-${type}">[${timestamp}] ${this.escapeHtml(message)}</p>`);
            $log.scrollTop($log[0].scrollHeight);
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

    // Initialize Institution Import Controller
    $(document).ready(function() {
        // Only initialize if the import button exists
        if ($('#eau-import-institution-btn').length > 0) {
            EauInstitutionImportController.init();
        }
    });

    // ============================================
    // Categories Controller (v1.60.0)
    // Integrated from class-eau-categories-management.php
    // ============================================

    const EauCategoriesController = {

        // State
        currentPage: 1,
        perPage: 20,
        searchTerm: '',
        selectedIds: [],
        orderBy: 'category_name',
        order: 'ASC',

        // Import state
        importFilename: '',
        importStep: 'upload',

        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.loadCategories();
        },

        /**
         * Bind events
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

            // Export Categories
            $('#eau-export-categories').on('click', this.handleExportCategories.bind(this));

            // Import Categories
            $('#eau-import-categories').on('click', this.handleImportCategoriesModal.bind(this));
            $('#import-btn-cancel').on('click', this.handleImportClose.bind(this));
            $('#import-btn-back').on('click', this.handleImportBack.bind(this));
            $('#import-btn-analyze').on('click', this.handleImportAnalyze.bind(this));
            $('#import-btn-execute').on('click', this.handleImportExecute.bind(this));
            $('#import-btn-close').on('click', this.handleImportClose.bind(this));
            $('[data-modal-close]').on('click', this.handleImportClose.bind(this));

            // Table actions
            $(document).on('click', '.eau-action-view', this.handleViewCategory.bind(this));
            $(document).on('click', '.eau-action-edit', this.handleEditCategory.bind(this));
            $(document).on('click', '.eau-action-delete', this.handleDeleteCategory.bind(this));

            // Modal close (Eau_Modal component uses data-modal-action)
            $(document).on('click', '.eau-modal-close, [data-modal-action="close"]', this.handleCloseModal.bind(this));

            // Modal save (Eau_Modal component uses data-modal-action)
            $(document).on('click', '[data-modal-action="save"]', this.handleSaveCategory.bind(this));
            $(document).on('click', '[data-modal-action="create"]', this.handleSaveCategory.bind(this));

            // Sortable columns
            $(document).on('click', '#categories-table .eau-table-th.eau-sortable', this.handleSort.bind(this));

            // Pagination
            $(document).on('click', '#eau-categories-pagination .eau-pagination-btn', function(e) {
                e.preventDefault();
                if ($(this).is(':disabled') || $(this).hasClass('eau-pagination-active')) {
                    return;
                }
                const page = parseInt($(this).data('page'));
                self.currentPage = page;
                self.loadCategories();
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
                url: eauSettingsData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_get_categories',
                    nonce: eauSettingsData.categoriesNonce,
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
                        // Update table count
                        self.updateTableCount(response.data.pagination.total);
                    } else {
                        EauNotifications.error('Error', response.data.message || 'Failed to load categories');
                    }
                },
                error: function() {
                    EauNotifications.error('Error', 'Failed to load categories');
                },
                complete: function() {
                    self.hideSkeleton();
                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                }
            });
        },

        /**
         * Load statistics
         */
        loadStats: function() {
            const self = this;

            $.ajax({
                url: eauSettingsData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_get_categories_stats',
                    nonce: eauSettingsData.categoriesNonce
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
            const $container = $('.eau-categories-tab-container');
            $container.find('.eau-stat-card').eq(0).find('.eau-stat-number').text(stats.total);
            $container.find('.eau-stat-card').eq(1).find('.eau-stat-number').text(stats.configured);
            $container.find('.eau-stat-card').eq(2).find('.eau-stat-number').text(stats.not_configured);
            $container.find('.eau-stat-card').eq(3).find('.eau-stat-number').text(stats.avg_points);
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
                        <td colspan="5" class="eau-table-td" style="text-align: center;">
                            <div class="eau-empty-state">
                                <i data-lucide="inbox"></i>
                                <p>No categories found</p>
                            </div>
                        </td>
                    </tr>
                `);
                return;
            }

            categories.forEach(category => {
                const pointsBadgeClass = category.points_per_hour_raw > 0 ? 'eau-points-badge' : 'eau-points-badge eau-points-badge-zero';

                const row = `
                    <tr class="eau-table-tr">
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
        },

        /**
         * Render pagination
         */
        renderPagination: function(pagination) {
            const totalPages = pagination.total_pages || 1;
            const $container = $('#eau-categories-pagination');

            if (totalPages <= 1) {
                $container.html('');
                return;
            }

            const startItem = ((pagination.page - 1) * pagination.per_page) + 1;
            const endItem = Math.min(pagination.page * pagination.per_page, pagination.total);

            const html = this.buildPaginationHTML(pagination.page, totalPages, startItem, endItem, pagination.total);
            $container.html(html);

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        },

        /**
         * Build pagination HTML
         */
        buildPaginationHTML: function(currentPage, totalPages, startItem, endItem, total) {
            const pagesToShow = this.getPagesToShow(currentPage, totalPages);

            let html = '<div class="eau-pagination-wrapper">';

            html += '<div class="eau-pagination-info">';
            html += `Showing ${startItem.toLocaleString()} to ${endItem.toLocaleString()} of ${total.toLocaleString()} categories`;
            html += '</div>';

            html += '<div class="eau-pagination-nav">';

            const prevDisabled = currentPage <= 1 ? 'disabled' : '';
            html += `<button class="eau-pagination-btn eau-pagination-prev ${prevDisabled}" data-page="${Math.max(1, currentPage - 1)}" ${prevDisabled ? 'disabled' : ''}>`;
            html += '<i data-lucide="chevron-left"></i>';
            html += '</button>';

            pagesToShow.forEach(function(page) {
                if (page === '...') {
                    html += '<span class="eau-pagination-ellipsis">...</span>';
                } else {
                    const activeClass = page === currentPage ? 'eau-pagination-active' : '';
                    html += `<button class="eau-pagination-btn eau-pagination-number ${activeClass}" data-page="${page}">${page}</button>`;
                }
            });

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
                url: eauSettingsData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_sync_categories',
                    nonce: eauSettingsData.categoriesNonce
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
                        url: eauSettingsData.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'eau_delete_category',
                            nonce: eauSettingsData.categoriesNonce,
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
                url: eauSettingsData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_get_category',
                    nonce: eauSettingsData.categoriesNonce,
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

            $.ajax({
                url: eauSettingsData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_generate_category_serial',
                    nonce: eauSettingsData.categoriesNonce
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
                nonce: eauSettingsData.categoriesNonce
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

            if (!data.category_serial || !data.category_name) {
                EauNotifications.error('Validation Error', 'Please fill in all required fields');
                return;
            }

            const self = this;

            $.ajax({
                url: eauSettingsData.ajaxUrl,
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

            const self = this;
            $overlay.off('click').on('click', function(e) {
                if ($(e.target).hasClass('eau-modal-overlay')) {
                    self.closeModal(modalId);
                }
            });

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
        },

        /**
         * Update table count display
         */
        updateTableCount: function(total) {
            $('#categories-table-count .eau-count-number').text(total.toLocaleString());
        },

        // ============================================
        // Export/Import Functions
        // ============================================

        /**
         * Handle export categories
         */
        handleExportCategories: function(e) {
            e.preventDefault();
            const self = this;

            const $btn = $('#eau-export-categories');
            const originalHtml = $btn.html();
            $btn.prop('disabled', true).html('<i data-lucide="loader"></i> Exporting...');

            $.ajax({
                url: eauSettingsData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_export_categories',
                    nonce: eauSettingsData.categoriesNonce
                },
                success: function(response) {
                    if (response.success) {
                        const data = response.data;
                        const jsonStr = JSON.stringify(data, null, 2);
                        const blob = new Blob([jsonStr], { type: 'application/json' });
                        const url = URL.createObjectURL(blob);

                        const filename = 'eau-categories-export-' + self.formatDateForFilename() + '.json';
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = filename;
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                        URL.revokeObjectURL(url);

                        EauNotifications.success('Export Complete', `Exported ${data.total_categories} categories to ${filename}`);
                    } else {
                        EauNotifications.error('Export Failed', response.data.message || 'Failed to export categories');
                    }
                },
                error: function() {
                    EauNotifications.error('Export Failed', 'Network error occurred');
                },
                complete: function() {
                    $btn.prop('disabled', false).html(originalHtml);
                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                }
            });
        },

        /**
         * Format date for filename
         */
        formatDateForFilename: function() {
            const now = new Date();
            const year = now.getFullYear();
            const month = String(now.getMonth() + 1).padStart(2, '0');
            const day = String(now.getDate()).padStart(2, '0');
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            return `${year}-${month}-${day}-${hours}${minutes}`;
        },

        /**
         * Handle import categories modal open
         */
        handleImportCategoriesModal: function(e) {
            e.preventDefault();
            this.importFilename = '';
            this.importStep = 'upload';
            this.showImportStep('upload');
            $('#import-categories-form')[0].reset();
            $('#eau-modal-import-overlay').css('display', 'flex').hide().fadeIn(200);
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        },

        /**
         * Handle import close
         */
        handleImportClose: function(e) {
            if (e) e.preventDefault();
            $('#eau-modal-import-overlay').fadeOut(200);
        },

        /**
         * Handle import back
         */
        handleImportBack: function(e) {
            e.preventDefault();
            this.showImportStep('upload');
        },

        /**
         * Show import step
         */
        showImportStep: function(step) {
            this.importStep = step;

            $('.import-step').hide();
            $('#import-step-' + step).show();

            $('#import-btn-cancel').toggle(step === 'upload');
            $('#import-btn-back').toggle(step === 'preview');
            $('#import-btn-analyze').toggle(step === 'upload');
            $('#import-btn-execute').toggle(step === 'preview');
            $('#import-btn-close').toggle(step === 'result');
        },

        /**
         * Handle import analyze
         */
        handleImportAnalyze: function(e) {
            e.preventDefault();
            const self = this;

            const fileInput = $('#import-json-file')[0];
            if (!fileInput.files || fileInput.files.length === 0) {
                EauNotifications.warning('No File', 'Please select a JSON file to import');
                return;
            }

            const file = fileInput.files[0];

            if (!file.name.toLowerCase().endsWith('.json')) {
                EauNotifications.error('Invalid File', 'Please select a JSON file');
                return;
            }

            if (file.size > 5 * 1024 * 1024) {
                EauNotifications.error('File Too Large', 'Maximum file size is 5MB');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'eau_import_categories_analyze');
            formData.append('nonce', eauSettingsData.categoriesNonce);
            formData.append('json_file', file);

            const $btn = $('#import-btn-analyze');
            const originalHtml = $btn.html();
            $btn.prop('disabled', true).html('<i data-lucide="loader"></i> Analyzing...');

            $.ajax({
                url: eauSettingsData.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        self.importFilename = response.data.filename;
                        self.showImportPreview(response.data);
                    } else {
                        EauNotifications.error('Analysis Failed', response.data.message || 'Failed to analyze file');
                    }
                },
                error: function() {
                    EauNotifications.error('Analysis Failed', 'Network error occurred');
                },
                complete: function() {
                    $btn.prop('disabled', false).html(originalHtml);
                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                }
            });
        },

        /**
         * Show import preview
         */
        showImportPreview: function(data) {
            const statsHtml = `
                <div class="eau-preview-stat total">
                    <div class="eau-preview-stat-number">${data.total_categories}</div>
                    <div class="eau-preview-stat-label">Total Categories</div>
                </div>
                <div class="eau-preview-stat create">
                    <div class="eau-preview-stat-number">${data.will_create}</div>
                    <div class="eau-preview-stat-label">Will Create</div>
                </div>
                <div class="eau-preview-stat update">
                    <div class="eau-preview-stat-number">${data.will_update}</div>
                    <div class="eau-preview-stat-label">Will Update</div>
                </div>
            `;
            $('#import-preview-stats').html(statsHtml);

            let tableHtml = '';
            if (data.preview && data.preview.length > 0) {
                data.preview.forEach(function(cat) {
                    const actionClass = cat.action === 'update' ? 'import-action-badge update' : 'import-action-badge create';
                    const actionLabel = cat.action === 'update' ? 'UPDATE' : 'CREATE';
                    tableHtml += `
                        <tr>
                            <td style="padding: 8px 12px;">${cat.category_serial}</td>
                            <td style="padding: 8px 12px;">${cat.category_name}</td>
                            <td style="padding: 8px 12px;">${cat.points_per_hour}</td>
                            <td style="padding: 8px 12px;">
                                <span class="${actionClass}">${actionLabel}</span>
                            </td>
                        </tr>
                    `;
                });
            }
            $('#import-preview-table tbody').html(tableHtml);

            this.showImportStep('preview');
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        },

        /**
         * Handle import execute
         */
        handleImportExecute: function(e) {
            e.preventDefault();
            const self = this;

            if (!this.importFilename) {
                EauNotifications.error('Error', 'No file to import');
                return;
            }

            const skipExisting = $('#import-skip-existing').is(':checked');

            const $btn = $('#import-btn-execute');
            const originalHtml = $btn.html();
            $btn.prop('disabled', true).html('<i data-lucide="loader"></i> Importing...');

            $.ajax({
                url: eauSettingsData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_import_categories_execute',
                    nonce: eauSettingsData.categoriesNonce,
                    filename: this.importFilename,
                    skip_existing: skipExisting
                },
                success: function(response) {
                    if (response.success) {
                        self.showImportResult(response.data);
                        self.loadCategories();
                        self.loadStats();
                    } else {
                        EauNotifications.error('Import Failed', response.data.message || 'Failed to import categories');
                    }
                },
                error: function() {
                    EauNotifications.error('Import Failed', 'Network error occurred');
                },
                complete: function() {
                    $btn.prop('disabled', false).html(originalHtml);
                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                }
            });
        },

        /**
         * Show import result
         */
        showImportResult: function(data) {
            const html = `
                <div class="eau-import-result">
                    <div class="eau-import-result-icon success">
                        <i data-lucide="check-circle"></i>
                    </div>
                    <h3>Import Complete!</h3>
                    <p>Successfully processed ${data.total} categories</p>

                    <div style="display: flex; justify-content: center; gap: 30px; margin: 20px 0;">
                        <div>
                            <div style="font-size: 28px; font-weight: 700; color: #16a34a;">${data.updated}</div>
                            <div style="font-size: 12px; color: #666;">Updated</div>
                        </div>
                        <div>
                            <div style="font-size: 28px; font-weight: 700; color: #d97706;">${data.created}</div>
                            <div style="font-size: 12px; color: #666;">Created</div>
                        </div>
                        <div>
                            <div style="font-size: 28px; font-weight: 700; color: #6b7280;">${data.skipped}</div>
                            <div style="font-size: 12px; color: #666;">Skipped</div>
                        </div>
                    </div>
                </div>
            `;

            $('#import-result-content').html(html);
            this.showImportStep('result');

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }

            EauNotifications.success('Import Complete', `Imported ${data.total} categories: ${data.created} created, ${data.updated} updated`);
        }
    };

    // Make EauCategoriesController globally accessible for lazy loading
    window.EauCategoriesController = EauCategoriesController;

    // ============================================
    // Event Categories Controller (v1.61.0)
    // ============================================

    const EauEventCategoriesController = {
        // State
        currentPage: 1,
        perPage: 20,
        searchTerm: '',
        orderBy: 'category_name',
        order: 'ASC',
        editingCategoryId: null,

        /**
         * Initialize controller
         */
        init: function() {
            this.bindEvents();
            this.loadCategories();
            this.loadStats();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            const self = this;

            // Search
            $('#eau-event-categories-search').on('input', this.debounce(function() {
                self.searchTerm = $(this).val();
                self.currentPage = 1;
                self.loadCategories();
            }, 300));

            // Add button
            $('#eau-add-event-category').on('click', this.handleAddCategory.bind(this));

            // Refresh from Events button
            $('#eau-refresh-event-categories').on('click', this.handleRefreshFromEvents.bind(this));

            // Table actions
            $(document).on('click', '#event-categories-container .eau-action-view', this.handleViewCategory.bind(this));
            $(document).on('click', '#event-categories-container .eau-action-edit', this.handleEditCategory.bind(this));
            $(document).on('click', '#event-categories-container .eau-action-delete', this.handleDeleteCategory.bind(this));

            // Modal close (uses data-modal-action from Eau_Modal component)
            $(document).on('click', '#eau-event-modal-view-overlay .eau-modal-close, #eau-event-modal-view-overlay [data-modal-action="close"]', function(e) {
                e.preventDefault();
                self.closeModal('eau-event-modal-view');
            });
            $(document).on('click', '#eau-event-modal-edit-overlay .eau-modal-close, #eau-event-modal-edit-overlay [data-modal-action="close"]', function(e) {
                e.preventDefault();
                self.closeModal('eau-event-modal-edit');
            });
            $(document).on('click', '#eau-event-modal-add-overlay .eau-modal-close, #eau-event-modal-add-overlay [data-modal-action="close"]', function(e) {
                e.preventDefault();
                self.closeModal('eau-event-modal-add');
            });

            // Modal save
            $(document).on('click', '#eau-event-modal-edit-overlay [data-modal-action="save"]', this.handleSaveCategory.bind(this));
            $(document).on('click', '#eau-event-modal-add-overlay [data-modal-action="create"]', this.handleSaveCategory.bind(this));

            // Sortable columns
            $(document).on('click', '#event-categories-table .eau-table-th.eau-sortable', this.handleSort.bind(this));

            // Pagination
            $(document).on('click', '#eau-event-categories-pagination .eau-pagination-btn', function(e) {
                e.preventDefault();
                if ($(this).is(':disabled') || $(this).hasClass('eau-pagination-active')) {
                    return;
                }
                const page = parseInt($(this).data('page'));
                self.currentPage = page;
                self.loadCategories();
            });
        },

        /**
         * Load categories
         */
        loadCategories: function() {
            const self = this;

            this.showSkeleton();

            $.ajax({
                url: eauSettingsData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_get_event_categories',
                    nonce: eauSettingsData.eventCategoriesNonce,
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
                        self.updateTableCount(response.data.pagination.total);
                    } else {
                        EauNotifications.error('Error', response.data.message || 'Failed to load event categories');
                    }
                },
                error: function() {
                    EauNotifications.error('Error', 'Failed to load event categories');
                },
                complete: function() {
                    self.hideSkeleton();
                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                }
            });
        },

        /**
         * Load statistics
         */
        loadStats: function() {
            const self = this;

            $.ajax({
                url: eauSettingsData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_get_event_categories_stats',
                    nonce: eauSettingsData.eventCategoriesNonce
                },
                success: function(response) {
                    if (response.success) {
                        self.updateStats(response.data);
                    }
                }
            });
        },

        /**
         * Update stats display
         */
        updateStats: function(stats) {
            $('#event-categories-container .eau-stats-cards .eau-stats-card:first-child .eau-stats-number').text(stats.total);
        },

        /**
         * Render categories
         */
        renderCategories: function(categories, pagination) {
            const $tbody = $('#event-categories-table-tbody');

            if (!categories || categories.length === 0) {
                $tbody.html(`
                    <tr class="eau-table-empty">
                        <td colspan="4" style="text-align: center; padding: 3rem;">
                            <i data-lucide="inbox" style="width: 3rem; height: 3rem; color: #d1d5db; margin-bottom: 1rem;"></i>
                            <p style="color: #6b7280; margin: 0;">No event categories found</p>
                        </td>
                    </tr>
                `);
                return;
            }

            let html = '';
            categories.forEach(function(category) {
                html += `
                    <tr class="eau-table-row" data-id="${category.id}">
                        <td class="eau-table-td" data-label="Category ID">
                            <span style="font-family: monospace; font-size: 0.875rem; color: #6b7280;">${category.category_serial}</span>
                        </td>
                        <td class="eau-table-td" data-label="Category Name">
                            <strong style="color: #1f2937;">${category.category_name}</strong>
                        </td>
                        <td class="eau-table-td" data-label="Last Updated">
                            <span style="color: #6b7280; font-size: 0.875rem;">${category.updated_at}</span>
                        </td>
                        <td class="eau-table-td eau-table-td-actions">
                            <div class="eau-table-actions">
                                <button class="eau-action-btn eau-action-view" data-id="${category.id}" title="View">
                                    <i data-lucide="eye"></i>
                                </button>
                                <button class="eau-action-btn eau-action-edit" data-id="${category.id}" title="Edit">
                                    <i data-lucide="pencil"></i>
                                </button>
                                <button class="eau-action-btn eau-action-delete" data-id="${category.id}" title="Delete">
                                    <i data-lucide="trash-2"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            });

            $tbody.html(html);
        },

        /**
         * Render pagination
         */
        renderPagination: function(pagination) {
            const totalPages = pagination.total_pages || 1;
            const $container = $('#eau-event-categories-pagination');

            if (totalPages <= 1) {
                $container.html('');
                return;
            }

            const startItem = ((pagination.page - 1) * pagination.per_page) + 1;
            const endItem = Math.min(pagination.page * pagination.per_page, pagination.total);

            const html = this.buildPaginationHTML(pagination.page, totalPages, startItem, endItem, pagination.total);
            $container.html(html);

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        },

        /**
         * Build pagination HTML
         */
        buildPaginationHTML: function(currentPage, totalPages, startItem, endItem, total) {
            const pagesToShow = this.getPagesToShow(currentPage, totalPages);

            let html = '<div class="eau-pagination-wrapper">';

            html += '<div class="eau-pagination-info">';
            html += `Showing ${startItem.toLocaleString()} to ${endItem.toLocaleString()} of ${total.toLocaleString()} categories`;
            html += '</div>';

            html += '<div class="eau-pagination-nav">';

            const prevDisabled = currentPage <= 1 ? 'disabled' : '';
            html += `<button class="eau-pagination-btn eau-pagination-prev ${prevDisabled}" data-page="${Math.max(1, currentPage - 1)}" ${prevDisabled ? 'disabled' : ''}>`;
            html += '<i data-lucide="chevron-left"></i>';
            html += '</button>';

            pagesToShow.forEach(function(page) {
                if (page === '...') {
                    html += '<span class="eau-pagination-ellipsis">...</span>';
                } else {
                    const activeClass = page === currentPage ? 'eau-pagination-active' : '';
                    html += `<button class="eau-pagination-btn eau-pagination-number ${activeClass}" data-page="${page}">${page}</button>`;
                }
            });

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
            } else {
                pages.push(1);

                let start = Math.max(2, currentPage - 1);
                let end = Math.min(totalPages - 1, currentPage + 1);

                if (currentPage <= 3) {
                    end = maxShown - 1;
                }
                if (currentPage >= totalPages - 2) {
                    start = totalPages - maxShown + 2;
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

                pages.push(totalPages);
            }

            return pages;
        },

        /**
         * Handle sort
         */
        handleSort: function(e) {
            const $th = $(e.currentTarget);
            const key = $th.data('key');

            if (this.orderBy === key) {
                this.order = this.order === 'ASC' ? 'DESC' : 'ASC';
            } else {
                this.orderBy = key;
                this.order = 'ASC';
            }

            // Update sort indicators
            $('#event-categories-table .eau-table-th').removeClass('sorted-asc sorted-desc');
            $th.addClass(this.order === 'ASC' ? 'sorted-asc' : 'sorted-desc');

            this.loadCategories();
        },

        /**
         * Handle view category
         */
        handleViewCategory: function(e) {
            e.preventDefault();
            const id = $(e.currentTarget).data('id');
            const self = this;

            $.ajax({
                url: eauSettingsData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_get_event_category',
                    nonce: eauSettingsData.eventCategoriesNonce,
                    id: id
                },
                success: function(response) {
                    if (response.success) {
                        self.showViewModal(response.data);
                    } else {
                        EauNotifications.error('Error', response.data.message || 'Failed to load category');
                    }
                },
                error: function() {
                    EauNotifications.error('Error', 'Failed to load category');
                }
            });
        },

        /**
         * Show view modal
         */
        showViewModal: function(category) {
            const html = `
                <div class="eau-form-grid" style="gap: 1.5rem;">
                    <div class="eau-form-field">
                        <label class="eau-form-label">Category ID</label>
                        <div class="eau-form-static" style="padding: 0.75rem; background: #f9fafb; border-radius: 8px; font-family: monospace;">
                            ${category.category_serial}
                        </div>
                    </div>
                    <div class="eau-form-field">
                        <label class="eau-form-label">Category Name</label>
                        <div class="eau-form-static" style="padding: 0.75rem; background: #f9fafb; border-radius: 8px;">
                            ${category.category_name}
                        </div>
                    </div>
                    <div class="eau-form-field">
                        <label class="eau-form-label">Created</label>
                        <div class="eau-form-static" style="padding: 0.75rem; background: #f9fafb; border-radius: 8px;">
                            ${category.created_at}
                        </div>
                    </div>
                    <div class="eau-form-field">
                        <label class="eau-form-label">Last Updated</label>
                        <div class="eau-form-static" style="padding: 0.75rem; background: #f9fafb; border-radius: 8px;">
                            ${category.updated_at}
                        </div>
                    </div>
                </div>
            `;

            $('#eau-event-modal-view-body').html(html);
            this.openModal('eau-event-modal-view');
        },

        /**
         * Handle edit category
         */
        handleEditCategory: function(e) {
            e.preventDefault();
            const id = $(e.currentTarget).data('id');
            const self = this;

            this.editingCategoryId = id;

            $.ajax({
                url: eauSettingsData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_get_event_category',
                    nonce: eauSettingsData.eventCategoriesNonce,
                    id: id
                },
                success: function(response) {
                    if (response.success) {
                        self.showEditModal(response.data);
                    } else {
                        EauNotifications.error('Error', response.data.message || 'Failed to load category');
                    }
                },
                error: function() {
                    EauNotifications.error('Error', 'Failed to load category');
                }
            });
        },

        /**
         * Show edit modal
         */
        showEditModal: function(category) {
            const html = `
                <form id="event-category-edit-form" class="eau-form-grid" style="gap: 1.5rem;">
                    <input type="hidden" name="id" value="${category.id}">
                    <div class="eau-form-field">
                        <label class="eau-form-label" for="edit-event-category-serial">Category ID <span class="eau-form-required">*</span></label>
                        <input type="text" class="eau-form-input" id="edit-event-category-serial" name="category_serial" value="${category.category_serial}" required>
                    </div>
                    <div class="eau-form-field">
                        <label class="eau-form-label" for="edit-event-category-name">Category Name <span class="eau-form-required">*</span></label>
                        <input type="text" class="eau-form-input" id="edit-event-category-name" name="category_name" value="${category.category_name}" required>
                    </div>
                </form>
            `;

            $('#eau-event-modal-edit-body').html(html);
            this.openModal('eau-event-modal-edit');
        },

        /**
         * Handle add category
         */
        handleAddCategory: function(e) {
            e.preventDefault();
            const self = this;

            this.editingCategoryId = null;

            // Generate category serial
            $.ajax({
                url: eauSettingsData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_generate_event_category_serial',
                    nonce: eauSettingsData.eventCategoriesNonce
                },
                success: function(response) {
                    const serial = response.success ? response.data.category_serial : '';
                    self.showAddModal(serial);
                },
                error: function() {
                    self.showAddModal('');
                }
            });
        },

        /**
         * Show add modal
         */
        showAddModal: function(serial) {
            const html = `
                <form id="event-category-add-form" class="eau-form-grid" style="gap: 1.5rem;">
                    <div class="eau-form-field">
                        <label class="eau-form-label" for="add-event-category-serial">Category ID <span class="eau-form-required">*</span></label>
                        <input type="text" class="eau-form-input" id="add-event-category-serial" name="category_serial" value="${serial}" required>
                    </div>
                    <div class="eau-form-field">
                        <label class="eau-form-label" for="add-event-category-name">Category Name <span class="eau-form-required">*</span></label>
                        <input type="text" class="eau-form-input" id="add-event-category-name" name="category_name" value="" required>
                    </div>
                </form>
            `;

            $('#eau-event-modal-add-body').html(html);
            this.openModal('eau-event-modal-add');
        },

        /**
         * Handle delete category
         */
        handleDeleteCategory: function(e) {
            e.preventDefault();
            const id = $(e.currentTarget).data('id');
            const self = this;

            EauNotifications.confirm(
                'Delete Category',
                'Are you sure you want to delete this event category? This action cannot be undone.',
                function() {
                    $.ajax({
                        url: eauSettingsData.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'eau_delete_event_category',
                            nonce: eauSettingsData.eventCategoriesNonce,
                            id: id
                        },
                        success: function(response) {
                            if (response.success) {
                                EauNotifications.success('Success', 'Event category deleted successfully');
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
            );
        },

        /**
         * Handle refresh from events
         */
        handleRefreshFromEvents: function(e) {
            e.preventDefault();
            const self = this;

            const $btn = $('#eau-refresh-event-categories');
            const originalHtml = $btn.html();
            $btn.prop('disabled', true).html('<i data-lucide="loader-2" class="eau-spin"></i> Syncing...');

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }

            $.ajax({
                url: eauSettingsData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_sync_event_categories',
                    nonce: eauSettingsData.eventCategoriesNonce
                },
                success: function(response) {
                    if (response.success) {
                        EauNotifications.success(
                            'Sync Complete',
                            `Found ${response.data.total_found} categories. Added ${response.data.added}, skipped ${response.data.skipped}.`
                        );
                        self.loadCategories();
                        self.loadStats();
                    } else {
                        EauNotifications.error('Error', response.data.message || 'Failed to sync categories');
                    }
                },
                error: function() {
                    EauNotifications.error('Error', 'Failed to sync categories');
                },
                complete: function() {
                    $btn.prop('disabled', false).html(originalHtml);
                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                }
            });
        },

        /**
         * Handle save category
         */
        handleSaveCategory: function(e) {
            e.preventDefault();

            const $modal = $(e.currentTarget).closest('.eau-modal-overlay');
            const modalId = $modal.find('.eau-modal').attr('id');
            const isEdit = modalId === 'eau-event-modal-edit';
            const $form = isEdit ? $('#event-category-edit-form') : $('#event-category-add-form');

            const data = {
                action: 'eau_save_event_category',
                nonce: eauSettingsData.eventCategoriesNonce,
                category_serial: $form.find('[name="category_serial"]').val(),
                category_name: $form.find('[name="category_name"]').val()
            };

            if (isEdit) {
                data.id = $form.find('[name="id"]').val();
            }

            if (!data.category_serial || !data.category_name) {
                EauNotifications.error('Validation Error', 'Please fill in all required fields');
                return;
            }

            const self = this;

            $.ajax({
                url: eauSettingsData.ajaxUrl,
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
         * Show skeleton
         */
        showSkeleton: function() {
            $('#event-categories-table-loading').show();
        },

        /**
         * Hide skeleton
         */
        hideSkeleton: function() {
            $('#event-categories-table-loading').hide();
        },

        /**
         * Update table count display
         */
        updateTableCount: function(total) {
            $('#event-categories-table-count .eau-count-number').text(total.toLocaleString());
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

    // Make EauEventCategoriesController globally accessible for lazy loading
    window.EauEventCategoriesController = EauEventCategoriesController;

    // ============================================
    // Coupons Controller (v1.69.0)
    // ============================================

    const EauCouponsController = {

        // State
        currentPage: 1,
        perPage: 20,
        searchTerm: '',
        statusFilter: '',
        orderBy: 'created_at',
        order: 'DESC',
        editingCouponId: null,
        eventsCache: [],

        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.loadCoupons();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            const self = this;

            // Search
            $('#eau-coupons-search').on('input', this.debounce(function(e) {
                self.searchTerm = $(e.target).val();
                self.currentPage = 1;
                self.loadCoupons();
            }, 300));

            // Status filter
            $('#eau-coupons-status-filter').on('change', function() {
                self.statusFilter = $(this).val();
                self.currentPage = 1;
                self.loadCoupons();
            });

            // Add Coupon
            $('#eau-add-coupon').on('click', this.handleAddCoupon.bind(this));

            // Table actions (delegated)
            $(document).on('click', '#coupons-table .eau-action-view', this.handleViewCoupon.bind(this));
            $(document).on('click', '#coupons-table .eau-action-edit', this.handleEditCoupon.bind(this));
            $(document).on('click', '#coupons-table .eau-action-delete', this.handleDeleteCoupon.bind(this));

            // Modal close
            $(document).on('click', '#eau-coupon-modal-view-overlay .eau-modal-close, #eau-coupon-modal-view-overlay [data-modal-action="close"]', function() {
                self.closeModal('eau-coupon-modal-view');
            });
            $(document).on('click', '#eau-coupon-modal-edit-overlay .eau-modal-close, #eau-coupon-modal-edit-overlay [data-modal-action="close"]', function() {
                self.closeModal('eau-coupon-modal-edit');
            });
            $(document).on('click', '#eau-coupon-modal-add-overlay .eau-modal-close, #eau-coupon-modal-add-overlay [data-modal-action="close"]', function() {
                self.closeModal('eau-coupon-modal-add');
            });
            $(document).on('click', '#eau-coupon-modal-delete-overlay .eau-modal-close, #eau-coupon-modal-delete-overlay [data-modal-action="close"]', function() {
                self.closeModal('eau-coupon-modal-delete');
            });

            // Modal save
            $(document).on('click', '#eau-coupon-modal-edit-overlay [data-modal-action="save"]', this.handleSaveCoupon.bind(this));
            $(document).on('click', '#eau-coupon-modal-add-overlay [data-modal-action="create"]', this.handleCreateCoupon.bind(this));
            $(document).on('click', '#eau-coupon-modal-delete-overlay [data-modal-action="delete"]', this.handleConfirmDelete.bind(this));

            // Event scope toggle
            $(document).on('change', 'input[name="event_scope"]', function() {
                const scope = $(this).val();
                if (scope === 'specific') {
                    $('#coupon-events-selector').show();
                    self.loadEvents();
                } else {
                    $('#coupon-events-selector').hide();
                }
            });

            // Sortable columns
            $(document).on('click', '#coupons-table .eau-table-th.eau-sortable', this.handleSort.bind(this));

            // Pagination
            $(document).on('click', '#eau-coupons-pagination .eau-pagination-btn', function(e) {
                e.preventDefault();
                if ($(this).is(':disabled') || $(this).hasClass('eau-pagination-active')) {
                    return;
                }
                const page = parseInt($(this).data('page'));
                self.currentPage = page;
                self.loadCoupons();
            });

            // Copy coupon code on click
            $(document).on('click', '.eau-coupon-code-copy', function(e) {
                e.stopPropagation();
                const code = $(this).data('code');
                const $el = $(this);

                // Function to show success feedback
                const showCopiedFeedback = function() {
                    const originalText = $el.text();
                    $el.text('Copied!');
                    $el.addClass('eau-coupon-copied');

                    setTimeout(function() {
                        $el.text(originalText);
                        $el.removeClass('eau-coupon-copied');
                    }, 1500);
                };

                // Try modern clipboard API first (only available on HTTPS)
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(code).then(function() {
                        showCopiedFeedback();
                    }).catch(function() {
                        // Fallback if clipboard API fails
                        self.copyToClipboardFallback(code);
                        showCopiedFeedback();
                    });
                } else {
                    // Fallback for HTTP or older browsers
                    self.copyToClipboardFallback(code);
                    showCopiedFeedback();
                }
            });
        },

        /**
         * Handle sort
         */
        handleSort: function(e) {
            const $th = $(e.currentTarget);
            const column = $th.data('key');

            if (!$th.hasClass('eau-sortable')) return;

            if (this.orderBy === column) {
                this.order = this.order === 'ASC' ? 'DESC' : 'ASC';
            } else {
                this.orderBy = column;
                this.order = 'ASC';
            }

            this.loadCoupons();
        },

        /**
         * Load coupons
         */
        loadCoupons: function() {
            const self = this;

            this.showSkeleton();

            $.ajax({
                url: eauSettingsData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_get_coupons',
                    nonce: eauSettingsData.couponsNonce,
                    page: this.currentPage,
                    per_page: this.perPage,
                    search: this.searchTerm,
                    status: this.statusFilter,
                    orderby: this.orderBy,
                    order: this.order
                },
                success: function(response) {
                    if (response.success) {
                        self.renderCoupons(response.data.coupons);
                        self.renderPagination(response.data);
                        self.loadStats();
                    } else {
                        EauNotifications.error('Error', response.data.message || 'Failed to load coupons');
                    }
                },
                error: function() {
                    EauNotifications.error('Error', 'Failed to load coupons');
                },
                complete: function() {
                    self.hideSkeleton();
                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                }
            });
        },

        /**
         * Load stats
         */
        loadStats: function() {
            const self = this;

            $.ajax({
                url: eauSettingsData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_get_coupons_stats',
                    nonce: eauSettingsData.couponsNonce
                },
                success: function(response) {
                    if (response.success) {
                        self.updateStatsCards(response.data.stats);
                    }
                }
            });
        },

        /**
         * Update stats cards
         */
        updateStatsCards: function(stats) {
            const $container = $('#coupons-container');
            $container.find('.eau-stat-card').eq(0).find('.eau-stat-number').text(stats.total);
            $container.find('.eau-stat-card').eq(1).find('.eau-stat-number').text(stats.active);
            $container.find('.eau-stat-card').eq(2).find('.eau-stat-number').text(stats.inactive);
            $container.find('.eau-stat-card').eq(3).find('.eau-stat-number').text('$' + parseFloat(stats.total_discount).toFixed(2));
        },

        /**
         * Render coupons
         */
        renderCoupons: function(coupons) {
            const tbody = $('#coupons-table tbody');
            tbody.empty();

            if (coupons.length === 0) {
                tbody.html(`
                    <tr class="eau-table-empty">
                        <td colspan="7" class="eau-table-td" style="text-align: center;">
                            <div class="eau-empty-state">
                                <i data-lucide="ticket"></i>
                                <p>No coupons found</p>
                            </div>
                        </td>
                    </tr>
                `);
                return;
            }

            coupons.forEach(coupon => {
                const statusClass = this.getStatusClass(coupon.status);
                const statusLabel = coupon.status.charAt(0).toUpperCase() + coupon.status.slice(1);

                const row = `
                    <tr class="eau-table-tr" data-id="${coupon.id}">
                        <td class="eau-table-td">
                            <code class="eau-coupon-code-copy" data-code="${coupon.code}" title="Click to copy">${coupon.code}</code>
                        </td>
                        <td class="eau-table-td">
                            ${coupon.formatted_discount}
                        </td>
                        <td class="eau-table-td">
                            ${coupon.usage_count}${coupon.usage_limit ? '/' + coupon.usage_limit : ''}
                        </td>
                        <td class="eau-table-td">
                            <span class="eau-validity-text">${coupon.validity_description}</span>
                        </td>
                        <td class="eau-table-td">
                            <span class="eau-status-badge ${statusClass}">${statusLabel}</span>
                        </td>
                        <td class="eau-table-td">
                            ${coupon.created_at_formatted}
                        </td>
                        <td class="eau-table-td eau-table-actions">
                            <button class="eau-action-btn eau-action-view" data-id="${coupon.id}" title="View">
                                <i data-lucide="eye"></i>
                            </button>
                            <button class="eau-action-btn eau-action-edit" data-id="${coupon.id}" title="Edit">
                                <i data-lucide="pencil"></i>
                            </button>
                            <button class="eau-action-btn eau-action-delete" data-id="${coupon.id}" title="Delete">
                                <i data-lucide="trash-2"></i>
                            </button>
                        </td>
                    </tr>
                `;
                tbody.append(row);
            });
        },

        /**
         * Get status class
         */
        getStatusClass: function(status) {
            switch (status) {
                case 'active': return 'eau-status-active';
                case 'inactive': return 'eau-status-inactive';
                case 'expired': return 'eau-status-expired';
                default: return '';
            }
        },

        /**
         * Render pagination
         */
        renderPagination: function(data) {
            const $pagination = $('#eau-coupons-pagination');
            $pagination.empty();

            if (data.total_pages <= 1) return;

            let html = '<div class="eau-pagination">';

            // Previous button
            html += `<button class="eau-pagination-btn" data-page="${data.page - 1}" ${data.page <= 1 ? 'disabled' : ''}>
                <i data-lucide="chevron-left"></i>
            </button>`;

            // Page numbers
            const maxPages = 5;
            let startPage = Math.max(1, data.page - Math.floor(maxPages / 2));
            let endPage = Math.min(data.total_pages, startPage + maxPages - 1);

            if (endPage - startPage < maxPages - 1) {
                startPage = Math.max(1, endPage - maxPages + 1);
            }

            if (startPage > 1) {
                html += `<button class="eau-pagination-btn" data-page="1">1</button>`;
                if (startPage > 2) {
                    html += '<span class="eau-pagination-dots">...</span>';
                }
            }

            for (let i = startPage; i <= endPage; i++) {
                html += `<button class="eau-pagination-btn ${i === data.page ? 'eau-pagination-active' : ''}" data-page="${i}">${i}</button>`;
            }

            if (endPage < data.total_pages) {
                if (endPage < data.total_pages - 1) {
                    html += '<span class="eau-pagination-dots">...</span>';
                }
                html += `<button class="eau-pagination-btn" data-page="${data.total_pages}">${data.total_pages}</button>`;
            }

            // Next button
            html += `<button class="eau-pagination-btn" data-page="${data.page + 1}" ${data.page >= data.total_pages ? 'disabled' : ''}>
                <i data-lucide="chevron-right"></i>
            </button>`;

            html += '</div>';

            $pagination.html(html);
        },

        /**
         * Handle view coupon
         */
        handleViewCoupon: function(e) {
            e.preventDefault();
            const id = $(e.currentTarget).data('id');
            const self = this;

            $.ajax({
                url: eauSettingsData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_get_coupon',
                    nonce: eauSettingsData.couponsNonce,
                    id: id
                },
                success: function(response) {
                    if (response.success) {
                        self.showViewModal(response.data.coupon);
                    } else {
                        EauNotifications.error('Error', response.data.message || 'Failed to load coupon');
                    }
                },
                error: function() {
                    EauNotifications.error('Error', 'Failed to load coupon');
                }
            });
        },

        /**
         * Show view modal
         */
        showViewModal: function(coupon) {
            const statusClass = this.getStatusClass(coupon.status);
            const statusLabel = coupon.status.charAt(0).toUpperCase() + coupon.status.slice(1);

            let eventsHtml = '';
            if (coupon.event_scope === 'specific' && coupon.event_names && coupon.event_names.length > 0) {
                eventsHtml = coupon.event_names.map(e => `<span class="eau-tag">${e.title}</span>`).join(' ');
            } else if (coupon.event_scope === 'all') {
                eventsHtml = '<span class="eau-tag">All Events</span>';
            } else {
                eventsHtml = '<span class="eau-text-muted">No events selected</span>';
            }

            const html = `
                <div class="eau-coupon-view">
                    <div class="eau-form-grid" style="gap: 1.5rem;">
                        <div class="eau-form-field">
                            <label class="eau-form-label">Coupon Code</label>
                            <div class="eau-form-static" style="padding: 0.75rem; background: #f9fafb; border-radius: 8px;">
                                <code style="font-size: 1.2em; font-weight: 600;">${coupon.code}</code>
                            </div>
                        </div>
                        <div class="eau-form-field">
                            <label class="eau-form-label">Status</label>
                            <div class="eau-form-static" style="padding: 0.75rem; background: #f9fafb; border-radius: 8px;">
                                <span class="eau-status-badge ${statusClass}">${statusLabel}</span>
                            </div>
                        </div>
                        ${coupon.description ? `
                        <div class="eau-form-field" style="grid-column: 1 / -1;">
                            <label class="eau-form-label">Description</label>
                            <div class="eau-form-static" style="padding: 0.75rem; background: #f9fafb; border-radius: 8px;">
                                ${coupon.description}
                            </div>
                        </div>
                        ` : ''}
                        <div class="eau-form-field">
                            <label class="eau-form-label">Discount</label>
                            <div class="eau-form-static" style="padding: 0.75rem; background: #f9fafb; border-radius: 8px;">
                                ${coupon.formatted_discount}
                            </div>
                        </div>
                        <div class="eau-form-field">
                            <label class="eau-form-label">Usage</label>
                            <div class="eau-form-static" style="padding: 0.75rem; background: #f9fafb; border-radius: 8px;">
                                ${coupon.usage_description}
                            </div>
                        </div>
                        <div class="eau-form-field" style="grid-column: 1 / -1;">
                            <label class="eau-form-label">Validity</label>
                            <div class="eau-form-static" style="padding: 0.75rem; background: #f9fafb; border-radius: 8px;">
                                ${coupon.validity_description}
                            </div>
                        </div>
                        ${coupon.min_order_value ? `
                        <div class="eau-form-field">
                            <label class="eau-form-label">Min Order Value</label>
                            <div class="eau-form-static" style="padding: 0.75rem; background: #f9fafb; border-radius: 8px;">
                                $${parseFloat(coupon.min_order_value).toFixed(2)}
                            </div>
                        </div>
                        ` : ''}
                        ${coupon.max_discount ? `
                        <div class="eau-form-field">
                            <label class="eau-form-label">Max Discount</label>
                            <div class="eau-form-static" style="padding: 0.75rem; background: #f9fafb; border-radius: 8px;">
                                $${parseFloat(coupon.max_discount).toFixed(2)}
                            </div>
                        </div>
                        ` : ''}
                        <div class="eau-form-field" style="grid-column: 1 / -1;">
                            <label class="eau-form-label">Applies To</label>
                            <div class="eau-form-static" style="padding: 0.75rem; background: #f9fafb; border-radius: 8px;">
                                ${eventsHtml}
                            </div>
                        </div>
                    </div>
                </div>
            `;

            $('#eau-coupon-modal-view-body').html(html);
            this.openModal('eau-coupon-modal-view');
        },

        /**
         * Handle edit coupon
         */
        handleEditCoupon: function(e) {
            e.preventDefault();
            const id = $(e.currentTarget).data('id');
            const self = this;

            this.editingCouponId = id;

            $.ajax({
                url: eauSettingsData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_get_coupon',
                    nonce: eauSettingsData.couponsNonce,
                    id: id
                },
                success: function(response) {
                    if (response.success) {
                        self.showEditModal(response.data.coupon);
                    } else {
                        EauNotifications.error('Error', response.data.message || 'Failed to load coupon');
                    }
                },
                error: function() {
                    EauNotifications.error('Error', 'Failed to load coupon');
                }
            });
        },

        /**
         * Show edit modal
         */
        showEditModal: function(coupon) {
            const html = this.getCouponFormHtml(coupon);
            $('#eau-coupon-modal-edit-body').html(html);
            this.openModal('eau-coupon-modal-edit');

            // If specific events, load events and select
            if (coupon.event_scope === 'specific') {
                $('#coupon-events-selector').show();
                this.loadEvents(coupon.events);
            }
        },

        /**
         * Handle add coupon
         */
        handleAddCoupon: function(e) {
            e.preventDefault();
            this.editingCouponId = null;
            const html = this.getCouponFormHtml();
            $('#eau-coupon-modal-add-body').html(html);
            this.openModal('eau-coupon-modal-add');
        },

        /**
         * Get coupon form HTML
         */
        getCouponFormHtml: function(coupon = null) {
            const isEdit = coupon !== null;

            return `
                <form id="coupon-form" class="eau-form-grid" style="gap: 1.5rem;">
                    ${isEdit ? `<input type="hidden" name="id" value="${coupon.id}">` : ''}

                    <div class="eau-form-field">
                        <label class="eau-form-label" for="coupon-code">Coupon Code <span class="eau-form-required">*</span></label>
                        <input type="text" class="eau-form-input" id="coupon-code" name="code"
                               value="${isEdit ? coupon.code : ''}"
                               style="text-transform: uppercase;"
                               placeholder="e.g., SUMMER20" required>
                    </div>

                    <div class="eau-form-field">
                        <label class="eau-form-label" for="coupon-status">Status</label>
                        <select class="eau-form-select" id="coupon-status" name="status">
                            <option value="active" ${isEdit && coupon.status === 'active' ? 'selected' : ''}>Active</option>
                            <option value="inactive" ${isEdit && coupon.status === 'inactive' ? 'selected' : ''}>Inactive</option>
                        </select>
                    </div>

                    <div class="eau-form-field" style="grid-column: 1 / -1;">
                        <label class="eau-form-label" for="coupon-description">Description</label>
                        <input type="text" class="eau-form-input" id="coupon-description" name="description"
                               value="${isEdit && coupon.description ? coupon.description : ''}"
                               placeholder="Optional description for internal reference">
                    </div>

                    <div class="eau-form-field">
                        <label class="eau-form-label" for="coupon-discount-type">Discount Type <span class="eau-form-required">*</span></label>
                        <select class="eau-form-select" id="coupon-discount-type" name="discount_type">
                            <option value="percentage" ${!isEdit || coupon.discount_type === 'percentage' ? 'selected' : ''}>Percentage (%)</option>
                            <option value="fixed" ${isEdit && coupon.discount_type === 'fixed' ? 'selected' : ''}>Fixed Amount ($)</option>
                        </select>
                    </div>

                    <div class="eau-form-field">
                        <label class="eau-form-label" for="coupon-discount-value">Discount Value <span class="eau-form-required">*</span></label>
                        <input type="number" class="eau-form-input" id="coupon-discount-value" name="discount_value"
                               value="${isEdit ? coupon.discount_value : ''}"
                               step="0.01" min="0" placeholder="e.g., 10" required>
                    </div>

                    <div class="eau-form-field">
                        <label class="eau-form-label" for="coupon-min-order">Min Order Value ($)</label>
                        <input type="number" class="eau-form-input" id="coupon-min-order" name="min_order_value"
                               value="${isEdit && coupon.min_order_value ? coupon.min_order_value : ''}"
                               step="0.01" min="0" placeholder="Optional">
                    </div>

                    <div class="eau-form-field">
                        <label class="eau-form-label" for="coupon-max-discount">Max Discount ($)</label>
                        <input type="number" class="eau-form-input" id="coupon-max-discount" name="max_discount"
                               value="${isEdit && coupon.max_discount ? coupon.max_discount : ''}"
                               step="0.01" min="0" placeholder="Optional (for %)">
                    </div>

                    <div class="eau-form-field">
                        <label class="eau-form-label" for="coupon-valid-from">Valid From</label>
                        <input type="datetime-local" class="eau-form-input" id="coupon-valid-from" name="valid_from"
                               value="${isEdit && coupon.valid_from ? coupon.valid_from.replace(' ', 'T').slice(0, 16) : ''}">
                    </div>

                    <div class="eau-form-field">
                        <label class="eau-form-label" for="coupon-valid-until">Valid Until</label>
                        <input type="datetime-local" class="eau-form-input" id="coupon-valid-until" name="valid_until"
                               value="${isEdit && coupon.valid_until ? coupon.valid_until.replace(' ', 'T').slice(0, 16) : ''}">
                    </div>

                    <div class="eau-form-field">
                        <label class="eau-form-label" for="coupon-usage-limit">Usage Limit (Total)</label>
                        <input type="number" class="eau-form-input" id="coupon-usage-limit" name="usage_limit"
                               value="${isEdit && coupon.usage_limit ? coupon.usage_limit : ''}"
                               min="0" placeholder="Unlimited">
                    </div>

                    <div class="eau-form-field">
                        <label class="eau-form-label" for="coupon-usage-limit-per-user">Usage Limit (Per User)</label>
                        <input type="number" class="eau-form-input" id="coupon-usage-limit-per-user" name="usage_limit_per_user"
                               value="${isEdit ? coupon.usage_limit_per_user : '1'}"
                               min="1" placeholder="1">
                    </div>

                    <div class="eau-form-field" style="grid-column: 1 / -1;">
                        <label class="eau-form-label">Apply to Events</label>
                        <div class="eau-radio-inline" style="display: flex; gap: 20px; margin-top: 8px;">
                            <label class="eau-radio-label">
                                <input type="radio" name="event_scope" value="all" ${!isEdit || coupon.event_scope === 'all' ? 'checked' : ''}>
                                <span>All Events</span>
                            </label>
                            <label class="eau-radio-label">
                                <input type="radio" name="event_scope" value="specific" ${isEdit && coupon.event_scope === 'specific' ? 'checked' : ''}>
                                <span>Specific Events</span>
                            </label>
                        </div>
                    </div>

                    <div class="eau-form-field" style="grid-column: 1 / -1; ${!isEdit || coupon.event_scope !== 'specific' ? 'display: none;' : ''}" id="coupon-events-selector">
                        <label class="eau-form-label">Select Events</label>
                        <div id="coupon-events-list" style="max-height: 200px; overflow-y: auto; border: 1px solid #e5e7eb; border-radius: 8px; padding: 10px;">
                            <div class="eau-skeleton" style="height: 30px; margin-bottom: 8px;"></div>
                            <div class="eau-skeleton" style="height: 30px; margin-bottom: 8px;"></div>
                            <div class="eau-skeleton" style="height: 30px;"></div>
                        </div>
                    </div>
                </form>
            `;
        },

        /**
         * Load events for selection
         */
        loadEvents: function(selectedIds = []) {
            const self = this;

            $.ajax({
                url: eauSettingsData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_get_events_for_coupon',
                    nonce: eauSettingsData.couponsNonce
                },
                success: function(response) {
                    if (response.success) {
                        self.eventsCache = response.data.events;
                        self.renderEventsSelector(response.data.events, selectedIds);
                    }
                }
            });
        },

        /**
         * Render events selector
         */
        renderEventsSelector: function(events, selectedIds = []) {
            const $container = $('#coupon-events-list');

            if (events.length === 0) {
                $container.html('<p class="eau-text-muted">No events available</p>');
                return;
            }

            let html = '';
            events.forEach(event => {
                const isSelected = selectedIds.includes(String(event.id)) || selectedIds.includes(event.id);
                html += `
                    <label class="eau-checkbox-label" style="display: flex; align-items: center; padding: 8px; border-bottom: 1px solid #f3f4f6;">
                        <input type="checkbox" name="event_ids[]" value="${event.id}" ${isSelected ? 'checked' : ''}>
                        <span style="margin-left: 10px;">
                            <strong>${event.title}</strong>
                            ${event.date ? `<span style="color: #6b7280; font-size: 12px;"> - ${event.date}</span>` : ''}
                            <span style="color: #10b981; font-size: 12px; margin-left: 5px;">${event.price}</span>
                        </span>
                    </label>
                `;
            });

            $container.html(html);
        },

        /**
         * Handle create coupon
         */
        handleCreateCoupon: function(e) {
            e.preventDefault();
            this.saveCoupon('create');
        },

        /**
         * Handle save coupon
         */
        handleSaveCoupon: function(e) {
            e.preventDefault();
            this.saveCoupon('update');
        },

        /**
         * Save coupon
         */
        saveCoupon: function(mode) {
            const self = this;
            const $form = $('#coupon-form');

            // Validation
            const code = $form.find('[name="code"]').val();
            const discountValue = $form.find('[name="discount_value"]').val();

            if (!code || !discountValue) {
                EauNotifications.error('Validation Error', 'Please fill in all required fields');
                return;
            }

            // Collect form data
            const data = {
                action: mode === 'create' ? 'eau_create_coupon' : 'eau_update_coupon',
                nonce: eauSettingsData.couponsNonce,
                code: code,
                description: $form.find('[name="description"]').val(),
                discount_type: $form.find('[name="discount_type"]').val(),
                discount_value: discountValue,
                min_order_value: $form.find('[name="min_order_value"]').val(),
                max_discount: $form.find('[name="max_discount"]').val(),
                valid_from: $form.find('[name="valid_from"]').val(),
                valid_until: $form.find('[name="valid_until"]').val(),
                usage_limit: $form.find('[name="usage_limit"]').val(),
                usage_limit_per_user: $form.find('[name="usage_limit_per_user"]').val() || 1,
                status: $form.find('[name="status"]').val(),
                event_scope: $form.find('[name="event_scope"]:checked').val(),
                event_ids: []
            };

            // Collect selected events
            $form.find('[name="event_ids[]"]:checked').each(function() {
                data.event_ids.push($(this).val());
            });

            if (mode === 'update') {
                data.id = $form.find('[name="id"]').val();
            }

            $.ajax({
                url: eauSettingsData.ajaxUrl,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        EauNotifications.success('Success', response.data.message);
                        self.closeModal(mode === 'create' ? 'eau-coupon-modal-add' : 'eau-coupon-modal-edit');
                        self.loadCoupons();
                    } else {
                        EauNotifications.error('Error', response.data.message || 'Failed to save coupon');
                    }
                },
                error: function() {
                    EauNotifications.error('Error', 'Failed to save coupon');
                }
            });
        },

        /**
         * Handle delete coupon
         */
        handleDeleteCoupon: function(e) {
            e.preventDefault();
            const id = $(e.currentTarget).data('id');
            this.editingCouponId = id;

            $('#eau-coupon-modal-delete-body').html(`
                <p style="color: #6b7280;">Are you sure you want to delete this coupon? This action cannot be undone.</p>
            `);

            this.openModal('eau-coupon-modal-delete');
        },

        /**
         * Handle confirm delete
         */
        handleConfirmDelete: function(e) {
            e.preventDefault();
            const self = this;

            $.ajax({
                url: eauSettingsData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_delete_coupon',
                    nonce: eauSettingsData.couponsNonce,
                    id: this.editingCouponId
                },
                success: function(response) {
                    if (response.success) {
                        EauNotifications.success('Success', 'Coupon deleted successfully');
                        self.closeModal('eau-coupon-modal-delete');
                        self.loadCoupons();
                    } else {
                        EauNotifications.error('Error', response.data.message || 'Failed to delete coupon');
                    }
                },
                error: function() {
                    EauNotifications.error('Error', 'Failed to delete coupon');
                }
            });
        },

        /**
         * Open modal
         */
        openModal: function(modalId) {
            const $overlay = $('#' + modalId + '-overlay');
            $overlay.css('display', 'flex').hide().fadeIn(200);

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
         * Show skeleton
         */
        showSkeleton: function() {
            $('#coupons-table-loading').show();
        },

        /**
         * Hide skeleton
         */
        hideSkeleton: function() {
            $('#coupons-table-loading').hide();
        },

        /**
         * Copy to clipboard fallback (for HTTP or older browsers)
         */
        copyToClipboardFallback: function(text) {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.left = '-9999px';
            textarea.style.top = '0';
            document.body.appendChild(textarea);
            textarea.focus();
            textarea.select();
            try {
                document.execCommand('copy');
            } catch (err) {
                console.warn('Fallback copy failed:', err);
            }
            document.body.removeChild(textarea);
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

    // Make EauCouponsController globally accessible for lazy loading
    window.EauCouponsController = EauCouponsController;

    // ==========================================================================
    // PAYMENT GATEWAY CONTROLLER (v1.70.0)
    // ==========================================================================

    /**
     * Payment Gateway Settings Controller
     *
     * @since 1.70.0
     */
    const EauPaymentGatewayController = {

        // State
        settings: {},

        /**
         * Initialize controller
         */
        init: function() {
            this.bindEvents();
            this.loadSettings();

            // Initialize Lucide icons
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            const self = this;

            // Mode toggle
            $('#eau-gateway-mode input[type="radio"]').on('change', function() {
                const mode = $(this).val();
                self.updateModeUI(mode);

                // Update radio visual state
                $('#eau-gateway-mode .eau-radio-option').removeClass('selected');
                $(this).closest('.eau-radio-option').addClass('selected');
            });

            // Test connection
            $('#eau-test-gateway-connection').on('click', function() {
                self.testConnection();
            });

            // Save settings
            $('#eau-save-gateway-settings').on('click', function() {
                self.saveSettings();
            });

            // Copy webhook URL
            $('#copy-webhook-url').on('click', function() {
                const url = $('#webhook-url').val();
                self.copyToClipboard(url);
            });

            // Generate webhook secret
            $('#generate-webhook-secret').on('click', function() {
                self.generateWebhookSecret();
            });
        },

        /**
         * Load current settings
         */
        loadSettings: function() {
            const self = this;

            // Settings are already loaded in HTML, just initialize UI
            const isSandbox = $('#eau-gateway-mode input[value="sandbox"]').is(':checked');
            this.updateModeUI(isSandbox ? 'sandbox' : 'production');
        },

        /**
         * Update UI based on mode
         */
        updateModeUI: function(mode) {
            if (mode === 'sandbox') {
                $('#sandbox-credentials-section').show();
                $('#production-credentials-section').hide();
            } else {
                $('#sandbox-credentials-section').show();
                $('#production-credentials-section').show();
            }
        },

        /**
         * Test connection to Fat Zebra
         */
        testConnection: function() {
            const self = this;
            const $btn = $('#eau-test-gateway-connection');
            const $status = $('#gateway-connection-status');

            // Disable button
            $btn.prop('disabled', true);
            $btn.find('i').attr('data-lucide', 'loader-2').addClass('eau-spin');
            lucide.createIcons();

            $status.text('Testing...');

            $.ajax({
                url: eauSettingsData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_fatzebra_test_connection',
                    nonce: eauSettingsData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $status.text('Connected').addClass('eau-text-success').removeClass('eau-text-danger');
                        EauNotifications.success('Connection successful! Mode: ' + response.data.mode);
                    } else {
                        $status.text('Failed').addClass('eau-text-danger').removeClass('eau-text-success');
                        EauNotifications.error('Connection failed: ' + response.data.message);
                    }
                },
                error: function() {
                    $status.text('Error').addClass('eau-text-danger');
                    EauNotifications.error('Failed to test connection');
                },
                complete: function() {
                    $btn.prop('disabled', false);
                    $btn.find('i').attr('data-lucide', 'plug').removeClass('eau-spin');
                    lucide.createIcons();
                }
            });
        },

        /**
         * Save gateway settings
         */
        saveSettings: function() {
            const self = this;
            const $btn = $('#eau-save-gateway-settings');

            // Gather settings
            const settings = {
                sandbox_mode: $('input[name="gateway_mode"]:checked').val() === 'sandbox',
                sandbox_username: $('#sandbox-username').val(),
                sandbox_token: $('#sandbox-token').val(),
                production_username: $('#production-username').val(),
                production_token: $('#production-token').val(),
                webhook_secret: $('#webhook-secret').val()
            };

            // Disable button
            $btn.prop('disabled', true);
            $btn.find('i').attr('data-lucide', 'loader-2').addClass('eau-spin');
            lucide.createIcons();

            $.ajax({
                url: eauSettingsData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_fatzebra_save_settings',
                    nonce: eauSettingsData.nonce,
                    ...settings
                },
                success: function(response) {
                    if (response.success) {
                        EauNotifications.success('Payment gateway settings saved successfully');

                        // Update masked values
                        if (settings.sandbox_token && settings.sandbox_token !== '********') {
                            $('#sandbox-token').val('********');
                        }
                        if (settings.production_token && settings.production_token !== '********') {
                            $('#production-token').val('********');
                        }
                        if (settings.webhook_secret && settings.webhook_secret !== '********') {
                            $('#webhook-secret').val('********');
                        }
                    } else {
                        EauNotifications.error('Failed to save settings: ' + response.data.message);
                    }
                },
                error: function() {
                    EauNotifications.error('Failed to save settings');
                },
                complete: function() {
                    $btn.prop('disabled', false);
                    $btn.find('i').attr('data-lucide', 'save').removeClass('eau-spin');
                    lucide.createIcons();
                }
            });
        },

        /**
         * Copy text to clipboard
         */
        copyToClipboard: function(text) {
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(function() {
                    EauNotifications.success('Copied to clipboard!');
                });
            } else {
                // Fallback
                const textarea = document.createElement('textarea');
                textarea.value = text;
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
                EauNotifications.success('Copied to clipboard!');
            }
        },

        /**
         * Generate random webhook secret
         */
        generateWebhookSecret: function() {
            const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
            let secret = '';
            for (let i = 0; i < 32; i++) {
                secret += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            $('#webhook-secret').val(secret);
            EauNotifications.info('New webhook secret generated. Remember to save!');
        }
    };

    // Make EauPaymentGatewayController globally accessible for lazy loading
    window.EauPaymentGatewayController = EauPaymentGatewayController;

})(jQuery);
