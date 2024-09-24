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
require_once '../class/News.php'; // Обязательно подключите класс
$newsId = $_GET['id'] ?? null; // Получаем ID новости из параметров URL
$news = new News($pdo);

$currentNews = $news->getNewsById($newsId); // Метод для получения информации о новости
$tags = $news->getTagsByNewsId($newsId); // Метод для получения тегов для этой новости

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Обработка формы редактирования
    $title = $_POST['title'];
    $content = $_POST['content'];

    // Генерация новых тегов
    $newTags = $news->generateTags($title, $content);

    // Обновление новости
    $news->updateNews($newsId, $title, $content, $newTags);

    header('Location: index.php'); // Перенаправление после завершения
}
?>

<!-- HTML код для формы редактирования -->
<form method="POST">
    <input type="text" name="title" value="<?= htmlspecialchars($currentNews['title']) ?>" required><br>
    <textarea name="content" required><?= ($currentNews['content']) ?></textarea><br>
    <button type="submit">Сохранить изменения</button>
</form>

<!-- Отображение текущих тегов -->
<h4>Текущие теги:</h4>
<?php if ($tags): ?>
    <?php foreach ($tags as $tag): ?>
        <span class="w3-tag w3-light-grey w3-small"><?= htmlspecialchars($tag['name']) ?></span>
    <?php endforeach; ?>
<?php else: ?>
    <p>Нет тегов</p>
<?php endif; ?>