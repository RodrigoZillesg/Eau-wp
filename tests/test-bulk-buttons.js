const { chromium } = require('playwright');

(async () => {
    const browser = await chromium.launch({ headless: false });
    const context = await browser.newContext({
        viewport: { width: 1920, height: 1080 }
    });
    const page = await context.newPage();

    console.log('\n=== TESTE DOS BOTOES DELETE SELECTED E MANAGE TAGS ===\n');

    try {
        // PASSO 1: Login
        console.log('PASSO 1: Fazendo login...');
        await page.goto('http://eau-site.local/login/');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(2000);
        
        await page.fill('input[name="log"]', 'rrzillesg@gmail.com');
        await page.fill('input[name="pwd"]', 'Pl@ttyPl@tty');
        await page.click('button[type="submit"]:has-text("Log In")');
        
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(3000);
        console.log('   Login realizado com sucesso!\n');

        // PASSO 2: Navegar para Members
        console.log('PASSO 2: Navegando para a pagina de membros...');
        await page.goto('http://eau-site.local/dashboard/manage-members/');
        await page.waitForLoadState('networkidle');
        
        // Esperar a tabela carregar
        console.log('   Aguardando tabela carregar...');
        await page.waitForTimeout(5000);
        console.log('   Tabela carregada!\n');

        // PASSO 3: Verificar se os botoes estao escondidos inicialmente
        console.log('PASSO 3: Verificando visibilidade inicial dos botoes...');
        
        // IDs corretos
        const deleteSelectedBtn = page.locator('#eau-bulk-delete-members');
        const manageTagsBtn = page.locator('#eau-bulk-manage-tags');
        
        const deleteExists = await deleteSelectedBtn.count() > 0;
        const tagsExists = await manageTagsBtn.count() > 0;
        
        console.log('   Delete Selected existe no DOM: ' + deleteExists);
        console.log('   Manage Tags existe no DOM: ' + tagsExists);
        
        if (deleteExists) {
            const deleteVisible = await deleteSelectedBtn.isVisible();
            const msg1 = deleteVisible ? 'VISIVEL (ERRO!)' : 'ESCONDIDO (OK)';
            console.log('   Botao "Delete Selected": ' + msg1);
        }
        
        if (tagsExists) {
            const tagsVisible = await manageTagsBtn.isVisible();
            const msg2 = tagsVisible ? 'VISIVEL (ERRO!)' : 'ESCONDIDO (OK)';
            console.log('   Botao "Manage Tags": ' + msg2);
        }
        console.log('');

        // PASSO 4: Selecionar um checkbox
        console.log('PASSO 4: Selecionando um checkbox de uma linha...');
        const firstCheckbox = page.locator('.eau-row-checkbox').first();
        const checkboxExists = await firstCheckbox.count() > 0;
        
        if (checkboxExists) {
            await firstCheckbox.click();
            await page.waitForTimeout(500);
            console.log('   Checkbox selecionado!\n');
        } else {
            console.log('   ERRO: Checkbox nao encontrado!\n');
        }

        // PASSO 5: Verificar se os botoes aparecem
        console.log('PASSO 5: Verificando se os botoes aparecem apos selecao...');
        
        if (deleteExists) {
            const deleteVisibleAfter = await deleteSelectedBtn.isVisible();
            const msg3 = deleteVisibleAfter ? 'VISIVEL (OK)' : 'ESCONDIDO (ERRO!)';
            console.log('   Botao "Delete Selected": ' + msg3);
        }
        
        if (tagsExists) {
            const tagsVisibleAfter = await manageTagsBtn.isVisible();
            const msg4 = tagsVisibleAfter ? 'VISIVEL (OK)' : 'ESCONDIDO (ERRO!)';
            console.log('   Botao "Manage Tags": ' + msg4);
        }
        console.log('');

        // PASSO 6: Desmarcar o checkbox
        console.log('PASSO 6: Desmarcando o checkbox...');
        if (checkboxExists) {
            await firstCheckbox.click();
            await page.waitForTimeout(500);
            console.log('   Checkbox desmarcado!\n');
        }

        // PASSO 7: Verificar se os botoes desaparecem
        console.log('PASSO 7: Verificando se os botoes desaparecem apos desmarcar...');
        
        if (deleteExists) {
            const deleteVisibleFinal = await deleteSelectedBtn.isVisible();
            const msg5 = deleteVisibleFinal ? 'VISIVEL (ERRO!)' : 'ESCONDIDO (OK)';
            console.log('   Botao "Delete Selected": ' + msg5);
        }
        
        if (tagsExists) {
            const tagsVisibleFinal = await manageTagsBtn.isVisible();
            const msg6 = tagsVisibleFinal ? 'VISIVEL (ERRO!)' : 'ESCONDIDO (OK)';
            console.log('   Botao "Manage Tags": ' + msg6);
        }
        
        console.log('\n=== TESTE CONCLUIDO ===\n');

        // Tirar screenshot final
        await page.screenshot({ path: 'bulk-buttons-test-result.png', fullPage: false });
        console.log('Screenshot salvo em: tests/bulk-buttons-test-result.png');

        await page.waitForTimeout(3000);

    } catch (error) {
        console.error('ERRO:', error.message);
        await page.screenshot({ path: 'bulk-buttons-test-error.png', fullPage: true });
    }

    await browser.close();
})();
