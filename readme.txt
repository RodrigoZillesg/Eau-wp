=== Eau System ===
Contributors: Platty, Rodrigo Zillesg
Tags: csv, import, post-type, jetengine, woocommerce
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.12.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sistema para importação de CSV e criação dinâmica de Post Types compatível com JetEngine e WooCommerce.

== Description ==

O **Eau System** é um plugin poderoso e intuitivo que permite:

1. **Upload e Análise de CSV**: Faça upload de arquivos CSV e visualize automaticamente todas as colunas e dados
2. **Criação Dinâmica de Post Types**: Crie Post Types customizados diretamente da interface, selecionando quais colunas do CSV serão campos
3. **Compatibilidade Total com JetEngine**: Os Post Types são criados no mesmo formato do JetEngine
4. **100% Compatível com WooCommerce**: Funciona perfeitamente com todas as funcionalidades do WooCommerce

== Características ==

* Interface intuitiva e fácil de usar
* Detecção automática de tipos de campo (texto, número, data, mídia, etc.)
* Preview dos dados do CSV antes de criar o Post Type
* Seleção flexível de colunas
* Validação de arquivos CSV
* Suporte a diferentes delimitadores (vírgula, ponto-e-vírgula, tab)
* Arquitetura segura e orientada a objetos
* Código limpo e bem documentado

== Installation ==

1. Faça upload da pasta `eau-system` para o diretório `/wp-content/plugins/`
2. Ative o plugin através do menu 'Plugins' no WordPress
3. Acesse 'Eau System' no menu do admin para começar a usar

== Frequently Asked Questions ==

= O plugin é compatível com JetEngine? =

Sim! Os Post Types criados pelo Eau System seguem exatamente o mesmo formato do JetEngine.

= Funciona com WooCommerce? =

Sim! O plugin é 100% compatível com WooCommerce e todos os seus recursos.

= Qual o tamanho máximo de arquivo CSV? =

O tamanho máximo padrão é 10MB.

= Que formatos de CSV são aceitos? =

O plugin aceita arquivos .csv com delimitadores: vírgula (,), ponto-e-vírgula (;), tab (\t) ou pipe (|).

= Posso editar os campos depois de criar o Post Type? =

Sim! O Post Type criado pode ser gerenciado normalmente através do WordPress e JetEngine (se instalado).

== Screenshots ==

1. Tela principal de upload de CSV
2. Análise automática das colunas do CSV
3. Seleção de colunas para criar o Post Type
4. Post Type criado com sucesso

== Changelog ==

= 1.12.3 =
* **AJUSTES UX:** Melhorias na apresentação da tabela de membros
* Diminuída fonte do email de 14px para 12px (0.75rem) para melhor legibilidade
* Removida coluna "Institution" (redundante - já aparece em Membership)
* Tabela agora tem 5 colunas: MEMBER | CONTACT | MEMBERSHIP | USER TYPE | STATUS
* Coluna Membership mostra: Nome da Instituição (grande) + Tipo de Membership (pequeno/subtitle)
* Visual mais limpo e informações melhor organizadas
* Menos poluição visual na tabela

= 1.12.2 =
* **REFORÇO CSS:** Adicionados !important em TODOS os estilos da tabela para garantir compatibilidade
* Corrigido: Interferência de CSS do tema/Elementor na tabela de membros
* Adicionados estilos mais específicos: `.eau-table tbody`, `.eau-table thead tr`
* Propriedades adicionadas: `line-height`, `vertical-align`, `border`, `border-spacing`, `table-layout`
* Estilos para elementos internos: `.eau-member-cell`, `.eau-membership-cell`, `.eau-membership-subtitle`
* Links da tabela: cor azul (#2563eb) com hover underline
* Checkboxes: estilos completos com `appearance`, `border`, `border-radius`
* Reset universal: `.eau-table * { box-sizing: border-box !important; }`
* Background branco forçado em tbody, tr e td
* Borders consistentes em todas as células
* Garantia de visual limpo e profissional independente do tema

= 1.12.1 =
* **CORREÇÃO CRÍTICA:** Resolvido problema de CSS que causava modal visível por padrão
* Corrigido: `.eau-modal-overlay` tinha `display: flex !important` sobrescrevendo `display: none` inline
* Corrigido: `.eau-table-loading-overlay` com o mesmo problema
* Solução: Adicionados seletores CSS com especificidade usando `[style*="display: none"]`
* Agora modais ficam ocultos por padrão e só aparecem quando explicitamente abertos
* Agora loading overlay da tabela fica oculto por padrão e só aparece durante carregamento
* CSS mais inteligente: respeita inline styles para controle dinâmico via JavaScript

= 1.12.0 =
* **PÁGINA DE CONFIGURAÇÃO:** Members Settings - Configure campos editáveis nos modais
* Nova página no WordPress Admin: "Eau System → Members Settings"
* Interface visual para configurar quais campos aparecem nos modais View/Edit/Add
* Tabela com todos os campos disponíveis (WordPress Core + Custom Meta Fields)
* Checkboxes para: Enabled (exibir campo), Required (obrigatório), Read Only (somente leitura)
* Drag & Drop (jQuery UI Sortable) para reordenar campos
* Separação visual: Core Fields (WordPress) vs Custom Meta Fields (JetEngine/Custom)
* Meta fields incluídos: mem_status, mem_membercompanyname, mem_phone, mem_address, mem_city, mem_state, mem_postcode, mem_country
* Salvamento via WordPress Options API (option name: eau_members_editable_fields)
* JavaScript: sortable drag & drop, auto-disable checkboxes, unsaved changes warning
* CSS dedicado: tabela moderna, drag handle visual, hover states, responsive
* AJAX handler eau_get_editable_fields: retorna configuração para o modal
* Integração automática: modal agora busca campos configurados antes de renderizar
* Fallback inteligente: se não houver configuração, usa campos padrão
* Campos personalizáveis por tipo: text, email, tel, textarea, select
* Sistema preparado para futuras integrações: JetEngine auto-detection (TODO)

= 1.11.0 =
* **NOVO COMPONENTE:** Modal reutilizável para View/Edit/Add Members
* Componente Eau_Modal altamente configurável (small, medium, large, full sizes)
* 3 modais implementados: View Member, Edit Member, Add New Member
* Modal com Header (título + botão X), Body (conteúdo dinâmico), Footer (botões de ação)
* Sistema de formulários dentro do modal com grid responsivo (2 colunas)
* Suporte a múltiplos tipos de campo: text, email, tel, number, date, textarea, select, checkbox, static
* Campos com span configurável (span-1, span-2) para layout flexível
* Validação de campos required, readonly, disabled
* JavaScript completo: openModal, closeModal, loadMemberDetails, renderMemberForm
* AJAX handlers: eau_get_member_details (busca dados), eau_update_member (salva), eau_create_member (cria)
* View Modal: exibe todos os dados em modo read-only
* Edit Modal: formulário editável com validação e save via AJAX
* Add Modal: formulário vazio para criar novos membros com geração automática de senha
* Animações suaves: fadeIn overlay + slideUp modal
* CSS completo: overlay, modal sizes, form grid, inputs, estados focus/hover/disabled
* Mobile: full-screen modal, form em 1 coluna, botões full-width
* Integração perfeita: após save/create, recarrega tabela automaticamente
* Close modal: botão X, click no overlay, botão Cancel

= 1.10.0 =
* **NOVO COMPONENTE:** Filters Panel para Members Management
* Componente reutilizável Eau_Filters com suporte a múltiplos tipos de filtro
* Filtros implementados: Status, User Type, Membership Type, Institution, Registration Date Range
* Interface com grid responsivo e botões "Apply Filters" e "Clear Filters"
* Badge de contagem de filtros ativos no botão "Filters"
* Filtros colapsáveis (slide toggle) para economizar espaço
* JavaScript: métodos handleApplyFilters e handleClearFilters
* Backend: suporte completo a todos os filtros no AJAX handler eau_get_members
* Filtro por Membership Type com JOINs complexos (usermeta → postmeta → ins_type)
* Filtro por Date Range com FROM e TO para user_registered
* Estilos CSS completos: inputs, selects, date pickers, responsive design
* Mobile: layout vertical automático, filtros em 1 coluna
* Integração perfeita com sistema de paginação e busca existente

= 1.9.0 =
* **NOVA FUNCIONALIDADE MAJOR:** Members Management Page
* Novo shortcode [eau_members_management] para gerenciamento completo de membros
* Helper class para relacionamento User ↔ Institution (mem_membercompanyname = ins_company_id)
* Componente Stats Cards reutilizável (adaptado do dashboard)
* Stats: Total Members, Active Members, New This Month, Inactive Members
* Interface com Page Header, botões Export CSV e Add Member
* Search Bar com busca por nome, email ou phone
* Filtros (em desenvolvimento): Status, User Type, Membership, Institution, Data
* Arquitetura componentizada: componentes isolados e reutilizáveis
* CSS modular: eau-components.css (compartilhado) + eau-members-management.css
* Totalmente responsivo desde o início (Mobile First)
* Status baseado no metadado mem_status (não user_status)
* Base sólida para próximas features: Data Table, Pagination, Modal, etc.

= 1.8.7 =
* **RESPONSIVIDADE COMPLETA:** Dashboard agora é 100% responsivo em todos os dispositivos
* Mobile (<768px): 1 coluna, fontes reduzidas, ícones 32px
* Tablet (768px-1023px): 2 colunas, layout otimizado
* Desktop Small (1024px-1279px): 4 colunas, espaçamento ajustado
* Desktop Large (≥1280px): 4 colunas, layout completo
* Welcome Section responsivo: fontes adaptativas (24px→28px→32px)
* Cards responsivos: padding, fontes e ícones ajustados por breakpoint
* Mobile-first approach com !important em todos os breakpoints
* Garantia de visualização perfeita em smartphones, tablets e desktops

= 1.8.6 =
* **NOVA FUNCIONALIDADE:** Seção de Welcome antes dos cards estatísticos
* Exibe "Welcome, [Nome do Usuário]" usando display_name do usuário logado
* Mensagem descritiva: "Here's what's happening with your membership today."
* Design limpo e profissional alinhado à esquerda
* Tipografia: título 32px (semi-bold), descrição 16px (regular, cinza)
* Espaçamento generoso (2.5rem) antes dos cards
* Totalmente responsivo e com !important para sobrescrever tema

= 1.8.5 =
* **AJUSTES DE LAYOUT:** Melhorias na organização visual dos cards
* Ordem reorganizada: TÍTULO primeiro, depois NÚMERO e subtítulo
* Posição invertida: conteúdo à ESQUERDA, ícone à DIREITA
* Círculo de fundo dos ícones REMOVIDO - agora são apenas ícones puros
* Ícones redimensionados para 40px (sem círculo)
* Textos alinhados à esquerda dentro dos cards
* Layout mais limpo e minimalista

= 1.8.4 =
* **CORREÇÃO CRÍTICA:** Adicionado !important em TODOS os estilos CSS do dashboard
* CSS agora sobrescreve corretamente os estilos do tema e do Elementor
* Garantia de que o layout horizontal será aplicado
* Garantia de que os ícones serão grandes (68px) e circulares
* Garantia de que as fontes serão bold (700) e no tamanho correto
* Garantia de que o grid terá 4 colunas no desktop
* Garantia de que as cores serão saturadas e vibrantes
* Especificidade CSS aumentada para evitar conflitos com outros plugins/temas

= 1.8.3 =
* **CORREÇÃO COMPLETA DO DESIGN:** Dashboard agora segue 100% a imagem de referência
* Layout HORIZONTAL correto: ícone à ESQUERDA + conteúdo à DIREITA
* Grid de 4 COLUNAS (não mais 5) no desktop
* Ícones GRANDES (68px) em CÍRCULOS PERFEITOS (border-radius: 50%)
* Números em BOLD (font-weight: 700) ao invés de light
* Fontes aumentadas: números 36px, títulos 14px, subtítulos 13px
* Cores SATURADAS: backgrounds -100/-200 ao invés de -50
* Padding GENEROSO: 28×24px ao invés de 20×16px
* Espaçamento maior entre cards: 24px (gap)
* Cards mais largos: min-width 280px
* Sombras mais pronunciadas no hover
* Design profissional e limpo como solicitado

= 1.8.2 =
* **REDESIGN COMPLETO:** Dashboard agora é FINO e MINIMALISTA
* Layout vertical: Número grande no topo → Título no meio → Ícone pequeno embaixo
* Números com font-weight 300 (light) - muito mais elegante e fino
* Fontes reduzidas: números 32px, títulos 11px, subtítulos 11px
* Ícones menores (18px) em quadrados levemente arredondados (6px) ao invés de círculos grandes
* Cores mais suaves: backgrounds -50 ao invés de -100 (eff6ff, f0fdf4, faf5ff, fff7ed, fef2f2)
* Todos os números sempre #111827 (escuro) - independente da cor do card
* Padding reduzido: 1.25rem (cards mais compactos)
* Espaçamento menor entre cards: 1rem (gap)
* Cards mais estreitos: min-width 200px ao invés de 250px
* Sombras ultra sutis: 0 1px 2px rgba(0,0,0,0.05)
* Hover minimalista: translateY(-1px) e shadow leve
* Border-radius suave: 8px ao invés de 12px
* Design 100% minimalista e profissional como solicitado

= 1.8.1 =
* **CORREÇÃO:** Dashboard agora segue 100% o Design System (DESIGN_SYSTEM_GUIDE_FOR_AI.md)
* CSS completamente refeito com classes do Tailwind/Design System
* Ícones Lucide agora aparecem automaticamente nos círculos coloridos
* Círculos agora são totalmente redondos (rounded-full) ao invés de quadrados arredondados
* Tamanhos ajustados: ícones 24px (w-6 h-6), círculos 48px (h-12 w-12)
* Cores dos cards ajustadas para Design System (blue-100, green-100, purple-100, orange-100, red-100)
* Tipografia ajustada: números text-3xl, títulos text-sm font-semibold, subtítulos text-xs
* Borders ajustadas para border-gray-200 e shadows para shadow-sm (hover: shadow-lg)
* Container ajustado para max-w-7xl com padding responsivo (px-4 sm:px-6 lg:px-8)
* Script de inicialização do Lucide melhorado com retry e verificação de carregamento
* Todas as queries SQL agora usam prepared statements para segurança
* Todas as queries otimizadas com wpdb direto ao invés de WP_Query/WP_User_Query
* Versão atualizada para 1.8.1

= 1.8.0 =
* **NOVIDADE MAJOR:** Dashboard Administrativo com shortcode [eau_admin_dashboard]
* Sistema de cards estatísticos com 5 métricas principais:
  - Total Members (com contagem de membros ativos)
  - CPD Activities (com atividades pendentes de aprovação)
  - Active Events (eventos com data presente/futura)
  - Points Awarded (soma de horas de todas as atividades)
  - Pending Payments (dados simulados por enquanto)
* Design moderno e responsivo com Lucide Icons
* Grid layout adaptativo: 1 coluna (mobile), 2 colunas (tablet), 5 colunas (desktop)
* Cards com efeitos hover e cores temáticas (azul, verde, roxo, laranja, vermelho)
* Queries otimizadas: WP_User_Query, WP_Query e wpdb direto para agregações
* CSS dedicado (eau-dashboard.css) enfileirado no frontend
* Lucide Icons carregado apenas em páginas com o shortcode
* Classe Eau_Dashboard com 7 métodos de estatísticas
* Sistema preparado para diferentes visualizações por mem_type (futuro)

= 1.7.2 =
* **NOVIDADE:** Suporte completo para Lucide Icons
* Biblioteca de ícones SVG moderna carregada via CDN
* Inicialização automática em todas as páginas do plugin
* Documentação completa em LUCIDE-ICONS.md
* Uso simples: `<i data-lucide="heart"></i>`
* Mais de 1000 ícones disponíveis
* Performance otimizada (apenas ~50KB)
* SVG inline escalável sem perda de qualidade

= 1.7.1 =
* **NOVIDADE:** Coluna Distinct para evitar duplicatas em importações
* Nova opção no mapeamento: "Coluna Distinct (Evitar Duplicatas)"
* Posts com mesmo valor na coluna distinct são ATUALIZADOS ao invés de duplicados
* Lógica inteligente: cria novo se não existe, atualiza se existe
* Atualização incremental: novos dados do CSV complementam/sobrescrevem dados antigos
* Campo destacado visualmente em amarelo para fácil identificação
* Útil para importações recorrentes (ex: atualizar catálogo de produtos)
* Funciona com qualquer campo customizado como referência

= 1.7.0 =
* **NOVIDADE MAJOR:** Sistema de sincronização de User Types (mem_type)
* Nova aba "Sincronização" na interface administrativa
* Sincroniza mem_type baseado em posts de membership:
  - Usuários com email em primary_contacts_email → institutionAdmin
  - Todos os outros usuários → Member
* Dashboard de estatísticas mostrando distribuição atual de tipos
* Classe Eau_User_Type_Sync para sincronização automática
* Métodos: sync_all_user_types(), sync_single_user(), get_current_stats()
* Interface com confirmação e feedback em tempo real
* Toast notification ao completar sincronização
* Recarregamento automático para atualizar estatísticas

= 1.6.3 =
* **NOVIDADE:** Limite de importação configurável (All, 10, 100, 1000 usuários)
* Usuários com email já cadastrado são IGNORADOS e NÃO contam no limite
* Interface atualizada com seletor de quantidade no Step 4
* Backend processa limite corretamente, parando quando atingido
* Progress bar mostra total processado + total importado separadamente
* Log informa quando limite é atingido
* Resumo final mostra se limite foi aplicado e quantos foram ignorados por email duplicado
* Validação de email único JÁ EXISTENTE mantida e reforçada

= 1.6.2 =
* **SEGURANÇA:** Bloqueio TOTAL de envio de emails durante importação de usuários
* Filtro `pre_wp_mail` bloqueia wp_mail() completamente quando send_email = false
* Proteção específica contra emails do WooCommerce (customer_new_account)
* Garantia 100% de que nenhum email será enviado acidentalmente durante importação em massa
* Filtros são aplicados e removidos por usuário, mantendo segurança e controle

= 1.6.1 =
* **CORREÇÃO CRÍTICA:** Formato de meta boxes agora 100% compatível com JetEngine
* Estrutura de campos corrigida com todas as propriedades: id, isNested, options_source
* Meta boxes agora aparecem corretamente em JetEngine → Meta Boxes
* Campos renderizam perfeitamente na tela de edição de usuários
* Array keys corrigidos (slug como chave, não índice numérico)
* Campos incluem: title, name, object_type, width, options, repeater-fields, type, id, isNested, options_source

= 1.6.0 =
* **NOVIDADE:** Sistema profissional de notificações toast (reutilizável em outros plugins)
* Toast notifications que persistem entre page reloads usando sessionStorage
* Integração completa de user meta boxes com JetEngine
* Meta boxes de usuários agora aparecem na tela de edição de usuários
* Salvamento duplo: formato Eau System + formato JetEngine (wp_options: jet_engine_meta_boxes)
* Mapeamento automático de tipos de campo para JetEngine
* Remoção sincronizada de meta boxes em ambos os sistemas
* Melhor feedback visual ao criar/excluir meta boxes
* Arquivos criados: eau-toast.css, eau-toast.js (sistema reutilizável)
* Location.reload() mantido apenas onde necessário com toast persistente

= 1.5.2 =
* Corrigido erro "Cannot read properties of undefined (reading 'slice')" no upload de CSV para user meta boxes
* Adicionada verificação de existência de preview antes de renderizar tabela
* Melhor tratamento de dados ausentes na resposta do AJAX

= 1.5.1 =
* JavaScript completo para sistema de usuários implementado
* Navegação entre tabs (Post Types e Usuários) funcionando
* Upload e criação de user meta boxes completamente funcional
* Modal de importação de usuários com 6 etapas completas
* Sistema de condicionais para importação de usuários
* Mapeamento automático de colunas CSV para campos de usuário
* Interface completa e interativa para todas as funcionalidades
* +750 linhas de JavaScript adicionadas
* Sistema 100% funcional e pronto para uso em produção

= 1.5.0 =
* Sistema completo de importação de usuários do WordPress
* Criação de Meta Boxes de usuários a partir de CSV (similar aos Post Types)
* Classe Eau_User_Importer para importação em lote de usuários
* Classe Eau_User_Meta_Creator para gerenciar meta boxes de usuários
* Classe Eau_Roles com 4 perfis customizados: Super Admin, Admin Eau, Administrador de Instituição, Membro
* Handlers AJAX para criação de meta boxes e importação de usuários
* Interface com tabs separadas: Post Types e Usuários
* Modal de importação de usuários com 6 etapas
* Suporte a mapeamento de colunas para campos nativos do WP (user_email, user_login, first_name, etc.)
* Suporte a user meta fields customizados
* Opção de definir role durante importação
* Opção de senha padrão ou geração automática
* Opção de envio de email de boas-vindas
* Sistema de condicionais também aplicado à importação de usuários
* Backend completo implementado (JavaScript será adicionado em versão futura)

= 1.4.0 =
* Adicionado sistema de condicionais para filtrar importação
* Nova etapa no modal de importação para configurar condições
* Suporte a 12 operadores diferentes (não vazio, vazio, igual, diferente, maior que, menor que, contém, etc.)
* Condições aplicadas com operador lógico E (todas devem ser atendidas)
* Interface visual para adicionar/remover condições dinamicamente
* Exemplos: importar apenas se coluna não estiver vazia, ou valor maior que X

= 1.3.0 =
* Adicionado sistema completo de importação de dados CSV
* Modal com 4 etapas: upload, mapeamento, condicionais e progresso
* Importação em lotes de 25 itens para melhor performance
* Mapeamento automático e manual de colunas
* Barra de progresso em tempo real
* Log detalhado de importação com status

= 1.2.0 =
* Adicionada opção de prefixo customizado para meta keys
* Preview em tempo real do prefixo aplicado aos campos
* Validação automática do prefixo (apenas letras, números e underscore)
* Melhorias na experiência do usuário

= 1.1.0 =
* Corrigida integração com JetEngine - agora salva na tabela wp_jet_post_types
* Post Types agora são 100% editáveis pelo JetEngine
* Adicionada funcionalidade de exclusão de Post Types
* Melhorias na interface administrativa
* Otimizações de segurança e performance

= 1.0.0 =
* Versão inicial
* Upload e análise de CSV
* Criação dinâmica de Post Types
* Compatibilidade com JetEngine
* Compatibilidade com WooCommerce
* Interface administrativa completa

== Upgrade Notice ==

= 1.1.0 =
Atualização importante: corrige integração com JetEngine e adiciona exclusão de Post Types.

= 1.0.0 =
Versão inicial do plugin.
