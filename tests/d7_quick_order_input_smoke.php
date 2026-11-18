<?php

declare(strict_types=1);

require_once __DIR__ . '/../local/php_interface/classes/Bitrix/Sale/QuickOrderInput.php';

use Project\Bitrix\Sale\QuickOrderInput;

function quickOrderInputExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$input = QuickOrderInput::fromPayload([
    'productId' => '321',
    'quantity' => '2.5',
    'USER_ID' => 999,
]);

quickOrderInputExpect($input->productId === 321, 'Строковый ID товара из HTTP-запроса должен преобразовываться в целое число.');
quickOrderInputExpect($input->quantity === 2.5, 'Дробное количество должно сохраняться.');
quickOrderInputExpect(!property_exists($input, 'userId') && !property_exists($input, 'USER_ID'), 'DTO не должен содержать идентификатор пользователя.');

$invalidPayloads = [
    ['productId' => 0, 'quantity' => 1],
    ['productId' => -1, 'quantity' => 1],
    ['productId' => '1.5', 'quantity' => 1],
    ['productId' => '999999999999999999999999', 'quantity' => 1],
    ['productId' => 1, 'quantity' => ''],
    ['productId' => 1, 'quantity' => 'abc'],
    ['productId' => 1, 'quantity' => 0],
    ['productId' => 1, 'quantity' => -1],
    ['productId' => 1, 'quantity' => INF],
    ['productId' => 1, 'quantity' => NAN],
    ['productId' => 1, 'quantity' => true],
];

foreach ($invalidPayloads as $payload) {
    $failed = false;

    try {
        QuickOrderInput::fromPayload($payload);
    } catch (InvalidArgumentException) {
        $failed = true;
    }

    quickOrderInputExpect($failed, 'Некорректные входные данные должны отклоняться.');
}

$defaultQuantity = QuickOrderInput::fromPayload(['productId' => 7]);
quickOrderInputExpect($defaultQuantity->quantity === 1, 'Количество по умолчанию должно быть равно единице.');

echo "Проверки QuickOrderInput пройдены.\n";
