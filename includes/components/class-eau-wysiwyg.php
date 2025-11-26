<?php
namespace EauSystem\Components;

/**
 * Componente reutilizável de Wysiwyg Editor
 *
 * Utiliza Quill.js para edição de texto rico
 *
 * Uso:
 * $wysiwyg = new Eau_Wysiwyg($config);
 * echo $wysiwyg->render();
 *
 * Ou método estático:
 * echo Eau_Wysiwyg::field($id, $name, $value, $config);
 *
 * @since 1.38.0
 */
class Eau_Wysiwyg {

    private $config;

    /**
     * Constructor
     *
     * @param array $config Configuração do editor:
     *  [
     *    'id' => 'my-editor',
     *    'name' => 'content',
     *    'value' => '<p>Initial content</p>',
     *    'placeholder' => 'Enter text...',
     *    'height' => 200, // em pixels
     *    'toolbar' => 'basic', // basic, standard, full
     *  ]
     */
    public function __construct($config = array()) {
        $defaults = array(
            'id' => 'eau-wysiwyg',
            'name' => 'content',
            'value' => '',
            'placeholder' => 'Enter text here...',
            'height' => 200,
            'toolbar' => 'standard',
        );

        $this->config = wp_parse_args($config, $defaults);
    }

    /**
     * Renderiza o editor
     *
     * @return string HTML do editor
     */
    public function render() {
        $id = esc_attr($this->config['id']);
        $name = esc_attr($this->config['name']);
        $value = $this->config['value'];
        $placeholder = esc_attr($this->config['placeholder']);
        $height = intval($this->config['height']);

        ob_start();
        ?>
        <div class="eau-wysiwyg-wrapper" data-wysiwyg-id="<?php echo $id; ?>">
            <!-- Container do editor Quill -->
            <div
                id="<?php echo $id; ?>-editor"
                class="eau-wysiwyg-editor"
                data-placeholder="<?php echo $placeholder; ?>"
                style="height: <?php echo $height; ?>px;"
            ><?php echo $value; ?></div>

            <!-- Campo hidden para enviar o valor no form -->
            <input
                type="hidden"
                id="<?php echo $id; ?>"
                name="<?php echo $name; ?>"
                value="<?php echo esc_attr($value); ?>"
            >
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Método estático para renderizar campo de formulário
     *
     * @param string $id ID do editor
     * @param string $name Nome do campo
     * @param string $value Valor inicial
     * @param array $config Configuração adicional
     * @return string HTML do campo
     */
    public static function field($id, $name, $value = '', $config = array()) {
        $config['id'] = $id;
        $config['name'] = $name;
        $config['value'] = $value;

        $wysiwyg = new self($config);
        return $wysiwyg->render();
    }

    /**
     * Retorna a configuração da toolbar baseada no tipo
     *
     * @param string $type Tipo da toolbar (basic, standard, full)
     * @return array Configuração da toolbar para Quill
     */
    public static function get_toolbar_config($type = 'standard') {
        switch ($type) {
            case 'basic':
                return array(
                    array('bold', 'italic', 'underline'),
                    array(array('list' => 'ordered'), array('list' => 'bullet')),
                    array('clean'),
                );

            case 'full':
                return array(
                    array(array('header' => array(1, 2, 3, false))),
                    array('bold', 'italic', 'underline', 'strike'),
                    array(array('color' => array()), array('background' => array())),
                    array(array('list' => 'ordered'), array('list' => 'bullet')),
                    array(array('indent' => '-1'), array('indent' => '+1')),
                    array('link', 'image'),
                    array('clean'),
                );

            case 'standard':
            default:
                return array(
                    array(array('header' => array(1, 2, 3, false))),
                    array('bold', 'italic', 'underline'),
                    array(array('list' => 'ordered'), array('list' => 'bullet')),
                    array('link'),
                    array('clean'),
                );
        }
    }

    /**
     * Enfileira os assets necessários para o Quill
     * Deve ser chamado no enqueue_scripts da página
     */
    public static function enqueue_assets() {
        // Quill CSS
        wp_enqueue_style(
            'quill-snow',
            'https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css',
            array(),
            '2.0.3'
        );

        // Quill JS
        wp_enqueue_script(
            'quill',
            'https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js',
            array(),
            '2.0.3',
            true
        );
    }

    /**
     * Retorna o JavaScript de inicialização para um editor específico
     *
     * @param string $id ID do editor
     * @param string $toolbar_type Tipo da toolbar
     * @return string JavaScript de inicialização
     */
    public static function get_init_script($id, $toolbar_type = 'standard') {
        $toolbar = wp_json_encode(self::get_toolbar_config($toolbar_type));

        return "
            (function() {
                var editorContainer = document.getElementById('{$id}-editor');
                var hiddenInput = document.getElementById('{$id}');

                if (!editorContainer || !hiddenInput) return;

                var quill = new Quill('#{$id}-editor', {
                    theme: 'snow',
                    placeholder: editorContainer.dataset.placeholder || '',
                    modules: {
                        toolbar: {$toolbar}
                    }
                });

                // Sincroniza com campo hidden
                quill.on('text-change', function() {
                    hiddenInput.value = quill.root.innerHTML;
                });

                // Armazena referência global
                window.eauWysiwygEditors = window.eauWysiwygEditors || {};
                window.eauWysiwygEditors['{$id}'] = quill;
            })();
        ";
    }
}
