<?php

declare(strict_types=1);

namespace Project\Support;

use RuntimeException;

/**
 * Получает глобальный объект по имени, заданному классом-наследником.
 * Кэш и имя глобальной переменной изолированы по классу.
 */
abstract class PerClassGlobalProxy
{
    private static array $instances = [];
    private static array $globalNames = [];

    abstract protected static function globalName(): string;

    public static function get()
    {
        $class = static::class;

        if (!array_key_exists($class, self::$instances)) {
            $name = static::globalName();

            if ($name === '') {
                throw new RuntimeException('Не задано имя глобальной переменной.');
            }

            if (!array_key_exists($name, $GLOBALS)) {
                throw new RuntimeException('Глобальная переменная не найдена: ' . $name);
            }

            self::$globalNames[$class] = $name;
            self::$instances[$class] = $GLOBALS[$name];
        }

        return self::$instances[$class];
    }

    public function __call($name, $arguments)
    {
        $object = static::get();

        if (!is_object($object) || !is_callable([$object, $name])) {
            return false;
        }

        return $object->{$name}(...$arguments);
    }

    public static function resetInstance(): void
    {
        $class = static::class;
        unset(self::$instances[$class], self::$globalNames[$class]);
    }
}
