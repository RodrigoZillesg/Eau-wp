<?php
/**
 * Email Template - Template HTML padrão
 *
 * @package EauSystem
 * @since   1.44.0
 */

namespace EauSystem\Email;

if (!defined('WPINC')) {
    die;
}

class Email_Template {

    /**
     * Renderiza o template completo do email
     *
     * @param string $subject Assunto do email
     * @param string $content Conteúdo HTML do email
     * @param array  $options Opções adicionais
     * @return string HTML completo do email
     */
    public static function render($subject, $content, $options = []) {
        $logo_url = $options['logo_url'] ?? Email_Config::get_logo_url();
        $footer_text = $options['footer_text'] ?? Email_Config::get_footer_text();
        $show_logo = $options['show_logo'] ?? true;
        $cancel_text = $options['cancel_text'] ?? '';

        $primary = Email_Config::COLOR_PRIMARY;
        $text = Email_Config::COLOR_TEXT;
        $text_light = Email_Config::COLOR_TEXT_LIGHT;
        $bg = Email_Config::COLOR_BACKGROUND;
        $white = Email_Config::COLOR_WHITE;
        $info_bg = '#e8f4fc'; // Azul bem claro para caixa de info

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($subject); ?></title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            font-size: 15px;
            line-height: 1.6;
            color: <?php echo $text; ?>;
            background-color: <?php echo $bg; ?>;
        }
        .email-wrapper {
            width: 100%;
            background-color: <?php echo $bg; ?>;
            padding: 30px 0;
        }
        .email-header {
            text-align: center;
            padding: 20px 0 30px 0;
        }
        .email-header img {
            max-width: 140px;
            height: auto;
        }
        .email-container {
            max-width: 580px;
            margin: 0 auto;
            background-color: <?php echo $white; ?>;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        .email-body {
            padding: 35px 40px;
        }
        .email-body h1 {
            margin: 0 0 25px 0;
            font-size: 22px;
            font-weight: 600;
            color: <?php echo $text; ?>;
        }
        .email-body p {
            margin: 0 0 15px 0;
            color: <?php echo $text; ?>;
        }
        .email-body a {
            color: <?php echo $primary; ?>;
        }
        .info-box {
            background-color: <?php echo $info_bg; ?>;
            border-left: 4px solid <?php echo $primary; ?>;
            padding: 18px 20px;
            margin: 20px 0;
            border-radius: 0 6px 6px 0;
        }
        .info-box h3 {
            margin: 0 0 10px 0;
            font-size: 16px;
            font-weight: 600;
            color: <?php echo $text; ?>;
        }
        .info-box p {
            margin: 5px 0;
            font-size: 14px;
        }
        .info-box .label {
            color: <?php echo $primary; ?>;
            font-weight: 600;
        }
        .email-footer {
            padding: 25px 40px;
            text-align: center;
            border-top: 1px solid #eee;
        }
        .email-footer p {
            margin: 0;
            font-size: 13px;
            color: <?php echo $text_light; ?>;
        }
        .email-footer a {
            color: <?php echo $primary; ?>;
            text-decoration: none;
        }
        .btn {
            display: inline-block;
            padding: 12px 28px;
            background-color: <?php echo $primary; ?>;
            color: <?php echo $white; ?> !important;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 14px;
            margin: 15px 0;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        table.data-table th,
        table.data-table td {
            padding: 10px 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
            font-size: 14px;
        }
        table.data-table th {
            background-color: <?php echo $bg; ?>;
            font-weight: 600;
        }
        @media only screen and (max-width: 600px) {
            .email-wrapper {
                padding: 15px 10px !important;
            }
            .email-container {
                width: 100% !important;
                border-radius: 6px !important;
            }
            .email-body {
                padding: 25px 20px !important;
            }
            .email-footer {
                padding: 20px !important;
            }
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <?php if ($show_logo && !empty($logo_url)): ?>
        <div class="email-header">
            <img src="<?php echo esc_url($logo_url); ?>" alt="English Australia">
        </div>
        <?php endif; ?>

        <div class="email-container">
            <div class="email-body">
                <?php echo $content; ?>
            </div>

            <?php if (!empty($cancel_text) || !empty($footer_text)): ?>
            <div class="email-footer">
                <?php if (!empty($cancel_text)): ?>
                <p><?php echo $cancel_text; ?></p>
                <?php endif; ?>
                <?php if (!empty($footer_text) && empty($cancel_text)): ?>
                <p><?php echo esc_html($footer_text); ?></p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
        <?php
        return ob_get_clean();
    }

    /**
     * Renderiza um botão
     */
    public static function button($text, $url) {
        return sprintf(
            '<p style="text-align: center;"><a href="%s" class="btn">%s</a></p>',
            esc_url($url),
            esc_html($text)
        );
    }

    /**
     * Renderiza uma caixa de informação com título
     */
    public static function info_box($title, $items = []) {
        $html = '<div class="info-box">';
        if (!empty($title)) {
            $html .= '<h3>' . esc_html($title) . '</h3>';
        }
        foreach ($items as $label => $value) {
            $html .= '<p><span class="label">' . esc_html($label) . ':</span> ' . esc_html($value) . '</p>';
        }
        $html .= '</div>';
        return $html;
    }

    /**
     * Renderiza caixa de informação com HTML customizado
     */
    public static function info_box_html($content) {
        return '<div class="info-box">' . $content . '</div>';
    }

    /**
     * Renderiza uma tabela de dados
     */
    public static function data_table($headers, $rows) {
        $html = '<table class="data-table"><thead><tr>';
        foreach ($headers as $header) {
            $html .= '<th>' . esc_html($header) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($row as $cell) {
                $html .= '<td>' . esc_html($cell) . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
        return $html;
    }
}
