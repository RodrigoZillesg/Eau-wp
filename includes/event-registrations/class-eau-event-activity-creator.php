<?php
/**
 * Event Activity Creator
 *
 * Cria Activities CPD automaticamente para usuários que participaram de eventos.
 *
 * @package    EauSystem
 * @subpackage EventRegistrations
 * @since      1.30.9
 */

namespace EauSystem\EventRegistrations;

if (!defined('WPINC')) {
    die;
}

/**
 * Class Eau_Event_Activity_Creator
 *
 * Gerencia a criação automática de Activities CPD a partir de Event Registrations.
 *
 * @since 1.30.9
 */
class Eau_Event_Activity_Creator {

    /**
     * Registra hooks
     *
     * @since  1.30.9
     * @return void
     */
    public static function register() {
        // Cron para processar eventos finalizados
        add_action('eau_process_completed_events', array(__CLASS__, 'process_completed_events'));

        // AJAX para marcar presença quando usuário clica em Join
        add_action('wp_ajax_eau_mark_event_attended', array(__CLASS__, 'mark_attended'));
    }

    /**
     * Setup do cron job
     *
     * @since  1.30.9
     * @return void
     */
    public static function setup_cron() {
        if (!wp_next_scheduled('eau_process_completed_events')) {
            wp_schedule_event(time(), 'hourly', 'eau_process_completed_events');
        }
    }

    /**
     * Remove o cron job
     *
     * @since  1.30.9
     * @return void
     */
    public static function clear_cron() {
        $timestamp = wp_next_scheduled('eau_process_completed_events');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'eau_process_completed_events');
        }
    }

    /**
     * Gera UUID único para activity_serial
     *
     * @since  1.30.9
     * @return string UUID no formato xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
     */
    public static function generate_activity_serial() {
        // Gera UUID v4
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // versão 4
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // variante RFC 4122

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * AJAX: Marca que o usuário participou do evento (clicou em Join)
     *
     * @since  1.30.9
     * @return void
     */
    public static function mark_attended() {
        check_ajax_referer('eau_event_registration', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in.', 'eau-system')));
        }

        $event_id = isset($_POST['event_id']) ? absint($_POST['event_id']) : 0;

        if (!$event_id) {
            wp_send_json_error(array('message' => __('Invalid event.', 'eau-system')));
        }

        // Busca o registration do usuário para este evento
        $registration = Frontend\Eau_Event_Registrations_Ajax::get_user_registration($event_id, get_current_user_id());

        if (!$registration) {
            wp_send_json_error(array('message' => __('Registration not found.', 'eau-system')));
        }

        // Marca como attended
        update_post_meta($registration->ID, Config\META_PREFIX . 'attended', '1');

        wp_send_json_success(array('message' => __('Attendance marked successfully.', 'eau-system')));
    }

    /**
     * Processa eventos finalizados e cria Activities para participantes
     *
     * @since  1.30.9
     * @return void
     */
    public static function process_completed_events() {
        // Busca eventos que já terminaram (end_datetime < agora)
        $completed_events = new \WP_Query(array(
            'post_type'      => 'eau_event',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => array(
                array(
                    'key'     => 'evt_end_datetime',
                    'value'   => current_time('Y-m-d\TH:i'),
                    'compare' => '<',
                    'type'    => 'DATETIME',
                ),
            ),
        ));

        if (!$completed_events->have_posts()) {
            return;
        }

        foreach ($completed_events->posts as $event) {
            self::create_activities_for_event($event->ID);
        }
    }

    /**
     * Cria Activities para todos os participantes de um evento
     *
     * @since  1.30.9
     * @param  int $event_id ID do evento
     * @return int Número de activities criadas
     */
    public static function create_activities_for_event($event_id) {
        $created = 0;
        $prefix = Config\META_PREFIX;

        // Busca todos os registrations confirmados que participaram e ainda não tiveram activity criada
        $registrations = new \WP_Query(array(
            'post_type'      => Config\POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => array(
                'relation' => 'AND',
                array(
                    'key'   => $prefix . 'event_id',
                    'value' => $event_id,
                ),
                array(
                    'key'   => $prefix . 'attended',
                    'value' => '1',
                ),
                array(
                    'key'     => $prefix . 'activity_created',
                    'value'   => '1',
                    'compare' => '!=',
                ),
                array(
                    'key'     => $prefix . 'status',
                    'value'   => 'cancelled',
                    'compare' => '!=',
                ),
            ),
        ));

        if (!$registrations->have_posts()) {
            return 0;
        }

        // Dados do evento
        $event_data = self::get_event_data($event_id);

        foreach ($registrations->posts as $registration) {
            $mem_userid = get_post_meta($registration->ID, $prefix . 'mem_userid', true);

            // Pula se não tiver mem_userid (não é membro)
            if (empty($mem_userid)) {
                continue;
            }

            // Cria a activity
            $activity_id = self::create_activity($registration->ID, $event_data, $mem_userid);

            if ($activity_id) {
                // Marca que activity foi criada
                update_post_meta($registration->ID, $prefix . 'activity_created', '1');
                $created++;
            }
        }

        return $created;
    }

    /**
     * Obtém dados do evento necessários para criar a activity
     *
     * @since  1.30.9
     * @param  int $event_id ID do evento
     * @return array Dados do evento
     */
    private static function get_event_data($event_id) {
        $event = get_post($event_id);
        $evt_prefix = 'evt_';

        // Calcula duração do evento em horas
        $start = get_post_meta($event_id, $evt_prefix . 'start_datetime', true);
        $end = get_post_meta($event_id, $evt_prefix . 'end_datetime', true);

        $hours = 1; // Default
        if ($start && $end) {
            $start_obj = \DateTime::createFromFormat('Y-m-d\TH:i', $start);
            $end_obj = \DateTime::createFromFormat('Y-m-d\TH:i', $end);
            if ($start_obj && $end_obj) {
                $diff = $end_obj->getTimestamp() - $start_obj->getTimestamp();
                $hours = max(0.5, round($diff / 3600, 2)); // Mínimo 0.5 hora
            }
        }

        // Categoria CPD
        $cpd_category_id = get_post_meta($event_id, $evt_prefix . 'cpd_category', true);
        $category_data = self::get_category_data($cpd_category_id);

        return array(
            'event_id'        => $event_id,
            'event_title'     => $event->post_title,
            'event_date'      => $start ? substr($start, 0, 10) : current_time('Y-m-d'),
            'hours'           => $hours,
            'cpd_points'      => get_post_meta($event_id, $evt_prefix . 'cpd_points', true) ?: $hours,
            'category_id'     => $cpd_category_id,
            'category_serial' => $category_data['category_serial'],
            'category_name'   => $category_data['category_name'],
        );
    }

    /**
     * Obtém dados da categoria CPD
     *
     * @since  1.30.9
     * @param  int $category_id ID da categoria
     * @return array Dados da categoria
     */
    private static function get_category_data($category_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'eau_activity_categories';

        $category = $wpdb->get_row($wpdb->prepare(
            "SELECT id, category_serial, category_name FROM $table_name WHERE id = %d",
            intval($category_id)
        ));

        if ($category) {
            return array(
                'category_serial' => $category->category_serial,
                'category_name'   => $category->category_name,
            );
        }

        return array(
            'category_serial' => '',
            'category_name'   => 'Event Attendance',
        );
    }

    /**
     * Cria uma Activity CPD
     *
     * Meta fields da Activity:
     * - act_user_id: mem_userid do usuário
     * - act_category_serial: ID da categoria CPD
     * - act_category: Nome da categoria CPD
     * - act_hours_of_pd_anything_below_60_minutes_can_be_entered_as_a_decimal_e_g_30_mins_0_5: Horas do evento
     * - act_supply_evidence_e_g_attendance_statement: 1 (sempre tem evidência - o evento)
     * - act_verified: 1 (verificado automaticamente)
     * - act_event_website_where_possible: ID da mídia/comprovante (não usar por agora)
     * - activity_serial: UUID gerado
     *
     * @since  1.30.9
     * @param  int    $registration_id ID do registration
     * @param  array  $event_data      Dados do evento
     * @param  string $mem_userid      mem_userid do participante
     * @return int|false ID da activity criada ou false em caso de erro
     */
    private static function create_activity($registration_id, $event_data, $mem_userid) {
        // Título da activity = nome do evento
        $post_title = $event_data['event_title'];

        // Data do post = data do evento
        $post_date = $event_data['event_date'] . ' 12:00:00';

        // Cria o post
        $post_id = wp_insert_post(array(
            'post_type'     => 'activitie',
            'post_title'    => $post_title,
            'post_content'  => sprintf(
                __('Activity automatically created from event attendance: %s', 'eau-system'),
                $event_data['event_title']
            ),
            'post_status'   => 'publish',
            'post_date'     => $post_date,
            'post_date_gmt' => get_gmt_from_date($post_date),
        ));

        if (is_wp_error($post_id)) {
            return false;
        }

        // Meta fields
        update_post_meta($post_id, 'activity_serial', self::generate_activity_serial());
        update_post_meta($post_id, 'act_user_id', $mem_userid);
        update_post_meta($post_id, 'act_category_serial', $event_data['category_serial']);
        update_post_meta($post_id, 'act_category', $event_data['category_name']);
        update_post_meta($post_id, 'act_hours_of_pd_anything_below_60_minutes_can_be_entered_as_a_decimal_e_g_30_mins_0_5', $event_data['hours']);
        update_post_meta($post_id, 'act_supply_evidence_e_g_attendance_statement', '1'); // Sempre tem evidência
        update_post_meta($post_id, 'act_verified', '1'); // Verificado automaticamente
        update_post_meta($post_id, 'act_completed_date', $event_data['event_date']);

        // Referência ao evento e registration (para rastreabilidade)
        update_post_meta($post_id, 'act_source_event_id', $event_data['event_id']);
        update_post_meta($post_id, 'act_source_registration_id', $registration_id);

        return $post_id;
    }

    /**
     * Cria activity manualmente para um registration específico
     * Usado quando admin quer forçar criação
     *
     * @since  1.30.9
     * @param  int $registration_id ID do registration
     * @return int|false ID da activity ou false
     */
    public static function create_activity_for_registration($registration_id) {
        $prefix = Config\META_PREFIX;

        // Verifica se já foi criada
        $already_created = get_post_meta($registration_id, $prefix . 'activity_created', true);
        if ($already_created === '1') {
            return false;
        }

        // Obtém dados
        $event_id = get_post_meta($registration_id, $prefix . 'event_id', true);
        $mem_userid = get_post_meta($registration_id, $prefix . 'mem_userid', true);

        if (!$event_id || !$mem_userid) {
            return false;
        }

        $event_data = self::get_event_data($event_id);
        $activity_id = self::create_activity($registration_id, $event_data, $mem_userid);

        if ($activity_id) {
            update_post_meta($registration_id, $prefix . 'activity_created', '1');
            update_post_meta($registration_id, $prefix . 'attended', '1'); // Marca como participou também
        }

        return $activity_id;
    }
}
