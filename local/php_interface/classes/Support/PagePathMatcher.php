<?php

declare(strict_types=1);

namespace Project\Support;

use InvalidArgumentException;

/**
 * Сопоставляет URL-каталог с одним или несколькими каталогами сайта.
 *
 * Сравнение выполняется по границам сегментов пути: /catalog не совпадает
 * с /catalog-old. Класс не обращается к глобальным переменным и Bitrix.
 */
final class PagePathMatcher
{
    /**
     * Проверяет, находится ли текущий путь в указанном каталоге или ниже него.
     *
     * @param string|array<int, string> $directories Один каталог или список каталогов.
     */
    public static function inDirectory(string $currentPath, string|array $directories): bool
    {
        $current = self::normalize($currentPath);
        if ($current === null) {
            return false;
        }

        foreach (self::directories($directories) as $directory) {
            $base = self::normalize($directory);
            if ($base === null) {
                continue;
            }

            if ($base === '/') {
                return true;
            }

            if ($current === $base || str_starts_with($current, $base . '/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Проверяет принадлежность корневому разделу сайта.
     *
     * В отличие от inDirectory(), учитывается только первый сегмент пути:
     * /catalog/item/ подходит для catalog, а /shop/catalog/ — нет.
     *
     * @param string|array<int, string> $sections Имя или список корневых разделов.
     */
    public static function inRootDirectory(string $currentPath, string|array $sections): bool
    {
        $current = self::normalize($currentPath);
        if ($current === null || $current === '/') {
            return false;
        }

        $firstSegment = explode('/', trim($current, '/'), 2)[0];

        foreach (self::directories($sections) as $section) {
            $normalized = self::normalize($section);
            if ($normalized === null || $normalized === '/') {
                continue;
            }

            $sectionSegment = explode('/', trim($normalized, '/'), 2)[0];
            if (strcasecmp($firstSegment, $sectionSegment) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Приводит путь к виду /segment/subsegment без завершающего слеша.
     */
    private static function normalize(string $path): ?string
    {
        $path = trim($path);
        if ($path === '' || str_contains($path, '?') || str_contains($path, '#')) {
            return null;
        }

        if ($path[0] !== '/') {
            $path = '/' . $path;
        }

        $path = preg_replace('~/+~', '/', $path) ?? $path;
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        return $path;
    }

    /**
     * @param string|array<int, string> $values
     * @return list<string>
     */
    private static function directories(string|array $values): array
    {
        $values = is_array($values) ? $values : [$values];

        foreach ($values as $value) {
            if (!is_string($value)) {
                throw new InvalidArgumentException('Пути разделов должны быть строками.');
            }
        }

        return array_values($values);
    }
}
