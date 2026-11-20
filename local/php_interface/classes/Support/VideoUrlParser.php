<?php

declare(strict_types=1);

namespace Project\Support;

/**
 * Разбирает ссылки на видео YouTube, Rutube и Vimeo.
 *
 * Не обращается к API видеохостингов и не загружает превью.
 */
final class VideoUrlParser
{
    /**
     * Возвращает данные видео или null, если ссылка не поддерживается.
     *
     * @return array{provider: string, id: string, url: string, embed_url: string}|null
     */
    public static function parse(string $url): ?array
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        $parts = parse_url($url);
        if (
            !is_array($parts)
            || !isset($parts['scheme'], $parts['host'], $parts['path'])
            || !in_array(strtolower($parts['scheme']), ['http', 'https'], true)
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['port'])
        ) {
            return null;
        }

        $host = strtolower(rtrim($parts['host'], '.'));
        $path = $parts['path'];

        if (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com'], true)) {
            parse_str($parts['query'] ?? '', $query);
            $id = null;

            if ($path === '/watch' && isset($query['v']) && is_string($query['v'])) {
                $id = $query['v'];
            } elseif (preg_match('~^/(?:embed|shorts|live)/([A-Za-z0-9_-]{11})/?$~D', $path, $matches)) {
                $id = $matches[1];
            }

            if (!is_string($id) || !preg_match('/^[A-Za-z0-9_-]{11}$/D', $id)) {
                return null;
            }

            return self::result(
                'youtube',
                $id,
                'https://www.youtube.com/watch?v=' . $id,
                'https://www.youtube.com/embed/' . $id
            );
        }

        if (in_array($host, ['youtube-nocookie.com', 'www.youtube-nocookie.com'], true)) {
            if (!preg_match('~^/embed/([A-Za-z0-9_-]{11})/?$~D', $path, $matches)) {
                return null;
            }

            $id = $matches[1];

            return self::result(
                'youtube',
                $id,
                'https://www.youtube.com/watch?v=' . $id,
                'https://www.youtube-nocookie.com/embed/' . $id
            );
        }

        if (in_array($host, ['youtu.be', 'www.youtu.be'], true)) {
            if (!preg_match('~^/([A-Za-z0-9_-]{11})/?$~D', $path, $matches)) {
                return null;
            }

            $id = $matches[1];

            return self::result(
                'youtube',
                $id,
                'https://www.youtube.com/watch?v=' . $id,
                'https://www.youtube.com/embed/' . $id
            );
        }

        if (in_array($host, ['rutube.ru', 'www.rutube.ru'], true)) {
            if (!preg_match('~^/(?:video|play/embed)/([A-Za-z0-9_-]{8,64})/?$~D', $path, $matches)) {
                return null;
            }

            $id = $matches[1];

            return self::result(
                'rutube',
                $id,
                'https://rutube.ru/video/' . $id . '/',
                'https://rutube.ru/play/embed/' . $id . '/'
            );
        }

        if (in_array($host, ['vimeo.com', 'www.vimeo.com', 'player.vimeo.com'], true)) {
            $pattern = $host === 'player.vimeo.com'
                ? '~^/video/(\d+)/?$~D'
                : '~(?:^|/)(\d+)(?:/|$)~';

            if (!preg_match($pattern, $path, $matches)) {
                return null;
            }

            $id = $matches[1];

            return self::result(
                'vimeo',
                $id,
                'https://vimeo.com/' . $id,
                'https://player.vimeo.com/video/' . $id
            );
        }

        return null;
    }

    /**
     * @return array{provider: string, id: string, url: string, embed_url: string}
     */
    private static function result(string $provider, string $id, string $url, string $embedUrl): array
    {
        return [
            'provider' => $provider,
            'id' => $id,
            'url' => $url,
            'embed_url' => $embedUrl,
        ];
    }
}
