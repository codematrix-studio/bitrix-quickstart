<?php

/**
 * Локальная точка инициализации проекта.
 * Не размещайте здесь бизнес-логику: подключайте отдельные файлы из include/.
 */

$localRoot = dirname(__DIR__);

// Composer autoload подключается, если зависимости установлены.
$autoloadFile = $localRoot . '/vendor/autoload.php';
if (is_file($autoloadFile)) {
    require_once $autoloadFile;
}

// Резервный PSR-4 автозагрузчик проекта. Позволяет запускать локальные классы
// без Composer и подстраховывает при неполной/устаревшей карте Composer.
spl_autoload_register(static function (string $class): void {
    $prefix = 'Project\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $classFile = __DIR__ . '/classes/' . str_replace('\\', '/', $relativeClass) . '.php';

    if (is_file($classFile)) {
        require_once $classFile;
    }
});

$configFile = __DIR__ . '/config/const.php';
if (is_file($configFile)) {
    require_once $configFile;
}

// По умолчанию логирование выключено; файлы хранятся в /local/logs.
if (class_exists(\Project\Support\Logger::class)) {
    \Project\Support\Logger::configure(
        defined('PROJECT_LOG_ENABLED') ? (bool)PROJECT_LOG_ENABLED : false,
        $localRoot . '/logs'
    );
}

$handlersFile = __DIR__ . '/include/handlers.php';
if (is_file($handlersFile)) {
    require_once $handlersFile;
}
