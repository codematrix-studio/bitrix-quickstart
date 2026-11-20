<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

/** @var array<string, mixed> $arComponentParameters */
$arComponentParameters = [
    'PARAMETERS' => [
        'SEF_MODE' => [
            'NAME' => 'Использовать ЧПУ',
            'TYPE' => 'CHECKBOX',
            'DEFAULT' => 'Y',
            'REFRESH' => 'Y',
        ],
        'SEF_FOLDER' => [
            'PARENT' => 'BASE',
            'NAME' => 'Каталог ЧПУ',
            'TYPE' => 'STRING',
            'DEFAULT' => '/',
        ],
        'SEF_URL_TEMPLATES' => [
            'LIST' => [
                'list' => '',
                'detail' => '#ELEMENT_ID#/',
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
