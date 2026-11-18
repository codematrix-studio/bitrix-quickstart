<?php

/**
 * Регистрация обработчиков событий проекта.
 *
 * Подключайте только те обработчики, которые действительно используются.
 * Классы обработчиков размещайте в local/php_interface/classes/.
 */

// Пример регистрации обработчика:
//
// use Bitrix\Main\EventManager;
//
// EventManager::getInstance()->addEventHandler(
//     'main',
//     'OnBeforeUserRegister',
//     [Project\EventHandlers\UserHandler::class, 'beforeRegister']
// );
