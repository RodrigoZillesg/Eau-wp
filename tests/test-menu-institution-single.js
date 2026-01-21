const { chromium } = require('playwright');

(async () => {
    const browser = await chromium.launch({ headless: false });
    const context = await browser.newContext({ viewport: { width: 1920, height: 1080 } });
    const page = await context.newPage();

    // Listen for console messages
    page.on('console', msg => {
        const text = msg.text();
        if (msg.type() === 'error' || text.includes('EauSidebarMenu') || text.includes('sidebar')) {
            console.log('   CONSOLE:', msg.type(), text);
        }
    });

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

        // Buscar uma instituição existente
        console.log('2. Buscando instituição existente...');
        await page.goto('http://eau-site.local/dashboard/manage-institutions/');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(5000);

        // Pegar o primeiro link de instituição
        const firstInstitutionLink = await page.locator('.eau-data-table tbody tr a').first();
        const href = await firstInstitutionLink.getAttribute('href').catch(() => null);

        let institutionUrl = href;
        if (!href) {
            // Se não encontrou, tentar uma URL direta
            institutionUrl = 'http://eau-site.local/institution/41420/';
            console.log('   Não encontrou link, usando URL direta:', institutionUrl);
        } else {
            console.log('   Link encontrado:', href);
        }

        // Acessar a single page da instituição
        console.log('3. Acessando single page da instituição...');
        await page.goto(institutionUrl);
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(5000);
        console.log('   URL atual:', page.url());

        // Screenshot da página
        await page.screenshot({ path: 'screenshots/institution-single-local.png', fullPage: true });

        // Verificar título da página
        const title = await page.title();
        console.log('4. Título da página:', title);

        // Verificar elementos duplicados
        const duplicateInfo = await page.evaluate(() => {
            const hamburgers = document.querySelectorAll('#eau-hamburger-btn');
            const sidebars = document.querySelectorAll('#eau-sidebar');
            const headers = document.querySelectorAll('.eau-app-header');

            return {
                hamburgerCount: hamburgers.length,
                sidebarCount: sidebars.length,
                headerCount: headers.length,
            };
        });
        console.log('5. Elementos encontrados:', duplicateInfo);

        // Verificar se EauSidebarMenu existe e está inicializado
        const menuInfo = await page.evaluate(() => {
            if (!window.EauSidebarMenu) return { error: 'EauSidebarMenu not found' };
            return {
                initialized: window.EauSidebarMenu.initialized,
                isOpen: window.EauSidebarMenu.isOpen,
            };
        });
        console.log('6. EauSidebarMenu info:', menuInfo);

        // Verificar se o botão hamburger existe
        const hamburgerBtns = await page.locator('#eau-hamburger-btn');
        const hamburgerCount = await hamburgerBtns.count();
        console.log('7. Quantidade de botões hamburger:', hamburgerCount);

        if (hamburgerCount > 0) {
            // Testar clique no primeiro botão visível
            const hamburgerBtn = hamburgerBtns.first();
            const isVisible = await hamburgerBtn.isVisible();
            console.log('   Primeiro botão visível:', isVisible);

            console.log('8. Clicando no botão hamburger...');
            await hamburgerBtn.click({ force: true });
            await page.waitForTimeout(1500);

            await page.screenshot({ path: 'screenshots/institution-single-after-click.png', fullPage: true });

            // Verificar se sidebar abriu
            const sidebarState = await page.evaluate(() => {
                const sidebars = document.querySelectorAll('#eau-sidebar');
                return Array.from(sidebars).map((s, i) => ({
                    index: i,
                    hasActive: s.classList.contains('active'),
                }));
            });
            console.log('   Sidebars após clique:', sidebarState);

            const anyOpen = sidebarState.some(s => s.hasActive);
            if (anyOpen) {
                console.log('   ✅ SUCESSO: Menu abriu!');
            } else {
                console.log('   ❌ FALHA: Menu não abriu');
            }
        }

        await page.waitForTimeout(3000);

    } catch (error) {
        console.error('Erro:', error.message);
        await page.screenshot({ path: 'screenshots/institution-single-error.png', fullPage: true });
    } finally {
        await browser.close();
    }
})();
