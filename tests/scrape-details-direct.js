/**
 * Scraper direto para a aba Details do evento
 * Navega diretamente para a URL da aba Details
 */

const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const SCREENSHOTS_DIR = './legacy-events-screenshots/details-direct';

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
    console.log('Scraper Direto - Aba Details');
    console.log('='.repeat(60));

    const browser = await chromium.launch({ headless: false, slowMo: 300 });
    const context = await browser.newContext({ viewport: { width: 1920, height: 1080 } });
    const page = await context.newPage();

    try {
        // 1. Login - vai para a página de eventos que requer login
        console.log('\n[1] Login...');
        await page.goto('https://www.englishaustralia.com.au/administration/eventsadmin/events', {
            waitUntil: 'networkidle',
            timeout: 60000
        });

        const usernameField = await page.$('input[name="loginusername"]');
        if (usernameField) {
            console.log('Preenchendo credenciais...');
            await usernameField.fill('rodrigo.zillesg@platty.tech');
            await page.fill('input[name="loginpassword"]', 'Salmo119:97');
            await page.click('input[type="submit"]');
            await page.waitForTimeout(8000);
        }

        await saveScreenshot(page, '01-apos-login');

        // 2. Navegar diretamente para a aba Details de um evento
        console.log('\n[2] Navegando para Details...');

        // Primeiro, pegar o ID do primeiro evento
        const eventId = await page.evaluate(() => {
            const cb = document.querySelector('input[id^="eventsListDataGridActionCheckboxes"]');
            return cb ? cb.value : null;
        });

        console.log(`Event ID: ${eventId}`);

        if (!eventId) {
            throw new Error('Nenhum evento encontrado');
        }

        // Navegar diretamente para a página Details
        const detailsUrl = `https://www.englishaustralia.com.au/administration/eventsadmin/events/event_details?eventSerial=${eventId}`;
        console.log(`URL: ${detailsUrl}`);

        await page.goto(detailsUrl, {
            waitUntil: 'networkidle',
            timeout: 60000
        });

        // Aguardar o conteúdo carregar
        console.log('\n[3] Aguardando conteúdo (10s)...');
        await page.waitForTimeout(10000);

        await saveScreenshot(page, '02-details-page');

        // 3. Analisar a estrutura da página Details
        console.log('\n[4] Analisando estrutura...');

        const pageStructure = await page.evaluate(() => {
            const result = {
                url: window.location.href,
                title: document.title,
                mainTabs: [],
                subTabs: [],
                forms: [],
                inputs: [],
                labels: [],
                fieldsets: [],
                sections: []
            };

            // Main tabs (igual às 6 abas principais)
            document.querySelectorAll('td.tab_on, td.tab_off').forEach(tab => {
                const text = tab.textContent?.trim();
                const isActive = tab.classList.contains('tab_on');
                const onclick = tab.getAttribute('onclick');
                if (text) {
                    result.mainTabs.push({ text, isActive, onclick });
                }
            });

            // Sub-tabs - procura vários padrões
            const subTabSelectors = [
                'ul.ui-tabs-nav li a',
                '.nav-tabs a',
                '[role="tab"]',
                '.tabs li a',
                '.sub-tabs a',
                '.tab-nav a',
                'a.tab-link',
                'td.subtab_on, td.subtab_off',
                'td[class*="tab"] a'
            ];

            subTabSelectors.forEach(selector => {
                document.querySelectorAll(selector).forEach(tab => {
                    const text = tab.textContent?.trim();
                    if (text && text.length > 0 && text.length < 50) {
                        result.subTabs.push({
                            selector,
                            text,
                            href: tab.getAttribute('href') || '',
                            onclick: tab.getAttribute('onclick') || ''
                        });
                    }
                });
            });

            // Formulários
            document.querySelectorAll('form').forEach(form => {
                result.forms.push({
                    id: form.id,
                    name: form.name,
                    action: form.action,
                    method: form.method
                });
            });

            // Fieldsets (geralmente contêm grupos de campos)
            document.querySelectorAll('fieldset, .sbFieldGroupFieldset').forEach(fs => {
                const legend = fs.querySelector('legend, .sbFieldGroupHeading');
                if (legend) {
                    result.fieldsets.push(legend.textContent?.trim());
                }
            });

            // Todos os inputs
            document.querySelectorAll('input:not([type="hidden"]), select, textarea').forEach(input => {
                result.inputs.push({
                    tag: input.tagName.toLowerCase(),
                    type: input.type || '',
                    name: input.name || '',
                    id: input.id || '',
                    placeholder: input.placeholder || '',
                    value: input.type === 'checkbox' || input.type === 'radio' ? input.checked : (input.value || '').substring(0, 50)
                });
            });

            // Labels
            document.querySelectorAll('label, .sbFormItemLabel, th, legend, dt').forEach(label => {
                const text = label.textContent?.trim();
                if (text && text.length > 1 && text.length < 100) {
                    result.labels.push(text);
                }
            });

            // Seções/Headers
            document.querySelectorAll('h1, h2, h3, h4, .sbSectionHeading, .section-title').forEach(h => {
                const text = h.textContent?.trim();
                if (text && text.length > 0) {
                    result.sections.push(text);
                }
            });

            return result;
        });

        // Exibir resultados
        console.log('\n=== ESTRUTURA DA PÁGINA DETAILS ===');
        console.log(`URL: ${pageStructure.url}`);
        console.log(`\nMain Tabs (${pageStructure.mainTabs.length}):`);
        pageStructure.mainTabs.forEach((tab, i) => {
            console.log(`  ${i + 1}. ${tab.text} ${tab.isActive ? '(ATIVA)' : ''}`);
        });

        console.log(`\nSub-Tabs (${pageStructure.subTabs.length}):`);
        const uniqueSubTabs = [...new Map(pageStructure.subTabs.map(t => [t.text, t])).values()];
        uniqueSubTabs.forEach((tab, i) => {
            console.log(`  ${i + 1}. ${tab.text}`);
        });

        console.log(`\nFieldsets (${pageStructure.fieldsets.length}):`);
        pageStructure.fieldsets.forEach((fs, i) => {
            console.log(`  ${i + 1}. ${fs}`);
        });

        console.log(`\nSeções (${pageStructure.sections.length}):`);
        pageStructure.sections.forEach((s, i) => {
            console.log(`  ${i + 1}. ${s}`);
        });

        console.log(`\nInputs: ${pageStructure.inputs.length}`);
        console.log(`Labels: ${pageStructure.labels.length}`);

        // Salvar análise em JSON
        ensureDir(SCREENSHOTS_DIR);
        fs.writeFileSync(
            path.join(SCREENSHOTS_DIR, 'details-structure.json'),
            JSON.stringify(pageStructure, null, 2)
        );

        // 4. Fazer scroll e capturar mais screenshots
        console.log('\n[5] Capturando seções da página...');

        // Scroll para ver mais conteúdo
        await page.evaluate(() => window.scrollTo(0, 500));
        await page.waitForTimeout(1000);
        await saveScreenshot(page, '03-details-scroll-1');

        await page.evaluate(() => window.scrollTo(0, 1000));
        await page.waitForTimeout(1000);
        await saveScreenshot(page, '04-details-scroll-2');

        await page.evaluate(() => window.scrollTo(0, 1500));
        await page.waitForTimeout(1000);
        await saveScreenshot(page, '05-details-scroll-3');

        await page.evaluate(() => window.scrollTo(0, 2000));
        await page.waitForTimeout(1000);
        await saveScreenshot(page, '06-details-scroll-4');

        // 5. Salvar HTML completo
        const html = await page.content();
        fs.writeFileSync(path.join(SCREENSHOTS_DIR, 'details-page.html'), html);
        console.log('\nHTML salvo');

        // 6. Gerar relatório de labels únicos
        const uniqueLabels = [...new Set(pageStructure.labels)].sort();

        let report = `# Análise da Aba Details - English Australia Events\n\n`;
        report += `**Data:** ${new Date().toISOString()}\n`;
        report += `**URL:** ${pageStructure.url}\n\n`;

        report += `## Estrutura Principal\n\n`;
        report += `- Main Tabs: ${pageStructure.mainTabs.length}\n`;
        report += `- Sub-Tabs encontradas: ${uniqueSubTabs.length}\n`;
        report += `- Fieldsets: ${pageStructure.fieldsets.length}\n`;
        report += `- Inputs: ${pageStructure.inputs.length}\n`;
        report += `- Labels: ${uniqueLabels.length}\n\n`;

        if (uniqueSubTabs.length > 0) {
            report += `## Sub-Tabs\n\n`;
            uniqueSubTabs.forEach((tab, i) => {
                report += `${i + 1}. **${tab.text}**\n`;
            });
            report += '\n';
        }

        report += `## Fieldsets/Seções\n\n`;
        pageStructure.fieldsets.forEach((fs, i) => {
            report += `${i + 1}. **${fs}**\n`;
        });
        report += '\n';

        report += `## Labels/Campos\n\n`;
        uniqueLabels.forEach(label => {
            report += `- ${label}\n`;
        });
        report += '\n';

        report += `## Inputs\n\n`;
        report += `| Tipo | Nome | ID |\n|------|------|----|---|\n`;
        pageStructure.inputs.forEach(input => {
            report += `| ${input.tag}/${input.type} | ${input.name || '-'} | ${input.id || '-'} |\n`;
        });

        fs.writeFileSync(path.join(SCREENSHOTS_DIR, 'DETAILS-ANALYSIS.md'), report);
        console.log('Relatório salvo: DETAILS-ANALYSIS.md');

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
