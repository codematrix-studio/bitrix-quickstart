<?php

declare(strict_types=1);

$localRoot = dirname(__DIR__) . '/local';
$autoloadFile = $localRoot . '/vendor/autoload.php';

if (!is_file($autoloadFile)) {
    throw new RuntimeException('Сначала выполните composer dump-autoload --working-dir=local.');
}

require_once $localRoot . '/php_interface/init.php';

if (!class_exists(Composer\Autoload\ClassLoader::class)) {
    throw new RuntimeException('Composer autoloader не был подключён из init.php.');
}

$classes = [
    Project\Support\Logger::class,
    Project\Bitrix\IBlock\Property\YesNoProperty::class,
    Project\Bitrix\IBlock\Property\ColorProperty::class,
    Project\Bitrix\IBlock\Property\YouTubeProperty::class,
];

foreach ($classes as $class) {
    if (!class_exists($class)) {
        throw new RuntimeException('Composer autoload не загрузил класс: ' . $class);
    }
}

echo "Проверка Composer autoload через init.php пройдена.\n";
