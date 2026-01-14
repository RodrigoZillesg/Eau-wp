const { chromium } = require('playwright');

(async () => {
    const browser = await chromium.launch({ headless: false });
    const page = await browser.newPage();
    page.setDefaultTimeout(30000);
    
    console.log('1. Fazendo login...');
    await page.goto('http://eau-site.local/login/');
    await page.waitForTimeout(2000);
    
    await page.fill('input[name="log"]', 'rrzillesg@gmail.com');
    await page.fill('input[name="pwd"]', 'Pl@ttyPl@tty');
    await page.click('button[type="submit"]');
    await page.waitForTimeout(4000);
    
    console.log('2. Navegando para Members...');
    await page.goto('http://eau-site.local/dashboard/manage-members/');
    await page.waitForTimeout(8000);
    
    // Verificar o que existe na pagina
    console.log('\n=== ESTRUTURA DA PAGINA ===');
    
    const pageInfo = await page.evaluate(() => {
        return {
            hasTable: !!document.querySelector('.eau-data-table'),
            hasTableBody: !!document.querySelector('.eau-data-table tbody'),
            rowCount: document.querySelectorAll('.eau-data-table tbody tr').length,
            checkboxCount: document.querySelectorAll('.eau-member-checkbox').length,
            bulkButtons: Array.from(document.querySelectorAll('[class*="bulk"]')).map(b => ({
                class: b.className,
                tag: b.tagName,
                text: b.textContent?.trim().substring(0, 50)
            })),
            allButtons: Array.from(document.querySelectorAll('button')).map(b => b.className).slice(0, 20),
            skeleton: !!document.querySelector('.eau-skeleton'),
            pageActions: document.querySelector('.eau-page-actions')?.innerHTML?.substring(0, 500)
        };
    });
    
    console.log('Tem tabela: ' + pageInfo.hasTable);
    console.log('Tem tbody: ' + pageInfo.hasTableBody);
    console.log('Linhas na tabela: ' + pageInfo.rowCount);
    console.log('Checkboxes: ' + pageInfo.checkboxCount);
    console.log('Skeleton visivel: ' + pageInfo.skeleton);
    console.log('Botoes bulk:', JSON.stringify(pageInfo.bulkButtons, null, 2));
    console.log('Page actions HTML:', pageInfo.pageActions);
    
    await page.screenshot({ path: 'C:/Users/rrzil/Local Sites/eau-site/members-page-debug.png', fullPage: true });
    console.log('\nScreenshot salvo: members-page-debug.png');
    
    await page.waitForTimeout(2000);
    await browser.close();
})();
