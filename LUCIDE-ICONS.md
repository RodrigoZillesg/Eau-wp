# 🎨 Lucide Icons no Eau System

O plugin Eau System agora inclui suporte completo para **Lucide Icons** - uma biblioteca moderna de ícones SVG.

## 📖 Como Usar

### Método 1: Atributo `data-lucide` (Recomendado)

```html
<i data-lucide="heart"></i>
<i data-lucide="user"></i>
<i data-lucide="settings"></i>
<i data-lucide="database"></i>
<i data-lucide="upload"></i>
```

Os ícones são automaticamente renderizados quando a página carrega.

### Método 2: JavaScript Dinâmico

```javascript
// Adicionar ícone dinamicamente
const icon = document.createElement('i');
icon.setAttribute('data-lucide', 'check-circle');
document.body.appendChild(icon);

// Re-inicializar ícones
lucide.createIcons();
```

### Método 3: PHP (WordPress)

```php
// Em um arquivo PHP do plugin
echo '<i data-lucide="alert-triangle"></i>';
echo '<span>Mensagem de aviso</span>';
```

## 🎨 Personalizando Ícones

### Tamanho

```html
<i data-lucide="heart" style="width: 16px; height: 16px;"></i>
<i data-lucide="heart" style="width: 24px; height: 24px;"></i>
<i data-lucide="heart" style="width: 48px; height: 48px;"></i>
```

### Cor

```html
<i data-lucide="heart" style="color: red;"></i>
<i data-lucide="check" style="color: #00a32a;"></i>
<i data-lucide="x" style="color: #d63638;"></i>
```

### Stroke (Espessura)

```html
<i data-lucide="heart" stroke-width="1"></i>
<i data-lucide="heart" stroke-width="2"></i>
<i data-lucide="heart" stroke-width="3"></i>
```

### Via CSS

```css
.meu-icone {
    width: 20px;
    height: 20px;
    color: #2271b1;
    stroke-width: 2;
}
```

```html
<i data-lucide="heart" class="meu-icone"></i>
```

## 📚 Ícones Úteis para o Plugin

### Ações
- `upload` - Upload de arquivos
- `download` - Download
- `save` - Salvar
- `trash-2` - Deletar
- `edit` - Editar
- `copy` - Copiar
- `check` - Confirmar
- `x` - Cancelar

### Navegação
- `chevron-left` - Voltar
- `chevron-right` - Próximo
- `arrow-left` - Seta esquerda
- `arrow-right` - Seta direita
- `home` - Início
- `menu` - Menu

### Dados
- `database` - Banco de dados
- `table` - Tabela
- `file-text` - Arquivo
- `folder` - Pasta
- `archive` - Arquivo

### Status
- `check-circle` - Sucesso
- `x-circle` - Erro
- `alert-circle` - Aviso
- `info` - Informação
- `loader` - Carregando

### Usuários
- `user` - Usuário
- `users` - Usuários
- `user-plus` - Adicionar usuário
- `user-minus` - Remover usuário
- `user-check` - Usuário verificado

### Configurações
- `settings` - Configurações
- `sliders` - Ajustes
- `tool` - Ferramentas
- `filter` - Filtro
- `search` - Buscar

## 🔍 Galeria Completa

Veja todos os ícones disponíveis em:
**https://lucide.dev/icons/**

## 💡 Exemplos Práticos

### Botão com Ícone

```html
<button class="button button-primary">
    <i data-lucide="upload"></i>
    Fazer Upload
</button>
```

### Mensagem de Status

```html
<div class="notice notice-success">
    <i data-lucide="check-circle" style="color: #00a32a;"></i>
    <p>Importação concluída com sucesso!</p>
</div>
```

### Lista com Ícones

```html
<ul>
    <li><i data-lucide="check"></i> Post Types criados</li>
    <li><i data-lucide="check"></i> Usuários importados</li>
    <li><i data-lucide="check"></i> Meta boxes configurados</li>
</ul>
```

### Card com Ícone

```html
<div class="eau-card">
    <i data-lucide="database" style="width: 32px; height: 32px; color: #2271b1;"></i>
    <h3>Total de Posts</h3>
    <p class="count">1,234</p>
</div>
```

## 🚀 Performance

- ✅ Carregado via CDN (cache global)
- ✅ SVG inline (não há requisições extras)
- ✅ Apenas ~50KB minificado
- ✅ Tree-shaking automático (apenas ícones usados)

## 🔄 Atualização Dinâmica

Se você adicionar ícones via JavaScript/AJAX, chame:

```javascript
lucide.createIcons();
```

Isso renderizará todos os novos elementos `data-lucide` adicionados.

## 📝 Notas

- Os ícones são renderizados como SVG inline
- São acessíveis (suportam screen readers)
- Escaláveis sem perda de qualidade
- Compatíveis com todos os navegadores modernos
