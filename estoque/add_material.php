<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ga3_stockly";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}

$descricao = $_POST['descricao'];
$categoria_id = isset($_POST['categoria_id']) && !empty($_POST['categoria_id']) ? $_POST['categoria_id'] : "NULL";

// Verificar se o material já existe
$sql = "SELECT id, codigo_identificacao FROM ga3_materiais WHERE descricao = '$descricao'";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    // Material já existe, vamos atualizar a categoria se fornecida
    $row = $result->fetch_assoc();
    $material_id = $row['id'];
    
    if ($categoria_id != "NULL") {
        $sql_update = "UPDATE ga3_materiais SET categoria_id = $categoria_id WHERE id = $material_id";
        
        if ($conn->query($sql_update) === TRUE) {
            echo "Material atualizado com sucesso!";
        } else {
            echo "Erro ao atualizar material: " . $conn->error;
        }
    } else {
        echo "Material já existe! Código de identificação: " . $row['codigo_identificacao'];
    }
} else {
    // Material não existe, adicionar novo registro
    
    // Obter a abreviação da categoria (ou usar "GEN" para genérico se não houver categoria)
    $prefixo_categoria = "GEN"; // Padrão para itens sem categoria
    
    if ($categoria_id != "NULL") {
        $sql_categoria = "SELECT nome FROM ga3_categorias WHERE id = $categoria_id";
        $result_categoria = $conn->query($sql_categoria);
        
        if ($result_categoria->num_rows > 0) {
            $row_categoria = $result_categoria->fetch_assoc();
            $nome_categoria = $row_categoria['nome'];
            
            // Criar abreviação a partir do nome da categoria (primeiras letras em maiúsculas)
            $palavras = explode(' ', $nome_categoria);
            $prefixo_categoria = "";
            
            foreach ($palavras as $palavra) {
                if (strlen($palavra) > 0) {
                    $prefixo_categoria .= strtoupper(substr($palavra, 0, 1));
                }
            }
            
            // Se a abreviação for muito curta, usar mais letras da primeira palavra
            if (strlen($prefixo_categoria) < 2) {
                $prefixo_categoria = strtoupper(substr($nome_categoria, 0, 2));
            }
            
            // Limitar a 2 caracteres
            $prefixo_categoria = substr($prefixo_categoria, 0, 2);
        }
    }
    
    // Criar abreviação para o material (primeiras 3 letras em maiúsculas)
    $palavras_material = explode(' ', $descricao);
    $prefixo_material = "";
    
    foreach ($palavras_material as $palavra) {
        if (strlen($palavra) > 0) {
            $prefixo_material .= strtoupper(substr($palavra, 0, 1));
        }
    }
    
    // Se a abreviação for muito curta, usar mais letras da primeira palavra
    if (strlen($prefixo_material) < 3) {
        $prefixo_material = strtoupper(substr($descricao, 0, 3));
    }
    
    // Limitar a 3 caracteres
    $prefixo_material = substr($prefixo_material, 0, 3);
    
    // Combinar os prefixos para formar o código de identificação base
    $prefixo = "$prefixo_categoria-$prefixo_material";
    
    // Obter o último número sequencial usado para este prefixo
    $sql_ultimo_codigo = "SELECT codigo_identificacao FROM ga3_materiais 
                          WHERE codigo_identificacao LIKE '$prefixo-%' 
                          ORDER BY id DESC LIMIT 1";
    $result_ultimo_codigo = $conn->query($sql_ultimo_codigo);
    
    if ($result_ultimo_codigo->num_rows > 0) {
        $row = $result_ultimo_codigo->fetch_assoc();
        $ultimo_codigo = $row['codigo_identificacao'];
        $partes = explode('-', $ultimo_codigo);
        $ultimo_numero = intval(end($partes));
        $novo_numero = $ultimo_numero + 1;
    } else {
        $novo_numero = 1;
    }
    
    // Formatar o número sequencial com zeros à esquerda (3 dígitos)
    $numero_formatado = str_pad($novo_numero, 3, '0', STR_PAD_LEFT);
    $codigo_identificacao = "$prefixo-$numero_formatado";
    
    // Inserir novo material com o código de identificação
    $sql_insert = "INSERT INTO ga3_materiais (descricao, quantidade, valor_unitario_estoque, categoria_id, codigo_identificacao) 
                  VALUES ('$descricao', 0, 0, $categoria_id, '$codigo_identificacao')";
    
    if ($conn->query($sql_insert) === TRUE) {
        echo "Material adicionado com sucesso! Código de identificação: $codigo_identificacao";
    } else {
        echo "Erro: " . $sql_insert . "<br>" . $conn->error;
    }
}

$conn->close();
?>