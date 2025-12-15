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

        console.log('   URL após login:', page.url());

        // Try different URLs for members page
        const membersUrls = [
            'http://eau-site.local/dashboard/?page=eau-members-management',
            'http://eau-site.local/dashboard/manage-members/',
            'http://eau-site.local/admin/members/'
        ];

        for (const url of membersUrls) {
            console.log(`\n3. Tentando acessar: ${url}`);
            await page.goto(url, { waitUntil: 'domcontentloaded' });
            await page.waitForTimeout(3000);

            const currentUrl = page.url();
            const pageTitle = await page.title();
            console.log(`   URL resultante: ${currentUrl}`);
            console.log(`   Título: ${pageTitle}`);

            await page.screenshot({
                path: path.join(__dirname, `../screenshots/test-url-${membersUrls.indexOf(url)}.png`),
                fullPage: true
            });

            // Check if we're on a members management page
            const pageCheck = await page.evaluate(() => ({
                hasTable: !!document.querySelector('.eau-data-table'),
                hasEditButton: !!document.querySelector('.eau-action-button[data-action="edit"]'),
                h1Text: document.querySelector('h1')?.textContent || '',
                hasEauMembersContainer: !!document.querySelector('[class*="members"]'),
                allH1: Array.from(document.querySelectorAll('h1, h2')).map(h => h.textContent.trim()).filter(t => t)
            }));

            console.log('   Análise:');
            console.log(`   - Tem tabela: ${pageCheck.hasTable}`);
            console.log(`   - Tem botão Edit: ${pageCheck.hasEditButton}`);
            console.log(`   - H1: ${pageCheck.h1Text}`);
            console.log(`   - Headings: ${pageCheck.allH1.join(', ')}`);

            if (pageCheck.hasEditButton) {
                console.log(`\n   ✅ Página de membros encontrada na URL: ${url}`);

                console.log('\n4. Forçando hard reload...');
                await page.keyboard.down('Control');
                await page.keyboard.down('Shift');
                await page.keyboard.press('R');
                await page.keyboard.up('Shift');
                await page.keyboard.up('Control');
                await page.waitForTimeout(3000);

                await page.screenshot({
                    path: path.join(__dirname, '../screenshots/05-members-page-reloaded.png'),
                    fullPage: true
                });

                console.log('5. Clicando no primeiro botão Edit...');
                const editButton = await page.$('.eau-action-button[data-action="edit"]');

                if (editButton) {
                    await editButton.scrollIntoViewIfNeeded();
                    await page.waitForTimeout(500);
                    await editButton.click();
                    await page.waitForTimeout(2000);

                    await page.screenshot({
                        path: path.join(__dirname, '../screenshots/06-after-edit-click.png'),
                        fullPage: true
                    });

                    console.log('6. Aguardando modal abrir...');
                    const hasModal = await page.waitForSelector('.eau-modal.active', { timeout: 5000 })
                        .then(() => true)
                        .catch(() => false);

                    if (hasModal) {
                        console.log('   ✅ Modal aberto!');
                        await page.waitForTimeout(1000);

                        await page.screenshot({
                            path: path.join(__dirname, '../screenshots/07-modal-opened.png'),
                            fullPage: true
                        });

                        console.log('\n7. Rolando modal até o final para encontrar Tags...');
                        await page.evaluate(() => {
                            const modalContent = document.querySelector('.eau-modal.active .eau-modal-content');
                            if (modalContent) {
                                modalContent.scrollTop = modalContent.scrollHeight;
                            }
                        });
                        await page.waitForTimeout(1000);

                        await page.screenshot({
                            path: path.join(__dirname, '../screenshots/08-modal-scrolled.png'),
                            fullPage: true
                        });

                        console.log('\n8. Procurando campo de Tags...');
                        const tagsInfo = await page.evaluate(() => {
                            const modal = document.querySelector('.eau-modal.active');
                            if (!modal) return null;

                            // Search for tags field
                            const tagsFieldSelectors = [
                                '#member-tags-field',
                                'input[name="mem_tags"]',
                                '[name="tags"]',
                                '.tags-input'
                            ];

                            let foundTagsField = null;
                            let foundSelector = null;

                            for (const selector of tagsFieldSelectors) {
                                const field = modal.querySelector(selector);
                                if (field) {
                                    foundTagsField = {
                                        tagName: field.tagName,
                                        id: field.id,
                                        name: field.name,
                                        className: field.className,
                                        type: field.type
                                    };
                                    foundSelector = selector;
                                    break;
                                }
                            }

                            // Get all labels to see if there's a Tags label
                            const labels = Array.from(modal.querySelectorAll('label')).map(l => ({
                                text: l.textContent.trim(),
                                for: l.getAttribute('for')
                            }));

                            // Get all fields
                            const allFields = Array.from(modal.querySelectorAll('input, select, textarea')).map(f => ({
                                tag: f.tagName,
                                type: f.type,
                                id: f.id,
                                name: f.name,
                                hasTagInId: (f.id || '').toLowerCase().includes('tag'),
                                hasTagInName: (f.name || '').toLowerCase().includes('tag')
                            }));

                            return {
                                foundTagsField,
                                foundSelector,
                                labels,
                                allFields,
                                hasTagsLabel: labels.some(l => l.text.toLowerCase().includes('tag'))
                            };
                        });

                        if (tagsInfo) {
                            console.log(`   Total de labels: ${tagsInfo.labels.length}`);
                            console.log(`   Tem label com "Tag": ${tagsInfo.hasTagsLabel}`);
                            console.log(`   Total de campos: ${tagsInfo.allFields.length}`);

                            if (tagsInfo.foundTagsField) {
                                console.log(`\n   ✅ CAMPO TAGS ENCONTRADO!`);
                                console.log(`   Seletor: ${tagsInfo.foundSelector}`);
                                console.log(`   Detalhes:`, tagsInfo.foundTagsField);

                                // Highlight the field
                                await page.evaluate((selector) => {
                                    const field = document.querySelector(`.eau-modal.active ${selector}`);
                                    if (field) {
                                        field.style.border = '5px solid lime';
                                        field.style.boxShadow = '0 0 20px lime';
                                        field.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                    }
                                }, tagsInfo.foundSelector);

                                await page.waitForTimeout(1000);

                                await page.screenshot({
                                    path: path.join(__dirname, '../screenshots/09-tags-field-highlighted.png'),
                                    fullPage: true
                                });

                                console.log('\n9. Tentando adicionar uma tag...');
                                const tagsField = await page.$(`.eau-modal.active ${tagsInfo.foundSelector}`);

                                if (tagsField) {
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
                                }

                            } else {
                                console.log('\n   ❌ CAMPO TAGS NÃO ENCONTRADO!');
                                console.log('\n   Labels disponíveis:');
                                tagsInfo.labels.slice(0, 10).forEach(l => {
                                    console.log(`   - "${l.text}" (for="${l.for}")`);
                                });

                                console.log('\n   Campos com "tag" no id/name:');
                                const tagFields = tagsInfo.allFields.filter(f => f.hasTagInId || f.hasTagInName);
                                if (tagFields.length > 0) {
                                    tagFields.forEach(f => {
                                        console.log(`   - ${f.tag} id="${f.id}" name="${f.name}"`);
                                    });
                                } else {
                                    console.log('   (nenhum campo encontrado com "tag" no id ou name)');
                                }
                            }
                        }

                    } else {
                        console.log('   ❌ Modal não abriu!');
                    }
                }

                // Found the right URL, no need to try others
                break;
            }
        }

        console.log('\n✅ Teste concluído!');

    } catch (error) {
        console.error('\n❌ Erro:', error.message);
        await page.screenshot({
            path: path.join(__dirname, '../screenshots/error.png'),
            fullPage: true
        });
    } finally {
        console.log('\nMantendo navegador aberto por 10 segundos...');
        await page.waitForTimeout(10000);
        await browser.close();
    }
})();
