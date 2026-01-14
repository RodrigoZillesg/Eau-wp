const { chromium } = require('playwright');

(async () => {
    const browser = await chromium.launch({ headless: false });
    const page = await browser.newPage();
    page.setDefaultTimeout(30000);
    
    console.log('=== TESTE DOS BOTOES BULK v1.58.7 ===');
    console.log('');
    
    try {
        // 1. Login
        console.log('1. Fazendo login...');
        await page.goto('http://eau-site.local/login/');
        await page.waitForTimeout(2000);
        
        await page.fill('input[name="log"]', 'rrzillesg@gmail.com');
        await page.fill('input[name="pwd"]', 'Pl@ttyPl@tty');
        await page.click('button[type="submit"]');
        await page.waitForTimeout(4000);
        
        console.log('   Login realizado!');
        console.log('');
        
        // 2. Navegar para Members
        console.log('2. Navegando para Members Management...');
        await page.goto('http://eau-site.local/dashboard/manage-members/');
        await page.waitForTimeout(8000);
        
        console.log('   Pagina carregada!');
        console.log('');
        
        // 3. Verificar versao
        console.log('3. Verificando versao do plugin...');
        const version = await page.evaluate(() => {
            const scripts = document.querySelectorAll('script[src*="eau-members"]');
            for (const s of scripts) {
                const match = s.src.match(/ver=([^&]+)/);
                if (match) return match[1];
            }
            return 'nao encontrada';
        });
        console.log('   Versao: ' + version);
        console.log('');
        
        // 4. Verificar todos os botoes com "Delete" ou "Tags" no texto
        console.log('4. Verificando botoes bulk na pagina...');
        const buttonsInfo = await page.evaluate(() => {
            const result = [];
            const buttons = document.querySelectorAll('button');
            buttons.forEach(b => {
                const text = b.textContent.trim();
                if (text.includes('Delete') || text.includes('Tags')) {
                    const style = window.getComputedStyle(b);
                    result.push({
                        text: text,
                        className: b.className,
                        display: style.display,
                        visibility: style.visibility,
                        opacity: style.opacity
                    });
                }
            });
            return result;
        });
        
        console.log('   Botoes encontrados:');
        buttonsInfo.forEach(b => {
            const hidden = b.display === 'none' || b.visibility === 'hidden';
            console.log('   - "' + b.text.substring(0, 30) + '"');
            console.log('     display: ' + b.display + ', visibility: ' + b.visibility);
            console.log('     ESTADO: ' + (hidden ? 'ESCONDIDO' : 'VISIVEL'));
        });
        console.log('');
        
        // 5. Encontrar e clicar em checkbox
        console.log('5. Procurando checkbox para selecionar...');
        const checkboxSelector = '#members-table tbody input[type="checkbox"], .eau-member-checkbox';
        const checkbox = await page.$(checkboxSelector);
        
        if (checkbox) {
            console.log('   Checkbox encontrado, clicando...');
            await checkbox.click();
            await page.waitForTimeout(500);
            
            // Screenshot apos selecao
            await page.screenshot({ path: 'C:/Users/rrzil/Local Sites/eau-site/after-selection.png' });
            
            // Verificar botoes apos selecao
            console.log('');
            console.log('6. Estado dos botoes APOS selecao:');
            const buttonsAfter = await page.evaluate(() => {
                const result = [];
                const buttons = document.querySelectorAll('button');
                buttons.forEach(b => {
                    const text = b.textContent.trim();
                    if (text.includes('Delete') || text.includes('Tags')) {
                        const style = window.getComputedStyle(b);
                        result.push({
                            text: text,
                            display: style.display,
                            visibility: style.visibility
                        });
                    }
                });
                return result;
            });
            
            buttonsAfter.forEach(b => {
                const hidden = b.display === 'none' || b.visibility === 'hidden';
                console.log('   - "' + b.text.substring(0, 30) + '"');
                console.log('     ESTADO: ' + (hidden ? 'ESCONDIDO' : 'VISIVEL'));
            });
            
            // 7. Desmarcar
            console.log('');
            console.log('7. Desmarcando checkbox...');
            await checkbox.click();
            await page.waitForTimeout(500);
            
            // Verificar botoes apos desmarcar
            console.log('');
            console.log('8. Estado dos botoes APOS desmarcar:');
            const buttonsFinal = await page.evaluate(() => {
                const result = [];
                const buttons = document.querySelectorAll('button');
                buttons.forEach(b => {
                    const text = b.textContent.trim();
                    if (text.includes('Delete') || text.includes('Tags')) {
                        const style = window.getComputedStyle(b);
                        result.push({
                            text: text,
                            display: style.display,
                            visibility: style.visibility
                        });
                    }
                });
                return result;
            });
            
            buttonsFinal.forEach(b => {
                const hidden = b.display === 'none' || b.visibility === 'hidden';
                console.log('   - "' + b.text.substring(0, 30) + '"');
                console.log('     ESTADO: ' + (hidden ? 'ESCONDIDO' : 'VISIVEL'));
            });
            
        } else {
            console.log('   ERRO: Checkbox nao encontrado');
        }
        
        await page.screenshot({ path: 'C:/Users/rrzil/Local Sites/eau-site/final-state.png' });
        console.log('');
        console.log('=== TESTE CONCLUIDO ===');
        
    } catch (error) {
        console.error('Erro:', error.message);
        await page.screenshot({ path: 'C:/Users/rrzil/Local Sites/eau-site/error.png' });
    }
    
    await page.waitForTimeout(3000);
    await browser.close();
})();
