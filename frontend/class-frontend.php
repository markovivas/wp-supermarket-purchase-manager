<?php
class WPSGL_Frontend {
    
    private $database;
    
    public function __construct() {
        $this->database = new WPSGL_Database();
        $this->init_hooks();
    }
    
    private function init_hooks() {
        add_action('wp_ajax_wpsgl_search_products_frontend', array($this, 'handle_ajax_search'));
        add_action('wp_ajax_nopriv_wpsgl_search_products_frontend', array($this, 'handle_ajax_search'));
        // Garantir que os assets do frontend sejam carregados quando necessário
        add_action('wp_enqueue_scripts', array($this, 'maybe_enqueue_assets'));
    }
    
    public function handle_ajax_search() {
        $term = isset($_REQUEST['term']) ? sanitize_text_field($_REQUEST['term']) : '';
        
        if (strlen($term) < 2) {
            wp_send_json(array(
                'success' => false,
                'message' => __('Digite pelo menos 2 caracteres.', 'wpsgl')
            ));
        }
        
        // Preparar resultados locais
        $local_results = array();
        $products = $this->database->search_products($term);
        foreach ($products as $product) {
            $local_results[] = array(
                'id' => $product->id,
                'name' => $product->name,
                'category_id' => $product->category_id,
                'category_name' => $product->category_name,
                'default_price' => $product->default_price,
                'default_unit' => $product->default_unit,
                'barcode' => $product->barcode,
                'source' => 'local',
                'display' => $product->name . ' (' . $product->category_name . ') - R$ ' . number_format($product->default_price, 2, ',', '.')
            );
        }

        // Buscar na API OpenFoodFacts (apenas resultados da API são cacheados)
        $api_results = array();
        $is_barcode = preg_match('/^[0-9]{8,14}$/', $term);
        $cache_key = 'wpsgl_off_' . md5($term . ($is_barcode ? '_barcode' : '_search'));
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            $api_results = $cached;
        } else {
            if ($is_barcode) {
                $url = 'https://world.openfoodfacts.org/api/v0/product/' . rawurlencode($term) . '.json';
                $resp = wp_remote_get($url, array('timeout' => 5));
                if (!is_wp_error($resp) && wp_remote_retrieve_response_code($resp) == 200) {
                    $body = wp_remote_retrieve_body($resp);
                    $data = json_decode($body, true);
                    if ($data && isset($data['status']) && intval($data['status']) === 1 && !empty($data['product'])) {
                        $p = $data['product'];
                        $name = !empty($p['product_name']) ? $p['product_name'] : (!empty($p['brands']) ? $p['brands'] : ('Produto ' . $term));
                        $cat = '';
                        if (!empty($p['categories_tags']) && is_array($p['categories_tags'])) {
                            $cat = preg_replace('/^\w+:/', '', $p['categories_tags'][0]);
                        } elseif (!empty($p['categories'])) {
                            $cat = $p['categories'];
                        }

                        // Tentar extrair preço quando disponível
                        $api_price = 0;
                        if (!empty($p['price'])) {
                            $price_str = preg_replace('/[^0-9\.,]/', '', $p['price']);
                            $price_str = str_replace(',', '.', $price_str);
                            $api_price = floatval($price_str);
                        } elseif (!empty($p['product_price'])) {
                            $price_str = preg_replace('/[^0-9\.,]/', '', $p['product_price']);
                            $price_str = str_replace(',', '.', $price_str);
                            $api_price = floatval($price_str);
                        }

                        $api_results[] = array(
                            'id' => 0,
                            'name' => $name,
                            'category_id' => 0,
                            'category_name' => $cat,
                            'default_price' => $api_price,
                            'default_unit' => null,
                            'barcode' => $term,
                            'source' => 'api',
                            'display' => $name . ($cat ? ' (' . $cat . ')' : '')
                        );
                    }
                }

                // Cache para códigos de barras: 12 horas
                $ttl = apply_filters('wpsgl_off_cache_ttl', 12 * HOUR_IN_SECONDS, $term, 'barcode');
                if (!empty($api_results)) {
                    set_transient($cache_key, $api_results, $ttl);
                }
            } else {
                $url = 'https://world.openfoodfacts.org/cgi/search.pl?search_terms=' . rawurlencode($term) . '&search_simple=1&action=process&json=1&page_size=5';
                $resp = wp_remote_get($url, array('timeout' => 5));
                if (!is_wp_error($resp) && wp_remote_retrieve_response_code($resp) == 200) {
                    $body = wp_remote_retrieve_body($resp);
                    $data = json_decode($body, true);
                    if ($data && !empty($data['products']) && is_array($data['products'])) {
                        foreach (array_slice($data['products'], 0, 5) as $p) {
                            $barcode = !empty($p['code']) ? $p['code'] : '';
                            $name = !empty($p['product_name']) ? $p['product_name'] : (!empty($p['brands']) ? $p['brands'] : 'Produto');
                            $cat = !empty($p['categories']) ? $p['categories'] : '';

                            // tentar extrair preço
                            $api_price = 0;
                            if (!empty($p['price'])) {
                                $price_str = preg_replace('/[^0-9\.,]/', '', $p['price']);
                                $price_str = str_replace(',', '.', $price_str);
                                $api_price = floatval($price_str);
                            }

                            $api_results[] = array(
                                'id' => 0,
                                'name' => $name,
                                'category_id' => 0,
                                'category_name' => $cat,
                                'default_price' => $api_price,
                                'default_unit' => null,
                                'barcode' => $barcode,
                                'source' => 'api',
                                'display' => $name . ($cat ? ' (' . $cat . ')' : '')
                            );
                        }
                    }
                }

                // Cache para buscas por termo: 1 hora
                $ttl = apply_filters('wpsgl_off_cache_ttl', HOUR_IN_SECONDS, $term, 'search');
                if (!empty($api_results)) {
                    set_transient($cache_key, $api_results, $ttl);
                }
            }
        }

        // Mesclar: priorizar API para buscas por código de barras
        if ($is_barcode) {
            $results = array_merge($api_results, $local_results);
        } else {
            $results = array_merge($local_results, $api_results);
        }

        wp_send_json(array(
            'success' => true,
            'results' => $results,
            'meta' => array(
                'local_count' => count($local_results),
                'api_count' => count($api_results)
            )
        ));
    }

    /**
     * Enfileira os assets do frontend quando necessário.
     * Será enfileirado para usuários logados no front-end (onde o formulário faz sentido).
     */
    public function maybe_enqueue_assets() {
        // Enfileirar somente no frontend e para usuários logados
        if (is_admin() || !is_user_logged_in()) {
            return;
        }

        // Registrar e enfileirar CSS/JS apenas se ainda não foi
        wp_enqueue_style('wpsgl-frontend', WPSGL_PLUGIN_URL . 'frontend/assets/css/frontend-style.css', array(), WPSGL_VERSION);
        wp_enqueue_script('wpsgl-frontend', WPSGL_PLUGIN_URL . 'frontend/assets/js/frontend-script.js', array('jquery'), WPSGL_VERSION, true);

        wp_localize_script('wpsgl-frontend', 'wpsgl_frontend', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wpsgl_add_purchase_nonce'),
            'loading_text' => __('Processando...', 'wpsgl'),
            'success_text' => __('Compra registrada com sucesso!', 'wpsgl'),
            'error_text' => __('Erro ao registrar compra. Tente novamente.', 'wpsgl'),
            // Indica se o usuário atual tem capability de administrador (usar para debug automático)
            'is_admin_user' => current_user_can('manage_options') ? true : false
        ));
    }
} 
