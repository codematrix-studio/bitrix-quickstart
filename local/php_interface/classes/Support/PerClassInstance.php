<?php

declare(strict_types=1);

namespace Project\Support;

/**
 * Базовый класс с одним экземпляром на каждый класс-наследник.
 */
abstract class PerClassInstance
{
    private static array $instances = [];

    public static function get(): static
    {
        $class = static::class;

        if (!isset(self::$instances[$class])) {
            self::$instances[$class] = new static();
        }

        return self::$instances[$class];
    }

    public static function resetInstance(): void
    {
        unset(self::$instances[static::class]);
    }
}
