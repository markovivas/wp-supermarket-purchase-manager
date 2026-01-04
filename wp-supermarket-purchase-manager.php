<?php
/**
 * Plugin Name: WP Supermarket Purchase Manager
 * Plugin URI: https://seusite.com/
 * Description: Sistema de controle de compras de supermercado/loja
 * Version: 1.0.0
 * Author: Seu Nome
 * License: GPL v2 or later
 * Text Domain: wpsgl
 * Requires PHP: 8.0
 * Requires at least: 6.0
 */

// Evitar acesso direto
if (!defined('ABSPATH')) {
    exit;
}

// Verificar se a classe já existe para evitar declaração duplicada
if (!class_exists('WP_Supermarket_Purchase_Manager')) :

// Define constants
define('WPSGL_VERSION', '1.0.0');
define('WPSGL_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPSGL_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WPSGL_PREFIX', 'wpsgl_');

// Include required files
require_once WPSGL_PLUGIN_DIR . 'includes/class-activator.php';
require_once WPSGL_PLUGIN_DIR . 'includes/class-database.php';
require_once WPSGL_PLUGIN_DIR . 'includes/class-ajax-handler.php';
require_once WPSGL_PLUGIN_DIR . 'includes/class-shortcode.php';
require_once WPSGL_PLUGIN_DIR . 'includes/class-utils.php';

/**
 * Classe principal do plugin
 */
class WP_Supermarket_Purchase_Manager {
    
    private static $instance = null;
    private $database;
    private $ajax_handler;
    private $shortcode;
    private $admin;
    private $frontend;
    
    /**
     * Obter instância única da classe (Singleton)
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Construtor privado (Singleton)
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    /**
     * Carregar dependências
     */
    private function load_dependencies() {
        $this->database = new WPSGL_Database();
        $this->ajax_handler = new WPSGL_Ajax_Handler();
        $this->shortcode = new WPSGL_Shortcode();
        
        require_once WPSGL_PLUGIN_DIR . 'frontend/class-frontend.php';
        $this->frontend = new WPSGL_Frontend();
        
        if (is_admin()) {
            require_once WPSGL_PLUGIN_DIR . 'admin/class-admin.php';
            $this->admin = new WPSGL_Admin($this->database);
        }
    }
    
    /**
     * Inicializar hooks
     */
    private function init_hooks() {
        // Activation/Deactivation hooks
        register_activation_hook(__FILE__, array('WPSGL_Activator', 'activate'));
        register_deactivation_hook(__FILE__, array('WPSGL_Activator', 'deactivate'));
        
        // Load text domain
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        
        // Inicialização do plugin
        add_action('init', array($this, 'init'));
    }
    
    /**
     * Inicializar plugin
     */
    public function init() {
        // Inicializar ações comuns
        do_action('wpsgl_init');
    }
    
    /**
     * Carregar arquivos de tradução
     */
    public function load_textdomain() {
        load_plugin_textdomain('wpsgl', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }
    
    /**
     * Obter instância do banco de dados
     */
    public function get_database() {
        return $this->database;
    }
    
    /**
     * Obter instância do AJAX handler
     */
    public function get_ajax_handler() {
        return $this->ajax_handler;
    }
    
    /**
     * Obter instância do shortcode
     */
    public function get_shortcode() {
        return $this->shortcode;
    }
}

/**
 * Função de inicialização do plugin
 */
function wpsgl_init() {
    return WP_Supermarket_Purchase_Manager::get_instance();
}

// Inicializar plugin
    add_action('plugins_loaded', 'wpsgl_init');
    
    // Auto-update database check
    add_action('admin_init', 'wpsgl_check_db');
    
    function wpsgl_check_db() {
        if (get_option('wpsgl_version') !== WPSGL_VERSION) {
            require_once WPSGL_PLUGIN_DIR . 'includes/class-activator.php';
            WPSGL_Activator::activate();
        }
    }
    
    endif; // Fim do if !class_exists
