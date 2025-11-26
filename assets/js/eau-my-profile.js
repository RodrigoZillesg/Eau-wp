/**
 * My Profile Page JavaScript
 *
 * @since 1.40.0
 */
(function($) {
    'use strict';

    const EauMyProfile = {
        // State
        userData: null,
        isLoading: false,
        phoneIti: null, // intl-tel-input instance

        /**
         * Inicializa o controller
         */
        init: function() {
            this.bindEvents();
            this.loadProfile();
            this.initPhoneInput();
        },

        /**
         * Inicializa o intl-tel-input para o campo de telefone
         */
        initPhoneInput: function() {
            const phoneInput = document.querySelector('#edit-phone');
            if (phoneInput && typeof intlTelInput !== 'undefined') {
                this.phoneIti = intlTelInput(phoneInput, {
                    initialCountry: 'au',
                    preferredCountries: ['au', 'nz', 'gb', 'us'],
                    separateDialCode: true,
                    utilsScript: 'https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/utils.js',
                    formatOnDisplay: true,
                    nationalMode: false,
                    autoPlaceholder: 'aggressive'
                });
            }
        },

        /**
         * Vincula eventos
         */
        bindEvents: function() {
            const self = this;

            // Edit buttons
            $(document).on('click', '.eau-profile-edit-btn', function() {
                const section = $(this).data('section');
                self.openEditModal(section);
            });

            // Save personal info
            $(document).on('click', '#eau-save-personal-btn', function() {
                self.savePersonalInfo();
            });

            // Save address
            $(document).on('click', '#eau-save-address-btn', function() {
                self.saveAddress();
            });

            // Change password button
            $(document).on('click', '#eau-change-password-btn', function() {
                self.openPasswordModal();
            });

            // Save password
            $(document).on('click', '#eau-save-password-btn', function() {
                self.changePassword();
            });

            // Password toggle visibility
            $(document).on('click', '.eau-password-toggle', function() {
                self.togglePasswordVisibility($(this));
            });

            // Photo upload button
            $(document).on('click', '.eau-profile-avatar-upload', function() {
                self.openPhotoUpload();
            });

            // Save photo button
            $(document).on('click', '#eau-save-photo-btn', function() {
                self.savePhoto();
            });

            // Profile photo upload - Browse button
            $(document).on('click', '#upload-photo-modal .eau-media-upload-browse-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $('#eau-profile-photo-file-input')[0].click();
            });

            // Profile photo upload - Click on dropzone
            $(document).on('click', '#eau-profile-photo-dropzone', function(e) {
                if ($(e.target).closest('.eau-media-upload-browse-btn').length ||
                    $(e.target).is('input[type="file"]')) {
                    return;
                }
                e.preventDefault();
                e.stopPropagation();
                $('#eau-profile-photo-file-input')[0].click();
            });

            // Profile photo upload - File input change
            $(document).on('change', '#eau-profile-photo-file-input', function() {
                const file = this.files[0];
                if (file) {
                    self.uploadProfilePhoto(file);
                }
                $(this).val('');
            });

            // Profile photo upload - Drag and drop
            $(document).on('dragover dragenter', '#eau-profile-photo-dropzone', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).addClass('drag-active');
            });

            $(document).on('dragleave dragend drop', '#eau-profile-photo-dropzone', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('drag-active');
            });

            $(document).on('drop', '#eau-profile-photo-dropzone', function(e) {
                e.preventDefault();
                const files = e.originalEvent.dataTransfer.files;
                if (files.length > 0) {
                    self.uploadProfilePhoto(files[0]);
                }
            });

            // Profile photo upload - Remove preview
            $(document).on('click', '#eau-profile-photo-remove', function() {
                self.clearPhotoUpload();
            });

            // Remove photo from profile
            $(document).on('click', '.eau-photo-remove-btn', function() {
                self.removePhoto();
            });

            // Modal close buttons
            $(document).on('click', '[data-modal-action="close"]', function() {
                self.closeAllModals();
            });

            // Close modal on overlay click
            $(document).on('click', '.eau-modal-overlay', function(e) {
                if ($(e.target).hasClass('eau-modal-overlay')) {
                    self.closeAllModals();
                }
            });

            // Close modal on ESC
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') {
                    self.closeAllModals();
                }
            });

            // Enter to submit forms
            $(document).on('keydown', '.eau-modal input', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const modal = $(this).closest('.eau-modal');
                    modal.find('.eau-modal-footer .eau-btn-primary').trigger('click');
                }
            });
        },

        /**
         * Carrega dados do perfil
         */
        loadProfile: function() {
            const self = this;
            this.isLoading = true;

            $.ajax({
                url: eau_my_profile_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'eau_get_my_profile',
                    nonce: eau_my_profile_vars.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.userData = response.data;
                        self.renderProfile();
                    } else {
                        EauNotifications.error(response.data.message || 'Failed to load profile.');
                    }
                },
                error: function() {
                    EauNotifications.error('Failed to load profile. Please try again.');
                },
                complete: function() {
                    self.isLoading = false;
                    lucide.createIcons();
                }
            });
        },

        /**
         * Renderiza os dados do perfil na página
         */
        renderProfile: function() {
            const data = this.userData;

            // Render Header
            this.renderHeader(data);

            // Render Personal Information
            this.renderPersonalInfo(data);

            // Render Address
            this.renderAddress(data);

            // Render Account Information
            this.renderAccountInfo(data);

            // Re-inicializa Lucide icons
            lucide.createIcons();
        },

        /**
         * Renderiza o header do perfil
         */
        renderHeader: function(data) {
            // Usa first_name + last_name ao invés de display_name
            let fullName = '';
            if (data.first_name || data.last_name) {
                fullName = ((data.first_name || '') + ' ' + (data.last_name || '')).trim();
            }
            if (!fullName) {
                fullName = data.display_name || 'User';
            }
            const statusClass = (data.mem_status || '').toLowerCase() === 'active' ? 'active' : 'inactive';

            const html = `
                <div class="eau-profile-avatar">
                    <img src="${this.escapeHtml(data.profile_photo_url)}" alt="Profile Photo" class="eau-profile-avatar-image">
                    <button type="button" class="eau-profile-avatar-upload" title="Change photo">
                        <i data-lucide="camera"></i>
                    </button>
                </div>
                <div class="eau-profile-info">
                    <h1 class="eau-profile-name">${this.escapeHtml(fullName)}</h1>
                    <p class="eau-profile-email">${this.escapeHtml(data.user_email)}</p>
                    <p class="eau-profile-since">Member since ${this.escapeHtml(data.user_registered)}</p>
                    <div class="eau-profile-badges">
                        <span class="eau-profile-badge">
                            <i data-lucide="user"></i>
                            ${this.escapeHtml(data.mem_type_label || 'Member')}
                        </span>
                        <span class="eau-profile-badge eau-profile-badge-status-${statusClass}">
                            <i data-lucide="${statusClass === 'active' ? 'check-circle' : 'x-circle'}"></i>
                            ${this.escapeHtml(this.capitalizeFirst(data.mem_status || 'Unknown'))}
                        </span>
                    </div>
                </div>
            `;

            $('#eau-profile-header').html(html);
        },

        /**
         * Renderiza informações pessoais
         */
        renderPersonalInfo: function(data) {
            const html = `
                <div class="eau-profile-grid">
                    <div class="eau-profile-field">
                        <span class="eau-profile-field-label">First Name</span>
                        <span class="eau-profile-field-value">${this.escapeHtml(data.first_name)}</span>
                    </div>
                    <div class="eau-profile-field">
                        <span class="eau-profile-field-label">Last Name</span>
                        <span class="eau-profile-field-value">${this.escapeHtml(data.last_name)}</span>
                    </div>
                    <div class="eau-profile-field">
                        <span class="eau-profile-field-label">Email</span>
                        <span class="eau-profile-field-value">${this.escapeHtml(data.user_email)}</span>
                    </div>
                    <div class="eau-profile-field">
                        <span class="eau-profile-field-label">Phone</span>
                        <span class="eau-profile-field-value">${this.escapeHtml(data.mem_phone)}</span>
                    </div>
                    <div class="eau-profile-field eau-profile-field-span-2">
                        <span class="eau-profile-field-label">Bio</span>
                        <span class="eau-profile-field-value">${this.escapeHtml(data.description)}</span>
                    </div>
                </div>
            `;

            $('#eau-profile-personal .eau-profile-section-body').html(html);
        },

        /**
         * Renderiza endereço
         */
        renderAddress: function(data) {
            const html = `
                <div class="eau-profile-grid">
                    <div class="eau-profile-field eau-profile-field-span-2">
                        <span class="eau-profile-field-label">Address</span>
                        <span class="eau-profile-field-value">${this.escapeHtml(data.mem_address)}</span>
                    </div>
                    <div class="eau-profile-field">
                        <span class="eau-profile-field-label">City</span>
                        <span class="eau-profile-field-value">${this.escapeHtml(data.mem_city)}</span>
                    </div>
                    <div class="eau-profile-field">
                        <span class="eau-profile-field-label">Postcode</span>
                        <span class="eau-profile-field-value">${this.escapeHtml(data.mem_postcode)}</span>
                    </div>
                </div>
            `;

            $('#eau-profile-address .eau-profile-section-body').html(html);
        },

        /**
         * Renderiza informações da conta (readonly)
         */
        renderAccountInfo: function(data) {
            const statusClass = (data.mem_status || '').toLowerCase() === 'active' ? 'active' : 'inactive';

            const html = `
                <div class="eau-profile-grid">
                    <div class="eau-profile-field">
                        <span class="eau-profile-field-label">Username</span>
                        <span class="eau-profile-field-value eau-profile-field-readonly">${this.escapeHtml(data.user_login)}</span>
                    </div>
                    <div class="eau-profile-field">
                        <span class="eau-profile-field-label">Member ID</span>
                        <span class="eau-profile-field-value eau-profile-field-readonly">${this.escapeHtml(data.mem_userid)}</span>
                    </div>
                    <div class="eau-profile-field">
                        <span class="eau-profile-field-label">User Type</span>
                        <span class="eau-profile-field-value">
                            <span class="eau-profile-type-badge">${this.escapeHtml(data.mem_type_label || 'Member')}</span>
                        </span>
                    </div>
                    <div class="eau-profile-field">
                        <span class="eau-profile-field-label">Status</span>
                        <span class="eau-profile-field-value">
                            <span class="eau-profile-status-badge eau-profile-status-${statusClass}">
                                ${this.escapeHtml(this.capitalizeFirst(data.mem_status || 'Unknown'))}
                            </span>
                        </span>
                    </div>
                    <div class="eau-profile-field eau-profile-field-span-2">
                        <span class="eau-profile-field-label">Institution</span>
                        <span class="eau-profile-field-value eau-profile-field-readonly">${this.escapeHtml(data.institution_name)}</span>
                    </div>
                </div>
            `;

            $('#eau-profile-account .eau-profile-section-body').html(html);
        },

        /**
         * Abre modal de edição
         */
        openEditModal: function(section) {
            if (section === 'personal') {
                this.populatePersonalForm();
                $('#edit-personal-modal-overlay').fadeIn(200);
            } else if (section === 'address') {
                this.populateAddressForm();
                $('#edit-address-modal-overlay').fadeIn(200);
            }

            lucide.createIcons();
        },

        /**
         * Popula formulário de dados pessoais
         */
        populatePersonalForm: function() {
            const data = this.userData;
            $('#edit-first-name').val(data.first_name || '');
            $('#edit-last-name').val(data.last_name || '');
            $('#edit-email').val(data.user_email || '');
            $('#edit-bio').val(data.description || '');

            // Popula o campo de telefone com intl-tel-input
            if (this.phoneIti && data.mem_phone) {
                this.phoneIti.setNumber(data.mem_phone);
            } else {
                $('#edit-phone').val(data.mem_phone || '');
            }
        },

        /**
         * Popula formulário de endereço
         */
        populateAddressForm: function() {
            const data = this.userData;
            $('#edit-address').val(data.mem_address || '');
            $('#edit-city').val(data.mem_city || '');
            $('#edit-postcode').val(data.mem_postcode || '');
        },

        /**
         * Salva informações pessoais
         */
        savePersonalInfo: function() {
            const self = this;
            const $btn = $('#eau-save-personal-btn');

            // Validações
            const email = $('#edit-email').val().trim();

            if (!email) {
                EauNotifications.error('Email is required.');
                return;
            }

            // Desabilita botão
            $btn.prop('disabled', true).html('<i data-lucide="loader-2" class="eau-spin"></i> Saving...');
            lucide.createIcons();

            // Pega o número de telefone formatado do intl-tel-input
            let phoneNumber = '';
            if (this.phoneIti) {
                phoneNumber = this.phoneIti.getNumber();
            } else {
                phoneNumber = $('#edit-phone').val().trim();
            }

            const fields = {
                first_name: $('#edit-first-name').val().trim(),
                last_name: $('#edit-last-name').val().trim(),
                user_email: email,
                mem_phone: phoneNumber,
                description: $('#edit-bio').val().trim()
            };

            $.ajax({
                url: eau_my_profile_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'eau_update_my_profile',
                    nonce: eau_my_profile_vars.nonce,
                    fields: fields
                },
                success: function(response) {
                    if (response.success) {
                        EauNotifications.success(response.data.message);
                        self.closeAllModals();
                        self.loadProfile(); // Recarrega dados
                    } else {
                        EauNotifications.error(response.data.message || 'Failed to update profile.');
                    }
                },
                error: function() {
                    EauNotifications.error('Failed to update profile. Please try again.');
                },
                complete: function() {
                    $btn.prop('disabled', false).html('<i data-lucide="check"></i> Save Changes');
                    lucide.createIcons();
                }
            });
        },

        /**
         * Salva endereço
         */
        saveAddress: function() {
            const self = this;
            const $btn = $('#eau-save-address-btn');

            // Desabilita botão
            $btn.prop('disabled', true).html('<i data-lucide="loader-2" class="eau-spin"></i> Saving...');
            lucide.createIcons();

            const fields = {
                mem_address: $('#edit-address').val().trim(),
                mem_city: $('#edit-city').val().trim(),
                mem_postcode: $('#edit-postcode').val().trim()
            };

            $.ajax({
                url: eau_my_profile_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'eau_update_my_profile',
                    nonce: eau_my_profile_vars.nonce,
                    fields: fields
                },
                success: function(response) {
                    if (response.success) {
                        EauNotifications.success(response.data.message);
                        self.closeAllModals();
                        self.loadProfile();
                    } else {
                        EauNotifications.error(response.data.message || 'Failed to update address.');
                    }
                },
                error: function() {
                    EauNotifications.error('Failed to update address. Please try again.');
                },
                complete: function() {
                    $btn.prop('disabled', false).html('<i data-lucide="check"></i> Save Changes');
                    lucide.createIcons();
                }
            });
        },

        /**
         * Abre modal de alteração de senha
         */
        openPasswordModal: function() {
            // Limpa campos
            $('#current-password').val('');
            $('#new-password').val('');
            $('#confirm-password').val('');

            // Reseta visibilidade das senhas
            $('.eau-password-toggle').each(function() {
                const targetId = $(this).data('target');
                $('#' + targetId).attr('type', 'password');
                $(this).find('i').attr('data-lucide', 'eye');
            });

            $('#change-password-modal-overlay').fadeIn(200);
            lucide.createIcons();
        },

        /**
         * Altera a senha
         */
        changePassword: function() {
            const self = this;
            const $btn = $('#eau-save-password-btn');

            const currentPassword = $('#current-password').val();
            const newPassword = $('#new-password').val();
            const confirmPassword = $('#confirm-password').val();

            // Validações
            if (!currentPassword) {
                EauNotifications.error('Current password is required.');
                return;
            }

            if (!newPassword) {
                EauNotifications.error('New password is required.');
                return;
            }

            if (newPassword.length < 8) {
                EauNotifications.error('New password must be at least 8 characters.');
                return;
            }

            if (newPassword !== confirmPassword) {
                EauNotifications.error('Passwords do not match.');
                return;
            }

            // Desabilita botão
            $btn.prop('disabled', true).html('<i data-lucide="loader-2" class="eau-spin"></i> Updating...');
            lucide.createIcons();

            $.ajax({
                url: eau_my_profile_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'eau_change_password',
                    nonce: eau_my_profile_vars.nonce,
                    current_password: currentPassword,
                    new_password: newPassword,
                    confirm_password: confirmPassword
                },
                success: function(response) {
                    if (response.success) {
                        EauNotifications.success(response.data.message);
                        self.closeAllModals();
                    } else {
                        EauNotifications.error(response.data.message || 'Failed to change password.');
                    }
                },
                error: function() {
                    EauNotifications.error('Failed to change password. Please try again.');
                },
                complete: function() {
                    $btn.prop('disabled', false).html('<i data-lucide="check"></i> Update Password');
                    lucide.createIcons();
                }
            });
        },

        /**
         * Toggle visibilidade da senha
         */
        togglePasswordVisibility: function($btn) {
            const targetId = $btn.data('target');
            const $input = $('#' + targetId);
            const $icon = $btn.find('i');

            if ($input.attr('type') === 'password') {
                $input.attr('type', 'text');
                $icon.attr('data-lucide', 'eye-off');
            } else {
                $input.attr('type', 'password');
                $icon.attr('data-lucide', 'eye');
            }

            lucide.createIcons();
        },

        /**
         * Abre modal de upload de foto de perfil
         */
        openPhotoUpload: function() {
            // Limpa o estado anterior
            this.clearPhotoUpload();

            // Abre o modal
            $('#upload-photo-modal-overlay').fadeIn(200);
            lucide.createIcons();
        },

        /**
         * Upload da foto de perfil para o servidor
         */
        uploadProfilePhoto: function(file) {
            const self = this;
            const maxSize = 5242880; // 5MB
            const allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            // Validar tamanho
            if (file.size > maxSize) {
                EauNotifications.error('File is too large. Maximum size is 5 MB.');
                return;
            }

            // Validar extensão
            const ext = file.name.split('.').pop().toLowerCase();
            if (!allowedExtensions.includes(ext)) {
                EauNotifications.error('File type not allowed. Allowed: JPG, JPEG, PNG, GIF, WEBP');
                return;
            }

            // Mostrar progresso
            const dropzone = $('#eau-profile-photo-dropzone');
            const progressContainer = dropzone.find('.eau-media-upload-progress');
            const progressFill = progressContainer.find('.eau-media-upload-progress-fill');
            const progressText = progressContainer.find('.eau-media-upload-progress-text');
            const dropzoneContent = dropzone.find('.eau-media-upload-dropzone-content');

            dropzoneContent.hide();
            progressContainer.show();
            progressFill.css('width', '0%');
            progressText.text('Uploading... 0%');

            // Criar FormData
            const formData = new FormData();
            formData.append('action', 'eau_upload_profile_photo');
            formData.append('nonce', eau_my_profile_vars.nonce);
            formData.append('file', file);

            // Upload via AJAX
            $.ajax({
                url: eau_my_profile_vars.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                xhr: function() {
                    const xhr = new window.XMLHttpRequest();
                    xhr.upload.addEventListener('progress', function(e) {
                        if (e.lengthComputable) {
                            const percent = Math.round((e.loaded / e.total) * 100);
                            progressFill.css('width', percent + '%');
                            progressText.text('Uploading... ' + percent + '%');
                        }
                    }, false);
                    return xhr;
                },
                success: function(response) {
                    if (response.success) {
                        // Mostrar preview
                        self.showPhotoPreview(response.data.id, response.data.url, response.data.filename);
                        EauNotifications.success('Photo uploaded successfully. Click "Save Photo" to apply.');
                    } else {
                        EauNotifications.error(response.data.message || 'Upload failed');
                    }
                },
                error: function() {
                    EauNotifications.error('Upload failed. Please try again.');
                },
                complete: function() {
                    progressContainer.hide();
                    dropzoneContent.show();
                }
            });
        },

        /**
         * Mostra preview da foto selecionada
         */
        showPhotoPreview: function(attachmentId, url, filename) {
            $('#eau-profile-photo-value').val(attachmentId);
            $('#eau-profile-photo-preview-name').text(filename);
            $('#eau-profile-photo-thumbnail .eau-media-upload-preview-image').attr('src', url).show();
            $('#eau-profile-photo-thumbnail').addClass('has-image');
            $('#eau-profile-photo-preview').show();

            // Habilitar botão de salvar
            $('#eau-save-photo-btn').prop('disabled', false);

            lucide.createIcons();
        },

        /**
         * Limpa o upload de foto
         */
        clearPhotoUpload: function() {
            $('#eau-profile-photo-value').val('');
            $('#eau-profile-photo-preview-name').text('');
            $('#eau-profile-photo-thumbnail .eau-media-upload-preview-image').attr('src', '').hide();
            $('#eau-profile-photo-thumbnail').removeClass('has-image');
            $('#eau-profile-photo-preview').hide();

            // Desabilitar botão de salvar
            $('#eau-save-photo-btn').prop('disabled', true);
        },

        /**
         * Salva a foto de perfil selecionada
         */
        savePhoto: function() {
            const attachmentId = $('#eau-profile-photo-value').val();

            if (!attachmentId) {
                EauNotifications.error('Please select a photo first.');
                return;
            }

            this.updateProfilePhoto(attachmentId);
            this.closeAllModals();
        },

        /**
         * Atualiza foto de perfil
         */
        updateProfilePhoto: function(attachmentId) {
            const self = this;

            $.ajax({
                url: eau_my_profile_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'eau_update_profile_photo',
                    nonce: eau_my_profile_vars.nonce,
                    attachment_id: attachmentId
                },
                success: function(response) {
                    if (response.success) {
                        EauNotifications.success(response.data.message);
                        // Atualiza a imagem no header
                        $('.eau-profile-avatar-image').attr('src', response.data.profile_photo_url);
                        self.userData.profile_photo_url = response.data.profile_photo_url;
                    } else {
                        EauNotifications.error(response.data.message || 'Failed to update photo.');
                    }
                },
                error: function() {
                    EauNotifications.error('Failed to update photo. Please try again.');
                }
            });
        },

        /**
         * Remove foto de perfil
         */
        removePhoto: function() {
            const self = this;

            EauNotifications.confirm({
                title: 'Remove Photo',
                message: 'Are you sure you want to remove your profile photo?',
                type: 'warning',
                confirmText: 'Remove',
                cancelText: 'Cancel',
                onConfirm: function() {
                    self.updateProfilePhoto(0);
                }
            });
        },

        /**
         * Fecha todos os modais
         */
        closeAllModals: function() {
            $('.eau-modal-overlay').fadeOut(200);
        },

        /**
         * Escape HTML para prevenir XSS
         */
        escapeHtml: function(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        /**
         * Capitaliza primeira letra
         */
        capitalizeFirst: function(str) {
            if (!str) return '';
            return str.charAt(0).toUpperCase() + str.slice(1).toLowerCase();
        }
    };

    // Inicializa quando o documento estiver pronto
    $(document).ready(function() {
        // Verifica se estamos na página correta
        if ($('#eau-my-profile-container').length) {
            EauMyProfile.init();
        }
    });

})(jQuery);
