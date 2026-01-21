<?php
namespace EauSystem;

use EauSystem\Components\Eau_Access_Denied;
use EauSystem\Components\Eau_Stats_Cards;
use EauSystem\Components\Eau_Data_Table;
use EauSystem\Components\Eau_Modal;

/**
 * System Settings Page
 *
 * Página de configurações do sistema via shortcode.
 * Acessível apenas para superAdmin e Admin.
 * Organizado em abas: General, Categories, Tags, Data Import, System.
 *
 * Shortcode: [eau_settings]
 *
 * @since 1.39.0
 * @since 1.60.0 Reorganizado em abas, integrado Categories Management
 */
class Eau_Settings {

    /**
     * Option names
     */
    const OPTION_ACTIVITY_APPROVAL = 'eau_activity_approval_mode';
    const OPTION_MEMBER_TAGS = 'eau_member_tags';
    const OPTION_EMAIL_MIGRATION_EXEMPT = 'eau_email_migration_exempt_emails';

    /**
     * Approval modes
     */
    const APPROVAL_AUTO = 'auto';
    const APPROVAL_MANUAL = 'manual';

    /**
     * Default tag colors
     */
    const DEFAULT_TAG_COLORS = array(
        '#3b82f6', // blue
        '#10b981', // green
        '#f59e0b', // amber
        '#ef4444', // red
        '#8b5cf6', // violet
        '#ec4899', // pink
        '#06b6d4', // cyan
        '#84cc16', // lime
    );

    /**
     * Registra o shortcode
     */
    public static function register_shortcode() {
        add_shortcode('eau_settings', array(__CLASS__, 'render_settings'));
    }

    /**
     * Renderiza a página de Settings
     *
     * @param array $atts Atributos do shortcode
     * @return string HTML da página
     */
    public static function render_settings($atts) {
        // Verifica se usuário está logado
        if (!is_user_logged_in()) {
            return Eau_Access_Denied::not_logged_in();
        }

        // Verifica permissão
        if (!self::can_access_settings()) {
            return Eau_Access_Denied::render(
                'Access Denied',
                'You do not have permission to access system settings.'
            );
        }

        // Carrega assets
        self::enqueue_assets();

        // Pega configurações atuais
        $approval_mode = self::get_activity_approval_mode();

        ob_start();
        ?>
        <div class="eau-settings-container">

            <!-- Page Header -->
            <div class="eau-page-header">
                <div class="eau-page-header-title">
                    <h2>
                        <i data-lucide="settings"></i>
                        System Settings
                    </h2>
                    <p class="eau-page-header-subtitle">Configure system-wide settings</p>
                </div>
            </div>

            <!-- Tabs Navigation -->
            <div class="eau-settings-tabs-nav">
                <button class="eau-settings-tab active" data-tab="general">
                    <i data-lucide="sliders-horizontal"></i>
                    <span>General</span>
                </button>
                <button class="eau-settings-tab" data-tab="cpd-categories">
                    <i data-lucide="folder"></i>
                    <span>CPD Categories</span>
                </button>
                <button class="eau-settings-tab" data-tab="event-categories">
                    <i data-lucide="calendar-range"></i>
                    <span>Event Categories</span>
                </button>
                <button class="eau-settings-tab" data-tab="tags">
                    <i data-lucide="tags"></i>
                    <span>Tags</span>
                </button>
                <button class="eau-settings-tab" data-tab="coupons">
                    <i data-lucide="ticket"></i>
                    <span>Coupons</span>
                </button>
                <button class="eau-settings-tab" data-tab="payment-gateway">
                    <i data-lucide="credit-card"></i>
                    <span>Payment Gateway</span>
                </button>
                <button class="eau-settings-tab" data-tab="import">
                    <i data-lucide="upload"></i>
                    <span>Data Import</span>
                </button>
                <button class="eau-settings-tab" data-tab="system">
                    <i data-lucide="file-text"></i>
                    <span>System</span>
                </button>
            </div>

            <!-- Tab Contents -->
            <div class="eau-settings-tabs-content">

                <!-- Tab: General -->
                <div class="eau-settings-tab-panel active" data-tab-content="general">
                    <div class="eau-settings-section">
                        <div class="eau-settings-section-header">
                            <div class="eau-settings-section-icon">
                                <i data-lucide="clipboard-check"></i>
                            </div>
                            <div class="eau-settings-section-title">
                                <h3>CPD Activities</h3>
                                <p>Configure how CPD activities are processed</p>
                            </div>
                        </div>

                        <div class="eau-settings-section-body">
                            <div class="eau-settings-field">
                                <label class="eau-settings-field-label">Activity Approval Mode</label>
                                <p class="eau-settings-field-description">
                                    Choose how new activities submitted by members are handled
                                </p>

                                <div class="eau-radio-group" id="eau-approval-mode">
                                    <label class="eau-radio-option <?php echo $approval_mode === self::APPROVAL_AUTO ? 'selected' : ''; ?>">
                                        <input
                                            type="radio"
                                            name="approval_mode"
                                            value="<?php echo esc_attr(self::APPROVAL_AUTO); ?>"
                                            <?php checked($approval_mode, self::APPROVAL_AUTO); ?>
                                        >
                                        <div class="eau-radio-content">
                                            <div class="eau-radio-indicator"></div>
                                            <div class="eau-radio-text">
                                                <span class="eau-radio-title">Automatic Approval</span>
                                                <span class="eau-radio-description">
                                                    Activities are verified immediately upon creation.
                                                    Points are counted right away.
                                                </span>
                                            </div>
                                        </div>
                                    </label>

                                    <label class="eau-radio-option <?php echo $approval_mode === self::APPROVAL_MANUAL ? 'selected' : ''; ?>">
                                        <input
                                            type="radio"
                                            name="approval_mode"
                                            value="<?php echo esc_attr(self::APPROVAL_MANUAL); ?>"
                                            <?php checked($approval_mode, self::APPROVAL_MANUAL); ?>
                                        >
                                        <div class="eau-radio-content">
                                            <div class="eau-radio-indicator"></div>
                                            <div class="eau-radio-text">
                                                <span class="eau-radio-title">Manual Approval</span>
                                                <span class="eau-radio-description">
                                                    Activities require admin review before being verified.
                                                    Points are counted only after approval.
                                                </span>
                                            </div>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="eau-settings-section-footer">
                            <button type="button" class="eau-btn eau-btn-primary" id="eau-save-settings-btn">
                                <i data-lucide="save"></i>
                                Save Settings
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Tab: CPD Categories -->
                <div class="eau-settings-tab-panel" data-tab-content="cpd-categories">
                    <?php echo self::render_categories_tab(); ?>
                </div>

                <!-- Tab: Event Categories -->
                <div class="eau-settings-tab-panel" data-tab-content="event-categories">
                    <?php echo self::render_event_categories_tab(); ?>
                </div>

                <!-- Tab: Tags -->
                <div class="eau-settings-tab-panel" data-tab-content="tags">
                    <div class="eau-settings-section">
                        <div class="eau-settings-section-header">
                            <div class="eau-settings-section-icon">
                                <i data-lucide="tags"></i>
                            </div>
                            <div class="eau-settings-section-title">
                                <h3>Member Tags</h3>
                                <p>Manage tags for member segmentation and Mailchimp integration</p>
                            </div>
                        </div>

                        <div class="eau-settings-section-body">
                            <p class="eau-settings-field-description">
                                Create and manage tags that can be assigned to members. These tags will be used for
                                email list segmentation in Mailchimp.
                            </p>

                            <!-- Tags List -->
                            <div class="eau-tags-manager">
                                <div class="eau-tags-list" id="eau-tags-list">
                                    <!-- Tags loaded via JS -->
                                    <div class="eau-tags-loading">
                                        <div class="eau-skeleton" style="height: 44px;"></div>
                                        <div class="eau-skeleton" style="height: 44px;"></div>
                                        <div class="eau-skeleton" style="height: 44px;"></div>
                                    </div>
                                </div>

                                <!-- Add New Tag Form -->
                                <div class="eau-add-tag-form">
                                    <div class="eau-add-tag-inputs">
                                        <div class="eau-add-tag-row">
                                            <input
                                                type="text"
                                                id="eau-new-tag-name"
                                                class="eau-form-input"
                                                placeholder="Tag name..."
                                                maxlength="50"
                                            >
                                            <input
                                                type="color"
                                                id="eau-new-tag-color"
                                                class="eau-color-picker"
                                                value="#3b82f6"
                                                title="Choose tag color"
                                            >
                                        </div>
                                        <input
                                            type="text"
                                            id="eau-new-tag-description"
                                            class="eau-form-input"
                                            placeholder="Description (optional) - helps identify the tag for email lists"
                                            maxlength="200"
                                        >
                                    </div>
                                    <button type="button" class="eau-btn eau-btn-primary" id="eau-add-tag-btn">
                                        <i data-lucide="plus"></i>
                                        Add Tag
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab: Coupons -->
                <div class="eau-settings-tab-panel" data-tab-content="coupons">
                    <?php echo self::render_coupons_tab(); ?>
                </div>

                <!-- Tab: Payment Gateway -->
                <div class="eau-settings-tab-panel" data-tab-content="payment-gateway">
                    <?php echo self::render_payment_gateway_tab(); ?>
                </div>

                <!-- Tab: Data Import -->
                <div class="eau-settings-tab-panel" data-tab-content="import">
                    <!-- Membership Import Section -->
                    <div class="eau-settings-section">
                        <div class="eau-settings-section-header">
                            <div class="eau-settings-section-icon">
                                <i data-lucide="users"></i>
                            </div>
                            <div class="eau-settings-section-title">
                                <h3>Membership Data Import</h3>
                                <p>Import membership data from legacy system CSV</p>
                            </div>
                        </div>

                        <div class="eau-settings-section-body">
                            <p class="eau-settings-field-description">
                                Import membership data from a CSV file exported from the legacy system.
                                This will update existing members with their membership type, status, start date,
                                and expiry date. New users will be created if their email is not found in the system.
                            </p>

                            <div class="eau-import-info-boxes" style="display: flex; gap: 15px; margin: 20px 0;">
                                <div class="eau-info-box" style="flex: 1; padding: 15px; background: #f0f6fc; border: 1px solid #0073aa; border-radius: 8px;">
                                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                                        <i data-lucide="user-check" style="width: 20px; height: 20px; color: #0073aa;"></i>
                                        <strong>Existing Members</strong>
                                    </div>
                                    <p style="margin: 0; font-size: 13px; color: #666;">
                                        Members matched by email will have their membership data updated.
                                    </p>
                                </div>
                                <div class="eau-info-box" style="flex: 1; padding: 15px; background: #fff8e5; border: 1px solid #dba617; border-radius: 8px;">
                                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                                        <i data-lucide="user-plus" style="width: 20px; height: 20px; color: #dba617;"></i>
                                        <strong>New Members</strong>
                                    </div>
                                    <p style="margin: 0; font-size: 13px; color: #666;">
                                        Emails not found will create new user accounts with membership data.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="eau-settings-section-footer">
                            <button type="button" class="eau-btn eau-btn-primary" id="eau-import-membership-btn">
                                <i data-lucide="upload"></i>
                                Import Membership Data
                            </button>
                        </div>
                    </div>

                    <!-- Institution Import Section -->
                    <div class="eau-settings-section">
                        <div class="eau-settings-section-header">
                            <div class="eau-settings-section-icon">
                                <i data-lucide="building-2"></i>
                            </div>
                            <div class="eau-settings-section-title">
                                <h3>Institution Data Import</h3>
                                <p>Import institution data from legacy system CSV</p>
                            </div>
                        </div>

                        <div class="eau-settings-section-body">
                            <p class="eau-settings-field-description">
                                Import institution data from the same CSV file used for membership import.
                                The system will extract unique institutions by Company ID, updating existing
                                institutions or creating new ones.
                            </p>

                            <div class="eau-import-info-boxes" style="display: flex; gap: 15px; margin: 20px 0;">
                                <div class="eau-info-box" style="flex: 1; padding: 15px; background: #f0f6fc; border: 1px solid #0073aa; border-radius: 8px;">
                                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                                        <i data-lucide="building" style="width: 20px; height: 20px; color: #0073aa;"></i>
                                        <strong>Existing Institutions</strong>
                                    </div>
                                    <p style="margin: 0; font-size: 13px; color: #666;">
                                        Institutions matched by Company ID will have their data updated.
                                    </p>
                                </div>
                                <div class="eau-info-box" style="flex: 1; padding: 15px; background: #fff8e5; border: 1px solid #dba617; border-radius: 8px;">
                                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                                        <i data-lucide="building-2" style="width: 20px; height: 20px; color: #dba617;"></i>
                                        <strong>New Institutions</strong>
                                    </div>
                                    <p style="margin: 0; font-size: 13px; color: #666;">
                                        Company IDs not found will create new institution records.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="eau-settings-section-footer">
                            <button type="button" class="eau-btn eau-btn-primary" id="eau-import-institution-btn">
                                <i data-lucide="upload"></i>
                                Import Institution Data
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Tab: System -->
                <div class="eau-settings-tab-panel" data-tab-content="system">
                    <!-- Email Migration Exemptions -->
                    <div class="eau-settings-section">
                        <div class="eau-settings-section-header">
                            <div class="eau-settings-section-icon">
                                <i data-lucide="mail-x"></i>
                            </div>
                            <div class="eau-settings-section-title">
                                <h3>Email Migration Exemptions</h3>
                                <p>Emails that should bypass the personal email update requirement</p>
                            </div>
                        </div>

                        <div class="eau-settings-section-body">
                            <div class="eau-form-field">
                                <label class="eau-form-label">Exempt Email Addresses</label>
                                <textarea
                                    class="eau-form-textarea"
                                    id="eau-email-migration-exempt"
                                    name="email_migration_exempt"
                                    rows="4"
                                    placeholder="email1@example.com, email2@example.com"
                                ><?php echo esc_textarea(self::get_email_migration_exempt_raw()); ?></textarea>
                                <p class="eau-form-hint">Enter email addresses separated by commas. These users will not be redirected to the email update page.</p>
                            </div>
                        </div>

                        <div class="eau-settings-section-footer">
                            <button type="button" class="eau-btn eau-btn-primary" id="eau-save-email-exempt-btn">
                                <i data-lucide="save"></i>
                                Save Exemptions
                            </button>
                        </div>
                    </div>

                    <!-- System Pages -->
                    <div class="eau-settings-section">
                        <div class="eau-settings-section-header">
                            <div class="eau-settings-section-icon">
                                <i data-lucide="file-text"></i>
                            </div>
                            <div class="eau-settings-section-title">
                                <h3>System Pages</h3>
                                <p>All pages automatically created by Eau System</p>
                            </div>
                        </div>

                        <div class="eau-settings-section-body">
                            <?php echo self::render_system_pages_list(); ?>
                        </div>

                        <div class="eau-settings-section-footer">
                            <button type="button" class="eau-btn eau-btn-secondary" id="eau-recreate-pages-btn">
                                <i data-lucide="refresh-cw"></i>
                                Recreate All Pages
                            </button>
                        </div>
                    </div>
                </div>

            </div>

        </div>

        <?php
        // Include Membership Import Modal (v1.55.0)
        include EAU_SYSTEM_PLUGIN_DIR . 'includes/admin-page-membership-import-modal.php';

        // Include Institution Import Modal (v1.54.0)
        include EAU_SYSTEM_PLUGIN_DIR . 'includes/admin-page-institution-import-modal.php';

        // CPD Categories Modals
        echo self::render_categories_modals();

        // Event Categories Modals
        echo self::render_event_categories_modals();

        // Coupons Modals
        echo self::render_coupons_modals();
        ?>
        <?php
        return ob_get_clean();
    }

    /**
     * Verifica se o usuário pode acessar as configurações
     *
     * @return bool
     */
    public static function can_access_settings() {
        if (!is_user_logged_in()) {
            return false;
        }

        $user_id = get_current_user_id();
        $mem_type = get_user_meta($user_id, 'mem_type', true);

        return in_array($mem_type, array('superAdmin', 'Admin'));
    }

    /**
     * Retorna o modo de aprovação de atividades
     *
     * @return string 'auto' ou 'manual'
     */
    public static function get_activity_approval_mode() {
        return get_option(self::OPTION_ACTIVITY_APPROVAL, self::APPROVAL_MANUAL);
    }

    /**
     * Verifica se aprovação é automática
     *
     * @return bool
     */
    public static function is_auto_approval() {
        return self::get_activity_approval_mode() === self::APPROVAL_AUTO;
    }

    /**
     * Retorna a lista de emails isentos da migração de email (como string raw)
     *
     * @since 1.66.8
     * @return string Lista de emails separados por vírgula
     */
    public static function get_email_migration_exempt_raw() {
        return get_option(self::OPTION_EMAIL_MIGRATION_EXEMPT, '');
    }

    /**
     * Retorna a lista de emails isentos da migração de email (como array)
     *
     * @since 1.66.8
     * @return array Array de emails em lowercase
     */
    public static function get_email_migration_exempt_list() {
        $raw = self::get_email_migration_exempt_raw();
        if (empty($raw)) {
            return array();
        }

        // Separa por vírgula, limpa espaços, converte para lowercase
        $emails = array_map(function($email) {
            return strtolower(trim($email));
        }, explode(',', $raw));

        // Remove entradas vazias
        return array_filter($emails, function($email) {
            return !empty($email) && is_email($email);
        });
    }

    /**
     * Verifica se um email está na lista de isentos
     *
     * @since 1.66.8
     * @param string $email Email a verificar
     * @return bool True se o email está na lista de isentos
     */
    public static function is_email_exempt_from_migration($email) {
        $exempt_list = self::get_email_migration_exempt_list();
        return in_array(strtolower(trim($email)), $exempt_list);
    }

    /**
     * Retorna todas as tags de membros
     *
     * @return array Array de tags
     */
    public static function get_member_tags() {
        $tags = get_option(self::OPTION_MEMBER_TAGS, array());
        return is_array($tags) ? $tags : array();
    }

    /**
     * Retorna uma tag pelo slug
     *
     * @param string $slug Slug da tag
     * @return array|null Tag encontrada ou null
     */
    public static function get_tag_by_slug($slug) {
        $tags = self::get_member_tags();
        foreach ($tags as $tag) {
            if (isset($tag['slug']) && $tag['slug'] === $slug) {
                return $tag;
            }
        }
        return null;
    }

    /**
     * Retorna uma tag pelo ID
     *
     * @param string $id ID da tag
     * @return array|null Tag encontrada ou null
     */
    public static function get_tag_by_id($id) {
        $tags = self::get_member_tags();
        foreach ($tags as $tag) {
            if (isset($tag['id']) && $tag['id'] === $id) {
                return $tag;
            }
        }
        return null;
    }

    /**
     * Adiciona uma nova tag
     *
     * @param string $name Nome da tag
     * @param string $color Cor da tag (hex)
     * @param string $description Descrição da tag
     * @return array|WP_Error Tag criada ou erro
     */
    public static function add_member_tag($name, $color = null, $description = '') {
        $name = trim($name);
        if (empty($name)) {
            return new \WP_Error('invalid_name', 'Tag name is required.');
        }

        // Gera slug único
        $slug = sanitize_title($name);
        $original_slug = $slug;
        $counter = 1;

        // Garante slug único
        while (self::get_tag_by_slug($slug) !== null) {
            $slug = $original_slug . '-' . $counter;
            $counter++;
        }

        // Cor padrão se não fornecida
        if (empty($color)) {
            $tags = self::get_member_tags();
            $color_index = count($tags) % count(self::DEFAULT_TAG_COLORS);
            $color = self::DEFAULT_TAG_COLORS[$color_index];
        }

        // Cria tag
        $tag = array(
            'id' => 'tag_' . uniqid(),
            'slug' => $slug,
            'name' => $name,
            'description' => trim($description),
            'color' => $color,
            'mailchimp_tag_id' => null,
            'created_at' => date('Y-m-d H:i:s'),
        );

        // Adiciona à lista
        $tags = self::get_member_tags();
        $tags[] = $tag;
        update_option(self::OPTION_MEMBER_TAGS, $tags);

        return $tag;
    }

    /**
     * Atualiza uma tag existente
     *
     * @param string $id ID da tag
     * @param string $name Novo nome
     * @param string $color Nova cor
     * @param string $description Nova descrição
     * @return array|WP_Error Tag atualizada ou erro
     */
    public static function update_member_tag($id, $name, $color, $description = null) {
        $tags = self::get_member_tags();
        $found = false;

        foreach ($tags as &$tag) {
            if ($tag['id'] === $id) {
                $tag['name'] = trim($name);
                $tag['color'] = $color;
                if ($description !== null) {
                    $tag['description'] = trim($description);
                }
                $found = true;
                $updated_tag = $tag;
                break;
            }
        }

        if (!$found) {
            return new \WP_Error('not_found', 'Tag not found.');
        }

        update_option(self::OPTION_MEMBER_TAGS, $tags);
        return $updated_tag;
    }

    /**
     * Remove uma tag
     *
     * @param string $id ID da tag
     * @return bool|WP_Error True se removida ou erro
     */
    public static function delete_member_tag($id) {
        $tags = self::get_member_tags();
        $tag_to_delete = null;
        $new_tags = array();

        foreach ($tags as $tag) {
            if ($tag['id'] === $id) {
                $tag_to_delete = $tag;
            } else {
                $new_tags[] = $tag;
            }
        }

        if (!$tag_to_delete) {
            return new \WP_Error('not_found', 'Tag not found.');
        }

        // Remove a tag de todos os usuários que a possuem
        $slug = $tag_to_delete['slug'];
        $users_with_tag = get_users(array(
            'meta_key' => 'mem_tags',
            'meta_compare' => 'EXISTS',
        ));

        foreach ($users_with_tag as $user) {
            $user_tags = get_user_meta($user->ID, 'mem_tags', true);
            if (is_array($user_tags) && in_array($slug, $user_tags)) {
                $user_tags = array_values(array_diff($user_tags, array($slug)));
                update_user_meta($user->ID, 'mem_tags', $user_tags);
            }
        }

        update_option(self::OPTION_MEMBER_TAGS, $new_tags);
        return true;
    }

    /**
     * Carrega assets (CSS e JS)
     *
     * @since 1.39.0
     * @since 1.60.0 Adicionado nonce de Categories
     */
    private static function enqueue_assets() {
        // CSS dos componentes
        wp_enqueue_style(
            'eau-components',
            EAU_SYSTEM_PLUGIN_URL . 'assets/css/eau-components.css',
            array(),
            EAU_SYSTEM_VERSION
        );

        // CSS específico da página
        wp_enqueue_style(
            'eau-settings',
            EAU_SYSTEM_PLUGIN_URL . 'assets/css/eau-settings.css',
            array('eau-components'),
            EAU_SYSTEM_VERSION
        );

        // JS - Notifications library
        wp_enqueue_script(
            'eau-notifications',
            EAU_SYSTEM_PLUGIN_URL . 'assets/js/eau-notifications.js',
            array('jquery', 'lucide-icons'),
            EAU_SYSTEM_VERSION,
            true
        );

        // JS - Settings
        wp_enqueue_script(
            'eau-settings',
            EAU_SYSTEM_PLUGIN_URL . 'assets/js/eau-settings.js',
            array('jquery', 'eau-notifications', 'lucide-icons'),
            EAU_SYSTEM_VERSION,
            true
        );

        // Localiza script com dados de Settings, CPD Categories, Event Categories e Coupons
        wp_localize_script('eau-settings', 'eauSettingsData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('eau_settings_nonce'),
            'categoriesNonce' => wp_create_nonce('eau_categories_nonce'),
            'eventCategoriesNonce' => wp_create_nonce('eau_event_categories_nonce'),
            'couponsNonce' => wp_create_nonce('eau_coupons_nonce'),
        ));
    }

    /**
     * Renderiza a lista de páginas do sistema
     *
     * @since 1.57.0
     * @return string HTML da lista
     */
    private static function render_system_pages_list() {
        $pages_status = Eau_Pages::get_pages_status();
        $summary = Eau_Pages::get_pages_summary();
        $descriptions = self::get_page_descriptions();

        ob_start();
        ?>
        <div class="eau-system-pages-wrapper">
            <!-- Summary -->
            <div class="eau-pages-summary">
                <div class="eau-pages-summary-item">
                    <span class="eau-pages-summary-number"><?php echo $summary['total']; ?></span>
                    <span class="eau-pages-summary-label">Total</span>
                </div>
                <div class="eau-pages-summary-item eau-pages-summary-success">
                    <span class="eau-pages-summary-number"><?php echo $summary['existing']; ?></span>
                    <span class="eau-pages-summary-label">Active</span>
                </div>
                <?php if ($summary['missing'] > 0) : ?>
                <div class="eau-pages-summary-item eau-pages-summary-warning">
                    <span class="eau-pages-summary-number"><?php echo $summary['missing']; ?></span>
                    <span class="eau-pages-summary-label">Missing</span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Pages Table -->
            <table class="eau-pages-table">
                <thead>
                    <tr>
                        <th>Page</th>
                        <th>URL</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pages_status as $key => $page) :
                        $description = isset($descriptions[$key]) ? $descriptions[$key] : '';
                        $url_path = $page['parent'] ? '/dashboard/' . $page['slug'] . '/' : '/' . $page['slug'] . '/';
                    ?>
                    <tr class="<?php echo $page['exists'] ? '' : 'eau-row-missing'; ?>">
                        <td>
                            <strong><?php echo esc_html($page['title']); ?></strong>
                            <span class="eau-page-desc"><?php echo esc_html($description); ?></span>
                        </td>
                        <td>
                            <?php if ($page['exists'] && $page['url']) : ?>
                            <a href="<?php echo esc_url($page['url']); ?>" target="_blank" class="eau-page-url">
                                <?php echo esc_html($url_path); ?>
                                <i data-lucide="external-link"></i>
                            </a>
                            <?php else : ?>
                            <span class="eau-page-url-missing"><?php echo esc_html($url_path); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="eau-status-badge <?php echo $page['exists'] ? 'eau-status-active' : 'eau-status-missing'; ?>">
                                <?php echo $page['exists'] ? 'Active' : 'Missing'; ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Retorna descrições para cada página do sistema
     *
     * @since 1.57.0
     * @return array
     */
    private static function get_page_descriptions() {
        return array(
            // Top level pages
            'dashboard'              => 'Main administration dashboard with statistics and quick access',
            'profile'                => 'User profile management and personal information',
            'register'               => 'Public registration form for new members',
            'membership-selection'   => 'Membership type selection during registration',
            'events'                 => 'Public events listing and registration',
            'roadmap'                => 'System presentation and feature documentation',

            // Dashboard children
            'manage-members'         => 'Member management: view, edit, create and delete members',
            'manage-institutions'    => 'Institution management: view, edit, create and delete institutions',
            'manage-activities'      => 'CPD activities management: view, approve and manage activities',
            'manage-categories'      => 'Activity categories management: organize and configure CPD categories',
            'my-cpds'                => 'Personal CPD activities tracking and submission',
            'my-payments'            => 'Personal payment history and invoices',
            'my-institution'         => 'Institution details and member management for institution admins',
            'courses'                => 'OpenLearning courses listing and enrollment',
            'settings'               => 'System-wide settings and configuration',
            'merge-members'          => 'Duplicate member detection and merge tool',
            'open-learning-management' => 'OpenLearning integration management and sync',
            'payments'               => 'Payment management: view and process all payments',
            'events-management'      => 'Events management: create, edit and manage events',
            'membership-applications' => 'Membership applications: review and approve new applications',
        );
    }

    /**
     * Renderiza a aba de Categories
     *
     * @since 1.60.0
     * @return string HTML da aba
     */
    private static function render_categories_tab() {
        $stats = self::get_categories_stats();

        ob_start();
        ?>
        <div class="eau-categories-tab-container">
            <!-- Stats Cards -->
            <?php echo self::render_categories_stats_cards($stats); ?>

            <!-- Header with Actions -->
            <div class="eau-categories-header">
                <div class="eau-search-wrapper">
                    <i data-lucide="search"></i>
                    <input
                        type="text"
                        class="eau-search-input"
                        placeholder="Search by category name or ID..."
                        id="eau-categories-search"
                    >
                </div>
                <div class="eau-categories-actions">
                    <button class="eau-btn eau-btn-ghost" id="eau-import-categories" title="Import Categories">
                        <i data-lucide="upload"></i>
                        Import
                    </button>
                    <button class="eau-btn eau-btn-ghost" id="eau-export-categories" title="Export Categories">
                        <i data-lucide="download"></i>
                        Export
                    </button>
                    <button class="eau-btn eau-btn-secondary" id="eau-refresh-categories">
                        <i data-lucide="refresh-cw"></i>
                        Refresh from Activities
                    </button>
                    <button class="eau-btn eau-btn-primary" id="eau-add-category">
                        <i data-lucide="plus"></i>
                        Add Category
                    </button>
                </div>
            </div>

            <!-- Data Table -->
            <?php echo self::render_categories_table(); ?>

            <!-- Pagination -->
            <div id="eau-categories-pagination"></div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Pega estatísticas das categorias
     *
     * @since 1.60.0
     * @return array Estatísticas
     */
    private static function get_categories_stats() {
        global $wpdb;

        $table_name = $wpdb->prefix . Eau_Categories_Database::TABLE_NAME;

        // Total de categorias
        $total = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");

        // Categorias com pontos configurados
        $configured = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE points_per_hour > 0");

        // Categorias sem pontos
        $not_configured = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE points_per_hour = 0");

        // Média de pontos por hora
        $avg_points = $wpdb->get_var("SELECT AVG(points_per_hour) FROM $table_name WHERE points_per_hour > 0");

        return array(
            'total' => (int) $total,
            'configured' => (int) $configured,
            'not_configured' => (int) $not_configured,
            'avg_points' => (float) $avg_points,
        );
    }

    /**
     * Renderiza os cards de estatísticas de categorias
     *
     * @since 1.60.0
     * @param array $stats Estatísticas das categorias
     * @return string HTML dos cards
     */
    private static function render_categories_stats_cards($stats) {
        $cards_data = array(
            array(
                'title' => 'Total Categories',
                'number' => $stats['total'],
                'icon' => 'folder',
                'color' => 'blue',
            ),
            array(
                'title' => 'Configured',
                'number' => $stats['configured'],
                'icon' => 'check-circle',
                'color' => 'green',
            ),
            array(
                'title' => 'Not Configured',
                'number' => $stats['not_configured'],
                'icon' => 'alert-circle',
                'color' => 'orange',
            ),
            array(
                'title' => 'Avg Points/Hour',
                'number' => $stats['avg_points'] ? number_format_i18n($stats['avg_points'], 2) : '0.00',
                'icon' => 'trending-up',
                'color' => 'purple',
            ),
        );

        $stats_cards = new Eau_Stats_Cards($cards_data);
        return $stats_cards->render();
    }

    /**
     * Renderiza a tabela de categorias
     *
     * @since 1.60.0
     * @return string HTML da tabela
     */
    private static function render_categories_table() {
        $columns = array(
            array(
                'key' => 'category_serial',
                'label' => 'Category ID',
                'sortable' => true,
            ),
            array(
                'key' => 'category_name',
                'label' => 'Category Name',
                'sortable' => true,
            ),
            array(
                'key' => 'points_per_hour',
                'label' => 'Points/Hour',
                'sortable' => true,
            ),
            array(
                'key' => 'updated_at',
                'label' => 'Last Updated',
                'sortable' => true,
            ),
        );

        $table = new Eau_Data_Table(array(
            'id' => 'categories-table',
            'columns' => $columns,
            'loading_rows' => 20,
            'selectable' => false,
        ));

        return $table->render();
    }

    /**
     * Renderiza os modais de categorias
     *
     * @since 1.60.0
     * @return string HTML dos modais
     */
    private static function render_categories_modals() {
        $html = '';

        // Modal View
        $view_modal = new Eau_Modal(array(
            'id' => 'eau-modal-view',
            'title' => 'View Category',
            'size' => 'fullheight',
            'show_footer' => true,
            'footer_buttons' => array(
                array(
                    'label' => 'Close',
                    'class' => 'eau-btn-secondary',
                    'action' => 'close',
                ),
            ),
        ));
        $html .= $view_modal->render();

        // Modal Edit
        $edit_modal = new Eau_Modal(array(
            'id' => 'eau-modal-edit',
            'title' => 'Edit Category',
            'size' => 'fullheight',
            'show_footer' => true,
            'footer_buttons' => array(
                array(
                    'label' => 'Cancel',
                    'class' => 'eau-btn-secondary',
                    'action' => 'close',
                ),
                array(
                    'label' => 'Save Changes',
                    'class' => 'eau-btn-primary',
                    'action' => 'save',
                ),
            ),
        ));
        $html .= $edit_modal->render();

        // Modal Add
        $add_modal = new Eau_Modal(array(
            'id' => 'eau-modal-add',
            'title' => 'Add Category',
            'size' => 'fullheight',
            'show_footer' => true,
            'footer_buttons' => array(
                array(
                    'label' => 'Cancel',
                    'class' => 'eau-btn-secondary',
                    'action' => 'close',
                ),
                array(
                    'label' => 'Create Category',
                    'class' => 'eau-btn-primary',
                    'action' => 'create',
                ),
            ),
        ));
        $html .= $add_modal->render();

        // Modal Import
        $html .= self::render_categories_import_modal();

        return $html;
    }

    /**
     * Renderiza o modal de importação de categorias
     *
     * @since 1.60.0
     * @return string HTML do modal
     */
    private static function render_categories_import_modal() {
        ob_start();
        ?>
        <div class="eau-modal-overlay" id="eau-modal-import-overlay" style="display: none;">
            <div class="eau-modal eau-modal-lg">
                <div class="eau-modal-header">
                    <h2 class="eau-modal-title">
                        <i data-lucide="upload"></i>
                        Import Categories
                    </h2>
                    <button type="button" class="eau-modal-close" data-modal-close>&times;</button>
                </div>
                <div class="eau-modal-body">
                    <!-- Step 1: Upload -->
                    <div id="import-step-upload" class="import-step">
                        <p class="eau-description">
                            Upload a JSON file previously exported from this system.
                            Categories will be matched by Category ID (serial).
                        </p>

                        <div class="eau-info-box" style="background: #f0f9ff; border: 1px solid #0ea5e9; border-radius: 8px; padding: 12px; margin-bottom: 20px;">
                            <div style="display: flex; align-items: flex-start; gap: 10px;">
                                <i data-lucide="info" style="width: 20px; height: 20px; color: #0ea5e9; flex-shrink: 0;"></i>
                                <div>
                                    <strong>How it works:</strong>
                                    <ul style="margin: 8px 0 0 0; padding-left: 20px; font-size: 13px; color: #666;">
                                        <li>Existing categories (same ID) will be <strong>updated</strong></li>
                                        <li>New categories will be <strong>created</strong></li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <form id="import-categories-form" enctype="multipart/form-data">
                            <div class="eau-form-group">
                                <label class="eau-form-label">JSON File</label>
                                <input type="file" name="json_file" id="import-json-file" accept=".json" required class="eau-form-input">
                                <p class="eau-form-hint">Maximum file size: 5MB</p>
                            </div>

                            <div class="eau-form-group">
                                <label class="eau-checkbox-label">
                                    <input type="checkbox" name="skip_existing" id="import-skip-existing">
                                    Skip existing categories (only add new ones)
                                </label>
                            </div>
                        </form>
                    </div>

                    <!-- Step 2: Preview -->
                    <div id="import-step-preview" class="import-step" style="display: none;">
                        <div id="import-preview-stats" class="eau-preview-stats" style="display: flex; gap: 15px; margin-bottom: 20px;">
                            <!-- Stats loaded via JS -->
                        </div>

                        <h4>Preview (first 10 categories):</h4>
                        <div class="eau-table-wrapper" style="max-height: 300px; overflow-y: auto; border: 1px solid #e5e7eb; border-radius: 8px;">
                            <table class="eau-data-table" id="import-preview-table">
                                <thead>
                                    <tr>
                                        <th>Category ID</th>
                                        <th>Category Name</th>
                                        <th>Points/Hour</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Preview rows loaded via JS -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Step 3: Result -->
                    <div id="import-step-result" class="import-step" style="display: none;">
                        <div id="import-result-content">
                            <!-- Result loaded via JS -->
                        </div>
                    </div>
                </div>
                <div class="eau-modal-footer">
                    <button type="button" class="eau-btn eau-btn-secondary" id="import-btn-cancel">Cancel</button>
                    <button type="button" class="eau-btn eau-btn-secondary" id="import-btn-back" style="display: none;">Back</button>
                    <button type="button" class="eau-btn eau-btn-primary" id="import-btn-analyze">
                        <i data-lucide="search"></i>
                        Analyze File
                    </button>
                    <button type="button" class="eau-btn eau-btn-primary" id="import-btn-execute" style="display: none;">
                        <i data-lucide="upload"></i>
                        Import Categories
                    </button>
                    <button type="button" class="eau-btn eau-btn-primary" id="import-btn-close" style="display: none;">
                        <i data-lucide="check"></i>
                        Close
                    </button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    // ==========================================================================
    // EVENT CATEGORIES TAB (v1.61.0)
    // ==========================================================================

    /**
     * Renderiza a aba de Event Categories
     *
     * @since 1.61.0
     * @return string HTML da aba
     */
    private static function render_event_categories_tab() {
        $stats = self::get_event_categories_stats();

        ob_start();
        ?>
        <div class="eau-categories-tab-container" id="event-categories-container">
            <!-- Stats Cards -->
            <?php echo self::render_event_categories_stats_cards($stats); ?>

            <!-- Header with Actions -->
            <div class="eau-categories-header">
                <div class="eau-search-wrapper">
                    <i data-lucide="search"></i>
                    <input
                        type="text"
                        class="eau-search-input"
                        placeholder="Search by category name or ID..."
                        id="eau-event-categories-search"
                    >
                </div>
                <div class="eau-categories-actions">
                    <button class="eau-btn eau-btn-secondary" id="eau-refresh-event-categories">
                        <i data-lucide="refresh-cw"></i>
                        Refresh from Events
                    </button>
                    <button class="eau-btn eau-btn-primary" id="eau-add-event-category">
                        <i data-lucide="plus"></i>
                        Add Category
                    </button>
                </div>
            </div>

            <!-- Data Table -->
            <?php echo self::render_event_categories_table(); ?>

            <!-- Pagination -->
            <div id="eau-event-categories-pagination"></div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Pega estatísticas das categorias de eventos
     *
     * @since 1.61.0
     * @return array Estatísticas
     */
    private static function get_event_categories_stats() {
        global $wpdb;

        $table_name = $wpdb->prefix . Eau_Event_Categories_Database::TABLE_NAME;

        // Verifica se a tabela existe
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        if (!$table_exists) {
            return array('total' => 0);
        }

        // Total de categorias
        $total = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");

        return array(
            'total' => (int) $total,
        );
    }

    /**
     * Renderiza os cards de estatísticas de categorias de eventos
     *
     * @since 1.61.0
     * @param array $stats Estatísticas das categorias
     * @return string HTML dos cards
     */
    private static function render_event_categories_stats_cards($stats) {
        $cards_data = array(
            array(
                'title' => 'Total Event Categories',
                'number' => $stats['total'],
                'icon' => 'calendar-range',
                'color' => 'blue',
            ),
        );

        $stats_cards = new Eau_Stats_Cards($cards_data);
        return $stats_cards->render();
    }

    /**
     * Renderiza a tabela de categorias de eventos
     *
     * @since 1.61.0
     * @return string HTML da tabela
     */
    private static function render_event_categories_table() {
        $columns = array(
            array(
                'key' => 'category_serial',
                'label' => 'Category ID',
                'sortable' => true,
            ),
            array(
                'key' => 'category_name',
                'label' => 'Category Name',
                'sortable' => true,
            ),
            array(
                'key' => 'updated_at',
                'label' => 'Last Updated',
                'sortable' => true,
            ),
        );

        $table = new Eau_Data_Table(array(
            'id' => 'event-categories-table',
            'columns' => $columns,
            'loading_rows' => 20,
            'selectable' => false,
        ));

        return $table->render();
    }

    /**
     * Renderiza os modais de categorias de eventos
     *
     * @since 1.61.0
     * @return string HTML dos modais
     */
    private static function render_event_categories_modals() {
        $html = '';

        // Modal View
        $view_modal = new Eau_Modal(array(
            'id' => 'eau-event-modal-view',
            'title' => 'View Event Category',
            'size' => 'fullheight',
            'show_footer' => true,
            'footer_buttons' => array(
                array(
                    'label' => 'Close',
                    'class' => 'eau-btn-secondary',
                    'action' => 'close',
                ),
            ),
        ));
        $html .= $view_modal->render();

        // Modal Edit
        $edit_modal = new Eau_Modal(array(
            'id' => 'eau-event-modal-edit',
            'title' => 'Edit Event Category',
            'size' => 'fullheight',
            'show_footer' => true,
            'footer_buttons' => array(
                array(
                    'label' => 'Cancel',
                    'class' => 'eau-btn-secondary',
                    'action' => 'close',
                ),
                array(
                    'label' => 'Save Changes',
                    'class' => 'eau-btn-primary',
                    'action' => 'save',
                ),
            ),
        ));
        $html .= $edit_modal->render();

        // Modal Add
        $add_modal = new Eau_Modal(array(
            'id' => 'eau-event-modal-add',
            'title' => 'Add Event Category',
            'size' => 'fullheight',
            'show_footer' => true,
            'footer_buttons' => array(
                array(
                    'label' => 'Cancel',
                    'class' => 'eau-btn-secondary',
                    'action' => 'close',
                ),
                array(
                    'label' => 'Create Category',
                    'class' => 'eau-btn-primary',
                    'action' => 'create',
                ),
            ),
        ));
        $html .= $add_modal->render();

        return $html;
    }

    // ==========================================================================
    // COUPONS TAB (v1.69.0)
    // ==========================================================================

    /**
     * Renderiza a aba de Coupons
     *
     * @since 1.69.0
     * @return string HTML da aba
     */
    private static function render_coupons_tab() {
        $stats = self::get_coupons_stats();

        ob_start();
        ?>
        <div class="eau-coupons-tab-container" id="coupons-container">
            <!-- Stats Cards -->
            <?php echo self::render_coupons_stats_cards($stats); ?>

            <!-- Header with Actions -->
            <div class="eau-categories-header">
                <div class="eau-search-wrapper">
                    <i data-lucide="search"></i>
                    <input
                        type="text"
                        class="eau-search-input"
                        placeholder="Search by code or description..."
                        id="eau-coupons-search"
                    >
                </div>
                <div class="eau-categories-actions">
                    <select class="eau-form-select eau-coupon-status-filter" id="eau-coupons-status-filter">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="expired">Expired</option>
                    </select>
                    <button class="eau-btn eau-btn-primary" id="eau-add-coupon">
                        <i data-lucide="plus"></i>
                        Add Coupon
                    </button>
                </div>
            </div>

            <!-- Data Table -->
            <?php echo self::render_coupons_table(); ?>

            <!-- Pagination -->
            <div id="eau-coupons-pagination"></div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Pega estatísticas dos cupons
     *
     * @since 1.69.0
     * @return array Estatísticas
     */
    private static function get_coupons_stats() {
        // Usa o model de cupons
        return \EauSystem\Coupons\Eau_Coupons_Model::get_stats();
    }

    /**
     * Renderiza os cards de estatísticas de cupons
     *
     * @since 1.69.0
     * @param array $stats Estatísticas dos cupons
     * @return string HTML dos cards
     */
    private static function render_coupons_stats_cards($stats) {
        $cards_data = array(
            array(
                'title' => 'Total Coupons',
                'number' => $stats['total'],
                'icon' => 'ticket',
                'color' => 'blue',
            ),
            array(
                'title' => 'Active',
                'number' => $stats['active'],
                'icon' => 'check-circle',
                'color' => 'green',
            ),
            array(
                'title' => 'Inactive',
                'number' => $stats['inactive'],
                'icon' => 'pause-circle',
                'color' => 'orange',
            ),
            array(
                'title' => 'Total Discounts',
                'number' => '$' . number_format($stats['total_discount'], 2),
                'icon' => 'dollar-sign',
                'color' => 'purple',
            ),
        );

        $stats_cards = new Eau_Stats_Cards($cards_data);
        return $stats_cards->render();
    }

    /**
     * Renderiza a tabela de cupons
     *
     * @since 1.69.0
     * @return string HTML da tabela
     */
    private static function render_coupons_table() {
        $columns = array(
            array(
                'key' => 'code',
                'label' => 'Code',
                'sortable' => true,
            ),
            array(
                'key' => 'formatted_discount',
                'label' => 'Discount',
                'sortable' => false,
            ),
            array(
                'key' => 'usage_count',
                'label' => 'Uses',
                'sortable' => true,
            ),
            array(
                'key' => 'validity_description',
                'label' => 'Validity',
                'sortable' => false,
            ),
            array(
                'key' => 'status',
                'label' => 'Status',
                'sortable' => true,
            ),
            array(
                'key' => 'created_at',
                'label' => 'Created',
                'sortable' => true,
            ),
        );

        $table = new Eau_Data_Table(array(
            'id' => 'coupons-table',
            'columns' => $columns,
            'loading_rows' => 10,
            'selectable' => false,
        ));

        return $table->render();
    }

    /**
     * Renderiza os modais de cupons
     *
     * @since 1.69.0
     * @return string HTML dos modais
     */
    private static function render_coupons_modals() {
        $html = '';

        // Modal View
        $view_modal = new Eau_Modal(array(
            'id' => 'eau-coupon-modal-view',
            'title' => 'Coupon Details',
            'size' => 'fullheight',
            'show_footer' => true,
            'footer_buttons' => array(
                array(
                    'label' => 'Close',
                    'class' => 'eau-btn-secondary',
                    'action' => 'close',
                ),
            ),
        ));
        $html .= $view_modal->render();

        // Modal Edit
        $edit_modal = new Eau_Modal(array(
            'id' => 'eau-coupon-modal-edit',
            'title' => 'Edit Coupon',
            'size' => 'fullheight',
            'show_footer' => true,
            'footer_buttons' => array(
                array(
                    'label' => 'Cancel',
                    'class' => 'eau-btn-secondary',
                    'action' => 'close',
                ),
                array(
                    'label' => 'Save Changes',
                    'class' => 'eau-btn-primary',
                    'action' => 'save',
                ),
            ),
        ));
        $html .= $edit_modal->render();

        // Modal Add
        $add_modal = new Eau_Modal(array(
            'id' => 'eau-coupon-modal-add',
            'title' => 'Create Coupon',
            'size' => 'fullheight',
            'show_footer' => true,
            'footer_buttons' => array(
                array(
                    'label' => 'Cancel',
                    'class' => 'eau-btn-secondary',
                    'action' => 'close',
                ),
                array(
                    'label' => 'Create Coupon',
                    'class' => 'eau-btn-primary',
                    'action' => 'create',
                ),
            ),
        ));
        $html .= $add_modal->render();

        // Modal Delete Confirmation
        $delete_modal = new Eau_Modal(array(
            'id' => 'eau-coupon-modal-delete',
            'title' => 'Delete Coupon',
            'size' => 'sm',
            'show_footer' => true,
            'footer_buttons' => array(
                array(
                    'label' => 'Cancel',
                    'class' => 'eau-btn-secondary',
                    'action' => 'close',
                ),
                array(
                    'label' => 'Delete',
                    'class' => 'eau-btn-danger',
                    'action' => 'delete',
                ),
            ),
        ));
        $html .= $delete_modal->render();

        return $html;
    }

    // ==========================================================================
    // PAYMENT GATEWAY TAB (v1.70.0)
    // ==========================================================================

    /**
     * Renderiza a aba de Payment Gateway
     *
     * @since 1.70.0
     * @return string HTML da aba
     */
    private static function render_payment_gateway_tab() {
        // Get current settings
        $settings = \EauSystem\FatZebra\FatZebra_Settings::get_for_display();

        ob_start();
        ?>
        <div class="eau-payment-gateway-container" id="payment-gateway-container">

            <!-- Gateway Status -->
            <div class="eau-settings-section">
                <div class="eau-settings-section-header">
                    <div class="eau-settings-section-icon">
                        <i data-lucide="activity"></i>
                    </div>
                    <div class="eau-settings-section-title">
                        <h3>Gateway Status</h3>
                        <p>Current Fat Zebra connection status</p>
                    </div>
                </div>

                <div class="eau-settings-section-body">
                    <div class="eau-gateway-status-cards">
                        <div class="eau-gateway-status-card">
                            <div class="eau-gateway-status-icon <?php echo $settings['is_configured'] ? 'configured' : 'not-configured'; ?>">
                                <i data-lucide="<?php echo $settings['is_configured'] ? 'check-circle' : 'alert-circle'; ?>"></i>
                            </div>
                            <div class="eau-gateway-status-info">
                                <span class="eau-gateway-status-label">Configuration</span>
                                <span class="eau-gateway-status-value"><?php echo $settings['is_configured'] ? 'Configured' : 'Not Configured'; ?></span>
                            </div>
                        </div>

                        <div class="eau-gateway-status-card">
                            <div class="eau-gateway-status-icon mode-<?php echo $settings['sandbox_mode'] ? 'sandbox' : 'production'; ?>">
                                <i data-lucide="<?php echo $settings['sandbox_mode'] ? 'flask-conical' : 'shield-check'; ?>"></i>
                            </div>
                            <div class="eau-gateway-status-info">
                                <span class="eau-gateway-status-label">Mode</span>
                                <span class="eau-gateway-status-value"><?php echo $settings['mode_label']; ?></span>
                            </div>
                        </div>

                        <div class="eau-gateway-status-card">
                            <div class="eau-gateway-status-icon">
                                <i data-lucide="link"></i>
                            </div>
                            <div class="eau-gateway-status-info">
                                <span class="eau-gateway-status-label">Connection</span>
                                <span class="eau-gateway-status-value" id="gateway-connection-status">Not Tested</span>
                            </div>
                        </div>
                    </div>

                    <div style="margin-top: 20px;">
                        <button type="button" class="eau-btn eau-btn-secondary" id="eau-test-gateway-connection">
                            <i data-lucide="plug"></i>
                            Test Connection
                        </button>
                    </div>
                </div>
            </div>

            <!-- Environment Settings -->
            <div class="eau-settings-section">
                <div class="eau-settings-section-header">
                    <div class="eau-settings-section-icon">
                        <i data-lucide="settings-2"></i>
                    </div>
                    <div class="eau-settings-section-title">
                        <h3>Environment</h3>
                        <p>Choose between Sandbox (testing) or Production mode</p>
                    </div>
                </div>

                <div class="eau-settings-section-body">
                    <div class="eau-settings-field">
                        <label class="eau-settings-field-label">Environment Mode</label>

                        <div class="eau-radio-group" id="eau-gateway-mode">
                            <label class="eau-radio-option <?php echo $settings['sandbox_mode'] ? 'selected' : ''; ?>">
                                <input
                                    type="radio"
                                    name="gateway_mode"
                                    value="sandbox"
                                    <?php checked($settings['sandbox_mode'], true); ?>
                                >
                                <div class="eau-radio-content">
                                    <div class="eau-radio-indicator"></div>
                                    <div class="eau-radio-text">
                                        <span class="eau-radio-title">
                                            <i data-lucide="flask-conical" style="width: 16px; height: 16px; vertical-align: middle;"></i>
                                            Sandbox Mode
                                        </span>
                                        <span class="eau-radio-description">
                                            Use test credentials. No real transactions will be processed.
                                            Use test cards: 4005 5500 0000 0001
                                        </span>
                                    </div>
                                </div>
                            </label>

                            <label class="eau-radio-option <?php echo !$settings['sandbox_mode'] ? 'selected' : ''; ?>">
                                <input
                                    type="radio"
                                    name="gateway_mode"
                                    value="production"
                                    <?php checked($settings['sandbox_mode'], false); ?>
                                >
                                <div class="eau-radio-content">
                                    <div class="eau-radio-indicator"></div>
                                    <div class="eau-radio-text">
                                        <span class="eau-radio-title">
                                            <i data-lucide="shield-check" style="width: 16px; height: 16px; vertical-align: middle;"></i>
                                            Production Mode
                                        </span>
                                        <span class="eau-radio-description">
                                            Use real credentials. Transactions will be processed for real.
                                            Only enable when ready for live payments.
                                        </span>
                                    </div>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sandbox Credentials -->
            <div class="eau-settings-section" id="sandbox-credentials-section">
                <div class="eau-settings-section-header">
                    <div class="eau-settings-section-icon">
                        <i data-lucide="flask-conical"></i>
                    </div>
                    <div class="eau-settings-section-title">
                        <h3>Sandbox Credentials</h3>
                        <p>Test environment credentials (default: TEST/TEST)</p>
                    </div>
                </div>

                <div class="eau-settings-section-body">
                    <div class="eau-form-row">
                        <div class="eau-form-group">
                            <label class="eau-form-label">Username</label>
                            <input
                                type="text"
                                class="eau-form-input"
                                id="sandbox-username"
                                name="sandbox_username"
                                value="<?php echo esc_attr($settings['sandbox_username']); ?>"
                                placeholder="TEST"
                            >
                        </div>
                        <div class="eau-form-group">
                            <label class="eau-form-label">Token</label>
                            <input
                                type="password"
                                class="eau-form-input"
                                id="sandbox-token"
                                name="sandbox_token"
                                value="<?php echo esc_attr($settings['sandbox_token']); ?>"
                                placeholder="Enter token..."
                            >
                        </div>
                    </div>
                </div>
            </div>

            <!-- Production Credentials -->
            <div class="eau-settings-section" id="production-credentials-section">
                <div class="eau-settings-section-header">
                    <div class="eau-settings-section-icon">
                        <i data-lucide="shield-check"></i>
                    </div>
                    <div class="eau-settings-section-title">
                        <h3>Production Credentials</h3>
                        <p>Live environment credentials from Fat Zebra</p>
                    </div>
                </div>

                <div class="eau-settings-section-body">
                    <div class="eau-form-row">
                        <div class="eau-form-group">
                            <label class="eau-form-label">Username</label>
                            <input
                                type="text"
                                class="eau-form-input"
                                id="production-username"
                                name="production_username"
                                value="<?php echo esc_attr($settings['production_username']); ?>"
                                placeholder="Your merchant username..."
                            >
                        </div>
                        <div class="eau-form-group">
                            <label class="eau-form-label">Token</label>
                            <input
                                type="password"
                                class="eau-form-input"
                                id="production-token"
                                name="production_token"
                                value="<?php echo esc_attr($settings['production_token']); ?>"
                                placeholder="Enter token..."
                            >
                        </div>
                    </div>

                    <div class="eau-info-box" style="background: #fef3cd; border: 1px solid #ffc107; border-radius: 8px; padding: 12px; margin-top: 15px;">
                        <div style="display: flex; align-items: flex-start; gap: 10px;">
                            <i data-lucide="alert-triangle" style="width: 20px; height: 20px; color: #856404; flex-shrink: 0;"></i>
                            <div style="font-size: 13px; color: #856404;">
                                <strong>Important:</strong> Production credentials should only be used when you're ready to accept real payments.
                                Test thoroughly in Sandbox mode first.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Webhook Configuration -->
            <div class="eau-settings-section">
                <div class="eau-settings-section-header">
                    <div class="eau-settings-section-icon">
                        <i data-lucide="webhook"></i>
                    </div>
                    <div class="eau-settings-section-title">
                        <h3>Webhook Configuration</h3>
                        <p>Configure webhook for payment notifications</p>
                    </div>
                </div>

                <div class="eau-settings-section-body">
                    <div class="eau-form-group">
                        <label class="eau-form-label">Webhook URL</label>
                        <div class="eau-input-with-copy">
                            <input
                                type="text"
                                class="eau-form-input"
                                id="webhook-url"
                                value="<?php echo esc_url($settings['webhook_url']); ?>"
                                readonly
                            >
                            <button type="button" class="eau-btn eau-btn-ghost" id="copy-webhook-url" title="Copy to clipboard">
                                <i data-lucide="copy"></i>
                            </button>
                        </div>
                        <p class="eau-form-hint">Add this URL in your Fat Zebra dashboard webhook configuration.</p>
                    </div>

                    <div class="eau-form-group" style="margin-top: 15px;">
                        <label class="eau-form-label">Webhook Secret</label>
                        <div class="eau-input-with-button">
                            <input
                                type="password"
                                class="eau-form-input"
                                id="webhook-secret"
                                name="webhook_secret"
                                value="<?php echo esc_attr($settings['webhook_secret']); ?>"
                                placeholder="Enter webhook secret..."
                            >
                            <button type="button" class="eau-btn eau-btn-ghost" id="generate-webhook-secret" title="Generate new secret">
                                <i data-lucide="refresh-cw"></i>
                            </button>
                        </div>
                        <p class="eau-form-hint">Used to verify webhook authenticity. Configure the same secret in Fat Zebra.</p>
                    </div>
                </div>
            </div>

            <!-- Test Cards Reference -->
            <div class="eau-settings-section">
                <div class="eau-settings-section-header">
                    <div class="eau-settings-section-icon">
                        <i data-lucide="credit-card"></i>
                    </div>
                    <div class="eau-settings-section-title">
                        <h3>Test Cards</h3>
                        <p>Use these cards in Sandbox mode for testing</p>
                    </div>
                </div>

                <div class="eau-settings-section-body">
                    <table class="eau-simple-table">
                        <thead>
                            <tr>
                                <th>Card Number</th>
                                <th>Result</th>
                                <th>CVV</th>
                                <th>Expiry</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>4005 5500 0000 0001</code></td>
                                <td><span class="eau-badge eau-badge-success">Success</span></td>
                                <td>Any 3 digits</td>
                                <td>Any future date</td>
                            </tr>
                            <tr>
                                <td><code>4557 0123 4567 8902</code></td>
                                <td><span class="eau-badge eau-badge-success">Success</span></td>
                                <td>Any 3 digits</td>
                                <td>Any future date</td>
                            </tr>
                            <tr>
                                <td><code>4000 0000 0000 0002</code></td>
                                <td><span class="eau-badge eau-badge-danger">Declined</span></td>
                                <td>Any 3 digits</td>
                                <td>Any future date</td>
                            </tr>
                            <tr>
                                <td><code>4000 0000 0000 0119</code></td>
                                <td><span class="eau-badge eau-badge-warning">CVV Error</span></td>
                                <td>Any 3 digits</td>
                                <td>Any future date</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Save Button -->
            <div class="eau-settings-footer" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
                <button type="button" class="eau-btn eau-btn-primary" id="eau-save-gateway-settings">
                    <i data-lucide="save"></i>
                    Save Payment Gateway Settings
                </button>
            </div>

        </div>
        <?php
        return ob_get_clean();
    }
}
