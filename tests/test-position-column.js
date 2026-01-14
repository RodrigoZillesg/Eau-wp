const { chromium } = require('playwright');

(async () => {
    const browser = await chromium.launch({ headless: false });
    const context = await browser.newContext({ viewport: { width: 1920, height: 1080 } });
    const page = await context.newPage();

    try {
        console.log('1. Navegando para login...');
        await page.goto('http://eau-site.local/login/');
        await page.waitForTimeout(3000);
        
        console.log('2. Fazendo login...');
        await page.locator('input[type="text"], input[type="email"], input[name="log"], input[name="username"]').first().fill('rrzillesg@gmail.com');
        await page.locator('input[type="password"]').first().fill('Pl@ttyPl@tty');
        await page.locator('button[type="submit"], input[type="submit"]').first().click();
        await page.waitForTimeout(4000);
        
        console.log('   URL atual:', page.url());

        console.log('3. Navegando para Members Management...');
        await page.goto('http://eau-site.local/dashboard/manage-members/');
        await page.waitForTimeout(8000);
        
        console.log('   URL apos navegacao:', page.url());
        
        // Debug: ver estrutura da pagina
        const pageTitle = await page.title();
        console.log('   Titulo da pagina:', pageTitle);
        
        // Verificar se ha tabelas na pagina
        const tables = await page.locator('table').count();
        console.log('   Quantidade de tabelas na pagina:', tables);
        
        // Tentar diferentes seletores para a tabela
        const selectors = [
            '.eau-data-table',
            'table.eau-data-table',
            '#eau-members-table',
            '.eau-table',
            'table',
            '.members-table',
            '[class*="table"]'
        ];
        
        for (const sel of selectors) {
            const count = await page.locator(sel).count();
            if (count > 0) {
                console.log('   Seletor "' + sel + '" encontrou ' + count + ' elemento(s)');
            }
        }
        
        // Esperar mais e tentar novamente
        console.log('4. Aguardando mais tempo para tabela carregar...');
        await page.waitForTimeout(5000);
        
        // Verificar se ha loading/skeleton
        const skeleton = await page.locator('.eau-skeleton').count();
        console.log('   Skeletons na pagina:', skeleton);
        
        // Tentar pegar headers de qualquer tabela
        const allHeaders = await page.locator('table thead th').allTextContents();
        console.log('   Headers de todas as tabelas:', allHeaders);
        
        // Tentar pegar todas as linhas de qualquer tabela
        const allRows = await page.locator('table tbody tr').count();
        console.log('   Total de linhas em todas as tabelas:', allRows);
        
        if (allRows > 0) {
            const posIndex = allHeaders.findIndex(h => h.toUpperCase().includes('POSITION'));
            console.log('   Indice da coluna Position:', posIndex);
            
            if (posIndex >= 0) {
                console.log('\n5. Dados das primeiras 15 linhas:');
                const rows = await page.locator('table tbody tr').all();
                for (let i = 0; i < Math.min(15, rows.length); i++) {
                    const cells = await rows[i].locator('td').allTextContents();
                    const name = cells[1] ? cells[1].split('\n')[0].trim() : 'N/A';
                    const position = cells[posIndex] ? cells[posIndex].trim() : 'N/A';
                    console.log('   ' + (i+1) + '. ' + name.substring(0, 30).padEnd(30) + ' | Position: "' + position + '"');
                }
            }
        }

        console.log('\n6. Tirando screenshot...');
        await page.screenshot({ path: './position-column-test.png', fullPage: true });
        console.log('   Screenshot salvo!');

    } catch (error) {
        console.error('Erro:', error.message);
        await page.screenshot({ path: './position-column-error.png', fullPage: true });
    } finally {
        await browser.close();
    }
})();
