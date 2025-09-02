<?php
session_start();

if (!isset($_SESSION['usuario'])) {
    header('HTTP/1.1 401 Unauthorized');
    exit(json_encode(['error' => 'Acesso não autorizado']));
}

// Conexão com o banco de dados
$conn = new mysqli('localhost', 'root', '', 'ga3_stockly');

if ($conn->connect_error) {
    header('HTTP/1.1 500 Internal Server Error');
    exit(json_encode(['error' => 'Erro na conexão com o banco de dados']));
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    header('HTTP/1.1 400 Bad Request');
    exit(json_encode(['error' => 'ID de material inválido']));
}

// Buscar detalhes do material
$sql = "SELECT id, descricao, codigo_identificacao, valor_unitario_estoque, valor_unitario_venda_estimado 
        FROM ga3_materiais 
        WHERE id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('HTTP/1.1 404 Not Found');
    exit(json_encode(['error' => 'Material não encontrado']));
}

$material = $result->fetch_assoc();
$stmt->close();
$conn->close();

// Retornar os detalhes como JSON
header('Content-Type: application/json');
echo json_encode($material);
?>