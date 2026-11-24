<?php

use Bitrix\Main\Loader;

Loader::registerAutoLoadClasses('vendor.advancedmodule', [
    \Vendor\AdvancedModule\Event\EventRegistry::class => 'lib/Event/EventRegistry.php',
    \Vendor\AdvancedModule\Event\ExampleEventHandler::class => 'lib/Event/ExampleEventHandler.php',
    \Vendor\AdvancedModule\Agent\ExampleAgent::class => 'lib/Agent/ExampleAgent.php',
    \Vendor\AdvancedModule\Agent\AgentRegistry::class => 'lib/Agent/AgentRegistry.php',
    \Vendor\AdvancedModule\Install\Migration\MigrationInterface::class => 'install/migrations/MigrationInterface.php',
    \Vendor\AdvancedModule\Install\Migration\MigrationRegistry::class => 'install/migrations/MigrationRegistry.php',
    \Vendor\AdvancedModule\Install\Migration\MigrationRunner::class => 'install/migrations/MigrationRunner.php',
    \Vendor\AdvancedModule\Install\Migration\Version202611240001CreateExampleTable::class => 'install/migrations/Version202611240001CreateExampleTable.php',
]);
