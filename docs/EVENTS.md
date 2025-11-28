# Events Module - Documentação

## Visão Geral

O módulo de Events gerencia eventos da organização English Australia, permitindo criar, editar e gerenciar eventos com integração completa ao sistema de CPD (Continuing Professional Development).

## Estrutura de Arquivos

```
includes/events/
├── class-eau-events.php                    # Bootstrap do módulo
├── config/
│   └── index.php
├── admin/
│   ├── class-eau-events-management.php     # Página de gerenciamento
│   ├── class-eau-events-management-ajax.php # AJAX handlers
│   └── tabs/
│       └── index.php
├── frontend/
│   └── index.php
├── templates/
│   └── archive-eau_event.php               # Template de listagem
└── assets/
    ├── css/
    │   └── eau-events-management.css
    └── js/
        ├── eau-events-admin.js
        └── eau-event-registrations-page.js
```

## Custom Post Type

**Post Type:** `eau_event`

### Meta Fields (prefixo: `evt_`)

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `evt_start_datetime` | datetime | Data/hora de início (formato: `Y-m-d\TH:i`) |
| `evt_end_datetime` | datetime | Data/hora de término |
| `evt_location` | string | Local do evento |
| `evt_online_url` | url | URL para evento online (Zoom, Meet, etc) |
| `evt_capacity` | integer | Capacidade máxima de participantes |
| `evt_cpd_points` | number | Pontos CPD concedidos |
| `evt_cpd_category` | integer | ID da categoria CPD (tabela `wp_eau_activity_categories`) |
| `evt_member_only` | boolean | Se apenas membros podem participar |
| `evt_price_member` | number | Preço para membros |
| `evt_price_non_member` | number | Preço para não-membros |
| `evt_early_bird_end_date` | date | Data limite para early bird |
| `evt_early_bird_price_member` | number | Preço early bird membros |
| `evt_early_bird_price_non_member` | number | Preço early bird não-membros |

### Meta Fields de Controle de Email (automáticos)

| Campo | Descrição |
|-------|-----------|
| `evt_reminder_sent_7_days` | Timestamp do envio do lembrete 7 dias |
| `evt_reminder_sent_3_days` | Timestamp do envio do lembrete 3 dias |
| `evt_reminder_sent_1_day` | Timestamp do envio do lembrete 1 dia |
| `evt_reminder_sent_1_hour` | Timestamp do envio do lembrete 1 hora |
| `evt_reminder_sent_starting` | Timestamp do envio do lembrete início |

## Páginas de Administração

### Dashboard de Eventos
**URL:** `/dashboard/events/`

Lista todos os eventos com:
- Filtros por data, status
- Ordenação por data de início
- Ações: editar, ver registros, duplicar, excluir

### Gerenciamento de Registros
**URL:** `/dashboard/events/{slug}/registrations/`

Lista registros de um evento específico com:
- Total de registros
- Status de cada registro
- Indicador de participação (attended)
- Indicador de Activity criada

## Fluxo de Dados

```
[Criar Evento]
    ↓
[Usuário se Registra] → Event Registration criado
    ↓
[Evento Acontece] → Usuário clica "Join" → reg_attended = 1
    ↓
[Evento Termina] → Cron processa → Activity CPD criada + Certificado gerado
    ↓
[Email enviado com certificado]
```

## Integração com Outros Módulos

### Event Registrations
- Cada evento pode ter múltiplos registros
- Registros vinculados via `reg_event_id`

### Activities (CPD)
- Activities criadas automaticamente após evento
- Vinculadas via `act_source_event_id`

### Email Service
- Emails automáticos de confirmação e lembretes
- Ver `EMAIL-SERVICE.md`

### Certificates
- Certificados PNG gerados automaticamente
- Ver `CERTIFICATES.md`

## Queries Úteis

### Buscar eventos futuros
```php
$events = get_posts([
    'post_type'      => 'eau_event',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'meta_query'     => [
        [
            'key'     => 'evt_start_datetime',
            'value'   => current_time('Y-m-d\TH:i'),
            'compare' => '>=',
            'type'    => 'DATETIME',
        ],
    ],
    'meta_key'  => 'evt_start_datetime',
    'orderby'   => 'meta_value',
    'order'     => 'ASC',
]);
```

### Buscar eventos passados
```php
$events = get_posts([
    'post_type'      => 'eau_event',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'meta_query'     => [
        [
            'key'     => 'evt_end_datetime',
            'value'   => current_time('Y-m-d\TH:i'),
            'compare' => '<',
            'type'    => 'DATETIME',
        ],
    ],
]);
```

## Versão

- **Introduzido:** v1.28.0
- **Última atualização:** v1.44.0
