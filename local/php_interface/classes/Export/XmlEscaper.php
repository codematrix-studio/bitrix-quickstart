<?php

declare(strict_types=1);

namespace Project\Export;

use InvalidArgumentException;

/**
 * Экранирование XML 1.0.
 *
 * Удаляет недопустимые для XML 1.0 управляющие символы и заменяет
 * некорректные UTF-8 последовательности до HTML-совместимого XML escaping.
 */
final class XmlEscaper
{
    public static function text($value): string
    {
        return self::escape($value);
    }

    public static function attribute($value): string
    {
        return self::escape($value);
    }

    private static function escape($value): string
    {
        if ($value === null) {
            return '';
        }

        if (!is_scalar($value) && !$value instanceof \Stringable) {
            throw new InvalidArgumentException('Для XML-экранирования ожидается скалярное значение.');
        }

        // Normalize malformed UTF-8 before applying the XML 1.0 character filter.
        // ENT_SUBSTITUTE replaces invalid byte sequences without discarding valid text.
        $escaped = htmlspecialchars(
            (string) $value,
            ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );

        $validXml = preg_replace(
            '/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u',
            '',
            $escaped
        );

        return $validXml ?? '';
    }
}
