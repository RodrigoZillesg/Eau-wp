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

        // Screenshot da página de membership applications
        console.log('2. Acessando membership-applications...');
        await page.goto('http://eau-site.local/dashboard/membership-applications/');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(2000);
        await page.screenshot({ path: 'tests/screenshots/membership-applications.png', fullPage: true });
        console.log('   Screenshot salvo: membership-applications.png');

        // Screenshot da página de members para comparação
        console.log('3. Acessando members para comparação...');
        await page.goto('http://eau-site.local/dashboard/members/');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(2000);
        await page.screenshot({ path: 'tests/screenshots/members-reference.png', fullPage: true });
        console.log('   Screenshot salvo: members-reference.png');

        // Screenshot do dashboard para comparação de cards
        console.log('4. Acessando dashboard para comparação de cards...');
        await page.goto('http://eau-site.local/dashboard/');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(2000);
        await page.screenshot({ path: 'tests/screenshots/dashboard-reference.png', fullPage: true });
        console.log('   Screenshot salvo: dashboard-reference.png');

        console.log('\n=== SCREENSHOTS SALVOS ===');
        await page.waitForTimeout(3000);

    } catch (error) {
        console.error('ERRO:', error.message);
    } finally {
        await browser.close();
    }
})();
