const { chromium } = require('playwright');
const path = require('path');

(async () => {
    console.log('Iniciando teste de verificacao do header...');
    
    const browser = await chromium.launch({ 
        headless: false,
        args: ['--start-maximized']
    });
    
    const context = await browser.newContext({
        viewport: { width: 1920, height: 1080 }
    });
    
    const page = await context.newPage();
    
    try {
        // 1. Login
        console.log('1. Navegando para login...');
        await page.goto('http://eau-site.local/login/');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(2000);
        
        // Debug: listar campos de input
        const inputs = await page.$$eval('input', els => els.map(el => ({
            id: el.id,
            name: el.name,
            type: el.type,
            placeholder: el.placeholder
        })));
        console.log('   Campos de input encontrados:', JSON.stringify(inputs, null, 2));
        
        console.log('   Preenchendo credenciais...');
        
        // Tentar diferentes seletores
        const usernameField = await page.$('input[name="log"], input[name="username"], input#user_login, input[type="text"]:not([type="hidden"])');
        const passwordField = await page.$('input[name="pwd"], input[name="password"], input#user_pass, input[type="password"]');
        
        if (usernameField && passwordField) {
            await usernameField.fill('rrzillesg@gmail.com');
            await passwordField.fill('Pl@ttyPl@tty');
            
            // Procurar botao de submit
            const submitBtn = await page.$('button[type="submit"], input[type="submit"], .login-submit button');
            if (submitBtn) {
                await submitBtn.click();
            } else {
                await page.keyboard.press('Enter');
            }
            
            await page.waitForLoadState('networkidle');
            await page.waitForTimeout(3000);
        } else {
            console.log('   [ERRO] Campos de login nao encontrados');
        }
        
        // 2. Navegar para Dashboard
        console.log('2. Navegando para dashboard...');
        await page.goto('http://eau-site.local/dashboard/');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(3000);
        
        // 3. Verificar elementos do header customizado
        console.log('3. Verificando elementos do header customizado...');
        
        // Debug: listar todas as classes do body
        const bodyClasses = await page.$eval('body', el => el.className);
        console.log('   Body classes:', bodyClasses);
        
        // Verificar se existe header customizado - tentar varios seletores
        let customHeader = await page.$('.eau-custom-header');
        if (!customHeader) {
            customHeader = await page.$('.eau-header');
        }
        if (!customHeader) {
            customHeader = await page.$('[class*="eau"][class*="header"]');
        }
        
        if (customHeader) {
            console.log('   [OK] Header customizado encontrado');
        } else {
            console.log('   [AVISO] Header customizado NAO encontrado com seletores esperados');
            // Listar todos os headers
            const allHeaders = await page.$$eval('header, [class*="header"]', els => els.map(el => el.className));
            console.log('   Headers encontrados:', allHeaders);
        }
        
        // Verificar logo
        const logo = await page.$('header img, [class*="header"] img');
        if (logo) {
            console.log('   [OK] Logo encontrado');
            const logoSrc = await logo.getAttribute('src');
            console.log('       Logo src: ' + logoSrc);
        } else {
            console.log('   [AVISO] Logo NAO encontrado');
        }
        
        // 4. Tirar screenshot
        console.log('4. Capturando screenshot...');
        const screenshotPath = path.join(__dirname, 'header-verification-screenshot.png');
        await page.screenshot({ 
            path: screenshotPath,
            fullPage: false
        });
        console.log('   Screenshot salvo em: ' + screenshotPath);
        
        // 5. Procurar e clicar no hamburger menu
        console.log('5. Procurando menu hamburger...');
        
        const hamburgerBtn = await page.$('[data-lucide="menu"], .hamburger, [class*="hamburger"], button[class*="menu"], .menu-toggle');
        if (hamburgerBtn) {
            console.log('   Menu hamburger encontrado, clicando...');
            await hamburgerBtn.click();
            await page.waitForTimeout(1500);
            
            // Screenshot com sidebar
            const sidebarScreenshotPath = path.join(__dirname, 'header-with-sidebar-screenshot.png');
            await page.screenshot({ 
                path: sidebarScreenshotPath,
                fullPage: false
            });
            console.log('   Screenshot com sidebar salvo em: ' + sidebarScreenshotPath);
        } else {
            // Tentar encontrar qualquer botao no header
            const headerButtons = await page.$$eval('header button, [class*="header"] button', els => els.map(el => ({
                className: el.className,
                innerHTML: el.innerHTML.substring(0, 100)
            })));
            console.log('   Botoes no header:', JSON.stringify(headerButtons, null, 2));
        }
        
        console.log('\n=== TESTE CONCLUIDO ===');
        
    } catch (error) {
        console.error('ERRO durante teste:', error.message);
        
        const errorScreenshotPath = path.join(__dirname, 'header-error-screenshot.png');
        await page.screenshot({ path: errorScreenshotPath });
        console.log('Screenshot do erro salvo em: ' + errorScreenshotPath);
    }
    
    await page.waitForTimeout(3000);
    await browser.close();
    console.log('Browser fechado.');
})();
