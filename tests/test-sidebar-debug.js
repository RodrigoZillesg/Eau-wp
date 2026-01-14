const { chromium } = require('playwright');
const fs = require('fs');

async function testSidebarDebug() {
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
        console.log('   Logado! URL: ' + page.url());
        
        // Acessa pagina de debug
        console.log('2. Acessando pagina de debug...');
        await page.goto('http://eau-site.local/test-sidebar-debug.php', { waitUntil: 'networkidle' });
        await page.waitForTimeout(3000);
        
        // Captura o texto da pagina
        const pageText = await page.textContent('body');
        console.log('\n--- Conteudo da pagina ---');
        console.log(pageText);
        console.log('--- Fim do conteudo ---\n');
        
        await page.screenshot({ path: screenshotDir + '/30-sidebar-debug.png', fullPage: true });
        
        console.log('=== Teste concluido! ===');
        
    } catch (error) {
        console.error('Erro durante o teste:', error);
    } finally {
        await browser.close();
    }
}

testSidebarDebug();
