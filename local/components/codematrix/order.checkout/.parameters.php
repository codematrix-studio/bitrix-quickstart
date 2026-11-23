<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

$arComponentParameters = [
    'PARAMETERS' => [
        'DEFAULT_LOCATION' => [
            'PARENT'  => 'BASE',
            'NAME'    => 'Код местоположения по умолчанию',
            'TYPE'    => 'STRING',
            'DEFAULT' => '0000073738',
        ],
        'PATH_TO_BASKET' => [
            'PARENT'  => 'BASE',
            'NAME'    => 'Путь к странице корзины',
            'TYPE'    => 'STRING',
            'DEFAULT' => '/personal/cart/',
        ],
        'PATH_TO_PERSONAL' => [
            'PARENT'  => 'BASE',
            'NAME'    => 'Путь к разделу заказов пользователя',
            'TYPE'    => 'STRING',
            'DEFAULT' => '/personal/orders/',
        ],
    ],
];
