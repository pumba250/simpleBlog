<?php

if (!defined('IN_SIMPLECMS')) {
    die('Прямой доступ запрещен');
}

/**
 * Класс для работы с пользователями и аутентификацией
 *
 * @package    SimpleBlog
 * @subpackage Core
 * @category   Authentication
 * @version    1.0.0
 *
 * @method void   __construct(PDO $pdo)                                        Инициализирует систему пользователей
 * @method array  getAllUsers()                                                Получает список всех пользователей
 * @method bool   hasPermission(int $requiredRole, int $currentRole)           Проверяет права доступа
 * @method bool   updateUser(int $id, string $username, string $email, int $isadmin) Обновляет данные пользователя
 * @method bool   deleteUser(int $id)                                          Удаляет пользователя
 * @method bool   register(string $username, string $password, string $email)  Регистрирует нового пользователя
 * @method array|bool getUserById(int $id)                                     Получает данные пользователя по ID
 * @method bool   verifyEmail(string $token)                                   Подтверждает email пользователя
 * @method bool   sendPasswordReset(string $email)                             Отправляет ссылку для сброса пароля
 * @method bool   resetPassword(string $token, string $newPassword)            Сбрасывает пароль пользователя
 * @method array|bool login(string $username, string $password)                Выполняет вход пользователя
 * @method void   logout()                                                     Выполняет выход пользователя
 * @method bool   updateProfile(int $userId, string $username, string $email, string|null $avatar = null, string|null $currentPassword = null) Обновляет профиль пользователя
 * @method bool   changeEmail(int $userId, string $newEmail, string $password) Изменяет email пользователя
 * @method bool   setSocialLink(int $userId, string $socialType, string $socialId, string|null $username = null) Добавляет/обновляет привязку социальной сети
 * @method array  getSocialLinks(int $userId)                                  Получает привязанные социальные сети пользователя
 * @method bool   removeSocialLink(int $userId, string $socialType)            Удаляет привязку социальной сети
 */
class User
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        $this->startSession();
    }

    private function startSession()
{
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }

    // Инициализация ВСЕГДА, независимо от того, кто запустил сессию
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = 0;
    }
    if (!isset($_SESSION['last_attempt'])) {
        $_SESSION['last_attempt'] = 0;
    }
}

    public function getAllUsers()
    {
        global $dbPrefix;
        $stmt = $this->pdo->prepare(
            "SELECT id, username, email, isadmin, created_at 
            FROM {$dbPrefix}users 
            ORDER BY created_at DESC"
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // Функция проверки прав
    public function hasPermission($requiredRole, $currentRole)
    {
        return $currentRole >= $requiredRole;
    }

    /**
     * Обновить пользователя
     */
    public function updateUser($id, $username, $email, $isadmin)
    {
        global $dbPrefix;
        try {
            $stmt = $this->pdo->prepare(
                "UPDATE {$dbPrefix}users 
                SET username = ?, email = ?, isadmin = ? 
                WHERE id = ?"
            );
            return $stmt->execute([$username, $email, $isadmin, $id]);
        } catch (Exception $e) {
            error_log(sprintf(
                '[%s] Error in %s:%d - %s',
                get_class($e),
                $e->getFile(),
                $e->getLine(),
                $config['debug'] ? $e->getMessage() : 'Operation failed'
            ));
            return false;
        }
    }

    /**
     * Удалить пользователя
     */
    public function deleteUser($id)
    {
        global $dbPrefix;
        try {
            $stmt = $this->pdo->prepare("DELETE FROM {$dbPrefix}users WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (Exception $e) {
            error_log($e->getMessage());
            return false;
        }
    }

    public function register($username, $password, $email)
    {
        global $dbPrefix, $config;
        $query = $this->pdo->query("SELECT COUNT(*) FROM {$dbPrefix}users");
        $userCount = $query->fetchColumn();

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $isAdmin = ($userCount == 0) ? 9 : 0;
        $verificationToken = bin2hex(random_bytes(32));

        $stmtCheck = $this->pdo->prepare(
            "SELECT COUNT(*) 
            FROM {$dbPrefix}users 
            WHERE username = ? OR email = ?"
        );
        $stmtCheck->execute([$username, $email]);
        $exists = $stmtCheck->fetchColumn();

        if ($exists) {
            flash(Lang::get('user_exists', 'core'), 'error');
            return false;
        }

        if (
            strlen($password) < 6
            || !preg_match('/[A-Z]/', $password)
            || !preg_match('/[0-9]/', $password)
        ) {
            throw new Exception("Password does not meet complexity requirements");
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO {$dbPrefix}users 
            (username, password, email, isadmin, verification_token) 
            VALUES (?, ?, ?, ?, ?)"
        );

        if ($stmt->execute([$username, $hash, $email, $isAdmin, $verificationToken])) {
            $this->sendVerificationEmail($username, $email, $verificationToken);
            flash(Lang::get('reg_success_verify', 'core'), 'success');
            return true;
        }

        return false;
    }

    public function getUserById($userId)
    {
        global $dbPrefix;
        $stmt = $this->pdo->prepare(
            "SELECT id, username, email, isadmin, created_at, avatar 
            FROM {$dbPrefix}users 
            WHERE id = ? 
            LIMIT 1"
        );
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function sendVerificationEmail($username, $email, $token)
    {
        global $config;
        Mailer::init($config);

        $verificationUrl = "https://{$_SERVER['HTTP_HOST']}/?action=verify_email&token=$token";
        $subject = Lang::get('verify_email_subject', 'core');

        $message = '
            <p>' . Lang::get('hiuser', 'main') . ' <strong>' . htmlspecialchars($username) . '</strong>,</p>

            <p>' . Lang::get('thank', 'core') . ' ' . htmlspecialchars($config['home_title'] ?? 'our site') . '. 
            ' . Lang::get('verify_email_body', 'core') . '</p>

            <p style="text-align: center; margin: 25px 0;">
                <a href="' . htmlspecialchars($verificationUrl) . '" class="button">
                    ' . Lang::get('verify', 'core') . '
                </a>
            </p>

            <p>' . Lang::get('reset_email_body2', 'core') . '<br>
            <code>' . htmlspecialchars($verificationUrl) . '</code></p>

            <p>' . Lang::get('verify_email_body2', 'core') . '</p>
            
            <p>' . Lang::get('reset_email_body4', 'core') . ',<br>' . htmlspecialchars($config['home_title'] ?? 'simpleBlog') . '</p>
        ';

        return Mailer::send($email, $subject, $message);
    }

    public function verifyEmail($token)
    {
        global $dbPrefix;

        // Check if token exists
        $stmt = $this->pdo->prepare(
            "SELECT id 
            FROM {$dbPrefix}users 
            WHERE verification_token = ? AND email_verified = 0"
        );
        $stmt->execute([$token]);
        $userId = $stmt->fetchColumn();

        if ($userId) {
            // Mark email as verified
            $stmt = $this->pdo->prepare(
                "UPDATE {$dbPrefix}users 
                SET email_verified = 1, verification_token = NULL 
                WHERE id = ?"
            );
            flash(Lang::get('email_verified', 'core'), 'success');
            return $stmt->execute([$userId]);
        }
        flash(Lang::get('invalid_token', 'core'), 'error');
        return false;
    }

    public function sendPasswordReset($email)
    {
        global $dbPrefix, $config;

        $stmt = $this->pdo->prepare(
            "SELECT id, username 
            FROM {$dbPrefix}users 
            WHERE email = ?"
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $resetToken = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour expiration

            $stmt = $this->pdo->prepare(
                "UPDATE {$dbPrefix}users 
                SET reset_token = ?, reset_expires = ? 
                WHERE id = ?"
            );
            if ($stmt->execute([$resetToken, $expires, $user['id']])) {
                return $this->sendResetEmail($user['username'], $email, $resetToken);
            }
        }

        return false;
    }

    private function sendResetEmail($username, $email, $token)
    {
        global $config;
        Mailer::init($config);

        $resetUrl = "https://{$_SERVER['HTTP_HOST']}/?action=reset_password&token=$token";
        $subject = Lang::get('reset_email_subject', 'core');

        $message = '
            <p>' . Lang::get('hiuser', 'main') . ' <strong>' . htmlspecialchars($username) . '</strong>,</p>

            <p>' . Lang::get('reset_email_body', 'core') . '</p>

            <p style="text-align: center; margin: 25px 0;">
                <a href="' . htmlspecialchars($resetUrl) . '" class="button">
                    ' . Lang::get('reset_password', 'core') . '
                </a>
            </p>

            <p>' . Lang::get('reset_email_body2', 'core') . '<br>
            <code>' . htmlspecialchars($resetUrl) . '</code></p>

            <p>' . Lang::get('reset_email_body3', 'core') . '</p>

            <p>' . Lang::get('reset_email_body4', 'core') . ',<br>' . htmlspecialchars($config['home_title'] ?? 'simpleBlog') . '</p>
        ';

        return Mailer::send($email, $subject, $message);
    }

    public function resetPassword($token, $newPassword)
    {
        global $dbPrefix;

        // Check if token is valid and not expired
        $stmt = $this->pdo->prepare(
            "SELECT id 
            FROM {$dbPrefix}users 
            WHERE reset_token = ? AND reset_expires > NOW()"
        );
        $stmt->execute([$token]);
        $userId = $stmt->fetchColumn();

        if ($userId) {
            $hash = password_hash($newPassword, PASSWORD_BCRYPT);
            $stmt = $this->pdo->prepare(
                "UPDATE {$dbPrefix}users 
                SET password = ?, reset_token = NULL, reset_expires = NULL 
                WHERE id = ?"
            );
            return $stmt->execute([$hash, $userId]);
        }

        return false;
    }

    /**
     * Обновляет данные профиля пользователя
     */
    public function updateProfile($userId, $username, $email, $avatar = null, $currentPassword = null)
    {
        global $dbPrefix;

        try {
            // Проверяем, изменился ли email
            $stmtCurrent = $this->pdo->prepare("SELECT email FROM {$dbPrefix}users WHERE id = ?");
            $stmtCurrent->execute([$userId]);
            $currentEmail = $stmtCurrent->fetchColumn();

            $emailChanged = ($currentEmail !== $email);
            $verificationToken = null;
            $updates = [];
            $params = [];

            // Если email изменился, выполняем дополнительные проверки
            if ($emailChanged) {
                // Проверяем, не занят ли новый email
                $stmtCheck = $this->pdo->prepare(
                    "SELECT COUNT(*) 
                    FROM {$dbPrefix}users 
                    WHERE email = ? AND id != ?"
                );
                $stmtCheck->execute([$email, $userId]);
                $exists = $stmtCheck->fetchColumn();

                if ($exists) {
                    flash(Lang::get('email_exists', 'core'), 'error');
                    return false;
                }

                // Требуем текущий пароль для смены email
                if (!$currentPassword) {
                    flash(Lang::get('current_password_required', 'core'));
                    return false;
                }

                // Проверяем текущий пароль
                $stmtPass = $this->pdo->prepare("SELECT password FROM {$dbPrefix}users WHERE id = ?");
                $stmtPass->execute([$userId]);
                $user = $stmtPass->fetch();

                if (!$user || !password_verify($currentPassword, $user['password'])) {
                    flash(Lang::get('invalid_password', 'core'));
                    return false;
                }

                // Генерируем токен верификации
                $verificationToken = bin2hex(random_bytes(32));
                $updates[] = 'verification_token = ?';
                $updates[] = 'email_verified = 0';
                $params[] = $verificationToken;
            }

            // Добавляем обновление username
            $updates[] = 'username = ?';
            $params[] = $username;

            // Добавляем обновление email
            $updates[] = 'email = ?';
            $params[] = $email;

            // Добавляем аватар, если есть
            if ($avatar) {
                $updates[] = 'avatar = ?';
                $params[] = $avatar;
            }

            // Добавляем ID пользователя в параметры
            $params[] = $userId;

            // Формируем и выполняем SQL запрос
            $sql = "UPDATE {$dbPrefix}users SET " . implode(', ', $updates) . " WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute($params);

            // Если email изменился, отправляем письмо для подтверждения
            if ($result && $emailChanged) {
                $this->sendVerificationEmail($username, $email, $verificationToken);
                flash(Lang::get('email_change_success_verify', 'core'), 'success');
            } elseif ($result) {
                flash(Lang::get('profile_update_success', 'core'), 'success');
            }

            return $result;
        } catch (Exception $e) {
            error_log("Profile update error: " . $e->getMessage());
            flash(Lang::get('profile_update_error', 'core'));
            return false;
        }
    }

    public function changeEmail($userId, $newEmail, $password)
    {
        global $dbPrefix, $config;

        // Сначала проверяем правильность пароля
        $stmt = $this->pdo->prepare("SELECT password FROM {$dbPrefix}users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            flash(Lang::get('invalid_password', 'core'), 'error');
            return false;
        }

        // Проверяем, не занят ли новый email
        $stmtCheck = $this->pdo->prepare(
            "SELECT COUNT(*) 
            FROM {$dbPrefix}users 
            WHERE email = ? AND id != ?"
        );
        $stmtCheck->execute([$newEmail, $userId]);
        $exists = $stmtCheck->fetchColumn();

        if ($exists) {
            flash(Lang::get('email_exists', 'core'), 'error');
            return false;
        }

        // Генерируем новый токен верификации
        $verificationToken = bin2hex(random_bytes(32));

        // Обновляем email и устанавливаем статус неверифицированным
        $stmtUpdate = $this->pdo->prepare(
            "UPDATE {$dbPrefix}users 
            SET email = ?, verification_token = ?, email_verified = 0 
            WHERE id = ?"
        );

        if ($stmtUpdate->execute([$newEmail, $verificationToken, $userId])) {
            // Получаем имя пользователя для письма
            $stmtUsername = $this->pdo->prepare("SELECT username FROM {$dbPrefix}users WHERE id = ?");
            $stmtUsername->execute([$userId]);
            $username = $stmtUsername->fetchColumn();

            $this->sendVerificationEmail($username, $newEmail, $verificationToken);
            flash(Lang::get('email_change_success_verify', 'core'), 'success');
            return true;
        }

        return false;
    }

    /**
     * Добавляет/обновляет привязку социальной сети
     */
    public function setSocialLink($userId, $socialType, $socialId, $username = null)
    {
        global $dbPrefix;

        try {
            error_log(
                "Attempting to link social: user_id=$userId, type=$socialType, id=$socialId, username=$username"
            );

            // Проверяем существующую привязку
            $stmt = $this->pdo->prepare(
                "SELECT id 
                FROM {$dbPrefix}user_social 
                WHERE user_id = ? AND social_type = ?"
            );
            $stmt->execute([$userId, $socialType]);
            $existing = $stmt->fetch();

            if ($existing) {
                error_log("Updating existing social link: ID " . $existing['id']);
                $stmt = $this->pdo->prepare(
                    "UPDATE {$dbPrefix}user_social SET 
                    social_id = ?, 
                    social_username = ? 
                    WHERE id = ?"
                );
                $result = $stmt->execute([$socialId, $username, $existing['id']]);
            } else {
                error_log("Creating new social link");
                $stmt = $this->pdo->prepare(
                    "INSERT INTO {$dbPrefix}user_social 
                    (user_id, social_type, social_id, social_username) 
                    VALUES (?, ?, ?, ?)"
                );
                $result = $stmt->execute([$userId, $socialType, $socialId, $username]);
            }

            error_log("Operation result: " . ($result ? "success" : "failed"));
            error_log("PDO errorInfo: " . json_encode($this->pdo->errorInfo()));

            return $result;
        } catch (Exception $e) {
            error_log("Social link error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Получает привязанные социальные сети пользователя
     */
    public function getSocialLinks($userId)
    {
        global $dbPrefix;

        $stmt = $this->pdo->prepare(
            "SELECT social_type, social_id, social_username 
            FROM {$dbPrefix}user_social 
            WHERE user_id = ?"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Удаляет привязку социальной сети
     */
    public function removeSocialLink($userId, $socialType)
    {
        global $dbPrefix;

        try {
            $stmt = $this->pdo->prepare(
                "DELETE FROM {$dbPrefix}user_social 
                WHERE user_id = ? AND social_type = ?"
            );
            return $stmt->execute([$userId, $socialType]);
        } catch (Exception $e) {
            error_log("Remove social link error: " . $e->getMessage());
            return false;
        }
    }

    public function login($username, $password)
    {
        global $dbPrefix;

        // Rate limiting check
        $security = [
            'max_attempts' => 5,         // Макс. попыток до полной блокировки
            'block_time' => 3600,        // Время блокировки (1 час)
            'initial_delay' => 10,       // Задержка после 1-й попытки (сек)
            'progressive_delay' => 30    // Задержка после 3+ попыток (сек)
        ];

        // Проверка блокировки
        if (
            $_SESSION['login_attempts'] >= $security['max_attempts']
            && time() - $_SESSION['last_attempt'] < $security['block_time']
        ) {
            flash(
                Lang::get('too_many_attempts', 'core') . ' Попробуйте через ' .
                ceil(($security['block_time'] - (time() - $_SESSION['last_attempt'])) / 60) . ' минут.',
                'error'
            );
            return false;
        }

        // Прогрессивная задержка
        if ($_SESSION['login_attempts'] >= 1) {
            $delay = ($_SESSION['login_attempts'] >= 3)
                ? $security['progressive_delay']
                : $security['initial_delay'];
            sleep($delay);
        }

        $stmt = $this->pdo->prepare("SELECT * FROM {$dbPrefix}users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION = [];
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['isadmin'] = $user['isadmin'];
            $_SESSION['ip'] = $_SERVER['REMOTE_ADDR'];
            $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
            $_SESSION['created_at'] = time();
            // Сбрасываем счетчик попыток
            $_SESSION['login_attempts'] = 0;
            Cache::clear();
            return $user;
        }

        // Увеличиваем счетчик попыток
        $_SESSION['login_attempts']++;
        $_SESSION['last_attempt'] = time();
        return false;
    }

    public function logout()
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = [];
        Cache::clear();
        session_destroy();
    }
}

function flash(?string $message = null, ?string $type = null)
{
    if ($message) {
        $_SESSION['flash'] = [
            'message' => $message,
            'type' => $type ?? 'info' // default, success, error, warning
        ];
    } else {
        if (!empty($_SESSION['flash'])) {
            $flash = $_SESSION['flash'];

            // Совместимая замена оператора match
            $color = 'blue'; // значение по умолчанию
            if ($flash['type'] === 'success') {
                $color = 'green';
            } elseif ($flash['type'] === 'error') {
                $color = 'red';
            } elseif ($flash['type'] === 'warning') {
                $color = 'orange';
            }
            ?>
            <div style="color: <?= $color ?>; padding: 10px; margin: 10px 0; border: 1px solid <?= $color ?>; border-radius: 4px;">
                <?= htmlspecialchars($flash['message'] ?? '') ?>
            </div>
        <?php }
        unset($_SESSION['flash']);
    }
}

/**
 * Generate random color for default avatars
 */
function getRandomColor()
{
    $colors = ['#3498db', '#2ecc71', '#e74c3c', '#f39c12', '#9b59b6', '#1abc9c'];
    return $colors[array_rand($colors)];
}