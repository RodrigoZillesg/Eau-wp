/**
 * GERADOR DE APRESENTAÇÃO BILÍNGUE - EAU SYSTEM
 *
 * Gera documentos DOCX em Português e Inglês com:
 * - Textos explicativos detalhados para cada funcionalidade
 * - Screenshots organizados por tipo de usuário
 * - Seção de itens pendentes (Próximos Passos)
 * - Credenciais de acesso para o cliente
 * - Formatação profissional
 *
 * USO:
 *   node generate-presentation-bilingual.js              # Captura screenshots + gera DOCX (PT + EN)
 *   node generate-presentation-bilingual.js --docx-only  # Apenas gera DOCX
 *   node generate-presentation-bilingual.js --pt-only    # Apenas Português
 *   node generate-presentation-bilingual.js --en-only    # Apenas Inglês
 *
 * IMPORTANTE: Antes de gerar, edite as seções PENDING_ITEMS e CLIENT_ACCESS
 */

const { chromium } = require('playwright');
const {
    Document, Packer, Paragraph, TextRun, HeadingLevel, Table, TableRow, TableCell,
    WidthType, AlignmentType, ImageRun, PageBreak, Header, Footer, PageNumber,
    ShadingType, VerticalAlign, BorderStyle,
} = require('docx');
const fs = require('fs');
const path = require('path');
const sizeOf = require('image-size');

// ===================== CONFIGURAÇÕES =====================

const BASE_URL = 'http://eau-site.local';
const SCREENSHOTS_DIR = path.join(__dirname, 'presentation-screenshots');
const SYSTEM_VERSION = '1.51.64';

// ===================== EDITAR ANTES DE GERAR =====================

/**
 * ITENS PENDENTES - Edite esta lista antes de gerar o documento
 * Formato: { pt: 'Texto em português', en: 'Text in English' }
 */
const PENDING_ITEMS = [
    // === CORREÇÕES EM ANDAMENTO ===
    {
        pt: 'Correção na visualização de comprovantes de atividades importadas do sistema antigo',
        en: 'Fix for viewing activity evidence imported from the legacy system'
    },
    {
        pt: 'Ajuste no controle de acesso do botão "Merge Duplicates" para administradores de instituição',
        en: 'Access control adjustment for "Merge Duplicates" button for institution administrators'
    },
    {
        pt: 'Correção na funcionalidade de recuperação de senha',
        en: 'Fix for password recovery functionality'
    },
    {
        pt: 'Correção no botão de exportação de atividades CPD',
        en: 'Fix for CPD activities export button'
    },

    // === MIGRAÇÃO DE DADOS ===
    {
        pt: 'Importação completa dos dados do sistema antigo (pagamentos, memberships, comprovantes e datas de expiração)',
        en: 'Complete data import from legacy system (payments, memberships, receipts, and expiration dates)'
    },
    {
        pt: 'Preservação das datas originais de cadastro dos membros durante a importação',
        en: 'Preservation of original member registration dates during import'
    },
    {
        pt: 'Atualização automática de todos os campos relacionados quando um membro é aprovado em uma nova instituição',
        en: 'Automatic update of all related fields when a member is approved at a new institution'
    },

    // === INTEGRAÇÕES (AGUARDANDO ACESSOS/INFORMAÇÕES) ===
    {
        pt: 'Configuração de emails oficiais da English Australia (SMTP) - aguardando acesso ao DNS',
        en: 'Configuration of official English Australia emails (SMTP) - awaiting DNS access'
    },
    {
        pt: 'Integração com Mailchimp para sincronização automática de listas de emails - aguardando acesso',
        en: 'Mailchimp integration for automatic email list synchronization - awaiting access'
    },
    {
        pt: 'Sistema de tags para segmentação de membros nas listas de email',
        en: 'Tag system for member segmentation in email lists'
    },
    {
        pt: 'Integração com meio de pagamento para atualização automática de status (a confirmar necessidade)',
        en: 'Payment gateway integration for automatic status updates (to be confirmed)'
    },
    {
        pt: 'Configuração do sistema de agendamento automático de tarefas (Cron)',
        en: 'Configuration of automatic task scheduling system (Cron)'
    },

    // === CONFIGURAÇÕES FINAIS ===
    {
        pt: 'Integração com o novo site institucional desenvolvido pela equipe',
        en: 'Integration with the new institutional website developed by the team'
    },
    {
        pt: 'Configuração de conteúdos dinâmicos no site',
        en: 'Dynamic content configuration on the website'
    },
    {
        pt: 'Substituição da assinatura nos certificados de eventos pela assinatura oficial',
        en: 'Replacement of event certificate signature with the official signature'
    },
    {
        pt: 'Configuração do domínio oficial do sistema',
        en: 'Configuration of the official system domain'
    },
    {
        pt: 'Plugin de edição de conteúdos para autonomia na gestão (a confirmar escopo)',
        en: 'Content editing plugin for management autonomy (scope to be confirmed)'
    },
];

/**
 * CREDENCIAIS DE ACESSO DO CLIENTE - Edite antes de gerar
 */
const CLIENT_ACCESS = {
    url: 'https://eaudevapp.platty.tech/',
    email: 'rrzillesg@gmail.com',
    password: 'Pl@ttyPl@tty',
};

// ===================== CORES DO TEMA =====================

const COLORS = {
    primary: '2563EB',
    primaryLight: 'DBEAFE',
    secondary: '64748B',
    secondaryLight: 'F1F5F9',
    success: '10B981',
    successLight: 'D1FAE5',
    warning: 'F59E0B',
    warningLight: 'FEF3C7',
    danger: 'DC2626',
    dangerLight: 'FEE2E2',
    dark: '1F2937',
    gray: '6B7280',
    light: 'F9FAFB',
    white: 'FFFFFF',
    black: '000000',
    tableBorder: 'E5E7EB',
    tableHeader: 'F3F4F6',
    tableStripe: 'F9FAFB',
};

// ===================== CONTEÚDO BILÍNGUE =====================

const CONTENT = {
    pt: {
        lang: 'pt-BR',
        filename: 'Apresentacao-Sistema-Eau-PT.docx',

        // Capa
        cover: {
            organization: 'ENGLISH AUSTRALIA',
            title: 'Sistema de Gestão de Membros',
            subtitle: 'Apresentação Completa do Sistema',
            version: `Eau System v${SYSTEM_VERSION}`,
            author: 'Desenvolvido por: Platty / Rodrigo Zillesg',
        },

        // Seções
        sections: {
            toc: 'Sumário',
            overview: 'Visão Geral do Sistema',
            userTypes: 'Tipos de Usuários',
            permissions: 'Matriz de Permissões',
            pendingItems: 'Próximos Passos',
            clientAccess: 'Acesso para Testes',
            conclusion: 'Conclusão',
        },

        // Visão Geral
        overview: {
            intro: 'O Eau System é uma plataforma completa de gestão de membros desenvolvida especificamente para a English Australia. O sistema centraliza todas as operações relacionadas aos membros, instituições parceiras, eventos e desenvolvimento profissional contínuo (CPD).',
            features: [
                'Gestão completa de membros individuais e institucionais',
                'Sistema de rastreamento de atividades CPD (Continuous Professional Development)',
                'Plataforma de eventos com inscrições e pagamentos online',
                'Sistema de membership com diferentes níveis e benefícios',
                'Integração nativa com OpenLearning para cursos online',
                'Módulo financeiro com gestão de pagamentos e faturas',
                'Dashboard personalizado por tipo de usuário',
                'Relatórios e exportação de dados em CSV',
            ],
            stats: {
                title: 'Estatísticas Atuais do Sistema',
                items: [
                    '6.188 membros cadastrados no sistema',
                    '128 instituições parceiras ativas',
                    '16.333 atividades CPD registradas',
                    '55 cursos disponíveis na plataforma OpenLearning',
                    '9 eventos programados',
                ],
            },
        },

        // Tipos de Usuários
        userTypesIntro: 'O sistema implementa uma hierarquia de permissões com 4 níveis de acesso distintos, cada um com funcionalidades específicas adequadas ao seu papel na organização:',
        userTypesTable: [
            ['Tipo de Usuário', 'Descrição', 'Principais Responsabilidades'],
            ['Super Admin', 'Administrador master com acesso irrestrito', 'Configurações do sistema, gestão completa, deleção em massa'],
            ['Admin', 'Administrador da English Australia', 'Gestão de membros, instituições, eventos e pagamentos'],
            ['Institution Admin', 'Administrador de instituição parceira', 'Gestão dos membros vinculados à sua instituição'],
            ['Member', 'Membro individual da organização', 'Acesso ao próprio perfil, CPDs e cursos'],
        ],

        // Permissões
        permissionsIntro: 'A tabela abaixo apresenta um resumo completo das funcionalidades disponíveis para cada tipo de usuário do sistema:',
        permissionsTable: [
            ['Funcionalidade', 'Super Admin', 'Admin', 'Inst. Admin', 'Member'],
            ['Dashboard Administrativo', '✓', '✓', '—', '—'],
            ['Gestão de Membros', '✓', '✓', '—', '—'],
            ['Gestão de Instituições', '✓', '✓', '—', '—'],
            ['Gestão de Atividades CPD', '✓', '✓', '—', '—'],
            ['Gestão de Categorias CPD', '✓', '—', '—', '—'],
            ['Gestão de Eventos', '✓', '✓', '—', '—'],
            ['Gestão de Pagamentos', '✓', '✓', '—', '—'],
            ['Aprovação de Membership', '✓', '✓', '—', '—'],
            ['Minha Instituição', '—', '—', '✓', '—'],
            ['Perfil Pessoal', '✓', '✓', '✓', '✓'],
            ['Minhas Atividades CPD', '✓', '✓', '✓', '✓'],
            ['Eventos Públicos', '✓', '✓', '✓', '✓'],
            ['Cursos OpenLearning', '✓', '✓', '✓', '✓'],
            ['Deleção em Massa', '✓', '—', '—', '—'],
        ],

        // Próximos Passos
        pendingIntro: 'Os itens abaixo representam as funcionalidades que ainda serão desenvolvidas nas próximas etapas do projeto:',
        noPendingItems: 'Não há itens pendentes no momento. Todas as funcionalidades planejadas foram implementadas.',

        // Acesso do Cliente
        accessIntro: 'Utilize as credenciais abaixo para acessar o sistema e testar as funcionalidades:',
        accessLabels: {
            url: 'URL do Sistema',
            email: 'Email',
            password: 'Senha',
            note: 'Nota: Este acesso é exclusivo para testes. Por favor, não compartilhe estas credenciais.',
        },

        // Conclusão
        conclusionText: 'O Eau System representa uma solução completa e integrada para a gestão de membros da English Australia. Com sua arquitetura bem definida de permissões, interface intuitiva e funcionalidades abrangentes, o sistema atende às necessidades de todos os stakeholders da organização.',
        conclusionFeatures: [
            'Hierarquia de permissões bem estruturada e segura',
            'Interface moderna, responsiva e de fácil utilização',
            'Gestão centralizada de membros, instituições e eventos',
            'Sistema CPD completo para rastreamento de desenvolvimento profissional',
            'Integrações com plataformas externas (OpenLearning, pagamentos)',
            'Relatórios detalhados e exportação de dados',
        ],

        // Labels gerais
        labels: {
            figure: 'Figura',
            version: 'Versão',
            date: 'Data',
            page: 'Página',
            of: 'de',
        },

        // Usuários (textos detalhados)
        users: {
            superAdmin: {
                title: '1. Super Admin (Administrador Master)',
                description: 'O Super Admin possui acesso irrestrito a todas as funcionalidades do sistema. Este perfil é destinado aos administradores técnicos e gestores principais da English Australia, responsáveis pela configuração e manutenção geral da plataforma.',
                capabilities: [
                    'Acesso completo a todos os módulos do sistema',
                    'Gestão de todos os tipos de usuários',
                    'Configurações avançadas e deleção em massa',
                    'Visualização de estatísticas globais',
                    'Merge de registros duplicados',
                ],
                pages: {
                    '01-dashboard': {
                        title: 'Dashboard Principal',
                        description: 'O Dashboard do Super Admin apresenta uma visão geral completa do sistema com estatísticas em tempo real. Na parte superior, cards informativos exibem: Total de Membros (6.188), Total de Instituições (128), Atividades CPD (16.333), Próximos Eventos (9), Pontos CPD Concedidos (114.438,8), Pagamentos Pendentes e Aprovações Pendentes de novos usuários.',
                        details: 'A seção "Share Registration Page" permite compartilhar o link de registro via QR Code ou redes sociais (WhatsApp, LinkedIn, X, Email). Na parte inferior, são exibidos os cursos disponíveis no OpenLearning com acesso direto à plataforma.',
                    },
                    '02-members': {
                        title: 'Members Management (Gestão de Membros)',
                        description: 'Página central para gerenciamento de todos os membros do sistema. Oferece uma tabela completa com busca avançada por nome, email ou telefone, e filtros múltiplos por Status, Tipo de Usuário, Instituição, Data de Registro e Tipo de Membership.',
                        details: 'Funcionalidades disponíveis: "Delete Selected" para remover membros selecionados, "Delete All Filtered" para deleção em massa baseada nos filtros aplicados (exclusivo Super Admin), "Export CSV" para exportação de dados, "Merge Duplicates" para unificar registros duplicados, e "Add Member" para cadastro manual. Cada linha possui ações individuais de View, Edit e Delete.',
                    },
                    '03-institutions': {
                        title: 'Institutions Management (Gestão de Instituições)',
                        description: 'Módulo dedicado à gestão das 128 instituições parceiras da English Australia. Exibe informações completas de cada instituição incluindo nome, contato principal, status de membership e número de membros vinculados.',
                        details: 'Permite criar novas instituições, editar informações existentes, vincular/desvincular membros e gerenciar o status de cada parceria institucional.',
                    },
                    '04-activities': {
                        title: 'CPD Activities Management (Gestão de Atividades CPD)',
                        description: 'Central de gerenciamento das atividades de Desenvolvimento Profissional Contínuo (CPD). Lista todas as 16.333 atividades registradas no sistema com informações de membro, categoria, pontos e status de aprovação.',
                        details: 'Os administradores podem aprovar ou rejeitar atividades pendentes, visualizar detalhes completos incluindo evidências anexadas, e exportar relatórios de CPD por período ou membro.',
                    },
                    '05-categories': {
                        title: 'CPD Categories Management (Gestão de Categorias)',
                        description: 'Configuração das categorias de atividades CPD disponíveis no sistema. Define a estrutura de categorização e os pontos atribuídos a cada tipo de atividade de desenvolvimento profissional.',
                        details: 'Funcionalidade exclusiva do Super Admin. Permite criar, editar e organizar as categorias que serão utilizadas pelos membros ao registrar suas atividades CPD.',
                    },
                    '06-events-admin': {
                        title: 'Events Management (Gestão de Eventos)',
                        description: 'Painel administrativo completo para gestão de eventos da English Australia. Permite criar novos eventos com configurações detalhadas de data, local, capacidade, preços diferenciados por tipo de membro e pontos CPD.',
                        details: 'Funcionalidades incluem: criação de eventos com múltiplas sessões, gestão de inscrições e lista de participantes, controle de pagamentos, envio de comunicações aos inscritos, e publicação/despublicação de eventos.',
                    },
                    '07-payments': {
                        title: 'Payments Management (Gestão de Pagamentos)',
                        description: 'Módulo financeiro para controle de todas as transações do sistema. Exibe faturas de eventos, memberships e outros serviços com status de pagamento (Paid, Pending, Overdue).',
                        details: 'Permite filtrar por período, status de pagamento e tipo de transação. Oferece visualização detalhada de cada fatura e histórico de pagamentos por membro ou instituição.',
                    },
                    '08-membership-applications': {
                        title: 'Membership Applications (Aplicações de Membership)',
                        description: 'Gestão das solicitações de membership recebidas. Lista todas as aplicações pendentes com informações do solicitante, tipo de membership escolhido e documentação anexada.',
                        details: 'Os administradores podem aprovar ou rejeitar aplicações, solicitar documentação adicional e acompanhar o histórico de todas as solicitações processadas.',
                    },
                    '09-my-profile': {
                        title: 'My Profile (Meu Perfil)',
                        description: 'Página de perfil pessoal do usuário logado. Permite visualizar e editar informações pessoais, dados de contato, preferências de comunicação e configurações da conta.',
                        details: 'Inclui upload de foto de perfil, atualização de senha e visualização do histórico de atividades na plataforma.',
                    },
                    '10-my-cpds': {
                        title: 'My CPDs (Minhas Atividades CPD)',
                        description: 'Registro pessoal de atividades de Desenvolvimento Profissional Contínuo. Exibe o histórico completo de atividades CPD do usuário com pontuação acumulada e status de cada registro.',
                        details: 'Permite submeter novas atividades CPD com upload de evidências (certificados, comprovantes), acompanhar o status de aprovação e visualizar o progresso em direção às metas de desenvolvimento profissional.',
                    },
                    '11-events-frontend': {
                        title: 'Events (Página Pública de Eventos)',
                        description: 'Página pública que lista todos os eventos disponíveis da English Australia. Apresenta os eventos em formato de cards com informações essenciais: título, data, local, preço e disponibilidade de vagas.',
                        details: 'Oferece filtros por categoria e período. Ao clicar em um evento, o usuário acessa a página de detalhes com descrição completa, programação e botão de inscrição.',
                    },
                    '12-courses': {
                        title: 'OpenLearning Courses (Cursos Online)',
                        description: 'Integração com a plataforma OpenLearning exibindo os 55 cursos disponíveis para os membros. Cada curso é apresentado com imagem, título, descrição breve e indicação se é gratuito ou pago.',
                        details: 'O botão "Access Course" redireciona diretamente para a plataforma OpenLearning onde o membro pode iniciar ou continuar o curso. Os cursos completados podem gerar pontos CPD automaticamente.',
                    },
                },
            },
            Admin: {
                title: '2. Admin (Administrador)',
                description: 'O perfil Admin é destinado aos funcionários da English Australia responsáveis pela gestão operacional do dia a dia. Possui acesso à maioria das funcionalidades administrativas, exceto configurações avançadas e deleção em massa.',
                capabilities: [
                    'Gestão completa de membros e instituições',
                    'Aprovação de atividades CPD e memberships',
                    'Gestão de eventos e pagamentos',
                    'Exportação de relatórios',
                ],
                pages: {
                    '01-dashboard': {
                        title: 'Dashboard Principal',
                        description: 'Dashboard similar ao Super Admin, apresentando as mesmas estatísticas gerais do sistema. A diferença está na mensagem de boas-vindas que indica "Here\'s what\'s happening with your membership today" ao invés de indicar acesso total.',
                        details: 'Todas as funcionalidades de visualização estão disponíveis, incluindo QR Code de compartilhamento e acesso aos cursos OpenLearning.',
                    },
                    '02-members': {
                        title: 'Members Management',
                        description: 'Acesso completo à gestão de membros com todas as funcionalidades de busca, filtros e ações individuais. A principal diferença é a ausência do botão "Delete All Filtered", reservado ao Super Admin.',
                        details: 'Pode criar, editar, visualizar e deletar membros individualmente, além de exportar dados e utilizar a ferramenta de merge de duplicatas.',
                    },
                    '03-institutions': {
                        title: 'Institutions Management',
                        description: 'Gestão completa das instituições parceiras com as mesmas funcionalidades do Super Admin.',
                        details: 'Pode criar novas instituições, editar informações e gerenciar vínculos com membros.',
                    },
                    '04-activities': {
                        title: 'CPD Activities Management',
                        description: 'Acesso total à gestão de atividades CPD, podendo aprovar ou rejeitar submissões e visualizar todos os registros do sistema.',
                        details: 'Funcionalidades idênticas ao Super Admin neste módulo.',
                    },
                    '05-events-admin': {
                        title: 'Events Management',
                        description: 'Gestão completa de eventos com todas as funcionalidades de criação, edição e gerenciamento de inscrições.',
                        details: 'Pode criar eventos, gerenciar participantes e controlar pagamentos.',
                    },
                    '06-payments': {
                        title: 'Payments Management',
                        description: 'Acesso ao módulo financeiro para visualização e gestão de pagamentos.',
                        details: 'Pode visualizar todas as faturas, filtrar por status e gerar relatórios financeiros.',
                    },
                    '07-membership-applications': {
                        title: 'Membership Applications',
                        description: 'Gestão das solicitações de membership com poder de aprovação e rejeição.',
                        details: 'Processo idêntico ao Super Admin.',
                    },
                    '08-my-profile': {
                        title: 'My Profile',
                        description: 'Perfil pessoal com opções de edição de dados e configurações.',
                        details: 'Funcionalidades padrão de perfil de usuário.',
                    },
                    '09-events-frontend': {
                        title: 'Events (Frontend)',
                        description: 'Visualização da página pública de eventos.',
                        details: 'Mesma visualização disponível para todos os usuários.',
                    },
                },
            },
            institutionAdmin: {
                title: '3. Institution Admin (Administrador de Instituição)',
                description: 'Perfil destinado aos representantes das instituições parceiras da English Australia. O acesso é limitado às informações e membros vinculados à sua instituição específica, permitindo uma gestão descentralizada.',
                capabilities: [
                    'Visualização e gestão dos membros da sua instituição',
                    'Edição das informações institucionais',
                    'Aprovação de novos membros para a instituição',
                    'Acesso pessoal a CPDs e cursos',
                ],
                pages: {
                    '01-dashboard': {
                        title: 'Dashboard Principal',
                        description: 'Dashboard personalizado para administradores de instituição. Exibe informações relevantes à sua função: solicitações de membros pendentes, próximos eventos, perfil pessoal e status do membership.',
                        details: 'A identificação "Institution Administrator for [Nome da Instituição]" aparece logo abaixo da saudação, deixando claro o escopo de acesso do usuário. Os cards disponíveis são: Member Requests, Upcoming Events, My Profile e My Membership.',
                    },
                    '02-my-institution': {
                        title: 'My Institution (Minha Instituição)',
                        description: 'Página dedicada à gestão da instituição do usuário. Exibe informações completas da instituição, lista de membros vinculados e solicitações de novos membros aguardando aprovação.',
                        details: 'O administrador pode editar informações da instituição (dentro dos limites permitidos), aprovar ou rejeitar solicitações de vínculo de novos membros, e visualizar estatísticas da instituição.',
                    },
                    '03-my-profile': {
                        title: 'My Profile',
                        description: 'Perfil pessoal do administrador da instituição.',
                        details: 'Permite editar dados pessoais e configurações da conta.',
                    },
                    '04-my-cpds': {
                        title: 'My CPDs',
                        description: 'Registro pessoal de atividades CPD do administrador.',
                        details: 'Pode registrar suas próprias atividades de desenvolvimento profissional e acompanhar sua pontuação.',
                    },
                    '05-events-frontend': {
                        title: 'Events',
                        description: 'Acesso à página pública de eventos para visualização e inscrição.',
                        details: 'Pode visualizar todos os eventos disponíveis e realizar inscrições.',
                    },
                    '06-courses': {
                        title: 'Courses',
                        description: 'Acesso aos cursos disponíveis no OpenLearning.',
                        details: 'Pode acessar e realizar os cursos disponíveis na plataforma.',
                    },
                },
            },
            Member: {
                title: '4. Member (Membro)',
                description: 'Perfil básico para membros individuais da English Australia. O acesso é limitado às funcionalidades pessoais, permitindo que o membro gerencie seu próprio desenvolvimento profissional e participe das atividades da organização.',
                capabilities: [
                    'Gerenciamento do perfil pessoal',
                    'Registro e acompanhamento de atividades CPD',
                    'Inscrição em eventos',
                    'Acesso a cursos online',
                    'Solicitação de membership',
                ],
                pages: {
                    '01-dashboard': {
                        title: 'Dashboard Principal',
                        description: 'Dashboard simplificado focado nas necessidades do membro individual. Apresenta apenas as informações relevantes: próximos eventos disponíveis, acesso ao perfil, status do membership e cursos recomendados.',
                        details: 'Os cards disponíveis são: Upcoming Events (com contador de eventos), My Profile (acesso rápido), My Membership (status atual ou link para aplicar) e a seção de Available Courses do OpenLearning.',
                    },
                    '02-my-profile': {
                        title: 'My Profile',
                        description: 'Página completa de perfil pessoal onde o membro pode visualizar e editar todas as suas informações cadastrais.',
                        details: 'Inclui dados pessoais, informações de contato, foto de perfil, configurações de notificação e histórico de atividades na plataforma.',
                    },
                    '03-my-cpds': {
                        title: 'My CPDs',
                        description: 'Central de gerenciamento das atividades de Desenvolvimento Profissional Contínuo do membro. Permite registrar novas atividades e acompanhar o histórico completo.',
                        details: 'O membro pode: submeter novas atividades CPD com upload de evidências, acompanhar o status de aprovação de cada submissão, visualizar a pontuação acumulada por categoria e período.',
                    },
                    '04-membership-selection': {
                        title: 'Membership Selection',
                        description: 'Página para seleção e aquisição de planos de membership. Apresenta as opções disponíveis com descrição dos benefícios e valores de cada plano.',
                        details: 'O membro pode comparar os diferentes tipos de membership, selecionar o plano desejado e prosseguir para o pagamento.',
                    },
                    '05-events-frontend': {
                        title: 'Events',
                        description: 'Página pública de eventos onde o membro pode descobrir e se inscrever em eventos da English Australia.',
                        details: 'Lista todos os eventos disponíveis com filtros por categoria e data. Permite inscrição direta com pagamento se aplicável.',
                    },
                    '06-courses': {
                        title: 'Courses',
                        description: 'Catálogo de cursos disponíveis através da integração com OpenLearning. Apresenta os 55 cursos disponíveis com indicação de cursos gratuitos.',
                        details: 'Cada card de curso exibe: imagem, título, descrição e botão "Access Course" que redireciona para a plataforma OpenLearning.',
                    },
                },
            },
        },
    },

    // ==================== ENGLISH VERSION ====================
    en: {
        lang: 'en-US',
        filename: 'System-Presentation-Eau-EN.docx',

        cover: {
            organization: 'ENGLISH AUSTRALIA',
            title: 'Member Management System',
            subtitle: 'Complete System Presentation',
            version: `Eau System v${SYSTEM_VERSION}`,
            author: 'Developed by: Platty / Rodrigo Zillesg',
        },

        sections: {
            toc: 'Table of Contents',
            overview: 'System Overview',
            userTypes: 'User Types',
            permissions: 'Permissions Matrix',
            pendingItems: 'Next Steps',
            clientAccess: 'Test Access',
            conclusion: 'Conclusion',
        },

        overview: {
            intro: 'The Eau System is a comprehensive member management platform developed specifically for English Australia. The system centralizes all operations related to members, partner institutions, events, and Continuous Professional Development (CPD).',
            features: [
                'Complete management of individual and institutional members',
                'CPD (Continuous Professional Development) activity tracking system',
                'Event platform with online registrations and payments',
                'Membership system with different levels and benefits',
                'Native integration with OpenLearning for online courses',
                'Financial module with payment and invoice management',
                'Customized dashboard by user type',
                'Reports and CSV data export',
            ],
            stats: {
                title: 'Current System Statistics',
                items: [
                    '6,188 members registered in the system',
                    '128 active partner institutions',
                    '16,333 registered CPD activities',
                    '55 courses available on the OpenLearning platform',
                    '9 scheduled events',
                ],
            },
        },

        userTypesIntro: 'The system implements a permission hierarchy with 4 distinct access levels, each with specific functionalities appropriate to their role:',
        userTypesTable: [
            ['User Type', 'Description', 'Main Responsibilities'],
            ['Super Admin', 'Master administrator with unrestricted access', 'System settings, complete management, bulk deletion'],
            ['Admin', 'English Australia administrator', 'Member, institution, event and payment management'],
            ['Institution Admin', 'Partner institution administrator', 'Management of members linked to their institution'],
            ['Member', 'Individual organization member', 'Access to own profile, CPDs and courses'],
        ],

        permissionsIntro: 'The table below presents a complete summary of functionalities available for each user type:',
        permissionsTable: [
            ['Functionality', 'Super Admin', 'Admin', 'Inst. Admin', 'Member'],
            ['Administrative Dashboard', '✓', '✓', '—', '—'],
            ['Members Management', '✓', '✓', '—', '—'],
            ['Institutions Management', '✓', '✓', '—', '—'],
            ['CPD Activities Management', '✓', '✓', '—', '—'],
            ['CPD Categories Management', '✓', '—', '—', '—'],
            ['Events Management', '✓', '✓', '—', '—'],
            ['Payments Management', '✓', '✓', '—', '—'],
            ['Membership Approval', '✓', '✓', '—', '—'],
            ['My Institution', '—', '—', '✓', '—'],
            ['Personal Profile', '✓', '✓', '✓', '✓'],
            ['My CPD Activities', '✓', '✓', '✓', '✓'],
            ['Public Events', '✓', '✓', '✓', '✓'],
            ['OpenLearning Courses', '✓', '✓', '✓', '✓'],
            ['Bulk Deletion', '✓', '—', '—', '—'],
        ],

        pendingIntro: 'The items below represent features that will be developed in the next project phases:',
        noPendingItems: 'There are no pending items at this time. All planned features have been implemented.',

        accessIntro: 'Use the credentials below to access the system and test the functionalities:',
        accessLabels: {
            url: 'System URL',
            email: 'Email',
            password: 'Password',
            note: 'Note: This access is for testing purposes only. Please do not share these credentials.',
        },

        conclusionText: 'The Eau System represents a complete and integrated solution for English Australia\'s member management. With its well-defined permission architecture, intuitive interface, and comprehensive functionalities, the system meets the needs of all organization stakeholders.',
        conclusionFeatures: [
            'Well-structured and secure permission hierarchy',
            'Modern, responsive, and user-friendly interface',
            'Centralized management of members, institutions, and events',
            'Complete CPD system for professional development tracking',
            'Integrations with external platforms (OpenLearning, payments)',
            'Detailed reports and data export',
        ],

        labels: {
            figure: 'Figure',
            version: 'Version',
            date: 'Date',
            page: 'Page',
            of: 'of',
        },

        users: {
            superAdmin: {
                title: '1. Super Admin (Master Administrator)',
                description: 'The Super Admin has unrestricted access to all system functionalities. This profile is intended for technical administrators and main managers of English Australia.',
                capabilities: [
                    'Complete access to all system modules',
                    'Management of all user types',
                    'Advanced settings and bulk deletion',
                    'View global statistics',
                    'Merge duplicate records',
                ],
                pages: {
                    '01-dashboard': {
                        title: 'Main Dashboard',
                        description: 'The Super Admin Dashboard presents a complete system overview with real-time statistics. Cards display: Total Members (6,188), Total Institutions (128), CPD Activities (16,333), Upcoming Events (9), CPD Points Awarded, Pending Payments and Pending Approvals.',
                        details: 'The "Share Registration Page" section allows sharing the registration link via QR Code or social networks (WhatsApp, LinkedIn, X, Email). OpenLearning courses are displayed at the bottom.',
                    },
                    '02-members': {
                        title: 'Members Management',
                        description: 'Central page for managing all system members. Offers a complete table with advanced search by name, email or phone, and multiple filters by Status, User Type, Institution, Registration Date and Membership Type.',
                        details: 'Available functionalities: "Delete Selected", "Delete All Filtered" (Super Admin exclusive), "Export CSV", "Merge Duplicates", and "Add Member". Each row has View, Edit and Delete actions.',
                    },
                    '03-institutions': {
                        title: 'Institutions Management',
                        description: 'Module for managing English Australia\'s 128 partner institutions. Displays complete information for each institution including name, primary contact, membership status and linked members.',
                        details: 'Allows creating new institutions, editing information, linking/unlinking members and managing partnership status.',
                    },
                    '04-activities': {
                        title: 'CPD Activities Management',
                        description: 'Central management of Continuous Professional Development activities. Lists all 16,333 activities with member information, category, points and approval status.',
                        details: 'Administrators can approve or reject pending activities, view details including attached evidence, and export CPD reports.',
                    },
                    '05-categories': {
                        title: 'CPD Categories Management',
                        description: 'Configuration of CPD activity categories. Defines categorization structure and points assigned to each type of professional development activity.',
                        details: 'Super Admin exclusive functionality. Allows creating, editing and organizing categories used by members when registering CPD activities.',
                    },
                    '06-events-admin': {
                        title: 'Events Management',
                        description: 'Complete administrative panel for event management. Allows creating events with detailed settings for date, location, capacity, differentiated prices and CPD points.',
                        details: 'Includes: event creation with multiple sessions, registration management, payment control, communications to registrants, and publishing/unpublishing.',
                    },
                    '07-payments': {
                        title: 'Payments Management',
                        description: 'Financial module for controlling all system transactions. Displays invoices for events, memberships and services with payment status (Paid, Pending, Overdue).',
                        details: 'Filter by period, payment status and transaction type. Detailed view of each invoice and payment history.',
                    },
                    '08-membership-applications': {
                        title: 'Membership Applications',
                        description: 'Management of received membership applications. Lists all pending applications with applicant information, chosen membership type and attached documentation.',
                        details: 'Administrators can approve or reject applications, request additional documentation and track processed applications history.',
                    },
                    '09-my-profile': {
                        title: 'My Profile',
                        description: 'Personal profile page. Allows viewing and editing personal information, contact details, communication preferences and account settings.',
                        details: 'Includes profile photo upload, password update and platform activity history.',
                    },
                    '10-my-cpds': {
                        title: 'My CPDs',
                        description: 'Personal record of Continuous Professional Development activities. Displays complete history with accumulated score and status of each record.',
                        details: 'Submit new CPD activities with evidence upload, track approval status and view progress towards professional development goals.',
                    },
                    '11-events-frontend': {
                        title: 'Events (Public Page)',
                        description: 'Public page listing all available English Australia events. Presents events in card format with essential information: title, date, location, price and availability.',
                        details: 'Filters by category and period. Click on an event for full details and registration.',
                    },
                    '12-courses': {
                        title: 'OpenLearning Courses',
                        description: 'Integration with OpenLearning platform displaying 55 courses available to members. Each course shows image, title, description and free/paid indication.',
                        details: '"Access Course" button redirects to OpenLearning platform. Completed courses can automatically generate CPD points.',
                    },
                },
            },
            Admin: {
                title: '2. Admin (Administrator)',
                description: 'The Admin profile is for English Australia staff responsible for day-to-day operational management. Has access to most administrative functionalities, except advanced settings and bulk deletion.',
                capabilities: [
                    'Complete management of members and institutions',
                    'Approval of CPD activities and memberships',
                    'Event and payment management',
                    'Report export',
                ],
                pages: {
                    '01-dashboard': { title: 'Main Dashboard', description: 'Dashboard similar to Super Admin with same general statistics. Welcome message indicates "Here\'s what\'s happening with your membership today".', details: 'All viewing functionalities available including QR Code sharing and OpenLearning courses.' },
                    '02-members': { title: 'Members Management', description: 'Full member management access without "Delete All Filtered" button (Super Admin exclusive).', details: 'Create, edit, view and delete members individually, export data and merge duplicates.' },
                    '03-institutions': { title: 'Institutions Management', description: 'Complete partner institution management.', details: 'Create new institutions, edit information and manage member links.' },
                    '04-activities': { title: 'CPD Activities Management', description: 'Full CPD activities management, approve or reject submissions.', details: 'Functionalities identical to Super Admin.' },
                    '05-events-admin': { title: 'Events Management', description: 'Complete event management with all creation and registration functionalities.', details: 'Create events, manage participants and control payments.' },
                    '06-payments': { title: 'Payments Management', description: 'Financial module access for payment viewing and management.', details: 'View invoices, filter by status and generate reports.' },
                    '07-membership-applications': { title: 'Membership Applications', description: 'Membership applications management with approval/rejection power.', details: 'Process identical to Super Admin.' },
                    '08-my-profile': { title: 'My Profile', description: 'Personal profile with data editing options.', details: 'Standard user profile functionalities.' },
                    '09-events-frontend': { title: 'Events (Frontend)', description: 'Public events page view.', details: 'Same view available to all users.' },
                },
            },
            institutionAdmin: {
                title: '3. Institution Admin',
                description: 'Profile for English Australia partner institution representatives. Access limited to information and members linked to their specific institution.',
                capabilities: [
                    'View and manage their institution\'s members',
                    'Edit institutional information',
                    'Approve new members for the institution',
                    'Personal CPD and course access',
                ],
                pages: {
                    '01-dashboard': { title: 'Main Dashboard', description: 'Customized dashboard showing: pending member requests, upcoming events, profile and membership status.', details: 'Identification "Institution Administrator for [Institution Name]" shown below greeting.' },
                    '02-my-institution': { title: 'My Institution', description: 'Page for managing user\'s institution. Shows institution info, linked members and pending member requests.', details: 'Edit institution info, approve/reject new member link requests, view statistics.' },
                    '03-my-profile': { title: 'My Profile', description: 'Personal profile page.', details: 'Edit personal data and account settings.' },
                    '04-my-cpds': { title: 'My CPDs', description: 'Personal CPD activity record.', details: 'Register professional development activities and track score.' },
                    '05-events-frontend': { title: 'Events', description: 'Public events page for viewing and registration.', details: 'View all available events and register.' },
                    '06-courses': { title: 'Courses', description: 'Access to OpenLearning courses.', details: 'Access and take available courses.' },
                },
            },
            Member: {
                title: '4. Member',
                description: 'Basic profile for individual English Australia members. Access limited to personal functionalities for managing professional development and participating in organization activities.',
                capabilities: [
                    'Personal profile management',
                    'CPD activity registration and tracking',
                    'Event registration',
                    'Online course access',
                    'Membership application',
                ],
                pages: {
                    '01-dashboard': { title: 'Main Dashboard', description: 'Simplified dashboard focused on individual member needs: upcoming events, profile access, membership status and recommended courses.', details: 'Cards: Upcoming Events, My Profile, My Membership and Available Courses.' },
                    '02-my-profile': { title: 'My Profile', description: 'Complete personal profile page for viewing and editing registration information.', details: 'Personal data, contact info, profile photo, notification settings and activity history.' },
                    '03-my-cpds': { title: 'My CPDs', description: 'CPD activity management central. Register new activities and track complete history.', details: 'Submit CPD activities with evidence upload, track approval status, view accumulated score.' },
                    '04-membership-selection': { title: 'Membership Selection', description: 'Page for selecting and purchasing membership plans. Shows available options with benefits and values.', details: 'Compare membership types, select plan and proceed to payment.' },
                    '05-events-frontend': { title: 'Events', description: 'Public events page for discovering and registering for English Australia events.', details: 'List events with filters, direct registration with payment if applicable.' },
                    '06-courses': { title: 'Courses', description: 'Catalog of courses via OpenLearning integration. Shows 55 available courses with free course indication.', details: 'Course cards with image, title, description and "Access Course" button.' },
                },
            },
        },
    },
};

// ===================== CREDENCIAIS DOS USUÁRIOS =====================

const USERS_CONFIG = {
    superAdmin: {
        email: 'rrzillesg@gmail.com',
        password: 'Pl@ttyPl@tty',
        folder: 'superAdmin',
        pages: [
            { name: '01-dashboard', url: '/dashboard/' },
            { name: '02-members', url: '/dashboard/members/' },
            { name: '03-institutions', url: '/dashboard/manage-institutions/' },
            { name: '04-activities', url: '/dashboard/manage-activities/' },
            { name: '05-categories', url: '/dashboard/manage-categories/' },
            { name: '06-events-admin', url: '/dashboard/events/' },
            { name: '07-payments', url: '/dashboard/payments/' },
            { name: '08-membership-applications', url: '/dashboard/membership-applications/' },
            { name: '09-my-profile', url: '/dashboard/profile/' },
            { name: '10-my-cpds', url: '/dashboard/my-cpds/' },
            { name: '11-events-frontend', url: '/events/' },
            { name: '12-courses', url: '/dashboard/courses/' },
        ],
    },
    Admin: {
        email: 'rachelwinton@englishaustralia.com.au',
        password: 'TestPass2024!',
        folder: 'Admin',
        pages: [
            { name: '01-dashboard', url: '/dashboard/' },
            { name: '02-members', url: '/dashboard/members/' },
            { name: '03-institutions', url: '/dashboard/manage-institutions/' },
            { name: '04-activities', url: '/dashboard/manage-activities/' },
            { name: '05-events-admin', url: '/dashboard/events/' },
            { name: '06-payments', url: '/dashboard/payments/' },
            { name: '07-membership-applications', url: '/dashboard/membership-applications/' },
            { name: '08-my-profile', url: '/dashboard/profile/' },
            { name: '09-events-frontend', url: '/events/' },
        ],
    },
    institutionAdmin: {
        email: 'a.jamal@c2cglobal.com.au',
        password: 'TestPass2024!',
        folder: 'institutionAdmin',
        pages: [
            { name: '01-dashboard', url: '/dashboard/' },
            { name: '02-my-institution', url: '/dashboard/my-institution/' },
            { name: '03-my-profile', url: '/dashboard/profile/' },
            { name: '04-my-cpds', url: '/dashboard/my-cpds/' },
            { name: '05-events-frontend', url: '/events/' },
            { name: '06-courses', url: '/dashboard/courses/' },
        ],
    },
    Member: {
        email: 'aakanksha.mishra@sydney.edu.au',
        password: 'TestPass2024!',
        folder: 'Member',
        pages: [
            { name: '01-dashboard', url: '/dashboard/' },
            { name: '02-my-profile', url: '/dashboard/profile/' },
            { name: '03-my-cpds', url: '/dashboard/my-cpds/' },
            { name: '04-membership-selection', url: '/membership-selection/' },
            { name: '05-events-frontend', url: '/events/' },
            { name: '06-courses', url: '/dashboard/courses/' },
        ],
    },
};

// ===================== FUNÇÕES AUXILIARES =====================

function loadImage(imagePath) {
    try {
        if (fs.existsSync(imagePath)) return fs.readFileSync(imagePath);
    } catch (e) {}
    return null;
}

function getImageDimensions(imagePath) {
    try {
        if (fs.existsSync(imagePath)) {
            return sizeOf(imagePath);
        }
    } catch (e) {}
    return { width: 1920, height: 1080 }; // Default fallback
}

// ===================== FUNÇÕES DO DOCX =====================

function createHeading(text, level = HeadingLevel.HEADING_1) {
    return new Paragraph({
        text,
        heading: level,
        spacing: { before: 400, after: 200 },
    });
}

function createText(text, options = {}) {
    return new Paragraph({
        children: [new TextRun({
            text,
            size: options.size || 24,
            bold: options.bold || false,
            italics: options.italics || false,
            color: options.color || COLORS.dark,
        })],
        spacing: { before: options.spaceBefore || 100, after: options.spaceAfter || 100 },
        alignment: options.alignment || AlignmentType.JUSTIFIED,
    });
}

function createBullets(items) {
    return items.map(item => new Paragraph({
        children: [new TextRun({ text: `• ${item}`, size: 24 })],
        spacing: { before: 80, after: 80 },
        indent: { left: 720 },
    }));
}

function createImage(imagePath, maxWidth = 550) {
    const buffer = loadImage(imagePath);
    if (!buffer) {
        return createText('[Image not available]', { color: COLORS.danger, italics: true, alignment: AlignmentType.CENTER });
    }

    // Get actual image dimensions to maintain aspect ratio
    const dimensions = getImageDimensions(imagePath);
    const aspectRatio = dimensions.height / dimensions.width;
    const width = maxWidth;
    const height = Math.round(width * aspectRatio);

    return new Paragraph({
        children: [new ImageRun({
            data: buffer,
            transformation: { width, height },
        })],
        alignment: AlignmentType.CENTER,
        spacing: { before: 200, after: 100 },
        border: {
            top: { style: BorderStyle.SINGLE, size: 1, color: COLORS.tableBorder },
            bottom: { style: BorderStyle.SINGLE, size: 1, color: COLORS.tableBorder },
            left: { style: BorderStyle.SINGLE, size: 1, color: COLORS.tableBorder },
            right: { style: BorderStyle.SINGLE, size: 1, color: COLORS.tableBorder },
        },
    });
}

function createCaption(text) {
    return new Paragraph({
        children: [new TextRun({ text, size: 20, italics: true, color: COLORS.gray })],
        alignment: AlignmentType.CENTER,
        spacing: { before: 80, after: 300 },
    });
}

function createTable(data) {
    const borderStyle = {
        style: BorderStyle.SINGLE,
        size: 1,
        color: COLORS.tableBorder,
    };

    return new Table({
        rows: data.map((row, ri) => new TableRow({
            children: row.map((cell, ci) => new TableCell({
                children: [new Paragraph({
                    children: [new TextRun({
                        text: cell,
                        bold: ri === 0 || ci === 0,
                        size: ri === 0 ? 22 : 20,
                        color: ri === 0 ? COLORS.dark : (cell === '✓' ? COLORS.success : (cell === '—' ? COLORS.gray : COLORS.dark)),
                    })],
                    alignment: ci === 0 ? AlignmentType.LEFT : AlignmentType.CENTER,
                    spacing: { before: 60, after: 60 },
                })],
                shading: {
                    fill: ri === 0 ? COLORS.tableHeader : (ri % 2 === 0 ? COLORS.white : COLORS.tableStripe),
                    type: ShadingType.SOLID,
                    color: ri === 0 ? COLORS.tableHeader : (ri % 2 === 0 ? COLORS.white : COLORS.tableStripe),
                },
                verticalAlign: VerticalAlign.CENTER,
                borders: {
                    top: borderStyle,
                    bottom: borderStyle,
                    left: borderStyle,
                    right: borderStyle,
                },
            })),
        })),
        width: { size: 100, type: WidthType.PERCENTAGE },
    });
}

function createAccessTable(labels, access) {
    const borderStyle = { style: BorderStyle.SINGLE, size: 1, color: COLORS.tableBorder };
    const data = [
        [labels.url, access.url || 'N/A'],
        [labels.email, access.email || 'N/A'],
        [labels.password, access.password || 'N/A'],
    ];

    return new Table({
        rows: data.map((row, ri) => new TableRow({
            children: row.map((cell, ci) => new TableCell({
                children: [new Paragraph({
                    children: [new TextRun({
                        text: cell,
                        bold: ci === 0,
                        size: 24,
                        color: COLORS.dark,
                    })],
                    spacing: { before: 100, after: 100 },
                })],
                shading: {
                    fill: ci === 0 ? COLORS.tableHeader : COLORS.white,
                    type: ShadingType.SOLID,
                    color: ci === 0 ? COLORS.tableHeader : COLORS.white,
                },
                verticalAlign: VerticalAlign.CENTER,
                borders: { top: borderStyle, bottom: borderStyle, left: borderStyle, right: borderStyle },
                width: ci === 0 ? { size: 25, type: WidthType.PERCENTAGE } : { size: 75, type: WidthType.PERCENTAGE },
            })),
        })),
        width: { size: 100, type: WidthType.PERCENTAGE },
    });
}

// ===================== GERAÇÃO DO DOCUMENTO =====================

function generateDocx(lang) {
    const content = CONTENT[lang];
    const today = new Date().toLocaleDateString(content.lang);
    const pendingItems = PENDING_ITEMS.map(item => item[lang]);

    console.log(`\nGenerating ${lang.toUpperCase()} document...`);

    const children = [];

    // ===== CAPA =====
    for (let i = 0; i < 5; i++) children.push(new Paragraph({ children: [] }));
    children.push(new Paragraph({
        children: [new TextRun({ text: content.cover.organization, size: 72, bold: true, color: COLORS.primary })],
        alignment: AlignmentType.CENTER,
    }));
    children.push(new Paragraph({ children: [] }));
    children.push(new Paragraph({
        children: [new TextRun({ text: content.cover.title, size: 48, color: COLORS.dark })],
        alignment: AlignmentType.CENTER,
    }));
    children.push(new Paragraph({
        children: [new TextRun({ text: content.cover.version, size: 28, color: COLORS.gray })],
        alignment: AlignmentType.CENTER,
    }));
    for (let i = 0; i < 4; i++) children.push(new Paragraph({ children: [] }));
    children.push(new Paragraph({
        children: [new TextRun({ text: content.cover.subtitle, size: 28, italics: true, color: COLORS.secondary })],
        alignment: AlignmentType.CENTER,
    }));
    for (let i = 0; i < 10; i++) children.push(new Paragraph({ children: [] }));
    children.push(new Paragraph({
        children: [new TextRun({ text: content.cover.author, size: 24, color: COLORS.gray })],
        alignment: AlignmentType.CENTER,
    }));
    children.push(new Paragraph({
        children: [new TextRun({ text: today, size: 24, color: COLORS.gray })],
        alignment: AlignmentType.CENTER,
    }));
    children.push(new Paragraph({ children: [new PageBreak()] }));

    // ===== VISÃO GERAL =====
    children.push(createHeading(content.sections.overview));
    children.push(createText(content.overview.intro));
    children.push(new Paragraph({ children: [] }));
    children.push(...createBullets(content.overview.features));
    children.push(new Paragraph({ children: [] }));
    children.push(createHeading(content.overview.stats.title, HeadingLevel.HEADING_2));
    children.push(...createBullets(content.overview.stats.items));
    children.push(new Paragraph({ children: [new PageBreak()] }));

    // ===== TIPOS DE USUÁRIOS =====
    children.push(createHeading(content.sections.userTypes));
    children.push(createText(content.userTypesIntro));
    children.push(new Paragraph({ children: [] }));
    children.push(createTable(content.userTypesTable));
    children.push(new Paragraph({ children: [new PageBreak()] }));

    // ===== SEÇÕES DE USUÁRIOS =====
    for (const [userKey, userContent] of Object.entries(content.users)) {
        children.push(createHeading(userContent.title));
        children.push(createText(userContent.description));
        children.push(new Paragraph({ children: [] }));

        if (userContent.capabilities && userContent.capabilities.length > 0) {
            children.push(createText(lang === 'pt' ? 'Principais capacidades:' : 'Main capabilities:', { bold: true }));
            children.push(...createBullets(userContent.capabilities));
            children.push(new Paragraph({ children: [] }));
        }

        // Páginas
        for (const [pageKey, pageContent] of Object.entries(userContent.pages)) {
            const imagePath = path.join(SCREENSHOTS_DIR, USERS_CONFIG[userKey].folder, `${pageKey}.png`);

            children.push(createHeading(pageContent.title, HeadingLevel.HEADING_2));
            children.push(createText(pageContent.description));

            if (pageContent.details) {
                children.push(createText(pageContent.details));
            }

            children.push(new Paragraph({ children: [] }));
            children.push(createImage(imagePath));
            children.push(createCaption(`${content.labels.figure}: ${pageContent.title}`));
        }

        children.push(new Paragraph({ children: [new PageBreak()] }));
    }

    // ===== TABELA DE PERMISSÕES =====
    children.push(createHeading(content.sections.permissions));
    children.push(createText(content.permissionsIntro));
    children.push(new Paragraph({ children: [] }));
    children.push(createTable(content.permissionsTable));
    children.push(new Paragraph({ children: [new PageBreak()] }));

    // ===== PRÓXIMOS PASSOS (ITENS PENDENTES) =====
    children.push(createHeading(content.sections.pendingItems));
    if (pendingItems.length > 0) {
        children.push(createText(content.pendingIntro));
        children.push(new Paragraph({ children: [] }));
        children.push(...createBullets(pendingItems));
    } else {
        children.push(createText(content.noPendingItems, { italics: true, color: COLORS.gray }));
    }
    children.push(new Paragraph({ children: [] }));

    // ===== ACESSO PARA TESTES =====
    if (CLIENT_ACCESS.url || CLIENT_ACCESS.email) {
        children.push(createHeading(content.sections.clientAccess, HeadingLevel.HEADING_2));
        children.push(createText(content.accessIntro));
        children.push(new Paragraph({ children: [] }));
        children.push(createAccessTable(content.accessLabels, CLIENT_ACCESS));
        children.push(new Paragraph({ children: [] }));
        children.push(createText(content.accessLabels.note, { italics: true, color: COLORS.warning, size: 20 }));
    }
    children.push(new Paragraph({ children: [new PageBreak()] }));

    // ===== CONCLUSÃO =====
    children.push(createHeading(content.sections.conclusion));
    children.push(createText(content.conclusionText));
    children.push(new Paragraph({ children: [] }));
    children.push(...createBullets(content.conclusionFeatures));
    children.push(new Paragraph({ children: [] }));
    children.push(new Paragraph({ children: [] }));
    children.push(createText(`${content.labels.version}: ${SYSTEM_VERSION}`, { bold: true }));
    children.push(createText(`${content.labels.date}: ${today}`));

    // Criar documento
    const doc = new Document({
        creator: 'Platty / Rodrigo Zillesg',
        title: content.cover.title,
        sections: [{
            properties: {},
            headers: {
                default: new Header({
                    children: [new Paragraph({
                        children: [new TextRun({
                            text: `${content.cover.organization} - ${content.cover.title}`,
                            size: 18,
                            color: COLORS.gray,
                        })],
                        alignment: AlignmentType.RIGHT,
                    })],
                }),
            },
            footers: {
                default: new Footer({
                    children: [new Paragraph({
                        children: [
                            new TextRun({ text: `${content.labels.page} `, size: 18, color: COLORS.gray }),
                            new TextRun({ children: [PageNumber.CURRENT], size: 18, color: COLORS.gray }),
                            new TextRun({ text: ` ${content.labels.of} `, size: 18, color: COLORS.gray }),
                            new TextRun({ children: [PageNumber.TOTAL_PAGES], size: 18, color: COLORS.gray }),
                        ],
                        alignment: AlignmentType.CENTER,
                    })],
                }),
            },
            children,
        }],
    });

    return doc;
}

async function saveDocx(doc, filename) {
    const outputPath = path.join(SCREENSHOTS_DIR, filename);
    const buffer = await Packer.toBuffer(doc);
    fs.writeFileSync(outputPath, buffer);
    console.log(`  Saved: ${filename} (${(buffer.length / 1024 / 1024).toFixed(2)} MB)`);
    return outputPath;
}

// ===================== CAPTURA DE SCREENSHOTS =====================

async function captureScreenshots() {
    console.log('\n' + '='.repeat(60));
    console.log('CAPTURING SCREENSHOTS');
    console.log('='.repeat(60));

    const browser = await chromium.launch({ headless: false, slowMo: 50 });
    const context = await browser.newContext({ viewport: { width: 1920, height: 1080 } });
    const page = await context.newPage();
    page.setDefaultTimeout(60000);

    try {
        for (const [userKey, config] of Object.entries(USERS_CONFIG)) {
            console.log(`\n>>> ${userKey.toUpperCase()}`);

            const userFolder = path.join(SCREENSHOTS_DIR, config.folder);
            if (!fs.existsSync(userFolder)) fs.mkdirSync(userFolder, { recursive: true });

            // Login
            await page.goto(`${BASE_URL}/login/`, { waitUntil: 'domcontentloaded' });
            await page.fill('input[name="log"]', config.email);
            await page.fill('input[name="pwd"]', config.password);
            await page.click('button[name="wp-submit"]');
            await page.waitForLoadState('networkidle');
            await page.waitForTimeout(2000);

            // Capture pages
            for (const pageInfo of config.pages) {
                try {
                    console.log(`    ${pageInfo.name}...`);
                    await page.goto(`${BASE_URL}${pageInfo.url}`, { waitUntil: 'networkidle', timeout: 60000 });
                    await page.waitForTimeout(3000);
                    await page.evaluate(() => window.scrollTo(0, 0));
                    await page.screenshot({ path: path.join(userFolder, `${pageInfo.name}.png`), fullPage: true });
                } catch (e) {
                    console.log(`    ERROR: ${pageInfo.name}`);
                }
            }

            // Logout
            await page.goto(`${BASE_URL}/wp-login.php?action=logout`, { waitUntil: 'networkidle' });
            const logoutLink = page.locator('a:has-text("log out")');
            if (await logoutLink.isVisible({ timeout: 3000 }).catch(() => false)) {
                await logoutLink.click();
                await page.waitForLoadState('networkidle');
            }
            await page.waitForTimeout(1000);
        }
    } finally {
        await browser.close();
    }
}

// ===================== MAIN =====================

async function main() {
    const args = process.argv.slice(2);

    if (args.includes('--help')) {
        console.log(`
BILINGUAL PRESENTATION GENERATOR - EAU SYSTEM
==============================================

USAGE:
  node generate-presentation-bilingual.js              Capture screenshots + generate DOCX (PT + EN)
  node generate-presentation-bilingual.js --docx-only  Only generate DOCX (use existing screenshots)
  node generate-presentation-bilingual.js --pt-only    Only Portuguese
  node generate-presentation-bilingual.js --en-only    Only English
  node generate-presentation-bilingual.js --help       Show this help

IMPORTANT:
  Before generating, edit PENDING_ITEMS and CLIENT_ACCESS at the top of the script.
`);
        return;
    }

    console.log('='.repeat(60));
    console.log('BILINGUAL PRESENTATION GENERATOR - EAU SYSTEM');
    console.log('='.repeat(60));

    // Check for image-size package
    try {
        require('image-size');
    } catch (e) {
        console.log('\nInstalling image-size package...');
        require('child_process').execSync('npm install image-size --save-dev', { stdio: 'inherit' });
    }

    if (!fs.existsSync(SCREENSHOTS_DIR)) fs.mkdirSync(SCREENSHOTS_DIR, { recursive: true });

    // Capture screenshots if not --docx-only
    if (!args.includes('--docx-only')) {
        await captureScreenshots();
    }

    // Generate documents
    const generatePt = !args.includes('--en-only');
    const generateEn = !args.includes('--pt-only');

    console.log('\n' + '='.repeat(60));
    console.log('GENERATING DOCX DOCUMENTS');
    console.log('='.repeat(60));

    if (PENDING_ITEMS.length === 0) {
        console.log('\nNOTE: No pending items defined. Edit PENDING_ITEMS in the script.');
    }
    if (!CLIENT_ACCESS.url && !CLIENT_ACCESS.email) {
        console.log('NOTE: No client access credentials defined. Edit CLIENT_ACCESS in the script.');
    }

    if (generatePt) {
        const docPt = generateDocx('pt');
        await saveDocx(docPt, CONTENT.pt.filename);
    }

    if (generateEn) {
        const docEn = generateDocx('en');
        await saveDocx(docEn, CONTENT.en.filename);
    }

    console.log('\n' + '='.repeat(60));
    console.log('COMPLETED!');
    console.log('='.repeat(60));
    console.log(`Files saved in: ${SCREENSHOTS_DIR}`);
}

main().catch(console.error);
