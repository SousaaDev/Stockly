<?php
include('php/conectar.php');

// Iniciar sessão se ainda não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Função para limpar e validar entrada
function limparEntrada($dados) {
    $dados = trim($dados);
    $dados = stripslashes($dados);
    $dados = htmlspecialchars($dados);
    return $dados;
}

// Verificar se o usuário já está logado
if (isset($_SESSION['usuario'])) {
    // Se já estiver logado, redireciona para a página principal
    header('Location: estoque/dashboard.php');
    exit();
}

// Verificar se existe um token de "lembrar-me" válido
if (!isset($_SESSION['usuario']) && isset($_COOKIE['token'])) {
    $token = limparEntrada($_COOKIE['token']);

    $stmt = $conexao->prepare("SELECT ga3_usuarios.* FROM ga3_sessoes JOIN ga3_usuarios ON ga3_sessoes.usuario_id = ga3_usuarios.id WHERE ga3_sessoes.token = ? AND ga3_sessoes.expiracao > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $usuario = $result->fetch_assoc();
        $_SESSION['usuario'] = $usuario;
        
        // Gerar novo token para aumentar segurança
        $novoToken = bin2hex(random_bytes(32));
        $expiracao = date('Y-m-d H:i:s', strtotime('+5 days'));
        
        // Atualizar token na base de dados
        $updateStmt = $conexao->prepare("UPDATE ga3_sessoes SET token = ?, expiracao = ? WHERE token = ?");
        $updateStmt->bind_param("sss", $novoToken, $expiracao, $token);
        $updateStmt->execute();
        $updateStmt->close();
        
        // Atualizar cookie
        setcookie('token', $novoToken, time() + (86400 * 5), "/", "", false, true); // httponly flag, secure removido para ambientes de teste
        
        // Redireciona para a página principal
        header('Location: estoque/dashboard.php');
        exit();
    }
    
    $stmt->close();
}

// Tratar tentativas de login
$erro = null;
$successMsg = null;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['email']) && isset($_POST['senha'])) {
        $email = limparEntrada($_POST['email']);
        $senha = $_POST['senha'];
        
        // Validações básicas
        if (empty($email) || empty($senha)) {
            $erro = "Por favor, preencha todos os campos.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erro = "Formato de e-mail inválido.";
        } else {
            // Consulta preparada para evitar injeção SQL
            $stmt = $conexao->prepare("SELECT * FROM ga3_usuarios WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $resultado = $stmt->get_result();
            
            if ($resultado->num_rows == 1) {
                $usuario = $resultado->fetch_assoc();
                if (password_verify($senha, $usuario['senha'])) {
                    $_SESSION['usuario'] = $usuario;
                    $_SESSION['login_time'] = time();
                    
                    // Registrar login bem-sucedido - Verificando se a tabela existe
                    try {
                        $ip = $_SERVER['REMOTE_ADDR'];
                        $agent = $_SERVER['HTTP_USER_AGENT'];
                        
                        // Verifica se a tabela login_logs existe
                        $checkTable = $conexao->query("SHOW TABLES LIKE 'ga3_login_logs'");
                        if ($checkTable->num_rows > 0) {
                            $logStmt = $conexao->prepare("INSERT INTO ga3_login_logs (usuario_id, ip, user_agent, data) VALUES (?, ?, ?, NOW())");
                            $logStmt->bind_param("iss", $usuario['id'], $ip, $agent);
                            $logStmt->execute();
                            $logStmt->close();
                        }
                    } catch (Exception $e) {
                        // Ignora erros de log - não críticos para o funcionamento do login
                    }

                    // Processar opção "lembrar-me"
                    if (isset($_POST['lembrar'])) {
                        $token = bin2hex(random_bytes(32)); // Token mais longo para segurança
                        $expiracao = date('Y-m-d H:i:s', strtotime('+5 days'));

                        // Remover tokens antigos deste usuário
                        $deleteStmt = $conexao->prepare("DELETE FROM ga3_sessoes WHERE usuario_id = ?");
                        $deleteStmt->bind_param("i", $usuario['id']);
                        $deleteStmt->execute();
                        $deleteStmt->close();
                        
                        // Inserir novo token - ajuste de campos disponíveis na tabela sessoes
                        try {
                            // Verificar estrutura da tabela sessoes
                            $colunas = $conexao->query("SHOW COLUMNS FROM ga3_sessoes LIKE 'ip'");
                            
                            if ($colunas->num_rows > 0) {
                                // Se a coluna IP existir
                                $ip = $_SERVER['REMOTE_ADDR'];
                                $insertStmt = $conexao->prepare("INSERT INTO ga3_sessoes (usuario_id, token, expiracao, ip) VALUES (?, ?, ?, ?)");
                                $insertStmt->bind_param("isss", $usuario['id'], $token, $expiracao, $ip);
                            } else {
                                // Sem coluna IP
                                $insertStmt = $conexao->prepare("INSERT INTO ga3_sessoes (usuario_id, token, expiracao) VALUES (?, ?, ?)");
                                $insertStmt->bind_param("iss", $usuario['id'], $token, $expiracao);
                            }
                            
                            $insertStmt->execute();
                            $insertStmt->close();
                            
                            setcookie('token', $token, time() + (86400 * 5), "/", "", false, true); // httponly flag, secure removido para ambientes de teste
                        } catch (Exception $e) {
                            // Ignora erros relacionados à sessão - não críticos
                        }
                    }

                    header('Location: estoque/dashboard.php');
                    exit();
                } else {
                    // Verificar se a tabela tem os campos necessários antes de incrementar contadores
                    try {
                        $checkCol = $conexao->query("SHOW COLUMNS FROM ga3_usuarios LIKE 'tentativas_login'");
                        if ($checkCol->num_rows > 0) {
                            // Incrementar contador de tentativas falhas
                            $failStmt = $conexao->prepare("UPDATE ga3_usuarios SET tentativas_login = tentativas_login + 1, ultimo_login_falho = NOW() WHERE id = ?");
                            $failStmt->bind_param("i", $usuario['id']);
                            $failStmt->execute();
                            $failStmt->close();
                        }
                    } catch (Exception $e) {
                        // Ignora erro se a coluna não existir
                    }
                    
                    // Atraso para dificultar ataques de força bruta
                    sleep(1);
                    $erro = "Email ou senha incorretos. Tente novamente.";
                }
            } else {
                // Atraso para dificultar ataques de força bruta
                sleep(1);
                $erro = "Email ou senha incorretos. Tente novamente.";
            }
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Sistema de Gestão</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="shortcut icon" href="img/favicon.ico" type="image/x-icon">
</head>
<body>
    <div class="login-page">
        <div class="login-wrapper">
            <div class="login-card">
                <div class="logo-container">
                    <!-- Substitua pelo seu logo -->
                    <div class="company-logo">
                        <img class="logo" src="imagens/logo.png" alt="" srcset="">
                    </div>
                </div>
                
                <div class="login-content">
                    <div class="login-header">
                        <h1>Bem-vindo</h1>
                        <p>Acesse sua conta para continuar</p>
                    </div>
                    
                    <?php if (isset($erro)) { ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle"></i>
                            <?= $erro ?>
                        </div>
                    <?php } ?>
                    
                    <?php if (isset($successMsg)) { ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <?= $successMsg ?>
                        </div>
                    <?php } ?>
                    
                    <form id="loginForm" action="<?= htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" class="login-form">
                        <div class="form-group">
                            <label for="email">
                                <i class="fas fa-envelope"></i>
                                <span>Email</span>
                            </label>
                            <input type="email" id="email" name="email" 
                                   value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>"
                                   placeholder="nome@empresa.com" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="password">
                                <i class="fas fa-lock"></i>
                                <span>Senha</span>
                            </label>
                            <div class="password-container">
                                <input type="password" id="password" name="senha" placeholder="Digite sua senha" required>
                                <span class="password-toggle" onclick="togglePassword()">
                                    <i class="fas fa-eye" id="toggleIcon"></i>
                                </span>
                            </div>
                        </div>
                        
                        <div class="form-options">
                            <div class="remember-me">
                                <input type="checkbox" id="lembrar" name="lembrar">
                                <label for="lembrar">Lembrar de mim</label>
                            </div>
                            <a href="recuperar-senha.php" class="forgot-password">Esqueceu a senha?</a>
                        </div>
                        
                        <div class="form-action">
                            <button type="submit" id="loginBtn" class="btn-login">
                                <span>Entrar</span>
                                <i class="fas fa-arrow-right"></i>
                            </button>
                        </div>
                    </form>
                </div>
                
                <div class="login-footer">
                    <p>&copy; <?= date('Y') ?> Stockly • Todos os direitos reservados</p>
                    <p class="support">Suporte: <a href="mailto:suporte@empresa.com">suporte@empresa.com</a></p>
                </div>
            </div>
            
            <div class="login-image">
                <div class="overlay"></div>
                <div class="quote-container">
                    <blockquote>
                        "A eficiência nos negócios é alcançada com organização e gestão inteligente."
                    </blockquote>
                    <cite>Equipe Stockly</cite>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Função para alternar visibilidade da senha
    function togglePassword() {
        const passwordInput = document.getElementById('password');
        const toggleIcon = document.getElementById('toggleIcon');
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            toggleIcon.classList.remove('fa-eye');
            toggleIcon.classList.add('fa-eye-slash');
        } else {
            passwordInput.type = 'password';
            toggleIcon.classList.remove('fa-eye-slash');
            toggleIcon.classList.add('fa-eye');
        }
    }
    
    // Adicionar efeito de loading no botão quando o formulário for enviado
    document.getElementById('loginForm').addEventListener('submit', function() {
        const loginBtn = document.getElementById('loginBtn');
        loginBtn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Entrando...';
        loginBtn.disabled = true;
    });
    
    // Adicionar classe active nos inputs quando focados
    const inputs = document.querySelectorAll('input');
    inputs.forEach(input => {
        input.addEventListener('focus', function() {
            this.parentElement.classList.add('active');
        });
        
        input.addEventListener('blur', function() {
            if (this.value === '') {
                this.parentElement.classList.remove('active');
            }
        });
        
        // Para campos já preenchidos
        if (input.value !== '') {
            input.parentElement.classList.add('active');
        }
    });
    
    // Função para sugestões de email
    function setupEmailSuggestions() {
        const emailInput = document.getElementById('email');
        const domainList = ['@gmail.com', '@hotmail.com', '@outlook.com', '@yahoo.com', '@icloud.com', '@protonmail.com', '@aol.com', '@live.com', '@msn.com', '@empresa.com'];
        
        // Criar elemento de sugestões
        const suggestionBox = document.createElement('div');
        suggestionBox.className = 'email-suggestions';
        suggestionBox.style.display = 'none';
        emailInput.parentNode.style.position = 'relative';
        emailInput.parentNode.appendChild(suggestionBox);
        
        // Estilizar o elemento de sugestões
        suggestionBox.style.position = 'absolute';
        suggestionBox.style.width = '100%';
        suggestionBox.style.maxHeight = '150px';
        suggestionBox.style.overflowY = 'auto';
        suggestionBox.style.backgroundColor = '#fff';
        suggestionBox.style.border = '1px solid #ddd';
        suggestionBox.style.borderRadius = '4px';
        suggestionBox.style.boxShadow = '0 2px 5px rgba(0,0,0,0.1)';
        suggestionBox.style.zIndex = '1000';
        suggestionBox.style.top = 'calc(100% + 5px)';
        suggestionBox.style.left = '0';
        
        // Evento de input para mostrar sugestões
        emailInput.addEventListener('input', function() {
            const inputValue = this.value.trim();
            
            // Limpar sugestões anteriores
            suggestionBox.innerHTML = '';
            
            // Verificar se tem @ para mostrar as sugestões
            if (inputValue.includes('@')) {
                suggestionBox.style.display = 'none';
                return;
            }
            
            // Se o campo estiver vazio, não mostrar sugestões
            if (inputValue === '') {
                suggestionBox.style.display = 'none';
                return;
            }
            
            // Criar e adicionar sugestões
            domainList.forEach(domain => {
                const suggestion = document.createElement('div');
                suggestion.className = 'suggestion-item';
                suggestion.textContent = inputValue + domain;
                suggestion.style.padding = '8px 12px';
                suggestion.style.cursor = 'pointer';
                suggestion.style.transition = 'background-color 0.2s';
                
                // Hover effect
                suggestion.addEventListener('mouseenter', function() {
                    this.style.backgroundColor = '#f0f0f0';
                });
                
                suggestion.addEventListener('mouseleave', function() {
                    this.style.backgroundColor = 'transparent';
                });
                
                // Clicar na sugestão preenche o campo
                suggestion.addEventListener('click', function() {
                    emailInput.value = this.textContent;
                    suggestionBox.style.display = 'none';
                    emailInput.focus();
                });
                
                suggestionBox.appendChild(suggestion);
            });
            
            // Mostrar o box de sugestões
            if (suggestionBox.children.length > 0) {
                suggestionBox.style.display = 'block';
            }
        });
        
        // Fechar sugestões quando clicar fora
        document.addEventListener('click', function(e) {
            if (e.target !== emailInput && e.target !== suggestionBox) {
                suggestionBox.style.display = 'none';
            }
        });
        
        // Navegação com teclado nas sugestões
        emailInput.addEventListener('keydown', function(e) {
            const items = suggestionBox.querySelectorAll('.suggestion-item');
            if (items.length === 0) return;
            
            const active = suggestionBox.querySelector('.suggestion-item.active');
            
            // Tecla para baixo
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (!active) {
                    items[0].classList.add('active');
                    items[0].style.backgroundColor = '#e0e0e0';
                } else {
                    active.classList.remove('active');
                    active.style.backgroundColor = 'transparent';
                    
                    const next = active.nextElementSibling || items[0];
                    next.classList.add('active');
                    next.style.backgroundColor = '#e0e0e0';
                    next.scrollIntoView({ block: 'nearest' });
                }
            } 
            // Tecla para cima
            else if (e.key === 'ArrowUp') {
                e.preventDefault();
                if (!active) {
                    items[items.length - 1].classList.add('active');
                    items[items.length - 1].style.backgroundColor = '#e0e0e0';
                } else {
                    active.classList.remove('active');
                    active.style.backgroundColor = 'transparent';
                    
                    const prev = active.previousElementSibling || items[items.length - 1];
                    prev.classList.add('active');
                    prev.style.backgroundColor = '#e0e0e0';
                    prev.scrollIntoView({ block: 'nearest' });
                }
            }
            // Enter para selecionar
            else if (e.key === 'Enter' && active) {
                e.preventDefault();
                emailInput.value = active.textContent;
                suggestionBox.style.display = 'none';
            }
            // Escape para fechar
            else if (e.key === 'Escape') {
                suggestionBox.style.display = 'none';
            }
        });
    }

    // Inicializar a funcionalidade quando o DOM estiver carregado
    document.addEventListener('DOMContentLoaded', function() {
        setupEmailSuggestions();
    });
    </script>
</body>
</html>