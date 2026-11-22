<?php

declare(strict_types=1);

$localRoot = dirname(__DIR__) . '/local';
$autoloadFile = $localRoot . '/vendor/autoload.php';

if (is_file($autoloadFile)) {
    throw new RuntimeException('Тест fallback должен запускаться до генерации Composer autoload.');
}

require_once $localRoot . '/php_interface/init.php';

$classes = [
    Project\Support\Logger::class,
    Project\Bitrix\IBlock\Property\YesNoProperty::class,
    Project\Bitrix\IBlock\Property\ColorProperty::class,
    Project\Bitrix\IBlock\Property\YouTubeProperty::class,
];

foreach ($classes as $class) {
    if (!class_exists($class)) {
        throw new RuntimeException('Резервный автозагрузчик не загрузил класс: ' . $class);
    }
}

if (Project\Support\Logger::isEnabled()) {
    throw new RuntimeException('Логгер не должен включаться без PROJECT_LOG_ENABLED=true.');
}

echo "Проверка резервного автозагрузчика пройдена.\n";
