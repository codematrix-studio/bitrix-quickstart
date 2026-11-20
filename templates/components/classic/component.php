<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

/**
 * @var CBitrixComponent $this Текущий экземпляр компонента.
 * @var array<string, mixed> $arParams Параметры компонента.
 * @var array{ITEMS: list<array{NAME: string}>} $arResult Результат компонента.
 */

$cacheTime = max(0, (int) ($arParams['CACHE_TIME'] ?? 3600));

if (!$this->StartResultCache($cacheTime)) {
    return;
}

try {
    $arResult = [
        'ITEMS' => [],
    ];

    // Получите данные компонента и заполните ITEMS до подключения шаблона.
    $this->IncludeComponentTemplate();
} catch (\Throwable $exception) {
    // Не оставляем незавершённую запись кеша при ошибке получения данных или вывода.
    $this->AbortResultCache();

    throw $exception;
}
