<?php
namespace EauSystem\Ajax;

/**
 * AJAX Handlers para Institutions Management
 */
class Eau_Institutions_Ajax {

    /**
     * Registra os handlers AJAX
     */
    public static function register_handlers() {
        // Lista instituições
        add_action('wp_ajax_eau_get_institutions', array(__CLASS__, 'get_institutions'));

        // Delete institution
        add_action('wp_ajax_eau_delete_institution', array(__CLASS__, 'delete_institution'));

        // Bulk delete institutions (apenas super admin)
        add_action('wp_ajax_eau_bulk_delete_institutions', array(__CLASS__, 'bulk_delete_institutions'));

        // Update institution
        add_action('wp_ajax_eau_update_institution', array(__CLASS__, 'update_institution'));

        // Get institution details
        add_action('wp_ajax_eau_get_institution_details', array(__CLASS__, 'get_institution_details'));

        // Create institution
        add_action('wp_ajax_eau_create_institution', array(__CLASS__, 'create_institution'));

        // Export CSV
        add_action('wp_ajax_eau_export_institutions_csv', array(__CLASS__, 'export_institutions_csv'));

        // Upload file for institution logo
        add_action('wp_ajax_eau_upload_institution_logo', array(__CLASS__, 'upload_institution_logo'));
    }

    /**
     * AJAX: Get Institutions (lista paginada com filtros)
     */
    public static function get_institutions() {
        // Verifica nonce
        check_ajax_referer('eau_institutions_nonce', 'nonce');

        // Pega parâmetros
        $page = isset($_POST['page']) ? absint($_POST['page']) : 1;
        $per_page = isset($_POST['per_page']) ? absint($_POST['per_page']) : 20;
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
        $membership_type = isset($_POST['membership_type']) ? sanitize_text_field($_POST['membership_type']) : '';
        $institution_type = isset($_POST['institution_type']) ? sanitize_text_field($_POST['institution_type']) : '';
        $created_date_from = isset($_POST['created_date_from']) ? sanitize_text_field($_POST['created_date_from']) : '';
        $created_date_to = isset($_POST['created_date_to']) ? sanitize_text_field($_POST['created_date_to']) : '';
        $orderby = isset($_POST['orderby']) ? sanitize_text_field($_POST['orderby']) : 'title';
        $order = isset($_POST['order']) ? sanitize_text_field($_POST['order']) : 'ASC';

        // Busca instituições
        $result = self::query_institutions(array(
            'posts_per_page' => $per_page,
            'paged' => $page,
            'search' => $search,
            'status' => $status,
            'membership_type' => $membership_type,
            'institution_type' => $institution_type,
            'created_date_from' => $created_date_from,
            'created_date_to' => $created_date_to,
            'orderby' => $orderby,
            'order' => $order,
        ));

        // Formata dados para a tabela
        $rows = array();
        foreach ($result['institutions'] as $institution) {
            $rows[] = self::format_institution_row($institution);
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
     * Query instituições usando WP_Query
     *
     * @param array $args Argumentos de busca
     * @return array Resultado com 'institutions', 'total' e 'total_pages'
     */
    private static function query_institutions($args = array()) {
        // Defaults
        $defaults = array(
            'posts_per_page' => 20,
            'paged' => 1,
            'search' => '',
            'status' => '',
            'membership_type' => '',
            'institution_type' => '',
            'created_date_from' => '',
            'created_date_to' => '',
            'orderby' => 'title',
            'order' => 'ASC',
        );
        $args = wp_parse_args($args, $defaults);

        // Campos que precisam de ordenação manual (meta fields ou calculados)
        $manual_sort_fields = array('ins_company_name', 'ins_company_id', 'ins_company_email', 'ins_status', 'ins_type', 'members_count', 'ins_member_start_date', 'ins_member_expire_date');
        $needs_manual_sort = in_array($args['orderby'], $manual_sort_fields);

        // Build WP_Query arguments
        $query_args = array(
            'post_type' => 'institutions',
            'post_status' => 'publish',
            'order' => strtoupper($args['order']),
        );

        // Se precisa ordenação manual, busca TODOS os registros
        if ($needs_manual_sort) {
            $query_args['posts_per_page'] = -1;
            $query_args['nopaging'] = true;
            $query_args['orderby'] = 'ID'; // Ordenação padrão temporária
        } else {
            $query_args['posts_per_page'] = $args['posts_per_page'];
            $query_args['paged'] = $args['paged'];
            $query_args['orderby'] = $args['orderby'] === 'company_name' ? 'title' : $args['orderby'];
        }

        // Search - busca no título do post e nos meta fields
        if (!empty($args['search'])) {
            $query_args['s'] = $args['search'];
            // Também buscar em meta fields
            add_filter('posts_search', array(__CLASS__, 'extend_search_to_meta'), 10, 2);
        }

        // Meta query para status
        $meta_query = array('relation' => 'AND');

        if (!empty($args['status'])) {
            if ($args['status'] === 'active') {
                // Active: sem meta ou com meta = 'active'
                $meta_query[] = array(
                    'relation' => 'OR',
                    array(
                        'key' => 'ins_status',
                        'compare' => 'NOT EXISTS',
                    ),
                    array(
                        'key' => 'ins_status',
                        'value' => 'active',
                        'compare' => '=',
                    ),
                );
            } else {
                $meta_query[] = array(
                    'key' => 'ins_status',
                    'value' => $args['status'],
                    'compare' => '=',
                );
            }
        }

        // Filtro de membership_type (ins_membership_type - para tipo de membership da instituição)
        if (!empty($args['membership_type'])) {
            if ($args['membership_type'] === 'none') {
                // Instituições sem membership type definido
                $meta_query[] = array(
                    'relation' => 'OR',
                    array(
                        'key' => 'ins_membership_type',
                        'compare' => 'NOT EXISTS',
                    ),
                    array(
                        'key' => 'ins_membership_type',
                        'value' => '',
                        'compare' => '=',
                    ),
                );
            } else {
                $meta_query[] = array(
                    'key' => 'ins_membership_type',
                    'value' => $args['membership_type'],
                    'compare' => '=',
                );
            }
        }

        // Filtro de institution_type (ins_type - College ou Corporate affiliate)
        if (!empty($args['institution_type'])) {
            if ($args['institution_type'] === 'not_defined') {
                // Buscar onde ins_type está vazio ou não existe
                $meta_query[] = array(
                    'relation' => 'OR',
                    array(
                        'key' => 'ins_type',
                        'value' => '',
                        'compare' => '=',
                    ),
                    array(
                        'key' => 'ins_type',
                        'compare' => 'NOT EXISTS',
                    ),
                );
            } else {
                $meta_query[] = array(
                    'key' => 'ins_type',
                    'value' => $args['institution_type'],
                    'compare' => 'LIKE',
                );
            }
        }

        if (!empty($meta_query) && count($meta_query) > 1) {
            $query_args['meta_query'] = $meta_query;
        }

        // Date query
        if (!empty($args['created_date_from']) || !empty($args['created_date_to'])) {
            $date_query = array();

            if (!empty($args['created_date_from'])) {
                $date_query['after'] = $args['created_date_from'];
            }

            if (!empty($args['created_date_to'])) {
                $date_query['before'] = $args['created_date_to'];
            }

            $query_args['date_query'] = array($date_query);
        }

        // Execute query
        $query = new \WP_Query($query_args);

        // Remove filter after query
        if (!empty($args['search'])) {
            remove_filter('posts_search', array(__CLASS__, 'extend_search_to_meta'), 10);
        }

        $institutions = $query->posts;
        $total = $query->found_posts;

        // Se precisa ordenação manual (meta fields ou calculados)
        if ($needs_manual_sort) {
            // Adiciona os valores necessários para ordenação
            foreach ($institutions as $institution) {
                // Meta fields
                $institution->ins_company_name = get_post_meta($institution->ID, 'ins_company_name', true) ?: $institution->post_title;
                $institution->ins_company_id = get_post_meta($institution->ID, 'ins_company_id', true);
                $institution->ins_company_email = get_post_meta($institution->ID, 'ins_company_email', true);
                $institution->ins_status = get_post_meta($institution->ID, 'ins_status', true) ?: 'active';

                // Date fields for sorting
                $institution->ins_member_start_date = get_post_meta($institution->ID, 'ins_member_start_date', true) ?: '';
                $institution->ins_member_expire_date = get_post_meta($institution->ID, 'ins_member_expire_date', true) ?: '';

                // Campos calculados
                if ($args['orderby'] === 'members_count') {
                    $institution->members_count = self::count_institution_members($institution->ins_company_id);
                }
            }

            // Ordena o array baseado no campo solicitado
            usort($institutions, function($a, $b) use ($args) {
                $field = $args['orderby'];
                $val_a = isset($a->$field) ? $a->$field : '';
                $val_b = isset($b->$field) ? $b->$field : '';

                // Ordenação numérica para members_count
                if ($field === 'members_count') {
                    $comparison = $val_a - $val_b;
                } elseif ($field === 'ins_member_start_date' || $field === 'ins_member_expire_date') {
                    // Ordenação por data (converte para timestamp)
                    $time_a = !empty($val_a) ? strtotime($val_a) : 0;
                    $time_b = !empty($val_b) ? strtotime($val_b) : 0;
                    $comparison = $time_a - $time_b;
                } else {
                    // Ordenação alfabética para outros campos (case-insensitive)
                    $comparison = strcasecmp($val_a, $val_b);
                }

                return $args['order'] === 'DESC' ? -$comparison : $comparison;
            });

            // Paginação manual
            $total = count($institutions);
            $total_pages = ceil($total / $args['posts_per_page']);
            $offset = ($args['paged'] - 1) * $args['posts_per_page'];
            $institutions = array_slice($institutions, $offset, $args['posts_per_page']);
        } else {
            $total_pages = $query->max_num_pages;
        }

        return array(
            'institutions' => $institutions,
            'total' => $total,
            'total_pages' => $total_pages,
        );
    }

    /**
     * Estende a busca para incluir meta fields
     */
    public static function extend_search_to_meta($search, $query) {
        global $wpdb;

        if (empty($search) || !$query->is_main_query()) {
            return $search;
        }

        $search_term = $query->get('s');
        if (empty($search_term)) {
            return $search;
        }

        // Adiciona busca em meta fields
        $meta_search = $wpdb->prepare(
            " OR EXISTS (
                SELECT 1 FROM {$wpdb->postmeta} pm
                WHERE pm.post_id = {$wpdb->posts}.ID
                AND (
                    (pm.meta_key = 'ins_company_id' AND pm.meta_value LIKE %s)
                    OR (pm.meta_key = 'ins_company_name' AND pm.meta_value LIKE %s)
                    OR (pm.meta_key = 'ins_company_email' AND pm.meta_value LIKE %s)
                )
            )",
            '%' . $wpdb->esc_like($search_term) . '%',
            '%' . $wpdb->esc_like($search_term) . '%',
            '%' . $wpdb->esc_like($search_term) . '%'
        );

        $search = preg_replace('/\)\s*$/', $meta_search . ')', $search);

        return $search;
    }

    /**
     * Formata os dados da instituição para a tabela
     *
     * @param \WP_Post $post Post da instituição
     * @return array Dados formatados
     */
    private static function format_institution_row($post) {
        // Get meta fields
        $company_name = get_post_meta($post->ID, 'ins_company_name', true) ?: $post->post_title;
        $company_id = get_post_meta($post->ID, 'ins_company_id', true);
        $company_email = get_post_meta($post->ID, 'ins_company_email', true);
        $company_phone = get_post_meta($post->ID, 'ins_company_company_phone', true);
        $institution_status = get_post_meta($post->ID, 'ins_status', true) ?: 'active';
        $institution_type = get_post_meta($post->ID, 'ins_type', true) ?: '';

        // Nome da instituição
        $institution_html = sprintf(
            '<div class="eau-institution-cell"><strong>%s</strong></div>',
            esc_html($company_name)
        );

        // Código
        $code_html = sprintf(
            '<span class="eau-institution-code">%s</span>',
            esc_html($company_id ?: '-')
        );

        // Tipo
        $type_html = '-';
        if (!empty($institution_type)) {
            $type_class = $institution_type === 'College' ? 'eau-type-badge-college' : 'eau-type-badge-corporate';
            $type_html = sprintf(
                '<span class="eau-type-badge %s">%s</span>',
                esc_attr($type_class),
                esc_html($institution_type)
            );
        }

        // Contact (email e telefone)
        $contact_parts = array();
        if (!empty($company_email)) {
            $contact_parts[] = sprintf(
                '<a href="mailto:%s">%s</a>',
                esc_attr($company_email),
                esc_html($company_email)
            );
        }
        if (!empty($company_phone)) {
            $contact_parts[] = sprintf(
                '<a href="tel:%s">%s</a>',
                esc_attr($company_phone),
                esc_html($company_phone)
            );
        }
        $contact_html = !empty($contact_parts) ? implode('<br>', $contact_parts) : '-';

        // Número de membros (conta usuários com mem_membercompanyname = company_id)
        // Link que abre a single da instituição e rola para a seção de membros
        $members_count = self::count_institution_members($company_id);
        $members_url = home_url('/institution/' . $post->ID . '/#eau-members-section');
        $members_html = sprintf(
            '<a href="%s" target="_blank" class="eau-members-count" title="View members of this institution">%d</a>',
            esc_url($members_url),
            $members_count
        );

        // Start Date
        $start_date = get_post_meta($post->ID, 'ins_member_start_date', true);
        $start_date_html = '-';
        if (!empty($start_date)) {
            $start_date_html = esc_html(date('d/m/Y', strtotime($start_date)));
        }

        // Expire Date (com indicador de status)
        $expire_date = get_post_meta($post->ID, 'ins_member_expire_date', true);
        $expire_date_html = '-';
        if (!empty($expire_date)) {
            $expire_timestamp = strtotime($expire_date);
            $now = time();
            $days_until = floor(($expire_timestamp - $now) / (60 * 60 * 24));

            $expire_class = '';
            $expire_label = '';

            if ($days_until < 0) {
                $expire_class = 'eau-expire-expired';
                $expire_label = 'Expired';
            } elseif ($days_until <= 30) {
                $expire_class = 'eau-expire-critical';
                $expire_label = $days_until . 'd';
            } elseif ($days_until <= 60) {
                $expire_class = 'eau-expire-warning';
                $expire_label = $days_until . 'd';
            } elseif ($days_until <= 90) {
                $expire_class = 'eau-expire-attention';
                $expire_label = $days_until . 'd';
            }

            $expire_date_formatted = date('d/m/Y', $expire_timestamp);

            if (!empty($expire_class)) {
                $expire_date_html = sprintf(
                    '<span class="eau-expire-cell %s">%s <span class="eau-expire-indicator">%s</span></span>',
                    esc_attr($expire_class),
                    esc_html($expire_date_formatted),
                    esc_html($expire_label)
                );
            } else {
                $expire_date_html = esc_html($expire_date_formatted);
            }
        }

        // Status
        $status_lower = strtolower($institution_status);
        $status_class = $status_lower === 'active' ? 'eau-status-badge-active' : 'eau-status-badge-inactive';
        $status_html = sprintf(
            '<span class="eau-status-badge %s">%s</span>',
            esc_attr($status_class),
            esc_html(ucfirst($institution_status))
        );

        // Actions - os botões serão renderizados pelo JavaScript
        $actions_html = '';

        return array(
            '_id' => $post->ID,
            'institution' => $institution_html,
            'code' => $code_html,
            'type' => $type_html,
            'contact' => $contact_html,
            'members' => $members_html,
            'start_date' => $start_date_html,
            'expire_date' => $expire_date_html,
            'status' => $status_html,
            'actions' => $actions_html,
        );
    }

    /**
     * Conta quantos membros tem uma instituição
     *
     * @param string $company_code Código da instituição
     * @return int Número de membros
     */
    private static function count_institution_members($company_code) {
        if (empty($company_code)) {
            return 0;
        }

        $args = array(
            'meta_query' => array(
                array(
                    'key' => 'mem_membercompanyname',
                    'value' => $company_code,
                    'compare' => '=',
                ),
            ),
            'count_total' => true,
            'fields' => 'ID',
        );

        $user_query = new \WP_User_Query($args);
        return (int) $user_query->get_total();
    }

    /**
     * AJAX: Delete Institution
     */
    public static function delete_institution() {
        check_ajax_referer('eau_institutions_nonce', 'nonce');

        // Verifica permissão
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'You do not have permission to delete institutions.'));
        }

        $institution_id = isset($_POST['institution_id']) ? absint($_POST['institution_id']) : 0;

        if (!$institution_id) {
            wp_send_json_error(array('message' => 'Invalid institution ID.'));
        }

        // Verifica se o post existe e é do tipo correto
        $post = get_post($institution_id);
        if (!$post || $post->post_type !== 'institutions') {
            wp_send_json_error(array('message' => 'Institution not found.'));
        }

        // Verifica se tem membros associados
        $company_id = get_post_meta($institution_id, 'ins_company_id', true);
        $members_count = self::count_institution_members($company_id);

        if ($members_count > 0) {
            wp_send_json_error(array(
                'message' => sprintf(
                    'Cannot delete institution with %d member(s). Please remove all members first.',
                    $members_count
                )
            ));
        }

        // Deleta instituição (move para trash)
        $deleted = wp_trash_post($institution_id);

        if ($deleted) {
            wp_send_json_success(array('message' => 'Institution deleted successfully.'));
        } else {
            wp_send_json_error(array('message' => 'Failed to delete institution.'));
        }
    }

    /**
     * AJAX: Get Institution Details
     */
    public static function get_institution_details() {
        check_ajax_referer('eau_institutions_nonce', 'nonce');

        $institution_id = isset($_POST['institution_id']) ? absint($_POST['institution_id']) : 0;

        if (!$institution_id) {
            wp_send_json_error(array('message' => 'Invalid institution ID.'));
        }

        $post = get_post($institution_id);
        if (!$post || $post->post_type !== 'institutions') {
            wp_send_json_error(array('message' => 'Institution not found.'));
        }

        // Pega todos os meta fields
        $meta_fields = get_post_meta($institution_id);

        // Get logo - convert attachment ID to URL
        $logo_id = isset($meta_fields['ins_company_logo'][0]) ? $meta_fields['ins_company_logo'][0] : '';
        $logo_url = '';
        if (!empty($logo_id) && is_numeric($logo_id)) {
            $logo_url = wp_get_attachment_url($logo_id);
        } elseif (!empty($logo_id) && filter_var($logo_id, FILTER_VALIDATE_URL)) {
            $logo_url = $logo_id;
        }

        // Formata dados
        $institution = array(
            '_ID' => $post->ID,
            'post_title' => $post->post_title,
            'ins_company_id' => isset($meta_fields['ins_company_id'][0]) ? $meta_fields['ins_company_id'][0] : '',
            'ins_company_name' => isset($meta_fields['ins_company_name'][0]) ? $meta_fields['ins_company_name'][0] : '',
            'ins_type' => isset($meta_fields['ins_type'][0]) ? $meta_fields['ins_type'][0] : '',
            'ins_company_email' => isset($meta_fields['ins_company_email'][0]) ? $meta_fields['ins_company_email'][0] : '',
            'ins_company_company_phone' => isset($meta_fields['ins_company_company_phone'][0]) ? $meta_fields['ins_company_company_phone'][0] : '',
            'ins_company_company_address_line_1' => isset($meta_fields['ins_company_company_address_line_1'][0]) ? $meta_fields['ins_company_company_address_line_1'][0] : '',
            'ins_company_company_suburb' => isset($meta_fields['ins_company_company_suburb'][0]) ? $meta_fields['ins_company_company_suburb'][0] : '',
            'ins_company_company_state' => isset($meta_fields['ins_company_company_state'][0]) ? $meta_fields['ins_company_company_state'][0] : '',
            'ins_company_company_postcode' => isset($meta_fields['ins_company_company_postcode'][0]) ? $meta_fields['ins_company_company_postcode'][0] : '',
            'ins_company_company_country' => isset($meta_fields['ins_company_company_country'][0]) ? $meta_fields['ins_company_company_country'][0] : '',
            'ins_status' => isset($meta_fields['ins_status'][0]) ? $meta_fields['ins_status'][0] : 'active',
            'ins_company_logo' => $logo_id,
            'ins_company_logo_url' => $logo_url,
        );

        // Adiciona contagem de membros
        $company_id = $institution['ins_company_id'];
        $institution['members_count'] = self::count_institution_members($company_id);

        // Adiciona lista de membros (para o modal de View)
        $institution['members'] = self::get_institution_members($company_id);

        wp_send_json_success($institution);
    }

    /**
     * Obtém lista de membros de uma instituição
     *
     * @param string $company_code Código da instituição
     * @return array Lista de membros formatada
     */
    private static function get_institution_members($company_code) {
        if (empty($company_code)) {
            return array();
        }

        $args = array(
            'meta_query' => array(
                array(
                    'key' => 'mem_membercompanyname',
                    'value' => $company_code,
                    'compare' => '=',
                ),
            ),
            'number' => 50, // Limita a 50 membros para não sobrecarregar
            'orderby' => 'display_name',
            'order' => 'ASC',
        );

        $user_query = new \WP_User_Query($args);
        $users = $user_query->get_results();

        $members = array();
        foreach ($users as $user) {
            $first_name = get_user_meta($user->ID, 'first_name', true);
            $last_name = get_user_meta($user->ID, 'last_name', true);
            $full_name = trim($first_name . ' ' . $last_name);
            if (empty($full_name)) {
                $full_name = $user->display_name;
            }

            $mem_type = get_user_meta($user->ID, 'mem_type', true);
            $mem_status = get_user_meta($user->ID, 'mem_status', true) ?: 'active';

            $members[] = array(
                'id' => $user->ID,
                'name' => $full_name,
                'email' => $user->user_email,
                'type' => $mem_type,
                'status' => $mem_status,
            );
        }

        return $members;
    }

    /**
     * AJAX: Update Institution
     */
    public static function update_institution() {
        check_ajax_referer('eau_institutions_nonce', 'nonce');

        // Verifica permissão
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'You do not have permission to edit institutions.'));
        }

        $institution_id = isset($_POST['institution_id']) ? absint($_POST['institution_id']) : 0;
        $fields = isset($_POST['fields']) ? $_POST['fields'] : array();

        if (!$institution_id) {
            wp_send_json_error(array('message' => 'Invalid institution ID.'));
        }

        $post = get_post($institution_id);
        if (!$post || $post->post_type !== 'institutions') {
            wp_send_json_error(array('message' => 'Institution not found.'));
        }

        // Campos permitidos para atualização
        $allowed_fields = array(
            'ins_company_id',
            'ins_company_name',
            'ins_type',
            'ins_company_email',
            'ins_company_company_phone',
            'ins_company_company_address_line_1',
            'ins_company_company_suburb',
            'ins_company_company_state',
            'ins_company_company_postcode',
            'ins_company_company_country',
            'ins_status',
            'ins_company_logo',
        );

        $updated_count = 0;
        foreach ($fields as $key => $value) {
            if (in_array($key, $allowed_fields)) {
                // Sanitize based on field type
                if ($key === 'ins_company_email') {
                    $value = sanitize_email($value);
                } elseif ($key === 'ins_company_logo') {
                    // Logo is an attachment ID - must be integer
                    $value = absint($value);
                } else {
                    $value = sanitize_text_field($value);
                }
                update_post_meta($institution_id, $key, $value);
                $updated_count++;
            }
        }

        // Atualiza o título do post se company_name foi alterado
        if (isset($fields['ins_company_name'])) {
            wp_update_post(array(
                'ID' => $institution_id,
                'post_title' => sanitize_text_field($fields['ins_company_name']),
            ));
        }

        if ($updated_count > 0) {
            wp_send_json_success(array('message' => 'Institution updated successfully.'));
        } else {
            wp_send_json_error(array('message' => 'No valid fields to update.'));
        }
    }

    /**
     * AJAX: Create Institution
     */
    public static function create_institution() {
        check_ajax_referer('eau_institutions_nonce', 'nonce');

        // Verifica permissão
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'You do not have permission to create institutions.'));
        }

        // Pega dados
        $company_name = isset($_POST['ins_company_name']) ? sanitize_text_field($_POST['ins_company_name']) : '';
        $company_id = isset($_POST['ins_company_id']) ? sanitize_text_field($_POST['ins_company_id']) : '';
        $institution_type = isset($_POST['ins_type']) ? sanitize_text_field($_POST['ins_type']) : '';
        $email = isset($_POST['ins_company_email']) ? sanitize_email($_POST['ins_company_email']) : '';
        $phone = isset($_POST['ins_company_company_phone']) ? sanitize_text_field($_POST['ins_company_company_phone']) : '';
        $address = isset($_POST['ins_company_company_address_line_1']) ? sanitize_text_field($_POST['ins_company_company_address_line_1']) : '';
        $suburb = isset($_POST['ins_company_company_suburb']) ? sanitize_text_field($_POST['ins_company_company_suburb']) : '';
        $state = isset($_POST['ins_company_company_state']) ? sanitize_text_field($_POST['ins_company_company_state']) : '';
        $postcode = isset($_POST['ins_company_company_postcode']) ? sanitize_text_field($_POST['ins_company_company_postcode']) : '';
        $country = isset($_POST['ins_company_company_country']) ? sanitize_text_field($_POST['ins_company_company_country']) : '';
        $status = isset($_POST['ins_status']) ? sanitize_text_field($_POST['ins_status']) : 'active';

        // Validação
        if (empty($company_name)) {
            wp_send_json_error(array('message' => 'Institution name is required.'));
        }

        // Se código não foi fornecido, gera automaticamente
        if (empty($company_id)) {
            $company_id = self::generate_unique_institution_code();
        } else {
            // Se foi fornecido, verifica se já existe
            $existing_query = new \WP_Query(array(
                'post_type' => 'institutions',
                'post_status' => 'publish',
                'posts_per_page' => 1,
                'fields' => 'ids',
                'meta_query' => array(
                    array(
                        'key' => 'ins_company_id',
                        'value' => $company_id,
                        'compare' => '=',
                    ),
                ),
            ));

            if ($existing_query->found_posts > 0) {
                wp_send_json_error(array('message' => 'Institution code already exists.'));
            }
        }

        // Cria o post
        $post_id = wp_insert_post(array(
            'post_type' => 'institutions',
            'post_title' => $company_name,
            'post_status' => 'publish',
            'post_author' => get_current_user_id(),
        ));

        if (is_wp_error($post_id)) {
            wp_send_json_error(array('message' => 'Failed to create institution.'));
        }

        // Adiciona meta fields
        update_post_meta($post_id, 'ins_company_id', $company_id);
        update_post_meta($post_id, 'ins_company_name', $company_name);
        update_post_meta($post_id, 'ins_type', $institution_type);
        update_post_meta($post_id, 'ins_company_email', $email);
        update_post_meta($post_id, 'ins_company_company_phone', $phone);
        update_post_meta($post_id, 'ins_company_company_address_line_1', $address);
        update_post_meta($post_id, 'ins_company_company_suburb', $suburb);
        update_post_meta($post_id, 'ins_company_company_state', $state);
        update_post_meta($post_id, 'ins_company_company_postcode', $postcode);
        update_post_meta($post_id, 'ins_company_company_country', $country);
        update_post_meta($post_id, 'ins_status', $status);

        wp_send_json_success(array(
            'message' => 'Institution created successfully.',
            'institution_id' => $post_id,
        ));
    }

    /**
     * AJAX: Export Institutions CSV
     */
    public static function export_institutions_csv() {
        check_ajax_referer('eau_institutions_nonce', 'nonce');

        // Verifica permissão
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission to export institutions.');
        }

        // Pega parâmetros
        $export_type = isset($_POST['export_type']) ? sanitize_text_field($_POST['export_type']) : 'all';
        $selected_ids = isset($_POST['selected_ids']) ? array_map('absint', $_POST['selected_ids']) : array();

        // Busca instituições
        $query_args = array(
            'post_type' => 'institutions',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        );

        if ($export_type === 'selected' && !empty($selected_ids)) {
            $query_args['post__in'] = $selected_ids;
        }

        $query = new \WP_Query($query_args);
        $institutions = $query->posts;

        // Gera CSV
        $filename = 'institutions-export-' . date('Y-m-d-H-i-s') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        $output = fopen('php://output', 'w');

        // Header
        fputcsv($output, array(
            'ID',
            'Name',
            'Code',
            'Email',
            'Phone',
            'Address',
            'Suburb',
            'State',
            'Postcode',
            'Country',
            'Status',
            'Members',
            'Created',
        ));

        // Rows
        foreach ($institutions as $post) {
            $company_id = get_post_meta($post->ID, 'ins_company_id', true);
            $members_count = self::count_institution_members($company_id);

            fputcsv($output, array(
                $post->ID,
                get_post_meta($post->ID, 'ins_company_name', true) ?: $post->post_title,
                $company_id,
                get_post_meta($post->ID, 'ins_company_email', true),
                get_post_meta($post->ID, 'ins_company_company_phone', true),
                get_post_meta($post->ID, 'ins_company_company_address_line_1', true),
                get_post_meta($post->ID, 'ins_company_company_suburb', true),
                get_post_meta($post->ID, 'ins_company_company_state', true),
                get_post_meta($post->ID, 'ins_company_company_postcode', true),
                get_post_meta($post->ID, 'ins_company_company_country', true),
                get_post_meta($post->ID, 'ins_status', true) ?: 'active',
                $members_count,
                get_the_date('Y-m-d H:i:s', $post->ID),
            ));
        }

        fclose($output);
        exit;
    }

    /**
     * Gera um código único para instituição
     *
     * Formato: Número sequencial de 4 dígitos começando em 1001
     * Verifica se já existe antes de retornar
     *
     * @return string Código único gerado
     */
    private static function generate_unique_institution_code() {
        global $wpdb;

        // Busca o maior código numérico existente
        $max_code = $wpdb->get_var("
            SELECT MAX(CAST(pm.meta_value AS UNSIGNED))
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
            WHERE pm.meta_key = 'ins_company_id'
            AND p.post_type = 'institutions'
            AND p.post_status = 'publish'
            AND pm.meta_value REGEXP '^[0-9]+$'
        ");

        // Se não encontrou nenhum código, começa em 1000
        if (empty($max_code)) {
            $next_code = 1001;
        } else {
            $next_code = intval($max_code) + 1;
        }

        // Garante que o código tem pelo menos 4 dígitos
        $code = str_pad($next_code, 4, '0', STR_PAD_LEFT);

        // Verifica se o código gerado já existe (segurança extra)
        $exists = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
            WHERE pm.meta_key = 'ins_company_id'
            AND pm.meta_value = %s
            AND p.post_type = 'institutions'
            AND p.post_status = 'publish'
        ", $code));

        // Se por algum motivo já existe, tenta o próximo
        while ($exists > 0) {
            $next_code++;
            $code = str_pad($next_code, 4, '0', STR_PAD_LEFT);
            $exists = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(*)
                FROM {$wpdb->postmeta} pm
                INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                WHERE pm.meta_key = 'ins_company_id'
                AND pm.meta_value = %s
                AND p.post_type = 'institutions'
                AND p.post_status = 'publish'
            ", $code));
        }

        return $code;
    }

    /**
     * AJAX: Bulk Delete Institutions (apenas super admin)
     */
    public static function bulk_delete_institutions() {
        // Verifica nonce
        check_ajax_referer('eau_institutions_nonce', 'nonce');

        // Verifica se é super admin
        if (!\EauSystem\Eau_User_Institution_Helper::is_super_admin()) {
            wp_send_json_error(array(
                'message' => 'Permission denied. Only super admins can perform bulk deletions.'
            ));
        }

        // Pega IDs das instituições
        $institution_ids = isset($_POST['ids']) ? array_map('absint', $_POST['ids']) : array();

        if (empty($institution_ids)) {
            wp_send_json_error(array(
                'message' => 'No institutions selected for deletion.'
            ));
        }

        $deleted_count = 0;
        $failed_count = 0;
        $failed_institutions = array();

        foreach ($institution_ids as $post_id) {
            // Verifica se é uma instituição válida
            $post = get_post($post_id);
            if (!$post || $post->post_type !== 'institutions') {
                $failed_count++;
                $failed_institutions[] = $post ? $post->post_title : "ID: $post_id";
                continue;
            }

            // Tenta deletar a instituição
            $deleted = wp_delete_post($post_id, true);

            if ($deleted) {
                $deleted_count++;
            } else {
                $failed_count++;
                $failed_institutions[] = $post->post_title;
            }
        }

        // Prepara mensagem de resposta
        $message = sprintf(
            '%d institution(s) deleted successfully.',
            $deleted_count
        );

        if ($failed_count > 0) {
            $message .= sprintf(
                ' %d institution(s) could not be deleted: %s',
                $failed_count,
                implode(', ', $failed_institutions)
            );
        }

        wp_send_json_success(array(
            'message' => $message,
            'deleted_count' => $deleted_count,
            'failed_count' => $failed_count,
        ));
    }

    /**
     * AJAX: Upload file for institution logo
     *
     * @since 1.46.5
     */
    public static function upload_institution_logo() {
        // Verifica nonce específico da página Institutions Management
        check_ajax_referer('eau_institutions_nonce', 'nonce');

        // Verifica se está logado e tem permissão
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied. You must be an administrator to upload files.'));
        }

        // Verifica se há arquivo
        if (empty($_FILES['file'])) {
            wp_send_json_error(array('message' => 'No file uploaded.'));
        }

        // Inclui funções necessárias para upload
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        $file = $_FILES['file'];

        // Validação de tamanho (10MB max por padrão)
        $max_size = isset($_POST['max_size']) ? intval($_POST['max_size']) : 10 * 1024 * 1024;
        if ($file['size'] > $max_size) {
            wp_send_json_error(array(
                'message' => 'File is too large. Maximum size is ' . size_format($max_size) . '.'
            ));
        }

        // Validação de extensão (se fornecida)
        if (!empty($_POST['allowed_extensions'])) {
            $allowed = array_map('trim', explode(',', strtolower($_POST['allowed_extensions'])));
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if (!in_array($ext, $allowed)) {
                wp_send_json_error(array(
                    'message' => 'File type not allowed. Allowed types: ' . implode(', ', array_map('strtoupper', $allowed))
                ));
            }
        }

        // Upload do arquivo
        $upload = wp_handle_upload($file, array('test_form' => false));

        if (isset($upload['error'])) {
            wp_send_json_error(array('message' => $upload['error']));
        }

        // Cria attachment na Media Library
        $attachment = array(
            'post_mime_type' => $upload['type'],
            'post_title'     => sanitize_file_name(pathinfo($file['name'], PATHINFO_FILENAME)),
            'post_content'   => '',
            'post_status'    => 'inherit',
            'post_author'    => get_current_user_id(),
        );

        $attachment_id = wp_insert_attachment($attachment, $upload['file']);

        if (is_wp_error($attachment_id)) {
            wp_send_json_error(array('message' => 'Failed to create attachment.'));
        }

        // Gera metadata para o attachment
        $attachment_data = wp_generate_attachment_metadata($attachment_id, $upload['file']);
        wp_update_attachment_metadata($attachment_id, $attachment_data);

        // Retorna dados do arquivo
        wp_send_json_success(array(
            'id' => $attachment_id,
            'url' => $upload['url'],
            'filename' => basename($upload['file']),
            'type' => $upload['type'],
            'size' => $file['size'],
            'size_formatted' => size_format($file['size']),
        ));
    }
}
