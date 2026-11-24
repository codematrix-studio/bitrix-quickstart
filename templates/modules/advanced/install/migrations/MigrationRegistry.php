<?php

declare(strict_types=1);

namespace Vendor\AdvancedModule\Install\Migration;

/**
 * Явный упорядоченный список миграций.
 *
 * Не сканируйте каталог и не подключайте произвольные PHP-файлы.
 */
final class MigrationRegistry
{
    /**
     * @return MigrationInterface[]
     */
    public static function all(): array
    {
        return [
            new Version202611240001CreateExampleTable(),
        ];
    }
}
