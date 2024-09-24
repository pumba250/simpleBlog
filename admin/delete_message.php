<?php
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
require_once '../class/Contact.php';
    // Вызов метода для удаления сообщения
    $contactManager = new Contact($pdo);
    if ($contactManager->deleteMessage($id)) {
        // Успешно удалено, можно перенаправить пользователя
        header("Location: index.php?view=manage_feedback"); // Замените на имя вашего файла с обратной связью
        exit;
    } else {
        echo "Ошибка при удалении сообщения.";
    }
}
?>