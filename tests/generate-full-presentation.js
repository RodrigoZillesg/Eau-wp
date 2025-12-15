/**
 * SCRIPT COMPLETO DE GERAÇÃO DE APRESENTAÇÃO
 *
 * Este script:
 * 1. Faz login com cada tipo de usuário
 * 2. Captura screenshots de todas as páginas
 * 3. Gera o documento DOCX final
 *
 * USO:
 *   node generate-full-presentation.js              # Captura screenshots + gera DOCX
 *   node generate-full-presentation.js --docx-only  # Apenas gera DOCX (usa screenshots existentes)
 *   node generate-full-presentation.js --help       # Mostra ajuda
 *
 * REQUISITOS:
 *   - Node.js instalado
 *   - npm install playwright docx
 *   - Servidor local rodando (eau-site.local)
 */

const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

// Configurações
const BASE_URL = 'http://eau-site.local';
const SCREENSHOTS_DIR = path.join(__dirname, 'presentation-screenshots');

// Credenciais dos usuários (ATUALIZE SE NECESSÁRIO)
const USERS = {
    superAdmin: {
        email: 'rrzillesg@gmail.com',
        password: 'Pl@ttyPl@tty',
        title: 'Super Admin',
        description: 'Administrador com acesso total ao sistema.',
        pages: [
            { name: '01-dashboard', url: '/dashboard/', title: 'Dashboard Principal' },
            { name: '02-members', url: '/dashboard/members/', title: 'Members Management' },
            { name: '03-institutions', url: '/dashboard/manage-institutions/', title: 'Institutions Management' },
            { name: '04-activities', url: '/dashboard/manage-activities/', title: 'CPD Activities Management' },
            { name: '05-categories', url: '/dashboard/manage-categories/', title: 'CPD Categories Management' },
            { name: '06-events-admin', url: '/dashboard/events/', title: 'Events Management' },
            { name: '07-payments', url: '/dashboard/payments/', title: 'Payments Management' },
            { name: '08-membership-applications', url: '/dashboard/membership-applications/', title: 'Membership Applications' },
            { name: '09-my-profile', url: '/dashboard/profile/', title: 'My Profile' },
            { name: '10-my-cpds', url: '/dashboard/my-cpds/', title: 'My CPDs' },
            { name: '11-events-frontend', url: '/events/', title: 'Events (Frontend)' },
            { name: '12-courses', url: '/dashboard/courses/', title: 'OpenLearning Courses' },
        ],
    },
    Admin: {
        email: 'rachelwinton@englishaustralia.com.au',
        password: 'TestPass2024!',
        title: 'Admin',
        description: 'Administrador da English Australia.',
        pages: [
            { name: '01-dashboard', url: '/dashboard/', title: 'Dashboard Principal' },
            { name: '02-members', url: '/dashboard/members/', title: 'Members Management' },
            { name: '03-institutions', url: '/dashboard/manage-institutions/', title: 'Institutions Management' },
            { name: '04-activities', url: '/dashboard/manage-activities/', title: 'CPD Activities Management' },
            { name: '05-events-admin', url: '/dashboard/events/', title: 'Events Management' },
            { name: '06-payments', url: '/dashboard/payments/', title: 'Payments Management' },
            { name: '07-membership-applications', url: '/dashboard/membership-applications/', title: 'Membership Applications' },
            { name: '08-my-profile', url: '/dashboard/profile/', title: 'My Profile' },
            { name: '09-events-frontend', url: '/events/', title: 'Events (Frontend)' },
        ],
    },
    institutionAdmin: {
        email: 'a.jamal@c2cglobal.com.au',
        password: 'TestPass2024!',
        title: 'Institution Admin',
        description: 'Administrador de instituição parceira.',
        pages: [
            { name: '01-dashboard', url: '/dashboard/', title: 'Dashboard Principal' },
            { name: '02-my-institution', url: '/dashboard/my-institution/', title: 'My Institution' },
            { name: '03-my-profile', url: '/dashboard/profile/', title: 'My Profile' },
            { name: '04-my-cpds', url: '/dashboard/my-cpds/', title: 'My CPDs' },
            { name: '05-events-frontend', url: '/events/', title: 'Events (Frontend)' },
            { name: '06-courses', url: '/dashboard/courses/', title: 'OpenLearning Courses' },
        ],
    },
    Member: {
        email: 'aakanksha.mishra@sydney.edu.au',
        password: 'TestPass2024!',
        title: 'Member',
        description: 'Membro individual.',
        pages: [
            { name: '01-dashboard', url: '/dashboard/', title: 'Dashboard Principal' },
            { name: '02-my-profile', url: '/dashboard/profile/', title: 'My Profile' },
            { name: '03-my-cpds', url: '/dashboard/my-cpds/', title: 'My CPDs' },
            { name: '04-membership-selection', url: '/membership-selection/', title: 'Membership Selection' },
            { name: '05-events-frontend', url: '/events/', title: 'Events (Frontend)' },
            { name: '06-courses', url: '/dashboard/courses/', title: 'OpenLearning Courses' },
        ],
    },
};

// ===================== FUNÇÕES DE SCREENSHOT =====================

async function login(page, email, password) {
    await page.goto(`${BASE_URL}/login/`, { waitUntil: 'domcontentloaded', timeout: 60000 });
    await page.waitForSelector('input[name="log"]', { timeout: 30000 });
    await page.fill('input[name="log"]', email);
    await page.fill('input[name="pwd"]', password);
    await page.click('button[name="wp-submit"]');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);
}

async function logout(page) {
    await page.goto(`${BASE_URL}/wp-login.php?action=logout`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1000);
    const logoutLink = page.locator('a:has-text("log out")');
    if (await logoutLink.isVisible({ timeout: 3000 }).catch(() => false)) {
        await logoutLink.click();
        await page.waitForLoadState('networkidle');
    }
    await page.waitForTimeout(1000);
}

async function captureScreenshots() {
    console.log('\n' + '='.repeat(60));
    console.log('CAPTURANDO SCREENSHOTS');
    console.log('='.repeat(60));

    const browser = await chromium.launch({
        headless: false,
        slowMo: 50,
    });

    const context = await browser.newContext({
        viewport: { width: 1920, height: 1080 },
    });

    const page = await context.newPage();
    page.setDefaultTimeout(60000);

    const results = {};

    try {
        for (const [userType, config] of Object.entries(USERS)) {
            console.log(`\n>>> ${config.title.toUpperCase()}`);

            const userFolder = path.join(SCREENSHOTS_DIR, userType);
            if (!fs.existsSync(userFolder)) {
                fs.mkdirSync(userFolder, { recursive: true });
            }

            console.log(`    Fazendo login...`);
            await login(page, config.email, config.password);

            let success = 0;
            let failed = 0;

            for (const pageInfo of config.pages) {
                try {
                    console.log(`    Capturando: ${pageInfo.title}...`);
                    await page.goto(`${BASE_URL}${pageInfo.url}`, {
                        waitUntil: 'networkidle',
                        timeout: 60000,
                    });
                    await page.waitForTimeout(3000);
                    await page.evaluate(() => window.scrollTo(0, 0));
                    await page.waitForTimeout(500);

                    const screenshotPath = path.join(userFolder, `${pageInfo.name}.png`);
                    await page.screenshot({ path: screenshotPath, fullPage: true });
                    success++;
                } catch (error) {
                    console.log(`    ERRO: ${pageInfo.title} - ${error.message}`);
                    failed++;
                }
            }

            console.log(`    Fazendo logout...`);
            await logout(page);

            results[userType] = { success, failed };
            console.log(`    Resultado: ${success} OK, ${failed} falhas`);
        }
    } finally {
        await browser.close();
    }

    console.log('\n' + '='.repeat(60));
    console.log('RESUMO DOS SCREENSHOTS');
    console.log('='.repeat(60));
    let totalSuccess = 0;
    let totalFailed = 0;
    for (const [userType, result] of Object.entries(results)) {
        console.log(`  ${userType}: ${result.success} OK, ${result.failed} falhas`);
        totalSuccess += result.success;
        totalFailed += result.failed;
    }
    console.log(`  TOTAL: ${totalSuccess} OK, ${totalFailed} falhas`);
    console.log('='.repeat(60));

    return totalFailed === 0;
}

// ===================== FUNÇÕES DO DOCX =====================

async function generateDocx() {
    // Importar docx dinamicamente
    const {
        Document,
        Packer,
        Paragraph,
        TextRun,
        HeadingLevel,
        Table,
        TableRow,
        TableCell,
        WidthType,
        AlignmentType,
        ImageRun,
        PageBreak,
        Header,
        Footer,
        PageNumber,
        ShadingType,
        VerticalAlign,
    } = require('docx');

    console.log('\n' + '='.repeat(60));
    console.log('GERANDO DOCUMENTO DOCX');
    console.log('='.repeat(60));

    const COLORS = {
        primary: '2563EB',
        secondary: '64748B',
        success: '10B981',
        dark: '1F2937',
        light: 'F3F4F6',
        white: 'FFFFFF',
    };

    const today = new Date().toLocaleDateString('pt-BR');
    const SYSTEM_VERSION = '1.51.64';

    // Helpers
    function loadImage(imagePath) {
        try {
            if (fs.existsSync(imagePath)) return fs.readFileSync(imagePath);
        } catch (e) {}
        return null;
    }

    function createTitle(text, level = HeadingLevel.HEADING_1) {
        return new Paragraph({ text, heading: level, spacing: { before: 400, after: 200 } });
    }

    function createParagraph(text, options = {}) {
        return new Paragraph({
            children: [new TextRun({ text, size: options.size || 24, bold: options.bold || false, color: options.color || COLORS.dark })],
            spacing: { before: 100, after: 100 },
            alignment: options.alignment || AlignmentType.LEFT,
        });
    }

    function createBulletList(items) {
        return items.map(item => new Paragraph({
            children: [new TextRun({ text: `• ${item}`, size: 24 })],
            spacing: { before: 50, after: 50 },
            indent: { left: 720 },
        }));
    }

    function createImage(imagePath, width = 600) {
        const imageBuffer = loadImage(imagePath);
        if (!imageBuffer) return createParagraph('[Imagem não disponível]', { color: 'DC2626' });
        return new Paragraph({
            children: [new ImageRun({ data: imageBuffer, transformation: { width, height: width * 0.5625 } })],
            alignment: AlignmentType.CENTER,
            spacing: { before: 200, after: 200 },
        });
    }

    function createCaption(text) {
        return new Paragraph({
            children: [new TextRun({ text, size: 20, italics: true, color: COLORS.secondary })],
            alignment: AlignmentType.CENTER,
            spacing: { before: 50, after: 300 },
        });
    }

    // Criar seções de cada usuário
    function createUserSection(userType, config) {
        const section = [];
        section.push(createTitle(config.title, HeadingLevel.HEADING_1));
        section.push(createParagraph(config.description));
        section.push(new Paragraph({ children: [] }));

        config.pages.forEach(page => {
            const imagePath = path.join(SCREENSHOTS_DIR, userType, `${page.name}.png`);
            section.push(createTitle(page.title, HeadingLevel.HEADING_2));
            section.push(createImage(imagePath));
            section.push(createCaption(`Figura: ${page.title}`));
        });

        return section;
    }

    // Tabela de permissões
    function createPermissionsTable() {
        const permissions = [
            ['Funcionalidade', 'Super Admin', 'Admin', 'Institution Admin', 'Member'],
            ['Dashboard Completo', '✓', '✓', '—', '—'],
            ['Members Management', '✓', '✓', '—', '—'],
            ['Institutions Management', '✓', '✓', '—', '—'],
            ['Activities Management', '✓', '✓', '—', '—'],
            ['Categories Management', '✓', '—', '—', '—'],
            ['Events Management', '✓', '✓', '—', '—'],
            ['Payments Management', '✓', '✓', '—', '—'],
            ['Membership Applications', '✓', '✓', '—', '—'],
            ['My Institution', '—', '—', '✓', '—'],
            ['My Profile', '✓', '✓', '✓', '✓'],
            ['My CPDs', '✓', '✓', '✓', '✓'],
            ['Events (Frontend)', '✓', '✓', '✓', '✓'],
            ['Courses', '✓', '✓', '✓', '✓'],
        ];

        return new Table({
            rows: permissions.map((row, rowIndex) => new TableRow({
                children: row.map((cell, cellIndex) => new TableCell({
                    children: [new Paragraph({
                        children: [new TextRun({
                            text: cell,
                            bold: rowIndex === 0 || cellIndex === 0,
                            size: 20,
                            color: cell === '✓' ? COLORS.success : (cell === '—' ? COLORS.secondary : COLORS.dark),
                        })],
                        alignment: cellIndex === 0 ? AlignmentType.LEFT : AlignmentType.CENTER,
                    })],
                    shading: { fill: rowIndex === 0 ? COLORS.primary : (rowIndex % 2 === 0 ? COLORS.light : COLORS.white), type: ShadingType.SOLID },
                    verticalAlign: VerticalAlign.CENTER,
                })),
            })),
            width: { size: 100, type: WidthType.PERCENTAGE },
        });
    }

    // Construir documento
    const doc = new Document({
        creator: 'Platty / Rodrigo Zillesg',
        title: 'Apresentação do Sistema Eau System',
        sections: [{
            properties: {},
            headers: {
                default: new Header({
                    children: [new Paragraph({
                        children: [new TextRun({ text: 'English Australia - Sistema Eau System', size: 18, color: COLORS.secondary })],
                        alignment: AlignmentType.RIGHT,
                    })],
                }),
            },
            footers: {
                default: new Footer({
                    children: [new Paragraph({
                        children: [
                            new TextRun({ text: 'Página ', size: 18 }),
                            new TextRun({ children: [PageNumber.CURRENT], size: 18 }),
                            new TextRun({ text: ' de ', size: 18 }),
                            new TextRun({ children: [PageNumber.TOTAL_PAGES], size: 18 }),
                        ],
                        alignment: AlignmentType.CENTER,
                    })],
                }),
            },
            children: [
                // CAPA
                new Paragraph({ children: [] }), new Paragraph({ children: [] }), new Paragraph({ children: [] }),
                new Paragraph({ children: [new TextRun({ text: 'ENGLISH AUSTRALIA', size: 72, bold: true, color: COLORS.primary })], alignment: AlignmentType.CENTER }),
                new Paragraph({ children: [] }),
                new Paragraph({ children: [new TextRun({ text: 'Sistema de Gestão de Membros', size: 48, color: COLORS.dark })], alignment: AlignmentType.CENTER }),
                new Paragraph({ children: [new TextRun({ text: `Eau System v${SYSTEM_VERSION}`, size: 36, color: COLORS.secondary })], alignment: AlignmentType.CENTER }),
                new Paragraph({ children: [] }), new Paragraph({ children: [] }), new Paragraph({ children: [] }),
                new Paragraph({ children: [new TextRun({ text: 'Apresentação do Sistema', size: 32, italics: true, color: COLORS.secondary })], alignment: AlignmentType.CENTER }),
                new Paragraph({ children: [] }), new Paragraph({ children: [] }), new Paragraph({ children: [] }), new Paragraph({ children: [] }),
                new Paragraph({ children: [new TextRun({ text: `Desenvolvido por: Platty / Rodrigo Zillesg`, size: 24, color: COLORS.secondary })], alignment: AlignmentType.CENTER }),
                new Paragraph({ children: [new TextRun({ text: `Data: ${today}`, size: 24, color: COLORS.secondary })], alignment: AlignmentType.CENTER }),
                new Paragraph({ children: [new PageBreak()] }),

                // VISÃO GERAL
                createTitle('1. Visão Geral do Sistema'),
                createParagraph('O Eau System é uma plataforma completa de gestão de membros desenvolvida para a English Australia.'),
                ...createBulletList(['Gestão de membros e instituições', 'Rastreamento de atividades CPD', 'Gestão de eventos', 'Sistema de membership', 'Integração com OpenLearning', 'Gestão de pagamentos']),
                new Paragraph({ children: [new PageBreak()] }),

                // SEÇÕES DE USUÁRIOS
                ...createUserSection('superAdmin', USERS.superAdmin),
                new Paragraph({ children: [new PageBreak()] }),
                ...createUserSection('Admin', USERS.Admin),
                new Paragraph({ children: [new PageBreak()] }),
                ...createUserSection('institutionAdmin', USERS.institutionAdmin),
                new Paragraph({ children: [new PageBreak()] }),
                ...createUserSection('Member', USERS.Member),
                new Paragraph({ children: [new PageBreak()] }),

                // TABELA DE PERMISSÕES
                createTitle('Tabela de Permissões'),
                createParagraph('Resumo das permissões por tipo de usuário:'),
                new Paragraph({ children: [] }),
                createPermissionsTable(),
                new Paragraph({ children: [new PageBreak()] }),

                // CONCLUSÃO
                createTitle('Conclusão'),
                createParagraph('O sistema oferece uma solução completa para gestão de membros da English Australia.'),
                new Paragraph({ children: [] }),
                new Paragraph({ children: [new TextRun({ text: `Versão: ${SYSTEM_VERSION}`, size: 24, bold: true })]}),
                new Paragraph({ children: [new TextRun({ text: `Data: ${today}`, size: 24 })]}),
            ],
        }],
    });

    // Salvar
    const outputFile = path.join(SCREENSHOTS_DIR, 'Apresentacao-Sistema-Eau.docx');
    const buffer = await Packer.toBuffer(doc);
    fs.writeFileSync(outputFile, buffer);

    console.log(`\nDocumento gerado: ${outputFile}`);
    console.log(`Tamanho: ${(buffer.length / 1024).toFixed(2)} KB`);
    console.log('='.repeat(60));

    return outputFile;
}

// ===================== MAIN =====================

async function main() {
    const args = process.argv.slice(2);

    if (args.includes('--help') || args.includes('-h')) {
        console.log(`
GERADOR DE APRESENTAÇÃO DO SISTEMA EAU
======================================

USO:
  node generate-full-presentation.js              Captura screenshots + gera DOCX
  node generate-full-presentation.js --docx-only  Apenas gera DOCX (screenshots existentes)
  node generate-full-presentation.js --help       Mostra esta ajuda

REQUISITOS:
  - Node.js
  - npm install playwright docx
  - Servidor local rodando (eau-site.local)

CREDENCIAIS:
  As credenciais estão configuradas no início do script.
  Atualize se necessário.
`);
        return;
    }

    console.log('='.repeat(60));
    console.log('GERADOR DE APRESENTAÇÃO - EAU SYSTEM');
    console.log('='.repeat(60));
    console.log(`Data: ${new Date().toLocaleString('pt-BR')}`);

    // Criar diretório se não existir
    if (!fs.existsSync(SCREENSHOTS_DIR)) {
        fs.mkdirSync(SCREENSHOTS_DIR, { recursive: true });
    }

    if (!args.includes('--docx-only')) {
        // Capturar screenshots
        const success = await captureScreenshots();
        if (!success) {
            console.log('\nAVISO: Alguns screenshots falharam, mas o DOCX será gerado mesmo assim.');
        }
    } else {
        console.log('\nModo --docx-only: usando screenshots existentes.');
    }

    // Gerar DOCX
    await generateDocx();

    console.log('\n' + '='.repeat(60));
    console.log('PROCESSO CONCLUÍDO!');
    console.log('='.repeat(60));
}

main().catch(error => {
    console.error('Erro:', error);
    process.exit(1);
});
