const { chromium } = require('playwright');
const path = require('path');

(async () => {
    const browser = await chromium.launch({
        headless: false,
        slowMo: 500
    });

    const context = await browser.newContext({
        viewport: { width: 1920, height: 1080 },
        ignoreHTTPSErrors: true
    });

    const page = await context.newPage();

    try {
        console.log('1. Navegando para página de login...');
        await page.goto('http://eau-site.local/login/', { waitUntil: 'domcontentloaded', timeout: 30000 });
        await page.waitForTimeout(2000);

        await page.screenshot({
            path: path.join(__dirname, '../screenshots/01-login-page.png'),
            fullPage: true
        });

        console.log('2. Analisando campos de login...');
        const loginInputs = await page.evaluate(() => {
            const inputs = Array.from(document.querySelectorAll('input'));
            return inputs.map(input => ({
                type: input.type,
                id: input.id,
                name: input.name,
                placeholder: input.placeholder,
                className: input.className
            }));
        });

        console.log('   Campos encontrados:');
        loginInputs.forEach(input => {
            console.log(`   - type="${input.type}" id="${input.id}" name="${input.name}" placeholder="${input.placeholder}"`);
        });

        console.log('\n3. Tentando fazer login com múltiplos seletores...');

        // Try different selectors for username/email field
        const usernameSelectors = [
            'input[name="log"]',
            'input[name="email"]',
            'input[type="text"]',
            'input[type="email"]',
            '#user_login',
            '#username',
            '#log'
        ];

        let usernameField = null;
        for (const selector of usernameSelectors) {
            try {
                const field = await page.$(selector);
                if (field) {
                    console.log(`   ✅ Campo de email encontrado: ${selector}`);
                    usernameField = selector;
                    break;
                }
            } catch (e) {
                // Try next selector
            }
        }

        // Try different selectors for password field
        const passwordSelectors = [
            'input[name="pwd"]',
            'input[name="password"]',
            'input[type="password"]',
            '#user_pass',
            '#password',
            '#pwd'
        ];

        let passwordField = null;
        for (const selector of passwordSelectors) {
            try {
                const field = await page.$(selector);
                if (field) {
                    console.log(`   ✅ Campo de senha encontrado: ${selector}`);
                    passwordField = selector;
                    break;
                }
            } catch (e) {
                // Try next selector
            }
        }

        if (usernameField && passwordField) {
            console.log('\n4. Preenchendo credenciais...');
            await page.fill(usernameField, 'rrzillesg@gmail.com');
            await page.fill(passwordField, 'Pl@ttyPl@tty');

            await page.screenshot({
                path: path.join(__dirname, '../screenshots/02-credentials-filled.png'),
                fullPage: true
            });

            console.log('5. Submetendo formulário...');
            const submitSelectors = [
                'button[type="submit"]',
                'input[type="submit"]',
                '#wp-submit'
            ];

            for (const selector of submitSelectors) {
                try {
                    const button = await page.$(selector);
                    if (button) {
                        console.log(`   ✅ Botão submit encontrado: ${selector}`);
                        await button.click();
                        break;
                    }
                } catch (e) {
                    // Try next selector
                }
            }

            await page.waitForTimeout(3000);
            await page.screenshot({
                path: path.join(__dirname, '../screenshots/03-after-login.png'),
                fullPage: true
            });

            console.log('\n6. Navegando para página de membros...');
            await page.goto('http://eau-site.local/dashboard/manage-members/', { waitUntil: 'domcontentloaded', timeout: 30000 });
            await page.waitForTimeout(2000);

            // Force reload (Ctrl+Shift+R)
            console.log('7. Forçando reload...');
            await page.keyboard.down('Control');
            await page.keyboard.down('Shift');
            await page.keyboard.press('R');
            await page.keyboard.up('Shift');
            await page.keyboard.up('Control');
            await page.waitForTimeout(3000);

            await page.screenshot({
                path: path.join(__dirname, '../screenshots/04-members-page.png'),
                fullPage: true
            });

            console.log('8. Aguardando tabela de membros...');
            await page.waitForSelector('.eau-data-table', { timeout: 10000 });
            await page.waitForTimeout(2000);

            console.log('9. Procurando botão Edit...');
            const editButton = await page.$('.eau-action-button[data-action="edit"]');

            if (editButton) {
                console.log('   ✅ Botão Edit encontrado!');
                await editButton.scrollIntoViewIfNeeded();
                await page.waitForTimeout(500);

                await editButton.evaluate(el => {
                    el.style.border = '3px solid red';
                });

                await page.screenshot({
                    path: path.join(__dirname, '../screenshots/05-edit-button-found.png'),
                    fullPage: true
                });

                console.log('10. Clicando no botão Edit...');
                await editButton.click();
                await page.waitForTimeout(2000);

                await page.screenshot({
                    path: path.join(__dirname, '../screenshots/06-after-edit-click.png'),
                    fullPage: true
                });

                console.log('11. Aguardando modal abrir...');
                await page.waitForSelector('.eau-modal.active', { timeout: 5000 });
                await page.waitForTimeout(1000);

                await page.screenshot({
                    path: path.join(__dirname, '../screenshots/07-modal-opened.png'),
                    fullPage: true
                });

                console.log('12. Analisando campos do modal...');
                const modalFields = await page.evaluate(() => {
                    const modal = document.querySelector('.eau-modal.active');
                    if (!modal) return [];

                    const fields = Array.from(modal.querySelectorAll('input, select, textarea'));
                    return fields.map(field => ({
                        type: field.tagName.toLowerCase(),
                        inputType: field.type,
                        id: field.id,
                        name: field.name,
                        className: field.className,
                        placeholder: field.placeholder
                    }));
                });

                console.log('   Campos encontrados no modal:');
                modalFields.forEach(field => {
                    console.log(`   - ${field.type} type="${field.inputType}" id="${field.id}" name="${field.name}"`);
                });

                console.log('\n13. Procurando especificamente campo Tags...');
                const tagsSelectors = [
                    '#member-tags-field',
                    'input[name="mem_tags"]',
                    '.tags-input',
                    '[placeholder*="tag" i]',
                    '[placeholder*="Tag" i]'
                ];

                let tagsField = null;
                for (const selector of tagsSelectors) {
                    try {
                        const field = await page.$(`.eau-modal.active ${selector}`);
                        if (field) {
                            console.log(`   ✅ Campo Tags encontrado: ${selector}`);
                            tagsField = field;
                            break;
                        }
                    } catch (e) {
                        // Try next selector
                    }
                }

                if (tagsField) {
                    console.log('\n14. Rolando até o campo Tags...');
                    await tagsField.scrollIntoViewIfNeeded();
                    await page.waitForTimeout(500);

                    await tagsField.evaluate(el => {
                        el.style.border = '3px solid lime';
                        el.style.boxShadow = '0 0 10px lime';
                    });

                    await page.screenshot({
                        path: path.join(__dirname, '../screenshots/08-tags-field-highlighted.png'),
                        fullPage: true
                    });

                    console.log('15. Tentando adicionar uma tag...');
                    await tagsField.click();
                    await page.waitForTimeout(500);
                    await page.keyboard.type('Test Tag');
                    await page.waitForTimeout(500);
                    await page.keyboard.press('Enter');
                    await page.waitForTimeout(1000);

                    await page.screenshot({
                        path: path.join(__dirname, '../screenshots/09-tag-added.png'),
                        fullPage: true
                    });

                    console.log('   ✅ Tag adicionada com sucesso!');
                } else {
                    console.log('   ❌ Campo Tags NÃO encontrado no modal!');

                    // Scroll the modal to the bottom
                    console.log('\n14. Rolando modal até o final...');
                    await page.evaluate(() => {
                        const modal = document.querySelector('.eau-modal.active .eau-modal-content');
                        if (modal) {
                            modal.scrollTop = modal.scrollHeight;
                        }
                    });
                    await page.waitForTimeout(1000);

                    await page.screenshot({
                        path: path.join(__dirname, '../screenshots/08-modal-scrolled-bottom.png'),
                        fullPage: true
                    });
                }

            } else {
                console.log('   ❌ Nenhum botão Edit encontrado!');
            }

        } else {
            console.log('   ❌ Não foi possível encontrar campos de login!');
        }

        console.log('\n✅ Teste concluído! Screenshots salvos em tests/screenshots/');

    } catch (error) {
        console.error('❌ Erro durante o teste:', error.message);
        await page.screenshot({
            path: path.join(__dirname, '../screenshots/error-screenshot.png'),
            fullPage: true
        });
    } finally {
        // Keep browser open for 5 seconds to review
        console.log('\nMantendo navegador aberto por 5 segundos para revisão...');
        await page.waitForTimeout(5000);
        await browser.close();
    }
})();
