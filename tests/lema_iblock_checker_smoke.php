<?php

declare(strict_types=1);

namespace Bitrix\Iblock {
    final class ElementCheckerTestResult
    {
        private array $rows;

        public function __construct(array $rows)
        {
            $this->rows = $rows;
        }

        public function fetch()
        {
            return array_shift($this->rows) ?: false;
        }
    }

    final class ElementTable
    {
        public static array $calls = [];
        public static array $rows = [];

        public static function getList(array $params): ElementCheckerTestResult
        {
            self::$calls[] = $params;

            return new ElementCheckerTestResult(self::$rows);
        }
    }
}

namespace {
    final class LegacyElementCheckerTestResult
    {
        private array $rows;

        public function __construct(array $rows)
        {
            $this->rows = $rows;
        }

        public function Fetch()
        {
            return array_shift($this->rows) ?: false;
        }
    }

    final class CIBlockElement
    {
        public static array $calls = [];
        public static array $rows = [];

        public static function GetList($order, $filter, $groupBy, $navigation, $select): LegacyElementCheckerTestResult
        {
            self::$calls[] = [$order, $filter, $groupBy, $navigation, $select];

            return new LegacyElementCheckerTestResult(self::$rows);
        }
    }

    require_once __DIR__ . '/../local/php_interface/classes/Bitrix/IBlock/ElementChecker.php';
    require_once __DIR__ . '/../local/php_interface/classes/Bitrix/IBlock/LegacyElementChecker.php';

    function checkerExpect(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new \RuntimeException($message);
        }
    }

    function checkerExpectInvalidFilter(callable $callback, string $message): void
    {
        try {
            $callback();
        } catch (\InvalidArgumentException $exception) {
            return;
        }

        throw new \RuntimeException($message);
    }

    // D7 ORM checker.
    \Bitrix\Iblock\ElementTable::$calls = [];
    \Bitrix\Iblock\ElementTable::$rows = [
        ['ID' => '42'],
        ['ID' => '43'],
    ];

    $d7 = new \Project\Bitrix\IBlock\ElementChecker();
    $filter = ['=IBLOCK_ID' => 7, '=CODE' => 'example'];

    checkerExpect($d7->findId($filter) === 42, 'D7 findId() must return the first matching element ID.');
    $query = \Bitrix\Iblock\ElementTable::$calls[0] ?? [];
    checkerExpect(($query['select'] ?? null) === ['ID'], 'D7 checker must select only ID.');
    checkerExpect(($query['filter'] ?? null) === $filter, 'D7 checker must pass the filter unchanged.');
    checkerExpect(($query['limit'] ?? null) === 1, 'D7 checker must limit the query to one row.');

    checkerExpect($d7->exists($filter), 'D7 exists() must return true when an element is found.');

    \Bitrix\Iblock\ElementTable::$rows = [];
    checkerExpect($d7->findId($filter) === null, 'D7 findId() must return null when no element is found.');
    checkerExpect(!$d7->exists($filter), 'D7 exists() must return false when no element is found.');

    $d7CallCount = count(\Bitrix\Iblock\ElementTable::$calls);
    checkerExpectInvalidFilter(
        static fn () => $d7->exists([]),
        'D7 checker must reject an empty filter.'
    );
    checkerExpect(
        count(\Bitrix\Iblock\ElementTable::$calls) === $d7CallCount,
        'D7 checker must reject an empty filter before querying.'
    );

    // Legacy CIBlockElement checker.
    \CIBlockElement::$calls = [];
    \CIBlockElement::$rows = [['ID' => '91']];

    $legacy = new \Project\Bitrix\IBlock\LegacyElementChecker();
    $legacyFilter = ['IBLOCK_ID' => 12, '=NAME' => 'Legacy item'];

    checkerExpect($legacy->findId($legacyFilter) === 91, 'Legacy findId() must return the first matching element ID.');
    $arguments = \CIBlockElement::$calls[0] ?? null;
    checkerExpect(
        $arguments === [[], $legacyFilter, false, ['nTopCount' => 1], ['ID']],
        'Legacy checker must preserve CIBlockElement::GetList() argument order and query limits.'
    );

    checkerExpect($legacy->exists($legacyFilter), 'Legacy exists() must return true when an element is found.');

    \CIBlockElement::$rows = [];
    checkerExpect($legacy->findId($legacyFilter) === null, 'Legacy findId() must return null when no element is found.');
    checkerExpect(!$legacy->exists($legacyFilter), 'Legacy exists() must return false when no element is found.');

    $legacyCallCount = count(\CIBlockElement::$calls);
    checkerExpectInvalidFilter(
        static fn () => $legacy->findId([]),
        'Legacy checker must reject an empty filter.'
    );
    checkerExpect(
        count(\CIBlockElement::$calls) === $legacyCallCount,
        'Legacy checker must reject an empty filter before querying.'
    );

    echo "Проверки ElementChecker и LegacyElementChecker пройдены.\n";
}
