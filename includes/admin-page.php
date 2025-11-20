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
        Sistema para importação de CSV e criação dinâmica de Post Types e Usuários compatível com JetEngine e WooCommerce
    </p>

    <!-- Tabs de Navegação -->
    <h2 class="nav-tab-wrapper">
        <a href="#" class="nav-tab nav-tab-active" data-tab="post-types">
            <span class="dashicons dashicons-admin-post"></span> Post Types
        </a>
        <a href="#" class="nav-tab" data-tab="users">
            <span class="dashicons dashicons-admin-users"></span> Usuários
        </a>
    </h2>

    <!-- Tab: Post Types -->
    <div class="eau-tab-content eau-tab-post-types" style="display: block;">
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
                        echo '<button type="button" class="button button-small button-primary eau-import-data" data-slug="' . esc_attr($slug) . '" data-name="' . esc_attr($config['name']) . '" data-fields="' . esc_attr(json_encode($config['meta_fields'])) . '">';
                        echo '<span class="dashicons dashicons-upload"></span> Importar Dados';
                        echo '</button>';
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

    <!-- Modal de Importação -->
    <div id="eau-import-modal" class="eau-modal" style="display: none;">
        <div class="eau-modal-overlay"></div>
        <div class="eau-modal-content">
            <div class="eau-modal-header">
                <h2>
                    <span class="dashicons dashicons-upload"></span>
                    Importar Dados do CSV
                </h2>
                <button type="button" class="eau-modal-close">
                    <span class="dashicons dashicons-no"></span>
                </button>
            </div>

            <div class="eau-modal-body">
                <div id="eau-import-step-1" class="eau-import-step">
                    <h3>Passo 1: Upload do CSV</h3>
                    <p class="description">Faça upload do arquivo CSV com os dados a serem importados.</p>

                    <form id="eau-import-upload-form" enctype="multipart/form-data">
                        <div class="form-group">
                            <input type="file" name="import_csv_file" id="import_csv_file" accept=".csv" required>
                        </div>
                        <input type="hidden" id="import_post_type_slug" name="post_type_slug">

                        <button type="submit" class="button button-primary">
                            <span class="dashicons dashicons-upload"></span>
                            Analisar CSV
                        </button>
                    </form>

                    <div id="eau-import-upload-progress" style="display: none;">
                        <div class="eau-spinner"></div>
                        <p>Analisando arquivo...</p>
                    </div>
                </div>

                <div id="eau-import-step-2" class="eau-import-step" style="display: none;">
                    <h3>Passo 2: Mapeamento de Colunas</h3>
                    <p class="description">Associe as colunas do CSV aos campos do Post Type.</p>

                    <div id="eau-import-info" class="eau-info-box">
                        <!-- Info será inserida via JS -->
                    </div>

                    <div id="eau-column-mapping" class="eau-mapping-container">
                        <!-- Mapeamento será inserido via JS -->
                    </div>

                    <div class="eau-actions">
                        <button type="button" id="eau-import-back" class="button button-secondary">
                            <span class="dashicons dashicons-arrow-left-alt"></span>
                            Voltar
                        </button>
                        <button type="button" id="eau-start-import" class="button button-primary button-large">
                            <span class="dashicons dashicons-arrow-right-alt"></span>
                            Próximo: Condicionais
                        </button>
                    </div>
                </div>

                <div id="eau-import-step-3" class="eau-import-step" style="display: none;">
                    <h3>Passo 3: Condições de Importação (Opcional)</h3>
                    <div id="eau-conditions-container">
                        <!-- Condições serão inseridas via JS -->
                    </div>

                    <div class="eau-actions">
                        <button type="button" id="eau-conditions-back" class="button button-secondary">
                            <span class="dashicons dashicons-arrow-left-alt"></span>
                            Voltar
                        </button>
                        <button type="button" id="eau-start-import-btn" class="button button-primary button-large">
                            <span class="dashicons dashicons-database-import"></span>
                            Iniciar Importação
                        </button>
                    </div>
                </div>

                <div id="eau-import-step-4" class="eau-import-step" style="display: none;">
                    <h3>Passo 4: Importando Dados</h3>

                    <div class="eau-import-progress-container">
                        <div class="eau-progress-bar">
                            <div class="eau-progress-fill" id="eau-import-progress-fill"></div>
                        </div>
                        <p class="eau-progress-text" id="eau-import-progress-text">0 de 0 itens importados</p>
                    </div>

                    <div id="eau-import-log" class="eau-import-log">
                        <!-- Log de importação -->
                    </div>
                </div>

                <div id="eau-import-step-5" class="eau-import-step" style="display: none;">
                    <h3>
                        <span class="dashicons dashicons-yes-alt" style="color: #00a32a;"></span>
                        Importação Concluída!
                    </h3>

                    <div id="eau-import-summary" class="eau-notice success">
                        <!-- Resumo será inserido via JS -->
                    </div>

                    <div class="eau-actions">
                        <button type="button" id="eau-import-close" class="button button-primary">
                            Fechar
                        </button>
                        <a href="#" id="eau-import-view-posts" class="button button-secondary" target="_blank">
                            <span class="dashicons dashicons-list-view"></span>
                            Ver Posts Importados
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>
    </div>
    <!-- Fim Tab: Post Types -->

    <!-- Tab: Usuários -->
    <div class="eau-tab-content eau-tab-users" style="display: none;">
    <div class="eau-system-container">

        <!-- Etapa 1: Upload do CSV para User Meta Box -->
        <div class="eau-card" id="eau-user-step-1">
            <div class="eau-card-header">
                <h2>
                    <span class="step-number">1</span>
                    Upload do CSV para Criar Meta Box de Usuários
                </h2>
            </div>
            <div class="eau-card-body">
                <div class="eau-info-box">
                    <h3>Como funciona?</h3>
                    <ul>
                        <li>Faça upload de um CSV com as colunas que deseja como campos de usuário</li>
                        <li>Selecione quais colunas serão campos do meta box</li>
                        <li>O sistema criará o meta box de usuários automaticamente</li>
                        <li>Depois você poderá importar usuários usando outro CSV</li>
                    </ul>
                </div>

                <form id="eau-user-csv-upload-form" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="user_csv_file">Selecione o arquivo CSV:</label>
                        <input type="file" name="user_csv_file" id="user_csv_file" accept=".csv" required>
                        <p class="description">
                            Tamanho máximo: 10MB | Formato aceito: CSV
                        </p>
                    </div>

                    <button type="submit" class="button button-primary button-large">
                        <span class="dashicons dashicons-upload"></span>
                        Fazer Upload e Analisar
                    </button>

                    <div id="eau-user-upload-progress" style="display: none;">
                        <div class="eau-spinner"></div>
                        <p>Processando arquivo...</p>
                    </div>
                </form>
            </div>
        </div>

        <!-- Etapa 2: Criar Meta Box de Usuários -->
        <div class="eau-card eau-hidden" id="eau-user-step-2">
            <div class="eau-card-header">
                <h2>
                    <span class="step-number">2</span>
                    Criar Meta Box de Usuários
                </h2>
            </div>
            <div class="eau-card-body">
                <div id="eau-user-csv-info"></div>
                <div id="eau-user-columns-preview"></div>

                <form id="eau-user-meta-box-form">
                    <div class="form-group">
                        <label for="user_meta_box_name">Nome do Meta Box:</label>
                        <input type="text" id="user_meta_box_name" name="user_meta_box_name" class="regular-text" required>
                        <p class="description">Ex: "Dados de Membros", "Informações da Instituição"</p>
                    </div>

                    <div class="form-group">
                        <label for="user_meta_key_prefix">Prefixo para Meta Keys (opcional):</label>
                        <input type="text" id="user_meta_key_prefix" name="user_meta_key_prefix" class="regular-text" placeholder="meu_prefixo">
                        <p class="description">Se informado, todos os meta keys terão este prefixo. Ex: "msp" resultará em "msp_first_name"</p>
                        <div id="eau-user-prefix-preview" class="eau-prefix-preview" style="display: none;"></div>
                    </div>

                    <div class="form-group">
                        <label>Selecione as colunas que serão campos do meta box:</label>
                        <div id="eau-user-columns-selection"></div>
                    </div>

                    <div class="eau-actions">
                        <button type="button" id="eau-user-back" class="button button-secondary">
                            <span class="dashicons dashicons-arrow-left-alt"></span>
                            Voltar
                        </button>
                        <button type="submit" class="button button-primary button-large">
                            <span class="dashicons dashicons-yes"></span>
                            Criar Meta Box
                        </button>
                    </div>

                    <div id="eau-user-create-progress" style="display: none;">
                        <div class="eau-spinner spin"></div>
                        <p>Criando meta box de usuários...</p>
                    </div>
                </form>
            </div>
        </div>

        <!-- Etapa 3: Meta Box Criado -->
        <div class="eau-card eau-success-card eau-hidden" id="eau-user-step-3">
            <div class="eau-card-header">
                <h2>
                    <span class="dashicons dashicons-yes-alt"></span>
                    Meta Box de Usuários Criado!
                </h2>
            </div>
            <div class="eau-card-body">
                <div id="eau-user-success-info"></div>

                <div class="eau-actions">
                    <button type="button" id="eau-user-create-another" class="button button-primary">
                        <span class="dashicons dashicons-plus-alt"></span>
                        Criar Outro Meta Box
                    </button>
                    <a href="<?php echo admin_url('users.php'); ?>" class="button button-secondary" target="_blank">
                        <span class="dashicons dashicons-admin-users"></span>
                        Ver Usuários
                    </a>
                </div>
            </div>
        </div>

        <!-- Meta Boxes Existentes -->
        <div class="eau-card" id="eau-existing-user-meta-boxes">
            <div class="eau-card-header">
                <h2>
                    <span class="dashicons dashicons-list-view"></span>
                    Meta Boxes de Usuários Criados
                </h2>
            </div>
            <div class="eau-card-body">
                <?php
                $user_meta_boxes = \EauSystem\Eau_User_Meta_Creator::get_registered_meta_boxes();

                if (empty($user_meta_boxes)) {
                    echo '<p class="description">Nenhum meta box de usuários criado ainda.</p>';
                } else {
                    echo '<div class="eau-post-types-list">';
                    foreach ($user_meta_boxes as $slug => $config) {
                        echo '<div class="eau-post-type-item">';
                        echo '<div class="eau-post-type-info">';
                        echo '<h3>' . esc_html($config['name']) . '</h3>';
                        echo '<p class="description">Slug: <code>' . esc_html($slug) . '</code></p>';
                        echo '<p class="description">Campos: ' . count($config['meta_fields']) . '</p>';
                        echo '<p class="description">Criado em: ' . esc_html($config['created_at']) . '</p>';
                        echo '</div>';
                        echo '<div class="eau-post-type-actions">';
                        echo '<button type="button" class="button button-small button-primary eau-import-users" data-slug="' . esc_attr($slug) . '" data-name="' . esc_attr($config['name']) . '" data-fields="' . esc_attr(json_encode($config['meta_fields'])) . '">';
                        echo '<span class="dashicons dashicons-upload"></span> Importar Usuários';
                        echo '</button>';
                        echo '<button type="button" class="button button-small eau-delete-user-meta-box" data-slug="' . esc_attr($slug) . '" data-name="' . esc_attr($config['name']) . '">';
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
    <!-- Fim Tab: Usuários -->

    <!-- Modal de Importação de Usuários -->
    <?php include EAU_SYSTEM_PLUGIN_DIR . 'includes/admin-page-users-import-modal.php'; ?>

</div>
