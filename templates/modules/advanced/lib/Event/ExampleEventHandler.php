<?php

declare(strict_types=1);

namespace Vendor\AdvancedModule\Event;

/**
 * Пример обработчика классического события OnAfterUserAdd.
 */
final class ExampleEventHandler
{
    /**
     * Получает поля пользователя в формате классического API.
     *
     * @param array<string, mixed> $fields Поля пользователя и результат операции.
     */
    public static function onAfterUserAdd(array &$fields): void
    {
        // Добавьте здесь логику модуля, использующую результат создания пользователя.
    }
}
