/**
 * Playwright Test: Events Management - Table and Modal
 * Tests: Table columns, Modal fonts, Create Event button validation
 */

const { chromium } = require('playwright');
const path = require('path');

const DELAYS = {
    afterNavigation: 3000,
    afterTableLoad: 2000,
    afterModalOpen: 1500,
    afterLogin: 3000,
};

const SCREENSHOT_DIR = path.join(__dirname, 'screenshots');

async function ensureScreenshotDir() {
    const fs = require('fs');
    if (!fs.existsSync(SCREENSHOT_DIR)) {
        fs.mkdirSync(SCREENSHOT_DIR, { recursive: true });
    }
}

async function login(page) {
    console.log('Logging in...');
    await page.goto('http://eau-site.local/login/');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    // Try different selectors for username field
    const usernameSelectors = ['#username', 'input[name="log"]', 'input[name="username"]', 'input[type="text"]'];
    let usernameField = null;

    for (const sel of usernameSelectors) {
        const field = await page.$(sel);
        if (field) {
            usernameField = sel;
            console.log('Found username field with selector:', sel);
            break;
        }
    }

    if (!usernameField) {
        console.log('Page HTML:', await page.content());
        throw new Error('Could not find username field');
    }

    await page.fill(usernameField, 'rrzillesg@gmail.com');

    // Try different selectors for password field
    const passwordSelectors = ['#password', 'input[name="pwd"]', 'input[name="password"]', 'input[type="password"]'];
    let passwordField = null;

    for (const sel of passwordSelectors) {
        const field = await page.$(sel);
        if (field) {
            passwordField = sel;
            console.log('Found password field with selector:', sel);
            break;
        }
    }

    await page.fill(passwordField, 'Pl@ttyPl@tty');

    // Try different selectors for submit button
    const submitSelectors = ['button[type="submit"]', 'input[type="submit"]', '#wp-submit', '.login-submit button'];
    for (const sel of submitSelectors) {
        const btn = await page.$(sel);
        if (btn) {
            console.log('Found submit button with selector:', sel);
            await page.click(sel);
            break;
        }
    }

    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(DELAYS.afterLogin);
    console.log('Login complete');
}

async function testTableColumns(page) {
    console.log('\n=== Testing Table Columns ===');

    await page.goto('http://eau-site.local/dashboard/events/');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(DELAYS.afterTableLoad);

    // Take screenshot of full page
    await page.screenshot({
        path: path.join(SCREENSHOT_DIR, '01-events-table-full.png'),
        fullPage: true
    });

    // Check if Actions column header exists
    const actionsHeader = await page.$('th.eau-table-actions-col');
    if (actionsHeader) {
        const isVisible = await actionsHeader.isVisible();
        console.log('Actions header exists:', true);
        console.log('Actions header visible:', isVisible);

        // Get bounding box
        const box = await actionsHeader.boundingBox();
        console.log('Actions header bounding box:', box);
    } else {
        console.log('Actions header NOT FOUND!');
    }

    // Check if action buttons exist in rows
    const actionButtons = await page.$$('.eau-table-actions .eau-action-btn');
    console.log('Action buttons found:', actionButtons.length);

    if (actionButtons.length > 0) {
        const firstButton = actionButtons[0];
        const isVisible = await firstButton.isVisible();
        console.log('First action button visible:', isVisible);
        const box = await firstButton.boundingBox();
        console.log('First action button bounding box:', box);
    }

    // Check container and table widths
    const container = await page.$('.eau-events-management-container');
    const tableWrapper = await page.$('.eau-data-table-wrapper');
    const table = await page.$('.eau-data-table');

    if (container && tableWrapper && table) {
        const containerBox = await container.boundingBox();
        const wrapperBox = await tableWrapper.boundingBox();
        const tableBox = await table.boundingBox();

        console.log('\nWidth comparison:');
        console.log('Container width:', containerBox?.width);
        console.log('Table wrapper width:', wrapperBox?.width);
        console.log('Table width:', tableBox?.width);

        if (tableBox?.width > containerBox?.width) {
            console.log('WARNING: Table is wider than container!');
        }
    }

    // Get computed styles
    const containerOverflow = await page.evaluate(() => {
        const el = document.querySelector('.eau-events-management-container');
        return el ? window.getComputedStyle(el).overflowX : 'not found';
    });
    console.log('Container overflow-x:', containerOverflow);

    return { hasActionsColumn: !!actionsHeader };
}

async function testModalFonts(page) {
    console.log('\n=== Testing Modal Fonts ===');

    // Click Create Event button
    await page.click('#eau-create-event-btn');
    await page.waitForTimeout(DELAYS.afterModalOpen);

    // Take screenshot of modal
    await page.screenshot({
        path: path.join(SCREENSHOT_DIR, '02-modal-basic-info.png'),
        fullPage: false
    });

    // Check font sizes
    const fontChecks = [
        { selector: '.eau-modal-title', name: 'Modal title' },
        { selector: '.eau-form-label', name: 'Form labels' },
        { selector: '.eau-form-hint', name: 'Form hints' },
        { selector: '.eau-form-section-title', name: 'Section titles' },
        { selector: '.eau-form-input', name: 'Form inputs' },
    ];

    for (const check of fontChecks) {
        const fontSize = await page.evaluate((sel) => {
            const el = document.querySelector(sel);
            return el ? window.getComputedStyle(el).fontSize : 'not found';
        }, check.selector);
        console.log(`${check.name} (${check.selector}): ${fontSize}`);
    }

    // Go to CPD & Settings tab
    await page.click('.eau-modal-tab-btn[data-tab="settings"]');
    await page.waitForTimeout(500);

    await page.screenshot({
        path: path.join(SCREENSHOT_DIR, '03-modal-settings-tab.png'),
        fullPage: false
    });

    // Check if Supplementary Materials field exists
    const materialsWrapper = await page.$('#eau-edit-materials-wrapper');
    console.log('\nSupplementary Materials wrapper exists:', !!materialsWrapper);

    // Close modal
    await page.click('#eau-modal-close');
    await page.waitForTimeout(500);
}

async function testCreateEventValidation(page) {
    console.log('\n=== Testing Create Event Validation ===');

    // Click Create Event button
    await page.click('#eau-create-event-btn');
    await page.waitForTimeout(DELAYS.afterModalOpen + 1000); // Wait for modal loading to complete

    // Check if Create Event button is enabled without filling fields
    const saveButton = await page.$('#eau-modal-save');
    const isDisabled = await saveButton.evaluate(el => el.disabled);
    console.log('Save button disabled initially:', isDisabled);

    // Take screenshot showing disabled button
    await page.screenshot({
        path: path.join(SCREENSHOT_DIR, '04-modal-button-disabled.png'),
        fullPage: false
    });

    // Now fill in required fields and check if button becomes enabled
    console.log('Filling required fields...');
    await page.fill('#eau-edit-title', 'Test Event');
    await page.fill('#eau-edit-start_datetime', '2025-12-01T10:00');
    await page.fill('#eau-edit-end_datetime', '2025-12-01T12:00');

    await page.waitForTimeout(500);

    // Check if button is now enabled
    const isEnabledAfterFill = await saveButton.evaluate(el => !el.disabled);
    console.log('Save button enabled after filling fields:', isEnabledAfterFill);

    // Take screenshot showing enabled button
    await page.screenshot({
        path: path.join(SCREENSHOT_DIR, '05-modal-button-enabled.png'),
        fullPage: false
    });

    // Test validation by clearing a field
    await page.fill('#eau-edit-title', '');
    await page.waitForTimeout(300);

    const isDisabledAgain = await saveButton.evaluate(el => el.disabled);
    console.log('Save button disabled after clearing field:', isDisabledAgain);

    // Close modal
    await page.click('#eau-modal-close').catch(() => {
        // Modal might have closed on its own
    });
}

async function main() {
    await ensureScreenshotDir();

    const browser = await chromium.launch({
        headless: false,
        args: ['--start-maximized']
    });

    const context = await browser.newContext({
        viewport: { width: 1920, height: 1080 }
    });

    const page = await context.newPage();

    try {
        await login(page);

        const tableResult = await testTableColumns(page);
        await testModalFonts(page);
        await testCreateEventValidation(page);

        console.log('\n=== TEST SUMMARY ===');
        console.log('Screenshots saved to:', SCREENSHOT_DIR);

        if (!tableResult.hasActionsColumn) {
            console.log('ISSUE: Actions column not visible in table');
        }

    } catch (error) {
        console.error('Test failed:', error);
        await page.screenshot({
            path: path.join(SCREENSHOT_DIR, 'error.png'),
            fullPage: true
        });
    } finally {
        await page.waitForTimeout(3000);
        await browser.close();
    }
}

main();
