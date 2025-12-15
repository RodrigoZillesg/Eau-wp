/**
 * Script para verificar usuários existentes no sistema
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
        await page.waitForTimeout(3000);

        // Extrair informações dos usuários da tabela
        console.log('Extraindo informações dos usuários...\n');

        // Verificar cada tipo de usuário usando o filtro
        const userTypes = ['superAdmin', 'Admin', 'institutionAdmin', 'Member'];

        for (const userType of userTypes) {
            console.log(`\n=== Verificando usuários do tipo: ${userType} ===`);

            // Abrir filtros
            const filterToggle = await page.$('#eau-filters-toggle');
            if (filterToggle) {
                const panel = await page.$('#eau-filters-panel');
                const isVisible = panel ? await panel.isVisible() : false;
                if (!isVisible) {
                    await filterToggle.click();
                    await page.waitForTimeout(500);
                }
            }

            // Selecionar o tipo no filtro (usando 'role' que é o key do filtro)
            const roleFilter = await page.$('#eau-filter-role');
            if (roleFilter) {
                await page.selectOption('#eau-filter-role', userType);
                await page.waitForTimeout(500);
            }

            // Clicar em Apply Filters
            const applyBtn = await page.$('#eau-apply-filters');
            if (applyBtn) {
                await applyBtn.click();
                await page.waitForTimeout(2000);
            }

            // Contar resultados
            const rows = await page.$$('table.eau-data-table tbody tr:not(.eau-no-results)');
            console.log(`Total de usuários ${userType}: ${rows.length}`);

            // Mostrar primeiro usuário de cada tipo (se existir)
            if (rows.length > 0) {
                for (let i = 0; i < Math.min(3, rows.length); i++) {
                    const row = rows[i];
                    const cells = await row.$$('td');
                    if (cells.length >= 3) {
                        const name = await cells[1].textContent();
                        const email = await cells[2].textContent();
                        console.log(`  - ${name.trim()} | ${email.trim()}`);
                    }
                }
            }

            // Resetar filtro
            const resetBtn = await page.$('#eau-reset-filters');
            if (resetBtn) {
                await resetBtn.click();
                await page.waitForTimeout(1000);
            }
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
