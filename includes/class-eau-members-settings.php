<?php
namespace EauSystem;

/**
 * Página de Configurações do Members Management
 *
 * Permite configurar quais campos serão exibidos e editáveis nos modais
 */
class Eau_Members_Settings {

    /**
     * Option name para salvar as configurações
     */
    const OPTION_NAME = 'eau_members_editable_fields';

    /**
     * Registra hooks
     */
    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'add_admin_menu'));
        add_action('admin_init', array(__CLASS__, 'register_settings'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_admin_assets'));
    }

    /**
     * Adiciona página no menu do admin
     */
    public static function add_admin_menu() {
        add_submenu_page(
            'eau-system',
            'Members Settings',
            'Members Settings',
            'manage_options',
            'eau-members-settings',
            array(__CLASS__, 'render_settings_page')
        );
    }

    /**
     * Registra as settings
     */
    public static function register_settings() {
        register_setting(
            'eau_members_settings_group',
            self::OPTION_NAME,
            array(
                'type' => 'array',
                'sanitize_callback' => array(__CLASS__, 'sanitize_settings'),
            )
        );
    }

    /**
     * Sanitiza os dados antes de salvar
     */
    public static function sanitize_settings($input) {
        if (!is_array($input)) {
            return array();
        }

        $sanitized = array();

        foreach ($input as $key => $field) {
            $sanitized[$key] = array(
                'enabled' => isset($field['enabled']) ? (bool) $field['enabled'] : false,
                'required' => isset($field['required']) ? (bool) $field['required'] : false,
                'readonly' => isset($field['readonly']) ? (bool) $field['readonly'] : false,
                'order' => isset($field['order']) ? absint($field['order']) : 0,
            );
        }

        return $sanitized;
    }

    /**
     * Enfileira assets do admin
     */
    public static function enqueue_admin_assets($hook) {
        // Only load on our settings page
        if ($hook !== 'eau-system_page_eau-members-settings') {
            return;
        }

        wp_enqueue_style(
            'eau-members-settings',
            EAU_SYSTEM_PLUGIN_URL . 'assets/css/eau-members-settings.css',
            array(),
            EAU_SYSTEM_VERSION
        );

        wp_enqueue_script(
            'eau-members-settings',
            EAU_SYSTEM_PLUGIN_URL . 'assets/js/eau-members-settings.js',
            array('jquery', 'jquery-ui-sortable'),
            EAU_SYSTEM_VERSION,
            true
        );
    }

    /**
     * Renderiza a página de configurações
     */
    public static function render_settings_page() {
        // Pega configurações salvas
        $saved_settings = get_option(self::OPTION_NAME, array());

        // Pega todos os campos disponíveis
        $available_fields = self::get_available_fields();

        // Merge com configurações padrão
        $fields_config = array();
        foreach ($available_fields as $key => $field) {
            $fields_config[$key] = array_merge(
                $field,
                isset($saved_settings[$key]) ? $saved_settings[$key] : array()
            );
        }

        // Ordena por order
        uasort($fields_config, function($a, $b) {
            $order_a = isset($a['order']) ? $a['order'] : 999;
            $order_b = isset($b['order']) ? $b['order'] : 999;
            return $order_a - $order_b;
        });

        ?>
        <div class="wrap eau-members-settings-wrap">
            <h1>Members Management Settings</h1>
            <p class="description">Configure which fields are displayed and editable in the View/Edit Member modals.</p>

            <form method="post" action="options.php">
                <?php settings_fields('eau_members_settings_group'); ?>

                <div class="eau-settings-container">

                    <!-- Core Fields -->
                    <div class="eau-settings-section">
                        <h2>WordPress Core Fields</h2>
                        <p class="description">Default WordPress user fields</p>

                        <table class="eau-fields-table" id="eau-core-fields-table">
                            <thead>
                                <tr>
                                    <th class="drag-handle"></th>
                                    <th class="field-name">Field</th>
                                    <th class="field-enabled">Enabled</th>
                                    <th class="field-required">Required</th>
                                    <th class="field-readonly">Read Only</th>
                                </tr>
                            </thead>
                            <tbody class="eau-sortable-tbody">
                                <?php
                                $order = 0;
                                foreach ($fields_config as $key => $field) :
                                    if ($field['type'] !== 'core') continue;
                                    $order++;
                                    self::render_field_row($key, $field, $order);
                                endforeach;
                                ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Meta Fields -->
                    <div class="eau-settings-section">
                        <h2>Custom Meta Fields</h2>
                        <p class="description">JetEngine and custom user meta fields</p>

                        <table class="eau-fields-table" id="eau-meta-fields-table">
                            <thead>
                                <tr>
                                    <th class="drag-handle"></th>
                                    <th class="field-name">Field</th>
                                    <th class="field-enabled">Enabled</th>
                                    <th class="field-required">Required</th>
                                    <th class="field-readonly">Read Only</th>
                                </tr>
                            </thead>
                            <tbody class="eau-sortable-tbody">
                                <?php
                                foreach ($fields_config as $key => $field) :
                                    if ($field['type'] !== 'meta') continue;
                                    $order++;
                                    self::render_field_row($key, $field, $order);
                                endforeach;
                                ?>
                            </tbody>
                        </table>
                    </div>

                </div>

                <?php submit_button('Save Settings'); ?>
            </form>

            <div class="eau-settings-help">
                <h3>How to use:</h3>
                <ul>
                    <li><strong>Enabled:</strong> Check to display this field in View/Edit modals</li>
                    <li><strong>Required:</strong> User must fill this field (only works if Enabled)</li>
                    <li><strong>Read Only:</strong> Field is visible but cannot be edited</li>
                    <li><strong>Drag & Drop:</strong> Reorder fields by dragging the ☰ icon</li>
                </ul>
            </div>
        </div>
        <?php
    }

    /**
     * Renderiza uma linha de campo
     */
    private static function render_field_row($key, $field, $order) {
        $enabled = isset($field['enabled']) ? $field['enabled'] : false;
        $required = isset($field['required']) ? $field['required'] : false;
        $readonly = isset($field['readonly']) ? $field['readonly'] : false;
        $label = isset($field['label']) ? $field['label'] : $key;
        $meta_key = isset($field['meta_key']) ? $field['meta_key'] : '';

        ?>
        <tr class="eau-field-row" data-field-key="<?php echo esc_attr($key); ?>">
            <td class="drag-handle">
                <span class="dashicons dashicons-menu"></span>
            </td>
            <td class="field-name">
                <strong><?php echo esc_html($label); ?></strong>
                <?php if ($meta_key) : ?>
                    <br><code><?php echo esc_html($meta_key); ?></code>
                <?php endif; ?>
            </td>
            <td class="field-enabled">
                <input
                    type="checkbox"
                    name="<?php echo esc_attr(self::OPTION_NAME); ?>[<?php echo esc_attr($key); ?>][enabled]"
                    value="1"
                    <?php checked($enabled, true); ?>
                >
            </td>
            <td class="field-required">
                <input
                    type="checkbox"
                    name="<?php echo esc_attr(self::OPTION_NAME); ?>[<?php echo esc_attr($key); ?>][required]"
                    value="1"
                    <?php checked($required, true); ?>
                    <?php disabled($enabled, false); ?>
                >
            </td>
            <td class="field-readonly">
                <input
                    type="checkbox"
                    name="<?php echo esc_attr(self::OPTION_NAME); ?>[<?php echo esc_attr($key); ?>][readonly]"
                    value="1"
                    <?php checked($readonly, true); ?>
                    <?php disabled($enabled, false); ?>
                >
            </td>
            <input
                type="hidden"
                name="<?php echo esc_attr(self::OPTION_NAME); ?>[<?php echo esc_attr($key); ?>][order]"
                value="<?php echo esc_attr($order); ?>"
                class="field-order-input"
            >
        </tr>
        <?php
    }

    /**
     * Retorna todos os campos disponíveis
     */
    public static function get_available_fields() {
        $fields = array(
            // Core WordPress Fields
            'display_name' => array(
                'type' => 'core',
                'label' => 'Display Name',
                'field_type' => 'text',
                'enabled' => true,
                'required' => true,
                'readonly' => false,
                'order' => 1,
            ),
            'first_name' => array(
                'type' => 'core',
                'label' => 'First Name',
                'field_type' => 'text',
                'enabled' => true,
                'required' => false,
                'readonly' => false,
                'order' => 2,
            ),
            'last_name' => array(
                'type' => 'core',
                'label' => 'Last Name',
                'field_type' => 'text',
                'enabled' => true,
                'required' => false,
                'readonly' => false,
                'order' => 3,
            ),
            'user_email' => array(
                'type' => 'core',
                'label' => 'Email',
                'field_type' => 'email',
                'enabled' => true,
                'required' => true,
                'readonly' => false,
                'order' => 4,
            ),
            'user_login' => array(
                'type' => 'core',
                'label' => 'Username',
                'field_type' => 'text',
                'enabled' => true,
                'required' => false,
                'readonly' => true,
                'order' => 5,
            ),
            'role' => array(
                'type' => 'core',
                'label' => 'User Role',
                'field_type' => 'select',
                'enabled' => true,
                'required' => false,
                'readonly' => false,
                'order' => 6,
            ),
            'user_url' => array(
                'type' => 'core',
                'label' => 'Website',
                'field_type' => 'text',
                'enabled' => false,
                'required' => false,
                'readonly' => false,
                'order' => 7,
            ),
            'description' => array(
                'type' => 'core',
                'label' => 'Biographical Info',
                'field_type' => 'textarea',
                'enabled' => false,
                'required' => false,
                'readonly' => false,
                'order' => 8,
            ),
        );

        // Adiciona meta fields personalizados
        $meta_fields = self::get_user_meta_fields();
        foreach ($meta_fields as $meta_key => $meta_field) {
            $fields[$meta_key] = array(
                'type' => 'meta',
                'label' => $meta_field['label'],
                'meta_key' => $meta_key,
                'field_type' => $meta_field['field_type'],
                'enabled' => isset($meta_field['enabled']) ? $meta_field['enabled'] : false,
                'required' => isset($meta_field['required']) ? $meta_field['required'] : false,
                'readonly' => isset($meta_field['readonly']) ? $meta_field['readonly'] : false,
                'order' => isset($meta_field['order']) ? $meta_field['order'] : (100 + count($fields)),
            );
        }

        return $fields;
    }

    /**
     * Busca meta fields personalizados (JetEngine, etc)
     */
    private static function get_user_meta_fields() {
        $meta_fields = array();

        // Meta fields conhecidos do Eau System
        $known_meta = array(
            'mem_status' => array(
                'label' => 'Member Status',
                'field_type' => 'select',
            ),
            'mem_membercompanyname' => array(
                'label' => 'Member Company Name',
                'field_type' => 'select',
            ),
            'mem_phone' => array(
                'label' => 'Phone',
                'field_type' => 'tel',
            ),
            'mem_address' => array(
                'label' => 'Address',
                'field_type' => 'textarea',
            ),
            'mem_city' => array(
                'label' => 'City',
                'field_type' => 'text',
            ),
            'mem_state' => array(
                'label' => 'State',
                'field_type' => 'text',
            ),
            'mem_postcode' => array(
                'label' => 'Postcode',
                'field_type' => 'text',
            ),
            'mem_country' => array(
                'label' => 'Country',
                'field_type' => 'text',
            ),
            'mem_tags' => array(
                'label' => 'Tags',
                'field_type' => 'tags',
                'enabled' => true,
                'required' => false,
                'readonly' => false,
                'order' => 99,
            ),
        );

        foreach ($known_meta as $key => $field) {
            $meta_fields[$key] = $field;
        }

        // TODO: Buscar automaticamente do JetEngine se estiver instalado
        // if (class_exists('Jet_Engine')) {
        //     $jet_fields = self::get_jetengine_user_fields();
        //     $meta_fields = array_merge($meta_fields, $jet_fields);
        // }

        return $meta_fields;
    }

    /**
     * Campos que devem estar sempre habilitados por padrão
     * (mesmo se não existem nas configurações salvas)
     */
    const FORCE_ENABLED_FIELDS = array('mem_tags');

    /**
     * Retorna as configurações salvas
     */
    public static function get_editable_fields() {
        $saved_settings = get_option(self::OPTION_NAME, array());
        $available_fields = self::get_available_fields();

        $editable_fields = array();

        foreach ($available_fields as $key => $field) {
            // Se não tem configuração salva para este campo, usa o padrão
            if (!isset($saved_settings[$key])) {
                $config = $field;
            } else {
                // Merge com configurações salvas
                $config = array_merge($field, $saved_settings[$key]);
            }

            // Força enabled para campos que devem estar sempre habilitados
            if (in_array($key, self::FORCE_ENABLED_FIELDS)) {
                $config['enabled'] = true;
            }

            // Só retorna se estiver enabled
            if (isset($config['enabled']) && $config['enabled']) {
                $editable_fields[$key] = $config;
            }
        }

        // Ordena por order
        uasort($editable_fields, function($a, $b) {
            $order_a = isset($a['order']) ? $a['order'] : 999;
            $order_b = isset($b['order']) ? $b['order'] : 999;
            return $order_a - $order_b;
        });

        return $editable_fields;
    }
}
