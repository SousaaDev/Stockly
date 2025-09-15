<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header('Location: ../index.php');
    exit();
}

include 'dashboard_data.php';


$usuario = $_SESSION['usuario'];

// Verifica se a foto de perfil está definida, caso contrário, define uma padrão
if (!isset($usuario['foto_perfil']) || empty($usuario['foto_perfil'])) {
    $usuario['foto_perfil'] = '../uploads/basico.png';
} else {
    $usuario['foto_perfil'] = htmlspecialchars($usuario['foto_perfil']);
}

// Conexão com o banco de dados
$conn = new mysqli('localhost', 'root', '', 'ga3_stockly');

if ($conn->connect_error) {
    die("Conexão falhou: " . $conn->connect_error);
}

function registrar_atividade($conn, $usuario_id, $atividade) {
    $stmt = $conn->prepare("INSERT INTO ga3_atividades (usuario_id, atividade) VALUES (?, ?)");
    $stmt->bind_param("is", $usuario_id, $atividade);
    $stmt->execute();
    $stmt->close();
}

// Processar a submissão do formulário de venda ANTES de qualquer output
$mensagem = '';
$tipo_mensagem = '';
$redirect = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['registrar_venda'])) {
    // Validar os dados do formulário
    $material_id = $_POST['material'];
    $quantidade_venda = $_POST['quantidade_venda'];
    $valor_unitario_venda_estimado = str_replace(',', '.', $_POST['valor_unitario_venda_estimado']);
    
    // Verificar se temos estoque suficiente
    $sql_verifica_estoque = "SELECT quantidade, valor_unitario_venda_estimado FROM ga3_materiais WHERE id = ?";
    $stmt_verifica = $conn->prepare($sql_verifica_estoque);
    $stmt_verifica->bind_param("i", $material_id);
    $stmt_verifica->execute();
    $result_verifica = $stmt_verifica->get_result();
    $material = $result_verifica->fetch_assoc();
    $stmt_verifica->close();

    if ($material && $material['quantidade'] >= $quantidade_venda) {
        // Calcular o valor total da venda e o lucro bruto
        $valor_total_venda = $quantidade_venda * $valor_unitario_venda_estimado;
        $custo_total = $quantidade_venda * $material['valor_unitario_venda_estimado'];
        $lucro_bruto = $valor_total_venda - $custo_total;
        $data_atual = date('Y-m-d');
        
        // Iniciar uma transação para garantir que ambas as operações sejam concluídas
        $conn->begin_transaction();
        
        try {
            // Registrar a transação na tabela ga3_transacoes
            $sql_inserir = "INSERT INTO ga3_transacoes (material_id, quantidade, valor_venda, custo_material, data_hora, data_venda, usuario_id) 
            VALUES (?, ?, ?, ?, NOW(), ?, ?)";
            $stmt_inserir = $conn->prepare($sql_inserir);
            $stmt_inserir->bind_param("idddsi", $material_id, $quantidade_venda, $valor_total_venda, $custo_total, $data_atual, $usuario['id']);
            $stmt_inserir->execute();
            $stmt_inserir->close();
            
            // Verificar se já existe um registro para a data atual na tabela ga3_vendas
            $sql_check_vendas = "SELECT id, receita, lucro_bruto FROM ga3_vendas WHERE data_venda = ?";
            $stmt_check = $conn->prepare($sql_check_vendas);
            $stmt_check->bind_param("s", $data_atual);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            
            if ($row_vendas = $result_check->fetch_assoc()) {
                // Atualizar o registro existente
                $nova_receita = $row_vendas['receita'] + $valor_total_venda;
                $novo_lucro = $row_vendas['lucro_bruto'] + $lucro_bruto;
                
                $sql_update_vendas = "UPDATE ga3_vendas SET receita = ?, lucro_bruto = ? WHERE id = ?";
                $stmt_update = $conn->prepare($sql_update_vendas);
                $stmt_update->bind_param("ddi", $nova_receita, $novo_lucro, $row_vendas['id']);
                $stmt_update->execute();
                $stmt_update->close();
            } else {
                // Inserir um novo registro
                $sql_insert_vendas = "INSERT INTO ga3_vendas (data_venda, receita, lucro_bruto) VALUES (?, ?, ?)";
                $stmt_insert = $conn->prepare($sql_insert_vendas);
                $stmt_insert->bind_param("sdd", $data_atual, $valor_total_venda, $lucro_bruto);
                $stmt_insert->execute();
                $stmt_insert->close();
            }
            
            $stmt_check->close();
            
            // Atualizar o estoque
            $nova_quantidade = $material['quantidade'] - $quantidade_venda;
            $sql_atualizar = "UPDATE ga3_materiais SET quantidade = ? WHERE id = ?";
            $stmt_atualizar = $conn->prepare($sql_atualizar);
            $stmt_atualizar->bind_param("ii", $nova_quantidade, $material_id);
            $stmt_atualizar->execute();
            $stmt_atualizar->close();
            
            // Registrar a atividade
            registrar_atividade($conn, $usuario['id'], "Venda de {$quantidade_venda} unidades do material ID {$material_id}");
            
            // Confirmar a transação
            $conn->commit();
            
            // Marcar para redirecionamento
            $redirect = true;
            
        } catch (Exception $e) {
            // Desfazer a transação em caso de erro
            $conn->rollback();
            $mensagem = "Erro ao registrar a venda: " . $e->getMessage();
            $tipo_mensagem = "error";
        }
    } else {
        $mensagem = "Estoque insuficiente para concluir esta venda.";
        $tipo_mensagem = "error";
    }
}

// Se deve redirecionar, fazer antes de qualquer output
if ($redirect) {
    header("Location: vendas.php?success=1");
    exit();
}

// Verificar se houve sucesso na URL
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $mensagem = "Venda registrada com sucesso!";
    $tipo_mensagem = "success";
}

// Obter informações de vendas do dia
$hoje = date('Y-m-d');
$sql_vendas_hoje = "SELECT COUNT(*) as total_vendas, SUM(valor_venda) as valor_total 
                    FROM ga3_transacoes 
                    WHERE DATE(data_venda) = ?";
$stmt_vendas_hoje = $conn->prepare($sql_vendas_hoje);
$stmt_vendas_hoje->bind_param("s", $hoje);
$stmt_vendas_hoje->execute();
$result_vendas_hoje = $stmt_vendas_hoje->get_result();
$vendas_hoje = $result_vendas_hoje->fetch_assoc();
$stmt_vendas_hoje->close();

// Formatar os valores
$total_vendas_hoje = $vendas_hoje['total_vendas'] ?? 0;
$valor_total_hoje = number_format(($vendas_hoje['valor_total'] ?? 0), 2, ',', '.');

// Obter lista de materiais disponíveis
$sql_materiais = "SELECT m.id, m.descricao, m.quantidade, m.valor_unitario_venda_estimado, m.valor_unitario_venda_estimado, c.nome as categoria 
                 FROM ga3_materiais m 
                 LEFT JOIN ga3_categorias c ON m.categoria_id = c.id 
                 WHERE m.quantidade > 0 
                 ORDER BY m.descricao";
$result_materiais = $conn->query($sql_materiais);
$materiais = [];

while ($row = $result_materiais->fetch_assoc()) {
    $materiais[] = $row;
}

// Obter as últimas vendas
// Obter as últimas vendas
$sql_ultimas_vendas = "SELECT t.id, t.material_id, m.descricao, t.quantidade, t.valor_venda, t.data_venda, t.data_hora 
                     FROM ga3_transacoes t 
                     JOIN ga3_materiais m ON t.material_id = m.id 
                     ORDER BY t.data_hora DESC 
                     LIMIT 5";
$result_ultimas_vendas = $conn->query($sql_ultimas_vendas);
$ultimas_vendas = [];

while ($row = $result_ultimas_vendas->fetch_assoc()) {
    $ultimas_vendas[] = $row;
}



// Obter lista de materiais disponíveis
$sql_materiais = "SELECT m.id, m.descricao, m.quantidade, m.valor_unitario_venda_estimado, m.valor_unitario_venda_estimado, c.nome as categoria 
                 FROM ga3_materiais m 
                 LEFT JOIN ga3_categorias c ON m.categoria_id = c.id 
                 WHERE m.quantidade > 0 
                 ORDER BY m.descricao";
$result_materiais = $conn->query($sql_materiais);
$materiais = [];

while ($row = $result_materiais->fetch_assoc()) {
    $materiais[] = $row;
}   
$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Histórico de Vendas - Stockly</title>
    <link rel="stylesheet" href="../css/styleprincipal.css">
    <link rel="stylesheet" href="dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
    <style>
/* Reset CSS */
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

/* Variáveis globais */
:root {
  --primary-color: #2a9d8f;
  --primary-dark: #1e7376;
  --secondary-color: #f0f9ff;
  --light-gray: #f5f5f5;
  --medium-gray: #e0e0e0;
  --dark-gray: #333;
  --text-color: #333;
  --success-color: #27ae60;
  --danger-color: #e74c3c;
  --warning-color: #f39c12;
  --bg-secondary: #e9ecef;
}

/* Layout principal */
.content-wrapper {
  max-width: 1200px;
  margin: 20px auto;
  padding: 20px;
  background: #fff;
  border-radius: 8px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

h1 {
  color: var(--primary-color);
  margin-bottom: 25px;
  font-size: 28px;
  border-bottom: 2px solid var(--primary-color);
  padding-bottom: 10px;
}

/* Layout de Grid do Conteúdo para Vendas */
.sales-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
    margin-top: 20px;
}

#form-container {
    grid-column: 1;
}

#historico-container {
    grid-column: 2;
}

/* Estilos dos Cards */
.card {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    padding: 20px;
    margin-bottom: 30px;
    transition: all 0.3s ease;
}

.card h2 {
    color: var(--primary-color);
    font-size: 20px;
    margin-top: 0;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--primary-color);
    display: flex;
    align-items: center;
    font-weight: 500;
}

.card h2 i {
    margin-right: 10px;
}

/* Estilo do Formulário */
#transacao-form {
    display: flex;
    flex-direction: column;
}

#transacao-form label {
    display: block;
    margin: 12px 0 6px;
    font-weight: 500;
    color: var(--dark-gray);
}

#transacao-form input, 
#transacao-form select {
    width: 100%;
    padding: 12px;
    margin-bottom: 15px;
    border: 1px solid var(--medium-gray);
    border-radius: 4px;
    font-size: 16px;
    transition: all 0.3s ease;
}

#transacao-form input:focus, 
#transacao-form select:focus {
    border-color: var(--primary-color);
    outline: none;
    box-shadow: 0 0 0 2px rgba(42, 157, 143, 0.25);
}

#transacao-form button {
    background-color: var(--primary-color);
    color: white;
    border: none;
    border-radius: 4px;
    padding: 12px 20px;
    font-size: 16px;
    cursor: pointer;
    width: 100%;
    margin-top: 20px;
    transition: all 0.3s ease;
}

#transacao-form button:hover {
    background-color: var(--primary-dark);
}

#quantidade_disponivel {
    background-color: var(--light-gray);
    padding: 10px;
    border-radius: 4px;
    margin-bottom: 15px;
    font-weight: 500;
}

/* Estilo do Histórico */
.historico-lista {
    list-style: none;
    padding: 0;
    margin: 0;
}

.historico-item {
    padding: 15px;
    border-bottom: 1px solid var(--medium-gray);
    display: flex;
    justify-content: space-between;
}

.historico-item:last-child {
    border-bottom: none;
}

.historico-item.empty {
    justify-content: center;
    color: var(--text-color);
    font-style: italic;
}

.historico-info {
    flex-grow: 1;
}

.historico-material {
    font-weight: 500;
    color: var(--dark-gray);
}

.historico-details {
    color: var(--text-color);
    font-size: 14px;
}

.historico-valor {
    font-weight: bold;
    color: var(--primary-color);
}

.ver-mais {
    display: inline-block;
    margin-top: 15px;
    color: var(--primary-color);
    text-decoration: none;
    font-weight: 500;
    transition: all 0.3s ease;
}

.ver-mais:hover {
    color: var(--primary-dark);
    text-decoration: underline;
}

.ver-mais i {
    margin-left: 5px;
}

/* Cards de Estatísticas */
.stats {
    display: flex;
    gap: 20px;
    margin-bottom: 20px;
}

.stat-card {
    background-color: #fff;
    border-radius: 8px;
    padding: 15px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    min-width: 150px;
    text-align: center;
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
}

.stat-card .stat-value {
    font-size: 24px;
    font-weight: 700;
    color: var(--primary-color);
    margin: 5px 0;
}

.stat-card .stat-label {
    font-size: 14px;
    color: var(--text-color);
}

/* Alertas */
.alert {
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 4px;
    display: flex;
    align-items: center;
    gap: 10px;
    transition: all 0.3s ease;
}

.alert-success {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-error, .alert-danger {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

/* Media Queries */
@media (max-width: 768px) {
    .sales-grid {
        grid-template-columns: 1fr;
        display: flex;
        flex-direction: column;
    }
    
    #form-container {
        order: 1;
        width: 100%;
    }
    
    #historico-container {
        order: 2;
        width: 100%;
    }
    
    .stats {
        flex-direction: column;
        gap: 10px;
    }
    
    .stat-card {
        min-width: unset;
        flex: 1;
        margin-bottom: 10px;
    }
}

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
    </style>
</head>
<header class="site-header">
    <div class="container">
        <a href="dashboard.php">
            <div class="divlogo">
                <img class="logo" src="../Imagens/logo.png" width="160px" height="80px" alt="Logo Stockly">
            </div>
        </a>
        <nav class="site-nav">
            <ul class="menu">
                <li><a href="dashboard.php"><i class="fas fa-home"></i> Home</a></li>
                <li><a href="../php/sobre.php"><i class="fas fa-book-open"></i> Sobre</a></li>
                <li><a href="../php/contato.php"><i class="fas fa-address-book"></i> Contato</a></li>
            </ul>
        </nav>

        <div class="dropdown">
        <button class="dropbtn" id="dropdownButton">
        <img src="<?php echo htmlspecialchars($usuario['foto_perfil']); ?>" alt="Foto de Perfil" class="perfil-foto">
    </button>
            <div class="dropdown-content" id="dropdownContent">
                <a href="../php/perfil.php"><i class="fas fa-user"></i> Perfil</a>
                <?php if (isset($usuario['cargo']) && in_array($usuario['cargo'], ['chefe', 'gerente'])): ?>
                    <a href="../php/configuracoes.php"><i class="fas fa-cog"></i> Configurações</a>
                <?php endif; ?>
                <a href="../php/logout.php"><i class="fas fa-sign-out-alt"></i> Sair</a>
            </div>
        </div>
        <div class="ham-menu" id="hamburgerMenu">
            <span></span>
            <span></span>
            <span></span>
        </div>
    </div>
</header>

<div class="sub-navbar desktop-only">
    <ul>
        <li><a href="adicionar_material.php" class="nav-link" data-page="adicionar_material"><i class="fas fa-plus-circle"></i> Adicionar material</a></li>
        <li><a href="gerenciar_categorias.php" class="nav-link" data-page="gerenciar_categorias"><i class="fas fa-tags"></i> Gerenciar categorias</a></li>
        <li><a href="vendas.php" class="active nav-link" data-page="computar_saida"><i class="fas fa-shopping-cart"></i> Marcar venda</a></li>
        <li><a href="estoque.php" class="nav-link" data-page="estoque"><i class="fas fa-boxes"></i> Estoque</a></li>
        <li><a href="historico_vendas.php" class="nav-link" data-page="historico_saida"><i class="fas fa-chart-line"></i> Histórico de venda</a></li>
        <li><a href="historico_despesas.php" class="nav-link" data-page="historico_despesas"><i class="fas fa-receipt"></i> Histórico de despesas</a></li>
    </ul>
</div>

<div class="off-screen-menu" id="offScreenMenu">
    <ul>
        <li><a href="../php/perfil.php"><i class="fas fa-user"></i> Perfil</a></li>
        <?php if (isset($usuario['cargo']) && $usuario['cargo'] === 'chefe'): ?>
            <li><a href="../php/configuracoes.php"><i class="fas fa-cog"></i> Configurações</a>
                <ul>
                    <li><a href="adicionar_material.php" class="nav-link" data-page="adicionar_material"><i class="fas fa-plus-circle"></i> Adicionar material</a></li>
                    <li><a href="gerenciar_categorias.php" class="nav-link" data-page="gerenciar_categorias"><i class="fas fa-tags"></i> Gerenciar categorias</a></li>
                    <li><a href="vendas.php" class="nav-link" data-page="computar_saida"><i class="fas fa-shopping-cart"></i> Marcar venda</a></li>
                    <li><a href="estoque.php" class="nav-link" data-page="estoque"><i class="fas fa-boxes"></i> Estoque</a></li>
                    <li><a href="historico_vendas.php" class="nav-link" data-page="historico_saida"><i class="fas fa-chart-line"></i> Histórico de venda</a></li>
                    <li><a href="historico_despesas.php" class="nav-link" data-page="historico_despesas"><i class="fas fa-receipt"></i> Histórico de despesas</a></li>
                </ul>
            </li>
        <?php endif; ?>
        <li><a href="../sobre.php"><i class="fas fa-book-open"></i> Sobre</a></li>
        <li><a href="../contato.php"><i class="fas fa-address-book"></i> Contato</a></li>
        <li><a href="../php/logout.php"><i class="fas fa-sign-out-alt"></i> Sair</a></li>
    </ul>
</div>
<body>
    <div class="content-wrapper">
        <h1>Histórico de Vendas</h1>
        
        <!-- Cards de Estatísticas -->
        <div class="stats">
            <div class="stat-card">
                <div class="stat-value" id="vendas-hoje"><?php echo $total_vendas_hoje; ?></div>
                <div class="stat-label">Vendas hoje</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="valor-hoje">R$ <?php echo $valor_total_hoje; ?></div>
                <div class="stat-label">Valor total hoje</div>
            </div>
        </div>

        <?php if(!empty($mensagem)): ?>
        <div class="alert alert-<?php echo $tipo_mensagem; ?>">
            <i class="fas fa-<?php echo $tipo_mensagem == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <?php echo $mensagem; ?>
        </div>
        <?php endif; ?>

        <!-- Layout de Grid para Vendas -->
        <div class="sales-grid">
            <div id="form-container" class="card">
                <h2><i class="fas fa-plus-circle"></i> Nova Transação</h2>
                <form id="transacao-form" method="POST" action="vendas.php">
                <label for="material"><i class="fas fa-box"></i> Material:</label>
<select id="material" name="material" required>
    <option value="">Selecione um material</option>
    <?php foreach($materiais as $material): ?>
        <option value="<?php echo $material['id']; ?>" 
                data-quantity="<?php echo $material['quantidade']; ?>"
                data-price="<?php echo $material['valor_unitario_venda_estimado'] ? number_format($material['valor_unitario_venda_estimado'], 2, ',', '.') : '0,00'; ?>">
            <?php echo htmlspecialchars($material['descricao']); ?> (<?php echo $material['quantidade']; ?> un.)
        </option>
    <?php endforeach; ?>
</select>
                         <!-- This extra endforeach is causing the error -->
                    </select>
                    
                    <label for="quantidade_venda"><i class="fas fa-sort-amount-up"></i> Quantidade Vendida:</label>
                    <input type="number" id="quantidade_venda" name="quantidade_venda" required min="1" max="1000">
                    
                    <div id="quantidade_disponivel" class="info-box">
                        <i class="fas fa-info-circle"></i> Quantidade disponível: <span id="qtd-disp-valor">0</span>
                    </div>
                    
                    <label for="valor_unitario_venda_estimado"><i class="fas fa-dollar-sign"></i> Valor Unitário de Venda (R$):</label>
                    <input type="text" id="valor_unitario_venda_estimado" name="valor_unitario_venda_estimado" required >
            
                    <div class="form-actions">
                        <button type="submit" name="registrar_venda"><i class="fas fa-check"></i> Registrar Venda</button>
                    </div>
                </form>
            </div>
            
            <div id="historico-container" class="card">
                <h2><i class="fas fa-history"></i> Últimas Vendas</h2>
                <ul class="historico-lista" id="ultimas-vendas">
                    <?php if(count($ultimas_vendas) > 0): ?>
                        <?php foreach($ultimas_vendas as $venda): ?>
                            <li class="historico-item">
                                <div class="historico-info">
                                    <div class="historico-material"><?php echo htmlspecialchars($venda['descricao']); ?></div>
                                    <div class="historico-details">
                                        <?php echo $venda['quantidade']; ?> unidades • 
                                        <?php 
                                            $data_venda = new DateTime($venda['data_hora']);
                                            $hoje = new DateTime('today');
                                            $ontem = new DateTime('yesterday');
                                            
                                            if ($data_venda->format('Y-m-d') === $hoje->format('Y-m-d')) {
                                                echo 'Hoje às ' . $data_venda->format('H:i');
                                            } elseif ($data_venda->format('Y-m-d') === $ontem->format('Y-m-d')) {
                                                echo 'Ontem às ' . $data_venda->format('H:i');
                                            } else {
                                                echo $data_venda->format('d/m/Y H:i');
                                            }
                                        ?>
                                    </div>
                                </div>
                                <div class="historico-valor">R$ <?php echo number_format($venda['valor_venda'], 2, ',', '.'); ?></div>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li class="historico-item empty">
                            <div>Nenhuma venda registrada ainda.</div>
                        </li>
                    <?php endif; ?>
                </ul>
                <a href="historico_vendas.php" class="ver-mais">Ver histórico completo <i class="fas fa-arrow-right"></i></a>
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



    <script>
// JavaScript completo e corrigido para vendas.php
document.addEventListener('DOMContentLoaded', function() {
    
    // ==================== ELEMENTOS DOM ====================
    const materialSelect = document.getElementById('material');
    const qtdInput = document.getElementById('quantidade_venda');
    const valorInput = document.getElementById('valor_unitario_venda_estimado');
    const transacaoForm = document.getElementById('transacao-form');
    const dropdownButton = document.getElementById('dropdownButton');
    const dropdownContent = document.getElementById('dropdownContent');
    const hamburgerMenu = document.getElementById('hamburgerMenu');
    const offScreenMenu = document.getElementById('offScreenMenu');

    // ==================== DROPDOWN MENU ====================
    if (dropdownButton && dropdownContent) {
        dropdownButton.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            dropdownContent.classList.toggle('show');
        });

        document.addEventListener('click', function(event) {
            if (!dropdownButton.contains(event.target) && !dropdownContent.contains(event.target)) {
                dropdownContent.classList.remove('show');
            }
        });

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && dropdownContent.classList.contains('show')) {
                dropdownContent.classList.remove('show');
            }
        });
    }

    // ==================== MENU HAMBÚRGUER ====================
    if (hamburgerMenu && offScreenMenu) {
        hamburgerMenu.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            toggleMobileMenu();
        });
        
        function toggleMobileMenu() {
            offScreenMenu.classList.toggle('active');
            hamburgerMenu.classList.toggle('active');
        }
        
        document.addEventListener('click', function(event) {
            if (offScreenMenu.classList.contains('active') && 
                !offScreenMenu.contains(event.target) && 
                !hamburgerMenu.contains(event.target)) {
                offScreenMenu.classList.remove('active');
                hamburgerMenu.classList.remove('active');
            }
        });
        
        const mobileMenuLinks = offScreenMenu.querySelectorAll('a');
        mobileMenuLinks.forEach(link => {
            link.addEventListener('click', function() {
                offScreenMenu.classList.remove('active');
                hamburgerMenu.classList.remove('active');
            });
        });

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && offScreenMenu.classList.contains('active')) {
                offScreenMenu.classList.remove('active');
                hamburgerMenu.classList.remove('active');
            }
        });
    }

    // ==================== SELEÇÃO DE MATERIAL ====================
    if (materialSelect) {
        materialSelect.addEventListener('change', function() {
            const qtdDispElement = document.getElementById('qtd-disp-valor');
            
            if (this.selectedIndex > 0) {
                const selectedOption = this.options[this.selectedIndex];
                const quantidadeDisponivel = selectedOption.dataset.quantity;
                const precoSugerido = selectedOption.dataset.price;
                
                // Atualizar quantidade disponível
                if (qtdDispElement) {
                    qtdDispElement.textContent = quantidadeDisponivel;
                }
                
                // Preencher valor apenas se houver preço válido
                if (valorInput) {
                    if (precoSugerido && precoSugerido !== '0,00' && precoSugerido !== '' && precoSugerido !== 'null') {
                        valorInput.value = precoSugerido;
                    } else {
                        valorInput.value = '';
                        valorInput.placeholder = 'Digite o valor de venda';
                    }
                }
                
                // Ajustar quantidade máxima
                if (qtdInput) {
                    qtdInput.max = quantidadeDisponivel;
                    qtdInput.value = '';
                }
            } else {
                // Resetar valores
                if (qtdDispElement) qtdDispElement.textContent = '0';
                if (valorInput) {
                    valorInput.value = '';
                    valorInput.placeholder = 'Digite o valor de venda';
                }
                if (qtdInput) {
                    qtdInput.value = '';
                    qtdInput.max = '1000';
                }
            }
        });
    }
    
    // ==================== FORMATAÇÃO SIMPLES DO VALOR ====================
    if (valorInput) {
        valorInput.addEventListener('input', function(e) {
            let valor = this.value;
            
            // Permitir apenas números, vírgula e ponto
            valor = valor.replace(/[^\d,.]/g, '');
            
            // Converter ponto em vírgula (para quem digitar com ponto)
            valor = valor.replace(/\./g, ',');
            
            // Permitir apenas uma vírgula
            const partes = valor.split(',');
            if (partes.length > 2) {
                valor = partes[0] + ',' + partes.slice(1).join('');
            }
            
            // Limitar a 2 casas decimais após a vírgula
            if (partes[1] && partes[1].length > 2) {
                valor = partes[0] + ',' + partes[1].substring(0, 2);
            }
            
            this.value = valor;
        });
        
        // Validação apenas ao sair do campo
        valorInput.addEventListener('blur', function() {
            if (this.value === '') return;
            
            const numeroValor = parseFloat(this.value.replace(',', '.'));
            if (isNaN(numeroValor) || numeroValor <= 0) {
                alert('Por favor, insira um valor válido maior que zero.');
                this.focus();
            }
        });
    }
    
    // ==================== VALIDAÇÃO DE QUANTIDADE ====================
    if (qtdInput) {
        qtdInput.addEventListener('input', function() {
            const maxQuantidade = parseInt(this.max) || 1000;
            const valorAtual = parseInt(this.value);
            
            if (valorAtual > maxQuantidade) {
                this.value = maxQuantidade;
                alert('Quantidade máxima disponível: ' + maxQuantidade);
            }
            
            if (valorAtual < 0) {
                this.value = 1;
            }
        });
    }
    
    // ==================== VALIDAÇÃO DO FORMULÁRIO ====================
    if (transacaoForm) {
        transacaoForm.addEventListener('submit', function(e) {
            let isValid = true;
            let mensagemErro = '';
            
            if (!materialSelect || !materialSelect.value) {
                isValid = false;
                mensagemErro = 'Por favor, selecione um material.';
            } else if (!qtdInput || !qtdInput.value || parseInt(qtdInput.value) <= 0) {
                isValid = false;
                mensagemErro = 'Por favor, insira uma quantidade válida.';
            } else if (!valorInput || !valorInput.value || parseFloat(valorInput.value.replace(',', '.')) <= 0) {
                isValid = false;
                mensagemErro = 'Por favor, insira um valor unitário válido.';
            }
            
            if (!isValid) {
                e.preventDefault();
                alert(mensagemErro);
                return false;
            }
            
            const material = materialSelect.options[materialSelect.selectedIndex].text;
            const quantidade = qtdInput.value;
            const valor = valorInput.value;
            const total = (parseInt(quantidade) * parseFloat(valor.replace(',', '.'))).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
            
            const confirmacao = confirm(
                `Confirmar venda?\n\n` +
                `Material: ${material}\n` +
                `Quantidade: ${quantidade} unidades\n` +
                `Valor unitário: R$ ${valor}\n` +
                `Total: ${total}`
            );
            
            if (!confirmacao) {
                e.preventDefault();
                return false;
            }
        });
    }

    // ==================== ALERTAS AUTOMÁTICOS ====================
    const alertas = document.querySelectorAll('.alert');
    if (alertas.length > 0) {
        alertas.forEach(function(alerta) {
            const btnFechar = document.createElement('button');
            btnFechar.innerHTML = '&times;';
            btnFechar.style.cssText = 'background:none;border:none;font-size:20px;cursor:pointer;margin-left:10px;';
            btnFechar.onclick = function() {
                alerta.style.opacity = '0';
                setTimeout(() => alerta.remove(), 300);
            };
            alerta.appendChild(btnFechar);
            
            setTimeout(function() {
                if (alerta.parentNode) {
                    alerta.style.opacity = '0';
                    setTimeout(() => alerta.remove(), 500);
                }
            }, 5000);
        });
    }

    console.log('Sistema de vendas inicializado com sucesso!');
});

// ==================== FUNÇÕES GLOBAIS ====================
function resetarFormulario() {
    const form = document.getElementById('transacao-form');
    if (form) {
        form.reset();
        const qtdDisp = document.getElementById('qtd-disp-valor');
        if (qtdDisp) qtdDisp.textContent = '0';
    }
}

// Prevenir envio duplo do formulário
let formEnviado = false;
document.addEventListener('submit', function(e) {
    if (e.target.id === 'transacao-form') {
        if (formEnviado) {
            e.preventDefault();
            return false;
        }
        formEnviado = true;
        setTimeout(() => formEnviado = false, 3000);
    }
});
    </script>
</body>
</html>