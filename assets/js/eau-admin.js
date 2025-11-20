/**
 * Eau System - Admin JavaScript
 */

(function($) {
    'use strict';

    let csvData = null;

    $(document).ready(function() {
        initUploadForm();
        initCreatePostTypeForm();
        initBackButton();
        initCreateAnotherButton();
        initDeleteButtons();
        initPrefixPreview();
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

})(jQuery);
