/**
 * Eau System - Admin JavaScript
 */

(function($) {
    'use strict';

    let csvData = null;

    let importData = {
        postTypeSlug: '',
        postTypeName: '',
        fields: [],
        csvFilename: '',
        csvColumns: [],
        rowCount: 0,
        columnMapping: {}
    };

    // Dados para importação de usuários
    let userCsvData = null;

    let importUsersData = {
        metaBoxSlug: '',
        metaBoxName: '',
        fields: [],
        csvFilename: '',
        csvColumns: [],
        rowCount: 0,
        columnMapping: {},
        conditions: [],
        userRole: 'eau_member',
        sendEmail: false,
        defaultPassword: ''
    };

    $(document).ready(function() {
        // Post Types
        initUploadForm();
        initCreatePostTypeForm();
        initBackButton();
        initCreateAnotherButton();
        initDeleteButtons();
        initPrefixPreview();
        initImportButtons();
        initImportModal();

        // Tabs
        initTabs();

        // User Meta Boxes
        initUserUploadForm();
        initUserMetaBoxForm();
        initUserBackButton();
        initUserCreateAnotherButton();
        initUserDeleteButtons();
        initUserPrefixPreview();
        initUserImportButtons();
        initUserImportModal();
    });

    /**
     * Inicializa o formulário de upload
     */
    function initUploadForm() {
        $('#eau-csv-upload-form').on('submit', function(e) {
            e.preventDefault();

            const fileInput = $('#csv_file')[0];
            if (!fileInput.files || !fileInput.files[0]) {
                showNotice('error', 'Selecione um arquivo CSV.');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'eau_upload_csv');
            formData.append('nonce', eauSystem.nonce);
            formData.append('csv_file', fileInput.files[0]);

            showProgress('#eau-upload-progress');
            disableForm('#eau-csv-upload-form');

            $.ajax({
                url: eauSystem.ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        csvData = response.data;
                        showStep2(response.data);
                    } else {
                        showNotice('error', response.data.message || eauSystem.strings.uploadError);
                    }
                },
                error: function() {
                    showNotice('error', eauSystem.strings.uploadError);
                },
                complete: function() {
                    hideProgress('#eau-upload-progress');
                    enableForm('#eau-csv-upload-form');
                }
            });
        });
    }

    /**
     * Inicializa o formulário de criação de post type
     */
    function initCreatePostTypeForm() {
        $('#eau-create-post-type-form').on('submit', function(e) {
            e.preventDefault();

            const postTypeName = $('#post_type_name').val().trim();
            const metaKeyPrefix = $('#meta_key_prefix').val().trim();
            const selectedColumns = [];

            $('.eau-column-checkbox:checked').each(function() {
                selectedColumns.push($(this).val());
            });

            // Validações
            if (!postTypeName) {
                showNotice('error', eauSystem.strings.postTypeName);
                return;
            }

            if (selectedColumns.length === 0) {
                showNotice('error', eauSystem.strings.selectColumns);
                return;
            }

            const data = {
                action: 'eau_create_post_type',
                nonce: eauSystem.nonce,
                post_type_name: postTypeName,
                meta_key_prefix: metaKeyPrefix,
                selected_columns: selectedColumns,
                csv_filename: $('#csv_filename').val()
            };

            showProgress('#eau-create-progress');
            disableForm('#eau-create-post-type-form');

            $.ajax({
                url: eauSystem.ajaxurl,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        showStep3(response.data);
                    } else {
                        showNotice('error', response.data.message || eauSystem.strings.createError);
                    }
                },
                error: function() {
                    showNotice('error', eauSystem.strings.createError);
                },
                complete: function() {
                    hideProgress('#eau-create-progress');
                    enableForm('#eau-create-post-type-form');
                }
            });
        });
    }

    /**
     * Inicializa botão de voltar
     */
    function initBackButton() {
        $('#eau-back-button').on('click', function() {
            $('#eau-step-2').hide();
            $('#eau-step-1').show();
            $('#eau-csv-upload-form')[0].reset();
            csvData = null;
        });
    }

    /**
     * Inicializa botão de criar outro
     */
    function initCreateAnotherButton() {
        $('#eau-create-another').on('click', function() {
            $('#eau-step-3').hide();
            $('#eau-step-1').show();
            $('#eau-csv-upload-form')[0].reset();
            $('#eau-create-post-type-form')[0].reset();
            csvData = null;
            location.reload(); // Recarrega para atualizar lista de post types
        });
    }

    /**
     * Exibe a etapa 2 com os dados do CSV
     */
    function showStep2(data) {
        // Exibe informações do CSV
        const infoHtml = `
            <h3>Arquivo Analisado com Sucesso!</h3>
            <ul>
                <li><strong>Arquivo:</strong> ${escapeHtml(data.filename)}</li>
                <li><strong>Colunas encontradas:</strong> ${data.columns.length}</li>
                <li><strong>Linhas de dados:</strong> ${data.row_count}</li>
            </ul>
        `;
        $('#eau-csv-info').html(infoHtml);

        // Exibe colunas para seleção
        let columnsHtml = '';
        data.columns.forEach(function(column, index) {
            const fieldType = detectFieldType(column);
            columnsHtml += `
                <div class="eau-column-item">
                    <input type="checkbox"
                           class="eau-column-checkbox"
                           id="column_${index}"
                           value="${escapeHtml(column)}"
                           checked>
                    <label for="column_${index}">
                        ${escapeHtml(column)}
                        <br>
                        <span class="eau-column-type">(${fieldType})</span>
                    </label>
                </div>
            `;
        });
        $('#eau-columns-list').html(columnsHtml);

        // Exibe preview dos dados
        let previewHtml = '<table><thead><tr>';
        data.columns.forEach(function(column) {
            previewHtml += `<th>${escapeHtml(column)}</th>`;
        });
        previewHtml += '</tr></thead><tbody>';

        data.sample_data.forEach(function(row) {
            previewHtml += '<tr>';
            data.columns.forEach(function(column) {
                const value = row[column] || '';
                previewHtml += `<td>${escapeHtml(value)}</td>`;
            });
            previewHtml += '</tr>';
        });

        previewHtml += '</tbody></table>';
        $('#eau-data-preview').html(previewHtml);

        // Armazena nome do arquivo
        $('#csv_filename').val(data.filename);

        // Transição de etapas
        $('#eau-step-1').hide();
        $('#eau-step-2').show();

        // Scroll para o topo
        $('html, body').animate({ scrollTop: $('#eau-step-2').offset().top - 100 }, 500);
    }

    /**
     * Exibe a etapa 3 com sucesso
     */
    function showStep3(data) {
        const successHtml = `
            <div class="eau-notice success">
                <h3>Post Type "${escapeHtml(data.post_type_name)}" criado com sucesso!</h3>
                <p><strong>Slug:</strong> <code>${escapeHtml(data.post_type_slug)}</code></p>
                <p><strong>Campos criados:</strong> ${data.meta_fields.length}</p>
                <ul>
                    ${data.meta_fields.map(field => `
                        <li>${escapeHtml(field.title)} <em>(${field.type})</em></li>
                    `).join('')}
                </ul>
            </div>
        `;

        $('#eau-success-info').html(successHtml);

        // Atualiza link para ver posts
        $('#eau-view-posts').attr('href',
            eauSystem.ajaxurl.replace('admin-ajax.php', 'edit.php?post_type=' + data.post_type_slug)
        );

        // Transição de etapas
        $('#eau-step-2').hide();
        $('#eau-step-3').show();

        // Scroll para o topo
        $('html, body').animate({ scrollTop: $('#eau-step-3').offset().top - 100 }, 500);
    }

    /**
     * Detecta o tipo de campo baseado no nome da coluna
     */
    function detectFieldType(columnName) {
        const column = columnName.toLowerCase();

        if (column.includes('email')) return 'E-mail';
        if (column.includes('url') || column.includes('link')) return 'URL';
        if (column.includes('phone') || column.includes('telefone')) return 'Telefone';
        if (column.includes('date') || column.includes('data')) return 'Data';
        if (column.includes('price') || column.includes('preco') || column.includes('valor')) return 'Número';
        if (column.includes('image') || column.includes('imagem') || column.includes('foto')) return 'Mídia';
        if (column.includes('description') || column.includes('descricao')) return 'Texto Longo';

        return 'Texto';
    }

    /**
     * Exibe notificação
     */
    function showNotice(type, message) {
        const notice = $(`
            <div class="notice notice-${type} is-dismissible">
                <p>${message}</p>
            </div>
        `);

        $('.eau-system-admin h1').after(notice);

        // Remove após 5 segundos
        setTimeout(function() {
            notice.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    }

    /**
     * Exibe indicador de progresso
     */
    function showProgress(selector) {
        $(selector).show();
    }

    /**
     * Esconde indicador de progresso
     */
    function hideProgress(selector) {
        $(selector).hide();
    }

    /**
     * Desabilita formulário
     */
    function disableForm(selector) {
        $(selector).find('input, button').prop('disabled', true);
    }

    /**
     * Habilita formulário
     */
    function enableForm(selector) {
        $(selector).find('input, button').prop('disabled', false);
    }

    /**
     * Inicializa botões de exclusão
     */
    function initDeleteButtons() {
        $(document).on('click', '.eau-delete-post-type', function() {
            const button = $(this);
            const slug = button.data('slug');
            const name = button.data('name');

            // Confirmação
            if (!confirm(`Tem certeza que deseja excluir o Post Type "${name}"?\n\nEsta ação não pode ser desfeita!`)) {
                return;
            }

            // Desabilita botão
            button.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Excluindo...');

            const data = {
                action: 'eau_delete_post_type',
                nonce: eauSystem.nonce,
                slug: slug
            };

            $.ajax({
                url: eauSystem.ajaxurl,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        showNotice('success', `Post Type "${name}" excluído com sucesso!`);

                        // Remove o item da lista
                        button.closest('.eau-post-type-item').fadeOut(function() {
                            $(this).remove();

                            // Se não houver mais items, mostra mensagem
                            if ($('.eau-post-type-item').length === 0) {
                                $('#eau-existing-post-types .eau-card-body').html(
                                    '<p class="description">Nenhum Post Type criado ainda.</p>'
                                );
                            }
                        });
                    } else {
                        showNotice('error', response.data.message || 'Erro ao excluir Post Type.');
                        button.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> Excluir');
                    }
                },
                error: function() {
                    showNotice('error', 'Erro ao excluir Post Type.');
                    button.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> Excluir');
                }
            });
        });
    }

    /**
     * Inicializa preview do prefixo
     */
    function initPrefixPreview() {
        $('#meta_key_prefix').on('input', function() {
            const prefix = $(this).val().trim().toLowerCase();
            const previewDiv = $('#prefix-preview');
            const exampleCode = $('#prefix-example');

            if (prefix) {
                // Sanitiza o prefixo para mostrar o resultado real
                const sanitizedPrefix = prefix.replace(/[^a-z0-9_]/g, '').substring(0, 20);

                // Atualiza o campo se foi sanitizado
                if (sanitizedPrefix !== prefix) {
                    $(this).val(sanitizedPrefix);
                }

                // Mostra exemplo
                const exampleField = sanitizedPrefix + '_first_name';
                exampleCode.text(exampleField);
                previewDiv.fadeIn();
            } else {
                previewDiv.fadeOut();
            }
        });
    }

    /**
     * Inicializa botões de importação
     */
    function initImportButtons() {
        $(document).on('click', '.eau-import-data', function() {
            const button = $(this);
            importData.postTypeSlug = button.data('slug');
            importData.postTypeName = button.data('name');
            importData.fields = button.data('fields');

            // Abre modal
            $('#eau-import-modal').fadeIn();
            resetImportModal();
        });
    }

    /**
     * Inicializa modal de importação
     */
    function initImportModal() {
        // Fechar modal
        $('.eau-modal-close, #eau-import-close').on('click', function() {
            $('#eau-import-modal').fadeOut();
            resetImportModal();
        });

        // Fechar ao clicar no overlay
        $('.eau-modal-overlay').on('click', function() {
            $('#eau-import-modal').fadeOut();
            resetImportModal();
        });

        // Upload do CSV para importação
        $('#eau-import-upload-form').on('submit', function(e) {
            e.preventDefault();

            const fileInput = $('#import_csv_file')[0];
            if (!fileInput.files || !fileInput.files[0]) {
                showNotice('error', 'Selecione um arquivo CSV.');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'eau_import_analyze_csv');
            formData.append('nonce', eauSystem.nonce);
            formData.append('import_csv_file', fileInput.files[0]);
            formData.append('post_type_slug', importData.postTypeSlug);

            showProgress('#eau-import-upload-progress');
            disableForm('#eau-import-upload-form');

            $.ajax({
                url: eauSystem.ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        importData.csvFilename = response.data.filename;
                        importData.csvColumns = response.data.columns;
                        importData.rowCount = response.data.row_count;
                        showImportStep2(response.data);
                    } else {
                        showNotice('error', response.data.message || 'Erro ao analisar CSV.');
                    }
                },
                error: function() {
                    showNotice('error', 'Erro ao processar arquivo.');
                },
                complete: function() {
                    hideProgress('#eau-import-upload-progress');
                    enableForm('#eau-import-upload-form');
                }
            });
        });

        // Voltar para step 1
        $('#eau-import-back').on('click', function() {
            $('#eau-import-step-2').hide();
            $('#eau-import-step-1').show();
        });

        // Voltar do step 3 para step 2
        $(document).on('click', '#eau-conditions-back', function() {
            $('#eau-import-step-3').hide();
            $('#eau-import-step-2').show();
        });

        // Iniciar importação (vai para condicionais)
        $('#eau-start-import').on('click', function() {
            startImport();
        });
    }

    /**
     * Mostra step 2 do modal (mapeamento)
     */
    function showImportStep2(data) {
        // Info do CSV
        const infoHtml = `
            <h3>CSV Analisado</h3>
            <ul>
                <li><strong>Arquivo:</strong> ${escapeHtml(data.filename)}</li>
                <li><strong>Linhas:</strong> ${data.row_count}</li>
                <li><strong>Colunas:</strong> ${data.columns.length}</li>
            </ul>
        `;
        $('#eau-import-info').html(infoHtml);

        // Mapeamento de colunas
        let mappingHtml = '<div class="eau-mapping-list">';

        // Campo especial: Título do Post
        mappingHtml += '<div class="eau-mapping-item">';
        mappingHtml += '<label><strong>Título do Post:</strong></label>';
        mappingHtml += '<select class="eau-mapping-select" data-field="post_title">';
        mappingHtml += '<option value="">Usar primeira coluna</option>';
        data.columns.forEach(function(column) {
            mappingHtml += `<option value="${escapeHtml(column)}">${escapeHtml(column)}</option>`;
        });
        mappingHtml += '</select>';
        mappingHtml += '</div>';

        // Campo especial: Conteúdo (opcional)
        mappingHtml += '<div class="eau-mapping-item">';
        mappingHtml += '<label>Conteúdo do Post (opcional):</label>';
        mappingHtml += '<select class="eau-mapping-select" data-field="post_content">';
        mappingHtml += '<option value="">Não mapear</option>';
        data.columns.forEach(function(column) {
            mappingHtml += `<option value="${escapeHtml(column)}">${escapeHtml(column)}</option>`;
        });
        mappingHtml += '</select>';
        mappingHtml += '</div>';

        // Campo especial: Coluna Distinct (para evitar duplicatas)
        mappingHtml += '<div class="eau-mapping-item eau-distinct-field">';
        mappingHtml += '<label><strong>Coluna Distinct (Evitar Duplicatas):</strong></label>';
        mappingHtml += '<select class="eau-mapping-select" data-field="distinct_column" id="eau-distinct-column">';
        mappingHtml += '<option value="">Nenhuma (permitir duplicatas)</option>';
        data.columns.forEach(function(column) {
            mappingHtml += `<option value="${escapeHtml(column)}">${escapeHtml(column)}</option>`;
        });
        mappingHtml += '</select>';
        mappingHtml += '<p class="description">Se selecionada, posts com o mesmo valor nesta coluna serão atualizados ao invés de duplicados.</p>';
        mappingHtml += '</div>';

        mappingHtml += '<hr style="margin: 20px 0;">';
        mappingHtml += '<h4>Campos Customizados:</h4>';

        // Campos customizados
        importData.fields.forEach(function(field) {
            mappingHtml += '<div class="eau-mapping-item">';
            mappingHtml += `<label>${escapeHtml(field.title)}:</label>`;
            mappingHtml += `<select class="eau-mapping-select" data-field="${escapeHtml(field.name)}">`;
            mappingHtml += '<option value="">Não mapear</option>';

            data.columns.forEach(function(column) {
                // Auto-seleciona se os nomes coincidirem
                const selected = (normalizeForComparison(column) === normalizeForComparison(field.title)) ? 'selected' : '';
                mappingHtml += `<option value="${escapeHtml(column)}" ${selected}>${escapeHtml(column)}</option>`;
            });

            mappingHtml += '</select>';
            mappingHtml += '</div>';
        });

        mappingHtml += '</div>';
        $('#eau-column-mapping').html(mappingHtml);

        // Transição
        $('#eau-import-step-1').hide();
        $('#eau-import-step-2').show();
    }

    /**
     * Coleta mapeamento e vai para condicionais
     */
    function startImport() {
        // Coleta mapeamento
        const mapping = {
            post_title: $('[data-field="post_title"]').val(),
            post_content: $('[data-field="post_content"]').val(),
            distinct_column: $('[data-field="distinct_column"]').val(),
            fields: {},
            available_columns: importData.csvColumns
        };

        $('.eau-mapping-select').each(function() {
            const field = $(this).data('field');
            const column = $(this).val();

            if (field && field !== 'post_title' && field !== 'post_content' && field !== 'distinct_column' && column) {
                mapping.fields[field] = column;
            }
        });

        importData.columnMapping = mapping;

        // Vai para step de condicionais
        showImportStep3();
    }

    /**
     * Mostra etapa de condicionais
     */
    function showImportStep3() {
        // Cria interface de condicionais
        let conditionsHtml = '<div class="eau-conditions-container">';
        conditionsHtml += '<p>Configure condições para filtrar quais linhas do CSV serão importadas. Todas as condições devem ser atendidas (operador E).</p>';
        conditionsHtml += '<div class="eau-conditions-list" id="eau-conditions-list"></div>';
        conditionsHtml += '<button type="button" class="button button-secondary" id="eau-add-condition">';
        conditionsHtml += '<span class="dashicons dashicons-plus-alt"></span> Adicionar Condição';
        conditionsHtml += '</button>';
        conditionsHtml += '</div>';

        $('#eau-conditions-container').html(conditionsHtml);

        // Transição
        $('#eau-import-step-2').hide();
        $('#eau-import-step-3').show();

        // Event listeners
        $(document).on('click', '#eau-add-condition', addCondition);
        $(document).on('click', '.eau-remove-condition', removeCondition);
        $(document).on('click', '#eau-start-import-btn', confirmAndStartImport);
    }

    /**
     * Adiciona uma nova condição
     */
    function addCondition() {
        const conditionId = Date.now();

        let conditionHtml = '<div class="eau-condition-item" data-condition-id="' + conditionId + '">';
        conditionHtml += '<div class="eau-condition-row">';

        // Seletor de coluna
        conditionHtml += '<div class="eau-condition-field">';
        conditionHtml += '<label>Coluna</label>';
        conditionHtml += '<select class="eau-condition-column">';
        conditionHtml += '<option value="">Selecione...</option>';
        importData.csvColumns.forEach(function(column) {
            conditionHtml += '<option value="' + escapeHtml(column) + '">' + escapeHtml(column) + '</option>';
        });
        conditionHtml += '</select>';
        conditionHtml += '</div>';

        // Seletor de operador
        conditionHtml += '<div class="eau-condition-field">';
        conditionHtml += '<label>Operador</label>';
        conditionHtml += '<select class="eau-condition-operator">';
        conditionHtml += '<option value="not_empty">Não está vazio</option>';
        conditionHtml += '<option value="empty">Está vazio</option>';
        conditionHtml += '<option value="equals">É igual a</option>';
        conditionHtml += '<option value="not_equals">É diferente de</option>';
        conditionHtml += '<option value="greater_than">Maior que</option>';
        conditionHtml += '<option value="greater_than_or_equal">Maior ou igual a</option>';
        conditionHtml += '<option value="less_than">Menor que</option>';
        conditionHtml += '<option value="less_than_or_equal">Menor ou igual a</option>';
        conditionHtml += '<option value="contains">Contém</option>';
        conditionHtml += '<option value="not_contains">Não contém</option>';
        conditionHtml += '<option value="starts_with">Começa com</option>';
        conditionHtml += '<option value="ends_with">Termina com</option>';
        conditionHtml += '</select>';
        conditionHtml += '</div>';

        // Campo de valor
        conditionHtml += '<div class="eau-condition-field eau-condition-value-field">';
        conditionHtml += '<label>Valor</label>';
        conditionHtml += '<input type="text" class="eau-condition-value" placeholder="Digite o valor...">';
        conditionHtml += '</div>';

        // Botão remover
        conditionHtml += '<div class="eau-condition-actions">';
        conditionHtml += '<button type="button" class="button button-small eau-remove-condition">';
        conditionHtml += '<span class="dashicons dashicons-trash"></span>';
        conditionHtml += '</button>';
        conditionHtml += '</div>';

        conditionHtml += '</div>';
        conditionHtml += '</div>';

        $('#eau-conditions-list').append(conditionHtml);

        // Listener para mostrar/esconder campo de valor
        $('.eau-condition-operator').off('change').on('change', toggleConditionValueField);
    }

    /**
     * Remove uma condição
     */
    function removeCondition() {
        $(this).closest('.eau-condition-item').remove();
    }

    /**
     * Mostra/esconde campo de valor baseado no operador
     */
    function toggleConditionValueField() {
        const operator = $(this).val();
        const valueField = $(this).closest('.eau-condition-row').find('.eau-condition-value-field');

        if (operator === 'not_empty' || operator === 'empty') {
            valueField.hide();
        } else {
            valueField.show();
        }
    }

    /**
     * Coleta condições e inicia importação
     */
    function confirmAndStartImport() {
        // Coleta condições
        const conditions = [];

        $('.eau-condition-item').each(function() {
            const column = $(this).find('.eau-condition-column').val();
            const operator = $(this).find('.eau-condition-operator').val();
            const value = $(this).find('.eau-condition-value').val();

            if (column && operator) {
                conditions.push({
                    column: column,
                    operator: operator,
                    value: value || ''
                });
            }
        });

        importData.conditions = conditions;

        // Vai para step 4 (progresso)
        $('#eau-import-step-3').hide();
        $('#eau-import-step-4').show();

        // Inicia importação em lotes
        processBatchImport(0);
    }

    /**
     * Processa importação em lotes
     */
    function processBatchImport(offset) {
        const batchSize = 25;

        const data = {
            action: 'eau_import_batch',
            nonce: eauSystem.nonce,
            csv_filename: importData.csvFilename,
            post_type_slug: importData.postTypeSlug,
            column_mapping: JSON.stringify(importData.columnMapping),
            conditions: JSON.stringify(importData.conditions || []),
            offset: offset,
            batch_size: batchSize
        };

        $.ajax({
            url: eauSystem.ajaxurl,
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    const result = response.data;

                    // Atualiza progress bar
                    const percentage = (result.processed / result.total) * 100;
                    $('#eau-import-progress-fill').css('width', percentage + '%');
                    $('#eau-import-progress-text').text(`${result.processed} de ${result.total} itens processados`);

                    // Adiciona log
                    if (result.imported > 0) {
                        $('#eau-import-log').append(`<p class="eau-log-success">✓ Lote importado: ${result.imported} itens</p>`);
                    }
                    if (result.skipped > 0) {
                        $('#eau-import-log').append(`<p class="eau-log-warning">⚠ Ignorados: ${result.skipped} itens</p>`);
                    }

                    // Scroll do log
                    $('#eau-import-log').scrollTop($('#eau-import-log')[0].scrollHeight);

                    // Continua se tiver mais
                    if (result.has_more) {
                        processBatchImport(result.processed);
                    } else {
                        showImportComplete(result);
                    }
                } else {
                    showNotice('error', response.data.message || 'Erro na importação.');
                    $('#eau-import-log').append(`<p class="eau-log-error">✗ Erro: ${response.data.message}</p>`);
                }
            },
            error: function() {
                showNotice('error', 'Erro ao processar importação.');
                $('#eau-import-log').append('<p class="eau-log-error">✗ Erro de conexão</p>');
            }
        });
    }

    /**
     * Mostra resumo da importação completa
     */
    function showImportComplete(finalResult) {
        const summaryHtml = `
            <h3>Importação Concluída!</h3>
            <p><strong>Total de itens:</strong> ${finalResult.total}</p>
            <p><strong>Importados com sucesso:</strong> ${finalResult.imported}</p>
            <p><strong>Ignorados:</strong> ${finalResult.skipped}</p>
        `;

        $('#eau-import-summary').html(summaryHtml);
        $('#eau-import-view-posts').attr('href', eauSystem.ajaxurl.replace('admin-ajax.php', 'edit.php?post_type=' + importData.postTypeSlug));

        $('#eau-import-step-4').hide();
        $('#eau-import-step-5').show();

        showNotice('success', `${finalResult.imported} posts importados com sucesso!`);
    }

    /**
     * Reseta modal de importação
     */
    function resetImportModal() {
        $('#eau-import-step-1').show();
        $('#eau-import-step-2, #eau-import-step-3, #eau-import-step-4').hide();
        $('#eau-import-upload-form')[0].reset();
        $('#eau-import-progress-fill').css('width', '0%');
        $('#eau-import-log').empty();
        importData.columnMapping = {};
    }

    /**
     * Normaliza string para comparação
     */
    function normalizeForComparison(str) {
        return str.toLowerCase().replace(/[_\s-]/g, '');
    }

    /**
     * Escapa HTML para prevenir XSS
     */
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    // ========================================
    // FUNÇÕES PARA TABS
    // ========================================

    /**
     * Inicializa sistema de tabs
     */
    function initTabs() {
        console.log('Iniciando tabs...');
        console.log('Nav tabs encontradas:', $('.nav-tab').length);

        $('.nav-tab').off('click').on('click', function(e) {
            e.preventDefault();

            const targetTab = $(this).data('tab');
            console.log('Tab clicada:', targetTab);

            // Atualiza tabs
            $('.nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');

            // Mostra/esconde conteúdo
            $('.eau-tab-content').hide();
            $('.eau-tab-' + targetTab).show();

            console.log('Exibindo conteúdo da tab:', targetTab);
        });
    }

    // ========================================
    // FUNÇÕES PARA USER META BOXES
    // ========================================

    /**
     * Inicializa formulário de upload de CSV para user meta box
     */
    function initUserUploadForm() {
        $('#eau-user-csv-upload-form').on('submit', function(e) {
            e.preventDefault();

            const fileInput = $('#user_csv_file')[0];
            if (!fileInput.files || !fileInput.files[0]) {
                showNotice('error', 'Selecione um arquivo CSV.');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'eau_upload_csv');
            formData.append('nonce', eauSystem.nonce);
            formData.append('csv_file', fileInput.files[0]);

            showProgress('#eau-user-upload-progress');
            disableForm('#eau-user-csv-upload-form');

            $.ajax({
                url: eauSystem.ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    hideProgress('#eau-user-upload-progress');
                    enableForm('#eau-user-csv-upload-form');

                    if (response.success) {
                        userCsvData = response.data;
                        showUserStep2(response.data);
                    } else {
                        showNotice('error', response.data.message || 'Erro ao processar CSV.');
                    }
                },
                error: function() {
                    hideProgress('#eau-user-upload-progress');
                    enableForm('#eau-user-csv-upload-form');
                    showNotice('error', 'Erro ao fazer upload do arquivo.');
                }
            });
        });
    }

    /**
     * Mostra step 2 para user meta box
     */
    function showUserStep2(data) {
        // Info do CSV
        const infoHtml = `
            <h3>CSV Analisado com Sucesso!</h3>
            <p><strong>Total de linhas:</strong> ${data.row_count}</p>
            <p><strong>Total de colunas:</strong> ${data.total_columns}</p>
        `;
        $('#eau-user-csv-info').html(infoHtml);

        // Preview das primeiras linhas (se disponível)
        let previewHtml = '';
        if (data.preview && data.preview.length > 0) {
            previewHtml = '<h4>Preview dos Dados:</h4>';
            previewHtml += '<div class="eau-table-wrapper"><table><thead><tr>';

            data.columns.forEach(function(column) {
                previewHtml += `<th>${escapeHtml(column)}</th>`;
            });
            previewHtml += '</tr></thead><tbody>';

            const previewRows = data.preview.slice(0, 5);
            previewRows.forEach(function(row) {
                previewHtml += '<tr>';
                data.columns.forEach(function(column) {
                    previewHtml += `<td>${escapeHtml(row[column] || '')}</td>`;
                });
                previewHtml += '</tr>';
            });

            previewHtml += '</tbody></table></div>';
        }
        $('#eau-user-columns-preview').html(previewHtml);

        // Checkboxes de seleção de colunas
        let columnsHtml = '<div class="eau-columns-grid">';

        data.columns.forEach(function(column) {
            const columnId = 'user_col_' + column.replace(/[^a-z0-9]/gi, '_');
            columnsHtml += `
                <div class="eau-column-item">
                    <input type="checkbox" id="${columnId}" name="selected_columns[]" value="${escapeHtml(column)}" checked>
                    <label for="${columnId}">${escapeHtml(column)}</label>
                </div>
            `;
        });

        columnsHtml += '</div>';
        $('#eau-user-columns-selection').html(columnsHtml);

        // Transição
        $('#eau-user-step-1').addClass('eau-hidden');
        $('#eau-user-step-2').removeClass('eau-hidden');
    }

    /**
     * Inicializa formulário de criação de user meta box
     */
    function initUserMetaBoxForm() {
        $('#eau-user-meta-box-form').on('submit', function(e) {
            e.preventDefault();

            const metaBoxName = $('#user_meta_box_name').val();
            const metaKeyPrefix = $('#user_meta_key_prefix').val();
            const selectedColumns = [];

            $('input[name="selected_columns[]"]:checked').each(function() {
                selectedColumns.push($(this).val());
            });

            if (selectedColumns.length === 0) {
                showNotice('error', 'Selecione pelo menos uma coluna.');
                return;
            }

            const data = {
                action: 'eau_create_user_meta_box',
                nonce: eauSystem.nonce,
                meta_box_name: metaBoxName,
                selected_columns: selectedColumns,
                meta_key_prefix: metaKeyPrefix
            };

            showProgress('#eau-user-create-progress');
            disableForm('#eau-user-meta-box-form');

            $.ajax({
                url: eauSystem.ajaxurl,
                type: 'POST',
                data: data,
                success: function(response) {
                    hideProgress('#eau-user-create-progress');
                    enableForm('#eau-user-meta-box-form');

                    if (response.success) {
                        // Salva toast para exibir após reload
                        EauToast.saveForReload('success', 'Meta Box Criado!', `O meta box "${response.data.name}" foi criado com ${response.data.fields_count} campos.`);

                        // Recarrega página para atualizar lista
                        location.reload();
                    } else {
                        EauToast.error('Erro ao Criar Meta Box', response.data.message || 'Ocorreu um erro inesperado.');
                    }
                },
                error: function() {
                    hideProgress('#eau-user-create-progress');
                    enableForm('#eau-user-meta-box-form');
                    EauToast.error('Erro de Conexão', 'Não foi possível criar o meta box. Tente novamente.');
                }
            });
        });
    }

    /**
     * Mostra step 3 - sucesso
     */
    function showUserStep3(data) {
        const successHtml = `
            <p><strong>Meta Box:</strong> ${escapeHtml(data.name)}</p>
            <p><strong>Slug:</strong> <code>${escapeHtml(data.slug)}</code></p>
            <p><strong>Total de Campos:</strong> ${data.fields_count}</p>
            <p class="description">O meta box foi criado com sucesso! Agora você pode importar usuários usando este meta box.</p>
        `;
        $('#eau-user-success-info').html(successHtml);

        $('#eau-user-step-2').addClass('eau-hidden');
        $('#eau-user-step-3').removeClass('eau-hidden');

        showNotice('success', 'Meta box de usuários criado com sucesso!');
    }

    /**
     * Botão voltar
     */
    function initUserBackButton() {
        $('#eau-user-back').on('click', function() {
            $('#eau-user-step-2').addClass('eau-hidden');
            $('#eau-user-step-1').removeClass('eau-hidden');
            $('#user_csv_file').val('');
        });
    }

    /**
     * Botão criar outro
     */
    function initUserCreateAnotherButton() {
        $('#eau-user-create-another').on('click', function() {
            $('#eau-user-step-3').addClass('eau-hidden');
            $('#eau-user-step-1').removeClass('eau-hidden');
            $('#eau-user-meta-box-form')[0].reset();
            $('#user_csv_file').val('');
        });
    }

    /**
     * Botões de deletar user meta box
     */
    function initUserDeleteButtons() {
        $(document).on('click', '.eau-delete-user-meta-box', function() {
            const button = $(this);
            const slug = button.data('slug');
            const name = button.data('name');

            if (!confirm(`Tem certeza que deseja excluir o meta box "${name}"?\n\nEsta ação não pode ser desfeita.`)) {
                return;
            }

            button.prop('disabled', true);

            $.ajax({
                url: eauSystem.ajaxurl,
                type: 'POST',
                data: {
                    action: 'eau_delete_user_meta_box',
                    nonce: eauSystem.nonce,
                    slug: slug
                },
                success: function(response) {
                    if (response.success) {
                        showNotice('success', 'Meta box excluído com sucesso!');
                        button.closest('.eau-post-type-item').fadeOut(function() {
                            $(this).remove();
                        });
                    } else {
                        showNotice('error', response.data.message || 'Erro ao excluir meta box.');
                        button.prop('disabled', false);
                    }
                },
                error: function() {
                    showNotice('error', 'Erro ao excluir meta box.');
                    button.prop('disabled', false);
                }
            });
        });
    }

    /**
     * Preview de prefixo para user meta box
     */
    function initUserPrefixPreview() {
        $('#user_meta_key_prefix').on('input', function() {
            const prefix = $(this).val();
            const previewDiv = $('#eau-user-prefix-preview');

            if (prefix) {
                const exampleField = userCsvData && userCsvData.columns.length > 0 ? userCsvData.columns[0] : 'campo_exemplo';
                const sanitizedPrefix = prefix.toLowerCase().replace(/[^a-z0-9_]/gi, '');
                const finalKey = sanitizedPrefix + '_' + exampleField.toLowerCase().replace(/[^a-z0-9_]/gi, '_');

                previewDiv.html(`<strong>Preview:</strong> <code>${escapeHtml(finalKey)}</code>`);
                previewDiv.show();
            } else {
                previewDiv.hide();
            }
        });
    }

    // ========================================
    // IMPORTAÇÃO DE USUÁRIOS
    // ========================================

    /**
     * Inicializa botões de importar usuários
     */
    function initUserImportButtons() {
        $(document).on('click', '.eau-import-users', function() {
            const button = $(this);

            importUsersData.metaBoxSlug = button.data('slug');
            importUsersData.metaBoxName = button.data('name');
            importUsersData.fields = button.data('fields');

            $('#eau-import-users-modal').fadeIn();
            resetImportUsersModal();
        });
    }

    /**
     * Inicializa modal de importação de usuários
     */
    function initUserImportModal() {
        // Fechar modal
        $('.eau-modal-close-users, #eau-import-users-close').on('click', function() {
            $('#eau-import-users-modal').fadeOut();
            resetImportUsersModal();
        });

        // Upload do CSV
        $('#eau-import-users-upload-form').on('submit', function(e) {
            e.preventDefault();

            const fileInput = $('#import_users_csv_file')[0];
            if (!fileInput.files || !fileInput.files[0]) {
                showNotice('error', 'Selecione um arquivo CSV.');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'eau_import_users_analyze_csv');
            formData.append('nonce', eauSystem.nonce);
            formData.append('import_users_csv_file', fileInput.files[0]);
            formData.append('meta_box_slug', importUsersData.metaBoxSlug);

            showProgress('#eau-import-users-upload-progress');
            disableForm('#eau-import-users-upload-form');

            $.ajax({
                url: eauSystem.ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    hideProgress('#eau-import-users-upload-progress');
                    enableForm('#eau-import-users-upload-form');

                    if (response.success) {
                        importUsersData.csvFilename = response.data.filename;
                        importUsersData.csvColumns = response.data.columns;
                        importUsersData.rowCount = response.data.row_count;

                        showImportUsersStep2(response.data);
                    } else {
                        showNotice('error', response.data.message || 'Erro ao processar CSV.');
                    }
                },
                error: function() {
                    hideProgress('#eau-import-users-upload-progress');
                    enableForm('#eau-import-users-upload-form');
                    showNotice('error', 'Erro ao fazer upload.');
                }
            });
        });

        // Navegação
        $('#eau-import-users-back').on('click', function() {
            $('#eau-import-users-step-2').hide();
            $('#eau-import-users-step-1').show();
        });

        $(document).on('click', '#eau-users-conditions-back', function() {
            $('#eau-import-users-step-3').hide();
            $('#eau-import-users-step-2').show();
        });

        $(document).on('click', '#eau-users-config-back', function() {
            $('#eau-import-users-step-4').hide();
            $('#eau-import-users-step-3').show();
        });

        $('#eau-start-users-import').on('click', function() {
            startUsersImport();
        });

        $(document).on('click', '#eau-start-users-import-btn', function() {
            showImportUsersStep4();
        });

        $(document).on('click', '#eau-confirm-users-import-btn', function() {
            confirmAndStartUsersImport();
        });
    }

    /**
     * Mostra step 2 - mapeamento
     */
    function showImportUsersStep2(data) {
        const infoHtml = `
            <h3>CSV Analisado</h3>
            <p><strong>Total de linhas:</strong> ${data.row_count}</p>
            <p><strong>Colunas encontradas:</strong> ${data.columns.length}</p>
        `;
        $('#eau-import-users-info').html(infoHtml);

        // Mapeamento de campos nativos
        let mappingHtml = '<div class="eau-mapping-list">';

        // Campos obrigatórios do usuário
        const userFields = [
            { key: 'user_email', label: 'Email (obrigatório)', required: true },
            { key: 'user_login', label: 'Username (opcional, gerado do email se vazio)', required: false },
            { key: 'user_pass', label: 'Senha (opcional)', required: false },
            { key: 'first_name', label: 'Primeiro Nome', required: false },
            { key: 'last_name', label: 'Sobrenome', required: false },
            { key: 'display_name', label: 'Nome de Exibição', required: false }
        ];

        userFields.forEach(function(field) {
            mappingHtml += '<div class="eau-mapping-item">';
            mappingHtml += `<label>${field.label}${field.required ? ' *' : ''}</label>`;
            mappingHtml += `<select class="eau-mapping-select" data-field="${field.key}">`;
            mappingHtml += '<option value="">-- Não mapear --</option>';

            data.columns.forEach(function(column) {
                const selected = (normalizeForComparison(column) === normalizeForComparison(field.key)) ? 'selected' : '';
                mappingHtml += `<option value="${escapeHtml(column)}" ${selected}>${escapeHtml(column)}</option>`;
            });

            mappingHtml += '</select>';
            mappingHtml += '</div>';
        });

        // Meta fields customizados
        if (importUsersData.fields && importUsersData.fields.length > 0) {
            mappingHtml += '<h4 style="margin-top: 20px;">Campos Customizados do Meta Box</h4>';

            importUsersData.fields.forEach(function(field) {
                mappingHtml += '<div class="eau-mapping-item">';
                mappingHtml += `<label>${field.title}</label>`;
                mappingHtml += `<select class="eau-mapping-select-meta" data-field="${field.name}">`;
                mappingHtml += '<option value="">-- Não mapear --</option>';

                data.columns.forEach(function(column) {
                    const selected = (normalizeForComparison(column) === normalizeForComparison(field.title)) ? 'selected' : '';
                    mappingHtml += `<option value="${escapeHtml(column)}" ${selected}>${escapeHtml(column)}</option>`;
                });

                mappingHtml += '</select>';
                mappingHtml += '</div>';
            });
        }

        mappingHtml += '</div>';
        $('#eau-users-column-mapping').html(mappingHtml);

        $('#eau-import-users-step-1').hide();
        $('#eau-import-users-step-2').show();
    }

    /**
     * Inicia processo (vai para condicionais)
     */
    function startUsersImport() {
        // Coleta mapeamento
        const mapping = {
            meta_fields: {}
        };

        $('.eau-mapping-select').each(function() {
            const field = $(this).data('field');
            const column = $(this).val();

            if (field && column) {
                mapping[field] = column;
            }
        });

        $('.eau-mapping-select-meta').each(function() {
            const field = $(this).data('field');
            const column = $(this).val();

            if (field && column) {
                mapping.meta_fields[field] = column;
            }
        });

        // Valida email obrigatório
        if (!mapping.user_email) {
            showNotice('error', 'O campo Email é obrigatório.');
            return;
        }

        importUsersData.columnMapping = mapping;

        showImportUsersStep3();
    }

    /**
     * Mostra step 3 - condicionais
     */
    function showImportUsersStep3() {
        let conditionsHtml = '<div class="eau-conditions-container">';
        conditionsHtml += '<p>Configure condições para filtrar quais linhas do CSV serão importadas. Todas as condições devem ser atendidas (operador E).</p>';
        conditionsHtml += '<div class="eau-conditions-list" id="eau-users-conditions-list"></div>';
        conditionsHtml += '<button type="button" class="button button-secondary" id="eau-add-user-condition">';
        conditionsHtml += '<span class="dashicons dashicons-plus-alt"></span> Adicionar Condição';
        conditionsHtml += '</button>';
        conditionsHtml += '</div>';

        $('#eau-users-conditions-container').html(conditionsHtml);

        $('#eau-import-users-step-2').hide();
        $('#eau-import-users-step-3').show();

        $(document).on('click', '#eau-add-user-condition', addUserCondition);
        $(document).on('click', '.eau-remove-user-condition', removeUserCondition);
    }

    /**
     * Adiciona condição de usuário
     */
    function addUserCondition() {
        const conditionId = Date.now();

        let conditionHtml = '<div class="eau-condition-item" data-condition-id="' + conditionId + '">';
        conditionHtml += '<div class="eau-condition-row">';

        conditionHtml += '<div class="eau-condition-field">';
        conditionHtml += '<label>Coluna</label>';
        conditionHtml += '<select class="eau-user-condition-column">';
        conditionHtml += '<option value="">Selecione...</option>';
        importUsersData.csvColumns.forEach(function(column) {
            conditionHtml += '<option value="' + escapeHtml(column) + '">' + escapeHtml(column) + '</option>';
        });
        conditionHtml += '</select>';
        conditionHtml += '</div>';

        conditionHtml += '<div class="eau-condition-field">';
        conditionHtml += '<label>Operador</label>';
        conditionHtml += '<select class="eau-user-condition-operator">';
        conditionHtml += '<option value="not_empty">Não está vazio</option>';
        conditionHtml += '<option value="empty">Está vazio</option>';
        conditionHtml += '<option value="equals">É igual a</option>';
        conditionHtml += '<option value="not_equals">É diferente de</option>';
        conditionHtml += '<option value="greater_than">Maior que</option>';
        conditionHtml += '<option value="greater_than_or_equal">Maior ou igual a</option>';
        conditionHtml += '<option value="less_than">Menor que</option>';
        conditionHtml += '<option value="less_than_or_equal">Menor ou igual a</option>';
        conditionHtml += '<option value="contains">Contém</option>';
        conditionHtml += '<option value="not_contains">Não contém</option>';
        conditionHtml += '<option value="starts_with">Começa com</option>';
        conditionHtml += '<option value="ends_with">Termina com</option>';
        conditionHtml += '</select>';
        conditionHtml += '</div>';

        conditionHtml += '<div class="eau-condition-field eau-user-condition-value-field">';
        conditionHtml += '<label>Valor</label>';
        conditionHtml += '<input type="text" class="eau-user-condition-value" placeholder="Digite o valor...">';
        conditionHtml += '</div>';

        conditionHtml += '<div class="eau-condition-actions">';
        conditionHtml += '<button type="button" class="button button-small eau-remove-user-condition">';
        conditionHtml += '<span class="dashicons dashicons-trash"></span>';
        conditionHtml += '</button>';
        conditionHtml += '</div>';

        conditionHtml += '</div>';
        conditionHtml += '</div>';

        $('#eau-users-conditions-list').append(conditionHtml);

        $('.eau-user-condition-operator').off('change').on('change', toggleUserConditionValueField);
    }

    function removeUserCondition() {
        $(this).closest('.eau-condition-item').remove();
    }

    function toggleUserConditionValueField() {
        const operator = $(this).val();
        const valueField = $(this).closest('.eau-condition-row').find('.eau-user-condition-value-field');

        if (operator === 'not_empty' || operator === 'empty') {
            valueField.hide();
        } else {
            valueField.show();
        }
    }

    /**
     * Mostra step 4 - configurações
     */
    function showImportUsersStep4() {
        // Coleta condições
        const conditions = [];

        $('.eau-condition-item').each(function() {
            const column = $(this).find('.eau-user-condition-column').val();
            const operator = $(this).find('.eau-user-condition-operator').val();
            const value = $(this).find('.eau-user-condition-value').val();

            if (column && operator) {
                conditions.push({
                    column: column,
                    operator: operator,
                    value: value || ''
                });
            }
        });

        importUsersData.conditions = conditions;

        $('#eau-import-users-step-3').hide();
        $('#eau-import-users-step-4').show();
    }

    /**
     * Confirma e inicia importação
     */
    function confirmAndStartUsersImport() {
        // Coleta configurações
        importUsersData.userRole = $('#eau-user-role').val();
        importUsersData.defaultPassword = $('#eau-default-password').val();
        importUsersData.sendEmail = $('#eau-send-email').is(':checked');
        importUsersData.importLimit = $('#eau-import-limit').val(); // 'all', '10', '100', ou '1000'
        importUsersData.totalImported = 0; // Contador total de importações bem-sucedidas

        // Vai para step 5 (progresso)
        $('#eau-import-users-step-4').hide();
        $('#eau-import-users-step-5').show();

        // Inicia importação
        processUsersBatchImport(0);
    }

    /**
     * Processa importação em lotes
     */
    function processUsersBatchImport(offset) {
        const batchSize = 25;

        const data = {
            action: 'eau_import_users_batch',
            nonce: eauSystem.nonce,
            csv_filename: importUsersData.csvFilename,
            column_mapping: JSON.stringify(importUsersData.columnMapping),
            conditions: JSON.stringify(importUsersData.conditions || []),
            user_role: importUsersData.userRole,
            send_email: importUsersData.sendEmail ? 'true' : 'false',
            default_password: importUsersData.defaultPassword,
            import_limit: importUsersData.importLimit,
            total_imported: importUsersData.totalImported,
            offset: offset,
            batch_size: batchSize
        };

        $.ajax({
            url: eauSystem.ajaxurl,
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    const result = response.data;

                    // Atualiza contador de importados
                    importUsersData.totalImported += result.imported;

                    // Atualiza progress bar
                    const percentage = (result.processed / result.total) * 100;
                    $('#eau-import-users-progress-fill').css('width', percentage + '%');
                    $('#eau-import-users-progress-text').text(`${result.processed} de ${result.total} usuários processados (${importUsersData.totalImported} importados)`);

                    // Adiciona log
                    if (result.imported > 0) {
                        $('#eau-import-users-log').append(`<p class="eau-log-success">✓ Lote importado: ${result.imported} usuários</p>`);
                    }
                    if (result.skipped > 0) {
                        $('#eau-import-users-log').append(`<p class="eau-log-warning">⚠ Ignorados: ${result.skipped} usuários</p>`);
                    }

                    // Scroll do log
                    $('#eau-import-users-log').scrollTop($('#eau-import-users-log')[0].scrollHeight);

                    // Verifica se atingiu o limite
                    if (result.limit_reached) {
                        $('#eau-import-users-log').append(`<p class="eau-log-info">ℹ Limite de ${importUsersData.importLimit} usuários atingido!</p>`);
                        showImportUsersComplete(result);
                        return;
                    }

                    // Continua se tiver mais
                    if (result.has_more) {
                        processUsersBatchImport(result.processed);
                    } else {
                        showImportUsersComplete(result);
                    }
                } else {
                    showNotice('error', response.data.message || 'Erro na importação.');
                    $('#eau-import-users-log').append(`<p class="eau-log-error">✗ Erro: ${response.data.message}</p>`);
                }
            },
            error: function() {
                showNotice('error', 'Erro ao processar importação.');
                $('#eau-import-users-log').append('<p class="eau-log-error">✗ Erro de conexão</p>');
            }
        });
    }

    /**
     * Mostra resumo da importação completa
     */
    function showImportUsersComplete(finalResult) {
        const limitMessage = finalResult.limit_reached
            ? `<p><strong>Limite aplicado:</strong> ${importUsersData.importLimit} usuários</p>`
            : '';

        const summaryHtml = `
            <h3>Importação Concluída!</h3>
            <p><strong>Total de linhas no CSV:</strong> ${finalResult.total}</p>
            <p><strong>Linhas processadas:</strong> ${finalResult.processed}</p>
            <p><strong>Importados com sucesso:</strong> ${importUsersData.totalImported}</p>
            <p><strong>Ignorados (email duplicado ou erros):</strong> ${finalResult.total_skipped || finalResult.skipped}</p>
            ${limitMessage}
        `;

        $('#eau-import-users-summary').html(summaryHtml);

        $('#eau-import-users-step-5').hide();
        $('#eau-import-users-step-6').show();

        showNotice('success', `${importUsersData.totalImported} usuários importados com sucesso!`);
    }

    /**
     * Reseta modal de importação de usuários
     */
    function resetImportUsersModal() {
        $('#eau-import-users-step-1').show();
        $('#eau-import-users-step-2, #eau-import-users-step-3, #eau-import-users-step-4, #eau-import-users-step-5, #eau-import-users-step-6').hide();
        $('#import_users_csv_file').val('');
        $('#eau-import-users-log').html('');
        $('#eau-import-users-progress-fill').css('width', '0%');
        $('#eau-import-users-progress-text').text('0 de 0 usuários importados');

        importUsersData = {
            metaBoxSlug: importUsersData.metaBoxSlug,
            metaBoxName: importUsersData.metaBoxName,
            fields: importUsersData.fields,
            csvFilename: '',
            csvColumns: [],
            rowCount: 0,
            columnMapping: {},
            conditions: [],
            userRole: 'eau_member',
            sendEmail: false,
            defaultPassword: ''
        };
    }

    // ===========================================
    // SINCRONIZAÇÃO DE USER TYPES
    // ===========================================

    /**
     * Handler para botão de sincronização
     */
    $(document).on('click', '#eau-sync-user-types', function() {
        if (!confirm('Tem certeza que deseja sincronizar todos os user types?\n\nIsso irá:\n- Definir institutionAdmin para usuários com email em primary_contacts_email dos posts de membership\n- Definir Member para todos os outros usuários\n\nEsta ação não pode ser desfeita.')) {
            return;
        }

        const $button = $(this);
        const $progress = $('#eau-sync-progress');
        const $result = $('#eau-sync-result');

        // Desabilita botão e mostra progresso
        $button.prop('disabled', true);
        $progress.show();
        $result.hide();

        $.ajax({
            url: eauSystem.ajaxurl,
            type: 'POST',
            data: {
                action: 'eau_sync_user_types',
                nonce: eauSystem.nonce
            },
            success: function(response) {
                $progress.hide();
                $button.prop('disabled', false);

                if (response.success) {
                    const data = response.data;
                    const stats = data.stats;

                    const resultHtml = `
                        <div class="eau-notice success">
                            <h3>✓ ${data.message}</h3>
                            <p><strong>Posts de Membership processados:</strong> ${data.memberships_processed}</p>
                            <p><strong>Emails de admins encontrados:</strong> ${data.admin_emails_found}</p>
                            <hr>
                            <h4>Estatísticas de Sincronização:</h4>
                            <ul>
                                <li><strong>Total de usuários:</strong> ${stats.total_users}</li>
                                <li><strong>Institution Admins:</strong> ${stats.institution_admins}</li>
                                <li><strong>Members:</strong> ${stats.members}</li>
                            </ul>
                        </div>
                    `;

                    $result.html(resultHtml).show();

                    // Atualiza a tabela de estatísticas
                    setTimeout(function() {
                        location.reload();
                    }, 3000);

                    EauToast.success('Sincronização Concluída!', `${stats.institution_admins} Institution Admins e ${stats.members} Members definidos.`);
                } else {
                    const errorHtml = `
                        <div class="eau-notice error">
                            <h3>✗ Erro na Sincronização</h3>
                            <p>${response.data.message || 'Ocorreu um erro inesperado.'}</p>
                        </div>
                    `;
                    $result.html(errorHtml).show();
                    EauToast.error('Erro na Sincronização', response.data.message);
                }
            },
            error: function() {
                $progress.hide();
                $button.prop('disabled', false);

                const errorHtml = `
                    <div class="eau-notice error">
                        <h3>✗ Erro de Conexão</h3>
                        <p>Não foi possível conectar ao servidor.</p>
                    </div>
                `;
                $result.html(errorHtml).show();
                EauToast.error('Erro de Conexão', 'Não foi possível conectar ao servidor.');
            }
        });
    });

})(jQuery);
