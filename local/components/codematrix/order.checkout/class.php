<?php
declare(strict_types=1);

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Context;
use Bitrix\Main\Engine\ActionFilter;
use Bitrix\Main\Engine\Contract\Controllerable;
use Bitrix\Main\Error;
use Bitrix\Main\Errorable;
use Bitrix\Main\ErrorCollection;
use Bitrix\Main\Loader;
use Bitrix\Sale\Basket;
use Bitrix\Sale\Delivery;
use Bitrix\Sale\DiscountCouponsManager;
use Bitrix\Sale\Fuser;
use Bitrix\Sale\Internals\PersonTypeTable;
use Bitrix\Sale\Location\LocationTable;
use Bitrix\Sale\Order;
use Bitrix\Sale\PaySystem;

class CodeMatrixOrderCheckoutComponent extends CBitrixComponent implements Controllerable, Errorable
{
    private ErrorCollection $errorCollection;

    public function __construct($component = null)
    {
        parent::__construct($component);
        $this->errorCollection = new ErrorCollection();
    }

    public function getErrors(): array
    {
        return $this->errorCollection->toArray();
    }

    public function getErrorByCode($code): ?Error
    {
        return $this->errorCollection->getErrorByCode($code);
    }

    public function configureActions(): array
    {
        return [
            'recalculate' => [
                'prefilters' => [
                    new ActionFilter\HttpMethod([ActionFilter\HttpMethod::METHOD_POST]),
                    new ActionFilter\Csrf(),
                ],
            ],
            'searchLocation' => [
                'prefilters' => [
                    new ActionFilter\HttpMethod([ActionFilter\HttpMethod::METHOD_POST]),
                    new ActionFilter\Csrf(),
                ],
            ],
            'createOrder' => [
                'prefilters' => [
                    new ActionFilter\HttpMethod([ActionFilter\HttpMethod::METHOD_POST]),
                    new ActionFilter\Csrf(),
                ],
            ],
        ];
    }

    public function executeComponent(): void
    {
        if (!Loader::includeModule('sale') || !Loader::includeModule('catalog')) {
            ShowError('Модули sale или catalog не установлены');
            return;
        }

        $basket = $this->getCurrentBasket();
        if ($basket->isEmpty()) {
            $this->arResult['IS_EMPTY'] = true;
            $this->includeComponentTemplate();
            return;
        }

        $this->arResult['IS_EMPTY'] = false;
        $this->arResult['INITIAL_STATE'] = $this->buildOrderState([
            'personTypeId' => $this->getDefaultPersonTypeId(),
            'properties'   => $this->getAuthorizedUserProps(),
            'deliveryId'   => 0,
            'paySystemId'  => 0,
            'comment'      => '',
            'coupon'       => '',
        ]);

        $this->includeComponentTemplate();
    }

    public function searchLocationAction(string $query = ''): array
    {
        Loader::includeModule('sale');
        $query = trim($query);
        if (mb_strlen($query) < 2) {
            return ['locations' => []];
        }

        $res = LocationTable::getList([
            'filter' => [
                '=NAME.LANGUAGE_ID' => LANGUAGE_ID,
                '%NAME.NAME'        => $query,
                '=TYPE.CODE'        => ['CITY', 'VILLAGE'],
            ],
            'select' => [
                'CODE',
                'CITY_NAME' => 'NAME.NAME',
            ],
            'limit' => 10,
        ]);

        $locations = [];
        while ($row = $res->fetch()) {
            $locations[] = [
                'code' => $row['CODE'],
                'name' => $this->getFullLocationName((string)$row['CODE']),
            ];
        }

        return ['locations' => $locations];
    }

    public function recalculateAction(array $payload = []): array
    {
        Loader::includeModule('sale');
        Loader::includeModule('catalog');

        $coupon = trim((string)($payload['coupon'] ?? ''));
        if ($coupon !== '') {
            DiscountCouponsManager::init();
            DiscountCouponsManager::add($coupon);
        }

        return $this->buildOrderState($payload);
    }

    public function createOrderAction(array $payload = []): ?array
    {
        Loader::includeModule('sale');
        Loader::includeModule('catalog');

        $basket = $this->getCurrentBasket();
        if ($basket->isEmpty()) {
            $this->errorCollection->setError(new Error('Ваша корзина пуста'));
            return null;
        }

        $order = $this->createVirtualOrder($payload);
        $propValues = $payload['properties'] ?? [];

        foreach ($order->getPropertyCollection() as $property) {
            $propData = $property->getProperty();
            $code = (string)$propData['CODE'];
            $val = trim((string)($propValues[$code] ?? ''));

            if ($propData['REQUIRED'] === 'Y' && $val === '') {
                $this->errorCollection->setError(
                    new Error('Заполните обязательное поле: ' . $propData['NAME'], 'PROP_' . $code)
                );
            } elseif ($propData['IS_EMAIL'] === 'Y' && $val !== '' && !check_email($val)) {
                $this->errorCollection->setError(
                    new Error('Некорректный E-Mail в поле: ' . $propData['NAME'], 'PROP_' . $code)
                );
            }
        }

        if (!$this->errorCollection->isEmpty()) {
            return null;
        }

        $order->doFinalAction(true);
        $saveResult = $order->save();

        if (!$saveResult->isSuccess()) {
            foreach ($saveResult->getErrors() as $err) {
                $this->errorCollection->setError($err);
            }
            return null;
        }

        return [
            'orderId'       => $order->getId(),
            'accountNumber' => $order->getField('ACCOUNT_NUMBER'),
        ];
    }

    private function createVirtualOrder(array $payload): Order
    {
        global $USER;
        $siteId = Context::getCurrent()->getSite();
        $userId = ($USER && $USER->IsAuthorized()) ? (int)$USER->GetID() : (int)\CSaleUser::GetAnonymousUserID();

        $personTypes = $this->getPersonTypes($siteId);
        $personTypeId = (int)($payload['personTypeId'] ?? 0);
        if (!isset($personTypes[$personTypeId])) {
            $personTypeId = $this->getDefaultPersonTypeId();
        }

        $order = Order::create($siteId, $userId);
        $order->setPersonTypeId($personTypeId);
        $order->setField('CURRENCY', 'RUB');
        $order->setField('USER_DESCRIPTION', trim((string)($payload['comment'] ?? '')));

        $basket = $this->getCurrentBasket();
        $order->setBasket($basket);

        $propValues = $payload['properties'] ?? [];
        foreach ($order->getPropertyCollection() as $property) {
            $propData = $property->getProperty();
            $code = (string)$propData['CODE'];
            if (array_key_exists($code, $propValues) && $propValues[$code] !== '') {
                $property->setValue($propValues[$code]);
            } elseif ($propData['IS_LOCATION'] === 'Y' && !$property->getValue()) {
                $property->setValue((string)($this->arParams['DEFAULT_LOCATION'] ?? '0000073738'));
            }
        }

        $shipmentCollection = $order->getShipmentCollection();
        $shipment = $shipmentCollection->createItem();
        $shipmentItemCollection = $shipment->getShipmentItemCollection();
        foreach ($basket as $basketItem) {
            $item = $shipmentItemCollection->createItem($basketItem);
            $item->setQuantity($basketItem->getQuantity());
        }

        $availableDeliveries = Delivery\Services\Manager::getRestrictedObjectsList($shipment);
        $deliveryId = (int)($payload['deliveryId'] ?? 0);
        if (!isset($availableDeliveries[$deliveryId]) && !empty($availableDeliveries)) {
            $firstDelivery = reset($availableDeliveries);
            $deliveryId = (int)$firstDelivery->getId();
        }

        if ($deliveryId > 0 && isset($availableDeliveries[$deliveryId])) {
            $shipment->setFields([
                'DELIVERY_ID'   => $deliveryId,
                'DELIVERY_NAME' => $availableDeliveries[$deliveryId]->getNameWithParent(),
            ]);
            $calcResult = $shipment->calculateDelivery();
            if ($calcResult->isSuccess()) {
                $shipment->setBasePriceDelivery($calcResult->getPrice(), true);
            }
        }

        $paymentCollection = $order->getPaymentCollection();
        $payment = $paymentCollection->createItem();
        $availablePaySystems = PaySystem\Manager::getListWithRestrictions($payment);

        $paySystemId = (int)($payload['paySystemId'] ?? 0);
        if (!isset($availablePaySystems[$paySystemId]) && !empty($availablePaySystems)) {
            $firstPaySystem = reset($availablePaySystems);
            $paySystemId = (int)$firstPaySystem['ID'];
        }

        if ($paySystemId > 0 && isset($availablePaySystems[$paySystemId])) {
            $payment->setFields([
                'PAY_SYSTEM_ID'   => $paySystemId,
                'PAY_SYSTEM_NAME' => $availablePaySystems[$paySystemId]['NAME'],
                'SUM'             => $order->getPrice(),
            ]);
        }

        return $order;
    }

    private function buildOrderState(array $payload): array
    {
        $siteId = Context::getCurrent()->getSite();
        $order = $this->createVirtualOrder($payload);

        $items = [];
        foreach ($order->getBasket() as $basketItem) {
            $items[] = [
                'id'       => $basketItem->getId(),
                'name'     => $basketItem->getField('NAME'),
                'quantity' => $basketItem->getQuantity(),
                'price'    => $basketItem->getPrice(),
                'sum'      => $basketItem->getFinalPrice(),
            ];
        }

        $properties = [];
        $propValues = [];
        $locationName = '';
        foreach ($order->getPropertyCollection() as $property) {
            $p = $property->getProperty();
            $code = (string)$p['CODE'];
            $val = (string)($payload['properties'][$code] ?? $property->getValue() ?? '');
            $propValues[$code] = $val;

            if ($p['IS_LOCATION'] === 'Y' && $val !== '') {
                $locationName = $this->getFullLocationName($val);
            }

            $properties[] = [
                'id'         => (int)$p['ID'],
                'code'       => $code,
                'name'       => $p['NAME'],
                'type'       => $p['TYPE'],
                'required'   => $p['REQUIRED'] === 'Y',
                'isLocation' => $p['IS_LOCATION'] === 'Y',
            ];
        }

        $deliveries = [];
        $shipment = null;
        foreach ($order->getShipmentCollection() as $s) {
            if (!$s->isSystem()) {
                $shipment = $s;
                break;
            }
        }

        $selectedDeliveryId = $shipment ? (int)$shipment->getDeliveryId() : 0;
        if ($shipment) {
            foreach (Delivery\Services\Manager::getRestrictedObjectsList($shipment) as $srv) {
                $dId = (int)$srv->getId();
                $cloneShipment = clone $shipment;
                $cloneShipment->setField('DELIVERY_ID', $dId);
                $calc = $cloneShipment->calculateDelivery();

                $deliveries[] = [
                    'id'          => $dId,
                    'name'        => $srv->getNameWithParent(),
                    'description' => $srv->getDescription(),
                    'price'       => $calc->isSuccess() ? $calc->getPrice() : 0,
                    'period'      => $calc->isSuccess() ? $calc->getPeriodDescription() : '',
                ];
            }
        }

        $paySystems = [];
        $selectedPaySystemId = 0;
        foreach ($order->getPaymentCollection() as $payment) {
            $selectedPaySystemId = (int)$payment->getPaymentSystemId();
            foreach (PaySystem\Manager::getListWithRestrictions($payment) as $ps) {
                $paySystems[] = [
                    'id'          => (int)$ps['ID'],
                    'name'        => $ps['NAME'],
                    'description' => $ps['DESCRIPTION'] ?? '',
                ];
            }
            break;
        }

        return [
            'personTypes'         => array_values($this->getPersonTypes($siteId)),
            'personTypeId'        => $order->getPersonTypeId(),
            'properties'          => $properties,
            'propValues'          => $propValues,
            'locationName'        => $locationName,
            'deliveries'          => $deliveries,
            'selectedDeliveryId'  => $selectedDeliveryId,
            'paySystems'          => $paySystems,
            'selectedPaySystemId' => $selectedPaySystemId,
            'items'               => $items,
            'totals'              => [
                'basketBase'    => $order->getBasket()->getBasePrice(),
                'basketPrice'   => $order->getBasket()->getPrice(),
                'discountPrice' => $order->getDiscountPrice(),
                'deliveryPrice' => $order->getDeliveryPrice(),
                'orderPrice'    => $order->getPrice(),
            ],
        ];
    }

    private function getCurrentBasket(): Basket
    {
        $siteId = Context::getCurrent()->getSite();
        return Basket::loadItemsForFUser(Fuser::getId(), $siteId)->getOrderableItems();
    }

    private function getPersonTypes(string $siteId): array
    {
        $result = [];
        $res = PersonTypeTable::getList([
            'filter' => ['=LID' => $siteId, '=ACTIVE' => 'Y'],
            'order'  => ['SORT' => 'ASC'],
        ]);
        while ($row = $res->fetch()) {
            $result[(int)$row['ID']] = [
                'id'   => (int)$row['ID'],
                'name' => $row['NAME'],
            ];
        }
        return $result;
    }

    private function getDefaultPersonTypeId(): int
    {
        $types = $this->getPersonTypes(Context::getCurrent()->getSite());
        return !empty($types) ? (int)array_key_first($types) : 1;
    }

    private function getAuthorizedUserProps(): array
    {
        global $USER;
        if (!$USER || !$USER->IsAuthorized()) {
            return [];
        }
        $user = \CUser::GetByID((int)$USER->GetID())->Fetch();
        if (!$user) {
            return [];
        }
        return [
            'FIO'     => trim(($user['LAST_NAME'] ?? '') . ' ' . ($user['NAME'] ?? '') . ' ' . ($user['SECOND_NAME'] ?? '')),
            'EMAIL'   => (string)($user['EMAIL'] ?? ''),
            'PHONE'   => (string)($user['PERSONAL_PHONE'] ?: $user['PERSONAL_MOBILE'] ?? ''),
            'ADDRESS' => (string)($user['PERSONAL_STREET'] ?? ''),
        ];
    }

    private function getFullLocationName(string $code): string
    {
        if ($code === '') {
            return '';
        }
        $res = LocationTable::getPathToNodeByCode($code, [
            'filter' => ['=NAME.LANGUAGE_ID' => LANGUAGE_ID],
            'select' => ['LNAME' => 'NAME.NAME'],
        ]);
        $parts = [];
        while ($row = $res->fetch()) {
            $parts[] = $row['LNAME'];
        }
        return implode(', ', array_reverse($parts));
    }
}
