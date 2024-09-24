<?php
// process_add_news.php
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


if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    header('Location: index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	require_once '../class/News.php';
	$news = new News($pdo);
    $title = $_POST['title'];
    $content = $_POST['content'];
	
    // Генерация тегов
    $tags = $news->generateTags($title, $content);

    // Сохранение новости
    $stmt = $pdo->prepare("INSERT INTO blogs (title, content) VALUES (?, ?)");
    $stmt->execute([$title, $content]);
    $newsId = $pdo->lastInsertId();

    // Сохранение тегов в БД
    foreach ($tags as $tag) {
        // Проверка, существует ли тег в БД
        $stmt = $pdo->prepare("INSERT INTO tags (name) VALUES (?) ON DUPLICATE KEY UPDATE id=id");
        $stmt->execute([$tag]);
        
        // Получаем ID тега
        $tagId = $pdo->lastInsertId();

        // Связываем тег с новостью
        $stmt = $pdo->prepare("INSERT INTO blogs_tags (blogs_id, tag_id) VALUES (?, ?)");
        $stmt->execute([$newsId, $tagId]);
    }

    header('Location: index.php?view=add_news&success=1');
}
?>