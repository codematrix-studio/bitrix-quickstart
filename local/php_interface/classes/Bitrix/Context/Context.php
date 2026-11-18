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

        return $application->getContext();
    }
}
