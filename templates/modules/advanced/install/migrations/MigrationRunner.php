<?php

declare(strict_types=1);

namespace Vendor\AdvancedModule\Install\Migration;

use Bitrix\Main\DB\Connection;
use Bitrix\Main\ORM\Fields\StringField;
use ReflectionClass;
use RuntimeException;
use Throwable;

/**
 * Выполняет forward-only миграции и хранит ledger применённых ID.
 *
 * Блокировка использует Bitrix Connection::lock(): поддерживаются MySQL
 * и PostgreSQL. Для других драйверов runner откажется запускаться.
 */
final class MigrationRunner
{
    private const LEDGER_TABLE = 'b_vendor_advancedmodule_migrations';
    private const LOCK_NAME = 'vendor.advancedmodule.migrations';
    private const REQUIRED_LEDGER_FIELDS = ['MIGRATION_ID', 'APPLIED_AT', 'CHECKSUM'];

    /**
     * Выполняет ещё не применённые миграции в порядке реестра.
     *
     * @param MigrationInterface[] $migrations
     *
     * @return string[] ID миграций, применённых этим запуском.
     */
    public static function run(Connection $connection, array $migrations): array
    {
        self::assertSupportedDatabase($connection);
        self::validateRegistry($migrations);
        self::ensureLedger($connection);

        if (!$connection->lock(self::LOCK_NAME, 10)) {
            throw new RuntimeException('Не удалось получить блокировку запуска миграций.');
        }

        $failure = null;

        try {
            $known = [];
            foreach ($migrations as $migration) {
                $known[$migration->getId()] = [
                    'migration' => $migration,
                    'checksum' => self::checksum($migration),
                ];
            }

            $applied = self::readLedger($connection);

            foreach ($applied as $id => $checksum) {
                if (!isset($known[$id])) {
                    throw new RuntimeException(sprintf(
                        'В ledger найдена неизвестная миграция "%s". Не удаляйте её запись вручную.',
                        $id
                    ));
                }

                if (!hash_equals($known[$id]['checksum'], $checksum)) {
                    throw new RuntimeException(sprintf(
                        'Контрольная сумма применённой миграции "%s" изменилась. Создайте новую миграцию вместо редактирования старой.',
                        $id
                    ));
                }
            }

            $newlyApplied = [];

            foreach ($known as $id => $entry) {
                if (isset($applied[$id])) {
                    continue;
                }

                /** @var MigrationInterface $migration */
                $migration = $entry['migration'];
                $transactionStarted = false;

                try {
                    if ($migration->isTransactional()) {
                        $connection->startTransaction();
                        $transactionStarted = true;
                    }

                    $migration->up($connection);

                    $connection->add(self::LEDGER_TABLE, [
                        'MIGRATION_ID' => $id,
                        'APPLIED_AT' => date('Y-m-d H:i:s'),
                        'CHECKSUM' => $entry['checksum'],
                    ], null);

                    if ($transactionStarted) {
                        $connection->commitTransaction();
                        $transactionStarted = false;
                    }

                    $newlyApplied[] = $id;
                } catch (Throwable $exception) {
                    if ($transactionStarted) {
                        try {
                            $connection->rollbackTransaction();
                        } catch (Throwable $rollbackException) {
                            throw new RuntimeException(
                                sprintf(
                                    'Миграция "%s" завершилась ошибкой, а транзакцию не удалось откатить: %s',
                                    $id,
                                    $rollbackException->getMessage()
                                ),
                                0,
                                $exception
                            );
                        }
                    }

                    throw new RuntimeException(
                        sprintf('Ошибка выполнения миграции "%s": %s', $id, $exception->getMessage()),
                        0,
                        $exception
                    );
                }
            }

            return $newlyApplied;
        } catch (Throwable $exception) {
            $failure = $exception;
            throw $exception;
        } finally {
            try {
                if (!$connection->unlock(self::LOCK_NAME)) {
                    throw new RuntimeException('Bitrix не смог снять блокировку migration runner.');
                }
            } catch (Throwable $unlockException) {
                if ($failure !== null) {
                    error_log('Migration runner: не удалось снять блокировку: ' . $unlockException->getMessage());
                } else {
                    throw $unlockException;
                }
            }
        }
    }

    private static function assertSupportedDatabase(Connection $connection): void
    {
        $type = $connection->getType();

        if (!in_array($type, ['mysql', 'pgsql'], true)) {
            throw new RuntimeException(sprintf(
                'Migration runner поддерживает только MySQL и PostgreSQL с работающим Connection::lock(); текущий драйвер: "%s".',
                $type
            ));
        }
    }

    /**
     * @param MigrationInterface[] $migrations
     */
    private static function validateRegistry(array $migrations): void
    {
        $previousId = '';

        foreach ($migrations as $migration) {
            if (!$migration instanceof MigrationInterface) {
                throw new RuntimeException('Реестр содержит объект, не реализующий MigrationInterface.');
            }

            $id = $migration->getId();

            if (!preg_match('/^\d{12}_[a-z0-9_]+$/D', $id)) {
                throw new RuntimeException(sprintf('Некорректный ID миграции "%s".', $id));
            }

            if ($previousId !== '' && strcmp($id, $previousId) <= 0) {
                throw new RuntimeException('Реестр миграций должен быть уникальным и отсортированным по ID.');
            }

            $previousId = $id;
        }
    }

    private static function ensureLedger(Connection $connection): void
    {
        if (!$connection->isTableExists(self::LEDGER_TABLE)) {
            $connection->createTable(
                self::LEDGER_TABLE,
                [
                    'MIGRATION_ID' => new StringField('MIGRATION_ID', ['size' => 64]),
                    'APPLIED_AT' => new StringField('APPLIED_AT', ['size' => 19]),
                    'CHECKSUM' => new StringField('CHECKSUM', ['size' => 64]),
                ],
                ['MIGRATION_ID']
            );
        }

        $fields = array_keys($connection->getTableFields(self::LEDGER_TABLE));
        foreach (self::REQUIRED_LEDGER_FIELDS as $requiredField) {
            if (!in_array($requiredField, $fields, true)) {
                throw new RuntimeException(sprintf(
                    'Таблица ledger "%s" не содержит обязательную колонку "%s".',
                    self::LEDGER_TABLE,
                    $requiredField
                ));
            }
        }
    }

    /**
     * @return array<string, string> migration ID => SHA-256
     */
    private static function readLedger(Connection $connection): array
    {
        $result = $connection->query(
            'SELECT MIGRATION_ID, CHECKSUM FROM ' . self::LEDGER_TABLE . ' ORDER BY MIGRATION_ID'
        );
        $applied = [];

        while ($row = $result->fetch()) {
            $applied[(string) $row['MIGRATION_ID']] = (string) $row['CHECKSUM'];
        }

        return $applied;
    }

    private static function checksum(MigrationInterface $migration): string
    {
        $file = (new ReflectionClass($migration))->getFileName();

        if (!is_string($file) || !is_file($file)) {
            throw new RuntimeException(sprintf(
                'Невозможно вычислить checksum файла миграции "%s".',
                $migration->getId()
            ));
        }

        $checksum = hash_file('sha256', $file);

        if (!is_string($checksum)) {
            throw new RuntimeException(sprintf(
                'Не удалось прочитать файл миграции "%s".',
                $migration->getId()
            ));
        }

        return $checksum;
    }
}
