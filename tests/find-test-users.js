/**
 * Script para encontrar usuários de teste de cada tipo
 */
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

async function main() {
    console.log('='.repeat(60));
    console.log('BUSCANDO USUÁRIOS DE TESTE');
    console.log('='.repeat(60));

    const browser = await chromium.launch({
        headless: false,
        slowMo: 100
    });

    const context = await browser.newContext({
        viewport: { width: 1920, height: 1080 }
    });

    const page = await context.newPage();
    page.setDefaultTimeout(60000);

    const foundUsers = {};

    try {
        // Login como superAdmin
        console.log('\n1. Fazendo login como superAdmin...');
        await page.goto('http://eau-site.local/login/', { waitUntil: 'domcontentloaded' });
        await page.waitForSelector('input[name="log"]', { timeout: 30000 });
        await page.fill('input[name="log"]', 'rrzillesg@gmail.com');
        await page.fill('input[name="pwd"]', 'Pl@ttyPl@tty');
        await page.click('button[name="wp-submit"]');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(2000);

        // O superAdmin atual
        foundUsers.superAdmin = {
            email: 'rrzillesg@gmail.com',
            password: 'Pl@ttyPl@tty',
            name: 'Rodrigo Zillesg'
        };
        console.log('   superAdmin encontrado: rrzillesg@gmail.com');

        // Ir para Members Management
        console.log('\n2. Navegando para Members Management...');
        await page.goto('http://eau-site.local/dashboard/members/', { waitUntil: 'networkidle' });
        await page.waitForTimeout(3000);

        // Buscar por usuários de cada tipo
        const typesToFind = ['Admin', 'institutionAdmin', 'Member'];

        for (const userType of typesToFind) {
            console.log(`\n3. Buscando usuário do tipo: ${userType}...`);

            // Abrir filtros
            const filterBtn = await page.$('#eau-filters-toggle');
            if (filterBtn) {
                const panel = await page.$('#eau-filters-panel');
                if (panel && !(await panel.isVisible())) {
                    await filterBtn.click();
                    await page.waitForTimeout(500);
                }
            }

            // Aplicar filtro de tipo
            await page.selectOption('#eau-filter-role', userType);
            await page.waitForTimeout(300);

            // Aplicar filtros (usando classe)
            await page.click('.eau-filters-apply');
            await page.waitForTimeout(3000);

            // Capturar screenshot do resultado
            await page.screenshot({ path: `users-${userType}.png`, fullPage: false });
            console.log(`   Screenshot salvo: users-${userType}.png`);

            // Verificar se há resultados
            const rows = await page.$$('tbody tr');
            if (rows.length > 0) {
                // Pegar o primeiro usuário da lista
                const firstRow = rows[0];
                const cells = await firstRow.$$('td');
                if (cells.length >= 3) {
                    const nameCell = await cells[1].textContent();
                    const emailCell = await cells[2].textContent();

                    foundUsers[userType] = {
                        name: nameCell.trim(),
                        email: emailCell.trim()
                    };
                    console.log(`   Encontrado: ${nameCell.trim()} (${emailCell.trim()})`);
                }
            } else {
                console.log(`   Nenhum usuário encontrado do tipo ${userType}`);
            }

            // Resetar filtros (usando classe)
            await page.click('.eau-filters-clear');
            await page.waitForTimeout(1000);
        }

        // Resultado final
        console.log('\n' + '='.repeat(60));
        console.log('USUÁRIOS ENCONTRADOS:');
        console.log('='.repeat(60));
        console.log(JSON.stringify(foundUsers, null, 2));

        // Salvar em arquivo
        fs.writeFileSync(
            path.join(__dirname, 'test-users.json'),
            JSON.stringify(foundUsers, null, 2)
        );
        console.log('\nArquivo salvo: test-users.json');

    } catch (error) {
        console.log('Erro:', error.message);
        console.log(error.stack);
    } finally {
        await browser.close();
    }
}

main();
