<?php

declare(strict_types=1);

namespace Project\Bitrix\IBlock;

use Closure;
use RuntimeException;

/**
 * Репозиторий разделов инфоблока: D7 ORM и старый CIBlockSection.
 */
class SectionRepository
{
    private ?Closure $moduleLoader;
    private ?Closure $d7List;
    private ?Closure $legacyList;

    public function __construct(
        ?callable $moduleLoader = null,
        ?callable $d7List = null,
        ?callable $legacyList = null
    ) {
        $this->moduleLoader = $moduleLoader === null ? null : Closure::fromCallable($moduleLoader);
        $this->d7List = $d7List === null ? null : Closure::fromCallable($d7List);
        $this->legacyList = $legacyList === null ? null : Closure::fromCallable($legacyList);
    }

    /**
     * Список активных разделов через D7.
     */
    public function getListD7($iblockId, array $params = []): array
    {
        $this->ensureModule();
        $params['filter'] = is_array($params['filter'] ?? null) ? $params['filter'] : [];
        $params['filter']['IBLOCK_ID'] = (int) $iblockId;
        $params['filter']['ACTIVE'] = 'Y';

        $result = $this->d7List !== null
            ? ($this->d7List)($params)
            : $this->callD7List($params);

        return $this->keyRowsById($this->fetchRows($result));
    }

    /**
     * Список разделов через CIBlockSection::GetList.
     * Порядок аргументов сохранён: order, filter, count, select, navigation.
     */
    public function getList($iblockId, array $params = []): array
    {
        $this->ensureModule();

        $order = $params['order'] ?? ['SORT' => 'ASC'];
        $filter = is_array($params['filter'] ?? null) ? $params['filter'] : [];
        $filter['IBLOCK_ID'] = (int) $iblockId;
        $includeInactive = !empty($params['includeInactive']);
        if (!$includeInactive) {
            $filter = array_replace(['ACTIVE' => 'Y'], $filter);
        }
        $includeCount = $params['bIncCnt'] ?? false;
        $select = $params['arSelect'] ?? [];
        $navigation = $params['arNavStartParams'] ?? false;

        $result = $this->legacyList !== null
            ? ($this->legacyList)($order, $filter, $includeCount, $select, $navigation)
            : $this->callLegacyList($order, $filter, $includeCount, $select, $navigation);

        return $this->keyRowsById($this->fetchRows($result, true));
    }

    /**
     * Legacy-выборка разделов без принудительного фильтра ACTIVE=Y.
     * Нужна, в частности, для выгрузок, в которых должны присутствовать
     * отключённые разделы и их дерево.
     */
    public function getListIncludingInactive($iblockId, array $params = []): array
    {
        $params['includeInactive'] = true;

        return $this->getList($iblockId, $params);
    }

    public function getAllD7($iblockId, array $params = []): array
    {
        $this->ensureModule();
        $params['filter'] = is_array($params['filter'] ?? null) ? $params['filter'] : [];
        $params['filter']['IBLOCK_ID'] = (int) $iblockId;

        $result = $this->d7List !== null
            ? ($this->d7List)($params)
            : $this->callD7List($params);

        return $this->keyRowsById($this->fetchRows($result));
    }

    /**
     * Получает потомков нескольких корневых разделов одним D7-запросом.
     * Возвращает null, если SectionTable недоступен и D7 callback не внедрён.
     */
    public function getDescendantsByIntervalsD7($iblockId, array $intervals): ?array
    {
        $this->ensureModule();

        if ($this->d7List === null && !class_exists('Bitrix\\\\Iblock\\\\SectionTable')) {
            return null;
        }

        $conditions = [];
        foreach ($intervals as $interval) {
            if (!is_array($interval)
                || !isset($interval['left'], $interval['right'])
                || !is_numeric($interval['left'])
                || !is_numeric($interval['right'])
            ) {
                throw new RuntimeException('Некорректный интервал раздела для D7-выборки.');
            }

            $left = (int) $interval['left'];
            $right = (int) $interval['right'];
            if ($left < 1 || $right <= $left) {
                throw new RuntimeException('Границы интервала раздела должны быть положительными и упорядоченными.');
            }

            $conditions[] = [
                '>LEFT_MARGIN' => $left,
                '<RIGHT_MARGIN' => $right,
            ];
        }

        if ($conditions === []) {
            return [];
        }

        $params = [
            'order' => ['LEFT_MARGIN' => 'ASC'],
            'select' => ['ID', 'NAME', 'IBLOCK_SECTION_ID'],
            'filter' => [
                'IBLOCK_ID' => (int) $iblockId,
                ['LOGIC' => 'OR', ...$conditions],
            ],
        ];

        $result = $this->d7List !== null
            ? ($this->d7List)($params)
            : $this->callD7List($params);

        return $this->keyRowsById($this->fetchRows($result));
    }

    public function getAll($iblockId, array $params = []): array
    {
        return $this->getList($iblockId, $params);
    }

    private function ensureModule(): void
    {
        if ($this->moduleLoader !== null) {
            $loaded = (bool) ($this->moduleLoader)('iblock');
        } elseif (class_exists('Bitrix\\Main\\Loader')) {
            $loaded = \Bitrix\Main\Loader::includeModule('iblock');
        } elseif (class_exists('CModule')) {
            $loaded = \CModule::IncludeModule('iblock');
        } else {
            $loaded = false;
        }

        if (!$loaded) {
            throw new RuntimeException('Не удалось подключить модуль iblock.');
        }
    }

    private function callD7List(array $params)
    {
        if (!class_exists('Bitrix\\Iblock\\SectionTable')) {
            throw new RuntimeException('D7-класс SectionTable недоступен.');
        }

        return \Bitrix\Iblock\SectionTable::getList($params);
    }

    private function callLegacyList($order, $filter, $includeCount, $select, $navigation)
    {
        if (!class_exists('CIBlockSection') || !is_callable(['CIBlockSection', 'GetList'])) {
            throw new RuntimeException('Класс CIBlockSection недоступен.');
        }

        return \CIBlockSection::GetList($order, $filter, $includeCount, $select, $navigation);
    }

    private function fetchRows($result, bool $legacy = false): array
    {
        if (is_array($result)) {
            return $result;
        }

        if (!is_object($result)) {
            return [];
        }

        $rows = [];
        $method = $legacy && method_exists($result, 'GetNext') ? 'GetNext' : 'fetch';

        if (!method_exists($result, $method)) {
            return [];
        }

        while ($row = $result->{$method}()) {
            if (is_array($row)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    private function keyRowsById(array $rows): array
    {
        $result = [];
        foreach ($rows as $row) {
            if (is_array($row) && isset($row['ID'])) {
                $result[$row['ID']] = $row;
            }
        }

        return $result;
    }
}
