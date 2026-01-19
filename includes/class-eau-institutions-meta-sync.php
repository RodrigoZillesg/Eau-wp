<?php
namespace EauSystem;

/**
 * Sincroniza novos meta fields de Instituições com JetEngine
 *
 * Adiciona os campos:
 * - ins_member_company_campus (Nome do campus)
 * - ins_member_start_date (Data de início do membership)
 * - ins_member_expire_date (Data de expiração do membership)
 *
 * @since 1.63.2
 */
class Eau_Institutions_Meta_Sync {

    /**
     * Versão dos meta fields (atualizar ao adicionar novos campos)
     */
    const META_VERSION = '1.0.0';

    /**
     * Post type das instituições
     */
    const POST_TYPE = 'institutions';

    /**
     * Novos campos a serem adicionados
     */
    private static $new_fields = array(
        array(
            'title'       => 'Campus Name',
            'name'        => 'ins_member_company_campus',
            'object_type' => 'field',
            'type'        => 'text',
            'width'       => '100%',
            'description' => 'Name of the institution campus',
        ),
        array(
            'title'       => 'Membership Start Date',
            'name'        => 'ins_member_start_date',
            'object_type' => 'field',
            'type'        => 'date',
            'width'       => '50%',
            'description' => 'Date when institution membership started',
        ),
        array(
            'title'       => 'Membership Expire Date',
            'name'        => 'ins_member_expire_date',
            'object_type' => 'field',
            'type'        => 'date',
            'width'       => '50%',
            'description' => 'Date when institution membership expires',
        ),
    );

    /**
     * Inicializa a classe
     */
    public static function init() {
        // Sincroniza meta fields com JetEngine ao carregar
        add_action('init', array(__CLASS__, 'sync_meta_fields'), 5);

        // Registra meta fields para REST API
        add_action('init', array(__CLASS__, 'register_meta_fields'), 10);
    }

    /**
     * Sincroniza meta fields com JetEngine
     */
    public static function sync_meta_fields() {
        global $wpdb;

        $table = $wpdb->prefix . 'jet_post_types';

        // Verifica se tabela existe
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            return;
        }

        // Verifica versão salva
        $saved_version = get_option('eau_institutions_meta_version');
        if ($saved_version === self::META_VERSION) {
            return; // Já está atualizado
        }

        // Busca registro do post type institutions no JetEngine
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT id, meta_fields FROM $table WHERE slug = %s",
            self::POST_TYPE
        ));

        if (!$row) {
            // Post type não existe no JetEngine, cria novo registro
            self::create_jet_post_type_entry();
        } else {
            // Atualiza meta fields existentes
            self::update_jet_meta_fields($row->id, $row->meta_fields);
        }

        // Salva versão
        update_option('eau_institutions_meta_version', self::META_VERSION);
    }

    /**
     * Cria entrada do post type no JetEngine
     */
    private static function create_jet_post_type_entry() {
        global $wpdb;

        $table = $wpdb->prefix . 'jet_post_types';

        $labels = array(
            'name'          => 'Institutions',
            'singular_name' => 'Institution',
            'menu_name'     => 'Institutions',
        );

        $args = array(
            'public'       => true,
            'has_archive'  => true,
            'show_in_rest' => true,
            'menu_icon'    => 'dashicons-building',
            'supports'     => array('title'),
            'rewrite'      => true,
            'rewrite_slug' => 'institutions',
        );

        $meta_fields = self::build_full_meta_fields();

        $data = array(
            'slug'        => self::POST_TYPE,
            'status'      => 'built-in', // Não deixa JetEngine gerenciar completamente
            'labels'      => maybe_serialize($labels),
            'args'        => maybe_serialize($args),
            'meta_fields' => maybe_serialize($meta_fields),
        );

        $wpdb->insert($table, $data, array('%s', '%s', '%s', '%s', '%s'));
    }

    /**
     * Atualiza meta fields existentes no JetEngine
     */
    private static function update_jet_meta_fields($row_id, $existing_meta_fields_serialized) {
        global $wpdb;

        $table = $wpdb->prefix . 'jet_post_types';

        // Deserializa campos existentes
        $existing_fields = maybe_unserialize($existing_meta_fields_serialized);
        if (!is_array($existing_fields)) {
            $existing_fields = array();
        }

        // Verifica quais campos novos ainda não existem
        $existing_names = array_column($existing_fields, 'name');
        $max_id = self::get_max_field_id($existing_fields);

        $fields_added = false;
        foreach (self::$new_fields as $field) {
            if (!in_array($field['name'], $existing_names)) {
                $max_id++;
                $field['id'] = $max_id;
                $existing_fields[] = $field;
                $fields_added = true;
            }
        }

        // Se adicionou campos, atualiza no banco
        if ($fields_added) {
            $wpdb->update(
                $table,
                array('meta_fields' => maybe_serialize($existing_fields)),
                array('id' => $row_id),
                array('%s'),
                array('%d')
            );
        }
    }

    /**
     * Constrói array completo de meta fields
     */
    private static function build_full_meta_fields() {
        $fields = array();
        $base_id = 95000; // ID base único para instituições

        // Campos existentes conhecidos
        $existing_known_fields = array(
            array('title' => 'Company ID', 'name' => 'ins_company_id', 'type' => 'text'),
            array('title' => 'Company Name', 'name' => 'ins_company_name', 'type' => 'text'),
            array('title' => 'Institution Type', 'name' => 'ins_type', 'type' => 'text'),
            array('title' => 'Email', 'name' => 'ins_company_email', 'type' => 'text'),
            array('title' => 'Phone', 'name' => 'ins_company_company_phone', 'type' => 'text'),
            array('title' => 'Address', 'name' => 'ins_company_company_address_line_1', 'type' => 'text'),
            array('title' => 'Suburb', 'name' => 'ins_company_company_suburb', 'type' => 'text'),
            array('title' => 'State', 'name' => 'ins_company_company_state', 'type' => 'text'),
            array('title' => 'Postcode', 'name' => 'ins_company_company_postcode', 'type' => 'text'),
            array('title' => 'Country', 'name' => 'ins_company_company_country', 'type' => 'text'),
            array('title' => 'Status', 'name' => 'ins_status', 'type' => 'text'),
            array('title' => 'Logo', 'name' => 'ins_company_logo', 'type' => 'media'),
            array('title' => 'Primary Contact', 'name' => 'ins_company_primary_contact', 'type' => 'text'),
        );

        // Adiciona campos existentes
        foreach ($existing_known_fields as $field) {
            $fields[] = array(
                'title'       => $field['title'],
                'name'        => $field['name'],
                'object_type' => 'field',
                'type'        => $field['type'],
                'width'       => '100%',
                'id'          => $base_id++,
            );
        }

        // Adiciona novos campos
        foreach (self::$new_fields as $field) {
            $field['id'] = $base_id++;
            $fields[] = $field;
        }

        return $fields;
    }

    /**
     * Obtém o maior ID de campo existente
     */
    private static function get_max_field_id($fields) {
        $max_id = 95000; // Valor base

        foreach ($fields as $field) {
            if (isset($field['id']) && $field['id'] > $max_id) {
                $max_id = $field['id'];
            }
        }

        return $max_id;
    }

    /**
     * Registra meta fields para REST API
     */
    public static function register_meta_fields() {
        $fields = array(
            'ins_member_company_campus' => 'string',
            'ins_member_start_date'     => 'string',
            'ins_member_expire_date'    => 'string',
        );

        foreach ($fields as $field => $type) {
            register_post_meta(self::POST_TYPE, $field, array(
                'type'              => $type,
                'single'            => true,
                'show_in_rest'      => true,
                'sanitize_callback' => 'sanitize_text_field',
                'auth_callback'     => function() {
                    return current_user_can('edit_posts');
                },
            ));
        }
    }

    /**
     * Helper: Obtém status de expiração de uma instituição
     *
     * @param int $institution_id ID da instituição
     * @return array Array com 'status', 'class', 'label', 'days_until'
     */
    public static function get_expiration_status($institution_id) {
        $expire_date = get_post_meta($institution_id, 'ins_member_expire_date', true);

        if (empty($expire_date)) {
            return array(
                'status'     => 'not_set',
                'class'      => 'eau-expire-not-set',
                'label'      => 'Not Set',
                'days_until' => null,
            );
        }

        $expire_timestamp = strtotime($expire_date);
        $today_timestamp = strtotime('today');
        $days_until = floor(($expire_timestamp - $today_timestamp) / (60 * 60 * 24));

        // Já expirou
        if ($days_until < 0) {
            return array(
                'status'     => 'expired',
                'class'      => 'eau-expire-expired',
                'label'      => 'Expired',
                'days_until' => $days_until,
            );
        }

        // Expira em menos de 30 dias
        if ($days_until <= 30) {
            return array(
                'status'     => 'critical',
                'class'      => 'eau-expire-critical',
                'label'      => $days_until === 0 ? 'Expires Today' : "Expires in {$days_until} days",
                'days_until' => $days_until,
            );
        }

        // Expira em menos de 60 dias
        if ($days_until <= 60) {
            return array(
                'status'     => 'warning',
                'class'      => 'eau-expire-warning',
                'label'      => "Expires in {$days_until} days",
                'days_until' => $days_until,
            );
        }

        // Expira em menos de 90 dias
        if ($days_until <= 90) {
            return array(
                'status'     => 'attention',
                'class'      => 'eau-expire-attention',
                'label'      => "Expires in {$days_until} days",
                'days_until' => $days_until,
            );
        }

        // Mais de 90 dias
        return array(
            'status'     => 'ok',
            'class'      => 'eau-expire-ok',
            'label'      => date('M j, Y', $expire_timestamp),
            'days_until' => $days_until,
        );
    }

    /**
     * Formata data para exibição
     *
     * @param string $date Data no formato Y-m-d
     * @return string Data formatada
     */
    public static function format_date($date) {
        if (empty($date)) {
            return '-';
        }

        $timestamp = strtotime($date);
        return date('M j, Y', $timestamp);
    }
}
