<?php
class WPSGL_Dashboard_Page {
    
    private $database;
    
    public function __construct($database) {
        $this->database = $database;
        add_action('admin_menu', array($this, 'add_menu_page'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_post_wpsgl_reset_data', array($this, 'handle_reset'));
    }
    
    public function add_menu_page() {
        add_menu_page(
            __('Dashboard de Compras', 'wpsgl'),
            __('Compras Supermercado', 'wpsgl'),
            'manage_options',
            'wpsgl-dashboard',
            array($this, 'render_page'),
            'dashicons-cart',
            30
        );
    }
    
    public function enqueue_scripts($hook) {
        if (strpos($hook, 'wpsgl-dashboard') !== false) {
            wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '3.9.1', true);
            wp_enqueue_script('wpsgl-chart-loader', WPSGL_PLUGIN_URL . 'admin/assets/js/chart-loader.js', array('chart-js', 'jquery'), WPSGL_VERSION, true);
            wp_enqueue_style('wpsgl-admin', WPSGL_PLUGIN_URL . 'admin/assets/css/admin-style.css', array(), WPSGL_VERSION);
        }
    }
    
    public function render_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Você não tem permissão para acessar esta página.', 'wpsgl'));
        }
        
        $stats = $this->database->get_monthly_stats();
        $today = date('Y-m-d');
        
        if (isset($_GET['message']) && $_GET['message'] === 'reset_done') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Dados do plugin foram resetados.', 'wpsgl') . '</p></div>';
        }
        
        // Compras de hoje
        $today_purchases = $this->database->get_purchases_report(array(
            'start_date' => $today,
            'end_date' => $today
        ));
        
        ?>
        <div class="wrap wpsgl-dashboard">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="wpsgl-cards-container">
                <!-- Card Resumo do Mês -->
                <div class="wpsgl-card wpsgl-card-primary">
                    <h3><?php _e('Resumo do Mês', 'wpsgl'); ?></h3>
                    <div class="wpsgl-card-content">
                        <div class="wpsgl-stat-item">
                            <span class="wpsgl-stat-label"><?php _e('Total Gasto:', 'wpsgl'); ?></span>
                            <span class="wpsgl-stat-value">R$ <?php echo number_format($stats['overall']->total_spent ?? 0, 2, ',', '.'); ?></span>
                        </div>
                        <div class="wpsgl-stat-item">
                            <span class="wpsgl-stat-label"><?php _e('Itens Comprados:', 'wpsgl'); ?></span>
                            <span class="wpsgl-stat-value"><?php echo $stats['overall']->total_items ?? 0; ?></span>
                        </div>
                        <div class="wpsgl-stat-item">
                            <span class="wpsgl-stat-label"><?php _e('Média por Item:', 'wpsgl'); ?></span>
                            <span class="wpsgl-stat-value">R$ <?php echo number_format($stats['overall']->avg_per_item ?? 0, 2, ',', '.'); ?></span>
                        </div>
                        <div class="wpsgl-stat-item">
                            <span class="wpsgl-stat-label"><?php _e('Dias com Compras:', 'wpsgl'); ?></span>
                            <span class="wpsgl-stat-value"><?php echo $stats['overall']->shopping_days ?? 0; ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- Card Compras de Hoje -->
                <div class="wpsgl-card">
                    <h3><?php _e('Compras de Hoje', 'wpsgl'); ?></h3>
                    <div class="wpsgl-card-content">
                        <?php if (empty($today_purchases)): ?>
                            <p><?php _e('Nenhuma compra hoje.', 'wpsgl'); ?></p>
                        <?php else: 
                            $today_total = array_sum(array_column($today_purchases, 'total_price'));
                        ?>
                            <div class="wpsgl-stat-item">
                                <span class="wpsgl-stat-label"><?php _e('Total Hoje:', 'wpsgl'); ?></span>
                                <span class="wpsgl-stat-value">R$ <?php echo number_format($today_total, 2, ',', '.'); ?></span>
                            </div>
                            <div class="wpsgl-stat-item">
                                <span class="wpsgl-stat-label"><?php _e('Itens:', 'wpsgl'); ?></span>
                                <span class="wpsgl-stat-value"><?php echo count($today_purchases); ?></span>
                            </div>
                            <ul class="wpsgl-today-list">
                                <?php foreach (array_slice($today_purchases, 0, 5) as $purchase): ?>
                                    <li><?php echo esc_html($purchase->product_name); ?> - R$ <?php echo number_format($purchase->total_price, 2, ',', '.'); ?></li>
                                <?php endforeach; ?>
                                <?php if (count($today_purchases) > 5): ?>
                                    <li>... <?php printf(__('e mais %d itens', 'wpsgl'), count($today_purchases) - 5); ?></li>
                                <?php endif; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Gráfico de Distribuição por Categoria -->
            <div class="wpsgl-card wpsgl-card-full">
                <h3><?php _e('Distribuição por Categoria', 'wpsgl'); ?></h3>
                <div class="wpsgl-card-content">
                    <div style="max-width: 600px; margin: 0 auto;">
                        <canvas id="categoryChart"></canvas>
                    </div>
                </div>
            </div>
            
            <div class="wpsgl-card wpsgl-card-danger">
                <h3><?php _e('Ferramentas', 'wpsgl'); ?></h3>
                <div class="wpsgl-card-content">
                    <p><?php _e('Esta ação apaga todas as compras, produtos, categorias e lojas.', 'wpsgl'); ?></p>
                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=wpsgl_reset_data'), 'wpsgl_reset_data')); ?>" 
                       class="button button-secondary" 
                       onclick="return confirm('<?php echo esc_attr(__('Tem certeza que deseja resetar todos os dados? Esta ação não pode ser desfeita.', 'wpsgl')); ?>');">
                        <?php _e('Resetar Plugin', 'wpsgl'); ?>
                    </a>
                </div>
            </div>
            
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                var categoryData = <?php echo json_encode($stats['by_category']); ?>;
                var labels = [];
                var data = [];
                var colors = [
                    '#4CAF50', '#2196F3', '#FF9800', '#E91E63', 
                    '#9C27B0', '#3F51B5', '#00BCD4', '#8BC34A',
                    '#FF5722', '#795548', '#607D8B', '#009688'
                ];
                
                categoryData.forEach(function(item, index) {
                    labels.push(item.name);
                    data.push(parseFloat(item.total));
                });
                
                var ctx = document.getElementById('categoryChart').getContext('2d');
                var chart = new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: labels,
                        datasets: [{
                            data: data,
                            backgroundColor: colors.slice(0, labels.length),
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                position: 'right',
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        var label = context.label || '';
                                        if (label) {
                                            label += ': ';
                                        }
                                        label += 'R$ ' + context.raw.toFixed(2).replace('.', ',');
                                        label += ' (' + categoryData[context.dataIndex].percentage + '%)';
                                        return label;
                                    }
                                }
                            }
                        }
                    }
                });
            });
            </script>
        </div>
        <?php
    }
    
    public function handle_reset() {
        if (!current_user_can('manage_options') || !wp_verify_nonce($_GET['_wpnonce'], 'wpsgl_reset_data')) {
            wp_die(__('Acesso não autorizado.', 'wpsgl'));
        }
        
        $this->database->reset_all();
        
        $defaults_categories = array('Padaria','Açougue','Hortifrúti','Laticínios','Bebidas','Limpeza','Higiene','Enlatados','Grãos','Congelados');
        global $wpdb;
        $prefix = $wpdb->prefix . WPSGL_PREFIX;
        foreach ($defaults_categories as $category) {
            $wpdb->insert($prefix . 'categories', array('name' => sanitize_text_field($category)));
        }
        $defaults_stores = array('Supermercado A','Supermercado B','Mercadinho','Atacado','Feira');
        foreach ($defaults_stores as $store) {
            $wpdb->insert($prefix . 'stores', array('name' => sanitize_text_field($store)));
        }
        
        wp_redirect(add_query_arg(array('page' => 'wpsgl-dashboard','message' => 'reset_done'), admin_url('admin.php')));
        exit;
    }
}
