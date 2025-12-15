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
        console.log('1. Navegando para página de login...');
        await page.goto('http://eau-site.local/login/', { waitUntil: 'domcontentloaded' });
        await page.waitForTimeout(2000);

        console.log('2. Fazendo login...');
        await page.fill('input[name="log"]', 'rrzillesg@gmail.com');
        await page.fill('input[name="pwd"]', 'Pl@ttyPl@tty');
        await page.click('button[type="submit"]');
        await page.waitForTimeout(3000);

        console.log('3. Navegando para página de Members (URL correta)...');
        await page.goto('http://eau-site.local/dashboard/members/', { waitUntil: 'networkidle', timeout: 30000 });
        await page.waitForTimeout(2000);

        await page.screenshot({
            path: path.join(__dirname, '../screenshots/final-01-members-page-initial.png'),
            fullPage: true
        });

        console.log('4. Aguardando tabela carregar...');
        await page.waitForSelector('.eau-data-table', { timeout: 10000 });
        await page.waitForTimeout(2000);

        await page.screenshot({
            path: path.join(__dirname, '../screenshots/final-02-table-loaded.png'),
            fullPage: true
        });

        console.log('5. Aguardando dados da tabela...');
        await page.waitForSelector('.eau-data-table tbody tr', { timeout: 15000 });
        await page.waitForTimeout(1000);

        console.log('6. Procurando botão Edit...');
        const editButton = await page.$('.eau-action-button[data-action="edit"]');

        if (!editButton) {
            throw new Error('Botão Edit não encontrado!');
        }

        console.log('   ✅ Botão Edit encontrado!');

        await editButton.scrollIntoViewIfNeeded();
        await page.waitForTimeout(500);

        // Highlight edit button
        await editButton.evaluate(el => {
            el.style.border = '3px solid red';
            el.style.boxShadow = '0 0 10px red';
        });

        await page.screenshot({
            path: path.join(__dirname, '../screenshots/final-03-edit-button-highlighted.png'),
            fullPage: true
        });

        console.log('7. Clicando no botão Edit...');
        await editButton.click();
        await page.waitForTimeout(2000);

        console.log('8. Aguardando modal abrir...');
        await page.waitForSelector('.eau-modal.active', { timeout: 5000 });
        await page.waitForTimeout(1500);

        await page.screenshot({
            path: path.join(__dirname, '../screenshots/final-04-modal-opened.png'),
            fullPage: true
        });

        console.log('9. Analisando campos do modal...');
        const modalInfo = await page.evaluate(() => {
            const modal = document.querySelector('.eau-modal.active');
            if (!modal) return null;

            const labels = Array.from(modal.querySelectorAll('label')).map(l => ({
                text: l.textContent.trim(),
                for: l.getAttribute('for')
            }));

            const fields = Array.from(modal.querySelectorAll('input, select, textarea')).map(f => ({
                tag: f.tagName,
                type: f.type,
                id: f.id,
                name: f.name
            }));

            return { labels, fields };
        });

        console.log(`   Total de labels: ${modalInfo.labels.length}`);
        console.log(`   Total de campos: ${modalInfo.fields.length}`);

        console.log('\n10. Rolando modal até o final para encontrar Tags...');
        await page.evaluate(() => {
            const modalContent = document.querySelector('.eau-modal.active .eau-modal-content');
            if (modalContent) {
                modalContent.scrollTop = modalContent.scrollHeight;
            }
        });
        await page.waitForTimeout(1500);

        await page.screenshot({
            path: path.join(__dirname, '../screenshots/final-05-modal-scrolled-to-bottom.png'),
            fullPage: true
        });

        console.log('\n11. Procurando campo de Tags...');
        const tagsResult = await page.evaluate(() => {
            const modal = document.querySelector('.eau-modal.active');
            if (!modal) return { found: false };

            // Try multiple selectors for tags field
            const selectors = [
                '#member-tags-field',
                'input[name="mem_tags"]',
                '[name="tags"]',
                '.tags-input',
                '[id*="tag" i]',
                '[name*="tag" i]'
            ];

            for (const selector of selectors) {
                const field = modal.querySelector(selector);
                if (field) {
                    return {
                        found: true,
                        selector: selector,
                        id: field.id,
                        name: field.name,
                        type: field.type,
                        className: field.className,
                        tagName: field.tagName
                    };
                }
            }

            // Check if there's a label with "Tags" text
            const tagsLabel = Array.from(modal.querySelectorAll('label')).find(l =>
                l.textContent.toLowerCase().includes('tag')
            );

            if (tagsLabel) {
                const forAttr = tagsLabel.getAttribute('for');
                const fieldByFor = forAttr ? modal.querySelector(`#${forAttr}`) : null;

                if (fieldByFor) {
                    return {
                        found: true,
                        foundVia: 'label',
                        selector: `#${forAttr}`,
                        id: fieldByFor.id,
                        name: fieldByFor.name,
                        type: fieldByFor.type,
                        className: fieldByFor.className,
                        tagName: fieldByFor.tagName,
                        labelText: tagsLabel.textContent.trim()
                    };
                }

                return {
                    found: false,
                    hasTagsLabel: true,
                    labelText: tagsLabel.textContent.trim(),
                    labelFor: forAttr
                };
            }

            return { found: false };
        });

        if (tagsResult.found) {
            console.log('   ✅ CAMPO TAGS ENCONTRADO!');
            console.log(`   Seletor: ${tagsResult.selector}`);
            console.log(`   ID: ${tagsResult.id}`);
            console.log(`   Name: ${tagsResult.name}`);
            console.log(`   Type: ${tagsResult.type}`);
            console.log(`   Tag: ${tagsResult.tagName}`);
            if (tagsResult.foundVia) {
                console.log(`   Encontrado via: ${tagsResult.foundVia}`);
                console.log(`   Label: "${tagsResult.labelText}"`);
            }

            // Highlight the tags field
            await page.evaluate((selector) => {
                const field = document.querySelector(`.eau-modal.active ${selector}`);
                if (field) {
                    field.style.border = '5px solid lime';
                    field.style.boxShadow = '0 0 20px lime';
                    field.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }, tagsResult.selector);

            await page.waitForTimeout(1000);

            await page.screenshot({
                path: path.join(__dirname, '../screenshots/final-06-tags-field-highlighted.png'),
                fullPage: true
            });

            console.log('\n12. Tentando adicionar uma tag...');
            const tagsField = await page.$(`.eau-modal.active ${tagsResult.selector}`);

            if (tagsField) {
                // Get current value
                const currentValue = await tagsField.inputValue();
                console.log(`   Valor atual: "${currentValue}"`);

                await tagsField.click();
                await page.waitForTimeout(500);

                // Type a test tag
                await page.keyboard.type('Test Tag');
                await page.waitForTimeout(500);
                await page.keyboard.press('Enter');
                await page.waitForTimeout(1500);

                await page.screenshot({
                    path: path.join(__dirname, '../screenshots/final-07-tag-added-attempt.png'),
                    fullPage: true
                });

                // Check if tag was added
                const afterValue = await tagsField.inputValue();
                console.log(`   Valor após: "${afterValue}"`);

                // Check if there are any tag pills/badges created
                const tagPills = await page.evaluate(() => {
                    const modal = document.querySelector('.eau-modal.active');
                    const pillElements = modal.querySelectorAll('.tag, .badge, [class*="tag-"]');
                    return Array.from(pillElements).map(el => ({
                        text: el.textContent.trim(),
                        className: el.className
                    }));
                });

                if (tagPills.length > 0) {
                    console.log('   Tags visualizadas:');
                    tagPills.forEach(pill => {
                        console.log(`   - "${pill.text}" (class: ${pill.className})`);
                    });
                }

                console.log('   ✅ Tentativa de adicionar tag concluída!');
            }

        } else {
            console.log('   ❌ CAMPO TAGS NÃO ENCONTRADO NO MODAL!');

            if (tagsResult.hasTagsLabel) {
                console.log(`   ⚠️ Mas existe um label com "Tags": "${tagsResult.labelText}"`);
                console.log(`   Label for: ${tagsResult.labelFor}`);
            }

            console.log('\n   Labels disponíveis:');
            modalInfo.labels.forEach(label => {
                console.log(`   - "${label.text}" (for="${label.for}")`);
            });

            console.log('\n   Campos disponíveis:');
            modalInfo.fields.forEach(field => {
                console.log(`   - ${field.tag} type="${field.type}" id="${field.id}" name="${field.name}"`);
            });
        }

        console.log('\n✅ Teste concluído! Screenshots salvos em tests/screenshots/final-*.png');

    } catch (error) {
        console.error('\n❌ Erro durante o teste:', error.message);
        await page.screenshot({
            path: path.join(__dirname, '../screenshots/final-error.png'),
            fullPage: true
        });
    } finally {
        console.log('\nMantendo navegador aberto por 15 segundos para revisão...');
        await page.waitForTimeout(15000);
        await browser.close();
    }
})();
