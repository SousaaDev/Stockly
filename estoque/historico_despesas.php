<?php
include 'header.php'; // Incluindo o header
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ga3_stockly"; // Banco de dados alterado

// Configurar o fuso horário para Brasília (GMT-3)
date_default_timezone_set('America/Sao_Paulo');

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}

// Iniciar a sessão
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
$filtro_categoria = isset($_GET['categoria']) ? $_GET['categoria'] : null;
$filtro_valor_min = isset($_GET['valor_min']) ? $_GET['valor_min'] : null;
$filtro_valor_max = isset($_GET['valor_max']) ? $_GET['valor_max'] : null;
$filtro_quantidade_min = isset($_GET['quantidade_min']) ? $_GET['quantidade_min'] : null;
$filtro_quantidade_max = isset($_GET['quantidade_max']) ? $_GET['quantidade_max'] : null;
$filtro_ordem = isset($_GET['ordem']) ? $_GET['ordem'] : 'id_desc'; // Padrão: ID decrescente

// Construir a consulta SQL com filtros - usando tabela ga3_materiais
$sql_where = [];
$sql_params = [];
$sql_types = "";

$sql = "SELECT m.id, m.descricao, m.quantidade, m.valor_unitario_estoque, m.codigo_identificacao, 
               c.nome as categoria_nome,
               (m.quantidade * m.valor_unitario_estoque) as valor_total_estoque
        FROM ga3_materiais m 
        LEFT JOIN ga3_categorias c ON m.categoria_id = c.id";

if ($filtro_categoria) {
    $sql_where[] = "m.categoria_id = ?";
    $sql_params[] = $filtro_categoria;
    $sql_types .= "i";
}

if ($filtro_valor_min !== null && $filtro_valor_min !== '') {
    $sql_where[] = "m.valor_unitario_estoque >= ?";
    $sql_params[] = $filtro_valor_min;
    $sql_types .= "d";
}

if ($filtro_valor_max !== null && $filtro_valor_max !== '') {
    $sql_where[] = "m.valor_unitario_estoque <= ?";
    $sql_params[] = $filtro_valor_max;
    $sql_types .= "d";
}

if ($filtro_quantidade_min !== null && $filtro_quantidade_min !== '') {
    $sql_where[] = "m.quantidade >= ?";
    $sql_params[] = $filtro_quantidade_min;
    $sql_types .= "i";
}

if ($filtro_quantidade_max !== null && $filtro_quantidade_max !== '') {
    $sql_where[] = "m.quantidade <= ?";
    $sql_params[] = $filtro_quantidade_max;
    $sql_types .= "i";
}

if (!empty($sql_where)) {
    $sql .= " WHERE " . implode(" AND ", $sql_where);
}

// Aplicar ordenação
switch ($filtro_ordem) {
    case 'id_asc':
        $sql .= " ORDER BY m.id ASC";
        break;
    case 'id_desc':
        $sql .= " ORDER BY m.id DESC";
        break;
    case 'material_asc':
        $sql .= " ORDER BY m.descricao ASC";
        break;
    case 'material_desc':
        $sql .= " ORDER BY m.descricao DESC";
        break;
    case 'valor_asc':
        $sql .= " ORDER BY m.valor_unitario_estoque ASC";
        break;
    case 'valor_desc':
        $sql .= " ORDER BY m.valor_unitario_estoque DESC";
        break;
    case 'quantidade_asc':
        $sql .= " ORDER BY m.quantidade ASC";
        break;
    case 'quantidade_desc':
        $sql .= " ORDER BY m.quantidade DESC";
        break;
    case 'total_asc':
        $sql .= " ORDER BY valor_total_estoque ASC";
        break;
    case 'total_desc':
        $sql .= " ORDER BY valor_total_estoque DESC";
        break;
    default:
        $sql .= " ORDER BY m.id DESC";
}

// Preparar e executar a consulta
$stmt = $conn->prepare($sql);

if (!empty($sql_params)) {
    $stmt->bind_param($sql_types, ...$sql_params);
}

$stmt->execute();
$result = $stmt->get_result();

$materiais = array();

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $materiais[] = $row;
    }
}

// Calcular o total de custos de estoque com os mesmos filtros
$sql_total = "SELECT SUM(m.quantidade * m.valor_unitario_estoque) as total_custos_estoque 
              FROM ga3_materiais m 
              LEFT JOIN ga3_categorias c ON m.categoria_id = c.id";

if (!empty($sql_where)) {
    $sql_total .= " WHERE " . implode(" AND ", $sql_where);
}

$stmt_total = $conn->prepare($sql_total);

if (!empty($sql_params)) {
    $stmt_total->bind_param($sql_types, ...$sql_params);
}

$stmt_total->execute();
$result_total = $stmt_total->get_result();
$total_custos_estoque = 0;

if ($result_total->num_rows > 0) {
    $row = $result_total->fetch_assoc();
    $total_custos_estoque = $row['total_custos_estoque'] ?: 0; // Garantir que não seja null
}

// Obter lista de categorias para o filtro
$sql_categorias = "SELECT id, nome FROM ga3_categorias ORDER BY nome ASC";
$result_categorias = $conn->query($sql_categorias);
$categorias = [];

if ($result_categorias->num_rows > 0) {
    while($row = $result_categorias->fetch_assoc()) {
        $categorias[] = $row;
    }
}

// Função para registrar atividade
function registrar_atividade($conn, $usuario_id, $atividade) {
    $stmt = $conn->prepare("INSERT INTO ga3_atividades (usuario_id, atividade) VALUES (?, ?)");
    $stmt->bind_param("is", $usuario_id, $atividade);
    $stmt->execute();
    $stmt->close();
}

// Registrar atividade de visualização
registrar_atividade($conn, $usuario['id'], 'Visualizou Histórico de despesas');

$conn->close();

include 'dashboard_data.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Custos de Estoque - Stockly</title>
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
/* Variáveis de cor */
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

/* Layout principal */
.content-wrapper {
  max-width: 1200px;
  margin: 20px auto;
  padding: 20px;
  background: #fff;
  border-radius: 8px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
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
  background-color: var(--gray-100, #f8f9fa);
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

/* Componentes de formulário e filtros */
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
.filter-group input[type="number"] {
  width: 100%;
  padding: 10px;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 14px;
  transition: border-color 0.3s;
}

.filter-group select:focus,
.filter-group input[type="text"]:focus,
.filter-group input[type="number"]:focus {
  border-color: var(--primary-color);
  outline: none;
  box-shadow: 0 0 0 2px rgba(41, 128, 185, 0.2);
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

/* Botões */
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

/* Área de resultados */
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

/* Tabela de dados */
.table-responsive {
  overflow-x: auto;
  box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
  border-radius: 6px;
}

#tabela-materiais {
  width: 100%;
  border-collapse: collapse;
  font-size: 14px;
}

#tabela-materiais th {
  background-color: var(--primary-color);
  color: white;
  text-align: left;
  padding: 12px 15px;
  font-weight: 500;
}

#tabela-materiais td {
  padding: 10px 15px;
  border-bottom: 1px solid var(--medium-gray);
}

#tabela-materiais tr:nth-child(even) {
  background-color: var(--light-gray);
}

#tabela-materiais tr:hover {
  background-color: #e9f4fd;
  transition: background-color 0.3s;
}

#tabela-materiais tfoot {
  font-weight: 700;
  background-color: var(--light-gray);
}

#tabela-materiais tfoot td {
  padding: 12px 15px;
}

.no-data {
  text-align: center;
  padding: 20px;
  font-style: italic;
  color: #777;
}

/* Botões de exportação */
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

  #tabela-materiais {
    font-size: 12px;
  }

  #tabela-materiais td, 
  #tabela-materiais th {
    padding: 8px 10px;
  }
}
    </style>
</head>

<body>

    <div class="content-wrapper">
        <h1>Histórico de despesas</h1>
        
        <div class="filters-container">
            <form method="GET" action="" id="filtro-form" class="filter-form">
                <div class="filter-group">
                    <label for="categoria">Categoria:</label>
                    <select id="categoria" name="categoria">
                        <option value="">Todas as categorias</option>
                        <?php foreach ($categorias as $categoria): ?>
                            <option value="<?php echo $categoria['id']; ?>" 
                                <?php echo ($filtro_categoria == $categoria['id']) ? 'selected' : ''; ?>>
                                <?php echo $categoria['nome']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="valor_min">Valor unitário mínimo (R$):</label>
                    <input type="number" id="valor_min" name="valor_min" step="0.01" min="0" 
                        value="<?php echo $filtro_valor_min !== null ? $filtro_valor_min : ''; ?>" 
                        placeholder="Ex: 50,00">
                </div>
                
                <div class="filter-group">
                    <label for="valor_max">Valor unitário máximo (R$):</label>
                    <input type="number" id="valor_max" name="valor_max" step="0.01" min="0" 
                        value="<?php echo $filtro_valor_max !== null ? $filtro_valor_max : ''; ?>" 
                        placeholder="Ex: 500,00">
                </div>
                
                <div class="filter-group">
                    <label for="quantidade_min">Quantidade mínima:</label>
                    <input type="number" id="quantidade_min" name="quantidade_min" min="0" 
                        value="<?php echo $filtro_quantidade_min !== null ? $filtro_quantidade_min : ''; ?>" 
                        placeholder="Ex: 10">
                </div>
                
                <div class="filter-group">
                    <label for="quantidade_max">Quantidade máxima:</label>
                    <input type="number" id="quantidade_max" name="quantidade_max" min="0" 
                        value="<?php echo $filtro_quantidade_max !== null ? $filtro_quantidade_max : ''; ?>" 
                        placeholder="Ex: 100">
                </div>
                
                <div class="filter-group">
                    <label for="ordem">Ordenar por:</label>
                    <select id="ordem" name="ordem">
                        <option value="id_desc" <?php echo $filtro_ordem == 'id_desc' ? 'selected' : ''; ?>>ID (mais recente)</option>
                        <option value="id_asc" <?php echo $filtro_ordem == 'id_asc' ? 'selected' : ''; ?>>ID (mais antigo)</option>
                        <option value="material_asc" <?php echo $filtro_ordem == 'material_asc' ? 'selected' : ''; ?>>Material (A-Z)</option>
                        <option value="material_desc" <?php echo $filtro_ordem == 'material_desc' ? 'selected' : ''; ?>>Material (Z-A)</option>
                        <option value="valor_asc" <?php echo $filtro_ordem == 'valor_asc' ? 'selected' : ''; ?>>Valor unitário (menor)</option>
                        <option value="valor_desc" <?php echo $filtro_ordem == 'valor_desc' ? 'selected' : ''; ?>>Valor unitário (maior)</option>
                        <option value="quantidade_asc" <?php echo $filtro_ordem == 'quantidade_asc' ? 'selected' : ''; ?>>Quantidade (menor)</option>
                        <option value="quantidade_desc" <?php echo $filtro_ordem == 'quantidade_desc' ? 'selected' : ''; ?>>Quantidade (maior)</option>
                        <option value="total_asc" <?php echo $filtro_ordem == 'total_asc' ? 'selected' : ''; ?>>Valor total (menor)</option>
                        <option value="total_desc" <?php echo $filtro_ordem == 'total_desc' ? 'selected' : ''; ?>>Valor total (maior)</option>
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
                <p id="total_custos">Total investido em estoque: <span class="valor-destaque">R$ <?php echo number_format($total_custos_estoque, 2, ',', '.'); ?></span></p>
            </div>
            
            <div class="table-responsive">
                <table id="tabela-materiais">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Código</th>
                            <th>Material</th>
                            <th>Categoria</th>
                            <th>Quantidade</th>
                            <th>Custo Unitário</th>
                            <th>Valor Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($materiais) > 0): ?>
                            <?php foreach ($materiais as $material): ?>
                                <tr>
                                    <td><?php echo $material['id']; ?></td>
                                    <td><?php echo $material['codigo_identificacao'] ?? '-'; ?></td>
                                    <td><?php echo $material['descricao']; ?></td>
                                    <td><?php echo $material['categoria_nome'] ?? 'Sem categoria'; ?></td>
                                    <td><?php echo $material['quantidade']; ?></td>
                                    <td>R$ <?php echo number_format($material['valor_unitario_estoque'], 2, ',', '.'); ?></td>
                                    <td>R$ <?php echo number_format($material['valor_total_estoque'], 2, ',', '.'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="no-data">Nenhum material encontrado</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="6">Total Investido:</td>
                            <td>R$ <?php echo number_format($total_custos_estoque, 2, ',', '.'); ?></td>
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
        
// Substitua a função limparFiltros() no histórico_despesas.php por esta:

function limparFiltros() {
    document.getElementById('categoria').value = '';
    document.getElementById('valor_min').value = '';
    document.getElementById('valor_max').value = '';
    document.getElementById('quantidade_min').value = '';
    document.getElementById('quantidade_max').value = '';
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
    doc.text('Histórico de Despesas (Custos de Estoque)', 14, 25);
    
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
    
    // Categoria
    const categoria = document.getElementById('categoria');
    const categoriaTexto = categoria.options[categoria.selectedIndex].text;
    doc.text(`• Categoria: ${categoriaTexto}`, 20, yPos);
    yPos += 4;
    
    // Valores
    const valorMin = document.getElementById('valor_min').value;
    const valorMax = document.getElementById('valor_max').value;
    if (valorMin) {
        doc.text(`• Valor unitário mínimo: R$ ${valorMin}`, 20, yPos);
        yPos += 4;
    }
    if (valorMax) {
        doc.text(`• Valor unitário máximo: R$ ${valorMax}`, 20, yPos);
        yPos += 4;
    }
    
    // Quantidades
    const quantidadeMin = document.getElementById('quantidade_min').value;
    const quantidadeMax = document.getElementById('quantidade_max').value;
    if (quantidadeMin) {
        doc.text(`• Quantidade mínima: ${quantidadeMin}`, 20, yPos);
        yPos += 4;
    }
    if (quantidadeMax) {
        doc.text(`• Quantidade máxima: ${quantidadeMax}`, 20, yPos);
        yPos += 4;
    }
    
    // Ordenação
    const ordem = document.getElementById('ordem');
    const ordemTexto = ordem.options[ordem.selectedIndex].text;
    doc.text(`• Ordenação: ${ordemTexto}`, 20, yPos);
    
    // Resumo
    const totalCustos = document.getElementById('total_custos').innerText;
    const totalInvestido = totalCustos.replace('Total investido em estoque: ', '');
    
    doc.setFontSize(12);
    doc.setFont("helvetica", "bold");
    doc.text('Resumo:', 14, yPos + 10);
    
    doc.setFontSize(11);
    doc.setFont("helvetica", "normal");
    doc.text(`Total investido em estoque: ${totalInvestido}`, 20, yPos + 16);
    
    // Preparar dados da tabela
    const table = document.getElementById('tabela-materiais');
    const headers = ['ID', 'Código', 'Material', 'Categoria', 'Qtd', 'Custo Unit.', 'Valor Total'];
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
        tableData.push(['Nenhum material encontrado', '', '', '', '', '', '']);
    }
    
    // Renderizar tabela
    doc.autoTable({
        head: [headers],
        body: tableData,
        startY: yPos + 25,
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
            content: `TOTAL INVESTIDO: ${totalInvestido}`,
            colSpan: 7,
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
    
    doc.save(`Stockly_Historico_Despesas_${hoje.toISOString().split('T')[0]}.pdf`);
}
        // Dropdown menu
        document.addEventListener('DOMContentLoaded', function() {
            const dropdownButton = document.getElementById('dropdownButton');
            const dropdownContent = document.getElementById('dropdownContent');
            
            if (dropdownButton && dropdownContent) {
                dropdownButton.addEventListener('click', function() {
                    dropdownContent.classList.toggle('show');
                });
                
                // Fechar o dropdown ao clicar fora
                window.addEventListener('click', function(event) {
                    if (!event.target.matches('.dropbtn') && !event.target.matches('.perfil-foto')) {
                        if (dropdownContent.classList.contains('show')) {
                            dropdownContent.classList.remove('show');
                        }
                    }
                });
            }
        });

        // Menu móvel
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