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
require '../class/User.php';

$user = new User($pdo);


// Получение пользователя из базы данных
    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = :username');
    $stmt->execute(['username' => $_SESSION['username']]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);

//var_dump($userData);
// Добавьте подгруппы для получения общего числа пользователей, новостей и обратной связи
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalNews = $pdo->query("SELECT COUNT(*) FROM blogs")->fetchColumn();
$totalFeedback = $pdo->query("SELECT COUNT(*) FROM blogs_contacts")->fetchColumn();

if (isset($userData['isadmin']) && $userData['isadmin'] == 9) {
    // Определите, какую часть админки показать
    $view = $_GET['view'] ?? 'dashboard';
    switch ($view) {
        case 'manage_users':
            $template = 'manage_users.tpl';
			$pageTitle = 'Управление пользователями';
            break;
        case 'manage_feedback':
            $template = 'manage_feedback.tpl';
			$pageTitle = 'Обратная связь';
            break;
        case 'add_news':
            $template = 'add_news.tpl';
			$pageTitle = 'Добавить пост';
            break;
        default:
            $template = 'dashboard.tpl';
			$pageTitle = 'Статистика';
            break;
    }

    // Передача данных для dashboard.tpl
    $templateVariables = [
		'pageTitle' => $pageTitle,
        'totalUsers' => $totalUsers,
        'totalNews' => $totalNews,
        'totalFeedback' => $totalFeedback,
    ];

    echo renderTemplate($template, $templateVariables);
} else {
/*    // Отображение формы входа для администратора
$referer = $_SERVER['HTTP_REFERER'];
header('Location: '.$referer);
die;*/
}

// Функция для отображения шаблона с данными
function renderTemplate($template, $variables = [])
{
    extract($variables); // Извлекаем переменные из массива
    ob_start();
    include "templates/$template";
    return ob_get_clean();
}
?>