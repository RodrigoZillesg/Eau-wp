const { chromium } = require('playwright');
const fs = require('fs');

const DELAYS = {
    afterNavigation: 3000,
    afterLogin: 5000,
    afterAction: 2000,
};

async function testSidebarMenu() {
    const browser = await chromium.launch({ headless: false });
    const context = await browser.newContext({
        viewport: { width: 1920, height: 1080 }
    });
    const page = await context.newPage();
    
    const screenshotDir = './presentation-screenshots/sidebar-test';
    
    try {
        if (!fs.existsSync(screenshotDir)) {
            fs.mkdirSync(screenshotDir, { recursive: true });
        }
        
        console.log('1. Navegando para login...');
        await page.goto('http://eau-site.local/login/', { waitUntil: 'networkidle' });
        await page.waitForTimeout(DELAYS.afterNavigation);
        await page.screenshot({ path: screenshotDir + '/01-login-page.png', fullPage: true });
        
        console.log('2. Fazendo login...');
        await page.fill('input[name="log"]', 'rrzillesg@gmail.com');
        await page.fill('input[name="pwd"]', 'Pl@ttyPl@tty');
        await page.screenshot({ path: screenshotDir + '/02-login-filled.png', fullPage: true });
        
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle', timeout: 60000 }).catch(() => {}),
            page.click('button[type="submit"]')
        ]);
        
        await page.waitForTimeout(DELAYS.afterLogin);
        console.log('   URL atual: ' + page.url());
        await page.screenshot({ path: screenshotDir + '/03-after-login.png', fullPage: true });
        
        console.log('3. Navegando para o dashboard...');
        await page.goto('http://eau-site.local/dashboard/', { waitUntil: 'networkidle', timeout: 60000 });
        await page.waitForTimeout(DELAYS.afterNavigation);
        console.log('   URL dashboard: ' + page.url());
        await page.screenshot({ path: screenshotDir + '/04-dashboard.png', fullPage: true });
        
        console.log('4. Verificando elementos do sidebar...');
        
        const sidebarSelectors = [
            '.eau-sidebar-menu',
            '.eau-sidebar',
            '.eau-menu',
            '.dashboard-sidebar',
            '[class*="sidebar"]'
        ];
        
        for (const selector of sidebarSelectors) {
            const elements = await page.$$(selector);
            if (elements.length > 0) {
                console.log('   Encontrado: ' + selector + ' (' + elements.length + ' elementos)');
            }
        }
        
        await page.screenshot({ path: screenshotDir + '/05-sidebar-check.png', fullPage: true });
        
        console.log('5. Verificando outras paginas...');
        
        console.log('   Navegando para /dashboard/manage-members/...');
        await page.goto('http://eau-site.local/dashboard/manage-members/', { waitUntil: 'networkidle', timeout: 60000 });
        await page.waitForTimeout(DELAYS.afterNavigation);
        await page.screenshot({ path: screenshotDir + '/06-page-members.png', fullPage: true });
        
        console.log('   Navegando para /dashboard/manage-institutions/...');
        await page.goto('http://eau-site.local/dashboard/manage-institutions/', { waitUntil: 'networkidle', timeout: 60000 });
        await page.waitForTimeout(DELAYS.afterNavigation);
        await page.screenshot({ path: screenshotDir + '/07-page-institutions.png', fullPage: true });
        
        console.log('   Navegando para /dashboard/manage-activities/...');
        await page.goto('http://eau-site.local/dashboard/manage-activities/', { waitUntil: 'networkidle', timeout: 60000 });
        await page.waitForTimeout(DELAYS.afterNavigation);
        await page.screenshot({ path: screenshotDir + '/08-page-activities.png', fullPage: true });
        
        console.log('=== Teste concluido! ===');
        console.log('Screenshots salvos em: ' + screenshotDir);
        
    } catch (error) {
        console.error('Erro durante o teste:', error);
        await page.screenshot({ path: screenshotDir + '/error.png', fullPage: true }).catch(() => {});
    } finally {
        await browser.close();
    }
}

testSidebarMenu();
