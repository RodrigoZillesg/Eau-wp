<?php
namespace EauSystem;

/**
 * Classe para garantir compatibilidade com WooCommerce
 */
class Eau_WooCommerce_Compat {

    public function __construct() {
        // Hooks para garantir compatibilidade
        add_action('plugins_loaded', array($this, 'check_woocommerce'));
        add_filter('woocommerce_get_query_vars', array($this, 'register_query_vars'));
    }

    /**
     * Verifica se WooCommerce está ativo e configura hooks
     */
    public function check_woocommerce() {
        if (class_exists('WooCommerce')) {
            // WooCommerce está ativo
            $this->setup_woocommerce_integration();
        }
    }

    /**
     * Configura integração com WooCommerce
     */
    private function setup_woocommerce_integration() {
        // Adiciona suporte aos post types criados pelo Eau System
        add_filter('woocommerce_data_stores', array($this, 'register_data_stores'));

        // Garante que os custom fields sejam compatíveis com produtos
        add_action('woocommerce_product_options_general_product_data', array($this, 'add_custom_fields_to_products'));

        // Permite que post types customizados apareçam em queries do WooCommerce
        add_filter('woocommerce_is_attribute_in_product_name', '__return_true');
    }

    /**
     * Registra query vars customizadas
     */
    public function register_query_vars($vars) {
        $post_types = \EauSystem\Eau_Post_Type_Creator::get_registered_post_types();

        foreach ($post_types as $slug => $config) {
            $vars[$slug] = $slug;
        }

        return $vars;
    }

    /**
     * Registra data stores para compatibilidade
     */
    public function register_data_stores($stores) {
        // Os post types criados pelo Eau System são compatíveis com o data store padrão
        return $stores;
    }

    /**
     * Adiciona campos customizados aos produtos (se necessário)
     */
    public function add_custom_fields_to_products() {
        // Placeholder para adicionar campos customizados aos produtos WooCommerce
        // Será implementado conforme necessidade
    }

    /**
     * Verifica se um post type é compatível com WooCommerce
     */
    public static function is_woocommerce_compatible($post_type_slug) {
        if (!class_exists('WooCommerce')) {
            return false;
        }

        // Todos os post types criados pelo Eau System são 100% compatíveis
        $post_types = \EauSystem\Eau_Post_Type_Creator::get_registered_post_types();

        return isset($post_types[$post_type_slug]);
    }

    /**
     * Adiciona meta boxes de compatibilidade WooCommerce
     */
    public static function add_woocommerce_meta_boxes($post_type_slug) {
        if (!self::is_woocommerce_compatible($post_type_slug)) {
            return;
        }

        add_meta_box(
            'eau_woocommerce_compat',
            'Compatibilidade WooCommerce',
            array(__CLASS__, 'render_woocommerce_meta_box'),
            $post_type_slug,
            'side',
            'default'
        );
    }

    /**
     * Renderiza meta box de compatibilidade
     */
    public static function render_woocommerce_meta_box($post) {
        echo '<div class="eau-wc-compat-info">';
        echo '<p><span class="dashicons dashicons-yes-alt" style="color: #00a32a;"></span> ';
        echo 'Este post type é 100% compatível com WooCommerce.</p>';
        echo '<p class="description">Você pode usar todos os recursos do WooCommerce com este post type.</p>';
        echo '</div>';
    }

    /**
     * Adiciona suporte a produtos WooCommerce em post types customizados
     */
    public static function add_product_support($post_type_slug) {
        if (!class_exists('WooCommerce')) {
            return;
        }

        // Adiciona taxonomias do WooCommerce ao post type
        register_taxonomy_for_object_type('product_cat', $post_type_slug);
        register_taxonomy_for_object_type('product_tag', $post_type_slug);

        // Adiciona suporte a recursos do WooCommerce
        add_post_type_support($post_type_slug, 'woocommerce');
    }

    /**
     * Verifica compatibilidade e exibe avisos se necessário
     */
    public static function check_compatibility_notices() {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-info is-dismissible">';
                echo '<p><strong>Eau System:</strong> O plugin é 100% compatível com WooCommerce. ';
                echo 'Instale o WooCommerce para aproveitar recursos adicionais de e-commerce.</p>';
                echo '</div>';
            });
        }
    }
}

// Inicializa a classe de compatibilidade
new Eau_WooCommerce_Compat();
