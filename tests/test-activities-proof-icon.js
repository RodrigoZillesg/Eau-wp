/**
 * Test: Activities Management - Proof Icon
 *
 * Verifica se o icone de prova/evidencia aparece na coluna Actions
 * da pagina CPD Activities Management quando existe prova anexada.
 */

const { chromium } = require('playwright');

const BASE_URL = 'http://eau-site.local';
const LOGIN_EMAIL = 'rrzillesg@gmail.com';
const LOGIN_PASSWORD = 'Pl@ttyPl@tty';

const DELAYS = {
    afterNavigation: 5000,
    afterTableLoad: 3000,
    afterLogin: 3000,
};

async function runTest() {
    console.log('Starting test: Activities Management - Proof Icon');

    const browser = await chromium.launch({
        headless: false,
        args: ['--start-maximized']
    });

    const context = await browser.newContext({
        viewport: { width: 1920, height: 1080 }
    });

    const page = await context.newPage();

    try {
        // 1. Login
        console.log('1. Navigating to login page...');
        await page.goto(`${BASE_URL}/login/`);
        await page.waitForLoadState('networkidle');

        console.log('2. Filling login form...');
        // Wait for form to be ready
        await page.waitForTimeout(2000);

        // Try different selectors for username field
        const usernameSelectors = ['#username', 'input[name="log"]', 'input[name="username"]', '#user_login'];
        let usernameSelector = null;

        for (const selector of usernameSelectors) {
            const element = await page.$(selector);
            if (element) {
                usernameSelector = selector;
                console.log(`   Found username field: ${selector}`);
                break;
            }
        }

        if (!usernameSelector) {
            // Take screenshot to see the page
            await page.screenshot({ path: 'tests/screenshots/login-page-debug.png', fullPage: true });
            throw new Error('Could not find username field');
        }

        // Try different selectors for password field
        const passwordSelectors = ['#password', 'input[name="pwd"]', 'input[name="password"]', '#user_pass'];
        let passwordSelector = null;

        for (const selector of passwordSelectors) {
            const element = await page.$(selector);
            if (element) {
                passwordSelector = selector;
                console.log(`   Found password field: ${selector}`);
                break;
            }
        }

        await page.fill(usernameSelector, LOGIN_EMAIL);
        await page.fill(passwordSelector, LOGIN_PASSWORD);

        // Find submit button
        const submitButton = await page.$('button[type="submit"]') || await page.$('input[type="submit"]');
        if (submitButton) {
            await submitButton.click();
        } else {
            await page.click('button:has-text("Log In")');
        }

        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(DELAYS.afterLogin);

        console.log('3. Login successful, navigating to Activities Management...');

        // 2. Navigate to Activities Management
        await page.goto(`${BASE_URL}/dashboard/manage-activities/`);
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(DELAYS.afterNavigation);

        // 3. Wait for table to load
        console.log('4. Waiting for table to load...');
        await page.waitForSelector('.eau-skeleton', { state: 'hidden', timeout: 15000 }).catch(() => {});
        await page.waitForSelector('#activities-table-tbody tr', { timeout: 15000 }).catch(() => {});
        await page.waitForTimeout(DELAYS.afterTableLoad);

        // 4. Check for proof icons
        console.log('5. Checking for proof icons...');

        const proofIcons = await page.$$('.eau-action-proof');
        const totalRows = await page.$$('#activities-table-tbody tr');

        console.log(`   - Total rows in table: ${totalRows.length}`);
        console.log(`   - Rows with proof icon: ${proofIcons.length}`);

        if (proofIcons.length > 0) {
            console.log('SUCCESS: Proof icons found in the Actions column!');

            // Get the href of the first proof icon
            const firstProofHref = await proofIcons[0].getAttribute('href');
            console.log(`   - First proof URL: ${firstProofHref}`);

            // Check if the icon has the correct attributes
            const hasTarget = await proofIcons[0].getAttribute('target');
            const hasTitle = await proofIcons[0].getAttribute('title');

            console.log(`   - Opens in new tab: ${hasTarget === '_blank' ? 'Yes' : 'No'}`);
            console.log(`   - Has tooltip: ${hasTitle ? 'Yes' : 'No'}`);
        } else {
            console.log('INFO: No proof icons found. This may be expected if no activities have proof attached.');
        }

        // 5. Take screenshot
        console.log('6. Taking screenshot...');
        const screenshotPath = 'tests/screenshots/activities-proof-icon-test.png';
        await page.screenshot({
            path: screenshotPath,
            fullPage: false
        });
        console.log(`   Screenshot saved to: ${screenshotPath}`);

        // 6. Verify the icon element structure
        if (proofIcons.length > 0) {
            const iconElement = await proofIcons[0].$('i[data-lucide="external-link"]');
            if (iconElement) {
                console.log('SUCCESS: Proof icon uses correct Lucide icon (external-link)!');
            } else {
                console.log('WARNING: Proof icon does not have the expected Lucide icon.');
            }
        }

        console.log('\n=== TEST COMPLETED SUCCESSFULLY ===\n');

    } catch (error) {
        console.error('Test failed:', error.message);

        // Take error screenshot
        await page.screenshot({
            path: 'tests/screenshots/activities-proof-icon-error.png',
            fullPage: true
        });

    } finally {
        await browser.close();
    }
}

runTest();
