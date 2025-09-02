<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ga3_stockly";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}

if (isset($_POST['id'])) {
    $material_id = $_POST['id'];
    
    // Verificar se existem transações relacionadas a este material
    $sql_check_transacoes = "SELECT COUNT(*) as count FROM ga3_transacoes WHERE material_id = $material_id";
    $result_check = $conn->query($sql_check_transacoes);
    $row_check = $result_check->fetch_assoc();
    
    if ($row_check['count'] > 0) {
        echo "Não é possível excluir este material pois existem transações relacionadas a ele.";
    } else {
        // Verificar se existem despesas relacionadas a este material
        $sql_check_despesas = "SELECT COUNT(*) as count FROM ga3_despesas WHERE material_id = $material_id";
        $result_check_despesas = $conn->query($sql_check_despesas);
        $row_check_despesas = $result_check_despesas->fetch_assoc();
        
        // Obter informações do material para o log
        $sql_material = "SELECT descricao, codigo_identificacao FROM ga3_materiais WHERE id = $material_id";
        $result_material = $conn->query($sql_material);
        $material_info = $result_material->fetch_assoc();
        $material_nome = $material_info['descricao'];
        $material_codigo = $material_info['codigo_identificacao'];
        
        if ($row_check_despesas['count'] > 0) {
            // Excluir as despesas relacionadas primeiro
            $sql_delete_despesas = "DELETE FROM ga3_despesas WHERE material_id = $material_id";
            if ($conn->query($sql_delete_despesas) === TRUE) {
                // Agora excluir o material
                $sql_delete = "DELETE FROM ga3_materiais WHERE id = $material_id";
                if ($conn->query($sql_delete) === TRUE) {
                    echo "Material '$material_nome' (Código: $material_codigo) excluído com sucesso! Atenção: $row_check_despesas[count] despesas relacionadas também foram excluídas.";
                } else {
                    echo "Erro ao excluir material: " . $conn->error;
                }
            } else {
                echo "Erro ao excluir despesas relacionadas: " . $conn->error;
            }
        } else {
            // Excluir o material diretamente
            $sql_delete = "DELETE FROM ga3_materiais WHERE id = $material_id";
            if ($conn->query($sql_delete) === TRUE) {
                echo "Material '$material_nome' (Código: $material_codigo) excluído com sucesso!";
            } else {
                echo "Erro ao excluir material: " . $conn->error;
            }
        }
    }
} else {
    echo "ID do material não fornecido.";
}

$conn->close();
?>