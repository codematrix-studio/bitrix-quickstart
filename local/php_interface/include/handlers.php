<?php

/**
 * Регистрация обработчиков событий проекта.
 *
 * Подключайте только те обработчики, которые действительно используются.
 * Классы обработчиков размещайте в local/php_interface/classes/.
 */

use Bitrix\Main\EventManager;
use Project\Bitrix\IBlock\Property\ColorProperty;
use Project\Bitrix\IBlock\Property\YesNoProperty;

// Пользовательский тип свойства инфоблока «Да/Нет».
if (class_exists(EventManager::class)) {
    EventManager::getInstance()->addEventHandler(
        'iblock',
        'OnIBlockPropertyBuildList',
        [YesNoProperty::class, 'getUserTypeDescription']
    );

    EventManager::getInstance()->addEventHandler(
        'iblock',
        'OnIBlockPropertyBuildList',
        [ColorProperty::class, 'getUserTypeDescription']
    );
}

// Пример регистрации обработчика:
// EventManager::getInstance()->addEventHandler(
//     'main',
//     'OnBeforeUserRegister',
//     [Project\EventHandlers\UserHandler::class, 'beforeRegister']
// );
