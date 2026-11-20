<?php

declare(strict_types=1);

namespace Project\Bitrix\Sale;

use Closure;
use InvalidArgumentException;
use LogicException;
use RuntimeException;
use Project\Bitrix\IBlock\ElementRepository;
use Project\Support\CharsetConverter;
use Project\Support\HtmlEscaper;

/**
 * Безопасный фасад чтения и совместимых операций с корзиной.
 *
 * Не раскрывает изменяемый объект D7 Basket и не позволяет задавать цену,
 * валюту, сайт или свойства позиции из входных данных.
 */
class BasketHandler
{
    private BasketManager $manager;
    private ?Closure $productFieldsLoader;
    private ?Closure $priceFormatter;

    public function __construct(
        ?BasketManager $manager = null,
        ?callable $productFieldsLoader = null,
        ?callable $priceFormatter = null
    ) {
        $this->manager = $manager ?? new BasketManager();
        $this->productFieldsLoader = $productFieldsLoader === null
            ? null
            : Closure::fromCallable($productFieldsLoader);
        $this->priceFormatter = $priceFormatter === null
            ? null
            : Closure::fromCallable($priceFormatter);
    }

    /**
     * Добавляет товары по ID и количеству.
     *
     * Поддерживаются только PRODUCT_ID/product_id и QUANTITY/quantity.
     * Цена, валюта, сайт и свойства должны определяться доверенным кодом.
     *
     * @param array<int, array<string, mixed>> $items
     */
    public function add(array $items = []): bool
    {
        $normalizedItems = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                throw new InvalidArgumentException('Каждая позиция должна быть массивом.');
            }

            $unsupported = array_diff(array_keys($item), [
                'PRODUCT_ID', 'product_id', 'QUANTITY', 'quantity',
            ]);
            if ($unsupported !== []) {
                throw new InvalidArgumentException(
                    'Неподдерживаемые параметры корзины: ' . implode(', ', $unsupported)
                    . '. Цена, валюта, сайт и свойства не принимаются.'
                );
            }

            if (isset($item['PRODUCT_ID'], $item['product_id'])
                || isset($item['QUANTITY'], $item['quantity'])
            ) {
                throw new InvalidArgumentException('Не дублируйте параметры в верхнем и нижнем регистре.');
            }

            $productId = filter_var($item['PRODUCT_ID'] ?? $item['product_id'] ?? null, FILTER_VALIDATE_INT);
            $quantity = $item['QUANTITY'] ?? $item['quantity'] ?? 1;

            if (!is_int($productId) || !is_numeric($quantity)) {
                throw new InvalidArgumentException('PRODUCT_ID должен быть целым числом, QUANTITY — числом.');
            }

            $quantity = (float) $quantity;
            if (!is_finite($quantity) || $quantity <= 0) {
                throw new InvalidArgumentException('Количество товара должно быть положительным числом.');
            }

            $normalizedItems[] = [$productId, $quantity];
        }

        // Сначала проверяем весь пакет, чтобы ошибка во входных данных
        // не оставляла корзину частично изменённой.
        foreach ($normalizedItems as [$productId, $quantity]) {
            $this->manager->add($productId, $quantity);
        }

        return true;
    }

    /** Добавляет одну позицию из параметров действия. */
    public function addAction(array $data = []): bool
    {
        return $this->add([$data]);
    }

    /**
     * Удаляет позиции по ID строк корзины.
     *
     * @param int|array<int, int> $itemIds
     */
    public function delete($itemIds = []): bool
    {
        $ids = [];
        foreach ((array) $itemIds as $itemId) {
            if (!is_int($itemId) && !(is_string($itemId) && ctype_digit($itemId))) {
                throw new InvalidArgumentException('ID позиции корзины должен быть целым числом.');
            }

            $id = (int) $itemId;
            if ($id < 1) {
                throw new InvalidArgumentException('ID позиции корзины должен быть положительным числом.');
            }
            $ids[] = $id;
        }

        $existingIds = array_column($this->getBasketItems(), 'id');
        foreach ($ids as $id) {
            if (!in_array($id, $existingIds, true)) {
                throw new InvalidArgumentException(sprintf('Позиция корзины с ID %d не найдена.', $id));
            }
        }

        foreach (array_unique($ids) as $id) {
            $this->manager->remove($id);
        }

        return true;
    }

    /** Удаляет одну позицию по ID строки корзины. */
    public function deleteAction($data = []): bool
    {
        if (is_array($data)) {
            $data = $data['ID'] ?? $data['id'] ?? null;
        }

        return $this->delete([$data]);
    }

    /** Возвращает ID FUSER текущего покупателя. */
    public function getFUserId(): int
    {
        return $this->manager->getFuserId();
    }

    /** Прямой доступ к изменяемому объекту D7 намеренно запрещён. */
    public function getBasket(): never
    {
        throw new LogicException(
            'Прямой доступ к объекту корзины запрещён. Используйте getBasketItems() и методы BasketHandler.'
        );
    }

    /** Очищает только корзину текущего FUSER и текущего сайта. */
    public function clear(): void
    {
        $this->manager->clear();
    }

    /**
     * Возвращает отдельные массивы-снимки позиций вместо объектов D7 BasketItem.
     *
     * @return array<int, array{id:int, product_id:int, quantity:float, price:float, name:?string}>
     */
    public function getBasketItems(): array
    {
        $result = [];

        foreach ($this->manager->getItems() as $item) {
            if (!is_object($item)
                || !is_callable([$item, 'getId'])
                || !is_callable([$item, 'getProductId'])
                || !is_callable([$item, 'getQuantity'])
                || !is_callable([$item, 'getPrice'])
            ) {
                throw new RuntimeException('Позиция корзины не поддерживает ожидаемый интерфейс D7.');
            }

            $name = is_callable([$item, 'getField']) ? $item->getField('NAME') : null;
            $result[] = [
                'id' => (int) $item->getId(),
                'product_id' => (int) $item->getProductId(),
                'quantity' => (float) $item->getQuantity(),
                'price' => (float) $item->getPrice(),
                'name' => is_scalar($name) ? (string) $name : null,
            ];
        }

        return $result;
    }

    public function getItemsCount(): int
    {
        return $this->manager->getItemsCount();
    }

    public function getGoodsCount(): float
    {
        return $this->manager->getGoodsCount();
    }

    public function getPrice(): float
    {
        return $this->manager->getPrice();
    }

    /**
     * Возвращает снимок позиции по ID товара.
     *
     * Сопоставление по произвольной цене и пользовательским свойствам удалено:
     * эти параметры не должны определять доверенную позицию корзины.
     *
     * @return array{id:int, product_id:int, quantity:float, price:float, name:?string}|false
     */
    public function getBasketItem($productId, $price = null, array $props = [])
    {
        if ($price !== null || $props !== []) {
            throw new InvalidArgumentException(
                'Поиск по произвольной цене или свойствам не поддерживается. Используйте ID товара.'
            );
        }

        $productId = filter_var($productId, FILTER_VALIDATE_INT);
        if (!is_int($productId) || $productId < 1) {
            throw new InvalidArgumentException('ID товара должен быть положительным целым числом.');
        }

        foreach ($this->getBasketItems() as $item) {
            if ($item['product_id'] === $productId) {
                return $item;
            }
        }

        return false;
    }

    /**
     * Возвращает снимки позиций и загруженные поля товаров.
     *
     * Загрузчик должен вернуть массив, индексированный ID товара.
     */
    public function getBasketItemsWithFields($iblockId): array
    {
        $iblockId = filter_var($iblockId, FILTER_VALIDATE_INT);
        if (!is_int($iblockId) || $iblockId < 1) {
            throw new InvalidArgumentException('ID инфоблока должен быть положительным целым числом.');
        }

        $items = $this->getBasketItems();
        $productIds = array_values(array_unique(array_column($items, 'product_id')));

        if ($productIds === []) {
            return ['items' => [], 'fields' => []];
        }

        $fields = $this->productFieldsLoader !== null
            ? ($this->productFieldsLoader)($iblockId, $productIds)
            : (new ElementRepository())->getListD7($iblockId, [
                'filter' => ['ID' => $productIds],
            ]);

        if (!is_array($fields)) {
            throw new RuntimeException('Загрузчик полей товаров должен вернуть массив.');
        }

        return ['items' => $items, 'fields' => $fields];
    }

    /**
     * Возвращает только запрошенные разрешённые поля позиции.
     *
     * @param string[] $propCodes
     */
    public function getBasketItemsWithProps(array $propCodes): array
    {
        $allowed = ['ID', 'PRODUCT_ID', 'NAME', 'QUANTITY', 'PRICE', 'BASE_PRICE', 'CURRENCY'];
        foreach ($propCodes as $code) {
            if (!is_string($code) || !in_array($code, $allowed, true)) {
                throw new InvalidArgumentException('Запрошено неподдерживаемое поле позиции корзины.');
            }
        }

        $result = [];
        foreach ($this->manager->getItems() as $item) {
            $id = (int) $item->getId();
            $values = [];
            foreach ($propCodes as $code) {
                $values[$code] = match ($code) {
                    'ID' => $id,
                    'PRODUCT_ID' => (int) $item->getProductId(),
                    'QUANTITY' => (float) $item->getQuantity(),
                    'PRICE' => (float) $item->getPrice(),
                    default => is_callable([$item, 'getField']) ? $item->getField($code) : null,
                };
            }
            $result[$id] = $values;
        }

        return $result;
    }

    public function getFormattedPrice(): string
    {
        return $this->formatPrice($this->manager->getPrice());
    }

    /** Возвращает форматированную стоимость корзины до применения скидок. */
    public function getFormattedBasePrice(): string
    {
        return $this->formatPrice($this->manager->getBasePrice());
    }

    /**
     * Совместимый метод HTML-кодирования строки.
     *
     * @param mixed $value
     */
    public static function encodeWithCharset($value, $cp1251 = false): string
    {
        if ($value === null) {
            $value = '';
        } elseif (!is_scalar($value)) {
            throw new InvalidArgumentException('Для HTML-кодирования ожидается скалярное значение.');
        }

        $value = (string) $value;
        $encoding = 'UTF-8';

        if ($cp1251) {
            $converted = CharsetConverter::toWindows1251($value);
            if (!is_string($converted)) {
                throw new RuntimeException('Не удалось преобразовать строку в Windows-1251.');
            }
            $value = $converted;
            $encoding = 'Windows-1251';
        }

        return (new HtmlEscaper())->escape($value, ENT_COMPAT, $encoding);
    }

    private function formatPrice(float $price): string
    {
        if ($this->priceFormatter !== null) {
            $formatted = ($this->priceFormatter)($price);
            if (!is_string($formatted)) {
                throw new RuntimeException('Форматтер цены должен вернуть строку.');
            }

            return $formatted;
        }

        $currency = $this->manager->getCurrency();
        if (function_exists('SaleFormatCurrency')) {
            return (string) SaleFormatCurrency($price, $currency);
        }

        throw new LogicException('Функция SaleFormatCurrency() недоступна. Проверьте подключение модуля currency.');
    }

    /**
     * Быстрый заказ выполняется отдельным QuickOrder и требует данных покупателя,
     * типа плательщика и свойств заказа. Старую Form-сигнатуру не имитируем.
     */
    public function fastOrderAction(...$arguments): never
    {
        throw new LogicException(
            'fastOrderAction() удалён. Используйте Project\Bitrix\Sale\QuickOrder и передавайте проверенные данные заказа.'
        );
    }

    /** Старая заглушка сравнения не выполняет действий. */
    public function compareAction(array $data = []): never
    {
        throw new LogicException(
            'compareAction() не реализован. Для списка сравнения используйте отдельный сервис.'
        );
    }
}
