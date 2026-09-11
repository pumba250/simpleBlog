<?php

if (!defined('IN_SIMPLECMS')) {
    die('Прямой доступ запрещен');
}
/**
 * Класс для работы с пагинацией
 *
 * @package    SimpleBlog
 * @subpackage Core
 * @category   Utilities
 * @version    1.0.0
 *
 * @method static array  calculate(int $totalItems, string $type, int $currentPage = 1, array $config) Рассчитывает параметры пагинации
 * @method static string render(array $paginationData, string $baseUrl, string $pageParam = 'page')    Генерирует HTML-код пагинации
 */
class Pagination
{
    const TYPE_NEWS = 'news';
    const TYPE_COMMENTS = 'comments';
    /**
     * Рассчитывает данные пагинации
     *
     * @param int $totalItems Общее количество элементов
     * @param int $perPage Количество элементов на странице
     * @param int $currentPage Текущая страница
     * @return array Массив с данными пагинации
     */
    public static function calculate(int $totalItems, string $type, array $config, int $currentPage = 1): array
    {
        // Определяем количество элементов на странице из конфига
        switch ($type) {
            case self::TYPE_NEWS:
                $perPage = (int)($config['blogs_per_page'] ?? 6);
                break;
            case self::TYPE_COMMENTS:
                $perPage = (int)($config['comments_per_page'] ?? 3);
                break;
            default:
                $perPage = 10;
        }

        $totalPages = (int)max(1, ceil($totalItems / $perPage));
        $currentPage = (int)max(1, min($currentPage, $totalPages));

        return [
            'total_items' => $totalItems,
            'per_page' => $perPage,
            'current_page' => $currentPage,
            'total_pages' => $totalPages,
            'offset' => ($currentPage - 1) * $perPage
        ];
    }

    /**
     * Генерирует HTML пагинации
     *
     * @param array $paginationData Данные пагинации из метода calculate()
     * @param string $baseUrl Базовый URL для ссылок
     * @param string $pageParam Имя параметра страницы в URL
     * @return string HTML код пагинации
     */
    public static function render(array $paginationData, string $baseUrl, string $pageParam = 'page'): string
    {
        $currentPage = $paginationData['current_page'];
        $totalPages = $paginationData['total_pages'];

        if ($totalPages <= 1) {
            return '';
        }

        // Параметры URL
        $urlParts = parse_url($baseUrl);
        $path = $urlParts['path'] ?? '';
        parse_str($urlParts['query'] ?? '', $queryParams);

        // Удаляем дублирующиеся параметры
        if (isset($queryParams[$pageParam])) {
            unset($queryParams[$pageParam]);
        }

        $links = [];

        // Кнопка "Назад"
        if ($currentPage > 1) {
            $queryParams[$pageParam] = $currentPage - 1;
            $prevUrl = $path . (!empty($queryParams) ? '?' . http_build_query($queryParams) : '');
            $links[] = sprintf(
                '<a href="%s" class="pagination-link pagination-prev">%s</a>',
                htmlspecialchars($prevUrl),
                Lang::get('prev')
            );
        } else {
            $links[] = sprintf(
                '<span class="pagination-link pagination-prev pagination-disabled">%s</span>',
                Lang::get('prev')
            );
        }

        // Номера страниц
        $start = max(1, $currentPage - 2);
        $end = min($totalPages, $currentPage + 2);

        for ($i = $start; $i <= $end; $i++) {
            $queryParams[$pageParam] = $i;
            $pageUrl = $path . (!empty($queryParams) ? '?' . http_build_query($queryParams) : '');

            if ($i == $currentPage) {
                $links[] = sprintf(
                    '<span class="pagination-link pagination-page pagination-current">%d</span>',
                    $i
                );
            } else {
                $links[] = sprintf(
                    '<a href="%s" class="pagination-link pagination-page">%d</a>',
                    htmlspecialchars($pageUrl),
                    $i
                );
            }
        }

        // Кнопка "Вперед"
        if ($currentPage < $totalPages) {
            $queryParams[$pageParam] = $currentPage + 1;
            $nextUrl = $path . (!empty($queryParams) ? '?' . http_build_query($queryParams) : '');
            $links[] = sprintf(
                '<a href="%s" class="pagination-link pagination-next">%s</a>',
                htmlspecialchars($nextUrl),
                Lang::get('next')
            );
        } else {
            $links[] = sprintf(
                '<span class="pagination-link pagination-next pagination-disabled">%s</span>',
                Lang::get('next')
            );
        }

        return '<div class="pagination">' . implode('', $links) . '</div>';
    }
}
