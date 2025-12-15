/**
 * Eau System - Teste do Campo de Tags em Members
 *
 * Verifica se o campo de Tags aparece no modal de edição de membros
 *
 * @package EauSystem
 * @since 1.48.3
 */

const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');

// Configurações
const BASE_URL = 'http://eau-site.local';
const CREDENTIALS = {
    email: 'rrzillesg@gmail.com',
    password: 'Pl@ttyPl@tty'
};

// Cria diretório de screenshots se não existir
const screenshotsDir = path.join(__dirname, 'screenshots');
if (!fs.existsSync(screenshotsDir)) {
    fs.mkdirSync(screenshotsDir, { recursive: true });
}

/**
 * Tira screenshot com nome descritivo
 */
async function takeScreenshot(page, name) {
    const timestamp = new Date().toISOString().replace(/[:.]/g, '-').slice(0, 19);
    const filename = `members-tags_${name}_${timestamp}.png`;
    const filepath = path.join(screenshotsDir, filename);

    await page.screenshot({ path: filepath, fullPage: true });
    console.log(`✓ Screenshot salvo: ${filename}`);

    return filepath;
}

/**
 * Teste principal
 */
async function testMembersTags() {
    console.log('\n========================================');
    console.log('  Teste: Campo de Tags em Members');
    console.log('========================================\n');

    const browser = await chromium.launch({
        headless: false,
        slowMo: 500 // Slow motion para melhor visualização
    });

    const context = await browser.newContext({
        viewport: { width: 1920, height: 1080 }
    });

    const page = await context.newPage();

    try {
        // 1. Login
        console.log('1. Fazendo login...');
        await page.goto(`${BASE_URL}/login/`, { timeout: 60000 });
        await page.waitForLoadState('networkidle', { timeout: 60000 });
        await page.waitForTimeout(2000);

        // Verifica se campo username existe
        const usernameField = page.locator('#username, input[name="log"], input[type="text"]').first();
        const passwordField = page.locator('#password, input[name="pwd"], input[type="password"]').first();

        await usernameField.waitFor({ timeout: 10000 });
        await usernameField.fill(CREDENTIALS.email);
        await passwordField.fill(CREDENTIALS.password);

        const submitBtn = page.locator('button[type="submit"], input[type="submit"]').first();
        await submitBtn.click();
        await page.waitForLoadState('networkidle', { timeout: 60000 });

        console.log('✓ Login realizado');

        // 2. Navegar para Members Management
        console.log('\n2. Navegando para Members Management...');
        await page.goto(`${BASE_URL}/admin/members/`, { timeout: 60000 });
        await page.waitForLoadState('networkidle', { timeout: 60000 });
        await page.waitForTimeout(3000);

        // Aguarda a tabela carregar (vários seletores possíveis)
        const tableSelectors = '.eau-data-table, table, .eau-table, #members-table';
        await page.waitForSelector(tableSelectors, { timeout: 15000 }).catch(() => {
            console.log('⚠ Tabela não encontrada com seletores padrão, continuando...');
        });
        console.log('✓ Página carregada');

        // 3. Force reload
        console.log('\n3. Fazendo force reload (Ctrl+Shift+R)...');
        await page.reload({ waitUntil: 'networkidle', timeout: 60000 });
        await page.waitForTimeout(3000);
        console.log('✓ Página recarregada');

        // Aguarda um pouco para garantir que tudo carregou
        await page.waitForTimeout(2000);

        // 4. Screenshot da tabela
        console.log('\n4. Tirando screenshot da tabela...');
        await takeScreenshot(page, '01-table-view');

        // Verificar se existem badges/tags visíveis na tabela
        const tagsInTable = await page.locator('.eau-badge, .badge, [class*="tag"]').count();
        console.log(`✓ Tags/badges encontrados na tabela: ${tagsInTable}`);

        // 5. Encontrar e clicar no botão Edit
        console.log('\n5. Procurando botão Edit...');

        // Aguarda botões Edit estarem presentes
        await page.waitForSelector('[data-action="edit"], button:has-text("Edit"), .btn-edit, [title="Edit"]', { timeout: 15000 }).catch(() => {
            console.log('⚠ Botão Edit não encontrado, tirando screenshot...');
        });

        const editButtons = page.locator('[data-action="edit"], button:has-text("Edit")');
        const editCount = await editButtons.count();
        console.log(`✓ Encontrados ${editCount} botões Edit`);

        if (editCount > 0) {
            console.log('\n6. Clicando no primeiro botão Edit...');
            await editButtons.first().click();

            // Aguarda modal abrir
            await page.waitForTimeout(4000);
            console.log('✓ Aguardou abertura do modal');

            // 7. Screenshot do topo do modal
            console.log('\n7. Tirando screenshot do topo do modal...');
            await takeScreenshot(page, '02-modal-top');

            // 8. Rolar o modal até o final
            console.log('\n8. Rolando modal até o final...');

            // Tenta rolar dentro do modal body
            const modalBody = page.locator('.eau-modal-body, .eau-reg-modal-body').first();
            if (await modalBody.isVisible()) {
                await modalBody.evaluate(element => {
                    element.scrollTop = element.scrollHeight;
                });
                console.log('✓ Modal rolado até o final');
            }

            // Aguarda um pouco após rolar
            await page.waitForTimeout(1000);

            // 9. Screenshot do final do modal
            console.log('\n9. Tirando screenshot do final do modal...');
            await takeScreenshot(page, '03-modal-bottom');

            // 10. Procurar pelo campo de Tags
            console.log('\n10. Procurando campo de Tags...');

            // Várias formas de procurar o campo
            const possibleSelectors = [
                'input[name="mem_tags"]',
                'select[name="mem_tags"]',
                'div[data-field="mem_tags"]',
                'label:has-text("Tags")',
                '.tags-input',
                '[class*="tags"]'
            ];

            let tagsFieldFound = false;
            for (const selector of possibleSelectors) {
                const count = await page.locator(selector).count();
                if (count > 0) {
                    console.log(`✓ Campo encontrado com seletor: ${selector} (${count} ocorrências)`);
                    tagsFieldFound = true;
                } else {
                    console.log(`✗ Nada encontrado com: ${selector}`);
                }
            }

            // Também procurar por texto "Tags" ou "Tag"
            const tagsText = page.locator('label:has-text("Tags"), label:has-text("Tag"), span:has-text("Tags"), span:has-text("Tag")');
            const tagsTextCount = await tagsText.count();
            if (tagsTextCount > 0) {
                console.log(`✓ Texto "Tags" encontrado: ${tagsTextCount} ocorrências`);
                tagsFieldFound = true;
            }

            if (!tagsFieldFound) {
                console.log('✗ CAMPO DE TAGS NÃO ENCONTRADO no modal');
            }

            // 11. Screenshot final mostrando onde deveria estar o campo
            console.log('\n11. Screenshot final do modal...');
            await takeScreenshot(page, '04-modal-final');

        } else {
            console.log('✗ ERRO: Nenhum botão Edit encontrado na tabela');
        }

        console.log('\n========================================');
        console.log('  Teste Concluído!');
        console.log('========================================\n');
        console.log('Screenshots salvos em: tests/playwright/screenshots/');
        console.log('\n');

    } catch (error) {
        console.error('\n❌ ERRO durante o teste:', error.message);
        await takeScreenshot(page, 'error');
    } finally {
        // Aguarda 3 segundos antes de fechar para visualizar
        await page.waitForTimeout(3000);
        await browser.close();
    }
}

// Executa o teste
testMembersTags().catch(console.error);
