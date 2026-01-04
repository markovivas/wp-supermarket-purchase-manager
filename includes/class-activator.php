<?php
class WPSGL_Activator {
    
    public static function activate() {
        // Verificar versão mínima do PHP
        if (version_compare(PHP_VERSION, '8.0', '<')) {
            wp_die(__('Este plugin requer PHP 8.0 ou superior. Sua versão atual é: ' . PHP_VERSION, 'wpsgl'));
        }
        
        // Verificar versão mínima do WordPress
        if (version_compare(get_bloginfo('version'), '6.0', '<')) {
            wp_die(__('Este plugin requer WordPress 6.0 ou superior.', 'wpsgl'));
        }
        
        self::create_tables();
        self::set_default_data();
        
        // Adicionar versão do banco de dados
        add_option('wpsgl_db_version', '1.0.0');
        add_option('wpsgl_version', WPSGL_VERSION);
        
        // Opção para remover dados na desinstalação (padrão: false)
        add_option('wpsgl_remove_data_on_uninstall', false);
        
        flush_rewrite_rules();
    }
    
    private static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $prefix = $wpdb->prefix . WPSGL_PREFIX;
        
        // SQL para criação das tabelas
        $sql = array();
        
        // Tabela de categorias
        $sql[] = "CREATE TABLE {$prefix}categories (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY name_unique (name)
        ) $charset_collate;";
        
        // Tabela de lojas
        $sql[] = "CREATE TABLE {$prefix}stores (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            address text,
            created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY name_unique (name)
        ) $charset_collate;";
        
        // Tabela de produtos
        $sql[] = "CREATE TABLE {$prefix}products (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            category_id mediumint(9) NOT NULL,
            default_price decimal(10,2) DEFAULT '0.00',
            default_unit varchar(50) DEFAULT 'un',
            barcode varchar(100),
            created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id),
            KEY category_id (category_id),
            KEY barcode (barcode)
        ) $charset_collate;";
        
        // Tabela de compras
        $sql[] = "CREATE TABLE {$prefix}purchases (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            product_id mediumint(9) NOT NULL,
            category_id mediumint(9) NOT NULL,
            store_id mediumint(9) NOT NULL,
            quantity decimal(10,3) NOT NULL,
            unit varchar(50) NOT NULL,
            unit_price decimal(10,2) NOT NULL,
            total_price decimal(10,2) NOT NULL,
            purchase_date date NOT NULL,
            purchase_time time,
            notes text,
            created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id),
            KEY product_id (product_id),
            KEY category_id (category_id),
            KEY store_id (store_id),
            KEY purchase_date (purchase_date)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        foreach ($sql as $query) {
            dbDelta($query);
        }
    }
    
    private static function set_default_data() {
        global $wpdb;
        $prefix = $wpdb->prefix . WPSGL_PREFIX;
        
        // Inserir categorias padrão
        $default_categories = array(
            'Padaria',
            'Açougue',
            'Hortifrúti',
            'Laticínios',
            'Bebidas',
            'Limpeza',
            'Higiene',
            'Enlatados',
            'Grãos',
            'Congelados'
        );
        
        foreach ($default_categories as $category) {
            $wpdb->insert(
                $prefix . 'categories',
                array('name' => sanitize_text_field($category)),
                array('%s')
            );
        }
        
        // Inserir lojas padrão
        $default_stores = array(
            'Supermercado A',
            'Supermercado B',
            'Mercadinho',
            'Atacado',
            'Feira'
        );
        
        foreach ($default_stores as $store) {
            $wpdb->insert(
                $prefix . 'stores',
                array('name' => sanitize_text_field($store)),
                array('%s')
            );
        }
    }
    
    public static function deactivate() {
        // Não removemos dados na desativação
        flush_rewrite_rules();
    }
} 
