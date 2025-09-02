<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ga3_stockly"; // Banco de dados alterado

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log function to help with debugging
function logError($message) {
    error_log($message, 3, 'update_material_error.log');
}

try {
    $conn = new mysqli($servername, $username, $password, $dbname);

    if ($conn->connect_error) {
        throw new Exception("Falha na conexão: " . $conn->connect_error);
    }

    // Capturar dados do formulário
    $material_id = $_POST['material'] ?? null;
    $quantidade = $_POST['quantidade'] ?? null;
    $valor_unitario_estoque = $_POST['valor_unitario_estoque'] ?? null;
    $valor_unitario_venda_estimado = $_POST['valor_unitario_venda_estimado'] ?? null;

    // Validar entradas
    if (!$material_id) {
        throw new Exception('Material não selecionado.');
    }

    if (!is_numeric($quantidade) || $quantidade <= 0) {
        throw new Exception('Quantidade inválida.');
    }

    if (!is_numeric($valor_unitario_estoque) || $valor_unitario_estoque < 0) {
        throw new Exception('Valor de estoque inválido.');
    }

    if (!is_numeric($valor_unitario_venda_estimado) || $valor_unitario_venda_estimado < 0) {
        throw new Exception('Valor de venda inválido.');
    }

    // Iniciar transação
    $conn->begin_transaction();

    // Declarar statements fora do escopo de bloco para garantir uma manipulação segura
    $stmt_get = null;
    $stmt_update = null;
    $stmt_despesa = null;

    try {
        // Verificar a quantidade atual para comparação
        $sql_get_atual = "SELECT quantidade, descricao, codigo_identificacao FROM ga3_materiais WHERE id = ?"; // Tabela alterada
        $stmt_get = $conn->prepare($sql_get_atual);
        
        if (!$stmt_get) {
            throw new Exception('Erro ao preparar consulta: ' . $conn->error);
        }
        
        $stmt_get->bind_param("i", $material_id);
        $stmt_get->execute();
        $result = $stmt_get->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $quantidade_atual = $row['quantidade'];
            $descricao = $row['descricao'];
            $codigo_identificacao = $row['codigo_identificacao'];
            
            // Fechar o statement de consulta
            $stmt_get->close();
            $stmt_get = null;
            
            // Atualizar o material usando prepared statement
            $sql_update = "UPDATE ga3_materiais SET 
                           quantidade = quantidade + ?, 
                           valor_unitario_estoque = ?, 
                           valor_unitario_venda_estimado = ?
                           WHERE id = ?"; // Tabela alterada
            
            $stmt_update = $conn->prepare($sql_update);
            
            if (!$stmt_update) {
                throw new Exception('Erro ao preparar atualização: ' . $conn->error);
            }
            
            $stmt_update->bind_param("dddi", 
                             $quantidade, 
                             $valor_unitario_estoque, 
                             $valor_unitario_venda_estimado,
                             $material_id);
            
            if ($stmt_update->execute()) {
                // Fechar o statement de update
                $stmt_update->close();
                $stmt_update = null;
                
                // Registrar a despesa se houver aumento na quantidade
                if ($quantidade > 0) {
                    $valor_despesa = $quantidade * $valor_unitario_estoque;
                    
                    // Verificar a estrutura da tabela despesas
                    $columns_check = $conn->query("SHOW COLUMNS FROM ga3_despesas"); // Tabela alterada
                    $columns = [];
                    while ($col = $columns_check->fetch_assoc()) {
                        $columns[] = $col['Field'];
                    }

                    // Preparar a inserção de despesa
                    $insert_columns = [];
                    $insert_values = [];
                    $bind_types = '';
                    $bind_params = [];

                    // Colunas obrigatórias
                    if (in_array('material_id', $columns)) {
                        $insert_columns[] = 'material_id';
                        $insert_values[] = '?';
                        $bind_types .= 'i';
                        $bind_params[] = &$material_id;
                    }

                    if (in_array('valor', $columns)) {
                        $insert_columns[] = 'valor';
                        $insert_values[] = '?';
                        $bind_types .= 'd';
                        $bind_params[] = &$valor_despesa;
                    }

                    // Colunas opcionais
                    if (in_array('data_despesa', $columns)) {
                        $insert_columns[] = 'data_despesa';
                        $insert_values[] = '?';
                        $bind_types .= 's';
                        $data_atual = date('Y-m-d');
                        $bind_params[] = &$data_atual;
                    }

                    // Mensagem de observação
                    $obs_message = "Adição de estoque: $quantidade unidades de $descricao (Código: $codigo_identificacao)";
                    
                    // Verificar colunas de descrição ou observação
                    $desc_columns = ['obs', 'descricao', 'observacao'];
                    $desc_column = array_intersect($desc_columns, $columns);
                    
                    if (!empty($desc_column)) {
                        $desc_col = reset($desc_column);
                        $insert_columns[] = $desc_col;
                        $insert_values[] = '?';
                        $bind_types .= 's';
                        $bind_params[] = &$obs_message;
                    }

                    // Se tiver colunas para inserir
                    if (!empty($insert_columns)) {
                        $sql_despesa = "INSERT INTO ga3_despesas (" . 
                                       implode(',', $insert_columns) . 
                                       ") VALUES (" . 
                                       implode(',', $insert_values) . ")"; // Tabela alterada
                        
                        $stmt_despesa = $conn->prepare($sql_despesa);
                        
                        if (!$stmt_despesa) {
                            throw new Exception('Erro ao preparar inserção de despesa: ' . $conn->error);
                        }
                        
                        // Preparar bind_param dinamicamente
                        $bind_func = function($stmt, $types, &$params) {
                            $refs = [];
                            foreach ($params as $key => $value) {
                                $refs[$key] = &$params[$key];
                            }
                            call_user_func_array([$stmt, 'bind_param'], array_merge([$types], $refs));
                        };
                        
                        // Bind e executar
                        $bind_func($stmt_despesa, $bind_types, $bind_params);
                        $stmt_despesa->execute();
                        
                        // Verificar erros na execução
                        if ($stmt_despesa->errno) {
                            throw new Exception('Erro ao inserir despesa: ' . $stmt_despesa->error);
                        }
                        
                        // Fechar statement de despesa
                        $stmt_despesa->close();
                        $stmt_despesa = null;
                    }
                }
                
                // Confirmar transação
                $conn->commit();
                
                echo json_encode([
                    'status' => 'success', 
                    'message' => 'Estoque atualizado com sucesso!'
                ]);
            } else {
                // Reverter transação em caso de erro
                $conn->rollback();
                throw new Exception('Erro ao atualizar material: ' . $stmt_update->error);
            }
        } else {
            // Reverter transação
            $conn->rollback();
            throw new Exception('Material não encontrado!');
        }
    } catch (Exception $inner_e) {
        // Reverter transação em caso de erro interno
        if ($conn->inTransaction()) {
            $conn->rollback();
        }
        
        throw $inner_e;
    } finally {
        // Garantir fechamento seguro de statements
        if ($stmt_get && $stmt_get instanceof mysqli_stmt) {
            @$stmt_get->close();
        }
        if ($stmt_update && $stmt_update instanceof mysqli_stmt) {
            @$stmt_update->close();
        }
        if ($stmt_despesa && $stmt_despesa instanceof mysqli_stmt) {
            @$stmt_despesa->close();
        }
    }
} catch (Exception $e) {
    // Log the full error for server-side debugging
    logError($e->getMessage() . "\n" . $e->getTraceAsString());
    
    // Return a user-friendly error message
    echo json_encode([
        'status' => 'error', 
        'message' => $e->getMessage()
    ]);
} finally {
    // Fechar conexão com o banco de dados
    if (isset($conn)) {
        $conn->close();
    }
}
?>