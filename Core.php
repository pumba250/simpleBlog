<?php

if (!defined('IN_SIMPLECMS')) {
    die('Прямой доступ запрещен');
}
/**
 * Основной класс для обработки запросов
 *
 * @package    SimpleBlog
 * @subpackage Core
 * @category   System
 * @version    1.0.0
 *
 * @method void __construct(PDO $pdo, array $config, Template $template) Инициализирует зависимости
 * @method void handlePostRequest() Обрабатывает все POST-запросы (основной публичный метод)
 * @method void handleCommentPost() Обрабатывает отправку комментария (приватный)
 * @method void handleVotePost() Обрабатывает голосования (приватный)
 * @method void handleRegistration() Обрабатывает регистрацию пользователя (приватный)
 * @method void handleLogin() Обрабатывает авторизацию (приватный)
 * @method void handleContact() Обрабатывает форму обратной связи (приватный)
 * @method void handlePasswordResetRequest() Обрабатывает запрос сброса пароля (приватный)
 * @method void handlePasswordReset() Обрабатывает сброс пароля (приватный)
 * @method void handleProfileUpdate() Обрабатывает обновление профиля (приватный)
 * @method void handleAdminPostRequest() Обрабатывает POST-запросы админки (приватный)
 */
class Core
{
    private $pdo;
    private $config;
    private $user;
    private $news;
    private $comments;
    private $votes;
    private $contact;
    private $parse;
    private $template;
    private $dbPrefix;

    public function __construct($pdo, $config, $template)
    {
        $this->pdo = $pdo;
        $this->config = $config;
        $this->template = $template;
        $this->dbPrefix = $config['db_prefix'] ?? '';

        // Инициализация зависимостей
        $this->user = new User($pdo, $template);
        $this->votes = new Votes($pdo);
        $this->contact = new Contact($pdo);
        $this->news = new News($pdo);
        $this->comments = new Comments($pdo);
        $this->parse = new Parse();
    }

    /**
     * Обработка POST-запросов
     */
    public function handlePostRequest()
    {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            die(Lang::get('invalid_csrf', 'core'));
        }

		// Проверяем, является ли запрос админским
        if (strpos($_SERVER['REQUEST_URI'], 'admin.php') !== false || isset($_GET['admin'])) {
            $this->handleAdminPostRequest();
            return;
        }

        // Обработка комментариев
        if (isset($_POST['user_text'])) {
            $this->handleCommentPost();
            return;
        }

        // Обработка голосований
        if (isset($_POST['vote_article']) || isset($_POST['vote_comment'])) {
            $this->handleVotePost();
            return;
        }

        // Обработка обновления профиля
        if (isset($_POST['update_profile'])) {
            $this->handleProfileUpdate();
            return;
        }

        // Обработка действий
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'register':
                    $this->handleRegistration();
                    break;
                case 'login':
                    $this->handleLogin();
                    break;
                case 'contact':
                    $this->handleContact();
                    break;
                case 'request_reset':
                    $this->handlePasswordResetRequest();
                    break;
                case 'reset_password':
                    $this->handlePasswordReset();
                    break;
            }
        }
    }

    /**
     * Обработка POST-запросов админки
     */
    private function handleAdminPostRequest()
    {
		/*if (isset($_POST['action']) && $_POST['action'] === 'restore_backup') {
			$_SESSION['admin_error'] = 'Восстановление временно недоступно. Используйте phpMyAdmin или командную строку.';
			return;
		}*/
        if (!isset($_SESSION['user']) || !$_SESSION['user']['isadmin']) {
            header('Location: /');
            exit;
        }

        $currentUserRole = $_SESSION['user']['isadmin'] ?? 0;

        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'edit_comment':
                    if (!$this->user->hasPermission(7, $currentUserRole)) {
                        $_SESSION['admin_error'] = Lang::get('not_perm', 'core');
                        logAction('Попытка редактирования комментария', "Редактирование комментария запрещено");
                    } else {
                        $id = (int)$_POST['id'];
                        $userText = htmlspecialchars(trim($_POST['user_text']));
                        $moderation = isset($_POST['moderation']) ? 1 : 0;
                        
                        if ($this->comments->editComment($id, $userText)) {
                            // Обновляем статус модерации отдельно
                            if ($moderation) {
                                $this->comments->approveComment($id);
                                logAction('Редактирование комментария', "Комментарий ID $id отредактирован и одобрен");
                            } else {
                                $stmt = $this->pdo->prepare("UPDATE `{$this->dbPrefix}comments` SET moderation = 0 WHERE id = ?");
                                $stmt->execute([$id]);
                                logAction('Редактирование комментария', "Комментарий ID $id отредактирован и снят с публикации");
                            }
                            $_SESSION['admin_message'] = Lang::get('edit_comm', 'core');
                        } else {
                            logAction('Ошибка редактирования комментария', "Не удалось отредактировать комментарий ID $id");
                            $_SESSION['admin_error'] = Lang::get('edit_comm', 'core');
                        }
                    }
                    break;
                    
                case 'delete_comment':
                    if (!$this->user->hasPermission(9, $currentUserRole)) {
                        $_SESSION['admin_error'] = Lang::get('not_perm', 'core');
                        logAction('Попытка удаления комментария', "Удаление комментария запрещено");
                    } else {
                        $id = (int)$_POST['id'];
                        if ($this->comments->deleteComment($id)) {
                            logAction('Удаление комментария', "Комментарий ID $id удален");
                            $_SESSION['admin_message'] = Lang::get('comm_del', 'core');
                        } else {
                            logAction('Ошибка удаления комментария', "Не удалось удалить комментарий ID $id");
                            $_SESSION['admin_error'] = Lang::get('comm_del_errno', 'core');
                        }
                    }
                    break;
                    
                case 'toggle_comment':
                    if (!$this->user->hasPermission(7, $currentUserRole)) {
                        $_SESSION['admin_error'] = Lang::get('not_perm', 'core');
                        logAction('Попытка изменения статуса комментария', "Изменение статуса запрещено");
                    } else {
                        $id = (int)$_POST['id'];
                        if ($this->comments->toggleModeration($id)) {
                            $status = $this->comments->getCommentStatus($id);
                            logAction('Изменение статуса комментария', "Комментарий ID $id: " . ($status ? 'Одобрен' : 'На модерации'));
                            $_SESSION['admin_message'] = Lang::get('comm_stat', 'core') . ($status ? Lang::get('comm_stat_app', 'core') : Lang::get('comm_stat_mod', 'core'));
                        } else {
                            logAction('Ошибка изменения статуса комментария', "Не удалось изменить статус комментария ID $id");
                            $_SESSION['admin_error'] = Lang::get('comm_stat_err', 'core');
                        }
                    }
                    break;
                    
                case 'edit_settings':
                    if (!$this->user->hasPermission(9, $currentUserRole)) {
                        $_SESSION['admin_error'] = Lang::get('not_perm', 'core');
                        logAction('Попытка изменения системных настроек', "Изменение запрещено");
                    } else {
                        // Обновляем конфигурацию
						$this->config['home_title'] = trim($_POST['home_title']);
        
						// Общие настройки
						$this->config['blogs_per_page'] = (int)trim($_POST['blogs_per_page']);
						$this->config['comments_per_page'] = (int)trim($_POST['comments_per_page']);
						$this->config['blocks_for_reg'] = isset($_POST['blocks_for_reg']) ? true : false;
						
						// SEO настройки
						$this->config['metaKeywords'] = trim($_POST['metaKeywords']);
						$this->config['metaDescription'] = trim($_POST['metaDescription']);
						
						// Email настройки
						$this->config['mail_from'] = trim($_POST['mail_from']);
						$this->config['mail_from_name'] = trim($_POST['mail_from_name']);
						
						// Настройки производительности
						$this->config['cache_enabled'] = isset($_POST['cache_enabled']) ? true : false;
						$this->config['cache_driver'] = trim($_POST['cache_driver']);
						$this->config['cache_ttl'] = (int)trim($_POST['cache_ttl']);
						$this->config['cache_key_salt'] = trim($_POST['cache_key_salt']);
						
						// Redis настройки (только если выбран redis)
						$this->config['redis_host'] = trim($_POST['redis_host'] ?? '127.0.0.1');
						$this->config['redis_port'] = (int)trim($_POST['redis_port'] ?? 6379);
						$this->config['redis_password'] = trim($_POST['redis_password'] ?? '');
						
						// Memcached настройки (только если выбран memcached)
						$this->config['memcached_host'] = trim($_POST['memcached_host'] ?? '127.0.0.1');
						$this->config['memcached_port'] = (int)trim($_POST['memcached_port'] ?? 11211);
						
						// Настройки безопасности (ДОБАВЬТЕ ЭТИ СТРОКИ)
						$this->config['session_lifetime'] = (int)trim($_POST['session_lifetime'] ?? 7200);
						$this->config['force_https'] = isset($_POST['force_https']) ? true : false;
                        
                        // Сохраняем обновленный конфиг
                        file_put_contents(__DIR__ . '/../config/config.php', "<?php\nif (!defined('IN_SIMPLECMS')) { die('Прямой доступ запрещен');}\nreturn " . var_export($this->config, true) . ";\n");
                        
                        $_SESSION['admin_message'] = Lang::get('save_setting', 'core');
                        logAction('Изменение системных настроек', 'Обновлены основные настройки системы');
                    }
                    break;
                    
                case 'update_blog':
                    if (!$this->user->hasPermission(7, $currentUserRole)) {
                        $_SESSION['admin_error'] = Lang::get('not_perm', 'core');
                        logAction('Попытка редактирования записи блога', "Редактирование запрещено");
                    } else {
                        $id = (int)$_POST['id'];
                        $title = htmlspecialchars(trim($_POST['title']));
                        $content = nl2br(trim($_POST['content']));
                        $tags = isset($_POST['tags']) ? $_POST['tags'] : [];

                        if ($this->news->updateBlog($id, $title, $content, $tags)) {
                            logAction('Редактирование записи блога', "Запись ID $id отредактирована. Новый заголовок: $title");
                            $_SESSION['admin_message'] = Lang::get('blog_upd', 'core');
                        } else {
                            logAction('Ошибка редактирования записи блога', "Не удалось отредактировать запись ID $id");
                            $_SESSION['admin_error'] = Lang::get('blog_err', 'core');
                        }
                    }
                    break;
                    
                case 'delete_blog':
                    if (!$this->user->hasPermission(9, $currentUserRole)) {
                        $_SESSION['admin_error'] = Lang::get('not_perm', 'core');
                        logAction('Попытка удаления записи блога', "Удаление запрещено");
                    } else {
                        $id = (int)$_POST['id'];
                        if ($this->news->deleteBlog($id)) {
                            logAction('Удаление записи блога', "Запись ID $id удалена");
                            $_SESSION['admin_message'] = Lang::get('blog_del', 'core');
                        } else {
                            logAction('Ошибка удаления записи блога', "Не удалось удалить запись ID $id");
                            $_SESSION['admin_error'] = Lang::get('blog_del_err', 'core');
                        }
                    }
                    break;
                    
                case 'add_blog':
                    if (!$this->user->hasPermission(7, $currentUserRole)) {
                        $_SESSION['admin_error'] = Lang::get('not_perm', 'core');
                        logAction('Попытка добавления записи блога', "Добавление запрещено");
                    } else {
                        $title = htmlspecialchars(trim($_POST['title']));
                        $content = nl2br(trim($_POST['content']));
                        $tags = isset($_POST['tags']) ? $_POST['tags'] : [];
                        
                        if ($this->news->addBlog($title, $content, $tags, $_SESSION['user']['id'] ?? null)) {
                            logAction('Добавление записи блога', "Добавлена новая запись: $title");
                            $_SESSION['admin_message'] = Lang::get('blog_add', 'core');
                        } else {
                            logAction('Ошибка добавления записи блога', "Не удалось добавить запись: $title");
                            $_SESSION['admin_error'] = Lang::get('blog_add_err', 'core');
                        }
                    }
                    break;
                    
                case 'delete_contact':
                    if (!$this->user->hasPermission(9, $currentUserRole)) {
                        $_SESSION['admin_error'] = Lang::get('not_perm', 'core');
                        logAction('Попытка удаления сообщения', "Удаление запрещено");
                    } else {
                        $id = (int)$_POST['id'];
                        if ($this->contact->deleteMessage($id)) {
                            logAction('Удаление сообщения', "Сообщение ID $id удалено");
                            $_SESSION['admin_message'] = Lang::get('cont_del', 'core');
                        } else {
                            logAction('Ошибка удаления сообщения', "Не удалось удалить сообщение ID $id");
                            $_SESSION['admin_error'] = Lang::get('cont_err', 'core');
                        }
                    }
                    break;
                    
                case 'edit_user':
                    if (!$this->user->hasPermission(9, $currentUserRole)) {
                        $_SESSION['admin_error'] = Lang::get('not_perm', 'core');
                        logAction('Попытка редактирования пользователя', "Редактирование запрещено");
                    } else {
                        $id = (int)$_POST['id'];
                        $username = htmlspecialchars(trim($_POST['username']));
                        $email = htmlspecialchars(trim($_POST['email']));
                        $isadmin = (int)$_POST['isadmin'];
                        
                        if ($this->user->updateUser($id, $username, $email, $isadmin)) {
                            $roleName = $this->getRoleName($isadmin);
                            logAction('Редактирование пользователя', "ID: $id, Новые данные: $username, $email, Роль: $roleName");
                            $_SESSION['admin_message'] = Lang::get('user_upd', 'core');
                        } else {
                            logAction('Ошибка редактирования пользователя', "Не удалось обновить пользователя ID $id");
                            $_SESSION['admin_error'] = Lang::get('user_upd_err', 'core');
                        }
                    }
                    break;
                    
                case 'delete_user':
                    if (!$this->user->hasPermission(9, $currentUserRole)) {
                        $_SESSION['admin_error'] = Lang::get('not_perm', 'core');
                        logAction('Попытка удаления пользователя', "Удаление запрещено");
                    } else {
                        $id = (int)$_POST['id'];
                        if ($this->user->deleteUser($id)) {
                            logAction('Удаление пользователя', "Пользователь ID $id удален");
                            $_SESSION['admin_message'] = Lang::get('user_del', 'core');
                        } else {
                            logAction('Ошибка удаления пользователя', "Не удалось удалить пользователя ID $id");
                            $_SESSION['admin_error'] = Lang::get('user_del_err', 'core');
                        }
                    }
                    break;
                    
                case 'add_tag':
                    if (!$this->user->hasPermission(7, $currentUserRole)) {
                        $_SESSION['admin_error'] = Lang::get('not_perm', 'core');
                        logAction('Попытка добавления тега', "Добавление запрещено");
                    } else {
                        $name = htmlspecialchars(trim($_POST['name']));
                        if ($this->news->addTag($name)) {
                            logAction('Добавление тега', "Добавлен новый тег: $name");
                            $_SESSION['admin_message'] = Lang::get('tag_add', 'core');
                        } else {
                            logAction('Ошибка добавления тега', "Не удалось добавить тег: $name");
                            $_SESSION['admin_error'] = Lang::get('tag_err', 'core');
                        }
                    }
                    break;
                    
                case 'delete_tag':
                    if (!$this->user->hasPermission(9, $currentUserRole)) {
                        $_SESSION['admin_error'] = Lang::get('not_perm', 'core');
                        logAction('Попытка удаления тега', "Удаление запрещено");
                    } else {
                        $id = (int)$_POST['id'];
                        if ($this->news->deleteTag($id)) {
                            logAction('Удаление тега', "Тег ID $id удален");
                            $_SESSION['admin_message'] = Lang::get('tag_del', 'core');
                        } else {
                            logAction('Ошибка удаления тега', "Не удалось удалить тег ID $id");
                            $_SESSION['admin_error'] = Lang::get('tag_del_err', 'core');
                        }
                    }
                    break;
                    
                case 'change_template':
                    if (!$this->user->hasPermission(9, $currentUserRole)) {
                        $_SESSION['admin_error'] = Lang::get('not_perm', 'core');
                        logAction('Попытка смены шаблона', "Смена шаблона запрещена");
                    } else {
                        $templatesDir = 'templates';
                        $configPath = 'config/config.php';
                        $availableTemplates = $this->template->getAvailableTemplates($templatesDir);
                        $newTemplate = $_POST['template'] ?? '';
                        
                        if ($this->template->changeTemplate($newTemplate, $availableTemplates, $this->config, $configPath)) {
                            $_SESSION['admin_message'] = Lang::get('templ_active', 'core');
                        } else {
                            $_SESSION['admin_error'] = Lang::get('templ_err', 'core');
                        }
                    }
                    break;
                    
                case 'delete_log':
                    if (!$this->user->hasPermission(9, $currentUserRole)) {
                        $_SESSION['admin_error'] = Lang::get('not_perm', 'core');
                        logAction('Попытка удаления лога', "Удаление лога запрещено");
                    } else {
                        $id = (int)$_POST['id'];
                        
                        $stmt = $this->pdo->prepare("DELETE FROM `{$this->dbPrefix}admin_logs` WHERE id = ?");
                        if ($stmt->execute([$id])) {
                            $_SESSION['admin_message'] = Lang::get('log_del', 'core');
                        } else {
                            $_SESSION['admin_error'] = Lang::get('log_del_err', 'core');
                        }
                    }
                    break;
                    
                case 'create_backup':
                    if (!$this->user->hasPermission(9, $currentUserRole)) {
                        $_SESSION['admin_error'] = Lang::get('not_perm', 'core');
                    } else {
                        $backupDir = $this->config['backup_dir'];
                        $backup = 'backup_'.date('Y-m-d_H-i-s').'.sql';
                        $backupFile = dbBackup(__DIR__.'/../'. $backupDir . $backup, false);
                        if ($backupFile) {
                            $_SESSION['admin_message'] = Lang::get('backup', 'core') . basename($backup);
                            logAction('Создание резервной копии', 'Файл: ' . basename($backup));
                        } else {
                            $_SESSION['admin_error'] = Lang::get('backup_err', 'core');
                        }
                    }
                    break;

				case 'restore_backup':
					if (!$this->user->hasPermission(9, $currentUserRole)) {
						$_SESSION['admin_error'] = Lang::get('not_perm', 'core');
					} else {
						$filename = basename($_POST['file']);
						$backupDir = $this->config['backup_dir'];
						
						// Валидация имени файла
						if (!preg_match('/^backup_[a-zA-Z0-9_\-\.]+\.sql$/i', $filename)) {
							$_SESSION['admin_error'] = 'Недопустимое имя файла бэкапа';
							break;
						}
						
						$file = realpath(__DIR__ . '/../' . $backupDir . $filename);
						
						if (!$file || !file_exists($file)) {
							$_SESSION['admin_error'] = Lang::get('not_file', 'core');
							break;
						}
						
						// Проверка размера файла
						if (filesize($file) > 50 * 1024 * 1024) { // 50MB максимум
							$_SESSION['admin_error'] = 'Файл бэкапа слишком большой (макс. 50MB)';
							break;
						}
						
						try {
							// Отключаем внешние ключи
							$this->pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
							$this->pdo->exec("SET NAMES utf8");
							
							// Начинаем транзакцию
							$this->pdo->beginTransaction();
							
							// Читаем файл построчно
							$handle = fopen($file, 'r');
							if ($handle) {
								$currentQuery = '';
								
								while (($line = fgets($handle)) !== false) {
									// Пропускаем комментарии
									if (strpos(trim($line), '#') === 0 || strpos(trim($line), '--') === 0) {
										continue;
									}
									
									$currentQuery .= $line;
									
									// Если строка заканчивается точкой с запятой - выполняем запрос
									if (strpos($line, ';') !== false) {
										$currentQuery = trim($currentQuery);
										
										if (!empty($currentQuery)) {
											// Для DROP TABLE IF EXISTS - сначала удаляем внешние ключи
											if (strpos($currentQuery, 'DROP TABLE IF EXISTS') === 0) {
												// Извлекаем имя таблицы
												preg_match('/DROP TABLE IF EXISTS `([^`]+)`/', $currentQuery, $matches);
												if (isset($matches[1])) {
													$tableName = $matches[1];
													
													// Удаляем внешние ключи для этой таблицы
													$fkStmt = $this->pdo->prepare("
														SELECT CONSTRAINT_NAME 
														FROM information_schema.KEY_COLUMN_USAGE 
														WHERE TABLE_SCHEMA = DATABASE() 
														AND TABLE_NAME = ?
														AND REFERENCED_TABLE_NAME IS NOT NULL
													");
													$fkStmt->execute([$tableName]);
													$foreignKeys = $fkStmt->fetchAll(PDO::FETCH_COLUMN);
													
													foreach ($foreignKeys as $fkName) {
														try {
															$this->pdo->exec("ALTER TABLE `{$tableName}` DROP FOREIGN KEY `{$fkName}`");
														} catch (Exception $e) {
															// Игнорируем ошибки, если внешнего ключа уже нет
															error_log("Could not drop FK {$fkName}: " . $e->getMessage());
														}
													}
												}
											}
											
											// Для CREATE TABLE - если таблица существует, сначала удаляем ее
											if (strpos($currentQuery, 'CREATE TABLE') === 0) {
												preg_match('/CREATE TABLE `([^`]+)`/', $currentQuery, $matches);
												if (isset($matches[1])) {
													$tableName = $matches[1];
													
													// Проверяем, существует ли таблица
													$checkStmt = $this->pdo->prepare("SHOW TABLES LIKE ?");
													$checkStmt->execute([$tableName]);
													
													if ($checkStmt->fetch()) {
														// Таблица существует, удаляем ее
														try {
															$this->pdo->exec("DROP TABLE `{$tableName}`");
														} catch (Exception $e) {
															error_log("Could not drop table {$tableName}: " . $e->getMessage());
														}
													}
												}
											}
											
											try {
												$stmt = $this->pdo->prepare($currentQuery);
												$stmt->execute();
											} catch (Exception $e) {
												// Пропускаем ошибки "таблица уже существует"
												if (strpos($e->getMessage(), 'already exists') === false) {
													throw $e;
												}
											}
											
											$currentQuery = '';
										}
									}
								}
								
								fclose($handle);
							}
							
							// Включаем внешние ключи
							$this->pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
							
							// Фиксируем транзакцию
							$this->pdo->commit();
							
							$_SESSION['admin_message'] = Lang::get('backup_rest', 'core');
							logAction('Восстановление БД', 'Из файла: ' . basename($filename));
							
						} catch (Exception $e) {
							// Откатываем транзакцию в случае ошибки
							if ($this->pdo->inTransaction()) {
								$this->pdo->rollBack();
							}
							// Включаем внешние ключи обратно
							$this->pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
							
							$_SESSION['admin_error'] = 'Ошибка восстановления: ' . $e->getMessage();
							error_log('Backup restore error: ' . $e->getMessage());
						}
					}
					break;
				
                case 'delete_backup':
                    if (!$this->user->hasPermission(9, $currentUserRole)) {
                        $_SESSION['admin_error'] = Lang::get('not_perm', 'core');
                    } else {
                        $backupDir = $this->config['backup_dir'];
                        $file = __DIR__ . '/../'. $backupDir . basename($_POST['file']);
                        
                        $realPath = realpath($file);
                        $allowedPath = realpath(__DIR__ . '/../' . $backupDir);
                        
                        if ($realPath === false || strpos($realPath, $allowedPath) !== 0) {
                            $_SESSION['admin_error'] = Lang::get('invalid_file_path', 'core');
                        } elseif (file_exists($realPath)) {
                            if (unlink($realPath)) {
                                $_SESSION['admin_message'] = Lang::get('backup_del', 'core');
                                logAction('Удаление резервной копии', 'Файл: ' . basename($realPath));
                            } else {
                                $_SESSION['admin_error'] = Lang::get('backup_del_failed', 'core');
                            }
                        } else {
                            $_SESSION['admin_error'] = Lang::get('not_file', 'core');
                        }
                    }
                    break;

                case 'update_backup_settings':
                    if (!$this->user->hasPermission(9, $currentUserRole)) {
                        $_SESSION['admin_error'] = Lang::get('not_perm', 'core');
                    } else {
                        $this->config['max_backups'] = (int)$_POST['max_backups'];
                        $this->config['backup_schedule'] = $_POST['backup_schedule'];
                        
                        file_put_contents(__DIR__ . '/../config/config.php', "<?php\nif (!defined('IN_SIMPLECMS')) { die('Прямой доступ запрещен');}\nreturn " . var_export($this->config, true) . ";\n");
                        
                        $_SESSION['admin_message'] = Lang::get('backup_upd', 'core');
                        logAction('Изменение настроек бэкапа', 'Макс. копий: ' . $this->config['max_backups'] . ', Расписание: ' . $this->config['backup_schedule']);
                    }
                    break;
                    
                case 'install_update':
                    if (!$this->user->hasPermission(9, $currentUserRole)) {
                        $_SESSION['admin_error'] = Lang::get('not_perm', 'core');
                        logAction('Попытка установки обновления', "Недостаточно прав");
                    } else {
                        $updater = new Updater($this->pdo, $this->config);
                        $updateInfo = $updater->checkForUpdates();
                        
                        if ($updater->performUpdate()) {
                            $_SESSION['admin_message'] = 'Обновление успешно установлено!';
                            logAction('Установка обновления', "Установлена версия: " . $updateInfo['new_version']);
                        } else {
                            $_SESSION['admin_error'] = "Не удалось выполнить обновление";
                        }
                    }
                    break;
                    
                case 'update_captcha_settings':
                    if (!$this->user->hasPermission(9, $currentUserRole)) {
                        $_SESSION['admin_error'] = Lang::get('not_perm', 'core');
                    } else {
                        $this->config['captcha_bg_color'] = trim($_POST['bg_color'] ?? '10,10,26');
						$this->config['captcha_text_color'] = trim($_POST['text_color'] ?? '11,227,255');
						$this->config['captcha_accent_color'] = trim($_POST['accent_color'] ?? '188,19,254');
						$this->config['captcha_noise_color'] = trim($_POST['noise_color'] ?? '50,50,80');
                        
                        file_put_contents(__DIR__ . '/../config/config.php', "<?php\nif (!defined('IN_SIMPLECMS')) { die('Прямой доступ запрещен');}\nreturn " . var_export($this->config, true) . ";\n");
                        
                        $_SESSION['admin_message'] = 'Настройки капчи обновлены';
                    }
                    break;
            }
        }
    }

    /**
     * Получение названия роли по значению
     */
    private function getRoleName($roleValue)
    {
        switch((int)$roleValue) {
            case 9: return Lang::get('admin', 'admin');
            case 7: return Lang::get('moder', 'admin');
            case 0: return Lang::get('ruser', 'admin');
            default: return Lang::get('unknownrole', 'admin');
        }
    }

    /**
     * Обработка отправки комментария
     */
    private function handleCommentPost()
    {
        $themeId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $userName = isset($_SESSION['user']['username']) ? $_SESSION['user']['username'] : $_POST['user_name'];
        $userText = $_POST['user_text'];

        if ($themeId > 0 && !empty($userName) && !empty($userText)) {
            $this->comments->addComment(0, 0, $themeId, $userName, $userText);
            header("Location: ?id=" . $themeId);
            exit;
        }
    }

    /**
     * Обработка голосования
     */
    private function handleVotePost()
    {
        $newsId = isset($_POST['id'])
            ? (int)$_POST['id']
            : (isset($_GET['id']) ? (int)$_GET['id'] : 0);

        if ($newsId === 0) {
            die(Lang::get('not_id', 'core'));
        }

        $newsItem = $this->news->getNewsById($newsId);
        if (!$newsItem) {
            die(Lang::get('not_news', 'core'));
        }

        if (isset($_POST['vote_article']) && isset($_SESSION['user']['id'])) {
            $voteType = $_POST['vote_article'];
            $this->votes->voteArticle($newsId, $voteType, $_SESSION['user']['id']);
            header("Location: ?id=$newsId");
            exit;
        }

        if (isset($_POST['vote_comment']) && isset($_SESSION['user']['id'])) {
            list($commentId, $voteType) = explode('_', $_POST['vote_comment']);
            $this->votes->voteComment($commentId, $voteType, $_SESSION['user']['id']);
            header("Location: ?id=$newsId");
            exit;
        }
    }

    /**
     * Обработка регистрации
     */
    private function handleRegistration()
    {
        $this->user->register($_POST['username'], $_POST['password'], $_POST['email']);
        header("Location: /");
        exit;
    }

    /**
     * Обработка авторизации
     */
    private function handleLogin()
    {
        $captchaValid = isset($_POST['captcha']) && $_POST['captcha'] == $_SESSION['captcha_answer'];
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        if (!$captchaValid) {
            $_SESSION['auth_error'] = Lang::get('not_answer', 'core');
        } elseif (empty($username) || empty($password)) {
            $_SESSION['auth_error'] = Lang::get('all_field', 'core');
        } else {
            $userData = $this->user->login($username, $password);
            if ($userData) {
                $_SESSION['user'] = $userData;
                session_regenerate_id(true);
                $_SESSION['last_activity'] = time();
                $_SESSION['ip'] = $_SERVER['REMOTE_ADDR'];
                sleep(1);
                header("Location: /");
                exit;
            } else {
                $_SESSION['auth_error'] = Lang::get('not_login', 'core');
            }
        }

        $_SESSION['auth_form_data'] = [
            'username' => $username
        ];

        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }

    /**
     * Обработка формы обратной связи
     */
    private function handleContact()
    {
        if (isset($_POST['captcha']) && $_POST['captcha'] == $_SESSION['captcha_answer']) {
            if ($this->contact->saveMessage($_POST['name'], $_POST['email'], $_POST['message'])) {
                flash(Lang::get('msg_send', 'core'), 'success');
                header('Location: ?action=contact&status=send');
                exit();
            } else {
                flash(Lang::get('msg_error', 'core'), 'error');
                header('Location: ?action=contact&status=errno');
                exit();
            }
        } else {
            flash(Lang::get('not_answer', 'core'), 'error');
            header('Location: ?action=contact&status=errno');
            exit();
        }
    }

    /**
     * Обработка запроса на сброс пароля
     */
    private function handlePasswordResetRequest()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
            if ($this->user->sendPasswordReset($_POST['email'])) {
                flash(Lang::get('reset_email_sent', 'core'), 'success');
            } else {
                flash(Lang::get('reset_email_error', 'core'), 'error');
            }
            header('Location: /');
            exit;
        }
    }

    /**
     * Обработка сброса пароля
     */
    private function handlePasswordReset()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['token'], $_POST['password'])) {
            if ($this->user->resetPassword($_POST['token'], $_POST['password'])) {
                flash(Lang::get('password_reset_success', 'core'), 'success');
            } else {
                flash(Lang::get('password_reset_error', 'core'), 'error');
            }
            header('Location: /');
            exit;
        }
    }

    /**
     * Обработка обновления профиля
     */
    private function handleProfileUpdate()
    {
        if (!isset($_SESSION['user']['id'])) {
            flash(Lang::get('auth_required', 'core'), 'error');
            header('Location: /?action=login');
            exit;
        }

        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $currentPassword = trim($_POST['current_password'] ?? '');

        // Обработка загрузки аватара
        $avatar = null;
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            try {
                // Используем upload_dir из конфига
                $uploadDir = $this->config['upload_dir'] . 'avatars/';
                $maxSize = 2 * 1024 * 1024; // 2MB
                $imageUploader = new ImageUploader($uploadDir, $maxSize);

                // Загружаем изображение. Базовое имя = 'user_' + ID пользователя
                $baseFileName = 'user_' . $_SESSION['user']['id'];
                $newAvatarPath = $imageUploader->upload($_FILES['avatar'], $baseFileName);

                // Если загрузка успешна, удаляем старый аватар и сохраняем путь к новому
                ImageUploader::removeOldAvatar($_SESSION['user']['avatar'] ?? null);
                $avatar = $newAvatarPath;
            } catch (RuntimeException $e) {
                // Ловим и обрабатываем ошибки загрузки
                flash(Lang::get('avatar_upload_error', 'core') . ': ' . $e->getMessage(), 'error');
                header('Location: /?action=profile');
                exit;
            }
        }

        // Проверяем, изменился ли email
        $emailChanged = ($email !== $_SESSION['user']['email']);

        // Обновляем профиль
        $updateResult = $this->user->updateProfile(
            $_SESSION['user']['id'],
            $username,
            $email,
            $currentPassword,
            $avatar,
            $emailChanged
        );

        if ($updateResult['success']) {
            // Обновляем данные в сессии
            $_SESSION['user']['username'] = $username;
            $_SESSION['user']['email'] = $email;
            if ($avatar) {
                $_SESSION['user']['avatar'] = $avatar;
            }

            flash($updateResult['message'], 'success');
        } else {
            flash($updateResult['message'], 'error');
        }

        header('Location: /?action=profile');
        exit;
    }
}
