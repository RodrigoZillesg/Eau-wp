<?php
namespace EauSystem;

use EauSystem\Components\Eau_Access_Denied;

/**
 * Classe para renderizar a página de listagem de cursos OpenLearning
 *
 * Shortcode: [eau_openlearning_courses]
 *
 * @since 1.42.0
 */
class Eau_OpenLearning_Courses {

    /**
     * Registra o shortcode
     */
    public static function register_shortcode() {
        add_shortcode('eau_openlearning_courses', array(__CLASS__, 'render_courses_page'));
    }

    /**
     * Enfileira assets da página
     */
    public static function enqueue_assets() {
        // CSS para cursos OpenLearning
        wp_enqueue_style(
            'eau-openlearning-courses',
            EAU_SYSTEM_PLUGIN_URL . 'assets/css/eau-openlearning-courses.css',
            array(),
            EAU_SYSTEM_VERSION
        );

        // CSS adicional para página de listagem
        wp_enqueue_style(
            'eau-openlearning-courses-page',
            EAU_SYSTEM_PLUGIN_URL . 'assets/css/eau-openlearning-courses-page.css',
            array('eau-openlearning-courses'),
            EAU_SYSTEM_VERSION
        );

        // JavaScript para OpenLearning SSO
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
            'i18n' => array(
                'launching' => __('Opening OpenLearning...', 'eau-system'),
                'launchError' => __('Failed to open course', 'eau-system'),
                'accessCourse' => __('Access Course', 'eau-system'),
                'free' => __('Free', 'eau-system'),
            ),
        ));
    }

    /**
     * Renderiza a página de cursos
     */
    public static function render_courses_page($atts) {
        // Verifica se usuário está logado
        if (!is_user_logged_in()) {
            return Eau_Access_Denied::not_logged_in();
        }

        // Enfileira assets
        self::enqueue_assets();

        // Busca cursos
        $featured_courses = Eau_OpenLearning_Post_Type::get_visible_courses(-1, true);
        $all_visible_courses = Eau_OpenLearning_Post_Type::get_visible_courses(-1, false);

        // Separa "outros" (não featured)
        $featured_ids = array_column($featured_courses, 'id');
        $other_courses = array_filter($all_visible_courses, function($course) use ($featured_ids) {
            return !in_array($course['id'], $featured_ids);
        });

        $total_courses = count($all_visible_courses);

        ob_start();
        ?>
        <div class="eau-courses-page-container">

            <!-- Page Header -->
            <div class="eau-courses-page-header">
                <div class="eau-courses-page-title-group">
                    <a href="/dashboard/" class="eau-back-link">
                        <i data-lucide="arrow-left"></i>
                        Back to Dashboard
                    </a>
                    <h1 class="eau-courses-page-title">
                        <i data-lucide="graduation-cap"></i>
                        Available Courses
                    </h1>
                    <p class="eau-courses-page-subtitle">
                        Access your professional development courses on OpenLearning
                        <span class="eau-courses-count"><?php echo $total_courses; ?> courses available</span>
                    </p>
                </div>
            </div>

            <?php if ($total_courses > 0): ?>

                <?php if (!empty($featured_courses)): ?>
                <!-- Featured Courses Section -->
                <div class="eau-courses-section eau-courses-featured-section">
                    <div class="eau-courses-section-header">
                        <h2 class="eau-courses-section-title">
                            <i data-lucide="star"></i>
                            Featured Courses
                        </h2>
                        <p class="eau-courses-section-subtitle">Recommended professional development courses</p>
                    </div>
                    <div class="eau-openlearning-courses-grid">
                        <?php foreach ($featured_courses as $course): ?>
                            <?php echo self::render_course_card($course, true); ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($other_courses)): ?>
                <!-- Other Courses Section -->
                <div class="eau-courses-section eau-courses-other-section">
                    <div class="eau-courses-section-header">
                        <h2 class="eau-courses-section-title">
                            <i data-lucide="book-open"></i>
                            All Courses
                        </h2>
                        <p class="eau-courses-section-subtitle">Browse all available professional development courses</p>
                    </div>
                    <div class="eau-openlearning-courses-grid">
                        <?php foreach ($other_courses as $course): ?>
                            <?php echo self::render_course_card($course, false); ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

            <?php else: ?>
                <!-- Empty State -->
                <div class="eau-courses-empty-state">
                    <i data-lucide="book-x"></i>
                    <h2>No Courses Available</h2>
                    <p>Courses are being synchronized. Please check back later or contact your administrator.</p>
                    <a href="/dashboard/" class="eau-btn eau-btn-primary">
                        <i data-lucide="arrow-left"></i>
                        Back to Dashboard
                    </a>
                </div>
            <?php endif; ?>

        </div>

        <script>
            // Re-inicializa ícones Lucide quando o shortcode é renderizado
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
     * Renderiza um card de curso
     *
     * @param array $course Dados do curso
     * @param bool $is_featured Se é curso em destaque
     * @return string HTML do card
     */
    private static function render_course_card($course, $is_featured = false) {
        $price_label = $course['price'] > 0 ? '$' . number_format($course['price'], 2) : 'Free';
        $price_class = $course['price'] > 0 ? '' : 'eau-course-free';
        $description = !empty($course['description']) ? wp_trim_words($course['description'], 20, '...') : '';

        ob_start();
        ?>
        <div class="eau-course-card<?php echo $is_featured ? ' eau-course-featured' : ''; ?>" data-course-id="<?php echo esc_attr($course['course_id']); ?>">
            <div class="eau-course-image">
                <?php if (!empty($course['image_url'])): ?>
                    <img src="<?php echo esc_url($course['image_url']); ?>" alt="<?php echo esc_attr($course['title']); ?>">
                <?php else: ?>
                    <div class="eau-course-image-placeholder"><i data-lucide="book-open"></i></div>
                <?php endif; ?>
                <span class="eau-course-price-badge <?php echo $price_class; ?>"><?php echo $price_label; ?></span>
                <?php if ($is_featured): ?>
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
        <?php
        return ob_get_clean();
    }
}
