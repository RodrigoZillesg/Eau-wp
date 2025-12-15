const { chromium } = require('playwright');

(async () => {
    const browser = await chromium.launch({ headless: false });
    const context = await browser.newContext({ viewport: { width: 1920, height: 1080 } });
    const page = await context.newPage();

    try {
        // Login
        console.log('1. Fazendo login...');
        await page.goto('http://eau-site.local/login/');
        await page.waitForLoadState('networkidle');

        // Tirar screenshot da página de login para ver os campos
        await page.screenshot({ path: 'tests/screenshots/login-page.png', fullPage: true });
        console.log('   Screenshot login salvo');

        // Tentar diferentes seletores
        const usernameField = await page.$('input[name="log"]') ||
                              await page.$('input[name="username"]') ||
                              await page.$('#user_login') ||
                              await page.$('input[type="text"]');

        const passwordField = await page.$('input[name="pwd"]') ||
                              await page.$('input[name="password"]') ||
                              await page.$('#user_pass') ||
                              await page.$('input[type="password"]');

        if (usernameField && passwordField) {
            await usernameField.fill('rrzillesg@gmail.com');
            await passwordField.fill('Pl@ttyPl@tty');

            const submitBtn = await page.$('button[type="submit"]') || await page.$('input[type="submit"]');
            if (submitBtn) {
                await submitBtn.click();
                await page.waitForLoadState('networkidle');
                console.log('   Login OK!');
            }
        } else {
            console.log('   Campos de login não encontrados, continuando sem login...');
        }

        // Acessar página de eventos
        console.log('2. Acessando /events/...');
        await page.goto('http://eau-site.local/events/');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(2000);

        // Screenshot
        await page.screenshot({ path: 'tests/screenshots/events-page.png', fullPage: true });
        console.log('   Screenshot salvo: tests/screenshots/events-page.png');

        // Verificar título
        const title = await page.$eval('.eau-events-title', el => {
            const style = window.getComputedStyle(el);
            return {
                text: el.textContent,
                fontSize: style.fontSize,
                fontWeight: style.fontWeight
            };
        });
        console.log('3. Título encontrado:', title);

        // Verificar se há cards de eventos
        const cards = await page.$$('.eau-event-card');
        console.log('4. Cards encontrados:', cards.length);

        // Verificar se tem botão Edit nos cards
        const editLinks = await page.$$('.eau-event-card-edit');
        console.log('5. Links de Edit encontrados:', editLinks.length);

        // Verificar estrutura do card
        if (cards.length > 0) {
            const cardHTML = await cards[0].innerHTML();
            console.log('6. HTML do primeiro card (resumido):');
            console.log('   - Tem eau-event-card-actions?', cardHTML.includes('eau-event-card-actions'));
            console.log('   - Tem Edit?', cardHTML.includes('Edit'));
            console.log('   - Tem Open?', cardHTML.includes('Open'));
        }

        // Verificar console logs
        page.on('console', msg => console.log('Console:', msg.text()));

        console.log('\n=== TESTE CONCLUÍDO ===');
        console.log('Aguardando 10 segundos para visualização...');
        await page.waitForTimeout(10000);

    } catch (error) {
        console.error('ERRO:', error.message);
        await page.screenshot({ path: 'tests/screenshots/events-error.png', fullPage: true });
    } finally {
        await browser.close();
    }
})();
