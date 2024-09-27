<?php
class News {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

public function getAllNews($limit, $offset) {
    $stmt = $this->pdo->prepare("SELECT id, title, LEFT(content, 300) AS excerpt, content, created_at FROM blogs ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

    public function getTotalNewsCount() {
        return $this->pdo->query("SELECT COUNT(*) FROM blogs")->fetchColumn();
    }
	public function getLastThreeNews() {
    $stmt = $this->pdo->query("SELECT * FROM blogs ORDER BY created_at DESC LIMIT 3");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}
	public function generateTags($title, $content) {
		// Простой алгоритм генерации тегов (можно усовершенствовать)
		$tags = [];
		
		// Массив стоп-слов
		$stopWords = ['и', 'в', 'на', 'с', 'по', 'к', 'из', 'для', 'это', 'что', 'как']; // Добавьте дополнительные

		// Удаляем HTML-теги и приводим к нижнему регистру
		$title = strip_tags($title);
		$content = strip_tags($content);
		
		// Удаляем знаки препинания 
		$title = preg_replace('/[^\w\s]/', '', $title);
		$content = preg_replace('/[^\w\s]/', '', $content);
		
		// Собираем слова из заголовка и контента
		$words = preg_split('/\s+/', $title . ' ' . $content);
		
		foreach ($words as $word) {
			$word = strtolower(trim($word));
			
			// Пропускаем короткие слова и стоп-слова
			if (strlen($word) > 5 && !in_array($word, $tags) && !in_array($word, $stopWords)) {
				$tags[] = $word;
			}
		}

		// Ограничиваем количество тегов (например, до 10)
		return array_slice($tags, 0, 10);
	}
	public function getNewsWithTags() {
    $stmt = $this->pdo->query("
        SELECT n.*, GROUP_CONCAT(t.name SEPARATOR ', ') as tags
        FROM blogs n
        LEFT JOIN blogs_tags nt ON n.id = nt.blogs_id
        LEFT JOIN tags t ON nt.tag_id = t.id
        GROUP BY n.id
        ORDER BY n.created_at DESC
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}
	public function updateNews($id, $title, $content, $tags) {
    $stmt = $this->pdo->prepare("UPDATE blogs SET title = ?, content = ? WHERE id = ?");
    $stmt->execute([$title, $content, $id]);

    // Удаление старых тегов
    $this->removeTags($id);

    // Сохранение новых тегов
    foreach ($tags as $tag) {
        $stmt = $this->pdo->prepare("INSERT INTO tags (name) VALUES (?) ON DUPLICATE KEY UPDATE id=id");
        $stmt->execute([$tag]);
        $tagId = $this->pdo->lastInsertId();

        $stmt = $this->pdo->prepare("INSERT INTO blogs_tags (blogs_id, tag_id) VALUES (?, ?)");
        $stmt->execute([$id, $tagId]);
    }
}
public function deleteNews($id) {
    try {
        // Удаляем теги, связанные с новостью
        $this->removeTags($id); 
        
        // Удаляем саму новость
        $stmt = $this->pdo->prepare("DELETE FROM blogs WHERE id = ?");
        $stmt->execute([$id]);

        // После удаления новости, удаляем неиспользуемые теги
        $this->removeUnusedTags();
    } catch (PDOException $e) {
        echo 'Ошибка при удалении новости: ' . $e->getMessage();
    }
}
public function removeUnusedTags() {
    // Удаляем теги, которые больше не используются
    $stmt = $this->pdo->prepare("DELETE FROM tags WHERE id NOT IN (SELECT tag_id FROM blogs_tags)");
    $stmt->execute();
}
public function removeTags($newsId) {
    $stmt = $this->pdo->prepare("DELETE FROM blogs_tags WHERE blogs_id = ?");
    $stmt->execute([$newsId]);
}

public function getNewsById($id) {
    $stmt = $this->pdo->prepare("SELECT * FROM blogs WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

public function getTagsByNewsId($newsId) {
    $stmt = $this->pdo->prepare("
        SELECT t.name
        FROM tags t
        JOIN blogs_tags nt ON t.id = nt.tag_id
        WHERE nt.blogs_id = ?
    ");
    $stmt->execute([$newsId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
public function getNewsByTag($tag) {
    // Сначала получаем ID тега по имени
    $stmt = $this->pdo->prepare("SELECT id FROM tags WHERE name = ?");
    $stmt->execute([$tag]);
    $tagId = $stmt->fetchColumn();
    // Если тег не найден, возвращаем пустой массив
    if (!$tagId) {
        return [];
    }

    // Теперь получаем все новости, связанные с этим тегом
    $stmt = $this->pdo->prepare("
        SELECT b.*
        FROM blogs b
        JOIN blogs_tags bt ON b.id = bt.blogs_id
        WHERE bt.tag_id = ?
    ");
    $stmt->execute([$tagId]);

         $news = $stmt->fetchAll(PDO::FETCH_ASSOC);

         return $news;
}

}
