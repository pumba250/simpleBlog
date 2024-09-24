<?php
class User {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function register($username, $password, $email) {
    // Проверяем, есть ли уже пользователи в базе данных
    $query = $this->pdo->query("SELECT COUNT(*) FROM users");
    $userCount = $query->fetchColumn();

    // Хеширование пароля
    $hash = password_hash($password, PASSWORD_BCRYPT);

    // Если это первый пользователь, устанавливаем права администратора
    $isAdmin = ($userCount == 0) ? 9 : 0;

    // Проверка уникальности имени пользователя и электронной почты
    $stmtCheck = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
    $stmtCheck->execute([$username, $email]);
    $exists = $stmtCheck->fetchColumn();

    if ($exists) {
        // Логика обработки ошибки: имя пользователя или email уже заняты
        return false; // Можно выбросить исключение или вернуть ошибку
    }

    // Подготовка и выполнение запроса на добавление пользователя
    $stmt = $this->pdo->prepare("INSERT INTO users (username, password, email, isadmin) VALUES (?, ?, ?, ?)");
    return $stmt->execute([$username, $hash, $email, $isAdmin]);
}

	public function login($username, $password) {
    $stmt = $this->pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && password_verify($password, $user['password'])) {
        // Инициализация сессии
        session_start();
        // Сохранение информации о пользователе в сессии
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
		$_SESSION['isadmin'] = $user['isadmin'];
        return $user;
    }
    return false;
}
public function logout() {
    // Начать сессию, если она еще не инициализирована
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    // Удалить все переменные сессии
    $_SESSION = [];
    
    // Уничтожить сессию
    session_destroy();
}
}
?>
