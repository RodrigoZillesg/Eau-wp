<?php
namespace EauSystem;

/**
 * AJAX Endpoints para o sistema de duplicatas
 */
class Eau_Duplicate_Ajax {

    /**
     * Registra todos os endpoints AJAX
     */
    public static function register_endpoints() {
        add_action('wp_ajax_eau_start_scan', array(__CLASS__, 'start_scan'));
        add_action('wp_ajax_eau_get_scan_progress', array(__CLASS__, 'get_scan_progress'));
        add_action('wp_ajax_eau_get_duplicate_pairs', array(__CLASS__, 'get_duplicate_pairs'));
        add_action('wp_ajax_eau_merge_members', array(__CLASS__, 'merge_members'));
        add_action('wp_ajax_eau_dismiss_duplicate', array(__CLASS__, 'dismiss_duplicate'));
        add_action('wp_ajax_eau_ignore_duplicate', array(__CLASS__, 'ignore_duplicate'));
    }

    /**
     * Inicia novo scan de duplicatas
     */
    public static function start_scan() {
        check_ajax_referer('eau_duplicate_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }

        $current_user_id = get_current_user_id();
        $scan_id = Eau_Duplicate_Scanner::start_scan($current_user_id);

        if ($scan_id === false) {
            wp_send_json_error(array('message' => 'Failed to start scan. Check error logs for details.'));
        }

        wp_send_json_success(array(
            'scan_id' => $scan_id,
            'message' => 'Scan started successfully',
        ));
    }

    /**
     * Retorna progresso do scan
     */
    public static function get_scan_progress() {
        check_ajax_referer('eau_duplicate_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }

        $scan_id = isset($_POST['scan_id']) ? intval($_POST['scan_id']) : 0;

        if (!$scan_id) {
            wp_send_json_error(array('message' => 'Invalid scan ID'));
        }

        $progress = Eau_Duplicate_Scanner::get_scan_progress($scan_id);

        if (!$progress) {
            wp_send_json_error(array('message' => 'Scan not found'));
        }

        wp_send_json_success($progress);
    }

    /**
     * Retorna lista de pares duplicados com paginação
     */
    public static function get_duplicate_pairs() {
        check_ajax_referer('eau_duplicate_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }

        global $wpdb;

        // Parâmetros
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $per_page = isset($_POST['per_page']) ? intval($_POST['per_page']) : 10;
        $filter = isset($_POST['filter']) ? sanitize_text_field($_POST['filter']) : 'all';
        $sort = isset($_POST['sort']) ? sanitize_text_field($_POST['sort']) : 'score_desc';

        $offset = ($page - 1) * $per_page;

        // Constrói query
        $where = "pair_status = 'pending'";

        // Filtro por score
        if ($filter === 'high') {
            $where .= " AND similarity_score >= 80";
        } elseif ($filter === 'medium') {
            $where .= " AND similarity_score >= 50 AND similarity_score < 80";
        }

        // Ordenação
        $order_by = 'similarity_score DESC';
        if ($sort === 'score_asc') {
            $order_by = 'similarity_score ASC';
        } elseif ($sort === 'date_desc') {
            $order_by = 'pair_id DESC';
        }

        // Busca pares
        $pairs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}eau_duplicate_pairs
            WHERE $where
            ORDER BY $order_by
            LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ));

        // Conta total
        $total = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}eau_duplicate_pairs WHERE $where"
        );

        // Processa dados dos pares
        $processed_pairs = array();
        foreach ($pairs as $pair) {
            $details = json_decode($pair->match_details, true);

            // Busca dados atuais dos usuários
            $user1 = get_user_by('id', $pair->user_id_1);
            $user2 = get_user_by('id', $pair->user_id_2);

            if (!$user1 || !$user2) {
                continue; // Usuário deletado, pula
            }

            $processed_pairs[] = array(
                'pair_id' => $pair->pair_id,
                'score' => floatval($pair->similarity_score),
                'matches' => isset($details['matches']) ? $details['matches'] : array(),
                'user1' => array(
                    'id' => $user1->ID,
                    'display_name' => self::get_full_name($user1),
                    'email' => $user1->user_email,
                    'phone' => get_user_meta($user1->ID, 'mem_phone', true),
                    'company' => self::get_institution_name($user1->ID),
                    'address' => get_user_meta($user1->ID, 'mem_address', true),
                    'city' => get_user_meta($user1->ID, 'mem_city', true),
                    'postcode' => get_user_meta($user1->ID, 'mem_postcode', true),
                ),
                'user2' => array(
                    'id' => $user2->ID,
                    'display_name' => self::get_full_name($user2),
                    'email' => $user2->user_email,
                    'phone' => get_user_meta($user2->ID, 'mem_phone', true),
                    'company' => self::get_institution_name($user2->ID),
                    'address' => get_user_meta($user2->ID, 'mem_address', true),
                    'city' => get_user_meta($user2->ID, 'mem_city', true),
                    'postcode' => get_user_meta($user2->ID, 'mem_postcode', true),
                ),
            );
        }

        wp_send_json_success(array(
            'pairs' => $processed_pairs,
            'total' => intval($total),
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil($total / $per_page),
        ));
    }

    /**
     * Executa merge de dois membros
     */
    public static function merge_members() {
        check_ajax_referer('eau_duplicate_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }

        global $wpdb;

        $pair_id = isset($_POST['pair_id']) ? intval($_POST['pair_id']) : 0;
        $user_id_keep = isset($_POST['user_id_keep']) ? intval($_POST['user_id_keep']) : 0;
        $user_id_delete = isset($_POST['user_id_delete']) ? intval($_POST['user_id_delete']) : 0;
        $field_choices = isset($_POST['field_choices']) ? $_POST['field_choices'] : array();

        // Validações
        if (!$pair_id || !$user_id_keep || !$user_id_delete) {
            wp_send_json_error(array('message' => 'Invalid parameters'));
        }

        // Verifica se usuários existem
        $user_keep = get_user_by('id', $user_id_keep);
        $user_delete = get_user_by('id', $user_id_delete);

        if (!$user_keep || !$user_delete) {
            wp_send_json_error(array('message' => 'One or both users not found'));
        }

        // Atualiza campos do usuário que vai ficar
        foreach ($field_choices as $field => $chosen_user_id) {
            $chosen_user_id = intval($chosen_user_id);

            if ($chosen_user_id === $user_id_keep) {
                continue; // Já está com os dados corretos
            }

            // Pega valor do usuário que será deletado
            if ($field === 'display_name') {
                wp_update_user(array(
                    'ID' => $user_id_keep,
                    'display_name' => $user_delete->display_name,
                ));
            } elseif ($field === 'user_email') {
                // Verifica se email já existe
                if (!email_exists($user_delete->user_email) || $user_delete->user_email === $user_keep->user_email) {
                    wp_update_user(array(
                        'ID' => $user_id_keep,
                        'user_email' => $user_delete->user_email,
                    ));
                }
            } else {
                // Meta field
                $value = get_user_meta($user_id_delete, $field, true);
                update_user_meta($user_id_keep, $field, $value);
            }
        }

        // Transfere posts relacionados (activities, etc)
        $wpdb->update(
            $wpdb->posts,
            array('post_author' => $user_id_keep),
            array('post_author' => $user_id_delete),
            array('%d'),
            array('%d')
        );

        // Deleta usuário
        require_once(ABSPATH . 'wp-admin/includes/user.php');
        $deleted = wp_delete_user($user_id_delete, $user_id_keep);

        if (!$deleted) {
            wp_send_json_error(array('message' => 'Failed to delete user'));
        }

        // Atualiza status do par
        $current_user_id = get_current_user_id();
        $wpdb->update(
            $wpdb->prefix . 'eau_duplicate_pairs',
            array(
                'pair_status' => 'merged',
                'reviewed_by_user_id' => $current_user_id,
                'reviewed_date' => current_time('mysql'),
                'merged_into_user_id' => $user_id_keep,
            ),
            array('pair_id' => $pair_id),
            array('%s', '%d', '%s', '%d'),
            array('%d')
        );

        wp_send_json_success(array(
            'message' => 'Members merged successfully',
            'kept_user_id' => $user_id_keep,
        ));
    }

    /**
     * Marca par como "não duplicado"
     */
    public static function dismiss_duplicate() {
        check_ajax_referer('eau_duplicate_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }

        global $wpdb;

        $pair_id = isset($_POST['pair_id']) ? intval($_POST['pair_id']) : 0;

        if (!$pair_id) {
            wp_send_json_error(array('message' => 'Invalid pair ID'));
        }

        // Busca par
        $pair = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}eau_duplicate_pairs WHERE pair_id = %d",
            $pair_id
        ));

        if (!$pair) {
            wp_send_json_error(array('message' => 'Pair not found'));
        }

        // Atualiza status
        $current_user_id = get_current_user_id();
        $wpdb->update(
            $wpdb->prefix . 'eau_duplicate_pairs',
            array(
                'pair_status' => 'dismissed',
                'reviewed_by_user_id' => $current_user_id,
                'reviewed_date' => current_time('mysql'),
            ),
            array('pair_id' => $pair_id),
            array('%s', '%d', '%s'),
            array('%d')
        );

        // Adiciona à lista de exclusões
        self::add_exclusion($pair->user_id_1, $pair->user_id_2, 'not_duplicate');

        wp_send_json_success(array('message' => 'Pair dismissed successfully'));
    }

    /**
     * Marca par para nunca mais ser analisado
     */
    public static function ignore_duplicate() {
        check_ajax_referer('eau_duplicate_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }

        global $wpdb;

        $pair_id = isset($_POST['pair_id']) ? intval($_POST['pair_id']) : 0;

        if (!$pair_id) {
            wp_send_json_error(array('message' => 'Invalid pair ID'));
        }

        // Busca par
        $pair = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}eau_duplicate_pairs WHERE pair_id = %d",
            $pair_id
        ));

        if (!$pair) {
            wp_send_json_error(array('message' => 'Pair not found'));
        }

        // Atualiza status
        $current_user_id = get_current_user_id();
        $wpdb->update(
            $wpdb->prefix . 'eau_duplicate_pairs',
            array(
                'pair_status' => 'ignored',
                'reviewed_by_user_id' => $current_user_id,
                'reviewed_date' => current_time('mysql'),
            ),
            array('pair_id' => $pair_id),
            array('%s', '%d', '%s'),
            array('%d')
        );

        // Adiciona à lista de exclusões permanentes
        self::add_exclusion($pair->user_id_1, $pair->user_id_2, 'never_analyze');

        wp_send_json_success(array('message' => 'Pair will be ignored in future scans'));
    }

    /**
     * Adiciona exclusão ao banco
     */
    private static function add_exclusion($user_id_1, $user_id_2, $type) {
        global $wpdb;

        // Garante que sempre salva com menor ID primeiro
        $ids = array($user_id_1, $user_id_2);
        sort($ids);

        $wpdb->replace(
            $wpdb->prefix . 'eau_duplicate_exclusions',
            array(
                'user_id_1' => $ids[0],
                'user_id_2' => $ids[1],
                'exclusion_type' => $type,
                'created_by_user_id' => get_current_user_id(),
                'created_date' => current_time('mysql'),
            ),
            array('%d', '%d', '%s', '%d', '%s')
        );
    }

    /**
     * Busca o nome da instituição pelo user_id
     */
    private static function get_institution_name($user_id) {
        global $wpdb;

        // Pega o ins_member_company_name do usuário
        $company_name = get_user_meta($user_id, 'mem_membercompanyname', true);

        if (empty($company_name)) {
            return '';
        }

        // Busca o post da instituição que tem esse ins_member_company_name
        $institution_post = $wpdb->get_row($wpdb->prepare(
            "SELECT p.post_title
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = %s
            AND p.post_status = %s
            AND pm.meta_key = %s
            AND pm.meta_value = %s
            LIMIT 1",
            'institutions',
            'publish',
            'ins_member_company_name',
            $company_name
        ));

        if ($institution_post) {
            return $institution_post->post_title;
        }

        // Se não encontrou, retorna o company_name mesmo
        return $company_name;
    }

    /**
     * Retorna nome completo do usuário (first_name + last_name ou display_name)
     */
    private static function get_full_name($user) {
        $first_name = get_user_meta($user->ID, 'first_name', true);
        $last_name = get_user_meta($user->ID, 'last_name', true);

        // Se tem first_name e last_name, usa eles
        if (!empty($first_name) && !empty($last_name)) {
            return trim($first_name . ' ' . $last_name);
        }

        // Se tem só first_name, usa ele
        if (!empty($first_name)) {
            return $first_name;
        }

        // Fallback para display_name
        return $user->display_name;
    }
}
