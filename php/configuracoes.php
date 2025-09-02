<?php
include '../estoque/header.php'; // Incluindo o header


if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit();
}

$usuario = $_SESSION['usuario'];

// Verificar se o usuário é chefe ou gerente
if (!isset($usuario['cargo']) || !in_array($usuario['cargo'], ['chefe', 'gerente'])) {
    header('Location: ../estoque/dashboard.php');
    exit();
}

// Definir a foto de perfil padrão se não estiver definida
if (!isset($usuario['foto_perfil']) || empty($usuario['foto_perfil'])) {
    $usuario['foto_perfil'] = '../uploads/basico.png';
}

// Conectar ao banco de dados
$conn = new mysqli('localhost', 'root', '', 'ga3_stockly');

if ($conn->connect_error) {
    die("Conexão falhou: " . $conn->connect_error);
}

// Função para registrar atividades
function registrar_atividade($conn, $usuario_id, $atividade) {
    $stmt = $conn->prepare("INSERT INTO ga3_atividades (usuario_id, atividade) VALUES (?, ?)");
    $stmt->bind_param("is", $usuario_id, $atividade);
    $stmt->execute();
    $stmt->close();
}

// Função para verificar se email já existe
function email_existe($conn, $email, $id = null) {
    if ($id) {
        $stmt = $conn->prepare("SELECT id FROM ga3_usuarios WHERE email = ? AND id != ?");
        $stmt->bind_param("si", $email, $id);
    } else {
        $stmt = $conn->prepare("SELECT id FROM ga3_usuarios WHERE email = ?");
        $stmt->bind_param("s", $email);
    }
    $stmt->execute();
    $stmt->store_result();
    $count = $stmt->num_rows;
    $stmt->close();
    return $count > 0;
}

// Mensagens e erros
$mensagem_sucesso = '';
$erro_geral = '';

// Adicionar novo funcionário
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['adicionar_funcionario'])) {
    $nome = trim($_POST['nome']);
    $email = trim($_POST['email']);
    $senha = trim($_POST['senha']);
    $cargo = $_POST['cargo'];
    $data_nascimento = !empty($_POST['data_nascimento']) ? $_POST['data_nascimento'] : null;
    $endereco = trim($_POST['endereco']);
    
    $erros = [];
    
    // Validações
    if (empty($nome)) {
        $erros['nome'] = "Nome é obrigatório.";
    }
    
    if (empty($email)) {
        $erros['email'] = "E-mail é obrigatório.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erros['email'] = "E-mail inválido.";
    } elseif (email_existe($conn, $email)) {
        $erros['email'] = "Este e-mail já está em uso.";
    }
    
    if (empty($senha)) {
        $erros['senha'] = "Senha é obrigatória.";
    } elseif (strlen($senha) < 8) {
        $erros['senha'] = "A senha deve ter pelo menos 8 caracteres.";
    }
    
    if (empty($cargo)) {
        $erros['cargo'] = "Cargo é obrigatório.";
    }
    
    if (empty($erros)) {
        $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare("INSERT INTO ga3_usuarios (nome, email, senha, cargo, data_nascimento, endereco) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $nome, $email, $senha_hash, $cargo, $data_nascimento, $endereco);
        
        if ($stmt->execute()) {
            registrar_atividade($conn, $usuario['id'], "Adicionou novo funcionário: $nome");
            $mensagem_sucesso = "Funcionário adicionado com sucesso!";
            // Limpar campos após sucesso
            $nome = $email = $senha = $endereco = '';
            $cargo = 'funcionario';
            $data_nascimento = null;
        } else {
            $erro_geral = "Erro ao adicionar funcionário: " . $conn->error;
        }
        
        $stmt->close();
    }
}

// Editar funcionário
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['editar_funcionario'])) {
    $id = $_POST['id'];
    $nome = trim($_POST['nome']);
    $email = trim($_POST['email']);
    $cargo = $_POST['cargo'];
    $data_nascimento = !empty($_POST['data_nascimento']) ? $_POST['data_nascimento'] : null;
    $endereco = trim($_POST['endereco']);
    $nova_senha = trim($_POST['nova_senha']);
    
    $erros = [];
    
    // Validações
    if (empty($nome)) {
        $erros['nome'] = "Nome é obrigatório.";
    }
    
    if (empty($email)) {
        $erros['email'] = "E-mail é obrigatório.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erros['email'] = "E-mail inválido.";
    } elseif (email_existe($conn, $email, $id)) {
        $erros['email'] = "Este e-mail já está em uso.";
    }
    
    if (!empty($nova_senha) && strlen($nova_senha) < 8) {
        $erros['nova_senha'] = "A nova senha deve ter pelo menos 8 caracteres.";
    }
    
    if (empty($cargo)) {
        $erros['cargo'] = "Cargo é obrigatório.";
    }
    
    if (empty($erros)) {
        if (!empty($nova_senha)) {
            $senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE ga3_usuarios SET nome = ?, email = ?, senha = ?, cargo = ?, data_nascimento = ?, endereco = ? WHERE id = ?");
            $stmt->bind_param("ssssssi", $nome, $email, $senha_hash, $cargo, $data_nascimento, $endereco, $id);
        } else {
            $stmt = $conn->prepare("UPDATE ga3_usuarios SET nome = ?, email = ?, cargo = ?, data_nascimento = ?, endereco = ? WHERE id = ?");
            $stmt->bind_param("sssssi", $nome, $email, $cargo, $data_nascimento, $endereco, $id);
        }
        
        if ($stmt->execute()) {
            registrar_atividade($conn, $usuario['id'], "Editou informações do funcionário ID: $id");
            $mensagem_sucesso = "Funcionário atualizado com sucesso!";
        } else {
            $erro_geral = "Erro ao atualizar funcionário: " . $conn->error;
        }
        
        $stmt->close();
    }
}

// Excluir funcionário
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['excluir_funcionario'])) {
    $id = $_POST['id'];
    
    // Verificar se não é o próprio usuário
    if ($id == $usuario['id']) {
        $erro_geral = "Você não pode excluir seu próprio usuário.";
    } else {
        // Verificar atividades relacionadas
        $stmt = $conn->prepare("SELECT COUNT(*) FROM ga3_atividades WHERE usuario_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();
        
        // Verificar transações relacionadas
        $stmt = $conn->prepare("SELECT COUNT(*) FROM ga3_transacoes WHERE usuario_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->bind_result($trans_count);
        $stmt->fetch();
        $stmt->close();
        
        if ($count > 0 || $trans_count > 0) {
            $erro_geral = "Este funcionário possui registros relacionados. Não é possível excluí-lo.";
        } else {
            $stmt = $conn->prepare("DELETE FROM ga3_usuarios WHERE id = ?");
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                registrar_atividade($conn, $usuario['id'], "Excluiu funcionário ID: $id");
                $mensagem_sucesso = "Funcionário excluído com sucesso!";
            } else {
                $erro_geral = "Erro ao excluir funcionário: " . $conn->error;
            }
            
            $stmt->close();
        }
    }
}

// Buscar funcionários
$funcionarios = [];
$sql = "SELECT id, nome, email, cargo, data_nascimento, endereco, created_at, foto_perfil, 
        (SELECT COUNT(*) FROM ga3_atividades WHERE usuario_id = ga3_usuarios.id) as total_atividades,
        (SELECT COUNT(*) FROM ga3_transacoes WHERE usuario_id = ga3_usuarios.id) as total_transacoes,
        (SELECT MAX(data) FROM ga3_atividades WHERE usuario_id = ga3_usuarios.id) as ultima_atividade
        FROM ga3_usuarios ORDER BY nome";

$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $funcionarios[] = $row;
    }
    $result->free();
}

// Listar todas as atividades de um funcionário específico
$atividades_funcionario = [];
if (isset($_GET['ver_atividades']) && !empty($_GET['ver_atividades'])) {
    $func_id = $_GET['ver_atividades'];
    
    // Buscar nome do funcionário
    $stmt = $conn->prepare("SELECT nome FROM ga3_usuarios WHERE id = ?");
    $stmt->bind_param("i", $func_id);
    $stmt->execute();
    $stmt->bind_result($nome_funcionario);
    $stmt->fetch();
    $stmt->close();
    
    // Buscar atividades
    $stmt = $conn->prepare("SELECT atividade, data FROM ga3_atividades WHERE usuario_id = ? ORDER BY data DESC");
    $stmt->bind_param("i", $func_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $atividades_funcionario[] = $row;
    }
    $stmt->close();
}

$conn->close();

// Formatação de data para exibição
function formatar_data($data) {
    if (empty($data)) return "Não informado";
    return date('d/m/Y', strtotime($data));
}

// Formatação de data e hora para exibição
function formatar_data_hora($data) {
    if (empty($data)) return "Nunca";
    return date('d/m/Y H:i:s', strtotime($data));
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administração de Funcionários - Stockly</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/styleprincipal.css">
    <link rel="stylesheet" href="../css/app.css">
    <style>
     ```css
/* Reset and Base Styles */
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

/* Variables */
:root {
  --primary-color: #5ea7a5;
  --secondary-color: #2a9d8f;
  --text-color: #333;
  --light-bg: #f5f8fa;
  --border-color: #ddd;
  --success-color: #2ecc71;
  --error-color: #e74c3c;
  --warning-color: #f39c12;
  --box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

/* Layout and Container */
.admin-container {
  max-width: 1200px;
  margin: 2rem auto;
  padding: 0 1rem;
}

/* Header Styles */
.admin-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2rem;
}

.admin-title {
  font-size: 1.8rem;
  color: var(--primary-color);
}

/* Tab Navigation */
.admin-tabs {
  display: flex;
  border-bottom: 1px solid var(--border-color);
  margin-bottom: 1.5rem;
  overflow-x: auto;
  white-space: nowrap;
  -webkit-overflow-scrolling: touch;
}

.admin-tab {
  padding: 0.8rem 1.5rem;
  cursor: pointer;
  font-weight: 500;
  color: var(--text-color);
  border-bottom: 3px solid transparent;
  transition: all 0.3s;
}

.admin-tab.active {
  color: var(--primary-color);
  border-bottom: 3px solid var(--primary-color);
}

.tab-content {
  display: none;
}

.tab-content.active {
  display: block;
}

/* Card Component */
.card {
  background: white;
  border-radius: 8px;
  box-shadow: var(--box-shadow);
  margin-bottom: 1.5rem;
  overflow: hidden;
}

.card-header {
  padding: 1rem 1.5rem;
  background: var(--light-bg);
  border-bottom: 1px solid var(--border-color);
  font-weight: 500;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.card-body {
  padding: 1.5rem;
}

/* Search Box */
.search-box {
  position: relative;
  margin-bottom: 1.5rem;
}

.search-box input {
  width: 100%;
  padding: 0.75rem 1rem 0.75rem 2.5rem;
  border: 1px solid var(--border-color);
  border-radius: 4px;
  font-size: 1rem;
}

.search-box i {
  position: absolute;
  left: 1rem;
  top: 50%;
  transform: translateY(-50%);
  color: #aaa;
}

/* Table Styles */
.funcionarios-table {
  width: 100%;
  border-collapse: collapse;
}

.funcionarios-table th,
.funcionarios-table td {
  padding: 0.75rem 1rem;
  text-align: left;
  border-bottom: 1px solid var(--border-color);
}

.funcionarios-table th {
  background: var(--light-bg);
  font-weight: 500;
}

.funcionarios-table tr:hover {
  background: rgba(94, 167, 165, 0.05);
}

.funcionarios-table td.actions {
  width: 120px;
}

/* Action Buttons Container */
.actions-container {
  display: flex;
  gap: 5px;
  justify-content: flex-start;
}

/* Button Styles */
.btn {
  display: inline-block;
  padding: 0.5rem 1rem;
  border-radius: 4px;
  background: var(--primary-color);
  color: white;
  text-decoration: none;
  border: none;
  cursor: pointer;
  font-size: 0.9rem;
  transition: background 0.3s;
}

.btn-small {
  padding: 0.3rem 0.6rem;
  font-size: 0.8rem;
}

.btn-action {
  width: 32px;
  height: 32px;
  padding: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 4px;
}

.btn-action i {
  font-size: 14px;
}

/* Button Colors */
.btn-primary {
  background: var(--primary-color);
}

.btn-primary:hover {
  background: var(--secondary-color);
}

.btn-secondary {
  background: #6c757d;
}

.btn-secondary:hover {
  background: #5a6268;
}

.btn-success {
  background: var(--success-color);
}

.btn-success:hover {
  background: #27ae60;
}

.btn-danger {
  background: var(--error-color);
}

.btn-danger:hover {
  background: #c0392b;
}

.btn-warning {
  background: var(--warning-color);
}

.btn-warning:hover {
  background: #e67e22;
}

/* Form Elements */
.form-group {
  margin-bottom: 1.5rem;
}

.form-group label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 500;
}

.form-control {
  width: 100%;
  padding: 0.75rem 1rem;
  border: 1px solid var(--border-color);
  border-radius: 4px;
  font-size: 1rem;
}

.form-error {
  color: var(--error-color);
  font-size: 0.8rem;
  margin-top: 0.3rem;
}

/* Badges */
.user-badge {
  display: inline-flex;
  align-items: center;
  padding: 0.3rem 0.6rem;
  border-radius: 20px;
  font-size: 0.8rem;
  font-weight: 500;
}

.badge-chefe {
  background: rgba(231, 76, 60, 0.1);
  color: #e74c3c;
}

.badge-gerente {
  background: rgba(52, 152, 219, 0.1);
  color: #3498db;
}

.badge-funcionario {
  background: rgba(46, 204, 113, 0.1);
  color: #2ecc71;
}

/* User Avatar and Profile */
.user-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  object-fit: cover;
}

.profile-card {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.profile-avatar {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  object-fit: cover;
}

.profile-info h3 {
  margin: 0 0 0.5rem 0;
  color: var(--text-color);
}

.profile-info p {
  margin: 0;
  color: #777;
}

/* Statistics */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 1.5rem;
  margin-bottom: 2rem;
}

.stats-card {
  background: white;
  border-radius: 8px;
  padding: 1.5rem;
  box-shadow: var(--box-shadow);
  text-align: center;
}

.stats-card h3 {
  margin: 0;
  color: var(--text-color);
}

.stats-card .number {
  font-size: 2rem;
  font-weight: 700;
  color: var(--primary-color);
  margin: 0.5rem 0;
}

.stats-card p {
  margin: 0;
  color: #777;
}

/* Alerts */
.alert {
  padding: 1rem 1.5rem;
  border-radius: 4px;
  margin-bottom: 1.5rem;
}

.alert-success {
  background: rgba(46, 204, 113, 0.1);
  border-left: 4px solid var(--success-color);
  color: #27ae60;
}

.alert-danger {
  background: rgba(231, 76, 60, 0.1);
  border-left: 4px solid var(--error-color);
  color: #e74c3c;
}

/* Modal */
.modal {
  display: none;
  position: fixed;
  z-index: 1000;
  left: 0;
  top: 0;
  width: 100%;
  height: 100%;
  overflow: auto;
  background-color: rgba(0, 0, 0, 0.5);
}

.modal-content {
  background-color: white;
  margin: 10% auto;
  padding: 1.5rem;
  border-radius: 8px;
  width: 80%;
  max-width: 600px;
  box-shadow: var(--box-shadow);
  animation: modalFadeIn 0.3s;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
}

.modal-header h2 {
  margin: 0;
}

.close {
  color: #aaa;
  font-size: 1.5rem;
  font-weight: bold;
  cursor: pointer;
}

.close:hover {
  color: black;
}

@keyframes modalFadeIn {
  from {
    opacity: 0;
    transform: translateY(-30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* Timeline */
.timeline {
  position: relative;
  padding-left: 30px;
  margin-top: 1.5rem;
}

.timeline-item {
  position: relative;
  padding-bottom: 1.5rem;
}

.timeline-item:last-child {
  padding-bottom: 0;
}

.timeline-item:before {
  content: '';
  position: absolute;
  left: -30px;
  top: 0;
  width: 2px;
  height: 100%;
  background: var(--border-color);
}

.timeline-item:last-child:before {
  height: 15px;
}

.timeline-item:after {
  content: '';
  position: absolute;
  left: -38px;
  top: 5px;
  width: 16px;
  height: 16px;
  border-radius: 50%;
  background: var(--primary-color);
  border: 2px solid white;
}

.timeline-date {
  font-size: 0.8rem;
  color: #777;
  margin-bottom: 0.3rem;
}

.timeline-content {
  background: white;
  padding: 1rem;
  border-radius: 4px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

/* Pagination */
.pagination {
  display: flex;
  justify-content: center;
  margin-top: 1.5rem;
}

.pagination-item {
  margin: 0 0.3rem;
  padding: 0.5rem 0.8rem;
  border: 1px solid var(--border-color);
  border-radius: 4px;
  color: var(--text-color);
  text-decoration: none;
  transition: all 0.3s;
}

.pagination-item.active,
.pagination-item:hover {
  background: var(--primary-color);
  color: white;
  border-color: var(--primary-color);
}

/* Off-screen Menu */
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
  background-color: var(--light-bg);
  color: var(--primary-color);
}

.off-screen-menu ul li ul {
  padding: 0;
}

.off-screen-menu ul li ul li a {
  padding-left: 3rem;
  font-size: 0.9rem;
}

/* Responsive */
@media (max-width: 768px) {
  .stats-grid {
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
  }
  
  .funcionarios-table {
    display: block;
    overflow-x: auto;
    white-space: nowrap;
  }
  
  .modal-content {
    width: 95%;
    margin: 5% auto;
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
<body>
    <!-- Header (same as in the original file) -->
   

<!-- Continuation of administracao_funcionarios.php from where it was cut off -->

<div class="admin-container">
    <div class="admin-header">
        <h1 class="admin-title"><i class="fas fa-users-cog"></i> Administração de Funcionários</h1>
        <button class="btn btn-primary" id="btnAddFuncionario"><i class="fas fa-user-plus"></i> Novo Funcionário</button>
    </div>

    <?php if (!empty($mensagem_sucesso)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo $mensagem_sucesso; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($erro_geral)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <?php echo $erro_geral; ?>
        </div>
    <?php endif; ?>

    <div class="admin-tabs">
        <div class="admin-tab active" data-tab="funcionarios">
            <i class="fas fa-users"></i> Funcionários
        </div>
        <div class="admin-tab" data-tab="estatisticas">
            <i class="fas fa-chart-pie"></i> Estatísticas
        </div>
        <?php if (isset($_GET['ver_atividades'])): ?>
            <div class="admin-tab active" data-tab="atividades">
                <i class="fas fa-history"></i> Atividades de <?php echo htmlspecialchars($nome_funcionario); ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Tab de funcionários -->
    <div class="tab-content <?php echo !isset($_GET['ver_atividades']) ? 'active' : ''; ?>" id="funcionarios">
        <div class="card">
            <div class="card-header">
                <h3>Lista de Funcionários</h3>
                <div class="search-box">
                    <input type="text" id="searchFuncionarios" placeholder="Pesquisar funcionários...">
                    <i class="fas fa-search"></i>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="funcionarios-table" id="funcionariosTable">
                        <thead>
                            <tr>
                                <th>Foto</th>
                                <th>Nome</th>
                                <th>Email</th>
                                <th>Cargo</th>
                                <th>Data de Cadastro</th>
                                <th>Atividades</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
    <?php foreach ($funcionarios as $func): ?>
        <tr>
            <td>
                <img src="<?php echo !empty($func['foto_perfil']) ? htmlspecialchars($func['foto_perfil']) : '../uploads/basico.png'; ?>" 
                     alt="Foto de <?php echo htmlspecialchars($func['nome']); ?>" 
                     class="user-avatar">
            </td>
            <td><?php echo htmlspecialchars($func['nome']); ?></td>
            <td><?php echo htmlspecialchars($func['email']); ?></td>
            <td>
                <span class="user-badge badge-<?php echo htmlspecialchars($func['cargo']); ?>">
                    <?php echo ucfirst(htmlspecialchars($func['cargo'])); ?>
                </span>
            </td>
            <td><?php echo formatar_data($func['created_at']); ?></td>
            <td>
                <strong><?php echo $func['total_atividades']; ?></strong> atividades
                <br>
                <small>Última: <?php echo !empty($func['ultima_atividade']) ? formatar_data_hora($func['ultima_atividade']) : 'Nunca'; ?></small>
            </td>
            <td class="actions">
                <div class="actions-container">
                    <a href="?ver_atividades=<?php echo $func['id']; ?>" class="btn btn-warning btn-small btn-action">
                        <i class="fas fa-history"></i>
                    </a>
                    <button class="btn btn-primary btn-small btn-action btn-edit" 
                            data-id="<?php echo $func['id']; ?>"
                            data-nome="<?php echo htmlspecialchars($func['nome']); ?>"
                            data-email="<?php echo htmlspecialchars($func['email']); ?>"
                            data-cargo="<?php echo htmlspecialchars($func['cargo']); ?>"
                            data-nascimento="<?php echo htmlspecialchars($func['data_nascimento']); ?>"
                            data-endereco="<?php echo htmlspecialchars($func['endereco']); ?>">
                        <i class="fas fa-edit"></i>
                    </button>
                    <?php if ($func['id'] != $usuario['id']): ?>
                        <button class="btn btn-danger btn-small btn-action btn-delete" data-id="<?php echo $func['id']; ?>" data-nome="<?php echo htmlspecialchars($func['nome']); ?>">
                            <i class="fas fa-trash"></i>
                        </button>
                    <?php else: ?>
                        <button class="btn btn-secondary btn-small btn-action" disabled title="Você não pode excluir sua própria conta">
                            <i class="fas fa-trash"></i>
                        </button>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (empty($funcionarios)): ?>
        <tr>
            <td colspan="7" class="text-center">Nenhum funcionário encontrado.</td>
        </tr>
    <?php endif; ?>
</tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab de estatísticas -->
    <div class="tab-content" id="estatisticas">
        <div class="stats-grid">
            <div class="stats-card">
                <h3>Total de Funcionários</h3>
                <div class="number"><?php echo count($funcionarios); ?></div>
                <p>Cadastrados no sistema</p>
            </div>
            <div class="stats-card">
                <h3>Chefes</h3>
                <div class="number">
                    <?php 
                        echo count(array_filter($funcionarios, function($f) {
                            return $f['cargo'] == 'chefe';
                        }));
                    ?>
                </div>
                <p>Administradores do sistema</p>
            </div>
            <div class="stats-card">
                <h3>Gerentes</h3>
                <div class="number">
                    <?php 
                        echo count(array_filter($funcionarios, function($f) {
                            return $f['cargo'] == 'gerente';
                        }));
                    ?>
                </div>
                <p>Gerentes de estoque</p>
            </div>
            <div class="stats-card">
                <h3>Funcionários</h3>
                <div class="number">
                    <?php 
                        echo count(array_filter($funcionarios, function($f) {
                            return $f['cargo'] == 'funcionario';
                        }));
                    ?>
                </div>
                <p>Operadores do sistema</p>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3>Atividades Recentes</h3>
            </div>
            <div class="card-body">
                <div class="timeline">
                    <?php 
                    $total_atividades = 0;
                    foreach ($funcionarios as $func): 
                        $total_atividades += $func['total_atividades'];
                    endforeach;
                    ?>
                    <div class="timeline-item">
                        <div class="timeline-date">Estatísticas gerais</div>
                        <div class="timeline-content">
                            <p><strong>Total de atividades registradas:</strong> <?php echo $total_atividades; ?></p>
                            <p><strong>Média de atividades por funcionário:</strong> 
                                <?php echo count($funcionarios) > 0 ? round($total_atividades / count($funcionarios), 1) : 0; ?>
                            </p>
                            <p><strong>Funcionário mais ativo:</strong> 
                                <?php 
                                $mais_ativo = null;
                                $max_atividades = 0;
                                foreach ($funcionarios as $func) {
                                    if ($func['total_atividades'] > $max_atividades) {
                                        $mais_ativo = $func;
                                        $max_atividades = $func['total_atividades'];
                                    }
                                }
                                echo $mais_ativo ? htmlspecialchars($mais_ativo['nome']) . ' (' . $max_atividades . ' atividades)' : 'Nenhum';
                                ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab de atividades de um funcionário específico -->
    <?php if (isset($_GET['ver_atividades'])): ?>
        <div class="tab-content active" id="atividades">
            <div class="card">
                <div class="card-header">
                    <h3>
                        <a href="administracao_funcionarios.php" class="btn btn-secondary btn-small">
                            <i class="fas fa-arrow-left"></i>
                        </a>
                        Histórico de Atividades: <?php echo htmlspecialchars($nome_funcionario); ?>
                    </h3>
                </div>
                <div class="card-body">
                    <?php if (empty($atividades_funcionario)): ?>
                        <p class="text-center">Nenhuma atividade registrada para este funcionário.</p>
                    <?php else: ?>
                        <div class="timeline">
                            <?php foreach ($atividades_funcionario as $atividade): ?>
                                <div class="timeline-item">
                                    <div class="timeline-date"><?php echo formatar_data_hora($atividade['data']); ?></div>
                                    <div class="timeline-content">
                                        <p><?php echo htmlspecialchars($atividade['atividade']); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Modal Adicionar Funcionário -->
<div id="modalAddFuncionario" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Adicionar Novo Funcionário</h2>
            <span class="close">&times;</span>
        </div>
        <form method="POST" action="" id="formAddFuncionario">
            <div class="form-group">
                <label for="nome">Nome *</label>
                <input type="text" class="form-control" id="nome" name="nome" required>
                <?php if (isset($erros['nome'])): ?>
                    <span class="form-error"><?php echo $erros['nome']; ?></span>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label for="email">E-mail *</label>
                <input type="email" class="form-control" id="email" name="email" required>
                <?php if (isset($erros['email'])): ?>
                    <span class="form-error"><?php echo $erros['email']; ?></span>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label for="senha">Senha *</label>
                <input type="password" class="form-control" id="senha" name="senha" required minlength="8">
                <small>A senha deve ter pelo menos 8 caracteres.</small>
                <?php if (isset($erros['senha'])): ?>
                    <span class="form-error"><?php echo $erros['senha']; ?></span>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label for="cargo">Cargo *</label>
                <select class="form-control" id="cargo" name="cargo" required>
                    <option value="">Selecione um cargo</option>
                    <option value="funcionario" selected>Funcionário</option>
                    <?php if ($usuario['cargo'] == 'chefe'): ?>
                        <option value="gerente">Gerente</option>
                        <option value="chefe">Chefe</option>
                    <?php elseif ($usuario['cargo'] == 'gerente'): ?>
                        <option value="funcionario">Funcionário</option>
                    <?php endif; ?>
                </select>
                <?php if (isset($erros['cargo'])): ?>
                    <span class="form-error"><?php echo $erros['cargo']; ?></span>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label for="data_nascimento">Data de Nascimento</label>
                <input type="date" class="form-control" id="data_nascimento" name="data_nascimento">
            </div>
            <div class="form-group">
                <label for="endereco">Endereço</label>
                <textarea class="form-control" id="endereco" name="endereco" rows="3"></textarea>
            </div>
            <button type="submit" name="adicionar_funcionario" class="btn btn-success">Adicionar Funcionário</button>
        </form>
    </div>
</div>

<!-- Modal Editar Funcionário -->
<div id="modalEditFuncionario" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Editar Funcionário</h2>
            <span class="close">&times;</span>
        </div>
        <form method="POST" action="" id="formEditFuncionario">
            <input type="hidden" id="edit_id" name="id">
            <div class="form-group">
                <label for="edit_nome">Nome *</label>
                <input type="text" class="form-control" id="edit_nome" name="nome" required>
                <?php if (isset($erros['nome'])): ?>
                    <span class="form-error"><?php echo $erros['nome']; ?></span>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label for="edit_email">E-mail *</label>
                <input type="email" class="form-control" id="edit_email" name="email" required>
                <?php if (isset($erros['email'])): ?>
                    <span class="form-error"><?php echo $erros['email']; ?></span>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label for="edit_nova_senha">Nova Senha (deixe em branco para manter a atual)</label>
                <input type="password" class="form-control" id="edit_nova_senha" name="nova_senha" minlength="8">
                <small>A senha deve ter pelo menos 8 caracteres.</small>
                <?php if (isset($erros['nova_senha'])): ?>
                    <span class="form-error"><?php echo $erros['nova_senha']; ?></span>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label for="edit_cargo">Cargo *</label>
                <select class="form-control" id="edit_cargo" name="cargo" required>
                    <option value="">Selecione um cargo</option>
                    <option value="funcionario">Funcionário</option>
                    <?php if ($usuario['cargo'] == 'chefe'): ?>
                        <option value="gerente">Gerente</option>
                        <option value="chefe">Chefe</option>
                    <?php endif; ?>
                </select>
                <?php if (isset($erros['cargo'])): ?>
                    <span class="form-error"><?php echo $erros['cargo']; ?></span>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label for="edit_data_nascimento">Data de Nascimento</label>
                <input type="date" class="form-control" id="edit_data_nascimento" name="data_nascimento">
            </div>
            <div class="form-group">
                <label for="edit_endereco">Endereço</label>
                <textarea class="form-control" id="edit_endereco" name="endereco" rows="3"></textarea>
            </div>
            <button type="submit" name="editar_funcionario" class="btn btn-success">Salvar Alterações</button>
        </form>
    </div>
</div>

<!-- Modal Excluir Funcionário -->
<div id="modalDeleteFuncionario" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Confirmar Exclusão</h2>
            <span class="close">&times;</span>
        </div>
        <div class="modal-body">
            <p>Tem certeza que deseja excluir o funcionário <strong id="delete_nome"></strong>?</p>
            <p class="text-danger">Esta ação não poderá ser desfeita.</p>
            <form method="POST" action="">
                <input type="hidden" id="delete_id" name="id">
                <div class="form-group">
                    <button type="submit" name="excluir_funcionario" class="btn btn-danger">Confirmar Exclusão</button>
                    <button type="button" class="btn btn-secondary cancel-delete">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- Footer -->
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
// Scripts para funcionamento das modais e interações
document.addEventListener('DOMContentLoaded', function() {
    // Variáveis para os modais
    const modalAdd = document.getElementById('modalAddFuncionario');
    const modalEdit = document.getElementById('modalEditFuncionario');
    const modalDelete = document.getElementById('modalDeleteFuncionario');
    const btnAdd = document.getElementById('btnAddFuncionario');
    
    // Botões de fechar modal
    const closes = document.querySelectorAll('.close, .cancel-delete');
    
    // Abrir modal para adicionar
    btnAdd.addEventListener('click', function() {
        modalAdd.style.display = 'block';
    });
    
    // Fechar modais
    closes.forEach(function(close) {
        close.addEventListener('click', function() {
            modalAdd.style.display = 'none';
            modalEdit.style.display = 'none';
            modalDelete.style.display = 'none';
        });
    });
    
    // Fechar ao clicar fora do modal
    window.addEventListener('click', function(event) {
        if (event.target == modalAdd) {
            modalAdd.style.display = 'none';
        }
        if (event.target == modalEdit) {
            modalEdit.style.display = 'none';
        }
        if (event.target == modalDelete) {
            modalDelete.style.display = 'none';
        }
    });
    
    // Botões de editar
    const editButtons = document.querySelectorAll('.btn-edit');
    editButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const nome = this.getAttribute('data-nome');
            const email = this.getAttribute('data-email');
            const cargo = this.getAttribute('data-cargo');
            const nascimento = this.getAttribute('data-nascimento');
            const endereco = this.getAttribute('data-endereco');
            
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_nome').value = nome;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_cargo').value = cargo;
            document.getElementById('edit_data_nascimento').value = nascimento;
            document.getElementById('edit_endereco').value = endereco;
            
            modalEdit.style.display = 'block';
        });
    });
    
    // Botões de excluir
    const deleteButtons = document.querySelectorAll('.btn-delete');
    deleteButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const nome = this.getAttribute('data-nome');
            
            document.getElementById('delete_id').value = id;
            document.getElementById('delete_nome').textContent = nome;
            
            modalDelete.style.display = 'block';
        });
    });
    
    // Alternar entre abas
    const tabs = document.querySelectorAll('.admin-tab');
    tabs.forEach(function(tab) {
        tab.addEventListener('click', function() {
            const tabName = this.getAttribute('data-tab');
            
            // Remover classe active de todas as abas e conteúdos
            document.querySelectorAll('.admin-tab').forEach(function(t) {
                t.classList.remove('active');
            });
            document.querySelectorAll('.tab-content').forEach(function(c) {
                c.classList.remove('active');
            });
            
            // Adicionar classe active na aba clicada e seu conteúdo
            this.classList.add('active');
            document.getElementById(tabName).classList.add('active');
        });
    });
    
    // Filtro de pesquisa
    const searchInput = document.getElementById('searchFuncionarios');
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            const query = this.value.toLowerCase();
            const rows = document.querySelectorAll('#funcionariosTable tbody tr');
            
            rows.forEach(function(row) {
                const nome = row.cells[1].textContent.toLowerCase();
                const email = row.cells[2].textContent.toLowerCase();
                const cargo = row.cells[3].textContent.toLowerCase();
                
                if (nome.includes(query) || email.includes(query) || cargo.includes(query)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }
});
</script>
</body>
</html>