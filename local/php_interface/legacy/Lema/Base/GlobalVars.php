<?php

/**
 * Совместимая база доступа к глобальным объектам из lema.lib.
 */

namespace Lema\Base;

abstract class GlobalVars extends \Project\Support\PerClassGlobalProxy
{
    protected static $object = null;
    protected static $varName = null;

    abstract public static function setVarName();

    protected static function globalName(): string
    {
        static::setVarName();

        return (string) static::$varName;
    }

    public static function get()
    {
        static::$object = parent::get();

        return static::$object;
    }
}
