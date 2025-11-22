# Eau System - Padrões de Design

## 📐 Sistema de Design

Este documento define os padrões visuais e de código para manter consistência em todas as páginas do Eau System.

---

## 🎨 Tipografia

### Headers de Página

Use as classes `eau-welcome-title` e `eau-welcome-description` para manter consistência com o Dashboard.

```html
<div class="eau-welcome-section">
    <h1 class="eau-welcome-title">Título da Página</h1>
    <p class="eau-welcome-description">Descrição breve da funcionalidade</p>
</div>
```

#### Especificações Técnicas:

**Título (`.eau-welcome-title`):**
- **Mobile** (< 768px): `font-size: 1.5rem` (24px)
- **Tablet** (≥ 768px): `font-size: 1.75rem` (28px)
- **Desktop** (≥ 1024px): `font-size: 2rem` (32px)
- **Peso**: `font-weight: 600` (Semi-bold)
- **Cor**: `#111827` (Gray 900)
- **Espaçamento**: `margin: 0 0 0.5rem 0`
- **Line Height**: `1.2`

**Descrição (`.eau-welcome-description`):**
- **Mobile** (< 768px): `font-size: 0.875rem` (14px)
- **Tablet** (≥ 768px): `font-size: 0.9375rem` (15px)
- **Desktop** (≥ 1024px): `font-size: 1rem` (16px)
- **Peso**: `font-weight: 400` (Regular)
- **Cor**: `#6b7280` (Gray 500)
- **Espaçamento**: `margin: 0`
- **Line Height**: `1.5`

---

## 🎯 Componentes Base

### Botões

#### Botão Primário
```html
<button class="eau-btn eau-btn-primary">
    <i data-lucide="plus"></i>
    <span>Label do Botão</span>
</button>
```

**Cores:**
- Background: `#2563eb` (Blue 600)
- Hover: `#1d4ed8` (Blue 700)
- Texto: `#ffffff`

#### Botão Secundário
```html
<button class="eau-btn eau-btn-secondary">
    <i data-lucide="x"></i>
    Cancel
</button>
```

**Cores:**
- Background: `#ffffff`
- Border: `#d1d5db`
- Hover Background: `#f9fafb`
- Texto: `#374151`

#### Botão Danger
```html
<button class="eau-btn eau-btn-danger">
    <i data-lucide="trash-2"></i>
    Delete
</button>
```

**Cores:**
- Background: `#dc2626` (Red 600)
- Hover: `#b91c1c` (Red 700)
- Texto: `#ffffff`

---

## 🏷️ Tags / Badges

### Tags de Status

Use para indicar diferentes tipos de matches ou status:

```html
<span class="eau-match-tag eau-tag-{type}">
    <i data-lucide="check"></i>
    Label
</span>
```

#### Cores por Tipo:

| Tipo | Classe | Background | Texto | Uso |
|------|--------|-----------|-------|-----|
| **Name** | `.eau-tag-name` | `#eff6ff` | `#2563eb` | Nomes similares |
| **Email** | `.eau-tag-email` | `#fef3c7` | `#d97706` | Emails similares |
| **Phone** | `.eau-tag-phone` | `#d1fae5` | `#059669` | Telefones similares |
| **Company** | `.eau-tag-company` | `#fae8ff` | `#a21caf` | Mesma empresa/instituição |
| **Address** | `.eau-tag-address` | `#e0e7ff` | `#4f46e5` | Endereços similares |
| **City** | `.eau-tag-city` | `#dbeafe` | `#1d4ed8` | Mesma cidade |
| **Postcode** | `.eau-tag-postcode` | `#fce7f3` | `#be123c` | Mesmo CEP |
| **Last Name** | `.eau-tag-lastname` | `#dcfce7` | `#16a34a` | Sobrenomes idênticos |
| **Initial** | `.eau-tag-initial` | `#e0f2fe` | `#0284c7` | Iniciais similares |
| **Other** | `.eau-tag-other` | `#f3f4f6` | `#6b7280` | Outros matches |

---

## 📦 Cards

### Card Base
```html
<div class="eau-card">
    <div class="eau-card-header">
        <h3>Título do Card</h3>
    </div>
    <div class="eau-card-body">
        Conteúdo
    </div>
</div>
```

**Especificações:**
- Background: `#ffffff`
- Border: `1px solid #e5e7eb`
- Border Radius: `12px`
- Padding: `1.5rem`
- Box Shadow: `0 1px 3px rgba(0, 0, 0, 0.1)`
- Hover Shadow: `0 4px 12px rgba(0, 0, 0, 0.1)`

---

## 🎨 Paleta de Cores

### Cores Principais

| Nome | Hex | Uso |
|------|-----|-----|
| **Primary** | `#2563eb` | Botões primários, links |
| **Success** | `#16a34a` | Ações positivas, confirmações |
| **Warning** | `#d97706` | Alertas, avisos |
| **Danger** | `#dc2626` | Erros, exclusões |
| **Info** | `#0284c7` | Informações, dicas |

### Cores de Texto

| Nome | Hex | Uso |
|------|-----|-----|
| **Text Primary** | `#111827` | Títulos, textos principais |
| **Text Secondary** | `#6b7280` | Descrições, textos secundários |
| **Text Muted** | `#9ca3af` | Textos auxiliares |

### Cores de Background

| Nome | Hex | Uso |
|------|-----|-----|
| **BG Primary** | `#ffffff` | Cards, modais |
| **BG Secondary** | `#f9fafb` | Seções alternadas |
| **BG Muted** | `#f3f4f6` | Backgrounds desabilitados |

### Borders

| Nome | Hex | Uso |
|------|-----|-----|
| **Border Default** | `#e5e7eb` | Bordas padrão |
| **Border Light** | `#f3f4f6` | Bordas sutis |
| **Border Strong** | `#d1d5db` | Bordas com destaque |

---

## 🔤 Ícones

### Lucide Icons

Usamos a biblioteca [Lucide Icons](https://lucide.dev/).

```html
<i data-lucide="icon-name"></i>
```

#### Ícones Comuns:

| Contexto | Ícone | Nome |
|----------|-------|------|
| **Usuários** | 👥 | `users` |
| **Usuário único** | 👤 | `user` |
| **Email** | ✉️ | `mail` |
| **Telefone** | 📞 | `phone` |
| **Endereço** | 📍 | `map-pin` |
| **Empresa** | 🏢 | `building-2` |
| **Pesquisar** | 🔍 | `search` |
| **Adicionar** | ➕ | `plus` |
| **Editar** | ✏️ | `edit-2` |
| **Deletar** | 🗑️ | `trash-2` |
| **Confirmar** | ✓ | `check` |
| **Fechar** | ✕ | `x` |
| **Loading** | ⟳ | `loader-2` |
| **Sucesso** | ✓ | `check-circle` |
| **Erro** | ⚠ | `alert-circle` |
| **Info** | ℹ | `info` |

**Animação de Loading:**
```css
.eau-spin {
    animation: spin 1s linear infinite !important;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
```

---

## 📱 Responsividade

### Breakpoints

```css
/* Mobile First */
/* Base: < 640px */

@media (min-width: 640px) {
    /* Tablets pequenos */
}

@media (min-width: 768px) {
    /* Tablets */
}

@media (min-width: 1024px) {
    /* Desktops */
}

@media (min-width: 1280px) {
    /* Desktops grandes */
}

@media (min-width: 1536px) {
    /* Ultra-wide */
}
```

### Container Widths

```css
.eau-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 2rem 1rem; /* Mobile */
}

@media (min-width: 640px) {
    .eau-container {
        padding: 2rem 1.5rem;
    }
}

@media (min-width: 1024px) {
    .eau-container {
        padding: 2.5rem 2rem;
    }
}
```

---

## 🎭 Estados Interativos

### Hover
```css
transition: all 0.2s ease;
```

### Focus
```css
outline: 2px solid #2563eb;
outline-offset: 2px;
```

### Disabled
```css
opacity: 0.5;
cursor: not-allowed;
```

---

## 📋 Formulários

### Input Text
```html
<input type="text" class="eau-form-input" placeholder="Enter text">
```

**Especificações:**
- Border: `1px solid #d1d5db`
- Border Radius: `8px`
- Padding: `0.75rem 1rem`
- Font Size: `0.9375rem`
- Focus Border: `#2563eb`

### Select
```html
<select class="eau-form-select">
    <option>Option 1</option>
    <option>Option 2</option>
</select>
```

---

## 🔔 Notificações

### Tipos

```javascript
EauNotifications.success('Title', 'Message');
EauNotifications.error('Title', 'Message');
EauNotifications.info('Title', 'Message');
EauNotifications.warning('Title', 'Message');
```

**Posição:** Top-right
**Duração:** 5 segundos (success/info), 7 segundos (error/warning)

---

## 📝 Nomenclatura

### Classes CSS

**Padrão:** `eau-{componente}-{elemento}-{modificador}`

**Exemplos:**
- `.eau-btn-primary`
- `.eau-card-header`
- `.eau-user-detail-label`

### JavaScript

**Padrão:** camelCase

**Exemplos:**
- `loadDuplicates()`
- `currentPage`
- `totalResults`

### PHP

**Padrão:** snake_case para funções, PascalCase para classes

**Exemplos:**
- `class Eau_Duplicate_Manager`
- `public static function get_full_name()`

---

## 🔒 Mensagens de Acesso Negado

### Componente Access Denied

Use o componente `Eau_Access_Denied` para exibir mensagens profissionais quando usuários não autorizados tentam acessar páginas protegidas.

#### Uso Básico

```php
use EauSystem\Components\Eau_Access_Denied;

// Para usuários não logados
if (!is_user_logged_in()) {
    return Eau_Access_Denied::not_logged_in();
}

// Para usuários sem permissão
if (!current_user_can('manage_options')) {
    return Eau_Access_Denied::no_permission();
}
```

#### Uso Customizado

```php
return Eau_Access_Denied::render(
    'Custom Title',
    'Custom message explaining why access is denied.',
    'not_logged_in' // ou 'no_permission'
);
```

#### Especificações Visuais

**Container:**
- Max-width: `600px`
- Margin: `4rem auto` (desktop), `2rem auto` (mobile)
- Text-align: `center`

**Card:**
- Background: `#ffffff`
- Border: `1px solid #e5e7eb`
- Border-radius: `16px`
- Padding: `3rem 2rem` (desktop), `2rem 1.5rem` (mobile)
- Box-shadow: `0 4px 12px rgba(0, 0, 0, 0.1)`

**Ícone:**
- Tamanho: `64px x 64px`
- Cor: `#dc2626` (Red 600)
- Ícones:
  - `log-in` - Para usuários não logados
  - `shield-alert` - Para usuários sem permissão

**Título:**
- Font-size: `1.5rem` (desktop), `1.25rem` (mobile)
- Font-weight: `600`
- Color: `#111827`
- Margin-bottom: `1rem`

**Mensagem:**
- Font-size: `1rem` (desktop), `0.9375rem` (mobile)
- Color: `#6b7280`
- Line-height: `1.6`
- Margin-bottom: `2rem`

**Botão:**
- Background: `#2563eb`
- Hover: `#1d4ed8`
- Color: `#ffffff`
- Padding: `0.875rem 2rem`
- Border-radius: `8px`
- Sempre redireciona para: `/login/`

#### Cenários de Uso

**1. Não Logado:**
```php
// Título: "Authentication Required"
// Mensagem: "You need to be logged in to access this page."
// Ícone: log-in
return Eau_Access_Denied::not_logged_in();
```

**2. Sem Permissão:**
```php
// Título: "Access Denied"
// Mensagem: "You do not have sufficient permissions..."
// Ícone: shield-alert
return Eau_Access_Denied::no_permission();
```

**3. Customizado com Role Específica:**
```php
return Eau_Access_Denied::no_permission('Institution Administrators');
// Mensagem: "...Only Institution Administrators can access this feature."
```

#### Páginas que Usam este Componente

- **Duplicate Manager** (`/dashboard/merge-members/`)
- **Members Management** (`/members/`)
- **Dashboard** (`/dashboard/`)

#### Segurança

O componente deve sempre ser usado em conjunto com verificações de permissão:

```php
// ✅ CORRETO
if (!is_user_logged_in()) {
    return Eau_Access_Denied::not_logged_in();
}

if (!current_user_can('manage_options')) {
    return Eau_Access_Denied::no_permission();
}

// ❌ INCORRETO - Nunca confie apenas na UI
// Sempre valide permissões no backend (AJAX endpoints)
```

#### Arquivo do Componente

**Localização:** `includes/components/class-eau-access-denied.php`

---

## 🚀 Boas Práticas

1. **Sempre use !important nos estilos CSS** para garantir que os estilos do plugin sobrescrevam os do tema
2. **Use classes utilitárias** ao invés de estilos inline
3. **Mantenha consistência** entre páginas similares
4. **Mobile First** - sempre desenvolva primeiro para mobile
5. **Acessibilidade** - sempre inclua atributos ARIA quando necessário
6. **Performance** - evite animações pesadas e múltiplos reflows

---

## 📚 Referências

- **Lucide Icons:** https://lucide.dev/
- **Tailwind Colors:** https://tailwindcss.com/docs/customizing-colors
- **CSS Grid:** https://css-tricks.com/snippets/css/complete-guide-grid/
- **Flexbox:** https://css-tricks.com/snippets/css/a-guide-to-flexbox/

---

**Versão:** 1.0
**Última Atualização:** 2025-01-21
**Autor:** Claude Code / Platty
