const { chromium } = require('playwright');

(async () => {
    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({ viewport: { width: 1920, height: 1080 } });
    const page = await context.newPage();

    try {
        // Go to login page first to see form structure
        console.log('Going to login page...');
        await page.goto('http://eau-site.local/login/');
        await page.waitForTimeout(2000);

        // Take screenshot to see form
        await page.screenshot({ path: 'test-login-form.png', fullPage: true });
        console.log('Login page screenshot saved');

        // Find all inputs
        const inputs = await page.$$('input');
        console.log('Found', inputs.length, 'inputs');

        for (const input of inputs) {
            const type = await input.getAttribute('type');
            const name = await input.getAttribute('name');
            const id = await input.getAttribute('id');
            const placeholder = await input.getAttribute('placeholder');
            console.log('Input:', { type, name, id, placeholder });
        }

        // Try different selectors for username field
        const possibleUserFields = ['#username', '#user_login', 'input[name="log"]', 'input[name="username"]', 'input[type="text"]', 'input[type="email"]'];
        for (const selector of possibleUserFields) {
            const field = await page.$(selector);
            if (field) {
                console.log('Found user field with selector:', selector);
            }
        }

        // Try login with the form
        try {
            // Try common WordPress login form selectors
            const userField = await page.$('input[name="log"]') || await page.$('input[name="username"]') || await page.$('#user_login') || await page.$('input[type="text"]');
            const passField = await page.$('input[name="pwd"]') || await page.$('input[name="password"]') || await page.$('#user_pass') || await page.$('input[type="password"]');

            if (userField && passField) {
                console.log('Found login fields, attempting login...');
                await userField.fill('rrzillesg@gmail.com');
                await passField.fill('Pl@ttyPl@tty');

                // Find submit button
                const submitBtn = await page.$('button[type="submit"]') || await page.$('input[type="submit"]');
                if (submitBtn) {
                    await submitBtn.click();
                    await page.waitForLoadState('networkidle');
                    await page.waitForTimeout(3000);

                    console.log('Login submitted, current URL:', page.url());

                    // Navigate to membership applications
                    console.log('Navigating to Membership Applications...');
                    await page.goto('http://eau-site.local/dashboard/membership-applications/');
                    await page.waitForLoadState('networkidle');
                    await page.waitForTimeout(3000);

                    // Take screenshot
                    await page.screenshot({ path: 'test-membership-applications-cards.png', fullPage: false });
                    console.log('Membership Applications screenshot saved');

                    // Check container position
                    const container = await page.$('.eau-membership-applications-container');
                    if (container) {
                        const containerBox = await container.boundingBox();
                        console.log('Container position - top:', containerBox.y, 'left:', containerBox.x);
                        console.log('Container size - width:', containerBox.width, 'height:', containerBox.height);
                    } else {
                        console.log('Container not found');
                    }

                    // Check cards container
                    const cardsContainer = await page.$('.eau-dashboard-cards');
                    if (cardsContainer) {
                        const cardsBox = await cardsContainer.boundingBox();
                        console.log('Cards container position - top:', cardsBox.y, 'left:', cardsBox.x);
                        console.log('Cards container size - width:', cardsBox.width, 'height:', cardsBox.height);
                    }

                    // Count cards
                    const cards = await page.$$('.eau-dashboard-card');
                    console.log('Number of cards:', cards.length);

                    // Check first card position
                    if (cards.length > 0) {
                        const firstCard = cards[0];
                        const isVisible = await firstCard.isVisible();
                        const cardBox = await firstCard.boundingBox();
                        console.log('First card visible:', isVisible);
                        console.log('First card position - top:', cardBox.y, 'left:', cardBox.x);
                    }
                }
            } else {
                console.log('Could not find login fields');
            }
        } catch (e) {
            console.log('Login error:', e.message);
        }

        console.log('Test completed!');

    } catch (error) {
        console.error('Error:', error.message);
        await page.screenshot({ path: 'test-error.png', fullPage: true });
    } finally {
        await browser.close();
    }
})();
