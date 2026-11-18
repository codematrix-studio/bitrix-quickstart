<?php

declare(strict_types=1);

namespace Project\Support;

/** Преобразование строк между UTF-8 и Windows-1251. */
final class CharsetConverter
{
    public static function toWindows1251(string $value, bool $ignoreInvalid = false)
    {
        $suffix = $ignoreInvalid ? '//IGNORE' : '';

        return iconv('UTF-8', 'Windows-1251' . $suffix, $value);
    }

    public static function fromWindows1251(string $value, bool $ignoreInvalid = false)
    {
        $suffix = $ignoreInvalid ? '//IGNORE' : '';

        return iconv('Windows-1251', 'UTF-8' . $suffix, $value);
    }
}
