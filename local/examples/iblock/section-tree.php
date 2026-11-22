<?php

/**
 * Собирает иерархию активных разделов инфоблока.
 *
 * @param int $iblockId ID инфоблока.
 *
 * @return array<int, array<string, mixed>>
 */
function buildIblockSectionTree(int $iblockId): array
{
    if ($iblockId <= 0 || !\Bitrix\Main\Loader::includeModule('iblock')) {
        return [];
    }

    $filter = [
        'IBLOCK_ID' => $iblockId,
        'ACTIVE' => 'Y',
        'GLOBAL_ACTIVE' => 'Y',
    ];

    $select = [
        'ID',
        'NAME',
        'IBLOCK_SECTION_ID',
        'DEPTH_LEVEL',
        'SORT',
    ];

    $order = [
        'LEFT_MARGIN' => 'ASC',
        'SORT' => 'ASC',
    ];

    $result = [];
    $nodes = [];

    $sections = \CIBlockSection::getList($order, $filter, false, $select);

    while ($section = $sections->fetch()) {
        $sectionId = (int)$section['ID'];
        $parentId = (int)$section['IBLOCK_SECTION_ID'];

        $nodes[$sectionId] = $section;
        $nodes[$sectionId]['CHILDREN'] = [];

        if ($parentId > 0 && isset($nodes[$parentId])) {
            $nodes[$parentId]['CHILDREN'][$sectionId] = &$nodes[$sectionId];
        } else {
            $result[$sectionId] = &$nodes[$sectionId];
        }
    }

    return $result;
}
