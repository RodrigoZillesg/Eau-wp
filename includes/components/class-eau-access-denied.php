<?php
namespace EauSystem\Components;

/**
 * Access Denied Component
 *
 * Renderiza mensagem visual profissional de acesso negado
 * com suporte a diferentes cenários (não logado, sem permissão, etc)
 */
class Eau_Access_Denied {

    /**
     * Renderiza mensagem de acesso negado
     *
     * @param string $title Título da mensagem
     * @param string $message Texto descritivo
     * @param string $scenario Cenário: 'not_logged_in' ou 'no_permission'
     * @return string HTML da mensagem
     */
    public static function render($title, $message, $scenario = 'no_permission') {
        // Enfileira Lucide Icons
        wp_enqueue_script(
            'lucide-icons',
            'https://unpkg.com/lucide@latest/dist/umd/lucide.min.js',
            array(),
            null,
            true
        );

        // Define ícone e botão baseado no cenário
        $icon = ($scenario === 'not_logged_in') ? 'log-in' : 'shield-alert';
        $button_text = 'Go to Login';
        $button_url = home_url('/login/');

        ob_start();
        ?>
        <style>
            .eau-access-denied-container {
                max-width: 600px !important;
                margin: 4rem auto !important;
                padding: 2rem !important;
                text-align: center !important;
            }

            .eau-access-denied-card {
                background: #ffffff !important;
                border: 1px solid #e5e7eb !important;
                border-radius: 16px !important;
                padding: 3rem 2rem !important;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1) !important;
            }

            .eau-access-denied-icon {
                width: 64px !important;
                height: 64px !important;
                margin: 0 auto 1.5rem !important;
                color: #dc2626 !important;
            }

            .eau-access-denied-title {
                font-size: 1.5rem !important;
                font-weight: 600 !important;
                color: #111827 !important;
                margin: 0 0 1rem 0 !important;
                line-height: 1.2 !important;
            }

            .eau-access-denied-message {
                font-size: 1rem !important;
                color: #6b7280 !important;
                line-height: 1.6 !important;
                margin: 0 0 2rem 0 !important;
            }

            .eau-access-denied-button {
                display: inline-flex !important;
                align-items: center !important;
                gap: 0.5rem !important;
                padding: 0.875rem 2rem !important;
                background: #2563eb !important;
                color: #ffffff !important;
                border: none !important;
                border-radius: 8px !important;
                font-size: 1rem !important;
                font-weight: 500 !important;
                text-decoration: none !important;
                cursor: pointer !important;
                transition: all 0.2s ease !important;
            }

            .eau-access-denied-button:hover {
                background: #1d4ed8 !important;
                color: #ffffff !important;
                text-decoration: none !important;
                transform: translateY(-1px) !important;
                box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3) !important;
            }

            .eau-access-denied-button i {
                width: 20px !important;
                height: 20px !important;
            }

            @media (max-width: 768px) {
                .eau-access-denied-container {
                    margin: 2rem auto !important;
                    padding: 1rem !important;
                }

                .eau-access-denied-card {
                    padding: 2rem 1.5rem !important;
                }

                .eau-access-denied-title {
                    font-size: 1.25rem !important;
                }

                .eau-access-denied-message {
                    font-size: 0.9375rem !important;
                }
            }
        </style>

        <div class="eau-access-denied-container">
            <div class="eau-access-denied-card">
                <i data-lucide="<?php echo esc_attr($icon); ?>" class="eau-access-denied-icon"></i>

                <h1 class="eau-access-denied-title"><?php echo esc_html($title); ?></h1>

                <p class="eau-access-denied-message"><?php echo esc_html($message); ?></p>

                <a href="<?php echo esc_url($button_url); ?>" class="eau-access-denied-button">
                    <i data-lucide="arrow-right"></i>
                    <?php echo esc_html($button_text); ?>
                </a>
            </div>
        </div>

        <script>
            (function() {
                function initLucideIcons() {
                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    } else {
                        setTimeout(initLucideIcons, 100);
                    }
                }

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', initLucideIcons);
                } else {
                    initLucideIcons();
                }
            })();
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * Atalho para renderizar mensagem de "não logado"
     *
     * @return string HTML da mensagem
     */
    public static function not_logged_in() {
        return self::render(
            'Authentication Required',
            'You need to be logged in to access this page.',
            'not_logged_in'
        );
    }

    /**
     * Atalho para renderizar mensagem de "sem permissão"
     *
     * @param string $required_role Descrição da role necessária (opcional)
     * @return string HTML da mensagem
     */
    public static function no_permission($required_role = 'Administrators and Super Administrators') {
        return self::render(
            'Access Denied',
            "You do not have sufficient permissions to access this page. Only {$required_role} can access this feature.",
            'no_permission'
        );
    }

    /**
     * Atalho para renderizar mensagem de "membership inativo"
     *
     * Usado quando o usuário está logado mas seu membership não está ativo
     * (cancelado, expirado, suspenso, ou sem membership)
     *
     * @since 1.51.46
     * @return string HTML da mensagem
     */
    public static function membership_inactive() {
        $status = \EauSystem\Eau_User_Institution_Helper::get_membership_status();

        // Mensagens específicas por status
        $messages = array(
            'cancelled' => 'Your membership has been cancelled. Please contact support if you believe this is an error.',
            'expired' => 'Your membership has expired. Please renew your membership to access this feature.',
            'suspended' => 'Your membership has been suspended. Please contact support for more information.',
            'pending' => 'Your membership application is pending approval. Please wait for confirmation.',
        );

        $message = isset($messages[$status])
            ? $messages[$status]
            : 'You need an active membership to access this feature. Please apply for a membership or renew your existing one.';

        return self::render_membership_inactive(
            'Active Membership Required',
            $message,
            $status
        );
    }

    /**
     * Renderiza mensagem de membership inativo com botão para Member Centre
     *
     * @since 1.51.46
     * @param string $title Título da mensagem
     * @param string $message Texto descritivo
     * @param string $status Status atual do membership
     * @return string HTML da mensagem
     */
    private static function render_membership_inactive($title, $message, $status) {
        // Enfileira Lucide Icons
        wp_enqueue_script(
            'lucide-icons',
            'https://unpkg.com/lucide@latest/dist/umd/lucide.min.js',
            array(),
            null,
            true
        );

        // Define ícone e botão baseado no status
        $icon = 'user-x';
        if ($status === 'expired') {
            $icon = 'clock';
        } elseif ($status === 'pending') {
            $icon = 'hourglass';
        }

        $button_text = 'Go to Dashboard';
        $button_url = home_url('/dashboard/');

        ob_start();
        ?>
        <style>
            .eau-access-denied-container {
                max-width: 600px !important;
                margin: 4rem auto !important;
                padding: 2rem !important;
                text-align: center !important;
            }

            .eau-access-denied-card {
                background: #ffffff !important;
                border: 1px solid #e5e7eb !important;
                border-radius: 16px !important;
                padding: 3rem 2rem !important;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1) !important;
            }

            .eau-access-denied-icon {
                width: 64px !important;
                height: 64px !important;
                margin: 0 auto 1.5rem !important;
                color: #f59e0b !important;
            }

            .eau-access-denied-title {
                font-size: 1.5rem !important;
                font-weight: 600 !important;
                color: #111827 !important;
                margin: 0 0 1rem 0 !important;
                line-height: 1.2 !important;
            }

            .eau-access-denied-message {
                font-size: 1rem !important;
                color: #6b7280 !important;
                line-height: 1.6 !important;
                margin: 0 0 2rem 0 !important;
            }

            .eau-access-denied-button {
                display: inline-flex !important;
                align-items: center !important;
                gap: 0.5rem !important;
                padding: 0.875rem 2rem !important;
                background: #2563eb !important;
                color: #ffffff !important;
                border: none !important;
                border-radius: 8px !important;
                font-size: 1rem !important;
                font-weight: 500 !important;
                text-decoration: none !important;
                cursor: pointer !important;
                transition: all 0.2s ease !important;
            }

            .eau-access-denied-button:hover {
                background: #1d4ed8 !important;
                color: #ffffff !important;
                text-decoration: none !important;
                transform: translateY(-1px) !important;
                box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3) !important;
            }

            .eau-access-denied-button i {
                width: 20px !important;
                height: 20px !important;
            }

            @media (max-width: 768px) {
                .eau-access-denied-container {
                    margin: 2rem auto !important;
                    padding: 1rem !important;
                }

                .eau-access-denied-card {
                    padding: 2rem 1.5rem !important;
                }

                .eau-access-denied-title {
                    font-size: 1.25rem !important;
                }

                .eau-access-denied-message {
                    font-size: 0.9375rem !important;
                }
            }
        </style>

        <div class="eau-access-denied-container">
            <div class="eau-access-denied-card">
                <i data-lucide="<?php echo esc_attr($icon); ?>" class="eau-access-denied-icon"></i>

                <h1 class="eau-access-denied-title"><?php echo esc_html($title); ?></h1>

                <p class="eau-access-denied-message"><?php echo esc_html($message); ?></p>

                <a href="<?php echo esc_url($button_url); ?>" class="eau-access-denied-button">
                    <i data-lucide="arrow-right"></i>
                    <?php echo esc_html($button_text); ?>
                </a>
            </div>
        </div>

        <script>
            (function() {
                function initLucideIcons() {
                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    } else {
                        setTimeout(initLucideIcons, 100);
                    }
                }

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', initLucideIcons);
                } else {
                    initLucideIcons();
                }
            })();
        </script>
        <?php
        return ob_get_clean();
    }
}
