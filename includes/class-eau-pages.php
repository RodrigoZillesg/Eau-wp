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
     */
    const PAGES_VERSION = '1.0.2';

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

        // Verificar se já existe pelo ID salvo
        if (isset($page_ids[$key])) {
            $existing_page = get_post($page_ids[$key]);
            if ($existing_page && $existing_page->post_status !== 'trash') {
                $results['existing'][$key] = $page_ids[$key];
                return $results;
            }
        }

        // Determinar parent ID
        $parent_id = 0;
        if ($page['parent'] !== null && isset($page_ids[$page['parent']])) {
            $parent_id = $page_ids[$page['parent']];
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
            'created'  => array_merge($results1['created'], $results2['created']),
            'existing' => array_merge($results1['existing'], $results2['existing']),
            'errors'   => array_merge($results1['errors'], $results2['errors']),
        );
    }

    /**
     * Verifica se as páginas precisam ser criadas/atualizadas
     *
     * @return bool
     */
    public static function needs_page_creation() {
        $current_version = get_option(self::OPTION_PAGES_VERSION, '0.0.0');
        return version_compare($current_version, self::PAGES_VERSION, '<');
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
     *
     * @return array
     */
    public static function get_pages_status() {
        $pages = self::get_pages_definition();
        $page_ids = self::get_page_ids();
        $status = array();

        foreach ($pages as $key => $page) {
            $page_id = isset($page_ids[$key]) ? $page_ids[$key] : null;
            $exists = false;
            $post_status = null;

            if ($page_id) {
                $post = get_post($page_id);
                if ($post && $post->post_status !== 'trash') {
                    $exists = true;
                    $post_status = $post->post_status;
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
     * @since 1.57.4
     * @return array
     */
    public static function delete_and_recreate_all_pages() {
        $page_ids = self::get_page_ids();
        $deleted = 0;

        // Deleta todas as páginas existentes (permanentemente)
        foreach ($page_ids as $key => $id) {
            $post = get_post($id);
            if ($post && $post->post_status !== 'trash') {
                // Force delete (bypass trash)
                wp_delete_post($id, true);
                $deleted++;
            }
        }

        // Limpa todos os IDs salvos
        update_option(self::OPTION_PAGE_IDS, array());

        // Reseta versão para forçar recriação
        update_option(self::OPTION_PAGES_VERSION, '0.0.0');

        // Log
        if ($deleted > 0) {
            error_log('Eau System: Deleted ' . $deleted . ' pages before recreation.');
        }

        // Recria todas as páginas
        return self::create_pages();
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
