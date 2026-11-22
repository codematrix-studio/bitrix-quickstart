<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

/** @var array<string, mixed> $arComponentDescription */
$arComponentDescription = [
    'NAME' => 'Шаблон классического компонента',
    'DESCRIPTION' => 'Минимальная заготовка компонента на процедурном API',
    'SORT' => 100,
    'PATH' => [
        'ID' => 'project',
        'NAME' => 'Компоненты проекта',
    ],
];
