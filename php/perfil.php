<?php
include '../estoque/header.php'; // Incluindo o header

if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit();
}

$usuario = $_SESSION['usuario'];

// Establish database connection FIRST - moved from below
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

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['atualizar_foto'])) {
    $usuario_id = $usuario['id'];
    
    // Verificar se uma nova foto de perfil foi enviada
    if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] == UPLOAD_ERR_OK) {
        $tmp_name = $_FILES['foto_perfil']['tmp_name'];
        $extensoes_permitidas = ['jpg', 'jpeg', 'png', 'gif'];
        $extensao = strtolower(pathinfo($_FILES['foto_perfil']['name'], PATHINFO_EXTENSION));
        
        if (!in_array($extensao, $extensoes_permitidas)) {
            $erros['foto_perfil'] = "Formato de imagem não suportado. Use JPG, JPEG, PNG ou GIF.";
        } else if ($_FILES['foto_perfil']['size'] > 5 * 1024 * 1024) { // 5MB
            $erros['foto_perfil'] = "O tamanho da imagem não pode exceder 5MB.";
        } else {
            $novo_nome = uniqid() . '.' . $extensao;
            $caminho = '../uploads/' . $novo_nome;

            if (move_uploaded_file($tmp_name, $caminho)) {
                // Se o usuário já tinha uma foto personalizada, exclua-a
                if ($usuario['foto_perfil'] != '../uploads/basico.png' && file_exists($usuario['foto_perfil'])) {
                    unlink($usuario['foto_perfil']);
                }
                
                // Atualizar no banco de dados
                $stmt = $conn->prepare("UPDATE ga3_usuarios SET foto_perfil = ? WHERE id = ?");
                $stmt->bind_param("si", $caminho, $usuario_id);
                
                if ($stmt->execute()) {
                    // Atualizar a sessão com a nova foto
                    $_SESSION['usuario']['foto_perfil'] = $caminho;
                    $usuario['foto_perfil'] = $caminho;
                    
                    // Registrar atividade
                    registrar_atividade($conn, $usuario_id, 'Upload de foto de perfil');
                    $mensagem_sucesso = "Foto de perfil atualizada com sucesso!";
                } else {
                    $erro_geral = "Erro ao atualizar a foto de perfil: " . $conn->error;
                }
                $stmt->close();
            } else {
                $erro_geral = "Erro ao fazer upload da imagem. Tente novamente.";
            }
        }
    } else if ($_FILES['foto_perfil']['error'] != UPLOAD_ERR_NO_FILE) {
        // Se houver algum erro no upload (exceto quando nenhum arquivo é enviado)
        switch ($_FILES['foto_perfil']['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $erro_geral = "O arquivo é muito grande.";
                break;
            case UPLOAD_ERR_PARTIAL:
                $erro_geral = "O upload do arquivo foi feito parcialmente.";
                break;
            default:
                $erro_geral = "Erro no upload: " . $_FILES['foto_perfil']['error'];
        }
    } else {
        $erro_geral = "Nenhuma imagem foi selecionada.";
    }
}

// Definir a foto de perfil padrão se não estiver definida
if (!isset($usuario['foto_perfil']) || empty($usuario['foto_perfil'])) {
    $usuario['foto_perfil'] = '../uploads/basico.png';
}

// Atualizar informações do usuário
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['atualizar'])) {
    $nome = trim($_POST['nome']);
    $email = trim($_POST['email']);
    $data_nascimento = !empty($_POST['data_nascimento']) ? $_POST['data_nascimento'] : null;
    $endereco = trim($_POST['endereco']);
    $usuario_id = $usuario['id'];
    
    // Validações
    $erros = [];
    
    if (empty($nome)) {
        $erros['nome'] = "O nome é obrigatório.";
    } elseif (strlen($nome) < 3) {
        $erros['nome'] = "O nome deve ter pelo menos 3 caracteres.";
    }
    
    if (empty($email)) {
        $erros['email'] = "O email é obrigatório.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erros['email'] = "Por favor, insira um email válido.";
    } else {
        // Verificar se o email já existe para outro usuário
        $check_email = $conn->prepare("SELECT id FROM ga3_usuarios WHERE email = ? AND id != ?");
        $check_email->bind_param("si", $email, $usuario_id);
        $check_email->execute();
        $result = $check_email->get_result();
        
        if ($result->num_rows > 0) {
            $erros['email'] = "Este email já está sendo usado por outro usuário.";
        }
        $check_email->close();
    }
    
    // Continuar apenas se não houver erros
    if (empty($erros)) {
        // Verificar se uma nova foto de perfil foi enviada
        $foto_perfil = $usuario['foto_perfil'] ?? null;
        if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] == UPLOAD_ERR_OK) {
            $tmp_name = $_FILES['foto_perfil']['tmp_name'];
            $extensoes_permitidas = ['jpg', 'jpeg', 'png', 'gif'];
            $extensao = strtolower(pathinfo($_FILES['foto_perfil']['name'], PATHINFO_EXTENSION));
            
            if (!in_array($extensao, $extensoes_permitidas)) {
                $erros['foto_perfil'] = "Formato de imagem não suportado. Use JPG, JPEG, PNG ou GIF.";
            } else if ($_FILES['foto_perfil']['size'] > 5 * 1024 * 1024) { // 5MB
                $erros['foto_perfil'] = "O tamanho da imagem não pode exceder 5MB.";
            } else {
                $novo_nome = uniqid() . '.' . $extensao;
                $caminho = '../uploads/' . $novo_nome;

                if (move_uploaded_file($tmp_name, $caminho)) {
                    // Se o usuário já tinha uma foto personalizada, exclua-a
                    if ($foto_perfil != '../uploads/basico.png' && file_exists($foto_perfil)) {
                        unlink($foto_perfil);
                    }
                    $foto_perfil = $caminho;
                } else {
                    $erros['foto_perfil'] = "Erro ao fazer upload da imagem. Tente novamente.";
                }
            }
        }
        
        if (empty($erros)) {
            $stmt = $conn->prepare("UPDATE ga3_usuarios SET nome = ?, email = ?, data_nascimento = ?, endereco = ?, foto_perfil = ? WHERE id = ?");
            $stmt->bind_param("sssssi", $nome, $email, $data_nascimento, $endereco, $foto_perfil, $usuario_id);

            if ($stmt->execute()) {
                // Atualizar a sessão com os novos dados
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

                // Registrar atividade
                registrar_atividade($conn, $usuario_id, 'Atualização de perfil');
                $mensagem_sucesso = "Perfil atualizado com sucesso!";
            } else {
                $erro_geral = "Erro ao atualizar o perfil: " . $conn->error;
            }

            $stmt->close();
        }
    }
}

// Remover foto de perfil
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['remover_foto'])) {
    $usuario_id = $usuario['id'];
    
    // Excluir arquivo físico se não for a imagem padrão
    if ($usuario['foto_perfil'] != '../uploads/basico.png' && file_exists($usuario['foto_perfil'])) {
        unlink($usuario['foto_perfil']);
    }
    
    $stmt = $conn->prepare("UPDATE ga3_usuarios SET foto_perfil = NULL WHERE id = ?");
    $stmt->bind_param("i", $usuario_id);

    if ($stmt->execute()) {
        // Atualizar a sessão com os novos dados
        $_SESSION['usuario']['foto_perfil'] = '../uploads/basico.png';
        $usuario['foto_perfil'] = '../uploads/basico.png';

        // Registrar atividade
        registrar_atividade($conn, $usuario_id, 'Remoção de foto de perfil');
        $mensagem_sucesso = "Foto de perfil removida com sucesso!";
    } else {
        $erro_geral = "Erro ao remover a foto de perfil: " . $conn->error;
    }

    $stmt->close();
}

// Alterar senha
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['alterar_senha'])) {
    $senha_atual = $_POST['senha_atual'];
    $nova_senha = $_POST['nova_senha'];
    $confirmar_senha = $_POST['confirmar_senha'];
    $usuario_id = $usuario['id'];
    
    $erros_senha = [];
    
    // Verificar senha atual
    $stmt = $conn->prepare("SELECT senha FROM ga3_usuarios WHERE id = ?");
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    if (!password_verify($senha_atual, $user['senha'])) {
        $erros_senha['senha_atual'] = "Senha atual incorreta.";
    }
    
    // Validar nova senha
    if (strlen($nova_senha) < 8) {
        $erros_senha['nova_senha'] = "A nova senha deve ter pelo menos 8 caracteres.";
    } elseif ($nova_senha !== $confirmar_senha) {
        $erros_senha['confirmar_senha'] = "As senhas não coincidem.";
    }
    
    if (empty($erros_senha)) {
        $senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE ga3_usuarios SET senha = ? WHERE id = ?");
        $stmt->bind_param("si", $senha_hash, $usuario_id);
        
        if ($stmt->execute()) {
            registrar_atividade($conn, $usuario_id, 'Alteração de senha');
            $mensagem_sucesso_senha = "Senha alterada com sucesso!";
        } else {
            $erro_geral_senha = "Erro ao alterar a senha: " . $conn->error;
        }
        
        $stmt->close();
    }
}

// Buscar as últimas 10 atividades do usuário
$atividades = [];
$stmt = $conn->prepare("SELECT atividade, data FROM ga3_atividades WHERE usuario_id = ? ORDER BY data DESC LIMIT 10");
$stmt->bind_param("i", $usuario['id']);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $atividades[] = $row;
}
$stmt->close();

$conn->close();

// Formatação de data para exibição
function formatar_data($data) {
    if (empty($data)) return "Não informado";
    return date('d/m/Y', strtotime($data));
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu Perfil - Stockly</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/styleprincipal.css">
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
  --primary-color: #5ea7a5;
  --secondary-color: #2a9d8f;
  --text-color: #333;
  --light-bg: #f5f8fa;
  --border-color: #ddd;
  --success-color: #2ecc71;
  --error-color: #e74c3c;
  --warning-color: #f39c12;
  --box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
  --bg-secondary: #f0f5fa;
}


main {
  flex: 1;
}

footer {
  position: relative;
  width: 100%;
  margin-top: auto;
}

/* Container e layout */
.profile-container {
  max-width: 1200px;
  margin: 30px auto;
  padding: 0 15px;
}

.page-title {
  font-size: 24px;
  font-weight: 500;
  color: var(--primary-color);
  border-bottom: 2px solid var(--secondary-color);
  padding-bottom: 10px;
  margin-bottom: 30px;
}

.profile-grid {
  display: grid;
  grid-template-columns: 1fr 2fr;
  gap: 30px;
}

.profile-sidebar, .profile-content {
  background: #fff;
  border-radius: 8px;
  box-shadow: var(--box-shadow);
  padding: 20px;
}

.profile-sidebar {
  text-align: center;
}

/* Perfil de usuário */
.profile-photo {
  position: relative;
  width: 180px;
  height: 180px;
  margin: 0 auto 20px;
}

.profile-photo img {
  width: 100%;
  height: 100%;
  border-radius: 50%;
  object-fit: cover;
  border: 3px solid var(--secondary-color);
}

.profile-photo .edit-photo {
  position: absolute;
  bottom: 10px;
  right: 10px;
  background: var(--secondary-color);
  color: white;
  width: 35px;
  height: 35px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
  transition: background 0.3s;
}

.profile-photo .edit-photo:hover {
  background: #2980b9;
}

.user-info h2 {
  font-size: 22px;
  margin-bottom: 5px;
  color: var(--primary-color);
}

.user-info p {
  color: #666;
  font-size: 14px;
  margin-bottom: 20px;
}

.user-role {
  color: white;
  border-radius: 20px;
  font-size: 12px;
  display: inline-block;
  margin-bottom: 20px;
}

/* Tabs */
.tab-container {
  margin-top: 20px;
}

.tabs {
  display: flex;
  border-bottom: 1px solid var(--border-color);
  margin-bottom: 20px;
}

.tab {
  padding: 10px 20px;
  cursor: pointer;
  font-weight: 500;
  color: #666;
  position: relative;
  transition: all 0.3s;
}

.tab.active {
  color: var(--secondary-color);
}

.tab.active::after {
  content: '';
  position: absolute;
  bottom: -1px;
  left: 0;
  width: 100%;
  height: 3px;
  background-color: var(--secondary-color);
  border-radius: 3px 3px 0 0;
}

.tab-content {
  display: none;
}

.tab-content.active {
  display: block;
}

/* Formulários */
.form-group {
  margin-bottom: 20px;
}

.form-group label {
  display: block;
  margin-bottom: 5px;
  font-weight: 500;
  color: var(--primary-color);
}

.form-control {
  width: 100%;
  padding: 10px 15px;
  border: 1px solid var(--border-color);
  border-radius: 4px;
  font-size: 14px;
  transition: border-color 0.3s;
}

.form-control:focus {
  outline: none;
  border-color: var(--secondary-color);
  box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
}

.error-message {
  color: var(--error-color);
  font-size: 12px;
  margin-top: 5px;
}

/* Botões */
.btn {
  padding: 10px 20px;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-weight: 500;
  transition: background 0.3s, transform 0.1s;
}

.btn:hover {
  transform: translateY(-2px);
}

.btn:active {
  transform: translateY(0);
}

.btn-primary {
  background-color: var(--secondary-color);
  color: white;
}

.btn-primary:hover {
  background-color: #1e7376;
}

.btn-outline {
  background-color: transparent;
  border: 1px solid var(--secondary-color);
  color: var(--secondary-color);
}

.btn-outline:hover {
  background-color: rgba(52, 152, 219, 0.1);
}

.btn-danger {
  background-color: var(--error-color);
  color: white;
}

.btn-danger:hover {
  background-color: #c0392b;
}

/* Lista de atividades */
.activity-list {
  list-style: none;
  padding: 0;
}

.activity-item {
  padding: 15px 0;
  border-bottom: 1px solid var(--border-color);
  display: flex;
  align-items: flex-start;
}

.activity-item:last-child {
  border-bottom: none;
}

.activity-icon {
  background-color: var(--bg-secondary);
  width: 40px;
  height: 40px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-right: 15px;
  color: var(--secondary-color);
}

.activity-content {
  flex-grow: 1;
}

.activity-content p {
  margin: 0;
  line-height: 1.4;
}

.activity-time {
  font-size: 12px;
  color: #999;
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

/* Alertas */
.alert {
  padding: 15px;
  margin-bottom: 20px;
  border-radius: 4px;
  font-size: 14px;
}

.alert-success {
  background-color: rgba(46, 204, 113, 0.1);
  border-left: 4px solid var(--success-color);
  color: #27ae60;
}

.alert-danger {
  background-color: rgba(231, 76, 60, 0.1);
  border-left: 4px solid var(--error-color);
  color: #c0392b;
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
  background-color: rgba(0, 0, 0, 0.5);
  overflow: auto;
}

.modal-content {
  background-color: white;
  margin: 10% auto;
  padding: 25px;
  border-radius: 8px;
  width: 450px;
  max-width: 90%;
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
  position: relative;
}

.close-modal {
  position: absolute;
  right: 20px;
  top: 15px;
  font-size: 24px;
  cursor: pointer;
  color: #999;
}

.close-modal:hover {
  color: #333;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  padding-top: 15px;
  gap: 10px;
}

/* Área de upload */
.upload-area {
  border: 2px dashed var(--border-color);
  padding: 25px;
  text-align: center;
  margin: 15px 0;
  border-radius: 4px;
  cursor: pointer;
}

.upload-area:hover {
  border-color: var(--secondary-color);
}

.upload-area i {
  font-size: 40px;
  color: #999;
  margin-bottom: 10px;
}

.upload-text {
  color: #666;
  margin-bottom: 10px;
}

.file-types {
  font-size: 12px;
  color: #999;
}

#photoPreview {
  max-width: 100%;
  max-height: 200px;
  margin: 15px auto;
  display: none;
  border-radius: 4px;
}

/* Ações rápidas */
.quick-actions {
  margin-top: 30px;
}

.quick-actions h3 {
  font-size: 16px;
  color: var(--primary-color);
  margin-bottom: 15px;
  border-bottom: 1px solid var(--border-color);
  padding-bottom: 5px;
}

.action-links {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.action-link {
  display: flex;
  align-items: center;
  padding: 10px;
  color: var(--text-color);
  text-decoration: none;
  border-radius: 4px;
  transition: all 0.2s;
}

.action-link:hover {
  background-color: var(--bg-secondary);
  color: var(--secondary-color);
}

.action-link i {
  margin-right: 10px;
  width: 20px;
  text-align: center;
}

/* Badges */
.badge {
  display: inline-block;
  padding: 3px 8px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 500;
  margin-left: 10px;
}

.badge-admin {
  background-color: #9b59b6;
  color: white;
}

.badge-manager {
  background-color: #2980b9;
  color: white;
}

.badge-employee {
  background-color: hsl(0, 0%, 84.7%);
  color: white;
}

/* Classes utilitárias */
.fodao {
  padding: 5px 10px;
  background-color: #fff;
  border-radius: 10px;
  border: 1px solid var(--border-color);
}

/* Animações */
@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

.fade-in {
  animation: fadeIn 0.3s ease-in;
}

/* Responsividade */
@media (max-width: 768px) {
  .profile-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 576px) {
  .profile-photo {
    width: 120px;
    height: 120px;
  }
  
  .tabs {
    flex-wrap: wrap;
  }
  
  .tab {
    padding: 8px 12px;
    font-size: 13px;
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
    <main class="profile-container">
        <h1 class="page-title">Meu Perfil</h1>
        
        <?php if(isset($mensagem_sucesso)): ?>
            <div class="alert alert-success fade-in">
                <i class="fas fa-check-circle"></i> <?php echo $mensagem_sucesso; ?>
            </div>
        <?php endif; ?>
        
        <?php if(isset($erro_geral)): ?>
            <div class="alert alert-danger fade-in">
                <i class="fas fa-exclamation-circle"></i> <?php echo $erro_geral; ?>
            </div>
        <?php endif; ?>
        
        <div class="profile-grid">
            <div class="profile-sidebar">
                <div class="profile-photo">
                    <img src="<?php echo htmlspecialchars($usuario['foto_perfil']); ?>" alt="Foto de perfil">
                    <div class="edit-photo" id="editPhotoBtn">
                        <i class="fas fa-camera"></i>
                    </div>
                </div>
                
                <div class="user-info">
                    <h2><?php echo htmlspecialchars($usuario['nome']); ?></h2>
                    <p><?php echo htmlspecialchars($usuario['email']); ?></p>
                    
                    <div class="user-role">
                        <?php 
                        $cargo_label = '';
                        $cargo_class = '';
                        
                        switch($usuario['cargo']) {
                            case 'chefe':
                                $cargo_label = 'Administrador';
                                $cargo_class = 'badge-admin';
                                break;
                            case 'gerente':
                                $cargo_label = 'Gerente';
                                $cargo_class = 'badge-manager';
                                break;
                            case 'funcionario':
                                $cargo_label = 'Funcionário';
                                $cargo_class = 'badge-employee';
                                break;
                            default:
                                $cargo_label = 'Usuário';
                                $cargo_class = '';
                        }
                        echo '<span class="badge ' . $cargo_class . '">' . $cargo_label . '</span>';
                        ?>
                    </div>
                </div>
                
                <div class="quick-actions">
                    <h3>Ações Rápidas</h3>
                    <div class="action-links">
                        <a href="../estoque/dashboard.php" class="action-link">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                        <a href="../estoque/estoque.php" class="action-link">
                            <i class="fas fa-box"></i> Estoque
                        </a>
                        <?php if (isset($usuario['cargo']) && in_array($usuario['cargo'], ['chefe', 'gerente'])): ?>
                            <a href="configuracoes.php" class="action-link">
                                <i class="fas fa-cog"></i> Configurações
                            </a>
                        <?php endif; ?>
                        <a href="logout.php" class="action-link">
                        <i class="fas fa-sign-out-alt"></i> Sair
            </a>
        </div>
    </div>
</div>

<div class="profile-content">
    <div class="tab-container">
        <div class="tabs">
            <div class="tab active" data-tab="informacoes">Informações Pessoais</div>
            <div class="tab" data-tab="seguranca">Segurança</div>
            <div class="tab" data-tab="atividades">Atividades Recentes</div>
        </div>
        
        <div class="tab-content active" id="informacoes">
            <form action="perfil.php" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="nome">Nome completo</label>
                    <input type="text" id="nome" name="nome" class="form-control" value="<?php echo htmlspecialchars($usuario['nome']); ?>" required>
                    <?php if(isset($erros['nome'])): ?>
                        <div class="error-message"><?php echo $erros['nome']; ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($usuario['email']); ?>" required>
                    <?php if(isset($erros['email'])): ?>
                        <div class="error-message"><?php echo $erros['email']; ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="data_nascimento">Data de nascimento</label>
                    <input type="date" id="data_nascimento" name="data_nascimento" class="form-control" value="<?php echo htmlspecialchars($usuario['data_nascimento'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="endereco">Endereço</label>
                    <input type="text" id="endereco" name="endereco" class="form-control" value="<?php echo htmlspecialchars($usuario['endereco'] ?? ''); ?>">
                </div>
                
                <input type="hidden" name="foto_perfil" id="foto_perfil_input">
                
                <button type="submit" name="atualizar" class="btn btn-primary">Salvar alterações</button>
            </form>
        </div>
        
        <div class="tab-content" id="seguranca">
            <form action="perfil.php" method="POST">
                <?php if(isset($mensagem_sucesso_senha)): ?>
                    <div class="alert alert-success fade-in">
                        <i class="fas fa-check-circle"></i> <?php echo $mensagem_sucesso_senha; ?>
                    </div>
                <?php endif; ?>
                
                <?php if(isset($erro_geral_senha)): ?>
                    <div class="alert alert-danger fade-in">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $erro_geral_senha; ?>
                    </div>
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="senha_atual">Senha atual</label>
                    <input type="password" id="senha_atual" name="senha_atual" class="form-control" required>
                    <?php if(isset($erros_senha['senha_atual'])): ?>
                        <div class="error-message"><?php echo $erros_senha['senha_atual']; ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="nova_senha">Nova senha</label>
                    <input type="password" id="nova_senha" name="nova_senha" class="form-control" required>
                    <?php if(isset($erros_senha['nova_senha'])): ?>
                        <div class="error-message"><?php echo $erros_senha['nova_senha']; ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="confirmar_senha">Confirmar senha</label>
                    <input type="password" id="confirmar_senha" name="confirmar_senha" class="form-control" required>
                    <?php if(isset($erros_senha['confirmar_senha'])): ?>
                        <div class="error-message"><?php echo $erros_senha['confirmar_senha']; ?></div>
                    <?php endif; ?>
                </div>
                
                <button type="submit" name="alterar_senha" class="btn btn-primary">Alterar senha</button>
            </form>
        </div>
        
        <div class="tab-content" id="atividades">
            <h3>Suas atividades recentes</h3>
            <ul class="activity-list">
                <?php if(empty($atividades)): ?>
                    <li class="activity-item">
                        <div class="activity-icon">
                            <i class="fas fa-info-circle"></i>
                        </div>
                        <div class="activity-content">
                            <p>Nenhuma atividade registrada ainda.</p>
                        </div>
                    </li>
                <?php else: ?>
                    <?php foreach($atividades as $atividade): ?>
                        <li class="activity-item">
                            <div class="activity-icon">
                                <?php 
                                // Definir ícone com base no tipo de atividade
                                $icone = 'fas fa-history';
                                if(strpos($atividade['atividade'], 'Login') !== false) {
                                    $icone = 'fas fa-sign-in-alt';
                                } elseif(strpos($atividade['atividade'], 'perfil') !== false) {
                                    $icone = 'fas fa-user-edit';
                                } elseif(strpos($atividade['atividade'], 'senha') !== false) {
                                    $icone = 'fas fa-key';
                                } elseif(strpos($atividade['atividade'], 'foto') !== false) {
                                    $icone = 'fas fa-camera';
                                }
                                ?>
                                <i class="<?php echo $icone; ?>"></i>
                            </div>
                            <div class="activity-content">
                                <p><?php echo htmlspecialchars($atividade['atividade']); ?></p>
                                <span class="activity-time"><?php echo date('d/m/Y H:i', strtotime($atividade['data'])); ?></span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</div>
</div>
</main>

<!-- Modal de troca de foto -->
<!-- Modifique o modal de troca de foto no HTML -->
<div id="photoModal" class="modal">
    <div class="modal-content">
        <span class="close-modal">&times;</span>
        <h3>Atualizar foto de perfil</h3>
        
        <?php if(isset($erro_geral)): ?>
            <div class="alert alert-danger fade-in">
                <i class="fas fa-exclamation-circle"></i> <?php echo $erro_geral; ?>
            </div>
        <?php endif; ?>
        
        <form id="uploadPhotoForm" action="perfil.php" method="POST" enctype="multipart/form-data">
            <div class="upload-area" id="dropArea">
                <i class="fas fa-cloud-upload-alt"></i>
                <p class="upload-text">Arraste uma imagem aqui ou clique para selecionar</p>
                <p class="file-types">Formatos aceitos: JPG, PNG, GIF (Max 5MB)</p>
                <input type="file" id="fileInput" name="foto_perfil" accept="image/*" style="display: none;">
            </div>
            
            <img id="photoPreview" src="#" alt="Preview">
            
            <div class="modal-footer">
                <button type="button" id="removePhotoBtn" class="btn btn-outline">Remover foto atual</button>
                <button type="submit" name="atualizar_foto" class="btn btn-primary">Salvar foto</button>
            </div>
        </form>
        
        <form id="removePhotoForm" action="perfil.php" method="POST" style="display: none;">
            <input type="hidden" name="remover_foto" value="1">
        </form>
    </div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="../js/app.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Funcionalidade das abas
    const tabs = document.querySelectorAll('.tab');
    const tabContents = document.querySelectorAll('.tab-content');

    tabs.forEach(function(tab) {
        tab.addEventListener('click', function() {
            // Remover classe ativa de todas as abas
            tabs.forEach(function(t) {
                t.classList.remove('active');
            });
            
            // Adicionar classe ativa na aba clicada
            this.classList.add('active');
            
            // Mostrar conteúdo correspondente
            const tabId = this.getAttribute('data-tab');
            tabContents.forEach(function(content) {
                content.classList.remove('active');
            });
            document.getElementById(tabId).classList.add('active');
        });
    });

    // Modal de foto de perfil
    const modal = document.getElementById('photoModal');
    const editPhotoBtn = document.getElementById('editPhotoBtn');
    const closeModal = document.querySelector('.close-modal');
    const dropArea = document.getElementById('dropArea');
    const fileInput = document.getElementById('fileInput');
    const photoPreview = document.getElementById('photoPreview');
    const removePhotoBtn = document.getElementById('removePhotoBtn');
    const removePhotoForm = document.getElementById('removePhotoForm');
    const uploadPhotoForm = document.getElementById('uploadPhotoForm');

    // Abrir modal
    editPhotoBtn.addEventListener('click', function() {
        modal.style.display = 'block';
    });

    // Fechar modal
    closeModal.addEventListener('click', function() {
        modal.style.display = 'none';
    });

    // Fechar modal clicando fora
    window.addEventListener('click', function(event) {
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    });

    // Clique na área de upload
    dropArea.addEventListener('click', function() {
        fileInput.click();
    });

    // Arrastar e soltar arquivo
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropArea.addEventListener(eventName, preventDefaults, false);
    });

    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    ['dragenter', 'dragover'].forEach(eventName => {
        dropArea.addEventListener(eventName, highlight, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropArea.addEventListener(eventName, unhighlight, false);
    });

    function highlight() {
        dropArea.classList.add('highlight');
    }

    function unhighlight() {
        dropArea.classList.remove('highlight');
    }

    dropArea.addEventListener('drop', handleDrop, false);

    function handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        fileInput.files = files;
        handleFiles(files);
    }

    // Processar arquivos selecionados
    fileInput.addEventListener('change', function() {
        handleFiles(this.files);
    });

    function handleFiles(files) {
        if (files.length > 0) {
            const file = files[0];
            
            // Verificar tipo de arquivo
            const validTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (!validTypes.includes(file.type)) {
                alert('Formato de arquivo inválido. Por favor, selecione uma imagem JPG, PNG ou GIF.');
                return;
            }
            
            // Verificar tamanho (5MB máximo)
            if (file.size > 5 * 1024 * 1024) {
                alert('O arquivo é muito grande. O tamanho máximo permitido é 5MB.');
                return;
            }
            
            const reader = new FileReader();
            reader.onload = function(e) {
                photoPreview.src = e.target.result;
                photoPreview.style.display = 'block';
            }
            reader.readAsDataURL(file);
        }
    }

    // Remover foto
    removePhotoBtn.addEventListener('click', function() {
        if (confirm('Tem certeza que deseja remover sua foto de perfil?')) {
            removePhotoForm.submit();
        }
    });
});
</script>
</body>
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
</html>