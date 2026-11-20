<?php

use Bitrix\Main\Localization\Loc;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

/**
 * Базовый каркас D7-компонента.
 *
 * Переименуйте класс и дополните получение данных под задачу компонента.
 *
 * @package Project\Components
 */
class ProjectD7Component extends CBitrixComponent
{
    /**
     * Подключает языковые сообщения компонента.
     *
     * @return void
     */
    public function onIncludeComponentLang()
    {
        $this->includeComponentLang(basename(__FILE__));
        Loc::loadMessages(__FILE__);
    }

    /**
     * Нормализует входные параметры компонента.
     *
     * @param array<string, mixed> $arParams Исходные параметры компонента.
     *
     * @return array<string, mixed> Нормализованные параметры компонента.
     */
    public function onPrepareComponentParams($arParams)
    {
        $arParams['CACHE_TIME'] = max(0, (int) ($arParams['CACHE_TIME'] ?? 3600));

        return $arParams;
    }

    /**
     * Формирует данные, передаваемые в шаблон компонента.
     *
     * Здесь размещайте получение и подготовку данных. Сложную бизнес-логику
     * выносите в отдельные сервисы, а не накапливайте в компоненте.
     *
     * @return array<string, mixed> Данные для шаблона компонента.
     */
    protected function getResult()
    {
        return [
            'ITEMS' => [],
        ];
    }

    /**
     * Выполняет компонент с учётом кеширования.
     *
     * @return void
     */
    public function executeComponent()
    {
        try {
            if ($this->startResultCache()) {
                $this->arResult = $this->getResult();
                $this->includeComponentTemplate();
            }
        } catch (\Throwable $exception) {
            $this->abortResultCache();
            ShowError($exception->getMessage());
        }
    }
}
