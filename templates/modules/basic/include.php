<?php

use Bitrix\Main\Loader;

Loader::registerAutoLoadClasses('vendor.module', [
    \Vendor\Module\ExampleService::class => 'lib/ExampleService.php',
]);
