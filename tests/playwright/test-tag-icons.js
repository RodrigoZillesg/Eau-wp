const { chromium } = require('playwright');

(async () => {
    const browser = await chromium.launch({ headless: false });
    const context = await browser.newContext({
        viewport: { width: 1920, height: 1080 }
    });
    const page = await context.newPage();

    try {
        console.log('📍 Navegando para página de login...');
        await page.goto('http://eau-site.local/login/');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(1000);

        // Tira screenshot da página de login para debug
        await page.screenshot({
            path: 'C:/Users/rrzil/Local Sites/eau-site/app/public/wp-content/plugins/eau-system/tests/screenshots/login-page.png'
        });

        console.log('🔐 Fazendo login...');
        // Tenta vários seletores possíveis para username/email
        const usernameSelectors = ['#username', 'input[name="log"]', 'input[type="text"]', 'input[name="username"]', 'input[name="email"]'];
        let usernameFilled = false;
        for (const selector of usernameSelectors) {
            try {
                const element = await page.locator(selector).first();
                if (await element.isVisible({ timeout: 1000 })) {
                    await element.fill('rrzillesg@gmail.com');
                    console.log(`✓ Username preenchido usando: ${selector}`);
                    usernameFilled = true;
                    break;
                }
            } catch (e) {
                // Continua tentando
            }
        }

        // Tenta vários seletores possíveis para password
        const passwordSelectors = ['#password', 'input[name="pwd"]', 'input[type="password"]', 'input[name="password"]'];
        let passwordFilled = false;
        for (const selector of passwordSelectors) {
            try {
                const element = await page.locator(selector).first();
                if (await element.isVisible({ timeout: 1000 })) {
                    await element.fill('Pl@ttyPl@tty');
                    console.log(`✓ Password preenchido usando: ${selector}`);
                    passwordFilled = true;
                    break;
                }
            } catch (e) {
                // Continua tentando
            }
        }

        if (!usernameFilled || !passwordFilled) {
            console.log('⚠️ Não conseguiu preencher os campos. Veja o screenshot em login-page.png');
        }

        await page.click('button[type="submit"]');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(2000);

        console.log('📍 Navegando para Settings...');
        await page.goto('http://eau-site.local/dashboard/settings/');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(2000);

        console.log('➕ Criando uma tag de teste...');
        // Preencher o nome da tag
        await page.fill('input[placeholder="Tag name..."]', 'Test Tag');
        await page.waitForTimeout(500);

        // Preencher a descrição (opcional)
        await page.fill('input[placeholder="Description (optional) - helps identify the tag for email lists"]', 'Tag de teste para verificar ícones');
        await page.waitForTimeout(500);

        // Clicar no botão Add Tag
        await page.click('button:has-text("Add Tag")');
        await page.waitForTimeout(2000);

        console.log('📸 Screenshot após criar a tag...');
        await page.screenshot({
            path: 'C:/Users/rrzil/Local Sites/eau-site/app/public/wp-content/plugins/eau-system/tests/screenshots/tag-created.png',
            fullPage: true
        });

        console.log('✏️ Clicando no botão Edit...');
        // Baseado no código JS, o botão Edit tem a classe .eau-tag-action-btn.edit
        const editSelectors = [
            '.eau-tag-action-btn.edit',
            'button.eau-tag-action-btn.edit',
            '.eau-tag-item .eau-tag-action-btn.edit',
            '.eau-tag-actions .edit'
        ];

        let editClicked = false;
        for (const selector of editSelectors) {
            try {
                const element = await page.locator(selector).first();
                if (await element.isVisible({ timeout: 2000 })) {
                    await element.click();
                    console.log(`✓ Botão Edit clicado usando: ${selector}`);
                    editClicked = true;
                    await page.waitForTimeout(2000);
                    break;
                }
            } catch (e) {
                console.log(`⚠️ Seletor ${selector} não encontrou elemento visível`);
            }
        }

        if (!editClicked) {
            console.log('⚠️ Não encontrou botão Edit. Veja o screenshot em tag-created.png');
        }

        console.log('📸 Tirando screenshot final com botões Save e Cancel...');
        await page.screenshot({
            path: 'C:/Users/rrzil/Local Sites/eau-site/app/public/wp-content/plugins/eau-system/tests/screenshots/tag-edit-buttons.png',
            fullPage: true
        });

        console.log('✅ Screenshot salvo com sucesso!');

    } catch (error) {
        console.error('❌ Erro:', error);
        await page.screenshot({
            path: 'C:/Users/rrzil/Local Sites/eau-site/app/public/wp-content/plugins/eau-system/tests/screenshots/error.png',
            fullPage: true
        });
    } finally {
        await browser.close();
    }
})();
