<?php

declare(strict_types=1);

namespace Project\Bitrix\HLBlock;

use InvalidArgumentException;

/**
 * Точка входа для работы с highload-блоками.
 *
 * Менеджер получает готовую ORM-сущность из репозитория и возвращает
 * типизированную обёртку для выполнения операций с записями.
 */
class HlBlockManager
{
    private HlBlockRepository $repository;

    public function __construct(?HlBlockRepository $repository = null)
    {
        $this->repository = $repository ?? new HlBlockRepository();
    }

    /**
     * Возвращает объект для работы с указанным highload-блоком.
     *
     * @throws InvalidArgumentException Если передан некорректный ID.
     */
    public function getById(int $id): HlBlockEntity
    {
        return $this->repository->getById($id);
    }
}
