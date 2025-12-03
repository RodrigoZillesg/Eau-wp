# Eau System - Design System & Padrões

> Documentação oficial de padrões de design, layout e funcionalidades do Eau System

---

## 📋 Índice

1. [Visão Geral](#visão-geral)
2. [Design Tokens](#design-tokens)
3. [Componentes](#componentes)
4. [Layout Patterns](#layout-patterns)
5. [Comunicação com Usuário](#comunicação-com-usuário)
6. [JavaScript Patterns](#javascript-patterns)
7. [AJAX Patterns](#ajax-patterns)
8. [Formulários Dinâmicos](#formulários-dinâmicos)

---

## 🎨 Visão Geral

O Eau System usa um design system consistente baseado em:
- **Framework CSS**: Custom (eau-components.css)
- **Ícones**: Lucide Icons
- **Grid**: CSS Grid (2 colunas padrão para forms)
- **Cores**: Palette moderna com foco em azul (#2563eb)
- **Tipografia**: System fonts, sans-serif

---

## 🎯 Design Tokens

### Cores Principais

```css
/* Primary */
--eau-primary: #2563eb;
--eau-primary-hover: #1d4ed8;

/* Secondary */
--eau-secondary: #6b7280;
--eau-secondary-hover: #4b5563;

/* Success */
--eau-success: #10b981;
--eau-success-hover: #059669;

/* Error/Danger */
--eau-error: #ef4444;
--eau-danger: #dc2626;

/* Warning */
--eau-warning: #f59e0b;

/* Info */
--eau-info: #3b82f6;

/* Neutral */
--eau-gray-50: #f9fafb;
--eau-gray-100: #f3f4f6;
--eau-gray-200: #e5e7eb;
--eau-gray-300: #d1d5db;
--eau-gray-600: #4b5563;
--eau-gray-700: #374151;
--eau-gray-800: #1f2937;
--eau-gray-900: #111827;
```

### Espaçamento

```css
/* Spacing scale (rem) */
--space-xs: 0.25rem;   /* 4px */
--space-sm: 0.5rem;    /* 8px */
--space-md: 1rem;      /* 16px */
--space-lg: 1.5rem;    /* 24px */
--space-xl: 2rem;      /* 32px */
--space-2xl: 3rem;     /* 48px */
```

### Border Radius

```css
--radius-sm: 6px;
--radius-md: 8px;
--radius-lg: 12px;
--radius-full: 9999px;
```

### Shadows

```css
--shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.05);
--shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1);
--shadow-lg: 0 10px 15px rgba(0, 0, 0, 0.1);
```

### Z-Index Scale

> ⚠️ **REGRA CRÍTICA - Gerenciamento de Z-Index**
>
> O sistema usa uma escala de z-index bem definida. **NUNCA** use valores arbitrários.
> Sempre consulte esta tabela antes de definir qualquer z-index.

```css
/* Z-Index Scale - DO NOT use arbitrary values */

/* Base Layer - Conteúdo normal */
z-index: 1;           /* Elementos dentro de containers */
z-index: 10;          /* Elementos com leve elevação */

/* Dropdown Layer */
z-index: 1000;        /* Dropdowns, menus suspensos */

/* Sticky/Fixed Elements */
z-index: 9999;        /* Headers fixos, sidebars sticky */

/* Modal Layer */
z-index: 99999;       /* Modais padrão (.eau-modal) */

/* Toast Layer */
z-index: 99999999;    /* Toast notifications (.eau-toast-container) */

/* Confirm Modal Layer - HIGHEST */
z-index: 99999999;    /* Confirm modals (EauNotifications.confirm) */
```

**Hierarquia Visual (do mais baixo para o mais alto):**

1. **Conteúdo normal** (1-10) - Elementos da página
2. **Dropdowns** (1000) - Menus, autocomplete
3. **Sticky elements** (9999) - Headers, sidebars
4. **Modais** (99999) - `.eau-modal` class
5. **Toasts e Confirms** (99999999) - Sempre visíveis acima de tudo

**Regras Importantes:**

1. **Modais sobre modais**: Se precisar abrir um modal sobre outro (ex: confirm sobre payment modal), o modal superior DEVE usar z-index maior inline, não via CSS class.

2. **Nunca use `!important` em z-index de modais dinâmicos**: O CSS usa `!important` para modais padrão, mas modais criados dinamicamente (como confirm) devem usar inline styles para sobrescrever.

3. **Confirm Modals**: O `EauNotifications.confirm()` usa z-index inline `99999999` para garantir que sempre apareça acima de qualquer outro modal.

4. **Evite conflitos de classe**: A modal de confirmação NÃO usa a classe `.eau-modal` para evitar herdar o z-index `99999 !important` do CSS.

**Exemplo - Modal sobre Modal:**

```javascript
// ❌ ERRADO - Usar classe .eau-modal que tem z-index fixo
const $modal = $(`
    <div class="eau-modal">...</div>
`);

// ✅ CORRETO - Usar inline style para z-index alto
const $modal = $(`
    <div class="eau-confirm-overlay" style="z-index:99999999;">
        <div class="eau-confirm-modal">...</div>
    </div>
`);
```

**Arquivos que definem z-index:**

| Arquivo | Seletor | Z-Index | Uso |
|---------|---------|---------|-----|
| `eau-components.css` | `.eau-modal` | 99999 | Modais padrão |
| `eau-components.css` | `.eau-toast-container` | 99999999 | Toast notifications |
| `eau-notifications.js` | `.eau-confirm-overlay` | 99999999 (inline) | Confirm modals |
| `eau-events-management.css` | `#eau-event-edit-modal` | 999999 | Modal de edição de evento |

---

## 🧩 Componentes

### 1. Stats Cards

**Uso**: Exibir métricas no topo da página

**Estrutura**:
```php
$cards_data = array(
    array(
        'title' => 'Total Members',
        'number' => 150,
        'icon' => 'users',
        'color' => 'blue', // blue, green, purple, red
    ),
);

$stats_cards = new Eau_Stats_Cards($cards_data);
echo $stats_cards->render();
```

**Classes CSS**:
- `.eau-stats-grid` - Grid container (4 colunas)
- `.eau-stat-card` - Card individual
- `.eau-stat-card-blue/green/purple/red` - Variantes de cor

---

### 2. Data Table

**Uso**: Tabela com AJAX, ordenação, paginação, ações

**Estrutura**:
```php
$table_config = array(
    'id' => 'members-table',
    'columns' => array(
        array(
            'key' => 'member',
            'label' => 'MEMBER',
            'sortable' => true,
        ),
    ),
    'actions' => array('view', 'edit', 'delete'),
    'selectable' => true,
    'ajax_endpoint' => 'eau_get_members',
    'empty_message' => 'No members found',
    'loading_message' => 'Loading members...',
);

$table = new Eau_Data_Table($table_config);
echo $table->render();
```

**Features**:
- ✅ Seleção múltipla (checkboxes)
- ✅ Ordenação por coluna (sortable)
- ✅ Ações por linha (view, edit, delete)
- ✅ Loading states (skeleton)
- ✅ Empty states
- ✅ AJAX data loading

**Classes CSS**:
- `.eau-data-table-wrapper` - Container principal
- `.eau-table` - Elemento table
- `.eau-sortable` - Coluna ordenável
- `.eau-sorted-asc/desc` - Estado de ordenação

---

### 3. Pagination

**Uso**: Navegação entre páginas

**Estrutura**:
```php
$pagination_config = array(
    'id' => 'members-pagination',
    'total_items' => 150,
    'per_page' => 20,
    'current_page' => 1,
    'max_pages_shown' => 5,
);

$pagination = new Eau_Pagination($pagination_config);
echo $pagination->render();
```

**Features**:
- ✅ First/Last page
- ✅ Previous/Next
- ✅ Página atual destacada
- ✅ Ellipsis (...) para páginas ocultas

---

### 4. Filters Panel

**Uso**: Filtros colapsáveis com aplicar/limpar

**Estrutura**:
```php
$filters_config = array(
    'id' => 'eau-filters-panel',
    'collapsible' => true,
    'show_clear' => true,
    'filters' => array(
        array(
            'key' => 'status',
            'label' => 'Status',
            'type' => 'select',
            'options' => array(
                'active' => 'Active',
                'inactive' => 'Inactive',
            ),
            'placeholder' => 'All Status',
        ),
        array(
            'key' => 'registered_date',
            'label' => 'Registration Date',
            'type' => 'date_range',
        ),
    ),
);

$filters = new Eau_Filters($filters_config);
echo $filters->render();
```

**Tipos de Filtro**:
- `select` - Dropdown
- `date_range` - Range de datas (from/to)

---

### 5. Modals

**Uso**: Dialogs para view/edit/create

**Estrutura**:
```php
$modal_config = array(
    'id' => 'eau-modal-edit',
    'title' => 'Edit Member',
    'size' => 'large', // small, medium, large
    'show_footer' => true,
    'footer_buttons' => array(
        array(
            'label' => 'Cancel',
            'class' => 'eau-btn-secondary',
            'action' => 'close',
        ),
        array(
            'label' => 'Save Changes',
            'class' => 'eau-btn-primary',
            'action' => 'save',
        ),
    ),
);

$modal = new Eau_Modal($modal_config);
echo $modal->render();
```

**Features**:
- ✅ Overlay com backdrop
- ✅ Centralizado com flexbox
- ✅ Tamanhos: small (400px), medium (600px), large (800px)
- ✅ Header com título e botão X
- ✅ Body dinâmico
- ✅ Footer customizável

**JavaScript**:
```javascript
// Abrir modal
EauMembersManagement.openModal('eau-modal-edit');

// Fechar modal
EauMembersManagement.closeModal('eau-modal-edit');
```

---

### 6. Buttons

**Classes Disponíveis**:

```html
<!-- Primary -->
<button class="eau-btn eau-btn-primary">
    <i data-lucide="user-plus"></i>
    Add Member
</button>

<!-- Secondary -->
<button class="eau-btn eau-btn-secondary">
    <i data-lucide="filter"></i>
    Filters
</button>

<!-- Danger -->
<button class="eau-btn eau-btn-danger">
    <i data-lucide="trash"></i>
    Delete
</button>
```

**Padrões**:
- Sempre use ícone Lucide antes do texto
- Gap de 0.5rem entre ícone e texto
- Padding: 0.625rem 1rem
- Border radius: 8px
- Hover com transição suave

---

### 7. Form Fields

**Grid Layout**:
```html
<form class="eau-modal-form">
    <div class="eau-form-grid">
        <!-- Campo normal (1 coluna) -->
        <div class="eau-form-field">
            <label class="eau-form-label">
                First Name
                <span class="eau-form-required">*</span>
            </label>
            <input type="text" class="eau-form-input" name="first_name" required>
        </div>

        <!-- Campo largo (2 colunas) -->
        <div class="eau-form-field eau-form-field-span-2">
            <label class="eau-form-label">Email</label>
            <input type="email" class="eau-form-input" name="user_email">
        </div>

        <!-- Select -->
        <div class="eau-form-field">
            <label class="eau-form-label">Status</label>
            <select class="eau-form-select" name="status">
                <option value="">Select Status</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
        </div>

        <!-- Textarea -->
        <div class="eau-form-field eau-form-field-span-2">
            <label class="eau-form-label">Address</label>
            <textarea class="eau-form-input" name="address" rows="3"></textarea>
        </div>
    </div>
</form>
```

**Regras**:
- Grid de 2 colunas (`.eau-form-grid`)
- Campo normal ocupa 1 coluna
- Campo largo usa `.eau-form-field-span-2`
- Campos obrigatórios têm `<span class="eau-form-required">*</span>`
- Readonly usa atributo `readonly`

---

### 8. Loading States

**Skeleton Loading** (preferido):
```html
<div class="eau-form-grid">
    <div class="eau-form-field">
        <div class="eau-skeleton eau-skeleton-text" style="width: 30%; margin-bottom: 0.5rem;"></div>
        <div class="eau-skeleton eau-skeleton-row"></div>
    </div>
</div>
```

**Spinner** (apenas para loading fullscreen):
```html
<div class="eau-table-loading-overlay">
    <div class="eau-spinner"></div>
    <p>Loading members...</p>
</div>
```

---

### 9. Media Upload (Eau_Media_Upload)

> ⚠️ **REGRA CRÍTICA - LEIA COM ATENÇÃO**
>
> **SEMPRE** use o componente `Eau_Media_Upload` para **QUALQUER** funcionalidade de upload de arquivos no sistema.
>
> **NUNCA** crie HTML customizado para upload, mesmo que pareça mais simples ou específico para seu caso de uso.
>
> **Exemplos de uso obrigatório:**
> - Upload de foto de perfil
> - Upload de certificados/provas
> - Upload de documentos
> - Upload de imagens para qualquer finalidade
> - Qualquer campo que aceite arquivo ou URL de arquivo
>
> **O que NÃO fazer:**
> ```php
> // ❌ ERRADO - Nunca criar HTML de upload manualmente
> <div class="eau-media-upload-wrapper">
>     <div class="eau-media-upload-dropzone">
>         <input type="file" ...>
>         <!-- HTML customizado -->
>     </div>
> </div>
>
> // ❌ ERRADO - Nunca usar wp.media do WordPress
> wp.media({ title: 'Select File' }).open();
> ```
>
> **O que fazer:**
> ```php
> // ✅ CORRETO - Sempre usar o componente
> use EauSystem\Components\Eau_Media_Upload;
> echo Eau_Media_Upload::field('meu-upload', 'campo', '', $config);
> ```

**Uso**: Upload de arquivos com drag & drop, URL externa ou seleção de arquivos já enviados

**Arquivo do componente**: `includes/components/class-eau-media-upload.php`

**Referência de implementação**: Ver uso em `class-eau-my-cpds.php` linhas 272-283

**Estrutura**:
```php
use EauSystem\Components\Eau_Media_Upload;

// Método estático (recomendado)
echo Eau_Media_Upload::field(
    'proof-upload',           // ID do componente
    'proof',                  // Nome do campo
    '',                       // Valor inicial (URL ou attachment ID)
    array(
        'type' => 'both',     // url, media, both
        'placeholder' => 'Upload file or enter URL',
        'url_placeholder' => 'https://example.com/file.pdf',
        'allowed_types' => 'image/*,.pdf,application/pdf',
        'allowed_extensions' => 'jpg,jpeg,png,gif,pdf',
        'max_file_size' => 10 * 1024 * 1024, // 10MB
    )
);

// Ou via instância
$upload = new Eau_Media_Upload(array(
    'id' => 'my-upload',
    'name' => 'attachment',
    'value' => '',
    'type' => 'both',
    'allowed_types' => 'image/*,.pdf',
    'allowed_extensions' => 'jpg,jpeg,png,pdf',
));
echo $upload->render();
```

**Configurações**:
| Parâmetro | Tipo | Default | Descrição |
|-----------|------|---------|-----------|
| `id` | string | 'eau-media-upload' | ID único do componente |
| `name` | string | 'attachment' | Nome do campo no formulário |
| `value` | string | '' | Valor inicial (URL ou attachment ID) |
| `type` | string | 'both' | Tipo de upload: `url`, `media`, `both` |
| `allowed_types` | string | '' | MIME types aceitos (ex: `image/*,.pdf`) |
| `allowed_extensions` | string | '' | Extensões permitidas (ex: `jpg,png,pdf`) |
| `max_file_size` | int | 10485760 | Tamanho máximo em bytes (10MB) |
| `placeholder` | string | 'Enter URL or upload file' | Placeholder |
| `url_placeholder` | string | 'https://example.com/file.pdf' | Placeholder do campo URL |

**Features**:
- ✅ Três abas: URL, Upload, My Files
- ✅ Drag & drop com área clicável
- ✅ Upload com barra de progresso
- ✅ Lista de arquivos já enviados pelo usuário
- ✅ Busca nos arquivos existentes
- ✅ Preview de imagem (thumbnail 48x48)
- ✅ Preview de arquivo (ícone + nome)
- ✅ Validação de tipo e tamanho
- ✅ Botão para remover arquivo selecionado
- ✅ Link para abrir arquivo em nova aba

**Classes CSS**:
- `.eau-media-upload-wrapper` - Container principal
- `.eau-media-upload-tabs` - Abas de navegação
- `.eau-media-upload-tab` - Aba individual
- `.eau-media-upload-tab-active` - Aba ativa
- `.eau-media-upload-panel` - Painel de conteúdo
- `.eau-media-upload-dropzone` - Área de drag & drop
- `.eau-media-upload-preview` - Container do preview
- `.eau-media-upload-preview-thumbnail` - Thumbnail do arquivo
- `.eau-media-upload-preview-thumbnail.has-image` - Quando tem imagem

**JavaScript API**:
```javascript
// Os métodos estão no controlador da página (ex: MyCpdsController)

// Definir valor programaticamente
self.setMediaValue(wrapper, value, type, filename, url);
// wrapper: jQuery object do .eau-media-upload-wrapper
// value: ID do attachment ou URL
// type: 'url' ou 'media'
// filename: nome do arquivo para exibir
// url: URL completa do arquivo

// Limpar valor
self.clearMediaUpload(wrapper);

// Verificar se é imagem
self.isImageFile(filename); // Retorna boolean
self.isImageUrl(url);       // Retorna boolean
```

**Campos Hidden Gerados**:
```html
<!-- Valor principal (ID ou URL) -->
<input type="hidden" name="proof" value="123">

<!-- Tipo do valor (url ou media) -->
<input type="hidden" name="proof_type" value="media">
```

**AJAX Handler para Upload**:
O componente usa o endpoint `eau_upload_file` que deve estar registrado:
```php
add_action('wp_ajax_eau_upload_file', array(__CLASS__, 'upload_file'));
```

**Exemplos de Casos de Uso Comuns**:

```php
// Upload de foto de perfil (apenas imagens, só upload)
echo Eau_Media_Upload::field(
    'profile-photo',
    'profile_photo',
    $current_photo_id,
    array(
        'type' => 'media',  // só upload, sem URL
        'allowed_types' => 'image/*',
        'allowed_extensions' => 'jpg,jpeg,png,gif,webp',
        'max_file_size' => 5 * 1024 * 1024, // 5MB
    )
);

// Upload de certificado/prova (PDF e imagens, com opção de URL)
echo Eau_Media_Upload::field(
    'certificate',
    'certificate',
    '',
    array(
        'type' => 'both',  // URL e upload
        'allowed_types' => 'image/*,.pdf,application/pdf',
        'allowed_extensions' => 'jpg,jpeg,png,gif,pdf',
        'url_placeholder' => 'https://example.com/certificate.pdf',
    )
);

// Upload de documento (apenas PDF)
echo Eau_Media_Upload::field(
    'document',
    'document',
    '',
    array(
        'type' => 'media',
        'allowed_types' => 'application/pdf',
        'allowed_extensions' => 'pdf',
        'max_file_size' => 20 * 1024 * 1024, // 20MB
    )
);
```

---

### 10. WYSIWYG Editor (Quill.js)

**Uso**: Editor de texto rico para campos que precisam de formatação

**Estrutura**:
```php
use EauSystem\Components\Eau_Wysiwyg;

// Primeiro, enfileire os assets do Quill (no enqueue_scripts)
Eau_Wysiwyg::enqueue_assets();

// Método estático (recomendado)
echo Eau_Wysiwyg::field(
    'description-editor',     // ID do editor
    'description',            // Nome do campo
    '<p>Initial content</p>', // Valor inicial (HTML)
    array(
        'placeholder' => 'Enter description...',
        'height' => 200,      // Altura em pixels
        'toolbar' => 'standard', // basic, standard, full
    )
);

// Ou via instância
$wysiwyg = new Eau_Wysiwyg(array(
    'id' => 'my-editor',
    'name' => 'content',
    'value' => '',
    'placeholder' => 'Enter text...',
    'height' => 250,
    'toolbar' => 'full',
));
echo $wysiwyg->render();
```

**Configurações**:
| Parâmetro | Tipo | Default | Descrição |
|-----------|------|---------|-----------|
| `id` | string | 'eau-wysiwyg' | ID único do editor |
| `name` | string | 'content' | Nome do campo no formulário |
| `value` | string | '' | Conteúdo inicial (HTML) |
| `placeholder` | string | 'Enter text here...' | Placeholder |
| `height` | int | 200 | Altura do editor em pixels |
| `toolbar` | string | 'standard' | Tipo da toolbar |

**Tipos de Toolbar**:

| Tipo | Botões Disponíveis |
|------|-------------------|
| `basic` | Bold, Italic, Underline, Lists, Clean |
| `standard` | Headers, Bold, Italic, Underline, Lists, Link, Clean |
| `full` | Headers, Bold, Italic, Underline, Strike, Colors, Lists, Indent, Link, Image, Clean |

**Features**:
- ✅ Formatação de texto (bold, italic, underline, strike)
- ✅ Cabeçalhos (H1, H2, H3)
- ✅ Listas ordenadas e não-ordenadas
- ✅ Links
- ✅ Cores de texto e fundo (toolbar full)
- ✅ Imagens (toolbar full)
- ✅ Indentação
- ✅ Botão limpar formatação
- ✅ Placeholder customizável
- ✅ Sincronização automática com campo hidden
- ✅ Acesso global aos editores via `window.eauWysiwygEditors`

**Classes CSS**:
- `.eau-wysiwyg-wrapper` - Container principal
- `.eau-wysiwyg-editor` - Container do editor Quill
- `.ql-toolbar.ql-snow` - Toolbar do Quill (customizada)
- `.ql-container.ql-snow` - Container do conteúdo
- `.ql-editor` - Área de edição

**Inicialização JavaScript**:
```javascript
// Após renderizar o HTML, inicialize o editor
// Use o método estático para gerar o script
<?php echo Eau_Wysiwyg::get_init_script('description-editor', 'standard'); ?>

// Ou inicialize manualmente
var quill = new Quill('#my-editor-editor', {
    theme: 'snow',
    placeholder: 'Enter text...',
    modules: {
        toolbar: [
            [{ 'header': [1, 2, 3, false] }],
            ['bold', 'italic', 'underline'],
            [{ 'list': 'ordered'}, { 'list': 'bullet' }],
            ['link'],
            ['clean']
        ]
    }
});

// Sincronizar com campo hidden
quill.on('text-change', function() {
    document.getElementById('my-editor').value = quill.root.innerHTML;
});
```

**Acessando Editores Existentes**:
```javascript
// Todos os editores ficam disponíveis em window.eauWysiwygEditors
var editor = window.eauWysiwygEditors['description-editor'];

// Obter conteúdo HTML
var html = editor.root.innerHTML;

// Obter conteúdo texto puro
var text = editor.getText();

// Definir conteúdo
editor.root.innerHTML = '<p>New content</p>';

// Limpar conteúdo
editor.setText('');
```

**Integração com Formulário**:
```html
<!-- O componente gera automaticamente um campo hidden -->
<input type="hidden" id="description-editor" name="description" value="">

<!-- Este campo é atualizado automaticamente quando o conteúdo muda -->
```

**Enfileiramento de Assets**:
```php
// No método enqueue_assets() da sua página
public static function enqueue_assets() {
    // ... outros assets ...

    // Assets do Quill
    Eau_Wysiwyg::enqueue_assets();
}
```

---

## 📐 Layout Patterns

### Estrutura de Página Padrão

```html
<div class="eau-[page-name]-container">

    <!-- 1. Stats Cards (se aplicável) -->
    <div class="eau-stats-grid">...</div>

    <!-- 2. Page Header -->
    <div class="eau-page-header">
        <div class="eau-page-header-title">
            <h1>Page Title</h1>
            <p class="eau-page-header-subtitle">Page description</p>
        </div>
        <div class="eau-page-header-actions">
            <button class="eau-btn eau-btn-secondary">...</button>
            <button class="eau-btn eau-btn-primary">...</button>
        </div>
    </div>

    <!-- 3. Search and Filters Bar -->
    <div class="eau-search-filters-bar">
        <div class="eau-search-wrapper">
            <i data-lucide="search"></i>
            <input type="text" class="eau-search-input" placeholder="Search...">
        </div>
        <button class="eau-btn eau-btn-secondary">
            <i data-lucide="filter"></i>
            Filters
        </button>
    </div>

    <!-- 4. Filters Panel (collapsible) -->
    <!-- Render via Eau_Filters component -->

    <!-- 5. Data Table -->
    <!-- Render via Eau_Data_Table component -->

    <!-- 6. Pagination -->
    <div id="eau-pagination-container">
        <!-- Render via Eau_Pagination component -->
    </div>

    <!-- 7. Modals -->
    <!-- Render via Eau_Modal component -->

</div>
```

### Hierarquia Visual

1. **Stats Cards** - Topo, destaque
2. **Page Header** - Título + Ações principais
3. **Search Bar** - Busca rápida + Toggle filtros
4. **Filters Panel** - Filtros avançados (colapsável)
5. **Data Table** - Conteúdo principal
6. **Pagination** - Navegação
7. **Modals** - Camada superior (overlay)

---

## 💬 Comunicação com Usuário

> **REGRA DE OURO**: Nunca use `alert()`, `confirm()` ou `console.log()` para feedback ao usuário.

### Toast Notifications

**Quando usar**:
- Feedback de ações (success, error)
- Avisos importantes
- Informações temporárias

**API**:
```javascript
// Success (verde)
EauNotifications.success('Saved!', 'Member updated successfully');

// Error (vermelho)
EauNotifications.error('Error', 'Failed to delete member');

// Warning (amarelo)
EauNotifications.warning('Warning', 'Maximum file size is 5MB');

// Info (azul)
EauNotifications.info('Info', 'New version available');
```

**Posição**: Top-right, fixed
**Duração**: 5 segundos (configurável)
**Animação**: Slide in/out

### Confirm Modals

**Quando usar**:
- Ações destrutivas (delete)
- Ações irreversíveis
- Decisões importantes

**API**:
```javascript
EauNotifications.confirm({
    title: 'Delete Member?',
    message: 'This action cannot be undone.',
    type: 'danger', // danger, warning, info
    confirmText: 'Delete',
    cancelText: 'Cancel',
    onConfirm: function() {
        // Executar ação
    },
    onCancel: function() {
        // Opcional
    }
});
```

**Tipos**:
- `danger` - Delete, ações destrutivas (vermelho)
- `warning` - Ações importantes mas reversíveis (amarelo)
- `info` - Confirmações informativas (azul)

### Documentação Completa

Ver: `/docs/USER-COMMUNICATION.md`

---

## 💻 JavaScript Patterns

### Estrutura de Controlador

```javascript
(function($) {
    'use strict';

    const EauPageController = {

        // === STATE ===
        currentPage: 1,
        perPage: 20,
        searchTerm: '',
        filters: {},
        selectedIds: [],
        orderBy: 'display_name',
        order: 'ASC',
        editableFields: {},
        dynamicData: {},

        // === INIT ===
        init: function() {
            this.loadDynamicData();
            this.bindEvents();
            this.loadData();
            console.log('Controller initialized');
        },

        // === LOAD DYNAMIC DATA ===
        loadDynamicData: function() {
            const self = this;

            // Carrega configurações/dados necessários ANTES de renderizar
            $.ajax({
                url: ajaxData.ajaxUrl,
                type: 'POST',
                async: false, // Síncrono para garantir carregamento
                data: {
                    action: 'get_dynamic_data',
                    nonce: ajaxData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.dynamicData = response.data;
                    }
                }
            });
        },

        // === BIND EVENTS ===
        bindEvents: function() {
            const self = this;

            // Search
            $('#search-input').on('input', this.debounce(function(e) {
                self.searchTerm = $(e.target).val();
                self.currentPage = 1;
                self.loadData();
            }, 300));

            // Sorting
            $(document).on('click', '.eau-sortable', function() {
                const columnKey = $(this).data('key');
                self.handleSort(columnKey);
            });

            // Actions
            $(document).on('click', '.eau-action-view', function(e) {
                e.preventDefault();
                const id = $(this).data('id');
                self.viewItem(id);
            });

            $(document).on('click', '.eau-action-edit', function(e) {
                e.preventDefault();
                const id = $(this).data('id');
                self.editItem(id);
            });

            $(document).on('click', '.eau-action-delete', function(e) {
                e.preventDefault();
                const id = $(this).data('id');
                self.deleteItem(id);
            });

            // Pagination
            $(document).on('click', '.eau-pagination-btn', function(e) {
                e.preventDefault();
                const page = $(this).data('page');
                self.goToPage(page);
            });

            // Modal actions
            $(document).on('click', '[data-action="save"]', function() {
                self.saveItem('modal-id');
            });

            $(document).on('click', '[data-action="create"]', function() {
                self.createItem();
            });

            $(document).on('click', '[data-action="close"]', function() {
                const modalId = $(this).closest('.eau-modal-overlay').attr('id').replace('-overlay', '');
                self.closeModal(modalId);
            });
        },

        // === LOAD DATA ===
        loadData: function() {
            const self = this;

            this.showLoading();

            $.ajax({
                url: ajaxData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'get_items',
                    nonce: ajaxData.nonce,
                    page: this.currentPage,
                    per_page: this.perPage,
                    search: this.searchTerm,
                    orderby: this.orderBy,
                    order: this.order,
                    ...this.filters
                },
                success: function(response) {
                    if (response.success) {
                        self.renderData(response.data);
                        self.renderPagination(response.data);
                    } else {
                        EauNotifications.error('Error', 'Failed to load data');
                    }
                },
                error: function() {
                    EauNotifications.error('Network Error', 'Please try again');
                },
                complete: function() {
                    self.hideLoading();
                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                }
            });
        },

        // === SORTING ===
        handleSort: function(columnKey) {
            const columnMap = {
                'name': 'display_name',
                'email': 'user_email'
            };

            const sortField = columnMap[columnKey] || columnKey;

            if (this.orderBy === sortField) {
                this.order = this.order === 'ASC' ? 'DESC' : 'ASC';
            } else {
                this.orderBy = sortField;
                this.order = 'ASC';
            }

            this.updateSortIcons(columnKey);
            this.currentPage = 1;
            this.loadData();
        },

        updateSortIcons: function(activeColumn) {
            $('.eau-sortable').removeClass('eau-sorted-asc eau-sorted-desc');
            const $activeHeader = $(`.eau-sortable[data-key="${activeColumn}"]`);
            if (this.order === 'ASC') {
                $activeHeader.addClass('eau-sorted-asc');
            } else {
                $activeHeader.addClass('eau-sorted-desc');
            }
        },

        // === MODAL MANAGEMENT ===
        openModal: function(modalId) {
            const $overlay = $('#' + modalId + '-overlay');
            $overlay.css('display', 'flex').hide().fadeIn(200);
        },

        closeModal: function(modalId) {
            $('#' + modalId + '-overlay').fadeOut(200);
        },

        // === CRUD OPERATIONS ===
        viewItem: function(id) {
            this.openModal('eau-modal-view');
            this.loadItemDetails(id, 'view');
        },

        editItem: function(id) {
            this.openModal('eau-modal-edit');
            this.loadItemDetails(id, 'edit');
        },

        deleteItem: function(id) {
            const self = this;

            EauNotifications.confirm({
                title: 'Delete Item?',
                message: 'This action cannot be undone.',
                type: 'danger',
                confirmText: 'Delete',
                cancelText: 'Cancel',
                onConfirm: function() {
                    $.ajax({
                        url: ajaxData.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'delete_item',
                            nonce: ajaxData.nonce,
                            id: id
                        },
                        success: function(response) {
                            if (response.success) {
                                EauNotifications.success('Deleted!', 'Item removed successfully');
                                self.loadData();
                            } else {
                                EauNotifications.error('Error', response.data.message);
                            }
                        },
                        error: function() {
                            EauNotifications.error('Network Error', 'Please try again');
                        }
                    });
                }
            });
        },

        // === UTILITIES ===
        showLoading: function() {
            $('#table-id-loading').show();
        },

        hideLoading: function() {
            $('#table-id-loading').hide();
        },

        debounce: function(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        EauPageController.init();
    });

})(jQuery);
```

### Padrões Importantes

1. **IIFE (Immediately Invoked Function Expression)**
   ```javascript
   (function($) {
       'use strict';
       // código
   })(jQuery);
   ```

2. **Objeto Controlador**
   - Agrupa toda a lógica em um objeto
   - Facilita acesso a state e métodos
   - Use `const self = this` para preservar contexto

3. **State Centralizado**
   - Todas as variáveis de estado no topo
   - Facilita debug e manutenção

4. **Async/Await vs Callbacks**
   - Use callbacks para AJAX (padrão WordPress)
   - Use `async: false` apenas para dados essenciais no init

5. **Debounce para Search**
   - Sempre use debounce (300ms) em campos de busca
   - Evita requests excessivos

6. **Lucide Icons**
   - Sempre re-inicialize após AJAX: `lucide.createIcons()`

---

## 🔌 AJAX Patterns

### Estrutura de Handlers PHP

```php
<?php
namespace EauSystem\Ajax;

class Eau_Items_Ajax {

    /**
     * Registra os handlers AJAX
     */
    public static function register_handlers() {
        // Lista items
        add_action('wp_ajax_eau_get_items', array(__CLASS__, 'get_items'));

        // Get item details
        add_action('wp_ajax_eau_get_item_details', array(__CLASS__, 'get_item_details'));

        // Create item
        add_action('wp_ajax_eau_create_item', array(__CLASS__, 'create_item'));

        // Update item
        add_action('wp_ajax_eau_update_item', array(__CLASS__, 'update_item'));

        // Delete item
        add_action('wp_ajax_eau_delete_item', array(__CLASS__, 'delete_item'));

        // Get dynamic data (configs, options, etc)
        add_action('wp_ajax_eau_get_dynamic_data', array(__CLASS__, 'get_dynamic_data'));
    }

    /**
     * AJAX: Get Items (lista paginada com filtros)
     */
    public static function get_items() {
        // Verifica nonce
        check_ajax_referer('eau_nonce', 'nonce');

        // Verifica permissões (opcional)
        if (!current_user_can('list_users')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        // Pega parâmetros
        $page = isset($_POST['page']) ? absint($_POST['page']) : 1;
        $per_page = isset($_POST['per_page']) ? absint($_POST['per_page']) : 20;
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $orderby = isset($_POST['orderby']) ? sanitize_text_field($_POST['orderby']) : 'ID';
        $order = isset($_POST['order']) ? sanitize_text_field($_POST['order']) : 'DESC';

        // Filtros específicos
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';

        // Calcula offset
        $offset = ($page - 1) * $per_page;

        // Busca dados (exemplo com WP_Query)
        $args = array(
            'post_type' => 'custom_type',
            'posts_per_page' => $per_page,
            'offset' => $offset,
            's' => $search,
            'orderby' => $orderby,
            'order' => $order,
        );

        if (!empty($status)) {
            $args['meta_query'] = array(
                array(
                    'key' => 'status',
                    'value' => $status,
                ),
            );
        }

        $query = new \WP_Query($args);

        // Formata dados para a tabela
        $rows = array();
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $rows[] = self::format_item_row(get_post());
            }
            wp_reset_postdata();
        }

        // Retorna resposta
        wp_send_json_success(array(
            'rows' => $rows,
            'total' => $query->found_posts,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil($query->found_posts / $per_page),
        ));
    }

    /**
     * Formata os dados do item para a tabela
     */
    private static function format_item_row($post) {
        return array(
            'ID' => $post->ID,
            'name' => get_the_title($post),
            'status' => get_post_meta($post->ID, 'status', true),
            // ... outros campos
        );
    }

    /**
     * AJAX: Create Item
     */
    public static function create_item() {
        check_ajax_referer('eau_nonce', 'nonce');

        // Verifica permissões
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        // Pega dados
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';

        // Validações
        if (empty($name)) {
            wp_send_json_error(array('message' => 'Name is required'));
        }

        // Cria item
        $post_id = wp_insert_post(array(
            'post_title' => $name,
            'post_type' => 'custom_type',
            'post_status' => 'publish',
        ));

        if (is_wp_error($post_id)) {
            wp_send_json_error(array('message' => $post_id->get_error_message()));
        }

        // Salva meta fields
        update_post_meta($post_id, 'custom_field', sanitize_text_field($_POST['custom_field']));

        wp_send_json_success(array(
            'message' => 'Item created successfully',
            'item_id' => $post_id,
        ));
    }

    /**
     * AJAX: Update Item
     */
    public static function update_item() {
        check_ajax_referer('eau_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $item_id = isset($_POST['item_id']) ? absint($_POST['item_id']) : 0;
        $fields = isset($_POST['fields']) ? $_POST['fields'] : array();

        if (!$item_id) {
            wp_send_json_error(array('message' => 'Invalid item ID'));
        }

        // Atualiza post
        wp_update_post(array(
            'ID' => $item_id,
            'post_title' => sanitize_text_field($fields['name']),
        ));

        // Atualiza meta fields
        foreach ($fields as $key => $value) {
            update_post_meta($item_id, sanitize_key($key), sanitize_text_field($value));
        }

        wp_send_json_success(array('message' => 'Item updated successfully'));
    }

    /**
     * AJAX: Delete Item
     */
    public static function delete_item() {
        check_ajax_referer('eau_nonce', 'nonce');

        if (!current_user_can('delete_posts')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $item_id = isset($_POST['id']) ? absint($_POST['id']) : 0;

        if (!$item_id) {
            wp_send_json_error(array('message' => 'Invalid item ID'));
        }

        $deleted = wp_delete_post($item_id, true);

        if ($deleted) {
            wp_send_json_success(array('message' => 'Item deleted successfully'));
        } else {
            wp_send_json_error(array('message' => 'Failed to delete item'));
        }
    }
}
```

### Padrões AJAX

1. **Sempre use nonce**
   ```php
   check_ajax_referer('eau_nonce', 'nonce');
   ```

2. **Sempre verifique permissões**
   ```php
   if (!current_user_can('capability')) {
       wp_send_json_error(array('message' => 'Permission denied'));
   }
   ```

3. **Sempre sanitize inputs**
   ```php
   $search = sanitize_text_field($_POST['search']);
   $id = absint($_POST['id']);
   $email = sanitize_email($_POST['email']);
   ```

4. **Sempre use wp_send_json_success/error**
   ```php
   wp_send_json_success(array('data' => $data));
   wp_send_json_error(array('message' => 'Error message'));
   ```

5. **Estrutura de resposta consistente**
   ```php
   // Success
   wp_send_json_success(array(
       'rows' => $rows,
       'total' => $total,
       'page' => $page,
       'total_pages' => $total_pages,
   ));

   // Error
   wp_send_json_error(array(
       'message' => 'User-friendly error message'
   ));
   ```

---

## 📝 Formulários Dinâmicos

### Sistema de Campos Configuráveis

**Conceito**: Campos de formulário baseados em configuração salvável

**Estrutura**:

1. **Página de Settings** (admin)
   - Lista todos os campos disponíveis
   - Admin marca enabled/required/readonly
   - Admin reordena com drag & drop
   - Salva em option `[prefix]_editable_fields`

2. **Classe de Settings**
   ```php
   class Eau_Items_Settings {
       const OPTION_NAME = 'eau_items_editable_fields';

       public static function get_available_fields() {
           return array(
               'field_key' => array(
                   'type' => 'core', // ou 'meta'
                   'label' => 'Field Label',
                   'field_type' => 'text', // text, email, tel, select, textarea
                   'enabled' => true,
                   'required' => false,
                   'readonly' => false,
                   'order' => 1,
               ),
           );
       }

       public static function get_editable_fields() {
           $saved = get_option(self::OPTION_NAME, array());
           $available = self::get_available_fields();

           $editable = array();
           foreach ($available as $key => $field) {
               $config = isset($saved[$key]) ? array_merge($field, $saved[$key]) : $field;
               if (isset($config['enabled']) && $config['enabled']) {
                   $editable[$key] = $config;
               }
           }

           // Ordena por order
           uasort($editable, function($a, $b) {
               return ($a['order'] ?? 999) - ($b['order'] ?? 999);
           });

           return $editable;
       }
   }
   ```

3. **AJAX Endpoint**
   ```php
   public static function get_editable_fields() {
       check_ajax_referer('eau_nonce', 'nonce');
       $fields = Eau_Items_Settings::get_editable_fields();
       wp_send_json_success($fields);
   }
   ```

4. **JavaScript - Renderização Dinâmica**
   ```javascript
   loadEditableFields: function() {
       $.ajax({
           url: ajaxData.ajaxUrl,
           async: false,
           data: {
               action: 'eau_get_editable_fields',
               nonce: ajaxData.nonce
           },
           success: function(response) {
               if (response.success) {
                   self.editableFields = response.data;
               }
           }
       });
   },

   renderForm: function(modalId, itemData, mode) {
       let html = '<form class="eau-modal-form"><div class="eau-form-grid">';

       for (const fieldKey in this.editableFields) {
           const field = this.editableFields[fieldKey];
           html += this.renderField(fieldKey, field, itemData, mode);
       }

       html += '</div></form>';
       $('#' + modalId + '-body').html(html);
   },

   renderField: function(fieldKey, fieldConfig, itemData, mode) {
       const isView = mode === 'view';
       const readonly = isView || fieldConfig.readonly ? 'readonly' : '';
       const required = !isView && fieldConfig.required ? 'required' : '';

       // Pega valor
       let value = '';
       if (fieldConfig.type === 'core') {
           value = itemData[fieldKey] || '';
       } else if (fieldConfig.type === 'meta') {
           value = itemData.meta && itemData.meta[fieldConfig.meta_key] ? itemData.meta[fieldConfig.meta_key] : '';
       }

       const fieldName = fieldConfig.type === 'meta' ? fieldConfig.meta_key : fieldKey;
       const inputType = fieldConfig.field_type || 'text';

       let html = '';

       if (inputType === 'select') {
           // Renderiza select
       } else if (inputType === 'textarea') {
           // Renderiza textarea
       } else {
           // Renderiza input
           html += `<div class="eau-form-field">`;
           html += `<label class="eau-form-label">${fieldConfig.label}${required ? ' <span class="eau-form-required">*</span>' : ''}</label>`;
           html += `<input type="${inputType}" class="eau-form-input" name="${fieldName}" value="${value}" ${readonly} ${required}>`;
           html += `</div>`;
       }

       return html;
   }
   ```

### Vantagens

- ✅ Admin controla quais campos aparecem
- ✅ Fácil adicionar novos campos
- ✅ Ordenação customizável
- ✅ Validação automática (required)
- ✅ Readonly configurável
- ✅ Suporta core fields e meta fields

---

## ✅ Checklist de Nova Página

Ao criar uma nova página no estilo Members Management:

### PHP (Backend)

- [ ] Criar classe `Eau_[Name]_Management.php` em `/includes/`
- [ ] Registrar shortcode `[eau_[name]_management]`
- [ ] Criar classe AJAX `Eau_[Name]_Ajax.php` em `/ajax/`
- [ ] Registrar handlers AJAX
- [ ] Criar classe Settings `Eau_[Name]_Settings.php` (se aplicável)
- [ ] Enfileirar CSS/JS na função `enqueue_assets()`
- [ ] Usar componentes: `Eau_Stats_Cards`, `Eau_Data_Table`, `Eau_Pagination`, `Eau_Filters`, `Eau_Modal`

### JavaScript (Frontend)

- [ ] Criar arquivo `eau-[name]-management.js` em `/assets/js/`
- [ ] Estrutura IIFE com objeto controlador
- [ ] State: page, perPage, search, filters, orderBy, order, editableFields
- [ ] Init: loadDynamicData, bindEvents, loadData
- [ ] Implementar sorting
- [ ] Implementar CRUD (view, edit, delete, create)
- [ ] Usar `EauNotifications` para feedback
- [ ] Re-inicializar Lucide após AJAX

### CSS (Estilos)

- [ ] Criar arquivo `eau-[name]-management.css` (se precisar de estilos específicos)
- [ ] Reusar classes de `eau-components.css` sempre que possível
- [ ] Seguir naming convention: `.eau-[component]-[elemento]`

### Testes

- [ ] Testar ordenação
- [ ] Testar paginação
- [ ] Testar busca
- [ ] Testar filtros
- [ ] Testar CRUD completo
- [ ] Testar validações
- [ ] Testar permissões
- [ ] Testar toast notifications
- [ ] Testar confirm modals
- [ ] Testar em mobile

---

## 📚 Referências

- **Componentes CSS**: `/assets/css/eau-components.css`
- **Notificações**: `/assets/js/eau-notifications.js`
- **Exemplo Completo**: `/includes/class-eau-members-management.php`
- **Exemplo AJAX**: `/ajax/class-eau-members-ajax.php`
- **Exemplo JS**: `/assets/js/eau-members-management.js`
- **User Communication**: `/docs/USER-COMMUNICATION.md`

---

**Versão**: 1.2.0
**Última Atualização**: 2025-12-03
**Autor**: Platty / Rodrigo Zillesg
