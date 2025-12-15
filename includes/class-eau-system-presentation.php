<?php
/**
 * EAU System Presentation
 * Shortcode: [eau_system_presentation]
 *
 * Public presentation page showing all system features with screenshots
 * Bilingual support (PT/EN) with language tabs
 *
 * @package EauSystem
 */

namespace EauSystem;

if (!defined('ABSPATH')) {
    exit;
}

class Eau_System_Presentation {

    /**
     * Screenshots base URL
     */
    private static $screenshots_url;

    /**
     * System version
     */
    private static $version = '1.52.8';

    /**
     * Register shortcode
     */
    public static function register_shortcode() {
        add_shortcode('eau_system_presentation', array(__CLASS__, 'render_shortcode'));
    }

    /**
     * Enqueue assets
     */
    public static function enqueue_assets() {
        // Lucide Icons
        wp_enqueue_script(
            'lucide-icons',
            'https://unpkg.com/lucide@latest/dist/umd/lucide.min.js',
            array(),
            null,
            true
        );

        // CSS
        wp_enqueue_style(
            'eau-system-presentation',
            EAU_SYSTEM_PLUGIN_URL . 'assets/css/eau-system-presentation.css',
            array(),
            EAU_SYSTEM_VERSION
        );

        // JavaScript
        wp_enqueue_script(
            'eau-system-presentation',
            EAU_SYSTEM_PLUGIN_URL . 'assets/js/eau-system-presentation.js',
            array('jquery'),
            EAU_SYSTEM_VERSION,
            true
        );
    }

    /**
     * Get bilingual content
     */
    private static function get_content() {
        return array(
            'pt' => array(
                'cover' => array(
                    'organization' => 'ENGLISH AUSTRALIA',
                    'title' => 'Sistema de Gestão de Membros',
                    'subtitle' => 'Apresentação Completa do Sistema',
                ),
                'toc' => array(
                    'title' => 'Navegação Rápida',
                    'items' => array(
                        'overview' => 'Visão Geral',
                        'features' => 'Funcionalidades',
                        'user-types' => 'Tipos de Usuários',
                        'super-admin' => 'Super Admin',
                        'admin' => 'Admin',
                        'institution-admin' => 'Institution Admin',
                        'member' => 'Member',
                        'permissions' => 'Permissões',
                        'pending' => 'Próximos Passos',
                        'access' => 'Acesso para Testes',
                    ),
                ),
                'sections' => array(
                    'overview' => 'Visão Geral do Sistema',
                    'featuresDetails' => 'Funcionalidades Detalhadas',
                    'userTypes' => 'Tipos de Usuários',
                    'permissions' => 'Matriz de Permissões',
                    'pendingItems' => 'Próximos Passos',
                    'clientAccess' => 'Acesso para Testes',
                ),
                'overview' => array(
                    'intro' => 'O Eau System é uma plataforma completa de gestão de membros desenvolvida especificamente para a English Australia. O sistema centraliza todas as operações relacionadas aos membros, instituições parceiras, eventos e desenvolvimento profissional contínuo (CPD).',
                    'features' => array(
                        'Gestão completa de membros individuais e institucionais',
                        'Sistema de rastreamento de atividades CPD (Continuous Professional Development)',
                        'Plataforma de eventos com inscrições e pagamentos online',
                        'Sistema de membership com diferentes níveis e benefícios',
                        'Integração nativa com OpenLearning para cursos online',
                        'Módulo financeiro com gestão de pagamentos e faturas',
                        'Dashboard personalizado por tipo de usuário',
                        'Relatórios e exportação de dados em CSV',
                        'Sistema de tags para segmentação de membros com gestão em massa',
                    ),
                    'stats' => array(
                        array('value' => '6.188', 'label' => 'Membros Cadastrados'),
                        array('value' => '128', 'label' => 'Instituições Parceiras'),
                        array('value' => '16.333', 'label' => 'Atividades CPD'),
                        array('value' => '55', 'label' => 'Cursos OpenLearning'),
                        array('value' => '9', 'label' => 'Eventos Ativos'),
                    ),
                ),
                'featuresIntro' => 'Conheça em detalhes as principais funcionalidades do sistema:',
                'featuresDetails' => array(
                    'dashboard' => array(
                        'title' => 'Dashboard Principal',
                        'icon' => 'layout-dashboard',
                        'description' => 'O Dashboard é a central de controle do sistema, oferecendo uma visão geral personalizada de acordo com o tipo de usuário. Exibe estatísticas em tempo real, atalhos para as principais funcionalidades e notificações importantes.',
                        'capabilities' => array(
                            'Visão geral com estatísticas em tempo real',
                            'Cards de navegação rápida para módulos',
                            'Conteúdo personalizado por tipo de usuário',
                            'Exibição de eventos próximos',
                            'Cursos OpenLearning em destaque',
                            'Status de membership do usuário',
                        ),
                        'screenshots' => array(
                            array(
                                'file' => 'dashboard-01-main.png',
                                'title' => 'Dashboard SuperAdmin',
                                'description' => 'Visão completa do dashboard para administradores com todas as estatísticas e atalhos.',
                            ),
                        ),
                    ),
                    'members' => array(
                        'title' => 'Gestão de Membros',
                        'icon' => 'users',
                        'description' => 'Módulo completo para gerenciamento de todos os membros da organização. Permite visualizar, criar, editar e excluir membros, além de funcionalidades avançadas como exportação CSV e merge de duplicatas.',
                        'capabilities' => array(
                            'Listagem com busca e filtros avançados',
                            'Cards de estatísticas (Total, Ativos, Inativos, Pendentes)',
                            'Criação e edição de membros via modal',
                            'Visualização detalhada de informações',
                            'Exportação de dados em CSV',
                            'Deleção individual e em massa',
                            'Merge de registros duplicados',
                            'Sistema de tags para segmentação',
                        ),
                        'screenshots' => array(
                            array(
                                'file' => 'members-01-table.png',
                                'title' => 'Tabela de Membros',
                                'description' => 'Interface principal com tabela de membros, busca, filtros e ações.',
                            ),
                            array(
                                'file' => 'members-03-view-modal.png',
                                'title' => 'Visualização de Membro',
                                'description' => 'Modal com detalhes completos do membro selecionado.',
                            ),
                        ),
                    ),
                    'institutions' => array(
                        'title' => 'Gestão de Instituições',
                        'icon' => 'building-2',
                        'description' => 'Gerenciamento das instituições parceiras da English Australia. Permite cadastrar, editar e visualizar informações das instituições, além de gerenciar os vínculos com membros.',
                        'capabilities' => array(
                            'Cadastro completo de instituições',
                            'Informações de contato e localização',
                            'Vínculo com membros associados',
                            'Status ativo/inativo',
                            'Filtros por região e status',
                            'Exportação de dados',
                        ),
                        'screenshots' => array(
                            array(
                                'file' => 'institutions-01-table.png',
                                'title' => 'Tabela de Instituições',
                                'description' => 'Interface de gerenciamento com lista de todas as instituições parceiras.',
                            ),
                        ),
                    ),
                    'activities' => array(
                        'title' => 'Gestão de Atividades CPD',
                        'icon' => 'clipboard-check',
                        'description' => 'Central de gerenciamento das atividades de Desenvolvimento Profissional Contínuo (CPD). Permite visualizar, aprovar ou rejeitar atividades submetidas pelos membros.',
                        'capabilities' => array(
                            'Listagem de todas as atividades CPD',
                            'Filtros por status, categoria e período',
                            'Aprovação e rejeição de atividades',
                            'Visualização de evidências/comprovantes',
                            'Cálculo automático de pontos CPD',
                            'Exportação de relatórios',
                            'Limpeza de atividades órfãs',
                        ),
                        'screenshots' => array(
                            array(
                                'file' => 'activities-01-table.png',
                                'title' => 'Tabela de Atividades',
                                'description' => 'Interface de gestão com todas as atividades CPD e seus status.',
                            ),
                        ),
                    ),
                    'categories' => array(
                        'title' => 'Gestão de Categorias CPD',
                        'icon' => 'folder-tree',
                        'description' => 'Configuração das categorias de atividades CPD e definição de pontos por hora para cada tipo de atividade.',
                        'capabilities' => array(
                            'Criar e editar categorias de CPD',
                            'Definir pontos por hora para cada categoria',
                            'Visualizar estatísticas por categoria',
                            'Sincronizar categorias de atividades existentes',
                        ),
                        'screenshots' => array(
                            array(
                                'file' => 'categories-01-table.png',
                                'title' => 'Categorias CPD',
                                'description' => 'Interface de configuração das categorias de atividades CPD.',
                            ),
                        ),
                    ),
                    'events-admin' => array(
                        'title' => 'Gestão de Eventos',
                        'icon' => 'calendar-range',
                        'description' => 'Painel administrativo para criação e gestão de eventos. Permite configurar todos os aspectos do evento incluindo datas, local, preços e pontos CPD.',
                        'capabilities' => array(
                            'Criar eventos com múltiplas configurações',
                            'Definir local físico ou URL online',
                            'Configurar preços para membros e não-membros',
                            'Preços early-bird com data limite',
                            'Atribuir pontos CPD ao evento',
                            'Gerenciar inscrições por evento',
                            'Duplicar eventos existentes',
                            'Publicação imediata ou agendada',
                        ),
                        'screenshots' => array(
                            array(
                                'file' => 'events-admin-01-table.png',
                                'title' => 'Tabela de Eventos',
                                'description' => 'Interface administrativa com lista de todos os eventos e ações disponíveis.',
                            ),
                        ),
                    ),
                    'events-frontend' => array(
                        'title' => 'Eventos Públicos',
                        'icon' => 'calendar',
                        'description' => 'Página pública de eventos onde membros e visitantes podem descobrir eventos, ver detalhes e realizar inscrições.',
                        'capabilities' => array(
                            'Listagem de eventos com filtros',
                            'Cards visuais com informações do evento',
                            'Página de detalhes do evento',
                            'Inscrição online em eventos',
                            'Preços dinâmicos (early-bird)',
                            'Validação de capacidade',
                            'Restrição para apenas membros',
                        ),
                        'screenshots' => array(
                            array(
                                'file' => 'events-frontend-01-list.png',
                                'title' => 'Lista de Eventos',
                                'description' => 'Página pública com cards de eventos disponíveis para inscrição.',
                            ),
                        ),
                    ),
                    'payments' => array(
                        'title' => 'Gestão de Pagamentos',
                        'icon' => 'credit-card',
                        'description' => 'Módulo financeiro para gerenciamento unificado de pagamentos de eventos e memberships.',
                        'capabilities' => array(
                            'Visualização de todas as faturas',
                            'Filtros por status e tipo de pagamento',
                            'Registro manual de pagamentos',
                            'Rastreamento de status (Pago, Pendente, Falhou)',
                            'Múltiplos métodos de pagamento',
                            'Exportação de relatórios financeiros',
                        ),
                        'screenshots' => array(
                            array(
                                'file' => 'payments-01-table.png',
                                'title' => 'Tabela de Pagamentos',
                                'description' => 'Interface de gestão financeira com todas as faturas e pagamentos.',
                            ),
                        ),
                    ),
                    'membership-apps' => array(
                        'title' => 'Aplicações de Membership',
                        'icon' => 'user-check',
                        'description' => 'Gerenciamento de solicitações de membership. Permite visualizar, aprovar ou rejeitar aplicações de novos membros.',
                        'capabilities' => array(
                            'Lista de aplicações pendentes',
                            'Visualização detalhada da aplicação',
                            'Aprovação com um clique',
                            'Rejeição com motivo',
                            'Filtros por status e tipo',
                            'Histórico de aprovações',
                        ),
                        'screenshots' => array(
                            array(
                                'file' => 'membership-apps-01-table.png',
                                'title' => 'Aplicações de Membership',
                                'description' => 'Interface de gestão de solicitações de membership pendentes.',
                            ),
                        ),
                    ),
                    'membership-selection' => array(
                        'title' => 'Seleção de Membership',
                        'icon' => 'badge-check',
                        'description' => 'Página para novos usuários selecionarem e solicitarem um plano de membership. Processo em múltiplas etapas com formulário dinâmico.',
                        'capabilities' => array(
                            'Exibição de tipos de membership disponíveis',
                            'Formulário em múltiplas etapas',
                            'Campos dinâmicos por tipo de membership',
                            'Upload de documentos comprobatórios',
                            'Validação de dados em tempo real',
                            'Confirmação e submissão da aplicação',
                        ),
                        'screenshots' => array(
                            array(
                                'file' => 'membership-selection-01-types.png',
                                'title' => 'Seleção de Plano',
                                'description' => 'Página de seleção de tipo de membership com cards informativos.',
                            ),
                        ),
                    ),
                    'openlearning-courses' => array(
                        'title' => 'Cursos OpenLearning',
                        'icon' => 'graduation-cap',
                        'description' => 'Catálogo de cursos online integrado com a plataforma OpenLearning. Oferece acesso direto via SSO aos cursos disponíveis.',
                        'capabilities' => array(
                            'Catálogo de cursos disponíveis',
                            'SSO (Single Sign-On) integrado',
                            'Filtros por tipo de curso',
                            'Acesso direto à plataforma',
                            'Cursos em destaque',
                        ),
                        'screenshots' => array(
                            array(
                                'file' => 'openlearning-courses-01-main.png',
                                'title' => 'Catálogo de Cursos',
                                'description' => 'Interface com cursos OpenLearning disponíveis para membros.',
                            ),
                        ),
                    ),
                    'openlearning-mgmt' => array(
                        'title' => 'Gestão OpenLearning',
                        'icon' => 'book-open',
                        'description' => 'Painel administrativo para gerenciar a visibilidade e destaque dos cursos sincronizados da plataforma OpenLearning.',
                        'capabilities' => array(
                            'Sincronização com API OpenLearning',
                            'Controle de visibilidade dos cursos',
                            'Marcar cursos como destaque',
                            'Operações em massa',
                            'Estatísticas de cursos',
                        ),
                        'screenshots' => array(
                            array(
                                'file' => 'openlearning-mgmt-01-table.png',
                                'title' => 'Gestão de Cursos',
                                'description' => 'Interface administrativa para gerenciar cursos OpenLearning.',
                            ),
                        ),
                    ),
                    'my-profile' => array(
                        'title' => 'Meu Perfil',
                        'icon' => 'user',
                        'description' => 'Página de perfil pessoal onde o usuário pode visualizar e editar suas informações, foto e configurações de conta.',
                        'capabilities' => array(
                            'Visualização de dados pessoais',
                            'Edição de informações de perfil',
                            'Upload de foto de perfil',
                            'Alteração de senha segura',
                            'Informações de endereço',
                            'Status da conta',
                        ),
                        'screenshots' => array(
                            array(
                                'file' => 'my-profile-01-main.png',
                                'title' => 'Perfil Pessoal',
                                'description' => 'Interface de perfil com seções editáveis de informações pessoais.',
                            ),
                        ),
                    ),
                    'my-cpds' => array(
                        'title' => 'Minhas Atividades CPD',
                        'icon' => 'award',
                        'description' => 'Dashboard pessoal de atividades CPD onde o membro pode acompanhar seu progresso, registrar novas atividades e visualizar histórico.',
                        'capabilities' => array(
                            'Progress bar do ano atual (meta: 20 pontos)',
                            'Registro de novas atividades',
                            'Upload de evidências/comprovantes',
                            'Histórico de atividades por ano',
                            'Filtros por categoria e status',
                            'Visualização de pontos acumulados',
                        ),
                        'screenshots' => array(
                            array(
                                'file' => 'my-cpds-01-main.png',
                                'title' => 'Minhas CPDs',
                                'description' => 'Dashboard pessoal de atividades CPD com progresso e histórico.',
                            ),
                        ),
                    ),
                    'my-institution' => array(
                        'title' => 'Minha Instituição',
                        'icon' => 'building',
                        'description' => 'Página para visualização e gestão do vínculo institucional. Administradores de instituição podem gerenciar membros e requisições.',
                        'capabilities' => array(
                            'Visualização da instituição vinculada',
                            'Lista de membros da instituição (Institution Admin)',
                            'Gerenciamento de requisições de vínculo',
                            'Aprovação/rejeição de novos membros',
                            'Solicitar vínculo com outra instituição',
                        ),
                        'screenshots' => array(
                            array(
                                'file' => 'my-institution-01-main.png',
                                'title' => 'Minha Instituição',
                                'description' => 'Interface de gestão institucional com membros e requisições.',
                            ),
                        ),
                    ),
                    'duplicates' => array(
                        'title' => 'Gestão de Duplicatas',
                        'icon' => 'copy',
                        'description' => 'Ferramenta para identificação e merge de registros duplicados de membros. Usa algoritmos de matching para encontrar possíveis duplicatas.',
                        'capabilities' => array(
                            'Scan automático de duplicatas',
                            'Score de matching (Alto, Médio, Baixo)',
                            'Preview antes do merge',
                            'Merge seguro com consolidação de dados',
                            'Log de operações de merge',
                            'Resolução de conflitos de campos',
                        ),
                        'screenshots' => array(
                            array(
                                'file' => 'duplicates-01-table.png',
                                'title' => 'Gestão de Duplicatas',
                                'description' => 'Interface de identificação e merge de registros duplicados.',
                            ),
                        ),
                    ),
                    'settings' => array(
                        'title' => 'Configurações do Sistema',
                        'icon' => 'settings',
                        'description' => 'Painel de configurações gerais do sistema incluindo modo de aprovação de CPD, gestão de tags e integrações.',
                        'capabilities' => array(
                            'Modo de aprovação de CPD (Auto/Manual)',
                            'Gestão de tags de membros',
                            'Criação de tags com cores personalizadas',
                            'Configurações de email',
                            'Configurações de integrações',
                        ),
                        'screenshots' => array(
                            array(
                                'file' => 'settings-01-main.png',
                                'title' => 'Configurações',
                                'description' => 'Painel de configurações do sistema com diferentes seções.',
                            ),
                        ),
                    ),
                    'registration' => array(
                        'title' => 'Registro Público',
                        'icon' => 'user-plus',
                        'description' => 'Formulário de registro para novos usuários em 3 etapas: informações pessoais, profissionais e configuração de conta.',
                        'capabilities' => array(
                            'Formulário em múltiplas etapas',
                            'Validação em tempo real',
                            'Upload de foto de perfil',
                            'Busca dinâmica de empresa',
                            'reCAPTCHA integrado',
                            'Seleção de newsletters',
                            'Login automático após registro',
                        ),
                        'screenshots' => array(
                            array(
                                'file' => 'registration-01-form.png',
                                'title' => 'Formulário de Registro',
                                'description' => 'Página pública de registro com formulário em etapas.',
                            ),
                        ),
                    ),
                    'tags' => array(
                        'title' => 'Sistema de Tags',
                        'icon' => 'tags',
                        'description' => 'Sistema de tags para categorizar e segmentar membros de forma flexível, facilitando a organização, busca e comunicação direcionada.',
                        'capabilities' => array(
                            'Criar tags personalizadas com cores distintas',
                            'Aplicar múltiplas tags por membro',
                            'Quick Tags: gerenciar tags diretamente na tabela',
                            'Bulk Tags: adicionar ou remover tags em massa',
                            'Filtrar membros por tag nas listagens',
                            'Remover todas as tags de uma vez',
                        ),
                        'screenshots' => array(
                            array(
                                'file' => 'tags-01-members-table.png',
                                'title' => 'Tabela de Membros',
                                'description' => 'Visualização das tags na tabela com botão Quick Tags.',
                            ),
                            array(
                                'file' => 'tags-02-quick-tags-popover.png',
                                'title' => 'Quick Tags Popover',
                                'description' => 'Popover para gerenciar tags de um membro individual.',
                            ),
                            array(
                                'file' => 'tags-04-bulk-modal-add.png',
                                'title' => 'Bulk Tags',
                                'description' => 'Modal para adicionar ou remover tags em massa.',
                            ),
                        ),
                    ),
                ),
                'userTypesIntro' => 'O sistema implementa uma hierarquia de permissões com 4 níveis de acesso distintos:',
                'userTypesTable' => array(
                    array('Tipo de Usuário', 'Descrição', 'Principais Responsabilidades'),
                    array('Super Admin', 'Administrador master com acesso irrestrito', 'Configurações do sistema, gestão completa, deleção em massa'),
                    array('Admin', 'Administrador da English Australia', 'Gestão de membros, instituições, eventos e pagamentos'),
                    array('Institution Admin', 'Administrador de instituição parceira', 'Gestão dos membros vinculados à sua instituição'),
                    array('Member', 'Membro individual da organização', 'Acesso ao próprio perfil, CPDs e cursos'),
                ),
                'permissionsIntro' => 'Resumo completo das funcionalidades disponíveis para cada tipo de usuário:',
                'permissionsTable' => array(
                    array('Funcionalidade', 'Super Admin', 'Admin', 'Inst. Admin', 'Member'),
                    array('Dashboard Administrativo', '✓', '✓', '—', '—'),
                    array('Gestão de Membros', '✓', '✓', '—', '—'),
                    array('Gestão de Instituições', '✓', '✓', '—', '—'),
                    array('Gestão de Atividades CPD', '✓', '✓', '—', '—'),
                    array('Gestão de Categorias CPD', '✓', '—', '—', '—'),
                    array('Gestão de Eventos', '✓', '✓', '—', '—'),
                    array('Gestão de Pagamentos', '✓', '✓', '—', '—'),
                    array('Aprovação de Membership', '✓', '✓', '—', '—'),
                    array('Minha Instituição', '—', '—', '✓', '—'),
                    array('Perfil Pessoal', '✓', '✓', '✓', '✓'),
                    array('Minhas Atividades CPD', '✓', '✓', '✓', '✓'),
                    array('Eventos Públicos', '✓', '✓', '✓', '✓'),
                    array('Cursos OpenLearning', '✓', '✓', '✓', '✓'),
                    array('Deleção em Massa', '✓', '—', '—', '—'),
                ),
                'pendingIntro' => 'Os itens abaixo representam as funcionalidades que ainda serão desenvolvidas:',
                'accessIntro' => 'Utilize as credenciais abaixo para acessar o sistema e testar as funcionalidades:',
                'accessLabels' => array(
                    'url' => 'URL do Sistema',
                    'email' => 'Email',
                    'password' => 'Senha',
                ),
                'accessNote' => 'Este acesso é exclusivo para testes. Por favor, não compartilhe estas credenciais.',
                'users' => array(
                    'superAdmin' => array(
                        'title' => 'Super Admin (Administrador Master)',
                        'description' => 'O Super Admin possui acesso irrestrito a todas as funcionalidades do sistema. Este perfil é destinado aos administradores técnicos e gestores principais da English Australia.',
                        'capabilities' => array(
                            'Acesso completo a todos os módulos',
                            'Gestão de todos os tipos de usuários',
                            'Configurações avançadas e deleção em massa',
                            'Visualização de estatísticas globais',
                            'Merge de registros duplicados',
                        ),
                        'pages' => array(
                            '01-dashboard' => array('title' => 'Dashboard Principal', 'description' => 'Visão geral completa do sistema com estatísticas em tempo real, cards de navegação rápida e QR Code para compartilhamento.'),
                            '02-members' => array('title' => 'Members Management', 'description' => 'Gestão completa de membros com busca avançada, filtros múltiplos, exportação CSV e merge de duplicatas.'),
                            '03-institutions' => array('title' => 'Institutions Management', 'description' => 'Gestão das 128 instituições parceiras com informações de contato e vínculos de membros.'),
                            '04-activities' => array('title' => 'CPD Activities Management', 'description' => 'Central de gestão das 16.333 atividades CPD com aprovação e relatórios.'),
                            '05-categories' => array('title' => 'CPD Categories Management', 'description' => 'Configuração das categorias de atividades CPD e pontuação por tipo.'),
                            '06-events-admin' => array('title' => 'Events Management', 'description' => 'Painel administrativo de eventos com criação, inscrições e controle de pagamentos.'),
                            '07-payments' => array('title' => 'Payments Management', 'description' => 'Módulo financeiro com faturas, status de pagamento e relatórios.'),
                            '08-membership-applications' => array('title' => 'Membership Applications', 'description' => 'Gestão de solicitações de membership com aprovação e histórico.'),
                            '09-my-profile' => array('title' => 'My Profile', 'description' => 'Perfil pessoal com dados, foto e configurações de conta.'),
                            '10-my-cpds' => array('title' => 'My CPDs', 'description' => 'Registro pessoal de atividades CPD com histórico e pontuação.'),
                            '11-events-frontend' => array('title' => 'Events (Público)', 'description' => 'Página pública de eventos com cards, filtros e inscrição.'),
                            '12-courses' => array('title' => 'OpenLearning Courses', 'description' => 'Catálogo de 55 cursos online com acesso direto à plataforma.'),
                        ),
                    ),
                    'Admin' => array(
                        'title' => 'Admin (Administrador)',
                        'description' => 'O perfil Admin é destinado aos funcionários da English Australia responsáveis pela gestão operacional do dia a dia.',
                        'capabilities' => array(
                            'Gestão completa de membros e instituições',
                            'Aprovação de atividades CPD e memberships',
                            'Gestão de eventos e pagamentos',
                            'Exportação de relatórios',
                        ),
                        'pages' => array(
                            '01-dashboard' => array('title' => 'Dashboard Principal', 'description' => 'Dashboard similar ao Super Admin com estatísticas gerais e navegação rápida.'),
                            '02-members' => array('title' => 'Members Management', 'description' => 'Gestão de membros sem o botão "Delete All Filtered".'),
                            '03-institutions' => array('title' => 'Institutions Management', 'description' => 'Gestão completa das instituições parceiras.'),
                            '04-activities' => array('title' => 'CPD Activities Management', 'description' => 'Gestão e aprovação de atividades CPD.'),
                            '05-events-admin' => array('title' => 'Events Management', 'description' => 'Criação e gestão de eventos.'),
                            '06-payments' => array('title' => 'Payments Management', 'description' => 'Visualização e gestão de pagamentos.'),
                            '07-membership-applications' => array('title' => 'Membership Applications', 'description' => 'Aprovação de solicitações de membership.'),
                            '08-my-profile' => array('title' => 'My Profile', 'description' => 'Perfil pessoal do administrador.'),
                            '09-events-frontend' => array('title' => 'Events (Público)', 'description' => 'Visualização da página pública de eventos.'),
                        ),
                    ),
                    'institutionAdmin' => array(
                        'title' => 'Institution Admin (Admin de Instituição)',
                        'description' => 'Perfil destinado aos representantes das instituições parceiras da English Australia. Acesso limitado à sua instituição.',
                        'capabilities' => array(
                            'Visualização e gestão dos membros da sua instituição',
                            'Edição das informações institucionais',
                            'Aprovação de novos membros para a instituição',
                            'Acesso pessoal a CPDs e cursos',
                        ),
                        'pages' => array(
                            '01-dashboard' => array('title' => 'Dashboard Principal', 'description' => 'Dashboard personalizado com solicitações de membros e eventos.'),
                            '02-my-institution' => array('title' => 'My Institution', 'description' => 'Gestão da instituição com lista de membros e aprovações.'),
                            '03-my-profile' => array('title' => 'My Profile', 'description' => 'Perfil pessoal do administrador.'),
                            '04-my-cpds' => array('title' => 'My CPDs', 'description' => 'Registro pessoal de atividades CPD.'),
                            '05-events-frontend' => array('title' => 'Events', 'description' => 'Visualização e inscrição em eventos.'),
                            '06-courses' => array('title' => 'Courses', 'description' => 'Acesso aos cursos OpenLearning.'),
                        ),
                    ),
                    'Member' => array(
                        'title' => 'Member (Membro)',
                        'description' => 'Perfil básico para membros individuais da English Australia. Acesso limitado às funcionalidades pessoais.',
                        'capabilities' => array(
                            'Gerenciamento do perfil pessoal',
                            'Registro e acompanhamento de atividades CPD',
                            'Inscrição em eventos',
                            'Acesso a cursos online',
                            'Solicitação de membership',
                        ),
                        'pages' => array(
                            '01-dashboard' => array('title' => 'Dashboard Principal', 'description' => 'Dashboard simplificado com eventos, perfil e cursos.'),
                            '02-my-profile' => array('title' => 'My Profile', 'description' => 'Perfil pessoal completo com dados e foto.'),
                            '03-my-cpds' => array('title' => 'My CPDs', 'description' => 'Central de gestão de atividades CPD pessoais.'),
                            '04-membership-selection' => array('title' => 'Membership Selection', 'description' => 'Seleção e aquisição de planos de membership.'),
                            '05-events-frontend' => array('title' => 'Events', 'description' => 'Descoberta e inscrição em eventos.'),
                            '06-courses' => array('title' => 'Courses', 'description' => 'Catálogo de cursos OpenLearning.'),
                        ),
                    ),
                ),
            ),
            'en' => array(
                'cover' => array(
                    'organization' => 'ENGLISH AUSTRALIA',
                    'title' => 'Member Management System',
                    'subtitle' => 'Complete System Presentation',
                ),
                'toc' => array(
                    'title' => 'Quick Navigation',
                    'items' => array(
                        'overview' => 'Overview',
                        'features' => 'Features',
                        'user-types' => 'User Types',
                        'super-admin' => 'Super Admin',
                        'admin' => 'Admin',
                        'institution-admin' => 'Institution Admin',
                        'member' => 'Member',
                        'permissions' => 'Permissions',
                        'pending' => 'Next Steps',
                        'access' => 'Test Access',
                    ),
                ),
                'sections' => array(
                    'overview' => 'System Overview',
                    'featuresDetails' => 'Detailed Features',
                    'userTypes' => 'User Types',
                    'permissions' => 'Permissions Matrix',
                    'pendingItems' => 'Next Steps',
                    'clientAccess' => 'Test Access',
                ),
                'overview' => array(
                    'intro' => 'The Eau System is a comprehensive member management platform developed specifically for English Australia. The system centralizes all operations related to members, partner institutions, events, and Continuous Professional Development (CPD).',
                    'features' => array(
                        'Complete management of individual and institutional members',
                        'CPD (Continuous Professional Development) activity tracking system',
                        'Event platform with online registrations and payments',
                        'Membership system with different levels and benefits',
                        'Native integration with OpenLearning for online courses',
                        'Financial module with payment and invoice management',
                        'Customized dashboard by user type',
                        'Reports and CSV data export',
                        'Tag system for member segmentation with bulk management',
                    ),
                    'stats' => array(
                        array('value' => '6,188', 'label' => 'Registered Members'),
                        array('value' => '128', 'label' => 'Partner Institutions'),
                        array('value' => '16,333', 'label' => 'CPD Activities'),
                        array('value' => '55', 'label' => 'OpenLearning Courses'),
                        array('value' => '9', 'label' => 'Active Events'),
                    ),
                ),
                'featuresIntro' => 'Learn about the main system features in detail:',
                'featuresDetails' => array(
                    'dashboard' => array(
                        'title' => 'Main Dashboard',
                        'icon' => 'layout-dashboard',
                        'description' => 'The Dashboard is the system\'s control center, offering a personalized overview based on user type. Displays real-time statistics, shortcuts to main features, and important notifications.',
                        'capabilities' => array(
                            'Overview with real-time statistics',
                            'Quick navigation cards to modules',
                            'Personalized content by user type',
                            'Display of upcoming events',
                            'Featured OpenLearning courses',
                            'User membership status',
                        ),
                        'screenshots' => array(
                            array(
                                'file' => 'dashboard-01-main.png',
                                'title' => 'SuperAdmin Dashboard',
                                'description' => 'Complete dashboard view for administrators with all statistics and shortcuts.',
                            ),
                        ),
                    ),
                    'members' => array(
                        'title' => 'Members Management',
                        'icon' => 'users',
                        'description' => 'Complete module for managing all organization members. Allows viewing, creating, editing, and deleting members, plus advanced features like CSV export and duplicate merging.',
                        'capabilities' => array(
                            'Listing with search and advanced filters',
                            'Statistics cards (Total, Active, Inactive, Pending)',
                            'Member creation and editing via modal',
                            'Detailed information viewing',
                            'CSV data export',
                            'Individual and bulk deletion',
                            'Duplicate record merging',
                            'Tag system for segmentation',
                        ),
                        'screenshots' => array(
                            array(
                                'file' => 'members-01-table.png',
                                'title' => 'Members Table',
                                'description' => 'Main interface with members table, search, filters, and actions.',
                            ),
                            array(
                                'file' => 'members-03-view-modal.png',
                                'title' => 'Member View',
                                'description' => 'Modal with complete details of the selected member.',
                            ),
                        ),
                    ),
                    'institutions' => array(
                        'title' => 'Institutions Management',
                        'icon' => 'building-2',
                        'description' => 'Management of English Australia partner institutions. Allows registering, editing, and viewing institution information, as well as managing member links.',
                        'capabilities' => array(
                            'Complete institution registration',
                            'Contact and location information',
                            'Link with associated members',
                            'Active/inactive status',
                            'Filters by region and status',
                            'Data export',
                        ),
                        'screenshots' => array(
                            array(
                                'file' => 'institutions-01-table.png',
                                'title' => 'Institutions Table',
                                'description' => 'Management interface with list of all partner institutions.',
                            ),
                        ),
                    ),
                    'activities' => array(
                        'title' => 'CPD Activities Management',
                        'icon' => 'clipboard-check',
                        'description' => 'Central management of Continuing Professional Development (CPD) activities. Allows viewing, approving, or rejecting activities submitted by members.',
                        'capabilities' => array(
                            'Listing of all CPD activities',
                            'Filters by status, category, and period',
                            'Activity approval and rejection',
                            'Evidence/proof viewing',
                            'Automatic CPD points calculation',
                            'Report export',
                            'Orphan activities cleanup',
                        ),
                        'screenshots' => array(
                            array(
                                'file' => 'activities-01-table.png',
                                'title' => 'Activities Table',
                                'description' => 'Management interface with all CPD activities and their status.',
                            ),
                        ),
                    ),
                    'categories' => array(
                        'title' => 'CPD Categories Management',
                        'icon' => 'folder-tree',
                        'description' => 'Configuration of CPD activity categories and definition of points per hour for each activity type.',
                        'capabilities' => array(
                            'Create and edit CPD categories',
                            'Define points per hour for each category',
                            'View statistics by category',
                            'Sync categories from existing activities',
                        ),
                        'screenshots' => array(
                            array(
                                'file' => 'categories-01-table.png',
                                'title' => 'CPD Categories',
                                'description' => 'Configuration interface for CPD activity categories.',
                            ),
                        ),
                    ),
                    'events-admin' => array(
                        'title' => 'Events Management',
                        'icon' => 'calendar-range',
                        'description' => 'Administrative panel for creating and managing events. Allows configuring all event aspects including dates, location, prices, and CPD points.',
                        'capabilities' => array(
                            'Create events with multiple configurations',
                            'Set physical location or online URL',
                            'Configure prices for members and non-members',
                            'Early-bird prices with deadline',
                            'Assign CPD points to event',
                            'Manage registrations per event',
                            'Duplicate existing events',
                            'Immediate or scheduled publishing',
                        ),
                        'screenshots' => array(
                            array(
                                'file' => 'events-admin-01-table.png',
                                'title' => 'Events Table',
                                'description' => 'Administrative interface with list of all events and available actions.',
                            ),
                        ),
                    ),
                    'events-frontend' => array(
                        'title' => 'Public Events',
                        'icon' => 'calendar',
                        'description' => 'Public events page where members and visitors can discover events, view details, and register.',
                        'capabilities' => array(
                            'Event listing with filters',
                            'Visual cards with event information',
                            'Event details page',
                            'Online event registration',
                            'Dynamic pricing (early-bird)',
                            'Capacity validation',
                            'Members-only restriction',
                        ),
                        'screenshots' => array(
                            array(
                                'file' => 'events-frontend-01-list.png',
                                'title' => 'Events List',
                                'description' => 'Public page with event cards available for registration.',
                            ),
                        ),
                    ),
                    'payments' => array(
                        'title' => 'Payments Management',
                        'icon' => 'credit-card',
                        'description' => 'Financial module for unified management of event and membership payments.',
                        'capabilities' => array(
                            'View all invoices',
                            'Filters by status and payment type',
                            'Manual payment recording',
                            'Status tracking (Paid, Pending, Failed)',
                            'Multiple payment methods',
                            'Financial report export',
                        ),
                        'screenshots' => array(
                            array(
                                'file' => 'payments-01-table.png',
                                'title' => 'Payments Table',
                                'description' => 'Financial management interface with all invoices and payments.',
                            ),
                        ),
                    ),
                    'membership-apps' => array(
                        'title' => 'Membership Applications',
                        'icon' => 'user-check',
                        'description' => 'Membership request management. Allows viewing, approving, or rejecting applications from new members.',
                        'capabilities' => array(
                            'Pending applications list',
                            'Detailed application view',
                            'One-click approval',
                            'Rejection with reason',
                            'Filters by status and type',
                            'Approval history',
                        ),
                        'screenshots' => array(
                            array(
                                'file' => 'membership-apps-01-table.png',
                                'title' => 'Membership Applications',
                                'description' => 'Management interface for pending membership requests.',
                            ),
                        ),
                    ),
                    'membership-selection' => array(
                        'title' => 'Membership Selection',
                        'icon' => 'badge-check',
                        'description' => 'Page for new users to select and apply for a membership plan. Multi-step process with dynamic form.',
                        'capabilities' => array(
                            'Display of available membership types',
                            'Multi-step form',
                            'Dynamic fields by membership type',
                            'Supporting document upload',
                            'Real-time data validation',
                            'Application confirmation and submission',
                        ),
                        'screenshots' => array(
                            array(
                                'file' => 'membership-selection-01-types.png',
                                'title' => 'Plan Selection',
                                'description' => 'Membership type selection page with informative cards.',
                            ),
                        ),
                    ),
                    'openlearning-courses' => array(
                        'title' => 'OpenLearning Courses',
                        'icon' => 'graduation-cap',
                        'description' => 'Online course catalog integrated with the OpenLearning platform. Offers direct access via SSO to available courses.',
                        'capabilities' => array(
                            'Available courses catalog',
                            'Integrated SSO (Single Sign-On)',
                            'Filters by course type',
                            'Direct platform access',
                            'Featured courses',
                        ),
                        'screenshots' => array(
                            array(
                                'file' => 'openlearning-courses-01-main.png',
                                'title' => 'Course Catalog',
                                'description' => 'Interface with OpenLearning courses available for members.',
                            ),
                        ),
                    ),
                    'openlearning-mgmt' => array(
                        'title' => 'OpenLearning Management',
                        'icon' => 'book-open',
                        'description' => 'Administrative panel for managing visibility and featuring of courses synced from the OpenLearning platform.',
                        'capabilities' => array(
                            'Sync with OpenLearning API',
                            'Course visibility control',
                            'Mark courses as featured',
                            'Bulk operations',
                            'Course statistics',
                        ),
                        'screenshots' => array(
                            array(
                                'file' => 'openlearning-mgmt-01-table.png',
                                'title' => 'Course Management',
                                'description' => 'Administrative interface for managing OpenLearning courses.',
                            ),
                        ),
                    ),
                    'my-profile' => array(
                        'title' => 'My Profile',
                        'icon' => 'user',
                        'description' => 'Personal profile page where users can view and edit their information, photo, and account settings.',
                        'capabilities' => array(
                            'Personal data viewing',
                            'Profile information editing',
                            'Profile photo upload',
                            'Secure password change',
                            'Address information',
                            'Account status',
                        ),
                        'screenshots' => array(
                            array(
                                'file' => 'my-profile-01-main.png',
                                'title' => 'Personal Profile',
                                'description' => 'Profile interface with editable personal information sections.',
                            ),
                        ),
                    ),
                    'my-cpds' => array(
                        'title' => 'My CPD Activities',
                        'icon' => 'award',
                        'description' => 'Personal CPD activities dashboard where members can track progress, register new activities, and view history.',
                        'capabilities' => array(
                            'Current year progress bar (goal: 20 points)',
                            'New activity registration',
                            'Evidence/proof upload',
                            'Activity history by year',
                            'Filters by category and status',
                            'Accumulated points view',
                        ),
                        'screenshots' => array(
                            array(
                                'file' => 'my-cpds-01-main.png',
                                'title' => 'My CPDs',
                                'description' => 'Personal CPD activities dashboard with progress and history.',
                            ),
                        ),
                    ),
                    'my-institution' => array(
                        'title' => 'My Institution',
                        'icon' => 'building',
                        'description' => 'Page for viewing and managing institutional affiliation. Institution administrators can manage members and requests.',
                        'capabilities' => array(
                            'View affiliated institution',
                            'Institution member list (Institution Admin)',
                            'Affiliation request management',
                            'Approval/rejection of new members',
                            'Request affiliation with another institution',
                        ),
                        'screenshots' => array(
                            array(
                                'file' => 'my-institution-01-main.png',
                                'title' => 'My Institution',
                                'description' => 'Institutional management interface with members and requests.',
                            ),
                        ),
                    ),
                    'duplicates' => array(
                        'title' => 'Duplicate Management',
                        'icon' => 'copy',
                        'description' => 'Tool for identifying and merging duplicate member records. Uses matching algorithms to find potential duplicates.',
                        'capabilities' => array(
                            'Automatic duplicate scanning',
                            'Matching score (High, Medium, Low)',
                            'Preview before merge',
                            'Safe merge with data consolidation',
                            'Merge operations log',
                            'Field conflict resolution',
                        ),
                        'screenshots' => array(
                            array(
                                'file' => 'duplicates-01-table.png',
                                'title' => 'Duplicate Management',
                                'description' => 'Interface for identifying and merging duplicate records.',
                            ),
                        ),
                    ),
                    'settings' => array(
                        'title' => 'System Settings',
                        'icon' => 'settings',
                        'description' => 'General system settings panel including CPD approval mode, tag management, and integrations.',
                        'capabilities' => array(
                            'CPD approval mode (Auto/Manual)',
                            'Member tag management',
                            'Tag creation with custom colors',
                            'Email settings',
                            'Integration settings',
                        ),
                        'screenshots' => array(
                            array(
                                'file' => 'settings-01-main.png',
                                'title' => 'Settings',
                                'description' => 'System settings panel with different sections.',
                            ),
                        ),
                    ),
                    'registration' => array(
                        'title' => 'Public Registration',
                        'icon' => 'user-plus',
                        'description' => 'Registration form for new users in 3 steps: personal information, professional information, and account setup.',
                        'capabilities' => array(
                            'Multi-step form',
                            'Real-time validation',
                            'Profile photo upload',
                            'Dynamic company search',
                            'Integrated reCAPTCHA',
                            'Newsletter selection',
                            'Auto-login after registration',
                        ),
                        'screenshots' => array(
                            array(
                                'file' => 'registration-01-form.png',
                                'title' => 'Registration Form',
                                'description' => 'Public registration page with multi-step form.',
                            ),
                        ),
                    ),
                    'tags' => array(
                        'title' => 'Tag System',
                        'icon' => 'tags',
                        'description' => 'Tag system for flexible member categorization and segmentation, facilitating organization, search, and targeted communication.',
                        'capabilities' => array(
                            'Create custom tags with distinct colors',
                            'Apply multiple tags per member',
                            'Quick Tags: manage tags directly in the table',
                            'Bulk Tags: add or remove tags in bulk',
                            'Filter members by tag in listings',
                            'Remove all tags at once',
                        ),
                        'screenshots' => array(
                            array(
                                'file' => 'tags-01-members-table.png',
                                'title' => 'Members Table',
                                'description' => 'View tags in table with Quick Tags button.',
                            ),
                            array(
                                'file' => 'tags-02-quick-tags-popover.png',
                                'title' => 'Quick Tags Popover',
                                'description' => 'Popover for managing individual member tags.',
                            ),
                            array(
                                'file' => 'tags-04-bulk-modal-add.png',
                                'title' => 'Bulk Tags',
                                'description' => 'Modal for adding or removing tags in bulk.',
                            ),
                        ),
                    ),
                ),
                'userTypesIntro' => 'The system implements a permission hierarchy with 4 distinct access levels:',
                'userTypesTable' => array(
                    array('User Type', 'Description', 'Main Responsibilities'),
                    array('Super Admin', 'Master administrator with unrestricted access', 'System settings, complete management, bulk deletion'),
                    array('Admin', 'English Australia administrator', 'Member, institution, event and payment management'),
                    array('Institution Admin', 'Partner institution administrator', 'Management of members linked to their institution'),
                    array('Member', 'Individual organization member', 'Access to own profile, CPDs and courses'),
                ),
                'permissionsIntro' => 'Complete summary of functionalities available for each user type:',
                'permissionsTable' => array(
                    array('Functionality', 'Super Admin', 'Admin', 'Inst. Admin', 'Member'),
                    array('Administrative Dashboard', '✓', '✓', '—', '—'),
                    array('Members Management', '✓', '✓', '—', '—'),
                    array('Institutions Management', '✓', '✓', '—', '—'),
                    array('CPD Activities Management', '✓', '✓', '—', '—'),
                    array('CPD Categories Management', '✓', '—', '—', '—'),
                    array('Events Management', '✓', '✓', '—', '—'),
                    array('Payments Management', '✓', '✓', '—', '—'),
                    array('Membership Approval', '✓', '✓', '—', '—'),
                    array('My Institution', '—', '—', '✓', '—'),
                    array('Personal Profile', '✓', '✓', '✓', '✓'),
                    array('My CPD Activities', '✓', '✓', '✓', '✓'),
                    array('Public Events', '✓', '✓', '✓', '✓'),
                    array('OpenLearning Courses', '✓', '✓', '✓', '✓'),
                    array('Bulk Deletion', '✓', '—', '—', '—'),
                ),
                'pendingIntro' => 'The items below represent features that will be developed in upcoming phases:',
                'accessIntro' => 'Use the credentials below to access the system and test the functionalities:',
                'accessLabels' => array(
                    'url' => 'System URL',
                    'email' => 'Email',
                    'password' => 'Password',
                ),
                'accessNote' => 'This access is for testing purposes only. Please do not share these credentials.',
                'users' => array(
                    'superAdmin' => array(
                        'title' => 'Super Admin (Master Administrator)',
                        'description' => 'The Super Admin has unrestricted access to all system functionalities. This profile is for technical administrators and main managers.',
                        'capabilities' => array(
                            'Complete access to all modules',
                            'Management of all user types',
                            'Advanced settings and bulk deletion',
                            'View global statistics',
                            'Merge duplicate records',
                        ),
                        'pages' => array(
                            '01-dashboard' => array('title' => 'Main Dashboard', 'description' => 'Complete system overview with real-time statistics, quick navigation cards and QR Code for sharing.'),
                            '02-members' => array('title' => 'Members Management', 'description' => 'Complete member management with advanced search, multiple filters, CSV export and duplicate merge.'),
                            '03-institutions' => array('title' => 'Institutions Management', 'description' => 'Management of 128 partner institutions with contact info and member links.'),
                            '04-activities' => array('title' => 'CPD Activities Management', 'description' => 'Central management of 16,333 CPD activities with approval and reports.'),
                            '05-categories' => array('title' => 'CPD Categories Management', 'description' => 'Configuration of CPD activity categories and scoring by type.'),
                            '06-events-admin' => array('title' => 'Events Management', 'description' => 'Administrative panel for events with creation, registrations and payment control.'),
                            '07-payments' => array('title' => 'Payments Management', 'description' => 'Financial module with invoices, payment status and reports.'),
                            '08-membership-applications' => array('title' => 'Membership Applications', 'description' => 'Management of membership requests with approval and history.'),
                            '09-my-profile' => array('title' => 'My Profile', 'description' => 'Personal profile with data, photo and account settings.'),
                            '10-my-cpds' => array('title' => 'My CPDs', 'description' => 'Personal CPD activity record with history and scoring.'),
                            '11-events-frontend' => array('title' => 'Events (Public)', 'description' => 'Public events page with cards, filters and registration.'),
                            '12-courses' => array('title' => 'OpenLearning Courses', 'description' => 'Catalog of 55 online courses with direct platform access.'),
                        ),
                    ),
                    'Admin' => array(
                        'title' => 'Admin (Administrator)',
                        'description' => 'The Admin profile is for English Australia staff responsible for day-to-day operational management.',
                        'capabilities' => array(
                            'Complete management of members and institutions',
                            'Approval of CPD activities and memberships',
                            'Event and payment management',
                            'Report export',
                        ),
                        'pages' => array(
                            '01-dashboard' => array('title' => 'Main Dashboard', 'description' => 'Dashboard similar to Super Admin with general statistics and quick navigation.'),
                            '02-members' => array('title' => 'Members Management', 'description' => 'Member management without "Delete All Filtered" button.'),
                            '03-institutions' => array('title' => 'Institutions Management', 'description' => 'Complete partner institution management.'),
                            '04-activities' => array('title' => 'CPD Activities Management', 'description' => 'Management and approval of CPD activities.'),
                            '05-events-admin' => array('title' => 'Events Management', 'description' => 'Event creation and management.'),
                            '06-payments' => array('title' => 'Payments Management', 'description' => 'Payment viewing and management.'),
                            '07-membership-applications' => array('title' => 'Membership Applications', 'description' => 'Approval of membership requests.'),
                            '08-my-profile' => array('title' => 'My Profile', 'description' => 'Administrator personal profile.'),
                            '09-events-frontend' => array('title' => 'Events (Public)', 'description' => 'Public events page view.'),
                        ),
                    ),
                    'institutionAdmin' => array(
                        'title' => 'Institution Admin',
                        'description' => 'Profile for English Australia partner institution representatives. Access limited to their institution.',
                        'capabilities' => array(
                            'View and manage their institution\'s members',
                            'Edit institutional information',
                            'Approve new members for the institution',
                            'Personal CPD and course access',
                        ),
                        'pages' => array(
                            '01-dashboard' => array('title' => 'Main Dashboard', 'description' => 'Customized dashboard with member requests and events.'),
                            '02-my-institution' => array('title' => 'My Institution', 'description' => 'Institution management with member list and approvals.'),
                            '03-my-profile' => array('title' => 'My Profile', 'description' => 'Administrator personal profile.'),
                            '04-my-cpds' => array('title' => 'My CPDs', 'description' => 'Personal CPD activity record.'),
                            '05-events-frontend' => array('title' => 'Events', 'description' => 'Event viewing and registration.'),
                            '06-courses' => array('title' => 'Courses', 'description' => 'OpenLearning course access.'),
                        ),
                    ),
                    'Member' => array(
                        'title' => 'Member',
                        'description' => 'Basic profile for individual English Australia members. Access limited to personal functionalities.',
                        'capabilities' => array(
                            'Personal profile management',
                            'CPD activity registration and tracking',
                            'Event registration',
                            'Online course access',
                            'Membership application',
                        ),
                        'pages' => array(
                            '01-dashboard' => array('title' => 'Main Dashboard', 'description' => 'Simplified dashboard with events, profile and courses.'),
                            '02-my-profile' => array('title' => 'My Profile', 'description' => 'Complete personal profile with data and photo.'),
                            '03-my-cpds' => array('title' => 'My CPDs', 'description' => 'Personal CPD activity management center.'),
                            '04-membership-selection' => array('title' => 'Membership Selection', 'description' => 'Selection and purchase of membership plans.'),
                            '05-events-frontend' => array('title' => 'Events', 'description' => 'Event discovery and registration.'),
                            '06-courses' => array('title' => 'Courses', 'description' => 'OpenLearning course catalog.'),
                        ),
                    ),
                ),
            ),
        );
    }

    /**
     * Get pending items
     */
    private static function get_pending_items() {
        return array(
            array(
                'pt' => 'Correção na visualização de comprovantes de atividades importadas do sistema antigo',
                'en' => 'Fix for viewing activity evidence imported from the legacy system',
            ),
            array(
                'pt' => 'Ajuste no controle de acesso do botão "Merge Duplicates" para administradores de instituição',
                'en' => 'Access control adjustment for "Merge Duplicates" button for institution administrators',
            ),
            array(
                'pt' => 'Correção na funcionalidade de recuperação de senha',
                'en' => 'Fix for password recovery functionality',
            ),
            array(
                'pt' => 'Correção no botão de exportação de atividades CPD',
                'en' => 'Fix for CPD activities export button',
            ),
            array(
                'pt' => 'Importação completa dos dados do sistema antigo (pagamentos, memberships, comprovantes e datas de expiração)',
                'en' => 'Complete data import from legacy system (payments, memberships, receipts, and expiration dates)',
            ),
            array(
                'pt' => 'Preservação das datas originais de cadastro dos membros durante a importação',
                'en' => 'Preservation of original member registration dates during import',
            ),
            array(
                'pt' => 'Atualização automática de todos os campos relacionados quando um membro é aprovado em uma nova instituição',
                'en' => 'Automatic update of all related fields when a member is approved at a new institution',
            ),
            array(
                'pt' => 'Configuração de emails oficiais da English Australia (SMTP) - aguardando acesso ao DNS',
                'en' => 'Configuration of official English Australia emails (SMTP) - awaiting DNS access',
            ),
            array(
                'pt' => 'Integração com Mailchimp para sincronização automática de listas de emails - aguardando acesso',
                'en' => 'Mailchimp integration for automatic email list synchronization - awaiting access',
            ),
            array(
                'pt' => 'Integração com meio de pagamento para atualização automática de status (a confirmar necessidade)',
                'en' => 'Payment gateway integration for automatic status updates (to be confirmed)',
            ),
            array(
                'pt' => 'Configuração do sistema de agendamento automático de tarefas (Cron)',
                'en' => 'Configuration of automatic task scheduling system (Cron)',
            ),
            array(
                'pt' => 'Integração com o novo site institucional desenvolvido pela equipe',
                'en' => 'Integration with the new institutional website developed by the team',
            ),
            array(
                'pt' => 'Configuração de conteúdos dinâmicos no site',
                'en' => 'Dynamic content configuration on the website',
            ),
            array(
                'pt' => 'Substituição da assinatura nos certificados de eventos pela assinatura oficial',
                'en' => 'Replacement of event certificate signature with the official signature',
            ),
            array(
                'pt' => 'Configuração do domínio oficial do sistema',
                'en' => 'Configuration of the official system domain',
            ),
            array(
                'pt' => 'Plugin de edição de conteúdos para autonomia na gestão (a confirmar escopo)',
                'en' => 'Content editing plugin for management autonomy (scope to be confirmed)',
            ),
        );
    }

    /**
     * Get client access credentials
     */
    private static function get_client_access() {
        return array(
            'url' => 'https://eaudevapp.platty.tech/',
            'email' => 'rrzillesg@gmail.com',
            'password' => 'Pl@ttyPl@tty',
        );
    }

    /**
     * Get user folders configuration
     */
    private static function get_user_folders() {
        return array(
            'superAdmin' => 'superAdmin',
            'Admin' => 'Admin',
            'institutionAdmin' => 'institutionAdmin',
            'Member' => 'Member',
        );
    }

    /**
     * Render shortcode
     */
    public static function render_shortcode($atts) {
        // Enqueue assets
        self::enqueue_assets();

        // Set screenshots URL
        self::$screenshots_url = EAU_SYSTEM_PLUGIN_URL . 'tests/presentation-screenshots/';

        // Get content
        $content = self::get_content();
        $pending_items = self::get_pending_items();
        $client_access = self::get_client_access();
        $user_folders = self::get_user_folders();
        $today = date('d/m/Y');

        ob_start();
        ?>
        <div class="eau-presentation">
            <!-- Header -->
            <header class="eau-pres-header">
                <div class="eau-pres-logo">ENGLISH AUSTRALIA</div>
                <h1 class="eau-pres-title">
                    <span class="eau-pres-content active" data-lang="pt"><?php echo esc_html($content['pt']['cover']['title']); ?></span>
                    <span class="eau-pres-content" data-lang="en"><?php echo esc_html($content['en']['cover']['title']); ?></span>
                </h1>
                <span class="eau-pres-version">Eau System v<?php echo esc_html(self::$version); ?></span>
            </header>

            <!-- Language Tabs -->
            <nav class="eau-pres-lang-tabs">
                <div class="eau-pres-lang-tabs-inner">
                    <button class="eau-pres-lang-tab active" data-lang="en">
                        <i data-lucide="globe"></i>
                        English
                    </button>
                    <button class="eau-pres-lang-tab" data-lang="pt">
                        <i data-lucide="globe"></i>
                        Português
                    </button>
                </div>
            </nav>

            <?php foreach (array('pt', 'en') as $lang) : $c = $content[$lang]; ?>
            <!-- Content: <?php echo strtoupper($lang); ?> -->
            <div class="eau-pres-content<?php echo $lang === 'en' ? ' active' : ''; ?>" data-lang="<?php echo esc_attr($lang); ?>">

                <!-- Table of Contents -->
                <nav class="eau-pres-toc">
                    <div class="eau-pres-toc-title"><?php echo esc_html($c['toc']['title']); ?></div>
                    <ul class="eau-pres-toc-list">
                        <?php foreach ($c['toc']['items'] as $key => $label) : ?>
                        <li class="eau-pres-toc-item">
                            <a href="#<?php echo esc_attr($lang . '-' . $key); ?>" class="eau-pres-toc-link">
                                <i data-lucide="chevron-right" width="16" height="16"></i>
                                <?php echo esc_html($label); ?>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </nav>

                <!-- Overview Section -->
                <section id="<?php echo esc_attr($lang); ?>-overview" class="eau-pres-section">
                    <h2 class="eau-pres-section-title"><?php echo esc_html($c['sections']['overview']); ?></h2>
                    <p class="eau-pres-text"><?php echo esc_html($c['overview']['intro']); ?></p>

                    <ul class="eau-pres-features">
                        <?php foreach ($c['overview']['features'] as $feature) : ?>
                        <li class="eau-pres-feature-item">
                            <i data-lucide="check-circle" width="18" height="18"></i>
                            <span><?php echo esc_html($feature); ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>

                    <div class="eau-pres-stats">
                        <?php foreach ($c['overview']['stats'] as $stat) : ?>
                        <div class="eau-pres-stat-card">
                            <div class="eau-pres-stat-value"><?php echo esc_html($stat['value']); ?></div>
                            <div class="eau-pres-stat-label"><?php echo esc_html($stat['label']); ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <!-- Features Details Section -->
                <section id="<?php echo esc_attr($lang); ?>-features" class="eau-pres-section">
                    <h2 class="eau-pres-section-title"><?php echo esc_html($c['sections']['featuresDetails']); ?></h2>
                    <p class="eau-pres-text"><?php echo esc_html($c['featuresIntro']); ?></p>

                    <?php foreach ($c['featuresDetails'] as $feature_key => $feature) : ?>
                    <div class="eau-pres-feature-detail">
                        <div class="eau-pres-feature-header">
                            <div class="eau-pres-feature-icon">
                                <i data-lucide="<?php echo esc_attr($feature['icon']); ?>" width="28" height="28"></i>
                            </div>
                            <h3 class="eau-pres-feature-title"><?php echo esc_html($feature['title']); ?></h3>
                        </div>

                        <p class="eau-pres-text"><?php echo esc_html($feature['description']); ?></p>

                        <ul class="eau-pres-capabilities">
                            <?php foreach ($feature['capabilities'] as $cap) : ?>
                            <li class="eau-pres-capability">
                                <i data-lucide="check" width="14" height="14"></i>
                                <?php echo esc_html($cap); ?>
                            </li>
                            <?php endforeach; ?>
                        </ul>

                        <div class="eau-pres-pages-grid">
                            <?php foreach ($feature['screenshots'] as $screenshot) :
                                $image_url = self::$screenshots_url . 'features/' . $screenshot['file'];
                            ?>
                            <div class="eau-pres-page-card">
                                <div class="eau-pres-page-image-wrapper">
                                    <img src="<?php echo esc_url($image_url); ?>"
                                         alt="<?php echo esc_attr($screenshot['title']); ?>"
                                         class="eau-pres-page-image"
                                         loading="lazy">
                                    <div class="eau-pres-page-overlay">
                                        <i data-lucide="maximize-2" width="32" height="32"></i>
                                    </div>
                                </div>
                                <div class="eau-pres-page-content">
                                    <h4 class="eau-pres-page-title"><?php echo esc_html($screenshot['title']); ?></h4>
                                    <p class="eau-pres-page-description"><?php echo esc_html($screenshot['description']); ?></p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </section>

                <!-- User Types Section -->
                <section id="<?php echo esc_attr($lang); ?>-user-types" class="eau-pres-section">
                    <h2 class="eau-pres-section-title"><?php echo esc_html($c['sections']['userTypes']); ?></h2>
                    <p class="eau-pres-text"><?php echo esc_html($c['userTypesIntro']); ?></p>

                    <div class="eau-pres-table-wrapper">
                        <table class="eau-pres-table">
                            <?php foreach ($c['userTypesTable'] as $ri => $row) : ?>
                            <tr>
                                <?php foreach ($row as $ci => $cell) : ?>
                                <?php if ($ri === 0) : ?>
                                <th><?php echo esc_html($cell); ?></th>
                                <?php else : ?>
                                <td><?php echo esc_html($cell); ?></td>
                                <?php endif; ?>
                                <?php endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                </section>

                <!-- User Type Details -->
                <?php
                $user_types = array(
                    'superAdmin' => 'super-admin',
                    'Admin' => 'admin',
                    'institutionAdmin' => 'institution-admin',
                    'Member' => 'member'
                );
                foreach ($user_types as $user_key => $css_class) :
                    $user = $c['users'][$user_key];
                    $folder = $user_folders[$user_key];
                ?>
                <section id="<?php echo esc_attr($lang . '-' . $css_class); ?>" class="eau-pres-section">
                    <div class="eau-pres-user-section">
                        <div class="eau-pres-user-header">
                            <div class="eau-pres-user-icon <?php echo esc_attr($css_class); ?>">
                                <i data-lucide="user" width="24" height="24"></i>
                            </div>
                            <h2 class="eau-pres-user-title"><?php echo esc_html($user['title']); ?></h2>
                        </div>

                        <p class="eau-pres-text"><?php echo esc_html($user['description']); ?></p>

                        <ul class="eau-pres-capabilities">
                            <?php foreach ($user['capabilities'] as $cap) : ?>
                            <li class="eau-pres-capability">
                                <i data-lucide="check" width="14" height="14"></i>
                                <?php echo esc_html($cap); ?>
                            </li>
                            <?php endforeach; ?>
                        </ul>

                        <div class="eau-pres-pages-grid">
                            <?php foreach ($user['pages'] as $page_key => $page) :
                                $image_url = self::$screenshots_url . $folder . '/' . $page_key . '.png';
                            ?>
                            <div class="eau-pres-page-card">
                                <div class="eau-pres-page-image-wrapper">
                                    <img src="<?php echo esc_url($image_url); ?>"
                                         alt="<?php echo esc_attr($page['title']); ?>"
                                         class="eau-pres-page-image"
                                         loading="lazy">
                                    <div class="eau-pres-page-overlay">
                                        <i data-lucide="maximize-2" width="32" height="32"></i>
                                    </div>
                                </div>
                                <div class="eau-pres-page-content">
                                    <h4 class="eau-pres-page-title"><?php echo esc_html($page['title']); ?></h4>
                                    <p class="eau-pres-page-description"><?php echo esc_html($page['description']); ?></p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>
                <?php endforeach; ?>

                <!-- Permissions Matrix -->
                <section id="<?php echo esc_attr($lang); ?>-permissions" class="eau-pres-section">
                    <h2 class="eau-pres-section-title"><?php echo esc_html($c['sections']['permissions']); ?></h2>
                    <p class="eau-pres-text"><?php echo esc_html($c['permissionsIntro']); ?></p>

                    <div class="eau-pres-table-wrapper">
                        <table class="eau-pres-table">
                            <?php foreach ($c['permissionsTable'] as $ri => $row) : ?>
                            <tr>
                                <?php foreach ($row as $ci => $cell) : ?>
                                <?php if ($ri === 0) : ?>
                                <th><?php echo esc_html($cell); ?></th>
                                <?php else : ?>
                                <td>
                                    <?php if ($cell === '✓') : ?>
                                    <span class="eau-pres-check">✓</span>
                                    <?php elseif ($cell === '—') : ?>
                                    <span class="eau-pres-dash">—</span>
                                    <?php else : ?>
                                    <?php echo esc_html($cell); ?>
                                    <?php endif; ?>
                                </td>
                                <?php endif; ?>
                                <?php endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                </section>

                <!-- Pending Items -->
                <section id="<?php echo esc_attr($lang); ?>-pending" class="eau-pres-section">
                    <h2 class="eau-pres-section-title"><?php echo esc_html($c['sections']['pendingItems']); ?></h2>
                    <p class="eau-pres-text"><?php echo esc_html($c['pendingIntro']); ?></p>

                    <ul class="eau-pres-pending-list">
                        <?php foreach ($pending_items as $item) : ?>
                        <li class="eau-pres-pending-item">
                            <i data-lucide="clock" width="18" height="18"></i>
                            <span class="eau-pres-pending-text"><?php echo esc_html($item[$lang]); ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </section>

                <!-- Client Access -->
                <section id="<?php echo esc_attr($lang); ?>-access" class="eau-pres-section">
                    <h2 class="eau-pres-section-title"><?php echo esc_html($c['sections']['clientAccess']); ?></h2>
                    <p class="eau-pres-text"><?php echo esc_html($c['accessIntro']); ?></p>

                    <div class="eau-pres-access-card">
                        <div class="eau-pres-access-title">
                            <i data-lucide="key" width="24" height="24"></i>
                            <?php echo esc_html($c['sections']['clientAccess']); ?>
                        </div>

                        <div class="eau-pres-access-grid">
                            <div class="eau-pres-access-item">
                                <div class="eau-pres-access-label"><?php echo esc_html($c['accessLabels']['url']); ?></div>
                                <div class="eau-pres-access-value"><?php echo esc_html($client_access['url']); ?></div>
                            </div>
                            <div class="eau-pres-access-item">
                                <div class="eau-pres-access-label"><?php echo esc_html($c['accessLabels']['email']); ?></div>
                                <div class="eau-pres-access-value"><?php echo esc_html($client_access['email']); ?></div>
                            </div>
                            <div class="eau-pres-access-item">
                                <div class="eau-pres-access-label"><?php echo esc_html($c['accessLabels']['password']); ?></div>
                                <div class="eau-pres-access-value"><?php echo esc_html($client_access['password']); ?></div>
                            </div>
                        </div>

                        <div class="eau-pres-access-note">
                            <i data-lucide="alert-circle" width="16" height="16"></i>
                            <?php echo esc_html($c['accessNote']); ?>
                        </div>
                    </div>
                </section>

            </div>
            <?php endforeach; ?>

            <!-- Footer -->
            <footer class="eau-pres-footer">
                <div class="eau-pres-footer-version">
                    Eau System v<?php echo esc_html(self::$version); ?>
                </div>
                <div>
                    <?php echo esc_html($today); ?> | Developed by Platty
                </div>
            </footer>

            <!-- Scroll to Top Button -->
            <button class="eau-pres-scroll-top" aria-label="Scroll to top" title="Back to top">
                <i data-lucide="arrow-up"></i>
            </button>
        </div>
        <?php
        return ob_get_clean();
    }
}
