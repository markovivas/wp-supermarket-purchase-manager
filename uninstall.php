<?php
// Verificar se foi chamado pelo WordPress
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Evitar acesso direto
if (!defined('ABSPATH')) {
    exit;
}

// Carregar arquivo principal para ter acesso às constantes
if (!class_exists('WP_Supermarket_Purchase_Manager')) {
    require_once plugin_dir_path(__FILE__) . 'wp-supermarket-purchase-manager.php';
}

global $wpdb;
$prefix = $wpdb->prefix . 'wpsgl_';

// Opção para remover ou manter dados
$remove_data = get_option('wpsgl_remove_data_on_uninstall', false);

if ($remove_data) {
    // Remover todas as tabelas na ordem correta (devido às foreign keys)
    $tables = array(
        'purchases',    // Primeiro porque tem foreign keys
        'products',     // Depois produtos
        'categories',   // Categorias
        'stores'        // Lojas
    );
    
    foreach ($tables as $table) {
        $table_name = $prefix . $table;
        $wpdb->query("DROP TABLE IF EXISTS {$table_name}");
    }
    
    // Remover opções
    $options = array(
        'wpsgl_version',
        'wpsgl_remove_data_on_uninstall',
        'wpsgl_settings',
        'wpsgl_db_version'
    );
    
    foreach ($options as $option) {
        delete_option($option);
        delete_site_option($option); // Para multisite
    }
    
    // Remover transientes
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wpsgl_%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_wpsgl_%'");
    
    // Remover transientes de rede (multisite)
    if (is_multisite()) {
        $wpdb->query("DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE '_site_transient_wpsgl_%'");
        $wpdb->query("DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE '_site_transient_timeout_wpsgl_%'");
    }
    
    // Limpar cache
    wp_cache_flush();
}