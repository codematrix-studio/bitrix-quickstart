<?php

declare(strict_types=1);

require_once __DIR__ . '/../local/php_interface/classes/Bitrix/IBlock/ElementRepository.php';
require_once __DIR__ . '/../local/php_interface/classes/Bitrix/IBlock/SectionRepository.php';
require_once __DIR__ . '/../local/php_interface/legacy/Lema/IBlock/Element.php';
require_once __DIR__ . '/../local/php_interface/legacy/Lema/IBlock/Section.php';

function iblockExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$elementD7Params = null;
$element = new Project\Bitrix\IBlock\ElementRepository(
    static fn (string $module): bool => $module === 'iblock',
    static function (array $params) use (&$elementD7Params): array {
        $elementD7Params = $params;
        return [
            ['ID' => 12, 'NAME' => 'Element 12'],
            ['ID' => 13, 'NAME' => 'Element 13'],
        ];
    },
    static fn (...$arguments): array => [
        ['ID' => 21, 'NAME' => 'Legacy element'],
    ],
    static fn ($id, array $params): array => ['ID' => $id, 'SELECT' => $params],
    static fn (array $params): int => (int) $params['IBLOCK_ID'] * 10
);

$d7Rows = $element->getListD7(5, ['filter' => ['ACTIVE' => 'N', 'NAME' => 'Test']]);
iblockExpect(array_keys($d7Rows) === [12, 13], 'D7 element rows must be keyed by ID.');
iblockExpect($elementD7Params['filter']['IBLOCK_ID'] === 5, 'D7 element query must be restricted to the requested iblock.');
iblockExpect($elementD7Params['filter']['ACTIVE'] === 'Y', 'getListD7 must preserve the legacy active-only behavior.');

$allRows = $element->getAllD7(7, ['filter' => ['NAME' => 'All']]);
iblockExpect($elementD7Params['filter']['IBLOCK_ID'] === 7, 'getAllD7 must force the requested iblock ID.');
iblockExpect(!array_key_exists('ACTIVE', $elementD7Params['filter']), 'getAllD7 must not force ACTIVE=Y, matching the old method.');

$legacyArgs = null;
$elementLegacy = new Project\Bitrix\IBlock\ElementRepository(
    static fn (): bool => true,
    null,
    static function (...$arguments) use (&$legacyArgs): array {
        $legacyArgs = $arguments;
        return [['ID' => 31, 'NAME' => 'Legacy']];
    }
);
$legacyRows = $elementLegacy->getList(9, ['filter' => ['ACTIVE' => 'N'], 'arNavStartParams' => ['nPageSize' => 10]]);
iblockExpect(isset($legacyRows[31]), 'Legacy element rows must be keyed by ID.');
iblockExpect($legacyArgs[1]['IBLOCK_ID'] === 9, 'Legacy element query must not be allowed to switch iblocks.');
iblockExpect($legacyArgs[1]['ACTIVE'] === 'N', 'Explicit ACTIVE filter must remain configurable.');
iblockExpect($legacyArgs[3]['nPageSize'] === 10, 'Legacy navigation parameters must retain their old position.');

iblockExpect($element->getById(44, ['select' => ['ID']])['ID'] === 44, 'D7 getById callback must be used.');
iblockExpect($element->getCount(6) === 60, 'D7 getCount callback must receive the iblock ID.');

// Section repository: D7 and legacy argument order.
$sectionD7Params = null;
$section = new Project\Bitrix\IBlock\SectionRepository(
    static fn (): bool => true,
    static function (array $params) use (&$sectionD7Params): array {
        $sectionD7Params = $params;
        return [['ID' => 4, 'NAME' => 'Section']];
    },
    static fn (...$arguments): array => [['ID' => 8, 'NAME' => 'Legacy section']]
);
$sectionRows = $section->getListD7(3);
iblockExpect(isset($sectionRows[4]), 'D7 section rows must be keyed by ID.');
iblockExpect($sectionD7Params['filter']['IBLOCK_ID'] === 3 && $sectionD7Params['filter']['ACTIVE'] === 'Y', 'D7 section filter must contain iblock ID and ACTIVE=Y.');

$sectionLegacyArgs = null;
$sectionLegacy = new Project\Bitrix\IBlock\SectionRepository(
    static fn (): bool => true,
    null,
    static function (...$arguments) use (&$sectionLegacyArgs): array {
        $sectionLegacyArgs = $arguments;
        return [['ID' => 8, 'NAME' => 'Legacy section']];
    }
);
$sectionLegacy->getList(2, ['bIncCnt' => true, 'arSelect' => ['ID', 'NAME'], 'arNavStartParams' => ['nPageSize' => 5]]);
iblockExpect($sectionLegacyArgs[2] === true, 'Section include-count argument must retain its legacy position.');
iblockExpect($sectionLegacyArgs[3] === ['ID', 'NAME'], 'Section select argument must retain its legacy position.');
iblockExpect($sectionLegacyArgs[4]['nPageSize'] === 5, 'Section navigation argument must retain its legacy position.');

// Old class names and constants remain available through explicit adapters.
iblockExpect(\Lema\IBlock\Element::CLASS_NAME === '\\Bitrix\\Iblock\\ElementTable', 'Legacy Element class constant must remain available.');
iblockExpect(\Lema\IBlock\Section::OLD_CLASS_NAME === '\\CIBlockSection', 'Legacy Section class constant must remain available.');

$failed = false;
try {
    (new Project\Bitrix\IBlock\ElementRepository())->getList(1);
} catch (RuntimeException $exception) {
    $failed = true;
}
iblockExpect($failed, 'Repository must fail explicitly when the iblock module is unavailable.');

echo "Проверки IBlock Element/Section пройдены.\n";
