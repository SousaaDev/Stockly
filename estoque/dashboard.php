<?php
include 'dashboard_data.php';
include 'header.php'; // Incluindo o header

$usuario = $_SESSION['usuario'];

// Verifica se a foto de perfil está definida, caso contrário, define uma padrão
if (!isset($usuario['foto_perfil']) || empty($usuario['foto_perfil'])) {
    $usuario['foto_perfil'] = '../uploads/basico.png';
} else {
    $usuario['foto_perfil'] = htmlspecialchars($usuario['foto_perfil']);
}

// Conexão com o banco de dados - ATUALIZADO PARA O NOVO BANCO
$conn = new mysqli('localhost', 'root', '', 'ga3_stockly');

if ($conn->connect_error) {
    die("Conexão falhou: " . $conn->connect_error);
}

// Função para obter o período atual (dia, semana, mês)
function getPeriodoAtual() {
    return isset($_GET['periodo']) ? $_GET['periodo'] : 'mes';
}

$periodo_atual = getPeriodoAtual();

// HTML e cabeçalho
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Stockly</title>
    <link rel="stylesheet" href="../css/styleprincipal.css">
    <link rel="stylesheet" href="dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
    
/* Variáveis e Reset */
:root {
    --primary: #2a9d8f;
    --success: #10b981;
    --warning: #e9c46a;
    --danger: #e76f51;
    --info: #3b82f6;
    --text-color: #333;
    --bg-secondary: #f8f9fa;
    --gray-100: #f8f9fa;
    --gray-200: #e9ecef;
    --gray-300: #dee2e6;
    --primary-color: #2a9d8f;
    --border-radius: 8px;
    --border-radius-sm: 4px;
    --border-radius-full: 9999px;
    --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    --transition: all 0.3s ease;
    --primary-light: rgba(42, 157, 143, 0.1);
    --success-light: rgba(16, 185, 129, 0.1);
    --warning-light: rgba(233, 196, 106, 0.1);
    --danger-light: rgba(231, 111, 81, 0.1);
    --info-light: rgba(59, 130, 246, 0.1);
}

* {
    margin: 0;
    padding: 0;
    border: 0;
    outline: 0;
    font-size: 100%;
    vertical-align: baseline;
    background: transparent;
    box-sizing: border-box;
}

body {
    line-height: 1;
    font-family: Arial, Helvetica, sans-serif;
    font-size: 16px;
    color: #000;
    background-color: #fff;
}

/* Dashboard Welcome Banner */
.dashboard-welcome {
    background: linear-gradient(135deg, #2a9d8f, #1e7376);
    color: white;
    border-radius: var(--border-radius);
    padding: 30px;
    margin-bottom: 32px;
    box-shadow: var(--shadow-md);
    position: relative;
    overflow: hidden;
}

.dashboard-welcome::after {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 300px;
    height: 100%;
    background: url('../Imagens/wave-pattern.svg') no-repeat right center;
    opacity: 0.1;
    pointer-events: none;
}

.welcome-content {
    position: relative;
    z-index: 1;
}

.welcome-title {
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 10px;
}

.welcome-subtitle {
    font-size: 16px;
    opacity: 0.9;
    max-width: 60%;
}

/* Action Buttons */
.quick-actions {
    margin-top: 15px;
    display: flex;
    gap: 10px;
}

.quick-action-btn {
    background-color: rgba(255, 255, 255, 0.2);
    color: white;
    border: none;
    border-radius: var(--border-radius);
    padding: 8px 16px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: var(--transition);
}

.quick-action-btn:hover {
    background-color: rgba(255, 255, 255, 0.3);
    transform: translateY(-2px);
}

/* Period Filter */
.period-filter {
    display: flex;
    gap: 8px;
    margin-bottom: 16px;
}

.period-btn {
    background-color: var(--gray-200);
    border: none;
    padding: 6px 12px;
    border-radius: var(--border-radius-sm);
    font-size: 14px;
    cursor: pointer;
    transition: var(--transition);
}

.period-btn.active {
    background-color: var(--primary);
    color: white;
}

.period-btn:hover:not(.active) {
    background-color: var(--gray-300);
}

/* Dashboard Cards */
.dashboard-card {
    position: relative;
    overflow: hidden;
}

.dashboard-card::after {
    content: '';
    position: absolute;
    bottom: 0;
    right: 0;
    width: 80px;
    height: 80px;
    background-repeat: no-repeat;
    background-position: bottom right;
    opacity: 0.1;
    pointer-events: none;
}

.dashboard-card:nth-child(1)::after {
    background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%232a9d8f" width="80" height="80"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1.41 16.09V20h-2.67v-1.93c-1.71-.36-3.16-1.46-3.27-3.4h1.96c.1 1.05.82 1.87 2.65 1.87 1.96 0 2.4-.98 2.4-1.59 0-.83-.44-1.61-2.67-2.14-2.48-.6-4.18-1.62-4.18-3.67 0-1.72 1.39-2.84 3.11-3.21V4h2.67v1.95c1.86.45 2.79 1.86 2.85 3.39H14.3c-.05-1.11-.64-1.87-2.22-1.87-1.5 0-2.4.68-2.4 1.64 0 .84.65 1.39 2.67 1.91s4.18 1.39 4.18 3.91c-.01 1.83-1.38 2.83-3.12 3.16z"/></svg>');
}

.dashboard-card:nth-child(2)::after {
    background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%2310b981" width="80" height="80"><path d="M3.5 18.49l6-6.01 4 4L22 6.92l-1.41-1.41-7.09 7.97-4-4L2 16.99z"/></svg>');
}

.dashboard-card:nth-child(3)::after {
    background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%23e9c46a" width="80" height="80"><path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z"/></svg>');
}

.dashboard-card:nth-child(4)::after {
    background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%23e76f51" width="80" height="80"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V5h14v14z"/><path d="M7 12h2v5H7zm4-7h2v12h-2zm4 4h2v8h-2z"/></svg>');
}

/* Alert Items */
.alert-item {
    transition: var(--transition);
    cursor: pointer;
}

.alert-item:hover {
    transform: translateX(5px);
}

/* Info Card */
.info-card {
    background-color: var(--info-light);
    border-left: 4px solid var(--info);
    margin-bottom: 24px;
    padding: 16px;
    border-radius: var(--border-radius);
    display: flex;
    align-items: center;
    gap: 16px;
}

.info-icon {
    width: 40px;
    height: 40px;
    background-color: var(--info);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

/* Chart and Table Containers */
.chart-container, .table-container {
    position: relative;
    width: 100%;
    height: auto;
    max-height: 600px;
    margin-bottom: 20px;
    transition: var(--transition);
    overflow: auto;
    display: flex;
    flex-direction: column;
    align-items: center;
}

.chart-container:hover, .table-container:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-md);
}

canvas {
    width: 100% !important;
    height: auto !important;
    max-height: 100%;
    aspect-ratio: 16 / 9;
}

/* Action Rows */
.action-row {
    display: flex;
    justify-content: flex-end;
    margin-top: 8px;
}

.chart-actions {
    display: flex;
    gap: 8px;
}

.chart-action-btn {
    background-color: var(--gray-200);
    border: none;
    border-radius: var(--border-radius-sm);
    padding: 4px 8px;
    font-size: 12px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 4px;
    transition: var(--transition);
}

.chart-action-btn:hover {
    background-color: var(--gray-300);
}

/* Progress Bars */
.progress-bar {
    height: 6px;
    width: 100%;
    background-color: var(--gray-200);
    border-radius: var(--border-radius-full);
    margin-top: 10px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    border-radius: var(--border-radius-full);
    transition: width 0.5s ease;
}

.progress-primary { background-color: var(--primary); }
.progress-success { background-color: var(--success); }
.progress-warning { background-color: var(--warning); }
.progress-danger { background-color: var(--danger); }

/* Hamburger Menu Animation */
.ham-menu.active span:nth-child(1) {
    top: 10px;
    transform: rotate(135deg);
}

.ham-menu.active span:nth-child(2) {
    opacity: 0;
    left: -60px;
}

.ham-menu.active span:nth-child(3) {
    top: 10px;
    transform: rotate(-135deg);
}

.off-screen-menu {
    padding: 10px;
    position: fixed;
    top: 0;
    right: -100%;
    width: 80%;
    max-width: 300px;
    height: 100%;
    background-color: white;
    z-index: 100;
    transition: right 0.3s ease;
    box-shadow: -2px 0 10px rgba(0, 0, 0, 0.1);
    overflow-y: auto;
}

.off-screen-menu.active, .off-screen-menu.show {
    right: 0;
}

.off-screen-menu ul {
    list-style: none;
    padding: 2rem 0;
}

.off-screen-menu ul li a {
    display: block;
    padding: 1rem 2rem;
    color: var(--text-color);
    text-decoration: none;
    border-bottom: 1px solid #dee2e6;
    transition: background-color 0.3s ease;
}

.off-screen-menu ul li a:hover {
    background-color: var(--bg-secondary);
    color: var(--primary);
}

.off-screen-menu ul li ul {
    padding: 0;
}

.off-screen-menu ul li ul li a {
    padding-left: 3rem;
    font-size: 0.9rem;
}
.ham-menu span {
    display: block;
    position: absolute;
    height: 3px;
    width: 100%;
    background-color: var(--gray-100);
    border-radius: 3px;
    opacity: 1;
    left: 0;
    transform: rotate(0deg);
    transition: .25s ease-in-out;
}

.off-screen-menu ul li a:hover {
    background-color: var(--bg-secondary);
    color: var(--primary-color);
}

.off-screen-menu ul li ul {
    padding: 0;
}

.off-screen-menu ul li ul li a {
    padding-left: 3rem;
    font-size: 0.9rem;
}

/* Status Badges */
.status-badge.low { background-color: var(--danger-light); color: var(--danger); }
.status-badge.medium { background-color: var(--warning-light); color: var(--warning); }
.status-badge.high { background-color: var(--success-light); color: var(--success); }
    </style>
</head>
<body>


    <div class="dashboard-container">
        <!-- Welcome Banner -->
        <div class="dashboard-welcome">
            <div class="welcome-content">
                <h1 class="welcome-title">Olá, <?php echo htmlspecialchars($usuario['nome']); ?>!</h1>
                <p class="welcome-subtitle">Bem-vindo ao dashboard de gestão de estoque. Aqui você pode acompanhar o desempenho do seu negócio e gerenciar seu inventário em tempo real.</p>
                <div class="quick-actions">
                    <a href="adicionar_material.php" class="quick-action-btn">
                        <i class="fas fa-plus-circle"></i> Novo Item
                    </a>
                    <a href="vendas.php" class="quick-action-btn">
                        <i class="fas fa-shopping-cart"></i> Registrar Venda
                    </a>
                    <button onclick="exportarDashboardPDF()" class="quick-action-btn">
    <i class="fas fa-file-alt"></i> Relatórios
</button>
                </div>
            </div>
        </div>
        
        <!-- Período de Filtro -->
        <div class="period-filter">
            <a href="?periodo=dia" class="period-btn <?php echo $periodo_atual == 'dia' ? 'active' : ''; ?>">Hoje</a>
            <a href="?periodo=semana" class="period-btn <?php echo $periodo_atual == 'semana' ? 'active' : ''; ?>">Esta Semana</a>
            <a href="?periodo=mes" class="period-btn <?php echo $periodo_atual == 'mes' ? 'active' : ''; ?>">Este Mês</a>
            <a href="?periodo=ano" class="period-btn <?php echo $periodo_atual == 'ano' ? 'active' : ''; ?>">Este Ano</a>
        </div>

        <!-- Cards de métricas principais -->
        <div class="dashboard-grid">
            <div class="dashboard-card">
                <div class="card-header">
                    <h3 class="card-title">Valor em Estoque</h3>
                    <div class="card-icon icon-primary">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                </div>
                <h2 class="card-value">R$ <?php echo number_format($estoque_stats['valor_total_estoque'], 2, ',', '.'); ?></h2>
                <p class="card-subtitle">
                    <?php if ($variacao_estoque >= 0): ?>
                        <span class="trend-up"><i class="fas fa-arrow-up"></i> <?php echo abs($variacao_estoque); ?>%</span>
                    <?php else: ?>
                        <span class="trend-down"><i class="fas fa-arrow-down"></i> <?php echo abs($variacao_estoque); ?>%</span>
                    <?php endif; ?>
                    desde o mês passado
                </p>
                <div class="progress-bar">
                    <div class="progress-fill progress-primary" style="width: <?php echo min(100, max(0, $variacao_estoque + 50)); ?>%"></div>
                </div>
            </div>

            <div class="dashboard-card">
                <div class="card-header">
                    <h3 class="card-title">Vendas Totais</h3>
                    <div class="card-icon icon-success">
                        <i class="fas fa-chart-line"></i>
                    </div>
                </div>
                <h2 class="card-value">R$ <?php echo number_format($vendas_mes['receita_total'], 2, ',', '.'); ?></h2>
                <p class="card-subtitle">
                    <?php if ($variacao_vendas >= 0): ?>
                        <span class="trend-up"><i class="fas fa-arrow-up"></i> <?php echo abs($variacao_vendas); ?>%</span>
                    <?php else: ?>
                        <span class="trend-down"><i class="fas fa-arrow-down"></i> <?php echo abs($variacao_vendas); ?>%</span>
                    <?php endif; ?>
                    comparado ao período anterior
                </p>
                <div class="progress-bar">
                    <div class="progress-fill progress-primary" style="width: <?php echo min(100, max(0, $variacao_vendas + 50)); ?>%"></div>
                </div>
            </div>

            <div class="dashboard-card">
                <div class="card-header">
                    <h3 class="card-title">Lucro Líquido</h3>
                    <div class="card-icon icon-warning">
                        <i class="fas fa-coins"></i>
                    </div>
                </div>
                <h2 class="card-value">R$ <?php echo number_format($vendas_mes['lucro_bruto'], 2, ',', '.'); ?></h2>
                <p class="card-subtitle">
                    <?php if ($variacao_lucro >= 0): ?>
                        <span class="trend-up"><i class="fas fa-arrow-up"></i> <?php echo abs($variacao_lucro); ?>%</span>
                    <?php else: ?>
                        <span class="trend-down"><i class="fas fa-arrow-down"></i> <?php echo abs($variacao_lucro); ?>%</span>
                    <?php endif; ?>
                    comparado ao período anterior
                </p>
                <div class="progress-bar">
                    <div class="progress-fill progress-primary" style="width: <?php echo min(100, max(0, $variacao_lucro + 50)); ?>%"></div>
                </div>
            </div>

            <div class="dashboard-card">
                <div class="card-header">
                    <h3 class="card-title">Itens em Estoque</h3>
                    <div class="card-icon icon-danger">
                        <i class="fas fa-box"></i>
                    </div>
                </div>
                <h2 class="card-value"><?php echo number_format($estoque_stats['total_itens']); ?></h2>
                <p class="card-subtitle">
                    <?php if ($variacao_itens >= 0): ?>
                        <span class="trend-up"><i class="fas fa-arrow-up"></i> <?php echo abs($variacao_itens); ?>%</span>
                    <?php else: ?>
                        <span class="trend-down"><i class="fas fa-arrow-down"></i> <?php echo abs($variacao_itens); ?>%</span>
                    <?php endif; ?>
                    desde o mês passado
                </p>
                <div class="progress-bar">
                    <div class="progress-fill progress-primary" style="width: <?php echo min(100, max(0, $variacao_itens + 50)); ?>%"></div>
                </div>
            </div>
        </div>

        <!-- Alertas de Estoque Baixo -->
        <?php if (count($produtos_estoque_baixo) > 0): ?>
        <div class="alert-container">
            <div class="alert-header">
                <h3 class="alert-title">
                    <i class="fas fa-exclamation-triangle" style="color: var(--warning);"></i>
                    Alertas de Estoque
                </h3>
            </div>
            
            <?php foreach ($produtos_estoque_baixo as $index => $produto): ?>
            <div class="alert-item" style="animation-delay: <?php echo 0.1 * $index; ?>s">
                <div class="alert-icon alert-danger">
                    <i class="fas fa-exclamation"></i>
                </div>
                <div class="alert-content">
                    <h4 class="alert-message">Estoque Baixo: <?php echo htmlspecialchars($produto['descricao']); ?></h4>
                    <p class="alert-description">
                        Código: <strong><?php echo htmlspecialchars($produto['codigo_identificacao']); ?></strong> | 
                        Quantidade atual: <span class="status-badge low"><?php echo htmlspecialchars($produto['quantidade']); ?> unidades</span> |
                        Nível crítico: <span class="status-badge medium">10 unidades</span>
                    </p>
                </div>
                <div class="alert-action">
                    <a href="adicionar_material.php?codigo=<?php echo htmlspecialchars($produto['codigo_identificacao']); ?>" class="action-btn">
                        <i class="fas fa-plus"></i> Repor
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Gráficos e Relatórios -->
        <div class="dashboard-row">
            <div class="chart-container">
                <div class="chart-header">
                    <h3 class="chart-title">
                        <i class="fas fa-chart-line" style="color: var(--primary);"></i>
                        Vendas e Quantidade Vendida dos Últimos 30 Dias
                    </h3>
                    <div class="chart-actions">
                        <button class="chart-action-btn" id="toggleChartType">
                            <i class="fas fa-chart-bar"></i> Tipo
                        </button>
                        <button class="chart-action-btn" id="downloadChart">
                            <i class="fas fa-download"></i> Exportar
                        </button>
                    </div>
                </div>
                <canvas id="vendas-chart"></canvas>
            </div>

            <div class="table-container">
                <div class="chart-header">
                    <h3 class="chart-title">
                        <i class="fas fa-trophy" style="color: var(--primary);"></i>
                        Produtos Mais Vendidos
                    </h3>
                    <div class="chart-actions">
                        <button class="chart-action-btn" id="togglePieChart">
                            <i class="fas fa-chart-pie"></i> Tipo
                        </button>
                    </div>
                </div>
                <canvas id="produtos-chart"></canvas>
            </div>
        </div>

        <div class="dashboard-row">
            <div class="chart-container">
                <div class="chart-header">
                    <h3 class="chart-title">
                        <i class="fas fa-sitemap" style="color: var(--primary);"></i>
                        Distribuição por Categoria
                    </h3>
                    <div class="chart-actions">
                        <button class="chart-action-btn" id="toggleCategoriesChart">
                            <i class="fas fa-chart-bar"></i> Tipo
                        </button>
                    </div>
                </div>
                <canvas id="categorias-chart"></canvas>
            </div>

            <div class="table-container">
                <div class="chart-header">
                    <h3 class="chart-title">
                        <i class="fas fa-tags" style="color: var(--primary);"></i>
                        Categorias
                    </h3>
                    <div class="chart-actions">
                        <a href="gerenciar_categorias.php" class="chart-action-btn">
                            <i class="fas fa-cog"></i> Gerenciar
                        </a>
                    </div>
                </div>
                <table class="dashboard-table">
                    <thead>
                        <tr>
                            <th>Categoria</th>
                            <th>Produtos</th>
                            <th>Itens</th>
                            <th>Valor Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categorias as $categoria): ?>
                        <tr>
                            <td>
                                <span style="display: flex; align-items: center; gap: 8px;">
                                    <i class="fas fa-tag" style="color: var(--primary);"></i>
                                    <?php echo htmlspecialchars($categoria['categoria']); ?>
                                </span>
                            </td>
                            <td><?php echo number_format($categoria['total_produtos']); ?></td>
                            <td><?php echo number_format($categoria['total_itens']); ?></td>
                            <td>R$ <?php echo number_format($categoria['valor_total'], 2, ',', '.'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Footer (same as in the original file) -->
    <footer class="site-footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-logo">
                    <img src="../Imagens/logo.png" alt="Stockly" width="120">
                    <p>Gerenciamento de estoque simplificado</p>
                </div>
                <div class="footer-links">
                    <h4>Links Rápidos</h4>
                    <ul>
                        <li><a href="../estoque/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                        <li><a href="../estoque/estoque.php"><i class="fas fa-boxes"></i> Estoque</a></li>
                        <li><a href="../estoque/vendas.php"><i class="fas fa-shopping-cart"></i> Vendas</a></li>
                        <li><a href="../estoque/historico_vendas.php"><i class="fas fa-chart-line"></i> Histórico de vendas</a></li>
                    </ul>
                </div>
                <div class="footer-contact">
                    <h4>Contato</h4>
                    <p><i class="fas fa-envelope"></i> suporte@stockly.com</p>
                    <p><i class="fas fa-phone"></i> (11) 9999-9999</p>
                    <div class="social-icons">
                        <a href="#"><i class="fab fa-facebook"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-linkedin"></i></a>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2025 Stockly. Todos os direitos reservados.</p>
            </div>
        </div>
    </footer>
<!-- Adicionar antes do fechamento da tag </body> no dashboard.php -->

<!-- Biblioteca jsPDF -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>

<script>
// Função para normalizar texto removendo acentos para o PDF
function normalizarTextoParaPDF(texto) {
    if (!texto) return '';
    
    const mapaAcentos = {
        'à': 'a', 'á': 'a', 'â': 'a', 'ã': 'a', 'ä': 'a',
        'À': 'A', 'Á': 'A', 'Â': 'A', 'Ã': 'A', 'Ä': 'A',
        'è': 'e', 'é': 'e', 'ê': 'e', 'ë': 'e',
        'È': 'E', 'É': 'E', 'Ê': 'E', 'Ë': 'E',
        'ì': 'i', 'í': 'i', 'î': 'i', 'ï': 'i',
        'Ì': 'I', 'Í': 'I', 'Î': 'I', 'Ï': 'I',
        'ò': 'o', 'ó': 'o', 'ô': 'o', 'õ': 'o', 'ö': 'o',
        'Ò': 'O', 'Ó': 'O', 'Ô': 'O', 'Õ': 'O', 'Ö': 'O',
        'ù': 'u', 'ú': 'u', 'û': 'u', 'ü': 'u',
        'Ù': 'U', 'Ú': 'U', 'Û': 'U', 'Ü': 'U',
        'ç': 'c', 'Ç': 'C',
        'ñ': 'n', 'Ñ': 'N',
        'ý': 'y', 'ÿ': 'y', 'Ý': 'Y',
        'º': 'o', 'ª': 'a'
    };
    
    return texto.replace(/[àáâãäÀÁÂÃÄèéêëÈÉÊËìíîïÌÍÎÏòóôõöÒÓÔÕÖùúûüÙÚÛÜçÇñÑýÿÝºª]/g, 
        function(match) {
            return mapaAcentos[match] || match;
        }
    );
}

// Função para exportar PDF do Dashboard
function exportarDashboardPDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF('p', 'mm', 'a4');
    
    // Configurações gerais
    let yPos = 20;
    const pageHeight = doc.internal.pageSize.height;
    const pageWidth = doc.internal.pageSize.width;
    const margin = 14;
    const contentWidth = pageWidth - (margin * 2);
    
    // Função para adicionar nova página se necessário
    function checkPageBreak(altura) {
        if (yPos + altura > pageHeight - 25) {
            doc.addPage();
            yPos = 20;
            return true;
        }
        return false;
    }
    
    // Função para adicionar texto com quebra de linha automática
    function addTextWithWrap(text, x, y, maxWidth, fontSize = 10) {
        doc.setFontSize(fontSize);
        const normalizedText = normalizarTextoParaPDF(text);
        const splitText = doc.splitTextToSize(normalizedText, maxWidth);
        doc.text(splitText, x, y);
        return splitText.length * (fontSize * 0.4);
    }
    
    // Header do documento
    doc.setFontSize(24);
    doc.setTextColor(42, 157, 143);
    const titleHeight = addTextWithWrap('STOCKLY - Dashboard Report', margin, yPos, contentWidth, 24);
    yPos += titleHeight + 5;
    
    // Data e usuário
    doc.setFontSize(12);
    doc.setTextColor(102, 102, 102);
    const dataGeracao = new Date().toLocaleDateString('pt-BR') + ' às ' + new Date().toLocaleTimeString('pt-BR');
    const usuarioNome = '<?php echo htmlspecialchars($usuario['nome']); ?>';
    
    yPos += addTextWithWrap('Gerado em: ' + dataGeracao, margin, yPos, contentWidth, 12);
    yPos += addTextWithWrap('Usuario: ' + normalizarTextoParaPDF(usuarioNome), margin, yPos, contentWidth, 12);
    yPos += 10;
    
    // Linha separadora
    doc.setDrawColor(42, 157, 143);
    doc.setLineWidth(0.5);
    doc.line(margin, yPos, pageWidth - margin, yPos);
    yPos += 15;
    
    // Métricas Principais
    checkPageBreak(80);
    doc.setFontSize(16);
    doc.setTextColor(42, 157, 143);
    yPos += addTextWithWrap('METRICAS PRINCIPAIS', margin, yPos, contentWidth, 16);
    yPos += 10;
    
    // Obter valores dos cards
    doc.setFontSize(11);
    doc.setTextColor(0, 0, 0);
    
    const valorEstoque = document.querySelector('.dashboard-card:nth-child(1) .card-value')?.textContent || 'R$ 0,00';
    const vendasTotais = document.querySelector('.dashboard-card:nth-child(2) .card-value')?.textContent || 'R$ 0,00';
    const lucroLiquido = document.querySelector('.dashboard-card:nth-child(3) .card-value')?.textContent || 'R$ 0,00';
    const itensEstoque = document.querySelector('.dashboard-card:nth-child(4) .card-value')?.textContent || '0';
    
    yPos += addTextWithWrap('Valor Total em Estoque: ' + normalizarTextoParaPDF(valorEstoque), margin, yPos, contentWidth, 11);
    yPos += addTextWithWrap('Vendas Totais: ' + normalizarTextoParaPDF(vendasTotais), margin, yPos, contentWidth, 11);
    yPos += addTextWithWrap('Lucro Liquido: ' + normalizarTextoParaPDF(lucroLiquido), margin, yPos, contentWidth, 11);
    yPos += addTextWithWrap('Itens em Estoque: ' + normalizarTextoParaPDF(itensEstoque), margin, yPos, contentWidth, 11);
    yPos += 15;
    
    // Alertas de Estoque (se existirem)
    const alertasContainer = document.querySelector('.alert-container');
    if (alertasContainer) {
        checkPageBreak(80);
        doc.setFontSize(16);
        doc.setTextColor(231, 76, 60);
        yPos += addTextWithWrap('ALERTAS DE ESTOQUE BAIXO', margin, yPos, contentWidth, 16);
        yPos += 10;
        
        const alertas = alertasContainer.querySelectorAll('.alert-item');
        const alertasData = [];
        
        alertas.forEach(alerta => {
            const produto = alerta.querySelector('.alert-message')?.textContent.replace('Estoque Baixo: ', '') || '';
            const descricao = alerta.querySelector('.alert-description')?.textContent || '';
            alertasData.push([normalizarTextoParaPDF(produto), normalizarTextoParaPDF(descricao)]);
        });
        
        if (alertasData.length > 0) {
            doc.autoTable({
                startY: yPos,
                head: [['Produto', 'Detalhes']],
                body: alertasData,
                theme: 'grid',
                styles: {
                    fontSize: 9,
                    cellPadding: 3,
                },
                headStyles: {
                    fillColor: [231, 76, 60],
                    textColor: [255, 255, 255],
                    fontStyle: 'bold',
                },
                columnStyles: {
                    0: { cellWidth: contentWidth * 0.3 },
                    1: { cellWidth: contentWidth * 0.7 }
                },
                margin: { left: margin, right: margin }
            });
            
            yPos = doc.lastAutoTable.finalY + 15;
        }
    }
    
    // Tabela de Categorias
    const tabelaCategorias = document.querySelector('.dashboard-table tbody');
    if (tabelaCategorias) {
        checkPageBreak(80);
        doc.setFontSize(16);
        doc.setTextColor(42, 157, 143);
        yPos += addTextWithWrap('DISTRIBUICAO POR CATEGORIA', margin, yPos, contentWidth, 16);
        yPos += 10;
        
        const categoriasData = [];
        const categoriaRows = tabelaCategorias.querySelectorAll('tr');
        
        categoriaRows.forEach(row => {
            const rowData = [];
            row.querySelectorAll('td').forEach(cell => {
                rowData.push(normalizarTextoParaPDF(cell.textContent.trim()));
            });
            categoriasData.push(rowData);
        });
        
        doc.autoTable({
            startY: yPos,
            head: [['Categoria', 'Produtos', 'Itens', 'Valor Total']],
            body: categoriasData,
            theme: 'grid',
            styles: {
                fontSize: 9,
                cellPadding: 3,
            },
            headStyles: {
                fillColor: [42, 157, 143],
                textColor: [255, 255, 255],
                fontStyle: 'bold',
            },
            columnStyles: {
                0: { cellWidth: contentWidth * 0.3 },
                1: { halign: 'center', cellWidth: contentWidth * 0.2 },
                2: { halign: 'center', cellWidth: contentWidth * 0.2 },
                3: { halign: 'right', cellWidth: contentWidth * 0.3 }
            },
            margin: { left: margin, right: margin }
        });
        
        yPos = doc.lastAutoTable.finalY + 15;
    }
    
    // Footer em todas as páginas
    const totalPages = doc.internal.getNumberOfPages();
    for (let i = 1; i <= totalPages; i++) {
        doc.setPage(i);
        doc.setFontSize(8);
        doc.setTextColor(102, 102, 102);
        
        // Linha do footer
        doc.setDrawColor(200, 200, 200);
        doc.setLineWidth(0.3);
        doc.line(margin, pageHeight - 15, pageWidth - margin, pageHeight - 15);
        
        // Texto do footer
        const footerLeft = 'Dashboard Stockly - Relatorio Executivo';
        const footerRight = `Pagina ${i} de ${totalPages}`;
        const footerCenter = new Date().toLocaleDateString('pt-BR') + ' ' + new Date().toLocaleTimeString('pt-BR');
        
        doc.text(footerLeft, margin, pageHeight - 8);
        doc.text(footerCenter, pageWidth / 2, pageHeight - 8, { align: 'center' });
        doc.text(footerRight, pageWidth - margin, pageHeight - 8, { align: 'right' });
    }
    
    // Salvar o PDF
    const hoje = new Date();
    const nomeArquivo = `dashboard_stockly_${hoje.getFullYear()}-${String(hoje.getMonth() + 1).padStart(2, '0')}-${String(hoje.getDate()).padStart(2, '0')}.pdf`;
    doc.save(nomeArquivo);
    

}
</script>
    <script>
        // Obter dados para gráficos
        const vendasData = <?php echo json_encode($dashboard_data['vendas_diarias']); ?>;
        const produtosData = <?php echo json_encode($dashboard_data['top_produtos']); ?>;
        const categoriasData = <?php echo json_encode($dashboard_data['categorias']); ?>;

        // Esperar que o DOM esteja completamente carregado
        document.addEventListener('DOMContentLoaded', function() {
            // Gráfico de Vendas
            const ctx1 = document.getElementById('vendas-chart').getContext('2d');
            const vendasChart = new Chart(ctx1, {
                type: 'line',
                data: {
                    labels: vendasData.map(item => item.data),
                    datasets: [{
                        label: 'Total de Vendas (R$)',
                        data: vendasData.map(item => item.total_vendas),
                        borderColor: 'rgba(42, 157, 143, 1)',
                        backgroundColor: 'rgba(42, 157, 143, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            title: {
                                display: true,
                                text: 'Data'
                            }
                        },
                        y: {
                            title: {
                                display: true,
                                text: 'Valor (R$)'
                            }
                        }
                    }
                }
            });

            // Gráfico de Produtos Mais Vendidos
            const ctx2 = document.getElementById('produtos-chart').getContext('2d');
            const produtosChart = new Chart(ctx2, {
                type: 'doughnut',
                data: {
                    labels: produtosData.map(item => item.descricao),
                    datasets: [{
                        data: produtosData.map(item => item.total_vendido),
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
                        }
                    }
                }
            });

            // Gráfico de Categorias
            const ctx3 = document.getElementById('categorias-chart').getContext('2d');
            const categoriasChart = new Chart(ctx3, {
                type: 'bar',
                data: {
                    labels: categoriasData.map(item => item.categoria),
                    datasets: [{
                        label: 'Valor em Estoque (R$)',
                        data: categoriasData.map(item => item.valor_total),
                        backgroundColor: 'rgba(42, 157, 143, 0.8)',
                        borderColor: 'rgba(42, 157, 143, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
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

            // Alternar tipo de gráfico
            document.getElementById('toggleChartType').addEventListener('click', function() {
                vendasChart.config.type = vendasChart.config.type === 'line' ? 'bar' : 'line';
                vendasChart.update();
            });

            document.getElementById('togglePieChart').addEventListener('click', function() {
                produtosChart.config.type = produtosChart.config.type === 'doughnut' ? 'pie' : 'doughnut';
                produtosChart.update();
            });

            document.getElementById('toggleCategoriesChart').addEventListener('click', function() {
                categoriasChart.config.type = categoriasChart.config.type === 'bar' ? 'horizontalBar' : 'bar';
                categoriasChart.update();
            });

            // Exportar gráfico
            document.getElementById('downloadChart').addEventListener('click', function() {
                const link = document.createElement('a');
                link.download = 'vendas-chart.png';
                link.href = document.getElementById('vendas-chart').toDataURL('image/png');
                link.click();
            });

            // Notificações e alertas interativos
            document.querySelectorAll('.alert-item').forEach(item => {
                item.addEventListener('click', function() {
                    const codigo = this.querySelector('.alert-description strong').textContent;
                    window.location.href = 'adicionar_material.php?codigo=' + codigo;
                });
            });
        });
    </script>
    <script src="../js/app.js"></script>
    <script src="../js/charts.js"></script>
</body>
</html>