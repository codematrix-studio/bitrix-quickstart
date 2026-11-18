<?php

declare(strict_types=1);

namespace Project\Bitrix\HLBlock;

use Closure;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Получение метаданных highload-блоков и компиляция их ORM-сущностей.
 *
 * Репозиторий кеширует метаданные и скомпилированные сущности в пределах
 * своего экземпляра. Зависимости можно передать через конструктор,
 * чтобы проверять работу без установленного ядра Битрикс.
 */
class HlBlockRepository
{
    private Closure $moduleLoader;
    private Closure $metadataLoader;
    private Closure $entityCompiler;

    /** @var array<int, array<string, mixed>> */
    private array $metadataCache = [];

    /** @var array<int, string> */
    private array $dataClassCache = [];

    public function __construct(
        ?callable $moduleLoader = null,
        ?callable $metadataLoader = null,
        ?callable $entityCompiler = null
    ) {
        $this->moduleLoader = Closure::fromCallable(
            $moduleLoader ?? static function (): bool {
                if (!class_exists('Bitrix\Main\Loader')) {
                    return false;
                }

                return \Bitrix\Main\Loader::includeModule('highloadblock');
            }
        );

        $this->metadataLoader = Closure::fromCallable(
            $metadataLoader ?? static function (int $id): ?array {
                $result = \Bitrix\Highloadblock\HighloadBlockTable::getById($id)->fetch();

                return is_array($result) ? $result : null;
            }
        );

        $this->entityCompiler = Closure::fromCallable(
            $entityCompiler ?? static function (array $metadata): object {
                return \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($metadata);
            }
        );
    }

    /**
     * Возвращает обёртку над ORM-сущностью highload-блока.
     *
     * @throws RuntimeException Если модуль, блок или ORM-сущность недоступны.
     */
    public function getById(int $id): HlBlockEntity
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Идентификатор highload-блока должен быть положительным числом.');
        }

        $this->ensureModuleLoaded();

        $metadata = $this->getMetadata($id);
        $dataClass = $this->getDataClass($id, $metadata);

        return new HlBlockEntity($id, $metadata, $dataClass);
    }

    private function ensureModuleLoaded(): void
    {
        try {
            $loaded = ($this->moduleLoader)();
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'Не удалось подключить модуль highloadblock.',
                0,
                $exception
            );
        }

        if ($loaded !== true) {
            throw new RuntimeException(
                'Не удалось подключить модуль highloadblock. Проверьте установку модуля и доступность ядра Bitrix.'
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function getMetadata(int $id): array
    {
        if (isset($this->metadataCache[$id])) {
            return $this->metadataCache[$id];
        }

        try {
            $metadata = ($this->metadataLoader)($id);
        } catch (Throwable $exception) {
            throw new RuntimeException(
                sprintf('Не удалось получить описание highload-блока с ID %d.', $id),
                0,
                $exception
            );
        }

        if (!is_array($metadata) || (int) ($metadata['ID'] ?? 0) !== $id) {
            throw new RuntimeException(sprintf('Highload-блок с ID %d не найден.', $id));
        }

        if (!isset($metadata['TABLE_NAME']) || !is_string($metadata['TABLE_NAME']) || $metadata['TABLE_NAME'] === '') {
            throw new RuntimeException(
                sprintf('Для highload-блока с ID %d не задано имя таблицы.', $id)
            );
        }

        $this->metadataCache[$id] = $metadata;

        return $metadata;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function getDataClass(int $id, array $metadata): string
    {
        if (isset($this->dataClassCache[$id])) {
            return $this->dataClassCache[$id];
        }

        try {
            $entity = ($this->entityCompiler)($metadata);
        } catch (Throwable $exception) {
            throw new RuntimeException(
                sprintf('Не удалось скомпилировать ORM-сущность highload-блока с ID %d.', $id),
                0,
                $exception
            );
        }

        if (!is_object($entity) || !is_callable([$entity, 'getDataClass'])) {
            throw new RuntimeException(
                sprintf('Компилятор не вернул корректную ORM-сущность для highload-блока с ID %d.', $id)
            );
        }

        $dataClass = $entity->getDataClass();

        if (!is_string($dataClass) || $dataClass === '' || !class_exists($dataClass)) {
            throw new RuntimeException(
                sprintf('Для highload-блока с ID %d не удалось получить ORM-класс данных.', $id)
            );
        }

        foreach (['getList', 'add', 'update', 'delete'] as $method) {
            if (!is_callable([$dataClass, $method])) {
                throw new RuntimeException(
                    sprintf('ORM-класс %s не предоставляет метод %s().', $dataClass, $method)
                );
            }
        }

        $this->dataClassCache[$id] = $dataClass;

        return $dataClass;
    }
}
