<?php

/**
 * Совместимый класс OpenGraph из lema.lib.
 */

namespace Lema\Seo;

class OpenGraph extends \Lema\Base\Markup
{
    public function setPrefix(): void
    {
        $this->prefix = 'og:';
    }
}
