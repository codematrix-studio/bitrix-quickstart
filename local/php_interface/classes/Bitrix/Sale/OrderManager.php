<?php

declare(strict_types=1);

namespace Project\Bitrix\Sale;

use Closure;
use InvalidArgumentException;
use RuntimeException;

/**
 * Создаёт заказ D7 из корзины зарегистрированного пользователя.
 *
 * Сервис не выбирает тип плательщика, доставку или оплату автоматически:
 * идентификаторы этих настроек передаются вызывающим кодом.
 */
class OrderManager
{
    private ?Closure $basketLoader;
    private ?Closure $orderFactory;
    private ?Closure $deliveryResolver;
    private ?Closure $paySystemResolver;
    private ?Closure $paySystemObjectResolver;
    private ?Closure $siteProvider;

    public function __construct(
        ?callable $basketLoader = null,
        ?callable $orderFactory = null,
        ?callable $deliveryResolver = null,
        ?callable $paySystemResolver = null,
        ?callable $siteProvider = null,
        ?callable $paySystemObjectResolver = null
    ) {
        $this->basketLoader = $basketLoader === null ? null : Closure::fromCallable($basketLoader);
        $this->orderFactory = $orderFactory === null ? null : Closure::fromCallable($orderFactory);
        $this->deliveryResolver = $deliveryResolver === null ? null : Closure::fromCallable($deliveryResolver);
        $this->paySystemResolver = $paySystemResolver === null ? null : Closure::fromCallable($paySystemResolver);
        $this->paySystemObjectResolver = $paySystemObjectResolver === null ? null : Closure::fromCallable($paySystemObjectResolver);
        $this->siteProvider = $siteProvider === null ? null : Closure::fromCallable($siteProvider);
    }

    /**
     * Создаёт заказ и возвращает его ID.
     *
     * @param array<string, mixed> $propertyValues Значения свойств по символьному коду
     */
    public function create(
        int $userId,
        int $personTypeId,
        array $propertyValues = [],
        ?int $deliveryId = null,
        ?int $paySystemId = null,
        ?string $comments = null
    ): int {
        $this->assertOrderParameters($userId, $personTypeId, $deliveryId, $paySystemId);

        $siteId = $this->getSiteId();
        $basket = $this->loadOrderableBasket($userId, $siteId);

        return $this->createFromPreparedBasket(
            $basket,
            $siteId,
            $userId,
            $personTypeId,
            $propertyValues,
            $deliveryId,
            $paySystemId,
            $comments
        );
    }

    /**
     * Создаёт заказ из явно переданной временной корзины.
     *
     * В отличие от create(), этот метод не загружает и не изменяет постоянную
     * корзину FUSER. Подготовка временной корзины остаётся ответственностью
     * вызывающего сценария.
     *
     * @param array<string, mixed> $propertyValues Значения свойств по символьному коду
     */
    public function createFromBasket(
        object $basket,
        int $userId,
        int $personTypeId,
        array $propertyValues = [],
        ?int $deliveryId = null,
        ?int $paySystemId = null,
        ?string $comments = null
    ): int {
        $this->assertOrderParameters($userId, $personTypeId, $deliveryId, $paySystemId);

        $siteId = $this->getSiteId();
        $orderableBasket = $this->prepareOrderableBasket($basket);

        return $this->createFromPreparedBasket(
            $orderableBasket,
            $siteId,
            $userId,
            $personTypeId,
            $propertyValues,
            $deliveryId,
            $paySystemId,
            $comments
        );
    }

    private function createFromPreparedBasket(
        object $basket,
        string $siteId,
        int $userId,
        int $personTypeId,
        array $propertyValues,
        ?int $deliveryId,
        ?int $paySystemId,
        ?string $comments
    ): int {
        $order = $this->createOrder($siteId, $userId);

        $this->assertResult(
            $order->setPersonTypeId($personTypeId),
            'назначить тип плательщика'
        );
        $this->assertResult(
            $order->setBasket($basket),
            'передать корзину в заказ'
        );

        $this->setPropertyValues($order, $propertyValues);
        $this->validateRequiredProperties($order);

        if ($comments !== null) {
            $this->assertResult(
                $order->setField('COMMENTS', $comments),
                'установить комментарий к заказу'
            );
        }

        $shipment = $deliveryId !== null ? $this->addDelivery($order, $deliveryId) : null;
        $payment = $paySystemId !== null ? $this->addPayment($order, $paySystemId) : null;

        $this->assertResult($order->doFinalAction(true), 'выполнить финальный расчёт заказа');

        // Финальный расчёт может изменить стоимость заказа (скидки, доставка, налоги).
        // Обновляем сумму оплаты и повторно проверяем ограничения сервисов.
        if ($payment !== null && $paySystemId !== null) {
            if (!is_callable([$payment, 'setField'])) {
                throw new RuntimeException('Не удалось получить созданную оплату после расчёта.');
            }

            $this->assertResult(
                $payment->setField('SUM', $order->getPrice()),
                'обновить сумму оплаты после расчёта заказа'
            );

            if (!isset($this->getAvailablePaySystems($payment)[$paySystemId])) {
                throw new InvalidArgumentException('Платёжная система стала недоступна после финального расчёта заказа.');
            }
        }

        if ($shipment !== null && $deliveryId !== null) {
            if (!isset($this->getAvailableDeliveries($shipment)[$deliveryId])) {
                throw new InvalidArgumentException('Служба доставки стала недоступна после финального расчёта заказа.');
            }
        }

        $this->assertResult($order->save(), 'сохранить заказ');

        if (!is_callable([$order, 'getId'])) {
            throw new RuntimeException('После сохранения заказа невозможно получить его ID.');
        }

        $orderId = (int) $order->getId();
        if ($orderId < 1) {
            throw new RuntimeException('Битрикс не вернул корректный ID сохранённого заказа.');
        }

        $warnings = is_callable([$saveResult, 'getWarningMessages'])
            ? $saveResult->getWarningMessages()
            : [];

        if (is_array($warnings) && $warnings !== []) {
            throw new OrderSaveWarningsException(
                $orderId,
                array_map('strval', $warnings)
            );
        }

        return $orderId;
    }

    private function assertOrderParameters(
        int $userId,
        int $personTypeId,
        ?int $deliveryId,
        ?int $paySystemId
    ): void {
        $this->assertPositiveId($userId, 'ID пользователя');
        $this->assertPositiveId($personTypeId, 'ID типа плательщика');

        if ($deliveryId !== null) {
            $this->assertPositiveId($deliveryId, 'ID службы доставки');
        }

        if ($paySystemId !== null) {
            $this->assertPositiveId($paySystemId, 'ID платёжной системы');
        }
    }

    private function loadOrderableBasket(int $userId, string $siteId): object
    {
        if ($this->basketLoader !== null) {
            $basket = ($this->basketLoader)($userId, $siteId);
        } else {
            $this->includeModule('sale');

            $fuserId = \Bitrix\Sale\Fuser::getIdByUserId($userId);
            if (!is_int($fuserId) || $fuserId < 1) {
                throw new RuntimeException('Не удалось получить FUSER для указанного пользователя.');
            }

            $basket = \Bitrix\Sale\Basket::loadItemsForFUser($fuserId, $siteId);
        }

        return $this->prepareOrderableBasket($basket);
    }

    private function prepareOrderableBasket(object $basket): object
    {
        if (!is_callable([$basket, 'refresh']) || !is_callable([$basket, 'getOrderableItems'])) {
            throw new RuntimeException('Корзина не поддерживает пересчёт и получение доступных для заказа позиций.');
        }

        $this->assertResult($basket->refresh(), 'пересчитать корзину');

        $orderableBasket = $basket->getOrderableItems();
        if (!is_object($orderableBasket) || !is_callable([$orderableBasket, 'isEmpty'])) {
            throw new RuntimeException('Не удалось получить доступные для заказа позиции корзины.');
        }

        if ($orderableBasket->isEmpty()) {
            throw new InvalidArgumentException('В корзине нет доступных для заказа товаров.');
        }

        return $orderableBasket;
    }

    private function createOrder(string $siteId, int $userId): object
    {
        if ($this->orderFactory !== null) {
            $order = ($this->orderFactory)($siteId, $userId);
        } else {
            $this->includeModule('sale');
            $order = \Bitrix\Sale\Order::create($siteId, $userId);
        }

        if (!is_object($order)) {
            throw new RuntimeException('Не удалось создать объект заказа.');
        }

        foreach ([
            'setPersonTypeId',
            'setBasket',
            'getPropertyCollection',
            'getBasket',
            'getPrice',
            'getCurrency',
            'setField',
            'getShipmentCollection',
            'getPaymentCollection',
            'doFinalAction',
            'save',
            'getId',
        ] as $method) {
            if (!is_callable([$order, $method])) {
                throw new RuntimeException(sprintf('Объект заказа не поддерживает метод %s().', $method));
            }
        }

        return $order;
    }

    /**
     * @param array<string, mixed> $propertyValues
     */
    private function setPropertyValues(object $order, array $propertyValues): void
    {
        if ($propertyValues === []) {
            return;
        }

        $collection = $order->getPropertyCollection();
        if (!is_object($collection) || !is_callable([$collection, 'getItemByOrderPropertyCode'])) {
            throw new RuntimeException('Не удалось получить коллекцию свойств заказа.');
        }

        foreach ($propertyValues as $code => $value) {
            if (!is_string($code) || trim($code) === '') {
                throw new InvalidArgumentException('Код свойства заказа должен быть непустой строкой.');
            }

            $property = $collection->getItemByOrderPropertyCode($code);
            if (!is_object($property) || !is_callable([$property, 'setValue'])) {
                throw new InvalidArgumentException(sprintf('Свойство заказа с кодом %s не найдено.', $code));
            }

            $this->assertResult(
                $property->setValue($value),
                sprintf('установить значение свойства заказа %s', $code)
            );
        }
    }

    private function validateRequiredProperties(object $order): void
    {
        $collection = $order->getPropertyCollection();
        if (!is_iterable($collection)) {
            throw new RuntimeException('Коллекция свойств заказа не поддерживает перебор.');
        }

        $errors = [];

        foreach ($collection as $property) {
            if (!is_object($property)
                || !is_callable([$property, 'isRequired'])
                || !is_callable([$property, 'getPropertyId'])
                || !is_callable([$property, 'getValue'])
                || !is_callable([$property, 'checkRequiredValue'])
            ) {
                throw new RuntimeException('Элемент коллекции свойств заказа не поддерживает проверку обязательности.');
            }

            if (!$property->isRequired()) {
                continue;
            }

            $result = $property->checkRequiredValue(
                $property->getPropertyId(),
                $property->getValue()
            );

            if (!is_object($result) || !is_callable([$result, 'isSuccess'])) {
                throw new RuntimeException('Не удалось проверить обязательное свойство заказа.');
            }

            if ($result->isSuccess()) {
                continue;
            }

            $messages = is_callable([$result, 'getErrorMessages']) ? $result->getErrorMessages() : [];
            if (is_array($messages) && $messages !== []) {
                $errors = array_merge($errors, $messages);
            } else {
                $errors[] = 'Не заполнено обязательное свойство заказа.';
            }
        }

        if ($errors !== []) {
            throw new InvalidArgumentException(
                'Не заполнены обязательные свойства заказа: ' . implode('; ', $errors)
            );
        }
    }

    private function addDelivery(object $order, int $deliveryId): object
    {
        $collection = $order->getShipmentCollection();
        if (!is_object($collection) || !is_callable([$collection, 'createItem'])) {
            throw new RuntimeException('Не удалось получить коллекцию отгрузок заказа.');
        }

        // Создаём пользовательскую отгрузку без назначения службы.
        $shipment = $collection->createItem();
        if (!is_object($shipment)
            || !is_callable([$shipment, 'getShipmentItemCollection'])
            || !is_callable([$shipment, 'setDeliveryService'])
        ) {
            throw new RuntimeException('Не удалось создать отгрузку заказа.');
        }

        $shipmentItems = $shipment->getShipmentItemCollection();
        if (!is_object($shipmentItems) || !is_callable([$shipmentItems, 'createItem'])) {
            throw new RuntimeException('Не удалось получить позиции отгрузки.');
        }

        $basket = $order->getBasket();
        if (!is_iterable($basket)) {
            throw new RuntimeException('Не удалось получить корзину заказа для формирования отгрузки.');
        }

        foreach ($basket as $basketItem) {
            $shipmentItem = $shipmentItems->createItem($basketItem);
            if (!is_object($shipmentItem) || !is_callable([$shipmentItem, 'setQuantity'])) {
                throw new RuntimeException('Не удалось добавить товар в отгрузку.');
            }

            $this->assertResult(
                $shipmentItem->setQuantity($basketItem->getQuantity()),
                'установить количество товара в отгрузке'
            );
        }

        $availableDeliveries = $this->getAvailableDeliveries($shipment);
        $delivery = $availableDeliveries[$deliveryId] ?? null;

        if (!is_object($delivery)) {
            throw new InvalidArgumentException(sprintf(
                'Служба доставки с ID %d недоступна для этого заказа.',
                $deliveryId
            ));
        }

        $shipment->setDeliveryService($delivery);

        if (!is_callable([$collection, 'calculateDelivery'])) {
            throw new RuntimeException('Коллекция отгрузок не поддерживает расчёт доставки.');
        }

        $this->assertResult($collection->calculateDelivery(), 'рассчитать стоимость доставки');

        return $shipment;
    }

    private function addPayment(object $order, int $paySystemId): object
    {
        $collection = $order->getPaymentCollection();
        if (!is_object($collection) || !is_callable([$collection, 'createItem'])) {
            throw new RuntimeException('Не удалось получить коллекцию оплат заказа.');
        }

        $payment = $collection->createItem();
        if (!is_object($payment) || !is_callable([$payment, 'setField']) || !is_callable([$payment, 'setPaySystemService'])) {
            throw new RuntimeException('Не удалось создать оплату заказа.');
        }

        $this->assertResult($payment->setField('SUM', $order->getPrice()), 'установить сумму оплаты');
        $this->assertResult($payment->setField('CURRENCY', $order->getCurrency()), 'установить валюту оплаты');

        $availablePaySystems = $this->getAvailablePaySystems($payment);
        if (!isset($availablePaySystems[$paySystemId])) {
            throw new InvalidArgumentException(sprintf(
                'Платёжная система с ID %d недоступна для этого заказа.',
                $paySystemId
            ));
        }

        $paySystem = $this->getPaySystemObject($paySystemId);
        if (!is_object($paySystem)) {
            throw new RuntimeException(sprintf('Платёжная система с ID %d не найдена.', $paySystemId));
        }

        $payment->setPaySystemService($paySystem);

        return $payment;
    }

    private function getAvailableDeliveries(object $shipment): array
    {
        if ($this->deliveryResolver !== null) {
            $deliveries = ($this->deliveryResolver)($shipment);
        } else {
            $this->includeModule('sale');
            $deliveries = \Bitrix\Sale\Delivery\Services\Manager::getRestrictedObjectsList($shipment);
        }

        if (!is_array($deliveries)) {
            throw new RuntimeException('Не удалось получить список доступных служб доставки.');
        }

        return $deliveries;
    }

    private function getAvailablePaySystems(object $payment): array
    {
        if ($this->paySystemResolver !== null) {
            $paySystems = ($this->paySystemResolver)($payment);
        } else {
            $this->includeModule('sale');
            $paySystems = \Bitrix\Sale\PaySystem\Manager::getListWithRestrictions(
                $payment,
                \Bitrix\Sale\Services\Base\RestrictionManager::MODE_CLIENT
            );
        }

        if (!is_array($paySystems)) {
            throw new RuntimeException('Не удалось получить список доступных платёжных систем.');
        }

        return $paySystems;
    }

    private function getPaySystemObject(int $paySystemId): ?object
    {
        if ($this->paySystemObjectResolver !== null) {
            $paySystem = ($this->paySystemObjectResolver)($paySystemId);
        } else {
            if (!class_exists(\Bitrix\Sale\PaySystem\Manager::class)) {
                throw new RuntimeException('Менеджер платёжных систем недоступен.');
            }

            $paySystem = \Bitrix\Sale\PaySystem\Manager::getObjectById($paySystemId);
        }

        return is_object($paySystem) ? $paySystem : null;
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

    private function includeModule(string $moduleId): void
    {
        if (!class_exists(\Bitrix\Main\Loader::class) || !\Bitrix\Main\Loader::includeModule($moduleId)) {
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
}
