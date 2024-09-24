<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Панель управления</title>
<style>
body,h1,h2,h3,h4,h5 {font-family: "Raleway", sans-serif;margin-left:30px;}

</style>
</head>
<body>
    <h1>Панель управления</h1>
    
    <h2>Статистика</h2>
    <ul>
        <li>Количество пользователей: <b><?= $totalUsers ?></b></li>
        <li>Количество записей в блоге: <b><?= $totalNews ?></b></li>
        <li>Количество сообщений обратной связи: <b><?= $totalFeedback ?></b></li>
    </ul>

    <h2>Навигация</h2>
    <nav>
        <ul>
            <li><a href="/indx.php">BLOG</a></li>
			<li><a href="index.php?view=manage_users">Управление пользователями</a></li>
            <li><a href="index.php?view=manage_feedback">Обратная связь</a></li>
            <li><a href="index.php?view=add_news">Добавить запись в блог</a></li>
        </ul>
    </nav>
</body>
</html>