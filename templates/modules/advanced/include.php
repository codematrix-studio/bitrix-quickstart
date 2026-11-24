<?php

use Bitrix\Main\Loader;

Loader::registerAutoLoadClasses('vendor.advancedmodule', [
    \Vendor\AdvancedModule\Event\EventRegistry::class => 'lib/Event/EventRegistry.php',
    \Vendor\AdvancedModule\Event\ExampleEventHandler::class => 'lib/Event/ExampleEventHandler.php',
    \Vendor\AdvancedModule\Agent\ExampleAgent::class => 'lib/Agent/ExampleAgent.php',
    \Vendor\AdvancedModule\Agent\AgentRegistry::class => 'lib/Agent/AgentRegistry.php',
]);
