<?php
session_start();

if (!isset($_SESSION['usuario'])) {
    header('HTTP/1.1 401 Unauthorized');
    exit('Acesso não autorizado');
}

// Conexão com o banco de dados
$conn = new mysqli('localhost', 'root', '', 'ga3_stockly');

if ($conn->connect_error) {
    header('HTTP/1.1 500 Internal Server Error');
    exit('Erro na conexão com o banco de dados');
}

$term = isset($_GET['term']) ? $_GET['term'] : '';

if (empty($term) || strlen($term) < 2) {
    echo json_encode([]);
    exit();
}

// Escapar o termo de pesquisa para evitar injeção SQL
$term = '%' . $conn->real_escape_string($term) . '%';

// Consulta para buscar materiais que correspondem ao termo
$sql = "SELECT id, descricao, codigo_identificacao, quantidade 
        FROM ga3_materiais 
        WHERE descricao LIKE ? OR codigo_identificacao LIKE ? 
        ORDER BY descricao 
        LIMIT 10";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $term, $term);
$stmt->execute();
$result = $stmt->get_result();

$materiais = [];
while ($row = $result->fetch_assoc()) {
    $materiais[] = $row;
}

$stmt->close();
$conn->close();

// Retornar os resultados como JSON
header('Content-Type: application/json');
echo json_encode($materiais);
?>