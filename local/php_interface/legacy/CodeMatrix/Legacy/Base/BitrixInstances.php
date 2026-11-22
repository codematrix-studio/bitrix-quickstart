<?php

/**
 * Совместимая база фасадов над объектами Bitrix.
 */

namespace CodeMatrix\Legacy\Base;

abstract class BitrixInstances extends \Project\Bitrix\Context\PerClassBitrixInstance
{
    protected static $instance = null;

    abstract public static function setInstance();


    public static function get()
    {
        static::$instance = parent::get();

        return static::$instance;
    }

    protected static function createInstance()
    {
        static::setInstance();

        return static::$instance;
    }
}
