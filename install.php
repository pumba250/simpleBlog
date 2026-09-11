<?php
/**
 * Установочный скрипт системы
 * 
 * @package    SimpleBlog
 * @subpackage Installer
 * @version    1.0.0
 * 
 * @security
 * - Валидация вводимых данных
 * - Безопасная работа с сессиями
 * - Шифрование паролей в конфигурации
 * - Проверка прав доступа к файлам
 * - Блокировка по IP при множественных попытках
 */

// Check PHP version
if (version_compare(PHP_VERSION, '8.1.0', '<')) {
    die('Требуется PHP версии 8.1 или выше.');
}

// Secure session settings
session_start([
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'use_strict_mode' => true
]);

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['csrf_generated'] = time();
}

// IP-based installation blocking
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$installAttempts = $_SESSION['install_attempts'] ?? 0;

if ($installAttempts > 3) {
    die("Слишком много попыток установки. Попробуйте позже.");
}

// Secure input validation
function validateInput($input, $pattern = null) {
    if (!is_string($input)) {
        return '';
    }
    
    $cleaned = trim($input);
    $cleaned = strip_tags($cleaned);
    $cleaned = htmlspecialchars($cleaned, ENT_QUOTES, 'UTF-8');
    
    if ($pattern && !preg_match($pattern, $cleaned)) {
        return '';
    }
    
    return $cleaned;
}

// Secure SQL file execution
function executeSqlFile($filename, $pdo, $prefix = '') {
    if (!file_exists($filename)) {
        throw new Exception("SQL файл не найден");
    }
    
    // Check file permissions
    if (substr(sprintf('%o', fileperms($filename)), -4) !== '0600') {
        throw new Exception("Небезопасные права доступа к SQL файлу");
    }
    
    $sql = file_get_contents($filename);
    
    if (!empty($prefix)) {
        $prefix = rtrim($prefix, '_');
        $prefix .= '_';
        
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $prefix)) {
            throw new Exception("Префикс таблиц должен начинаться с буквы и содержать только буквы и цифры");
        }
        
        $sql = str_replace('{PREFIX_}', $prefix, $sql);
    }
    
    // Split queries more carefully
    $queries = preg_split('/;\s*(?=([^"]*"[^"]*")*[^"]*$)/', $sql);
    foreach ($queries as $query) {
        $query = trim($query);
        if (!empty($query)) {
            $pdo->exec($query);
        }
    }
}

// Secure database connection test
function testDatabaseConnection($host, $name, $user, $pass) {
    try {
        // Validate host (prevent SSRF)
        if (!preg_match('/^[a-zA-Z0-9\.\-]+$/', $host)) {
            return false;
        }
        
        $pdo = new PDO("mysql:host=$host", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => false
        ]);
        
        // Use prepared statement to check database existence
        $stmt = $pdo->prepare("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?");
        $stmt->execute([$name]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Database connection error: " . $e->getMessage());
        return false;
    }
}

// Secure file deletion
function deleteInstallationFiles() {
    $files = [
        __FILE__,
        dirname(__FILE__) . '/sql/schema.sql',
        dirname(__FILE__) . '/sql/'
    ];
    
    foreach ($files as $file) {
        if (file_exists($file)) {
            if (is_dir($file)) {
                array_map('unlink', glob("$file/*.*"));
                @rmdir($file);
            } else {
                @unlink($file);
            }
        }
    }
}

// Process installation form
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $_SESSION['install_attempts'] = ++$installAttempts;
    
    // Validate CSRF token with time check
    if (!isset($_POST['csrf_token']) || 
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']) ||
        (time() - $_SESSION['csrf_generated']) > 3600) {
        die("Неверный или устаревший CSRF-токен");
    }

    // Validate inputs with strict patterns
    $dbHost = validateInput($_POST['db_host'] ?? '', '/^[a-zA-Z0-9\.\-]+$/');
    $dbName = validateInput($_POST['db_name'] ?? '', '/^[a-zA-Z0-9_\-]+$/');
    $dbUser = validateInput($_POST['db_user'] ?? '', '/^[a-zA-Z0-9_\-]+$/');
    $dbPass = $_POST['db_pass'] ?? '';
    $dbPrefix = isset($_POST['db_prefix']) ? validateInput($_POST['db_prefix'], '/^[a-zA-Z][a-zA-Z0-9_]*$/') : '';
    
    $dbPrefix = rtrim($dbPrefix, '_');
    if (!empty($dbPrefix)) {
        $dbPrefix .= '_';
    }

    // Check if config already exists
    if (file_exists('config/config.php')) {
        if (substr(sprintf('%o', fileperms('config/config.php')), -4) !== '0600') {
            die("Обнаружен небезопасный конфигурационный файл. Удалите его вручную перед повторной установкой.");
        }
        die("Конфигурационный файл уже существует. Удалите его для повторной установки.");
    }

    // Test database connection
    if (!testDatabaseConnection($dbHost, $dbName, $dbUser, $dbPass)) {
        die("Не удалось подключиться к БД или БД не существует");
    }

    try {
        // Create database connection
        $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => false
        ]);

        // Execute schema with error handling
        executeSqlFile('sql/schema.sql', $pdo, $dbPrefix);
        
        // Create config directory with secure permissions
        if (!file_exists('config')) {
            if (!mkdir('config', 0750, true)) {
                throw new Exception("Не удалось создать директорию config");
            }
        }

        // Generate secure config file
        $configContent = "<?php
if (!defined('IN_SIMPLECMS')) { die('Прямой доступ запрещен'); }
return [
    'host' => '".addslashes($dbHost)."',
    'database' => '".addslashes($dbName)."',
    'db_user' => '".addslashes($dbUser)."',
    'db_pass' => '"..addslashes($dbPass)."',
    'home_title' => 'Заголовок вашего сайта',
    'templ' => 'simple',
    'db_prefix' => '".addslashes($dbPrefix)."',
    'csrf_token_name' => 'csrf_token',
    'max_backups' => 5,
    'backup_schedule' => 'disabled',
    'backup_dir' => 'admin/backups/',
	'upload_dir' => 'uploads/',
    'blocks_for_reg' => true,
    'metaKeywords' => 'Здесь ключевые слова, через запятую (,)',
    'metaDescription' => 'Здесь описание вашего сайта',
    'mail_from' => 'no-reply@yourdomain.com',
    'mail_from_name' => 'SimpleBlogCMS Notifications',
    'blogs_per_page' => 6,
    'comments_per_page' => 10,
    'powered' => 'simpleBlogCMS',
    'version' => 'v1.0.1',
	'pretty_urls' => false,
	'github_repo' => 'pumba250/simpleBlogCMS',
	'update_check_interval' => 86400, // 24 часов в секундах
	'disable_update_check' => false,
    'install_ip' => '".addslashes($ip)."',
    'install_time' => ".time()."
];";

        // Write config with strict permissions
        if (file_put_contents('config/config.php', $configContent) === false) {
            throw new Exception("Не удалось записать конфигурационный файл");
        }
        chmod('config/config.php', 0600);

        // Clean up
        $deleteFiles = isset($_POST['delete_files']) && $_POST['delete_files'] == '1';
        
        echo "<!DOCTYPE html>
        <html>
        <head>
            <title>Установка завершена</title>
            <meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\">
            <style>
                body { font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; }
                .success { color: green; }
                .warning { color: orange; }
            </style>
        </head>
        <body>
            <h1>Установка SimpleBlogCMS</h1>
            <p class=\"success\">Установка завершена успешно!</p>";
        
        if ($deleteFiles) {
            echo "<p>Удаление установочных файлов...</p>";
            deleteInstallationFiles();
            echo "<p class=\"success\">Установочные файлы удалены.</p>";
        } else {
            echo "<p class=\"warning\">Не забудьте удалить install.php и папку sql вручную!</p>";
        }
        
        echo '<meta http-equiv="refresh" content="3;URL=/?action=register">
            </body>
        </html>';
        
        exit;
    } catch (PDOException $e) {
        error_log("Database error during installation: " . $e->getMessage());
        die("Ошибка базы данных: " . htmlspecialchars($e->getMessage()));
    } catch (Exception $e) {
        error_log("Installation error: " . $e->getMessage());
        die("Ошибка установки: " . htmlspecialchars($e->getMessage()));
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Установка simpleBlogCMS</title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { 
            font-family: Arial, sans-serif; 
            max-width: 600px; 
            margin: 0 auto; 
            padding: 20px;
            line-height: 1.6;
        }
        label { 
            display: inline-block; 
            width: 180px; 
            margin-bottom: 10px; 
            font-weight: bold;
        }
        input[type="text"], input[type="password"] { 
            width: 100%; 
            max-width: 300px;
            padding: 8px; 
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .checkbox-label { 
            width: auto; 
            vertical-align: middle;
        }
        button { 
            padding: 12px 24px; 
            background: #4CAF50; 
            color: white; 
            border: none; 
            border-radius: 4px;
            cursor: pointer; 
            font-size: 16px;
            transition: background 0.3s;
        }
        button:hover { 
            background: #45a049; 
        }
        .form-group {
            margin-bottom: 15px;
        }
        .note {
            font-size: 0.9em;
            color: #666;
            margin-top: -10px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <h1>Установка simpleBlogCMS</h1>
    <form method="POST" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
        
        <div class="form-group">
            <label for="db_host">Хост БД:</label>
            <input type="text" name="db_host" required pattern="[a-zA-Z0-9\.\-]+" value="localhost" autocomplete="off">
            <div class="note">Обычно localhost или IP адрес сервера</div>
        </div>
        
        <div class="form-group">
            <label for="db_name">Имя БД:</label>
            <input type="text" name="db_name" required pattern="[a-zA-Z0-9_\-]+" autocomplete="off">
            <div class="note">Имя существующей базы данных</div>
        </div>
        
        <div class="form-group">
            <label for="db_user">Пользователь БД:</label>
            <input type="text" name="db_user" required pattern="[a-zA-Z0-9_\-]+" autocomplete="off">
        </div>
        
        <div class="form-group">
            <label for="db_pass">Пароль БД:</label>
            <input type="password" name="db_pass" required autocomplete="new-password">
        </div>
        
        <div class="form-group">
            <label for="db_prefix">Префикс таблиц:</label>
            <input type="text" name="db_prefix" placeholder="Например: sb_" pattern="[a-zA-Z][a-zA-Z0-9_]*" 
                   title="Префикс должен начинаться с буквы и содержать только буквы, цифры и подчеркивания" autocomplete="off">
            <div class="note">Рекомендуется для безопасности и совместимости</div>
        </div>
        
        <div class="form-group">
            <label>
                <input type="checkbox" name="delete_files" value="1" checked>
                <span class="checkbox-label">Удалить установочные файлы после успешной установки</span>
            </label>
        </div>
        
        <button type="submit">Установить</button>
    </form>
</body>
</html>
