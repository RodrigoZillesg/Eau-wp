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
        await page.goto('http://eau-site.local/login/', { waitUntil: 'domcontentloaded' });
        await page.waitForTimeout(2000);

        console.log('2. Fazendo login...');
        await page.fill('input[name="log"]', 'rrzillesg@gmail.com');
        await page.fill('input[name="pwd"]', 'Pl@ttyPl@tty');
        await page.click('button[type="submit"]');
        await page.waitForTimeout(3000);

        const afterLoginUrl = page.url();
        console.log(`   URL após login: ${afterLoginUrl}`);

        await page.screenshot({
            path: path.join(__dirname, '../screenshots/01-after-login.png'),
            fullPage: true
        });

        console.log('\n3. Tentando acessar dashboard primeiro...');
        await page.goto('http://eau-site.local/dashboard/', { waitUntil: 'domcontentloaded' });
        await page.waitForTimeout(3000);

        const dashboardUrl = page.url();
        console.log(`   URL do dashboard: ${dashboardUrl}`);

        await page.screenshot({
            path: path.join(__dirname, '../screenshots/02-dashboard.png'),
            fullPage: true
        });

        console.log('\n4. Navegando para manage-members...');
        await page.goto('http://eau-site.local/dashboard/manage-members/', { waitUntil: 'domcontentloaded' });
        await page.waitForTimeout(3000);

        const membersUrl = page.url();
        console.log(`   URL da página de membros: ${membersUrl}`);

        // Force reload
        console.log('5. Forçando hard reload...');
        await page.reload({ waitUntil: 'domcontentloaded' });
        await page.waitForTimeout(3000);

        await page.screenshot({
            path: path.join(__dirname, '../screenshots/03-members-page-after-reload.png'),
            fullPage: true
        });

        console.log('\n6. Verificando elementos na página...');
        const pageContent = await page.evaluate(() => {
            return {
                title: document.title,
                hasTable: !!document.querySelector('.eau-data-table'),
                hasSkeletonLoading: !!document.querySelector('.eau-skeleton-loading'),
                hasH1: !!document.querySelector('h1'),
                h1Text: document.querySelector('h1')?.textContent || '',
                bodyClasses: document.body.className,
                hasEditButton: !!document.querySelector('.eau-action-button[data-action="edit"]'),
                allEditButtons: document.querySelectorAll('.eau-action-button[data-action="edit"]').length
            };
        });

        console.log('   Análise da página:');
        console.log(`   - Title: ${pageContent.title}`);
        console.log(`   - H1: ${pageContent.h1Text}`);
        console.log(`   - Body classes: ${pageContent.bodyClasses}`);
        console.log(`   - Tem tabela: ${pageContent.hasTable}`);
        console.log(`   - Skeleton loading: ${pageContent.hasSkeletonLoading}`);
        console.log(`   - Tem botão Edit: ${pageContent.hasEditButton}`);
        console.log(`   - Total de botões Edit: ${pageContent.allEditButtons}`);

        if (pageContent.hasSkeletonLoading) {
            console.log('\n   ⏳ Skeleton loading detectado, aguardando carregar...');
            await page.waitForTimeout(5000);

            await page.screenshot({
                path: path.join(__dirname, '../screenshots/04-after-skeleton-wait.png'),
                fullPage: true
            });

            const afterWait = await page.evaluate(() => ({
                hasTable: !!document.querySelector('.eau-data-table'),
                hasSkeletonLoading: !!document.querySelector('.eau-skeleton-loading'),
                hasEditButton: !!document.querySelector('.eau-action-button[data-action="edit"]')
            }));

            console.log('   Após aguardar:');
            console.log(`   - Tem tabela: ${afterWait.hasTable}`);
            console.log(`   - Skeleton loading: ${afterWait.hasSkeletonLoading}`);
            console.log(`   - Tem botão Edit: ${afterWait.hasEditButton}`);
        }

        // Try to find and click Edit button
        console.log('\n7. Procurando botão Edit...');
        const editButton = await page.$('.eau-action-button[data-action="edit"]');

        if (editButton) {
            console.log('   ✅ Botão Edit encontrado!');

            await editButton.scrollIntoViewIfNeeded();
            await page.waitForTimeout(500);

            await page.screenshot({
                path: path.join(__dirname, '../screenshots/05-edit-button-visible.png'),
                fullPage: true
            });

            console.log('8. Clicando no botão Edit...');
            await editButton.click();
            await page.waitForTimeout(2000);

            await page.screenshot({
                path: path.join(__dirname, '../screenshots/06-after-edit-click.png'),
                fullPage: true
            });

            console.log('9. Verificando se modal abriu...');
            const modalCheck = await page.evaluate(() => ({
                hasModal: !!document.querySelector('.eau-modal'),
                hasActiveModal: !!document.querySelector('.eau-modal.active'),
                modalCount: document.querySelectorAll('.eau-modal').length,
                activeModalCount: document.querySelectorAll('.eau-modal.active').length
            }));

            console.log(`   - Tem modal: ${modalCheck.hasModal}`);
            console.log(`   - Tem modal active: ${modalCheck.hasActiveModal}`);
            console.log(`   - Total modals: ${modalCheck.modalCount}`);
            console.log(`   - Modals active: ${modalCheck.activeModalCount}`);

            if (modalCheck.hasActiveModal) {
                console.log('   ✅ Modal aberto!');

                await page.waitForTimeout(1000);

                await page.screenshot({
                    path: path.join(__dirname, '../screenshots/07-modal-opened.png'),
                    fullPage: true
                });

                console.log('\n10. Analisando campos do modal...');
                const modalInfo = await page.evaluate(() => {
                    const modal = document.querySelector('.eau-modal.active');
                    if (!modal) return null;

                    const fields = Array.from(modal.querySelectorAll('input, select, textarea'));
                    const labels = Array.from(modal.querySelectorAll('label'));

                    return {
                        fields: fields.map(field => ({
                            tag: field.tagName.toLowerCase(),
                            type: field.type,
                            id: field.id,
                            name: field.name,
                            className: field.className,
                            placeholder: field.placeholder
                        })),
                        labels: labels.map(label => ({
                            text: label.textContent.trim(),
                            for: label.getAttribute('for')
                        })),
                        hasTagsLabel: labels.some(l => l.textContent.toLowerCase().includes('tag')),
                        hasTagsField: fields.some(f => f.id?.includes('tag') || f.name?.includes('tag'))
                    };
                });

                if (modalInfo) {
                    console.log(`   Total de campos: ${modalInfo.fields.length}`);
                    console.log(`   Total de labels: ${modalInfo.labels.length}`);
                    console.log(`   Tem label "Tags": ${modalInfo.hasTagsLabel}`);
                    console.log(`   Tem campo com "tag": ${modalInfo.hasTagsField}`);

                    console.log('\n   Labels encontrados:');
                    modalInfo.labels.forEach(label => {
                        console.log(`   - "${label.text}" (for="${label.for}")`);
                    });

                    console.log('\n   Campos encontrados:');
                    modalInfo.fields.forEach(field => {
                        console.log(`   - ${field.tag} type="${field.type}" id="${field.id}" name="${field.name}"`);
                    });

                    // Scroll modal to bottom
                    console.log('\n11. Rolando modal até o final...');
                    await page.evaluate(() => {
                        const modalContent = document.querySelector('.eau-modal.active .eau-modal-content');
                        if (modalContent) {
                            modalContent.scrollTop = modalContent.scrollHeight;
                        }
                    });
                    await page.waitForTimeout(1000);

                    await page.screenshot({
                        path: path.join(__dirname, '../screenshots/08-modal-scrolled-bottom.png'),
                        fullPage: true
                    });

                    // Search for tags field specifically
                    console.log('\n12. Procurando campo de Tags...');
                    const tagsField = await page.$('.eau-modal.active #member-tags-field');

                    if (tagsField) {
                        console.log('   ✅ Campo Tags encontrado! (#member-tags-field)');

                        await tagsField.scrollIntoViewIfNeeded();
                        await page.waitForTimeout(500);

                        await tagsField.evaluate(el => {
                            el.style.border = '5px solid lime';
                            el.style.boxShadow = '0 0 20px lime';
                        });

                        await page.screenshot({
                            path: path.join(__dirname, '../screenshots/09-tags-field-found.png'),
                            fullPage: true
                        });

                        console.log('\n13. Tentando adicionar uma tag...');
                        await tagsField.click();
                        await page.waitForTimeout(500);
                        await page.keyboard.type('Test Tag');
                        await page.waitForTimeout(500);
                        await page.keyboard.press('Enter');
                        await page.waitForTimeout(1500);

                        await page.screenshot({
                            path: path.join(__dirname, '../screenshots/10-tag-added.png'),
                            fullPage: true
                        });

                        console.log('   ✅ Tentativa de adicionar tag concluída!');

                    } else {
                        console.log('   ❌ Campo Tags (#member-tags-field) NÃO encontrado no modal!');
                    }
                } else {
                    console.log('   ❌ Não foi possível analisar o modal!');
                }

            } else {
                console.log('   ❌ Modal não abriu após clicar no Edit!');
            }

        } else {
            console.log('   ❌ Nenhum botão Edit encontrado na página!');
            console.log('   Isso pode indicar:');
            console.log('   - A tabela não carregou dados');
            console.log('   - O usuário não tem permissão');
            console.log('   - A página não é a correta');
        }

        console.log('\n✅ Teste concluído! Screenshots salvos em tests/screenshots/');

    } catch (error) {
        console.error('\n❌ Erro durante o teste:', error.message);
        await page.screenshot({
            path: path.join(__dirname, '../screenshots/error-screenshot.png'),
            fullPage: true
        });
    } finally {
        console.log('\nMantendo navegador aberto por 10 segundos para revisão...');
        await page.waitForTimeout(10000);
        await browser.close();
    }
})();
