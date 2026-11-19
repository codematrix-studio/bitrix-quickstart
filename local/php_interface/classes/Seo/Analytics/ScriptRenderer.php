<?php
declare(strict_types=1);

namespace Project\Seo\Analytics;

use JsonException;
use RuntimeException;

/** Кодирует данные для безопасной вставки в HTML-контекст script. */
final class ScriptRenderer
{
    public static function render(array $payload): string
    {
        try {
            $json = json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
                | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR,
                512
            );
        } catch (JsonException $exception) {
            throw new RuntimeException('Не удалось подготовить данные аналитики для JSON.', 0, $exception);
        }

        return '<script>window.dataLayer = window.dataLayer || [];'
            . 'window.dataLayer.push(' . $json . ');</script>' . PHP_EOL;
    }
}
