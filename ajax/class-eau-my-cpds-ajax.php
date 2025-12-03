<?php
namespace EauSystem\Ajax;

use EauSystem\Eau_My_Cpds;

/**
 * AJAX Handlers para My CPDs
 *
 * @since 1.37.0
 */
class Eau_My_Cpds_Ajax {

    /**
     * Registra os handlers AJAX
     */
    public static function register_handlers() {
        // Lista atividades do usuário
        add_action('wp_ajax_eau_get_my_cpds', array(__CLASS__, 'get_my_cpds'));

        // Pega progresso CPD
        add_action('wp_ajax_eau_get_my_cpd_progress', array(__CLASS__, 'get_my_cpd_progress'));

        // Cria nova atividade (Add Activity)
        add_action('wp_ajax_eau_create_my_activity', array(__CLASS__, 'create_my_activity'));

        // Edita atividade (category e proof)
        add_action('wp_ajax_eau_update_my_activity', array(__CLASS__, 'update_my_activity'));

        // Deleta atividade
        add_action('wp_ajax_eau_delete_my_activity', array(__CLASS__, 'delete_my_activity'));

        // Pega dados de uma atividade para edição
        add_action('wp_ajax_eau_get_my_activity', array(__CLASS__, 'get_my_activity'));

        // File upload handlers
        add_action('wp_ajax_eau_upload_file', array(__CLASS__, 'upload_file'));
        add_action('wp_ajax_eau_get_user_files', array(__CLASS__, 'get_user_files'));
    }

    /**
     * AJAX: Get My CPDs (lista paginada com filtros)
     */
    public static function get_my_cpds() {
        // Verifica nonce
        check_ajax_referer('eau_my_cpds_nonce', 'nonce');

        // Verifica se está logado
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'You must be logged in to view your CPDs.'));
        }

        $user_id = get_current_user_id();
        $mem_userid = get_user_meta($user_id, 'mem_userid', true);

        if (empty($mem_userid)) {
            wp_send_json_success(array(
                'rows' => array(),
                'total' => 0,
                'page' => 1,
                'per_page' => 20,
                'total_pages' => 0,
            ));
            return;
        }

        // Pega parâmetros
        $page = isset($_POST['page']) ? absint($_POST['page']) : 1;
        $per_page = isset($_POST['per_page']) ? absint($_POST['per_page']) : 20;
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $year = isset($_POST['year']) ? absint($_POST['year']) : date('Y');
        $category = isset($_POST['category']) ? sanitize_text_field($_POST['category']) : '';
        $orderby = isset($_POST['orderby']) ? sanitize_text_field($_POST['orderby']) : 'post_date';
        $order = isset($_POST['order']) ? sanitize_text_field($_POST['order']) : 'DESC';

        // Busca atividades
        $result = self::query_user_activities(array(
            'mem_userid' => $mem_userid,
            'posts_per_page' => $per_page,
            'paged' => $page,
            'search' => $search,
            'year' => $year,
            'category' => $category,
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
     * AJAX: Get My CPD Progress
     */
    public static function get_my_cpd_progress() {
        // Verifica nonce
        check_ajax_referer('eau_my_cpds_nonce', 'nonce');

        // Verifica se está logado
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'You must be logged in.'));
        }

        $user_id = get_current_user_id();
        $year = isset($_POST['year']) ? absint($_POST['year']) : date('Y');

        $progress = Eau_My_Cpds::get_user_cpd_progress($user_id, $year);
        $goal = Eau_My_Cpds::CPD_ANNUAL_GOAL;
        $percentage = min(100, ($progress['total_points'] / $goal) * 100);

        wp_send_json_success(array(
            'total_points' => number_format($progress['total_points'], 2),
            'points_remaining' => number_format(max(0, $goal - $progress['total_points']), 2),
            'activities_count' => $progress['activities_count'],
            'verified_count' => $progress['verified_count'],
            'pending_count' => $progress['pending_count'],
            'percentage' => number_format($percentage, 1),
            'goal' => $goal,
            'goal_reached' => $percentage >= 100,
        ));
    }

    /**
     * AJAX: Create My Activity (Add Activity form)
     *
     * @since 1.38.0
     */
    public static function create_my_activity() {
        // Verifica nonce
        check_ajax_referer('eau_my_cpds_nonce', 'nonce');

        // Verifica se está logado
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'You must be logged in to create activities.'));
        }

        $user_id = get_current_user_id();
        $mem_userid = get_user_meta($user_id, 'mem_userid', true);

        // Se não tiver mem_userid, gera um baseado no WordPress User ID
        if (empty($mem_userid)) {
            // Gera um mem_userid único baseado no timestamp e user ID
            $mem_userid = 'MEM' . str_pad($user_id, 6, '0', STR_PAD_LEFT);
            update_user_meta($user_id, 'mem_userid', $mem_userid);
        }

        // Pega e valida campos obrigatórios
        $activity_name = isset($_POST['activity_name']) ? sanitize_text_field($_POST['activity_name']) : '';
        $category_serial = isset($_POST['category_serial']) ? sanitize_text_field($_POST['category_serial']) : '';
        $hours = isset($_POST['hours']) ? floatval($_POST['hours']) : 0;
        $completed_date = isset($_POST['completed_date']) ? sanitize_text_field($_POST['completed_date']) : '';

        // Validações
        if (empty($activity_name)) {
            wp_send_json_error(array('message' => 'Activity name is required.'));
        }

        if (empty($category_serial)) {
            wp_send_json_error(array('message' => 'Category is required.'));
        }

        if ($hours <= 0) {
            wp_send_json_error(array('message' => 'Hours must be greater than 0.'));
        }

        if (empty($completed_date)) {
            wp_send_json_error(array('message' => 'Completion date is required.'));
        }

        // Campos opcionais
        $description = isset($_POST['description']) ? wp_kses_post($_POST['description']) : '';
        $proof_value = isset($_POST['proof']) ? sanitize_text_field($_POST['proof']) : '';
        $proof_type = isset($_POST['proof_type']) ? sanitize_text_field($_POST['proof_type']) : '';

        // Busca dados da categoria
        global $wpdb;
        $table_categories = $wpdb->prefix . 'eau_activity_categories';

        $category = $wpdb->get_row($wpdb->prepare(
            "SELECT category_name, points_per_hour FROM {$table_categories} WHERE category_serial = %s",
            $category_serial
        ));

        if (!$category) {
            wp_send_json_error(array('message' => 'Invalid category selected.'));
        }

        // Converte data do formato dd/mm/yyyy ou yyyy-mm-dd para Y-m-d H:i:s
        $date_obj = null;
        if (strpos($completed_date, '/') !== false) {
            // Formato dd/mm/yyyy
            $date_obj = \DateTime::createFromFormat('d/m/Y', $completed_date);
        } else {
            // Formato yyyy-mm-dd
            $date_obj = \DateTime::createFromFormat('Y-m-d', $completed_date);
        }

        if (!$date_obj) {
            wp_send_json_error(array('message' => 'Invalid date format.'));
        }

        $post_date = $date_obj->format('Y-m-d H:i:s');

        // Cria o post (activity)
        $post_data = array(
            'post_title'   => $activity_name,
            'post_content' => $description,
            'post_type'    => 'activitie',
            'post_status'  => 'publish',
            'post_date'    => $post_date,
            'post_date_gmt'=> get_gmt_from_date($post_date),
        );

        $post_id = wp_insert_post($post_data);

        if (is_wp_error($post_id)) {
            wp_send_json_error(array('message' => 'Failed to create activity: ' . $post_id->get_error_message()));
        }

        // Salva meta fields
        update_post_meta($post_id, 'act_user_id', $mem_userid);
        update_post_meta($post_id, 'act_category_serial', $category_serial);
        update_post_meta($post_id, 'act_category', $category->category_name);
        update_post_meta($post_id, 'act_hours_of_pd_anything_below_60_minutes_can_be_entered_as_a_decimal_e_g_30_mins_0_5', $hours);
        update_post_meta($post_id, 'act_completed_date', $date_obj->format('Y-m-d'));
        // Define status de verificação baseado na configuração do sistema
        $is_auto_approval = \EauSystem\Eau_Settings::is_auto_approval();
        update_post_meta($post_id, 'act_verified', $is_auto_approval ? '1' : '0');

        // Proof/Evidence
        $has_proof = !empty($proof_value);
        update_post_meta($post_id, 'act_supply_evidence_e_g_attendance_statement', $has_proof ? '1' : '0');

        if ($has_proof) {
            update_post_meta($post_id, 'act_event_website_where_possible', $proof_value);
        }

        // Calcula pontos
        $points = $hours * floatval($category->points_per_hour);

        wp_send_json_success(array(
            'message' => 'Activity created successfully!',
            'post_id' => $post_id,
            'points' => number_format($points, 2),
        ));
    }

    /**
     * Query atividades do usuário
     *
     * @param array $args Argumentos de busca
     * @return array Resultado com 'activities', 'total' e 'total_pages'
     */
    private static function query_user_activities($args = array()) {
        global $wpdb;

        // Defaults
        $defaults = array(
            'mem_userid' => '',
            'posts_per_page' => 20,
            'paged' => 1,
            'search' => '',
            'year' => date('Y'),
            'category' => '',
            'orderby' => 'post_date',
            'order' => 'DESC',
        );
        $args = wp_parse_args($args, $defaults);

        if (empty($args['mem_userid'])) {
            return array(
                'activities' => array(),
                'total' => 0,
                'total_pages' => 0,
            );
        }

        // Campos que precisam de ordenação manual
        $manual_sort_fields = array('hours', 'points', 'category_name');
        $needs_manual_sort = in_array($args['orderby'], $manual_sort_fields);

        // Build WP_Query arguments
        $query_args = array(
            'post_type' => 'activitie',
            'post_status' => 'publish',
            'order' => strtoupper($args['order']),
        );

        // Se precisa ordenação manual, busca TODOS os registros
        if ($needs_manual_sort) {
            $query_args['posts_per_page'] = -1;
            $query_args['nopaging'] = true;
            $query_args['orderby'] = 'ID';
        } else {
            $query_args['posts_per_page'] = $args['posts_per_page'];
            $query_args['paged'] = $args['paged'];
            $query_args['orderby'] = $args['orderby'];
        }

        // Search - busca no título
        if (!empty($args['search'])) {
            $query_args['s'] = $args['search'];
        }

        // Meta query para act_user_id
        $meta_query = array(
            'relation' => 'AND',
            array(
                'key' => 'act_user_id',
                'value' => $args['mem_userid'],
                'compare' => '=',
            ),
        );

        // Filtro por categoria
        if (!empty($args['category'])) {
            $meta_query[] = array(
                'key' => 'act_category_serial',
                'value' => $args['category'],
                'compare' => '=',
            );
        }

        $query_args['meta_query'] = $meta_query;

        // Date query para filtrar por ano
        if (!empty($args['year'])) {
            $query_args['date_query'] = array(
                array(
                    'year' => $args['year'],
                ),
            );
        }

        // Execute query
        $query = new \WP_Query($query_args);

        $activities = $query->posts;
        $total = $query->found_posts;

        // Se precisa ordenação manual
        if ($needs_manual_sort) {
            $table_categories = $wpdb->prefix . 'eau_activity_categories';

            // Adiciona os valores necessários para ordenação
            foreach ($activities as $activity) {
                // Hours
                $activity->hours = floatval(get_post_meta($activity->ID, 'act_hours_of_pd_anything_below_60_minutes_can_be_entered_as_a_decimal_e_g_30_mins_0_5', true));

                // Category
                $category_serial = get_post_meta($activity->ID, 'act_category_serial', true);
                $activity->category_name = '';
                $activity->points_per_hour = 1;

                if (!empty($category_serial)) {
                    $category = $wpdb->get_row($wpdb->prepare(
                        "SELECT category_name, points_per_hour FROM {$table_categories} WHERE category_serial = %s",
                        $category_serial
                    ));

                    if ($category) {
                        $activity->category_name = $category->category_name;
                        $activity->points_per_hour = (float) $category->points_per_hour;
                    }
                } else {
                    // Fallback para act_category
                    $activity->category_name = get_post_meta($activity->ID, 'act_category', true);
                }

                // Points
                $activity->points = $activity->hours * $activity->points_per_hour;
            }

            // Ordena o array baseado no campo solicitado
            usort($activities, function($a, $b) use ($args) {
                $field = $args['orderby'];
                $val_a = isset($a->$field) ? $a->$field : '';
                $val_b = isset($b->$field) ? $b->$field : '';

                // Ordenação numérica para hours e points
                if (in_array($field, array('hours', 'points'))) {
                    $comparison = $val_a - $val_b;
                } else {
                    // Ordenação alfabética para outros campos (case-insensitive)
                    $comparison = strcasecmp($val_a, $val_b);
                }

                return $args['order'] === 'DESC' ? -$comparison : $comparison;
            });

            // Paginação manual
            $total = count($activities);
            $total_pages = ceil($total / $args['posts_per_page']);
            $offset = ($args['paged'] - 1) * $args['posts_per_page'];
            $activities = array_slice($activities, $offset, $args['posts_per_page']);
        } else {
            $total_pages = $query->max_num_pages;
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
        global $wpdb;

        // Activity title
        $activity_html = sprintf(
            '<div class="eau-activity-cell"><strong>%s</strong></div>',
            esc_html($post->post_title)
        );

        // Hours/Points - Calcula pontos: horas × pontos_per_hour da categoria
        $hours = get_post_meta($post->ID, 'act_hours_of_pd_anything_below_60_minutes_can_be_entered_as_a_decimal_e_g_30_mins_0_5', true);
        $hours_float = $hours ? (float)$hours : 0;

        // Busca pontos da categoria
        $category_serial = get_post_meta($post->ID, 'act_category_serial', true);
        $points_per_hour = 1; // Default
        $category_name = '';

        if (!empty($category_serial)) {
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
            // Fallback para act_category
            $category_name = get_post_meta($post->ID, 'act_category', true);

            if (!empty($category_name)) {
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

        // Hours
        $hours_html = sprintf(
            '<span class="eau-hours-badge">%s</span>',
            esc_html(number_format_i18n($hours_float, 2))
        );

        // Points
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
        $date = get_the_date('M j, Y', $post->ID);
        $date_html = sprintf(
            '<span class="eau-activity-date">%s</span>',
            esc_html($date)
        );

        // Proof/Evidence - act_event_website_where_possible pode ser URL ou attachment ID
        $proof_value = get_post_meta($post->ID, 'act_event_website_where_possible', true);
        $proof_url = '';

        if (!empty($proof_value)) {
            // Verifica se é uma URL ou um attachment ID
            if (filter_var($proof_value, FILTER_VALIDATE_URL)) {
                $proof_url = $proof_value;
            } elseif (is_numeric($proof_value)) {
                // É um attachment ID - pega a URL
                $proof_url = wp_get_attachment_url($proof_value);
            }
        }

        // Action column - Botões de ação
        $action_html = '<div class="eau-activity-actions">';

        // Ver prova (se existir)
        if (!empty($proof_url)) {
            $action_html .= sprintf(
                '<a href="%s" target="_blank" class="eau-action-btn eau-action-proof" title="View proof/evidence"><i data-lucide="external-link"></i></a>',
                esc_url($proof_url)
            );
        }

        // Botão editar
        $action_html .= sprintf(
            '<button type="button" class="eau-action-btn eau-action-edit" data-id="%d" title="Edit activity"><i data-lucide="pencil"></i></button>',
            $post->ID
        );

        // Botão excluir
        $action_html .= sprintf(
            '<button type="button" class="eau-action-btn eau-action-delete" data-id="%d" title="Delete activity"><i data-lucide="trash-2"></i></button>',
            $post->ID
        );

        $action_html .= '</div>';

        return array(
            '_id' => $post->ID,
            'activity' => $activity_html,
            'category' => $category_html,
            'hours' => $hours_html,
            'points' => $points_html,
            'status' => $status_html,
            'date' => $date_html,
            'action' => $action_html,
        );
    }

    /**
     * AJAX: Upload file to WordPress Media Library
     *
     * @since 1.38.3
     */
    public static function upload_file() {
        // Verifica se está logado
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'You must be logged in to upload files.'), 401);
            return;
        }

        // Aceita múltiplos nonces do sistema
        $nonce_valid = false;
        $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';

        $nonces_to_check = array(
            'eau_my_cpds_nonce',
            'eau_events_management_nonce',
            'eau_event_registrations_nonce',
        );

        foreach ($nonces_to_check as $nonce_action) {
            if ($nonce && wp_verify_nonce($nonce, $nonce_action)) {
                $nonce_valid = true;
                break;
            }
        }

        if (!$nonce_valid) {
            wp_send_json_error(array('message' => 'Security check failed. Please refresh the page and try again.'), 403);
            return;
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

    /**
     * AJAX: Get user's uploaded files
     *
     * @since 1.38.3
     */
    public static function get_user_files() {
        // Verifica nonce
        check_ajax_referer('eau_my_cpds_nonce', 'nonce');

        // Verifica se está logado
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'You must be logged in.'));
        }

        $user_id = get_current_user_id();
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $page = isset($_POST['page']) ? absint($_POST['page']) : 1;
        $per_page = isset($_POST['per_page']) ? absint($_POST['per_page']) : 20;

        // Query attachments do usuário
        $args = array(
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'author'         => $user_id,
            'posts_per_page' => $per_page,
            'paged'          => $page,
            'orderby'        => 'date',
            'order'          => 'DESC',
        );

        // Filtro por MIME types (opcional)
        if (!empty($_POST['mime_types'])) {
            $args['post_mime_type'] = explode(',', sanitize_text_field($_POST['mime_types']));
        }

        // Busca por nome
        if (!empty($search)) {
            $args['s'] = $search;
        }

        $query = new \WP_Query($args);

        $files = array();
        foreach ($query->posts as $attachment) {
            $file_path = get_attached_file($attachment->ID);
            $file_size = file_exists($file_path) ? filesize($file_path) : 0;
            $mime_type = get_post_mime_type($attachment->ID);
            $is_image = strpos($mime_type, 'image/') === 0;

            $files[] = array(
                'id' => $attachment->ID,
                'filename' => basename($file_path),
                'url' => wp_get_attachment_url($attachment->ID),
                'thumbnail' => $is_image ? wp_get_attachment_image_url($attachment->ID, 'thumbnail') : '',
                'mime_type' => $mime_type,
                'is_image' => $is_image,
                'size' => $file_size,
                'size_formatted' => size_format($file_size),
                'date' => get_the_date('M j, Y', $attachment->ID),
            );
        }

        wp_send_json_success(array(
            'files' => $files,
            'total' => $query->found_posts,
            'total_pages' => $query->max_num_pages,
            'page' => $page,
        ));
    }

    /**
     * AJAX: Get activity data for editing
     *
     * @since 1.38.5
     */
    public static function get_my_activity() {
        // Verifica nonce
        check_ajax_referer('eau_my_cpds_nonce', 'nonce');

        // Verifica se está logado
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'You must be logged in.'));
        }

        $user_id = get_current_user_id();
        $mem_userid = get_user_meta($user_id, 'mem_userid', true);
        $activity_id = isset($_POST['activity_id']) ? absint($_POST['activity_id']) : 0;

        if (!$activity_id) {
            wp_send_json_error(array('message' => 'Invalid activity ID.'));
        }

        // Verifica se a atividade existe e pertence ao usuário
        $post = get_post($activity_id);

        if (!$post || $post->post_type !== 'activitie') {
            wp_send_json_error(array('message' => 'Activity not found.'));
        }

        $activity_user_id = get_post_meta($activity_id, 'act_user_id', true);

        if ($activity_user_id !== $mem_userid) {
            wp_send_json_error(array('message' => 'You do not have permission to edit this activity.'));
        }

        // Busca dados da atividade
        $category_serial = get_post_meta($activity_id, 'act_category_serial', true);
        $proof_value = get_post_meta($activity_id, 'act_event_website_where_possible', true);
        $proof_type = '';
        $proof_url = '';

        if (!empty($proof_value)) {
            if (filter_var($proof_value, FILTER_VALIDATE_URL)) {
                $proof_type = 'url';
                $proof_url = $proof_value;
            } elseif (is_numeric($proof_value)) {
                $proof_type = 'media';
                $proof_url = wp_get_attachment_url($proof_value);
            }
        }

        wp_send_json_success(array(
            'id' => $activity_id,
            'title' => $post->post_title,
            'category_serial' => $category_serial,
            'proof_value' => $proof_value,
            'proof_type' => $proof_type,
            'proof_url' => $proof_url,
            'proof_filename' => !empty($proof_url) ? basename($proof_url) : '',
        ));
    }

    /**
     * AJAX: Update activity (category and proof)
     *
     * @since 1.38.5
     */
    public static function update_my_activity() {
        // Verifica nonce
        check_ajax_referer('eau_my_cpds_nonce', 'nonce');

        // Verifica se está logado
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'You must be logged in.'));
        }

        $user_id = get_current_user_id();
        $mem_userid = get_user_meta($user_id, 'mem_userid', true);
        $activity_id = isset($_POST['activity_id']) ? absint($_POST['activity_id']) : 0;

        if (!$activity_id) {
            wp_send_json_error(array('message' => 'Invalid activity ID.'));
        }

        // Verifica se a atividade existe e pertence ao usuário
        $post = get_post($activity_id);

        if (!$post || $post->post_type !== 'activitie') {
            wp_send_json_error(array('message' => 'Activity not found.'));
        }

        $activity_user_id = get_post_meta($activity_id, 'act_user_id', true);

        if ($activity_user_id !== $mem_userid) {
            wp_send_json_error(array('message' => 'You do not have permission to edit this activity.'));
        }

        global $wpdb;
        $table_categories = $wpdb->prefix . 'eau_activity_categories';

        // Atualiza categoria se fornecida
        if (isset($_POST['category_serial']) && !empty($_POST['category_serial'])) {
            $category_serial = sanitize_text_field($_POST['category_serial']);

            // Valida categoria
            $category = $wpdb->get_row($wpdb->prepare(
                "SELECT category_name, points_per_hour FROM {$table_categories} WHERE category_serial = %s",
                $category_serial
            ));

            if ($category) {
                update_post_meta($activity_id, 'act_category_serial', $category_serial);
                update_post_meta($activity_id, 'act_category', $category->category_name);
            }
        }

        // Atualiza prova/evidência
        $proof_value = isset($_POST['proof']) ? sanitize_text_field($_POST['proof']) : '';

        if (!empty($proof_value)) {
            update_post_meta($activity_id, 'act_event_website_where_possible', $proof_value);
            update_post_meta($activity_id, 'act_supply_evidence_e_g_attendance_statement', '1');
        } else {
            delete_post_meta($activity_id, 'act_event_website_where_possible');
            update_post_meta($activity_id, 'act_supply_evidence_e_g_attendance_statement', '0');
        }

        wp_send_json_success(array(
            'message' => 'Activity updated successfully!',
        ));
    }

    /**
     * AJAX: Delete activity
     *
     * @since 1.38.5
     */
    public static function delete_my_activity() {
        // Verifica nonce
        check_ajax_referer('eau_my_cpds_nonce', 'nonce');

        // Verifica se está logado
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'You must be logged in.'));
        }

        $user_id = get_current_user_id();
        $mem_userid = get_user_meta($user_id, 'mem_userid', true);
        $activity_id = isset($_POST['activity_id']) ? absint($_POST['activity_id']) : 0;

        if (!$activity_id) {
            wp_send_json_error(array('message' => 'Invalid activity ID.'));
        }

        // Verifica se a atividade existe e pertence ao usuário
        $post = get_post($activity_id);

        if (!$post || $post->post_type !== 'activitie') {
            wp_send_json_error(array('message' => 'Activity not found.'));
        }

        $activity_user_id = get_post_meta($activity_id, 'act_user_id', true);

        if ($activity_user_id !== $mem_userid) {
            wp_send_json_error(array('message' => 'You do not have permission to delete this activity.'));
        }

        // Deleta a atividade (move para lixeira)
        $result = wp_trash_post($activity_id);

        if (!$result) {
            wp_send_json_error(array('message' => 'Failed to delete activity.'));
        }

        wp_send_json_success(array(
            'message' => 'Activity deleted successfully!',
        ));
    }
}
