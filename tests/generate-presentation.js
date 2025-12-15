/**
 * Script para gerar screenshots de apresentação
 * Captura telas do sistema para cada tipo de usuário
 */
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

// Diretório para salvar screenshots
const screenshotsDir = path.join(__dirname, 'presentation-screenshots');

// Credenciais dos usuários de teste
const users = {
    superAdmin: {
        email: 'rrzillesg@gmail.com',
        password: 'Pl@ttyPl@tty',
        type: 'superAdmin'
    }
};

// Páginas para capturar por tipo de usuário
const pages = {
    superAdmin: [
        { name: '01-dashboard', url: '/dashboard/', description: 'Dashboard Principal' },
        { name: '02-members', url: '/dashboard/members/', description: 'Gerenciamento de Membros' },
        { name: '03-institutions', url: '/dashboard/manage-institutions/', description: 'Gerenciamento de Instituições' },
        { name: '04-activities', url: '/dashboard/manage-activities/', description: 'Gerenciamento de Atividades CPD' },
        { name: '05-events', url: '/dashboard/events/', description: 'Gerenciamento de Eventos' },
        { name: '06-payments', url: '/dashboard/payments/', description: 'Gerenciamento de Pagamentos' },
        { name: '07-membership-applications', url: '/dashboard/membership-applications/', description: 'Aplicações de Membership' },
        { name: '08-categories', url: '/dashboard/manage-categories/', description: 'Categorias CPD' },
        { name: '09-my-profile', url: '/dashboard/profile/', description: 'Meu Perfil' },
        { name: '10-my-cpds', url: '/dashboard/my-cpds/', description: 'Meus CPDs' },
        { name: '11-events-frontend', url: '/events/', description: 'Página de Eventos (Frontend)' },
    ],
    Admin: [
        { name: '01-dashboard', url: '/dashboard/', description: 'Dashboard Principal' },
        { name: '02-members', url: '/dashboard/members/', description: 'Gerenciamento de Membros' },
        { name: '03-institutions', url: '/dashboard/manage-institutions/', description: 'Gerenciamento de Instituições' },
        { name: '04-activities', url: '/dashboard/manage-activities/', description: 'Gerenciamento de Atividades CPD' },
        { name: '05-events', url: '/dashboard/events/', description: 'Gerenciamento de Eventos' },
        { name: '06-payments', url: '/dashboard/payments/', description: 'Gerenciamento de Pagamentos' },
        { name: '07-membership-applications', url: '/dashboard/membership-applications/', description: 'Aplicações de Membership' },
        { name: '08-my-profile', url: '/dashboard/profile/', description: 'Meu Perfil' },
        { name: '09-events-frontend', url: '/events/', description: 'Página de Eventos (Frontend)' },
    ],
    institutionAdmin: [
        { name: '01-dashboard', url: '/dashboard/', description: 'Dashboard Principal' },
        { name: '02-my-institution', url: '/dashboard/my-institution/', description: 'Minha Instituição' },
        { name: '03-my-profile', url: '/dashboard/profile/', description: 'Meu Perfil' },
        { name: '04-my-cpds', url: '/dashboard/my-cpds/', description: 'Meus CPDs' },
        { name: '05-events-frontend', url: '/events/', description: 'Página de Eventos (Frontend)' },
        { name: '06-courses', url: '/dashboard/courses/', description: 'Cursos OpenLearning' },
    ],
    Member: [
        { name: '01-dashboard', url: '/dashboard/', description: 'Dashboard Principal' },
        { name: '02-my-profile', url: '/dashboard/profile/', description: 'Meu Perfil' },
        { name: '03-my-cpds', url: '/dashboard/my-cpds/', description: 'Meus CPDs' },
        { name: '04-membership-selection', url: '/membership-selection/', description: 'Seleção de Membership' },
        { name: '05-events-frontend', url: '/events/', description: 'Página de Eventos (Frontend)' },
        { name: '06-courses', url: '/dashboard/courses/', description: 'Cursos OpenLearning' },
    ]
};

async function login(page, email, password) {
    console.log(`   Fazendo login com ${email}...`);
    await page.goto('http://eau-site.local/login/', { waitUntil: 'domcontentloaded', timeout: 60000 });
    await page.waitForSelector('input[name="log"]', { timeout: 30000 });
    await page.fill('input[name="log"]', email);
    await page.fill('input[name="pwd"]', password);
    await page.click('button[name="wp-submit"]');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);
}

async function logout(page) {
    // Ir para wp-admin para fazer logout
    await page.goto('http://eau-site.local/wp-login.php?action=logout');
    await page.waitForTimeout(1000);
    // Clicar no link de logout se aparecer confirmação
    const logoutLink = await page.locator('a:has-text("log out")');
    if (await logoutLink.isVisible()) {
        await logoutLink.click();
        await page.waitForLoadState('networkidle');
    }
    await page.waitForTimeout(1000);
}

async function captureScreenshots(page, userType, userPages, folder) {
    const userFolder = path.join(folder, userType);
    if (!fs.existsSync(userFolder)) {
        fs.mkdirSync(userFolder, { recursive: true });
    }

    for (const pageInfo of userPages) {
        try {
            console.log(`   Capturando: ${pageInfo.description}...`);
            await page.goto(`http://eau-site.local${pageInfo.url}`, {
                waitUntil: 'networkidle',
                timeout: 60000
            });
            await page.waitForTimeout(2000);

            // Scroll para carregar lazy content
            await page.evaluate(() => window.scrollTo(0, 0));
            await page.waitForTimeout(500);

            const screenshotPath = path.join(userFolder, `${pageInfo.name}.png`);
            await page.screenshot({
                path: screenshotPath,
                fullPage: true
            });
            console.log(`   ✅ Salvo: ${pageInfo.name}.png`);
        } catch (error) {
            console.log(`   ❌ Erro em ${pageInfo.name}: ${error.message}`);
        }
    }
}

async function main() {
    console.log('='.repeat(60));
    console.log('GERADOR DE SCREENSHOTS PARA APRESENTAÇÃO');
    console.log('='.repeat(60));

    // Criar diretório principal
    if (!fs.existsSync(screenshotsDir)) {
        fs.mkdirSync(screenshotsDir, { recursive: true });
    }

    const browser = await chromium.launch({
        headless: false,
        slowMo: 50
    });

    const context = await browser.newContext({
        viewport: { width: 1920, height: 1080 }
    });

    const page = await context.newPage();
    page.setDefaultTimeout(60000);

    try {
        // ========================================
        // 1. SUPER ADMIN
        // ========================================
        console.log('\n📸 1. Capturando screenshots do SUPER ADMIN...');
        await login(page, users.superAdmin.email, users.superAdmin.password);
        await captureScreenshots(page, 'superAdmin', pages.superAdmin, screenshotsDir);
        await logout(page);

        // ========================================
        // 2. Verificar/Criar outros usuários
        // ========================================
        console.log('\n🔍 Verificando usuários de teste...');

        // Login novamente como superAdmin para verificar usuários
        await login(page, users.superAdmin.email, users.superAdmin.password);

        // Ir para Members para verificar usuários existentes
        await page.goto('http://eau-site.local/dashboard/members/');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(3000);

        // Buscar por usuários de cada tipo
        // Vou verificar se existem usuários de teste

        console.log('\n✅ Screenshots do superAdmin capturados!');
        console.log('\n📝 Para capturar os outros tipos de usuário, preciso das credenciais.');
        console.log('   Por favor, forneça os emails/senhas dos seguintes tipos:');
        console.log('   - Admin');
        console.log('   - institutionAdmin');
        console.log('   - Member (ativo)');

    } catch (error) {
        console.log('❌ Erro:', error.message);
    } finally {
        await browser.close();
    }

    console.log('\n='.repeat(60));
    console.log('Screenshots salvos em:', screenshotsDir);
    console.log('='.repeat(60));
}

main();
