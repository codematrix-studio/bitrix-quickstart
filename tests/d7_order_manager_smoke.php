<?php

declare(strict_types=1);

require_once __DIR__ . '/../local/php_interface/classes/Bitrix/Sale/OrderManager.php';
require_once __DIR__ . '/../local/php_interface/classes/Bitrix/Sale/OrderSaveWarningsException.php';

use Project\Bitrix\Sale\OrderManager;

function orderManagerExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

class FakeOrderResult
{
    public function __construct(
        private bool $success = true,
        private array $errors = [],
        private array $warnings = []
    ) {}

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getErrorMessages(): array
    {
        return $this->errors;
    }

    public function getWarningMessages(): array
    {
        return $this->warnings;
    }
}

class FakeOrderBasketItem
{
    public function __construct(private float $quantity) {}

    public function getQuantity(): float
    {
        return $this->quantity;
    }
}

class FakeOrderBasket implements IteratorAggregate
{
    public bool $refreshSuccess = true;

    public function refresh(): FakeOrderResult
    {
        return new FakeOrderResult($this->refreshSuccess, ['Ошибка пересчёта корзины']);
    }

    public function getOrderableItems(): FakeOrderOrderableBasket
    {
        return new FakeOrderOrderableBasket($this->refreshSuccess
            ? [new FakeOrderBasketItem(2), new FakeOrderBasketItem(1)]
            : []);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator([new FakeOrderBasketItem(2), new FakeOrderBasketItem(1)]);
    }
}

class FakeOrderOrderableBasket
{
    public function __construct(private array $items) {}

    public function isEmpty(): bool
    {
        return $this->items === [];
    }
}

class FakeOrderProperty
{
    public array $values = [];
    public bool $required = false;

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function getPropertyId(): int
    {
        return 10;
    }

    public function getValue(): mixed
    {
        return $this->values === [] ? '' : end($this->values);
    }

    public function checkRequiredValue(int $propertyId, mixed $value): FakeOrderResult
    {
        return new FakeOrderResult($propertyId === 10 && $value !== '', ['Обязательное свойство EMAIL не заполнено']);
    }

    public function setValue(mixed $value): FakeOrderResult
    {
        $this->values[] = $value;

        return new FakeOrderResult();
    }
}

class FakeOrderPropertyCollection implements IteratorAggregate
{
    public FakeOrderProperty $email;

    public function __construct()
    {
        $this->email = new FakeOrderProperty();
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator([$this->email]);
    }

    public function getItemByOrderPropertyCode(string $code): ?FakeOrderProperty
    {
        return $code === 'EMAIL' ? $this->email : null;
    }
}

class FakeOrderShipmentItem
{
    public function setQuantity(float $quantity): FakeOrderResult
    {
        return new FakeOrderResult($quantity > 0, ['Некорректное количество отгрузки']);
    }
}

class FakeOrderShipmentItems
{
    public array $items = [];

    public function createItem(object $basketItem): FakeOrderShipmentItem
    {
        return $this->items[] = new FakeOrderShipmentItem();
    }
}

class FakeOrderShipment
{
    public FakeOrderShipmentItems $items;
    public mixed $delivery = null;

    public function __construct()
    {
        $this->items = new FakeOrderShipmentItems();
    }

    public function getShipmentItemCollection(): FakeOrderShipmentItems
    {
        return $this->items;
    }

    public function setDeliveryService(object $delivery): void
    {
        $this->delivery = $delivery;
    }
}

class FakeOrderShipmentCollection
{
    public array $items = [];

    public function createItem(): FakeOrderShipment
    {
        return $this->items[] = new FakeOrderShipment();
    }

    public function calculateDelivery(): FakeOrderResult
    {
        return new FakeOrderResult();
    }

    public function getLastItem(): ?FakeOrderShipment
    {
        return $this->items === [] ? null : end($this->items);
    }
}

class FakeOrderPayment
{
    public array $fields = [];
    public mixed $service = null;

    public function setField(string $name, mixed $value): FakeOrderResult
    {
        $this->fields[$name] = $value;

        return new FakeOrderResult();
    }

    public function setPaySystemService(object $service): void
    {
        $this->service = $service;
    }
}

class FakeOrderPaymentCollection
{
    public array $items = [];

    public function createItem(): FakeOrderPayment
    {
        return $this->items[] = new FakeOrderPayment();
    }

    public function getLastItem(): ?FakeOrderPayment
    {
        return $this->items === [] ? null : end($this->items);
    }
}

class FakeOrder
{
    public FakeOrderPropertyCollection $properties;
    public FakeOrderShipmentCollection $shipments;
    public FakeOrderPaymentCollection $payments;
    public mixed $basket = null;
    public int $personTypeId = 0;
    public array $fields = [];
    public bool $saveSuccess = true;
    public int $finalActions = 0;
    public int $saveCalls = 0;
    public array $saveWarnings = [];

    public function __construct()
    {
        $this->properties = new FakeOrderPropertyCollection();
        $this->shipments = new FakeOrderShipmentCollection();
        $this->payments = new FakeOrderPaymentCollection();
    }

    public function setPersonTypeId(int $id): FakeOrderResult
    {
        $this->personTypeId = $id;

        return new FakeOrderResult();
    }

    public function setBasket(object $basket): FakeOrderResult
    {
        $this->basket = $basket;

        return new FakeOrderResult();
    }

    public function getPropertyCollection(): FakeOrderPropertyCollection
    {
        return $this->properties;
    }

    public function getBasket(): FakeOrderBasket
    {
        return new FakeOrderBasket();
    }

    public function getShipmentCollection(): FakeOrderShipmentCollection
    {
        return $this->shipments;
    }

    public function getPaymentCollection(): FakeOrderPaymentCollection
    {
        return $this->payments;
    }

    public function getPrice(): float
    {
        return 125.50;
    }

    public function getCurrency(): string
    {
        return 'RUB';
    }

    public function setField(string $name, mixed $value): FakeOrderResult
    {
        $this->fields[$name] = $value;

        return new FakeOrderResult();
    }

    public function doFinalAction(bool $calculate): FakeOrderResult
    {
        $this->finalActions++;

        return new FakeOrderResult();
    }

    public function save(): FakeOrderResult
    {
        $this->saveCalls++;

        return new FakeOrderResult($this->saveSuccess, ['Ошибка сохранения заказа'], $this->saveWarnings);
    }

    public function getId(): int
    {
        return $this->saveSuccess ? 777 : 0;
    }
}

$basket = new FakeOrderBasket();
$order = new FakeOrder();
$delivery = new stdClass();
$paySystem = new stdClass();
$deliveryChecks = 0;
$paySystemChecks = 0;

$manager = new OrderManager(
    static fn (int $userId, string $siteId): FakeOrderBasket => $basket,
    static fn (string $siteId, int $userId): FakeOrder => $order,
    static function (FakeOrderShipment $shipment) use (&$deliveryChecks, $delivery): array {
        $deliveryChecks++;

        return [15 => $delivery];
    },
    static function (FakeOrderPayment $payment) use (&$paySystemChecks): array {
        $paySystemChecks++;

        return [23 => ['ID' => 23, 'NAME' => 'Тестовая оплата']];
    },
    static fn (): string => 's1',
    static fn (int $id): stdClass => $paySystem
);

$orderId = $manager->create(
    101,
    2,
    ['EMAIL' => 'buyer@example.test'],
    15,
    23,
    'Тестовый заказ'
);

orderManagerExpect($orderId === 777, 'Метод должен вернуть ID сохранённого заказа.');
orderManagerExpect($order->personTypeId === 2, 'Должен быть назначен переданный тип плательщика.');
orderManagerExpect($order->basket !== null, 'Корзина должна быть передана в заказ.');
orderManagerExpect($order->properties->email->values === ['buyer@example.test'], 'Свойство заказа должно быть заполнено по коду.');
orderManagerExpect($order->fields['COMMENTS'] === 'Тестовый заказ', 'Комментарий должен быть записан.');
orderManagerExpect(count($order->shipments->items) === 1, 'Должна быть создана пользовательская отгрузка.');
orderManagerExpect(count($order->shipments->getLastItem()->items->items) === 2, 'Позиции корзины должны быть перенесены в отгрузку.');
orderManagerExpect($order->shipments->getLastItem()->delivery === $delivery, 'Должна быть назначена доступная служба доставки.');
orderManagerExpect(count($order->payments->items) === 1, 'Должна быть создана оплата.');
orderManagerExpect($order->payments->getLastItem()->fields['SUM'] === 125.50, 'Сумма оплаты должна быть обновлена после расчёта.');
orderManagerExpect($order->payments->getLastItem()->fields['CURRENCY'] === 'RUB', 'В оплату должна быть передана валюта заказа.');
orderManagerExpect($order->payments->getLastItem()->service === $paySystem, 'Должна быть назначена платёжная система.');
orderManagerExpect($order->finalActions === 1, 'Финальный расчёт должен быть выполнен один раз.');
orderManagerExpect($deliveryChecks === 2, 'Ограничения доставки должны проверяться до и после финального расчёта.');
orderManagerExpect($paySystemChecks === 2, 'Ограничения оплаты должны проверяться до и после финального расчёта.');

$requiredOrder = new FakeOrder();
$requiredOrder->properties->email->required = true;
$requiredPropertyManager = new OrderManager(
    static fn (): FakeOrderBasket => $basket,
    static fn (): FakeOrder => $requiredOrder,
    null,
    null,
    static fn (): string => 's1'
);
$requiredPropertyFailed = false;
try {
    $requiredPropertyManager->create(101, 2);
} catch (InvalidArgumentException $exception) {
    $requiredPropertyFailed = str_contains($exception->getMessage(), 'EMAIL не заполнено');
}
orderManagerExpect($requiredPropertyFailed, 'Незаполненное обязательное свойство должно отклоняться до сохранения.');
orderManagerExpect($requiredOrder->saveCalls === 0, 'Заказ с незаполненным обязательным свойством не должен сохраняться.');

$invalidUserFailed = false;
try {
    $manager->create(0, 2);
} catch (InvalidArgumentException) {
    $invalidUserFailed = true;
}
orderManagerExpect($invalidUserFailed, 'Нулевой ID пользователя должен отклоняться.');

$unknownPropertyFailed = false;
try {
    $manager->create(101, 2, ['UNKNOWN' => 'value']);
} catch (InvalidArgumentException) {
    $unknownPropertyFailed = true;
}
orderManagerExpect($unknownPropertyFailed, 'Неизвестное свойство заказа должно приводить к понятной ошибке.');

$deniedDeliveryManager = new OrderManager(
    static fn (): FakeOrderBasket => $basket,
    static fn (): FakeOrder => new FakeOrder(),
    static fn (): array => [],
    static fn (): array => [],
    static fn (): string => 's1',
    static fn (): stdClass => new stdClass()
);
$deniedDeliveryFailed = false;
try {
    $deniedDeliveryManager->create(101, 2, [], 999);
} catch (InvalidArgumentException) {
    $deniedDeliveryFailed = true;
}
orderManagerExpect($deniedDeliveryFailed, 'Служба доставки, не прошедшая ограничения, должна быть отклонена.');

$warningOrder = new FakeOrder();
$warningOrder->saveWarnings = ['Предупреждение проверки заказа'];
$warningManager = new OrderManager(
    static fn (): FakeOrderBasket => $basket,
    static fn (): FakeOrder => $warningOrder,
    null,
    null,
    static fn (): string => 's1'
);
$saveWarningsRaised = false;
try {
    $warningManager->create(101, 2);
} catch (\Project\Bitrix\Sale\OrderSaveWarningsException $exception) {
    $saveWarningsRaised = $exception->getOrderId() === 777
        && $exception->getWarnings() === ['Предупреждение проверки заказа'];
}
orderManagerExpect($saveWarningsRaised, 'Предупреждения после сохранения должны возвращать специальное исключение с ID уже созданного заказа.');
orderManagerExpect($warningOrder->saveCalls === 1, 'Заказ с предупреждениями должен быть сохранён ровно один раз.');

$failedOrder = new FakeOrder();
$failedOrder->saveSuccess = false;
$failedSaveManager = new OrderManager(
    static fn (): FakeOrderBasket => $basket,
    static fn (): FakeOrder => $failedOrder,
    static fn (): array => [],
    static fn (): array => [],
    static fn (): string => 's1',
    static fn (): stdClass => new stdClass()
);
$failedSave = false;
try {
    $failedSaveManager->create(101, 2);
} catch (RuntimeException $exception) {
    $failedSave = str_contains($exception->getMessage(), 'Ошибка сохранения заказа');
}
orderManagerExpect($failedSave, 'Ошибка сохранения заказа должна быть передана вызывающему коду.');

$temporaryBasket = new FakeOrderBasket();
$temporaryOrder = new FakeOrder();
$permanentBasketLoads = 0;
$temporaryManager = new OrderManager(
    static function () use (&$permanentBasketLoads): FakeOrderBasket {
        $permanentBasketLoads++;

        throw new RuntimeException('Постоянная корзина не должна загружаться для быстрого заказа.');
    },
    static fn (): FakeOrder => $temporaryOrder,
    null,
    null,
    static fn (): string => 's1'
);
$temporaryOrderId = $temporaryManager->createFromBasket(
    $temporaryBasket,
    202,
    4,
    ['EMAIL' => 'quick@example.test']
);
orderManagerExpect($temporaryOrderId === 777, 'Быстрый заказ должен вернуть ID сохранённого заказа.');
orderManagerExpect($temporaryOrder->basket !== null, 'Временная корзина должна быть передана в заказ.');
orderManagerExpect($permanentBasketLoads === 0, 'Создание заказа из временной корзины не должно загружать постоянную корзину пользователя.');

$invalidTemporaryBasketFailed = false;
try {
    $temporaryManager->createFromBasket(new stdClass(), 202, 4);
} catch (RuntimeException) {
    $invalidTemporaryBasketFailed = true;
}
orderManagerExpect($invalidTemporaryBasketFailed, 'Некорректная временная корзина должна отклоняться.');

echo "Проверки OrderManager пройдены.\n";
