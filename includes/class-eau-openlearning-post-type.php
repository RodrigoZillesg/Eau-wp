<?php
namespace EauSystem;

/**
 * Registra o Post Type para cursos do OpenLearning
 *
 * Compatível com JetEngine - salva diretamente na tabela wp_jet_post_types
 *
 * @since 1.41.0
 * @updated 1.42.1 - Compatibilidade total com JetEngine
 */
class Eau_OpenLearning_Post_Type {

    /**
     * Slug do Post Type
     */
    const POST_TYPE = 'ol_course';

    /**
     * Prefixo para meta fields
     */
    const META_PREFIX = 'olc_';

    /**
     * Retorna a definição dos meta fields no formato JetEngine
     */
    public static function get_jet_meta_fields() {
        return array(
            // ID único do curso no OpenLearning
            array(
                'name' => self::META_PREFIX . 'course_id',
                'title' => 'OpenLearning Course ID',
                'type' => 'text',
                'object_type' => 'field',
                'width' => '100%',
                'is_required' => false,
                'is_multiple' => false,
                'default_value' => '',
                'conditional_logic' => array(),
            ),
            // URL do curso no OpenLearning
            array(
                'name' => self::META_PREFIX . 'course_url',
                'title' => 'Course URL',
                'type' => 'text',
                'object_type' => 'field',
                'width' => '100%',
                'is_required' => false,
                'is_multiple' => false,
                'default_value' => '',
                'conditional_logic' => array(),
            ),
            // URL da imagem do curso
            array(
                'name' => self::META_PREFIX . 'image_url',
                'title' => 'Image URL',
                'type' => 'text',
                'object_type' => 'field',
                'width' => '100%',
                'is_required' => false,
                'is_multiple' => false,
                'default_value' => '',
                'conditional_logic' => array(),
            ),
            // Preço do curso (0 = gratuito)
            array(
                'name' => self::META_PREFIX . 'price',
                'title' => 'Price',
                'type' => 'number',
                'object_type' => 'field',
                'width' => '100%',
                'is_required' => false,
                'is_multiple' => false,
                'default_value' => '0',
                'conditional_logic' => array(),
            ),
            // Se é self-paced
            array(
                'name' => self::META_PREFIX . 'self_paced',
                'title' => 'Self Paced',
                'type' => 'switcher',
                'object_type' => 'field',
                'width' => '100%',
                'is_required' => false,
                'is_multiple' => false,
                'default_value' => '',
                'conditional_logic' => array(),
            ),
            // Slug original do OpenLearning
            array(
                'name' => self::META_PREFIX . 'slug',
                'title' => 'Original Slug',
                'type' => 'text',
                'object_type' => 'field',
                'width' => '100%',
                'is_required' => false,
                'is_multiple' => false,
                'default_value' => '',
                'conditional_logic' => array(),
            ),
            // Se está visível no frontend (controlado pelo admin)
            array(
                'name' => self::META_PREFIX . 'is_visible',
                'title' => 'Visible on Frontend',
                'type' => 'switcher',
                'object_type' => 'field',
                'width' => '100%',
                'is_required' => false,
                'is_multiple' => false,
                'default_value' => 'true',
                'conditional_logic' => array(),
            ),
            // Se está em destaque
            array(
                'name' => self::META_PREFIX . 'is_featured',
                'title' => 'Featured Course',
                'type' => 'switcher',
                'object_type' => 'field',
                'width' => '100%',
                'is_required' => false,
                'is_multiple' => false,
                'default_value' => '',
                'conditional_logic' => array(),
            ),
            // Ordem de exibição (para destaques)
            array(
                'name' => self::META_PREFIX . 'display_order',
                'title' => 'Display Order',
                'type' => 'number',
                'object_type' => 'field',
                'width' => '100%',
                'is_required' => false,
                'is_multiple' => false,
                'default_value' => '0',
                'conditional_logic' => array(),
            ),
            // Última sincronização
            array(
                'name' => self::META_PREFIX . 'last_synced',
                'title' => 'Last Synced',
                'type' => 'text',
                'object_type' => 'field',
                'width' => '100%',
                'is_required' => false,
                'is_multiple' => false,
                'default_value' => '',
                'conditional_logic' => array(),
            ),
        );
    }

    /**
     * Retorna as labels do Post Type
     */
    public static function get_labels() {
        return array(
            'name' => 'OL Courses',
            'singular_name' => 'OL Course',
            'add_new' => 'Add New',
            'add_new_item' => 'Add New OL Course',
            'edit_item' => 'Edit OL Course',
            'new_item' => 'New OL Course',
            'view_item' => 'View OL Course',
            'search_items' => 'Search OL Courses',
            'not_found' => 'No OL Course found',
            'not_found_in_trash' => 'No OL Course found in trash',
            'parent_item_colon' => '',
            'all_items' => 'All OL Courses',
            'archives' => 'OL Course Archives',
            'menu_name' => 'OL Courses',
        );
    }

    /**
     * Retorna os argumentos do Post Type
     */
    public static function get_args() {
        return array(
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_rest' => true,
            'query_var' => true,
            'capability_type' => 'post',
            'has_archive' => true,
            'hierarchical' => false,
            'menu_position' => 25,
            'menu_icon' => 'dashicons-welcome-learn-more',
            'supports' => array('title', 'editor', 'thumbnail', 'custom-fields'),
            'rewrite' => array('with_front' => false),
            'rewrite_slug' => 'ol-course', // Required by JetEngine
        );
    }

    /**
     * Verifica se o Post Type já existe na tabela do JetEngine
     */
    public static function exists_in_jet_engine() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'jet_post_types';

        // Verifica se a tabela existe
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return false;
        }

        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE slug = %s",
            self::POST_TYPE
        ));

        return !empty($exists);
    }

    /**
     * Obtém o ID do registro no JetEngine
     */
    public static function get_jet_engine_id() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'jet_post_types';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return null;
        }

        return $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE slug = %s",
            self::POST_TYPE
        ));
    }

    /**
     * Cria ou atualiza o Post Type na tabela do JetEngine
     */
    public static function save_to_jet_engine() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'jet_post_types';

        // Verifica se a tabela existe
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            // Se não existe, cria a estrutura básica (JetEngine deve criar, mas por segurança)
            self::create_jet_engine_table();
        }

        $data = array(
            'slug' => self::POST_TYPE,
            'status' => 'publish',
            'labels' => maybe_serialize(self::get_labels()),
            'args' => maybe_serialize(self::get_args()),
            'meta_fields' => maybe_serialize(self::get_jet_meta_fields()),
        );

        $existing_id = self::get_jet_engine_id();

        if ($existing_id) {
            // Atualiza
            $result = $wpdb->update(
                $table_name,
                $data,
                array('id' => $existing_id),
                array('%s', '%s', '%s', '%s', '%s'),
                array('%d')
            );

            return $existing_id;
        } else {
            // Insere
            $inserted = $wpdb->insert(
                $table_name,
                $data,
                array('%s', '%s', '%s', '%s', '%s')
            );

            if ($inserted) {
                return $wpdb->insert_id;
            }
        }

        return false;
    }

    /**
     * Cria a tabela do JetEngine se não existir
     */
    private static function create_jet_engine_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'jet_post_types';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            slug text,
            status text,
            labels longtext,
            args longtext,
            meta_fields longtext,
            PRIMARY KEY (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Salva backup na opção do Eau System
     */
    public static function save_backup() {
        $post_types = get_option('eau_system_post_types', array());
        $post_types[self::POST_TYPE] = array(
            'slug' => self::POST_TYPE,
            'name' => 'OL Course',
            'labels' => self::get_labels(),
            'args' => self::get_args(),
            'meta_fields' => self::get_jet_meta_fields(),
            'created_at' => current_time('mysql'),
        );
        update_option('eau_system_post_types', $post_types);
    }

    /**
     * Remove o Post Type do JetEngine
     */
    public static function remove_from_jet_engine() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'jet_post_types';

        // Remove da tabela do JetEngine
        $wpdb->delete(
            $table_name,
            array('slug' => self::POST_TYPE),
            array('%s')
        );

        // Remove do backup
        $post_types = get_option('eau_system_post_types', array());
        if (isset($post_types[self::POST_TYPE])) {
            unset($post_types[self::POST_TYPE]);
            update_option('eau_system_post_types', $post_types);
        }
    }

    /**
     * Registra o Post Type (chamado pelo WordPress init)
     * Este método é chamado para garantir que o PT funcione mesmo sem JetEngine
     */
    public static function register() {
        // Só registra via código se o JetEngine não estiver ativo
        // Se JetEngine estiver ativo, ele registra automaticamente a partir da tabela
        if (!class_exists('Jet_Engine')) {
            $args = array_merge(self::get_args(), array(
                'labels' => self::get_labels()
            ));

            register_post_type(self::POST_TYPE, $args);

            // Registra os meta fields
            self::register_meta_fields();
        }
    }

    /**
     * Registra os meta fields para REST API (fallback sem JetEngine)
     */
    private static function register_meta_fields() {
        $fields = self::get_jet_meta_fields();

        foreach ($fields as $field) {
            $wp_type = self::get_wp_meta_type($field['type']);

            register_post_meta(self::POST_TYPE, $field['name'], array(
                'type' => $wp_type,
                'single' => true,
                'show_in_rest' => true,
            ));
        }
    }

    /**
     * Converte tipo JetEngine para tipo WP
     */
    private static function get_wp_meta_type($jet_type) {
        $map = array(
            'text' => 'string',
            'textarea' => 'string',
            'number' => 'number',
            'switcher' => 'boolean',
            'checkbox' => 'boolean',
            'date' => 'string',
            'media' => 'integer',
        );

        return isset($map[$jet_type]) ? $map[$jet_type] : 'string';
    }

    /**
     * Registra hooks
     */
    public static function register_hooks() {
        // Registra na ativação do plugin (salva no JetEngine)
        add_action('init', array(__CLASS__, 'maybe_create_post_type'), 5);

        // Fallback: registra via código se JetEngine não estiver ativo
        add_action('init', array(__CLASS__, 'register'), 10);

        // Admin columns (funcionam com ou sem JetEngine)
        add_filter('manage_' . self::POST_TYPE . '_posts_columns', array(__CLASS__, 'add_admin_columns'));
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', array(__CLASS__, 'render_admin_column'), 10, 2);
        add_filter('manage_edit-' . self::POST_TYPE . '_sortable_columns', array(__CLASS__, 'sortable_columns'));
    }

    /**
     * Cria o Post Type no JetEngine se não existir
     */
    public static function maybe_create_post_type() {
        // Só cria se JetEngine estiver ativo
        if (!class_exists('Jet_Engine')) {
            return;
        }

        // Verifica se já existe
        if (self::exists_in_jet_engine()) {
            return;
        }

        // Cria no JetEngine
        $result = self::save_to_jet_engine();

        if ($result) {
            // Salva backup
            self::save_backup();

            // Flush rewrite rules
            flush_rewrite_rules();
        }
    }

    /**
     * Adiciona colunas customizadas na listagem do admin
     */
    public static function add_admin_columns($columns) {
        $new_columns = array();

        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;

            if ($key === 'title') {
                $new_columns['ol_visible'] = 'Visible';
                $new_columns['ol_featured'] = 'Featured';
                $new_columns['ol_price'] = 'Price';
            }
        }

        $new_columns['ol_synced'] = 'Last Synced';

        return $new_columns;
    }

    /**
     * Renderiza valores das colunas customizadas
     */
    public static function render_admin_column($column, $post_id) {
        switch ($column) {
            case 'ol_visible':
                $is_visible = get_post_meta($post_id, self::META_PREFIX . 'is_visible', true);
                echo $is_visible === 'true' || $is_visible === '1' ? '<span style="color: #059669;">&#10003; Yes</span>' : '<span style="color: #dc2626;">&#10005; No</span>';
                break;

            case 'ol_featured':
                $is_featured = get_post_meta($post_id, self::META_PREFIX . 'is_featured', true);
                echo $is_featured === 'true' || $is_featured === '1' ? '<span style="color: #f59e0b;">&#9733; Featured</span>' : '-';
                break;

            case 'ol_price':
                $price = get_post_meta($post_id, self::META_PREFIX . 'price', true);
                echo $price > 0 ? '$' . number_format($price, 2) : '<span style="color: #059669;">Free</span>';
                break;

            case 'ol_synced':
                $last_synced = get_post_meta($post_id, self::META_PREFIX . 'last_synced', true);
                echo $last_synced ? esc_html($last_synced) : 'Never';
                break;
        }
    }

    /**
     * Torna colunas ordenáveis
     */
    public static function sortable_columns($columns) {
        $columns['ol_visible'] = 'ol_visible';
        $columns['ol_featured'] = 'ol_featured';
        $columns['ol_price'] = 'ol_price';
        return $columns;
    }

    /**
     * Busca cursos visíveis, ordenados por destaque e ordem
     *
     * @param int $limit Número máximo de cursos (-1 para todos)
     * @param bool $featured_only Se deve retornar apenas destaques
     * @return array
     */
    public static function get_visible_courses($limit = -1, $featured_only = false) {
        $meta_query = array(
            'relation' => 'AND',
            array(
                'relation' => 'OR',
                array(
                    'key' => self::META_PREFIX . 'is_visible',
                    'value' => 'true',
                    'compare' => '=',
                ),
                array(
                    'key' => self::META_PREFIX . 'is_visible',
                    'value' => '1',
                    'compare' => '=',
                ),
            ),
        );

        if ($featured_only) {
            $meta_query[] = array(
                'relation' => 'OR',
                array(
                    'key' => self::META_PREFIX . 'is_featured',
                    'value' => 'true',
                    'compare' => '=',
                ),
                array(
                    'key' => self::META_PREFIX . 'is_featured',
                    'value' => '1',
                    'compare' => '=',
                ),
            );
        }

        $args = array(
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'meta_query' => $meta_query,
            'meta_key' => self::META_PREFIX . 'display_order',
            'orderby' => array(
                'meta_value_num' => 'ASC',
                'title' => 'ASC',
            ),
        );

        $query = new \WP_Query($args);

        $courses = array();
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();

                $is_featured = get_post_meta($post_id, self::META_PREFIX . 'is_featured', true);

                $courses[] = array(
                    'id' => $post_id,
                    'course_id' => get_post_meta($post_id, self::META_PREFIX . 'course_id', true),
                    'title' => get_the_title(),
                    'description' => get_the_content(),
                    'image_url' => get_post_meta($post_id, self::META_PREFIX . 'image_url', true),
                    'course_url' => get_post_meta($post_id, self::META_PREFIX . 'course_url', true),
                    'price' => floatval(get_post_meta($post_id, self::META_PREFIX . 'price', true)),
                    'self_paced' => self::is_truthy(get_post_meta($post_id, self::META_PREFIX . 'self_paced', true)),
                    'is_featured' => self::is_truthy($is_featured),
                    'display_order' => intval(get_post_meta($post_id, self::META_PREFIX . 'display_order', true)),
                );
            }
            wp_reset_postdata();
        }

        return $courses;
    }

    /**
     * Verifica se valor é "truthy" (compatível com JetEngine switcher)
     */
    private static function is_truthy($value) {
        return $value === 'true' || $value === '1' || $value === true || $value === 1;
    }

    /**
     * Busca cursos para o Dashboard (4 cursos, destaques primeiro)
     *
     * @return array
     */
    public static function get_dashboard_courses() {
        // Primeiro, busca destaques
        $featured = self::get_visible_courses(4, true);

        // Se tiver menos de 4 destaques, completa com outros
        if (count($featured) < 4) {
            $remaining = 4 - count($featured);
            $featured_ids = array_column($featured, 'id');

            $meta_query = array(
                'relation' => 'AND',
                array(
                    'relation' => 'OR',
                    array(
                        'key' => self::META_PREFIX . 'is_visible',
                        'value' => 'true',
                        'compare' => '=',
                    ),
                    array(
                        'key' => self::META_PREFIX . 'is_visible',
                        'value' => '1',
                        'compare' => '=',
                    ),
                ),
            );

            $args = array(
                'post_type' => self::POST_TYPE,
                'post_status' => 'publish',
                'posts_per_page' => $remaining,
                'post__not_in' => $featured_ids,
                'meta_query' => $meta_query,
                'meta_key' => self::META_PREFIX . 'display_order',
                'orderby' => array(
                    'meta_value_num' => 'ASC',
                    'title' => 'ASC',
                ),
            );

            $query = new \WP_Query($args);

            if ($query->have_posts()) {
                while ($query->have_posts()) {
                    $query->the_post();
                    $post_id = get_the_ID();

                    $featured[] = array(
                        'id' => $post_id,
                        'course_id' => get_post_meta($post_id, self::META_PREFIX . 'course_id', true),
                        'title' => get_the_title(),
                        'description' => get_the_content(),
                        'image_url' => get_post_meta($post_id, self::META_PREFIX . 'image_url', true),
                        'course_url' => get_post_meta($post_id, self::META_PREFIX . 'course_url', true),
                        'price' => floatval(get_post_meta($post_id, self::META_PREFIX . 'price', true)),
                        'self_paced' => self::is_truthy(get_post_meta($post_id, self::META_PREFIX . 'self_paced', true)),
                        'is_featured' => false,
                        'display_order' => intval(get_post_meta($post_id, self::META_PREFIX . 'display_order', true)),
                    );
                }
                wp_reset_postdata();
            }
        }

        return array_slice($featured, 0, 4);
    }

    /**
     * Conta cursos visíveis
     *
     * @return int
     */
    public static function count_visible_courses() {
        $meta_query = array(
            'relation' => 'OR',
            array(
                'key' => self::META_PREFIX . 'is_visible',
                'value' => 'true',
                'compare' => '=',
            ),
            array(
                'key' => self::META_PREFIX . 'is_visible',
                'value' => '1',
                'compare' => '=',
            ),
        );

        $args = array(
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => $meta_query,
        );

        $query = new \WP_Query($args);

        return $query->found_posts;
    }

    /**
     * Busca curso por OpenLearning ID
     *
     * @param string $course_id
     * @return int|null Post ID ou null
     */
    public static function get_by_course_id($course_id) {
        $args = array(
            'post_type' => self::POST_TYPE,
            'post_status' => 'any',
            'posts_per_page' => 1,
            'meta_query' => array(
                array(
                    'key' => self::META_PREFIX . 'course_id',
                    'value' => $course_id,
                    'compare' => '=',
                ),
            ),
            'fields' => 'ids',
        );

        $query = new \WP_Query($args);

        if ($query->have_posts()) {
            return $query->posts[0];
        }

        return null;
    }
}
