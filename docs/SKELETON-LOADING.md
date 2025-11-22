# Skeleton Loading - Padrão Oficial

## 📋 Visão Geral

**Skeleton Loading** é o padrão oficial para indicar estados de carregamento em todo o plugin Eau System.

❌ **NUNCA USE:**
- Spinners/loaders rotativos
- Texto "Loading..."
- GIFs animados
- Barras de progresso indeterminadas

✅ **SEMPRE USE:**
- Skeleton loaders (placeholders animados)

---

## 🎯 Por que Skeleton Loading?

1. **Melhor UX**: Usuários veem a estrutura do conteúdo antes dele carregar
2. **Reduz percepção de lentidão**: O movimento contínuo é menos frustrante que um spinner
3. **Consistência visual**: Mantém o layout estável durante o carregamento
4. **Profissional**: Padrão usado por Facebook, LinkedIn, YouTube, etc.

---

## 🔧 Como Usar

### PHP (Server-side)

```php
use EauSystem\Components\Eau_Skeleton;

// Tabela completa (Members, etc)
echo Eau_Skeleton::table(10); // 10 linhas

// Cards de estatísticas (Dashboard)
echo Eau_Skeleton::stats_cards(4); // 4 cards

// Texto simples
echo Eau_Skeleton::text(3); // 3 linhas

// Card genérico
echo Eau_Skeleton::card();

// Linha genérica
echo Eau_Skeleton::row();
```

### JavaScript (Client-side)

```javascript
// Skeleton para tabela
const skeleton = `
    <tr>
        <td colspan="7" style="padding: 0;">
            ${renderTableSkeleton(10)}
        </td>
    </tr>
`;

// Skeleton para stats cards
const skeleton = renderStatsCardsSkeleton(4);
```

### HTML Direto

```html
<!-- Skeleton básico -->
<div class="eau-skeleton eau-skeleton-text"></div>

<!-- Skeleton de título -->
<div class="eau-skeleton eau-skeleton-title"></div>

<!-- Skeleton circular (avatar/icon) -->
<div class="eau-skeleton eau-skeleton-circle"></div>

<!-- Skeleton de card -->
<div class="eau-skeleton eau-skeleton-card"></div>
```

---

## 📦 Classes CSS Disponíveis

### Base
- `.eau-skeleton` - Classe base (obrigatória)

### Variações
- `.eau-skeleton-text` - Linha de texto (1rem altura)
- `.eau-skeleton-title` - Título (1.5rem altura, 60% largura)
- `.eau-skeleton-card` - Card genérico (120px altura)
- `.eau-skeleton-circle` - Círculo (48x48px)
- `.eau-skeleton-row` - Linha genérica (60px altura)

### Componentes Complexos
- `.eau-skeleton-table` - Container de tabela
- `.eau-skeleton-table-header` - Cabeçalho da tabela
- `.eau-skeleton-table-row` - Linha da tabela
- `.eau-skeleton-stats-grid` - Grid de cards de estatísticas
- `.eau-skeleton-stat-card` - Card individual de estatística

---

## 🎨 Customização

### Ajustar largura
```html
<div class="eau-skeleton eau-skeleton-text" style="width: 80%;"></div>
```

### Ajustar altura
```html
<div class="eau-skeleton eau-skeleton-row" style="height: 100px;"></div>
```

### Ajustar animação
```css
.eau-skeleton {
    animation-duration: 1s !important; /* Padrão: 1.5s */
}
```

---

## 📍 Onde Usar

### ✅ Use skeleton loading em:

1. **Tabelas de dados** (Members, Institutions, etc)
   - Ao carregar a lista inicial
   - Ao aplicar filtros
   - Ao mudar de página

2. **Cards de estatísticas** (Dashboard)
   - Ao carregar métricas

3. **Formulários dinâmicos**
   - Ao buscar dados para preencher

4. **Listas** (dropdowns, selects)
   - Ao carregar opções via AJAX

5. **Conteúdo de modais**
   - Ao buscar detalhes de um item

### ❌ Não use skeleton loading em:

1. **Operações instantâneas** (< 100ms)
2. **Ações de salvamento** (use feedback visual diferente)
3. **Uploads** (use barra de progresso determinada)

---

## 🔄 Exemplos Práticos

### Exemplo 1: Tabela de Members

```php
// class-eau-members-management.php
private static function render_members_table() {
    $table_config = array(
        // ... outras configs
    );

    $table = new Eau_Data_Table($table_config);
    return $table->render();
}
```

O DataTable automaticamente usa `Eau_Skeleton::table()` no estado inicial.

### Exemplo 2: Stats Cards (Dashboard)

```php
// class-eau-dashboard.php
public static function render_page() {
    ob_start();
    ?>
    <div id="eau-stats-container">
        <!-- Skeleton inicial -->
        <?php echo Eau_Skeleton::stats_cards(5); ?>
    </div>

    <script>
    jQuery(document).ready(function($) {
        // Carrega stats reais via AJAX
        loadStats();
    });
    </script>
    <?php
    return ob_get_clean();
}
```

### Exemplo 3: Loading em filtros

```javascript
// Ao aplicar filtros
function applyFilters() {
    const tbody = $('.eau-table-tbody');

    // Mostra skeleton
    tbody.html(`
        <tr>
            <td colspan="7" style="padding: 0;">
                ${renderTableSkeleton(10)}
            </td>
        </tr>
    `);

    // Carrega dados
    $.ajax({
        // ... ajax config
        success: function(data) {
            renderMembers(data.rows);
        }
    });
}
```

---

## 🎬 Animação

O skeleton usa um gradiente animado que se move da direita para a esquerda, criando um efeito de "brilho" ou "shimmer".

```css
@keyframes eauSkeletonLoading {
    0% {
        background-position: 200% 0;
    }
    100% {
        background-position: -200% 0;
    }
}
```

- **Duração**: 1.5s
- **Timing**: ease-in-out
- **Loop**: infinite

---

## 🚫 Anti-patterns

### ❌ NÃO FAÇA:

```php
// ERRADO: Spinner antigo
<div class="eau-spinner"></div>
<p>Loading...</p>

// ERRADO: Apenas texto
<p>Please wait...</p>

// ERRADO: GIF animado
<img src="loading.gif" alt="Loading">
```

### ✅ FAÇA:

```php
// CORRETO: Skeleton
<?php echo Eau_Skeleton::table(); ?>

// CORRETO: Skeleton personalizado
<div class="eau-skeleton eau-skeleton-text"></div>
```

---

## 📝 Checklist de Implementação

Ao adicionar uma nova feature com loading:

- [ ] Identifiquei onde preciso de loading state
- [ ] Usei `Eau_Skeleton::` ao invés de spinner
- [ ] Escolhi o tipo correto de skeleton (table, stats_cards, text, etc)
- [ ] Testei a transição skeleton → conteúdo real
- [ ] Verifiquei que não há "flash" durante a transição
- [ ] O skeleton tem o mesmo layout do conteúdo real

---

## 🔗 Referências

- Arquivo CSS: `/assets/css/eau-components.css` (linhas 1228-1354)
- Componente PHP: `/includes/components/class-eau-skeleton.php`
- Uso no DataTable: `/includes/components/class-eau-data-table.php`

---

## 📞 Dúvidas?

Em caso de dúvida sobre quando ou como usar skeleton loading, consulte esta documentação ou pergunte ao time de desenvolvimento.

**Regra de ouro**: Se tem loading, use skeleton. Sempre.
