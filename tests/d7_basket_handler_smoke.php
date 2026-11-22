<?php

declare(strict_types=1);

require_once __DIR__ . '/../local/php_interface/classes/Bitrix/Sale/BasketManager.php';
require_once __DIR__ . '/../local/php_interface/classes/Support/CharsetConverter.php';
require_once __DIR__ . '/../local/php_interface/classes/Support/HtmlEscaper.php';
require_once __DIR__ . '/../local/php_interface/classes/Bitrix/Sale/BasketHandler.php';
require_once __DIR__ . '/../local/php_interface/legacy/CodeMatrix/Legacy/Basket/BasketHandler.php';

use Project\Bitrix\Sale\BasketHandler;
use Project\Bitrix\Sale\BasketManager;
use CodeMatrix\Legacy\Basket\BasketHandler as LegacyBasketHandler;

function basketHandlerExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

class BasketHandlerResult
{
    public function isSuccess(): bool { return true; }
    public function getErrorMessages(): array { return []; }
}

class BasketHandlerItem
{
    private array $fields = [];

    public function __construct(
        private BasketHandlerBasket $basket,
        private int $id,
        private int $productId
    ) {}

    public function setFields(array $fields): BasketHandlerResult
    {
        $this->fields = array_replace($this->fields, $fields);
        return new BasketHandlerResult();
    }

    public function getId(): int { return $this->id; }
    public function getProductId(): int { return $this->productId; }
    public function getQuantity(): float { return (float) ($this->fields['QUANTITY'] ?? 0); }
    public function getPrice(): float { return 125.5; }
    public function getField(string $name) { return $this->fields[$name] ?? ($name === 'NAME' ? 'Тестовый товар' : null); }
    public function delete(): BasketHandlerResult
    {
        $this->basket->remove($this->id);
        return new BasketHandlerResult();
    }
}

class BasketHandlerBasket
{
    private array $items = [];
    private int $nextId = 1;

    public function getExistsItem(string $module, int $productId): ?BasketHandlerItem
    {
        foreach ($this->items as $item) {
            if ($item->getProductId() === $productId) return $item;
        }
        return null;
    }

    public function createItem(string $module, int $productId): BasketHandlerItem
    {
        $item = new BasketHandlerItem($this, $this->nextId++, $productId);
        $this->items[$item->getId()] = $item;
        return $item;
    }

    public function getItemById(int $id): ?BasketHandlerItem { return $this->items[$id] ?? null; }
    public function getBasketItems(): array { return array_values($this->items); }
    public function getQuantityList(): array
    {
        $result = [];
        foreach ($this->items as $id => $item) $result[$id] = $item->getQuantity();
        return $result;
    }
    public function getPrice(): float { return count($this->items) * 125.5; }
    public function getBasePrice(): float { return count($this->items) * 150.0; }
    public function save(): BasketHandlerResult { return new BasketHandlerResult(); }
    public function remove(int $id): void { unset($this->items[$id]); }
}

$basket = new BasketHandlerBasket();
$manager = new BasketManager(
    static fn (): BasketHandlerBasket => $basket,
    static fn (): string => 's1',
    static fn (): int => 77,
    static fn (): string => 'RUB'
);
$handler = new BasketHandler(
    $manager,
    static fn (int $iblockId, array $ids): array => [$ids[0] => ['ID' => $ids[0], 'NAME' => 'Товар']],
    static fn (float $price): string => number_format($price, 2, '.', '') . ' ₽'
);

$handler->addAction(['PRODUCT_ID' => 501, 'QUANTITY' => 2]);
$handler->add([['product_id' => '502', 'quantity' => '1']]);

$items = $handler->getBasketItems();
basketHandlerExpect(count($items) === 2, 'Фасад должен возвращать обе позиции корзины.');
basketHandlerExpect($items[0]['product_id'] === 501 && $items[0]['quantity'] === 2.0, 'Снимок должен содержать ID товара и количество.');
basketHandlerExpect($items[0]['name'] === 'Тестовый товар', 'Снимок должен содержать безопасное название товара.');
basketHandlerExpect($handler->getItemsCount() === 2, 'Количество позиций должно делегироваться BasketManager.');
basketHandlerExpect($handler->getBasketItem(501)['id'] === $items[0]['id'], 'Поиск товара должен возвращать снимок позиции.');
basketHandlerExpect($handler->getBasketItemsWithFields(9)['fields'][501]['NAME'] === 'Товар', 'Загрузчик полей товара должен получать ID инфоблока и товаров.');
basketHandlerExpect($handler->getBasketItemsWithProps(['ID', 'QUANTITY'])[1]['QUANTITY'] === 2.0, 'Разрешённые поля позиции должны возвращаться по ID строки.');
basketHandlerExpect($handler->getFormattedPrice() === '251.00 ₽', 'Форматирование суммы должно использовать переданный форматтер.');
basketHandlerExpect($handler->getFormattedBasePrice() === '300.00 ₽', 'Базовая стоимость должна форматироваться отдельно от итоговой суммы.');
basketHandlerExpect($handler->getFUserId() === 77, 'ID FUSER должен быть доступен через совместимый метод.');
basketHandlerExpect(LegacyBasketHandler::get() instanceof LegacyBasketHandler, 'Старое имя класса должно сохранять статический get().');
basketHandlerExpect(LegacyBasketHandler::encodeWithCharset('<script>') === '&lt;script&gt;', 'Старый метод кодирования должен использовать безопасный HTML-экранирование.');

$rawBasketAccessRejected = false;
try {
    $handler->getBasket();
} catch (LogicException) {
    $rawBasketAccessRejected = true;
}
basketHandlerExpect($rawBasketAccessRejected, 'Прямой доступ к изменяемой корзине должен завершаться понятной ошибкой.');

$unsafePriceRejected = false;
try {
    $handler->addAction(['PRODUCT_ID' => 503, 'PRICE' => 1]);
} catch (InvalidArgumentException) {
    $unsafePriceRejected = true;
}
basketHandlerExpect($unsafePriceRejected, 'Передача произвольной цены должна отклоняться.');

$unsafePropsRejected = false;
try {
    $handler->addAction(['PRODUCT_ID' => 503, 'PROPERTIES' => ['COLOR' => 'red']]);
} catch (InvalidArgumentException) {
    $unsafePropsRejected = true;
}
basketHandlerExpect($unsafePropsRejected, 'Передача свойств товара должна отклоняться.');

$partialAddPrevented = false;
try {
    $handler->add([
        ['PRODUCT_ID' => 504, 'QUANTITY' => 1],
        ['PRODUCT_ID' => 505, 'PRICE' => 1],
    ]);
} catch (InvalidArgumentException) {
    $partialAddPrevented = $handler->getItemsCount() === 2
        && $handler->getBasketItem(504) === false;
}
basketHandlerExpect($partialAddPrevented, 'Ошибка валидации пакета добавления не должна менять корзину.');

$rawBasketRejected = false;
try {
    $handler->getBasketItem(501, 1);
} catch (InvalidArgumentException) {
    $rawBasketRejected = true;
}
basketHandlerExpect($rawBasketRejected, 'Поиск по произвольной цене должен отклоняться.');

$unknownFieldRejected = false;
try {
    $handler->getBasketItemsWithProps(['CUSTOM_FIELD']);
} catch (InvalidArgumentException) {
    $unknownFieldRejected = true;
}
basketHandlerExpect($unknownFieldRejected, 'Произвольные поля позиции не должны открываться через фасад.');

$partialDeletePrevented = false;
try {
    $handler->delete([$items[0]['id'], 999]);
} catch (InvalidArgumentException) {
    $partialDeletePrevented = $handler->getItemsCount() === 2;
}
basketHandlerExpect($partialDeletePrevented, 'Невалидный ID в пакете удаления должен быть отклонён до начала удаления.');

$handler->deleteAction(['ID' => $items[1]['id']]);
basketHandlerExpect($handler->getItemsCount() === 1, 'Удаление должно выполняться по ID строки корзины.');

$handler->clear();
basketHandlerExpect($handler->getItemsCount() === 0, 'Очистка должна затрагивать только текущую корзину.');
basketHandlerExpect($handler->getBasketItemsWithFields(9) === ['items' => [], 'fields' => []], 'Пустая корзина не должна запускать загрузку всех товаров инфоблока.');

$quickOrderRejected = false;
try {
    $handler->fastOrderAction();
} catch (LogicException) {
    $quickOrderRejected = true;
}
basketHandlerExpect($quickOrderRejected, 'Старый быстрый заказ должен направлять к отдельному QuickOrder.');

echo "Проверки BasketHandler пройдены.\n";
