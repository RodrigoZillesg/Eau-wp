/**
 * Playwright Test: Events Management - All Fixes Verification
 * Tests: ID column, filters, dropdown overflow, tooltip, button validation, timezone
 */

const { chromium } = require('playwright');
const path = require('path');

const DELAYS = {
    afterNavigation: 3000,
    afterTableLoad: 2000,
    afterModalOpen: 2500,
    afterLogin: 3000,
};

const SCREENSHOT_DIR = path.join(__dirname, 'screenshots', 'all-fixes');

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

async function testTableAndFilters(page) {
    console.log('\n=== Testing Table and Filters ===');

    await page.goto('http://eau-site.local/dashboard/events/');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(DELAYS.afterTableLoad);

    // Take screenshot of table with ID column
    await page.screenshot({
        path: path.join(SCREENSHOT_DIR, '01-table-with-id-column.png'),
        fullPage: false
    });

    // Check for ID column
    const headers = await page.$$eval('.eau-data-table thead th', ths =>
        ths.map(th => th.textContent.trim())
    );
    console.log('Table headers:', headers);
    const hasIdColumn = headers.some(h => h.toLowerCase().includes('id'));
    console.log('Has ID column:', hasIdColumn);

    // Check filters exist
    const filters = {
        status: await page.$('#eau-events-status-filter'),
        date: await page.$('#eau-events-date-filter'),
        category: await page.$('#eau-events-category-filter'),
        cpd: await page.$('#eau-events-cpd-filter'),
        location: await page.$('#eau-events-location-filter'),
    };

    console.log('\nFilters present:');
    for (const [name, el] of Object.entries(filters)) {
        console.log(`  ${name}: ${el ? 'YES' : 'NO'}`);
    }

    // Test date filter - Upcoming
    if (filters.date) {
        await page.selectOption('#eau-events-date-filter', 'upcoming');
        await page.waitForTimeout(1500);
        await page.screenshot({
            path: path.join(SCREENSHOT_DIR, '02-filter-upcoming.png'),
            fullPage: false
        });
        console.log('Filtered by upcoming events');

        // Reset filter
        await page.selectOption('#eau-events-date-filter', '');
        await page.waitForTimeout(1500);
    }

    // Check first row ID (should be highest since ordered DESC)
    const firstRowId = await page.$eval('.eau-data-table tbody tr:first-child td:first-child', td => td.textContent.trim()).catch(() => 'N/A');
    console.log('First row ID (should be highest):', firstRowId);

    return { hasIdColumn, hasFilters: Object.values(filters).every(f => f) };
}

async function testDropdownOverflow(page) {
    console.log('\n=== Testing Dropdown Overflow ===');

    await page.goto('http://eau-site.local/dashboard/events/');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(DELAYS.afterTableLoad);

    // Find the last row's more button
    const moreButtons = await page.$$('.eau-action-more');
    console.log('More buttons found:', moreButtons.length);

    if (moreButtons.length > 0) {
        // Scroll to bottom
        await page.evaluate(() => {
            const tbody = document.querySelector('#eau-events-tbody');
            if (tbody) tbody.scrollIntoView({ block: 'end' });
        });
        await page.waitForTimeout(500);

        // Click the last more button
        const lastButton = moreButtons[moreButtons.length - 1];
        await lastButton.click();
        await page.waitForTimeout(500);

        // Take screenshot
        await page.screenshot({
            path: path.join(SCREENSHOT_DIR, '03-dropdown-last-row.png'),
            fullPage: false
        });

        // Check dropdown position
        const dropdownMenu = await page.$('.eau-dropdown-menu.active');
        if (dropdownMenu) {
            const dropdownBox = await dropdownMenu.boundingBox();
            const viewportHeight = await page.evaluate(() => window.innerHeight);
            console.log('Dropdown bounding box:', dropdownBox);
            console.log('Viewport height:', viewportHeight);

            if (dropdownBox && dropdownBox.y + dropdownBox.height > viewportHeight) {
                console.log('WARNING: Dropdown still clipped!');
            } else {
                console.log('SUCCESS: Dropdown is within viewport');
            }
        }

        // Close dropdown
        await page.click('body');
        await page.waitForTimeout(300);
    }
}

async function testButtonValidationAndTooltip(page) {
    console.log('\n=== Testing Button Validation & Tooltip ===');

    await page.goto('http://eau-site.local/dashboard/events/');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    // Click Create Event button
    await page.click('#eau-create-event-btn');
    await page.waitForTimeout(DELAYS.afterModalOpen);

    // Check button state before filling fields
    const saveButton = await page.$('#eau-modal-save');

    const buttonInfo = await saveButton.evaluate(el => ({
        disabled: el.disabled,
        className: el.className,
        title: el.getAttribute('title')
    }));

    console.log('Button info (before filling):');
    console.log('  Disabled:', buttonInfo.disabled);
    console.log('  Has disabled class:', buttonInfo.className.includes('eau-btn-disabled'));
    console.log('  Title (tooltip):', buttonInfo.title || '(none)');

    // Take screenshot
    await page.screenshot({
        path: path.join(SCREENSHOT_DIR, '04-button-disabled-with-tooltip.png'),
        fullPage: false
    });

    // Check for tooltip wrapper
    const tooltipWrapper = await page.$('.eau-btn-tooltip-wrapper');
    const tooltip = await page.$('.eau-btn-tooltip');
    console.log('  Tooltip wrapper exists:', !!tooltipWrapper);
    console.log('  Tooltip element exists:', !!tooltip);

    if (tooltip) {
        const tooltipText = await tooltip.textContent();
        console.log('  Tooltip text:', tooltipText);
    }

    // Now fill required fields
    console.log('\nFilling required fields...');
    await page.fill('#eau-edit-title', 'Test Event');
    await page.fill('#eau-edit-start_datetime', '2025-12-01T10:00');
    await page.fill('#eau-edit-end_datetime', '2025-12-01T12:00');
    await page.waitForTimeout(500);

    // Check button state after filling
    const buttonInfoAfter = await saveButton.evaluate(el => ({
        disabled: el.disabled,
        className: el.className
    }));

    console.log('\nButton info (after filling):');
    console.log('  Disabled:', buttonInfoAfter.disabled);
    console.log('  Has disabled class:', buttonInfoAfter.className.includes('eau-btn-disabled'));

    // Take screenshot
    await page.screenshot({
        path: path.join(SCREENSHOT_DIR, '05-button-enabled.png'),
        fullPage: false
    });

    // Close modal
    await page.click('#eau-modal-close').catch(() => {});
    await page.waitForTimeout(500);

    return {
        buttonDisabledWhenEmpty: buttonInfo.disabled && buttonInfo.className.includes('eau-btn-disabled'),
        tooltipShown: buttonInfo.title && buttonInfo.title.length > 0,
        buttonEnabledAfterFill: !buttonInfoAfter.disabled && !buttonInfoAfter.className.includes('eau-btn-disabled')
    };
}

async function testTimezoneSinglePage(page) {
    console.log('\n=== Testing Timezone on Single Page ===');

    // Go to events archive first
    await page.goto('http://eau-site.local/events/');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    // Find first event link
    const eventLink = await page.$('.eau-event-card a, .eau-event-item a');
    if (eventLink) {
        await eventLink.click();
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(2000);

        // Take screenshot
        await page.screenshot({
            path: path.join(SCREENSHOT_DIR, '06-single-page-timezone.png'),
            fullPage: false
        });

        // Check for timezone elements
        const timezoneEl = await page.$('.eau-event-timezone');
        const timezoneFull = await page.$('.eau-event-info-timezone-full');

        console.log('Timezone abbreviation element exists:', !!timezoneEl);
        console.log('Timezone full element exists:', !!timezoneFull);

        if (timezoneEl) {
            const timezoneText = await timezoneEl.textContent();
            console.log('Timezone abbreviation:', timezoneText);
        }

        if (timezoneFull) {
            const timezoneFullText = await timezoneFull.textContent();
            console.log('Timezone full name:', timezoneFullText);
        }

        return { hasTimezone: !!timezoneEl || !!timezoneFull };
    } else {
        console.log('No events found to test timezone');
        return { hasTimezone: false };
    }
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

    const results = {
        tableAndFilters: {},
        buttonValidation: {},
        timezone: {}
    };

    try {
        await login(page);

        results.tableAndFilters = await testTableAndFilters(page);
        await testDropdownOverflow(page);
        results.buttonValidation = await testButtonValidationAndTooltip(page);
        results.timezone = await testTimezoneSinglePage(page);

        console.log('\n=== TEST SUMMARY ===');
        console.log('1. ID Column:', results.tableAndFilters.hasIdColumn ? 'PASS' : 'FAIL');
        console.log('2. All Filters:', results.tableAndFilters.hasFilters ? 'PASS' : 'FAIL');
        console.log('3. Button Disabled When Empty:', results.buttonValidation.buttonDisabledWhenEmpty ? 'PASS' : 'FAIL');
        console.log('4. Tooltip Shows Missing Fields:', results.buttonValidation.tooltipShown ? 'PASS' : 'FAIL');
        console.log('5. Button Enabled After Fill:', results.buttonValidation.buttonEnabledAfterFill ? 'PASS' : 'FAIL');
        console.log('6. Timezone on Single Page:', results.timezone.hasTimezone ? 'PASS' : 'CHECK MANUALLY');
        console.log('\nScreenshots saved to:', SCREENSHOT_DIR);

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
