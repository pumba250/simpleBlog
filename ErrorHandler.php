<?php

if (!defined('IN_SIMPLECMS')) {
    die('Прямой доступ запрещен');
}
/**
 * Централизованный обработчик ошибок и исключений
 *
 * @package    SimpleBlog
 * @subpackage Core
 * @category   Views
 * @version    1.0.0
 *
 * @method static void init(bool $debugMode = false)                     Инициализирует обработчики ошибок и исключений
 * @method static void handleException(Throwable $e)                     Обрабатывает исключения
 * @method static bool handleError(int $errno, string $errstr, string $errfile, int $errline) Обрабатывает ошибки
 * @method static void handleShutdown()                                  Обрабатывает фатальные ошибки при завершении
 */
class ErrorHandler
{
    private static $logFile = 'logs/errors.log';
    private static $debugMode = false;

    public static function init(bool $debugMode = false)
    {
        self::$debugMode = $debugMode;

        // Обработчик исключений
        set_exception_handler([self::class, 'handleException']);

        // Обработчик ошибок
        set_error_handler([self::class, 'handleError']);

        // Обработчик фатальных ошибок
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    public static function handleException(Throwable $e)
    {
        self::logError($e);
        self::displayError($e);
    }

    public static function handleError(int $errno, string $errstr, string $errfile, int $errline)
{
    // Если ошибка подавлена через @ — не трогаем
    if (!(error_reporting() & $errno)) {
        return false;
    }

    // E_WARNING, E_NOTICE, E_DEPRECATED — только в лог, не фатал
    if (in_array($errno, [
        E_WARNING, E_NOTICE, E_DEPRECATED,
        E_USER_WARNING, E_USER_NOTICE, E_USER_DEPRECATED,
    ], true)) {
        self::logWarning($errno, $errstr, $errfile, $errline);
        return true; // подавляем стандартный обработчик
    }

    // E_ERROR, E_USER_ERROR и т.п. — фатал
    $exception = new ErrorException($errstr, 0, $errno, $errfile, $errline);
    self::handleException($exception);

    return true;
}

private static function logWarning(int $errno, string $errstr, string $errfile, int $errline): void
{
    if (!file_exists(dirname(self::$logFile))) {
        mkdir(dirname(self::$logFile), 0755, true);
    }

    $message = sprintf(
        "[%s] [PHP %d] %s in %s:%d\n",
        date('Y-m-d H:i:s'),
        $errno,
        $errstr,
        $errfile,
        $errline
    );

    file_put_contents(self::$logFile, $message, FILE_APPEND);
}

    public static function handleShutdown()
    {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            self::handleException(new ErrorException($error['message'], $error['type'], 0, $error['file'], $error['line']));
        }
    }

    private static function logError(Throwable $e)
    {
        // Создаем директорию для логов если ее нет
        if (!file_exists(dirname(self::$logFile))) {
            mkdir(dirname(self::$logFile), 0755, true);
        }

        $message = sprintf(
            "[%s] %s in %s:%d\nStack Trace:\n%s\n\n",
            date('Y-m-d H:i:s'),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        );

        file_put_contents(self::$logFile, $message, FILE_APPEND);
    }

    private static function shouldShowDebugInfo(): bool
    {
        // Режим отладки включен в конфиге ИЛИ пользователь - администратор
        return self::$debugMode || (isset($_SESSION['user']['isadmin']) && $_SESSION['user']['isadmin'] == 9);
    }

    private static function displayError(Throwable $e)
    {
        if (headers_sent() === false) {
            header($_SERVER["SERVER_PROTOCOL"] . ' 500 Internal Server Error');
            header('Content-Type: text/html; charset=utf-8');
        }

        if (self::shouldShowDebugInfo()) {
            $errorDetails = [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'type' => get_class($e),
                'trace' => array_slice($e->getTrace(), 0, 10) // Ограничиваем глубину трассировки
            ];

            // Дополнительная информация только для админов
            if (isset($_SESSION['user']['isadmin']) && $_SESSION['user']['isadmin'] == 9) {
                $errorDetails['full_trace'] = $e->getTrace();
                $errorDetails['request'] = [
                    'method' => $_SERVER['REQUEST_METHOD'],
                    'uri' => $_SERVER['REQUEST_URI'] ?? '',
                    'query' => $_GET,
                    'post' => self::sanitizePostData($_POST),
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                    'session' => self::sanitizeSessionData($_SESSION)
                ];
            }

            require __DIR__ . '/../templates/error_debug.tpl';
        } else {
            require __DIR__ . '/../templates/error_500.tpl';
        }

        exit(1);
    }

    private static function sanitizeSessionData(array $session): array
    {
        $sanitized = [];
        foreach ($session as $key => $value) {
            if (in_array($key, ['user', 'csrf_token'])) {
                if ($key === 'user') {
                    $sanitized[$key] = [
                        'id' => $value['id'] ?? null,
                        'username' => $value['username'] ?? null,
                        'isadmin' => $value['isadmin'] ?? null
                    ];
                } else {
                    $sanitized[$key] = '*****';
                }
            }
        }
        return $sanitized;
    }

    private static function sanitizePostData(array $data): array
    {
        $sanitized = [];
        foreach ($data as $key => $value) {
            if (strtolower($key) === 'password' || strpos(strtolower($key), 'pass') !== false) {
                $sanitized[$key] = '*****';
            } else {
                $sanitized[$key] = is_array($value) ? self::sanitizePostData($value) : $value;
            }
        }
        return $sanitized;
    }
}
