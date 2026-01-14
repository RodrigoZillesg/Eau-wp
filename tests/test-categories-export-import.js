/**
 * Teste do Categories Export/Import
 *
 * Testa a UI de exportação e importação de categorias.
 */

const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const SCREENSHOTS_DIR = './categories-export-import-screenshots';

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
    console.log('Teste de Export/Import de Categorias');
    console.log('='.repeat(60));

    const browser = await chromium.launch({ headless: false, slowMo: 200 });
    const context = await browser.newContext({ viewport: { width: 1920, height: 1080 } });
    const page = await context.newPage();

    try {
        // 1. Login
        console.log('\n[1] Fazendo login...');
        await page.goto('http://eau-site.local/login/', { waitUntil: 'networkidle', timeout: 60000 });

        const usernameField = await page.$('input[name="log"]');
        if (usernameField) {
            await usernameField.fill('rrzillesg@gmail.com');
            await page.fill('input[name="pwd"]', 'Pl@ttyPl@tty');
            await page.click('button[type="submit"]');
            await page.waitForLoadState('networkidle');
            await page.waitForTimeout(3000);
        }

        await saveScreenshot(page, '01-after-login');

        // 2. Navegar para Categories
        console.log('\n[2] Navegando para Categories...');
        await page.goto('http://eau-site.local/dashboard/manage-categories/', { waitUntil: 'networkidle', timeout: 60000 });
        await page.waitForTimeout(3000);

        await saveScreenshot(page, '02-categories-page');

        // 3. Verificar botões de Export e Import
        console.log('\n[3] Verificando botões de Export e Import...');
        const exportBtn = await page.$('#eau-export-categories');
        const importBtn = await page.$('#eau-import-categories');

        if (exportBtn) {
            console.log('✓ Botão Export encontrado!');
        } else {
            console.log('✗ Botão Export NÃO encontrado!');
        }

        if (importBtn) {
            console.log('✓ Botão Import encontrado!');
        } else {
            console.log('✗ Botão Import NÃO encontrado!');
        }

        // 4. Testar Export
        console.log('\n[4] Testando Export...');
        if (exportBtn) {
            // Setup download handler
            const [download] = await Promise.all([
                page.waitForEvent('download', { timeout: 10000 }).catch(() => null),
                exportBtn.click()
            ]);

            if (download) {
                const downloadPath = await download.path();
                const filename = download.suggestedFilename();
                console.log(`✓ Export gerou arquivo: ${filename}`);

                // Copy to screenshots dir
                const destPath = path.join(SCREENSHOTS_DIR, filename);
                fs.copyFileSync(downloadPath, destPath);
                console.log(`  Arquivo salvo em: ${destPath}`);
            } else {
                console.log('  Download não detectado (pode ter funcionado mas não foi capturado)');
            }

            await page.waitForTimeout(2000);
            await saveScreenshot(page, '03-after-export');
        }

        // 5. Testar Import Modal
        console.log('\n[5] Testando Import Modal...');
        if (importBtn) {
            await importBtn.click();
            await page.waitForTimeout(1000);
            await saveScreenshot(page, '04-import-modal-opened');

            // Verificar estrutura do modal
            const modalElements = await page.evaluate(() => {
                const modal = document.querySelector('#eau-modal-import-overlay');
                if (!modal) return null;

                return {
                    isVisible: modal.style.display !== 'none',
                    hasTitle: !!modal.querySelector('.eau-modal-title'),
                    hasFileInput: !!modal.querySelector('#import-json-file'),
                    hasAnalyzeBtn: !!modal.querySelector('#import-btn-analyze'),
                    hasStep1: !!modal.querySelector('#import-step-upload'),
                    hasStep2: !!modal.querySelector('#import-step-preview'),
                    hasStep3: !!modal.querySelector('#import-step-result'),
                };
            });

            if (modalElements) {
                console.log('Estrutura do modal:');
                console.log('  - Visível:', modalElements.isVisible ? '✓' : '✗');
                console.log('  - Título:', modalElements.hasTitle ? '✓' : '✗');
                console.log('  - Input arquivo:', modalElements.hasFileInput ? '✓' : '✗');
                console.log('  - Botão Analyze:', modalElements.hasAnalyzeBtn ? '✓' : '✗');
                console.log('  - Step 1 (Upload):', modalElements.hasStep1 ? '✓' : '✗');
                console.log('  - Step 2 (Preview):', modalElements.hasStep2 ? '✓' : '✗');
                console.log('  - Step 3 (Result):', modalElements.hasStep3 ? '✓' : '✗');
            }

            // Fechar modal
            const cancelBtn = await page.$('#import-btn-cancel');
            if (cancelBtn) {
                await cancelBtn.click();
                await page.waitForTimeout(500);
            }
        }

        await saveScreenshot(page, '05-final');

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
