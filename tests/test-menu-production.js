const { chromium } = require('playwright');

(async () => {
    const browser = await chromium.launch({ headless: false });
    const context = await browser.newContext({ viewport: { width: 1920, height: 1080 } });
    const page = await context.newPage();

    // Listen for console messages
    page.on('console', msg => {
        if (msg.type() === 'error' || msg.text().includes('EauSidebarMenu')) {
            console.log('   CONSOLE:', msg.type(), msg.text());
        }
    });

    try {
        // Login na produção
        console.log('1. Fazendo login em eaudevapp.platty.tech...');
        await page.goto('https://eaudevapp.platty.tech/login/');
        await page.waitForTimeout(3000);

        const emailInput = await page.locator('input[type="email"], input[type="text"], input[name="log"], input[name="email"], input[name="username"]').first();
        const passwordInput = await page.locator('input[type="password"]').first();

        await emailInput.fill('rrzillesg@gmail.com');
        await passwordInput.fill('Pl@ttyPl@tty');

        const submitBtn = await page.locator('button[type="submit"], input[type="submit"]').first();
        await submitBtn.click();

        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(3000);
        console.log('   Login OK - URL:', page.url());

        // Acessar a single page da instituição
        console.log('2. Acessando single page da instituição...');
        await page.goto('https://eaudevapp.platty.tech/institution/41420/');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(5000);
        console.log('   URL atual:', page.url());

        // Screenshot da página
        await page.screenshot({ path: 'screenshots/production-institution-page.png', fullPage: true });

        // Verificar título da página
        const title = await page.title();
        console.log('3. Título da página:', title);

        // Verificar versão do script carregado
        const scripts = await page.evaluate(() => {
            return Array.from(document.querySelectorAll('script[src]'))
                .map(s => s.src)
                .filter(src => src.includes('sidebar') || src.includes('menu'));
        });
        console.log('4. Scripts de menu carregados:', scripts);

        // Verificar se EauSidebarMenu existe e está inicializado
        const menuInfo = await page.evaluate(() => {
            return {
                exists: typeof window.EauSidebarMenu !== 'undefined',
                initialized: window.EauSidebarMenu ? window.EauSidebarMenu.initialized : null,
                hamburger: window.EauSidebarMenu ? !!window.EauSidebarMenu.hamburger : null,
                sidebar: window.EauSidebarMenu ? !!window.EauSidebarMenu.sidebar : null,
            };
        });
        console.log('5. EauSidebarMenu info:', menuInfo);

        // Verificar se o botão hamburger existe no DOM
        const hamburgerBtn = await page.locator('#eau-hamburger-btn');
        const hamburgerExists = await hamburgerBtn.count() > 0;
        console.log('6. Botão hamburger existe no DOM:', hamburgerExists);

        if (hamburgerExists) {
            const isVisible = await hamburgerBtn.isVisible();
            console.log('   Botão visível:', isVisible);

            const box = await hamburgerBtn.boundingBox();
            console.log('   Bounding box:', box);

            // Verificar se sidebar existe
            const sidebar = await page.locator('#eau-sidebar');
            const sidebarExists = await sidebar.count() > 0;
            console.log('   Sidebar existe:', sidebarExists);

            // Verificar classe do sidebar ANTES do clique
            if (sidebarExists) {
                const sidebarClassesBefore = await sidebar.evaluate(el => el.className);
                console.log('   Sidebar classes ANTES:', sidebarClassesBefore);
            }

            console.log('7. Clicando no botão hamburger...');

            // Tentar clicar via JavaScript
            const clickResult = await page.evaluate(() => {
                const btn = document.getElementById('eau-hamburger-btn');
                if (!btn) return { success: false, error: 'Button not found' };

                try {
                    // Simular click event
                    const event = new MouseEvent('click', {
                        bubbles: true,
                        cancelable: true,
                        view: window
                    });
                    btn.dispatchEvent(event);
                    return { success: true, error: null };
                } catch (e) {
                    return { success: false, error: e.message };
                }
            });
            console.log('   Click result:', clickResult);

            await page.waitForTimeout(1500);

            // Screenshot após clique
            await page.screenshot({ path: 'screenshots/production-menu-after-click.png', fullPage: true });

            // Verificar estado do sidebar APÓS clique
            if (sidebarExists) {
                const sidebarClassesAfter = await sidebar.evaluate(el => el.className);
                console.log('   Sidebar classes DEPOIS:', sidebarClassesAfter);

                const hasActiveClass = await sidebar.evaluate(el => el.classList.contains('active'));
                console.log('   Sidebar tem classe active:', hasActiveClass);

                if (!hasActiveClass) {
                    console.log('   PROBLEMA: Sidebar não abriu!');

                    // Tentar chamar toggle diretamente
                    console.log('8. Tentando chamar EauSidebarMenu.toggle() diretamente...');
                    const toggleResult = await page.evaluate(() => {
                        if (window.EauSidebarMenu && window.EauSidebarMenu.toggle) {
                            try {
                                window.EauSidebarMenu.toggle();
                                return { success: true };
                            } catch (e) {
                                return { success: false, error: e.message };
                            }
                        }
                        return { success: false, error: 'EauSidebarMenu.toggle not available' };
                    });
                    console.log('   Toggle result:', toggleResult);

                    await page.waitForTimeout(1000);

                    const sidebarClassesAfterToggle = await sidebar.evaluate(el => el.className);
                    console.log('   Sidebar classes após toggle:', sidebarClassesAfterToggle);
                }
            }
        } else {
            console.log('   PROBLEMA: Botão hamburger não encontrado!');

            // Verificar se há header do Eau System
            const eauHeader = await page.locator('.eau-app-header');
            const hasEauHeader = await eauHeader.count() > 0;
            console.log('   Tem eau-app-header:', hasEauHeader);

            if (hasEauHeader) {
                const headerHTML = await page.locator('.eau-app-header').innerHTML();
                console.log('   Header HTML (primeiros 1000 chars):', headerHTML.substring(0, 1000));
            }
        }

        // Verificar erros de JS na página
        console.log('9. Verificando erros JavaScript...');
        const jsErrors = await page.evaluate(() => {
            // Check for any errors stored
            return window.__jsErrors || [];
        });
        if (jsErrors.length > 0) {
            console.log('   JS Errors encontrados:', jsErrors);
        }

        await page.waitForTimeout(3000);

    } catch (error) {
        console.error('Erro:', error.message);
        await page.screenshot({ path: 'screenshots/production-menu-error.png', fullPage: true });
    } finally {
        await browser.close();
    }
})();
