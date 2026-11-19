<?php

declare(strict_types=1);

namespace Project\Bitrix\HLBlock;

use InvalidArgumentException;
use RuntimeException;

/**
 * Выполняет операции над записями одного HL-блока через D7 ORM.
 */
class HlBlockEntity
{
    /** @var class-string */
    private string $dataClass;

    /**
     * @param class-string $dataClass
     */
    public function __construct(string $dataClass)
    {
        if ($dataClass === '' || !class_exists($dataClass)) {
            throw new InvalidArgumentException('Передан недоступный ORM-класс HL-блока.');
        }

        foreach (['getList', 'getByPrimary', 'add', 'update', 'delete'] as $method) {
            if (!is_callable([$dataClass, $method])) {
                throw new InvalidArgumentException(
                    sprintf('ORM-класс HL-блока не поддерживает метод %s().', $method)
                );
            }
        }

        $this->dataClass = $dataClass;
    }

    /**
     * Возвращает список записей по параметрам D7 ORM.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getList(array $parameters = []): array
    {
        $result = ($this->dataClass)::getList($parameters);

        if (!is_object($result) || !is_callable([$result, 'fetchAll'])) {
            throw new RuntimeException('ORM HL-блока вернул некорректный результат списка.');
        }

        $rows = $result->fetchAll();

        if (!is_array($rows)) {
            throw new RuntimeException('Метод fetchAll() вернул некорректный список записей HL-блока.');
        }

        return $rows;
    }

    /**
     * Возвращает запись по первичному ключу или null, если она не найдена.
     *
     * @return array<string, mixed>|null
     */
    public function getById(int $id, array $select = ['*']): ?array
    {
        $this->assertId($id);
        $result = ($this->dataClass)::getByPrimary($id, ['select' => $select]);

        if (!is_object($result) || !is_callable([$result, 'fetch'])) {
            throw new RuntimeException('ORM HL-блока вернул некорректный результат поиска.');
        }

        $row = $result->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * Ищет запись по UF_XML_ID.
     *
     * Метод применим только к блокам, в которых создано такое пользовательское поле.
     *
     * @return array<string, mixed>|null
     */
    public function findByXmlId(string $xmlId, array $select = ['*']): ?array
    {
        if (trim($xmlId) === '') {
            throw new InvalidArgumentException('UF_XML_ID не может быть пустым.');
        }

        $rows = $this->getList([
            'select' => $select,
            'filter' => ['=UF_XML_ID' => $xmlId],
            'limit' => 1,
        ]);

        return $rows[0] ?? null;
    }

    /**
     * Добавляет запись и возвращает её ID.
     */
    public function add(array $fields): int
    {
        if ($fields === []) {
            throw new InvalidArgumentException('Нельзя добавить пустую запись HL-блока.');
        }

        $result = ($this->dataClass)::add($fields);
        $this->assertSuccessfulResult($result, 'добавить запись');

        if (!is_callable([$result, 'getId'])) {
            throw new RuntimeException('Результат добавления HL-блока не содержит ID записи.');
        }

        return (int) $result->getId();
    }

    /**
     * Обновляет запись. При ошибке ORM выбрасывает RuntimeException.
     */
    public function update(int $id, array $fields): bool
    {
        $this->assertId($id);

        if ($fields === []) {
            throw new InvalidArgumentException('Нельзя обновить запись пустым набором полей.');
        }

        $result = ($this->dataClass)::update($id, $fields);
        $this->assertSuccessfulResult($result, 'обновить запись');

        return true;
    }

    /**
     * Удаляет запись. При ошибке ORM выбрасывает RuntimeException.
     */
    public function delete(int $id): bool
    {
        $this->assertId($id);
        $result = ($this->dataClass)::delete($id);
        $this->assertSuccessfulResult($result, 'удалить запись');

        return true;
    }

    private function assertId(int $id): void
    {
        if ($id < 1) {
            throw new InvalidArgumentException('ID записи HL-блока должен быть положительным целым числом.');
        }
    }

    private function assertSuccessfulResult($result, string $operation): void
    {
        if (!is_object($result) || !is_callable([$result, 'isSuccess'])) {
            throw new RuntimeException(sprintf('Не удалось %s: ORM вернул некорректный результат.', $operation));
        }

        if ($result->isSuccess()) {
            return;
        }

        $messages = is_callable([$result, 'getErrorMessages']) ? $result->getErrorMessages() : [];
        $details = is_array($messages) ? implode('; ', $messages) : '';

        throw new RuntimeException(sprintf(
            'Не удалось %s%s.',
            $operation,
            $details !== '' ? ': ' . $details : ''
        ));
    }
}
