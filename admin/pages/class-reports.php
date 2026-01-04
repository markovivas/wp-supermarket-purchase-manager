<?php
class WPSGL_Reports_Page {
    
    private $database;
    
    public function __construct($database) {
        $this->database = $database;
        add_action('admin_menu', array($this, 'add_submenu_page'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_wpsgl_reports_export_csv', array($this, 'handle_export_csv'));
        add_action('wp_ajax_wpsgl_export_csv', array($this, 'handle_export_csv'));
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
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
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
