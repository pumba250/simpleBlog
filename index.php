<?php
$start = microtime(1);
session_start();
if (!file_exists('config/config.php')) {
	header('Location: install.php')
	die:
}
if (file_exists('install.php')) {
	echo "<font color=red>Удалите файл install.php и директорию sql</font>";
}
$config = require 'config/config.php';
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
require 'class/Template.php';
require 'class/User.php';
require 'class/Contact.php';
require 'class/News.php';
$template = new Template();
$user = new User($pdo);
$contact = new Contact($pdo);
$news = new News($pdo);
$pageTitle = 'simpleBlog';
try {
    // Обработка регистрации
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($_POST['action'] === 'register') {
            $user->register($_POST['username'], $_POST['password'], $_POST['email']);
            $pageTitle = 'Регистрация simpleBlog';
        }
        // Обработка авторизации
        if ($_POST['action'] === 'login') {
            $userData = $user->login($_POST['username'], $_POST['password']);
            if ($userData) {
                $_SESSION['user'] = $userData;
            }
        }
        // Обработка обратной связи
        if ($_POST['action'] === 'contact') {
            if (isset($_POST['captcha']) && $_POST['captcha'] == $_SESSION['captcha_answer']) {
                if ($contact->saveMessage($_POST['name'], $_POST['email'], $_POST['message'])) {
                    $errors[] = "Сообщение успешно отправлено!";
                } else {
                    $errors[] = "Произошла ошибка при отправке сообщения.";
                }
            } else {
                $errors[] = "Неправильный ответ на капчу. Попробуйте еще раз.";
            }
        }
    }
    // Обработка GET-запросов
    if (!isset($_GET['action'])) {
        // Если не указано действие, загружаем главную страницу
        if (isset($_GET['id'])) {
    // Получаем одну новость по id
    $newsId = (int)$_GET['id'];
    $newsItem = $news->getNewsById($newsId); // Получаем одну новость
	// Получение последних 3 новостей
    $lastThreeNews = $news->getLastThreeNews();
    // Получение всех тегов
    $stmt = $pdo->query("SELECT * FROM tags ORDER by `name`");
    $allTags = $stmt->fetchAll(PDO::FETCH_ASSOC);
	// Передача данных тегов и заголовка в шаблон
    $template->assign('allTags', $allTags);
    $template->assign('lastThreeNews', $lastThreeNews);
    $template->assign('user', $_SESSION['user'] ?? null);
	$template->assign('news', $news);
    $template->assign('pageTitle', $pageTitle); // Передача заголовка в шаблон
} else {
    // Загрузка главной страницы
    $pageTitle = 'Главная страница'; // Заголовок для главной страницы
    // Настройка пагинации
    $limit = 6; // Количество новостей на странице
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $offset = ($page - 1) * $limit;
    $allNews = $news->getAllNews($limit, $offset);
    $totalNewsCount = $news->getTotalNewsCount();
    $totalPages = ceil($totalNewsCount / $limit);
    // Получение последних 3 новостей
    $lastThreeNews = $news->getLastThreeNews();
    // Получение всех тегов
    $stmt = $pdo->query("SELECT DISTINCT(`name`) FROM tags");
    $allTags = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Передача данных тегов и заголовка в шаблон
    $template->assign('allTags', $allTags);
    $template->assign('lastThreeNews', $lastThreeNews);
    $template->assign('user', $_SESSION['user'] ?? null);
    $template->assign('news', $allNews);
    $template->assign('totalPages', $totalPages);
    $template->assign('currentPage', $page);
    $template->assign('pageTitle', $pageTitle); // Передача заголовка в шаблон
}
    echo $template->render('home.tpl');
    } else {
        switch ($_GET['action']) {
            case 'register':
                // Загрузка страницы регистрации
                $template->assign('lastThreeNews', $news->getLastThreeNews());
                $template->assign('allTags', $pdo->query("SELECT DISTINCT(`name`) FROM tags")->fetchAll(PDO::FETCH_ASSOC));
    $template->assign('user', $_SESSION['user'] ?? null);
                $template->assign('pageTitle', 'Регистрация simpleBlog');
				    echo $template->render('register.tpl');
                break;
            case 'contact':
                // Загрузка формы обратной связи
                $template->assign('lastThreeNews', $news->getLastThreeNews());
                $template->assign('allTags', $pdo->query("SELECT DISTINCT(`name`) FROM tags")->fetchAll(PDO::FETCH_ASSOC));
    $template->assign('user', $_SESSION['user'] ?? null);
                $template->assign('pageTitle', 'Форма обратной связи simpleBlog');
				    echo $template->render('contact.tpl');
                break;
            default:
                // Обработка 404 ошибки
                header($_SERVER["SERVER_PROTOCOL"] . " 404 Not Found");
                $template->assign('pageTitle', '404 - Страница не найдена simpleBlog');
                echo $template->render('404.tpl');
                exit;
        }
    }
} catch (Exception $e) {
    // Обработка ошибки 500
    error_log($e->getMessage()); // Логирование ошибки
    header($_SERVER["SERVER_PROTOCOL"] . " 500 Internal Server Error");
    $template->assign('pageTitle', '500 - Внутренняя ошибка сервера simpleBlog');
    echo $template->render('500.tpl');
    exit;
}
$finish = microtime(1);
echo 'generation time: ' . round($finish - $start, 5) . ' сек';
