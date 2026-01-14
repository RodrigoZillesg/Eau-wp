/**
 * Teste do Institution Import
 *
 * Testa a UI de importação de instituições na página Settings.
 */

const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const SCREENSHOTS_DIR = './institution-import-screenshots';

function ensureDir(dir) {
    if (!fs.existsSync(dir)) {
        fs.mkdirSync(dir, { recursive: true });
    }
}

async function saveScreenshot(page, name) {
    ensureDir(SCREENSHOTS_DIR);
    const filepath = path.join(SCREENSHOTS_DIR, `${name}.png`);
    await page.screenshot({ path: filepath, fullPage: true });
    console.log(`[Screenshot] ${name}.png`);
}

async function main() {
    console.log('='.repeat(60));
    console.log('Teste de Importação de Instituições');
    console.log('='.repeat(60));

    const browser = await chromium.launch({ headless: false, slowMo: 200 });
    const context = await browser.newContext({ viewport: { width: 1920, height: 1080 } });
    const page = await context.newPage();

    try {
        // 1. Login
        console.log('\n[1] Fazendo login...');
        await page.goto('http://eau-site.local/login/', { waitUntil: 'networkidle', timeout: 60000 });

        // Try different selectors for the login form
        const usernameSelectors = ['#username', 'input[name="log"]', 'input[name="username"]', 'input[type="text"]'];
        const passwordSelectors = ['#password', 'input[name="pwd"]', 'input[name="password"]', 'input[type="password"]'];
        const submitSelectors = ['button[type="submit"]', 'input[type="submit"]', '#wp-submit'];

        let usernameField = null;
        let passwordField = null;
        let submitBtn = null;

        for (const sel of usernameSelectors) {
            usernameField = await page.$(sel);
            if (usernameField) {
                console.log('  Username field encontrado:', sel);
                break;
            }
        }

        for (const sel of passwordSelectors) {
            passwordField = await page.$(sel);
            if (passwordField) {
                console.log('  Password field encontrado:', sel);
                break;
            }
        }

        for (const sel of submitSelectors) {
            submitBtn = await page.$(sel);
            if (submitBtn) {
                console.log('  Submit button encontrado:', sel);
                break;
            }
        }

        if (usernameField && passwordField && submitBtn) {
            await usernameField.fill('rrzillesg@gmail.com');
            await passwordField.fill('Pl@ttyPl@tty');
            await submitBtn.click();
            await page.waitForLoadState('networkidle');
            await page.waitForTimeout(3000);
        } else {
            console.log('  Campos de login não encontrados, tentando navegar direto para Settings...');
        }

        await saveScreenshot(page, '01-after-login');

        // 2. Navegar para Settings
        console.log('\n[2] Navegando para Settings...');
        await page.goto('http://eau-site.local/dashboard/settings/', { waitUntil: 'networkidle', timeout: 60000 });
        await page.waitForTimeout(3000);

        await saveScreenshot(page, '02-settings-page');

        // 3. Verificar se a seção de Institution Import existe
        console.log('\n[3] Verificando seção de Institution Import...');
        const institutionSection = await page.$('#eau-settings-institution-import');

        if (institutionSection) {
            console.log('✓ Seção de Institution Import encontrada!');

            // Scroll para a seção
            await institutionSection.scrollIntoViewIfNeeded();
            await page.waitForTimeout(1000);
            await saveScreenshot(page, '03-institution-section');
        } else {
            console.log('✗ Seção de Institution Import NÃO encontrada!');
        }

        // 4. Clicar no botão de importar
        console.log('\n[4] Clicando no botão Import Institution Data...');
        const importBtn = await page.$('#eau-import-institution-btn');

        if (importBtn) {
            await importBtn.click();
            await page.waitForTimeout(2000);
            await saveScreenshot(page, '04-modal-opened');

            // Verificar se o modal abriu
            const modal = await page.$('#eau-import-institution-modal.active');
            if (modal) {
                console.log('✓ Modal de importação aberto com sucesso!');
            } else {
                console.log('✗ Modal NÃO abriu!');
            }
        } else {
            console.log('✗ Botão de importação NÃO encontrado!');
        }

        // 5. Verificar estrutura do modal
        console.log('\n[5] Verificando estrutura do modal...');
        const modalElements = await page.evaluate(() => {
            const modal = document.querySelector('#eau-import-institution-modal');
            if (!modal) return null;

            return {
                hasOverlay: !!modal.querySelector('.eau-modal-overlay'),
                hasContainer: !!modal.querySelector('.eau-modal-container'),
                hasTitle: !!modal.querySelector('.eau-modal-title'),
                titleText: modal.querySelector('.eau-modal-title')?.textContent?.trim(),
                hasCloseBtn: !!modal.querySelector('.eau-modal-close-institution'),
                hasUploadForm: !!modal.querySelector('#eau-import-institution-upload-form'),
                hasFileInput: !!modal.querySelector('#institution_csv_file'),
                hasStep1: !!modal.querySelector('#eau-import-institution-step-1'),
                hasStep2: !!modal.querySelector('#eau-import-institution-step-2'),
                hasStep3: !!modal.querySelector('#eau-import-institution-step-3'),
                hasStep4: !!modal.querySelector('#eau-import-institution-step-4'),
            };
        });

        if (modalElements) {
            console.log('Estrutura do modal:');
            console.log('  - Overlay:', modalElements.hasOverlay ? '✓' : '✗');
            console.log('  - Container:', modalElements.hasContainer ? '✓' : '✗');
            console.log('  - Título:', modalElements.titleText);
            console.log('  - Botão fechar:', modalElements.hasCloseBtn ? '✓' : '✗');
            console.log('  - Form upload:', modalElements.hasUploadForm ? '✓' : '✗');
            console.log('  - Input arquivo:', modalElements.hasFileInput ? '✓' : '✗');
            console.log('  - Step 1:', modalElements.hasStep1 ? '✓' : '✗');
            console.log('  - Step 2:', modalElements.hasStep2 ? '✓' : '✗');
            console.log('  - Step 3:', modalElements.hasStep3 ? '✓' : '✗');
            console.log('  - Step 4:', modalElements.hasStep4 ? '✓' : '✗');
        }

        // 6. Fechar modal
        console.log('\n[6] Fechando modal...');
        const closeBtn = await page.$('.eau-modal-close-institution');
        if (closeBtn) {
            await closeBtn.click();
            await page.waitForTimeout(1000);
            await saveScreenshot(page, '05-modal-closed');
            console.log('✓ Modal fechado');
        }

        console.log('\n' + '='.repeat(60));
        console.log('TESTE CONCLUÍDO COM SUCESSO!');
        console.log('='.repeat(60));
        console.log('\nScreenshots salvos em:', SCREENSHOTS_DIR);

    } catch (error) {
        console.error('\n[ERRO]', error.message);
        await saveScreenshot(page, 'error');
    } finally {
        await browser.close();
    }
}

main().catch(console.error);
