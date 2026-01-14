/**
 * Eau System - Event Details Scraper
 *
 * Script focado em capturar as 9 abas de detalhes de um evento
 *
 * @package EauSystem
 * @since 1.55.0
 */

const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

// Credenciais do sistema legado
const LEGACY_CREDENTIALS = {
    loginUrl: 'https://www.englishaustralia.com.au/administration/eventsadmin/events',
    username: 'rodrigo.zillesg@platty.tech',
    password: 'Salmo119:97'
};

// Diretório para screenshots
const SCREENSHOTS_DIR = './legacy-events-screenshots/event-details';

// Delays
const DELAYS = {
    short: 1000,
    medium: 2000,
    long: 5000
};

function ensureDir(dir) {
    if (!fs.existsSync(dir)) {
        fs.mkdirSync(dir, { recursive: true });
    }
}

async function saveScreenshot(page, name) {
    ensureDir(SCREENSHOTS_DIR);
    const filename = `${name}.png`;
    const filepath = path.join(SCREENSHOTS_DIR, filename);
    await page.screenshot({ path: filepath, fullPage: true });
    console.log(`[Screenshot] ${filename}`);
    return filepath;
}

async function login(page) {
    console.log('[Login] Acessando sistema...');
    await page.goto(LEGACY_CREDENTIALS.loginUrl, { waitUntil: 'networkidle', timeout: 60000 });
    await page.waitForTimeout(DELAYS.medium);

    const usernameField = await page.$('input[name="loginusername"]');
    if (usernameField) {
        await usernameField.fill(LEGACY_CREDENTIALS.username);
        await page.fill('input[name="loginpassword"]', LEGACY_CREDENTIALS.password);
        await page.click('input[type="submit"][name="submit"]');
        await page.waitForTimeout(DELAYS.long);
        await page.waitForLoadState('networkidle').catch(() => {});
        console.log('[Login] Login realizado');
    } else {
        console.log('[Login] Já logado');
    }
}

async function main() {
    console.log('=' .repeat(60));
    console.log('Scraper de Detalhes do Evento');
    console.log('='.repeat(60));

    const browser = await chromium.launch({ headless: false, slowMo: 300 });
    const context = await browser.newContext({ viewport: { width: 1920, height: 1080 } });
    const page = await context.newPage();

    const report = {
        timestamp: new Date().toISOString(),
        mainTabs: [],
        detailsSubTabs: [],
        allFields: []
    };

    try {
        // Login
        await login(page);
        await saveScreenshot(page, '01-lista-eventos');

        // Pegar o ID do primeiro evento
        console.log('\n[Eventos] Buscando primeiro evento...');
        const firstEventId = await page.evaluate(() => {
            const checkbox = document.querySelector('input[id^="eventsListDataGridActionCheckboxes"]');
            return checkbox ? checkbox.value : null;
        });

        console.log(`[Eventos] ID do evento: ${firstEventId}`);

        if (!firstEventId) {
            throw new Error('Nenhum evento encontrado');
        }

        // Navegar diretamente para a página de edição do evento
        const eventUrl = `https://www.englishaustralia.com.au/administration/eventsadmin/events/event?id=${firstEventId}`;
        console.log(`[Eventos] Navegando para: ${eventUrl}`);

        await page.goto(eventUrl, { waitUntil: 'networkidle', timeout: 60000 });
        await page.waitForTimeout(DELAYS.long);

        await saveScreenshot(page, '02-evento-inicial');

        // Captura das abas principais
        console.log('\n[Abas] Procurando abas principais...');

        // Encontra todas as abas (tabs) na página
        const mainTabs = await page.evaluate(() => {
            const tabs = [];
            // Procura abas em diferentes formatos
            document.querySelectorAll('ul.ui-tabs-nav li a, .tabs li a, .nav-tabs li a, [role="tab"]').forEach((tab, idx) => {
                tabs.push({
                    index: idx,
                    text: tab.textContent?.trim() || '',
                    href: tab.getAttribute('href') || '',
                    id: tab.id || ''
                });
            });
            return tabs;
        });

        console.log(`[Abas] Encontradas ${mainTabs.length} abas principais`);
        report.mainTabs = mainTabs;

        // Clica em cada aba principal e captura screenshot
        for (let i = 0; i < mainTabs.length; i++) {
            const tab = mainTabs[i];
            if (!tab.text || tab.text.length === 0) continue;

            console.log(`\n[Aba Principal ${i + 1}] ${tab.text}`);

            try {
                // Tenta clicar na aba
                if (tab.href && tab.href.startsWith('#')) {
                    await page.click(`a[href="${tab.href}"]`);
                } else {
                    await page.click(`ul.ui-tabs-nav li:nth-child(${i + 1}) a`);
                }

                await page.waitForTimeout(DELAYS.medium);

                const safeName = tab.text.toLowerCase().replace(/[^a-z0-9]/g, '-').substring(0, 20);
                await saveScreenshot(page, `03-tab-${String(i + 1).padStart(2, '0')}-${safeName}`);

                // Se for a aba "Details", procura sub-abas
                if (tab.text.toLowerCase().includes('detail')) {
                    console.log('[Abas] Aba Details encontrada, procurando sub-abas...');

                    const subTabs = await page.evaluate(() => {
                        const subs = [];
                        // Procura sub-abas dentro do conteúdo da aba Details
                        document.querySelectorAll('.ui-tabs-panel.ui-widget-content ul.ui-tabs-nav li a, #detailTabs li a').forEach((tab, idx) => {
                            subs.push({
                                index: idx,
                                text: tab.textContent?.trim() || '',
                                href: tab.getAttribute('href') || ''
                            });
                        });
                        // Tenta outro padrão de sub-abas
                        if (subs.length === 0) {
                            document.querySelectorAll('.detail-tabs a, .sub-tabs a, [class*="tabs"] li a').forEach((tab, idx) => {
                                subs.push({
                                    index: idx,
                                    text: tab.textContent?.trim() || '',
                                    href: tab.getAttribute('href') || ''
                                });
                            });
                        }
                        return subs;
                    });

                    console.log(`[Sub-abas] Encontradas ${subTabs.length} sub-abas em Details`);
                    report.detailsSubTabs = subTabs;

                    // Clica em cada sub-aba
                    for (let j = 0; j < subTabs.length; j++) {
                        const subTab = subTabs[j];
                        if (!subTab.text) continue;

                        console.log(`  [Sub-aba ${j + 1}] ${subTab.text}`);

                        try {
                            if (subTab.href && subTab.href.startsWith('#')) {
                                await page.click(`a[href="${subTab.href}"]`);
                            } else {
                                // Tenta clicar pelo texto
                                await page.click(`a:has-text("${subTab.text}")`);
                            }

                            await page.waitForTimeout(DELAYS.short);

                            const subSafeName = subTab.text.toLowerCase().replace(/[^a-z0-9]/g, '-').substring(0, 20);
                            await saveScreenshot(page, `04-subtab-${String(j + 1).padStart(2, '0')}-${subSafeName}`);

                            // Extrai campos desta sub-aba
                            const fields = await extractFields(page);
                            report.allFields.push({
                                tab: tab.text,
                                subTab: subTab.text,
                                fields: fields
                            });

                        } catch (e) {
                            console.log(`  [Sub-aba ${j + 1}] Erro: ${e.message}`);
                        }
                    }
                } else {
                    // Extrai campos desta aba principal (não Details)
                    const fields = await extractFields(page);
                    report.allFields.push({
                        tab: tab.text,
                        subTab: null,
                        fields: fields
                    });
                }

            } catch (e) {
                console.log(`[Aba Principal ${i + 1}] Erro: ${e.message}`);
            }
        }

        // Se não encontrou abas UI, tenta capturar todos os campos da página
        if (mainTabs.length === 0) {
            console.log('\n[Fallback] Nenhuma aba encontrada, extraindo campos da página...');

            const allLabels = await page.evaluate(() => {
                const labels = [];
                document.querySelectorAll('label, th, .field-label, dt, legend').forEach(el => {
                    const text = el.textContent?.trim();
                    if (text && text.length > 1 && text.length < 100) {
                        // Tenta encontrar o input associado
                        const forAttr = el.getAttribute('for');
                        const input = forAttr ? document.getElementById(forAttr) : el.querySelector('input, select, textarea');

                        labels.push({
                            label: text,
                            inputName: input?.name || input?.id || '',
                            inputType: input?.tagName?.toLowerCase() || '',
                            inputSubType: input?.type || ''
                        });
                    }
                });
                return labels;
            });

            console.log(`[Fallback] Encontrados ${allLabels.length} labels/campos`);
            report.allFields.push({
                tab: 'Página completa',
                subTab: null,
                fields: allLabels
            });
        }

        // Salva HTML completo
        const html = await page.content();
        fs.writeFileSync(path.join(SCREENSHOTS_DIR, 'full-page.html'), html);
        console.log('\n[HTML] Página completa salva');

    } catch (error) {
        console.error('\n[ERRO]', error.message);
        await saveScreenshot(page, 'error');
    } finally {
        // Salva relatório
        const reportPath = path.join(SCREENSHOTS_DIR, 'event-fields-report.json');
        fs.writeFileSync(reportPath, JSON.stringify(report, null, 2));
        console.log(`\n[Relatório] Salvo: ${reportPath}`);

        // Gera resumo markdown
        const summary = generateSummary(report);
        const summaryPath = path.join(SCREENSHOTS_DIR, 'EVENT-FIELDS-SUMMARY.md');
        fs.writeFileSync(summaryPath, summary);
        console.log(`[Resumo] Salvo: ${summaryPath}`);

        await browser.close();
    }

    console.log('\n' + '='.repeat(60));
    console.log('Scraping concluído!');
    console.log('='.repeat(60));
}

async function extractFields(page) {
    return await page.evaluate(() => {
        const fields = [];

        // Inputs, selects, textareas
        document.querySelectorAll('input:not([type="hidden"]), select, textarea').forEach(input => {
            const label = document.querySelector(`label[for="${input.id}"]`)?.textContent?.trim() ||
                         input.closest('label')?.textContent?.trim() ||
                         input.placeholder ||
                         input.name ||
                         '';

            fields.push({
                tag: input.tagName.toLowerCase(),
                type: input.type || '',
                name: input.name || '',
                id: input.id || '',
                label: label.substring(0, 50),
                required: input.required || false
            });
        });

        // Labels sem inputs associados (podem indicar campos de texto)
        document.querySelectorAll('label, th, dt, .field-label').forEach(el => {
            const text = el.textContent?.trim();
            if (text && text.length > 1 && text.length < 50) {
                // Verifica se já não foi capturado
                if (!fields.some(f => f.label === text)) {
                    fields.push({
                        tag: 'label-only',
                        label: text
                    });
                }
            }
        });

        return fields;
    });
}

function generateSummary(report) {
    let md = `# Campos do Evento - Sistema Legado English Australia\n\n`;
    md += `**Data:** ${report.timestamp}\n\n`;

    if (report.mainTabs.length > 0) {
        md += `## Abas Principais\n\n`;
        report.mainTabs.forEach((tab, i) => {
            md += `${i + 1}. **${tab.text}**\n`;
        });
        md += `\n`;
    }

    if (report.detailsSubTabs.length > 0) {
        md += `## Sub-abas de Details\n\n`;
        report.detailsSubTabs.forEach((tab, i) => {
            md += `${i + 1}. ${tab.text}\n`;
        });
        md += `\n`;
    }

    if (report.allFields.length > 0) {
        md += `## Campos por Aba\n\n`;
        report.allFields.forEach(section => {
            md += `### ${section.tab}${section.subTab ? ' > ' + section.subTab : ''}\n\n`;

            if (section.fields && section.fields.length > 0) {
                md += `| Campo | Tipo | Nome/ID |\n|-------|------|--------|\n`;
                section.fields.forEach(f => {
                    if (f.label) {
                        md += `| ${f.label} | ${f.tag || ''}${f.type ? '/' + f.type : ''} | ${f.name || f.id || '-'} |\n`;
                    }
                });
                md += `\n`;
            }
        });
    }

    md += `## Mapeamento Sugerido para Eau Events\n\n`;
    md += `| Campo Legado | Campo Eau Events | Observações |\n|--------------|------------------|-------------|\n`;
    md += `| Event Title | evt_title | - |\n`;
    md += `| Start Date | evt_start_date | - |\n`;
    md += `| End Date | evt_end_date | - |\n`;
    md += `| Description | evt_description | - |\n`;
    md += `| Location | evt_location | - |\n`;
    md += `| Capacity | evt_capacity | - |\n`;
    md += `| Price | evt_price | - |\n`;
    md += `| Status | evt_status | Mapear valores |\n`;

    return md;
}

main().catch(console.error);
