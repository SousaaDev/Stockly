<?php
session_start();
unset($_SESSION['usuario']);
setcookie('token', '', time() - 3600, "/");
header('Location: ../index.php');
exit();
?>
