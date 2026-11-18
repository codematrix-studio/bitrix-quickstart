<?php

declare(strict_types=1);

namespace Project\Bitrix\Context;

/**
 * Основа для фасадов над объектами Bitrix, получаемыми из контекста.
 *
 * Метод createInstance() вызывается для каждого класса-наследника отдельно.
 */
abstract class PerClassBitrixInstance
{
    private static array $instances = [];

    abstract protected static function createInstance();

    public static function get()
    {
        $class = static::class;

        if (!array_key_exists($class, self::$instances)) {
            self::$instances[$class] = static::createInstance();
        }

        return self::$instances[$class];
    }

    public static function resetInstance(): void
    {
        unset(self::$instances[static::class]);
    }
}
