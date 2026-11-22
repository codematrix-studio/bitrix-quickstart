<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

/** @var array<string, mixed> $arComponentDescription */
$arComponentDescription = [
    'NAME' => 'Шаблон комплексного компонента',
    'DESCRIPTION' => 'Заготовка компонента со страницами списка и элемента',
    'SORT' => 120,
    'PATH' => [
        'ID' => 'project',
        'NAME' => 'Компоненты проекта',
    ],
];
