<?php

if (!defined('IN_SIMPLECMS')) {
    die('Прямой доступ запрещен');
}

/**
 * Шаблонизатор системы с поддержкой расширенного синтаксиса
 *
 * @package    SimpleBlog
 * @subpackage Core
 * @category   Views
 * @version    1.0.0
 *
 * @method void   __construct()                                                Инициализирует шаблонизатор
 * @method void   assign(string $key, mixed $value)                            Назначает переменную шаблона
 * @method void   assignMultiple(array $vars)                                  Назначает несколько переменных
 * @method string render(string $templateFile, array $additionalVars = [])     Рендерит шаблон
 * @method array  getCommonTemplateVars(array $config, News $news, User|null $user) Возвращает общие переменные
 * @method bool   changeTemplate(string $newTemplate, array $availableTemplates, array &$config, string $configPath) Изменяет активный шаблон
 * @method array  getAvailableTemplates(string $templatesDir)                  Возвращает список доступных шаблонов
 * @method string formatSize(int $bytes)                                       Форматирует размер в байтах
 * @method string renderTemplateString(string $template, array $data)          Рендерит строку шаблона
 * @method string renderTemplateId(string $template, array $data)              Альтернативный рендеринг шаблона
 * @method string renderNewsList(array $newsItems, string $templateFile)       Рендерит список новостей
 * @method string renderNewsItem(array|object $newsItem, string $templateFile) Рендерит одну новость
 * @method string processComments(array $comments, string $templateFile)       Обрабатывает комментарии
 * @method string formatDate(string $date)                                     Форматирует дату
 * @method array  objectToArray(object $object)                                Конвертирует объект в массив
 * @method string renderFooter(array $footerData)                              Рендерит футер
 */
class Template
{
    protected $variables = [];
    protected $templateDir;
    protected $templateCache = [];
    protected $config;
    protected $news;

    public function __construct()
    {
        global $templ, $config, $news;
        $this->config = $config;
        $this->news = $news;
        $this->templateDir = "templates/{$templ}";

        if (!is_dir($this->templateDir)) {
            throw new Exception("Директория с шаблонами не найдена: {$this->templateDir}");
        }
    }

    public function assignMultiple(array $vars): void
    {
        foreach ($vars as $key => $value) {
            $this->assign($key, $value);
        }
    }

    public function assign($key, $value)
    {
        if (is_string($value)) {
            $this->variables[$key] = htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8', false);
        } else {
            $this->variables[$key] = $value;
        }
    }

    public function getCommonTemplateVars($config, $news, $user = null)
    {
        global $flash;
        $currentCommentPage = $_GET['comment_page'] ?? 1;
        $commentsPerPage = $config['comments_per_page'];
        $authError = $_SESSION['auth_error'] ?? null;
        //$flash = $_SESSION['flash'] ?? null;
        if (isset($_SESSION['auth_error'])) {
            unset($_SESSION['auth_error']);
        }
        if (isset($_SESSION['flash'])) {
            unset($_SESSION['flash']);
        }

        return [
            'powered' => $config['powered'],
            'version' => $config['version'],
            'dbPrefix' => $config['db_prefix'],
            'csrf_token' => $_SESSION['csrf_token'],
            'templ' => $config['templ'],
            'captcha_image_url' => '/class/captcha.php',
            'allTags' => $news->GetAllTags(),
            'lastThreeNewsHtml' => $news->getLastThreeNews(),
            'user' => $user ?? null,
            'auth_error' => $authError,
            'flash' => $flash,
            'comments' => $comments ?? null,
            'currentYear' => date("Y"),
            'serverName' => htmlspecialchars($_SERVER['SERVER_NAME']),
			'currentLang' => $_SESSION['lang'] ?? 'ru',
        ];
    }

    /**
     * Рендерит шаблон с передачей переменных
     *
     * @param string $templateFile Имя файла шаблона (относительно templateDir)
     * @param array $additionalVars Дополнительные переменные для этого рендеринга
     * @return string
     * @throws Exception Если файл шаблона не найден или ошибка рендеринга
     */
    public function render(string $templateFile, array $additionalVars = []): string
    {
        $templatePath = $this->templateDir . '/' . ltrim($templateFile, '/');

        if (!file_exists($templatePath)) {
            throw new \RuntimeException("Template file not found: {$templatePath}");
        }

        $vars = array_merge($this->variables, $additionalVars);
        $templateContent = file_get_contents($templatePath);
        $compiledContent = $this->compileTemplate($templateContent);

        $tempFile = tempnam(sys_get_temp_dir(), 'tpl_');
        file_put_contents($tempFile, $compiledContent);

        $originalTemplateDir = $this->templateDir;

        extract($vars, EXTR_SKIP);
        ob_start();

        try {
            include $tempFile;
            unlink($tempFile);
        } catch (\Throwable $e) {
            ob_end_clean();
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
            error_log("Template error: " . $e->getMessage());
            if ($this->config['debug'] ?? false) {
                throw $e;
            }
            return '<div class="error">Page rendering error</div>';
        } finally {
            $this->templateDir = $originalTemplateDir;
        }

        return ob_get_clean();
    }

    /**
     * Компилирует шаблон с синтаксисом {} в PHP-код
     */
    protected function compileTemplate(string $content): string
    {
        $replacements = [
            // Условия
            '/\{if\s+(.+?)\}/' => '<?php if ($1): ?>',
            '/\{elseif\s+(.+?)\}/' => '<?php elseif ($1): ?>',
            '/\{else\}/' => '<?php else: ?>',
            '/\{\/if\}/' => '<?php endif; ?>',

            // Статические методы (Lang::get)
            '/\{([A-Za-z_][A-Za-z0-9_]*)::([a-zA-Z0-9_]+)\(([^)]*)\)\}/' => '<?php echo $1::$2($3); ?>',

            // Языковые переменные (из $lang)
            '/\{l_([a-zA-Z0-9_]+)(?:\:([a-zA-Z0-9_]+))?\}/' => '<?php $key = \'$1\'; $section = !empty(\'$2\') ? \'$2\' : \'main\'; echo htmlspecialchars(Lang::get($key, $section), ENT_QUOTES, \'UTF-8\'); ?>',

            // Конфигурационные переменные (из $config)
            '/\{c_([a-zA-Z0-9_]+)\}/' => '<?php echo $config[\'$1\'] ?? \'\'; ?>',
            '/\{var_([a-zA-Z0-9_]+)\}/' => '<?php echo var_dump($1) ?? \'\'; ?>',
            // Циклы
            '/\{foreach\s+\$([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\s+as\s+\$([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\}/' => '<?php foreach ($$1 as $$2): ?>',
            '/\{for \$([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\s*=\s*([^\s]+)\s+to\s+([^\}]+)\s*\}/' => '<?php for ($1 = $2; $1 <= $3; $1++): ?>',
            '/\{\/for\}/' => '<?php endfor; ?>',
            '/\{foreach\s+\$([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\s+as\s+\$([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\s*=>\s*\$([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\}/' => '<?php foreach ($$1 as $$2 => $$3): ?>',
            '/\{\/foreach\}/' => '<?php endforeach; ?>',

            // Обработка $_SESSION, $_GET, $_POST и других суперглобальных массивов
            '/\{\$_([A-Za-z_]+)\[\'([^\']+)\'\]\}/' => '<?php echo htmlspecialchars($_$1[\'$2\'] ?? \'\', ENT_QUOTES, \'UTF-8\'); ?>',

            // Включения (исправлено)
            '/\{include\s+[\'"](.+?)[\'"]\}/' => '<?php echo $this->render(\'$1\'); ?>',
            '/\{\$([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\[\'([^\']+)\'\]\s*\?:\s*([^\}]+)\}/' => '<?php echo htmlspecialchars(($$1[\'$2\'] ?? $3), ENT_QUOTES, \'UTF-8\'); ?>',
            '/\{\$([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\.([a-zA-Z0-9_\x7f-\xff]+)\s*\?:\s*([^\}]+)\}/' => '<?php echo htmlspecialchars((is_array($$1) ? ($$1[\'$2\'] ?? $3) : ($$1->$2 ?? $3)), ENT_QUOTES, \'UTF-8\'); ?>',

            // Общее правило для элементов массивов
            '/\{\$([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\[\'([^\']+)\'\]\}/' => '<?php echo htmlspecialchars($$1[\'$2\'] ?? \'\', ENT_QUOTES, \'UTF-8\'); ?>',

            // Переменные
            '/\{\$([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\}/' => '<?php echo htmlspecialchars($$1, ENT_QUOTES, \'UTF-8\'); ?>',
            '/\{\$([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\.([a-zA-Z0-9_\x7f-\xff]+)\}/' => '<?php echo htmlspecialchars((is_array($$1) ? $$1[\'$2\'] : $$1->$2), ENT_QUOTES, \'UTF-8\'); ?>',
            '/\{\$([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)->([a-zA-Z0-9_\x7f-\xff]+)\}/' => '<?php echo htmlspecialchars($$1->$2, ENT_QUOTES, \'UTF-8\'); ?>',

            // Для арифметических операций с элементами массива
            '/\{\$([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\[\'([a-zA-Z0-9_\x7f-\xff]+)\'\]\s*([+\-*\/])\s*([0-9]+)\}/' => '<?php echo htmlspecialchars($$1[\'$2\'] $3 $4, ENT_QUOTES, \'UTF-8\'); ?>',
            '/\{\$([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\s*([+\-*\/])\s*([0-9]+)\}/' => '<?php echo htmlspecialchars($$1 $2 $3, ENT_QUOTES, \'UTF-8\'); ?>',

            // Форматирование дат
            '/\{([^\|]+)\|date_format\}/' => '<?php echo date("d.m.Y", strtotime($1)); ?>',

            // Проверка на пустоту (empty)
            '/\{empty\s+(.+?)\}/' => '<?php if(empty($1)): ?>',

            // Модификаторы для переменных
            '/\{([^\|]+)\|upper\}/' => '<?php echo strtoupper($1); ?>',
            '/\{([^\|]+)\|lower\}/' => '<?php echo strtolower($1); ?>',

            // Более сложные условия в {if}
            '/\{if\s+([^}]+)\}/' => '<?php if ($1): ?>',

            // Обработка тернарных операторов
            '/\\{\\$(?<var>[a-zA-Z0-9_]+(?:\\.[a-zA-Z0-9_]+)*)\\s*\\?\\s*(?<true>[^:]+)\\s*:\\s*(?<false>[^}]+)\\}/' => '<?php $parts = explode(".", "$1"); $val = $this->variables[array_shift($parts)] ?? null; foreach ($parts as $part) {$val = is_array($val) ? ($val[$part] ?? null) : (is_object($val) ? ($val->$part ?? null) : null);}echo $val ? "$2" : "$3";?>',

            // Вывод без экранирования
            '/\{!\$([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\}/' => '<?php echo $$1; ?>',
            '/\{!\$([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\.([a-zA-Z0-9_\x7f-\xff]+)\}/' => '<?php echo $$1[\'$2\']; ?>',

            // Комментарии
            '/\{\*.+?\*\}/s' => '',
        ];

        return preg_replace(array_keys($replacements), array_values($replacements), $content);
    }

    public function setTemplateDir($dir)
    {
        $this->templateDir = $dir;
        return $this;
    }

    public function getAvailableTemplates($templatesDir)
    {
        $availableTemplates = [];
        if (is_dir($templatesDir)) {
            $items = scandir($templatesDir);
            foreach ($items as $item) {
                $fullPath = $templatesDir . '/' . $item;
                if ($item != '.' && $item != '..' && is_dir($fullPath)) {
                    if (
                        file_exists($fullPath . '/footer.tpl') &&
                        file_exists($fullPath . '/header.tpl')
                    ) {
                        $availableTemplates[] = $item;
                    }
                }
            }
        }
        return $availableTemplates;
    }

    public function formatSize($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    public function changeTemplate($newTemplate, $availableTemplates, &$config, $configPath)
    {
        if (in_array($newTemplate, $availableTemplates)) {
            $config['templ'] = $newTemplate;
            $configContent = "<?php\nif (!defined('IN_SIMPLECMS')) { die('Прямой доступ запрещен'); }\nreturn " . var_export($config, true) . ";\n";

            if (file_put_contents($configPath, $configContent, LOCK_EX)) {
                if (function_exists('opcache_invalidate')) {
                    opcache_invalidate($configPath);
                }
                return true;
            }
        }
        return false;
    }

    public function renderTemplateString(string $template, array $data): string
    {
        $template = preg_replace_callback(
            '/\{l_([a-zA-Z0-9_]+)(?:\:([a-zA-Z0-9_]+))?\}/',
            function ($matches) {
                $key = $matches[1];
                $section = $matches[2] ?? 'main';
                return htmlspecialchars(Lang::get($key, $section), ENT_QUOTES, 'UTF-8');
            },
            $template
        );

        $template = preg_replace_callback(
            '/\{([a-zA-Z0-9_]+(?:\.[a-zA-Z0-9_]+)*)\}/',
            function ($matches) use ($data) {
                $keys = explode('.', $matches[1]);
                $value = $data;
                foreach ($keys as $key) {
                    if (is_array($value)) {
                        $value = $value[$key] ?? null;
                    } elseif (is_object($value)) {
                        $value = $value->$key ?? null;
                    } else {
                        return '';
                    }
                }
                return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
            },
            $template
        );

        $template = preg_replace_callback(
            '/\{!([a-zA-Z0-9_]+(?:\.[a-zA-Z0-9_]+)*)\}/',
            function ($matches) use ($data) {
                $keys = explode('.', $matches[1]);
                $value = $data;
                foreach ($keys as $key) {
                    if (is_array($value)) {
                        $value = $value[$key] ?? null;
                    } elseif (is_object($value)) {
                        $value = $value->$key ?? null;
                    } else {
                        return '';
                    }
                }
                return $value ?? '';
            },
            $template
        );

        return $template;
    }

    public function renderTemplateId(string $template, $data): string
    {
        $template = preg_replace_callback(
            '/\{l_([a-zA-Z0-9_]+)(?:\:([a-zA-Z0-9_]+))?\}/',
            function ($matches) {
                $key = $matches[1];
                $section = $matches[2] ?? 'main';
                return htmlspecialchars(Lang::get($key, $section), ENT_QUOTES, 'UTF-8');
            },
            $template
        );

        $template = preg_replace_callback(
            '/\{([a-zA-Z0-9_]+(?:\.[a-zA-Z0-9_]+)*)\}/',
            function ($matches) use ($data) {
                $keys = explode('.', $matches[1]);
                $value = $data;
                foreach ($keys as $key) {
                    if (is_array($value)) {
                        $value = $value[$key] ?? null;
                    } elseif (is_object($value)) {
                        $value = $value->$key ?? null;
                    } else {
                        return '';
                    }
                }
                return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
            },
            $template
        );

        $template = preg_replace_callback(
            '/\{!([a-zA-Z0-9_]+(?:\.[a-zA-Z0-9_]+)*)\}/',
            function ($matches) use ($data) {
                $keys = explode('.', $matches[1]);
                $value = $data;
                foreach ($keys as $key) {
                    if (is_array($value)) {
                        $value = $value[$key] ?? null;
                    } elseif (is_object($value)) {
                        $value = $value->$key ?? null;
                    } else {
                        return '';
                    }
                }
                return $value ?? '';
            },
            $template
        );

        return $template;
    }

    public function renderNewsList(array $newsItems, string $templateFile): string
    {
        global $votes;

        if ($newsItems === null || !is_array($newsItems)) {
            $newsItems = [];
        }

        if (!isset($this->templateCache[$templateFile])) {
            $path = $this->templateDir . '/' . ltrim($templateFile, '/');
            $this->templateCache[$templateFile] = file_get_contents($path);
        }

        $templateContent = $this->templateCache[$templateFile];

        if (empty($newsItems)) {
            return '<div class="w3-card-4 w3-margin w3-white"><div class="w3-container w3-padding"><h3><b>'
                . Lang::get('nonews') . '</b></h3></div></div><hr>';
        }

        $result = '';
        foreach ($newsItems as $item) {
            $item['article_rating'] = $votes->getArticleRating($item['id']);
            $item['hasVotedArticle'] = isset($_SESSION['user'])
                ? $votes->hasUserVotedForArticle($item['id'], $_SESSION['user']['id'])
                : false;
            $result .= $this->renderTemplateString($templateContent, $item);
        }

        return $result;
    }

    /**
     * Рендерит одну новость
     *
     * @param array|object $newsItem Данные новости
     * @param string $templateFile Шаблон для рендеринга
     * @return string
     */
    public function renderNewsItem($newsItem, string $templateFile): string
    {
        global $votes;

        if (!isset($this->templateCache[$templateFile])) {
            $path = $this->templateDir . '/' . ltrim($templateFile, '/');
            if (!file_exists($path)) {
                throw new \RuntimeException("Template file not found: $path");
            }
            $this->templateCache[$templateFile] = file_get_contents($path);
        }

        $newsItem['article_rating'] = $votes->getArticleRating($newsItem['id']);
        $newsItem['csrf_token'] = $_SESSION['csrf_token'];
        $newsItem['hasVotedArticle'] = isset($_SESSION['user'])
            ? $votes->hasUserVotedForArticle($newsItem['id'], $_SESSION['user']['id'])
            : false;

        return $this->renderTemplateId($this->templateCache[$templateFile], $newsItem);
    }

    /**
     * Обрабатывает массив комментариев для вывода
     */
    public function processComments(array $comments, string $templateFile): string
    {
        global $votes;
        $processed = '';

        if (!isset($this->templateCache[$templateFile])) {
            $path = $this->templateDir . '/' . ltrim($templateFile, '/');
            $this->templateCache[$templateFile] = file_get_contents($path);
        }

        $templateContent = $this->templateCache[$templateFile];

        foreach ($comments as $comment) {
            $data = [
                'id' => $comment['id'],
                'theme_id' => $comment['theme_id'] ?? '',
                'user_name' => $comment['user_name'] ?? '',
                'user_text' => $comment['user_text'] ?? '',
                'avatar' => !empty($comment['avatar']) ? $comment['avatar'] : 'images/avatar_g.png',
                'created_at' => $this->formatDate($comment['created_at'] ?? ''),
                'comm_rating' => $votes->getCommentRating($comment['id']),
                'voted' => isset($_SESSION['user'])
                    ? $votes->hasUserVoted($comment['id'], $_SESSION['user']['id'])
                    : false,
                'csrf_token' => $_SESSION['csrf_token'] ?? ''
            ];

            $processed .= $this->renderTemplateString($templateContent, $data);
        }

        return $processed;
    }

    protected function formatDate($date): string
    {
        try {
            if (empty($date)) {
                return 'дата неизвестна';
            }

            $timestamp = is_numeric($date) ? (int)$date : strtotime($date);

            if ($timestamp === false) {
                return 'неверная дата';
            }

            $now = time();
            $today = strtotime('today', $now);
            $yesterday = strtotime('yesterday', $now);

            if ($timestamp >= $today) {
                return 'сегодня в ' . date('H:i', $timestamp);
            }

            if ($timestamp >= $yesterday) {
                return 'вчера в ' . date('H:i', $timestamp);
            }

            if (class_exists('IntlDateFormatter')) {
                $formatter = new IntlDateFormatter(
                    'ru_RU',
                    IntlDateFormatter::LONG,
                    IntlDateFormatter::SHORT,
                    date_default_timezone_get(),
                    IntlDateFormatter::GREGORIAN,
                    'd MMMM yyyy, HH:mm'
                );
                return $formatter->format($timestamp);
            }

            return date('d.m.Y H:i', $timestamp);
        } catch (Exception $e) {
            error_log("Date formatting error: " . $e->getMessage());
            return 'ошибка даты';
        }
    }

    public function generateUrl($params = [], $absolute = false)
    {
        global $config;

        if ($config['pretty_urls'] ?? false) {
            if (isset($params['id'])) {
                return ($absolute ? $config['base_url'] : '') . '/news/' . $params['id'];
            } elseif (isset($params['tags'])) {
                return ($absolute ? $config['base_url'] : '') . '/tags/' . urlencode($params['tag']);
            } elseif (isset($params['search'])) {
                return ($absolute ? $config['base_url'] : '') . '/search/' . urlencode($params['search']);
            } elseif (isset($params['user'])) {
                return ($absolute ? $config['base_url'] : '') . '/user/' . $params['user'];
            }
        }

        // Стандартные URL
        return ($absolute ? $config['base_url'] : '') . '?' . http_build_query($params);
    }

    public function renderFooter(array $footerData)
    {
        return $this->render('footer.tpl', $footerData);
    }
}
