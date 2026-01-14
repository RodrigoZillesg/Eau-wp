const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');

(async () => {
    const browser = await chromium.launch({ headless: false });
    const context = await browser.newContext({
        viewport: { width: 1920, height: 1080 }
    });
    const page = await context.newPage();
    
    const screenshotDir = path.join(__dirname, 'button-test-screenshots');
    
    if (!fs.existsSync(screenshotDir)) {
        fs.mkdirSync(screenshotDir, { recursive: true });
    }
    
    console.log('1. Fazendo login...');
    await page.goto('http://eau-site.local/login/');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);
    
    await page.screenshot({ path: path.join(screenshotDir, 'debug-login.png'), fullPage: true });
    
    const fields = await page.$$('input');
    console.log('   Campos input encontrados: ' + fields.length);
    
    for (let i = 0; i < fields.length; i++) {
        const type = await fields[i].getAttribute('type');
        const name = await fields[i].getAttribute('name');
        const id = await fields[i].getAttribute('id');
        console.log('   Input ' + i + ': type=' + type + ', name=' + name + ', id=' + id);
    }
    
    try {
        await page.fill('input[name="log"]', 'rrzillesg@gmail.com');
        await page.fill('input[name="pwd"]', 'Pl@ttyPl@tty');
    } catch (e) {
        try {
            await page.fill('#user_login', 'rrzillesg@gmail.com');
            await page.fill('#user_pass', 'Pl@ttyPl@tty');
        } catch (e2) {
            console.log('   Tentando seletores alternativos...');
            const textInputs = await page.$$('input[type="text"], input[type="email"]');
            const passInputs = await page.$$('input[type="password"]');
            if (textInputs.length > 0 && passInputs.length > 0) {
                await textInputs[0].fill('rrzillesg@gmail.com');
                await passInputs[0].fill('Pl@ttyPl@tty');
            }
        }
    }
    
    await page.click('button[type="submit"], input[type="submit"], #wp-submit');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(3000);
    
    console.log('2. Navegando para a pagina de membros...');
    await page.goto('http://eau-site.local/dashboard/manage-members/');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(5000);
    
    console.log('3. Esperando tabela carregar...');
    await page.waitForTimeout(3000);
    
    console.log('4. Verificando estado inicial dos botoes...');
    
    const allButtons = await page.$$('button, .eau-btn');
    console.log('   Total de botoes encontrados: ' + allButtons.length);
    
    let deleteBtn = null;
    let tagsBtn = null;
    
    for (const btn of allButtons) {
        const text = await btn.textContent();
        if (text && text.includes('Delete Selected')) {
            deleteBtn = btn;
        }
        if (text && text.includes('Manage Tags')) {
            tagsBtn = btn;
        }
    }
    
    console.log('');
    console.log('=== ESTADO INICIAL (antes de selecionar) ===');
    
    if (deleteBtn) {
        const isVisible = await deleteBtn.isVisible();
        const display = await deleteBtn.evaluate(el => window.getComputedStyle(el).display);
        console.log('Delete Selected: existe=true, visivel=' + isVisible + ', display=' + display);
    } else {
        console.log('Delete Selected: NAO ENCONTRADO no DOM');
    }
    
    if (tagsBtn) {
        const isVisible = await tagsBtn.isVisible();
        const display = await tagsBtn.evaluate(el => window.getComputedStyle(el).display);
        console.log('Manage Tags: existe=true, visivel=' + isVisible + ', display=' + display);
    } else {
        console.log('Manage Tags: NAO ENCONTRADO no DOM');
    }
    
    await page.screenshot({ 
        path: path.join(screenshotDir, '01-estado-inicial.png'),
        fullPage: false 
    });
    console.log('');
    console.log('5. Screenshot do estado inicial salvo');
    
    console.log('');
    console.log('6. Selecionando primeiro checkbox...');
    const checkboxes = await page.$$('table tbody input[type="checkbox"], .eau-data-table tbody input[type="checkbox"]');
    console.log('   Checkboxes encontrados: ' + checkboxes.length);
    
    if (checkboxes.length > 0) {
        await checkboxes[0].click();
        await page.waitForTimeout(1500);
        
        console.log('');
        console.log('=== ESTADO APOS SELECIONAR UM ITEM ===');
        
        if (deleteBtn) {
            const isVisible = await deleteBtn.isVisible();
            const display = await deleteBtn.evaluate(el => window.getComputedStyle(el).display);
            console.log('Delete Selected: existe=true, visivel=' + isVisible + ', display=' + display);
        }
        
        if (tagsBtn) {
            const isVisible = await tagsBtn.isVisible();
            const display = await tagsBtn.evaluate(el => window.getComputedStyle(el).display);
            console.log('Manage Tags: existe=true, visivel=' + isVisible + ', display=' + display);
        }
        
        await page.screenshot({ 
            path: path.join(screenshotDir, '02-apos-selecao.png'),
            fullPage: false 
        });
        console.log('');
        console.log('7. Screenshot apos selecao salvo');
        
        console.log('');
        console.log('8. Desmarcando checkbox...');
        await checkboxes[0].click();
        await page.waitForTimeout(1500);
        
        console.log('');
        console.log('=== ESTADO APOS DESMARCAR ===');
        
        if (deleteBtn) {
            const isVisible = await deleteBtn.isVisible();
            const display = await deleteBtn.evaluate(el => window.getComputedStyle(el).display);
            console.log('Delete Selected: existe=true, visivel=' + isVisible + ', display=' + display);
        }
        
        if (tagsBtn) {
            const isVisible = await tagsBtn.isVisible();
            const display = await tagsBtn.evaluate(el => window.getComputedStyle(el).display);
            console.log('Manage Tags: existe=true, visivel=' + isVisible + ', display=' + display);
        }
        
        await page.screenshot({ 
            path: path.join(screenshotDir, '03-apos-desmarcar.png'),
            fullPage: false 
        });
        console.log('');
        console.log('9. Screenshot apos desmarcar salvo');
        
    } else {
        console.log('ERRO: Nao encontrou checkbox na tabela');
        await page.screenshot({ path: path.join(screenshotDir, 'debug-no-checkbox.png'), fullPage: true });
    }
    
    console.log('');
    console.log('=== TESTE CONCLUIDO ===');
    console.log('Screenshots salvos em: ' + screenshotDir);
    
    await page.waitForTimeout(2000);
    await browser.close();
})();
