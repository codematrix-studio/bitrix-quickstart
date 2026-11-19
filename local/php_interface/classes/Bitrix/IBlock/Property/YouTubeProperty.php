<?php

declare(strict_types=1);

namespace Project\Bitrix\IBlock\Property;

/**
 * Пользовательское свойство инфоблока для ссылок на видео YouTube.
 *
 * В хранилище записывается только проверенный идентификатор видео.
 */
final class YouTubeProperty
{
    public const USER_TYPE = 'codematrix_youtube';

    private const PLAYER_WIDTH = 350;
    private const PLAYER_HEIGHT = 197;

    /**
     * Возвращает описание пользовательского типа свойства.
     *
     * @return array<string, mixed>
     */
    public static function getUserTypeDescription(): array
    {
        return [
            'PROPERTY_TYPE' => 'S',
            'USER_TYPE' => self::USER_TYPE,
            'DESCRIPTION' => 'CodeMatrix: Видео YouTube',
            'GetPropertyFieldHtml' => [self::class, 'getPropertyFieldHtml'],
            'GetAdminListViewHTML' => [self::class, 'getViewHtml'],
            'GetPublicViewHTML' => [self::class, 'getViewHtml'],
            'ConvertToDB' => [self::class, 'convertToDb'],
            'ConvertFromDB' => [self::class, 'convertFromDb'],
            'GetSearchContent' => [self::class, 'getSearchContent'],
            'GetLength' => [self::class, 'getLength'],
        ];
    }

    /**
     * Выводит поле ввода и предпросмотр корректного видео.
     *
     * @param array<string, mixed> $property
     * @param array<string, mixed> $value
     * @param array<string, mixed> $control
     */
    public static function getPropertyFieldHtml(array $property, array $value, array $control): string
    {
        $name = self::escape((string) ($control['VALUE'] ?? ''));
        $videoId = self::extractVideoId($value['VALUE'] ?? '');
        $inputValue = self::escape((string) ($value['VALUE'] ?? ''));

        $html = sprintf(
            '<input type="text" name="%s" value="%s" maxlength="2048" placeholder="Ссылка на YouTube или ID видео" style="width:100%%;max-width:420px">',
            $name,
            $inputValue
        );

        if ($videoId !== null) {
            $html .= sprintf(
                '<div style="margin-top:8px"><iframe width="%d" height="%d" src="https://www.youtube-nocookie.com/embed/%s" title="Предпросмотр видео YouTube" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe></div>',
                self::PLAYER_WIDTH,
                self::PLAYER_HEIGHT,
                self::escape($videoId)
            );
        }

        return $html;
    }

    /**
     * Возвращает безопасную ссылку на видео для списка и публичного просмотра.
     *
     * @param array<string, mixed> $property
     * @param array<string, mixed> $value
     * @param array<string, mixed> $control
     */
    public static function getViewHtml(array $property, array $value, array $control = []): string
    {
        $videoId = self::extractVideoId($value['VALUE'] ?? '');

        if ($videoId === null) {
            return '';
        }

        $url = 'https://www.youtube.com/watch?v=' . rawurlencode($videoId);

        return sprintf(
            '<a href="%s" target="_blank" rel="noopener noreferrer">Открыть видео YouTube</a>',
            self::escape($url)
        );
    }

    /**
     * Перед сохранением заменяет ссылку проверенным ID видео.
     *
     * @param array<string, mixed> $property
     * @param array<string, mixed> $value
     * @return array<string, mixed>
     */
    public static function convertToDb(array $property, array $value): array
    {
        $value['VALUE'] = self::extractVideoId($value['VALUE'] ?? '') ?? '';

        return $value;
    }

    /**
     * Нормализует ранее сохранённое значение.
     *
     * @param array<string, mixed> $property
     * @param array<string, mixed> $value
     * @return array<string, mixed>
     */
    public static function convertFromDb(array $property, array $value): array
    {
        $value['VALUE'] = self::extractVideoId($value['VALUE'] ?? '') ?? '';

        return $value;
    }

    /**
     * Возвращает ID видео для поискового индекса.
     *
     * @param array<string, mixed> $property
     * @param array<string, mixed> $value
     * @param array<string, mixed> $control
     */
    public static function getSearchContent(array $property, array $value, array $control = []): string
    {
        return self::extractVideoId($value['VALUE'] ?? '') ?? '';
    }

    /**
     * Возвращает длину сохранённого идентификатора.
     *
     * @param array<string, mixed> $property
     * @param array<string, mixed> $value
     */
    public static function getLength(array $property, array $value): int
    {
        return strlen(self::extractVideoId($value['VALUE'] ?? '') ?? '');
    }

    /**
     * Извлекает ID только из допустимого формата ссылки YouTube.
     */
    private static function extractVideoId(mixed $value): ?string
    {
        if (!is_string($value) && !is_int($value)) {
            return null;
        }

        $value = trim((string) $value);

        if (preg_match('/^[A-Za-z0-9_-]{11}$/D', $value) === 1) {
            return $value;
        }

        if ($value === '' || preg_match('/\s/', $value) === 1) {
            return null;
        }

        if (!preg_match('~^https?://~i', $value)) {
            $value = 'https://' . $value;
        }

        $parts = parse_url($value);

        if (!is_array($parts) || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
            return null;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));

        if (!in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtu.be'], true)) {
            return null;
        }

        $videoId = null;

        if ($host === 'youtu.be') {
            $segments = explode('/', trim((string) ($parts['path'] ?? ''), '/'));
            $videoId = $segments[0] ?? null;
        } else {
            $path = trim((string) ($parts['path'] ?? ''), '/');
            $segments = $path === '' ? [] : explode('/', $path);

            if ($path === 'watch') {
                parse_str((string) ($parts['query'] ?? ''), $query);
                $videoId = $query['v'] ?? null;
            } elseif (isset($segments[1]) && in_array($segments[0], ['embed', 'shorts', 'live'], true)) {
                $videoId = $segments[1];
            }
        }

        if (!is_string($videoId) || preg_match('/^[A-Za-z0-9_-]{11}$/D', $videoId) !== 1) {
            return null;
        }

        return $videoId;
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
