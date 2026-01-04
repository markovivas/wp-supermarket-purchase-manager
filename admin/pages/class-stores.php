<?php
class WPSGL_Stores_Page {
    
    private $database;
    
    public function __construct($database) {
        $this->database = $database;
        add_action('admin_menu', array($this, 'add_submenu_page'));
        add_action('admin_post_wpsgl_save_store', array($this, 'handle_save'));
        add_action('admin_post_wpsgl_delete_store', array($this, 'handle_delete'));
    }
    
    public function add_submenu_page() {
        add_submenu_page(
            'wpsgl-dashboard',
            __('Gerenciar Lojas', 'wpsgl'),
            __('Lojas', 'wpsgl'),
            'manage_options',
            'wpsgl-stores',
            array($this, 'render_page')
        );
    }
	
	public function enqueue_scripts($hook) {
    if (strpos($hook, 'wpsgl-stores') !== false) {
        wp_enqueue_style('wpsgl-admin', WPSGL_PLUGIN_URL . 'admin/assets/css/admin-style.css', array(), WPSGL_VERSION);
    }
}
    
    public function render_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Você não tem permissão para acessar esta página.', 'wpsgl'));
        }
        
        global $wpdb;
        $prefix = $wpdb->prefix . 'wpsgl_';
        
        // Verificar ação
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
        
        // Listar lojas
        $stores = $this->database->get_stores();
        
        // Estatísticas
        $stats = $wpdb->get_results("
            SELECT s.id, s.name, 
                   COUNT(pu.id) as purchase_count,
                   SUM(pu.total_price) as total_spent,
                   AVG(pu.total_price) as avg_purchase
            FROM {$prefix}stores s
            LEFT JOIN {$prefix}purchases pu ON s.id = pu.store_id
            GROUP BY s.id
            ORDER BY s.name
        ");
        
        ?>
        <div class="wrap wpsgl-stores">
            <h1 class="wp-heading-inline"><?php _e('Lojas/Mercados', 'wpsgl'); ?></h1>
            <a href="<?php echo esc_url(admin_url('admin.php?page=wpsgl-stores&action=add')); ?>" class="page-title-action">
                <?php _e('Adicionar Loja', 'wpsgl'); ?>
            </a>
            
            <?php if (isset($_GET['message'])): ?>
                <?php
                $messages = array(
                    'added' => __('Loja adicionada com sucesso!', 'wpsgl'),
                    'updated' => __('Loja atualizada com sucesso!', 'wpsgl'),
                    'deleted' => __('Loja excluída com sucesso!', 'wpsgl'),
                    'error' => __('Erro ao processar a loja.', 'wpsgl')
                );
                $message_type = isset($_GET['error']) ? 'error' : 'success';
                ?>
                <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible">
                    <p><?php echo esc_html($messages[$_GET['message']] ?? 'Operação concluída.'); ?></p>
                </div>
            <?php endif; ?>
            
            <div class="wpsgl-cards-container">
                <?php if (!empty($stats)): ?>
                    <?php foreach ($stats as $stat): ?>
                        <div class="wpsgl-card">
                            <h3><?php echo esc_html($stat->name); ?></h3>
                            <div class="wpsgl-card-content">
                                <div class="wpsgl-stat-item">
                                    <span class="wpsgl-stat-label"><?php _e('Compras:', 'wpsgl'); ?></span>
                                    <span class="wpsgl-stat-value"><?php echo intval($stat->purchase_count); ?></span>
                                </div>
                                <div class="wpsgl-stat-item">
                                    <span class="wpsgl-stat-label"><?php _e('Total Gasto:', 'wpsgl'); ?></span>
                                    <span class="wpsgl-stat-value">R$ <?php echo number_format(floatval($stat->total_spent), 2, ',', '.'); ?></span>
                                </div>
                                <div class="wpsgl-stat-item">
                                    <span class="wpsgl-stat-label"><?php _e('Média por Compra:', 'wpsgl'); ?></span>
                                    <span class="wpsgl-stat-value">R$ <?php echo number_format(floatval($stat->avg_purchase), 2, ',', '.'); ?></span>
                                </div>
                                <div class="wpsgl-form-actions" style="margin-top: 15px;">
                                    <a href="<?php echo esc_url(add_query_arg(array('action' => 'edit', 'id' => $stat->id))); ?>" 
                                       class="button button-small">
                                        <?php _e('Editar', 'wpsgl'); ?>
                                    </a>
                                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=wpsgl_delete_store&id=' . $stat->id), 'delete_store_' . $stat->id)); ?>" 
                                       class="button button-small button-link-delete" 
                                       onclick="return confirm('<?php esc_attr_e('Tem certeza que deseja excluir esta loja? Todas as compras desta loja também serão excluídas.', 'wpsgl'); ?>');">
                                        <?php _e('Excluir', 'wpsgl'); ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="wpsgl-card wpsgl-card-full">
                        <div class="wpsgl-card-content">
                            <p><?php _e('Nenhuma loja encontrada.', 'wpsgl'); ?></p>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=wpsgl-stores&action=add')); ?>" class="button button-primary">
                                <?php _e('Adicionar Primeira Loja', 'wpsgl'); ?>
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    private function render_add_form() {
        ?>
        <div class="wrap wpsgl-stores">
            <h1><?php _e('Adicionar Nova Loja', 'wpsgl'); ?></h1>
            
            <div class="wpsgl-form-container">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="wpsgl_save_store">
                    <?php wp_nonce_field('wpsgl_save_store', 'wpsgl_nonce'); ?>
                    
                    <div class="wpsgl-form-group">
                        <label for="store_name"><?php _e('Nome da Loja:', 'wpsgl'); ?> *</label>
                        <input type="text" id="store_name" name="store_name" required 
                               placeholder="<?php esc_attr_e('Ex: Supermercado A, Mercadinho, etc.', 'wpsgl'); ?>">
                    </div>
                    
                    <div class="wpsgl-form-group">
                        <label for="store_address"><?php _e('Endereço (opcional):', 'wpsgl'); ?></label>
                        <textarea id="store_address" name="store_address" rows="3"></textarea>
                    </div>
                    
                    <div class="wpsgl-form-actions">
                        <button type="submit" class="button button-primary">
                            <?php _e('Salvar Loja', 'wpsgl'); ?>
                        </button>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=wpsgl-stores')); ?>" class="button">
                            <?php _e('Cancelar', 'wpsgl'); ?>
                        </a>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }
    
    private function render_edit_form($id) {
        global $wpdb;
        $prefix = $wpdb->prefix . 'wpsgl_';
        
        $store = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$prefix}stores WHERE id = %d",
            $id
        ));
        
        if (!$store) {
            wp_die(__('Loja não encontrada.', 'wpsgl'));
        }
        
        ?>
        <div class="wrap wpsgl-stores">
            <h1><?php _e('Editar Loja', 'wpsgl'); ?></h1>
            
            <div class="wpsgl-form-container">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="wpsgl_save_store">
                    <input type="hidden" name="store_id" value="<?php echo esc_attr($id); ?>">
                    <?php wp_nonce_field('wpsgl_save_store', 'wpsgl_nonce'); ?>
                    
                    <div class="wpsgl-form-group">
                        <label for="store_name"><?php _e('Nome da Loja:', 'wpsgl'); ?> *</label>
                        <input type="text" id="store_name" name="store_name" 
                               value="<?php echo esc_attr($store->name); ?>" required>
                    </div>
                    
                    <div class="wpsgl-form-group">
                        <label for="store_address"><?php _e('Endereço (opcional):', 'wpsgl'); ?></label>
                        <textarea id="store_address" name="store_address" rows="3"><?php echo esc_textarea($store->address ?? ''); ?></textarea>
                    </div>
                    
                    <div class="wpsgl-form-actions">
                        <button type="submit" class="button button-primary">
                            <?php _e('Atualizar Loja', 'wpsgl'); ?>
                        </button>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=wpsgl-stores')); ?>" class="button">
                            <?php _e('Cancelar', 'wpsgl'); ?>
                        </a>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }
    
    public function handle_save() {
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['wpsgl_nonce'], 'wpsgl_save_store')) {
            wp_die(__('Acesso não autorizado.', 'wpsgl'));
        }
        
        global $wpdb;
        $prefix = $wpdb->prefix . 'wpsgl_';
        
        $store_name = sanitize_text_field($_POST['store_name']);
        $store_address = isset($_POST['store_address']) ? sanitize_textarea_field($_POST['store_address']) : '';
        $store_id = isset($_POST['store_id']) ? intval($_POST['store_id']) : 0;
        
        if (empty($store_name)) {
            wp_die(__('Nome da loja é obrigatório.', 'wpsgl'));
        }
        
        // Verificar duplicidade
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$prefix}stores WHERE name = %s AND id != %d",
            $store_name, $store_id
        ));
        
        if ($exists) {
            wp_redirect(add_query_arg(array(
                'page' => 'wpsgl-stores',
                'message' => 'error',
                'error' => 'duplicate'
            ), admin_url('admin.php')));
            exit;
        }
        
        $data = array(
            'name' => $store_name,
            'address' => $store_address
        );
        
        if ($store_id > 0) {
            // Atualizar loja existente
            $result = $wpdb->update(
                $prefix . 'stores',
                $data,
                array('id' => $store_id),
                array('%s', '%s'),
                array('%d')
            );
            $message = 'updated';
        } else {
            // Inserir nova loja
            $result = $wpdb->insert($prefix . 'stores', $data, array('%s', '%s'));
            $message = 'added';
        }
        
        if ($result !== false) {
            wp_redirect(add_query_arg(array(
                'page' => 'wpsgl-stores',
                'message' => $message
            ), admin_url('admin.php')));
        } else {
            // Log do erro para debug
            error_log('WPSGL Store Save Error: ' . $wpdb->last_error);
            
            wp_redirect(add_query_arg(array(
                'page' => 'wpsgl-stores',
                'message' => 'error'
            ), admin_url('admin.php')));
        }
        exit;
    }
    
    public function handle_delete() {
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        if (!current_user_can('manage_options') || !wp_verify_nonce($_GET['_wpnonce'], 'delete_store_' . $id)) {
            wp_die(__('Acesso não autorizado.', 'wpsgl'));
        }
        
        if ($id <= 0) {
            wp_die(__('ID inválido.', 'wpsgl'));
        }
        
        global $wpdb;
        $prefix = $wpdb->prefix . 'wpsgl_';
        
        // Verificar se existem compras nesta loja
        $purchase_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$prefix}purchases WHERE store_id = %d",
            $id
        ));
        
        if ($purchase_count > 0) {
            // Deletar compras da loja
            $wpdb->delete($prefix . 'purchases', array('store_id' => $id), array('%d'));
        }
        
        // Deletar loja
        $result = $wpdb->delete($prefix . 'stores', array('id' => $id), array('%d'));
        
        if ($result) {
            wp_redirect(add_query_arg(array(
                'page' => 'wpsgl-stores',
                'message' => 'deleted'
            ), admin_url('admin.php')));
        } else {
            wp_redirect(add_query_arg(array(
                'page' => 'wpsgl-stores',
                'message' => 'error'
            ), admin_url('admin.php')));
        }
        exit;
    }
} 
