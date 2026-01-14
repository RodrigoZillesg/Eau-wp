const { chromium } = require('playwright');

(async () => {
    const browser = await chromium.launch({ headless: false });
    const page = await browser.newPage();
    
    console.log('Navegando para login...');
    await page.goto('http://eau-site.local/login/');
    await page.waitForTimeout(3000);
    
    // Capturar HTML da pagina
    const html = await page.content();
    console.log('\n=== INPUTS NA PAGINA ===');
    
    const inputs = await page.evaluate(() => {
        const allInputs = document.querySelectorAll('input');
        return Array.from(allInputs).map(i => ({
            type: i.type,
            name: i.name,
            id: i.id,
            class: i.className,
            placeholder: i.placeholder
        }));
    });
    
    console.log(JSON.stringify(inputs, null, 2));
    
    // Capturar botoes
    const buttons = await page.evaluate(() => {
        const allBtns = document.querySelectorAll('button, input[type="submit"]');
        return Array.from(allBtns).map(b => ({
            tag: b.tagName,
            type: b.type,
            text: b.textContent?.trim(),
            class: b.className
        }));
    });
    
    console.log('\n=== BOTOES ===');
    console.log(JSON.stringify(buttons, null, 2));
    
    await page.screenshot({ path: 'C:/Users/rrzil/Local Sites/eau-site/login-page.png' });
    console.log('\nScreenshot salvo: login-page.png');
    
    await page.waitForTimeout(2000);
    await browser.close();
})();
