const { chromium } = require('playwright');

(async () => {
    const browser = await chromium.launch({ headless: false });
    const page = await browser.newPage();
    page.setDefaultTimeout(60000);
    
    console.log('1. Fazendo login...');
    await page.goto('http://eau-site.local/login/');
    await page.waitForTimeout(2000);
    
    await page.fill('input[name="log"]', 'rrzillesg@gmail.com');
    await page.fill('input[name="pwd"]', 'Pl@ttyPl@tty');
    await page.click('button[type="submit"]');
    await page.waitForTimeout(4000);
    
    console.log('2. Navegando para Members...');
    await page.goto('http://eau-site.local/dashboard/manage-members/');
    await page.waitForTimeout(15000);
    
    // Pegar o HTML de dentro do container
    const containerHTML = await page.evaluate(() => {
        const container = document.querySelector('.eau-members-container');
        if (container) {
            return container.innerHTML;
        }
        // Se nao encontrar, procurar outros elementos
        const mainContent = document.querySelector('main') || document.querySelector('.entry-content') || document.querySelector('.site-main');
        if (mainContent) {
            return 'Main content: ' + mainContent.innerHTML.substring(0, 3000);
        }
        return 'Nenhum container encontrado. Body classes: ' + document.body.className;
    });
    
    console.log('\n=== HTML DO CONTAINER ===');
    console.log(containerHTML.substring(0, 5000));
    
    // Verificar se ha alguma tabela na pagina
    const allTables = await page.evaluate(() => {
        const tables = document.querySelectorAll('table');
        return Array.from(tables).map(t => ({
            class: t.className,
            id: t.id,
            rows: t.querySelectorAll('tr').length
        }));
    });
    
    console.log('\n=== TABELAS NA PAGINA ===');
    console.log(JSON.stringify(allTables, null, 2));
    
    await page.screenshot({ path: 'C:/Users/rrzil/Local Sites/eau-site/members-html-debug.png', fullPage: true });
    
    await page.waitForTimeout(2000);
    await browser.close();
})();
