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

namespace {
    define('B_PROLOG_INCLUDED', true);

    /** @var array<string, bool> $installedModules */
    $installedModules = [];

    /** @var list<string> $lifecycleLog */
    $lifecycleLog = [];

    final class CModule
    {
    }

    final class TestApplication
    {
        public ?string $exceptionMessage = null;

        public function ThrowException(string $message): void
        {
            $this->exceptionMessage = $message;
        }
    }

    function IncludeModuleLangFile(string $file): void
    {
    }

    function IsModuleInstalled(string $moduleId): bool
    {
        global $installedModules;

        return $installedModules[$moduleId] ?? false;
    }

    function RegisterModule(string $moduleId): void
    {
        global $installedModules;

        $installedModules[$moduleId] = true;
    }

    function UnRegisterModule(string $moduleId): void
    {
        global $installedModules;

        unset($installedModules[$moduleId]);
    }

    require_once __DIR__ . '/../templates/modules/basic/install/index.php';

    /**
     * Проверяет условие и завершает тест при его нарушении.
     *
     * @param bool $condition Проверяемое условие.
     * @param string $message Сообщение об ошибке.
     *
     * @return void
     */
    $assert = static function (bool $condition, string $message): void {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    };

    /**
     * Проверяет успешную установку и обратный порядок отката.
     */
    final class SuccessfulModuleInstaller extends vendor_module
    {
        public function InstallDB()
        {
            $GLOBALS['lifecycleLog'][] = 'install-db';

            return true;
        }

        public function InstallEvents()
        {
            $GLOBALS['lifecycleLog'][] = 'install-events';

            return true;
        }

        public function InstallFiles()
        {
            $GLOBALS['lifecycleLog'][] = 'install-files';

            return true;
        }

        public function UnInstallFiles()
        {
            $GLOBALS['lifecycleLog'][] = 'uninstall-files';

            return true;
        }

        public function UnInstallEvents()
        {
            $GLOBALS['lifecycleLog'][] = 'uninstall-events';

            return true;
        }

        public function UnInstallDB()
        {
            $GLOBALS['lifecycleLog'][] = 'uninstall-db';

            return true;
        }
    }

    /**
     * Имитирует ошибку этапа регистрации событий.
     */
    final class FailingEventsInstaller extends SuccessfulModuleInstaller
    {
        public function InstallEvents()
        {
            $GLOBALS['lifecycleLog'][] = 'install-events';

            return false;
        }
    }

    /**
     * Имитирует ситуацию, когда откат событий завершился ошибкой.
     */
    final class FailingRollbackInstaller extends FailingEventsInstaller
    {
        public function UnInstallEvents()
        {
            $GLOBALS['lifecycleLog'][] = 'uninstall-events';

            return false;
        }
    }

    /**
     * Имитирует ошибку удаления файлов.
     */
    final class FailingUninstallInstaller extends SuccessfulModuleInstaller
    {
        public function UnInstallFiles()
        {
            $GLOBALS['lifecycleLog'][] = 'uninstall-files';

            return false;
        }
    }

    $GLOBALS['APPLICATION'] = new TestApplication();

    // Успешная установка должна выполнить этапы в прямом порядке.
    $GLOBALS['installedModules'] = [];
    $GLOBALS['lifecycleLog'] = [];
    $installer = new SuccessfulModuleInstaller();
    $assert($installer->DoInstall(), 'Успешная установка должна вернуть true.');
    $assert(IsModuleInstalled('vendor.module'), 'После установки модуль должен быть зарегистрирован.');
    $assert(
        $GLOBALS['lifecycleLog'] === ['install-db', 'install-events', 'install-files'],
        'Этапы установки выполнены в неверном порядке.'
    );

    // При ошибке события и база должны откатиться в обратном порядке.
    $GLOBALS['installedModules'] = [];
    $GLOBALS['lifecycleLog'] = [];
    $installer = new FailingEventsInstaller();
    $assert(!$installer->DoInstall(), 'Ошибка этапа установки должна вернуть false.');
    $assert(!IsModuleInstalled('vendor.module'), 'После успешного отката регистрация должна быть снята.');
    $assert(
        $GLOBALS['lifecycleLog'] === [
            'install-db',
            'install-events',
            'uninstall-events',
            'uninstall-db',
        ],
        'Откат должен включать неуспешный этап и выполняться в обратном порядке.'
    );

    // При неудачном откате регистрация сохраняется для последующего восстановления.
    $GLOBALS['installedModules'] = [];
    $GLOBALS['lifecycleLog'] = [];
    $installer = new FailingRollbackInstaller();
    $assert(!$installer->DoInstall(), 'Неудачный откат не должен считаться успешной установкой.');
    $assert(IsModuleInstalled('vendor.module'), 'При неудачном откате модуль должен остаться зарегистрированным.');

    // При ошибке удаления регистрация также должна сохраняться.
    $GLOBALS['installedModules'] = ['vendor.module' => true];
    $GLOBALS['lifecycleLog'] = [];
    $installer = new FailingUninstallInstaller();
    $assert(!$installer->DoUninstall(), 'Ошибка удаления должна вернуть false.');
    $assert(IsModuleInstalled('vendor.module'), 'При ошибке удаления регистрация должна сохраниться.');
    $assert(
        $GLOBALS['lifecycleLog'] === ['uninstall-files'],
        'После ошибки удаления последующие этапы не должны запускаться.'
    );

    // Полное удаление должно выполнить все этапы и снять регистрацию.
    $GLOBALS['installedModules'] = ['vendor.module' => true];
    $GLOBALS['lifecycleLog'] = [];
    $installer = new SuccessfulModuleInstaller();
    $assert($installer->DoUninstall(), 'Успешное удаление должно вернуть true.');
    $assert(!IsModuleInstalled('vendor.module'), 'После удаления регистрация должна быть снята.');
    $assert(
        $GLOBALS['lifecycleLog'] === ['uninstall-files', 'uninstall-events', 'uninstall-db'],
        'Этапы удаления выполнены в неверном порядке.'
    );

    echo "Module installer lifecycle smoke test: OK\n";
}
