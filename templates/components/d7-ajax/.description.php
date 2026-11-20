<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

/** @var array<string, mixed> $arComponentDescription */
$arComponentDescription = [
    'NAME' => 'Шаблон D7-компонента с AJAX',
    'DESCRIPTION' => 'Заготовка Controllerable с POST, CSRF и проверкой прав',
    'SORT' => 120,
    'PATH' => [
        'ID' => 'project',
        'NAME' => 'Компоненты проекта',
    ],
];
