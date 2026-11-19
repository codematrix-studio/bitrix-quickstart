<?php

/**
 * Адаптер совместимости для проектов, переходящих с lema.lib.
 *
 * Подключайте файл после загрузки Composer autoloader. Старое пространство имён намеренно
 * не регистрируется глобально, чтобы избежать конфликтов с уже установленным lema.lib.
 */

namespace Lema\Base;

class Paginator extends \Project\Support\ArrayPaginator
{
    protected static $instance = null;
}
