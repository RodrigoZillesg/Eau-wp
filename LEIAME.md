# Eau System

**Versão:** 1.0.0
**Autor:** Platty / Rodrigo Zillesg
**Compatibilidade:** WordPress 5.8+, PHP 7.4+

## 📋 Descrição

O **Eau System** é um plugin WordPress profissional que facilita a importação de dados via CSV e a criação dinâmica de Post Types customizados. Ele é 100% compatível com **JetEngine** e **WooCommerce**.

## ✨ Funcionalidades Principais

### 1. Upload e Análise de CSV
- Upload de arquivos CSV (até 10MB)
- Detecção automática de delimitadores (vírgula, ponto-e-vírgula, tab, pipe)
- Análise automática das colunas e tipos de dados
- Preview dos dados antes de importar
- Validação completa de segurança

### 2. Criação Dinâmica de Post Types
- Interface visual para criar Post Types sem código
- Seleção interativa de colunas do CSV
- Detecção inteligente de tipos de campo:
  - **Texto**: campos gerais
  - **Texto Longo**: descrições
  - **Número**: preços, valores
  - **Data**: datas e timestamps
  - **E-mail**: campos de email
  - **URL**: links e URLs
  - **Telefone**: números de telefone
  - **Mídia**: imagens e arquivos

### 3. Compatibilidade com JetEngine
- Post Types criados no mesmo formato do JetEngine
- Meta fields totalmente compatíveis
- Pode ser gerenciado pelo JetEngine após criação

### 4. Compatibilidade com WooCommerce
- 100% compatível com todos os recursos do WooCommerce
- Suporte a taxonomias de produtos
- Integração com data stores do WooCommerce

## 📁 Estrutura do Plugin

```
eau-system/
├── eau-system.php                          # Arquivo principal
├── readme.txt                              # Documentação WordPress
├── LEIAME.md                              # Este arquivo
├── index.php                              # Segurança
│
├── includes/                              # Classes PHP
│   ├── class-eau-system.php              # Classe principal
│   ├── class-eau-admin.php               # Interface administrativa
│   ├── class-eau-csv-handler.php         # Manipulação de CSV
│   ├── class-eau-post-type-creator.php   # Criação de Post Types
│   ├── class-eau-woocommerce-compat.php  # Compatibilidade WooCommerce
│   ├── admin-page.php                    # Template da página admin
│   └── index.php                         # Segurança
│
└── assets/                               # Recursos front-end
    ├── css/
    │   ├── eau-admin.css                # Estilos administrativos
    │   └── index.php                    # Segurança
    └── js/
        ├── eau-admin.js                 # JavaScript administrativo
        └── index.php                    # Segurança
```

## 🚀 Como Usar

### Passo 1: Ativar o Plugin
1. Acesse **Plugins** no menu do WordPress
2. Localize **Eau System**
3. Clique em **Ativar**

### Passo 2: Acessar a Interface
1. No menu lateral do WordPress, clique em **Eau System**
2. Você verá a interface principal com 3 etapas

### Passo 3: Upload do CSV
1. Clique em **Escolher arquivo**
2. Selecione seu arquivo CSV
3. Clique em **Fazer Upload e Analisar**
4. Aguarde a análise automática

### Passo 4: Configurar o Post Type
1. Digite o **nome do Post Type** (ex: "Produtos", "Clientes")
2. Selecione as **colunas** que deseja incluir como campos
3. Visualize o **preview dos dados**
4. Clique em **Criar Post Type**

### Passo 5: Usar o Post Type
1. Após a criação, o Post Type aparecerá no menu do WordPress
2. Você pode criar, editar e gerenciar posts normalmente
3. Se tiver o JetEngine instalado, poderá gerenciar os campos por lá também

## 🔧 Requisitos Técnicos

- **WordPress:** 5.8 ou superior
- **PHP:** 7.4 ou superior
- **Memória PHP:** Mínimo 64MB (recomendado 128MB)
- **Extensões PHP:**
  - `fileinfo` (para validação de tipos de arquivo)
  - `mbstring` (para manipulação de strings)

## 🛡️ Segurança

O plugin implementa várias camadas de segurança:

- ✅ Verificação de nonces em todas as requisições AJAX
- ✅ Sanitização de todos os inputs do usuário
- ✅ Escape de outputs para prevenir XSS
- ✅ Validação de tipos de arquivo (MIME type)
- ✅ Limite de tamanho de arquivo (10MB)
- ✅ Verificação de permissões de usuário
- ✅ Arquivos index.php em todas as pastas

## 🔌 Compatibilidade

### Plugins Testados
- ✅ **WooCommerce** (todas as versões recentes)
- ✅ **JetEngine** (todas as versões)
- ✅ **Elementor** / **Elementor Pro**
- ✅ **JetSmartFilters**
- ✅ **Rank Math SEO**

### Temas Testados
- ✅ Qualquer tema WordPress padrão
- ✅ Temas que seguem os padrões do WordPress

## 🐛 Solução de Problemas

### O upload do CSV falha
- Verifique o tamanho do arquivo (máximo 10MB)
- Confirme que o arquivo é um CSV válido
- Verifique as permissões da pasta `wp-content/uploads/`

### O Post Type não aparece no menu
- Vá em **Configurações > Links Permanentes** e clique em **Salvar**
- Limpe o cache do site/navegador
- Verifique se o plugin está ativo

### Campos não aparecem no post
- Verifique se você selecionou as colunas na etapa 2
- Instale o JetEngine para melhor gerenciamento de campos

## 📝 Notas do Desenvolvedor

### Arquitetura
- **Padrão:** Orientação a objetos (OOP)
- **Namespace:** `EauSystem`
- **PSR-4:** Autoloading automático
- **WordPress Coding Standards:** Sim

### Hooks Disponíveis

O plugin não expõe hooks customizados na versão 1.0.0, mas utiliza os hooks padrão do WordPress.

### Banco de Dados

O plugin armazena configurações em:
- `wp_options` → `eau_system_post_types`
- `wp_options` → `jet_engine_post_types` (compatibilidade JetEngine)

## 🆘 Suporte

Para suporte, entre em contato com:
- **Email:** [seu-email]
- **Website:** https://platty.com.br

## 📄 Licença

Este plugin é licenciado sob a GPL v2 ou posterior.

```
Copyright (C) 2025 Platty / Rodrigo Zillesg

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.
```

## 🎉 Créditos

Desenvolvido por **Platty / Rodrigo Zillesg**

---

**Versão:** 1.0.0
**Última atualização:** 2025
