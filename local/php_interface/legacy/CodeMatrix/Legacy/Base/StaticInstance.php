<?php

/**
 * Совместимая база singleton-классов lema.lib.
 */

namespace CodeMatrix\Legacy\Base;

abstract class StaticInstance extends \Project\Support\PerClassInstance
{
    protected static $instance = null;

    public static function get(): static
    {
        static::$instance = parent::get();

        return static::$instance;
    }
}
