<?php
// Conexão com o banco de dados
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ga3_stockly";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Calcular a receita total das vendas
$sql_receita = "SELECT SUM(valor_venda) as receita FROM ga3_transacoes";
$result_receita = $conn->query($sql_receita);
$receita = 0;

if ($result_receita->num_rows > 0) {
    $row = $result_receita->fetch_assoc();
    $receita = $row['receita'];
}

// Calcular o custo total dos materiais vendidos
$sql_custo = "SELECT SUM(custo_material) as custo FROM ga3_transacoes";
$result_custo = $conn->query($sql_custo);
$custo = 0;

if ($result_custo->num_rows > 0) {
    $row = $result_custo->fetch_assoc();
    $custo = $row['custo'];
}

// Calcular a despesa total
$sql_despesa = "SELECT SUM(valor) as despesa FROM ga3_despesas";
$result_despesa = $conn->query($sql_despesa);
$despesa = 0;

if ($result_despesa->num_rows > 0) {
    $row = $result_despesa->fetch_assoc();
    $despesa = $row['despesa'];
}

// Calcular o lucro
$lucro = $receita - $despesa;

// Calcular o valor total em estoque
$sql_estoque = "SELECT SUM(preco_unitario * quantidade) as valor_total_estoque FROM ga3_produtos";
$result_estoque = $conn->query($sql_estoque);
$valor_total_estoque = 0;

if ($result_estoque->num_rows > 0) {
    $row = $result_estoque->fetch_assoc();
    $valor_total_estoque = $row['valor_total_estoque'];
}

// Prepare os dados para o dashboard
$estoque_stats = [
    'valor_total_estoque' => $valor_total_estoque,
];

// Retornar o lucro como JSON (se necessário)
echo json_encode(array('lucro' => $lucro));

// Fechar a conexão
$conn->close();
?>

<!-- HTML do Dashboard -->
<div class="dashboard-grid">
    <div class="dashboard-card">
        <div class="card-header">
            <h3 class="card-title">Valor em Estoque</h3>
            <div class="card-icon icon-primary">
                <i class="fas fa-dollar-sign"></i>
            </div>
        </div>
        <h2 class="card-value" id="valor-estoque">R$ <?php echo number_format($estoque_stats['valor_total_estoque'], 2, ',', '.'); ?></h2>
        <p class="card-subtitle" id="estoque-variacao">
            <span class="trend-up" id="estoque-variacao-perc">0%</span> em relação ao período anterior
        </p>
    </div>
</div>