<?php
namespace EauSystem;

/**
 * Gerencia a tabela de categorias de eventos
 *
 * @since 1.61.0
 */
class Eau_Event_Categories_Database {

    /**
     * Nome da tabela
     */
    const TABLE_NAME = 'eau_event_categories';

    /**
     * Cria a tabela de categorias de eventos
     */
    public static function create_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE_NAME;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            category_serial varchar(255) NOT NULL,
            category_name varchar(255) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY category_serial (category_serial)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Busca todas as categorias
     *
     * @param array $args Argumentos de busca (page, per_page, search, orderby, order)
     * @return array
     */
    public static function get_categories($args = array()) {
        global $wpdb;

        $defaults = array(
            'page' => 1,
            'per_page' => 20,
            'search' => '',
            'orderby' => 'category_name',
            'order' => 'ASC',
        );

        $args = wp_parse_args($args, $defaults);
        $table_name = $wpdb->prefix . self::TABLE_NAME;

        // Base query
        $where = array('1=1');
        $values = array();

        // Search
        if (!empty($args['search'])) {
            $where[] = '(category_name LIKE %s OR category_serial LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $values[] = $search_term;
            $values[] = $search_term;
        }

        $where_sql = implode(' AND ', $where);

        // Count total
        $count_sql = "SELECT COUNT(*) FROM $table_name WHERE $where_sql";
        if (!empty($values)) {
            $count_sql = $wpdb->prepare($count_sql, $values);
        }
        $total = (int) $wpdb->get_var($count_sql);

        // Order by
        $allowed_orderby = array('id', 'category_serial', 'category_name', 'created_at');
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'category_name';
        $order = strtoupper($args['order']) === 'DESC' ? 'DESC' : 'ASC';

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

        return array(
            'categories' => $results,
            'total' => $total,
            'page' => (int) $args['page'],
            'per_page' => (int) $args['per_page'],
            'total_pages' => ceil($total / $args['per_page']),
        );
    }

    /**
     * Busca uma categoria por ID
     *
     * @param int $id ID da categoria
     * @return array|null
     */
    public static function get_category($id) {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE_NAME;

        $sql = $wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id);
        return $wpdb->get_row($sql, ARRAY_A);
    }

    /**
     * Insere ou atualiza uma categoria
     *
     * @param array $data Dados da categoria
     * @return int|false ID da categoria ou false em caso de erro
     */
    public static function save_category($data) {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE_NAME;

        $category_data = array(
            'category_serial' => sanitize_text_field($data['category_serial']),
            'category_name' => sanitize_text_field($data['category_name']),
        );

        $format = array('%s', '%s');

        // Update
        if (!empty($data['id'])) {
            $result = $wpdb->update(
                $table_name,
                $category_data,
                array('id' => intval($data['id'])),
                $format,
                array('%d')
            );

            return $result !== false ? intval($data['id']) : false;
        }

        // Insert
        $result = $wpdb->insert($table_name, $category_data, $format);
        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Deleta uma categoria
     *
     * @param int $id ID da categoria
     * @return bool
     */
    public static function delete_category($id) {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE_NAME;

        $result = $wpdb->delete(
            $table_name,
            array('id' => intval($id)),
            array('%d')
        );

        return $result !== false;
    }

    /**
     * Busca categorias distintas dos eventos atuais
     *
     * @return array
     */
    public static function get_distinct_categories_from_events() {
        global $wpdb;

        $sql = "SELECT DISTINCT
                    pm1.meta_value as category_serial,
                    pm2.meta_value as category_name
                FROM {$wpdb->postmeta} pm1
                INNER JOIN {$wpdb->postmeta} pm2 ON pm1.post_id = pm2.post_id
                INNER JOIN {$wpdb->posts} p ON p.ID = pm1.post_id
                WHERE p.post_type = 'event'
                AND p.post_status = 'publish'
                AND pm1.meta_key = 'event_category_serial'
                AND pm2.meta_key = 'event_category'
                AND pm1.meta_value != ''
                AND pm2.meta_value != ''
                ORDER BY pm2.meta_value ASC";

        return $wpdb->get_results($sql, ARRAY_A);
    }

    /**
     * Sincroniza categorias dos eventos com a tabela
     * Adiciona novas categorias encontradas que ainda não existem
     *
     * @return array Estatísticas da sincronização
     */
    public static function sync_categories_from_events() {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE_NAME;
        $event_categories = self::get_distinct_categories_from_events();

        $stats = array(
            'total_found' => count($event_categories),
            'added' => 0,
            'skipped' => 0,
        );

        foreach ($event_categories as $event_cat) {
            // Verifica se já existe
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE category_serial = %s",
                $event_cat['category_serial']
            ));

            if (!$exists) {
                // Adiciona nova categoria
                $result = $wpdb->insert(
                    $table_name,
                    array(
                        'category_serial' => $event_cat['category_serial'],
                        'category_name' => $event_cat['category_name'],
                    ),
                    array('%s', '%s')
                );

                if ($result) {
                    $stats['added']++;
                }
            } else {
                $stats['skipped']++;
            }
        }

        return $stats;
    }

    /**
     * Retorna todas as categorias para uso em selects
     *
     * @return array
     */
    public static function get_all_for_select() {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE_NAME;

        $sql = "SELECT id, category_serial, category_name FROM $table_name ORDER BY category_name ASC";

        return $wpdb->get_results($sql, ARRAY_A);
    }
}
