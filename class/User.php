<?php
class User {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function register($username, $password, $email) {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $this->pdo->prepare("INSERT INTO users (username, password, email) VALUES (?, ?, ?)");
        return $stmt->execute([$username, $hash, $email]);
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