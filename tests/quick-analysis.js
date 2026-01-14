const { chromium } = require('playwright');

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
    
    // Analise detalhada
    const analysis = await page.evaluate(() => {
        const header = document.querySelector('.eau-custom-header');
        if (!header) return { error: 'Header nao encontrado' };
        
        const result = {};
        
        // Email
        const headerText = header.textContent;
        const emailMatch = headerText.match(/[\w.+-]+@[\w.-]+\.[a-zA-Z]{2,}/);
        result.email = emailMatch ? emailMatch[0] : 'nao encontrado';
        
        // Logo
        const logo = header.querySelector('img');
        result.logo = logo ? { src: logo.src, exists: true } : { exists: false };
        
        // Linha azul (border ou elemento)
        const style = window.getComputedStyle(header);
        result.borderBottom = style.borderBottom;
        
        // Procurar elemento separador
        const allElements = header.querySelectorAll('*');
        for (let el of allElements) {
            const elStyle = window.getComputedStyle(el);
            const h = parseInt(elStyle.height);
            if (h >= 2 && h <= 5) {
                result.lineElement = {
                    tag: el.tagName,
                    className: el.className,
                    backgroundColor: elStyle.backgroundColor,
                    height: elStyle.height
                };
                break;
            }
        }
        
        // Header rect
        const rect = header.getBoundingClientRect();
        result.headerRect = { top: rect.top, height: rect.height };
        
        // Menu button
        const menuBtn = header.querySelector('button');
        result.menuButton = menuBtn ? true : false;
        
        // Theme header check
        const themeHeader = document.querySelector('#masthead, .site-header:not(.eau-custom-header)');
        if (themeHeader) {
            const thStyle = window.getComputedStyle(themeHeader);
            result.themeHeader = {
                exists: true,
                display: thStyle.display,
                visibility: thStyle.visibility
            };
        } else {
            result.themeHeader = { exists: false };
        }
        
        return result;
    });
    
    console.log('\n=== ANALISE DO HEADER ===\n');
    console.log(JSON.stringify(analysis, null, 2));
    
    await page.waitForTimeout(2000);
    await browser.close();
})();
