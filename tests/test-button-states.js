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
        await page.waitForTimeout(2000);

        // Find the view button (eye icon) in the table row
        const viewBtn = await page.$('button[data-action="view"]');
        if (viewBtn) {
            console.log('Found view button');
            await viewBtn.click();
            await page.waitForTimeout(1500);

            // Take screenshot of modal
            await page.screenshot({ path: 'test-view-modal.png', fullPage: false });
            console.log('View modal screenshot saved');

            // Look for approve button
            const approveBtn = await page.$('#eau-approve-application');
            if (approveBtn) {
                console.log('Found approve button');
                await approveBtn.click();
                await page.waitForTimeout(1500);

                // Take screenshot of approve modal
                await page.screenshot({ path: 'test-approve-modal.png', fullPage: false });
                console.log('Approve modal screenshot saved');

                // Find confirm approve button and check its styles
                const confirmBtn = await page.$('#eau-confirm-approve');
                if (confirmBtn) {
                    // Get styles before click
                    const stylesBefore = await page.evaluate(() => {
                        const btn = document.querySelector('#eau-confirm-approve');
                        if (!btn) return null;
                        const computed = window.getComputedStyle(btn);
                        return {
                            background: computed.backgroundColor,
                            color: computed.color
                        };
                    });
                    console.log('Button styles before click:', stylesBefore);

                    // Click the button
                    await confirmBtn.focus();
                    await page.waitForTimeout(500);

                    // Get styles when focused
                    const stylesFocused = await page.evaluate(() => {
                        const btn = document.querySelector('#eau-confirm-approve');
                        if (!btn) return null;
                        const computed = window.getComputedStyle(btn);
                        return {
                            background: computed.backgroundColor,
                            color: computed.color
                        };
                    });
                    console.log('Button styles when focused:', stylesFocused);

                    await page.screenshot({ path: 'test-button-focused.png', fullPage: false });
                    console.log('Button focused screenshot saved');
                }
            }
        } else {
            console.log('View button not found, trying alternative selector');

            // Try clicking on the first row action
            const actionCell = await page.$('.eau-table-actions');
            if (actionCell) {
                const firstBtn = await actionCell.$('button');
                if (firstBtn) {
                    await firstBtn.click();
                    await page.waitForTimeout(1500);
                    await page.screenshot({ path: 'test-modal-alt.png', fullPage: false });
                    console.log('Alternative modal screenshot saved');
                }
            }
        }

        console.log('Test completed!');

    } catch (error) {
        console.error('Error:', error.message);
        await page.screenshot({ path: 'test-error.png', fullPage: true });
    } finally {
        await browser.close();
    }
})();
