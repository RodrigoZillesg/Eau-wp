<?php
namespace EauSystem;

use EauSystem\Components\Eau_Access_Denied;

/**
 * System Settings Page
 *
 * Página de configurações do sistema via shortcode.
 * Acessível apenas para superAdmin e Admin.
 *
 * Shortcode: [eau_settings]
 *
 * @since 1.39.0
 */
class Eau_Settings {

    /**
     * Option names
     */
    const OPTION_ACTIVITY_APPROVAL = 'eau_activity_approval_mode';
    const OPTION_MEMBER_TAGS = 'eau_member_tags';

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

            <!-- Settings Sections -->
            <div class="eau-settings-sections">

                <!-- CPD Activities Section -->
                <div class="eau-settings-section" id="eau-settings-activities">
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

                <!-- Member Tags Section -->
                <div class="eau-settings-section" id="eau-settings-member-tags">
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

                <!-- Future Settings Placeholder -->
                <!-- More sections can be added here -->

            </div>

        </div>
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

        // Localiza script
        wp_localize_script('eau-settings', 'eauSettingsData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('eau_settings_nonce'),
        ));
    }
}
