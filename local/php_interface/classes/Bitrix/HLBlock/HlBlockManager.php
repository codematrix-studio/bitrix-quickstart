<?php

declare(strict_types=1);

namespace Project\Bitrix\HLBlock;

use InvalidArgumentException;

/**
 * Находит HL-блоки и предоставляет обёртки для работы с их записями.
 *
 * Публичный конструктор сохраняет совместимость: три callable-параметра
 * по-прежнему означают загрузчик модуля, поиск метаданных и компилятор ORM.
 */
class HlBlockManager
{
    private HlBlockRepository $repository;

    /** @var array<int, HlBlockEntity> */
    private array $entities = [];

    public function __construct(
        ?callable $moduleLoader = null,
        ?callable $blockFinder = null,
        ?callable $entityCompiler = null
    ) {
        $this->repository = new HlBlockRepository($moduleLoader, $blockFinder, $entityCompiler);
    }

    /**
     * Возвращает обёртку для HL-блока с указанным ID.
     */
    public function getById(int $id): HlBlockEntity
    {
        if ($id < 1) {
            throw new InvalidArgumentException('ID HL-блока должен быть положительным целым числом.');
        }

        if (isset($this->entities[$id])) {
            return $this->entities[$id];
        }

        $dataClass = $this->repository->getDataClass($id);

        return $this->entities[$id] = new HlBlockEntity($dataClass);
    }
}
