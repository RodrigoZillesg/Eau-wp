# Certificates - Documentação

## Visão Geral

O sistema de certificados gera automaticamente certificados PNG de participação em eventos usando a biblioteca GD do PHP. Os certificados são salvos na biblioteca de mídia do WordPress.

## Estrutura de Arquivos

```
includes/event-registrations/certificate/
├── class-certificate-config.php      # Configurações (dimensões, cores, textos)
├── class-certificate-renderer.php    # Métodos de desenho
├── class-certificate-generator.php   # Geração e salvamento
├── english-australia-logo-500.png    # Logo da organização
├── rubrica.png                       # Imagem da assinatura
└── index.php
```

## Classes

### Certificate_Config

Configurações do certificado.

```php
namespace EauSystem\EventRegistrations\Certificate;

// Dimensões (16:9 paisagem)
Certificate_Config::WIDTH   // 1920
Certificate_Config::HEIGHT  // 1080

// Cores (RGB arrays)
Certificate_Config::COLOR_BLUE   // [0, 94, 184]    - #005EB8
Certificate_Config::COLOR_GOLD   // [212, 175, 55]  - Dourado
Certificate_Config::COLOR_DARK   // [51, 51, 51]    - Texto escuro
Certificate_Config::COLOR_GRAY   // [128, 128, 128] - Texto cinza
Certificate_Config::COLOR_WHITE  // [255, 255, 255] - Fundo

// Organização
Certificate_Config::ORG_NAME      // 'English Australia'
Certificate_Config::SIGNER_NAME   // 'Ian Aird'
Certificate_Config::SIGNER_TITLE  // 'Chief Executive Officer'

// Fontes
Certificate_Config::get_font('regular');  // Arial ou DejaVuSans
Certificate_Config::get_font('bold');
Certificate_Config::get_font('italic');
```

### Certificate_Renderer

Métodos de desenho no certificado.

```php
namespace EauSystem\EventRegistrations\Certificate;

$image = imagecreatetruecolor(1920, 1080);
$renderer = new Certificate_Renderer($image);

// Background
$renderer->fill_background();

// Formas decorativas (triângulos nos cantos)
$renderer->draw_corner_shapes();

// Textos
$renderer->draw_text_centered($text, $size, $color_key, $y, $font_type);
$renderer->draw_text_left($text, $size, $color_key, $x, $y, $font_type);

// Imagens
$renderer->draw_logo($x, $y, $max_width);
$renderer->draw_signature($x, $y, $max_width);
```

**Cores disponíveis:** `blue`, `blue_light`, `gold`, `dark`, `gray`, `white`

**Tipos de fonte:** `regular`, `bold`, `italic`

### Certificate_Generator

Gera e salva o certificado.

```php
namespace EauSystem\EventRegistrations\Certificate;

// Gerar certificado e salvar na mídia
$attachment_id = Certificate_Generator::generate([
    'first_name'   => 'João',
    'last_name'    => 'Silva',
    'event_title'  => 'Workshop de Desenvolvimento',
    'event_date'   => '2025-12-20',
    'cpd_points'   => '5',
    'cpd_category' => 'Professional Development',
]);

// $attachment_id = ID do attachment na mídia do WordPress
// false = erro na geração
```

## Preview de Certificado

**URL:** `/wp-admin/?eau_certificate_preview=1`

Parâmetros opcionais:
- `first_name` - Primeiro nome
- `last_name` - Sobrenome
- `event_title` - Título do evento
- `event_date` - Data (formato: `November 28, 2025`)
- `cpd_points` - Pontos CPD
- `cpd_category` - Categoria CPD

**Exemplo:**
```
/wp-admin/?eau_certificate_preview=1&first_name=Maria&last_name=Santos&event_title=Workshop%20ABC&cpd_points=10
```

## Layout do Certificado

```
┌────────────────────────────────────────────────────────┐
│▲                                                      │
│ ▲ (triângulos azuis)                                  │
│                                                        │
│         [LOGO]                                         │
│                                                        │
│         CERTIFICATE OF                                 │
│         ATTENDANCE                                     │
│                                                        │
│         This certificate is awarded to                 │
│         João Silva                                     │
│                                                        │
│         For attendance at                              │
│         Workshop de Desenvolvimento                    │
│                                                        │
│         Date: December 20, 2025                        │
│         CPD Points: 5 (Professional Development)       │
│                                                        │
│         [Assinatura]                                   │
│         Ian Aird                                       │
│         Chief Executive Officer                        │
│                                                    ▼ ▼│
│                                      (triângulos)  ▼▼│
└────────────────────────────────────────────────────────┘
```

## Integração com Activity Creator

Quando uma Activity é criada após um evento:

```php
// includes/event-registrations/class-eau-event-activity-creator.php

// Gera certificado
$certificate_id = Certificate\Certificate_Generator::generate([
    'first_name'   => $user_data['first_name'],
    'last_name'    => $user_data['last_name'],
    'event_title'  => $event_data['event_title'],
    'event_date'   => $event_data['event_date'],
    'cpd_points'   => $event_data['cpd_points'],
    'cpd_category' => $event_data['category_name'],
]);

if ($certificate_id) {
    // Salva ID da mídia no Activity
    update_post_meta($activity_id, 'act_event_website_where_possible', $certificate_id);

    // Envia email com certificado
    $certificate_url = wp_get_attachment_url($certificate_id);
    \EauSystem\Email\Email_Events::send_certificate_email($registration_id, $certificate_url);
}
```

## Requisitos

- **PHP GD Extension:** Obrigatório (`extension_loaded('gd')`)
- **Fontes TTF:** Arial (Windows) ou DejaVuSans (Linux)
- **Permissões:** Escrita no diretório de uploads do WordPress

## Arquivos de Imagem

### Logo
- **Arquivo:** `english-australia-logo-500.png`
- **Tamanho original:** 500px largura
- **Tamanho no certificado:** 154px (30% menor que 220px original)

### Assinatura
- **Arquivo:** `rubrica.png`
- **Formato:** PNG com transparência
- **Tamanho no certificado:** 120px largura

## Personalização

Para alterar o layout, edite `class-certificate-generator.php`:

```php
private static function create_image($data) {
    // ...

    // Margem esquerda para conteúdo
    $left = 350;

    // Logo no topo
    $r->draw_logo($left, 100, 154);  // x, y, max_width

    // Título
    $r->draw_text_left('CERTIFICATE OF', 38, 'blue', $left, 320, 'bold');
    $r->draw_text_left('ATTENDANCE', 38, 'blue', $left, 370, 'bold');

    // Nome do participante
    $r->draw_text_left($full_name, 36, 'dark', $left, 500, 'bold');

    // ... etc
}
```

## Versão

- **Introduzido:** v1.43.9
- **Última atualização:** v1.44.0
