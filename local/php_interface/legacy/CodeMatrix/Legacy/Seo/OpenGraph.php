<?php

/**
 * Совместимый класс OpenGraph из lema.lib.
 */

namespace CodeMatrix\Legacy\Seo;

class OpenGraph extends \CodeMatrix\Legacy\Base\Markup
{
    public function setPrefix(): void
    {
        $this->prefix = 'og:';
    }
}
