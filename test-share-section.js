const { chromium } = require('playwright');

(async () => {
    const browser = await chromium.launch({ headless: false });
    const context = await browser.newContext({
        viewport: { width: 1920, height: 1080 },
        bypassCSP: true
    });
    const page = await context.newPage();

    // Disable cache
    await page.route('**/*', route => {
        route.continue({
            headers: {
                ...route.request().headers(),
                'Cache-Control': 'no-cache, no-store, must-revalidate',
                'Pragma': 'no-cache',
                'Expires': '0'
            }
        });
    });
    
    try {
        // Login
        console.log('Navigating to login...');
        await page.goto('http://eau-site.local/login/', { waitUntil: 'networkidle' });
        
        // Wait for page to load and take screenshot to see what's there
        await page.waitForTimeout(2000);
        await page.screenshot({ path: 'test-screenshots/login-page.png' });
        console.log('Login page screenshot saved');
        
        // Try different selectors
        const usernameInput = await page.$('input[name="log"], input[name="username"], input[type="text"]');
        const passwordInput = await page.$('input[name="pwd"], input[name="password"], input[type="password"]');
        
        if (usernameInput && passwordInput) {
            await usernameInput.fill('rrzillesg@gmail.com');
            await passwordInput.fill('Pl@ttyPl@tty');
            
            const submitBtn = await page.$('button[type="submit"], input[type="submit"]');
            if (submitBtn) {
                await submitBtn.click();
            }
            
            await page.waitForLoadState('networkidle');
            await page.waitForTimeout(2000);
            
            console.log('Logged in, navigating to dashboard...');
            await page.goto('http://eau-site.local/dashboard/', { waitUntil: 'networkidle' });
            await page.waitForTimeout(3000);
            
            // Check share section and SVGs
            const shareSection = await page.$('.eau-share-registration-section');
            console.log('Share section found:', !!shareSection);

            const shareButtons = await page.$$('.eau-share-btn');
            console.log('Share buttons found:', shareButtons.length);

            // Check SVG content and computed styles
            const svgContent = await page.evaluate(() => {
                const btns = document.querySelectorAll('.eau-share-btn');
                return Array.from(btns).map(btn => {
                    const svg = btn.querySelector('svg');
                    const path = svg?.querySelector('path');
                    const svgStyles = svg ? window.getComputedStyle(svg) : null;
                    const pathStyles = path ? window.getComputedStyle(path) : null;
                    return {
                        class: btn.className,
                        hasSvg: !!svg,
                        svgFill: svg?.getAttribute('fill'),
                        svgWidth: svgStyles?.width,
                        svgHeight: svgStyles?.height,
                        svgDisplay: svgStyles?.display,
                        svgVisibility: svgStyles?.visibility,
                        svgOpacity: svgStyles?.opacity,
                        pathFill: pathStyles?.fill,
                        pathDisplay: pathStyles?.display,
                        btnOverflow: window.getComputedStyle(btn).overflow
                    };
                });
            });
            console.log('SVG detailed info:', JSON.stringify(svgContent, null, 2));

            // Screenshot full dashboard
            await page.screenshot({ path: 'test-screenshots/dashboard-full.png', fullPage: true });
            console.log('Full dashboard screenshot saved');
            
            // Mobile view
            await page.setViewportSize({ width: 375, height: 812 });
            await page.waitForTimeout(1000);
            await page.screenshot({ path: 'test-screenshots/dashboard-mobile.png', fullPage: true });
            console.log('Mobile screenshot saved');
        } else {
            console.log('Could not find login inputs');
        }
        
        console.log('Test completed!');
    } catch (error) {
        console.error('Error:', error.message);
        await page.screenshot({ path: 'test-screenshots/error.png' });
    } finally {
        await browser.close();
    }
})();
