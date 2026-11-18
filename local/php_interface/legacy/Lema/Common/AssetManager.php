<?php

/**
 * Совместимый адаптер старого Lema\Common\AssetManager.
 * Подключать после Composer autoload и только после отключения старого lema.lib.
 */

namespace Lema\Common;

class AssetManager extends \Project\Bitrix\Asset\AssetManager
{
    /** @var array<class-string, static> */
    private static array $instances = [];

    public static function get(): static
    {
        $class = static::class;

        return self::$instances[$class] ??= new static();
    }

    public static function resetInstance(): void
    {
        unset(self::$instances[static::class]);
    }
}
