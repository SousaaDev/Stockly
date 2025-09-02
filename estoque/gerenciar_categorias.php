<?php
include 'header.php'; // Incluindo o header


// Verifica se o usuário está logado
if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit();
}

$usuario = $_SESSION['usuario'];

// Conexão com o banco de dados
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ga3_stockly";

$conn = new mysqli($servername, $username, $password, $dbname);

// Verifica a conexão
if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}

// Mensagens de feedback para o usuário
$mensagem = '';
$tipo_mensagem = '';

// Adicionar nova categoria
if (isset($_POST['nova_categoria'])) {
    $nome_categoria = $conn->real_escape_string(trim($_POST['nova_categoria']));
    
    // Verificar se a categoria já existe
    $check_sql = "SELECT * FROM ga3_categorias WHERE nome = '$nome_categoria'";
    $check_result = $conn->query($check_sql);
    
    if ($check_result->num_rows > 0) {
        $mensagem = "Esta categoria já existe!";
        $tipo_mensagem = "error";
    } else {
        $sql = "INSERT INTO ga3_categorias (nome) VALUES ('$nome_categoria')";
        
        if ($conn->query($sql) === TRUE) {
            $mensagem = "Categoria adicionada com sucesso!";
            $tipo_mensagem = "success";
            
            // Registrar atividade
            if (function_exists('registrar_atividade')) {
                registrar_atividade($conn, $usuario['id'], "Adicionou categoria: $nome_categoria");
            }
        } else {
            $mensagem = "Erro ao adicionar categoria: " . $conn->error;
            $tipo_mensagem = "error";
        }
    }
}

// Excluir categoria
if (isset($_POST['excluir_categoria'])) {
    $categoria_id = intval($_POST['excluir_categoria']);
    
    // Verificar se a categoria está sendo usada
    $check_uso = "SELECT COUNT(*) as total FROM ga3_materiais WHERE categoria_id = $categoria_id";
    $result_uso = $conn->query($check_uso);
    $row_uso = $result_uso->fetch_assoc();
    
    if ($row_uso['total'] > 0) {
        $mensagem = "Esta categoria não pode ser excluída pois está sendo usada por produtos no estoque!";
        $tipo_mensagem = "error";
    } else {
        $sql = "DELETE FROM ga3_categorias WHERE id = $categoria_id";
        
        if ($conn->query($sql) === TRUE) {
            $mensagem = "Categoria excluída com sucesso!";
            $tipo_mensagem = "success";
            
            // Registrar atividade
            if (function_exists('registrar_atividade')) {
                registrar_atividade($conn, $usuario['id'], "Excluiu categoria #$categoria_id");
            }
        } else {
            $mensagem = "Erro ao excluir categoria: " . $conn->error;
            $tipo_mensagem = "error";
        }
    }
}

// Editar categoria
if (isset($_POST['editar_categoria']) && isset($_POST['categoria_id'])) {
    $categoria_id = intval($_POST['categoria_id']);
    $novo_nome = $conn->real_escape_string(trim($_POST['editar_categoria']));
    
    // Verificar se o novo nome já existe
    $check_sql = "SELECT * FROM ga3_categorias WHERE nome = '$novo_nome' AND id != $categoria_id";
    $check_result = $conn->query($check_sql);
    
    if ($check_result->num_rows > 0) {
        $mensagem = "Já existe uma categoria com este nome!";
        $tipo_mensagem = "error";
    } else {
        $sql = "UPDATE ga3_categorias SET nome = '$novo_nome' WHERE id = $categoria_id";
        
        if ($conn->query($sql) === TRUE) {
            $mensagem = "Categoria atualizada com sucesso!";
            $tipo_mensagem = "success";
            
            // Registrar atividade
            if (function_exists('registrar_atividade')) {
                registrar_atividade($conn, $usuario['id'], "Atualizou categoria #$categoria_id para: $novo_nome");
            }
        } else {
            $mensagem = "Erro ao atualizar categoria: " . $conn->error;
            $tipo_mensagem = "error";
        }
    }
}

// Buscar todas as categorias
$sql = "SELECT c.*, COUNT(m.id) as total_produtos 
        FROM ga3_categorias c 
        LEFT JOIN ga3_materiais m ON c.id = m.categoria_id 
        GROUP BY c.id 
        ORDER BY c.nome";
$result = $conn->query($sql);
$categorias = array();

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $categorias[] = $row;
    }
}

// Contar total de categorias
$total_categorias = count($categorias);

// Verifica se a foto de perfil está definida, caso contrário, define uma padrão
if (!isset($usuario['foto_perfil']) || empty($usuario['foto_perfil'])) {
    $usuario['foto_perfil'] = '../uploads/basico.png';
} else {
    $usuario['foto_perfil'] = htmlspecialchars($usuario['foto_perfil']);
}

// Função para registrar atividade
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

// Fechar a conexão ao final
$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Categorias | Stockly</title>
    <link rel="stylesheet" href="../css/styleprincipal.css">
    <link rel="stylesheet" href="dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
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

/* ====== CARDS E CONTÊINERES ====== */
.card {
  background-color: #fff;
  border-radius: 8px;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  padding: 25px;
  margin-bottom: 25px;
  transition: all 0.3s ease;
}

.card:hover {
  transform: translateY(-5px);
  box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
  border-bottom: 1px solid var(--medium-gray);
  padding-bottom: 15px;
}

.card-header h2 {
  margin: 0;
  color: var(--text-color);
  font-weight: 600;
  font-size: 1.5rem;
}

.card-header .stats {
  background-color: rgba(42, 157, 143, 0.15);
  color: var(--primary-color);
  padding: 6px 12px;
  border-radius: 20px;
  font-weight: 500;
  font-size: 0.9rem;
}

/* ====== CATEGORIA ITEMS ====== */
.categoria-item {
  background-color: var(--light-gray);
  padding: 15px 20px;
  margin-bottom: 12px;
  border-radius: 8px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  transition: all 0.3s ease;
  border-left: 4px solid var(--primary-color);
}

.categoria-item:hover {
  background-color: rgba(42, 157, 143, 0.1);
  transform: translateX(5px);
}

.categoria-info {
  display: flex;
  align-items: center;
  flex: 1;
}

.categoria-nome {
  font-weight: 600;
  font-size: 1.1rem;
  color: var(--text-color);
  margin-right: 10px;
}

.categoria-stats {
  background-color: rgba(42, 157, 143, 0.1);
  color: var(--primary-color);
  font-size: 0.8rem;
  padding: 3px 8px;
  border-radius: 12px;
  margin-left: 10px;
  font-weight: 500;
}

.categoria-acoes {
  display: flex;
  gap: 10px;
}

/* ====== BOTÕES ====== */
.btn {
  padding: 8px 16px;
  border-radius: 4px;
  border: none;
  cursor: pointer;
  font-weight: 500;
  transition: all 0.3s ease;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 0.9rem;
}

.btn i {
  margin-right: 6px;
}

.btn-primary {
  background-color: var(--primary-color);
  color: #fff;
}

.btn-primary:hover {
  background-color: var(--primary-dark);
  transform: translateY(-2px);
}

.btn-danger {
  background-color: var(--danger-color);
  color: #fff;
}

.btn-danger:hover {
  background-color: #c0392b;
  transform: translateY(-2px);
}

.btn-edit {
  background-color: var(--warning-color);
  color: #fff;
  padding: 6px 10px;
  font-size: 0.8rem;
}

.btn-edit:hover {
  background-color: #e67e22;
}

.btn-delete {
  background-color: var(--danger-color);
  color: #fff;
  padding: 6px 10px;
  font-size: 0.8rem;
}

.btn-delete:hover {
  background-color: #c0392b;
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

/* ====== FORMULÁRIOS ====== */
.form-group {
  margin-bottom: 20px;
}

.form-control {
  width: 100%;
  padding: 12px 15px;
  border: 1px solid var(--medium-gray);
  border-radius: 4px;
  font-size: 1rem;
  transition: all 0.3s ease;
}

.form-control:focus {
  border-color: var(--primary-color);
  outline: none;
  box-shadow: 0 0 0 3px rgba(42, 157, 143, 0.2);
}

.input-group {
  display: flex;
  gap: 10px;
}

.input-group .form-control {
  flex: 3;
}

.input-group .btn {
  flex: 1;
  white-space: nowrap;
}

/* ====== MENSAGENS ====== */
.mensagem {
  padding: 15px;
  margin-bottom: 20px;
  border-radius: 8px;
  text-align: center;
  font-weight: 500;
  opacity: 0;
  animation: fadeIn 0.5s forwards;
}

.mensagem-success {
  background-color: rgba(42, 157, 143, 0.15);
  color: var(--primary-dark);
  border-left: 4px solid var(--primary-color);
}

.mensagem-error {
  background-color: #f8d7da;
  color: #721c24;
  border-left: 4px solid var(--danger-color);
}

/* ====== STATES ====== */
.empty-state {
  text-align: center;
  padding: 40px 20px;
  color: #6c757d;
}

.empty-state i {
  font-size: 4rem;
  margin-bottom: 20px;
  opacity: 0.4;
}

.empty-state p {
  font-size: 1.1rem;
  margin-bottom: 20px;
}

/* ====== MODAL ====== */
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
  background-color: #fff;
  margin: 10% auto;
  padding: 25px;
  border-radius: 8px;
  width: 80%;
  max-width: 500px;
  box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
  animation: modalOpen 0.3s ease;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
  padding-bottom: 15px;
  border-bottom: 1px solid var(--medium-gray);
}

.modal-header h3 {
  margin: 0;
  color: var(--text-color);
  font-weight: 600;
  font-size: 1.3rem;
}

.close-modal {
  font-size: 1.5rem;
  font-weight: bold;
  cursor: pointer;
  color: #6c757d;
  transition: all 0.3s ease;
}

.close-modal:hover {
  color: var(--text-color);
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 20px;
  padding-top: 15px;
  border-top: 1px solid var(--medium-gray);
}

/* ====== PESQUISA ====== */
.search-container {
  margin-bottom: 20px;
}

.search-input {
  width: 100%;
  padding: 12px 15px 12px 40px;
  border: 1px solid var(--medium-gray);
  border-radius: 4px;
  font-size: 1rem;
  background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="%236c757d" class="bi bi-search" viewBox="0 0 16 16"><path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z"/></svg>');
  background-repeat: no-repeat;
  background-position: 15px center;
  transition: all 0.3s ease;
}

.search-input:focus {
  border-color: var(--primary-color);
  outline: none;
  box-shadow: 0 0 0 3px rgba(42, 157, 143, 0.2);
}

.text-danger {
  color: var(--danger-color);
}


/* ====== RESPONSIVIDADE ====== */
@media (max-width: 768px) {
  .categoria-item {
    flex-direction: column;
    align-items: flex-start;
    gap: 10px;
  }
  
  .categoria-acoes {
    width: 100%;
    justify-content: flex-end;
  }
  
  .input-group {
    flex-direction: column;
  }
  
  .input-group .form-control,
  .input-group .btn {
    width: 100%;
  }
}

/* ====== ANIMAÇÕES ====== */
@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(-10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes modalOpen {
  from {
    opacity: 0;
    transform: translateY(-30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes pulse {
  0% { transform: scale(1); }
  50% { transform: scale(1.05); }
  100% { transform: scale(1); }
}

.animate-pulse {
  animation: pulse 2s infinite;
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
    <div class="content-wrapper">
        <h1>Gerenciar Categorias</h1>
        
        <!-- Simulação de mensagem - removeria o PHP aqui -->
        <?php if (!empty($mensagem)): ?>
    <div class="mensagem <?php echo $tipo_mensagem == 'success' ? 'mensagem-success' : 'mensagem-error'; ?>">
        <i class="fas <?php echo $tipo_mensagem == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i> 
        <?php echo htmlspecialchars($mensagem); ?>
    </div>
<?php endif; ?>
        
        <div class="categorias-container">
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-plus-circle"></i> Adicionar Nova Categoria</h2>
                </div>
                <form method="post" action="">
                    <div class="input-group">
                        <input type="text" name="nova_categoria" class="form-control" placeholder="Nome da categoria" required>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Adicionar</button>
                    </div>
                </form>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-tags"></i> Categorias Existentes</h2>
                    <span class="stats"><?php echo $total_categorias; ?> <?php echo $total_categorias == 1 ? 'categoria' : 'categorias'; ?></span>
                </div>
                
                <?php if (!empty($categorias)): ?>
                    <div class="search-container">
                        <input type="text" id="searchCategoria" class="search-input" placeholder="Buscar categoria..." onkeyup="filterCategorias()">
                    </div>
                    
                    <div id="categorias-lista">
                        <?php foreach ($categorias as $categoria): ?>
                            <div class="categoria-item">
                                <div class="categoria-info">
                                    <span class="categoria-nome"><?php echo htmlspecialchars($categoria['nome']); ?></span>
                                    <span class="categoria-stats">
                                        <i class="fas fa-box"></i> <?php echo $categoria['total_produtos']; ?> produto<?php echo $categoria['total_produtos'] != 1 ? 's' : ''; ?>
                                    </span>
                                </div>
                                <div class="categoria-acoes">
                                    <button class="btn btn-edit" onclick="abrirModalEditar(<?php echo $categoria['id']; ?>, '<?php echo htmlspecialchars($categoria['nome']); ?>')">
                                        <i class="fas fa-edit"></i> Editar
                                    </button>
                                    <?php if ($categoria['total_produtos'] == 0): ?>
                                        <button class="btn btn-delete" onclick="confirmarExclusao(<?php echo $categoria['id']; ?>, '<?php echo htmlspecialchars($categoria['nome']); ?>')">
                                            <i class="fas fa-trash-alt"></i> Excluir
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-delete" disabled title="Esta categoria possui produtos associados">
                                            <i class="fas fa-trash-alt"></i> Excluir
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-folder-open"></i>
                        <p>Nenhuma categoria cadastrada.</p>
                        <button class="btn btn-primary animate-pulse">Crie sua primeira categoria</button>
                    </div>
                <?php endif; ?>
            </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal de Edição -->
    <div id="modalEditar" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Editar Categoria</h3>
                <span class="close-modal" onclick="fecharModal('modalEditar')">&times;</span>
            </div>
            <form method="post" action="">
                <div class="form-group">
                    <label for="editar_categoria">Nome da Categoria:</label>
                    <input type="text" id="editar_categoria" name="editar_categoria" class="form-control" required>
                    <input type="hidden" id="categoria_id" name="categoria_id">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" onclick="fecharModal('modalEditar')">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal de Confirmação de Exclusão -->
    <div id="modalExcluir" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-trash-alt"></i> Confirmar Exclusão</h3>
                <span class="close-modal" onclick="fecharModal('modalExcluir')">&times;</span>
            </div>
            <p>Tem certeza que deseja excluir a categoria <strong id="nome_categoria_excluir"></strong>?</p>
            <p class="text-danger"><small>Esta ação não pode ser desfeita.</small></p>
            <form method="post" action="">
                <input type="hidden" id="excluir_categoria_id" name="excluir_categoria">
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" onclick="fecharModal('modalExcluir')">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Confirmar Exclusão</button>
                </div>
            </form>
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
        // Função para busca dinâmica
        function filterCategorias() {
            var input, filter, categoriasDiv, categoriaItems, txtValue;
            input = document.getElementById('searchCategoria');
            filter = input.value.toUpperCase();
            categoriasDiv = document.getElementById('categorias-lista');
            categoriaItems = categoriasDiv.getElementsByClassName('categoria-item');
            
            for (var i = 0; i < categoriaItems.length; i++) {
                txtValue = categoriaItems[i].getElementsByClassName('categoria-nome')[0].textContent || categoriaItems[i].getElementsByClassName('categoria-nome')[0].innerText;
                if (txtValue.toUpperCase().indexOf(filter) > -1) {
                    categoriaItems[i].style.display = "";
                } else {
                    categoriaItems[i].style.display = "none";
                }
            }
        }
        
        // Função para abrir modal de edição
        function abrirModalEditar(id, nome) {
            document.getElementById('categoria_id').value = id;
            document.getElementById('editar_categoria').value = nome;
            document.getElementById('modalEditar').style.display = 'block';
        }
        
        // Função para confirmar exclusão
        function confirmarExclusao(id, nome) {
            document.getElementById('excluir_categoria_id').value = id;
            document.getElementById('nome_categoria_excluir').textContent = nome;
            document.getElementById('modalExcluir').style.display = 'block';
        }
        
        // Função para fechar modais
        function fecharModal(id) {
            document.getElementById(id).style.display = 'none';
        }
        
        // Fechar modal ao clicar fora dela
        window.onclick = function(event) {
            var modals = document.getElementsByClassName('modal');
            for (var i = 0; i < modals.length; i++) {
                if (event.target == modals[i]) {
                    modals[i].style.display = "none";
                }
            }
        }
        
        // Adicionar classe "active" ao menu atual
        document.addEventListener('DOMContentLoaded', function() {
            var currentPage = 'gerenciar_categorias';
            var navLinks = document.querySelectorAll('.nav-link');
            
            navLinks.forEach(function(link) {
                if (link.getAttribute('data-page') === currentPage) {
                    link.classList.add('active');
                }
            });
            
            // Definir foco no campo de busca ao digitar
            var searchInput = document.getElementById('searchCategoria');
            if (searchInput) {
                document.addEventListener('keydown', function(e) {
                    if (e.keyCode === 191 && !e.target.closest('input, textarea')) { // Tecla '/'
                        e.preventDefault();
                        searchInput.focus();
                    }
                });
            }
            
            // Animação de feedback para mensagens
            var mensagem = document.querySelector('.mensagem');
            if (mensagem) {
                setTimeout(function() {
                    mensagem.style.opacity = '0';
                    mensagem.style.transform = 'translateY(-10px)';
                    setTimeout(function() {
                        mensagem.style.display = 'none';
                    }, 500);
                }, 5000);
            }
        });


        
    </script>

</body>
</html>