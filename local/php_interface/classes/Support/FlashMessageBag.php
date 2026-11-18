<?php

declare(strict_types=1);

namespace Project\\Support;

use InvalidArgumentException;

/**
 * Stores one-request flash messages in a caller-provided session array.
 *
 * The class does not start a session, render HTML, or escape message content.
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
     * Returns queued messages without removing them.
     *
     * @return list<string>
     */
    public function all(): array
    {
        $messages = $this->storage[$this->key] ?? [];

        return is_array($messages) ? array_values($messages) : [];
    }

    /**
     * Returns queued messages and removes them from storage.
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
