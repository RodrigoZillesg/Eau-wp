const { chromium } = require('playwright');

(async () => {
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage({ viewport: { width: 1920, height: 1080 } });

    // Login
    await page.goto('http://eau-site.local/login/');
    await page.waitForLoadState('networkidle');
    const usernameField = await page.$('input[name="log"]') || await page.$('input[type="text"]');
    const passwordField = await page.$('input[name="pwd"]') || await page.$('input[type="password"]');
    if (usernameField && passwordField) {
        await usernameField.fill('rrzillesg@gmail.com');
        await passwordField.fill('Pl@ttyPl@tty');
        const submitBtn = await page.$('button[type="submit"]') || await page.$('input[type="submit"]');
        if (submitBtn) await submitBtn.click();
        await page.waitForLoadState('networkidle');
    }

    // Acessar eventos
    await page.goto('http://eau-site.local/events/');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1000);

    // Verificar CSS das imagens
    const imageData = await page.evaluate(() => {
        const cards = document.querySelectorAll('.eau-event-card');
        const results = [];
        
        cards.forEach((card, i) => {
            if (i > 2) return; // Apenas primeiros 3
            
            const imageContainer = card.querySelector('.eau-event-card-image');
            const img = card.querySelector('.eau-event-card-image img');
            
            if (imageContainer && img) {
                const containerStyle = window.getComputedStyle(imageContainer);
                const imgStyle = window.getComputedStyle(img);
                
                results.push({
                    cardIndex: i,
                    container: {
                        width: containerStyle.width,
                        height: containerStyle.height,
                        paddingTop: containerStyle.paddingTop,
                        position: containerStyle.position,
                        overflow: containerStyle.overflow
                    },
                    img: {
                        width: imgStyle.width,
                        height: imgStyle.height,
                        objectFit: imgStyle.objectFit,
                        objectPosition: imgStyle.objectPosition,
                        position: imgStyle.position,
                        top: imgStyle.top,
                        left: imgStyle.left,
                        naturalWidth: img.naturalWidth,
                        naturalHeight: img.naturalHeight,
                        src: img.src.substring(0, 80)
                    }
                });
            }
        });
        
        return results;
    });

    console.log('=== ANÁLISE DO CSS DAS IMAGENS ===\n');
    imageData.forEach(data => {
        console.log(`Card ${data.cardIndex}:`);
        console.log('  Container:', JSON.stringify(data.container, null, 4));
        console.log('  Image:', JSON.stringify(data.img, null, 4));
        console.log('');
    });

    await browser.close();
})();
