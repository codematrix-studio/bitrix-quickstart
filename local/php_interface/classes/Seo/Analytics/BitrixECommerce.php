<?php
declare(strict_types=1);

namespace Project\Seo\Analytics;

use Project\Support\PerClassInstance;
use RuntimeException;
use InvalidArgumentException;

/**
 * Подготавливает ecommerce payload из заказа Bitrix Sale без данных покупателя.
 */
class BitrixECommerce extends PerClassInstance
{
    protected $order = null;
    protected ?int $iblockId = null;
    protected array $properties = [];
    protected array $categories = [];
    protected array $products = [];
    protected bool $categoriesLoaded = false;
    protected bool $productsLoaded = false;
    protected ?string $paySystemName = null;
    protected ?string $deliveryName = null;

    public function setIblockId($iblockId): static
    {
        $id = filter_var($iblockId, FILTER_VALIDATE_INT);
        if (!is_int($id) || $id < 1) {
            throw new InvalidArgumentException('ID инфоблока должен быть положительным целым числом.');
        }

        $this->iblockId = $id;
        $this->categories = [];
        $this->categoriesLoaded = false;
        $this->products = [];
        $this->productsLoaded = false;
        return $this;
    }

    public function load($orderId): static
    {
        $id = filter_var($orderId, FILTER_VALIDATE_INT);
        if (!is_int($id) || $id < 1) {
            throw new InvalidArgumentException('ID заказа должен быть положительным целым числом.');
        }

        $this->includeSaleModule();
        $this->order = \Bitrix\Sale\Order::load($id);
        $this->resetOrderCache();
        return $this;
    }

    public function loadByAccNumber($orderNumber): static
    {
        if (!is_scalar($orderNumber) || trim((string) $orderNumber) === '') {
            throw new InvalidArgumentException('Номер заказа не задан.');
        }

        $this->includeSaleModule();
        $this->order = \Bitrix\Sale\Order::loadByAccountNumber(trim((string) $orderNumber));
        $this->resetOrderCache();
        return $this;
    }

    /** Серверный метод; результат может содержать персональные данные. */
    public function getOrderProps(): array
    {
        $this->checkOrder();
        if ($this->properties !== []) {
            return $this->properties;
        }

        $collection = $this->order->getPropertyCollection();
        if (!is_object($collection) || !is_callable([$collection, 'getArray'])) {
            return [];
        }

        $data = $collection->getArray();
        $items = is_array($data) && is_array($data['properties'] ?? null) ? $data['properties'] : [];
        foreach ($items as $property) {
            if (!is_array($property) || empty($property['CODE'])) {
                continue;
            }
            $value = $property['VALUE'] ?? null;
            $this->properties[(string) $property['CODE']] = is_array($value)
                ? (reset($value) === false ? null : reset($value))
                : $value;
        }
        return $this->properties;
    }

    public function getPaymentName($needUpdate = false): ?string
    {
        $this->checkOrder();
        if ($this->paySystemName !== null && !$needUpdate) {
            return $this->paySystemName;
        }

        $this->paySystemName = null;
        $payments = $this->order->getPaymentCollection();
        if (is_iterable($payments)) {
            foreach ($payments as $payment) {
                if (is_object($payment) && is_callable([$payment, 'getPaymentSystemName'])) {
                    $name = $payment->getPaymentSystemName();
                    $this->paySystemName = is_scalar($name) ? (string) $name : null;
                    break;
                }
            }
        }
        return $this->paySystemName;
    }

    public function getDeliveryName($needUpdate = false): ?string
    {
        $this->checkOrder();
        if ($this->deliveryName !== null && !$needUpdate) {
            return $this->deliveryName;
        }

        $this->deliveryName = null;
        foreach ((array) $this->order->getDeliverySystemId() as $deliveryId) {
            $delivery = \Bitrix\Sale\Delivery\Services\Manager::getById((int) $deliveryId);
            $name = is_array($delivery) ? ($delivery['NAME'] ?? null) : null;
            $this->deliveryName = is_scalar($name) && (string) $name !== ''
                ? (string) $name
                : 'Доставка курьером';
            break;
        }
        return $this->deliveryName;
    }

    public function getProductCategories($needUpdate = false): array
    {
        $this->checkOrder();
        if ($this->iblockId === null) {
            throw new RuntimeException('Не задан ID инфоблока товаров.');
        }
        $this->includeIblockModule();
        if ($this->categoriesLoaded && !$needUpdate) {
            return $this->categories;
        }

        $this->categories = [];
        foreach ($this->order->getBasket() as $item) {
            $this->categories[(int) $item->getProductId()] = null;
        }

        if ($this->categories !== []) {
            $result = \Bitrix\Iblock\ElementTable::getList([
                'filter' => ['IBLOCK_ID' => $this->iblockId, 'ID' => array_keys($this->categories)],
                'select' => ['IBLOCK_SECTION.NAME', 'ID'],
            ]);
            while ($row = $result->fetch()) {
                $id = (int) ($row['ID'] ?? 0);
                if ($id > 0) {
                    $this->categories[$id] = $row['IBLOCK_ELEMENT_IBLOCK_SECTION_NAME'] ?? null;
                }
            }
        }

        $this->categoriesLoaded = true;
        return $this->categories;
    }

    public function getProducts($needUpdate = false): array
    {
        $this->checkOrder();
        if ($this->productsLoaded && !$needUpdate) {
            return $this->products;
        }

        $categories = $this->iblockId === null ? [] : $this->getProductCategories($needUpdate);
        $this->products = [];
        foreach ($this->order->getBasket() as $item) {
            $id = (int) $item->getProductId();
            $this->products[] = [
                'sku' => $id,
                'name' => (string) $item->getField('NAME'),
                'category' => $categories[$id] ?? null,
                'price' => (float) $item->getPrice(),
                'quantity' => (float) $item->getQuantity(),
            ];
        }

        $this->productsLoaded = true;
        return $this->products;
    }

    /** Payload не содержит ФИО, email, телефона, адреса или комментариев заказа. */
    public function getGoogleCode($needUpdate = false): array
    {
        $this->checkOrder();
        return [
            'event' => 'ecomEvent',
            'delivery' => $this->getDeliveryName($needUpdate),
            'pay_system' => $this->getPaymentName($needUpdate),
            'transactionId' => $this->order->getId(),
            'transactionTotal' => (float) $this->order->getPrice(),
            'transactionProducts' => $this->getProducts($needUpdate),
        ];
    }

    public function getYandexCode($needUpdate = false): array
    {
        $this->checkOrder();
        return ['ecommerce' => ['purchase' => [
            'actionField' => ['id' => $this->order->getId()],
            'products' => $this->getProducts($needUpdate),
        ]]];
    }

    public function jsonResult($return = false, $needUpdate = false)
    {
        $script = ScriptRenderer::render($this->getGoogleCode($needUpdate))
            . ScriptRenderer::render($this->getYandexCode($needUpdate));
        if ($return) {
            return $script;
        }
        echo $script;
    }

    public function setViewContent($name = 'ecommerce', $content = null): void
    {
        global $APPLICATION;
        if (!is_object($APPLICATION) || !is_callable([$APPLICATION, 'AddViewContent'])) {
            throw new RuntimeException('Bitrix $APPLICATION::AddViewContent() недоступен.');
        }
        $APPLICATION->AddViewContent($name, $content ?? $this->jsonResult(true));
    }

    public function show($view = 'ecommerce'): void
    {
        global $APPLICATION;
        if (!is_object($APPLICATION) || !is_callable([$APPLICATION, 'ShowViewContent'])) {
            throw new RuntimeException('Bitrix $APPLICATION::ShowViewContent() недоступен.');
        }
        $APPLICATION->ShowViewContent($view);
    }

    protected function checkOrder(): void
    {
        if (!is_object($this->order)) {
            throw new RuntimeException('Заказ не загружен или не найден.');
        }
    }

    protected function includeSaleModule(): void
    {
        if (!class_exists(\Bitrix\Main\Loader::class) || !\Bitrix\Main\Loader::includeModule('sale')) {
            throw new RuntimeException('Не удалось подключить модуль sale.');
        }
    }

    protected function includeIblockModule(): void
    {
        if (!class_exists(\Bitrix\Main\Loader::class) || !\Bitrix\Main\Loader::includeModule('iblock')) {
            throw new RuntimeException('Не удалось подключить модуль iblock.');
        }
    }

    protected function resetOrderCache(): void
    {
        $this->properties = [];
        $this->categories = [];
        $this->products = [];
        $this->categoriesLoaded = false;
        $this->productsLoaded = false;
        $this->paySystemName = null;
        $this->deliveryName = null;
    }
}
