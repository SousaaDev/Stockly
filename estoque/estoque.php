<?php
include 'dashboard_data.php';

session_start();

if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit();
}

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

// Atualização de perfil
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['atualizar'])) {
    $nome = $_POST['nome'];
    $email = $_POST['email'];
    $data_nascimento = $_POST['data_nascimento'];
    $endereco = $_POST['endereco'];
    $usuario_id = $usuario['id'];

    if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] == UPLOAD_ERR_OK) {
        $foto_perfil = $_FILES['foto_perfil'];
        $extensao = pathinfo($foto_perfil['name'], PATHINFO_EXTENSION);
        $novo_nome = uniqid() . '.' . $extensao;
        $caminho = '../uploads/' . $novo_nome;

        if (move_uploaded_file($foto_perfil['tmp_name'], $caminho)) {
            $foto_perfil = $caminho;
        } else {
            $foto_perfil = $usuario['foto_perfil'] ?? null;
        }
    } else {
        $foto_perfil = $usuario['foto_perfil'] ?? null;
    }

    $stmt = $conn->prepare("UPDATE ga3_usuarios SET nome = ?, email = ?, data_nascimento = ?, endereco = ?, foto_perfil = ? WHERE id = ?");
    $stmt->bind_param("sssssi", $nome, $email, $data_nascimento, $endereco, $foto_perfil, $usuario_id);

    if ($stmt->execute()) {
        $_SESSION['usuario']['nome'] = $nome;
        $_SESSION['usuario']['email'] = $email;
        $_SESSION['usuario']['data_nascimento'] = $data_nascimento;
        $_SESSION['usuario']['endereco'] = $endereco;
        $_SESSION['usuario']['foto_perfil'] = $foto_perfil;
        $usuario['nome'] = $nome;
        $usuario['email'] = $email;
        $usuario['data_nascimento'] = $data_nascimento;
        $usuario['endereco'] = $endereco;
        $usuario['foto_perfil'] = $foto_perfil;

        registrar_atividade($conn, $usuario_id, 'Atualização de perfil');
    }

    $stmt->close();
}

// Remoção de foto de perfil
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['remover_foto'])) {
    $usuario_id = $usuario['id'];
    $stmt = $conn->prepare("UPDATE ga3_usuarios SET foto_perfil = NULL WHERE id = ?");
    $stmt->bind_param("i", $usuario_id);

    if ($stmt->execute()) {
        $_SESSION['usuario']['foto_perfil'] = '../uploads/basico.png';
        $usuario['foto_perfil'] = '../uploads/basico.png';

        registrar_atividade($conn, $usuario_id, 'Remoção de foto de perfil');
    }

    $stmt->close();
}

// Obter atividades do usuário
$atividades = [];
$stmt = $conn->prepare("SELECT atividade, data FROM ga3_atividades WHERE usuario_id = ? ORDER BY data DESC LIMIT 5");
$stmt->bind_param("i", $usuario['id']);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $atividades[] = $row;
}
$stmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stockly - Gerenciamento de Estoque</title>
    <link rel="stylesheet" href="../css/styleprincipal.css">
    <link rel="stylesheet" href="dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
  --accent-color: #2a9d8f;
  --text-light: #8d99ae;
  --bg-color: #f1f5f9;
  --border-color: #dee2e6;
  --box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  --border-radius: 8px;
  --border-radius-sm: 4px;
}

/* Layout principal - MANTENDO O ESTILO DO HISTÓRICO DE VENDAS */
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

/* Estilos de layout e componentes */
.main-content {
  max-width: 1200px;
  margin: 0 auto;
  padding: 1rem;
}

/* Search container */
.search-container {
  background-color: white;
  border-radius: var(--border-radius);
  padding: 1.5rem;
  box-shadow: var(--box-shadow);
  margin-bottom: 2rem;
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
  align-items: center;
}

.search-container select,
.search-container input,
.search-container button {
  padding: 0.75rem 1rem;
  border-radius: var(--border-radius);
  border: 1px solid var(--border-color);
  font-family: Arial, Helvetica, sans-serif;
  font-size: 0.9rem;
  transition: all 0.3s ease;
}

.search-container select:focus,
.search-container input:focus {
  border-color: var(--primary-color);
  outline: none;
  box-shadow: 0 0 0 2px rgba(42, 157, 143, 0.2);
}

.search-container button {
  background-color: var(--primary-color);
  color: white;
  border: none;
  cursor: pointer;
  font-weight: 500;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  transition: background-color 0.3s ease;
}

.search-container button:hover {
  background-color: var(--primary-dark);
}

/* Resultados */
#resultados {
  background-color: white;
  border-radius: var(--border-radius);
  padding: 1.5rem;
  box-shadow: var(--box-shadow);
}

#resultados h2 {
  font-size: 1.5rem;
  font-weight: 600;
  color: var(--text-color);
  margin-bottom: 1rem;
}

.total-estoque {
  font-size: 1.1rem;
  font-weight: 500;
  color: var(--primary-color);
  margin-bottom: 1.5rem;
  padding: 0.75rem;
  background-color: rgba(42, 157, 143, 0.1);
  border-radius: var(--border-radius);
  display: inline-block;
}

/* Tabela de estoque */
#table-container {
  overflow-x: auto;
  margin-bottom: 1rem;
}

.estoque-table {
  width: 100%;
  border-collapse: collapse;
  border-radius: var(--border-radius);
  overflow: hidden;
}

.estoque-table thead {
  background-color: var(--primary-color);
  color: white;
}

.estoque-table th {
  padding: 1rem;
  font-weight: 600;
  text-align: left;
  text-transform: uppercase;
  font-size: 0.85rem;
  letter-spacing: 0.5px;
}

.estoque-table tbody tr {
  border-bottom: 1px solid var(--border-color);
  transition: background-color 0.3s ease;
}

.estoque-table tbody tr:hover {
  background-color: rgba(42, 157, 143, 0.05);
}

.estoque-table tbody tr:last-child {
  border-bottom: none;
}

.estoque-table td {
  padding: 1rem;
  font-size: 0.9rem;
  vertical-align: middle;
}

/* Status e indicadores */
.quantidade-baixa {
  color: var(--danger-color);
  font-weight: 600;
  position: relative;
}

.quantidade-baixa::after {
  content: ' !';
  color: var(--danger-color);
  font-weight: bold;
}

.status-indicator {
  display: inline-block;
  width: 10px;
  height: 10px;
  border-radius: 50%;
  margin-right: 5px;
}

.status-normal {
  background-color: #4CAF50;
}

.status-warning {
  background-color: #FFC107;
}

.status-danger {
  background-color: #F44336;
}

/* Botões de ação */
.action-buttons {
  display: flex;
  gap: 0.5rem;
}

.action-btn {
  background-color: transparent;
  border: none;
  cursor: pointer;
  padding: 0.5rem;
  border-radius: 50%;
  transition: background-color 0.3s ease, transform 0.3s ease;
}

.edit-btn {
  color: var(--accent-color);
}

.view-btn {
  color: var(--success-color);
}

.delete-btn {
  background-color: transparent;
  border: none;
  cursor: pointer;
  font-size: 1rem;
  color: var(--danger-color);
  transition: transform 0.3s ease, color 0.3s ease;
  padding: 0.5rem;
  border-radius: 50%;
}

.action-btn:hover,
.delete-btn:hover {
  transform: scale(1.15);
  background-color: rgba(42, 157, 143, 0.1);
}

.delete-btn:hover {
  color: var(--danger-color);
  background-color: rgba(230, 57, 70, 0.1);
}

.trash-icon {
  font-style: normal;
}

/* Cards de estatísticas */
.stats-container {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1.5rem;
  margin-bottom: 2.5rem;
}

.stat-card {
  background: linear-gradient(145deg, #ffffff, #f5f7fa);
  border-radius: var(--border-radius);
  padding: 1.8rem;
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
  border-left: 4px solid var(--primary-color);
  display: flex;
  flex-direction: column;
  gap: 0.8rem;
  transition: transform 0.3s ease, box-shadow 0.3s ease;
  position: relative;
  overflow: hidden;
}

.stat-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}

.stat-card::after {
  content: '';
  position: absolute;
  top: -50%;
  right: -50%;
  width: 100%;
  height: 100%;
  background: radial-gradient(circle, rgba(255,255,255,0.8) 0%, rgba(255,255,255,0) 70%);
  opacity: 0.6;
  z-index: 0;
}

.stat-title {
  font-size: 0.9rem;
  color: var(--text-light);
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  position: relative;
  z-index: 1;
}

.stat-value {
  font-size: 2rem;
  font-weight: 700;
  color: var(--text-color);
  position: relative;
  z-index: 1;
}

.stat-change {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  font-size: 0.85rem;
  font-weight: 600;
  position: relative;
  z-index: 1;
  padding: 0.3rem 0.6rem;
  border-radius: 20px;
  width: fit-content;
}

.stat-change.positive {
  color: #10B981;
  background-color: rgba(16, 185, 129, 0.1);
}

.stat-change.negative {
  color: #EF4444;
  background-color: rgba(239, 68, 68, 0.1);
}

.stat-icon {
  position: absolute;
  top: 1.2rem;
  right: 1.2rem;
  font-size: 2rem;
  opacity: 0.2;
  color: var(--primary-color);
  z-index: 1;
}

/* Mensagem de sem resultados */
.no-results {
  padding: 2rem;
  text-align: center;
  color: var(--text-light);
  font-weight: 500;
  font-size: 1.1rem;
  background-color: var(--bg-secondary);
  border-radius: var(--border-radius);
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1rem;
}

.no-results i {
  font-size: 3rem;
  color: var(--text-light);
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

.off-screen-menu.active {
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
  color: var(--primary-color);
}

.off-screen-menu ul li ul {
  padding: 0;
}

.off-screen-menu ul li ul li a {
  padding-left: 3rem;
  font-size: 0.9rem;
}

/* Menu hambúrguer */
.ham-menu {
  display: none;
  cursor: pointer;
  width: 30px;
  height: 24px;
  position: relative;
  z-index: 1000;
  margin: 10px;
}

.ham-menu span {
  display: block;
  position: absolute;
  height: 3px;
  width: 100%;
  background-color: var(--text-color);
  border-radius: 3px;
  opacity: 1;
  left: 0;
  transform: rotate(0deg);
  transition: .25s ease-in-out;
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

/* Formulários */
.form-group {
  margin-bottom: 1.25rem;
}

.form-group label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 500;
  color: var(--text-color);
  font-size: 0.9rem;
}

.form-control {
  width: 100%;
  padding: 0.75rem;
  border-radius: var(--border-radius);
  border: 1px solid var(--border-color);
  font-family: Arial, Helvetica, sans-serif;
  font-size: 0.9rem;
  transition: border-color 0.3s ease, box-shadow 0.3s ease;
}

.form-control:focus {
  border-color: var(--primary-color);
  outline: none;
  box-shadow: 0 0 0 2px rgba(42, 157, 143, 0.2);
}

.form-control.error {
  border-color: var(--danger-color);
}

.input-group {
  display: flex;
  align-items: center;
}

.input-prefix {
  padding: 0.75rem;
  background-color: var(--bg-secondary);
  border: 1px solid var(--border-color);
  border-radius: var(--border-radius) 0 0 var(--border-radius);
  border-right: none;
  color: var(--text-light);
}

.input-group .form-control {
  border-radius: 0 var(--border-radius) var(--border-radius) 0;
}

.validation-message {
  color: var(--danger-color);
  font-size: 0.8rem;
  margin-top: 0.25rem;
  display: none;
}

.validation-message.show {
  display: block;
}

/* Responsividade */
@media (max-width: 992px) {
  .site-nav {
    display: none;
  }
  
  .ham-menu {
    display: flex;
  }
  
  .sub-navbar ul {
    justify-content: flex-start;
  }
  
  .search-container {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 768px) {
  .stats-container {
    grid-template-columns: 1fr;
  }
  
  .estoque-table th, 
  .estoque-table td {
    padding: 0.75rem 0.5rem;
    font-size: 0.85rem;
  }
  
  .sub-navbar {
    display: none;
  }
  
  #resultados h2 {
    font-size: 1.25rem;
  }
  
  .content-wrapper {
    margin: 10px;
    padding: 15px;
  }
  
  h1 {
    font-size: 24px;
  }
}

@media (max-width: 576px) {
  .container {
    padding: 0.5rem;
  }
  
  .site-header .container {
    padding: 0.5rem;
  }
  
  .search-container, 
  #resultados {
    padding: 1rem;
  }
  
  .estoque-table th, 
  .estoque-table td {
    padding: 0.5rem 0.25rem;
    font-size: 0.8rem;
  }
  
  .total-estoque {
    padding: 0.5rem;
    font-size: 0.9rem;
  }
  
  .estoque-table th:nth-child(3),
  .estoque-table td:nth-child(3),
  .estoque-table th:nth-child(6),
  .estoque-table td:nth-child(6) {
    display: none;
  }
  
  .content-wrapper {
    margin: 5px;
    padding: 10px;
  }
  
  h1 {
    font-size: 20px;
    margin-bottom: 15px;
  }
}

.card:hover{
    transform: translateY(-5px);
    box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
}

.card{
    transition: all 0.3s ease;
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

<body>
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
        <li><a href="vendas.php" class="nav-link" data-page="computar_saida"><i class="fas fa-shopping-cart"></i> Marcar venda</a></li>
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
<div class="content-wrapper">

        <h1>Gerenciamento de Estoque</h1>
        
        <div class=" stats-container">
    <div class="stat-card">
        <i class="fas fa-box stat-icon"></i>
        <div class="stat-title">Total de Itens</div>
        <div class="stat-value" id="total-itens">0</div>
        <div class="stat-change positive" id="variacao-itens">
            <i class="fas fa-arrow-up"></i> 0%
        </div>
    </div>
    <div class="card stat-card">
        <i class="fas fa-dollar-sign stat-icon"></i>
        <div class="stat-title">Valor em Estoque</div>
        <div class="stat-value" id="valor-estoque">R$ 0,00</div>
        <div class="stat-change positive" id="variacao-valor">
            <i class="fas fa-arrow-up"></i> 0%
        </div>
    </div>
    <div class="card stat-card">
        <i class="fas fa-exclamation-triangle stat-icon"></i>
        <div class="stat-title">Itens com Estoque Baixo</div>
        <div class="stat-value" id="estoque-baixo">0</div>
        <div class="stat-change" id="alerta-estoque">
            <i class="fas fa-exclamation-triangle"></i> Monitorando
        </div>
    </div>
</div>
        
        <div class="card search-container">
            <select id="categoria-filter" aria-label="Filtrar por categoria">
                <option value="">Todas as categorias</option>
                <!-- Opções serão preenchidas pelo JavaScript -->
            </select>
            <select id="filtro-ordenacao" aria-label="Ordenar por">
                <option value="">Filtros</option>
                <optgroup label="Ordem Alfabética">
                    <option value="nome_asc">A-Z</option>
                    <option value="nome_desc">Z-A</option>
                </optgroup>
                <optgroup label="Preço">
                    <option value="preco_asc">Menor preço</option>
                    <option value="preco_desc">Maior preço</option>
                </optgroup>
                <optgroup label="Quantidade">
                    <option value="qtd_asc">Menor quantidade</option>
                    <option value="qtd_desc">Maior quantidade</option>
                </optgroup>
                <optgroup label="Código">
                    <option value="codigo_asc">Código (A-Z)</option>
                    <option value="codigo_desc">Código (Z-A)</option>
                </optgroup>
            </select>
            <input type="text" id="search-input" placeholder="Pesquisar por nome ou código..." aria-label="Pesquisar material">
            <button id="search-button"><i class="fas fa-search"></i> Buscar</button>
        </div>
        
        <div class="card " id="resultados">
            <h2>Materiais em Estoque</h2>
            <p id="total-estoque" class="total-estoque">Valor total em estoque: R$ 0,00</p>
            <div  id="table-container">
                <table class="estoque-table" id="estoque-table">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Material</th>
                            <th>Categoria</th>
                            <th>Quantidade</th>
                            <th>Valor Unitário</th>
                            <th>Valor Total</th>
                            <th>Valor de Venda</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody id="estoque-body">
                        <!-- Será preenchido pelo JavaScript -->
                    </tbody>
                </table>
                <div id="no-results" class="no-results" style="display: none;">
                    <i class="fas fa-search"></i>
                    <p>Nenhum material encontrado com esse nome ou código.</p>
                </div>
            </div>
        </div>
    </main>
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
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('search-input');
            const searchButton = document.getElementById('search-button');
            const categoriaFilter = document.getElementById('categoria-filter');
            const filtroOrdenacao = document.getElementById('filtro-ordenacao');
            const estoqueBody = document.getElementById('estoque-body');
            const totalEstoqueElement = document.getElementById('total-estoque');
            const noResultsElement = document.getElementById('no-results');
            const dropdownButton = document.getElementById('dropdownButton');
            const dropdownContent = document.getElementById('dropdownContent');
            const hamburgerMenu = document.getElementById('hamburgerMenu');
            const offScreenMenu = document.getElementById('offScreenMenu');
            
            let allMaterials = [];
            
            // Dropdown menu functionality
            dropdownButton.addEventListener('click', function() {
                dropdownContent.classList.toggle('show');
            });
            
            // Close dropdown when clicking outside
            window.addEventListener('click', function(event) {
                if (!event.target.matches('.dropbtn') && !event.target.matches('.perfil-foto')) {
                    if (dropdownContent.classList.contains('show')) {
                        dropdownContent.classList.remove('show');
                    }
                }
            });
            
            // Hamburger menu functionality
            hamburgerMenu.addEventListener('click', function() {
                hamburgerMenu.classList.toggle('active');
                offScreenMenu.classList.toggle('active');
            });
            
            // Close off-screen menu when clicking outside
            document.addEventListener('click', function(event) {
                if (offScreenMenu.classList.contains('active') && 
                    !offScreenMenu.contains(event.target) && 
                    !hamburgerMenu.contains(event.target)) {
                    hamburgerMenu.classList.remove('active');
                    offScreenMenu.classList.remove('active');
                }
            });
            
            // Set active nav link
            document.querySelectorAll('.nav-link').forEach(link => {
                if (link.getAttribute('data-page') === 'estoque') {
                    link.classList.add('active');
                }
            });
            
            // Função para carregar categorias
            function loadCategorias() {
                fetch('fetch_categorias.php')
                    .then(response => response.json())
                    .then(data => {
                        categoriaFilter.innerHTML = '<option value="">Todas as categorias</option>';
                        data.forEach(categoria => {
                            const option = document.createElement('option');
                            option.value = categoria.id;
                            option.textContent = categoria.nome;
                            categoriaFilter.appendChild(option);
                        });
                    })
                    .catch(error => {
                        console.error('Erro ao carregar categorias:', error);
                    });
            }
            
            // Função para carregar materiais com filtros
            function loadMateriais() {
                const searchTerm = searchInput.value.trim();
                const categoriaId = categoriaFilter.value;
                const ordenacao = filtroOrdenacao.value;
                
                let url = 'fetch_materiais.php';
                let params = [];
                
                if (searchTerm) {
                    params.push(`search=${encodeURIComponent(searchTerm)}`);
                }
                
                if (categoriaId) {
                    params.push(`categoria_id=${encodeURIComponent(categoriaId)}`);
                }
                
                if (ordenacao) {
                    params.push(`ordenacao=${encodeURIComponent(ordenacao)}`);
                }
                
                if (params.length > 0) {
                    url += '?' + params.join('&');
                }
                
                fetch(url)
                    .then(response => response.json())
                    .then(data => {
                        allMaterials = data;
                        displayMaterials(allMaterials);
                        updateStatistics(allMaterials);
                    })
                    .catch(error => {
                        console.error('Erro ao carregar materiais:', error);
                        noResultsElement.style.display = 'block';
                        document.getElementById('estoque-table').style.display = 'none';
                    });
            }
            
            // Função para exibir materiais na tabela
            function displayMaterials(materials) {
                estoqueBody.innerHTML = '';
                let valorTotalEstoque = 0;
                
                if (materials.length === 0) {
                    noResultsElement.style.display = 'block';
                    document.getElementById('estoque-table').style.display = 'none';
                } else {
                    noResultsElement.style.display = 'none';
                    document.getElementById('estoque-table').style.display = 'table';
                    
                    materials.forEach(material => {
                        const valorTotal = material.quantidade * material.valor_unitario_estoque;
                        valorTotalEstoque += valorTotal;
                        
                        const tr = document.createElement('tr');
                        
                        // Verificar se a quantidade está baixa (menos de 5)
                        const quantidadeClass = material.quantidade < 5 ? 'quantidade-baixa' : '';
                        
                        tr.innerHTML = `
                            <td>${material.codigo_identificacao || 'N/A'}</td>
                            <td>${material.descricao}</td>
                            <td>${material.categoria_nome ? material.categoria_nome : 'Sem categoria'}</td>
                            <td class="${quantidadeClass}">${material.quantidade}</td>
                            <td>R$ ${parseFloat(material.valor_unitario_estoque).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.')}</td>
<td>R$ ${valorTotal.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.')}</td>
<td>R$ ${parseFloat(material.valor_unitario_venda_estimado).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.')}</td>
                            <td class="action-buttons">
                                <button class="action-btn edit-btn" data-id="${material.id}" title="Editar material">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="action-btn view-btn" data-id="${material.id}" title="Ver detalhes">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="action-btn delete-btn" data-id="${material.id}" title="Excluir material">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </td>
                        `;
                        
                        estoqueBody.appendChild(tr);
                    });
                    
                    // Adicionar event listeners para os botões de ação
                    document.querySelectorAll('.edit-btn').forEach(button => {
                        button.addEventListener('click', function() {
                            const materialId = this.getAttribute('data-id');
                            editarMaterial(materialId);
                        });
                    });
                    
                    document.querySelectorAll('.view-btn').forEach(button => {
                        button.addEventListener('click', function() {
                            const materialId = this.getAttribute('data-id');
                            verDetalhesMaterial(materialId);
                        });
                    });
                    
                    document.querySelectorAll('.delete-btn').forEach(button => {
                        button.addEventListener('click', function() {
                            const materialId = this.getAttribute('data-id');
                            const material = allMaterials.find(m => m.id == materialId);
                            const materialNome = material ? material.descricao : 'este material';
                            const materialCodigo = material ? material.codigo_identificacao : '';
                            
                            const confirmMessage = `Tem certeza que deseja excluir o material "${materialNome}" ${materialCodigo ? `(Código: ${materialCodigo})` : ''}?\n\nEsta ação não poderá ser desfeita.`;
                            
                            if (confirm(confirmMessage)) {
                                deleteMaterial(materialId);
                            }
                        });
                    });
                }
                
                totalEstoqueElement.textContent = `Valor total em estoque: R$ ${valorTotalEstoque.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.')}`;
            }
            
            // Função para deletar material
            function deleteMaterial(materialId) {
    fetch('delete_material.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `id=${materialId}`
    })
                .then(response => response.text())
                .then(data => {
                    showNotification(data, 'success');
                    loadMateriais(); // Recarregar a lista após a exclusão
                })
                .catch(error => {
                    console.error('Erro:', error);
                    showNotification('Ocorreu um erro ao tentar excluir o material.', 'error');
                });
            }
            
            // Função para editar material
            // Função para editar material
function editarMaterial(materialId) {
    const material = allMaterials.find(m => m.id == materialId);
    if (!material) return;
    
    const modalHtml = `
        <div class="modal-backdrop"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h3>Editar Material</h3>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="edit-material-form">
                    <input type="hidden" name="material_id" value="${material.id}">
                    
                    <div class="form-group">
                        <label for="edit-codigo">Código:</label>
                        <input type="text" id="edit-codigo" name="codigo" value="${material.codigo_identificacao || ''}" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label for="edit-descricao">Nome/Descrição:</label>
                        <input type="text" id="edit-descricao" name="descricao" value="${material.descricao}" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit-categoria">Categoria:</label>
                        <select id="edit-categoria" name="categoria_id" class="form-control">
                            <option value="">Selecione uma categoria</option>
                            <!-- Será preenchido via JavaScript -->
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit-quantidade">Quantidade:</label>
                        <input type="number" id="edit-quantidade" name="quantidade" value="${material.quantidade}" min="0" step="1" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit-valor-unitario">Valor Unitário:</label>
                        <div class="input-group">
                            <span class="input-prefix">R$</span>
                            <input type="number" id="edit-valor-unitario" name="valor_unitario" value="${material.valor_unitario_estoque}" min="0" step="0.01" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit-valor-venda">Valor de Venda:</label>
                        <div class="input-group">
                            <span class="input-prefix">R$</span>
                            <input type="number" id="edit-valor-venda" name="valor_venda" value="${material.valor_unitario_venda_estimado}" min="0" step="0.01" class="form-control" required>
                        </div>
                    </div>
                    
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary save-material-btn">Salvar</button>
                <button class="btn btn-secondary close-modal-btn">Cancelar</button>
            </div>
        </div>
    `;
    
    const modalContainer = document.createElement('div');
    modalContainer.className = 'modal';
    modalContainer.innerHTML = modalHtml;
    document.body.appendChild(modalContainer);
    
    // Carregar categorias no select
    loadCategoriasForSelect(document.getElementById('edit-categoria'), material.categoria_id);
    
    setTimeout(() => {
        modalContainer.classList.add('show');
    }, 10);
    
    // Event listeners para o modal
    modalContainer.querySelector('.close-modal').addEventListener('click', () => {
        closeModal(modalContainer);
    });
    
    modalContainer.querySelector('.close-modal-btn').addEventListener('click', () => {
        closeModal(modalContainer);
    });
    
    modalContainer.querySelector('.save-material-btn').addEventListener('click', () => {
        saveMaterialChanges(modalContainer);
    });
    
    modalContainer.querySelector('.modal-backdrop').addEventListener('click', () => {
        closeModal(modalContainer);
    });
}

// Função para carregar categorias no select de edição
function loadCategoriasForSelect(selectElement, selectedCategoryId) {
    fetch('fetch_categorias.php')
        .then(response => response.json())
        .then(data => {
            selectElement.innerHTML = '<option value="">Selecione uma categoria</option>';
            data.forEach(categoria => {
                const option = document.createElement('option');
                option.value = categoria.id;
                option.textContent = categoria.nome;
                if (categoria.id == selectedCategoryId) {
                    option.selected = true;
                }
                selectElement.appendChild(option);
            });
        })
        .catch(error => {
            console.error('Erro ao carregar categorias:', error);
        });
}


            
            // Função para ver detalhes do material
            function verDetalhesMaterial(materialId) {
                const material = allMaterials.find(m => m.id == materialId);
                if (!material) return;
                
                const modalHtml = `
                    <div class="modal-backdrop"></div>
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3>Detalhes do Material</h3>
                            <button class="close-modal">&times;</button>
                        </div>
                        <div class="modal-body">
                            <div class="detail-row">
                                <span class="detail-label">Código:</span>
                                <span class="detail-value">${material.codigo_identificacao || 'N/A'}</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Nome:</span>
                                <span class="detail-value">${material.descricao}</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Categoria:</span>
                                <span class="detail-value">${material.categoria_nome || 'Sem categoria'}</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Quantidade:</span>
                                <span class="detail-value ${material.quantidade < 5 ? 'quantidade-baixa' : ''}">${material.quantidade}</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Valor Unitário:</span>
                                <span class="detail-value">R$ ${parseFloat(material.valor_unitario_estoque).toFixed(2).replace('.', ',')}</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Valor Total:</span>
                                <span class="detail-value">R$ ${(material.quantidade * material.valor_unitario_estoque).toFixed(2).replace('.', ',')}</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Valor de Venda:</span>
                                <span class="detail-value">R$ ${parseFloat(material.valor_unitario_venda_estimado).toFixed(2).replace('.', ',')}</span>
                            </div>
                            ${material.data_aquisicao ? `
                            <div class="detail-row">
                                <span class="detail-label">Data de Aquisição:</span>
                                <span class="detail-value">${new Date(material.data_aquisicao).toLocaleDateString('pt-BR')}</span>
                            </div>` : ''}
                            ${material.fornecedor ? `
                            <div class="detail-row">
                                <span class="detail-label">Fornecedor:</span>
                                <span class="detail-value">${material.fornecedor}</span>
                            </div>` : ''}
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-primary edit-material-btn" data-id="${material.id}">Editar</button>
                            <button class="btn btn-secondary close-modal-btn">Fechar</button>
                        </div>
                    </div>
                `;
                
                const modalContainer = document.createElement('div');
                modalContainer.className = 'modal';
                modalContainer.innerHTML = modalHtml;
                document.body.appendChild(modalContainer);
                
                setTimeout(() => {
                    modalContainer.classList.add('show');
                }, 10);
                
                // Event listeners para o modal
                modalContainer.querySelector('.close-modal').addEventListener('click', () => {
                    closeModal(modalContainer);
                });
                
                modalContainer.querySelector('.close-modal-btn').addEventListener('click', () => {
                    closeModal(modalContainer);
                });
                
                modalContainer.querySelector('.edit-material-btn').addEventListener('click', () => {
                    editarMaterial(material.id);
                });
                
                modalContainer.querySelector('.modal-backdrop').addEventListener('click', () => {
                    closeModal(modalContainer);
                });
            }
            
            // Função para fechar o modal
            function closeModal(modalContainer) {
                modalContainer.classList.remove('show');
                setTimeout(() => {
                    document.body.removeChild(modalContainer);
                }, 300);
            }
            
            // Função para atualizar estatísticas
            function updateStatistics(materials) {
                const totalItens = materials.length;
                const valorEstoque = materials.reduce((total, material) => total + (material.quantidade * material.valor_unitario_estoque), 0);
                const estoqueBaixo = materials.filter(material => material.quantidade < 5).length;
                
                document.getElementById('total-itens').textContent = totalItens.toLocaleString('pt-BR');
document.getElementById('valor-estoque').textContent = `R$ ${valorEstoque.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.')}`;
document.getElementById('estoque-baixo').textContent = estoqueBaixo.toLocaleString('pt-BR');
                
                // Simulação de variação (em uma aplicação real, isso viria do banco de dados)
                const variacaoItens = Math.floor(Math.random() * 10);
                const variacaoValor = Math.floor(Math.random() * 15);
                
                const variacaoItensElement = document.getElementById('variacao-itens');
                variacaoItensElement.innerHTML = `<i class="fas fa-arrow-up"></i> ${variacaoItens}%`;
                variacaoItensElement.className = 'stat-change positive';
                
                const variacaoValorElement = document.getElementById('variacao-valor');
                variacaoValorElement.innerHTML = `<i class="fas fa-arrow-up"></i> ${variacaoValor}%`;
                variacaoValorElement.className = 'stat-change positive';
                
                const alertaEstoqueElement = document.getElementById('alerta-estoque');
                if (estoqueBaixo > 0) {
                    alertaEstoqueElement.innerHTML = `<i class="fas fa-exclamation-triangle"></i> Atenção necessária`;
                    alertaEstoqueElement.className = 'stat-change negative';
                } else {
                    alertaEstoqueElement.innerHTML = `<i class="fas fa-check-circle"></i> Todos os níveis OK`;
                    alertaEstoqueElement.className = 'stat-change positive';
                }
            }
            
            // Função para mostrar notificações
            function showNotification(message, type) {
                const notification = document.createElement('div');
                notification.className = `notification ${type}`;
                notification.innerHTML = `
                    <div class="notification-content">
                        <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
                        <span>${message}</span>
                    </div>
                    <button class="close-notification">&times;</button>
                `;
                
                document.body.appendChild(notification);
                
                setTimeout(() => {
                    notification.classList.add('show');
                }, 10);
                
                notification.querySelector('.close-notification').addEventListener('click', () => {
                    notification.classList.remove('show');
                    setTimeout(() => {
                        document.body.removeChild(notification);
                    }, 300);
                });
                
                setTimeout(() => {
                    notification.classList.remove('show');
                    setTimeout(() => {
                        if (document.body.contains(notification)) {
                            document.body.removeChild(notification);
                        }
                    }, 300);
                }, 5000);
            }
            
            // Event listeners
            searchButton.addEventListener('click', loadMateriais);
            
            searchInput.addEventListener('keyup', function(event) {
                if (event.key === 'Enter') {
                    loadMateriais();
                }
            });
            
            categoriaFilter.addEventListener('change', loadMateriais);
            filtroOrdenacao.addEventListener('change', loadMateriais);
            
            // Adicionar estilos para o modal e notificações
            const styleSheet = document.createElement("style");
            styleSheet.innerText = `
                .modal {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    z-index: 1050;
                    opacity: 0;
                    visibility: hidden;
                    transition: opacity 0.3s ease, visibility 0.3s ease;
                }
                
                .modal.show {
                    opacity: 1;
                    visibility: visible;
                }
                
                .modal-backdrop {
                    position: absolute;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background-color: rgba(0, 0, 0, 0.5);
                }
                
                .modal-content {
                    position: relative;
                    background-color: white;
                    width: 90%;
                    max-width: 500px;
                    border-radius: var(--border-radius);
                    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
                    overflow: hidden;
                    transform: translateY(20px);
                    transition: transform 0.3s ease;
                    z-index: 1051;
                }
                
                .modal.show .modal-content {
                    transform: translateY(0);
                }
                
                .modal-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    padding: 1rem 1.5rem;
                    border-bottom: 1px solid var(--border-color);
                }
                
                .modal-header h3 {
                    margin: 0;
                    color: var(--text-color);
                    font-weight: 600;
                }
                
                .close-modal {
                    background: none;
                    border: none;
                    font-size: 1.5rem;
                    line-height: 1;
                    color: var(--text-light);
                    cursor: pointer;
                    transition: color 0.3s ease;
                }
                
                .close-modal:hover {
                    color: var(--danger-color);
                }
                
                .modal-body {
                    padding: 1.5rem;
                    max-height: 70vh;
                    overflow-y: auto;
                }
                
                .detail-row {
                    display: flex;
                    margin-bottom: 1rem;
                }
                
                .detail-label {
                    width: 40%;
                    font-weight: 500;
                    color: var(--text-light);
                }
                
                .detail-value {
                    width: 60%;
                    font-weight: 400;
                    color: var(--text-color);
                }
                
                .modal-footer {
                    padding: 1rem 1.5rem;
                    border-top: 1px solid var(--border-color);
                    display: flex;
                    justify-content: flex-end;
                    gap: 1rem;
                }
                
                .btn {
                    padding: 0.5rem 1.25rem;
                    border-radius: var(--border-radius);
                    font-family: 'Poppins', sans-serif;
                    font-weight: 500;
                    font-size: 0.9rem;
                    border: none;
                    cursor: pointer;
                    transition: background-color 0.3s ease, box-shadow 0.3s ease;
                }
                
                .btn-primary {
                    background-color: var(--primary-color);
                    color: white;
                }
                
                .btn-primary:hover {
                    background-color: var(--secondary-color);
                    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
                }
                
                .btn-secondary {
                    background-color: var(--bg-secondary);
                    color: var(--text-color);
                }
                
                .btn-secondary:hover {
                    background-color: var(--border-color);
                    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
                }
                
                .notification {
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    max-width: 350px;
                    padding: 1rem;
                    border-radius: var(--border-radius);
                    background-color: white;
                    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
                    transform: translateX(120%);
                    transition: transform 0.3s ease;
                    z-index: 1100;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }
                
                .notification.show {
                    transform: translateX(0);
                }
                
                .notification.success {
                    border-left: 4px solid #4CAF50;
                }
                
                .notification.error {
                    border-left: 4px solid #F44336;
                }
                
                .notification-content {
                    display: flex;
                    align-items: center;
                    gap: 0.75rem;
                }
                
                .notification-content i {
                    font-size: 1.25rem;
                }
                
                .notification.success i {
                    color: #4CAF50;
                }
                
                .notification.error i {
                    color: #F44336;
                }
                
                .close-notification {
                    background: none;
                    border: none;
                    font-size: 1.25rem;
                    color: var(--text-light);
                    cursor: pointer;
                    transition: color 0.3s ease;
                }
                
                .close-notification:hover {
                    color: var(--danger-color);
                }
                
                @media (max-width: 768px) {
                    .notification {
                        left: 20px;
                        right: 20px;
                        max-width: calc(100% - 40px);
                    }
                }
            `;
            document.head.appendChild(styleSheet);

            // Função para editar material
function editarMaterial(materialId) {
    const material = allMaterials.find(m => m.id == materialId);
    if (!material) return;
    
    const modalHtml = `
        <div class="modal-backdrop"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h3>Editar Material</h3>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="edit-material-form">
                    <input type="hidden" name="material_id" value="${material.id}">
                    
                    <div class="form-group">
                        <label for="edit-codigo">Código:</label>
                        <input type="text" id="edit-codigo" name="codigo" value="${material.codigo_identificacao || ''}" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label for="edit-descricao">Nome/Descrição:</label>
                        <input type="text" id="edit-descricao" name="descricao" value="${material.descricao}" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit-categoria">Categoria:</label>
                        <select id="edit-categoria" name="categoria_id" class="form-control">
                            <option value="">Selecione uma categoria</option>
                            <!-- Será preenchido via JavaScript -->
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit-quantidade">Quantidade:</label>
                        <input type="number" id="edit-quantidade" name="quantidade" value="${material.quantidade}" min="0" step="1" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit-valor-unitario">Valor Unitário:</label>
                        <div class="input-group">
                            <span class="input-prefix">R$</span>
                            <input type="number" id="edit-valor-unitario" name="valor_unitario" value="${material.valor_unitario_estoque}" min="0" step="0.01" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit-valor-venda">Valor de Venda:</label>
                        <div class="input-group">
                            <span class="input-prefix">R$</span>
                            <input type="number" id="edit-valor-venda" name="valor_venda" value="${material.valor_unitario_venda_estimado}" min="0" step="0.01" class="form-control" required>
                        </div>
                    </div>
                    

                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary save-material-btn">Salvar</button>
                <button class="btn btn-secondary close-modal-btn">Cancelar</button>
            </div>
        </div>
    `;
    
    const modalContainer = document.createElement('div');
    modalContainer.className = 'modal';
    modalContainer.innerHTML = modalHtml;
    document.body.appendChild(modalContainer);
    
    // Carregar categorias no select
    loadCategoriasForSelect(document.getElementById('edit-categoria'), material.categoria_id);
    
    setTimeout(() => {
        modalContainer.classList.add('show');
    }, 10);
    
    // Event listeners para o modal
    modalContainer.querySelector('.close-modal').addEventListener('click', () => {
        closeModal(modalContainer);
    });
    
    modalContainer.querySelector('.close-modal-btn').addEventListener('click', () => {
        closeModal(modalContainer);
    });
    
    modalContainer.querySelector('.save-material-btn').addEventListener('click', () => {
        saveMaterialChanges(modalContainer);
    });
    
    modalContainer.querySelector('.modal-backdrop').addEventListener('click', () => {
        closeModal(modalContainer);
    });
}

// Função para carregar categorias no select de edição
function loadCategoriasForSelect(selectElement, selectedCategoryId) {
    fetch('fetch_categorias.php')
        .then(response => response.json())
        .then(data => {
            selectElement.innerHTML = '<option value="">Selecione uma categoria</option>';
            data.forEach(categoria => {
                const option = document.createElement('option');
                option.value = categoria.id;
                option.textContent = categoria.nome;
                if (categoria.id == selectedCategoryId) {
                    option.selected = true;
                }
                selectElement.appendChild(option);
            });
        })
        .catch(error => {
            console.error('Erro ao carregar categorias:', error);
        });
}

// Função para salvar as alterações do material
// Função para salvar as alterações do material - Versão corrigida
function saveMaterialChanges(modalContainer) {
    const form = modalContainer.querySelector('#edit-material-form');
    const formData = new FormData(form);
    
    // Get the material_id from the hidden input
    const materialId = form.querySelector('input[name="material_id"]').value;
    
    // Renomear os campos para os nomes esperados pelo backend
    const valorUnitario = formData.get('valor_unitario');
    const valorVenda = formData.get('valor_venda');
    
    // Remover campos originais
    formData.delete('valor_unitario');
    formData.delete('valor_venda');
    
    // Adicionar com os nomes corretos
    formData.append('valor_unitario_estoque', valorUnitario);
    formData.append('valor_unitario_venda_estimado', valorVenda);
    
    // Add the material ID with the correct parameter name expected by update_material.php
    formData.append('material', materialId);
    
    fetch('update_material.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())  // Expect JSON response
    .then(data => {
        if (data.status === 'success') {
            showNotification(data.message, 'success');
            closeModal(modalContainer);
            loadMateriais(); // Reload the list after updating
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        showNotification('Ocorreu um erro ao tentar atualizar o material.', 'error');
    });
}   
            
            // Carregar dados iniciais
            loadCategorias();
            loadMateriais();
            
            // Recarregar a cada 60 segundos para manter atualizado
            setInterval(loadMateriais, 60000);
        });
    </script>
</body>
</html>