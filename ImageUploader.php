<?php

/**
 * Класс для безопасной загрузки и обработки изображений
 *
 * Обеспечивает проверку MIME-типов, размеров файлов, генерацию безопасных имен
 * и валидацию изображений средствами GD
 *
 * @package    SimpleBlog
 * @category   FileUpload
 * @version    0.9.8
 *
 * @method void   __construct(string $uploadDir, int $maxFileSize = 1048576, array|null $allowedMimeTypes = null) Инициализирует загрузчик
 * @method string upload(array $file, string $fileName)                              Безопасно загружает и проверяет изображение
 * @method bool   isValidImage(string $filePath)                                     Проверяет, является ли файл действительным изображением
 * @method string generateSafeFileName(string $baseName, string $extension)          Генерирует безопасное имя файла
 * @method string getUploadError(int $errorCode)                                     Возвращает описание ошибки загрузки
 * @method string formatBytes(int $bytes, int $precision = 2)                        Форматирует байты в читаемый вид
 * @method static void removeOldAvatar(string $oldAvatarPath)                        Удаляет старый аватар
 */

if (!defined('IN_SIMPLECMS')) {
    die('Прямой доступ запрещен');
}

class ImageUploader
{
    private $allowedMimeTypes;
    private $maxFileSize;
    private $uploadDir;

    public function __construct($uploadDir, $maxFileSize = 1048576, $allowedMimeTypes = null)
    {
        $this->uploadDir = rtrim($uploadDir, '/') . '/';
        $this->maxFileSize = $maxFileSize;

        // По умолчанию разрешаем основные форматы изображений
        $this->allowedMimeTypes = $allowedMimeTypes ?? [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp'
        ];
    }

    /**
     * Безопасно загружает и проверяет изображение
     *
     * @param array $file Элемент из $_FILES
     * @param string $fileName Желаемое имя файла (без расширения)
     * @return string|null Путь к сохраненному файлу относительно корня сайта или null при ошибке
     */
    public function upload($file, $fileName)
    {
        // 1. Проверка базовых ошибок загрузки
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Ошибка загрузки файла: ' . $this->getUploadError($file['error']));
        }

        // 2. Проверка размера файла
        if ($file['size'] > $this->maxFileSize) {
            throw new RuntimeException('Размер файла превышает допустимый лимит (' . $this->formatBytes($this->maxFileSize) . ')');
        }

        // 3. Проверка MIME-типа с помощью finfo (САМАЯ ВАЖНАЯ ПРОВЕРКА)
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $detectedMimeType = $finfo->file($file['tmp_name']);

        if (!array_key_exists($detectedMimeType, $this->allowedMimeTypes)) {
            throw new RuntimeException('Недопустимый тип файла. Разрешены только: ' . implode(', ', array_values($this->allowedMimeTypes)));
        }

        // 4. Получаем безопасное расширение из нашего белого списка
        $safeExtension = $this->allowedMimeTypes[$detectedMimeType];

        // 5. Генерируем безопасное имя файла
        $safeFileName = $this->generateSafeFileName($fileName, $safeExtension);

        // 6. Проверяем, является ли файл действительным изображением (опционально, но рекомендуется)
        if (!$this->isValidImage($file['tmp_name'])) {
            throw new RuntimeException('Файл не является корректным изображением');
        }

        // 7. Создаем директорию, если её нет
        if (!is_dir($this->uploadDir)) {
            if (!mkdir($this->uploadDir, 0755, true)) {
                throw new RuntimeException('Не удалось создать директорию для загрузки');
            }
        }

        $targetPath = $this->uploadDir . $safeFileName;

        // 8. Перемещаем файл
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new RuntimeException('Не удалось сохранить файл');
        }

        // 9. Возвращаем путь относительно корня сайта для сохранения в БД
        return $targetPath;
    }

    /**
     * Проверяет, является ли файл действительным изображением
     * @param string $filePath
     * @return bool
     */
    private function isValidImage($filePath)
	{
		$allowedTypes = [
			IMAGETYPE_JPEG => 'image/jpeg',
			IMAGETYPE_PNG => 'image/png',
			IMAGETYPE_GIF => 'image/gif'
		];
		
		$imageInfo = @getimagesize($filePath);
		if ($imageInfo === false) {
			return false;
		}
		
		$detectedType = $imageInfo[2];
		if (!isset($allowedTypes[$detectedType])) {
			return false;
		}
		
		// Дополнительная проверка содержимого
		$image = @imagecreatefromstring(file_get_contents($filePath));
		if ($image === false) {
			return false;
		}
		imagedestroy($image);
		
		return true;
	}

    /**
     * Генерирует безопасное имя файла
     * @param string $baseName
     * @param string $extension
     * @return string
     */
    private function generateSafeFileName($baseName, $extension)
    {
        // Очищаем базовое имя от небезопасных символов
        $safeBaseName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $baseName);
        $safeBaseName = substr($safeBaseName, 0, 50); // Ограничиваем длину

        return $safeBaseName . '_' . time() . '.' . $extension;
    }

    private function getUploadError($errorCode)
    {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'Размер файла превышает значение upload_max_filesize в php.ini',
            UPLOAD_ERR_FORM_SIZE => 'Размер файла превышает значение MAX_FILE_SIZE из формы',
            UPLOAD_ERR_PARTIAL => 'Файл был загружен только частично',
            UPLOAD_ERR_NO_FILE => 'Файл не был загружен',
            UPLOAD_ERR_NO_TMP_DIR => 'Отсутствует временная папка',
            UPLOAD_ERR_CANT_WRITE => 'Не удалось записать файл на диск',
            UPLOAD_ERR_EXTENSION => 'Расширение PHP остановило загрузку файла',
        ];

        return $errors[$errorCode] ?? 'Неизвестная ошибка';
    }

    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * Удаляет старый аватар, если он существует и не является дефолтным
     * @param string $oldAvatarPath
     */
    public static function removeOldAvatar($oldAvatarPath)
    {
        // Не удаляем дефолтные аватары
        $defaultAvatars = ['images/avatar_g.png', 'images/avatar_m.png'];

        if (!empty($oldAvatarPath) && !in_array($oldAvatarPath, $defaultAvatars) && file_exists($oldAvatarPath)) {
            @unlink($oldAvatarPath);
        }
    }
}
