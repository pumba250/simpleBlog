<?php
session_start();
$config = require '../config/config.php';
try {
	// Получаем необходимые параметры из конфигурационного файла
    $host = $config['host'];
    $database = $config['database'];
    $db_user = $config['db_user'];
    $db_pass = $config['db_pass'];
	// Устанавливаем соединение с базой данных
    $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}

if (!isset($_SESSION['isadmin']) || (int)$_SESSION['isadmin'] !== 9) {
    header('Location: index.php');
    exit();
}

if (isset($_GET['id'])) {
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$_GET['id']]);
}

header('Location: index.php?view=manage_users&deleted=1');
?>