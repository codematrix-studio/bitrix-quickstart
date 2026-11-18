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
        $filter = array_replace(['ACTIVE' => 'Y'], $filter);
        $includeCount = $params['bIncCnt'] ?? false;
        $select = $params['arSelect'] ?? [];
        $navigation = $params['arNavStartParams'] ?? false;

        $result = $this->legacyList !== null
            ? ($this->legacyList)($order, $filter, $includeCount, $select, $navigation)
            : $this->callLegacyList($order, $filter, $includeCount, $select, $navigation);

        return $this->keyRowsById($this->fetchRows($result, true));
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
