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
use Bitrix\Main\Web\HttpClient;
use Bitrix\Main\Web\Json;
use Bitrix\Sale\Basket;
use Bitrix\Sale\Delivery;
use Bitrix\Sale\DiscountCouponsManager;
use Bitrix\Sale\Fuser;
use Bitrix\Sale\Internals\PersonTypeTable;
use Bitrix\Sale\Location\ExternalTable;
use Bitrix\Sale\Location\LocationTable;
use Bitrix\Sale\Order;
use Bitrix\Sale\OrderUserProperties;
use Bitrix\Sale\Payment;
use Bitrix\Sale\Internals\UserPropsTable;
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
            'searchLocations' => [
                'prefilters' => [
                    new ActionFilter\HttpMethod([ActionFilter\HttpMethod::METHOD_POST]),
                    new ActionFilter\Csrf(),
                ],
            ],
            'suggestDadata' => [
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

    protected function listKeysSignedParameters(): array
    {
        return [
            'DEFAULT_LOCATION',
            'DADATA_API_KEY',
            'AUTO_INIT_PAYMENT',
            'AUTO_REDIRECT_PAYMENT',
        ];
    }

    public function executeComponent(): void
    {
        if (!Loader::includeModule('sale') || !Loader::includeModule('catalog')) {
            ShowError('Модули интернет-магазина (sale, catalog) не установлены.');
            return;
        }

        $basket = $this->getCurrentBasket();
        if ($basket->isEmpty()) {
            $this->arResult['IS_EMPTY'] = true;
            $this->arResult['PATH_TO_BASKET'] = (string)($this->arParams['PATH_TO_BASKET'] ?? '/personal/cart/');
            $this->includeComponentTemplate();
            return;
        }

        $this->arResult['IS_EMPTY'] = false;
        $this->arResult['SIGNED_PARAMS'] = $this->getSignedParameters();
        $this->arResult['PATH_TO_PERSONAL'] = (string)($this->arParams['PATH_TO_PERSONAL'] ?? '/personal/orders/');
        $this->arResult['DADATA_ENABLED'] = trim((string)($this->arParams['DADATA_API_KEY'] ?? '')) !== '';
        $this->arResult['AUTO_REDIRECT_PAYMENT'] = ($this->arParams['AUTO_REDIRECT_PAYMENT'] ?? 'Y') === 'Y';

        $initialProperties = $this->getUserProfileDefaults();
        $this->arResult['INITIAL_STATE'] = $this->buildOrderState([
            'personTypeId' => $this->getDefaultPersonTypeId(),
            'properties'   => $initialProperties,
            'deliveryId'   => 0,
            'paySystemId'  => 0,
            'comment'      => '',
            'coupon'       => '',
        ]);

        $this->includeComponentTemplate();
    }

    public function recalculateAction(array $payload = []): array
    {
        Loader::includeModule('sale');
        Loader::includeModule('catalog');

        $coupon = trim((string)($payload['coupon'] ?? ''));
        if ($coupon !== '') {
            DiscountCouponsManager::init();
            DiscountCouponsManager::add($coupon);
        } elseif (isset($payload['clearCoupon']) && $payload['clearCoupon'] === true) {
            DiscountCouponsManager::init();
            DiscountCouponsManager::clear(true);
        }

        return $this->buildOrderState($payload);
    }

    public function searchLocationsAction(string $query = ''): array
    {
        Loader::includeModule('sale');
        $query = trim($query);
        if (mb_strlen($query) < 2) {
            return ['items' => []];
        }

        $items = [];
        $res = LocationTable::getList([
            'filter' => [
                '=NAME.LANGUAGE_ID' => LANGUAGE_ID,
                '%NAME.NAME'        => $query,
                '=TYPE.CODE'        => ['CITY', 'VILLAGE', 'TOWN'],
            ],
            'select' => [
                'ID',
                'CODE',
                'CITY_NAME'   => 'NAME.NAME',
                'PARENT_NAME' => 'PARENT.NAME.NAME',
            ],
            'limit' => 10,
            'order' => ['SORT' => 'ASC', 'NAME.NAME' => 'ASC'],
        ]);

        while ($row = $res->fetch()) {
            $label = $row['CITY_NAME'];
            if (!empty($row['PARENT_NAME']) && $row['PARENT_NAME'] !== $row['CITY_NAME']) {
                $label .= ' (' . $row['PARENT_NAME'] . ')';
            }
            $items[] = [
                'code'  => $row['CODE'],
                'label' => $label,
            ];
        }

        return ['items' => $items];
    }

    public function suggestDadataAction(string $type = 'address', string $query = ''): array
    {
        Loader::includeModule('sale');

        $apiKey = trim((string)($this->arParams['DADATA_API_KEY'] ?? ''));
        $query = trim($query);

        if ($apiKey === '' || mb_strlen($query) < 2) {
            return ['items' => []];
        }

        if (mb_strlen($query) > 120) {
            return ['items' => []];
        }

        if (!in_array($type, ['address', 'party'], true)) {
            $type = 'address';
        }

        $endpoint = $type === 'party'
            ? 'https://suggestions.dadata.ru/suggestions/api/4_1/rs/suggest/party'
            : 'https://suggestions.dadata.ru/suggestions/api/4_1/rs/suggest/address';

        $http = new HttpClient([
            'socketTimeout' => 5,
            'streamTimeout' => 5,
        ]);
        $http->setHeader('Content-Type', 'application/json', true);
        $http->setHeader('Accept', 'application/json', true);
        $http->setHeader('Authorization', 'Token ' . $apiKey, true);

        $rawResponse = $http->post($endpoint, Json::encode([
            'query' => $query,
            'count' => 7,
        ]));

        if ($rawResponse === false || $http->getStatus() !== 200) {
            return ['items' => []];
        }

        try {
            $decoded = Json::decode($rawResponse);
        } catch (\Throwable $e) {
            return ['items' => []];
        }

        $items = [];
        foreach (($decoded['suggestions'] ?? []) as $suggestion) {
            $data = $suggestion['data'] ?? [];

            if ($type === 'party') {
                $items[] = [
                    'value'   => (string)($suggestion['value'] ?? ''),
                    'name'    => (string)($data['name']['short_with_opf'] ?? $suggestion['value'] ?? ''),
                    'inn'     => (string)($data['inn'] ?? ''),
                    'kpp'     => (string)($data['kpp'] ?? ''),
                    'ogrn'    => (string)($data['ogrn'] ?? ''),
                    'address' => (string)($data['address']['unrestricted_value'] ?? $data['address']['value'] ?? ''),
                ];
            } else {
                $kladrId = (string)(
                    ($data['settlement_kladr_id'] ?? '')
                    ?: ($data['city_kladr_id'] ?? '')
                    ?: ($data['region_kladr_id'] ?? '')
                );
                $cityName = (string)(($data['settlement'] ?? '') ?: ($data['city'] ?? ''));
                $items[] = [
                    'value'        => (string)($suggestion['value'] ?? ''),
                    'postalCode'   => (string)($data['postal_code'] ?? ''),
                    'city'         => $cityName,
                    'locationCode' => $this->resolveBitrixLocationByKladr($kladrId),
                ];
            }
        }

        return ['items' => $items];
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
            }

            if ($val !== '' && ($propData['IS_EMAIL'] === 'Y' || $code === 'EMAIL') && !check_email($val)) {
                $this->errorCollection->setError(
                    new Error('Укажите корректный E-mail', 'PROP_' . $code)
                );
            }
        }

        if (!$this->errorCollection->isEmpty()) {
            return null;
        }

        $finalResult = $order->doFinalAction(true);
        if (!$finalResult->isSuccess()) {
            foreach ($finalResult->getErrors() as $error) {
                $this->errorCollection->setError($error);
            }
            return null;
        }

        $saveResult = $order->save();

        if (!$saveResult->isSuccess()) {
            foreach ($saveResult->getErrors() as $err) {
                $this->errorCollection->setError($err);
            }
            return null;
        }

        $paymentData = null;
        if (($this->arParams['AUTO_INIT_PAYMENT'] ?? 'Y') === 'Y') {
            $paymentData = $this->initiateOrderPayment($order);
        }

        return [
            'orderId'       => $order->getId(),
            'accountNumber' => $order->getField('ACCOUNT_NUMBER'),
            'payment'       => $paymentData,
        ];
    }

    /**
     * Инициализация платёжной системы после сохранения заказа
     * Извлекает URL редиректа (ЮKassa, Сбер, Т-Банк и др.) или HTML-форму/виджет эквайринга
     */
    private function initiateOrderPayment(Order $order): ?array
    {
        /** @var Payment|null $targetPayment */
        $targetPayment = null;
        foreach ($order->getPaymentCollection() as $payment) {
            if (!$payment->isInner() && !$payment->isPaid()) {
                $targetPayment = $payment;
                break;
            }
        }

        if (!$targetPayment) {
            return null;
        }

        $paySystemId = (int)$targetPayment->getPaymentSystemId();
        if ($paySystemId <= 0) {
            return null;
        }

        $service = PaySystem\Manager::getObjectById($paySystemId);
        if (!$service) {
            return null;
        }

        $isCash = $service->isCash();
        $resultData = [
            'paySystemId'   => $paySystemId,
            'paySystemName' => $targetPayment->getPaymentSystemName(),
            'isCash'        => $isCash,
            'paymentUrl'    => '',
            'html'          => '',
            'error'         => '',
        ];

        if ($isCash) {
            return $resultData;
        }

        try {
            $request = Context::getCurrent()->getRequest();
            $initResult = $service->initiatePay($targetPayment, $request, PaySystem\BaseServiceHandler::STRING);

            if (!$initResult->isSuccess()) {
                $resultData['error'] = implode('; ', $initResult->getErrorMessages());
                return $resultData;
            }

            if (method_exists($initResult, 'getPaymentUrl')) {
                $paymentUrl = (string)$initResult->getPaymentUrl();
                if ($paymentUrl !== '') {
                    $resultData['paymentUrl'] = $paymentUrl;
                }
            }

            $templateHtml = (string)$initResult->getTemplate();
            if ($templateHtml !== '') {
                $resultData['html'] = $templateHtml;

                // Если шлюз вернул HTML с авто-редиректом или прямую ссылку в атрибуте action/href
                if ($resultData['paymentUrl'] === '' && preg_match('/(?:window\.location(?:\.href)?\s*=\s*|location\.replace\()["\'](https?:\/\/[^"\']+)["\']/i', $templateHtml, $m)) {
                    $resultData['paymentUrl'] = $m[1];
                }
            }
        } catch (\Throwable $e) {
            $resultData['error'] = $e->getMessage();
        }

        return $resultData;
    }

    private function resolveBitrixLocationByKladr(string $kladrId): string
    {
        if ($kladrId === '') {
            return '';
        }

        $ext = ExternalTable::getList([
            'filter' => [
                '=XML_ID'       => $kladrId,
                '=SERVICE.CODE' => 'KLADR',
            ],
            'select' => ['LOCATION_CODE' => 'LOCATION.CODE'],
            'limit'  => 1,
        ])->fetch();

        return !empty($ext['LOCATION_CODE']) ? (string)$ext['LOCATION_CODE'] : '';
    }

    private function createVirtualOrder(array $payload): Order
    {
        global $USER;
        $siteId = Context::getCurrent()->getSite();
        $userId = ($USER && $USER->IsAuthorized())
            ? (int)$USER->GetID()
            : (int)\CSaleUser::GetAnonymousUserID();

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
        $defaultLocation = (string)($this->arParams['DEFAULT_LOCATION'] ?? '0000073738');
        $profileValues = [];
        $profileId = (int)($payload['profileId'] ?? 0);

        if ($profileId > 0 && $userId > 0 && $userId !== (int)\\CSaleUser::GetAnonymousUserID()
            && OrderUserProperties::checkCorrect($profileId, $personTypeId, $userId)
        ) {
            $profileValues = OrderUserProperties::getProfileValues($profileId);
        }

        foreach ($order->getPropertyCollection() as $property) {
            $propData = $property->getProperty();
            $code = (string)$propData['CODE'];
            $propertyId = (int)$propData['ID'];

            if (array_key_exists($propertyId, $profileValues)) {
                $property->setValue($profileValues[$propertyId]);
            }

            if (array_key_exists($code, $propValues) && $propValues[$code] !== '') {
                $property->setValue($propValues[$code]);
            } elseif ($propData['IS_LOCATION'] === 'Y' && $property->getValue() === null) {
                $property->setValue($defaultLocation);
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
                $locationName = $this->getLocationNameByCode($val);
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
                    'description' => (string)$srv->getDescription(),
                    'price'       => $calc->isSuccess() ? $calc->getPrice() : 0,
                    'period'      => $calc->isSuccess() ? (string)$calc->getPeriodDescription() : '',
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
                    'description' => (string)($ps['DESCRIPTION'] ?? ''),
                ];
            }
            break;
        }

        DiscountCouponsManager::init();
        $appliedCoupons = array_keys(DiscountCouponsManager::get(true, [], true, true));

        return [
            'personTypes'         => array_values($this->getPersonTypes($siteId)),
            'profiles'            => $this->getBuyerProfiles((int)$order->getPersonTypeId()),
            'selectedProfileId'   => (int)($payload['profileId'] ?? 0),
            'personTypeId'        => $order->getPersonTypeId(),
            'properties'          => $properties,
            'propValues'          => $propValues,
            'locationName'        => $locationName,
            'deliveries'          => $deliveries,
            'selectedDeliveryId'  => $selectedDeliveryId,
            'paySystems'          => $paySystems,
            'selectedPaySystemId' => $selectedPaySystemId,
            'items'               => $items,
            'appliedCoupons'      => $appliedCoupons,
            'totals'              => [
                'basketBase'    => $order->getBasket()->getBasePrice(),
                'basketPrice'   => $order->getBasket()->getPrice(),
                'discountPrice' => $order->getDiscountPrice(),
                'deliveryPrice' => $order->getDeliveryPrice(),
                'orderPrice'    => $order->getPrice(),
            ],
        ];
    }

    private function getLocationNameByCode(string $code): string
    {
        $row = LocationTable::getList([
            'filter' => ['=CODE' => $code, '=NAME.LANGUAGE_ID' => LANGUAGE_ID],
            'select' => ['CITY_NAME' => 'NAME.NAME', 'PARENT_NAME' => 'PARENT.NAME.NAME'],
            'limit'  => 1,
        ])->fetch();

        if (!$row) {
            return '';
        }

        $label = (string)$row['CITY_NAME'];
        if (!empty($row['PARENT_NAME']) && $row['PARENT_NAME'] !== $label) {
            $label .= ' (' . $row['PARENT_NAME'] . ')';
        }
        return $label;
    }

    private function getUserProfileDefaults(): array
    {
        global $USER;
        if (!$USER || !$USER->IsAuthorized()) {
            return [];
        }

        $userRow = \CUser::GetByID((int)$USER->GetID())->Fetch();
        if (!$userRow) {
            return [];
        }

        $fio = trim(implode(' ', array_filter([
            $userRow['LAST_NAME'] ?? '',
            $userRow['NAME'] ?? '',
            $userRow['SECOND_NAME'] ?? '',
        ])));

        return array_filter([
            'FIO'     => $fio,
            'EMAIL'   => (string)($userRow['EMAIL'] ?? ''),
            'PHONE'   => (string)(($userRow['PERSONAL_PHONE'] ?? '') ?: ($userRow['PERSONAL_MOBILE'] ?? '')),
            'ADDRESS' => (string)($userRow['PERSONAL_STREET'] ?? ''),
            'ZIP'     => (string)($userRow['PERSONAL_ZIP'] ?? ''),
        ]);
    }

    private function getCurrentBasket(): Basket
    {
        $siteId = Context::getCurrent()->getSite();
        return Basket::loadItemsForFUser(Fuser::getId(), $siteId)->getOrderableItems();
    }

    private function getBuyerProfiles(int $personTypeId): array
    {
        global $USER;

        if (!$USER || !$USER->IsAuthorized() || $personTypeId <= 0) {
            return [];
        }

        $profiles = UserPropsTable::getList([
            'select' => ['ID', 'NAME'],
            'filter' => [
                '=USER_ID' => (int)$USER->GetID(),
                '=PERSON_TYPE_ID' => $personTypeId,
            ],
            'order' => [
                'DATE_UPDATE' => 'DESC',
                'ID' => 'DESC',
            ],
        ]);

        $result = [];
        while ($profile = $profiles->fetch()) {
            $result[] = [
                'id' => (int)$profile['ID'],
                'name' => (string)$profile['NAME'],
            ];
        }

        return $result;
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
}