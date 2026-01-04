<?php
class WPSGL_Admin {
    
    private $database;
    private $dashboard;
    private $reports;
    private $products;
    private $categories;
    private $stores;
    
    public function __construct($database) {
        $this->database = $database;
        $this->init_pages();
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    private function init_pages() {
        require_once WPSGL_PLUGIN_DIR . 'admin/pages/class-dashboard.php';
        require_once WPSGL_PLUGIN_DIR . 'admin/pages/class-reports.php';
        require_once WPSGL_PLUGIN_DIR . 'admin/pages/class-products.php';
        require_once WPSGL_PLUGIN_DIR . 'admin/pages/class-categories.php';
        require_once WPSGL_PLUGIN_DIR . 'admin/pages/class-stores.php';
        
        $this->dashboard = new WPSGL_Dashboard_Page($this->database);
        $this->reports = new WPSGL_Reports_Page($this->database);
        $this->products = new WPSGL_Products_Page($this->database);
        $this->categories = new WPSGL_Categories_Page($this->database);
        $this->stores = new WPSGL_Stores_Page($this->database);
    }
    
    public function register_settings() {
        register_setting('wpsgl_settings', 'wpsgl_settings');
    }
}