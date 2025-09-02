<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ga3_stockly";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Lógica para buscar dados com base no período
$period = isset($_GET['period']) ? intval($_GET['period']) : 30; // Padrão para 30 dias

// Definir a data de início e fim com base no período
$startDate = date('Y-m-d', strtotime("-$period days"));
$endDate = date('Y-m-d');

// Obter estatísticas gerais
$sql_estoque = "SELECT 
                SUM(quantidade * valor_unitario_estoque) as valor_total_estoque,
                SUM(quantidade) as total_itens,
                COUNT(*) as total_produtos,
                COUNT(CASE WHEN quantidade < 5 THEN 1 END) as produtos_baixo_estoque
                FROM ga3_materiais";
$result_estoque = $conn->query($sql_estoque);
$estoque_stats = $result_estoque->fetch_assoc();

// Obter dados de vendas com base no período
$sql_vendas_mes = "SELECT 
                    COUNT(*) as total_transacoes,
                    SUM(valor_venda) as receita_total,
                    SUM(valor_venda - custo_material) as lucro_bruto,
                    SUM(quantidade) as total_quantidade_vendida
                    FROM ga3_transacoes 
                    WHERE data_hora BETWEEN '$startDate 00:00:00' AND '$endDate 23:59:59'";
$result_vendas_mes = $conn->query($sql_vendas_mes);
$vendas_mes = $result_vendas_mes->fetch_assoc();

// Obter dados para o gráfico de vendas dos últimos dias
$sql_vendas_diarias = "SELECT 
                       DATE(data_hora) as data,
                       SUM(valor_venda) as total_vendas,
                       SUM(quantidade) as total_quantidade_vendida,
                       COUNT(*) as total_transacoes
                       FROM ga3_transacoes
                       WHERE data_hora BETWEEN '$startDate 00:00:00' AND '$endDate 23:59:59'
                       GROUP BY DATE(data_hora)
                       ORDER BY data";
$result_vendas_diarias = $conn->query($sql_vendas_diarias);
$vendas_diarias = [];
while ($row = $result_vendas_diarias->fetch_assoc()) {
    $vendas_diarias[] = $row;
}

// Obter produtos mais vendidos
$sql_top_produtos = "SELECT 
                    m.descricao, 
                    m.codigo_identificacao,
                    SUM(t.quantidade) as total_vendido,
                    SUM(t.valor_venda) as valor_total
                    FROM ga3_transacoes t
                    JOIN ga3_materiais m ON t.material_id = m.id
                    WHERE t.data_hora BETWEEN '$startDate 00:00:00' AND '$endDate 23:59:59'
                    GROUP BY t.material_id
                    ORDER BY total_vendido DESC
                    LIMIT 5";
$result_top_produtos = $conn->query($sql_top_produtos);
$top_produtos = [];
while ($row = $result_top_produtos->fetch_assoc()) {
    $top_produtos[] = $row;
}

// Obter produtos com estoque baixo
$sql_estoque_baixo = "SELECT 
                      id, 
                      descricao, 
                      codigo_identificacao, 
                      quantidade, 
                      valor_unitario_estoque
                      FROM ga3_materiais
                      WHERE quantidade < 5
                      ORDER BY quantidade ASC
                      LIMIT 10";
$result_estoque_baixo = $conn->query($sql_estoque_baixo);
$produtos_estoque_baixo = [];
while ($row = $result_estoque_baixo->fetch_assoc()) {
    $produtos_estoque_baixo[] = $row;
}

// Obter dados para o gráfico de categorias
$sql_categorias = "SELECT 
                  c.nome as categoria,
                  COUNT(m.id) as total_produtos,
                  SUM(m.quantidade) as total_itens,
                  SUM(m.quantidade * m.valor_unitario_estoque) as valor_total
                  FROM ga3_materiais m
                  LEFT JOIN ga3_categorias c ON m.categoria_id = c.id
                  GROUP BY m.categoria_id
                  ORDER BY valor_total DESC";
$result_categorias = $conn->query($sql_categorias);
$categorias = [];
while ($row = $result_categorias->fetch_assoc()) {
    $categorias[] = $row;
}

// Fechar conexão
$conn->close();

// Formatar dados para JSON (para os gráficos)
$vendas_diarias_json = json_encode($vendas_diarias);
$categorias_json = json_encode($categorias);
$top_produtos_json = json_encode($top_produtos);

// Criar um array com todos os dados para passar para o frontend
$dashboard_data = [
    'estoque_stats' => $estoque_stats,
    'vendas_mes' => $vendas_mes,
    'vendas_diarias' => $vendas_diarias,
    'produtos_estoque_baixo' => $produtos_estoque_baixo,
    'top_produtos' => $top_produtos,
    'categorias' => $categorias,
    'data_atual' => date('d/m/Y')
];

// Se for uma requisição AJAX, retornar os dados como JSON
if(isset($_GET['ajax']) && $_GET['ajax'] == 1) {
    header('Content-Type: application/json');
    echo json_encode($dashboard_data);
    exit;
}

// Retornar os dados em formato JSON
header('Content-Type: application/json');
echo json_encode($dashboard_data);
?>