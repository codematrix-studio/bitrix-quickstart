<?php

declare(strict_types=1);

namespace Lema\\Common;

use LogicException;

/** Переходный фасад старого Lema\\Common\\Asset. */
final class Asset
{
    public static function get(): object
    {
        $class = '\\\\Bitrix\\\\Main\\\\Page\\\\Asset';

        if (!class_exists($class) || !is_callable([$class, 'getInstance'])) {
            throw new LogicException('Для Lema\\\\Common\\\\Asset требуется Bitrix\\\\Main\\\\Page\\\\Asset::getInstance().');
        }

        return $class::getInstance();
    }
}
