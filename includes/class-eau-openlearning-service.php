<?php
namespace EauSystem;

/**
 * Serviço de integração com a API OpenLearning
 *
 * Gerencia:
 * - Provisionamento de usuários
 * - SSO (Single Sign-On) via LTI
 * - Sincronização de catálogo de cursos
 *
 * @since 1.41.0
 */
class Eau_OpenLearning_Service {

    /**
     * Configurações da API
     */
    private static $config = array(
        'institution_id' => 'english-australia',
        'api_key' => '681bbb338d4d83608d1d6114.c9323f76014106f3a8f6531f958b541a80f3ce39afc3d33244a09b27c6d075bd',
        'api_base_url' => 'https://api.openlearning.com/v2.2',
    );

    /**
     * Retorna configuração
     */
    public static function get_config($key = null) {
        if ($key) {
            return isset(self::$config[$key]) ? self::$config[$key] : null;
        }
        return self::$config;
    }

    /**
     * Faz requisição à API OpenLearning
     *
     * @param string $endpoint Endpoint relativo
     * @param string $method HTTP method (GET, POST, etc)
     * @param array $data Dados para enviar
     * @return array|WP_Error
     */
    private static function api_request($endpoint, $method = 'GET', $data = array()) {
        $url = self::$config['api_base_url'] . $endpoint;

        // Adiciona API key como query parameter
        $url = add_query_arg('api_key', self::$config['api_key'], $url);

        $args = array(
            'method' => $method,
            'timeout' => 30,
            'headers' => array(
                'Accept' => 'application/json',
            ),
        );

        if ($method === 'POST' && !empty($data)) {
            $args['headers']['Content-Type'] = 'application/x-www-form-urlencoded';
            $args['body'] = http_build_query($data);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);

        return array(
            'status_code' => $status_code,
            'body' => $decoded,
            'raw' => $body,
        );
    }

    /**
     * Provisiona um usuário no OpenLearning
     *
     * @param int $wp_user_id ID do usuário WordPress
     * @return array Array com success, openlearning_user_id, error
     */
    public static function provision_user($wp_user_id) {
        global $wpdb;

        // Verifica se já está provisionado
        $users_table = Eau_OpenLearning_Database::get_users_table();
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$users_table} WHERE wp_user_id = %d",
            $wp_user_id
        ));

        if ($existing) {
            return array(
                'success' => true,
                'openlearning_user_id' => $existing->openlearning_user_id,
                'already_provisioned' => true,
            );
        }

        // Pega dados do usuário WordPress
        $user = get_userdata($wp_user_id);
        if (!$user) {
            return array(
                'success' => false,
                'error' => 'User not found',
            );
        }

        // Monta dados para provisionamento
        $mem_userid = get_user_meta($wp_user_id, 'mem_userid', true);
        $external_id = !empty($mem_userid) ? $mem_userid : 'wp_' . $wp_user_id;

        $provision_data = array(
            'full_name' => $user->display_name,
            'external_institution_id' => $external_id,
            'primary_email_address' => $user->user_email,
            'send_password_email' => 'false',
            'send_welcome_email' => 'false',
        );

        // Faz requisição de provisionamento
        $endpoint = '/institutions/' . self::$config['institution_id'] . '/managed-users/';
        $response = self::api_request($endpoint, 'POST', $provision_data);

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => $response->get_error_message(),
            );
        }

        // Processa resposta
        $status_code = $response['status_code'];
        $body = $response['body'];

        // Sucesso - usuário criado
        if ($status_code === 200 || $status_code === 201) {
            $openlearning_user_id = isset($body['data']['id']) ? $body['data']['id'] : null;

            if ($openlearning_user_id) {
                // Salva no banco
                $wpdb->insert($users_table, array(
                    'wp_user_id' => $wp_user_id,
                    'openlearning_user_id' => $openlearning_user_id,
                    'external_institution_id' => $external_id,
                    'provisioned_at' => current_time('mysql'),
                ));

                return array(
                    'success' => true,
                    'openlearning_user_id' => $openlearning_user_id,
                    'newly_provisioned' => true,
                );
            }
        }

        // Usuário já existe (409 Conflict)
        if ($status_code === 409) {
            $existing_id = isset($body['data']['existing_user_id']) ? $body['data']['existing_user_id'] : null;

            if ($existing_id) {
                // Salva no banco local
                $wpdb->insert($users_table, array(
                    'wp_user_id' => $wp_user_id,
                    'openlearning_user_id' => $existing_id,
                    'external_institution_id' => $external_id,
                    'provisioned_at' => current_time('mysql'),
                ));

                return array(
                    'success' => true,
                    'openlearning_user_id' => $existing_id,
                    'already_existed' => true,
                );
            }
        }

        // Erro: usuário em outra instituição
        if ($status_code === 400 && isset($body['detail'])) {
            if (strpos($body['detail'], 'not managed by this institution') !== false) {
                return array(
                    'success' => false,
                    'error' => 'User email is registered with a different institution in OpenLearning. Please contact support.',
                    'error_code' => 'different_institution',
                );
            }
        }

        // Erro genérico
        return array(
            'success' => false,
            'error' => isset($body['detail']) ? $body['detail'] : 'Unknown error during provisioning',
            'status_code' => $status_code,
        );
    }

    /**
     * Gera dados de SSO (LTI Launch)
     *
     * @param int $wp_user_id ID do usuário WordPress
     * @param string|null $course_id ID do curso (opcional, para launch direto)
     * @return array Array com success, launch_data, error
     */
    public static function generate_sso_launch($wp_user_id, $course_id = null) {
        global $wpdb;

        // Primeiro, garante que usuário está provisionado
        $provision_result = self::provision_user($wp_user_id);

        if (!$provision_result['success']) {
            return $provision_result;
        }

        $openlearning_user_id = $provision_result['openlearning_user_id'];

        // Monta dados para SSO
        $sso_data = array();
        if ($course_id) {
            $sso_data['context_id'] = $course_id;
        }

        // Faz requisição de SSO
        $endpoint = '/institutions/' . self::$config['institution_id'] . '/managed-users/' . $openlearning_user_id . '/sign-on/';
        $response = self::api_request($endpoint, 'POST', $sso_data);

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => $response->get_error_message(),
            );
        }

        $status_code = $response['status_code'];
        $body = $response['body'];

        if ($status_code === 200 && isset($body['data'])) {
            // Atualiza contador de SSO
            $users_table = Eau_OpenLearning_Database::get_users_table();
            $wpdb->query($wpdb->prepare(
                "UPDATE {$users_table} SET last_sso_at = %s, sso_count = sso_count + 1 WHERE wp_user_id = %d",
                current_time('mysql'),
                $wp_user_id
            ));

            return array(
                'success' => true,
                'launch_data' => array(
                    'url' => $body['data']['url'],
                    'method' => $body['data']['method'],
                    'params' => $body['data']['params'],
                ),
            );
        }

        return array(
            'success' => false,
            'error' => isset($body['detail']) ? $body['detail'] : 'Failed to generate SSO launch data',
            'status_code' => $status_code,
        );
    }

    /**
     * Busca todos os cursos da instituição
     *
     * @return array Array com success, courses, error
     */
    public static function fetch_courses_from_api() {
        $endpoint = '/institutions/' . self::$config['institution_id'] . '/courses/';
        $response = self::api_request($endpoint, 'GET');

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => $response->get_error_message(),
            );
        }

        $status_code = $response['status_code'];
        $body = $response['body'];

        if ($status_code === 200 && isset($body['data'])) {
            return array(
                'success' => true,
                'courses' => $body['data'],
            );
        }

        return array(
            'success' => false,
            'error' => isset($body['detail']) ? $body['detail'] : 'Failed to fetch courses',
            'status_code' => $status_code,
        );
    }

    /**
     * Verifica se um curso está disponível (não retorna 404 ou mensagem de indisponível)
     *
     * @param string $course_url URL do curso
     * @return bool
     */
    public static function check_course_availability($course_url) {
        $response = wp_remote_get($course_url, array(
            'timeout' => 10,
            'redirection' => 5,
        ));

        if (is_wp_error($response)) {
            return false;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $body_lower = strtolower($body);

        // Verifica mensagens de indisponibilidade
        $unavailable_messages = array(
            'this course is currently unavailable',
            'course is not available',
            'course not found',
            'page not found',
        );

        foreach ($unavailable_messages as $message) {
            if (strpos($body_lower, $message) !== false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Sincroniza catálogo de cursos com o Post Type
     *
     * @param bool $check_availability Se deve verificar disponibilidade de cada curso
     * @return array Resultado da sincronização
     */
    public static function sync_course_catalog($check_availability = false) {
        // Busca cursos da API
        $api_result = self::fetch_courses_from_api();

        if (!$api_result['success']) {
            // Loga erro
            update_option('eau_openlearning_last_sync_error', array(
                'time' => current_time('mysql'),
                'error' => $api_result['error'],
            ));

            return $api_result;
        }

        $api_courses = $api_result['courses'];
        $stats = array(
            'total' => count($api_courses),
            'available' => 0,
            'unavailable' => 0,
            'new' => 0,
            'updated' => 0,
        );

        $meta_prefix = Eau_OpenLearning_Post_Type::META_PREFIX;
        $post_type = Eau_OpenLearning_Post_Type::POST_TYPE;

        // Processa cada curso
        foreach ($api_courses as $course) {
            $course_id = $course['id'];
            $course_url = isset($course['url']) ? $course['url'] : '';

            // Verifica disponibilidade (se solicitado)
            $is_available = true;
            if ($check_availability && !empty($course_url)) {
                $is_available = self::check_course_availability($course_url);
            }

            if ($is_available) {
                $stats['available']++;
            } else {
                $stats['unavailable']++;
                continue; // Pula cursos indisponíveis
            }

            // Verifica se curso já existe no Post Type
            $existing_post_id = Eau_OpenLearning_Post_Type::get_by_course_id($course_id);

            $course_title = isset($course['title']) ? $course['title'] : '';
            $course_description = isset($course['summary']) ? $course['summary'] : '';
            $course_image = isset($course['image']) ? $course['image'] : '';
            $course_price = isset($course['price']) ? floatval($course['price']) : 0;
            $course_self_paced = isset($course['self_paced']) ? ($course['self_paced'] ? 'true' : '') : 'true';
            $course_slug = isset($course['slug']) ? $course['slug'] : '';

            if ($existing_post_id) {
                // Atualiza post existente
                wp_update_post(array(
                    'ID' => $existing_post_id,
                    'post_title' => $course_title,
                    'post_content' => $course_description,
                ));

                // Atualiza meta fields
                update_post_meta($existing_post_id, $meta_prefix . 'course_url', $course_url);
                update_post_meta($existing_post_id, $meta_prefix . 'image_url', $course_image);
                update_post_meta($existing_post_id, $meta_prefix . 'price', $course_price);
                update_post_meta($existing_post_id, $meta_prefix . 'self_paced', $course_self_paced);
                update_post_meta($existing_post_id, $meta_prefix . 'slug', $course_slug);
                update_post_meta($existing_post_id, $meta_prefix . 'last_synced', current_time('mysql'));

                $stats['updated']++;
            } else {
                // Cria novo post
                $post_id = wp_insert_post(array(
                    'post_type' => $post_type,
                    'post_title' => $course_title,
                    'post_content' => $course_description,
                    'post_status' => 'publish',
                ));

                if ($post_id && !is_wp_error($post_id)) {
                    // Define meta fields
                    update_post_meta($post_id, $meta_prefix . 'course_id', $course_id);
                    update_post_meta($post_id, $meta_prefix . 'course_url', $course_url);
                    update_post_meta($post_id, $meta_prefix . 'image_url', $course_image);
                    update_post_meta($post_id, $meta_prefix . 'price', $course_price);
                    update_post_meta($post_id, $meta_prefix . 'self_paced', $course_self_paced);
                    update_post_meta($post_id, $meta_prefix . 'slug', $course_slug);
                    update_post_meta($post_id, $meta_prefix . 'is_visible', 'true'); // Visível por padrão (JetEngine switcher format)
                    update_post_meta($post_id, $meta_prefix . 'is_featured', ''); // Não destaque por padrão (JetEngine switcher format)
                    update_post_meta($post_id, $meta_prefix . 'display_order', 0);
                    update_post_meta($post_id, $meta_prefix . 'last_synced', current_time('mysql'));

                    $stats['new']++;
                }
            }
        }

        // Salva estatísticas da última sincronização
        update_option('eau_openlearning_last_sync', array(
            'time' => current_time('mysql'),
            'stats' => $stats,
        ));

        return array(
            'success' => true,
            'stats' => $stats,
        );
    }

    /**
     * Retorna cursos disponíveis do Post Type
     *
     * @param array $args Argumentos de filtro
     * @return array
     */
    public static function get_available_courses($args = array()) {
        $defaults = array(
            'limit' => -1,
            'featured_only' => false,
        );

        $args = wp_parse_args($args, $defaults);

        return Eau_OpenLearning_Post_Type::get_visible_courses($args['limit'], $args['featured_only']);
    }

    /**
     * Conta cursos disponíveis
     *
     * @return int
     */
    public static function count_available_courses() {
        return Eau_OpenLearning_Post_Type::count_visible_courses();
    }

    /**
     * Verifica se precisa sincronizar (última sincronização há mais de 24h)
     *
     * @return bool
     */
    public static function needs_sync() {
        $last_sync = get_option('eau_openlearning_last_sync');

        if (!$last_sync || !isset($last_sync['time'])) {
            return true;
        }

        $last_sync_time = strtotime($last_sync['time']);
        $hours_since_sync = (time() - $last_sync_time) / 3600;

        return $hours_since_sync >= 24;
    }

    /**
     * Retorna estatísticas de sincronização
     *
     * @return array
     */
    public static function get_sync_stats() {
        $last_sync = get_option('eau_openlearning_last_sync');
        $total_courses = Eau_OpenLearning_Post_Type::count_visible_courses();

        return array(
            'last_sync' => $last_sync ? $last_sync['time'] : null,
            'total_courses' => $total_courses,
            'available_courses' => $total_courses,
            'needs_sync' => self::needs_sync(),
            'stats' => $last_sync ? $last_sync['stats'] : null,
        );
    }

    /**
     * Agenda sincronização via WP Cron
     */
    public static function setup_cron() {
        if (!wp_next_scheduled('eau_openlearning_sync_courses')) {
            wp_schedule_event(time(), 'twicedaily', 'eau_openlearning_sync_courses');
        }
    }

    /**
     * Remove agendamento do WP Cron
     */
    public static function clear_cron() {
        $timestamp = wp_next_scheduled('eau_openlearning_sync_courses');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'eau_openlearning_sync_courses');
        }
    }

    /**
     * Handler do WP Cron para sincronização
     */
    public static function cron_sync_handler() {
        self::sync_course_catalog(false);
    }
}
