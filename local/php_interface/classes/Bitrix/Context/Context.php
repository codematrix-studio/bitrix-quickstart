<?php

declare(strict_types=1);

namespace Project\Bitrix\Context;

use RuntimeException;

/** Фасад над Bitrix\Main\Context текущего запроса. */
final class Context extends PerClassBitrixInstance
{
    protected static function createInstance()
    {
        if (!class_exists(\Bitrix\Main\Application::class)) {
            throw new RuntimeException('Класс Bitrix\Main\Application недоступен.');
        }

        $application = \Bitrix\Main\Application::getInstance();
        if (!is_object($application) || !is_callable([$application, 'getContext'])) {
            throw new RuntimeException('Не удалось получить контекст Bitrix.');
        }

        $context = $application->getContext();
        if (!is_object($context)) {
            throw new RuntimeException('Приложение Bitrix вернуло некорректный контекст.');
        }

        return $context;
    }
}
