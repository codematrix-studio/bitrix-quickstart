<?php

declare(strict_types=1);

namespace Project\Support;

use InvalidArgumentException;

/**
 * Хранит flash-сообщения на один запрос в переданном массиве сессии.
 *
 * Класс не запускает сессию, не формирует HTML и не экранирует содержимое сообщений.
 */
final class FlashMessageBag
{
    private array $storage;

    public function __construct(array &$storage, private readonly string $key = 'flash_messages')
    {
        if ($key === '') {
            throw new InvalidArgumentException('The flash-message storage key cannot be empty.');
        }

        $this->storage =& $storage;
    }

    public function add(string $message): void
    {
        $this->storage[$this->key] ??= [];
        $this->storage[$this->key][] = $message;
    }

    /**
     * Возвращает ожидающие сообщения, не удаляя их из хранилища.
     *
     * @return list<string>
     */
    public function all(): array
    {
        $messages = $this->storage[$this->key] ?? [];

        return is_array($messages) ? array_values($messages) : [];
    }

    /**
     * Возвращает ожидающие сообщения и удаляет их из хранилища.
     *
     * @return list<string>
     */
    public function pull(): array
    {
        $messages = $this->all();
        $this->clear();

        return $messages;
    }

    public function clear(): void
    {
        unset($this->storage[$this->key]);
    }
}
