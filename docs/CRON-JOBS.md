# Cron Jobs - Documentação

## Visão Geral

O plugin utiliza o sistema de WP-Cron para executar tarefas agendadas automaticamente. Existem dois cron jobs principais relacionados a eventos.

## Cron Jobs Ativos

### 1. eau_process_completed_events

**Função:** Processa eventos finalizados e cria Activities CPD para participantes.

| Propriedade | Valor |
|-------------|-------|
| Hook | `eau_process_completed_events` |
| Frequência | `hourly` (a cada hora) |
| Classe | `Eau_Event_Activity_Creator` |
| Método | `process_completed_events()` |

**O que faz:**
1. Busca eventos com `evt_end_datetime` < agora
2. Para cada evento finalizado:
   - Busca registros com `reg_attended = 1` e `reg_activity_created = 0`
   - Cria Activity CPD para cada participante
   - Gera certificado PNG
   - Envia email com certificado
   - Marca `reg_activity_created = 1`

**Registro:**
```php
// includes/event-registrations/class-eau-event-activity-creator.php
public static function setup_cron() {
    if (!wp_next_scheduled('eau_process_completed_events')) {
        wp_schedule_event(time(), 'hourly', 'eau_process_completed_events');
    }
}

add_action('eau_process_completed_events', [__CLASS__, 'process_completed_events']);
```

### 2. eau_email_reminders_cron

**Função:** Envia lembretes de eventos para participantes registrados.

| Propriedade | Valor |
|-------------|-------|
| Hook | `eau_email_reminders_cron` |
| Frequência | `hourly` (a cada hora) |
| Classe | `Email_Events` |
| Método | `process_reminders()` |

**O que faz:**
1. Busca eventos futuros (`evt_start_datetime` >= agora)
2. Calcula diferença em horas até o início
3. Envia lembrete apropriado se ainda não foi enviado

**Lembretes:**

| Diferença | Tipo | Assunto |
|-----------|------|---------|
| 167-168h | `7_days` | "Event Reminder: 1 Week to Go!" |
| 71-72h | `3_days` | "Event Reminder: 3 Days to Go!" |
| 23-24h | `1_day` | "Event Reminder: Tomorrow!" |
| 0-1h | `1_hour` | "Event Starting Soon!" |
| -1h a 0 | `starting` | "Event is Starting Now!" |

**Flags de controle (meta do evento):**
- `evt_reminder_sent_7_days`
- `evt_reminder_sent_3_days`
- `evt_reminder_sent_1_day`
- `evt_reminder_sent_1_hour`
- `evt_reminder_sent_starting`

**Registro:**
```php
// includes/email/class-email-events.php
public static function register() {
    add_action('eau_email_reminders_cron', [__CLASS__, 'process_reminders']);

    if (!wp_next_scheduled('eau_email_reminders_cron')) {
        wp_schedule_event(time(), 'hourly', 'eau_email_reminders_cron');
    }
}
```

## Fluxo de Execução

```
[WP-Cron executa a cada hora]
         ↓
┌────────────────────────────────────┐
│ eau_email_reminders_cron           │
│ - Busca eventos futuros            │
│ - Verifica horário de cada um      │
│ - Envia lembretes se necessário    │
└────────────────────────────────────┘
         ↓
┌────────────────────────────────────┐
│ eau_process_completed_events       │
│ - Busca eventos finalizados        │
│ - Cria Activities para quem        │
│   participou (attended=1)          │
│ - Gera certificados                │
│ - Envia emails                     │
└────────────────────────────────────┘
```

## Cenário Exemplo

**Evento:** Workshop dia 29/11 às 10:00
**Inscrição:** 27/11 às 14:00

| Data/Hora | Ação |
|-----------|------|
| 27/11 14:00 | Email de confirmação (imediato) |
| 28/11 ~10:00 | Lembrete "Tomorrow!" (24h antes) |
| 29/11 ~09:00 | Lembrete "Starting Soon!" (1h antes) |
| 29/11 ~10:00 | Lembrete "Starting Now!" (início) |
| 29/11 após fim | Activity criada + Email certificado |

## Verificar Crons Agendados

```php
// Verificar próxima execução
$next_reminders = wp_next_scheduled('eau_email_reminders_cron');
$next_activities = wp_next_scheduled('eau_process_completed_events');

echo 'Próximo lembrete: ' . date('Y-m-d H:i:s', $next_reminders);
echo 'Próximo processamento: ' . date('Y-m-d H:i:s', $next_activities);
```

## Forçar Execução Manual

```php
// Processar eventos finalizados agora
do_action('eau_process_completed_events');

// Processar lembretes agora
do_action('eau_email_reminders_cron');
```

## Reset de Lembretes

Para reenviar todos os lembretes de um evento:

```php
use EauSystem\Email\Email_Events;

// Remove todas as flags de lembrete
Email_Events::reset_reminder_flags($event_id);
```

## Desativar Crons

```php
// Remover cron de activities
$timestamp = wp_next_scheduled('eau_process_completed_events');
if ($timestamp) {
    wp_unschedule_event($timestamp, 'eau_process_completed_events');
}

// Remover cron de lembretes
$timestamp = wp_next_scheduled('eau_email_reminders_cron');
if ($timestamp) {
    wp_unschedule_event($timestamp, 'eau_email_reminders_cron');
}
```

## Debug

Para debugar crons, adicione ao `wp-config.php`:

```php
define('DISABLE_WP_CRON', false);
```

Para forçar execução de crons pendentes, acesse qualquer página do site ou use:

```bash
wp cron event run --all
```

## Considerações de Performance

- Crons rodam **a cada hora** (não em tempo real)
- Lembretes podem ter delay de até ~1 hora
- Eventos com muitos registros podem demorar para processar
- Certificados são gerados um por vez (evita sobrecarga)

## Versão

- **Activity Creator Cron:** v1.30.9
- **Email Reminders Cron:** v1.44.0
