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

        // Investigar elementos duplicados
        console.log('\n=== INVESTIGAÇÃO DE ELEMENTOS DUPLICADOS ===\n');

        const duplicateInfo = await page.evaluate(() => {
            const hamburgers = document.querySelectorAll('#eau-hamburger-btn');
            const sidebars = document.querySelectorAll('#eau-sidebar');
            const overlays = document.querySelectorAll('#eau-sidebar-overlay');
            const headers = document.querySelectorAll('.eau-app-header');

            const getElementInfo = (el, index) => {
                const rect = el.getBoundingClientRect();
                const styles = window.getComputedStyle(el);
                return {
                    index: index,
                    visible: rect.width > 0 && rect.height > 0 && styles.display !== 'none' && styles.visibility !== 'hidden',
                    display: styles.display,
                    visibility: styles.visibility,
                    position: { top: rect.top, left: rect.left, width: rect.width, height: rect.height },
                    parent: el.parentElement ? el.parentElement.className : null,
                    inHeader: !!el.closest('.eau-app-header'),
                };
            };

            return {
                hamburgerCount: hamburgers.length,
                hamburgers: Array.from(hamburgers).map((el, i) => getElementInfo(el, i)),
                sidebarCount: sidebars.length,
                sidebars: Array.from(sidebars).map((el, i) => getElementInfo(el, i)),
                overlayCount: overlays.length,
                headerCount: headers.length,
                headers: Array.from(headers).map((el, i) => ({
                    index: i,
                    visible: el.getBoundingClientRect().height > 0,
                    position: el.getBoundingClientRect(),
                    parent: el.parentElement ? el.parentElement.className : null,
                })),
            };
        });

        console.log('Hamburger buttons encontrados:', duplicateInfo.hamburgerCount);
        duplicateInfo.hamburgers.forEach((h, i) => {
            console.log(`   [${i}] visible: ${h.visible}, display: ${h.display}, visibility: ${h.visibility}`);
            console.log(`       position: top=${h.position.top}, left=${h.position.left}, width=${h.position.width}, height=${h.position.height}`);
            console.log(`       parent class: ${h.parent}`);
            console.log(`       inside .eau-app-header: ${h.inHeader}`);
        });

        console.log('\nSidebars encontrados:', duplicateInfo.sidebarCount);
        duplicateInfo.sidebars.forEach((s, i) => {
            console.log(`   [${i}] visible: ${s.visible}, display: ${s.display}`);
        });

        console.log('\nOverlays encontrados:', duplicateInfo.overlayCount);
        console.log('\nHeaders (.eau-app-header) encontrados:', duplicateInfo.headerCount);
        duplicateInfo.headers.forEach((h, i) => {
            console.log(`   [${i}] visible: ${h.visible}, top: ${h.position.top}, height: ${h.position.height}`);
            console.log(`       parent class: ${h.parent}`);
        });

        // Screenshot da página
        await page.screenshot({ path: 'screenshots/production-duplicate-investigation.png', fullPage: true });

        // Verificar qual elemento o JavaScript capturou
        console.log('\n=== ESTADO DO EauSidebarMenu ===\n');
        const menuState = await page.evaluate(() => {
            if (!window.EauSidebarMenu) return { error: 'EauSidebarMenu not found' };

            const menu = window.EauSidebarMenu;
            return {
                initialized: menu.initialized,
                isOpen: menu.isOpen,
                hamburgerElement: menu.hamburger ? {
                    exists: true,
                    id: menu.hamburger.id,
                    visible: menu.hamburger.getBoundingClientRect().width > 0,
                    position: menu.hamburger.getBoundingClientRect(),
                } : { exists: false },
                sidebarElement: menu.sidebar ? {
                    exists: true,
                    id: menu.sidebar.id,
                    hasActiveClass: menu.sidebar.classList.contains('active'),
                } : { exists: false },
            };
        });
        console.log('Menu state:', JSON.stringify(menuState, null, 2));

        // Testar clique no botão visível
        console.log('\n=== TESTE DE CLIQUE ===\n');

        // Encontrar o botão visível
        const visibleButtonIndex = duplicateInfo.hamburgers.findIndex(h => h.visible);
        console.log('Índice do botão visível:', visibleButtonIndex);

        if (visibleButtonIndex >= 0) {
            console.log('Clicando no botão visível (índice ' + visibleButtonIndex + ')...');

            // Clicar usando JavaScript no botão visível
            const clickResult = await page.evaluate((index) => {
                const buttons = document.querySelectorAll('#eau-hamburger-btn');
                const btn = buttons[index];
                if (!btn) return { success: false, error: 'Button not found at index ' + index };

                try {
                    btn.click();
                    return { success: true };
                } catch (e) {
                    return { success: false, error: e.message };
                }
            }, visibleButtonIndex);
            console.log('Click result:', clickResult);

            await page.waitForTimeout(1500);

            // Verificar se abriu
            const afterClick = await page.evaluate(() => {
                const sidebars = document.querySelectorAll('#eau-sidebar');
                return Array.from(sidebars).map((s, i) => ({
                    index: i,
                    hasActive: s.classList.contains('active'),
                    classes: s.className,
                }));
            });
            console.log('Sidebars após clique:', afterClick);

            await page.screenshot({ path: 'screenshots/production-after-click-visible.png', fullPage: true });
        }

        await page.waitForTimeout(3000);

    } catch (error) {
        console.error('Erro:', error.message);
        await page.screenshot({ path: 'screenshots/production-menu-error-v2.png', fullPage: true });
    } finally {
        await browser.close();
    }
})();
