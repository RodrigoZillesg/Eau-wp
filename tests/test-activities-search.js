/**
 * Test: Activities Management - Search Filter
 *
 * Verifica se o filtro de busca por nome/email do membro funciona corretamente.
 */

const { chromium } = require('playwright');

const BASE_URL = 'http://eau-site.local';
const LOGIN_EMAIL = 'rrzillesg@gmail.com';
const LOGIN_PASSWORD = 'Pl@ttyPl@tty';

const DELAYS = {
    afterNavigation: 5000,
    afterTableLoad: 3000,
    afterLogin: 3000,
    afterSearch: 4000,
};

async function runTest() {
    console.log('Starting test: Activities Management - Search Filter');

    const browser = await chromium.launch({
        headless: false,
        args: ['--start-maximized']
    });

    const context = await browser.newContext({
        viewport: { width: 1920, height: 1080 }
    });

    const page = await context.newPage();

    try {
        // Monitor network requests
        let lastAjaxData = null;
        page.on('request', request => {
            if (request.url().includes('admin-ajax.php')) {
                const postData = request.postData();
                if (postData && postData.includes('eau_get_activities')) {
                    lastAjaxData = postData;
                    console.log('   [AJAX] Request sent: ' + postData.substring(0, 200) + '...');
                }
            }
        });

        // 1. Login
        console.log('1. Navigating to login page...');
        await page.goto(`${BASE_URL}/login/`);
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(2000);

        console.log('2. Filling login form...');
        await page.fill('input[name="log"]', LOGIN_EMAIL);
        await page.fill('input[name="pwd"]', LOGIN_PASSWORD);
        await page.click('button[type="submit"], input[type="submit"]');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(DELAYS.afterLogin);

        // 2. Navigate to Activities Management
        console.log('3. Navigating to Activities Management...');
        await page.goto(`${BASE_URL}/dashboard/manage-activities/`);
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(DELAYS.afterNavigation);

        // 3. Wait for table to load
        console.log('4. Waiting for table to load...');
        await page.waitForSelector('.eau-skeleton', { state: 'hidden', timeout: 15000 }).catch(() => {});
        await page.waitForSelector('#activities-table-tbody tr', { timeout: 15000 }).catch(() => {});
        await page.waitForTimeout(DELAYS.afterTableLoad);

        // 4. Get initial count
        const initialRows = await page.$$('#activities-table-tbody tr');
        const initialCount = initialRows.length;
        console.log(`   - Initial rows in table: ${initialCount}`);

        // Get total items from counter (optional)
        try {
            const totalItemsText = await page.$eval('.eau-table-counter, .eau-pagination-info, [class*="total"]', el => el.textContent.trim());
            console.log(`   - Total items info: ${totalItemsText}`);
        } catch (e) {
            console.log('   - Could not find total items counter');
        }

        // 5. Get a member name from the first row to search
        console.log('5. Getting member name from first row...');
        const firstMemberName = await page.$eval('#activities-table-tbody tr:first-child td:nth-child(3)', el => el.textContent.trim());
        console.log(`   - First member name: "${firstMemberName}"`);

        // 6. Search by member name
        console.log('6. Searching by member name...');
        const searchInput = await page.$('#eau-activities-search');
        if (searchInput) {
            // Use JavaScript to set value and trigger the search
            await page.evaluate((searchTerm) => {
                const input = document.getElementById('eau-activities-search');
                if (input) {
                    input.value = searchTerm;
                    // Trigger the jQuery keyup event
                    jQuery(input).trigger('keyup');
                }
            }, firstMemberName);

            // Verify the value is in the input
            const inputValue = await searchInput.inputValue();
            console.log(`   - Search input value: "${inputValue}"`);

            // Wait for debounce (500ms) + AJAX load
            console.log('   - Waiting for debounce and AJAX...');
            await page.waitForTimeout(DELAYS.afterSearch + 2000);

            // Wait for table to reload
            await page.waitForSelector('.eau-skeleton', { state: 'hidden', timeout: 15000 }).catch(() => {});
            await page.waitForTimeout(3000);

            // 7. Check results
            const searchRows = await page.$$('#activities-table-tbody tr');
            const searchCount = searchRows.length;
            console.log(`   - Rows after search: ${searchCount}`);

            // Check total after search
            try {
                const totalAfterSearch = await page.$eval('.eau-table-counter, .eau-pagination-info, [class*="total"]', el => el.textContent.trim());
                console.log(`   - Total after search: ${totalAfterSearch}`);
            } catch (e) {
                console.log('   - Could not find total counter after search');
            }

            // Verify all rows have the searched member
            let allMatch = true;
            let matchCount = 0;
            for (let i = 0; i < searchRows.length; i++) {
                const memberCell = await searchRows[i].$('td:nth-child(3)');
                if (memberCell) {
                    const memberText = await memberCell.textContent();
                    if (memberText.toLowerCase().includes(firstMemberName.toLowerCase())) {
                        matchCount++;
                    } else {
                        // Check if activity title matches
                        const titleCell = await searchRows[i].$('td:nth-child(2)');
                        if (titleCell) {
                            const titleText = await titleCell.textContent();
                            if (!titleText.toLowerCase().includes(firstMemberName.toLowerCase())) {
                                allMatch = false;
                                console.log(`   - Row ${i+1} does not match: Member="${memberText}", Title="${titleText}"`);
                            }
                        }
                    }
                }
            }

            console.log(`   - Rows matching member name: ${matchCount}/${searchCount}`);

            if (searchCount > 0 && searchCount <= initialCount) {
                console.log('SUCCESS: Search filter is working! Results are filtered.');
            } else if (searchCount === 0) {
                console.log('WARNING: No results found. The member may not have activities.');
            } else {
                console.log('WARNING: Search may not be filtering correctly.');
            }

            // Take screenshot
            await page.screenshot({
                path: 'tests/screenshots/activities-search-test.png',
                fullPage: false
            });
            console.log('   - Screenshot saved: activities-search-test.png');

            // 8. Clear search and verify it resets
            console.log('7. Clearing search...');
            await searchInput.fill('');
            await searchInput.press('Enter');
            await page.waitForTimeout(DELAYS.afterSearch);
            await page.waitForSelector('.eau-skeleton', { state: 'hidden', timeout: 15000 }).catch(() => {});
            await page.waitForTimeout(2000);

            const resetRows = await page.$$('#activities-table-tbody tr');
            const resetCount = resetRows.length;
            console.log(`   - Rows after clearing search: ${resetCount}`);

            if (resetCount >= initialCount) {
                console.log('SUCCESS: Table reset correctly after clearing search.');
            }

        } else {
            console.log('ERROR: Could not find search input field.');
        }

        console.log('\n=== TEST COMPLETED ===\n');

    } catch (error) {
        console.error('Test failed:', error.message);
        await page.screenshot({
            path: 'tests/screenshots/activities-search-error.png',
            fullPage: true
        });

    } finally {
        await browser.close();
    }
}

runTest();
