<?php
include '../estoque/header.php'; // Incluindo o header

if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit();
}

$usuario = $_SESSION['usuario'];

// Verificar se o usuário é chefe ou gerente
if (!isset($usuario['cargo']) || !in_array($usuario['cargo'], ['chefe', 'gerente'])) {
    header('Location: ../estoque/dashboard.php');
    exit();
}

// Definir a foto de perfil padrão se não estiver definida
if (!isset($usuario['foto_perfil']) || empty($usuario['foto_perfil'])) {
    $usuario['foto_perfil'] = '../uploads/basico.png';
}

// Conectar ao banco de dados
$conn = new mysqli('localhost', 'root', '', 'ga3_stockly');

if ($conn->connect_error) {
    die("Conexão falhou: " . $conn->connect_error);
}

// Função para registrar atividades
function registrar_atividade($conn, $usuario_id, $atividade) {
    $stmt = $conn->prepare("INSERT INTO ga3_atividades (usuario_id, atividade) VALUES (?, ?)");
    $stmt->bind_param("is", $usuario_id, $atividade);
    $stmt->execute();
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sobre - Stockly</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="../css/styleprincipal.css">
    <style>
    /* Variáveis CSS */
:root {
    --primary-color: #5ea7a5;
    --secondary-color: #2a9d8f;
    --accent-color: #1a7b6e;
    --text-color: #333;
    --light-bg: #f5f8fa;
    --border-color: #ddd;
    --success-color: #2ecc71;
    --error-color: #e74c3c;
    --warning-color: #f39c12;
    --box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    --transition: all 0.3s ease;
}

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

/* Sobre Section */
.about-section {
    max-width: 1200px;
    margin: 0 auto;
    padding: 60px 20px;
}

.about-content {
    display: flex;
    flex-wrap: wrap;
    gap: 40px;
    align-items: center;
}

.about-text {
    flex: 1;
    min-width: 300px;
}

.about-image {
    flex: 1;
    min-width: 300px;
    text-align: center;
}

.about-image img {
    max-width: 100%;
    border-radius: 10px;
    box-shadow: var(--box-shadow);
}

.about-heading {
    color: var(--primary-color);
    font-size: 2.5rem;
    margin-bottom: 20px;
}

/* Missão, Visão e Valores */
.mission-vision-values {
    display: flex;
    flex-wrap: wrap;
    gap: 30px;
    margin-top: 40px;
}

.mission-card,
.vision-card,
.values-card {
    flex: 1;
    background: white;
    padding: 30px;
    border-radius: 10px;
    box-shadow: var(--box-shadow);
    min-width: 250px;
    transition: var(--transition);
}

.mission-card:hover,
.vision-card:hover,
.values-card:hover {
    transform: translateY(-5px);
}

.mission-card h3,
.vision-card h3,
.values-card h3 {
    color: var(--primary-color);
    margin-bottom: 15px;
}

.mission-card i,
.vision-card i,
.values-card i {
    font-size: 2.5rem;
    color: var(--primary-color);
    margin-bottom: 15px;
    display: block;
}

/* Seção de Equipe */
.team-section {
    background-color: white;
    padding: 60px 0;
    text-align: center;
}

.team-title {
    color: var(--primary-color);
    font-size: 2.5rem;
    margin-bottom: 40px;
}

.team-grid {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 30px;
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
}

.team-member {
    flex: 0 1 250px;
    background: #f9f9f9;
    border-radius: 10px;
    padding: 30px;
    box-shadow: var(--box-shadow);
    transition: var(--transition);
}

.team-member:hover {
    transform: translateY(-10px);
}

.team-member img {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    object-fit: cover;
    margin-bottom: 20px;
}

.team-member h4 {
    margin: 0 0 10px;
    color: var(--primary-color);
}

.team-member p {
    color: #666;
    margin-bottom: 15px;
}

.team-social {
    display: flex;
    justify-content: center;
    gap: 15px;
}

.team-social a {
    color: var(--primary-color);
    font-size: 1.2rem;
    transition: var(--transition);
}

.team-social a:hover {
    color: var(--accent-color);
    transform: scale(1.2);
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
    </style>
</head>
<body>

    <!-- Main About Content -->
    <section class="about-section">
        <div class="about-content">
            <div class="about-text">
                <h1 class="about-heading">Sobre a Stockly</h1>
                <p>A Stockly nasceu da necessidade de simplificar o gerenciamento de estoque para pequenas e médias empresas. Nosso objetivo é fornecer uma solução intuitiva e eficiente que ajude empresários a controlar seus recursos com precisão e facilidade.</p>
                <p>Com uma interface amigável e recursos poderosos, a Stockly permite que você tenha total controle sobre seu inventário, vendas e relatórios, economizando tempo e reduzindo erros de gestão.</p>
            </div>
            <div class="about-image">
                <img src="../Imagens/logo.png" alt="Stockly">
            </div>
        </div>

        <div class="mission-vision-values">
            <div class="mission-card">
                <i class="fas fa-bullseye"></i>
                <h3>Nossa Missão</h3>
                <p>Empoderar pequenas e médias empresas com uma ferramenta de gestão de estoque simples, inteligente e acessível, permitindo que se concentrem no crescimento do seu negócio.</p>
            </div>
            <div class="vision-card">
                <i class="fas fa-eye"></i>
                <h3>Nossa Visão</h3>
                <p>Ser a plataforma líder de gerenciamento de estoque para empresas em crescimento, reconhecida pela inovação, facilidade de uso e suporte excepcional.</p>
            </div>
            <div class="values-card">
                <i class="fas fa-heart"></i>
                <h3>Nossos Valores</h3>
                <p>
                    - Simplicidade<br>
                    - Transparência<br>
                    - Inovação<br>
                    - Foco no Cliente<br>
                    - Integridade
                </p>
            </div>
        </div>
    </section>

    <section class="team-section">
        <h2 class="team-title">Nossa Equipe</h2>
        <div class="team-grid">
            <div class="team-member">
                <img src="../imagens/felipe.jpg" alt="Membro da Equipe">
                <h4>Felipe Sousa</h4>
                <p>Fundador & CEO</p>
                <div class="team-social">
                    <a href="#"><i class="fab fa-linkedin"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                </div>
            </div>
            <div class="team-member">
                <img src="../imagens/kaua.jpg" alt="Membro da Equipe">
                <h4>Kauã Leal </h4>
                <p>CTO</p>
                <div class="team-social">
                    <a href="#"><i class="fab fa-linkedin"></i></a>
                    <a href="#"><i class="fab fa-github"></i></a>
                </div>
            </div>
            <div class="team-member">
                <img src="../imagens/gustavo.jpg" alt="Membro da Equipe">
                <h4>Gustavo Felipe</h4>
                <p>Head de Produto</p>
                <div class="team-social">
                    <a href="#"><i class="fab fa-linkedin"></i></a>
                    <a href="#"><i class="fab fa-dribbble"></i></a>
                </div>
            </div>
            <div class="team-member">
                <img src="../imagens/ivan.jpg" alt="Membro da Equipe">
                <h4>Ivan Pereria</h4>
                <p>CTO</p>
                <div class="team-social">
                    <a href="#"><i class="fab fa-linkedin"></i></a>
                    <a href="#"><i class="fab fa-dribbble"></i></a>
                </div>
            </div>
        </div>
    </section>

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

    <!-- JavaScript -->
    <script>

    
    // FAQ accordion (if needed)
    const faqItems = document.querySelectorAll('.faq-item');
    
    faqItems.forEach(item => {
        const question = item.querySelector('.faq-question');
        
        question.addEventListener('click', () => {
            // Close all other items
            faqItems.forEach(otherItem => {
                if (otherItem !== item && otherItem.classList.contains('active')) {
                    otherItem.classList.remove('active');
                }
            });
            
            // Toggle current item
            item.classList.toggle('active');
        });
    });

    </script>
</body>
</html>