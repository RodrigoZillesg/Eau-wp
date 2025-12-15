<?php
namespace EauSystem\Components;

/**
 * Componente reutilizável de Modal
 *
 * Uso:
 * $modal = new Eau_Modal($config);
 * echo $modal->render();
 */
class Eau_Modal {

    private $config;

    /**
     * Constructor
     *
     * @param array $config Configuração do modal:
     *  [
     *    'id' => 'member-modal',
     *    'title' => 'Edit Member',
     *    'size' => 'medium', // small, medium, large, full
     *    'content' => '<p>Modal content here</p>', // HTML content (opcional)
     *    'show_footer' => true,
     *    'footer_buttons' => [
     *        ['label' => 'Cancel', 'class' => 'eau-btn-secondary', 'action' => 'close'],
     *        ['label' => 'Save', 'class' => 'eau-btn-primary', 'action' => 'save']
     *    ]
     *  ]
     */
    public function __construct($config = array()) {
        $defaults = array(
            'id' => 'eau-modal',
            'title' => 'Modal Title',
            'size' => 'medium',
            'content' => '',
            'show_footer' => true,
            'footer_buttons' => array(
                array(
                    'label' => 'Cancel',
                    'class' => 'eau-btn-secondary',
                    'action' => 'close',
                ),
                array(
                    'label' => 'Save',
                    'class' => 'eau-btn-primary',
                    'action' => 'save',
                ),
            ),
        );

        $this->config = wp_parse_args($config, $defaults);
    }

    /**
     * Renderiza o modal
     *
     * @return string HTML do modal
     */
    public function render() {
        $size_class = $this->get_size_class();

        ob_start();
        ?>
        <div class="eau-modal-overlay" id="<?php echo esc_attr($this->config['id']); ?>-overlay" style="display: none;">
            <div class="eau-modal <?php echo esc_attr($size_class); ?>" id="<?php echo esc_attr($this->config['id']); ?>">

                <!-- Modal Header -->
                <div class="eau-modal-header">
                    <h2 class="eau-modal-title" id="<?php echo esc_attr($this->config['id']); ?>-title">
                        <?php echo esc_html($this->config['title']); ?>
                    </h2>
                    <button class="eau-modal-close" type="button" data-modal-action="close">
                        <i data-lucide="x"></i>
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="eau-modal-body" id="<?php echo esc_attr($this->config['id']); ?>-body">
                    <?php if (!empty($this->config['content'])) : ?>
                        <?php echo $this->config['content']; ?>
                    <?php else : ?>
                        <!-- Skeleton loading (conteúdo será carregado dinamicamente via JavaScript) -->
                        <?php echo \EauSystem\Components\Eau_Skeleton::form(3); ?>
                    <?php endif; ?>
                </div>

                <!-- Modal Footer -->
                <?php if ($this->config['show_footer']) : ?>
                    <div class="eau-modal-footer" id="<?php echo esc_attr($this->config['id']); ?>-footer">
                        <?php foreach ($this->config['footer_buttons'] as $button) : ?>
                            <button
                                type="button"
                                class="eau-btn <?php echo esc_attr($button['class']); ?>"
                                data-modal-action="<?php echo esc_attr($button['action']); ?>"
                                <?php if (!empty($button['id'])) : ?>id="<?php echo esc_attr($button['id']); ?>"<?php endif; ?>
                            >
                                <?php echo wp_kses_post($button['label']); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Obtém a classe CSS do tamanho do modal
     *
     * @return string Classe CSS
     */
    private function get_size_class() {
        $sizes = array(
            'small' => 'eau-modal-small',
            'medium' => 'eau-modal-medium',
            'large' => 'eau-modal-large',
            'full' => 'eau-modal-full',
        );

        $size = $this->config['size'];
        return isset($sizes[$size]) ? $sizes[$size] : $sizes['medium'];
    }

    /**
     * Renderiza um formulário dentro do modal
     *
     * @param array $fields Array de campos do formulário
     * @return string HTML do formulário
     */
    public static function render_form($fields = array()) {
        if (empty($fields)) {
            return '<p>No fields to display</p>';
        }

        ob_start();
        ?>
        <form class="eau-modal-form" id="eau-member-form">
            <div class="eau-form-grid">
                <?php foreach ($fields as $field) : ?>
                    <?php echo self::render_field($field); ?>
                <?php endforeach; ?>
            </div>
        </form>
        <?php
        return ob_get_clean();
    }

    /**
     * Renderiza um campo individual do formulário
     *
     * @param array $field Configuração do campo
     * @return string HTML do campo
     */
    private static function render_field($field) {
        $type = isset($field['type']) ? $field['type'] : 'text';
        $key = isset($field['key']) ? $field['key'] : '';
        $label = isset($field['label']) ? $field['label'] : '';
        $value = isset($field['value']) ? $field['value'] : '';
        $required = isset($field['required']) && $field['required'] ? 'required' : '';
        $readonly = isset($field['readonly']) && $field['readonly'] ? 'readonly' : '';
        $options = isset($field['options']) ? $field['options'] : array();
        $span = isset($field['span']) ? $field['span'] : 1; // Grid span

        $span_class = 'eau-form-field-span-' . $span;

        ob_start();
        ?>
        <div class="eau-form-field <?php echo esc_attr($span_class); ?>">
            <label class="eau-form-label" for="eau-field-<?php echo esc_attr($key); ?>">
                <?php echo esc_html($label); ?>
                <?php if ($required) : ?>
                    <span class="eau-form-required">*</span>
                <?php endif; ?>
            </label>

            <?php
            switch ($type) {
                case 'text':
                case 'email':
                case 'tel':
                case 'number':
                case 'date':
                    ?>
                    <input
                        type="<?php echo esc_attr($type); ?>"
                        class="eau-form-input"
                        id="eau-field-<?php echo esc_attr($key); ?>"
                        name="<?php echo esc_attr($key); ?>"
                        value="<?php echo esc_attr($value); ?>"
                        <?php echo $required; ?>
                        <?php echo $readonly; ?>
                    >
                    <?php
                    break;

                case 'textarea':
                    ?>
                    <textarea
                        class="eau-form-textarea"
                        id="eau-field-<?php echo esc_attr($key); ?>"
                        name="<?php echo esc_attr($key); ?>"
                        rows="4"
                        <?php echo $required; ?>
                        <?php echo $readonly; ?>
                    ><?php echo esc_textarea($value); ?></textarea>
                    <?php
                    break;

                case 'select':
                    ?>
                    <select
                        class="eau-form-select"
                        id="eau-field-<?php echo esc_attr($key); ?>"
                        name="<?php echo esc_attr($key); ?>"
                        <?php echo $required; ?>
                        <?php echo $readonly; ?>
                    >
                        <option value="">Select...</option>
                        <?php foreach ($options as $opt_value => $opt_label) : ?>
                            <option
                                value="<?php echo esc_attr($opt_value); ?>"
                                <?php selected($value, $opt_value); ?>
                            >
                                <?php echo esc_html($opt_label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php
                    break;

                case 'checkbox':
                    ?>
                    <label class="eau-form-checkbox-wrapper">
                        <input
                            type="checkbox"
                            class="eau-form-checkbox"
                            id="eau-field-<?php echo esc_attr($key); ?>"
                            name="<?php echo esc_attr($key); ?>"
                            value="1"
                            <?php checked($value, '1'); ?>
                            <?php echo $readonly; ?>
                        >
                        <span class="eau-form-checkbox-label">
                            <?php echo isset($field['checkbox_label']) ? esc_html($field['checkbox_label']) : 'Enable'; ?>
                        </span>
                    </label>
                    <?php
                    break;

                case 'static':
                    // Campo somente leitura (exibição apenas)
                    ?>
                    <div class="eau-form-static">
                        <?php echo esc_html($value); ?>
                    </div>
                    <?php
                    break;

                default:
                    ?>
                    <input
                        type="text"
                        class="eau-form-input"
                        id="eau-field-<?php echo esc_attr($key); ?>"
                        name="<?php echo esc_attr($key); ?>"
                        value="<?php echo esc_attr($value); ?>"
                        <?php echo $required; ?>
                        <?php echo $readonly; ?>
                    >
                    <?php
                    break;
            }
            ?>
        </div>
        <?php
        return ob_get_clean();
    }
}
