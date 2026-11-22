<?php

/**
 * Совместимая база meta-разметки из lema.lib.
 */

namespace CodeMatrix\Legacy\Base;

abstract class Markup extends \Project\Seo\MetaMarkup
{
    /**
     * В исходном Markup::getItemMeta() пустые значения и строка "0"
     * считались отсутствующими. Сохраняем это только в legacy-слое.
     */
    public function getItemMeta($type)
    {
        $item = $this->getItem($type);

        if (!$item) {
            return false;
        }

        return parent::getItemMeta($type);
    }
}
