const { chromium } = require('playwright');
const path = require('path');

(async () => {
    const browser = await chromium.launch({
        headless: false,
        slowMo: 300
    });

    const context = await browser.newContext({
        viewport: { width: 1920, height: 1080 },
        ignoreHTTPSErrors: true
    });

    const page = await context.newPage();

    try {
        console.log('1. Login...');
        await page.goto('http://eau-site.local/login/');
        await page.waitForLoadState('domcontentloaded');
        await page.fill('input[name="log"]', 'rrzillesg@gmail.com');
        await page.fill('input[name="pwd"]', 'Pl@ttyPl@tty');
        await page.click('button[type="submit"]');
        await page.waitForLoadState('domcontentloaded');
        await page.waitForTimeout(2000);

        console.log('2. Acessando Members...');
        await page.goto('http://eau-site.local/dashboard/members/');
        await page.waitForLoadState('domcontentloaded');
        await page.waitForTimeout(3000);

        await page.screenshot({
            path: path.join(__dirname, '../screenshots/tags-01-members-page-loading.png'),
            fullPage: true
        });

        console.log('3. Aguardando skeleton loading desaparecer...');
        // Wait for skeleton to disappear
        await page.waitForFunction(() => {
            const skeleton = document.querySelector('.eau-skeleton-loading');
            return !skeleton || skeleton.offsetParent === null;
        }, { timeout: 20000 });

        await page.waitForTimeout(2000);

        await page.screenshot({
            path: path.join(__dirname, '../screenshots/tags-02-members-page-loaded.png'),
            fullPage: true
        });

        console.log('4. Clicando no Edit...');
        // O botão edit é um ícone na coluna ACTIONS
        const editButton = await page.waitForSelector('button[data-action="edit"], .eau-action-icon[data-action="edit"], i[data-lucide="edit"]', { timeout: 10000 });
        await editButton.scrollIntoViewIfNeeded();
        await editButton.click();
        await page.waitForTimeout(2000);

        console.log('5. Aguardando modal...');
        // Aguardar tempo fixo para modal aparecer
        await page.waitForTimeout(3000);

        await page.screenshot({
            path: path.join(__dirname, '../screenshots/tags-03-modal-opened.png'),
            fullPage: true
        });

        console.log('6. Rolando modal até o final...');
        await page.evaluate(() => {
            const modal = document.querySelector('.swal2-popup, .eau-modal.active, [role="dialog"]');
            if (modal) {
                const content = modal.querySelector('.swal2-html-container, .eau-modal-content, .modal-content');
                if (content) {
                    content.scrollTop = content.scrollHeight;
                } else {
                    modal.scrollTop = modal.scrollHeight;
                }
            }
        });
        await page.waitForTimeout(1500);

        await page.screenshot({
            path: path.join(__dirname, '../screenshots/tags-04-modal-scrolled.png'),
            fullPage: true
        });

        console.log('7. Procurando campo Tags...');
        const tagsInfo = await page.evaluate(() => {
            const modal = document.querySelector('.swal2-popup, .eau-modal.active, [role="dialog"]');
            if (!modal) return { found: false };

            // Procurar label com "Tags"
            const labels = Array.from(modal.querySelectorAll('label'));
            const tagsLabel = labels.find(l => l.textContent.toLowerCase().includes('tag'));

            if (tagsLabel) {
                const forId = tagsLabel.getAttribute('for');
                const field = forId ? modal.querySelector(`#${forId}`) : null;

                return {
                    found: !!field,
                    labelText: tagsLabel.textContent.trim(),
                    labelFor: forId,
                    fieldExists: !!field,
                    fieldId: field?.id,
                    fieldName: field?.name,
                    fieldType: field?.type
                };
            }

            // Buscar diretamente por selectors conhecidos
            const selectors = ['#member-tags-field', 'input[name="mem_tags"]'];
            for (const sel of selectors) {
                const field = modal.querySelector(sel);
                if (field) {
                    return {
                        found: true,
                        selector: sel,
                        fieldId: field.id,
                        fieldName: field.name,
                        fieldType: field.type
                    };
                }
            }

            // Listar todos os campos para debug
            const allFields = Array.from(modal.querySelectorAll('input, select, textarea')).map(f => ({
                tag: f.tagName,
                type: f.type,
                id: f.id,
                name: f.name
            }));

            return {
                found: false,
                allFields: allFields,
                totalFields: allFields.length
            };
        });

        console.log('\n=== RESULTADO ===');
        if (tagsInfo.found) {
            console.log('✅ CAMPO TAGS ENCONTRADO!');
            console.log('Label:', tagsInfo.labelText || 'N/A');
            console.log('ID:', tagsInfo.fieldId);
            console.log('Name:', tagsInfo.fieldName);
            console.log('Type:', tagsInfo.fieldType);

            // Highlight e screenshot
            const selector = tagsInfo.fieldId ? `#${tagsInfo.fieldId}` : `input[name="${tagsInfo.fieldName}"]`;
            await page.evaluate((sel) => {
                const modal = document.querySelector('.swal2-popup, .eau-modal.active, [role="dialog"]');
                if (modal) {
                    const field = modal.querySelector(sel);
                    if (field) {
                        field.style.border = '5px solid lime';
                        field.style.boxShadow = '0 0 20px lime';
                        field.scrollIntoView({ block: 'center' });
                    }
                }
            }, selector);

            await page.waitForTimeout(500);

            await page.screenshot({
                path: path.join(__dirname, '../screenshots/tags-05-FOUND-highlighted.png'),
                fullPage: true
            });

            console.log('\n8. Tentando adicionar tag...');
            // Find and click the field within the modal
            await page.evaluate((sel) => {
                const modal = document.querySelector('.swal2-popup, .eau-modal.active, [role="dialog"]');
                if (modal) {
                    const field = modal.querySelector(sel);
                    if (field) field.click();
                }
            }, selector);
            await page.waitForTimeout(300);
            await page.keyboard.type('Test Tag');
            await page.keyboard.press('Enter');
            await page.waitForTimeout(1000);

            await page.screenshot({
                path: path.join(__dirname, '../screenshots/tags-06-tag-added.png'),
                fullPage: true
            });

        } else {
            console.log('❌ CAMPO TAGS NÃO ENCONTRADO!');
            console.log('Total de campos no modal:', tagsInfo.totalFields);

            if (tagsInfo.allFields && tagsInfo.allFields.length > 0) {
                console.log('\nCampos disponíveis:');
                tagsInfo.allFields.forEach((f, i) => {
                    console.log(`${i + 1}. ${f.tag} - type="${f.type}" id="${f.id}" name="${f.name}"`);
                });
            }

            if (tagsInfo.labelText) {
                console.log('\n⚠️ Label "Tags" encontrado:', tagsInfo.labelText);
                console.log('   Mas o campo não existe! (for="' + tagsInfo.labelFor + '")');
            }
        }

        console.log('\n✅ Teste concluído!');

    } catch (error) {
        console.error('\n❌ Erro:', error.message);
        await page.screenshot({
            path: path.join(__dirname, '../screenshots/tags-ERROR.png'),
            fullPage: true
        });
    } finally {
        console.log('\nMantendo navegador aberto por 10 segundos...');
        await page.waitForTimeout(10000);
        await browser.close();
    }
})();
