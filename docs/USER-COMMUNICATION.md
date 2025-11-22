# User Communication - Padrão Oficial

## 📋 Visão Geral

Este documento define o padrão oficial para **todas as comunicações com o usuário** no plugin Eau System.

**IMPORTANTE**: Nunca use `alert()` ou `confirm()` nativos do JavaScript.

---

## 🎯 Tipos de Comunicação

### 1. Notificações (Toast)

Use **Toast** para feedback de ações:
- ✅ Sucesso de operações
- ❌ Erros de operações
- ⚠️ Avisos
- ℹ️ Informações

### 2. Confirmações (Modal)

Use **Confirm Modal** para:
- 🗑️ Ações destrutivas (delete)
- ⚠️ Ações que não podem ser desfeitas
- ❓ Decisões importantes

### 3. Detalhes (Modal Regular)

Use **Modal Regular** para:
- 👁️ Visualizar detalhes
- ✏️ Editar informações
- ➕ Criar novos itens

---

## 🍞 Toast Notifications

### API JavaScript

```javascript
// Success
EauNotifications.success('Title', 'Message', duration);

// Error
EauNotifications.error('Title', 'Message', duration);

// Warning
EauNotifications.warning('Title', 'Message', duration);

// Info
EauNotifications.info('Title', 'Message', duration);

// Generic (com type customizado)
EauNotifications.toast('success', 'Title', 'Message', 5000);
```

### Parâmetros

- **title** (string): Título do toast **(obrigatório)**
- **message** (string): Mensagem do toast (opcional)
- **duration** (number): Duração em ms (padrão: 5000)

### Exemplos Práticos

```javascript
// Sucesso ao salvar
EauNotifications.success('Saved!', 'Member updated successfully');

// Erro ao deletar
EauNotifications.error('Error', 'Failed to delete member');

// Aviso de validação
EauNotifications.warning('Validation Error', 'Please fill all required fields');

// Informação geral
EauNotifications.info('Info', 'This feature is in beta');

// Toast sem mensagem (só título)
EauNotifications.success('Deleted!');

// Toast com duração customizada (10 segundos)
EauNotifications.info('Important', 'Read this carefully', 10000);
```

### Quando Usar Cada Tipo

#### ✅ Success (Verde)
- Operação concluída com sucesso
- Item salvo/criado/atualizado
- Upload concluído
- Configuração aplicada

**Exemplos**:
- "Saved!" - "Member updated successfully"
- "Success" - "File uploaded"
- "Applied" - "Settings saved"

#### ❌ Error (Vermelho)
- Operação falhou
- Erro de rede
- Erro de validação de servidor
- Permissão negada

**Exemplos**:
- "Error" - "Failed to delete member"
- "Network Error" - "Please try again"
- "Access Denied" - "You don't have permission"

#### ⚠️ Warning (Amarelo)
- Aviso importante
- Validação de formulário
- Recurso em beta
- Limite atingido

**Exemplos**:
- "Validation Error" - "Email is required"
- "Warning" - "Maximum file size is 5MB"
- "Beta Feature" - "This feature is experimental"

#### ℹ️ Info (Azul)
- Informação geral
- Dica
- Novidade
- Status

**Exemplos**:
- "Tip" - "You can use keyboard shortcuts"
- "Info" - "New version available"
- "Processing" - "Your request is being processed"

---

## ❓ Confirm Modal

### API JavaScript

```javascript
EauNotifications.confirm({
    title: 'Confirm Title',
    message: 'Confirm message',
    type: 'danger', // danger, warning, info
    confirmText: 'Confirm',
    cancelText: 'Cancel',
    onConfirm: function() {
        // Action to execute on confirm
    },
    onCancel: function() {
        // Action to execute on cancel (optional)
    }
});
```

### Parâmetros

- **title** (string): Título do modal (default: "Are you sure?")
- **message** (string): Mensagem explicativa (default: "This action cannot be undone.")
- **type** (string): Tipo do modal - `danger`, `warning`, `info` (default: "danger")
- **confirmText** (string): Texto do botão de confirmação (default: "Confirm")
- **cancelText** (string): Texto do botão de cancelar (default: "Cancel")
- **onConfirm** (function): Callback ao confirmar **(obrigatório)**
- **onCancel** (function): Callback ao cancelar (opcional)

### Exemplos Práticos

```javascript
// Delete member (danger)
EauNotifications.confirm({
    title: 'Delete Member?',
    message: 'Are you sure you want to delete this member? This action cannot be undone.',
    type: 'danger',
    confirmText: 'Delete',
    cancelText: 'Cancel',
    onConfirm: function() {
        // Perform delete
        deleteMember(userId);
    }
});

// Archive item (warning)
EauNotifications.confirm({
    title: 'Archive Item?',
    message: 'The item will be moved to archive. You can restore it later.',
    type: 'warning',
    confirmText: 'Archive',
    cancelText: 'Cancel',
    onConfirm: function() {
        archiveItem(itemId);
    }
});

// Information confirmation (info)
EauNotifications.confirm({
    title: 'Important Notice',
    message: 'This will send an email to all members. Do you want to proceed?',
    type: 'info',
    confirmText: 'Send',
    cancelText: 'Cancel',
    onConfirm: function() {
        sendEmail();
    }
});
```

### Quando Usar Cada Tipo

#### 🔴 Danger (Vermelho)
- **Delete permanente**
- Ações destrutivas
- Remover dados
- Cancelar com perda de dados

**Ícone**: ⚠️ Alert Triangle
**Cor**: Vermelho (#dc2626)

#### 🟡 Warning (Amarelo)
- **Ações importantes mas reversíveis**
- Archive/Hide
- Mudanças significativas
- Sobrescrever dados

**Ícone**: ⚠️ Alert Circle
**Cor**: Amarelo (#f59e0b)

#### 🔵 Info (Azul)
- **Confirmações informativas**
- Enviar emails
- Publicar conteúdo
- Executar ação importante

**Ícone**: ℹ️ Info
**Cor**: Azul (#3b82f6)

---

## 🚫 O Que NÃO Fazer

### ❌ NÃO use alert() nativo
```javascript
// ERRADO
alert('Member deleted successfully');

// CORRETO
EauNotifications.success('Success', 'Member deleted successfully');
```

### ❌ NÃO use confirm() nativo
```javascript
// ERRADO
if (confirm('Delete?')) {
    deleteMember();
}

// CORRETO
EauNotifications.confirm({
    title: 'Delete Member?',
    message: 'This action cannot be undone.',
    onConfirm: function() {
        deleteMember();
    }
});
```

### ❌ NÃO use console.log() para feedback
```javascript
// ERRADO
console.log('Member saved');

// CORRETO
EauNotifications.success('Saved!', 'Member updated successfully');
```

### ❌ NÃO misture estilos
```javascript
// ERRADO - Mistura alert e toast
alert('Deleted!');
EauNotifications.success('Updated!');

// CORRETO - Usa apenas toast
EauNotifications.success('Deleted!', 'Member removed');
EauNotifications.success('Updated!', 'Changes saved');
```

---

## 📦 Estrutura dos Arquivos

### CSS
`/assets/css/eau-components.css` (linhas 1356-1568)
- Toast container e estilos
- Confirm modal estilos
- Animações

### JavaScript
`/assets/js/eau-notifications.js`
- `EauNotifications.toast()`
- `EauNotifications.success()`
- `EauNotifications.error()`
- `EauNotifications.warning()`
- `EauNotifications.info()`
- `EauNotifications.confirm()`

### Dependencies
- jQuery
- Lucide Icons (para ícones)

---

## 🎨 Design System

### Cores

| Tipo | Cor | Hex |
|------|-----|-----|
| Success | Verde | #10b981 |
| Error | Vermelho | #ef4444 |
| Warning | Amarelo | #f59e0b |
| Info | Azul | #3b82f6 |
| Danger | Vermelho | #dc2626 |

### Ícones (Lucide)

| Tipo | Ícone |
|------|-------|
| Success | check-circle |
| Error | alert-circle |
| Warning | alert-triangle |
| Info | info |
| Danger | alert-triangle |

### Animações

- **Toast**: Slide in da direita (0.3s)
- **Toast Close**: Slide out para direita (0.3s)
- **Modal**: Fade in (0.2s)

---

## 🔄 Fluxo de Exemplo Completo

```javascript
// 1. Usuário clica em Delete
$('.eau-action-delete').on('click', function() {
    const userId = $(this).data('id');

    // 2. Mostra confirm modal
    EauNotifications.confirm({
        title: 'Delete Member?',
        message: 'This action cannot be undone.',
        type: 'danger',
        confirmText: 'Delete',
        onConfirm: function() {
            // 3. Faz o AJAX delete
            $.ajax({
                url: ajaxUrl,
                data: { action: 'delete_member', user_id: userId },
                success: function(response) {
                    if (response.success) {
                        // 4. Mostra toast de sucesso
                        EauNotifications.success('Deleted!', 'Member removed successfully');
                        // 5. Recarrega tabela
                        reloadTable();
                    } else {
                        // 4. Mostra toast de erro
                        EauNotifications.error('Error', response.data.message);
                    }
                },
                error: function() {
                    // 4. Mostra toast de erro de rede
                    EauNotifications.error('Network Error', 'Please try again');
                }
            });
        }
    });
});
```

---

## 📝 Checklist de Implementação

Ao adicionar uma nova feature com feedback ao usuário:

- [ ] Identifiquei todos os pontos de comunicação com o usuário
- [ ] Removi todos os `alert()` e `confirm()` nativos
- [ ] Usei `EauNotifications.toast()` para feedbacks
- [ ] Usei `EauNotifications.confirm()` para confirmações
- [ ] Escolhi o tipo correto (success, error, warning, info, danger)
- [ ] Escrevi títulos e mensagens claras e concisas
- [ ] Testei todas as interações
- [ ] Verifiquei que os toasts desaparecem corretamente
- [ ] Verifiquei que os modais de confirmação funcionam corretamente

---

## 🔗 Referências

- Arquivo CSS: `/assets/css/eau-components.css`
- Arquivo JS: `/assets/js/eau-notifications.js`
- Exemplo de uso: `/assets/js/eau-members-management.js`

---

## 📞 Regra de Ouro

> **"Nunca use alert() ou confirm(). Sempre use Toast para feedback e Confirm Modal para confirmações."**

Se tem comunicação com o usuário, use o sistema de notificações. Sempre.
