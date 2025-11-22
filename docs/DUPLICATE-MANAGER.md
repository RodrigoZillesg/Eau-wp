# Sistema de Detecção e Merge de Duplicatas

**Versão:** 1.18.0
**Sistema:** Eau Duplicate Manager

---

## 📋 VISÃO GERAL

O Sistema de Detecção e Merge de Duplicatas é uma funcionalidade avançada que detecta automaticamente membros potencialmente duplicados através de análise inteligente de similaridade, permitindo que administradores revisem, comparem e façam merge de perfis duplicados de forma segura e eficiente.

### Características Principais

✅ **Scan Inteligente**: Analisa todos os membros comparando múltiplos campos
✅ **Score de Similaridade**: Calcula porcentagem de match (50-100%)
✅ **Comparação Visual**: Cards lado a lado mostrando diferenças
✅ **Merge Seletivo**: Escolha quais dados manter de cada membro
✅ **Sistema de Exclusões**: Marca pares como "não duplicado" ou "ignorar"
✅ **Histórico de Scans**: Mantém registro de todas as análises
✅ **Permissões**: Apenas Admin e Super Admin têm acesso

---

## 🗄️ ESTRUTURA DE BANCO DE DADOS

### Tabela: `wp_eau_duplicate_scans`

Armazena histórico de scans realizados.

```sql
CREATE TABLE wp_eau_duplicate_scans (
    scan_id bigint(20) PRIMARY KEY AUTO_INCREMENT,
    scan_date datetime NOT NULL,
    total_users_analyzed int(11) DEFAULT 0,
    duplicates_found int(11) DEFAULT 0,
    scan_status varchar(20) NOT NULL DEFAULT 'in_progress',
    scan_by_user_id bigint(20) NOT NULL
);
```

**Campos:**
- `scan_id`: ID único do scan
- `scan_date`: Data/hora do início do scan
- `total_users_analyzed`: Quantidade de usuários analisados
- `duplicates_found`: Quantidade de pares duplicados encontrados
- `scan_status`: Status (`in_progress`, `completed`, `failed`)
- `scan_by_user_id`: ID do admin que iniciou o scan

### Tabela: `wp_eau_duplicate_pairs`

Armazena pares de usuários potencialmente duplicados.

```sql
CREATE TABLE wp_eau_duplicate_pairs (
    pair_id bigint(20) PRIMARY KEY AUTO_INCREMENT,
    scan_id bigint(20) NOT NULL,
    user_id_1 bigint(20) NOT NULL,
    user_id_2 bigint(20) NOT NULL,
    similarity_score decimal(5,2) NOT NULL,
    match_details longtext,
    pair_status varchar(20) NOT NULL DEFAULT 'pending',
    reviewed_by_user_id bigint(20) DEFAULT NULL,
    reviewed_date datetime DEFAULT NULL,
    merged_into_user_id bigint(20) DEFAULT NULL
);
```

**Campos:**
- `pair_id`: ID único do par
- `scan_id`: FK para scan que encontrou este par
- `user_id_1`, `user_id_2`: IDs dos usuários duplicados
- `similarity_score`: Score de similaridade (50.00 - 100.00)
- `match_details`: JSON com detalhes completos da comparação
- `pair_status`: Status (`pending`, `merged`, `dismissed`, `ignored`)
- `reviewed_by_user_id`: Admin que revisou
- `reviewed_date`: Data da revisão
- `merged_into_user_id`: ID do usuário que permaneceu após merge

### Tabela: `wp_eau_duplicate_exclusions`

Lista de pares que não devem ser analisados.

```sql
CREATE TABLE wp_eau_duplicate_exclusions (
    exclusion_id bigint(20) PRIMARY KEY AUTO_INCREMENT,
    user_id_1 bigint(20) NOT NULL,
    user_id_2 bigint(20) NOT NULL,
    exclusion_type varchar(20) NOT NULL,
    created_by_user_id bigint(20) NOT NULL,
    created_date datetime NOT NULL,
    UNIQUE KEY user_pair (user_id_1, user_id_2)
);
```

**Campos:**
- `exclusion_id`: ID único da exclusão
- `user_id_1`, `user_id_2`: Par excluído (sempre menor ID primeiro)
- `exclusion_type`: Tipo (`not_duplicate`, `never_analyze`)
- `created_by_user_id`: Admin que criou a exclusão
- `created_date`: Data da exclusão

---

## 🧮 ALGORITMO DE DETECÇÃO

### Campos Analisados e Pesos

| Campo | Peso | Métodos de Comparação |
|-------|------|----------------------|
| `display_name` | 25% | Levenshtein + Soundex |
| `user_email` | 20% | Match exato + Domínio + Similaridade |
| `mem_phone` | 15% | Normalização + Match |
| `mem_membercompanyname` | 15% | Match exato + Levenshtein |
| `mem_postcode` | 10% | Match exato + Região |
| `mem_address` | 10% | Levenshtein |
| `mem_city` | 5% | Match exato + Levenshtein |

**Total:** 100%

### Threshold de Detecção

- **≥ 80%**: Alta probabilidade (badge vermelho)
- **50-79%**: Média probabilidade (badge amarelo)
- **< 50%**: Não aparece (muito improvável)

### Métodos de Comparação

#### 1. **Exact Match**
Comparação exata de strings (case insensitive).
```php
return strtolower($value1) === strtolower($value2) ? 1.0 : 0.0;
```

#### 2. **Levenshtein Distance**
Distância de edição entre strings.
```php
$distance = levenshtein($value1, $value2);
$similarity = 1 - ($distance / max(strlen($value1), strlen($value2)));
```

**Exemplos:**
- "John Smith" vs "Jon Smith" = 95% similar
- "Maria" vs "Mara" = 80% similar

#### 3. **Soundex (Fonético)**
Compara sons das palavras (detecta nomes que soam igual).
```php
return soundex($name1) === soundex($name2) ? 1.0 : 0.0;
```

**Exemplos:**
- "Smith" vs "Smyth" = Match
- "Stephen" vs "Steven" = Match

#### 4. **Email Domain Match**
Verifica se emails são do mesmo domínio.
```php
// Se domínios iguais: +0.2 base + similaridade do username
// Se domínios diferentes: 0.2 (baixa similaridade)
```

**Exemplos:**
- "john@company.com" vs "j.smith@company.com" = 85% (mesmo domínio, usernames similares)
- "john@company.com" vs "john@other.com" = 20% (domínios diferentes)

#### 5. **Phone Normalization**
Remove formatação e compara números.
```php
$normalized = preg_replace('/[^0-9]/', '', $phone);
```

**Exemplos:**
- "(11) 98765-4321" vs "11987654321" = 100% match
- "+55 11 9876-5432" vs "11 9876-5432" = 90% match (substring)

#### 6. **Postcode Regional Match**
Compara CEPs por região.
```php
// Match exato: 100%
// Primeiros 5 dígitos iguais: 70% (mesma região)
// Diferentes: 0%
```

### Fluxo do Algoritmo

```
1. Para cada par de usuários (user1, user2):

   2. Para cada campo configurado:
      a. Pega valor de user1 e user2
      b. Se ambos vazios: pula campo
      c. Calcula similaridade do campo (0.0 - 1.0)
      d. Se similaridade >= 0.7: adiciona tag de match
      e. Multiplica similaridade pelo peso do campo
      f. Soma ao score total

   3. Calcula score final:
      score_final = (soma_ponderada / soma_pesos) * 100

   4. Se score >= 50%:
      a. Salva par no banco
      b. Armazena detalhes em JSON
      c. Incrementa contador de duplicatas
```

---

## 🎨 INTERFACE DO USUÁRIO

### Shortcode

```php
[eau_duplicate_manager]
```

Adicione este shortcode em qualquer página para exibir o sistema.

### Layout da Página

#### 1. Header Section
- Título: "Duplicate Manager"
- Descrição: "Find and merge duplicate members using intelligent matching"

#### 2. Scan Section
- Botão "Start New Scan"
- Informações do último scan
- Barra de progresso (quando scan está rodando)

#### 3. Filters and Sort
- **Filtros:** All / High (≥80%) / Medium (50-79%)
- **Ordenação:** Similarity (High to Low) / Similarity (Low to High) / Date (Newest First)

#### 4. Duplicate Pairs List
- Cards de comparação lado a lado
- Score de similaridade com badge colorido
- Tags de campos que fazem match
- Ações: Merge / Not Duplicate / Ignore

#### 5. Pagination
- Previous / Next buttons
- Page info: "Page X of Y"

### Modal de Merge

Interface para escolher quais dados manter:

```
┌─────────────────────────────────────┐
│ Merge Members                  [X]  │
├─────────────────────────────────────┤
│                                     │
│ Display Name:                       │
│ ⦿ John Smith    ○ Jon Smith        │
│                                     │
│ Email:                              │
│ ○ john@a.com    ⦿ j.smith@b.com    │
│                                     │
│ Phone:                              │
│ ⦿ (555) 123-4567  ○ 555-123-4567   │
│                                     │
│ ⚠ Warning: This action cannot be   │
│   undone. One member will be        │
│   deleted.                          │
│                                     │
│ [Cancel]        [Confirm Merge] ✓  │
└─────────────────────────────────────┘
```

---

## 🔧 GUIA DE DESENVOLVIMENTO

### Arquivos Principais

```
eau-system/
├── includes/
│   ├── class-eau-duplicate-database.php  (Gerencia tabelas)
│   ├── class-eau-duplicate-scanner.php   (Algoritmo de detecção)
│   └── class-eau-duplicate-manager.php   (Interface/Shortcode)
├── ajax/
│   └── class-eau-duplicate-ajax.php      (Endpoints AJAX)
├── assets/
│   ├── css/
│   │   └── eau-duplicate-manager.css     (Estilos)
│   └── js/
│       └── eau-duplicate-manager.js      (Controller JS)
```

### Classe: `Eau_Duplicate_Database`

**Responsabilidade:** Gerenciar estrutura do banco de dados.

**Métodos públicos:**
```php
// Cria todas as tabelas
Eau_Duplicate_Database::create_tables();

// Verifica se tabelas existem
$exist = Eau_Duplicate_Database::tables_exist();

// Remove tabelas (desinstalação)
Eau_Duplicate_Database::drop_tables();
```

### Classe: `Eau_Duplicate_Scanner`

**Responsabilidade:** Algoritmo de detecção de duplicatas.

**Métodos públicos:**
```php
// Inicia novo scan
$scan_id = Eau_Duplicate_Scanner::start_scan($user_id);

// Compara dois usuários manualmente
$comparison = Eau_Duplicate_Scanner::compare_users($user1, $user2);
// Retorna: ['score' => 85.5, 'matches' => ['Name', 'Email'], 'details' => [...]]

// Busca último scan
$last_scan = Eau_Duplicate_Scanner::get_last_scan();

// Busca progresso de scan específico
$progress = Eau_Duplicate_Scanner::get_scan_progress($scan_id);
```

**Configuração de campos:**
```php
private static $field_weights = array(
    'display_name' => 25,
    'user_email' => 20,
    'mem_phone' => 15,
    'mem_membercompanyname' => 15,
    'mem_postcode' => 10,
    'mem_address' => 10,
    'mem_city' => 5,
);
```

### Classe: `Eau_Duplicate_Manager`

**Responsabilidade:** Renderizar interface e enfileirar assets.

**Shortcode:**
```php
Eau_Duplicate_Manager::register_shortcode();
// Registra: [eau_duplicate_manager]
```

**Assets enfileirados:**
- `eau-components.css`
- `eau-duplicate-manager.css`
- `lucide-icons.js`
- `eau-notifications.js`
- `eau-duplicate-manager.js`

### Classe: `Eau_Duplicate_Ajax`

**Responsabilidade:** Endpoints AJAX para todas as operações.

**Endpoints:**

#### 1. `eau_start_scan`
Inicia novo scan de duplicatas.
```javascript
$.ajax({
    action: 'eau_start_scan',
    nonce: eauDuplicateData.nonce
});
// Response: { success: true, data: { scan_id: 123 } }
```

#### 2. `eau_get_scan_progress`
Retorna progresso do scan atual.
```javascript
$.ajax({
    action: 'eau_get_scan_progress',
    scan_id: 123
});
// Response: { success: true, data: { scan_status, total_users_analyzed, duplicates_found } }
```

#### 3. `eau_get_duplicate_pairs`
Lista pares duplicados com paginação.
```javascript
$.ajax({
    action: 'eau_get_duplicate_pairs',
    page: 1,
    per_page: 10,
    filter: 'all',  // 'all' | 'high' | 'medium'
    sort: 'score_desc'  // 'score_desc' | 'score_asc' | 'date_desc'
});
// Response: { success: true, data: { pairs: [...], total: 45, total_pages: 5 } }
```

#### 4. `eau_merge_members`
Executa merge de dois membros.
```javascript
$.ajax({
    action: 'eau_merge_members',
    pair_id: 123,
    user_id_keep: 45,
    user_id_delete: 67,
    field_choices: {
        'display_name': 45,
        'user_email': 67,
        'mem_phone': 45
    }
});
// Response: { success: true, data: { kept_user_id: 45 } }
```

#### 5. `eau_dismiss_duplicate`
Marca par como "não duplicado".
```javascript
$.ajax({
    action: 'eau_dismiss_duplicate',
    pair_id: 123
});
// Response: { success: true }
```

#### 6. `eau_ignore_duplicate`
Marca par para nunca mais ser analisado.
```javascript
$.ajax({
    action: 'eau_ignore_duplicate',
    pair_id: 123
});
// Response: { success: true }
```

### JavaScript Controller

**Global Object:** `window.EauDuplicateManager`

**Métodos públicos:**
```javascript
// Inicializa sistema
EauDuplicateManager.init();

// Inicia novo scan
EauDuplicateManager.startScan();

// Carrega lista de duplicatas
EauDuplicateManager.loadDuplicates();

// Abre modal de merge
EauDuplicateManager.openMergeModal(pairId);

// Executa merge
EauDuplicateManager.executeMerge();

// Marca como não duplicado
EauDuplicateManager.dismissPair(pairId);

// Ignora em futuros scans
EauDuplicateManager.ignorePair(pairId);
```

---

## 🔐 SEGURANÇA

### Verificações de Permissão

Todos os endpoints verificam:
1. ✅ Nonce válido (`eau_duplicate_nonce`)
2. ✅ Usuário logado
3. ✅ Capability `manage_options` (Admin/Super Admin)

```php
check_ajax_referer('eau_duplicate_nonce', 'nonce');

if (!current_user_can('manage_options')) {
    wp_send_json_error(array('message' => 'Insufficient permissions'));
}
```

### Sanitização de Dados

Todos os inputs são sanitizados:
```php
$pair_id = intval($_POST['pair_id']);
$filter = sanitize_text_field($_POST['filter']);
$field_choices = array_map('intval', $_POST['field_choices']);
```

### Validações no Merge

Antes de executar merge:
1. ✅ Verifica se ambos usuários existem
2. ✅ Verifica se não foram deletados
3. ✅ Verifica se email não está em uso (ao mudar email)
4. ✅ Transfere posts relacionados antes de deletar

### Proteção contra XSS

Todo output HTML usa escape:
```javascript
escapeHtml: function(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return String(text).replace(/[&<>"']/g, m => map[m]);
}
```

---

## 🎯 FLUXOS DE TRABALHO

### 1. Scan de Duplicatas

```
Admin clica "Start New Scan"
    ↓
JavaScript envia AJAX: eau_start_scan
    ↓
Backend cria registro em eau_duplicate_scans
    ↓
Backend busca todos os usuários
    ↓
Para cada par de usuários:
    - Verifica se está em exclusões
    - Se não: compara usuários
    - Se score ≥ 50%: salva em eau_duplicate_pairs
    ↓
Atualiza scan_status = 'completed'
    ↓
Frontend atualiza lista de duplicatas
```

### 2. Merge de Membros

```
Admin clica "Merge" no card
    ↓
Modal abre mostrando campos lado a lado
    ↓
Admin seleciona dados de cada campo
    ↓
Admin clica "Confirm Merge"
    ↓
Confirm modal pede confirmação final
    ↓
JavaScript determina user_id_keep e user_id_delete
    ↓
AJAX: eau_merge_members
    ↓
Backend:
    1. Atualiza campos do user_id_keep
    2. Transfere posts relacionados
    3. Deleta user_id_delete
    4. Atualiza pair_status = 'merged'
    ↓
Toast de sucesso
    ↓
Remove card da lista
```

### 3. Marcar como "Não Duplicado"

```
Admin clica "Not Duplicate"
    ↓
Confirm modal pede confirmação
    ↓
AJAX: eau_dismiss_duplicate
    ↓
Backend:
    1. Atualiza pair_status = 'dismissed'
    2. Cria registro em eau_duplicate_exclusions (type='not_duplicate')
    ↓
Remove card da lista
    ↓
Em futuros scans: este par não aparece mais
```

### 4. Ignorar Par

```
Admin clica "Ignore"
    ↓
Confirm modal explica que nunca mais será analisado
    ↓
AJAX: eau_ignore_duplicate
    ↓
Backend:
    1. Atualiza pair_status = 'ignored'
    2. Cria registro em eau_duplicate_exclusions (type='never_analyze')
    ↓
Remove card da lista
    ↓
Em futuros scans: este par é pulado na análise
```

---

## ⚡ PERFORMANCE

### Otimizações Implementadas

1. **Scan em Background**
   - Não bloqueia interface
   - Polling a cada 2 segundos
   - Timeout seguro

2. **Exclusões em Memória**
   - Carrega todas exclusões uma vez
   - Armazena em array associativo
   - Lookup O(1) por par

3. **Paginação**
   - 10 pares por página
   - Queries com LIMIT/OFFSET
   - Reduz carga no frontend

4. **Índices no Banco**
   - `user_id_1`, `user_id_2`: busca por usuário
   - `pair_status`: filtragem rápida
   - `similarity_score`: ordenação eficiente
   - `user_pair (unique)`: previne duplicatas em exclusões

### Considerações de Escala

**Sites com muitos usuários:**

- 100 usuários: ~5.000 comparações (rápido)
- 500 usuários: ~125.000 comparações (médio, ~30s)
- 1.000 usuários: ~500.000 comparações (lento, ~2min)
- 5.000+ usuários: Considerar chunk processing

**Melhorias Futuras (se necessário):**
- Processar em chunks (100 usuários por vez)
- Background processing com WP Cron
- Cache de meta fields durante scan
- Paralelização com workers

---

## 🧪 TESTANDO O SISTEMA

### Criar Dados de Teste

```php
// Criar 2 usuários similares para teste
$user1 = wp_create_user('john.smith', 'password123', 'john@company.com');
update_user_meta($user1, 'mem_phone', '(11) 98765-4321');
update_user_meta($user1, 'mem_city', 'São Paulo');

$user2 = wp_create_user('jon.smith', 'password456', 'j.smith@company.com');
update_user_meta($user2, 'mem_phone', '11 9876-5432');
update_user_meta($user2, 'mem_city', 'São Paulo');

// Testar comparação manual
$comparison = \EauSystem\Eau_Duplicate_Scanner::compare_users(
    get_user_by('id', $user1),
    get_user_by('id', $user2)
);

var_dump($comparison);
// Deve retornar score alto (~90%) e tags de match
```

### Checklist de Testes

- [ ] Scan detecta duplicatas corretamente
- [ ] Score de similaridade faz sentido
- [ ] Tags de match aparecem nos campos certos
- [ ] Modal de merge abre e fecha
- [ ] Seleção de campos funciona
- [ ] Merge executa sem erros
- [ ] Posts são transferidos
- [ ] Usuário deletado some do sistema
- [ ] "Not Duplicate" funciona
- [ ] "Ignore" funciona
- [ ] Pares excluídos não aparecem em novos scans
- [ ] Paginação funciona
- [ ] Filtros funcionam
- [ ] Ordenação funciona
- [ ] Permissões são respeitadas
- [ ] Nonce validation funciona

---

## 🐛 TROUBLESHOOTING

### Scan não inicia

**Sintoma:** Clicar em "Start New Scan" não faz nada.

**Possíveis causas:**
1. Erro de JavaScript no console
2. Nonce inválido
3. Permissões insuficientes

**Debug:**
```javascript
// Abra console do navegador (F12)
// Procure por erros em vermelho
// Verifique se eauDuplicateData está definido:
console.log(eauDuplicateData);
```

### Scan trava em "In Progress"

**Sintoma:** Progress bar não atualiza, scan nunca completa.

**Possíveis causas:**
1. Timeout do PHP
2. Erro fatal durante comparação
3. Muitos usuários

**Debug:**
```sql
-- Verifique status do scan
SELECT * FROM wp_eau_duplicate_scans ORDER BY scan_date DESC LIMIT 1;

-- Se scan_status = 'in_progress' por muito tempo:
UPDATE wp_eau_duplicate_scans
SET scan_status = 'failed'
WHERE scan_id = X;
```

**Solução:**
```php
// Aumentar timeout do PHP (wp-config.php)
set_time_limit(300); // 5 minutos
ini_set('max_execution_time', 300);
```

### Duplicatas não aparecem

**Sintoma:** Scan completa mas não mostra pares.

**Possíveis causas:**
1. Score muito baixo (< 50%)
2. Pares já foram marcados como excluídos

**Debug:**
```sql
-- Verifique se há pares no banco
SELECT * FROM wp_eau_duplicate_pairs ORDER BY pair_id DESC LIMIT 10;

-- Verifique exclusões
SELECT * FROM wp_eau_duplicate_exclusions;
```

### Merge falha

**Sintoma:** Clicar em "Confirm Merge" retorna erro.

**Possíveis causas:**
1. Usuário já foi deletado
2. Email em conflito
3. Erro ao transferir posts

**Debug:**
```javascript
// Console do navegador mostra erro
// Verifique response do AJAX:
// Network tab → eau_merge_members → Response
```

**Logs úteis:**
```php
// Adicione em class-eau-duplicate-ajax.php (temporariamente)
error_log('Merge attempt: ' . print_r($_POST, true));
```

---

## 📚 REFERÊNCIAS

### Algoritmos Utilizados

- **Levenshtein Distance**: https://en.wikipedia.org/wiki/Levenshtein_distance
- **Soundex**: https://en.wikipedia.org/wiki/Soundex
- **String Similarity**: https://en.wikipedia.org/wiki/String_similarity

### Documentação WordPress

- **wp_create_user()**: https://developer.wordpress.org/reference/functions/wp_create_user/
- **wp_delete_user()**: https://developer.wordpress.org/reference/functions/wp_delete_user/
- **update_user_meta()**: https://developer.wordpress.org/reference/functions/update_user_meta/
- **wpdb Class**: https://developer.wordpress.org/reference/classes/wpdb/

---

## 📝 CHANGELOG

### v1.18.0 (2025-01-21)

**✨ Novas Funcionalidades:**
- Sistema completo de detecção de duplicatas
- Algoritmo inteligente com 7 campos analisados
- Interface visual com comparação lado a lado
- Modal de merge com seleção de campos
- Sistema de exclusões (Not Duplicate / Ignore)
- Histórico de scans
- Paginação e filtros
- Permissões para Admin e Super Admin

**🗄️ Banco de Dados:**
- Tabela `wp_eau_duplicate_scans`
- Tabela `wp_eau_duplicate_pairs`
- Tabela `wp_eau_duplicate_exclusions`

**📦 Arquivos Criados:**
- `includes/class-eau-duplicate-database.php`
- `includes/class-eau-duplicate-scanner.php`
- `includes/class-eau-duplicate-manager.php`
- `ajax/class-eau-duplicate-ajax.php`
- `assets/css/eau-duplicate-manager.css`
- `assets/js/eau-duplicate-manager.js`
- `docs/DUPLICATE-MANAGER.md`

---

**Desenvolvido por:** Platty / Rodrigo Zillesg
**Plugin:** Eau System v1.18.0
**WordPress:** Compatible desde 5.8+
**PHP:** Requer 7.4+
