<?php
namespace EauSystem\Coupons;

/**
 * Modelo CRUD para cupons de desconto
 *
 * @since 1.69.0
 */
class Eau_Coupons_Model {

    /**
     * Busca cupons com paginação e filtros
     *
     * @param array $args Argumentos de busca
     * @return array
     */
    public static function get_coupons($args = array()) {
        global $wpdb;

        $defaults = array(
            'page' => 1,
            'per_page' => 20,
            'search' => '',
            'status' => '',
            'orderby' => 'created_at',
            'order' => 'DESC',
        );

        $args = wp_parse_args($args, $defaults);
        $table_name = $wpdb->prefix . Eau_Coupons_Database::TABLE_COUPONS;

        // Build WHERE clause
        $where = array('1=1');
        $values = array();

        // Search
        if (!empty($args['search'])) {
            $where[] = '(code LIKE %s OR description LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $values[] = $search_term;
            $values[] = $search_term;
        }

        // Status filter
        if (!empty($args['status'])) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }

        $where_sql = implode(' AND ', $where);

        // Count total
        $count_sql = "SELECT COUNT(*) FROM $table_name WHERE $where_sql";
        if (!empty($values)) {
            $count_sql = $wpdb->prepare($count_sql, $values);
        }
        $total = (int) $wpdb->get_var($count_sql);

        // Order by
        $allowed_orderby = array('id', 'code', 'discount_value', 'usage_count', 'valid_from', 'valid_until', 'created_at', 'status');
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'created_at';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

        // Pagination
        $offset = ($args['page'] - 1) * $args['per_page'];
        $limit = (int) $args['per_page'];

        // Query
        $sql = "SELECT * FROM $table_name WHERE $where_sql ORDER BY $orderby $order LIMIT %d OFFSET %d";

        if (!empty($values)) {
            $values[] = $limit;
            $values[] = $offset;
            $sql = $wpdb->prepare($sql, $values);
        } else {
            $sql = $wpdb->prepare($sql, $limit, $offset);
        }

        $results = $wpdb->get_results($sql, ARRAY_A);

        // Add related events for each coupon
        foreach ($results as &$coupon) {
            $coupon['events'] = self::get_coupon_events($coupon['id']);
        }

        return array(
            'coupons' => $results,
            'total' => $total,
            'page' => (int) $args['page'],
            'per_page' => (int) $args['per_page'],
            'total_pages' => ceil($total / $args['per_page']),
        );
    }

    /**
     * Busca um cupom por ID
     *
     * @param int $id ID do cupom
     * @return array|null
     */
    public static function get_coupon($id) {
        global $wpdb;

        $table_name = $wpdb->prefix . Eau_Coupons_Database::TABLE_COUPONS;
        $sql = $wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id);
        $coupon = $wpdb->get_row($sql, ARRAY_A);

        if ($coupon) {
            $coupon['events'] = self::get_coupon_events($coupon['id']);
        }

        return $coupon;
    }

    /**
     * Busca um cupom pelo código
     *
     * @param string $code Código do cupom
     * @return array|null
     */
    public static function get_coupon_by_code($code) {
        global $wpdb;

        $table_name = $wpdb->prefix . Eau_Coupons_Database::TABLE_COUPONS;
        $sql = $wpdb->prepare("SELECT * FROM $table_name WHERE code = %s", strtoupper(trim($code)));
        $coupon = $wpdb->get_row($sql, ARRAY_A);

        if ($coupon) {
            $coupon['events'] = self::get_coupon_events($coupon['id']);
        }

        return $coupon;
    }

    /**
     * Cria um novo cupom
     *
     * @param array $data Dados do cupom
     * @return int|false ID do cupom criado ou false em caso de erro
     */
    public static function create_coupon($data) {
        global $wpdb;

        $table_name = $wpdb->prefix . Eau_Coupons_Database::TABLE_COUPONS;

        // Valida código único
        $existing = self::get_coupon_by_code($data['code']);
        if ($existing) {
            return false;
        }

        $coupon_data = array(
            'code' => strtoupper(sanitize_text_field($data['code'])),
            'description' => sanitize_text_field($data['description'] ?? ''),
            'discount_type' => in_array($data['discount_type'], array('percentage', 'fixed')) ? $data['discount_type'] : 'percentage',
            'discount_value' => floatval($data['discount_value']),
            'min_order_value' => !empty($data['min_order_value']) ? floatval($data['min_order_value']) : null,
            'max_discount' => !empty($data['max_discount']) ? floatval($data['max_discount']) : null,
            'valid_from' => !empty($data['valid_from']) ? $data['valid_from'] : null,
            'valid_until' => !empty($data['valid_until']) ? $data['valid_until'] : null,
            'usage_limit' => !empty($data['usage_limit']) ? intval($data['usage_limit']) : null,
            'usage_limit_per_user' => !empty($data['usage_limit_per_user']) ? intval($data['usage_limit_per_user']) : 1,
            'status' => in_array($data['status'], array('active', 'inactive', 'expired')) ? $data['status'] : 'active',
            'event_scope' => in_array($data['event_scope'], array('all', 'specific')) ? $data['event_scope'] : 'all',
            'created_by' => get_current_user_id(),
        );

        $format = array('%s', '%s', '%s', '%f', '%f', '%f', '%s', '%s', '%d', '%d', '%s', '%s', '%d');

        // Handle NULL values
        foreach ($coupon_data as $key => $value) {
            if ($value === null) {
                $coupon_data[$key] = null;
            }
        }

        $result = $wpdb->insert($table_name, $coupon_data);

        if (!$result) {
            return false;
        }

        $coupon_id = $wpdb->insert_id;

        // Se event_scope é 'specific', salva os eventos relacionados
        if ($coupon_data['event_scope'] === 'specific' && !empty($data['event_ids'])) {
            self::set_coupon_events($coupon_id, $data['event_ids']);
        }

        return $coupon_id;
    }

    /**
     * Atualiza um cupom existente
     *
     * @param int $id ID do cupom
     * @param array $data Dados do cupom
     * @return bool
     */
    public static function update_coupon($id, $data) {
        global $wpdb;

        $table_name = $wpdb->prefix . Eau_Coupons_Database::TABLE_COUPONS;

        // Verifica se cupom existe
        $existing = self::get_coupon($id);
        if (!$existing) {
            return false;
        }

        // Se código está sendo alterado, valida unicidade
        if (isset($data['code']) && strtoupper($data['code']) !== $existing['code']) {
            $code_exists = self::get_coupon_by_code($data['code']);
            if ($code_exists) {
                return false;
            }
        }

        $update_data = array();
        $format = array();

        // Campos atualizáveis
        $fields = array(
            'code' => '%s',
            'description' => '%s',
            'discount_type' => '%s',
            'discount_value' => '%f',
            'min_order_value' => '%f',
            'max_discount' => '%f',
            'valid_from' => '%s',
            'valid_until' => '%s',
            'usage_limit' => '%d',
            'usage_limit_per_user' => '%d',
            'status' => '%s',
            'event_scope' => '%s',
        );

        foreach ($fields as $field => $field_format) {
            if (isset($data[$field])) {
                $value = $data[$field];

                // Sanitização específica por campo
                switch ($field) {
                    case 'code':
                        $value = strtoupper(sanitize_text_field($value));
                        break;
                    case 'description':
                        $value = sanitize_text_field($value);
                        break;
                    case 'discount_type':
                        $value = in_array($value, array('percentage', 'fixed')) ? $value : 'percentage';
                        break;
                    case 'discount_value':
                    case 'min_order_value':
                    case 'max_discount':
                        $value = $value !== '' && $value !== null ? floatval($value) : null;
                        break;
                    case 'usage_limit':
                    case 'usage_limit_per_user':
                        $value = $value !== '' && $value !== null ? intval($value) : null;
                        break;
                    case 'status':
                        $value = in_array($value, array('active', 'inactive', 'expired')) ? $value : 'active';
                        break;
                    case 'event_scope':
                        $value = in_array($value, array('all', 'specific')) ? $value : 'all';
                        break;
                }

                $update_data[$field] = $value;
                $format[] = $field_format;
            }
        }

        if (empty($update_data)) {
            return true;
        }

        $result = $wpdb->update(
            $table_name,
            $update_data,
            array('id' => intval($id)),
            $format,
            array('%d')
        );

        // Se event_scope mudou ou é 'specific', atualiza eventos
        if (isset($data['event_scope'])) {
            if ($data['event_scope'] === 'specific') {
                self::set_coupon_events($id, $data['event_ids'] ?? array());
            } else {
                // Remove todos os eventos se mudou para 'all'
                self::set_coupon_events($id, array());
            }
        }

        return $result !== false;
    }

    /**
     * Deleta um cupom
     *
     * @param int $id ID do cupom
     * @return bool
     */
    public static function delete_coupon($id) {
        global $wpdb;

        $coupons_table = $wpdb->prefix . Eau_Coupons_Database::TABLE_COUPONS;
        $events_table = $wpdb->prefix . Eau_Coupons_Database::TABLE_COUPON_EVENTS;

        // Remove eventos relacionados
        $wpdb->delete($events_table, array('coupon_id' => intval($id)), array('%d'));

        // Remove cupom
        $result = $wpdb->delete(
            $coupons_table,
            array('id' => intval($id)),
            array('%d')
        );

        return $result !== false;
    }

    /**
     * Busca os IDs dos eventos relacionados a um cupom
     *
     * @param int $coupon_id ID do cupom
     * @return array Array de event_ids
     */
    public static function get_coupon_events($coupon_id) {
        global $wpdb;

        $table_name = $wpdb->prefix . Eau_Coupons_Database::TABLE_COUPON_EVENTS;

        $sql = $wpdb->prepare(
            "SELECT event_id FROM $table_name WHERE coupon_id = %d",
            $coupon_id
        );

        return $wpdb->get_col($sql);
    }

    /**
     * Define os eventos relacionados a um cupom
     *
     * @param int $coupon_id ID do cupom
     * @param array $event_ids Array de event_ids
     * @return bool
     */
    public static function set_coupon_events($coupon_id, $event_ids) {
        global $wpdb;

        $table_name = $wpdb->prefix . Eau_Coupons_Database::TABLE_COUPON_EVENTS;

        // Remove todos os eventos atuais
        $wpdb->delete($table_name, array('coupon_id' => intval($coupon_id)), array('%d'));

        // Insere novos eventos
        if (!empty($event_ids)) {
            foreach ($event_ids as $event_id) {
                $wpdb->insert(
                    $table_name,
                    array(
                        'coupon_id' => intval($coupon_id),
                        'event_id' => intval($event_id),
                    ),
                    array('%d', '%d')
                );
            }
        }

        return true;
    }

    /**
     * Incrementa o contador de uso de um cupom
     *
     * @param int $coupon_id ID do cupom
     * @return bool
     */
    public static function increment_usage($coupon_id) {
        global $wpdb;

        $table_name = $wpdb->prefix . Eau_Coupons_Database::TABLE_COUPONS;

        $result = $wpdb->query($wpdb->prepare(
            "UPDATE $table_name SET usage_count = usage_count + 1 WHERE id = %d",
            $coupon_id
        ));

        return $result !== false;
    }

    /**
     * Registra o uso de um cupom
     *
     * @param array $data Dados do uso
     * @return int|false ID do registro ou false
     */
    public static function record_usage($data) {
        global $wpdb;

        $table_name = $wpdb->prefix . Eau_Coupons_Database::TABLE_COUPON_USAGE;

        $usage_data = array(
            'coupon_id' => intval($data['coupon_id']),
            'user_id' => intval($data['user_id']),
            'registration_id' => !empty($data['registration_id']) ? intval($data['registration_id']) : null,
            'event_id' => !empty($data['event_id']) ? intval($data['event_id']) : null,
            'discount_applied' => floatval($data['discount_applied']),
            'original_price' => floatval($data['original_price']),
            'final_price' => floatval($data['final_price']),
        );

        $result = $wpdb->insert($table_name, $usage_data);

        if (!$result) {
            return false;
        }

        // Incrementa contador
        self::increment_usage($data['coupon_id']);

        return $wpdb->insert_id;
    }

    /**
     * Conta quantas vezes um usuário usou um cupom
     *
     * @param int $coupon_id ID do cupom
     * @param int $user_id ID do usuário
     * @return int
     */
    public static function get_user_usage_count($coupon_id, $user_id) {
        global $wpdb;

        $table_name = $wpdb->prefix . Eau_Coupons_Database::TABLE_COUPON_USAGE;

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE coupon_id = %d AND user_id = %d",
            $coupon_id,
            $user_id
        ));

        return (int) $count;
    }

    /**
     * Busca estatísticas de cupons
     *
     * @return array
     */
    public static function get_stats() {
        global $wpdb;

        $table_name = $wpdb->prefix . Eau_Coupons_Database::TABLE_COUPONS;
        $usage_table = $wpdb->prefix . Eau_Coupons_Database::TABLE_COUPON_USAGE;

        // Verifica se tabela existe
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        if (!$table_exists) {
            return array(
                'total' => 0,
                'active' => 0,
                'inactive' => 0,
                'expired' => 0,
                'total_discount' => 0,
            );
        }

        // Total de cupons
        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_name");

        // Ativos
        $active = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'active'");

        // Inativos
        $inactive = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'inactive'");

        // Expirados
        $expired = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'expired'");

        // Total de desconto aplicado
        $usage_exists = $wpdb->get_var("SHOW TABLES LIKE '$usage_table'");
        $total_discount = 0;
        if ($usage_exists) {
            $total_discount = (float) $wpdb->get_var("SELECT COALESCE(SUM(discount_applied), 0) FROM $usage_table");
        }

        return array(
            'total' => $total,
            'active' => $active,
            'inactive' => $inactive,
            'expired' => $expired,
            'total_discount' => $total_discount,
        );
    }

    /**
     * Atualiza status de cupons expirados automaticamente
     *
     * @return int Número de cupons atualizados
     */
    public static function update_expired_coupons() {
        global $wpdb;

        $table_name = $wpdb->prefix . Eau_Coupons_Database::TABLE_COUPONS;

        $result = $wpdb->query(
            "UPDATE $table_name
             SET status = 'expired'
             WHERE status = 'active'
             AND valid_until IS NOT NULL
             AND valid_until < NOW()"
        );

        return $result;
    }
}
