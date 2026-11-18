<?php

declare(strict_types=1);

namespace Project\Bitrix\HLBlock;

use InvalidArgumentException;
use RuntimeException;

/**
 * Операции с записями конкретного highload-блока.
 *
 * Методы записи возвращают штатные объекты Result ORM Bitrix.
 */
class HlBlockEntity
{
    private int $id;

    /** @var array<string, mixed> */
    private array $metadata;

    private string $dataClass;

    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(int $id, array $metadata, string $dataClass)
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Идентификатор highload-блока должен быть положительным числом.');
        }

        if ($dataClass === '' || !class_exists($dataClass)) {
            throw new InvalidArgumentException('Не указан доступный ORM-класс highload-блока.');
        }

        $this->id = $id;
        $this->metadata = $metadata;
        $this->dataClass = $dataClass;
    }

    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Возвращает метаданные highload-блока из HighloadBlockTable.
     *
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Возвращает имя скомпилированного ORM-класса.
     */
    public function getDataClass(): string
    {
        return $this->dataClass;
    }

    /**
     * Выполняет запрос к записям highload-блока.
     *
     * @param array<string, mixed> $parameters Параметры DataManager::getList().
     */
    public function getList(array $parameters = []): object
    {
        return $this->callDataClass('getList', [$parameters]);
    }

    /**
     * Возвращает запись по ID или null, если запись не найдена.
     *
     * @return array<string, mixed>|null
     */
    public function getById(int $id, array $select = ['*']): ?array
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Идентификатор записи должен быть положительным числом.');
        }

        $result = $this->getList([
            'select' => $select,
            'filter' => ['=ID' => $id],
            'limit' => 1,
        ]);

        if (!is_callable([$result, 'fetch'])) {
            throw new RuntimeException('Результат ORM-запроса не поддерживает fetch().');
        }

        $row = $result->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * Ищет запись по внешнему идентификатору UF_XML_ID.
     *
     * Метод применим только к HL-блокам, в которых создано поле UF_XML_ID.
     *
     * @return array<string, mixed>|null
     */
    public function findByXmlId(string $xmlId, array $select = ['*']): ?array
    {
        if (trim($xmlId) === '') {
            throw new InvalidArgumentException('Внешний идентификатор не может быть пустым.');
        }

        $result = $this->getList([
            'select' => $select,
            'filter' => ['=UF_XML_ID' => $xmlId],
            'limit' => 1,
        ]);

        if (!is_callable([$result, 'fetch'])) {
            throw new RuntimeException('Результат ORM-запроса не поддерживает fetch().');
        }

        $row = $result->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * Добавляет запись и возвращает штатный AddResult.
     *
     * @param array<string, mixed> $fields
     */
    public function add(array $fields): object
    {
        if ($fields === []) {
            throw new InvalidArgumentException('Нельзя добавить запись без полей.');
        }

        return $this->callDataClass('add', [$fields]);
    }

    /**
     * Обновляет запись и возвращает штатный UpdateResult.
     *
     * @param array<string, mixed> $fields
     */
    public function update(int $id, array $fields): object
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Идентификатор записи должен быть положительным числом.');
        }

        if ($fields === []) {
            throw new InvalidArgumentException('Нельзя обновить запись без полей.');
        }

        return $this->callDataClass('update', [$id, $fields]);
    }

    /**
     * Удаляет запись и возвращает штатный DeleteResult.
     */
    public function delete(int $id): object
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Идентификатор записи должен быть положительным числом.');
        }

        return $this->callDataClass('delete', [$id]);
    }

    /**
     * @param array<int, mixed> $arguments
     */
    private function callDataClass(string $method, array $arguments): object
    {
        if (!is_callable([$this->dataClass, $method])) {
            throw new RuntimeException(
                sprintf('ORM-класс %s не предоставляет метод %s().', $this->dataClass, $method)
            );
        }

        $result = $this->dataClass::{$method}(...$arguments);

        if (!is_object($result)) {
            throw new RuntimeException(
                sprintf('Метод %s::%s() должен возвращать объект результата ORM.', $this->dataClass, $method)
            );
        }

        return $result;
    }
}
