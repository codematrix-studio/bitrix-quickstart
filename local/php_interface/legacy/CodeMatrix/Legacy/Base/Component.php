<?php

/**
 * Адаптер совместимости со старым классом CodeMatrix\Legacy\Base\Component.
 * Подключайте файл после загрузки Composer autoloader.
 */

namespace CodeMatrix\Legacy\Base;

abstract class Component extends \Project\Bitrix\Component\ComponentInvoker
{
}
