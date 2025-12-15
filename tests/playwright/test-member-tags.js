const { chromium } = require('playwright');
const path = require('path');

(async () => {
    const browser = await chromium.launch({
        headless: false,
        slowMo: 1000 // Slow down to see what's happening
    });

    const context = await browser.newContext({
        viewport: { width: 1920, height: 1080 }
    });

    const page = await context.newPage();

    try {
        console.log('1. Navegando para página de login...');
        await page.goto('http://eau-site.local/login/', { waitUntil: 'networkidle' });
        await page.screenshot({
            path: path.join(__dirname, '../screenshots/01-login-page.png'),
            fullPage: true
        });

        console.log('2. Fazendo login...');
        await page.fill('#username', 'rrzillesg@gmail.com');
        await page.fill('#password', 'Pl@ttyPl@tty');
        await page.click('button[type="submit"]');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(2000);

        console.log('3. Navegando para página de membros...');
        await page.goto('http://eau-site.local/dashboard/manage-members/', { waitUntil: 'networkidle' });

        // Force reload para limpar cache
        console.log('4. Forçando reload (Ctrl+Shift+R)...');
        await page.reload({ waitUntil: 'networkidle' });
        await page.waitForTimeout(2000);

        await page.screenshot({
            path: path.join(__dirname, '../screenshots/02-members-page-after-reload.png'),
            fullPage: true
        });

        console.log('5. Aguardando tabela de membros carregar...');
        await page.waitForSelector('.eau-data-table tbody tr', { timeout: 10000 });
        await page.waitForTimeout(2000);

        console.log('6. Procurando botão Edit...');
        const editButtons = await page.$$('.eau-action-button[data-action="edit"]');
        console.log(`   Encontrados ${editButtons.length} botões Edit`);

        if (editButtons.length === 0) {
            console.log('   ❌ Nenhum botão Edit encontrado!');
            await page.screenshot({
                path: path.join(__dirname, '../screenshots/03-no-edit-buttons.png'),
                fullPage: true
            });
        } else {
            console.log('7. Clicando no primeiro botão Edit...');
            await editButtons[0].click();
            await page.waitForTimeout(2000);

            console.log('8. Aguardando modal abrir...');
            await page.waitForSelector('.eau-modal.active', { timeout: 5000 });
            await page.waitForTimeout(1000);

            await page.screenshot({
                path: path.join(__dirname, '../screenshots/04-modal-opened.png'),
                fullPage: true
            });

            console.log('9. Rolando modal até o final para encontrar campo Tags...');
            const modalContent = await page.$('.eau-modal.active .eau-modal-content');

            if (modalContent) {
                // Scroll to bottom of modal
                await modalContent.evaluate(el => el.scrollTop = el.scrollHeight);
                await page.waitForTimeout(1000);

                await page.screenshot({
                    path: path.join(__dirname, '../screenshots/05-modal-scrolled-to-tags.png'),
                    fullPage: true
                });

                console.log('10. Verificando se campo Tags existe...');
                const tagsField = await page.$('#member-tags-field');

                if (tagsField) {
                    console.log('   ✅ Campo Tags encontrado!');

                    // Highlight the tags field
                    await tagsField.evaluate(el => {
                        el.style.border = '3px solid red';
                        el.style.boxShadow = '0 0 10px red';
                    });

                    await page.screenshot({
                        path: path.join(__dirname, '../screenshots/06-tags-field-highlighted.png'),
                        fullPage: true
                    });

                    console.log('11. Tentando adicionar uma tag...');
                    await tagsField.click();
                    await page.waitForTimeout(500);

                    // Try to type a tag
                    await page.keyboard.type('Test Tag');
                    await page.waitForTimeout(500);
                    await page.keyboard.press('Enter');
                    await page.waitForTimeout(1000);

                    await page.screenshot({
                        path: path.join(__dirname, '../screenshots/07-tag-added.png'),
                        fullPage: true
                    });

                    console.log('   ✅ Tag adicionada com sucesso!');
                } else {
                    console.log('   ❌ Campo Tags NÃO encontrado no modal!');

                    // Let's check what fields are in the modal
                    const allInputs = await page.$$('.eau-modal.active input, .eau-modal.active select, .eau-modal.active textarea');
                    console.log(`   Total de campos no modal: ${allInputs.length}`);

                    // Get IDs of all inputs
                    for (const input of allInputs) {
                        const id = await input.getAttribute('id');
                        const name = await input.getAttribute('name');
                        console.log(`   - Input: id="${id}", name="${name}"`);
                    }
                }
            } else {
                console.log('   ❌ Modal content não encontrado!');
            }
        }

        console.log('\n✅ Teste concluído! Screenshots salvos em tests/screenshots/');

    } catch (error) {
        console.error('❌ Erro durante o teste:', error);
        await page.screenshot({
            path: path.join(__dirname, '../screenshots/error-screenshot.png'),
            fullPage: true
        });
    } finally {
        await browser.close();
    }
})();
