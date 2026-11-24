<?php

use Bitrix\Main\Loader;

Loader::registerAutoLoadClasses('vendor.advancedmodule', [
    \Vendor\AdvancedModule\Event\EventRegistry::class => 'lib/Event/EventRegistry.php',
    \Vendor\AdvancedModule\Event\ExampleEventHandler::class => 'lib/Event/ExampleEventHandler.php',
]);
