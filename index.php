<?php

/**
 * Главный контроллер приложения - точка входа
 * 
 * @package    SimpleBlog
 * @subpackage Core
 * @version    1.0.0
 * 
 * @router
 *  GET  /             - Главная страница блога
 *  GET  /?id=N        - Просмотр отдельной записи
 *  GET  /?action=X    - Специальные действия (авторизация/регистрация)
 *  POST /             - Обработка форм
 * 
 * @features
 * - Поддержка русского и английского языков
 * - SEO-оптимизированные маршруты
 * - Защита от CSRF-атак
 * - Система кэширования
 */
 
define('IN_SIMPLECMS', true);
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
$start = microtime(1);
$config = require 'config/config.php';

if (isset($config['session_lifetime'])) {
    ini_set('session.gc_maxlifetime', $config['session_lifetime']);
    ini_set('session.cookie_lifetime', $config['session_lifetime']);
}

session_start([
    'cookie_secure' => ($config['force_https'] ?? false) ? true : false,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax', 
    'use_strict_mode' => true,
    'use_trans_sid' => false,
    'cookie_lifetime' => $config['session_lifetime'] ?? 7200,
    'gc_maxlifetime' => $config['session_lifetime'] ?? 7200,
    'sid_length' => 128,
    'sid_bits_per_character' => 6 
]);

$regenerateTime = 300;
if (!isset($_SESSION['created'])) {
    $_SESSION['created'] = time();
    session_regenerate_id(true);
} elseif (time() - $_SESSION['created'] > $regenerateTime) {
    session_regenerate_id(true);
    $_SESSION['created'] = time();
}
if (!file_exists('config/config.php')) {
	header('Location: install.php');
	die;
}
require 'class/ErrorHandler.php';;
ErrorHandler::init($config['debug'] ?? false);
require 'class/Lang.php';
Lang::init();
if (file_exists('install.php')) {
	echo '<p style="color: red; text-align: center;">' . Lang::get('delete_install', 'core') . '</p>';
	die;
}
if ($config['pretty_urls'] ?? false) {
    // Парсим URL для определения запрашиваемой страницы
    $requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $requestUri = trim($requestUri, '/');
    // Обработка различных маршрутов
    if (preg_match('#^news/([a-z0-9-]+)-([0-9]+)$#', $requestUri, $matches)) {
        $_GET['id'] = $matches[2]; // Берем только ID из slug
    } elseif (preg_match('#^tag/([^/]+)$#', $requestUri, $matches)) {
        $_GET['tag'] = urldecode($matches[1]);
    } elseif (preg_match('#^search/([^/]+)$#', $requestUri, $matches)) {
        $_GET['search'] = urldecode($matches[1]);
    } elseif (preg_match('#^user/(\d+)$#', $requestUri, $matches)) {
        $_GET['user'] = $matches[1];
    }
}
if (isset($_GET['lang'])) {
	$lang = in_array($_GET['lang'], ['ru', 'en']) ? $_GET['lang'] : 'ru';
    Lang::setLanguage($lang);
	$_SESSION['lang'] = $lang;
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}
if (!empty($_SESSION['lang'])) {
    Lang::setLanguage($_SESSION['lang']);
}
if (!ob_start("ob_gzhandler")) {
    ob_start();
}
$host = $config['host'];
$database = $config['database'];
$db_user = $config['db_user'];
$db_pass = $config['db_pass'];
$pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8", $db_user, $db_pass);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("SET NAMES utf8mb4");
$templ = $config['templ'];
$dbPrefix = $config['db_prefix'];
$backupDir = $config['backup_dir'];
$maxBackups = $config['max_backups'];
$version = $config['version'];
// Проверка необходимости создания резервной копии по расписанию
if (isset($config['backup_schedule']) && $config['backup_schedule'] !== 'disabled') {
    require_once __DIR__ . '/admin/backup_db.php';
    checkScheduledBackup($pdo, $config);
}
require 'class/Cache.php';
Cache::init($config);
require 'class/User.php';
require 'class/Contact.php';
require 'class/Comments.php';
require 'class/News.php';
require 'class/Vote.php';
require 'class/ImageUploader.php';
require 'class/Parse.php';
require 'class/Mailer.php';
require 'class/Pagination.php';
require 'class/Core.php';
require 'class/FooterDataProvider.php';
require 'class/Template.php';
// Инициализация объектов
$votes = new Votes($pdo);
$contact = new Contact($pdo);
$news = new News($pdo);
$comments = new Comments($pdo);
$parse = new parse();
$template = new Template();
$imageUploader = new imageUploader($config['upload_dir'], $config['maxSize']);
$user = new User($pdo, $template);
$baseTitle = $config['home_title'];
$pageTitle = $baseTitle;
$core = new Core($pdo, $config, $template);
$tags = $news->getAllTags();
// Обработка POST-запросов
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $core->handlePostRequest();
}
// Генерация CSRF-токена
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
// Обработка GET-запросов
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
if (!isset($_GET['action'])) {
    if (isset($_GET['id'])) {
		// Получаем одну новость по id
		$newsId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, [
			'options' => ['min_range' => 1]
		]);
		if ($newsId === false) {
			throw new InvalidArgumentException("Invalid news ID");
		}
		$userPrefix = isset($_SESSION['user_id']) ? 'user_' . $_SESSION['user_id'] : 'guest';
		$cacheKey = $userPrefix . '_news_page_'. $newsId .'_' . ($_SESSION['lang'] ?? 'ru');
		if ($_SERVER['REQUEST_METHOD'] === 'GET' && Cache::has($cacheKey)) {
			echo Cache::get($cacheKey);
			exit;
		}
		$newsItem = $news->getNewsByIdCached($newsId); // Получаем одну новость
		if (!$newsItem) {
			header($_SERVER["SERVER_PROTOCOL"] . " 404 Not Found");
			$pageTitle = Lang::get('error404', 'core') . ' | ' . $baseTitle;
			$metaDescription = $news->generateMetaDescription('', 'error404');
			$metaKeywords = $news->generateMetaKeywords('', 'error404');
			$commonVars = $template->getCommonTemplateVars($config, $news, $_SESSION['user'] ?? null);
			$pageVars = [
				'pageTitle' => Lang::get('error404', 'core'),
				'metaDescription' => $metaDescription,
				'metaKeywords' => $metaKeywords,
			];
			$template->assignMultiple(array_merge($commonVars, $pageVars));
			echo $template->render('404.tpl');
			exit;
		}
		// Обрабатываем контент
		$newsItem['content'] = $parse->userblocks($newsItem['content'], $config, $_SESSION['user'] ?? null);
		$newsItem['content'] = $parse->truncateHTML($newsItem['content'], 100000);
		$pageTitle = htmlspecialchars($newsItem['title']) . ' | ' . $baseTitle;
		$metaDescription = $news->generateMetaDescription($newsItem['content'], 'article', [
			'title' => $newsItem['title']
		]);
		$metaKeywords = $news->generateMetaKeywords($newsItem['content'], 'article', [
			'title' => $newsItem['title']
		]);
		$totalComments = $comments->countComments($newsId);
		$currentCommentPage = (int)($_GET['comment_page'] ?? 1);
		$pagination = Pagination::calculate(
			$totalComments,
			Pagination::TYPE_COMMENTS,
			$config,
			$currentCommentPage
		);
		$commentsList = $comments->getComments(
			$newsId,
			$pagination['per_page'],
			$pagination['offset']
		);
		$paginationHtml = Pagination::render(
			$pagination, 
			"?id=$newsId", 
			'comment_page'
		);
		$articleRating = $votes->getArticleRating($newsId);
		// Передаем данные в шаблон
		$commonVars = $template->getCommonTemplateVars($config, $news, $_SESSION['user'] ?? null);
		$pageVars = [
		'pageTitle' => $pageTitle,
		'metaDescription' => $metaDescription,
		'metaKeywords' => $metaKeywords,
		'csrf_token' => $_SESSION['csrf_token'],
		];
		$footerProvider = new FooterDataProvider($news, $user, $template, $config);
		// Получаем данные для футера
		$footerData = $footerProvider->prepareFooterData();
		$template->assignMultiple(array_merge($commonVars, $pageVars, $footerData));
		$template->assign('news.id', $newsId);
		$template->assign('pagination', new class($paginationHtml) {
			private $html;
			public function __construct($html) { $this->html = $html; }
			public function __toString() { return $this->html; }
		});
		$output = $template->render('header.tpl');
		$output .= $template->renderNewsItem($newsItem, 'news.tpl');
		$output .= $template->processComments($commentsList, 'comment.tpl');
		$output .= $template->render('add_comment.tpl');
		//$output .= $template->render('footer.tpl');
		$output .= $template->renderFooter($footerData);
		Cache::set($cacheKey, $output, $config['cache_ttl'] ?? 3600);
		echo $output;
	} elseif (isset($_GET['tags'])) {
		// Обработка тегов
		$tag = htmlspecialchars($_GET['tags']);
		$userPrefix = isset($_SESSION['user_id']) ? 'user_' . $_SESSION['user_id'] : 'guest';
		$cacheKey = $userPrefix . '_tags_page_' . $tag . '_' . ($_SESSION['lang'] ?? 'ru');
		if ($_SERVER['REQUEST_METHOD'] === 'GET' && Cache::has($cacheKey)) {
			echo Cache::get($cacheKey);
			exit;
		}
		$pageTitle = Lang::get('tag_news', 'core') . $tag . ' | ' . $baseTitle;
		$metaDescription = $news->generateMetaDescription('', 'tag', [
			'tag' => $tag
		]);
		$metaKeywords = $news->generateMetaKeywords('', 'tag', [
			'tag' => $tag
		]);
		$countNewsByTags = $news->getNewsCountByTag($tag);
		$page = (int)($_GET['page'] ?? 1);
		$pagination = Pagination::calculate(
			$countNewsByTags, 
			Pagination::TYPE_NEWS, 
			$config,
			$page
			
		);
		$newsByTags = $news->getNewsByTag($tag, $pagination['per_page'], $pagination['offset']);
		foreach ($newsByTags as &$item) {
			if (isset($item['content'])) {
				$item['content'] = $parse->userblocks(
					$item['content'],
					$config,
					$_SESSION['user'] ?? null  
				);
				$item['content'] = $parse->truncateHTML($item['content']);
				$item['news_url'] = $template->generateUrl(['id' => $item['id']]);
			}
		}
		unset($item);
		$paginationHtml = Pagination::render($pagination, $config['pretty_urls'] ? '/tags/' . urlencode($tag) : '?tags=' . urlencode($tag) . "&page=");
		$lastThreeNews = $news->getLastThreeNews();
		$commonVars = $template->getCommonTemplateVars($config, $news, $_SESSION['user'] ?? null);
		$pageVars = [
			'pageTitle' => $pageTitle,
			'metaDescription' => $metaDescription,
			'metaKeywords' => $metaKeywords,
			'votes' => $votes,
			];
		if (empty($newsByTags)) {
			$template->assign('no_news_message', 'Нет новостей');
		}
		$footerProvider = new FooterDataProvider($news, $user, $template, $config);
		$footerData = $footerProvider->prepareFooterData();
		$template->assignMultiple(array_merge($commonVars, $pageVars, $footerData));
		$template->assign('pagination', new class($paginationHtml) {
			private $html;
			public function __construct($html) { $this->html = $html; }
			public function __toString() { return $this->html; }
		});
		$output = $template->render('header.tpl');
		$output .= $template->renderNewsList($newsByTags, 'news_item.tpl');
		$output .= $template->renderFooter($footerData);
		Cache::set($cacheKey, $output, $config['cache_ttl'] ?? 3600);
		echo $output;
	} else {
		// Загрузка главной страницы
		$userPrefix = isset($_SESSION['user_id']) ? 'user_' . $_SESSION['user_id'] : 'guest';
		$cacheKey = $userPrefix . '_homepage_' . ($_SESSION['lang'] ?? 'ru') . '_page_' . (isset($_GET['page']) ? (int)$_GET['page'] : 1);
		if (Cache::has($cacheKey) && ($_SERVER['REQUEST_METHOD'] === 'GET')) {
			echo Cache::get($cacheKey);
			exit;
		}
		$pageTitle = Lang::get('home_page', 'main') . ' | ' . $baseTitle;
		$totalNewsCount = $news->getTotalNewsCount();
		$page = (int)($_GET['page'] ?? 1);
		$pagination = Pagination::calculate(
			$totalNewsCount, 
			Pagination::TYPE_NEWS, 
			$config,
			$page
		);
		$allNews = $news->getAllNewsCached(
			$pagination['per_page'], 
			$pagination['offset']
		);
		$baseUrl = $config['pretty_urls'] ? '/' : '/index.php';
		$paginationHtml = Pagination::render($pagination, $baseUrl);
		foreach ($allNews as &$item) {
			if (isset($item['content'])) {
				$item['content'] = $parse->userblocks(
					$item['content'],
					$config,
					$_SESSION['user'] ?? null  
				);
				$item['content'] = $parse->truncateHTML($item['content']);
				$item['news_url'] = $template->generateUrl(['id' => $item['id']]);
			}
		}
		unset($item);
		$lastThreeNews = $news->getLastThreeNews();
		$commonVars = $template->getCommonTemplateVars($config, $news, $_SESSION['user'] ?? null);
		$pageVars = [
			'pageTitle' => $pageTitle,
			'metaDescription' => $config['metaDescription'],
			'metaKeywords' => $config['metaKeywords'],
			'news' => $allNews,
			'votes' => $votes,
		];
		$footerProvider = new FooterDataProvider($news, $user, $template, $config);
		$footerData = $footerProvider->prepareFooterData();
		$template->assignMultiple(array_merge($commonVars, $pageVars, $footerData));
		$template->assign('pagination', new class($paginationHtml) {
			private $html;
			public function __construct($html) { $this->html = $html; }
			public function __toString() { return $this->html; }
		});
		$output = $template->render('header.tpl');
		$output .= $template->renderNewsList($allNews, 'news_item.tpl');
		$output .= $template->renderFooter($footerData);
		Cache::set($cacheKey, $output, $config['cache_ttl'] ?? 3600);
		echo $output;
	}
} else {
	switch ($_GET['action']) {
		case 'verify_email':
			if (isset($_GET['token'])) {
				if ($user->verifyEmail($_GET['token'])) {
					flash(Lang::get('email_verified', 'core'), 'success');
				} else {
					flash(Lang::get('invalid_token', 'core'), 'error');
				}
				header('Location: /');
				exit;
			}
			break;

		case 'forgot_password':
		case 'request_reset':
		case 'reset_password':
		case 'search':
		case 'login':
		case 'register':
		case 'contact':
		case 'profile':
			// Общие переменные для всех страниц
			$action = $_GET['action'];
			$pageTitle = Lang::get($action, 'core') . ' | ' . $baseTitle;

			// Генерация мета-тегов в зависимости от действия
			$metaDescription = $news->generateMetaDescription('', $action, [
				'query' => $searchQuery ?? '',
				'tag' => $tag ?? '',
				'title' => $newsItem['title'] ?? ''
			]);

			$metaKeywords = $news->generateMetaKeywords('', $action, [
				'query' => $searchQuery ?? '',
				'tag' => $tag ?? '',
				'title' => $newsItem['title'] ?? ''
			]);

			$commonVars = $template->getCommonTemplateVars($config, $news, $_SESSION['user'] ?? null);
			$pageVars = [
				'pageTitle' => $pageTitle,
				'metaDescription' => $metaDescription,
				'metaKeywords' => $metaKeywords,
				'csrf_token' => $_SESSION['csrf_token'],
				'flash' => $_SESSION['flash'] ?? null, 
			];

			$footerProvider = new FooterDataProvider($news, $user, $template, $config);
			$footerData = $footerProvider->prepareFooterData();

			// Специфические данные для каждого действия
            $searchResults = [];

			switch ($action) {
				case 'forgot_password':
					// Никаких дополнительных данных не нужно
					break;

				case 'request_reset':
					if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
						if ($user->sendPasswordReset($_POST['email'])) {
							$_SESSION['flash'] = Lang::get('reset_email_sent', 'core');
						} else {
							$_SESSION['flash'] = Lang::get('reset_email_error', 'core');
						}
						header('Location: /');
						exit;
					}
					break;

				case 'reset_password':
					if (isset($_GET['token'])) {
						$pageVars['token'] = $_GET['token'];
					}

					if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['token'], $_POST['password'])) {
						if ($user->resetPassword($_POST['token'], $_POST['password'])) {
							$_SESSION['flash'] = Lang::get('password_reset_success', 'core');
						} else {
							$_SESSION['flash'] = Lang::get('password_reset_error', 'core');
						}
						header('Location: /');
						exit;
					}
					break;

				case 'search':
					if (!empty($searchQuery)) {
						$totalResults = $news->countSearchResults($searchQuery);
						$page = (int)($_GET['page'] ?? 1);
						$pagination = Pagination::calculate(
							$totalResults, 
							Pagination::TYPE_NEWS, 
							$config,
							$page
						);
						$searchResults = $news->searchNews($searchQuery, $pagination['per_page'], $pagination['offset']);

						foreach ($searchResults as &$item) {
							if (isset($item['content'])) {
								$item['content'] = $parse->userblocks(
									$item['content'],
									$config,
									$_SESSION['user'] ?? null
								);
								$item['content'] = $parse->truncateHTML($item['content']);
							}
						}
						unset($item);

						$paginationHtml = Pagination::render(
							$pagination, 
							"?action=search&search=" . urlencode($searchQuery)
						);

						$pageVars['searchResults'] = $searchResults;
						$pageVars['searchQuery'] = $searchQuery;
						$pageVars['pagination'] = new class($paginationHtml) {
							private $html;
							public function __construct($html) { $this->html = $html; }
							public function __toString() { return $this->html; }
						};
					}
					break;

				case 'profile':
					if (!isset($_SESSION['user']['id'])) {
						$_SESSION['flash'] = Lang::get('auth_required', 'core');
						header('Location: /?action=login');
						exit;
					}

					// Обработка привязки соцсетей
					if (isset($_GET['link_social'])) {
						$socialType = $_GET['link_social'];
						$core->handleSocialAuth($socialType);
						flash(Lang::get('social_link_started', 'core') . ': ' . $socialType, 'info');
						exit;
					}

					if (isset($_GET['unlink_social'])) {
						if ($user->removeSocialLink($_SESSION['user']['id'], $_GET['unlink_social'])) {
							flash(Lang::get('social_unlinked', 'core'), 'success');
						} else {
							flash(Lang::get('social_unlink_error', 'core'), 'error');
						}
						header('Location: /?action=profile');
						exit;
					}

					// Получаем данные для профиля
					$userData = $user->getUserById($_SESSION['user']['id']);
					$userNews = $news->getNewsByAuthor($_SESSION['user']['id'], 5);
					$userComments = $comments->getCommentsByUser($_SESSION['user']['id'], 5);
					$socialLinks = $user->getSocialLinks($_SESSION['user']['id']);

					if (!isset($userData['avatar']) || empty($userData['avatar'])) {
						$userData['avatar'] = 'images/avatar_g.png';
					}

					$pageVars['userData'] = $userData;
					$pageVars['userNews'] = $userNews;
					$pageVars['userComments'] = $userComments;
					$pageVars['socialLinks'] = $socialLinks;
					$pageVars['supportedSocials'] = ['telegram', 'github', 'google'];
					break;
			}

			// Общий рендеринг для всех страниц
			$template->assignMultiple(array_merge($commonVars, $pageVars, $footerData));

			$output = $template->render('header.tpl');
			if ($action == 'search') {
				$output .= $template->renderNewsList($searchResults, 'search.tpl');
			} else {
			    $output .= $template->render($action . '.tpl');
			}
			$output .= $template->renderFooter($footerData);
			echo $output;
			break;

		case 'oauth_callback':
			$this->handleOAuthCallback();
			exit;

		default:
			// Обработка 404 ошибки
			header($_SERVER["SERVER_PROTOCOL"] . Lang::get('error404', 'core'));
			$pageTitle = Lang::get('error404', 'core') . ' | ' . $baseTitle;
			$metaDescription = $news->generateMetaDescription('', 'error404');
			$metaKeywords = $news->generateMetaKeywords('', 'error404');
			$commonVars = $template->getCommonTemplateVars($config, $news, $_SESSION['user'] ?? null);
			$pageVars = [
				'pageTitle' => $pageTitle,
				'metaDescription' => $metaDescription,
				'metaKeywords' => $metaKeywords,
			];
			$footerProvider = new FooterDataProvider($news, $user, $template, $config);
			$footerData = $footerProvider->prepareFooterData();
			$template->assignMultiple(array_merge($commonVars, $pageVars));
			$output = $template->render('404.tpl');
			echo $output;
			exit;
	}
}
$finish = microtime(1);
//echo 'generation time: ' . round($finish - $start, 5) . ' сек';
