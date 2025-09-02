<?php
// Configurações de conexão com o banco de dados
$host = 'localhost';
$usuario = 'root';     // Altere conforme suas configurações
$senha = '';           // Altere conforme suas configurações
$banco = 'ga3_stockly';    // Nome do banco de dados com prefixo ga3_

// Criar conexão
$conexao = new mysqli($host, $usuario, $senha, $banco);

// Verificar conexão
if ($conexao->connect_error) {
    die("Falha na conexão: " . $conexao->connect_error);
}

// Configurar charset para UTF-8
$conexao->set_charset("utf8");