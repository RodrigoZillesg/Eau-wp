<?php
/**
 * Payments Custom Post Type
 *
 * @package    EauSystem
 * @subpackage Payments
 * @since      1.45.0
 */

namespace EauSystem\Payments;

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
    const VERSION = '1.49.9';

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
     * @since  1.49.9 Adicionados campos para membership payments
     * @since  1.53.0 Adicionados campos para importação de CSV legado
     * @return array
     */
    public static function get_meta_fields() {
        return array(
            // Campos comuns
            'payment_type'    => 'string',   // 'event', 'membership', ou 'legacy'
            'user_id'         => 'integer',  // ID do usuário que pagou
            'amount'          => 'number',   // Valor do pagamento
            'payment_date'    => 'string',   // Data do pagamento (Y-m-d)
            'payment_method'  => 'string',   // Método: credit_card, bank_transfer, pix, cash, other
            'transaction_id'  => 'string',   // ID da transação (gateway) - CHAVE ÚNICA para prevenção de duplicatas
            'receipt_url'     => 'string',   // URL do comprovante
            'receipt_id'      => 'integer',  // ID do attachment do comprovante
            'notes'           => 'string',   // Observações
            'created_by'      => 'integer',  // Admin que registrou o pagamento
            'status'          => 'string',   // confirmed, pending, refunded

            // Campos para Event Payments
            'registration_id' => 'integer',  // ID da registration (eau_event_reg)
            'event_id'        => 'integer',  // ID do evento

            // Campos para Membership Payments (v1.49.9)
            'membership_application_id' => 'integer',  // ID da aplicação de membership
            'membership_type'           => 'string',   // Tipo: full_provider, associate_access, etc.
            'membership_period_start'   => 'string',   // Data início do período (Y-m-d)
            'membership_period_end'     => 'string',   // Data fim do período (Y-m-d)

            // Campos para importação de CSV legado (v1.53.0)
            'legacy_order_no'     => 'string',   // Order No do sistema legado
            'legacy_reference'    => 'string',   // Reference Number do sistema legado
            'legacy_description'  => 'string',   // Description do item (para identificar evento/membership)
            'legacy_raw_data'     => 'string',   // JSON com todos os dados originais do CSV
            'legacy_import_date'  => 'string',   // Data/hora da importação (Y-m-d H:i:s)
            'payer_name'          => 'string',   // Nome de quem pagou (pode ser diferente do beneficiário)
            'payer_email'         => 'string',   // Email de quem pagou
            'card_type'           => 'string',   // Tipo do cartão (Visa, Mastercard, Amex, etc)
            'tax_amount'          => 'number',   // Valor do imposto (GST)
            'subtotal_amount'     => 'number',   // Valor sem imposto
        );
    }

    /**
     * Retorna valores padrão dos meta fields
     *
     * @since  1.45.0
     * @since  1.49.9 Adicionados campos para membership payments
     * @since  1.53.0 Adicionados campos para importação de CSV legado
     * @return array
     */
    public static function get_defaults() {
        return array(
            // Campos comuns
            'payment_type'    => 'event',
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

            // Event Payments
            'registration_id' => 0,
            'event_id'        => 0,

            // Membership Payments
            'membership_application_id' => 0,
            'membership_type'           => '',
            'membership_period_start'   => '',
            'membership_period_end'     => '',

            // Legacy Import (v1.53.0)
            'legacy_order_no'     => '',
            'legacy_reference'    => '',
            'legacy_description'  => '',
            'legacy_raw_data'     => '',
            'legacy_import_date'  => '',
            'payer_name'          => '',
            'payer_email'         => '',
            'card_type'           => '',
            'tax_amount'          => 0,
            'subtotal_amount'     => 0,
        );
    }

    /**
     * Verifica se já existe um pagamento com o transaction_id especificado
     *
     * @since  1.53.0
     * @param  string $transaction_id ID da transação
     * @return int|false ID do pagamento existente ou false se não existe
     */
    public static function get_payment_by_transaction_id($transaction_id) {
        if (empty($transaction_id)) {
            return false;
        }

        $query = new \WP_Query(array(
            'post_type'      => self::POST_TYPE,
            'posts_per_page' => 1,
            'post_status'    => 'any',
            'meta_query'     => array(
                array(
                    'key'   => self::META_PREFIX . 'transaction_id',
                    'value' => $transaction_id,
                ),
            ),
            'fields'         => 'ids',
        ));

        if ($query->have_posts()) {
            return $query->posts[0];
        }

        return false;
    }

    /**
     * Verifica se já existe um pagamento com o legacy_order_no especificado
     *
     * @since  1.53.0
     * @param  string $order_no Order Number do sistema legado
     * @return int|false ID do pagamento existente ou false se não existe
     */
    public static function get_payment_by_legacy_order_no($order_no) {
        if (empty($order_no)) {
            return false;
        }

        $query = new \WP_Query(array(
            'post_type'      => self::POST_TYPE,
            'posts_per_page' => 1,
            'post_status'    => 'any',
            'meta_query'     => array(
                array(
                    'key'   => self::META_PREFIX . 'legacy_order_no',
                    'value' => $order_no,
                ),
            ),
            'fields'         => 'ids',
        ));

        if ($query->have_posts()) {
            return $query->posts[0];
        }

        return false;
    }

    /**
     * Cria um novo pagamento
     *
     * @since  1.45.0
     * @since  1.49.9 Suporte para membership payments
     * @param  array $data Dados do pagamento
     * @return int|WP_Error ID do post ou erro
     */
    public static function create_payment($data) {
        $defaults = self::get_defaults();
        $data = wp_parse_args($data, $defaults);

        // Gera título do pagamento baseado no tipo
        if ($data['payment_type'] === 'membership') {
            $title = sprintf(
                'Membership Payment #%s - User #%d',
                date('YmdHis'),
                $data['user_id']
            );
        } elseif ($data['payment_type'] === 'legacy') {
            // Pagamento importado do sistema legado
            $order_no = !empty($data['legacy_order_no']) ? $data['legacy_order_no'] : date('YmdHis');
            $payer = !empty($data['payer_name']) ? $data['payer_name'] : 'Unknown';
            $title = sprintf(
                'Legacy Payment #%s - %s',
                $order_no,
                $payer
            );
        } else {
            $title = sprintf(
                'Payment #%s - Reg #%d',
                date('YmdHis'),
                $data['registration_id']
            );
        }

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
     * @since  1.49.9 Adicionados campos para membership payments
     * @param  WP_Post $payment Post do pagamento
     * @return array Dados formatados
     */
    public static function format_payment($payment) {
        $prefix = self::META_PREFIX;
        $payment_type = get_post_meta($payment->ID, $prefix . 'payment_type', true) ?: 'event';

        $data = array(
            'id'              => $payment->ID,
            'payment_type'    => $payment_type,
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

        // Campos específicos de Event Payments
        if ($payment_type === 'event') {
            $data['registration_id'] = intval(get_post_meta($payment->ID, $prefix . 'registration_id', true));
            $data['event_id'] = intval(get_post_meta($payment->ID, $prefix . 'event_id', true));
        }

        // Campos específicos de Membership Payments
        if ($payment_type === 'membership') {
            $data['membership_application_id'] = intval(get_post_meta($payment->ID, $prefix . 'membership_application_id', true));
            $data['membership_type'] = get_post_meta($payment->ID, $prefix . 'membership_type', true);
            $data['membership_period_start'] = get_post_meta($payment->ID, $prefix . 'membership_period_start', true);
            $data['membership_period_end'] = get_post_meta($payment->ID, $prefix . 'membership_period_end', true);
        }

        return $data;
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

    // =========================================================================
    // MEMBERSHIP PAYMENT METHODS (v1.49.9)
    // =========================================================================

    /**
     * Cria um novo pagamento de membership
     *
     * @since  1.49.9
     * @param  array $data Dados do pagamento
     * @return int|WP_Error ID do post ou erro
     */
    public static function create_membership_payment($data) {
        $data['payment_type'] = 'membership';
        return self::create_payment($data);
    }

    /**
     * Obtém pagamentos de membership de um usuário
     *
     * @since  1.49.9
     * @param  int $user_id ID do usuário
     * @return array Lista de pagamentos
     */
    public static function get_payments_by_user_membership($user_id) {
        $payments = get_posts(array(
            'post_type'      => self::POST_TYPE,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_query'     => array(
                'relation' => 'AND',
                array(
                    'key'     => self::META_PREFIX . 'payment_type',
                    'value'   => 'membership',
                    'compare' => '=',
                ),
                array(
                    'key'     => self::META_PREFIX . 'user_id',
                    'value'   => $user_id,
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
     * Obtém pagamentos de uma aplicação de membership
     *
     * @since  1.49.9
     * @param  int $application_id ID da aplicação
     * @return array Lista de pagamentos
     */
    public static function get_payments_by_application($application_id) {
        $payments = get_posts(array(
            'post_type'      => self::POST_TYPE,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_query'     => array(
                'relation' => 'AND',
                array(
                    'key'     => self::META_PREFIX . 'payment_type',
                    'value'   => 'membership',
                    'compare' => '=',
                ),
                array(
                    'key'     => self::META_PREFIX . 'membership_application_id',
                    'value'   => $application_id,
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
     * Obtém total pago de membership de um usuário (período atual)
     *
     * @since  1.49.9
     * @param  int    $user_id ID do usuário
     * @param  string $period_start Data início do período (Y-m-d)
     * @param  string $period_end Data fim do período (Y-m-d)
     * @return float Total pago
     */
    public static function get_membership_total_paid($user_id, $period_start = '', $period_end = '') {
        $meta_query = array(
            'relation' => 'AND',
            array(
                'key'     => self::META_PREFIX . 'payment_type',
                'value'   => 'membership',
                'compare' => '=',
            ),
            array(
                'key'     => self::META_PREFIX . 'user_id',
                'value'   => $user_id,
                'compare' => '=',
                'type'    => 'NUMERIC',
            ),
        );

        // Filtra por período se especificado
        if (!empty($period_start)) {
            $meta_query[] = array(
                'key'     => self::META_PREFIX . 'membership_period_start',
                'value'   => $period_start,
                'compare' => '>=',
            );
        }

        if (!empty($period_end)) {
            $meta_query[] = array(
                'key'     => self::META_PREFIX . 'membership_period_end',
                'value'   => $period_end,
                'compare' => '<=',
            );
        }

        $payments = get_posts(array(
            'post_type'      => self::POST_TYPE,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_query'     => $meta_query,
        ));

        $total = 0;
        foreach ($payments as $payment) {
            $status = get_post_meta($payment->ID, self::META_PREFIX . 'status', true);
            if ($status === 'confirmed') {
                $total += floatval(get_post_meta($payment->ID, self::META_PREFIX . 'amount', true));
            }
        }

        return $total;
    }

    /**
     * Obtém total pago de membership por application_id
     *
     * @since  1.51.0
     * @param  int $application_id ID da membership application
     * @return float Total pago
     */
    public static function get_membership_total_paid_by_application($application_id) {
        $payments = get_posts(array(
            'post_type'      => self::POST_TYPE,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_query'     => array(
                'relation' => 'AND',
                array(
                    'key'     => self::META_PREFIX . 'payment_type',
                    'value'   => 'membership',
                    'compare' => '=',
                ),
                array(
                    'key'     => self::META_PREFIX . 'membership_application_id',
                    'value'   => $application_id,
                    'compare' => '=',
                    'type'    => 'NUMERIC',
                ),
            ),
        ));

        $total = 0;
        foreach ($payments as $payment) {
            $status = get_post_meta($payment->ID, self::META_PREFIX . 'status', true);
            if ($status === 'confirmed') {
                $total += floatval(get_post_meta($payment->ID, self::META_PREFIX . 'amount', true));
            }
        }

        return $total;
    }

    /**
     * Obtém todos os pagamentos de membership (para listagem admin)
     *
     * @since  1.49.9
     * @param  array $args Argumentos de busca
     * @return array Lista de pagamentos e total
     */
    public static function get_all_membership_payments($args = array()) {
        $defaults = array(
            'page'     => 1,
            'per_page' => 20,
            'search'   => '',
            'status'   => '',
            'membership_type' => '',
            'order_by' => 'date',
            'order'    => 'DESC',
        );

        $args = wp_parse_args($args, $defaults);

        $meta_query = array(
            array(
                'key'     => self::META_PREFIX . 'payment_type',
                'value'   => 'membership',
                'compare' => '=',
            ),
        );

        if (!empty($args['status'])) {
            $meta_query[] = array(
                'key'     => self::META_PREFIX . 'status',
                'value'   => $args['status'],
                'compare' => '=',
            );
        }

        if (!empty($args['membership_type'])) {
            $meta_query[] = array(
                'key'     => self::META_PREFIX . 'membership_type',
                'value'   => $args['membership_type'],
                'compare' => '=',
            );
        }

        $query_args = array(
            'post_type'      => self::POST_TYPE,
            'posts_per_page' => $args['per_page'],
            'paged'          => $args['page'],
            'post_status'    => 'publish',
            'meta_query'     => $meta_query,
            'orderby'        => $args['order_by'],
            'order'          => $args['order'],
        );

        // Search by user name/email
        if (!empty($args['search'])) {
            // Busca usuários que correspondem à pesquisa
            $user_query = new \WP_User_Query(array(
                'search'         => '*' . $args['search'] . '*',
                'search_columns' => array('user_login', 'user_email', 'display_name'),
                'fields'         => 'ID',
            ));
            $user_ids = $user_query->get_results();

            if (!empty($user_ids)) {
                $meta_query[] = array(
                    'key'     => self::META_PREFIX . 'user_id',
                    'value'   => $user_ids,
                    'compare' => 'IN',
                    'type'    => 'NUMERIC',
                );
                $query_args['meta_query'] = $meta_query;
            } else {
                // Se não encontrou usuários, retorna vazio
                return array(
                    'payments' => array(),
                    'total'    => 0,
                    'pages'    => 0,
                );
            }
        }

        $query = new \WP_Query($query_args);
        $payments = array();

        foreach ($query->posts as $payment) {
            $formatted = self::format_payment($payment);

            // Adiciona dados do usuário
            if ($formatted['user_id']) {
                $user = get_userdata($formatted['user_id']);
                if ($user) {
                    $formatted['user_name'] = $user->display_name;
                    $formatted['user_email'] = $user->user_email;
                }
            }

            // Adiciona label do membership type
            if (!empty($formatted['membership_type'])) {
                $type = \EauSystem\Eau_Membership_Types::get_by_key($formatted['membership_type']);
                $formatted['membership_type_label'] = $type ? $type->type_label : $formatted['membership_type'];
            }

            $payments[] = $formatted;
        }

        return array(
            'payments' => $payments,
            'total'    => $query->found_posts,
            'pages'    => $query->max_num_pages,
        );
    }

    /**
     * Obtém estatísticas de pagamentos de membership
     *
     * @since  1.49.9
     * @return array Estatísticas
     */
    public static function get_membership_payment_stats() {
        global $wpdb;
        $prefix = self::META_PREFIX;

        // Total de pagamentos de membership confirmados
        $total_received = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(pm_amount.meta_value)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm_type ON p.ID = pm_type.post_id AND pm_type.meta_key = %s
            INNER JOIN {$wpdb->postmeta} pm_amount ON p.ID = pm_amount.post_id AND pm_amount.meta_key = %s
            INNER JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = %s
            WHERE p.post_type = %s
            AND p.post_status = 'publish'
            AND pm_type.meta_value = 'membership'
            AND pm_status.meta_value = 'confirmed'",
            $prefix . 'payment_type',
            $prefix . 'amount',
            $prefix . 'status',
            self::POST_TYPE
        ));

        // Total pendente
        $total_pending = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(pm_amount.meta_value)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm_type ON p.ID = pm_type.post_id AND pm_type.meta_key = %s
            INNER JOIN {$wpdb->postmeta} pm_amount ON p.ID = pm_amount.post_id AND pm_amount.meta_key = %s
            INNER JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = %s
            WHERE p.post_type = %s
            AND p.post_status = 'publish'
            AND pm_type.meta_value = 'membership'
            AND pm_status.meta_value = 'pending'",
            $prefix . 'payment_type',
            $prefix . 'amount',
            $prefix . 'status',
            self::POST_TYPE
        ));

        // Contagem de pagamentos por status
        $count_confirmed = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm_type ON p.ID = pm_type.post_id AND pm_type.meta_key = %s
            INNER JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = %s
            WHERE p.post_type = %s
            AND p.post_status = 'publish'
            AND pm_type.meta_value = 'membership'
            AND pm_status.meta_value = 'confirmed'",
            $prefix . 'payment_type',
            $prefix . 'status',
            self::POST_TYPE
        ));

        $count_pending = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm_type ON p.ID = pm_type.post_id AND pm_type.meta_key = %s
            INNER JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = %s
            WHERE p.post_type = %s
            AND p.post_status = 'publish'
            AND pm_type.meta_value = 'membership'
            AND pm_status.meta_value = 'pending'",
            $prefix . 'payment_type',
            $prefix . 'status',
            self::POST_TYPE
        ));

        // Pagamentos este mês
        $current_month_start = date('Y-m-01');
        $payments_this_month = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm_type ON p.ID = pm_type.post_id AND pm_type.meta_key = %s
            WHERE p.post_type = %s
            AND p.post_status = 'publish'
            AND pm_type.meta_value = 'membership'
            AND p.post_date >= %s",
            $prefix . 'payment_type',
            self::POST_TYPE,
            $current_month_start
        ));

        return array(
            'total_received'     => floatval($total_received) ?: 0,
            'total_pending'      => floatval($total_pending) ?: 0,
            'count_confirmed'    => intval($count_confirmed) ?: 0,
            'count_pending'      => intval($count_pending) ?: 0,
            'payments_this_month' => intval($payments_this_month) ?: 0,
        );
    }

    // =========================================================================
    // ALL PAYMENTS METHODS (v1.50.1) - Unified listing for Payments Management
    // =========================================================================

    /**
     * Obtém todos os pagamentos (eventos + membership) para listagem unificada
     *
     * @since  1.50.1
     * @param  array $args Argumentos de busca
     * @return array Lista de pagamentos e total
     */
    public static function get_all_payments($args = array()) {
        $defaults = array(
            'page'         => 1,
            'per_page'     => 20,
            'search'       => '',
            'status'       => '',
            'payment_type' => '', // 'event', 'membership', or empty for all
            'order_by'     => 'date',
            'order'        => 'DESC',
        );

        $args = wp_parse_args($args, $defaults);

        $meta_query = array();

        // Filter by payment type
        if (!empty($args['payment_type'])) {
            $meta_query[] = array(
                'key'     => self::META_PREFIX . 'payment_type',
                'value'   => $args['payment_type'],
                'compare' => '=',
            );
        }

        // Filter by status
        if (!empty($args['status'])) {
            $meta_query[] = array(
                'key'     => self::META_PREFIX . 'status',
                'value'   => $args['status'],
                'compare' => '=',
            );
        }

        $query_args = array(
            'post_type'      => self::POST_TYPE,
            'posts_per_page' => $args['per_page'],
            'paged'          => $args['page'],
            'post_status'    => 'publish',
            'orderby'        => $args['order_by'],
            'order'          => $args['order'],
        );

        if (!empty($meta_query)) {
            $query_args['meta_query'] = $meta_query;
        }

        // Search by user name/email
        if (!empty($args['search'])) {
            $user_query = new \WP_User_Query(array(
                'search'         => '*' . $args['search'] . '*',
                'search_columns' => array('user_login', 'user_email', 'display_name'),
                'fields'         => 'ID',
            ));
            $user_ids = $user_query->get_results();

            if (!empty($user_ids)) {
                $query_args['meta_query'][] = array(
                    'key'     => self::META_PREFIX . 'user_id',
                    'value'   => $user_ids,
                    'compare' => 'IN',
                    'type'    => 'NUMERIC',
                );
            } else {
                return array(
                    'payments' => array(),
                    'total'    => 0,
                    'pages'    => 0,
                );
            }
        }

        $query = new \WP_Query($query_args);
        $payments = array();

        foreach ($query->posts as $payment) {
            $formatted = self::format_payment($payment);

            // Add user data
            if ($formatted['user_id']) {
                $user = get_userdata($formatted['user_id']);
                if ($user) {
                    $formatted['user_name'] = $user->display_name;
                    $formatted['user_email'] = $user->user_email;
                }
            }

            // Add membership type label
            if ($formatted['payment_type'] === 'membership' && !empty($formatted['membership_type'])) {
                $type = \EauSystem\Eau_Membership_Types::get_by_key($formatted['membership_type']);
                $formatted['membership_type_label'] = $type ? $type->type_label : $formatted['membership_type'];
            }

            // Add event title for event payments
            if ($formatted['payment_type'] === 'event' && !empty($formatted['event_id'])) {
                $event = get_post($formatted['event_id']);
                $formatted['event_title'] = $event ? $event->post_title : 'Unknown Event';
            }

            $payments[] = $formatted;
        }

        return array(
            'payments' => $payments,
            'total'    => $query->found_posts,
            'pages'    => $query->max_num_pages,
        );
    }

    /**
     * Obtém estatísticas de todos os pagamentos (eventos + membership)
     *
     * @since  1.50.1
     * @return array Estatísticas
     */
    public static function get_all_payment_stats() {
        global $wpdb;
        $prefix = self::META_PREFIX;

        // Total received (all types, confirmed)
        $total_received = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(pm_amount.meta_value)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm_amount ON p.ID = pm_amount.post_id AND pm_amount.meta_key = %s
            INNER JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = %s
            WHERE p.post_type = %s
            AND p.post_status = 'publish'
            AND pm_status.meta_value = 'confirmed'",
            $prefix . 'amount',
            $prefix . 'status',
            self::POST_TYPE
        ));

        // Total pending
        $total_pending = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(pm_amount.meta_value)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm_amount ON p.ID = pm_amount.post_id AND pm_amount.meta_key = %s
            INNER JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = %s
            WHERE p.post_type = %s
            AND p.post_status = 'publish'
            AND pm_status.meta_value = 'pending'",
            $prefix . 'amount',
            $prefix . 'status',
            self::POST_TYPE
        ));

        // Count by type
        $count_events = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm_type ON p.ID = pm_type.post_id AND pm_type.meta_key = %s
            WHERE p.post_type = %s
            AND p.post_status = 'publish'
            AND pm_type.meta_value = 'event'",
            $prefix . 'payment_type',
            self::POST_TYPE
        ));

        $count_membership = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm_type ON p.ID = pm_type.post_id AND pm_type.meta_key = %s
            WHERE p.post_type = %s
            AND p.post_status = 'publish'
            AND pm_type.meta_value = 'membership'",
            $prefix . 'payment_type',
            self::POST_TYPE
        ));

        // Payments this month
        $current_month_start = date('Y-m-01');
        $payments_this_month = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*)
            FROM {$wpdb->posts} p
            WHERE p.post_type = %s
            AND p.post_status = 'publish'
            AND p.post_date >= %s",
            self::POST_TYPE,
            $current_month_start
        ));

        // Revenue this month
        $revenue_this_month = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(pm_amount.meta_value)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm_amount ON p.ID = pm_amount.post_id AND pm_amount.meta_key = %s
            INNER JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = %s
            WHERE p.post_type = %s
            AND p.post_status = 'publish'
            AND pm_status.meta_value = 'confirmed'
            AND p.post_date >= %s",
            $prefix . 'amount',
            $prefix . 'status',
            self::POST_TYPE,
            $current_month_start
        ));

        return array(
            'total_received'      => floatval($total_received) ?: 0,
            'total_pending'       => floatval($total_pending) ?: 0,
            'count_events'        => intval($count_events) ?: 0,
            'count_membership'    => intval($count_membership) ?: 0,
            'payments_this_month' => intval($payments_this_month) ?: 0,
            'revenue_this_month'  => floatval($revenue_this_month) ?: 0,
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
