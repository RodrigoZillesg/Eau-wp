/**
 * Script para capturar screenshots de todas as features do sistema
 *
 * IMPORTANTE: Este script usa delays generosos para garantir que:
 * - Páginas carreguem completamente (incluindo AJAX)
 * - Skeleton loading termine
 * - Modais abram e renderizem completamente
 * - Dados da tabela sejam carregados
 *
 * Delays recomendados:
 * - Após navegação: 5-6 segundos (páginas com tabelas AJAX)
 * - Após abrir modal: 2-3 segundos
 * - Após clicar em botões/tabs: 1 segundo
 *
 * URLs CORRETAS (verificadas):
 * - Members: /admin/members/ ou /dashboard/manage-members/
 * - Institutions: /dashboard/manage-institutions/ (NÃO /admin/institutions/)
 * - Activities: /dashboard/manage-activities/ (NÃO /admin/activities/)
 * - Categories: /dashboard/manage-categories/ (NÃO /admin/categories/)
 * - My Profile: /profile/ (redireciona para /dashboard/profile/)
 */

const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');

// Configuração de delays (em ms)
const DELAYS = {
    afterNavigation: 6000,      // Após navegar para nova página
    afterTableLoad: 4000,       // Após tabela AJAX carregar
    afterModalOpen: 2500,       // Após abrir modal
    afterTabClick: 1000,        // Após clicar em tab/botão
    afterLogin: 4000,           // Após fazer login
};

(async () => {
    const browser = await chromium.launch({ headless: false });
    const context = await browser.newContext({ viewport: { width: 1920, height: 1080 } });
    const page = await context.newPage();
    page.setDefaultTimeout(60000);

    const screenshotsDir = path.join(__dirname, 'presentation-screenshots', 'features');

    // Ensure directory exists
    if (!fs.existsSync(screenshotsDir)) {
        fs.mkdirSync(screenshotsDir, { recursive: true });
    }

    // Helper para esperar carregamento completo
    async function waitForPageLoad() {
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(DELAYS.afterNavigation);
    }

    // Helper para esperar tabela AJAX carregar
    async function waitForTableLoad() {
        try {
            // Espera skeleton desaparecer se existir
            await page.waitForSelector('.eau-skeleton', { state: 'hidden', timeout: 10000 }).catch(() => {});
            // Espera tabela aparecer
            await page.waitForSelector('.eau-data-table tbody tr, .eau-table tbody tr, table tbody tr', { timeout: 10000 }).catch(() => {});
        } catch (e) {
            // Se não encontrar, apenas espera
        }
        await page.waitForTimeout(DELAYS.afterTableLoad);
    }

    // Helper para screenshot seguro
    async function safeScreenshot(name, description) {
        try {
            const filepath = path.join(screenshotsDir, name);
            await page.screenshot({ path: filepath, fullPage: false });
            console.log(`   OK: ${name} - ${description}`);
            return true;
        } catch (e) {
            console.log(`   ERRO: ${name} - ${e.message}`);
            return false;
        }
    }

    // Helper para navegar com espera
    async function navigateTo(url, description) {
        console.log(`\n>>> Navegando para: ${description}`);
        await page.goto(url, { waitUntil: 'networkidle' });
        await waitForPageLoad();
        // Log da URL final para debug
        const finalUrl = page.url();
        if (!finalUrl.includes(url.split('/').slice(-2, -1)[0])) {
            console.log(`   AVISO: Redirecionou para ${finalUrl}`);
        }
    }

    try {
        // ============================================
        // LOGIN
        // ============================================
        console.log('='.repeat(50));
        console.log('INICIANDO CAPTURA DE SCREENSHOTS');
        console.log('='.repeat(50));

        console.log('\n1. Fazendo login como SuperAdmin...');
        await page.goto('http://eau-site.local/login/', { waitUntil: 'networkidle' });
        await page.waitForTimeout(2000);

        await page.fill('input[name="log"], input#user_login', 'rrzillesg@gmail.com');
        await page.fill('input[name="pwd"], input#user_pass', 'Pl@ttyPl@tty');
        await page.click('button[type="submit"], input[type="submit"]');
        await page.waitForTimeout(DELAYS.afterLogin);
        await page.waitForLoadState('networkidle');
        console.log('   Login OK');

        // ============================================
        // DASHBOARD
        // ============================================
        await navigateTo('http://eau-site.local/dashboard/', 'Dashboard');
        await page.waitForTimeout(3000);
        await safeScreenshot('dashboard-01-main.png', 'Dashboard principal');

        // ============================================
        // MEMBERS MANAGEMENT
        // ============================================
        await navigateTo('http://eau-site.local/admin/members/', 'Members Management');
        await waitForTableLoad();
        await safeScreenshot('members-01-table.png', 'Tabela de membros');

        // Abrir modal de visualização
        const memberViewBtn = page.locator('.eau-action-view').first();
        if (await memberViewBtn.isVisible()) {
            console.log('   Abrindo modal de visualização de membro...');
            await memberViewBtn.click();
            await page.waitForTimeout(DELAYS.afterModalOpen);
            await safeScreenshot('members-03-view-modal.png', 'Modal de visualização de membro');
            await page.keyboard.press('Escape');
            await page.waitForTimeout(DELAYS.afterTabClick);
        }

        // ============================================
        // INSTITUTIONS MANAGEMENT (URL CORRETA!)
        // ============================================
        await navigateTo('http://eau-site.local/dashboard/manage-institutions/', 'Institutions Management');
        await waitForTableLoad();
        await safeScreenshot('institutions-01-table.png', 'Tabela de instituições');

        // ============================================
        // ACTIVITIES MANAGEMENT (URL CORRETA!)
        // ============================================
        await navigateTo('http://eau-site.local/dashboard/manage-activities/', 'Activities Management');
        await waitForTableLoad();
        await safeScreenshot('activities-01-table.png', 'Tabela de atividades');

        // ============================================
        // CATEGORIES MANAGEMENT (URL CORRETA!)
        // ============================================
        await navigateTo('http://eau-site.local/dashboard/manage-categories/', 'Categories Management');
        await waitForTableLoad();
        await safeScreenshot('categories-01-table.png', 'Tabela de categorias');

        // ============================================
        // EVENTS MANAGEMENT (Admin)
        // ============================================
        await navigateTo('http://eau-site.local/dashboard/events/', 'Events Management');
        await waitForTableLoad();
        await safeScreenshot('events-admin-01-table.png', 'Tabela de eventos');

        // ============================================
        // PAYMENTS MANAGEMENT
        // ============================================
        await navigateTo('http://eau-site.local/dashboard/payments/', 'Payments Management');
        await waitForTableLoad();
        await safeScreenshot('payments-01-table.png', 'Tabela de pagamentos');

        // ============================================
        // MEMBERSHIP APPLICATIONS
        // ============================================
        await navigateTo('http://eau-site.local/dashboard/membership-applications/', 'Membership Applications');
        await waitForTableLoad();
        await safeScreenshot('membership-apps-01-table.png', 'Tabela de aplicações');

        // ============================================
        // OPENLEARNING MANAGEMENT
        // ============================================
        await navigateTo('http://eau-site.local/dashboard/openlearning-management/', 'OpenLearning Management');
        await waitForTableLoad();
        await safeScreenshot('openlearning-mgmt-01-table.png', 'Gestão OpenLearning');

        // ============================================
        // DUPLICATE MANAGER
        // ============================================
        await navigateTo('http://eau-site.local/dashboard/merge-members/', 'Duplicate Manager');
        await waitForTableLoad();
        await safeScreenshot('duplicates-01-table.png', 'Gestão de duplicatas');

        // ============================================
        // SETTINGS
        // ============================================
        await navigateTo('http://eau-site.local/dashboard/settings/', 'Settings');
        await page.waitForTimeout(4000);
        await safeScreenshot('settings-01-main.png', 'Configurações');

        // ============================================
        // MY PROFILE (URL CORRETA!)
        // ============================================
        await navigateTo('http://eau-site.local/profile/', 'My Profile');
        await page.waitForTimeout(4000);
        await safeScreenshot('my-profile-01-main.png', 'Perfil pessoal');

        // ============================================
        // MY CPDS
        // ============================================
        await navigateTo('http://eau-site.local/dashboard/my-cpds/', 'My CPDs');
        await waitForTableLoad();
        await safeScreenshot('my-cpds-01-main.png', 'Minhas atividades CPD');

        // ============================================
        // MY INSTITUTION
        // ============================================
        await navigateTo('http://eau-site.local/dashboard/my-institution/', 'My Institution');
        await page.waitForTimeout(4000);
        await safeScreenshot('my-institution-01-main.png', 'Minha instituição');

        // ============================================
        // OPENLEARNING COURSES
        // ============================================
        await navigateTo('http://eau-site.local/dashboard/courses/', 'OpenLearning Courses');
        await page.waitForTimeout(5000);
        await safeScreenshot('openlearning-courses-01-main.png', 'Cursos OpenLearning');

        // ============================================
        // EVENTS FRONTEND (Public)
        // ============================================
        await navigateTo('http://eau-site.local/events/', 'Events Frontend');
        await page.waitForTimeout(5000);
        await safeScreenshot('events-frontend-01-list.png', 'Lista pública de eventos');

        // ============================================
        // MEMBERSHIP SELECTION
        // ============================================
        await navigateTo('http://eau-site.local/membership-selection/', 'Membership Selection');
        await page.waitForTimeout(4000);
        await safeScreenshot('membership-selection-01-types.png', 'Seleção de membership');

        // ============================================
        // LOGOUT E REGISTRATION
        // ============================================
        console.log('\n>>> Fazendo logout para capturar página de registro...');
        await page.goto('http://eau-site.local/wp-login.php?action=logout', { waitUntil: 'domcontentloaded' });
        await page.waitForTimeout(2000);

        try {
            const logoutLink = page.locator('a:has-text("log out")');
            if (await logoutLink.isVisible()) {
                await logoutLink.click();
                await page.waitForTimeout(3000);
            }
        } catch (e) {
            console.log('   Aviso: não encontrou link de logout');
        }

        await navigateTo('http://eau-site.local/register/', 'Public Registration');
        await page.waitForTimeout(4000);
        await safeScreenshot('registration-01-form.png', 'Formulário de registro');

        // ============================================
        // CONCLUSÃO
        // ============================================
        console.log('\n' + '='.repeat(50));
        console.log('CAPTURA DE SCREENSHOTS CONCLUÍDA');
        console.log('='.repeat(50));
        console.log(`\nArquivos salvos em: ${screenshotsDir}`);

    } catch (error) {
        console.error('\nERRO GERAL:', error.message);
        await page.screenshot({ path: path.join(screenshotsDir, 'error.png') });
    }

    await page.waitForTimeout(2000);
    await browser.close();
})();
