<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Управление пользователями</title>
</head>
<body>
    <h1>Управление пользователями</h1>
    <table>
        <tr>
            <th>ID</th>
            <th>Логин</th>
            <th>Email</th>
            <th>Действия</th>
        </tr>
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
        // Получить список пользователей для отображения
        $stmt = $pdo->query("SELECT * FROM `users`");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "<tr>
                    <td>{$row['id']}</td>
                    <td>{$row['username']}</td>
                    <td>{$row['email']}</td>
                    <td><a href='delete_user.php?id={$row['id']}'>Удалить</a></td>
                  </tr>";
        }
        ?>
    </table>
</body>
</html>