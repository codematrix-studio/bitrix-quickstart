<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

/** @var array<string, mixed> $arComponentParameters */
$arComponentParameters = [
    'GROUPS' => [],
    'PARAMETERS' => [],
    'VARIABLE_ALIASES' => [
        'ELEMENT_ID' => [
            'NAME' => 'Имя GET-параметра идентификатора элемента',
        ],
    ],
    'SEF_MODE' => [
        'list' => [
            'NAME' => 'Страница списка',
            'DEFAULT' => '',
            'VARIABLES' => [],
        ],
        'detail' => [
            'NAME' => 'Детальная страница',
            'DEFAULT' => '#ELEMENT_ID#/',
            'VARIABLES' => ['ELEMENT_ID'],
        ],
    ],
];
