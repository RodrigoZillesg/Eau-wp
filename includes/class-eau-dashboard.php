<?php
namespace EauSystem;

use EauSystem\Components\Eau_Access_Denied;

/**
 * Classe para gerenciar dashboards customizados
 *
 * @since 1.0.0
 * @updated 1.41.0 - Adicionada seção de cursos OpenLearning
 * @updated 1.42.0 - Dashboard mostra 4 cursos do Post Type (não AJAX)
 */
class Eau_Dashboard {

    /**
     * Registra o shortcode
     */
    public static function register_shortcode() {
        add_shortcode('eau_admin_dashboard', array(__CLASS__, 'render_admin_dashboard'));
        add_shortcode('eau_register_link', array(__CLASS__, 'render_register_link'));
    }

    /**
     * Renderiza link para página de registro
     * Shortcode: [eau_register_link]
     *
     * @since 1.51.52
     * @param array $atts Atributos do shortcode
     * @return string HTML do link
     */
    public static function render_register_link($atts) {
        // Se usuário já está logado, não mostra o link
        if (is_user_logged_in()) {
            return '';
        }

        $atts = shortcode_atts(array(
            'text' => "Don't have an account? Register here",
            'link_text' => 'Register here',
            'url' => '/register/',
            'class' => 'eau-register-link',
        ), $atts);

        ob_start();
        ?>
        <div class="<?php echo esc_attr($atts['class']); ?>">
            <p>
                <?php
                $full_text = $atts['text'];
                $link_text = $atts['link_text'];

                // Se o texto contém o link_text, substitui por link
                if (strpos($full_text, $link_text) !== false) {
                    $link_html = '<a href="' . esc_url($atts['url']) . '">' . esc_html($link_text) . '</a>';
                    echo str_replace($link_text, $link_html, esc_html($full_text));
                } else {
                    // Senão, mostra o texto e link separados
                    echo esc_html($full_text) . ' ';
                    echo '<a href="' . esc_url($atts['url']) . '">' . esc_html($link_text) . '</a>';
                }
                ?>
            </p>
        </div>
        <style>
            .eau-register-link {
                text-align: center;
                margin-top: 1rem;
            }
            .eau-register-link p {
                color: #6b7280;
                font-size: 0.875rem;
                margin: 0;
            }
            .eau-register-link a {
                color: #2563eb;
                text-decoration: none;
                font-weight: 500;
            }
            .eau-register-link a:hover {
                text-decoration: underline;
            }
        </style>
        <?php
        return ob_get_clean();
    }

    /**
     * Enfileira assets específicos do dashboard
     */
    public static function enqueue_dashboard_assets() {
        // CSS para cursos OpenLearning
        wp_enqueue_style(
            'eau-openlearning-courses',
            EAU_SYSTEM_PLUGIN_URL . 'assets/css/eau-openlearning-courses.css',
            array(),
            EAU_SYSTEM_VERSION
        );

        // JavaScript para OpenLearning SSO (apenas para SSO, não para carregar cursos)
        wp_enqueue_script(
            'eau-openlearning',
            EAU_SYSTEM_PLUGIN_URL . 'assets/js/eau-openlearning.js',
            array('jquery'),
            EAU_SYSTEM_VERSION,
            true
        );

        // Passa dados para o JavaScript
        wp_localize_script('eau-openlearning', 'eauOpenLearning', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('eau_openlearning_nonce'),
            'coursesUrl' => '/dashboard/courses/', // URL da página de listagem
            'i18n' => array(
                'launching' => __('Opening OpenLearning...', 'eau-system'),
                'launchError' => __('Failed to open course', 'eau-system'),
                'accessCourse' => __('Access Course', 'eau-system'),
                'free' => __('Free', 'eau-system'),
            ),
        ));
    }

    /**
     * Renderiza o dashboard do Super Admin
     */
    public static function render_admin_dashboard($atts) {
        // Verifica se usuário está logado
        if (!is_user_logged_in()) {
            return Eau_Access_Denied::not_logged_in();
        }

        // Por enquanto, qualquer usuário logado pode ver
        // TODO: Adicionar verificação de mem_type depois

        // Enfileira assets do dashboard (OpenLearning)
        self::enqueue_dashboard_assets();

        // Coleta estatísticas
        $stats = self::get_dashboard_stats();

        // Pega o nome do usuário logado
        $current_user = wp_get_current_user();
        $display_name = $current_user->display_name;

        // Identifica tipo de usuário
        $user_role_info = self::get_user_role_info($current_user->ID);

        // Verifica se é membro com status pendente (aguardando aprovação)
        // Verifica mem_membership_status = 'pending' OU (mem_membership_status vazio E mem_status = 'pending')
        $is_pending_member = false;
        if (!Eau_User_Institution_Helper::has_admin_access()) {
            $membership_status = Eau_User_Institution_Helper::get_membership_status($current_user->ID);
            $mem_status = get_user_meta($current_user->ID, 'mem_status', true);

            // É pendente se:
            // 1. mem_membership_status é 'pending', OU
            // 2. mem_membership_status está vazio/não existe E mem_status é 'pending'
            $is_pending_member = ($membership_status === 'pending') ||
                                 (empty($membership_status) && $mem_status === 'pending');
        }

        // Renderiza HTML
        ob_start();
        ?>
        <div class="eau-dashboard-container">

            <!-- Welcome Section -->
            <div class="eau-welcome-section">
                <h1 class="eau-welcome-title">Welcome, <?php echo esc_html($display_name); ?></h1>
                <p class="eau-welcome-description">
                    <?php echo esc_html($user_role_info['description']); ?>
                    <?php if (!empty($user_role_info['institutions'])): ?>
                        <?php foreach ($user_role_info['institutions'] as $institution_name): ?>
                            <span class="eau-institution-badge"><?php echo esc_html($institution_name); ?></span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </p>
            </div>

            <?php
            // Pending Member - Show special message and limited cards
            if ($is_pending_member):
            ?>
            <div class="eau-membership-alert eau-alert-info">
                <div class="eau-membership-alert-icon">
                    <i data-lucide="clock"></i>
                </div>
                <div class="eau-membership-alert-content">
                    <h4>Membership Application Under Review</h4>
                    <p>Thank you for applying! Your membership application is currently being reviewed by English Australia. You will receive an email notification once your application has been approved.</p>
                </div>
            </div>

            <div class="eau-dashboard-cards">
                <!-- My Profile (for pending members) -->
                <a href="/dashboard/profile/" class="eau-dashboard-card-link">
                    <div class="eau-dashboard-card eau-card-slate">
                        <div class="eau-card-content">
                            <h3 class="eau-card-title">My Profile</h3>
                            <div class="eau-card-stats">
                                <span class="eau-card-number eau-card-membership-type"><?php echo esc_html($current_user->display_name); ?></span>
                                <span class="eau-card-active">View & Edit Profile</span>
                            </div>
                        </div>
                        <div class="eau-card-icon">
                            <i data-lucide="user-circle"></i>
                        </div>
                    </div>
                </a>

                <!-- My Membership (for pending members) -->
                <?php
                $membership_info = self::get_user_membership_info($current_user->ID);
                if ($membership_info):
                ?>
                <a href="/membership-selection/" class="eau-dashboard-card-link">
                    <div class="eau-dashboard-card eau-card-teal">
                        <div class="eau-card-content">
                            <h3 class="eau-card-title">My Membership</h3>
                            <div class="eau-card-stats">
                                <span class="eau-card-number eau-card-membership-type"><?php echo esc_html($membership_info['type_label']); ?></span>
                                <span class="eau-card-<?php echo esc_attr($membership_info['status_class']); ?>">
                                    <?php echo esc_html($membership_info['status_label']); ?>
                                </span>
                            </div>
                        </div>
                        <div class="eau-card-icon">
                            <i data-lucide="id-card"></i>
                        </div>
                    </div>
                </a>
                <?php endif; ?>
            </div>

            <?php
            // Reinicializa Lucide icons
            ?>
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
            });
            </script>
        </div>
            <?php
            return ob_get_clean();
            endif;
            ?>

            <?php
            // Membership Inactive Alert (v1.51.46)
            // Show alert if user is not admin and has inactive membership
            if (!Eau_User_Institution_Helper::has_admin_access() && Eau_User_Institution_Helper::is_membership_inactive()):
                $membership_status = Eau_User_Institution_Helper::get_membership_status();
                $alert_messages = array(
                    'cancelled' => array(
                        'title' => 'Membership Cancelled',
                        'message' => 'Your membership has been cancelled. Some features are restricted. Please contact support if you believe this is an error.',
                        'class' => 'eau-alert-danger',
                        'icon' => 'user-x',
                    ),
                    'expired' => array(
                        'title' => 'Membership Expired',
                        'message' => 'Your membership has expired. Please renew your membership to regain full access to member features.',
                        'class' => 'eau-alert-warning',
                        'icon' => 'clock',
                    ),
                    'suspended' => array(
                        'title' => 'Membership Suspended',
                        'message' => 'Your membership has been suspended. Please contact support for more information.',
                        'class' => 'eau-alert-danger',
                        'icon' => 'alert-triangle',
                    ),
                );
                $alert = isset($alert_messages[$membership_status]) ? $alert_messages[$membership_status] : array(
                    'title' => 'Membership Issue',
                    'message' => 'There is an issue with your membership. Please visit the Member Centre.',
                    'class' => 'eau-alert-warning',
                    'icon' => 'alert-circle',
                );
            ?>
            <div class="eau-membership-alert <?php echo esc_attr($alert['class']); ?>">
                <div class="eau-membership-alert-icon">
                    <i data-lucide="<?php echo esc_attr($alert['icon']); ?>"></i>
                </div>
                <div class="eau-membership-alert-content">
                    <h4><?php echo esc_html($alert['title']); ?></h4>
                    <p><?php echo esc_html($alert['message']); ?></p>
                </div>
                <a href="/dashboard/" class="eau-btn eau-btn-secondary eau-btn-sm">
                    Go to Dashboard
                </a>
            </div>
            <?php endif; ?>

            <div class="eau-dashboard-cards">

                <!-- Total Members (Admin/superAdmin only) -->
                <?php if (Eau_User_Institution_Helper::has_admin_access()): ?>
                <a href="/dashboard/members/" class="eau-dashboard-card-link">
                    <div class="eau-dashboard-card eau-card-blue">
                        <div class="eau-card-content">
                            <h3 class="eau-card-title">Total Members</h3>
                            <div class="eau-card-stats">
                                <span class="eau-card-number"><?php echo number_format($stats['total_members']); ?></span>
                                <span class="eau-card-active"><?php echo number_format($stats['active_members']); ?> Active</span>
                            </div>
                        </div>
                        <div class="eau-card-icon">
                            <i data-lucide="users"></i>
                        </div>
                    </div>
                </a>
                <?php endif; ?>

                <!-- Total Institutions (apenas para superAdmin e Admin) -->
                <?php if (Eau_User_Institution_Helper::has_admin_access()): ?>
                <a href="/dashboard/manage-institutions/" class="eau-dashboard-card-link">
                    <div class="eau-dashboard-card eau-card-indigo">
                        <div class="eau-card-content">
                            <h3 class="eau-card-title">Total Institutions</h3>
                            <div class="eau-card-stats">
                                <span class="eau-card-number"><?php echo number_format($stats['total_institutions']); ?></span>
                            </div>
                        </div>
                        <div class="eau-card-icon">
                            <i data-lucide="building-2"></i>
                        </div>
                    </div>
                </a>
                <?php endif; ?>

                <!-- Pending Member Requests (apenas para institutionAdmin) -->
                <?php if (Eau_User_Institution_Helper::is_institution_admin()): ?>
                <a href="/dashboard/my-instituion/" class="eau-dashboard-card-link">
                    <div class="eau-dashboard-card eau-card-teal">
                        <div class="eau-card-content">
                            <h3 class="eau-card-title">Member Requests</h3>
                            <div class="eau-card-stats">
                                <span class="eau-card-number"><?php echo number_format($stats['pending_member_requests']); ?></span>
                                <span class="eau-card-pending">Pending Approval</span>
                            </div>
                        </div>
                        <div class="eau-card-icon">
                            <i data-lucide="user-plus"></i>
                        </div>
                    </div>
                </a>
                <?php endif; ?>

                <!-- CPD Activities (Admin/superAdmin only) -->
                <?php if (Eau_User_Institution_Helper::has_admin_access()): ?>
                <a href="/dashboard/manage-activities/" class="eau-dashboard-card-link">
                    <div class="eau-dashboard-card eau-card-green">
                        <div class="eau-card-content">
                            <h3 class="eau-card-title">CPD Activities</h3>
                            <div class="eau-card-stats">
                                <span class="eau-card-number"><?php echo number_format($stats['cpd_activities']); ?></span>
                                <span class="eau-card-pending"><?php echo number_format($stats['pending_approval']); ?> Pending Approval</span>
                            </div>
                        </div>
                        <div class="eau-card-icon">
                            <i data-lucide="book-open"></i>
                        </div>
                    </div>
                </a>
                <?php endif; ?>

                <!-- Active Events (visible to all users) -->
                <a href="/events/" class="eau-dashboard-card-link">
                    <div class="eau-dashboard-card eau-card-purple">
                        <div class="eau-card-content">
                            <h3 class="eau-card-title">Upcoming Events</h3>
                            <div class="eau-card-stats">
                                <span class="eau-card-number"><?php echo number_format($stats['active_events']); ?></span>
                                <?php if (!empty($stats['next_event'])): ?>
                                    <span class="eau-card-pending eau-card-next-event">
                                        Next: <?php echo esc_html($stats['next_event']['title']); ?> - <?php echo esc_html($stats['next_event']['date']); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="eau-card-icon">
                            <i data-lucide="calendar"></i>
                        </div>
                    </div>
                </a>

                <!-- Points Awarded (Admin/superAdmin only) -->
                <?php if (Eau_User_Institution_Helper::has_admin_access()): ?>
                <div class="eau-dashboard-card eau-card-orange">
                    <div class="eau-card-content">
                        <h3 class="eau-card-title">Points Awarded</h3>
                        <div class="eau-card-stats">
                            <span class="eau-card-number"><?php echo number_format($stats['points_awarded'], 1); ?></span>
                        </div>
                    </div>
                    <div class="eau-card-icon">
                        <i data-lucide="award"></i>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Pending Payments (Admin/superAdmin only) -->
                <?php if (Eau_User_Institution_Helper::has_admin_access()): ?>
                <a href="/dashboard/payments/?payment_status=pending" class="eau-dashboard-card-link">
                    <div class="eau-dashboard-card eau-card-red">
                        <div class="eau-card-content">
                            <h3 class="eau-card-title">Pending Payments</h3>
                            <div class="eau-card-stats">
                                <span class="eau-card-number"><?php echo number_format($stats['pending_payments']); ?></span>
                            </div>
                        </div>
                        <div class="eau-card-icon">
                            <i data-lucide="credit-card"></i>
                        </div>
                    </div>
                </a>
                <?php endif; ?>

                <!-- My Profile (for all logged-in users) -->
                <a href="/dashboard/profile/" class="eau-dashboard-card-link">
                    <div class="eau-dashboard-card eau-card-slate">
                        <div class="eau-card-content">
                            <h3 class="eau-card-title">My Profile</h3>
                            <div class="eau-card-stats">
                                <span class="eau-card-number eau-card-membership-type"><?php echo esc_html($current_user->display_name); ?></span>
                                <span class="eau-card-active">View & Edit Profile</span>
                            </div>
                        </div>
                        <div class="eau-card-icon">
                            <i data-lucide="user-circle"></i>
                        </div>
                    </div>
                </a>

                <!-- Pending User Approvals (Admin/superAdmin only) -->
                <?php if (Eau_User_Institution_Helper::has_admin_access()): ?>
                <a href="/dashboard/members/?status=pending" class="eau-dashboard-card-link">
                    <div class="eau-dashboard-card eau-card-yellow">
                        <div class="eau-card-content">
                            <h3 class="eau-card-title">Pending Approvals</h3>
                            <div class="eau-card-stats">
                                <span class="eau-card-number"><?php echo number_format($stats['pending_user_approvals']); ?></span>
                                <span class="eau-card-pending">Users Awaiting Approval</span>
                            </div>
                        </div>
                        <div class="eau-card-icon">
                            <i data-lucide="user-check"></i>
                        </div>
                    </div>
                </a>
                <?php else:
                // My Membership (for non-admin users)
                $membership_info = self::get_user_membership_info($current_user->ID);
                if ($membership_info):
                ?>
                <a href="/membership-selection/" class="eau-dashboard-card-link">
                    <div class="eau-dashboard-card eau-card-teal">
                        <div class="eau-card-content">
                            <h3 class="eau-card-title">My Membership</h3>
                            <div class="eau-card-stats">
                                <span class="eau-card-number eau-card-membership-type"><?php echo esc_html($membership_info['type_label']); ?></span>
                                <span class="eau-card-<?php echo esc_attr($membership_info['status_class']); ?>">
                                    <?php echo esc_html($membership_info['status_label']); ?>
                                </span>
                            </div>
                        </div>
                        <div class="eau-card-icon">
                            <i data-lucide="id-card"></i>
                        </div>
                    </div>
                </a>
                <?php endif; ?>
                <?php endif; ?>

                <!-- Membership Applications (superAdmin and Admin only) -->
                <?php if (Eau_User_Institution_Helper::has_admin_access() && $stats['pending_applications'] > 0): ?>
                <a href="/dashboard/membership-applications/" class="eau-dashboard-card-link">
                    <div class="eau-dashboard-card eau-card-yellow">
                        <div class="eau-card-content">
                            <h3 class="eau-card-title">Membership Applications</h3>
                            <div class="eau-card-stats">
                                <span class="eau-card-number"><?php echo number_format($stats['pending_applications']); ?></span>
                                <span class="eau-card-pending">Pending Review</span>
                            </div>
                        </div>
                        <div class="eau-card-icon">
                            <i data-lucide="file-text"></i>
                        </div>
                    </div>
                </a>
                <?php endif; ?>

            </div>

            <!-- Share Registration Section (Admin/superAdmin only) -->
            <?php if (Eau_User_Institution_Helper::has_admin_access()): ?>
            <?php
            $registration_url = home_url('/register/');
            // QR pequeno para exibição na página
            $qr_display_url = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' . urlencode($registration_url);
            // QR grande para download/impressão em materiais de marketing (500x500)
            $qr_download_url = 'https://api.qrserver.com/v1/create-qr-code/?size=500x500&format=png&data=' . urlencode($registration_url);
            ?>
            <div class="eau-share-registration-section">
                <div class="eau-share-registration-header">
                    <div class="eau-share-registration-title-group">
                        <h2 class="eau-share-registration-title">
                            <i data-lucide="share-2"></i>
                            Share Registration Page
                        </h2>
                        <p class="eau-share-registration-subtitle">Invite new members to register on the platform</p>
                    </div>
                </div>

                <div class="eau-share-registration-content">
                    <!-- QR Code -->
                    <div class="eau-share-qrcode-container">
                        <div class="eau-share-qrcode">
                            <img src="<?php echo esc_url($qr_display_url); ?>"
                                 alt="Registration QR Code"
                                 id="eau-registration-qrcode"
                                 width="150"
                                 height="150"
                                 loading="lazy">
                        </div>
                        <button type="button"
                           class="eau-btn eau-btn-secondary eau-btn-sm"
                           id="eau-download-qrcode"
                           data-url="<?php echo esc_url($qr_download_url); ?>">
                            <i data-lucide="download"></i>
                            Download QR Code
                        </button>
                    </div>

                    <!-- Link and Share Options -->
                    <div class="eau-share-options-container">
                        <!-- Copy Link -->
                        <div class="eau-share-link-group">
                            <label class="eau-share-label">Registration Link</label>
                            <div class="eau-share-link-input-group">
                                <input type="text"
                                    id="eau-registration-url"
                                    class="eau-share-link-input"
                                    value="<?php echo esc_url($registration_url); ?>"
                                    readonly>
                                <button type="button" class="eau-btn eau-btn-primary" id="eau-copy-link">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>
                                    <span>Copy</span>
                                </button>
                            </div>
                            <span class="eau-share-link-copied" id="eau-link-copied">Link copied!</span>
                        </div>

                        <!-- Share Buttons -->
                        <div class="eau-share-buttons-group">
                            <label class="eau-share-label">Share via</label>
                            <div class="eau-share-buttons">
                                <button type="button" class="eau-share-btn eau-share-whatsapp" data-share="whatsapp" title="Share on WhatsApp">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="#ffffff"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                                </button>
                                <button type="button" class="eau-share-btn eau-share-linkedin" data-share="linkedin" title="Share on LinkedIn">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="#ffffff"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
                                </button>
                                <button type="button" class="eau-share-btn eau-share-twitter" data-share="twitter" title="Share on X (Twitter)">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="#ffffff"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                                </button>
                                <button type="button" class="eau-share-btn eau-share-email" data-share="email" title="Share via Email">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="#ffffff"><path d="M1.5 8.67v8.58a3 3 0 003 3h15a3 3 0 003-3V8.67l-8.928 5.493a3 3 0 01-3.144 0L1.5 8.67z"/><path d="M22.5 6.908V6.75a3 3 0 00-3-3h-15a3 3 0 00-3 3v.158l9.714 5.978a1.5 1.5 0 001.572 0L22.5 6.908z"/></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <script>
            (function() {
                const registrationUrl = '<?php echo esc_js($registration_url); ?>';
                const shareText = 'Join English Australia! Register now:';

                // Copy Link with fallback
                const copyBtn = document.getElementById('eau-copy-link');
                if (copyBtn) {
                    copyBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        const input = document.getElementById('eau-registration-url');
                        const copiedMsg = document.getElementById('eau-link-copied');
                        const textToCopy = input.value;

                        // Try modern clipboard API first
                        if (navigator.clipboard && navigator.clipboard.writeText) {
                            navigator.clipboard.writeText(textToCopy).then(function() {
                                showCopiedMessage(copiedMsg);
                            }).catch(function() {
                                // Fallback if clipboard API fails
                                fallbackCopy(input, copiedMsg);
                            });
                        } else {
                            // Fallback for older browsers
                            fallbackCopy(input, copiedMsg);
                        }
                    });
                }

                function showCopiedMessage(element) {
                    element.classList.add('visible');
                    setTimeout(function() {
                        element.classList.remove('visible');
                    }, 2000);
                }

                function fallbackCopy(input, copiedMsg) {
                    input.select();
                    input.setSelectionRange(0, 99999);
                    try {
                        document.execCommand('copy');
                        showCopiedMessage(copiedMsg);
                    } catch (err) {
                        console.error('Copy failed:', err);
                        alert('Press Ctrl+C to copy the link');
                    }
                    input.setSelectionRange(0, 0);
                }

                // Download QR Code directly
                const downloadBtn = document.getElementById('eau-download-qrcode');
                if (downloadBtn) {
                    downloadBtn.addEventListener('click', async function(e) {
                        e.preventDefault();
                        const url = this.dataset.url;
                        const originalText = this.innerHTML;

                        try {
                            // Show loading state
                            this.innerHTML = '<i data-lucide="loader-2" class="animate-spin"></i> Downloading...';
                            this.disabled = true;

                            // Fetch the image
                            const response = await fetch(url);
                            const blob = await response.blob();

                            // Create download link
                            const downloadUrl = window.URL.createObjectURL(blob);
                            const link = document.createElement('a');
                            link.href = downloadUrl;
                            link.download = 'english-australia-registration-qrcode.png';
                            document.body.appendChild(link);
                            link.click();
                            document.body.removeChild(link);
                            window.URL.revokeObjectURL(downloadUrl);

                            // Restore button
                            this.innerHTML = originalText;
                            this.disabled = false;
                            if (typeof lucide !== 'undefined') {
                                lucide.createIcons();
                            }
                        } catch (err) {
                            console.error('Download failed:', err);
                            // Fallback: open in new tab
                            window.open(url, '_blank');
                            this.innerHTML = originalText;
                            this.disabled = false;
                            if (typeof lucide !== 'undefined') {
                                lucide.createIcons();
                            }
                        }
                    });
                }

                // Share Buttons
                document.querySelectorAll('.eau-share-btn').forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        const platform = this.dataset.share;
                        let url = '';

                        switch (platform) {
                            case 'whatsapp':
                                url = 'https://wa.me/?text=' + encodeURIComponent(shareText + ' ' + registrationUrl);
                                break;
                            case 'linkedin':
                                url = 'https://www.linkedin.com/sharing/share-offsite/?url=' + encodeURIComponent(registrationUrl);
                                break;
                            case 'twitter':
                                url = 'https://twitter.com/intent/tweet?text=' + encodeURIComponent(shareText) + '&url=' + encodeURIComponent(registrationUrl);
                                break;
                            case 'email':
                                url = 'mailto:?subject=' + encodeURIComponent('Join English Australia') + '&body=' + encodeURIComponent(shareText + '\n\n' + registrationUrl);
                                break;
                        }

                        if (url) {
                            if (platform === 'email') {
                                window.location.href = url;
                            } else {
                                window.open(url, '_blank', 'width=600,height=400');
                            }
                        }
                    });
                });
            })();
            </script>
            <?php endif; ?>

            <!-- OpenLearning Courses Section -->
            <?php
            // Busca 4 cursos do Post Type (destaques primeiro)
            $dashboard_courses = Eau_OpenLearning_Post_Type::get_dashboard_courses();
            $total_courses = Eau_OpenLearning_Post_Type::count_visible_courses();
            ?>
            <div class="eau-openlearning-section">
                <div class="eau-openlearning-header">
                    <div class="eau-openlearning-title-group">
                        <h2 class="eau-openlearning-title">
                            <i data-lucide="graduation-cap"></i>
                            Available Courses
                        </h2>
                        <p class="eau-openlearning-subtitle">Access your professional development courses on OpenLearning</p>
                    </div>
                    <?php if ($total_courses > 4): ?>
                    <a href="/dashboard/courses/" class="eau-btn eau-btn-secondary eau-btn-sm">
                        <i data-lucide="arrow-right"></i>
                        View All Courses (<?php echo $total_courses; ?>)
                    </a>
                    <?php endif; ?>
                </div>

                <?php if (!empty($dashboard_courses)): ?>
                <!-- Courses Grid (renderizado server-side) -->
                <div class="eau-openlearning-courses-grid" id="eau-openlearning-courses">
                    <?php foreach ($dashboard_courses as $course): ?>
                    <?php
                        $price_label = $course['price'] > 0 ? '$' . number_format($course['price'], 2) : 'Free';
                        $price_class = $course['price'] > 0 ? '' : 'eau-course-free';
                        $description = !empty($course['description']) ? wp_trim_words($course['description'], 15, '...') : '';
                    ?>
                    <div class="eau-course-card" data-course-id="<?php echo esc_attr($course['course_id']); ?>">
                        <div class="eau-course-image">
                            <?php if (!empty($course['image_url'])): ?>
                                <img src="<?php echo esc_url($course['image_url']); ?>" alt="<?php echo esc_attr($course['title']); ?>">
                            <?php else: ?>
                                <div class="eau-course-image-placeholder"><i data-lucide="book-open"></i></div>
                            <?php endif; ?>
                            <span class="eau-course-price-badge <?php echo $price_class; ?>"><?php echo $price_label; ?></span>
                            <?php if ($course['is_featured']): ?>
                                <span class="eau-course-featured-badge"><i data-lucide="star"></i></span>
                            <?php endif; ?>
                        </div>
                        <div class="eau-course-content">
                            <h3 class="eau-course-title"><?php echo esc_html($course['title']); ?></h3>
                            <p class="eau-course-description"><?php echo esc_html($description); ?></p>
                            <div class="eau-course-footer">
                                <button type="button"
                                        class="eau-course-access-btn"
                                        data-course-id="<?php echo esc_attr($course['course_id']); ?>">
                                    <i data-lucide="external-link"></i>
                                    Access Course
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <!-- Empty State -->
                <div class="eau-openlearning-empty">
                    <i data-lucide="book-x"></i>
                    <p>No courses available at the moment.</p>
                    <p class="eau-openlearning-empty-hint">Courses are being synchronized. Please check back later.</p>
                </div>
                <?php endif; ?>
            </div>

        </div>

        <script>
            // Re-inicializa ícones Lucide quando o shortcode é renderizado
            (function() {
                function initLucideIcons() {
                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                        console.log('EAU Dashboard: Lucide icons initialized');
                    } else {
                        console.warn('EAU Dashboard: Lucide not loaded yet, retrying...');
                        setTimeout(initLucideIcons, 100);
                    }
                }

                // Tenta inicializar imediatamente
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
     * Coleta todas as estatísticas do dashboard
     */
    private static function get_dashboard_stats() {
        // Usa o método filtrado do helper que já respeita institutionAdmin
        $user_stats = Eau_User_Institution_Helper::get_users_stats();

        return array(
            'total_members' => $user_stats['total'],
            'active_members' => $user_stats['active'],
            'total_institutions' => self::get_total_institutions(),
            'pending_member_requests' => self::get_pending_member_requests(),
            'cpd_activities' => self::get_cpd_activities(),
            'pending_approval' => self::get_pending_approval(),
            'active_events' => self::get_active_events(),
            'next_event' => self::get_next_event(),
            'points_awarded' => self::get_points_awarded(),
            'pending_payments' => self::get_pending_payments(),
            'pending_applications' => self::get_pending_membership_applications(),
            'pending_user_approvals' => self::get_pending_user_approvals(),
        );
    }

    /**
     * Total de Institutions
     */
    private static function get_total_institutions() {
        $count = wp_count_posts('institutions');
        return isset($count->publish) ? $count->publish : 0;
    }

    /**
     * Pending Member Requests (para institutionAdmin)
     * Conta solicitações pendentes de membros que querem se vincular às instituições gerenciadas
     */
    private static function get_pending_member_requests() {
        // Apenas para institutionAdmin
        if (!Eau_User_Institution_Helper::is_institution_admin()) {
            return 0;
        }

        $user_id = get_current_user_id();

        // Pega as instituições gerenciadas
        $managed = Eau_User_Institution_Helper::get_user_managed_institutions($user_id);
        $institution_ids = array_map(function($inst) { return $inst->ID; }, $managed);

        if (empty($institution_ids)) {
            return 0;
        }

        // Conta solicitações pendentes usando o database helper
        return Eau_Institution_Requests_Database::count_pending_for_institutions($institution_ids);
    }

    /**
     * Total de CPD Activities publicadas
     *
     * CORRETO: Filtra por TODAS as instituições que o admin gerencia
     * Usa act_user_id (não post_author) para relacionamento
     */
    private static function get_cpd_activities() {
        global $wpdb;

        $is_institution_admin = Eau_User_Institution_Helper::is_institution_admin();

        if ($is_institution_admin) {
            $company_ids = Eau_User_Institution_Helper::get_user_managed_company_ids();

            if (empty($company_ids)) {
                return 0;
            }

            // Busca act_user_id dos membros dessas instituições
            $user_ids = get_users(array(
                'fields' => 'ID',
                'meta_query' => array(
                    array(
                        'key' => 'mem_membercompanyname',
                        'value' => $company_ids,
                        'compare' => 'IN',
                    ),
                ),
            ));

            if (empty($user_ids)) {
                return 0;
            }

            // Pega os mem_userid desses usuários
            $act_user_ids = array();
            foreach ($user_ids as $user_id) {
                $mem_userid = get_user_meta($user_id, 'mem_userid', true);
                if (!empty($mem_userid)) {
                    $act_user_ids[] = $mem_userid;
                }
            }

            if (empty($act_user_ids)) {
                return 0;
            }

            // Busca activities via act_user_id (relacionamento correto)
            $placeholders = implode(',', array_fill(0, count($act_user_ids), '%s'));
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT p.ID)
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_type = 'activitie'
                AND p.post_status = 'publish'
                AND pm.meta_key = 'act_user_id'
                AND pm.meta_value IN ($placeholders)",
                ...$act_user_ids
            ));

            return intval($count);
        }

        // Admin/Super Admin: vê tudo
        $count = wp_count_posts('activitie');
        return isset($count->publish) ? $count->publish : 0;
    }

    /**
     * Activities pendentes de aprovação (act_verified != 1)
     *
     * CORRETO: Filtra por TODAS as instituições que o admin gerencia
     * Usa act_user_id (não post_author) para relacionamento
     */
    private static function get_pending_approval() {
        global $wpdb;

        $is_institution_admin = Eau_User_Institution_Helper::is_institution_admin();

        if ($is_institution_admin) {
            $company_ids = Eau_User_Institution_Helper::get_user_managed_company_ids();

            if (empty($company_ids)) {
                return 0;
            }

            // Busca act_user_id dos membros dessas instituições
            $user_ids = get_users(array(
                'fields' => 'ID',
                'meta_query' => array(
                    array(
                        'key' => 'mem_membercompanyname',
                        'value' => $company_ids,
                        'compare' => 'IN',
                    ),
                ),
            ));

            if (empty($user_ids)) {
                return 0;
            }

            // Pega os mem_userid desses usuários
            $act_user_ids = array();
            foreach ($user_ids as $user_id) {
                $mem_userid = get_user_meta($user_id, 'mem_userid', true);
                if (!empty($mem_userid)) {
                    $act_user_ids[] = $mem_userid;
                }
            }

            if (empty($act_user_ids)) {
                return 0;
            }

            // Activities pendentes via act_user_id (relacionamento correto)
            $placeholders = implode(',', array_fill(0, count($act_user_ids), '%s'));
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT p.ID)
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm_user ON p.ID = pm_user.post_id
                LEFT JOIN {$wpdb->postmeta} pm_verified ON p.ID = pm_verified.post_id AND pm_verified.meta_key = 'act_verified'
                WHERE p.post_type = 'activitie'
                AND p.post_status = 'publish'
                AND pm_user.meta_key = 'act_user_id'
                AND pm_user.meta_value IN ($placeholders)
                AND (pm_verified.meta_value IS NULL OR pm_verified.meta_value != '1')",
                ...$act_user_ids
            ));

            return intval($count);
        }

        // Admin/Super Admin: vê tudo
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT p.ID)
                FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = %s
                WHERE p.post_type = %s
                AND p.post_status = %s
                AND (pm.meta_value IS NULL OR pm.meta_value != %s)",
                'act_verified',
                'activitie',
                'publish',
                '1'
            )
        );

        return intval($count);
    }

    /**
     * Eventos ativos (publicados com evt_start_datetime >= agora)
     */
    private static function get_active_events() {
        global $wpdb;
        $now = current_time('Y-m-d H:i:s');

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT p.ID)
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_type = %s
                AND p.post_status = %s
                AND pm.meta_key = %s
                AND pm.meta_value >= %s",
                'eau_event',
                'publish',
                'evt_start_datetime',
                $now
            )
        );

        return intval($count);
    }

    /**
     * Retorna o próximo evento agendado
     *
     * @return array|null Array com 'title' e 'date' ou null se não houver
     */
    private static function get_next_event() {
        global $wpdb;
        $now = current_time('Y-m-d H:i:s');

        // Busca o próximo evento publicado com start_datetime >= agora
        $event = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT p.ID, p.post_title, pm.meta_value as start_datetime
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_type = %s
                AND p.post_status = %s
                AND pm.meta_key = %s
                AND pm.meta_value >= %s
                ORDER BY pm.meta_value ASC
                LIMIT 1",
                'eau_event',
                'publish',
                'evt_start_datetime',
                $now
            )
        );

        if (!$event) {
            return null;
        }

        // Formata a data para exibição
        $date_formatted = '';
        if (!empty($event->start_datetime)) {
            $timestamp = strtotime($event->start_datetime);
            if ($timestamp) {
                $date_formatted = date_i18n('M j', $timestamp);
            }
        }

        // Trunca título se necessário
        $title = $event->post_title;
        if (strlen($title) > 25) {
            $title = substr($title, 0, 25) . '...';
        }

        return array(
            'title' => $title,
            'date' => $date_formatted,
        );
    }

    /**
     * Soma de pontos (hours) de todas as activities publicadas
     *
     * CORRETO: Filtra por TODAS as instituições que o admin gerencia
     */
    private static function get_points_awarded() {
        global $wpdb;

        $table_categories = $wpdb->prefix . 'eau_activity_categories';
        $is_institution_admin = Eau_User_Institution_Helper::is_institution_admin();

        if ($is_institution_admin) {
            $company_ids = Eau_User_Institution_Helper::get_user_managed_company_ids();

            if (empty($company_ids)) {
                return 0;
            }

            // Busca act_user_id dos membros dessas instituições
            $user_ids = get_users(array(
                'fields' => 'ID',
                'meta_query' => array(
                    array(
                        'key' => 'mem_membercompanyname',
                        'value' => $company_ids,
                        'compare' => 'IN',
                    ),
                ),
            ));

            if (empty($user_ids)) {
                return 0;
            }

            // Pega os mem_userid desses usuários
            $act_user_ids = array();
            foreach ($user_ids as $user_id) {
                $mem_userid = get_user_meta($user_id, 'mem_userid', true);
                if (!empty($mem_userid)) {
                    $act_user_ids[] = $mem_userid;
                }
            }

            if (empty($act_user_ids)) {
                return 0;
            }

            // Calcula pontos: horas × pontos_per_hour da categoria
            $placeholders = implode(',', array_fill(0, count($act_user_ids), '%s'));
            $total = $wpdb->get_var($wpdb->prepare(
                "SELECT SUM(
                    CAST(pm_hours.meta_value AS DECIMAL(10,2)) *
                    COALESCE(cat.points_per_hour, 0)
                )
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm_user ON p.ID = pm_user.post_id AND pm_user.meta_key = 'act_user_id'
                INNER JOIN {$wpdb->postmeta} pm_hours ON p.ID = pm_hours.post_id AND pm_hours.meta_key = 'act_hours_of_pd_anything_below_60_minutes_can_be_entered_as_a_decimal_e_g_30_mins_0_5'
                LEFT JOIN {$wpdb->postmeta} pm_cat ON p.ID = pm_cat.post_id AND pm_cat.meta_key = 'act_category_serial'
                LEFT JOIN {$table_categories} cat ON cat.category_serial = pm_cat.meta_value
                WHERE p.post_type = 'activitie'
                AND p.post_status = 'publish'
                AND pm_user.meta_value IN ($placeholders)",
                ...$act_user_ids
            ));

            return floatval($total);
        }

        // Admin/Super Admin: vê tudo - calcula pontos: horas × pontos_per_hour
        $total = $wpdb->get_var(
            "SELECT SUM(
                CAST(pm_hours.meta_value AS DECIMAL(10,2)) *
                COALESCE(cat.points_per_hour, 0)
            )
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm_hours ON p.ID = pm_hours.post_id
                AND pm_hours.meta_key = 'act_hours_of_pd_anything_below_60_minutes_can_be_entered_as_a_decimal_e_g_30_mins_0_5'
            LEFT JOIN {$wpdb->postmeta} pm_cat ON p.ID = pm_cat.post_id
                AND pm_cat.meta_key = 'act_category_serial'
            LEFT JOIN {$table_categories} cat ON cat.category_serial = pm_cat.meta_value
            WHERE p.post_type = 'activitie'
            AND p.post_status = 'publish'"
        );

        return floatval($total);
    }

    /**
     * Conta pagamentos pendentes reais
     *
     * Busca faturas de eventos e membership applications com status pending ou partial
     *
     * @since 1.51.56
     * @return int
     */
    private static function get_pending_payments() {
        global $wpdb;

        $pending_count = 0;

        // 1. Contar Event Registrations com pagamento pendente
        $event_regs = get_posts(array(
            'post_type'      => 'eau_event_reg',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ));

        foreach ($event_regs as $reg_id) {
            $event_id = get_post_meta($reg_id, 'reg_event_id', true);
            $event_price = floatval(get_post_meta($event_id, 'evt_member_price', true) ?: 0);

            // Pula eventos gratuitos
            if ($event_price <= 0) {
                continue;
            }

            // Calcula total pago
            $total_paid = \EauSystem\Payments\Payments_Post_Type::get_total_paid($reg_id);

            // Se não pagou tudo, é pendente
            if ($total_paid < $event_price) {
                $pending_count++;
            }
        }

        // 2. Contar Membership Applications aprovadas com pagamento pendente
        $table = $wpdb->prefix . 'eau_membership_applications';

        // Verifica se a tabela existe
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
            $applications = $wpdb->get_results(
                "SELECT application_id, membership_type FROM {$table} WHERE status = 'approved'",
                ARRAY_A
            );

            foreach ($applications as $app) {
                $app_id = $app['application_id'];
                $membership_type = $app['membership_type'];

                // Busca preço do membership type
                $type = \EauSystem\Eau_Membership_Types::get_by_key($membership_type);
                $fee = $type ? floatval($type->fee_amount) : 0;

                // Pula memberships gratuitos ou tipo não encontrado
                if ($fee <= 0) {
                    continue;
                }

                // Calcula total pago para esta application
                $total_paid = \EauSystem\Payments\Payments_Post_Type::get_membership_total_paid_by_application($app_id);

                // Se não pagou tudo, é pendente
                if ($total_paid < $fee) {
                    $pending_count++;
                }
            }
        }

        return $pending_count;
    }

    /**
     * Conta usuários aguardando aprovação
     *
     * Busca usuários com mem_membership_status = 'pending'
     *
     * @since 1.51.61
     * @return int
     */
    private static function get_pending_user_approvals() {
        global $wpdb;

        // Conta usuários com mem_membership_status = 'pending'
        $count = $wpdb->get_var(
            "SELECT COUNT(DISTINCT u.ID)
            FROM {$wpdb->users} u
            INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
            WHERE um.meta_key = 'mem_membership_status'
            AND um.meta_value = 'pending'"
        );

        return intval($count);
    }

    /**
     * Retorna informações sobre a role do usuário para exibição
     *
     * @param int $user_id ID do usuário
     * @return array Array com 'description' e 'institutions' (array de nomes)
     */
    private static function get_user_role_info($user_id) {
        $user = get_userdata($user_id);

        // Verifica se é Super Admin ou Admin (manage_options)
        if (in_array('administrator', $user->roles) || current_user_can('manage_options')) {
            return array(
                'description' => 'System Administrator - Full access to all institutions and data',
                'institutions' => array(),
            );
        }

        // Verifica se é Institution Admin - CORRETO: pega TODAS as instituições
        if (Eau_User_Institution_Helper::is_institution_admin($user_id)) {
            $institution_names = Eau_User_Institution_Helper::get_user_managed_institution_names($user_id);

            if (empty($institution_names)) {
                return array(
                    'description' => 'Institution Administrator for',
                    'institutions' => array('Unknown Institution'),
                );
            }

            return array(
                'description' => 'Institution Administrator for',
                'institutions' => $institution_names,
            );
        }

        // Membro comum
        return array(
            'description' => 'Here\'s what\'s happening with your membership today.',
            'institutions' => array(),
        );
    }

    /**
     * Get pending membership applications count
     *
     * @since 1.49.5
     * @return int
     */
    private static function get_pending_membership_applications() {
        global $wpdb;

        $table_name = Eau_Membership_Database::get_table_name(Eau_Membership_Database::TABLE_APPLICATIONS);

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE status IN (%s, %s)",
            'pending',
            'under_review'
        ));

        return intval($count);
    }

    /**
     * Get user membership info for dashboard card
     *
     * @since 1.49.5
     * @param int $user_id
     * @return array|null
     */
    private static function get_user_membership_info($user_id) {
        $membership_type = get_user_meta($user_id, 'mem_membership_type', true);
        $membership_status = get_user_meta($user_id, 'mem_membership_status', true);

        // If no membership type set, check if there's a pending application
        if (empty($membership_type)) {
            global $wpdb;
            $table_name = Eau_Membership_Database::get_table_name(Eau_Membership_Database::TABLE_APPLICATIONS);
            $user_email = wp_get_current_user()->user_email;

            $pending_app = $wpdb->get_row($wpdb->prepare(
                "SELECT membership_type, status FROM $table_name WHERE email = %s AND status IN ('pending', 'under_review') ORDER BY submitted_at DESC LIMIT 1",
                $user_email
            ));

            if ($pending_app) {
                $type_data = Eau_Membership_Types::get_by_key($pending_app->membership_type);
                return array(
                    'type_label' => $type_data ? $type_data->type_label : 'Pending Application',
                    'status_label' => 'Application ' . ucwords(str_replace('_', ' ', $pending_app->status)),
                    'status_class' => 'pending',
                );
            }

            // No membership and no pending application
            return array(
                'type_label' => 'Not a Member',
                'status_label' => 'Apply for Membership',
                'status_class' => 'inactive',
            );
        }

        // Get membership type label
        $type_data = Eau_Membership_Types::get_by_key($membership_type);
        $type_label = $type_data ? $type_data->type_label : ucwords(str_replace('_', ' ', $membership_type));

        // Status labels and classes (v1.51.46 - added cancelled)
        $status_labels = array(
            'active' => 'Active',
            'pending' => 'Pending Approval',
            'expired' => 'Expired',
            'suspended' => 'Suspended',
            'cancelled' => 'Cancelled',
        );

        $status_classes = array(
            'active' => 'active',
            'pending' => 'pending',
            'expired' => 'inactive',
            'suspended' => 'inactive',
            'cancelled' => 'inactive',
        );

        $status_label = isset($status_labels[$membership_status]) ? $status_labels[$membership_status] : 'Unknown';
        $status_class = isset($status_classes[$membership_status]) ? $status_classes[$membership_status] : 'inactive';

        // Check for expiry
        $expiry_date = get_user_meta($user_id, 'mem_membership_expiry_date', true);
        if ($membership_status === 'active' && !empty($expiry_date)) {
            $days_until_expiry = (strtotime($expiry_date) - time()) / DAY_IN_SECONDS;
            if ($days_until_expiry <= 30 && $days_until_expiry > 0) {
                $status_label = 'Expiring in ' . ceil($days_until_expiry) . ' days';
                $status_class = 'pending';
            }
        }

        return array(
            'type_label' => $type_label,
            'status_label' => $status_label,
            'status_class' => $status_class,
        );
    }
}
