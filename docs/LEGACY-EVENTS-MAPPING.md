# Mapeamento de Campos - Sistema Legado → Eau Events

## Visão Geral

Este documento mapeia os campos identificados no sistema legado English Australia para o sistema Eau Events.

**Sistema Legado:** https://www.englishaustralia.com.au/administration/eventsadmin/events
**Data da Análise:** 2025-12-18

---

## Campos da Lista de Eventos (Sistema Legado)

| Campo Legado | Tipo | Descrição |
|--------------|------|-----------|
| Event Title | string | Título do evento |
| Start Date | date | Data de início (formato: "11th Jun 26") |
| Periods | number | Número de períodos |
| Status | enum | Status do evento (Active, Expired, etc.) |
| Places | number | Número de vagas |
| Registrations to date | number | Total de inscrições |

---

## Categorias do Sistema Legado

- **Events** (categoria raiz)
  - English Australia Events
  - Webinars
  - Sector events
  - SIG Events
- **Home Page Events**
- **Hidden From Public**
- **All**
- **Uncategorised**

---

## Resumo de Registros (Dashboard do Evento)

| Campo | Descrição |
|-------|-----------|
| Individual | Inscrições individuais |
| Group | Inscrições em grupo |
| Total | Total de inscrições |
| Paying | Inscrições pagas |
| Free | Inscrições gratuitas |
| Cancelled | Inscrições canceladas |
| Refunded | Inscrições reembolsadas |
| Total Revenue | Receita total |

---

## Estrutura do Sistema Legado (Association Online/ASI)

### 6 Abas Principais do Evento

| # | Aba | Descrição |
|---|-----|-----------|
| 1 | **Summary** | Dashboard com estatísticas de registros e receita |
| 2 | **Registrations** | Lista de inscrições do evento |
| 3 | **Attendance** | Controle de presença |
| 4 | **Seating** | Configuração de assentos |
| 5 | **Details** | Configurações detalhadas (9 sub-abas) |
| 6 | **Emails** | Configuração de emails automáticos |

### Campos da Aba Summary

| Campo | Descrição |
|-------|-----------|
| Individual | Inscrições individuais |
| Group | Inscrições em grupo (X in Y groups) |
| Total | Total de inscrições |
| **Revenue - Members** | |
| Paying | Registros pagos (count + amount) |
| Free | Registros gratuitos |
| Cancelled | Cancelados |
| Refunded | Reembolsados |
| **Revenue - Non-members** | |
| Paying/Free/Cancelled/Refunded | Mesmos campos para não-membros |
| Total Revenue | Receita total |

---

## ⚠️ 9 Sub-abas de Details (PROPOSTA)

> **NOTA:** As sub-abas exatas precisam ser verificadas manualmente no sistema legado. A proposta abaixo é baseada em padrões comuns do sistema ASI/Association Online.

Baseado em sistemas similares, as 9 sub-abas provavelmente incluem:

### 1. **Basic Info** - Informações Básicas
| Campo | Tipo | Meta Key Eau |
|-------|------|--------------|
| Event Title | text | `post_title` |
| Short Description | text | `evt_short_description` |
| Full Description | wysiwyg | `evt_description` |
| Event Code | text | `evt_code` |
| Event Type | select | `evt_type` |

### 2. **Dates & Times** - Datas e Horários
| Campo | Tipo | Meta Key Eau |
|-------|------|--------------|
| Start Date | date | `evt_start_date` |
| End Date | date | `evt_end_date` |
| Start Time | time | `evt_start_time` |
| End Time | time | `evt_end_time` |
| Timezone | select | `evt_timezone` |
| All Day Event | checkbox | `evt_all_day` |

### 3. **Location** - Localização
| Campo | Tipo | Meta Key Eau |
|-------|------|--------------|
| Venue Name | text | `evt_location` |
| Address | text | `evt_address` |
| City | text | `evt_city` |
| State | text | `evt_state` |
| Postcode | text | `evt_postcode` |
| Country | select | `evt_country` |
| Online URL | url | `evt_online_url` |
| Online Platform | select | `evt_online_platform` |

### 4. **Registration** - Configurações de Registro
| Campo | Tipo | Meta Key Eau |
|-------|------|--------------|
| Registration Start | datetime | `evt_registration_start` |
| Registration End | datetime | `evt_registration_end` |
| Allow Group Registration | checkbox | `evt_allow_group` |
| Waitlist Enabled | checkbox | `evt_waitlist_enabled` |
| Registration Enabled | checkbox | `evt_registration_enabled` |

### 5. **Capacity** - Capacidade
| Campo | Tipo | Meta Key Eau |
|-------|------|--------------|
| Maximum Capacity | number | `evt_capacity` |
| Minimum Registrations | number | `evt_min_registrations` |
| Reserved Places | number | `evt_reserved_places` |

### 6. **Pricing** - Preços
| Campo | Tipo | Meta Key Eau |
|-------|------|--------------|
| Member Price | decimal | `evt_member_price` |
| Non-Member Price | decimal | `evt_price` |
| Early Bird Price | decimal | `evt_early_bird_price` |
| Early Bird End Date | date | `evt_early_bird_end_date` |
| Group Discount | decimal | `evt_group_discount` |
| Tax Included | checkbox | `evt_tax_included` |

### 7. **Categories** - Categorização
| Campo | Tipo | Meta Key Eau |
|-------|------|--------------|
| Event Category | select/multi | `evt_category` |
| Tags | multi-select | `evt_tags` |
| Target Audience | multi-select | `evt_audience` |

### 8. **CPD/Accreditation** - Pontos CPD
| Campo | Tipo | Meta Key Eau |
|-------|------|--------------|
| CPD Points | number | `evt_cpd_points` |
| CPD Category | select | `evt_cpd_category` |
| CPD Subcategory | select | `evt_cpd_subcategory` |
| Accreditation Body | text | `evt_accreditation_body` |

### 9. **Settings** - Configurações Avançadas
| Campo | Tipo | Meta Key Eau |
|-------|------|--------------|
| Status | select | `evt_status` |
| Visibility | select | `evt_visibility` |
| Featured | checkbox | `evt_featured` |
| Allow Comments | checkbox | `evt_allow_comments` |
| Periods | select | `evt_periods` |

---

## 🔍 Como Verificar os Campos Exatos

### Opção 1: Verificação Manual (Recomendado)
1. Acessar: https://www.englishaustralia.com.au/administration/eventsadmin/events
2. Editar qualquer evento existente
3. Clicar na aba "Details"
4. Documentar os nomes das 9 sub-abas
5. Para cada sub-aba, listar os campos disponíveis

### Opção 2: Export CSV
1. No menu lateral: **Import/Export**
2. Exportar eventos para CSV
3. As colunas do CSV representam os campos disponíveis
4. Mapear cada coluna para o campo correspondente no Eau Events

### Opção 3: Events Import (do próprio sistema)
1. No menu lateral: **Events Import**
2. Verificar o formato de importação esperado
3. Usar o template como referência para os campos

---

## Mapeamento Proposto: Legado → Eau Events

### Campos Principais

| Campo Legado | Campo Eau Events | Meta Key | Observações |
|--------------|------------------|----------|-------------|
| Event Title | Título | `post_title` | Post title do CPT |
| Start Date | Data Início | `evt_start_date` | Formato Y-m-d |
| End Date | Data Fim | `evt_end_date` | Formato Y-m-d |
| Start Time | Hora Início | `evt_start_time` | Formato H:i |
| End Time | Hora Fim | `evt_end_time` | Formato H:i |
| Description | Descrição | `evt_description` | HTML permitido |
| Short Description | Resumo | `evt_short_description` | Texto curto |
| Location | Localização | `evt_location` | Nome do local |
| Address | Endereço | `evt_address` | Endereço completo |
| City | Cidade | `evt_city` | - |
| State | Estado | `evt_state` | - |
| Postcode | CEP | `evt_postcode` | - |
| Country | País | `evt_country` | - |
| Event Type | Tipo | `evt_type` | in_person/online/hybrid |
| Online URL | URL Online | `evt_online_url` | Para eventos online |
| Capacity | Capacidade | `evt_capacity` | Número máximo |
| Price | Preço | `evt_price` | Decimal |
| Member Price | Preço Membro | `evt_member_price` | Decimal |
| Early Bird Price | Preço Early Bird | `evt_early_bird_price` | Decimal |
| Early Bird End | Fim Early Bird | `evt_early_bird_end_date` | Data |
| Registration Start | Início Registro | `evt_registration_start` | Data |
| Registration End | Fim Registro | `evt_registration_end` | Data |
| Status | Status | `evt_status` | draft/published/cancelled |
| Featured | Destaque | `evt_featured` | 0 ou 1 |
| Periods | Períodos | `evt_periods` | Campo específico EA |
| CPD Points | Pontos CPD | `evt_cpd_points` | Número |
| CPD Category | Categoria CPD | `evt_cpd_category` | - |

### Status Mapping

| Status Legado | Status Eau Events |
|---------------|-------------------|
| Active | published |
| Expired | cancelled |
| Draft | draft |
| Not Public | draft |

---

## Estrutura do Eau Events (Para Referência)

### Custom Post Type: `eau_event`

```php
// Campos do evento no Eau System
$event_fields = array(
    // Informações Básicas
    'evt_title',
    'evt_short_description',
    'evt_description',
    'evt_featured_image',

    // Data e Hora
    'evt_start_date',
    'evt_end_date',
    'evt_start_time',
    'evt_end_time',
    'evt_timezone',

    // Localização
    'evt_type',           // in_person, online, hybrid
    'evt_location',
    'evt_address',
    'evt_city',
    'evt_state',
    'evt_postcode',
    'evt_country',
    'evt_online_url',
    'evt_online_platform',

    // Capacidade e Preços
    'evt_capacity',
    'evt_price',
    'evt_member_price',
    'evt_early_bird_price',
    'evt_early_bird_end_date',

    // Registro
    'evt_registration_start',
    'evt_registration_end',
    'evt_registration_enabled',

    // CPD
    'evt_cpd_points',
    'evt_cpd_category',
    'evt_cpd_subcategory',

    // Configurações
    'evt_status',         // draft, published, cancelled
    'evt_featured',
    'evt_visibility',     // public, members_only

    // Períodos (específico EA)
    'evt_periods',
);
```

### Custom Post Type: `eau_event_reg` (Registros)

```php
// Campos de registro
$registration_fields = array(
    'reg_event_id',
    'reg_user_id',
    'reg_type',           // individual, group
    'reg_status',         // pending, confirmed, cancelled, refunded
    'reg_payment_status', // pending, paid, refunded
    'reg_amount',
    'reg_date',
    // Dados do participante...
);
```

---

## Próximos Passos para Importação

### Opção 1: Export CSV do Sistema Legado

1. Verificar se o sistema legado tem opção de Export em: `/administration/eventsadmin/events/import` (Import/Export)
2. Exportar eventos para CSV
3. Mapear campos CSV para campos Eau Events
4. Criar script de importação similar ao Membership Importer

### Opção 2: Scraping Manual

1. Navegar manualmente pelo sistema legado
2. Documentar os nomes exatos das 9 sub-abas de Details
3. Identificar campos importantes em cada aba
4. Atualizar este documento com o mapeamento completo

### Opção 3: API (se disponível)

1. Verificar se o sistema legado tem API
2. Documentar endpoints disponíveis
3. Criar integração direta

---

## Exemplo de Código de Importação

```php
// Exemplo de como criar um evento a partir de dados importados
function eau_import_event($legacy_data) {
    // Prepara dados
    $event_data = array(
        'post_type'   => 'eau_event',
        'post_title'  => sanitize_text_field($legacy_data['title']),
        'post_status' => 'publish',
        'post_author' => get_current_user_id(),
    );

    // Insere evento
    $event_id = wp_insert_post($event_data);

    if (!is_wp_error($event_id)) {
        // Define meta fields
        update_post_meta($event_id, 'evt_start_date', $legacy_data['start_date']);
        update_post_meta($event_id, 'evt_end_date', $legacy_data['end_date']);
        update_post_meta($event_id, 'evt_capacity', $legacy_data['capacity']);
        update_post_meta($event_id, 'evt_price', $legacy_data['price']);
        // ... outros campos
    }

    return $event_id;
}
```

---

**Última Atualização:** 2025-12-18
