<?php
namespace EauSystem\Ajax;

use EauSystem\Eau_User_Institution_Helper;

/**
 * AJAX Handlers para Activities Management
 */
class Eau_Activities_Ajax {

    /**
     * Registra os handlers AJAX
     */
    public static function register_handlers() {
        // Lista activities
        add_action('wp_ajax_eau_get_activities', array(__CLASS__, 'get_activities'));

        // Delete activity
        add_action('wp_ajax_eau_delete_activity', array(__CLASS__, 'delete_activity'));

        // Bulk delete activities (apenas super admin)
        add_action('wp_ajax_eau_bulk_delete_activities', array(__CLASS__, 'bulk_delete_activities'));

        // Get filtered activity IDs (para delete all filtered)
        add_action('wp_ajax_eau_get_filtered_activity_ids', array(__CLASS__, 'get_filtered_activity_ids'));

        // Bulk delete activities batch (processamento em lotes)
        add_action('wp_ajax_eau_bulk_delete_activities_batch', array(__CLASS__, 'bulk_delete_activities_batch'));

        // Update activity
        add_action('wp_ajax_eau_update_activity', array(__CLASS__, 'update_activity'));

        // Get activity details
        add_action('wp_ajax_eau_get_activity_details', array(__CLASS__, 'get_activity_details'));

        // Create activity
        add_action('wp_ajax_eau_create_activity', array(__CLASS__, 'create_activity'));

        // Export CSV
        add_action('wp_ajax_eau_export_activities_csv', array(__CLASS__, 'export_activities_csv'));

        // Get stats
        add_action('wp_ajax_eau_get_activities_stats', array(__CLASS__, 'get_activities_stats'));

        // Get orphan activity IDs (para cleanup)
        add_action('wp_ajax_eau_get_orphan_activity_ids', array(__CLASS__, 'get_orphan_activity_ids'));
    }

    /**
     * AJAX: Get Activities (lista paginada com filtros)
     */
    public static function get_activities() {
        // Verifica nonce
        check_ajax_referer('eau_activities_nonce', 'nonce');

        // Pega parâmetros
        $page = isset($_POST['page']) ? absint($_POST['page']) : 1;
        $per_page = isset($_POST['per_page']) ? absint($_POST['per_page']) : 20;
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $member_id = isset($_POST['member']) ? absint($_POST['member']) : '';
        $institution_id = isset($_POST['institution']) ? absint($_POST['institution']) : '';
        $verified = isset($_POST['verified']) ? sanitize_text_field($_POST['verified']) : '';
        $date_from = isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : '';
        $date_to = isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : '';
        $orderby = isset($_POST['orderby']) ? sanitize_text_field($_POST['orderby']) : 'post_date';
        $order = isset($_POST['order']) ? sanitize_text_field($_POST['order']) : 'DESC';

        // Busca activities
        $result = self::query_activities(array(
            'posts_per_page' => $per_page,
            'paged' => $page,
            'search' => $search,
            'member_id' => $member_id,
            'institution_id' => $institution_id,
            'verified' => $verified,
            'date_from' => $date_from,
            'date_to' => $date_to,
            'orderby' => $orderby,
            'order' => $order,
        ));

        // Formata dados para a tabela
        $rows = array();
        foreach ($result['activities'] as $activity) {
            $rows[] = self::format_activity_row($activity);
        }

        wp_send_json_success(array(
            'rows' => $rows,
            'total' => $result['total'],
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => $result['total_pages'],
        ));
    }

    /**
     * Query activities usando WP_Query
     *
     * @param array $args Argumentos de busca
     * @return array Resultado com 'activities', 'total' e 'total_pages'
     */
    private static function query_activities($args = array()) {
        // Defaults
        $defaults = array(
            'posts_per_page' => 20,
            'paged' => 1,
            'search' => '',
            'member_id' => '',
            'institution_id' => '',
            'verified' => '',
            'date_from' => '',
            'date_to' => '',
            'orderby' => 'post_date',
            'order' => 'DESC',
        );
        $args = wp_parse_args($args, $defaults);

        // Campos que precisam de ordenação manual (meta fields ou calculados)
        $manual_sort_fields = array('member_name', 'institution_name', 'hours');
        $needs_manual_sort = in_array($args['orderby'], $manual_sort_fields);

        // Se há busca por membro, precisamos de processamento manual
        $search_member_ids = array();
        $has_member_search = false;

        if (!empty($args['search'])) {
            // Busca usuários pelo nome ou email
            $search_term = $args['search'];

            // Divide o termo de busca em palavras para busca mais flexível
            $search_words = preg_split('/\s+/', trim($search_term));

            $found_user_ids = array();

            foreach ($search_words as $word) {
                if (strlen($word) < 2) continue; // Ignora palavras muito curtas

                // Busca por nome (display_name, email, etc)
                $user_query_args = array(
                    'search' => '*' . $word . '*',
                    'search_columns' => array('user_login', 'user_email', 'user_nicename', 'display_name'),
                    'fields' => 'ID',
                    'number' => -1,
                );
                $users_by_search = get_users($user_query_args);

                // Busca também por first_name e last_name via meta
                $users_by_meta = get_users(array(
                    'fields' => 'ID',
                    'number' => -1,
                    'meta_query' => array(
                        'relation' => 'OR',
                        array(
                            'key' => 'first_name',
                            'value' => $word,
                            'compare' => 'LIKE',
                        ),
                        array(
                            'key' => 'last_name',
                            'value' => $word,
                            'compare' => 'LIKE',
                        ),
                    ),
                ));

                // Combina resultados
                $found_user_ids = array_merge($found_user_ids, $users_by_search, $users_by_meta);
            }

            // Remove duplicatas
            $found_user_ids = array_unique($found_user_ids);

            // Pega os mem_userid desses usuários
            if (!empty($found_user_ids)) {
                foreach ($found_user_ids as $uid) {
                    $mem_userid = get_user_meta($uid, 'mem_userid', true);
                    if (!empty($mem_userid)) {
                        $search_member_ids[] = $mem_userid;
                    }
                }
                $search_member_ids = array_unique($search_member_ids);
                $has_member_search = !empty($search_member_ids);
            }
        }

        // Build WP_Query arguments
        $query_args = array(
            'post_type' => 'activitie',
            'post_status' => 'publish',
            'order' => strtoupper($args['order']),
        );

        // Se precisa ordenação manual, busca todos os registros
        if ($needs_manual_sort) {
            $query_args['posts_per_page'] = -1;
            $query_args['nopaging'] = true;
            $query_args['orderby'] = 'ID';
        } else {
            $query_args['posts_per_page'] = $args['posts_per_page'];
            $query_args['paged'] = $args['paged'];
            $query_args['orderby'] = $args['orderby'];
        }

        // Se há busca, usamos duas abordagens:
        // 1. Se encontramos member_ids, filtramos por act_user_id
        // 2. Se não, buscamos no título
        // Como WP_Query não suporta facilmente OR entre meta e título,
        // vamos fazer duas queries separadas e combinar os resultados
        $use_combined_search = false;
        if (!empty($args['search'])) {
            if ($has_member_search) {
                $use_combined_search = true;
            } else {
                // Se não encontrou membros, busca apenas no título
                $query_args['s'] = $args['search'];
            }
        }

        // Inicializa meta_query
        $meta_query = array('relation' => 'AND');

        // Filtro de instituição - Institution Admin automaticamente filtra suas instituições
        $is_institution_admin = Eau_User_Institution_Helper::is_institution_admin();

        if ($is_institution_admin) {
            // Pega company_ids das instituições que o admin gerencia
            $company_ids = Eau_User_Institution_Helper::get_user_managed_company_ids(get_current_user_id());

            if (empty($company_ids)) {
                // Sem instituições: retorna vazio
                return array(
                    'activities' => array(),
                    'total' => 0,
                    'total_pages' => 0,
                );
            }

            // Se filtrou uma instituição específica, pega apenas o company_id dela
            if (!empty($args['institution_id'])) {
                $filtered_company_id = get_post_meta($args['institution_id'], 'ins_company_id', true);
                // Verifica se o admin tem acesso a essa instituição
                if (in_array($filtered_company_id, $company_ids)) {
                    $company_ids = array($filtered_company_id);
                } else {
                    // Admin não tem acesso a essa instituição
                    return array(
                        'activities' => array(),
                        'total' => 0,
                        'total_pages' => 0,
                    );
                }
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
                return array(
                    'activities' => array(),
                    'total' => 0,
                    'total_pages' => 0,
                );
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
                return array(
                    'activities' => array(),
                    'total' => 0,
                    'total_pages' => 0,
                );
            }

            // Filtra activities pelo act_user_id
            $meta_query[] = array(
                'key' => 'act_user_id',
                'value' => $act_user_ids,
                'compare' => 'IN',
            );
        } elseif (!empty($args['institution_id'])) {
            // Admin/Super Admin filtrando por instituição específica
            $company_id = get_post_meta($args['institution_id'], 'ins_company_id', true);
            $user_ids = get_users(array(
                'fields' => 'ID',
                'meta_query' => array(
                    array(
                        'key' => 'mem_membercompanyname',
                        'value' => $company_id,
                        'compare' => '=',
                    ),
                ),
            ));

            if (!empty($user_ids)) {
                // Pega os mem_userid desses usuários
                $act_user_ids = array();
                foreach ($user_ids as $user_id) {
                    $mem_userid = get_user_meta($user_id, 'mem_userid', true);
                    if (!empty($mem_userid)) {
                        $act_user_ids[] = $mem_userid;
                    }
                }

                if (!empty($act_user_ids)) {
                    $meta_query[] = array(
                        'key' => 'act_user_id',
                        'value' => $act_user_ids,
                        'compare' => 'IN',
                    );
                } else {
                    return array(
                        'activities' => array(),
                        'total' => 0,
                        'total_pages' => 0,
                    );
                }
            } else {
                return array(
                    'activities' => array(),
                    'total' => 0,
                    'total_pages' => 0,
                );
            }
        }

        // Filtro por membro específico
        if (!empty($args['member_id'])) {
            $member_mem_userid = get_user_meta($args['member_id'], 'mem_userid', true);
            if (!empty($member_mem_userid)) {
                $meta_query[] = array(
                    'key' => 'act_user_id',
                    'value' => $member_mem_userid,
                    'compare' => '=',
                );
            }
        }

        // Meta query para verified
        if ($args['verified'] !== '') {
            if ($args['verified'] === '1') {
                $meta_query[] = array(
                    'key' => 'act_verified',
                    'value' => '1',
                    'compare' => '=',
                );
            } else {
                $meta_query[] = array(
                    'relation' => 'OR',
                    array(
                        'key' => 'act_verified',
                        'compare' => 'NOT EXISTS',
                    ),
                    array(
                        'key' => 'act_verified',
                        'value' => '1',
                        'compare' => '!=',
                    ),
                );
            }
        }

        if (!empty($meta_query) && count($meta_query) > 1) {
            $query_args['meta_query'] = $meta_query;
        }

        // Date query
        if (!empty($args['date_from']) || !empty($args['date_to'])) {
            $date_query = array();

            if (!empty($args['date_from'])) {
                $date_query['after'] = $args['date_from'];
            }

            if (!empty($args['date_to'])) {
                $date_query['before'] = $args['date_to'];
            }

            $query_args['date_query'] = array($date_query);
        }

        // Se precisa busca combinada (título OU membro), fazemos duas queries
        if ($use_combined_search) {
            // Query 1: Busca por título
            $query_title = $query_args;
            $query_title['s'] = $args['search'];
            $query_title['posts_per_page'] = -1;
            $query_title['nopaging'] = true;
            $query_title['fields'] = 'ids';

            $title_query = new \WP_Query($query_title);
            $title_ids = $title_query->posts;

            // Query 2: Busca por member_ids
            $query_member = $query_args;
            $query_member['posts_per_page'] = -1;
            $query_member['nopaging'] = true;
            $query_member['fields'] = 'ids';
            $query_member['meta_query'][] = array(
                'key' => 'act_user_id',
                'value' => $search_member_ids,
                'compare' => 'IN',
            );

            $member_query = new \WP_Query($query_member);
            $member_ids = $member_query->posts;

            // Combina e remove duplicatas
            $all_ids = array_unique(array_merge($title_ids, $member_ids));
            $total = count($all_ids);

            // Aplica paginação manual
            $total_pages = ceil($total / $args['posts_per_page']);
            $offset = ($args['paged'] - 1) * $args['posts_per_page'];
            $page_ids = array_slice($all_ids, $offset, $args['posts_per_page']);

            if (empty($page_ids)) {
                $activities = array();
            } else {
                // Busca os posts completos apenas para a página atual
                $final_query = new \WP_Query(array(
                    'post_type' => 'activitie',
                    'post__in' => $page_ids,
                    'posts_per_page' => count($page_ids),
                    'orderby' => 'post__in',
                ));
                $activities = $final_query->posts;
            }
        } else {
            // Execute query normal
            $query = new \WP_Query($query_args);
            $activities = $query->posts;
            $total = $query->found_posts;
            $total_pages = isset($total_pages) ? $total_pages : $query->max_num_pages;
        }

        // Se precisa ordenação manual (meta fields ou calculados)
        if ($needs_manual_sort) {
            // Adiciona os valores necessários para ordenação
            foreach ($activities as $activity) {
                $author = get_userdata($activity->post_author);
                if ($author) {
                    $activity->member_name = trim($author->first_name . ' ' . $author->last_name);
                    if (empty($activity->member_name)) {
                        $activity->member_name = $author->display_name;
                    }
                    $activity->institution_name = Eau_User_Institution_Helper::get_user_institution_name($activity->post_author);
                }

                if ($args['orderby'] === 'hours') {
                    $activity->hours = floatval(get_post_meta($activity->ID, 'act_hours_of_pd_anything_below_60_minutes_can_be_entered_as_a_decimal_e_g_30_mins_0_5', true));
                }
            }

            // Ordena o array baseado no campo solicitado
            usort($activities, function($a, $b) use ($args) {
                $field = $args['orderby'];
                $val_a = isset($a->$field) ? $a->$field : '';
                $val_b = isset($b->$field) ? $b->$field : '';

                // Ordenação numérica para hours
                if ($field === 'hours') {
                    $comparison = $val_a - $val_b;
                } else {
                    // Ordenação alfabética para outros campos (case-insensitive)
                    $comparison = strcasecmp($val_a, $val_b);
                }

                return $args['order'] === 'DESC' ? -$comparison : $comparison;
            });
        }

        // Paginação manual (apenas quando precisa ordenação manual e não usou busca combinada)
        // Se usamos busca combinada, a paginação já foi feita acima
        if ($needs_manual_sort && !$use_combined_search) {
            $total = count($activities);
            $total_pages = ceil($total / $args['posts_per_page']);
            $offset = ($args['paged'] - 1) * $args['posts_per_page'];
            $activities = array_slice($activities, $offset, $args['posts_per_page']);
        } elseif (!$use_combined_search && !isset($total_pages)) {
            $total_pages = isset($query) ? $query->max_num_pages : 1;
        }

        return array(
            'activities' => $activities,
            'total' => $total,
            'total_pages' => $total_pages,
        );
    }

    /**
     * Formata os dados da activity para a tabela
     *
     * @param \WP_Post $post Post da activity
     * @return array Dados formatados
     */
    private static function format_activity_row($post) {
        // Activity title
        $activity_html = sprintf(
            '<div class="eau-activity-cell"><strong>%s</strong></div>',
            esc_html($post->post_title)
        );

        // Busca usuário pelo act_user_id da activity
        $act_user_id = get_post_meta($post->ID, 'act_user_id', true);
        $user_id = 0;
        $member_name = '-';

        if (!empty($act_user_id)) {
            // Busca usuário que tem esse mem_userid
            $users = get_users(array(
                'meta_key' => 'mem_userid',
                'meta_value' => $act_user_id,
                'number' => 1,
            ));

            if (!empty($users)) {
                $user = $users[0];
                $user_id = $user->ID;
                $full_name = trim($user->first_name . ' ' . $user->last_name);
                $member_name = !empty($full_name) ? $full_name : $user->display_name;
            }
        }

        $member_html = sprintf(
            '<span class="eau-member-name">%s</span>',
            esc_html($member_name)
        );

        // Institution
        $institution_name = '-';
        if ($user_id > 0) {
            $institution_name = Eau_User_Institution_Helper::get_user_institution_name($user_id);
        }
        $institution_html = sprintf(
            '<span class="eau-institution-name">%s</span>',
            esc_html($institution_name ?: '-')
        );

        // Hours/Points - Calcula pontos: horas × pontos_per_hour da categoria
        $hours = get_post_meta($post->ID, 'act_hours_of_pd_anything_below_60_minutes_can_be_entered_as_a_decimal_e_g_30_mins_0_5', true);
        $hours_float = $hours ? (float)$hours : 0;

        // Busca pontos da categoria
        $category_serial = get_post_meta($post->ID, 'act_category_serial', true);
        $points_per_hour = 0;
        $category_name = '';

        if (!empty($category_serial)) {
            global $wpdb;
            $table_categories = $wpdb->prefix . 'eau_activity_categories';

            $category = $wpdb->get_row($wpdb->prepare(
                "SELECT category_name, points_per_hour FROM {$table_categories} WHERE category_serial = %s",
                $category_serial
            ));

            if ($category) {
                $points_per_hour = (float) $category->points_per_hour;
                $category_name = $category->category_name;
            }
        } else {
            // DEBUG: Tenta buscar pela act_category (nome da categoria)
            $category_name = get_post_meta($post->ID, 'act_category', true);

            if (!empty($category_name)) {
                global $wpdb;
                $table_categories = $wpdb->prefix . 'eau_activity_categories';

                $category = $wpdb->get_row($wpdb->prepare(
                    "SELECT points_per_hour FROM {$table_categories} WHERE category_name = %s",
                    $category_name
                ));

                if ($category) {
                    $points_per_hour = (float) $category->points_per_hour;
                }
            }
        }

        $total_points = $hours_float * $points_per_hour;

        // Hours (apenas horas)
        $hours_html = sprintf(
            '<span class="eau-hours-badge">%s</span>',
            esc_html(number_format_i18n($hours_float, 2))
        );

        // Points (apenas pontos)
        $points_html = sprintf(
            '<span class="eau-points-badge">%s</span>',
            esc_html(number_format_i18n($total_points, 2))
        );

        // Category name
        $category_html = !empty($category_name)
            ? sprintf('<span class="eau-category-name">%s</span>', esc_html($category_name))
            : '<span class="eau-category-empty">-</span>';

        // Verified status
        $verified = get_post_meta($post->ID, 'act_verified', true);
        $is_verified = ($verified === '1');
        $status_class = $is_verified ? 'eau-status-badge-verified' : 'eau-status-badge-pending';
        $status_text = $is_verified ? 'Verified' : 'Pending';
        $status_html = sprintf(
            '<span class="eau-status-badge %s">%s</span>',
            esc_attr($status_class),
            esc_html($status_text)
        );

        // Date
        $date = get_the_date('Y-m-d H:i', $post->ID);
        $date_html = sprintf(
            '<span class="eau-activity-date">%s</span>',
            esc_html($date)
        );

        // Proof/Evidence - act_event_website_where_possible pode ser URL, attachment ID ou texto
        $proof_value = get_post_meta($post->ID, 'act_event_website_where_possible', true);
        $proof_data = self::process_proof_value($proof_value);

        return array(
            '_id' => $post->ID,
            'activity' => $activity_html,
            'member' => $member_html,
            'institution' => $institution_html,
            'category' => $category_html,
            'hours' => $hours_html,
            'points' => $points_html,
            'status' => $status_html,
            'date' => $date_html,
            'proof_type' => $proof_data['type'],
            'proof_url' => $proof_data['url'],
            'proof_text' => $proof_data['text'],
        );
    }

    /**
     * Processa o valor do comprovante e determina seu tipo
     *
     * @param string $proof_value Valor do campo act_event_website_where_possible
     * @return array Array com 'type' (url|text|null), 'url' e 'text'
     */
    private static function process_proof_value($proof_value) {
        $result = array(
            'type' => null,
            'url' => '',
            'text' => '',
        );

        if (empty($proof_value)) {
            return $result;
        }

        // Domínio base para URLs relativas
        $base_domain = 'https://www.englishaustralia.com.au';

        // 1. Verifica se é um attachment ID (número)
        if (is_numeric($proof_value)) {
            $attachment_url = wp_get_attachment_url($proof_value);
            if ($attachment_url) {
                $result['type'] = 'url';
                $result['url'] = $attachment_url;
                return $result;
            }
        }

        // 2. Verifica se é uma URL absoluta (começa com http:// ou https://)
        if (filter_var($proof_value, FILTER_VALIDATE_URL)) {
            $result['type'] = 'url';
            $result['url'] = $proof_value;
            return $result;
        }

        // 3. Verifica se é uma URL relativa (começa com /)
        if (strpos($proof_value, '/') === 0) {
            $result['type'] = 'url';
            $result['url'] = $base_domain . $proof_value;
            return $result;
        }

        // 4. Se não é nenhum dos anteriores, é texto
        $result['type'] = 'text';
        $result['text'] = $proof_value;
        return $result;
    }

    /**
     * AJAX: Delete Activity
     */
    public static function delete_activity() {
        check_ajax_referer('eau_activities_nonce', 'nonce');

        // Verifica permissão
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'You do not have permission to delete activities.'));
        }

        $activity_id = isset($_POST['activity_id']) ? absint($_POST['activity_id']) : 0;

        if (!$activity_id) {
            wp_send_json_error(array('message' => 'Invalid activity ID.'));
        }

        // Verifica se o post existe e é do tipo correto
        $post = get_post($activity_id);
        if (!$post || $post->post_type !== 'activitie') {
            wp_send_json_error(array('message' => 'Activity not found.'));
        }

        // Deleta activity (move para trash)
        $deleted = wp_trash_post($activity_id);

        if ($deleted) {
            // Invalida cache de stats
            \EauSystem\Eau_Activities_Stats_Cache::invalidate_cache(get_current_user_id());

            wp_send_json_success(array('message' => 'Activity deleted successfully.'));
        } else {
            wp_send_json_error(array('message' => 'Failed to delete activity.'));
        }
    }

    /**
     * AJAX: Get Activity Details
     */
    public static function get_activity_details() {
        check_ajax_referer('eau_activities_nonce', 'nonce');

        $activity_id = isset($_POST['activity_id']) ? absint($_POST['activity_id']) : 0;

        if (!$activity_id) {
            wp_send_json_error(array('message' => 'Invalid activity ID.'));
        }

        $post = get_post($activity_id);
        if (!$post || $post->post_type !== 'activitie') {
            wp_send_json_error(array('message' => 'Activity not found.'));
        }

        // Pega todos os meta fields
        $meta_fields = get_post_meta($activity_id);

        // Formata dados
        $activity = array(
            '_ID' => $post->ID,
            'post_title' => $post->post_title,
            'post_content' => $post->post_content,
            'post_date' => $post->post_date,
            'act_verified' => isset($meta_fields['act_verified'][0]) ? $meta_fields['act_verified'][0] : '0',
            'act_hours' => isset($meta_fields['act_hours_of_pd_anything_below_60_minutes_can_be_entered_as_a_decimal_e_g_30_mins_0_5'][0]) ? $meta_fields['act_hours_of_pd_anything_below_60_minutes_can_be_entered_as_a_decimal_e_g_30_mins_0_5'][0] : '',
            'act_user_id' => isset($meta_fields['act_user_id'][0]) ? $meta_fields['act_user_id'][0] : '',
        );

        // Busca dados do membro pelo act_user_id
        $act_user_id = $activity['act_user_id'];
        if (!empty($act_user_id)) {
            $users = get_users(array(
                'meta_key' => 'mem_userid',
                'meta_value' => $act_user_id,
                'number' => 1,
            ));

            if (!empty($users)) {
                $member = $users[0];
                $full_name = trim($member->first_name . ' ' . $member->last_name);
                $activity['member_id'] = $member->ID;
                $activity['member_name'] = !empty($full_name) ? $full_name : $member->display_name;
                $activity['member_email'] = $member->user_email;
            }
        }

        wp_send_json_success($activity);
    }

    /**
     * AJAX: Update Activity
     */
    public static function update_activity() {
        check_ajax_referer('eau_activities_nonce', 'nonce');

        // Verifica permissão
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'You do not have permission to edit activities.'));
        }

        $activity_id = isset($_POST['activity_id']) ? absint($_POST['activity_id']) : 0;
        $fields = isset($_POST['fields']) ? $_POST['fields'] : array();

        if (!$activity_id) {
            wp_send_json_error(array('message' => 'Invalid activity ID.'));
        }

        $post = get_post($activity_id);
        if (!$post || $post->post_type !== 'activitie') {
            wp_send_json_error(array('message' => 'Activity not found.'));
        }

        // Atualiza o post se title ou content foram alterados
        $post_data = array();
        if (isset($fields['post_title'])) {
            $post_data['ID'] = $activity_id;
            $post_data['post_title'] = sanitize_text_field($fields['post_title']);
        }
        if (isset($fields['post_content'])) {
            $post_data['ID'] = $activity_id;
            $post_data['post_content'] = wp_kses_post($fields['post_content']);
        }

        if (!empty($post_data)) {
            wp_update_post($post_data);
        }

        // Atualiza meta fields
        if (isset($fields['act_verified'])) {
            update_post_meta($activity_id, 'act_verified', sanitize_text_field($fields['act_verified']));
        }

        if (isset($fields['act_hours'])) {
            update_post_meta($activity_id, 'act_hours_of_pd_anything_below_60_minutes_can_be_entered_as_a_decimal_e_g_30_mins_0_5', sanitize_text_field($fields['act_hours']));
        }

        // Atualiza act_user_id se member_id foi enviado
        if (isset($fields['member_id'])) {
            $member_id = absint($fields['member_id']);
            if ($member_id > 0) {
                $mem_userid = get_user_meta($member_id, 'mem_userid', true);
                if (!empty($mem_userid)) {
                    update_post_meta($activity_id, 'act_user_id', $mem_userid);
                }
            }
        }

        // Invalida cache de stats
        \EauSystem\Eau_Activities_Stats_Cache::invalidate_cache(get_current_user_id());

        wp_send_json_success(array('message' => 'Activity updated successfully.'));
    }

    /**
     * AJAX: Create Activity
     */
    public static function create_activity() {
        check_ajax_referer('eau_activities_nonce', 'nonce');

        // Verifica permissão
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'You do not have permission to create activities.'));
        }

        // Pega dados
        $title = isset($_POST['post_title']) ? sanitize_text_field($_POST['post_title']) : '';
        $content = isset($_POST['post_content']) ? wp_kses_post($_POST['post_content']) : '';
        $member_id = isset($_POST['member_id']) ? absint($_POST['member_id']) : 0;
        $hours = isset($_POST['act_hours']) ? sanitize_text_field($_POST['act_hours']) : '';
        $verified = isset($_POST['act_verified']) ? sanitize_text_field($_POST['act_verified']) : '0';

        // Validação
        if (empty($title)) {
            wp_send_json_error(array('message' => 'Activity title is required.'));
        }

        if (empty($member_id)) {
            wp_send_json_error(array('message' => 'Member is required.'));
        }

        // Pega mem_userid do membro
        $mem_userid = get_user_meta($member_id, 'mem_userid', true);
        if (empty($mem_userid)) {
            wp_send_json_error(array('message' => 'Member does not have mem_userid.'));
        }

        // Cria o post
        $post_id = wp_insert_post(array(
            'post_type' => 'activitie',
            'post_title' => $title,
            'post_content' => $content,
            'post_status' => 'publish',
            'post_author' => 1, // Pode ser qualquer valor, não usamos mais post_author
        ));

        if (is_wp_error($post_id)) {
            wp_send_json_error(array('message' => 'Failed to create activity.'));
        }

        // Adiciona meta fields
        update_post_meta($post_id, 'act_user_id', $mem_userid);
        update_post_meta($post_id, 'act_verified', $verified);
        if (!empty($hours)) {
            update_post_meta($post_id, 'act_hours_of_pd_anything_below_60_minutes_can_be_entered_as_a_decimal_e_g_30_mins_0_5', $hours);
        }

        // Invalida cache de stats
        \EauSystem\Eau_Activities_Stats_Cache::invalidate_cache(get_current_user_id());

        wp_send_json_success(array(
            'message' => 'Activity created successfully.',
            'activity_id' => $post_id,
        ));
    }

    /**
     * AJAX: Export Activities CSV
     * Processa em lotes para evitar estouro de memória
     */
    public static function export_activities_csv() {
        check_ajax_referer('eau_activities_nonce', 'nonce');

        // Verifica permissão
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission to export activities.');
        }

        // Aumenta limites para exportação
        @set_time_limit(300);
        @ini_set('memory_limit', '512M');

        // Pega parâmetros
        $export_type = isset($_POST['export_type']) ? sanitize_text_field($_POST['export_type']) : 'all';
        $selected_ids = isset($_POST['selected_ids']) ? array_map('absint', $_POST['selected_ids']) : array();

        // Gera CSV
        $filename = 'activities-export-' . date('Y-m-d-H-i-s') . '.csv';

        // Headers para download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');

        // BOM para UTF-8 (Excel compatibility)
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        // Header
        fputcsv($output, array(
            'ID',
            'Title',
            'Member Name',
            'Member Email',
            'Institution',
            'Hours',
            'Verified',
            'Created',
        ));

        // Cache de instituições para evitar queries repetidas
        $institution_cache = array();

        // Processa em lotes de 500
        $batch_size = 500;
        $page = 1;
        $has_more = true;

        while ($has_more) {
            // Query args para este lote
            $query_args = array(
                'post_type' => 'activitie',
                'post_status' => 'publish',
                'posts_per_page' => $batch_size,
                'paged' => $page,
                'orderby' => 'ID',
                'order' => 'ASC',
                'fields' => 'ids', // Apenas IDs para economizar memória
            );

            if ($export_type === 'selected' && !empty($selected_ids)) {
                $query_args['post__in'] = $selected_ids;
                // Se selecionados, não precisa paginar
                $query_args['posts_per_page'] = -1;
                $has_more = false;
            }

            $query = new \WP_Query($query_args);
            $post_ids = $query->posts;

            if (empty($post_ids)) {
                $has_more = false;
                break;
            }

            // Processa cada post do lote
            foreach ($post_ids as $post_id) {
                $post = get_post($post_id);
                if (!$post) continue;

                $author_id = $post->post_author;
                $author = get_userdata($author_id);
                $member_name = '-';
                $member_email = '-';

                if ($author) {
                    $full_name = trim($author->first_name . ' ' . $author->last_name);
                    $member_name = !empty($full_name) ? $full_name : $author->display_name;
                    $member_email = $author->user_email;
                }

                // Usa cache de instituições
                if (!isset($institution_cache[$author_id])) {
                    $institution_cache[$author_id] = Eau_User_Institution_Helper::get_user_institution_name($author_id);
                }
                $institution = $institution_cache[$author_id];

                $hours = get_post_meta($post_id, 'act_hours_of_pd_anything_below_60_minutes_can_be_entered_as_a_decimal_e_g_30_mins_0_5', true);
                $verified = get_post_meta($post_id, 'act_verified', true);

                fputcsv($output, array(
                    $post_id,
                    $post->post_title,
                    $member_name,
                    $member_email,
                    $institution ?: '-',
                    $hours ?: '0',
                    ($verified === '1' ? 'Yes' : 'No'),
                    get_the_date('Y-m-d H:i:s', $post_id),
                ));

                // Limpa cache do post para liberar memória
                clean_post_cache($post_id);
            }

            // Verifica se há mais páginas antes de limpar
            $posts_count = count($post_ids);

            // Libera memória
            wp_reset_postdata();
            unset($post_ids, $query);

            // Verifica se há mais páginas
            if ($export_type !== 'selected') {
                if ($posts_count < $batch_size) {
                    $has_more = false;
                } else {
                    $page++;
                }
            }

            // Flush output buffer
            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();
        }

        fclose($output);
        exit;
    }

    /**
     * AJAX: Get Activities Stats (com cache)
     */
    public static function get_activities_stats() {
        check_ajax_referer('eau_activities_nonce', 'nonce');

        // Usa sistema de cache para melhor performance
        $stats = \EauSystem\Eau_Activities_Stats_Cache::get_stats(get_current_user_id());

        wp_send_json_success(array(
            'total' => (int) $stats['total'],
            'verified' => (int) $stats['verified'],
            'pending' => (int) $stats['pending'],
            'total_points' => number_format_i18n((float) $stats['total_hours'], 2),
        ));
    }

    /**
     * AJAX: Bulk Delete Activities (apenas super admin)
     */
    public static function bulk_delete_activities() {
        // Verifica nonce
        check_ajax_referer('eau_activities_nonce', 'nonce');

        // Verifica se é super admin
        if (!Eau_User_Institution_Helper::is_super_admin()) {
            wp_send_json_error(array(
                'message' => 'Permission denied. Only super admins can perform bulk deletions.'
            ));
        }

        // Pega IDs das atividades
        $activity_ids = isset($_POST['ids']) ? array_map('absint', $_POST['ids']) : array();

        if (empty($activity_ids)) {
            wp_send_json_error(array(
                'message' => 'No activities selected for deletion.'
            ));
        }

        $deleted_count = 0;
        $failed_count = 0;
        $failed_activities = array();

        foreach ($activity_ids as $post_id) {
            // Verifica se é uma atividade válida
            $post = get_post($post_id);
            if (!$post || $post->post_type !== 'activitie') {
                $failed_count++;
                $failed_activities[] = $post ? $post->post_title : "ID: $post_id";
                continue;
            }

            // Tenta deletar a atividade
            $deleted = wp_delete_post($post_id, true);

            if ($deleted) {
                $deleted_count++;
            } else {
                $failed_count++;
                $failed_activities[] = $post->post_title;
            }
        }

        // Prepara mensagem de resposta
        $message = sprintf(
            '%d activity(ies) deleted successfully.',
            $deleted_count
        );

        if ($failed_count > 0) {
            $message .= sprintf(
                ' %d activity(ies) could not be deleted: %s',
                $failed_count,
                implode(', ', $failed_activities)
            );
        }

        // Invalida TODOS os caches (bulk operation afeta múltiplos usuários)
        \EauSystem\Eau_Activities_Stats_Cache::invalidate_all();

        wp_send_json_success(array(
            'message' => $message,
            'deleted_count' => $deleted_count,
            'failed_count' => $failed_count,
        ));
    }

    /**
     * AJAX: Get Filtered Activity IDs (para Delete All Filtered)
     */
    public static function get_filtered_activity_ids() {
        // Verifica nonce
        check_ajax_referer('eau_activities_nonce', 'nonce');

        // Verifica se é super admin
        if (!Eau_User_Institution_Helper::is_super_admin()) {
            wp_send_json_error(array(
                'message' => 'Permission denied. Only super admins can perform this action.'
            ));
        }

        // Pega parâmetros de filtro
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $member_id = isset($_POST['member']) ? absint($_POST['member']) : '';
        $institution_id = isset($_POST['institution']) ? absint($_POST['institution']) : '';
        $verified = isset($_POST['verified']) ? sanitize_text_field($_POST['verified']) : '';
        $date_from = isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : '';
        $date_to = isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : '';

        // Busca TODOS os IDs (sem paginação)
        $result = self::query_activities(array(
            'posts_per_page' => -1, // Todos os resultados
            'paged' => 1,
            'search' => $search,
            'member_id' => $member_id,
            'institution_id' => $institution_id,
            'verified' => $verified,
            'date_from' => $date_from,
            'date_to' => $date_to,
        ));

        // Extrai apenas os IDs
        $activity_ids = array();
        foreach ($result['activities'] as $activity) {
            $activity_ids[] = $activity->ID;
        }

        wp_send_json_success(array(
            'ids' => $activity_ids,
            'total' => count($activity_ids),
        ));
    }

    /**
     * AJAX: Bulk Delete Activities Batch (processa em lotes de 50)
     */
    public static function bulk_delete_activities_batch() {
        // Verifica nonce
        check_ajax_referer('eau_activities_nonce', 'nonce');

        // Verifica se é super admin
        if (!Eau_User_Institution_Helper::is_super_admin()) {
            wp_send_json_error(array(
                'message' => 'Permission denied. Only super admins can perform bulk deletions.'
            ));
        }

        // Pega IDs das atividades (máximo 50 por vez)
        $activity_ids = isset($_POST['ids']) ? array_map('absint', $_POST['ids']) : array();
        $activity_ids = array_slice($activity_ids, 0, 50); // Limita a 50

        if (empty($activity_ids)) {
            wp_send_json_error(array(
                'message' => 'No activities provided for deletion.'
            ));
        }

        $deleted_count = 0;

        foreach ($activity_ids as $post_id) {
            // Verifica se é uma atividade válida
            $post = get_post($post_id);
            if (!$post || $post->post_type !== 'activitie') {
                continue;
            }

            // Deleta a atividade
            $deleted = wp_delete_post($post_id, true);

            if ($deleted) {
                $deleted_count++;
            }
        }

        // Invalida TODOS os caches (batch delete afeta múltiplos usuários)
        \EauSystem\Eau_Activities_Stats_Cache::invalidate_all();

        wp_send_json_success(array(
            'deleted_count' => $deleted_count,
        ));
    }

    /**
     * AJAX: Get Orphan Activity IDs
     * Retorna IDs de atividades cujo act_user_id não corresponde a nenhum membro existente
     */
    public static function get_orphan_activity_ids() {
        check_ajax_referer('eau_activities_nonce', 'nonce');

        // Verifica se é super admin ou admin
        if (!Eau_User_Institution_Helper::is_super_admin() && !Eau_User_Institution_Helper::has_admin_access()) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        global $wpdb;

        // Busca todas as atividades com act_user_id
        $activities = $wpdb->get_results("
            SELECT p.ID, pm.meta_value as act_user_id
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'activitie'
            AND p.post_status = 'publish'
            AND pm.meta_key = 'act_user_id'
            AND pm.meta_value != ''
            AND pm.meta_value IS NOT NULL
        ");

        // Busca todos os mem_userid válidos (de usuários existentes)
        $valid_user_ids = $wpdb->get_col("
            SELECT DISTINCT meta_value
            FROM {$wpdb->usermeta}
            WHERE meta_key = 'mem_userid'
            AND meta_value != ''
            AND meta_value IS NOT NULL
        ");

        // Encontra atividades órfãs
        $orphan_ids = array();
        foreach ($activities as $activity) {
            if (!in_array($activity->act_user_id, $valid_user_ids)) {
                $orphan_ids[] = (int) $activity->ID;
            }
        }

        wp_send_json_success(array(
            'ids' => $orphan_ids,
            'total' => count($orphan_ids),
        ));
    }
}
