<?php

/**
 * Совместимая база фасадов над объектами Bitrix.
 */

namespace Lema\Base;

abstract class BitrixInstances extends \\Project\\Bitrix\\Context\\PerClassBitrixInstance
{
    protected static $instance = null;

    abstract public static function setInstance();

    protected static function createInstance()
    {
        static::setInstance();

        return static::$instance;
    }
}
