<?php

declare(strict_types=1);

namespace Project\Bitrix\IBlock;

use Project\Support\HtmlEscaper;

/** Безопасное чтение значения свойства из стандартного массива элемента/раздела. */
final class PropertyAccessor
{
    public static function isEmpty(string $code, array $data = [], string $propertiesKey = 'PROPERTIES'): bool
    {
        return empty($data[$propertiesKey][$code]['VALUE']);
    }

    public static function isFilled(string $code, array $data = [], string $propertiesKey = 'PROPERTIES'): bool
    {
        return !self::isEmpty($code, $data, $propertiesKey);
    }

    public static function get(string $code, array $data = [], string $propertiesKey = 'PROPERTIES'): array
    {
        $property = $data[$propertiesKey][$code] ?? null;

        return is_array($property) ? $property : [];
    }

    public static function getValue(string $code, array $data = [], string $propertiesKey = 'PROPERTIES')
    {
        return $data[$propertiesKey][$code]['VALUE'] ?? null;
    }

    public static function getEscapedValue(
        string $code,
        array $data = [],
        string $propertiesKey = 'PROPERTIES',
        ?HtmlEscaper $escaper = null
    ): ?string {
        $value = self::getValue($code, $data, $propertiesKey);

        if (empty($value) || !is_scalar($value)) {
            return null;
        }

        return ($escaper ?? new HtmlEscaper())->escape((string) $value);
    }
}
