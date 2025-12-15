/**
 * Script para executar o set-test-passwords.php via navegador
 */
const { chromium } = require('playwright');

async function main() {
    console.log('Definindo senhas de teste...\n');

    const browser = await chromium.launch({
        headless: false,
        slowMo: 100
    });

    const context = await browser.newContext({
        viewport: { width: 1920, height: 1080 }
    });

    const page = await context.newPage();

    try {
        // Login como superAdmin
        console.log('1. Fazendo login como superAdmin...');
        await page.goto('http://eau-site.local/login/', { waitUntil: 'domcontentloaded' });
        await page.waitForSelector('input[name="log"]', { timeout: 30000 });
        await page.fill('input[name="log"]', 'rrzillesg@gmail.com');
        await page.fill('input[name="pwd"]', 'Pl@ttyPl@tty');
        await page.click('button[name="wp-submit"]');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(2000);

        // Executar o script PHP
        console.log('2. Executando script de senhas...');
        await page.goto('http://eau-site.local/wp-content/plugins/eau-system/tests/set-test-passwords.php', {
            waitUntil: 'networkidle'
        });
        await page.waitForTimeout(2000);

        // Capturar resultado
        const content = await page.content();
        console.log('\n3. Resultado:');

        // Extrair texto relevante
        const bodyText = await page.textContent('body');
        console.log(bodyText);

        console.log('\nSenhas definidas com sucesso!');

    } catch (error) {
        console.log('Erro:', error.message);
    } finally {
        await browser.close();
    }
}

main();
