const { chromium } = require('playwright');
const fs = require('fs');

async function testSidebarFinal() {
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
        
        // Acessa pagina de teste do sidebar
        console.log('2. Acessando pagina de teste...');
        await page.goto('http://eau-site.local/test-sidebar-shortcode.php', { waitUntil: 'networkidle' });
        await page.waitForTimeout(3000);
        
        // Verifica o wrapper correto
        const sidebarWrapper = await page.$('.eau-sidebar-menu-wrapper');
        if (sidebarWrapper) {
            console.log('   SUCESSO! Sidebar wrapper encontrado!');
        } else {
            console.log('   AVISO: Sidebar wrapper nao encontrado');
        }
        
        await page.screenshot({ path: screenshotDir + '/40-sidebar-initial.png', fullPage: true });
        
        // Encontra e clica no botao hamburger para abrir o sidebar
        console.log('3. Abrindo sidebar...');
        const hamburgerBtn = await page.$('.eau-hamburger-btn, #eau-hamburger-btn');
        if (hamburgerBtn) {
            await hamburgerBtn.click();
            await page.waitForTimeout(500);
            console.log('   Clicou no hamburger!');
            await page.screenshot({ path: screenshotDir + '/41-sidebar-open.png', fullPage: true });
            
            // Verifica se sidebar esta visivel
            const sidebar = await page.$('.eau-sidebar');
            if (sidebar) {
                const isVisible = await sidebar.isVisible();
                console.log('   Sidebar visivel: ' + isVisible);
            }
            
            // Lista os itens do menu
            const menuItems = await page.$$('.eau-sidebar a.eau-sidebar-item');
            console.log('   Total de itens no menu: ' + menuItems.length);
            
            // Mostra os primeiros 5 itens
            for (let i = 0; i < Math.min(5, menuItems.length); i++) {
                const text = await menuItems[i].textContent();
                console.log('   - ' + text.trim());
            }
            
            // Fecha o sidebar
            console.log('4. Fechando sidebar...');
            const closeBtn = await page.$('.eau-sidebar-close, #eau-sidebar-close');
            if (closeBtn) {
                await closeBtn.click();
                await page.waitForTimeout(500);
                console.log('   Fechou o sidebar!');
            }
        } else {
            console.log('   AVISO: Botao hamburger nao encontrado');
        }
        
        // Testa responsividade
        console.log('5. Testando responsividade...');
        
        // Abre sidebar novamente antes de mudar viewport
        const hamburger2 = await page.$('.eau-hamburger-btn');
        if (hamburger2) {
            await hamburger2.click();
            await page.waitForTimeout(500);
        }
        
        // Tablet
        await page.setViewportSize({ width: 768, height: 1024 });
        await page.waitForTimeout(1000);
        await page.screenshot({ path: screenshotDir + '/42-sidebar-tablet.png', fullPage: true });
        console.log('   Screenshot tablet capturado');
        
        // Mobile
        await page.setViewportSize({ width: 375, height: 812 });
        await page.waitForTimeout(1000);
        await page.screenshot({ path: screenshotDir + '/43-sidebar-mobile.png', fullPage: true });
        console.log('   Screenshot mobile capturado');
        
        console.log('\n=== Teste concluido com sucesso! ===');
        console.log('Screenshots salvos em: ' + screenshotDir);
        
    } catch (error) {
        console.error('Erro durante o teste:', error);
        await page.screenshot({ path: screenshotDir + '/error-final.png', fullPage: true }).catch(() => {});
    } finally {
        await browser.close();
    }
}

testSidebarFinal();
