<?php

declare(strict_types=1);

namespace Project\Bitrix\IBlock;

use Closure;
use RuntimeException;

/**
 * Репозиторий элементов инфоблока.
 *
 * Поддерживает D7 ORM и старый CIBlockElement. Зависимости можно передать
 * через callbacks — это позволяет тестировать преобразование параметров без ядра.
 */
class ElementRepository
{
    private ?Closure $moduleLoader;
    private ?Closure $d7List;
    private ?Closure $legacyList;
    private ?Closure $d7GetByPrimary;
    private ?Closure $d7GetCount;

    public function __construct(
        ?callable $moduleLoader = null,
        ?callable $d7List = null,
        ?callable $legacyList = null,
        ?callable $d7GetByPrimary = null,
        ?callable $d7GetCount = null
    ) {
        $this->moduleLoader = $moduleLoader === null ? null : Closure::fromCallable($moduleLoader);
        $this->d7List = $d7List === null ? null : Closure::fromCallable($d7List);
        $this->legacyList = $legacyList === null ? null : Closure::fromCallable($legacyList);
        $this->d7GetByPrimary = $d7GetByPrimary === null ? null : Closure::fromCallable($d7GetByPrimary);
        $this->d7GetCount = $d7GetCount === null ? null : Closure::fromCallable($d7GetCount);
    }

    public function getById($elementId, array $params = [])
    {
        $this->ensureModule();

        if ($this->d7GetByPrimary !== null) {
            return ($this->d7GetByPrimary)($elementId, $params);
        }

        if (!class_exists('Bitrix\\Iblock\\ElementTable')) {
            throw new RuntimeException('D7-класс ElementTable недоступен.');
        }

        $result = \Bitrix\Iblock\ElementTable::getByPrimary($elementId, $params);

        return is_object($result) && method_exists($result, 'fetch') ? $result->fetch() : null;
    }

    /**
     * Список активных элементов через D7. Фильтр инфоблока и ACTIVE=Y задаются явно.
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
     * Список элементов через CIBlockElement::GetList.
     * Ключи параметров соответствуют старому API lema.lib.
     */
    public function getList($iblockId, array $params = []): array
    {
        $this->ensureModule();

        $order = $params['order'] ?? ['SORT' => 'ASC'];
        $filter = is_array($params['filter'] ?? null) ? $params['filter'] : [];
        $filter['IBLOCK_ID'] = (int) $iblockId;
        $filter = array_replace(['ACTIVE' => 'Y'], $filter);
        $groupBy = $params['bIncCnt'] ?? false;
        $navigation = $params['arNavStartParams'] ?? false;
        $select = $params['arSelect'] ?? [];

        $result = $this->legacyList !== null
            ? ($this->legacyList)($order, $filter, $groupBy, $navigation, $select)
            : $this->callLegacyList($order, $filter, $groupBy, $navigation, $select);

        return $this->keyRowsById($this->fetchRows($result, true));
    }

    public function getCount($iblockId, array $params = [])
    {
        $this->ensureModule();
        $params['IBLOCK_ID'] = (int) $iblockId;

        if ($this->d7GetCount !== null) {
            return ($this->d7GetCount)($params);
        }

        if (!class_exists('Bitrix\\Iblock\\ElementTable') || !is_callable(['Bitrix\\Iblock\\ElementTable', 'getCount'])) {
            throw new RuntimeException('Метод ElementTable::getCount() недоступен.');
        }

        return \Bitrix\Iblock\ElementTable::getCount($params);
    }

    /**
     * Старый метод возвращал только строки текущей страницы, без HTML навигации.
     */
    public function getListPagination($iblockId, array $params = []): array
    {
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

    public function getAll($iblockId, array $params = [], $pagination = false): array
    {
        return $pagination
            ? $this->getListPagination($iblockId, $params)
            : $this->getList($iblockId, $params);
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
        if (!class_exists('Bitrix\\Iblock\\ElementTable')) {
            throw new RuntimeException('D7-класс ElementTable недоступен.');
        }

        return \Bitrix\Iblock\ElementTable::getList($params);
    }

    private function callLegacyList($order, $filter, $groupBy, $navigation, $select)
    {
        if (!class_exists('CIBlockElement') || !is_callable(['CIBlockElement', 'GetList'])) {
            throw new RuntimeException('Класс CIBlockElement недоступен.');
        }

        return \CIBlockElement::GetList($order, $filter, $groupBy, $navigation, $select);
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
