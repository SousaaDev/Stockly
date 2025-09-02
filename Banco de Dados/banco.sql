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

-- Criar a tabela de dados financeiros
CREATE TABLE ga3_dados_financeiros (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mes VARCHAR(50) NOT NULL,
    vendas INT NOT NULL,
    compras INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Criar a tabela de estoque
CREATE TABLE ga3_estoque (
    id INT AUTO_INCREMENT PRIMARY KEY,
    produto VARCHAR(255) NOT NULL,
    quantidade INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
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
    FOREIGN KEY (categoria_id) REFERENCES ga3_categorias(id)
);

-- Criar a tabela de despesas
CREATE TABLE ga3_despesas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    material_id INT NOT NULL,
    valor DECIMAL(10, 2) NOT NULL,
    data_despesa DATE DEFAULT CURRENT_DATE,
    FOREIGN KEY (material_id) REFERENCES ga3_materiais(id)
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
    FOREIGN KEY (material_id) REFERENCES ga3_materiais(id)
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

CREATE TABLE ga3_vendas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    data_venda DATE NOT NULL,
    receita DECIMAL(10, 2) NOT NULL,
    lucro_bruto DECIMAL(10, 2) NOT NULL
);

CREATE TABLE ga3_produtos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    descricao VARCHAR(255) NOT NULL,
    preco_unitario DECIMAL(10, 2) NOT NULL,
    quantidade INT NOT NULL
);

ALTER TABLE ga3_materiais ADD COLUMN valor_unitario_venda DECIMAL(10,2) DEFAULT NULL;

ALTER TABLE ga3_transacoes
ADD COLUMN usuario_id INT,
ADD CONSTRAINT fk_usuario_id
FOREIGN KEY (usuario_id) REFERENCES ga3_usuarios(id);

-- Inserir dados na tabela de categorias
INSERT INTO ga3_categorias (nome) VALUES
('Eletrônicos'),
('Roupas'),
('Alimentos'),
('Móveis'),
('Livros'),
('Brinquedos'),
('Esportes'),
('Beleza');

-- Inserir dados na tabela de usuários
INSERT INTO ga3_usuarios (nome, email, senha, cargo, data_nascimento, endereco) VALUES
('Carlos Silva', 'carlos@gmail.com', '$2y$10$8UvwgAchHvpd3cLhdTBzNOjVevLEl0v2XcEwz2w7Z0raJNVwhWRH.', 'funcionario', '1990-05-15', 'Rua A, 123'),
('Maria Oliveira', 'maria@gmail.com', '$2y$10$8UvwgAchHvpd3cLhdTBzNOjVevLEl0v2XcEwz2w7Z0raJNVwhWRH.', 'gerente', '1985-08-22', 'Av. B, 456'),
('João Santos', 'joao@gmail.com', '$2y$10$8UvwgAchHvpd3cLhdTBzNOjVevLEl0v2XcEwz2w7Z0raJNVwhWRH.', 'funcionario', '1992-11-10', 'Rua C, 789'),
('Ana Costa', 'ana@gmail.com', '$2y$10$8UvwgAchHvpd3cLhdTBzNOjVevLEl0v2XcEwz2w7Z0raJNVwhWRH.', 'funcionario', '1995-03-25', 'Av. D, 101'),
('Roberto Almeida', 'roberto@gmail.com', '$2y$10$8UvwgAchHvpd3cLhdTBzNOjVevLEl0v2XcEwz2w7Z0raJNVwhWRH.', 'gerente', '1980-12-05', 'Rua E, 202');

-- Cadastro do usuário admin
INSERT INTO ga3_usuarios (nome, email, senha, cargo, data_nascimento, endereco) 
VALUES ('admin', 'admin@gmail.com', '$2y$10$8UvwgAchHvpd3cLhdTBzNOjVevLEl0v2XcEwz2w7Z0raJNVwhWRH.', 'chefe', '1975-06-20', 'Av. Principal, 1000');

-- Inserir dados na tabela de materiais
INSERT INTO ga3_materiais (descricao, quantidade, valor_unitario_estoque, valor_unitario_venda_estimado, categoria_id, codigo_identificacao) VALUES
('Smartphone Samsung Galaxy', 100, 1500.00, 1800.00, 1, 'ELEC-001'),
('Camiseta Básica', 200, 30.00, 50.00, 2, 'CLOTH-001'),
('Arroz 5kg', 500, 20.00, 30.00, 3, 'FOOD-001'),
('Sofá 3 Lugares', 20, 2000.00, 2500.00, 4, 'FURN-001'),
('Livro de Programação', 150, 50.00, 70.00, 5, 'BOOK-001'),
('Notebook Dell', 50, 3000.00, 3800.00, 1, 'ELEC-002'),
('Calça Jeans', 180, 80.00, 120.00, 2, 'CLOTH-002'),
('Feijão 1kg', 450, 8.00, 12.00, 3, 'FOOD-002'),
('Mesa de Jantar', 15, 1200.00, 1700.00, 4, 'FURN-002'),
('Livro Harry Potter', 100, 40.00, 65.00, 5, 'BOOK-002'),
('Boneco Action Figure', 80, 50.00, 90.00, 6, 'TOY-001'),
('Bola de Futebol', 120, 40.00, 60.00, 7, 'SPORT-001'),
('Kit Maquiagem', 70, 60.00, 100.00, 8, 'BEAUTY-001'),
('Tablet Apple iPad', 40, 2500.00, 3200.00, 1, 'ELEC-003'),
('Vestido Casual', 90, 70.00, 120.00, 2, 'CLOTH-003'),
('Açúcar 1kg', 400, 5.00, 7.50, 3, 'FOOD-003'),
('Poltrona Reclinável', 25, 900.00, 1300.00, 4, 'FURN-003'),
('Livro O Senhor dos Anéis', 80, 45.00, 75.00, 5, 'BOOK-003'),
('Console de Videogame', 30, 2000.00, 2500.00, 1, 'ELEC-004'),
('Jaqueta de Inverno', 70, 150.00, 250.00, 2, 'CLOTH-004'),
('TV Smart 55"', 5, 3200.00, 4000.00, 1, 'ELEC-005'),
('Shampoo Premium', 8, 25.00, 40.00, 8, 'BEAUTY-002');

-- Inserir dados na tabela de despesas para vários meses
INSERT INTO ga3_despesas (material_id, valor, data_despesa) VALUES
-- Abril
(1, 5000.00, '2025-04-01'), -- Despesas com Smartphone Samsung
(2, 2000.00, '2025-04-03'), -- Despesas com Camisetas
(3, 1500.00, '2025-04-05'), -- Despesas com Arroz
(4, 10000.00, '2025-04-07'), -- Despesas com Sofás
(5, 2500.00, '2025-04-10'), -- Despesas com Livros
(6, 15000.00, '2025-04-12'), -- Despesas com Notebooks
(7, 4000.00, '2025-04-15'), -- Despesas com Calças
-- Março
(8, 1200.00, '2025-03-05'), -- Despesas com Feijão
(9, 6000.00, '2025-03-08'), -- Despesas com Mesas
(10, 1600.00, '2025-03-12'), -- Despesas com Harry Potter
(11, 1500.00, '2025-03-15'), -- Despesas com Action Figures
(12, 1800.00, '2025-03-20'), -- Despesas com Bolas
-- Fevereiro
(13, 1400.00, '2025-02-05'), -- Despesas com Kit Maquiagem
(14, 30000.00, '2025-02-10'), -- Despesas com iPads
(15, 2100.00, '2025-02-15'), -- Despesas com Vestidos
(16, 800.00, '2025-02-20'), -- Despesas com Açúcar
-- Janeiro
(17, 9000.00, '2025-01-10'), -- Despesas com Poltronas
(18, 1200.00, '2025-01-15'), -- Despesas com Senhor dos Anéis
(19, 20000.00, '2025-01-20'), -- Despesas com Consoles
(20, 3500.00, '2025-01-25'); -- Despesas com Jaquetas

-- Inserir dados na tabela de transações para vários dias
INSERT INTO ga3_transacoes (quantidade, material_id, valor_venda, custo_material, data_hora, data_venda) VALUES
-- Abril (mês atual) - Dias mais recentes
(10, 1, 18000.00, 15000.00, '2025-04-01 10:00:00', '2025-04-01'),
(15, 2, 750.00, 450.00, '2025-04-02 11:00:00', '2025-04-02'),
(20, 3, 600.00, 400.00, '2025-04-03 12:00:00', '2025-04-03'),
(5, 6, 19000.00, 15000.00, '2025-04-04 09:30:00', '2025-04-04'),
(12, 7, 1440.00, 960.00, '2025-04-05 14:15:00', '2025-04-05'),
(8, 11, 720.00, 400.00, '2025-04-06 16:00:00', '2025-04-06'),
(10, 12, 600.00, 400.00, '2025-04-07 11:30:00', '2025-04-07'),
(7, 13, 700.00, 420.00, '2025-04-08 15:45:00', '2025-04-08'),
(3, 14, 9600.00, 7500.00, '2025-04-09 10:15:00', '2025-04-09'),
(9, 15, 1080.00, 630.00, '2025-04-10 13:20:00', '2025-04-10'),
(25, 16, 187.50, 125.00, '2025-04-11 09:00:00', '2025-04-11'),
(2, 17, 2600.00, 1800.00, '2025-04-12 14:30:00', '2025-04-12'),
(11, 18, 825.00, 495.00, '2025-04-13 16:15:00', '2025-04-13'),
(4, 19, 10000.00, 8000.00, '2025-04-14 11:45:00', '2025-04-14'),
(6, 20, 1500.00, 900.00, '2025-04-15 10:30:00', '2025-04-15'),
(2, 4, 5000.00, 4000.00, '2025-04-16 14:00:00', '2025-04-16'),
(5, 5, 350.00, 250.00, '2025-04-17 15:30:00', '2025-04-17'),
(8, 8, 96.00, 64.00, '2025-04-18 13:45:00', '2025-04-18'),
(1, 9, 1700.00, 1200.00, '2025-04-19 09:15:00', '2025-04-19'),
(5, 10, 325.00, 200.00, '2025-04-20 16:00:00', '2025-04-20'),
(1, 21, 4000.00, 3200.00, '2025-04-21 08:30:00', '2025-04-21'),

-- Março (mês anterior)
(18, 1, 32400.00, 27000.00, '2025-03-02 10:45:00', '2025-03-02'),
(22, 2, 1100.00, 660.00, '2025-03-05 13:20:00', '2025-03-05'),
(30, 3, 900.00, 600.00, '2025-03-08 15:00:00', '2025-03-08'),
(7, 6, 26600.00, 21000.00, '2025-03-10 11:15:00', '2025-03-10'),
(15, 7, 1800.00, 1200.00, '2025-03-12 14:30:00', '2025-03-12'),
(10, 11, 900.00, 500.00, '2025-03-15 10:00:00', '2025-03-15'),
(14, 12, 840.00, 560.00, '2025-03-18 09:45:00', '2025-03-18'),
(9, 13, 900.00, 540.00, '2025-03-20 16:30:00', '2025-03-20'),
(5, 14, 16000.00, 12500.00, '2025-03-22 13:15:00', '2025-03-22'),
(12, 15, 1440.00, 840.00, '2025-03-25 11:00:00', '2025-03-25'),
(2, 21, 8000.00, 6400.00, '2025-03-28 10:15:00', '2025-03-28'),
(3, 22, 120.00, 75.00, '2025-03-30 14:45:00', '2025-03-30'),

-- Fevereiro
(12, 1, 21600.00, 18000.00, '2025-02-03 09:30:00', '2025-02-03'),
(18, 2, 900.00, 540.00, '2025-02-07 15:45:00', '2025-02-07'),
(25, 3, 750.00, 500.00, '2025-02-10 12:15:00', '2025-02-10'),
(4, 6, 15200.00, 12000.00, '2025-02-14 11:30:00', '2025-02-14'),
(10, 7, 1200.00, 800.00, '2025-02-18 14:00:00', '2025-02-18'),
(6, 11, 540.00, 300.00, '2025-02-22 16:45:00', '2025-02-22'),
(3, 21, 12000.00, 9600.00, '2025-02-25 13:30:00', '2025-02-25'),
(4, 22, 160.00, 100.00, '2025-02-28 10:00:00', '2025-02-28'),

-- Janeiro
(8, 1, 14400.00, 12000.00, '2025-01-05 14:30:00', '2025-01-05'),
(15, 2, 750.00, 450.00, '2025-01-10 11:15:00', '2025-01-10'),
(20, 3, 600.00, 400.00, '2025-01-15 10:45:00', '2025-01-15'),
(3, 6, 11400.00, 9000.00, '2025-01-20 15:00:00', '2025-01-20'),
(8, 7, 960.00, 640.00, '2025-01-25 13:45:00', '2025-01-25'),
(5, 21, 20000.00, 16000.00, '2025-01-30 09:30:00', '2025-01-30');

-- Inserir dados na tabela de vendas resumidas por dia
INSERT INTO ga3_vendas (data_venda, receita, lucro_bruto) VALUES
-- Abril
('2025-04-01', 18000.00, 3000.00),
('2025-04-02', 750.00, 300.00),
('2025-04-03', 600.00, 200.00),
('2025-04-04', 19000.00, 4000.00),
('2025-04-05', 1440.00, 480.00),
('2025-04-06', 720.00, 320.00),
('2025-04-07', 600.00, 200.00),
('2025-04-08', 700.00, 280.00),
('2025-04-09', 9600.00, 2100.00),
('2025-04-10', 1080.00, 450.00),
('2025-04-11', 187.50, 62.50),
('2025-04-12', 2600.00, 800.00),
('2025-04-13', 825.00, 330.00),
('2025-04-14', 10000.00, 2000.00),
('2025-04-15', 1500.00, 600.00),
('2025-04-16', 5000.00, 1000.00),
('2025-04-17', 350.00, 100.00),
('2025-04-18', 96.00, 32.00),
('2025-04-19', 1700.00, 500.00),
('2025-04-20', 325.00, 125.00),
('2025-04-21', 4000.00, 800.00),

-- Março
('2025-03-02', 32400.00, 5400.00),
('2025-03-05', 1100.00, 440.00),
('2025-03-08', 900.00, 300.00),
('2025-03-10', 26600.00, 5600.00),
('2025-03-12', 1800.00, 600.00),
('2025-03-15', 900.00, 400.00),
('2025-03-18', 840.00, 280.00),
('2025-03-20', 900.00, 360.00),
('2025-03-22', 16000.00, 3500.00),
('2025-03-25', 1440.00, 600.00),
('2025-03-28', 8000.00, 1600.00),
('2025-03-30', 120.00, 45.00),

-- Fevereiro
('2025-02-03', 21600.00, 3600.00),
('2025-02-07', 900.00, 360.00),
('2025-02-10', 750.00, 250.00),
('2025-02-14', 15200.00, 3200.00),
('2025-02-18', 1200.00, 400.00),
('2025-02-22', 540.00, 240.00),
('2025-02-25', 12000.00, 2400.00),
('2025-02-28', 160.00, 60.00),

-- Janeiro
('2025-01-05', 14400.00, 2400.00),
('2025-01-10', 750.00, 300.00),
('2025-01-15', 600.00, 200.00),
('2025-01-20', 11400.00, 2400.00),
('2025-01-25', 960.00, 320.00),
('2025-01-30', 20000.00, 4000.00);

-- Inserir dados na tabela de dados financeiros
INSERT INTO ga3_dados_financeiros (mes, vendas, compras) VALUES
('Janeiro', 48110, 33640),
('Fevereiro', 52350, 40290),
('Março', 91000, 50000),
('Abril', 79073, 40000);

-- Adicionar produtos com estoque baixo para testar os alertas
INSERT INTO ga3_materiais (descricao, quantidade, valor_unitario_estoque, valor_unitario_venda_estimado, categoria_id, codigo_identificacao) VALUES
('Fone de Ouvido Bluetooth', 5, 80.00, 120.00, 1, 'ELEC-006'),
('Carregador Tipo C', 3, 25.00, 45.00, 1, 'ELEC-007'),
('Mouse sem fio', 4, 40.00, 65.00, 1, 'ELEC-008'),
('Perfume Importado', 2, 150.00, 220.00, 8, 'BEAUTY-003');