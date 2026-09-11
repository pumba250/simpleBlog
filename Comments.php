<?php

if (!defined('IN_SIMPLECMS')) {
    die('Прямой доступ запрещен');
}

/**
 * Класс для работы с комментариями
 *
 * @package    SimpleBlog
 * @subpackage Models
 * @category   Content
 * @version    1.0.0
 *
 * @method bool   getCommentsByUser(int $userId, int $limit = 5)          Получает комментарии по автору с ограничением
 * @method int    addComment(int $parentId, int $fParent, int $themeId, string $userName, string $userText) Добавляет комментарий
 * @method bool   editComment(int $id, string $userText)                  Редактирует текст комментария
 * @method bool   deleteComment(int $id)                                  Удаляет комментарий
 * @method array  getComments(int $themeId, int $limit = 10, int $offset = 0, bool $moderatedOnly = true) Получает комментарии для темы
 * @method array  AllComments(int $limit = 10, int $offset = 0)           Получает все комментарии (админка)
 * @method int    countComments(int $themeId, bool $moderatedOnly = true) Считает комментарии для темы
 * @method int    countAllComments()                                      Считает все комментарии в системе
 * @method bool   toggleModeration(int $id)                               Переключает статус модерации
 * @method bool   voteComment(int $id, string $voteType)                  Голосование за/против комментария
 * @method bool   approveComment(int $commentId)                          Одобряет комментарий
 * @method bool   rejectComment(int $commentId)                           Отклоняет комментарий
 * @method array  getPendingComments()                                    Получает комментарии на модерации
 * @method int    countPendingComments()                                  Считает комментарии на модерации
 * @method bool   getCommentStatus(int $id)                               Получает статус модерации комментария
 * @method array  getCommentsPaginationData(int $themeId, int $currentPage = 1, bool $moderatedOnly = true) Рассчитывает данные пагинации
 */

class Comments
{
    private $pdo;
    private $dbPrefix;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        global $dbPrefix;
        $this->dbPrefix = $dbPrefix;
    }

    public function getCommentsByUser($userId, $limit = 5)
    {
        // Валидация параметров
        $userId = filter_var($userId, FILTER_VALIDATE_INT);
        $limit = filter_var($limit, FILTER_VALIDATE_INT);

        if ($userId === false || $userId <= 0) {
            throw new InvalidArgumentException('Invalid user ID');
        }
        if ($limit === false || $limit <= 0) {
            throw new InvalidArgumentException('Invalid limit value');
        }

        $stmt = $this->pdo->prepare("
            SELECT c.id, c.theme_id, c.user_text, c.created_at, b.title as post_title 
            FROM {$this->dbPrefix}comments c
            LEFT JOIN {$this->dbPrefix}blogs b ON c.theme_id = b.id
            WHERE c.user_name = (SELECT username FROM {$this->dbPrefix}users WHERE id = :user_id)
            ORDER BY c.created_at DESC 
            LIMIT :limit
        ");

        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addComment($parentId, $fParent, $themeId, $userName, $userText)
    {
        // Валидация параметров
        $parentId = filter_var($parentId, FILTER_VALIDATE_INT);
        $fParent = filter_var($fParent, FILTER_VALIDATE_INT);
        $themeId = filter_var($themeId, FILTER_VALIDATE_INT);

        if ($parentId === false || $parentId < 0) {
            throw new InvalidArgumentException('Invalid parent ID');
        }
        if ($fParent === false || $fParent < 0) {
            throw new InvalidArgumentException('Invalid fParent ID');
        }
        if ($themeId === false || $themeId <= 0) {
            throw new InvalidArgumentException('Invalid theme ID');
        }

        // Валидация имени пользователя
        $userName = trim($userName);
        if (empty($userName) || strlen($userName) > 255) {
            throw new InvalidArgumentException('Invalid user name');
        }
        $userName = htmlspecialchars($userName, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Валидация текста комментария
        $userText = trim($userText);
        if (empty($userText)) {
            throw new InvalidArgumentException('Comment text cannot be empty');
        }
        if (strlen($userText) > 5000) {
            throw new InvalidArgumentException('Comment text is too long');
        }
        $userText = htmlspecialchars($userText, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $stmt = $this->pdo->prepare(
            "INSERT INTO `{$this->dbPrefix}comments` 
            (parent_id, f_parent, created_at, theme_id, user_name, user_text, moderation, plus, minus) 
            VALUES (:parent_id, :f_parent, :created_at, :theme_id, :user_name, :user_text, 0, 0, 0)"
        );

        $stmt->bindValue(':parent_id', $parentId, PDO::PARAM_INT);
        $stmt->bindValue(':f_parent', $fParent, PDO::PARAM_INT);
        $stmt->bindValue(':created_at', time(), PDO::PARAM_INT);
        $stmt->bindValue(':theme_id', $themeId, PDO::PARAM_INT);
        $stmt->bindValue(':user_name', $userName, PDO::PARAM_STR);
        $stmt->bindValue(':user_text', $userText, PDO::PARAM_STR);

        $stmt->execute();
        return $this->pdo->lastInsertId();
    }

    public function editComment($id, $userText)
    {
        // Валидация ID
        $id = filter_var($id, FILTER_VALIDATE_INT);
        if ($id === false || $id <= 0) {
            throw new InvalidArgumentException('Invalid comment ID');
        }

        // Валидация и очистка текста
        $userText = trim($userText);
        if (empty($userText)) {
            throw new InvalidArgumentException('Comment text cannot be empty');
        }

        // Ограничение длины текста
        if (strlen($userText) > 5000) {
            throw new InvalidArgumentException('Comment text is too long');
        }

        // Экранирование специальных символов HTML (для XSS защиты)
        $userText = htmlspecialchars($userText, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $stmt = $this->pdo->prepare(
            "UPDATE `{$this->dbPrefix}comments` 
             SET user_text = :user_text 
             WHERE id = :id"
        );

        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':user_text', $userText, PDO::PARAM_STR);

        return $stmt->execute();
    }

    public function deleteComment($id)
    {
        // Валидация ID
        $id = filter_var($id, FILTER_VALIDATE_INT);
        if ($id === false || $id <= 0) {
            throw new InvalidArgumentException('Invalid comment ID');
        }

        $stmt = $this->pdo->prepare(
            "DELETE FROM `{$this->dbPrefix}comments` WHERE id = :id"
        );

        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function getCommentsPaginationData($themeId, $currentPage = 1, $moderatedOnly = true)
    {
        // Валидация параметров
        $themeId = filter_var($themeId, FILTER_VALIDATE_INT);
        $currentPage = filter_var($currentPage, FILTER_VALIDATE_INT);

        if ($themeId === false || $themeId <= 0) {
            throw new InvalidArgumentException('Invalid theme ID');
        }
        if ($currentPage === false || $currentPage <= 0) {
            $currentPage = 1;
        }

        $total = $this->countComments($themeId, $moderatedOnly);
        global $config;
        return Pagination::calculate($total, 'comments_per_page', $currentPage, $config);
    }

    /**
     * Получает комментарии для конкретной темы с пагинацией
     * @param int $themeId ID темы
     * @param int $limit Количество комментариев на странице
     * @param int $offset Смещение
     * @param bool $moderatedOnly Только модерированные комментарии (по умолчанию true)
     * @return array
     */
    public function getComments($themeId, $limit = 10, $offset = 0, $moderatedOnly = true)
    {
        // Валидация параметров
        $themeId = filter_var($themeId, FILTER_VALIDATE_INT);
        $limit = filter_var($limit, FILTER_VALIDATE_INT);
        $offset = filter_var($offset, FILTER_VALIDATE_INT);

        if ($themeId === false || $themeId <= 0) {
            throw new InvalidArgumentException('Invalid theme ID');
        }
        if ($limit === false || $limit <= 0) {
            throw new InvalidArgumentException('Invalid limit value');
        }
        if ($offset === false || $offset < 0) {
            $offset = 0;
        }

        $moderationCondition = $moderatedOnly ? 'AND moderation = 1' : '';

        $stmt = $this->pdo->prepare("
            SELECT c.*, u.avatar, u.isadmin 
            FROM `{$this->dbPrefix}comments` c
            LEFT JOIN `{$this->dbPrefix}users` u ON c.user_name = u.username
            WHERE theme_id = :theme_id $moderationCondition 
            ORDER BY created_at DESC 
            LIMIT :limit OFFSET :offset
        ");

        $stmt->bindValue(':theme_id', $themeId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function allComments($limit = 10, $offset = 0)
    {
        // Валидация параметров
        $limit = filter_var($limit, FILTER_VALIDATE_INT);
        $offset = filter_var($offset, FILTER_VALIDATE_INT);

        if ($limit === false || $limit <= 0) {
            throw new InvalidArgumentException('Invalid limit value');
        }
        if ($offset === false || $offset < 0) {
            $offset = 0;
        }

        $stmt = $this->pdo->prepare("
            SELECT c.*, b.title as post_title, u.username 
            FROM `{$this->dbPrefix}comments` c 
            LEFT JOIN `{$this->dbPrefix}blogs` b ON c.theme_id = b.id 
            LEFT JOIN `{$this->dbPrefix}users` u ON c.user_name = u.username
            ORDER BY c.created_at DESC
            LIMIT :limit OFFSET :offset
        ");

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Считает общее количество комментариев для темы
     * @param int $themeId ID темы
     * @param bool $moderatedOnly Только модерированные
     * @return int
     */
    public function countComments($themeId, $moderatedOnly = true)
    {
        // Валидация ID темы
        $themeId = filter_var($themeId, FILTER_VALIDATE_INT);
        if ($themeId === false || $themeId <= 0) {
            throw new InvalidArgumentException('Invalid theme ID');
        }

        $moderationCondition = $moderatedOnly ? 'AND moderation = 1' : '';
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM `{$this->dbPrefix}comments` 
            WHERE theme_id = :theme_id $moderationCondition"
        );

        $stmt->bindValue(':theme_id', $themeId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchColumn();
    }

    /**
     * Считает общее количество всех комментариев (для админки)
     * @return int
     */
    public function countAllComments()
    {
        return $this->pdo->query(
            "SELECT COUNT(*) FROM `{$this->dbPrefix}comments`"
        )->fetchColumn();
    }

    public function toggleModeration($id)
    {
        // Валидация ID
        $id = filter_var($id, FILTER_VALIDATE_INT);
        if ($id === false || $id <= 0) {
            throw new InvalidArgumentException('Invalid comment ID');
        }

        $stmt = $this->pdo->prepare(
            "UPDATE `{$this->dbPrefix}comments` 
            SET moderation = NOT moderation 
            WHERE id = :id"
        );

        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function voteComment($id, $voteType)
    {
        // Валидация ID
        $id = filter_var($id, FILTER_VALIDATE_INT);
        if ($id === false || $id <= 0) {
            throw new InvalidArgumentException('Invalid comment ID');
        }

        // Валидация типа голоса
        $allowedVoteTypes = ['plus', 'minus'];
        if (!in_array($voteType, $allowedVoteTypes)) {
            throw new InvalidArgumentException('Invalid vote type');
        }

        // Используем белый список для имени колонки
        $column = $voteType == 'plus' ? 'plus' : 'minus';

        $stmt = $this->pdo->prepare(
            "UPDATE `{$this->dbPrefix}comments` 
            SET `$column` = `$column` + 1 
            WHERE id = :id"
        );

        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function approveComment($commentId)
    {
        // Валидация ID
        $commentId = filter_var($commentId, FILTER_VALIDATE_INT);
        if ($commentId === false || $commentId <= 0) {
            throw new InvalidArgumentException('Invalid comment ID');
        }

        $stmt = $this->pdo->prepare(
            "UPDATE `{$this->dbPrefix}comments` 
            SET moderation = 1 
            WHERE id = :id"
        );

        $stmt->bindValue(':id', $commentId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function rejectComment($commentId)
    {
        // Валидация ID
        $commentId = filter_var($commentId, FILTER_VALIDATE_INT);
        if ($commentId === false || $commentId <= 0) {
            throw new InvalidArgumentException('Invalid comment ID');
        }

        $stmt = $this->pdo->prepare(
            "DELETE FROM `{$this->dbPrefix}comments` 
            WHERE id = :id"
        );

        $stmt->bindValue(':id', $commentId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function getPendingComments()
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM `{$this->dbPrefix}comments` 
            WHERE moderation = 0"
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countPendingComments()
    {
        return $this->pdo->query(
            "SELECT COUNT(*) FROM `{$this->dbPrefix}comments` 
            WHERE moderation = 0"
        )->fetchColumn();
    }

    public function getCommentStatus($id)
    {
        // Валидация ID
        $id = filter_var($id, FILTER_VALIDATE_INT);
        if ($id === false || $id <= 0) {
            throw new InvalidArgumentException('Invalid comment ID');
        }

        $stmt = $this->pdo->prepare(
            "SELECT moderation FROM `{$this->dbPrefix}comments` 
            WHERE id = :id"
        );

        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return (bool)$stmt->fetchColumn();
    }
}
