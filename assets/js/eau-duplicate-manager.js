/**
 * Eau Duplicate Manager - JavaScript Controller
 * Sistema de detecção e merge de membros duplicados
 * Version: 1.0.0
 */

(function($) {
    'use strict';

    window.EauDuplicateManager = {
        // Estado
        currentScan: null,
        scanInterval: null,
        duplicatePairs: [],
        currentPage: 1,
        totalPages: 1,
        totalResults: 0,
        perPage: 10,
        currentFilter: 'all',
        currentSort: 'score_desc',
        selectedPair: null,

        /**
         * Inicializa o sistema
         */
        init: function() {
            const self = this;

            // Event Listeners
            $('#eau-start-scan-btn').on('click', function() {
                self.startScan();
            });

            $('.eau-filter-btn').on('click', function() {
                $('.eau-filter-btn').removeClass('active');
                $(this).addClass('active');
                self.currentFilter = $(this).data('filter');
                self.currentPage = 1;
                self.loadDuplicates();
            });

            $('#eau-sort-select').on('change', function() {
                self.currentSort = $(this).val();
                self.currentPage = 1;
                self.loadDuplicates();
            });

            $('#eau-prev-page').on('click', function() {
                if (self.currentPage > 1) {
                    self.currentPage--;
                    self.loadDuplicates();
                }
            });

            $('#eau-next-page').on('click', function() {
                if (self.currentPage < self.totalPages) {
                    self.currentPage++;
                    self.loadDuplicates();
                }
            });

            // Modal close
            $(document).on('click', '[data-action="close-merge"]', function() {
                self.closeMergeModal();
            });

            // Merge actions
            $(document).on('click', '.eau-merge-choice', function() {
                const $field = $(this).closest('.eau-merge-field');
                $field.find('.eau-merge-choice').removeClass('selected');
                $(this).addClass('selected');
                $(this).find('input[type="radio"]').prop('checked', true);
            });

            $('#eau-confirm-merge-btn').on('click', function() {
                self.executeMerge();
            });

            // Carrega duplicatas ao iniciar
            this.loadDuplicates();
        },

        /**
         * Inicia novo scan
         */
        startScan: function() {
            const self = this;

            // Limpa a interface imediatamente
            self.duplicatePairs = [];
            self.totalResults = 0;
            self.currentPage = 1;

            // Esconde o contador de resultados
            $('#eau-results-summary').hide();

            // Esconde paginação
            $('#eau-pagination').hide();

            // Mostra e limpa a barra de progresso PRIMEIRO
            $('#eau-scan-progress').show();
            $('#eau-progress-text').text('Starting scan...');
            $('#eau-progress-fill').css('width', '0%');
            $('#eau-progress-stats').text('');

            // DEPOIS limpa a lista (para não sobrepor a barra)
            $('#eau-duplicates-container').html(`
                <div class="eau-duplicates-empty">
                    <i data-lucide="loader-2" class="eau-spin"></i>
                    <p>Initializing scan...</p>
                </div>
            `);
            lucide.createIcons();

            $('#eau-start-scan-btn').prop('disabled', true);

            $.ajax({
                url: eauDuplicateData.ajaxUrl,
                type: 'POST',
                timeout: 30000,
                data: {
                    action: 'eau_start_scan',
                    nonce: eauDuplicateData.nonce,
                },
                success: function(response) {
                    if (response.success) {
                        self.currentScan = response.data.scan_id;
                        self.pollScanProgress();
                        EauNotifications.success('Scan Started', 'Analyzing members for duplicates...');
                    } else {
                        console.error('✗ Erro ao iniciar scan:', response.data.message);
                        EauNotifications.error('Error', response.data.message);
                        $('#eau-start-scan-btn').prop('disabled', false);
                        $('#eau-scan-progress').slideUp();
                        self.loadDuplicates(); // Recarrega dados antigos se falhou
                    }
                },
                error: function(xhr, status, error) {
                    console.error('✗ AJAX Error:', {xhr, status, error});
                    console.error('Response text:', xhr.responseText);
                    EauNotifications.error('Error', 'Failed to start scan');
                    $('#eau-start-scan-btn').prop('disabled', false);
                    $('#eau-scan-progress').slideUp();
                    self.loadDuplicates(); // Recarrega dados antigos se falhou
                }
            });
        },

        /**
         * Verifica progresso do scan
         */
        pollScanProgress: function() {
            const self = this;

            this.scanInterval = setInterval(function() {

                $.ajax({
                    url: eauDuplicateData.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'eau_get_scan_progress',
                        nonce: eauDuplicateData.nonce,
                        scan_id: self.currentScan,
                    },
                    success: function(response) {

                        if (response.success) {
                            const scan = response.data;

                            if (scan.scan_status === 'completed') {
                                clearInterval(self.scanInterval);
                                $('#eau-progress-text').text('Scan completed!');
                                $('#eau-progress-fill').css('width', '100%');
                                $('#eau-progress-stats').text(`Found ${scan.duplicates_found} potential duplicates`);

                                setTimeout(function() {
                                    $('#eau-scan-progress').slideUp();
                                    $('#eau-start-scan-btn').prop('disabled', false);

                                    // Carrega duplicados finais se houver
                                    if (scan.duplicates_found > 0) {
                                        self.loadDuplicates();
                                    } else {
                                        // Mostra mensagem de nenhum duplicado encontrado
                                        $('#eau-duplicates-container').html(`
                                            <div class="eau-duplicates-empty">
                                                <i data-lucide="check-circle"></i>
                                                <p>No duplicates found! All members are unique.</p>
                                            </div>
                                        `);
                                        lucide.createIcons();
                                    }
                                }, 1000);

                            } else if (scan.scan_status === 'failed') {
                                clearInterval(self.scanInterval);
                                EauNotifications.error('Scan Failed', 'An error occurred during the scan');
                                $('#eau-scan-progress').slideUp();
                                $('#eau-start-scan-btn').prop('disabled', false);
                            } else {
                                // In progress
                                const totalUsers = scan.total_users || 0;
                                const analyzed = scan.total_users_analyzed || 0;
                                const duplicates = scan.duplicates_found || 0;

                                // Calcula progresso real baseado no total de usuários
                                // Total de comparações = n * (n-1) / 2
                                let progressPercent = 0;
                                if (totalUsers > 0) {
                                    const totalComparisons = (totalUsers * (totalUsers - 1)) / 2;
                                    progressPercent = Math.min(95, (analyzed / totalComparisons) * 100);
                                }

                                // Mostra progresso em formato amigável
                                const percentText = progressPercent > 0 ? ` (${progressPercent.toFixed(1)}%)` : '';
                                $('#eau-progress-text').text(`Analyzing ${totalUsers.toLocaleString()} members... ${analyzed.toLocaleString()} of ${((totalUsers * (totalUsers - 1)) / 2).toLocaleString()} comparisons${percentText}`);
                                $('#eau-progress-fill').css('width', progressPercent + '%');

                                if (duplicates > 0) {
                                    $('#eau-progress-stats').text(`${duplicates} potential duplicate${duplicates !== 1 ? 's' : ''} found so far`);

                                    // Carrega duplicados em tempo real se houver novos
                                    if (duplicates !== self.totalResults) {
                                        self.loadDuplicates();
                                    }
                                } else {
                                    $('#eau-progress-stats').text('No duplicates found yet');

                                    // Atualiza mensagem enquanto escaneia
                                    if (analyzed > 0) {
                                        $('#eau-duplicates-container').html(`
                                            <div class="eau-duplicates-empty">
                                                <i data-lucide="search" class="eau-spin"></i>
                                                <p>Scanning members... No duplicates found yet.</p>
                                            </div>
                                        `);
                                        lucide.createIcons();
                                    }
                                }
                            }
                        }
                    }
                });
            }, 2000); // Polling a cada 2 segundos
        },

        /**
         * Carrega lista de duplicatas
         */
        loadDuplicates: function() {
            const self = this;


            $.ajax({
                url: eauDuplicateData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eau_get_duplicate_pairs',
                    nonce: eauDuplicateData.nonce,
                    page: this.currentPage,
                    per_page: this.perPage,
                    filter: this.currentFilter,
                    sort: this.currentSort,
                },
                success: function(response) {

                    if (response.success) {
                        self.duplicatePairs = response.data.pairs;
                        self.totalPages = response.data.total_pages;
                        self.totalResults = response.data.total;


                        self.renderDuplicates();
                        self.updatePagination();
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Erro ao carregar duplicados:', {xhr, status, error});
                    EauNotifications.error('Error', 'Failed to load duplicates');
                }
            });
        },

        /**
         * Renderiza lista de duplicatas
         */
        renderDuplicates: function() {
            const $container = $('#eau-duplicates-container');
            const $summary = $('#eau-results-summary');

            if (this.duplicatePairs.length === 0) {
                $summary.hide();
                $container.html(`
                    <div class="eau-duplicates-empty">
                        <i data-lucide="users-2"></i>
                        <p>No duplicate pairs found. Click "Start New Scan" to analyze your members.</p>
                    </div>
                `);
                lucide.createIcons();
                return;
            }

            // Atualiza e mostra o contador de resultados
            const startIndex = (this.currentPage - 1) * this.perPage + 1;
            const endIndex = Math.min(startIndex + this.duplicatePairs.length - 1, this.totalResults);

            $('#eau-results-text').text(`Showing ${startIndex}-${endIndex} of ${this.totalResults.toLocaleString()} potential duplicates`);
            $summary.show();

            let html = '';
            this.duplicatePairs.forEach(function(pair) {
                html += EauDuplicateManager.renderPairCard(pair);
            });

            $container.html(html);

            // Re-inicializa ícones Lucide
            lucide.createIcons();
        },

        /**
         * Renderiza card de par duplicado
         */
        renderPairCard: function(pair) {
            const scoreClass = pair.score >= 80 ? 'high' : 'medium';

            // Map de tipos de tags para ícones e classes CSS
            const tagConfig = {
                'name': { icon: 'user', class: 'eau-tag-name' },
                'email': { icon: 'mail', class: 'eau-tag-email' },
                'phone': { icon: 'phone', class: 'eau-tag-phone' },
                'company': { icon: 'building-2', class: 'eau-tag-company' },
                'address': { icon: 'map-pin', class: 'eau-tag-address' },
                'city': { icon: 'map', class: 'eau-tag-city' },
                'postcode': { icon: 'hash', class: 'eau-tag-postcode' }
            };

            const matchTags = pair.matches.map(match => {
                const config = tagConfig[match.type] || { icon: 'check', class: 'eau-tag-other' };
                return `<span class="eau-match-tag ${config.class}">
                    <i data-lucide="${config.icon}"></i>
                    ${match.label}
                </span>`;
            }).join('');

            return `
                <div class="eau-duplicate-pair" data-pair-id="${pair.pair_id}">
                    <div class="eau-pair-header">
                        <div class="eau-pair-score">
                            <div class="eau-score-badge ${scoreClass}">
                                ${Math.round(pair.score)}%
                            </div>
                            <div>
                                <div class="eau-score-label">Match Confidence</div>
                                <div class="eau-pair-tags">
                                    ${matchTags}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="eau-pair-content">
                        <div class="eau-user-card">
                            <div class="eau-user-name">${this.escapeHtml(pair.user1.display_name)}</div>
                            <div class="eau-user-details">
                                <div class="eau-user-detail">
                                    <i data-lucide="mail"></i>
                                    <span class="eau-user-detail-value">${this.escapeHtml(pair.user1.email)}</span>
                                </div>
                                ${pair.user1.phone ? `
                                <div class="eau-user-detail">
                                    <i data-lucide="phone"></i>
                                    <span class="eau-user-detail-value">${this.escapeHtml(pair.user1.phone)}</span>
                                </div>
                                ` : ''}
                                ${pair.user1.company ? `
                                <div class="eau-user-detail">
                                    <i data-lucide="building-2"></i>
                                    <span class="eau-user-detail-value">${this.escapeHtml(pair.user1.company)}</span>
                                </div>
                                ` : ''}
                                ${pair.user1.city ? `
                                <div class="eau-user-detail">
                                    <i data-lucide="map-pin"></i>
                                    <span class="eau-user-detail-value">${this.escapeHtml(pair.user1.city)}, ${this.escapeHtml(pair.user1.postcode || '')}</span>
                                </div>
                                ` : ''}
                            </div>
                        </div>

                        <div class="eau-pair-divider"></div>

                        <div class="eau-user-card">
                            <div class="eau-user-name">${this.escapeHtml(pair.user2.display_name)}</div>
                            <div class="eau-user-details">
                                <div class="eau-user-detail">
                                    <i data-lucide="mail"></i>
                                    <span class="eau-user-detail-value">${this.escapeHtml(pair.user2.email)}</span>
                                </div>
                                ${pair.user2.phone ? `
                                <div class="eau-user-detail">
                                    <i data-lucide="phone"></i>
                                    <span class="eau-user-detail-value">${this.escapeHtml(pair.user2.phone)}</span>
                                </div>
                                ` : ''}
                                ${pair.user2.company ? `
                                <div class="eau-user-detail">
                                    <i data-lucide="building-2"></i>
                                    <span class="eau-user-detail-value">${this.escapeHtml(pair.user2.company)}</span>
                                </div>
                                ` : ''}
                                ${pair.user2.city ? `
                                <div class="eau-user-detail">
                                    <i data-lucide="map-pin"></i>
                                    <span class="eau-user-detail-value">${this.escapeHtml(pair.user2.city)}, ${this.escapeHtml(pair.user2.postcode || '')}</span>
                                </div>
                                ` : ''}
                            </div>
                        </div>
                    </div>

                    <div class="eau-pair-actions">
                        <button class="eau-btn eau-btn-secondary" onclick="EauDuplicateManager.dismissPair(${pair.pair_id})">
                            <i data-lucide="x"></i>
                            Not Duplicate
                        </button>
                        <button class="eau-btn eau-btn-primary eau-merge-btn" data-pair-id="${pair.pair_id}" onclick="EauDuplicateManager.openMergeModal(${pair.pair_id})">
                            <i data-lucide="merge"></i>
                            Merge
                        </button>
                    </div>
                </div>
            `;
        },

        /**
         * Atualiza paginação
         */
        updatePagination: function() {
            if (this.totalPages <= 1) {
                $('#eau-pagination').hide();
                return;
            }

            $('#eau-pagination').show();
            $('#eau-page-info').text(`Page ${this.currentPage} of ${this.totalPages}`);

            $('#eau-prev-page').prop('disabled', this.currentPage === 1);
            $('#eau-next-page').prop('disabled', this.currentPage === this.totalPages);
        },

        /**
         * Abre modal de merge
         */
        openMergeModal: function(pairId) {
            // Força conversão para número para garantir comparação correta
            const pairIdNum = parseInt(pairId);
            const pair = this.duplicatePairs.find(p => parseInt(p.pair_id) === pairIdNum);

            if (!pair) {
                console.error('Pair not found!');
                return;
            }

            this.selectedPair = pair;

            const fields = [
                { key: 'display_name', label: 'Display Name', value1: pair.user1.display_name, value2: pair.user2.display_name },
                { key: 'user_email', label: 'Email', value1: pair.user1.email, value2: pair.user2.email },
                { key: 'mem_phone', label: 'Phone', value1: pair.user1.phone, value2: pair.user2.phone },
                { key: 'mem_membercompanyname', label: 'Company', value1: pair.user1.company, value2: pair.user2.company },
                { key: 'mem_address', label: 'Address', value1: pair.user1.address, value2: pair.user2.address },
                { key: 'mem_city', label: 'City', value1: pair.user1.city, value2: pair.user2.city },
                { key: 'mem_postcode', label: 'Postcode', value1: pair.user1.postcode, value2: pair.user2.postcode },
            ];

            let html = '<div class="eau-merge-comparison">';

            fields.forEach(function(field) {
                // Pula campos vazios em ambos
                if (!field.value1 && !field.value2) return;

                html += `
                    <div class="eau-merge-field">
                        <label class="eau-merge-field-label">${field.label}:</label>
                        <div class="eau-merge-choice ${field.value1 ? 'selected' : ''}" data-field="${field.key}">
                            <input type="radio" name="field_${field.key}" value="${pair.user1.id}" ${field.value1 ? 'checked' : ''}>
                            <div class="eau-merge-choice-content">
                                <div class="eau-merge-choice-value">${field.value1 || '(empty)'}</div>
                            </div>
                        </div>
                    </div>
                    <div class="eau-merge-field">
                        <label class="eau-merge-field-label">&nbsp;</label>
                        <div class="eau-merge-choice ${!field.value1 && field.value2 ? 'selected' : ''}" data-field="${field.key}">
                            <input type="radio" name="field_${field.key}" value="${pair.user2.id}" ${!field.value1 && field.value2 ? 'checked' : ''}>
                            <div class="eau-merge-choice-content">
                                <div class="eau-merge-choice-value">${field.value2 || '(empty)'}</div>
                            </div>
                        </div>
                    </div>
                `;
            });

            html += '</div>';

            html += `
                <div class="eau-merge-warning">
                    <i data-lucide="alert-triangle"></i>
                    <div class="eau-merge-warning-text">
                        <strong>Warning:</strong> This action cannot be undone. One member will be deleted and their data will be merged into the selected member.
                    </div>
                </div>
            `;

            $('#eau-merge-modal-body').html(html);

            // Usa display: flex para garantir centralização
            $('#eau-merge-modal-overlay').css('display', 'flex').hide().fadeIn(200);

            lucide.createIcons();
        },

        /**
         * Fecha modal de merge
         */
        closeMergeModal: function() {
            $('#eau-merge-modal-overlay').fadeOut(200, function() {
                $(this).css('display', 'none');
            });
            this.selectedPair = null;
        },

        /**
         * Executa merge
         */
        executeMerge: function() {
            const self = this;

            if (!this.selectedPair) {
                console.error('No pair selected for merge');
                return;
            }


            // Coleta escolhas dos campos
            const fieldChoices = {};
            $('input[type="radio"]:checked').each(function() {
                const fieldName = $(this).attr('name').replace('field_', '');
                fieldChoices[fieldName] = parseInt($(this).val());
            });


            // Determina qual usuário será mantido e qual será deletado
            // (conta quantos campos foram escolhidos de cada)
            const user1Count = Object.values(fieldChoices).filter(id => id === this.selectedPair.user1.id).length;
            const user2Count = Object.values(fieldChoices).filter(id => id === this.selectedPair.user2.id).length;

            const userIdKeep = user1Count >= user2Count ? this.selectedPair.user1.id : this.selectedPair.user2.id;
            const userIdDelete = userIdKeep === this.selectedPair.user1.id ? this.selectedPair.user2.id : this.selectedPair.user1.id;


            // IMPORTANTE: Salva dados ANTES de fechar o modal (que reseta selectedPair)
            const pairId = this.selectedPair.pair_id;

            // Confirma merge
            EauNotifications.confirm({
                title: 'Confirm Merge',
                message: 'Are you sure you want to merge these members? This action cannot be undone.',
                type: 'danger',
                confirmText: 'Merge Members',
                onConfirm: function() {
                    self.closeMergeModal();

                    $.ajax({
                        url: eauDuplicateData.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'eau_merge_members',
                            nonce: eauDuplicateData.nonce,
                            pair_id: pairId, // Usa a variável local ao invés de self.selectedPair
                            user_id_keep: userIdKeep,
                            user_id_delete: userIdDelete,
                            field_choices: fieldChoices,
                        },
                        success: function(response) {
                            if (response.success) {
                                EauNotifications.success('Merged!', 'Members have been merged successfully');
                                self.loadDuplicates();
                            } else {
                                console.error('✗ Merge failed:', response.data.message);
                                EauNotifications.error('Error', response.data.message);
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('✗ Merge AJAX error:', {xhr, status, error});
                            console.error('Response text:', xhr.responseText);
                            EauNotifications.error('Error', 'Failed to merge members');
                        }
                    });
                }
            });
        },

        /**
         * Marca par como não duplicado
         */
        dismissPair: function(pairId) {
            const self = this;

            EauNotifications.confirm({
                title: 'Not Duplicate?',
                message: 'Mark this pair as not duplicate? They will not appear in future scans.',
                type: 'warning',
                confirmText: 'Confirm',
                onConfirm: function() {
                    $.ajax({
                        url: eauDuplicateData.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'eau_dismiss_duplicate',
                            nonce: eauDuplicateData.nonce,
                            pair_id: pairId,
                        },
                        success: function(response) {
                            if (response.success) {
                                EauNotifications.success('Dismissed', response.data.message);
                                self.loadDuplicates();
                            } else {
                                EauNotifications.error('Error', response.data.message);
                            }
                        },
                        error: function() {
                            EauNotifications.error('Error', 'Failed to dismiss pair');
                        }
                    });
                }
            });
        },

        /**
         * Escapa HTML para prevenir XSS
         */
        escapeHtml: function(text) {
            if (!text) return '';
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
        }
    };

    // Inicializa quando o documento estiver pronto
    $(document).ready(function() {
        if ($('.eau-duplicate-manager-container').length > 0) {
            EauDuplicateManager.init();
        }
    });

})(jQuery);
