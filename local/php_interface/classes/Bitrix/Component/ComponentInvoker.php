<?php

declare(strict_types=1);

namespace Project\Bitrix\Component;

use LogicException;

/**
 * Base class for invoking Bitrix components with reusable default parameters.
 *
 * Unlike the legacy Lema implementation, getParams() does not mutate shared
 * static defaults when a caller supplies overrides.
 */
abstract class ComponentInvoker
{
    protected static $componentName = '';
    protected static $params = [];

    public static function inc($template = '', array $params = [], $parentComponent = null, array $arFunctionParams = [])
    {
        global $APPLICATION;

        if (static::$componentName === '') {
            throw new LogicException('Empty component name.');
        }

        if (!is_object($APPLICATION) || !method_exists($APPLICATION, 'IncludeComponent')) {
            throw new LogicException('Bitrix $APPLICATION is not available.');
        }

        $componentParams = static::getParams($params);

        return $APPLICATION->IncludeComponent(
            static::$componentName,
            $template,
            $componentParams,
            $parentComponent,
            $arFunctionParams
        );
    }

    public static function includeComponent($component, $template = '', array $params = [], $parentComponent = null, array $arFunctionParams = [])
    {
        global $APPLICATION;

        if (!is_object($APPLICATION) || !method_exists($APPLICATION, 'IncludeComponent')) {
            throw new LogicException('Bitrix $APPLICATION is not available.');
        }

        return $APPLICATION->IncludeComponent(
            $component,
            $template,
            $params,
            $parentComponent,
            $arFunctionParams
        );
    }

    public static function getParams(array $params = [])
    {
        return array_replace(static::$params, $params);
    }
}
