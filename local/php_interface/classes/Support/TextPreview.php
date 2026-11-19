<?php

declare(strict_types=1);

namespace Project\Support;

use InvalidArgumentException;

/**
 * Преобразует HTML или обычный текст в короткий и удобочитаемый текстовый фрагмент.
 */
final class TextPreview
{
    private const DEFAULT_MAX_LENGTH = 300;

    /**
     * Создаёт текстовый фрагмент, по возможности обрезая его по границе слова.
     *
     * Максимальная длина измеряется в графемных кластерах Unicode.
     */
    public function create(string $text, int $maxLength = self::DEFAULT_MAX_LENGTH): string
    {
        if ($maxLength < 1) {
            throw new InvalidArgumentException('The maximum preview length must be greater than zero.');
        }

        // Удаляет блоки, не содержащие основного текста, и сохраняет разделение между блочными элементами.
        $text = preg_replace(
            '~<(script|style)\b[^>]*>.*?</\1\s*>~isu',
            ' ',
            $text
        );

        if ($text === null) {
            throw new InvalidArgumentException('The input contains invalid UTF-8 data.');
        }

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

        // Не оставляет часть последнего слова, если доступна граница слова.
        if (preg_match('/^(.*)\s+\S*$/us', $preview, $wordBoundary) === 1) {
            $preview = rtrim($wordBoundary[1]);
        }

        if ($preview === '') {
            $preview = implode('', array_slice($characters, 0, $maxLength));
        }

        return $preview . '…';
    }
}
