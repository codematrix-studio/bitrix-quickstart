<?php

declare(strict_types=1);

namespace Project\Bitrix\HLBlock;

use Closure;
use InvalidArgumentException;
use RuntimeException;

/**
 * Находит HL-блоки и подготавливает их ORM-сущности.
 *
 * Модуль highloadblock подключается только при первом обращении к менеджеру.
 * Метаданные и ORM-классы кешируются в пределах экземпляра менеджера.
 */
class HlBlockManager
{
    private ?Closure $moduleLoader;
    private ?Closure $blockFinder;
    private ?Closure $entityCompiler;

    /** @var array<int, HlBlockEntity> */
    private array $entities = [];

    public function __construct(
        ?callable $moduleLoader = null,
        ?callable $blockFinder = null,
        ?callable $entityCompiler = null
    ) {
        $this->moduleLoader = $moduleLoader === null ? null : Closure::fromCallable($moduleLoader);
        $this->blockFinder = $blockFinder === null ? null : Closure::fromCallable($blockFinder);
        $this->entityCompiler = $entityCompiler === null ? null : Closure::fromCallable($entityCompiler);
    }

    /**
     * Возвращает ORM-обёртку для HL-блока с указанным ID.
     */
    public function getById(int $id): HlBlockEntity
    {
        if ($id < 1) {
            throw new InvalidArgumentException('ID HL-блока должен быть положительным целым числом.');
        }

        if (isset($this->entities[$id])) {
            return $this->entities[$id];
        }

        $this->ensureModule();
        $block = $this->findBlock($id);

        if ($block === null) {
            throw new RuntimeException(sprintf('HL-блок с ID %d не найден.', $id));
        }

        $dataClass = $this->compileEntity($block);

        if (!is_string($dataClass) || $dataClass === '' || !class_exists($dataClass)) {
            throw new RuntimeException(sprintf('Не удалось получить ORM-класс HL-блока с ID %d.', $id));
        }

        return $this->entities[$id] = new HlBlockEntity($dataClass);
    }

    private function ensureModule(): void
    {
        $loaded = $this->moduleLoader !== null
            ? ($this->moduleLoader)()
            : $this->loadModule();

        if ($loaded !== true) {
            throw new RuntimeException('Не удалось подключить модуль Битрикс highloadblock.');
        }
    }

    private function loadModule(): bool
    {
        if (!class_exists('Bitrix\\Main\\Loader')) {
            return false;
        }

        return \Bitrix\Main\Loader::includeModule('highloadblock');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findBlock(int $id): ?array
    {
        if ($this->blockFinder !== null) {
            $block = ($this->blockFinder)($id);
        } else {
            if (!class_exists('Bitrix\\Highloadblock\\HighloadBlockTable')) {
                throw new RuntimeException('Класс HighloadBlockTable недоступен.');
            }

            $result = \Bitrix\Highloadblock\HighloadBlockTable::getById($id);
            $block = is_object($result) && method_exists($result, 'fetch') ? $result->fetch() : null;
        }

        if ($block === null) {
            return null;
        }

        if (!is_array($block)) {
            throw new RuntimeException('Метаданные HL-блока имеют некорректный формат.');
        }

        return $block;
    }

    /**
     * @param array<string, mixed> $block
     */
    private function compileEntity(array $block): string
    {
        if ($this->entityCompiler !== null) {
            return ($this->entityCompiler)($block);
        }

        if (!class_exists('Bitrix\\Highloadblock\\HighloadBlockTable')) {
            throw new RuntimeException('Класс HighloadBlockTable недоступен.');
        }

        $entity = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($block);

        if (!is_object($entity) || !method_exists($entity, 'getDataClass')) {
            throw new RuntimeException('Битрикс не смог скомпилировать ORM-сущность HL-блока.');
        }

        return $entity->getDataClass();
    }
}
