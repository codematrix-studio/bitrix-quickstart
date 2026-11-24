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
            $this->registered[] = func_get_args();
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
            $this->unregistered[] = func_get_args();
        }
    }
}

namespace {
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

    require_once __DIR__ . '/../templates/modules/advanced/lib/Event/EventRegistry.php';
    require_once __DIR__ . '/../templates/modules/advanced/install/index.php';

    $assert = static function (bool $condition, string $message): void {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    };

    $manager = \Bitrix\Main\EventManager::getInstance();
    $installer = new vendor_advancedmodule();

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

    echo "Advanced module lifecycle/events smoke test: OK\n";
}
