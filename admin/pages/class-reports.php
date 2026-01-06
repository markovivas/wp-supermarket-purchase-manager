<?php
class WPSGL_Reports_Page {
    
    private $database;
    
    public function __construct($database) {
        $this->database = $database;
        add_action('admin_menu', array($this, 'add_submenu_page'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_wpsgl_reports_export_csv', array($this, 'handle_export_csv'));
        add_action('wp_ajax_wpsgl_export_csv', array($this, 'handle_export_csv'));
        add_action('wp_ajax_wpsgl_reports_import_csv', array($this, 'handle_import_csv'));
        add_action('admin_init', array($this, 'trigger_export_csv'));
    }
    
    public function add_submenu_page() {
        add_submenu_page(
            'wpsgl-dashboard',
            __('Relatórios de Compras', 'wpsgl'),
            __('Relatórios', 'wpsgl'),
            'manage_options',
            'wpsgl-reports',
            array($this, 'render_page')
        );
    }
    
    public function enqueue_scripts($hook) {
        if (strpos($hook, 'wpsgl-reports') !== false) {
            wp_enqueue_style('wpsgl-admin', WPSGL_PLUGIN_URL . 'admin/assets/css/admin-style.css', array(), WPSGL_VERSION);
            wp_enqueue_script('wpsgl-reports', WPSGL_PLUGIN_URL . 'admin/assets/js/admin-script.js', array('jquery'), WPSGL_VERSION, true);
            
            wp_localize_script('wpsgl-reports', 'wpsgl_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wpsgl_reports_nonce')
            ));
        }
    }
    
    public function render_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Você não tem permissão para acessar esta página.', 'wpsgl'));
        }
        
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if ($action === 'delete_purchase' && $id > 0) {
            if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'delete_purchase_' . $id)) {
                wp_die(__('Acesso não autorizado.', 'wpsgl'));
            }
            $result = $this->database->delete_purchase($id);
            wp_redirect(add_query_arg(array(
                'page' => 'wpsgl-reports',
                'message' => $result ? 'purchase_deleted' : 'error'
            ), admin_url('admin.php')));
            exit;
        }
        
        // Processar filtros
        $filters = array(
            'start_date' => isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : date('Y-m-01'),
            'end_date' => isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : date('Y-m-d'),
            'category_id' => isset($_GET['category_id']) ? intval($_GET['category_id']) : 0,
            'store_id' => isset($_GET['store_id']) ? intval($_GET['store_id']) : 0
        );
        
        $purchases = $this->database->get_purchases_report($filters);
        $categories = $this->database->get_categories();
        $stores = $this->database->get_stores();
        
        // Calcular estatísticas
        $total_items = count($purchases);
        $total_spent = array_sum(array_column($purchases, 'total_price'));
        $avg_per_item = $total_items > 0 ? $total_spent / $total_items : 0;
        
        ?>
        <div class="wrap wpsgl-reports">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <?php if (isset($_GET['message'])): ?>
                <?php
                $messages = array(
                    'purchase_deleted' => __('Compra excluída com sucesso!', 'wpsgl'),
                    'error' => __('Erro ao excluir.', 'wpsgl')
                );
                $message_type = $_GET['message'] === 'error' ? 'error' : 'success';
                ?>
                <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible">
                    <p><?php echo esc_html($messages[$_GET['message']] ?? 'Operação concluída.'); ?></p>
                </div>
            <?php endif; ?>
            
            <!-- Filtros -->
            <div class="wpsgl-filters">
                <form method="get" action="">
                    <input type="hidden" name="page" value="wpsgl-reports">
                    
                    <div class="wpsgl-filter-row">
                        <div class="wpsgl-filter-group">
                            <label for="start_date"><?php _e('Data Inicial:', 'wpsgl'); ?></label>
                            <input type="date" id="start_date" name="start_date" value="<?php echo esc_attr($filters['start_date']); ?>">
                        </div>
                        
                        <div class="wpsgl-filter-group">
                            <label for="end_date"><?php _e('Data Final:', 'wpsgl'); ?></label>
                            <input type="date" id="end_date" name="end_date" value="<?php echo esc_attr($filters['end_date']); ?>">
                        </div>
                        
                        <div class="wpsgl-filter-group">
                            <label for="category_id"><?php _e('Categoria:', 'wpsgl'); ?></label>
                            <select id="category_id" name="category_id">
                                <option value="0"><?php _e('Todas', 'wpsgl'); ?></option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo esc_attr($category->id); ?>" <?php selected($filters['category_id'], $category->id); ?>>
                                        <?php echo esc_html($category->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="wpsgl-filter-group">
                            <label for="store_id"><?php _e('Loja:', 'wpsgl'); ?></label>
                            <select id="store_id" name="store_id">
                                <option value="0"><?php _e('Todas', 'wpsgl'); ?></option>
                                <?php foreach ($stores as $store): ?>
                                    <option value="<?php echo esc_attr($store->id); ?>" <?php selected($filters['store_id'], $store->id); ?>>
                                        <?php echo esc_html($store->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="wpsgl-filter-actions">
                        <button type="submit" class="button button-primary">
                            <?php _e('Filtrar', 'wpsgl'); ?>
                        </button>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=wpsgl-reports')); ?>" class="button">
                            <?php _e('Limpar', 'wpsgl'); ?>
                        </a>
                        <button type="button" id="export-csv" class="button button-secondary">
                            <?php _e('Exportar CSV', 'wpsgl'); ?>
                        </button>
                        <button type="button" class="button button-secondary" data-modal="wpsgl-import-modal">
                            <?php _e('Importar CSV', 'wpsgl'); ?>
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Cards Estatísticos -->
            <div class="wpsgl-stats-cards">
                <div class="wpsgl-stat-card">
                    <h4><?php _e('Itens Comprados', 'wpsgl'); ?></h4>
                    <div class="wpsgl-stat-number"><?php echo esc_html($total_items); ?></div>
                </div>
                
                <div class="wpsgl-stat-card">
                    <h4><?php _e('Total Gasto', 'wpsgl'); ?></h4>
                    <div class="wpsgl-stat-number">R$ <?php echo number_format($total_spent, 2, ',', '.'); ?></div>
                </div>
                
                <div class="wpsgl-stat-card">
                    <h4><?php _e('Média por Item', 'wpsgl'); ?></h4>
                    <div class="wpsgl-stat-number">R$ <?php echo number_format($avg_per_item, 2, ',', '.'); ?></div>
                </div>
            </div>
            
            <!-- Tabela de Compras -->
            <div class="wpsgl-purchases-table">
                <?php if (empty($purchases)): ?>
                    <p class="description"><?php _e('Nenhuma compra encontrada para os filtros selecionados.', 'wpsgl'); ?></p>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Data', 'wpsgl'); ?></th>
                                <th><?php _e('Produto', 'wpsgl'); ?></th>
                                <th><?php _e('Categoria', 'wpsgl'); ?></th>
                                <th><?php _e('Quantidade', 'wpsgl'); ?></th>
                                <th><?php _e('Preço Unitário', 'wpsgl'); ?></th>
                                <th><?php _e('Total', 'wpsgl'); ?></th>
                                <th><?php _e('Loja', 'wpsgl'); ?></th>
                                <th><?php _e('Ações', 'wpsgl'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($purchases as $purchase): ?>
                                <tr>
                                    <td><?php echo esc_html(date('d/m/Y', strtotime($purchase->purchase_date))); ?></td>
                                    <td><?php echo esc_html($purchase->product_name); ?></td>
                                    <td><?php echo esc_html($purchase->category_name); ?></td>
                                    <td><?php echo esc_html($purchase->quantity . ' ' . $purchase->unit); ?></td>
                                    <td>R$ <?php echo number_format($purchase->unit_price, 2, ',', '.'); ?></td>
                                    <td>R$ <?php echo number_format($purchase->total_price, 2, ',', '.'); ?></td>
                                    <td><?php echo esc_html($purchase->store_name); ?></td>
                                    <td>
                                        <div class="row-actions">
                                            <span class="edit">
                                                <a href="<?php echo esc_url(add_query_arg(array('page' => 'wpsgl-products', 'action' => 'edit', 'id' => intval($purchase->product_id)), admin_url('admin.php'))); ?>" class="button button-small">
                                                    <?php _e('Editar', 'wpsgl'); ?>
                                                </a>
                                            </span>
                                            <span class="delete">
                                                <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(array('page' => 'wpsgl-reports', 'action' => 'delete_purchase', 'id' => intval($purchase->id)), admin_url('admin.php')), 'delete_purchase_' . intval($purchase->id))); ?>" class="button button-small button-link-delete" onclick="return confirm('<?php esc_attr_e('Tem certeza que deseja excluir esta compra?', 'wpsgl'); ?>');">
                                                    <?php _e('Excluir compra', 'wpsgl'); ?>
                                                </a>
                                            </span>
                                            <span class="delete">
                                                <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(array('page' => 'wpsgl-products', 'action' => 'delete', 'id' => intval($purchase->product_id)), admin_url('admin.php')), 'delete_product_' . intval($purchase->product_id))); ?>" class="button button-small button-link-delete" onclick="return confirm('<?php esc_attr_e('Tem certeza que deseja excluir este produto?', 'wpsgl'); ?>');">
                                                    <?php _e('Excluir', 'wpsgl'); ?>
                                                </a>
                                            </span>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
            <div id="wpsgl-import-modal" class="wpsgl-modal" style="display: none;">
                <div class="wpsgl-modal-content">
                    <div class="wpsgl-modal-header">
                        <h3><?php _e('Importar Compras via CSV', 'wpsgl'); ?></h3>
                        <button type="button" class="wpsgl-modal-close">&times;</button>
                    </div>
                    <div class="wpsgl-modal-body">
                        <form id="import-csv-form" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="wpsgl_reports_import_csv">
                            <?php wp_nonce_field('wpsgl_reports_nonce', 'wpsgl_nonce'); ?>
                            <div class="wpsgl-form-group">
                                <label for="csv_file"><?php _e('Arquivo CSV:', 'wpsgl'); ?> *</label>
                                <input type="file" id="csv_file" name="csv_file" accept=".csv" required>
                                <p class="description">
                                    <?php _e('Use o formato: Data;Hora;Produto;Código de Barras;Categoria;Quantidade;Unidade;Preço Unitário;Total;Loja;Observações', 'wpsgl'); ?>
                                </p>
                            </div>
                            <div class="wpsgl-form-actions">
                                <button type="submit" class="button button-primary"><?php _e('Importar', 'wpsgl'); ?></button>
                                <button type="button" class="button button-secondary wpsgl-modal-close"><?php _e('Cancelar', 'wpsgl'); ?></button>
                            </div>
                        </form>
                        <div id="wpsgl-import-result" style="display:none;"></div>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#export-csv').on('click', function(e) {
                e.preventDefault();
                
                var formData = $('form').serialize();
                var url = '<?php echo admin_url('admin.php?page=wpsgl-reports&export=csv'); ?>' + 
                          '&' + formData + 
                          '&nonce=<?php echo wp_create_nonce('wpsgl_reports_export_nonce'); ?>';
                
                window.location.href = url;
            });
        });
        </script>
        <?php
    }
    
    public function handle_export_csv() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Acesso não autorizado.', 'wpsgl'));
        }
        if (isset($_REQUEST['nonce']) && !wp_verify_nonce($_REQUEST['nonce'], 'wpsgl_reports_export_nonce')) {
            wp_die(__('Acesso não autorizado.', 'wpsgl'));
        }
        
        $filters = array(
            'start_date' => isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : '',
            'end_date' => isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : '',
            'category_id' => isset($_GET['category_id']) ? intval($_GET['category_id']) : 0,
            'store_id' => isset($_GET['store_id']) ? intval($_GET['store_id']) : 0
        );
        
        $this->database->export_purchases_csv($filters);
    }
    
    public function handle_import_csv() {
        if (!current_user_can('manage_options')) {
            wp_die(json_encode(array('success' => false, 'message' => __('Acesso não autorizado.', 'wpsgl'))));
        }
        if (!isset($_POST['wpsgl_nonce']) || !wp_verify_nonce($_POST['wpsgl_nonce'], 'wpsgl_reports_nonce')) {
            wp_die(json_encode(array('success' => false, 'message' => __('Acesso não autorizado.', 'wpsgl'))));
        }
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            wp_die(json_encode(array('success' => false, 'message' => __('Erro no upload do arquivo.', 'wpsgl'))));
        }
        
        $file = $_FILES['csv_file']['tmp_name'];
        $imported = 0;
        $skipped = 0;
        
        global $wpdb;
        $prefix = $wpdb->prefix . WPSGL_PREFIX;
        
        if (($handle = fopen($file, 'r')) !== false) {
            $header = fgetcsv($handle, 0, ';');
            while (($data = fgetcsv($handle, 0, ';')) !== false) {
                if (count($data) < 11) {
                    $skipped++;
                    continue;
                }
                
                $purchase_date = sanitize_text_field($data[0]);
                $purchase_time = sanitize_text_field($data[1]);
                $product_name = sanitize_text_field($data[2]);
                $barcode = sanitize_text_field($data[3]);
                $category_name = sanitize_text_field($data[4]);
                $quantity = floatval(str_replace(',', '.', $data[5]));
                $unit = sanitize_text_field($data[6]);
                $unit_price = floatval(str_replace(',', '.', $data[7]));
                $total_price = floatval(str_replace(',', '.', $data[8]));
                $store_name = sanitize_text_field($data[9]);
                $notes = sanitize_textarea_field($data[10]);
                
                if (empty($product_name) || empty($category_name) || empty($store_name) || empty($purchase_date)) {
                    $skipped++;
                    continue;
                }
                
                $category_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$prefix}categories WHERE name = %s", $category_name));
                if (!$category_id) {
                    $wpdb->insert($prefix . 'categories', array('name' => $category_name));
                    $category_id = $wpdb->insert_id;
                }
                
                $store_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$prefix}stores WHERE name = %s", $store_name));
                if (!$store_id) {
                    $wpdb->insert($prefix . 'stores', array('name' => $store_name));
                    $store_id = $wpdb->insert_id;
                }
                
                $product_id = 0;
                if (!empty($barcode)) {
                    $product_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$prefix}products WHERE barcode = %s", $barcode));
                }
                if (!$product_id) {
                    $product_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$prefix}products WHERE name = %s AND category_id = %d", $product_name, $category_id));
                }
                if (!$product_id) {
                    $wpdb->insert($prefix . 'products', array(
                        'name' => $product_name,
                        'category_id' => $category_id,
                        'default_price' => $unit_price,
                        'default_unit' => $unit,
                        'barcode' => $barcode
                    ));
                    $product_id = $wpdb->insert_id;
                }
                
                $this->database->insert_purchase(array(
                    'product_id' => $product_id,
                    'category_id' => $category_id,
                    'store_id' => $store_id,
                    'quantity' => $quantity,
                    'unit' => $unit,
                    'unit_price' => $unit_price,
                    'total_price' => $total_price,
                    'purchase_date' => $purchase_date,
                    'purchase_time' => $purchase_time ?: '00:00:00',
                    'notes' => $notes
                ));
                
                $imported++;
            }
            fclose($handle);
        }
        
        wp_die(json_encode(array(
            'success' => true,
            'message' => __('Importação de compras concluída com sucesso!', 'wpsgl'),
            'imported' => $imported,
            'skipped' => $skipped
        )));
    }
    
    public function trigger_export_csv() {
        if (
            isset($_GET['page']) &&
            $_GET['page'] === 'wpsgl-reports' &&
            isset($_GET['export']) &&
            $_GET['export'] === 'csv'
        ) {
            $this->handle_export_csv();
        }
    }
}
