<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Обратная связь</title>
</head>
<body>
    <h1>Обратная связь</h1>
    <table>
        <tr>
            <th>ID</th>
            <th>Имя</th>
            <th>Email</th>
            <th>Сообщение</th>
            <th>Дата</th>
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

        // Получить список обратной связи
        $stmt = $pdo->query("SELECT * FROM blogs_contacts ORDER BY created_at DESC");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "<tr>
                    <td>{$row['id']}</td>
                    <td>{$row['name']}</td>
                    <td>{$row['email']}</td>
                    <td>{$row['message']}</td>
                    <td>{$row['created_at']}</td>
                    <td>
                        <form action='delete_message.php' method='POST' onsubmit='return confirm(\"Вы уверены, что хотите удалить это сообщение?\");'>
                            <input type='hidden' name='id' value='{$row['id']}'>
                            <input type='submit' value='Удалить'>
                        </form>
                    </td>
                  </tr>";
        }
        ?>
    </table>
</body>
</html>