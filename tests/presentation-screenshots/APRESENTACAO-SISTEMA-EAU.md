# English Australia - Sistema de Gestão de Membros

## Apresentação do Sistema Eau System v1.51.64

**Data:** Dezembro 2024
**Desenvolvido por:** Platty / Rodrigo Zillesg

---

## Sumário

1. [Visão Geral do Sistema](#visão-geral-do-sistema)
2. [Tipos de Usuários](#tipos-de-usuários)
3. [Funcionalidades por Tipo de Usuário](#funcionalidades-por-tipo-de-usuário)
   - [Super Admin](#1-super-admin)
   - [Admin](#2-admin)
   - [Institution Admin](#3-institution-admin)
   - [Member](#4-member)
4. [Páginas Públicas](#páginas-públicas)
5. [Integrações](#integrações)

---

## Visão Geral do Sistema

O **Eau System** é uma plataforma completa de gestão de membros desenvolvida especificamente para a English Australia. O sistema permite:

- Gestão completa de membros e instituições
- Rastreamento de atividades CPD (Continuous Professional Development)
- Gestão de eventos com inscrições online
- Sistema de membership com diferentes níveis
- Integração com OpenLearning para cursos online
- Gestão de pagamentos e faturas

### Estatísticas Atuais do Sistema
- **6.188** membros totais
- **128** instituições cadastradas
- **16.333** atividades CPD registradas
- **55** cursos disponíveis no OpenLearning

---

## Tipos de Usuários

O sistema possui 4 níveis de acesso com permissões específicas:

| Tipo | Descrição | Acesso Principal |
|------|-----------|------------------|
| **Super Admin** | Administrador master do sistema | Acesso total a todas funcionalidades |
| **Admin** | Administrador da English Australia | Gestão de membros, instituições e eventos |
| **Institution Admin** | Administrador de instituição parceira | Gestão da própria instituição e seus membros |
| **Member** | Membro individual | Perfil pessoal, CPDs e cursos |

---

## Funcionalidades por Tipo de Usuário

---

### 1. SUPER ADMIN

**Descrição:** Administrador com acesso total ao sistema, incluindo funcionalidades exclusivas de gestão e configuração.

#### Dashboard do Super Admin
![Dashboard Super Admin](superAdmin/01-dashboard.png)

O dashboard do Super Admin exibe:
- **Total de Membros**: 6.188 (6.114 ativos)
- **Total de Instituições**: 128
- **Atividades CPD**: 16.333
- **Próximos Eventos**: 9
- **Pontos Concedidos**: 114.438,8
- **Pagamentos Pendentes**: 2
- **Aprovações Pendentes**: 1

**Funcionalidades Exclusivas:**
- QR Code para compartilhamento da página de registro
- Botões de compartilhamento via WhatsApp, LinkedIn, X (Twitter) e Email
- Visualização de cursos OpenLearning disponíveis

#### Members Management
![Members Management](superAdmin/02-members.png)

Página completa de gestão de membros com:
- **Busca avançada** por nome, email ou telefone
- **Filtros múltiplos**: Status, User Type, Institution, Data de Registro, Membership Type
- **Ações em massa**: Delete Selected, Delete All Filtered
- **Exportação**: Export CSV
- **Merge de Duplicatas**: Ferramenta para unificar registros duplicados
- **Ações individuais**: View, Edit, Delete

**Colunas da tabela:**
- Member (nome)
- Contact (email)
- Membership (instituição e tipo)
- User Type (badge colorido)
- Status (Active/Pending/etc)
- Actions

#### Institutions Management
![Institutions Management](superAdmin/03-institutions.png)

Gestão completa de instituições parceiras:
- Lista de todas as 128 instituições
- Informações de contato e status
- Vínculo com membros (Primary Contact)
- Status de membership da instituição

#### CPD Activities Management
![CPD Activities](superAdmin/04-activities.png)

Gestão de atividades de desenvolvimento profissional:
- Registro de atividades com pontuação CPD
- Categorização por tipo
- Aprovação de atividades pendentes
- Histórico completo de atividades

#### CPD Categories Management
![CPD Categories](superAdmin/05-categories.png)

Configuração das categorias de atividades CPD:
- Criação e edição de categorias
- Definição de pontos por categoria
- Organização hierárquica

#### Events Management (Admin View)
![Events Admin](superAdmin/06-events-admin.png)

Painel administrativo de eventos:
- Criação de novos eventos
- Gestão de inscrições
- Configuração de preços e capacidade
- Publicação e despublicação
- Visualização de participantes

#### Payments Management
![Payments](superAdmin/07-payments.png)

Controle financeiro completo:
- Lista de todas as faturas
- Status de pagamento (Paid/Pending/Overdue)
- Filtros por período e status
- Geração de relatórios

#### Membership Applications
![Membership Applications](superAdmin/08-membership-applications.png)

Gestão de solicitações de membership:
- Aprovação/rejeição de aplicações
- Visualização de documentos
- Histórico de aplicações

#### My Profile
![My Profile](superAdmin/09-my-profile.png)

Perfil pessoal do usuário:
- Informações pessoais
- Dados de contato
- Configurações de conta

#### My CPDs
![My CPDs](superAdmin/10-my-cpds.png)

Registro pessoal de atividades CPD:
- Histórico de atividades
- Pontuação acumulada
- Submissão de novas atividades

#### Events (Frontend)
![Events Frontend](superAdmin/11-events-frontend.png)

Página pública de eventos:
- Lista de eventos disponíveis
- Filtros por data e categoria
- Cards com informações do evento
- Botão de inscrição

#### OpenLearning Courses
![Courses](superAdmin/12-courses.png)

Integração com plataforma OpenLearning:
- Catálogo de 55 cursos disponíveis
- Acesso direto aos cursos
- Progresso de conclusão

---

### 2. ADMIN

**Descrição:** Administrador da English Australia com acesso à gestão de membros, instituições e eventos, porém sem acesso a funcionalidades exclusivas do Super Admin.

#### Dashboard do Admin
![Dashboard Admin](Admin/01-dashboard.png)

Dashboard similar ao Super Admin, com:
- Mesmas estatísticas gerais
- Cards de navegação rápida
- QR Code de compartilhamento
- Cursos disponíveis

**Diferença:** A saudação mostra "Here's what's happening with your membership today" ao invés de "Full access to all institutions and data".

#### Funcionalidades Disponíveis:

| Página | Descrição |
|--------|-----------|
| ![Members](Admin/02-members.png) | **Members Management** - Gestão completa de membros |
| ![Institutions](Admin/03-institutions.png) | **Institutions Management** - Gestão de instituições |
| ![Activities](Admin/04-activities.png) | **CPD Activities** - Gestão de atividades |
| ![Events](Admin/05-events-admin.png) | **Events Management** - Gestão de eventos |
| ![Payments](Admin/06-payments.png) | **Payments** - Gestão de pagamentos |
| ![Applications](Admin/07-membership-applications.png) | **Membership Applications** - Aprovação de aplicações |
| ![Profile](Admin/08-my-profile.png) | **My Profile** - Perfil pessoal |
| ![Events Public](Admin/09-events-frontend.png) | **Events (Public)** - Visualização de eventos |

**Diferenças em relação ao Super Admin:**
- Não possui botão "Delete All Filtered" em algumas páginas
- Algumas configurações avançadas não estão disponíveis

---

### 3. INSTITUTION ADMIN

**Descrição:** Administrador de uma instituição parceira. Tem acesso limitado aos dados da sua própria instituição e seus membros vinculados.

#### Dashboard do Institution Admin
![Dashboard Institution Admin](institutionAdmin/01-dashboard.png)

Dashboard personalizado mostrando:
- **Member Requests**: Solicitações de membros para a instituição
- **Upcoming Events**: Próximos eventos disponíveis
- **My Profile**: Acesso ao perfil
- **My Membership**: Status do membership pessoal
- **Available Courses**: Cursos do OpenLearning

**Identificação:** O sistema exibe "Institution Administrator for [Nome da Instituição]" (ex: "C2C Global")

#### My Institution
![My Institution](institutionAdmin/02-my-institution.png)

Página dedicada à gestão da instituição:
- Informações da instituição
- Lista de membros vinculados
- Aprovação de novos membros
- Edição de dados da instituição

#### Funcionalidades Disponíveis:

| Página | Descrição |
|--------|-----------|
| ![Profile](institutionAdmin/03-my-profile.png) | **My Profile** - Perfil pessoal |
| ![My CPDs](institutionAdmin/04-my-cpds.png) | **My CPDs** - Atividades CPD pessoais |
| ![Events](institutionAdmin/05-events-frontend.png) | **Events** - Visualização e inscrição em eventos |
| ![Courses](institutionAdmin/06-courses.png) | **Courses** - Acesso aos cursos OpenLearning |

**Limitações:**
- Não acessa Members Management geral
- Não acessa Institutions Management geral
- Não acessa Activities Management geral
- Não acessa Payments Management
- Não acessa Membership Applications

---

### 4. MEMBER

**Descrição:** Membro individual da English Australia. Possui acesso apenas às suas próprias informações e recursos disponíveis para membros.

#### Dashboard do Member
![Dashboard Member](Member/01-dashboard.png)

Dashboard simplificado com:
- **Upcoming Events**: 9 eventos disponíveis
- **My Profile**: Acesso ao perfil
- **My Membership**: Status (pode mostrar "Not a Member - Apply for Membership")
- **Available Courses**: Cursos do OpenLearning

#### Funcionalidades Disponíveis:

| Página | Descrição |
|--------|-----------|
| ![Profile](Member/02-my-profile.png) | **My Profile** - Perfil pessoal completo |
| ![My CPDs](Member/03-my-cpds.png) | **My CPDs** - Registro de atividades CPD |
| ![Membership](Member/04-membership-selection.png) | **Membership Selection** - Escolha de plano de membership |
| ![Events](Member/05-events-frontend.png) | **Events** - Visualização e inscrição em eventos |
| ![Courses](Member/06-courses.png) | **Courses** - Acesso aos cursos OpenLearning |

**Características:**
- Acesso somente aos próprios dados
- Pode submeter atividades CPD
- Pode se inscrever em eventos
- Pode acessar cursos online
- Pode aplicar para membership

---

## Páginas Públicas

### Página de Eventos
A página de eventos é acessível publicamente e exibe:
- Lista de eventos futuros
- Informações de data, local e preço
- Botão de inscrição (requer login)
- Filtros por categoria e data

### Página de Registro
Formulário público para novos membros:
- Dados pessoais
- Vinculação com instituição (opcional)
- Criação de conta

### Página de Membership Selection
Para membros que desejam adquirir um plano:
- Diferentes tipos de membership
- Preços e benefícios
- Processo de pagamento

---

## Integrações

### OpenLearning
- **55 cursos** disponíveis
- Acesso direto do dashboard
- Cursos gratuitos e pagos
- Integração com CPD points

### Sistema de Pagamentos
- Gestão de faturas
- Status de pagamento
- Histórico financeiro

### Compartilhamento Social
- QR Code para registro
- WhatsApp
- LinkedIn
- X (Twitter)
- Email

---

## Resumo de Permissões

| Funcionalidade | Super Admin | Admin | Institution Admin | Member |
|----------------|:-----------:|:-----:|:-----------------:|:------:|
| Dashboard Completo | ✅ | ✅ | ❌ | ❌ |
| Members Management | ✅ | ✅ | ❌ | ❌ |
| Institutions Management | ✅ | ✅ | ❌ | ❌ |
| Activities Management | ✅ | ✅ | ❌ | ❌ |
| Categories Management | ✅ | ❌ | ❌ | ❌ |
| Events Management | ✅ | ✅ | ❌ | ❌ |
| Payments Management | ✅ | ✅ | ❌ | ❌ |
| Membership Applications | ✅ | ✅ | ❌ | ❌ |
| My Institution | ❌ | ❌ | ✅ | ❌ |
| My Profile | ✅ | ✅ | ✅ | ✅ |
| My CPDs | ✅ | ✅ | ✅ | ✅ |
| Events (Frontend) | ✅ | ✅ | ✅ | ✅ |
| Courses | ✅ | ✅ | ✅ | ✅ |
| Membership Selection | ✅ | ✅ | ✅ | ✅ |
| Delete All Filtered | ✅ | ❌ | ❌ | ❌ |
| Merge Duplicates | ✅ | ✅ | ❌ | ❌ |

---

## Conclusão

O sistema Eau System oferece uma solução completa e robusta para a gestão de membros da English Australia, com:

1. **Hierarquia de permissões** bem definida
2. **Interface intuitiva** e responsiva
3. **Gestão completa** de membros, instituições e eventos
4. **Sistema CPD** para desenvolvimento profissional
5. **Integração** com plataformas externas (OpenLearning)
6. **Relatórios** e exportação de dados

**Versão atual:** 1.51.64
**Desenvolvido por:** Platty / Rodrigo Zillesg
**Data:** Dezembro 2024

---

*Este documento foi gerado automaticamente com screenshots capturados do sistema em ambiente de desenvolvimento.*
