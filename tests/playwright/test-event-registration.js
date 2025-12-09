/**
 * Eau System - Teste de Visualização e Inscrição em Eventos
 *
 * Script para testar o fluxo de um usuário comum:
 * - Visualizar lista de eventos
 * - Pesquisar eventos
 * - Acessar detalhes de um evento
 * - Realizar inscrição
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
    waitForLucideIcons,
    waitForToast,
    closeModals
} = require('./base-helpers');

/**
 * Configuração do teste
 */
const TEST_CONFIG = {
    // Termos de busca para testar
    searchTerms: ['teste', 'evento', 'workshop'],
    // Viewports para testar responsividade
    viewportsToTest: ['mobile', 'tablet', 'desktop']
};

/**
 * PASSO 1: Login como Usuário (Admin para ter certeza de ver eventos)
 *
 * @param {Page} page - Playwright page object
 */
async function step1_login(page) {
    console.log('\n========================================');
    console.log('PASSO 1: Login');
    console.log('========================================\n');

    const success = await login(page, 'admin');

    if (!success) {
        throw new Error('Falha no login');
    }

    await takeScreenshot(page, '01_reg_after_login', 'desktop');

    console.log('[PASSO 1] Concluído com sucesso!');
    return true;
}

/**
 * PASSO 2: Navegar para Página de Eventos (Frontend)
 *
 * @param {Page} page - Playwright page object
 */
async function step2_navigateToEvents(page) {
    console.log('\n========================================');
    console.log('PASSO 2: Navegar para Página de Eventos');
    console.log('========================================\n');

    // Vai para a página pública de eventos
    await page.goto(URLS.events);
    await page.waitForLoadState('networkidle');

    // Aguarda Lucide icons
    await waitForLucideIcons(page);

    // Screenshot da página de eventos
    await takeScreenshot(page, '02_reg_events_page', 'desktop');

    // Verifica elementos principais
    const hasEventCards = await page.locator('.eau-event-card, .event-card, article').first().isVisible().catch(() => false);
    const hasFilters = await page.locator('.eau-filters, .event-filters, [class*="filter"]').first().isVisible().catch(() => false);

    console.log(`[Verificação] Cards de eventos: ${hasEventCards}`);
    console.log(`[Verificação] Filtros presentes: ${hasFilters}`);

    console.log('[PASSO 2] Concluído com sucesso!');
    return true;
}

/**
 * PASSO 3: Testar Filtros (se disponíveis)
 *
 * @param {Page} page - Playwright page object
 */
async function step3_testFilters(page) {
    console.log('\n========================================');
    console.log('PASSO 3: Testar Filtros');
    console.log('========================================\n');

    // Procura botão de toggle de filtros
    const filterToggle = page.locator('[data-toggle-filters], .eau-filters-toggle, button:has-text("Filter")').first();

    if (await filterToggle.isVisible().catch(() => false)) {
        await filterToggle.click();
        await page.waitForTimeout(500);
        console.log('[Filtros] Toggle de filtros clicado');

        await takeScreenshot(page, '03_reg_filters_open', 'desktop');
    }

    // Procura campo de busca
    const searchField = page.locator('input[type="search"], input[name="search"], .eau-search-input, input[placeholder*="Search"], input[placeholder*="Buscar"]').first();

    if (await searchField.isVisible().catch(() => false)) {
        // Testa busca
        const searchTerm = TEST_CONFIG.searchTerms[0];
        await searchField.fill(searchTerm);
        console.log(`[Busca] Buscando por: ${searchTerm}`);

        // Aguarda resultados (pode ser AJAX ou submit)
        await page.waitForTimeout(1000);

        await takeScreenshot(page, '03_reg_search_results', 'desktop');

        // Limpa busca
        await searchField.fill('');
        await page.waitForTimeout(500);
    }

    // Testa filtro de categoria se disponível
    const categoryFilter = page.locator('select[name*="category"], select[name*="type"], .eau-filter-select').first();

    if (await categoryFilter.isVisible().catch(() => false)) {
        // Pega opções disponíveis
        const options = await categoryFilter.locator('option').allTextContents();
        console.log(`[Filtros] Categorias disponíveis: ${options.slice(0, 5).join(', ')}`);
    }

    console.log('[PASSO 3] Concluído com sucesso!');
    return true;
}

/**
 * PASSO 4: Listar Eventos Disponíveis
 *
 * @param {Page} page - Playwright page object
 */
async function step4_listAvailableEvents(page) {
    console.log('\n========================================');
    console.log('PASSO 4: Listar Eventos Disponíveis');
    console.log('========================================\n');

    // Conta eventos na página
    const eventCards = page.locator('.eau-event-card, .event-card, article[class*="event"], .eau-events-grid > div');
    const eventCount = await eventCards.count();

    console.log(`[Eventos] Encontrados ${eventCount} eventos na página`);

    if (eventCount === 0) {
        console.log('[Aviso] Nenhum evento encontrado. Pode ser necessário criar eventos primeiro.');
        await takeScreenshot(page, '04_reg_no_events', 'desktop');
        return { count: 0, events: [] };
    }

    // Lista os títulos dos eventos
    const events = [];
    for (let i = 0; i < Math.min(eventCount, 5); i++) {
        const card = eventCards.nth(i);
        const title = await card.locator('h2, h3, .event-title, .eau-event-title').first().textContent().catch(() => 'Sem título');
        const date = await card.locator('.event-date, .eau-event-date, time').first().textContent().catch(() => 'Sem data');

        events.push({ index: i, title: title.trim(), date: date.trim() });
        console.log(`  ${i + 1}. ${title.trim()} - ${date.trim()}`);
    }

    await takeScreenshot(page, '04_reg_events_list', 'desktop');

    console.log('[PASSO 4] Concluído com sucesso!');
    return { count: eventCount, events };
}

/**
 * PASSO 5: Selecionar um Evento Aleatório
 *
 * @param {Page} page - Playwright page object
 * @param {Object} eventsData - Dados dos eventos listados
 */
async function step5_selectRandomEvent(page, eventsData) {
    console.log('\n========================================');
    console.log('PASSO 5: Selecionar Evento Aleatório');
    console.log('========================================\n');

    if (!eventsData || eventsData.count === 0) {
        console.log('[Aviso] Nenhum evento para selecionar');
        return null;
    }

    // Seleciona um evento aleatório
    const randomIndex = Math.floor(Math.random() * Math.min(eventsData.count, 5));
    const selectedEvent = eventsData.events[randomIndex];

    console.log(`[Seleção] Selecionado evento #${randomIndex + 1}: ${selectedEvent.title}`);

    // Clica no evento
    const eventCards = page.locator('.eau-event-card, .event-card, article[class*="event"], .eau-events-grid > div');
    const selectedCard = eventCards.nth(randomIndex);

    // Procura link ou botão para ver detalhes
    const detailsLink = selectedCard.locator('a, button:has-text("View"), button:has-text("Ver"), button:has-text("Details")').first();

    if (await detailsLink.isVisible().catch(() => false)) {
        await detailsLink.click();
    } else {
        // Tenta clicar no card inteiro
        await selectedCard.click();
    }

    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(500);

    console.log(`[Navegação] URL atual: ${page.url()}`);

    await takeScreenshot(page, '05_reg_event_selected', 'desktop');

    console.log('[PASSO 5] Concluído com sucesso!');
    return selectedEvent;
}

/**
 * PASSO 6: Visualizar Detalhes do Evento
 *
 * @param {Page} page - Playwright page object
 */
async function step6_viewEventDetails(page) {
    console.log('\n========================================');
    console.log('PASSO 6: Visualizar Detalhes do Evento');
    console.log('========================================\n');

    // Aguarda a página carregar
    await waitForLucideIcons(page);

    // Coleta informações do evento
    const eventDetails = {};

    // Título
    const title = await page.locator('h1, .event-title, .eau-event-title').first().textContent().catch(() => '');
    eventDetails.title = title.trim();
    console.log(`[Detalhes] Título: ${eventDetails.title}`);

    // Data
    const date = await page.locator('.event-date, .eau-event-date, .eau-event-datetime, time').first().textContent().catch(() => '');
    eventDetails.date = date.trim();
    console.log(`[Detalhes] Data: ${eventDetails.date}`);

    // Local
    const location = await page.locator('.event-location, .eau-event-location, .eau-event-venue').first().textContent().catch(() => '');
    eventDetails.location = location.trim();
    console.log(`[Detalhes] Local: ${eventDetails.location}`);

    // Preço
    const price = await page.locator('.event-price, .eau-event-price, .price').first().textContent().catch(() => '');
    eventDetails.price = price.trim();
    console.log(`[Detalhes] Preço: ${eventDetails.price}`);

    // Vagas
    const capacity = await page.locator('.event-capacity, .eau-event-capacity, .eau-event-spots').first().textContent().catch(() => '');
    eventDetails.capacity = capacity.trim();
    console.log(`[Detalhes] Vagas: ${eventDetails.capacity}`);

    // Screenshot dos detalhes
    await takeScreenshot(page, '06_reg_event_details', 'desktop');

    // Verifica se há botão de inscrição
    const registerBtn = page.locator('button:has-text("Register"), button:has-text("Inscrever"), button:has-text("Sign Up"), .eau-btn-register, .eau-event-register-btn').first();
    eventDetails.hasRegisterButton = await registerBtn.isVisible().catch(() => false);
    console.log(`[Detalhes] Botão de inscrição presente: ${eventDetails.hasRegisterButton}`);

    console.log('[PASSO 6] Concluído com sucesso!');
    return eventDetails;
}

/**
 * PASSO 7: Iniciar Processo de Inscrição
 *
 * @param {Page} page - Playwright page object
 * @param {Object} eventDetails - Detalhes do evento
 */
async function step7_startRegistration(page, eventDetails) {
    console.log('\n========================================');
    console.log('PASSO 7: Iniciar Processo de Inscrição');
    console.log('========================================\n');

    if (!eventDetails || !eventDetails.hasRegisterButton) {
        console.log('[Aviso] Botão de inscrição não disponível');
        return false;
    }

    // Clica no botão de inscrição
    const registerBtn = page.locator('button:has-text("Register"), button:has-text("Inscrever"), button:has-text("Sign Up"), .eau-btn-register, .eau-event-register-btn').first();

    await registerBtn.click();
    console.log('[Inscrição] Clicou no botão de inscrição');

    // Aguarda modal ou formulário aparecer
    await page.waitForTimeout(1000);

    // Verifica se abriu modal
    const modalOpened = await page.locator('.eau-modal, .eau-reg-modal, [class*="modal"][class*="open"], .swal2-popup').isVisible().catch(() => false);

    if (modalOpened) {
        console.log('[Inscrição] Modal de inscrição aberto');
        await takeScreenshot(page, '07_reg_registration_modal', 'desktop');
    } else {
        console.log('[Inscrição] Verificando se redirecionou para formulário');
        await takeScreenshot(page, '07_reg_registration_form', 'desktop');
    }

    console.log('[PASSO 7] Concluído com sucesso!');
    return true;
}

/**
 * PASSO 8: Preencher Formulário de Inscrição (se necessário)
 *
 * @param {Page} page - Playwright page object
 */
async function step8_fillRegistrationForm(page) {
    console.log('\n========================================');
    console.log('PASSO 8: Preencher Formulário');
    console.log('========================================\n');

    // Procura campos do formulário no modal ou página
    const formFields = {
        name: page.locator('input[name="name"], input[name="full_name"], #reg-name').first(),
        email: page.locator('input[name="email"], input[type="email"], #reg-email').first(),
        phone: page.locator('input[name="phone"], input[name="tel"], input[type="tel"], #reg-phone').first(),
        notes: page.locator('textarea[name="notes"], textarea[name="comments"], #reg-notes').first()
    };

    let fieldsFound = 0;

    // Preenche campos se visíveis (já deve estar logado, então pode não precisar)
    for (const [fieldName, field] of Object.entries(formFields)) {
        if (await field.isVisible().catch(() => false)) {
            fieldsFound++;
            const currentValue = await field.inputValue().catch(() => '');

            if (!currentValue) {
                // Preenche com dados de teste
                switch (fieldName) {
                    case 'name':
                        await field.fill('Usuário de Teste');
                        break;
                    case 'email':
                        await field.fill('teste@teste.com');
                        break;
                    case 'phone':
                        await field.fill('11999999999');
                        break;
                    case 'notes':
                        await field.fill('Inscrição de teste via Playwright');
                        break;
                }
                console.log(`[Form] Preenchido campo: ${fieldName}`);
            } else {
                console.log(`[Form] Campo ${fieldName} já preenchido: ${currentValue.substring(0, 20)}...`);
            }
        }
    }

    if (fieldsFound === 0) {
        console.log('[Form] Nenhum campo de formulário encontrado (inscrição pode ser automática)');
    }

    await takeScreenshot(page, '08_reg_form_filled', 'desktop');

    console.log('[PASSO 8] Concluído com sucesso!');
    return true;
}

/**
 * PASSO 9: Confirmar Inscrição
 *
 * @param {Page} page - Playwright page object
 */
async function step9_confirmRegistration(page) {
    console.log('\n========================================');
    console.log('PASSO 9: Confirmar Inscrição');
    console.log('========================================\n');

    // Procura botão de confirmação
    const confirmBtn = page.locator(
        'button:has-text("Confirm"), button:has-text("Confirmar"), ' +
        'button:has-text("Submit"), button:has-text("Enviar"), ' +
        'button:has-text("Complete"), button:has-text("Finalizar"), ' +
        '.eau-btn-confirm, .eau-modal-save, button[type="submit"]'
    ).first();

    if (await confirmBtn.isVisible().catch(() => false)) {
        await confirmBtn.click();
        console.log('[Inscrição] Clicou em confirmar');

        // Aguarda processamento
        await page.waitForTimeout(2000);

        // Verifica sucesso
        const hasSuccessToast = await waitForToast(page, 'success', 5000);
        const hasSuccessMessage = await page.locator(
            '.success-message, .eau-success, .swal2-success, ' +
            ':has-text("sucesso"), :has-text("success"), :has-text("registered")'
        ).first().isVisible().catch(() => false);

        await takeScreenshot(page, '09_reg_confirmation_result', 'desktop');

        if (hasSuccessToast || hasSuccessMessage) {
            console.log('[Inscrição] Inscrição confirmada com sucesso!');
            return true;
        } else {
            console.log('[Inscrição] Status da inscrição incerto - verificar screenshot');
            return null;
        }
    } else {
        console.log('[Inscrição] Botão de confirmação não encontrado');
        return false;
    }
}

/**
 * PASSO 10: Testar Responsividade da Página de Eventos
 *
 * @param {Page} page - Playwright page object
 */
async function step10_testResponsiveness(page) {
    console.log('\n========================================');
    console.log('PASSO 10: Testar Responsividade');
    console.log('========================================\n');

    // Volta para a lista de eventos
    await page.goto(URLS.events);
    await page.waitForLoadState('networkidle');

    // Testa em cada viewport
    for (const viewport of TEST_CONFIG.viewportsToTest) {
        console.log(`\n[Responsividade] Testando viewport: ${viewport}`);

        await setViewport(page, viewport);
        await page.waitForTimeout(500);

        // Screenshot da lista de eventos
        await takeScreenshot(page, '10_reg_responsive_events', viewport);

        // Verifica elementos principais
        const hasEventCards = await page.locator('.eau-event-card, .event-card, article').first().isVisible().catch(() => false);
        const hasNavigation = await page.locator('nav, .menu, .navigation').first().isVisible().catch(() => false);

        console.log(`[${viewport}] Cards visíveis: ${hasEventCards}, Navegação visível: ${hasNavigation}`);

        // Em mobile, verifica se há menu hambúrguer
        if (viewport === 'mobile') {
            const hasHamburger = await page.locator('.hamburger, .menu-toggle, .mobile-menu-toggle, [class*="burger"]').isVisible().catch(() => false);
            console.log(`[${viewport}] Menu hamburger: ${hasHamburger}`);
        }
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
    console.log('\n╔════════════════════════════════════════════╗');
    console.log('║  TESTE: VISUALIZAÇÃO E INSCRIÇÃO EM EVENTO ║');
    console.log('╚════════════════════════════════════════════╝\n');

    const results = {
        login: false,
        navigation: false,
        filters: false,
        listEvents: false,
        selectEvent: false,
        viewDetails: false,
        startRegistration: false,
        fillForm: false,
        confirmRegistration: false,
        responsiveness: false
    };

    let eventsData = null;
    let selectedEvent = null;
    let eventDetails = null;

    try {
        results.login = await step1_login(page);
        results.navigation = await step2_navigateToEvents(page);
        results.filters = await step3_testFilters(page);

        eventsData = await step4_listAvailableEvents(page);
        results.listEvents = eventsData && eventsData.count > 0;

        if (results.listEvents) {
            selectedEvent = await step5_selectRandomEvent(page, eventsData);
            results.selectEvent = !!selectedEvent;

            eventDetails = await step6_viewEventDetails(page);
            results.viewDetails = !!eventDetails;

            results.startRegistration = await step7_startRegistration(page, eventDetails);
            results.fillForm = await step8_fillRegistrationForm(page);

            const confirmResult = await step9_confirmRegistration(page);
            results.confirmRegistration = confirmResult === true;
        }

        results.responsiveness = await step10_testResponsiveness(page);

    } catch (error) {
        console.error('\n[ERRO] Teste falhou:', error.message);
        await takeScreenshot(page, 'reg_error_screenshot', 'desktop');
    }

    // Resumo do teste
    console.log('\n╔════════════════════════════════════════════╗');
    console.log('║           RESUMO DO TESTE                  ║');
    console.log('╠════════════════════════════════════════════╣');
    Object.entries(results).forEach(([step, passed]) => {
        const status = passed ? '✓' : '✗';
        console.log(`║ ${status} ${step.padEnd(38)} ║`);
    });
    console.log('╚════════════════════════════════════════════╝\n');

    const passedCount = Object.values(results).filter(v => v).length;
    const totalCount = Object.values(results).length;
    console.log(`Resultado: ${passedCount}/${totalCount} passos completados`);

    return results;
}

// Exporta funções para uso individual ou teste completo
module.exports = {
    TEST_CONFIG,
    step1_login,
    step2_navigateToEvents,
    step3_testFilters,
    step4_listAvailableEvents,
    step5_selectRandomEvent,
    step6_viewEventDetails,
    step7_startRegistration,
    step8_fillRegistrationForm,
    step9_confirmRegistration,
    step10_testResponsiveness,
    runFullTest
};
