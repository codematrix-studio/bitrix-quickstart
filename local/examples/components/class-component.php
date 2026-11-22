<?php

declare(strict_types=1);

use Bitrix\Main\SystemException;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

/**
 * Базовый пример простого компонента.
 *
 * Скопируйте файл в каталог собственного компонента и переименуйте класс.
 * Бизнес-логику выносите в отдельные сервисы, а в arResult передавайте
 * только подготовленные данные для шаблона.
 */
class ExampleComponent extends CBitrixComponent
{
    /**
     * Нормализует параметры до начала выполнения компонента.
     *
     * @param array<string, mixed> $arParams
     * @return array<string, mixed>
     */
    public function onPrepareComponentParams($arParams): array
    {
        $arParams['CACHE_TIME'] = max(0, (int) ($arParams['CACHE_TIME'] ?? 3600));

        return $arParams;
    }

    /**
     * Точка входа компонента.
     */
    public function executeComponent(): void
    {
        if (!$this->startResultCache($this->arParams['CACHE_TIME'])) {
            return;
        }

        try {
            $this->loadModules();
            $this->arResult = $this->buildResult();

            $this->includeComponentTemplate();
        } catch (Throwable $exception) {
            $this->abortResultCache();

            // В реальном компоненте используйте журналирование проекта.
            ShowError('Не удалось выполнить компонент.');
        }
    }

    /**
     * Подключает только те модули, которые действительно нужны компоненту.
     *
     * @throws SystemException
     */
    private function loadModules(): void
    {
        // Пример проверки зависимости:
        // if (!\Bitrix\Main\Loader::includeModule('iblock')) {
        //     throw new SystemException('Модуль iblock не установлен.');
        // }
    }

    /**
     * Подготавливает данные для шаблона.
     *
     * @return array<string, mixed>
     */
    private function buildResult(): array
    {
        return [
            'TITLE' => 'Пример компонента',
            'ITEMS' => [],
        ];
    }
}
