<?php

declare(strict_types=1);

namespace Project\Bitrix\IBlock;

use InvalidArgumentException;
use LogicException;

/**
 * Checks whether an information-block element matching a Legacy API filter exists.
 *
 * The filter uses the CIBlockElement::GetList() filter syntax.
 */
final class LegacyElementChecker
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

        if (!class_exists(\CIBlockElement::class)) {
            throw new LogicException(
                'The Bitrix iblock module is not available. Load the iblock module before using LegacyElementChecker.'
            );
        }

        $result = \CIBlockElement::GetList(
            false,
            $filter,
            false,
            ['nTopCount' => 1],
            ['ID']
        );

        $row = $result->Fetch();

        return $row === false ? null : (int) $row['ID'];
    }
}
