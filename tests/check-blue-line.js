const { chromium } = require('playwright');
const path = require('path');

(async () => {
    const browser = await chromium.launch({ headless: false });
    const context = await browser.newContext({ viewport: { width: 1920, height: 1080 } });
    const page = await context.newPage();
    
    // Login
    await page.goto('http://eau-site.local/login/');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1500);
    await page.fill('input[name="log"]', 'rrzillesg@gmail.com');
    await page.fill('input[name="pwd"]', 'Pl@ttyPl@tty');
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2500);
    
    // Dashboard
    await page.goto('http://eau-site.local/dashboard/');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(3000);
    
    // Check linha azul especificamente
    const lineCheck = await page.evaluate(() => {
        const line = document.querySelector('.eau-app-header-line');
        if (!line) return { found: false };
        
        const style = window.getComputedStyle(line);
        const rect = line.getBoundingClientRect();
        
        return {
            found: true,
            className: line.className,
            rect: {
                top: rect.top,
                left: rect.left,
                width: rect.width,
                height: rect.height
            },
            styles: {
                backgroundColor: style.backgroundColor,
                backgroundImage: style.backgroundImage,
                background: style.background,
                borderTop: style.borderTop,
                borderBottom: style.borderBottom
            }
        };
    });
    
    console.log('\n=== VERIFICACAO DA LINHA AZUL ===\n');
    console.log(JSON.stringify(lineCheck, null, 2));
    
    // Screenshot focado no header
    const headerElement = await page.$('.eau-custom-header');
    if (headerElement) {
        const headerScreenshot = path.join(__dirname, 'header-only-screenshot.png');
        await headerElement.screenshot({ path: headerScreenshot });
        console.log('\nScreenshot do header: ' + headerScreenshot);
    }
    
    await page.waitForTimeout(2000);
    await browser.close();
})();
