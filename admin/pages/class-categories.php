<?php
class WPSGL_Categories_Page {
    
    private $database;
    
    public function __construct($database) {
        $this->database = $database;
        add_action('admin_menu', array($this, 'add_submenu_page'));
        add_action('admin_post_wpsgl_save_category', array($this, 'handle_save'));
        add_action('admin_post_wpsgl_delete_category', array($this, 'handle_delete'));
    }
    
    public function add_submenu_page() {
        add_submenu_page(
            'wpsgl-dashboard',
            __('Gerenciar Categorias', 'wpsgl'),
            __('Categorias', 'wpsgl'),
            'manage_options',
            'wpsgl-categories',
            array($this, 'render_page')
        );
    }
    
	public function enqueue_scripts($hook) {
    if (strpos($hook, 'wpsgl-categories') !== false) {
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
        
        // Listar categorias
        $categories = $this->database->get_categories();
        
        // Estatísticas
        $stats = $wpdb->get_results("
            SELECT c.id, c.name, COUNT(p.id) as product_count, 
                   COUNT(pu.id) as purchase_count
            FROM {$prefix}categories c
            LEFT JOIN {$prefix}products p ON c.id = p.category_id
            LEFT JOIN {$prefix}purchases pu ON c.id = pu.category_id
            GROUP BY c.id
            ORDER BY c.name
        ");
        
        ?>
        <div class="wrap wpsgl-categories">
            <h1 class="wp-heading-inline"><?php _e('Categorias', 'wpsgl'); ?></h1>
            <a href="<?php echo esc_url(admin_url('admin.php?page=wpsgl-categories&action=add')); ?>" class="page-title-action">
                <?php _e('Adicionar Categoria', 'wpsgl'); ?>
            </a>
            
            <?php if (isset($_GET['message'])): ?>
                <?php
                $messages = array(
                    'added' => __('Categoria adicionada com sucesso!', 'wpsgl'),
                    'updated' => __('Categoria atualizada com sucesso!', 'wpsgl'),
                    'deleted' => __('Categoria excluída com sucesso!', 'wpsgl'),
                    'error' => __('Erro ao processar a categoria.', 'wpsgl')
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
                                    <span class="wpsgl-stat-label"><?php _e('Produtos:', 'wpsgl'); ?></span>
                                    <span class="wpsgl-stat-value"><?php echo intval($stat->product_count); ?></span>
                                </div>
                                <div class="wpsgl-stat-item">
                                    <span class="wpsgl-stat-label"><?php _e('Compras:', 'wpsgl'); ?></span>
                                    <span class="wpsgl-stat-value"><?php echo intval($stat->purchase_count); ?></span>
                                </div>
                                <div class="wpsgl-form-actions" style="margin-top: 15px;">
                                    <a href="<?php echo esc_url(add_query_arg(array('action' => 'edit', 'id' => $stat->id))); ?>" 
                                       class="button button-small">
                                        <?php _e('Editar', 'wpsgl'); ?>
                                    </a>
                                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=wpsgl_delete_category&id=' . $stat->id), 'delete_category_' . $stat->id)); ?>" 
                                       class="button button-small button-link-delete" 
                                       onclick="return confirm('<?php esc_attr_e('Tem certeza que deseja excluir esta categoria? Todos os produtos desta categoria também serão excluídos.', 'wpsgl'); ?>');">
                                        <?php _e('Excluir', 'wpsgl'); ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="wpsgl-card wpsgl-card-full">
                        <div class="wpsgl-card-content">
                            <p><?php _e('Nenhuma categoria encontrada.', 'wpsgl'); ?></p>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=wpsgl-categories&action=add')); ?>" class="button button-primary">
                                <?php _e('Adicionar Primeira Categoria', 'wpsgl'); ?>
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
        <div class="wrap wpsgl-categories">
            <h1><?php _e('Adicionar Nova Categoria', 'wpsgl'); ?></h1>
            
            <div class="wpsgl-form-container">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="wpsgl_save_category">
                    <?php wp_nonce_field('wpsgl_save_category', 'wpsgl_nonce'); ?>
                    
                    <div class="wpsgl-form-group">
                        <label for="category_name"><?php _e('Nome da Categoria:', 'wpsgl'); ?> *</label>
                        <input type="text" id="category_name" name="category_name" required 
                               placeholder="<?php esc_attr_e('Ex: Hortifrúti, Padaria, etc.', 'wpsgl'); ?>">
                    </div>
                    
                    <div class="wpsgl-form-actions">
                        <button type="submit" class="button button-primary">
                            <?php _e('Salvar Categoria', 'wpsgl'); ?>
                        </button>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=wpsgl-categories')); ?>" class="button">
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
        
        $category = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$prefix}categories WHERE id = %d",
            $id
        ));
        
        if (!$category) {
            wp_die(__('Categoria não encontrada.', 'wpsgl'));
        }
        
        ?>
        <div class="wrap wpsgl-categories">
            <h1><?php _e('Editar Categoria', 'wpsgl'); ?></h1>
            
            <div class="wpsgl-form-container">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="wpsgl_save_category">
                    <input type="hidden" name="category_id" value="<?php echo esc_attr($id); ?>">
                    <?php wp_nonce_field('wpsgl_save_category', 'wpsgl_nonce'); ?>
                    
                    <div class="wpsgl-form-group">
                        <label for="category_name"><?php _e('Nome da Categoria:', 'wpsgl'); ?> *</label>
                        <input type="text" id="category_name" name="category_name" 
                               value="<?php echo esc_attr($category->name); ?>" required>
                    </div>
                    
                    <div class="wpsgl-form-actions">
                        <button type="submit" class="button button-primary">
                            <?php _e('Atualizar Categoria', 'wpsgl'); ?>
                        </button>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=wpsgl-categories')); ?>" class="button">
                            <?php _e('Cancelar', 'wpsgl'); ?>
                        </a>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }
    
    public function handle_save() {
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['wpsgl_nonce'], 'wpsgl_save_category')) {
            wp_die(__('Acesso não autorizado.', 'wpsgl'));
        }
        
        global $wpdb;
        $prefix = $wpdb->prefix . 'wpsgl_';
        
        $category_name = sanitize_text_field($_POST['category_name']);
        $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
        
        if (empty($category_name)) {
            wp_die(__('Nome da categoria é obrigatório.', 'wpsgl'));
        }
        
        // Verificar duplicidade
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$prefix}categories WHERE name = %s AND id != %d",
            $category_name, $category_id
        ));
        
        if ($exists) {
            wp_redirect(add_query_arg(array(
                'page' => 'wpsgl-categories',
                'message' => 'error',
                'error' => 'duplicate'
            ), admin_url('admin.php')));
            exit;
        }
        
        $data = array('name' => $category_name);
        
        if ($category_id > 0) {
            // Atualizar categoria existente
            $result = $wpdb->update(
                $prefix . 'categories',
                $data,
                array('id' => $category_id),
                array('%s'),
                array('%d')
            );
            $message = 'updated';
        } else {
            // Inserir nova categoria
            $result = $wpdb->insert($prefix . 'categories', $data, array('%s'));
            $message = 'added';
        }
        
        if ($result !== false) {
            wp_redirect(add_query_arg(array(
                'page' => 'wpsgl-categories',
                'message' => $message
            ), admin_url('admin.php')));
        } else {
            // Log do erro para debug
            error_log('WPSGL Category Save Error: ' . $wpdb->last_error);

            wp_redirect(add_query_arg(array(
                'page' => 'wpsgl-categories',
                'message' => 'error'
            ), admin_url('admin.php')));
        }
        exit;
    }
    
    public function handle_delete() {
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        if (!current_user_can('manage_options') || !wp_verify_nonce($_GET['_wpnonce'], 'delete_category_' . $id)) {
            wp_die(__('Acesso não autorizado.', 'wpsgl'));
        }
        
        if ($id <= 0) {
            wp_die(__('ID inválido.', 'wpsgl'));
        }
        
        global $wpdb;
        $prefix = $wpdb->prefix . 'wpsgl_';
        
        // Verificar se existem produtos nesta categoria
        $product_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$prefix}products WHERE category_id = %d",
            $id
        ));
        
        if ($product_count > 0) {
            // Se houver produtos, perguntar se quer deletar tudo (já perguntado no JavaScript)
            // Deletar primeiro os produtos e depois a categoria
            $wpdb->delete($prefix . 'products', array('category_id' => $id), array('%d'));
        }
        
        // Deletar categoria
        $result = $wpdb->delete($prefix . 'categories', array('id' => $id), array('%d'));
        
        if ($result) {
            wp_redirect(add_query_arg(array(
                'page' => 'wpsgl-categories',
                'message' => 'deleted'
            ), admin_url('admin.php')));
        } else {
            wp_redirect(add_query_arg(array(
                'page' => 'wpsgl-categories',
                'message' => 'error'
            ), admin_url('admin.php')));
        }
        exit;
    }
} 
