/**
 * Settings Page Controller
 *
 * @since 1.39.0
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

        /**
         * Initialize controller
         */
        init: function() {
            this.bindEvents();
            this.loadTags();

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
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        EauSettingsController.init();
    });

})(jQuery);
