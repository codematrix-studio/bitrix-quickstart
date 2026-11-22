<?php

declare(strict_types=1);

namespace Project\Bitrix\Sale;

use Closure;
use InvalidArgumentException;
use RuntimeException;

/**
 * Управляет корзиной текущего FUSER на текущем сайте через D7 Sale.
 *
 * Экземпляр хранит загруженную корзину только в пределах своего жизненного
 * цикла. Не используйте статический singleton: контекст сайта и FUSER
 * определяется при первой операции с корзиной.
 */
class BasketManager
{
    private ?Closure $basketLoader;
    private ?Closure $siteProvider;
    private ?Closure $fuserProvider;
    private ?Closure $currencyProvider;
    private ?object $basket = null;

    public function __construct(
        ?callable $basketLoader = null,
        ?callable $siteProvider = null,
        ?callable $fuserProvider = null,
        ?callable $currencyProvider = null
    ) {
        $this->basketLoader = $basketLoader === null ? null : Closure::fromCallable($basketLoader);
        $this->siteProvider = $siteProvider === null ? null : Closure::fromCallable($siteProvider);
        $this->fuserProvider = $fuserProvider === null ? null : Closure::fromCallable($fuserProvider);
        $this->currencyProvider = $currencyProvider === null ? null : Closure::fromCallable($currencyProvider);
    }

    /**
     * Добавляет товар в корзину или увеличивает количество существующей позиции.
     *
     * @return int ID позиции корзины
     */
    public function add(int $productId, int|float $quantity = 1): int
    {
        $this->assertPositiveId($productId, 'ID товара');
        $this->assertPositiveQuantity($quantity);

        if ($this->basketLoader === null) {
            $this->includeModule('catalog');
        }

        $basket = $this->basket();
        $item = $basket->getExistsItem('catalog', $productId);

        if ($item === null || $item === false) {
            $item = $basket->createItem('catalog', $productId);
            if (!is_object($item) || !is_callable([$item, 'setFields'])) {
                throw new RuntimeException('Не удалось создать позицию корзины.');
            }

            $fields = [
                'QUANTITY' => $quantity,
                'CURRENCY' => $this->getCurrency(),
                'LID' => $this->getSiteId(),
                'PRODUCT_PROVIDER_CLASS' => \Bitrix\Catalog\Product\CatalogProvider::class,
            ];
        } else {
            if (!is_callable([$item, 'getQuantity'])) {
                throw new RuntimeException('Позиция корзины не поддерживает получение количества.');
            }

            $fields = [
                'QUANTITY' => (float) $item->getQuantity() + $quantity,
            ];
        }

        $this->assertResult($item->setFields($fields), 'изменить позицию корзины');
        $this->saveBasket();

        if (!is_callable([$item, 'getId'])) {
            throw new RuntimeException('Не удалось получить ID позиции корзины после сохранения.');
        }

        return (int) $item->getId();
    }

    /**
     * Устанавливает количество конкретной позиции корзины.
     */
    public function setQuantity(int $basketItemId, int|float $quantity): void
    {
        $this->assertPositiveId($basketItemId, 'ID позиции корзины');
        $this->assertPositiveQuantity($quantity);

        $item = $this->basket()->getItemById($basketItemId);
        if (!is_object($item) || !is_callable([$item, 'setFields'])) {
            throw new InvalidArgumentException(sprintf('Позиция корзины с ID %d не найдена.', $basketItemId));
        }

        $this->assertResult($item->setFields(['QUANTITY' => $quantity]), 'изменить количество товара');
        $this->saveBasket();
    }

    /**
     * Удаляет позицию корзины по её ID, а не по ID товара.
     */
    public function remove(int $basketItemId): void
    {
        $this->assertPositiveId($basketItemId, 'ID позиции корзины');

        $item = $this->basket()->getItemById($basketItemId);
        if (!is_object($item) || !is_callable([$item, 'delete'])) {
            throw new InvalidArgumentException(sprintf('Позиция корзины с ID %d не найдена.', $basketItemId));
        }

        $this->assertResult($item->delete(), 'удалить позицию корзины');
        $this->saveBasket();
    }

    /**
     * Удаляет все позиции текущей корзины.
     */
    public function clear(): void
    {
        foreach ($this->getItems() as $item) {
            if (!is_object($item) || !is_callable([$item, 'delete'])) {
                throw new RuntimeException('Обнаружена некорректная позиция корзины.');
            }

            $this->assertResult($item->delete(), 'очистить позицию корзины');
        }

        $this->saveBasket();
    }

    /**
     * Возвращает позиции корзины как объекты D7 BasketItem.
     *
     * @return array<int, object>
     */
    public function getItems(): array
    {
        $items = $this->basket()->getBasketItems();

        if (!is_array($items)) {
            throw new RuntimeException('Не удалось получить список позиций корзины.');
        }

        return $items;
    }

    /**
     * Возвращает количество позиций, независимо от количества товаров в них.
     */
    public function getItemsCount(): int
    {
        return count($this->getItems());
    }

    /**
     * Возвращает суммарное количество товаров с учётом количества в позициях.
     */
    public function getGoodsCount(): float
    {
        $quantities = $this->basket()->getQuantityList();

        if (!is_array($quantities)) {
            throw new RuntimeException('Не удалось получить количество товаров в корзине.');
        }

        return array_sum(array_map('floatval', $quantities));
    }

    /**
     * Возвращает итоговую стоимость корзины по расчётам D7 Sale.
     */
    public function getPrice(): float
    {
        return (float) $this->basket()->getPrice();
    }

    /**
     * Возвращает стоимость корзины до применения скидок, если её поддерживает ядро.
     */
    public function getBasePrice(): float
    {
        $basket = $this->basket();
        if (!is_callable([$basket, 'getBasePrice'])) {
            throw new RuntimeException('Текущая версия D7 Sale не предоставляет Basket::getBasePrice().');
        }

        return (float) $basket->getBasePrice();
    }

    private function basket(): object
    {
        if ($this->basket !== null) {
            return $this->basket;
        }

        $siteId = $this->getSiteId();
        $fuserId = $this->getFuserId();

        if ($this->basketLoader !== null) {
            $basket = ($this->basketLoader)($fuserId, $siteId);
        } else {
            $this->includeModule('sale');
            $basket = \Bitrix\Sale\Basket::loadItemsForFUser($fuserId, $siteId);
        }

        if (!is_object($basket)) {
            throw new RuntimeException('Не удалось загрузить корзину покупателя.');
        }

        foreach (['getBasketItems', 'getQuantityList', 'getExistsItem', 'createItem', 'getItemById', 'save', 'getPrice'] as $method) {
            if (!is_callable([$basket, $method])) {
                throw new RuntimeException(sprintf('Объект корзины не поддерживает метод %s().', $method));
            }
        }

        $this->basket = $basket;

        return $this->basket;
    }

    private function getSiteId(): string
    {
        if ($this->siteProvider !== null) {
            $siteId = ($this->siteProvider)();
        } else {
            if (!class_exists(\Bitrix\Main\Context::class)) {
                throw new RuntimeException('Контекст Bitrix недоступен: невозможно определить текущий сайт.');
            }

            $siteId = \Bitrix\Main\Context::getCurrent()->getSite();
        }

        if (!is_string($siteId) || $siteId === '') {
            throw new RuntimeException('Не удалось определить ID текущего сайта.');
        }

        return $siteId;
    }

    /**
     * Возвращает ID FUSER текущего покупателя.
     *
     * Метод сохраняет совместимость с прежним BasketHandler.
     */
    public function getFuserId(): int
    {
        if ($this->fuserProvider !== null) {
            $fuserId = ($this->fuserProvider)();
        } else {
            $this->includeModule('sale');

            if (!class_exists(\Bitrix\Sale\Fuser::class)) {
                throw new RuntimeException('Класс Bitrix\Sale\Fuser недоступен.');
            }

            $fuserId = \Bitrix\Sale\Fuser::getId();
        }

        if (!is_int($fuserId) || $fuserId < 1) {
            throw new RuntimeException('Не удалось определить FUSER текущего покупателя.');
        }

        return $fuserId;
    }

    /** Возвращает базовую валюту текущего сайта. */
    public function getCurrency(): string
    {
        if ($this->currencyProvider !== null) {
            $currency = ($this->currencyProvider)();
        } else {
            $this->includeModule('currency');

            if (!class_exists(\Bitrix\Currency\CurrencyManager::class)) {
                throw new RuntimeException('Класс CurrencyManager недоступен.');
            }

            $currency = \Bitrix\Currency\CurrencyManager::getBaseCurrency();
        }

        if (!is_string($currency) || $currency === '') {
            throw new RuntimeException('Не удалось определить базовую валюту.');
        }

        return $currency;
    }

    private function includeModule(string $moduleId): void
    {
        if (!class_exists(\Bitrix\Main\Loader::class) || !\Bitrix\Main\Loader::includeModule($moduleId)) {
            throw new RuntimeException(sprintf('Не удалось подключить модуль Битрикс %s.', $moduleId));
        }
    }

    private function saveBasket(): void
    {
        $this->assertResult($this->basket()->save(), 'сохранить корзину');
    }

    private function assertResult($result, string $operation): void
    {
        if (!is_object($result) || !is_callable([$result, 'isSuccess'])) {
            throw new RuntimeException(sprintf('Не удалось %s: ORM вернул некорректный результат.', $operation));
        }

        if ($result->isSuccess()) {
            return;
        }

        $messages = is_callable([$result, 'getErrorMessages']) ? $result->getErrorMessages() : [];
        $details = is_array($messages) ? implode('; ', $messages) : '';

        throw new RuntimeException(sprintf(
            'Не удалось %s%s.',
            $operation,
            $details !== '' ? ': ' . $details : ''
        ));
    }

    private function assertPositiveId(int $id, string $name): void
    {
        if ($id < 1) {
            throw new InvalidArgumentException(sprintf('%s должен быть положительным целым числом.', $name));
        }
    }

    private function assertPositiveQuantity(int|float $quantity): void
    {
        if (!is_finite((float) $quantity) || $quantity <= 0) {
            throw new InvalidArgumentException('Количество товара должно быть положительным числом.');
        }
    }
}
