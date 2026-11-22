<?php

declare(strict_types=1);

namespace Project\Examples\Cache;

use Bitrix\Main\Application;
use Bitrix\Main\Data\Cache;
use InvalidArgumentException;
use Throwable;

/**
 * Возвращает данные из кеша с привязкой к тегам инвалидации.
 *
 * Каталог кеша для initCache() и startTagCache() должен совпадать.
 * В $cacheId необходимо включить все параметры, влияющие на результат.
 *
 * @param callable(): mixed $producer
 * @param string[] $tags
 */
function getTaggedCachedData(
    callable $producer,
    string $cacheId,
    string $cacheDirectory,
    array $tags,
    int $ttl = 3600
): mixed {
    if ($ttl < 0) {
        throw new InvalidArgumentException('Время жизни кеша не может быть отрицательным.');
    }

    foreach ($tags as $tag) {
        if (!is_string($tag) || $tag === '') {
            throw new InvalidArgumentException('Теги кеша должны быть непустыми строками.');
        }
    }

    $cache = Cache::createInstance();

    if ($cache->initCache($ttl, $cacheId, $cacheDirectory)) {
        return $cache->getVars();
    }

    if (!$cache->startDataCache()) {
        return $producer();
    }

    $taggedCache = Application::getInstance()->getTaggedCache();
    $taggedCache->startTagCache($cacheDirectory);

    try {
        $data = $producer();

        foreach ($tags as $tag) {
            $taggedCache->registerTag($tag);
        }
    } catch (Throwable $exception) {
        $taggedCache->abortTagCache();
        $cache->abortDataCache();

        throw $exception;
    }

    $taggedCache->endTagCache();
    $cache->endDataCache($data);

    return $data;
}
