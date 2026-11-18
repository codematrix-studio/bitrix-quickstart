<?php

/**
 * Совместимый фасад старого Lema\\IBlock\\Section.
 * Подключать после Composer autoload.
 */

namespace Lema\IBlock;

class Section
{
    const CLASS_NAME = '\\Bitrix\\Iblock\\SectionTable';
    const OLD_CLASS_NAME = '\\CIBlockSection';

    private static function repository(): \Project\Bitrix\IBlock\SectionRepository
    {
        return new \Project\Bitrix\IBlock\SectionRepository();
    }

    public static function __callStatic($name, $arguments)
    {
        // В старом классе модуль подключался при загрузке файла.
        // Теперь подключаем его только при динамическом вызове D7-метода.
        if (class_exists('Bitrix\\Main\\Loader')) {
            if (!\Bitrix\Main\Loader::includeModule('iblock')) {
                return false;
            }
        } elseif (class_exists('CModule')) {
            if (!\CModule::IncludeModule('iblock')) {
                return false;
            }
        }

        $class = ltrim(static::CLASS_NAME, '\\');
        if (class_exists($class) && method_exists($class, $name)) {
            return call_user_func_array([$class, $name], $arguments);
        }

        return false;
    }

    public static function getListD7($iblockId, array $params = [])
    {
        return static::repository()->getListD7($iblockId, $params);
    }

    public static function getList($iblockId, array $params = [])
    {
        return static::repository()->getList($iblockId, $params);
    }

    public static function getAllD7($iblockId, array $params = [])
    {
        return static::repository()->getAllD7($iblockId, $params);
    }

    public static function getAll($iblockId, array $params = [])
    {
        return static::repository()->getAll($iblockId, $params);
    }
}
