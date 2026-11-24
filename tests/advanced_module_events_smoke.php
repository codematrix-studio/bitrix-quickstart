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

namespace Bitrix\Main {
    final class EventManager
    {
        private static ?self $instance = null;
        public array $registered = [];
        public array $unregistered = [];
        public array $active = [];

        public static function getInstance(): self
        {
            return self::$instance ??= new self();
        }

        public function registerEventHandler(
            string $fromModuleId,
            string $eventType,
            string $toModuleId,
            string $toClass,
            string $toMethod,
            int $sort = 100,
            string $toPath = '',
            array $toMethodArg = []
        ): void {
            $arguments = func_get_args();
            $this->registered[] = $arguments;
            $this->active[implode('|', array_slice($arguments, 0, 5))] = true;
        }

        public bool $failUnregister = false;

        public function unRegisterEventHandler(
            string $fromModuleId,
            string $eventType,
            string $toModuleId,
            string $toClass,
            string $toMethod,
            string $toPath = '',
            array $toMethodArg = []
        ): void {
            if ($this->failUnregister) {
                throw new \RuntimeException('Тестовая ошибка снятия обработчика.');
            }
            $arguments = func_get_args();
            $this->unregistered[] = $arguments;
            unset($this->active[implode('|', array_slice($arguments, 0, 5))]);
        }
    }
}

namespace {
    final class AdvancedAgentResult
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
        private static array $agents = [];

        public static function GetList(array $order, array $filter): AdvancedAgentResult
        {
            foreach (self::$agents as $agent) {
                if (
                    ($filter['=NAME'] ?? null) === $agent['NAME']
                    && ($filter['MODULE_ID'] ?? null) === $agent['MODULE_ID']
                    && ($filter['USER_ID'] ?? null) === $agent['USER_ID']
                ) {
                    return new AdvancedAgentResult($agent);
                }
            }

            return new AdvancedAgentResult([]);
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
        ): int {
            self::$agents[] = [
                'NAME' => $name,
                'MODULE_ID' => $module,
                'USER_ID' => $userId,
            ];

            return count(self::$agents);
        }

        public static function RemoveAgent(string $name, string $module = '', $userId = false): void
        {
            self::$agents = array_values(array_filter(
                self::$agents,
                static fn (array $agent): bool => !(
                    $agent['NAME'] === $name
                    && $agent['MODULE_ID'] === $module
                    && $agent['USER_ID'] === $userId
                )
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
        public ?string $exceptionMessage = null;

        public function ThrowException(string $message): void
        {
            $this->exceptionMessage = $message;
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

    $manager = \Bitrix\Main\EventManager::getInstance();
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
    $assert(IsModuleInstalled('vendor.advancedmodule'), 'Install should register the module.');
    $assert(count($manager->registered) === 1, 'Install should register exactly one persistent handler.');

    $registration = $manager->registered[0];
    $assert($registration[0] === 'main', 'Handler source module should be main.');
    $assert($registration[1] === 'OnAfterUserAdd', 'Unexpected event type.');
    $assert($registration[2] === 'vendor.advancedmodule', 'Handler target module should match module ID.');
    $assert($registration[3] === \Vendor\AdvancedModule\Event\ExampleEventHandler::class, 'Unexpected handler class.');
    $assert($registration[4] === 'onAfterUserAdd', 'Unexpected handler method.');
    $assert($registration[5] === 100 && $registration[6] === '' && $registration[7] === [], 'Unexpected registration options.');

    $assert($installer->DoInstall(), 'Repeated install should remain successful.');
    $assert(count($manager->registered) === 1, 'A healthy repeated install must not register the handler again.');

    $assert($installer->DoUninstall(), 'Advanced module uninstall should succeed.');
    $assert(!IsModuleInstalled('vendor.advancedmodule'), 'Uninstall should unregister the module.');
    $assert(count($manager->unregistered) === 1, 'Uninstall should remove exactly one persistent handler.');

    $unregistration = $manager->unregistered[0];
    $assert(
        $unregistration === [
            $registration[0],
            $registration[1],
            $registration[2],
            $registration[3],
            $registration[4],
            $registration[6],
            $registration[7],
        ],
        'Unregister must use the same identity tuple as registration, excluding sort.'
    );

    // Ошибка снятия события после удаления файлов и агента должна восстановить ресурсы.
    $assert($installer->DoInstall(), 'Install before uninstall recovery test should succeed.');
    $manager->failUnregister = true;
    $assert(!$installer->DoUninstall(), 'Uninstall should fail when event unregistration throws.');
    $assert(IsModuleInstalled('vendor.advancedmodule'), 'A failed uninstall must keep the module registered.');
    $assert(count($manager->active) === 1, 'A failed uninstall must not duplicate an event handler that was not removed.');
    $restoredAgent = \CAgent::GetList(['ID' => 'ASC'], [
        '=NAME' => \Vendor\AdvancedModule\Agent\ExampleAgent::class . '::run();',
        'MODULE_ID' => 'vendor.advancedmodule',
        'USER_ID' => false,
    ])->Fetch();
    $assert($restoredAgent !== [], 'A failed uninstall must restore the removed agent.');
    $manager->failUnregister = false;
    $assert($installer->DoUninstall(), 'Uninstall should succeed after the failure is resolved.');
    $assert($manager->active === [], 'Successful retry should remove the active event handler.');
    $assert(!IsModuleInstalled('vendor.advancedmodule'), 'Successful retry should unregister the module.');

    echo "Advanced module lifecycle/events smoke test: OK\n";
}
