<?php
// Conexão com o banco de dados
$conn = new mysqli('localhost', 'root', '', 'ga3_stockly');

if ($conn->connect_error) {
    die("Conexão falhou: " . $conn->connect_error);
}

// Obter dados do formulário
$material_id = $_POST['material'];
$quantidade = $_POST['quantidade_venda'];
$valor_unitario_venda = $_POST['valor_unitario_venda'];

// Calcular valor total da venda
$valor_venda = $quantidade * $valor_unitario_venda;

// Obter o custo unitário do material
$stmt = $conn->prepare("SELECT valor_unitario_estoque FROM ga3_materiais WHERE id = ?");
$stmt->bind_param("i", $material_id);
$stmt->execute();
$result = $stmt->get_result();
$material = $result->fetch_assoc();
$custo_unitario = $material['valor_unitario_estoque'];

// Calcular custo total do material
$custo_material = $quantidade * $custo_unitario;

// Data e hora atual
$data_hora = date('Y-m-d H:i:s');
$data_venda = date('Y-m-d');

// Inserir a transação
$stmt = $conn->prepare("INSERT INTO ga3_transacoes (quantidade, material_id, valor_venda, custo_material, data_hora, data_venda) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->bind_param("iiddss", $quantidade, $material_id, $valor_venda, $custo_material, $data_hora, $data_venda);

if ($stmt->execute()) {
    // Calcular lucro bruto
    $lucro_bruto = $valor_venda - $custo_material;
    
    // Verificar se já existe um registro para essa data na tabela vendas
    $check_stmt = $conn->prepare("SELECT id, receita, lucro_bruto FROM ga3_vendas WHERE data_venda = ?");
    $check_stmt->bind_param("s", $data_venda);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        // Atualizar o registro existente
        $venda = $check_result->fetch_assoc();
        $nova_receita = $venda['receita'] + $valor_venda;
        $novo_lucro = $venda['lucro_bruto'] + $lucro_bruto;
        
        $update_stmt = $conn->prepare("UPDATE ga3_vendas SET receita = ?, lucro_bruto = ? WHERE id = ?");
        $update_stmt->bind_param("ddi", $nova_receita, $novo_lucro, $venda['id']);
        $update_stmt->execute();
        $update_stmt->close();
    } else {
        // Inserir um novo registro
        $insert_stmt = $conn->prepare("INSERT INTO ga3_vendas (data_venda, receita, lucro_bruto) VALUES (?, ?, ?)");
        $insert_stmt->bind_param("sdd", $data_venda, $valor_venda, $lucro_bruto);
        $insert_stmt->execute();
        $insert_stmt->close();
    }
    $check_stmt->close();
    
    // Atualizar a quantidade no estoque
    $update_estoque = $conn->prepare("UPDATE ga3_materiais SET quantidade = quantidade - ? WHERE id = ?");
    $update_estoque->bind_param("ii", $quantidade, $material_id);
    $update_estoque->execute();
    $update_estoque->close();
    
    echo "Transação registrada com sucesso!";
} else {
    echo "Erro ao registrar transação: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>