/**
 * Script para capturar screenshots das páginas problemáticas
 * com debug detalhado para identificar problemas
 */

const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');

(async () => {
    const browser = await chromium.launch({ headless: false });
    const context = await browser.newContext({ viewport: { width: 1920, height: 1080 } });
    const page = await context.newPage();
    page.setDefaultTimeout(60000);

    const screenshotsDir = path.join(__dirname, 'presentation-screenshots', 'features');

    // Helper para debug
    async function debugPage(name) {
        const url = page.url();
        const title = await page.title();
        console.log(`   URL atual: ${url}`);
        console.log(`   Título: ${title}`);
    }

    try {
        // ============================================
        // LOGIN
        // ============================================
        console.log('='.repeat(60));
        console.log('1. Fazendo login...');
        await page.goto('http://eau-site.local/login/', { waitUntil: 'networkidle' });
        await page.waitForTimeout(2000);

        await page.fill('input[name="log"], input#user_login', 'rrzillesg@gmail.com');
        await page.fill('input[name="pwd"], input#user_pass', 'Pl@ttyPl@tty');
        await page.click('button[type="submit"], input[type="submit"]');

        // Esperar redirecionamento completo após login
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(5000);

        console.log('   Login concluído');
        await debugPage('Após login');

        // ============================================
        // TESTANDO DIFERENTES URLs PARA INSTITUTIONS
        // ============================================
        console.log('\n' + '='.repeat(60));
        console.log('2. INSTITUTIONS MANAGEMENT');
        console.log('='.repeat(60));

        // Testar várias URLs possíveis
        const institutionUrls = [
            'http://eau-site.local/admin/institutions/',
            'http://eau-site.local/dashboard/manage-institutions/',
            'http://eau-site.local/dashboard/institutions/',
        ];

        for (const url of institutionUrls) {
            console.log(`\n   Tentando: ${url}`);
            await page.goto(url, { waitUntil: 'networkidle' });
            await page.waitForTimeout(5000);
            await debugPage('Institutions');

            // Verificar se tem conteúdo de institutions
            const hasTable = await page.locator('.eau-data-table, .eau-table, table').first().isVisible().catch(() => false);
            const hasStats = await page.locator('.eau-stats-cards, .eau-stat-card').first().isVisible().catch(() => false);
            console.log(`   Tem tabela: ${hasTable}, Tem stats: ${hasStats}`);

            if (hasTable || hasStats) {
                console.log('   >>> URL CORRETA ENCONTRADA!');
                await page.waitForTimeout(3000);
                await page.screenshot({ path: path.join(screenshotsDir, 'institutions-01-table.png') });
                console.log('   Screenshot salvo');
                break;
            }
        }

        // ============================================
        // TESTANDO DIFERENTES URLs PARA ACTIVITIES
        // ============================================
        console.log('\n' + '='.repeat(60));
        console.log('3. ACTIVITIES MANAGEMENT');
        console.log('='.repeat(60));

        const activitiesUrls = [
            'http://eau-site.local/admin/activities/',
            'http://eau-site.local/dashboard/manage-activities/',
            'http://eau-site.local/dashboard/activities/',
        ];

        for (const url of activitiesUrls) {
            console.log(`\n   Tentando: ${url}`);
            await page.goto(url, { waitUntil: 'networkidle' });
            await page.waitForTimeout(5000);
            await debugPage('Activities');

            const hasTable = await page.locator('.eau-data-table, .eau-table, table').first().isVisible().catch(() => false);
            const hasStats = await page.locator('.eau-stats-cards, .eau-stat-card').first().isVisible().catch(() => false);
            console.log(`   Tem tabela: ${hasTable}, Tem stats: ${hasStats}`);

            if (hasTable || hasStats) {
                console.log('   >>> URL CORRETA ENCONTRADA!');
                await page.waitForTimeout(3000);
                await page.screenshot({ path: path.join(screenshotsDir, 'activities-01-table.png') });
                console.log('   Screenshot salvo');
                break;
            }
        }

        // ============================================
        // TESTANDO DIFERENTES URLs PARA CATEGORIES
        // ============================================
        console.log('\n' + '='.repeat(60));
        console.log('4. CATEGORIES MANAGEMENT');
        console.log('='.repeat(60));

        const categoriesUrls = [
            'http://eau-site.local/admin/categories/',
            'http://eau-site.local/dashboard/manage-categories/',
            'http://eau-site.local/dashboard/categories/',
        ];

        for (const url of categoriesUrls) {
            console.log(`\n   Tentando: ${url}`);
            await page.goto(url, { waitUntil: 'networkidle' });
            await page.waitForTimeout(5000);
            await debugPage('Categories');

            const hasTable = await page.locator('.eau-data-table, .eau-table, table').first().isVisible().catch(() => false);
            const hasStats = await page.locator('.eau-stats-cards, .eau-stat-card').first().isVisible().catch(() => false);
            console.log(`   Tem tabela: ${hasTable}, Tem stats: ${hasStats}`);

            if (hasTable || hasStats) {
                console.log('   >>> URL CORRETA ENCONTRADA!');
                await page.waitForTimeout(3000);
                await page.screenshot({ path: path.join(screenshotsDir, 'categories-01-table.png') });
                console.log('   Screenshot salvo');
                break;
            }
        }

        // ============================================
        // TESTANDO DIFERENTES URLs PARA MY PROFILE
        // ============================================
        console.log('\n' + '='.repeat(60));
        console.log('5. MY PROFILE');
        console.log('='.repeat(60));

        const profileUrls = [
            'http://eau-site.local/dashboard/my-profile/',
            'http://eau-site.local/my-profile/',
            'http://eau-site.local/profile/',
        ];

        for (const url of profileUrls) {
            console.log(`\n   Tentando: ${url}`);
            await page.goto(url, { waitUntil: 'networkidle' });
            await page.waitForTimeout(5000);
            await debugPage('My Profile');

            // Verificar se tem conteúdo de profile
            const hasProfileHeader = await page.locator('.eau-profile-header, .profile-header, .eau-my-profile').first().isVisible().catch(() => false);
            const hasProfileSection = await page.locator('.eau-profile-section, .eau-collapsible-section').first().isVisible().catch(() => false);
            console.log(`   Tem header: ${hasProfileHeader}, Tem seção: ${hasProfileSection}`);

            if (hasProfileHeader || hasProfileSection) {
                console.log('   >>> URL CORRETA ENCONTRADA!');
                await page.waitForTimeout(3000);
                await page.screenshot({ path: path.join(screenshotsDir, 'my-profile-01-main.png') });
                console.log('   Screenshot salvo');
                break;
            }
        }

        console.log('\n' + '='.repeat(60));
        console.log('CAPTURA CONCLUÍDA');
        console.log('='.repeat(60));

    } catch (error) {
        console.error('\nERRO:', error.message);
        await page.screenshot({ path: path.join(screenshotsDir, 'debug-error.png') });
    }

    await page.waitForTimeout(3000);
    await browser.close();
})();
