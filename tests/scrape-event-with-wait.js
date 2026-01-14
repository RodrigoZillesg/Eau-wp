/**
 * Eau System - Event Scraper with Extended Wait
 *
 * O sistema legado carrega conteúdo via JavaScript.
 * Este script aguarda mais tempo para o conteúdo carregar.
 */

const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const SCREENSHOTS_DIR = './legacy-events-screenshots/with-wait';

function ensureDir(dir) {
    if (!fs.existsSync(dir)) {
        fs.mkdirSync(dir, { recursive: true });
    }
}

async function saveScreenshot(page, name) {
    ensureDir(SCREENSHOTS_DIR);
    const filepath = path.join(SCREENSHOTS_DIR, `${name}.png`);
    await page.screenshot({ path: filepath, fullPage: true });
    console.log(`[Screenshot] ${name}.png`);
}

async function main() {
    console.log('='.repeat(60));
    console.log('Scraper com espera estendida');
    console.log('='.repeat(60));

    const browser = await chromium.launch({
        headless: false,
        slowMo: 100
    });

    const context = await browser.newContext({
        viewport: { width: 1920, height: 1080 }
    });

    const page = await context.newPage();

    try {
        // 1. Login
        console.log('\n[1] Login...');
        await page.goto('https://www.englishaustralia.com.au/administration/eventsadmin/events', {
            waitUntil: 'networkidle',
            timeout: 60000
        });

        const usernameField = await page.$('input[name="loginusername"]');
        if (usernameField) {
            await usernameField.fill('rodrigo.zillesg@platty.tech');
            await page.fill('input[name="loginpassword"]', 'Salmo119:97');
            await page.click('input[type="submit"]');
            await page.waitForTimeout(10000);
        }

        await saveScreenshot(page, '01-lista-eventos');

        // 2. Pegar ID do evento
        console.log('\n[2] Buscando evento...');
        const eventId = await page.evaluate(() => {
            const cb = document.querySelector('input[id^="eventsListDataGridActionCheckboxes"]');
            return cb ? cb.value : null;
        });

        console.log(`Event ID: ${eventId}`);

        if (!eventId) {
            throw new Error('Evento não encontrado');
        }

        // 3. Clicar no checkbox do evento para selecioná-lo
        console.log('\n[3] Selecionando evento...');
        await page.click(`#eventsListDataGridActionCheckboxes0`);
        await page.waitForTimeout(2000);
        await saveScreenshot(page, '02-evento-selecionado');

        // 4. Clicar no botão Edit
        console.log('\n[4] Clicando em Edit...');

        // Procura o botão de editar
        const editButton = await page.$('#eventsListDataGridToolbar1, button:has-text("Edit"), .sbToolbarButton:has(img[src*="edit"])');

        if (editButton) {
            await editButton.click();
            console.log('Botão Edit clicado');
        } else {
            // Tenta executar a função JavaScript diretamente
            console.log('Executando edit via JavaScript...');
            await page.evaluate((id) => {
                if (typeof events2 !== 'undefined' && events2.admin && events2.admin.events) {
                    events2.admin.events.edit(id);
                }
            }, eventId);
        }

        // 5. Aguarda o conteúdo carregar (tempo estendido)
        console.log('\n[5] Aguardando carregamento (15s)...');
        await page.waitForTimeout(15000);
        await saveScreenshot(page, '03-apos-edit-15s');

        // 6. Verifica se há abas na página
        console.log('\n[6] Analisando estrutura da página...');

        const pageAnalysis = await page.evaluate(() => {
            const analysis = {
                url: window.location.href,
                title: document.title,
                forms: [],
                tabs: [],
                inputs: [],
                labels: [],
                sections: []
            };

            // Formulários
            document.querySelectorAll('form').forEach(form => {
                analysis.forms.push({
                    id: form.id,
                    name: form.name,
                    action: form.action
                });
            });

            // Abas (vários padrões)
            ['ul.ui-tabs-nav li a', '.nav-tabs a', '[role="tab"]', '.tabs li a', 'a[href^="#"]'].forEach(selector => {
                document.querySelectorAll(selector).forEach(tab => {
                    const text = tab.textContent?.trim();
                    if (text && text.length > 0 && text.length < 50) {
                        analysis.tabs.push({
                            selector,
                            text,
                            href: tab.getAttribute('href')
                        });
                    }
                });
            });

            // Inputs
            document.querySelectorAll('input:not([type="hidden"]), select, textarea').forEach(input => {
                analysis.inputs.push({
                    tag: input.tagName.toLowerCase(),
                    type: input.type,
                    name: input.name,
                    id: input.id,
                    placeholder: input.placeholder
                });
            });

            // Labels
            document.querySelectorAll('label, th, .field-label, legend, dt').forEach(label => {
                const text = label.textContent?.trim();
                if (text && text.length > 1 && text.length < 100) {
                    analysis.labels.push(text);
                }
            });

            // Seções/Fieldsets
            document.querySelectorAll('fieldset, .section, .panel, .card, [class*="section"]').forEach(section => {
                const title = section.querySelector('legend, .title, .header, h2, h3')?.textContent?.trim();
                if (title) {
                    analysis.sections.push(title);
                }
            });

            return analysis;
        });

        console.log('\nAnálise da página:');
        console.log(`- URL: ${pageAnalysis.url}`);
        console.log(`- Formulários: ${pageAnalysis.forms.length}`);
        console.log(`- Abas: ${pageAnalysis.tabs.length}`);
        console.log(`- Inputs: ${pageAnalysis.inputs.length}`);
        console.log(`- Labels: ${pageAnalysis.labels.length}`);

        // Mostra tabs encontradas
        if (pageAnalysis.tabs.length > 0) {
            console.log('\nAbas encontradas:');
            pageAnalysis.tabs.forEach((tab, i) => {
                console.log(`  ${i + 1}. ${tab.text}`);
            });
        }

        // Salva análise
        const analysisPath = path.join(SCREENSHOTS_DIR, 'page-analysis.json');
        fs.writeFileSync(analysisPath, JSON.stringify(pageAnalysis, null, 2));
        console.log(`\nAnálise salva: ${analysisPath}`);

        // 7. Se encontrou abas, clica em cada uma
        if (pageAnalysis.tabs.length > 0) {
            console.log('\n[7] Navegando pelas abas...');

            for (let i = 0; i < Math.min(pageAnalysis.tabs.length, 15); i++) {
                const tab = pageAnalysis.tabs[i];
                console.log(`\nClicando na aba: ${tab.text}`);

                try {
                    if (tab.href && tab.href.startsWith('#')) {
                        await page.click(`a[href="${tab.href}"]`);
                    } else {
                        await page.click(`text="${tab.text}"`);
                    }

                    await page.waitForTimeout(3000);

                    const safeName = tab.text.toLowerCase().replace(/[^a-z0-9]/g, '-').substring(0, 25);
                    await saveScreenshot(page, `04-tab-${String(i + 1).padStart(2, '0')}-${safeName}`);

                } catch (e) {
                    console.log(`  Erro: ${e.message}`);
                }
            }
        }

        // 8. Salva HTML completo
        const html = await page.content();
        fs.writeFileSync(path.join(SCREENSHOTS_DIR, 'page-content.html'), html);
        console.log('\nHTML salvo');

        // 9. Lista de labels únicas
        const uniqueLabels = [...new Set(pageAnalysis.labels)].sort();
        console.log(`\n${uniqueLabels.length} labels únicos encontrados`);

        // Gera relatório
        let report = `# Análise do Sistema Legado - English Australia Events\n\n`;
        report += `**Data:** ${new Date().toISOString()}\n`;
        report += `**URL:** ${pageAnalysis.url}\n\n`;

        report += `## Estrutura\n\n`;
        report += `- Formulários: ${pageAnalysis.forms.length}\n`;
        report += `- Abas: ${pageAnalysis.tabs.length}\n`;
        report += `- Inputs: ${pageAnalysis.inputs.length}\n`;
        report += `- Labels: ${uniqueLabels.length}\n\n`;

        if (pageAnalysis.tabs.length > 0) {
            report += `## Abas\n\n`;
            pageAnalysis.tabs.forEach((tab, i) => {
                report += `${i + 1}. **${tab.text}**\n`;
            });
            report += '\n';
        }

        report += `## Labels/Campos\n\n`;
        uniqueLabels.forEach(label => {
            report += `- ${label}\n`;
        });

        report += `\n## Inputs\n\n`;
        report += `| Tipo | Nome | ID |\n|------|------|----|\n`;
        pageAnalysis.inputs.forEach(input => {
            report += `| ${input.tag}/${input.type || ''} | ${input.name || '-'} | ${input.id || '-'} |\n`;
        });

        fs.writeFileSync(path.join(SCREENSHOTS_DIR, 'ANALYSIS-REPORT.md'), report);
        console.log('Relatório salvo: ANALYSIS-REPORT.md');

    } catch (error) {
        console.error('\n[ERRO]', error.message);
        await saveScreenshot(page, 'error');
    } finally {
        await browser.close();
    }

    console.log('\n' + '='.repeat(60));
    console.log('Concluído!');
    console.log('='.repeat(60));
}

main().catch(console.error);
