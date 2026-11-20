<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

/**
 * Базовый каркас D7-компонента.
 *
 * Переименуйте класс и дополните получение данных под задачу компонента.
 * Зависимости от модулей подключайте явно в getResult() через Loader::includeModule().
 *
 * @package Project\\Components
 */
class ProjectD7Component extends CBitrixComponent
{
    /**
     * Подключает языковые сообщения компонента штатным механизмом Bitrix.
     *
     * @return void
     */
    public function onIncludeComponentLang(): void
    {
        $this->includeComponentLang(basename(__FILE__));
    }

    /**
     * Нормализует входные параметры компонента.
     *
     * @param array<string, mixed> $arParams Исходные параметры компонента.
     *
     * @return array<string, mixed> Нормализованные параметры компонента.
     */
    public function onPrepareComponentParams($arParams): array
    {
        $arParams['CACHE_TIME'] = max(0, (int) ($arParams['CACHE_TIME'] ?? 3600));

        return $arParams;
    }

    /**
     * Формирует данные, передаваемые в шаблон компонента.
     *
     * В этом примере каждый элемент содержит строковое поле NAME.
     * Если компонент использует модуль Битрикс, сначала проверьте его подключение:
     * Bitrix\\Main\\Loader::includeModule('iblock').
     *
     * @return array{ITEMS: list<array{NAME: string}>} Данные для шаблона.
     */
    protected function getResult(): array
    {
        return [
            'ITEMS' => [],
        ];
    }

    /**
     * Выполняет компонент и управляет кешированием результата.
     *
     * При ошибке кеш текущего запуска отменяется, после чего исключение
     * передаётся обработчику Bitrix. Не скрывайте ошибки разработки пустым результатом.
     *
     * @return void
     *
     * @throws \\Throwable Если получение данных или подключение шаблона завершилось ошибкой.
     */
    public function executeComponent(): void
    {
        if (!$this->startResultCache()) {
            return;
        }

        try {
            $this->arResult = $this->getResult();
            $this->includeComponentTemplate();
        } catch (\\Throwable $exception) {
            $this->abortResultCache();

            throw $exception;
        }
    }
}
