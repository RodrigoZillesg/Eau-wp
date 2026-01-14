const { chromium } = require('playwright');
const fs = require('fs');

async function testSidebarPage() {
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
        
        // Primeiro faz login normalmente
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
        
        // Agora acessa a pagina de teste
        console.log('2. Acessando pagina de teste do sidebar...');
        await page.goto('http://eau-site.local/test-sidebar-shortcode.php', { waitUntil: 'networkidle' });
        await page.waitForTimeout(3000);
        
        console.log('   URL: ' + page.url());
        await page.screenshot({ path: screenshotDir + '/20-sidebar-test-page.png', fullPage: true });
        
        // Verifica se o sidebar foi renderizado
        const sidebarMenu = await page.$('.eau-sidebar-menu');
        if (sidebarMenu) {
            console.log('   SUCESSO! Sidebar menu encontrado!');
            
            // Conta os itens do menu
            const menuItems = await page.$$('.eau-sidebar-menu a');
            console.log('   Total de itens no menu: ' + menuItems.length);
            
            // Lista os itens
            for (let i = 0; i < menuItems.length; i++) {
                const text = await menuItems[i].textContent();
                const href = await menuItems[i].getAttribute('href');
                console.log('   - ' + text.trim() + ' -> ' + href);
            }
        } else {
            console.log('   AVISO: Sidebar menu nao encontrado');
            
            // Verifica se ha algum erro na pagina
            const pageContent = await page.textContent('body');
            if (pageContent.includes('Error') || pageContent.includes('Fatal')) {
                console.log('   Possivel erro na pagina!');
            }
        }
        
        // Testa em diferentes viewports
        console.log('3. Testando responsividade...');
        
        // Tablet
        await page.setViewportSize({ width: 768, height: 1024 });
        await page.waitForTimeout(1000);
        await page.screenshot({ path: screenshotDir + '/21-sidebar-tablet.png', fullPage: true });
        console.log('   Screenshot tablet capturado');
        
        // Mobile
        await page.setViewportSize({ width: 375, height: 812 });
        await page.waitForTimeout(1000);
        await page.screenshot({ path: screenshotDir + '/22-sidebar-mobile.png', fullPage: true });
        console.log('   Screenshot mobile capturado');
        
        // Volta para desktop
        await page.setViewportSize({ width: 1920, height: 1080 });
        await page.waitForTimeout(1000);
        
        // Testa clique em um item do menu
        console.log('4. Testando navegacao...');
        const firstLink = await page.$('.eau-sidebar-menu a');
        if (firstLink) {
            const linkText = await firstLink.textContent();
            console.log('   Clicando em: ' + linkText.trim());
            
            await Promise.all([
                page.waitForNavigation({ waitUntil: 'networkidle', timeout: 30000 }).catch(() => {}),
                firstLink.click()
            ]);
            
            await page.waitForTimeout(2000);
            console.log('   Navegou para: ' + page.url());
            await page.screenshot({ path: screenshotDir + '/23-after-click.png', fullPage: true });
        }
        
        console.log('\n=== Teste concluido! ===');
        console.log('Screenshots salvos em: ' + screenshotDir);
        
    } catch (error) {
        console.error('Erro durante o teste:', error);
        await page.screenshot({ path: screenshotDir + '/error-sidebar.png', fullPage: true }).catch(() => {});
    } finally {
        await browser.close();
    }
}

testSidebarPage();
