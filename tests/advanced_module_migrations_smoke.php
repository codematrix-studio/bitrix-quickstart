<?php

declare(strict_types=1);

namespace Bitrix\Main\DB {
    class Connection
    {
    }
}

namespace Bitrix\Main\ORM\Fields {
    class StringField
    {
        public function __construct(public string $name, public array $parameters = [])
        {
        }
    }

    class IntegerField
    {
        public function __construct(public string $name, public array $parameters = [])
        {
        }
    }
}

namespace Bitrix\Main {
    final class FakeResult
    {
        private array $rows;

        public function __construct(array $rows)
        {
            $this->rows = array_values($rows);
        }

        public function fetch()
        {
            return array_shift($this->rows) ?: false;
        }
    }

    final class Application
    {
        public static $connection;

        public static function getConnection()
        {
            return self::$connection;
        }
    }

    final class Loader
    {
        public static function includeModule(string $moduleId): bool
        {
            return true;
        }
    }
}

namespace Bitrix\Main\Localization {
    final class Loc
    {
        public static function getMessage(string $code): string
        {
            return $code;
        }
    }
}

namespace Bitrix\Main\Config {
    final class Option
    {
        public static function get(string $moduleId, string $name, string $default = ''): string
        {
            return $default;
        }

        public static function set(string $moduleId, string $name, string $value): void
        {
        }

        public static function delete(string $moduleId, array $filter = []): void
        {
        }
    }
}

namespace {
    use Bitrix\Main\DB\Connection;
    use Vendor\AdvancedModule\Install\Migration\MigrationInterface;
    use Vendor\AdvancedModule\Install\Migration\MigrationRunner;

    class FakeConnection extends Connection
    {
        public array $tables = [];
        public bool $locked = false;
        public bool $lockResult = true;
        public int $lockCalls = 0;
        public int $unlockCalls = 0;
        private ?array $transactionSnapshot = null;

        public function getType(): string
        {
            return 'mysql';
        }

        public function lock(string $name, int $timeout = 0): bool
        {
            $this->lockCalls++;
            if (!$this->lockResult || $this->locked) {
                return false;
            }

            $this->locked = true;
            return true;
        }

        public function unlock(string $name): bool
        {
            $this->unlockCalls++;
            $this->locked = false;
            return true;
        }

        public function isTableExists(string $tableName): bool
        {
            return isset($this->tables[$tableName]);
        }

        public function createTable(string $tableName, array $fields, array $primary = [], array $autoincrement = []): void
        {
            if (!isset($this->tables[$tableName])) {
                $this->tables[$tableName] = [
                    'fields' => array_fill_keys(array_keys($fields), true),
                    'rows' => [],
                ];
            }
        }

        public function getTableFields(string $tableName): array
        {
            return $this->tables[$tableName]['fields'] ?? [];
        }

        public function query(string $sql): \Bitrix\Main\FakeResult
        {
            preg_match('/FROM\s+([a-z0-9_]+)/i', $sql, $matches);
            $tableName = $matches[1] ?? '';
            return new \Bitrix\Main\FakeResult($this->tables[$tableName]['rows'] ?? []);
        }

        public function add(string $tableName, array $data, $identity = 'ID')
        {
            if (!isset($this->tables[$tableName])) {
                throw new RuntimeException('Table does not exist: ' . $tableName);
            }

            $this->tables[$tableName]['rows'][] = $data;
            return count($this->tables[$tableName]['rows']);
        }

        public function startTransaction(): void
        {
            $this->transactionSnapshot = $this->tables;
        }

        public function commitTransaction(): void
        {
            $this->transactionSnapshot = null;
        }

        public function rollbackTransaction(): void
        {
            if ($this->transactionSnapshot !== null) {
                $this->tables = $this->transactionSnapshot;
                $this->transactionSnapshot = null;
            }
        }

        public function seedLedger(string $id, string $checksum): void
        {
            $this->tables['b_vendor_advancedmodule_migrations']['rows'][] = [
                'MIGRATION_ID' => $id,
                'APPLIED_AT' => '2026-11-24 00:00:00',
                'CHECKSUM' => $checksum,
            ];
        }
    }

    final class SuccessfulMigration implements MigrationInterface
    {
        public static int $runs = 0;

        public function getId(): string
        {
            return '202611240002_successful_test';
        }

        public function isTransactional(): bool
        {
            return false;
        }

        public function up(Connection $connection): void
        {
            self::$runs++;
            $connection->createTable('b_test_successful_migration', [
                'ID' => new \Bitrix\Main\ORM\Fields\IntegerField('ID'),
            ], ['ID'], ['ID']);
        }
    }

    final class TransactionalFailingMigration implements MigrationInterface
    {
        public function getId(): string
        {
            return '202611240003_transactional_failure';
        }

        public function isTransactional(): bool
        {
            return true;
        }

        public function up(Connection $connection): void
        {
            $connection->createTable('b_test_transactional_rollback', [
                'ID' => new \Bitrix\Main\ORM\Fields\IntegerField('ID'),
            ], ['ID'], ['ID']);
            throw new RuntimeException('Expected test failure.');
        }
    }

    final class PartiallyFailingMigration implements MigrationInterface
    {
        public static bool $failFirstRun = true;
        public static int $runs = 0;

        public function getId(): string
        {
            return '202611240005_partial_failure';
        }

        public function isTransactional(): bool
        {
            return false;
        }

        public function up(Connection $connection): void
        {
            self::$runs++;
            $connection->createTable('b_test_partial_migration', [
                'ID' => new \Bitrix\Main\ORM\Fields\IntegerField('ID'),
            ], ['ID'], ['ID']);

            if (self::$failFirstRun) {
                self::$failFirstRun = false;
                throw new RuntimeException('Expected partial DDL failure.');
            }
        }
    }

    final class UnknownMigration implements MigrationInterface
    {
        public function getId(): string
        {
            return '202611240004_unknown_test';
        }

        public function isTransactional(): bool
        {
            return false;
        }

        public function up(Connection $connection): void
        {
        }
    }

    final class UnsupportedConnection extends FakeConnection
    {
        public function getType(): string
        {
            return 'mssql';
        }
    }

    define('B_PROLOG_INCLUDED', true);
    $GLOBALS['USER'] = new class {
        public function IsAdmin(): bool
        {
            return true;
        }
    };
    $GLOBALS['APPLICATION'] = new class {
        public function ThrowException(string $message): void
        {
        }
    };
    $GLOBALS['installedModules'] = [];

    class CModule
    {
    }

    function IncludeModuleLangFile(string $file): void
    {
    }

    function IsModuleInstalled(string $moduleId): bool
    {
        return $GLOBALS['installedModules'][$moduleId] ?? false;
    }

    function RegisterModule(string $moduleId): void
    {
        $GLOBALS['installedModules'][$moduleId] = true;
    }

    function UnRegisterModule(string $moduleId): void
    {
        unset($GLOBALS['installedModules'][$moduleId]);
    }

    require_once __DIR__ . '/../templates/modules/advanced/install/migrations/MigrationInterface.php';
    require_once __DIR__ . '/../templates/modules/advanced/install/migrations/MigrationRunner.php';
    require_once __DIR__ . '/../templates/modules/advanced/install/migrations/MigrationRegistry.php';
    require_once __DIR__ . '/../templates/modules/advanced/install/migrations/Version202611240001CreateExampleTable.php';
    require_once __DIR__ . '/../templates/modules/advanced/install/index.php';

    $assert = static function (bool $condition, string $message): void {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    };

    $db = new FakeConnection();
    $applied = MigrationRunner::run($db, [new SuccessfulMigration()]);
    $assert($applied === ['202611240002_successful_test'], 'Runner should return newly applied IDs.');
    $assert(SuccessfulMigration::$runs === 1, 'Migration should execute exactly once.');
    $assert($db->isTableExists('b_vendor_advancedmodule_migrations'), 'Runner should create the ledger.');
    $assert(count($db->tables['b_vendor_advancedmodule_migrations']['rows']) === 1, 'Ledger should record successful migration.');
    $assert($db->unlockCalls === 1 && !$db->locked, 'Runner must release its lock.');

    $again = MigrationRunner::run($db, [new SuccessfulMigration()]);
    $assert($again === [], 'A second run must skip an applied migration.');
    $assert(SuccessfulMigration::$runs === 1, 'An applied migration must not execute twice.');

    $failed = false;
    try {
        MigrationRunner::run($db, [new SuccessfulMigration(), new TransactionalFailingMigration()]);
    } catch (RuntimeException $exception) {
        $failed = true;
    }
    $assert($failed, 'Runner must stop on the first migration error.');
    $assert(!$db->isTableExists('b_test_transactional_rollback'), 'Transactional migration must roll back its changes.');
    $assert(count($db->tables['b_vendor_advancedmodule_migrations']['rows']) === 1, 'Failed migration must not be recorded.');

    $partialDb = new FakeConnection();
    $partialFailed = false;
    try {
        MigrationRunner::run($partialDb, [new PartiallyFailingMigration()]);
    } catch (RuntimeException $exception) {
        $partialFailed = true;
    }
    $assert($partialFailed, 'A non-transactional partial failure must stop the runner.');
    $assert($partialDb->isTableExists('b_test_partial_migration'), 'The test should demonstrate that DDL may remain after a partial failure.');
    $assert(count($partialDb->tables['b_vendor_advancedmodule_migrations']['rows']) === 0, 'A partially failed migration must not be marked as applied.');
    $partialApplied = MigrationRunner::run($partialDb, [new PartiallyFailingMigration()]);
    $assert($partialApplied === ['202611240005_partial_failure'], 'An idempotent non-transactional migration should be retryable.');
    $assert(PartiallyFailingMigration::$runs === 2, 'A partially failed migration should be retried exactly once.');

    $lockDb = new FakeConnection();
    $lockDb->lockResult = false;
    $lockFailed = false;
    try {
        MigrationRunner::run($lockDb, [new SuccessfulMigration()]);
    } catch (RuntimeException $exception) {
        $lockFailed = true;
    }
    $assert($lockFailed && $lockDb->unlockCalls === 0, 'Runner must not execute without acquiring the lock.');

    $unknownDb = new FakeConnection();
    MigrationRunner::run($unknownDb, []);
    $unknownDb->seedLedger('202611240099_removed_migration', str_repeat('a', 64));
    $unknownFailed = false;
    try {
        MigrationRunner::run($unknownDb, []);
    } catch (RuntimeException $exception) {
        $unknownFailed = true;
    }
    $assert($unknownFailed, 'Runner must reject unknown IDs in the ledger.');

    $checksumDb = new FakeConnection();
    MigrationRunner::run($checksumDb, []);
    $checksumDb->seedLedger('202611240002_successful_test', str_repeat('f', 64));
    $checksumFailed = false;
    try {
        MigrationRunner::run($checksumDb, [new SuccessfulMigration()]);
    } catch (RuntimeException $exception) {
        $checksumFailed = true;
    }
    $assert($checksumFailed, 'Runner must reject changed contents for an already applied migration ID.');

    $unsupportedFailed = false;
    try {
        MigrationRunner::run(new UnsupportedConnection(), []);
    } catch (RuntimeException $exception) {
        $unsupportedFailed = true;
    }
    $assert($unsupportedFailed, 'Runner must reject database drivers without a supported named lock.');

    $integrationDb = new FakeConnection();
    \Bitrix\Main\Application::$connection = $integrationDb;
    $installer = new class extends vendor_advancedmodule {
        public function InstallEvents()
        {
            return true;
        }

        public function UnInstallEvents()
        {
            return true;
        }

        public function InstallAgents()
        {
            return true;
        }

        public function UnInstallAgents()
        {
            return true;
        }
    };

    $assert($installer->DoInstall(), 'Installer should run migrations through InstallDB().');
    $assert($integrationDb->isTableExists('b_vendor_advancedmodule_example'), 'InstallDB should apply the example migration.');
    $assert($installer->DoInstall(), 'A repeated install should check for newly shipped migrations.');
    $assert($integrationDb->lockCalls === 2, 'Repeated install should run the migration runner again.');
    $assert(count($integrationDb->tables['b_vendor_advancedmodule_migrations']['rows']) === 1, 'Repeated install must not duplicate ledger entries.');
    $assert($installer->DoUninstall(), 'Uninstall should complete.');
    $assert($integrationDb->isTableExists('b_vendor_advancedmodule_migrations'), 'Uninstall must preserve migration ledger by default.');
    $assert($integrationDb->isTableExists('b_vendor_advancedmodule_example'), 'Uninstall must preserve application data by default.');

    echo "Advanced module migrations smoke test: OK\n";
}
