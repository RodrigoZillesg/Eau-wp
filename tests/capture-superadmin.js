/**
 * Script para capturar screenshots do superAdmin
 */
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

// Diretório para screenshots
const screenshotsDir = path.join(__dirname, 'presentation-screenshots', 'superAdmin');

// Páginas para capturar
const pages = [
    { name: '01-dashboard', url: '/dashboard/', description: 'Dashboard Principal', wait: 3000 },
    { name: '02-members', url: '/dashboard/members/', description: 'Members Management', wait: 3000 },
    { name: '03-institutions', url: '/dashboard/manage-institutions/', description: 'Institutions Management', wait: 3000 },
    { name: '04-activities', url: '/dashboard/manage-activities/', description: 'CPD Activities Management', wait: 3000 },
    { name: '05-categories', url: '/dashboard/manage-categories/', description: 'CPD Categories Management', wait: 3000 },
    { name: '06-events-admin', url: '/dashboard/events/', description: 'Events Management (Admin)', wait: 3000 },
    { name: '07-payments', url: '/dashboard/payments/', description: 'Payments Management', wait: 3000 },
    { name: '08-membership-applications', url: '/dashboard/membership-applications/', description: 'Membership Applications', wait: 3000 },
    { name: '09-my-profile', url: '/dashboard/profile/', description: 'My Profile', wait: 3000 },
    { name: '10-my-cpds', url: '/dashboard/my-cpds/', description: 'My CPDs', wait: 3000 },
    { name: '11-events-frontend', url: '/events/', description: 'Events (Frontend)', wait: 3000 },
    { name: '12-courses', url: '/dashboard/courses/', description: 'OpenLearning Courses', wait: 3000 },
];

async function login(page) {
    console.log('   Fazendo login como superAdmin...');
    await page.goto('http://eau-site.local/login/', { waitUntil: 'domcontentloaded', timeout: 60000 });
    await page.waitForSelector('input[name="log"]', { timeout: 30000 });
    await page.fill('input[name="log"]', 'rrzillesg@gmail.com');
    await page.fill('input[name="pwd"]', 'Pl@ttyPl@tty');
    await page.click('button[name="wp-submit"]');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);
}

async function captureScreenshot(page, pageInfo) {
    try {
        console.log(`   Capturando: ${pageInfo.description}...`);
        await page.goto(`http://eau-site.local${pageInfo.url}`, {
            waitUntil: 'networkidle',
            timeout: 60000
        });
        await page.waitForTimeout(pageInfo.wait || 2000);

        // Scroll para carregar lazy content
        await page.evaluate(() => window.scrollTo(0, 0));
        await page.waitForTimeout(500);

        const screenshotPath = path.join(screenshotsDir, `${pageInfo.name}.png`);
        await page.screenshot({
            path: screenshotPath,
            fullPage: true
        });
        console.log(`   OK: ${pageInfo.name}.png`);
        return true;
    } catch (error) {
        console.log(`   ERRO em ${pageInfo.name}: ${error.message}`);
        return false;
    }
}

async function main() {
    console.log('='.repeat(60));
    console.log('CAPTURANDO SCREENSHOTS - SUPERADMIN');
    console.log('='.repeat(60));

    // Criar diretório
    if (!fs.existsSync(screenshotsDir)) {
        fs.mkdirSync(screenshotsDir, { recursive: true });
    }

    const browser = await chromium.launch({
        headless: false,
        slowMo: 50
    });

    const context = await browser.newContext({
        viewport: { width: 1920, height: 1080 }
    });

    const page = await context.newPage();
    page.setDefaultTimeout(60000);

    try {
        await login(page);

        let success = 0;
        let failed = 0;

        for (const pageInfo of pages) {
            const result = await captureScreenshot(page, pageInfo);
            if (result) success++;
            else failed++;
        }

        console.log('\n' + '='.repeat(60));
        console.log(`RESULTADO: ${success} OK, ${failed} FALHAS`);
        console.log('Screenshots salvos em:', screenshotsDir);
        console.log('='.repeat(60));

    } catch (error) {
        console.log('ERRO:', error.message);
    } finally {
        await browser.close();
    }
}

main();
