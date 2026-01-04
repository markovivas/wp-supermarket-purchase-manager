<?php
class WPSGL_Ajax_Handler {
    
    private $database;
    
    public function __construct() {
        $this->database = new WPSGL_Database();
        $this->init_ajax_actions();
    }
    public function generate_barcode() {
    /*
    if (!wp_verify_nonce($_POST['nonce'], 'wpsgl_admin_nonce')) {
        wp_die(json_encode(array(
            'success' => false,
            'message' => 'Invalid nonce'
        )));
    }
    */
    
    require_once WPSGL_PLUGIN_DIR . 'includes/class-utils.php';
    $barcode = WPSGL_Utils::generate_barcode();
    
    wp_die(json_encode(array(
        'success' => true,
        'barcode' => $barcode
    )));
}

    public function save_product() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Acesso não autorizado.', 'wpsgl'));
        }

        // Verificar nonce se necessário (recomendado descomentar e ajustar conforme seu formulário)
        // if (!isset($_POST['wpsgl_nonce']) || !wp_verify_nonce($_POST['wpsgl_nonce'], 'wpsgl_save_product')) {
        //     wp_die(__('Nonce inválido.', 'wpsgl'));
        // }

        global $wpdb;
        $prefix = $wpdb->prefix . 'wpsgl_';

        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
        
        // Tratamento flexível para preço (aceita R$, espaços, 15,22 ou 15.22)
        $price_raw = isset($_POST['price']) ? $_POST['price'] : '';
        // Remove tudo que não for número, ponto ou vírgula
        $price_clean = preg_replace('/[^0-9,.]/', '', $price_raw);
        // Se houver ponto e vírgula (ex: 1.000,00), remove o ponto de milhar
        if (strpos($price_clean, '.') !== false && strpos($price_clean, ',') !== false) {
            $price_clean = str_replace('.', '', $price_clean);
        }
        // Garante que o separador decimal seja ponto para o banco de dados
        $price = str_replace(',', '.', $price_clean);
        $price = floatval($price);
        
        $unit = isset($_POST['unit']) ? sanitize_text_field($_POST['unit']) : '';
        $barcode = isset($_POST['barcode']) ? sanitize_text_field($_POST['barcode']) : '';

        // Fallback: Se categoria vier como nome em vez de ID (opcional)
        if (empty($category_id) && !empty($_POST['category_name'])) {
             $category_name = sanitize_text_field($_POST['category_name']);
             $category_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$prefix}categories WHERE name = %s",
                $category_name
            ));
            if (!$category_id) {
                $wpdb->insert($prefix . 'categories', array('name' => $category_name));
                $category_id = $wpdb->insert_id;
            }
        }

        $data = array(
            'name' => $name,
            'category_id' => $category_id,
            'default_price' => $price,
            'default_unit' => $unit,
            'barcode' => $barcode
        );

        $result = false;
        if ($id > 0) {
            $result = $wpdb->update($prefix . 'products', $data, array('id' => $id));
        } else {
            $result = $wpdb->insert($prefix . 'products', $data);
        }

        if ($result === false) {
            $redirect_url = add_query_arg('message', 'error', wp_get_referer());
        } else {
            $redirect_url = add_query_arg('message', 'success', wp_get_referer());
        }

        wp_redirect($redirect_url);
        exit;
    }

    private function init_ajax_actions() {
        // Buscar produtos (autocomplete)
        add_action('wp_ajax_wpsgl_admin_search_products', array($this, 'search_products'));
        add_action('wp_ajax_nopriv_wpsgl_admin_search_products', array($this, 'search_products'));
        
        // Importar CSV
        add_action('wp_ajax_wpsgl_admin_products_import_csv', array($this, 'import_csv'));
        
        // Exportar dados
        add_action('wp_ajax_wpsgl_admin_export_data', array($this, 'export_data'));
        
        // Obter estatísticas
        add_action('wp_ajax_wpsgl_admin_get_stats', array($this, 'get_stats'));
        
        // Salvar produto (admin-post) - MOVIDO PARA class-products.php
        // add_action('admin_post_wpsgl_save_product', array($this, 'save_product'));
    }
    
    public function search_products() {
        /*
        if (!wp_verify_nonce($_POST['nonce'], 'wpsgl_search_nonce')) {
            wp_die(json_encode(array('success' => false, 'message' => 'Invalid nonce')));
        }
        */
        
        $term = isset($_POST['term']) ? sanitize_text_field($_POST['term']) : '';
        
        if (empty($term)) {
            wp_die(json_encode(array('success' => false, 'message' => 'Empty search term')));
        }
        
        $products = $this->database->search_products($term);
        
        // Se encontrou localmente, retorna
        if (!empty($products)) {
            wp_die(json_encode(array(
                'success' => true,
                'data' => $products
            )));
        }
        
        // Se não encontrou e parece um código de barras, busca na API externa
        if (is_numeric($term) && strlen($term) > 7) {
            $api_product = $this->query_open_food_facts($term);
            if ($api_product) {
                wp_die(json_encode(array(
                    'success' => true,
                    'data' => array($api_product)
                )));
            }
        }
        
        wp_die(json_encode(array(
            'success' => true,
            'data' => array()
        )));
    }

    private function query_open_food_facts($barcode) {
        $url = "https://world.openfoodfacts.org/api/v0/product/{$barcode}.json";
        $response = wp_remote_get($url);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['status']) && $data['status'] == 1) {
            $product = $data['product'];
            // Retorna objeto compatível com a estrutura do banco local
            return (object) array(
                'id' => 0, // 0 indica novo produto
                'name' => isset($product['product_name']) ? $product['product_name'] : '',
                'category_id' => 0,
                'category_name' => '', // Categoria vazia para o usuário selecionar
                'default_price' => 0,
                'default_unit' => 'un',
                'barcode' => $barcode,
                'source' => 'api' // Marcador para o frontend saber que veio da API
            );
        }
        
        return false;
    }
    
    public function import_csv() {
        if (!current_user_can('manage_options') /* || !wp_verify_nonce($_POST['wpsgl_nonce'], 'wpsgl_import_csv') */) {
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
        $handle = fopen($file, 'r');
        
        if (!$handle) {
            wp_die(json_encode(array(
                'success' => false,
                'message' => __('Não foi possível abrir o arquivo.', 'wpsgl')
            )));
        }
        
        // Ler cabeçalho
        $header = fgetcsv($handle, 0, ';');
        
        // Validar cabeçalho
        $expected_header = array('Nome', 'Categoria', 'Preço Padrão', 'Unidade Padrão', 'Código de Barras');
        
        if ($header !== $expected_header) {
            fclose($handle);
            wp_die(json_encode(array(
                'success' => false,
                'message' => __('Cabeçalho do CSV inválido.', 'wpsgl')
            )));
        }
        
        global $wpdb;
        $prefix = $wpdb->prefix . 'wpsgl_';
        $imported = 0;
        $skipped = 0;
        $errors = array();
        
        // Processar linhas
        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            if (count($row) !== 5) {
                $skipped++;
                continue;
            }
            
            // Sanitizar dados
            $name = sanitize_text_field($row[0]);
            $category_name = sanitize_text_field($row[1]);
            $price = str_replace(',', '.', $row[2]);
            $price = floatval($price);
            $unit = sanitize_text_field($row[3]);
            $barcode = sanitize_text_field($row[4]);
            
            // Verificar se categoria existe
            $category_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$prefix}categories WHERE name = %s",
                $category_name
            ));
            
            if (!$category_id) {
                // Criar nova categoria
                $wpdb->insert($prefix . 'categories', array('name' => $category_name));
                $category_id = $wpdb->insert_id;
            }
            
            // Verificar duplicidade por código de barras
            if (!empty($barcode)) {
                $exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$prefix}products WHERE barcode = %s",
                    $barcode
                ));
                
                if ($exists) {
                    $skipped++;
                    continue;
                }
            }
            
            // Verificar duplicidade por nome
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$prefix}products WHERE name = %s",
                $name
            ));
            
            if ($exists) {
                $skipped++;
                continue;
            }
            
            // Inserir produto
            $result = $wpdb->insert($prefix . 'products', array(
                'name' => $name,
                'category_id' => $category_id,
                'default_price' => $price,
                'default_unit' => $unit,
                'barcode' => $barcode
            ));
            
            if ($result) {
                $imported++;
            } else {
                $skipped++;
                $errors[] = $name;
            }
        }
        
        fclose($handle);
        
        wp_die(json_encode(array(
            'success' => true,
            'message' => sprintf(__('Importação concluída: %d produtos importados, %d ignorados.', 'wpsgl'), $imported, $skipped),
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors
        )));
    }
} 
