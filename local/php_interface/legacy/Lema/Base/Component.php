<?php

/**
 * Адаптер совместимости со старым классом Lema\Base\Component.
 * Подключайте файл после загрузки Composer autoloader.
 */

namespace Lema\Base;

abstract class Component extends \Project\Bitrix\Component\ComponentInvoker
{
}
