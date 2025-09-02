// Função para inicializar todos os gráficos quando os dados estiverem disponíveis
function initializeCharts(vendasData, produtosData, categoriasData) {
    // Gráfico de Vendas e Quantidade Vendida
    const ctxVendasCompras = document.getElementById('vendas-chart').getContext('2d');
    if (ctxVendasCompras) {
        const vendasComprasChart = new Chart(ctxVendasCompras, {
            type: 'line',
            data: {
                labels: vendasData.map(item => item.data),
                datasets: [
                    {
                        label: 'Valor de Vendas (R$)',
                        data: vendasData.map(item => item.valor),
                        borderColor: 'rgba(42, 157, 143, 1)',
                        backgroundColor: 'rgba(42, 157, 143, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Quantidade Vendida',
                        data: vendasData.map(item => item.quantidade),
                        borderColor: 'rgba(231, 111, 81, 1)',
                        backgroundColor: 'rgba(231, 111, 81, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.datasetIndex === 0) {
                                    label += 'R$ ' + context.parsed.y.toFixed(2).replace('.', ',');
                                } else {
                                    label += context.parsed.y + ' unidades';
                                }
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Valor (R$)'
                        },
                        grid: {
                            drawOnChartArea: false
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Quantidade'
                        },
                        grid: {
                            drawOnChartArea: false
                        }
                    }
                }
            }
        });

        // Adicionar o event listener para alternar tipo de gráfico
        document.getElementById('toggleChartType')?.addEventListener('click', function() {
            vendasComprasChart.config.type = vendasComprasChart.config.type === 'line' ? 'bar' : 'line';
            vendasComprasChart.update();
        });

        // Event listener para download do gráfico
        document.getElementById('downloadChart')?.addEventListener('click', function() {
            const link = document.createElement('a');
            link.download = 'vendas-chart.png';
            link.href = document.getElementById('vendas-chart').toDataURL('image/png');
            link.click();
        });
    }

    // Gráfico de Produtos Mais Vendidos
    const ctxProdutos = document.getElementById('produtos-chart').getContext('2d');
    if (ctxProdutos) {
        const produtosChart = new Chart(ctxProdutos, {
            type: 'doughnut',
            data: {
                labels: produtosData.map(item => item.nome),
                datasets: [{
                    data: produtosData.map(item => item.quantidade),
                    backgroundColor: [
                        'rgba(42, 157, 143, 0.8)',
                        'rgba(233, 196, 106, 0.8)',
                        'rgba(244, 162, 97, 0.8)',
                        'rgba(231, 111, 81, 0.8)',
                        'rgba(38, 70, 83, 0.8)'
                    ],
                    borderColor: 'rgba(255, 255, 255, 0.8)',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            boxWidth: 15
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                label += context.raw + ' unidades';
                                return label;
                            }
                        }
                    }
                }
            }
        });

        // Event listener para alternar tipo de gráfico
        document.getElementById('togglePieChart')?.addEventListener('click', function() {
            produtosChart.config.type = produtosChart.config.type === 'doughnut' ? 'pie' : 'doughnut';
            produtosChart.update();
        });
    }

    // Gráfico de Categorias
    const ctxCategorias = document.getElementById('categorias-chart').getContext('2d');
    if (ctxCategorias) {
        const categoriasChart = new Chart(ctxCategorias, {
            type: 'bar',
            data: {
                labels: categoriasData.map(item => item.nome),
                datasets: [{
                    label: 'Valor em Estoque (R$)',
                    data: categoriasData.map(item => item.valor),
                    backgroundColor: 'rgba(42, 157, 143, 0.8)',
                    borderColor: 'rgba(42, 157, 143, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                label += 'R$ ' + context.raw.toFixed(2).replace('.', ',');
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'R$ ' + value.toLocaleString('pt-BR');
                            }
                        }
                    }
                }
            }
        });

        // Event listener para alternar tipo de gráfico
        document.getElementById('toggleCategoriesChart')?.addEventListener('click', function() {
            // Chart.js v3 não tem mais 'horizontalBar', precisamos mudar para 'bar' com indexAxis: 'y'
            if (categoriasChart.config.type === 'bar' && categoriasChart.options.indexAxis !== 'y') {
                categoriasChart.options.indexAxis = 'y';
            } else {
                categoriasChart.options.indexAxis = 'x';
            }
            categoriasChart.update();
        });
    }
}

// Função para animação dos valores nos cards
function animateValue(obj, start, end, duration) {
    if (!obj) return;
    
    let startTimestamp = null;
    const step = (timestamp) => {
        if (!startTimestamp) startTimestamp = timestamp;
        const progress = Math.min((timestamp - startTimestamp) / duration, 1);
        let value = progress * (end - start) + start;
        
        if (obj.id === 'valor-estoque' || obj.id === 'vendas-totais' || obj.id === 'lucro-liquido') {
            obj.innerHTML = 'R$ ' + value.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        } else {
            obj.innerHTML = Math.floor(value).toLocaleString('pt-BR');
        }
        
        if (progress < 1) {
            window.requestAnimationFrame(step);
        }
    };
    window.requestAnimationFrame(step);
}

// Configurar observador para animações quando elementos estiverem visíveis
function setupAnimationObserver() {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const targetElement = entry.target;
                
                // Verificar qual elemento está visível e animar seu valor
                if (targetElement.id === 'valor-estoque') {
                    const valorEstoque = parseFloat(targetElement.getAttribute('data-value') || 0);
                    animateValue(targetElement, 0, valorEstoque, 1000);
                } else if (targetElement.id === 'vendas-totais') {
                    const vendasTotais = parseFloat(targetElement.getAttribute('data-value') || 0);
                    animateValue(targetElement, 0, vendasTotais, 1000);
                } else if (targetElement.id === 'lucro-liquido') {
                    const lucroLiquido = parseFloat(targetElement.getAttribute('data-value') || 0);
                    animateValue(targetElement, 0, lucroLiquido, 1000);
                } else if (targetElement.id === 'itens-estoque') {
                    const itensEstoque = parseFloat(targetElement.getAttribute('data-value') || 0);
                    animateValue(targetElement, 0, itensEstoque, 1000);
                }
                
                // Parar de observar após animação
                observer.unobserve(targetElement);
            }
        });
    }, { threshold: 0.1 });
    
    // Observar elementos para animações
    document.querySelectorAll('.card-value').forEach(card => {
        observer.observe(card);
    });
}


// Inicializar tudo quando o DOM estiver pronto
document.addEventListener('DOMContentLoaded', function() {
    // Configurar o tema
    setupThemeToggle();
    
    // Configurar as animações
    setupAnimationObserver();
    
    // Verificar se temos todos os dados necessários para os gráficos
    const vendasDataElement = document.getElementById('vendas-data');
    const produtosDataElement = document.getElementById('produtos-data');
    const categoriasDataElement = document.getElementById('categorias-data');
    
    if (vendasDataElement && produtosDataElement && categoriasDataElement) {
        try {
            const vendasData = JSON.parse(vendasDataElement.textContent);
            const produtosData = JSON.parse(produtosDataElement.textContent);
            const categoriasData = JSON.parse(categoriasDataElement.textContent);
            
            // Inicializar os gráficos com os dados
            initializeCharts(vendasData, produtosData, categoriasData);
        } catch (e) {
            console.error('Erro ao analisar os dados para os gráficos:', e);
        }
    } else {
        console.warn('Dados para gráficos não encontrados no DOM');
    }
    
    // Configurar listeners para elementos de alerta
    document.querySelectorAll('.alert-item').forEach(item => {
        item.addEventListener('click', function() {
            const codigo = this.querySelector('.alert-description strong')?.textContent;
            if (codigo) {
                window.location.href = 'adicionar_material.php?codigo=' + codigo;
            }
        });
    });
});