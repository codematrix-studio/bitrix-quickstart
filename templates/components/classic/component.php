<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

/**
 * @var CBitrixComponent $this Текущий экземпляр компонента.
 * @var array<string, mixed> $arParams Параметры компонента.
 * @var array<string, mixed> $arResult Результат работы компонента.
 */

$arResult = [];

if ($this->StartResultCache()) {
    // Подготовьте данные для шаблона и сохраните их в $arResult.
    $arResult['ITEMS'] = [];

    $this->IncludeComponentTemplate();
}
