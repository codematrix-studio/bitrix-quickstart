<?php

declare(strict_types=1);

namespace Project\Support;

use Countable;
use JsonException;

/**
 * Неизменяемый список положительных ID товаров.
 *
 * Класс не зависит от Битрикс и отвечает только за нормализацию списка.
 */
final class ProductIdList implements Countable
{
    /**
     * @param list<int> $productIds
     */
    private function __construct(
        private readonly array $productIds
    ) {
    }

    /**
     * Создаёт список из произвольного набора значений.
     *
     * Некорректные значения отбрасываются, повторяющиеся ID удаляются
     * с сохранением порядка первого появления.
     */
    public static function fromValues(iterable $values): self
    {
        $ids = [];

        foreach ($values as $value) {
            $id = self::normalizeId($value);

            if ($id !== null) {
                $ids[$id] = $id;
            }
        }

        return new self(array_values($ids));
    }

    /**
     * Читает JSON-массив или старое сериализованное значение.
     *
     * Поддержка serialize() оставлена только для миграции старых cookie
     * и пользовательских полей. Объекты при unserialize запрещены.
     */
    public static function fromStorageValue(mixed $value): self
    {
        if (is_array($value)) {
            return self::fromValues($value);
        }

        if (!is_string($value) || $value === '' || strlen($value) > 65536) {
            return new self([]);
        }

        try {
            $decoded = json_decode($value, true, 16, JSON_THROW_ON_ERROR);

            if (is_array($decoded) && array_is_list($decoded)) {
                return self::fromValues($decoded);
            }
        } catch (JsonException) {
            // Старые версии сохраняли массив через serialize().
        }

        $legacyValue = @unserialize($value, ['allowed_classes' => false]);

        return is_array($legacyValue)
            ? self::fromValues($legacyValue)
            : new self([]);
    }

    /**
     * @return list<int>
     */
    public function toArray(): array
    {
        return $this->productIds;
    }

    public function toJson(): string
    {
        return json_encode($this->productIds, JSON_THROW_ON_ERROR);
    }

    public function contains(int $productId): bool
    {
        return $productId > 0 && in_array($productId, $this->productIds, true);
    }

    public function add(int $productId): self
    {
        if ($productId < 1 || $this->contains($productId)) {
            return $this;
        }

        return new self([...$this->productIds, $productId]);
    }

    public function remove(int $productId): self
    {
        if (!$this->contains($productId)) {
            return $this;
        }

        return new self(array_values(array_filter(
            $this->productIds,
            static fn (int $id): bool => $id !== $productId
        )));
    }

    public function count(): int
    {
        return count($this->productIds);
    }

    private static function normalizeId(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '' || preg_match('/^[0-9]+$/D', $value) !== 1) {
            return null;
        }

        $id = filter_var($value, FILTER_VALIDATE_INT);

        return is_int($id) && $id > 0 ? $id : null;
    }
}
