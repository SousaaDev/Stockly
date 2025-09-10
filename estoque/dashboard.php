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


    // Definir $dados_dashboard com base nos dados existentes
$dados_dashboard = array(
    'tem_dados_grafico' => isset($vendas_mes) && $vendas_mes['receita_total'] > 0,
    'tem_produtos_vendidos' => isset($produtos_vendidos) && !empty($produtos_vendidos),
    'tem_categorias' => isset($categorias) && !empty($categorias)
);

// HTML e cabeçalho
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Stockly</title>
    <link rel="stylesheet" href="../css/styleprincipal.css">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
 /* dashboard.css - Versão Corrigida */

/* Reset básico */
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

/* Variáveis CSS */
:root {
    --primary: #2a9d8f;
    --primary-dark: #1e7376;
    --secondary: #e9c46a;
    --success: #06d6a0;
    --warning: #f4a261;
    --danger: #e76f51;
    --info: #219ebc;
    --light: #f8f9fa;
    --dark: #343a40;
    --white: #f8f9fa;
    --gray-100: #f8f9fa;
    --gray-200: #e9ecef;
    --gray-300: #dee2e6;
    --gray-400: #ced4da;
    --gray-500: #adb5bd;
    --gray-600: #6c757d;
    --gray-700: #495057;
    --gray-800: #343a40;
    --gray-900: #212529;
    
    /* Background colors */
    --bg-primary: var(--white);
    --bg-secondary: #f8f9fa;
    --bg-tertiary: #e9ecef;
    
    /* Text colors */
    --text-color: var(--gray-800);
    --text-muted: var(--gray-600);
    --text-light: var(--gray-500);
    
    /* Status colors with light variants */
    --primary-light: rgba(42, 157, 143, 0.1);
    --success-light: rgba(6, 214, 160, 0.1);
    --warning-light: rgba(244, 162, 97, 0.1);
    --danger-light: rgba(231, 111, 81, 0.1);
    --info-light: rgba(33, 158, 188, 0.1);
    
    /* Spacing */
    --border-radius: 12px;
    --border-radius-sm: 6px;
    --border-radius-lg: 16px;
    --border-radius-full: 50px;
    
    /* Shadows */
    --shadow-sm: 0 2px 4px rgba(0, 0, 0, 0.06);
    --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.1);
    --shadow-lg: 0 8px 24px rgba(0, 0, 0, 0.12);
    
    /* Transitions */
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    --transition-fast: all 0.15s ease;
}




/* Container principal do dashboard */
.dashboard-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
    animation: fadeInUp 0.6s ease-out;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Welcome Banner */
.dashboard-welcome {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    color: white;
    border-radius: var(--border-radius-lg);
    padding: 40px;
    margin-bottom: 32px;
    box-shadow: var(--shadow-lg);
    position: relative;
    overflow: hidden;
}

.dashboard-welcome::before {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 200px;
    height: 200px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 50%;
    transform: translate(50%, -50%);
}

.welcome-content {
    position: relative;
    z-index: 2;
}

.welcome-title {
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 12px;
    background: linear-gradient(45deg, rgba(255, 255, 255, 1), rgba(255, 255, 255, 0.8));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.welcome-subtitle {
    font-size: 1.1rem;
    opacity: 0.9;
    margin-bottom: 24px;
    max-width: 600px;
    line-height: 1.6;
}

/* Quick Actions */
.quick-actions {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
}

.quick-action-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(255, 255, 255, 0.15);
    color: white;
    padding: 12px 20px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-radius: var(--border-radius);
    text-decoration: none;
    font-weight: 600;
    transition: var(--transition);
    backdrop-filter: blur(10px);
}

.quick-action-btn:hover {
    background: rgba(255, 255, 255, 0.25);
    border-color: rgba(255, 255, 255, 0.5);
    transform: translateY(-2px);
    box-shadow: 0 8px 15px rgba(0, 0, 0, 0.2);
}

/* Filtro de período */
.period-filter {
    display: flex;
    gap: 8px;
    margin-bottom: 32px;
    background: var(--white);
    padding: 8px;
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-sm);
    flex-wrap: wrap;
}

.period-btn {
    padding: 10px 20px;
    border: none;
    border-radius: var(--border-radius-sm);
    background: transparent;
    color: var(--text-muted);
    text-decoration: none;
    font-weight: 500;
    transition: var(--transition);
    cursor: pointer;
}

.period-btn:hover {
    background: var(--bg-secondary);
    color: var(--text-color);
}

.period-btn.active {
    background: var(--primary);
    color: white;
    box-shadow: var(--shadow-sm);
}

/* Grid do dashboard */
.dashboard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 24px;
    margin-bottom: 32px;
}

/* Cards do dashboard */
.dashboard-card {
    background: var(--white);
    border-radius: var(--border-radius);
    padding: 24px;
    box-shadow: var(--shadow-sm);
    transition: var(--transition);
    position: relative;
    overflow: hidden;
}

.dashboard-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--primary), var(--secondary));
}

.dashboard-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-md);
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}

.card-title {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.card-icon {
    width: 48px;
    height: 48px;
    border-radius: var(--border-radius);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.2rem;
}

.icon-primary { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); }
.icon-success { background: linear-gradient(135deg, var(--success), #05a085); }
.icon-warning { background: linear-gradient(135deg, var(--warning), #e8944a); }
.icon-danger { background: linear-gradient(135deg, var(--danger), #d85a41); }

.card-value {
    font-size: 2.2rem;
    font-weight: 700;
    color: var(--text-color);
    margin-bottom: 8px;
    line-height: 1.2;
}

.card-subtitle {
    font-size: 0.85rem;
    color: var(--text-muted);
    display: flex;
    align-items: center;
    gap: 8px;
}

.trend-up {
    color: var(--success);
    font-weight: 600;
}

.trend-down {
    color: var(--danger);
    font-weight: 600;
}

/* Container de alertas */
.alert-container {
    background: var(--white);
    border-radius: var(--border-radius);
    padding: 24px;
    margin-bottom: 32px;
    box-shadow: var(--shadow-sm);
    border-left: 4px solid var(--warning);
}

.alert-header {
    margin-bottom: 20px;
}

.alert-title {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--text-color);
    display: flex;
    align-items: center;
    gap: 12px;
}

.alert-item {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 16px;
    border-radius: var(--border-radius);
    background: var(--warning-light);
    margin-bottom: 12px;
    transition: var(--transition);
    animation: slideIn 0.5s ease-out forwards;
    opacity: 0;
    transform: translateX(-20px);
}

@keyframes slideIn {
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.alert-item:hover {
    transform: translateX(5px);
}

.alert-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    flex-shrink: 0;
}

.alert-danger { background-color: var(--danger); }

.alert-content {
    flex: 1;
}

.alert-message {
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-color);
    margin-bottom: 4px;
}

.alert-description {
    font-size: 0.85rem;
    color: var(--text-muted);
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.alert-action .action-btn {
    background: var(--primary);
    color: white;
    padding: 8px 16px;
    border: none;
    border-radius: var(--border-radius-sm);
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: var(--transition);
    font-size: 0.85rem;
}

.alert-action .action-btn:hover {
    background: var(--primary-dark);
    transform: translateY(-2px);
}

/* Layout de linha do dashboard */
.dashboard-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
    margin-bottom: 32px;
}

/* Containers de gráficos e tabelas - CORRIGIDO */
.chart-container, .table-container {
    background: var(--white);
    border-radius: var(--border-radius);
    padding: 24px;
    box-shadow: var(--shadow-sm);
    transition: var(--transition);
    position: relative;
    min-height: 400px; /* Altura mínima fixa */
    max-height: 600px; /* Altura máxima fixa */
    display: flex;
    flex-direction: column;
}

.chart-container:hover, .table-container:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

/* Header dos gráficos */
.chart-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-shrink: 0; /* Não permitir que encolha */
}

.chart-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--text-color);
    display: flex;
    align-items: center;
    gap: 8px;
}

.chart-actions {
    display: flex;
    gap: 8px;
}

.chart-action-btn {
    background-color: var(--gray-200);
    border: none;
    border-radius: var(--border-radius-sm);
    padding: 6px 12px;
    font-size: 0.8rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: var(--transition);
    color: var(--text-color);
    text-decoration: none;
}

.chart-action-btn:hover {
    background-color: var(--gray-300);
    transform: translateY(-1px);
}

/* Canvas dos gráficos - CORRIGIDO */
canvas {
    flex: 1;
    width: 100% !important;
    height: auto !important;
    min-height: 250px !important;
    max-height: 400px !important;
}

/* Estado vazio - CORRIGIDO */
.chart-empty-state {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    text-align: center;
    z-index: 10;
    border-radius: var(--border-radius);
    max-width: 90%;
    width: auto;
    min-width: 300px;
}

/* Ocultar estado vazio quando há dados */
.has-data .chart-empty-state {
    display: none !important;
}

/* Blur do gráfico quando vazio */
.chart-container:not(.has-data) canvas {
    filter: blur(3px);
    opacity: 0.3;
    pointer-events: none;
}


@keyframes pulse {
    0%, 100% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.05); opacity: 0.8; }
}

.empty-state-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-color);
    margin-bottom: 12px;
}

.empty-state-message {
    font-size: 1rem;
    color: var(--text-muted);
    margin-bottom: 24px;
    max-width: 350px;
    line-height: 1.5;
    margin-left: auto;
    margin-right: auto;
}

.empty-state-actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    justify-content: center;
}

.action-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 20px;
    border: none;
    border-radius: var(--border-radius);
    font-weight: 600;
    text-decoration: none;
    transition: var(--transition);
    cursor: pointer;
    font-size: 0.9rem;
}

.action-btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
}

.action-btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(42, 157, 143, 0.3);
}

.action-btn-secondary {
    background: white;
    color: var(--primary);
    border: 2px solid var(--primary);
}

.action-btn-secondary:hover {
    background: var(--primary);
    color: white;
    transform: translateY(-2px);
}

/* Tabelas do dashboard */
.dashboard-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9rem;
}

.dashboard-table th,
.dashboard-table td {
    padding: 12px 16px;
    text-align: left;
    border-bottom: 1px solid var(--gray-200);
}

.dashboard-table th {
    background-color: var(--bg-secondary);
    font-weight: 600;
    color: var(--text-color);
    text-transform: uppercase;
    font-size: 0.8rem;
    letter-spacing: 0.5px;
}

.dashboard-table tr:hover {
    background-color: var(--bg-secondary);
}

/* Status badges */
.status-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: var(--border-radius-full);
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-badge.low { 
    background-color: var(--danger-light); 
    color: var(--danger); 
}

.status-badge.medium { 
    background-color: var(--warning-light); 
    color: var(--warning); 
}

.status-badge.high { 
    background-color: var(--success-light); 
    color: var(--success); 
}

/* Progress bars */
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

.progress-primary { background: linear-gradient(90deg, var(--primary), var(--primary-dark)); }
.progress-success { background: linear-gradient(90deg, var(--success), #05a085); }
.progress-warning { background: linear-gradient(90deg, var(--warning), #e8944a); }
.progress-danger { background: linear-gradient(90deg, var(--danger), #d85a41); }

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

/* Cartão motivacional */
.motivational-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: var(--border-radius);
    padding: 30px;
    margin-bottom: 32px;
    text-align: center;
    box-shadow: var(--shadow-md);
}

.motivational-card h2 {
    font-size: 1.5rem;
    margin-bottom: 16px;
}

.motivational-card p {
    font-size: 1rem;
    opacity: 0.9;
    margin-bottom: 20px;
}

/* Menu hamburger */
.ham-menu {
    width: 30px;
    height: 30px;
    position: relative;
    cursor: pointer;
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
    transition: 0.25s ease-in-out;
}

.ham-menu span:nth-child(1) {
    top: 0px;
}

.ham-menu span:nth-child(2) {
    top: 10px;
}

.ham-menu span:nth-child(3) {
    top: 20px;
}

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

/* Menu off-screen */
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

.off-screen-menu.active, 
.off-screen-menu.show {
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
    border-bottom: 1px solid var(--gray-200);
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

/* Responsividade */
@media (max-width: 1200px) {
    .dashboard-row {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .dashboard-container {
        padding: 16px;
    }
    
    .welcome-title {
        font-size: 2rem;
    }
    
    .welcome-subtitle {
        font-size: 1rem;
    }
    
    .quick-actions {
        flex-direction: column;
    }
    
    .quick-action-btn {
        width: 100%;
        justify-content: center;
    }
    
    .dashboard-grid {
        grid-template-columns: 1fr;
        gap: 16px;
    }
    
    .dashboard-row {
        gap: 16px;
    }
    
    .chart-container, .table-container {
        padding: 16px;
        min-height: 350px;
    }
    
    .empty-state-actions {
        flex-direction: column;
        width: 100%;
    }
    
    .action-btn {
        width: 100%;
        justify-content: center;
    }
    
    .empty-state-message {
        font-size: 0.9rem;
    }
    
    .empty-state-title {
        font-size: 1.2rem;
    }
    
    .chart-empty-state {
        padding: 16px;
    }
    
    .period-filter {
        flex-wrap: wrap;
        gap: 4px;
    }
    
    .period-btn {
        flex: 1;
        min-width: 100px;
        text-align: center;
    }
    
    .alert-item {
        flex-direction: column;
        text-align: center;
        gap: 12px;
    }
    
    .alert-description {
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .welcome-title {
        font-size: 1.5rem;
    }
    
    .card-value {
        font-size: 1.8rem;
    }
    
    .dashboard-table {
        font-size: 0.8rem;
    }
    
    .dashboard-table th,
    .dashboard-table td {
        padding: 8px 12px;
    }
}
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
            <!-- Gráfico de Vendas -->
            <div class="chart-container <?php echo $dados_dashboard['tem_dados_grafico'] ? 'has-data' : ''; ?>">
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
                
                <!-- Estado vazio para vendas -->
                <?php if (!$dados_dashboard['tem_dados_grafico']): ?>
                <div class="chart-empty-state">

                    <h3 class="empty-state-title">Sem Vendas Registradas</h3>
                    <p class="empty-state-message">
                        Comece registrando suas primeiras vendas para visualizar o desempenho do seu negócio em tempo real.
                    </p>
                    <div class="empty-state-actions">
                        <a href="vendas.php" class="action-btn action-btn-primary">
                            <i class="fas fa-shopping-cart"></i>
                            Registrar Venda
                        </a>
                        <a href="adicionar_material.php" class="action-btn action-btn-secondary">
                            <i class="fas fa-plus"></i>
                            Adicionar Produto
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Gráfico de Produtos Mais Vendidos -->
            <div class="chart-container <?php echo $dados_dashboard['tem_produtos_vendidos'] ? 'has-data' : ''; ?>">
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
                
                <!-- Estado vazio para produtos -->
                <?php if (!$dados_dashboard['tem_produtos_vendidos']): ?>
                <div class="chart-empty-state">
                    <h3 class="empty-state-title">Nenhum Produto Vendido</h3>
                    <p class="empty-state-message">
                        Adicione produtos ao seu estoque e comece a vender para ver o ranking dos mais vendidos.
                    </p>
                    <div class="empty-state-actions">
                        <a href="adicionar_material.php" class="action-btn action-btn-primary">
                            <i class="fas fa-box"></i>
                            Adicionar Produto
                        </a>
                        <a href="vendas.php" class="action-btn action-btn-secondary">
                            <i class="fas fa-shopping-cart"></i>
                            Fazer Venda
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="dashboard-row">
            <!-- Gráfico de Categorias -->
            <div class="chart-container <?php echo $dados_dashboard['tem_categorias'] ? 'has-data' : ''; ?>">
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
                
                <!-- Estado vazio para categorias -->
                <?php if (!$dados_dashboard['tem_categorias']): ?>
                <div class="chart-empty-state">

                    <h3 class="empty-state-title">Sem Categorias</h3>
                    <p class="empty-state-message">
                        Organize seu estoque criando categorias e adicionando produtos para uma melhor gestão.
                    </p>
                    <div class="empty-state-actions">
                        <a href="gerenciar_categorias.php" class="action-btn action-btn-primary">
                            <i class="fas fa-tags"></i>
                            Criar Categoria
                        </a>
                        <a href="adicionar_material.php" class="action-btn action-btn-secondary">
                            <i class="fas fa-plus"></i>
                            Adicionar Produto
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Tabela de Categorias -->
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
                
                <?php if ($dados_dashboard['tem_categorias']): ?>
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
                <?php else: ?>
                <div style="text-align: center; padding: 40px; color: #666;">
                    <i class="fas fa-tags" style="font-size: 48px; margin-bottom: 16px; opacity: 0.3;"></i>
                    <h4>Nenhuma categoria encontrada</h4>
                    <p>Crie categorias para organizar melhor seus produtos.</p>
                    <a href="gerenciar_categorias.php" class="action-btn action-btn-primary" style="margin-top: 16px;">
                        <i class="fas fa-plus"></i> Criar Primeira Categoria
                    </a>
                </div>
                <?php endif; ?>
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
  // Dados para os gráficos
  const vendasData = <?php echo json_encode($dashboard_data['vendas_diarias']); ?>;
        const produtosData = <?php echo json_encode($dashboard_data['top_produtos']); ?>;
        const categoriasData = <?php echo json_encode($dashboard_data['categorias']); ?>;
        const temDados = <?php echo json_encode($dados_dashboard); ?>;

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
                'ñ': 'n', 'Ñ': 'N'
            };
            
            return texto.replace(/[àáâãäÀÁÂÃÄèéêëÈÉÊËìíîïÌÍÎÏòóôõöÒÓÔÕÖùúûüÙÚÛÜçÇñÑ]/g, 
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
            
            // Header do documento
            doc.setFontSize(24);
            doc.setTextColor(42, 157, 143);
            doc.text('STOCKLY - Dashboard Report', margin, yPos);
            yPos += 15;
            
            // Data e usuário
            doc.setFontSize(12);
            doc.setTextColor(102, 102, 102);
            const dataGeracao = new Date().toLocaleDateString('pt-BR') + ' às ' + new Date().toLocaleTimeString('pt-BR');
            const usuarioNome = '<?php echo htmlspecialchars($usuario['nome']); ?>';
            
            doc.text('Gerado em: ' + dataGeracao, margin, yPos);
            yPos += 8;
            doc.text('Usuario: ' + normalizarTextoParaPDF(usuarioNome), margin, yPos);
            yPos += 15;
            
            // Métricas principais
            doc.setFontSize(16);
            doc.setTextColor(42, 157, 143);
            doc.text('METRICAS PRINCIPAIS', margin, yPos);
            yPos += 15;
            
            doc.setFontSize(11);
            doc.setTextColor(0, 0, 0);
            
            const valorEstoque = 'R$ <?php echo number_format($estoque_stats["valor_total_estoque"], 2, ",", "."); ?>';
            const vendasTotais = 'R$ <?php echo number_format($vendas_mes["receita_total"], 2, ",", "."); ?>';
            const lucroLiquido = 'R$ <?php echo number_format($vendas_mes["lucro_bruto"], 2, ",", "."); ?>';
            const itensEstoque = '<?php echo number_format($estoque_stats["total_itens"]); ?>';
            
            doc.text('Valor Total em Estoque: ' + valorEstoque, margin, yPos);
            yPos += 8;
            doc.text('Vendas Totais: ' + vendasTotais, margin, yPos);
            yPos += 8;
            doc.text('Lucro Liquido: ' + lucroLiquido, margin, yPos);
            yPos += 8;
            doc.text('Itens em Estoque: ' + itensEstoque, margin, yPos);
            yPos += 15;
            
            // Salvar o PDF
            const hoje = new Date();
            const nomeArquivo = `dashboard_stockly_${hoje.getFullYear()}-${String(hoje.getMonth() + 1).padStart(2, '0')}-${String(hoje.getDate()).padStart(2, '0')}.pdf`;
            doc.save(nomeArquivo);
        }

        // Esperar que o DOM esteja completamente carregado
        document.addEventListener('DOMContentLoaded', function() {
            // Só criar gráficos se houver dados
            if (temDados.tem_dados_grafico) {
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
            }

            if (temDados.tem_produtos_vendidos) {
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
            }

            if (temDados.tem_categorias) {
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
            }
        });
    </script>
    <script src="../js/app.js"></script>
    <script src="../js/charts.js"></script>
</body>
</html>