<?php
include '../estoque/header.php'; // Incluindo o header

if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit();
}

$usuario = $_SESSION['usuario'];

if (!isset($usuario['foto_perfil']) || empty($usuario['foto_perfil'])) {
    $usuario['foto_perfil'] = '../uploads/basico.png';
} else {
    $usuario['foto_perfil'] = ($usuario['foto_perfil']);
}

// Conexão com banco de dados ga3_stockly
$host = 'localhost';
$usuario_db = 'root';
$senha = '';
$banco = 'ga3_stockly';

$conexao = new mysqli($host, $usuario_db, $senha, $banco);

if ($conexao->connect_error) {
    die("Erro na conexão: " . $conexao->connect_error);
}

// Verificar se o formulário foi enviado
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Receber dados do formulário
    $nome = $_POST['nome'] ?? '';
    $email = $_POST['email'] ?? '';
    $telefone = $_POST['telefone'] ?? '';
    $assunto = $_POST['assunto'] ?? '';
    $mensagem = $_POST['mensagem'] ?? '';

    // Verificar se todos os campos obrigatórios foram preenchidos
    if (!empty($nome) && !empty($email) && !empty($mensagem)) {
        // Inserir no banco (tabela com prefixo ga3_)
        $sql = "INSERT INTO ga3_contato (nome, email, telefone, assunto, mensagem, data_envio) VALUES (?, ?, ?, ?, ?, NOW())";
        $stmt = $conexao->prepare($sql);
        $stmt->bind_param("sssss", $nome, $email, $telefone, $assunto, $mensagem);

        if ($stmt->execute()) {
            $mensagem_sucesso = "Mensagem enviada com sucesso!";
        } else {
            $mensagem_erro = "Erro ao enviar mensagem: " . $conexao->error;
        }

        $stmt->close();
    } else {
        $mensagem_erro = "Preencha todos os campos obrigatórios (nome, email, mensagem).";
    }
}

$conexao->close();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contato - Stockly</title>
    <link rel="stylesheet" href="../css/styleprincipal.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
      /* Variáveis de cores e estilos */
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

/* Menu Off-Screen */
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
  background-color: var(--light-bg);
  color: var(--primary-color);
}

.off-screen-menu ul li ul {
  padding: 0;
}

.off-screen-menu ul li ul li a {
  padding-left: 3rem;
  font-size: 0.9rem;
}

/* Page Banner */
.page-banner {
  background: linear-gradient(135deg, var(--secondary-color), var(--primary-color));
  color: white;
  padding: 60px 0;
  text-align: center;
  margin-bottom: 40px;
}

.page-banner h1 {
  font-size: 2.5rem;
  margin: 0;
  font-weight: 600;
}

.page-banner p {
  font-size: 1.2rem;
  max-width: 800px;
  margin: 15px auto 0;
}

/* Contact Container */
.contact-container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 20px 60px;
}

.contact-flex {
  display: flex;
  flex-wrap: wrap;
  gap: 30px;
  justify-content: space-between;
}

.contact-form-container,
.contact-info-container {
  margin-top:30px;
  flex: 1;
  min-width: 300px;
  background: white;
  padding: 30px;
  border-radius: 10px;
  box-shadow: var(--box-shadow);
}

/* Formulário */
.contact-form {
  display: flex;
  flex-direction: column;
}

.form-group {
  margin-bottom: 20px;
}

.form-group label {
  display: block;
  margin-bottom: 8px;
  font-weight: 500;
  color: #555;
}

.form-control {
  width: 100%;
  padding: 12px 15px;
  border: 1px solid var(--border-color);
  border-radius: 5px;
  font-size: 16px;
  transition: var(--transition);
}

.form-control:focus {
  border-color: var(--primary-color);
  outline: none;
  box-shadow: 0 0 0 3px rgba(94, 167, 165, 0.2);
}

textarea.form-control {
  min-height: 150px;
  resize: vertical;
}

/* Botões */
.btn {
  background-color: var(--primary-color);
  color: white;
  border: none;
  padding: 14px 25px;
  font-size: 16px;
  font-weight: 600;
  border-radius: 5px;
  cursor: pointer;
  transition: var(--transition);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.btn:hover {
  background-color: var(--accent-color);
  transform: translateY(-2px);
}

/* Alertas */
.alert {
  padding: 15px;
  border-radius: 5px;
  margin-bottom: 20px;
  font-weight: 500;
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

/* Informações de Contato */
.contact-info-title {
  font-size: 1.5rem;
  color: var(--primary-color);
  margin-top: 0;
  margin-bottom: 25px;
  padding-bottom: 15px;
  border-bottom: 2px solid #f0f0f0;
}

.contact-info-item {
  display: flex;
  align-items: flex-start;
  margin-bottom: 25px;
}

.contact-info-icon {
  background-color: #eef7f6;
  color: var(--primary-color);
  width: 50px;
  height: 50px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
  margin-right: 15px;
  flex-shrink: 0;
}

.contact-info-content h4 {
  margin: 0 0 5px;
  font-size: 1.1rem;
  color: #444;
}

.contact-info-content p {
  margin: 0;
  color: #666;
}

/* WhatsApp Box */
.whatsapp-box {
  background-color: #e6f7ef;
  padding: 20px;
  border-radius: 8px;
  margin-top: 30px;
  display: flex;
  align-items: center;
}

.whatsapp-icon {
  background-color: #25d366;
  color: white;
  width: 50px;
  height: 50px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
  margin-right: 15px;
  flex-shrink: 0;
}

.whatsapp-content h4 {
  margin: 0 0 5px;
  color: #075e54;
}

.whatsapp-content p {
  margin: 0;
  font-weight: 500;
  font-size: 1.1rem;
}

/* Links Sociais */
.social-links {
  display: flex;
  gap: 15px;
  margin-top: 25px;
}

.social-link {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  background-color: #eef7f6;
  color: var(--primary-color);
  border-radius: 50%;
  font-size: 18px;
  transition: var(--transition);
}

.social-link:hover {
  background-color: var(--primary-color);
  color: white;
  transform: translateY(-3px);
}

/* Mapa */
.map-container {
  margin-top: 40px;
  border-radius: 10px;
  overflow: hidden;
  box-shadow: var(--box-shadow);
}

.map-container iframe {
  width: 100%;
  height: 300px;
  border: none;
  display: block;
}

/* FAQ */
.faq-section {
  margin-top: 60px;
}

.faq-title {
  text-align: center;
  font-size: 2rem;
  margin-bottom: 40px;
  color: var(--primary-color);
}

.faq-container {
  max-width: 800px;
  margin: 0 auto;
}

.faq-item {
  background: white;
  border-radius: 8px;
  margin-bottom: 15px;
  box-shadow: var(--box-shadow);
  overflow: hidden;
}

.faq-question {
  padding: 20px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  cursor: pointer;
  font-weight: 600;
  color: #444;
}

.faq-answer {
  padding: 0 20px;
  max-height: 0;
  overflow: hidden;
  transition: max-height 0.3s ease, padding 0.3s ease;
  color: #666;
}

.faq-item.active .faq-answer {
  padding: 0 20px 20px;
  max-height: 300px;
}

.faq-icon {
  font-size: 1.2rem;
  transition: transform 0.3s ease;
}

.faq-item.active .faq-icon {
  transform: rotate(180deg);
}

/* Footer */
.site-footer {
  background-color: #333;
  color: #fff;
  padding: 50px 0 20px;
}

.footer-content {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  margin-bottom: 30px;
}

.footer-logo img {
  margin-bottom: 15px;
}

.footer-logo p {
  color: #ddd;
  max-width: 250px;
}

.footer-links h4,
.footer-contact h4 {
  color: #fff;
  margin-bottom: 20px;
  position: relative;
  padding-bottom: 10px;
}

.footer-links h4:after,
.footer-contact h4:after {
  content: "";
  position: absolute;
  bottom: 0;
  left: 0;
  width: 50px;
  height: 2px;
  background-color: var(--primary-color);
}

.footer-links ul {
  list-style: none;
  padding: 0;
}

.footer-links ul li {
  margin-bottom: 10px;
}

.footer-links ul li a {
  color: #ddd;
  text-decoration: none;
  transition: color 0.3s ease;
}

.footer-links ul li a:hover {
  color: var(--primary-color);
}

.footer-contact p {
  margin-bottom: 10px;
  display: flex;
  align-items: center;
}

.footer-contact p i {
  margin-right: 10px;
  color: var(--primary-color);
}

.social-icons {
  display: flex;
  gap: 15px;
  margin-top: 20px;
}

.social-icons a {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  background-color: rgba(255, 255, 255, 0.1);
  color: #fff;
  border-radius: 50%;
  transition: all 0.3s ease;
}

.social-icons a:hover {
  background-color: var(--primary-color);
  transform: translateY(-3px);
}

.footer-bottom {
  text-align: center;
  padding-top: 20px;
  border-top: 1px solid rgba(255, 255, 255, 0.1);
}

/* Responsividade */
@media (max-width: 768px) {
  .page-banner {
    padding: 40px 0;
  }

  .page-banner h1 {
    font-size: 2rem;
  }

  .contact-form-container,
  .contact-info-container {
    flex: 100%;
  }

  .footer-content > div {
    flex: 100%;
    margin-bottom: 30px;
  }
}

/* Hamburger Menu Animation */
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

 </style>
</head>
<body>
   <!-- Header (same as in the original file) -->
   

    <!-- Conteúdo principal -->
    <div class="contact-container">
        <?php if (isset($mensagem_sucesso)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $mensagem_sucesso; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($mensagem_erro)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo $mensagem_erro; ?>
            </div>
        <?php endif; ?>

        <div class="contact-flex">
            <!-- Formulário de contato -->
            <div class="contact-form-container">
                <h2>Envie sua mensagem</h2>
                <form class="contact-form" method="POST">
                    <div class="form-group">
                        <label for="nome">Nome completo *</label>
                        <input type="text" id="nome" name="nome" class="form-control" placeholder="Digite seu nome completo" required value="<?php echo htmlspecialchars($usuario['nome']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="email">E-mail *</label>
                        <input type="email" id="email" name="email" class="form-control" placeholder="seu.email@exemplo.com" required value="<?php echo htmlspecialchars($usuario['email']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="telefone">Telefone</label>
                        <input type="tel" id="telefone" name="telefone" class="form-control" placeholder="(00) 00000-0000">
                    </div>
                    
                    <div class="form-group">
                        <label for="assunto">Assunto *</label>
                        <select id="assunto" name="assunto" class="form-control" required>
                            <option value="">Selecione um assunto</option>
                            <option value="Suporte técnico">Suporte técnico</option>
                            <option value="Dúvidas sobre o sistema">Dúvidas sobre o sistema</option>
                            <option value="Sugestões de melhorias">Sugestões de melhorias</option>
                            <option value="Reportar um problema">Reportar um problema</option>
                            <option value="Outros">Outros</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="mensagem">Mensagem *</label>
                        <textarea id="mensagem" name="mensagem" class="form-control" placeholder="Descreva sua mensagem em detalhes..." required></textarea>
                    </div>
                    
                    <button type="submit" class="btn">
                        <i class="fas fa-paper-plane"></i> Enviar mensagem
                    </button>
                </form>
            </div>

            <!-- Informações de contato -->
            <div class="contact-info-container">
                <h3 class="contact-info-title">Informações de Contato</h3>
                
                <div class="contact-info-item">
                    <div class="contact-info-icon">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <div class="contact-info-content">
                        <h4>Endereço</h4>
                        <p>Rua Octávio Rodrigues de Souza, 350<br>Parque Paduan, Taubaté - SP<br>CEP: 12081-200</p>
                    </div>
                </div>
                
                <div class="contact-info-item">
                    <div class="contact-info-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div class="contact-info-content">
                        <h4>E-mail</h4>
                        <p>stocklymanagment@gmail.com</p>
                        <p>suporte@stockly.com</p>
                    </div>
                </div>
                
                <div class="contact-info-item">
                    <div class="contact-info-icon">
                        <i class="fas fa-phone-alt"></i>
                    </div>
                    <div class="contact-info-content">
                        <h4>Telefone</h4>
                        <p>(12) 99106-1742</p>
                    </div>
                </div>
                
                <div class="contact-info-item">
                    <div class="contact-info-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="contact-info-content">
                        <h4>Horário de Atendimento</h4>
                        <p>Segunda a Sexta: 8h às 18h</p>
                        <p>Sábado: 9h às 13h</p>
                    </div>
                </div>
                
                <div class="whatsapp-box">
                    <div class="whatsapp-icon">
                        <i class="fab fa-whatsapp"></i>
                    </div>
                    <div class="whatsapp-content">
                        <h4>Atendimento via WhatsApp</h4>
                        <p>(12) 99102-0460</p>
                    </div>
                </div>
                
                <div class="social-links">
                    <a href="#" class="social-link"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" class="social-link"><i class="fab fa-instagram"></i></a>
                    <a href="#" class="social-link"><i class="fab fa-linkedin-in"></i></a>
                    <a href="#" class="social-link"><i class="fab fa-twitter"></i></a>
                    <a href="#" class="social-link"><i class="fab fa-youtube"></i></a>
                </div>
            </div>
        </div>
        
        <!-- Mapa -->
        <div class="map-container">
            <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3672.8200952855694!2d-45.5766064!3d-23.0176086!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x94cc4bc8557809f3%3A0x3e9f3a76e1ebfb69!2sR.%20Octavio%20Rodrigues%20de%20Souza%2C%20350%20-%20Parque%20Paduan%2C%20Taubat%C3%A9%20-%20SP%2C%2012081-200!5e0!3m2!1spt-BR!2sbr!4v1715274845231!5m2!1spt-BR!2sbr" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
        </div>
        
        <!-- FAQ Section -->
        <div class="faq-section">
            <h2 class="faq-title">Perguntas Frequentes</h2>
            <div class="faq-container">
                <div class="faq-item">
                    <div class="faq-question">
                        <span>Como posso resetar minha senha?</span>
                        <i class="fas fa-chevron-down faq-icon"></i>
                    </div>
                    <div class="faq-answer">
                        <p>Para configurar alertas de estoque baixo, acesse o menu "Configurações" e depois "Alertas de Estoque". Defina o nível mínimo de estoque para cada categoria ou produto específico e selecione o tipo de notificação desejada (e-mail, notificação no sistema ou ambos).</p>
                    </div>
                </div>

                
                <div class="faq-item">
                    <div class="faq-question">
                        <span>Como adicionar um novo usuário ao sistema?</span>
                        <i class="fas fa-chevron-down faq-icon"></i>
                    </div>
                    <div class="faq-answer">
                        <p>Apenas usuários com cargo de gerente ou chefe podem adicionar novos usuários. Acesse o menu "Administrar Funcionários" e clique em "Adicionar login". Preencha todos os dados necessários e defina o nível de acesso do novo usuário.</p>
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question">
                        <span>Como exportar relatórios do sistema?</span>
                        <i class="fas fa-chevron-down faq-icon"></i>
                    </div>
                    <div class="faq-answer">
                        <p>Na página de Relatórios, selecione o período desejado e o tipo de relatório. Clique no botão "Exportar" e escolha o formato desejado (PDF, Excel ou CSV). O arquivo será automaticamente baixado para o seu dispositivo.</p>
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question">
                        <span>O sistema funciona em dispositivos móveis?</span>
                        <i class="fas fa-chevron-down faq-icon"></i>
                    </div>
                    <div class="faq-answer">
                        <p>Sim, o Stockly é totalmente responsivo e funciona em qualquer dispositivo com acesso à internet. Você pode gerenciar seu estoque pelo computador, tablet ou smartphone sem perder nenhuma funcionalidade.</p>
                    </div>
                </div>
                
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

    <!-- JavaScript -->
    <script>

        // Hamburger Menu Toggle Function
function toggleMenu() {
    const hamMenu = document.querySelector('.ham-menu');
    
    // Toggle active class on hamburger menu
    hamMenu.classList.toggle('active');
    
    // Toggle active class on off-screen menu
    offScreenMenu.classList.toggle('active');
}

// Add event listener when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    const hamMenu = document.querySelector('.ham-menu');
    
    if (hamMenu) {
        // Remove previous event listener to prevent multiple bindings
        hamMenu.removeEventListener('click', toggleMenu);
        hamMenu.addEventListener('click', toggleMenu);
    }
});
        // Dropdown menu
        document.addEventListener('DOMContentLoaded', function() {
            const dropdownBtn = document.getElementById('dropdownButton');
            const dropdownContent = document.getElementById('dropdownContent');

            if (dropdownBtn && dropdownContent) {
                dropdownBtn.addEventListener('click', function() {
                    dropdownContent.classList.toggle('show');
                });

                // Close the dropdown when clicking outside
                window.addEventListener('click', function(event) {
                    if (!event.target.matches('.dropbtn') && !event.target.matches('.perfil-foto')) {
                        if (dropdownContent.classList.contains('show')) {
                            dropdownContent.classList.remove('show');
                        }
                    }
                });
            }
            
            // Off-screen menu toggle
            window.toggleMenu = function() {
                const menu = document.querySelector('.off-screen-menu');
                menu.classList.toggle('active');
            }
            
            // FAQ accordion
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
            
            // Form validation
            const contactForm = document.querySelector('.contact-form');
            
            if (contactForm) {
                contactForm.addEventListener('submit', function(e) {
                    const nome = document.getElementById('nome').value;
                    const email = document.getElementById('email').value;
                    const assunto = document.getElementById('assunto').value;
                    const mensagem = document.getElementById('mensagem').value;
                    
                    if (!nome || !email || !assunto || !mensagem) {
                        e.preventDefault();
                        alert('Por favor, preencha todos os campos obrigatórios.');
                    }
                    
                    // Email validation
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test(email)) {
                        e.preventDefault();
                        alert('Por favor, insira um endereço de e-mail válido.');
                    }
                });
            }
            
            // Phone number mask
            const telefoneInput = document.getElementById('telefone');
            
            if (telefoneInput) {
                telefoneInput.addEventListener('input', function(e) {
                    let value = e.target.value.replace(/\D/g, '');
                    
                    if (value.length > 0) {
                        value = '(' + value;
                    }
                    if (value.length > 3) {
                        value = value.substring(0, 3) + ') ' + value.substring(3);
                    }
                    if (value.length > 10) {
                        value = value.substring(0, 10) + '-' + value.substring(10, 15);
                    }
                    
                    e.target.value = value;
                });
            }
        });

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

</body>
</html>

