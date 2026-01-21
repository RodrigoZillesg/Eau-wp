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

        // Acessar a single page da instituição diretamente
        console.log('2. Acessando single page da instituição...');
        await page.goto('https://eaudevapp.platty.tech/institution/41420/');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(5000);
        console.log('   URL atual:', page.url());

        // Screenshot da página
        await page.screenshot({ path: 'screenshots/production-institution-single.png', fullPage: true });

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
                retryCount: window.EauSidebarMenu.retryCount,
            };
        });
        console.log('6. EauSidebarMenu info:', menuInfo);

        // Simular o novo código de Event Delegation injetando diretamente
        // (já que a produção ainda tem a versão antiga)
        console.log('\n7. INJETANDO NOVO CÓDIGO DE EVENT DELEGATION...');

        await page.evaluate(() => {
            // Remover listeners antigos se existirem
            // Não é possível remover listeners anônimos, mas podemos adicionar novo

            // Criar novo handler usando Event Delegation
            document.addEventListener('click', function(e) {
                // Check if clicked on hamburger button or its children
                var hamburger = e.target.closest('#eau-hamburger-btn');
                if (hamburger) {
                    e.preventDefault();
                    e.stopPropagation();

                    // Toggle all sidebars
                    var isOpen = document.body.classList.contains('eau-sidebar-open');

                    if (isOpen) {
                        // Close
                        document.querySelectorAll('#eau-sidebar').forEach(function(el) {
                            el.classList.remove('active');
                        });
                        document.querySelectorAll('#eau-sidebar-overlay').forEach(function(el) {
                            el.classList.remove('active');
                        });
                        document.body.classList.remove('eau-sidebar-open');
                        document.querySelectorAll('#eau-hamburger-btn').forEach(function(el) {
                            el.setAttribute('aria-expanded', 'false');
                        });
                    } else {
                        // Open
                        document.querySelectorAll('#eau-sidebar').forEach(function(el) {
                            el.classList.add('active');
                        });
                        document.querySelectorAll('#eau-sidebar-overlay').forEach(function(el) {
                            el.classList.add('active');
                        });
                        document.body.classList.add('eau-sidebar-open');
                        document.querySelectorAll('#eau-hamburger-btn').forEach(function(el) {
                            el.setAttribute('aria-expanded', 'true');
                        });
                    }
                    return;
                }

                // Check if clicked on close button
                var closeBtn = e.target.closest('#eau-sidebar-close');
                if (closeBtn) {
                    e.preventDefault();
                    document.querySelectorAll('#eau-sidebar').forEach(function(el) {
                        el.classList.remove('active');
                    });
                    document.querySelectorAll('#eau-sidebar-overlay').forEach(function(el) {
                        el.classList.remove('active');
                    });
                    document.body.classList.remove('eau-sidebar-open');
                    return;
                }

                // Check if clicked on overlay
                var overlay = e.target.closest('#eau-sidebar-overlay');
                if (overlay && e.target === overlay) {
                    e.preventDefault();
                    document.querySelectorAll('#eau-sidebar').forEach(function(el) {
                        el.classList.remove('active');
                    });
                    document.querySelectorAll('#eau-sidebar-overlay').forEach(function(el) {
                        el.classList.remove('active');
                    });
                    document.body.classList.remove('eau-sidebar-open');
                    return;
                }
            }, true);

            console.log('Event Delegation handler injected');
        });

        console.log('   Código injetado com sucesso');

        // Verificar botões hamburger
        const hamburgerBtns = await page.locator('#eau-hamburger-btn');
        const hamburgerCount = await hamburgerBtns.count();
        console.log('8. Quantidade de botões hamburger:', hamburgerCount);

        if (hamburgerCount > 0) {
            console.log('9. Clicando no PRIMEIRO botão hamburger...');
            await hamburgerBtns.first().click({ force: true });
            await page.waitForTimeout(1500);

            await page.screenshot({ path: 'screenshots/production-after-click-injected.png', fullPage: true });

            // Verificar se sidebar abriu
            const sidebarState = await page.evaluate(() => {
                const sidebars = document.querySelectorAll('#eau-sidebar');
                const bodyHasClass = document.body.classList.contains('eau-sidebar-open');
                return {
                    sidebars: Array.from(sidebars).map((s, i) => ({
                        index: i,
                        hasActive: s.classList.contains('active'),
                    })),
                    bodyHasClass: bodyHasClass,
                };
            });
            console.log('   Estado após clique:', sidebarState);

            const anyOpen = sidebarState.sidebars.some(s => s.hasActive) || sidebarState.bodyHasClass;
            if (anyOpen) {
                console.log('   ✅ SUCESSO: Menu abriu com Event Delegation!');
            } else {
                console.log('   ❌ FALHA: Menu não abriu');
            }

            // Testar fechar
            console.log('10. Clicando no botão fechar...');
            const closeBtns = await page.locator('#eau-sidebar-close');
            if (await closeBtns.count() > 0) {
                await closeBtns.first().click({ force: true });
                await page.waitForTimeout(1000);

                const closedState = await page.evaluate(() => {
                    return document.body.classList.contains('eau-sidebar-open');
                });
                console.log('    Menu fechou:', !closedState);
            }
        }

        await page.waitForTimeout(3000);

    } catch (error) {
        console.error('Erro:', error.message);
        await page.screenshot({ path: 'screenshots/production-menu-error-v3.png', fullPage: true });
    } finally {
        await browser.close();
    }
})();
