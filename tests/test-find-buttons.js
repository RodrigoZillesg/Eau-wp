const { chromium } = require('playwright');

(async () => {
    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({ viewport: { width: 1920, height: 1080 } });
    const page = await context.newPage();

    try {
        // Login
        await page.goto('http://eau-site.local/login/');
        await page.waitForTimeout(1000);
        const userField = await page.$('input[name="log"]');
        const passField = await page.$('input[name="pwd"]');
        await userField.fill('rrzillesg@gmail.com');
        await passField.fill('Pl@ttyPl@tty');
        await page.click('button[type="submit"]');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(2000);

        // Navigate to Membership Applications
        await page.goto('http://eau-site.local/dashboard/membership-applications/');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(3000);

        // Get all buttons in the page
        const allButtons = await page.evaluate(() => {
            const btns = document.querySelectorAll('button');
            return Array.from(btns).map(b => ({
                text: b.textContent.trim().substring(0, 50),
                className: b.className,
                id: b.id,
                dataAction: b.getAttribute('data-action'),
                title: b.getAttribute('title')
            }));
        });
        console.log('All buttons:', JSON.stringify(allButtons, null, 2));

        // Get action buttons in table
        const actionBtns = await page.evaluate(() => {
            const cells = document.querySelectorAll('td');
            const lastCells = [];
            cells.forEach(c => {
                const btns = c.querySelectorAll('button');
                if (btns.length > 0) {
                    btns.forEach(b => {
                        lastCells.push({
                            text: b.textContent.trim(),
                            className: b.className,
                            title: b.getAttribute('title'),
                            dataAction: b.getAttribute('data-action'),
                            innerHTML: b.innerHTML.substring(0, 100)
                        });
                    });
                }
            });
            return lastCells;
        });
        console.log('Action buttons in table:', JSON.stringify(actionBtns, null, 2));

        // Take screenshot
        await page.screenshot({ path: 'test-page-state.png', fullPage: false });
        console.log('Screenshot saved');

    } catch (error) {
        console.error('Error:', error.message);
    } finally {
        await browser.close();
    }
})();
