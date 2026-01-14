const { chromium } = require('playwright');

(async () => {
    console.log('='.repeat(80));
    console.log('INVESTIGANDO PROBLEMA COM COLUNA POSITION');
    console.log('='.repeat(80));
    
    const browser = await chromium.launch({ headless: false, slowMo: 200 });
    const context = await browser.newContext({ viewport: { width: 1920, height: 1080 } });
    const page = await context.newPage();
    
    // Interceptar AJAX
    page.on('response', async (response) => {
        const url = response.url();
        if (url.includes('admin-ajax.php')) {
            try {
                const text = await response.text();
                const json = JSON.parse(text);
                if (json.data && json.data.members) {
                    console.log('
' + '='.repeat(60));
                    console.log('DADOS AJAX DOS MEMBROS');
                    console.log('='.repeat(60));
                    console.log('Total de membros:', json.data.members.length);
                    console.log('
Primeiros 5 membros:');
                    json.data.members.slice(0, 5).forEach((m, i) => {
                        console.log('
--- Membro', i+1, '---');
                        console.log('ID:', m.id);
                        console.log('Nome:', m.mem_firstname, m.mem_lastname);
                        console.log('mem_position:', m.mem_position);
                        console.log('position:', m.position);
                        const posKeys = Object.keys(m).filter(k => k.toLowerCase().includes('pos'));
                        console.log('Chaves com pos:', posKeys.length ? posKeys.join(', ') : 'nenhuma');
                        posKeys.forEach(k => console.log('  ', k, '=', m[k]));
                    });
                }
                if (json.data && json.data.filters && json.data.filters.positions) {
                    console.log('
' + '='.repeat(60));
                    console.log('FILTROS DE POSITION DISPONIVEIS');
                    console.log('='.repeat(60));
                    console.log(JSON.stringify(json.data.filters.positions, null, 2));
                }
            } catch(e) {}
        }
    });
    
    // Login
    console.log('
[1] Fazendo login...');
    await page.goto('http://eau-site.local/login/');
    await page.waitForTimeout(2000);
    await page.fill('input[name="log"]', 'rrzillesg@gmail.com');
    await page.fill('input[name="pwd"]', 'Pl@ttyPl@tty');
    await page.click('button[type="submit"]');
    await page.waitForTimeout(4000);
    console.log('Login OK! URL:', page.url());
    
    // Ir para membros
    console.log('
[2] Navegando para pagina de membros...');
    await page.goto('http://eau-site.local/dashboard/manage-members/');
    await page.waitForTimeout(8000);
    
    // Aguardar tabela
    console.log('
[3] Aguardando tabela carregar...');
    try {
        await page.waitForSelector('.eau-data-table tbody tr', { timeout: 15000 });
        console.log('Tabela carregada!');
    } catch(e) {
        console.log('Aguardando mais...');
    }
    await page.waitForTimeout(3000);
    
    // Verificar HTML
    console.log('
[4] Analisando HTML da tabela...');
    const result = await page.evaluate(() => {
        const headers = Array.from(document.querySelectorAll('.eau-data-table thead th')).map(h => h.textContent.trim());
        const posIdx = headers.findIndex(h => h.includes('Position'));
        const rows = document.querySelectorAll('.eau-data-table tbody tr');
        const cells = [];
        rows.forEach((r, i) => {
            if (i < 10) {
                const tds = r.querySelectorAll('td');
                const posCell = tds[posIdx];
                cells.push({ 
                    row: i+1, 
                    positionHTML: posCell ? posCell.innerHTML : 'N/A',
                    positionText: posCell ? posCell.textContent.trim() : 'N/A'
                });
            }
        });
        return { headers, posIdx, totalRows: rows.length, cells };
    });
    
    console.log('
' + '='.repeat(60));
    console.log('ANALISE DO HTML DA TABELA');
    console.log('='.repeat(60));
    console.log('Headers:', result.headers.join(' | '));
    console.log('Indice da coluna Position:', result.posIdx);
    console.log('Total de linhas:', result.totalRows);
    console.log('
Conteudo das celulas Position (primeiras 10):');
    result.cells.forEach(c => {
        console.log('  Linha', c.row + ':', JSON.stringify(c.positionText));
    });
    
    await page.screenshot({ path: 'screenshots/position-investigation-table.png' });
    console.log('
Screenshot da tabela salvo!');
    
    // Abrir modal de um membro
    console.log('
[5] Abrindo modal de um membro...');
    const viewBtn = await page.;
    if (viewBtn) {
        await viewBtn.click();
        await page.waitForTimeout(3000);
        await page.screenshot({ path: 'screenshots/position-investigation-modal.png' });
        console.log('Screenshot do modal salvo!');
    }
    
    console.log('
' + '='.repeat(80));
    console.log('INVESTIGACAO CONCLUIDA');
    console.log('='.repeat(80));
    
    await page.waitForTimeout(5000);
    await browser.close();
})();
