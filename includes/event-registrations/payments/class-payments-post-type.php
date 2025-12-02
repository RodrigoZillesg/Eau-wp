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
     * Inicializa o Post Type
     *
     * @since  1.45.0
     * @return void
     */
    public static function init() {
        add_action('init', array(__CLASS__, 'register_post_type'));
        add_action('init', array(__CLASS__, 'register_meta_fields'));
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
            'show_in_menu'        => false, // Não mostrar no menu admin
            'query_var'           => false,
            'rewrite'             => false,
            'capability_type'     => 'post',
            'has_archive'         => false,
            'hierarchical'        => false,
            'menu_position'       => null,
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
}
