<?php

declare(strict_types=1);

namespace Project\Bitrix\Sale;

use RuntimeException;

/**
 * Заказ сохранён, но Bitrix вернул предупреждения.
 *
 * Важно: исключение не означает, что заказ не создан. Не повторяйте создание
 * автоматически — сначала проверьте заказ по ID, возвращаемому getOrderId().
 */
final class OrderSaveWarningsException extends RuntimeException
{
    /**
     * @param array<int, string> $warnings
     */
    public function __construct(
        private int $orderId,
        private array $warnings
    ) {
        parent::__construct(sprintf(
            'Заказ #%d сохранён с предупреждениями: %s',
            $orderId,
            implode('; ', $warnings)
        ));
    }

    public function getOrderId(): int
    {
        return $this->orderId;
    }

    /**
     * @return array<int, string>
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }
}
