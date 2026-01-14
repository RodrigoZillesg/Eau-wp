<?php
namespace EauSystem;

/**
 * Menu Sidebar para o Eau System
 *
 * Renderiza um botão hamburger e um menu sidebar responsivo
 * com links baseados no tipo de usuário (mem_type)
 *
 * @since 1.56.0
 */
class Eau_Sidebar_Menu {

    /**
     * Registra o shortcode
     */
    public static function register_shortcode() {
        add_shortcode('eau_sidebar_menu', array(__CLASS__, 'render_shortcode'));
    }

    /**
     * Enfileira os assets do sidebar menu
     */
    public static function enqueue_assets() {
        wp_enqueue_style(
            'eau-sidebar-menu',
            EAU_SYSTEM_PLUGIN_URL . 'assets/css/eau-sidebar-menu.css',
            array(),
            EAU_SYSTEM_VERSION
        );

        wp_enqueue_script(
            'eau-sidebar-menu',
            EAU_SYSTEM_PLUGIN_URL . 'assets/js/eau-sidebar-menu.js',
            array('jquery'),
            EAU_SYSTEM_VERSION,
            true
        );
    }

    /**
     * Renderiza o shortcode
     *
     * @param array $atts Atributos do shortcode
     * @return string HTML do shortcode
     */
    public static function render_shortcode($atts = array()) {
        // Enfileira assets
        self::enqueue_assets();

        // Se não está logado, não mostra o menu
        if (!is_user_logged_in()) {
            return '';
        }

        $current_user = wp_get_current_user();
        $user_id = $current_user->ID;
        $mem_type = get_user_meta($user_id, 'mem_type', true);

        // Obtém nome para exibição
        $display_name = $current_user->display_name;
        if (empty($display_name)) {
            $display_name = $current_user->user_email;
        }

        // Obtém email do usuário
        $user_email = $current_user->user_email;

        // Define os menus baseado no tipo de usuário
        $navigation_items = self::get_navigation_items($mem_type);
        $administration_items = self::get_administration_items($mem_type);
        $account_items = self::get_account_items();

        // Gera o HTML
        ob_start();
        ?>
        <div class="eau-sidebar-menu-wrapper">
            <!-- Hamburger Button -->
            <button type="button" class="eau-hamburger-btn" id="eau-hamburger-btn" aria-label="Menu" aria-expanded="false">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="eau-hamburger-icon">
                    <line x1="4" x2="20" y1="12" y2="12"></line>
                    <line x1="4" x2="20" y1="6" y2="6"></line>
                    <line x1="4" x2="20" y1="18" y2="18"></line>
                </svg>
            </button>

            <!-- Overlay -->
            <div class="eau-sidebar-overlay" id="eau-sidebar-overlay"></div>

            <!-- Sidebar -->
            <div class="eau-sidebar" id="eau-sidebar">
                <!-- Close Button -->
                <button type="button" class="eau-sidebar-close" id="eau-sidebar-close" aria-label="Fechar menu">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 6 6 18"></path>
                        <path d="m6 6 12 12"></path>
                    </svg>
                </button>

                <!-- Navigation Section -->
                <?php if (!empty($navigation_items)) : ?>
                <div class="eau-sidebar-section">
                    <div class="eau-sidebar-section-title">NAVIGATION</div>
                    <nav class="eau-sidebar-nav">
                        <?php foreach ($navigation_items as $item) : ?>
                        <a href="<?php echo esc_url($item['url']); ?>" class="eau-sidebar-link<?php echo self::is_current_page($item['url']) ? ' active' : ''; ?>">
                            <span class="eau-sidebar-link-text"><?php echo esc_html($item['label']); ?></span>
                            <span class="eau-sidebar-link-icon"><?php echo $item['icon']; ?></span>
                        </a>
                        <?php endforeach; ?>
                    </nav>
                </div>
                <?php endif; ?>

                <!-- Administration Section -->
                <?php if (!empty($administration_items)) : ?>
                <div class="eau-sidebar-section">
                    <div class="eau-sidebar-section-title">ADMINISTRATION</div>
                    <nav class="eau-sidebar-nav">
                        <?php foreach ($administration_items as $item) : ?>
                        <a href="<?php echo esc_url($item['url']); ?>" class="eau-sidebar-link<?php echo self::is_current_page($item['url']) ? ' active' : ''; ?>">
                            <span class="eau-sidebar-link-text"><?php echo esc_html($item['label']); ?></span>
                            <span class="eau-sidebar-link-icon"><?php echo $item['icon']; ?></span>
                        </a>
                        <?php endforeach; ?>
                    </nav>
                </div>
                <?php endif; ?>

                <!-- Account Section -->
                <?php if (!empty($account_items)) : ?>
                <div class="eau-sidebar-section">
                    <div class="eau-sidebar-section-title">ACCOUNT</div>
                    <nav class="eau-sidebar-nav">
                        <?php foreach ($account_items as $item) : ?>
                        <a href="<?php echo esc_url($item['url']); ?>" class="eau-sidebar-link<?php echo self::is_current_page($item['url']) ? ' active' : ''; ?>">
                            <span class="eau-sidebar-link-text"><?php echo esc_html($item['label']); ?></span>
                            <span class="eau-sidebar-link-icon"><?php echo $item['icon']; ?></span>
                        </a>
                        <?php endforeach; ?>
                    </nav>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Obtém os itens de navegação baseado no tipo de usuário
     *
     * @param string $mem_type Tipo de membro
     * @return array Array de itens de navegação
     */
    private static function get_navigation_items($mem_type) {
        $items = array();

        // Dashboard - disponível para todos os usuários logados
        $items[] = array(
            'label' => 'Dashboard',
            'url' => home_url('/dashboard/'),
            'icon' => self::get_icon('home'),
        );

        // My CPDs - disponível para todos
        $items[] = array(
            'label' => 'My CPDs',
            'url' => home_url('/dashboard/my-cpds/'),
            'icon' => self::get_icon('file-badge'),
        );

        // My Payments - disponível para todos
        $items[] = array(
            'label' => 'My Payments',
            'url' => home_url('/dashboard/my-payments/'),
            'icon' => self::get_icon('credit-card'),
        );

        // Events - disponível para todos
        $items[] = array(
            'label' => 'Events',
            'url' => home_url('/events/'),
            'icon' => self::get_icon('calendar'),
        );

        // My Institution - disponível para todos (institutionAdmin vê mais opções)
        $items[] = array(
            'label' => 'My Institution',
            'url' => home_url('/dashboard/my-institution/'),
            'icon' => self::get_icon('building'),
        );

        // OpenLearning Courses - disponível para todos
        $items[] = array(
            'label' => 'OpenLearning Courses',
            'url' => home_url('/dashboard/courses/'),
            'icon' => self::get_icon('book-open'),
        );

        return $items;
    }

    /**
     * Obtém os itens de administração baseado no tipo de usuário
     *
     * @param string $mem_type Tipo de membro
     * @return array Array de itens de administração
     */
    private static function get_administration_items($mem_type) {
        $items = array();

        // Apenas para Admin, superAdmin ou institutionAdmin
        if (!in_array($mem_type, array('Admin', 'superAdmin', 'institutionAdmin'))) {
            return $items;
        }

        // Manage Members - Admin e superAdmin
        if (in_array($mem_type, array('Admin', 'superAdmin'))) {
            $items[] = array(
                'label' => 'Manage Members',
                'url' => home_url('/dashboard/manage-members/'),
                'icon' => self::get_icon('users'),
            );
        }

        // Merge Members - Admin e superAdmin apenas
        if (in_array($mem_type, array('Admin', 'superAdmin'))) {
            $items[] = array(
                'label' => 'Merge Members',
                'url' => home_url('/dashboard/merge-members/'),
                'icon' => self::get_icon('git-merge'),
            );
        }

        // Manage Institutions - Admin e superAdmin
        if (in_array($mem_type, array('Admin', 'superAdmin'))) {
            $items[] = array(
                'label' => 'Manage Institutions',
                'url' => home_url('/dashboard/manage-institutions/'),
                'icon' => self::get_icon('building-2'),
            );
        }

        // Manage Activities - Admin e superAdmin
        if (in_array($mem_type, array('Admin', 'superAdmin'))) {
            $items[] = array(
                'label' => 'Manage Activities',
                'url' => home_url('/dashboard/manage-activities/'),
                'icon' => self::get_icon('clipboard-list'),
            );
        }

        // Manage Events - Admin e superAdmin
        if (in_array($mem_type, array('Admin', 'superAdmin'))) {
            $items[] = array(
                'label' => 'Manage Events',
                'url' => home_url('/dashboard/events/'),
                'icon' => self::get_icon('calendar-range'),
            );
        }

        // Manage Payments - Admin e superAdmin
        if (in_array($mem_type, array('Admin', 'superAdmin'))) {
            $items[] = array(
                'label' => 'Manage Payments',
                'url' => home_url('/dashboard/payments/'),
                'icon' => self::get_icon('wallet'),
            );
        }

        // Settings - Admin e superAdmin
        if (in_array($mem_type, array('Admin', 'superAdmin'))) {
            $items[] = array(
                'label' => 'Settings',
                'url' => home_url('/dashboard/settings/'),
                'icon' => self::get_icon('settings'),
            );
        }

        // CPD Categories removed in v1.60.0 - now integrated into Settings page

        // Open Learning Management - Admin e superAdmin
        if (in_array($mem_type, array('Admin', 'superAdmin'))) {
            $items[] = array(
                'label' => 'Open Learning Management',
                'url' => home_url('/dashboard/open-learning-management/'),
                'icon' => self::get_icon('graduation-cap'),
            );
        }

        return $items;
    }

    /**
     * Obtém os itens da seção Account
     *
     * @return array Array de itens
     */
    private static function get_account_items() {
        $items = array();

        // Profile
        $items[] = array(
            'label' => 'Profile',
            'url' => home_url('/profile/'),
            'icon' => self::get_icon('user'),
        );

        // Logout
        $items[] = array(
            'label' => 'Logout',
            'url' => wp_logout_url(home_url('/')),
            'icon' => self::get_icon('log-out'),
        );

        // WP Admin Panel - apenas para quem tem acesso ao admin
        if (current_user_can('read')) {
            $items[] = array(
                'label' => 'Panel',
                'url' => admin_url(),
                'icon' => self::get_icon('settings-2'),
            );
        }

        return $items;
    }

    /**
     * Verifica se a URL atual corresponde ao item do menu
     *
     * @param string $url URL do item
     * @return bool True se é a página atual
     */
    private static function is_current_page($url) {
        $current_url = home_url($_SERVER['REQUEST_URI']);
        $current_path = wp_parse_url($current_url, PHP_URL_PATH);
        $item_path = wp_parse_url($url, PHP_URL_PATH);

        // Remove trailing slashes para comparação
        $current_path = rtrim($current_path, '/');
        $item_path = rtrim($item_path, '/');

        return $current_path === $item_path;
    }

    /**
     * Retorna o SVG do ícone
     *
     * @param string $name Nome do ícone
     * @return string SVG do ícone
     */
    private static function get_icon($name) {
        $icons = array(
            'home' => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>',

            'file-badge' => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"></path><polyline points="14 2 14 8 20 8"></polyline><path d="M12 18v-6"></path><path d="M9 15h6"></path></svg>',

            'credit-card' => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="5" rx="2"></rect><line x1="2" x2="22" y1="10" y2="10"></line></svg>',

            'calendar' => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"></rect><line x1="16" x2="16" y1="2" y2="6"></line><line x1="8" x2="8" y1="2" y2="6"></line><line x1="3" x2="21" y1="10" y2="10"></line></svg>',

            'building' => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="16" height="20" x="4" y="2" rx="2" ry="2"></rect><path d="M9 22v-4h6v4"></path><path d="M8 6h.01"></path><path d="M16 6h.01"></path><path d="M12 6h.01"></path><path d="M12 10h.01"></path><path d="M12 14h.01"></path><path d="M16 10h.01"></path><path d="M16 14h.01"></path><path d="M8 10h.01"></path><path d="M8 14h.01"></path></svg>',

            'book-open' => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path></svg>',

            'users' => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>',

            'git-merge' => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="18" r="3"></circle><circle cx="6" cy="6" r="3"></circle><path d="M6 21V9a9 9 0 0 0 9 9"></path></svg>',

            'building-2' => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z"></path><path d="M6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"></path><path d="M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2"></path><path d="M10 6h4"></path><path d="M10 10h4"></path><path d="M10 14h4"></path><path d="M10 18h4"></path></svg>',

            'clipboard-list' => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="8" height="4" x="8" y="2" rx="1" ry="1"></rect><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><path d="M12 11h4"></path><path d="M12 16h4"></path><path d="M8 11h.01"></path><path d="M8 16h.01"></path></svg>',

            'calendar-range' => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"></rect><line x1="16" x2="16" y1="2" y2="6"></line><line x1="8" x2="8" y1="2" y2="6"></line><line x1="3" x2="21" y1="10" y2="10"></line><path d="M17 14h-6"></path><path d="M13 18H7"></path><path d="M7 14h.01"></path><path d="M17 18h.01"></path></svg>',

            'wallet' => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"></path><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"></path><path d="M18 12a2 2 0 0 0 0 4h4v-4Z"></path></svg>',

            'settings' => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"></path><circle cx="12" cy="12" r="3"></circle></svg>',

            'folder-tree' => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10a1 1 0 0 0 1-1V6a1 1 0 0 0-1-1h-2.5a1 1 0 0 1-.8-.4l-.9-1.2A1 1 0 0 0 15 3h-2a1 1 0 0 0-1 1v5a1 1 0 0 0 1 1Z"></path><path d="M20 21a1 1 0 0 0 1-1v-3a1 1 0 0 0-1-1h-2.9a1 1 0 0 1-.88-.55l-.42-.85a1 1 0 0 0-.9-.6H13a1 1 0 0 0-1 1v5a1 1 0 0 0 1 1Z"></path><path d="M3 3v2"></path><path d="M3 19h7"></path><path d="M3 8h7"></path><path d="M3 5v16"></path></svg>',

            'graduation-cap' => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"></path><path d="M6 12v5c3 3 9 3 12 0v-5"></path></svg>',

            'user' => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>',

            'log-out' => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" x2="9" y1="12" y2="12"></line></svg>',

            'settings-2' => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 7h-9"></path><path d="M14 17H5"></path><circle cx="17" cy="17" r="3"></circle><circle cx="7" cy="7" r="3"></circle></svg>',
        );

        return isset($icons[$name]) ? $icons[$name] : '';
    }
}
