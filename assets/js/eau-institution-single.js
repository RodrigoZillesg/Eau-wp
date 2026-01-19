/**
 * Eau Institution Single Page JavaScript
 * Inline editing implementation
 *
 * @since 1.63.0
 * @updated 1.63.5
 */
(function($) {
    'use strict';

    const EauInstitutionSingle = {
        // State
        currentPage: 1,
        perPage: 20,
        searchTerm: '',
        statusFilter: '',
        institutionId: 0,
        searchTimeout: null,
        currentEditField: null,
        expandedMemberId: null,

        /**
         * Initialize
         */
        init: function() {
            const $container = $('.eau-institution-single-container');
            if ($container.length === 0) return;

            this.institutionId = $container.data('institution-id');
            if (!this.institutionId) return;

            this.bindEvents();
            this.loadMembers();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            const self = this;

            // Search with debounce
            $('#eau-members-search').on('input', function() {
                clearTimeout(self.searchTimeout);
                const searchTerm = $(this).val();

                self.searchTimeout = setTimeout(function() {
                    self.searchTerm = searchTerm;
                    self.currentPage = 1;
                    self.loadMembers();
                }, 300);
            });

            // Status filter
            $('#eau-members-status-filter').on('change', function() {
                self.statusFilter = $(this).val();
                self.currentPage = 1;
                self.loadMembers();
            });

            // Pagination clicks
            $(document).on('click', '.eau-pagination-btn:not(:disabled)', function() {
                const page = $(this).data('page');
                if (page && page !== self.currentPage) {
                    self.currentPage = parseInt(page);
                    self.loadMembers();
                }
            });

            // Inline editing events
            if (eauInstitutionSingleData.canEdit) {
                self.bindInlineEditEvents();
                self.bindLogoUploadEvents();
            }

            // Member management events
            if (eauInstitutionSingleData.canEditMembers) {
                self.bindMemberManagementEvents();
            }

            // Primary contact events (only for restricted editors)
            if (eauInstitutionSingleData.canEditRestricted) {
                self.bindPrimaryContactEvents();
            }
        },

        /**
         * Bind primary contact events
         */
        bindPrimaryContactEvents: function() {
            const self = this;
            let searchTimeout = null;

            // Open selector
            $('#eau-change-primary-contact').on('click', function() {
                $('#eau-primary-contact-selector').slideDown(200);
                $('#eau-primary-contact-search').focus();
                self.loadMembersForPrimaryContact();
            });

            // Close selector
            $('#eau-cancel-primary-contact').on('click', function() {
                $('#eau-primary-contact-selector').slideUp(200);
                $('#eau-primary-contact-search').val('');
            });

            // Search members
            $('#eau-primary-contact-search').on('input', function() {
                clearTimeout(searchTimeout);
                const searchTerm = $(this).val();

                searchTimeout = setTimeout(function() {
                    self.loadMembersForPrimaryContact(searchTerm);
                }, 300);
            });

            // Select member as primary contact
            $(document).on('click', '.eau-contact-option', function() {
                const memberId = $(this).data('member-id');
                self.setPrimaryContact(memberId);
            });
        },

        /**
         * Load members for primary contact selector
         */
        loadMembersForPrimaryContact: function(search) {
            const self = this;
            const $list = $('#eau-primary-contact-list');

            $list.html('<div class="eau-selector-loading">Loading...</div>');

            $.ajax({
                url: eauInstitutionSingleData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_get_institution_members_for_select',
                    nonce: eauInstitutionSingleData.nonce,
                    institution_id: self.institutionId,
                    search: search || ''
                },
                success: function(response) {
                    if (response.success && response.data.members) {
                        self.renderMembersForSelect(response.data.members);
                    } else {
                        $list.html('<div class="eau-selector-empty">No members found</div>');
                    }
                },
                error: function() {
                    $list.html('<div class="eau-selector-empty">Failed to load members</div>');
                }
            });
        },

        /**
         * Render members in selector
         */
        renderMembersForSelect: function(members) {
            const $list = $('#eau-primary-contact-list');

            if (!members || members.length === 0) {
                $list.html('<div class="eau-selector-empty">No members found</div>');
                return;
            }

            let html = '';
            members.forEach(function(member) {
                html += `
                    <div class="eau-contact-option" data-member-id="${member.id}">
                        <div class="eau-option-avatar">
                            ${member.avatar
                                ? `<img src="${member.avatar}" alt="${member.name}">`
                                : `<div class="eau-avatar-initials">${member.name.charAt(0).toUpperCase()}</div>`
                            }
                        </div>
                        <div class="eau-option-info">
                            <span class="eau-option-name">${member.name}</span>
                            <span class="eau-option-email">${member.email}</span>
                        </div>
                        ${member.type ? `<span class="eau-option-badge">${member.type}</span>` : ''}
                    </div>
                `;
            });

            $list.html(html);
        },

        /**
         * Set primary contact
         */
        setPrimaryContact: function(memberId) {
            const self = this;

            $.ajax({
                url: eauInstitutionSingleData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_set_primary_contact',
                    nonce: eauInstitutionSingleData.nonce,
                    institution_id: self.institutionId,
                    member_id: memberId
                },
                success: function(response) {
                    if (response.success) {
                        // Update the display
                        const contact = response.data.primary_contact;
                        const avatarHtml = contact.avatar
                            ? `<img src="${contact.avatar}" alt="${contact.name}">`
                            : `<div class="eau-avatar-placeholder">${contact.name.charAt(0).toUpperCase()}</div>`;

                        const html = `
                            <div class="eau-contact-avatar">
                                ${avatarHtml}
                            </div>
                            <div class="eau-contact-info">
                                <h4>${contact.name}</h4>
                                ${contact.email ? `
                                    <a href="mailto:${contact.email}" class="eau-contact-email">
                                        <i data-lucide="mail"></i>
                                        ${contact.email}
                                    </a>
                                ` : ''}
                                ${contact.phone ? `
                                    <a href="tel:${contact.phone}" class="eau-contact-phone">
                                        <i data-lucide="phone"></i>
                                        ${contact.phone}
                                    </a>
                                ` : ''}
                            </div>
                        `;

                        $('#eau-primary-contact-display')
                            .removeClass('eau-primary-contact-empty')
                            .addClass('eau-primary-contact-card')
                            .html(html);

                        // Close selector
                        $('#eau-primary-contact-selector').slideUp(200);
                        $('#eau-primary-contact-search').val('');

                        // Reinitialize Lucide icons
                        if (typeof lucide !== 'undefined') {
                            lucide.createIcons();
                        }

                        if (typeof EauNotifications !== 'undefined') {
                            EauNotifications.success(response.data.message);
                        }

                        // Reload members list to update badges
                        self.loadMembers();
                    } else {
                        if (typeof EauNotifications !== 'undefined') {
                            EauNotifications.error(response.data?.message || 'Failed to update primary contact.');
                        }
                    }
                },
                error: function() {
                    if (typeof EauNotifications !== 'undefined') {
                        EauNotifications.error('An error occurred. Please try again.');
                    }
                }
            });
        },

        /**
         * Bind logo upload events
         */
        bindLogoUploadEvents: function() {
            const self = this;

            // Click on logo edit overlay
            $(document).on('click', '#eau-logo-edit-trigger', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $('#eau-logo-file-input').click();
            });

            // File input change
            $('#eau-logo-file-input').on('change', function() {
                const file = this.files[0];
                if (!file) return;

                // Validate file type
                if (!file.type.match('image.*')) {
                    if (typeof EauNotifications !== 'undefined') {
                        EauNotifications.error('Please select a valid image file.');
                    }
                    return;
                }

                // Validate file size (max 5MB)
                if (file.size > 5 * 1024 * 1024) {
                    if (typeof EauNotifications !== 'undefined') {
                        EauNotifications.error('File size must be less than 5MB.');
                    }
                    return;
                }

                self.uploadLogo(file);
            });
        },

        /**
         * Upload logo via AJAX
         */
        uploadLogo: function(file) {
            const self = this;
            const $logoContainer = $('.eau-hero-logo');

            // Show loading state
            $logoContainer.addClass('eau-logo-uploading');

            const formData = new FormData();
            formData.append('action', 'eau_upload_institution_logo');
            formData.append('nonce', eauInstitutionSingleData.nonce);
            formData.append('institution_id', self.institutionId);
            formData.append('logo', file);

            $.ajax({
                url: eauInstitutionSingleData.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        // Update logo image
                        const $logoImg = $('#eau-institution-logo-img');
                        const $placeholder = $('#eau-institution-logo-placeholder');

                        if ($logoImg.length) {
                            $logoImg.attr('src', response.data.logo_url);
                        } else if ($placeholder.length) {
                            $placeholder.replaceWith(
                                '<img src="' + response.data.logo_url + '" alt="Institution Logo" id="eau-institution-logo-img">'
                            );
                        }

                        if (typeof EauNotifications !== 'undefined') {
                            EauNotifications.success('Logo updated successfully!');
                        }

                        // Reinitialize Lucide icons
                        if (typeof lucide !== 'undefined') {
                            lucide.createIcons();
                        }
                    } else {
                        if (typeof EauNotifications !== 'undefined') {
                            EauNotifications.error(response.data?.message || 'Failed to upload logo.');
                        }
                    }
                },
                error: function() {
                    if (typeof EauNotifications !== 'undefined') {
                        EauNotifications.error('An error occurred. Please try again.');
                    }
                },
                complete: function() {
                    $logoContainer.removeClass('eau-logo-uploading');
                    // Clear the file input
                    $('#eau-logo-file-input').val('');
                }
            });
        },

        /**
         * Bind inline edit events
         */
        bindInlineEditEvents: function() {
            const self = this;

            // Click on editable field
            $(document).on('click', '.eau-editable', function(e) {
                e.preventDefault();
                e.stopPropagation();

                const $field = $(this);

                // Don't open if already editing
                if ($field.hasClass('eau-editing')) {
                    return;
                }

                // Check if this is a restricted field
                if ($field.hasClass('eau-restricted-field') && !eauInstitutionSingleData.canEditRestricted) {
                    return;
                }

                self.startEditing($field);
            });

            // Close editing when clicking outside
            $(document).on('click', function(e) {
                if (self.currentEditField && !$(e.target).closest('.eau-editable').length) {
                    self.saveAndClose(self.currentEditField);
                }
            });

            // Handle keyboard events
            $(document).on('keydown', '.eau-edit-input', function(e) {
                const $input = $(this);
                const $field = $input.closest('.eau-editable');

                if (e.key === 'Enter') {
                    e.preventDefault();
                    self.saveAndClose($field);
                } else if (e.key === 'Escape') {
                    e.preventDefault();
                    self.cancelEditing($field);
                }
            });

            // Handle select change
            $(document).on('change', '.eau-edit-select', function() {
                const $select = $(this);
                const $field = $select.closest('.eau-editable');
                self.saveAndClose($field);
            });
        },

        /**
         * Bind member management events
         */
        bindMemberManagementEvents: function() {
            const self = this;

            // Expand member row to show actions
            $(document).on('click', '.eau-member-row', function(e) {
                // Don't expand if clicking on action buttons
                if ($(e.target).closest('.eau-member-actions').length) {
                    return;
                }

                const $row = $(this);
                const memberId = $row.data('member-id');

                // Toggle expanded state
                if (self.expandedMemberId === memberId) {
                    self.collapseMemberRow($row);
                } else {
                    // Collapse any other expanded row
                    $('.eau-member-row.eau-expanded').each(function() {
                        self.collapseMemberRow($(this));
                    });
                    self.expandMemberRow($row, memberId);
                }
            });

            // Make admin button
            $(document).on('click', '.eau-btn-make-admin', function(e) {
                e.preventDefault();
                e.stopPropagation();

                const memberId = $(this).closest('.eau-member-row').data('member-id');
                const memberName = $(this).closest('.eau-member-row').find('.eau-member-name').text();
                const isAdmin = $(this).hasClass('eau-is-admin');

                if (isAdmin) {
                    self.removeAdminRole(memberId, memberName);
                } else {
                    self.makeAdmin(memberId, memberName);
                }
            });

            // Remove member button
            $(document).on('click', '.eau-btn-remove-member', function(e) {
                e.preventDefault();
                e.stopPropagation();

                const memberId = $(this).closest('.eau-member-row').data('member-id');
                const memberName = $(this).closest('.eau-member-row').find('.eau-member-name').text();

                self.confirmRemoveMember(memberId, memberName);
            });

            // Member field edit
            $(document).on('click', '.eau-member-editable', function(e) {
                e.preventDefault();
                e.stopPropagation();

                const $field = $(this);
                if ($field.hasClass('eau-editing')) return;

                self.startMemberFieldEditing($field);
            });

            // Member field keyboard events
            $(document).on('keydown', '.eau-member-edit-input', function(e) {
                const $input = $(this);
                const $field = $input.closest('.eau-member-editable');

                if (e.key === 'Enter') {
                    e.preventDefault();
                    self.saveMemberField($field);
                } else if (e.key === 'Escape') {
                    e.preventDefault();
                    self.cancelMemberFieldEditing($field);
                }
            });

            // Member field blur
            $(document).on('blur', '.eau-member-edit-input', function() {
                const $input = $(this);
                const $field = $input.closest('.eau-member-editable');
                // Small delay to allow click events on buttons to fire first
                setTimeout(function() {
                    if ($field.hasClass('eau-editing')) {
                        self.saveMemberField($field);
                    }
                }, 100);
            });
        },

        /**
         * Expand member row
         */
        expandMemberRow: function($row, memberId) {
            this.expandedMemberId = memberId;
            $row.addClass('eau-expanded');

            // Add expanded content if not exists
            if (!$row.find('.eau-member-expanded').length) {
                const member = $row.data('member');
                const canEditRestricted = eauInstitutionSingleData.canEditRestricted;
                const isAdmin = member.type === 'institutionAdmin';

                let expandedHtml = `
                    <div class="eau-member-expanded">
                        <div class="eau-member-details">
                            <div class="eau-member-detail-row">
                                <span class="eau-member-editable" data-field="first_name" data-member-id="${memberId}" data-value="${member.first_name || ''}">
                                    <span class="eau-detail-label">First Name:</span>
                                    <span class="eau-detail-value">${member.first_name || '<em>Not set</em>'}</span>
                                    <i data-lucide="pencil" class="eau-edit-icon-sm"></i>
                                </span>
                            </div>
                            <div class="eau-member-detail-row">
                                <span class="eau-member-editable" data-field="last_name" data-member-id="${memberId}" data-value="${member.last_name || ''}">
                                    <span class="eau-detail-label">Last Name:</span>
                                    <span class="eau-detail-value">${member.last_name || '<em>Not set</em>'}</span>
                                    <i data-lucide="pencil" class="eau-edit-icon-sm"></i>
                                </span>
                            </div>
                            <div class="eau-member-detail-row">
                                <span class="eau-member-editable" data-field="user_email" data-member-id="${memberId}" data-value="${member.email || ''}">
                                    <span class="eau-detail-label">Email:</span>
                                    <span class="eau-detail-value">${member.email || '<em>Not set</em>'}</span>
                                    <i data-lucide="pencil" class="eau-edit-icon-sm"></i>
                                </span>
                            </div>
                            <div class="eau-member-detail-row">
                                <span class="eau-member-editable" data-field="mem_memberphone" data-member-id="${memberId}" data-value="${member.phone || ''}">
                                    <span class="eau-detail-label">Phone:</span>
                                    <span class="eau-detail-value">${member.phone || '<em>Not set</em>'}</span>
                                    <i data-lucide="pencil" class="eau-edit-icon-sm"></i>
                                </span>
                            </div>
                        </div>
                        <div class="eau-member-actions">
                            ${canEditRestricted ? `
                                <button type="button" class="eau-btn eau-btn-sm ${isAdmin ? 'eau-btn-warning eau-is-admin' : 'eau-btn-primary'} eau-btn-make-admin">
                                    <i data-lucide="${isAdmin ? 'user-minus' : 'user-plus'}"></i>
                                    ${isAdmin ? 'Remove Admin' : 'Make Admin'}
                                </button>
                            ` : ''}
                            <button type="button" class="eau-btn eau-btn-sm eau-btn-danger eau-btn-remove-member">
                                <i data-lucide="user-x"></i>
                                Remove from Institution
                            </button>
                        </div>
                    </div>
                `;

                $row.append(expandedHtml);

                // Reinitialize Lucide icons
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
            }
        },

        /**
         * Collapse member row
         */
        collapseMemberRow: function($row) {
            this.expandedMemberId = null;
            $row.removeClass('eau-expanded');
            $row.find('.eau-member-expanded').remove();
        },

        /**
         * Start member field editing
         */
        startMemberFieldEditing: function($field) {
            const currentValue = $field.data('value') || '';
            const fieldName = $field.data('field');

            $field.addClass('eau-editing');

            const $valueEl = $field.find('.eau-detail-value');
            $valueEl.hide();
            $field.find('.eau-edit-icon-sm').hide();

            let inputType = 'text';
            if (fieldName === 'user_email') {
                inputType = 'email';
            }

            const $input = $(`<input type="${inputType}" class="eau-member-edit-input" value="${currentValue}">`);
            $input.data('original-value', currentValue);
            $field.append($input);
            $input.focus().select();
        },

        /**
         * Save member field
         */
        saveMemberField: function($field) {
            const self = this;
            const $input = $field.find('.eau-member-edit-input');
            if (!$input.length) return;

            const newValue = $input.val();
            const originalValue = $input.data('original-value');
            const fieldName = $field.data('field');
            const memberId = $field.data('member-id');

            // If no change, just close
            if (newValue === originalValue) {
                self.cancelMemberFieldEditing($field);
                return;
            }

            $field.addClass('eau-saving');

            $.ajax({
                url: eauInstitutionSingleData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_save_member_field',
                    nonce: eauInstitutionSingleData.nonce,
                    member_id: memberId,
                    institution_id: self.institutionId,
                    field: fieldName,
                    value: newValue
                },
                success: function(response) {
                    if (response.success) {
                        // Update value
                        $field.data('value', newValue);
                        $field.find('.eau-detail-value').html(newValue || '<em>Not set</em>');

                        // Update the row data
                        const $row = $field.closest('.eau-member-row');
                        const member = $row.data('member');
                        if (fieldName === 'first_name') {
                            member.first_name = newValue;
                            $row.find('.eau-member-name').text((newValue + ' ' + (member.last_name || '')).trim() || member.email);
                        } else if (fieldName === 'last_name') {
                            member.last_name = newValue;
                            $row.find('.eau-member-name').text(((member.first_name || '') + ' ' + newValue).trim() || member.email);
                        } else if (fieldName === 'user_email') {
                            member.email = newValue;
                            $row.find('.eau-member-email').text(newValue);
                        } else if (fieldName === 'mem_memberphone') {
                            member.phone = newValue;
                        }
                        $row.data('member', member);

                        if (typeof EauNotifications !== 'undefined') {
                            EauNotifications.success('Saved!', { duration: 2000 });
                        }
                    } else {
                        if (typeof EauNotifications !== 'undefined') {
                            EauNotifications.error(response.data?.message || 'Failed to save.');
                        }
                    }
                },
                error: function() {
                    if (typeof EauNotifications !== 'undefined') {
                        EauNotifications.error('An error occurred. Please try again.');
                    }
                },
                complete: function() {
                    self.cancelMemberFieldEditing($field);
                }
            });
        },

        /**
         * Cancel member field editing
         */
        cancelMemberFieldEditing: function($field) {
            $field.removeClass('eau-editing eau-saving');
            $field.find('.eau-member-edit-input').remove();
            $field.find('.eau-detail-value').show();
            $field.find('.eau-edit-icon-sm').show();
        },

        /**
         * Make member admin
         */
        makeAdmin: function(memberId, memberName) {
            const self = this;

            if (typeof EauNotifications !== 'undefined' && EauNotifications.confirm) {
                EauNotifications.confirm({
                    title: 'Promote to Institution Admin',
                    message: `Are you sure you want to make <strong>${memberName}</strong> an Institution Admin?<br><br>They will be able to manage members and edit institution details.`,
                    confirmText: 'Yes, Make Admin',
                    cancelText: 'Cancel',
                    type: 'warning',
                    onConfirm: function() {
                        self.doMakeAdmin(memberId);
                    }
                });
            } else {
                // Fallback to native confirm
                if (confirm(`Are you sure you want to make "${memberName}" an Institution Admin?\n\nThey will be able to manage members and edit institution details.`)) {
                    self.doMakeAdmin(memberId);
                }
            }
        },

        /**
         * Execute make admin action
         */
        doMakeAdmin: function(memberId) {
            const self = this;

            $.ajax({
                url: eauInstitutionSingleData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_set_institution_admin',
                    nonce: eauInstitutionSingleData.nonce,
                    member_id: memberId,
                    institution_id: self.institutionId,
                    make_admin: true
                },
                success: function(response) {
                    if (response.success) {
                        if (typeof EauNotifications !== 'undefined') {
                            EauNotifications.success(response.data.message);
                        }
                        // Reload members to reflect changes
                        self.loadMembers();
                    } else {
                        if (typeof EauNotifications !== 'undefined') {
                            EauNotifications.error(response.data?.message || 'Failed to update member.');
                        }
                    }
                },
                error: function() {
                    if (typeof EauNotifications !== 'undefined') {
                        EauNotifications.error('An error occurred. Please try again.');
                    }
                }
            });
        },

        /**
         * Remove admin role
         */
        removeAdminRole: function(memberId, memberName) {
            const self = this;

            if (typeof EauNotifications !== 'undefined' && EauNotifications.confirm) {
                EauNotifications.confirm({
                    title: 'Remove Admin Role',
                    message: `Are you sure you want to remove admin role from <strong>${memberName}</strong>?<br><br>They will become a regular member.`,
                    confirmText: 'Yes, Remove Admin',
                    cancelText: 'Cancel',
                    type: 'warning',
                    onConfirm: function() {
                        self.doRemoveAdminRole(memberId);
                    }
                });
            } else {
                // Fallback to native confirm
                if (confirm(`Are you sure you want to remove admin role from "${memberName}"?\n\nThey will become a regular member.`)) {
                    self.doRemoveAdminRole(memberId);
                }
            }
        },

        /**
         * Execute remove admin role action
         */
        doRemoveAdminRole: function(memberId) {
            const self = this;

            $.ajax({
                url: eauInstitutionSingleData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_set_institution_admin',
                    nonce: eauInstitutionSingleData.nonce,
                    member_id: memberId,
                    institution_id: self.institutionId,
                    make_admin: false
                },
                success: function(response) {
                    if (response.success) {
                        if (typeof EauNotifications !== 'undefined') {
                            EauNotifications.success(response.data.message);
                        }
                        // Reload members to reflect changes
                        self.loadMembers();
                    } else {
                        if (typeof EauNotifications !== 'undefined') {
                            EauNotifications.error(response.data?.message || 'Failed to update member.');
                        }
                    }
                },
                error: function() {
                    if (typeof EauNotifications !== 'undefined') {
                        EauNotifications.error('An error occurred. Please try again.');
                    }
                }
            });
        },

        /**
         * Confirm and remove member
         */
        confirmRemoveMember: function(memberId, memberName) {
            const self = this;

            if (typeof EauNotifications !== 'undefined' && EauNotifications.confirm) {
                EauNotifications.confirm({
                    title: 'Remove Member from Institution',
                    message: `Are you sure you want to remove <strong>${memberName}</strong> from this institution?<br><br>They will become a "non-member" and will need to request membership again to rejoin any institution.`,
                    confirmText: 'Yes, Remove Member',
                    cancelText: 'Cancel',
                    type: 'danger',
                    onConfirm: function() {
                        self.doRemoveMember(memberId);
                    }
                });
            } else {
                // Fallback to native confirm
                if (confirm(`Are you sure you want to remove "${memberName}" from this institution?\n\nThey will become a "non-member" and will need to request membership again to rejoin any institution.`)) {
                    self.doRemoveMember(memberId);
                }
            }
        },

        /**
         * Execute remove member action
         */
        doRemoveMember: function(memberId) {
            const self = this;

            $.ajax({
                url: eauInstitutionSingleData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_remove_member_from_institution',
                    nonce: eauInstitutionSingleData.nonce,
                    member_id: memberId,
                    institution_id: self.institutionId
                },
                success: function(response) {
                    if (response.success) {
                        if (typeof EauNotifications !== 'undefined') {
                            EauNotifications.success(response.data.message);
                        }
                        // Reload members to reflect changes
                        self.loadMembers();
                        // Update member count in hero
                        const $countBadge = $('.eau-members-count-badge');
                        if ($countBadge.length) {
                            const currentCount = parseInt($countBadge.text()) || 0;
                            $countBadge.text(Math.max(0, currentCount - 1));
                        }
                        const $statNumber = $('.eau-hero-stat .eau-stat-number').first();
                        if ($statNumber.length) {
                            const currentStat = parseInt($statNumber.text()) || 0;
                            $statNumber.text(Math.max(0, currentStat - 1));
                        }
                    } else {
                        if (typeof EauNotifications !== 'undefined') {
                            EauNotifications.error(response.data?.message || 'Failed to remove member.');
                        }
                    }
                },
                error: function() {
                    if (typeof EauNotifications !== 'undefined') {
                        EauNotifications.error('An error occurred. Please try again.');
                    }
                }
            });
        },

        /**
         * Start editing a field
         */
        startEditing: function($field) {
            const self = this;

            // Close any other open field
            if (this.currentEditField && this.currentEditField[0] !== $field[0]) {
                this.saveAndClose(this.currentEditField);
            }

            const fieldName = $field.data('field');
            const fieldType = $field.data('type') || 'text';
            const currentValue = $field.data('value') || '';
            const options = $field.data('options');

            // Mark as editing
            $field.addClass('eau-editing');
            this.currentEditField = $field;

            // Hide display, show input
            const $display = $field.find('.eau-editable-display');
            $display.hide();
            $field.find('.eau-edit-icon').hide();

            // Create input based on type
            let $input;

            if (fieldType === 'select' && options) {
                $input = $('<select class="eau-edit-select"></select>');
                $.each(options, function(value, label) {
                    const $option = $('<option></option>')
                        .val(value)
                        .text(label);
                    if (value === currentValue) {
                        $option.prop('selected', true);
                    }
                    $input.append($option);
                });
            } else if (fieldType === 'date') {
                $input = $('<input type="date" class="eau-edit-input">')
                    .val(currentValue);
            } else if (fieldType === 'email') {
                $input = $('<input type="email" class="eau-edit-input">')
                    .val(currentValue);
            } else {
                $input = $('<input type="text" class="eau-edit-input">')
                    .val(currentValue);
            }

            $input.data('original-value', currentValue);
            $field.append($input);

            // Focus and select
            setTimeout(function() {
                $input.focus();
                if ($input.is('input')) {
                    $input.select();
                }
            }, 10);
        },

        /**
         * Save and close editing
         */
        saveAndClose: function($field) {
            const self = this;
            const $input = $field.find('.eau-edit-input, .eau-edit-select');

            if ($input.length === 0) {
                this.cancelEditing($field);
                return;
            }

            const newValue = $input.val();
            const originalValue = $input.data('original-value');
            const fieldName = $field.data('field');

            // If value hasn't changed, just close
            if (newValue === originalValue) {
                this.cancelEditing($field);
                return;
            }

            // Show loading state
            $field.addClass('eau-saving');

            // Save via AJAX
            $.ajax({
                url: eauInstitutionSingleData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_save_institution_field',
                    nonce: eauInstitutionSingleData.nonce,
                    institution_id: this.institutionId,
                    field: fieldName,
                    value: newValue
                },
                success: function(response) {
                    if (response.success) {
                        // Update the field value and display
                        $field.data('value', newValue);

                        // Update display based on response
                        if (response.data && response.data.display) {
                            $field.find('.eau-editable-display').html(response.data.display);
                        } else {
                            // Simple text update
                            const displayValue = newValue || '<span class="eau-empty-value">Click to edit</span>';
                            $field.find('.eau-editable-display').html(displayValue);
                        }

                        // Show success notification
                        if (typeof EauNotifications !== 'undefined') {
                            EauNotifications.success('Saved!', { duration: 2000 });
                        }

                        // Reinitialize Lucide icons
                        if (typeof lucide !== 'undefined') {
                            lucide.createIcons();
                        }
                    } else {
                        // Show error
                        if (typeof EauNotifications !== 'undefined') {
                            EauNotifications.error(response.data?.message || 'Failed to save.');
                        }
                        // Restore original value
                        $field.data('value', originalValue);
                    }
                },
                error: function() {
                    if (typeof EauNotifications !== 'undefined') {
                        EauNotifications.error('An error occurred. Please try again.');
                    }
                    $field.data('value', originalValue);
                },
                complete: function() {
                    self.closeEditing($field);
                }
            });
        },

        /**
         * Cancel editing without saving
         */
        cancelEditing: function($field) {
            this.closeEditing($field);
        },

        /**
         * Close editing mode
         */
        closeEditing: function($field) {
            $field.removeClass('eau-editing eau-saving');
            $field.find('.eau-edit-input, .eau-edit-select').remove();
            $field.find('.eau-editable-display').show();
            $field.find('.eau-edit-icon').show();

            if (this.currentEditField && this.currentEditField[0] === $field[0]) {
                this.currentEditField = null;
            }
        },

        /**
         * Load members via AJAX
         */
        loadMembers: function() {
            const self = this;
            const $list = $('#eau-members-list');
            const $pagination = $('#eau-members-pagination');

            // Reset expanded state
            self.expandedMemberId = null;

            // Show skeleton loading
            $list.html(self.getSkeletonHTML());

            $.ajax({
                url: eauInstitutionSingleData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_get_institution_members_paginated',
                    nonce: eauInstitutionSingleData.nonce,
                    institution_id: self.institutionId,
                    page: self.currentPage,
                    per_page: self.perPage,
                    search: self.searchTerm,
                    status: self.statusFilter,
                },
                success: function(response) {
                    if (response.success) {
                        self.renderMembers(response.data.members);
                        self.renderPagination(response.data);
                    } else {
                        $list.html(self.getEmptyStateHTML('Failed to load members'));
                        $pagination.html('');
                    }
                },
                error: function() {
                    $list.html(self.getEmptyStateHTML('Failed to load members'));
                    $pagination.html('');
                }
            });
        },

        /**
         * Render members list
         */
        renderMembers: function(members) {
            const self = this;
            const $list = $('#eau-members-list');
            const canEditMembers = eauInstitutionSingleData.canEditMembers;

            if (!members || members.length === 0) {
                $list.html(this.getEmptyStateHTML('No members found'));
                return;
            }

            let html = '';
            members.forEach(function(member) {
                const isAdmin = member.type === 'institutionAdmin';
                const cursorClass = canEditMembers ? 'eau-member-clickable' : '';

                html += `
                    <div class="eau-member-row ${cursorClass}" data-member-id="${member.id}" data-member='${JSON.stringify(member).replace(/'/g, "&#39;")}'>
                        <div class="eau-member-avatar">
                            ${member.avatar
                                ? `<img src="${member.avatar}" alt="${member.name}">`
                                : `<div class="eau-avatar-initials">${member.name.charAt(0).toUpperCase()}</div>`
                            }
                        </div>
                        <div class="eau-member-info">
                            <h4 class="eau-member-name">${member.name}</h4>
                            <p class="eau-member-email">${member.email}</p>
                        </div>
                        <div class="eau-member-badges">
                            ${member.type
                                ? `<span class="eau-member-type-badge ${isAdmin ? 'eau-type-admin' : ''}">${member.type}</span>`
                                : ''
                            }
                            <span class="eau-member-status-badge eau-status-${member.status.toLowerCase()}">${member.status}</span>
                        </div>
                        ${canEditMembers ? '<i data-lucide="chevron-down" class="eau-member-expand-icon"></i>' : ''}
                    </div>
                `;
            });

            $list.html(html);

            // Reinitialize Lucide icons
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        },

        /**
         * Render pagination
         */
        renderPagination: function(data) {
            const $pagination = $('#eau-members-pagination');

            if (!data.total_pages || data.total_pages <= 1) {
                $pagination.html('');
                return;
            }

            const currentPage = data.page;
            const totalPages = data.total_pages;
            const total = data.total;

            let html = '';

            // Previous button
            html += `
                <button class="eau-pagination-btn" data-page="${currentPage - 1}" ${currentPage <= 1 ? 'disabled' : ''}>
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
                </button>
            `;

            // Page numbers
            const maxPages = 5;
            let startPage = Math.max(1, currentPage - Math.floor(maxPages / 2));
            let endPage = Math.min(totalPages, startPage + maxPages - 1);

            if (endPage - startPage < maxPages - 1) {
                startPage = Math.max(1, endPage - maxPages + 1);
            }

            if (startPage > 1) {
                html += `<button class="eau-pagination-btn" data-page="1">1</button>`;
                if (startPage > 2) {
                    html += `<span class="eau-pagination-info">...</span>`;
                }
            }

            for (let i = startPage; i <= endPage; i++) {
                html += `
                    <button class="eau-pagination-btn ${i === currentPage ? 'active' : ''}" data-page="${i}">
                        ${i}
                    </button>
                `;
            }

            if (endPage < totalPages) {
                if (endPage < totalPages - 1) {
                    html += `<span class="eau-pagination-info">...</span>`;
                }
                html += `<button class="eau-pagination-btn" data-page="${totalPages}">${totalPages}</button>`;
            }

            // Next button
            html += `
                <button class="eau-pagination-btn" data-page="${currentPage + 1}" ${currentPage >= totalPages ? 'disabled' : ''}>
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                </button>
            `;

            $pagination.html(html);
        },

        /**
         * Get skeleton loading HTML
         */
        getSkeletonHTML: function() {
            let html = '<div class="eau-loading-skeleton">';
            for (let i = 0; i < 5; i++) {
                html += `
                    <div class="eau-skeleton-row">
                        <div class="eau-skeleton eau-skeleton-avatar"></div>
                        <div class="eau-skeleton-content">
                            <div class="eau-skeleton eau-skeleton-text"></div>
                            <div class="eau-skeleton eau-skeleton-text-sm"></div>
                        </div>
                    </div>
                `;
            }
            html += '</div>';
            return html;
        },

        /**
         * Get empty state HTML
         */
        getEmptyStateHTML: function(message) {
            return `
                <div class="eau-members-empty">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                    <p>${message}</p>
                </div>
            `;
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        EauInstitutionSingle.init();

        // Re-initialize Lucide icons
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    });

})(jQuery);
