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
    public function escape(string $value): string
    {
        return htmlspecialchars(
            $value,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }
}
