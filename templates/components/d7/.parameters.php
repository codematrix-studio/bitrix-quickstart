<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

/** @var array<string, mixed> $arComponentParameters */
$arComponentParameters = [
    'PARAMETERS' => [
        'CACHE_TIME' => [
            'PARENT' => 'BASE',
            'NAME' => 'Время кеширования, секунд',
            'TYPE' => 'STRING',
            'DEFAULT' => '3600',
        ],
    ],
];
