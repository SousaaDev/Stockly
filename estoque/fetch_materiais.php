<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ga3_stockly";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}

// Verificar se há parâmetros de busca
$search = isset($_GET['search']) ? $_GET['search'] : '';
$categoria_id = isset($_GET['categoria_id']) ? $_GET['categoria_id'] : '';
$ordenacao = isset($_GET['ordenacao']) ? $_GET['ordenacao'] : '';

$sql = "SELECT m.id, m.descricao, m.quantidade, m.valor_unitario_estoque, m.valor_unitario_venda_estimado, m.categoria_id, c.nome as categoria_nome, m.codigo_identificacao 
        FROM ga3_materiais m 
        LEFT JOIN ga3_categorias c ON m.categoria_id = c.id";

// Adicionar condições de busca
$where_conditions = [];

if (!empty($search)) {
    $search = $conn->real_escape_string($search);
    $where_conditions[] = "(m.descricao LIKE '%$search%' OR m.codigo_identificacao LIKE '%$search%')";
}

if (!empty($categoria_id)) {
    $categoria_id = $conn->real_escape_string($categoria_id);
    $where_conditions[] = "m.categoria_id = '$categoria_id'";
}

// Adicionar WHERE se houver condições
if (!empty($where_conditions)) {
    $sql .= " WHERE " . implode(" AND ", $where_conditions);
}

// Adicionar ordenação com base no parâmetro
if (!empty($ordenacao)) {
    switch ($ordenacao) {
        case 'nome_asc':
            $sql .= " ORDER BY m.descricao ASC";
            break;
        case 'nome_desc':
            $sql .= " ORDER BY m.descricao DESC";
            break;
        case 'preco_asc':
            $sql .= " ORDER BY m.valor_unitario_estoque ASC";
            break;
        case 'preco_desc':
            $sql .= " ORDER BY m.valor_unitario_estoque DESC";
            break;
        case 'qtd_asc':
            $sql .= " ORDER BY m.quantidade ASC";
            break;
        case 'qtd_desc':
            $sql .= " ORDER BY m.quantidade DESC";
            break;
        case 'codigo_asc':
            $sql .= " ORDER BY m.codigo_identificacao ASC";
            break;
        case 'codigo_desc':
            $sql .= " ORDER BY m.codigo_identificacao DESC";
            break;
    }
}

$result = $conn->query($sql);

$materiais = array();

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $materiais[] = $row;
    }
}

echo json_encode($materiais);

$conn->close();
?>