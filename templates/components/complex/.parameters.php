<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

/** @var array<string, mixed> $arComponentParameters */
$arComponentParameters = [
    'PARAMETERS' => [
        'VARIABLE_ALIASES' => [
            'ELEMENT_ID' => [
                'NAME' => 'Идентификатор элемента',
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
                'VARIABLES' => [
                    'ELEMENT_ID' => 'ELEMENT_ID',
                ],
            ],
        ],
        'CACHE_TIME' => [
            'PARENT' => 'BASE',
            'NAME' => 'Время кеширования, секунд',
            'TYPE' => 'STRING',
            'DEFAULT' => '3600',
        ],
    ],
];
