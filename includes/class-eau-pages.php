<?php
namespace EauSystem;

/**
 * Gerencia a criação automática de páginas do Eau System
 *
 * Cria automaticamente todas as páginas necessárias na ativação do plugin,
 * eliminando a necessidade de criação manual.
 *
 * @since 1.57.0
 */
class Eau_Pages {

    /**
     * Option name para armazenar IDs das páginas criadas
     */
    const OPTION_PAGE_IDS = 'eau_system_page_ids';

    /**
     * Option name para armazenar versão da estrutura de páginas
     */
    const OPTION_PAGES_VERSION = 'eau_system_pages_version';

    /**
     * Versão atual da estrutura de páginas
     * Incrementar quando adicionar novas páginas
     * v1.0.1 - Removed manage-categories (integrated into Settings)
     * v1.0.2 - Added update-email page for Email Migration (v1.62.0)
     * v1.0.3 - Added checkout page for Payment Gateway (v1.70.0)
     */
    const PAGES_VERSION = '1.0.3';

    /**
     * Definição de todas as páginas do sistema
     *
     * @return array
     */
    public static function get_pages_definition() {
        return array(
            // ===================================
            // PÁGINAS DE NÍVEL SUPERIOR (6)
            // ===================================
            'dashboard' => array(
                'title'     => 'Dashboard',
                'slug'      => 'dashboard',
                'shortcode' => '[eau_admin_dashboard]',
                'parent'    => null,
                'order'     => 1,
            ),
            'profile' => array(
                'title'     => 'Profile',
                'slug'      => 'profile',
                'shortcode' => '[eau_my_profile]',
                'parent'    => null,
                'order'     => 2,
            ),
            'register' => array(
                'title'     => 'Register',
                'slug'      => 'register',
                'shortcode' => '[eau_public_registration]',
                'parent'    => null,
                'order'     => 3,
            ),
            'membership-selection' => array(
                'title'     => 'Membership Selection',
                'slug'      => 'membership-selection',
                'shortcode' => '[eau_membership_selection]',
                'parent'    => null,
                'order'     => 4,
            ),
            'events' => array(
                'title'     => 'Events',
                'slug'      => 'events',
                'shortcode' => '[eau_events_archive]',
                'parent'    => null,
                'order'     => 5,
            ),
            'roadmap' => array(
                'title'     => 'Roadmap',
                'slug'      => 'roadmap',
                'shortcode' => '[eau_system_presentation]',
                'parent'    => null,
                'order'     => 6,
            ),
            'update-email' => array(
                'title'     => 'Update Email',
                'slug'      => 'update-email',
                'shortcode' => '[eau_email_update]',
                'parent'    => null,
                'order'     => 7,
            ),
            'checkout' => array(
                'title'     => 'Checkout',
                'slug'      => 'checkout',
                'shortcode' => '[eau_checkout]',
                'parent'    => null,
                'order'     => 8,
            ),

            // ===================================
            // PÁGINAS FILHAS DE /dashboard/ (14)
            // ===================================
            'manage-members' => array(
                'title'     => 'Manage Members',
                'slug'      => 'manage-members',
                'shortcode' => '[eau_members_management]',
                'parent'    => 'dashboard',
                'order'     => 1,
            ),
            'manage-institutions' => array(
                'title'     => 'Manage Institutions',
                'slug'      => 'manage-institutions',
                'shortcode' => '[eau_institutions_management]',
                'parent'    => 'dashboard',
                'order'     => 2,
            ),
            'manage-activities' => array(
                'title'     => 'Manage Activities',
                'slug'      => 'manage-activities',
                'shortcode' => '[eau_activities_management]',
                'parent'    => 'dashboard',
                'order'     => 3,
            ),
            // manage-categories removed in v1.60.0 - now integrated into Settings page
            'my-cpds' => array(
                'title'     => 'My CPDs',
                'slug'      => 'my-cpds',
                'shortcode' => '[eau_my_cpds]',
                'parent'    => 'dashboard',
                'order'     => 5,
            ),
            'my-payments' => array(
                'title'     => 'My Payments',
                'slug'      => 'my-payments',
                'shortcode' => '[eau_my_payments]',
                'parent'    => 'dashboard',
                'order'     => 6,
            ),
            'my-institution' => array(
                'title'     => 'My Institution',
                'slug'      => 'my-institution',
                'shortcode' => '[eau_my_institution]',
                'parent'    => 'dashboard',
                'order'     => 7,
            ),
            'courses' => array(
                'title'     => 'Courses',
                'slug'      => 'courses',
                'shortcode' => '[eau_openlearning_courses]',
                'parent'    => 'dashboard',
                'order'     => 8,
            ),
            'settings' => array(
                'title'     => 'Settings',
                'slug'      => 'settings',
                'shortcode' => '[eau_settings]',
                'parent'    => 'dashboard',
                'order'     => 9,
            ),
            'merge-members' => array(
                'title'     => 'Merge Members',
                'slug'      => 'merge-members',
                'shortcode' => '[eau_duplicate_manager]',
                'parent'    => 'dashboard',
                'order'     => 10,
            ),
            'open-learning-management' => array(
                'title'     => 'Open Learning Management',
                'slug'      => 'open-learning-management',
                'shortcode' => '[eau_openlearning_management]',
                'parent'    => 'dashboard',
                'order'     => 11,
            ),
            'payments' => array(
                'title'     => 'Payments',
                'slug'      => 'payments',
                'shortcode' => '[eau_payments_management]',
                'parent'    => 'dashboard',
                'order'     => 12,
            ),
            'events-management' => array(
                'title'     => 'Events Management',
                'slug'      => 'events',
                'shortcode' => '[eau_events_management]',
                'parent'    => 'dashboard',
                'order'     => 13,
            ),
            'membership-applications' => array(
                'title'     => 'Membership Applications',
                'slug'      => 'membership-applications',
                'shortcode' => '[eau_membership_applications]',
                'parent'    => 'dashboard',
                'order'     => 14,
            ),
        );
    }

    /**
     * Cria todas as páginas do sistema
     *
     * @return array Array com resultados da criação
     */
    public static function create_pages() {
        $pages = self::get_pages_definition();
        $page_ids = get_option(self::OPTION_PAGE_IDS, array());
        $results = array(
            'created'  => array(),
            'existing' => array(),
            'errors'   => array(),
        );

        // Primeiro, criar páginas de nível superior
        foreach ($pages as $key => $page) {
            if ($page['parent'] === null) {
                $result = self::create_single_page($key, $page, $page_ids);
                $results = self::merge_results($results, $result);
                if (isset($result['created'][$key])) {
                    $page_ids[$key] = $result['created'][$key];
                } elseif (isset($result['existing'][$key])) {
                    $page_ids[$key] = $result['existing'][$key];
                }
            }
        }

        // Depois, criar páginas filhas
        foreach ($pages as $key => $page) {
            if ($page['parent'] !== null) {
                $result = self::create_single_page($key, $page, $page_ids);
                $results = self::merge_results($results, $result);
                if (isset($result['created'][$key])) {
                    $page_ids[$key] = $result['created'][$key];
                } elseif (isset($result['existing'][$key])) {
                    $page_ids[$key] = $result['existing'][$key];
                }
            }
        }

        // Salvar IDs das páginas
        update_option(self::OPTION_PAGE_IDS, $page_ids);
        update_option(self::OPTION_PAGES_VERSION, self::PAGES_VERSION);

        // Log results
        if (!empty($results['created'])) {
            error_log('Eau System: Created ' . count($results['created']) . ' pages: ' . implode(', ', array_keys($results['created'])));
        }
        if (!empty($results['errors'])) {
            error_log('Eau System: Errors creating pages: ' . print_r($results['errors'], true));
        }

        return $results;
    }

    /**
     * Cria uma única página
     *
     * @param string $key      Chave da página
     * @param array  $page     Configuração da página
     * @param array  $page_ids Array de IDs já criados
     * @return array
     */
    private static function create_single_page($key, $page, $page_ids) {
        $results = array(
            'created'  => array(),
            'existing' => array(),
            'errors'   => array(),
        );

        // Determinar parent ID
        $parent_id = 0;
        if ($page['parent'] !== null && isset($page_ids[$page['parent']])) {
            $parent_id = $page_ids[$page['parent']];
        }

        // Verificar se já existe pelo ID salvo
        if (isset($page_ids[$key])) {
            $existing_page = get_post($page_ids[$key]);
            if ($existing_page && $existing_page->post_status !== 'trash') {
                $results['existing'][$key] = $existing_page->ID;
                return $results;
            }
        }

        // Verificar se existe pelo slug
        $existing = self::get_page_by_slug($page['slug'], $parent_id);
        if ($existing) {
            $results['existing'][$key] = $existing->ID;
            return $results;
        }

        // Criar a página
        $page_data = array(
            'post_title'   => $page['title'],
            'post_name'    => $page['slug'],
            'post_content' => $page['shortcode'],
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_parent'  => $parent_id,
            'menu_order'   => $page['order'],
            'post_author'  => get_current_user_id() ?: 1,
        );

        $page_id = wp_insert_post($page_data, true);

        if (is_wp_error($page_id)) {
            $results['errors'][$key] = $page_id->get_error_message();
        } else {
            $results['created'][$key] = $page_id;
        }

        return $results;
    }

    /**
     * Busca página pelo slug considerando hierarquia
     *
     * @param string $slug      Slug da página
     * @param int    $parent_id ID do parent (ou 0)
     * @return \WP_Post|null
     */
    private static function get_page_by_slug($slug, $parent_id = 0) {
        $args = array(
            'post_type'      => 'page',
            'name'           => $slug,
            'post_parent'    => $parent_id,
            'post_status'    => array('publish', 'draft', 'private'),
            'posts_per_page' => 1,
        );

        $pages = get_posts($args);

        return !empty($pages) ? $pages[0] : null;
    }

    /**
     * Merge arrays de resultados
     *
     * @param array $results1 Primeiro array
     * @param array $results2 Segundo array
     * @return array
     */
    private static function merge_results($results1, $results2) {
        return array(
            'created'  => array_merge($results1['created'] ?? array(), $results2['created'] ?? array()),
            'existing' => array_merge($results1['existing'] ?? array(), $results2['existing'] ?? array()),
            'errors'   => array_merge($results1['errors'] ?? array(), $results2['errors'] ?? array()),
        );
    }

    /**
     * Callback para o hook 'wp_loaded' - cria páginas faltantes em batch
     * SOMENTE executa se foi agendado via option na ativação do plugin
     * NÃO roda em cada request - apenas quando a option existe
     * Usa batch para evitar timeout em hospedagens lentas
     *
     * @since 1.72.12
     * @since 1.72.16 Mudado de transient para option (mais confiável em hospedagens)
     * @since 1.72.25 Mudado para usar batch em vez de criar todas de uma vez
     */
    public static function maybe_recreate_all_pages() {
        // SOMENTE executa se foi agendado via option
        if (get_option('eau_needs_page_recreation')) {
            error_log('Eau System: Recreation flag found - starting batch page creation...');

            // Remove a flag ANTES de criar (evita loop infinito se der erro)
            delete_option('eau_needs_page_recreation');

            // Usa o método batch que cria 5 páginas por vez
            $result = self::create_missing_pages_batch();

            $created_count = count($result['created']);
            $total_pages = count(self::get_pages_definition());
            $existing_count = count($result['existing']) + $created_count;

            error_log("Eau System: Batch completed. Created: {$created_count}, Total existing: {$existing_count}/{$total_pages}");

            // Se ainda faltam páginas, agenda nova execução
            if ($existing_count < $total_pages) {
                error_log('Eau System: Still missing pages, scheduling another batch...');
                // Agenda para o próximo request
                update_option('eau_needs_page_creation_continue', true, false);
            }

            if (!empty($result['errors'])) {
                error_log('Eau System: Errors: ' . print_r($result['errors'], true));
            }
        }

        // Continua criação de páginas se necessário (batch mode)
        if (get_option('eau_needs_page_creation_continue')) {
            delete_option('eau_needs_page_creation_continue');

            $result = self::create_missing_pages_batch();
            $created_count = count($result['created']);
            $total_pages = count(self::get_pages_definition());
            $existing_count = count($result['existing']) + $created_count;

            error_log("Eau System: Continue batch. Created: {$created_count}, Total existing: {$existing_count}/{$total_pages}");

            // Se ainda faltam páginas, agenda outra execução
            if ($existing_count < $total_pages && $created_count > 0) {
                update_option('eau_needs_page_creation_continue', true, false);
            }
        }
    }

    /**
     * Agenda a recriação de páginas para o próximo request
     * Usado no hook de ativação onde não podemos chamar wp_insert_post diretamente
     *
     * @since 1.72.12
     * @since 1.72.16 Mudado de transient para option (mais confiável)
     */
    public static function schedule_recreate_pages() {
        update_option('eau_needs_page_recreation', true, false); // false = não autoload
        error_log('Eau System: Page recreation scheduled via option');
    }

    /**
     * Retorna os IDs das páginas criadas
     *
     * @return array
     */
    public static function get_page_ids() {
        return get_option(self::OPTION_PAGE_IDS, array());
    }

    /**
     * Retorna o ID de uma página específica
     *
     * @param string $key Chave da página
     * @return int|null
     */
    public static function get_page_id($key) {
        $page_ids = self::get_page_ids();
        return isset($page_ids[$key]) ? $page_ids[$key] : null;
    }

    /**
     * Retorna a URL de uma página específica
     *
     * @param string $key Chave da página
     * @return string|null
     */
    public static function get_page_url($key) {
        $page_id = self::get_page_id($key);
        return $page_id ? get_permalink($page_id) : null;
    }

    /**
     * Verifica status de todas as páginas
     * Verifica tanto pelo ID salvo quanto pelo slug (mais confiável)
     *
     * @return array
     */
    public static function get_pages_status() {
        $pages = self::get_pages_definition();
        $page_ids = self::get_page_ids();
        $status = array();

        // Primeiro, obter IDs das páginas pai pelo slug para usar como referência
        $parent_ids_by_slug = array();
        foreach ($pages as $key => $page) {
            if ($page['parent'] === null) {
                $found_page = get_page_by_path($page['slug']);
                if ($found_page && $found_page->post_status !== 'trash') {
                    $parent_ids_by_slug[$key] = $found_page->ID;
                }
            }
        }

        foreach ($pages as $key => $page) {
            $page_id = isset($page_ids[$key]) ? $page_ids[$key] : null;
            $exists = false;
            $post_status = null;
            $found_by = null;

            // 1. Verificar pelo ID salvo na opção
            if ($page_id) {
                $post = get_post($page_id);
                if ($post && $post->post_status !== 'trash') {
                    $exists = true;
                    $post_status = $post->post_status;
                    $found_by = 'option_id';
                }
            }

            // 2. Se não encontrou pelo ID, tentar pelo slug
            if (!$exists) {
                if ($page['parent'] === null) {
                    // Página pai - buscar diretamente pelo slug
                    $found_page = get_page_by_path($page['slug']);
                    if ($found_page && $found_page->post_status !== 'trash') {
                        $exists = true;
                        $page_id = $found_page->ID;
                        $post_status = $found_page->post_status;
                        $found_by = 'slug';
                    }
                } else {
                    // Página filha - buscar pelo caminho completo
                    $parent_key = $page['parent'];
                    $parent_slug = $pages[$parent_key]['slug'] ?? null;

                    if ($parent_slug) {
                        $full_path = $parent_slug . '/' . $page['slug'];
                        $found_page = get_page_by_path($full_path);
                        if ($found_page && $found_page->post_status !== 'trash') {
                            $exists = true;
                            $page_id = $found_page->ID;
                            $post_status = $found_page->post_status;
                            $found_by = 'slug';
                        }
                    }
                }
            }

            $status[$key] = array(
                'title'       => $page['title'],
                'slug'        => $page['slug'],
                'shortcode'   => $page['shortcode'],
                'parent'      => $page['parent'],
                'page_id'     => $page_id,
                'exists'      => $exists,
                'post_status' => $post_status,
                'found_by'    => $found_by,
                'url'         => $exists ? get_permalink($page_id) : null,
            );
        }

        return $status;
    }

    /**
     * Recria páginas que foram deletadas
     *
     * @return array
     */
    public static function recreate_missing_pages() {
        // Limpa IDs de páginas que não existem mais
        $page_ids = self::get_page_ids();
        foreach ($page_ids as $key => $id) {
            $post = get_post($id);
            if (!$post || $post->post_status === 'trash') {
                unset($page_ids[$key]);
            }
        }
        update_option(self::OPTION_PAGE_IDS, $page_ids);

        // Recria páginas faltantes
        return self::create_pages();
    }

    /**
     * Deleta todas as páginas do sistema e recria
     *
     * Processo simples e direto:
     * 1. Deleta TODAS as páginas pelo SLUG (único identificador confiável)
     * 2. Recria todas as páginas do zero
     * 3. Força flush dos permalinks
     *
     * @since 1.57.4
     * @since 1.72.10 Reescrito - deleta apenas por slug (funciona em qualquer instalação)
     * @return array
     */
    public static function delete_and_recreate_all_pages() {
        $pages = self::get_pages_definition();
        $deleted = 0;

        // Separar páginas pai e filhas
        $parent_pages = array();
        $child_pages = array();

        foreach ($pages as $key => $page) {
            if ($page['parent'] === null) {
                $parent_pages[$key] = $page;
            } else {
                $child_pages[$key] = $page;
            }
        }

        // PASSO 1: Deletar páginas filhas primeiro (por slug)
        foreach ($child_pages as $key => $page) {
            $parent_slug = $parent_pages[$page['parent']]['slug'] ?? null;
            if ($parent_slug) {
                $child_path = $parent_slug . '/' . $page['slug'];
                $child_page = get_page_by_path($child_path);
                if ($child_page) {
                    wp_delete_post($child_page->ID, true);
                    $deleted++;
                }
            }
        }

        // PASSO 2: Deletar páginas de nível superior (por slug)
        foreach ($parent_pages as $key => $page) {
            $parent_page = get_page_by_path($page['slug']);
            if ($parent_page) {
                wp_delete_post($parent_page->ID, true);
                $deleted++;
            }
        }

        // PASSO 3: Limpar options
        delete_option(self::OPTION_PAGE_IDS);
        delete_option(self::OPTION_PAGES_VERSION);

        // Log
        error_log('Eau System: Deleted ' . $deleted . ' pages by slug. Recreating...');

        // PASSO 4: Recriar todas as páginas
        $results = self::create_all_pages_fresh();

        // PASSO 5: Forçar flush dos permalinks
        flush_rewrite_rules(true);

        error_log('Eau System: Pages recreated. Created: ' . count($results['created']) . ', Errors: ' . count($results['errors']));

        return $results;
    }

    /**
     * Força template Elementor Header Footer para manter header/footer mas renderizar shortcode
     *
     * @since 1.72.19
     * @since 1.72.20 Mudou para Elementor Canvas
     * @since 1.72.23 Mudou para Elementor Header Footer (mantém header/footer do site)
     * @param int $page_id ID da página
     */
    private static function force_default_template($page_id) {
        // Usar Elementor Header Footer - mantém header/footer mas renderiza o post_content
        // Isso evita que o Theme Builder "Single Page" template sobrescreva o conteúdo
        // mas mantém a navegação do site
        update_post_meta($page_id, '_wp_page_template', 'elementor_header_footer');

        // Remover TODOS os metadados do Elementor para garantir que não há dados antigos
        // Isso impede que o Elementor tente renderizar dados antigos
        delete_post_meta($page_id, '_elementor_data');
        delete_post_meta($page_id, '_elementor_edit_mode');
        delete_post_meta($page_id, '_elementor_template_type');
        delete_post_meta($page_id, '_elementor_version');
        delete_post_meta($page_id, '_elementor_pro_version');
        delete_post_meta($page_id, '_elementor_css');
        delete_post_meta($page_id, '_elementor_page_settings');
        delete_post_meta($page_id, '_elementor_controls_usage');

        error_log('Eau System: Forced Elementor Header Footer template for page ID: ' . $page_id);
    }

    /**
     * Encontra um autor válido para criar páginas
     *
     * @since 1.72.17
     * @return int ID do autor válido
     */
    private static function get_valid_author_id() {
        // 1. Tentar usuário atual
        $current_user_id = get_current_user_id();
        if ($current_user_id > 0) {
            $user = get_user_by('id', $current_user_id);
            if ($user && $user->has_cap('edit_pages')) {
                error_log('Eau System: Using current user ID: ' . $current_user_id);
                return $current_user_id;
            }
        }

        // 2. Tentar user ID 1 (padrão do WordPress)
        $user = get_user_by('id', 1);
        if ($user && $user->has_cap('edit_pages')) {
            error_log('Eau System: Using default user ID 1');
            return 1;
        }

        // 3. Buscar qualquer administrador
        $admins = get_users(array(
            'role'   => 'administrator',
            'number' => 1,
            'orderby' => 'ID',
            'order'   => 'ASC',
        ));

        if (!empty($admins)) {
            $admin_id = $admins[0]->ID;
            error_log('Eau System: Using first admin ID: ' . $admin_id);
            return $admin_id;
        }

        // 4. Buscar qualquer usuário com cap edit_pages
        $editors = get_users(array(
            'capability' => 'edit_pages',
            'number'     => 1,
            'orderby'    => 'ID',
            'order'      => 'ASC',
        ));

        if (!empty($editors)) {
            $editor_id = $editors[0]->ID;
            error_log('Eau System: Using first editor ID: ' . $editor_id);
            return $editor_id;
        }

        // 5. Fallback: qualquer usuário
        $any_user = get_users(array(
            'number'  => 1,
            'orderby' => 'ID',
            'order'   => 'ASC',
        ));

        if (!empty($any_user)) {
            $any_id = $any_user[0]->ID;
            error_log('Eau System: WARNING - Using any user ID: ' . $any_id);
            return $any_id;
        }

        // 6. Último fallback - ID 1 mesmo que não exista (WordPress vai usar default)
        error_log('Eau System: CRITICAL - No valid user found, using ID 1 as fallback');
        return 1;
    }

    /**
     * Cria todas as páginas do zero (sem verificações)
     *
     * @since 1.72.10
     * @since 1.72.17 Usa get_valid_author_id() para encontrar autor válido
     * @since 1.72.20 Adiciona set_time_limit e cache flush para evitar timeout
     * @return array
     */
    private static function create_all_pages_fresh() {
        // Evitar timeout em hospedagens compartilhadas
        if (function_exists('set_time_limit')) {
            @set_time_limit(300); // 5 minutos
        }

        // Aumentar limite de memória se possível
        if (function_exists('ini_set')) {
            @ini_set('memory_limit', '256M');
        }

        $pages = self::get_pages_definition();
        $page_ids = array();
        $results = array(
            'created' => array(),
            'errors'  => array(),
        );

        // Obter um autor válido UMA VEZ
        $author_id = self::get_valid_author_id();
        error_log('Eau System: create_all_pages_fresh() starting with author ID: ' . $author_id);
        error_log('Eau System: Total pages to create: ' . count($pages));

        // Primeiro: criar páginas de nível superior
        foreach ($pages as $key => $page) {
            if ($page['parent'] === null) {
                $page_data = array(
                    'post_title'   => $page['title'],
                    'post_name'    => $page['slug'],
                    'post_content' => $page['shortcode'],
                    'post_status'  => 'publish',
                    'post_type'    => 'page',
                    'post_parent'  => 0,
                    'menu_order'   => $page['order'],
                    'post_author'  => $author_id,
                );

                error_log('Eau System: Creating parent page: ' . $key . ' (' . $page['slug'] . ')');

                $page_id = wp_insert_post($page_data, true);

                if (is_wp_error($page_id)) {
                    $error_msg = $page_id->get_error_message();
                    error_log('Eau System: ERROR creating ' . $key . ': ' . $error_msg);
                    $results['errors'][$key] = $error_msg;
                } else {
                    // Forçar template padrão e remover Elementor
                    self::force_default_template($page_id);
                    error_log('Eau System: SUCCESS created ' . $key . ' with ID: ' . $page_id);
                    $results['created'][$key] = $page_id;
                    $page_ids[$key] = $page_id;

                    // Liberar memória após cada página
                    wp_cache_flush();
                }
            }
        }

        error_log('Eau System: Parent pages created: ' . count(array_filter($page_ids)));
        error_log('Eau System: Now creating child pages...');

        // Segundo: criar páginas filhas
        $child_count = 0;
        foreach ($pages as $key => $page) {
            if ($page['parent'] !== null) {
                $child_count++;
                $parent_id = $page_ids[$page['parent']] ?? 0;

                if ($parent_id === 0) {
                    error_log('Eau System: WARNING - Parent page ' . $page['parent'] . ' not found for ' . $key);
                }

                $page_data = array(
                    'post_title'   => $page['title'],
                    'post_name'    => $page['slug'],
                    'post_content' => $page['shortcode'],
                    'post_status'  => 'publish',
                    'post_type'    => 'page',
                    'post_parent'  => $parent_id,
                    'menu_order'   => $page['order'],
                    'post_author'  => $author_id,
                );

                error_log('Eau System: Creating child page ' . $child_count . ': ' . $key . ' (' . $page['slug'] . ') under parent ID: ' . $parent_id);

                $page_id = wp_insert_post($page_data, true);

                if (is_wp_error($page_id)) {
                    $error_msg = $page_id->get_error_message();
                    error_log('Eau System: ERROR creating ' . $key . ': ' . $error_msg);
                    $results['errors'][$key] = $error_msg;
                } else {
                    // Forçar template padrão e remover Elementor
                    self::force_default_template($page_id);
                    error_log('Eau System: SUCCESS created ' . $key . ' with ID: ' . $page_id);
                    $results['created'][$key] = $page_id;
                    $page_ids[$key] = $page_id;

                    // Liberar memória após cada página
                    wp_cache_flush();
                }
            }
        }

        // Salvar IDs
        update_option(self::OPTION_PAGE_IDS, $page_ids);
        update_option(self::OPTION_PAGES_VERSION, self::PAGES_VERSION);

        error_log('Eau System: create_all_pages_fresh() completed. Total created: ' . count($results['created']) . ', Total errors: ' . count($results['errors']));

        return $results;
    }

    /**
     * Cria apenas as páginas que estão faltando (em batch para evitar timeout)
     *
     * @since 1.72.21
     * @return array
     */
    public static function create_missing_pages_batch() {
        // Evitar timeout
        if (function_exists('set_time_limit')) {
            @set_time_limit(120);
        }

        $pages = self::get_pages_definition();
        $page_ids = self::get_page_ids();
        $results = array(
            'created' => array(),
            'existing' => array(),
            'errors'  => array(),
        );

        $author_id = self::get_valid_author_id();
        $created_count = 0;
        $max_per_batch = 5; // Criar no máximo 5 páginas por vez para evitar timeout

        error_log('Eau System: create_missing_pages_batch() starting');

        // Primeiro: criar páginas pai faltantes
        foreach ($pages as $key => $page) {
            if ($created_count >= $max_per_batch) {
                error_log('Eau System: Batch limit reached, stopping');
                break;
            }

            if ($page['parent'] === null) {
                // Verificar se já existe
                if (isset($page_ids[$key])) {
                    $existing = get_post($page_ids[$key]);
                    if ($existing && $existing->post_status !== 'trash') {
                        $results['existing'][$key] = $page_ids[$key];
                        continue;
                    }
                }

                // Verificar pelo slug
                $found = get_page_by_path($page['slug']);
                if ($found && $found->post_status !== 'trash') {
                    $page_ids[$key] = $found->ID;
                    $results['existing'][$key] = $found->ID;
                    continue;
                }

                // Criar a página
                error_log('Eau System: Creating missing parent page: ' . $key);

                $page_id = wp_insert_post(array(
                    'post_title'   => $page['title'],
                    'post_name'    => $page['slug'],
                    'post_content' => $page['shortcode'],
                    'post_status'  => 'publish',
                    'post_type'    => 'page',
                    'post_parent'  => 0,
                    'menu_order'   => $page['order'],
                    'post_author'  => $author_id,
                ), true);

                if (is_wp_error($page_id)) {
                    $results['errors'][$key] = $page_id->get_error_message();
                } else {
                    self::force_default_template($page_id);
                    $results['created'][$key] = $page_id;
                    $page_ids[$key] = $page_id;
                    $created_count++;
                    wp_cache_flush();
                }
            }
        }

        // Segundo: criar páginas filhas faltantes
        foreach ($pages as $key => $page) {
            if ($created_count >= $max_per_batch) {
                break;
            }

            if ($page['parent'] !== null) {
                // Verificar se já existe
                if (isset($page_ids[$key])) {
                    $existing = get_post($page_ids[$key]);
                    if ($existing && $existing->post_status !== 'trash') {
                        $results['existing'][$key] = $page_ids[$key];
                        continue;
                    }
                }

                // Verificar pelo slug (caminho completo)
                $parent_slug = $pages[$page['parent']]['slug'] ?? null;
                if ($parent_slug) {
                    $full_path = $parent_slug . '/' . $page['slug'];
                    $found = get_page_by_path($full_path);
                    if ($found && $found->post_status !== 'trash') {
                        $page_ids[$key] = $found->ID;
                        $results['existing'][$key] = $found->ID;
                        continue;
                    }
                }

                // Obter parent ID
                $parent_id = $page_ids[$page['parent']] ?? 0;
                if ($parent_id === 0) {
                    // Tentar encontrar o parent pelo slug
                    $parent_page = get_page_by_path($pages[$page['parent']]['slug']);
                    if ($parent_page) {
                        $parent_id = $parent_page->ID;
                        $page_ids[$page['parent']] = $parent_id;
                    } else {
                        $results['errors'][$key] = 'Parent page not found: ' . $page['parent'];
                        continue;
                    }
                }

                // Criar a página
                error_log('Eau System: Creating missing child page: ' . $key);

                $page_id = wp_insert_post(array(
                    'post_title'   => $page['title'],
                    'post_name'    => $page['slug'],
                    'post_content' => $page['shortcode'],
                    'post_status'  => 'publish',
                    'post_type'    => 'page',
                    'post_parent'  => $parent_id,
                    'menu_order'   => $page['order'],
                    'post_author'  => $author_id,
                ), true);

                if (is_wp_error($page_id)) {
                    $results['errors'][$key] = $page_id->get_error_message();
                } else {
                    self::force_default_template($page_id);
                    $results['created'][$key] = $page_id;
                    $page_ids[$key] = $page_id;
                    $created_count++;
                    wp_cache_flush();
                }
            }
        }

        // Salvar IDs atualizados
        update_option(self::OPTION_PAGE_IDS, $page_ids);
        update_option(self::OPTION_PAGES_VERSION, self::PAGES_VERSION);

        error_log('Eau System: create_missing_pages_batch() completed. Created: ' . count($results['created']));

        return $results;
    }

    /**
     * Sincroniza páginas existentes com a opção
     * Encontra páginas pelo slug e salva seus IDs na opção
     *
     * @since 1.72.18
     * @return array
     */
    public static function sync_existing_pages() {
        $pages = self::get_pages_definition();
        $page_ids = self::get_page_ids();
        $result = array(
            'synced' => 0,
            'already_registered' => 0,
            'not_found' => 0,
        );

        // Primeiro, processar páginas pai
        foreach ($pages as $key => $page) {
            if ($page['parent'] === null) {
                // Verificar se já está registrada e válida
                if (isset($page_ids[$key])) {
                    $existing = get_post($page_ids[$key]);
                    if ($existing && $existing->post_status !== 'trash') {
                        $result['already_registered']++;
                        continue;
                    }
                }

                // Buscar pelo slug
                $found_page = get_page_by_path($page['slug']);
                if ($found_page && $found_page->post_status !== 'trash') {
                    $page_ids[$key] = $found_page->ID;
                    $result['synced']++;
                } else {
                    $result['not_found']++;
                }
            }
        }

        // Depois, processar páginas filhas
        foreach ($pages as $key => $page) {
            if ($page['parent'] !== null) {
                // Verificar se já está registrada e válida
                if (isset($page_ids[$key])) {
                    $existing = get_post($page_ids[$key]);
                    if ($existing && $existing->post_status !== 'trash') {
                        $result['already_registered']++;
                        continue;
                    }
                }

                // Buscar pelo caminho completo
                $parent_key = $page['parent'];
                $parent_slug = $pages[$parent_key]['slug'] ?? null;

                if ($parent_slug) {
                    $full_path = $parent_slug . '/' . $page['slug'];
                    $found_page = get_page_by_path($full_path);
                    if ($found_page && $found_page->post_status !== 'trash') {
                        $page_ids[$key] = $found_page->ID;
                        $result['synced']++;
                    } else {
                        $result['not_found']++;
                    }
                } else {
                    $result['not_found']++;
                }
            }
        }

        // Salvar IDs atualizados
        update_option(self::OPTION_PAGE_IDS, $page_ids);
        update_option(self::OPTION_PAGES_VERSION, self::PAGES_VERSION);

        error_log('Eau System: sync_existing_pages() - Synced: ' . $result['synced'] . ', Already: ' . $result['already_registered'] . ', Not found: ' . $result['not_found']);

        return $result;
    }

    /**
     * Retorna contagem de páginas por status
     *
     * @return array
     */
    public static function get_pages_summary() {
        $status = self::get_pages_status();
        $summary = array(
            'total'    => count($status),
            'existing' => 0,
            'missing'  => 0,
        );

        foreach ($status as $page) {
            if ($page['exists']) {
                $summary['existing']++;
            } else {
                $summary['missing']++;
            }
        }

        return $summary;
    }
}
