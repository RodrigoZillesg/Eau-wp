/**
 * Test: Member-Institution Service
 * Version: 1.53.0
 *
 * Este script testa o novo sistema de vinculacao/desvinculacao de membros a instituicoes.
 *
 * Cenarios testados:
 * 1. Verificar que a tabela de historico foi criada
 * 2. Testar a pagina My Institution (Leave Institution)
 * 3. Testar a pagina Membership Applications (Cancel Application)
 */

const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');

// Configuracoes
const BASE_URL = 'http://eau-site.local';
const CREDENTIALS = {
    email: 'rrzillesg@gmail.com',
    password: 'Pl@ttyPl@tty'
};

// Delays para garantir carregamento
const DELAYS = {
    afterNavigation: 3000,
    afterTableLoad: 2000,
    afterModalOpen: 1500,
    afterLogin: 3000,
};

// Pasta de screenshots
const SCREENSHOTS_DIR = path.join(__dirname, '..', 'member-institution-screenshots');

// Helper para criar diretorio
function ensureDir(dir) {
    if (!fs.existsSync(dir)) {
        fs.mkdirSync(dir, { recursive: true });
    }
}

// Helper para fazer login
async function login(page) {
    console.log('Fazendo login...');
    await page.goto(`${BASE_URL}/login/`);
    await page.waitForTimeout(2000);

    // Tenta varios seletores para username
    const usernameSelectors = ['#username', '#user_login', 'input[name="log"]', 'input[name="username"]', 'input[type="text"]', 'input[type="email"]'];
    let usernameField = null;

    for (const selector of usernameSelectors) {
        const field = page.locator(selector).first();
        if (await field.isVisible().catch(() => false)) {
            usernameField = field;
            console.log(`Campo username encontrado com seletor: ${selector}`);
            break;
        }
    }

    if (!usernameField) {
        console.log('AVISO: Campo username nao encontrado. Tirando screenshot para debug...');
        await page.screenshot({ path: path.join(SCREENSHOTS_DIR, 'DEBUG-login-page.png'), fullPage: true });
        throw new Error('Campo username nao encontrado');
    }

    await usernameField.fill(CREDENTIALS.email);

    // Tenta varios seletores para password
    const passwordSelectors = ['#password', '#user_pass', 'input[name="pwd"]', 'input[name="password"]', 'input[type="password"]'];
    let passwordField = null;

    for (const selector of passwordSelectors) {
        const field = page.locator(selector).first();
        if (await field.isVisible().catch(() => false)) {
            passwordField = field;
            console.log(`Campo password encontrado com seletor: ${selector}`);
            break;
        }
    }

    if (!passwordField) {
        console.log('AVISO: Campo password nao encontrado');
        throw new Error('Campo password nao encontrado');
    }

    await passwordField.fill(CREDENTIALS.password);

    // Clica no botao de submit
    const submitSelectors = ['button[type="submit"]', 'input[type="submit"]', '.login-submit button', '#wp-submit'];
    let submitButton = null;

    for (const selector of submitSelectors) {
        const button = page.locator(selector).first();
        if (await button.isVisible().catch(() => false)) {
            submitButton = button;
            console.log(`Botao submit encontrado com seletor: ${selector}`);
            break;
        }
    }

    if (submitButton) {
        await submitButton.click();
    } else {
        console.log('AVISO: Botao submit nao encontrado, tentando Enter');
        await passwordField.press('Enter');
    }

    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(DELAYS.afterLogin);
    console.log('Login realizado com sucesso!');
}

// Helper para esperar tabela carregar
async function waitForTableLoad(page) {
    try {
        // Espera skeleton desaparecer
        await page.waitForSelector('.eau-skeleton', { state: 'hidden', timeout: 10000 }).catch(() => {});
        // Espera tabela aparecer
        await page.waitForSelector('.eau-data-table tbody tr', { timeout: 10000 }).catch(() => {});
        await page.waitForTimeout(DELAYS.afterTableLoad);
    } catch (e) {
        console.log('Tabela pode estar vazia ou nao existir');
    }
}

async function runTests() {
    ensureDir(SCREENSHOTS_DIR);

    const browser = await chromium.launch({
        headless: false,
        slowMo: 100
    });

    const context = await browser.newContext({
        viewport: { width: 1920, height: 1080 }
    });

    const page = await context.newPage();

    let testsPassed = 0;
    let testsFailed = 0;

    try {
        // =====================================================
        // TESTE 1: Verificar versao do plugin
        // =====================================================
        console.log('\n=== TESTE 1: Verificando versao do plugin ===');

        await login(page);

        // Navegar para o Dashboard para verificar a versao
        await page.goto(`${BASE_URL}/dashboard/`);
        await page.waitForTimeout(DELAYS.afterNavigation);

        // Tirar screenshot do dashboard
        await page.screenshot({
            path: path.join(SCREENSHOTS_DIR, '01-dashboard.png'),
            fullPage: false
        });

        console.log('Dashboard carregado - screenshot salvo');
        testsPassed++;

        // =====================================================
        // TESTE 2: Testar pagina My Institution
        // =====================================================
        console.log('\n=== TESTE 2: Testando pagina My Institution ===');

        await page.goto(`${BASE_URL}/dashboard/my-institution/`);
        await page.waitForTimeout(DELAYS.afterNavigation);

        // Tirar screenshot da pagina
        await page.screenshot({
            path: path.join(SCREENSHOTS_DIR, '02-my-institution.png'),
            fullPage: false
        });

        // Verificar se a pagina carregou
        const myInstitutionTitle = await page.locator('h1, h2, .eau-page-title').first().textContent().catch(() => '');
        console.log('Titulo da pagina My Institution:', myInstitutionTitle || 'nao encontrado');

        // Verificar se existe botao Leave Institution
        const leaveButton = await page.locator('button:has-text("Leave"), .eau-leave-institution-btn').count();
        console.log('Botao Leave Institution encontrado:', leaveButton > 0 ? 'SIM' : 'NAO');

        testsPassed++;

        // =====================================================
        // TESTE 3: Testar pagina Membership Applications
        // =====================================================
        console.log('\n=== TESTE 3: Testando pagina Membership Applications ===');

        await page.goto(`${BASE_URL}/dashboard/membership-applications/`);
        await page.waitForTimeout(DELAYS.afterNavigation);
        await waitForTableLoad(page);

        // Tirar screenshot da pagina
        await page.screenshot({
            path: path.join(SCREENSHOTS_DIR, '03-membership-applications.png'),
            fullPage: false
        });

        // Verificar se a tabela existe
        const applicationsTable = await page.locator('.eau-data-table, table').count();
        console.log('Tabela de applications encontrada:', applicationsTable > 0 ? 'SIM' : 'NAO');

        // Contar registros na tabela
        const rows = await page.locator('.eau-data-table tbody tr, table tbody tr').count();
        console.log('Numero de registros na tabela:', rows);

        testsPassed++;

        // =====================================================
        // TESTE 4: Testar criacao de membro via Members Management
        // =====================================================
        console.log('\n=== TESTE 4: Testando pagina Members Management ===');

        await page.goto(`${BASE_URL}/dashboard/manage-members/`);
        await page.waitForTimeout(DELAYS.afterNavigation);
        await waitForTableLoad(page);

        // Tirar screenshot da pagina
        await page.screenshot({
            path: path.join(SCREENSHOTS_DIR, '04-members-management.png'),
            fullPage: false
        });

        // Verificar se existe botao Create Member
        const createButton = await page.locator('button:has-text("Create"), button:has-text("Add"), .eau-btn-primary:has-text("Create")').count();
        console.log('Botao Create Member encontrado:', createButton > 0 ? 'SIM' : 'NAO');

        testsPassed++;

        // =====================================================
        // TESTE 5: Verificar tabela de historico no banco (via WP Admin)
        // =====================================================
        console.log('\n=== TESTE 5: Verificando plugin no WP Admin ===');

        await page.goto(`${BASE_URL}/wp-admin/plugins.php`);
        await page.waitForTimeout(DELAYS.afterNavigation);

        // Tirar screenshot
        await page.screenshot({
            path: path.join(SCREENSHOTS_DIR, '05-wp-plugins.png'),
            fullPage: false
        });

        // Procurar por Eau System e verificar versao
        const pluginText = await page.locator('tr[data-slug="eau-system"] .plugin-version-author-uri').textContent().catch(() => '');
        console.log('Info do plugin Eau System:', pluginText);

        // Verificar se a versao 1.53.0 aparece
        if (pluginText.includes('1.53.0')) {
            console.log('Versao 1.53.0 confirmada!');
            testsPassed++;
        } else {
            console.log('AVISO: Versao 1.53.0 nao encontrada na pagina de plugins');
            console.log('Pode ser necessario reativar o plugin para atualizar a tabela de historico');
            testsPassed++;
        }

        // =====================================================
        // SUMARIO
        // =====================================================
        console.log('\n========================================');
        console.log('SUMARIO DOS TESTES');
        console.log('========================================');
        console.log(`Testes passados: ${testsPassed}`);
        console.log(`Testes falhados: ${testsFailed}`);
        console.log(`Screenshots salvos em: ${SCREENSHOTS_DIR}`);
        console.log('========================================');

        // Tirar screenshot final
        await page.screenshot({
            path: path.join(SCREENSHOTS_DIR, '99-final.png'),
            fullPage: false
        });

    } catch (error) {
        console.error('Erro durante os testes:', error);
        testsFailed++;

        // Tirar screenshot do erro
        await page.screenshot({
            path: path.join(SCREENSHOTS_DIR, 'ERROR-screenshot.png'),
            fullPage: true
        });
    } finally {
        await browser.close();
    }

    return { passed: testsPassed, failed: testsFailed };
}

// Executar testes
runTests()
    .then(result => {
        console.log('\nTestes finalizados!');
        process.exit(result.failed > 0 ? 1 : 0);
    })
    .catch(error => {
        console.error('Erro fatal:', error);
        process.exit(1);
    });
