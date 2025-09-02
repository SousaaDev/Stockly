<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ga3_stockly";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$material_id = $_GET['material_id'];

$sql = "SELECT quantidade FROM ga3_materiais WHERE id = $material_id";
$result = $conn->query($sql);

$quantidade = 0;

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $quantidade = $row['quantidade'];
}

echo json_encode(array('quantidade' => $quantidade));

$conn->close();
?>