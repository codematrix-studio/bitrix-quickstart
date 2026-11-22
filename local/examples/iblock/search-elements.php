<?php

declare(strict_types=1);

use Bitrix\Main\Loader;
use RuntimeException;
use InvalidArgumentException;

/**
 * Ищет элементы и разделы инфоблока через поисковый индекс Битрикс.
 *
 * Возвращает ID отдельно: ITEM_ID с префиксом S относится к разделу.
 * Для работы должны быть доступны модули search и iblock.
 *
 * @return array{ELEMENTS: int[], SECTIONS: int[]}
 */
function searchIblockItems(
    string $query,
    int $iblockId,
    string $siteId,
    ?int $sectionId = null,
    int $limit = 50
): array {
    $query = trim($query);

    if ($query === '') {
        throw new InvalidArgumentException('Поисковый запрос не должен быть пустым.');
    }

    if ($iblockId < 1 || ($sectionId !== null && $sectionId < 1)) {
        throw new InvalidArgumentException('ID инфоблока и раздела должны быть положительными.');
    }

    if ($siteId === '') {
        throw new InvalidArgumentException('Не указан ID сайта.');
    }

    if ($limit < 1 || $limit > 100) {
        throw new InvalidArgumentException('Лимит должен находиться в диапазоне от 1 до 100.');
    }

    if (!Loader::includeModule('search') || !Loader::includeModule('iblock')) {
        throw new RuntimeException('Не удалось подключить модули search и iblock.');
    }

    $filter = [
        'SITE_ID' => $siteId,
        'QUERY' => $query,
        'PARAM2' => $iblockId,
    ];

    if ($sectionId !== null) {
        $filter['PARAMS'] = [
            'iblock_section' => $sectionId,
        ];
    }

    $search = new CSearch();
    $search->Search($filter);

    if ((int) $search->errorno !== 0) {
        throw new RuntimeException('Ошибка поиска по индексу Битрикс.');
    }

    $elements = [];
    $sections = [];

    while (($row = $search->Fetch()) !== false) {
        if (count($elements) + count($sections) >= $limit) {
            break;
        }

        $itemId = (string) ($row['ITEM_ID'] ?? '');

        if (preg_match('/^S([1-9][0-9]*)$/', $itemId, $matches) === 1) {
            $sections[(int) $matches[1]] = (int) $matches[1];
            continue;
        }

        if (preg_match('/^[1-9][0-9]*$/', $itemId) === 1) {
            $elements[(int) $itemId] = (int) $itemId;
        }
    }

    return [
        'ELEMENTS' => array_values($elements),
        'SECTIONS' => array_values($sections),
    ];
}
