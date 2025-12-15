const { chromium } = require('playwright');
const path = require('path');

(async () => {
    const browser = await chromium.launch({ headless: false });
    const context = await browser.newContext({ viewport: { width: 1920, height: 1080 } });
    const page = await context.newPage();

    const screenshotsDir = path.join(__dirname, 'presentation-screenshots', 'features');

    try {
        // 1. Login
        console.log('1. Fazendo login...');
        await page.goto('http://eau-site.local/login/');
        await page.waitForLoadState('networkidle');

        const userField = page.locator('input[name="log"], input[name="username"], input#user_login, input[type="text"]').first();
        const passField = page.locator('input[name="pwd"], input[name="password"], input#user_pass, input[type="password"]').first();

        await userField.fill('rrzillesg@gmail.com');
        await passField.fill('Pl@ttyPl@tty');
        await page.locator('button[type="submit"], input[type="submit"]').first().click();
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(2000);
        console.log('   Login OK');

        // 2. Ir para Members Management
        console.log('2. Indo para Members Management...');
        await page.goto('http://eau-site.local/admin/members/');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(3000);
        console.log('   Página carregada');

        // 3. Screenshot da página de members mostrando botão de tags na linha
        console.log('3. Capturando screenshot da tabela com botão de tags...');
        await page.screenshot({
            path: path.join(screenshotsDir, 'tags-01-members-table.png'),
            fullPage: false
        });
        console.log('   Screenshot 1 salvo');

        // 4. Clicar no botão de Quick Tags do primeiro membro
        console.log('4. Abrindo Quick Tags popover...');
        const tagsButton = page.locator('.eau-action-tags').first();
        if (await tagsButton.isVisible()) {
            await tagsButton.click();
            await page.waitForTimeout(500);

            // Screenshot do popover aberto
            await page.screenshot({
                path: path.join(screenshotsDir, 'tags-02-quick-tags-popover.png'),
                fullPage: false
            });
            console.log('   Screenshot 2 salvo (Quick Tags popover)');

            // Fechar popover clicando fora
            await page.click('body', { position: { x: 10, y: 10 } });
            await page.waitForTimeout(300);
        }

        // 5. Selecionar alguns membros para mostrar Bulk Tags
        console.log('5. Selecionando membros para Bulk Tags...');
        await page.locator('.eau-row-checkbox').first().click();
        await page.waitForTimeout(200);
        await page.locator('.eau-row-checkbox').nth(1).click();
        await page.waitForTimeout(200);
        await page.locator('.eau-row-checkbox').nth(2).click();
        await page.waitForTimeout(300);

        // Screenshot mostrando membros selecionados e botão Manage Tags visível
        await page.screenshot({
            path: path.join(screenshotsDir, 'tags-03-members-selected.png'),
            fullPage: false
        });
        console.log('   Screenshot 3 salvo (membros selecionados)');

        // 6. Abrir modal Bulk Tags
        console.log('6. Abrindo modal Bulk Tags...');
        const manageTagsBtn = page.locator('#eau-bulk-manage-tags');
        if (await manageTagsBtn.isVisible()) {
            await manageTagsBtn.click();
            await page.waitForTimeout(500);

            // Screenshot do modal Add Tags
            await page.screenshot({
                path: path.join(screenshotsDir, 'tags-04-bulk-modal-add.png'),
                fullPage: false
            });
            console.log('   Screenshot 4 salvo (Bulk Tags - Add)');

            // Clicar na tab Remove
            await page.locator('.eau-bulk-tags-tab[data-tab="remove"]').click();
            await page.waitForTimeout(300);

            // Screenshot do modal Remove Tags (com opção Remove All)
            await page.screenshot({
                path: path.join(screenshotsDir, 'tags-05-bulk-modal-remove.png'),
                fullPage: false
            });
            console.log('   Screenshot 5 salvo (Bulk Tags - Remove)');

            // Fechar modal
            await page.locator('#eau-bulk-tags-cancel-btn').click();
            await page.waitForTimeout(300);
        }

        console.log('\n✅ Todos os screenshots capturados com sucesso!');
        console.log(`   Salvos em: ${screenshotsDir}`);

    } catch (error) {
        console.error('Erro:', error.message);
        await page.screenshot({ path: path.join(screenshotsDir, 'error.png') });
    }

    await page.waitForTimeout(2000);
    await browser.close();
})();
