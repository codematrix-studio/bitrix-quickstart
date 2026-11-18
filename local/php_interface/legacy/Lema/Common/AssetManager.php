<?php

/**
 * Совместимый адаптер старого Lema\Common\AssetManager.
 * Подключать после Composer autoload и только после отключения старого lema.lib.
 */

namespace Lema\Common;

class AssetManager extends \Project\Bitrix\Asset\AssetManager
{
    private static ?self $instance = null;

    public static function get(): static
    {
        return self::$instance ??= new static();
    }

    public static function resetInstance(): void
    {
        self::$instance = null;
    }
}
