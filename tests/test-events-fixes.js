/**
 * Playwright Test: Events Management Fixes
 */

const { chromium } = require('playwright');
const path = require('path');

const DELAYS = {
    afterNavigation: 3000,
    afterModalOpen: 2500,
    afterLogin: 3000,
};

const SCREENSHOT_DIR = path.join(__dirname, 'screenshots', 'fixes');

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

    await page.fill('input[name="log"]', 'rrzillesg@gmail.com');
    await page.fill('input[name="pwd"]', 'Pl@ttyPl@tty');
    await page.click('button[type="submit"]');

    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(DELAYS.afterLogin);
    console.log('Login complete');
}

async function testButtonValidation(page) {
    console.log('\n=== Testing Button Validation ===');

    await page.goto('http://eau-site.local/dashboard/events/');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    // Click Create Event button
    await page.click('#eau-create-event-btn');
    await page.waitForTimeout(DELAYS.afterModalOpen);

    // Check button state before filling fields
    const saveButton = await page.$('#eau-modal-save');

    // Get button state and classes
    const buttonInfo = await saveButton.evaluate(el => ({
        disabled: el.disabled,
        className: el.className,
        innerHTML: el.innerHTML
    }));

    console.log('Button info (before filling):', buttonInfo);

    // Take screenshot
    await page.screenshot({
        path: path.join(SCREENSHOT_DIR, '01-button-before-fill.png'),
        fullPage: false
    });

    // Check if required fields are empty
    const titleValue = await page.$eval('#eau-edit-title', el => el.value);
    const startValue = await page.$eval('#eau-edit-start_datetime', el => el.value);
    const endValue = await page.$eval('#eau-edit-end_datetime', el => el.value);

    console.log('Field values:');
    console.log('  Title:', titleValue || '(empty)');
    console.log('  Start:', startValue || '(empty)');
    console.log('  End:', endValue || '(empty)');

    // Try clicking the button
    console.log('\nTrying to click Create Event button...');
    await page.click('#eau-modal-save');
    await page.waitForTimeout(1000);

    // Take screenshot after click attempt
    await page.screenshot({
        path: path.join(SCREENSHOT_DIR, '02-after-click-attempt.png'),
        fullPage: false
    });

    // Check if modal is still open
    const modalVisible = await page.$eval('#eau-event-edit-modal', el => el.classList.contains('active'));
    console.log('Modal still open after click:', modalVisible);

    // Check for toast messages
    const toasts = await page.$$('.eau-toast');
    console.log('Toast messages found:', toasts.length);

    // Close modal
    await page.click('#eau-modal-close').catch(() => {});
    await page.waitForTimeout(500);
}

async function testDropdownOverflow(page) {
    console.log('\n=== Testing Dropdown Overflow ===');

    await page.goto('http://eau-site.local/dashboard/events/');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    // Scroll to the bottom of the table
    await page.evaluate(() => {
        const tbody = document.querySelector('#eau-events-tbody');
        if (tbody) {
            tbody.scrollIntoView({ block: 'end' });
        }
    });

    await page.waitForTimeout(500);

    // Find the last row's more button
    const moreButtons = await page.$$('.eau-action-more');
    console.log('More buttons found:', moreButtons.length);

    if (moreButtons.length > 0) {
        // Click the last more button
        const lastButton = moreButtons[moreButtons.length - 1];
        await lastButton.click();
        await page.waitForTimeout(500);

        // Take screenshot
        await page.screenshot({
            path: path.join(SCREENSHOT_DIR, '03-dropdown-last-row.png'),
            fullPage: false
        });

        // Check if dropdown menu is visible
        const dropdownMenu = await page.$('.eau-dropdown-menu.active');
        if (dropdownMenu) {
            const box = await dropdownMenu.boundingBox();
            console.log('Dropdown menu bounding box:', box);

            // Check if it's clipped
            const viewportHeight = await page.evaluate(() => window.innerHeight);
            console.log('Viewport height:', viewportHeight);

            if (box && box.y + box.height > viewportHeight) {
                console.log('WARNING: Dropdown is clipped!');
            }
        }
    }
}

async function testTableColumns(page) {
    console.log('\n=== Testing Table Columns ===');

    await page.goto('http://eau-site.local/dashboard/events/');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    // Get table headers
    const headers = await page.$$eval('.eau-data-table thead th', ths =>
        ths.map(th => th.textContent.trim())
    );
    console.log('Current table headers:', headers);

    // Check if ID column exists
    const hasIdColumn = headers.some(h => h.toLowerCase().includes('id'));
    console.log('Has ID column:', hasIdColumn);

    // Take screenshot
    await page.screenshot({
        path: path.join(SCREENSHOT_DIR, '04-table-columns.png'),
        fullPage: false
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
        await testButtonValidation(page);
        await testDropdownOverflow(page);
        await testTableColumns(page);

        console.log('\n=== TEST COMPLETE ===');
        console.log('Screenshots saved to:', SCREENSHOT_DIR);

    } catch (error) {
        console.error('Test failed:', error);
        await page.screenshot({
            path: path.join(SCREENSHOT_DIR, 'error.png'),
            fullPage: true
        });
    } finally {
        await page.waitForTimeout(2000);
        await browser.close();
    }
}

main();
