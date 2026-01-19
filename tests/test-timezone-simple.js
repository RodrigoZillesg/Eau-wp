const { chromium } = require('playwright');

(async () => {
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage({ viewport: { width: 1920, height: 1080 } });

    // Login
    await page.goto('http://eau-site.local/login/');
    await page.fill('input[name="log"]', 'rrzillesg@gmail.com');
    await page.fill('input[name="pwd"]', 'Pl@ttyPl@tty');
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(3000);

    // Go to events archive and get first event link
    await page.goto('http://eau-site.local/events/');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(3000);

    // Try to find event links
    const links = await page.$$eval('a[href*="/events/"]', els =>
        els.filter(el => el.href.match(/\/events\/[^\/]+\/?$/))
           .slice(0, 5)
           .map(el => el.href)
    );

    console.log('Event links found:', links.length);
    if (links.length > 0) {
        console.log('First event:', links[0]);

        // Navigate to first event
        await page.goto(links[0]);
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(2000);

        // Check timezone elements
        const timezoneAbbr = await page.$('.eau-event-timezone');
        const timezoneFull = await page.$('.eau-event-info-timezone-full');

        if (timezoneAbbr) {
            const text = await timezoneAbbr.textContent();
            console.log('Timezone abbreviation:', text);
        } else {
            console.log('Timezone abbreviation element NOT FOUND');
        }

        if (timezoneFull) {
            const text = await timezoneFull.textContent();
            console.log('Timezone full name:', text);
        } else {
            console.log('Timezone full name element NOT FOUND');
        }

        // Check the info-subvalue content directly
        const subvalue = await page.$('.eau-event-info-subvalue');
        if (subvalue) {
            const html = await subvalue.innerHTML();
            console.log('Subvalue HTML:', html);
        }
    }

    await browser.close();
    console.log('Done!');
})();
