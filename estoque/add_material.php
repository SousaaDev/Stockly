<?php
session_start();
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ga3_stockly";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die(json_encode(["success" => false, "message" => "Falha na conexão: " . $conn->connect_error]));
}

if (!isset($_SESSION['usuario'])) {
    die(json_encode(["success" => false, "message" => "Usuário não autenticado"]));
}

if (!isset($_POST['descricao']) || empty(trim($_POST['descricao']))) {
    die(json_encode(["success" => false, "message" => "Descrição do material é obrigatória"]));
}

$descricao = trim($_POST['descricao']);
$quantidade = isset($_POST['quantidade']) ? intval($_POST['quantidade']) : 0;
$valor_unitario = isset($_POST['valor_unitario']) ? floatval($_POST['valor_unitario']) : 0;
$valor_unitario_venda_estimado = isset($_POST['valor_unitario_venda_estimado']) ? floatval($_POST['valor_unitario_venda_estimado']) : 0;
$categoria_id = isset($_POST['categoria_id']) && !empty($_POST['categoria_id']) ? intval($_POST['categoria_id']) : null;

if ($quantidade < 0) {
    die(json_encode(["success" => false, "message" => "Quantidade não pode ser negativa"]));
}

if ($valor_unitario < 0) {
    die(json_encode(["success" => false, "message" => "Valor unitário não pode ser negativo"]));
}

if ($valor_unitario_venda_estimado < 0) {
    die(json_encode(["success" => false, "message" => "Valor de venda estimado não pode ser negativo"]));
}

// Verificar se o material já existe
$sql = "SELECT id, codigo_identificacao, quantidade, valor_unitario_estoque, categoria_id FROM ga3_materiais WHERE descricao = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $descricao);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // MATERIAL JÁ EXISTE
    $row = $result->fetch_assoc();
    $material_id = $row['id'];
    $quantidade_anterior = $row['quantidade'];
    $valor_anterior = $row['valor_unitario_estoque'];
    
    // Se não informou categoria, usa a existente do material
    if ($categoria_id === null) {
        $categoria_id = $row['categoria_id'];
    }
    
    $valor_total_anterior = $quantidade_anterior * $valor_anterior;
    $valor_total_novo = $quantidade * $valor_unitario;
    $quantidade_total = $quantidade_anterior + $quantidade;
    
    if ($quantidade_total > 0) {
        $novo_valor_medio = ($valor_total_anterior + $valor_total_novo) / $quantidade_total;
    } else {
        $novo_valor_medio = $valor_anterior;
    }
    
    // Atualizar material
    $sql_update = "UPDATE ga3_materiais SET 
                   quantidade = quantidade + ?, 
                   valor_unitario_estoque = ?,
                   valor_unitario_venda_estimado = ?,
                   categoria_id = ?
                   WHERE id = ?";
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->bind_param("iddii", $quantidade, $novo_valor_medio, $valor_unitario_venda_estimado, $categoria_id, $material_id);
    
    if ($stmt_update->execute()) {
        // Registrar despesa SEM quantidade e categoria_id
        $valor_total_despesa = $quantidade * $valor_unitario;
        $descricao_despesa = "Adição de estoque: " . $quantidade . " unidades de " . $descricao . " (Código: " . $row['codigo_identificacao'] . ")";
        
        $sql_despesa = "INSERT INTO ga3_despesas (material_id, descricao, valor, data_despesa) 
                       VALUES (?, ?, ?, CURDATE())";
        $stmt_despesa = $conn->prepare($sql_despesa);
        $stmt_despesa->bind_param("isd", $material_id, $descricao_despesa, $valor_total_despesa);
        
        if (!$stmt_despesa->execute()) {
            error_log("ERRO AO INSERIR DESPESA: " . $stmt_despesa->error);
        }
        $stmt_despesa->close();
        
        // Registrar atividade
        if (isset($_SESSION['usuario']['id'])) {
            $usuario_id = $_SESSION['usuario']['id'];
            $atividade = "Atualizou material: {$descricao} (+{$quantidade} unidades)";
            $sql_atividade = "INSERT INTO ga3_atividades (usuario_id, atividade) VALUES (?, ?)";
            $stmt_atividade = $conn->prepare($sql_atividade);
            $stmt_atividade->bind_param("is", $usuario_id, $atividade);
            $stmt_atividade->execute();
            $stmt_atividade->close();
        }
        
        echo json_encode([
            "success" => true, 
            "message" => "Material atualizado com sucesso!",
            "codigo" => $row['codigo_identificacao'],
            "material_id" => $material_id
        ]);
    } else {
        echo json_encode(["success" => false, "message" => "Erro ao atualizar material: " . $conn->error]);
    }
    
    $stmt_update->close();
    
} else {
    // MATERIAL NOVO
    $prefixo_categoria = "GEN";
    
    if ($categoria_id !== null) {
        $sql_categoria = "SELECT nome FROM ga3_categorias WHERE id = ?";
        $stmt_cat = $conn->prepare($sql_categoria);
        $stmt_cat->bind_param("i", $categoria_id);
        $stmt_cat->execute();
        $result_categoria = $stmt_cat->get_result();
        
        if ($result_categoria->num_rows > 0) {
            $row_categoria = $result_categoria->fetch_assoc();
            $nome_categoria = $row_categoria['nome'];
            
            $palavras = explode(' ', $nome_categoria);
            $prefixo_categoria = "";
            
            foreach ($palavras as $palavra) {
                if (strlen($palavra) > 0) {
                    $prefixo_categoria .= strtoupper(substr($palavra, 0, 1));
                }
            }
            
            if (strlen($prefixo_categoria) < 2) {
                $prefixo_categoria = strtoupper(substr($nome_categoria, 0, 2));
            }
            
            $prefixo_categoria = substr($prefixo_categoria, 0, 2);
        }
        $stmt_cat->close();
    }
    
    $palavras_material = explode(' ', $descricao);
    $prefixo_material = "";
    
    foreach ($palavras_material as $palavra) {
        if (strlen($palavra) > 0) {
            $prefixo_material .= strtoupper(substr($palavra, 0, 1));
        }
    }
    
    if (strlen($prefixo_material) < 3) {
        $prefixo_material = strtoupper(substr($descricao, 0, 3));
    }
    
    $prefixo_material = substr($prefixo_material, 0, 3);
    $prefixo = "{$prefixo_categoria}-{$prefixo_material}";
    
    $sql_ultimo_codigo = "SELECT codigo_identificacao FROM ga3_materiais 
                          WHERE codigo_identificacao LIKE ? 
                          ORDER BY id DESC LIMIT 1";
    $stmt_codigo = $conn->prepare($sql_ultimo_codigo);
    $prefixo_busca = $prefixo . "-%";
    $stmt_codigo->bind_param("s", $prefixo_busca);
    $stmt_codigo->execute();
    $result_ultimo_codigo = $stmt_codigo->get_result();
    
    if ($result_ultimo_codigo->num_rows > 0) {
        $row_codigo = $result_ultimo_codigo->fetch_assoc();
        $ultimo_codigo = $row_codigo['codigo_identificacao'];
        $partes = explode('-', $ultimo_codigo);
        $ultimo_numero = intval(end($partes));
        $novo_numero = $ultimo_numero + 1;
    } else {
        $novo_numero = 1;
    }
    
    $stmt_codigo->close();
    
    $numero_formatado = str_pad($novo_numero, 3, '0', STR_PAD_LEFT);
    $codigo_identificacao = "{$prefixo}-{$numero_formatado}";
    
    // Inserir novo material
    $sql_insert = "INSERT INTO ga3_materiais (descricao, quantidade, valor_unitario_estoque, valor_unitario_venda_estimado, categoria_id, codigo_identificacao) 
                  VALUES (?, ?, ?, ?, ?, ?)";
    $stmt_insert = $conn->prepare($sql_insert);
    $stmt_insert->bind_param("siddis", $descricao, $quantidade, $valor_unitario, $valor_unitario_venda_estimado, $categoria_id, $codigo_identificacao);
    
    if ($stmt_insert->execute()) {
        $material_id = $conn->insert_id;
        
        // Registrar despesa SEM quantidade e categoria_id
        $valor_total_despesa = $quantidade * $valor_unitario;
        $descricao_despesa = "Adição de estoque: " . $quantidade . " unidades de " . $descricao . " (Código: " . $codigo_identificacao . ")";
        
        $sql_despesa = "INSERT INTO ga3_despesas (material_id, descricao, valor, data_despesa) 
                       VALUES (?, ?, ?, CURDATE())";
        $stmt_despesa = $conn->prepare($sql_despesa);
        $stmt_despesa->bind_param("isd", $material_id, $descricao_despesa, $valor_total_despesa);
        
        if (!$stmt_despesa->execute()) {
            error_log("ERRO AO INSERIR DESPESA: " . $stmt_despesa->error);
        }
        $stmt_despesa->close();
        
        // Registrar atividade
        if (isset($_SESSION['usuario']['id'])) {
            $usuario_id = $_SESSION['usuario']['id'];
            $atividade = "Adicionou novo material: {$descricao} ({$quantidade} unidades)";
            $sql_atividade = "INSERT INTO ga3_atividades (usuario_id, atividade) VALUES (?, ?)";
            $stmt_atividade = $conn->prepare($sql_atividade);
            $stmt_atividade->bind_param("is", $usuario_id, $atividade);
            $stmt_atividade->execute();
            $stmt_atividade->close();
        }
        
        echo json_encode([
            "success" => true, 
            "message" => "Material adicionado com sucesso!",
            "codigo" => $codigo_identificacao,
            "material_id" => $material_id
        ]);
    } else {
        echo json_encode(["success" => false, "message" => "Erro ao adicionar material: " . $conn->error]);
    }
    
    $stmt_insert->close();
}

$stmt->close();
$conn->close();
?>