<?php

declare(strict_types=1);

namespace Project\Support;

/**
 * Экранирует текст для безопасной вставки в текст HTML или значение атрибута в кавычках.
 *
 * Это кодирование при выводе, а не замена проверке данных с учётом контекста.
 */
final class HtmlEscaper
{
    public function escape(string $value, int $flags = ENT_QUOTES | ENT_SUBSTITUTE, string $encoding = 'UTF-8'): string
    {
        return htmlspecialchars(
            $value,
            $flags,
            $encoding
        );
    }
}
