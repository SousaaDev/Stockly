<?php
session_start();

// Verifique se o usuário está logado
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

// Obter o nome do arquivo atual para marcar o menu ativo
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="pt-br">
    <head>
    </head>
<body>
<header class="site-header">
    <div class="container">
        <a href="../estoque/dashboard.php">
            <div class="divlogo">
                <img class="logo" src="../Imagens/logo.png" width="160px" height="80px" alt="Logo Stockly">
            </div>
        </a>
        <nav class="site-nav">
            <ul class="menu">
                <li><a href="../estoque/dashboard.php"><i class="fas fa-home"></i> Home</a></li>
                <li><a href="../php/sobre.php" ><i class="fas fa-book-open"></i> Sobre</a></li>
                <li><a href="../php/contato.php"><i class="fas fa-address-book"></i> Contato</a></li>
            </ul>
        </nav>
        <div class="dropdown">
            <button class="dropbtn" id="dropdownButton">
                <img src="<?php echo htmlspecialchars($usuario['foto_perfil']); ?>" alt="Foto de Perfil" class="perfil-foto">
            </button>
            <div class="dropdown-content" id="dropdownContent">
                <a href="../php/perfil.php"><i class="fas fa-user"></i> Perfil</a>
                <?php if (isset($usuario['cargo']) && in_array($usuario['cargo'], ['chefe', 'gerente'])): ?>
                    <a href="../php/configuracoes.php"><i class="fas fa-users-cog"></i> Administrar Funcionários</a>
                <?php endif; ?>
                <a href="../php/logout.php"><i class="fas fa-sign-out-alt"></i> Sair</a>
            </div>
            <div class="ham-menu" onclick="toggleMenu()">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </div>
    </div>
</header>
<!-- Sub Navbar -->
<div class="sub-navbar desktop-only">
    <ul>
        <li><a href="../estoque/adicionar_material.php" class="nav-link <?php echo ($current_page == 'adicionar_material.php') ? 'active' : ''; ?>" data-page="adicionar_material"><i class="fas fa-plus-circle"></i> Adicionar material</a></li>
        <li><a href="../estoque/gerenciar_categorias.php" class="nav-link <?php echo ($current_page == 'gerenciar_categorias.php') ? 'active' : ''; ?>" data-page="gerenciar_categorias"><i class="fas fa-tags"></i> Gerenciar categorias</a></li>
        <li><a href="../estoque/vendas.php" class="nav-link <?php echo ($current_page == 'vendas.php') ? 'active' : ''; ?>" data-page="vendas"><i class="fas fa-shopping-cart"></i> Marcar venda</a></li>
        <li><a href="../estoque/estoque.php" class="nav-link <?php echo ($current_page == 'estoque.php') ? 'active' : ''; ?>" data-page="estoque"><i class="fas fa-boxes"></i> Estoque</a></li>
        <li><a href="../estoque/historico_vendas.php" class="nav-link <?php echo ($current_page == 'historico_vendas.php') ? 'active' : ''; ?>" data-page="historico_vendas"><i class="fas fa-chart-line"></i> Histórico de venda</a></li>
        <li><a href="../estoque/historico_despesas.php" class="nav-link <?php echo ($current_page == 'historico_despesas.php') ? 'active' : ''; ?>" data-page="historico_despesas"><i class="fas fa-receipt"></i> Histórico de despesas</a></li>
    </ul>
</div>
<!-- Off-screen Menu -->
<div class="off-screen-menu">
    <ul>
        <li><a href="../php/perfil.php"><i class="fas fa-user"></i> Perfil</a></li>
        <li><a href="../php/configuracoes.php"><i class="fas fa-users-cog"></i> Administrar Funcionários</a>
            <ul>
                <li><a href="../estoque/adicionar_material.php" class="nav-link mobile-nav-link <?php echo ($current_page == 'adicionar_material.php') ? 'active' : ''; ?>" data-page="adicionar_material"><i class="fas fa-plus-circle"></i> Adicionar material</a></li>
                <li><a href="../estoque/gerenciar_categorias.php" class="nav-link mobile-nav-link <?php echo ($current_page == 'gerenciar_categorias.php') ? 'active' : ''; ?>" data-page="gerenciar_categorias"><i class="fas fa-tags"></i> Gerenciar categorias</a></li>
                <li><a href="../estoque/vendas.php" class="nav-link mobile-nav-link <?php echo ($current_page == 'vendas.php') ? 'active' : ''; ?>" data-page="vendas"><i class="fas fa-shopping-cart"></i> Marcar venda</a></li>
                <li><a href="../estoque/estoque.php" class="nav-link mobile-nav-link <?php echo ($current_page == 'estoque.php') ? 'active' : ''; ?>" data-page="estoque"><i class="fas fa-boxes"></i> Estoque</a></li>
                <li><a href="../estoque/historico_vendas.php" class="nav-link mobile-nav-link <?php echo ($current_page == 'historico_vendas.php') ? 'active' : ''; ?>" data-page="historico_vendas"><i class="fas fa-chart-line"></i> Histórico de venda</a></li>
                <li><a href="../estoque/historico_despesas.php" class="nav-link mobile-nav-link <?php echo ($current_page == 'historico_despesas.php') ? 'active' : ''; ?>" data-page="historico_despesas"><i class="fas fa-receipt"></i> Histórico de despesas</a></li>
            </ul>
        </li>
        <li><a href="../php/sobre.php"><i class="fas fa-book-open"></i> Sobre</a></li>
        <li><a href="../php/contato.php"><i class="fas fa-address-book"></i> Contato</a></li>
        <li><a href="../php/logout.php"><i class="fas fa-sign-out-alt"></i> Sair</a></li>
    </ul>
</div>
<!-- JavaScript para o dropdown e menu mobile -->
<script>
// Dropdown de perfil
const dropdownBtn = document.getElementById('dropdownButton');
const dropdownContent = document.getElementById('dropdownContent');

dropdownBtn.addEventListener('click', function() {
    dropdownContent.classList.toggle('show');
});

window.addEventListener('click', function(event) {
    if (!event.target.matches('.dropbtn') && !event.target.matches('.perfil-foto')) {
        if (dropdownContent.classList.contains('show')) {
            dropdownContent.classList.remove('show');
        }
    }
});

// Menu mobile
function toggleMenu() {
    const offScreenMenu = document.querySelector('.off-screen-menu');
    offScreenMenu.classList.toggle('active');
    document.querySelector('.ham-menu').classList.toggle('active');
}

// Função adicional para garantir que a página atual seja marcada como ativa
document.addEventListener('DOMContentLoaded', function() {
    // Pega o caminho da URL atual
    const currentPath = window.location.pathname;
    const filename = currentPath.substring(currentPath.lastIndexOf('/') + 1);
    
    // Encontra todos os links no sub-navbar (desktop e mobile)
    const navLinks = document.querySelectorAll('.nav-link');
    
    // Percorre cada link
    navLinks.forEach(link => {
        // Pega o href do link e extrai o nome do arquivo
        const href = link.getAttribute('href');
        const linkFilename = href.substring(href.lastIndexOf('/') + 1);
        
        // Verifica se o nome do arquivo na URL corresponde ao href do link
        if (filename === linkFilename) {
            // Adiciona a classe 'active' ao link
            link.classList.add('active');
        }
    });
});
</script>
</body>
</html>