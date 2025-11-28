# Email Service - Documentação

## Visão Geral

O Email Service fornece uma infraestrutura completa para envio de emails com template HTML responsivo, suporte a templates pré-definidos e sistema de lembretes automáticos para eventos.

## Estrutura de Arquivos

```
includes/email/
├── class-email-config.php      # Configurações globais
├── class-email-template.php    # Template HTML responsivo
├── class-email-service.php     # Serviço de envio
└── class-email-events.php      # Emails específicos para eventos
```

## Classes

### Email_Config

Configurações globais do serviço de email.

```php
namespace EauSystem\Email;

// Constantes
Email_Config::FROM_NAME          // 'English Australia'
Email_Config::FROM_EMAIL         // 'noreply@englishaustralia.com.au'
Email_Config::COLOR_PRIMARY      // '#005EB8'
Email_Config::COLOR_SECONDARY    // '#6495ED'
Email_Config::COLOR_TEXT         // '#333333'
Email_Config::COLOR_TEXT_LIGHT   // '#666666'
Email_Config::COLOR_BACKGROUND   // '#f4f4f4'
Email_Config::COLOR_WHITE        // '#ffffff'

// Métodos (retornam options ou defaults)
Email_Config::get_from_name();
Email_Config::get_from_email();
Email_Config::get_logo_url();
Email_Config::get_footer_text();
```

**Options do WordPress:**
- `eau_email_from_name`
- `eau_email_from_email`
- `eau_email_logo_url`
- `eau_email_footer_text`

### Email_Template

Renderiza o template HTML responsivo.

```php
namespace EauSystem\Email;

// Renderizar template completo
$html = Email_Template::render($subject, $content, [
    'logo_url'    => 'https://...',      // opcional
    'footer_text' => '© 2025 ...',       // opcional
    'show_logo'   => true,               // opcional
    'cancel_text' => 'If you need...',   // opcional (texto no footer)
]);

// Helpers para conteúdo
Email_Template::button('Click Here', 'https://...');
Email_Template::info_box('Event Title', [
    'Date'     => 'December 20, 2025',
    'Location' => 'Sydney',
]);
Email_Template::info_box_html('<p>Custom HTML</p>');
Email_Template::data_table(['Col1', 'Col2'], [['a', 'b'], ['c', 'd']]);
```

### Email_Service

Serviço principal de envio.

```php
namespace EauSystem\Email;

// Envio simples
Email_Service::send($to, $subject, $content, $options);

// Envio em massa (mesmo conteúdo)
$results = Email_Service::send_bulk($recipients, $subject, $content, $options);

// Envio personalizado (placeholders {name}, {email}, etc)
$results = Email_Service::send_personalized([
    'user1@email.com' => ['name' => 'João'],
    'user2@email.com' => ['name' => 'Maria'],
], 'Hello {name}!', '<p>Dear {name}...</p>', $options);

// Templates pré-definidos
$content = Email_Service::get_template('welcome', ['name' => 'João']);
$content = Email_Service::get_template('certificate', [...]);
$content = Email_Service::get_template('event_registration', [...]);
$content = Email_Service::get_template('event_reminder', [...]);
```

**Opções de envio:**
```php
$options = [
    'reply_to'    => 'reply@email.com',
    'cc'          => ['cc1@email.com', 'cc2@email.com'],
    'bcc'         => 'bcc@email.com',
    'attachments' => ['/path/to/file.pdf'],
    'log'         => true,  // salva no option eau_email_log
    'cancel_text' => 'If you need to cancel...',
];
```

### Email_Events

Emails específicos para o módulo de eventos.

```php
namespace EauSystem\Email;

// Enviar confirmação de registro
Email_Events::send_registration_confirmation($registration_id);

// Enviar lembrete de evento
Email_Events::send_event_reminder($event_id, '1_day');
// Tipos: '7_days', '3_days', '1_day', '1_hour', 'starting'

// Enviar email de certificado
Email_Events::send_certificate_email($registration_id, $certificate_url);

// Enviar email customizado para todos os registrados
Email_Events::send_to_event_registrations($event_id, $subject, $content, $options);

// Buscar registros de um evento
$registrations = Email_Events::get_event_registrations($event_id, ['confirmed', 'pending']);

// Reset flags de lembretes (para reenviar)
Email_Events::reset_reminder_flags($event_id);
```

## Templates Pré-definidos

### welcome
```php
Email_Service::get_template('welcome', [
    'name' => 'João Silva',
]);
```

### certificate
```php
Email_Service::get_template('certificate', [
    'name'            => 'João Silva',
    'event_title'     => 'Workshop XYZ',
    'cpd_points'      => '5',
    'cpd_category'    => 'Professional Development',
    'certificate_url' => 'https://...',
]);
```

### event_registration
```php
Email_Service::get_template('event_registration', [
    'name'           => 'João Silva',
    'event_title'    => 'Workshop XYZ',
    'event_date'     => 'Saturday, December 20, 2025',
    'event_location' => 'Sydney Convention Centre',
]);
```

### event_reminder
```php
Email_Service::get_template('event_reminder', [
    'name'           => 'João Silva',
    'event_title'    => 'Workshop XYZ',
    'event_date'     => 'Saturday, December 20, 2025 at 9:00 AM',
    'event_location' => 'Sydney',
    'join_url'       => 'https://zoom.us/...',  // opcional
]);
```

## Emails Automáticos de Eventos

| Momento | Método | Trigger |
|---------|--------|---------|
| No registro | `send_registration_confirmation()` | AJAX `eau_register_for_event` |
| 7 dias antes | `send_event_reminder('7_days')` | Cron |
| 3 dias antes | `send_event_reminder('3_days')` | Cron |
| 1 dia antes | `send_event_reminder('1_day')` | Cron |
| 1 hora antes | `send_event_reminder('1_hour')` | Cron |
| Evento iniciando | `send_event_reminder('starting')` | Cron |
| Certificado emitido | `send_certificate_email()` | Activity Creator |

## Preview de Email

**URL:** `/wp-admin/?eau_email_preview=1`

Parâmetros opcionais:
- `template` - `event_registration`, `certificate`, `event_reminder`, `welcome`
- `name` - Nome do participante
- `event_title` - Título do evento
- `event_date` - Data do evento
- `event_location` - Local
- `cpd_points` - Pontos CPD
- `cpd_category` - Categoria CPD

**Exemplo:**
```
/wp-admin/?eau_email_preview=1&template=certificate&name=Maria&event_title=Workshop
```

## Design do Template

O template segue o design:
- Logo centralizado no topo (fundo cinza claro)
- Card branco com borda arredondada e sombra sutil
- Caixa de info azul clara com borda esquerda azul (`info_box`)
- Footer com texto de cancelamento (opcional)
- Responsivo para mobile

## Integração

### No registro de evento
```php
// includes/event-registrations/frontend/class-eau-event-registrations-ajax.php
\EauSystem\Email\Email_Events::send_registration_confirmation($post_id);
```

### Na criação de Activity/Certificado
```php
// includes/event-registrations/class-eau-event-activity-creator.php
$certificate_url = wp_get_attachment_url($certificate_id);
\EauSystem\Email\Email_Events::send_certificate_email($registration_id, $certificate_url);
```

### Cron de lembretes
```php
// Registrado em class-eau-system.php
Email\Email_Events::register();

// Executa: add_action('eau_email_reminders_cron', [Email_Events::class, 'process_reminders']);
```

## Versão

- **Introduzido:** v1.44.0
