# Eau System - Padrões de Design

**Versão**: 1.1.0
**Última atualização**: 22/11/2024
**Changelog**: Adicionada seção completa sobre Skeleton Loading com troubleshooting

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

## ⏳ Skeleton Loading

### ⚠️ PADRÃO OBRIGATÓRIO

**NUNCA use spinners ou texto "Loading..."**. Use SEMPRE skeleton loaders para indicar carregamento de dados.

### Como Usar

#### PHP - Renderizar Skeleton

```php
// Use o componente Eau_Skeleton
use EauSystem\Components\Eau_Skeleton;

// Skeleton para tabela (padrão: 30 linhas)
echo Eau_Skeleton::table();

// Skeleton para stats cards (padrão: 4 cards)
echo Eau_Skeleton::stats_cards();

// Skeleton para texto simples (padrão: 3 linhas)
echo Eau_Skeleton::text();
```

#### JavaScript - Controlar Visibilidade

```javascript
// Mostrar skeleton durante AJAX
showLoading: function() {
    $('#table-id-loading').show();
},

// Esconder skeleton após carregar
hideLoading: function() {
    $('#table-id-loading').hide();
}
```

### Estrutura HTML Gerada

O componente `Eau_Skeleton::table()` gera:

```html
<div class="eau-skeleton-table">
    <div class="eau-skeleton-table-row">
        <div class="eau-skeleton-table-cell eau-skeleton-table-cell-checkbox">
            <div class="eau-skeleton eau-skeleton-checkbox">
                <div class="eau-skeleton-shimmer"></div>
            </div>
        </div>
        <div class="eau-skeleton-table-cell">
            <div class="eau-skeleton eau-skeleton-text">
                <div class="eau-skeleton-shimmer"></div>
            </div>
        </div>
        <!-- Mais células... -->
    </div>
    <!-- Mais linhas... -->
</div>
```

### ⚠️ IMPORTANTE: Estrutura do HTML

**CADA elemento `.eau-skeleton` DEVE conter uma `<div class="eau-skeleton-shimmer"></div>`**

```html
<!-- ✅ CORRETO -->
<div class="eau-skeleton eau-skeleton-text">
    <div class="eau-skeleton-shimmer"></div>
</div>

<!-- ❌ ERRADO - Faltando shimmer -->
<div class="eau-skeleton eau-skeleton-text"></div>
```

**Por quê?** A animação é aplicada no `.eau-skeleton-shimmer`, não no elemento pai. Sem essa div interna, a animação NÃO funcionará.

### Integração com Data Table

#### Overlay de Loading

O componente `Eau_Data_Table` já inclui um overlay de skeleton:

```html
<div class="eau-data-table-wrapper">
    <!-- Tabela normal -->
    <table class="eau-table">...</table>

    <!-- Overlay de loading (inicialmente oculto) -->
    <div class="eau-table-loading-overlay" id="table-id-loading" style="display: none;">
        <?php echo Eau_Skeleton::table(30); ?>
    </div>
</div>
```

#### CSS Necessário

O overlay DEVE ter estas propriedades para cobrir 100% da tabela:

```css
.eau-table-loading-overlay {
    position: absolute !important;
    top: 0 !important;
    left: 0 !important;
    right: 0 !important;
    bottom: 0 !important;
    min-height: 100% !important;
    background: rgba(255, 255, 255, 0.95) !important;
    z-index: 10 !important;
    display: flex !important;              /* ← IMPORTANTE */
    flex-direction: column !important;     /* ← IMPORTANTE */
}

.eau-skeleton-table {
    flex: 1 !important;                    /* ← IMPORTANTE - expande */
    display: flex !important;
    flex-direction: column !important;
}
```

### Especificações CSS

#### Animação Shimmer

```css
.eau-skeleton {
    background-color: #e5e7eb;
    position: relative;
    overflow: hidden;
}

.eau-skeleton-shimmer {
    position: absolute;
    top: 0;
    left: -150%;
    width: 150%;
    height: 100%;
    background: linear-gradient(
        90deg,
        transparent 0%,
        rgba(255, 255, 255, 0.8) 50%,
        transparent 100%
    );
    animation: eauSkeletonShimmer 1.5s ease-in-out infinite;
}

@keyframes eauSkeletonShimmer {
    0% { transform: translateX(0); }
    100% { transform: translateX(300%); }
}
```

#### Especificidade para WordPress

**CRÍTICO:** WordPress pode sobrescrever animações CSS. Use MÁXIMA especificidade:

```css
/* Múltiplos seletores para alta especificidade */
.eau-data-table-wrapper .eau-skeleton,
.eau-skeleton-table .eau-skeleton,
div.eau-skeleton {
    /* estilos */
}

.eau-data-table-wrapper .eau-skeleton-shimmer,
.eau-skeleton-table .eau-skeleton-shimmer,
div.eau-skeleton > .eau-skeleton-shimmer {
    /* estilos */
}
```

### Troubleshooting

#### ❌ Problema: Animação não aparece

**Causa 1:** Faltando `<div class="eau-skeleton-shimmer"></div>` dentro do skeleton
```html
<!-- Adicione a div interna -->
<div class="eau-skeleton">
    <div class="eau-skeleton-shimmer"></div>  ← NECESSÁRIO
</div>
```

**Causa 2:** CSS do WordPress sobrescrevendo
- Aumente especificidade usando múltiplos seletores
- Use `!important` nas propriedades críticas

**Causa 3:** `!important` dentro de `@keyframes`
```css
/* ❌ ERRADO */
@keyframes eauSkeletonShimmer {
    0% { transform: translateX(0) !important; }
}

/* ✅ CORRETO */
@keyframes eauSkeletonShimmer {
    0% { transform: translateX(0); }  /* Sem !important */
}
```

#### ❌ Problema: Skeleton não cobre altura total

**Solução:** Use `flexbox` no overlay e skeleton table:
```css
.eau-table-loading-overlay {
    display: flex !important;
    flex-direction: column !important;
}

.eau-skeleton-table {
    flex: 1 !important;  /* Expande para preencher */
}
```

### Fluxo Completo de Uso

```javascript
// 1. Usuário clica em filtro/paginação
handleApplyFilters: function() {
    // 2. Mostra skeleton
    this.showLoading();

    // 3. Faz request AJAX
    $.ajax({
        url: ajaxurl,
        method: 'POST',
        data: { /* ... */ },
        success: function(response) {
            // 4. Atualiza tabela
            $('#table-tbody').html(response.html);
        },
        complete: function() {
            // 5. Esconde skeleton
            self.hideLoading();
        }
    });
}
```

### Checklist de Implementação

Ao criar uma nova página com skeleton:

- [ ] Usar `Eau_Skeleton::table()` no PHP
- [ ] Garantir que cada `.eau-skeleton` tem `.eau-skeleton-shimmer` dentro
- [ ] Overlay tem `display: flex` e `flex-direction: column`
- [ ] Skeleton table tem `flex: 1`
- [ ] JavaScript chama `showLoading()` antes do AJAX
- [ ] JavaScript chama `hideLoading()` no `complete` callback
- [ ] Testar em página isolada (sem WordPress) primeiro
- [ ] Se não funcionar no WordPress, aumentar especificidade CSS

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

## 🔍 Sistema de Filtros

### Componente Eau_Filters

Use o componente `Eau_Filters` para criar painéis de filtros consistentes em todas as páginas de listagem.

#### Uso Básico

```php
use EauSystem\Components\Eau_Filters;

$filters = new Eau_Filters(array(
    'id' => 'members-filters',
    'filters' => array(
        array(
            'key' => 'status',
            'label' => 'Status',
            'type' => 'select',
            'options' => Eau_Filters::get_status_options(),
            'placeholder' => 'All Status'
        ),
        array(
            'key' => 'institution',
            'label' => 'Institution',
            'type' => 'select',
            'options' => Eau_Filters::get_institution_options(),
            'placeholder' => 'All Institutions'
        ),
    )
));

echo $filters->render();
```

#### Tipos de Filtros Disponíveis

**1. Select (Dropdown)**
```php
array(
    'key' => 'status',
    'label' => 'Status',
    'type' => 'select',
    'options' => array(
        'active' => 'Active',
        'inactive' => 'Inactive',
    ),
    'placeholder' => 'All Status'
)
```

**2. Date (Data Única)**
```php
array(
    'key' => 'registered_date',
    'label' => 'Registration Date',
    'type' => 'date',
    'placeholder' => 'Select date...'
)
```

**3. Date Range (Período)**
```php
array(
    'key' => 'registered_date',
    'label' => 'Registration Period',
    'type' => 'date_range'
)
// Gera dois campos: registered_date_from e registered_date_to
```

**4. Text (Busca de Texto)**
```php
array(
    'key' => 'search',
    'label' => 'Search',
    'type' => 'text',
    'placeholder' => 'Search...'
)
```

#### Métodos Helper

O componente já vem com métodos prontos para filtros comuns:

```php
// Status (active/inactive)
Eau_Filters::get_status_options()

// User Types (superAdmin, Admin, institutionAdmin, Member)
// Respeita hierarquia: institutionAdmin vê apenas institutionAdmin e Member
Eau_Filters::get_user_type_options()

// Instituições
// institutionAdmin vê apenas suas instituições
Eau_Filters::get_institution_options()

// Membership Types (Corporate, Individual, etc)
Eau_Filters::get_membership_type_options()
```

#### JavaScript - Aplicação de Filtros

**IMPORTANTE:** Sempre chamar `showLoading()` antes de aplicar filtros para exibir o skeleton loader.

```javascript
handleApplyFilters: function(e) {
    if (e) e.preventDefault();

    const self = this;
    this.filters = {};

    // Coleta valores de todos os filtros
    $('#eau-filters-panel [data-filter]').each(function() {
        const key = $(this).data('filter');
        const value = $(this).val();

        if (value && value !== '') {
            self.filters[key] = value;
        }
    });

    // ✅ IMPORTANTE: Mostra skeleton durante filtragem
    this.showLoading();

    // Reset para primeira página e recarrega
    this.currentPage = 1;
    this.loadMembers();
}
```

**Limpar Filtros:**

```javascript
handleClearFilters: function(e) {
    e.preventDefault();

    // Limpa todos os inputs de filtro
    $('#eau-filters-panel [data-filter]').val('');

    // Limpa o objeto de filtros
    this.filters = {};

    // ✅ IMPORTANTE: Mostra skeleton durante limpeza
    this.showLoading();

    // Reset para primeira página e recarrega
    this.currentPage = 1;
    this.loadMembers();
}
```

#### Fluxo Completo

1. **Usuário clica em "Aplicar Filtros"** ou "Limpar Filtros"
2. **JavaScript chama** `showLoading()` → Exibe skeleton
3. **AJAX busca** dados filtrados
4. **Complete callback** chama `hideLoading()` → Remove skeleton
5. **Tabela atualizada** com resultados filtrados

#### Especificações Visuais

**Painel de Filtros:**
- Display: `none` (inicialmente escondido)
- Background: `#f9fafb`
- Border: `1px solid #e5e7eb`
- Border-radius: `8px`
- Padding: `1.5rem`
- Grid: `repeat(auto-fit, minmax(250px, 1fr))`

**Botões de Ação:**
- **Aplicar:** `.eau-btn-primary` (azul)
- **Limpar:** `.eau-btn-secondary` (branco)
- Ícones: `check` e `x` (Lucide)

**Select Inputs:**
- Border: `1px solid #d1d5db`
- Border-radius: `8px`
- Padding: `0.75rem 1rem`
- Font-size: `0.9375rem`
- Focus: Border `#2563eb`

#### Exemplo Completo - Members Management

```php
// PHP - Renderização
$filters_config = array(
    'id' => 'members-filters',
    'filters' => array(
        array(
            'key' => 'status',
            'label' => 'Status',
            'type' => 'select',
            'options' => Eau_Filters::get_status_options(),
            'placeholder' => 'All Status'
        ),
        array(
            'key' => 'role',
            'label' => 'User Type',
            'type' => 'select',
            'options' => Eau_Filters::get_user_type_options(),
            'placeholder' => 'All Types'
        ),
        array(
            'key' => 'institution',
            'label' => 'Institution',
            'type' => 'select',
            'options' => Eau_Filters::get_institution_options(),
            'placeholder' => 'All Institutions'
        ),
        array(
            'key' => 'registered_date',
            'label' => 'Registration Period',
            'type' => 'date_range'
        ),
    )
);

$filters = new Eau_Filters($filters_config);
echo $filters->render();
```

```javascript
// JavaScript - Gerenciamento
const MembersManagement = {
    filters: {},

    bindEvents: function() {
        $('.eau-filters-apply').on('click', this.handleApplyFilters.bind(this));
        $('.eau-filters-clear').on('click', this.handleClearFilters.bind(this));
    },

    handleApplyFilters: function(e) {
        if (e) e.preventDefault();

        // Coleta filtros
        const self = this;
        this.filters = {};

        $('#eau-filters-panel [data-filter]').each(function() {
            const key = $(this).data('filter');
            const value = $(this).val();
            if (value && value !== '') {
                self.filters[key] = value;
            }
        });

        // ✅ Mostra skeleton
        this.showLoading();

        // Recarrega
        this.currentPage = 1;
        this.loadMembers();
    },

    loadMembers: function() {
        const self = this;

        // Já mostra loading se vier de filtro/busca
        // Se vier de paginação, chama novamente
        if (!$('#members-table-wrapper-loading').is(':visible')) {
            this.showLoading();
        }

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'eau_get_members',
                ...this.filters
            },
            success: function(response) {
                self.renderMembers(response.data);
            },
            complete: function() {
                self.hideLoading();
            }
        });
    }
};
```

#### Páginas que Usam Filtros

- **Members Management** (`/members/`)
  - Status, User Type, Institution, Registration Period

#### Arquivo do Componente

**Localização:** `includes/components/class-eau-filters.php`

**CSS:** `assets/css/eau-filters.css` (incluído em `eau-dashboard.css`)

**JS:** Integrado nas páginas de listagem (ex: `eau-members-management.js`)

---

## 📊 Sistema de Ordenação de Tabelas

### ⚠️ REGRA CRÍTICA: Ordenação ANTES da Paginação

**NUNCA ordene dados após a paginação**. A ordenação deve sempre ser aplicada em **TODOS** os registros antes de paginar.

#### ❌ Problema Comum

```php
// ❌ ERRADO - Ordena apenas os 20 itens da página atual
$query = new WP_Query(array(
    'posts_per_page' => 20,
    'paged' => 1,
    'orderby' => 'meta_value',
    'meta_key' => 'custom_field'
));

// Isso ordena APENAS os 20 itens retornados, não todos os 128 do banco!
```

#### ✅ Solução Correta

Para campos que precisam ordenação manual (meta fields ou valores calculados), siga este padrão:

**1. Detecte quais campos precisam ordenação manual:**

```php
// Campos que exigem ordenação manual (meta fields ou calculados)
$manual_sort_fields = array('ins_company_name', 'ins_company_id', 'members_count');
$needs_manual_sort = in_array($args['orderby'], $manual_sort_fields);
```

**2. Busque TODOS os registros se precisar ordenação manual:**

```php
if ($needs_manual_sort) {
    $query_args['posts_per_page'] = -1;  // Busca TUDO
    $query_args['nopaging'] = true;
} else {
    $query_args['posts_per_page'] = $args['posts_per_page'];
    $query_args['paged'] = $args['paged'];
}
```

**3. Adicione os valores necessários para ordenação:**

```php
if ($needs_manual_sort) {
    foreach ($items as $item) {
        // Meta fields
        $item->ins_company_name = get_post_meta($item->ID, 'ins_company_name', true);
        $item->ins_company_id = get_post_meta($item->ID, 'ins_company_id', true);

        // Campos calculados
        if ($args['orderby'] === 'members_count') {
            $item->members_count = count_members($item->ID);
        }
    }
}
```

**4. Ordene considerando tipo de dados:**

```php
if ($needs_manual_sort) {
    usort($items, function($a, $b) use ($args) {
        $field = $args['orderby'];
        $val_a = isset($a->$field) ? $a->$field : '';
        $val_b = isset($b->$field) ? $b->$field : '';

        // Ordenação NUMÉRICA para contadores
        if ($field === 'members_count') {
            $comparison = $val_a - $val_b;
        } else {
            // Ordenação ALFABÉTICA case-insensitive para textos
            $comparison = strcasecmp($val_a, $val_b);
        }

        return $args['order'] === 'DESC' ? -$comparison : $comparison;
    });
}
```

**5. Aplique paginação DEPOIS da ordenação:**

```php
if ($needs_manual_sort) {
    // Paginação manual
    $total = count($items);
    $total_pages = ceil($total / $args['posts_per_page']);
    $offset = ($args['paged'] - 1) * $args['posts_per_page'];
    $items = array_slice($items, $offset, $args['posts_per_page']);
}
```

### Exemplo Completo - WP_Query (Post Types)

```php
public static function query_institutions($args = array()) {
    // 1. Detecta campos que precisam ordenação manual
    $manual_sort_fields = array('ins_company_name', 'ins_company_id', 'ins_company_email', 'ins_status', 'members_count');
    $needs_manual_sort = in_array($args['orderby'], $manual_sort_fields);

    // 2. Build query
    $query_args = array(
        'post_type' => 'institutions',
        'post_status' => 'publish',
        'order' => strtoupper($args['order']),
    );

    // 3. Se precisa ordenação manual, busca TODOS
    if ($needs_manual_sort) {
        $query_args['posts_per_page'] = -1;
        $query_args['nopaging'] = true;
        $query_args['orderby'] = 'ID';
    } else {
        $query_args['posts_per_page'] = $args['posts_per_page'];
        $query_args['paged'] = $args['paged'];
        $query_args['orderby'] = $args['orderby'];
    }

    // 4. Execute query
    $query = new WP_Query($query_args);
    $institutions = $query->posts;
    $total = $query->found_posts;

    // 5. Se precisa ordenação manual
    if ($needs_manual_sort) {
        // Adiciona valores para ordenação
        foreach ($institutions as $institution) {
            $institution->ins_company_name = get_post_meta($institution->ID, 'ins_company_name', true);
            $institution->ins_company_id = get_post_meta($institution->ID, 'ins_company_id', true);
            $institution->ins_company_email = get_post_meta($institution->ID, 'ins_company_email', true);
            $institution->ins_status = get_post_meta($institution->ID, 'ins_status', true) ?: 'active';

            if ($args['orderby'] === 'members_count') {
                $institution->members_count = count_institution_members($institution->ins_company_id);
            }
        }

        // Ordena
        usort($institutions, function($a, $b) use ($args) {
            $field = $args['orderby'];
            $val_a = isset($a->$field) ? $a->$field : '';
            $val_b = isset($b->$field) ? $b->$field : '';

            if ($field === 'members_count') {
                $comparison = $val_a - $val_b;  // Numérico
            } else {
                $comparison = strcasecmp($val_a, $val_b);  // Alfabético
            }

            return $args['order'] === 'DESC' ? -$comparison : $comparison;
        });

        // Pagina manualmente
        $total = count($institutions);
        $total_pages = ceil($total / $args['posts_per_page']);
        $offset = ($args['paged'] - 1) * $args['posts_per_page'];
        $institutions = array_slice($institutions, $offset, $args['posts_per_page']);
    } else {
        $total_pages = $query->max_num_pages;
    }

    return array(
        'institutions' => $institutions,
        'total' => $total,
        'total_pages' => $total_pages,
    );
}
```

### Exemplo Completo - SQL Customizado (Users)

```php
public static function get_users_with_filters($args = array()) {
    global $wpdb;

    // 1. Detecta campos que precisam ordenação manual
    $manual_sort_fields = array('first_name', 'last_name', 'mem_type', 'institution_name', 'membership_type', 'status');
    $needs_manual_sort = in_array($args['orderby'], $manual_sort_fields);

    // 2. Build query
    $where_sql = "1=1"; // Adicione seus filtros aqui
    $count_query = "SELECT COUNT(DISTINCT u.ID) FROM {$wpdb->users} u WHERE {$where_sql}";
    $total = $wpdb->get_var($count_query);

    // 3. Query de dados
    if ($needs_manual_sort) {
        // Busca TODOS os IDs sem limit/offset
        $data_query = "SELECT DISTINCT u.ID FROM {$wpdb->users} u WHERE {$where_sql}";
        $user_ids = $wpdb->get_results($data_query);
    } else {
        // Query normal com ordenação SQL
        $orderby = sanitize_sql_orderby("{$args['orderby']} {$args['order']}");
        $data_query = "SELECT DISTINCT u.ID
                      FROM {$wpdb->users} u
                      WHERE {$where_sql}
                      ORDER BY {$orderby}
                      LIMIT %d OFFSET %d";
        $user_ids = $wpdb->get_results($wpdb->prepare($data_query, $args['number'], $args['offset']));
    }

    // 4. Pega dados completos
    $users = array();
    foreach ($user_ids as $user_data) {
        $user = get_userdata($user_data->ID);
        $users[] = array(
            'ID' => $user->ID,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'mem_type' => get_user_meta($user->ID, 'mem_type', true),
            'status' => get_user_status($user->ID),
            'institution_name' => get_user_institution_name($user->ID),
        );
    }

    // 5. Se precisa ordenação manual, ordena e pagina
    if ($needs_manual_sort) {
        usort($users, function($a, $b) use ($args) {
            $field = $args['orderby'];
            $val_a = isset($a[$field]) ? $a[$field] : '';
            $val_b = isset($b[$field]) ? $b[$field] : '';
            $comparison = strcasecmp($val_a, $val_b);
            return $args['order'] === 'DESC' ? -$comparison : $comparison;
        });

        // Paginação manual
        $users = array_slice($users, $args['offset'], $args['number']);
    }

    return array(
        'users' => $users,
        'total' => (int) $total
    );
}
```

### Quando Usar Ordenação Manual

Use ordenação manual quando o campo a ordenar:

1. **É um meta field** (post meta ou user meta)
2. **É um valor calculado** (contadores, agregações)
3. **É um valor de relacionamento** (nome de instituição via lookup)
4. **É uma combinação de campos** (first_name + last_name)

### Quando NÃO Usar Ordenação Manual

Pode usar ordenação SQL direta quando:

1. **Campos nativos da tabela** (user_email, user_login, post_title, post_date)
2. **IDs** (ID, post_author)
3. **Timestamps** (post_date, user_registered)

### Diferença entre Ordenação Numérica e Alfabética

**Numérica** (para contadores, IDs numéricos):
```php
$comparison = $val_a - $val_b;
// Resultado: 1, 2, 3, 10, 20, 100
```

**Alfabética** (para textos, case-insensitive):
```php
$comparison = strcasecmp($val_a, $val_b);
// Resultado: Alice, bob, Charlie, david
```

### Checklist de Implementação

Ao criar uma nova página com tabela ordenável:

- [ ] Identifique quais colunas são meta fields ou calculados
- [ ] Adicione esses campos ao array `$manual_sort_fields`
- [ ] Busque TODOS os registros quando `$needs_manual_sort` for true
- [ ] Adicione os valores dos campos aos objetos antes de ordenar
- [ ] Use ordenação numérica para contadores e alfabética para textos
- [ ] Aplique paginação DEPOIS da ordenação
- [ ] Retorne `total_pages` calculado manualmente

### Arquivos de Referência

- **Institutions:** `ajax/class-eau-institutions-ajax.php` (linha 82-230)
- **Members:** `includes/class-eau-user-institution-helper.php` (linha 244-428)

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

**Versão:** 1.1
**Última Atualização:** 2025-01-22
**Autor:** Claude Code / Platty
