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

$handlersFile = __DIR__ . '/include/handlers.php';
if (is_file($handlersFile)) {
    require_once $handlersFile;
}
