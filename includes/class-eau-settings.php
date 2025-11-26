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

    /**
     * Approval modes
     */
    const APPROVAL_AUTO = 'auto';
    const APPROVAL_MANUAL = 'manual';

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
