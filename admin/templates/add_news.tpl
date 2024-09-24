<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Добавить новость</title>
</head>
<body>
<a href="/indx.php">BLOG</a> / <a href="/admin/">Dashboard</a>
    <h1>Добавить новость</h1>
    <form method="POST" action="process_add_news.php">
        <input type="text" name="title" placeholder="Заголовок" required><br>
        <textarea name="content" placeholder="Содержание" required></textarea><br>
        <button type="submit">Добавить новость</button>
    </form>
</body>
</html>