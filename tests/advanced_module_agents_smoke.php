<?php

declare(strict_types=1);

namespace Bitrix\Main\Localization {
    final class Loc
    {
        public static function getMessage(string $code): string
        {
            return $code;
        }
    }
}

namespace Bitrix\Main {
    final class EventManager
    {
        private static ?self $instance = null;

        public static function getInstance(): self
        {
            return self::$instance ??= new self();
        }

        public function registerEventHandlerCompatible(
            string $fromModuleId,
            string $eventType,
            string $toModuleId,
            string $toClass,
            string $toMethod,
            int $sort = 100,
            string $toPath = '',
            array $toMethodArg = []
        ): void {
        }

        public function unRegisterEventHandler(
            string $fromModuleId,
            string $eventType,
            string $toModuleId,
            string $toClass,
            string $toMethod,
            string $toPath = '',
            array $toMethodArg = []
        ): void {
        }
    }
}

namespace Bitrix\Main\Config {
    final class Option
    {
        private static array $options = [];

        public static function get(string $moduleId, string $name, string $default = ''): string
        {
            return self::$options[$moduleId][$name] ?? $default;
        }

        public static function set(string $moduleId, string $name, string $value): void
        {
            self::$options[$moduleId][$name] = $value;
        }

        public static function delete(string $moduleId, array $filter = []): void
        {
            if (isset($filter['name'])) {
                unset(self::$options[$moduleId][$filter['name']]);
            }
        }
    }
}

namespace {
    final class AgentResult
    {
        private array $row;

        public function __construct(array $row)
        {
            $this->row = $row;
        }

        public function Fetch(): array
        {
            return $this->row;
        }
    }

    final class CAgent
    {
        public static array $agents = [];
        public static array $removed = [];
        public static bool $failNextAdd = false;
        public static bool $failNextRemove = false;

        public static function GetList(array $order, array $filter): AgentResult
        {
            foreach (self::$agents as $agent) {
                if (
                    ($filter['=NAME'] ?? null) === $agent['NAME']
                    && ($filter['MODULE_ID'] ?? null) === $agent['MODULE_ID']
                    && ($filter['USER_ID'] ?? null) === $agent['USER_ID']
                ) {
                    return new AgentResult($agent);
                }
            }

            return new AgentResult([]);
        }

        public static function AddAgent(
            string $name,
            string $module,
            string $period = 'N',
            int $interval = 86400,
            string $datecheck = '',
            string $active = 'Y',
            string $nextExec = '',
            int $sort = 100,
            $userId = false,
            bool $existError = true
        ) {
            if (self::$failNextAdd) {
                self::$failNextAdd = false;
                return false;
            }

            self::$agents[] = [
                'ID' => count(self::$agents) + 1,
                'NAME' => $name,
                'MODULE_ID' => $module,
                'USER_ID' => $userId,
                'PERIOD' => $period,
                'INTERVAL' => $interval,
            ];

            return count(self::$agents);
        }

        public static function RemoveAgent(string $name, string $module = '', $userId = false): void
        {
            self::$removed[] = [$name, $module, $userId];
            if (self::$failNextRemove) {
                self::$failNextRemove = false;
                return;
            }
            self::$agents = array_values(array_filter(
                self::$agents,
                static function (array $agent) use ($name, $module, $userId): bool {
                    return !(
                        $agent['NAME'] === $name
                        && $agent['MODULE_ID'] === $module
                        && $agent['USER_ID'] === $userId
                    );
                }
            ));
        }
    }

    define('B_PROLOG_INCLUDED', true);
    $GLOBALS['installedModules'] = [];
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

        public function GetException()
        {
            return null;
        }
    };

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
        CAgent::$agents = array_values(array_filter(
            CAgent::$agents,
            static function (array $agent) use ($moduleId): bool {
                return $agent['MODULE_ID'] !== $moduleId;
            }
        ));
    }

    require_once __DIR__ . '/../templates/modules/advanced/lib/Agent/ExampleAgent.php';
    require_once __DIR__ . '/../templates/modules/advanced/lib/Agent/AgentRegistry.php';
    require_once __DIR__ . '/../templates/modules/advanced/lib/Event/EventRegistry.php';
    require_once __DIR__ . '/../templates/modules/advanced/install/index.php';

    $assert = static function (bool $condition, string $message): void {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    };

    $callback = 'Vendor\AdvancedModule\Agent\ExampleAgent::run();';
    $assert(
        \Vendor\AdvancedModule\Agent\ExampleAgent::run() === $callback,
        'Agent callback must return the same stable call string without a leading slash.'
    );

    $installer = new class extends vendor_advancedmodule {
        public function InstallDB()
        {
            return true;
        }

        public function UnInstallDB()
        {
            return true;
        }
    };
    $assert($installer->DoInstall(), 'Advanced module install should succeed.');
    $assert(count(CAgent::$agents) === 1, 'Install should register one system agent.');
    $assert(CAgent::$agents[0]['NAME'] === $callback, 'Unexpected agent callback string.');
    $assert(CAgent::$agents[0]['MODULE_ID'] === 'vendor.advancedmodule', 'Unexpected agent module ID.');
    $assert(CAgent::$agents[0]['USER_ID'] === false, 'Example should register a system agent.');

    \Vendor\AdvancedModule\Agent\AgentRegistry::register();
    $assert(count(CAgent::$agents) === 1, 'Repeated registration must not create a duplicate.');

    $assert($installer->DoUninstall(), 'Advanced module uninstall should succeed.');
    $assert(count(CAgent::$removed) === 1, 'Uninstall should explicitly remove the registered agent.');
    $assert(CAgent::$removed[0] === [$callback, 'vendor.advancedmodule', false], 'RemoveAgent must use the exact callback, module ID, and user ID.');
    $assert(CAgent::$agents === [], 'Uninstall should leave no registered agent.');

    $assert($installer->DoUninstall(), 'Repeated uninstall should remain successful.');

    // Если RemoveAgent не удалил запись, установщик должен остановить удаление.
    $assert($installer->DoInstall(), 'Reinstall before agent removal failure test should succeed.');
    CAgent::$failNextRemove = true;
    $assert(!$installer->DoUninstall(), 'Uninstall should fail when the agent remains registered.');
    $assert(IsModuleInstalled('vendor.advancedmodule'), 'A failed agent removal must keep the module registered.');
    $assert(count(CAgent::$agents) === 1, 'The failed removal test must leave the agent registered.');
    $assert($installer->DoUninstall(), 'Uninstall should succeed after the agent removal problem is resolved.');
    $assert(CAgent::$agents === [], 'Successful retry must remove the agent.');



    $removedBeforeRollback = count(CAgent::$removed);
    $failingInstaller = new class extends vendor_advancedmodule {
        public function InstallDB()
        {
            return true;
        }

        public function UnInstallDB()
        {
            return true;
        }

        public function InstallFiles()
        {
            return false;
        }
    };
    $assert(!$failingInstaller->DoInstall(), 'A later install-stage failure should fail installation.');
    $assert(!IsModuleInstalled('vendor.advancedmodule'), 'A successful rollback should unregister the module.');
    $assert(CAgent::$agents === [], 'A later install-stage failure must roll back the agent.');
    $assert(count(CAgent::$removed) === $removedBeforeRollback + 1, 'Rollback must remove the agent registered by the failed installation.');

    CAgent::$failNextAdd = true;
    $failed = false;
    try {
        \Vendor\AdvancedModule\Agent\AgentRegistry::register();
    } catch (\RuntimeException $exception) {
        $failed = true;
    }
    $assert($failed, 'A failed AddAgent call must abort registration.');

    echo "Advanced module agents smoke test: OK\n";
}
