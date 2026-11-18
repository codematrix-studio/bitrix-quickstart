<?php

/**
 * Compatibility adapter for the legacy Lema\Base\Component class.
 * Load after Composer autoloading.
 */

namespace Lema\Base;

abstract class Component extends \Project\Bitrix\Component\ComponentInvoker
{
}
