=== Eau System ===
Contributors: Platty, Rodrigo Zillesg
Tags: csv, import, post-type, jetengine, woocommerce
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.4.0
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
