<?php
class WPSGL_Database {
    
    private $wpdb;
    private $prefix;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->prefix = $wpdb->prefix . WPSGL_PREFIX;
    }
    
    public function reset_all() {
        $this->wpdb->query("TRUNCATE TABLE {$this->prefix}purchases");
        $this->wpdb->query("TRUNCATE TABLE {$this->prefix}products");
        $this->wpdb->query("TRUNCATE TABLE {$this->prefix}categories");
        $this->wpdb->query("TRUNCATE TABLE {$this->prefix}stores");
    }
    
    // Métodos para produtos
    public function get_products($params = array()) {
        $defaults = array(
            'per_page' => 20,
            'page' => 1,
            'search' => '',
            'category_id' => 0,
            'orderby' => 'id',
            'order' => 'DESC'
        );
        
        $params = wp_parse_args($params, $defaults);
        $offset = ($params['page'] - 1) * $params['per_page'];
        
        $where = array('1=1');
        $prepare_values = array();
        
        if (!empty($params['search'])) {
            $where[] = "(p.name LIKE %s OR p.barcode LIKE %s)";
            $search_term = '%' . $this->wpdb->esc_like($params['search']) . '%';
            $prepare_values[] = $search_term;
            $prepare_values[] = $search_term;
        }
        
        if (!empty($params['category_id'])) {
            $where[] = "p.category_id = %d";
            $prepare_values[] = intval($params['category_id']);
        }
        
        $where_clause = implode(' AND ', $where);
        
        $query = $this->wpdb->prepare(
            "SELECT SQL_CALC_FOUND_ROWS p.*, c.name as category_name 
             FROM {$this->prefix}products p 
             LEFT JOIN {$this->prefix}categories c ON p.category_id = c.id 
             WHERE {$where_clause} 
             ORDER BY {$params['orderby']} {$params['order']} 
             LIMIT %d OFFSET %d",
            array_merge($prepare_values, array($params['per_page'], $offset))
        );
        
        $results = $this->wpdb->get_results($query);
        $total = $this->wpdb->get_var("SELECT FOUND_ROWS()");
        
        return array(
            'items' => $results,
            'total' => $total,
            'pages' => ceil($total / $params['per_page'])
        );
    }
    
    public function insert_product($data) {
        $defaults = array(
            'name' => '',
            'category_id' => 0,
            'default_price' => 0,
            'default_unit' => 'un',
            'barcode' => ''
        );
        
        $data = wp_parse_args($data, $defaults);
        
        // Sanitização
        $sanitized_data = array(
            'name' => sanitize_text_field($data['name']),
            'category_id' => intval($data['category_id']),
            'default_price' => floatval($data['default_price']),
            'default_unit' => sanitize_text_field($data['default_unit']),
            'barcode' => sanitize_text_field($data['barcode'])
        );
        
        return $this->wpdb->insert(
            $this->prefix . 'products',
            $sanitized_data,
            array('%s', '%d', '%f', '%s', '%s')
        );
    }
    
    public function update_product($id, $data) {
        $sanitized_data = array();
        
        if (isset($data['name'])) {
            $sanitized_data['name'] = sanitize_text_field($data['name']);
        }
        if (isset($data['category_id'])) {
            $sanitized_data['category_id'] = intval($data['category_id']);
        }
        if (isset($data['default_price'])) {
            $sanitized_data['default_price'] = floatval($data['default_price']);
        }
        if (isset($data['default_unit'])) {
            $sanitized_data['default_unit'] = sanitize_text_field($data['default_unit']);
        }
        if (isset($data['barcode'])) {
            $sanitized_data['barcode'] = sanitize_text_field($data['barcode']);
        }
        
        return $this->wpdb->update(
            $this->prefix . 'products',
            $sanitized_data,
            array('id' => intval($id)),
            array('%s', '%d', '%f', '%s', '%s'),
            array('%d')
        );
    }
    
    public function delete_product($id) {
        return $this->wpdb->delete(
            $this->prefix . 'products',
            array('id' => intval($id)),
            array('%d')
        );
    }
    
    // Métodos para compras (purchases)
    public function insert_purchase($data) {
        $defaults = array(
            'product_id' => 0,
            'category_id' => 0,
            'store_id' => 0,
            'quantity' => 1,
            'unit' => 'un',
            'unit_price' => 0,
            'total_price' => 0,
            'purchase_date' => current_time('Y-m-d'),
            'purchase_time' => current_time('H:i:s'),
            'notes' => ''
        );
        
        $data = wp_parse_args($data, $defaults);
        
        // Calcular total se não fornecido
        if ($data['total_price'] == 0) {
            $data['total_price'] = floatval($data['quantity']) * floatval($data['unit_price']);
        }
        
        $sanitized_data = array(
            'product_id' => intval($data['product_id']),
            'category_id' => intval($data['category_id']),
            'store_id' => intval($data['store_id']),
            'quantity' => floatval($data['quantity']),
            'unit' => sanitize_text_field($data['unit']),
            'unit_price' => floatval($data['unit_price']),
            'total_price' => floatval($data['total_price']),
            'purchase_date' => sanitize_text_field($data['purchase_date']),
            'purchase_time' => sanitize_text_field($data['purchase_time']),
            'notes' => sanitize_textarea_field($data['notes'])
        );
        
        return $this->wpdb->insert(
            $this->prefix . 'purchases',
            $sanitized_data,
            array('%d', '%d', '%d', '%f', '%s', '%f', '%f', '%s', '%s', '%s')
        );
    }
    
    public function delete_purchase($id) {
        return $this->wpdb->delete(
            $this->prefix . 'purchases',
            array('id' => intval($id)),
            array('%d')
        );
    }
    
    public function get_purchases_report($filters = array()) {
        $defaults = array(
            'start_date' => date('Y-m-01'),
            'end_date' => date('Y-m-d'),
            'category_id' => 0,
            'store_id' => 0,
            'product_id' => 0
        );
        
        $filters = wp_parse_args($filters, $defaults);
        
        $where = array('1=1');
        $prepare_values = array();
        
        if (!empty($filters['start_date'])) {
            $where[] = "pu.purchase_date >= %s";
            $prepare_values[] = sanitize_text_field($filters['start_date']);
        }
        
        if (!empty($filters['end_date'])) {
            $where[] = "pu.purchase_date <= %s";
            $prepare_values[] = sanitize_text_field($filters['end_date']);
        }
        
        if (!empty($filters['category_id'])) {
            $where[] = "pu.category_id = %d";
            $prepare_values[] = intval($filters['category_id']);
        }
        
        if (!empty($filters['store_id'])) {
            $where[] = "pu.store_id = %d";
            $prepare_values[] = intval($filters['store_id']);
        }
        
        if (!empty($filters['product_id'])) {
            $where[] = "pu.product_id = %d";
            $prepare_values[] = intval($filters['product_id']);
        }
        
        $where_clause = implode(' AND ', $where);
        
        $query = $this->wpdb->prepare(
            "SELECT pu.*, 
                    pr.name as product_name,
                    pr.barcode as barcode,
                    c.name as category_name,
                    s.name as store_name
             FROM {$this->prefix}purchases pu
             LEFT JOIN {$this->prefix}products pr ON pu.product_id = pr.id
             LEFT JOIN {$this->prefix}categories c ON pu.category_id = c.id
             LEFT JOIN {$this->prefix}stores s ON pu.store_id = s.id
             WHERE {$where_clause}
             ORDER BY pu.purchase_date DESC, pu.purchase_time DESC",
            $prepare_values
        );
        
        return $this->wpdb->get_results($query);
    }
    
    public function get_monthly_stats($month = null, $year = null) {
        if (!$month) $month = date('m');
        if (!$year) $year = date('Y');
        
        $start_date = "{$year}-{$month}-01";
        $end_date = date('Y-m-t', strtotime($start_date));
        
        $query = $this->wpdb->prepare(
            "SELECT 
                COUNT(*) as total_items,
                SUM(total_price) as total_spent,
                AVG(total_price) as avg_per_item,
                COUNT(DISTINCT purchase_date) as shopping_days,
                AVG(total_price) as avg_per_day
             FROM {$this->prefix}purchases
             WHERE purchase_date BETWEEN %s AND %s",
            array($start_date, $end_date)
        );
        
        $overall = $this->wpdb->get_row($query);
        
        // Estatísticas por categoria
        $categories_query = $this->wpdb->prepare(
            "SELECT c.name, 
                    SUM(pu.total_price) as total,
                    COUNT(pu.id) as items,
                    ROUND((SUM(pu.total_price) / %f * 100), 2) as percentage
             FROM {$this->prefix}purchases pu
             LEFT JOIN {$this->prefix}categories c ON pu.category_id = c.id
             WHERE pu.purchase_date BETWEEN %s AND %s
             GROUP BY pu.category_id
             ORDER BY total DESC",
            array($overall->total_spent ?: 1, $start_date, $end_date)
        );
        
        $by_category = $this->wpdb->get_results($categories_query);
        
        return array(
            'overall' => $overall,
            'by_category' => $by_category,
            'month' => $month,
            'year' => $year
        );
    }
    
    // Métodos para categorias
    public function get_categories() {
        return $this->wpdb->get_results(
            "SELECT * FROM {$this->prefix}categories ORDER BY name ASC"
        );
    }
    
    public function get_stores() {
        return $this->wpdb->get_results(
            "SELECT * FROM {$this->prefix}stores ORDER BY name ASC"
        );
    }
    
    public function search_products($term) {
        $like = '%' . $this->wpdb->esc_like($term) . '%';
        $query = $this->wpdb->prepare(
            "SELECT p.*, c.name as category_name 
             FROM {$this->prefix}products p 
             LEFT JOIN {$this->prefix}categories c ON p.category_id = c.id 
             WHERE p.name LIKE %s 
                OR p.barcode = %s 
                OR p.barcode LIKE %s 
             LIMIT 10",
            array($like, $term, $like)
        );
        
        return $this->wpdb->get_results($query);
    }

    /**
     * Retorna a query SQL preparada utilizada para a busca de produtos (útil para diagnóstico).
     * @param string $term
     * @return string Prepared SQL query
     */
    public function get_search_products_query($term) {
        $like = '%' . $this->wpdb->esc_like($term) . '%';
        $query = $this->wpdb->prepare(
            "SELECT p.*, c.name as category_name 
             FROM {$this->prefix}products p 
             LEFT JOIN {$this->prefix}categories c ON p.category_id = c.id 
             WHERE p.name LIKE %s 
                OR p.barcode = %s 
                OR p.barcode LIKE %s 
             LIMIT 10",
            array($like, $term, $like)
        );

        return $query;
    }

    /**
     * Executa e retorna os resultados da query de busca de produtos (útil para diagnóstico).
     * @param string $term
     * @return array
     */
    public function get_search_products_results($term) {
        $query = $this->get_search_products_query($term);
        return $this->wpdb->get_results($query);
    }
    
    // Exportação para CSV
    public function export_purchases_csv($filters = array()) {
        $purchases = $this->get_purchases_report($filters);
        
        $filename = 'compras_' . date('Y-m-d_H-i-s') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        
        $output = fopen('php://output', 'w');
        
        // Cabeçalho
        fputcsv($output, array(
            'Data',
            'Hora',
            'Produto',
            'Código de Barras',
            'Categoria',
            'Quantidade',
            'Unidade',
            'Preço Unitário',
            'Total',
            'Loja',
            'Observações'
        ), ';');
        
        // Dados
        foreach ($purchases as $purchase) {
            fputcsv($output, array(
                $purchase->purchase_date,
                $purchase->purchase_time,
                $purchase->product_name,
                $purchase->barcode,
                $purchase->category_name,
                $purchase->quantity,
                $purchase->unit,
                number_format($purchase->unit_price, 2, ',', '.'),
                number_format($purchase->total_price, 2, ',', '.'),
                $purchase->store_name,
                $purchase->notes
            ), ';');
        }
        
        fclose($output);
        exit;
    }
} 
