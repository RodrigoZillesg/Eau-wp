<?php
/**
 * Template: Single Institution Page
 *
 * Uses the Eau System custom header instead of theme header
 *
 * @since 1.63.0
 */

// Previne acesso direto
if (!defined('ABSPATH')) {
    exit;
}

$institution_id = get_query_var('eau_institution_id');
$institution = get_post($institution_id);

// Verifica se usuário está logado
if (!is_user_logged_in()) {
    wp_redirect(home_url('/login/?redirect=' . urlencode($_SERVER['REQUEST_URI'])));
    exit;
}

// Verifica permissões
if (!\EauSystem\Eau_Institution_Single::user_can_view_institution($institution_id)) {
    wp_redirect(home_url('/dashboard/'));
    exit;
}

// Get institution name for title
$company_name = get_post_meta($institution_id, 'ins_company_name', true) ?: $institution->post_title;

// Enqueue header styles
wp_enqueue_style(
    'eau-header',
    EAU_SYSTEM_PLUGIN_URL . 'assets/css/eau-header.css',
    array(),
    EAU_SYSTEM_VERSION
);

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($company_name); ?> - Institution Details</title>
    <?php wp_head(); ?>
</head>
<body <?php body_class('eau-system-page eau-custom-header'); ?>>
<?php wp_body_open(); ?>

<?php
// Output the custom Eau header
echo \EauSystem\Eau_Header::render_header();
?>

<main id="main-content" class="eau-single-institution-main">
    <div class="eau-institution-page-wrapper">
        <?php echo \EauSystem\Eau_Institution_Single::render_institution_page($institution); ?>
    </div>
</main>

<script>
    // Inicializa Lucide icons após carregar
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    });
</script>

<?php wp_footer(); ?>
</body>
</html>
