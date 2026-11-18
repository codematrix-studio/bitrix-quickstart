<?php

declare(strict_types=1);

namespace Project\Support;

use InvalidArgumentException;
use RuntimeException;

/**
 * Converts HTML or plain text into a short, readable plain-text preview.
 */
final class TextPreview
{
    private const DEFAULT_MAX_LENGTH = 300;

    /**
     * Creates a plain-text preview, cutting at a word boundary when possible.
     *
     * The maximum length is measured in Unicode grapheme clusters.
     */
    public function create(string $text, int $maxLength = self::DEFAULT_MAX_LENGTH): string
    {
        if ($maxLength < 1) {
            throw new InvalidArgumentException('The maximum preview length must be greater than zero.');
        }

        // Preserve word separation when removing common block-level HTML tags.
        $text = preg_replace(
            '/<(?:br|hr)\b[^>]*>|<\/(?:p|div|li|h[1-6]|blockquote|section|article|tr)\s*>/iu',
            ' ',
            $text
        );

        if ($text === null) {
            throw new InvalidArgumentException('The input contains invalid UTF-8 data.');
        }

        $text = html_entity_decode(
            strip_tags($text),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );

        $text = preg_replace('/[\p{Z}\s]+/u', ' ', trim($text));

        if ($text === null) {
            throw new InvalidArgumentException('The input contains invalid UTF-8 data.');
        }

        if ($text === '') {
            return '';
        }

        if (preg_match_all('/\X/u', $text, $matches) === false) {
            throw new InvalidArgumentException('The input contains invalid UTF-8 data.');
        }

        $characters = $matches[0];

        if (count($characters) <= $maxLength) {
            return $text;
        }

        $preview = implode('', array_slice($characters, 0, $maxLength));

        // Avoid leaving a partial final word when a word boundary is available.
        if (preg_match('/^(.*)\s+\S*$/us', $preview, $wordBoundary) === 1) {
            $preview = rtrim($wordBoundary[1]);
        }

        if ($preview === '') {
            $preview = implode('', array_slice($characters, 0, $maxLength));
        }

        return $preview . '…';
    }
}
