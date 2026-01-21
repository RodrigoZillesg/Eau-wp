<?php
namespace EauSystem;

use EauSystem\Components\Eau_Access_Denied;
use EauSystem\Components\Eau_Media_Upload;
use EauSystem\Components\Eau_Modal;
use EauSystem\Components\Eau_Skeleton;

/**
 * My Profile Page
 *
 * Página para membros visualizarem e editarem seus próprios dados,
 * incluindo foto de perfil e alteração de senha.
 *
 * Shortcode: [eau_my_profile]
 *
 * @since 1.40.0
 */
class Eau_My_Profile {

    /**
     * Meta key para foto de perfil
     */
    const PROFILE_PHOTO_META_KEY = 'mem_profile_photo';

    /**
     * Registra o shortcode
     */
    public static function register_shortcode() {
        add_shortcode('eau_my_profile', array(__CLASS__, 'render_my_profile'));
    }

    /**
     * Renderiza a página My Profile
     *
     * @param array $atts Atributos do shortcode
     * @return string HTML da página
     */
    public static function render_my_profile($atts) {
        // Verifica se usuário está logado
        if (!is_user_logged_in()) {
            return Eau_Access_Denied::not_logged_in();
        }

        // Carrega assets
        self::enqueue_assets();

        // Pega dados do usuário
        $user_id = get_current_user_id();
        $user = get_userdata($user_id);

        ob_start();
        ?>
        <div class="eau-my-profile-container" id="eau-my-profile-container">

            <!-- Sidebar: Profile Header -->
            <div class="eau-profile-sidebar">
                <div class="eau-profile-header" id="eau-profile-header">
                    <?php echo Eau_Skeleton::card(); ?>
                </div>
            </div>

            <!-- Content: Sections -->
            <div class="eau-profile-content">

                <!-- Personal Information Section -->
                <div class="eau-profile-section" id="eau-profile-personal">
                    <div class="eau-profile-section-header">
                        <h3 class="eau-profile-section-title">
                            <i data-lucide="user"></i>
                            Personal Information
                        </h3>
                        <button type="button" class="eau-btn eau-btn-secondary eau-btn-sm eau-profile-edit-btn" data-section="personal">
                            <i data-lucide="pencil"></i>
                            Edit
                        </button>
                    </div>
                    <div class="eau-profile-section-body">
                        <?php echo Eau_Skeleton::text(4); ?>
                    </div>
                </div>

                <!-- Address Section -->
                <div class="eau-profile-section" id="eau-profile-address">
                    <div class="eau-profile-section-header">
                        <h3 class="eau-profile-section-title">
                            <i data-lucide="map-pin"></i>
                            Address
                        </h3>
                        <button type="button" class="eau-btn eau-btn-secondary eau-btn-sm eau-profile-edit-btn" data-section="address">
                            <i data-lucide="pencil"></i>
                            Edit
                        </button>
                    </div>
                    <div class="eau-profile-section-body">
                        <?php echo Eau_Skeleton::text(3); ?>
                    </div>
                </div>

                <!-- Account Information Section (Read-only) -->
                <div class="eau-profile-section" id="eau-profile-account">
                    <div class="eau-profile-section-header">
                        <h3 class="eau-profile-section-title">
                            <i data-lucide="shield"></i>
                            Account Information
                        </h3>
                    </div>
                    <div class="eau-profile-section-body">
                        <?php echo Eau_Skeleton::text(4); ?>
                    </div>
                </div>

                <!-- Membership Details Section -->
                <div class="eau-profile-section" id="eau-profile-membership">
                    <div class="eau-profile-section-header">
                        <h3 class="eau-profile-section-title">
                            <i data-lucide="id-card"></i>
                            Membership Details
                        </h3>
                        <?php
                        $membership_type = get_user_meta($user_id, 'mem_membership_type', true);
                        if (empty($membership_type)):
                        ?>
                        <a href="/membership-selection/" class="eau-btn eau-btn-primary eau-btn-sm">
                            <i data-lucide="plus"></i>
                            Apply for Membership
                        </a>
                        <?php endif; ?>
                    </div>
                    <div class="eau-profile-section-body">
                        <?php echo self::render_membership_section($user_id); ?>
                    </div>
                </div>

                <!-- Security Section -->
                <div class="eau-profile-section" id="eau-profile-security">
                    <div class="eau-profile-section-header">
                        <h3 class="eau-profile-section-title">
                            <i data-lucide="lock"></i>
                            Security
                        </h3>
                        <button type="button" class="eau-btn eau-btn-secondary eau-btn-sm" id="eau-change-password-btn">
                            <i data-lucide="key"></i>
                            Change Password
                        </button>
                    </div>
                    <div class="eau-profile-section-body">
                        <div class="eau-profile-grid">
                            <div class="eau-profile-field">
                                <span class="eau-profile-field-label">Password</span>
                                <span class="eau-profile-field-value">••••••••••••</span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Edit Personal Info Modal -->
            <?php echo self::render_edit_personal_modal(); ?>

            <!-- Edit Address Modal -->
            <?php echo self::render_edit_address_modal(); ?>

            <!-- Change Password Modal -->
            <?php echo self::render_change_password_modal(); ?>

            <!-- Upload Photo Modal -->
            <?php echo self::render_upload_photo_modal(); ?>

        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renderiza o modal de edição de dados pessoais
     *
     * @return string HTML do modal
     */
    private static function render_edit_personal_modal() {
        ob_start();
        ?>
        <div class="eau-modal-overlay" id="edit-personal-modal-overlay" style="display: none;">
            <div class="eau-modal eau-modal-medium" id="edit-personal-modal">
                <div class="eau-modal-header">
                    <h2 class="eau-modal-title">
                        <i data-lucide="user"></i>
                        Edit Personal Information
                    </h2>
                    <button class="eau-modal-close" type="button" data-modal-action="close">
                        <i data-lucide="x"></i>
                    </button>
                </div>
                <div class="eau-modal-body">
                    <form id="eau-edit-personal-form" class="eau-modal-form">
                        <div class="eau-form-grid">
                            <!-- First Name -->
                            <div class="eau-form-field">
                                <label class="eau-form-label" for="edit-first-name">First Name</label>
                                <input type="text" class="eau-form-input" id="edit-first-name" name="first_name">
                            </div>

                            <!-- Last Name -->
                            <div class="eau-form-field">
                                <label class="eau-form-label" for="edit-last-name">Last Name</label>
                                <input type="text" class="eau-form-input" id="edit-last-name" name="last_name">
                            </div>

                            <!-- Email -->
                            <div class="eau-form-field eau-form-field-span-2">
                                <label class="eau-form-label" for="edit-email">
                                    Email <span class="eau-form-required">*</span>
                                </label>
                                <input type="email" class="eau-form-input" id="edit-email" name="user_email" required>
                            </div>

                            <!-- Phone -->
                            <div class="eau-form-field eau-form-field-span-2">
                                <label class="eau-form-label" for="edit-phone">Phone</label>
                                <input type="tel" class="eau-form-input" id="edit-phone" name="mem_phone">
                            </div>

                            <!-- Bio -->
                            <div class="eau-form-field eau-form-field-span-2">
                                <label class="eau-form-label" for="edit-bio">Bio</label>
                                <textarea class="eau-form-textarea" id="edit-bio" name="description" rows="3"></textarea>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="eau-modal-footer">
                    <button type="button" class="eau-btn eau-btn-secondary" data-modal-action="close">Cancel</button>
                    <button type="button" class="eau-btn eau-btn-primary" id="eau-save-personal-btn">
                        <i data-lucide="check"></i>
                        Save Changes
                    </button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renderiza o modal de edição de endereço
     *
     * @return string HTML do modal
     */
    private static function render_edit_address_modal() {
        ob_start();
        ?>
        <div class="eau-modal-overlay" id="edit-address-modal-overlay" style="display: none;">
            <div class="eau-modal eau-modal-medium" id="edit-address-modal">
                <div class="eau-modal-header">
                    <h2 class="eau-modal-title">
                        <i data-lucide="map-pin"></i>
                        Edit Address
                    </h2>
                    <button class="eau-modal-close" type="button" data-modal-action="close">
                        <i data-lucide="x"></i>
                    </button>
                </div>
                <div class="eau-modal-body">
                    <form id="eau-edit-address-form" class="eau-modal-form">
                        <div class="eau-form-grid">
                            <!-- Address -->
                            <div class="eau-form-field eau-form-field-span-2">
                                <label class="eau-form-label" for="edit-address">Address</label>
                                <textarea class="eau-form-textarea" id="edit-address" name="mem_address" rows="2"></textarea>
                            </div>

                            <!-- City -->
                            <div class="eau-form-field">
                                <label class="eau-form-label" for="edit-city">City</label>
                                <input type="text" class="eau-form-input" id="edit-city" name="mem_city">
                            </div>

                            <!-- Postcode -->
                            <div class="eau-form-field">
                                <label class="eau-form-label" for="edit-postcode">Postcode</label>
                                <input type="text" class="eau-form-input" id="edit-postcode" name="mem_postcode">
                            </div>
                        </div>
                    </form>
                </div>
                <div class="eau-modal-footer">
                    <button type="button" class="eau-btn eau-btn-secondary" data-modal-action="close">Cancel</button>
                    <button type="button" class="eau-btn eau-btn-primary" id="eau-save-address-btn">
                        <i data-lucide="check"></i>
                        Save Changes
                    </button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renderiza o modal de alteração de senha
     *
     * @return string HTML do modal
     */
    private static function render_change_password_modal() {
        ob_start();
        ?>
        <div class="eau-modal-overlay" id="change-password-modal-overlay" style="display: none;">
            <div class="eau-modal eau-modal-small" id="change-password-modal">
                <div class="eau-modal-header">
                    <h2 class="eau-modal-title">
                        <i data-lucide="key"></i>
                        Change Password
                    </h2>
                    <button class="eau-modal-close" type="button" data-modal-action="close">
                        <i data-lucide="x"></i>
                    </button>
                </div>
                <div class="eau-modal-body">
                    <form id="eau-change-password-form" class="eau-modal-form">
                        <div class="eau-form-fields">
                            <!-- Current Password -->
                            <div class="eau-form-field">
                                <label class="eau-form-label" for="current-password">
                                    Current Password <span class="eau-form-required">*</span>
                                </label>
                                <div class="eau-password-input-wrapper">
                                    <input type="password" class="eau-form-input" id="current-password" name="current_password" required>
                                    <button type="button" class="eau-password-toggle" data-target="current-password">
                                        <i data-lucide="eye"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- New Password -->
                            <div class="eau-form-field">
                                <label class="eau-form-label" for="new-password">
                                    New Password <span class="eau-form-required">*</span>
                                </label>
                                <div class="eau-password-input-wrapper">
                                    <input type="password" class="eau-form-input" id="new-password" name="new_password" required minlength="8">
                                    <button type="button" class="eau-password-toggle" data-target="new-password">
                                        <i data-lucide="eye"></i>
                                    </button>
                                </div>
                                <span class="eau-form-hint">Minimum 8 characters</span>
                            </div>

                            <!-- Confirm Password -->
                            <div class="eau-form-field">
                                <label class="eau-form-label" for="confirm-password">
                                    Confirm New Password <span class="eau-form-required">*</span>
                                </label>
                                <div class="eau-password-input-wrapper">
                                    <input type="password" class="eau-form-input" id="confirm-password" name="confirm_password" required>
                                    <button type="button" class="eau-password-toggle" data-target="confirm-password">
                                        <i data-lucide="eye"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="eau-modal-footer">
                    <button type="button" class="eau-btn eau-btn-secondary" data-modal-action="close">Cancel</button>
                    <button type="button" class="eau-btn eau-btn-primary" id="eau-save-password-btn">
                        <i data-lucide="check"></i>
                        Update Password
                    </button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renderiza o modal de upload de foto de perfil
     *
     * @return string HTML do modal
     */
    private static function render_upload_photo_modal() {
        ob_start();
        ?>
        <div class="eau-modal-overlay" id="upload-photo-modal-overlay" style="display: none;">
            <div class="eau-modal eau-modal-medium" id="upload-photo-modal">
                <div class="eau-modal-header">
                    <h2 class="eau-modal-title">
                        <i data-lucide="camera"></i>
                        Update Profile Photo
                    </h2>
                    <button class="eau-modal-close" type="button" data-modal-action="close">
                        <i data-lucide="x"></i>
                    </button>
                </div>
                <div class="eau-modal-body">
                    <div class="eau-media-upload-wrapper" id="eau-profile-photo-wrapper"
                         data-type="media"
                         data-allowed-types="image/*"
                         data-allowed-extensions="jpg,jpeg,png,gif,webp"
                         data-max-file-size="5242880">

                        <!-- Painel de Upload -->
                        <div class="eau-media-upload-panel eau-media-upload-file-panel active">
                            <div class="eau-media-upload-dropzone" id="eau-profile-photo-dropzone">
                                <input
                                    type="file"
                                    class="eau-media-upload-file-input"
                                    id="eau-profile-photo-file-input"
                                    accept="image/*"
                                    style="display: none;">
                                <div class="eau-media-upload-dropzone-content">
                                    <i data-lucide="upload-cloud"></i>
                                    <span class="eau-media-upload-dropzone-text">
                                        Drag & drop or <button type="button" class="eau-media-upload-browse-btn">Browse</button>
                                    </span>
                                    <span class="eau-media-upload-dropzone-hint">
                                        Allowed: JPG, JPEG, PNG, GIF, WEBP<br>
                                        Max size: 5 MB
                                    </span>
                                </div>
                                <!-- Upload progress -->
                                <div class="eau-media-upload-progress" style="display: none;">
                                    <div class="eau-media-upload-progress-bar">
                                        <div class="eau-media-upload-progress-fill"></div>
                                    </div>
                                    <span class="eau-media-upload-progress-text">Uploading... 0%</span>
                                </div>
                            </div>
                        </div>

                        <!-- Preview da foto selecionada -->
                        <div class="eau-media-upload-preview" id="eau-profile-photo-preview" style="display: none;">
                            <div class="eau-media-upload-preview-content">
                                <div class="eau-media-upload-preview-thumbnail" id="eau-profile-photo-thumbnail">
                                    <img src="" alt="Preview" class="eau-media-upload-preview-image" style="display: none;">
                                </div>
                                <div class="eau-media-upload-preview-info">
                                    <span class="eau-media-upload-preview-name" id="eau-profile-photo-preview-name"></span>
                                </div>
                                <div class="eau-media-upload-preview-actions">
                                    <button type="button" class="eau-media-upload-remove" id="eau-profile-photo-remove" title="Remove file">
                                        <i data-lucide="x"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Campo hidden para o attachment ID -->
                        <input type="hidden" id="eau-profile-photo-value" name="profile_photo" value="">
                    </div>
                </div>
                <div class="eau-modal-footer">
                    <button type="button" class="eau-btn eau-btn-secondary" data-modal-action="close">Cancel</button>
                    <button type="button" class="eau-btn eau-btn-primary" id="eau-save-photo-btn" disabled>
                        <i data-lucide="check"></i>
                        Save Photo
                    </button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Enfileira assets da página
     */
    private static function enqueue_assets() {
        // Lucide Icons
        wp_enqueue_script(
            'lucide-icons',
            'https://unpkg.com/lucide@latest/dist/umd/lucide.min.js',
            array(),
            null,
            true
        );

        // intl-tel-input CSS
        wp_enqueue_style(
            'intl-tel-input',
            'https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/css/intlTelInput.css',
            array(),
            '18.2.1'
        );

        // CSS Base - eau-components
        wp_enqueue_style(
            'eau-components',
            EAU_SYSTEM_PLUGIN_URL . 'assets/css/eau-components.css',
            array(),
            EAU_SYSTEM_VERSION
        );

        // CSS específico da página
        wp_enqueue_style(
            'eau-my-profile',
            EAU_SYSTEM_PLUGIN_URL . 'assets/css/eau-my-profile.css',
            array('eau-components', 'intl-tel-input'),
            EAU_SYSTEM_VERSION
        );

        // intl-tel-input JS
        wp_enqueue_script(
            'intl-tel-input',
            'https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/intlTelInput.min.js',
            array(),
            '18.2.1',
            true
        );

        // JS - Notifications
        wp_enqueue_script(
            'eau-notifications',
            EAU_SYSTEM_PLUGIN_URL . 'assets/js/eau-notifications.js',
            array('jquery'),
            EAU_SYSTEM_VERSION,
            true
        );

        // JS - Media Upload Component
        wp_enqueue_script(
            'eau-media-upload',
            EAU_SYSTEM_PLUGIN_URL . 'assets/js/eau-media-upload.js',
            array('jquery'),
            EAU_SYSTEM_VERSION,
            true
        );

        // JS - My Profile
        wp_enqueue_script(
            'eau-my-profile',
            EAU_SYSTEM_PLUGIN_URL . 'assets/js/eau-my-profile.js',
            array('jquery', 'eau-notifications', 'lucide-icons', 'intl-tel-input', 'eau-media-upload'),
            EAU_SYSTEM_VERSION,
            true
        );

        // Localiza variáveis para o JS
        wp_localize_script('eau-my-profile', 'eau_my_profile_vars', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('eau_my_profile_nonce'),
        ));
    }

    /**
     * Pega URL da foto de perfil do usuário
     *
     * @param int $user_id ID do usuário
     * @param int $size Tamanho da imagem (default 150)
     * @return string URL da foto ou gravatar
     */
    public static function get_profile_photo_url($user_id, $size = 150) {
        $attachment_id = get_user_meta($user_id, self::PROFILE_PHOTO_META_KEY, true);

        if (!empty($attachment_id) && is_numeric($attachment_id)) {
            $image_url = wp_get_attachment_image_url($attachment_id, array($size, $size));
            if ($image_url) {
                return $image_url;
            }
        }

        // Fallback para Gravatar
        $user = get_userdata($user_id);
        if ($user) {
            return get_avatar_url($user->user_email, array('size' => $size));
        }

        return '';
    }

    /**
     * Pega o label do mem_type
     *
     * @since 1.72.6 - Suporte a múltiplos tipos (array)
     * @param string|array $mem_type Tipo do membro (pode ser string ou array)
     * @return string Label amigável (concatenado se múltiplos)
     */
    public static function get_mem_type_label($mem_type) {
        $labels = array(
            'superAdmin' => 'Super Admin',
            'Admin' => 'Admin',
            'institutionAdmin' => 'Institution Admin',
            'Member' => 'Member',
            'member' => 'Member',
            'non-member' => 'Non-Member',
        );

        // Se é array, retorna o tipo de maior autoridade
        if (is_array($mem_type)) {
            $highest = Eau_User_Institution_Helper::get_highest_permission_type();
            return isset($labels[$highest]) ? $labels[$highest] : $highest;
        }

        return isset($labels[$mem_type]) ? $labels[$mem_type] : (!empty($mem_type) ? $mem_type : 'Member');
    }

    /**
     * Retorna array com todos os labels de tipos do usuário
     *
     * @since 1.72.6
     * @since 1.72.7 - Inclui institutionAdmin mesmo quando vem de outras fontes (mem_managed_institutions, primary_contact)
     * @param int|null $user_id ID do usuário (null = atual)
     * @return array Array de labels
     */
    public static function get_all_mem_type_labels($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        $labels_map = array(
            'superAdmin' => 'Super Admin',
            'Admin' => 'Admin',
            'institutionAdmin' => 'Institution Admin',
            'Member' => 'Member',
            'member' => 'Member',
            'non-member' => 'Non-Member',
        );

        $types = Eau_User_Institution_Helper::get_user_types($user_id);

        // v1.72.7: Verifica se é institutionAdmin por outras vias (mem_managed_institutions, primary_contact)
        // e adiciona ao array se ainda não estiver
        if (Eau_User_Institution_Helper::is_institution_admin($user_id) && !in_array('institutionAdmin', $types)) {
            $types[] = 'institutionAdmin';
        }

        $labels = array();

        foreach ($types as $type) {
            $labels[] = isset($labels_map[$type]) ? $labels_map[$type] : $type;
        }

        // Remove duplicatas (ex: 'Member' e 'member')
        return array_unique($labels);
    }

    /**
     * Render membership section for My Profile page
     *
     * @since 1.49.6
     * @param int $user_id User ID
     * @return string HTML output
     */
    private static function render_membership_section($user_id) {
        $membership_type = get_user_meta($user_id, 'mem_membership_type', true);
        $membership_status = get_user_meta($user_id, 'mem_membership_status', true);
        $membership_start = get_user_meta($user_id, 'mem_membership_start_date', true);
        $membership_expiry = get_user_meta($user_id, 'mem_membership_expiry_date', true);
        $user_email = wp_get_current_user()->user_email;

        ob_start();
        ?>
        <div class="eau-profile-grid">
            <?php if (!empty($membership_type)): ?>
                <?php
                $type_data = Eau_Membership_Types::get_by_key($membership_type);
                $type_label = $type_data ? $type_data->type_label : ucwords(str_replace('_', ' ', $membership_type));

                // Status styling
                $status_labels = array(
                    'active' => 'Active',
                    'pending' => 'Pending Approval',
                    'expired' => 'Expired',
                    'suspended' => 'Suspended',
                );
                $status_classes = array(
                    'active' => 'eau-status-badge--approved',
                    'pending' => 'eau-status-badge--pending',
                    'expired' => 'eau-status-badge--rejected',
                    'suspended' => 'eau-status-badge--rejected',
                );
                $status_label = isset($status_labels[$membership_status]) ? $status_labels[$membership_status] : 'Unknown';
                $status_class = isset($status_classes[$membership_status]) ? $status_classes[$membership_status] : 'eau-status-badge--pending';
                ?>
                <div class="eau-profile-field">
                    <span class="eau-profile-field-label">Membership Type</span>
                    <span class="eau-profile-field-value"><?php echo esc_html($type_label); ?></span>
                </div>
                <div class="eau-profile-field">
                    <span class="eau-profile-field-label">Status</span>
                    <span class="eau-profile-field-value">
                        <span class="eau-status-badge <?php echo esc_attr($status_class); ?>">
                            <?php echo esc_html($status_label); ?>
                        </span>
                    </span>
                </div>
                <?php if (!empty($membership_start)): ?>
                <div class="eau-profile-field">
                    <span class="eau-profile-field-label">Member Since</span>
                    <span class="eau-profile-field-value"><?php echo esc_html(date_i18n('F j, Y', strtotime($membership_start))); ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($membership_expiry)): ?>
                <div class="eau-profile-field">
                    <span class="eau-profile-field-label">Expiry Date</span>
                    <span class="eau-profile-field-value">
                        <?php
                        $expiry_timestamp = strtotime($membership_expiry);
                        $days_until = ($expiry_timestamp - time()) / DAY_IN_SECONDS;
                        echo esc_html(date_i18n('F j, Y', $expiry_timestamp));
                        if ($days_until <= 30 && $days_until > 0) {
                            echo ' <span class="eau-status-badge eau-status-badge--pending">Expiring Soon</span>';
                        } elseif ($days_until <= 0) {
                            echo ' <span class="eau-status-badge eau-status-badge--rejected">Expired</span>';
                        }
                        ?>
                    </span>
                </div>
                <?php endif; ?>
            <?php else: ?>
                <?php
                // Check for pending application
                global $wpdb;
                $table_name = Eau_Membership_Database::get_table_name(Eau_Membership_Database::TABLE_APPLICATIONS);
                $pending_app = $wpdb->get_row($wpdb->prepare(
                    "SELECT membership_type, status, submitted_at FROM $table_name WHERE email = %s AND status IN ('pending', 'under_review') ORDER BY submitted_at DESC LIMIT 1",
                    $user_email
                ));

                if ($pending_app):
                    $type_data = Eau_Membership_Types::get_by_key($pending_app->membership_type);
                    $type_label = $type_data ? $type_data->type_label : 'Membership';
                    $status_label = ucwords(str_replace('_', ' ', $pending_app->status));
                ?>
                <div class="eau-profile-field eau-profile-field-full">
                    <div class="eau-alert eau-alert-info">
                        <i data-lucide="clock"></i>
                        <div class="eau-alert-content">
                            <strong>Application Pending</strong>
                            <p>Your application for <strong><?php echo esc_html($type_label); ?></strong> is currently <?php echo esc_html($status_label); ?>.</p>
                            <small>Submitted on <?php echo esc_html(date_i18n('F j, Y', strtotime($pending_app->submitted_at))); ?></small>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="eau-profile-field eau-profile-field-full">
                    <div class="eau-alert eau-alert-warning">
                        <i data-lucide="alert-circle"></i>
                        <div class="eau-alert-content">
                            <strong>No Active Membership</strong>
                            <p>You don't have an active membership yet. Apply now to access exclusive member benefits.</p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}
