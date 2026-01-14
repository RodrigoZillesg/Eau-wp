const { chromium } = require('playwright');

(async () => {
    const browser = await chromium.launch({ headless: false });
    const page = await browser.newPage();
    page.setDefaultTimeout(60000);
    
    // Capturar erros do console
    const consoleErrors = [];
    const consoleMessages = [];
    page.on('console', msg => {
        if (msg.type() === 'error') {
            consoleErrors.push(msg.text());
        }
        consoleMessages.push(msg.type() + ': ' + msg.text());
    });
    
    // Capturar erros de request
    const requestErrors = [];
    page.on('requestfailed', request => {
        requestErrors.push(request.url() + ' - ' + request.failure().errorText);
    });
    
    console.log('1. Fazendo login...');
    await page.goto('http://eau-site.local/login/');
    await page.waitForTimeout(2000);
    
    await page.fill('input[name="log"]', 'rrzillesg@gmail.com');
    await page.fill('input[name="pwd"]', 'Pl@ttyPl@tty');
    await page.click('button[type="submit"]');
    await page.waitForTimeout(4000);
    
    console.log('2. Navegando para Members...');
    await page.goto('http://eau-site.local/dashboard/manage-members/');
    
    // Esperar skeleton desaparecer ou timeout
    console.log('3. Aguardando tabela carregar (max 30s)...');
    try {
        await page.waitForSelector('.eau-skeleton', { state: 'hidden', timeout: 30000 });
        console.log('   Skeleton desapareceu!');
    } catch (e) {
        console.log('   TIMEOUT: Skeleton ainda visivel apos 30s');
    }
    
    await page.waitForTimeout(2000);
    
    // Verificar estrutura
    console.log('\n=== ESTRUTURA DA PAGINA ===');
    const pageInfo = await page.evaluate(() => {
        return {
            hasTable: !!document.querySelector('.eau-data-table'),
            rowCount: document.querySelectorAll('.eau-data-table tbody tr').length,
            skeleton: !!document.querySelector('.eau-skeleton:not([style*="display: none"])'),
            errorMessages: document.body.innerHTML.includes('error') ? 'Pode haver erro' : 'Sem erro visivel',
            pageContent: document.querySelector('.eau-members-container')?.innerHTML?.substring(0, 1000) || 'Container nao encontrado'
        };
    });
    
    console.log('Tem tabela: ' + pageInfo.hasTable);
    console.log('Linhas: ' + pageInfo.rowCount);
    console.log('Skeleton ainda visivel: ' + pageInfo.skeleton);
    
    console.log('\n=== ERROS DO CONSOLE ===');
    if (consoleErrors.length > 0) {
        consoleErrors.forEach(e => console.log('  ' + e));
    } else {
        console.log('  Nenhum erro no console');
    }
    
    console.log('\n=== ERROS DE REQUEST ===');
    if (requestErrors.length > 0) {
        requestErrors.forEach(e => console.log('  ' + e));
    } else {
        console.log('  Nenhum erro de request');
    }
    
    console.log('\n=== ULTIMAS MENSAGENS DO CONSOLE ===');
    consoleMessages.slice(-20).forEach(m => console.log('  ' + m));
    
    await page.screenshot({ path: 'C:/Users/rrzil/Local Sites/eau-site/members-debug-final.png', fullPage: true });
    
    await page.waitForTimeout(2000);
    await browser.close();
})();
