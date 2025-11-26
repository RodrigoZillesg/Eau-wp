# Eau System - Relacionamentos de Dados

## 📚 Visão Geral

Este documento mapeia todos os relacionamentos entre entidades do sistema Eau. É **crítico** manter este documento atualizado à medida que novos relacionamentos são descobertos ou criados.

**Última Atualização**: 2025-01-22
**Versão**: 1.0.0

---

## 👤 Users (Usuários)

### Tipo de Entidade
- **Tipo WordPress**: `WP_User`
- **Tabela**: `wp_users` + `wp_usermeta`

### Metadados Principais

| Meta Key | Tipo | Descrição | Valores Possíveis |
|----------|------|-----------|-------------------|
| `mem_userid` | string | **ID único do membro** - Usado para relacionamentos | Ex: "MEM001", "MEM123" |
| `mem_type` | string | Tipo de usuário no sistema | `superAdmin`, `Admin`, `institutionAdmin`, `member` |
| `mem_status` | string | Status de ativação do membro | `active`, `inactive` |
| `mem_membercompanyname` | string | Company ID da instituição (DEPRECATED para institutionAdmin) | Ex: "COMP123" |
| `mem_phone` | string | Telefone do membro | - |
| `mem_address` | string | Endereço completo | - |
| `mem_city` | string | Cidade | - |
| `mem_state` | string | Estado/Província | - |
| `mem_postcode` | string | CEP/Código Postal | - |
| `mem_country` | string | País | - |
| `first_name` | string | Primeiro nome (WordPress core) | - |
| `last_name` | string | Sobrenome (WordPress core) | - |

### Tipos de Usuário (`mem_type`)

#### 1. **superAdmin**
- Acesso total ao sistema
- Vê todas as instituições
- Vê todos os membros
- Vê todas as atividades

#### 2. **Admin**
- Acesso total ao sistema
- Vê todas as instituições
- Vê todos os membros
- Vê todas as atividades

#### 3. **institutionAdmin**
- Acesso limitado à(s) sua(s) instituição(ões)
- Vê apenas membros das suas instituições
- Vê apenas atividades das suas instituições
- **Pode administrar múltiplas instituições**

#### 4. **member**
- Acesso básico
- Vê apenas seus próprios dados

---

## 🏢 Institutions (Instituições)

### Tipo de Entidade
- **Tipo WordPress**: Custom Post Type `institutions`
- **Tabela**: `wp_posts` + `wp_postmeta`

### Metadados Principais

| Meta Key | Tipo | Descrição | Exemplo |
|----------|------|-----------|---------|
| `ins_company_id` | string | ID único da empresa/instituição | "COMP123" |
| `ins_company_primary_contact` | string | **ID do usuário administrador** (`mem_userid`) | "MEM001" |
| `ins_type` | string | Tipo de membership da instituição | Ex: "Corporate", "Individual" |
| `ins_member_company_name` | string | Nome da empresa (parece duplicado do post_title) | - |

---

## 🔗 Relacionamento: User ↔ Institution

### ✅ Relacionamento CORRETO (Institution Admin)

**Como Funciona**:
1. User tem `mem_userid` único (ex: "MEM001")
2. Institution tem `ins_company_primary_contact` = `mem_userid`
3. **Um usuário pode administrar MÚLTIPLAS instituições**

**Exemplo**:
```
User ID: 42
├─ mem_userid: "MEM001"
├─ mem_type: "institutionAdmin"

Institution ID: 100
├─ post_title: "University of Example"
├─ ins_company_id: "COMP123"
└─ ins_company_primary_contact: "MEM001" ← Relacionamento

Institution ID: 101
├─ post_title: "College of Sample"
├─ ins_company_id: "COMP456"
└─ ins_company_primary_contact: "MEM001" ← Mesmo admin!
```

**Query para Buscar Instituições de um Admin**:
```sql
SELECT p.ID, p.post_title, pm_company.meta_value as company_id
FROM wp_posts p
INNER JOIN wp_postmeta pm_contact ON p.ID = pm_contact.post_id
LEFT JOIN wp_postmeta pm_company ON p.ID = pm_company.post_id AND pm_company.meta_key = 'ins_company_id'
WHERE p.post_type = 'institutions'
AND p.post_status = 'publish'
AND pm_contact.meta_key = 'ins_company_primary_contact'
AND pm_contact.meta_value = 'MEM001'
```

**Query Reversa - Buscar Admin de uma Instituição**:
```sql
SELECT u.ID, u.display_name, um.meta_value as mem_userid
FROM wp_users u
INNER JOIN wp_usermeta um ON u.ID = um.user_id
INNER JOIN wp_postmeta pm ON pm.meta_value = um.meta_value
WHERE um.meta_key = 'mem_userid'
AND pm.meta_key = 'ins_company_primary_contact'
AND pm.post_id = 100 -- ID da instituição
LIMIT 1
```

---

### ⚠️ Relacionamento ANTIGO (Member → Institution)

**DEPRECATED para institutionAdmin, mas ainda usado para members regulares**

**Como Funciona**:
1. User tem `mem_membercompanyname` = company_id
2. Institution tem `ins_company_id` = company_id
3. Match: `mem_membercompanyname` = `ins_company_id`

**Exemplo**:
```
User (Member):
├─ mem_userid: "MEM050"
├─ mem_type: "member"
└─ mem_membercompanyname: "COMP123"

Institution:
├─ ins_company_id: "COMP123" ← Match!
└─ post_title: "University of Example"
```

**Uso Atual**:
- ✅ Membros regulares (vinculados a UMA instituição)
- ❌ Institution Admins (devem usar `ins_company_primary_contact`)

---

## 📝 Activities (CPD Activities)

### Tipo de Entidade
- **Tipo WordPress**: Custom Post Type `activitie`
- **Tabela**: `wp_posts` + `wp_postmeta`

### Metadados Principais

| Meta Key | Tipo | Descrição |
|----------|------|-----------|
| `act_verified` | string | Status de verificação | `1` = verificado, vazio/`0` = pendente |
| `act_hours_of_pd_anything_below_60_minutes_can_be_entered_as_a_decimal_e_g_30_mins_0_5` | decimal | Horas de PD (pontos) |

### Relacionamento com User

**Como Funciona**:
- Activity possui meta `act_user_id` (string)
- User possui meta `act_user_id` (string)
- **Relação**: `activitie.act_user_id` = `user.act_user_id`

⚠️ **IMPORTANTE**: NÃO usar `post_author`! O relacionamento é via metadados `act_user_id`.

**Como Institution Admin vê Activities**:

Institution Admins podem ver activities dos membros das suas instituições. O filtro funciona assim:

1. Admin gerencia instituições (via `ins_company_primary_contact`)
2. Cada instituição tem um `company_id` (meta `ins_companyid`)
3. Membros pertencem a instituições (via `mem_membercompanyname` = `company_id`)
4. Cada membro tem um `act_user_id` único
5. Activities pertencem a membros (via `act_user_id`)

**Implementação no Activities Management**:

```sql
-- 1. Buscar company_ids das instituições do admin
SELECT pm.meta_value as company_id
FROM wp_postmeta pm
WHERE pm.post_id IN (100, 101) -- IDs das instituições do admin
AND pm.meta_key = 'ins_companyid'

-- 2. Buscar act_user_id dos membros dessas instituições
SELECT um.meta_value as act_user_id
FROM wp_users u
INNER JOIN wp_usermeta um ON u.ID = um.user_id
INNER JOIN wp_usermeta um2 ON u.ID = um2.user_id
WHERE um2.meta_key = 'mem_membercompanyname'
AND um2.meta_value IN ('COMP123', 'COMP456') -- company_ids obtidos acima
AND um.meta_key = 'act_user_id'

-- 3. Filtrar activities desses membros
SELECT p.ID, p.post_title, p.post_date, pm.meta_value as act_user_id
FROM wp_posts p
INNER JOIN wp_postmeta pm ON p.ID = pm.post_id
WHERE p.post_type = 'activitie'
AND p.post_status = 'publish'
AND pm.meta_key = 'act_user_id'
AND pm.meta_value IN ('MEM001', 'MEM002', 'MEM003') -- act_user_ids obtidos acima
```

**Query Otimizada (stats)**:

```sql
-- Total de activities das instituições do admin
SELECT COUNT(DISTINCT p.ID)
FROM wp_posts p
INNER JOIN wp_postmeta pm_user ON p.ID = pm_user.post_id
WHERE p.post_type = 'activitie'
AND p.post_status = 'publish'
AND pm_user.meta_key = 'act_user_id'
AND pm_user.meta_value IN ('MEM001', 'MEM002', 'MEM003')
```

---

## 📅 Events

### Tipo de Entidade
- **Tipo WordPress**: Custom Post Type `events`
- **Tabela**: `wp_posts` + `wp_postmeta`

### Metadados Principais

| Meta Key | Tipo | Descrição |
|----------|------|-----------|
| `event_date` | date | Data do evento (Y-m-d) |

### Relacionamento
- Evento não tem relacionamento direto com User ou Institution (por enquanto)
- Filtro apenas por data

---

## 🔄 Relacionamento: User ↔ User (Duplicates)

### Tabelas Customizadas

#### `wp_eau_duplicate_scans`
Registra execuções de scan de duplicatas

| Coluna | Tipo | Descrição |
|--------|------|-----------|
| `scan_id` | bigint | ID do scan |
| `scan_date` | datetime | Data/hora do scan |
| `scan_status` | varchar(20) | `in_progress`, `completed`, `cancelled` |
| `total_users` | int | Total de usuários analisados |
| `duplicates_found` | int | Total de duplicatas encontradas |
| `created_by_user_id` | bigint | ID do admin que iniciou |

#### `wp_eau_duplicate_pairs`
Pares de usuários potencialmente duplicados

| Coluna | Tipo | Descrição |
|--------|------|-----------|
| `pair_id` | bigint | ID do par |
| `scan_id` | bigint | FK → `eau_duplicate_scans` |
| `user_id_1` | bigint | FK → `wp_users` |
| `user_id_2` | bigint | FK → `wp_users` |
| `similarity_score` | decimal(5,2) | Score 0-100 |
| `match_details` | longtext | JSON com detalhes |
| `pair_status` | varchar(20) | `pending`, `merged`, `dismissed`, `ignored` |
| `reviewed_by_user_id` | bigint | ID do admin que revisou |
| `merged_into_user_id` | bigint | ID do usuário mantido (se merged) |

#### `wp_eau_duplicate_exclusions`
Pares que não devem ser analisados novamente

| Coluna | Tipo | Descrição |
|--------|------|-----------|
| `user_id_1` | bigint | FK → `wp_users` |
| `user_id_2` | bigint | FK → `wp_users` |
| `exclusion_type` | varchar(20) | `not_duplicate`, `never_analyze` |
| `created_by_user_id` | bigint | ID do admin |

---

## 🎯 Filtros por Instituição

### Para Institution Admin

**Objetivo**: Mostrar apenas dados das instituições que o admin gerencia

#### 1. Filtrar Membros (Users)

**Método Antigo (INCORRETO)**:
```sql
WHERE mem_membercompanyname = 'COMP123'
```

**Método Novo (CORRETO)**:
```sql
-- 1. Pega instituições do admin
SELECT inst.ID, pm_company.meta_value as company_id
FROM wp_posts inst
INNER JOIN wp_postmeta pm_contact ON inst.ID = pm_contact.post_id
LEFT JOIN wp_postmeta pm_company ON inst.ID = pm_company.post_id AND pm_company.meta_key = 'ins_company_id'
WHERE inst.post_type = 'institutions'
AND pm_contact.meta_key = 'ins_company_primary_contact'
AND pm_contact.meta_value = 'MEM001' -- mem_userid do admin

-- 2. Filtra usuários dessas instituições
SELECT u.ID, u.display_name
FROM wp_users u
INNER JOIN wp_usermeta um ON u.ID = um.user_id
WHERE um.meta_key = 'mem_membercompanyname'
AND um.meta_value IN ('COMP123', 'COMP456') -- company_ids das instituições
```

#### 2. Filtrar Activities

```sql
-- Activities de membros das instituições do admin
SELECT p.ID, p.post_title
FROM wp_posts p
WHERE p.post_type = 'activitie'
AND p.post_status = 'publish'
AND p.post_author IN (
    -- IDs dos membros das instituições
    SELECT u.ID
    FROM wp_users u
    INNER JOIN wp_usermeta um ON u.ID = um.user_id
    WHERE um.meta_key = 'mem_membercompanyname'
    AND um.meta_value IN ('COMP123', 'COMP456')
)
```

---

## 🔐 Regras de Acesso

### Super Admin / Admin
- ✅ Acesso total a todas as instituições
- ✅ Vê todos os membros
- ✅ Vê todas as activities
- ✅ Pode editar/deletar qualquer registro

### Institution Admin
- ✅ Acesso apenas às suas instituições (via `ins_company_primary_contact`)
- ✅ Vê apenas membros das suas instituições (via `mem_membercompanyname`)
- ✅ Vê apenas activities dos membros das suas instituições
- ✅ Pode gerenciar múltiplas instituições
- ❌ Não pode acessar outras instituições

### Member
- ✅ Acesso apenas aos próprios dados
- ❌ Não vê outros membros
- ❌ Não tem acesso a dashboards administrativos

---

## 📊 Casos de Uso Comuns

### Caso 1: Admin gerencia 2 instituições

```
User ID: 42
├─ mem_userid: "MEM001"
├─ mem_type: "institutionAdmin"

Institution A (ID: 100):
├─ ins_company_id: "COMP123"
└─ ins_company_primary_contact: "MEM001"

Institution B (ID: 101):
├─ ins_company_id: "COMP456"
└─ ins_company_primary_contact: "MEM001"

Membros de A:
├─ User 50: mem_membercompanyname = "COMP123"
├─ User 51: mem_membercompanyname = "COMP123"

Membros de B:
├─ User 60: mem_membercompanyname = "COMP456"
└─ User 61: mem_membercompanyname = "COMP456"

Dashboard do Admin (ID 42):
├─ Total Members: 4 (50, 51, 60, 61)
├─ Institutions: "University A, College B"
└─ Activities: Todas de users 50, 51, 60, 61
```

### Caso 2: Membro pertence a uma instituição

```
User ID: 50
├─ mem_userid: "MEM050"
├─ mem_type: "member"
└─ mem_membercompanyname: "COMP123"

Institution (ID: 100):
├─ ins_company_id: "COMP123" ← Match!
├─ post_title: "University A"
└─ ins_company_primary_contact: "MEM001" (outro usuário)

Relacionamento:
└─ User 50 é MEMBRO da University A
    Admin da University A é User 42 (MEM001)
```

---

## 🚨 Pontos de Atenção

### 1. Relacionamento Duplo
- **Institution Admin** usa `ins_company_primary_contact` = `mem_userid`
- **Member** usa `mem_membercompanyname` = `ins_company_id`
- **NÃO confundir os dois!**

### 2. Múltiplas Instituições
- Um `institutionAdmin` pode ter `ins_company_primary_contact` em VÁRIAS instituições
- Sempre buscar TODAS as instituições ao filtrar dados

### 3. Company ID vs User ID
- `ins_company_id`: ID da empresa/instituição (ex: "COMP123")
- `mem_userid`: ID único do membro (ex: "MEM001")
- **São campos diferentes com propósitos diferentes!**

### 4. Queries Performance
- Sempre usar INNER JOIN para garantir integridade
- Evitar subqueries quando possível
- Criar índices em:
  - `wp_usermeta.meta_key` + `meta_value`
  - `wp_postmeta.meta_key` + `meta_value`

---

## 📝 Changelog

### v1.2.0 - 2025-01-22
- **CORREÇÃO CRÍTICA**: Relacionamento de Activities é via `act_user_id` (meta), NÃO `post_author`
- Activities relacionam com Users via: `activitie.act_user_id` = `user.act_user_id`
- Atualização de todas as queries para usar o relacionamento correto
- Exemplos práticos de filtros em cascata (Institution → Member → Activity)

### v1.1.0 - 2025-01-22
- Atualização da seção Activities com implementação via `post_author` (INCORRETO - veja v1.2.0)
- Documentação detalhada do relacionamento Activity → User
- Queries otimizadas para filtro de Institution Admin

### v1.0.0 - 2025-01-22
- Documento inicial criado
- Mapeamento de User ↔ Institution (correto)
- Documentação de Activities, Events, Duplicates
- Casos de uso e queries de exemplo

---

**Mantenha este documento atualizado sempre que descobrir novos relacionamentos!**
