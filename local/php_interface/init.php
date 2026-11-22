<?php

/**
 * Локальная точка инициализации проекта.
 * Не размещайте здесь бизнес-логику: подключайте отдельные файлы из include/.
 */

$localRoot = dirname(__DIR__);

$autoloadFile = $localRoot . '/vendor/autoload.php';
if (is_file($autoloadFile)) {
    require_once $autoloadFile;
}

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
