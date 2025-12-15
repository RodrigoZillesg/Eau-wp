const { chromium } = require('playwright');

(async () => {
    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({ viewport: { width: 1920, height: 1080 } });
    const page = await context.newPage();

    try {
        // Go directly to register page (no login needed)
        console.log('Navigating to Register page...');
        await page.goto('http://eau-site.local/register/');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(2000);

        // Take full page screenshot
        await page.screenshot({ path: 'test-register-page.png', fullPage: true });
        console.log('Screenshot saved: test-register-page.png');

        // Check if page title is hidden
        const pageTitle = await page.$('.entry-title, .page-title, h1.wp-block-post-title');
        if (pageTitle) {
            const isVisible = await pageTitle.isVisible();
            console.log('Page title visible:', isVisible);
        } else {
            console.log('Page title element not found (good - may be hidden)');
        }

        // Check if registration header exists
        const header = await page.$('.eau-registration-header');
        if (header) {
            const headerBox = await header.boundingBox();
            console.log('Registration header position - top:', headerBox.y);
        }

        // Check container position
        const container = await page.$('.eau-public-registration-container');
        if (container) {
            const containerBox = await container.boundingBox();
            console.log('Container position - top:', containerBox.y);
            console.log('Container has padding-top for header:', containerBox.y >= 60 ? 'YES' : 'NO');
        }

        // Check form fields styling
        const inputs = await page.$$('.eau-form-input');
        console.log('Number of form inputs:', inputs.length);

        // Check buttons
        const primaryBtns = await page.$$('.eau-btn-primary');
        console.log('Number of primary buttons:', primaryBtns.length);

        console.log('Test completed!');

    } catch (error) {
        console.error('Error:', error.message);
        await page.screenshot({ path: 'test-register-error.png', fullPage: true });
    } finally {
        await browser.close();
    }
})();
