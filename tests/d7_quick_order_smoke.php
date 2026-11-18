<?php

declare(strict_types=1);

require_once __DIR__ . '/../local/php_interface/classes/Bitrix/Sale/OrderManager.php';
require_once __DIR__ . '/../local/php_interface/classes/Bitrix/Sale/QuickOrder.php';

use Project\Bitrix\Sale\OrderManager;
use Project\Bitrix\Sale\QuickOrder;

function quickOrderExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

class FakeQuickOrderResult
{
    public function __construct(
        private bool $success = true,
        private array $errors = []
    ) {}

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getErrorMessages(): array
    {
        return $this->errors;
    }
}

class FakeQuickOrderItem
{
    public array $fields = [];
    public bool $saveSuccess = true;

    public function setFields(array $fields): FakeQuickOrderResult
    {
        $this->fields = $fields;

        return new FakeQuickOrderResult($this->saveSuccess, ['Ошибка заполнения позиции']);
    }
}

class FakeQuickOrderBasket
{
    public ?FakeQuickOrderItem $item = null;
    public string $provider = '';
    public int $productId = 0;
    public bool $itemSaveSuccess = true;

    public function createItem(string $provider, int $productId): FakeQuickOrderItem
    {
        $this->provider = $provider;
        $this->productId = $productId;
        $this->item = new FakeQuickOrderItem();
        $this->item->saveSuccess = $this->itemSaveSuccess;

        return $this->item;
    }
}

class FakeQuickOrderManager extends OrderManager
{
    public ?object $basket = null;
    public array $arguments = [];

    public function createFromBasket(
        object $basket,
        int $userId,
        int $personTypeId,
        array $propertyValues = [],
        ?int $deliveryId = null,
        ?int $paySystemId = null,
        ?string $comments = null
    ): int {
        $this->basket = $basket;
        $this->arguments = [$userId, $personTypeId, $propertyValues, $deliveryId, $paySystemId, $comments];

        return 987;
    }
}

$basket = new FakeQuickOrderBasket();
$manager = new FakeQuickOrderManager();
$factoryCalls = 0;
$quickOrder = new QuickOrder(
    $manager,
    static function (string $siteId) use (&$factoryCalls, $basket): FakeQuickOrderBasket {
        $factoryCalls++;

        quickOrderExpect($siteId === 's1', 'В фабрику корзины должен передаваться текущий сайт.');

        return $basket;
    },
    static fn (): string => 's1',
    static fn (): string => 'Bitrix\\Catalog\\Product\\CatalogProvider'
);

$orderId = $quickOrder->create(
    321,
    2.5,
    101,
    3,
    ['EMAIL' => 'buyer@example.test'],
    15,
    23,
    'Быстрый заказ'
);

quickOrderExpect($orderId === 987, 'Сервис должен вернуть ID заказа.');
quickOrderExpect($factoryCalls === 1, 'Для быстрого заказа должна создаваться отдельная корзина.');
quickOrderExpect($basket->provider === 'catalog', 'Товар должен добавляться через провайдер каталога.');
quickOrderExpect($basket->productId === 321, 'В корзину должен попасть запрошенный товар.');
quickOrderExpect($basket->item?->fields === [
    'QUANTITY' => 2.5,
    'LID' => 's1',
    'PRODUCT_PROVIDER_CLASS' => 'Bitrix\\Catalog\\Product\\CatalogProvider',
], 'Позиция должна получать количество и стандартный провайдер каталога; цена и валюта рассчитываются провайдером.');
quickOrderExpect($manager->basket === $basket, 'В менеджер заказа должна передаваться временная корзина.');
quickOrderExpect($manager->arguments === [
    101,
    3,
    ['EMAIL' => 'buyer@example.test'],
    15,
    23,
    'Быстрый заказ',
], 'Параметры заказа должны передаваться без подмены.');

$invalidProductFailed = false;
try {
    $quickOrder->create(0, 1, 101, 3);
} catch (InvalidArgumentException) {
    $invalidProductFailed = true;
}
quickOrderExpect($invalidProductFailed, 'Нулевой ID товара должен отклоняться.');

$invalidQuantityFailed = false;
try {
    $quickOrder->create(321, 0, 101, 3);
} catch (InvalidArgumentException) {
    $invalidQuantityFailed = true;
}
quickOrderExpect($invalidQuantityFailed, 'Нулевое количество должно отклоняться.');

$invalidUserFailed = false;
try {
    $quickOrder->create(321, 1, 0, 3);
} catch (InvalidArgumentException) {
    $invalidUserFailed = true;
}
quickOrderExpect($invalidUserFailed, 'Нулевой ID пользователя должен отклоняться.');

$failedItem = new FakeQuickOrderBasket();
$failedQuickOrder = new QuickOrder(
    $manager,
    static fn (): FakeQuickOrderBasket => $failedItem,
    static fn (): string => 's1',
    static fn (): string => 'Bitrix\\Catalog\\Product\\CatalogProvider'
);
$failedItem->itemSaveSuccess = false;
$failedItemSaveFailed = false;
try {
    $failedQuickOrder->create(321, 1, 101, 3);
} catch (RuntimeException $exception) {
    $failedItemSaveFailed = str_contains($exception->getMessage(), 'Ошибка заполнения позиции');
}
quickOrderExpect($failedItemSaveFailed, 'Ошибка заполнения позиции корзины должна передаваться вызывающему коду.');

echo "Проверки QuickOrder пройдены.\n";
