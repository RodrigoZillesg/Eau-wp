const { chromium } = require('playwright');

(async () => {
    console.log('Procurando pagina de Members Management...');
    
    const browser = await chromium.launch({ headless: false, slowMo: 200 });
    const context = await browser.newContext({ viewport: { width: 1920, height: 1080 } });
    const page = await context.newPage();
    
    // Login
    await page.goto('http://eau-site.local/login/');
    await page.waitForTimeout(2000);
    await page.fill('input[name="log"]', 'rrzillesg@gmail.com');
    await page.fill('input[name="pwd"]', 'Pl@ttyPl@tty');
    await page.click('button[type="submit"]');
    await page.waitForTimeout(4000);
    console.log('Login OK! URL:', page.url());
    
    // Ir para o dashboard e procurar links
    console.log('\nProcurando links no dashboard...');
    await page.goto('http://eau-site.local/dashboard/');
    await page.waitForTimeout(3000);
    
    // Listar todos os links
    const links = await page.evaluate(() => {
        return Array.from(document.querySelectorAll('a')).filter(a => 
            a.textContent.toLowerCase().includes('member') || 
            a.href.includes('member')
        ).map(a => ({
            text: a.textContent.trim().substring(0, 40),
            href: a.href
        }));
    });
    console.log('Links com member:', JSON.stringify(links, null, 2));
    
    // Tentar algumas URLs possiveis
    const urlsToTry = [
        'http://eau-site.local/members-management/',
        'http://eau-site.local/manage-members/',
        'http://eau-site.local/member-management/',
        'http://eau-site.local/dashboard/members/',
        'http://eau-site.local/dashboard/manage-members/'
    ];
    
    for (const url of urlsToTry) {
        console.log('\nTentando:', url);
        await page.goto(url);
        await page.waitForTimeout(3000);
        
        const hasTable = await page.evaluate(() => {
            const table = document.querySelector('.eau-data-table');
            const container = document.querySelector('.eau-members-management-container');
            return { 
                hasTable: table ? true : false, 
                hasContainer: container ? true : false,
                url: window.location.href
            };
        });
        console.log('Resultado:', hasTable);
        
        if (hasTable.hasTable || hasTable.hasContainer) {
            console.log('ENCONTRADO!');
            await page.screenshot({ path: 'screenshots/members-found.png' });
            break;
        }
    }
    
    await page.waitForTimeout(2000);
    await browser.close();
})();
