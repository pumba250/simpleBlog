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
require_once '../class/News.php';
$newsId = $_GET['id'] ?? null;

if ($newsId) {
    $news = new News($pdo);
    $news->deleteNews($newsId);
    header('Location: index.php'); // Перенаправление после удаления
}