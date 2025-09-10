<?php
// Função para calcular a variação percentual entre dois valores
// Substitua a função calcularVariacao no dashboard_data.php
function calcularVariacao($valor_atual, $valor_anterior) {
    // Garantir que ambos os valores sejam numéricos
    $valor_atual = floatval($valor_atual);
    $valor_anterior = floatval($valor_anterior);
    
    // Evitar divisão por zero
    if (empty($valor_anterior) || $valor_anterior == 0) return 0;
    
    // Calcular a variação percentual
    $variacao = (($valor_atual - $valor_anterior) / $valor_anterior) * 100;
    
    // Arredondar para uma casa decimal
    return round($variacao, 1);
}

// Garantir que um valor é numérico e não nulo
function garantirValorNumerico($valor) {
    if ($valor === null || $valor === '' || !is_numeric($valor)) {
        return 0;
    }
    return floatval($valor);
}

// Função para obter o início e fim do período baseado no filtro
function getIntervaloDatas($periodo) {
    $hoje = date('Y-m-d');
    $inicio = '';
    $fim = $hoje;
    
    switch ($periodo) {
        case 'dia':
            $inicio = $hoje;
            break;
        case 'semana':
            $inicio = date('Y-m-d', strtotime('monday this week'));
            break;
        case 'mes':
            $inicio = date('Y-m-01');
            break;
        case 'ano':
            $inicio = date('Y-01-01');
            break;
        default:
            $inicio = date('Y-m-01'); // Padrão: mês atual
    }
    
    return ['inicio' => $inicio, 'fim' => $fim];
}

// Função para obter o mesmo período, mas no período anterior (mês anterior, semana anterior, etc)
function getPeriodoAnterior($periodo) {
    $hoje = date('Y-m-d');
    $inicio = '';
    $fim = '';
    
    switch ($periodo) {
        case 'dia':
            $inicio = date('Y-m-d', strtotime('-1 day'));
            $fim = $inicio;
            break;
        case 'semana':
            $inicio = date('Y-m-d', strtotime('monday last week'));
            $fim = date('Y-m-d', strtotime('sunday last week'));
            break;
        case 'mes':
            $inicio = date('Y-m-01', strtotime('first day of last month'));
            $fim = date('Y-m-t', strtotime('last day of last month'));
            break;
        case 'ano':
            $inicio = date('Y-01-01', strtotime('-1 year'));
            $fim = date('Y-12-31', strtotime('-1 year'));
            break;
        default:
            $inicio = date('Y-m-01', strtotime('first day of last month'));
            $fim = date('Y-m-t', strtotime('last day of last month'));
    }
    
    return ['inicio' => $inicio, 'fim' => $fim];
}

// Conexão com o banco de dados
$conn = new mysqli('localhost', 'root', '', 'ga3_stockly');

if ($conn->connect_error) {
    die("Conexão falhou: " . $conn->connect_error);
}

// Obter o período atual do filtro
$periodo_atual = isset($_GET['periodo']) ? $_GET['periodo'] : 'mes';

// Obter intervalo de datas para o período atual
$intervalo = getIntervaloDatas($periodo_atual);
$data_inicio = $intervalo['inicio'];
$data_fim = $intervalo['fim'];

// Obter intervalo de datas para o período anterior
$intervalo_anterior = getPeriodoAnterior($periodo_atual);
$data_inicio_anterior = $intervalo_anterior['inicio'];
$data_fim_anterior = $intervalo_anterior['fim'];

// 1. Estatísticas do Estoque
$sql_estoque = "SELECT 
                    COUNT(*) as total_itens,
                    SUM(quantidade) as total_quantidade,
                    SUM(quantidade * valor_unitario_estoque) as valor_total_estoque
                FROM ga3_materiais";
$result_estoque = $conn->query($sql_estoque);
$estoque_stats = $result_estoque->fetch_assoc();

// Valor do estoque no período anterior (mês passado)
$sql_estoque_anterior = "SELECT 
                            SUM(quantidade * valor_unitario_estoque) as valor_total_estoque
                         FROM ga3_materiais";
$result_estoque_anterior = $conn->query($sql_estoque_anterior);
$estoque_anterior = $result_estoque_anterior->fetch_assoc();

// Calcular variação percentual do estoque
$variacao_estoque = calcularVariacao(
    $estoque_stats['valor_total_estoque'], 
    $estoque_anterior['valor_total_estoque']
);

// 2. Estatísticas de Vendas do Período Atual
// Substitua a consulta SQL de vendas no dashboard_data.php
$sql_vendas = "SELECT 
                  COALESCE(SUM(valor_venda), 0) as receita_total,
                  COALESCE(SUM(lucro_bruto), 0) as lucro_bruto,
                  COUNT(*) as total_vendas
               FROM ga3_transacoes 
               WHERE data_venda BETWEEN ? AND ?";
$stmt_vendas = $conn->prepare($sql_vendas);
$stmt_vendas->bind_param("ss", $data_inicio, $data_fim);
$stmt_vendas->execute();
$result_vendas = $stmt_vendas->get_result();
$vendas_mes = $result_vendas->fetch_assoc();

// 3. Estatísticas de Vendas do Período Anterior
$sql_vendas_anterior = "SELECT 
                           SUM(valor_venda) as receita_total,
                           SUM(lucro_bruto) as lucro_bruto
                        FROM ga3_transacoes 
                        WHERE data_venda BETWEEN ? AND ?";
$stmt_vendas_anterior = $conn->prepare($sql_vendas_anterior);
$stmt_vendas_anterior->bind_param("ss", $data_inicio_anterior, $data_fim_anterior);
$stmt_vendas_anterior->execute();
$result_vendas_anterior = $stmt_vendas_anterior->get_result();
$vendas_anterior = $result_vendas_anterior->fetch_assoc();

// Calcular variações percentuais
$variacao_vendas = calcularVariacao(
    $vendas_mes['receita_total'], 
    $vendas_anterior['receita_total']
);

$variacao_lucro = calcularVariacao(
    $vendas_mes['lucro_bruto'], 
    $vendas_anterior['lucro_bruto']
);

// 4. Variação no número de itens em estoque (comparado ao mês anterior)
$sql_itens_anterior = "SELECT COUNT(*) as total_itens FROM ga3_materiais";
$result_itens_anterior = $conn->query($sql_itens_anterior);
$itens_anterior = $result_itens_anterior->fetch_assoc();

$variacao_itens = calcularVariacao(
    $estoque_stats['total_itens'], 
    $itens_anterior['total_itens']
);

// 5. Alertas de Estoque Baixo (produtos com quantidade menor que 10)
$sql_estoque_baixo = "SELECT * FROM ga3_materiais WHERE quantidade < 10 ORDER BY quantidade ASC LIMIT 5";
$result_estoque_baixo = $conn->query($sql_estoque_baixo);
$produtos_estoque_baixo = [];

while ($row = $result_estoque_baixo->fetch_assoc()) {
    $produtos_estoque_baixo[] = $row;
}

// 6. Top categorias por valor em estoque
$sql_categorias = "SELECT 
                      c.nome as categoria,
                      COUNT(m.id) as total_produtos,
                      SUM(m.quantidade) as total_itens,
                      SUM(m.quantidade * m.valor_unitario_estoque) as valor_total
                   FROM ga3_materiais m
                   JOIN ga3_categorias c ON m.categoria_id = c.id
                   GROUP BY c.id
                   ORDER BY valor_total DESC";
$result_categorias = $conn->query($sql_categorias);
$categorias = [];

while ($row = $result_categorias->fetch_assoc()) {
    $categorias[] = $row;
}

// 7. Dados para os gráficos do dashboard
$dashboard_data = [
    'vendas_diarias' => [],
    'top_produtos' => [],
    'categorias' => []
];

// Vendas diárias dos últimos 30 dias
$sql_vendas_diarias = "SELECT 
                          DATE(data_venda) as data,
                          SUM(valor_venda) as total_vendas,
                          SUM(quantidade) as total_itens
                       FROM ga3_transacoes
                       WHERE data_venda >= DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY)
                       GROUP BY DATE(data_venda)
                       ORDER BY data_venda";
$result_vendas_diarias = $conn->query($sql_vendas_diarias);

while ($row = $result_vendas_diarias->fetch_assoc()) {
    $dashboard_data['vendas_diarias'][] = [
        'data' => date('d/m', strtotime($row['data'])),
        'total_vendas' => (float)$row['total_vendas'],
        'total_itens' => (int)$row['total_itens']
    ];
}

// Top 5 produtos mais vendidos
$sql_top_produtos = "SELECT 
                        m.descricao,
                        SUM(t.quantidade) as total_vendido,
                        SUM(t.valor_venda) as valor_total
                     FROM ga3_transacoes t
                     JOIN ga3_materiais m ON t.material_id = m.id
                     WHERE t.data_venda BETWEEN ? AND ?
                     GROUP BY m.id
                     ORDER BY total_vendido DESC
                     LIMIT 5";
$stmt_top_produtos = $conn->prepare($sql_top_produtos);
$stmt_top_produtos->bind_param("ss", $data_inicio, $data_fim);
$stmt_top_produtos->execute();
$result_top_produtos = $stmt_top_produtos->get_result();

while ($row = $result_top_produtos->fetch_assoc()) {
    $dashboard_data['top_produtos'][] = [
        'descricao' => $row['descricao'],
        'total_vendido' => (int)$row['total_vendido'],
        'valor_total' => (float)$row['valor_total']
    ];
}

// Distribuição por categoria
$sql_categorias_chart = "SELECT 
                            c.nome as categoria,
                            SUM(m.quantidade * m.valor_unitario_estoque) as valor_total
                         FROM ga3_materiais m
                         JOIN ga3_categorias c ON m.categoria_id = c.id
                         GROUP BY c.id
                         ORDER BY valor_total DESC";

$result_categorias_chart = $conn->query($sql_categorias_chart);

while ($row = $result_categorias_chart->fetch_assoc()) {
    $dashboard_data['categorias'][] = [
        'categoria' => $row['categoria'],
        'valor_total' => (float)$row['valor_total']
    ];
}

// Se algum dos arrays estiver vazio, adicione dados fictícios para evitar erros nos gráficos
if (empty($dashboard_data['vendas_diarias'])) {
    for ($i = 0; $i < 30; $i++) {
        $dashboard_data['vendas_diarias'][] = [
            'data' => date('d/m', strtotime("-$i days")),
            'total_vendas' => 0,
            'total_itens' => 0
        ];
    }
}

if (empty($dashboard_data['top_produtos'])) {
    $dashboard_data['top_produtos'] = [
        ['descricao' => 'Sem vendas', 'total_vendido' => 0, 'valor_total' => 0]
    ];
}

if (empty($dashboard_data['categorias'])) {
    $dashboard_data['categorias'] = [
        ['categoria' => 'Sem categorias', 'valor_total' => 0]
    ];
}

// Garantir que não temos valores nulos nos principais indicadores
$estoque_stats['valor_total_estoque'] = $estoque_stats['valor_total_estoque'] ?? 0;
$estoque_stats['total_itens'] = $estoque_stats['total_itens'] ?? 0;
$vendas_mes['receita_total'] = $vendas_mes['receita_total'] ?? 0;
$vendas_mes['lucro_bruto'] = $vendas_mes['lucro_bruto'] ?? 0;

// Arredondar as variações percentuais para 1 casa decimal
$variacao_estoque = round($variacao_estoque, 1);
$variacao_vendas = round($variacao_vendas, 1);
$variacao_lucro = round($variacao_lucro, 1);
$variacao_itens = round($variacao_itens, 1);

// Converter os valores NULL para 0
// Modificar o código existente no final do arquivo dashboard_data.php
$estoque_stats['valor_total_estoque'] = garantirValorNumerico($estoque_stats['valor_total_estoque']);
$estoque_stats['total_itens'] = garantirValorNumerico($estoque_stats['total_itens']);
$vendas_mes['receita_total'] = garantirValorNumerico($vendas_mes['receita_total']);
$vendas_mes['lucro_bruto'] = garantirValorNumerico($vendas_mes['lucro_bruto']);

// Criar variável $produtos_vendidos para compatibilidade com dashboard.php
$produtos_vendidos = $dashboard_data['top_produtos'];

// Filtrar produtos vendidos para remover entradas vazias ou sem vendas
$produtos_vendidos = array_filter($produtos_vendidos, function($produto) {
    return isset($produto['total_vendido']) && $produto['total_vendido'] > 0;
});

// Garantir que $produtos_vendidos não seja null
if (!is_array($produtos_vendidos)) {
    $produtos_vendidos = [];
}

// Fechar as conexões
$stmt_vendas->close();
$stmt_vendas_anterior->close();
$stmt_top_produtos->close();
$conn->close();
?>