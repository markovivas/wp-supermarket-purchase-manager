<?php
class WPSGL_Products_Page {
    
    private $database;
    
    public function __construct($database) {
        $this->database = $database;
        add_action('admin_menu', array($this, 'add_submenu_page'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_wpsgl_handle_product', array($this, 'handle_ajax'));
        add_action('wp_ajax_wpsgl_products_import_csv', array($this, 'handle_import_csv'));
        add_action('wp_ajax_wpsgl_products_export_csv', array($this, 'handle_export_csv'));
        // AJAX de diagnóstico para rodar a query (somente admin)
        add_action('wp_ajax_wpsgl_debug_run_query', array($this, 'handle_ajax_debug_run_query'));
        add_action('admin_post_wpsgl_save_product', array($this, 'handle_save'));
        add_action('admin_init', array($this, 'trigger_export_csv'));
    }
    
    public function add_submenu_page() {
        add_submenu_page(
            'wpsgl-dashboard',
            __('Produtos', 'wpsgl'),
            __('Produtos', 'wpsgl'),
            'manage_options',
            'wpsgl-products',
            array($this, 'render_page')
        );
    }
    
    public function enqueue_scripts($hook) {
        // Carregar apenas na página de produtos
        if (strpos($hook, 'wpsgl-products') !== false) {
            wp_enqueue_style('wpsgl-admin', WPSGL_PLUGIN_URL . 'admin/assets/css/admin-style.css', array(), WPSGL_VERSION);
            wp_enqueue_script('wpsgl-admin-script', WPSGL_PLUGIN_URL . 'admin/assets/js/admin-script.js', array('jquery'), WPSGL_VERSION, true);
            
            wp_localize_script('wpsgl-admin-script', 'wpsgl_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wpsgl_products_nonce')
            ));
        }
    }
    
    public function render_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Você não tem permissão para acessar esta página.', 'wpsgl'));
        }
        
        // Processar ações
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        if ($action === 'edit' && $id > 0) {
            $this->render_edit_form($id);
            return;
        }
        
        if ($action === 'add') {
            $this->render_add_form();
            return;
        }
        
        if ($action === 'delete' && $id > 0) {
            $this->handle_delete($id);
            return;
        }
        
        // Listar produtos
        $this->render_list();
    }
    
    private function render_list() {
        // Parâmetros de paginação e filtro
        $per_page = 20;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($current_page - 1) * $per_page;
        
        global $wpdb;
        $prefix = $wpdb->prefix . 'wpsgl_';
        
        // Construir query base
        $where = array('1=1');
        $query_params = array();
        
        // Filtro de busca
        if (!empty($_GET['s'])) {
            $search = sanitize_text_field($_GET['s']);
            $where[] = "(p.name LIKE %s OR p.barcode LIKE %s)";
            $query_params[] = '%' . $wpdb->esc_like($search) . '%';
            $query_params[] = '%' . $wpdb->esc_like($search) . '%';
        }
        
        // Filtro por categoria
        if (!empty($_GET['category_id'])) {
            $where[] = "p.category_id = %d";
            $query_params[] = intval($_GET['category_id']);
        }
        
        $where_clause = implode(' AND ', $where);
        
        // Contar total
        $count_query = "SELECT COUNT(*) FROM {$prefix}products p WHERE {$where_clause}";
        if (!empty($query_params)) {
            $count_query = $wpdb->prepare($count_query, $query_params);
        }
        $total_items = $wpdb->get_var($count_query);
        $total_pages = ceil($total_items / $per_page);
        
        // Obter produtos
        $query = "SELECT p.*, c.name as category_name 
                  FROM {$prefix}products p 
                  LEFT JOIN {$prefix}categories c ON p.category_id = c.id 
                  WHERE {$where_clause} 
                  ORDER BY p.id DESC 
                  LIMIT %d OFFSET %d";
        
        $query_params[] = $per_page;
        $query_params[] = $offset;
        
        $query = $wpdb->prepare($query, $query_params);
        $products = $wpdb->get_results($query);
        
        $categories = $this->database->get_categories();
        
        ?>
        <div class="wrap wpsgl-products">
            <h1 class="wp-heading-inline"><?php _e('Produtos', 'wpsgl'); ?></h1>
            <a href="<?php echo esc_url(admin_url('admin.php?page=wpsgl-products&action=add')); ?>" class="page-title-action">
                <?php _e('Adicionar Produto', 'wpsgl'); ?>
            </a>
            
            <?php if (isset($_GET['message'])): ?>
                <?php
                $messages = array(
                    'added' => __('Produto adicionado com sucesso!', 'wpsgl'),
                    'updated' => __('Produto atualizado com sucesso!', 'wpsgl'),
                    'deleted' => __('Produto excluído com sucesso!', 'wpsgl'),
                    'error' => __('Erro ao processar o produto.', 'wpsgl')
                );
                $message_type = isset($_GET['error']) ? 'error' : 'success';
                ?>
                <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible">
                    <p><?php echo esc_html($messages[$_GET['message']] ?? 'Operação concluída.'); ?></p>
                </div>
            <?php endif; ?>
            
            <div class="wpsgl-filters">
                <form method="get" action="">
                    <input type="hidden" name="page" value="wpsgl-products">
                    
                    <div class="wpsgl-filter-row">
                        <div class="wpsgl-filter-group">
                            <label for="s"><?php _e('Buscar:', 'wpsgl'); ?></label>
                            <input type="search" id="s" name="s" value="<?php echo isset($_GET['s']) ? esc_attr($_GET['s']) : ''; ?>" 
                                   placeholder="<?php esc_attr_e('Nome ou código de barras...', 'wpsgl'); ?>">
                        </div>
                        
                        <div class="wpsgl-filter-group">
                            <label for="category_id"><?php _e('Categoria:', 'wpsgl'); ?></label>
                            <select id="category_id" name="category_id">
                                <option value=""><?php _e('Todas as categorias', 'wpsgl'); ?></option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo esc_attr($category->id); ?>" <?php selected(isset($_GET['category_id']) ? $_GET['category_id'] : '', $category->id); ?>>
                                        <?php echo esc_html($category->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="wpsgl-filter-group">
                            <label>&nbsp;</label>
                            <button type="submit" class="button"><?php _e('Filtrar', 'wpsgl'); ?></button>
                            <?php if (!empty($_GET['s']) || !empty($_GET['category_id'])): ?>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=wpsgl-products')); ?>" class="button">
                                    <?php _e('Limpar', 'wpsgl'); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="wpsgl-actions-bar">
                <div class="wpsgl-bulk-actions">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=wpsgl-products&export=csv')); ?>" class="button button-secondary">
                        <i class="dashicons dashicons-download"></i> <?php _e('Exportar CSV', 'wpsgl'); ?>
                    </a>
                    <button type="button" class="button button-secondary" id="wpsgl-import-csv-btn">
                        <i class="dashicons dashicons-upload"></i> <?php _e('Importar CSV', 'wpsgl'); ?>
                    </button>
                    <button type="button" class="button button-secondary" id="wpsgl-debug-btn" data-modal="wpsgl-debug-modal">
                        <i class="dashicons dashicons-search"></i> <?php _e('Diagnóstico de Busca', 'wpsgl'); ?>
                    </button>
                </div>
            </div>
            
            <?php if (empty($products)): ?>
                <div class="wpsgl-no-results">
                    <div class="wpsgl-no-results-icon">
                        <i class="dashicons dashicons-products"></i>
                    </div>
                    <h3><?php _e('Nenhum produto encontrado', 'wpsgl'); ?></h3>
                    <p><?php _e('Não há produtos cadastrados ou os filtros não retornaram resultados.', 'wpsgl'); ?></p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=wpsgl-products&action=add')); ?>" class="button button-primary">
                        <?php _e('Adicionar Primeiro Produto', 'wpsgl'); ?>
                    </a>
                </div>
            <?php else: ?>
                <div class="wpsgl-table-container">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th scope="col" class="column-id">ID</th>
                                <th scope="col" class="column-name"><?php _e('Nome', 'wpsgl'); ?></th>
                                <th scope="col" class="column-category"><?php _e('Categoria', 'wpsgl'); ?></th>
                                <th scope="col" class="column-price"><?php _e('Preço Padrão', 'wpsgl'); ?></th>
                                <th scope="col" class="column-unit"><?php _e('Unidade Padrão', 'wpsgl'); ?></th>
                                <th scope="col" class="column-barcode"><?php _e('Código de Barras', 'wpsgl'); ?></th>
                                <th scope="col" class="column-actions"><?php _e('Ações', 'wpsgl'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product): ?>
                                <tr>
                                    <td class="column-id"><?php echo esc_html($product->id); ?></td>
                                    <td class="column-name">
                                        <strong><?php echo esc_html($product->name); ?></strong>
                                    </td>
                                    <td class="column-category"><?php echo esc_html($product->category_name); ?></td>
                                    <td class="column-price">R$ <?php echo number_format($product->default_price, 2, ',', '.'); ?></td>
                                    <td class="column-unit"><?php echo esc_html($product->default_unit); ?></td>
                                    <td class="column-barcode"><?php echo esc_html($product->barcode); ?></td>
                                    <td class="column-actions">
                                        <div class="row-actions">
                                            <span class="edit">
                                                <a href="<?php echo esc_url(add_query_arg(array('action' => 'edit', 'id' => $product->id))); ?>" 
                                                   class="button button-small">
                                                    <?php _e('Editar', 'wpsgl'); ?>
                                                </a>
                                            </span>
                                            <span class="delete">
                                                <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(array('action' => 'delete', 'id' => $product->id)), 'delete_product_' . $product->id)); ?>" 
                                                   class="button button-small button-link-delete" 
                                                   onclick="return confirm('<?php esc_attr_e('Tem certeza que deseja excluir este produto?', 'wpsgl'); ?>');">
                                                    <?php _e('Excluir', 'wpsgl'); ?>
                                                </a>
                                            </span>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if ($total_pages > 1): ?>
                    <div class="wpsgl-pagination">
                        <div class="tablenav-pages">
                            <span class="displaying-num">
                                <?php printf(_n('%d produto', '%d produtos', $total_items, 'wpsgl'), $total_items); ?>
                            </span>
                            <span class="pagination-links">
                                <?php
                                $page_links = paginate_links(array(
                                    'base' => add_query_arg('paged', '%#%'),
                                    'format' => '',
                                    'prev_text' => __('&laquo; Anterior', 'wpsgl'),
                                    'next_text' => __('Próxima &raquo;', 'wpsgl'),
                                    'total' => $total_pages,
                                    'current' => $current_page,
                                    'show_all' => false,
                                    'end_size' => 1,
                                    'mid_size' => 2,
                                    'type' => 'plain',
                                    'add_args' => array(
                                        's' => isset($_GET['s']) ? $_GET['s'] : '',
                                        'category_id' => isset($_GET['category_id']) ? $_GET['category_id'] : ''
                                    )
                                ));
                                
                                if ($page_links) {
                                    echo $page_links;
                                }
                                ?>
                            </span>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <!-- Modal de Importação CSV -->
            <div id="wpsgl-import-modal" class="wpsgl-modal" style="display: none;">
                <div class="wpsgl-modal-content">
                    <div class="wpsgl-modal-header">
                        <h3><?php _e('Importar Produtos via CSV', 'wpsgl'); ?></h3>
                        <button type="button" class="wpsgl-modal-close">&times;</button>
                    </div>
                    <div class="wpsgl-modal-body">
                        <form id="wpsgl-import-csv-form" enctype="multipart/form-data">
                            <?php wp_nonce_field('wpsgl_import_csv', 'wpsgl_nonce'); ?>
                            
                            <div class="wpsgl-form-group">
                                <label for="csv_file"><?php _e('Arquivo CSV:', 'wpsgl'); ?> *</label>
                                <input type="file" id="csv_file" name="csv_file" accept=".csv" required>
                                <p class="description">
                                    <?php _e('O arquivo deve estar no formato CSV com os seguintes cabeçalhos:', 'wpsgl'); ?><br>
                                    <code>Nome, Categoria, Preço Padrão, Unidade Padrão, Código de Barras</code>
                                </p>
                            </div>
                            
                            <div class="wpsgl-form-group">
                                <label>
                                    <input type="checkbox" name="update_existing" value="1">
                                    <?php _e('Atualizar produtos existentes (por código de barras)', 'wpsgl'); ?>
                                </label>
                            </div>
                            
                            <div class="wpsgl-form-actions">
                                <button type="submit" class="button button-primary">
                                    <?php _e('Importar', 'wpsgl'); ?>
                                </button>
                                <button type="button" class="button button-secondary wpsgl-modal-close">
                                    <?php _e('Cancelar', 'wpsgl'); ?>
                                </button>
                            </div>
                        </form>
                        
                        <div id="wpsgl-import-result" style="display: none;"></div>
                    </div>
                </div>
            </div>

            <!-- Modal de Diagnóstico -->
            <div id="wpsgl-debug-modal" class="wpsgl-modal" style="display: none;">
                <div class="wpsgl-modal-content">
                    <div class="wpsgl-modal-header">
                        <h3><?php _e('Diagnóstico de Busca', 'wpsgl'); ?></h3>
                        <button type="button" class="wpsgl-modal-close">&times;</button>
                    </div>
                    <div class="wpsgl-modal-body">
                        <p><?php _e('Use este painel para inspecionar a query de busca e executar a mesma contra o banco (somente administradores).', 'wpsgl'); ?></p>
                        <p style="color:#666; font-size:13px;"><em><?php _e('Observação: para gerar o log, execute a busca na página pública enquanto estiver logado como administrador (o AJAX de busca incluirá debug automaticamente). Em seguida clique em "Mostrar Último Log".', 'wpsgl'); ?></em></p>

                        <div class="wpsgl-form-group">
                            <label for="wpsgl-debug-term"><?php _e('Termo de busca:', 'wpsgl'); ?></label>
                            <input type="text" id="wpsgl-debug-term" class="regular-text">

                        <div class="wpsgl-form-actions">
                            <button type="button" class="button button-primary" id="wpsgl-debug-show-query"><?php _e('Mostrar Query', 'wpsgl'); ?></button>
                            <button type="button" class="button button-secondary" id="wpsgl-debug-run-query"><?php _e('Executar Query', 'wpsgl'); ?></button>
                            <button type="button" class="button" id="wpsgl-debug-show-log"><?php _e('Mostrar Último Log', 'wpsgl'); ?></button>
                    </div>
                    <div class="wpsgl-modal-footer">
                        <button type="button" class="button button-secondary wpsgl-modal-close"><?php _e('Fechar', 'wpsgl'); ?></button>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Abrir modal de importação
            $('#wpsgl-import-csv-btn').on('click', function() {
                $('#wpsgl-import-modal').show();
            });
            
            // Fechar modal
            $('.wpsgl-modal-close').on('click', function() {
                $(this).closest('.wpsgl-modal').hide();
            });
            
            // Fechar modal ao clicar fora
            $(document).on('click', function(e) {
                if ($(e.target).hasClass('wpsgl-modal')) {
                    $(e.target).hide();
                }
            });
            
            // Enviar formulário de importação
            $('#wpsgl-import-csv-form').on('submit', function(e) {
                e.preventDefault();
                
                var form = $(this);
                var submitBtn = form.find('button[type="submit"]');
                var originalText = submitBtn.text();
                var formData = new FormData(this);
                
                formData.append('action', 'wpsgl_products_import_csv');
                formData.append('nonce', wpsgl_ajax.nonce);
                
                // Desabilitar botão
                submitBtn.prop('disabled', true).text('<?php _e('Importando...', 'wpsgl'); ?>');
                
                $.ajax({
                    url: wpsgl_ajax.ajax_url,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            $('#wpsgl-import-result').html(
                                '<div class="notice notice-success">' +
                                '<p>' + response.message + '</p>' +
                                '<p><strong>' + response.imported + '</strong> produtos importados, ' +
                                '<strong>' + response.updated + '</strong> atualizados, ' +
                                '<strong>' + response.skipped + '</strong> ignorados.</p>' +
                                '</div>'
                            ).show();
                            
                            // Atualizar a página após 2 segundos
                            setTimeout(function() {
                                window.location.reload();
                            }, 2000);
                        } else {
                            $('#wpsgl-import-result').html(
                                '<div class="notice notice-error">' +
                                '<p>' + response.message + '</p>' +
                                '</div>'
                            ).show();
                            submitBtn.prop('disabled', false).text(originalText);
                        }
                    },
                    error: function() {
                        $('#wpsgl-import-result').html(
                            '<div class="notice notice-error">' +
                            '<p>Erro na importação. Tente novamente.</p>' +
                            '</div>'
                        ).show();
                        submitBtn.prop('disabled', false).text(originalText);
                    }
                });
            });

            // Debug: modal de diagnóstico
            $('#wpsgl-debug-btn').on('click', function() {
                $('#wpsgl-debug-term').val($('#s').val() || '');
                $('#wpsgl-debug-output').hide().html('');
                $('#wpsgl-debug-modal').show();
            });

            function _wpsgl_escape_html(s) {
                return $('<div>').text(s).html();
            }

            $('#wpsgl-debug-show-query').on('click', function() {
                var btn = $(this);
                var term = $('#wpsgl-debug-term').val();
                btn.prop('disabled', true);
                $.ajax({
                    url: wpsgl_ajax.ajax_url,
                    type: 'GET',
                    data: {
                        action: 'wpsgl_debug_search_query',
                        term: term
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#wpsgl-debug-output').show().html('<pre style="white-space:pre-wrap;">' + _wpsgl_escape_html(response.query) + '</pre>');
                        } else {
                            $('#wpsgl-debug-output').show().html('<div class="notice notice-error"><p>' + _wpsgl_escape_html(response.message || 'Erro') + '</p></div>');
                        }
                    },
                    complete: function() { btn.prop('disabled', false); }
                });
            });

            $('#wpsgl-debug-run-query').on('click', function() {
                var btn = $(this);
                var term = $('#wpsgl-debug-term').val();
                btn.prop('disabled', true);
                $.ajax({
                    url: wpsgl_ajax.ajax_url,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'wpsgl_debug_run_query',
                        term: term,
                        nonce: wpsgl_ajax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            var html = '<h4>Query</h4><pre style="white-space:pre-wrap;">' + _wpsgl_escape_html(response.query) + '</pre>';
                            html += '<h4>Resultados (' + response.count + ')</h4>';
                            if (response.results && response.results.length) {
                                html += '<table class="widefat"><thead><tr><th>ID</th><th>Nome</th><th>Categoria</th><th>Preço</th><th>Código</th></tr></thead><tbody>';
                                response.results.forEach(function(r) {
                                    html += '<tr><td>' + (r.id || '') + '</td><td>' + _wpsgl_escape_html(r.name || '') + '</td><td>' + _wpsgl_escape_html(r.category_name || '') + '</td><td>' + (r.default_price !== undefined ? r.default_price : '') + '</td><td>' + _wpsgl_escape_html(r.barcode || '') + '</td></tr>';
                                });
                                html += '</tbody></table>';
                            } else {
                                html += '<p>Nenhum resultado.</p>';
                            }
                            $('#wpsgl-debug-output').show().html(html);
                        } else {
                            $('#wpsgl-debug-output').show().html('<div class="notice notice-error"><p>' + _wpsgl_escape_html(response.message || 'Erro') + '</p></div>');
                        }
                    },
                    error: function() {
                        $('#wpsgl-debug-output').show().html('<div class="notice notice-error"><p>Erro na requisição.</p></div>');
                    },
                    complete: function() { btn.prop('disabled', false); }
                });
            });

            $('#wpsgl-debug-show-log').on('click', function() {
                var btn = $(this);
                btn.prop('disabled', true);
                $.ajax({
                    url: wpsgl_ajax.ajax_url,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'wpsgl_get_search_debug_log',
                        nonce: wpsgl_ajax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#wpsgl-debug-output').show().html('<h4>Último Log</h4><pre style="white-space:pre-wrap;">' + _wpsgl_escape_html(response.log) + '</pre>');
                        } else {
                            $('#wpsgl-debug-output').show().html('<div class="notice notice-error"><p>' + _wpsgl_escape_html(response.message || 'Erro') + '</p></div>');
                        }
                    },
                    error: function() {
                        $('#wpsgl-debug-output').show().html('<div class="notice notice-error"><p>Erro ao obter log.</p></div>');
                    },
                    complete: function() { btn.prop('disabled', false); }
                });
            });
        });
        </script>
        <?php
    }
    
    private function render_add_form() {
        $categories = $this->database->get_categories();
        ?>
        <div class="wrap wpsgl-products">
            <h1><?php _e('Adicionar Novo Produto', 'wpsgl'); ?></h1>
            
            <div class="wpsgl-form-container">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="wpsgl_save_product">
                    <?php wp_nonce_field('wpsgl_save_product', 'wpsgl_nonce'); ?>
                    
                    <div class="wpsgl-form-group">
                        <label for="name"><?php _e('Nome do Produto:', 'wpsgl'); ?> *</label>
                        <input type="text" id="name" name="name" required 
                               placeholder="<?php esc_attr_e('Ex: Arroz Tipo 1, Feijão Carioca, etc.', 'wpsgl'); ?>">
                    </div>
                    
                    <div class="wpsgl-form-group">
                        <label for="category_id"><?php _e('Categoria:', 'wpsgl'); ?> *</label>
                        <select id="category_id" name="category_id" required>
                            <option value=""><?php _e('Selecione uma categoria...', 'wpsgl'); ?></option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo esc_attr($category->id); ?>">
                                    <?php echo esc_html($category->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="wpsgl-form-row">
                        <div class="wpsgl-form-group">
                            <label for="default_price"><?php _e('Preço Padrão (R$):', 'wpsgl'); ?></label>
                            <input type="number" id="default_price" name="default_price" step="0.01" min="0" value="0">
                        </div>
                        
                        <div class="wpsgl-form-group">
                            <label for="default_unit"><?php _e('Unidade Padrão:', 'wpsgl'); ?></label>
                            <select id="default_unit" name="default_unit">
                                <option value="un"><?php _e('Unidade', 'wpsgl'); ?></option>
                                <option value="kg"><?php _e('Kg', 'wpsgl'); ?></option>
                                <option value="g"><?php _e('Gramas', 'wpsgl'); ?></option>
                                <option value="l"><?php _e('Litro', 'wpsgl'); ?></option>
                                <option value="ml"><?php _e('ML', 'wpsgl'); ?></option>
                                <option value="cx"><?php _e('Caixa', 'wpsgl'); ?></option>
                                <option value="pct"><?php _e('Pacote', 'wpsgl'); ?></option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="wpsgl-form-group">
                        <label for="barcode"><?php _e('Código de Barras:', 'wpsgl'); ?></label>
                        <input type="text" id="barcode" name="barcode" 
                               placeholder="<?php esc_attr_e('Digite ou escaneie o código de barras', 'wpsgl'); ?>">
                        <button type="button" class="button button-small" id="generate-barcode">
                            <?php _e('Gerar Código', 'wpsgl'); ?>
                        </button>
                    </div>
                    
                    <div class="wpsgl-form-actions">
                        <button type="submit" class="button button-primary">
                            <?php _e('Salvar Produto', 'wpsgl'); ?>
                        </button>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=wpsgl-products')); ?>" class="button">
                            <?php _e('Cancelar', 'wpsgl'); ?>
                        </a>
                    </div>
                </form>
            </div>
            
            <script>
            jQuery(document).ready(function($) {
                // Gerar código de barras
                $('#generate-barcode').on('click', function() {
                    $.ajax({
                        url: wpsgl_ajax.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'wpsgl_generate_barcode',
                            nonce: wpsgl_ajax.nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                $('#barcode').val(response.barcode);
                            }
                        }
                    });
                });
            });
            </script>
        </div>
        <?php
    }
    
    private function render_edit_form($id) {
        global $wpdb;
        $prefix = $wpdb->prefix . 'wpsgl_';
        
        $product = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$prefix}products WHERE id = %d",
            $id
        ));
        
        if (!$product) {
            wp_die(__('Produto não encontrado.', 'wpsgl'));
        }
        
        $categories = $this->database->get_categories();
        ?>
        <div class="wrap wpsgl-products">
            <h1><?php _e('Editar Produto', 'wpsgl'); ?></h1>
            
            <div class="wpsgl-form-container">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="wpsgl_save_product">
                    <input type="hidden" name="product_id" value="<?php echo esc_attr($id); ?>">
                    <?php wp_nonce_field('wpsgl_save_product', 'wpsgl_nonce'); ?>
                    
                    <div class="wpsgl-form-group">
                        <label for="name"><?php _e('Nome do Produto:', 'wpsgl'); ?> *</label>
                        <input type="text" id="name" name="name" 
                               value="<?php echo esc_attr($product->name); ?>" required>
                    </div>
                    
                    <div class="wpsgl-form-group">
                        <label for="category_id"><?php _e('Categoria:', 'wpsgl'); ?> *</label>
                        <select id="category_id" name="category_id" required>
                            <option value=""><?php _e('Selecione uma categoria...', 'wpsgl'); ?></option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo esc_attr($category->id); ?>" <?php selected($product->category_id, $category->id); ?>>
                                    <?php echo esc_html($category->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="wpsgl-form-row">
                        <div class="wpsgl-form-group">
                            <label for="default_price"><?php _e('Preço Padrão (R$):', 'wpsgl'); ?></label>
                            <input type="number" id="default_price" name="default_price" step="0.01" min="0" 
                                   value="<?php echo esc_attr($product->default_price); ?>">
                        </div>
                        
                        <div class="wpsgl-form-group">
                            <label for="default_unit"><?php _e('Unidade Padrão:', 'wpsgl'); ?></label>
                            <select id="default_unit" name="default_unit">
                                <option value="un" <?php selected($product->default_unit, 'un'); ?>><?php _e('Unidade', 'wpsgl'); ?></option>
                                <option value="kg" <?php selected($product->default_unit, 'kg'); ?>><?php _e('Kg', 'wpsgl'); ?></option>
                                <option value="g" <?php selected($product->default_unit, 'g'); ?>><?php _e('Gramas', 'wpsgl'); ?></option>
                                <option value="l" <?php selected($product->default_unit, 'l'); ?>><?php _e('Litro', 'wpsgl'); ?></option>
                                <option value="ml" <?php selected($product->default_unit, 'ml'); ?>><?php _e('ML', 'wpsgl'); ?></option>
                                <option value="cx" <?php selected($product->default_unit, 'cx'); ?>><?php _e('Caixa', 'wpsgl'); ?></option>
                                <option value="pct" <?php selected($product->default_unit, 'pct'); ?>><?php _e('Pacote', 'wpsgl'); ?></option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="wpsgl-form-group">
                        <label for="barcode"><?php _e('Código de Barras:', 'wpsgl'); ?></label>
                        <input type="text" id="barcode" name="barcode" 
                               value="<?php echo esc_attr($product->barcode); ?>">
                    </div>
                    
                    <div class="wpsgl-form-actions">
                        <button type="submit" class="button button-primary">
                            <?php _e('Atualizar Produto', 'wpsgl'); ?>
                        </button>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=wpsgl-products')); ?>" class="button">
                            <?php _e('Cancelar', 'wpsgl'); ?>
                        </a>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }
    
    public function handle_save() {
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['wpsgl_nonce'], 'wpsgl_save_product')) {
            wp_die(__('Acesso não autorizado.', 'wpsgl'));
        }
        
        global $wpdb;
        $prefix = $wpdb->prefix . 'wpsgl_';
        
        $name = sanitize_text_field($_POST['name']);
        $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $default_price = isset($_POST['default_price']) ? floatval($_POST['default_price']) : 0;
        $default_unit = isset($_POST['default_unit']) ? sanitize_text_field($_POST['default_unit']) : 'un';
        $barcode = isset($_POST['barcode']) ? sanitize_text_field($_POST['barcode']) : '';
        
        if (empty($name) || empty($category_id)) {
            wp_die(__('Nome e Categoria são obrigatórios.', 'wpsgl'));
        }
        
        $data = array(
            'name' => $name,
            'category_id' => $category_id,
            'default_price' => $default_price,
            'default_unit' => $default_unit,
            'barcode' => $barcode
        );
        
        if ($product_id > 0) {
            $wpdb->update($prefix . 'products', $data, array('id' => $product_id));
            $message = 'updated';
        } else {
            $wpdb->insert($prefix . 'products', $data);
            $message = 'added';
        }
        
        wp_redirect(add_query_arg(array(
            'page' => 'wpsgl-products',
            'message' => $message
        ), admin_url('admin.php')));
        exit;
    }
    
    private function handle_delete($id) {
        if (!current_user_can('manage_options') || !wp_verify_nonce($_GET['_wpnonce'], 'delete_product_' . $id)) {
            wp_die(__('Acesso não autorizado.', 'wpsgl'));
        }
        
        $result = $this->database->delete_product($id);
        
        $message = $result ? 'deleted' : 'error';
        
        wp_redirect(add_query_arg(array(
            'page' => 'wpsgl-products',
            'message' => $message
        ), admin_url('admin.php')));
        exit;
    }
    
    public function handle_ajax() {
        // Implementar ações AJAX específicas para produtos
        wp_die();
    }
    
    public function trigger_export_csv() {
        if (
            isset($_GET['page']) &&
            $_GET['page'] === 'wpsgl-products' &&
            isset($_GET['export']) &&
            $_GET['export'] === 'csv'
        ) {
            $this->handle_export_csv();
        }
    }
    
    public function handle_import_csv() {
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'], 'wpsgl_products_nonce')) {
            wp_die(json_encode(array(
                'success' => false,
                'message' => __('Acesso não autorizado.', 'wpsgl')
            )));
        }
        
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            wp_die(json_encode(array(
                'success' => false,
                'message' => __('Erro no upload do arquivo.', 'wpsgl')
            )));
        }
        
        $file = $_FILES['csv_file']['tmp_name'];
        $update_existing = isset($_POST['update_existing']) && $_POST['update_existing'] == '1';
        
        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $errors = array();
        
        if (($handle = fopen($file, 'r')) !== false) {
            // Pular cabeçalho
            fgetcsv($handle);
            
            while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                if (count($data) < 5) {
                    $skipped++;
                    continue;
                }
                
                $name = sanitize_text_field($data[0]);
                $category_name = sanitize_text_field($data[1]);
                $price = floatval(str_replace(',', '.', $data[2]));
                $unit = sanitize_text_field($data[3]);
                $barcode = sanitize_text_field($data[4]);
                
                if (empty($name) || empty($category_name)) {
                    $skipped++;
                    continue;
                }
                
                // Encontrar ou criar categoria
                global $wpdb;
                $prefix = $wpdb->prefix . 'wpsgl_';
                
                $category_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$prefix}categories WHERE name = %s",
                    $category_name
                ));
                
                if (!$category_id) {
                    $wpdb->insert($prefix . 'categories', array('name' => $category_name));
                    $category_id = $wpdb->insert_id;
                }
                
                // Verificar se produto já existe
                $existing = false;
                if (!empty($barcode)) {
                    $existing = $wpdb->get_row($wpdb->prepare(
                        "SELECT id FROM {$prefix}products WHERE barcode = %s",
                        $barcode
                    ));
                }
                
                if (!$existing) {
                    $existing = $wpdb->get_row($wpdb->prepare(
                        "SELECT id FROM {$prefix}products WHERE name = %s AND category_id = %d",
                        $name, $category_id
                    ));
                }
                
                $product_data = array(
                    'name' => $name,
                    'category_id' => $category_id,
                    'default_price' => $price,
                    'default_unit' => $unit,
                    'barcode' => $barcode
                );
                
                if ($existing && $update_existing) {
                    // Atualizar produto existente
                    $wpdb->update(
                        $prefix . 'products',
                        $product_data,
                        array('id' => $existing->id)
                    );
                    $updated++;
                } elseif (!$existing) {
                    // Inserir novo produto
                    $wpdb->insert($prefix . 'products', $product_data);
                    $imported++;
                } else {
                    $skipped++;
                }
            }
            
            fclose($handle);
        }
        
        wp_die(json_encode(array(
            'success' => true,
            'message' => __('Importação concluída com sucesso!', 'wpsgl'),
            'imported' => $imported,
            'updated' => $updated,
            'skipped' => $skipped
        )));
    }

    /**
     * AJAX handler para rodar a query de busca (diagnóstico)
     */
    public function handle_ajax_debug_run_query() {
        if (!current_user_can('manage_options') || !isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wpsgl_products_nonce')) {
            wp_send_json(array('success' => false, 'message' => __('Acesso não autorizado.', 'wpsgl')));
        }

        $term = isset($_POST['term']) ? sanitize_text_field($_POST['term']) : '';

        if (!method_exists($this->database, 'get_search_products_query')) {
            wp_send_json(array('success' => false, 'message' => __('Método de diagnóstico indisponível.', 'wpsgl')));
        }

        $query = $this->database->get_search_products_query($term);
        $results = $this->database->get_search_products_results($term);

        // Limitar a quantidade de linhas retornadas para evitar payloads enormes
        $max = 50;
        if (count($results) > $max) {
            $results = array_slice($results, 0, $max);
        }

        wp_send_json(array(
            'success' => true,
            'term' => $term,
            'query' => $query,
            'count' => count($results),
            'results' => $results
        ));
    }

    public function handle_export_csv() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Acesso não autorizado.', 'wpsgl'));
        }
        
        global $wpdb;
        $prefix = $wpdb->prefix . 'wpsgl_';
        
        $products = $wpdb->get_results("
            SELECT p.*, c.name as category_name 
            FROM {$prefix}products p 
            LEFT JOIN {$prefix}categories c ON p.category_id = c.id 
            ORDER BY p.name ASC
        ");
        
        $filename = 'produtos_' . date('Y-m-d_H-i-s') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        
        $output = fopen('php://output', 'w');
        
        // Cabeçalho
        fputcsv($output, array(
            'Nome',
            'Categoria',
            'Preço Padrão',
            'Unidade Padrão',
            'Código de Barras'
        ), ';');
        
        // Dados
        foreach ($products as $product) {
            fputcsv($output, array(
                $product->name,
                $product->category_name,
                number_format($product->default_price, 2, ',', '.'),
                $product->default_unit,
                $product->barcode
            ), ';');
        }
        
        fclose($output);
        exit;
    }
}
