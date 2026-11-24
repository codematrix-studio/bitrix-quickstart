<?php

declare(strict_types=1);

namespace Vendor\AdvancedModule\Install\Migration;

use Bitrix\Main\DB\Connection;

/**
 * Контракт одной неизменяемой forward-only миграции.
 */
interface MigrationInterface
{
    public function getId(): string;

    public function isTransactional(): bool;

    public function up(Connection $connection): void;
}
