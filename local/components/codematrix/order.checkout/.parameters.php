<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

$arComponentParameters = [
    'GROUPS' => [
        'INTEGRATIONS' => [
            'NAME' => 'Интеграции и оплата',
            'SORT' => 200,
        ],
    ],
    'PARAMETERS' => [
        'DEFAULT_LOCATION' => [
            'PARENT'  => 'BASE',
            'NAME'    => 'Код местоположения по умолчанию (например, 0000073738 — Москва)',
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
            'NAME'    => 'Путь к персональному разделу',
            'TYPE'    => 'STRING',
            'DEFAULT' => '/personal/orders/',
        ],
        'AUTO_INIT_PAYMENT' => [
            'PARENT'  => 'INTEGRATIONS',
            'NAME'    => 'Инициализировать онлайн-оплату сразу после создания заказа',
            'TYPE'    => 'CHECKBOX',
            'DEFAULT' => 'Y',
        ],
        'AUTO_REDIRECT_PAYMENT' => [
            'PARENT'  => 'INTEGRATIONS',
            'NAME'    => 'Автоматически перенаправлять на эквайринг (если шлюз возвращает ссылку или форму)',
            'TYPE'    => 'CHECKBOX',
            'DEFAULT' => 'Y',
        ],
        'DADATA_API_KEY' => [
            'PARENT'  => 'INTEGRATIONS',
            'NAME'    => 'API-ключ DaData (оставьте пустым, чтобы отключить подсказки)',
            'TYPE'    => 'STRING',
            'DEFAULT' => '',
        ],
    ],
];