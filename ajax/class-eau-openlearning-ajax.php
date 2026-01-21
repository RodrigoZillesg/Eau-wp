<?php
namespace EauSystem\Ajax;

use EauSystem\Eau_OpenLearning_Service;
use EauSystem\Eau_OpenLearning_Post_Type;
use EauSystem\Eau_User_Institution_Helper;
use EauSystem\Checkout\Eau_Course_Purchases;
use EauSystem\Eau_Pages;

/**
 * Handlers AJAX para OpenLearning
 *
 * @since 1.41.0
 * @updated 1.42.0 - Usa Post Type em vez de tabelas de cache
 */
class Eau_OpenLearning_Ajax {

    /**
     * Registra os handlers AJAX
     */
    public static function register_handlers() {
        // SSO Launch - gera dados para login automático
        add_action('wp_ajax_eau_openlearning_sso_launch', array(__CLASS__, 'handle_sso_launch'));

        // Get courses - retorna cursos disponíveis
        add_action('wp_ajax_eau_openlearning_get_courses', array(__CLASS__, 'handle_get_courses'));

        // Sync courses - sincroniza catálogo (admin only)
        add_action('wp_ajax_eau_openlearning_sync_courses', array(__CLASS__, 'handle_sync_courses'));

        // Get sync stats - retorna estatísticas de sincronização
        add_action('wp_ajax_eau_openlearning_get_sync_stats', array(__CLASS__, 'handle_get_sync_stats'));

        // Enroll in course - cria compra e redireciona para checkout (v1.71.0)
        add_action('wp_ajax_eau_openlearning_enroll_course', array(__CLASS__, 'handle_enroll_course'));
    }

    /**
     * Gera dados de SSO Launch para abrir OpenLearning
     *
     * Estratégia: SSO autentica e usa course_url para redirecionamento direto
     * O context_id com MongoDB ObjectId não funciona, então usamos redirect_url
     *
     * v1.71.0: Verifica se usuário tem acesso a cursos pagos antes de permitir SSO
     */
    public static function handle_sso_launch() {
        // Verifica nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'eau_openlearning_nonce')) {
            wp_send_json_error(array('message' => 'Invalid security token'));
        }

        // Verifica se usuário está logado
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'User not logged in'));
        }

        $user_id = get_current_user_id();
        $course_id = isset($_POST['course_id']) ? sanitize_text_field($_POST['course_id']) : null;

        // Busca course_url se course_id foi fornecido
        $course_url = null;
        if ($course_id) {
            $post_id = Eau_OpenLearning_Post_Type::get_by_course_id($course_id);
            if ($post_id) {
                $meta_prefix = Eau_OpenLearning_Post_Type::META_PREFIX;
                $course_url = get_post_meta($post_id, $meta_prefix . 'course_url', true);

                // v1.71.0: Verificar se curso é pago e se usuário tem acesso
                $price = (float) get_post_meta($post_id, $meta_prefix . 'price', true);
                if ($price > 0) {
                    // Verificar se usuário comprou o curso
                    if (!Eau_Course_Purchases::user_has_access($user_id, $post_id)) {
                        wp_send_json_error(array(
                            'message' => 'You need to purchase this course before accessing it.',
                            'error_code' => 'payment_required',
                        ));
                        return;
                    }
                }
            }
        }

        // Gera SSO launch data SEM context_id (usamos course_url para redirect)
        $result = Eau_OpenLearning_Service::generate_sso_launch($user_id, null);

        if ($result['success']) {
            $response_data = array(
                'launch_data' => $result['launch_data'],
            );

            // Se temos course_url, passa separadamente (NÃO adicionar aos params LTI pois invalida a assinatura OAuth)
            if ($course_url) {
                $response_data['redirect_url'] = $course_url;
            }

            wp_send_json_success($response_data);
        } else {
            wp_send_json_error(array(
                'message' => $result['error'],
                'error_code' => isset($result['error_code']) ? $result['error_code'] : 'unknown',
            ));
        }
    }

    /**
     * Retorna cursos disponíveis do Post Type
     */
    public static function handle_get_courses() {
        // Verifica nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'eau_openlearning_nonce')) {
            wp_send_json_error(array('message' => 'Invalid security token'));
        }

        // Verifica se usuário está logado
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'User not logged in'));
        }

        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : -1;
        $featured_only = isset($_POST['featured_only']) ? (bool) $_POST['featured_only'] : false;

        // Busca cursos do Post Type
        $courses = Eau_OpenLearning_Post_Type::get_visible_courses($limit, $featured_only);
        $total = Eau_OpenLearning_Post_Type::count_visible_courses();

        wp_send_json_success(array(
            'courses' => $courses,
            'total' => $total,
        ));
    }

    /**
     * Sincroniza catálogo de cursos (admin only)
     */
    public static function handle_sync_courses() {
        // Verifica nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'eau_openlearning_nonce')) {
            wp_send_json_error(array('message' => 'Invalid security token'));
        }

        // Verifica permissão (apenas admin)
        if (!Eau_User_Institution_Helper::has_admin_access()) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $check_availability = isset($_POST['check_availability']) ? (bool) $_POST['check_availability'] : false;

        // Executa sincronização
        $result = Eau_OpenLearning_Service::sync_course_catalog($check_availability);

        if ($result['success']) {
            wp_send_json_success(array(
                'message' => 'Courses synchronized successfully',
                'stats' => $result['stats'],
            ));
        } else {
            wp_send_json_error(array(
                'message' => $result['error'],
            ));
        }
    }

    /**
     * Retorna estatísticas de sincronização
     */
    public static function handle_get_sync_stats() {
        // Verifica nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'eau_openlearning_nonce')) {
            wp_send_json_error(array('message' => 'Invalid security token'));
        }

        // Verifica se usuário está logado
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'User not logged in'));
        }

        $stats = Eau_OpenLearning_Service::get_sync_stats();

        wp_send_json_success($stats);
    }

    /**
     * Processa enrollment de curso pago (v1.71.0)
     *
     * Cria purchase e redireciona para checkout
     */
    public static function handle_enroll_course() {
        // Verifica nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'eau_openlearning_nonce')) {
            wp_send_json_error(array('message' => 'Invalid security token'));
        }

        // Verifica se usuário está logado
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'User not logged in'));
        }

        $course_id = isset($_POST['course_id']) ? sanitize_text_field($_POST['course_id']) : null;

        if (!$course_id) {
            wp_send_json_error(array('message' => 'Course ID is required'));
        }

        // Busca post do curso
        $post_id = Eau_OpenLearning_Post_Type::get_by_course_id($course_id);

        if (!$post_id) {
            wp_send_json_error(array('message' => 'Course not found'));
        }

        $meta_prefix = Eau_OpenLearning_Post_Type::META_PREFIX;
        $user_id = get_current_user_id();

        // Obter preço do curso
        $price = (float) get_post_meta($post_id, $meta_prefix . 'price', true);

        // Se curso é gratuito, não precisa de checkout
        if ($price <= 0) {
            wp_send_json_success(array(
                'requires_payment' => false,
                'message' => 'This course is free. Click Access Course to continue.',
            ));
            return;
        }

        // Verificar se usuário já comprou o curso
        if (Eau_Course_Purchases::user_has_access($user_id, $post_id)) {
            wp_send_json_success(array(
                'requires_payment' => false,
                'already_purchased' => true,
                'message' => 'You already have access to this course.',
            ));
            return;
        }

        // Gerar referência para o pagamento
        $reference = 'course_' . $post_id . '_' . $user_id . '_' . time();

        // Criar purchase record
        $purchase_data = array(
            'user_id'   => $user_id,
            'course_id' => $post_id,
            'amount'    => $price,
            'currency'  => 'AUD',
            'reference' => $reference,
        );

        $purchase_id = Eau_Course_Purchases::create_purchase($purchase_data);

        if (is_wp_error($purchase_id)) {
            wp_send_json_error(array('message' => $purchase_id->get_error_message()));
            return;
        }

        if (!$purchase_id) {
            wp_send_json_error(array('message' => 'Failed to create purchase record'));
            return;
        }

        // Construir URL de checkout
        $checkout_page_id = Eau_Pages::get_page_id('checkout');
        $checkout_url = $checkout_page_id ? get_permalink($checkout_page_id) : home_url('/checkout/');

        $checkout_url = add_query_arg(array(
            'type'        => 'course',
            'purchase_id' => $purchase_id,
        ), $checkout_url);

        wp_send_json_success(array(
            'requires_payment' => true,
            'checkout_url'     => $checkout_url,
            'purchase_id'      => $purchase_id,
            'message'          => 'Redirecting to checkout...',
        ));
    }
}
