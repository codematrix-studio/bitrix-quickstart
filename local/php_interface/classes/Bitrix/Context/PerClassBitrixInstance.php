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
        $instance = static::createInstance();
        self::$instances[$class] = $instance;

        return $instance;
    }

    public static function resetInstance(): void
    {
        unset(self::$instances[static::class]);
    }
}
