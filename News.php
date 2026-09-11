<?php

if (!defined('IN_SIMPLECMS')) {
    die('Прямой доступ запрещен');
}
/**
 * Класс для работы с новостями и статьями
 *
 * @package    SimpleBlog
 * @subpackage Models
 * @category   Content
 * @version    1.0.0
 *
 * @method array getNewsByAuthor(int $userId, int $limit = 5) Получает новости по автору с ограничением
 * @method array getAllNews(int $limit, int $offset) Получает новости с пагинацией (ограничение и смещение)
 * @method array searchNews(string $query, int $limit = 10, int $offset = 0) Ищет новости по текстовому запросу
 * @method int countSearchResults(string $query) Возвращает количество результатов поиска
 * @method array getAllAdm(int $limit = null, int $offset = null) Получает все новости для админки с тегами
 * @method int getTotalNewsCount() Возвращает общее количество новостей
 * @method object getLastThreeNews() Возвращает 3 последние новости в виде HTML-объекта
 * @method array generateTags(string $title, string $content) Генерирует теги из заголовка и текста статьи
 * @method bool addBlog(string $title, string $content, array $tags = [], int $authorId = null) Добавляет новую запись в блог с тегами
 * @method bool updateBlog(int $id, string $title, string $content, array $tags = []) Обновляет запись в блоге и связанные теги
 * @method bool deleteBlog(int $id) Удаляет запись из блога и связанные теги
 * @method object GetAllTags() Возвращает все теги в виде HTML-объекта
 * @method bool addTag(string $name) Добавляет новый тег
 * @method bool deleteTag(int $id) Удаляет тег по ID и связанные связи
 * @method void removeUnusedTags() Удаляет неиспользуемые теги из базы
 * @method void removeTags(int $newsId) Удаляет все теги у указанной новости
 * @method array|false getNewsById(int $id) Получает новость по ID или false при ошибке
 * @method array getTagsByNewsId(int $newsId) Возвращает теги для указанной новости
 * @method array getNewsByTag(string $tag, int $limit, int $offset) Получает новости по тегу с пагинацией
 * @method int getNewsCountByTag(string $tag) Возвращает количество новостей по тегу
 * @method string generateMetaDescription(string $content, string $type = 'default', array $additionalData = []) Генерирует meta-описание для разных типов страниц
 * @method string generateMetaKeywords(string $content, string $type = 'default', array $additionalData = []) Генерирует meta-ключевые слова для разных типов страниц
 * @method array getAllNewsCached(int $limit, int $offset) Получает новости из кэша или БД (с пагинацией)
 * @method array|false getNewsByIdCached(int $id) Получает новость по ID из кэша или БД
 */
class News
{
    private $pdo;
    private $dbPrefix;

    public function __construct($pdo)
    {
        global $config;
        $this->pdo = $pdo;
        if (!isset($config['db_prefix'])) {
            throw new InvalidArgumentException("Database prefix not configured");
        }
        if (!preg_match('/^[a-z][a-z0-9_]*$/i', $config['db_prefix'])) {
            throw new InvalidArgumentException("Invalid database prefix format");
        }
        $this->dbPrefix = $config['db_prefix'];
    }

    public function getNewsByAuthor($userId, $limit = 5)
    {
        $stmt = $this->pdo->prepare("
            SELECT id, title, created_at 
            FROM {$this->dbPrefix}blogs 
            WHERE author_id = ? 
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        // Явное приведение типов для безопасности
        $userId = (int)$userId;
        $limit = (int)$limit;

        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllNews($limit, $offset)
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, title, SUBSTR(content, 1, 330) AS content, created_at as date 
            FROM {$this->dbPrefix}blogs 
            ORDER BY created_at DESC 
            LIMIT :limit OFFSET :offset"
        );
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function searchNews($query, $limit = 10, $offset = 0)
    {
        try {
            $searchQuery = '%' . $query . '%';
            $stmt = $this->pdo->prepare("
                SELECT id, title, SUBSTR(content, 1, 330) AS content, created_at 
                FROM {$this->dbPrefix}blogs 
                WHERE title LIKE :query OR content LIKE :query
                ORDER BY created_at DESC
                LIMIT :limit OFFSET :offset
            ");

            $stmt->bindParam(':query', $searchQuery, PDO::PARAM_STR);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Search error: " . $e->getMessage());
            return [];
        }
    }

    public function countSearchResults($query)
    {
        try {
            $searchQuery = '%' . $query . '%';
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) 
                FROM {$this->dbPrefix}blogs 
                WHERE title LIKE :query OR content LIKE :query
            ");

            $stmt->bindParam(':query', $searchQuery, PDO::PARAM_STR);
            $stmt->execute();

            return $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Search count error: " . $e->getMessage());
            return 0;
        }
    }

    public function getAllAdm($limit = null, $offset = null)
    {
        $sql = "
            SELECT b.*, GROUP_CONCAT(t.name) as tag_names, GROUP_CONCAT(t.id) as tag_ids
            FROM `{$this->dbPrefix}blogs` b
            LEFT JOIN `{$this->dbPrefix}blogs_tags` bt ON b.id = bt.blogs_id
            LEFT JOIN `{$this->dbPrefix}tags` t ON bt.tag_id = t.id
            GROUP BY b.id
            ORDER BY b.created_at DESC
        ";

        // Добавляем LIMIT и OFFSET если они указаны
        if ($limit !== null) {
            $sql .= " LIMIT :limit";
            if ($offset !== null) {
                $sql .= " OFFSET :offset";
            }
        }

        $stmt = $this->pdo->prepare($sql);

        // Привязываем параметры если они указаны
        if ($limit !== null) {
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
            if ($offset !== null) {
                $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
            }
        }

        $stmt->execute();

        $blogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Преобразуем теги в массивы
        foreach ($blogs as &$blog) {
            $blog['tags'] = $blog['tag_names'] ? array_map('htmlspecialchars', explode(',', $blog['tag_names'])) : [];
            $blog['tag_ids'] = $blog['tag_ids'] ? array_map('intval', explode(',', $blog['tag_ids'])) : [];
        }

        return $blogs;
    }

    public function getTotalNewsCount()
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM {$this->dbPrefix}blogs");
        $stmt->execute();
        return $stmt->fetchColumn();
    }

    public function getLastThreeNews()
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, title, created_at 
            FROM {$this->dbPrefix}blogs 
            ORDER BY RAND() DESC 
            LIMIT 3"
        );
        $stmt->execute();
        $newsItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $newsHtml = '';
        foreach ($newsItems as $item) {
            $newsHtml .= '<li class="news-item">';
            $newsHtml .= '<a href="?id=' . htmlspecialchars($item['id']) . '" class="news-title">'
                        . htmlspecialchars($item['title']) . '</a>';
            $newsHtml .= '<span class="news-date">' . htmlspecialchars($item['created_at']) . '</span>';
            $newsHtml .= '</li>';
        }

        return new class ($newsHtml)
        {
            private $html;

            public function __construct($html)
            {
                $this->html = $html;
            }

            public function __toString()
            {
                return $this->html;
            }
        };
    }

    public function generateTags($title, $content)
    {
        $tags = [];

        $stopWords = ['и', 'в', 'на', 'с', 'по', 'к', 'из', 'для', 'это', 'что', 'как'];

        $title = strip_tags($title);
        $content = strip_tags($content);

        $title = preg_replace('/[^\w\s]/u', '', $title);  // Added 'u' modifier for UTF-8 support
        $content = preg_replace('/[^\w\s]/u', '', $content);

        $words = preg_split('/\s+/', $title . ' ' . $content);

        foreach ($words as $word) {
            $word = mb_strtolower(trim($word), 'UTF-8');  // Using mb_strtolower for proper UTF-8 support

            if (mb_strlen($word, 'UTF-8') > 5 && !in_array($word, $tags) && !in_array($word, $stopWords)) {
                $tags[] = $word;
            }
        }

        return array_slice($tags, 0, 4);
    }

    /**
     * Добавить новую запись блога
     */
    public function addBlog($title, $content, $tags = [], $authorId = null)
    {
        try {
            $this->pdo->beginTransaction();

            // Добавляем запись
            $stmt = $this->pdo->prepare(
                "INSERT INTO {$this->dbPrefix}blogs (title, content, author_id) VALUES (?, ?, ?)"
            );
            $stmt->execute([$title, $content, $authorId]);
            $blogId = $this->pdo->lastInsertId();

            // Get auto-generated tags
            $autoTags = $this->generateTags($title, $content);

            // Process both manual tags and auto-generated tags
            $allTags = $tags;
            foreach ($autoTags as $tagName) {
                // Check if tag exists, if not create it
                $stmt = $this->pdo->prepare("SELECT id FROM {$this->dbPrefix}tags WHERE name = ?");
                $stmt->execute([$tagName]);
                $tagId = $stmt->fetchColumn();

                if (!$tagId) {
                    $stmt = $this->pdo->prepare("INSERT INTO {$this->dbPrefix}tags (name) VALUES (?)");
                    $stmt->execute([$tagName]);
                    $tagId = $this->pdo->lastInsertId();
                }

                $allTags[] = $tagId;
            }

            // Добавляем связи с тегами
            if (!empty($allTags)) {
                $allTags = array_unique($allTags); // Remove duplicates
                $stmt = $this->pdo->prepare(
                    "INSERT INTO {$this->dbPrefix}blogs_tags (blogs_id, tag_id) VALUES (?, ?)"
                );
                foreach ($allTags as $tagId) {
                    $stmt->execute([$blogId, $tagId]);
                }
            }

            $this->pdo->commit();
            Cache::clear();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log($e->getMessage());
            return false;
        }
    }

    /**
     * Обновить запись блога
     */
    public function updateBlog($id, $title, $content, $tags = [])
    {
        try {
            $this->pdo->beginTransaction();

            // Обновление основной информации
            $stmt = $this->pdo->prepare(
                "UPDATE {$this->dbPrefix}blogs SET title = ?, content = ? WHERE id = ?"
            );
            $stmt->execute([$title, $content, $id]);

            // Удаляем старые связи с тегами
            $stmt = $this->pdo->prepare("DELETE FROM {$this->dbPrefix}blogs_tags WHERE blogs_id = ?");
            $stmt->execute([$id]);

            // Добавляем новые связи с тегами
            if (!empty($tags)) {
                $stmt = $this->pdo->prepare(
                    "INSERT INTO {$this->dbPrefix}blogs_tags (blogs_id, tag_id) VALUES (?, ?)"
                );
                foreach ($tags as $tagId) {
                    $tagId = (int)$tagId;
                    // Проверяем, что тег существует
                    $checkStmt = $this->pdo->prepare("SELECT COUNT(*) FROM {$this->dbPrefix}tags WHERE id = ?");
                    $checkStmt->execute([$tagId]);
                    if ($checkStmt->fetchColumn()) {
                        $stmt->execute([$id, $tagId]);
                    }
                }
            }

            $this->pdo->commit();
            Cache::clear();
            return true;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("Ошибка при обновлении блога: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Удалить запись блога
     */
    public function deleteBlog($id)
    {
        try {
            $this->pdo->beginTransaction();

            // Удаляем связи с тегами
            $stmt = $this->pdo->prepare("DELETE FROM {$this->dbPrefix}blogs_tags WHERE blogs_id = ?");
            $stmt->execute([$id]);

            // Удаляем саму запись
            $stmt = $this->pdo->prepare("DELETE FROM {$this->dbPrefix}blogs WHERE id = ?");
            $stmt->execute([$id]);

            $this->pdo->commit();
            Cache::clear();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log($e->getMessage());
            return false;
        }
    }

    public function getAllTags()
    {
        $stmt = $this->pdo->prepare("SELECT DISTINCT(`name`) FROM {$this->dbPrefix}tags");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addTag($name)
    {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO {$this->dbPrefix}tags (name) VALUES (?)");
            return $stmt->execute([$name]);
        } catch (Exception $e) {
            error_log($e->getMessage());
            return false;
        }
    }

    /**
     * Удалить тег
     */
    public function deleteTag($id)
    {
        try {
            $this->pdo->beginTransaction();

            // Удаляем связи с записями
            $stmt = $this->pdo->prepare("DELETE FROM {$this->dbPrefix}blogs_tags WHERE tag_id = ?");
            $stmt->execute([$id]);

            // Удаляем сам тег
            $stmt = $this->pdo->prepare("DELETE FROM {$this->dbPrefix}tags WHERE id = ?");
            $stmt->execute([$id]);

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log($e->getMessage());
            return false;
        }
    }

    public function removeUnusedTags()
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM `{$this->dbPrefix}tags` 
            WHERE id NOT IN (SELECT tag_id FROM `{$this->dbPrefix}blogs_tags`)"
        );
        $stmt->execute();
    }

    public function removeTags($newsId)
    {
        $stmt = $this->pdo->prepare("DELETE FROM `{$this->dbPrefix}blogs_tags` WHERE blogs_id = ?");
        $stmt->execute([$newsId]);
    }

    public function getNewsById($id)
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM {$this->dbPrefix}blogs WHERE id = ? LIMIT 1");
            $stmt->execute([$id]);
            $news = $stmt->fetch(PDO::FETCH_ASSOC);
            return $news;
        } catch (PDOException $e) {
            error_log("Error in getBlogById: " . $e->getMessage());
            return false;
        }
    }

    public function getTagsByNewsId($newsId)
    {
        $stmt = $this->pdo->prepare("
            SELECT t.name
            FROM {$this->dbPrefix}tags t
            JOIN {$this->dbPrefix}blogs_tags nt ON t.id = nt.tag_id
            WHERE nt.blogs_id = ?
        ");
        $stmt->execute([$newsId]);
        $tags = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $tags ?: [];
    }

    public function getNewsByTag($tag, $limit, $offset)
    {
        $stmt = $this->pdo->prepare("SELECT id FROM {$this->dbPrefix}tags WHERE name = :tag");
        $stmt->bindParam(':tag', $tag, PDO::PARAM_STR);
        $stmt->execute();
        $tagId = $stmt->fetchColumn();
        if (!$tagId) {
            return [];
        }

        $stmt = $this->pdo->prepare("
            SELECT b.id, b.title, LEFT(b.content, 320) AS content, b.created_at as date
            FROM {$this->dbPrefix}blogs b
            JOIN {$this->dbPrefix}blogs_tags bt ON b.id = bt.blogs_id
            WHERE bt.tag_id = :tag_id
            ORDER BY b.created_at DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindParam(':tag_id', $tagId, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $news = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $news ?: [];
    }

    public function getNewsCountByTag($tag)
    {
        $stmt = $this->pdo->prepare("SELECT id FROM {$this->dbPrefix}tags WHERE name = :tag");
        $stmt->bindParam(':tag', $tag, PDO::PARAM_STR);
        $stmt->execute();
        $tagId = $stmt->fetchColumn();

        if (!$tagId) {
            return 0;
        }

        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) 
            FROM {$this->dbPrefix}blogs b
            JOIN {$this->dbPrefix}blogs_tags bt ON b.id = bt.blogs_id
            WHERE bt.tag_id = :tag_id
        ");

        $stmt->bindParam(':tag_id', $tagId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchColumn();
    }

    // Функция для генерации metaDescription
    public function generateMetaDescription($content, $type = 'default', $additionalData = [])
    {
        global $config;
        $defaultDescription = $config['metaDescription'] ?? '';

        switch ($type) {
            case 'article':
                // Для статьи берем первые 160 символов контента
                $cleanContent = strip_tags($content);
                return mb_substr($cleanContent, 0, 160) . '...';

            case 'tag':
                return 'Все публикации по теме: ' . htmlspecialchars($additionalData['tag']);

            case 'search':
                return 'Результаты поиска по запросу: ' . htmlspecialchars($additionalData['query']);

            case 'login':
                return 'Форма авторизации ' . $config['metaDescription'];

            case 'register':
                return 'Форма регистрации нового пользователя ' . $config['metaDescription'];

            case 'contact':
                return 'Контактная форма для связи с администрацией ' . $config['metaDescription'];

            case 'error404':
                return 'Страница не найдена';

            case 'error500':
                return 'Внутренняя ошибка сервера';

            default:
                return $defaultDescription;
        }
    }

    // Функция для генерации metaKeywords
    public function generateMetaKeywords($content, $type = 'default', $additionalData = [])
    {
        global $config;
        $defaultKeywords = $config['metaKeywords'] ?? '';

        switch ($type) {
            case 'article':
                // Для статьи берем слова из заголовка и первые 20 слов контента
                $titleWords = explode(' ', $additionalData['title']);
                $cleanContent = strip_tags($content);
                $contentWords = explode(' ', $cleanContent);
                $allWords = array_merge($titleWords, array_slice($contentWords, 0, 20));

                // Удаляем слишком короткие слова и дубликаты
                $filteredWords = array_filter($allWords, function ($word) {
                    return mb_strlen($word) > 3;
                });

                $uniqueWords = array_unique($filteredWords);
                return implode(', ', array_slice($uniqueWords, 0, 15));

            case 'tag':
                return htmlspecialchars($additionalData['tag']) . ', ' . $config['metaKeywords'];

            case 'search':
                return 'поиск, ' . htmlspecialchars($additionalData['query']);

            case 'login':
                return 'авторизация, вход, ' . $config['metaKeywords'];

            case 'register':
                return 'регистрация, создать аккаунт, ' . $config['metaKeywords'];

            case 'contact':
                return 'контакты, обратная связь, ' . $config['metaKeywords'];

            case 'error404':
                return '404, страница не найдена';

            case 'error500':
                return '500, ошибка сервера';

            default:
                return $defaultKeywords;
        }
    }

    public function getAllNewsCached($limit, $offset)
    {
        $cacheKey = 'news_all_' . $limit . '_' . $offset . '_' . ($_SESSION['lang'] ?? 'ru');

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $result = $this->getAllNews($limit, $offset);
        Cache::set($cacheKey, $result, 1800); // 30 минут кэша

        return $result;
    }

    public function getNewsByIdCached($id)
    {
        $cacheKey = 'news_item_' . $id;

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $result = $this->getNewsById($id);
        if ($result) {
            Cache::set($cacheKey, $result, 3600); // 1 час кэша для статьи
        }

        return $result;
    }
}
