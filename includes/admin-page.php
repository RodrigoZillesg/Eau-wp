<?php
/**
 * Template da página administrativa do Eau System
 */

// Previne acesso direto
if (!defined('WPINC')) {
    die;
}
?>

<div class="wrap eau-system-admin">
    <h1>
        <span class="dashicons dashicons-database-import"></span>
        <?php echo esc_html(get_admin_page_title()); ?>
    </h1>

    <p class="description">
        Sistema para importação de CSV e criação dinâmica de Post Types compatível com JetEngine e WooCommerce
    </p>

    <div class="eau-system-container">
        <!-- Etapa 1: Upload do CSV -->
        <div class="eau-card" id="eau-step-1">
            <div class="eau-card-header">
                <h2>
                    <span class="step-number">1</span>
                    Upload do Arquivo CSV
                </h2>
            </div>
            <div class="eau-card-body">
                <form id="eau-csv-upload-form" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="csv_file">Selecione o arquivo CSV:</label>
                        <input type="file" name="csv_file" id="csv_file" accept=".csv" required>
                        <p class="description">
                            Tamanho máximo: 10MB | Formato aceito: CSV
                        </p>
                    </div>

                    <button type="submit" class="button button-primary button-large">
                        <span class="dashicons dashicons-upload"></span>
                        Fazer Upload e Analisar
                    </button>

                    <div id="eau-upload-progress" style="display: none;">
                        <div class="eau-spinner"></div>
                        <p>Processando arquivo...</p>
                    </div>
                </form>
            </div>
        </div>

        <!-- Etapa 2: Análise do CSV e Seleção de Colunas -->
        <div class="eau-card" id="eau-step-2" style="display: none;">
            <div class="eau-card-header">
                <h2>
                    <span class="step-number">2</span>
                    Análise do CSV e Configuração do Post Type
                </h2>
            </div>
            <div class="eau-card-body">
                <div id="eau-csv-info" class="eau-info-box">
                    <!-- Informações do CSV serão inseridas aqui via JS -->
                </div>

                <form id="eau-create-post-type-form">
                    <div class="form-group">
                        <label for="post_type_name">Nome do Post Type:</label>
                        <input type="text"
                               id="post_type_name"
                               name="post_type_name"
                               class="regular-text"
                               placeholder="Ex: Produtos, Clientes, Imóveis"
                               required>
                        <p class="description">
                            Digite o nome que deseja dar ao seu Post Type customizado
                        </p>
                    </div>

                    <div class="form-group">
                        <label for="meta_key_prefix">Prefixo para Meta Keys (opcional):</label>
                        <input type="text"
                               id="meta_key_prefix"
                               name="meta_key_prefix"
                               class="regular-text"
                               placeholder="Ex: msp, custom, site"
                               pattern="[a-z0-9_]*"
                               maxlength="20">
                        <p class="description">
                            Opcional: Digite um prefixo para ser usado nos meta keys dos campos (ex: "msp" → "msp_first_name").
                            Use apenas letras minúsculas, números e underscore.
                        </p>
                        <div id="prefix-preview" class="eau-prefix-preview" style="display: none;">
                            <strong>Exemplo:</strong> <code id="prefix-example"></code>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Selecione as colunas que deseja incluir como campos:</label>
                        <div id="eau-columns-list" class="eau-columns-grid">
                            <!-- Colunas serão inseridas aqui via JS -->
                        </div>
                        <p class="description">
                            Marque as colunas do CSV que você deseja adicionar como campos do Post Type
                        </p>
                    </div>

                    <div class="form-group">
                        <h3>Preview dos Dados:</h3>
                        <div id="eau-data-preview" class="eau-table-wrapper">
                            <!-- Preview será inserido aqui via JS -->
                        </div>
                    </div>

                    <input type="hidden" id="csv_filename" name="csv_filename">

                    <div class="eau-actions">
                        <button type="button" id="eau-back-button" class="button button-secondary">
                            <span class="dashicons dashicons-arrow-left-alt"></span>
                            Voltar
                        </button>
                        <button type="submit" class="button button-primary button-large">
                            <span class="dashicons dashicons-plus-alt"></span>
                            Criar Post Type
                        </button>
                    </div>

                    <div id="eau-create-progress" style="display: none;">
                        <div class="eau-spinner"></div>
                        <p>Criando Post Type...</p>
                    </div>
                </form>
            </div>
        </div>

        <!-- Etapa 3: Sucesso -->
        <div class="eau-card eau-success-card" id="eau-step-3" style="display: none;">
            <div class="eau-card-header">
                <h2>
                    <span class="dashicons dashicons-yes-alt"></span>
                    Post Type Criado com Sucesso!
                </h2>
            </div>
            <div class="eau-card-body">
                <div id="eau-success-info">
                    <!-- Informações de sucesso serão inseridas aqui via JS -->
                </div>

                <div class="eau-actions">
                    <button type="button" id="eau-create-another" class="button button-primary">
                        <span class="dashicons dashicons-plus-alt"></span>
                        Criar Outro Post Type
                    </button>
                    <a href="<?php echo admin_url('edit.php?post_type='); ?>" id="eau-view-posts" class="button button-secondary" target="_blank">
                        <span class="dashicons dashicons-list-view"></span>
                        Ver Posts
                    </a>
                </div>
            </div>
        </div>

        <!-- Post Types Existentes -->
        <div class="eau-card" id="eau-existing-post-types">
            <div class="eau-card-header">
                <h2>
                    <span class="dashicons dashicons-list-view"></span>
                    Post Types Criados pelo Eau System
                </h2>
            </div>
            <div class="eau-card-body">
                <?php
                $post_types = \EauSystem\Eau_Post_Type_Creator::get_registered_post_types();

                if (empty($post_types)) {
                    echo '<p class="description">Nenhum Post Type criado ainda.</p>';
                } else {
                    echo '<div class="eau-post-types-list">';
                    foreach ($post_types as $slug => $config) {
                        echo '<div class="eau-post-type-item">';
                        echo '<div class="eau-post-type-info">';
                        echo '<h3>' . esc_html($config['name']) . '</h3>';
                        echo '<p class="description">Slug: <code>' . esc_html($slug) . '</code></p>';
                        echo '<p class="description">Campos: ' . count($config['meta_fields']) . '</p>';
                        echo '<p class="description">Criado em: ' . esc_html($config['created_at']) . '</p>';
                        echo '</div>';
                        echo '<div class="eau-post-type-actions">';
                        echo '<a href="' . admin_url('edit.php?post_type=' . $slug) . '" class="button button-small">';
                        echo '<span class="dashicons dashicons-list-view"></span> Ver Posts';
                        echo '</a>';
                        echo '<button type="button" class="button button-small eau-delete-post-type" data-slug="' . esc_attr($slug) . '" data-name="' . esc_attr($config['name']) . '">';
                        echo '<span class="dashicons dashicons-trash"></span> Excluir';
                        echo '</button>';
                        echo '</div>';
                        echo '</div>';
                    }
                    echo '</div>';
                }
                ?>
            </div>
        </div>
    </div>
</div>
