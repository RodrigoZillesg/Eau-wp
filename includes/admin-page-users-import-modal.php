<?php
/**
 * Modal de Importação de Usuários
 */

// Previne acesso direto
if (!defined('WPINC')) {
    die;
}
?>

<div id="eau-import-users-modal" class="eau-modal" style="display: none;">
    <div class="eau-modal-overlay"></div>
    <div class="eau-modal-content">
        <div class="eau-modal-header">
            <h2>
                <span class="dashicons dashicons-upload"></span>
                Importar Usuários do CSV
            </h2>
            <button type="button" class="eau-modal-close-users">
                <span class="dashicons dashicons-no"></span>
            </button>
        </div>

        <div class="eau-modal-body">
            <!-- Step 1: Upload -->
            <div id="eau-import-users-step-1" class="eau-import-step">
                <h3>Passo 1: Upload do CSV</h3>
                <p class="description">Faça upload do arquivo CSV com os dados dos usuários.</p>

                <form id="eau-import-users-upload-form" enctype="multipart/form-data">
                    <div class="form-group">
                        <input type="file" name="import_users_csv_file" id="import_users_csv_file" accept=".csv" required>
                    </div>
                    <input type="hidden" id="import_users_meta_box_slug" name="meta_box_slug">

                    <button type="submit" class="button button-primary">
                        <span class="dashicons dashicons-upload"></span>
                        Analisar CSV
                    </button>
                </form>

                <div id="eau-import-users-upload-progress" style="display: none;">
                    <div class="eau-spinner"></div>
                    <p>Analisando arquivo...</p>
                </div>
            </div>

            <!-- Step 2: Mapeamento -->
            <div id="eau-import-users-step-2" class="eau-import-step" style="display: none;">
                <h3>Passo 2: Mapeamento de Colunas</h3>
                <p class="description">Associe as colunas do CSV aos campos de usuário.</p>

                <div id="eau-import-users-info" class="eau-info-box"></div>

                <div id="eau-users-column-mapping" class="eau-mapping-container"></div>

                <div class="eau-actions">
                    <button type="button" id="eau-import-users-back" class="button button-secondary">
                        <span class="dashicons dashicons-arrow-left-alt"></span>
                        Voltar
                    </button>
                    <button type="button" id="eau-start-users-import" class="button button-primary button-large">
                        <span class="dashicons dashicons-arrow-right-alt"></span>
                        Próximo: Condicionais
                    </button>
                </div>
            </div>

            <!-- Step 3: Condicionais -->
            <div id="eau-import-users-step-3" class="eau-import-step" style="display: none;">
                <h3>Passo 3: Condições de Importação (Opcional)</h3>
                <div id="eau-users-conditions-container"></div>

                <div class="eau-actions">
                    <button type="button" id="eau-users-conditions-back" class="button button-secondary">
                        <span class="dashicons dashicons-arrow-left-alt"></span>
                        Voltar
                    </button>
                    <button type="button" id="eau-start-users-import-btn" class="button button-primary button-large">
                        <span class="dashicons dashicons-arrow-right-alt"></span>
                        Próximo: Configurações
                    </button>
                </div>
            </div>

            <!-- Step 4: Configurações de Importação -->
            <div id="eau-import-users-step-4" class="eau-import-step" style="display: none;">
                <h3>Passo 4: Configurações de Importação</h3>

                <div class="form-group">
                    <label for="eau-user-role">Perfil (Role) dos Usuários:</label>
                    <select id="eau-user-role" class="regular-text">
                        <?php
                        $roles = \EauSystem\Eau_Roles::get_available_roles();
                        foreach ($roles as $role_key => $role_label) {
                            $selected = ($role_key === 'eau_member') ? 'selected' : '';
                            echo '<option value="' . esc_attr($role_key) . '" ' . $selected . '>' . esc_html($role_label) . '</option>';
                        }
                        ?>
                    </select>
                    <p class="description">Todos os usuários importados terão este perfil</p>
                </div>

                <div class="form-group">
                    <label for="eau-default-password">Senha Padrão (opcional):</label>
                    <input type="text" id="eau-default-password" class="regular-text" placeholder="Deixe vazio para gerar automaticamente">
                    <p class="description">Se não houver coluna de senha no CSV, esta será usada. Vazio = senha aleatória</p>
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" id="eau-send-email" value="1">
                        Enviar email de boas-vindas para os novos usuários
                    </label>
                    <p class="description">Os usuários receberão email com login e senha</p>
                </div>

                <div class="eau-actions">
                    <button type="button" id="eau-users-config-back" class="button button-secondary">
                        <span class="dashicons dashicons-arrow-left-alt"></span>
                        Voltar
                    </button>
                    <button type="button" id="eau-confirm-users-import-btn" class="button button-primary button-large">
                        <span class="dashicons dashicons-database-import"></span>
                        Iniciar Importação
                    </button>
                </div>
            </div>

            <!-- Step 5: Progresso -->
            <div id="eau-import-users-step-5" class="eau-import-step" style="display: none;">
                <h3>Passo 5: Importando Usuários</h3>

                <div class="eau-import-progress-container">
                    <div class="eau-progress-bar">
                        <div class="eau-progress-fill" id="eau-import-users-progress-fill"></div>
                    </div>
                    <p class="eau-progress-text" id="eau-import-users-progress-text">0 de 0 usuários importados</p>
                </div>

                <div id="eau-import-users-log" class="eau-import-log"></div>
            </div>

            <!-- Step 6: Resumo -->
            <div id="eau-import-users-step-6" class="eau-import-step" style="display: none;">
                <h3>
                    <span class="dashicons dashicons-yes-alt" style="color: #00a32a;"></span>
                    Importação Concluída!
                </h3>

                <div id="eau-import-users-summary" class="eau-notice success"></div>

                <div class="eau-actions">
                    <button type="button" id="eau-import-users-close" class="button button-primary">
                        Fechar
                    </button>
                    <a href="<?php echo admin_url('users.php'); ?>" class="button button-secondary" target="_blank">
                        <span class="dashicons dashicons-admin-users"></span>
                        Ver Usuários
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
