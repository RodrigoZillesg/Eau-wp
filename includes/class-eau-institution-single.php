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
     * Verifica se usuário pode visualizar a instituição
     */
    public static function user_can_view_institution($institution_id) {
        $current_user_id = get_current_user_id();

        // superAdmin e Admin podem ver todas
        if (Eau_User_Institution_Helper::has_admin_access($current_user_id)) {
            return true;
        }

        // institutionAdmin pode ver apenas suas instituições
        if (Eau_User_Institution_Helper::is_institution_admin($current_user_id)) {
            $managed_institutions = Eau_User_Institution_Helper::get_user_managed_institutions($current_user_id);
            foreach ($managed_institutions as $inst) {
                if ($inst->ID == $institution_id) {
                    return true;
                }
            }
            return false;
        }

        // Membros regulares podem ver apenas sua própria instituição
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
     */
    private static function count_institution_admins($institution_id) {
        global $wpdb;

        $mem_userid = get_post_meta($institution_id, 'ins_company_primary_contact', true);

        if (empty($mem_userid)) {
            return 0;
        }

        // Conta usuários com mem_userid que são institutionAdmin
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT um1.user_id)
            FROM {$wpdb->usermeta} um1
            INNER JOIN {$wpdb->usermeta} um2 ON um1.user_id = um2.user_id
            WHERE um1.meta_key = 'mem_userid' AND um1.meta_value = %s
            AND um2.meta_key = 'mem_type' AND um2.meta_value = 'institutionAdmin'",
            $mem_userid
        ));

        return (int) $count ?: 1; // Pelo menos 1 se tiver primary contact
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
            $mem_status = get_user_meta($user->ID, 'mem_membership_status', true) ?: 'active';
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

        // institutionAdmin pode editar apenas suas instituições
        if (Eau_User_Institution_Helper::is_institution_admin($current_user_id)) {
            $managed_institutions = Eau_User_Institution_Helper::get_user_managed_institutions($current_user_id);
            foreach ($managed_institutions as $inst) {
                if ($inst->ID == $institution_id) {
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
        );
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
     */
    public static function ajax_remove_member_from_institution() {
        check_ajax_referer('eau_institution_single_nonce', 'nonce');

        $member_id = isset($_POST['member_id']) ? absint($_POST['member_id']) : 0;
        $institution_id = isset($_POST['institution_id']) ? absint($_POST['institution_id']) : 0;

        if (!$member_id || !$institution_id) {
            wp_send_json_error(array('message' => 'Invalid request.'));
        }

        // Verifica permissões
        if (!self::user_can_edit_members($institution_id)) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        // Remove o membro da instituição
        delete_user_meta($member_id, 'mem_membercompanyname');

        // Muda o tipo para non-member
        update_user_meta($member_id, 'mem_type', 'non-member');

        // Muda o status para inactive
        update_user_meta($member_id, 'mem_membership_status', 'inactive');

        wp_send_json_success(array(
            'message' => 'Member removed from institution successfully.',
            'member_id' => $member_id,
        ));
    }

    /**
     * AJAX: Definir/remover membro como admin da instituição
     */
    public static function ajax_set_institution_admin() {
        check_ajax_referer('eau_institution_single_nonce', 'nonce');

        $member_id = isset($_POST['member_id']) ? absint($_POST['member_id']) : 0;
        $institution_id = isset($_POST['institution_id']) ? absint($_POST['institution_id']) : 0;
        $make_admin = isset($_POST['make_admin']) ? filter_var($_POST['make_admin'], FILTER_VALIDATE_BOOLEAN) : false;

        if (!$member_id || !$institution_id) {
            wp_send_json_error(array('message' => 'Invalid request.'));
        }

        // Apenas admin e superAdmin podem definir outros admins
        if (!self::user_can_edit_restricted_fields()) {
            wp_send_json_error(array('message' => 'Only admins can set institution administrators.'));
        }

        $current_type = get_user_meta($member_id, 'mem_type', true);

        if ($make_admin) {
            // Promove a institutionAdmin
            update_user_meta($member_id, 'mem_type', 'institutionAdmin');

            // Pega o mem_userid do membro
            $mem_userid = get_user_meta($member_id, 'mem_userid', true);

            // Adiciona como primary contact se não houver um
            $current_primary = get_post_meta($institution_id, 'ins_company_primary_contact', true);
            if (empty($current_primary)) {
                update_post_meta($institution_id, 'ins_company_primary_contact', $mem_userid);
            }

            $message = 'Member promoted to Institution Admin.';
        } else {
            // Remove de institutionAdmin (volta a ser member)
            if ($current_type === 'institutionAdmin') {
                update_user_meta($member_id, 'mem_type', 'member');
            }
            $message = 'Admin role removed from member.';
        }

        wp_send_json_success(array(
            'message' => $message,
            'member_id' => $member_id,
            'new_type' => get_user_meta($member_id, 'mem_type', true),
        ));
    }

    /**
     * Verifica se usuário pode editar membros da instituição
     */
    public static function user_can_edit_members($institution_id) {
        $current_user_id = get_current_user_id();

        // superAdmin e Admin podem editar membros de qualquer instituição
        if (Eau_User_Institution_Helper::has_admin_access($current_user_id)) {
            return true;
        }

        // institutionAdmin pode editar membros apenas de suas instituições
        if (Eau_User_Institution_Helper::is_institution_admin($current_user_id)) {
            $managed_institutions = Eau_User_Institution_Helper::get_user_managed_institutions($current_user_id);
            foreach ($managed_institutions as $inst) {
                if ($inst->ID == $institution_id) {
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
}
