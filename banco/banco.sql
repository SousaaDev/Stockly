-- Criar o banco de dados
CREATE DATABASE ga3_stockly;
USE ga3_stockly;

-- Criar a tabela de categorias
CREATE TABLE ga3_categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL
);

-- Criar a tabela de usuários
CREATE TABLE ga3_usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    senha VARCHAR(255) NOT NULL,
    cargo ENUM('chefe', 'gerente', 'funcionario') NOT NULL DEFAULT 'funcionario',
    lembrar_me BOOLEAN DEFAULT FALSE,
    data_nascimento DATE,
    endereco VARCHAR(255),
    foto_perfil VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    tentativas_login INT DEFAULT 0,
    ultimo_login_falho DATETIME DEFAULT NULL
);


-- Criar a tabela de sessões
CREATE TABLE ga3_sessoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT,
    token VARCHAR(255) UNIQUE NOT NULL,
    expiracao DATETIME NOT NULL,
    ip VARCHAR(45) DEFAULT NULL,
    FOREIGN KEY (usuario_id) REFERENCES ga3_usuarios(id)
);

-- Criar a tabela de logs de login
CREATE TABLE ga3_login_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    ip VARCHAR(45) NOT NULL,
    user_agent TEXT NOT NULL,
    data DATETIME NOT NULL,
    FOREIGN KEY (usuario_id) REFERENCES ga3_usuarios(id)
);



-- Criar a tabela de atividades
CREATE TABLE ga3_atividades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT,
    atividade VARCHAR(255),
    data TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES ga3_usuarios(id)
);

-- Criar a tabela de materiais
CREATE TABLE ga3_materiais (
    id INT AUTO_INCREMENT PRIMARY KEY,
    descricao VARCHAR(255) NOT NULL,
    quantidade INT NOT NULL,
    valor_unitario_estoque DECIMAL(10, 2) NOT NULL,
    valor_unitario_venda_estimado DECIMAL(10, 2) NOT NULL,
    categoria_id INT,
    codigo_identificacao VARCHAR(20),
    valor_unitario_venda DECIMAL(10,2) DEFAULT NULL,
    FOREIGN KEY (categoria_id) REFERENCES ga3_categorias(id)
);

-- Criar a tabela de despesas (estrutura corrigida)
CREATE TABLE ga3_despesas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    material_id INT NOT NULL,
    descricao VARCHAR(255) NOT NULL,
    valor DECIMAL(10, 2) NOT NULL,
    data_despesa DATE DEFAULT CURRENT_DATE,
    FOREIGN KEY (material_id) REFERENCES ga3_materiais(id)
);

-- Criar a tabela de recuperação de senha (CORRIGIDO)
CREATE TABLE ga3_recuperacao_senha (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    codigo VARCHAR(6) DEFAULT NULL,
    token VARCHAR(64) DEFAULT NULL,
    expiracao DATETIME NOT NULL,
    usado BOOLEAN DEFAULT FALSE,
    tentativas INT DEFAULT 0,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES ga3_usuarios(id)
);

-- Criar a tabela de transações
CREATE TABLE ga3_transacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quantidade INT NOT NULL,
    material_id INT NOT NULL,
    valor_venda DECIMAL(10, 2) NOT NULL,
    custo_material DECIMAL(10, 2) NOT NULL,
    data_hora DATETIME NOT NULL,
    lucro_bruto DECIMAL(10, 2) GENERATED ALWAYS AS (valor_venda - custo_material) STORED,
    data_venda DATE NOT NULL,
    usuario_id INT,
    FOREIGN KEY (material_id) REFERENCES ga3_materiais(id),
    CONSTRAINT fk_usuario_id FOREIGN KEY (usuario_id) REFERENCES ga3_usuarios(id)
);

-- Criar a tabela de contato
CREATE TABLE ga3_contato (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    telefone VARCHAR(20),
    assunto VARCHAR(255),
    mensagem TEXT NOT NULL,
    data_envio TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Criar a tabela de vendas
CREATE TABLE ga3_vendas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    data_venda DATE NOT NULL,
    receita DECIMAL(10, 2) NOT NULL,
    lucro_bruto DECIMAL(10, 2) NOT NULL
);

-- Cadastro do usuário admin
INSERT INTO ga3_usuarios (nome, email, senha, cargo, data_nascimento, endereco) 
VALUES ('admin', 'admin@gmail.com', '$2y$10$8UvwgAchHvpd3cLhdTBzNOjVevLEl0v2XcEwz2w7Z0raJNVwhWRH.', 'chefe', '1975-06-20', 'Av. Principal, 1000');