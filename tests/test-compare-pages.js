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

        const usernameField = await page.$('input[name="log"]') || await page.$('input[type="text"]');
        const passwordField = await page.$('input[name="pwd"]') || await page.$('input[type="password"]');

        if (usernameField && passwordField) {
            await usernameField.fill('rrzillesg@gmail.com');
            await passwordField.fill('Pl@ttyPl@tty');
            const submitBtn = await page.$('button[type="submit"]') || await page.$('input[type="submit"]');
            if (submitBtn) {
                await submitBtn.click();
                await page.waitForLoadState('networkidle');
            }
        }
        console.log('   Login OK!');

        // 1. Membership Applications (problema)
        console.log('\n2. Membership Applications...');
        await page.goto('http://eau-site.local/dashboard/membership-applications/');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(2000);
        await page.screenshot({ path: 'tests/screenshots/1-membership-applications.png', fullPage: true });

        // 2. Dashboard (referência para cards)
        console.log('3. Dashboard (referência cards)...');
        await page.goto('http://eau-site.local/dashboard/');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(2000);
        await page.screenshot({ path: 'tests/screenshots/2-dashboard-cards.png', fullPage: true });

        // 3. My CPDs (referência para tabela)
        console.log('4. My CPDs (referência tabela)...');
        await page.goto('http://eau-site.local/dashboard/my-cpds/');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(2000);
        await page.screenshot({ path: 'tests/screenshots/3-my-cpds-table.png', fullPage: true });

        // 4. Members (referência para paginação e título)
        console.log('5. Members (referência paginação e título)...');
        await page.goto('http://eau-site.local/dashboard/members/');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(2000);
        await page.screenshot({ path: 'tests/screenshots/4-members-pagination.png', fullPage: true });

        console.log('\n=== SCREENSHOTS SALVOS ===');
        console.log('Aguardando 5 segundos...');
        await page.waitForTimeout(5000);

    } catch (error) {
        console.error('ERRO:', error.message);
    } finally {
        await browser.close();
    }
})();
