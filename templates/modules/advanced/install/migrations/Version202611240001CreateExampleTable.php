<?php

declare(strict_types=1);

namespace Vendor\AdvancedModule\Install\Migration;

use Bitrix\Main\DB\Connection;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;

/**
 * Пример forward-only миграции с созданием таблицы модуля.
 */
final class Version202611240001CreateExampleTable implements MigrationInterface
{
    public function getId(): string
    {
        return '202611240001_create_example_table';
    }

    /**
     * CREATE TABLE может не поддерживать транзакционный откат на конкретной СУБД.
     */
    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Connection $connection): void
    {
        $connection->createTable(
            'b_vendor_advancedmodule_example',
            [
                'ID' => new IntegerField('ID'),
                'NAME' => new StringField('NAME', ['size' => 255]),
            ],
            ['ID'],
            ['ID']
        );
    }
}
