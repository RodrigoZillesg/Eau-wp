# JetEngine CPT Sync - Documentação

Este documento descreve como criar um Custom Post Type (CPT) sincronizado com JetEngine no Eau System.

## Visão Geral

O padrão permite que CPTs criados via código PHP sejam automaticamente registrados no JetEngine, aparecendo no painel admin do JetEngine com todos os meta fields editáveis.

### Status do CPT

- **`publish`**: JetEngine gerencia completamente o CPT (recomendado)
- **`built-in`**: CPT é registrado via código, JetEngine apenas referencia

## Estrutura de Arquivos

```
includes/
└── {module}/
    ├── class-{module}.php              # Bootstrap principal
    ├── class-{module}-cpt.php          # Registro do CPT
    ├── class-{module}-meta.php         # Meta fields e sync JetEngine
    └── config/
        ├── constants.php               # Constantes (POST_TYPE, META_PREFIX)
        ├── meta-fields.php             # Definição dos meta fields
        └── options.php                 # Opções de selects/radios
```

## Passo a Passo

### 1. Criar Constantes (`config/constants.php`)

```php
<?php
namespace EauSystem\{Module}\Config;

if (!defined('WPINC')) {
    die;
}

/** @var string Post Type slug */
const POST_TYPE = 'eau_{slug}';

/** @var string Prefixo dos meta fields */
const META_PREFIX = '{prefix}_';
```

### 2. Criar Meta Fields (`config/meta-fields.php`)

```php
<?php
namespace EauSystem\{Module}\Config;

if (!defined('WPINC')) {
    die;
}

/**
 * Retorna definição dos meta fields
 *
 * @return array field_name => type
 */
function get_meta_fields() {
    return array(
        'field_name'      => 'string',
        'numeric_field'   => 'integer',
        'date_field'      => 'string',
        'boolean_field'   => 'boolean',
    );
}

/**
 * Retorna callback de sanitização por tipo
 */
function get_sanitize_callback($type) {
    switch ($type) {
        case 'integer':
            return 'absint';
        case 'number':
            return 'floatval';
        case 'boolean':
            return 'rest_sanitize_boolean';
        default:
            return 'sanitize_text_field';
    }
}
```

### 3. Criar Classe Meta (`class-{module}-meta.php`)

```php
<?php
namespace EauSystem\{Module};

use EauSystem\{Module}\Config;

class {Module}_Meta {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', array($this, 'register_meta'), 10);
        add_action('init', array($this, 'register_to_jet_engine'), 5);
    }

    /**
     * Registra meta fields para REST API
     */
    public function register_meta() {
        $fields = Config\get_meta_fields();

        foreach ($fields as $field => $type) {
            register_post_meta(Config\POST_TYPE, Config\META_PREFIX . $field, array(
                'type'              => $type,
                'single'            => true,
                'show_in_rest'      => true,
                'sanitize_callback' => Config\get_sanitize_callback($type),
                'auth_callback'     => function() {
                    return current_user_can('edit_posts');
                },
            ));
        }
    }

    /**
     * Registra/atualiza CPT no JetEngine
     */
    public function register_to_jet_engine() {
        global $wpdb;
        $table = $wpdb->prefix . 'jet_post_types';

        // Verifica se tabela existe
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            return;
        }

        // Controle de versão para updates
        $version_key = 'eau_{module}_jet_version';
        $current_version = \EauSystem\{Module}\{Module}::VERSION;
        $saved_version = get_option($version_key);

        if ($this->exists_in_jet_engine()) {
            if ($saved_version !== $current_version) {
                $this->update_in_jet_engine();
                update_option($version_key, $current_version);
            }
            return;
        }

        $this->save_to_jet_engine();
        update_option($version_key, $current_version);
    }

    private function exists_in_jet_engine() {
        global $wpdb;
        $table = $wpdb->prefix . 'jet_post_types';

        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE slug = %s",
            Config\POST_TYPE
        ));
    }

    private function save_to_jet_engine() {
        global $wpdb;
        $table = $wpdb->prefix . 'jet_post_types';

        $data = array(
            'slug'        => Config\POST_TYPE,
            'status'      => 'publish', // JetEngine gerencia completamente
            'labels'      => maybe_serialize($this->get_labels()),
            'args'        => maybe_serialize($this->get_args()),
            'meta_fields' => maybe_serialize($this->get_jet_meta_fields()),
        );

        return $wpdb->insert($table, $data, array('%s', '%s', '%s', '%s', '%s'));
    }

    private function update_in_jet_engine() {
        global $wpdb;
        $table = $wpdb->prefix . 'jet_post_types';

        $data = array(
            'labels'      => maybe_serialize($this->get_labels()),
            'args'        => maybe_serialize($this->get_args()),
            'meta_fields' => maybe_serialize($this->get_jet_meta_fields()),
        );

        return $wpdb->update(
            $table,
            $data,
            array('slug' => Config\POST_TYPE),
            array('%s', '%s', '%s'),
            array('%s')
        );
    }

    private function get_labels() {
        return array(
            'name'          => '{Labels Plural}',
            'singular_name' => '{Label Singular}',
            'menu_name'     => '{Menu Name}',
        );
    }

    private function get_args() {
        return array(
            'public'       => true,
            'has_archive'  => true,
            'show_in_rest' => true,
            'menu_icon'    => 'dashicons-{icon}',
            'supports'     => array('title'),
            'rewrite'      => true,
            'rewrite_slug' => '{slug}',
        );
    }

    private function get_jet_meta_fields() {
        $p = Config\META_PREFIX;
        $base_id = 90000; // ID base único para este CPT

        return array(
            array(
                'title'       => 'Field Label',
                'name'        => $p . 'field_name',
                'object_type' => 'field',
                'type'        => 'text',
                'width'       => '100%',
                'id'          => $base_id++,
            ),
            // ... mais campos
        );
    }
}
```

### 4. Criar Classe CPT (`class-{module}-cpt.php`)

```php
<?php
namespace EauSystem\{Module};

use EauSystem\{Module}\Config;

class {Module}_CPT {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', array($this, 'register_post_type'), 10);
    }

    public function register_post_type() {
        // Não registra se JetEngine gerencia (status='publish')
        if ($this->is_managed_by_jet_engine()) {
            return;
        }

        register_post_type(Config\POST_TYPE, array(
            'labels'             => $this->get_labels(),
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => array('slug' => '{slug}', 'with_front' => false),
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => 25,
            'menu_icon'          => 'dashicons-{icon}',
            'supports'           => array('title'),
            'show_in_rest'       => true,
        ));
    }

    private function is_managed_by_jet_engine() {
        global $wpdb;
        $table = $wpdb->prefix . 'jet_post_types';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            return false;
        }

        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE slug = %s AND status = 'publish'",
            Config\POST_TYPE
        ));
    }

    private function get_labels() {
        return array(
            'name'               => __('{Plural}', 'eau-system'),
            'singular_name'      => __('{Singular}', 'eau-system'),
            'add_new'            => __('Add New', 'eau-system'),
            'add_new_item'       => __('Add New {Singular}', 'eau-system'),
            'edit_item'          => __('Edit {Singular}', 'eau-system'),
            'new_item'           => __('New {Singular}', 'eau-system'),
            'view_item'          => __('View {Singular}', 'eau-system'),
            'all_items'          => __('All {Plural}', 'eau-system'),
            'search_items'       => __('Search {Plural}', 'eau-system'),
            'not_found'          => __('{Plural} not found.', 'eau-system'),
            'not_found_in_trash' => __('{Plural} not found in Trash.', 'eau-system'),
            'menu_name'          => __('{Menu}', 'eau-system'),
        );
    }
}
```

## Tipos de Campos JetEngine

| Tipo | Descrição | Opções extras |
|------|-----------|---------------|
| `text` | Texto simples | `placeholder` |
| `textarea` | Área de texto | `rows` |
| `wysiwyg` | Editor visual | - |
| `number` | Número | `min_value`, `max_value`, `step_value` |
| `select` | Dropdown | `options` (array) |
| `radio` | Radio buttons | `options` (array) |
| `checkbox` | Checkbox | - |
| `switcher` | Toggle on/off | `default_value` |
| `date` | Data | - |
| `datetime-local` | Data e hora | - |
| `time` | Hora | - |
| `media` | Upload mídia | `value_format` ('id' ou 'url') |
| `posts` | Relação com posts | `post_type`, `is_multiple` |

### Exemplo de campo `posts` (Relacionamento)

```php
array(
    'title'       => 'Related Event',
    'name'        => $p . 'event_id',
    'object_type' => 'field',
    'type'        => 'posts',
    'post_type'   => 'eau_event',
    'is_multiple' => false,
    'id'          => $base_id++,
),
```

### Formato de Options (select/radio)

```php
// No config/options.php
function get_status_options() {
    return array(
        'confirmed' => 'Confirmed',
        'pending'   => 'Pending',
        'cancelled' => 'Cancelled',
    );
}

// Converter para formato JetEngine
function to_jet_format($options) {
    $result = array();
    foreach ($options as $key => $label) {
        $result[] = array(
            'key'   => $key,
            'value' => $label,
        );
    }
    return $result;
}

// Uso
$status_options = Config\to_jet_format(Config\get_status_options());
```

## Endpoints de Debug

Adicione no `__construct` da classe Meta:

```php
add_action('admin_init', array($this, 'handle_debug_request'));
```

E o método:

```php
public function handle_debug_request() {
    if (!current_user_can('manage_options')) {
        return;
    }

    // Debug: /wp-admin/?eau_{module}_debug=1
    if (isset($_GET['eau_{module}_debug'])) {
        $this->show_debug();
    }

    // Force sync: /wp-admin/?eau_{module}_sync=1
    if (isset($_GET['eau_{module}_sync'])) {
        $this->force_sync();
    }
}
```

## Checklist de Implementação

- [ ] Criar `config/constants.php` com POST_TYPE e META_PREFIX
- [ ] Criar `config/meta-fields.php` com get_meta_fields()
- [ ] Criar `config/options.php` com opções de selects (se necessário)
- [ ] Criar `class-{module}-meta.php` com integração JetEngine
- [ ] Criar `class-{module}-cpt.php` como fallback
- [ ] Criar `class-{module}.php` bootstrap
- [ ] Testar sync acessando `/wp-admin/?eau_{module}_debug=1`
- [ ] Verificar CPT no JetEngine admin (Post Types)
- [ ] Verificar meta fields no editor do post

## Notas Importantes

1. **IDs únicos**: Use `$base_id` incrementando para cada campo, começando em valor único (90000, 91000, etc.)

2. **Versão**: Sempre atualize VERSION na classe principal para triggerar update no JetEngine

3. **Status 'publish'**: Use este status para JetEngine gerenciar completamente o CPT

4. **Fallback**: A classe CPT só registra se JetEngine não estiver gerenciando (status != 'publish')

5. **Tabela**: JetEngine usa `{prefix}_jet_post_types` para armazenar CPTs
