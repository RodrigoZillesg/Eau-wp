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

        // Ir para manage-institutions
        console.log('2. Indo para manage-institutions...');
        await page.goto('http://eau-site.local/dashboard/manage-institutions/');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(5000);
        
        // Screenshot da página de institutions
        await page.screenshot({ path: 'screenshots/institutions-page.png', fullPage: true });
        
        // Verificar se há linhas na tabela
        const rows = await page.locator('.eau-data-table tbody tr');
        const rowCount = await rows.count();
        console.log('3. Número de linhas na tabela:', rowCount);
        
        if (rowCount > 0) {
            // Pegar o link da primeira instituição
            const firstLink = await page.locator('.eau-data-table tbody tr a').first();
            const href = await firstLink.getAttribute('href');
            console.log('   Link da primeira instituição:', href);
            
            if (href) {
                console.log('4. Acessando instituição...');
                await page.goto(href);
                await page.waitForLoadState('networkidle');
                await page.waitForTimeout(3000);
                console.log('   URL atual:', page.url());
                
                // Screenshot da página da instituição
                await page.screenshot({ path: 'screenshots/institution-single.png', fullPage: true });
            }
        } else {
            // Verificar se há skeleton loading
            const skeleton = await page.locator('.eau-skeleton');
            const skeletonCount = await skeleton.count();
            console.log('   Skeletons visíveis:', skeletonCount);
            
            // Aguardar mais tempo
            await page.waitForTimeout(5000);
            
            // Tentar novamente
            const rowsAfterWait = await page.locator('.eau-data-table tbody tr');
            const rowCountAfterWait = await rowsAfterWait.count();
            console.log('   Linhas após aguardar:', rowCountAfterWait);
        }

        // Verificar se o botão hamburger existe
        const hamburgerBtn = await page.locator('#eau-hamburger-btn');
        const hamburgerExists = await hamburgerBtn.count() > 0;
        console.log('5. Botão hamburger existe:', hamburgerExists);

        if (hamburgerExists) {
            const isVisible = await hamburgerBtn.isVisible();
            console.log('   Botão visível:', isVisible);

            console.log('6. Clicando no botão hamburger...');
            await hamburgerBtn.click({ force: true });
            await page.waitForTimeout(1500);

            await page.screenshot({ path: 'screenshots/menu-test-after-click.png', fullPage: true });

            const sidebar = await page.locator('#eau-sidebar');
            const sidebarHasActiveClass = await sidebar.evaluate(el => el.classList.contains('active'));
            console.log('   Sidebar tem classe active:', sidebarHasActiveClass);
        }

        // Verificar scripts
        const sidebarMenuLoaded = await page.evaluate(() => typeof window.EauSidebarMenu !== 'undefined');
        console.log('7. EauSidebarMenu carregado:', sidebarMenuLoaded);

        if (sidebarMenuLoaded) {
            const isInitialized = await page.evaluate(() => window.EauSidebarMenu.initialized);
            console.log('   EauSidebarMenu.initialized:', isInitialized);
        }

        await page.waitForTimeout(3000);

    } catch (error) {
        console.error('Erro:', error.message);
        await page.screenshot({ path: 'screenshots/menu-test-error.png', fullPage: true });
    } finally {
        await browser.close();
    }
})();
