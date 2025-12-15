const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');

(async () => {
    const browser = await chromium.launch({ headless: false });
    const context = await browser.newContext({ viewport: { width: 1920, height: 1080 } });
    const page = await context.newPage();
    page.setDefaultTimeout(60000); // 60 seconds

    const screenshotsDir = path.join(__dirname, 'presentation-screenshots', 'features');

    // Ensure directory exists
    if (!fs.existsSync(screenshotsDir)) {
        fs.mkdirSync(screenshotsDir, { recursive: true });
    }

    async function safeScreenshot(name, fullPage = false) {
        try {
            await page.screenshot({
                path: path.join(screenshotsDir, name),
                fullPage: fullPage
            });
            console.log(`   OK: ${name}`);
            return true;
        } catch (e) {
            console.log(`   ERRO ao capturar ${name}: ${e.message}`);
            return false;
        }
    }

    async function safeGoto(url) {
        try {
            await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 30000 });
            await page.waitForTimeout(2000);
            return true;
        } catch (e) {
            console.log(`   ERRO ao navegar para ${url}: ${e.message}`);
            return false;
        }
    }

    try {
        // 1. Login as SuperAdmin
        console.log('1. Fazendo login...');
        await page.goto('http://eau-site.local/login/', { waitUntil: 'domcontentloaded' });
        await page.waitForTimeout(1000);

        await page.fill('input[name="log"], input#user_login', 'rrzillesg@gmail.com');
        await page.fill('input[name="pwd"], input#user_pass', 'Pl@ttyPl@tty');
        await page.click('button[type="submit"], input[type="submit"]');
        await page.waitForTimeout(3000);
        console.log('   Login OK');

        // INSTITUTIONS
        console.log('\n=== INSTITUTIONS ===');
        if (await safeGoto('http://eau-site.local/admin/institutions/')) {
            await page.waitForTimeout(2000);
            await safeScreenshot('institutions-01-table.png');
        }

        // ACTIVITIES
        console.log('\n=== ACTIVITIES ===');
        if (await safeGoto('http://eau-site.local/admin/activities/')) {
            await page.waitForTimeout(2000);
            await safeScreenshot('activities-01-table.png');
        }

        // CATEGORIES
        console.log('\n=== CATEGORIES ===');
        if (await safeGoto('http://eau-site.local/admin/categories/')) {
            await page.waitForTimeout(2000);
            await safeScreenshot('categories-01-table.png');
        }

        // EVENTS ADMIN
        console.log('\n=== EVENTS ADMIN ===');
        if (await safeGoto('http://eau-site.local/dashboard/events/')) {
            await page.waitForTimeout(2000);
            await safeScreenshot('events-admin-01-table.png');
        }

        // PAYMENTS
        console.log('\n=== PAYMENTS ===');
        if (await safeGoto('http://eau-site.local/dashboard/payments/')) {
            await page.waitForTimeout(2000);
            await safeScreenshot('payments-01-table.png');
        }

        // MEMBERSHIP APPLICATIONS
        console.log('\n=== MEMBERSHIP APPLICATIONS ===');
        if (await safeGoto('http://eau-site.local/dashboard/membership-applications/')) {
            await page.waitForTimeout(2000);
            await safeScreenshot('membership-apps-01-table.png');
        }

        // OPENLEARNING MANAGEMENT
        console.log('\n=== OPENLEARNING MANAGEMENT ===');
        if (await safeGoto('http://eau-site.local/dashboard/openlearning-management/')) {
            await page.waitForTimeout(2000);
            await safeScreenshot('openlearning-mgmt-01-table.png');
        }

        // DUPLICATE MANAGER
        console.log('\n=== DUPLICATE MANAGER ===');
        if (await safeGoto('http://eau-site.local/dashboard/merge-members/')) {
            await page.waitForTimeout(2000);
            await safeScreenshot('duplicates-01-table.png');
        }

        // SETTINGS
        console.log('\n=== SETTINGS ===');
        if (await safeGoto('http://eau-site.local/dashboard/settings/')) {
            await page.waitForTimeout(2000);
            await safeScreenshot('settings-01-main.png');
        }

        // MY PROFILE
        console.log('\n=== MY PROFILE ===');
        if (await safeGoto('http://eau-site.local/dashboard/my-profile/')) {
            await page.waitForTimeout(2000);
            await safeScreenshot('my-profile-01-main.png');
        }

        // MY CPDS
        console.log('\n=== MY CPDS ===');
        if (await safeGoto('http://eau-site.local/dashboard/my-cpds/')) {
            await page.waitForTimeout(2000);
            await safeScreenshot('my-cpds-01-main.png');
        }

        // MY INSTITUTION
        console.log('\n=== MY INSTITUTION ===');
        if (await safeGoto('http://eau-site.local/dashboard/my-institution/')) {
            await page.waitForTimeout(2000);
            await safeScreenshot('my-institution-01-main.png');
        }

        // OPENLEARNING COURSES
        console.log('\n=== OPENLEARNING COURSES ===');
        if (await safeGoto('http://eau-site.local/dashboard/courses/')) {
            await page.waitForTimeout(2000);
            await safeScreenshot('openlearning-courses-01-main.png');
        }

        // EVENTS FRONTEND
        console.log('\n=== EVENTS FRONTEND ===');
        if (await safeGoto('http://eau-site.local/events/')) {
            await page.waitForTimeout(2000);
            await safeScreenshot('events-frontend-01-list.png');
        }

        // MEMBERSHIP SELECTION (already public)
        console.log('\n=== MEMBERSHIP SELECTION ===');
        if (await safeGoto('http://eau-site.local/membership-selection/')) {
            await page.waitForTimeout(2000);
            await safeScreenshot('membership-selection-01-types.png');
        }

        // REGISTRATION (logout first)
        console.log('\n=== REGISTRATION ===');
        await page.goto('http://eau-site.local/wp-login.php?action=logout', { waitUntil: 'domcontentloaded' });
        await page.waitForTimeout(1000);
        try {
            await page.click('a:has-text("log out")', { timeout: 5000 });
            await page.waitForTimeout(2000);
        } catch (e) {}

        if (await safeGoto('http://eau-site.local/register/')) {
            await page.waitForTimeout(2000);
            await safeScreenshot('registration-01-form.png');
        }

        console.log('\n========================================');
        console.log('Captura de screenshots concluída!');
        console.log('========================================');

    } catch (error) {
        console.error('Erro geral:', error.message);
    }

    await page.waitForTimeout(2000);
    await browser.close();
})();
