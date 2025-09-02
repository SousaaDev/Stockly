<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ga3_stockly";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Calcular o total de despesas
$sql_total_despesas = "SELECT SUM(valor) as total_despesas FROM ga3_despesas";
$result_total_despesas = $conn->query($sql_total_despesas);
$total_despesas = 0;

if ($result_total_despesas->num_rows > 0) {
    $row = $result_total_despesas->fetch_assoc();
    $total_despesas = $row['total_despesas'] ?: 0; // Garantir que não seja null
}

// Certifique-se de que o valor é um número
$total_despesas = floatval($total_despesas);

echo json_encode(array('total_despesas' => $total_despesas));

$conn->close();
?>