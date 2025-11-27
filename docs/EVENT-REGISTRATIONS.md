# Event Registrations Module - Documentação

## Visão Geral

O módulo de Event Registrations gerencia inscrições de usuários em eventos. Controla o ciclo completo desde a inscrição até a geração de certificado e Activity CPD.

## Estrutura de Arquivos

```
includes/event-registrations/
├── class-eau-event-registrations.php       # Bootstrap do módulo
├── class-eau-event-registrations-cpt.php   # Definição do CPT
├── class-eau-event-activity-creator.php    # Criação automática de Activities
├── config/
│   ├── constants.php                       # Constantes (POST_TYPE, META_PREFIX)
│   ├── meta-fields.php                     # Definição dos meta fields
│   └── options.php                         # Opções do módulo
├── admin/
│   └── class-eau-event-registrations-metabox.php  # Metabox no admin
├── frontend/
│   └── class-eau-event-registrations-ajax.php     # AJAX handlers frontend
├── dashboard/
│   ├── class-registrations-page.php        # Página de registros
│   ├── class-registrations-template.php    # Template da tabela
│   ├── class-registrations-stats.php       # Estatísticas
│   └── class-registrations-assets.php      # Assets CSS/JS
└── certificate/
    ├── class-certificate-config.php        # Configurações do certificado
    ├── class-certificate-renderer.php      # Renderização do certificado
    ├── class-certificate-generator.php     # Geração do PNG
    ├── english-australia-logo-500.png      # Logo
    └── rubrica.png                         # Assinatura
```

## Custom Post Type

**Post Type:** `eau_event_reg`
**Meta Prefix:** `reg_`

### Meta Fields

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `reg_attendee_name` | string | Nome do participante |
| `reg_attendee_email` | string | Email do participante |
| `reg_event_id` | integer | ID do evento (`eau_event`) |
| `reg_registration_date` | datetime | Data/hora do registro |
| `reg_member_type` | string | `member` ou `non_member` |
| `reg_status` | string | Status: `confirmed`, `pending`, `cancelled` |
| `reg_payment_status` | string | Status de pagamento |
| `reg_user_id` | integer | WordPress User ID (se logado) |
| `reg_mem_userid` | string | EAU Member ID (user meta `mem_userid`) |
| `reg_attended` | boolean | Se o usuário participou (clicou em Join) |
| `reg_activity_created` | boolean | Se a Activity CPD já foi criada |

## Fluxo de Registro

```
1. [Usuário acessa evento]
       ↓
2. [Clica em "Register"]
       ↓
3. [AJAX: eau_register_for_event]
       ↓
4. [Validações]
   - Evento existe?
   - Já registrado?
   - Capacidade disponível?
       ↓
5. [Cria post eau_event_reg]
       ↓
6. [Salva meta fields]
       ↓
7. [Envia email de confirmação]
       ↓
8. [Retorna sucesso]
```

## Fluxo de Participação e Activity

```
1. [Evento acontece]
       ↓
2. [Usuário clica "Join Online"]
       ↓
3. [AJAX: eau_mark_attended]
       ↓
4. [reg_attended = 1]
       ↓
5. [Cron: eau_process_completed_events (após evento)]
       ↓
6. [Para cada registro com attended=1 e activity_created=0]
       ↓
7. [Cria Activity CPD]
       ↓
8. [Gera Certificado PNG]
       ↓
9. [Envia email com certificado]
       ↓
10. [reg_activity_created = 1]
```

## AJAX Endpoints

### Frontend (usuários)

| Action | Descrição | Auth |
|--------|-----------|------|
| `eau_register_for_event` | Registrar em evento | Ambos |
| `eau_check_registration` | Verificar se já registrado | Ambos |
| `eau_cancel_registration` | Cancelar registro | Logado |
| `eau_mark_attended` | Marcar participação | Logado |

### Admin (dashboard)

| Action | Descrição |
|--------|-----------|
| `eau_get_event_registrations` | Listar registros de um evento |
| `eau_delete_registration` | Excluir registro |

## Classes Principais

### Eau_Event_Registrations_Ajax

Handlers AJAX para registro frontend.

```php
// Verificar se usuário está registrado
$is_registered = Eau_Event_Registrations_Ajax::is_user_registered($event_id);

// Buscar registro do usuário
$registration = Eau_Event_Registrations_Ajax::get_user_registration($event_id, $user_id);

// Contar registros de um evento
$count = Eau_Event_Registrations_Ajax::count_registrations($event_id);
```

### Eau_Event_Activity_Creator

Criação automática de Activities após eventos.

```php
// Criar activities para todos os participantes de um evento
$created = Eau_Event_Activity_Creator::create_activities_for_event($event_id);

// Criar activity para um registro específico
$activity_id = Eau_Event_Activity_Creator::create_activity_for_registration($registration_id);

// Marcar participação manualmente
Eau_Event_Activity_Creator::mark_attended(); // via AJAX
```

## Queries Úteis

### Buscar registros de um evento
```php
$prefix = 'reg_';
$registrations = get_posts([
    'post_type'      => 'eau_event_reg',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'meta_query'     => [
        [
            'key'   => $prefix . 'event_id',
            'value' => $event_id,
        ],
        [
            'key'     => $prefix . 'status',
            'value'   => 'cancelled',
            'compare' => '!=',
        ],
    ],
]);
```

### Buscar registros que participaram mas não tiveram Activity criada
```php
$registrations = get_posts([
    'post_type'      => 'eau_event_reg',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'meta_query'     => [
        [
            'key'   => 'reg_event_id',
            'value' => $event_id,
        ],
        [
            'key'   => 'reg_attended',
            'value' => '1',
        ],
        [
            'key'     => 'reg_activity_created',
            'value'   => '1',
            'compare' => '!=',
        ],
    ],
]);
```

## Integração com Activity CPD

Quando uma Activity é criada a partir de um registro:

| Campo Activity | Origem |
|----------------|--------|
| `act_user_id` | `reg_mem_userid` |
| `act_activity_serial` | UUID gerado |
| `act_first_name` | User meta `mem_memberfirstname` |
| `act_last_name` | User meta `mem_memberlastname` |
| `act_email` | User email |
| `act_pd_activity_name` | Título do evento |
| `act_category_serial` | Do evento `evt_cpd_category` |
| `act_category` | Nome da categoria |
| `act_hours_of_pd_*` | Calculado da duração do evento |
| `act_verified` | `1` (automático) |
| `act_source_event_id` | ID do evento |
| `act_source_registration_id` | ID do registro |
| `act_event_website_where_possible` | ID do certificado (attachment) |

## Status de Registro

| Status | Descrição |
|--------|-----------|
| `pending` | Aguardando confirmação/pagamento |
| `confirmed` | Confirmado |
| `cancelled` | Cancelado pelo usuário |

## Versão

- **Introduzido:** v1.29.0
- **Activity Creator:** v1.30.9
- **Certificados:** v1.43.9
- **Última atualização:** v1.44.0
