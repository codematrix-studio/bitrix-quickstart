<?php

declare(strict_types=1);

namespace Project\Bitrix\IBlock;

use Bitrix\Iblock\ElementTable;
use InvalidArgumentException;
use LogicException;

/**
 * Проверяет, существует ли элемент инфоблока, соответствующий фильтру.
 *
 * Фильтр задаётся в формате ORM D7 Bitrix.
 */
final class ElementChecker
{
    /**
     * Возвращает true, если фильтру соответствует хотя бы один элемент.
     *
     * @param array<string, mixed> $filter
     */
    public function exists(array $filter): bool
    {
        return $this->findId($filter) !== null;
    }

    /**
     * Возвращает ID первого найденного элемента или null, если совпадений нет.
     *
     * @param array<string, mixed> $filter
     */
    public function findId(array $filter): ?int
    {
        if ($filter === []) {
            throw new InvalidArgumentException(
                'An explicit filter is required to check information-block elements.'
            );
        }

        if (!class_exists(ElementTable::class)) {
            throw new LogicException(
                'The Bitrix iblock module is not available. Load the iblock module before using ElementChecker.'
            );
        }

        $result = ElementTable::getList([
            'select' => ['ID'],
            'filter' => $filter,
            'limit' => 1,
        ]);

        $row = $result->fetch();

        return $row === false ? null : (int) $row['ID'];
    }
}
