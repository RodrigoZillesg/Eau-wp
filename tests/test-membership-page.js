const { chromium } = require('playwright');

(async () => {
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage({ viewport: { width: 1920, height: 1080 } });

    try {
        // Login
        console.log('Logging in...');
        await page.goto('http://eau-site.local/login/');
        await page.waitForTimeout(1000);
        await page.fill('input[name="log"]', 'rodrigo.zillesg@platty.tech');
        await page.fill('input[name="pwd"]', 'Salmo119:97');
        await page.click('button[type="submit"]');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(2000);

        // Go to membership selection
        console.log('Navigating to membership selection...');
        await page.goto('http://eau-site.local/membership-selection/');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(2000);

        // Take screenshot of the page
        await page.screenshot({ path: 'membership-page.png', fullPage: true });
        console.log('Screenshot 1: membership-page.png');

        // Check if cards exist
        const cards = await page.$$('.eau-membership-card');
        console.log('Number of membership cards:', cards.length);

        if (cards.length > 0) {
            // Click on "More Details" button of first card
            console.log('Clicking More Details...');
            const detailsBtn = await page.$('.eau-details-btn');
            if (detailsBtn) {
                await detailsBtn.click();
                await page.waitForTimeout(1000);

                // Take screenshot of details modal
                await page.screenshot({ path: 'membership-details-modal.png', fullPage: false });
                console.log('Screenshot 2: membership-details-modal.png');

                // Close modal
                const closeBtn = await page.$('#details-modal .eau-modal-close');
                if (closeBtn) {
                    await closeBtn.click();
                    await page.waitForTimeout(500);
                }
            }

            // Click on "Apply Now" button of first card
            console.log('Clicking Apply Now...');
            const applyBtn = await page.$('.eau-apply-btn');
            if (applyBtn) {
                await applyBtn.click();
                await page.waitForTimeout(1500);

                // Take screenshot of application modal Step 1
                await page.screenshot({ path: 'membership-apply-step1.png', fullPage: false });
                console.log('Screenshot 3: membership-apply-step1.png (Personal Info)');

                // Check if phone field has DDI selector
                const phoneIti = await page.$('.eau-phone-input-wrapper .iti');
                console.log('Phone DDI selector present:', phoneIti !== null);

                // Check phone field value
                const phoneValue = await page.$eval('#app-phone-full', el => el.value);
                console.log('Phone field value:', phoneValue || '(empty)');

                // Click Next to go to Step 2
                console.log('Clicking Next to go to Step 2...');
                const nextBtn = await page.$('.eau-next-step');
                if (nextBtn) {
                    await nextBtn.click();
                    await page.waitForTimeout(1000);

                    // Take screenshot of Step 2
                    await page.screenshot({ path: 'membership-apply-step2.png', fullPage: false });
                    console.log('Screenshot 4: membership-apply-step2.png (Organization)');

                    // Check if step indicator updated
                    const step2Active = await page.$('.eau-app-step[data-step="2"].active');
                    const step1Completed = await page.$('.eau-app-step[data-step="1"].completed');
                    console.log('Step 2 is active:', step2Active !== null);
                    console.log('Step 1 is completed:', step1Completed !== null);

                    // Click Next to go to Step 3
                    console.log('Clicking Next to go to Step 3...');
                    await nextBtn.click();
                    await page.waitForTimeout(1000);

                    // Take screenshot of Step 3
                    await page.screenshot({ path: 'membership-apply-step3.png', fullPage: false });
                    console.log('Screenshot 5: membership-apply-step3.png (Documents)');

                    // Check step indicator
                    const step3Active = await page.$('.eau-app-step[data-step="3"].active');
                    console.log('Step 3 is active:', step3Active !== null);
                }
            }
        } else {
            console.log('No membership cards found - user may already have membership');
        }

        console.log('Test completed!');

    } catch (error) {
        console.error('Error:', error.message);
        await page.screenshot({ path: 'membership-error.png', fullPage: true });
    } finally {
        await browser.close();
    }
})();
