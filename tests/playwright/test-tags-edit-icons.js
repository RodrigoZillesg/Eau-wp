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

    const screenshotsDir = path.join(__dirname, '../screenshots');

    try {
        console.log('🔐 Acessando página de login...');
        await page.goto('http://eau-site.local/login/', { waitUntil: 'networkidle' });
        await page.waitForTimeout(2000);

        console.log('📸 Screenshot da página de login...');
        await page.screenshot({
            path: path.join(screenshotsDir, 'login-page.png'),
            fullPage: true
        });

        console.log('🔍 Aguardando campo de username...');
        await page.waitForSelector('input[type="text"], input[type="email"], input#username, input#user_login', { timeout: 10000 });

        console.log('🔐 Preenchendo credenciais...');
        const usernameInput = await page.locator('input[type="text"], input[type="email"], input#username, input#user_login').first();
        await usernameInput.fill('rrzillesg@gmail.com');

        const passwordInput = await page.locator('input[type="password"], input#password, input#user_pass').first();
        await passwordInput.fill('Pl@ttyPl@tty');

        console.log('🔐 Submetendo login...');
        await page.click('button[type="submit"]');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(2000);

        console.log('📄 Navegando para Settings...');
        await page.goto('http://eau-site.local/dashboard/settings/', { waitUntil: 'networkidle' });
        await page.waitForTimeout(2000);

        console.log('🔄 Forçando reload para limpar cache...');
        await page.reload({ waitUntil: 'networkidle' });
        await page.waitForTimeout(1000);

        console.log('📸 Screenshot inicial da página de settings...');
        await page.screenshot({
            path: path.join(screenshotsDir, 'settings-page-before-edit.png'),
            fullPage: true
        });

        console.log('🖱️ Procurando botões Edit de tags...');
        const editButtons = await page.locator('.eau-tag-action-btn.edit').all();

        if (editButtons.length === 0) {
            console.error('❌ Nenhum botão Edit encontrado!');
            await browser.close();
            return;
        }

        console.log(`✅ Encontrados ${editButtons.length} botões Edit`);
        console.log('🖱️ Clicando no primeiro botão Edit...');
        await editButtons[0].click();
        await page.waitForTimeout(1000);

        console.log('📸 Screenshot após clicar em Edit (modo de edição)...');
        await page.screenshot({
            path: path.join(screenshotsDir, 'settings-tag-edit-mode.png'),
            fullPage: true
        });

        console.log('🔍 Verificando presença dos botões Save e Cancel...');
        const saveBtn = await page.locator('.eau-tag-action-btn.save').first();
        const cancelBtn = await page.locator('.eau-tag-action-btn.cancel').first();

        const saveVisible = await saveBtn.isVisible();
        const cancelVisible = await cancelBtn.isVisible();

        console.log(`✅ Botão Save visível: ${saveVisible}`);
        console.log(`✅ Botão Cancel visível: ${cancelVisible}`);

        console.log('🔍 Verificando se os ícones SVG estão sendo renderizados...');
        const saveIcon = await page.locator('.eau-tag-action-btn.save svg').first();
        const cancelIcon = await page.locator('.eau-tag-action-btn.cancel svg').first();

        const saveIconVisible = await saveIcon.isVisible().catch(() => false);
        const cancelIconVisible = await cancelIcon.isVisible().catch(() => false);

        console.log(`📌 Ícone Save (check) visível: ${saveIconVisible}`);
        console.log(`📌 Ícone Cancel (X) visível: ${cancelIconVisible}`);

        // Verificar o conteúdo HTML dos botões
        const saveHTML = await saveBtn.innerHTML();
        const cancelHTML = await cancelBtn.innerHTML();
        console.log(`📋 HTML do botão Save: ${saveHTML.substring(0, 200)}`);
        console.log(`📋 HTML do botão Cancel: ${cancelHTML.substring(0, 200)}`);

        console.log('📸 Screenshot final focado nos botões...');
        const tagItem = await page.locator('.eau-tag-item.editing').first();
        await tagItem.screenshot({
            path: path.join(screenshotsDir, 'settings-tag-buttons-closeup.png')
        });

        console.log('✅ Teste concluído!');
        console.log('📁 Screenshots salvos em:', screenshotsDir);

    } catch (error) {
        console.error('❌ Erro durante o teste:', error);
        await page.screenshot({
            path: path.join(screenshotsDir, 'error-screenshot.png'),
            fullPage: true
        });
    } finally {
        await browser.close();
    }
})();
