# Eau System

![Version](https://img.shields.io/badge/version-1.2.0-blue.svg)
![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-blue.svg)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)
![License](https://img.shields.io/badge/license-GPL%20v2%2B-green.svg)

Sistema completo para importação de CSV e criação dinâmica de Post Types compatível com **JetEngine** e **WooCommerce**.

## 🎯 Características Principais

- 📁 **Upload e Análise de CSV** - Detecção automática de colunas e delimitadores
- 🔧 **Criação Dinâmica de Post Types** - Interface visual intuitiva
- ⚡ **100% Compatível com JetEngine** - Salva na tabela `wp_jet_post_types`
- 🏷️ **Prefixo Customizado** - Defina prefixos para meta keys (ex: `msp_field_name`)
- 👁️ **Preview em Tempo Real** - Veja o resultado antes de criar
- 🗑️ **Exclusão de Post Types** - Gerencie tudo pela interface
- 🛒 **Compatibilidade WooCommerce** - 100% integrado
- 🎨 **Interface Moderna** - Design responsivo e intuitivo
- 🔒 **Segurança Total** - Nonces, sanitização e validações

## 📸 Screenshots

### Upload de CSV
Interface para upload e análise automática de arquivos CSV.

### Seleção de Colunas
Escolha quais colunas do CSV serão campos do Post Type.

### Preview do Prefixo
Veja em tempo real como ficará o meta key com o prefixo aplicado.

### Gerenciamento
Lista completa dos Post Types criados com opções de visualizar e excluir.

## 🚀 Instalação

### Via WordPress Admin

1. Baixe o arquivo ZIP do plugin
2. Acesse **Plugins → Adicionar Novo → Enviar Plugin**
3. Faça upload do arquivo ZIP
4. Clique em **Instalar Agora** e depois **Ativar**

### Via FTP

1. Extraia o arquivo ZIP
2. Envie a pasta `eau-system` para `/wp-content/plugins/`
3. Ative o plugin através do menu **Plugins** no WordPress

### Via Composer (Em breve)

```bash
composer require platty/eau-system
```

## 📖 Como Usar

### Passo 1: Upload do CSV

1. Acesse **Eau System** no menu do WordPress
2. Clique em **Escolher arquivo** e selecione seu CSV
3. Clique em **Fazer Upload e Analisar**

### Passo 2: Configurar Post Type

1. Digite o **nome do Post Type** (ex: "Produtos", "Clientes")
2. *(Opcional)* Digite um **prefixo** para os meta keys (ex: "msp")
3. Selecione as **colunas** que deseja incluir como campos
4. Visualize o **preview dos dados**
5. Clique em **Criar Post Type**

### Passo 3: Gerenciar

- Acesse o Post Type criado pelo menu do WordPress
- Edite os campos pelo JetEngine (se instalado)
- Exclua Post Types pela interface do Eau System

## 🔧 Requisitos

- **WordPress:** 5.8 ou superior
- **PHP:** 7.4 ou superior
- **Extensões PHP:**
  - `fileinfo` (validação de arquivos)
  - `mbstring` (manipulação de strings)

### Plugins Recomendados

- **JetEngine** - Para edição avançada dos campos
- **WooCommerce** - Para recursos de e-commerce

## 📋 Funcionalidades Detalhadas

### Detecção Inteligente de Tipos

O plugin detecta automaticamente o tipo de campo baseado no nome da coluna:

| Palavra-chave | Tipo de Campo |
|---------------|---------------|
| email | Texto (E-mail) |
| url, link | Texto (URL) |
| phone, telefone | Texto (Telefone) |
| date, data | Data |
| price, preco, valor | Número |
| image, imagem, foto | Mídia |
| description, descricao | Texto Longo |
| *outros* | Texto |

### Prefixo de Meta Keys

Defina um prefixo para organizar seus meta keys:

**Sem prefixo:**
```
first_name
last_name
email
```

**Com prefixo "msp":**
```
msp_first_name
msp_last_name
msp_email
```

### Compatibilidade JetEngine

Os Post Types criados são **100% compatíveis** com JetEngine:

- ✅ Salvos na tabela `wp_jet_post_types`
- ✅ Editáveis pelo JetEngine
- ✅ Formato idêntico aos Post Types nativos do JetEngine
- ✅ Meta fields configuráveis

### Segurança

- ✅ Verificação de nonces em todas as requisições AJAX
- ✅ Sanitização de todos os inputs
- ✅ Escape de outputs (prevenção XSS)
- ✅ Validação de tipos MIME
- ✅ Limite de tamanho de arquivo (10MB)
- ✅ Verificação de permissões (`manage_options`)

## 🗂️ Estrutura do Plugin

```
eau-system/
├── eau-system.php              # Arquivo principal
├── README.md                   # Este arquivo
├── readme.txt                  # Documentação WordPress
├── LEIAME.md                   # Documentação em português
├── .gitignore                  # Git ignore
│
├── includes/                   # Classes PHP
│   ├── class-eau-system.php              # Classe principal
│   ├── class-eau-admin.php               # Interface admin
│   ├── class-eau-csv-handler.php         # Manipulação CSV
│   ├── class-eau-post-type-creator.php   # Criação de Post Types
│   ├── class-eau-woocommerce-compat.php  # Compat. WooCommerce
│   └── admin-page.php                    # Template admin
│
└── assets/                     # Recursos front-end
    ├── css/
    │   └── eau-admin.css       # Estilos admin
    └── js/
        └── eau-admin.js        # JavaScript admin
```

## 🔌 Compatibilidade

### Plugins Testados

| Plugin | Versão | Status |
|--------|--------|--------|
| JetEngine | Todas | ✅ 100% |
| WooCommerce | Todas | ✅ 100% |
| Elementor | Todas | ✅ Compatível |
| Elementor Pro | Todas | ✅ Compatível |
| JetSmartFilters | Todas | ✅ Compatível |
| Rank Math SEO | Todas | ✅ Compatível |

### Temas

✅ Qualquer tema WordPress que siga os padrões

## 🐛 Solução de Problemas

### Upload do CSV falha

- Verifique o tamanho do arquivo (máximo 10MB)
- Confirme que o arquivo é um CSV válido
- Verifique permissões da pasta `wp-content/uploads/`

### Post Type não aparece no menu

- Vá em **Configurações → Links Permanentes** e clique em **Salvar**
- Limpe o cache do site/navegador
- Verifique se o plugin está ativo

### Campos não aparecem no JetEngine

- Confirme que o JetEngine está instalado e ativo
- Recarregue a página do JetEngine
- Verifique a tabela `wp_jet_post_types` no banco de dados

## 📝 Changelog

### 1.2.0 (Atual)
- ✨ Adicionada opção de prefixo customizado para meta keys
- ✨ Preview em tempo real do prefixo aplicado aos campos
- ✨ Validação automática do prefixo
- 🎨 Melhorias na experiência do usuário

### 1.1.0
- 🔧 Corrigida integração com JetEngine
- ✨ Adicionada funcionalidade de exclusão de Post Types
- 🎨 Melhorias na interface administrativa
- 🔒 Otimizações de segurança e performance

### 1.0.0
- 🎉 Versão inicial
- ✨ Upload e análise de CSV
- ✨ Criação dinâmica de Post Types
- ✨ Compatibilidade com JetEngine e WooCommerce

## 🤝 Contribuindo

Contribuições são bem-vindas! Por favor:

1. Fork o projeto
2. Crie uma branch para sua feature (`git checkout -b feature/AmazingFeature`)
3. Commit suas mudanças (`git commit -m 'Add some AmazingFeature'`)
4. Push para a branch (`git push origin feature/AmazingFeature`)
5. Abra um Pull Request

## 📄 Licença

Este plugin é licenciado sob a GPL v2 ou posterior.

```
Copyright (C) 2025 Platty / Rodrigo Zillesg

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.
```

## 👨‍💻 Autor

**Platty / Rodrigo Zillesg**

- Website: [https://platty.com.br](https://platty.com.br)
- GitHub: [@RodrigoZillesg](https://github.com/RodrigoZillesg)
- Email: rrzillesg@gmail.com

## 🙏 Agradecimentos

- WordPress Community
- JetEngine Team
- WooCommerce Team

---

**Desenvolvido com ❤️ por Platty**

🤖 *Built with assistance from [Claude Code](https://claude.com/claude-code)*
