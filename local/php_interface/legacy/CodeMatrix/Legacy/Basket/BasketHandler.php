<?php

declare(strict_types=1);

/**
 * Совместимый фасад старого CodeMatrix\Legacy\Basket\BasketHandler.
 *
 * Подключать после Composer autoload. Небезопасный getBasket() не возвращает
 * изменяемый объект D7; метод явно сообщает об изменении контракта.
 */

namespace CodeMatrix\Legacy\Basket;

class BasketHandler extends \Project\Bitrix\Sale\BasketHandler
{
    private static ?self $instance = null;

    public static function get(): static
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }

        return static::$instance;
    }
}
