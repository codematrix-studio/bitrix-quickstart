<?php

declare(strict_types=1);

namespace Project\Bitrix\IBlock;

use Bitrix\Iblock\ElementTable;
use InvalidArgumentException;
use LogicException;

/**
 * Checks whether an information-block element matching a filter exists.
 *
 * The filter uses the Bitrix D7 ORM filter syntax.
 */
final class ElementChecker
{
    /**
     * Returns true when at least one element matches the filter.
     *
     * @param array<string, mixed> $filter
     */
    public function exists(array $filter): bool
    {
        return $this->findId($filter) !== null;
    }

    /**
     * Returns the ID of the first matching element, or null when none exists.
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
