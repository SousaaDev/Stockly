<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ga3_stockly";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}

$sql = "SELECT id, nome FROM ga3_categorias ORDER BY nome";
$result = $conn->query($sql);

$categorias = array();

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $categorias[] = $row;
    }
}

echo json_encode($categorias);

$conn->close();
?>