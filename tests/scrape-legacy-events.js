/**
 * Eau System - Legacy Events Scraper
 *
 * Script para fazer scraping dos eventos do sistema legado English Australia
 * e documentar a estrutura de campos para importação.
 *
 * @package EauSystem
 * @since 1.55.0
 */

const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

// Credenciais do sistema legado
const LEGACY_CREDENTIALS = {
    url: 'https://www.englishaustralia.com.au/administration/eventsadmin/events',
    username: 'rodrigo.zillesg@platty.tech',
    password: 'Salmo119:97'
};

// Diretório para screenshots
const SCREENSHOTS_DIR = './legacy-events-screenshots';

// Delays para garantir carregamento
const DELAYS = {
    afterNavigation: 5000,
    afterLogin: 5000,
    afterTableLoad: 3000,
    afterTabClick: 2000,
    afterPageChange: 3000
};

/**
 * Cria diretório se não existir
 */
function ensureDir(dir) {
    if (!fs.existsSync(dir)) {
        fs.mkdirSync(dir, { recursive: true });
    }
}

/**
 * Salva screenshot com nome descritivo
 */
async function saveScreenshot(page, name) {
    ensureDir(SCREENSHOTS_DIR);
    const filename = `${name}.png`;
    const filepath = path.join(SCREENSHOTS_DIR, filename);
    await page.screenshot({ path: filepath, fullPage: true });
    console.log(`[Screenshot] Salvo: ${filename}`);
    return filepath;
}

/**
 * Faz login no sistema legado
 */
async function loginToLegacy(page) {
    console.log('\n[Login] Acessando sistema legado...');

    await page.goto(LEGACY_CREDENTIALS.url, { waitUntil: 'networkidle', timeout: 60000 });
    await page.waitForTimeout(DELAYS.afterNavigation);

    // Verifica se precisa fazer login
    const currentUrl = page.url();
    console.log(`[Login] URL atual: ${currentUrl}`);

    // Tira screenshot da página atual
    await saveScreenshot(page, '00-pagina-inicial');

    // Procura campos de login específicos do sistema legado
    const usernameField = await page.$('input[name="loginusername"]');
    const passwordField = await page.$('input[name="loginpassword"]');

    if (usernameField && passwordField) {
        console.log('[Login] Campos de login encontrados, preenchendo...');

        await usernameField.fill(LEGACY_CREDENTIALS.username);
        await passwordField.fill(LEGACY_CREDENTIALS.password);

        await saveScreenshot(page, '01-login-preenchido');

        // Procura botão de submit
        const submitBtn = await page.$('input[type="submit"][name="submit"]');
        if (submitBtn) {
            await submitBtn.click();
            console.log('[Login] Botão de login clicado');
        } else {
            // Tenta pressionar Enter
            await page.keyboard.press('Enter');
            console.log('[Login] Enter pressionado');
        }

        await page.waitForTimeout(DELAYS.afterLogin);
        await page.waitForLoadState('networkidle').catch(() => {});

        console.log(`[Login] Após login: ${page.url()}`);
        await saveScreenshot(page, '02-apos-login');
    } else {
        console.log('[Login] Campos de login não encontrados - pode já estar logado');
    }

    return true;
}

/**
 * Extrai informações de campos de um formulário/página
 */
async function extractFieldInfo(page) {
    const fields = await page.evaluate(() => {
        const fieldData = [];

        // Procura todos os inputs, selects e textareas
        const inputs = document.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            const label = input.closest('label')?.textContent ||
                         document.querySelector(`label[for="${input.id}"]`)?.textContent ||
                         input.getAttribute('placeholder') ||
                         input.name ||
                         'sem-label';

            fieldData.push({
                type: input.tagName.toLowerCase(),
                inputType: input.type || '',
                name: input.name || '',
                id: input.id || '',
                label: label.trim().substring(0, 50),
                value: input.value?.substring(0, 100) || '',
                required: input.required || false
            });
        });

        // Procura labels e campos de texto
        const labels = document.querySelectorAll('label, .field-label, .form-label, th');
        labels.forEach(label => {
            const text = label.textContent?.trim();
            if (text && text.length > 1 && text.length < 100) {
                // Verifica se já não foi capturado
                const exists = fieldData.some(f => f.label === text);
                if (!exists) {
                    fieldData.push({
                        type: 'label',
                        label: text,
                        value: ''
                    });
                }
            }
        });

        return fieldData;
    });

    return fields;
}

/**
 * Analisa a estrutura de uma tabela
 */
async function analyzeTable(page) {
    const tableData = await page.evaluate(() => {
        const tables = document.querySelectorAll('table');
        const data = [];

        tables.forEach((table, tableIndex) => {
            const headers = [];
            const headerCells = table.querySelectorAll('thead th, thead td, tr:first-child th, tr:first-child td');
            headerCells.forEach(cell => {
                headers.push(cell.textContent?.trim() || '');
            });

            const rows = [];
            const bodyRows = table.querySelectorAll('tbody tr, tr:not(:first-child)');
            bodyRows.forEach((row, rowIndex) => {
                if (rowIndex < 5) { // Limita a 5 linhas de exemplo
                    const cells = [];
                    row.querySelectorAll('td').forEach(cell => {
                        cells.push(cell.textContent?.trim().substring(0, 50) || '');
                    });
                    if (cells.length > 0) {
                        rows.push(cells);
                    }
                }
            });

            if (headers.length > 0 || rows.length > 0) {
                data.push({
                    tableIndex,
                    headers,
                    sampleRows: rows,
                    totalRows: table.querySelectorAll('tbody tr').length
                });
            }
        });

        return data;
    });

    return tableData;
}

/**
 * Encontra e clica em abas
 */
async function findAndClickTabs(page) {
    const tabs = await page.evaluate(() => {
        // Procura elementos de navegação por abas
        const tabSelectors = [
            '.nav-tabs a',
            '.nav-tabs li a',
            '.tab-nav a',
            '.tabs a',
            '[role="tab"]',
            '.nav-link',
            '.tab-link',
            '.tab-item a',
            'ul.nav li a'
        ];

        let foundTabs = [];

        for (const selector of tabSelectors) {
            const elements = document.querySelectorAll(selector);
            if (elements.length > 0) {
                elements.forEach((el, idx) => {
                    foundTabs.push({
                        selector: selector,
                        index: idx,
                        text: el.textContent?.trim() || '',
                        href: el.getAttribute('href') || ''
                    });
                });
                break;
            }
        }

        return foundTabs;
    });

    return tabs;
}

/**
 * Navega pelas abas de detalhes do evento
 */
async function navigateEventDetailsTabs(page, eventReport) {
    console.log('\n[Abas] Procurando abas de detalhes...');

    // Captura estrutura de abas da página
    const tabsStructure = await page.evaluate(() => {
        const tabs = [];

        // Procura todas as listas de abas (ul com li > a)
        const tabLists = document.querySelectorAll('ul.nav, ul.tabs, .nav-tabs, .tab-nav, ul[role="tablist"]');

        tabLists.forEach((tabList, listIndex) => {
            const tabItems = tabList.querySelectorAll('li a, a.nav-link');
            tabItems.forEach((tab, tabIndex) => {
                tabs.push({
                    listIndex,
                    tabIndex,
                    text: tab.textContent?.trim() || '',
                    href: tab.getAttribute('href') || '',
                    id: tab.id || '',
                    classList: tab.className || ''
                });
            });
        });

        // Se não encontrou, procura links em divs de navegação
        if (tabs.length === 0) {
            const navLinks = document.querySelectorAll('.nav a, .tabs a, .tab a, [class*="tab"] > a');
            navLinks.forEach((link, idx) => {
                tabs.push({
                    listIndex: 0,
                    tabIndex: idx,
                    text: link.textContent?.trim() || '',
                    href: link.getAttribute('href') || '',
                    id: link.id || '',
                    classList: link.className || ''
                });
            });
        }

        return tabs;
    });

    console.log(`[Abas] Encontradas ${tabsStructure.length} abas no total`);

    eventReport.tabs = [];

    // Primeiro screenshot geral da página de evento
    await saveScreenshot(page, '05-evento-pagina-inicial');

    // Filtra abas relevantes (ignora abas vazias ou de navegação do sistema)
    const relevantTabs = tabsStructure.filter(tab =>
        tab.text.length > 0 &&
        !tab.text.toLowerCase().includes('dashboard') &&
        !tab.text.toLowerCase().includes('logout')
    );

    console.log(`[Abas] ${relevantTabs.length} abas relevantes para analisar`);

    // Navega por cada aba
    for (let i = 0; i < relevantTabs.length; i++) {
        const tab = relevantTabs[i];
        console.log(`\n[Aba ${i + 1}/${relevantTabs.length}] "${tab.text}"`);

        try {
            // Clica na aba usando diferentes estratégias
            let clicked = false;

            // Tenta pelo href se for um link interno
            if (tab.href && tab.href.startsWith('#')) {
                const tabElement = await page.$(`a[href="${tab.href}"]`);
                if (tabElement) {
                    await tabElement.click();
                    clicked = true;
                }
            }

            // Tenta pelo texto exato
            if (!clicked) {
                const tabByText = await page.$(`a:text-is("${tab.text}")`);
                if (tabByText) {
                    await tabByText.click();
                    clicked = true;
                }
            }

            // Tenta pelo texto parcial
            if (!clicked) {
                const tabByPartialText = await page.$(`a:has-text("${tab.text}")`);
                if (tabByPartialText) {
                    await tabByPartialText.click();
                    clicked = true;
                }
            }

            if (clicked) {
                await page.waitForTimeout(DELAYS.afterTabClick);

                // Screenshot da aba
                const safeName = tab.text.toLowerCase().replace(/[^a-z0-9]/g, '-').substring(0, 30);
                const screenshotName = `06-tab-${String(i + 1).padStart(2, '0')}-${safeName}`;
                await saveScreenshot(page, screenshotName);

                // Extrai campos desta aba
                const fields = await extractFieldInfo(page);
                const tables = await analyzeTable(page);

                // Extrai também labels visíveis
                const visibleLabels = await page.evaluate(() => {
                    const labels = [];
                    // Procura labels, th, dt, strong em contextos de formulário
                    document.querySelectorAll('label, th, dt, .field-label, .form-label, strong').forEach(el => {
                        const text = el.textContent?.trim();
                        if (text && text.length > 1 && text.length < 100) {
                            labels.push(text);
                        }
                    });
                    return [...new Set(labels)]; // Remove duplicatas
                });

                eventReport.tabs.push({
                    name: tab.text,
                    href: tab.href,
                    fields: fields,
                    tables: tables,
                    visibleLabels: visibleLabels
                });

                console.log(`[Aba ${i + 1}] Campos: ${fields.length}, Labels: ${visibleLabels.length}`);
            } else {
                console.log(`[Aba ${i + 1}] Não conseguiu clicar na aba`);
            }

        } catch (error) {
            console.log(`[Aba ${i + 1}] Erro ao processar: ${error.message}`);
        }
    }

    // Salva HTML completo para análise manual
    const html = await page.content();
    fs.writeFileSync(path.join(SCREENSHOTS_DIR, 'event-details-page.html'), html);
    console.log('\n[Debug] HTML da página de evento salvo');

    return eventReport;
}

/**
 * Função principal de scraping
 */
async function scrapeEvents() {
    console.log('='.repeat(60));
    console.log('Eau System - Legacy Events Scraper');
    console.log('='.repeat(60));

    const browser = await chromium.launch({
        headless: false, // Visível para debug
        slowMo: 500
    });

    const context = await browser.newContext({
        viewport: { width: 1920, height: 1080 }
    });

    const page = await context.newPage();

    // Relatório completo
    const report = {
        timestamp: new Date().toISOString(),
        legacySystem: LEGACY_CREDENTIALS.url,
        eventsFound: 0,
        eventStructure: null,
        allTabs: [],
        fieldMapping: []
    };

    try {
        // 1. Login
        await loginToLegacy(page);

        // 2. Navega para lista de eventos (se necessário)
        console.log('\n[Eventos] Verificando lista de eventos...');
        await page.goto(LEGACY_CREDENTIALS.url, { waitUntil: 'networkidle', timeout: 60000 });
        await page.waitForTimeout(DELAYS.afterNavigation);

        await saveScreenshot(page, '03-lista-eventos');

        // 3. Analisa a tabela de eventos
        const eventTables = await analyzeTable(page);
        if (eventTables.length > 0) {
            console.log('[Eventos] Estrutura da tabela de eventos:');
            console.log(JSON.stringify(eventTables[0], null, 2));
            report.eventsTableStructure = eventTables[0];
        }

        // 4. Encontra e clica no primeiro evento
        console.log('\n[Eventos] Procurando eventos na lista...');

        // O sistema usa checkboxes para selecionar eventos
        // Precisamos clicar na linha da tabela para selecionar e depois usar o botão "Edit"
        // Ou podemos clicar diretamente no título do evento se for um link

        // Primeiro, vamos ver se há links clicáveis nos títulos dos eventos
        const eventRows = await page.$$('table tbody tr');
        console.log(`[Eventos] Encontradas ${eventRows.length} linhas na tabela`);

        // Tenta encontrar o primeiro evento com título clicável
        let foundEvent = false;

        // Vamos usar JavaScript para clicar em um evento via a função do sistema
        // ou navegar diretamente para a URL de edição
        const firstEventId = await page.evaluate(() => {
            // Procura o primeiro checkbox de evento
            const checkbox = document.querySelector('input[id^="eventsListDataGridActionCheckboxes"]');
            if (checkbox) {
                return checkbox.value;
            }
            return null;
        });

        if (firstEventId) {
            console.log(`[Eventos] ID do primeiro evento: ${firstEventId}`);

            // Navega diretamente para a página de edição do evento
            const eventEditUrl = `https://www.englishaustralia.com.au/administration/eventsadmin/events/event?id=${firstEventId}`;
            console.log(`[Eventos] Navegando para: ${eventEditUrl}`);

            await page.goto(eventEditUrl, { waitUntil: 'networkidle', timeout: 60000 });
            await page.waitForTimeout(DELAYS.afterNavigation);

            console.log(`[Eventos] URL atual: ${page.url()}`);
            await saveScreenshot(page, '04-evento-detalhes');

            // 5. Analisa estrutura do evento
            report.eventStructure = {
                url: page.url(),
                eventId: firstEventId,
                tabs: []
            };

            // 6. Navega pelas abas de detalhes
            await navigateEventDetailsTabs(page, report.eventStructure);

            foundEvent = true;
        }

        if (!foundEvent) {
            console.log('[Eventos] Nenhum evento encontrado');

            // Captura HTML para análise
            const html = await page.content();
            fs.writeFileSync(path.join(SCREENSHOTS_DIR, 'page-content.html'), html);
            console.log('[Debug] HTML da página salvo para análise');
        }

        // 7. Volta para a lista e analisa paginação
        console.log('\n[Paginação] Analisando paginação...');
        await page.goto(LEGACY_CREDENTIALS.url, { waitUntil: 'networkidle', timeout: 60000 });
        await page.waitForTimeout(DELAYS.afterNavigation);

        const pagination = await page.evaluate(() => {
            const paginationElements = document.querySelectorAll('.pagination a, .pager a, [class*="page"]');
            const pages = [];
            paginationElements.forEach(el => {
                pages.push({
                    text: el.textContent?.trim() || '',
                    href: el.getAttribute('href') || ''
                });
            });
            return {
                elements: pages,
                totalText: document.body.innerText.match(/(\d+)\s*(of|de)\s*(\d+)/i)?.[0] || ''
            };
        });

        report.pagination = pagination;
        console.log(`[Paginação] Info: ${pagination.totalText || 'Não encontrada'}`);

    } catch (error) {
        console.error('\n[ERRO]', error.message);
        await saveScreenshot(page, 'error-state');
    } finally {
        // Salva relatório
        const reportPath = path.join(SCREENSHOTS_DIR, 'legacy-events-report.json');
        fs.writeFileSync(reportPath, JSON.stringify(report, null, 2));
        console.log(`\n[Relatório] Salvo em: ${reportPath}`);

        // Gera resumo em markdown
        const summary = generateMarkdownSummary(report);
        const summaryPath = path.join(SCREENSHOTS_DIR, 'LEGACY-EVENTS-ANALYSIS.md');
        fs.writeFileSync(summaryPath, summary);
        console.log(`[Resumo] Salvo em: ${summaryPath}`);

        await browser.close();
    }

    console.log('\n' + '='.repeat(60));
    console.log('Scraping concluído!');
    console.log('='.repeat(60));
}

/**
 * Gera resumo em Markdown
 */
function generateMarkdownSummary(report) {
    let md = `# Análise do Sistema Legado de Eventos - English Australia\n\n`;
    md += `**Data da análise:** ${report.timestamp}\n`;
    md += `**URL do sistema:** ${report.legacySystem}\n\n`;

    if (report.eventsTableStructure) {
        md += `## Estrutura da Tabela de Eventos\n\n`;
        md += `| Coluna |\n|--------|\n`;
        report.eventsTableStructure.headers.forEach(h => {
            md += `| ${h} |\n`;
        });
        md += `\n`;
    }

    if (report.eventStructure && report.eventStructure.tabs) {
        md += `## Abas do Evento\n\n`;

        report.eventStructure.tabs.forEach((tab, idx) => {
            md += `### ${idx + 1}. ${tab.name}\n\n`;

            if (tab.fields && tab.fields.length > 0) {
                md += `**Campos encontrados:**\n\n`;
                md += `| Campo | Tipo | Nome/ID |\n|-------|------|--------|\n`;
                tab.fields.forEach(f => {
                    if (f.type !== 'label') {
                        md += `| ${f.label} | ${f.type}${f.inputType ? '/' + f.inputType : ''} | ${f.name || f.id || '-'} |\n`;
                    }
                });
                md += `\n`;
            }

            if (tab.tables && tab.tables.length > 0) {
                md += `**Tabelas encontradas:**\n\n`;
                tab.tables.forEach(t => {
                    md += `- Headers: ${t.headers.join(', ')}\n`;
                    md += `- Total de linhas: ${t.totalRows}\n\n`;
                });
            }
        });
    }

    if (report.pagination) {
        md += `## Paginação\n\n`;
        md += `Info: ${report.pagination.totalText || 'Não identificada'}\n\n`;
    }

    md += `## Próximos Passos\n\n`;
    md += `1. Analisar os screenshots capturados em \`${SCREENSHOTS_DIR}\`\n`;
    md += `2. Mapear campos do sistema legado para o Eau Events\n`;
    md += `3. Criar script de importação em batch\n`;
    md += `4. Testar com um pequeno conjunto de eventos\n`;

    return md;
}

// Executa
scrapeEvents().catch(console.error);
