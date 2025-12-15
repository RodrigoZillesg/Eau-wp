/**
 * Script para capturar screenshots de todos os tipos de usuários
 */
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

// Diretório base para screenshots
const baseDir = path.join(__dirname, 'presentation-screenshots');

// Configuração de usuários
const users = {
    Admin: {
        email: 'rachelwinton@englishaustralia.com.au',
        password: 'TestPass2024!',
        pages: [
            { name: '01-dashboard', url: '/dashboard/', description: 'Dashboard Principal' },
            { name: '02-members', url: '/dashboard/members/', description: 'Members Management' },
            { name: '03-institutions', url: '/dashboard/manage-institutions/', description: 'Institutions Management' },
            { name: '04-activities', url: '/dashboard/manage-activities/', description: 'CPD Activities Management' },
            { name: '05-events-admin', url: '/dashboard/events/', description: 'Events Management' },
            { name: '06-payments', url: '/dashboard/payments/', description: 'Payments Management' },
            { name: '07-membership-applications', url: '/dashboard/membership-applications/', description: 'Membership Applications' },
            { name: '08-my-profile', url: '/dashboard/profile/', description: 'My Profile' },
            { name: '09-events-frontend', url: '/events/', description: 'Events (Frontend)' },
        ]
    },
    institutionAdmin: {
        email: 'a.jamal@c2cglobal.com.au',
        password: 'TestPass2024!',
        pages: [
            { name: '01-dashboard', url: '/dashboard/', description: 'Dashboard Principal' },
            { name: '02-my-institution', url: '/dashboard/my-institution/', description: 'My Institution' },
            { name: '03-my-profile', url: '/dashboard/profile/', description: 'My Profile' },
            { name: '04-my-cpds', url: '/dashboard/my-cpds/', description: 'My CPDs' },
            { name: '05-events-frontend', url: '/events/', description: 'Events (Frontend)' },
            { name: '06-courses', url: '/dashboard/courses/', description: 'OpenLearning Courses' },
        ]
    },
    Member: {
        email: 'aakanksha.mishra@sydney.edu.au',
        password: 'TestPass2024!',
        pages: [
            { name: '01-dashboard', url: '/dashboard/', description: 'Dashboard Principal' },
            { name: '02-my-profile', url: '/dashboard/profile/', description: 'My Profile' },
            { name: '03-my-cpds', url: '/dashboard/my-cpds/', description: 'My CPDs' },
            { name: '04-membership-selection', url: '/membership-selection/', description: 'Membership Selection' },
            { name: '05-events-frontend', url: '/events/', description: 'Events (Frontend)' },
            { name: '06-courses', url: '/dashboard/courses/', description: 'OpenLearning Courses' },
        ]
    }
};

async function login(page, email, password) {
    console.log(`      Fazendo login com ${email}...`);
    await page.goto('http://eau-site.local/login/', { waitUntil: 'domcontentloaded', timeout: 60000 });
    await page.waitForSelector('input[name="log"]', { timeout: 30000 });
    await page.fill('input[name="log"]', email);
    await page.fill('input[name="pwd"]', password);
    await page.click('button[name="wp-submit"]');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);
}

async function logout(page) {
    console.log('      Fazendo logout...');
    await page.goto('http://eau-site.local/wp-login.php?action=logout', { waitUntil: 'networkidle' });
    await page.waitForTimeout(1000);

    const logoutLink = page.locator('a:has-text("log out")');
    if (await logoutLink.isVisible({ timeout: 3000 }).catch(() => false)) {
        await logoutLink.click();
        await page.waitForLoadState('networkidle');
    }
    await page.waitForTimeout(1000);
}

async function captureScreenshots(page, userType, userPages, folder) {
    const userFolder = path.join(folder, userType);
    if (!fs.existsSync(userFolder)) {
        fs.mkdirSync(userFolder, { recursive: true });
    }

    let success = 0;
    let failed = 0;

    for (const pageInfo of userPages) {
        try {
            console.log(`      Capturando: ${pageInfo.description}...`);
            await page.goto(`http://eau-site.local${pageInfo.url}`, {
                waitUntil: 'networkidle',
                timeout: 60000
            });
            await page.waitForTimeout(3000);

            await page.evaluate(() => window.scrollTo(0, 0));
            await page.waitForTimeout(500);

            const screenshotPath = path.join(userFolder, `${pageInfo.name}.png`);
            await page.screenshot({
                path: screenshotPath,
                fullPage: true
            });
            console.log(`      OK: ${pageInfo.name}.png`);
            success++;
        } catch (error) {
            console.log(`      ERRO em ${pageInfo.name}: ${error.message}`);
            failed++;
        }
    }

    return { success, failed };
}

async function main() {
    console.log('='.repeat(60));
    console.log('CAPTURANDO SCREENSHOTS - TODOS OS TIPOS DE USUÁRIO');
    console.log('='.repeat(60));

    if (!fs.existsSync(baseDir)) {
        fs.mkdirSync(baseDir, { recursive: true });
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

    const results = {};

    try {
        for (const [userType, config] of Object.entries(users)) {
            console.log(`\n${'='.repeat(60)}`);
            console.log(`   USUÁRIO: ${userType.toUpperCase()}`);
            console.log(`${'='.repeat(60)}`);

            await login(page, config.email, config.password);
            results[userType] = await captureScreenshots(page, userType, config.pages, baseDir);
            await logout(page);

            console.log(`   ${userType}: ${results[userType].success} OK, ${results[userType].failed} FALHAS`);
        }

        console.log('\n' + '='.repeat(60));
        console.log('RESUMO FINAL');
        console.log('='.repeat(60));
        for (const [userType, result] of Object.entries(results)) {
            console.log(`   ${userType}: ${result.success} OK, ${result.failed} FALHAS`);
        }
        console.log('\nScreenshots salvos em:', baseDir);
        console.log('='.repeat(60));

    } catch (error) {
        console.log('ERRO GERAL:', error.message);
    } finally {
        await browser.close();
    }
}

main();
