<?php
/**
 * Template para tabela de relatórios administrativos
 * 
 * Este template pode ser sobrescrito em temas filhos
 */

if (!defined('ABSPATH')) {
    exit; // Saída se acessado diretamente
}

// Dados devem ser passados para o template
$purchases = isset($data['purchases']) ? $data['purchases'] : array();
$total_items = isset($data['total_items']) ? $data['total_items'] : 0;
$total_spent = isset($data['total_spent']) ? $data['total_spent'] : 0;
$avg_per_item = isset($data['avg_per_item']) ? $data['avg_per_item'] : 0;
$pagination = isset($data['pagination']) ? $data['pagination'] : '';
?>

<div class="wpsgl-report-table-wrapper">
    <!-- Cabeçalho do Relatório -->
    <div class="wpsgl-report-header">
        <div class="wpsgl-report-title">
            <h2><i class="dashicons dashicons-chart-bar"></i> <?php _e('Relatório de Compras', 'wpsgl'); ?></h2>
            <p class="description">
                <?php 
                $filters_text = array();
                if (!empty($data['filters']['start_date'])) {
                    $filters_text[] = sprintf(__('De: %s', 'wpsgl'), date_i18n('d/m/Y', strtotime($data['filters']['start_date'])));
                }
                if (!empty($data['filters']['end_date'])) {
                    $filters_text[] = sprintf(__('Até: %s', 'wpsgl'), date_i18n('d/m/Y', strtotime($data['filters']['end_date'])));
                }
                if (!empty($data['filters']['category_id'])) {
                    $category_name = isset($data['category_name']) ? $data['category_name'] : '';
                    $filters_text[] = sprintf(__('Categoria: %s', 'wpsgl'), $category_name);
                }
                if (!empty($data['filters']['store_id'])) {
                    $store_name = isset($data['store_name']) ? $data['store_name'] : '';
                    $filters_text[] = sprintf(__('Loja: %s', 'wpsgl'), $store_name);
                }
                
                if (!empty($filters_text)) {
                    echo '<strong>' . __('Filtros aplicados:', 'wpsgl') . '</strong> ' . implode(' | ', $filters_text);
                } else {
                    _e('Mostrando todas as compras', 'wpsgl');
                }
                ?>
            </p>
        </div>
        
        <div class="wpsgl-report-actions">
            <button type="button" class="button button-secondary" id="wpsgl-print-report">
                <i class="dashicons dashicons-printer"></i> <?php _e('Imprimir', 'wpsgl'); ?>
            </button>
            <button type="button" class="button button-secondary" id="wpsgl-download-pdf">
                <i class="dashicons dashicons-pdf"></i> <?php _e('PDF', 'wpsgl'); ?>
            </button>
            <a href="#" class="button button-primary" id="wpsgl-export-excel">
                <i class="dashicons dashicons-media-spreadsheet"></i> <?php _e('Exportar Excel', 'wpsgl'); ?>
            </a>
        </div>
    </div>
    
    <!-- Estatísticas Rápidas -->
    <div class="wpsgl-quick-stats">
        <div class="wpsgl-stat-box">
            <div class="wpsgl-stat-icon" style="background: #4CAF50;">
                <i class="dashicons dashicons-cart"></i>
            </div>
            <div class="wpsgl-stat-content">
                <span class="wpsgl-stat-label"><?php _e('Itens Comprados', 'wpsgl'); ?></span>
                <span class="wpsgl-stat-value"><?php echo number_format_i18n($total_items); ?></span>
            </div>
        </div>
        
        <div class="wpsgl-stat-box">
            <div class="wpsgl-stat-icon" style="background: #2196F3;">
                <i class="dashicons dashicons-money-alt"></i>
            </div>
            <div class="wpsgl-stat-content">
                <span class="wpsgl-stat-label"><?php _e('Total Gasto', 'wpsgl'); ?></span>
                <span class="wpsgl-stat-value">R$ <?php echo number_format_i18n($total_spent, 2); ?></span>
            </div>
        </div>
        
        <div class="wpsgl-stat-box">
            <div class="wpsgl-stat-icon" style="background: #FF9800;">
                <i class="dashicons dashicons-chart-line"></i>
            </div>
            <div class="wpsgl-stat-content">
                <span class="wpsgl-stat-label"><?php _e('Média por Item', 'wpsgl'); ?></span>
                <span class="wpsgl-stat-value">R$ <?php echo number_format_i18n($avg_per_item, 2); ?></span>
            </div>
        </div>
        
        <?php if (isset($data['shopping_days']) && $data['shopping_days'] > 0): ?>
        <div class="wpsgl-stat-box">
            <div class="wpsgl-stat-icon" style="background: #9C27B0;">
                <i class="dashicons dashicons-calendar-alt"></i>
            </div>
            <div class="wpsgl-stat-content">
                <span class="wpsgl-stat-label"><?php _e('Dias com Compras', 'wpsgl'); ?></span>
                <span class="wpsgl-stat-value"><?php echo number_format_i18n($data['shopping_days']); ?></span>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Tabela de Compras -->
    <div class="wpsgl-purchases-table-container">
        <?php if (empty($purchases)): ?>
            <div class="wpsgl-no-results">
                <div class="wpsgl-no-results-icon">
                    <i class="dashicons dashicons-warning"></i>
                </div>
                <h3><?php _e('Nenhuma compra encontrada', 'wpsgl'); ?></h3>
                <p><?php _e('Não há compras registradas para os filtros selecionados.', 'wpsgl'); ?></p>
                <a href="<?php echo esc_url(remove_query_arg(array('start_date', 'end_date', 'category_id', 'store_id', 'paged'))); ?>" class="button button-primary">
                    <?php _e('Limpar Filtros', 'wpsgl'); ?>
                </a>
            </div>
        <?php else: ?>
            <div class="wpsgl-table-responsive">
                <table class="wp-list-table widefat fixed striped wpsgl-report-table">
                    <thead>
                        <tr>
                            <th scope="col" class="column-date sortable <?php echo isset($_GET['orderby']) && $_GET['orderby'] == 'purchase_date' ? 'sorted ' . $_GET['order'] : ''; ?>">
                                <a href="<?php echo esc_url(add_query_arg(array('orderby' => 'purchase_date', 'order' => isset($_GET['order']) && $_GET['order'] == 'asc' ? 'desc' : 'asc'))); ?>">
                                    <span><?php _e('Data', 'wpsgl'); ?></span>
                                    <span class="sorting-indicator"></span>
                                </a>
                            </th>
                            <th scope="col" class="column-product"><?php _e('Produto', 'wpsgl'); ?></th>
                            <th scope="col" class="column-category"><?php _e('Categoria', 'wpsgl'); ?></th>
                            <th scope="col" class="column-quantity"><?php _e('Quantidade', 'wpsgl'); ?></th>
                            <th scope="col" class="column-unit-price"><?php _e('Preço Unitário', 'wpsgl'); ?></th>
                            <th scope="col" class="column-total"><?php _e('Total', 'wpsgl'); ?></th>
                            <th scope="col" class="column-store"><?php _e('Loja', 'wpsgl'); ?></th>
                            <th scope="col" class="column-actions"><?php _e('Ações', 'wpsgl'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($purchases as $index => $purchase): ?>
                            <tr class="<?php echo $index % 2 == 0 ? 'even' : 'odd'; ?>">
                                <td class="column-date" data-label="<?php esc_attr_e('Data', 'wpsgl'); ?>">
                                    <strong><?php echo date_i18n('d/m/Y', strtotime($purchase->purchase_date)); ?></strong>
                                    <div class="row-actions">
                                        <span class="time"><?php echo esc_html($purchase->purchase_time); ?></span>
                                    </div>
                                </td>
                                <td class="column-product" data-label="<?php esc_attr_e('Produto', 'wpsgl'); ?>">
                                    <div class="product-info">
                                        <strong><?php echo esc_html($purchase->product_name); ?></strong>
                                        <?php if (!empty($purchase->barcode)): ?>
                                            <div class="product-meta">
                                                <span class="barcode"><code><?php echo esc_html($purchase->barcode); ?></code></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="column-category" data-label="<?php esc_attr_e('Categoria', 'wpsgl'); ?>">
                                    <span class="category-badge" style="background-color: <?php echo esc_attr($this->get_category_color($purchase->category_id)); ?>;">
                                        <?php echo esc_html($purchase->category_name); ?>
                                    </span>
                                </td>
                                <td class="column-quantity" data-label="<?php esc_attr_e('Quantidade', 'wpsgl'); ?>">
                                    <div class="quantity-display">
                                        <span class="value"><?php echo number_format_i18n($purchase->quantity, 3); ?></span>
                                        <span class="unit"><?php echo esc_html($purchase->unit); ?></span>
                                    </div>
                                </td>
                                <td class="column-unit-price" data-label="<?php esc_attr_e('Preço Unitário', 'wpsgl'); ?>">
                                    <span class="price">R$ <?php echo number_format_i18n($purchase->unit_price, 2); ?></span>
                                </td>
                                <td class="column-total" data-label="<?php esc_attr_e('Total', 'wpsgl'); ?>">
                                    <strong class="total-price">R$ <?php echo number_format_i18n($purchase->total_price, 2); ?></strong>
                                </td>
                                <td class="column-store" data-label="<?php esc_attr_e('Loja', 'wpsgl'); ?>">
                                    <div class="store-info">
                                        <span class="store-name"><?php echo esc_html($purchase->store_name); ?></span>
                                        <?php if (!empty($purchase->store_address)): ?>
                                            <div class="store-meta">
                                                <small><?php echo esc_html($purchase->store_address); ?></small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="column-actions" data-label="<?php esc_attr_e('Ações', 'wpsgl'); ?>">
                                    <div class="row-actions">
                                        <span class="view">
                                            <a href="#" class="view-purchase" data-id="<?php echo esc_attr($purchase->id); ?>" 
                                               title="<?php esc_attr_e('Ver detalhes', 'wpsgl'); ?>">
                                                <i class="dashicons dashicons-visibility"></i>
                                            </a>
                                        </span>
                                        <span class="edit">
                                            <a href="<?php echo esc_url(add_query_arg(array('action' => 'edit', 'id' => $purchase->id), admin_url('admin.php?page=wpsgl-purchases'))); ?>" 
                                               title="<?php esc_attr_e('Editar compra', 'wpsgl'); ?>">
                                                <i class="dashicons dashicons-edit"></i>
                                            </a>
                                        </span>
                                        <span class="delete">
                                            <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(array('action' => 'delete', 'id' => $purchase->id)), 'delete_purchase_' . $purchase->id)); ?>" 
                                               class="delete-purchase" 
                                               title="<?php esc_attr_e('Excluir compra', 'wpsgl'); ?>"
                                               onclick="return confirm('<?php esc_attr_e('Tem certeza que deseja excluir esta compra?', 'wpsgl'); ?>');">
                                                <i class="dashicons dashicons-trash"></i>
                                            </a>
                                        </span>
                                    </div>
                                </td>
                            </tr>
                            
                            <?php if (!empty($purchase->notes)): ?>
                                <tr class="purchase-notes notes-row">
                                    <td colspan="8">
                                        <div class="notes-content">
                                            <strong><?php _e('Observações:', 'wpsgl'); ?></strong>
                                            <?php echo nl2br(esc_html($purchase->notes)); ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4" class="footer-label">
                                <strong><?php _e('Total Geral:', 'wpsgl'); ?></strong>
                            </td>
                            <td class="footer-unit-total">
                                <strong><?php _e('Soma dos Unitários:', 'wpsgl'); ?></strong><br>
                                <span class="value">R$ <?php echo number_format_i18n(array_sum(array_column($purchases, 'unit_price')), 2); ?></span>
                            </td>
                            <td class="footer-grand-total">
                                <strong><?php _e('Total Geral:', 'wpsgl'); ?></strong><br>
                                <span class="value">R$ <?php echo number_format_i18n($total_spent, 2); ?></span>
                            </td>
                            <td colspan="2" class="footer-actions">
                                <div class="footer-summary">
                                    <span class="summary-item">
                                        <strong><?php echo count($purchases); ?></strong> <?php _e('itens', 'wpsgl'); ?>
                                    </span>
                                    <span class="summary-item">
                                        <strong><?php echo number_format_i18n($avg_per_item, 2); ?></strong> <?php _e('média/item', 'wpsgl'); ?>
                                    </span>
                                </div>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            
            <!-- Paginação -->
            <?php if (!empty($pagination)): ?>
                <div class="wpsgl-pagination">
                    <?php echo $pagination; ?>
                </div>
            <?php endif; ?>
            
            <!-- Resumo por Categoria (se houver múltiplas categorias) -->
            <?php if (isset($data['summary_by_category']) && count($data['summary_by_category']) > 1): ?>
                <div class="wpsgl-category-summary">
                    <h3><i class="dashicons dashicons-category"></i> <?php _e('Resumo por Categoria', 'wpsgl'); ?></h3>
                    <div class="wpsgl-summary-chart">
                        <?php foreach ($data['summary_by_category'] as $category): ?>
                            <div class="wpsgl-summary-item">
                                <div class="wpsgl-summary-label">
                                    <span class="category-color" style="background-color: <?php echo esc_attr($this->get_category_color($category->category_id)); ?>;"></span>
                                    <?php echo esc_html($category->category_name); ?>
                                </div>
                                <div class="wpsgl-summary-bar">
                                    <div class="wpsgl-bar-container">
                                        <div class="wpsgl-bar-fill" style="width: <?php echo esc_attr($category->percentage); ?>%; 
                                            background-color: <?php echo esc_attr($this->get_category_color($category->category_id)); ?>;"></div>
                                    </div>
                                </div>
                                <div class="wpsgl-summary-values">
                                    <span class="wpsgl-summary-total">R$ <?php echo number_format_i18n($category->total, 2); ?></span>
                                    <span class="wpsgl-summary-percentage"><?php echo number_format_i18n($category->percentage, 1); ?>%</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Resumo por Loja (se houver múltiplas lojas) -->
            <?php if (isset($data['summary_by_store']) && count($data['summary_by_store']) > 1): ?>
                <div class="wpsgl-store-summary">
                    <h3><i class="dashicons dashicons-store"></i> <?php _e('Resumo por Loja', 'wpsgl'); ?></h3>
                    <div class="wpsgl-store-cards">
                        <?php foreach ($data['summary_by_store'] as $store): ?>
                            <div class="wpsgl-store-card">
                                <div class="wpsgl-store-header">
                                    <h4><?php echo esc_html($store->store_name); ?></h4>
                                    <span class="wpsgl-store-count"><?php echo intval($store->purchase_count); ?> <?php _e('compras', 'wpsgl'); ?></span>
                                </div>
                                <div class="wpsgl-store-body">
                                    <div class="wpsgl-store-total">
                                        <span class="label"><?php _e('Total Gasto:', 'wpsgl'); ?></span>
                                        <span class="value">R$ <?php echo number_format_i18n($store->total, 2); ?></span>
                                    </div>
                                    <div class="wpsgl-store-avg">
                                        <span class="label"><?php _e('Média por Compra:', 'wpsgl'); ?></span>
                                        <span class="value">R$ <?php echo number_format_i18n($store->avg_purchase, 2); ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal para visualização de detalhes -->
<div id="wpsgl-purchase-detail-modal" class="wpsgl-modal" style="display: none;">
    <div class="wpsgl-modal-content">
        <div class="wpsgl-modal-header">
            <h3><i class="dashicons dashicons-tickets-alt"></i> <?php _e('Detalhes da Compra', 'wpsgl'); ?></h3>
            <button type="button" class="wpsgl-modal-close">&times;</button>
        </div>
        <div class="wpsgl-modal-body" id="wpsgl-purchase-detail-content">
            <!-- Conteúdo carregado via AJAX -->
        </div>
        <div class="wpsgl-modal-footer">
            <button type="button" class="button button-secondary wpsgl-modal-close">
                <?php _e('Fechar', 'wpsgl'); ?>
            </button>
        </div>
    </div>
</div>

<!-- Estilos específicos para a tabela de relatórios -->
<style>
.wpsgl-report-table-wrapper {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    overflow: hidden;
}

.wpsgl-report-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    background: #f8f9fa;
    border-bottom: 1px solid #e0e0e0;
}

.wpsgl-report-title h2 {
    margin: 0 0 5px 0;
    color: #23282d;
}

.wpsgl-report-actions {
    display: flex;
    gap: 10px;
}

.wpsgl-quick-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    padding: 20px;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
}

.wpsgl-stat-box {
    background: #fff;
    border-radius: 8px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: transform 0.2s;
}

.wpsgl-stat-box:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.wpsgl-stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 24px;
}

.wpsgl-stat-content {
    flex: 1;
}

.wpsgl-stat-label {
    display: block;
    font-size: 12px;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 5px;
}

.wpsgl-stat-value {
    display: block;
    font-size: 24px;
    font-weight: bold;
    color: #343a40;
}

.wpsgl-table-responsive {
    overflow-x: auto;
}

.wpsgl-report-table {
    margin: 0;
    border: none;
}

.wpsgl-report-table th {
    background: #f8f9fa;
    font-weight: 600;
    color: #495057;
    border-bottom: 2px solid #dee2e6;
}

.wpsgl-report-table td {
    vertical-align: middle;
    padding: 12px 8px;
}

.column-date .row-actions {
    font-size: 11px;
    color: #6c757d;
}

.category-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 11px;
    color: white;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.quantity-display {
    display: flex;
    align-items: center;
    gap: 5px;
}

.quantity-display .value {
    font-weight: 600;
    color: #495057;
}

.quantity-display .unit {
    font-size: 11px;
    color: #6c757d;
    background: #f8f9fa;
    padding: 2px 6px;
    border-radius: 3px;
}

.price, .total-price {
    font-family: 'Courier New', monospace;
    font-weight: 600;
}

.total-price {
    color: #28a745;
    font-size: 14px;
}

.product-meta, .store-meta {
    font-size: 11px;
    color: #6c757d;
    margin-top: 3px;
}

.barcode {
    font-family: monospace;
    background: #f8f9fa;
    padding: 1px 4px;
    border-radius: 2px;
}

.notes-row {
    background: #f8f9fa !important;
}

.notes-content {
    padding: 10px;
    background: #fff;
    border-left: 3px solid #0073aa;
    border-radius: 3px;
    font-size: 13px;
    color: #495057;
}

.row-actions {
    display: flex;
    gap: 10px;
}

.row-actions a {
    text-decoration: none;
    color: #6c757d;
    transition: color 0.2s;
}

.row-actions a:hover {
    color: #0073aa;
}

.row-actions .delete a:hover {
    color: #dc3545;
}

tfoot tr {
    background: #f8f9fa !important;
    font-weight: 600;
}

.footer-label {
    text-align: right;
    padding-right: 20px !important;
}

.footer-unit-total, .footer-grand-total {
    text-align: center;
    border-left: 2px solid #dee2e6 !important;
}

.footer-unit-total .value, .footer-grand-total .value {
    display: block;
    font-size: 18px;
    color: #28a745;
    margin-top: 5px;
}

.footer-actions {
    text-align: right;
    padding-right: 20px !important;
}

.footer-summary {
    display: flex;
    flex-direction: column;
    gap: 5px;
    align-items: flex-end;
}

.summary-item {
    display: block;
    font-size: 12px;
    color: #6c757d;
}

.wpsgl-no-results {
    text-align: center;
    padding: 60px 20px;
}

.wpsgl-no-results-icon {
    font-size: 60px;
    color: #6c757d;
    margin-bottom: 20px;
}

.wpsgl-no-results h3 {
    color: #495057;
    margin-bottom: 10px;
}

.wpsgl-pagination {
    padding: 20px;
    background: #f8f9fa;
    border-top: 1px solid #dee2e6;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.wpsgl-category-summary, .wpsgl-store-summary {
    padding: 20px;
    border-top: 1px solid #dee2e6;
}

.wpsgl-category-summary h3, .wpsgl-store-summary h3 {
    margin-top: 0;
    margin-bottom: 20px;
    color: #495057;
}

.wpsgl-summary-chart {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.wpsgl-summary-item {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 10px 0;
    border-bottom: 1px solid #f0f0f0;
}

.wpsgl-summary-label {
    width: 150px;
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 500;
}

.category-color {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    display: inline-block;
}

.wpsgl-summary-bar {
    flex: 1;
}

.wpsgl-bar-container {
    height: 20px;
    background: #f0f0f0;
    border-radius: 10px;
    overflow: hidden;
}

.wpsgl-bar-fill {
    height: 100%;
    border-radius: 10px;
    transition: width 0.5s ease-out;
}

.wpsgl-summary-values {
    width: 120px;
    text-align: right;
    display: flex;
    flex-direction: column;
    gap: 3px;
}

.wpsgl-summary-total {
    font-weight: 600;
    color: #28a745;
}

.wpsgl-summary-percentage {
    font-size: 12px;
    color: #6c757d;
}

.wpsgl-store-cards {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 15px;
}

.wpsgl-store-card {
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    overflow: hidden;
    transition: transform 0.2s;
}

.wpsgl-store-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.wpsgl-store-header {
    background: #f8f9fa;
    padding: 15px;
    border-bottom: 1px solid #dee2e6;
}

.wpsgl-store-header h4 {
    margin: 0 0 5px 0;
    color: #495057;
}

.wpsgl-store-count {
    font-size: 12px;
    color: #6c757d;
}

.wpsgl-store-body {
    padding: 15px;
}

.wpsgl-store-total, .wpsgl-store-avg {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
}

.wpsgl-store-total .label, .wpsgl-store-avg .label {
    color: #6c757d;
}

.wpsgl-store-total .value {
    color: #28a745;
    font-weight: 600;
}

.wpsgl-store-avg .value {
    color: #0073aa;
    font-weight: 600;
}

/* Modal */
.wpsgl-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
}

.wpsgl-modal-content {
    background: #fff;
    border-radius: 8px;
    width: 90%;
    max-width: 800px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
}

.wpsgl-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    border-bottom: 1px solid #dee2e6;
    background: #f8f9fa;
    border-radius: 8px 8px 0 0;
}

.wpsgl-modal-header h3 {
    margin: 0;
    color: #495057;
}

.wpsgl-modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #6c757d;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: background 0.2s;
}

.wpsgl-modal-close:hover {
    background: #e9ecef;
    color: #495057;
}

.wpsgl-modal-body {
    padding: 20px;
}

.wpsgl-modal-footer {
    padding: 20px;
    border-top: 1px solid #dee2e6;
    text-align: right;
}

/* Responsividade */
@media (max-width: 768px) {
    .wpsgl-report-header {
        flex-direction: column;
        align-items: stretch;
        gap: 15px;
    }
    
    .wpsgl-report-actions {
        justify-content: flex-start;
        flex-wrap: wrap;
    }
    
    .wpsgl-quick-stats {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .wpsgl-store-cards {
        grid-template-columns: 1fr;
    }
    
    .wpsgl-summary-item {
        flex-direction: column;
        align-items: stretch;
        gap: 10px;
    }
    
    .wpsgl-summary-label {
        width: auto;
    }
    
    .wpsgl-summary-values {
        width: auto;
        text-align: left;
    }
}

@media (max-width: 480px) {
    .wpsgl-quick-stats {
        grid-template-columns: 1fr;
    }
    
    .wpsgl-stat-box {
        flex-direction: column;
        text-align: center;
        gap: 10px;
    }
    
    .row-actions {
        flex-direction: column;
        gap: 5px;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Modal de visualização de detalhes
    $('.view-purchase').on('click', function(e) {
        e.preventDefault();
        var purchaseId = $(this).data('id');
        
        // Mostrar modal com loading
        $('#wpsgl-purchase-detail-modal').show();
        $('#wpsgl-purchase-detail-content').html('<div class="wpsgl-loading">Carregando...</div>');
        
        // Carregar dados via AJAX
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wpsgl_get_purchase_detail',
                purchase_id: purchaseId,
                nonce: wpsgl_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#wpsgl-purchase-detail-content').html(response.data);
                } else {
                    $('#wpsgl-purchase-detail-content').html('<div class="wpsgl-error">Erro ao carregar dados</div>');
                }
            },
            error: function() {
                $('#wpsgl-purchase-detail-content').html('<div class="wpsgl-error">Erro de conexão</div>');
            }
        });
    });
    
    // Fechar modal
    $('.wpsgl-modal-close').on('click', function() {
        $(this).closest('.wpsgl-modal').hide();
    });
    
    // Fechar modal ao clicar fora
    $(document).on('click', function(e) {
        if ($(e.target).hasClass('wpsgl-modal')) {
            $(e.target).hide();
        }
    });
    
    // Imprimir relatório
    $('#wpsgl-print-report').on('click', function() {
        window.print();
    });
    
    // Exportar para Excel
    $('#wpsgl-export-excel').on('click', function(e) {
        e.preventDefault();
        
        var form = $('#wpsgl-report-filters');
        var url = ajaxurl + '?action=wpsgl_export_excel&' + form.serialize();
        window.location.href = url;
    });
    
    // Download PDF
    $('#wpsgl-download-pdf').on('click', function() {
        var button = $(this);
        var originalText = button.html();
        
        button.html('<i class="dashicons dashicons-update spin"></i> Gerando PDF...');
        button.prop('disabled', true);
        
        var form = $('#wpsgl-report-filters');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wpsgl_generate_pdf',
                filters: form.serialize(),
                nonce: wpsgl_ajax.nonce
            },
            xhrFields: {
                responseType: 'blob'
            },
            success: function(blob) {
                var link = document.createElement('a');
                link.href = window.URL.createObjectURL(blob);
                link.download = 'relatorio-compras-' + new Date().toISOString().slice(0, 10) + '.pdf';
                link.click();
                
                button.html(originalText);
                button.prop('disabled', false);
            },
            error: function() {
                alert('Erro ao gerar PDF');
                button.html(originalText);
                button.prop('disabled', false);
            }
        });
    });
    
    // Ordenação por coluna
    $('.wpsgl-report-table th.sortable a').on('click', function(e) {
        e.preventDefault();
        window.location.href = $(this).attr('href');
    });
    
    // Animações
    $('.wpsgl-bar-fill').each(function() {
        var width = $(this).css('width');
        $(this).css('width', '0');
        
        setTimeout(() => {
            $(this).animate({
                width: width
            }, 1000);
        }, 300);
    });
});

// Função para gerar cores para categorias
function getCategoryColor(categoryId) {
    var colors = [
        '#4CAF50', '#2196F3', '#FF9800', '#E91E63',
        '#9C27B0', '#3F51B5', '#00BCD4', '#8BC34A',
        '#FF5722', '#795548', '#607D8B', '#009688'
    ];
    
    return colors[categoryId % colors.length];
}
</script>