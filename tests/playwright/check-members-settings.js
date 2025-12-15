/**
 * Script para verificar configurações do campo Tags em Members Settings
 */

const { chromium } = require('playwright');
const path = require('path');

(async () => {
    const browser = await chromium.launch({
        headless: false,
        slowMo: 500
    });

    const context = await browser.newContext({
        viewport: { width: 1920, height: 1080 }
    });

    const page = await context.newPage();

    try {
        console.log('1. Navegando para página de login do wp-admin...');
        await page.goto('http://eau-site.local/wp-admin/');
        await page.waitForLoadState('networkidle');

        console.log('2. Fazendo login como admin...');
        // Tentar preencher o campo username (pode ser #user_login no wp-admin)
        const usernameField = await page.locator('#user_login').first();
        if (await usernameField.isVisible()) {
            await usernameField.fill('rrzillesg@gmail.com');
        } else {
            // Se não encontrar, tentar pelo placeholder
            await page.fill('input[placeholder="Username"]', 'rrzillesg@gmail.com');
        }

        // Tentar preencher password
        const passwordField = await page.locator('#user_pass').first();
        if (await passwordField.isVisible()) {
            await passwordField.fill('Pl@ttyPl@tty');
        } else {
            await page.fill('input[placeholder="Password"]', 'Pl@ttyPl@tty');
        }

        // Clicar no botão de login
        await page.click('button:has-text("Log In"), input[type="submit"][value="Log In"]');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(2000);

        console.log('3. Navegando para Members Settings...');
        await page.goto('http://eau-site.local/wp-admin/admin.php?page=eau-members-settings');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(2000);

        console.log('4. Capturando screenshot da página completa...');
        const screenshotPath = path.join(__dirname, '..', '..', '..', '..', '..', '..', 'screenshot-members-settings-full.png');
        await page.screenshot({
            path: screenshotPath,
            fullPage: true
        });
        console.log(`Screenshot salvo em: ${screenshotPath}`);

        // Tentar localizar o campo Tags
        console.log('5. Procurando pelo campo Tags...');
        const tagsElements = await page.locator('text=/tags/i').all();
        console.log(`Encontrados ${tagsElements.length} elementos contendo "tags"`);

        // Capturar screenshot específico se encontrar
        if (tagsElements.length > 0) {
            const tagsScreenshot = path.join(__dirname, '..', '..', '..', '..', '..', '..', 'screenshot-tags-field.png');
            await tagsElements[0].screenshot({ path: tagsScreenshot });
            console.log(`Screenshot do campo Tags salvo em: ${tagsScreenshot}`);
        }

        console.log('\n✅ Script concluído com sucesso!');

    } catch (error) {
        console.error('❌ Erro ao executar o script:', error);

        // Screenshot de erro
        const errorScreenshot = path.join(__dirname, '..', '..', '..', '..', '..', '..', 'screenshot-error.png');
        await page.screenshot({ path: errorScreenshot, fullPage: true });
        console.log(`Screenshot de erro salvo em: ${errorScreenshot}`);
    } finally {
        await browser.close();
    }
})();
