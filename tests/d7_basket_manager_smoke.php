<?php

declare(strict_types=1);

require_once __DIR__ . '/../local/php_interface/classes/Bitrix/Sale/BasketManager.php';

use Project\Bitrix\Sale\BasketManager;

function basketManagerExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

class FakeSaleResult
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

class FakeSaleBasketItem
{
    public function __construct(
        private FakeSaleBasket $basket,
        private int $id,
        private int $productId,
        private array $fields = []
    ) {}

    public function setFields(array $fields): FakeSaleResult
    {
        $this->fields = array_replace($this->fields, $fields);

        return new FakeSaleResult();
    }

    public function getQuantity(): float
    {
        return (float) ($this->fields['QUANTITY'] ?? 0);
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getProductId(): int
    {
        return $this->productId;
    }

    public function getFields(): array
    {
        return $this->fields;
    }

    public function delete(): FakeSaleResult
    {
        $this->basket->removeById($this->id);

        return new FakeSaleResult();
    }
}

class FakeSaleBasket
{
    /** @var array<int, FakeSaleBasketItem> */
    private array $items = [];
    private int $nextId = 1;
    public bool $saveSuccess = true;

    public function getExistsItem(string $module, int $productId): ?FakeSaleBasketItem
    {
        foreach ($this->items as $item) {
            if ($item->getProductId() === $productId) {
                return $item;
            }
        }

        return null;
    }

    public function createItem(string $module, int $productId): FakeSaleBasketItem
    {
        $item = new FakeSaleBasketItem($this, $this->nextId++, $productId);
        $this->items[$item->getId()] = $item;

        return $item;
    }

    public function getItemById(int $id): ?FakeSaleBasketItem
    {
        return $this->items[$id] ?? null;
    }

    public function getBasketItems(): array
    {
        return array_values($this->items);
    }

    public function getQuantityList(): array
    {
        $quantities = [];
        foreach ($this->items as $id => $item) {
            $quantities[$id] = $item->getQuantity();
        }

        return $quantities;
    }

    public function getPrice(): float
    {
        return array_sum(array_map(
            static fn (FakeSaleBasketItem $item): float => $item->getQuantity() * 10,
            $this->items
        ));
    }

    public function save(): FakeSaleResult
    {
        return $this->saveSuccess
            ? new FakeSaleResult()
            : new FakeSaleResult(false, ['Ошибка сохранения тестовой корзины']);
    }

    public function removeById(int $id): void
    {
        unset($this->items[$id]);
    }
}

$basket = new FakeSaleBasket();
$loaderCalls = [];
$manager = new BasketManager(
    static function (int $fuserId, string $siteId) use ($basket, &$loaderCalls): FakeSaleBasket {
        $loaderCalls[] = [$fuserId, $siteId];

        return $basket;
    },
    static fn (): string => 's1',
    static fn (): int => 42,
    static fn (): string => 'RUB'
);

$firstId = $manager->add(100, 2);
$secondCallId = $manager->add(100, 3);
$secondId = $manager->add(200, 1);

$firstItem = $basket->getItemById($firstId);
basketManagerExpect(
    $firstItem instanceof FakeSaleBasketItem
        && $firstItem->getFields()['PRODUCT_PROVIDER_CLASS'] === \Bitrix\Catalog\Product\CatalogProvider::class,
    'Для новых позиций должен использоваться актуальный D7-провайдер каталога.'
);

basketManagerExpect($firstId === $secondCallId, 'Повторное добавление товара должно увеличивать существующую позицию.');
basketManagerExpect($firstId !== $secondId, 'Разные товары должны занимать разные позиции.');
basketManagerExpect(count($loaderCalls) === 1, 'Корзина должна загружаться один раз в пределах экземпляра менеджера.');
basketManagerExpect($loaderCalls[0] === [42, 's1'], 'Загрузчик должен получить FUSER и ID текущего сайта.');
basketManagerExpect($manager->getItemsCount() === 2, 'Должно учитываться количество позиций, а не товаров.');
basketManagerExpect($manager->getGoodsCount() === 6.0, 'Количество товаров должно суммироваться по позициям.');
basketManagerExpect($manager->getPrice() === 60.0, 'Стоимость должна поступать из объекта D7-корзины.');

$manager->setQuantity($firstId, 4);
basketManagerExpect($manager->getGoodsCount() === 5.0, 'setQuantity() должен устанавливать, а не прибавлять количество.');

$manager->remove($secondId);
basketManagerExpect($manager->getItemsCount() === 1, 'remove() должен удалять позицию по ID позиции корзины.');

$manager->clear();
basketManagerExpect($manager->getItemsCount() === 0, 'clear() должен удалять все позиции.');
basketManagerExpect($manager->getGoodsCount() === 0.0, 'После очистки количество товаров должно быть равно нулю.');

$invalidIdFailed = false;
try {
    $manager->add(0);
} catch (InvalidArgumentException) {
    $invalidIdFailed = true;
}
basketManagerExpect($invalidIdFailed, 'Нулевой ID товара должен отклоняться.');

$invalidQuantityFailed = false;
try {
    $manager->add(100, 0);
} catch (InvalidArgumentException) {
    $invalidQuantityFailed = true;
}
basketManagerExpect($invalidQuantityFailed, 'Нулевое количество товара должно отклоняться.');

$missingItemFailed = false;
try {
    $manager->remove(999);
} catch (InvalidArgumentException) {
    $missingItemFailed = true;
}
basketManagerExpect($missingItemFailed, 'Удаление несуществующей позиции должно завершаться понятной ошибкой.');

$failedBasket = new FakeSaleBasket();
$failedBasket->saveSuccess = false;
$failedManager = new BasketManager(
    static fn (): FakeSaleBasket => $failedBasket,
    static fn (): string => 's1',
    static fn (): int => 42,
    static fn (): string => 'RUB'
);
$saveErrorFailed = false;
try {
    $failedManager->add(300);
} catch (RuntimeException $exception) {
    $saveErrorFailed = str_contains($exception->getMessage(), 'Ошибка сохранения тестовой корзины');
}
basketManagerExpect($saveErrorFailed, 'Ошибки сохранения корзины должны быть переданы вызывающему коду.');

echo "Проверки BasketManager пройдены.\n";
