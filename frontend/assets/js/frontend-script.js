(function($) {
    'use strict';
    
    $(document).ready(function() {
        var wpsgl = window.wpsgl_frontend || {};
        
        // Setup autocomplete container
        var $input = $('#product_search');
        var $results = $('<div class="wpsgl-autocomplete-results"></div>');
        
        if ($input.length) {
            var $wrapper = $('<div style="position:relative; flex-grow:1;"></div>');
            $input.wrap($wrapper);
            $input.parent().append($results);
        }
        
        var $status = $('#wpsgl-search-status');
        function setSearchStatus(message, type) {
            if (!$status.length) return;
            if (!message) {
                $status.hide().text('').removeClass('info warning');
                return;
            }
            $status.removeClass('info warning').addClass(type || 'info').text(message).show();
        }
        
        // Hide results when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.wpsgl-autocomplete-results').length && !$(e.target).is($input)) {
                $results.hide();
            }
        });

        var previousAjax = null;
        var debounceTimer = null;
        function searchProducts(autoSelect) {
            autoSelect = autoSelect || false;
            var searchTerm = ($input.val() || '').trim();

            if (searchTerm.length < 2) {
                $results.hide();
                setSearchStatus('', '');
                return;
            }

            var $btn = $('#product_search_btn');
            var originalBtnText = $btn.html();
            if (autoSelect) {
                $btn.prop('disabled', true).html('<i class="dashicons dashicons-update"></i> ...');
                setSearchStatus('Buscando...', 'info');
            }

            if (previousAjax && previousAjax.readyState !== 4) {
                try { previousAjax.abort(); } catch (e) {}
            }

            previousAjax = $.ajax({
                url: wpsgl.ajax_url,
                method: 'POST',
                data: {
                    action: 'wpsgl_search_products_frontend',
                    term: searchTerm,
                    debug: (wpsgl_frontend && wpsgl_frontend.is_admin_user) ? 1 : 0
                },
                cache: false,
                success: function(response) {
                    if (typeof response === 'string') {
                        try {
                            response = JSON.parse(response);
                        } catch (e) {
                            setSearchStatus('Erro ao interpretar resposta. Tente novamente.', 'warning');
                            $results.hide();
                            return;
                        }
                    }

                    if (!response || !response.success) {
                        setSearchStatus(response && response.message ? response.message : 'Nenhum produto cadastrado para esta busca.', 'warning');
                        $results.hide();
                        return;
                    }

                    var list = response.results || [];
                    var meta = response.meta || { local_count: 0, api_count: 0 };

                    // wpsgl-debug: mostrar resultado da busca no console (temporário)
                    try { console.log('wpsgl-debug: searchProducts response', { list: list, meta: meta, term: searchTerm }); } catch (e) {}

                    if (list.length === 0) {
                        setSearchStatus('Nenhum produto cadastrado para esta busca.', 'warning');
                        $results.hide();
                        return;
                    }

                    // Se não houver produtos locais, avisar que os resultados são da API
                    if (meta.local_count === 0 && meta.api_count > 0) {
                        setSearchStatus('Nenhum produto local encontrado — mostrando resultados da API.', 'info');
                    } else {
                        setSearchStatus('', '');
                    }

                    if (autoSelect && list.length > 0) {
                        selectProduct(list[0]);
                        $results.hide();
                        return;
                    }

                    var html = '';
                    list.forEach(function(product) {
                        var productJson = JSON.stringify(product).replace(/'/g, "&#39;");
                        html += '<div class="wpsgl-autocomplete-item" data-product=\'' + productJson + '\'>';
                        html += '<strong>' + product.name + '</strong>';
                        if (product.source === 'api') {
                            html += '<span class="source" style="color: #2ecc71; font-size: 11px; margin-left: 5px;">(API)</span>';
                        }
                        if (product.category_name) {
                            html += '<span class="category">(' + product.category_name + ')</span>';
                        }
                        if (product.default_price > 0) {
                            html += '<span class="price">R$ ' + parseFloat(product.default_price).toFixed(2).replace('.', ',') + '</span>';
                        }
                        html += '</div>';
                    });

                    $results.html(html).show();
                    setSearchStatus('', '');
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    var msg = 'Erro ao buscar produtos. Tente novamente.';
                    var code = jqXHR && jqXHR.status ? jqXHR.status : 0;
                    if (code) {
                        msg += ' Código: ' + code;
                    }

                    // Construir dados de debug (temporário) para ajudar no diagnóstico
                    var debugData = {
                        url: wpsgl.ajax_url + '?action=wpsgl_search_products_frontend&term=' + encodeURIComponent(searchTerm),
                        status: code,
                        statusText: jqXHR && jqXHR.statusText ? jqXHR.statusText : textStatus,
                        errorThrown: errorThrown || '',
                        responseText: jqXHR && jqXHR.responseText ? jqXHR.responseText : '',
                        headers: (jqXHR && typeof jqXHR.getAllResponseHeaders === 'function') ? jqXHR.getAllResponseHeaders() : ''
                    };

                    // Logar no console (privado / temporário)
                    try { console.error('wpsgl-debug: search error', debugData); } catch (e) {}

                    // Exibir mensagem amigável e painel de detalhes copiável
                    setSearchStatus(msg, 'warning');
                    $results.hide();

                    var $debug = $('#wpsgl-search-debug');
                    if (!$debug.length) {
                        $debug = $('<div id="wpsgl-search-debug" style="background:#fff;border:1px solid #e6e6e6;padding:10px;margin-top:8px;border-radius:6px;">' +
                            '<div style="display:flex; gap:8px; align-items:center;"><strong>Detalhes da requisição</strong><button type="button" id="wpsgl-search-debug-copy" class="button">Copiar detalhes</button></div>' +
                            '<pre style="white-space:pre-wrap;margin-top:8px;max-height:240px;overflow:auto;">' + JSON.stringify(debugData, null, 2) + '</pre>' +
                        '</div>');
                        $status.after($debug);

                        // Copiar para clipboard
                        $(document).on('click', '#wpsgl-search-debug-copy', function() {
                            var text = JSON.stringify(debugData, null, 2);
                            if (navigator.clipboard && navigator.clipboard.writeText) {
                                navigator.clipboard.writeText(text).then(function() {
                                    alert('Detalhes copiados para a área de transferência.');
                                });
                            } else {
                                // fallback
                                var $tmp = $('<textarea>').val(text).appendTo('body').select();
                                try { document.execCommand('copy'); alert('Detalhes copiados para a área de transferência.'); } catch (e) { alert('Não foi possível copiar automaticamente. Por favor selecione e copie manualmente.'); }
                                $tmp.remove();
                            }
                        });
                    } else {
                        $debug.find('pre').text(JSON.stringify(debugData, null, 2));
                    }
                },

                complete: function() {
                    if (autoSelect) {
                        $btn.prop('disabled', false).html(originalBtnText);
                    }
                }
            });
        }

        
        function selectProduct(product) {
            // wpsgl-debug: selectProduct foi chamado (temporário)
            try { console.log('wpsgl-debug: selectProduct called', product); } catch (e) {}

            if (product.id == 0) {
                // Produto da API (Novo)
                $('#product_id').val(''); // Garante que é tratado como novo
                $('#product_name').val(product.name);
                $('#barcode').val(product.barcode);
                $('#category_id').val(''); // Forçar usuário a escolher
                $('#store_id').val(''); // Forçar usuário a escolher

                if (product.default_price && parseFloat(product.default_price) > 0) {
                    $('#unit_price').val(parseFloat(product.default_price).toFixed(2));
                }

                showMessage('Produto encontrado na base externa! Complete o cadastro.', 'success');

                // Focar na categoria para o usuário completar
                $('#category_id').focus();
            } else {
                // Produto Local (Existente)
                $('#product_id').val(product.id);
                $('#product_name').val(product.name);
                $('#category_id').val(product.category_id);
                $('#barcode').val(product.barcode);

                if (product.default_unit) {
                    $('#unit').val(product.default_unit);
                }

                if (product.default_price && parseFloat(product.default_price) > 0) {
                    $('#unit_price').val(parseFloat(product.default_price).toFixed(2));
                }

                // Focar na quantidade após selecionar
                $('#quantity').focus().select();
            }

            calculateTotal();
            $input.val('');
        }

        // Auto-complete para produtos (input)
        $input.on('input', function() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function() {
                searchProducts(false);
            }, 250);
        });
        
        // Enter no input de busca
        $input.on('keypress', function(e) {
            if(e.which === 13) {
                e.preventDefault();
                searchProducts(true);
            }
        });

        // Botão de busca
        $('#product_search_btn').on('click', function(e) {
            e.preventDefault();
            searchProducts(true); // Botão força busca "intencional"
        });
        
        // Handle click on result
        $(document).on('click', '.wpsgl-autocomplete-item', function() {
            var raw = $(this).attr('data-product');
            var product = raw;
            try {
                if (typeof raw === 'string') {
                    product = JSON.parse(raw);
                }
            } catch (e) {}
            selectProduct(product);
            $results.hide();
        });
        
        // Limpar formulário completo (incluindo busca)
        $('.wpsgl-reset-button').on('click', function() {
            $('#product_search').val('');
            $('#wpsgl-message').hide();
            // Pequeno delay para garantir que o form reset nativo já ocorreu
            setTimeout(function() {
                $('#product_id').val('');
                calculateTotal();
            }, 10);
        });
        
        // Calcular total automaticamente
        $('#quantity, #unit_price').on('input', function() {
            calculateTotal();
        });
        
        $('#unit').on('change', function() {
            calculateTotal();
        });
        
        function calculateTotal() {
            var quantity = parseFloat($('#quantity').val()) || 0;
            var unitPrice = parseFloat($('#unit_price').val()) || 0;
            var total = quantity * unitPrice;
            
            $('#total_price').val(total.toFixed(2));
        }
        
        // Submit do formulário
        $('#wpsgl-purchase-form').on('submit', function(e) {
            e.preventDefault();
            
            var form = $(this);
            var submitBtn = form.find('.wpsgl-submit-button');
            var originalText = submitBtn.text();
            var messageDiv = $('#wpsgl-message');
            
            // Validar campos obrigatórios
            var requiredFields = ['product_name', 'category_id', 'store_id', 'quantity', 'unit_price'];
            var isValid = true;
            
            requiredFields.forEach(function(field) {
                var value = form.find('[name="' + field + '"]').val();
                if (!value || value.trim() === '') {
                    isValid = false;
                    form.find('[name="' + field + '"]').addClass('error');
                } else {
                    form.find('[name="' + field + '"]').removeClass('error');
                }
            });
            
            if (!isValid) {
                showMessage('Por favor, preencha todos os campos obrigatórios.', 'error');
                return;
            }
            
            // Preparar dados do formulário
            var formData = form.serialize();
            
            // Adicionar nonce
            formData += '&nonce=' + wpsgl.nonce + '&action=wpsgl_add_purchase';
            
            // Desabilitar botão
            submitBtn.prop('disabled', true).text(wpsgl.loading_text);
            
            // Enviar via AJAX
            $.ajax({
                url: wpsgl.ajax_url,
                method: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showMessage(response.message, 'success');
                        form[0].reset();
                        calculateTotal();
                    } else {
                        showMessage(response.message, 'error');
                    }
                },
                error: function() {
                    showMessage(wpsgl.error_text, 'error');
                },
                complete: function() {
                    submitBtn.prop('disabled', false).text(originalText);
                }
            });
        });
        
        // Função para mostrar mensagens
        function showMessage(message, type) {
            var messageDiv = $('#wpsgl-message');
            
            messageDiv.removeClass('success error warning')
                      .addClass(type)
                      .html('<p>' + message + '</p>')
                      .fadeIn();
            
            // Auto-esconder após 5 segundos
            setTimeout(function() {
                messageDiv.fadeOut();
            }, 5000);
        }
        
        // Inicializar
        calculateTotal();
        
        // Estilo para campos com erro
        $('<style>')
            .prop('type', 'text/css')
            .html('.wpsgl-registration-form input.error, .wpsgl-registration-form select.error { border-color: #dc3232 !important; }')
            .appendTo('head');
    });
})(jQuery); 
