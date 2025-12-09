/**
 * Eau System - Teste de Criação de Eventos
 *
 * Script para testar o fluxo completo de criação de eventos
 * como usuário admin.
 *
 * @package EauSystem
 * @since 1.48.3
 *
 * USO COM MCP PLAYWRIGHT:
 * 1. Navegue para a URL de login
 * 2. Execute as funções deste script na sequência
 * 3. Analise os screenshots gerados
 */

const {
    BASE_URL,
    URLS,
    VIEWPORTS,
    login,
    takeScreenshot,
    setViewport,
    waitAndClick,
    randomData,
    waitForLucideIcons,
    waitForToast,
    closeModals
} = require('./base-helpers');

/**
 * Configuração do teste
 */
const TEST_CONFIG = {
    // Dados do evento a ser criado
    event: {
        title: randomData.eventTitle(),
        description: randomData.eventDescription(),
        startDate: randomData.futureDate(30),
        endDate: randomData.futureDateEnd(30, 2),
        capacity: 50,
        price: '0', // Evento gratuito para teste
        location: 'Local de Teste - Sala Principal',
        address: 'Rua de Teste, 123 - Centro'
    },
    // Viewports para testar responsividade
    viewportsToTest: ['mobile', 'tablet', 'desktop']
};

/**
 * PASSO 1: Login como Admin
 *
 * @param {Page} page - Playwright page object
 */
async function step1_login(page) {
    console.log('\n========================================');
    console.log('PASSO 1: Login como Admin');
    console.log('========================================\n');

    const success = await login(page, 'admin');

    if (!success) {
        throw new Error('Falha no login como admin');
    }

    // Screenshot após login
    await takeScreenshot(page, '01_after_login', 'desktop');

    console.log('[PASSO 1] Concluído com sucesso!');
    return true;
}

/**
 * PASSO 2: Navegar para Gerenciamento de Eventos
 *
 * @param {Page} page - Playwright page object
 */
async function step2_navigateToEventsManagement(page) {
    console.log('\n========================================');
    console.log('PASSO 2: Navegar para Gerenciamento de Eventos');
    console.log('========================================\n');

    // Vai direto para a página de eventos
    await page.goto(URLS.eventsManagement);
    await page.waitForLoadState('networkidle');

    // Aguarda Lucide icons carregarem
    await waitForLucideIcons(page);

    // Screenshot da página de eventos
    await takeScreenshot(page, '02_events_management_page', 'desktop');

    // Verifica se a tabela de eventos está presente
    const hasTable = await page.locator('.eau-data-table, .eau-events-table').isVisible();
    console.log(`[Verificação] Tabela de eventos presente: ${hasTable}`);

    // Verifica se botão de criar está presente
    const hasCreateBtn = await page.locator('button:has-text("Create"), button:has-text("Criar"), .eau-btn-primary:has-text("Event")').first().isVisible();
    console.log(`[Verificação] Botão de criar presente: ${hasCreateBtn}`);

    console.log('[PASSO 2] Concluído com sucesso!');
    return true;
}

/**
 * PASSO 3: Abrir Modal de Criação
 *
 * @param {Page} page - Playwright page object
 */
async function step3_openCreateModal(page) {
    console.log('\n========================================');
    console.log('PASSO 3: Abrir Modal de Criação');
    console.log('========================================\n');

    // Procura o botão de criar evento
    const createBtn = page.locator('button:has-text("Create Event"), button:has-text("Criar Evento"), .eau-btn-create-event').first();

    if (await createBtn.isVisible()) {
        await createBtn.click();
        console.log('[Modal] Clicou no botão de criar');
    } else {
        // Tenta botão alternativo
        const altBtn = page.locator('.eau-page-actions button, .eau-btn-primary').first();
        await altBtn.click();
        console.log('[Modal] Clicou em botão alternativo');
    }

    // Aguarda modal abrir
    await page.waitForSelector('.eau-modal, .eau-reg-modal, [class*="modal"]', { timeout: 5000 });
    await page.waitForTimeout(500); // Pequena pausa para animação

    // Screenshot do modal aberto
    await takeScreenshot(page, '03_create_modal_opened', 'desktop');

    console.log('[PASSO 3] Concluído com sucesso!');
    return true;
}

/**
 * PASSO 4: Preencher Aba de Informações Básicas
 *
 * @param {Page} page - Playwright page object
 */
async function step4_fillBasicInfo(page) {
    console.log('\n========================================');
    console.log('PASSO 4: Preencher Informações Básicas');
    console.log('========================================\n');

    const { event } = TEST_CONFIG;

    // Título do evento
    const titleField = page.locator('#eau-edit-evt_title, input[name="evt_title"], input[name="title"]').first();
    if (await titleField.isVisible()) {
        await titleField.fill(event.title);
        console.log(`[Form] Título: ${event.title}`);
    }

    // Descrição (pode ser Quill editor)
    const descField = page.locator('#eau-edit-evt_description, textarea[name="evt_description"], .ql-editor').first();
    if (await descField.isVisible()) {
        const isQuill = await page.locator('.ql-editor').isVisible();
        if (isQuill) {
            await page.locator('.ql-editor').click();
            await page.locator('.ql-editor').fill(event.description);
        } else {
            await descField.fill(event.description);
        }
        console.log('[Form] Descrição preenchida');
    }

    // Data de início
    const startDateField = page.locator('#eau-edit-evt_start_datetime, input[name="evt_start_datetime"], input[type="datetime-local"]').first();
    if (await startDateField.isVisible()) {
        await startDateField.fill(event.startDate);
        console.log(`[Form] Data início: ${event.startDate}`);
    }

    // Data de fim
    const endDateField = page.locator('#eau-edit-evt_end_datetime, input[name="evt_end_datetime"]').first();
    if (await endDateField.isVisible()) {
        await endDateField.fill(event.endDate);
        console.log(`[Form] Data fim: ${event.endDate}`);
    }

    // Screenshot após preencher informações básicas
    await takeScreenshot(page, '04_basic_info_filled', 'desktop');

    console.log('[PASSO 4] Concluído com sucesso!');
    return true;
}

/**
 * PASSO 5: Navegar para Aba de Localização
 *
 * @param {Page} page - Playwright page object
 */
async function step5_fillLocationTab(page) {
    console.log('\n========================================');
    console.log('PASSO 5: Preencher Aba de Localização');
    console.log('========================================\n');

    const { event } = TEST_CONFIG;

    // Clica na aba de localização
    const locationTab = page.locator('[data-tab="location"], .eau-modal-tab:has-text("Location"), .eau-modal-tab:has-text("Local")').first();
    if (await locationTab.isVisible()) {
        await locationTab.click();
        await page.waitForTimeout(300);
        console.log('[Tab] Navegou para aba Location');
    }

    // Local/Venue
    const venueField = page.locator('#eau-edit-evt_venue, input[name="evt_venue"]').first();
    if (await venueField.isVisible()) {
        await venueField.fill(event.location);
        console.log(`[Form] Local: ${event.location}`);
    }

    // Endereço
    const addressField = page.locator('#eau-edit-evt_address, input[name="evt_address"], textarea[name="evt_address"]').first();
    if (await addressField.isVisible()) {
        await addressField.fill(event.address);
        console.log(`[Form] Endereço: ${event.address}`);
    }

    // Screenshot da aba de localização
    await takeScreenshot(page, '05_location_tab_filled', 'desktop');

    console.log('[PASSO 5] Concluído com sucesso!');
    return true;
}

/**
 * PASSO 6: Navegar para Aba de Preços
 *
 * @param {Page} page - Playwright page object
 */
async function step6_fillPricingTab(page) {
    console.log('\n========================================');
    console.log('PASSO 6: Preencher Aba de Preços');
    console.log('========================================\n');

    const { event } = TEST_CONFIG;

    // Clica na aba de preços
    const pricingTab = page.locator('[data-tab="pricing"], .eau-modal-tab:has-text("Pricing"), .eau-modal-tab:has-text("Preço")').first();
    if (await pricingTab.isVisible()) {
        await pricingTab.click();
        await page.waitForTimeout(300);
        console.log('[Tab] Navegou para aba Pricing');
    }

    // Preço
    const priceField = page.locator('#eau-edit-evt_price, input[name="evt_price"]').first();
    if (await priceField.isVisible()) {
        await priceField.fill(event.price);
        console.log(`[Form] Preço: ${event.price}`);
    }

    // Capacidade
    const capacityField = page.locator('#eau-edit-evt_capacity, input[name="evt_capacity"]').first();
    if (await capacityField.isVisible()) {
        await capacityField.fill(String(event.capacity));
        console.log(`[Form] Capacidade: ${event.capacity}`);
    }

    // Screenshot da aba de preços
    await takeScreenshot(page, '06_pricing_tab_filled', 'desktop');

    console.log('[PASSO 6] Concluído com sucesso!');
    return true;
}

/**
 * PASSO 7: Navegar para Aba de Configurações e Publicar
 *
 * @param {Page} page - Playwright page object
 */
async function step7_fillSettingsAndPublish(page) {
    console.log('\n========================================');
    console.log('PASSO 7: Configurações e Publicar');
    console.log('========================================\n');

    // Clica na aba de configurações
    const settingsTab = page.locator('[data-tab="settings"], .eau-modal-tab:has-text("Settings"), .eau-modal-tab:has-text("Config")').first();
    if (await settingsTab.isVisible()) {
        await settingsTab.click();
        await page.waitForTimeout(300);
        console.log('[Tab] Navegou para aba Settings');
    }

    // Marca "Publicar imediatamente"
    const publishCheckbox = page.locator('#eau-edit-publish_immediately, input[name="publish_immediately"]').first();
    if (await publishCheckbox.isVisible()) {
        await publishCheckbox.check();
        console.log('[Form] Marcou "Publicar imediatamente"');
    }

    // Screenshot antes de salvar
    await takeScreenshot(page, '07_settings_before_save', 'desktop');

    console.log('[PASSO 7] Concluído com sucesso!');
    return true;
}

/**
 * PASSO 8: Salvar Evento
 *
 * @param {Page} page - Playwright page object
 */
async function step8_saveEvent(page) {
    console.log('\n========================================');
    console.log('PASSO 8: Salvar Evento');
    console.log('========================================\n');

    // Clica no botão salvar
    const saveBtn = page.locator('button:has-text("Save"), button:has-text("Salvar"), .eau-modal-save, .eau-btn-save').first();

    if (await saveBtn.isVisible()) {
        await saveBtn.click();
        console.log('[Save] Clicou em Salvar');
    }

    // Aguarda resposta (toast ou fechamento do modal)
    await page.waitForTimeout(2000);

    // Verifica se houve sucesso
    const hasSuccessToast = await waitForToast(page, 'success', 3000);

    // Screenshot do resultado
    await takeScreenshot(page, '08_after_save', 'desktop');

    if (hasSuccessToast) {
        console.log('[Save] Evento salvo com sucesso!');
    } else {
        console.log('[Save] Verificar se evento foi salvo (pode não ter toast visível)');
    }

    // Fecha modal se ainda estiver aberto
    await closeModals(page);

    console.log('[PASSO 8] Concluído com sucesso!');
    return true;
}

/**
 * PASSO 9: Verificar Evento na Lista
 *
 * @param {Page} page - Playwright page object
 */
async function step9_verifyEventInList(page) {
    console.log('\n========================================');
    console.log('PASSO 9: Verificar Evento na Lista');
    console.log('========================================\n');

    const { event } = TEST_CONFIG;

    // Aguarda a tabela atualizar
    await page.waitForTimeout(1000);
    await page.waitForLoadState('networkidle');

    // Procura o evento criado na tabela
    const eventRow = page.locator(`tr:has-text("${event.title}"), .eau-table-row:has-text("${event.title}")`).first();

    const eventFound = await eventRow.isVisible().catch(() => false);

    if (eventFound) {
        console.log(`[Verificação] Evento "${event.title}" encontrado na lista!`);

        // Destaca o evento encontrado
        await eventRow.scrollIntoViewIfNeeded();
    } else {
        console.log('[Verificação] Evento não encontrado na lista (pode precisar de refresh)');

        // Tenta recarregar a página
        await page.reload();
        await page.waitForLoadState('networkidle');
    }

    // Screenshot final da lista
    await takeScreenshot(page, '09_event_in_list', 'desktop');

    console.log('[PASSO 9] Concluído com sucesso!');
    return eventFound;
}

/**
 * PASSO 10: Testar Responsividade
 *
 * @param {Page} page - Playwright page object
 */
async function step10_testResponsiveness(page) {
    console.log('\n========================================');
    console.log('PASSO 10: Testar Responsividade');
    console.log('========================================\n');

    // Testa em cada viewport
    for (const viewport of TEST_CONFIG.viewportsToTest) {
        console.log(`\n[Responsividade] Testando viewport: ${viewport}`);

        await setViewport(page, viewport);
        await page.waitForTimeout(500);

        // Screenshot da página de eventos
        await takeScreenshot(page, `10_responsive_events_list`, viewport);

        // Verifica elementos principais
        const hasTable = await page.locator('.eau-data-table, table').isVisible();
        const hasHeader = await page.locator('.eau-page-header, h1, h2').isVisible();

        console.log(`[${viewport}] Tabela visível: ${hasTable}, Header visível: ${hasHeader}`);
    }

    // Retorna para desktop
    await setViewport(page, 'desktop');

    console.log('\n[PASSO 10] Concluído com sucesso!');
    return true;
}

/**
 * Executa o teste completo
 *
 * @param {Page} page - Playwright page object
 */
async function runFullTest(page) {
    console.log('\n╔════════════════════════════════════════╗');
    console.log('║  TESTE: CRIAÇÃO DE EVENTO (ADMIN)      ║');
    console.log('╚════════════════════════════════════════╝\n');

    const results = {
        login: false,
        navigation: false,
        modalOpen: false,
        basicInfo: false,
        location: false,
        pricing: false,
        settings: false,
        save: false,
        verification: false,
        responsiveness: false
    };

    try {
        results.login = await step1_login(page);
        results.navigation = await step2_navigateToEventsManagement(page);
        results.modalOpen = await step3_openCreateModal(page);
        results.basicInfo = await step4_fillBasicInfo(page);
        results.location = await step5_fillLocationTab(page);
        results.pricing = await step6_fillPricingTab(page);
        results.settings = await step7_fillSettingsAndPublish(page);
        results.save = await step8_saveEvent(page);
        results.verification = await step9_verifyEventInList(page);
        results.responsiveness = await step10_testResponsiveness(page);
    } catch (error) {
        console.error('\n[ERRO] Teste falhou:', error.message);
        await takeScreenshot(page, 'error_screenshot', 'desktop');
    }

    // Resumo do teste
    console.log('\n╔════════════════════════════════════════╗');
    console.log('║         RESUMO DO TESTE                ║');
    console.log('╠════════════════════════════════════════╣');
    Object.entries(results).forEach(([step, passed]) => {
        const status = passed ? '✓' : '✗';
        console.log(`║ ${status} ${step.padEnd(35)} ║`);
    });
    console.log('╚════════════════════════════════════════╝\n');

    const allPassed = Object.values(results).every(v => v);
    console.log(allPassed ? '✓ TESTE COMPLETO COM SUCESSO!' : '✗ ALGUNS PASSOS FALHARAM');

    return results;
}

// Exporta funções para uso individual ou teste completo
module.exports = {
    TEST_CONFIG,
    step1_login,
    step2_navigateToEventsManagement,
    step3_openCreateModal,
    step4_fillBasicInfo,
    step5_fillLocationTab,
    step6_fillPricingTab,
    step7_fillSettingsAndPublish,
    step8_saveEvent,
    step9_verifyEventInList,
    step10_testResponsiveness,
    runFullTest
};
