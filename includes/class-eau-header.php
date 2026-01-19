<?php
namespace EauSystem;

/**
 * Custom Header for Eau System Pages
 *
 * Renders a custom header for all plugin pages, replacing the default theme header.
 *
 * @since 1.57.5
 */
class Eau_Header {

    /**
     * Track if header was already output
     */
    private static $header_output = false;

    /**
     * Initialize the header system
     */
    public static function init() {
        // Add custom header at the beginning of body
        add_action('wp_body_open', array(__CLASS__, 'output_header'), 1);

        // Fallback: add via wp_head with JS if wp_body_open not available
        add_action('wp_footer', array(__CLASS__, 'output_header_fallback'), 1);

        // Add body class for system pages
        add_filter('body_class', array(__CLASS__, 'add_body_class'));

        // Enqueue header styles
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_styles'));
    }

    /**
     * Check if current page is a system page
     *
     * @return bool
     */
    public static function is_system_page() {
        // Check for Institution Single Page (rewrite rule)
        $institution_id = get_query_var('eau_institution_id');
        if (!empty($institution_id)) {
            return true;
        }

        if (!is_page()) {
            return false;
        }

        $page_ids = Eau_Pages::get_page_ids();
        $current_page_id = get_the_ID();

        return in_array($current_page_id, $page_ids);
    }

    /**
     * Add body class for system pages
     *
     * @param array $classes
     * @return array
     */
    public static function add_body_class($classes) {
        if (self::is_system_page()) {
            $classes[] = 'eau-system-page';
            $classes[] = 'eau-custom-header';
        }
        return $classes;
    }

    /**
     * Enqueue header styles
     */
    public static function enqueue_styles() {
        if (!self::is_system_page()) {
            return;
        }

        wp_enqueue_style(
            'eau-header',
            EAU_SYSTEM_PLUGIN_URL . 'assets/css/eau-header.css',
            array(),
            EAU_SYSTEM_VERSION
        );
    }

    /**
     * Output header via wp_body_open hook
     */
    public static function output_header() {
        if (!self::is_system_page()) {
            return;
        }

        // Check if already output
        if (self::$header_output) {
            return;
        }
        self::$header_output = true;

        echo self::render_header();
    }

    /**
     * Fallback for themes without wp_body_open support
     * Injects header via JavaScript
     */
    public static function output_header_fallback() {
        if (!self::is_system_page()) {
            return;
        }

        // Check if header was already output via wp_body_open
        if (self::$header_output) {
            return;
        }
        self::$header_output = true;

        // Use JavaScript to inject header at start of body
        $header_html = self::render_header();
        $header_escaped = esc_js($header_html);
        ?>
        <script>
        (function() {
            if (document.querySelector('.eau-app-header')) return;
            var header = document.createElement('div');
            header.innerHTML = '<?php echo $header_escaped; ?>';
            var headerElement = header.firstElementChild;
            if (headerElement && document.body.firstChild) {
                document.body.insertBefore(headerElement, document.body.firstChild);
            }
        })();
        </script>
        <?php
    }

    /**
     * Render the custom header HTML
     *
     * @return string
     */
    public static function render_header() {
        $logo_url = EAU_SYSTEM_PLUGIN_URL . 'assets/images/english-australia-logo.png';
        $home_url = home_url('/dashboard/');

        // Get current user info
        $current_user = wp_get_current_user();
        $user_email = $current_user->user_email ?? '';
        $user_display = $user_email ?: $current_user->display_name;

        ob_start();
        ?>
        <header class="eau-app-header">
            <div class="eau-app-header-inner">
                <!-- Logo -->
                <a href="<?php echo esc_url($home_url); ?>" class="eau-app-header-logo">
                    <img src="<?php echo esc_url($logo_url); ?>" alt="English Australia" />
                </a>

                <!-- Right Side -->
                <div class="eau-app-header-right">
                    <?php if (is_user_logged_in()) : ?>
                    <a href="<?php echo esc_url(home_url('/profile/')); ?>" class="eau-app-header-user">
                        <i data-lucide="user"></i>
                        <span class="eau-app-header-email"><?php echo esc_html($user_display); ?></span>
                    </a>
                    <?php endif; ?>

                    <!-- Sidebar Menu (shortcode) -->
                    <?php echo do_shortcode('[eau_sidebar_menu]'); ?>
                </div>
            </div>
        </header>
        <?php
        return ob_get_clean();
    }
}
