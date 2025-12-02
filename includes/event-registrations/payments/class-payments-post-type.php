<?php
/**
 * Payments Custom Post Type
 *
 * @package    EauSystem
 * @subpackage EventRegistrations\Payments
 * @since      1.45.0
 */

namespace EauSystem\EventRegistrations\Payments;

if (!defined('WPINC')) {
    die;
}

/**
 * Class Payments_Post_Type
 *
 * Registra o CPT eau_payment para armazenar pagamentos de eventos.
 *
 * @since 1.45.0
 */
class Payments_Post_Type {

    const POST_TYPE = 'eau_payment';
    const META_PREFIX = 'pay_';

    /**
     * Versão do módulo para controle de sincronização
     */
    const VERSION = '1.45.5';

    /**
     * Inicializa o Post Type
     *
     * @since  1.45.0
     * @return void
     */
    public static function init() {
        add_action('init', array(__CLASS__, 'register_post_type'));
        add_action('init', array(__CLASS__, 'register_meta_fields'));
        add_action('init', array(__CLASS__, 'register_to_jet_engine'), 5);
    }

    /**
     * Registra o Custom Post Type
     *
     * @since  1.45.0
     * @return void
     */
    public static function register_post_type() {
        $labels = array(
            'name'               => __('Payments', 'eau-system'),
            'singular_name'      => __('Payment', 'eau-system'),
            'add_new'            => __('Add New', 'eau-system'),
            'add_new_item'       => __('Add New Payment', 'eau-system'),
            'edit_item'          => __('Edit Payment', 'eau-system'),
            'new_item'           => __('New Payment', 'eau-system'),
            'view_item'          => __('View Payment', 'eau-system'),
            'search_items'       => __('Search Payments', 'eau-system'),
            'not_found'          => __('No payments found', 'eau-system'),
            'not_found_in_trash' => __('No payments found in trash', 'eau-system'),
        );

        $args = array(
            'labels'              => $labels,
            'public'              => false,
            'publicly_queryable'  => false,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'query_var'           => false,
            'rewrite'             => false,
            'capability_type'     => 'post',
            'has_archive'         => false,
            'hierarchical'        => false,
            'menu_position'       => 27, // Após Events (25) e Registrations (26)
            'menu_icon'           => 'dashicons-money-alt',
            'supports'            => array('title'),
            'show_in_rest'        => false,
        );

        register_post_type(self::POST_TYPE, $args);
    }

    /**
     * Registra os meta fields
     *
     * @since  1.45.0
     * @return void
     */
    public static function register_meta_fields() {
        $meta_fields = self::get_meta_fields();

        foreach ($meta_fields as $key => $type) {
            register_post_meta(self::POST_TYPE, self::META_PREFIX . $key, array(
                'show_in_rest'  => false,
                'single'        => true,
                'type'          => $type,
                'auth_callback' => function() {
                    return current_user_can('edit_posts');
                },
            ));
        }
    }

    /**
     * Retorna lista de meta fields
     *
     * @since  1.45.0
     * @return array
     */
    public static function get_meta_fields() {
        return array(
            'registration_id' => 'integer',  // ID da registration (eau_event_reg)
            'event_id'        => 'integer',  // ID do evento
            'user_id'         => 'integer',  // ID do usuário que pagou
            'amount'          => 'number',   // Valor do pagamento
            'payment_date'    => 'string',   // Data do pagamento (Y-m-d)
            'payment_method'  => 'string',   // Método: credit_card, bank_transfer, pix, cash, other
            'transaction_id'  => 'string',   // ID da transação (gateway)
            'receipt_url'     => 'string',   // URL do comprovante
            'receipt_id'      => 'integer',  // ID do attachment do comprovante
            'notes'           => 'string',   // Observações
            'created_by'      => 'integer',  // Admin que registrou o pagamento
            'status'          => 'string',   // confirmed, pending, refunded
        );
    }

    /**
     * Retorna valores padrão dos meta fields
     *
     * @since  1.45.0
     * @return array
     */
    public static function get_defaults() {
        return array(
            'registration_id' => 0,
            'event_id'        => 0,
            'user_id'         => 0,
            'amount'          => 0,
            'payment_date'    => '',
            'payment_method'  => '',
            'transaction_id'  => '',
            'receipt_url'     => '',
            'receipt_id'      => 0,
            'notes'           => '',
            'created_by'      => 0,
            'status'          => 'confirmed',
        );
    }

    /**
     * Cria um novo pagamento
     *
     * @since  1.45.0
     * @param  array $data Dados do pagamento
     * @return int|WP_Error ID do post ou erro
     */
    public static function create_payment($data) {
        $defaults = self::get_defaults();
        $data = wp_parse_args($data, $defaults);

        // Gera título do pagamento
        $title = sprintf(
            'Payment #%s - Reg #%d',
            date('YmdHis'),
            $data['registration_id']
        );

        // Cria o post
        $post_id = wp_insert_post(array(
            'post_type'   => self::POST_TYPE,
            'post_title'  => $title,
            'post_status' => 'publish',
        ));

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        // Salva meta fields
        foreach (self::get_meta_fields() as $key => $type) {
            if (isset($data[$key])) {
                update_post_meta($post_id, self::META_PREFIX . $key, $data[$key]);
            }
        }

        return $post_id;
    }

    /**
     * Obtém pagamentos de uma registration
     *
     * @since  1.45.0
     * @param  int $registration_id ID da registration
     * @return array Lista de pagamentos
     */
    public static function get_payments_by_registration($registration_id) {
        $payments = get_posts(array(
            'post_type'      => self::POST_TYPE,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_query'     => array(
                array(
                    'key'     => self::META_PREFIX . 'registration_id',
                    'value'   => $registration_id,
                    'compare' => '=',
                    'type'    => 'NUMERIC',
                ),
            ),
            'orderby'        => 'date',
            'order'          => 'DESC',
        ));

        $result = array();
        foreach ($payments as $payment) {
            $result[] = self::format_payment($payment);
        }

        return $result;
    }

    /**
     * Obtém total pago de uma registration
     *
     * @since  1.45.0
     * @param  int $registration_id ID da registration
     * @return float Total pago
     */
    public static function get_total_paid($registration_id) {
        $payments = self::get_payments_by_registration($registration_id);
        $total = 0;

        foreach ($payments as $payment) {
            if ($payment['status'] === 'confirmed') {
                $total += floatval($payment['amount']);
            }
        }

        return $total;
    }

    /**
     * Formata dados de um pagamento
     *
     * @since  1.45.0
     * @param  WP_Post $payment Post do pagamento
     * @return array Dados formatados
     */
    public static function format_payment($payment) {
        $prefix = self::META_PREFIX;

        return array(
            'id'              => $payment->ID,
            'registration_id' => intval(get_post_meta($payment->ID, $prefix . 'registration_id', true)),
            'event_id'        => intval(get_post_meta($payment->ID, $prefix . 'event_id', true)),
            'user_id'         => intval(get_post_meta($payment->ID, $prefix . 'user_id', true)),
            'amount'          => floatval(get_post_meta($payment->ID, $prefix . 'amount', true)),
            'payment_date'    => get_post_meta($payment->ID, $prefix . 'payment_date', true),
            'payment_method'  => get_post_meta($payment->ID, $prefix . 'payment_method', true),
            'transaction_id'  => get_post_meta($payment->ID, $prefix . 'transaction_id', true),
            'receipt_url'     => get_post_meta($payment->ID, $prefix . 'receipt_url', true),
            'receipt_id'      => intval(get_post_meta($payment->ID, $prefix . 'receipt_id', true)),
            'notes'           => get_post_meta($payment->ID, $prefix . 'notes', true),
            'created_by'      => intval(get_post_meta($payment->ID, $prefix . 'created_by', true)),
            'status'          => get_post_meta($payment->ID, $prefix . 'status', true) ?: 'confirmed',
            'created_at'      => $payment->post_date,
        );
    }

    /**
     * Retorna métodos de pagamento disponíveis
     *
     * @since  1.45.0
     * @return array
     */
    public static function get_payment_methods() {
        return array(
            'credit_card'   => __('Credit Card', 'eau-system'),
            'debit_card'    => __('Debit Card', 'eau-system'),
            'bank_transfer' => __('Bank Transfer', 'eau-system'),
            'pix'           => __('PIX', 'eau-system'),
            'cash'          => __('Cash', 'eau-system'),
            'invoice'       => __('Invoice', 'eau-system'),
            'other'         => __('Other', 'eau-system'),
        );
    }

    /**
     * Registra CPT no JetEngine se disponível
     *
     * @since  1.45.2
     * @return void
     */
    public static function register_to_jet_engine() {
        global $wpdb;
        $table = $wpdb->prefix . 'jet_post_types';

        // Verifica se tabela JetEngine existe
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            return;
        }

        // Verifica se precisa atualizar baseado na versão
        $version_key = 'eau_payment_jet_version';
        $saved_version = get_option($version_key);

        if (self::exists_in_jet_engine()) {
            // Atualiza se versão mudou
            if ($saved_version !== self::VERSION) {
                $wpdb->delete($table, array('slug' => self::POST_TYPE), array('%s'));
                self::save_to_jet_engine();
                update_option($version_key, self::VERSION);
            }
            return;
        }

        self::save_to_jet_engine();
        update_option($version_key, self::VERSION);
    }

    /**
     * Verifica se CPT existe na tabela JetEngine
     *
     * @since  1.45.2
     * @return bool
     */
    private static function exists_in_jet_engine() {
        global $wpdb;
        $table = $wpdb->prefix . 'jet_post_types';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            return false;
        }

        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE slug = %s",
            self::POST_TYPE
        ));
    }

    /**
     * Salva configuração do CPT na tabela JetEngine
     *
     * @since  1.45.2
     * @return int|false
     */
    private static function save_to_jet_engine() {
        global $wpdb;
        $table = $wpdb->prefix . 'jet_post_types';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            return false;
        }

        $labels = array(
            'name'          => 'Payments',
            'singular_name' => 'Payment',
            'menu_name'     => 'Payments',
        );

        $args = array(
            'public'              => false,
            'publicly_queryable'  => false,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'show_in_admin_bar'   => false,
            'show_in_nav_menus'   => false,
            'query_var'           => false,
            'has_archive'         => false,
            'hierarchical'        => false,
            'show_in_rest'        => false,
            'menu_position'       => 27,
            'menu_icon'           => 'dashicons-money-alt',
            'capability_type'     => 'post',
            'map_meta_cap'        => true,
            'supports'            => array('title'),
            'rewrite'             => false,
        );

        $meta_fields = self::get_jet_meta_fields();

        $data = array(
            'slug'        => self::POST_TYPE,
            'status'      => 'publish',
            'labels'      => maybe_serialize($labels),
            'args'        => maybe_serialize($args),
            'meta_fields' => maybe_serialize($meta_fields),
        );

        return $wpdb->insert($table, $data, array('%s', '%s', '%s', '%s', '%s'));
    }

    /**
     * Retorna configuração de meta fields para JetEngine
     *
     * @since  1.45.2
     * @return array
     */
    private static function get_jet_meta_fields() {
        $p = self::META_PREFIX;
        $base_id = 95000;

        $payment_methods = array(
            array('key' => 'credit_card', 'value' => 'Credit Card'),
            array('key' => 'debit_card', 'value' => 'Debit Card'),
            array('key' => 'bank_transfer', 'value' => 'Bank Transfer'),
            array('key' => 'pix', 'value' => 'PIX'),
            array('key' => 'cash', 'value' => 'Cash'),
            array('key' => 'invoice', 'value' => 'Invoice'),
            array('key' => 'other', 'value' => 'Other'),
        );

        $status_options = array(
            array('key' => 'confirmed', 'value' => 'Confirmed'),
            array('key' => 'pending', 'value' => 'Pending'),
            array('key' => 'refunded', 'value' => 'Refunded'),
        );

        return array(
            array('title' => 'Registration ID', 'name' => $p.'registration_id', 'object_type' => 'field', 'type' => 'number', 'width' => '50%', 'id' => $base_id++),
            array('title' => 'Event ID', 'name' => $p.'event_id', 'object_type' => 'field', 'type' => 'number', 'width' => '50%', 'id' => $base_id++),
            array('title' => 'User ID', 'name' => $p.'user_id', 'object_type' => 'field', 'type' => 'number', 'width' => '50%', 'id' => $base_id++),
            array('title' => 'Amount', 'name' => $p.'amount', 'object_type' => 'field', 'type' => 'number', 'width' => '50%', 'min_value' => 0, 'step_value' => 0.01, 'id' => $base_id++),
            array('title' => 'Payment Date', 'name' => $p.'payment_date', 'object_type' => 'field', 'type' => 'date', 'width' => '50%', 'id' => $base_id++),
            array('title' => 'Payment Method', 'name' => $p.'payment_method', 'object_type' => 'field', 'type' => 'select', 'options' => $payment_methods, 'width' => '50%', 'id' => $base_id++),
            array('title' => 'Transaction ID', 'name' => $p.'transaction_id', 'object_type' => 'field', 'type' => 'text', 'width' => '100%', 'id' => $base_id++),
            array('title' => 'Receipt URL', 'name' => $p.'receipt_url', 'object_type' => 'field', 'type' => 'text', 'width' => '100%', 'id' => $base_id++),
            array('title' => 'Receipt File', 'name' => $p.'receipt_id', 'object_type' => 'field', 'type' => 'media', 'value_format' => 'id', 'id' => $base_id++),
            array('title' => 'Notes', 'name' => $p.'notes', 'object_type' => 'field', 'type' => 'textarea', 'width' => '100%', 'id' => $base_id++),
            array('title' => 'Created By', 'name' => $p.'created_by', 'object_type' => 'field', 'type' => 'number', 'width' => '50%', 'id' => $base_id++),
            array('title' => 'Status', 'name' => $p.'status', 'object_type' => 'field', 'type' => 'select', 'options' => $status_options, 'default_value' => 'confirmed', 'width' => '50%', 'id' => $base_id++),
        );
    }
}
