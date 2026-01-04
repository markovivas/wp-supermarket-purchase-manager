<?php
class WPSGL_Shortcode {
    
    private $database;
    
    public function __construct() {
        $this->database = new WPSGL_Database();
        add_shortcode('product_registration', array($this, 'render_form'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_wpsgl_add_purchase', array($this, 'handle_ajax_add_purchase'));
        add_action('wp_ajax_nopriv_wpsgl_add_purchase', array($this, 'handle_ajax_add_purchase'));
    }
    
    public function enqueue_scripts() {
        global $post;
        
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'product_registration')) {
            wp_enqueue_style('wpsgl-frontend', WPSGL_PLUGIN_URL . 'frontend/assets/css/frontend-style.css', array(), WPSGL_VERSION);
            wp_enqueue_script('wpsgl-frontend', WPSGL_PLUGIN_URL . 'frontend/assets/js/frontend-script.js', array('jquery'), WPSGL_VERSION, true);
            
            wp_localize_script('wpsgl-frontend', 'wpsgl_frontend', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wpsgl_add_purchase_nonce'),
                'loading_text' => __('Processando...', 'wpsgl'),
                'success_text' => __('Compra registrada com sucesso!', 'wpsgl'),
                'error_text' => __('Erro ao registrar compra. Tente novamente.', 'wpsgl')
            ));
        }
    }
    
    public function render_form($atts = array()) {
        if (!is_user_logged_in()) {
            return '<p>' . __('Você precisa estar logado para registrar compras.', 'wpsgl') . '</p>';
        }
        
        $categories = $this->database->get_categories();
        $stores = $this->database->get_stores();
        
        // Parse shortcode attributes: use [product_registration show_search="no"] to hide the search box
        $atts = shortcode_atts(array(
            'show_search' => 'yes'
        ), $atts, 'product_registration');
        $show_search = !in_array(strtolower($atts['show_search']), array('0','false','no'), true);
        
        ob_start();
        ?>
        <div class="wpsgl-registration-form">
            <h2><?php _e('Registro de Compras', 'wpsgl'); ?></h2>
            
            <div id="wpsgl-message" class="wpsgl-message" style="display: none;"></div>
            
            <?php if ($show_search): ?>
            <!-- Seção de Busca -->
            <div class="wpsgl-search-container">
                <div class="wpsgl-form-group wpsgl-form-group-full" style="margin-bottom: 0;">
                    <label for="product_search" style="font-size: 16px; margin-bottom: 10px;"><?php _e('Buscar por Código de Barras:', 'wpsgl'); ?></label>
                    <div style="display: flex; gap: 10px;">
                        <input type="text" id="product_search" class="wpsgl-autocomplete" 
                               placeholder="<?php esc_attr_e('Digite o código de barras...', 'wpsgl'); ?>" 
                               style="flex-grow: 1; font-size: 16px; padding: 15px;">
                        <button type="button" id="product_search_btn" class="wpsgl-submit-button" 
                                style="width: auto; margin: 0; padding: 0 30px; font-size: 16px;">
                            <i class="dashicons dashicons-search" style="margin-top: 3px;"></i> <?php _e('Buscar', 'wpsgl'); ?>
                        </button>
                    </div>
                    <div id="wpsgl-search-status" class="wpsgl-search-status" style="display:none;"></div>
                </div>
            </div>
            <?php endif; ?>

            <form id="wpsgl-purchase-form">
                <?php wp_nonce_field('wpsgl_add_purchase', 'wpsgl_nonce'); ?>
                <input type="hidden" id="product_id" name="product_id">
                
                <div class="wpsgl-main-grid">
                    <!-- Coluna 1: Dados do Produto -->
                    <div class="wpsgl-column">
                        <h3 class="wpsgl-section-title"><?php _e('Dados do Produto', 'wpsgl'); ?></h3>
                        
                        <div class="wpsgl-form-group">
                            <label for="product_name"><?php _e('Nome do Produto:', 'wpsgl'); ?></label>
                            <input type="text" id="product_name" name="product_name" required>
                        </div>
                        
                        <div class="wpsgl-form-group">
                            <label for="barcode"><?php _e('Código de Barras:', 'wpsgl'); ?></label>
                            <input type="text" id="barcode" name="barcode">
                        </div>
                        
                        <div class="wpsgl-form-group">
                            <label for="category_id"><?php _e('Categoria:', 'wpsgl'); ?></label>
                            <select id="category_id" name="category_id" required>
                                <option value=""><?php _e('Selecione...', 'wpsgl'); ?></option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo esc_attr($category->id); ?>">
                                        <?php echo esc_html($category->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="wpsgl-form-group">
                            <label for="store_id"><?php _e('Loja:', 'wpsgl'); ?></label>
                            <select id="store_id" name="store_id" required>
                                <option value=""><?php _e('Selecione...', 'wpsgl'); ?></option>
                                <?php foreach ($stores as $store): ?>
                                    <option value="<?php echo esc_attr($store->id); ?>">
                                        <?php echo esc_html($store->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Coluna 2: Detalhes da Compra -->
                    <div class="wpsgl-column">
                        <h3 class="wpsgl-section-title"><?php _e('Detalhes da Compra', 'wpsgl'); ?></h3>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                            <div class="wpsgl-form-group">
                                <label for="quantity"><?php _e('Quantidade:', 'wpsgl'); ?></label>
                                <input type="number" id="quantity" name="quantity" step="0.001" min="0.001" value="1" required>
                            </div>
                            
                            <div class="wpsgl-form-group">
                                <label for="unit"><?php _e('Unidade:', 'wpsgl'); ?></label>
                                <select id="unit" name="unit">
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
                            <label for="unit_price"><?php _e('Preço Unitário (R$):', 'wpsgl'); ?></label>
                            <input type="number" id="unit_price" name="unit_price" step="0.01" min="0" required 
                                   style="font-size: 18px; font-weight: bold; color: #2980b9;">
                        </div>
                        
                        <div class="wpsgl-form-group">
                            <label for="total_price"><?php _e('Total (R$):', 'wpsgl'); ?></label>
                            <input type="number" id="total_price" name="total_price" step="0.01" min="0" readonly
                                   style="background-color: #e8f6f3; font-weight: bold; font-size: 18px; color: #27ae60;">
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                            <div class="wpsgl-form-group">
                                <label for="purchase_date"><?php _e('Data:', 'wpsgl'); ?></label>
                                <input type="date" id="purchase_date" name="purchase_date" 
                                       value="<?php echo esc_attr(date('Y-m-d')); ?>" required>
                            </div>
                            
                            <div class="wpsgl-form-group">
                                <label for="purchase_time"><?php _e('Hora:', 'wpsgl'); ?></label>
                                <input type="time" id="purchase_time" name="purchase_time" 
                                       value="<?php echo esc_attr(date('H:i')); ?>" required>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="wpsgl-form-group wpsgl-form-group-full">
                    <label for="notes"><?php _e('Observações:', 'wpsgl'); ?></label>
                    <textarea id="notes" name="notes" rows="2" placeholder="<?php esc_attr_e('Opcional...', 'wpsgl'); ?>"></textarea>
                </div>
                
                <div class="wpsgl-form-actions">
                    <button type="submit" class="wpsgl-submit-button">
                        <i class="dashicons dashicons-saved" style="margin-top: 3px;"></i> <?php _e('Registrar Compra', 'wpsgl'); ?>
                    </button>
                    <button type="reset" class="wpsgl-reset-button">
                        <?php _e('Limpar Formulário', 'wpsgl'); ?>
                    </button>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function handle_ajax_add_purchase() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wpsgl_add_purchase_nonce')) {
            wp_die(json_encode(array(
                'success' => false,
                'message' => __('Token de segurança inválido.', 'wpsgl')
            )));
        }
        
        // Verificar se usuário está logado
        if (!is_user_logged_in()) {
            wp_die(json_encode(array(
                'success' => false,
                'message' => __('Você precisa estar logado.', 'wpsgl')
            )));
        }
        
        // Sanitizar dados
        $data = array(
            'product_id' => isset($_POST['product_id']) ? intval($_POST['product_id']) : 0,
            'category_id' => isset($_POST['category_id']) ? intval($_POST['category_id']) : 0,
            'store_id' => isset($_POST['store_id']) ? intval($_POST['store_id']) : 0,
            'quantity' => isset($_POST['quantity']) ? floatval($_POST['quantity']) : 0,
            'unit' => isset($_POST['unit']) ? sanitize_text_field($_POST['unit']) : 'un',
            'unit_price' => isset($_POST['unit_price']) ? floatval($_POST['unit_price']) : 0,
            'total_price' => isset($_POST['total_price']) ? floatval($_POST['total_price']) : 0,
            'purchase_date' => isset($_POST['purchase_date']) ? sanitize_text_field($_POST['purchase_date']) : date('Y-m-d'),
            'purchase_time' => isset($_POST['purchase_time']) ? sanitize_text_field($_POST['purchase_time']) : date('H:i:s'),
            'notes' => isset($_POST['notes']) ? sanitize_textarea_field($_POST['notes']) : ''
        );
        
        // Se não tem product_id, verificar se precisa criar produto
        if ($data['product_id'] == 0 && !empty($_POST['product_name'])) {
            // Verificar se produto já existe
            global $wpdb;
            $prefix = $wpdb->prefix . WPSGL_PREFIX;
            
            $product_name = sanitize_text_field($_POST['product_name']);
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$prefix}products WHERE name = %s",
                $product_name
            ));
            
            if ($existing) {
                $data['product_id'] = $existing;
            } else {
                // Criar novo produto
                $product_data = array(
                    'name' => $product_name,
                    'category_id' => $data['category_id'],
                    'default_price' => $data['unit_price'],
                    'default_unit' => $data['unit'],
                    'barcode' => isset($_POST['barcode']) ? sanitize_text_field($_POST['barcode']) : ''
                );
                
                $wpdb->insert($prefix . 'products', $product_data);
                $data['product_id'] = $wpdb->insert_id;
            }
        }
        
        // Validar dados
        if ($data['product_id'] == 0 || $data['category_id'] == 0 || $data['store_id'] == 0) {
            wp_die(json_encode(array(
                'success' => false,
                'message' => __('Dados incompletos.', 'wpsgl')
            )));
        }
        
        // Inserir compra
        $database = new WPSGL_Database();
        $result = $database->insert_purchase($data);
        
        if ($result) {
            wp_die(json_encode(array(
                'success' => true,
                'message' => __('Compra registrada com sucesso!', 'wpsgl'),
                'purchase_id' => $wpdb->insert_id
            )));
        } else {
            wp_die(json_encode(array(
                'success' => false,
                'message' => __('Erro ao registrar compra.', 'wpsgl')
            )));
        }
    }
} 
