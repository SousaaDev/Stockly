<?php
// gerenciar_categorias_ajax.php
session_start();
include 'dashboard_data.php'; // Inclui configurações necessárias

// Verifica se o usuário está logado
if (!isset($_SESSION['usuario'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Usuário não autenticado']);
    exit;
}

// Conexão com o banco de dados
$conn = new mysqli('localhost', 'root', '', 'ga3_stockly');

if ($conn->connect_error) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Erro de conexão: ' . $conn->connect_error]);
    exit;
}

// Adicionar nova categoria
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'adicionar') {
    $nome_categoria = trim($_POST['nome_categoria']);
    
    // Validação básica
    if (empty($nome_categoria)) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Nome da categoria não pode estar vazio']);
        exit;
    }
    
    // Verificar se já existe uma categoria com este nome
    $stmt = $conn->prepare("SELECT id FROM ga3_categorias WHERE nome = ?");
    $stmt->bind_param("s", $nome_categoria);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error', 
            'message' => 'Já existe uma categoria com este nome',
            'categoria_id' => $row['id']
        ]);
        exit;
    }
    
    // Inserir nova categoria
    $stmt = $conn->prepare("INSERT INTO ga3_categorias (nome) VALUES (?)");
    $stmt->bind_param("s", $nome_categoria);
    
    if ($stmt->execute()) {
        $categoria_id = $conn->insert_id;
        
        // Registrar atividade
        $usuario_id = $_SESSION['usuario']['id'];
        $atividade = "Adicionou categoria: " . $nome_categoria;
        
        $stmt_atividade = $conn->prepare("INSERT INTO ga3_atividades (usuario_id, atividade) VALUES (?, ?)");
        $stmt_atividade->bind_param("is", $usuario_id, $atividade);
        $stmt_atividade->execute();
        
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success', 
            'message' => 'Categoria adicionada com sucesso',
            'categoria_id' => $categoria_id
        ]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Erro ao adicionar categoria: ' . $stmt->error]);
    }
    
    exit;
}

// Se chegou aqui, é porque a ação solicitada não é válida
header('Content-Type: application/json');
echo json_encode(['status' => 'error', 'message' => 'Ação inválida']);