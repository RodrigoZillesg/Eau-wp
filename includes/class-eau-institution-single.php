<?php
namespace EauSystem;

use EauSystem\Components\Eau_Access_Denied;

/**
 * Institution Single Page
 *
 * Página dedicada para visualização de uma instituição
 * URL: /institution/{id}/
 *
 * @since 1.63.0
 */
class Eau_Institution_Single {

    /**
     * Registra hooks
     */
    public static function init() {
        // Rewrite rules
        add_action('init', array(__CLASS__, 'register_rewrite_rules'));
        add_filter('query_vars', array(__CLASS__, 'register_query_vars'));
        add_action('template_redirect', array(__CLASS__, 'handle_template_redirect'));

        // Shortcode
        add_shortcode('eau_institution_single', array(__CLASS__, 'render_institution_single'));

        // AJAX handlers
        add_action('wp_ajax_eau_get_institution_members_paginated', array(__CLASS__, 'ajax_get_institution_members'));
        add_action('wp_ajax_eau_save_institution', array(__CLASS__, 'ajax_save_institution'));
        add_action('wp_ajax_eau_save_institution_field', array(__CLASS__, 'ajax_save_institution_field'));
        add_action('wp_ajax_eau_upload_institution_logo', array(__CLASS__, 'ajax_upload_institution_logo'));
        add_action('wp_ajax_eau_save_member_field', array(__CLASS__, 'ajax_save_member_field'));
        add_action('wp_ajax_eau_remove_member_from_institution', array(__CLASS__, 'ajax_remove_member_from_institution'));
        add_action('wp_ajax_eau_set_institution_admin', array(__CLASS__, 'ajax_set_institution_admin'));
        add_action('wp_ajax_eau_set_primary_contact', array(__CLASS__, 'ajax_set_primary_contact'));
        add_action('wp_ajax_eau_get_institution_members_for_select', array(__CLASS__, 'ajax_get_members_for_select'));
        add_action('wp_ajax_eau_get_institution_admins', array(__CLASS__, 'ajax_get_institution_admins'));
    }

    /**
     * Registra rewrite rules
     */
    public static function register_rewrite_rules() {
        add_rewrite_rule(
            '^institution/([0-9]+)/?$',
            'index.php?eau_institution_id=$matches[1]',
            'top'
        );
    }

    /**
     * Registra query vars
     */
    public static function register_query_vars($vars) {
        $vars[] = 'eau_institution_id';
        return $vars;
    }

    /**
     * Handle template redirect para a single page
     */
    public static function handle_template_redirect() {
        $institution_id = get_query_var('eau_institution_id');

        if (empty($institution_id)) {
            return;
        }

        // Verifica se a instituição existe
        $institution = get_post($institution_id);
        if (!$institution || $institution->post_type !== 'institutions') {
            wp_redirect(home_url('/404'));
            exit;
        }

        // Renderiza a página
        self::enqueue_assets();

        // Define o título da página
        add_filter('pre_get_document_title', function() use ($institution) {
            $company_name = get_post_meta($institution->ID, 'ins_company_name', true) ?: $institution->post_title;
            return $company_name . ' - Institution Details';
        });

        // Renderiza template
        include EAU_SYSTEM_PLUGIN_DIR . 'templates/single-institution.php';
        exit;
    }

    /**
     * Renderiza a single page (shortcode alternativo)
     */
    public static function render_institution_single($atts) {
        $atts = shortcode_atts(array(
            'id' => 0,
        ), $atts);

        $institution_id = absint($atts['id']);

        if (!$institution_id) {
            $institution_id = get_query_var('eau_institution_id');
        }

        if (!$institution_id) {
            return '<p>Institution not found.</p>';
        }

        // Verifica se usuário está logado
        if (!is_user_logged_in()) {
            return Eau_Access_Denied::not_logged_in();
        }

        // Verifica permissões
        if (!self::user_can_view_institution($institution_id)) {
            return Eau_Access_Denied::no_permission();
        }

        // Pega dados da instituição
        $institution = get_post($institution_id);
        if (!$institution || $institution->post_type !== 'institutions') {
            return '<p>Institution not found.</p>';
        }

        self::enqueue_assets();

        return self::render_institution_page($institution);
    }

    /**
     * Renderiza a página da instituição para embed em outras páginas (ex: My Institution)
     *
     * @param int $institution_id ID da instituição
     * @param array $options Opções de renderização
     * @return string HTML renderizado
     * @since 1.65.0
     */
    public static function render_embedded($institution_id, $options = array()) {
        $defaults = array(
            'show_back_link' => false,
            'show_members_section' => true,
            'show_primary_contact' => true,
            'show_admins_section' => true,
            'container_class' => 'eau-institution-embedded',
        );
        $options = wp_parse_args($options, $defaults);

        // Verifica se a instituição existe
        $institution = get_post($institution_id);
        if (!$institution || $institution->post_type !== 'institutions') {
            return '<div class="eau-empty-state"><i data-lucide="building-2"></i><p>Institution not found.</p></div>';
        }

        // Verifica permissões
        if (!self::user_can_view_institution($institution_id)) {
            return '<div class="eau-empty-state"><i data-lucide="lock"></i><p>You do not have permission to view this institution.</p></div>';
        }

        // Enqueue assets (CSS/JS específicos da single page)
        self::enqueue_assets_for_embedded($institution_id);

        // Renderiza a página com opções
        return self::render_institution_page_internal($institution, $options);
    }

    /**
     * Enqueue assets para versão embedded (chamado via AJAX também)
     *
     * @param int $institution_id ID da instituição
     * @since 1.65.0
     */
    public static function enqueue_assets_for_embedded($institution_id) {
        // Se assets já foram enfileirados, retorna
        if (wp_script_is('eau-institution-single', 'enqueued')) {
            return;
        }

        // Enfileira CSS
        wp_enqueue_style(
            'eau-institution-single',
            EAU_SYSTEM_PLUGIN_URL . 'assets/css/eau-institution-single.css',
            array('eau-components'),
            EAU_SYSTEM_VERSION
        );

        // Enfileira JS
        wp_enqueue_script(
            'eau-institution-single',
            EAU_SYSTEM_PLUGIN_URL . 'assets/js/eau-institution-single.js',
            array('jquery', 'lucide-icons', 'eau-notifications'),
            EAU_SYSTEM_VERSION,
            true
        );

        // Permissões do usuário
        $permissions = self::get_user_permissions($institution_id);
        $can_edit_members = self::user_can_edit_members($institution_id);

        // Localiza script
        wp_localize_script('eau-institution-single', 'eauInstitutionSingleData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('eau_institution_single_nonce'),
            'canEdit' => $permissions['can_edit'],
            'canEditRestricted' => $permissions['can_edit_restricted'],
            'canEditMembers' => $can_edit_members,
            'canManageAdmins' => $permissions['can_manage_admins'],
        ));
    }

    /**
     * Renderiza a página da instituição (versão interna com opções)
     *
     * @param WP_Post $institution Post da instituição
     * @param array $options Opções de renderização
     * @return string HTML
     * @since 1.65.0
     */
    private static function render_institution_page_internal($institution, $options = array()) {
        $defaults = array(
            'show_back_link' => true,
            'show_members_section' => true,
            'show_primary_contact' => true,
            'show_admins_section' => true,
            'container_class' => '',
        );
        $options = wp_parse_args($options, $defaults);

        // Pega meta fields
        $company_name = get_post_meta($institution->ID, 'ins_company_name', true) ?: $institution->post_title;
        $company_id = get_post_meta($institution->ID, 'ins_company_id', true);
        $institution_type = get_post_meta($institution->ID, 'ins_type', true);
        $status = get_post_meta($institution->ID, 'ins_status', true) ?: 'active';
        $email = get_post_meta($institution->ID, 'ins_company_email', true);
        $phone = get_post_meta($institution->ID, 'ins_company_company_phone', true);
        $address = get_post_meta($institution->ID, 'ins_company_company_address_line_1', true);
        $suburb = get_post_meta($institution->ID, 'ins_company_company_suburb', true);
        $state = get_post_meta($institution->ID, 'ins_company_company_state', true);
        $postcode = get_post_meta($institution->ID, 'ins_company_company_postcode', true);
        $country = get_post_meta($institution->ID, 'ins_company_company_country', true);

        // Logo
        $logo_id = get_post_meta($institution->ID, 'ins_company_logo', true);
        $logo_url = '';
        if (!empty($logo_id) && is_numeric($logo_id)) {
            $logo_url = wp_get_attachment_url($logo_id);
        } elseif (!empty($logo_id) && filter_var($logo_id, FILTER_VALIDATE_URL)) {
            $logo_url = $logo_id;
        }

        // Conta membros
        $members_count = self::count_institution_members($company_id);

        // Conta admins da instituição
        $admins_count = self::count_institution_admins($institution->ID);

        // Primary contact
        $primary_contact_id = get_post_meta($institution->ID, 'ins_company_primary_contact', true);
        $primary_contact = null;
        if (!empty($primary_contact_id)) {
            $primary_contact = self::get_primary_contact_data($primary_contact_id);
        }

        // Institution admins
        $institution_admins = self::get_institution_admins($company_id);

        // Novos campos de membership
        $campus = get_post_meta($institution->ID, 'ins_member_company_campus', true);
        $start_date = get_post_meta($institution->ID, 'ins_member_start_date', true);
        $expire_date = get_post_meta($institution->ID, 'ins_member_expire_date', true);

        // Status de expiração com cores
        $expiration_status = Eau_Institutions_Meta_Sync::get_expiration_status($institution->ID);

        // Permissões de edição
        $can_edit = self::user_can_edit_institution($institution->ID);
        $can_edit_restricted = self::user_can_edit_restricted_fields();

        // Opções para selects
        $type_options = array(
            '' => 'Select Type',
            'College' => 'College',
            'Corporate affiliate' => 'Corporate Affiliate',
        );
        $status_options = array(
            'active' => 'Active',
            'pending' => 'Pending',
            'inactive' => 'Inactive',
        );

        // Classes do container
        $container_classes = array('eau-institution-single-container');
        if ($can_edit) $container_classes[] = 'eau-can-edit';
        if ($can_edit_restricted) $container_classes[] = 'eau-can-edit-restricted';
        if (!empty($options['container_class'])) $container_classes[] = $options['container_class'];

        ob_start();
        ?>
        <div class="<?php echo esc_attr(implode(' ', $container_classes)); ?>" data-institution-id="<?php echo esc_attr($institution->ID); ?>">

            <?php if ($options['show_back_link']): ?>
            <!-- Back Link -->
            <div class="eau-back-link">
                <a href="<?php echo esc_url(home_url('/dashboard/manage-institutions/')); ?>">
                    <i data-lucide="arrow-left"></i>
                    Back to Institutions
                </a>
            </div>
            <?php endif; ?>

            <!-- Hero Section -->
            <div class="eau-institution-hero">
                <div class="eau-hero-content">
                    <div class="eau-hero-logo<?php echo $can_edit ? ' eau-logo-editable' : ''; ?>">
                        <?php if (!empty($logo_url)): ?>
                            <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($company_name); ?> Logo" id="eau-institution-logo-img">
                        <?php else: ?>
                            <div class="eau-logo-placeholder" id="eau-institution-logo-placeholder">
                                <i data-lucide="building-2"></i>
                            </div>
                        <?php endif; ?>
                        <?php if ($can_edit): ?>
                        <div class="eau-logo-edit-overlay" id="eau-logo-edit-trigger">
                            <i data-lucide="camera"></i>
                            <span>Change</span>
                        </div>
                        <input type="file" id="eau-logo-file-input" accept="image/*" style="display: none;">
                        <?php endif; ?>
                    </div>
                    <div class="eau-hero-info">
                        <h1 class="eau-institution-name">
                            <?php if ($can_edit): ?>
                                <?php echo self::editable_field('ins_company_name', $company_name, array('placeholder' => 'Institution Name')); ?>
                            <?php else: ?>
                                <?php echo esc_html($company_name); ?>
                            <?php endif; ?>
                        </h1>
                        <div class="eau-institution-meta">
                            <span class="eau-meta-item">
                                <i data-lucide="hash"></i>
                                Code: <?php if ($can_edit): ?>
                                    <?php echo self::editable_field('ins_company_id', $company_id, array('placeholder' => 'Add code')); ?>
                                <?php else: ?>
                                    <?php echo !empty($company_id) ? esc_html($company_id) : '<span class="eau-empty-value">Not set</span>'; ?>
                                <?php endif; ?>
                            </span>
                            <span class="eau-meta-item">
                                <i data-lucide="map-pin"></i>
                                Campus: <?php if ($can_edit): ?>
                                    <?php echo self::editable_field('ins_member_company_campus', $campus, array('placeholder' => 'Add campus')); ?>
                                <?php else: ?>
                                    <?php echo !empty($campus) ? esc_html($campus) : '<span class="eau-empty-value">Not set</span>'; ?>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="eau-institution-badges">
                            <?php if ($can_edit): ?>
                                <?php echo self::editable_field('ins_type', $institution_type, array(
                                    'type' => 'select',
                                    'placeholder' => 'Select type',
                                    'display_value' => !empty($institution_type) ? '<span class="eau-badge eau-badge-type ' . ($institution_type === 'College' ? 'eau-badge-college' : 'eau-badge-corporate') . '">' . esc_html($institution_type) . '</span>' : '<span class="eau-badge eau-badge-empty">Add Type</span>',
                                    'select_options' => $type_options,
                                )); ?>
                            <?php elseif (!empty($institution_type)): ?>
                                <span class="eau-badge eau-badge-type <?php echo $institution_type === 'College' ? 'eau-badge-college' : 'eau-badge-corporate'; ?>">
                                    <?php echo esc_html($institution_type); ?>
                                </span>
                            <?php endif; ?>

                            <?php if ($can_edit_restricted): ?>
                                <?php echo self::editable_field('ins_status', $status, array(
                                    'type' => 'select',
                                    'restricted' => true,
                                    'display_value' => '<span class="eau-badge eau-badge-status ' . ($status === 'active' ? 'eau-badge-active' : 'eau-badge-inactive') . '">' . esc_html(ucfirst($status)) . '</span>',
                                    'select_options' => $status_options,
                                )); ?>
                            <?php else: ?>
                                <span class="eau-badge eau-badge-status <?php echo $status === 'active' ? 'eau-badge-active' : 'eau-badge-inactive'; ?>">
                                    <?php echo esc_html(ucfirst($status)); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="eau-hero-right">
                    <div class="eau-hero-stats">
                        <div class="eau-hero-stat">
                            <span class="eau-stat-number"><?php echo esc_html($members_count); ?></span>
                            <span class="eau-stat-label">Members</span>
                        </div>
                        <div class="eau-hero-stat">
                            <span class="eau-stat-number"><?php echo esc_html($admins_count); ?></span>
                            <span class="eau-stat-label">Admins</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Info Cards -->
            <div class="eau-info-cards">
                <!-- Contact Information -->
                <div class="eau-info-card">
                    <div class="eau-card-header">
                        <i data-lucide="mail"></i>
                        <h3>Contact Information</h3>
                    </div>
                    <div class="eau-card-body">
                        <div class="eau-info-item">
                            <i data-lucide="mail"></i>
                            <?php if ($can_edit): ?>
                                <?php echo self::editable_field('ins_company_email', $email, array(
                                    'type' => 'email',
                                    'placeholder' => 'Add email',
                                    'link' => !empty($email),
                                    'link_prefix' => 'mailto:',
                                )); ?>
                            <?php elseif (!empty($email)): ?>
                                <a href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a>
                            <?php else: ?>
                                <span class="eau-empty-value">No email</span>
                            <?php endif; ?>
                        </div>
                        <div class="eau-info-item">
                            <i data-lucide="phone"></i>
                            <?php if ($can_edit): ?>
                                <?php echo self::editable_field('ins_company_company_phone', $phone, array(
                                    'placeholder' => 'Add phone',
                                    'link' => !empty($phone),
                                    'link_prefix' => 'tel:',
                                )); ?>
                            <?php elseif (!empty($phone)): ?>
                                <a href="tel:<?php echo esc_attr($phone); ?>"><?php echo esc_html($phone); ?></a>
                            <?php else: ?>
                                <span class="eau-empty-value">No phone</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Address -->
                <div class="eau-info-card">
                    <div class="eau-card-header">
                        <i data-lucide="map-pin"></i>
                        <h3>Address</h3>
                    </div>
                    <div class="eau-card-body eau-address-fields">
                        <div class="eau-address-field">
                            <label>Street</label>
                            <?php if ($can_edit): ?>
                                <?php echo self::editable_field('ins_company_company_address_line_1', $address, array('placeholder' => 'Add street address')); ?>
                            <?php else: ?>
                                <span><?php echo !empty($address) ? esc_html($address) : '<span class="eau-empty-value">Not set</span>'; ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="eau-address-row">
                            <div class="eau-address-field">
                                <label>Suburb</label>
                                <?php if ($can_edit): ?>
                                    <?php echo self::editable_field('ins_company_company_suburb', $suburb, array('placeholder' => 'Add suburb')); ?>
                                <?php else: ?>
                                    <span><?php echo !empty($suburb) ? esc_html($suburb) : '<span class="eau-empty-value">Not set</span>'; ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="eau-address-field">
                                <label>State</label>
                                <?php if ($can_edit): ?>
                                    <?php
                                    $state_options = self::get_australian_states();
                                    $state_display = isset($state_options[$state]) ? $state_options[$state] : $state;
                                    echo self::editable_field('ins_company_company_state', $state, array(
                                        'type' => 'select',
                                        'placeholder' => 'Select state',
                                        'display_value' => !empty($state) ? esc_html($state_display) : null,
                                        'select_options' => $state_options,
                                    ));
                                    ?>
                                <?php else: ?>
                                    <?php
                                    $state_options = self::get_australian_states();
                                    $state_display = isset($state_options[$state]) ? $state_options[$state] : $state;
                                    ?>
                                    <span><?php echo !empty($state) ? esc_html($state_display) : '<span class="eau-empty-value">Not set</span>'; ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="eau-address-row">
                            <div class="eau-address-field">
                                <label>Postcode</label>
                                <?php if ($can_edit): ?>
                                    <?php echo self::editable_field('ins_company_company_postcode', $postcode, array('placeholder' => 'Add postcode')); ?>
                                <?php else: ?>
                                    <span><?php echo !empty($postcode) ? esc_html($postcode) : '<span class="eau-empty-value">Not set</span>'; ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="eau-address-field">
                                <label>Country</label>
                                <?php if ($can_edit): ?>
                                    <?php
                                    $country_options = self::get_countries();
                                    $country_display = isset($country_options[$country]) ? $country_options[$country] : $country;
                                    echo self::editable_field('ins_company_company_country', $country, array(
                                        'type' => 'select',
                                        'placeholder' => 'Select country',
                                        'display_value' => !empty($country) ? esc_html($country_display) : null,
                                        'select_options' => $country_options,
                                    ));
                                    ?>
                                <?php else: ?>
                                    <?php
                                    $country_options = self::get_countries();
                                    $country_display = isset($country_options[$country]) ? $country_options[$country] : $country;
                                    ?>
                                    <span><?php echo !empty($country) ? esc_html($country_display) : '<span class="eau-empty-value">Not set</span>'; ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Membership Information -->
                <div class="eau-info-card eau-info-card-membership">
                    <div class="eau-card-header">
                        <i data-lucide="id-card"></i>
                        <h3>Membership Information</h3>
                        <?php if ($can_edit_restricted): ?>
                            <span class="eau-admin-badge">Admin Only</span>
                        <?php endif; ?>
                    </div>
                    <div class="eau-card-body">
                        <div class="eau-info-item">
                            <i data-lucide="calendar-check"></i>
                            <div class="eau-info-detail">
                                <span class="eau-info-label">Start Date</span>
                                <?php if ($can_edit_restricted): ?>
                                    <?php echo self::editable_field('ins_member_start_date', $start_date, array(
                                        'type' => 'date',
                                        'restricted' => true,
                                        'placeholder' => 'Set start date',
                                        'display_value' => !empty($start_date) ? Eau_Institutions_Meta_Sync::format_date($start_date) : '<span class="eau-empty-value">Not set</span>',
                                    )); ?>
                                <?php else: ?>
                                    <span class="eau-info-value"><?php echo !empty($start_date) ? esc_html(Eau_Institutions_Meta_Sync::format_date($start_date)) : '<span class="eau-empty-value">Not set</span>'; ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="eau-info-item">
                            <i data-lucide="calendar-x"></i>
                            <div class="eau-info-detail">
                                <span class="eau-info-label">Expiration Date</span>
                                <?php if ($can_edit_restricted): ?>
                                    <?php
                                    $expire_display = !empty($expire_date)
                                        ? '<span class="eau-expire-badge ' . esc_attr($expiration_status['class']) . '">' . esc_html(Eau_Institutions_Meta_Sync::format_date($expire_date)) . ($expiration_status['status'] !== 'ok' && $expiration_status['status'] !== 'not_set' ? ' <span class="eau-expire-label">' . esc_html($expiration_status['label']) . '</span>' : '') . '</span>'
                                        : '<span class="eau-empty-value">Not set</span>';
                                    echo self::editable_field('ins_member_expire_date', $expire_date, array(
                                        'type' => 'date',
                                        'restricted' => true,
                                        'placeholder' => 'Set expire date',
                                        'display_value' => $expire_display,
                                    ));
                                    ?>
                                <?php else: ?>
                                    <?php if (!empty($expire_date)): ?>
                                        <span class="eau-info-value eau-expire-badge <?php echo esc_attr($expiration_status['class']); ?>">
                                            <?php echo esc_html(Eau_Institutions_Meta_Sync::format_date($expire_date)); ?>
                                            <?php if ($expiration_status['status'] !== 'ok' && $expiration_status['status'] !== 'not_set'): ?>
                                                <span class="eau-expire-label"><?php echo esc_html($expiration_status['label']); ?></span>
                                            <?php endif; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="eau-info-value eau-expire-badge eau-expire-not-set">Not Set</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($options['show_members_section']): ?>
            <!-- Members Section -->
            <div class="eau-members-section" id="eau-members-section">
                <div class="eau-section-header">
                    <h2>
                        <i data-lucide="users"></i>
                        Members
                        <span class="eau-members-count-badge"><?php echo esc_html($members_count); ?></span>
                    </h2>
                    <div class="eau-section-actions">
                        <div class="eau-search-wrapper">
                            <i data-lucide="search"></i>
                            <input type="text" id="eau-members-search" placeholder="Search members...">
                        </div>
                        <select id="eau-members-status-filter" class="eau-select-filter">
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="pending">Pending</option>
                        </select>
                    </div>
                </div>
                <div class="eau-members-list" id="eau-members-list">
                    <!-- Carregado via AJAX -->
                    <div class="eau-loading-skeleton">
                        <?php for ($i = 0; $i < 5; $i++): ?>
                            <div class="eau-skeleton-row">
                                <div class="eau-skeleton eau-skeleton-avatar"></div>
                                <div class="eau-skeleton-content">
                                    <div class="eau-skeleton eau-skeleton-text"></div>
                                    <div class="eau-skeleton eau-skeleton-text-sm"></div>
                                </div>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
                <div class="eau-members-pagination" id="eau-members-pagination">
                    <!-- Paginação carregada via AJAX -->
                </div>
            </div>
            <?php endif; ?>

            <?php if ($options['show_primary_contact']): ?>
            <!-- Primary Contact Section -->
            <div class="eau-primary-contact-section">
                <div class="eau-section-header">
                    <h2>
                        <i data-lucide="user-check"></i>
                        Primary Contact
                    </h2>
                    <?php if ($can_edit_restricted): ?>
                    <button type="button" class="eau-btn eau-btn-sm eau-btn-secondary" id="eau-change-primary-contact">
                        <i data-lucide="repeat"></i>
                        Change
                    </button>
                    <?php endif; ?>
                </div>
                <?php if ($primary_contact): ?>
                <div class="eau-primary-contact-card" id="eau-primary-contact-display">
                    <div class="eau-contact-avatar">
                        <?php if (!empty($primary_contact['avatar'])): ?>
                            <img src="<?php echo esc_url($primary_contact['avatar']); ?>" alt="<?php echo esc_attr($primary_contact['name']); ?>">
                        <?php else: ?>
                            <div class="eau-avatar-placeholder">
                                <?php echo esc_html(strtoupper(substr($primary_contact['name'], 0, 1))); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="eau-contact-info">
                        <h4><?php echo esc_html($primary_contact['name']); ?></h4>
                        <?php if (!empty($primary_contact['email'])): ?>
                            <a href="mailto:<?php echo esc_attr($primary_contact['email']); ?>" class="eau-contact-email">
                                <i data-lucide="mail"></i>
                                <?php echo esc_html($primary_contact['email']); ?>
                            </a>
                        <?php endif; ?>
                        <?php if (!empty($primary_contact['phone'])): ?>
                            <a href="tel:<?php echo esc_attr($primary_contact['phone']); ?>" class="eau-contact-phone">
                                <i data-lucide="phone"></i>
                                <?php echo esc_html($primary_contact['phone']); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php else: ?>
                <div class="eau-primary-contact-empty" id="eau-primary-contact-display">
                    <p>No primary contact defined.</p>
                    <?php if ($can_edit_restricted): ?>
                    <p class="eau-empty-hint">Click "Change" to select a primary contact from the members.</p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <?php if ($can_edit_restricted): ?>
                <div class="eau-primary-contact-selector" id="eau-primary-contact-selector" style="display: none;">
                    <div class="eau-selector-header">
                        <h4>Select Primary Contact</h4>
                        <button type="button" class="eau-btn-close" id="eau-cancel-primary-contact">&times;</button>
                    </div>
                    <div class="eau-selector-search">
                        <i data-lucide="search"></i>
                        <input type="text" id="eau-primary-contact-search" placeholder="Search members...">
                    </div>
                    <div class="eau-selector-list" id="eau-primary-contact-list">
                        <!-- Populated via AJAX -->
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if ($options['show_admins_section']): ?>
            <!-- Institution Admins Section -->
            <div class="eau-institution-admins-section">
                <div class="eau-section-header">
                    <h2>
                        <i data-lucide="shield-check"></i>
                        Institution Administrators
                        <?php if (!empty($institution_admins)): ?>
                        <span class="eau-admin-count">(<?php echo count($institution_admins); ?>)</span>
                        <?php endif; ?>
                    </h2>
                </div>
                <?php if (!empty($institution_admins)): ?>
                <div class="eau-admins-grid">
                    <?php foreach ($institution_admins as $admin): ?>
                    <div class="eau-admin-card">
                        <div class="eau-admin-avatar">
                            <?php if (!empty($admin['avatar'])): ?>
                                <img src="<?php echo esc_url($admin['avatar']); ?>" alt="<?php echo esc_attr($admin['name']); ?>">
                            <?php else: ?>
                                <div class="eau-avatar-placeholder">
                                    <?php echo esc_html(strtoupper(substr($admin['name'], 0, 1))); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="eau-admin-info">
                            <h4><?php echo esc_html($admin['name']); ?></h4>
                            <?php if (!empty($admin['email'])): ?>
                                <a href="mailto:<?php echo esc_attr($admin['email']); ?>" class="eau-admin-email">
                                    <i data-lucide="mail"></i>
                                    <?php echo esc_html($admin['email']); ?>
                                </a>
                            <?php endif; ?>
                            <?php if (!empty($admin['phone'])): ?>
                                <a href="tel:<?php echo esc_attr($admin['phone']); ?>" class="eau-admin-phone">
                                    <i data-lucide="phone"></i>
                                    <?php echo esc_html($admin['phone']); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="eau-admins-empty">
                    <p>No institution administrators found.</p>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- v1.67.3 - Modal para remover membro com motivo -->
            <?php if ($can_edit): ?>
            <?php echo self::render_remove_member_modal(); ?>
            <?php endif; ?>

        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Verifica se usuário pode visualizar a instituição
     */
    public static function user_can_view_institution($institution_id) {
        $current_user_id = get_current_user_id();

        // superAdmin e Admin podem ver todas
        if (Eau_User_Institution_Helper::has_admin_access($current_user_id)) {
            return true;
        }

        // institutionAdmin pode ver suas instituições gerenciadas
        if (Eau_User_Institution_Helper::is_institution_admin($current_user_id)) {
            $managed_institutions = Eau_User_Institution_Helper::get_user_managed_institutions($current_user_id);
            foreach ($managed_institutions as $inst) {
                if ($inst->ID == $institution_id) {
                    return true;
                }
            }
            // institutionAdmin também pode ser membro de outra instituição
            // Não retorna false aqui - continua para verificar se é membro
        }

        // Qualquer usuário pode ver sua própria instituição como membro
        $user_institution = Eau_User_Institution_Helper::get_user_institution($current_user_id);
        if ($user_institution && $user_institution->ID == $institution_id) {
            return true;
        }

        return false;
    }

    /**
     * Enfileira assets
     */
    public static function enqueue_assets() {
        // CSS dos componentes
        wp_enqueue_style(
            'eau-components',
            EAU_SYSTEM_PLUGIN_URL . 'assets/css/eau-components.css',
            array(),
            EAU_SYSTEM_VERSION
        );

        // CSS específico
        wp_enqueue_style(
            'eau-institution-single',
            EAU_SYSTEM_PLUGIN_URL . 'assets/css/eau-institution-single.css',
            array('eau-components'),
            EAU_SYSTEM_VERSION
        );

        // Lucide Icons
        wp_enqueue_script(
            'lucide-icons',
            'https://unpkg.com/lucide@latest/dist/umd/lucide.min.js',
            array(),
            null,
            true
        );

        // JS de notificações
        wp_enqueue_script(
            'eau-notifications',
            EAU_SYSTEM_PLUGIN_URL . 'assets/js/eau-notifications.js',
            array('jquery', 'lucide-icons'),
            EAU_SYSTEM_VERSION,
            true
        );

        // JS específico
        wp_enqueue_script(
            'eau-institution-single',
            EAU_SYSTEM_PLUGIN_URL . 'assets/js/eau-institution-single.js',
            array('jquery', 'lucide-icons', 'eau-notifications'),
            EAU_SYSTEM_VERSION,
            true
        );

        // Pega o ID da instituição atual (se disponível)
        $institution_id = get_query_var('eau_institution_id');
        $permissions = array(
            'can_edit' => false,
            'can_edit_restricted' => false,
        );

        if ($institution_id) {
            $permissions = self::get_user_permissions($institution_id);
        }

        // Verifica permissão de editar membros
        $can_edit_members = false;
        if ($institution_id) {
            $can_edit_members = self::user_can_edit_members($institution_id);
        }

        // Localiza script
        wp_localize_script('eau-institution-single', 'eauInstitutionSingleData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('eau_institution_single_nonce'),
            'canEdit' => $permissions['can_edit'],
            'canEditRestricted' => $permissions['can_edit_restricted'],
            'canEditMembers' => $can_edit_members,
            'canManageAdmins' => $permissions['can_manage_admins'],
        ));
    }

    /**
     * Helper para gerar campo editável inline
     */
    private static function editable_field($field_name, $value, $options = array()) {
        $defaults = array(
            'type' => 'text',
            'placeholder' => 'Click to edit',
            'display_value' => $value,
            'restricted' => false,
            'select_options' => array(),
            'link' => false,
            'link_prefix' => '',
        );
        $opts = wp_parse_args($options, $defaults);

        $display = !empty($opts['display_value']) ? $opts['display_value'] : '<span class="eau-empty-value">' . esc_html($opts['placeholder']) . '</span>';

        // Se é link (email ou phone)
        if ($opts['link'] && !empty($value)) {
            $display = '<a href="' . esc_attr($opts['link_prefix'] . $value) . '">' . esc_html($value) . '</a>';
        }

        $restricted_class = $opts['restricted'] ? ' eau-restricted-field' : '';
        $data_options = '';
        if ($opts['type'] === 'select' && !empty($opts['select_options'])) {
            $data_options = " data-options='" . esc_attr(json_encode($opts['select_options'])) . "'";
        }

        return sprintf(
            '<span class="eau-editable%s" data-field="%s" data-type="%s" data-value="%s"%s>
                <span class="eau-editable-display">%s</span>
                <i data-lucide="pencil" class="eau-edit-icon"></i>
            </span>',
            $restricted_class,
            esc_attr($field_name),
            esc_attr($opts['type']),
            esc_attr($value),
            $data_options,
            $display
        );
    }

    /**
     * Renderiza a página da instituição
     */
    public static function render_institution_page($institution) {
        // Pega meta fields
        $company_name = get_post_meta($institution->ID, 'ins_company_name', true) ?: $institution->post_title;
        $company_id = get_post_meta($institution->ID, 'ins_company_id', true);
        $institution_type = get_post_meta($institution->ID, 'ins_type', true);
        $status = get_post_meta($institution->ID, 'ins_status', true) ?: 'active';
        $email = get_post_meta($institution->ID, 'ins_company_email', true);
        $phone = get_post_meta($institution->ID, 'ins_company_company_phone', true);
        $address = get_post_meta($institution->ID, 'ins_company_company_address_line_1', true);
        $suburb = get_post_meta($institution->ID, 'ins_company_company_suburb', true);
        $state = get_post_meta($institution->ID, 'ins_company_company_state', true);
        $postcode = get_post_meta($institution->ID, 'ins_company_company_postcode', true);
        $country = get_post_meta($institution->ID, 'ins_company_company_country', true);

        // Logo
        $logo_id = get_post_meta($institution->ID, 'ins_company_logo', true);
        $logo_url = '';
        if (!empty($logo_id) && is_numeric($logo_id)) {
            $logo_url = wp_get_attachment_url($logo_id);
        } elseif (!empty($logo_id) && filter_var($logo_id, FILTER_VALIDATE_URL)) {
            $logo_url = $logo_id;
        }

        // Conta membros
        $members_count = self::count_institution_members($company_id);

        // Conta admins da instituição
        $admins_count = self::count_institution_admins($institution->ID);

        // Primary contact
        $primary_contact_id = get_post_meta($institution->ID, 'ins_company_primary_contact', true);
        $primary_contact = null;
        if (!empty($primary_contact_id)) {
            $primary_contact = self::get_primary_contact_data($primary_contact_id);
        }

        // Institution admins
        $institution_admins = self::get_institution_admins($company_id);

        // Novos campos de membership
        $campus = get_post_meta($institution->ID, 'ins_member_company_campus', true);
        $start_date = get_post_meta($institution->ID, 'ins_member_start_date', true);
        $expire_date = get_post_meta($institution->ID, 'ins_member_expire_date', true);

        // Status de expiração com cores
        $expiration_status = Eau_Institutions_Meta_Sync::get_expiration_status($institution->ID);

        // Permissões de edição
        $can_edit = self::user_can_edit_institution($institution->ID);
        $can_edit_restricted = self::user_can_edit_restricted_fields();

        // Opções para selects
        $type_options = array(
            '' => 'Select Type',
            'College' => 'College',
            'Corporate affiliate' => 'Corporate Affiliate',
        );
        $status_options = array(
            'active' => 'Active',
            'pending' => 'Pending',
            'inactive' => 'Inactive',
        );

        ob_start();
        ?>
        <div class="eau-institution-single-container<?php echo $can_edit ? ' eau-can-edit' : ''; ?><?php echo $can_edit_restricted ? ' eau-can-edit-restricted' : ''; ?>" data-institution-id="<?php echo esc_attr($institution->ID); ?>">

            <!-- Back Link -->
            <div class="eau-back-link">
                <a href="<?php echo esc_url(home_url('/dashboard/manage-institutions/')); ?>">
                    <i data-lucide="arrow-left"></i>
                    Back to Institutions
                </a>
            </div>

            <!-- Hero Section -->
            <div class="eau-institution-hero">
                <div class="eau-hero-content">
                    <div class="eau-hero-logo<?php echo $can_edit ? ' eau-logo-editable' : ''; ?>">
                        <?php if (!empty($logo_url)): ?>
                            <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($company_name); ?> Logo" id="eau-institution-logo-img">
                        <?php else: ?>
                            <div class="eau-logo-placeholder" id="eau-institution-logo-placeholder">
                                <i data-lucide="building-2"></i>
                            </div>
                        <?php endif; ?>
                        <?php if ($can_edit): ?>
                        <div class="eau-logo-edit-overlay" id="eau-logo-edit-trigger">
                            <i data-lucide="camera"></i>
                            <span>Change</span>
                        </div>
                        <input type="file" id="eau-logo-file-input" accept="image/*" style="display: none;">
                        <?php endif; ?>
                    </div>
                    <div class="eau-hero-info">
                        <h1 class="eau-institution-name">
                            <?php if ($can_edit): ?>
                                <?php echo self::editable_field('ins_company_name', $company_name, array('placeholder' => 'Institution Name')); ?>
                            <?php else: ?>
                                <?php echo esc_html($company_name); ?>
                            <?php endif; ?>
                        </h1>
                        <div class="eau-institution-meta">
                            <span class="eau-meta-item">
                                <i data-lucide="hash"></i>
                                Code: <?php if ($can_edit): ?>
                                    <?php echo self::editable_field('ins_company_id', $company_id, array('placeholder' => 'Add code')); ?>
                                <?php else: ?>
                                    <?php echo !empty($company_id) ? esc_html($company_id) : '<span class="eau-empty-value">Not set</span>'; ?>
                                <?php endif; ?>
                            </span>
                            <span class="eau-meta-item">
                                <i data-lucide="map-pin"></i>
                                Campus: <?php if ($can_edit): ?>
                                    <?php echo self::editable_field('ins_member_company_campus', $campus, array('placeholder' => 'Add campus')); ?>
                                <?php else: ?>
                                    <?php echo !empty($campus) ? esc_html($campus) : '<span class="eau-empty-value">Not set</span>'; ?>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="eau-institution-badges">
                            <?php if ($can_edit): ?>
                                <?php echo self::editable_field('ins_type', $institution_type, array(
                                    'type' => 'select',
                                    'placeholder' => 'Select type',
                                    'display_value' => !empty($institution_type) ? '<span class="eau-badge eau-badge-type ' . ($institution_type === 'College' ? 'eau-badge-college' : 'eau-badge-corporate') . '">' . esc_html($institution_type) . '</span>' : '<span class="eau-badge eau-badge-empty">Add Type</span>',
                                    'select_options' => $type_options,
                                )); ?>
                            <?php elseif (!empty($institution_type)): ?>
                                <span class="eau-badge eau-badge-type <?php echo $institution_type === 'College' ? 'eau-badge-college' : 'eau-badge-corporate'; ?>">
                                    <?php echo esc_html($institution_type); ?>
                                </span>
                            <?php endif; ?>

                            <?php if ($can_edit_restricted): ?>
                                <?php echo self::editable_field('ins_status', $status, array(
                                    'type' => 'select',
                                    'restricted' => true,
                                    'display_value' => '<span class="eau-badge eau-badge-status ' . ($status === 'active' ? 'eau-badge-active' : 'eau-badge-inactive') . '">' . esc_html(ucfirst($status)) . '</span>',
                                    'select_options' => $status_options,
                                )); ?>
                            <?php else: ?>
                                <span class="eau-badge eau-badge-status <?php echo $status === 'active' ? 'eau-badge-active' : 'eau-badge-inactive'; ?>">
                                    <?php echo esc_html(ucfirst($status)); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="eau-hero-right">
                    <div class="eau-hero-stats">
                        <div class="eau-hero-stat">
                            <span class="eau-stat-number"><?php echo esc_html($members_count); ?></span>
                            <span class="eau-stat-label">Members</span>
                        </div>
                        <div class="eau-hero-stat">
                            <span class="eau-stat-number"><?php echo esc_html($admins_count); ?></span>
                            <span class="eau-stat-label">Admins</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Info Cards -->
            <div class="eau-info-cards">
                <!-- Contact Information -->
                <div class="eau-info-card">
                    <div class="eau-card-header">
                        <i data-lucide="mail"></i>
                        <h3>Contact Information</h3>
                    </div>
                    <div class="eau-card-body">
                        <div class="eau-info-item">
                            <i data-lucide="mail"></i>
                            <?php if ($can_edit): ?>
                                <?php echo self::editable_field('ins_company_email', $email, array(
                                    'type' => 'email',
                                    'placeholder' => 'Add email',
                                    'link' => !empty($email),
                                    'link_prefix' => 'mailto:',
                                )); ?>
                            <?php elseif (!empty($email)): ?>
                                <a href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a>
                            <?php else: ?>
                                <span class="eau-empty-value">No email</span>
                            <?php endif; ?>
                        </div>
                        <div class="eau-info-item">
                            <i data-lucide="phone"></i>
                            <?php if ($can_edit): ?>
                                <?php echo self::editable_field('ins_company_company_phone', $phone, array(
                                    'placeholder' => 'Add phone',
                                    'link' => !empty($phone),
                                    'link_prefix' => 'tel:',
                                )); ?>
                            <?php elseif (!empty($phone)): ?>
                                <a href="tel:<?php echo esc_attr($phone); ?>"><?php echo esc_html($phone); ?></a>
                            <?php else: ?>
                                <span class="eau-empty-value">No phone</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Address -->
                <div class="eau-info-card">
                    <div class="eau-card-header">
                        <i data-lucide="map-pin"></i>
                        <h3>Address</h3>
                    </div>
                    <div class="eau-card-body eau-address-fields">
                        <div class="eau-address-field">
                            <label>Street</label>
                            <?php if ($can_edit): ?>
                                <?php echo self::editable_field('ins_company_company_address_line_1', $address, array('placeholder' => 'Add street address')); ?>
                            <?php else: ?>
                                <span><?php echo !empty($address) ? esc_html($address) : '<span class="eau-empty-value">Not set</span>'; ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="eau-address-row">
                            <div class="eau-address-field">
                                <label>Suburb</label>
                                <?php if ($can_edit): ?>
                                    <?php echo self::editable_field('ins_company_company_suburb', $suburb, array('placeholder' => 'Add suburb')); ?>
                                <?php else: ?>
                                    <span><?php echo !empty($suburb) ? esc_html($suburb) : '<span class="eau-empty-value">Not set</span>'; ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="eau-address-field">
                                <label>State</label>
                                <?php if ($can_edit): ?>
                                    <?php
                                    $state_options = self::get_australian_states();
                                    $state_display = isset($state_options[$state]) ? $state_options[$state] : $state;
                                    echo self::editable_field('ins_company_company_state', $state, array(
                                        'type' => 'select',
                                        'placeholder' => 'Select state',
                                        'display_value' => !empty($state) ? esc_html($state_display) : null,
                                        'select_options' => $state_options,
                                    ));
                                    ?>
                                <?php else: ?>
                                    <?php
                                    $state_options = self::get_australian_states();
                                    $state_display = isset($state_options[$state]) ? $state_options[$state] : $state;
                                    ?>
                                    <span><?php echo !empty($state) ? esc_html($state_display) : '<span class="eau-empty-value">Not set</span>'; ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="eau-address-row">
                            <div class="eau-address-field">
                                <label>Postcode</label>
                                <?php if ($can_edit): ?>
                                    <?php echo self::editable_field('ins_company_company_postcode', $postcode, array('placeholder' => 'Add postcode')); ?>
                                <?php else: ?>
                                    <span><?php echo !empty($postcode) ? esc_html($postcode) : '<span class="eau-empty-value">Not set</span>'; ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="eau-address-field">
                                <label>Country</label>
                                <?php if ($can_edit): ?>
                                    <?php
                                    $country_options = self::get_countries();
                                    $country_display = isset($country_options[$country]) ? $country_options[$country] : $country;
                                    echo self::editable_field('ins_company_company_country', $country, array(
                                        'type' => 'select',
                                        'placeholder' => 'Select country',
                                        'display_value' => !empty($country) ? esc_html($country_display) : null,
                                        'select_options' => $country_options,
                                    ));
                                    ?>
                                <?php else: ?>
                                    <?php
                                    $country_options = self::get_countries();
                                    $country_display = isset($country_options[$country]) ? $country_options[$country] : $country;
                                    ?>
                                    <span><?php echo !empty($country) ? esc_html($country_display) : '<span class="eau-empty-value">Not set</span>'; ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Membership Information -->
                <div class="eau-info-card eau-info-card-membership">
                    <div class="eau-card-header">
                        <i data-lucide="id-card"></i>
                        <h3>Membership Information</h3>
                        <?php if ($can_edit_restricted): ?>
                            <span class="eau-admin-badge">Admin Only</span>
                        <?php endif; ?>
                    </div>
                    <div class="eau-card-body">
                        <div class="eau-info-item">
                            <i data-lucide="calendar-check"></i>
                            <div class="eau-info-detail">
                                <span class="eau-info-label">Start Date</span>
                                <?php if ($can_edit_restricted): ?>
                                    <?php echo self::editable_field('ins_member_start_date', $start_date, array(
                                        'type' => 'date',
                                        'restricted' => true,
                                        'placeholder' => 'Set start date',
                                        'display_value' => !empty($start_date) ? Eau_Institutions_Meta_Sync::format_date($start_date) : '<span class="eau-empty-value">Not set</span>',
                                    )); ?>
                                <?php else: ?>
                                    <span class="eau-info-value"><?php echo !empty($start_date) ? esc_html(Eau_Institutions_Meta_Sync::format_date($start_date)) : '<span class="eau-empty-value">Not set</span>'; ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="eau-info-item">
                            <i data-lucide="calendar-x"></i>
                            <div class="eau-info-detail">
                                <span class="eau-info-label">Expiration Date</span>
                                <?php if ($can_edit_restricted): ?>
                                    <?php
                                    $expire_display = !empty($expire_date)
                                        ? '<span class="eau-expire-badge ' . esc_attr($expiration_status['class']) . '">' . esc_html(Eau_Institutions_Meta_Sync::format_date($expire_date)) . ($expiration_status['status'] !== 'ok' && $expiration_status['status'] !== 'not_set' ? ' <span class="eau-expire-label">' . esc_html($expiration_status['label']) . '</span>' : '') . '</span>'
                                        : '<span class="eau-empty-value">Not set</span>';
                                    echo self::editable_field('ins_member_expire_date', $expire_date, array(
                                        'type' => 'date',
                                        'restricted' => true,
                                        'placeholder' => 'Set expire date',
                                        'display_value' => $expire_display,
                                    ));
                                    ?>
                                <?php else: ?>
                                    <?php if (!empty($expire_date)): ?>
                                        <span class="eau-info-value eau-expire-badge <?php echo esc_attr($expiration_status['class']); ?>">
                                            <?php echo esc_html(Eau_Institutions_Meta_Sync::format_date($expire_date)); ?>
                                            <?php if ($expiration_status['status'] !== 'ok' && $expiration_status['status'] !== 'not_set'): ?>
                                                <span class="eau-expire-label"><?php echo esc_html($expiration_status['label']); ?></span>
                                            <?php endif; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="eau-info-value eau-expire-badge eau-expire-not-set">Not Set</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Members Section -->
            <div class="eau-members-section" id="eau-members-section">
                <div class="eau-section-header">
                    <h2>
                        <i data-lucide="users"></i>
                        Members
                        <span class="eau-members-count-badge"><?php echo esc_html($members_count); ?></span>
                    </h2>
                    <div class="eau-section-actions">
                        <div class="eau-search-wrapper">
                            <i data-lucide="search"></i>
                            <input type="text" id="eau-members-search" placeholder="Search members...">
                        </div>
                        <select id="eau-members-status-filter" class="eau-select-filter">
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="pending">Pending</option>
                        </select>
                    </div>
                </div>
                <div class="eau-members-list" id="eau-members-list">
                    <!-- Carregado via AJAX -->
                    <div class="eau-loading-skeleton">
                        <?php for ($i = 0; $i < 5; $i++): ?>
                            <div class="eau-skeleton-row">
                                <div class="eau-skeleton eau-skeleton-avatar"></div>
                                <div class="eau-skeleton-content">
                                    <div class="eau-skeleton eau-skeleton-text"></div>
                                    <div class="eau-skeleton eau-skeleton-text-sm"></div>
                                </div>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
                <div class="eau-members-pagination" id="eau-members-pagination">
                    <!-- Paginação carregada via AJAX -->
                </div>
            </div>

            <!-- Primary Contact Section -->
            <div class="eau-primary-contact-section">
                <div class="eau-section-header">
                    <h2>
                        <i data-lucide="user-check"></i>
                        Primary Contact
                    </h2>
                    <?php if ($can_edit_restricted): ?>
                    <button type="button" class="eau-btn eau-btn-sm eau-btn-secondary" id="eau-change-primary-contact">
                        <i data-lucide="repeat"></i>
                        Change
                    </button>
                    <?php endif; ?>
                </div>
                <?php if ($primary_contact): ?>
                <div class="eau-primary-contact-card" id="eau-primary-contact-display">
                    <div class="eau-contact-avatar">
                        <?php if (!empty($primary_contact['avatar'])): ?>
                            <img src="<?php echo esc_url($primary_contact['avatar']); ?>" alt="<?php echo esc_attr($primary_contact['name']); ?>">
                        <?php else: ?>
                            <div class="eau-avatar-placeholder">
                                <?php echo esc_html(strtoupper(substr($primary_contact['name'], 0, 1))); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="eau-contact-info">
                        <h4><?php echo esc_html($primary_contact['name']); ?></h4>
                        <?php if (!empty($primary_contact['email'])): ?>
                            <a href="mailto:<?php echo esc_attr($primary_contact['email']); ?>" class="eau-contact-email">
                                <i data-lucide="mail"></i>
                                <?php echo esc_html($primary_contact['email']); ?>
                            </a>
                        <?php endif; ?>
                        <?php if (!empty($primary_contact['phone'])): ?>
                            <a href="tel:<?php echo esc_attr($primary_contact['phone']); ?>" class="eau-contact-phone">
                                <i data-lucide="phone"></i>
                                <?php echo esc_html($primary_contact['phone']); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php else: ?>
                <div class="eau-primary-contact-empty" id="eau-primary-contact-display">
                    <p>No primary contact defined.</p>
                    <?php if ($can_edit_restricted): ?>
                    <p class="eau-empty-hint">Click "Change" to select a primary contact from the members.</p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <?php if ($can_edit_restricted): ?>
                <div class="eau-primary-contact-selector" id="eau-primary-contact-selector" style="display: none;">
                    <div class="eau-selector-header">
                        <h4>Select Primary Contact</h4>
                        <button type="button" class="eau-btn-close" id="eau-cancel-primary-contact">&times;</button>
                    </div>
                    <div class="eau-selector-search">
                        <i data-lucide="search"></i>
                        <input type="text" id="eau-primary-contact-search" placeholder="Search members...">
                    </div>
                    <div class="eau-selector-list" id="eau-primary-contact-list">
                        <!-- Populated via AJAX -->
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Institution Admins Section -->
            <div class="eau-institution-admins-section">
                <div class="eau-section-header">
                    <h2>
                        <i data-lucide="shield-check"></i>
                        Institution Administrators
                        <?php if (!empty($institution_admins)): ?>
                        <span class="eau-admin-count">(<?php echo count($institution_admins); ?>)</span>
                        <?php endif; ?>
                    </h2>
                </div>
                <?php if (!empty($institution_admins)): ?>
                <div class="eau-admins-grid">
                    <?php foreach ($institution_admins as $admin): ?>
                    <div class="eau-admin-card">
                        <div class="eau-admin-avatar">
                            <?php if (!empty($admin['avatar'])): ?>
                                <img src="<?php echo esc_url($admin['avatar']); ?>" alt="<?php echo esc_attr($admin['name']); ?>">
                            <?php else: ?>
                                <div class="eau-avatar-placeholder">
                                    <?php echo esc_html(strtoupper(substr($admin['name'], 0, 1))); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="eau-admin-info">
                            <h4><?php echo esc_html($admin['name']); ?></h4>
                            <?php if (!empty($admin['email'])): ?>
                                <a href="mailto:<?php echo esc_attr($admin['email']); ?>" class="eau-admin-email">
                                    <i data-lucide="mail"></i>
                                    <?php echo esc_html($admin['email']); ?>
                                </a>
                            <?php endif; ?>
                            <?php if (!empty($admin['phone'])): ?>
                                <a href="tel:<?php echo esc_attr($admin['phone']); ?>" class="eau-admin-phone">
                                    <i data-lucide="phone"></i>
                                    <?php echo esc_html($admin['phone']); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="eau-admins-empty">
                    <p>No institution administrators found.</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- v1.67.3 - Modal para remover membro com motivo -->
            <?php if ($can_edit): ?>
            <?php echo self::render_remove_member_modal(); ?>
            <?php endif; ?>

        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Conta membros da instituição
     */
    private static function count_institution_members($company_code) {
        if (empty($company_code)) {
            return 0;
        }

        $args = array(
            'meta_query' => array(
                array(
                    'key' => 'mem_membercompanyname',
                    'value' => $company_code,
                    'compare' => '=',
                ),
            ),
            'count_total' => true,
            'fields' => 'ID',
        );

        $user_query = new \WP_User_Query($args);
        return (int) $user_query->get_total();
    }

    /**
     * Conta admins da instituição
     *
     * @since 1.66.4 - Atualizado para usar o novo sistema de separação de papéis
     */
    private static function count_institution_admins($institution_id) {
        // Usa o helper para obter os user IDs (combina novo sistema + legado + primary contact)
        $user_ids = Eau_User_Institution_Helper::get_institution_admin_user_ids($institution_id);
        return count($user_ids);
    }

    /**
     * Pega dados do primary contact
     */
    private static function get_primary_contact_data($mem_userid) {
        global $wpdb;

        // Busca usuário pelo mem_userid
        $user_id = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta}
            WHERE meta_key = 'mem_userid' AND meta_value = %s
            LIMIT 1",
            $mem_userid
        ));

        if (!$user_id) {
            return null;
        }

        $user = get_userdata($user_id);
        if (!$user) {
            return null;
        }

        $first_name = get_user_meta($user_id, 'first_name', true);
        $last_name = get_user_meta($user_id, 'last_name', true);
        $full_name = trim($first_name . ' ' . $last_name);
        if (empty($full_name)) {
            $full_name = $user->display_name;
        }

        $phone = get_user_meta($user_id, 'mem_memberphone', true);
        $avatar = get_avatar_url($user_id, array('size' => 96));

        return array(
            'id' => $user_id,
            'name' => $full_name,
            'email' => $user->user_email,
            'phone' => $phone,
            'avatar' => $avatar,
        );
    }

    /**
     * Obtém os administradores de uma instituição
     *
     * @since 1.66.0 - Atualizado para usar o novo sistema de separação de papéis
     * @param string $company_id Company ID da instituição
     * @return array Array de administradores
     */
    private static function get_institution_admins($company_id) {
        if (empty($company_id)) {
            return array();
        }

        // Busca o institution_id a partir do company_id
        global $wpdb;
        $institution_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta}
            WHERE meta_key = 'ins_company_id' AND meta_value = %s
            LIMIT 1",
            $company_id
        ));

        if (!$institution_id) {
            return array();
        }

        // Usa o helper para obter os user IDs (combina novo sistema + legado + primary contact)
        $user_ids = Eau_User_Institution_Helper::get_institution_admin_user_ids($institution_id);

        if (empty($user_ids)) {
            return array();
        }

        // Formata os dados dos administradores
        $admins = array();
        foreach ($user_ids as $user_id) {
            $user = get_userdata($user_id);
            if (!$user) {
                continue;
            }

            $first_name = get_user_meta($user->ID, 'first_name', true);
            $last_name = get_user_meta($user->ID, 'last_name', true);
            $full_name = trim($first_name . ' ' . $last_name);
            if (empty($full_name)) {
                $full_name = $user->display_name;
            }

            $phone = get_user_meta($user->ID, 'mem_memberphone', true);
            $avatar = get_avatar_url($user->ID, array('size' => 96));

            $admins[] = array(
                'id' => $user->ID,
                'name' => $full_name,
                'email' => $user->user_email,
                'phone' => $phone,
                'avatar' => $avatar,
            );
        }

        // Ordena por nome
        usort($admins, function($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });

        return $admins;
    }

    /**
     * AJAX: Get institution members paginated
     */
    public static function ajax_get_institution_members() {
        check_ajax_referer('eau_institution_single_nonce', 'nonce');

        $institution_id = isset($_POST['institution_id']) ? absint($_POST['institution_id']) : 0;
        $page = isset($_POST['page']) ? absint($_POST['page']) : 1;
        $per_page = isset($_POST['per_page']) ? absint($_POST['per_page']) : 20;
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';

        if (!$institution_id) {
            wp_send_json_error(array('message' => 'Invalid institution ID.'));
        }

        // Verifica permissões
        if (!self::user_can_view_institution($institution_id)) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        // Pega company_id da instituição
        $company_id = get_post_meta($institution_id, 'ins_company_id', true);

        if (empty($company_id)) {
            wp_send_json_success(array(
                'members' => array(),
                'total' => 0,
                'total_pages' => 0,
                'page' => 1,
            ));
        }

        // Query para membros
        $meta_query = array(
            array(
                'key' => 'mem_membercompanyname',
                'value' => $company_id,
                'compare' => '=',
            ),
        );

        // Filtro de status
        if (!empty($status)) {
            $meta_query[] = array(
                'key' => 'mem_membership_status',
                'value' => $status,
                'compare' => '=',
            );
        }

        $args = array(
            'meta_query' => $meta_query,
            'number' => $per_page,
            'paged' => $page,
            'orderby' => 'display_name',
            'order' => 'ASC',
        );

        // Busca textual
        if (!empty($search)) {
            $args['search'] = '*' . $search . '*';
            $args['search_columns'] = array('user_login', 'user_email', 'display_name');
        }

        $user_query = new \WP_User_Query($args);
        $users = $user_query->get_results();
        $total = $user_query->get_total();

        // Formata membros
        $members = array();
        foreach ($users as $user) {
            $first_name = get_user_meta($user->ID, 'first_name', true);
            $last_name = get_user_meta($user->ID, 'last_name', true);
            $full_name = trim($first_name . ' ' . $last_name);
            if (empty($full_name)) {
                $full_name = $user->display_name;
            }

            $mem_type = get_user_meta($user->ID, 'mem_type', true);
            // v1.67.2 - Se o membro está na lista (vinculado à instituição), considera 'active'
            // exceto se status for explicitamente cancelled, expired ou suspended
            $raw_status = get_user_meta($user->ID, 'mem_membership_status', true);
            $inactive_statuses = array('cancelled', 'expired', 'suspended');
            if (in_array($raw_status, $inactive_statuses)) {
                $mem_status = $raw_status;
            } else {
                // Membros vinculados são considerados ativos por padrão
                $mem_status = 'active';
            }
            $mem_phone = get_user_meta($user->ID, 'mem_memberphone', true);
            $avatar = get_avatar_url($user->ID, array('size' => 48));

            $members[] = array(
                'id' => $user->ID,
                'name' => $full_name,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $user->user_email,
                'phone' => $mem_phone,
                'type' => $mem_type,
                'status' => $mem_status,
                'avatar' => $avatar,
            );
        }

        wp_send_json_success(array(
            'members' => $members,
            'total' => $total,
            'total_pages' => ceil($total / $per_page),
            'page' => $page,
        ));
    }

    /**
     * Verifica se usuário pode editar a instituição
     *
     * @param int $institution_id ID da instituição
     * @return bool
     */
    public static function user_can_edit_institution($institution_id) {
        $current_user_id = get_current_user_id();

        // superAdmin e Admin podem editar todas
        if (Eau_User_Institution_Helper::has_admin_access($current_user_id)) {
            return true;
        }

        // institutionAdmin pode editar suas instituições
        if (Eau_User_Institution_Helper::is_institution_admin($current_user_id)) {
            // Verifica se é Primary Contact da instituição
            $managed_institutions = Eau_User_Institution_Helper::get_user_managed_institutions($current_user_id);
            foreach ($managed_institutions as $inst) {
                if ($inst->ID == $institution_id) {
                    return true;
                }
            }

            // Verifica se é institutionAdmin E membro da instituição (v1.65.5)
            $ins_company_id = get_post_meta($institution_id, 'ins_company_id', true);
            if (!empty($ins_company_id)) {
                $user_company_id = get_user_meta($current_user_id, 'mem_membercompanyname', true);
                if ($user_company_id === $ins_company_id) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Verifica se usuário pode editar campos restritos (start_date, expire_date, status)
     *
     * @return bool
     */
    public static function user_can_edit_restricted_fields() {
        $current_user_id = get_current_user_id();
        return Eau_User_Institution_Helper::has_admin_access($current_user_id);
    }

    /**
     * Retorna permissões do usuário atual para uma instituição
     *
     * @param int $institution_id ID da instituição
     * @return array
     */
    public static function get_user_permissions($institution_id) {
        return array(
            'can_edit' => self::user_can_edit_institution($institution_id),
            'can_edit_restricted' => self::user_can_edit_restricted_fields(),
            'can_manage_admins' => self::user_can_manage_institution_admins($institution_id),
        );
    }

    /**
     * Verifica se usuário pode gerenciar admins da instituição
     *
     * institutionAdmin pode promover/rebaixar outros membros da sua instituição
     *
     * @param int $institution_id ID da instituição
     * @return bool
     * @since 1.65.6
     */
    public static function user_can_manage_institution_admins($institution_id) {
        $current_user_id = get_current_user_id();

        // superAdmin e Admin podem gerenciar admins de qualquer instituição
        if (Eau_User_Institution_Helper::has_admin_access($current_user_id)) {
            return true;
        }

        // institutionAdmin pode gerenciar admins da sua própria instituição
        if (Eau_User_Institution_Helper::is_institution_admin($current_user_id)) {
            // Verifica se é Primary Contact da instituição
            $managed_institutions = Eau_User_Institution_Helper::get_user_managed_institutions($current_user_id);
            foreach ($managed_institutions as $inst) {
                if ($inst->ID == $institution_id) {
                    return true;
                }
            }

            // Verifica se é institutionAdmin E membro da instituição
            $ins_company_id = get_post_meta($institution_id, 'ins_company_id', true);
            if (!empty($ins_company_id)) {
                $user_company_id = get_user_meta($current_user_id, 'mem_membercompanyname', true);
                if ($user_company_id === $ins_company_id) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * AJAX: Salvar dados da instituição
     */
    public static function ajax_save_institution() {
        check_ajax_referer('eau_institution_single_nonce', 'nonce');

        $institution_id = isset($_POST['institution_id']) ? absint($_POST['institution_id']) : 0;

        if (!$institution_id) {
            wp_send_json_error(array('message' => 'Invalid institution ID.'));
        }

        // Verifica permissões
        if (!self::user_can_edit_institution($institution_id)) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        // Campos editáveis por todos (admin, superAdmin, institutionAdmin)
        $editable_fields = array(
            'ins_company_name'                  => 'sanitize_text_field',
            'ins_company_id'                    => 'sanitize_text_field',
            'ins_type'                          => 'sanitize_text_field',
            'ins_company_email'                 => 'sanitize_email',
            'ins_company_company_phone'         => 'sanitize_text_field',
            'ins_company_company_address_line_1'=> 'sanitize_text_field',
            'ins_company_company_suburb'        => 'sanitize_text_field',
            'ins_company_company_state'         => 'sanitize_text_field',
            'ins_company_company_postcode'      => 'sanitize_text_field',
            'ins_company_company_country'       => 'sanitize_text_field',
            'ins_member_company_campus'         => 'sanitize_text_field',
        );

        // Campos restritos (apenas admin e superAdmin)
        $restricted_fields = array(
            'ins_status'              => 'sanitize_text_field',
            'ins_member_start_date'   => 'sanitize_text_field',
            'ins_member_expire_date'  => 'sanitize_text_field',
        );

        $can_edit_restricted = self::user_can_edit_restricted_fields();

        // Salva campos editáveis
        foreach ($editable_fields as $field => $sanitize_callback) {
            if (isset($_POST[$field])) {
                $value = call_user_func($sanitize_callback, $_POST[$field]);
                update_post_meta($institution_id, $field, $value);
            }
        }

        // Salva campos restritos se tiver permissão
        if ($can_edit_restricted) {
            foreach ($restricted_fields as $field => $sanitize_callback) {
                if (isset($_POST[$field])) {
                    $value = call_user_func($sanitize_callback, $_POST[$field]);
                    update_post_meta($institution_id, $field, $value);
                }
            }
        }

        // Atualiza o título do post se o nome da empresa mudou
        if (isset($_POST['ins_company_name'])) {
            wp_update_post(array(
                'ID' => $institution_id,
                'post_title' => sanitize_text_field($_POST['ins_company_name']),
            ));
        }

        wp_send_json_success(array(
            'message' => 'Institution updated successfully.',
            'institution_id' => $institution_id,
        ));
    }

    /**
     * AJAX: Salvar um único campo da instituição (para edição inline)
     */
    public static function ajax_save_institution_field() {
        check_ajax_referer('eau_institution_single_nonce', 'nonce');

        $institution_id = isset($_POST['institution_id']) ? absint($_POST['institution_id']) : 0;
        $field = isset($_POST['field']) ? sanitize_text_field($_POST['field']) : '';
        $value = isset($_POST['value']) ? $_POST['value'] : '';

        if (!$institution_id || empty($field)) {
            wp_send_json_error(array('message' => 'Invalid request.'));
        }

        // Verifica permissões gerais
        if (!self::user_can_edit_institution($institution_id)) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        // Campos editáveis por todos (admin, superAdmin, institutionAdmin)
        $editable_fields = array(
            'ins_company_name'                   => 'sanitize_text_field',
            'ins_company_id'                     => 'sanitize_text_field',
            'ins_type'                           => 'sanitize_text_field',
            'ins_company_email'                  => 'sanitize_email',
            'ins_company_company_phone'          => 'sanitize_text_field',
            'ins_company_company_address_line_1' => 'sanitize_text_field',
            'ins_company_company_suburb'         => 'sanitize_text_field',
            'ins_company_company_state'          => 'sanitize_text_field',
            'ins_company_company_postcode'       => 'sanitize_text_field',
            'ins_company_company_country'        => 'sanitize_text_field',
            'ins_member_company_campus'          => 'sanitize_text_field',
        );

        // Campos restritos (apenas admin e superAdmin)
        $restricted_fields = array(
            'ins_status'              => 'sanitize_text_field',
            'ins_member_start_date'   => 'sanitize_text_field',
            'ins_member_expire_date'  => 'sanitize_text_field',
        );

        $can_edit_restricted = self::user_can_edit_restricted_fields();

        // Verifica se o campo é válido
        $is_editable = isset($editable_fields[$field]);
        $is_restricted = isset($restricted_fields[$field]);

        if (!$is_editable && !$is_restricted) {
            wp_send_json_error(array('message' => 'Invalid field.'));
        }

        // Verifica permissão para campos restritos
        if ($is_restricted && !$can_edit_restricted) {
            wp_send_json_error(array('message' => 'You do not have permission to edit this field.'));
        }

        // Sanitiza o valor
        $sanitize_callback = $is_editable ? $editable_fields[$field] : $restricted_fields[$field];
        $sanitized_value = call_user_func($sanitize_callback, $value);

        // Salva o valor
        update_post_meta($institution_id, $field, $sanitized_value);

        // Atualiza o título do post se o nome da empresa mudou
        if ($field === 'ins_company_name') {
            wp_update_post(array(
                'ID' => $institution_id,
                'post_title' => $sanitized_value,
            ));
        }

        // Gera o HTML de display atualizado
        $display = self::get_field_display_html($field, $sanitized_value, $institution_id);

        wp_send_json_success(array(
            'message' => 'Field updated successfully.',
            'field' => $field,
            'value' => $sanitized_value,
            'display' => $display,
        ));
    }

    /**
     * Gera HTML de display para um campo
     */
    private static function get_field_display_html($field, $value, $institution_id) {
        if (empty($value)) {
            return '<span class="eau-empty-value">Click to edit</span>';
        }

        switch ($field) {
            case 'ins_company_email':
                return '<a href="mailto:' . esc_attr($value) . '">' . esc_html($value) . '</a>';

            case 'ins_company_company_phone':
                return '<a href="tel:' . esc_attr($value) . '">' . esc_html($value) . '</a>';

            case 'ins_type':
                $badge_class = $value === 'College' ? 'eau-badge-college' : 'eau-badge-corporate';
                return '<span class="eau-badge eau-badge-type ' . $badge_class . '">' . esc_html($value) . '</span>';

            case 'ins_status':
                $badge_class = $value === 'active' ? 'eau-badge-active' : 'eau-badge-inactive';
                return '<span class="eau-badge eau-badge-status ' . $badge_class . '">' . esc_html(ucfirst($value)) . '</span>';

            case 'ins_member_start_date':
                return esc_html(Eau_Institutions_Meta_Sync::format_date($value));

            case 'ins_member_expire_date':
                $expiration_status = Eau_Institutions_Meta_Sync::get_expiration_status($institution_id);
                $html = '<span class="eau-expire-badge ' . esc_attr($expiration_status['class']) . '">';
                $html .= esc_html(Eau_Institutions_Meta_Sync::format_date($value));
                if ($expiration_status['status'] !== 'ok' && $expiration_status['status'] !== 'not_set') {
                    $html .= ' <span class="eau-expire-label">' . esc_html($expiration_status['label']) . '</span>';
                }
                $html .= '</span>';
                return $html;

            case 'ins_company_company_state':
                $states = self::get_australian_states();
                return isset($states[$value]) ? esc_html($states[$value]) : esc_html($value);

            case 'ins_company_company_country':
                $countries = self::get_countries();
                return isset($countries[$value]) ? esc_html($countries[$value]) : esc_html($value);

            default:
                return esc_html($value);
        }
    }

    /**
     * Retorna lista de estados australianos
     */
    public static function get_australian_states() {
        return array(
            '' => 'Select State',
            'ACT' => 'Australian Capital Territory',
            'NSW' => 'New South Wales',
            'NT' => 'Northern Territory',
            'QLD' => 'Queensland',
            'SA' => 'South Australia',
            'TAS' => 'Tasmania',
            'VIC' => 'Victoria',
            'WA' => 'Western Australia',
        );
    }

    /**
     * Retorna lista de países
     */
    public static function get_countries() {
        return array(
            '' => 'Select Country',
            'AU' => 'Australia',
            'NZ' => 'New Zealand',
            'US' => 'United States',
            'GB' => 'United Kingdom',
            'CA' => 'Canada',
            'SG' => 'Singapore',
            'MY' => 'Malaysia',
            'ID' => 'Indonesia',
            'PH' => 'Philippines',
            'TH' => 'Thailand',
            'VN' => 'Vietnam',
            'JP' => 'Japan',
            'KR' => 'South Korea',
            'CN' => 'China',
            'HK' => 'Hong Kong',
            'TW' => 'Taiwan',
            'IN' => 'India',
            'PK' => 'Pakistan',
            'BD' => 'Bangladesh',
            'LK' => 'Sri Lanka',
            'NP' => 'Nepal',
            'AE' => 'United Arab Emirates',
            'SA' => 'Saudi Arabia',
            'QA' => 'Qatar',
            'KW' => 'Kuwait',
            'BH' => 'Bahrain',
            'OM' => 'Oman',
            'ZA' => 'South Africa',
            'KE' => 'Kenya',
            'NG' => 'Nigeria',
            'EG' => 'Egypt',
            'DE' => 'Germany',
            'FR' => 'France',
            'IT' => 'Italy',
            'ES' => 'Spain',
            'PT' => 'Portugal',
            'NL' => 'Netherlands',
            'BE' => 'Belgium',
            'CH' => 'Switzerland',
            'AT' => 'Austria',
            'SE' => 'Sweden',
            'NO' => 'Norway',
            'DK' => 'Denmark',
            'FI' => 'Finland',
            'IE' => 'Ireland',
            'PL' => 'Poland',
            'CZ' => 'Czech Republic',
            'GR' => 'Greece',
            'BR' => 'Brazil',
            'AR' => 'Argentina',
            'CL' => 'Chile',
            'CO' => 'Colombia',
            'MX' => 'Mexico',
            'PE' => 'Peru',
            'OTHER' => 'Other',
        );
    }

    /**
     * AJAX: Upload de logo da instituição
     */
    public static function ajax_upload_institution_logo() {
        check_ajax_referer('eau_institution_single_nonce', 'nonce');

        $institution_id = isset($_POST['institution_id']) ? absint($_POST['institution_id']) : 0;

        if (!$institution_id) {
            wp_send_json_error(array('message' => 'Invalid institution ID.'));
        }

        // Verifica permissões
        if (!self::user_can_edit_institution($institution_id)) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        // Verifica se há arquivo
        if (empty($_FILES['logo'])) {
            wp_send_json_error(array('message' => 'No file uploaded.'));
        }

        // Inclui funções de mídia do WordPress
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        // Faz upload do arquivo
        $attachment_id = media_handle_upload('logo', 0);

        if (is_wp_error($attachment_id)) {
            wp_send_json_error(array('message' => $attachment_id->get_error_message()));
        }

        // Salva o ID do attachment como logo
        update_post_meta($institution_id, 'ins_company_logo', $attachment_id);

        // Retorna a URL da nova imagem
        $logo_url = wp_get_attachment_url($attachment_id);

        wp_send_json_success(array(
            'message' => 'Logo updated successfully.',
            'logo_url' => $logo_url,
            'attachment_id' => $attachment_id,
        ));
    }

    /**
     * AJAX: Salvar campo de um membro
     */
    public static function ajax_save_member_field() {
        check_ajax_referer('eau_institution_single_nonce', 'nonce');

        $member_id = isset($_POST['member_id']) ? absint($_POST['member_id']) : 0;
        $field = isset($_POST['field']) ? sanitize_text_field($_POST['field']) : '';
        $value = isset($_POST['value']) ? $_POST['value'] : '';
        $institution_id = isset($_POST['institution_id']) ? absint($_POST['institution_id']) : 0;

        if (!$member_id || empty($field)) {
            wp_send_json_error(array('message' => 'Invalid request.'));
        }

        // Verifica permissões
        if (!self::user_can_edit_members($institution_id)) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        // Campos editáveis do membro
        $editable_fields = array(
            'first_name' => 'sanitize_text_field',
            'last_name' => 'sanitize_text_field',
            'user_email' => 'sanitize_email',
            'mem_memberphone' => 'sanitize_text_field',
            'mem_type' => 'sanitize_text_field',
            'mem_membership_status' => 'sanitize_text_field',
        );

        if (!isset($editable_fields[$field])) {
            wp_send_json_error(array('message' => 'Invalid field.'));
        }

        // Sanitiza o valor
        $sanitized_value = call_user_func($editable_fields[$field], $value);

        // Salva o valor
        if ($field === 'user_email') {
            // Atualiza email do usuário
            wp_update_user(array(
                'ID' => $member_id,
                'user_email' => $sanitized_value,
            ));
        } else {
            update_user_meta($member_id, $field, $sanitized_value);
        }

        wp_send_json_success(array(
            'message' => 'Member updated successfully.',
            'field' => $field,
            'value' => $sanitized_value,
        ));
    }

    /**
     * AJAX: Remover membro da instituição (torna non-member)
     *
     * Uses centralized Eau_Member_Institution_Service for proper unlinking
     * with history tracking.
     *
     * @since 1.63.0
     * @since 1.65.4 Uses centralized service with history tracking
     * @since 1.67.4 Added optional reason parameter and email notification
     */
    public static function ajax_remove_member_from_institution() {
        check_ajax_referer('eau_institution_single_nonce', 'nonce');

        $member_id = isset($_POST['member_id']) ? absint($_POST['member_id']) : 0;
        $institution_id = isset($_POST['institution_id']) ? absint($_POST['institution_id']) : 0;
        $admin_reason = isset($_POST['reason']) ? sanitize_textarea_field($_POST['reason']) : '';

        if (!$member_id || !$institution_id) {
            wp_send_json_error(array('message' => 'Invalid request.'));
        }

        // Verifica permissões
        if (!self::user_can_edit_members($institution_id)) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        // Verifica se o membro pode ser desvinculado (valida se é único admin)
        $can_unlink = Eau_Member_Institution_Service::can_unlink_member($member_id);
        if (is_wp_error($can_unlink)) {
            wp_send_json_error(array(
                'message' => $can_unlink->get_error_message(),
                'error_code' => $can_unlink->get_error_code(),
            ));
        }

        // Get institution name for the reason
        $institution = get_post($institution_id);
        $institution_name = $institution ? $institution->post_title : 'Unknown';

        // v1.67.4 - Build the reason for history (includes admin reason if provided)
        $history_reason = sprintf('Removed from institution "%s" by administrator', $institution_name);
        if (!empty($admin_reason)) {
            $history_reason .= '. Reason: ' . $admin_reason;
        }

        // Use centralized service to unlink member with history tracking
        $result = Eau_Member_Institution_Service::unlink_member($member_id, array(
            'reason' => $history_reason,
            'performed_by' => get_current_user_id(),
            'update_membership_status' => true,
            'new_membership_status' => 'inactive',
        ));

        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'message' => $result->get_error_message(),
            ));
        }

        // v1.66.8 - NÃO alterar mem_type se usuário tem papel administrativo do sistema
        // Papéis do sistema (superAdmin, Admin) devem ser preservados
        $current_type = get_user_meta($member_id, 'mem_type', true);
        $system_admin_roles = array('superAdmin', 'Admin');

        // Se é array, verifica se contém papel administrativo
        if (is_array($current_type)) {
            $has_system_admin = !empty(array_intersect($current_type, $system_admin_roles));
        } else {
            $has_system_admin = in_array($current_type, $system_admin_roles);
        }

        // Só muda para non-member se NÃO for admin do sistema
        if (!$has_system_admin) {
            update_user_meta($member_id, 'mem_type', 'non-member');
        }

        // Remove da lista de managed institutions (se aplicável)
        Eau_User_Institution_Helper::remove_managed_institution($member_id, $institution_id);

        // v1.67.4 - Send email notification to the removed member
        Email\Email_Membership::send_member_removed_from_institution(
            $member_id,
            $institution_id,
            $admin_reason
        );

        wp_send_json_success(array(
            'message' => 'Member removed from institution successfully.',
            'member_id' => $member_id,
        ));
    }

    /**
     * AJAX: Definir/remover membro como admin da instituição
     *
     * @since 1.63.0
     * @since 1.65.6 Now institutionAdmin can also manage admins of their own institution
     * @since 1.66.0 Novo sistema de separação de papéis - NÃO altera mem_type, preservando papel original
     */
    public static function ajax_set_institution_admin() {
        check_ajax_referer('eau_institution_single_nonce', 'nonce');

        $member_id = isset($_POST['member_id']) ? absint($_POST['member_id']) : 0;
        $institution_id = isset($_POST['institution_id']) ? absint($_POST['institution_id']) : 0;
        $make_admin = isset($_POST['make_admin']) ? filter_var($_POST['make_admin'], FILTER_VALIDATE_BOOLEAN) : false;

        if (!$member_id || !$institution_id) {
            wp_send_json_error(array('message' => 'Invalid request.'));
        }

        // superAdmin, Admin e institutionAdmin (da própria instituição) podem gerenciar admins
        if (!self::user_can_manage_institution_admins($institution_id)) {
            wp_send_json_error(array('message' => 'You do not have permission to manage administrators for this institution.'));
        }

        $current_type = get_user_meta($member_id, 'mem_type', true);

        if ($make_admin) {
            // Novo sistema (v1.66.0): Adiciona à lista de managed institutions
            // NÃO altera mem_type - preserva papel original (superAdmin, Admin, member, etc.)
            Eau_User_Institution_Helper::add_managed_institution($member_id, $institution_id);

            // Pega o mem_userid do membro
            $mem_userid = get_user_meta($member_id, 'mem_userid', true);

            // Adiciona como primary contact se não houver um
            $current_primary = get_post_meta($institution_id, 'ins_company_primary_contact', true);
            if (empty($current_primary)) {
                update_post_meta($institution_id, 'ins_company_primary_contact', $mem_userid);
            }

            $message = 'Member promoted to Institution Admin.';
        } else {
            // Novo sistema (v1.66.0): Remove da lista de managed institutions
            // Se era 'institutionAdmin' (legado) e não administrar mais nenhuma, muda para 'member'
            Eau_User_Institution_Helper::remove_managed_institution($member_id, $institution_id, true);

            // Remove do primary contact se era o primary contact desta instituição
            $mem_userid = get_user_meta($member_id, 'mem_userid', true);
            $current_primary = get_post_meta($institution_id, 'ins_company_primary_contact', true);
            if ($current_primary === $mem_userid) {
                delete_post_meta($institution_id, 'ins_company_primary_contact');
            }

            $message = 'Admin role removed from member.';
        }

        wp_send_json_success(array(
            'message' => $message,
            'member_id' => $member_id,
            'new_type' => get_user_meta($member_id, 'mem_type', true),
            'is_institution_admin' => Eau_User_Institution_Helper::is_institution_admin($member_id),
        ));
    }

    /**
     * AJAX: Retorna os administradores da instituição
     */
    public static function ajax_get_institution_admins() {
        check_ajax_referer('eau_institution_single_nonce', 'nonce');

        $institution_id = isset($_POST['institution_id']) ? absint($_POST['institution_id']) : 0;

        if (!$institution_id) {
            wp_send_json_error(array('message' => 'Invalid institution ID.'));
        }

        $company_id = get_post_meta($institution_id, 'ins_company_id', true);
        $admins = self::get_institution_admins($company_id);

        // Gera o HTML dos cards
        ob_start();
        if (!empty($admins)) {
            ?>
            <div class="eau-admins-grid">
                <?php foreach ($admins as $admin): ?>
                <div class="eau-admin-card">
                    <div class="eau-admin-avatar">
                        <?php if (!empty($admin['avatar'])): ?>
                            <img src="<?php echo esc_url($admin['avatar']); ?>" alt="<?php echo esc_attr($admin['name']); ?>">
                        <?php else: ?>
                            <div class="eau-avatar-placeholder">
                                <?php echo esc_html(strtoupper(substr($admin['name'], 0, 1))); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="eau-admin-info">
                        <h4><?php echo esc_html($admin['name']); ?></h4>
                        <?php if (!empty($admin['email'])): ?>
                            <a href="mailto:<?php echo esc_attr($admin['email']); ?>" class="eau-admin-email">
                                <i data-lucide="mail"></i>
                                <?php echo esc_html($admin['email']); ?>
                            </a>
                        <?php endif; ?>
                        <?php if (!empty($admin['phone'])): ?>
                            <a href="tel:<?php echo esc_attr($admin['phone']); ?>" class="eau-admin-phone">
                                <i data-lucide="phone"></i>
                                <?php echo esc_html($admin['phone']); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php
        } else {
            ?>
            <div class="eau-admins-empty">
                <p>No institution administrators found.</p>
            </div>
            <?php
        }
        $html = ob_get_clean();

        wp_send_json_success(array(
            'html' => $html,
            'count' => count($admins),
        ));
    }

    /**
     * Verifica se usuário pode editar membros da instituição
     *
     * @since 1.63.0
     * @since 1.65.5 Added check for institutionAdmin who is member of the institution
     */
    public static function user_can_edit_members($institution_id) {
        $current_user_id = get_current_user_id();

        // superAdmin e Admin podem editar membros de qualquer instituição
        if (Eau_User_Institution_Helper::has_admin_access($current_user_id)) {
            return true;
        }

        // institutionAdmin pode editar membros de suas instituições
        if (Eau_User_Institution_Helper::is_institution_admin($current_user_id)) {
            // Verifica se é Primary Contact da instituição
            $managed_institutions = Eau_User_Institution_Helper::get_user_managed_institutions($current_user_id);
            foreach ($managed_institutions as $inst) {
                if ($inst->ID == $institution_id) {
                    return true;
                }
            }

            // Verifica se é institutionAdmin E membro da instituição
            $ins_company_id = get_post_meta($institution_id, 'ins_company_id', true);
            if (!empty($ins_company_id)) {
                $user_company_id = get_user_meta($current_user_id, 'mem_membercompanyname', true);
                if ($user_company_id === $ins_company_id) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * AJAX: Buscar membros da instituição para select de Primary Contact
     */
    public static function ajax_get_members_for_select() {
        check_ajax_referer('eau_institution_single_nonce', 'nonce');

        $institution_id = isset($_POST['institution_id']) ? absint($_POST['institution_id']) : 0;
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';

        if (!$institution_id) {
            wp_send_json_error(array('message' => 'Invalid institution ID.'));
        }

        // Apenas admin e superAdmin podem alterar primary contact
        if (!self::user_can_edit_restricted_fields()) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        // Pega company_id da instituição
        $company_id = get_post_meta($institution_id, 'ins_company_id', true);

        if (empty($company_id)) {
            wp_send_json_success(array('members' => array()));
        }

        // Query para membros
        $args = array(
            'meta_query' => array(
                array(
                    'key' => 'mem_membercompanyname',
                    'value' => $company_id,
                    'compare' => '=',
                ),
            ),
            'number' => 50,
            'orderby' => 'display_name',
            'order' => 'ASC',
        );

        // Busca textual
        if (!empty($search)) {
            $args['search'] = '*' . $search . '*';
            $args['search_columns'] = array('user_login', 'user_email', 'display_name');
        }

        $user_query = new \WP_User_Query($args);
        $users = $user_query->get_results();

        // Formata membros
        $members = array();
        foreach ($users as $user) {
            $first_name = get_user_meta($user->ID, 'first_name', true);
            $last_name = get_user_meta($user->ID, 'last_name', true);
            $full_name = trim($first_name . ' ' . $last_name);
            if (empty($full_name)) {
                $full_name = $user->display_name;
            }

            $mem_userid = get_user_meta($user->ID, 'mem_userid', true);
            $mem_type = get_user_meta($user->ID, 'mem_type', true);
            $avatar = get_avatar_url($user->ID, array('size' => 48));

            $members[] = array(
                'id' => $user->ID,
                'mem_userid' => $mem_userid,
                'name' => $full_name,
                'email' => $user->user_email,
                'type' => $mem_type,
                'avatar' => $avatar,
            );
        }

        wp_send_json_success(array('members' => $members));
    }

    /**
     * AJAX: Definir novo Primary Contact da instituição
     */
    public static function ajax_set_primary_contact() {
        check_ajax_referer('eau_institution_single_nonce', 'nonce');

        $institution_id = isset($_POST['institution_id']) ? absint($_POST['institution_id']) : 0;
        $member_id = isset($_POST['member_id']) ? absint($_POST['member_id']) : 0;

        if (!$institution_id || !$member_id) {
            wp_send_json_error(array('message' => 'Invalid request.'));
        }

        // Apenas admin e superAdmin podem alterar primary contact
        if (!self::user_can_edit_restricted_fields()) {
            wp_send_json_error(array('message' => 'Only administrators can change the primary contact.'));
        }

        // Pega o mem_userid do membro
        $mem_userid = get_user_meta($member_id, 'mem_userid', true);

        if (empty($mem_userid)) {
            wp_send_json_error(array('message' => 'Member does not have a valid user ID.'));
        }

        // Atualiza o primary contact da instituição
        update_post_meta($institution_id, 'ins_company_primary_contact', $mem_userid);

        // Promove o membro a institutionAdmin se ainda não for
        $current_type = get_user_meta($member_id, 'mem_type', true);
        if ($current_type !== 'institutionAdmin' && $current_type !== 'Admin' && $current_type !== 'superAdmin') {
            update_user_meta($member_id, 'mem_type', 'institutionAdmin');
        }

        // Busca os dados do novo primary contact
        $primary_contact = self::get_primary_contact_data($mem_userid);

        wp_send_json_success(array(
            'message' => 'Primary contact updated successfully.',
            'primary_contact' => $primary_contact,
        ));
    }

    /**
     * Render modal for removing member with reason
     *
     * @since 1.67.3
     * @return string HTML
     */
    private static function render_remove_member_modal() {
        ob_start();
        ?>
        <div class="eau-modal-overlay" id="eau-remove-member-modal-overlay" style="display: none;">
            <div class="eau-modal eau-modal-small" id="eau-remove-member-modal">
                <div class="eau-modal-header">
                    <h2 class="eau-modal-title">
                        <i data-lucide="user-x"></i>
                        Remove Member from Institution
                    </h2>
                    <button class="eau-modal-close" type="button" data-action="close">
                        <i data-lucide="x"></i>
                    </button>
                </div>
                <div class="eau-modal-body">
                    <div class="eau-alert eau-alert-warning eau-mb-4">
                        <i data-lucide="alert-triangle"></i>
                        <span>This action will remove the member from this institution. They will become a "non-member" and will need to request membership again to rejoin.</span>
                    </div>

                    <div class="eau-member-to-remove">
                        <p class="eau-text-muted">You are about to remove:</p>
                        <p class="eau-member-name-display"><strong id="eau-remove-member-name"></strong></p>
                        <p class="eau-member-email-display eau-text-muted" id="eau-remove-member-email"></p>
                    </div>

                    <div class="eau-form-field eau-mt-4">
                        <label class="eau-form-label" for="eau-remove-member-reason">
                            Reason for removal (optional)
                        </label>
                        <textarea class="eau-form-input" id="eau-remove-member-reason" rows="3"
                                  placeholder="Explain why this member is being removed. This will be visible to them..."></textarea>
                        <p class="eau-form-hint">The member will receive an email notification with this reason.</p>
                    </div>
                </div>
                <div class="eau-modal-footer">
                    <button type="button" class="eau-btn eau-btn-secondary" data-action="close">
                        Cancel
                    </button>
                    <button type="button" class="eau-btn eau-btn-danger" id="eau-confirm-remove-member-btn">
                        <i data-lucide="user-x"></i>
                        Remove Member
                    </button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
