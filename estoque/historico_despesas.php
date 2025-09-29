<?php
include 'header.php';
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ga3_stockly";

date_default_timezone_set('America/Sao_Paulo');

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}

if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit();
}

$usuario = $_SESSION['usuario'];

if (!isset($usuario['foto_perfil']) || empty($usuario['foto_perfil'])) {
    $usuario['foto_perfil'] = '../uploads/basico.png';
} else {
    $usuario['foto_perfil'] = htmlspecialchars($usuario['foto_perfil']);
}

// Definir filtros
$filtro_categoria = isset($_GET['categoria']) ? $_GET['categoria'] : null;
$filtro_valor_min = isset($_GET['valor_min']) ? $_GET['valor_min'] : null;
$filtro_valor_max = isset($_GET['valor_max']) ? $_GET['valor_max'] : null;
$filtro_data_inicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : null;
$filtro_data_fim = isset($_GET['data_fim']) ? $_GET['data_fim'] : null;
$filtro_ordem = isset($_GET['ordem']) ? $_GET['ordem'] : 'id_desc';

// Construir consulta SQL
$sql_where = [];
$sql_params = [];
$sql_types = "";

$sql = "SELECT d.id, d.descricao, d.quantidade, d.valor, d.data_despesa,
               m.descricao as material_descricao, m.codigo_identificacao,
               c.nome as categoria_nome
        FROM ga3_despesas d
        INNER JOIN ga3_materiais m ON d.material_id = m.id
        LEFT JOIN ga3_categorias c ON d.categoria_id = c.id";

if ($filtro_categoria && $filtro_categoria !== '') {
    $sql_where[] = "d.categoria_id = ?";
    $sql_params[] = $filtro_categoria;
    $sql_types .= "i";
}

if ($filtro_valor_min !== null && $filtro_valor_min !== '') {
    $sql_where[] = "d.valor >= ?";
    $sql_params[] = $filtro_valor_min;
    $sql_types .= "d";
}

if ($filtro_valor_max !== null && $filtro_valor_max !== '') {
    $sql_where[] = "d.valor <= ?";
    $sql_params[] = $filtro_valor_max;
    $sql_types .= "d";
}

if ($filtro_data_inicio && $filtro_data_inicio !== '') {
    $sql_where[] = "d.data_despesa >= ?";
    $sql_params[] = $filtro_data_inicio;
    $sql_types .= "s";
}

if ($filtro_data_fim && $filtro_data_fim !== '') {
    $sql_where[] = "d.data_despesa <= ?";
    $sql_params[] = $filtro_data_fim;
    $sql_types .= "s";
}

if (!empty($sql_where)) {
    $sql .= " WHERE " . implode(" AND ", $sql_where);
}

// Aplicar ordenação
switch ($filtro_ordem) {
    case 'id_asc':
        $sql .= " ORDER BY d.id ASC";
        break;
    case 'id_desc':
        $sql .= " ORDER BY d.id DESC";
        break;
    case 'data_asc':
        $sql .= " ORDER BY d.data_despesa ASC";
        break;
    case 'data_desc':
        $sql .= " ORDER BY d.data_despesa DESC";
        break;
    case 'valor_asc':
        $sql .= " ORDER BY d.valor ASC";
        break;
    case 'valor_desc':
        $sql .= " ORDER BY d.valor DESC";
        break;
    case 'quantidade_asc':
        $sql .= " ORDER BY d.quantidade ASC";
        break;
    case 'quantidade_desc':
        $sql .= " ORDER BY d.quantidade DESC";
        break;
    default:
        $sql .= " ORDER BY d.id DESC";
}

// Executar consulta
$stmt = $conn->prepare($sql);

if (!empty($sql_params)) {
    $stmt->bind_param($sql_types, ...$sql_params);
}

$stmt->execute();
$result = $stmt->get_result();

$despesas = array();

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $despesas[] = $row;
    }
}

// Calcular total de despesas - corrigindo a consulta
$sql_total = "SELECT SUM(d.valor) as total_despesas 
              FROM ga3_despesas d 
              LEFT JOIN ga3_materiais m ON d.material_id = m.id
              LEFT JOIN ga3_categorias c ON d.categoria_id = c.id";

if (!empty($sql_where)) {
    $sql_total .= " WHERE " . implode(" AND ", $sql_where);
}

$stmt_total = $conn->prepare($sql_total);

if (!empty($sql_params)) {
    $stmt_total->bind_param($sql_types, ...$sql_params);
}

$stmt_total->execute();
$result_total = $stmt_total->get_result();
$total_despesas = 0;

if ($result_total->num_rows > 0) {
    $row = $result_total->fetch_assoc();
    $total_despesas = $row['total_despesas'] ?: 0;
}

// Obter categorias para filtro
$sql_categorias = "SELECT id, nome FROM ga3_categorias ORDER BY nome ASC";
$result_categorias = $conn->query($sql_categorias);
$categorias = [];

if ($result_categorias->num_rows > 0) {
    while($row = $result_categorias->fetch_assoc()) {
        $categorias[] = $row;
    }
}

// Registrar atividade
function registrar_atividade($conn, $usuario_id, $atividade) {
    $stmt = $conn->prepare("INSERT INTO ga3_atividades (usuario_id, atividade) VALUES (?, ?)");
    $stmt->bind_param("is", $usuario_id, $atividade);
    $stmt->execute();
    $stmt->close();
}

registrar_atividade($conn, $usuario['id'], 'Visualizou Histórico de despesas');

$conn->close();

include 'dashboard_data.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Histórico de Despesas - Stockly</title>
    <link rel="stylesheet" href="../css/styleprincipal.css">
    <link rel="stylesheet" href="dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
    <style>
     * {
    margin: 0;
    padding: 0;
    border: 0;
    outline: 0;
    font-size: 100%;
    vertical-align: baseline;
    background: transparent;
    box-sizing: border-box;
}

body {
    line-height: 1;
    font-family: Arial, Helvetica, sans-serif;
    font-size: 16px;
    color: #000;
    background-color: #fff;
}

:root {
  --primary-color: #2a9d8f;
  --primary-dark: #1e7376;
  --secondary-color: #f0f9ff;
  --light-gray: #f5f5f5;
  --medium-gray: #e0e0e0;
  --dark-gray: #333;
  --text-color: #333;
  --success-color: #27ae60;
  --danger-color: #e74c3c;
  --warning-color: #f39c12;
  --bg-secondary: #e9ecef;
}

h1 {
  color: var(--primary-color);
  margin-bottom: 25px;
  font-size: 28px;
  border-bottom: 2px solid var(--primary-color);
  padding-bottom: 10px;
}

.content-wrapper {
  max-width: 1200px;
  margin: 20px auto;
  padding: 20px;
  background: #fff;
  border-radius: 8px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

.filters-container {
  background-color: var(--light-gray);
  border-radius: 8px;
  padding: 20px;
  margin-bottom: 25px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.filter-form {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 15px;
}

.filter-group {
  margin-bottom: 15px;
}

.filter-group label {
  display: block;
  margin-bottom: 5px;
  font-weight: 500;
  color: var(--dark-gray);
}

.filter-group select,
.filter-group input[type="text"],
.filter-group input[type="number"],
.filter-group input[type="date"] {
  width: 100%;
  padding: 10px;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 14px;
  transition: border-color 0.3s;
  background-color: white;
}

.filter-group select:focus,
.filter-group input:focus {
  border-color: var(--primary-color);
  outline: none;
  box-shadow: 0 0 0 2px rgba(41, 128, 185, 0.2);
}

.filter-buttons {
  display: flex;
  gap: 10px;
  margin-top: 15px;
}

.btn-filtrar,
.btn-limpar {
  padding: 10px 15px;
  border: none;
  border-radius: 4px;
  font-weight: 500;
  cursor: pointer;
  transition: background-color 0.3s, transform 0.2s;
}

.btn-filtrar {
  background-color: var(--primary-color);
  color: white;
}

.btn-filtrar:hover {
  background-color: var(--primary-dark);
  transform: translateY(-2px);
}

.btn-limpar {
  background-color: #f8f9fa;
  color: var(--dark-gray);
  border: 1px solid #ddd;
}

.btn-limpar:hover {
  background-color: var(--bg-secondary);
}

.resultados-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 15px;
  padding-bottom: 10px;
  border-bottom: 1px solid var(--medium-gray);
}

.resultados-header h2 {
  font-size: 20px;
  color: var(--primary-color);
  margin: 0;
}

.valor-destaque {
  font-weight: 700;
  color: var(--danger-color);
}

.table-responsive {
  overflow-x: auto;
  box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
  border-radius: 6px;
}

#tabela-despesas {
  width: 100%;
  border-collapse: collapse;
  font-size: 14px;
}

#tabela-despesas th {
  background-color: var(--primary-color);
  color: white;
  text-align: left;
  padding: 12px 15px;
  font-weight: 500;
}

#tabela-despesas td {
  padding: 10px 15px;
  border-bottom: 1px solid var(--medium-gray);
}

#tabela-despesas tr:nth-child(even) {
  background-color: var(--light-gray);
}

#tabela-despesas tr:hover {
  background-color: #e9f4fd;
  transition: background-color 0.3s;
}

#tabela-despesas tfoot {
  font-weight: 700;
  background-color: var(--light-gray);
}

#tabela-despesas tfoot td {
  padding: 12px 15px;
}

.no-data {
  text-align: center;
  padding: 20px;
  font-style: italic;
  color: #777;
}

.export-buttons {
  display: flex;
  justify-content: flex-end;
  margin-top: 15px;
}

.btn-export {
  padding: 8px 15px;
  border: none;
  border-radius: 4px;
  font-weight: 500;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 6px;
  transition: background-color 0.3s;
}

.btn-export.pdf {
  background-color: var(--danger-color);
  color: white;
}

.btn-export.pdf:hover {
  background-color: #c0392b;
}

@media (max-width: 768px) {
  .content-wrapper {
    padding: 15px;
    margin: 10px;
  }

  .filter-form {
    grid-template-columns: 1fr;
  }

  .resultados-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 10px;
  }

  .export-buttons {
    flex-direction: column;
    width: 100%;
    gap: 10px;
  }

  .btn-export {
    width: 100%;
    justify-content: center;
  }

  #tabela-despesas {
    font-size: 12px;
  }

  #tabela-despesas td, 
  #tabela-despesas th {
    padding: 8px 10px;
  }
}
    </style>
</head>

<body>

    <div class="content-wrapper">
        <h1>Histórico de Despesas</h1>
        
        <div class="filters-container">
            <form method="GET" action="" id="filtro-form" class="filter-form">
                <div class="filter-group">
                    <label for="categoria">Categoria:</label>
                    <select id="categoria" name="categoria">
                        <option value="">Todas as categorias</option>
                        <?php foreach ($categorias as $categoria): ?>
                            <option value="<?php echo $categoria['id']; ?>" 
                                <?php echo ($filtro_categoria == $categoria['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($categoria['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="valor_min">Valor mínimo (R$):</label>
                    <input type="number" id="valor_min" name="valor_min" step="0.01" min="0" 
                        value="<?php echo $filtro_valor_min !== null ? $filtro_valor_min : ''; ?>" 
                        placeholder="Ex: 50,00">
                </div>
                
                <div class="filter-group">
                    <label for="valor_max">Valor máximo (R$):</label>
                    <input type="number" id="valor_max" name="valor_max" step="0.01" min="0" 
                        value="<?php echo $filtro_valor_max !== null ? $filtro_valor_max : ''; ?>" 
                        placeholder="Ex: 500,00">
                </div>
                
                <div class="filter-group">
                    <label for="data_inicio">Data início:</label>
                    <input type="date" id="data_inicio" name="data_inicio" 
                        value="<?php echo $filtro_data_inicio ?? ''; ?>">
                </div>
                
                <div class="filter-group">
                    <label for="data_fim">Data fim:</label>
                    <input type="date" id="data_fim" name="data_fim" 
                        value="<?php echo $filtro_data_fim ?? ''; ?>">
                </div>
                
                <div class="filter-group">
                    <label for="ordem">Ordenar por:</label>
                    <select id="ordem" name="ordem">
                        <option value="id_desc" <?php echo $filtro_ordem == 'id_desc' ? 'selected' : ''; ?>>ID (mais recente)</option>
                        <option value="id_asc" <?php echo $filtro_ordem == 'id_asc' ? 'selected' : ''; ?>>ID (mais antigo)</option>
                        <option value="data_desc" <?php echo $filtro_ordem == 'data_desc' ? 'selected' : ''; ?>>Data (mais recente)</option>
                        <option value="data_asc" <?php echo $filtro_ordem == 'data_asc' ? 'selected' : ''; ?>>Data (mais antiga)</option>
                        <option value="valor_desc" <?php echo $filtro_ordem == 'valor_desc' ? 'selected' : ''; ?>>Valor (maior)</option>
                        <option value="valor_asc" <?php echo $filtro_ordem == 'valor_asc' ? 'selected' : ''; ?>>Valor (menor)</option>
                        <option value="quantidade_desc" <?php echo $filtro_ordem == 'quantidade_desc' ? 'selected' : ''; ?>>Quantidade (maior)</option>
                        <option value="quantidade_asc" <?php echo $filtro_ordem == 'quantidade_asc' ? 'selected' : ''; ?>>Quantidade (menor)</option>
                    </select>
                </div>
                
                <div class="filter-buttons">
                    <button type="submit" class="btn-filtrar">
                        <i class="fas fa-filter"></i> Filtrar
                    </button>
                    <button type="button" class="btn-limpar" onclick="limparFiltros()">
                        <i class="fas fa-times"></i> Limpar Filtros
                    </button>
                </div>
            </form>
            
            <div class="export-buttons">
                <button type="button" class="btn-export pdf" onclick="exportToPDF()">
                    <i class="fas fa-file-pdf"></i> Exportar para PDF
                </button>
            </div>
        </div>

        <div id="resultados">
            <div class="resultados-header">
                <h2>Resultados</h2>
                <p id="total_despesas">Total de Despesas: <span class="valor-destaque">R$ <?php echo number_format($total_despesas, 2, ',', '.'); ?></span></p>
            </div>
            
            <div class="table-responsive">
                <table id="tabela-despesas">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Data</th>
                            <th>Código Material</th>
                            <th>Material</th>
                            <th>Descrição</th>
                            <th>Categoria</th>
                            <th>Quantidade</th>
                            <th>Valor</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($despesas) > 0): ?>
                            <?php foreach ($despesas as $despesa): ?>
                                <tr>
                                    <td><?php echo $despesa['id']; ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($despesa['data_despesa'])); ?></td>
                                    <td><?php echo htmlspecialchars($despesa['codigo_identificacao'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($despesa['material_descricao']); ?></td>
                                    <td><?php echo htmlspecialchars($despesa['descricao']); ?></td>
                                    <td><?php echo htmlspecialchars($despesa['categoria_nome'] ?? 'Sem categoria'); ?></td>
                                    <td><?php echo $despesa['quantidade']; ?></td>
                                    <td>R$ <?php echo number_format($despesa['valor'], 2, ',', '.'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="no-data">Nenhuma despesa encontrada</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="7">Total de Despesas:</td>
                            <td>R$ <?php echo number_format($total_despesas, 2, ',', '.'); ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

<footer class="site-footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-logo">
                    <img src="../Imagens/logo.png" alt="Stockly" width="120">
                    <p>Gerenciamento de estoque simplificado</p>
                </div>
                <div class="footer-links">
                    <h4>Links Rápidos</h4>
                    <ul>
                        <li><a href="../estoque/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                        <li><a href="../estoque/estoque.php"><i class="fas fa-boxes"></i> Estoque</a></li>
                        <li><a href="../estoque/vendas.php"><i class="fas fa-shopping-cart"></i> Vendas</a></li>
                        <li><a href="../estoque/historico_vendas.php"><i class="fas fa-chart-line"></i> Histórico de vendas</a></li>
                    </ul>
                </div>
                <div class="footer-contact">
                    <h4>Contato</h4>
                    <p><i class="fas fa-envelope"></i> suporte@stockly.com</p>
                    <p><i class="fas fa-phone"></i> (11) 9999-9999</p>
                    <div class="social-icons">
                        <a href="#"><i class="fab fa-facebook"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-linkedin"></i></a>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2025 Stockly. Todos os direitos reservados.</p>
            </div>
        </div>
    </footer>
    
    <script>
        document.getElementById('dropdownButton')?.addEventListener('click', function() {
            document.getElementById('dropdownContent')?.classList.toggle('show');
        });

        function limparFiltros() {
            document.getElementById('categoria').value = '';
            document.getElementById('valor_min').value = '';
            document.getElementById('valor_max').value = '';
            document.getElementById('data_inicio').value = '';
            document.getElementById('data_fim').value = '';
            document.getElementById('ordem').value = 'id_desc';
            document.getElementById('filtro-form').submit();
        }
        
        function exportToPDF() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            
            doc.setFontSize(20);
            doc.setFont("helvetica", "bold");
            doc.text('STOCKLY', 14, 15);
            
            doc.setFontSize(16);
            doc.setFont("helvetica", "normal");
            doc.text('Histórico de Despesas', 14, 25);
            
            doc.setLineWidth(0.5);
            doc.line(14, 30, 196, 30);
            
            const hoje = new Date();
            doc.setFontSize(10);
            doc.setFont("helvetica", "normal");
            doc.text(`Relatório gerado em: ${hoje.toLocaleDateString('pt-BR')} às ${hoje.toLocaleTimeString('pt-BR')}`, 14, 38);
            
            doc.setFontSize(12);
            doc.setFont("helvetica", "bold");
            doc.text('Filtros Aplicados:', 14, 48);
            
            doc.setFontSize(9);
            doc.setFont("helvetica", "normal");
            
            let yPos = 54;
            
            const categoria = document.getElementById('categoria');
            const categoriaTexto = categoria.options[categoria.selectedIndex].text;
            doc.text(`• Categoria: ${categoriaTexto}`, 20, yPos);
            yPos += 4;
            
            const valorMin = document.getElementById('valor_min').value;
            const valorMax = document.getElementById('valor_max').value;
            if (valorMin) {
                doc.text(`• Valor mínimo: R$ ${valorMin}`, 20, yPos);
                yPos += 4;
            }
            if (valorMax) {
                doc.text(`• Valor máximo: R$ ${valorMax}`, 20, yPos);
                yPos += 4;
            }
            
            const dataInicio = document.getElementById('data_inicio').value;
            const dataFim = document.getElementById('data_fim').value;
            if (dataInicio) {
                doc.text(`• Data início: ${dataInicio}`, 20, yPos);
                yPos += 4;
            }
            if (dataFim) {
                doc.text(`• Data fim: ${dataFim}`, 20, yPos);
                yPos += 4;
            }
            
            const ordem = document.getElementById('ordem');
            const ordemTexto = ordem.options[ordem.selectedIndex].text;
            doc.text(`• Ordenação: ${ordemTexto}`, 20, yPos);
            
            const totalDespesas = document.getElementById('total_despesas').innerText;
            const totalTexto = totalDespesas.replace('Total de Despesas: ', '');
            
            doc.setFontSize(12);
            doc.setFont("helvetica", "bold");
            doc.text('Resumo:', 14, yPos + 10);
            
            doc.setFontSize(11);
            doc.setFont("helvetica", "normal");
            doc.text(`Total de Despesas: ${totalTexto}`, 20, yPos + 16);
            
            const table = document.getElementById('tabela-despesas');
            const headers = ['ID', 'Data', 'Cód.', 'Material', 'Descrição', 'Categoria', 'Qtd', 'Valor'];
            const tableData = [];
            
            const rows = table.querySelectorAll('tbody tr');
            if (rows.length > 0) {
                rows.forEach(row => {
                    const cells = row.querySelectorAll('td');
                    if (cells.length > 1) {
                        const rowData = [];
                        cells.forEach(cell => {
                            rowData.push(cell.innerText);
                        });
                        tableData.push(rowData);
                    }
                });
            }
            
            if (tableData.length === 0) {
                tableData.push(['Nenhuma despesa encontrada', '', '', '', '', '', '', '']);
            }
            
            doc.autoTable({
                head: [headers],
                body: tableData,
                startY: yPos + 25,
                theme: 'grid',
                styles: {
                    fontSize: 7,
                    cellPadding: 2,
                    lineColor: [128, 128, 128],
                    lineWidth: 0.1,
                },
                headStyles: {
                    fillColor: [42, 157, 143],
                    textColor: [255, 255, 255],
                    fontStyle: 'bold',
                    halign: 'center'
                },
                alternateRowStyles: {
                    fillColor: [248, 249, 250]
                },
                foot: [[{
                    content: `TOTAL DE DESPESAS: ${totalTexto}`,
                    colSpan: 8,
                    styles: { 
                        fillColor: [231, 76, 60], 
                        textColor: [255, 255, 255], 
                        fontStyle: 'bold',
                        halign: 'center'
                    }
                }]]
            });
            
            const pageCount = doc.internal.getNumberOfPages();
            for (let i = 1; i <= pageCount; i++) {
                doc.setPage(i);
                doc.setFontSize(8);
                doc.setFont("helvetica", "normal");
                doc.text(`Página ${i} de ${pageCount}`, doc.internal.pageSize.width / 2, doc.internal.pageSize.height - 10, { align: 'center' });
                doc.text(`© ${new Date().getFullYear()} Stockly - Sistema de Gestão de Estoque`, doc.internal.pageSize.width / 2, doc.internal.pageSize.height - 5, { align: 'center' });
            }
            
            doc.save(`Stockly_Historico_Despesas_${hoje.toISOString().split('T')[0]}.pdf`);
        }

        document.addEventListener('DOMContentLoaded', function() {
            const dropdownButton = document.getElementById('dropdownButton');
            const dropdownContent = document.getElementById('dropdownContent');
            
            if (dropdownButton && dropdownContent) {
                dropdownButton.addEventListener('click', function() {
                    dropdownContent.classList.toggle('show');
                });
                
                window.addEventListener('click', function(event) {
                    if (!event.target.matches('.dropbtn') && !event.target.matches('.perfil-foto')) {
                        if (dropdownContent.classList.contains('show')) {
                            dropdownContent.classList.remove('show');
                        }
                    }
                });
            }
        });

        function toggleMenu() {
            const offScreenMenu = document.querySelector('.off-screen-menu');
            if (offScreenMenu) {
                offScreenMenu.classList.toggle('active');
                const hamMenu = document.querySelector('.ham-menu');
                if (hamMenu) {
                    hamMenu.classList.toggle('active');
                }
            }
        }
    </script>
    <script src="../js/app.js"></script>
</body>
</html>