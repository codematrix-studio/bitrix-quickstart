<?php

declare(strict_types=1);

namespace Project\Bitrix\Sale;

use Closure;
use InvalidArgumentException;
use RuntimeException;

/**
 * Оформляет быстрый заказ из отдельной временной корзины.
 *
 * Сервис не использует постоянную корзину FUSER и не принимает цену товара
 * или валюту от вызывающего кода.
 */
class QuickOrder
{
    private OrderManager $orderManager;
    private ?Closure $basketFactory;
    private ?Closure $siteProvider;
    private ?Closure $providerNameResolver;
    private ?Closure $moduleLoader;

    public function __construct(
        OrderManager $orderManager,
        ?callable $basketFactory = null,
        ?callable $siteProvider = null,
        ?callable $providerNameResolver = null,
        ?callable $moduleLoader = null
    ) {
        $this->orderManager = $orderManager;
        $this->basketFactory = $basketFactory === null ? null : Closure::fromCallable($basketFactory);
        $this->siteProvider = $siteProvider === null ? null : Closure::fromCallable($siteProvider);
        $this->providerNameResolver = $providerNameResolver === null ? null : Closure::fromCallable($providerNameResolver);
        $this->moduleLoader = $moduleLoader === null ? null : Closure::fromCallable($moduleLoader);
    }

    /**
     * Создаёт заказ из одной товарной позиции.
     *
     * @param array<string, mixed> $propertyValues Значения свойств заказа по символьным кодам
     */
    public function create(
        int $productId,
        int|float $quantity,
        int $userId,
        int $personTypeId,
        array $propertyValues = [],
        ?int $deliveryId = null,
        ?int $paySystemId = null,
        ?string $comments = null
    ): int {
        $this->assertPositiveId($productId, 'ID товара');
        $this->assertPositiveQuantity($quantity);
        $this->assertPositiveId($userId, 'ID пользователя');
        $this->assertPositiveId($personTypeId, 'ID типа плательщика');

        $siteId = $this->getSiteId();
        $basket = $this->createBasket($siteId);
        $item = $basket->createItem('catalog', $productId);

        if (!is_object($item) || !is_callable([$item, 'setFields'])) {
            throw new RuntimeException('Не удалось создать позицию временной корзины.');
        }

        $this->assertResult(
            $item->setFields([
                'QUANTITY' => $quantity,
                'LID' => $siteId,
                'PRODUCT_PROVIDER_CLASS' => $this->getProviderName(),
            ]),
            'добавить товар во временную корзину'
        );

        return $this->orderManager->createFromBasket(
            $basket,
            $userId,
            $personTypeId,
            $propertyValues,
            $deliveryId,
            $paySystemId,
            $comments
        );
    }

    private function createBasket(string $siteId): object
    {
        if ($this->basketFactory !== null) {
            $basket = ($this->basketFactory)($siteId);
        } else {
            $this->includeModule('sale');
            $this->includeModule('catalog');
            $basket = \Bitrix\Sale\Basket::create($siteId);
        }

        if (!is_object($basket) || !is_callable([$basket, 'createItem'])) {
            throw new RuntimeException('Не удалось создать временную корзину D7.');
        }

        return $basket;
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

    private function getProviderName(): string
    {
        if ($this->providerNameResolver !== null) {
            $providerName = ($this->providerNameResolver)();
        } else {
            $this->includeModule('catalog');

            if (!class_exists(\Bitrix\Catalog\Product\Basket::class)) {
                throw new RuntimeException('Класс провайдера каталога недоступен.');
            }

            $providerName = \Bitrix\Catalog\Product\Basket::getDefaultProviderName();
        }

        if (!is_string($providerName) || $providerName === '') {
            throw new RuntimeException('Не удалось определить стандартный провайдер каталога.');
        }

        return $providerName;
    }

    private function includeModule(string $moduleId): void
    {
        if ($this->moduleLoader !== null) {
            $loaded = ($this->moduleLoader)($moduleId);
        } else {
            $loaded = class_exists(\Bitrix\Main\Loader::class)
                && \Bitrix\Main\Loader::includeModule($moduleId);
        }

        if ($loaded !== true) {
            throw new RuntimeException(sprintf('Не удалось подключить модуль Битрикс %s.', $moduleId));
        }
    }

    private function assertResult($result, string $operation): void
    {
        if (!is_object($result) || !is_callable([$result, 'isSuccess'])) {
            throw new RuntimeException(sprintf('Не удалось %s: Bitrix вернул некорректный результат.', $operation));
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
