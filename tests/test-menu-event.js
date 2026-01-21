const { chromium } = require('playwright');

(async () => {
    const browser = await chromium.launch({ headless: false });
    const context = await browser.newContext({ viewport: { width: 1920, height: 1080 } });
    const page = await context.newPage();

    try {
        // Login
        console.log('1. Fazendo login...');
        await page.goto('http://eau-site.local/login/');
        await page.waitForTimeout(2000);
        
        const emailInput = await page.locator('input[type="email"], input[type="text"], input[name="log"], input[name="email"], input[name="username"]').first();
        const passwordInput = await page.locator('input[type="password"]').first();
        
        await emailInput.fill('rrzillesg@gmail.com');
        await passwordInput.fill('Pl@ttyPl@tty');
        
        const submitBtn = await page.locator('button[type="submit"], input[type="submit"]').first();
        await submitBtn.click();
        
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(3000);
        console.log('   Login OK - URL:', page.url());

        // Acessar a single page de um evento diretamente (asdfasdf)
        console.log('2. Acessando single page do evento...');
        await page.goto('http://eau-site.local/events/asdfasdf/');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(3000);
        console.log('   URL atual:', page.url());
        
        // Screenshot da single do evento
        await page.screenshot({ path: 'screenshots/event-single-page.png', fullPage: true });
        
        // Verificar título da página
        const title = await page.title();
        console.log('3. Título da página:', title);
        
        // Verificar se estamos na single do evento (não na archive)
        const isEventSingle = await page.locator('.eau-event-single, .single-eau_event').count() > 0;
        console.log('4. É single page do evento:', isEventSingle);
        
        // Verificar se o botão hamburger existe
        const hamburgerBtn = await page.locator('#eau-hamburger-btn');
        const hamburgerExists = await hamburgerBtn.count() > 0;
        console.log('5. Botão hamburger existe:', hamburgerExists);

        if (hamburgerExists) {
            const isVisible = await hamburgerBtn.isVisible();
            console.log('   Botão visível:', isVisible);
            
            // Verificar se há algo bloqueando o botão
            const box = await hamburgerBtn.boundingBox();
            console.log('   Bounding box:', box);

            console.log('6. Clicando no botão hamburger...');
            await hamburgerBtn.click({ force: true });
            await page.waitForTimeout(1500);

            await page.screenshot({ path: 'screenshots/event-single-menu-after-click.png', fullPage: true });

            const sidebar = await page.locator('#eau-sidebar');
            const sidebarExists = await sidebar.count() > 0;
            console.log('   Sidebar existe:', sidebarExists);
            
            if (sidebarExists) {
                const sidebarHasActiveClass = await sidebar.evaluate(el => el.classList.contains('active'));
                console.log('   Sidebar tem classe active:', sidebarHasActiveClass);
                
                if (!sidebarHasActiveClass) {
                    console.log('   PROBLEMA: Sidebar não abriu após clicar!');
                }
            }
        } else {
            console.log('   PROBLEMA: Botão hamburger não encontrado na single do evento!');
            
            // Verificar se há header do Eau System
            const eauHeader = await page.locator('.eau-app-header');
            const hasEauHeader = await eauHeader.count() > 0;
            console.log('   Tem eau-app-header:', hasEauHeader);
            
            // Verificar conteúdo do header
            const headerContent = await page.locator('.eau-app-header').innerHTML().catch(() => 'não encontrado');
            console.log('   Conteúdo do header (primeiros 500 chars):', headerContent.substring(0, 500));
        }

        // Verificar scripts
        const sidebarMenuLoaded = await page.evaluate(() => typeof window.EauSidebarMenu !== 'undefined');
        console.log('7. EauSidebarMenu carregado:', sidebarMenuLoaded);

        if (sidebarMenuLoaded) {
            const isInitialized = await page.evaluate(() => window.EauSidebarMenu.initialized);
            console.log('   EauSidebarMenu.initialized:', isInitialized);
            
            // Verificar elementos cacheados
            const hamburgerCached = await page.evaluate(() => window.EauSidebarMenu.$hamburger ? window.EauSidebarMenu.$hamburger.length : 0);
            console.log('   $hamburger.length:', hamburgerCached);
        }

        // Verificar se há sidebar-menu script carregado
        const scripts = await page.evaluate(() => {
            return Array.from(document.querySelectorAll('script[src]'))
                .map(s => s.src)
                .filter(src => src.includes('sidebar') || src.includes('menu'));
        });
        console.log('8. Scripts de menu carregados:', scripts);

        await page.waitForTimeout(3000);

    } catch (error) {
        console.error('Erro:', error.message);
        await page.screenshot({ path: 'screenshots/event-menu-error.png', fullPage: true });
    } finally {
        await browser.close();
    }
})();
