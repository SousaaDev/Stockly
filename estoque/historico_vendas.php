<?php
    include 'header.php'; // Incluindo o header
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ga3_stockly";

// Configurar o fuso horário para Brasília (GMT-3)
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

// Verifica se a foto de perfil está definida, caso contrário, define uma padrão
if (!isset($usuario['foto_perfil']) || empty($usuario['foto_perfil'])) {
    $usuario['foto_perfil'] = '../uploads/basico.png';
} else {
    $usuario['foto_perfil'] = htmlspecialchars($usuario['foto_perfil']);
}

// Definir filtros padrão
$filtro_material = isset($_GET['material']) ? $_GET['material'] : null;
$filtro_valor_min = isset($_GET['valor_min']) ? $_GET['valor_min'] : null;
$filtro_valor_max = isset($_GET['valor_max']) ? $_GET['valor_max'] : null;
$filtro_ordem = isset($_GET['ordem']) ? $_GET['ordem'] : 'id_desc'; // Padrão: ID decrescente

// Construir a consulta SQL com filtros
$sql_where = [];
$sql_params = [];
$sql_types = "";

// Modificado para usar a tabela ga3_transacoes em vez de vendas, que contém os dados detalhados
$sql = "SELECT t.id, t.material_id, m.descricao, t.quantidade, 
        (t.valor_venda / t.quantidade) as valor_unitario, 
        t.valor_venda as valor_total, t.data_hora as data_venda 
        FROM ga3_transacoes t 
        JOIN ga3_materiais m ON t.material_id = m.id";

if ($filtro_material) {
    $sql_where[] = "t.material_id = ?";
    $sql_params[] = $filtro_material;
    $sql_types .= "i";
}

if ($filtro_valor_min !== null && $filtro_valor_min !== '') {
    $sql_where[] = "t.valor_venda >= ?";
    $sql_params[] = $filtro_valor_min;
    $sql_types .= "d";
}

if ($filtro_valor_max !== null && $filtro_valor_max !== '') {
    $sql_where[] = "t.valor_venda <= ?";
    $sql_params[] = $filtro_valor_max;
    $sql_types .= "d";
}



if (!empty($sql_where)) {
    $sql .= " WHERE " . implode(" AND ", $sql_where);
}

// Aplicar ordenação
switch ($filtro_ordem) {
    case 'id_asc':
        $sql .= " ORDER BY t.id ASC";
        break;
    case 'id_desc':
        $sql .= " ORDER BY t.id DESC";
        break;
    case 'material_asc':
        $sql .= " ORDER BY m.descricao ASC";
        break;
    case 'material_desc':
        $sql .= " ORDER BY m.descricao DESC";
        break;
    case 'valor_asc':
        $sql .= " ORDER BY t.valor_venda ASC";
        break;
    case 'valor_desc':
        $sql .= " ORDER BY t.valor_venda DESC";
        break;
    case 'data_asc':
        $sql .= " ORDER BY t.data_hora ASC";
        break;
    case 'data_desc':
        $sql .= " ORDER BY t.data_hora DESC";
        break;
    default:
        $sql .= " ORDER BY t.id DESC";
}

// Preparar e executar a consulta
$stmt = $conn->prepare($sql);

if (!empty($sql_params)) {
    $stmt->bind_param($sql_types, ...$sql_params);
}

$stmt->execute();
$result = $stmt->get_result();

$vendas = array();

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $vendas[] = $row;
    }
}

// Calcular o total de vendas com os mesmos filtros
$sql_total = "SELECT SUM(t.valor_venda) as total_vendas FROM ga3_transacoes t JOIN ga3_materiais m ON t.material_id = m.id";

if (!empty($sql_where)) {
    $sql_total .= " WHERE " . implode(" AND ", $sql_where);
}

$stmt_total = $conn->prepare($sql_total);

if (!empty($sql_params)) {
    $stmt_total->bind_param($sql_types, ...$sql_params);
}

$stmt_total->execute();
$result_total = $stmt_total->get_result();
$total_vendas = 0;

if ($result_total->num_rows > 0) {
    $row = $result_total->fetch_assoc();
    $total_vendas = $row['total_vendas'] ?: 0; // Garantir que não seja null
}

// Calcular o total de quantidade vendida
$sql_qtd_total = "SELECT SUM(t.quantidade) as total_quantidade FROM ga3_transacoes t JOIN ga3_materiais m ON t.material_id = m.id";

if (!empty($sql_where)) {
    $sql_qtd_total .= " WHERE " . implode(" AND ", $sql_where);
}

$stmt_qtd_total = $conn->prepare($sql_qtd_total);

if (!empty($sql_params)) {
    $stmt_qtd_total->bind_param($sql_types, ...$sql_params);
}

$stmt_qtd_total->execute();
$result_qtd_total = $stmt_qtd_total->get_result();
$total_quantidade = 0;

if ($result_qtd_total->num_rows > 0) {
    $row = $result_qtd_total->fetch_assoc();
    $total_quantidade = $row['total_quantidade'] ?: 0; // Garantir que não seja null
}

// Obter lista de materiais para o filtro
$sql_materiais = "SELECT id, descricao FROM ga3_materiais ORDER BY descricao ASC";
$result_materiais = $conn->query($sql_materiais);
$materiais = [];

if ($result_materiais->num_rows > 0) {
    while($row = $result_materiais->fetch_assoc()) {
        $materiais[] = $row;
    }
}

// Exportação para PDF
if (isset($_GET['export']) && $_GET['export'] == 'pdf') {
    // Lógica de exportação para PDF permanece...
    // (Mantido o código existente para PDF)
}

// Função para registrar atividade
function registrar_atividade($conn, $usuario_id, $atividade) {
    $stmt = $conn->prepare("INSERT INTO ga3_atividades (usuario_id, atividade) VALUES (?, ?)");
    $stmt->bind_param("is", $usuario_id, $atividade);
    $stmt->execute();
    $stmt->close();
}

// Registrar atividade de visualização
registrar_atividade($conn, $usuario['id'], 'Visualizou histórico de vendas');

$conn->close();

include 'dashboard_data.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Histórico de Vendas - Stockly</title>
    <link rel="stylesheet" href="../css/styleprincipal.css">
    <link rel="stylesheet" href="dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
    <style>
<style>
/* Reset CSS */
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

/* Variáveis globais */
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

/* Layout principal */
.content-wrapper {
  max-width: 1200px;
  margin: 20px auto;
  padding: 20px;
  background: #fff;
  border-radius: 8px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

h1 {
  color: var(--primary-color);
  margin-bottom: 25px;
  font-size: 28px;
  border-bottom: 2px solid var(--primary-color);
  padding-bottom: 10px;
}

/* Menu off-screen */
.off-screen-menu {
  padding: 10px;
  position: fixed;
  top: 0;
  right: -100%;
  width: 80%;
  max-width: 300px;
  height: 100%;
  background-color: white;
  z-index: 100;
  transition: right 0.3s ease;
  box-shadow: -2px 0 10px rgba(0, 0, 0, 0.1);
  overflow-y: auto;
}

.off-screen-menu.active {
  right: 0;
}

.off-screen-menu ul {
  list-style: none;
  padding: 2rem 0;
}

.off-screen-menu ul li a {
  display: block;
  padding: 1rem 2rem;
  color: var(--text-color);
  text-decoration: none;
  border-bottom: 1px solid #dee2e6;
  transition: background-color 0.3s ease;
}

.off-screen-menu ul li a:hover {
  background-color: var(--bg-secondary);
  color: var(--primary-color);
}

.off-screen-menu ul li ul {
  padding: 0;
}

.off-screen-menu ul li ul li a {
  padding-left: 3rem;
  font-size: 0.9rem;
}

/* Hamburger menu */
.ham-menu span {
  display: block;
  position: absolute;
  height: 3px;
  width: 100%;
  background-color: var(--gray-100);
  border-radius: 3px;
  opacity: 1;
  left: 0;
  transform: rotate(0deg);
  transition: .25s ease-in-out;
}

.ham-menu.active span:nth-child(1) {
  top: 10px;
  transform: rotate(135deg);
}

.ham-menu.active span:nth-child(2) {
  opacity: 0;
  left: -60px;
}

.ham-menu.active span:nth-child(3) {
  top: 10px;
  transform: rotate(-135deg);
}

/* CORREÇÃO ESPECÍFICA PARA O FILTERS-CONTAINER */
.filters-container {
  background-color: #f5f5f5 !important;
  background: #f5f5f5 !important;
  border-radius: 8px;
  padding: 20px;
  margin-bottom: 25px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

/* Garantir que não seja sobrescrito */
div.filters-container,
.content-wrapper .filters-container,
body .filters-container {
  background-color: #f5f5f5 !important;
  background: #f5f5f5 !important;
}

.filter-form {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 15px;
  background: transparent;
}

.filter-group {
  margin-bottom: 15px;
  background: transparent;
}

.filter-group label {
  display: block;
  margin-bottom: 5px;
  font-weight: 500;
  color: var(--dark-gray);
  background: transparent;
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
.filter-group input[type="text"]:focus,
.filter-group input[type="number"]:focus,
.filter-group input[type="date"]:focus {
  border-color: var(--primary-color);
  outline: none;
  box-shadow: 0 0 0 2px rgba(41, 128, 185, 0.2);
}

/* Botões */
.filter-buttons {
  display: flex;
  gap: 10px;
  margin-top: 15px;
  background: transparent;
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
  background-color: #e9ecef;
}

/* Botões de exportação */
.export-buttons {
  display: flex;
  justify-content: flex-end;
  margin-top: 15px;
  background: transparent;
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

/* Estilos para os resultados */
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
  color: var(--primary-color);
}

/* Tabela de vendas */
.table-responsive {
  overflow-x: auto;
  box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
  border-radius: 6px;
}

#tabela-vendas {
  width: 100%;
  border-collapse: collapse;
  font-size: 14px;
}

#tabela-vendas th {
  background-color: var(--primary-color);
  color: white;
  text-align: left;
  padding: 12px 15px;
  font-weight: 500;
}

#tabela-vendas td {
  padding: 10px 15px;
  border-bottom: 1px solid var(--medium-gray);
}

#tabela-vendas tr:nth-child(even) {
  background-color: var(--light-gray);
}

#tabela-vendas tr:hover {
  background-color: #e9f4fd;
  transition: background-color 0.3s;
}

#tabela-vendas tfoot {
  font-weight: 700;
  background-color: var(--light-gray);
}

#tabela-vendas tfoot td {
  padding: 12px 15px;
}

.no-data {
  text-align: center;
  padding: 20px;
  font-style: italic;
  color: #777;
}

/* Media queries */
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

  #tabela-vendas {
    font-size: 12px;
  }

  #tabela-vendas td, 
  #tabela-vendas th {
    padding: 8px 10px;
  }
}


    </style>
</style>
</head>

<body>


    <div class="content-wrapper">
        <h1>Histórico de Vendas</h1>
        
        <div class="filters-container bg-light-gray">
            <form method="GET" action="" id="filtro-form" class="filter-form">
                <div class="filter-group">
                    <label for="material">Material:</label>
                    <select id="material" name="material">
                        <option value="">Todos os materiais</option>
                        <?php foreach ($materiais as $material): ?>
                            <option value="<?php echo $material['id']; ?>" 
                                <?php echo ($filtro_material == $material['id']) ? 'selected' : ''; ?>>
                                <?php echo $material['descricao']; ?>
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
                    <label for="ordem">Ordenar por:</label>
                    <select id="ordem" name="ordem">
                        <option value="id_desc" <?php echo $filtro_ordem == 'id_desc' ? 'selected' : ''; ?>>ID (mais recente)</option>
                        <option value="id_asc" <?php echo $filtro_ordem == 'id_asc' ? 'selected' : ''; ?>>ID (mais antigo)</option>
                        <option value="material_asc" <?php echo $filtro_ordem == 'material_asc' ? 'selected' : ''; ?>>Material (A-Z)</option>
                        <option value="material_desc" <?php echo $filtro_ordem == 'material_desc' ? 'selected' : ''; ?>>Material (Z-A)</option>
                        <option value="valor_asc" <?php echo $filtro_ordem == 'valor_asc' ? 'selected' : ''; ?>>Valor (menor para maior)</option>
                        <option value="valor_desc" <?php echo $filtro_ordem == 'valor_desc' ? 'selected' : ''; ?>>Valor (maior para menor)</option>
                        <option value="data_asc" <?php echo $filtro_ordem == 'data_asc' ? 'selected' : ''; ?>>Data (mais antiga)</option>
                        <option value="data_desc" <?php echo $filtro_ordem == 'data_desc' ? 'selected' : ''; ?>>Data (mais recente)</option>
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
                <div>
                    <p>Total vendas: <span class="valor-destaque">R$ <?php echo number_format($total_vendas, 2, ',', '.'); ?></span></p>
                    <p>Quantidade total: <span class="valor-destaque"><?php echo number_format($total_quantidade, 0, ',', '.'); ?> unidades</span></p>
                </div>
            </div>
            
            <div class="table-responsive">
            <table id="tabela-vendas">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Material</th>
                            <th>Quantidade</th>
                            <th>Valor Unitário</th>
                            <th>Valor Total</th>
                            <th>Data da Venda</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($vendas) > 0): ?>
                            <?php foreach ($vendas as $venda): ?>
                                <tr>
                                    <td><?php echo $venda['id']; ?></td>
                                    <td><?php echo htmlspecialchars($venda['descricao']); ?></td>
                                    <td><?php echo number_format($venda['quantidade'], 0, ',', '.'); ?></td>
                                    <td>R$ <?php echo number_format($venda['valor_unitario'], 2, ',', '.'); ?></td>
                                    <td>R$ <?php echo number_format($venda['valor_total'], 2, ',', '.'); ?></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($venda['data_venda'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="no-data">Nenhuma venda encontrada com os filtros selecionados.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="2"><strong>Total</strong></td>
                            <td><strong><?php echo number_format($total_quantidade, 0, ',', '.'); ?></strong></td>
                            <td></td>
                            <td><strong>R$ <?php echo number_format($total_vendas, 2, ',', '.'); ?></strong></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

<!-- Footer (same as in the original file) -->
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
        // Função para mostrar/ocultar o menu dropdown
        document.getElementById('dropdownButton').addEventListener('click', function() {
            document.getElementById('dropdownContent').classList.toggle('show');
        });

        // Fechar o dropdown se o usuário clicar fora dele
        window.addEventListener('click', function(event) {
            if (!event.target.matches('.dropbtn') && !event.target.matches('.perfil-foto')) {
                var dropdowns = document.getElementsByClassName('dropdown-content');
                for (var i = 0; i < dropdowns.length; i++) {
                    var openDropdown = dropdowns[i];
                    if (openDropdown.classList.contains('show')) {
                        openDropdown.classList.remove('show');
                    }
                }
            }
        });

        // Função para alternar o menu off-screen
        function toggleMenu() {
            document.querySelector('.ham-menu').classList.toggle('active');
            document.getElementById('offScreenMenu').classList.toggle('active');
        }

        // Função para limpar os filtros
        function limparFiltros() {
            document.getElementById('material').value = '';
            document.getElementById('valor_min').value = '';
            document.getElementById('valor_max').value = '';
            document.getElementById('ordem').value = 'id_desc';
            document.getElementById('filtro-form').submit();
        }

        // Função para exportar para PDF
        function exportToPDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    
    // Header do documento com logo/título
    doc.setFontSize(20);
    doc.setFont("helvetica", "bold");
    doc.text('STOCKLY', 14, 15);
    
    doc.setFontSize(16);
    doc.setFont("helvetica", "normal");
    doc.text('Histórico de Vendas', 14, 25);
    
    // Linha separadora
    doc.setLineWidth(0.5);
    doc.line(14, 30, 196, 30);
    
    // Informações do relatório
    const hoje = new Date();
    doc.setFontSize(10);
    doc.setFont("helvetica", "normal");
    doc.text(`Relatório gerado em: ${hoje.toLocaleDateString('pt-BR')} às ${hoje.toLocaleTimeString('pt-BR')}`, 14, 38);
    
    // Seção de filtros
    doc.setFontSize(12);
    doc.setFont("helvetica", "bold");
    doc.text('Filtros Aplicados:', 14, 48);
    
    doc.setFontSize(9);
    doc.setFont("helvetica", "normal");
    
    let yPos = 54;
    
    // Material
    const material = document.getElementById('material');
    const materialTexto = material.options[material.selectedIndex].text;
    doc.text(`• Material: ${materialTexto}`, 20, yPos);
    yPos += 4;
    
    // Valores
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
    
    // Ordenação
    const ordem = document.getElementById('ordem');
    const ordemTexto = ordem.options[ordem.selectedIndex].text;
    doc.text(`• Ordenação: ${ordemTexto}`, 20, yPos);
    
    // Resumo
    const totalVendas = document.querySelector('.resultados-header .valor-destaque').innerText;
    const totalQuantidade = document.querySelectorAll('.resultados-header .valor-destaque')[1].innerText;
    
    doc.setFontSize(12);
    doc.setFont("helvetica", "bold");
    doc.text('Resumo:', 14, yPos + 10);
    
    doc.setFontSize(11);
    doc.setFont("helvetica", "normal");
    doc.text(`Total de vendas: ${totalVendas}`, 20, yPos + 16);
    doc.text(`Quantidade total vendida: ${totalQuantidade}`, 20, yPos + 22);
    
    // Preparar dados da tabela
    const table = document.getElementById('tabela-vendas');
    const headers = ['ID', 'Material', 'Qtd', 'Valor Unit.', 'Valor Total', 'Data da Venda'];
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
        tableData.push(['Nenhuma venda encontrada', '', '', '', '', '']);
    }
    
    // Renderizar tabela
    doc.autoTable({
        head: [headers],
        body: tableData,
        startY: yPos + 31,
        theme: 'grid',
        styles: {
            fontSize: 8,
            cellPadding: 3,
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
            content: `TOTAL VENDAS: ${totalVendas} | QUANTIDADE: ${totalQuantidade}`,
            colSpan: 6,
            styles: { 
                fillColor: [42, 157, 143], 
                textColor: [255, 255, 255], 
                fontStyle: 'bold',
                halign: 'center'
            }
        }]]
    });
    
    // Footer
    const pageCount = doc.internal.getNumberOfPages();
    for (let i = 1; i <= pageCount; i++) {
        doc.setPage(i);
        doc.setFontSize(8);
        doc.setFont("helvetica", "normal");
        doc.text(`Página ${i} de ${pageCount}`, doc.internal.pageSize.width / 2, doc.internal.pageSize.height - 10, { align: 'center' });
        doc.text(`© ${new Date().getFullYear()} Stockly - Sistema de Gestão de Estoque`, doc.internal.pageSize.width / 2, doc.internal.pageSize.height - 5, { align: 'center' });
    }
    
    doc.save(`Stockly_Historico_Vendas_${hoje.toISOString().split('T')[0]}.pdf`);
}

        // Dropdown menu
document.addEventListener('DOMContentLoaded', function() {
    // Procurar por qualquer elemento com classe que contenha 'dropdown' ou 'perfil'
    const perfilFoto = document.querySelector('.perfil-foto');
    const dropdownContent = document.querySelector('.dropdown-content');
    
    // Se não encontrar os elementos acima, procurar por outros seletores comuns
    const dropdownBtn = document.querySelector('.dropbtn') || 
                       document.querySelector('[data-dropdown]') || 
                       document.querySelector('.user-menu') ||
                       perfilFoto;
    
    const dropdownMenu = dropdownContent || 
                        document.querySelector('.dropdown-menu') ||
                        document.querySelector('.user-dropdown');

    if (dropdownBtn && dropdownMenu) {
        dropdownBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            dropdownMenu.classList.toggle('show');
        });

        // Fechar dropdown ao clicar fora
        document.addEventListener('click', function(event) {
            if (!dropdownBtn.contains(event.target) && !dropdownMenu.contains(event.target)) {
                dropdownMenu.classList.remove('show');
            }
        });
    }
});
    </script>
    <script src="../js/app.js"></script>
</body>
</html>