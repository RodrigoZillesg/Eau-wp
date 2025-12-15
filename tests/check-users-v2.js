/**
 * Script para verificar usuários existentes no sistema - v2
 */
const { chromium } = require('playwright');

async function main() {
    console.log('Iniciando verificação de usuários...\n');

    const browser = await chromium.launch({
        headless: false,
        slowMo: 100
    });

    const context = await browser.newContext({
        viewport: { width: 1920, height: 1080 }
    });

    const page = await context.newPage();
    page.setDefaultTimeout(60000);

    try {
        // Login como superAdmin
        console.log('Fazendo login como superAdmin...');
        await page.goto('http://eau-site.local/login/', { waitUntil: 'domcontentloaded' });
        await page.waitForSelector('input[name="log"]', { timeout: 30000 });
        await page.fill('input[name="log"]', 'rrzillesg@gmail.com');
        await page.fill('input[name="pwd"]', 'Pl@ttyPl@tty');
        await page.click('button[name="wp-submit"]');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(2000);

        // Ir para Members Management
        console.log('Navegando para Members Management...');
        await page.goto('http://eau-site.local/dashboard/members/', { waitUntil: 'networkidle' });
        await page.waitForTimeout(5000);

        // Capturar screenshot para ver a página
        await page.screenshot({ path: 'members-page.png', fullPage: true });
        console.log('Screenshot salvo: members-page.png');

        // Tentar extrair todos os usuários da tabela (sem filtro)
        console.log('\nExtraindo todos os usuários da tabela...');

        // Verificar se há dados
        const tableBody = await page.$('table.eau-data-table tbody');
        if (tableBody) {
            const rows = await tableBody.$$('tr');
            console.log(`Total de linhas na tabela: ${rows.length}`);

            for (let i = 0; i < Math.min(10, rows.length); i++) {
                const row = rows[i];
                const rowText = await row.textContent();
                console.log(`Linha ${i + 1}: ${rowText.substring(0, 150).replace(/\s+/g, ' ')}`);
            }
        }

        // Verificar se o carregamento ainda está acontecendo
        const loadingIndicator = await page.$('.eau-loading');
        if (loadingIndicator && await loadingIndicator.isVisible()) {
            console.log('Aguardando carregamento...');
            await page.waitForTimeout(5000);
        }

        // Verificar stats cards
        const statsCards = await page.$$('.eau-card-number');
        console.log('\nStats Cards encontrados:');
        for (const card of statsCards) {
            const text = await card.textContent();
            console.log(`  - ${text.trim()}`);
        }

        console.log('\n\nVerificação concluída!');

    } catch (error) {
        console.log('Erro:', error.message);
        console.log(error.stack);
    } finally {
        await browser.close();
    }
}

main();
