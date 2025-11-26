# Plano: Página de Settings (Shortcode)

## Objetivo

Criar um shortcode `[eau_settings]` que renderiza uma página de configurações do sistema no frontend. A primeira configuração será definir se as atividades CPD terão **aprovação automática** ou **aprovação manual** (pendente de revisão por admin).

---

## Análise do Contexto

### Diferença do `Eau_Members_Settings`
- O `Eau_Members_Settings` existente é uma página **admin** (wp-admin)
- O novo `Eau_Settings` será um **shortcode frontend** para usuários com permissão (superAdmin/Admin)
- Seguirá os padrões visuais do Design System (não o estilo wp-admin)

### Integração com My CPDs
- Quando aprovação automática: `act_verified = '1'` ao criar atividade
- Quando aprovação manual: `act_verified = '0'` ao criar atividade (status "Pending")

---

## Estrutura de Arquivos

```
/eau-system/
├── /includes/
│   └── class-eau-settings.php          # Classe principal do shortcode
├── /ajax/
│   └── class-eau-settings-ajax.php     # Handlers AJAX
├── /assets/
│   ├── /css/
│   │   └── eau-settings.css            # Estilos específicos
│   └── /js/
│       └── eau-settings.js             # JavaScript controller
```

---

## Detalhamento Técnico

### 1. PHP - Classe Principal (`class-eau-settings.php`)

```php
namespace EauSystem;

class Eau_Settings {

    // Option names
    const OPTION_ACTIVITY_APPROVAL = 'eau_activity_approval_mode';

    // Valores possíveis
    const APPROVAL_AUTO = 'auto';      // Aprovação automática
    const APPROVAL_MANUAL = 'manual';  // Aprovação manual (pendente)

    // Registra shortcode
    public static function register_shortcode();

    // Renderiza página
    public static function render_settings($atts);

    // Verifica permissão (superAdmin ou Admin)
    private static function can_access_settings();

    // Carrega assets
    private static function enqueue_assets();

    // Renderiza seção de Activities
    private static function render_activities_section();

    // Helpers para obter configurações
    public static function get_activity_approval_mode();
    public static function is_auto_approval();
}
```

**Estrutura Visual da Página:**

```
┌──────────────────────────────────────────────────────────────┐
│ [Page Header]                                                │
│ ⚙️ System Settings                                           │
│ Configure system-wide settings                               │
├──────────────────────────────────────────────────────────────┤
│                                                              │
│ ┌─────────────────────────────────────────────────────────┐  │
│ │ [Settings Section: CPD Activities]                      │  │
│ │                                                         │  │
│ │ Activity Approval Mode                                  │  │
│ │ ┌─────────────────────────────────────────────────────┐ │  │
│ │ │ ○ Automatic Approval                                │ │  │
│ │ │   Activities are verified immediately upon creation │ │  │
│ │ │                                                     │ │  │
│ │ │ ● Manual Approval                                   │ │  │
│ │ │   Activities require admin review before verified   │ │  │
│ │ └─────────────────────────────────────────────────────┘ │  │
│ │                                                         │  │
│ │ [Save Settings]                                         │  │
│ └─────────────────────────────────────────────────────────┘  │
│                                                              │
│ ┌─────────────────────────────────────────────────────────┐  │
│ │ [Settings Section: Future Settings]                     │  │
│ │ (Placeholder para futuras configurações)                │  │
│ └─────────────────────────────────────────────────────────┘  │
│                                                              │
└──────────────────────────────────────────────────────────────┘
```

### 2. AJAX Handler (`class-eau-settings-ajax.php`)

```php
namespace EauSystem\Ajax;

class Eau_Settings_Ajax {

    public static function register_handlers() {
        add_action('wp_ajax_eau_get_settings', array(__CLASS__, 'get_settings'));
        add_action('wp_ajax_eau_save_settings', array(__CLASS__, 'save_settings'));
    }

    // Retorna configurações atuais
    public static function get_settings();

    // Salva configurações
    public static function save_settings();
}
```

### 3. JavaScript (`eau-settings.js`)

```javascript
const EauSettingsController = {
    init: function() {
        this.bindEvents();
        this.loadSettings();
    },

    bindEvents: function() {
        // Radio button change
        // Save button click
    },

    loadSettings: function() {
        // Carrega configurações via AJAX
    },

    saveSettings: function() {
        // Salva via AJAX
        // Mostra toast de sucesso/erro
    }
};
```

### 4. CSS (`eau-settings.css`)

- Container principal
- Settings sections (cards)
- Radio button groups customizados
- Descrições das opções
- Responsivo

---

## Integração com My CPDs

### Modificação em `class-eau-my-cpds-ajax.php`

Na função `create_my_activity()`, alterar:

```php
// Antes (fixo):
update_post_meta($post_id, 'act_verified', '0');

// Depois (dinâmico):
$approval_mode = Eau_Settings::get_activity_approval_mode();
$is_verified = ($approval_mode === Eau_Settings::APPROVAL_AUTO) ? '1' : '0';
update_post_meta($post_id, 'act_verified', $is_verified);
```

---

## Controle de Acesso

Apenas usuários com `mem_type` = `superAdmin` ou `Admin` podem acessar a página de settings.

```php
private static function can_access_settings() {
    if (!is_user_logged_in()) {
        return false;
    }

    $user_id = get_current_user_id();
    $mem_type = get_user_meta($user_id, 'mem_type', true);

    return in_array($mem_type, array('superAdmin', 'Admin'));
}
```

---

## Tarefas de Implementação

### Fase 1: Estrutura Base
1. [ ] Criar `class-eau-settings.php` com shortcode básico
2. [ ] Criar `class-eau-settings-ajax.php` com handlers
3. [ ] Registrar shortcode e AJAX handlers em `class-eau-system.php`
4. [ ] Criar `eau-settings.css` com estilos
5. [ ] Criar `eau-settings.js` com controller

### Fase 2: Funcionalidade
6. [ ] Implementar renderização da página com seção de Activities
7. [ ] Implementar radio buttons para modo de aprovação
8. [ ] Implementar AJAX get/save settings
9. [ ] Adicionar feedback com EauNotifications

### Fase 3: Integração
10. [ ] Modificar `create_my_activity()` para usar configuração
11. [ ] Testar criação de atividade com cada modo
12. [ ] Atualizar versão do plugin

### Fase 4: Finalização
13. [ ] Testar controle de acesso
14. [ ] Testar responsividade
15. [ ] Documentar no DESIGN-SYSTEM.md (se necessário)

---

## Considerações Futuras

A estrutura da página de Settings foi pensada para ser **expansível**. Futuras configurações podem incluir:

- **Email Settings**: Configurar notificações por email
- **Points Settings**: Alterar meta anual de pontos CPD
- **Import/Export**: Configurações de importação
- **Permissions**: Configurações de permissões por role
- **UI Preferences**: Configurações visuais

Cada nova configuração será uma **nova seção** (card) na página.

---

## Estimativa

- Fase 1: Estrutura base
- Fase 2: Funcionalidade completa
- Fase 3: Integração com My CPDs
- Fase 4: Testes e ajustes finais

---

**Versão do Plano**: 1.0
**Data**: 2025-01-25
