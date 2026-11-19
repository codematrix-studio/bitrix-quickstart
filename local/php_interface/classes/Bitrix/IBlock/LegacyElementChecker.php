<?php

declare(strict_types=1);

namespace Project\Bitrix\IBlock;

use InvalidArgumentException;
use LogicException;

/**
 * Проверяет, существует ли элемент инфоблока, соответствующий фильтру старого API.
 *
 * Фильтр задаётся в формате метода CIBlockElement::GetList().
 */
final class LegacyElementChecker
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
