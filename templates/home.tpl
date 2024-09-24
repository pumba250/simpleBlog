<?php include 'header.tpl'; 
/* Blog entry */
// Проверяем, есть ли параметр id в GET-запросе
if (isset($_GET['id'])) {
    // Получаем одну новость по id
    $newsId = (int)$_GET['id'];
    $newsItem = $news->getNewsById($newsId); // Получаем одну новость
    if ($newsItem): // Если новость найдена
?>
    <div class="w3-card-4 w3-margin w3-white">
        <div class="w3-container">
            <h3><b><?= htmlspecialchars($newsItem['title']) ?></b>
                    <?php if (isset($user) && $user['isadmin'] == 9): ?>
                        / <a href="/admin/edit_news.php?id=<?= $newsItem['id'] ?>">edit</a>
                        or <a href="/admin/delete_news.php?id=<?= $newsItem['id'] ?>">delete</a>
                    <?php endif; ?>
                    </h3>
            <h5><span class="w3-opacity"><?= htmlspecialchars($newsItem['created_at']) ?></span></h5>
        </div>
        <div class="w3-container">
            <p><?= ($newsItem['content']) ?></p> <!-- Полное содержание -->
            <div class="w3-row">
                <div class="w3-col m8 s12">
                    <p><button class="w3-button w3-padding-large w3-white w3-border"><b>
                        <a href="?">Вернуться »</a>
                    </b></button></p>
                </div>
            </div>
        </div>
    </div>
    <hr>
<?php
    else:
        // Если новость не найдена
        echo "<p>Новость не найдена.</p>";
    endif;
// Загружаем краткие новости, если id не указан
} else {
    if ($news): // Проверяем, есть ли новости
        foreach ($news as $newsItem): // Перебираем все короткие новости
?>
        <div class="w3-card-4 w3-margin w3-white">
            <div class="w3-container">
                <h3><b><?= htmlspecialchars($newsItem['title']) ?></b>
                    <?php if (isset($user) && $user['isadmin'] == 9): ?>
                        / <a href="/admin/edit_news.php?id=<?= $newsItem['id'] ?>">edit</a>
                        or <a href="/admin/delete_news.php?id=<?= $newsItem['id'] ?>">delete</a>
                    <?php endif; ?>
                    </h3>
                <h5><span class="w3-opacity"><?= htmlspecialchars($newsItem['created_at']) ?></span></h5>
            </div>
            <div class="w3-container">
                <p><?= ($newsItem['excerpt']) ?>...</p> <!-- Краткое содержание -->
                <div class="w3-row">
                    <div class="w3-col m8 s12">
                        <p><button class="w3-button w3-padding-large w3-white w3-border"><b>
                            <a href="?id=<?= $newsItem['id'] ?>">Читать дальше »</a>
                        </b></button></p>
                    </div>
                </div>
            </div>
        </div>
        <hr>
<?php
        endforeach; 
    else:
        echo "<p>Нет новостей</p>";
    endif; 
}
/* END BLOG ENTRIES */
include 'footer.tpl'; ?>