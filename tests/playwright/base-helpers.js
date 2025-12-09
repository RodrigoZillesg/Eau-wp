/**
 * Eau System - Playwright Test Helpers
 *
 * Funções base para testes automatizados
 *
 * @package EauSystem
 * @since 1.48.3
 */

const BASE_URL = 'http://eau-site.local';

// Credenciais de teste
const CREDENTIALS = {
    admin: {
        email: 'rrzillesg@gmail.com',
        password: 'Salmo119:97'
    }
};

// URLs importantes
const URLS = {
    login: `${BASE_URL}/login/`,
    dashboard: `${BASE_URL}/dashboard/`,
    events: `${BASE_URL}/events/`,
    eventsManagement: `${BASE_URL}/dashboard/events/`,
    members: `${BASE_URL}/dashboard/manage-members/`,
    activities: `${BASE_URL}/dashboard/manage-activities/`
};

// Viewport sizes para testes responsivos
const VIEWPORTS = {
    mobile: { width: 375, height: 812 },    // iPhone X
    tablet: { width: 768, height: 1024 },   // iPad
    desktop: { width: 1920, height: 1080 }  // Full HD
};

/**
 * Faz login no sistema
 *
 * @param {Page} page - Playwright page object
 * @param {string} userType - Tipo de usuário ('admin', 'member', etc)
 * @returns {Promise<boolean>} - True se login bem sucedido
 */
async function login(page, userType = 'admin') {
    const creds = CREDENTIALS[userType];
    if (!creds) {
        throw new Error(`Credenciais não encontradas para: ${userType}`);
    }

    console.log(`[Login] Iniciando login como ${userType}...`);

    await page.goto(URLS.login);
    await page.waitForLoadState('networkidle');

    // Preenche credenciais
    await page.fill('#username', creds.email);
    await page.fill('#password', creds.password);

    // Clica no botão de login
    await page.click('button[type="submit"]');

    // Aguarda redirecionamento
    await page.waitForLoadState('networkidle');

    // Verifica se login foi bem sucedido (deve estar no dashboard ou não na página de login)
    const currentUrl = page.url();
    const success = !currentUrl.includes('/login/');

    if (success) {
        console.log(`[Login] Login bem sucedido! Redirecionado para: ${currentUrl}`);
    } else {
        console.log(`[Login] Falha no login. Ainda em: ${currentUrl}`);
    }

    return success;
}

/**
 * Tira screenshot com nome descritivo
 *
 * @param {Page} page - Playwright page object
 * @param {string} name - Nome descritivo do screenshot
 * @param {string} viewport - Tipo de viewport ('mobile', 'tablet', 'desktop')
 * @returns {Promise<string>} - Caminho do arquivo salvo
 */
async function takeScreenshot(page, name, viewport = 'desktop') {
    const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
    const filename = `${name}_${viewport}_${timestamp}.png`;
    const path = `./screenshots/${filename}`;

    await page.screenshot({ path, fullPage: false });
    console.log(`[Screenshot] Salvo: ${filename}`);

    return path;
}

/**
 * Define o viewport para teste responsivo
 *
 * @param {Page} page - Playwright page object
 * @param {string} size - Tamanho ('mobile', 'tablet', 'desktop')
 */
async function setViewport(page, size = 'desktop') {
    const viewport = VIEWPORTS[size] || VIEWPORTS.desktop;
    await page.setViewportSize(viewport);
    console.log(`[Viewport] Definido para ${size}: ${viewport.width}x${viewport.height}`);
}

/**
 * Aguarda e clica em um elemento com retry
 *
 * @param {Page} page - Playwright page object
 * @param {string} selector - Seletor do elemento
 * @param {number} timeout - Timeout em ms
 */
async function waitAndClick(page, selector, timeout = 5000) {
    await page.waitForSelector(selector, { timeout });
    await page.click(selector);
}

/**
 * Navega pelo menu principal
 *
 * @param {Page} page - Playwright page object
 * @param {string} menuItem - Item do menu para clicar
 */
async function navigateToMenu(page, menuItem) {
    console.log(`[Navegação] Indo para: ${menuItem}`);

    // Procura o link no menu
    const menuLink = page.locator(`a:has-text("${menuItem}")`).first();

    if (await menuLink.isVisible()) {
        await menuLink.click();
        await page.waitForLoadState('networkidle');
        console.log(`[Navegação] Chegou em: ${page.url()}`);
    } else {
        console.log(`[Navegação] Menu item não encontrado: ${menuItem}`);
    }
}

/**
 * Gera dados aleatórios para testes
 */
const randomData = {
    eventTitle: () => `Evento Teste ${Date.now()}`,
    eventDescription: () => `Descrição do evento de teste criado automaticamente em ${new Date().toLocaleString('pt-BR')}`,
    futureDate: (daysAhead = 30) => {
        const date = new Date();
        date.setDate(date.getDate() + daysAhead);
        return date.toISOString().slice(0, 16); // formato: YYYY-MM-DDTHH:mm
    },
    futureDateEnd: (daysAhead = 30, hoursAfter = 2) => {
        const date = new Date();
        date.setDate(date.getDate() + daysAhead);
        date.setHours(date.getHours() + hoursAfter);
        return date.toISOString().slice(0, 16);
    },
    capacity: () => Math.floor(Math.random() * 100) + 10,
    price: () => (Math.random() * 100).toFixed(2)
};

/**
 * Aguarda Lucide icons serem carregados
 *
 * @param {Page} page - Playwright page object
 */
async function waitForLucideIcons(page) {
    await page.waitForFunction(() => {
        return typeof window.lucide !== 'undefined';
    }, { timeout: 5000 }).catch(() => {
        console.log('[Lucide] Icons podem não ter carregado');
    });
}

/**
 * Verifica se um toast/notificação apareceu
 *
 * @param {Page} page - Playwright page object
 * @param {string} type - Tipo ('success', 'error', 'warning')
 * @param {number} timeout - Timeout em ms
 */
async function waitForToast(page, type = 'success', timeout = 5000) {
    const selector = `.eau-toast-${type}, .swal2-popup`;
    try {
        await page.waitForSelector(selector, { timeout });
        console.log(`[Toast] Notificação ${type} detectada`);
        return true;
    } catch {
        console.log(`[Toast] Nenhuma notificação ${type} em ${timeout}ms`);
        return false;
    }
}

/**
 * Fecha modais abertos
 *
 * @param {Page} page - Playwright page object
 */
async function closeModals(page) {
    // Tenta fechar modal Eau
    const eauModalClose = page.locator('.eau-modal-close, .eau-reg-modal-close');
    if (await eauModalClose.isVisible()) {
        await eauModalClose.click();
    }

    // Tenta fechar SweetAlert
    const swalClose = page.locator('.swal2-close, .swal2-cancel');
    if (await swalClose.isVisible()) {
        await swalClose.click();
    }
}

module.exports = {
    BASE_URL,
    CREDENTIALS,
    URLS,
    VIEWPORTS,
    login,
    takeScreenshot,
    setViewport,
    waitAndClick,
    navigateToMenu,
    randomData,
    waitForLucideIcons,
    waitForToast,
    closeModals
};
