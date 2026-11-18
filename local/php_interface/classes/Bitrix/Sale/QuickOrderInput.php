<?php

declare(strict_types=1);

namespace Project\\Bitrix\\Sale;

use InvalidArgumentException;

/**
 * Проверенные входные данные для сценария быстрого заказа.
 *
 * Идентификатор пользователя намеренно отсутствует: его можно получить
 * только из контекста авторизованной сессии на стороне приложения.
 */
final class QuickOrderInput
{
    public readonly int $productId;
    public readonly int|float $quantity;

    private function __construct(int $productId, int|float $quantity)
    {
        $this->productId = $productId;
        $this->quantity = $quantity;
    }

    /**
     * Создаёт DTO из параметров HTTP/AJAX-запроса.
     *
     * @param array<string, mixed> $payload
     */
    public static function fromPayload(array $payload): self
    {
        $productId = $payload['productId'] ?? null;
        $quantity = $payload['quantity'] ?? 1;

        if (is_string($productId) && preg_match('/^[1-9][0-9]*$/D', $productId) === 1) {
            $productId = filter_var($productId, FILTER_VALIDATE_INT);
        }

        if (!is_int($productId) || $productId < 1) {
            throw new InvalidArgumentException('ID товара должен быть положительным целым числом.');
        }

        if (is_string($quantity) && trim($quantity) !== '' && is_numeric($quantity)) {
            $quantity = (float) $quantity;
        }

        if ((!is_int($quantity) && !is_float($quantity))
            || !is_finite((float) $quantity)
            || $quantity <= 0
        ) {
            throw new InvalidArgumentException('Количество товара должно быть положительным конечным числом.');
        }

        return new self($productId, $quantity);
    }
}
