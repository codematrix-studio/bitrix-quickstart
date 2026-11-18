<?php

declare(strict_types=1);

namespace Lema\Common;

use Project\Bitrix\Context\Page as BitrixPage;

/**
 * Переходный адаптер старого Lema\Common\Page.
 *
 * Старые методы inDir() и inRootDir() сохранены. Для нового кода используйте
 * Project\Bitrix\Context\Page.
 */
final class Page
{
    /**
     * @param string|array<int, string> $data
     */
    public static function inDir($data): bool
    {
        return BitrixPage::inDirectory(self::validate($data));
    }

    /**
     * @param string|array<int, string> $section
     */
    public static function inRootDir($section): bool
    {
        return BitrixPage::inRootDirectory(self::validate($section));
    }

    private static function validate($value): string|array
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                if (!is_string($item)) {
                    throw new \InvalidArgumentException('Пути разделов должны быть строками.');
                }
            }

            return $value;
        }

        throw new \InvalidArgumentException('Ожидается строка или массив строк с путями разделов.');
    }
}
