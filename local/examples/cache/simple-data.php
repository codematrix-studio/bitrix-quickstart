<?php

declare(strict_types=1);

use Bitrix\Main\Data\Cache;
use Throwable;

/**
 * Возвращает данные из кеша или получает их через callback.
 *
 * В $cacheId необходимо включить все параметры, влияющие на результат.
 *
 * @param callable(): mixed $producer
 */
function getCachedData(
    callable $producer,
    string $cacheId,
    string $cacheDirectory,
    int $ttl = 3600
): mixed {
    if ($ttl < 0) {
        throw new InvalidArgumentException('Время жизни кеша не может быть отрицательным.');
    }

    $cache = Cache::createInstance();

    if ($cache->initCache($ttl, $cacheId, $cacheDirectory)) {
        return $cache->getVars();
    }

    if (!$cache->startDataCache()) {
        return $producer();
    }

    try {
        $data = $producer();
        $cache->endDataCache($data);

        return $data;
    } catch (Throwable $exception) {
        $cache->abortDataCache();

        throw $exception;
    }
}
