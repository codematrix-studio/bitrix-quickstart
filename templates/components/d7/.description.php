<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

/** @var array<string, mixed> $arComponentDescription */
$arComponentDescription = [
    'NAME' => 'Шаблон D7-компонента',
    'DESCRIPTION' => 'Заготовка компонента на базе CBitrixComponent',
    'SORT' => 110,
    'PATH' => [
        'ID' => 'project',
        'NAME' => 'Компоненты проекта',
    ],
];
