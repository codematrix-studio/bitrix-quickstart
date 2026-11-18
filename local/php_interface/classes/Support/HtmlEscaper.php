<?php

declare(strict_types=1);

namespace Project\Support;

/**
 * Escapes text for safe insertion into an HTML text or quoted-attribute context.
 *
 * This is output encoding, not a replacement for context-specific validation.
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
