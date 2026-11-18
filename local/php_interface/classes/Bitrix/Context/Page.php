<?php

declare(strict_types=1);

namespace Project\Bitrix\Context;

use Project\Support\PagePathMatcher;
use RuntimeException;

/**
 * Проверка принадлежности текущей страницы каталогу или корневому разделу.
 *
 * Использует getRequestedPageDirectory() HTTP-запроса Bitrix.
 */
final class Page
{
    /**
     * Проверяет текущий URL-каталог и его вложенные страницы.
     *
     * @param string|array<int, string> $directories
     */
    public static function inDirectory(string|array $directories): bool
    {
        return PagePathMatcher::inDirectory(self::currentDirectory(), $directories);
    }

    /**
     * Проверяет только корневой раздел сайта.
     *
     * @param string|array<int, string> $sections
     */
    public static function inRootDirectory(string|array $sections): bool
    {
        return PagePathMatcher::inRootDirectory(self::currentDirectory(), $sections);
    }

    private static function currentDirectory(): string
    {
        $request = Request::get();

        if (!is_object($request) || !is_callable([$request, 'getRequestedPageDirectory'])) {
            throw new RuntimeException('HTTP-запрос Bitrix не предоставляет getRequestedPageDirectory().');
        }

        return (string) $request->getRequestedPageDirectory();
    }
}
