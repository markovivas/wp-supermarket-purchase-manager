# Controle de Supermercado

Sistema de controle de compras de supermercado/loja para WordPress. Permite cadastrar produtos, registrar compras, buscar por código de barras (EAN) com integração à Open Food Facts, e importar/exportar dados em CSV.

## Requisitos
- PHP 8.0+
- WordPress 6.0+
- Permissão de administrador para acessar páginas do plugin e executar ações sensíveis

## Instalação
- Copie a pasta `wp-supermarket-purchase-manager` para `wp-content/plugins/`
- Ative o plugin em Plugins > Instalados
- Na primeira ativação, as tabelas são criadas e categorias/lojas padrão são inseridas

## Páginas do Admin
- Dashboard: visão geral, estatísticas e botão de reset
- Produtos: listar, adicionar, editar, importar CSV, exportar CSV
- Relatórios: filtros por período, categoria e loja, exportar CSV
- Categorias: gerenciar categorias
- Lojas: gerenciar lojas

## Shortcode
- Formulário de registro de compra:  
  `[product_registration]`
- Campos principais:
  - Buscar por Código de Barras: somente EAN válido (8–14 dígitos)
  - Nome do Produto, Categoria, Loja
  - Quantidade, Unidade, Preço Unitário, Total
  - Data da compra e observações

## Busca por Código de Barras
- Validação de EAN (8–14 dígitos) no front-end
- Busca local no banco por `barcode` com correspondência exata e parcial
- Integração com Open Food Facts para sugerir dados quando não houver cadastro local

## Importação CSV (Produtos)
- Formato esperado (ponto e vírgula “;” como separador):
  - Cabeçalho: `Nome;Categoria;Preço Padrão;Unidade Padrão;Código de Barras`
  - Preços aceitam `,` ou `.` como separador decimal (convertidos para ponto)
- Opção para atualizar produtos existentes via código de barras
- Acesso em Produtos > Importar CSV

## Exportação CSV
- Produtos: botão “Exportar CSV” na página de Produtos (gera `produtos_YYYY-mm-dd_HH-ii-ss.csv`)
- Relatórios: botão “Exportar CSV” na página de Relatórios, respeitando filtros (gera `compras_YYYY-mm-dd_HH-ii-ss.csv`)

## Botão de Reset
- Local: Dashboard > Resetar Plugin
- Ação: apaga todas as compras, produtos, categorias e lojas
- Após o reset, categorias e lojas padrão são recriadas
- Requer confirmação e nonce; acessível somente para administradores

## Segurança
- Ações administrativas exigem `manage_options`
- Nonces específicos em importação e exportação
- Sem armazenamento de segredos; não loga dados sensíveis

## Tabelas do Banco
- Prefixo: `{$wpdb->prefix}wpsgl_`
- `categories (id, name, created_at)`
- `stores (id, name, address, created_at)`
- `products (id, name, category_id, default_price, default_unit, barcode, created_at)`
- `purchases (id, product_id, category_id, store_id, quantity, unit, unit_price, total_price, purchase_date, purchase_time, notes, created_at)`

## Suporte
- Para ajustes ou novas funcionalidades (ex.: filtros adicionais na exportação, ou regras de segurança mais restritas), abra uma issue no repositório ou entre em contato com o mantenedor.
