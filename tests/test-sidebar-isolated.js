const { chromium } = require('playwright');
const fs = require('fs');

async function testSidebarIsolated() {
    const browser = await chromium.launch({ headless: false });
    const context = await browser.newContext({
        viewport: { width: 1920, height: 1080 }
    });
    const page = await context.newPage();
    
    const screenshotDir = './presentation-screenshots/sidebar-test';
    
    try {
        if (!fs.existsSync(screenshotDir)) {
            fs.mkdirSync(screenshotDir, { recursive: true });
        }
        
        console.log('1. Fazendo login no WordPress Admin...');
        await page.goto('http://eau-site.local/wp-admin/', { waitUntil: 'networkidle' });
        await page.waitForTimeout(2000);
        
        // Verifica se precisa fazer login
        const needsLogin = await page.$('input[name="log"]');
        if (needsLogin) {
            console.log('   Preenchendo credenciais...');
            await page.fill('input[name="log"]', 'rrzillesg@gmail.com');
            await page.fill('input[name="pwd"]', 'Pl@ttyPl@tty');
            await Promise.all([
                page.waitForNavigation({ waitUntil: 'networkidle', timeout: 60000 }).catch(() => {}),
                page.click('#wp-submit')
            ]);
            await page.waitForTimeout(3000);
        }
        
        console.log('   URL atual: ' + page.url());
        await page.screenshot({ path: screenshotDir + '/10-wp-admin.png', fullPage: true });
        
        // Vai para a pagina de adicionar nova pagina
        console.log('2. Criando pagina de teste...');
        await page.goto('http://eau-site.local/wp-admin/post-new.php?post_type=page', { waitUntil: 'networkidle' });
        await page.waitForTimeout(3000);
        await page.screenshot({ path: screenshotDir + '/11-new-page.png', fullPage: true });
        
        // Verifica se tem Gutenberg ou editor classico
        const gutenbergEditor = await page.$('.block-editor');
        const classicEditor = await page.$('#content');
        
        if (gutenbergEditor) {
            console.log('   Editor Gutenberg detectado');
            
            // Fecha modal de boas vindas se existir
            const closeModal = await page.$('button[aria-label="Close"]');
            if (closeModal) {
                await closeModal.click();
                await page.waitForTimeout(500);
            }
            
            // Adiciona titulo
            const titleInput = await page.$('.editor-post-title__input, h1[aria-label="Add title"]');
            if (titleInput) {
                await titleInput.click();
                await page.keyboard.type('Teste Sidebar Menu');
            }
            
            // Adiciona bloco shortcode
            await page.keyboard.press('Enter');
            await page.keyboard.type('/shortcode');
            await page.waitForTimeout(1000);
            await page.keyboard.press('Enter');
            await page.waitForTimeout(500);
            await page.keyboard.type('[eau_sidebar_menu]');
            
            await page.screenshot({ path: screenshotDir + '/12-shortcode-added.png', fullPage: true });
            
        } else if (classicEditor) {
            console.log('   Editor Classico detectado');
            await page.fill('#title', 'Teste Sidebar Menu');
            await page.fill('#content', '[eau_sidebar_menu]');
            await page.screenshot({ path: screenshotDir + '/12-shortcode-added.png', fullPage: true });
        } else {
            console.log('   Nenhum editor detectado, verificando estrutura...');
            
            // Tira screenshot para analise
            await page.screenshot({ path: screenshotDir + '/12-page-structure.png', fullPage: true });
        }
        
        console.log('=== Teste concluido! ===');
        console.log('Verifique os screenshots para analisar a estrutura do editor.');
        
    } catch (error) {
        console.error('Erro durante o teste:', error);
        await page.screenshot({ path: screenshotDir + '/error-admin.png', fullPage: true }).catch(() => {});
    } finally {
        await browser.close();
    }
}

testSidebarIsolated();
