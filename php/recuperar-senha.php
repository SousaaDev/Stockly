<?php
include('conectar.php');

// Iniciar sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Função para limpar entrada
function limparEntrada($dados) {
    $dados = trim($dados);
    $dados = stripslashes($dados);
    $dados = htmlspecialchars($dados);
    return $dados;
}

// Redirecionar se já estiver logado
if (isset($_SESSION['usuario'])) {
    header('Location: ../estoque/dashboard.php');
    exit();
}

$erro = null;
$sucesso = null;
$etapa = 'email'; // etapas: email, codigo, nova_senha

// Verificar se há email na sessão para avançar etapas
if (isset($_SESSION['email_recuperacao']) && !isset($_POST['solicitar_recuperacao'])) {
    if (!isset($_SESSION['codigo_validado'])) {
        $etapa = 'codigo';
    } else {
        $etapa = 'nova_senha';
    }
}

// ============================================
// Enviar email com código usando SendGrid
// ============================================
function enviarCodigoEmail($emailDestino, $nomeUsuario, $codigo) {
    $apiKey = 'add api';
    
    $data = [
        'personalizations' => [
            [
                'to' => [['email' => $emailDestino, 'name' => $nomeUsuario]],
                'subject' => 'Código de Recuperação de Senha - Stockly'
            ]
        ],
        'from' => ['email' => 'stocklysuport@gmail.com', 'name' => 'Stockly'],
        'content' => [
            [
                'type' => 'text/html',
                'value' => gerarHTMLEmailCodigo($nomeUsuario, $codigo)
            ]
        ]
    ];
    
    $ch = curl_init('https://api.sendgrid.com/v3/mail/send');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $httpCode == 202;
}

// Função para gerar HTML do email com código
function gerarHTMLEmailCodigo($nomeUsuario, $codigo) {
    return "
<html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #2a9d8f 0%, #1e7376 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { padding: 30px; background: #f9f9f9; }
            .codigo-box { 
                background: white;
                border: 3px dashed #2a9d8f;
                padding: 30px;
                margin: 30px 0;
                text-align: center;
                border-radius: 10px;
            }
            .codigo {
                font-size: 48px;
                font-weight: bold;
                color: #2a9d8f;
                letter-spacing: 10px;
                font-family: 'Courier New', monospace;
            }
            .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; background: #f0f0f0; border-radius: 0 0 10px 10px; }
            .alert-box { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1 style='margin: 0;'>🔒 Código de Recuperação</h1>
            </div>
            <div class='content'>
                <p>Olá, <strong>" . htmlspecialchars($nomeUsuario) . "</strong>!</p>
                <p>Recebemos uma solicitação para redefinir a senha da sua conta no <strong>Stockly</strong>.</p>
                <p>Use o código abaixo para continuar com a recuperação:</p>
                
                <div class='codigo-box'>
                    <p style='margin: 0 0 10px 0; font-size: 14px; color: #666;'>SEU CÓDIGO DE VERIFICAÇÃO</p>
                    <div class='codigo'>" . $codigo . "</div>
                </div>
                
                <div class='alert-box'>
                    <strong>⏱️ Atenção:</strong> Este código expira em <strong>15 minutos</strong> por questões de segurança.
                </div>
                
                <hr style='border: none; border-top: 1px solid #ddd; margin: 30px 0;'>
                <p style='font-size: 12px; color: #666;'>
                    <strong>⚠️ Não solicitou esta alteração?</strong><br>
                    Se você não solicitou a redefinição de senha, ignore este email. Sua senha permanecerá inalterada e sua conta está segura.
                </p>
            </div>
            <div class='footer'>
                <p><strong>Stockly</strong> - Sistema de Gestão de Estoque</p>
                <p>&copy; " . date('Y') . " Stockly. Todos os direitos reservados.</p>
                <p style='margin-top: 10px;'>
                    📧 Suporte: <a href='mailto:suporte@stockly.com' style='color: #2a9d8f;'>suporte@stockly.com</a>
                </p>
            </div>
        </div>
    </body>
    </html>
    ";
}

// Processar formulário de solicitação de recuperação (Enviar código)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['solicitar_recuperacao'])) {
    $email = limparEntrada($_POST['email']);
    
    if (empty($email)) {
        $erro = "Por favor, informe seu email.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = "Formato de email inválido.";
    } else {
        // Verificar se o email existe
        $stmt = $conexao->prepare("SELECT id, nome FROM ga3_usuarios WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $usuario = $result->fetch_assoc();
            
            // Gerar código de 6 dígitos
            $codigo = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
            $expiracao = date('Y-m-d H:i:s', strtotime('+15 minutes'));
            
            // Verificar se a tabela de recuperação existe, caso não, criar
            $checkTable = $conexao->query("SHOW TABLES LIKE 'ga3_recuperacao_senha'");
            if ($checkTable->num_rows == 0) {
                $conexao->query("CREATE TABLE ga3_recuperacao_senha (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    usuario_id INT NOT NULL,
                    codigo VARCHAR(6) NOT NULL,
                    token VARCHAR(64) DEFAULT NULL,
                    expiracao DATETIME NOT NULL,
                    usado BOOLEAN DEFAULT FALSE,
                    tentativas INT DEFAULT 0,
                    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )");
            }
            
            // Invalidar códigos antigos deste usuário
            $deleteStmt = $conexao->prepare("UPDATE ga3_recuperacao_senha SET usado = 1 WHERE usuario_id = ? AND usado = 0");
            $deleteStmt->bind_param("i", $usuario['id']);
            $deleteStmt->execute();
            $deleteStmt->close();
            
            // Inserir novo código
            $insertStmt = $conexao->prepare("INSERT INTO ga3_recuperacao_senha (usuario_id, codigo, expiracao) VALUES (?, ?, ?)");
            $insertStmt->bind_param("iss", $usuario['id'], $codigo, $expiracao);
            $insertStmt->execute();
            $insertStmt->close();
            
            // Enviar email com código
            $emailEnviado = enviarCodigoEmail($email, $usuario['nome'], $codigo);
            
            if ($emailEnviado) {
                $_SESSION['email_recuperacao'] = $email;
                $_SESSION['usuario_id_recuperacao'] = $usuario['id'];
                $etapa = 'codigo';
                $sucesso = "✅ Um código de verificação foi enviado para <strong>" . $email . "</strong>. Verifique sua caixa de entrada e spam.";
            } else {
                // Fallback para desenvolvimento
                $_SESSION['email_recuperacao'] = $email;
                $_SESSION['usuario_id_recuperacao'] = $usuario['id'];
                $etapa = 'codigo';
                $sucesso = "⚠️ Não foi possível enviar o email automaticamente.<br><br>
                           <strong>Código de recuperação (apenas para testes):</strong> <span style='font-size: 20px; font-weight: bold; color: #2a9d8f;'>" . $codigo . "</span><br><br>
                           <small style='color: #666;'>Este código expira em 15 minutos.</small>";
            }
        } else {
            // Por segurança, não revelar se o email existe ou não
            $sucesso = "Se o email informado estiver cadastrado, você receberá um código de verificação.";
        }
        $stmt->close();
    }
}

// Processar verificação de código
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['verificar_codigo'])) {
    if (!isset($_SESSION['email_recuperacao']) || !isset($_SESSION['usuario_id_recuperacao'])) {
        $erro = "Sessão inválida. Por favor, solicite um novo código.";
        $etapa = 'email';
    } else {
        $codigoDigitado = limparEntrada($_POST['codigo']);
        $usuarioId = $_SESSION['usuario_id_recuperacao'];
        
        if (empty($codigoDigitado)) {
            $erro = "Por favor, informe o código.";
            $etapa = 'codigo';
        } elseif (strlen($codigoDigitado) != 6 || !ctype_digit($codigoDigitado)) {
            $erro = "O código deve conter 6 dígitos.";
            $etapa = 'codigo';
        } else {
            // Verificar código
            $stmt = $conexao->prepare("SELECT id, tentativas FROM ga3_recuperacao_senha WHERE usuario_id = ? AND codigo = ? AND expiracao > NOW() AND usado = 0");
            $stmt->bind_param("is", $usuarioId, $codigoDigitado);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows == 1) {
                $row = $result->fetch_assoc();
                
                // Código válido
                $_SESSION['codigo_validado'] = true;
                $_SESSION['recuperacao_id'] = $row['id'];
                $etapa = 'nova_senha';
                $sucesso = "✅ Código verificado com sucesso! Agora defina sua nova senha.";
            } else {
                // Código inválido - incrementar tentativas
                $updateStmt = $conexao->prepare("UPDATE ga3_recuperacao_senha SET tentativas = tentativas + 1 WHERE usuario_id = ? AND usado = 0 AND expiracao > NOW()");
                $updateStmt->bind_param("i", $usuarioId);
                $updateStmt->execute();
                $updateStmt->close();
                
                // Verificar se excedeu tentativas
                $checkStmt = $conexao->prepare("SELECT tentativas FROM ga3_recuperacao_senha WHERE usuario_id = ? AND usado = 0 AND expiracao > NOW() ORDER BY id DESC LIMIT 1");
                $checkStmt->bind_param("i", $usuarioId);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();
                
                if ($checkResult->num_rows > 0) {
                    $tentativas = $checkResult->fetch_assoc()['tentativas'];
                    if ($tentativas >= 5) {
                        // Bloquear após 5 tentativas
                        $blockStmt = $conexao->prepare("UPDATE ga3_recuperacao_senha SET usado = 1 WHERE usuario_id = ? AND usado = 0");
                        $blockStmt->bind_param("i", $usuarioId);
                        $blockStmt->execute();
                        $blockStmt->close();
                        
                        $erro = "Muitas tentativas inválidas. Por favor, solicite um novo código.";
                        unset($_SESSION['email_recuperacao']);
                        unset($_SESSION['usuario_id_recuperacao']);
                        $etapa = 'email';
                    } else {
                        $erro = "Código inválido. Tentativa " . $tentativas . " de 5.";
                        $etapa = 'codigo';
                    }
                } else {
                    $erro = "Código inválido ou expirado.";
                    $etapa = 'codigo';
                }
                $checkStmt->close();
            }
            $stmt->close();
        }
    }
}

// Processar formulário de nova senha
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['redefinir_senha'])) {
    if (!isset($_SESSION['codigo_validado']) || !isset($_SESSION['usuario_id_recuperacao'])) {
        $erro = "Sessão inválida. Por favor, solicite um novo código.";
        $etapa = 'email';
    } else {
        $novaSenha = $_POST['nova_senha'];
        $confirmarSenha = $_POST['confirmar_senha'];
        
        if (empty($novaSenha) || empty($confirmarSenha)) {
            $erro = "Por favor, preencha todos os campos.";
            $etapa = 'nova_senha';
        } elseif (strlen($novaSenha) < 6) {
            $erro = "A senha deve ter no mínimo 6 caracteres.";
            $etapa = 'nova_senha';
        } elseif ($novaSenha !== $confirmarSenha) {
            $erro = "As senhas não coincidem.";
            $etapa = 'nova_senha';
        } else {
            $usuarioId = $_SESSION['usuario_id_recuperacao'];
            
            // Atualizar senha
            $senhaHash = password_hash($novaSenha, PASSWORD_DEFAULT);
            $updateStmt = $conexao->prepare("UPDATE ga3_usuarios SET senha = ? WHERE id = ?");
            $updateStmt->bind_param("si", $senhaHash, $usuarioId);
            
            if ($updateStmt->execute()) {
                // Marcar código como usado
                $markStmt = $conexao->prepare("UPDATE ga3_recuperacao_senha SET usado = 1 WHERE usuario_id = ? AND usado = 0");
                $markStmt->bind_param("i", $usuarioId);
                $markStmt->execute();
                $markStmt->close();
                
                // Limpar sessão
                unset($_SESSION['email_recuperacao']);
                unset($_SESSION['usuario_id_recuperacao']);
                unset($_SESSION['codigo_validado']);
                unset($_SESSION['recuperacao_id']);
                
                $sucesso = "✅ Senha redefinida com sucesso! Você já pode fazer login com sua nova senha.";
                $etapa = 'concluido';
            } else {
                $erro = "Erro ao redefinir senha. Tente novamente.";
                $etapa = 'nova_senha';
            }
            $updateStmt->close();
        }
    }
}

// Botão para solicitar novo código
if (isset($_POST['reenviar_codigo']) && isset($_SESSION['email_recuperacao'])) {
    $email = $_SESSION['email_recuperacao'];
    
    $stmt = $conexao->prepare("SELECT id, nome FROM ga3_usuarios WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $usuario = $result->fetch_assoc();
        
        // Gerar novo código
        $codigo = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiracao = date('Y-m-d H:i:s', strtotime('+15 minutes'));
        
        // Invalidar códigos antigos
        $deleteStmt = $conexao->prepare("UPDATE ga3_recuperacao_senha SET usado = 1 WHERE usuario_id = ? AND usado = 0");
        $deleteStmt->bind_param("i", $usuario['id']);
        $deleteStmt->execute();
        $deleteStmt->close();
        
        // Inserir novo código
        $insertStmt = $conexao->prepare("INSERT INTO ga3_recuperacao_senha (usuario_id, codigo, expiracao) VALUES (?, ?, ?)");
        $insertStmt->bind_param("iss", $usuario['id'], $codigo, $expiracao);
        $insertStmt->execute();
        $insertStmt->close();
        
        // Enviar email
        $emailEnviado = enviarCodigoEmail($email, $usuario['nome'], $codigo);
        
        if ($emailEnviado) {
            $sucesso = "✅ Um novo código foi enviado para seu email.";
        } else {
            $sucesso = "⚠️ Novo código gerado (apenas para testes): <span style='font-size: 20px; font-weight: bold; color: #2a9d8f;'>" . $codigo . "</span>";
        }
        $etapa = 'codigo';
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Senha | Sistema de Gestão</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="shortcut icon" href="../img/favicon.ico" type="image/x-icon">
    <style>
        .alerts-container {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-bottom: 20px;
        }

        .alert {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 15px;
            border-radius: 8px;
            font-size: 14px;
            line-height: 1.5;
            width: 100%;
        }

        .alert i {
            font-size: 18px;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .alert span {
            flex: 1;
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

        .codigo-input {
            text-align: center;
            font-size: 32px;
            letter-spacing: 10px;
            font-weight: bold;
            font-family: 'Courier New', monospace;
        }

        .reenviar-codigo {
            text-align: center;
            margin-top: 15px;
            font-size: 14px;
        }

        .reenviar-codigo button {
            background: none;
            border: none;
            color: #667eea;
            text-decoration: underline;
            cursor: pointer;
            font-size: 14px;
        }

        .reenviar-codigo button:hover {
            color: #5568d3;
        }

        .password-container {
            position: relative;
            width: 100%;
        }

        .password-container input {
            width: 100%;
            padding-right: 45px;
        }


    </style>
</head>
<body>
    <div class="login-page">
        <div class="login-wrapper">
            <div class="login-card">
                <div class="logo-container">
                    <div class="company-logo">
                        <img class="logo" src="../imagens/logo.png" alt="">
                    </div>
                </div>
                
                <div class="login-content">
                    <div class="login-header">
                        <h1>🔒 Recuperar Senha</h1>
                        <p>
                            <?php 
                            if ($etapa == 'email') {
                                echo "Informe seu email para receber um código";
                            } elseif ($etapa == 'codigo') {
                                echo "Digite o código enviado para seu email";
                            } elseif ($etapa == 'nova_senha') {
                                echo "Defina sua nova senha";
                            } elseif ($etapa == 'concluido') {
                                echo "Senha redefinida com sucesso!";
                            }
                            ?>
                        </p>
                    </div>
                    
                    <div class="alerts-container">
                        <?php if (isset($erro)) { ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle"></i>
                                <span><?= $erro ?></span>
                            </div>
                        <?php } ?>
                        
                        <?php if (isset($sucesso)) { ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i>
                                <span><?= $sucesso ?></span>
                            </div>
                        <?php } ?>
                    </div>
                    
                    <?php if ($etapa == 'email') { ?>
                        <form action="<?= htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" class="login-form">
                            <div class="form-group">
                                <label for="email">
                                    <i class="fas fa-envelope"></i>
                                    <span>Email</span>
                                </label>
                                <input type="email" id="email" name="email" 
                                       placeholder="nome@empresa.com" required>
                            </div>
                            
                            <div class="form-action">
                                <button type="submit" name="solicitar_recuperacao" class="btn-login">
                                    <span>Enviar Código</span>
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </div>
                        </form>
                    
                    <?php } elseif ($etapa == 'codigo') { ?>
                        <form action="<?= htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" class="login-form">
                            <div class="form-group">
                                <label for="codigo">
                                    <i class="fas fa-key"></i>
                                    <span>Código de Verificação</span>
                                </label>
                                <input type="text" id="codigo" name="codigo" 
                                       class="codigo-input"
                                       placeholder="000000" 
                                       required 
                                       maxlength="6" 
                                       pattern="[0-9]{6}"
                                       inputmode="numeric"
                                       autocomplete="off">
                                <small style="color: #666; font-size: 12px; display: block; text-align: center; margin-top: 10px;">
                                    Digite o código de 6 dígitos
                                </small>
                            </div>
                            
                            <div class="form-action">
                                <button type="submit" name="verificar_codigo" class="btn-login">
                                    <span>Verificar Código</span>
                                    <i class="fas fa-check"></i>
                                </button>
                            </div>
                        </form>
                        
                        <div class="reenviar-codigo">
                            <form method="POST" action="<?= htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                                Não recebeu o código? 
                                <button type="submit" name="reenviar_codigo">Reenviar código</button>
                            </form>
                        </div>
                    
                    <?php } elseif ($etapa == 'nova_senha') { ?>
                        <form action="<?= htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" class="login-form">
                            <div class="form-group">
                                <label for="nova_senha">
                                    <i class="fas fa-lock"></i>
                                    <span>Nova Senha</span>
                                </label>
                                <div class="password-container">
                                    <input type="password" id="nova_senha" name="nova_senha" 
                                           placeholder="Digite sua nova senha" required minlength="6">
                                   
                                </div>
                                <small style="color: #666; font-size: 12px;">Mínimo de 6 caracteres</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="confirmar_senha">
                                    <i class="fas fa-lock"></i>
                                    <span>Confirmar Senha</span>
                                </label>
                                <div class="password-container">
                                    <input type="password" id="confirmar_senha" name="confirmar_senha" 
                                           placeholder="Confirme sua nova senha" required minlength="6">
                            
                                </div>
                            </div>
                            
                            <div class="form-action">
                                <button type="submit" name="redefinir_senha" class="btn-login">
                                    <span>Redefinir Senha</span>
                                    <i class="fas fa-check"></i>
                                </button>
                            </div>
                        </form>
                    
                    <?php } elseif ($etapa == 'concluido') { ?>
                        <div class="form-action">
                            <a href="../index.php" class="btn-login" style="text-decoration: none; display: flex; align-items: center; justify-content: center;">
                                <span>Ir para Login</span>
                                <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    <?php } ?>
                    
                    <div class="form-action" style="margin-top: 15px;">
                        <a href="../index.php" style="text-align: center; display: block; color: #667eea; text-decoration: none;">
                            <i class="fas fa-arrow-left"></i> Voltar para login
                        </a>
                    </div>
                </div>
                
                <div class="login-footer">
                    <p>&copy; <?= date('Y') ?> Stockly • Todos os direitos reservados</p>
                    <p class="support">Suporte: <a href="mailto:suporte@stockly.com">suporte@stockly.com</a></p>
                </div>
            </div>
            
            <div class="login-image">
                <div class="overlay"></div>
                <div class="quote-container">
                    <blockquote>
                        "A segurança da sua conta é nossa prioridade."
                    </blockquote>
                    <cite>Equipe Stockly</cite>
                </div>
            </div>
        </div>
    </div>

    <script>
    function togglePassword(inputId, iconId) {
        const passwordInput = document.getElementById(inputId);
        const toggleIcon = document.getElementById(iconId);
        
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
    
    // Validação de senhas em tempo real
    const novaSenha = document.getElementById('nova_senha');
    const confirmarSenha = document.getElementById('confirmar_senha');
    
    if (confirmarSenha) {
        confirmarSenha.addEventListener('input', function() {
            if (novaSenha.value !== confirmarSenha.value) {
                confirmarSenha.setCustomValidity('As senhas não coincidem');
            } else {
                confirmarSenha.setCustomValidity('');
            }
        });
        
        novaSenha.addEventListener('input', function() {
            if (confirmarSenha.value !== '') {
                if (novaSenha.value !== confirmarSenha.value) {
                    confirmarSenha.setCustomValidity('As senhas não coincidem');
                } else {
                    confirmarSenha.setCustomValidity('');
                }
            }
        });
    }
    
    // Auto-formatação do input de código (apenas números)
    const codigoInput = document.getElementById('codigo');
    if (codigoInput) {
        codigoInput.addEventListener('input', function(e) {
            // Remove qualquer caractere que não seja número
            this.value = this.value.replace(/[^0-9]/g, '');
            
            // Limita a 6 dígitos
            if (this.value.length > 6) {
                this.value = this.value.slice(0, 6);
            }
        });
        
        // Auto-submit quando digitar 6 dígitos
        codigoInput.addEventListener('input', function() {
            if (this.value.length === 6) {
                // Pequeno delay para melhor UX
                setTimeout(() => {
                    this.form.submit();
                }, 300);
            }
        });
        
        // Focar automaticamente no input quando a página carregar
        codigoInput.focus();
    }