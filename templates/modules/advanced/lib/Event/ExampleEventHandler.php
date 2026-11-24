<?php

declare(strict_types=1);

namespace Vendor\AdvancedModule\Event;

use Bitrix\Main\Event;

/**
 * Безопасный пример D7-обработчика.
 *
 * Здесь намеренно нет побочных действий: добавьте бизнес-логику конкретного
 * модуля и проверяйте состав параметров события на целевой версии ядра.
 */
final class ExampleEventHandler
{
    public static function onAfterUserAdd(Event $event): void
    {
        // Example hook: implement module-specific behavior here.
    }
}
