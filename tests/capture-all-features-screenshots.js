const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');

(async () => {
    const browser = await chromium.launch({ headless: false });
    const context = await browser.newContext({ viewport: { width: 1920, height: 1080 } });
    const page = await context.newPage();

    const screenshotsDir = path.join(__dirname, 'presentation-screenshots', 'features');

    // Ensure directory exists
    if (!fs.existsSync(screenshotsDir)) {
        fs.mkdirSync(screenshotsDir, { recursive: true });
    }

    try {
        // 1. Login as SuperAdmin
        console.log('1. Fazendo login como SuperAdmin...');
        await page.goto('http://eau-site.local/login/');
        await page.waitForLoadState('networkidle');

        const userField = page.locator('input[name="log"], input[name="username"], input#user_login, input[type="text"]').first();
        const passField = page.locator('input[name="pwd"], input[name="password"], input#user_pass, input[type="password"]').first();

        await userField.fill('rrzillesg@gmail.com');
        await passField.fill('Pl@ttyPl@tty');
        await page.locator('button[type="submit"], input[type="submit"]').first().click();
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(2000);
        console.log('   Login OK');

        // ============================================
        // DASHBOARD
        // ============================================
        console.log('\n=== DASHBOARD ===');
        await page.goto('http://eau-site.local/dashboard/');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(2000);
        await page.screenshot({
            path: path.join(screenshotsDir, 'dashboard-01-main.png'),
            fullPage: false
        });
        console.log('   Dashboard principal capturado');

        // ============================================
        // MEMBERS MANAGEMENT
        // ============================================
        console.log('\n=== MEMBERS MANAGEMENT ===');
        await page.goto('http://eau-site.local/admin/members/');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(3000);

        // Main table view
        await page.screenshot({
            path: path.join(screenshotsDir, 'members-01-table.png'),
            fullPage: false
        });
        console.log('   Tabela de membros capturada');

        // Show filters
        const filtersToggle = page.locator('.eau-filters-toggle, #eau-toggle-filters');
        if (await filtersToggle.isVisible()) {
            await filtersToggle.click();
            await page.waitForTimeout(500);
            await page.screenshot({
                path: path.join(screenshotsDir, 'members-02-filters.png'),
                fullPage: false
            });
            console.log('   Filtros de membros capturados');
        }

        // Open view modal
        const viewBtn = page.locator('.eau-action-view').first();
        if (await viewBtn.isVisible()) {
            await viewBtn.click();
            await page.waitForTimeout(1000);
            await page.screenshot({
                path: path.join(screenshotsDir, 'members-03-view-modal.png'),
                fullPage: false
            });
            console.log('   Modal de visualização capturado');

            // Close modal
            await page.locator('.eau-modal-close, [data-action="close"]').first().click();
            await page.waitForTimeout(500);
        }

        // ============================================
        // INSTITUTIONS MANAGEMENT
        // ============================================
        console.log('\n=== INSTITUTIONS MANAGEMENT ===');
        await page.goto('http://eau-site.local/admin/institutions/');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(3000);

        await page.screenshot({
            path: path.join(screenshotsDir, 'institutions-01-table.png'),
            fullPage: false
        });
        console.log('   Tabela de instituições capturada');

        // View institution
        const instViewBtn = page.locator('.eau-action-view').first();
        if (await instViewBtn.isVisible()) {
            await instViewBtn.click();
            await page.waitForTimeout(1000);
            await page.screenshot({
                path: path.join(screenshotsDir, 'institutions-02-view-modal.png'),
                fullPage: false
            });
            console.log('   Modal de instituição capturado');
            await page.locator('.eau-modal-close, [data-action="close"]').first().click();
            await page.waitForTimeout(500);
        }

        // ============================================
        // ACTIVITIES MANAGEMENT
        // ============================================
        console.log('\n=== ACTIVITIES MANAGEMENT ===');
        await page.goto('http://eau-site.local/admin/activities/');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(3000);

        await page.screenshot({
            path: path.join(screenshotsDir, 'activities-01-table.png'),
            fullPage: false
        });
        console.log('   Tabela de atividades capturada');

        // View activity
        const actViewBtn = page.locator('.eau-action-view').first();
        if (await actViewBtn.isVisible()) {
            await actViewBtn.click();
            await page.waitForTimeout(1000);
            await page.screenshot({
                path: path.join(screenshotsDir, 'activities-02-view-modal.png'),
                fullPage: false
            });
            console.log('   Modal de atividade capturado');
            await page.locator('.eau-modal-close, [data-action="close"]').first().click();
            await page.waitForTimeout(500);
        }

        // ============================================
        // CATEGORIES MANAGEMENT
        // ============================================
        console.log('\n=== CATEGORIES MANAGEMENT ===');
        await page.goto('http://eau-site.local/admin/categories/');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(3000);

        await page.screenshot({
            path: path.join(screenshotsDir, 'categories-01-table.png'),
            fullPage: false
        });
        console.log('   Tabela de categorias capturada');

        // ============================================
        // EVENTS MANAGEMENT (Admin)
        // ============================================
        console.log('\n=== EVENTS MANAGEMENT ===');
        await page.goto('http://eau-site.local/dashboard/events/');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(3000);

        await page.screenshot({
            path: path.join(screenshotsDir, 'events-admin-01-table.png'),
            fullPage: false
        });
        console.log('   Tabela de eventos capturada');

        // Click Create Event to show modal
        const createEventBtn = page.locator('#eau-create-event-btn, .eau-btn-primary:has-text("Create Event")');
        if (await createEventBtn.isVisible()) {
            await createEventBtn.click();
            await page.waitForTimeout(1000);
            await page.screenshot({
                path: path.join(screenshotsDir, 'events-admin-02-create-modal.png'),
                fullPage: false
            });
            console.log('   Modal de criação de evento capturado');

            // Close modal
            await page.keyboard.press('Escape');
            await page.waitForTimeout(500);
        }

        // ============================================
        // PAYMENTS MANAGEMENT
        // ============================================
        console.log('\n=== PAYMENTS MANAGEMENT ===');
        await page.goto('http://eau-site.local/dashboard/payments/');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(3000);

        await page.screenshot({
            path: path.join(screenshotsDir, 'payments-01-table.png'),
            fullPage: false
        });
        console.log('   Tabela de pagamentos capturada');

        // ============================================
        // MEMBERSHIP APPLICATIONS
        // ============================================
        console.log('\n=== MEMBERSHIP APPLICATIONS ===');
        await page.goto('http://eau-site.local/dashboard/membership-applications/');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(3000);

        await page.screenshot({
            path: path.join(screenshotsDir, 'membership-apps-01-table.png'),
            fullPage: false
        });
        console.log('   Tabela de aplicações capturada');

        // ============================================
        // OPENLEARNING MANAGEMENT
        // ============================================
        console.log('\n=== OPENLEARNING MANAGEMENT ===');
        await page.goto('http://eau-site.local/dashboard/openlearning-management/');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(3000);

        await page.screenshot({
            path: path.join(screenshotsDir, 'openlearning-mgmt-01-table.png'),
            fullPage: false
        });
        console.log('   Gestão OpenLearning capturada');

        // ============================================
        // DUPLICATE MANAGER
        // ============================================
        console.log('\n=== DUPLICATE MANAGER ===');
        await page.goto('http://eau-site.local/dashboard/merge-members/');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(3000);

        await page.screenshot({
            path: path.join(screenshotsDir, 'duplicates-01-table.png'),
            fullPage: false
        });
        console.log('   Gestão de duplicatas capturada');

        // ============================================
        // SETTINGS
        // ============================================
        console.log('\n=== SETTINGS ===');
        await page.goto('http://eau-site.local/dashboard/settings/');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(2000);

        await page.screenshot({
            path: path.join(screenshotsDir, 'settings-01-main.png'),
            fullPage: false
        });
        console.log('   Configurações capturadas');

        // ============================================
        // MY PROFILE
        // ============================================
        console.log('\n=== MY PROFILE ===');
        await page.goto('http://eau-site.local/dashboard/my-profile/');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(2000);

        await page.screenshot({
            path: path.join(screenshotsDir, 'my-profile-01-main.png'),
            fullPage: false
        });
        console.log('   Perfil pessoal capturado');

        // ============================================
        // MY CPDS
        // ============================================
        console.log('\n=== MY CPDS ===');
        await page.goto('http://eau-site.local/dashboard/my-cpds/');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(2000);

        await page.screenshot({
            path: path.join(screenshotsDir, 'my-cpds-01-main.png'),
            fullPage: false
        });
        console.log('   Minhas CPDs capturado');

        // Click Add Activity
        const addCpdBtn = page.locator('#eau-add-activity-btn, .eau-btn-primary:has-text("Add Activity")');
        if (await addCpdBtn.isVisible()) {
            await addCpdBtn.click();
            await page.waitForTimeout(1000);
            await page.screenshot({
                path: path.join(screenshotsDir, 'my-cpds-02-add-modal.png'),
                fullPage: false
            });
            console.log('   Modal de adicionar CPD capturado');
            await page.keyboard.press('Escape');
            await page.waitForTimeout(500);
        }

        // ============================================
        // MY INSTITUTION
        // ============================================
        console.log('\n=== MY INSTITUTION ===');
        await page.goto('http://eau-site.local/dashboard/my-institution/');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(2000);

        await page.screenshot({
            path: path.join(screenshotsDir, 'my-institution-01-main.png'),
            fullPage: false
        });
        console.log('   Minha instituição capturado');

        // ============================================
        // OPENLEARNING COURSES
        // ============================================
        console.log('\n=== OPENLEARNING COURSES ===');
        await page.goto('http://eau-site.local/dashboard/courses/');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(3000);

        await page.screenshot({
            path: path.join(screenshotsDir, 'openlearning-courses-01-main.png'),
            fullPage: false
        });
        console.log('   Cursos OpenLearning capturado');

        // ============================================
        // EVENTS FRONTEND (Public)
        // ============================================
        console.log('\n=== EVENTS FRONTEND ===');
        await page.goto('http://eau-site.local/events/');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(3000);

        await page.screenshot({
            path: path.join(screenshotsDir, 'events-frontend-01-list.png'),
            fullPage: false
        });
        console.log('   Lista de eventos capturada');

        // Click on first event to see details
        const eventCard = page.locator('.eau-event-card a, .event-link').first();
        if (await eventCard.isVisible()) {
            await eventCard.click();
            await page.waitForLoadState('networkidle');
            await page.waitForTimeout(2000);
            await page.screenshot({
                path: path.join(screenshotsDir, 'events-frontend-02-single.png'),
                fullPage: false
            });
            console.log('   Detalhe de evento capturado');
        }

        // ============================================
        // PUBLIC REGISTRATION (logout first)
        // ============================================
        console.log('\n=== PUBLIC REGISTRATION ===');
        // Logout
        await page.goto('http://eau-site.local/wp-login.php?action=logout');
        await page.waitForTimeout(1000);
        const logoutConfirm = page.locator('a:has-text("log out")');
        if (await logoutConfirm.isVisible()) {
            await logoutConfirm.click();
            await page.waitForTimeout(2000);
        }

        await page.goto('http://eau-site.local/register/');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(2000);

        await page.screenshot({
            path: path.join(screenshotsDir, 'registration-01-form.png'),
            fullPage: false
        });
        console.log('   Formulário de registro capturado');

        // ============================================
        // MEMBERSHIP SELECTION (login as member without membership)
        // ============================================
        console.log('\n=== MEMBERSHIP SELECTION ===');
        await page.goto('http://eau-site.local/membership-selection/');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(2000);

        await page.screenshot({
            path: path.join(screenshotsDir, 'membership-selection-01-types.png'),
            fullPage: false
        });
        console.log('   Seleção de membership capturada');

        console.log('\n========================================');
        console.log('Todos os screenshots capturados com sucesso!');
        console.log(`Salvos em: ${screenshotsDir}`);
        console.log('========================================');

    } catch (error) {
        console.error('Erro:', error.message);
        await page.screenshot({ path: path.join(screenshotsDir, 'error.png') });
    }

    await page.waitForTimeout(2000);
    await browser.close();
})();
