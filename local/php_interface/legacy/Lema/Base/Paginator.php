<?php

/**
 * Compatibility adapter for projects migrating from lema.lib.
 *
 * Load after Composer autoloading. The legacy namespace is intentionally
 * not registered globally to avoid collisions with an existing lema.lib.
 */

namespace Lema\Base;

class Paginator extends \Project\Support\ArrayPaginator
{
    protected static $instance = null;
}
