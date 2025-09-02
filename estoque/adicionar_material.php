<?php
include 'dashboard_data.php';
include 'header.php'; // Incluindo o header

if (!isset($_SESSION['usuario'])) {
    header('Location: ../index.php');
    exit();
}

$usuario = $_SESSION['usuario'];

// Verifica se a foto de perfil está definida, caso contrário, define uma padrão
if (!isset($usuario['foto_perfil']) || empty($usuario['foto_perfil'])) {
    $usuario['foto_perfil'] = '../uploads/basico.png';
} else {
    $usuario['foto_perfil'] = htmlspecialchars($usuario['foto_perfil']);
}

// Conexão com o banco de dados - ATUALIZADO PARA O NOVO BANCO
$conn = new mysqli('localhost', 'root', '', 'ga3_stockly');

if ($conn->connect_error) {
    die("Conexão falhou: " . $conn->connect_error);
}

function registrar_atividade($conn, $usuario_id, $atividade) {
    // ATUALIZADO: Nome da tabela com prefixo ga3_
    $stmt = $conn->prepare("INSERT INTO ga3_atividades (usuario_id, atividade) VALUES (?, ?)");
    $stmt->bind_param("is", $usuario_id, $atividade);
    $stmt->execute();
    $stmt->close();
}

// Atualização de perfil
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['atualizar'])) {
    $nome = $_POST['nome'];
    $email = $_POST['email'];
    $data_nascimento = $_POST['data_nascimento'];
    $endereco = $_POST['endereco'];
    $usuario_id = $usuario['id'];

    if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] == UPLOAD_ERR_OK) {
        $foto_perfil = $_FILES['foto_perfil'];
        $extensao = pathinfo($foto_perfil['name'], PATHINFO_EXTENSION);
        $novo_nome = uniqid() . '.' . $extensao;
        $caminho = '../uploads/' . $novo_nome;

        if (move_uploaded_file($foto_perfil['tmp_name'], $caminho)) {
            $foto_perfil = $caminho;
        } else {
            $foto_perfil = $usuario['foto_perfil'] ?? null;
        }
    } else {
        $foto_perfil = $usuario['foto_perfil'] ?? null;
    }

    // ATUALIZADO: Nome da tabela com prefixo ga3_
    $stmt = $conn->prepare("UPDATE ga3_usuarios SET nome = ?, email = ?, data_nascimento = ?, endereco = ?, foto_perfil = ? WHERE id = ?");
    $stmt->bind_param("sssssi", $nome, $email, $data_nascimento, $endereco, $foto_perfil, $usuario_id);

    if ($stmt->execute()) {
        $_SESSION['usuario']['nome'] = $nome;
        $_SESSION['usuario']['email'] = $email;
        $_SESSION['usuario']['data_nascimento'] = $data_nascimento;
        $_SESSION['usuario']['endereco'] = $endereco;
        $_SESSION['usuario']['foto_perfil'] = $foto_perfil;
        $usuario['nome'] = $nome;
        $usuario['email'] = $email;
        $usuario['data_nascimento'] = $data_nascimento;
        $usuario['endereco'] = $endereco;
        $usuario['foto_perfil'] = $foto_perfil;

        registrar_atividade($conn, $usuario_id, 'Atualização de perfil');
    }

    $stmt->close();
}

// Remoção de foto de perfil
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['remover_foto'])) {
    $usuario_id = $usuario['id'];
    // ATUALIZADO: Nome da tabela com prefixo ga3_
    $stmt = $conn->prepare("UPDATE ga3_usuarios SET foto_perfil = NULL WHERE id = ?");
    $stmt->bind_param("i", $usuario_id);

    if ($stmt->execute()) {
        $_SESSION['usuario']['foto_perfil'] = '../uploads/basico.png';
        $usuario['foto_perfil'] = '../uploads/basico.png';

        registrar_atividade($conn, $usuario_id, 'Remoção de foto de perfil');
    }

    $stmt->close();
}

// Obter atividades do usuário
$atividades = [];
// ATUALIZADO: Nome da tabela com prefixo ga3_
$stmt = $conn->prepare("SELECT atividade, data FROM ga3_atividades WHERE usuario_id = ? ORDER BY data DESC LIMIT 5");
$stmt->bind_param("i", $usuario['id']);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $atividades[] = $row;
}
$stmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Adicionar Material | Stockly</title>
    <link rel="stylesheet" href="../css/styleprincipal.css">
    <link rel="stylesheet" href="dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>

        /* Variáveis globais */
:root {
    --primary: #2a9d8f;
    --primary-dark: #287271;
    --white: #ffffff;
    --gray-100: #f8f9fa;
    --gray-300: #dee2e6;
    --gray-700: #495057;
    --text-color: #333333;
    --bg-secondary: #f1f1f1;
    --border-radius: 8px;
    --border-radius-sm: 4px;
    --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1);
    --shadow-lg: 0 10px 15px rgba(0, 0, 0, 0.1);
    --transition: all 0.3s ease;
    /* Variáveis do histórico de vendas */
    --primary-color: #2a9d8f;
    --primary-dark: #1e7376;
    --secondary-color: #f0f9ff;
    --light-gray: #f5f5f5;
    --medium-gray: #e0e0e0;
    --dark-gray: #333;
    --success-color: #27ae60;
    --danger-color: #e74c3c;
    --warning-color: #f39c12;
}

/* Reset básico */
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

/* Layout principal - usando o estilo do histórico de vendas */
.content-wrapper {
    max-width: 1200px;
    margin: 20px auto;
    padding: 20px;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

/* Estilos para títulos */
h1 {
    color: var(--primary-color);
    margin-bottom: 25px;
    font-size: 28px;
    border-bottom: 2px solid var(--primary-color);
    padding-bottom: 10px;
}

h2 {
    color: var(--primary);
    border-bottom: 2px solid var(--primary);
    padding-bottom: 10px;
    margin-bottom: 20px;
    font-weight: 500;
}

/* Container de formulários */
#form-container {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 30px;
    max-width: 1200px;
    margin: 0 auto 50px;
    padding: 0 20px;
}

/* Estilos para os formulários */
#material-form, #update-form {
    background-color: var(--white);
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-md);
    padding: 25px;
    width: 100%;
    max-width: 500px;
    transition: var(--transition);
}

#material-form:hover, #update-form:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
}

/* Seções do formulário */
.form-section {
    margin-bottom: 30px;
}

/* Estilos para campos do formulário */
label {
    display: block;
    margin: 12px 0 6px;
    font-weight: 500;
    color: var(--gray-700);
}

input, select {
    width: 100%;
    padding: 12px;
    border: 1px solid var(--gray-300);
    border-radius: var(--border-radius-sm);
    font-size: 16px;
    transition: var(--transition);
    margin-bottom: 10px;
}

input:focus, select:focus {
    border-color: var(--primary);
    outline: none;
    box-shadow: 0 0 0 2px rgba(42, 157, 143, 0.25);
}

button {
    background-color: var(--primary);
    color: var(--white);
    border: none;
    border-radius: var(--border-radius-sm);
    padding: 12px 20px;
    font-size: 16px;
    cursor: pointer;
    width: 100%;
    transition: var(--transition);
}

button:hover {
    background-color: var(--primary-dark);
}

/* Links de ação */
.action-links {
    text-align: right;
    margin-bottom: 15px;
}

.action-links a {
    font-size: 14px;
    color: var(--primary);
    text-decoration: none;
    transition: var(--transition);
}

.action-links a:hover {
    color: var(--primary-dark);
    text-decoration: underline;
}

/* Alertas e mensagens */
.alert {
    padding: 15px;
    margin-bottom: 20px;
    border-radius: var(--border-radius-sm);
    display: flex;
    align-items: center;
    gap: 10px;
}

.alert-success {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-danger {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

/* Sistema de busca */
.search-container {
    position: relative;
    margin-bottom: 15px;
    width: 100%;
    display: block;
    height: auto;
}

.search-input {
    width: 100%;
    height: 45px;
    padding: 0 15px 0 40px !important;
    box-sizing: border-box;
    font-size: 16px;
    line-height: normal;
    display: block;
    border-radius: var(--border-radius-sm);
}

.search-icon {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--gray-700);
    pointer-events: none;
    z-index: 2;
}

/* Dropdown de resultados */
.dropdown-results {
    display: none;
    position: absolute;
    width: 100%;
    max-height: 200px;
    overflow-y: auto;
    background-color: var(--white);
    border: 1px solid var(--gray-300);
    border-radius: var(--border-radius-sm);
    box-shadow: var(--shadow-md);
    z-index: 10;
    transition: opacity 0.3s ease, transform 0.3s ease;
}

.dropdown-item {
    padding: 10px 15px;
    cursor: pointer;
    border-bottom: 1px solid var(--gray-300);
    transition: background-color 0.2s ease;
}

.dropdown-item:hover, .dropdown-item.active {
    background-color: var(--bg-secondary);
}

.result-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.highlight {
    font-weight: bold;
    color: var(--primary);
    background-color: rgba(42, 157, 143, 0.1);
}

.no-results {
    padding: 10px 15px;
    color: var(--gray-700);
    text-align: center;
    font-style: italic;
}

/* Menu Hamburger */
.ham-menu {
    display: none;
    cursor: pointer;
    width: 30px;
    height: 24px;
    position: relative;
    z-index: 100;
    margin: 10px;
}

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

.ham-menu span:nth-child(1) {
    top: 0px;
}

.ham-menu span:nth-child(2) {
    top: 10px;
}

.ham-menu span:nth-child(3) {
    top: 20px;
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

/* Menu Off-screen */
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

.off-screen-menu.active, .off-screen-menu.show {
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
    color: var(--primary);
}

.off-screen-menu ul li ul {
    padding: 0;
}

.off-screen-menu ul li ul li a {
    padding-left: 3rem;
    font-size: 0.9rem;
}

/* Menu overlay */
.menu-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 99;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.menu-overlay.active {
    display: block;
    opacity: 1;
}

/* Animações e estados */
.field-updated {
    animation: pulse 1.5s ease;
}

.form-success {
    animation: success-bounce 1s ease;
}

.invalid-field {
    animation: shake 0.5s ease;
    border-color: #dc3545 !important;
}

@keyframes pulse {
    0% { background-color: rgba(42, 157, 143, 0.2); }
    50% { background-color: rgba(42, 157, 143, 0.1); }
    100% { background-color: transparent; }
}

@keyframes success-bounce {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-5px); }
}

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    20%, 60% { transform: translateX(-5px); }
    40%, 80% { transform: translateX(5px); }
}

/* Media queries */
@media (max-width: 768px) {
    .ham-menu {
        display: flex;
    }
    
    .desktop-only {
        display: none;
    }
    
    .site-nav {
        display: none;
    }
    
    #form-container {
        flex-direction: column;
        align-items: center;
    }
    
    #material-form, #update-form {
        max-width: 100%;
    }
    
    .content-wrapper {
        margin: 10px;
        padding: 15px;
    }
}


        /* CSS para os dropdowns personalizados */
        .custom-dropdown {
            position: relative;
            width: 100%;
        }

        .custom-dropdown input[type="text"] {
            width: 100%;
            padding: 12px 40px 12px 12px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 14px;
            background-color: #fff;
            transition: all 0.3s ease;
            cursor: text;
            box-sizing: border-box;
        }

        .custom-dropdown input[type="text"]:focus {
            border-color: #4CAF50;
            outline: none;
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
        }

        .dropdown-arrow {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
            pointer-events: none;
            transition: transform 0.3s ease;
        }

        .custom-dropdown.open .dropdown-arrow {
            transform: translateY(-50%) rotate(180deg);
        }

        .dropdown-list {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 2px solid #e1e5e9;
            border-top: none;
            border-radius: 0 0 8px 8px;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .dropdown-list.show {
            display: block;
            animation: dropdownShow 0.3s ease;
        }

        @keyframes dropdownShow {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .dropdown-item {
            padding: 12px;
            cursor: pointer;
            border-bottom: 1px solid #f0f0f0;
            transition: background-color 0.2s ease;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .dropdown-item:hover,
        .dropdown-item.active {
            background-color: #f8f9fa;
        }

        .dropdown-item:last-child {
            border-bottom: none;
        }

        .dropdown-item .item-name {
            font-weight: 500;
            color: #333;
            flex: 1;
        }

        .dropdown-item .item-code {
            font-size: 12px;
            color: #666;
            background: #e9ecef;
            padding: 2px 6px;
            border-radius: 4px;
            margin-left: 8px;
        }

        .no-results {
            padding: 12px;
            text-align: center;
            color: #666;
            font-style: italic;
        }

        .highlight {
            background-color: #fff3cd;
            font-weight: bold;
        }

        /* Estilo para campos inválidos */
        .custom-dropdown input.invalid {
            border-color: #dc3545;
            box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.1);
        }

        /* Mensagens de alerta */
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
            transition: all 0.5s ease;
        }

        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }

        .alert-danger {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }

        .alert i {
            margin-right: 8px;
        }

        /* Animação para campos atualizados */
        .field-updated {
            animation: fieldUpdate 1.5s ease;
        }

        @keyframes fieldUpdate {
            0% { background-color: #fff3cd; }
            50% { background-color: #fff3cd; }
            100% { background-color: #fff; }
        }

        /* Animação para campos inválidos */
        .invalid-field {
            animation: shake 0.5s ease-in-out;
            border-color: #dc3545 !important;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        /* Animação para formulários com sucesso */
        .form-success {
            animation: formSuccess 1s ease;
        }

        @keyframes formSuccess {
            0% { transform: scale(1); }
            50% { transform: scale(1.02); }
            100% { transform: scale(1); }
        }
    </style>
</head>

<body>
    <div class="content-wrapper">
        <h1>Adicionar Material</h1>
        
        <div id="messageContainer"></div>
        
        <div id="form-container">
            <form id="material-form">
                <h2><i class="fas fa-plus-circle"></i> Adicionar Novo Material</h2>
                
                <div class="form-section">
                    <label for="descricao">Descrição:</label>
                    <input type="text" id="descricao" name="descricao" required placeholder="Digite o nome do material">
                    
                    <label for="categoria">Categoria:</label>
                    <div class="custom-dropdown">
                        <input type="text" id="categoria" name="categoria" placeholder="Selecione ou pesquise uma categoria" autocomplete="off">
                        <input type="hidden" id="categoria_id" name="categoria_id" required>
                        <div class="dropdown-arrow">
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="dropdown-list" id="categoriaList"></div>
                    </div>
                    <div class="action-links">
                        <a href="gerenciar_categorias.php"><i class="fas fa-cog"></i> Gerenciar categorias</a>
                    </div>
                </div>
                
                <button type="submit"><i class="fas fa-save"></i> Adicionar Material</button>
            </form>
            
            <form id="update-form">
                <h2><i class="fas fa-sync-alt"></i> Atualizar Estoque</h2>
                
                <div class="form-section">
                    <label for="material">Material:</label>
                    <div class="custom-dropdown">
                        <input type="text" id="material" name="material" placeholder="Selecione ou pesquise um material" autocomplete="off">
                        <input type="hidden" id="material_id" name="material_id" required>
                        <div class="dropdown-arrow">
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="dropdown-list" id="materialList"></div>
                    </div>
                    
                    <label for="quantidade">Quantidade a adicionar:</label>
                    <input type="number" id="quantidade" name="quantidade" required>
                    
                    <label for="valor_unitario_estoque">Valor Unitário de Estoque (R$):</label>
                    <input type="number" step="0.01" id="valor_unitario_estoque" name="valor_unitario_estoque" required>
                    
                    <label for="valor_unitario_venda_estimado">Valor Unitário de Venda Estimado (R$):</label>
                    <input type="number" step="0.01" id="valor_unitario_venda_estimado" name="valor_unitario_venda_estimado" required>
                </div>
                
                <button type="submit"><i class="fas fa-sync-alt"></i> Atualizar Estoque</button>
            </form>
        </div>
    </div>

   <!-- Footer -->
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
        // Classe CustomDropdown
        class CustomDropdown {
            constructor(container, options = {}) {
                this.container = typeof container === 'string' ? document.querySelector(container) : container;
                this.input = this.container.querySelector('input[type="text"]');
                this.hiddenInput = this.container.querySelector('input[type="hidden"]');
                this.dropdownList = this.container.querySelector('.dropdown-list');
                this.arrow = this.container.querySelector('.dropdown-arrow');
                
                this.options = {
                    placeholder: 'Selecione uma opção',
                    searchPlaceholder: 'Digite para pesquisar...',
                    noResultsText: 'Nenhum resultado encontrado',
                    minSearchLength: 0,
                    ...options
                };
                
                this.data = [];
                this.filteredData = [];
                this.isOpen = false;
                this.selectedIndex = -1;
                
                this.init();
            }
            
            init() {
                this.bindEvents();
                this.input.placeholder = this.options.placeholder;
            }
            
            bindEvents() {
                // Click no input para abrir/fechar
                this.input.addEventListener('click', (e) => {
                    e.stopPropagation();
                    if (this.isOpen) {
                        this.close();
                    } else {
                        this.open();
                    }
                });
                
                // Input para pesquisa
                this.input.addEventListener('input', (e) => {
                    const value = e.target.value;
                    this.filterData(value);
                    if (!this.isOpen && value.length >= this.options.minSearchLength) {
                        this.open();
                    }
                });
                
                // Navegação por teclado
                this.input.addEventListener('keydown', (e) => {
                    this.handleKeyNavigation(e);
                });
                
                // Fechar ao clicar fora
                document.addEventListener('click', (e) => {
                    if (!this.container.contains(e.target)) {
                        this.close();
                    }
                });
                
                // Focus e blur
                this.input.addEventListener('focus', () => {
                    if (this.data.length > 0) {
                        this.input.placeholder = this.options.searchPlaceholder;
                    }
                });
                
                this.input.addEventListener('blur', () => {
                    setTimeout(() => {
                        if (!this.container.contains(document.activeElement)) {
                            this.validateSelection();
                        }
                    }, 100);
                });
            }
            
            setData(data) {
                this.data = data;
                this.filteredData = [...data];
            }
            
            filterData(searchTerm) {
                const term = searchTerm.toLowerCase().trim();
                
                if (term === '') {
                    this.filteredData = [...this.data];
                } else {
                    this.filteredData = this.data.filter(item => {
                        return item.name.toLowerCase().includes(term) ||
                               (item.code && item.code.toLowerCase().includes(term));
                    });
                }
                
                this.renderList();
                this.selectedIndex = -1;
            }
            
            renderList() {
                this.dropdownList.innerHTML = '';
                
                if (this.filteredData.length === 0) {
                    const noResults = document.createElement('div');
                    noResults.className = 'no-results';
                    noResults.textContent = this.options.noResultsText;
                    this.dropdownList.appendChild(noResults);
                    return;
                }
                
                this.filteredData.forEach((item, index) => {
                    const div = document.createElement('div');
                    div.className = 'dropdown-item';
                    div.innerHTML = `
                        <span class="item-name">${this.highlightMatch(item.name, this.input.value)}</span>
                        ${item.code ? `<span class="item-code">${item.code}</span>` : ''}
                    `;
                    
                    div.addEventListener('click', () => {
                        this.selectItem(item);
                    });
                    
                    div.addEventListener('mouseenter', () => {
                        this.setActiveItem(index);
                    });
                    
                    this.dropdownList.appendChild(div);
                });
            }
            
            highlightMatch(text, searchTerm) {
                if (!searchTerm) return text;
                
                const regex = new RegExp(`(${this.escapeRegExp(searchTerm)})`, 'gi');
                return text.replace(regex, '<span class="highlight">$1</span>');
            }
            
            escapeRegExp(string) {
                return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            }
            
            open() {
                if (this.isOpen) return;
                
                this.isOpen = true;
                this.container.classList.add('open');
                this.dropdownList.classList.add('show');
                
                if (this.filteredData.length === 0 && this.data.length > 0) {
                    this.filterData('');
                } else {
                    this.renderList();
                }
                
                this.input.placeholder = this.options.searchPlaceholder;
            }
            
            close() {
                if (!this.isOpen) return;
                
                this.isOpen = false;
                this.container.classList.remove('open');
                this.dropdownList.classList.remove('show');
                this.selectedIndex = -1;
                
                this.input.placeholder = this.options.placeholder;
            }
            
            selectItem(item) {
                this.input.value = item.name;
                this.hiddenInput.value = item.id;
                this.close();
                
                // Trigger change event
                const changeEvent = new CustomEvent('change', {
                    detail: { selectedItem: item }
                });
                this.container.dispatchEvent(changeEvent);
            }
            
            setActiveItem(index) {
                const items = this.dropdownList.querySelectorAll('.dropdown-item');
                items.forEach(item => item.classList.remove('active'));
                
                if (index >= 0 && index < items.length) {
                    this.selectedIndex = index;
                    items[index].classList.add('active');
                    items[index].scrollIntoView({ block: 'nearest' });
                }
            }
            
            handleKeyNavigation(e) {
                if (!this.isOpen) {
                    if (e.key === 'ArrowDown' || e.key === 'Enter') {
                        e.preventDefault();
                        this.open();
                        return;
                    }
                }
                
                const items = this.dropdownList.querySelectorAll('.dropdown-item');
                
                switch (e.key) {
                    case 'ArrowDown':
                        e.preventDefault();
                        this.selectedIndex = Math.min(this.selectedIndex + 1, items.length - 1);
                        this.setActiveItem(this.selectedIndex);
                        break;
                        
                    case 'ArrowUp':
                        e.preventDefault();
                        this.selectedIndex = Math.max(this.selectedIndex - 1, 0);
                        this.setActiveItem(this.selectedIndex);
                        break;
                        
                    case 'Enter':
                        e.preventDefault();
                        if (this.selectedIndex >= 0 && this.filteredData[this.selectedIndex]) {
                            this.selectItem(this.filteredData[this.selectedIndex]);
                        }
                        break;
                        
                    case 'Escape':
                        this.close();
                        break;
                }
            }
            
            validateSelection() {
                const currentValue = this.input.value.trim();
                const hasValidSelection = this.hiddenInput.value !== '';
                
                if (currentValue === '') {
                    this.hiddenInput.value = '';
                    this.input.placeholder = this.options.placeholder;
                    return;
                }
                
                // Verificar se o texto digitado corresponde a um item válido
                const exactMatch = this.data.find(item => 
                    item.name.toLowerCase() === currentValue.toLowerCase()
                );
                
                if (exactMatch && !hasValidSelection) {
                    this.selectItem(exactMatch);
                } else if (!hasValidSelection) {
                    // Se não há correspondência exata, limpar o campo
                    this.input.value = '';
                    this.hiddenInput.value = '';
                    this.input.placeholder = this.options.placeholder;
                }
            }
            
            setValue(id, name) {
                this.hiddenInput.value = id;
                this.input.value = name;
            }
            
            clear() {
                this.input.value = '';
                this.hiddenInput.value = '';
                this.close();
            }
            
            setInvalid(invalid = true) {
                if (invalid) {
                    this.input.classList.add('invalid');
                } else {
                    this.input.classList.remove('invalid');
                }
            }
        }

        // Funções auxiliares
        let timeoutId;
        const messageContainer = document.getElementById('messageContainer');

        function showMessage(message, type = 'success') {
            const alertType = type === 'success' ? 'alert-success' : 'alert-danger';
            const iconType = type === 'success' ? 'fas fa-check-circle' : 'fas fa-exclamation-circle';
            
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert ${alertType}`;
            alertDiv.innerHTML = `<i class="${iconType}"></i> ${message}`;
            
            // Limpa mensagens anteriores
            messageContainer.innerHTML = '';
            messageContainer.appendChild(alertDiv);
            
            // Adiciona a animação de entrada
            alertDiv.style.opacity = '0';
            alertDiv.style.transform = 'translateY(-20px)';
            
            setTimeout(() => {
                alertDiv.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                alertDiv.style.opacity = '1';
                alertDiv.style.transform = 'translateY(0)';
            }, 10);
            
            // Estiliza o container
            messageContainer.style.display = 'block';
            messageContainer.style.margin = '20px auto';
            messageContainer.style.maxWidth = '1200px';
            messageContainer.style.padding = '0 20px';
            
            // Rola suavemente até a mensagem
            messageContainer.scrollIntoView({ behavior: 'smooth', block: 'center' });
            
            // Remove a mensagem após 5 segundos
            clearTimeout(timeoutId);
            timeoutId = setTimeout(() => {
                fadeOut(alertDiv);
            }, 5000);
        }

        function fadeOut(element) {
            element.style.opacity = '0';
            element.style.transform = 'translateY(-20px)';
            
            setTimeout(() => {
                if (element.parentNode) {
                    element.parentNode.removeChild(element);
                }
                
                if (messageContainer.children.length === 0) {
                    messageContainer.style.display = 'none';
                }
            }, 500);
        }

        function animateInvalidField(field) {
            field.classList.add('invalid-field');
            field.focus();
            
            setTimeout(() => {
                field.classList.remove('invalid-field');
            }, 1000);
        }

        function highlightField(field) {
            field.classList.add('field-updated');
            setTimeout(() => {
                field.classList.remove('field-updated');
            }, 1500);
        }

        async function loadCategorias() {
            try {
                const response = await fetch('fetch_categorias.php');
                if (!response.ok) throw new Error('Erro na resposta do servidor');
                return await response.json();
            } catch (error) {
                console.error('Erro ao carregar categorias:', error);
                showMessage('Erro ao carregar categorias. Por favor, tente novamente.', 'error');
                return [];
            }
        }

        async function loadMateriais() {
            try {
                const response = await fetch('fetch_materiais.php');
                if (!response.ok) throw new Error('Erro na resposta do servidor');
                return await response.json();
            } catch (error) {
                console.error('Erro ao carregar materiais:', error);
                showMessage('Erro ao carregar materiais. Por favor, tente novamente.', 'error');
                return [];
            }
        }

        function getMaterialDetails(materialId) {
            if (!materialId) return;
            
            const valorEstoqueInput = document.getElementById('valor_unitario_estoque');
            const valorVendaInput = document.getElementById('valor_unitario_venda_estimado');
            
            // Adicionar indicador de carregamento
            valorEstoqueInput.value = 'Carregando...';
            valorVendaInput.value = 'Carregando...';
            valorEstoqueInput.disabled = true;
            valorVendaInput.disabled = true;
            
            fetch(`get_material_details.php?id=${materialId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Erro na resposta do servidor');
                    }
                    return response.json();
                })
                .then(data => {
                    valorEstoqueInput.disabled = false;
                    valorVendaInput.disabled = false;
                    
                    if (data) {
                        // Converter os valores para formato numérico
                        const valorEstoque = parseFloat(data.valor_unitario_estoque || 0).toFixed(2);
                        const valorVenda = parseFloat(data.valor_unitario_venda_estimado || 0).toFixed(2);
                        
                        valorEstoqueInput.value = valorEstoque;
                        valorVendaInput.value = valorVenda;
                        
                        // Destaque suave para os campos atualizados
                        highlightField(valorEstoqueInput);
                        highlightField(valorVendaInput);
                    } else {
                        valorEstoqueInput.value = '';
                        valorVendaInput.value = '';
                    }
                })
                .catch(error => {
                    console.error('Erro ao obter detalhes do material:', error);
                    valorEstoqueInput.value = '';
                    valorVendaInput.value = '';
                    valorEstoqueInput.disabled = false;
                    valorVendaInput.disabled = false;
                    showMessage('Erro ao carregar detalhes do material. Por favor, tente novamente.', 'error');
                });
        }

        // Inicialização quando o DOM estiver carregado
        document.addEventListener('DOMContentLoaded', function() {
            let categoriaDropdown, materialDropdown;
            
            // Inicializar dropdown de categoria
            const categoriaContainer = document.querySelector('#categoria').closest('.custom-dropdown');
            if (categoriaContainer) {
                categoriaDropdown = new CustomDropdown(categoriaContainer, {
                    placeholder: 'Selecione uma categoria',
                    searchPlaceholder: 'Digite para pesquisar categoria...',
                    noResultsText: 'Nenhuma categoria encontrada'
                });
                
                // Carregar categorias
                loadCategorias().then(categorias => {
                    const formattedData = categorias.map(cat => ({
                        id: cat.id,
                        name: cat.nome,
                        code: null
                    }));
                    categoriaDropdown.setData(formattedData);
                });
            }
            
            // Inicializar dropdown de material
            const materialContainer = document.querySelector('#material').closest('.custom-dropdown');
            if (materialContainer) {
                materialDropdown = new CustomDropdown(materialContainer, {
                    placeholder: 'Selecione um material',
                    searchPlaceholder: 'Digite para pesquisar material...',
                    noResultsText: 'Nenhum material encontrado'
                });
                
                // Carregar materiais
                loadMateriais().then(materiais => {
                    const formattedData = materiais.map(mat => ({
                        id: mat.id,
                        name: mat.descricao,
                        code: mat.codigo_identificacao
                    }));
                    materialDropdown.setData(formattedData);
                });
                
                // Evento para carregar detalhes do material
                materialContainer.addEventListener('change', (e) => {
                    const selectedItem = e.detail.selectedItem;
                    if (selectedItem) {
                        getMaterialDetails(selectedItem.id);
                    }
                });
            }
            
            // Formulário de adicionar material
            const materialForm = document.getElementById('material-form');
            materialForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const descricao = document.getElementById('descricao').value.trim();
                const categoriaId = document.getElementById('categoria_id').value;
                
                // Reset invalid states
                if (categoriaDropdown) categoriaDropdown.setInvalid(false);
                
                let hasError = false;
                
                if (!descricao) {
                    showMessage('Por favor, insira uma descrição para o material.', 'error');
                    animateInvalidField(document.getElementById('descricao'));
                    hasError = true;
                }
                
                if (!categoriaId && categoriaDropdown) {
                    showMessage('Por favor, selecione uma categoria.', 'error');
                    categoriaDropdown.setInvalid(true);
                    hasError = true;
                }
                
                if (hasError) return;
                
                // Botão com estado de loading
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalBtnText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processando...';
                submitBtn.disabled = true;
                
                const formData = new FormData();
                formData.append('descricao', descricao);
                formData.append('categoria_id', categoriaId);
                
                fetch('add_material.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Erro na resposta do servidor');
                    }
                    return response.text();
                })
                .then(text => {
                    let data;
                    try {
                        data = JSON.parse(text);
                    } catch (e) {
                        data = { message: text, status: text.includes('sucesso') ? 'success' : 'error' };
                    }
                    
                    showMessage(data.message || 'Operação realizada com sucesso.', data.status || 'success');
                    
                    if (data.status === 'success' || text.includes('sucesso')) {
                        materialForm.reset();
                        if (categoriaDropdown) categoriaDropdown.clear();
                        
                        // Recarregar materiais
                        if (materialDropdown) {
                            loadMateriais().then(materiais => {
                                const formattedData = materiais.map(mat => ({
                                    id: mat.id,
                                    name: mat.descricao,
                                    code: mat.codigo_identificacao
                                }));
                                materialDropdown.setData(formattedData);
                            });
                        }
                        
                        // Animar formulário como concluído
                        materialForm.classList.add('form-success');
                        setTimeout(() => {
                            materialForm.classList.remove('form-success');
                        }, 1000);
                    }
                })
                .catch(error => {
                    console.error('Erro ao adicionar material:', error);
                    showMessage('Erro ao processar a solicitação. Por favor, tente novamente.', 'error');
                })
                .finally(() => {
                    // Restaurar botão
                    submitBtn.innerHTML = originalBtnText;
                    submitBtn.disabled = false;
                });
            });
            
            // Formulário de atualizar estoque
            const updateForm = document.getElementById('update-form');
            updateForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const materialId = document.getElementById('material_id').value;
                const quantidade = document.getElementById('quantidade').value;
                const valorUnitarioEstoque = document.getElementById('valor_unitario_estoque').value;
                const valorUnitarioVendaEstimado = document.getElementById('valor_unitario_venda_estimado').value;
                
                // Reset invalid states
                if (materialDropdown) materialDropdown.setInvalid(false);
                
                let hasError = false;
                
                if (!materialId) {
                    showMessage('Por favor, selecione um material.', 'error');
                    if (materialDropdown) materialDropdown.setInvalid(true);
                    hasError = true;
                }
                
                if (!quantidade || isNaN(quantidade) || quantidade <= 0) {
                    showMessage('Por favor, insira uma quantidade válida.', 'error');
                    animateInvalidField(document.getElementById('quantidade'));
                    hasError = true;
                }
                
                if (!valorUnitarioEstoque || isNaN(valorUnitarioEstoque) || valorUnitarioEstoque < 0) {
                    showMessage('Por favor, insira um valor unitário de estoque válido.', 'error');
                    animateInvalidField(document.getElementById('valor_unitario_estoque'));
                    hasError = true;
                }
                
                if (hasError) return;
                
                // Botão com estado de loading
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalBtnText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Atualizando...';
                submitBtn.disabled = true;
                
                const formData = new FormData();
                formData.append('material', materialId);
                formData.append('quantidade', quantidade);
                formData.append('valor_unitario_estoque', valorUnitarioEstoque);
                formData.append('valor_unitario_venda_estimado', valorUnitarioVendaEstimado);
                
                fetch('update_material.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Erro na resposta do servidor');
                    }
                    return response.text();
                })
                .then(text => {
                    let data;
                    try {
                        data = JSON.parse(text);
                    } catch (e) {
                        data = { message: text, status: text.includes('sucesso') ? 'success' : 'error' };
                    }
                    
                    showMessage(data.message || 'Estoque atualizado com sucesso.', data.status || 'success');
                    
                    if (data.status === 'success' || text.includes('sucesso')) {
                        updateForm.reset();
                        if (materialDropdown) materialDropdown.clear();
                        
                        // Animar formulário como concluído
                        updateForm.classList.add('form-success');
                        setTimeout(() => {
                            updateForm.classList.remove('form-success');
                        }, 1000);
                    }
                })
                .catch(error => {
                    console.error('Erro ao atualizar estoque:', error);
                    showMessage('Erro ao processar a solicitação. Por favor, tente novamente.', 'error');
                })
                .finally(() => {
                    // Restaurar botão
                    submitBtn.innerHTML = originalBtnText;
                    submitBtn.disabled = false;
                });
            });
            
            // Função para alternar o menu off-screen (hamburguer menu)
            window.toggleMenu = function() {
                const menu = document.querySelector('.off-screen-menu');
                const hamMenu = document.querySelector('.ham-menu');
                const overlay = document.querySelector('.menu-overlay');
                
                // Toggle das classes
                menu.classList.toggle('show');
                hamMenu.classList.toggle('active');
                
                // Criar o overlay se não existir
                if (!overlay) {
                    const newOverlay = document.createElement('div');
                    newOverlay.className = 'menu-overlay';
                    document.body.appendChild(newOverlay);
                    
                    // Adicionar evento de clique para fechar o menu
                    newOverlay.addEventListener('click', function() {
                        menu.classList.remove('show');
                        hamMenu.classList.remove('active');
                        this.classList.remove('active');
                    });
                }
                
                // Ativar/desativar overlay
                if (menu.classList.contains('show')) {
                    document.querySelector('.menu-overlay').classList.add('active');
                } else {
                    document.querySelector('.menu-overlay').classList.remove('active');
                }
            };
            
            // Lógica do dropdown de perfil
            const dropdownButton = document.getElementById('dropdownButton');
            const dropdownContent = document.getElementById('dropdownContent');
            
            if (dropdownButton && dropdownContent) {
                dropdownButton.addEventListener('click', function(event) {
                    event.stopPropagation();
                    dropdownContent.classList.toggle('show');
                });
                
                window.addEventListener('click', function(event) {
                    if (!dropdownButton.contains(event.target) && !dropdownContent.contains(event.target)) {
                        dropdownContent.classList.remove('show');
                    }
                });
            }
            
            // Procurar por qualquer elemento com classe que contenha 'dropdown' ou 'perfil'
            const perfilFoto = document.querySelector('.perfil-foto');
            const dropdownContentAlt = document.querySelector('.dropdown-content');
            
            // Se não encontrar os elementos acima, procurar por outros seletores comuns
            const dropdownBtn = document.querySelector('.dropbtn') || 
                               document.querySelector('[data-dropdown]') || 
                               document.querySelector('.user-menu') ||
                               perfilFoto;
            
            const dropdownMenu = dropdownContentAlt || 
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
            
            // Adicionar evento de clique para cada link do menu off-screen
            const menuLinks = document.querySelectorAll('.off-screen-menu a');
            menuLinks.forEach(link => {
                link.addEventListener('click', function() {
                    // Fechar o menu ao clicar em um link
                    const menu = document.querySelector('.off-screen-menu');
                    const hamMenu = document.querySelector('.ham-menu');
                    const overlay = document.querySelector('.menu-overlay');
                    
                    menu.classList.remove('show');
                    hamMenu.classList.remove('active');
                    if (overlay) {
                        overlay.classList.remove('active');
                    }
                });
            });
            
            // Criar o overlay para o menu
            if (!document.querySelector('.menu-overlay')) {
                const overlay = document.createElement('div');
                overlay.className = 'menu-overlay';
                document.body.appendChild(overlay);
                
                overlay.addEventListener('click', function() {
                    const menu = document.querySelector('.off-screen-menu');
                    const hamMenu = document.querySelector('.ham-menu');
                    
                    menu.classList.remove('show');
                    hamMenu.classList.remove('active');
                    this.classList.remove('active');
                });
            }
            
            // Marcar link ativo na barra de navegação
            const currentPage = 'adicionar_material';
            const navLinks = document.querySelectorAll('.nav-link');
            
            navLinks.forEach(link => {
                if (link.getAttribute('data-page') === currentPage) {
                    link.classList.add('active');
                } else {
                    link.classList.remove('active');
                }
            });
        });
    </script>
</body>
</html>