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
use Bitrix\Main\Config\Option;
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
use Bitrix\Sale\Services\Base\RestrictionManager;

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
            'AUTO_INIT_PAYMENT',
            'AUTO_REDIRECT_PAYMENT',
            'SAVE_USER_PROFILE',
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

        if ($this->getPersonTypes(Context::getCurrent()->getSite()) === []) {
            ShowError('Для текущего сайта не настроены активные типы плательщиков. Проверьте настройки интернет-магазина.');
            return;
        }

        $this->arResult['IS_EMPTY'] = false;
        $this->arResult['SIGNED_PARAMS'] = $this->getSignedParameters();
        $this->arResult['PATH_TO_PERSONAL'] = (string)($this->arParams['PATH_TO_PERSONAL'] ?? '/personal/orders/');
        $this->arResult['DADATA_ENABLED'] = trim((string)Option::get('main', 'codematrix_order_checkout_dadata_api_key', '')) !== '';
        $this->arResult['AUTO_REDIRECT_PAYMENT'] = ($this->arParams['AUTO_REDIRECT_PAYMENT'] ?? 'Y') === 'Y';
        $this->arResult['SAVE_USER_PROFILE_ENABLED'] = ($this->arParams['SAVE_USER_PROFILE'] ?? 'Y') === 'Y';
        global $USER;
        $this->arResult['USER_AUTHORIZED'] = $USER && $USER->IsAuthorized();

        DiscountCouponsManager::init();
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
        $payload = $this->normalizePayload($payload);

        if (!Loader::includeModule('sale') || !Loader::includeModule('catalog')) {
            $this->errorCollection->setError(new Error('Модули интернет-магазина (sale, catalog) не установлены.'));
            return [];
        }

        DiscountCouponsManager::init();
        $coupon = trim((string)($payload['coupon'] ?? ''));
        if ($coupon !== '') {
            // В форме предусмотрен один промокод: новый заменяет ранее применённый.
            DiscountCouponsManager::clear(true);
            DiscountCouponsManager::add($coupon);
        } elseif (isset($payload['clearCoupon']) && $payload['clearCoupon'] === true) {
            DiscountCouponsManager::clear(true);
        }

        return $this->buildOrderState($payload);
    }

    public function searchLocationsAction($query = ''): array
    {
        if (!Loader::includeModule('sale') || !is_scalar($query)) {
            return ['items' => []];
        }

        $query = trim((string)$query);
        if (mb_strlen($query) < 2 || mb_strlen($query) > 120) {
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

    public function suggestDadataAction($type = 'address', $query = ''): array
    {
        if (!Loader::includeModule('sale') || !is_scalar($type) || !is_scalar($query)) {
            return ['items' => []];
        }

        $type = (string)$type;
        $apiKey = trim((string)Option::get('main', 'codematrix_order_checkout_dadata_api_key', ''));
        $query = trim((string)$query);

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

        try {
            $rawResponse = $http->post($endpoint, Json::encode([
                'query' => $query,
                'count' => 7,
            ]));

            if ($rawResponse === false || $http->getStatus() !== 200) {
                return ['items' => []];
            }

            $decoded = Json::decode($rawResponse);
        } catch (\Throwable $exception) {
            // Ошибка внешнего сервиса не должна ломать оформление заказа.
            return ['items' => []];
        }

        if (!is_array($decoded) || !is_array($decoded['suggestions'] ?? null)) {
            return ['items' => []];
        }

        $items = [];
        foreach ($decoded['suggestions'] as $suggestion) {
            if (!is_array($suggestion)) {
                continue;
            }

            $data = is_array($suggestion['data'] ?? null) ? $suggestion['data'] : [];

            if ($type === 'party') {
                $nameData = is_array($data['name'] ?? null) ? $data['name'] : [];
                $addressData = is_array($data['address'] ?? null) ? $data['address'] : [];
                $suggestionValue = is_scalar($suggestion['value'] ?? null)
                    ? (string)$suggestion['value']
                    : '';
                $shortName = is_scalar($nameData['short_with_opf'] ?? null)
                    ? (string)$nameData['short_with_opf']
                    : $suggestionValue;
                $addressValue = $addressData['unrestricted_value'] ?? $addressData['value'] ?? '';
                $addressValue = is_scalar($addressValue) ? (string)$addressValue : '';

                $items[] = [
                    'value'   => $suggestionValue,
                    'name'    => $shortName,
                    'inn'     => is_scalar($data['inn'] ?? null) ? (string)$data['inn'] : '',
                    'kpp'     => is_scalar($data['kpp'] ?? null) ? (string)$data['kpp'] : '',
                    'ogrn'    => is_scalar($data['ogrn'] ?? null) ? (string)$data['ogrn'] : '',
                    'address' => $addressValue,
                ];
            } else {
                $settlementKladrId = is_scalar($data['settlement_kladr_id'] ?? null)
                    ? (string)$data['settlement_kladr_id']
                    : '';
                $cityKladrId = is_scalar($data['city_kladr_id'] ?? null)
                    ? (string)$data['city_kladr_id']
                    : '';
                $regionKladrId = is_scalar($data['region_kladr_id'] ?? null)
                    ? (string)$data['region_kladr_id']
                    : '';
                $kladrId = $settlementKladrId ?: ($cityKladrId ?: $regionKladrId);

                $settlementName = is_scalar($data['settlement'] ?? null) ? (string)$data['settlement'] : '';
                $cityValue = is_scalar($data['city'] ?? null) ? (string)$data['city'] : '';
                $cityName = $settlementName ?: $cityValue;
                $items[] = [
                    'value'        => is_scalar($suggestion['value'] ?? null) ? (string)$suggestion['value'] : '',
                    'postalCode'   => is_scalar($data['postal_code'] ?? null) ? (string)$data['postal_code'] : '',
                    'city'         => $cityName,
                    'locationCode' => $this->resolveBitrixLocationByKladr($kladrId),
                ];
            }
        }

        return ['items' => $items];
    }

    public function createOrderAction(array $payload = []): ?array
    {
        $payload = $this->normalizePayload($payload);

        if (!Loader::includeModule('sale') || !Loader::includeModule('catalog')) {
            $this->errorCollection->setError(new Error('Модули интернет-магазина (sale, catalog) не установлены.'));
            return null;
        }

        DiscountCouponsManager::init();

        $requestId = (string)($payload['requestId'] ?? '');
        if (!preg_match('/^[a-f0-9]{32}$/i', $requestId)) {
            $this->errorCollection->setError(new Error('Не удалось проверить запрос оформления заказа. Обновите страницу и повторите попытку.'));
            return null;
        }

        $fingerprint = $this->getOrderRequestFingerprint($payload);
        $sessionKey = $this->getOrderIdempotencySessionKey();
        $_SESSION['CODEMATRIX_ORDER_CHECKOUT_IDEMPOTENCY'] ??= [];
        $_SESSION['CODEMATRIX_ORDER_CHECKOUT_IDEMPOTENCY'][$sessionKey] ??= [];
        $requests = &$_SESSION['CODEMATRIX_ORDER_CHECKOUT_IDEMPOTENCY'][$sessionKey];

        // После обновления страницы requestId меняется. Не допускаем повтор
        // того же заказа, если предыдущая попытка завершилась неопределённо.
        foreach ($requests as $entry) {
            if (($entry['status'] ?? '') === 'unknown'
                && hash_equals((string)($entry['fingerprint'] ?? ''), $fingerprint)
            ) {
                $this->errorCollection->setError(new Error(
                    'Результат предыдущей попытки оформления не подтверждён. Проверьте список заказов перед повторной отправкой.'
                ));
                return null;
            }
        }

        // Храним только недавние ключи, чтобы сессия не разрасталась.
        $expirationTime = time() - 86400;
        foreach ($requests as $key => $entry) {
            if (($entry['status'] ?? '') !== 'unknown'
                && (int)($entry['updatedAt'] ?? 0) < $expirationTime
            ) {
                unset($requests[$key]);
            }
        }

        if (isset($requests[$requestId])) {
            $entry = $requests[$requestId];
            if (!hash_equals((string)($entry['fingerprint'] ?? ''), $fingerprint)) {
                $this->errorCollection->setError(new Error('Данные заказа изменились. Обновите страницу перед повторным оформлением.'));
                return null;
            }

            if (($entry['status'] ?? '') === 'completed' && (int)($entry['orderId'] ?? 0) > 0) {
                $existingOrder = Order::load((int)$entry['orderId']);
                global $USER;
                $currentUserId = ($USER && $USER->IsAuthorized())
                    ? (int)$USER->GetID()
                    : (int)\CSaleUser::GetAnonymousUserID();

                if ($existingOrder
                    && (int)$existingOrder->getField('USER_ID') === $currentUserId
                ) {
                    return [
                        'orderId'         => $existingOrder->getId(),
                        'accountNumber'   => $existingOrder->getField('ACCOUNT_NUMBER'),
                        'payment'         => null,
                        'alreadyProcessed' => true,
                    ];
                }

                unset($requests[$requestId]);
            } elseif (($entry['status'] ?? '') === 'processing') {
                $this->errorCollection->setError(new Error('Заказ с этим запросом уже обрабатывается. Подождите и обновите страницу.'));
                return null;
            } elseif (($entry['status'] ?? '') === 'unknown') {
                $this->errorCollection->setError(new Error('Не удалось однозначно определить результат предыдущей попытки. Проверьте список заказов перед повторной отправкой.'));
                return null;
            } else {
                unset($requests[$requestId]);
            }
        }

        $basket = $this->getCurrentBasket();
        if ($basket->isEmpty()) {
            $this->errorCollection->setError(new Error('Ваша корзина пуста'));
            return null;
        }

        $order = $this->createVirtualOrder($payload);
        if (!$this->errorCollection->isEmpty()) {
            return null;
        }

        // Не подменяем выбранные покупателем способы доставки и оплаты,
        // если ограничения изменились между пересчётом и созданием заказа.
        $requestedDeliveryId = (int)($payload['deliveryId'] ?? 0);
        if ($requestedDeliveryId > 0) {
            $deliveryFound = false;
            foreach ($order->getShipmentCollection() as $shipment) {
                if ($shipment->isSystem()) {
                    continue;
                }

                $deliveryFound = true;
                if ((int)$shipment->getDeliveryId() !== $requestedDeliveryId) {
                    $this->errorCollection->setError(new Error(
                        'Выбранная служба доставки больше недоступна. Обновите расчёт заказа и выберите доставку повторно.'
                    ));
                    return null;
                }

                try {
                    $deliveryCalculation = $shipment->calculateDelivery();
                } catch (\Throwable $exception) {
                    $this->errorCollection->setError(new Error(
                        'Не удалось рассчитать выбранную доставку. Выберите другой способ доставки.'
                    ));
                    return null;
                }

                if (!$deliveryCalculation->isSuccess()) {
                    foreach ($deliveryCalculation->getErrors() as $error) {
                        $this->errorCollection->setError($error);
                    }
                    if ($this->errorCollection->isEmpty()) {
                        $this->errorCollection->setError(new Error(
                            'Не удалось рассчитать стоимость выбранной доставки. Выберите другой способ доставки.'
                        ));
                    }
                    return null;
                }

                $priceResult = $shipment->setBasePriceDelivery($deliveryCalculation->getPrice());
                if (!$priceResult->isSuccess()) {
                    foreach ($priceResult->getErrors() as $error) {
                        $this->errorCollection->setError($error);
                    }
                    return null;
                }
                break;
            }

            if (!$deliveryFound) {
                $this->errorCollection->setError(new Error(
                    'Не удалось назначить выбранную службу доставки. Обновите расчёт заказа.'
                ));
                return null;
            }
        }

        $requestedPaySystemId = (int)($payload['paySystemId'] ?? 0);
        if ($requestedPaySystemId > 0) {
            $actualPaySystemId = 0;
            foreach ($order->getPaymentCollection() as $payment) {
                if (!$payment->isInner()) {
                    $actualPaySystemId = (int)$payment->getPaymentSystemId();
                    break;
                }
            }

            if ($actualPaySystemId !== $requestedPaySystemId) {
                $this->errorCollection->setError(new Error(
                    'Выбранный способ оплаты больше недоступен. Обновите расчёт заказа и выберите оплату повторно.'
                ));
                return null;
            }
        }

        foreach ($order->getPropertyCollection() as $property) {
            $propData = $property->getProperty();
            $code = (string)$propData['CODE'];
            $value = $property->getValue();

            // Проверяем итоговое значение свойства после применения профиля покупателя.
            if (is_array($value)) {
                $value = implode(', ', array_map('strval', $value));
            }
            $val = trim((string)($value ?? ''));

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

        // Повторно проверяем ограничения доставки уже после пересчёта скидок и налогов.
        if ($requestedDeliveryId > 0) {
            foreach ($order->getShipmentCollection() as $shipment) {
                if ($shipment->isSystem()) {
                    continue;
                }

                $availableDeliveries = Delivery\Services\Manager::getRestrictedObjectsList($shipment);
                if (!isset($availableDeliveries[$requestedDeliveryId])) {
                    $this->errorCollection->setError(new Error(
                        'Выбранная служба доставки недоступна для итоговой суммы заказа. Выберите доставку повторно.'
                    ));
                    return null;
                }
                break;
            }
        }

        // Финальная сумма оплаты должна совпадать с итоговой суммой заказа.
        $externalPaymentFound = false;
        foreach ($order->getPaymentCollection() as $payment) {
            if ($payment->isInner()) {
                continue;
            }

            $externalPaymentFound = true;
            $paySystemId = (int)$payment->getPaymentSystemId();
            if ($paySystemId <= 0) {
                $this->errorCollection->setError(new Error(
                    'Для сайта не настроен доступный способ оплаты. Обратитесь к администратору магазина.'
                ));
                return null;
            }

            $sumResult = $payment->setField('SUM', $order->getPrice());
            if (!$sumResult->isSuccess()) {
                foreach ($sumResult->getErrors() as $error) {
                    $this->errorCollection->setError($error);
                }
                return null;
            }

            // Ограничения по сумме должны проверяться уже по итоговой сумме оплаты.
            $availablePaySystems = PaySystem\Manager::getListWithRestrictions(
                $payment,
                RestrictionManager::MODE_CLIENT
            );
            if (!isset($availablePaySystems[$paySystemId])) {
                $this->errorCollection->setError(new Error('Выбранный способ оплаты недоступен для этого заказа.'));
                return null;
            }
        }

        if (!$externalPaymentFound) {
            $this->errorCollection->setError(new Error(
                'Не удалось создать платёж для заказа. Проверьте настройки способов оплаты.'
            ));
            return null;
        }

        // Резервируем ключ до сохранения. При стандартной PHP session handler
        // параллельные AJAX-запросы одной сессии выполняются последовательно.
        $requests[$requestId] = [
            'fingerprint' => $fingerprint,
            'status' => 'processing',
            'orderId' => 0,
            'updatedAt' => time(),
        ];

        try {
            $saveResult = $order->save();
        } catch (\Throwable $exception) {
            // После исключения нельзя достоверно знать, успела ли БД сохранить заказ.
            // Блокируем повторную отправку с тем же ключом, чтобы не создать дубль.
            $requests[$requestId] = [
                'fingerprint' => $fingerprint,
                'status' => 'unknown',
                'orderId' => 0,
                'updatedAt' => time(),
            ];
            $this->errorCollection->setError(new Error(
                'Не удалось подтвердить результат сохранения заказа. Проверьте список заказов перед повторной отправкой.'
            ));
            return null;
        }

        if (!$saveResult->isSuccess()) {
            unset($requests[$requestId]);
            foreach ($saveResult->getErrors() as $err) {
                $this->errorCollection->setError($err);
            }
            return null;
        }

        // Фиксируем заказ сразу после успешного сохранения, до обращения к эквайрингу.
        $requests[$requestId] = [
            'fingerprint' => $fingerprint,
            'status' => 'completed',
            'orderId' => (int)$order->getId(),
            'updatedAt' => time(),
        ];

        // Профиль сохраняется только после успешной записи заказа и явного согласия.
        $savedProfileId = $this->saveBuyerProfileAfterOrder($order, $payload);

        if (count($requests) > 20) {
            // Не удаляем записи с неопределённым результатом сохранения:
            // иначе повторная отправка может создать дублирующий заказ.
            $knownRequests = array_filter(
                $requests,
                static fn(array $entry): bool => ($entry['status'] ?? '') !== 'unknown'
            );
            uasort($knownRequests, static function (array $a, array $b): int {
                return (int)($a['updatedAt'] ?? 0) <=> (int)($b['updatedAt'] ?? 0);
            });
            $keepUnknown = array_filter(
                $requests,
                static fn(array $entry): bool => ($entry['status'] ?? '') === 'unknown'
            );
            $requests = $keepUnknown + array_slice($knownRequests, -20, null, true);
        }

        $paymentData = null;
        if (($this->arParams['AUTO_INIT_PAYMENT'] ?? 'Y') === 'Y') {
            $paymentData = $this->initiateOrderPayment($order);
        }

        return [
            'orderId'          => $order->getId(),
            'accountNumber'    => $order->getField('ACCOUNT_NUMBER'),
            'payment'          => $paymentData,
            'alreadyProcessed' => false,
            'savedProfileId' => $savedProfileId,
        ];
    }

    /**
     * Сохраняет разрешённые свойства заказа в профиль текущего покупателя.
     */
    private function saveBuyerProfileAfterOrder(Order $order, array $payload): int
    {
        global $USER;

        if (($this->arParams['SAVE_USER_PROFILE'] ?? 'Y') !== 'Y'
            || ($payload['saveProfile'] ?? false) !== true
            || !$USER
            || !$USER->IsAuthorized()
        ) {
            return 0;
        }

        $userId = (int)$USER->GetID();
        $personTypeId = (int)$order->getPersonTypeId();
        if ($userId <= 0 || $personTypeId <= 0) {
            return 0;
        }

        $profileData = [];
        foreach ($order->getPropertyCollection() as $property) {
            $propertyData = $property->getProperty();
            if (($propertyData['ACTIVE'] ?? 'Y') !== 'Y'
                || ($propertyData['USER_PROPS'] ?? 'N') !== 'Y'
            ) {
                continue;
            }

            $value = $property->getValue();
            if ($value === null || $value === '') {
                continue;
            }
            $profileData[(int)$propertyData['ID']] = $value;
        }

        if ($profileData === []) {
            return 0;
        }

        $profileId = (int)($payload['profileId'] ?? 0);
        if ($profileId <= 0 || !OrderUserProperties::checkCorrect($profileId, $personTypeId, $userId)) {
            $profileId = 0;
        }

        $profileName = trim((string)($payload['profileName'] ?? ''));
        if ($profileName === '') {
            $profileName = 'Профиль покупателя';
        }
        $profileName = mb_substr($profileName, 0, 255);

        $errors = [];
        try {
            $savedProfileId = \CSaleOrderUserProps::DoSaveUserProfile(
                $userId,
                $profileId,
                $profileName,
                $personTypeId,
                $profileData,
                $errors
            );
        } catch (\Throwable $exception) {
            return 0;
        }

        return $savedProfileId === false ? 0 : (int)$savedProfileId;
    }

    private function getOrderRequestFingerprint(array $payload): string
    {
        unset($payload['requestId']);
        $normalize = static function (&$value) use (&$normalize): void {
            if (!is_array($value)) {
                return;
            }

            foreach ($value as &$item) {
                $normalize($item);
            }
            unset($item);

            if ($value !== [] && array_keys($value) !== range(0, count($value) - 1)) {
                ksort($value);
            }
        };
        $normalize($payload);

        return hash('sha256', Json::encode($payload));
    }

    /**
     * Изолирует ключи идемпотентности по сайту, пользователю и Fuser.
     */
    private function getOrderIdempotencySessionKey(): string
    {
        global $USER;
        $userId = ($USER && $USER->IsAuthorized())
            ? (int)$USER->GetID()
            : (int)\CSaleUser::GetAnonymousUserID();

        return hash('sha256', implode(':', [
            Context::getCurrent()->getSite(),
            $userId,
            Fuser::getId(),
        ]));
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
            return [
                'paySystemId' => 0,
                'paySystemName' => '',
                'isCash' => false,
                'paymentUrl' => '',
                'html' => '',
                'error' => 'Заказ сохранён, но не удалось определить платёж для оплаты. Обратитесь к администратору магазина.',
            ];
        }

        $paySystemId = (int)$targetPayment->getPaymentSystemId();
        if ($paySystemId <= 0) {
            return [
                'paySystemId' => 0,
                'paySystemName' => '',
                'isCash' => false,
                'paymentUrl' => '',
                'html' => '',
                'error' => 'Заказ сохранён, но для него не назначена платёжная система. Обратитесь к администратору магазина.',
            ];
        }

        try {
            $service = $targetPayment->getPaySystem();
            if (!$service) {
                return [
                    'paySystemId' => $paySystemId,
                    'paySystemName' => '',
                    'isCash' => false,
                    'paymentUrl' => '',
                    'html' => '',
                    'error' => 'Заказ сохранён, но платёжный обработчик недоступен. Обратитесь к администратору магазина.',
                ];
            }

            $isCash = $service->isCash();
            $paySystemName = $targetPayment->getPaymentSystemName();
        } catch (\Throwable $exception) {
            return [
                'paySystemId' => $paySystemId,
                'paySystemName' => '',
                'isCash' => false,
                'paymentUrl' => '',
                'html' => '',
                'error' => 'Заказ сохранён, но не удалось проверить платёжный обработчик. Обратитесь к администратору магазина.',
            ];
        }

        $resultData = [
            'paySystemId'   => $paySystemId,
            'paySystemName' => $paySystemName,
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
                // Не передаём покупателю внутренние сообщения платёжного обработчика.
                $resultData['error'] = 'Платёжный обработчик не смог начать оплату. Заказ сохранён; повторите попытку из личного кабинета или обратитесь к администратору.';

                return $resultData;
            }

            if (method_exists($initResult, 'getPaymentUrl')) {
                $paymentUrl = trim((string)$initResult->getPaymentUrl());
                if ($paymentUrl !== '') {
                    if ($this->isSafePaymentUrl($paymentUrl)) {
                        $resultData['paymentUrl'] = $paymentUrl;
                    } else {
                        $resultData['error'] = 'Платёжный обработчик вернул некорректную ссылку.';
                    }
                }
            }

            $templateHtml = (string)$initResult->getTemplate();
            if ($templateHtml !== '') {
                $resultData['html'] = $templateHtml;

                // Если шлюз вернул HTML с авто-редиректом или прямую ссылку в атрибуте action/href
                if ($resultData['paymentUrl'] === '' && preg_match('/(?:window\.location(?:\.href)?\s*=\s*|location\.replace\()["\'](https?:\/\/[^"\']+)["\']/i', $templateHtml, $m)) {
                    if ($this->isSafePaymentUrl($m[1])) {
                        $resultData['paymentUrl'] = $m[1];
                    }
                }
            }
            if ($resultData['paymentUrl'] === '' && $resultData['html'] === '' && $resultData['error'] === '') {
                $resultData['error'] = 'Платёжный обработчик не вернул ссылку или форму для оплаты.';
            }
        } catch (\Throwable $exception) {
            $resultData['error'] = 'Не удалось подготовить форму оплаты. Заказ сохранён, обратитесь к администратору магазина.';
        }

        return $resultData;
    }

    /**
     * Проверяет ссылку, возвращённую платёжным обработчиком.
     */
    private function isSafePaymentUrl(string $url): bool
    {
        if ($url === ''
            || preg_match('/[\\x00-\\x20\\x7F]/', $url)
            || strpos($url, '\\') !== false
        ) {
            return false;
        }

        $parts = parse_url($url);
        if (!is_array($parts)) {
            return false;
        }

        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        if (in_array($scheme, ['http', 'https'], true)) {
            return !empty($parts['host'])
                && !isset($parts['user'])
                && !isset($parts['pass']);
        }

        return $scheme === ''
            && !str_starts_with($url, '//');
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

    /**
     * Нормализует данные AJAX до обращения к свойствам заказа и API Sale.
     * Массивы допустимы только как плоские наборы значений свойств.
     */
    private function normalizePayload(array $payload): array
    {
        foreach (['personTypeId', 'deliveryId', 'paySystemId', 'profileId'] as $field) {
            $value = $payload[$field] ?? 0;
            $payload[$field] = is_scalar($value) && is_numeric($value) ? (int)$value : 0;
        }

        foreach (['coupon', 'comment', 'profileName', 'requestId'] as $field) {
            $value = $payload[$field] ?? '';
            $payload[$field] = is_scalar($value) ? trim((string)$value) : '';
        }

        $payload['saveProfile'] = ($payload['saveProfile'] ?? false) === true;
        $payload['clearCoupon'] = ($payload['clearCoupon'] ?? false) === true;

        $properties = $payload['properties'] ?? [];
        if (!is_array($properties)) {
            $properties = [];
        }

        $normalizedProperties = [];
        foreach ($properties as $code => $value) {
            if (!is_string($code) && !is_int($code)) {
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $normalizedProperties[$code] = $value;
                continue;
            }

            if (!is_array($value)) {
                continue;
            }

            $values = [];
            foreach ($value as $item) {
                if (is_scalar($item) || $item === null) {
                    $values[] = $item;
                }
            }
            $normalizedProperties[$code] = $values;
        }

        $payload['properties'] = $normalizedProperties;

        return $payload;
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

        $personTypeResult = $order->setPersonTypeId($personTypeId);
        if (!$personTypeResult->isSuccess()) {
            foreach ($personTypeResult->getErrors() as $error) {
                $this->errorCollection->setError($error);
            }
            return $order;
        }

        foreach ([
            $order->setField('CURRENCY', 'RUB'),
            $order->setField('USER_DESCRIPTION', trim((string)($payload['comment'] ?? ''))),
        ] as $fieldResult) {
            if (!$fieldResult->isSuccess()) {
                foreach ($fieldResult->getErrors() as $error) {
                    $this->errorCollection->setError($error);
                }
                return $order;
            }
        }

        $basket = $this->getCurrentBasket();
        $basketResult = $order->setBasket($basket);
        if (!$basketResult->isSuccess()) {
            foreach ($basketResult->getErrors() as $error) {
                $this->errorCollection->setError($error);
            }
            return $order;
        }

        $propValues = is_array($payload['properties'] ?? null) ? $payload['properties'] : [];
        $defaultLocation = (string)($this->arParams['DEFAULT_LOCATION'] ?? '0000073738');
        $profileValues = [];
        $profileId = (int)($payload['profileId'] ?? 0);

        if ($profileId > 0 && $userId > 0 && $userId !== (int)\CSaleUser::GetAnonymousUserID()
            && OrderUserProperties::checkCorrect($profileId, $personTypeId, $userId)
        ) {
            $profileValues = OrderUserProperties::getProfileValues($profileId);
        }

        foreach ($order->getPropertyCollection() as $property) {
            $propData = $property->getProperty();
            $code = (string)$propData['CODE'];
            $propertyId = (int)$propData['ID'];

            if (array_key_exists($propertyId, $profileValues)) {
                $valueResult = $property->setValue($profileValues[$propertyId]);
                if (!$valueResult->isSuccess()) {
                    foreach ($valueResult->getErrors() as $error) {
                        $this->errorCollection->setError($error);
                    }
                    return $order;
                }
            }

            if (array_key_exists($code, $propValues)) {
                // Пустое значение тоже является явным вводом покупателя:
                // оно должно очищать значение из профиля, а не оставлять его.
                $valueResult = $property->setValue($propValues[$code]);
            } elseif ($propData['IS_LOCATION'] === 'Y' && $property->getValue() === null) {
                $valueResult = $property->setValue($defaultLocation);
            } else {
                $valueResult = null;
            }

            if ($valueResult && !$valueResult->isSuccess()) {
                foreach ($valueResult->getErrors() as $error) {
                    $this->errorCollection->setError($error);
                }
                return $order;
            }
        }

        $shipmentCollection = $order->getShipmentCollection();
        $shipment = $shipmentCollection->createItem();
        $shipmentItemCollection = $shipment->getShipmentItemCollection();
        foreach ($basket as $basketItem) {
            $item = $shipmentItemCollection->createItem($basketItem);
            if (!$item) {
                $this->errorCollection->setError(new Error(
                    'Не удалось добавить товар в состав отгрузки. Обновите корзину и повторите попытку.'
                ));
                return $order;
            }

            $quantityResult = $item->setQuantity($basketItem->getQuantity());
            if (!$quantityResult->isSuccess()) {
                foreach ($quantityResult->getErrors() as $error) {
                    $this->errorCollection->setError($error);
                }
                return $order;
            }
        }

        $availableDeliveries = Delivery\Services\Manager::getRestrictedObjectsList($shipment);
        $deliveryId = (int)($payload['deliveryId'] ?? 0);
        $deliveryService = $deliveryId > 0
            ? Delivery\Services\Manager::getObjectById($deliveryId)
            : null;

        if (!$deliveryService && !empty($availableDeliveries)) {
            $deliveryService = reset($availableDeliveries);
            $deliveryId = (int)$deliveryService->getId();
        }

        if ($deliveryService) {
            $shipment->setDeliveryService($deliveryService);
            try {
                $calcResult = Delivery\Services\Manager::calculateDeliveryPrice(
                    $shipment,
                    $deliveryId,
                    $shipment->getExtraServices()
                );
            } catch (\Throwable $exception) {
                $calcResult = null;
            }

            if (!$calcResult || !$calcResult->isSuccess()) {
                if ($calcResult) {
                    foreach ($calcResult->getErrors() as $error) {
                        $this->errorCollection->setError($error);
                    }
                }
                if ($this->errorCollection->isEmpty()) {
                    $this->errorCollection->setError(new Error(
                        'Не удалось рассчитать стоимость выбранной службы доставки.'
                    ));
                }
            } else {
                $priceResult = $shipment->setBasePriceDelivery($calcResult->getPrice());
                if (!$priceResult->isSuccess()) {
                    foreach ($priceResult->getErrors() as $error) {
                        $this->errorCollection->setError($error);
                    }
                }
            }
        }

        $paymentCollection = $order->getPaymentCollection();
        $payment = $paymentCollection->createItem();
        $payment->setField('SUM', $order->getPrice());
        $availablePaySystems = PaySystem\Manager::getListWithRestrictions($payment, RestrictionManager::MODE_CLIENT);

        $paySystemId = (int)($payload['paySystemId'] ?? 0);
        if ($paySystemId > 0) {
            // Сначала сохраняем явно выбранный сервис. Его ограничения
            // проверяются после doFinalAction(), когда сумма уже окончательная.
            $service = PaySystem\Manager::getObjectById($paySystemId);
            if ($service) {
                $payment->setPaySystemService($service);
            }
        } elseif (!empty($availablePaySystems)) {
            $firstPaySystem = reset($availablePaySystems);
            $service = PaySystem\Manager::getObjectById((int)$firstPaySystem['ID']);
            if ($service) {
                $payment->setPaySystemService($service);
            }
        }

        return $order;
    }

    private function buildOrderState(array $payload): array
    {
        $siteId = Context::getCurrent()->getSite();
        $order = $this->createVirtualOrder($payload);
        if (!$this->errorCollection->isEmpty()) {
            return [];
        }

        // Пересчитываем скидки и итог заказа до расчёта ограничений платёжных систем.
        $finalResult = $order->doFinalAction(true);
        if (!$finalResult->isSuccess()) {
            foreach ($finalResult->getErrors() as $error) {
                $this->errorCollection->setError($error);
            }

            // Не возвращаем состояние формы с потенциально устаревшими итогами.
            return [];
        }

        // Если ограничения доставки изменились после пересчёта скидок,
        // выбираем первый доступный вариант и повторно пересчитываем заказ.
        foreach ($order->getShipmentCollection() as $shipment) {
            if ($shipment->isSystem()) {
                continue;
            }

            $availableDeliveries = Delivery\Services\Manager::getRestrictedObjectsList($shipment);
            $selectedDeliveryId = (int)$shipment->getDeliveryId();
            if (!isset($availableDeliveries[$selectedDeliveryId])) {
                if ($availableDeliveries === []) {
                    $this->errorCollection->setError(new Error(
                        'Для текущей суммы заказа не найден доступный способ доставки.'
                    ));
                    return [];
                }

                $firstDelivery = reset($availableDeliveries);
                $fallbackDeliveryId = (int)$firstDelivery->getId();
                $shipment->setDeliveryService($firstDelivery);

                try {
                    $deliveryResult = Delivery\Services\Manager::calculateDeliveryPrice(
                        $shipment,
                        $fallbackDeliveryId,
                        $shipment->getExtraServices()
                    );
                } catch (\Throwable $exception) {
                    $this->errorCollection->setError(new Error(
                        'Не удалось рассчитать доступную службу доставки. Повторите пересчёт заказа.'
                    ));
                    return [];
                }

                if (!$deliveryResult || !$deliveryResult->isSuccess()) {
                    if ($deliveryResult) {
                        foreach ($deliveryResult->getErrors() as $error) {
                            $this->errorCollection->setError($error);
                        }
                    }
                    if ($this->errorCollection->isEmpty()) {
                        $this->errorCollection->setError(new Error(
                            'Не удалось рассчитать стоимость доступной службы доставки.'
                        ));
                    }
                    return [];
                }

                $priceResult = $shipment->setBasePriceDelivery($deliveryResult->getPrice());
                if (!$priceResult->isSuccess()) {
                    foreach ($priceResult->getErrors() as $error) {
                        $this->errorCollection->setError($error);
                    }
                    return [];
                }

                $finalResult = $order->doFinalAction(true);
                if (!$finalResult->isSuccess()) {
                    foreach ($finalResult->getErrors() as $error) {
                        $this->errorCollection->setError($error);
                    }
                    return [];
                }
            }
            break;
        }

        foreach ($order->getPaymentCollection() as $payment) {
            $sumResult = $payment->setField('SUM', $order->getPrice());
            if (!$sumResult->isSuccess()) {
                foreach ($sumResult->getErrors() as $error) {
                    $this->errorCollection->setError($error);
                }

                // Не возвращаем способы оплаты и итоги с несинхронизированной суммой.
                return [];
            }
        }

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
            $inputValue = $payload['properties'][$code] ?? $property->getValue() ?? '';
            if (is_array($inputValue)) {
                $inputValue = implode(', ', array_map('strval', $inputValue));
            }
            $val = is_scalar($inputValue) ? (string)$inputValue : '';
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
                try {
                    $calc = Delivery\Services\Manager::calculateDeliveryPrice(
                        $shipment,
                        $dId,
                        $shipment->getExtraServices()
                    );
                } catch (\Throwable $exception) {
                    continue;
                }

                if (!$calc->isSuccess()) {
                    continue;
                }

                $deliveries[] = [
                    'id'          => $dId,
                    'name'        => $srv->getNameWithParent(),
                    'description' => (string)$srv->getDescription(),
                    'price'       => $calc->getPrice(),
                    'period'      => (string)$calc->getPeriodDescription(),
                ];
            }
        }

        // Не оставляем в форме службу, которая не прошла ограничения
        // или для которой не удалось рассчитать стоимость.
        if (!in_array($selectedDeliveryId, array_column($deliveries, 'id'), true)) {
            $selectedDeliveryId = 0;
        }

        $paySystems = [];
        $selectedPaySystemId = 0;
        foreach ($order->getPaymentCollection() as $payment) {
            $availablePaySystems = PaySystem\Manager::getListWithRestrictions(
                $payment,
                RestrictionManager::MODE_CLIENT
            );
            $selectedPaySystemId = (int)$payment->getPaymentSystemId();

            if (!isset($availablePaySystems[$selectedPaySystemId]) && $availablePaySystems !== []) {
                $firstPaySystem = reset($availablePaySystems);
                $fallbackPaySystemId = (int)$firstPaySystem['ID'];
                $fallbackService = PaySystem\Manager::getObjectById($fallbackPaySystemId);
                if ($fallbackService) {
                    $payment->setPaySystemService($fallbackService);
                    $selectedPaySystemId = $fallbackPaySystemId;
                } else {
                    $selectedPaySystemId = 0;
                }
            } elseif (!isset($availablePaySystems[$selectedPaySystemId])) {
                $selectedPaySystemId = 0;
            }

            foreach ($availablePaySystems as $ps) {
                $paySystems[] = [
                    'id'          => (int)$ps['ID'],
                    'name'        => $ps['NAME'],
                    'description' => (string)($ps['DESCRIPTION'] ?? ''),
                ];
            }
            break;
        }

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
        return $types !== [] ? (int)array_key_first($types) : 0;
    }
}