/**
 * Script para gerar documento DOCX da apresentação do sistema
 *
 * USO: node generate-presentation-docx.js
 *
 * Este script gera um documento Word completo com:
 * - Capa
 * - Sumário
 * - Screenshots de cada tipo de usuário
 * - Tabelas de permissões
 * - Formatação profissional
 */

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
    BorderStyle,
    ImageRun,
    PageBreak,
    Header,
    Footer,
    PageNumber,
    NumberFormat,
    ShadingType,
    VerticalAlign,
    TableOfContents,
    StyleLevel,
} = require('docx');
const fs = require('fs');
const path = require('path');

// Configurações
const SCREENSHOTS_DIR = path.join(__dirname, 'presentation-screenshots');
const OUTPUT_FILE = path.join(SCREENSHOTS_DIR, 'Apresentacao-Sistema-Eau.docx');
const SYSTEM_VERSION = '1.51.64';

// Cores do tema
const COLORS = {
    primary: '2563EB',
    secondary: '64748B',
    success: '10B981',
    warning: 'F59E0B',
    danger: 'DC2626',
    dark: '1F2937',
    light: 'F3F4F6',
    white: 'FFFFFF',
};

// Função para carregar imagem como buffer
function loadImage(imagePath) {
    try {
        if (fs.existsSync(imagePath)) {
            return fs.readFileSync(imagePath);
        }
    } catch (error) {
        console.log(`Aviso: Imagem não encontrada: ${imagePath}`);
    }
    return null;
}

// Função para criar parágrafo de título
function createTitle(text, level = HeadingLevel.HEADING_1) {
    return new Paragraph({
        text: text,
        heading: level,
        spacing: { before: 400, after: 200 },
    });
}

// Função para criar parágrafo normal
function createParagraph(text, options = {}) {
    return new Paragraph({
        children: [
            new TextRun({
                text: text,
                size: options.size || 24,
                bold: options.bold || false,
                color: options.color || COLORS.dark,
            }),
        ],
        spacing: { before: 100, after: 100 },
        alignment: options.alignment || AlignmentType.LEFT,
    });
}

// Função para criar lista com bullets
function createBulletList(items) {
    return items.map(item =>
        new Paragraph({
            children: [
                new TextRun({
                    text: `• ${item}`,
                    size: 24,
                }),
            ],
            spacing: { before: 50, after: 50 },
            indent: { left: 720 },
        })
    );
}

// Função para criar imagem
function createImage(imagePath, description, width = 600) {
    const imageBuffer = loadImage(imagePath);
    if (!imageBuffer) {
        return createParagraph(`[Imagem não disponível: ${description}]`, { color: COLORS.danger });
    }

    return new Paragraph({
        children: [
            new ImageRun({
                data: imageBuffer,
                transformation: {
                    width: width,
                    height: width * 0.5625, // Proporção 16:9
                },
            }),
        ],
        alignment: AlignmentType.CENTER,
        spacing: { before: 200, after: 200 },
    });
}

// Função para criar legenda de imagem
function createCaption(text) {
    return new Paragraph({
        children: [
            new TextRun({
                text: text,
                size: 20,
                italics: true,
                color: COLORS.secondary,
            }),
        ],
        alignment: AlignmentType.CENTER,
        spacing: { before: 50, after: 300 },
    });
}

// Função para criar tabela de permissões
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
        ['Membership Selection', '✓', '✓', '✓', '✓'],
        ['Delete All Filtered', '✓', '—', '—', '—'],
        ['Merge Duplicates', '✓', '✓', '—', '—'],
    ];

    const rows = permissions.map((row, rowIndex) => {
        return new TableRow({
            children: row.map((cell, cellIndex) => {
                const isHeader = rowIndex === 0;
                const isFirstCol = cellIndex === 0;
                return new TableCell({
                    children: [
                        new Paragraph({
                            children: [
                                new TextRun({
                                    text: cell,
                                    bold: isHeader || isFirstCol,
                                    size: 20,
                                    color: cell === '✓' ? COLORS.success : (cell === '—' ? COLORS.secondary : COLORS.dark),
                                }),
                            ],
                            alignment: isFirstCol ? AlignmentType.LEFT : AlignmentType.CENTER,
                        }),
                    ],
                    shading: {
                        fill: isHeader ? COLORS.primary : (rowIndex % 2 === 0 ? COLORS.light : COLORS.white),
                        type: ShadingType.SOLID,
                        color: isHeader ? COLORS.primary : (rowIndex % 2 === 0 ? COLORS.light : COLORS.white),
                    },
                    verticalAlign: VerticalAlign.CENTER,
                });
            }),
        });
    });

    return new Table({
        rows: rows,
        width: { size: 100, type: WidthType.PERCENTAGE },
    });
}

// Função para criar tabela de tipos de usuário
function createUserTypesTable() {
    const types = [
        ['Tipo', 'Descrição', 'Acesso Principal'],
        ['Super Admin', 'Administrador master do sistema', 'Acesso total a todas funcionalidades'],
        ['Admin', 'Administrador da English Australia', 'Gestão de membros, instituições e eventos'],
        ['Institution Admin', 'Administrador de instituição parceira', 'Gestão da própria instituição e seus membros'],
        ['Member', 'Membro individual', 'Perfil pessoal, CPDs e cursos'],
    ];

    const rows = types.map((row, rowIndex) => {
        return new TableRow({
            children: row.map((cell, cellIndex) => {
                const isHeader = rowIndex === 0;
                return new TableCell({
                    children: [
                        new Paragraph({
                            children: [
                                new TextRun({
                                    text: cell,
                                    bold: isHeader || cellIndex === 0,
                                    size: 22,
                                    color: isHeader ? COLORS.white : COLORS.dark,
                                }),
                            ],
                        }),
                    ],
                    shading: {
                        fill: isHeader ? COLORS.primary : COLORS.white,
                        type: ShadingType.SOLID,
                        color: isHeader ? COLORS.primary : COLORS.white,
                    },
                    verticalAlign: VerticalAlign.CENTER,
                });
            }),
        });
    });

    return new Table({
        rows: rows,
        width: { size: 100, type: WidthType.PERCENTAGE },
    });
}

// Função para criar seção de screenshots de um tipo de usuário
function createUserSection(userType, title, description, pages) {
    const section = [];

    // Título da seção
    section.push(createTitle(title, HeadingLevel.HEADING_1));
    section.push(createParagraph(description));
    section.push(new Paragraph({ children: [] })); // Espaço

    // Para cada página
    pages.forEach((page, index) => {
        const imagePath = path.join(SCREENSHOTS_DIR, userType, `${page.file}.png`);

        section.push(createTitle(page.title, HeadingLevel.HEADING_2));

        if (page.description) {
            section.push(createParagraph(page.description));
        }

        section.push(createImage(imagePath, page.title));
        section.push(createCaption(`Figura: ${page.title}`));

        if (page.features) {
            section.push(createParagraph('Funcionalidades:', { bold: true }));
            section.push(...createBulletList(page.features));
        }

        section.push(new Paragraph({ children: [] })); // Espaço
    });

    return section;
}

// Função principal para gerar o documento
async function generateDocument() {
    console.log('='.repeat(60));
    console.log('GERANDO DOCUMENTO DOCX DA APRESENTAÇÃO');
    console.log('='.repeat(60));

    const today = new Date().toLocaleDateString('pt-BR');

    // Definição das páginas por tipo de usuário
    const superAdminPages = [
        { file: '01-dashboard', title: 'Dashboard Principal', description: 'Dashboard completo com estatísticas gerais, QR Code para compartilhamento e cursos disponíveis.', features: ['Total de Membros: 6.188', 'Total de Instituições: 128', 'Atividades CPD: 16.333', 'Próximos Eventos: 9', 'QR Code para registro', 'Cursos OpenLearning'] },
        { file: '02-members', title: 'Members Management', description: 'Gestão completa de membros com filtros avançados e ações em massa.', features: ['Busca por nome, email ou telefone', 'Filtros múltiplos', 'Exportação CSV', 'Merge de Duplicatas', 'Delete Selected/All Filtered'] },
        { file: '03-institutions', title: 'Institutions Management', description: 'Gestão de instituições parceiras.' },
        { file: '04-activities', title: 'CPD Activities Management', description: 'Gestão de atividades de desenvolvimento profissional.' },
        { file: '05-categories', title: 'CPD Categories Management', description: 'Configuração das categorias de atividades CPD.' },
        { file: '06-events-admin', title: 'Events Management', description: 'Painel administrativo para gestão de eventos.' },
        { file: '07-payments', title: 'Payments Management', description: 'Controle financeiro e gestão de faturas.' },
        { file: '08-membership-applications', title: 'Membership Applications', description: 'Gestão de solicitações de membership.' },
        { file: '09-my-profile', title: 'My Profile', description: 'Perfil pessoal do usuário.' },
        { file: '10-my-cpds', title: 'My CPDs', description: 'Registro pessoal de atividades CPD.' },
        { file: '11-events-frontend', title: 'Events (Frontend)', description: 'Página pública de eventos.' },
        { file: '12-courses', title: 'OpenLearning Courses', description: 'Integração com plataforma de cursos online.' },
    ];

    const adminPages = [
        { file: '01-dashboard', title: 'Dashboard Principal', description: 'Dashboard similar ao Super Admin com acesso às principais funcionalidades.' },
        { file: '02-members', title: 'Members Management', description: 'Gestão de membros.' },
        { file: '03-institutions', title: 'Institutions Management', description: 'Gestão de instituições.' },
        { file: '04-activities', title: 'CPD Activities Management', description: 'Gestão de atividades CPD.' },
        { file: '05-events-admin', title: 'Events Management', description: 'Gestão de eventos.' },
        { file: '06-payments', title: 'Payments Management', description: 'Gestão de pagamentos.' },
        { file: '07-membership-applications', title: 'Membership Applications', description: 'Aprovação de aplicações.' },
        { file: '08-my-profile', title: 'My Profile', description: 'Perfil pessoal.' },
        { file: '09-events-frontend', title: 'Events (Frontend)', description: 'Visualização de eventos.' },
    ];

    const institutionAdminPages = [
        { file: '01-dashboard', title: 'Dashboard Principal', description: 'Dashboard personalizado para administrador de instituição.', features: ['Member Requests', 'Upcoming Events', 'My Profile', 'My Membership'] },
        { file: '02-my-institution', title: 'My Institution', description: 'Página dedicada à gestão da instituição e seus membros.' },
        { file: '03-my-profile', title: 'My Profile', description: 'Perfil pessoal.' },
        { file: '04-my-cpds', title: 'My CPDs', description: 'Atividades CPD pessoais.' },
        { file: '05-events-frontend', title: 'Events', description: 'Visualização e inscrição em eventos.' },
        { file: '06-courses', title: 'Courses', description: 'Acesso aos cursos OpenLearning.' },
    ];

    const memberPages = [
        { file: '01-dashboard', title: 'Dashboard Principal', description: 'Dashboard simplificado para membros.', features: ['Upcoming Events', 'My Profile', 'My Membership', 'Available Courses'] },
        { file: '02-my-profile', title: 'My Profile', description: 'Perfil pessoal completo.' },
        { file: '03-my-cpds', title: 'My CPDs', description: 'Registro de atividades CPD.' },
        { file: '04-membership-selection', title: 'Membership Selection', description: 'Escolha de plano de membership.' },
        { file: '05-events-frontend', title: 'Events', description: 'Visualização e inscrição em eventos.' },
        { file: '06-courses', title: 'Courses', description: 'Acesso aos cursos OpenLearning.' },
    ];

    // Criar documento
    const doc = new Document({
        creator: 'Platty / Rodrigo Zillesg',
        title: 'Apresentação do Sistema Eau System',
        description: 'Documento de apresentação das funcionalidades do sistema',
        styles: {
            default: {
                heading1: {
                    run: {
                        size: 48,
                        bold: true,
                        color: COLORS.primary,
                    },
                    paragraph: {
                        spacing: { before: 400, after: 200 },
                    },
                },
                heading2: {
                    run: {
                        size: 36,
                        bold: true,
                        color: COLORS.dark,
                    },
                    paragraph: {
                        spacing: { before: 300, after: 150 },
                    },
                },
                heading3: {
                    run: {
                        size: 28,
                        bold: true,
                        color: COLORS.secondary,
                    },
                },
            },
        },
        sections: [
            {
                properties: {},
                headers: {
                    default: new Header({
                        children: [
                            new Paragraph({
                                children: [
                                    new TextRun({
                                        text: 'English Australia - Sistema Eau System',
                                        size: 18,
                                        color: COLORS.secondary,
                                    }),
                                ],
                                alignment: AlignmentType.RIGHT,
                            }),
                        ],
                    }),
                },
                footers: {
                    default: new Footer({
                        children: [
                            new Paragraph({
                                children: [
                                    new TextRun({
                                        text: 'Página ',
                                        size: 18,
                                    }),
                                    new TextRun({
                                        children: [PageNumber.CURRENT],
                                        size: 18,
                                    }),
                                    new TextRun({
                                        text: ' de ',
                                        size: 18,
                                    }),
                                    new TextRun({
                                        children: [PageNumber.TOTAL_PAGES],
                                        size: 18,
                                    }),
                                ],
                                alignment: AlignmentType.CENTER,
                            }),
                        ],
                    }),
                },
                children: [
                    // ==================== CAPA ====================
                    new Paragraph({ children: [] }),
                    new Paragraph({ children: [] }),
                    new Paragraph({ children: [] }),
                    new Paragraph({
                        children: [
                            new TextRun({
                                text: 'ENGLISH AUSTRALIA',
                                size: 72,
                                bold: true,
                                color: COLORS.primary,
                            }),
                        ],
                        alignment: AlignmentType.CENTER,
                    }),
                    new Paragraph({ children: [] }),
                    new Paragraph({
                        children: [
                            new TextRun({
                                text: 'Sistema de Gestão de Membros',
                                size: 48,
                                color: COLORS.dark,
                            }),
                        ],
                        alignment: AlignmentType.CENTER,
                    }),
                    new Paragraph({ children: [] }),
                    new Paragraph({
                        children: [
                            new TextRun({
                                text: `Eau System v${SYSTEM_VERSION}`,
                                size: 36,
                                color: COLORS.secondary,
                            }),
                        ],
                        alignment: AlignmentType.CENTER,
                    }),
                    new Paragraph({ children: [] }),
                    new Paragraph({ children: [] }),
                    new Paragraph({ children: [] }),
                    new Paragraph({ children: [] }),
                    new Paragraph({ children: [] }),
                    new Paragraph({
                        children: [
                            new TextRun({
                                text: 'Apresentação do Sistema',
                                size: 32,
                                italics: true,
                                color: COLORS.secondary,
                            }),
                        ],
                        alignment: AlignmentType.CENTER,
                    }),
                    new Paragraph({ children: [] }),
                    new Paragraph({ children: [] }),
                    new Paragraph({ children: [] }),
                    new Paragraph({ children: [] }),
                    new Paragraph({ children: [] }),
                    new Paragraph({ children: [] }),
                    new Paragraph({ children: [] }),
                    new Paragraph({ children: [] }),
                    new Paragraph({
                        children: [
                            new TextRun({
                                text: `Desenvolvido por: Platty / Rodrigo Zillesg`,
                                size: 24,
                                color: COLORS.secondary,
                            }),
                        ],
                        alignment: AlignmentType.CENTER,
                    }),
                    new Paragraph({
                        children: [
                            new TextRun({
                                text: `Data: ${today}`,
                                size: 24,
                                color: COLORS.secondary,
                            }),
                        ],
                        alignment: AlignmentType.CENTER,
                    }),
                    new Paragraph({ children: [new PageBreak()] }),

                    // ==================== SUMÁRIO ====================
                    createTitle('Sumário'),
                    createParagraph('1. Visão Geral do Sistema'),
                    createParagraph('2. Tipos de Usuários'),
                    createParagraph('3. Super Admin - Funcionalidades'),
                    createParagraph('4. Admin - Funcionalidades'),
                    createParagraph('5. Institution Admin - Funcionalidades'),
                    createParagraph('6. Member - Funcionalidades'),
                    createParagraph('7. Tabela de Permissões'),
                    createParagraph('8. Conclusão'),
                    new Paragraph({ children: [new PageBreak()] }),

                    // ==================== VISÃO GERAL ====================
                    createTitle('1. Visão Geral do Sistema'),
                    createParagraph('O Eau System é uma plataforma completa de gestão de membros desenvolvida especificamente para a English Australia. O sistema permite:'),
                    ...createBulletList([
                        'Gestão completa de membros e instituições',
                        'Rastreamento de atividades CPD (Continuous Professional Development)',
                        'Gestão de eventos com inscrições online',
                        'Sistema de membership com diferentes níveis',
                        'Integração com OpenLearning para cursos online',
                        'Gestão de pagamentos e faturas',
                    ]),
                    new Paragraph({ children: [] }),
                    createTitle('Estatísticas Atuais do Sistema', HeadingLevel.HEADING_2),
                    ...createBulletList([
                        '6.188 membros totais',
                        '128 instituições cadastradas',
                        '16.333 atividades CPD registradas',
                        '55 cursos disponíveis no OpenLearning',
                    ]),
                    new Paragraph({ children: [new PageBreak()] }),

                    // ==================== TIPOS DE USUÁRIOS ====================
                    createTitle('2. Tipos de Usuários'),
                    createParagraph('O sistema possui 4 níveis de acesso com permissões específicas:'),
                    new Paragraph({ children: [] }),
                    createUserTypesTable(),
                    new Paragraph({ children: [new PageBreak()] }),

                    // ==================== SUPER ADMIN ====================
                    ...createUserSection(
                        'superAdmin',
                        '3. Super Admin',
                        'Administrador com acesso total ao sistema, incluindo funcionalidades exclusivas de gestão e configuração.',
                        superAdminPages
                    ),
                    new Paragraph({ children: [new PageBreak()] }),

                    // ==================== ADMIN ====================
                    ...createUserSection(
                        'Admin',
                        '4. Admin',
                        'Administrador da English Australia com acesso à gestão de membros, instituições e eventos, porém sem acesso a funcionalidades exclusivas do Super Admin.',
                        adminPages
                    ),
                    new Paragraph({ children: [new PageBreak()] }),

                    // ==================== INSTITUTION ADMIN ====================
                    ...createUserSection(
                        'institutionAdmin',
                        '5. Institution Admin',
                        'Administrador de uma instituição parceira. Tem acesso limitado aos dados da sua própria instituição e seus membros vinculados.',
                        institutionAdminPages
                    ),
                    new Paragraph({ children: [new PageBreak()] }),

                    // ==================== MEMBER ====================
                    ...createUserSection(
                        'Member',
                        '6. Member',
                        'Membro individual da English Australia. Possui acesso apenas às suas próprias informações e recursos disponíveis para membros.',
                        memberPages
                    ),
                    new Paragraph({ children: [new PageBreak()] }),

                    // ==================== TABELA DE PERMISSÕES ====================
                    createTitle('7. Tabela de Permissões'),
                    createParagraph('Resumo completo das permissões por tipo de usuário:'),
                    new Paragraph({ children: [] }),
                    createPermissionsTable(),
                    new Paragraph({ children: [new PageBreak()] }),

                    // ==================== CONCLUSÃO ====================
                    createTitle('8. Conclusão'),
                    createParagraph('O sistema Eau System oferece uma solução completa e robusta para a gestão de membros da English Australia, com:'),
                    ...createBulletList([
                        'Hierarquia de permissões bem definida',
                        'Interface intuitiva e responsiva',
                        'Gestão completa de membros, instituições e eventos',
                        'Sistema CPD para desenvolvimento profissional',
                        'Integração com plataformas externas (OpenLearning)',
                        'Relatórios e exportação de dados',
                    ]),
                    new Paragraph({ children: [] }),
                    new Paragraph({ children: [] }),
                    new Paragraph({
                        children: [
                            new TextRun({
                                text: `Versão atual: ${SYSTEM_VERSION}`,
                                size: 24,
                                bold: true,
                            }),
                        ],
                    }),
                    new Paragraph({
                        children: [
                            new TextRun({
                                text: 'Desenvolvido por: Platty / Rodrigo Zillesg',
                                size: 24,
                            }),
                        ],
                    }),
                    new Paragraph({
                        children: [
                            new TextRun({
                                text: `Data: ${today}`,
                                size: 24,
                            }),
                        ],
                    }),
                ],
            },
        ],
    });

    // Gerar arquivo
    console.log('\nGerando arquivo DOCX...');
    const buffer = await Packer.toBuffer(doc);
    fs.writeFileSync(OUTPUT_FILE, buffer);

    console.log('\n' + '='.repeat(60));
    console.log('DOCUMENTO GERADO COM SUCESSO!');
    console.log('='.repeat(60));
    console.log(`Arquivo: ${OUTPUT_FILE}`);
    console.log(`Tamanho: ${(buffer.length / 1024).toFixed(2)} KB`);
    console.log('='.repeat(60));
}

// Executar
generateDocument().catch(error => {
    console.error('Erro ao gerar documento:', error);
    process.exit(1);
});
