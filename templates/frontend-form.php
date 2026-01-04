<?php
/**
 * Template para o formulário de registro de compras (frontend)
 * 
 * Este template pode ser sobrescrito em temas filhos
 */

if (!defined('ABSPATH')) {
    exit; // Saída se acessado diretamente
}

// Verificar se o usuário está logado
if (!is_user_logged_in()) {
    echo '<div class="wpsgl-login-required">';
    echo '<p>' . __('Você precisa estar logado para registrar compras.', 'wpsgl') . '</p>';
    echo wp_login_form(array('echo' => false));
    echo '</div>';
    return;
}

// Obter dados do banco
global $wpdb;
$prefix = $wpdb->prefix . 'wpsgl_';

$categories = $wpdb->get_results("SELECT * FROM {$prefix}categories ORDER BY name ASC");
$stores = $wpdb->get_results("SELECT * FROM {$prefix}stores ORDER BY name ASC");
?>

<div class="wpsgl-registration-form-wrapper">
    <div class="wpsgl-registration-form">
        <h2><i class="dashicons dashicons-cart"></i> <?php _e('Registro de Compras', 'wpsgl'); ?></h2>
        
        <div class="wpsgl-info-card">
            <h4><i class="dashicons dashicons-info"></i> <?php _e('Como usar:', 'wpsgl'); ?></h4>
            <p><?php _e('Preencha os dados da sua compra abaixo. Você pode buscar produtos pelo nome ou código de barras.', 'wpsgl'); ?></p>
        </div>
        
        <div id="wpsgl-message" class="wpsgl-message" style="display: none;"></div>
        
        <form id="wpsgl-purchase-form" class="wpsgl-validate-form">
            <?php wp_nonce_field('wpsgl_add_purchase', 'wpsgl_nonce'); ?>
            
            <?php if (apply_filters('wpsgl_show_search', true)): ?>
            <!-- Seção de busca de produto -->
            <div class="wpsgl-form-row">
                <div class="wpsgl-form-group wpsgl-form-group-full">
                    <label for="product_search">
                        <?php _e('Buscar Produto Existente:', 'wpsgl'); ?>
                        <span class="wpsgl-tooltip" data-tooltip="<?php esc_attr_e('Digite para buscar produtos já cadastrados', 'wpsgl'); ?>">
                            <i class="dashicons dashicons-editor-help"></i>
                        </span>
                    </label>
                    <div class="wpsgl-input-with-icon">
                        <i class="dashicons dashicons-search"></i>
                        <input type="text" id="product_search" class="wpsgl-autocomplete wpsgl-live-search" 
                               placeholder="<?php esc_attr_e('Digite o nome do produto...', 'wpsgl'); ?>" 
                               autocomplete="off">
                    </div>
                    <div id="wpsgl-autocomplete-results" class="wpsgl-autocomplete-results"></div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Seção de código de barras -->
            <div class="wpsgl-form-row">
                <div class="wpsgl-form-group wpsgl-form-group-full">
                    <label for="barcode"><?php _e('Código de Barras:', 'wpsgl'); ?></label>
                    <div class="wpsgl-barcode-section">
                        <div class="wpsgl-barcode-input">
                            <input type="text" id="barcode" name="barcode" 
                                   placeholder="<?php esc_attr_e('Digite ou escaneie o código de barras', 'wpsgl'); ?>">
                        </div>
                        <button type="button" id="scan-barcode" class="wpsgl-barcode-btn">
                            <i class="dashicons dashicons-scanner"></i> <?php _e('Escanear', 'wpsgl'); ?>
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="wpsgl-form-row">
                <div class="wpsgl-form-group">
                    <label for="product_name"><?php _e('Nome do Produto:', 'wpsgl'); ?> *</label>
                    <input type="text" id="product_name" name="product_name" required 
                           placeholder="<?php esc_attr_e('Ex: Arroz, Feijão, etc.', 'wpsgl'); ?>">
                    <span class="wpsgl-valid-feedback" style="display: none;"><?php _e('Produto válido', 'wpsgl'); ?></span>
                    <span class="wpsgl-invalid-feedback" style="display: none;"><?php _e('Este campo é obrigatório', 'wpsgl'); ?></span>
                </div>
                
                <div class="wpsgl-form-group">
                    <label for="category_id"><?php _e('Categoria:', 'wpsgl'); ?> *</label>
                    <select id="category_id" name="category_id" required>
                        <option value=""><?php _e('Selecione uma categoria...', 'wpsgl'); ?></option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo esc_attr($category->id); ?>">
                                <?php echo esc_html($category->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="wpsgl-form-row">
                <div class="wpsgl-form-group">
                    <label for="store_id"><?php _e('Loja/Mercado:', 'wpsgl'); ?> *</label>
                    <select id="store_id" name="store_id" required>
                        <option value=""><?php _e('Selecione uma loja...', 'wpsgl'); ?></option>
                        <?php foreach ($stores as $store): ?>
                            <option value="<?php echo esc_attr($store->id); ?>">
                                <?php echo esc_html($store->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="wpsgl-form-group">
                    <label for="purchase_date"><?php _e('Data da Compra:', 'wpsgl'); ?> *</label>
                    <input type="date" id="purchase_date" name="purchase_date" 
                           value="<?php echo esc_attr(date('Y-m-d')); ?>" required>
                </div>
                
                <div class="wpsgl-form-group">
                    <label for="purchase_time"><?php _e('Hora:', 'wpsgl'); ?> *</label>
                    <input type="time" id="purchase_time" name="purchase_time" 
                           value="<?php echo esc_attr(date('H:i')); ?>" required>
                </div>
            </div>
            
            <div class="wpsgl-form-row">
                <div class="wpsgl-form-group">
                    <label for="quantity"><?php _e('Quantidade:', 'wpsgl'); ?> *</label>
                    <input type="number" id="quantity" name="quantity" step="0.001" min="0.001" value="1" required>
                </div>
                
                <div class="wpsgl-form-group">
                    <label for="unit"><?php _e('Unidade:', 'wpsgl'); ?> *</label>
                    <select id="unit" name="unit" required>
                        <option value="un"><?php _e('Unidade', 'wpsgl'); ?></option>
                        <option value="kg"><?php _e('Kg', 'wpsgl'); ?></option>
                        <option value="g"><?php _e('Gramas', 'wpsgl'); ?></option>
                        <option value="l"><?php _e('Litro', 'wpsgl'); ?></option>
                        <option value="ml"><?php _e('ML', 'wpsgl'); ?></option>
                        <option value="cx"><?php _e('Caixa', 'wpsgl'); ?></option>
                        <option value="pct"><?php _e('Pacote', 'wpsgl'); ?></option>
                        <option value="dz"><?php _e('Dúzia', 'wpsgl'); ?></option>
                    </select>
                </div>
                
                <div class="wpsgl-form-group">
                    <label for="unit_price"><?php _e('Preço Unitário (R$):', 'wpsgl'); ?> *</label>
                    <input type="number" id="unit_price" name="unit_price" step="0.01" min="0" required>
                </div>
                
                <div class="wpsgl-form-group">
                    <label for="total_price"><?php _e('Total (R$):', 'wpsgl'); ?></label>
                    <input type="number" id="total_price" name="total_price" step="0.01" min="0" readonly>
                </div>
            </div>
            
            <div class="wpsgl-form-row">
                <div class="wpsgl-form-group wpsgl-form-group-full">
                    <label for="notes"><?php _e('Observações:', 'wpsgl'); ?></label>
                    <textarea id="notes" name="notes" rows="3" 
                              placeholder="<?php esc_attr_e('Observações sobre a compra (opcional)...', 'wpsgl'); ?>"></textarea>
                </div>
            </div>
            
            <div class="wpsgl-form-actions">
                <button type="submit" class="wpsgl-submit-button">
                    <i class="dashicons dashicons-yes-alt"></i> <?php _e('Registrar Compra', 'wpsgl'); ?>
                </button>
                <button type="reset" class="wpsgl-reset-button">
                    <i class="dashicons dashicons-dismiss"></i> <?php _e('Limpar Formulário', 'wpsgl'); ?>
                </button>
            </div>
            
            <input type="hidden" id="product_id" name="product_id" value="0">
        </form>
        
        <div class="wpsgl-form-footer">
            <p class="description">
                <i class="dashicons dashicons-lightbulb"></i> 
                <?php _e('Dica: Use a busca para encontrar produtos já cadastrados e preencher automaticamente os campos.', 'wpsgl'); ?>
            </p>
        </div>
    </div>
</div>

<script type="text/template" id="wpsgl-autocomplete-template">
    <div class="wpsgl-autocomplete-item" data-id="<%= id %>" data-name="<%= name %>" data-category="<%= category_id %>" data-price="<%= default_price %>" data-unit="<%= default_unit %>">
        <strong><%= name %></strong>
        <span class="category"><%= category_name %></span>
        <span class="price">R$ <%= default_price_formatted %></span>
    </div>
</script>

<script type="text/template" id="wpsgl-success-template">
    <div class="wpsgl-message success">
        <p><%= message %></p>
        <% if (purchase_id) { %>
            <p><small><?php _e('ID do registro:', 'wpsgl'); ?> <%= purchase_id %></small></p>
        <% } %>
    </div>
</script> 
