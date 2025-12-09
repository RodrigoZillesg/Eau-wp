# Eau System - Scripts de Teste Playwright

Scripts de automação para testes de funcionalidades do Eau System usando MCP Playwright.

## Estrutura de Arquivos

```
tests/playwright/
├── base-helpers.js           # Funções utilitárias compartilhadas
├── test-event-creation.js    # Teste de criação de eventos (admin)
├── test-event-registration.js # Teste de inscrição em eventos (usuário)
└── README.md                 # Esta documentação
```

## Como Usar com MCP Playwright

Os scripts foram projetados para serem executados **passo a passo** usando as ferramentas do MCP Playwright. Cada função pode ser executada individualmente para permitir análise visual entre os passos.

### Credenciais de Acesso

As credenciais estão definidas em `base-helpers.js`:
- **Admin**: Email e senha para testes de administrador

### URLs de Teste

- Login: `http://eau-site.local/login/`
- Dashboard: `http://eau-site.local/dashboard/`
- Eventos (Frontend): `http://eau-site.local/events/`
- Gerenciamento de Eventos: `http://eau-site.local/dashboard/events/`

## Scripts Disponíveis

### 1. test-event-creation.js - Criação de Eventos

**Objetivo**: Testar o fluxo completo de criação de um evento como administrador.

**Passos**:
1. `step1_login` - Login como admin
2. `step2_navigateToEventsManagement` - Ir para /dashboard/events/
3. `step3_openCreateModal` - Abrir modal de criação
4. `step4_fillBasicInfo` - Preencher informações básicas
5. `step5_fillLocationTab` - Preencher localização
6. `step6_fillPricingTab` - Preencher preços e capacidade
7. `step7_fillSettingsAndPublish` - Configurar e marcar publicação
8. `step8_saveEvent` - Salvar evento
9. `step9_verifyEventInList` - Verificar se aparece na lista
10. `step10_testResponsiveness` - Testar responsividade

**Execução Manual com MCP Playwright**:
```
1. browser_navigate -> http://eau-site.local/login/
2. browser_snapshot -> Analisar página de login
3. browser_type -> Preencher email
4. browser_type -> Preencher senha
5. browser_click -> Clicar em Login
... (continuar conforme os passos do script)
```

---

### 2. test-event-registration.js - Inscrição em Eventos

**Objetivo**: Testar o fluxo de visualização e inscrição em eventos como usuário.

**Passos**:
1. `step1_login` - Login
2. `step2_navigateToEvents` - Ir para /events/
3. `step3_testFilters` - Testar filtros disponíveis
4. `step4_listAvailableEvents` - Listar eventos na página
5. `step5_selectRandomEvent` - Selecionar evento aleatório
6. `step6_viewEventDetails` - Ver detalhes do evento
7. `step7_startRegistration` - Iniciar inscrição
8. `step8_fillRegistrationForm` - Preencher formulário
9. `step9_confirmRegistration` - Confirmar inscrição
10. `step10_testResponsiveness` - Testar responsividade

---

## Viewports para Teste Responsivo

| Nome | Largura | Altura | Dispositivo |
|------|---------|--------|-------------|
| mobile | 375px | 812px | iPhone X |
| tablet | 768px | 1024px | iPad |
| desktop | 1920px | 1080px | Full HD |

## Screenshots

Os screenshots são salvos em `./screenshots/` com o formato:
```
{nome}_{viewport}_{timestamp}.png
```

Exemplo: `01_after_login_desktop_2024-01-15T10-30-00.png`

## Funções Utilitárias (base-helpers.js)

- `login(page, userType)` - Realiza login no sistema
- `takeScreenshot(page, name, viewport)` - Tira screenshot com nome descritivo
- `setViewport(page, size)` - Define o tamanho da janela
- `waitAndClick(page, selector, timeout)` - Aguarda e clica em elemento
- `navigateToMenu(page, menuItem)` - Navega pelo menu
- `waitForLucideIcons(page)` - Aguarda ícones Lucide carregarem
- `waitForToast(page, type, timeout)` - Aguarda notificação toast
- `closeModals(page)` - Fecha modais abertos
- `randomData.*` - Geradores de dados aleatórios para testes

## Checklist de Validação Visual

Ao executar os testes, verificar em cada screenshot:

### Layout Desktop
- [ ] Header alinhado corretamente
- [ ] Menu de navegação visível
- [ ] Conteúdo centralizado
- [ ] Tabelas com largura adequada
- [ ] Botões com espaçamento correto

### Layout Mobile
- [ ] Menu hamburger presente
- [ ] Conteúdo responsivo (sem overflow horizontal)
- [ ] Botões com tamanho touch-friendly
- [ ] Texto legível sem zoom
- [ ] Cards empilhados verticalmente

### Layout Tablet
- [ ] Transição suave entre mobile e desktop
- [ ] Grid adaptado para largura média
- [ ] Navegação adequada para touch

## Integração com Claude Code

Para invocar os testes via Claude Code:

**"Simule a criação de um evento"**:
-> Usar `test-event-creation.js`, executando passos 1-10

**"Simule a inscrição em um evento"**:
-> Usar `test-event-registration.js`, executando passos 1-10

**"Teste a responsividade dos eventos"**:
-> Usar as funções `step10_testResponsiveness` de qualquer script
