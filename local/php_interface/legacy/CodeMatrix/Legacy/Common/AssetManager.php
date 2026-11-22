<?php

/**
 * Совместимый адаптер старого CodeMatrix\Legacy\Common\AssetManager.
 * Подключать после Composer autoload. Пространство имён CodeMatrix\\Legacy
 */

namespace CodeMatrix\Legacy\Common;

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
