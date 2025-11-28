<?php
/**
 * Email Settings - Página de configurações de email
 *
 * @package EauSystem
 * @since   1.44.1
 */

namespace EauSystem\Email;

if (!defined('WPINC')) {
    die;
}

class Email_Settings {

    const OPTION_ENV = 'eau_email_environment';
    const OPTION_DEV_EMAILS = 'eau_email_dev_recipients';

    /**
     * Registra hooks
     */
    public static function register() {
        add_action('admin_menu', [__CLASS__, 'add_menu']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    /**
     * Adiciona menu no admin
     */
    public static function add_menu() {
        add_submenu_page(
            'eau-system',
            __('Email Settings', 'eau-system'),
            __('Email Settings', 'eau-system'),
            'manage_options',
            'eau-email-settings',
            [__CLASS__, 'render_page']
        );
    }

    /**
     * Registra settings
     */
    public static function register_settings() {
        register_setting('eau_email_settings', self::OPTION_ENV, [
            'type' => 'string',
            'default' => 'dev',
            'sanitize_callback' => 'sanitize_text_field',
        ]);

        register_setting('eau_email_settings', self::OPTION_DEV_EMAILS, [
            'type' => 'string',
            'default' => 'dev@platty.tech',
            'sanitize_callback' => 'sanitize_textarea_field',
        ]);
    }

    /**
     * Enqueue assets
     */
    public static function enqueue_assets($hook) {
        if ($hook !== 'eau-system_page_eau-email-settings') {
            return;
        }

        wp_enqueue_style(
            'eau-email-settings',
            EAU_SYSTEM_PLUGIN_URL . 'assets/css/eau-components.css',
            [],
            EAU_SYSTEM_VERSION
        );
    }

    /**
     * Verifica se está em modo dev
     */
    public static function is_dev_mode() {
        return get_option(self::OPTION_ENV, 'dev') === 'dev';
    }

    /**
     * Retorna emails de dev
     */
    public static function get_dev_emails() {
        $emails = get_option(self::OPTION_DEV_EMAILS, 'dev@platty.tech');
        return array_filter(array_map('trim', explode("\n", $emails)));
    }

    /**
     * Processa o email de destino baseado no ambiente
     *
     * @param string $original_email Email original do destinatário
     * @return string Email processado
     */
    public static function process_recipient($original_email) {
        if (self::is_dev_mode()) {
            $dev_emails = self::get_dev_emails();
            return !empty($dev_emails) ? $dev_emails[0] : 'dev@platty.tech';
        }
        return $original_email;
    }

    /**
     * Renderiza página
     */
    public static function render_page() {
        $environment = get_option(self::OPTION_ENV, 'dev');
        $dev_emails = get_option(self::OPTION_DEV_EMAILS, 'dev@platty.tech');
        $is_dev = $environment === 'dev';
        ?>
        <div class="wrap">
            <h1><?php _e('Email Settings', 'eau-system'); ?></h1>

            <form method="post" action="options.php">
                <?php settings_fields('eau_email_settings'); ?>

                <div class="eau-settings-card" style="max-width: 600px; background: #fff; padding: 24px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-top: 20px;">

                    <!-- Environment Switcher -->
                    <div class="eau-setting-row" style="margin-bottom: 30px;">
                        <h2 style="margin: 0 0 10px 0; font-size: 16px;"><?php _e('Environment', 'eau-system'); ?></h2>
                        <p style="color: #666; margin: 0 0 15px 0; font-size: 13px;">
                            <?php _e('In Dev mode, all emails are sent to the dev recipients below. In Production mode, emails are sent to actual users.', 'eau-system'); ?>
                        </p>

                        <div class="eau-switcher" style="display: flex; gap: 0; border-radius: 6px; overflow: hidden; border: 1px solid #ddd; width: fit-content;">
                            <label style="margin: 0;">
                                <input type="radio" name="<?php echo self::OPTION_ENV; ?>" value="dev" <?php checked($environment, 'dev'); ?> style="display: none;">
                                <span class="eau-switch-option" style="display: block; padding: 10px 24px; cursor: pointer; font-weight: 500; transition: all 0.2s; <?php echo $is_dev ? 'background: #f59e0b; color: #fff;' : 'background: #f5f5f5; color: #666;'; ?>">
                                    <?php _e('Dev', 'eau-system'); ?>
                                </span>
                            </label>
                            <label style="margin: 0;">
                                <input type="radio" name="<?php echo self::OPTION_ENV; ?>" value="prod" <?php checked($environment, 'prod'); ?> style="display: none;">
                                <span class="eau-switch-option" style="display: block; padding: 10px 24px; cursor: pointer; font-weight: 500; transition: all 0.2s; <?php echo !$is_dev ? 'background: #10b981; color: #fff;' : 'background: #f5f5f5; color: #666;'; ?>">
                                    <?php _e('Production', 'eau-system'); ?>
                                </span>
                            </label>
                        </div>

                        <!-- Status Badge -->
                        <div style="margin-top: 15px;">
                            <?php if ($is_dev): ?>
                                <span style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; background: #fef3c7; color: #92400e; border-radius: 20px; font-size: 13px; font-weight: 500;">
                                    <span style="width: 8px; height: 8px; background: #f59e0b; border-radius: 50%;"></span>
                                    <?php _e('Dev Mode - Emails redirected to dev recipients', 'eau-system'); ?>
                                </span>
                            <?php else: ?>
                                <span style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; background: #d1fae5; color: #065f46; border-radius: 20px; font-size: 13px; font-weight: 500;">
                                    <span style="width: 8px; height: 8px; background: #10b981; border-radius: 50%;"></span>
                                    <?php _e('Production Mode - Emails sent to actual users', 'eau-system'); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Dev Emails -->
                    <div class="eau-setting-row" style="margin-bottom: 20px;">
                        <h2 style="margin: 0 0 10px 0; font-size: 16px;"><?php _e('Dev Recipients', 'eau-system'); ?></h2>
                        <p style="color: #666; margin: 0 0 15px 0; font-size: 13px;">
                            <?php _e('Enter email addresses that will receive all emails in Dev mode. One email per line.', 'eau-system'); ?>
                        </p>

                        <textarea
                            name="<?php echo self::OPTION_DEV_EMAILS; ?>"
                            rows="4"
                            style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-family: monospace; font-size: 13px;"
                            placeholder="dev@platty.tech&#10;another@email.com"
                        ><?php echo esc_textarea($dev_emails); ?></textarea>
                    </div>

                    <!-- Submit -->
                    <div class="eau-setting-row">
                        <?php submit_button(__('Save Settings', 'eau-system'), 'primary', 'submit', false); ?>
                    </div>
                </div>
            </form>

            <!-- Test Email Section -->
            <div class="eau-settings-card" style="max-width: 600px; background: #fff; padding: 24px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-top: 20px;">
                <h2 style="margin: 0 0 10px 0; font-size: 16px;"><?php _e('Test Email', 'eau-system'); ?></h2>
                <p style="color: #666; margin: 0 0 15px 0; font-size: 13px;">
                    <?php _e('Send a test email to verify your configuration.', 'eau-system'); ?>
                </p>

                <form method="post" action="">
                    <?php wp_nonce_field('eau_test_email', 'eau_test_email_nonce'); ?>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <input
                            type="email"
                            name="test_email"
                            placeholder="test@example.com"
                            value="<?php echo esc_attr(wp_get_current_user()->user_email); ?>"
                            style="flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 6px;"
                        >
                        <button type="submit" name="send_test_email" class="button button-secondary">
                            <?php _e('Send Test', 'eau-system'); ?>
                        </button>
                    </div>
                </form>

                <?php self::handle_test_email(); ?>
            </div>
        </div>

        <script>
        document.querySelectorAll('input[name="<?php echo self::OPTION_ENV; ?>"]').forEach(function(radio) {
            radio.addEventListener('change', function() {
                location.reload();
            });
        });
        </script>
        <?php
    }

    /**
     * Processa envio de email de teste
     */
    private static function handle_test_email() {
        if (!isset($_POST['send_test_email'])) {
            return;
        }

        if (!wp_verify_nonce($_POST['eau_test_email_nonce'], 'eau_test_email')) {
            echo '<div class="notice notice-error" style="margin-top: 15px;"><p>' . __('Security check failed.', 'eau-system') . '</p></div>';
            return;
        }

        $test_email = sanitize_email($_POST['test_email']);
        if (empty($test_email)) {
            echo '<div class="notice notice-error" style="margin-top: 15px;"><p>' . __('Please enter a valid email.', 'eau-system') . '</p></div>';
            return;
        }

        $content = '
            <h1>Test Email</h1>
            <p>This is a test email from Eau System.</p>
            <p><strong>Environment:</strong> ' . (self::is_dev_mode() ? 'Dev' : 'Production') . '</p>
            <p><strong>Original recipient:</strong> ' . $test_email . '</p>
            <p><strong>Actual recipient:</strong> ' . self::process_recipient($test_email) . '</p>
            ' . Email_Template::info_box('Test Info', [
                'Sent at' => current_time('Y-m-d H:i:s'),
                'From' => Email_Config::get_from_email(),
            ]) . '
        ';

        $sent = Email_Service::send($test_email, 'Test Email - Eau System', $content);

        if ($sent) {
            $actual_recipient = self::process_recipient($test_email);
            echo '<div class="notice notice-success" style="margin-top: 15px;"><p>' .
                sprintf(__('Test email sent to %s', 'eau-system'), '<strong>' . $actual_recipient . '</strong>') .
                '</p></div>';
        } else {
            echo '<div class="notice notice-error" style="margin-top: 15px;"><p>' . __('Failed to send test email.', 'eau-system') . '</p></div>';
        }
    }
}
