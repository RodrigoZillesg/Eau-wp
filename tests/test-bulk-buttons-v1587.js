const { chromium } = require('playwright');

(async () => {
    const browser = await chromium.launch({ 
        headless: false
    });
    
    const context = await browser.newContext();
    const page = await context.newPage();
    page.setDefaultTimeout(30000);
    
    console.log('=== TESTE DOS BOTOES BULK v1.58.7 ===\n');
    
    try {
        // 1. Login
        console.log('1. Fazendo login...');
        await page.goto('http://eau-site.local/login/');
        await page.waitForTimeout(2000);
        
        await page.fill('input[name="log"]', 'rrzillesg@gmail.com');
        await page.fill('input[name="pwd"]', 'Pl@ttyPl@tty');
        await page.click('button[type="submit"]');
        
        await page.waitForTimeout(4000);
        console.log('   Login realizado com sucesso!\n');
        
        // 2. Navegar para Members
        console.log('2. Navegando para Members Management...');
        await page.goto('http://eau-site.local/dashboard/manage-members/');
        await page.waitForTimeout(5000);
        
        // Aguardar tabela carregar
        try {
            await page.waitForSelector('.eau-data-table tbody tr', { timeout: 15000 });
        } catch (e) {
            console.log('   Aguardando mais tempo para tabela...');
            await page.waitForTimeout(5000);
        }
        await page.waitForTimeout(2000);
        
        console.log('   Pagina carregada!\n');
        
        // 3. Verificar versao do plugin
        console.log('3. Verificando versao do plugin...');
        const version = await page.evaluate(() => {
            const scripts = document.querySelectorAll('script[src*="eau-members"]');
            for (const s of scripts) {
                const match = s.src.match(/ver=([^&]+)/);
                if (match) return match[1];
            }
            return 'nao encontrada';
        });
        console.log('   Versao do JS carregado: ' + version + '\n');
        
        // 4. Verificar estado inicial dos botoes
        console.log('4. Verificando estado INICIAL dos botoes (sem selecao)...');
        
        const deleteBtn = await page.$('.eau-bulk-delete-btn');
        const tagsBtn = await page.$('.eau-bulk-tags-btn');
        
        if (!deleteBtn) {
            console.log('   ERRO: Botao Delete Selected NAO encontrado no DOM!');
        } else {
            const deleteVisible = await deleteBtn.isVisible();
            const deleteDisplay = await deleteBtn.evaluate(el => window.getComputedStyle(el).display);
            const deleteOpacity = await deleteBtn.evaluate(el => window.getComputedStyle(el).opacity);
            console.log('   Delete Selected: visible=' + deleteVisible + ', display=' + deleteDisplay + ', opacity=' + deleteOpacity);
            
            if (!deleteVisible) {
                console.log('   [OK] Delete Selected esta ESCONDIDO');
            } else {
                console.log('   [FALHA] Delete Selected deveria estar escondido!');
            }
        }
        
        if (!tagsBtn) {
            console.log('   ERRO: Botao Manage Tags NAO encontrado no DOM!');
        } else {
            const tagsVisible = await tagsBtn.isVisible();
            const tagsDisplay = await tagsBtn.evaluate(el => window.getComputedStyle(el).display);
            const tagsOpacity = await tagsBtn.evaluate(el => window.getComputedStyle(el).opacity);
            console.log('   Manage Tags: visible=' + tagsVisible + ', display=' + tagsDisplay + ', opacity=' + tagsOpacity);
            
            if (!tagsVisible) {
                console.log('   [OK] Manage Tags esta ESCONDIDO');
            } else {
                console.log('   [FALHA] Manage Tags deveria estar escondido!');
            }
        }
        
        // 5. Selecionar um membro
        console.log('\n5. Selecionando o primeiro membro...');
        const firstCheckbox = await page.$('.eau-data-table tbody .eau-member-checkbox');
        if (firstCheckbox) {
            await firstCheckbox.click();
            await page.waitForTimeout(500);
            console.log('   Checkbox clicado!\n');
        } else {
            console.log('   ERRO: Nenhum checkbox encontrado!');
        }
        
        // 6. Verificar se botoes aparecem
        console.log('6. Verificando estado APOS selecao...');
        
        if (deleteBtn) {
            const deleteVisibleAfter = await deleteBtn.isVisible();
            const deleteDisplayAfter = await deleteBtn.evaluate(el => window.getComputedStyle(el).display);
            console.log('   Delete Selected: visible=' + deleteVisibleAfter + ', display=' + deleteDisplayAfter);
            
            if (deleteVisibleAfter) {
                console.log('   [OK] Delete Selected APARECEU');
            } else {
                console.log('   [FALHA] Delete Selected deveria estar visivel!');
            }
        }
        
        if (tagsBtn) {
            const tagsVisibleAfter = await tagsBtn.isVisible();
            const tagsDisplayAfter = await tagsBtn.evaluate(el => window.getComputedStyle(el).display);
            console.log('   Manage Tags: visible=' + tagsVisibleAfter + ', display=' + tagsDisplayAfter);
            
            if (tagsVisibleAfter) {
                console.log('   [OK] Manage Tags APARECEU');
            } else {
                console.log('   [FALHA] Manage Tags deveria estar visivel!');
            }
        }
        
        // 7. Desmarcar checkbox
        console.log('\n7. Desmarcando o checkbox...');
        if (firstCheckbox) {
            await firstCheckbox.click();
            await page.waitForTimeout(500);
            console.log('   Checkbox desmarcado!\n');
        }
        
        // 8. Verificar se botoes desaparecem
        console.log('8. Verificando estado APOS desmarcar...');
        
        if (deleteBtn) {
            const deleteVisibleFinal = await deleteBtn.isVisible();
            console.log('   Delete Selected: visible=' + deleteVisibleFinal);
            
            if (!deleteVisibleFinal) {
                console.log('   [OK] Delete Selected DESAPARECEU');
            } else {
                console.log('   [FALHA] Delete Selected deveria estar escondido!');
            }
        }
        
        if (tagsBtn) {
            const tagsVisibleFinal = await tagsBtn.isVisible();
            console.log('   Manage Tags: visible=' + tagsVisibleFinal);
            
            if (!tagsVisibleFinal) {
                console.log('   [OK] Manage Tags DESAPARECEU');
            } else {
                console.log('   [FALHA] Manage Tags deveria estar escondido!');
            }
        }
        
        // Screenshot final
        await page.screenshot({ path: 'C:/Users/rrzil/Local Sites/eau-site/test-bulk-buttons-result.png', fullPage: false });
        console.log('\n=== Screenshot salvo em test-bulk-buttons-result.png ===');
        
        console.log('\n=== TESTE CONCLUIDO ===\n');
        
    } catch (error) {
        console.error('Erro durante o teste:', error.message);
        await page.screenshot({ path: 'C:/Users/rrzil/Local Sites/eau-site/test-error.png', fullPage: false });
    }
    
    await page.waitForTimeout(3000);
    await browser.close();
})();
