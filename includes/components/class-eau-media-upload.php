<?php
namespace EauSystem\Components;

/**
 * Componente reutilizável de Media Upload
 *
 * Permite upload de arquivos com UI customizada seguindo o design system
 * Os arquivos são salvos na Media Library do WordPress
 *
 * Features:
 * - Upload via drag & drop ou clique
 * - Lista de arquivos já enviados pelo usuário
 * - Inserção de URL externa
 * - Preview do arquivo selecionado
 *
 * Uso:
 * $upload = new Eau_Media_Upload($config);
 * echo $upload->render();
 *
 * Ou método estático:
 * echo Eau_Media_Upload::field($id, $name, $value, $config);
 *
 * @since 1.38.0
 * @updated 1.38.3 - Custom file picker UI
 */
class Eau_Media_Upload {

    private $config;

    /**
     * Constructor
     *
     * @param array $config Configuração do uploader:
     *  [
     *    'id' => 'my-upload',
     *    'name' => 'attachment_id',
     *    'value' => '', // URL ou attachment ID
     *    'type' => 'both', // url, media, both
     *    'allowed_types' => 'image,application/pdf', // MIME types
     *    'placeholder' => 'Enter URL or upload file',
     *    'button_text' => 'Upload File',
     *  ]
     */
    public function __construct($config = array()) {
        $defaults = array(
            'id' => 'eau-media-upload',
            'name' => 'attachment',
            'value' => '',
            'type' => 'both', // url, media, both
            'allowed_types' => '', // vazio = todos
            'allowed_extensions' => '', // e.g., 'pdf,jpg,png'
            'placeholder' => 'Enter URL or upload file',
            'button_text' => 'Upload',
            'url_placeholder' => 'https://example.com/file.pdf',
            'max_file_size' => 10 * 1024 * 1024, // 10MB default
        );

        $this->config = wp_parse_args($config, $defaults);
    }

    /**
     * Renderiza o componente
     *
     * @return string HTML do componente
     */
    public function render() {
        $id = esc_attr($this->config['id']);
        $name = esc_attr($this->config['name']);
        $value = esc_attr($this->config['value']);
        $type = $this->config['type'];
        $url_placeholder = esc_attr($this->config['url_placeholder']);
        $allowed_types = esc_attr($this->config['allowed_types']);
        $allowed_extensions = esc_attr($this->config['allowed_extensions']);
        $max_file_size = intval($this->config['max_file_size']);

        // Determina se o valor atual é URL ou attachment ID
        $is_url = filter_var($this->config['value'], FILTER_VALIDATE_URL);
        $display_value = '';
        $file_name = '';

        if ($is_url) {
            $display_value = $this->config['value'];
            $file_name = basename($display_value);
        } elseif (is_numeric($this->config['value']) && $this->config['value'] > 0) {
            // É um attachment ID - pega a URL
            $display_value = wp_get_attachment_url($this->config['value']);
            $file_name = basename(get_attached_file($this->config['value']));
        }

        // Formata allowed extensions para exibição
        $extensions_display = '';
        if (!empty($allowed_extensions)) {
            $exts = array_map('strtoupper', explode(',', $allowed_extensions));
            $extensions_display = implode(', ', $exts);
        }

        ob_start();
        ?>
        <div
            class="eau-media-upload-wrapper"
            id="<?php echo $id; ?>-wrapper"
            data-type="<?php echo esc_attr($type); ?>"
            data-allowed-types="<?php echo $allowed_types; ?>"
            data-allowed-extensions="<?php echo $allowed_extensions; ?>"
            data-max-file-size="<?php echo $max_file_size; ?>"
        >
            <!-- Tabs para alternar entre URL, Upload e My Files (se type = both) -->
            <?php if ($type === 'both'): ?>
            <div class="eau-media-upload-tabs">
                <button type="button" class="eau-media-upload-tab eau-media-upload-tab-active" data-tab="url">
                    <i data-lucide="link"></i>
                    URL
                </button>
                <button type="button" class="eau-media-upload-tab" data-tab="upload">
                    <i data-lucide="upload"></i>
                    Upload
                </button>
                <button type="button" class="eau-media-upload-tab" data-tab="myfiles">
                    <i data-lucide="folder"></i>
                    My Files
                </button>
            </div>
            <?php endif; ?>

            <!-- Painel de URL -->
            <?php if ($type === 'url' || $type === 'both'): ?>
            <div class="eau-media-upload-panel eau-media-upload-url-panel <?php echo ($type === 'both') ? 'active' : ''; ?>">
                <div class="eau-media-upload-input-group">
                    <input
                        type="text"
                        class="eau-form-input eau-media-upload-url-input"
                        placeholder="<?php echo $url_placeholder; ?>"
                        value="<?php echo ($is_url ? esc_attr($display_value) : ''); ?>"
                    >
                </div>
            </div>
            <?php endif; ?>

            <!-- Painel de Upload -->
            <?php if ($type === 'media' || $type === 'both'): ?>
            <div class="eau-media-upload-panel eau-media-upload-file-panel <?php echo ($type === 'media') ? 'active' : ''; ?>">
                <div class="eau-media-upload-dropzone" id="<?php echo $id; ?>-dropzone">
                    <input
                        type="file"
                        class="eau-media-upload-file-input"
                        id="<?php echo $id; ?>-file-input"
                        <?php echo !empty($allowed_types) ? 'accept="' . esc_attr($allowed_types) . '"' : ''; ?>
                        style="display: none;"
                    >
                    <div class="eau-media-upload-dropzone-content">
                        <i data-lucide="upload-cloud"></i>
                        <span class="eau-media-upload-dropzone-text">
                            Drag & drop or <button type="button" class="eau-media-upload-browse-btn">Browse</button>
                        </span>
                        <span class="eau-media-upload-dropzone-hint">
                            <?php if (!empty($extensions_display)): ?>
                                Allowed: <?php echo esc_html($extensions_display); ?>
                            <?php else: ?>
                                All file types accepted
                            <?php endif; ?>
                            <br>
                            Max size: <?php echo esc_html(size_format($max_file_size)); ?>
                        </span>
                    </div>
                    <!-- Upload progress -->
                    <div class="eau-media-upload-progress" style="display: none;">
                        <div class="eau-media-upload-progress-bar">
                            <div class="eau-media-upload-progress-fill"></div>
                        </div>
                        <span class="eau-media-upload-progress-text">Uploading... 0%</span>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Painel My Files (hidden by default via CSS - shown when myfiles tab is active) -->
            <?php if ($type === 'media' || $type === 'both'): ?>
            <div class="eau-media-upload-panel eau-media-upload-myfiles-panel">
                <div class="eau-media-upload-myfiles-header">
                    <div class="eau-media-upload-myfiles-search">
                        <i data-lucide="search"></i>
                        <input type="text" class="eau-media-upload-myfiles-search-input" placeholder="Search files...">
                    </div>
                </div>
                <div class="eau-media-upload-myfiles-list" id="<?php echo $id; ?>-myfiles-list">
                    <div class="eau-media-upload-myfiles-loading">
                        <i data-lucide="loader-2" class="eau-spin"></i>
                        <span>Loading files...</span>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Preview da mídia selecionada -->
            <div class="eau-media-upload-preview" id="<?php echo $id; ?>-preview" style="<?php echo empty($display_value) ? 'display:none;' : ''; ?>">
                <div class="eau-media-upload-preview-content">
                    <div class="eau-media-upload-preview-thumbnail" id="<?php echo $id; ?>-thumbnail">
                        <i data-lucide="file-check" class="eau-media-upload-preview-icon"></i>
                        <img src="" alt="Preview" class="eau-media-upload-preview-image" style="display: none;">
                    </div>
                    <div class="eau-media-upload-preview-info">
                        <span class="eau-media-upload-preview-name" id="<?php echo $id; ?>-preview-name">
                            <?php echo esc_html($file_name); ?>
                        </span>
                    </div>
                    <div class="eau-media-upload-preview-actions">
                        <a href="<?php echo esc_url($display_value); ?>" target="_blank" class="eau-media-upload-preview-link" id="<?php echo $id; ?>-preview-link" title="Open file">
                            <i data-lucide="external-link"></i>
                        </a>
                        <button type="button" class="eau-media-upload-remove" id="<?php echo $id; ?>-remove" title="Remove file">
                            <i data-lucide="x"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Campo hidden para enviar o valor -->
            <input
                type="hidden"
                id="<?php echo $id; ?>"
                name="<?php echo $name; ?>"
                value="<?php echo $value; ?>"
                class="eau-media-upload-value"
            >

            <!-- Campo para indicar o tipo de prova (URL ou Upload) -->
            <input
                type="hidden"
                id="<?php echo $id; ?>-type"
                name="<?php echo $name; ?>_type"
                value="<?php echo ($is_url ? 'url' : (!empty($value) ? 'media' : '')); ?>"
                class="eau-media-upload-type"
            >
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Método estático para renderizar campo de formulário
     *
     * @param string $id ID do componente
     * @param string $name Nome do campo
     * @param string $value Valor inicial (URL ou attachment ID)
     * @param array $config Configuração adicional
     * @return string HTML do campo
     */
    public static function field($id, $name, $value = '', $config = array()) {
        $config['id'] = $id;
        $config['name'] = $name;
        $config['value'] = $value;

        $upload = new self($config);
        return $upload->render();
    }

    /**
     * Enfileira os assets necessários
     * Deve ser chamado no enqueue_scripts da página
     */
    public static function enqueue_assets() {
        // Não precisa mais do wp_enqueue_media() pois usamos upload próprio
    }
}
