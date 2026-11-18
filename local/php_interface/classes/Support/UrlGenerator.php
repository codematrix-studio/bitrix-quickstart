<?php

declare(strict_types=1);

namespace Project\Support;

use InvalidArgumentException;

/**
 * Формирует абсолютные ссылки только от явно заданного доверенного адреса сайта.
 * Не использует HTTP_HOST или SERVER_NAME из входящего запроса.
 */
final class UrlGenerator
{
    private string $baseUrl;

    public function __construct(string $baseUrl)
    {
        $baseUrl = rtrim(trim($baseUrl), '/');
        $parts = parse_url($baseUrl);

        if ($parts === false
            || !isset($parts['scheme'], $parts['host'])
            || !in_array(strtolower($parts['scheme']), ['http', 'https'], true)
            || isset($parts['user'])
            || isset($parts['pass'])
            || preg_match('/[\r\n]/', $baseUrl)
        ) {
            throw new InvalidArgumentException('Нужен доверенный базовый URL сайта с протоколом http или https.');
        }

        $this->baseUrl = $baseUrl;
    }

    public function absolute(string $path = ''): string
    {
        if (preg_match('/[\r\n]/', $path) || preg_match('~^[a-z][a-z0-9+.-]*:~i', $path) || str_starts_with($path, '//')) {
            throw new InvalidArgumentException('Ожидается относительный путь, а не внешний URL.');
        }

        return $this->baseUrl . '/' . ltrim($path, '/');
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }
}
