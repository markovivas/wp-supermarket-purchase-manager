(function($) {
    'use strict';
    
    var WPSGL_Chart_Loader = {
        
        charts: {},
        
        init: function() {
            this.loadMonthlyChart();
            this.loadCategoryChart();
            this.loadStoreChart();
            this.bindEvents();
        },
        
        loadMonthlyChart: function() {
            var canvas = document.getElementById('monthlyChart');
            
            if (!canvas) {
                return;
            }
            
            var ctx = canvas.getContext('2d');
            var container = $(canvas).closest('.wpsgl-chart-container');
            
            // Mostrar carregamento
            container.addClass('loading');
            
            $.ajax({
                url: wpsgl_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wpsgl_get_monthly_chart_data',
                    nonce: wpsgl_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        WPSGL_Chart_Loader.renderMonthlyChart(ctx, response.data);
                    }
                },
                complete: function() {
                    container.removeClass('loading');
                }
            });
        },
        
        renderMonthlyChart: function(ctx, data) {
            // Destruir gráfico existente
            if (ctx.chart) {
                ctx.chart.destroy();
            }
            
            var chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Gastos Mensais',
                        data: data.values,
                        borderColor: '#0073aa',
                        backgroundColor: 'rgba(0, 115, 170, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return 'R$ ' + context.raw.toFixed(2).replace('.', ',');
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return 'R$ ' + value.toFixed(2).replace('.', ',');
                                }
                            }
                        }
                    }
                }
            });
            
            // Salvar referência
            ctx.chart = chart;
            WPSGL_Chart_Loader.charts.monthly = chart;
        },
        
        loadCategoryChart: function() {
            var canvas = document.getElementById('categoryChart');
            
            if (!canvas) {
                return;
            }
            
            var ctx = canvas.getContext('2d');
            var container = $(canvas).closest('.wpsgl-chart-container');
            
            // Mostrar carregamento
            container.addClass('loading');
            
            $.ajax({
                url: wpsgl_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wpsgl_get_category_chart_data',
                    nonce: wpsgl_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        WPSGL_Chart_Loader.renderCategoryChart(ctx, response.data);
                    }
                },
                complete: function() {
                    container.removeClass('loading');
                }
            });
        },
        
        renderCategoryChart: function(ctx, data) {
            if (ctx.chart) {
                ctx.chart.destroy();
            }
            
            // Gerar cores
            var backgroundColors = [];
            var borderColors = [];
            
            data.labels.forEach(function(label, index) {
                var hue = (index * 137.508) % 360; // Golden angle
                backgroundColors.push('hsla(' + hue + ', 70%, 50%, 0.7)');
                borderColors.push('hsla(' + hue + ', 70%, 40%, 1)');
            });
            
            var chart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: data.labels,
                    datasets: [{
                        data: data.values,
                        backgroundColor: backgroundColors,
                        borderColor: borderColors,
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                boxWidth: 12,
                                padding: 15
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    var label = context.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    label += 'R$ ' + context.raw.toFixed(2).replace('.', ',');
                                    label += ' (' + context.dataset.dataPercentages[context.dataIndex] + '%)';
                                    return label;
                                }
                            }
                        }
                    },
                    cutout: '60%'
                }
            });
            
            ctx.chart = chart;
            WPSGL_Chart_Loader.charts.category = chart;
        },
        
        loadStoreChart: function() {
            var canvas = document.getElementById('storeChart');
            
            if (!canvas) {
                return;
            }
            
            var ctx = canvas.getContext('2d');
            var container = $(canvas).closest('.wpsgl-chart-container');
            
            container.addClass('loading');
            
            $.ajax({
                url: wpsgl_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wpsgl_get_store_chart_data',
                    nonce: wpsgl_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        WPSGL_Chart_Loader.renderStoreChart(ctx, response.data);
                    }
                },
                complete: function() {
                    container.removeClass('loading');
                }
            });
        },
        
        renderStoreChart: function(ctx, data) {
            if (ctx.chart) {
                ctx.chart.destroy();
            }
            
            var chart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Gastos por Loja',
                        data: data.values,
                        backgroundColor: 'rgba(54, 162, 235, 0.7)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return 'R$ ' + value.toFixed(2).replace('.', ',');
                                }
                            }
                        }
                    }
                }
            });
            
            ctx.chart = chart;
            WPSGL_Chart_Loader.charts.store = chart;
        },
        
        bindEvents: function() {
            // Atualizar gráfico ao mudar período
            $(document).on('change', '.wpsgl-chart-period', function() {
                var period = $(this).val();
                var chartType = $(this).data('chart-type');
                
                switch(chartType) {
                    case 'monthly':
                        WPSGL_Chart_Loader.updateMonthlyChart(period);
                        break;
                    case 'category':
                        WPSGL_Chart_Loader.updateCategoryChart(period);
                        break;
                    case 'store':
                        WPSGL_Chart_Loader.updateStoreChart(period);
                        break;
                }
            });
            
            // Alternar tipo de gráfico
            $(document).on('click', '.wpsgl-chart-type-btn', function(e) {
                e.preventDefault();
                var type = $(this).data('chart-type');
                var chartId = $(this).closest('.wpsgl-chart-container').data('chart-id');
                
                $(this).addClass('active').siblings().removeClass('active');
                WPSGL_Chart_Loader.changeChartType(chartId, type);
            });
            
            // Exportar gráfico como imagem
            $(document).on('click', '.wpsgl-chart-export', function() {
                var chartContainer = $(this).closest('.wpsgl-chart-container');
                var canvas = chartContainer.find('canvas')[0];
                var chartName = chartContainer.data('chart-name') || 'grafico';
                
                var link = document.createElement('a');
                link.download = chartName + '-' + new Date().toISOString().slice(0, 10) + '.png';
                link.href = canvas.toDataURL('image/png');
                link.click();
            });
        },
        
        updateMonthlyChart: function(period) {
            var canvas = document.getElementById('monthlyChart');
            if (!canvas) return;
            
            var ctx = canvas.getContext('2d');
            var container = $(canvas).closest('.wpsgl-chart-container');
            
            container.addClass('loading');
            
            $.ajax({
                url: wpsgl_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wpsgl_get_monthly_chart_data',
                    period: period,
                    nonce: wpsgl_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        WPSGL_Chart_Loader.renderMonthlyChart(ctx, response.data);
                    }
                },
                complete: function() {
                    container.removeClass('loading');
                }
            });
        },
        
        updateCategoryChart: function(period) {
            var canvas = document.getElementById('categoryChart');
            if (!canvas) return;
            
            var ctx = canvas.getContext('2d');
            var container = $(canvas).closest('.wpsgl-chart-container');
            
            container.addClass('loading');
            
            $.ajax({
                url: wpsgl_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wpsgl_get_category_chart_data',
                    period: period,
                    nonce: wpsgl_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        WPSGL_Chart_Loader.renderCategoryChart(ctx, response.data);
                    }
                },
                complete: function() {
                    container.removeClass('loading');
                }
            });
        },
        
        updateStoreChart: function(period) {
            var canvas = document.getElementById('storeChart');
            if (!canvas) return;
            
            var ctx = canvas.getContext('2d');
            var container = $(canvas).closest('.wpsgl-chart-container');
            
            container.addClass('loading');
            
            $.ajax({
                url: wpsgl_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wpsgl_get_store_chart_data',
                    period: period,
                    nonce: wpsgl_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        WPSGL_Chart_Loader.renderStoreChart(ctx, response.data);
                    }
                },
                complete: function() {
                    container.removeClass('loading');
                }
            });
        },
        
        changeChartType: function(chartId, type) {
            var canvas = document.getElementById(chartId);
            if (!canvas) return;
            
            var ctx = canvas.getContext('2d');
            
            if (ctx.chart) {
                ctx.chart.destroy();
            }
            
            // Aqui você pode implementar a lógica para mudar o tipo do gráfico
            // Baseado no chartId e no type
        },
        
        // Utilitários
        formatCurrency: function(value) {
            return 'R$ ' + parseFloat(value).toFixed(2).replace('.', ',');
        },
        
        getRandomColor: function() {
            var letters = '0123456789ABCDEF';
            var color = '#';
            for (var i = 0; i < 6; i++) {
                color += letters[Math.floor(Math.random() * 16)];
            }
            return color;
        },
        
        generateColors: function(count) {
            var colors = [];
            for (var i = 0; i < count; i++) {
                var hue = (i * 137.508) % 360; // Golden angle
                colors.push('hsl(' + hue + ', 70%, 50%)');
            }
            return colors;
        }
    };
    
    // Inicializar quando o documento estiver pronto
    $(document).ready(function() {
        // Verificar se Chart.js está disponível
        if (typeof Chart !== 'undefined') {
            WPSGL_Chart_Loader.init();
        } else {
            console.warn('Chart.js não está disponível. Carregando...');
            
            // Tentar carregar Chart.js dinamicamente
            var script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/chart.js';
            script.onload = function() {
                WPSGL_Chart_Loader.init();
            };
            document.head.appendChild(script);
        }
    });
    
    // Expor para uso global
    window.WPSGL_Chart_Loader = WPSGL_Chart_Loader;
    
})(jQuery); 
