const { chromium } = require('playwright');
const fs = require('fs');

async function testSidebarNavigation() {
    const browser = await chromium.launch({ headless: false });
    const context = await browser.newContext({
        viewport: { width: 1920, height: 1080 }
    });
    const page = await context.newPage();
    
    const screenshotDir = './presentation-screenshots/sidebar-test';
    
    try {
        if (!fs.existsSync(screenshotDir)) {
            fs.mkdirSync(screenshotDir, { recursive: true });
        }
        
        // Primeiro faz login
        console.log('1. Fazendo login...');
        await page.goto('http://eau-site.local/login/', { waitUntil: 'networkidle' });
        await page.waitForTimeout(2000);
        
        await page.fill('input[name="log"]', 'rrzillesg@gmail.com');
        await page.fill('input[name="pwd"]', 'Pl@ttyPl@tty');
        
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle', timeout: 60000 }).catch(() => {}),
            page.click('button[type="submit"]')
        ]);
        
        await page.waitForTimeout(3000);
        console.log('   Logado!');
        
        // Acessa pagina de teste
        console.log('2. Acessando pagina de teste...');
        await page.goto('http://eau-site.local/test-sidebar-shortcode.php', { waitUntil: 'networkidle' });
        await page.waitForTimeout(3000);
        
        // Abre o sidebar
        console.log('3. Abrindo sidebar...');
        await page.click('.eau-hamburger-btn');
        await page.waitForTimeout(500);
        
        // Lista todos os links do menu
        const menuLinks = await page.$$('.eau-sidebar-link');
        console.log('\n   === Itens do Menu (' + menuLinks.length + ' links) ===');
        
        for (let i = 0; i < menuLinks.length; i++) {
            const text = await menuLinks[i].textContent();
            const href = await menuLinks[i].getAttribute('href');
            console.log('   ' + (i+1) + '. ' + text.trim().replace(/\s+/g, ' ') + ' -> ' + href);
        }
        
        // Navega para "Manage Members"
        console.log('\n4. Navegando para Manage Members...');
        const membersLink = await page.$('a.eau-sidebar-link[href*="manage-members"]');
        if (membersLink) {
            await Promise.all([
                page.waitForNavigation({ waitUntil: 'networkidle', timeout: 60000 }).catch(() => {}),
                membersLink.click()
            ]);
            
            await page.waitForTimeout(3000);
            console.log('   URL: ' + page.url());
            await page.screenshot({ path: screenshotDir + '/50-navigated-members.png', fullPage: true });
            console.log('   Screenshot capturado!');
        }
        
        console.log('\n=== Teste concluido! ===');
        
    } catch (error) {
        console.error('Erro durante o teste:', error);
    } finally {
        await browser.close();
    }
}

testSidebarNavigation();
