<?php

/**
 * Совместимый адаптер старого CodeMatrix\Legacy\Template\TemplateHelper.
 * Подключать после Composer autoload и адаптеров Item/Section.
 */

namespace CodeMatrix\Legacy\Template;

require_once __DIR__ . '/Item.php';
require_once __DIR__ . '/Section.php';

class TemplateHelper extends \Project\Bitrix\IBlock\TemplateHelper
{
    public function items(bool $objectData = true): array
    {
        $items = parent::items($objectData);
        if (!$objectData) {
            return $items;
        }

        foreach ($items as $key => $item) {
            $class = $item instanceof \Project\Bitrix\IBlock\SectionView
                ? Section::class
                : Item::class;

            $items[$key] = new $class($item->toArray(), $item->editId(false));
        }

        return $items;
    }

    public function sections(bool $objectData = true): array
    {
        return $this->items($objectData);
    }

    public function item(): Item
    {
        $item = parent::item();

        return new Item($item->toArray(), $item->editId(false));
    }
}
