(function($) {
    'use strict';
    
    var WPSGL_Admin = {
        
        init: function() {
            this.bindEvents();
            this.initDataTables();
            this.initCharts();
        },
        
        bindEvents: function() {
            // Exportar CSV
            $(document).on('click', '#export-csv', this.exportCSV);
            
            // Importar CSV
            $(document).on('submit', '#import-csv-form', this.importCSV);
            
            // Filtros dinâmicos
            $(document).on('change', '.wpsgl-dynamic-filter', this.applyFilters);
            
            // Validação de formulários
            $(document).on('submit', '.wpsgl-validate-form', this.validateForm);
            
            // Tooltips
            this.initTooltips();
            
            // Modal
            this.initModals();
            
            // Gráficos dinâmicos
            $(document).on('change', '.wpsgl-chart-period', this.updateChart);
            
            // Busca em tempo real
            $(document).on('keyup', '.wpsgl-live-search', this.liveSearch);
        },
        
        exportCSV: function(e) {
            e.preventDefault();
            
            var button = $(this);
            var originalText = button.text();
            var form = button.closest('form');
            
            // Desabilitar botão
            button.prop('disabled', true).text('Exportando...');
            
            // Coletar dados do formulário
            var formData = form.serialize();
            var url = wpsgl_ajax.ajax_url + '?action=wpsgl_export_csv&' + formData;
            
            // Redirecionar para download
            window.location.href = url;
            
            // Restaurar botão após 2 segundos
            setTimeout(function() {
                button.prop('disabled', false).text(originalText);
            }, 2000);
        },
        
        importCSV: function(e) {
            e.preventDefault();
            
            var form = $(this);
            var button = form.find('button[type="submit"]');
            var originalText = button.text();
            var formData = new FormData(form[0]);
            
            // Desabilitar botão
            button.prop('disabled', true).text('Importando...');
            
            $.ajax({
                url: wpsgl_ajax.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        WPSGL_Admin.showNotice(response.message, 'success');
                        
                        // Atualizar a página após 1.5 segundos
                        setTimeout(function() {
                            window.location.reload();
                        }, 1500);
                    } else {
                        WPSGL_Admin.showNotice(response.message, 'error');
                        button.prop('disabled', false).text(originalText);
                    }
                },
                error: function() {
                    WPSGL_Admin.showNotice('Erro na importação. Tente novamente.', 'error');
                    button.prop('disabled', false).text(originalText);
                }
            });
        },
        
        applyFilters: function() {
            var form = $(this).closest('form');
            
            // Adicionar indicador de carregamento
            form.addClass('loading');
            
            // Coletar dados do formulário
            var formData = form.serialize();
            
            $.ajax({
                url: wpsgl_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wpsgl_filter_data',
                    data: formData,
                    nonce: wpsgl_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Atualizar tabela com novos dados
                        if (response.data.table) {
                            $('.wpsgl-data-table').html(response.data.table);
                        }
                        
                        // Atualizar estatísticas
                        if (response.data.stats) {
                            $('.wpsgl-stats-cards').html(response.data.stats);
                        }
                        
                        // Atualizar gráfico
                        if (response.data.chart) {
                            WPSGL_Admin.updateChartData(response.data.chart);
                        }
                    }
                },
                complete: function() {
                    form.removeClass('loading');
                }
            });
        },
        
        validateForm: function(e) {
            var form = $(this);
            var isValid = true;
            
            // Validar campos obrigatórios
            form.find('[required]').each(function() {
                var field = $(this);
                var value = field.val().trim();
                
                if (value === '') {
                    isValid = false;
                    field.addClass('error');
                    
                    // Adicionar mensagem de erro
                    var errorMsg = $('<span class="wpsgl-error-message">Este campo é obrigatório</span>');
                    field.after(errorMsg);
                } else {
                    field.removeClass('error');
                    field.next('.wpsgl-error-message').remove();
                }
            });
            
            // Validação específica para números
            form.find('[data-validate="number"]').each(function() {
                var field = $(this);
                var value = field.val();
                
                if (value && isNaN(parseFloat(value))) {
                    isValid = false;
                    field.addClass('error');
                    
                    var errorMsg = $('<span class="wpsgl-error-message">Digite um número válido</span>');
                    field.after(errorMsg);
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                WPSGL_Admin.showNotice('Por favor, corrija os erros no formulário.', 'error');
            }
        },
        
        initTooltips: function() {
            // Inicializar tooltips do WordPress
            if (typeof jQuery.fn.tooltip === 'function') {
                $('[data-tooltip]').tooltip({
                    position: {
                        my: "center bottom",
                        at: "center top-10"
                    }
                });
            }
        },
        
        initModals: function() {
            // Abrir modal
            $(document).on('click', '[data-modal]', function(e) {
                e.preventDefault();
                var modalId = $(this).data('modal');
                $('#' + modalId).show();
            });
            
            // Fechar modal
            $(document).on('click', '.wpsgl-modal-close, .wpsgl-modal-overlay', function() {
                $(this).closest('.wpsgl-modal').hide();
            });
            
            // Fechar com ESC
            $(document).on('keyup', function(e) {
                if (e.keyCode === 27) {
                    $('.wpsgl-modal').hide();
                }
            });
        },
        
        initDataTables: function() {
            // Inicializar DataTables se disponível
            if (typeof $.fn.DataTable === 'function') {
                $('.wpsgl-data-table').DataTable({
                    pageLength: 25,
                    language: {
                        url: '//cdn.datatables.net/plug-ins/1.10.24/i18n/Portuguese-Brasil.json'
                    },
                    responsive: true,
                    order: [[0, 'desc']]
                });
            }
        },
        
        initCharts: function() {
            // Verificar se Chart.js está disponível
            if (typeof Chart === 'undefined') {
                console.warn('Chart.js não está disponível');
                return;
            }
            
            // Inicializar gráficos existentes
            $('.wpsgl-chart').each(function() {
                var canvas = $(this).find('canvas')[0];
                var config = $(this).data('chart-config');
                
                if (canvas && config) {
                    new Chart(canvas, JSON.parse(config));
                }
            });
        },
        
        updateChart: function() {
            var chartContainer = $(this).closest('.wpsgl-chart-container');
            var period = $(this).val();
            var chartId = chartContainer.data('chart-id');
            
            // Mostrar indicador de carregamento
            chartContainer.addClass('loading');
            
            $.ajax({
                url: wpsgl_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wpsgl_update_chart',
                    chart_id: chartId,
                    period: period,
                    nonce: wpsgl_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Atualizar gráfico
                        WPSGL_Admin.renderChart(chartContainer, response.data);
                    }
                },
                complete: function() {
                    chartContainer.removeClass('loading');
                }
            });
        },
        
        renderChart: function(container, chartData) {
            var canvas = container.find('canvas')[0];
            var ctx = canvas.getContext('2d');
            
            // Destruir gráfico existente
            if (canvas.chart) {
                canvas.chart.destroy();
            }
            
            // Criar novo gráfico
            canvas.chart = new Chart(ctx, chartData);
        },
        
        updateChartData: function(chartData) {
            // Implementação para atualizar dados do gráfico
            // Depende da estrutura específica do chartData
        },
        
        liveSearch: function() {
            var searchTerm = $(this).val();
            var table = $(this).closest('.wpsgl-searchable-table');
            var timeout;
            
            // Debounce para evitar muitas requisições
            clearTimeout(timeout);
            
            timeout = setTimeout(function() {
                if (searchTerm.length === 0 || searchTerm.length >= 2) {
                    WPSGL_Admin.performSearch(searchTerm, table);
                }
            }, 300);
        },
        
        performSearch: function(term, table) {
            $.ajax({
                url: wpsgl_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wpsgl_live_search',
                    term: term,
                    table: table.data('table-name'),
                    nonce: wpsgl_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        table.find('tbody').html(response.data);
                    }
                }
            });
        },
        
        showNotice: function(message, type) {
            // Remover notificações existentes
            $('.wpsgl-notice').remove();
            
            // Criar nova notificação
            var notice = $('<div class="notice notice-' + type + ' wpsgl-notice"><p>' + message + '</p></div>');
            
            // Adicionar ao topo da página
            $('.wrap').prepend(notice);
            
            // Auto-remover após 5 segundos
            setTimeout(function() {
                notice.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
        },
        
        // Utilitários
        formatCurrency: function(value) {
            return 'R$ ' + parseFloat(value).toFixed(2).replace('.', ',');
        },
        
        formatDate: function(dateString) {
            var date = new Date(dateString);
            return date.toLocaleDateString('pt-BR');
        },
        
        calculateTotal: function(quantity, unitPrice) {
            return parseFloat(quantity) * parseFloat(unitPrice);
        }
    };
    
    // Inicializar quando o documento estiver pronto
    $(document).ready(function() {
        WPSGL_Admin.init();
    });
    
    // Expor para uso global se necessário
    window.WPSGL_Admin = WPSGL_Admin;
    
})(jQuery); 
