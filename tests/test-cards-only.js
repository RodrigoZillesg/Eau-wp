const { chromium } = require('playwright');

(async () => {
    const browser = await chromium.launch({ headless: false });
    const page = await browser.newPage({ viewport: { width: 1920, height: 1080 } });

    // Login
    await page.goto('http://eau-site.local/login/');
    await page.waitForLoadState('networkidle');
    const usernameField = await page.$('input[name="log"]') || await page.$('input[type="text"]');
    const passwordField = await page.$('input[name="pwd"]') || await page.$('input[type="password"]');
    if (usernameField && passwordField) {
        await usernameField.fill('rrzillesg@gmail.com');
        await passwordField.fill('Pl@ttyPl@tty');
        const submitBtn = await page.$('button[type="submit"]') || await page.$('input[type="submit"]');
        if (submitBtn) await submitBtn.click();
        await page.waitForLoadState('networkidle');
    }

    // Membership Applications
    await page.goto('http://eau-site.local/dashboard/membership-applications/');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);
    
    // Screenshot focado no topo da página
    await page.screenshot({ path: 'tests/screenshots/cards-problem.png' });
    
    // Análise dos cards
    const cardsInfo = await page.evaluate(() => {
        const container = document.querySelector('.eau-membership-applications-container');
        const cards = document.querySelectorAll('.eau-dashboard-card');
        const cardsContainer = document.querySelector('.eau-dashboard-cards');
        
        return {
            containerRect: container ? container.getBoundingClientRect() : null,
            cardsContainerRect: cardsContainer ? cardsContainer.getBoundingClientRect() : null,
            cardsCount: cards.length,
            viewportHeight: window.innerHeight,
            headerHeight: document.querySelector('header') ? document.querySelector('header').getBoundingClientRect().height : 0
        };
    });
    
    console.log('Cards Info:', JSON.stringify(cardsInfo, null, 2));
    
    await page.waitForTimeout(5000);
    await browser.close();
})();
