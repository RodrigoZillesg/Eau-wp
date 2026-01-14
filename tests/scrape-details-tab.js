/**
 * Scraper para a aba Details do evento
 */

const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const SCREENSHOTS_DIR = './legacy-events-screenshots/details-tab';

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
    console.log('Scraper da aba Details');
    console.log('='.repeat(60));

    const browser = await chromium.launch({ headless: false, slowMo: 200 });
    const context = await browser.newContext({ viewport: { width: 1920, height: 1080 } });
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
            await page.waitForTimeout(5000);
        }

        // 2. Selecionar evento e clicar Edit
        console.log('\n[2] Abrindo evento...');
        await page.click('#eventsListDataGridActionCheckboxes0');
        await page.waitForTimeout(1000);

        await page.click('#eventsListDataGridToolbar1');
        await page.waitForTimeout(10000);

        await saveScreenshot(page, '01-summary-tab');

        // 3. Clicar na aba Details
        console.log('\n[3] Clicando na aba Details...');
        await page.click('text=Details');
        await page.waitForTimeout(5000);

        await saveScreenshot(page, '02-details-tab');

        // 4. Capturar as sub-abas de Details
        console.log('\n[4] Analisando sub-abas de Details...');

        const subTabs = await page.evaluate(() => {
            const tabs = [];
            // Procura todas as abas/links dentro do conteúdo de Details
            document.querySelectorAll('a, .tab, li a, [role="tab"]').forEach(el => {
                const text = el.textContent?.trim();
                const href = el.getAttribute('href');
                const isVisible = el.offsetParent !== null;

                if (text && text.length > 0 && text.length < 50 && isVisible) {
                    // Evita duplicatas
                    if (!tabs.some(t => t.text === text)) {
                        tabs.push({
                            text,
                            href: href || '',
                            tagName: el.tagName
                        });
                    }
                }
            });
            return tabs;
        });

        console.log(`\nSub-abas/Links encontrados: ${subTabs.length}`);
        subTabs.forEach((tab, i) => {
            console.log(`  ${i + 1}. ${tab.text}`);
        });

        // 5. Tenta encontrar abas específicas de Details (geralmente em formato de tabs jQuery UI)
        const detailsContent = await page.evaluate(() => {
            // Procura conteúdo dentro de um painel de tabs
            const panels = document.querySelectorAll('.ui-tabs-panel, .tab-pane, .tab-content > div');
            const visiblePanel = Array.from(panels).find(p => p.offsetParent !== null);

            if (visiblePanel) {
                // Procura links de sub-navegação dentro do painel visível
                const innerTabs = visiblePanel.querySelectorAll('ul.ui-tabs-nav li a, .nav-tabs li a, .sub-nav a');
                return Array.from(innerTabs).map(a => ({
                    text: a.textContent?.trim(),
                    href: a.getAttribute('href')
                }));
            }
            return [];
        });

        if (detailsContent.length > 0) {
            console.log('\nSub-abas dentro de Details:');
            detailsContent.forEach((tab, i) => {
                console.log(`  ${i + 1}. ${tab.text}`);
            });
        }

        // 6. Captura screenshot com scroll para ver todo o conteúdo
        console.log('\n[5] Capturando conteúdo completo...');

        // Scroll down para ver mais conteúdo
        await page.evaluate(() => window.scrollTo(0, 500));
        await page.waitForTimeout(1000);
        await saveScreenshot(page, '03-details-scrolled');

        // 7. Tenta clicar em cada link dentro de Details
        console.log('\n[6] Navegando por sub-seções...');

        // Links típicos em formulários de eventos
        const commonSubTabs = [
            'Basic', 'Basic Info', 'General',
            'Dates', 'Schedule', 'Time',
            'Location', 'Venue',
            'Pricing', 'Prices', 'Fees',
            'Capacity', 'Registrations',
            'Description', 'Content',
            'Presenters', 'Speakers',
            'Settings', 'Options', 'Advanced',
            'CPD', 'Points'
        ];

        for (const tabName of commonSubTabs) {
            try {
                const link = await page.$(`a:has-text("${tabName}"), li:has-text("${tabName}") a`);
                if (link) {
                    const isVisible = await link.isVisible();
                    if (isVisible) {
                        console.log(`\nClicando em: ${tabName}`);
                        await link.click();
                        await page.waitForTimeout(2000);

                        const safeName = tabName.toLowerCase().replace(/[^a-z0-9]/g, '-');
                        await saveScreenshot(page, `04-subtab-${safeName}`);
                    }
                }
            } catch (e) {
                // Ignora erros
            }
        }

        // 8. Salva HTML
        const html = await page.content();
        fs.writeFileSync(path.join(SCREENSHOTS_DIR, 'details-page.html'), html);
        console.log('\nHTML salvo');

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
