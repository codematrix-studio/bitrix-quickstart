<?php

declare(strict_types=1);

namespace Project\Bitrix\HLBlock;

use Closure;
use RuntimeException;
use Throwable;

/**
 * Получает метаданные и ORM-класс HL-блока.
 *
 * Репозиторий отделяет работу с HighloadBlockTable от публичного API
 * менеджера. Метаданные и скомпилированные классы кешируются в экземпляре.
 */
final class HlBlockRepository
{
    private Closure $moduleLoader;
    private Closure $blockFinder;
    private Closure $entityCompiler;
    private bool $moduleLoaded = false;

    /** @var array<int, array<string, mixed>> */
    private array $metadata = [];

    /** @var array<int, class-string> */
    private array $dataClasses = [];

    public function __construct(
        ?callable $moduleLoader = null,
        ?callable $blockFinder = null,
        ?callable $entityCompiler = null
    ) {
        $this->moduleLoader = Closure::fromCallable(
            $moduleLoader ?? static function (): bool {
                if (!class_exists(\Bitrix\Main\Loader::class)) {
                    return false;
                }

                return \Bitrix\Main\Loader::includeModule('highloadblock');
            }
        );

        $this->blockFinder = Closure::fromCallable(
            $blockFinder ?? static function (int $id): ?array {
                $result = \Bitrix\Highloadblock\HighloadBlockTable::getById($id);

                if (!is_object($result) || !is_callable([$result, 'fetch'])) {
                    throw new RuntimeException('Битрикс вернул некорректный результат поиска HL-блока.');
                }

                $block = $result->fetch();

                return is_array($block) ? $block : null;
            }
        );

        $this->entityCompiler = Closure::fromCallable(
            $entityCompiler ?? static function (array $block): string {
                $entity = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($block);

                if (!is_object($entity) || !is_callable([$entity, 'getDataClass'])) {
                    throw new RuntimeException('Битрикс не смог скомпилировать ORM-сущность HL-блока.');
                }

                return $entity->getDataClass();
            }
        );
    }

    /**
     * Возвращает ORM-класс указанного HL-блока.
     *
     * @return class-string
     */
    public function getDataClass(int $id): string
    {
        if ($id < 1) {
            throw new \InvalidArgumentException('ID HL-блока должен быть положительным целым числом.');
        }

        if (isset($this->dataClasses[$id])) {
            return $this->dataClasses[$id];
        }

        $this->ensureModuleLoaded();
        $block = $this->getMetadata($id);

        try {
            $dataClass = ($this->entityCompiler)($block);
        } catch (Throwable $exception) {
            throw new RuntimeException(
                sprintf('Не удалось скомпилировать ORM-сущность HL-блока с ID %d.', $id),
                0,
                $exception
            );
        }

        if (!is_string($dataClass) || $dataClass === '' || !class_exists($dataClass)) {
            throw new RuntimeException(
                sprintf('Для HL-блока с ID %d не удалось получить доступный ORM-класс.', $id)
            );
        }

        foreach (['getList', 'getByPrimary', 'add', 'update', 'delete'] as $method) {
            if (!is_callable([$dataClass, $method])) {
                throw new RuntimeException(
                    sprintf('ORM-класс %s не предоставляет метод %s().', $dataClass, $method)
                );
            }
        }

        /** @var class-string $dataClass */
        $this->dataClasses[$id] = $dataClass;

        return $dataClass;
    }

    /**
     * Возвращает метаданные HL-блока.
     *
     * @return array<string, mixed>
     */
    public function getMetadata(int $id): array
    {
        if ($id < 1) {
            throw new \InvalidArgumentException('ID HL-блока должен быть положительным целым числом.');
        }

        if (isset($this->metadata[$id])) {
            return $this->metadata[$id];
        }

        $this->ensureModuleLoaded();

        try {
            $block = ($this->blockFinder)($id);
        } catch (Throwable $exception) {
            throw new RuntimeException(
                sprintf('Не удалось получить метаданные HL-блока с ID %d.', $id),
                0,
                $exception
            );
        }

        if (!is_array($block) || (int) ($block['ID'] ?? 0) !== $id) {
            throw new RuntimeException(sprintf('HL-блок с ID %d не найден.', $id));
        }

        $this->metadata[$id] = $block;

        return $block;
    }

    private function ensureModuleLoaded(): void
    {
        if ($this->moduleLoaded) {
            return;
        }

        try {
            $loaded = ($this->moduleLoader)();
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'Не удалось подключить модуль Битрикс highloadblock.',
                0,
                $exception
            );
        }

        if ($loaded !== true) {
            throw new RuntimeException('Не удалось подключить модуль Битрикс highloadblock.');
        }

        $this->moduleLoaded = true;
    }
}
